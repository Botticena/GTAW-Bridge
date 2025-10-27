<?php
defined('ABSPATH') or exit;

/* ========= DISCORD CORE FUNCTIONALITY ========= */
/*
 * This file contains core Discord functionality that is shared across submodules:
 * - API communication utilities with intelligent rate limiting
 * - Advanced caching with optimized durations
 * - Common helper functions with error recovery
 * - Performance monitoring for API requests
 * - Shared hooks and filters
 *
 * @version 2.0
 */

/**
 * Cache durations for different Discord data types (in seconds)
 */
define('GTAW_DISCORD_ROLE_CACHE_DURATION', 30 * MINUTE_IN_SECONDS);      // 30 minutes for roles
define('GTAW_DISCORD_MEMBER_CACHE_DURATION', 15 * MINUTE_IN_SECONDS);    // 15 minutes for member data
define('GTAW_DISCORD_SERVER_CHECK_DURATION', 30 * MINUTE_IN_SECONDS);    // 30 minutes for server membership
define('GTAW_DISCORD_CHECKOUT_CACHE_DURATION', 5 * MINUTE_IN_SECONDS);   // 5 minutes during checkout
define('GTAW_DISCORD_ERROR_CACHE_DURATION', 5 * MINUTE_IN_SECONDS);      // 5 minutes for error caching
define('GTAW_DISCORD_RATE_LIMIT_THRESHOLD', 5);                          // Remaining requests before rate limit protection

/**
 * Discord API settings and state management
 */
class GTAW_Discord_API {
    // Singleton instance
    private static $instance = null;
    
    // API state tracking
    private $rate_limit_remaining = null;
    private $rate_limit_reset = null;
    private $last_response_headers = [];
    private $error_count = 0;
    private $request_count = 0;
    
    // Settings cache
    private $bot_token = null;
    private $guild_id = null;
    
    /**
     * Get singleton instance
     *
     * @return GTAW_Discord_API Instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton
     */
    private function __construct() {
        // Get settings on first instantiation
        $this->load_settings();
    }
    
    /**
     * Load Discord API settings
     */
    public function load_settings() {
        $this->bot_token = get_option('gtaw_discord_bot_token', '');
        $this->guild_id = get_option('gtaw_discord_guild_id', '');
    }
    
    /**
     * Get bot token with lazy loading
     *
     * @return string Bot token
     */
    public function get_bot_token() {
        if ($this->bot_token === null) {
            $this->bot_token = get_option('gtaw_discord_bot_token', '');
        }
        return $this->bot_token;
    }
    
    /**
     * Get guild ID with lazy loading
     *
     * @return string Guild ID
     */
    public function get_guild_id() {
        if ($this->guild_id === null) {
            $this->guild_id = get_option('gtaw_discord_guild_id', '');
        }
        return $this->guild_id;
    }
    
