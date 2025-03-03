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

/**
 * Make a request to GTAW OAuth API for token exchange
 *
 * @param string $code The authorization code to exchange
 * @return array|WP_Error Response data or error
 */
function gtaw_oauth_exchange_token($code) {
    $client_id = get_option('gtaw_client_id');
    $client_secret = get_option('gtaw_client_secret');
    $callback_url = get_option('gtaw_callback_url', site_url('?gta_oauth=callback'));
    
    if (empty($client_id) || empty($client_secret)) {
        return new WP_Error('missing_credentials', 'OAuth Client ID and Client Secret are required');
    }
    
    $response = wp_remote_post('https://ucp.gta.world/oauth/token', array(
        'body' => array(
            'grant_type'    => 'authorization_code',
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $callback_url,
            'code'          => $code,
        )
    ));
    
    if (is_wp_error($response)) {
        gtaw_add_log('oauth', 'Error', "Token exchange failed: " . $response->get_error_message(), 'error');
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
        
        gtaw_add_log('oauth', 'Error', "API Error ({$response_code}): {$error_message}", 'error');
        
        return new WP_Error(
            'gtaw_api_error',
            sprintf('GTA:W API Error (%d): %s', $response_code, $error_message),
            [
                'status' => $response_code,
                'response' => $body
            ]
        );
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        gtaw_add_log('oauth', 'Error', "Failed to parse token response", 'error');
        return new WP_Error('json_parse_error', 'Failed to parse API response');
    }
    
    return $body;
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
    
    $response = wp_remote_get('https://ucp.gta.world/api/user', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
        )
    ));
    
    if (is_wp_error($response)) {
        gtaw_add_log('oauth', 'Error', "User data fetch failed: " . $response->get_error_message(), 'error');
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
        
        gtaw_add_log('oauth', 'Error', "API Error ({$response_code}): {$error_message}", 'error');
        
        return new WP_Error(
            'gtaw_api_error',
            sprintf('GTA:W API Error (%d): %s', $response_code, $error_message),
            [
                'status' => $response_code,
                'response' => $body
            ]
        );
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        gtaw_add_log('oauth', 'Error', "Failed to parse user data response", 'error');
        return new WP_Error('json_parse_error', 'Failed to parse API response');
    }
    
    return $body;
}

/**
 * Helper function to generate the OAuth authorization URL
 *
 * @return string The complete OAuth URL
 */
function gtaw_get_oauth_url() {
    $client_id = get_option('gtaw_client_id', '');
    if (empty($client_id)) {
        return '';
    }
    
    $callback_url = get_option('gtaw_callback_url', site_url('?gta_oauth=callback'));
    
    return add_query_arg(array(
        'client_id'     => $client_id,
        'redirect_uri'  => urlencode($callback_url),
        'response_type' => 'code',
        'scope'         => ''
    ), 'https://ucp.gta.world/oauth/authorize');
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
 * Generates a secure random password
 * 
 * @param int $length The desired password length
 * @return string The generated password
 */
function gtaw_generate_secure_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $password;
}

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