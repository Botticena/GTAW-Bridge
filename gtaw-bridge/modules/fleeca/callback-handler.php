<?php
defined('ABSPATH') or exit;

/* ========= FLEECA CALLBACK HANDLER ========= */
/*
 * This file handles the callback from Fleeca Bank:
 * - Registering the gateway endpoint
 * - Processing the token
 * - Finalizing orders
 * - Error handling and logging
 * 
 * @version 2.0 Enhanced with performance monitoring, security improvements,
 * and better error handling
 */

/**
 * Register a custom endpoint for Fleeca callbacks
 */
function gtaw_fleeca_add_callback_endpoint() {
    // Prevent multiple registrations in the same request
    static $registered = false;
    if ($registered) {
        return;
    }
    
    // Start performance tracking
    gtaw_perf_start('fleeca_register_endpoint');
    
    // Register the rewrite rule for the callback URL
    add_rewrite_rule('^gateway/?$', 'index.php?fleeca_callback=1', 'top');
    
    // Check if we need to flush rewrite rules
    $flush_rules = get_option('gtaw_fleeca_flush_rules', 'yes') === 'yes';
    
    if ($flush_rules) {
        flush_rewrite_rules();
        update_option('gtaw_fleeca_flush_rules', 'no');
        
        // Log the flush operation
        $debug_mode = gtaw_fleeca_get_setting('debug_mode', false);
        if ($debug_mode) {
            gtaw_add_log('fleeca', 'Rewrite', 'Rewrite rules flushed', 'success');
        }
    }
    
    // Mark as registered to prevent duplicate operations
    $registered = true;
    
    gtaw_perf_end('fleeca_register_endpoint');
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
 * Handle the Fleeca callback request with comprehensive error handling
 * and security measures
 */
function gtaw_fleeca_callback_handler() {
    // Check if this is a Fleeca callback request using multiple indicators
    $is_callback = get_query_var('fleeca_callback') || 
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/gateway') === 0) ||
        (isset($_GET['fleeca_callback']) && $_GET['fleeca_callback'] == 1);
    
    if (!$is_callback) {
        return;
    }
    
    // Start performance tracking
    gtaw_perf_start('fleeca_callback_process');
    
    // Get token from URL parameter with validation
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    
    // Debug logging
    $debug_mode = gtaw_fleeca_get_setting('debug_mode', false);
    if ($debug_mode) {
        gtaw_add_log('fleeca', 'Debug', "Callback received. URI: {$_SERVER['REQUEST_URI']}, Token: {$token}", 'success');
    }
    
    if (empty($token)) {
        gtaw_add_log('fleeca', 'Error', 'Missing token in callback', 'error');
        gtaw_fleeca_handle_error('Missing token. Please contact the store administrator.');
        exit;
    }
    
    // Optional - Rate limiting to prevent abuse
    if (!gtaw_fleeca_check_rate_limit('callback')) {
        gtaw_add_log('fleeca', 'Security', 'Rate limit exceeded for callbacks', 'error');
        gtaw_fleeca_handle_error('Too many payment attempts. Please try again later.');
        exit;
    }
    
    // Process the payment with detailed error handling
    try {
        $result = gtaw_fleeca_process_callback($token);
        
        if (!$result) {
            throw new Exception('Payment processing failed');
        }
        
        // End performance tracking
        $elapsed = gtaw_perf_end('fleeca_callback_process');
        
        if ($debug_mode) {
            gtaw_add_log('fleeca', 'Performance', "Callback processing completed in {$elapsed}s", 'success');
        }
        
        // Redirecting is handled in the process_callback function
    } catch (Exception $e) {
        // Log the error
        gtaw_add_log('fleeca', 'Error', 'Callback exception: ' . $e->getMessage(), 'error');
        
        // End performance tracking
        gtaw_perf_end('fleeca_callback_process');
        
        // Display error with fallback redirection
        gtaw_fleeca_handle_error('Error processing payment: ' . $e->getMessage());
        exit;
    }
}
add_action('template_redirect', 'gtaw_fleeca_callback_handler', 5); // Higher priority

/**
 * Process the Fleeca callback with token validation and enhanced security
 *
 * @param string $token The token from Fleeca
 * @return bool Success status
 * @throws Exception On processing errors
 */
function gtaw_fleeca_process_callback($token) {
    // Start performance tracking for token validation
    gtaw_perf_start('fleeca_token_validation');
    
    // Add detailed logging before token validation
    $debug_mode = gtaw_fleeca_get_setting('debug_mode', false);
    if ($debug_mode) {
        gtaw_add_log('fleeca', 'Debug', "Attempting to validate token: {$token}", 'success');
    }
    
    // Validate the token with Fleeca API
    $token_data = gtaw_fleeca_validate_token($token, false, true); // Force fresh validation, bypass cache
    
    if (is_wp_error($token_data)) {
        // Detailed error logging
        $error_code = $token_data->get_error_code();
        $error_message = $token_data->get_error_message();
        $error_data = $token_data->get_error_data();
        
        gtaw_add_log('fleeca', 'Error', "Token validation failed with code '{$error_code}': {$error_message}", 'error');
        
        if ($debug_mode && !empty($error_data)) {
            gtaw_add_log('fleeca', 'Debug', "Error data: " . print_r($error_data, true), 'error');
        }
        
        // End performance tracking
        gtaw_perf_end('fleeca_token_validation');
        
        // Handle different error types with appropriate messaging
        if ($error_code === 'token_not_found') {
            wc_add_notice('Payment validation failed: The payment token has expired or is invalid.', 'error');
        } else if ($error_code === 'rate_limited') {
            wc_add_notice('Payment service temporarily unavailable. Please try again in a few minutes.', 'error');
        } else {
            wc_add_notice('Payment validation failed: ' . $error_message, 'error');
        }
        
        wp_redirect(wc_get_checkout_url());
        exit;
    }
    
    // End performance tracking for token validation
    $token_validation_time = gtaw_perf_end('fleeca_token_validation');
    
    if ($debug_mode) {
        gtaw_add_log('fleeca', 'Performance', "Token validation took {$token_validation_time}s", 'success');
        gtaw_fleeca_debug_token_info($token_data);
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
    
    // Security check - verify order is still pending
    if ($order->get_status() !== 'pending') {
        gtaw_add_log('fleeca', 'Security', "Attempted payment for non-pending order {$order_id} (status: {$order->get_status()})", 'error');
        wc_add_notice('This order has already been processed or canceled.', 'error');
        wp_redirect($order->get_checkout_order_received_url());
        exit;
    }
    
    // Security check - verify order belongs to current customer if logged in
    if (is_user_logged_in()) {
        $current_user_id = get_current_user_id();
        $order_user_id = $order->get_user_id();
        
        if ($order_user_id && $current_user_id != $order_user_id) {
            gtaw_add_log('fleeca', 'Security', "User {$current_user_id} attempted to process order {$order_id} belonging to user {$order_user_id}", 'error');
            wc_add_notice('You do not have permission to process this order.', 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }
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
    
    // Start processing performance tracking
    gtaw_perf_start('fleeca_payment_process');
    
    // Process successful payment
    $success = gtaw_fleeca_process_payment_success($order_id, $token_data);
    
    // End processing performance tracking
    $payment_process_time = gtaw_perf_end('fleeca_payment_process');
    
    if ($debug_mode) {
        gtaw_add_log('fleeca', 'Performance', "Payment processing took {$payment_process_time}s", 'success');
    }
    
    if ($success) {
        // Clear the session data
        WC()->session->set('fleeca_current_order_id', null);
        
        // Add success message
        wc_add_notice('Payment successful! Thank you for your order.', 'success');
        
        // Log the successful payment
        gtaw_add_log(
            'fleeca', 
            'Payment', 
            "Order #{$order_id} payment completed with token {$token} for " . wc_price($order->get_total()), 
            'success'
        );
        
        // Clear token cache as it's now been used
        gtaw_fleeca_clear_token_cache($token);
        
        // Apply order-specific actions via filter
        do_action('gtaw_fleeca_payment_complete', $order, $token_data);
        
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
    
    return $success;
}

/**
 * Rate limiting function to prevent abuse
 * 
 * @param string $action The action being rate-limited
 * @return bool Whether the request should be allowed
 */
function gtaw_fleeca_check_rate_limit($action) {
    // Generate a unique key for the current IP or user
    $unique_id = '';
    
    if (is_user_logged_in()) {
        $unique_id = 'user_' . get_current_user_id();
    } else {
        $unique_id = 'ip_' . md5($_SERVER['REMOTE_ADDR']);
    }
    
    $rate_key = "gtaw_fleeca_rate_{$action}_{$unique_id}";
    $count = get_transient($rate_key);
    
    if ($count === false) {
        // First request in the time window
        set_transient($rate_key, 1, 60); // 60 second window
        return true;
    }
    
    // Increment count
    $count++;
    set_transient($rate_key, $count, 60);
    
    // Allow up to 5 requests in the time window
    $limit = 5;
    
    // Apply filters to allow custom rate limiting
    $limit = apply_filters('gtaw_fleeca_rate_limit', $limit, $action, $unique_id);
    
    if ($count > $limit) {
        return false;
    }
    
    return true;
}

/**
 * Handle errors in a standardized way with fallback options
 * 
 * @param string $message Error message to display
 */
function gtaw_fleeca_handle_error($message) {
    if (function_exists('wc_add_notice')) {
        wc_add_notice($message, 'error');
    }
    
    echo '<div class="woocommerce-error">' . esc_html($message) . '</div>';
    echo '<p>Redirecting to checkout...</p>';
    echo '<script>setTimeout(function() { window.location = "' . esc_url(function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url()) . '"; }, 5000);</script>';
}

/**
 * Add notice if payment was canceled
 */
function gtaw_fleeca_check_cancel_notice() {
    if (isset($_GET['fleeca_cancel'])) {
        wc_add_notice('Your Fleeca Bank payment was canceled. Please try again or select a different payment method.', 'error');
    }
}
add_action('woocommerce_before_checkout_form', 'gtaw_fleeca_check_cancel_notice');

/**
 * Set a flag to flush rules when settings are saved
 */
function gtaw_fleeca_settings_saved() {
    // When Fleeca settings are saved, mark that we need to flush rules
    update_option('gtaw_fleeca_flush_rules', 'yes');
    
    // Log the settings change
    gtaw_add_log('fleeca', 'Settings', 'Fleeca settings updated, rewrite rules will be flushed', 'success');
}
add_action('update_option_gtaw_fleeca_settings', 'gtaw_fleeca_settings_saved');
add_action('update_option_gtaw_fleeca_enabled', 'gtaw_fleeca_settings_saved');
add_action('update_option_gtaw_fleeca_callback_url', 'gtaw_fleeca_settings_saved');

/**
 * Flush on plugin activation
 */
function gtaw_fleeca_activation() {
    // This ensures rules are flushed when the plugin is activated
    update_option('gtaw_fleeca_flush_rules', 'yes');
}
register_activation_hook(plugin_basename(GTAW_BRIDGE_PLUGIN_DIR . 'gtaw-bridge.php'), 'gtaw_fleeca_activation');

/**
 * Add debugging tools to WooCommerce orders
 */
function gtaw_fleeca_add_order_debug_actions($actions, $order) {
    // Only for admin users and Fleeca orders
    if (!current_user_can('manage_options') || $order->get_payment_method() !== 'fleeca') {
        return $actions;
    }
    
    // Only add for orders that might need debugging
    if (in_array($order->get_status(), ['pending', 'on-hold', 'failed'])) {
        $token = get_post_meta($order->get_id(), '_fleeca_payment_token', true);
        
        if (!empty($token)) {
            $validate_url = add_query_arg([
                'action' => 'gtaw_fleeca_validate_token',
                'order_id' => $order->get_id(),
                'token' => $token,
                'nonce' => wp_create_nonce('gtaw_fleeca_debug')
            ], admin_url('admin-ajax.php'));
            
            $actions['gtaw_fleeca_debug'] = [
                'url' => $validate_url,
                'name' => __('Debug Fleeca Payment', 'gtaw-bridge'),
                'action' => 'view',
            ];
        }
    }
    
    return $actions;
}
add_filter('woocommerce_order_actions', 'gtaw_fleeca_add_order_debug_actions', 10, 2);

/**
 * AJAX handler for token validation debug
 */
function gtaw_fleeca_debug_token_validation() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied');
    }
    
    // Verify nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'gtaw_fleeca_debug')) {
        wp_die('Security check failed');
    }
    
    // Get order and token
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    
    if (empty($order_id) || empty($token)) {
        wp_die('Missing required parameters');
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die('Order not found');
    }
    
    // Set debug mode to true temporarily
    add_filter('pre_option_gtaw_fleeca_settings', function($value) {
        if (!is_array($value)) {
            $value = [];
        }
        $value['debug_mode'] = true;
        return $value;
    });
    
    echo '<h1>Fleeca Payment Debug for Order #' . esc_html($order_id) . '</h1>';
    echo '<p>Testing token validation for: ' . esc_html($token) . '</p>';
    
    // Try non-strict validation
    echo '<h2>Standard Validation</h2>';
    $result = gtaw_fleeca_validate_token($token, false, true);
    if (is_wp_error($result)) {
        echo '<p style="color: red;">Error: ' . esc_html($result->get_error_message()) . '</p>';
    } else {
        echo '<p style="color: green;">Success!</p>';
        echo '<pre>' . esc_html(print_r($result, true)) . '</pre>';
    }
    
    // Try strict validation
    echo '<h2>Strict Validation</h2>';
    $strict_result = gtaw_fleeca_validate_token($token, true, true);
    if (is_wp_error($strict_result)) {
        echo '<p style="color: red;">Error: ' . esc_html($strict_result->get_error_message()) . '</p>';
    } else {
        echo '<p style="color: green;">Success!</p>';
        echo '<pre>' . esc_html(print_r($strict_result, true)) . '</pre>';
    }
    
    // Add debug tools
    echo '<h2>Order Information</h2>';
    echo '<p>Order Total: ' . wc_price($order->get_total()) . '</p>';
    echo '<p>Order Status: ' . esc_html($order->get_status()) . '</p>';
    
    echo '<h2>Debug Actions</h2>';
    echo '<ul>';
    
    // Provide option to reprocess the payment if validation succeeded
    if (!is_wp_error($result) || !is_wp_error($strict_result)) {
        $reprocess_url = add_query_arg([
            'action' => 'gtaw_fleeca_reprocess_payment',
            'order_id' => $order_id,
            'token' => $token,
            'nonce' => wp_create_nonce('gtaw_fleeca_debug')
        ], admin_url('admin-ajax.php'));
        
        echo '<li><a href="' . esc_url($reprocess_url) . '" class="button">Reprocess Payment</a></li>';
    }
    
    // Option to clear token cache
    $clear_cache_url = add_query_arg([
        'action' => 'gtaw_fleeca_clear_token_cache',
        'token' => $token,
        'nonce' => wp_create_nonce('gtaw_fleeca_debug')
    ], admin_url('admin-ajax.php'));
    
    echo '<li><a href="' . esc_url($clear_cache_url) . '" class="button">Clear Token Cache</a></li>';
    
    // Return to order link
    echo '<li><a href="' . esc_url($order->get_edit_order_url()) . '" class="button">Return to Order</a></li>';
    
    echo '</ul>';
    
    wp_die();
}
add_action('wp_ajax_gtaw_fleeca_validate_token', 'gtaw_fleeca_debug_token_validation');

/**
 * AJAX handler for clearing token cache
 */
function gtaw_fleeca_ajax_clear_token_cache() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    // Verify nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'gtaw_fleeca_debug')) {
        wp_send_json_error('Security check failed');
    }
    
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    
    if (empty($token)) {
        wp_send_json_error('Missing token parameter');
    }
    
    gtaw_fleeca_clear_token_cache($token);
    wp_send_json_success('Token cache cleared successfully');
}
add_action('wp_ajax_gtaw_fleeca_clear_token_cache', 'gtaw_fleeca_ajax_clear_token_cache');

