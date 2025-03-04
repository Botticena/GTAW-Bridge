<?php
defined('ABSPATH') or exit;

/* ========= FLEECA WOOCOMMERCE GATEWAY ========= */
/*
 * This file implements the WooCommerce payment gateway for Fleeca Bank:
 * - Register the gateway in WooCommerce
 * - Handle payment processing and redirection
 * - Manage order status updates
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
    // Ensure WooCommerce payment gateway class exists
    if (!class_exists('WC_Payment_Gateway')) {
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
            $this->id = 'fleeca';
            $this->method_title = 'Fleeca Bank';
            $this->method_description = 'Redirects customers to Fleeca Bank to make their payment.';
            
            // Get gateway display name from settings or use default
            $this->title = get_option('gtaw_fleeca_gateway_name', 'Fleeca Bank');
            $this->description = 'Pay securely using your Fleeca Bank account.';
            $this->icon = ''; // Optional: URL to icon image
            
            // Support flags
            $this->has_fields = false; // We don't need custom fields
            $this->supports = array(
                'products',
            );
            
            // Load the settings
            $this->init_settings();
            
            // Get API key from GTAW Bridge settings
            $this->api_key = get_option('gtaw_fleeca_api_key', '');
            
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
        
        /**
         * Processes the payment and redirects to Fleeca Bank
         *
         * @param int $order_id WooCommerce order ID
         * @return array Payment process result and redirect URL
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                gtaw_add_log('fleeca', 'Error', "Invalid order ID: {$order_id}", 'error');
                return array(
                    'result'   => 'failure',
                    'messages' => 'Invalid order',
                );
            }
            
            // Get order total
            $amount = intval($order->get_total());
            
            // Store order ID in session for retrieval during callback
            WC()->session->set('fleeca_current_order_id', $order_id);
            
            // Generate the payment URL
            $payment_url = gtaw_fleeca_get_payment_url($amount, $this->api_key);
            
            if (empty($payment_url)) {
                gtaw_add_log('fleeca', 'Error', "Failed to generate payment URL for order {$order_id}", 'error');
                return array(
                    'result'   => 'failure',
                    'messages' => 'Failed to create payment URL',
                );
            }
            
            // Mark as pending payment
            $order->update_status('pending', __('Customer redirected to Fleeca Bank.', 'gtaw-bridge'));
            
            // Log the redirection
            gtaw_add_log('fleeca', 'Redirect', "Redirecting customer to Fleeca Bank for order {$order_id}", 'success');
            
            // Redirect to Fleeca Bank
            return array(
                'result'   => 'success',
                'redirect' => $payment_url,
            );
        }
        
        /**
         * Check if this gateway is enabled and configured correctly
         *
         * @return bool
         */
        public function is_available() {
            $is_available = parent::is_available();
            
            if ($is_available) {
                // Also check if API key is set
                if (empty($this->api_key)) {
                    $is_available = false;
                }
            }
            
            return $is_available;
        }
    }
    
    // Register the gateway with WooCommerce
    add_filter('woocommerce_payment_gateways', 'gtaw_add_fleeca_gateway');
}