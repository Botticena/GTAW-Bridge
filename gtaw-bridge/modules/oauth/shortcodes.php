<?php
defined('ABSPATH') or exit;

/* ========= OAUTH SHORTCODES MODULE ========= */
/*
 * This module provides shortcodes for embedding OAuth functionality:
 * - Login button with enhanced styling options
 * - User status display with improved formatting
 * - Character information display
 * - Conditional content display based on login status
 */

/**
 * Shortcode for generating the GTA:W login link
 *
 * @return string HTML for the login link
 */
function gtaw_login_shortcode() {
    // Get settings for the OAuth URL
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
        'style' => 'default', // default, minimal, prominent
        'icon' => 'true', // true or false
        'width' => '' // empty for auto, or CSS value like '200px'
    ], $atts);
    
    // Get settings for the OAuth URL
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
    
    // Determine button style and class
    $style_class = $atts['class'];
    $custom_css = '';
    
    // Generate button style based on style parameter
    switch ($atts['style']) {
        case 'minimal':
            $style_class .= ' gtaw-minimal-button';
            $custom_css .= '
            .gtaw-minimal-button {
                display: inline-block;
                color: #4CAF50;
                background: transparent;
                padding: 8px 16px;
                text-align: center;
                text-decoration: none;
                font-size: 16px;
                margin: 4px 2px;
                cursor: pointer;
                border: 1px solid #4CAF50;
                border-radius: 4px;
                transition: all 0.3s;
            }
            .gtaw-minimal-button:hover {
                background-color: #4CAF50;
                color: white;
            }';
            break;
            
        case 'prominent':
            $style_class .= ' gtaw-prominent-button';
            $custom_css .= '
            .gtaw-prominent-button {
                display: inline-block;
                background-color: #2271b1;
                color: white;
                padding: 12px 24px;
                text-align: center;
                text-decoration: none;
                font-size: 18px;
                margin: 4px 2px;
                cursor: pointer;
                border-radius: 4px;
                border: none;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                transition: all 0.3s;
            }
            .gtaw-prominent-button:hover {
                background-color: #135e96;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                transform: translateY(-2px);
            }';
            break;
            
        default: // 'default' style
            $custom_css .= '
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
    }
    
    // Add custom width if specified
    if (!empty($atts['width'])) {
        $custom_css .= '
        .' . sanitize_html_class($style_class) . ' {
            width: ' . esc_attr($atts['width']) . ';
        }';
    }
    
    // Add the CSS to the page
    wp_add_inline_style('gtaw-style', $custom_css);
    
    // Determine if we show an icon
    $icon_html = '';
    if ($atts['icon'] === 'true') {
        $icon_html = '<span style="margin-right: 5px;">🔑</span>';
    }
    
    return '<a href="' . esc_url($auth_url) . '" class="' . esc_attr($style_class) . '">' . 
        $icon_html . esc_html($atts['text']) . '</a>';
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
        'layout' => 'default', // default, inline, card
        'label' => 'You are logged in as'
    ], $atts);
    
    if (!is_user_logged_in()) {
        return '<p class="gtaw-user-info-notice">Please log in to view your GTA:W character information.</p>';
    }
    
    $user_id = get_current_user_id();
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    if (empty($character)) {
        return '<p class="gtaw-user-info-notice">Your account is not linked to a GTA:W character.</p>';
    }
    
    // Prepare character info
    $character_name = esc_html($character['firstname'] . ' ' . $character['lastname']);
    $character_id = '';
    
    if ($atts['show_id'] === 'yes' && isset($character['id'])) {
        $character_id = ' (Character ID: ' . esc_html($character['id']) . ')';
    }
    
    // Generate the output based on chosen layout
    $output = '';
    
    switch ($atts['layout']) {
        case 'inline':
            $output = '<span class="gtaw-user-info gtaw-layout-inline">' . 
                esc_html($atts['label']) . ' <strong>' . $character_name . '</strong>' . $character_id .
                '</span>';
            break;
            
        case 'card':
            $output = '<div class="gtaw-user-info gtaw-layout-card" style="border: 1px solid #ddd; border-radius: 4px; overflow: hidden; max-width: 300px; margin: 10px 0;">' .
                '<div style="background: #f5f5f5; padding: 8px 12px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html($atts['label']) . '</div>' .
                '<div style="padding: 12px;">' .
                '<div style="font-size: 16px; font-weight: bold; margin-bottom: 5px;">' . $character_name . '</div>';
            
            if (!empty($character_id)) {
                $output .= '<div style="font-size: 13px; color: #666;">' . $character_id . '</div>';
            }
            
            $output .= '</div></div>';
            break;
            
        default: // 'default' layout
            $output = '<div class="gtaw-user-info">' .
                '<p>' . esc_html($atts['label']) . ' <strong>' . $character_name . '</strong>' . $character_id . '</p>' .
                '</div>';
    }
    
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
    // Quick exit if no content
    if (empty($content)) {
        return '';
    }
    
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
    // Quick exit if no content
    if (empty($content)) {
        return '';
    }
    
    if (!is_user_logged_in()) {
        return do_shortcode($content);
    }
    
    $user_id = get_current_user_id();
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    
    if (empty($gtaw_user_id)) {
        return do_shortcode($content);
    }
    
    return '';
}
add_shortcode('gtaw_if_not_logged_in', 'gtaw_if_not_logged_in_shortcode');

