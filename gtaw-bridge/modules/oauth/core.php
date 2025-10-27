<?php
defined('ABSPATH') or exit;

/* ========= OAUTH CORE FUNCTIONALITY ========= */
/*
 * This file contains core OAuth functionality that is shared across submodules:
 * - API communication utilities
 * - Token handling
 * - Common helper functions
 * - Shared hooks and filters
 */

/* ========= API COMMUNICATION CONSTANTS ========= */

// API endpoints
define('GTAW_OAUTH_TOKEN_ENDPOINT', 'https://ucp.gta.world/oauth/token');
define('GTAW_OAUTH_USER_ENDPOINT', 'https://ucp.gta.world/api/user');
define('GTAW_OAUTH_AUTH_ENDPOINT', 'https://ucp.gta.world/oauth/authorize');

// Cache durations
define('GTAW_OAUTH_TOKEN_CACHE_DURATION', 60); // 60 seconds for tokens
define('GTAW_OAUTH_USER_CACHE_DURATION', 300); // 5 minutes for user data

/* ========= API COMMUNICATION UTILITIES ========= */

/**
 * Make a request to GTAW OAuth API for token exchange
 *
 * @param string $code The authorization code to exchange
 * @return array|WP_Error Response data or error
 */
function gtaw_oauth_exchange_token($code) {
    // Start performance tracking
    gtaw_perf_start('oauth_token_exchange');
    
    // Get credentials from consolidated settings
    $settings = get_option('gtaw_oauth_settings');
    
    // Fallback to legacy settings for backward compatibility
    // @deprecated 2.0 - Use consolidated settings
    $client_id = isset($settings['client_id']) ? $settings['client_id'] : get_option('gtaw_client_id');
    $client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : get_option('gtaw_client_secret');
    $callback_url = isset($settings['callback_url']) ? $settings['callback_url'] : get_option('gtaw_callback_url', site_url('?gta_oauth=callback'));
    
    if (empty($client_id) || empty($client_secret)) {
        gtaw_add_log('oauth', 'Error', 'OAuth Client ID and Client Secret are required', 'error');
        return new WP_Error('missing_credentials', 'OAuth Client ID and Client Secret are required');
    }
    
    // Build request body
    $body = [
        'grant_type'    => 'authorization_code',
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri'  => $callback_url,
        'code'          => $code,
    ];
    
    // Use our enhanced API request function with retries and error handling
    $response = gtaw_oauth_api_request(GTAW_OAUTH_TOKEN_ENDPOINT, [
        'method' => 'POST',
        'body' => $body,
        'timeout' => 15, // Increase timeout for token exchange
    ]);
    
    // End performance tracking
    $elapsed = gtaw_perf_end('oauth_token_exchange');
    
    // Log timing for performance monitoring
    if (defined('WP_DEBUG') && WP_DEBUG) {
        gtaw_add_log('oauth', 'Performance', "Token exchange took {$elapsed}s", 'success');
    }
    
    return $response;
}

/**
 * Fetch user data from the GTAW API using an access token
 *
 * @param string $access_token The access token
 * @return array|WP_Error User data or error
 */
function gtaw_oauth_get_user_data($access_token) {
    if (empty($access_token)) {
        return new WP_Error('missing_token', 'Access token is required');
    }
    
    // Create cache key based on token
    $cache_key = 'gtaw_oauth_user_' . md5($access_token);
    
    // Check if we have cached data
    $cached_data = get_transient($cache_key);
    if ($cached_data !== false) {
        return $cached_data;
    }
    
    // Start performance tracking
    gtaw_perf_start('oauth_user_data');
    
    // Make the API request
    $response = gtaw_oauth_api_request(GTAW_OAUTH_USER_ENDPOINT, [
        'method' => 'GET',
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
        ]
    ]);
    
    // If successful, cache the result
    if (!is_wp_error($response)) {
        set_transient($cache_key, $response, GTAW_OAUTH_USER_CACHE_DURATION);
    }
    
    // End performance tracking
    $elapsed = gtaw_perf_end('oauth_user_data');
    
    // Log timing for performance monitoring
    if (defined('WP_DEBUG') && WP_DEBUG) {
        gtaw_add_log('oauth', 'Performance', "User data fetch took {$elapsed}s", 'success');
    }
    
    return $response;
}

