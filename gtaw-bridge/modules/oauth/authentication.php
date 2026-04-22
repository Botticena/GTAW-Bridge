<?php
defined('ABSPATH') or exit;

// Callback ?gta_oauth=callback, cookie for modal, AJAX for pick-character flows.

function gtaw_handle_oauth_callback() {
    if ( function_exists( 'gtaw_oauth_get_setting' ) && ! gtaw_oauth_get_setting( 'enabled', true ) ) {
        return;
    }
    if (isset($_GET['gta_oauth']) && $_GET['gta_oauth'] === 'callback' && isset($_GET['code'])) {
        gtaw_perf_start('oauth_callback_process');

        $code = sanitize_text_field($_GET['code']);

        if (empty($code)) {
            gtaw_add_log('oauth', 'Error', "Empty authorization code", 'error');
            wp_safe_redirect(add_query_arg('gtaw_error', urlencode('Missing authorization code. Please try again.'), home_url()));
            exit;
        }
        
        $token_data = gtaw_oauth_exchange_token($code);
        
        if (is_wp_error($token_data)) {
            $error_message = $token_data->get_error_message();
            $error_data = $token_data->get_error_data();
            
            gtaw_oauth_log_error('token_exchange_failed', $error_message, $error_data);

            wp_safe_redirect(add_query_arg('gtaw_error', urlencode('Error during authentication: ' . $error_message), home_url()));
            exit;
        }
        
        if (empty($token_data['access_token'])) {
            gtaw_add_log('oauth', 'Error', "OAuth callback failed: No access token returned", 'error');
            wp_safe_redirect(add_query_arg('gtaw_error', urlencode('Authentication server did not return an access token. Please try again later.'), home_url()));
            exit;
        }
        
        $access_token = $token_data['access_token'];

        $user_data = gtaw_oauth_get_user_data($access_token);
        
        if (is_wp_error($user_data)) {
            $error_message = $user_data->get_error_message();
            gtaw_add_log('oauth', 'Error', "Failed to retrieve user data: {$error_message}", 'error');
            wp_safe_redirect(add_query_arg('gtaw_error', urlencode('Error retrieving user data: ' . $error_message), home_url()));
            exit;
        }
        
        if (empty($user_data['user']) || empty($user_data['user']['id'])) {
            gtaw_add_log('oauth', 'Error', "Invalid or incomplete user data returned", 'error');
            wp_safe_redirect(add_query_arg('gtaw_error', urlencode('Incomplete user data received from authentication server.'), home_url()));
            exit;
        }
        
        gtaw_add_log('oauth', 'Debug', "Received user data for ID: {$user_data['user']['id']}", 'success');

        $cookie_value = base64_encode(json_encode($user_data));
        gtaw_add_log('oauth', 'Debug', "Setting cookie with data length: " . strlen($cookie_value), 'success');

        setcookie('gtaw_user_data', $cookie_value, time() + HOUR_IN_SECONDS * 2, COOKIEPATH, COOKIE_DOMAIN);

        do_action('gtaw_oauth_process_started', $user_data);

        if (isset($user_data['user']['username'])) {
            gtaw_add_log('oauth', 'Auth', "GTA:W user {$user_data['user']['username']} authenticated successfully", 'success');
        }
        $elapsed = gtaw_perf_end('oauth_callback_process');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            gtaw_add_log('oauth', 'Performance', "OAuth callback processing took {$elapsed}s", 'success');
        }
        
        $callback_url = gtaw_oauth_get_setting('callback_url', site_url());

        if (isset($_GET['gtaw_redirect_key'])) {
            $redirect_key = sanitize_key($_GET['gtaw_redirect_key']);
            $custom_redirect = get_transient('gtaw_redirect_' . $redirect_key);
            
            if ($custom_redirect) {
                delete_transient('gtaw_redirect_' . $redirect_key);
                $callback_url = $custom_redirect;
            }
        }
        
        $callback_url = add_query_arg('gtaw_auth', time(), $callback_url);

        wp_safe_redirect($callback_url);
        exit;
    }
}
add_action('init', 'gtaw_handle_oauth_callback');

function gtaw_display_character_selection_modal() {
    if (is_user_logged_in()) {
        return;
    }

    if (!isset($_COOKIE['gtaw_user_data'])) {
        if (isset($_GET['gtaw_auth'])) {
            gtaw_add_log('oauth', 'Debug', "Auth redirect detected but no cookie found", 'error');
        }
        return;
    }
    
    try {
        $cookie_data = $_COOKIE['gtaw_user_data'];
        $decoded = base64_decode($cookie_data);
        $user_data = json_decode($decoded, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            gtaw_add_log('oauth', 'Error', "Cookie data could not be decoded: " . json_last_error_msg(), 'error');
            return;
        }
        
        if (empty($user_data['user']) || empty($user_data['user']['id'])) {
            gtaw_add_log('oauth', 'Error', "Cookie does not contain valid user data", 'error');
            return;
        }
        
        gtaw_add_log('oauth', 'Debug', "Modal should display for user ID: {$user_data['user']['id']}", 'success');
    } catch (Exception $e) {
        gtaw_add_log('oauth', 'Error', "Exception processing cookie: " . $e->getMessage(), 'error');
        return;
    }
    
    wp_enqueue_script('gtaw-script', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-script.js', ['jquery'], GTAW_BRIDGE_VERSION, true);

    wp_localize_script('gtaw-script', 'gtaw_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('gtaw_nonce'),
        'error_messages' => [
            'general' => __('An error occurred. Please try again.', 'gtaw-bridge'),
            'invalid_character' => __('Invalid character selection. Please try again.', 'gtaw-bridge'),
            'server_error' => __('Server error. Please try again later.', 'gtaw-bridge')
        ]
    ]);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        add_action('wp_footer', 'gtaw_add_modal_debug_info', 999);
    }
}
add_action('wp_footer', 'gtaw_display_character_selection_modal');

