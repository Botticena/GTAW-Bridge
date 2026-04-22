<?php
/**
 * Fleeca — Redirect URL, webhooks, rewrites, and admin order tools.
 *
 * @package GTAW_Bridge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register rewrite rules for browser return and server webhook.
 */
function gtaw_fleeca_add_callback_endpoint() {
	static $registered = false;
	if ( $registered ) {
		return;
	}

	gtaw_perf_start( 'fleeca_register_endpoint' );

	if ( '1' !== get_option( 'gtaw_fleeca_rewrite_fleeca_short_slugs' ) ) {
		update_option( 'gtaw_fleeca_flush_rules', 'yes' );
		update_option( 'gtaw_fleeca_rewrite_fleeca_short_slugs', '1' );
	}

	add_rewrite_rule( '^fleeca/return/?$', 'index.php?gtaw_fleeca_return=1', 'top' );
	add_rewrite_rule( '^fleeca/callback/?$', 'index.php?gtaw_fleeca_webhook=1', 'top' );
	add_rewrite_rule( '^gtaw-fleeca/payment/complete/?$', 'index.php?gtaw_fleeca_return=1', 'top' );
	add_rewrite_rule( '^fleeca-return/?$', 'index.php?gtaw_fleeca_return=1', 'top' );

	$flush_rules = get_option( 'gtaw_fleeca_flush_rules', 'yes' ) === 'yes';
	if ( $flush_rules ) {
		flush_rewrite_rules();
		update_option( 'gtaw_fleeca_flush_rules', 'no' );
		if ( gtaw_fleeca_get_setting( 'debug_mode', false ) ) {
			gtaw_add_log( 'fleeca', 'Rewrite', 'Rewrite rules flushed', 'success' );
		}
	}

	$registered = true;
	gtaw_perf_end( 'fleeca_register_endpoint' );
}
add_action( 'init', 'gtaw_fleeca_add_callback_endpoint', 10 );

/**
 * @param array $vars Query vars.
 * @return array
 */
function gtaw_fleeca_query_vars( $vars ) {
	$vars[] = 'gtaw_fleeca_return';
	$vars[] = 'gtaw_fleeca_webhook';
	return $vars;
}
add_filter( 'query_vars', 'gtaw_fleeca_query_vars' );

/**
 * REST: server-to-server webhook (HMAC-signed JSON).
 */
