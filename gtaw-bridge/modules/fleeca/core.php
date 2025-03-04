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
 * @param string $token The token to validate
 * @param bool $strict Whether to use strict mode validation
 * @return array|WP_Error The validation response or error
 */
function gtaw_fleeca_validate_token($token, $strict = true) {
    if (empty($token)) {
        return new WP_Error('missing_token', 'Token is required for validation');
    }
    
    // Determine the validation URL based on strict mode
    $validation_url = $strict 
        ? "https://banking.gta.world/gateway_token/{$token}/strict" 
        : "https://banking.gta.world/gateway_token/{$token}";
    
    // Make the request to validate the token
    $response = wp_remote_get($validation_url);
    
    if (is_wp_error($response)) {
        gtaw_add_log('fleeca', 'Error', "Token validation failed: " . $response->get_error_message(), 'error');
        return $response;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    // Check for API errors
    if ($response_code < 200 || $response_code >= 300) {
        // In strict mode, 404 is expected for expired/sandbox tokens
        if ($strict && $response_code === 404) {
            gtaw_add_log('fleeca', 'Error', "Token validation failed: Token expired or in sandbox mode", 'error');
            return new WP_Error('invalid_token', 'Token expired or in sandbox mode');
        }
        
        $error_message = wp_remote_retrieve_response_message($response);
        gtaw_add_log('fleeca', 'Error', "API Error ({$response_code}): {$error_message}", 'error');
        
        return new WP_Error(
            'fleeca_api_error',
            sprintf('Fleeca API Error (%d): %s', $response_code, $error_message)
        );
    }
    
    // Parse the response
    $body = wp_remote_retrieve_body($response);
    $token_data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        gtaw_add_log('fleeca', 'Error', "Failed to parse token validation response", 'error');
        return new WP_Error('json_parse_error', 'Failed to parse API response');
    }
    
    // Validate that our API key matches the one in the response
    $stored_api_key = get_option('gtaw_fleeca_api_key', '');
    if ($token_data['auth_key'] !== $stored_api_key) {
        gtaw_add_log('fleeca', 'Error', "API key mismatch in token validation", 'error');
        return new WP_Error('auth_key_mismatch', 'API key in response does not match stored key');
    }
    
    gtaw_add_log('fleeca', 'Token', "Successfully validated token for payment of {$token_data['payment']}", 'success');
    
    return $token_data;
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
    
    // Add payment information to the order notes
    $order->add_order_note(
        sprintf(
            'Fleeca Bank payment completed (Amount: %s, From: %s, To: %s, Token: %s)',
            $token_data['payment'],
            $token_data['routing_from'],
            $token_data['routing_to'],
            $token_data['token']
        )
    );
    
    // Set the transaction ID to the token
    $order->set_transaction_id($token_data['token']);
    
    // Payment complete
    $order->payment_complete();
    
    // If this is a sandbox payment, add a note
    if (isset($token_data['sandbox']) && $token_data['sandbox']) {
        $order->add_order_note('This was a Fleeca Bank sandbox payment (test mode).');
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