function gtaw_add_modal_debug_info() {
    if (!isset($_COOKIE['gtaw_user_data'])) {
        return;
    }
    
    echo '<!-- GTAW Debug: Cookie exists, length: ' . strlen($_COOKIE['gtaw_user_data']) . ' -->';
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('GTAW Debug: Document ready fired');
        console.log('GTAW Debug: Cookie exists: ' + (document.cookie.indexOf('gtaw_user_data') !== -1));
        
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


function gtaw_handle_logout_and_switch() {
    if (isset($_POST['gtaw_switch_character']) && isset($_POST['gtaw_character_nonce'])) {
        return;
    }

    if (isset($_POST['gtaw_logout_and_switch']) && isset($_POST['gtaw_switch_nonce'])) {
        if (!wp_verify_nonce($_POST['gtaw_switch_nonce'], 'gtaw_logout_and_switch')) {
            gtaw_add_log('oauth', 'Security', 'Invalid nonce in logout and switch', 'error');
            wp_die('Security check failed. Please try again.', 'Security Error', ['response' => 403]);
        }

        $user_id = get_current_user_id();
        $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);

        if (!empty($gtaw_user_id)) {
            gtaw_perf_start('logout_and_switch');

            $oauth_url = gtaw_get_oauth_url();

            gtaw_add_log('oauth', 'Switch', "User {$user_id} (GTA:W ID: {$gtaw_user_id}) logged out to switch characters", 'success');

            wp_logout();
            gtaw_perf_end('logout_and_switch');

            wp_redirect($oauth_url);
            exit;
        }
    }
}
add_action('init', 'gtaw_handle_logout_and_switch');


