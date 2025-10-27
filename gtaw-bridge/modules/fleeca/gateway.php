<?php
defined('ABSPATH') or exit;

/* ========= FLEECA WOOCOMMERCE GATEWAY ========= */
/*
 * This file implements the WooCommerce payment gateway for Fleeca Bank:
 * - Register the gateway in WooCommerce
 * - Handle payment processing and redirection
 * - Manage order status updates
 * 
 * @version 2.0 Enhanced with improved error handling, performance monitoring,
 * and better integration with WooCommerce
 */

// First check if WooCommerce is active before proceeding
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Add the Fleeca Bank Gateway to WooCommerce
 *
 * @param array $gateways Array of registered gateways
 * @return array Updated gateways array
 */
function gtaw_add_fleeca_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Fleeca';
    return $gateways;
}

// Initialize the gateway after WooCommerce is loaded
add_action('plugins_loaded', 'gtaw_fleeca_gateway_init', 20);

/**
 * Initialize the Fleeca Bank gateway
 */
function gtaw_fleeca_gateway_init() {
    // Prevent multiple initializations in the same request
    static $initialized = false;
    if ($initialized) {
        return;
    }
    
    // Start performance tracking
    gtaw_perf_start('fleeca_gateway_init');
    
    // Ensure WooCommerce payment gateway class exists
    if (!class_exists('WC_Payment_Gateway')) {
        gtaw_perf_end('fleeca_gateway_init');
        return;
    }
    
    /**
     * Fleeca Bank WooCommerce Gateway
     */
    class WC_Gateway_Fleeca extends WC_Payment_Gateway {
        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            // Basic gateway setup
            $this->id = 'fleeca';
            $this->method_title = 'Fleeca Bank';
            $this->method_description = 'Redirects customers to Fleeca Bank to make their payment using in-game currency.';
            $this->has_fields = false;
            $this->supports = [
                'products',
                'refunds' => false
            ];
            
            // Get settings from consolidated options
            $settings = get_option('gtaw_fleeca_settings', []);
            
            // Get gateway display name from settings or use default
            $this->title = isset($settings['gateway_name']) ? $settings['gateway_name'] : 'Fleeca Bank';
            $this->description = 'Pay securely using your Fleeca Bank account from GTA World.';
            
            // Optional icon (could be added in the future)
            $this->icon = apply_filters('woocommerce_gtaw_fleeca_icon', '');
            
            // Get API key from consolidated settings
            $this->api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
            $this->sandbox_mode = isset($settings['sandbox_mode']) ? (bool)$settings['sandbox_mode'] : false;
            
            // Load the settings
            $this->init_settings();
            
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            
            // Add filter to customize the thankyou page
            add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page'], 10, 1);
        }
        
        /**
         * Initialize form fields for the gateway settings page
         */
        public function init_form_fields() {
            $this->form_fields = [
                'title' => [
                    'title'       => __('Title', 'gtaw-bridge'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'gtaw-bridge'),
                    'default'     => $this->title,
                    'desc_tip'    => true,
                ],
                'description' => [
                    'title'       => __('Description', 'gtaw-bridge'),
                    'type'        => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'gtaw-bridge'),
                    'default'     => $this->description,
                    'desc_tip'    => true,
                ],
                'api_details' => [
                    'title'       => __('API Configuration', 'gtaw-bridge'),
                    'type'        => 'title',
                    'description' => __('API settings are managed in the GTAW Bridge Fleeca Module settings page.', 'gtaw-bridge'),
                ],
                'debug_info' => [
                    'title'       => __('Debug Information', 'gtaw-bridge'),
                    'type'        => 'title',
                    'description' => sprintf(
                        __('API Key: %s<br>Sandbox Mode: %s<br>Callback URL: %s', 'gtaw-bridge'),
                        strlen($this->api_key) > 5 ? substr($this->api_key, 0, 5) . '...' : __('Not set', 'gtaw-bridge'),
                        $this->sandbox_mode ? __('Enabled', 'gtaw-bridge') : __('Disabled', 'gtaw-bridge'),
                        gtaw_fleeca_get_callback_base_url()
                    ),
                ],
            ];
        }
        
        /**
         * Processes the payment and redirects to Fleeca Bank
         *
         * @param int $order_id WooCommerce order ID
         * @return array Payment process result and redirect URL
         */
        public function process_payment($order_id) {
            // Start performance tracking
            gtaw_perf_start('fleeca_process_payment');
            
            // Get the order
            $order = wc_get_order($order_id);
            
            if (!$order) {
                gtaw_add_log('fleeca', 'Error', "Invalid order ID: {$order_id}", 'error');
                gtaw_perf_end('fleeca_process_payment');
                return [
                    'result'   => 'failure',
                    'messages' => 'Invalid order',
                ];
            }
            
            // Get order total
            $amount = intval($order->get_total());
            
            // Store order ID in session for retrieval during callback
            WC()->session->set('fleeca_current_order_id', $order_id);
            
            // Generate the payment URL
            $payment_url = gtaw_fleeca_get_payment_url($amount, $this->api_key);
            
            if (empty($payment_url)) {
                gtaw_add_log('fleeca', 'Error', "Failed to generate payment URL for order {$order_id}", 'error');
                gtaw_perf_end('fleeca_process_payment');
                return [
                    'result'   => 'failure',
                    'messages' => 'Failed to create payment URL',
                ];
            }
            
            // Add a token to the session for additional security verification
            $security_token = wp_generate_password(32, false);
            WC()->session->set('fleeca_security_token', $security_token);
            update_post_meta($order_id, '_fleeca_security_token', $security_token);
            
            // Mark as pending payment
            $order->update_status('pending', __('Customer redirected to Fleeca Bank.', 'gtaw-bridge'));
            
            // Add custom note about amount
            $order->add_order_note(sprintf(__('Customer redirected to Fleeca Bank for payment of %s.', 'gtaw-bridge'), 
                wc_price($amount)), false);
            
            // Log the redirection
            gtaw_add_log('fleeca', 'Redirect', "Redirecting customer to Fleeca Bank for order {$order_id} (Amount: {$amount})", 'success');
            
            // End performance tracking
            gtaw_perf_end('fleeca_process_payment');
            
            // Redirect to Fleeca Bank
            return [
                'result'   => 'success',
                'redirect' => $payment_url,
            ];
        }
        
        /**
         * Check if this gateway is enabled and available for use
         *
         * @return bool
         */
        public function is_available() {
            $parent_available = parent::is_available();
            
            if ($parent_available) {
                // Check if required settings are configured
                if (empty($this->api_key)) {
                    return false;
                }
                
                // Check if we're in a supported currency (only USD/GTA$ is supported)
                $currency = get_woocommerce_currency();
                if ($currency !== 'USD') {
                    return false;
                }
                
                // Check if the current user is allowed to use Fleeca Bank
                // This could be extended with permission checks if needed
                $user_id = get_current_user_id();
                if ($user_id && !apply_filters('gtaw_fleeca_user_can_use', true, $user_id)) {
                    return false;
                }
                
                return true;
            }
            
            return false;
        }
        
        /**
         * Output for the order received page with Fleeca-specific details
         *
         * @param int $order_id Order ID
         */
        public function thankyou_page($order_id) {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return;
            }
            
            // Check if this is a Fleeca Bank order
            if ($order->get_payment_method() !== 'fleeca') {
                return;
            }
            
            // Get transaction meta data
            $token = get_post_meta($order_id, '_fleeca_payment_token', true);
            $routing_from = get_post_meta($order_id, '_fleeca_routing_from', true);
            $routing_to = get_post_meta($order_id, '_fleeca_routing_to', true);
            $sandbox = get_post_meta($order_id, '_fleeca_sandbox', true) === 'yes';
            
            // Only show details for completed payments
            if (empty($token)) {
                return;
            }
            
            echo '<h2>' . __('Fleeca Bank Payment Details', 'gtaw-bridge') . '</h2>';
            echo '<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">';
            
            // Transaction ID
            if ($transaction_id = $order->get_transaction_id()) {
                echo '<li class="woocommerce-order-overview__transaction transaction">';
                echo __('Transaction ID:', 'gtaw-bridge') . ' <strong>' . esc_html($transaction_id) . '</strong>';
                echo '</li>';
            }
            
            // From Account
            if (!empty($routing_from)) {
                echo '<li class="woocommerce-order-overview__account-from account-from">';
                echo __('From Account:', 'gtaw-bridge') . ' <strong>' . esc_html($routing_from) . '</strong>';
                echo '</li>';
            }
            
            // To Account
            if (!empty($routing_to)) {
                echo '<li class="woocommerce-order-overview__account-to account-to">';
                echo __('To Account:', 'gtaw-bridge') . ' <strong>' . esc_html($routing_to) . '</strong>';
                echo '</li>';
            }
            
            // Sandbox indicator
            if ($sandbox) {
                echo '<li class="woocommerce-order-overview__test-mode test-mode">';
                echo '<strong>' . __('Test Payment', 'gtaw-bridge') . '</strong> - ' . __('This payment was processed in sandbox mode.', 'gtaw-bridge');
                echo '</li>';
            }
            
            echo '</ul>';
            
            // Additional note for sandbox payments
            if ($sandbox) {
                echo '<div class="woocommerce-info">' . __('Note: This was a test payment made in sandbox mode. No real money was transferred.', 'gtaw-bridge') . '</div>';
            }
        }
        
        /**
         * Process refunds - this is a placeholder as Fleeca doesn't support refunds
         * 
         * @param int $order_id Order ID
         * @param float $amount Refund amount
         * @param string $reason Refund reason
         * @return bool|WP_Error
         */
        public function process_refund($order_id, $amount = null, $reason = '') {
            // Fleeca doesn't support automated refunds
            return new WP_Error('fleeca_refund_not_supported', __('Fleeca Bank does not support automated refunds. Please process the refund manually in-game.', 'gtaw-bridge'));
        }
    }
    
    // Register the gateway with WooCommerce
    add_filter('woocommerce_payment_gateways', 'gtaw_add_fleeca_gateway');
    
    // Mark as initialized to prevent duplicate operations
    $initialized = true;

    // End performance tracking
    gtaw_perf_end('fleeca_gateway_init');
}