/**
 * Enhanced API request function for OAuth module
 * 
 * @param string $url API endpoint URL
 * @param array $args Request arguments (method, body, headers, etc.)
 * @param int $retry_count Number of retry attempts for failed requests
 * @return array|WP_Error Response data or error
 */
function gtaw_oauth_api_request($url, $args = [], $retry_count = 1) {
    // Set default method
    $args['method'] = isset($args['method']) ? $args['method'] : 'GET';
    
    // Make the API request
    $response = wp_remote_request($url, $args);
    
    // Handle network errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        gtaw_add_log('oauth', 'Error', "API request failed: {$error_message}", 'error');
        
        // Retry logic for transient network issues
        if ($retry_count > 0) {
            // Wait briefly before retrying
            usleep(500000); // 500ms
            return gtaw_oauth_api_request($url, $args, $retry_count - 1);
        }
        
        return $response;
    }
    
    // Get response code and body
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    // Check for API errors (non-2xx status)
    if ($response_code < 200 || $response_code >= 300) {
        $error_message = wp_remote_retrieve_response_message($response);
        $error_body = json_decode($response_body, true);
        
        // Get specific error message from response if available
        if (isset($error_body['message'])) {
            $error_message = $error_body['message'];
        }
        
        gtaw_add_log('oauth', 'Error', "API Error ({$response_code}): {$error_message}", 'error');
        
        return new WP_Error(
            'gtaw_api_error',
            sprintf('GTA:W API Error (%d): %s', $response_code, $error_message),
            [
                'status' => $response_code,
                'response' => $error_body
            ]
        );
    }
    
    // Parse JSON response
    $response_data = json_decode($response_body, true);
    
    // Handle JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        gtaw_add_log('oauth', 'Error', "Failed to parse API response: " . json_last_error_msg(), 'error');
        return new WP_Error('json_parse_error', 'Failed to parse API response');
    }
    
    return $response_data;
}

/* ========= HELPER FUNCTIONS ========= */

/**
 * Helper function to generate the OAuth authorization URL
 *
 * @return string The complete OAuth URL
 */
function gtaw_get_oauth_url() {
    // Get credentials from consolidated settings
    $settings = get_option('gtaw_oauth_settings');
    
    // Fallback to legacy settings for backward compatibility
    // @deprecated 2.0 - Use consolidated settings
    $client_id = isset($settings['client_id']) ? $settings['client_id'] : get_option('gtaw_client_id', '');
    $callback_url = isset($settings['callback_url']) ? $settings['callback_url'] : get_option('gtaw_callback_url', site_url('?gta_oauth=callback'));
    
    if (empty($client_id)) {
        return '';
    }
    
    return add_query_arg([
        'client_id'     => $client_id,
        'redirect_uri'  => urlencode($callback_url),
        'response_type' => 'code',
        'scope'         => ''
    ], GTAW_OAUTH_AUTH_ENDPOINT);
}

/**
 * Checks if a username is valid according to GTA:W standards
 * 
 * @param string $firstname The first name to check
 * @param string $lastname The last name to check
 * @return bool Whether the name is valid
 */
function gtaw_is_valid_character_name($firstname, $lastname) {
    // Check if the names are empty
    if (empty($firstname) || empty($lastname)) {
        return false;
    }
    
    // Check if the names are between 2 and 16 characters
    if (strlen($firstname) < 2 || strlen($firstname) > 16 || 
        strlen($lastname) < 2 || strlen($lastname) > 16) {
        return false;
    }
    
    // Check if the names only contain letters
    if (!preg_match('/^[A-Za-z]+$/', $firstname) || !preg_match('/^[A-Za-z]+$/', $lastname)) {
        return false;
    }
    
    // Check if the first letter is uppercase
    if ($firstname[0] !== strtoupper($firstname[0]) || $lastname[0] !== strtoupper($lastname[0])) {
        return false;
    }
    
    return true;
}

/**
 * Generates a secure random password with improved entropy
 * 
 * @param int $length The desired password length
 * @return string The generated password
 */
