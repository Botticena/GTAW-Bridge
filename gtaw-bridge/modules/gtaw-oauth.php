<?php
defined('ABSPATH') or exit;

/* ========= REGISTER OAUTH SETTINGS ========= */
function gtaw_register_oauth_settings() {
    register_setting( 'gtaw_settings_group', 'gtaw_oauth_enabled' );
    register_setting( 'gtaw_settings_group', 'gtaw_client_id' );
    register_setting( 'gtaw_settings_group', 'gtaw_client_secret' );
    register_setting( 'gtaw_settings_group', 'gtaw_callback_url' );
}
add_action('admin_init', 'gtaw_register_oauth_settings');


/* ========= OAUTH ADMIN SUBMENU ========= */
// Add an OAuth settings submenu under the main GTA:W Bridge menu.
function gtaw_add_oauth_submenu() {
    add_submenu_page(
        'gtaw-bridge',           // Parent slug.
        'OAuth Module',  // Page title.
        'OAuth Module',        // Menu title.
        'manage_options',        // Capability.
        'gtaw-oauth',            // Menu slug.
        'gtaw_oauth_page_callback' // Callback function.
    );
}
add_action('admin_menu', 'gtaw_add_oauth_submenu');

function gtaw_oauth_page_callback() {
    // Set the default callback URL.
    $default_oauth_callback = site_url('?gta_oauth=callback');
    $enabled      = get_option('gtaw_oauth_enabled', 1); // Default enabled.
    $client_id    = get_option('gtaw_client_id', '');
    $oauth_callback_url = get_option('gtaw_callback_url', $default_oauth_callback);
    $logs = gtaw_get_logs('oauth');
    
    // Generate the login link.
    $login_link = add_query_arg( array(
        'client_id'     => $client_id,
        'redirect_uri'  => urlencode( $oauth_callback_url ),
        'response_type' => 'code',
        'scope'         => ''
    ), 'https://ucp.gta.world/oauth/authorize' );
    ?>
    <div class="wrap">
        <h1>OAuth Module</h1>
        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="#settings" class="nav-tab nav-tab-active">Settings</a>
            <a href="#logs" class="nav-tab">Logs</a>
        </h2>
        <!-- Tab Content -->
        <div id="settings" class="tab-content">
            <form method="post" action="options.php">
                <?php 
                    settings_fields('gtaw_settings_group'); 
                    do_settings_sections('gtaw_settings_group'); 
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Activate OAuth Module</th>
                        <td>
                            <input type="checkbox" name="gtaw_oauth_enabled" value="1" <?php checked($enabled, 1); ?> />
                            <p class="description">Check to activate GTA:W OAuth integration. Uncheck to disable all OAuth functionality.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">OAuth Client ID</th>
                        <td>
                            <input type="text" name="gtaw_client_id" value="<?php echo esc_attr($client_id); ?>" size="50" />
                            <p class="description">Enter your OAuth Client ID provided in the GTA:W UCP Developers section.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">OAuth Client Secret</th>
                        <td>
                            <input type="text" name="gtaw_client_secret" value="<?php echo esc_attr(get_option('gtaw_client_secret')); ?>" size="50" />
                            <p class="description">Enter your OAuth Client Secret.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">OAuth Callback/Redirect URL</th>
                        <td>
                            <input type="text" name="gtaw_callback_url" readonly value="<?php echo esc_attr($oauth_callback_url); ?>" size="50" />
                            <p class="description">This URL is auto-generated. Ensure it matches the one in your GTA:W UCP Developers settings.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">GTA:W Login Link</th>
                        <td>
                            <input type="text" readonly value="<?php echo esc_url($login_link); ?>" size="50" style="width:100%;" />
                            <p class="description">Use this link directly or embed it with the shortcode <code>[gtaw_login]</code>.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        
        <div id="logs" class="tab-content" style="display:none;">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Type</th><th>Message</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if (!empty($logs)) : ?>
                        <?php foreach ($logs as $log) : ?>
                            <tr style="color: <?php echo ($log['status'] === 'success') ? 'green' : 'red'; ?>;">
                                <td><?php echo esc_html($log['type']); ?></td>
                                <td><?php echo esc_html($log['message']); ?></td>
                                <td><?php echo esc_html($log['date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="3">No logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <button id="clear-logs" class="button button-danger">Clear Logs</button>
        </div>

    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".nav-tab").forEach(tab => {
            tab.addEventListener("click", function() {
                document.querySelectorAll(".tab-content").forEach(content => content.style.display = "none");
                document.querySelector(this.getAttribute("href")).style.display = "block";
                document.querySelectorAll(".nav-tab").forEach(t => t.classList.remove("nav-tab-active"));
                this.classList.add("nav-tab-active");
            });
        });

        document.getElementById("clear-logs").addEventListener("click", function() {
            if (confirm("Are you sure you want to clear all logs?")) {
                fetch(ajaxurl, {
                    method: "POST",
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: "gtaw_clear_logs", module: "oauth" })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                      	alert("Logs cleared successfully.")
                      	location.reload();
                    } else {
                        alert("Failed to clear logs.");
                    }
                });
            }
        });
    });
    </script>
    
    <?php
}

