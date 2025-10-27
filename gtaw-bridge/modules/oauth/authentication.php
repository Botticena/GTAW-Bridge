<?php
defined('ABSPATH') or exit;

/* ========= OAUTH AUTHENTICATION MODULE ========= */
/*
 * This module handles the OAuth authentication process:
 * - OAuth callback handler with improved error handling
 * - User authentication and data processing with caching
 * - Token exchange with retry logic
 * - Character selection modal with enhanced UI
 * - AJAX handlers with better security
 */

/* ========= OAUTH CALLBACK HANDLER ========= */

/**
 * Handle OAuth callback from GTA World with enhanced error handling
 * Process the authorization code and retrieve user data securely
 * 
 * @since 2.0 Added performance tracking and improved error handling
 */
function gtaw_handle_oauth_callback() {
    if (isset($_GET['gta_oauth']) && $_GET['gta_oauth'] === 'callback' && isset($_GET['code'])) {
        // Start performance tracking
        gtaw_perf_start('oauth_callback_process');
        
        // Sanitize the code but don't perform strict validation
        $code = sanitize_text_field($_GET['code']);
        
        // Basic validation - just ensure it's not empty
        if (empty($code)) {
            gtaw_add_log('oauth', 'Error', "Empty authorization code", 'error');
            wp_safe_redirect(add_query_arg('gtaw_error', urlencode('Missing authorization code. Please try again.'), home_url()));
            exit;
        }
        
        // Exchange the authorization code for an access token
        $token_data = gtaw_oauth_exchange_token($code);
        
        if (is_wp_error($token_data)) {
            $error_message = $token_data->get_error_message();
            $error_data = $token_data->get_error_data();
            
            // Log detailed error information
            gtaw_oauth_log_error('token_exchange_failed', $error_message, $error_data);
            
            // User-friendly error redirect
            wp_safe_redirect(add_query_arg('gtaw_error', urlencode('Error during authentication: ' . $error_message), home_url()));
            exit;
        }
        
        if (empty($token_data['access_token'])) {
            gtaw_add_log('oauth', 'Error', "OAuth callback failed: No access token returned", 'error');
            wp_safe_redirect(add_query_arg('gtaw_error', urlencode('Authentication server did not return an access token. Please try again later.'), home_url()));
            exit;
        }
        
        $access_token = $token_data['access_token']; // No need to sanitize, it's from the API
        
        // Retrieve the GTA:W user data using the access token
        $user_data = gtaw_oauth_get_user_data($access_token);
        
        if (is_wp_error($user_data)) {
            $error_message = $user_data->get_error_message();
            gtaw_add_log('oauth', 'Error', "Failed to retrieve user data: {$error_message}", 'error');
            wp_safe_redirect(add_query_arg('gtaw_error', urlencode('Error retrieving user data: ' . $error_message), home_url()));
            exit;
        }
        
        // Validate required user data
        if (empty($user_data['user']) || empty($user_data['user']['id'])) {
            gtaw_add_log('oauth', 'Error', "Invalid or incomplete user data returned", 'error');
            wp_safe_redirect(add_query_arg('gtaw_error', urlencode('Incomplete user data received from authentication server.'), home_url()));
            exit;
        }
        
        // Debug log to check user data
        gtaw_add_log('oauth', 'Debug', "Received user data for ID: {$user_data['user']['id']}", 'success');
        
        // Save the GTA:W user data in a cookie - using WP functions for better compatibility
        $cookie_value = base64_encode(json_encode($user_data));
        gtaw_add_log('oauth', 'Debug', "Setting cookie with data length: " . strlen($cookie_value), 'success');
        
        // Use basic cookie setting for maximum compatibility
        setcookie('gtaw_user_data', $cookie_value, time() + HOUR_IN_SECONDS * 2, COOKIEPATH, COOKIE_DOMAIN);
        
        // Trigger the OAuth process started hook
        do_action('gtaw_oauth_process_started', $user_data);
        
        // Log successful OAuth process
        if (isset($user_data['user']['username'])) {
            gtaw_add_log('oauth', 'Auth', "GTA:W user {$user_data['user']['username']} authenticated successfully", 'success');
        }
        
        // End performance tracking
        $elapsed = gtaw_perf_end('oauth_callback_process');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            gtaw_add_log('oauth', 'Performance', "OAuth callback processing took {$elapsed}s", 'success');
        }
        
        // Determine the redirect URL
        $callback_url = gtaw_oauth_get_setting('callback_url', site_url());
        
        // Check for custom redirect in transient (from shortcode)
        if (isset($_GET['gtaw_redirect_key'])) {
            $redirect_key = sanitize_key($_GET['gtaw_redirect_key']);
            $custom_redirect = get_transient('gtaw_redirect_' . $redirect_key);
            
            if ($custom_redirect) {
                delete_transient('gtaw_redirect_' . $redirect_key);
                $callback_url = $custom_redirect;
            }
        }
        
        // Add cache buster to prevent browser caching issues
        $callback_url = add_query_arg('gtaw_auth', time(), $callback_url);
        
        // Redirect back to the site
        wp_safe_redirect($callback_url);
        exit;
    }
}
add_action('init', 'gtaw_handle_oauth_callback');

