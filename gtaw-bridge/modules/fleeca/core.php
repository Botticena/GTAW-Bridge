<?php
defined('ABSPATH') or exit;

/* ========= FLEECA CORE FUNCTIONALITY ========= */
/*
 * This file contains core Fleeca functionality that is shared across submodules:
 * - API communication utilities
 * - Token validation
 * - Payment URL generation
 * - Common helper functions
 */

/**
 * Generate a payment URL for Fleeca Bank
 *
 * @param float $amount The payment amount
 * @param string $api_key Optional API key (defaults to stored setting)
 * @return string The complete payment URL
 */
function gtaw_fleeca_get_payment_url($amount, $api_key = '') {
    // Use provided API key or get from settings
    if (empty($api_key)) {
        $api_key = get_option('gtaw_fleeca_api_key', '');
    }
    
    if (empty($api_key)) {
        gtaw_add_log('fleeca', 'Error', 'Missing API key when generating payment URL', 'error');
        return '';
    }
    
    // Type 0 is the standard transaction type per the API documentation
    $transaction_type = 0;
    
    // Format the URL according to Fleeca API structure
    $payment_url = "https://banking.gta.world/gateway/{$api_key}/{$transaction_type}/{$amount}/";
    
    gtaw_add_log('fleeca', 'URL', "Generated payment URL for amount {$amount}", 'success');
    
    return $payment_url;
}

/**
 * Validate a token with the Fleeca Bank API
 * 
 * This function first tries non-strict validation, which works for all valid tokens
 * even if they've been used/expired
 *
 * @param string $token The token to validate
 * @param bool $strict Whether to use strict mode validation (not used in this implementation)
 * @return array|WP_Error The validation response or error
 */
function gtaw_fleeca_validate_token($token, $strict = true) {
    if (empty($token)) {
        return new WP_Error('missing_token', 'Token is required for validation');
    }
    
    // First try non-strict validation to see if the token is valid at all
    $validation_url = "https://banking.gta.world/gateway_token/{$token}";
    
    // Make the request to validate the token
    gtaw_add_log('fleeca', 'Debug', "Validating token with URL: {$validation_url}", 'success');
    $response = wp_remote_get($validation_url);
    
    if (is_wp_error($response)) {
        gtaw_add_log('fleeca', 'Error', "Token validation failed: " . $response->get_error_message(), 'error');
        return $response;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    gtaw_add_log('fleeca', 'Debug', "Response code: {$response_code}, Response body: " . substr($response_body, 0, 200) . "...", 'success');
    
    // Check if non-strict validation worked
    if ($response_code >= 200 && $response_code < 300) {
        // Parse the response
        $token_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            gtaw_add_log('fleeca', 'Error', "Failed to parse token validation response: " . json_last_error_msg(), 'error');
            return new WP_Error('json_parse_error', 'Failed to parse API response');
        }
        
        // Validate that our API key matches the one in the response
        $stored_api_key = get_option('gtaw_fleeca_api_key', '');
        if ($token_data['auth_key'] !== $stored_api_key) {
            gtaw_add_log('fleeca', 'Error', "API key mismatch in token validation. Expected: {$stored_api_key}, Got: {$token_data['auth_key']}", 'error');
            return new WP_Error('auth_key_mismatch', 'API key in response does not match stored key');
        }
        
        // Check if the token is expired
        if (isset($token_data['token_expired']) && $token_data['token_expired']) {
            gtaw_add_log('fleeca', 'Warning', "Token is marked as expired but validation succeeded", 'success');
            // We still accept the token if everything else is valid
        }
        
        gtaw_add_log('fleeca', 'Token', "Successfully validated token for payment of {$token_data['payment']}", 'success');
        return $token_data;
    }
    
    // If the above fails, try strict mode as a fallback
    $strict_url = "https://banking.gta.world/gateway_token/{$token}/strict";
    gtaw_add_log('fleeca', 'Debug', "Non-strict validation failed, trying strict validation: {$strict_url}", 'success');
    
    $strict_response = wp_remote_get($strict_url);
    
    if (is_wp_error($strict_response)) {
        gtaw_add_log('fleeca', 'Error', "Strict token validation failed: " . $strict_response->get_error_message(), 'error');
        return $strict_response;
    }
    
    $strict_code = wp_remote_retrieve_response_code($strict_response);
    $strict_body = wp_remote_retrieve_body($strict_response);
    
    gtaw_add_log('fleeca', 'Debug', "Strict response code: {$strict_code}, Response body: " . substr($strict_body, 0, 200) . "...", 'success');
    
    if ($strict_code >= 200 && $strict_code < 300) {
        // Parse the response
        $token_data = json_decode($strict_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            gtaw_add_log('fleeca', 'Error', "Failed to parse strict token validation response", 'error');
            return new WP_Error('json_parse_error', 'Failed to parse API response');
        }
        
        // Validate API key
        $stored_api_key = get_option('gtaw_fleeca_api_key', '');
        if ($token_data['auth_key'] !== $stored_api_key) {
            gtaw_add_log('fleeca', 'Error', "API key mismatch in strict token validation", 'error');
            return new WP_Error('auth_key_mismatch', 'API key in response does not match stored key');
        }
        
        gtaw_add_log('fleeca', 'Token', "Successfully validated token in strict mode for payment of {$token_data['payment']}", 'success');
        return $token_data;
    }
    
    // If we get here, both validations failed
    if ($strict_code === 404) {
        // This is the most common case - expired token or sandbox mode
        gtaw_add_log('fleeca', 'Error', "Both validation methods failed. Token might be expired, used, or in sandbox mode.", 'error');
        return new WP_Error('invalid_token', 'Token expired or already used');
    }
    
    gtaw_add_log('fleeca', 'Error', "Token validation failed completely. Non-strict code: {$response_code}, Strict code: {$strict_code}", 'error');
    return new WP_Error('validation_failed', 'Token validation failed');
}

