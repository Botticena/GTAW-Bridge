<?php
defined('ABSPATH') or exit;

/* ========= OAUTH AUTHENTICATION MODULE ========= */
/*
 * This module handles the OAuth authentication process:
 * - OAuth callback handler
 * - User authentication and data processing
 * - Token exchange
 */

/* ========= OAUTH CALLBACK HANDLER ========= */

/**
 * Handle OAuth callback from GTA World
 * This function processes the authorization code and retrieves the user data
 */
function gtaw_handle_oauth_callback() {
    if (isset($_GET['gta_oauth']) && $_GET['gta_oauth'] === 'callback' && isset($_GET['code'])) {
        $code = sanitize_text_field($_GET['code']);
        
        // Exchange the authorization code for an access token
        $token_data = gtaw_oauth_exchange_token($code);
        
        if (is_wp_error($token_data)) {
            gtaw_add_log('oauth', 'Error', "OAuth callback failed: " . $token_data->get_error_message(), 'error');
            wp_die('Error during token exchange: ' . esc_html($token_data->get_error_message()));
        }
        
        if (empty($token_data['access_token'])) {
            gtaw_add_log('oauth', 'Error', "OAuth callback failed: No access token returned", 'error');
            wp_die('Error: No access token returned.');
        }
        
        $access_token = sanitize_text_field($token_data['access_token']);
        
        // Retrieve the GTA:W user data using the access token
        $user_data = gtaw_oauth_get_user_data($access_token);
        
        if (is_wp_error($user_data)) {
            gtaw_add_log('oauth', 'Error', "OAuth callback failed: " . $user_data->get_error_message(), 'error');
            wp_die('Error retrieving user data: ' . esc_html($user_data->get_error_message()));
        }
        
        // Save the GTA:W user data in a cookie (encoded as base64 JSON)
        setcookie('gtaw_user_data', base64_encode(json_encode($user_data)), time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
        
        // Trigger the OAuth process started hook
        gtaw_oauth_trigger_process_started($user_data);
        
        // Log successful OAuth process
        if (isset($user_data['user']['username'])) {
            gtaw_add_log('oauth', 'Auth', "GTA:W user {$user_data['user']['username']} authenticated successfully", 'success');
        }
        
        // Determine the redirect URL
        $callback_url = get_option('gtaw_callback_url', site_url());
        wp_redirect($callback_url);
        exit;
    }
}
add_action('init', 'gtaw_handle_oauth_callback');

/**
 * Display the character selection modal for new and returning users
 */
function gtaw_display_character_selection_modal() {
    // Skip if already logged in
    if (is_user_logged_in()) {
        return;
    }
    
    // Check for the GTA:W user data cookie
    if (!isset($_COOKIE['gtaw_user_data'])) {
        return;
    }
    
    // Enqueue the JavaScript to handle the modal display
    wp_enqueue_script('gtaw-script');
    
    // For the modal display itself, the JavaScript will handle the rest
}
add_action('wp_footer', 'gtaw_display_character_selection_modal');

/* ========= CHARACTER SWITCHING ========= */

/**
 * Handle logout and character switching
 */
function gtaw_handle_logout_and_switch() {
    // First check if we're using the new character switching method
    if (isset($_POST['gtaw_switch_character']) && isset($_POST['gtaw_character_nonce'])) {
        // This will be handled by our new character-switching.php
        return;
    }
    
    // Handle the traditional logout method
    if (isset($_POST['gtaw_logout_and_switch']) && isset($_POST['gtaw_switch_nonce']) && wp_verify_nonce($_POST['gtaw_switch_nonce'], 'gtaw_logout_and_switch')) {
        // Get the current user's info before logout
        $user_id = get_current_user_id();
        $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
        
        if (!empty($gtaw_user_id)) {
            // Prepare the OAuth URL with user info for later
            $oauth_url = gtaw_get_oauth_url();
            
            // Log the user out
            wp_logout();
            
            // Redirect to the OAuth URL
            wp_redirect($oauth_url);
            exit;
        }
    }
}
add_action('init', 'gtaw_handle_logout_and_switch');

/* ========= AJAX HANDLERS FOR CHARACTER SELECTION ========= */

/**
 * AJAX handler to check if a GTA:W user has existing WordPress accounts
 */
function gtaw_check_account_callback() {
    if (!isset($_COOKIE['gtaw_user_data'])) {
        wp_send_json_error("No GTA:W data found.");
    }
    
    // Decode the user data from the cookie
    $user_data = json_decode(base64_decode($_COOKIE['gtaw_user_data']), true);
    $gtaw_user_id = $user_data['user']['id'] ?? '';
    
    if (empty($gtaw_user_id)) {
        wp_send_json_error("No GTA:W user ID found.");
    }
    
    // Check for existing WordPress accounts linked to this GTA:W user
    $users = get_users(array(
        'meta_key'   => 'gtaw_user_id',
        'meta_value' => $gtaw_user_id,
    ));
    
    $accounts = array();
    foreach ($users as $user) {
        $active = get_user_meta($user->ID, 'active_gtaw_character', true);
        $accounts[] = array(
            'wp_user_id' => $user->ID,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'active'     => $active,
        );
    }
    
    wp_send_json_success(array('exists' => !empty($accounts), 'accounts' => $accounts));
}
add_action('wp_ajax_gtaw_check_account', 'gtaw_check_account_callback');
add_action('wp_ajax_nopriv_gtaw_check_account', 'gtaw_check_account_callback');

/**
 * AJAX handler to create a new WordPress account for a GTA:W character
 */
function gtaw_create_account_callback() {
    check_ajax_referer('gtaw_nonce', 'nonce');
    
    // Validate the selected character data
    $selected = $_POST['character'] ?? null;
    if (!$selected || empty($selected['firstname']) || empty($selected['lastname']) || empty($selected['id'])) {
        gtaw_add_log('oauth', 'Error', "Failed to create account - Invalid character data.", 'error');
        wp_send_json_error("Invalid character data.");
    }
    
    // Check for GTA:W user data
    if (!isset($_COOKIE['gtaw_user_data'])) {
        gtaw_add_log('oauth', 'Error', "Failed to create account - No GTA:W user data.", 'error');
        wp_send_json_error("No GTA:W user data.");
    }
    
    // Decode the user data from the cookie
    $user_data = json_decode(base64_decode($_COOKIE['gtaw_user_data']), true);
    $gtaw_user_id = $user_data['user']['id'] ?? '';
    
    if (empty($gtaw_user_id)) {
        gtaw_add_log('oauth', 'Error', "Failed to create account - No GTA:W user ID found.", 'error');
        wp_send_json_error("Invalid GTA:W user data.");
    }
    
    // Sanitize character data
    $firstname = sanitize_text_field($selected['firstname']);
    $lastname  = sanitize_text_field($selected['lastname']);
    
    // Create a username based on the character's name
    $new_username = sanitize_user($firstname . '_' . $lastname);
    
    // Check if the username already exists
    if (get_user_by('login', $new_username)) {
        $new_username .= '_' . time();
        $new_username = sanitize_user($new_username);
    }
    
    // Generate an email using the character's name
    $email = strtolower($firstname . '.' . $lastname) . '@mail.sa';
    
    // Create the WordPress user
    $user_id = wp_insert_user(array(
        'user_login' => $new_username,
        'user_pass'  => gtaw_generate_secure_password(),
        'first_name' => $firstname,
        'last_name'  => $lastname,
        'user_email' => $email,
    ));
    
    if (is_wp_error($user_id)) {
        gtaw_add_log('oauth', 'Error', "Failed to create account for $firstname $lastname: " . $user_id->get_error_message(), 'error');
        wp_send_json_error("Error creating user: " . $user_id->get_error_message());
    }
    
    // Store GTA:W data in user meta
    update_user_meta($user_id, 'gtaw_user_id', $gtaw_user_id);
    update_user_meta($user_id, 'active_gtaw_character', $selected);
    
    // Log in the new user
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    
    // Clear the GTA:W user data cookie
    setcookie('gtaw_user_data', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    
    // Log the registration
    gtaw_add_log('oauth', 'Register', "User {$user_data['user']['username']} registered character $firstname $lastname (ID: {$selected['id']})", 'success');
    
    // Trigger the character registered hook
    gtaw_oauth_trigger_character_registered($user_id, $selected);
    
    wp_send_json_success("Account created and logged in successfully as " . $firstname . " " . $lastname . ".");
}
add_action('wp_ajax_gtaw_create_account', 'gtaw_create_account_callback');
add_action('wp_ajax_nopriv_gtaw_create_account', 'gtaw_create_account_callback');

/**
 * AJAX handler to log in to an existing WordPress account linked to a GTA:W character
 */
function gtaw_login_account_callback() {
    check_ajax_referer('gtaw_nonce', 'nonce');
    
    // Validate the selected character data
    $selected = $_POST['character'] ?? null;
    if (!$selected || empty($selected['id']) || empty($selected['firstname']) || empty($selected['lastname'])) {
        gtaw_add_log('oauth', 'Error', "Failed to log in - Invalid character data.", 'error');
        wp_send_json_error("Invalid character data.");
    }
    
    // Check for GTA:W user data
    if (!isset($_COOKIE['gtaw_user_data'])) {
        gtaw_add_log('oauth', 'Error', "Failed to log in - No GTA:W user data.", 'error');
        wp_send_json_error("No GTA:W user data.");
    }
    
    // Decode the user data from the cookie
    $user_data = json_decode(base64_decode($_COOKIE['gtaw_user_data']), true);
    $gtaw_user_id = $user_data['user']['id'] ?? '';
    
    if (empty($gtaw_user_id)) {
        gtaw_add_log('oauth', 'Error', "Failed to log in - No GTA:W user ID found.", 'error');
        wp_send_json_error("Invalid GTA:W user data.");
    }
    
    // Find the WordPress user(s) associated with this GTA:W user
    $users = get_users(['meta_key' => 'gtaw_user_id', 'meta_value' => $gtaw_user_id]);
    
    // Look for the specific character
    $found = false;
    foreach ($users as $user) {
        $active = get_user_meta($user->ID, 'active_gtaw_character', true);
        if ($active && $active['id'] == $selected['id']) {
            $found = $user;
            break;
        }
    }
    
    if (!$found) {
        wp_send_json_error("Account not found for selected character.");
    }
    
    // Log in the user
    wp_set_current_user($found->ID);
    wp_set_auth_cookie($found->ID);
    
    // Clear the GTA:W user data cookie
    setcookie('gtaw_user_data', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    
    // Log the login
    gtaw_add_log('oauth', 'Login', "User {$user_data['user']['username']} logged in as {$selected['firstname']} {$selected['lastname']} (ID: {$selected['id']}).", 'success');
    
    wp_send_json_success("Logged in as " . $selected['firstname'] . " " . $selected['lastname'] . ".");
}
add_action('wp_ajax_gtaw_login_account', 'gtaw_login_account_callback');
add_action('wp_ajax_nopriv_gtaw_login_account', 'gtaw_login_account_callback');