/**
 * AJAX handler for manually reprocessing a payment
 */
function gtaw_fleeca_ajax_reprocess_payment() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    // Verify nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'gtaw_fleeca_debug')) {
        wp_send_json_error('Security check failed');
    }
    
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    
    if (empty($order_id) || empty($token)) {
        wp_send_json_error('Missing required parameters');
    }
    
    // Set up session for the order ID
    WC()->session->set('fleeca_current_order_id', $order_id);
    
    // Process the payment
    try {
        $result = gtaw_fleeca_process_callback($token);
        
        if ($result) {
            wp_send_json_success('Payment processed successfully');
        } else {
            wp_send_json_error('Failed to process payment');
        }
    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}
add_action('wp_ajax_gtaw_fleeca_reprocess_payment', 'gtaw_fleeca_ajax_reprocess_payment');

/**
 * Register a manual flush action for troubleshooting
 */
function gtaw_register_fleeca_manual_flush() {
    // Only accessible to admins
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_GET['fleeca_flush_rules']) && $_GET['fleeca_flush_rules'] === 'yes' && 
        isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'fleeca_flush_rules')) {
        
        flush_rewrite_rules();
        gtaw_add_log('fleeca', 'Rewrite', 'Rewrite rules manually flushed', 'success');
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Fleeca rewrite rules have been flushed.</p></div>';
        });
    }
}
add_action('admin_init', 'gtaw_register_fleeca_manual_flush');

/**
 * "Flush Rules" button in Settings page with nonce security
 */
function gtaw_add_fleeca_flush_button($content) {
    if (!is_admin() || !current_user_can('manage_options')) {
        return $content;
    }
    
    global $pagenow;
    if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'gtaw-fleeca') {
        $flush_url = wp_nonce_url(
            add_query_arg(['fleeca_flush_rules' => 'yes'], admin_url('admin.php?page=gtaw-fleeca')),
            'fleeca_flush_rules'
        );
        
        echo '<a href="' . esc_url($flush_url) . '" class="button button-secondary" style="margin-left: 10px;">Flush Rewrite Rules</a>';
    }
    
    return $content;
}
add_filter('gtaw_fleeca_after_settings', 'gtaw_add_fleeca_flush_button');