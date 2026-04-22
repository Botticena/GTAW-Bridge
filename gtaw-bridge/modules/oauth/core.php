<?php
defined('ABSPATH') or exit;

// UCP OAuth — token exchange, /api/user, small helpers.

define('GTAW_OAUTH_TOKEN_ENDPOINT', 'https://ucp.gta.world/oauth/token');
define('GTAW_OAUTH_USER_ENDPOINT', 'https://ucp.gta.world/api/user');
define('GTAW_OAUTH_AUTH_ENDPOINT', 'https://ucp.gta.world/oauth/authorize');

define('GTAW_OAUTH_TOKEN_CACHE_DURATION', 60);
define('GTAW_OAUTH_USER_CACHE_DURATION', 300);

/**
 * @param string $code Authorization code from redirect.
 * @return array|WP_Error
 */
function gtaw_oauth_exchange_token($code) {
    gtaw_perf_start('oauth_token_exchange');

    $settings = get_option('gtaw_oauth_settings');
    // @deprecated 2.0 old options still read for upgrades
    $client_id = isset($settings['client_id']) ? $settings['client_id'] : get_option('gtaw_client_id');
    $client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : get_option('gtaw_client_secret');
    $callback_url = isset($settings['callback_url']) ? $settings['callback_url'] : get_option('gtaw_callback_url', site_url('?gta_oauth=callback'));

    if (empty($client_id) || empty($client_secret)) {
        gtaw_add_log('oauth', 'Error', 'OAuth Client ID and Client Secret are required', 'error');
        return new WP_Error('missing_credentials', 'OAuth Client ID and Client Secret are required');
    }

    $body = [
        'grant_type'    => 'authorization_code',
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri'  => $callback_url,
        'code'          => $code,
    ];

    $response = gtaw_oauth_api_request(GTAW_OAUTH_TOKEN_ENDPOINT, [
        'method' => 'POST',
        'body' => $body,
        'timeout' => 15,
    ]);

    $elapsed = gtaw_perf_end('oauth_token_exchange');

    if (defined('WP_DEBUG') && WP_DEBUG) {
        gtaw_add_log('oauth', 'Performance', "Token exchange took {$elapsed}s", 'success');
    }

    return $response;
}

/**
 * @param string $access_token Bearer token.
 * @return array|WP_Error
 */
function gtaw_oauth_get_user_data($access_token) {
    if (empty($access_token)) {
        return new WP_Error('missing_token', 'Access token is required');
    }

    $cache_key = 'gtaw_oauth_user_' . md5($access_token);
    $cached_data = get_transient($cache_key);
    if ($cached_data !== false) {
        return $cached_data;
    }

    gtaw_perf_start('oauth_user_data');

    $response = gtaw_oauth_api_request(GTAW_OAUTH_USER_ENDPOINT, [
        'method' => 'GET',
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
        ]
    ]);

    if (!is_wp_error($response)) {
        set_transient($cache_key, $response, GTAW_OAUTH_USER_CACHE_DURATION);
    }

    $elapsed = gtaw_perf_end('oauth_user_data');

    if (defined('WP_DEBUG') && WP_DEBUG) {
        gtaw_add_log('oauth', 'Performance', "User data fetch took {$elapsed}s", 'success');
    }

    return $response;
}

/**
 * JSON API helper; retries once on transport failure (kinda flaky on some hosts).
 *
 * @param string $url
 * @param array  $args
 * @param int    $retry_count
 * @return array|WP_Error
 */
function gtaw_oauth_api_request($url, $args = [], $retry_count = 1) {
    $args['method'] = isset($args['method']) ? $args['method'] : 'GET';

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        gtaw_add_log('oauth', 'Error', "API request failed: {$error_message}", 'error');

        if ($retry_count > 0) {
            usleep(500000);
            return gtaw_oauth_api_request($url, $args, $retry_count - 1);
        }

        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code < 200 || $response_code >= 300) {
        $error_message = wp_remote_retrieve_response_message($response);
        $error_body = json_decode($response_body, true);

        if (isset($error_body['message'])) {
            $error_message = $error_body['message'];
        }

        gtaw_add_log('oauth', 'Error', "API Error ({$response_code}): {$error_message}", 'error');

        return new WP_Error(
            'gtaw_api_error',
            sprintf('GTA:W API Error (%d): %s', $response_code, $error_message),
            [
                'status' => $response_code,
                'response' => $error_body
            ]
        );
    }

    $response_data = json_decode($response_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        gtaw_add_log('oauth', 'Error', "Failed to parse API response: " . json_last_error_msg(), 'error');
        return new WP_Error('json_parse_error', 'Failed to parse API response');
    }

    return $response_data;
}

/** @return string Authorize URL (legacy helper; prefer gtaw_oauth_get_authorize_url). */
function gtaw_get_oauth_url() {
    $settings = get_option('gtaw_oauth_settings');
    // @deprecated 2.0
    $client_id = isset($settings['client_id']) ? $settings['client_id'] : get_option('gtaw_client_id', '');
    $callback_url = isset($settings['callback_url']) ? $settings['callback_url'] : get_option('gtaw_callback_url', site_url('?gta_oauth=callback'));
    
    if (empty($client_id)) {
        return '';
    }
    
    return add_query_arg([
        'client_id'     => $client_id,
        'redirect_uri'  => urlencode($callback_url),
        'response_type' => 'code',
        'scope'         => ''
    ], GTAW_OAUTH_AUTH_ENDPOINT);
}

