<?php
defined('ABSPATH') or exit;

/* ========= FLEECA CORE FUNCTIONALITY ========= */
/*
 * This file contains core Fleeca functionality that is shared across submodules:
 * - API communication utilities with improved performance
 * - Token validation with enhanced security
 * - Payment URL generation with better reliability
 * - Common helper functions with optimization
 * 
 * @version 2.0 Enhanced with performance monitoring, caching, and security improvements
 */

/* ========= API COMMUNICATION CONSTANTS ========= */

// API endpoints
define('GTAW_FLEECA_API_BASE', 'https://banking.gta.world/');
define('GTAW_FLEECA_GATEWAY_ENDPOINT', GTAW_FLEECA_API_BASE . 'gateway/');
define('GTAW_FLEECA_TOKEN_ENDPOINT', GTAW_FLEECA_API_BASE . 'gateway_token/');

// Cache durations
define('GTAW_FLEECA_TOKEN_CACHE_DURATION', 300); // 5 minutes for token data
define('GTAW_FLEECA_PAYMENT_CACHE_DURATION', 3600); // 1 hour for payment data

/**
 * Generate a payment URL for Fleeca Bank with optimization and error handling
 *
 * @param float $amount The payment amount
 * @param string $api_key Optional API key (defaults to stored setting)
 * @return string|false The complete payment URL or false on error
 */
function gtaw_fleeca_get_payment_url($amount, $api_key = '') {
    // Start performance tracking
    gtaw_perf_start('fleeca_generate_url');
    
    // Use provided API key or get from settings
    if (empty($api_key)) {
        $api_key = gtaw_fleeca_get_setting('api_key', '');
    }
    
    if (empty($api_key)) {
        gtaw_add_log('fleeca', 'Error', 'Missing API key when generating payment URL', 'error');
        gtaw_perf_end('fleeca_generate_url');
        return false;
    }
    
    // Type 0 is the standard transaction type per the API documentation
    $transaction_type = 0;
    
    // Ensure amount is a positive integer
    $amount = max(1, intval($amount));
    
    // Format the URL according to Fleeca API structure
    $payment_url = GTAW_FLEECA_GATEWAY_ENDPOINT . "{$api_key}/{$transaction_type}/{$amount}/";
    
    // Cache the payment URL for this amount (keyed by amount and API key)
    $cache_key = 'gtaw_fleeca_payment_url_' . md5($api_key . '_' . $amount);
    set_transient($cache_key, $payment_url, GTAW_FLEECA_PAYMENT_CACHE_DURATION);
    
    // Log URL generation
    $debug_mode = gtaw_fleeca_get_setting('debug_mode', false);
    if ($debug_mode) {
        gtaw_add_log('fleeca', 'URL', "Generated payment URL for amount {$amount}", 'success');
    }
    
    // End performance tracking
    gtaw_perf_end('fleeca_generate_url');
    
    return $payment_url;
}

/**
 * Validate a token with the Fleeca Bank API with enhanced error handling
 * and caching for improved performance
 * 
 * @param string $token The token to validate
 * @param bool $strict Whether to use strict mode validation
 * @param bool $bypass_cache Whether to bypass cache and force validation
 * @return array|WP_Error The validation response or error
 */