    /**
     * Process response headers to track rate limits
     *
     * @param array $headers Response headers
     */
    public function process_headers($headers) {
        $this->last_response_headers = $headers;
        
        // Extract rate limit information
        if (isset($headers['x-ratelimit-remaining'])) {
            $this->rate_limit_remaining = (int)$headers['x-ratelimit-remaining'];
        }
        
        if (isset($headers['x-ratelimit-reset'])) {
            $this->rate_limit_reset = (int)$headers['x-ratelimit-reset'];
        }
        
        // Store rate limit data in transient for cross-request awareness
        if ($this->rate_limit_remaining !== null && $this->rate_limit_reset !== null) {
            set_transient('gtaw_discord_rate_limit', [
                'remaining' => $this->rate_limit_remaining,
                'reset' => $this->rate_limit_reset,
                'updated' => time()
            ], 1 * HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Check if we're close to being rate limited
     *
     * @return bool True if close to rate limit
     */
    public function is_rate_limited() {
        // First check our instance variables from the current request
        if ($this->rate_limit_remaining !== null && $this->rate_limit_remaining <= GTAW_DISCORD_RATE_LIMIT_THRESHOLD) {
            return true;
        }
        
        // Then check stored rate limit data from previous requests
        $rate_limit_data = get_transient('gtaw_discord_rate_limit');
        if ($rate_limit_data && $rate_limit_data['remaining'] <= GTAW_DISCORD_RATE_LIMIT_THRESHOLD) {
            // Check if reset time has passed
            if (time() < $rate_limit_data['reset']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Track request count and errors for monitoring
     *
     * @param bool $is_error Whether the request resulted in an error
     */
    public function track_request($is_error = false) {
        $this->request_count++;
        if ($is_error) {
            $this->error_count++;
        }
        
        // Store daily stats
        $today = date('Y-m-d');
        $stats = get_option('gtaw_discord_api_stats', []);
        
        if (!isset($stats[$today])) {
            $stats[$today] = ['requests' => 0, 'errors' => 0];
        }
        
        $stats[$today]['requests']++;
        if ($is_error) {
            $stats[$today]['errors']++;
        }
        
        // Keep only the last 30 days
        if (count($stats) > 30) {
            $stats = array_slice($stats, -30, 30, true);
        }
        
        update_option('gtaw_discord_api_stats', $stats);
    }
    
    /**
     * Get API stats for monitoring
     *
     * @return array API stats
     */
    public function get_stats() {
        return [
            'instance' => [
                'requests' => $this->request_count,
                'errors' => $this->error_count,
                'error_rate' => $this->request_count ? round(($this->error_count / $this->request_count) * 100, 2) : 0
            ],
            'historical' => get_option('gtaw_discord_api_stats', []),
            'rate_limit' => [
                'remaining' => $this->rate_limit_remaining,
                'reset' => $this->rate_limit_reset
            ]
        ];
    }
}

/**
 * Make a request to Discord API with enhanced error handling and caching
 *
 * @param string $endpoint The API endpoint (without leading slash)
 * @param array $args Request arguments (@see wp_remote_request)
 * @param string $method HTTP method (GET, POST, etc.)
 * @param int $retry_count Current retry attempt (for internal use)
 * @return array|WP_Error Response or error
 */
function gtaw_discord_api_request($endpoint, $args = [], $method = 'GET', $retry_count = 0) {
    // Start timing the request for performance monitoring
    $start_time = microtime(true);
    
    // Get API instance for rate limiting and tracking
    $api = GTAW_Discord_API::get_instance();
    
    // Generate a cache key for GET requests if appropriate
    $use_cache = ($method === 'GET' && strpos($endpoint, '/messages') === false);
    $cache_key = null;
    
    if ($use_cache) {
        $cache_key = 'discord_api_' . md5($endpoint . serialize($args));
        $cached_response = get_transient($cache_key);
        
        if ($cached_response !== false) {
            // Track the request as a cache hit
            $api->track_request(false);
            return $cached_response;
        }
    }
    
    // Check if we're close to being rate limited
    if ($api->is_rate_limited() && $retry_count == 0) {
        // Log the rate limiting
        gtaw_add_log('discord', 'Rate Limit', "Discord API rate limit protection activated - delaying request", 'warning');
        
        // Sleep for a short time before retrying
        sleep(2);
        
        // Increment retry count and try again
        return gtaw_discord_api_request($endpoint, $args, $method, $retry_count + 1);
    }
    
    $bot_token = $api->get_bot_token();
    
    if (empty($bot_token)) {
        gtaw_add_log('discord', 'API Error', "Discord Bot Token is missing", 'error');
        return new WP_Error('missing_token', 'Discord Bot Token is required');
    }
    
    // Set default headers
    if (!isset($args['headers'])) {
        $args['headers'] = [];
    }
    
    // Add authorization header
    $args['headers']['Authorization'] = 'Bot ' . $bot_token;
    
    // Add user agent for proper identification
    if (!isset($args['headers']['User-Agent'])) {
        $args['headers']['User-Agent'] = 'GTAW-Bridge/2.0 (WordPress/' . get_bloginfo('version') . '; +' . home_url() . ')';
    }
    
    // Full API URL
    $api_url = 'https://discord.com/api/v10/' . ltrim($endpoint, '/');
    
    // Set the HTTP method
    $args['method'] = $method;
    
    // Add timeout and avoid blocking
    if (!isset($args['timeout'])) {
        $args['timeout'] = 15; // 15 seconds timeout
    }
    
    // Make the request using wp_remote_request which supports all HTTP methods
    $response = wp_remote_request($api_url, $args);
    
    // Track the request for monitoring
    $is_error = is_wp_error($response);
    $api->track_request($is_error);
    
    // Calculate request time for logging
    $request_time = microtime(true) - $start_time;
    
    // Handle errors
    if ($is_error) {
        // Log the error with request time
        gtaw_add_log('discord', 'API Error', sprintf(
            "Discord API request failed (%.2fs): %s - Endpoint: %s",
            $request_time,
            $response->get_error_message(),
            $endpoint
        ), 'error');
        
        // If this is a temporary error and we haven't retried too many times
        if ($retry_count < 3 && gtaw_is_temporary_error($response)) {
            // Exponential backoff with jitter
            $delay = pow(2, $retry_count) + (rand(0, 1000) / 1000);
            
            // Log the retry
            gtaw_add_log('discord', 'Retry', "Retrying Discord API request after {$delay}s delay (attempt " . ($retry_count + 1) . "/3)", 'warning');
            
            // Wait and retry
            usleep($delay * 1000000);
            return gtaw_discord_api_request($endpoint, $args, $method, $retry_count + 1);
        }
        
        return $response;
    }
    
    // Process headers for rate limiting awareness
    $api->process_headers(wp_remote_retrieve_headers($response));
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    // Check for API errors
    if ($response_code < 200 || $response_code >= 300) {
        $error_message = wp_remote_retrieve_response_message($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['message'])) {
            $error_message = $body['message'];
        }
        
        // Check if rate limited
        if ($response_code === 429) {
            $retry_after = isset($body['retry_after']) ? $body['retry_after'] : 5;
            
            // Log the rate limiting with request time
            gtaw_add_log('discord', 'Rate Limit', sprintf(
                "Discord API rate limited (%.2fs). Retry after %s seconds. Endpoint: %s",
                $request_time,
                $retry_after,
                $endpoint
            ), 'warning');
            
            // Set cache with short duration for error to prevent hammering
            if ($cache_key) {
                set_transient('discord_error_' . $cache_key, true, GTAW_DISCORD_ERROR_CACHE_DURATION);
            }
            
            // If we haven't retried too many times, wait and retry
            if ($retry_count < 2) {
                // Add a small buffer to the retry time
                $retry_after = $retry_after + 0.5;
                sleep($retry_after);
                return gtaw_discord_api_request($endpoint, $args, $method, $retry_count + 1);
            }
        } else {
            // Log the error with request time
            gtaw_add_log('discord', 'API Error', sprintf(
                "Discord API Error (%.2fs): (%d) %s - Endpoint: %s",
                $request_time,
                $response_code,
                $error_message,
                $endpoint
            ), 'error');
        }
        
        return new WP_Error(
            'discord_api_error',
            sprintf('Discord API Error (%d): %s', $response_code, $error_message),
            [
                'status' => $response_code,
                'response' => $body,
                'endpoint' => $endpoint,
                'request_time' => $request_time
            ]
        );
    }
    
    // Log successful request with timing for performance monitoring
    if (defined('WP_DEBUG') && WP_DEBUG) {
        gtaw_add_log('discord', 'API Request', sprintf(
            "Discord API request successful (%.2fs): %s",
            $request_time,
            $endpoint
        ), 'success');
    }
    
    // For successful GET requests, cache the response
    if ($use_cache && $cache_key) {
        // Determine appropriate cache duration based on endpoint
        $cache_duration = HOUR_IN_SECONDS; // Default 1 hour
        
        if (strpos($endpoint, '/roles') !== false) {
            $cache_duration = GTAW_DISCORD_ROLE_CACHE_DURATION;
        } elseif (strpos($endpoint, '/members') !== false) {
            $cache_duration = GTAW_DISCORD_MEMBER_CACHE_DURATION;
        }
        
        set_transient($cache_key, $response, $cache_duration);
    }
    
    return $response;
}

/**
 * Helper to determine if an error is temporary and should be retried
 *
 * @param WP_Error $error The error to check
 * @return bool Whether the error is temporary
 */
function gtaw_is_temporary_error($error) {
    if (!is_wp_error($error)) {
        return false;
    }
    
    $error_code = $error->get_error_code();
    $temporary_codes = [
        'http_request_failed',
        'request_timeout',
        'internal_server_error',
        'service_unavailable',
        'gateway_timeout',
    ];
    
    return in_array($error_code, $temporary_codes) || strpos($error->get_error_message(), 'cURL error 28') !== false;
}

/**
 * Get Discord server roles with intelligent caching
 *
 * @param bool $force_refresh Force refresh from API instead of using cache
 * @return array|WP_Error List of roles or error
 */
function gtaw_get_discord_roles($force_refresh = false) {
    $cache_key = 'gtaw_discord_roles';
    
    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
    }
    
    return gtaw_fetch_discord_roles();
}

/**
 * Fetch Discord roles from API with error handling
 *
 * @return array|WP_Error List of roles or error
 */
function gtaw_fetch_discord_roles() {
    $guild_id = GTAW_Discord_API::get_instance()->get_guild_id();
    
    if (empty($guild_id)) {
        gtaw_add_log('discord', 'Error', "Discord Guild ID is missing", 'error');
        return new WP_Error('missing_guild_id', 'Discord Guild ID is required');
    }
    
    $response = gtaw_discord_api_request("guilds/{$guild_id}/roles");
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $roles = json_decode(wp_remote_retrieve_body($response), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        gtaw_add_log('discord', 'Error', "Failed to parse Discord roles response: " . json_last_error_msg(), 'error');
        return new WP_Error('json_parse_error', 'Failed to parse Discord roles response: ' . json_last_error_msg());
    }
    
    // Sort roles by position (higher position = higher in hierarchy)
    usort($roles, function($a, $b) {
        return $b['position'] <=> $a['position'];
    });
    
    // Cache roles with longer duration as they change infrequently
    set_transient('gtaw_discord_roles', $roles, GTAW_DISCORD_ROLE_CACHE_DURATION);
    
    return $roles;
}

/**
 * Get Discord member data for a user with optimized caching
 *
 * @param string $discord_id The Discord user ID
 * @param bool $force_refresh Force refresh from API instead of using cache
 * @param int $custom_cache_duration Optional custom cache duration
 * @return array|WP_Error Member data or error
 */
function gtaw_get_discord_member($discord_id, $force_refresh = false, $custom_cache_duration = null) {
    if (empty($discord_id)) {
        return new WP_Error('missing_discord_id', 'Discord user ID is required');
    }
    
    // Check error cache first - prevents hammering API with invalid IDs
    $error_key = 'discord_member_error_' . $discord_id;
    if (!$force_refresh && get_transient($error_key)) {
        return new WP_Error('member_not_found', 'Discord member not found in server (cached result)');
    }
    
    // Check cache first
    $cache_key = 'discord_member_' . $discord_id;
    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            // If we get a 'not_found' string, return appropriate error
            if ($cached === 'not_found') {
                return new WP_Error('member_not_found', 'Discord member not found in server');
            }
            return $cached;
        }
    }
    
    $guild_id = GTAW_Discord_API::get_instance()->get_guild_id();
    
    if (empty($guild_id)) {
        gtaw_add_log('discord', 'Error', "Discord Guild ID is missing", 'error');
        return new WP_Error('missing_guild_id', 'Discord Guild ID is required');
    }
    
    $response = gtaw_discord_api_request("guilds/{$guild_id}/members/{$discord_id}");
    
    if (is_wp_error($response)) {
        // For 404 errors (member not in server), cache that result
        if (is_wp_error($response) && isset($response->get_error_data()['status']) && $response->get_error_data()['status'] === 404) {
            // Cache negative result for a shorter period
            set_transient($cache_key, 'not_found', GTAW_DISCORD_ERROR_CACHE_DURATION);
            set_transient($error_key, true, GTAW_DISCORD_ERROR_CACHE_DURATION);
        }
        return $response;
    }
    
    $member_data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        gtaw_add_log('discord', 'Error', "Failed to parse Discord member response: " . json_last_error_msg(), 'error');
        return new WP_Error('json_parse_error', 'Failed to parse Discord member response: ' . json_last_error_msg());
    }
    
    // Determine cache duration
    $cache_duration = $custom_cache_duration ?? GTAW_DISCORD_MEMBER_CACHE_DURATION;
    
    // If we're on checkout, use shorter cache time
    if (function_exists('is_checkout') && is_checkout()) {
        $cache_duration = GTAW_DISCORD_CHECKOUT_CACHE_DURATION;
    }
    
    // Clear any error cache since we got a valid response
    delete_transient($error_key);
    
    // Cache the member data
    set_transient($cache_key, $member_data, $cache_duration);
    
    return $member_data;
}

/**
 * Get Discord roles for a specific user with efficiency improvements
 *
 * @param string $discord_id The Discord user ID
 * @param bool $force_refresh Force refresh from API
 * @return array|WP_Error Array of role IDs or error
 */
function gtaw_get_user_discord_roles($discord_id, $force_refresh = false) {
    // Direct cache for user roles - faster lookup
    $cache_key = 'discord_roles_' . $discord_id;
    
    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
    }
    
