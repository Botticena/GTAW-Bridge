<?php
/*
Plugin Name: GTA:W Wordpress Bridge
Description: GTA:World Roleplay Wordpress Bridge with oAuth.
Version: 1.0
Author: Lena
Author URI: https://forum.gta.world/en/profile/56418-lena/
Plugin URI: https://github.com/Botticena/gtaw-bridge/
*/

if ( is_admin() ) {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        add_action( 'admin_notices', 'gtaw_wc_admin_notice' );
        // Optionally, stop further execution on the front-end:
        return;
    }
}

function gtaw_wc_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    echo '<div class="notice notice-error is-dismissible">';
    echo '<p><strong>GTAW Bridge Plugin Notice:</strong> WooCommerce must be installed and activated for this plugin to work properly. Please <a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">install WooCommerce</a> or activate it if it is already installed.</p>';
    echo '</div>';
}


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ========= SETTINGS & ADMIN PAGE ========= */

function gtaw_register_settings() {
    // GTAW OAuth Settings
    register_setting( 'gtaw_settings_group', 'gtaw_client_id' );
    register_setting( 'gtaw_settings_group', 'gtaw_client_secret' );
    register_setting( 'gtaw_settings_group', 'gtaw_callback_url' );
}
add_action( 'admin_init', 'gtaw_register_settings' );



// Create a top-level admin menu item for GTA:W Bridge.
function gtaw_add_admin_menu() {
    add_menu_page(
        'GTA:W Bridge Settings',    // Page title
        'GTA:W Bridge',             // Menu title
        'manage_options',           // Capability
        'gtaw-bridge-settings',     // Menu slug
        'gtaw_settings_page_callback', // Callback function
        'dashicons-admin-site',  // Icon (you can choose another dashicon)
        2                          // Position
    );
}
add_action('admin_menu', 'gtaw_add_admin_menu');

function gtaw_settings_page_callback() {
    // Set default callback URL.
    $default_oauth_callback = site_url( '?gta_oauth=callback' );
    
    // Retrieve stored options.
    $client_id = get_option('gtaw_client_id', '');
    $oauth_callback_url = get_option('gtaw_callback_url', $default_oauth_callback);
    
    // Generate the GTA:W login link.
    $login_link = add_query_arg( array(
        'client_id'     => $client_id,
        'redirect_uri'  => urlencode( $oauth_callback_url ),
        'response_type' => 'code',
        'scope'         => ''
    ), 'https://ucp.gta.world/oauth/authorize' );
    
    // Determine current tab.
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'gtaw_oauth';
    ?>
    <div class="wrap">
        <h1>GTA:W Bridge Settings</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=gtaw-bridge-settings&tab=gtaw_oauth" class="nav-tab <?php echo ($current_tab === 'gtaw_oauth') ? 'nav-tab-active' : ''; ?>">GTA:W oAuth</a>
            <a href="?page=gtaw-bridge-settings&tab=fleeca_api" class="nav-tab <?php echo ($current_tab === 'fleeca_api') ? 'nav-tab-active' : ''; ?>">Fleeca API</a>
            <a href="?page=gtaw-bridge-settings&tab=discord_sync" class="nav-tab <?php echo ($current_tab === 'discord_sync') ? 'nav-tab-active' : ''; ?>">Discord Sync</a>
        </h2>
        
        <?php if ( $current_tab == 'gtaw_oauth' ) : ?>
            <form method="post" action="options.php">
                <?php 
                    settings_fields('gtaw_settings_group'); 
                    do_settings_sections('gtaw_settings_group'); 
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">OAuth Client ID</th>
                        <td>
                            <input type="text" name="gtaw_client_id" value="<?php echo esc_attr($client_id); ?>" size="50" />
                            <p class="description">Enter your OAuth Client ID provided on the GTA:W UCP Developers section.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">OAuth Client Secret</th>
                        <td>
                            <input type="text" name="gtaw_client_secret" value="<?php echo esc_attr(get_option('gtaw_client_secret')); ?>" size="50" />
                            <p class="description">Enter your OAuth Client Secret provided on the GTA:W UCP Developers section.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">OAuth Callback/Redirect URL</th>
                        <td>
                            <input type="text" name="gtaw_callback_url" value="<?php echo esc_attr($oauth_callback_url); ?>" size="50" />
                            <p class="description">Auto-generated URL. Make sure to edit the one on the GTA:W UCP Developers section to match this auto generated one.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">GTA:W Login Link</th>
                        <td>
                            <input type="text" readonly value="<?php echo esc_url($login_link); ?>" size="50" style="width:100%;" />
                            <p class="description">You can use this link directly or embed it using the shortcode <code>[gtaw_login]</code>.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        <?php elseif ( $current_tab == 'fleeca_api' ) : ?>
            <p>Coming Soon</p>
        <?php elseif ( $current_tab == 'discord_sync' ) : ?>
            <p>Coming Soon</p>
        <?php endif; ?>
    </div>
    <?php
}


