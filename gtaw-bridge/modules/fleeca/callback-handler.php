<?php
defined('ABSPATH') or exit;

/* ========= FLEECA CALLBACK HANDLER ========= */
/*
 * This file handles the callback from Fleeca Bank:
 * - Registering the gateway endpoint
 * - Processing the token
 * - Finalizing orders
 */

/**
 * Register a custom endpoint for Fleeca callbacks
 */
function gtaw_fleeca_add_callback_endpoint() {
    add_rewrite_rule('^gateway/?$', 'index.php?fleeca_callback=1', 'top');
}
add_action('init', 'gtaw_fleeca_add_callback_endpoint');

/**
 * Add custom query vars for the Fleeca callback
 *
 * @param array $vars Existing query vars
 * @return array Updated query vars
 */
function gtaw_fleeca_query_vars($vars) {
    $vars[] = 'fleeca_callback';
    $vars[] = 'token';
    return $vars;
}
add_filter('query_vars', 'gtaw_fleeca_query_vars');

/**
 * Handle the Fleeca callback request
 */
function gtaw_fleeca_callback_handler() {
    $is_callback = get_query_var('fleeca_callback');
    
    if ($is_callback) {
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        if (empty($token)) {
            gtaw_add_log('fleeca', 'Error', 'Missing token in callback', 'error');
            wp_die('Missing token. Please contact the store administrator.');
        }
        
        // Process the payment
        gtaw_fleeca_process_callback($token);
    }
}
add_action('template_redirect', 'gtaw_fleeca_callback_handler');

/**
 * Process the Fleeca callback with token validation
 *
 * @param string $token The token from Fleeca
 */
function gtaw_fleeca_process_callback($token) {
    // Validate the token with Fleeca API
    $token_data = gtaw_fleeca_validate_token($token, true);
    
    if (is_wp_error($token_data)) {
        gtaw_add_log('fleeca', 'Error', "Token validation failed: " . $token_data->get_error_message(), 'error');
        wc_add_notice('Payment validation failed: ' . $token_data->get_error_message(), 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
    }
    
    // Get the current order ID from session
    $order_id = WC()->session->get('fleeca_current_order_id');
    
    if (empty($order_id)) {
        gtaw_add_log('fleeca', 'Error', "No order ID found in session for token {$token}", 'error');
        wc_add_notice('Failed to locate your order. Please contact customer support.', 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
    }
    
    // Get the order
    $order = wc_get_order($order_id);
    
    if (!$order) {
        gtaw_add_log('fleeca', 'Error', "Invalid order ID: {$order_id}", 'error');
        wc_add_notice('Invalid order. Please contact customer support.', 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
    }
    
    // Validate payment amount
    if (!gtaw_fleeca_validate_payment_amount($order, $token_data)) {
        gtaw_add_log('fleeca', 'Error', "Payment amount mismatch for order {$order_id}", 'error');
        $order->add_order_note('Fleeca Bank payment amount mismatch. Manual verification required.');
        $order->update_status('on-hold', 'Payment amount mismatch. Manual verification required.');
        wc_add_notice('Your payment amount did not match the order total. Your order is on hold pending manual verification.', 'error');
        wp_redirect($order->get_checkout_order_received_url());
        exit;
    }
    
    // Process successful payment
    if (gtaw_fleeca_process_payment_success($order_id, $token_data)) {
        // Clear the session data
        WC()->session->set('fleeca_current_order_id', null);
        
        // Add success message
        wc_add_notice('Payment successful! Thank you for your order.', 'success');
        
        // Redirect to thank you page
        wp_redirect($order->get_checkout_order_received_url());
        exit;
    } else {
        // Something went wrong during processing
        gtaw_add_log('fleeca', 'Error', "Failed to process payment for order {$order_id}", 'error');
        wc_add_notice('There was a problem processing your payment. Please contact customer support.', 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
    }
}

/**
 * Add notice if payment was canceled
 */
function gtaw_fleeca_check_cancel_notice() {
    if (isset($_GET['fleeca_cancel'])) {
        wc_add_notice('Your Fleeca Bank payment was canceled. Please try again or select a different payment method.', 'error');
    }
}