/* ========= FRONT-END SHORTCODE ========= */
// Shortcode for generating the GTAW login link.
if ( get_option('gtaw_oauth_enabled', 1) == 1 ) {

    /* ========= FRONT-END SHORTCODE ========= */
    function gtaw_login_shortcode() {
        $client_id = get_option('gtaw_client_id');
        if ( empty($client_id) ) {
            return '<p>Please set your GTAW Client ID in the OAuth settings.</p>';
        }
        $callback = get_option('gtaw_callback_url');
        if ( empty($callback) ) {
            $callback = add_query_arg( array('gta_oauth' => 'callback'), site_url() );
        }
        $auth_url = add_query_arg( array(
            'client_id'     => $client_id,
            'redirect_uri'  => urlencode($callback),
            'response_type' => 'code',
            'scope'         => ''
        ), 'https://ucp.gta.world/oauth/authorize' );
        return '<a href="' . esc_url($auth_url) . '">Login / Create Account via GTAW</a>';
    }
    add_shortcode('gtaw_login', 'gtaw_login_shortcode');

    /* ========= OAUTH CALLBACK HANDLER ========= */
    function gtaw_handle_oauth_callback() {
        if ( isset($_GET['gta_oauth']) && $_GET['gta_oauth'] === 'callback' && isset($_GET['code']) ) {
            $code = sanitize_text_field($_GET['code']);
            $client_secret = get_option('gtaw_client_secret');
            $callback = get_option('gtaw_callback_url');
            if ( empty($callback) ) {
                $callback = site_url();
            }
            $response = wp_remote_post( 'https://ucp.gta.world/oauth/token', array(
                'body' => array(
                    'grant_type'    => 'authorization_code',
                    'client_id'     => get_option('gtaw_client_id'),
                    'client_secret' => $client_secret,
                    'redirect_uri'  => $callback,
                    'code'          => $code,
                )
            ));
            if ( is_wp_error($response) ) {
                wp_die( 'Error during token exchange.' );
            }
            $body = json_decode( wp_remote_retrieve_body($response), true );
            if ( empty($body['access_token']) ) {
                wp_die( 'No access token returned.' );
            }
            $access_token = sanitize_text_field($body['access_token']);
            $user_response = wp_remote_get( 'https://ucp.gta.world/api/user', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                )
            ));
            if ( is_wp_error($user_response) ) {
                wp_die( 'Error retrieving user data.' );
            }
            $user_data = json_decode( wp_remote_retrieve_body($user_response), true );
            // Save the GTAW user data in a cookie (encoded as base64 JSON).
            setcookie( 'gtaw_user_data', base64_encode( json_encode( $user_data ) ), time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
            wp_redirect( $callback );
            exit;
        }
    }
    add_action('init', 'gtaw_handle_oauth_callback');

    /* ========= AJAX ENDPOINTS ========= */
    // Check Account.
    function gtaw_check_account_callback() {
        if ( ! isset($_COOKIE['gtaw_user_data']) ) {
            wp_send_json_error("No GTAW data found.");
        }
        $user_data = json_decode( base64_decode($_COOKIE['gtaw_user_data'] ), true );
        $gtaw_user_id = $user_data['user']['id'] ?? '';
        if ( empty($gtaw_user_id) ) {
            wp_send_json_error("No GTAW user ID found.");
        }
        $users = get_users( array(
            'meta_key'   => 'gtaw_user_id',
            'meta_value' => $gtaw_user_id,
        ));
        $accounts = array();
        foreach ( $users as $user ) {
            $active = get_user_meta( $user->ID, 'active_gtaw_character', true );
            $accounts[] = array(
                'wp_user_id' => $user->ID,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'active'     => $active,
            );
        }
        wp_send_json_success( array('exists' => !empty($accounts), 'accounts' => $accounts) );
    }
    add_action('wp_ajax_gtaw_check_account', 'gtaw_check_account_callback');
    add_action('wp_ajax_nopriv_gtaw_check_account', 'gtaw_check_account_callback');

    // Create Account.
    function gtaw_create_account_callback() {
        check_ajax_referer('gtaw_nonce', 'nonce');
        $selected = $_POST['character'] ?? null;
        if ( ! $selected || empty($selected['firstname']) || empty($selected['lastname']) || empty($selected['id']) ) {
            gtaw_add_log('oauth', 'Error', "Failed to create account - Invalid character data.", 'error');
            wp_send_json_error("Invalid character data.");
        }
        if ( ! isset($_COOKIE['gtaw_user_data']) ) {
            gtaw_add_log('oauth', 'Error', "Failed to create account - No GTAW user data.", 'error');
            wp_send_json_error("No GTAW user data.");
        }
        $user_data = json_decode( base64_decode($_COOKIE['gtaw_user_data'] ), true );
        $gtaw_user_id = $user_data['user']['id'] ?? '';
        if ( empty($gtaw_user_id) ) {
            gtaw_add_log('oauth', 'Error', "Failed to create account - No GTAW user ID found.", 'error');
            wp_send_json_error("Invalid GTAW user data.");
        }
        $firstname = sanitize_text_field($selected['firstname']);
        $lastname  = sanitize_text_field($selected['lastname']);
        $new_username = sanitize_user( $firstname . '_' . $lastname );
        if ( get_user_by('login', $new_username) ) {
            $new_username .= '_' . time();
            $new_username = sanitize_user( $new_username );
        }
        $email = strtolower( $firstname . '.' . $lastname ) . '@mail.sa';
        $user_id = wp_insert_user( array(
            'user_login' => $new_username,
            'user_pass'  => wp_generate_password(),
            'first_name' => $firstname,
            'last_name'  => $lastname,
            'user_email' => $email,
        ));
        if ( is_wp_error($user_id) ) {
            gtaw_add_log('oauth', 'Error', "Failed to create account for $firstname $lastname.", 'error');
            wp_send_json_error("Error creating user.");
        }
        update_user_meta( $user_id, 'gtaw_user_id', $gtaw_user_id );
        update_user_meta( $user_id, 'active_gtaw_character', $selected );
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );
        setcookie('gtaw_user_data', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        gtaw_add_log('oauth', 'Register', "User {$user_data['user']['username']} registered character $firstname $lastname (ID: {$selected['id']})", 'success');
        wp_send_json_success("Account created and logged in successfully as " . $firstname . " " . $lastname . ".");
    }
    add_action('wp_ajax_gtaw_create_account', 'gtaw_create_account_callback');
    add_action('wp_ajax_nopriv_gtaw_create_account', 'gtaw_create_account_callback');

    // Login Account.
    function gtaw_login_account_callback() {
        check_ajax_referer('gtaw_nonce', 'nonce');
        $selected = $_POST['character'] ?? null;
        if (!$selected || empty($selected['id']) || empty($selected['firstname']) || empty($selected['lastname'])) {
            gtaw_add_log('oauth', 'Error', "Failed to log in - Invalid character data.", 'error');
            wp_send_json_error("Invalid character data.");
        }
        if (!isset($_COOKIE['gtaw_user_data'])) {
            gtaw_add_log('oauth', 'Error', "Failed to log in - No GTAW user data.", 'error');
            wp_send_json_error("No GTAW user data.");
        }
        $user_data = json_decode(base64_decode($_COOKIE['gtaw_user_data']), true);
        $gtaw_user_id = $user_data['user']['id'] ?? '';
        if (empty($gtaw_user_id)) {
            gtaw_add_log('oauth', 'Error', "Failed to log in - No GTAW user ID found.", 'error');
            wp_send_json_error("Invalid GTAW user data.");
        }
        $users = get_users(['meta_key' => 'gtaw_user_id', 'meta_value' => $gtaw_user_id]);
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
        wp_set_current_user($found->ID);
        wp_set_auth_cookie($found->ID);
        gtaw_add_log('oauth', 'Login', "User {$user_data['user']['username']} logged in as {$selected['firstname']} {$selected['lastname']} (ID: {$selected['id']}).", 'success');
        wp_send_json_success("Logged in as " . $selected['firstname'] . " " . $selected['lastname'] . ".");
    }
    add_action('wp_ajax_gtaw_login_account', 'gtaw_login_account_callback');
    add_action('wp_ajax_nopriv_gtaw_login_account', 'gtaw_login_account_callback');
}
