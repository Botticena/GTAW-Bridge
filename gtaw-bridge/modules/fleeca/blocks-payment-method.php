<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' )
	|| ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry' ) ) {
	return;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

final class GTAW_Fleeca_Blocks_Payment_Method extends AbstractPaymentMethodType {

	protected $name = 'fleeca';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_fleeca_settings', array() );
	}

	public function is_active() {
		if ( ! function_exists( 'gtaw_fleeca_get_setting' ) || ! gtaw_fleeca_get_setting( 'enabled', false ) ) {
			return false;
		}
		$enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : '';
		return 'yes' === $enabled;
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'gtaw-fleeca-blocks',
			GTAW_BRIDGE_PLUGIN_URL . 'assets/js/fleeca-blocks.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wc-blocks-checkout',
				'wp-element',
				'wp-html-entities',
			),
			GTAW_BRIDGE_VERSION,
			true
		);
		return array( 'gtaw-fleeca-blocks' );
	}

	public function get_payment_method_script_handles_for_admin() {
		return $this->get_payment_method_script_handles();
	}

	public function get_payment_method_data() {
		$title       = __( 'Fleeca Bank', 'gtaw-bridge' );
		$description = __( 'Pay securely using your Fleeca Bank account from GTA World.', 'gtaw-bridge' );

		$gt = get_option( 'gtaw_fleeca_settings', array() );
		if ( ! empty( $gt['gateway_name'] ) ) {
			$title = $gt['gateway_name'];
		}
		if ( ! empty( $this->settings['title'] ) ) {
			$title = $this->settings['title'];
		}
		if ( ! empty( $this->settings['description'] ) ) {
			$description = $this->settings['description'];
		}

		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			$list = WC()->payment_gateways()->payment_gateways();
			if ( isset( $list['fleeca'] ) && is_a( $list['fleeca'], 'WC_Payment_Gateway' ) ) {
				$g = $list['fleeca'];
				$t = $g->get_title();
				$d = $g->get_description();
				if ( is_string( $t ) && $t !== '' ) {
					$title = $t;
				}
				if ( is_string( $d ) && $d !== '' ) {
					$description = $d;
				}
			}
		}

		$api_ok = function_exists( 'gtaw_fleeca_get_setting' ) && (string) gtaw_fleeca_get_setting( 'api_key', '' ) !== '';

		$logo_url = function_exists( 'gtaw_fleeca_get_logo_url' ) ? gtaw_fleeca_get_logo_url() : '';

		return array(
			'title'       => is_string( $title ) ? wp_strip_all_tags( $title ) : $title,
			'description' => is_string( $description ) ? wp_strip_all_tags( $description ) : '',
			'supports'    => $this->get_supported_features(),
			'enabled'     => $api_ok,
			'logo_url'    => esc_url( $logo_url ),
		);
	}
}

$gtaw_fleeca_blocks_register = function ( PaymentMethodRegistry $payment_method_registry ) {
	$payment_method_registry->register( new GTAW_Fleeca_Blocks_Payment_Method() );
};

$gtaw_fleeca_blocks_bootstrap = function () use ( $gtaw_fleeca_blocks_register ) {
	add_action( 'woocommerce_blocks_payment_method_type_registration', $gtaw_fleeca_blocks_register, 5, 1 );
};

if ( did_action( 'woocommerce_blocks_loaded' ) ) {
	$gtaw_fleeca_blocks_bootstrap();
} else {
	add_action( 'woocommerce_blocks_loaded', $gtaw_fleeca_blocks_bootstrap, 5 );
}