function gtaw_fleeca_validate_token($token, $strict = false, $bypass_cache = false) {
    // Start performance tracking
    gtaw_perf_start('fleeca_token_validation');
    
    if (empty($token)) {
        gtaw_perf_end('fleeca_token_validation');
        return new WP_Error('missing_token', 'Token is required for validation');
    }
    
    // Sanitize and validate token format
    $token = sanitize_text_field($token);
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $token)) {
        gtaw_add_log('fleeca', 'Error', "Invalid token format", 'error');
        gtaw_perf_end('fleeca_token_validation');
        return new WP_Error('invalid_token_format', 'Token contains invalid characters');
    }
    
    // Check cache first (unless bypassing)
    if (!$bypass_cache) {
        $cache_key = 'gtaw_fleeca_token_' . md5($token . ($strict ? '_strict' : '_nonstrict'));
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            gtaw_add_log('fleeca', 'Cache', "Using cached token validation data", 'success');
            gtaw_perf_end('fleeca_token_validation');
            return $cached_data;
        }
    }
    
    // Debug logging
    $debug_mode = gtaw_fleeca_get_setting('debug_mode', false);
    if ($debug_mode) {
        gtaw_add_log('fleeca', 'Debug', "Validating token: {$token}" . ($strict ? ' (strict mode)' : ''), 'success');
    }
    
    // Determine validation URL based on mode
    $validation_url = GTAW_FLEECA_TOKEN_ENDPOINT . $token;
    if ($strict) {
        $validation_url .= '/strict';
    }
    
    // Make the request using enhanced API utility
    $response = wp_remote_get($validation_url, [
        'timeout' => 15, // Increased timeout for reliability
        'headers' => [
            'User-Agent' => 'GTAW-Bridge/' . (defined('GTAW_BRIDGE_VERSION') ? GTAW_BRIDGE_VERSION : '1.0')
        ]
    ]);
    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        gtaw_add_log('fleeca', 'Error', "Token validation request failed: {$error_message}", 'error');
        gtaw_perf_end('fleeca_token_validation');
        return $response;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    // Debug logging
    if ($debug_mode) {
        gtaw_add_log('fleeca', 'Debug', "Response code: {$response_code}, Response body: " . substr($response_body, 0, 200) . (strlen($response_body) > 200 ? '...' : ''), 'success');
    }
    
    // Handle different response codes
    if ($response_code >= 200 && $response_code < 300) {
        // Parse the response
        $token_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            gtaw_add_log('fleeca', 'Error', "Failed to parse token validation response: " . json_last_error_msg(), 'error');
            gtaw_perf_end('fleeca_token_validation');
            return new WP_Error('json_parse_error', 'Failed to parse API response');
        }
        
        // Validate that our API key matches the one in the response
        $stored_api_key = gtaw_fleeca_get_setting('api_key', '');
        if (!isset($token_data['auth_key']) || $token_data['auth_key'] !== $stored_api_key) {
            gtaw_add_log('fleeca', 'Error', "API key mismatch in token validation. Expected: {$stored_api_key}, Got: " . (isset($token_data['auth_key']) ? $token_data['auth_key'] : 'none'), 'error');
            gtaw_perf_end('fleeca_token_validation');
            return new WP_Error('auth_key_mismatch', 'API key in response does not match stored key');
        }
        
        // Check if the token is expired but still accept it (for sandbox environments)
        if (isset($token_data['token_expired']) && $token_data['token_expired']) {
            gtaw_add_log('fleeca', 'Warning', "Token is marked as expired but validation succeeded", 'success');
        }
        
        // Cache the successful validation
        $cache_key = 'gtaw_fleeca_token_' . md5($token . ($strict ? '_strict' : '_nonstrict'));
        set_transient($cache_key, $token_data, GTAW_FLEECA_TOKEN_CACHE_DURATION);
        
        // Log the validation
        gtaw_add_log('fleeca', 'Token', "Successfully validated token for payment of {$token_data['payment']}", 'success');
        
        // End performance tracking
        gtaw_perf_end('fleeca_token_validation');
        return $token_data;
    } elseif ($response_code === 404) {
        // Token not found or expired
        gtaw_add_log('fleeca', 'Error', "Token not found or expired: {$token}", 'error');
        gtaw_perf_end('fleeca_token_validation');
        return new WP_Error('token_not_found', 'Token not found or has expired');
    } elseif ($response_code === 429) {
        // Rate limited
        gtaw_add_log('fleeca', 'Error', "Rate limited by Fleeca API", 'error');
        gtaw_perf_end('fleeca_token_validation');
        return new WP_Error('rate_limited', 'API rate limit exceeded. Please try again later.');
    } else {
        // Other error
        gtaw_add_log('fleeca', 'Error', "Unexpected response from Fleeca API: {$response_code}", 'error');
        gtaw_perf_end('fleeca_token_validation');
        return new WP_Error('validation_failed', 'Unexpected response from Fleeca API', [
            'code' => $response_code,
            'body' => $response_body
        ]);
    }
}

