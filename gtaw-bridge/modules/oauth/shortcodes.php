<?php
defined('ABSPATH') or exit;

/* ========= OAUTH SHORTCODES MODULE ========= */
/*
 * This module provides shortcodes for embedding OAuth functionality:
 * - Login button
 * - User status display
 * - Character information
 */

/**
 * Shortcode for generating the GTA:W login link
 *
 * @return string HTML for the login link
 */
function gtaw_login_shortcode() {
    $client_id = get_option('gtaw_client_id');
    if (empty($client_id)) {
        return '<p>Please set your GTA:W Client ID in the OAuth settings.</p>';
    }
    
    $callback = get_option('gtaw_callback_url');
    if (empty($callback)) {
        $callback = add_query_arg(['gta_oauth' => 'callback'], site_url());
    }
    
    $auth_url = add_query_arg([
        'client_id'     => $client_id,
        'redirect_uri'  => urlencode($callback),
        'response_type' => 'code',
        'scope'         => ''
    ], 'https://ucp.gta.world/oauth/authorize');
    
    return '<a href="' . esc_url($auth_url) . '" class="gtaw-login-button">Login / Create Account via GTA:W</a>';
}
add_shortcode('gtaw_login', 'gtaw_login_shortcode');

/**
 * Shortcode for a styled GTA:W login button
 *
 * @param array $atts Shortcode attributes
 * @return string HTML for the styled login button
 */
function gtaw_login_button_shortcode($atts) {
    $atts = shortcode_atts([
        'text' => 'Login with GTA:W',
        'class' => 'gtaw-styled-button',
        'redirect' => '',
    ], $atts);
    
    $client_id = get_option('gtaw_client_id');
    if (empty($client_id)) {
        return '<p>Please set your GTA:W Client ID in the OAuth settings.</p>';
    }
    
    $callback = get_option('gtaw_callback_url');
    if (empty($callback)) {
        $callback = add_query_arg(['gta_oauth' => 'callback'], site_url());
    }
    
    // If a custom redirect is specified, store it in a transient
    if (!empty($atts['redirect'])) {
        $redirect_key = 'gtaw_redirect_' . md5(time() . rand());
        set_transient($redirect_key, $atts['redirect'], HOUR_IN_SECONDS);
        $callback = add_query_arg(['gtaw_redirect_key' => $redirect_key], $callback);
    }
    
    $auth_url = add_query_arg([
        'client_id'     => $client_id,
        'redirect_uri'  => urlencode($callback),
        'response_type' => 'code',
        'scope'         => ''
    ], 'https://ucp.gta.world/oauth/authorize');
    
    $button_style = '
    .gtaw-styled-button {
        display: inline-block;
        background-color: #4CAF50;
        color: white;
        padding: 10px 20px;
        text-align: center;
        text-decoration: none;
        font-size: 16px;
        margin: 4px 2px;
        cursor: pointer;
        border-radius: 4px;
        border: none;
        transition: background-color 0.3s;
    }
    .gtaw-styled-button:hover {
        background-color: #45a049;
        color: white;
    }';
    
    wp_add_inline_style('gtaw-style', $button_style);
    
    return '<a href="' . esc_url($auth_url) . '" class="' . esc_attr($atts['class']) . '">' . esc_html($atts['text']) . '</a>';
}
add_shortcode('gtaw_login_button', 'gtaw_login_button_shortcode');

/**
 * Shortcode to display the currently logged-in user's GTA:W character information
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function gtaw_user_info_shortcode($atts) {
    $atts = shortcode_atts([
        'show_id' => 'yes',
    ], $atts);
    
    if (!is_user_logged_in()) {
        return '<p>Please log in to view your GTA:W character information.</p>';
    }
    
    $user_id = get_current_user_id();
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    if (empty($character)) {
        return '<p>Your account is not linked to a GTA:W character.</p>';
    }
    
    $output = '<div class="gtaw-user-info">';
    $output .= '<p>You are logged in as <strong>' . esc_html($character['firstname'] . ' ' . $character['lastname']) . '</strong>';
    
    if ($atts['show_id'] === 'yes') {
        $output .= ' (Character ID: ' . esc_html($character['id']) . ')';
    }
    
    $output .= '</p>';
    $output .= '</div>';
    
    return $output;
}
add_shortcode('gtaw_user_info', 'gtaw_user_info_shortcode');

/**
 * Shortcode to conditionally display content based on GTA:W login status
 *
 * @param array $atts Shortcode attributes
 * @param string $content The content to conditionally display
 * @return string HTML output
 */
function gtaw_if_logged_in_shortcode($atts, $content = null) {
    if (!is_user_logged_in()) {
        return '';
    }
    
    $user_id = get_current_user_id();
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    
    if (empty($gtaw_user_id)) {
        return '';
    }
    
    return do_shortcode($content);
}
add_shortcode('gtaw_if_logged_in', 'gtaw_if_logged_in_shortcode');

/**
 * Shortcode to conditionally display content for users not logged in with GTA:W
 *
 * @param array $atts Shortcode attributes
 * @param string $content The content to conditionally display
 * @return string HTML output
 */
function gtaw_if_not_logged_in_shortcode($atts, $content = null) {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
        
        if (!empty($gtaw_user_id)) {
            return '';
        }
    }
    
    return do_shortcode($content);
}
add_shortcode('gtaw_if_not_logged_in', 'gtaw_if_not_logged_in_shortcode');

/**
 * Handle custom redirects from the login process
 */
function gtaw_handle_custom_redirect() {
    if (isset($_GET['gtaw_redirect_key'])) {
        $redirect_key = sanitize_text_field($_GET['gtaw_redirect_key']);
        $redirect_url = get_transient($redirect_key);
        
        if (!empty($redirect_url)) {
            delete_transient($redirect_key);
            wp_redirect($redirect_url);
            exit;
        }
    }
}
add_action('template_redirect', 'gtaw_handle_custom_redirect');