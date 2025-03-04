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
    
    // Check if we need to flush rewrite rules
    if (get_option('gtaw_fleeca_flush_rules', 'yes') === 'yes') {
        flush_rewrite_rules();
        update_option('gtaw_fleeca_flush_rules', 'no');
    }
}
add_action('init', 'gtaw_fleeca_add_callback_endpoint', 10);

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
    // More robust checking for the callback
    if (get_query_var('fleeca_callback') || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/gateway') === 0)) {
        // Get token from URL parameter
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        // Debug logging to help track the issue
        gtaw_add_log('fleeca', 'Debug', "Callback received. URI: {$_SERVER['REQUEST_URI']}, Token: {$token}", 'success');
        
        if (empty($token)) {
            gtaw_add_log('fleeca', 'Error', 'Missing token in callback', 'error');
            wp_die('Missing token. Please contact the store administrator.');
        }
        
        // Process the payment
        gtaw_fleeca_process_callback($token);
        
        // If we get here without a redirect, let's provide a fallback
        echo '<p>Payment processed. Redirecting you back to the store...</p>';
        echo '<script>setTimeout(function() { window.location = "' . esc_url(wc_get_checkout_url()) . '"; }, 3000);</script>';
        exit;
    }
}
add_action('template_redirect', 'gtaw_fleeca_callback_handler', 5); // Higher priority

/**
 * Process the Fleeca callback with token validation
 *
 * @param string $token The token from Fleeca
 */
function gtaw_fleeca_process_callback($token) {
    // Add detailed logging before token validation
    gtaw_add_log('fleeca', 'Debug', "Attempting to validate token: {$token}", 'success');
    
    // Validate the token with Fleeca API - using modified validation function
    $token_data = gtaw_fleeca_validate_token($token);
    
    if (is_wp_error($token_data)) {
        // Add detailed error logging
        gtaw_add_log('fleeca', 'Debug', "Token validation error details: " . print_r($token_data->get_error_data(), true), 'error');
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

// Set a flag to flush rules when settings are saved
function gtaw_fleeca_settings_saved() {
    // When Fleeca settings are saved, mark that we need to flush rules
    update_option('gtaw_fleeca_flush_rules', 'yes');
}
add_action('update_option_gtaw_fleeca_enabled', 'gtaw_fleeca_settings_saved');
add_action('update_option_gtaw_fleeca_callback_url', 'gtaw_fleeca_settings_saved');

// Flush on plugin activation
function gtaw_fleeca_activation() {
    // This ensures rules are flushed when the plugin is activated
    update_option('gtaw_fleeca_flush_rules', 'yes');
}
register_activation_hook(plugin_basename(GTAW_FLEECA_PATH . '../gtaw-fleeca.php'), 'gtaw_fleeca_activation');

// Register a manual flush action for troubleshooting
function gtaw_register_fleeca_manual_flush() {
    // Only accessible to admins
    if (!current_user_can('manage_options')) return;
    
    if (isset($_GET['fleeca_flush_rules']) && $_GET['fleeca_flush_rules'] === 'yes') {
        flush_rewrite_rules();
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Fleeca rewrite rules have been flushed.</p></div>';
        });
    }
}
add_action('admin_init', 'gtaw_register_fleeca_manual_flush');

// "Flush Rules" button in Settings page
function gtaw_add_fleeca_flush_button($content) {
    if (!is_admin() || !current_user_can('manage_options')) return $content;
    
    global $pagenow;
    if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'gtaw-fleeca') {
        $flush_url = add_query_arg(['fleeca_flush_rules' => 'yes'], admin_url('admin.php?page=gtaw-fleeca'));
        echo '<a href="' . esc_url($flush_url) . '" class="button button-secondary" style="margin-left: 10px;">Flush Rewrite Rules</a>';
    }
    
    return $content;
}
add_filter('gtaw_fleeca_after_settings', 'gtaw_add_fleeca_flush_button');