/**
 * New shortcode to display a combined login/logout button
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function gtaw_login_logout_shortcode($atts) {
    $atts = shortcode_atts([
        'login_text' => 'Login with GTA:W',
        'logout_text' => 'Logout',
        'class' => 'gtaw-styled-button',
        'redirect' => '',
        'style' => 'default', // default, minimal, prominent
        'icon' => 'true'
    ], $atts);
    
    // Check if user is logged in and has a GTA:W account
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
        
        if (!empty($gtaw_user_id)) {
            // User is logged in with GTA:W, show logout button
            $logout_url = wp_logout_url(home_url());
            
            // Use same style for logout button
            $style_class = $atts['class'];
            
            // Add style-specific classes if needed
            if ($atts['style'] === 'minimal') {
                $style_class .= ' gtaw-minimal-button';
            } else if ($atts['style'] === 'prominent') {
                $style_class .= ' gtaw-prominent-button';
            }
            
            return '<a href="' . esc_url($logout_url) . '" class="' . 
                esc_attr($style_class) . '">' . esc_html($atts['logout_text']) . '</a>';
        }
    }
    
    // User is not logged in or doesn't have a GTA:W account, show login button
    return gtaw_login_button_shortcode([
        'text' => $atts['login_text'],
        'class' => $atts['class'],
        'redirect' => $atts['redirect'],
        'style' => $atts['style'],
        'icon' => $atts['icon']
    ]);
}
add_shortcode('gtaw_login_logout', 'gtaw_login_logout_shortcode');

/**
 * Character information shortcode with enhanced styling options
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function gtaw_character_info_enhanced_shortcode($atts) {
    $atts = shortcode_atts([
        'style' => 'default', // default, compact, expanded
        'show_id' => 'yes',
        'show_connection' => 'no'
    ], $atts);
    
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your character information.</p>';
    }
    
    $user_id = get_current_user_id();
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    if (empty($character)) {
        return '<p>No GTA:W character information found.</p>';
    }
    
    // Get character details
    $fullname = esc_html($character['firstname'] . ' ' . $character['lastname']);
    $character_id = esc_html($character['id']);
    
    // Get connection time if needed
    $connection_html = '';
    if ($atts['show_connection'] === 'yes') {
        $connection_time = get_user_meta($user_id, 'gtaw_last_connection', true);
        $connection_status = empty($connection_time) ? 'Unknown' : human_time_diff($connection_time) . ' ago';
        $connection_html = '<p><strong>Last Authentication:</strong> ' . esc_html($connection_status) . '</p>';
    }
    
    // Build the output based on style
    $output = '<div class="gtaw-character-info gtaw-style-' . esc_attr($atts['style']) . '">';
    
    switch ($atts['style']) {
        case 'compact':
            $output .= '<div class="gtaw-character-compact" style="display: inline-block; padding: 5px 10px; background: #f5f5f5; border-radius: 3px;">';
            $output .= '<span class="gtaw-character-name" style="font-weight: bold;">' . $fullname . '</span>';
            if ($atts['show_id'] === 'yes') {
                $output .= ' <span class="gtaw-character-id" style="color: #666; font-size: 0.9em;">(ID: ' . $character_id . ')</span>';
            }
            $output .= '</div>';
            break;
            
        case 'expanded':
            $output .= '<div class="gtaw-character-expanded" style="background: #f8f8f8; padding: 15px; border-radius: 5px;">';
            $output .= '<h3 class="gtaw-character-title" style="margin-top: 0;">Your GTA:W Character</h3>';
            $output .= '<div class="gtaw-character-card" style="display: flex; align-items: center;">';
            $output .= '<div class="gtaw-character-avatar" style="margin-right: 15px; font-size: 48px; color: #2271b1;">👤</div>';
            $output .= '<div class="gtaw-character-details">';
            $output .= '<h4 style="margin: 0 0 10px 0;">' . $fullname . '</h4>';
            if ($atts['show_id'] === 'yes') {
                $output .= '<p style="margin: 5px 0;"><strong>Character ID:</strong> ' . $character_id . '</p>';
            }
            $output .= $connection_html;
            $output .= '</div></div></div>';
            break;
            
        default: // 'default' style
            $output .= '<h3>Your GTA:W Character</h3>';
            $output .= '<p><strong>Name:</strong> ' . $fullname . '</p>';
            if ($atts['show_id'] === 'yes') {
                $output .= '<p><strong>Character ID:</strong> ' . $character_id . '</p>';
            }
            $output .= $connection_html;
    }
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('gtaw_character_details', 'gtaw_character_info_enhanced_shortcode');

/**
 * Handle custom redirects from the login process
 */
function gtaw_handle_custom_redirect() {
    if (!isset($_GET['gtaw_redirect_key'])) {
        return;
    }
    
    $redirect_key = sanitize_text_field($_GET['gtaw_redirect_key']);
    $redirect_url = get_transient('gtaw_redirect_' . $redirect_key);
    
    if (!empty($redirect_url)) {
        delete_transient('gtaw_redirect_' . $redirect_key);
        
        // Validate URL is safe
        $redirect_url = wp_validate_redirect($redirect_url, home_url());
        
        wp_redirect($redirect_url);
        exit;
    }
}
add_action('template_redirect', 'gtaw_handle_custom_redirect');