function gtaw_generate_secure_password($length = 16) {
    // Improved character set with clear separation of similar looking characters
    $chars = [
        'abcdefghjkmnpqrstuvwxyz', // lowercase (without ambiguous i, l, o)
        'ABCDEFGHJKLMNPQRSTUVWXYZ', // uppercase (without ambiguous I, O)
        '23456789',                 // numbers (without ambiguous 0, 1)
        '!@#$%^&*()-_=+[]{};:,.?'   // special chars
    ];
    
    // Ensure we use at least one character from each set for improved security
    $password = '';
    foreach ($chars as $char_set) {
        $password .= $char_set[random_int(0, strlen($char_set) - 1)];
    }
    
    // Fill the rest of the password
    $all_chars = implode('', $chars);
    for ($i = 4; $i < $length; $i++) {
        $password .= $all_chars[random_int(0, strlen($all_chars) - 1)];
    }
    
    // Shuffle the password to avoid predictable pattern from first 4 chars
    return str_shuffle($password);
}

/**
 * Validate an OAuth authorization code
 * 
 * @param string $code Authorization code to validate
 * @return bool Whether the code appears to be valid
 */
function gtaw_is_valid_auth_code($code) {
    // Basic validation - just ensure it's not empty and is a string
    if (empty($code) || !is_string($code)) {
        return false;
    }
    
    // Allow any non-empty string as a valid authorization code
    // OAuth providers may use various formats
    return true;
}

/**
 * Check if current user is logged in via OAuth
 * 
 * @param int|null $user_id Optional user ID to check, defaults to current user
 * @return bool Whether user is OAuth authenticated
 */
function gtaw_is_oauth_user($user_id = null) {
    // Use current user if none specified
    if ($user_id === null) {
        if (!is_user_logged_in()) {
            return false;
        }
        $user_id = get_current_user_id();
    }
    
    // Check for GTAW user ID meta
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    return !empty($gtaw_user_id);
}

/* ========= ACTION HOOKS ========= */

/**
 * Action hook that fires when a GTA:W user starts the OAuth process
 *
 * @param array $user_data The user data from the GTA:W API
 */
function gtaw_oauth_trigger_process_started($user_data) {
    do_action('gtaw_oauth_process_started', $user_data);
}

/**
 * Action hook that fires when a GTA:W character is registered as a WordPress user
 *
 * @param int $user_id WordPress user ID
 * @param array $character_data Character data from GTA:W
 */
function gtaw_oauth_trigger_character_registered($user_id, $character_data) {
    do_action('gtaw_oauth_character_registered', $user_id, $character_data);
}

/**
 * Action hook that fires when a user switches characters
 * 
 * @param int $user_id WordPress user ID
 * @param array $new_character New character data
 * @param array|null $old_character Previous character data or null
 */
function gtaw_oauth_trigger_character_switched($user_id, $new_character, $old_character = null) {
    do_action('gtaw_oauth_character_switched', $user_id, $new_character, $old_character);
}

/* ========= ERROR HANDLING ========= */

/**
 * Enhanced error logging for OAuth errors
 * 
 * @param string $error_code Error code
 * @param string $error_message Human readable error message
 * @param array $context Additional context data
 */
function gtaw_oauth_log_error($error_code, $error_message, $context = []) {
    // Add to plugin logs
    gtaw_add_log('oauth', 'Error', "{$error_code}: {$error_message}", 'error');
    
    // Detailed debugging when WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("GTAW OAuth Error - {$error_code}: {$error_message}");
        
        if (!empty($context)) {
            error_log("Error Context: " . wp_json_encode($context));
        }
    }
}

/**
 * Start a secure session for authentication flow
 */
function gtaw_start_secure_session() {
    if (!headers_sent() && session_status() == PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => is_ssl()
        ]);
    }
}

/**
 * Store temporary authentication data in session
 * 
 * @param array $data The data to store
 */
function gtaw_store_auth_data($data) {
    gtaw_start_secure_session();
    $_SESSION['gtaw_auth_data'] = $data;
}

/**
 * Get authentication data from session
 * 
 * @return array|null The stored data or null
 */
function gtaw_get_auth_data() {
    gtaw_start_secure_session();
    return isset($_SESSION['gtaw_auth_data']) ? $_SESSION['gtaw_auth_data'] : null;
}

/**
 * Clear authentication data from session
 */
function gtaw_clear_auth_data() {
    gtaw_start_secure_session();
    if (isset($_SESSION['gtaw_auth_data'])) {
        unset($_SESSION['gtaw_auth_data']);
    }
}