<?php
/**
 * Fleeca Bank Merchant API — client, webhooks, and order completion.
 *
 * @package GTAW_Bridge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base URL for the REST API (path prefix /api).
 *
 * @return string
 */
function gtaw_fleeca_get_merchant_api_base() {
	$base = 'https://banking.gta.world/api';
	$base = apply_filters( 'gtaw_fleeca_merchant_api_base', $base );
	/** @deprecated 3.1.0 Use gtaw_fleeca_merchant_api_base */
	return apply_filters( 'gtaw_fleeca_v2_api_base', $base );
}

/**
 * Find a WooCommerce order by stored Fleeca payment_id.
 *
 * @param string $payment_id UUID from Fleeca.
 * @return WC_Order|null
 */
function gtaw_fleeca_get_order_by_payment_id( $payment_id ) {
	$payment_id = sanitize_text_field( $payment_id );
	if ( '' === $payment_id || ! gtaw_fleeca_is_payment_uuid( $payment_id ) ) {
		return null;
	}

	$orders = wc_get_orders(
		[
			'limit'        => 1,
			'meta_key'     => '_fleeca_payment_id',
			'meta_value'   => $payment_id,
			'meta_compare' => '=',
			'return'       => 'objects',
		]
	);

	if ( empty( $orders ) || ! is_a( $orders[0], 'WC_Order' ) ) {
		return null;
	}

	return $orders[0];
}

/**
 * Validate payment_id as UUID v4 (loose, matches Fleeca style).
 *
 * @param string $id Payment id.
 * @return bool
 */
function gtaw_fleeca_is_payment_uuid( $id ) {
	return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id );
}

/**
 * HTTP request to Fleeca merchant API.
 *
 * @param string $method GET or POST.
 * @param string $path   Path with leading slash, e.g. /v2/payment.
 * @param array  $args   Optional: 'body' => array for JSON body.
 * @return array|WP_Error { 'code' => int, 'body' => string|array, 'raw' => string } or error.
 */
function gtaw_fleeca_merchant_request( $method, $path, $args = [] ) {
	$api_key = gtaw_fleeca_get_setting( 'api_key', '' );
	if ( '' === $api_key ) {
		return new WP_Error( 'fleeca_no_api_key', __( 'Fleeca API key is not configured.', 'gtaw-bridge' ) );
	}

	$url  = rtrim( gtaw_fleeca_get_merchant_api_base(), '/' ) . $path;
	$body = isset( $args['body'] ) && is_array( $args['body'] ) ? wp_json_encode( $args['body'] ) : null;

	$request_args = [
		'method'      => $method,
		'timeout'     => 20,
		'redirection' => 3,
		'headers'     => [
			'Authorization' => 'Bearer ' . $api_key,
			'Accept'        => 'application/json',
			'User-Agent'    => 'GTAW-Bridge/' . ( defined( 'GTAW_BRIDGE_VERSION' ) ? GTAW_BRIDGE_VERSION : '1.0' ) . '; ' . get_bloginfo( 'url' ),
		],
	];

	if ( 'POST' === $method && null !== $body ) {
		$request_args['headers']['Content-Type'] = 'application/json';
		$request_args['body']                    = $body;
	}

	$response = wp_remote_request( $url, $request_args );
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$raw  = wp_remote_retrieve_body( $response );
	$json = json_decode( $raw, true );

	return [
		'code' => $code,
		'body' => ( JSON_ERROR_NONE === json_last_error() && is_array( $json ) ) ? $json : $raw,
		'raw'  => $raw,
	];
}

/**
 * Create a hosted payment (POST /v2/payment).
 *
 * @param int    $amount   Integer amount in dollars.
 * @param int    $mode     0 = sandbox, 1 = live.
 * @param string $description Order description (max 255).
 * @return array|WP_Error { payment_id, payment_link, message } on success.
 */