    // Try to get member data - this has its own caching
    $member = gtaw_get_discord_member($discord_id, $force_refresh);
    
    if (is_wp_error($member)) {
        // For member not found, cache empty roles to avoid repeated lookups
        if ($member->get_error_code() === 'member_not_found') {
            set_transient($cache_key, [], GTAW_DISCORD_ERROR_CACHE_DURATION);
            return [];
        }
        return $member;
    }
    
    // If roles key doesn't exist or is not an array, return empty array instead of error
    if (!isset($member['roles']) || !is_array($member['roles'])) {
        $roles = [];
    } else {
        $roles = $member['roles'];
    }
    
    // Cache the roles separately for faster access
    set_transient($cache_key, $roles, GTAW_DISCORD_MEMBER_CACHE_DURATION);
    
    return $roles;
}

/**
 * Check if a user is a member of the Discord server with more reliable caching
 *
 * @param string $discord_id The Discord user ID
 * @param bool $force_refresh Force a check instead of using cache
 * @return bool True if user is in the server
 */
function gtaw_is_user_in_discord_server($discord_id, $force_refresh = false) {
    if (empty($discord_id)) {
        return false;
    }
    
    // Dedicated cache key for server membership checks
    $cache_key = 'discord_in_server_' . $discord_id;
    
    // Check cache first to reduce API calls, unless forced check
    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            // If we're in a sensitive context (checkout, account page), use a shorter-lived cache
            if (is_account_page() || is_checkout()) {
                // If the cache is older than 5 minutes, ignore it and refresh
                $cache_time = get_option('_transient_timeout_' . $cache_key, 0);
                $cache_age = time() - ($cache_time - GTAW_DISCORD_SERVER_CHECK_DURATION);
                
                if ($cache_age < 5 * MINUTE_IN_SECONDS) {
                    return $cached === 'yes';
                }
                // Otherwise continue to refresh the data
            } else {
                return $cached === 'yes';
            }
        }
    }
    
    // For forced refresh, completely skip the member cache check
    if (!$force_refresh) {
        // We'll try to use the member data cache first before making a new API call
        $member_cache_key = 'discord_member_' . $discord_id;
        $cached_member = get_transient($member_cache_key);
        
        // If we have cached member data that's not an error string
        if ($cached_member !== false && $cached_member !== 'not_found' && !is_string($cached_member)) {
            $is_member = true;
            
            // Use a much shorter cache duration on sensitive pages
            $duration = (is_account_page() || is_checkout()) 
                ? 5 * MINUTE_IN_SECONDS  // 5 minutes on account/checkout pages
                : GTAW_DISCORD_SERVER_CHECK_DURATION;
                
            set_transient($cache_key, 'yes', $duration);
            return true;
        }
    }
    
    // If we don't have cached data or need a fresh check, make an API call
    $member = gtaw_get_discord_member($discord_id, true);
    $is_member = !is_wp_error($member);
    
    // Cache result with appropriate duration
    $cache_duration = GTAW_DISCORD_SERVER_CHECK_DURATION;
    
    // Use shorter duration for sensitive context
    if (is_account_page() || is_checkout()) {
        $cache_duration = 5 * MINUTE_IN_SECONDS; // 5 minutes
    }
    
    set_transient($cache_key, $is_member ? 'yes' : 'no', $cache_duration);
    
    return $is_member;
}