/* ========= FRONT-END SHORTCODE & CALLBACKS ========= */

// Shortcode for GTAW login.
function gtaw_login_shortcode() {
    $client_id = get_option( 'gtaw_client_id' );
    if ( empty( $client_id ) ) {
        return '<p>Please set your GTAW Client ID in the plugin settings.</p>';
    }
    $callback = get_option( 'gtaw_callback_url' );
    if ( empty( $callback ) ) {
        $callback = add_query_arg( array( 'gta_oauth' => 'callback' ), site_url() );
    }
    $auth_url = add_query_arg( array(
        'client_id'     => $client_id,
        'redirect_uri'  => urlencode( $callback ),
        'response_type' => 'code',
        'scope'         => ''
    ), 'https://ucp.gta.world/oauth/authorize' );
    return '<a href="' . esc_url( $auth_url ) . '">Login / Create Account via GTAW</a>';
}
add_shortcode( 'gtaw_login', 'gtaw_login_shortcode' );

// GTAW OAuth callback.
function gtaw_handle_oauth_callback() {
    if ( isset( $_GET['gta_oauth'] ) && $_GET['gta_oauth'] === 'callback' && isset( $_GET['code'] ) ) {
        $code = sanitize_text_field( $_GET['code'] );
        $client_secret = get_option( 'gtaw_client_secret' );
        $callback = get_option( 'gtaw_callback_url' );
        // If no callback URL is set, use the site homepage.
        if ( empty( $callback ) ) {
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
        ) );
        if ( is_wp_error( $response ) ) {
            wp_die( 'Error during token exchange.' );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) {
            wp_die( 'No access token returned.' );
        }
        $access_token = sanitize_text_field( $body['access_token'] );
        $user_response = wp_remote_get( 'https://ucp.gta.world/api/user', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            )
        ) );
        if ( is_wp_error( $user_response ) ) {
            wp_die( 'Error retrieving user data.' );
        }
        $user_data = json_decode( wp_remote_retrieve_body( $user_response ), true );
        // Save GTAW user data (as base64-encoded JSON) in a cookie.
        setcookie( 'gtaw_user_data', base64_encode( json_encode( $user_data ) ), time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
        // Redirect to the callback URL (or site homepage if not set).
        wp_redirect( $callback );
        exit;
    }
}
add_action( 'init', 'gtaw_handle_oauth_callback' );


/* ========= NEW AJAX ENDPOINTS ========= */