function gtaw_fleeca_register_rest_webhook() {
	register_rest_route(
		'gtaw-bridge/v1',
		'/fleeca/webhook',
		[
			'methods'             => 'POST',
			'callback'            => 'gtaw_fleeca_rest_webhook_handler',
			'permission_callback' => '__return_true',
		]
	);
}
add_action( 'rest_api_init', 'gtaw_fleeca_register_rest_webhook' );

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function gtaw_fleeca_rest_webhook_handler( $request ) {
	$raw = $request->get_body();
	if ( ! is_string( $raw ) ) {
		$raw = '';
	}
	$sig = $request->get_header( 'X-Fleeca-Signature' );
	if ( ! is_string( $sig ) || $sig === '' ) {
		$sig = isset( $_SERVER['HTTP_X_FLEECA_SIGNATURE'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_FLEECA_SIGNATURE'] ) : '';
	}
	$out = gtaw_fleeca_process_webhook_payload( $raw, $sig );
	return new WP_REST_Response( $out['body'], $out['code'] );
}

/**
 * Pretty URL POST /fleeca/callback (same as wp-json/.../fleeca/webhook).
 */
function gtaw_fleeca_pretty_webhook_handler() {
	if ( is_admin() ) {
		return;
	}
	$q   = (int) get_query_var( 'gtaw_fleeca_webhook' );
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = (string) ( wp_parse_url( $uri, PHP_URL_PATH ) ?? '' );
	if ( 1 !== $q && ( '' === $path || false === strpos( $path, '/fleeca/callback' ) ) ) {
		return;
	}
	$method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
	if ( 'POST' !== $method ) {
		status_header( 405 );
		header( 'Allow: POST' );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( [ 'ok' => false, 'message' => 'Method not allowed' ] );
		exit;
	}
	$raw = file_get_contents( 'php://input' );
	if ( ! is_string( $raw ) ) {
		$raw = '';
	}
	$sig = isset( $_SERVER['HTTP_X_FLEECA_SIGNATURE'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_FLEECA_SIGNATURE'] ) : '';
	$out = gtaw_fleeca_process_webhook_payload( $raw, $sig );
	status_header( $out['code'] );
	header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
	echo wp_json_encode( $out['body'] );
	exit;
}
add_action( 'template_redirect', 'gtaw_fleeca_pretty_webhook_handler', 1 );

/**
 * After hosted checkout Fleeca appends ?payment_id= to the Redirect URL.
 */
function gtaw_fleeca_browser_return_handler() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$is_return = 1 === (int) get_query_var( 'gtaw_fleeca_return' );
	if ( ! $is_return && ! empty( $_SERVER['REQUEST_URI'] ) ) {
		$uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
		$path = (string) ( wp_parse_url( $uri, PHP_URL_PATH ) ?? '' );
		$is_return = (
			false !== strpos( $path, '/fleeca/return' )
			|| false !== strpos( $uri, 'fleeca-return' )
			|| false !== strpos( $path, 'gtaw-fleeca/payment/complete' )
		);
	}
	if ( ! $is_return || empty( $_GET['payment_id'] ) ) {
		return;
	}
	$payment_id = sanitize_text_field( wp_unslash( $_GET['payment_id'] ) );
	if ( ! $payment_id || ! gtaw_fleeca_is_payment_uuid( $payment_id ) ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Invalid payment reference.', 'gtaw-bridge' ), 'error' );
		}
		wp_safe_redirect( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url() );
		exit;
	}
	if ( ! function_exists( 'gtaw_fleeca_check_rate_limit' ) || ! gtaw_fleeca_check_rate_limit( 'fleeca_return' ) ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Too many return attempts. Please wait and try again.', 'gtaw-bridge' ), 'error' );
		}
		wp_safe_redirect( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url() );
		exit;
	}
	$order = gtaw_fleeca_get_order_by_payment_id( $payment_id );
	if ( ! $order ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'We could not match this session to an order. If you were charged, contact the store.', 'gtaw-bridge' ), 'error' );
		}
		wp_safe_redirect( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url() );
		exit;
	}
	if ( is_user_logged_in() ) {
		$ouid = (int) $order->get_user_id();
		if ( $ouid && (int) get_current_user_id() !== $ouid ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'This order does not belong to your account.', 'gtaw-bridge' ), 'error' );
			}
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}
	$remote = gtaw_fleeca_get_hosted_payment( $payment_id );
	if ( is_wp_error( $remote ) ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $remote->get_error_message(), 'error' );
		}
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}
	$row = gtaw_fleeca_normalize_payment_row( $remote );
	if ( 'payment_successful' === $row['status'] ) {
		$r = gtaw_fleeca_complete_order_from_row( $order, $row, 'return' );
		if ( is_wp_error( $r ) && 'fleeca_not_paid' !== $r->get_error_code() && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $r->get_error_message(), 'notice' );
		} elseif ( true === $r && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Payment successful! Thank you for your order.', 'gtaw-bridge' ), 'success' );
		}
	} elseif ( 'payment_failed' === $row['status'] ) {
		gtaw_fleeca_handle_non_success_status( $order, $row );
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Payment was not completed.', 'gtaw-bridge' ), 'error' );
		}
	} else {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Payment is still processing. You will receive a confirmation by email when it completes.', 'gtaw-bridge' ), 'notice' );
		}
	}
	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'fleeca_current_order_id', null );
	}
	wp_safe_redirect( $order->get_checkout_order_received_url() );
	exit;
}
add_action( 'template_redirect', 'gtaw_fleeca_browser_return_handler', 3 );

/**
 * @param string $action Rate-limit bucket name.
 * @return bool
 */
function gtaw_fleeca_check_rate_limit( $action ) {
	$unique_id = '';
	if ( is_user_logged_in() ) {
		$unique_id = 'user_' . get_current_user_id();
	} else {
		$unique_id = 'ip_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '' );
	}

	$rate_key = "gtaw_fleeca_rate_{$action}_{$unique_id}";
	$count    = get_transient( $rate_key );

	if ( false === $count ) {
		set_transient( $rate_key, 1, 60 );
		return true;
	}

	$count++;
	set_transient( $rate_key, $count, 60 );

	$limit = 5;
	$limit = apply_filters( 'gtaw_fleeca_rate_limit', $limit, $action, $unique_id );

	return $count <= $limit;
}

/**
 * Checkout notice when customer cancels at Fleeca.
 */
function gtaw_fleeca_check_cancel_notice() {
	if ( isset( $_GET['fleeca_cancel'] ) ) {
		wc_add_notice( __( 'Your Fleeca Bank payment was canceled. Please try again or select a different payment method.', 'gtaw-bridge' ), 'error' );
	}
}
add_action( 'woocommerce_before_checkout_form', 'gtaw_fleeca_check_cancel_notice' );

/**
 * Flush rewrites when Fleeca options change.
 */
function gtaw_fleeca_settings_saved() {
	update_option( 'gtaw_fleeca_flush_rules', 'yes' );
	gtaw_add_log( 'fleeca', 'Settings', 'Fleeca settings updated, rewrite rules will be flushed', 'success' );
}
add_action( 'update_option_gtaw_fleeca_settings', 'gtaw_fleeca_settings_saved' );
add_action( 'update_option_gtaw_fleeca_enabled', 'gtaw_fleeca_settings_saved' );

/**
 * Activation: flush rules once.
 */
function gtaw_fleeca_activation() {
	update_option( 'gtaw_fleeca_flush_rules', 'yes' );
}
register_activation_hook( plugin_basename( GTAW_BRIDGE_PLUGIN_DIR . 'gtaw-bridge.php' ), 'gtaw_fleeca_activation' );