/**
 * Clear Discord role cache for a specific user
 *
 * @param string $discord_id The Discord user ID
 * @return bool True on success
 */
function gtaw_clear_discord_user_cache($discord_id) {
    if (empty($discord_id)) {
        return false;
    }
    
    // Clear all caches related to this user
    delete_transient('discord_member_' . $discord_id);
    delete_transient('discord_roles_' . $discord_id);
    delete_transient('discord_in_server_' . $discord_id);
    delete_transient('discord_member_error_' . $discord_id);
    
    // Log the cache clearing
    gtaw_add_log('discord', 'Cache', "Cleared Discord cache for user ID: {$discord_id}", 'success');
    
    return true;
}

/**
 * Clears all Discord caches with performance optimizations
 * 
 * @param string $type Optional. Specific cache type to clear ('roles', 'members', 'all')
 * @return int Number of caches cleared
 */
function gtaw_clear_all_discord_caches($type = 'all') {
    global $wpdb;
    
    $count = 0;
    $query_conditions = [];
    
    if ($type === 'all' || $type === 'roles') {
        $query_conditions[] = "option_name LIKE '%_transient_gtaw_discord_roles%'";
    }
    
    if ($type === 'all' || $type === 'members') {
        $query_conditions[] = "option_name LIKE '%_transient_discord_member_%'";
        $query_conditions[] = "option_name LIKE '%_transient_discord_roles_%'";
        $query_conditions[] = "option_name LIKE '%_transient_discord_in_server_%'";
    }
    
    if ($type === 'all') {
        $query_conditions[] = "option_name LIKE '%_transient_discord_api_%'";
    }
    
    if (empty($query_conditions)) {
        return 0;
    }
    
    // Get all Discord-related transients
    $query = "SELECT option_name FROM {$wpdb->options} WHERE " . implode(' OR ', $query_conditions);
    $discord_transients = $wpdb->get_col($query);
    
    if (empty($discord_transients)) {
        return 0;
    }
    
    // Process in batches for better performance
    $batches = array_chunk($discord_transients, 50);
    
    foreach ($batches as $batch) {
        foreach ($batch as $transient) {
            $name = str_replace('_transient_', '', $transient);
            delete_transient($name);
            $count++;
        }
    }
    
    // Also clear rate limit data
    delete_transient('gtaw_discord_rate_limit');
    
    gtaw_add_log('discord', 'Cache', "Cleared {$count} Discord caches of type: {$type}", 'success');
    return $count;
}