/**
 * Process a successful payment
 *
 * @param int $order_id WooCommerce order ID
 * @param array $token_data Validated token data
 * @return bool Success status
 */
function gtaw_fleeca_process_payment_success($order_id, $token_data) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        gtaw_add_log('fleeca', 'Error', "Failed to process payment: Order {$order_id} not found", 'error');
        return false;
    }
    
    // Store the token in hidden meta data instead of as the transaction ID
    update_post_meta($order_id, '_fleeca_payment_token', $token_data['token']);
    update_post_meta($order_id, '_fleeca_routing_from', $token_data['routing_from']);
    update_post_meta($order_id, '_fleeca_routing_to', $token_data['routing_to']);
    update_post_meta($order_id, '_fleeca_payment_amount', $token_data['payment']);
    update_post_meta($order_id, '_fleeca_payment_time', current_time('mysql'));
    
    // Add payment information to the order notes for admin reference
    $order->add_order_note(
        sprintf(
            'Fleeca Bank payment completed (Amount: %s, From: %s, To: %s, Token: %s)',
            $token_data['payment'],
            $token_data['routing_from'],
            $token_data['routing_to'],
            $token_data['token']
        ),
        false // Set to private note (only visible to admin)
    );
    
    // Payment complete without setting transaction ID
    $order->payment_complete();
    
    // If this is a sandbox payment, add a note
    if (isset($token_data['sandbox']) && $token_data['sandbox']) {
        $order->add_order_note('This was a Fleeca Bank sandbox payment (test mode).', false);
    }
    
    // Save the order
    $order->save();
    
    gtaw_add_log('fleeca', 'Payment', "Order #{$order_id} payment completed successfully", 'success');
    
    return true;
}

/**
 * Check if an order payment amount matches the validated token amount
 *
 * @param WC_Order $order WooCommerce order object
 * @param array $token_data Validated token data
 * @return bool Whether the amounts match
 */
function gtaw_fleeca_validate_payment_amount($order, $token_data) {
    if (!$order) {
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
    
    return true;
}

/**
 * Get the base callback URL for Fleeca
 * 
 * @return string The callback URL without the token parameter
 */
function gtaw_fleeca_get_callback_base_url() {
    // Get the callback URL from settings or use default
    $callback_url = get_option('gtaw_fleeca_callback_url', site_url('gateway?token='));
    
    // Ensure it ends with ?token= or &token=
    if (strpos($callback_url, '?token=') === false && strpos($callback_url, '&token=') === false) {
        // Check if it already has query parameters
        if (strpos($callback_url, '?') !== false) {
            $callback_url .= '&token=';
        } else {
            $callback_url .= '?token=';
        }
    }
    
    return $callback_url;
}