/**
 * Add settings link on plugin page
 *
 * @param array $links Default plugin action links
 * @return array Modified links
 */
function gtaw_fleeca_gateway_plugin_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=gtaw-fleeca') . '">' . __('Settings', 'gtaw-bridge') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_gtaw-bridge/gtaw-bridge.php', 'gtaw_fleeca_gateway_plugin_links');

/**
 * Add custom gateway information to the order emails
 * 
 * @param WC_Order $order Order object
 * @param bool $sent_to_admin Whether the email is for admin
 * @param bool $plain_text Whether the email is plain text
 */
function gtaw_fleeca_email_payment_details($order, $sent_to_admin, $plain_text = false) {
    if (!$order || $order->get_payment_method() !== 'fleeca') {
        return;
    }
    
    // Only show for completed orders
    if ($order->get_status() !== 'completed' && $order->get_status() !== 'processing') {
        return;
    }
    
    $order_id = $order->get_id();
    $token = get_post_meta($order_id, '_fleeca_payment_token', true);
    $sandbox = get_post_meta($order_id, '_fleeca_sandbox', true) === 'yes';
    
    // Only show if we have transaction data
    if (empty($token)) {
        return;
    }
    
    if ($plain_text) {
        // Plain text email
        echo "\n\n" . __('Fleeca Bank Payment Details', 'gtaw-bridge') . "\n";
        echo __('Transaction ID:', 'gtaw-bridge') . ' ' . $order->get_transaction_id() . "\n";
        
        if ($sandbox) {
            echo __('Test Payment - This payment was processed in sandbox mode.', 'gtaw-bridge') . "\n";
        }
    } else {
        // HTML email
        echo '<h2>' . __('Fleeca Bank Payment Details', 'gtaw-bridge') . '</h2>';
        echo '<p><strong>' . __('Transaction ID:', 'gtaw-bridge') . '</strong> ' . esc_html($order->get_transaction_id()) . '</p>';
        
        if ($sandbox) {
            echo '<p><em>' . __('Note: This was a test payment made in sandbox mode. No real money was transferred.', 'gtaw-bridge') . '</em></p>';
        }
    }
}
add_action('woocommerce_email_order_details', 'gtaw_fleeca_email_payment_details', 20, 3);