/**
 * Send a Discord message to a channel with enhanced error handling
 *
 * @param string $channel_id The channel ID to send to
 * @param string $content The message content
 * @param array $embeds Optional embeds to include
 * @param int $retry_count Current retry attempt (for internal use)
 * @return bool|WP_Error True on success or error
 */
function gtaw_discord_send_message($channel_id, $content, $embeds = [], $retry_count = 0) {
    if (empty($channel_id)) {
        return new WP_Error('missing_channel', 'Discord channel ID is required');
    }
    
    $body = ['content' => $content];
    
    if (!empty($embeds)) {
        $body['embeds'] = $embeds;
    }
    
    $args = [
        'body' => json_encode($body),
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ];
    
    $response = gtaw_discord_api_request("channels/{$channel_id}/messages", $args, 'POST');
    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        gtaw_add_log('discord', 'Error', "Failed to send Discord message: {$error_message}", 'error');
        
        // If this is a temporary error and we haven't retried too many times
        if ($retry_count < 2 && (
            strpos($error_message, 'cURL error') !== false || 
            strpos($error_message, 'rate limit') !== false
        )) {
            // Exponential backoff
            $delay = pow(2, $retry_count);
            
            // Wait and retry
            sleep($delay);
            return gtaw_discord_send_message($channel_id, $content, $embeds, $retry_count + 1);
        }
        
        return $response;
    }
    
    return true;
}