function gtaw_fleeca_create_hosted_payment( $amount, $mode, $description = '' ) {
	$amount      = max( 1, absint( $amount ) );
	$mode        = 1 === (int) $mode ? 1 : 0;
	$description = is_string( $description ) ? mb_substr( $description, 0, 255 ) : '';

	$result = gtaw_fleeca_merchant_request(
		'POST',
		'/v2/payment',
		[
			'body' => [
				'amount'        => $amount,
				'mode'          => $mode,
				'description'   => $description,
			],
		]
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$code = $result['code'];
	$data = is_array( $result['body'] ) ? $result['body'] : [];

	if ( 201 === $code && ! empty( $data['success'] ) && ! empty( $data['payment_id'] ) && ! empty( $data['payment_link'] ) ) {
		return [
			'payment_id'   => $data['payment_id'],
			'payment_link' => $data['payment_link'],
			'message'      => isset( $data['message'] ) ? (string) $data['message'] : '',
		];
	}

	if ( 401 === $code ) {
		return new WP_Error( 'fleeca_unauthorized', __( 'Fleeca API rejected the API key. Check Merchant Center credentials.', 'gtaw-bridge' ) );
	}
	if ( 429 === $code ) {
		return new WP_Error( 'fleeca_rate_limited', __( 'Fleeca API rate limit reached. Try again in a minute.', 'gtaw-bridge' ) );
	}

	$err = is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : __( 'Failed to create Fleeca payment.', 'gtaw-bridge' );
	return new WP_Error( 'fleeca_create_failed', $err, [ 'code' => $code, 'body' => $data ] );
}

/**
 * GET /v2/payments/{payment_id}
 *
 * @param string $payment_id UUID.
 * @return array|WP_Error Decoded 'data' row or error.
 */
function gtaw_fleeca_get_hosted_payment( $payment_id ) {
	if ( ! gtaw_fleeca_is_payment_uuid( $payment_id ) ) {
		return new WP_Error( 'fleeca_invalid_id', __( 'Invalid payment id.', 'gtaw-bridge' ) );
	}

	$path   = '/v2/payments/' . rawurlencode( $payment_id );
	$result = gtaw_fleeca_merchant_request( 'GET', $path, [] );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$code = $result['code'];
	$data = is_array( $result['body'] ) ? $result['body'] : [];
	if ( 200 === $code && ! empty( $data['success'] ) && ! empty( $data['data'] ) && is_array( $data['data'] ) ) {
		return $data['data'];
	}

	if ( 404 === $code ) {
		return new WP_Error( 'fleeca_not_found', __( 'Payment not found in Fleeca.', 'gtaw-bridge' ) );
	}
	if ( 429 === $code ) {
		return new WP_Error( 'fleeca_rate_limited', __( 'Fleeca API rate limit reached. Try again in a minute.', 'gtaw-bridge' ) );
	}

	return new WP_Error( 'fleeca_get_failed', __( 'Could not load payment details from Fleeca.', 'gtaw-bridge' ) );
}

/**
 * Verify X-Fleeca-Signature (HMAC-SHA256 of raw body with API key).
 *
 * @param string $raw_body         Raw request body.
 * @param string $header_signature Value of X-Fleeca-Signature (optional sha256= prefix).
 * @return bool
 */
function gtaw_fleeca_verify_webhook_signature( $raw_body, $header_signature ) {
	$api_key = gtaw_fleeca_get_setting( 'api_key', '' );
	if ( '' === $api_key || '' === $raw_body || '' === $header_signature ) {
		return false;
	}

	$expected         = 'sha256=' . hash_hmac( 'sha256', $raw_body, $api_key );
	$header_signature = trim( $header_signature );
	return hash_equals( $expected, $header_signature );
}

/**
 * Server webhook logic (used by /fleeca/callback and the REST route).
 *
 * @param string $raw_body         Raw JSON body.
 * @param string $signature_header X-Fleeca-Signature value.
 * @return array{ code: int, body: array } HTTP status and JSON body.
 */
function gtaw_fleeca_process_webhook_payload( $raw_body, $signature_header ) {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return [ 'code' => 400, 'body' => [ 'ok' => false, 'message' => 'WooCommerce unavailable' ] ];
	}
	if ( ! function_exists( 'gtaw_fleeca_get_setting' ) || ! gtaw_fleeca_get_setting( 'enabled' ) ) {
		return [ 'code' => 503, 'body' => [ 'ok' => false, 'message' => 'Fleeca module disabled' ] ];
	}
	if ( ! is_string( $raw_body ) || $raw_body === '' ) {
		return [ 'code' => 400, 'body' => [ 'ok' => false, 'message' => 'Empty body' ] ];
	}
	if ( ! function_exists( 'gtaw_fleeca_verify_webhook_signature' ) || ! gtaw_fleeca_verify_webhook_signature( $raw_body, (string) $signature_header ) ) {
		return [ 'code' => 403, 'body' => [ 'ok' => false, 'message' => 'Invalid signature' ] ];
	}
	$data = json_decode( $raw_body, true );
	if ( ! is_array( $data ) || empty( $data['payment_id'] ) ) {
		return [ 'code' => 400, 'body' => [ 'ok' => false, 'message' => 'Invalid JSON' ] ];
	}
	$row   = gtaw_fleeca_normalize_payment_row( $data );
	$order = gtaw_fleeca_get_order_by_payment_id( $row['payment_id'] );
	if ( ! $order ) {
		gtaw_add_log( 'fleeca', 'Webhook', 'No order for payment_id ' . $row['payment_id'], 'error' );
		return [ 'code' => 200, 'body' => [ 'ok' => true, 'message' => 'order not found' ] ];
	}
	if ( 'payment_successful' === $row['status'] ) {
		$r = gtaw_fleeca_complete_order_from_row( $order, $row, 'webhook' );
		if ( is_wp_error( $r ) ) {
			gtaw_add_log( 'fleeca', 'Webhook', $r->get_error_message() . ' (order ' . $order->get_id() . ')', 'error' );
		}
	} else {
		gtaw_fleeca_handle_non_success_status( $order, $row );
	}
	return [ 'code' => 200, 'body' => [ 'ok' => true ] ];
}