/**
 * Process a successful payment with comprehensive logging
 * and data storage
 *
 * @param int $order_id WooCommerce order ID
 * @param array $token_data Validated token data
 * @return bool Success status
 */
function gtaw_fleeca_process_payment_success($order_id, $token_data) {
    // Start performance tracking
    gtaw_perf_start('fleeca_process_payment');
    
    $order = wc_get_order($order_id);
    
    if (!$order) {
        gtaw_add_log('fleeca', 'Error', "Failed to process payment: Order {$order_id} not found", 'error');
        gtaw_perf_end('fleeca_process_payment');
        return false;
    }
    
    // Create a transaction ID based on token and timestamp
    $transaction_id = 'fleeca_' . substr($token_data['token'], 0, 10) . '_' . time();
    
    // Store all token data in hidden meta data with sanitization
    $meta_data = [
        '_fleeca_payment_token' => sanitize_text_field($token_data['token']),
        '_fleeca_routing_from' => sanitize_text_field($token_data['routing_from'] ?? ''),
        '_fleeca_routing_to' => sanitize_text_field($token_data['routing_to'] ?? ''),
        '_fleeca_payment_amount' => floatval($token_data['payment'] ?? 0),
        '_fleeca_payment_time' => current_time('mysql'),
        '_fleeca_transaction_id' => $transaction_id,
        '_fleeca_sandbox' => isset($token_data['sandbox']) && $token_data['sandbox'] ? 'yes' : 'no'
    ];
    
    // Store meta data efficiently
    foreach ($meta_data as $key => $value) {
        update_post_meta($order_id, $key, $value);
    }
    
    // Add payment information to the order notes for admin reference
    $order->add_order_note(
        sprintf(
            'Fleeca Bank payment completed (Amount: %s, From: %s, To: %s, Token: %s, Transaction ID: %s)',
            isset($token_data['payment']) ? $token_data['payment'] : 'unknown',
            isset($token_data['routing_from']) ? $token_data['routing_from'] : 'unknown',
            isset($token_data['routing_to']) ? $token_data['routing_to'] : 'unknown',
            sanitize_text_field($token_data['token']),
            $transaction_id
        ),
        false // Set to private note (only visible to admin)
    );
    
    // Mark payment complete with transaction ID
    $order->payment_complete($transaction_id);
    
    // If this is a sandbox payment, add a note
    if (isset($token_data['sandbox']) && $token_data['sandbox']) {
        $order->add_order_note('This was a Fleeca Bank sandbox payment (test mode).', false);
    }
    
    // Save the order
    $order->save();
    
    // Log the payment completion
    gtaw_add_log('fleeca', 'Payment', "Order #{$order_id} payment completed successfully with transaction ID: {$transaction_id}", 'success');
    
    // End performance tracking
    gtaw_perf_end('fleeca_process_payment');
    
    return true;
}

/**
 * Check if an order payment amount matches the validated token amount
 * with enhanced validation and security
 *
 * @param WC_Order $order WooCommerce order object
 * @param array $token_data Validated token data
 * @return bool Whether the amounts match
 */
