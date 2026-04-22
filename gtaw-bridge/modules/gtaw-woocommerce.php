<?php
/**
 * WooCommerce: GTA:W store rules — United States + San Andreas state, address labels, no postcode/Contact UI
 * in checkout; billing email from the logged-in account. Loaded whenever WooCommerce is available.
 *
 * @package GTAW_Bridge
 */

defined( 'ABSPATH' ) || exit;

// --- Shared copy & helpers (single source for i18n + CA→SA) ---

/**
 * @return array{line1: array, line2: array, state: string}
 */
function gtaw_wc_get_address_i18n() {
	static $i;
	if ( null === $i ) {
		$i = array(
			'line1' => array(
				'label'         => __( 'Address (property name)', 'gtaw-bridge' ),
				'placeholder'   => __( '(( Exact property name ))', 'gtaw-bridge' ),
			),
			'line2' => array(
				'required' => false,
				'label'    => __( 'Apartment, suite, etc.', 'gtaw-bridge' ),
			),
			'state' => __( 'San Andreas', 'gtaw-bridge' ),
		);
	}
	return $i;
}

/**
 * @param string $v State code.
 * @return string
 */
function gtaw_wc_map_ca_to_sa( $v ) {
	if ( ! is_string( $v ) ) {
		return $v;
	}
	$t = strtoupper( trim( $v ) );
	return ( 'CA' === $t ) ? 'SA' : $v;
}

/**
 * @return string Valid user email, or empty.
 */
function gtaw_wc_get_logged_in_order_email() {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	$e = (string) wp_get_current_user()->user_email;
	return is_email( $e ) ? $e : '';
}

/**
 * @param WC_Order $order Order.
 * @return void
 */
function gtaw_wc_order_ensure_billing_email( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) || (string) $order->get_billing_email() ) {
		return;
	}
	$e = gtaw_wc_get_logged_in_order_email();
	if ( $e ) {
		$order->set_billing_email( $e );
	}
}

/**
 * @param WC_Order $order Order.
 * @return void
 */
function gtaw_wc_order_map_ca_states( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return;
	}
	foreach ( array( 'billing', 'shipping' ) as $p ) {
		$g = "get_{$p}_state";
		$s = "set_{$p}_state";
		if ( is_callable( array( $order, $g ) ) && is_callable( array( $order, $s ) ) ) {
			$v = (string) $order->{$g}();
			if ( 'CA' === strtoupper( trim( $v ) ) ) {
				$order->{$s}( 'SA' );
			}
		}
	}
}

// --- Filter / action handlers ---

/**
 * @return void
 */
function gtaw_wc_ensure_session_customer_billing_email() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
		return;
	}
	$e = gtaw_wc_get_logged_in_order_email();
	if ( ! $e || ! function_exists( 'WC' ) || ! WC()->customer ) {
		return;
	}
	$c = WC()->customer;
	if ( ! (string) $c->get_billing_email() ) {
		$c->set_billing_email( $e );
	}
}

/**
 * @param string   $html Block output.
 * @param array    $block Block.
 * @return string
 */
function gtaw_wc_render_block_strip_contact( $html, $block ) {
	$n = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
	return ( 'woocommerce/checkout-contact-information-block' === $n ) ? '' : $html;
}

/**
 * @param array $fields Checkout fields.
 * @return array
 */
function gtaw_wc_checkout_fields_strip_email( $fields ) {
	if ( isset( $fields['billing']['billing_email'] ) ) {
		unset( $fields['billing']['billing_email'] );
	}
	return $fields;
}

/**
 * @param array $fields Billing (classic account + checkout when applicable).
 * @return array
 */
function gtaw_wc_billing_fields_unset_postcode( $fields ) {
	unset( $fields['billing_postcode'] );
	return $fields;
}

/**
 * @param array $fields Shipping fields.
 * @return array
 */
function gtaw_wc_shipping_fields_unset_postcode( $fields ) {
	unset( $fields['shipping_postcode'] );
	return $fields;
}

/**
 * Merged: fill billing email, map legacy California → San Andreas.
 *
 * @param mixed $value Value.
 * @param string $input Key.
 * @return mixed
 */
function gtaw_wc_checkout_get_value( $value, $input ) {
	if ( 'billing_email' === $input && is_user_logged_in() ) {
		$e = gtaw_wc_get_logged_in_order_email();
		if ( $e && ( '' === (string) $value || ! is_email( (string) $value ) ) ) {
			return $e;
		}
		return $value;
	}
	if ( in_array( $input, array( 'billing_state', 'shipping_state' ), true ) && is_string( $value ) ) {
		return gtaw_wc_map_ca_to_sa( $value );
	}
	return $value;
}

/**
 * @param array $countries Allowed countries.
 * @return array
 */
function gtaw_wc_countries_us_only( $countries ) {
	if ( ! is_array( $countries ) || ! function_exists( 'WC' ) || ! WC()->countries ) {
		return $countries;
	}
	$all = WC()->countries->get_countries();
	return isset( $all['US'] ) ? array( 'US' => $all['US'] ) : $countries;
}

/**
 * @param array $states All states.
 * @return array
 */
function gtaw_wc_us_states_san_andreas( $states ) {
	if ( ! is_array( $states ) ) {
		return $states;
	}
	$strings                   = gtaw_wc_get_address_i18n();
	$states['US']              = array( 'SA' => $strings['state'] );
	return $states;
}

/**
 * @param array $fields Default address fields.
 * @return array
 */
