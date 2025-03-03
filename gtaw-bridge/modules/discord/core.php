<?php
defined('ABSPATH') or exit;

/* ========= DISCORD CORE FUNCTIONALITY ========= */
/*
 * This file contains core Discord functionality that is shared across submodules:
 * - API communication utilities
 * - Common helper functions
 * - Shared hooks and filters
 */

/**
 * Make a request to Discord API
 *
 * @param string $endpoint The API endpoint (without leading slash)
 * @param array $args Request arguments (@see wp_remote_request)
 * @param string $method HTTP method (GET, POST, etc.)
 * @return array|WP_Error Response or error
 */
function gtaw_discord_api_request($endpoint, $args = [], $method = 'GET') {
    $bot_token = get_option('gtaw_discord_bot_token', '');
    
    if (empty($bot_token)) {
        return new WP_Error('missing_token', 'Discord Bot Token is required');
    }
    
    // Set default headers
    if (!isset($args['headers'])) {
        $args['headers'] = [];
    }
    
    // Add authorization header
    $args['headers']['Authorization'] = 'Bot ' . $bot_token;
    
    // Full API URL
    $api_url = 'https://discord.com/api/v10/' . ltrim($endpoint, '/');
    
    // Make the request
    $response = 'GET' === $method ? wp_remote_get($api_url, $args) : wp_remote_post($api_url, $args);
    
    // Handle errors
    if (is_wp_error($response)) {
        return $response;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    // Check for API errors
    if ($response_code < 200 || $response_code >= 300) {
        $error_message = wp_remote_retrieve_response_message($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['message'])) {
            $error_message = $body['message'];
        }
        
        return new WP_Error(
            'discord_api_error',
            sprintf('Discord API Error (%d): %s', $response_code, $error_message),
            [
                'status' => $response_code,
                'response' => $body
            ]
        );
    }
    
    return $response;
}

/**
 * Get Discord server roles
 *
 * @param bool $force_refresh Force refresh from API instead of using cache
 * @return array|WP_Error List of roles or error
 */
function gtaw_get_discord_roles($force_refresh = false) {
    if (!$force_refresh) {
        $cached = get_transient('gtaw_discord_roles');
        if ($cached !== false) {
            return $cached;
        }
    }
    
    return gtaw_fetch_discord_roles();
}

/**
 * Fetch Discord roles from API
 *
 * @return array|WP_Error List of roles or error
 */
function gtaw_fetch_discord_roles() {
    $guild_id = get_option('gtaw_discord_guild_id', '');
    
    if (empty($guild_id)) {
        return new WP_Error('missing_guild_id', 'Discord Guild ID is required');
    }
    
    $response = gtaw_discord_api_request("guilds/{$guild_id}/roles");
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $roles = json_decode(wp_remote_retrieve_body($response), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_parse_error', 'Failed to parse Discord roles response');
    }
    
    // Sort roles by position (higher position = higher in hierarchy)
    usort($roles, function($a, $b) {
        return $b['position'] <=> $a['position'];
    });
    
    // Cache roles for 15 minutes
    set_transient('gtaw_discord_roles', $roles, 15 * MINUTE_IN_SECONDS);
    
    return $roles;
}

/**
 * Get Discord member data for a user
 *
 * @param string $discord_id The Discord user ID
 * @param bool $force_refresh Force refresh from API instead of using cache
 * @return array|WP_Error Member data or error
 */
function gtaw_get_discord_member($discord_id, $force_refresh = false) {
    if (empty($discord_id)) {
        return new WP_Error('missing_discord_id', 'Discord user ID is required');
    }
    
    // Check cache first
    if (!$force_refresh) {
        $cache_key = 'discord_member_' . $discord_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
    }
    
    $guild_id = get_option('gtaw_discord_guild_id', '');
    
    if (empty($guild_id)) {
        return new WP_Error('missing_guild_id', 'Discord Guild ID is required');
    }
    
    $response = gtaw_discord_api_request("guilds/{$guild_id}/members/{$discord_id}");
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $member_data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_parse_error', 'Failed to parse Discord member response');
    }
    
    // Cache the member data for 5 minutes
    $cache_key = 'discord_member_' . $discord_id;
    set_transient($cache_key, $member_data, 5 * MINUTE_IN_SECONDS);
    
    return $member_data;
}

/**
 * Get Discord roles for a specific user
 *
 * @param string $discord_id The Discord user ID
 * @param bool $force_refresh Force refresh from API
 * @return array|WP_Error Array of role IDs or error
 */
function gtaw_get_user_discord_roles($discord_id, $force_refresh = false) {
    $member = gtaw_get_discord_member($discord_id, $force_refresh);
    
    if (is_wp_error($member)) {
        return $member;
    }
    
    if (!isset($member['roles']) || !is_array($member['roles'])) {
        return new WP_Error('invalid_member_data', 'Discord member data does not contain roles');
    }
    
    return $member['roles'];
}

/**
 * Check if a user is a member of the Discord server
 *
 * @param string $discord_id The Discord user ID
 * @param bool $force_refresh Force a check instead of using cache
 * @return bool True if user is in the server
 */
function gtaw_is_user_in_discord_server($discord_id, $force_refresh = false) {
    if (empty($discord_id)) {
        return false;
    }
    
    // Check cache first to reduce API calls, unless forced check
    if (!$force_refresh) {
        $cache_key = 'discord_member_' . $discord_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached === 'yes';
        }
    }
    
    $member = gtaw_get_discord_member($discord_id, true);
    $is_member = !is_wp_error($member);
    
    // Cache result 
    $cache_duration = is_checkout() ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS;
    $cache_key = 'discord_member_' . $discord_id;
    set_transient($cache_key, $is_member ? 'yes' : 'no', $cache_duration);
    
    return $is_member;
}

/**
 * Send a Discord message to a channel
 *
 * @param string $channel_id The channel ID to send to
 * @param string $content The message content
 * @param array $embeds Optional embeds to include
 * @return bool|WP_Error True on success or error
 */
function gtaw_discord_send_message($channel_id, $content, $embeds = []) {
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
        gtaw_add_log('discord', 'Error', "Failed to send Discord message: " . $response->get_error_message(), 'error');
        return $response;
    }
    
    return true;
}

/**
 * Action hook that fires when a Discord account is linked to WordPress
 *
 * @param int $user_id WordPress user ID
 * @param string $discord_id Discord user ID
 */
function gtaw_discord_trigger_account_linked($user_id, $discord_id) {
    do_action('gtaw_discord_account_linked', $user_id, $discord_id);
}