function gtaw_check_account_callback() {
    gtaw_perf_start('account_check');

    if (!isset($_COOKIE['gtaw_user_data'])) {
        wp_send_json_error("No GTA:W data found. Please authenticate again.");
    }
    
    try {
        $cookie_data = $_COOKIE['gtaw_user_data'];
        $decoded_data = base64_decode($cookie_data);
        
        if ($decoded_data === false) {
            throw new Exception("Invalid data encoding");
        }
        
        $user_data = json_decode($decoded_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON parse error: " . json_last_error_msg());
        }
        
        $gtaw_user_id = $user_data['user']['id'] ?? '';
        
        if (empty($gtaw_user_id)) {
            throw new Exception("No GTA:W user ID found in data");
        }
        
        gtaw_add_log('oauth', 'Debug', "Checking accounts for GTA:W user ID: {$gtaw_user_id}", 'success');

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
        
        gtaw_add_log('oauth', 'Debug', "Found " . count($accounts) . " accounts linked to GTA:W user ID: {$gtaw_user_id}", 'success');
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

function gtaw_create_account_callback() {
    gtaw_perf_start('account_creation');

    if (!check_ajax_referer('gtaw_nonce', 'nonce', false)) {
        gtaw_add_log('oauth', 'Security', 'Invalid nonce in account creation attempt', 'error');
        wp_send_json_error("Security check failed. Please refresh the page and try again.");
    }
    
    $selected = isset($_POST['character']) ? $_POST['character'] : null;
    if (!$selected || 
        empty($selected['firstname']) || 
        empty($selected['lastname']) || 
        empty($selected['id'])) {
        
        gtaw_add_log('oauth', 'Error', "Failed to create account - Invalid character data", 'error');
        wp_send_json_error("Invalid character data. Please select a valid character.");
    }
    
    $firstname = sanitize_text_field($selected['firstname']);
    $lastname = sanitize_text_field($selected['lastname']);
    
    gtaw_add_log('oauth', 'Debug', "Creating account for character: {$firstname} {$lastname} (ID: {$selected['id']})", 'success');
    
    if (!isset($_COOKIE['gtaw_user_data'])) {
        gtaw_add_log('oauth', 'Error', "Failed to create account - No GTA:W user data", 'error');
        wp_send_json_error("Authentication data not found. Please authenticate again.");
    }
    
    try {
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
        
        gtaw_add_log('oauth', 'Debug', "Creating account with GTA:W user ID: {$gtaw_user_id}", 'success');

        $character_found = false;
        $characters = $user_data['user']['character'] ?? [];
        
        foreach ($characters as $character) {
            if (isset($character['id']) && $character['id'] == $selected['id']) {
                $character_found = true;
                break;
            }
        }
        
        if (!$character_found) {
            $character_info = [];
            foreach ($characters as $character) {
                $character_info[] = "{$character['firstname']} {$character['lastname']} (ID: {$character['id']})";
            }
            
            $available_chars = implode(', ', $character_info);
            gtaw_add_log('oauth', 'Debug', "Available characters: {$available_chars}", 'success');
            
            throw new Exception("Selected character not found in authenticated user data.");
        }
        
        $firstname = sanitize_text_field($selected['firstname']);
        $lastname  = sanitize_text_field($selected['lastname']);
        $char_id = sanitize_text_field($selected['id']);

        $new_username = sanitize_user($firstname . '_' . $lastname);

        if (username_exists($new_username)) {
            $new_username .= '_' . substr(md5($gtaw_user_id . $char_id), 0, 6);
            $new_username = sanitize_user($new_username);
        }
        
        $email = strtolower($firstname . '.' . $lastname) . '@mail.sa';

        if (email_exists($email)) {
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
            
            $email = strtolower($firstname . '.' . $lastname . '.' . substr(md5($gtaw_user_id), 0, 6)) . '@mail.sa';
        }

        gtaw_add_log('oauth', 'Debug', "Creating WP user with username: {$new_username}, email: {$email}", 'success');

        $user_id = wp_insert_user([
            'user_login' => $new_username,
            'user_pass'  => wp_generate_password(16, true, true),
            'first_name' => $firstname,
            'last_name'  => $lastname,
            'display_name' => "$firstname $lastname",
            'user_email' => $email,
            'role'       => gtaw_get_default_wp_role()
        ]);
        
        if (is_wp_error($user_id)) {
            throw new Exception("Error creating user: " . $user_id->get_error_message());
        }
        
        gtaw_add_log('oauth', 'Debug', "WP user created successfully with ID: {$user_id}", 'success');

        update_user_meta($user_id, 'gtaw_user_id', $gtaw_user_id);
        update_user_meta($user_id, 'active_gtaw_character', [
            'id' => $char_id,
            'firstname' => $firstname,
            'lastname' => $lastname
        ]);
        
        update_user_meta($user_id, 'gtaw_last_connection', time());

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        setcookie('gtaw_user_data', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        $elapsed = gtaw_perf_end('account_creation');

        gtaw_add_log(
            'oauth', 
            'Register', 
            "User {$user_data['user']['username']} registered character $firstname $lastname (ID: {$char_id}), took {$elapsed}s", 
            'success'
        );
        
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

function gtaw_login_account_callback() {
    gtaw_perf_start('account_login');

    if (!check_ajax_referer('gtaw_nonce', 'nonce', false)) {
        gtaw_add_log('oauth', 'Security', 'Invalid nonce in login attempt', 'error');
        wp_send_json_error("Security check failed. Please refresh the page and try again.");
    }
    
    $selected = $_POST['character'] ?? null;
    if (!$selected || 
        empty($selected['id']) || 
        empty($selected['firstname']) || 
        empty($selected['lastname'])) {
        
        gtaw_add_log('oauth', 'Error', "Failed to log in - Invalid character data", 'error');
        wp_send_json_error("Invalid character data. Please select a valid character.");
    }
    
    gtaw_add_log('oauth', 'Debug', "Login attempt for character: {$selected['firstname']} {$selected['lastname']} (ID: {$selected['id']})", 'success');

    if (!isset($_COOKIE['gtaw_user_data'])) {
        gtaw_add_log('oauth', 'Error', "Failed to log in - No GTA:W user data", 'error');
        wp_send_json_error("Authentication data not found. Please authenticate again.");
    }
    
    try {
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
        
        gtaw_add_log('oauth', 'Debug', "Logging in with GTA:W user ID: {$gtaw_user_id}", 'success');

        $users = get_users([
            'meta_key' => 'gtaw_user_id', 
            'meta_value' => $gtaw_user_id,
            'fields' => ['ID', 'user_login', 'display_name']
        ]);
        
        gtaw_add_log('oauth', 'Debug', "Found " . count($users) . " users with GTA:W ID: {$gtaw_user_id}", 'success');

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
        
        gtaw_add_log('oauth', 'Debug', "Found matching user ID: {$found->ID}", 'success');

        update_user_meta($found->ID, 'gtaw_last_connection', time());

        wp_set_current_user($found->ID);
        wp_set_auth_cookie($found->ID);

        setcookie('gtaw_user_data', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        $elapsed = gtaw_perf_end('account_login');

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

function gtaw_display_auth_errors() {
    if (isset($_GET['gtaw_error'])) {
        $error = sanitize_text_field(urldecode($_GET['gtaw_error']));
        echo '<div class="gtaw-error-notice">' . esc_html($error) . '</div>';

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

function gtaw_get_default_wp_role() {
    $default_role = get_option('default_role', 'subscriber');

    $roles = wp_roles();
    if (isset($roles->roles[$default_role])) {
        return $default_role;
    }

    return 'subscriber';
}