function gtaw_fleeca_validate_payment_amount($order, $token_data) {
    if (!$order || !is_a($order, 'WC_Order')) {
        gtaw_add_log('fleeca', 'Error', "Invalid order object in payment validation", 'error');
        return false;
    }
    
    if (!isset($token_data['payment'])) {
        gtaw_add_log('fleeca', 'Error', "Token data missing payment amount", 'error');
        return false;
    }
    
    // Get order total and format it the same way as Fleeca (no decimal)
    $order_total = intval($order->get_total());
    $token_amount = intval($token_data['payment']);
    
    // Compare the amounts
    if ($order_total !== $token_amount) {
        gtaw_add_log(
            'fleeca', 
            'Error', 
            "Payment amount mismatch: Order amount {$order_total} doesn't match token amount {$token_amount}", 
            'error'
        );
        return false;
    }
    
    // Log successful validation if in debug mode
    $debug_mode = gtaw_fleeca_get_setting('debug_mode', false);
    if ($debug_mode) {
        gtaw_add_log('fleeca', 'Debug', "Payment amount validated successfully: {$order_total}", 'success');
    }
    
    return true;
}

/**
 * Get the base callback URL for Fleeca with validation
 * 
 * @return string The callback URL without the token parameter
 */
function gtaw_fleeca_get_callback_base_url() {
    // Get the callback URL from settings or use default
    $callback_url = gtaw_fleeca_get_setting('callback_url', site_url('gateway?token='));
    
    // Ensure it ends with ?token= or &token=
    if (strpos($callback_url, '?token=') === false && strpos($callback_url, '&token=') === false) {
        // Check if it already has query parameters
        if (strpos($callback_url, '?') !== false) {
            $callback_url .= '&token=';
        } else {
            $callback_url .= '?token=';
        }
    }
    
    // Validate URL is valid and safe
    $callback_url = esc_url_raw($callback_url);
    
    return $callback_url;
}

/**
 * Debug function to log token info
 * Only runs when debug mode is enabled
 * 
 * @param array $token_data The token data to log
 */
function gtaw_fleeca_debug_token_info($token_data) {
    if (!gtaw_fleeca_get_setting('debug_mode', false)) {
        return;
    }
    
    $debug_info = [
        'Token' => isset($token_data['token']) ? substr($token_data['token'], 0, 10) . '...' : 'N/A',
        'Amount' => isset($token_data['payment']) ? $token_data['payment'] : 'N/A',
        'From Account' => isset($token_data['routing_from']) ? $token_data['routing_from'] : 'N/A',
        'To Account' => isset($token_data['routing_to']) ? $token_data['routing_to'] : 'N/A',
        'Sandbox' => isset($token_data['sandbox']) ? ($token_data['sandbox'] ? 'Yes' : 'No') : 'N/A',
        'Expired' => isset($token_data['token_expired']) ? ($token_data['token_expired'] ? 'Yes' : 'No') : 'N/A'
    ];
    
    foreach ($debug_info as $key => $value) {
        gtaw_add_log('fleeca', 'Debug', "{$key}: {$value}", 'success');
    }
}

/**
 * Clear Fleeca token cache
 * 
 * @param string $token Token to clear from cache
 */
function gtaw_fleeca_clear_token_cache($token = '') {
    if (!empty($token)) {
        // Clear specific token
        $nonstrict_key = 'gtaw_fleeca_token_' . md5($token . '_nonstrict');
        $strict_key = 'gtaw_fleeca_token_' . md5($token . '_strict');
        
        delete_transient($nonstrict_key);
        delete_transient($strict_key);
    } else {
        // Clear all Fleeca transients when no specific token provided
        global $wpdb;
        
        $sql = "DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_gtaw_fleeca_token_%'";
        $wpdb->query($sql);
        
        gtaw_add_log('fleeca', 'Cache', "Cleared all Fleeca token cache", 'success');
    }
}

/**
 * Generate a transaction ID based on order and token data
 * 
 * @param int $order_id WooCommerce order ID
 * @param string $token Token string
 * @return string Unique transaction ID
 */
function gtaw_fleeca_generate_transaction_id($order_id, $token) {
    $token_part = substr(sanitize_text_field($token), 0, 8);
    $random = substr(md5(uniqid()), 0, 6);
    return "fleeca_{$order_id}_{$token_part}_{$random}";
}