/**
 * Batch process multiple Discord API requests with rate limit awareness
 *
 * @param array $requests Array of request parameters
 * @param int $concurrency Max number of concurrent requests
 * @param int $delay_ms Milliseconds to delay between requests
 * @return array Results indexed same as input requests
 */
function gtaw_discord_batch_api_requests($requests, $concurrency = 3, $delay_ms = 250) {
    $results = [];
    $batches = array_chunk($requests, $concurrency);
    
    // Get API instance for rate limit tracking
    $api = GTAW_Discord_API::get_instance();
    
    foreach ($batches as $batch_index => $batch) {
        $batch_results = [];
        
        // Check if we're close to being rate limited
        if ($api->is_rate_limited()) {
            // Log the rate limiting
            gtaw_add_log('discord', 'Rate Limit', "Discord API batch processing paused due to rate limits", 'warning');
            
            // Pause for a bit before continuing
            sleep(5);
        }
        
        foreach ($batch as $index => $request) {
            $endpoint = $request['endpoint'];
            $args = $request['args'] ?? [];
            $method = $request['method'] ?? 'GET';
            
            $batch_results[$index] = gtaw_discord_api_request($endpoint, $args, $method);
            
            // Dynamic delay based on rate limit status
            if ($api->is_rate_limited()) {
                // Longer delay when close to rate limit
                usleep(1000000); // 1 second
            } else {
                // Standard delay to avoid rate limits
                usleep($delay_ms * 1000);
            }
        }
        
        $results = array_merge($results, $batch_results);
        
        // Add a longer delay between batches
        if (count($batches) > 1 && $batch_index < count($batches) - 1) {
            usleep(500000); // 500ms between batches
        }
    }
    
    return $results;
}