/**
 * Display the character selection modal for new and returning users
 * Handles the modal display logic with enhanced UI and debugging
 * 
 * @since 2.0 Improved UX and added debugging for modal display
 */
function gtaw_display_character_selection_modal() {
    // Skip if already logged in
    if (is_user_logged_in()) {
        return;
    }
    
    // Check for the GTA:W user data cookie with debugging
    if (!isset($_COOKIE['gtaw_user_data'])) {
        // If auth parameter is present, log missing cookie for debugging
        if (isset($_GET['gtaw_auth'])) {
            gtaw_add_log('oauth', 'Debug', "Auth redirect detected but no cookie found", 'error');
        }
        return;
    }
    
    // Verify cookie content can be decoded
    try {
        $cookie_data = $_COOKIE['gtaw_user_data'];
        $decoded = base64_decode($cookie_data);
        $user_data = json_decode($decoded, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            gtaw_add_log('oauth', 'Error', "Cookie data could not be decoded: " . json_last_error_msg(), 'error');
            return;
        }
        
        // Verify user data has required fields
        if (empty($user_data['user']) || empty($user_data['user']['id'])) {
            gtaw_add_log('oauth', 'Error', "Cookie does not contain valid user data", 'error');
            return;
        }
        
        gtaw_add_log('oauth', 'Debug', "Modal should display for user ID: {$user_data['user']['id']}", 'success');
    } catch (Exception $e) {
        gtaw_add_log('oauth', 'Error', "Exception processing cookie: " . $e->getMessage(), 'error');
        return;
    }
    
    // Enqueue the script
    wp_enqueue_script('gtaw-script', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-script.js', ['jquery'], GTAW_BRIDGE_VERSION, true);
    
    // Localize script with improved data
    wp_localize_script('gtaw-script', 'gtaw_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('gtaw_nonce'),
        'error_messages' => [
            'general' => __('An error occurred. Please try again.', 'gtaw-bridge'),
            'invalid_character' => __('Invalid character selection. Please try again.', 'gtaw-bridge'),
            'server_error' => __('Server error. Please try again later.', 'gtaw-bridge')
        ]
    ]);
    
    // Check if we need to add debug markup for troubleshooting
    if (defined('WP_DEBUG') && WP_DEBUG) {
        add_action('wp_footer', 'gtaw_add_modal_debug_info', 999);
    }
}
add_action('wp_footer', 'gtaw_display_character_selection_modal');

/**
 * Add debug information for modal troubleshooting
 * Only shown when WP_DEBUG is enabled
 */
function gtaw_add_modal_debug_info() {
    if (!isset($_COOKIE['gtaw_user_data'])) {
        return;
    }
    
    // Basic information about the cookie
    echo '<!-- GTAW Debug: Cookie exists, length: ' . strlen($_COOKIE['gtaw_user_data']) . ' -->';
    
    // Add script to check if modal creation is happening
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('GTAW Debug: Document ready fired');
        console.log('GTAW Debug: Cookie exists: ' + (document.cookie.indexOf('gtaw_user_data') !== -1));
        
        // Check if modal exists after a short delay
        setTimeout(function() {
            if ($('#gtaw-modal').length === 0) {
                console.log('GTAW Debug: Modal was not created');
            } else {
                console.log('GTAW Debug: Modal was created');
            }
        }, 1000);
    });
    </script>
    <?php
}