function gtaw_wc_default_address_fields( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}
	$i = gtaw_wc_get_address_i18n();
	if ( isset( $fields['address_1'] ) && is_array( $fields['address_1'] ) ) {
		$fields['address_1'] = array_merge( $fields['address_1'], $i['line1'] );
	}
	if ( isset( $fields['address_2'] ) && is_array( $fields['address_2'] ) ) {
		$fields['address_2'] = array_merge( $fields['address_2'], $i['line2'] );
	}
	return $fields;
}

/**
 * @param array $locale Country locale.
 * @return array
 */
function gtaw_wc_country_locale( $locale ) {
	if ( ! is_array( $locale ) ) {
		return $locale;
	}
	foreach ( $locale as $code => $data ) {
		if ( is_array( $data ) ) {
			$locale[ $code ]['postcode'] = array(
				'required' => false,
				'hidden'   => true,
			);
		}
	}
	$i = gtaw_wc_get_address_i18n();
	if ( isset( $locale['US'] ) && is_array( $locale['US'] ) ) {
		$locale['US']['address_1'] = array_merge(
			array( 'required' => true, 'hidden' => false ),
			$i['line1']
		);
		$locale['US']['address_2'] = array_merge( array( 'hidden' => false ), $i['line2'] );
	}
	return $locale;
}

/**
 * @param WC_Order $order Order.
 * @return void
 */
function gtaw_wc_on_checkout_create_order( $order ) {
	gtaw_wc_order_ensure_billing_email( $order );
	gtaw_wc_order_map_ca_states( $order );
}

/**
 * @param mixed $v Meta.
 * @param int   $oid User.
 * @param string $key Meta key.
 * @param bool  $single Single.
 * @return mixed
 */
function gtaw_wc_get_user_metadata_map_state( $v, $oid, $key, $single ) {
	if ( ! in_array( $key, array( 'billing_state', 'shipping_state' ), true ) ) {
		return $v;
	}
	if ( $single && is_string( $v ) ) {
		return gtaw_wc_map_ca_to_sa( $v );
	}
	if ( ! $single && is_array( $v ) ) {
		return array_map( 'gtaw_wc_map_ca_to_sa', $v );
	}
	return $v;
}

/**
 * @param int    $user_id User.
 * @param string $load_address billing|shipping.
 * @param array  $address Data.
 * @return void
 */
function gtaw_wc_save_address_map_state( $user_id, $load_address, $address ) {
	if ( ! is_array( $address ) ) {
		return;
	}
	$k = ( 'shipping' === $load_address ) ? 'shipping_state' : 'billing_state';
	if ( isset( $address[ $k ] ) && is_string( $address[ $k ] ) && 'CA' === strtoupper( trim( $address[ $k ] ) ) ) {
		update_user_meta( (int) $user_id, $k, 'SA' );
	}
}

/**
 * @return string
 */
function gtaw_wc_addr2_option_default() {
	return 'optional';
}

/**
 * @param string|false $value Option.
 * @return string
 */
function gtaw_wc_addr2_option_unhide( $value ) {
	$v = (string) $value;
	return in_array( $v, array( 'hidden', '' ), true ) ? 'optional' : $v;
}

/**
 * Register hooks once WooCommerce exists.
 */
function gtaw_wc_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	add_filter( 'woocommerce_countries_allowed_countries', 'gtaw_wc_countries_us_only', 100 );
	add_filter( 'woocommerce_countries_shipping_countries', 'gtaw_wc_countries_us_only', 100 );
	add_filter( 'woocommerce_default_address_fields', 'gtaw_wc_default_address_fields', 20 );
	add_filter( 'woocommerce_get_country_locale', 'gtaw_wc_country_locale', 20 );
	add_filter( 'woocommerce_states', 'gtaw_wc_us_states_san_andreas', 20 );
	add_filter( 'default_option_woocommerce_checkout_address_2_field', 'gtaw_wc_addr2_option_default' );
	add_filter( 'option_woocommerce_checkout_address_2_field', 'gtaw_wc_addr2_option_unhide' );
	add_filter( 'render_block', 'gtaw_wc_render_block_strip_contact', 10, 2 );
	add_filter( 'woocommerce_checkout_fields', 'gtaw_wc_checkout_fields_strip_email', 99 );
	add_filter( 'woocommerce_billing_fields', 'gtaw_wc_billing_fields_unset_postcode', 20, 1 );
	add_filter( 'woocommerce_shipping_fields', 'gtaw_wc_shipping_fields_unset_postcode', 20, 1 );
	foreach ( array( 'template_redirect', 'woocommerce_checkout_init' ) as $hook ) {
		add_action( $hook, 'gtaw_wc_ensure_session_customer_billing_email', 5 );
	}
	add_filter( 'woocommerce_checkout_get_value', 'gtaw_wc_checkout_get_value', 10, 2 );
	add_filter( 'woocommerce_order_get_billing_state', 'gtaw_wc_map_ca_to_sa', 10, 1 );
	add_filter( 'woocommerce_order_get_shipping_state', 'gtaw_wc_map_ca_to_sa', 10, 1 );
	add_filter( 'get_user_metadata', 'gtaw_wc_get_user_metadata_map_state', 10, 4 );
	add_action( 'woocommerce_checkout_create_order', 'gtaw_wc_on_checkout_create_order', 5, 1 );
	add_action( 'woocommerce_checkout_validate_order_before_payment', 'gtaw_wc_order_ensure_billing_email', 1, 1 );
	add_action( 'woocommerce_customer_save_address', 'gtaw_wc_save_address_map_state', 10, 3 );
}

add_action( 'plugins_loaded', 'gtaw_wc_bootstrap', 12 );