/**
 * GTA:W RP name rules (length, letters, capital first letter).
 *
 * @param string $firstname
 * @param string $lastname
 * @return bool
 */
function gtaw_is_valid_character_name($firstname, $lastname) {
    if (empty($firstname) || empty($lastname)) {
        return false;
    }

    if (strlen($firstname) < 2 || strlen($firstname) > 16 ||
        strlen($lastname) < 2 || strlen($lastname) > 16) {
        return false;
    }

    if (!preg_match('/^[A-Za-z]+$/', $firstname) || !preg_match('/^[A-Za-z]+$/', $lastname)) {
        return false;
    }

    if ($firstname[0] !== strtoupper($firstname[0]) || $lastname[0] !== strtoupper($lastname[0])) {
        return false;
    }

    return true;
}

/**
 * Random WP password; skips easy-to-mix chars (0/O, 1/l, etc.).
 *
 * @param int $length
 * @return string
 */
function gtaw_generate_secure_password($length = 16) {
    $chars = [
        'abcdefghjkmnpqrstuvwxyz',
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        '23456789',
        '!@#$%^&*()-_=+[]{};:,.?'
    ];

    $password = '';
    foreach ($chars as $char_set) {
        $password .= $char_set[random_int(0, strlen($char_set) - 1)];
    }

    $all_chars = implode('', $chars);
    for ($i = 4; $i < $length; $i++) {
        $password .= $all_chars[random_int(0, strlen($all_chars) - 1)];
    }

    return str_shuffle($password);
}

/** @param string $code */
function gtaw_is_valid_auth_code($code) {
    if (empty($code) || !is_string($code)) {
        return false;
    }

    return true;
}

/**
 * @param int|null $user_id Default: current user.
 */
function gtaw_is_oauth_user($user_id = null) {
    if ($user_id === null) {
        if (!is_user_logged_in()) {
            return false;
        }
        $user_id = get_current_user_id();
    }

    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    return !empty($gtaw_user_id);
}

function gtaw_oauth_trigger_process_started($user_data) {
    do_action('gtaw_oauth_process_started', $user_data);
}

function gtaw_oauth_trigger_character_registered($user_id, $character_data) {
    do_action('gtaw_oauth_character_registered', $user_id, $character_data);
}

function gtaw_oauth_trigger_character_switched($user_id, $new_character, $old_character = null) {
    do_action('gtaw_oauth_character_switched', $user_id, $new_character, $old_character);
}

function gtaw_oauth_log_error($error_code, $error_message, $context = []) {
    gtaw_add_log('oauth', 'Error', "{$error_code}: {$error_message}", 'error');

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("GTAW OAuth Error - {$error_code}: {$error_message}");

        if (!empty($context)) {
            error_log("Error Context: " . wp_json_encode($context));
        }
    }
}

function gtaw_start_secure_session() {
    if (!headers_sent() && session_status() == PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => is_ssl()
        ]);
    }
}

function gtaw_store_auth_data($data) {
    gtaw_start_secure_session();
    $_SESSION['gtaw_auth_data'] = $data;
}

/** @return array|null */
function gtaw_get_auth_data() {
    gtaw_start_secure_session();
    return isset($_SESSION['gtaw_auth_data']) ? $_SESSION['gtaw_auth_data'] : null;
}

function gtaw_clear_auth_data() {
    gtaw_start_secure_session();
    if (isset($_SESSION['gtaw_auth_data'])) {
        unset($_SESSION['gtaw_auth_data']);
    }
}

/**
 * Build GTA:W UCP OAuth authorize URL.
 * redirect_uri is encoded once by add_query_arg (do not pre-urlencode).
 *
 * @param string|null $callback_url Registered redirect URI; uses settings if null/empty.
 * @return string Empty string if client_id is not configured.
 */
function gtaw_oauth_get_authorize_url( $callback_url = null ) {
    if ( ! function_exists( 'gtaw_oauth_get_setting' ) ) {
        return '';
    }
    $client_id = (string) gtaw_oauth_get_setting( 'client_id', '' );
    if ( '' === $client_id ) {
        $client_id = (string) get_option( 'gtaw_client_id', '' );
    }
    if ( '' === $client_id ) {
        return '';
    }
    if ( null === $callback_url || '' === (string) $callback_url ) {
        $callback_url = (string) gtaw_oauth_get_setting( 'callback_url', '' );
        if ( '' === $callback_url ) {
            $callback_url = (string) get_option( 'gtaw_callback_url', '' );
        }
        if ( '' === $callback_url ) {
            $callback_url = site_url( '?gta_oauth=callback' );
        }
    }
    return add_query_arg(
        [
            'client_id'     => $client_id,
            'redirect_uri'  => $callback_url,
            'response_type' => 'code',
            'scope'         => '',
        ],
        'https://ucp.gta.world/oauth/authorize'
    );
}