/**
 * Map API or webhook row to a normalized structure for order completion.
 *
 * @param array $row data[] from API or raw webhook.
 * @return array
 */
function gtaw_fleeca_normalize_payment_row( $row ) {
	if ( ! is_array( $row ) ) {
		return [];
	}
	$out = [
		'payment_id'    => isset( $row['payment_id'] ) ? sanitize_text_field( $row['payment_id'] ) : '',
		'amount'        => isset( $row['amount'] ) ? absint( $row['amount'] ) : 0,
		'status'        => isset( $row['status'] ) ? sanitize_text_field( $row['status'] ) : '',
		'mode'          => isset( $row['mode'] ) ? (string) $row['mode'] : '',
		'payer_routing' => isset( $row['payer_routing'] ) ? sanitize_text_field( $row['payer_routing'] ) : '',
		'description'   => isset( $row['description'] ) ? sanitize_text_field( $row['description'] ) : '',
		'payment_url'   => isset( $row['payment_url'] ) ? esc_url_raw( $row['payment_url'] ) : '',
	];
	$mode_str          = isset( $row['mode'] ) ? strtolower( (string) $row['mode'] ) : '';
	$out['is_sandbox'] = ( 'sandbox' === $mode_str || '0' === $mode_str );
	return $out;
}

/**
 * Whether this order is already marked paid by Fleeca (idempotency).
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function gtaw_fleeca_order_payment_already_recorded( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}
	return 'yes' === $order->get_meta( '_fleeca_payment_completed' ) || 'yes' === $order->get_meta( '_fleeca_v2_completed' );
}

/**
 * Idempotent: complete WooCommerce order from merchant API data if appropriate.
 *
 * @param WC_Order $order  Order.
 * @param array    $row    Normalized row.
 * @param string   $source webhook|return|reconcile.
 * @return true|WP_Error
 */