/**
 * Reconcile and API view actions for pending Fleeca orders.
 *
 * @param array    $actions Actions.
 * @param WC_Order $order   Order.
 * @return array
 */
function gtaw_fleeca_add_order_debug_actions( $actions, $order ) {
	if ( ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) || 'fleeca' !== $order->get_payment_method() ) {
		return $actions;
	}
	if ( ! in_array( $order->get_status(), [ 'pending', 'on-hold', 'failed' ], true ) ) {
		return $actions;
	}
	$pid = (string) $order->get_meta( '_fleeca_payment_id' );
	if ( $pid === '' ) {
		return $actions;
	}
	$nonce = wp_create_nonce( 'gtaw_fleeca_order' );
	$actions['gtaw_fleeca_reconcile'] = [
		'url'    => add_query_arg(
			[
				'action'   => 'gtaw_fleeca_reconcile',
				'order_id' => $order->get_id(),
				'nonce'    => $nonce,
			],
			admin_url( 'admin-ajax.php' )
		),
		'name'   => __( 'Reconcile Fleeca payment', 'gtaw-bridge' ),
		'action' => 'view',
	];
	$actions['gtaw_fleeca_view_api']  = [
		'url'    => add_query_arg(
			[
				'action'   => 'gtaw_fleeca_view_payment',
				'order_id' => $order->get_id(),
				'nonce'    => $nonce,
			],
			admin_url( 'admin-ajax.php' )
		),
		'name'   => __( 'View Fleeca API payment', 'gtaw-bridge' ),
		'action' => 'view',
	];
	return $actions;
}
add_filter( 'woocommerce_order_actions', 'gtaw_fleeca_add_order_debug_actions', 10, 2 );

/**
 * AJAX: fetch payment JSON from the API.
 */
function gtaw_fleeca_ajax_view_payment() {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( __( 'Permission denied', 'gtaw-bridge' ) ) );
	}
	if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'gtaw_fleeca_order' ) ) {
		wp_die( esc_html( __( 'Invalid nonce', 'gtaw-bridge' ) ) );
	}
	$order = isset( $_GET['order_id'] ) ? wc_get_order( absint( $_GET['order_id'] ) ) : null;
	if ( ! $order || 'fleeca' !== $order->get_payment_method() ) {
		wp_die( esc_html( __( 'Not a Fleeca order', 'gtaw-bridge' ) ) );
	}
	$pid = (string) $order->get_meta( '_fleeca_payment_id' );
	if ( ! $pid || ! function_exists( 'gtaw_fleeca_get_hosted_payment' ) ) {
		wp_die( esc_html( __( 'No payment_id on this order', 'gtaw-bridge' ) ) );
	}
	$r = gtaw_fleeca_get_hosted_payment( $pid );
	echo '<h1>' . esc_html__( 'Fleeca API — payment', 'gtaw-bridge' ) . '</h1><pre style="max-width:900px;white-space:pre-wrap;">';
	echo is_wp_error( $r ) ? esc_html( $r->get_error_message() ) : esc_html( print_r( $r, true ) );
	echo '</pre><p><a class="button" href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Back to order', 'gtaw-bridge' ) . '</a></p>';
	wp_die();
}
add_action( 'wp_ajax_gtaw_fleeca_view_payment', 'gtaw_fleeca_ajax_view_payment' );

/**
 * AJAX: reconcile from admin order screen.
 */
function gtaw_fleeca_ajax_reconcile() {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( __( 'Permission denied', 'gtaw-bridge' ) ) );
	}
	if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'gtaw_fleeca_order' ) ) {
		wp_die( esc_html( __( 'Invalid nonce', 'gtaw-bridge' ) ) );
	}
	$oid = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
	if ( $oid < 1 || ! function_exists( 'gtaw_fleeca_reconcile_order' ) ) {
		wp_die( esc_html( __( 'Invalid request', 'gtaw-bridge' ) ) );
	}
	$r = gtaw_fleeca_reconcile_order( $oid, false );
	if ( is_wp_error( $r ) ) {
		wp_die( esc_html( sprintf( /* translators: %s error */ __( 'Reconcile failed: %s', 'gtaw-bridge' ), $r->get_error_message() ) ) );
	}
	$order = wc_get_order( $oid );
	wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url( 'edit.php?post_type=shop_order' ) );
	exit;
}
add_action( 'wp_ajax_gtaw_fleeca_reconcile', 'gtaw_fleeca_ajax_reconcile' );

/**
 * Optional tool: ?fleeca_flush_rules=yes&_wpnonce=...
 */
function gtaw_register_fleeca_manual_flush() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_GET['fleeca_flush_rules'] ) && 'yes' === $_GET['fleeca_flush_rules']
		&& isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fleeca_flush_rules' ) ) {
		flush_rewrite_rules();
		gtaw_add_log( 'fleeca', 'Rewrite', 'Rewrite rules manually flushed', 'success' );
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Fleeca rewrite rules have been flushed.', 'gtaw-bridge' ) . '</p></div>';
			}
		);
	}
}
add_action( 'admin_init', 'gtaw_register_fleeca_manual_flush' );