/**
 * Action hook that fires when a Discord account is linked to WordPress
 *
 * @param int $user_id WordPress user ID
 * @param string $discord_id Discord user ID
 */
function gtaw_discord_trigger_account_linked($user_id, $discord_id) {
    do_action('gtaw_discord_account_linked', $user_id, $discord_id);
    
    // Clear any cached data for this user to ensure fresh data
    gtaw_clear_discord_user_cache($discord_id);
}

/**
 * Register AJAX endpoint for Discord cache management
 */
function gtaw_register_discord_cache_endpoints() {
    add_action('wp_ajax_gtaw_clear_discord_cache', 'gtaw_ajax_clear_discord_cache');
}
add_action('init', 'gtaw_register_discord_cache_endpoints');

/**
 * AJAX handler for clearing Discord caches
 */
function gtaw_ajax_clear_discord_cache() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gtaw_discord_admin_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Get cache type to clear
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
    
    // Clear caches
    $count = gtaw_clear_all_discord_caches($type);
    
    wp_send_json_success([
        'message' => sprintf('Successfully cleared %d Discord caches.', $count),
        'count' => $count
    ]);
}

/**
 * Get Discord API statistics for admin dashboard
 *
 * @return array API statistics
 */
function gtaw_get_discord_api_stats() {
    return GTAW_Discord_API::get_instance()->get_stats();
}

/**
 * Backward compatibility function for legacy code
 * 
 * @deprecated 2.0 Use gtaw_discord_send_message() instead
 */
function gtaw_discord_api_send_message($channel_id, $content, $embeds = []) {
    return gtaw_discord_send_message($channel_id, $content, $embeds);
}