/* 1. Check account – return all WP accounts for this GTAW user. */
function gtaw_check_account_callback() {
    if ( ! isset($_COOKIE['gtaw_user_data']) ) {
        wp_send_json_error("No GTAW data found.");
    }
    $user_data = json_decode(base64_decode($_COOKIE['gtaw_user_data']), true);
    $gtaw_user_id = $user_data['user']['id'] ?? '';
    if ( empty($gtaw_user_id) ) {
        wp_send_json_error("No GTAW user ID found.");
    }
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

/* 2. Create account: always create a new WP account for the selected character. */
function gtaw_create_account_callback() {
    check_ajax_referer('gtaw_nonce', 'nonce');
    $selected = $_POST['character'] ?? null;
    if ( ! $selected || empty($selected['firstname']) || empty($selected['lastname']) || empty($selected['id']) ) {
        wp_send_json_error("Invalid character data.");
    }
    if ( ! isset($_COOKIE['gtaw_user_data']) ) {
        wp_send_json_error("No GTAW user data.");
    }
    $user_data = json_decode(base64_decode($_COOKIE['gtaw_user_data']), true);
    $gtaw_user_id = $user_data['user']['id'] ?? '';
    if ( empty($gtaw_user_id) ) {
        wp_send_json_error("Invalid GTAW user data.");
    }
    // Create WP username as: (firstname)_(lastname)
    $firstname = sanitize_text_field($selected['firstname']);
    $lastname  = sanitize_text_field($selected['lastname']);
    $new_username = sanitize_user($firstname . '_' . $lastname);
    if ( get_user_by('login', $new_username) ) {
        $new_username .= '_' . time();
        $new_username = sanitize_user($new_username);
    }
    $email = strtolower($firstname . '.' . $lastname) . '@mail.sa';
    $user_id = wp_insert_user(array(
        'user_login' => $new_username,
        'user_pass'  => wp_generate_password(),
        'first_name' => $firstname,
        'last_name'  => $lastname,
        'user_email' => $email,
    ));
    if ( is_wp_error($user_id) ) {
        wp_send_json_error("Error creating user.");
    }
    // Save meta linking this WP account to the GTAW user.
    update_user_meta($user_id, 'gtaw_user_id', $gtaw_user_id);
    // Save active character.
    update_user_meta($user_id, 'active_gtaw_character', $selected);
    // (Optionally, you could store an array of connected characters; here each account is one character.)
    // Log the user in.
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    setcookie('gtaw_user_data', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    wp_send_json_success("Account created and logged in successfully as " . $firstname . " " . $lastname . ".");
}
add_action('wp_ajax_gtaw_create_account', 'gtaw_create_account_callback');
add_action('wp_ajax_nopriv_gtaw_create_account', 'gtaw_create_account_callback');

/* 3. Login account: log in as an existing account based on selected active character. */
function gtaw_login_account_callback() {
    check_ajax_referer('gtaw_nonce', 'nonce');
    $selected = $_POST['character'] ?? null;
    if ( ! $selected || empty($selected['id']) ) {
        wp_send_json_error("Invalid character data.");
    }
    if ( ! isset($_COOKIE['gtaw_user_data']) ) {
        wp_send_json_error("No GTAW user data.");
    }
    $user_data = json_decode(base64_decode($_COOKIE['gtaw_user_data']), true);
    $gtaw_user_id = $user_data['user']['id'] ?? '';
    if ( empty($gtaw_user_id) ) {
        wp_send_json_error("Invalid GTAW user data.");
    }
    // Find the WP account that has the matching GTAW user ID and whose active character has the selected character's id.
    $users = get_users(array(
        'meta_key'   => 'gtaw_user_id',
        'meta_value' => $gtaw_user_id,
    ));
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
    // Log in as the found account.
    wp_set_current_user($found->ID);
    wp_set_auth_cookie($found->ID);
    wp_send_json_success("Logged in as " . $selected['firstname'] . " " . $selected['lastname'] . ".");
}
add_action('wp_ajax_gtaw_login_account', 'gtaw_login_account_callback');
add_action('wp_ajax_nopriv_gtaw_login_account', 'gtaw_login_account_callback');

/* ========= ENQUEUE SCRIPTS & STYLES ========= */
function gtaw_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('gtaw-script', plugin_dir_url(__FILE__) . 'gtaw-script.js', array('jquery'), '1.0', true);
    wp_localize_script('gtaw-script', 'gtaw_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('gtaw_nonce'),
    ));
    wp_enqueue_style('gtaw-style', plugin_dir_url(__FILE__) . 'gtaw-style.css');
}
add_action('wp_enqueue_scripts', 'gtaw_enqueue_scripts');