/* ========= CHARACTER SWITCHING ========= */

/**
 * Handle logout and character switching with improved UX
 * Manages the traditional logout and character switch flow
 * 
 * @since 2.0 Added performance tracking and improved security
 */
function gtaw_handle_logout_and_switch() {
    // First check if we're using the new character switching method
    if (isset($_POST['gtaw_switch_character']) && isset($_POST['gtaw_character_nonce'])) {
        // This will be handled by the character-switching.php module
        return;
    }
    
    // Handle the traditional logout method with better security
    if (isset($_POST['gtaw_logout_and_switch']) && isset($_POST['gtaw_switch_nonce'])) {
        // Verify nonce
        if (!wp_verify_nonce($_POST['gtaw_switch_nonce'], 'gtaw_logout_and_switch')) {
            gtaw_add_log('oauth', 'Security', 'Invalid nonce in logout and switch', 'error');
            wp_die('Security check failed. Please try again.', 'Security Error', ['response' => 403]);
        }
        
        // Get the current user's info before logout
        $user_id = get_current_user_id();
        $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
        
        if (!empty($gtaw_user_id)) {
            // Start performance tracking
            gtaw_perf_start('logout_and_switch');
            
            // Prepare the OAuth URL with user info for later
            $oauth_url = gtaw_get_oauth_url();
            
            // Log the action before logout
            gtaw_add_log('oauth', 'Switch', "User {$user_id} (GTA:W ID: {$gtaw_user_id}) logged out to switch characters", 'success');
            
            // Log the user out
            wp_logout();
            
            // End performance tracking
            gtaw_perf_end('logout_and_switch');
            
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
 * Enhanced with better error handling and response formatting
 * 
 * @since 2.0 Improved security and error handling
 */
function gtaw_check_account_callback() {
    // Start performance tracking
    gtaw_perf_start('account_check');
    
    // Check for user data cookie
    if (!isset($_COOKIE['gtaw_user_data'])) {
        wp_send_json_error("No GTA:W data found. Please authenticate again.");
    }
    
    try {
        // Decode the user data from the cookie with error handling
        $cookie_data = $_COOKIE['gtaw_user_data'];
        $decoded_data = base64_decode($cookie_data);
        
        if ($decoded_data === false) {
            throw new Exception("Invalid data encoding");
        }
        
        $user_data = json_decode($decoded_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON parse error: " . json_last_error_msg());
        }
        
        // Validate user data
        $gtaw_user_id = $user_data['user']['id'] ?? '';
        
        if (empty($gtaw_user_id)) {
            throw new Exception("No GTA:W user ID found in data");
        }
        
        // Debug log
        gtaw_add_log('oauth', 'Debug', "Checking accounts for GTA:W user ID: {$gtaw_user_id}", 'success');
        
        // Check for existing WordPress accounts linked to this GTA:W user
        $users = get_users([
            'meta_key'   => 'gtaw_user_id',
            'meta_value' => $gtaw_user_id,
            'fields'     => ['ID', 'display_name', 'user_login', 'user_email']
        ]);
        
        $accounts = [];
        foreach ($users as $user) {
            $active = get_user_meta($user->ID, 'active_gtaw_character', true);
            
            if (!empty($active) && is_array($active)) {
                $accounts[] = [
                    'wp_user_id' => $user->ID,
                    'first_name' => get_user_meta($user->ID, 'first_name', true),
                    'last_name'  => get_user_meta($user->ID, 'last_name', true),
                    'active'     => $active,
                ];
            }
        }
        
        // Debug log
        gtaw_add_log('oauth', 'Debug', "Found " . count($accounts) . " accounts linked to GTA:W user ID: {$gtaw_user_id}", 'success');
        
        // End performance tracking
        $elapsed = gtaw_perf_end('account_check');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            gtaw_add_log('oauth', 'Performance', "Account check took {$elapsed}s", 'success');
        }
        
        wp_send_json_success([
            'exists' => !empty($accounts), 
            'accounts' => $accounts,
            'character_count' => count($user_data['user']['character'] ?? [])
        ]);
        
    } catch (Exception $e) {
        gtaw_add_log('oauth', 'Error', "Account check failed: " . $e->getMessage(), 'error');
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_gtaw_check_account', 'gtaw_check_account_callback');
add_action('wp_ajax_nopriv_gtaw_check_account', 'gtaw_check_account_callback');

/**
 * AJAX handler to create a new WordPress account for a GTA:W character
 * Enhanced with better validation and error handling
 * 
 * @since 2.0 Added character name validation and improved security
 */
function gtaw_create_account_callback() {
    // Start performance tracking
    gtaw_perf_start('account_creation');
    
    // Verify nonce
    if (!check_ajax_referer('gtaw_nonce', 'nonce', false)) {
        gtaw_add_log('oauth', 'Security', 'Invalid nonce in account creation attempt', 'error');
        wp_send_json_error("Security check failed. Please refresh the page and try again.");
    }
    
    // Validate the selected character data
    $selected = isset($_POST['character']) ? $_POST['character'] : null;
    if (!$selected || 
        empty($selected['firstname']) || 
        empty($selected['lastname']) || 
        empty($selected['id'])) {
        
        gtaw_add_log('oauth', 'Error', "Failed to create account - Invalid character data", 'error');
        wp_send_json_error("Invalid character data. Please select a valid character.");
    }
    
    // Skip the restrictive character name validation for now
    $firstname = sanitize_text_field($selected['firstname']);
    $lastname = sanitize_text_field($selected['lastname']);
    
    gtaw_add_log('oauth', 'Debug', "Creating account for character: {$firstname} {$lastname} (ID: {$selected['id']})", 'success');
    
    // Check for GTA:W user data
    if (!isset($_COOKIE['gtaw_user_data'])) {
        gtaw_add_log('oauth', 'Error', "Failed to create account - No GTA:W user data", 'error');
        wp_send_json_error("Authentication data not found. Please authenticate again.");
    }
    
    try {
        // Decode the user data with error handling
        $cookie_data = $_COOKIE['gtaw_user_data'];
        $decoded_data = base64_decode($cookie_data);
        
        if ($decoded_data === false) {
            throw new Exception("Invalid data encoding in cookie");
        }
        
        $user_data = json_decode($decoded_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse authentication data: " . json_last_error_msg());
        }
        
        $gtaw_user_id = $user_data['user']['id'] ?? '';
        
        if (empty($gtaw_user_id)) {
            throw new Exception("Invalid authentication data. Missing user ID.");
        }
        
        // Debug log
        gtaw_add_log('oauth', 'Debug', "Creating account with GTA:W user ID: {$gtaw_user_id}", 'success');
        
        // Verify the character exists in the user data
        $character_found = false;
        $characters = $user_data['user']['character'] ?? [];
        
        foreach ($characters as $character) {
            if (isset($character['id']) && $character['id'] == $selected['id']) {
                $character_found = true;
                break;
            }
        }
        
        if (!$character_found) {
            // Log available characters for debugging
            $character_info = [];
            foreach ($characters as $character) {
                $character_info[] = "{$character['firstname']} {$character['lastname']} (ID: {$character['id']})";
            }
            
            $available_chars = implode(', ', $character_info);
            gtaw_add_log('oauth', 'Debug', "Available characters: {$available_chars}", 'success');
            
            throw new Exception("Selected character not found in authenticated user data.");
        }
        
        // Sanitize character data
        $firstname = sanitize_text_field($selected['firstname']);
        $lastname  = sanitize_text_field($selected['lastname']);
        $char_id = sanitize_text_field($selected['id']);
        
        // Create a username based on the character's name
        $new_username = sanitize_user($firstname . '_' . $lastname);
        
        // Check if the username already exists
        if (username_exists($new_username)) {
            $new_username .= '_' . substr(md5($gtaw_user_id . $char_id), 0, 6);
            $new_username = sanitize_user($new_username);
        }
        
        // Generate an email using the character's name
        $email = strtolower($firstname . '.' . $lastname) . '@mail.sa';
        
        // Check if email already exists
        if (email_exists($email)) {
            // This character might already have an account
            // Do a secondary check by character ID
            $existing_users = get_users([
                'meta_key'   => 'gtaw_user_id',
                'meta_value' => $gtaw_user_id
            ]);
            
            foreach ($existing_users as $existing_user) {
                $active_char = get_user_meta($existing_user->ID, 'active_gtaw_character', true);
                if (isset($active_char['id']) && $active_char['id'] == $char_id) {
                    throw new Exception("This character already has an account. Please use the login option instead.");
                }
            }
            
            // If we got here, it's another user with the same name pattern
            $email = strtolower($firstname . '.' . $lastname . '.' . substr(md5($gtaw_user_id), 0, 6)) . '@mail.sa';
        }
        
        // Debug before user creation
        gtaw_add_log('oauth', 'Debug', "Creating WP user with username: {$new_username}, email: {$email}", 'success');
        
        // Create the WordPress user with enhanced security
        $user_id = wp_insert_user([
            'user_login' => $new_username,
            'user_pass'  => wp_generate_password(16, true, true), // Use WP's function for compatibility
            'first_name' => $firstname,
            'last_name'  => $lastname,
            'display_name' => "$firstname $lastname",
            'user_email' => $email,
            'role'       => gtaw_get_default_wp_role()
        ]);
        
        if (is_wp_error($user_id)) {
            throw new Exception("Error creating user: " . $user_id->get_error_message());
        }
        
        // Debug after user creation
        gtaw_add_log('oauth', 'Debug', "WP user created successfully with ID: {$user_id}", 'success');
        
        // Store GTA:W data in user meta
        update_user_meta($user_id, 'gtaw_user_id', $gtaw_user_id);
        update_user_meta($user_id, 'active_gtaw_character', [
            'id' => $char_id,
            'firstname' => $firstname,
            'lastname' => $lastname
        ]);
        
        // Store timestamp for connection freshness check
        update_user_meta($user_id, 'gtaw_last_connection', time());
        
        // Log in the new user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Clear the GTA:W user data cookie
        setcookie('gtaw_user_data', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        
        // End performance tracking
        $elapsed = gtaw_perf_end('account_creation');
        
        // Log the registration with performance data
        gtaw_add_log(
            'oauth', 
            'Register', 
            "User {$user_data['user']['username']} registered character $firstname $lastname (ID: {$char_id}), took {$elapsed}s", 
            'success'
        );
        
        // Trigger the character registered hook
        do_action('gtaw_oauth_character_registered', $user_id, [
            'id' => $char_id,
            'firstname' => $firstname,
            'lastname' => $lastname
        ]);
        
        wp_send_json_success("Account created and logged in successfully as " . $firstname . " " . $lastname . ".");
    
    } catch (Exception $e) {
        gtaw_add_log('oauth', 'Error', "Account creation failed: " . $e->getMessage(), 'error');
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_gtaw_create_account', 'gtaw_create_account_callback');
add_action('wp_ajax_nopriv_gtaw_create_account', 'gtaw_create_account_callback');

/**
 * AJAX handler to log in to an existing WordPress account linked to a GTA:W character
 * Enhanced with better validation and security
 * 
 * @since 2.0 Improved error handling and added character verification
 */
function gtaw_login_account_callback() {
    // Start performance tracking
    gtaw_perf_start('account_login');
    
    // Verify nonce
    if (!check_ajax_referer('gtaw_nonce', 'nonce', false)) {
        gtaw_add_log('oauth', 'Security', 'Invalid nonce in login attempt', 'error');
        wp_send_json_error("Security check failed. Please refresh the page and try again.");
    }
    
    // Validate the selected character data
    $selected = $_POST['character'] ?? null;
    if (!$selected || 
        empty($selected['id']) || 
        empty($selected['firstname']) || 
        empty($selected['lastname'])) {
        
        gtaw_add_log('oauth', 'Error', "Failed to log in - Invalid character data", 'error');
        wp_send_json_error("Invalid character data. Please select a valid character.");
    }
    
    // Debug log
    gtaw_add_log('oauth', 'Debug', "Login attempt for character: {$selected['firstname']} {$selected['lastname']} (ID: {$selected['id']})", 'success');
    
    // Check for GTA:W user data
    if (!isset($_COOKIE['gtaw_user_data'])) {
        gtaw_add_log('oauth', 'Error', "Failed to log in - No GTA:W user data", 'error');
        wp_send_json_error("Authentication data not found. Please authenticate again.");
    }
    
    try {
        // Decode the user data with error handling
        $cookie_data = $_COOKIE['gtaw_user_data'];
        $decoded_data = base64_decode($cookie_data);
        
        if ($decoded_data === false) {
            throw new Exception("Invalid data encoding in cookie");
        }
        
        $user_data = json_decode($decoded_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse authentication data: " . json_last_error_msg());
        }
        
        $gtaw_user_id = $user_data['user']['id'] ?? '';
        
        if (empty($gtaw_user_id)) {
            throw new Exception("Invalid authentication data. Missing user ID.");
        }
        
        // Debug log
        gtaw_add_log('oauth', 'Debug', "Logging in with GTA:W user ID: {$gtaw_user_id}", 'success');
        
        // Find the WordPress user(s) associated with this GTA:W user
        $users = get_users([
            'meta_key' => 'gtaw_user_id', 
            'meta_value' => $gtaw_user_id,
            'fields' => ['ID', 'user_login', 'display_name']
        ]);
        
        // Debug log
        gtaw_add_log('oauth', 'Debug', "Found " . count($users) . " users with GTA:W ID: {$gtaw_user_id}", 'success');
        
        // Look for the specific character
        $found = false;
        foreach ($users as $user) {
            $active = get_user_meta($user->ID, 'active_gtaw_character', true);
            if ($active && isset($active['id']) && $active['id'] == $selected['id']) {
                $found = $user;
                break;
            }
        }
        
        if (!$found) {
            throw new Exception("Account not found for selected character. You may need to create a new account for this character.");
        }
        
        // Debug log
        gtaw_add_log('oauth', 'Debug', "Found matching user ID: {$found->ID}", 'success');
        
        // Update the connection timestamp
        update_user_meta($found->ID, 'gtaw_last_connection', time());
        
        // Log in the user
        wp_set_current_user($found->ID);
        wp_set_auth_cookie($found->ID);
        
        // Clear the GTA:W user data cookie
        setcookie('gtaw_user_data', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        
        // End performance tracking
        $elapsed = gtaw_perf_end('account_login');
        
        // Log the login with performance data
        gtaw_add_log(
            'oauth', 
            'Login', 
            "User {$user_data['user']['username']} logged in as {$selected['firstname']} {$selected['lastname']} (ID: {$selected['id']}), took {$elapsed}s", 
            'success'
        );
        
        wp_send_json_success("Logged in as " . $selected['firstname'] . " " . $selected['lastname'] . ".");
        
    } catch (Exception $e) {
        gtaw_add_log('oauth', 'Error', "Account login failed: " . $e->getMessage(), 'error');
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_gtaw_login_account', 'gtaw_login_account_callback');
add_action('wp_ajax_nopriv_gtaw_login_account', 'gtaw_login_account_callback');

/**
 * Display authentication errors
 */
function gtaw_display_auth_errors() {
    if (isset($_GET['gtaw_error'])) {
        $error = sanitize_text_field(urldecode($_GET['gtaw_error']));
        echo '<div class="gtaw-error-notice">' . esc_html($error) . '</div>';
        
        // Add styling
        echo '<style>
        .gtaw-error-notice {
            background: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            margin: 10px 0;
            border-radius: 3px;
            border-left: 4px solid #dc3232;
        }
        </style>';
    }
}
add_action('wp_footer', 'gtaw_display_auth_errors', 10);

/**
 * Get the default WordPress user role with fallback
 * 
 * @return string The default role
 */
function gtaw_get_default_wp_role() {
    // Get WordPress default role setting
    $default_role = get_option('default_role', 'subscriber');
    
    // Validate that the role exists before returning it
    $roles = wp_roles();
    if (isset($roles->roles[$default_role])) {
        return $default_role;
    }
    
    // Fallback to subscriber if the role doesn't exist
    return 'subscriber';
}