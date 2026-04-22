<?php
defined('ABSPATH') or exit;

// WooCommerce gateway: create Fleeca payment, redirect to their hosted URL.

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

function gtaw_add_fleeca_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Fleeca';
    return $gateways;
}

add_action('plugins_loaded', 'gtaw_fleeca_gateway_init', 20);

function gtaw_fleeca_gateway_init() {
    // Prevent multiple initializations in the same request
    static $initialized = false;
    if ($initialized) {
        return;
    }
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
            $this->method_description = 'Redirects customers to Fleeca Bank to make their payment.';
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

            $this->icon = function_exists( 'gtaw_fleeca_get_logo_url' ) ? gtaw_fleeca_get_logo_url() : '';
            
            // Get API key from consolidated settings
            $this->api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
            $this->sandbox_mode = isset($settings['sandbox_mode']) ? (bool)$settings['sandbox_mode'] : false;
            
            // Load the settings
            $this->init_settings();
            
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }

        /**
         * Admin WC payment settings — debug / integration hints.
         *
         * @return string
         */
        public function get_debug_info_text() {
            $key_hint = strlen( $this->api_key ) > 5 ? substr( $this->api_key, 0, 5 ) . '...' : __( 'Not set', 'gtaw-bridge' );
            $sandbox  = $this->sandbox_mode ? __( 'Enabled', 'gtaw-bridge' ) : __( 'Disabled', 'gtaw-bridge' );
            $webhook  = function_exists( 'gtaw_fleeca_get_webhook_url' ) ? untrailingslashit( gtaw_fleeca_get_webhook_url() ) : '';
            $return   = function_exists( 'gtaw_fleeca_get_return_url' ) ? untrailingslashit( gtaw_fleeca_get_return_url() ) : '';
            return sprintf(
                /* translators: 1: key prefix, 2: sandbox, 3: server webhook URL, 4: browser Redirect URL */
                esc_html__( 'API key: %1$s. Sandbox: %2$s. Server webhook: %3$s. Browser return: %4$s', 'gtaw-bridge' ),
                esc_html( $key_hint ),
                esc_html( $sandbox ),
                esc_html( $webhook ),
                esc_html( $return )
            );
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
                    'description' => $this->get_debug_info_text(),
                ],
            ];
        }
        
        /**
         * Processes the payment and redirects to Fleeca Bank
         *
         * @param int $order_id WooCommerce order ID
         * @return array Payment process result and redirect URL
         */
        public function process_payment( $order_id ) {
            gtaw_perf_start( 'fleeca_process_payment' );
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                gtaw_add_log( 'fleeca', 'Error', 'Invalid order ID: ' . (int) $order_id, 'error' );
                gtaw_perf_end( 'fleeca_process_payment' );
                return [
                    'result'   => 'failure',
                    'messages' => __( 'Invalid order.', 'gtaw-bridge' ),
                ];
            }
            $amount = (int) $order->get_total();
            if ( $amount < 1 ) {
                gtaw_add_log( 'fleeca', 'Error', 'Order total below minimum for Fleeca: ' . (int) $order_id, 'error' );
                gtaw_perf_end( 'fleeca_process_payment' );
                return [
                    'result'   => 'failure',
                    'messages' => __( 'Order amount is invalid for Fleeca.', 'gtaw-bridge' ),
                ];
            }
            WC()->session->set( 'fleeca_current_order_id', $order_id );
            $mode         = $this->sandbox_mode ? 0 : 1;
            $order_number = $order->get_order_number();
            $shop_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
            $shop_name    = is_string( $shop_name ) ? trim( $shop_name ) : '';
            if ( '' === $shop_name ) {
                $shop_name = __( 'Shop', 'gtaw-bridge' );
            }
            $default_desc = sprintf(
                /* translators: 1: site/shop name, 2: order number */
                __( '%1$s - Order #%2$s', 'gtaw-bridge' ),
                $shop_name,
                $order_number
            );
            $description  = apply_filters( 'gtaw_fleeca_payment_description', $default_desc, $order );
            $description  = apply_filters( 'gtaw_fleeca_v2_payment_description', $description, $order );
            if ( ! is_string( $description ) ) {
                $description = $default_desc;
            }
            $result = gtaw_fleeca_create_hosted_payment( $amount, $mode, $description );
            if ( is_wp_error( $result ) ) {
                gtaw_add_log( 'fleeca', 'Error', 'Create hosted payment: ' . $result->get_error_message(), 'error' );
                gtaw_perf_end( 'fleeca_process_payment' );
                return [
                    'result'   => 'failure',
                    'messages' => $result->get_error_message(),
                ];
            }
            $order->update_meta_data( '_fleeca_payment_id', $result['payment_id'] );
            $order->update_meta_data( '_fleeca_expected_amount', (string) $amount );
            $order->update_meta_data( '_fleeca_payment_link', esc_url_raw( $result['payment_link'] ) );
            $order->update_status( 'pending', __( 'Customer sent to Fleeca hosted checkout.', 'gtaw-bridge' ) );
            $order->add_order_note(
                sprintf(
                    /* translators: %s: Fleeca payment UUID */
                    __( 'Fleeca payment created. payment_id: %s', 'gtaw-bridge' ),
                    $result['payment_id']
                )
            );
            $order->save();
            gtaw_add_log( 'fleeca', 'Redirect', 'Order ' . (int) $order_id . ' redirect to Fleeca payment_link', 'success' );
            gtaw_perf_end( 'fleeca_process_payment' );
            return [
                'result'   => 'success',
                'redirect' => $result['payment_link'],
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
         * Process refunds - this is a placeholder as Fleeca doesn't support refunds
         * 
         * @param int $order_id Order ID
         * @param float $amount Refund amount
         * @param string $reason Refund reason
         * @return bool|WP_Error
         */
        public function process_refund($order_id, $amount = null, $reason = '') {
            // Fleeca doesn't support automated refunds
            return new WP_Error('fleeca_refund_not_supported', __('Fleeca Bank does not support automated refunds. Please process the refund manually.', 'gtaw-bridge'));
        }

        public function get_icon() {
            $url = is_string( $this->icon ) ? trim( $this->icon ) : '';
            if ( $url === '' ) {
                return parent::get_icon();
            }
            $alt  = esc_attr( $this->get_title() );
            $html = '<img src="' . esc_url( $url ) . '" alt="' . $alt . '" class="gtaw-fleeca-checkout-icon" style="max-height:28px;width:auto;max-width:100px;vertical-align:middle;object-fit:contain;margin:0 0.45em 0 0;" />';
            return apply_filters( 'woocommerce_gateway_icon', $html, $this->id );
        }
    }
    
    // Register the gateway with WooCommerce
    add_filter('woocommerce_payment_gateways', 'gtaw_add_fleeca_gateway');
    
    // Mark as initialized to prevent duplicate operations
    $initialized = true;
    gtaw_perf_end('fleeca_gateway_init');
}

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
    
    $pay_id  = (string) $order->get_meta( '_fleeca_payment_id' );
    $sandbox = 'yes' === $order->get_meta( '_fleeca_sandbox' );
    if ( ! $pay_id && ! $order->get_transaction_id() ) {
        return;
    }
    
    if ($plain_text) {
        echo "\n\n" . __('Fleeca Bank payment details', 'gtaw-bridge') . "\n";
        echo __('Transaction ID:', 'gtaw-bridge') . ' ' . $order->get_transaction_id() . "\n";
        if ( $pay_id ) {
            echo __( 'Fleeca payment ID:', 'gtaw-bridge' ) . ' ' . $pay_id . "\n";
        }
        if ( $sandbox ) {
            echo __( 'Sandbox / test — no real transfer if applicable.', 'gtaw-bridge' ) . "\n";
        }
    } else {
        echo '<h2>' . esc_html__( 'Fleeca Bank payment details', 'gtaw-bridge' ) . '</h2>';
        echo '<p><strong>' . esc_html__( 'Transaction ID:', 'gtaw-bridge' ) . '</strong> ' . esc_html( (string) $order->get_transaction_id() ) . '</p>';
        if ( $pay_id ) {
            echo '<p><strong>' . esc_html__( 'Fleeca payment ID:', 'gtaw-bridge' ) . '</strong> ' . esc_html( $pay_id ) . '</p>';
        }
        if ( $sandbox ) {
            echo '<p><em>' . esc_html__( 'Sandbox or test payment where applicable.', 'gtaw-bridge' ) . '</em></p>';
        }
    }
}
add_action('woocommerce_email_order_details', 'gtaw_fleeca_email_payment_details', 20, 3);