function gtaw_fleeca_complete_order_from_row( $order, $row, $source = 'webhook' ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return new WP_Error( 'fleeca_bad_order', __( 'Invalid order.', 'gtaw-bridge' ) );
	}

	$status = $row['status'] ?? '';
	if ( 'payment_successful' !== $status ) {
		return new WP_Error( 'fleeca_not_paid', __( 'Payment is not successful yet.', 'gtaw-bridge' ) );
	}

	if ( 'fleeca' !== $order->get_payment_method() ) {
		return new WP_Error( 'fleeca_wrong_gateway', __( 'Order does not use Fleeca.', 'gtaw-bridge' ) );
	}

	$oid = (int) $order->get_id();
	if ( gtaw_fleeca_order_payment_already_recorded( $order ) ) {
		return true;
	}
	if ( in_array( $order->get_status(), [ 'processing', 'completed' ], true ) ) {
		$order->update_meta_data( '_fleeca_payment_completed', 'yes' );
		$order->save();
		return true;
	}

	if ( ! in_array( $order->get_status(), [ 'pending', 'on-hold' ], true ) ) {
		return new WP_Error( 'fleeca_order_state', __( 'Order cannot be paid in its current state.', 'gtaw-bridge' ) );
	}

	$order_total   = (int) $order->get_total();
	$expected_meta = (string) $order->get_meta( '_fleeca_payment_id' );
	$remote_id     = (string) ( $row['payment_id'] ?? '' );
	if ( $expected_meta !== '' && $remote_id !== '' && $expected_meta !== $remote_id ) {
		return new WP_Error( 'fleeca_id_mismatch', __( 'Payment id does not match this order.', 'gtaw-bridge' ) );
	}
	if ( $row['amount'] > 0 && (int) $row['amount'] !== $order_total ) {
		$order->add_order_note(
			sprintf(
				/* translators: 1: order total, 2: remote amount */
				__( 'Fleeca amount mismatch (order: %1$s, remote: %2$s). Placed on hold for review.', 'gtaw-bridge' ),
				$order_total,
				(int) $row['amount']
			)
		);
		$order->update_status( 'on-hold' );
		return new WP_Error( 'fleeca_amount', __( 'Amount mismatch — order on hold.', 'gtaw-bridge' ) );
	}

	$transaction_id = 'fleeca_' . sanitize_key( $remote_id );
	$order->set_transaction_id( $transaction_id );
	$order->update_meta_data( '_fleeca_payer_routing', $row['payer_routing'] );
	$order->update_meta_data( '_fleeca_mode', $row['mode'] );
	$order->update_meta_data( '_fleeca_sandbox', $row['is_sandbox'] ? 'yes' : 'no' );
	$order->update_meta_data( '_fleeca_completion_source', $source );
	$order->update_meta_data( '_fleeca_payment_completed', 'yes' );
	$order->update_meta_data( '_fleeca_v2_completed', 'yes' );
	$order->payment_complete( $transaction_id );
	if ( $row['is_sandbox'] ) {
		$order->add_order_note( __( 'Fleeca sandbox / test payment.', 'gtaw-bridge' ), false );
	} else {
		$order->add_order_note(
			sprintf(
				/* translators: %s: completion context e.g. webhook, return, reconcile */
				__( 'Fleeca payment confirmed (%s).', 'gtaw-bridge' ),
				$source
			),
			false
		);
	}
	$order->save();
	gtaw_add_log( 'fleeca', 'Payment', 'Order #' . $oid . ' completed via Fleeca (' . $source . ').', 'success' );
	/**
	 * Fires when a hosted Fleeca payment completes.
	 *
	 * @param WC_Order $order  Order.
	 * @param array    $row    Normalized data.
	 * @param string   $source Context.
	 */
	do_action( 'gtaw_fleeca_payment_complete', $order, $row, $source );
	return true;
}

/**
 * Non-success webhook statuses: update order state.
 *
 * @param WC_Order $order Order.
 * @param array    $row   Normalized row.
 */
function gtaw_fleeca_handle_non_success_status( $order, $row ) {
	$status = $row['status'] ?? '';
	if ( 'payment_failed' === $status ) {
		$order->update_status( 'failed', __( 'Fleeca reported payment failed.', 'gtaw-bridge' ) );
	}
	if ( 'pending' === $status ) {
		$order->add_order_note( __( 'Fleeca reported payment as pending (webhook).', 'gtaw-bridge' ) );
	}
}

/**
 * Public Redirect URL (browser redirect after hosted checkout).
 *
 * @return string
 */
function gtaw_fleeca_get_return_url() {
	$default = home_url( '/fleeca/return/' );
	$default = apply_filters( 'gtaw_fleeca_v2_return_url', $default );
	return apply_filters( 'gtaw_fleeca_return_url', $default );
}

/**
 * Server webhook URL (Merchant Center). Default is a short path; legacy REST still works.
 *
 * @return string
 */
function gtaw_fleeca_get_webhook_url() {
	$default = home_url( '/fleeca/callback' );
	$default = apply_filters( 'gtaw_fleeca_v2_webhook_url', $default );
	return apply_filters( 'gtaw_fleeca_webhook_url', $default );
}

/**
 * Reconcile a single order by fetching API and completing if successful (admin tools / Redirect URL).
 *
 * @param int  $order_id            Order ID.
 * @param bool $redirect_on_success If true, wp_redirect to thank you.
 * @return bool|WP_Error
 */
function gtaw_fleeca_reconcile_order( $order_id, $redirect_on_success = false ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || 'fleeca' !== $order->get_payment_method() ) {
		return new WP_Error( 'fleeca_order', __( 'Invalid Fleeca order.', 'gtaw-bridge' ) );
	}
	$pid = (string) $order->get_meta( '_fleeca_payment_id' );
	if ( ! $pid ) {
		return new WP_Error( 'fleeca_no_id', __( 'No Fleeca payment id on this order.', 'gtaw-bridge' ) );
	}
	$remote = gtaw_fleeca_get_hosted_payment( $pid );
	if ( is_wp_error( $remote ) ) {
		return $remote;
	}
	$row    = gtaw_fleeca_normalize_payment_row( $remote );
	$result = gtaw_fleeca_complete_order_from_row( $order, $row, 'reconcile' );
	if ( is_wp_error( $result ) && 'fleeca_not_paid' !== $result->get_error_code() ) {
		return $result;
	}
	if ( true === $result && $redirect_on_success ) {
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}
	return $result;
}
