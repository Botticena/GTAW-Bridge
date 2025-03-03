<?php 
defined('ABSPATH') or exit;

/* ========= ADMIN SETTINGS ========= */

// Register Discord settings.
function gtaw_discord_register_settings() {
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_enabled');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_client_id');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_client_secret');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_bot_token');
}
add_action('admin_init', 'gtaw_discord_register_settings');

// Add Discord Settings submenu under the main GTA:W Bridge menu.
function gtaw_add_discord_settings_submenu() {
    add_submenu_page(
        'gtaw-bridge',                   // Parent slug.
        'Discord Module',              // Page title.
        'Discord Module',              // Menu title.
        'manage_options',                // Capability.
        'gtaw-discord',         // Menu slug.
        'gtaw_discord_settings_page_callback' // Callback function.
    );
}
add_action('admin_menu', 'gtaw_add_discord_settings_submenu');

// Callback for the Discord Settings page.
function gtaw_discord_settings_page_callback() {
    // Auto-generate the Discord OAuth redirect URI.
    $redirect_uri = site_url('?discord_oauth=callback');
    $enabled      = get_option('gtaw_discord_enabled', 0);
    $client_id    = get_option('gtaw_discord_client_id', '');
    $client_secret= get_option('gtaw_discord_client_secret', '');
    $bot_token    = get_option('gtaw_discord_bot_token', '');
    $logs = gtaw_get_logs('discord');
    ?>
    <div class="wrap">
        <h1>Discord Module</h1>
        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="#settings" class="nav-tab nav-tab-active">Settings</a>
            <a href="#logs" class="nav-tab">Logs</a>
        </h2>
        <!-- Tab Content -->
        <div id="settings" class="tab-content">
            <form method="post" action="options.php">
                <?php 
                    settings_fields('gtaw_discord_settings_group');
                    do_settings_sections('gtaw_discord_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Activate Discord Module</th>
                        <td>
                            <input type="checkbox" name="gtaw_discord_enabled" value="1" <?php checked($enabled, 1); ?> />
                            <p class="description">Check to activate Discord integration. Uncheck to disable all Discord functionality.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Discord Client ID</th>
                        <td>
                            <input type="text" name="gtaw_discord_client_id" value="<?php echo esc_attr($client_id); ?>" size="50" />
                            <p class="description">Enter your Discord application’s Client ID for OAuth.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Discord Client Secret</th>
                        <td>
                            <input type="text" name="gtaw_discord_client_secret" value="<?php echo esc_attr($client_secret); ?>" size="50" />
                            <p class="description">Enter your Discord application’s Client Secret for OAuth.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Discord Bot Token</th>
                        <td>
                            <input type="text" name="gtaw_discord_bot_token" value="<?php echo esc_attr($bot_token); ?>" size="50" />
                            <p class="description">Enter your Discord Bot Token (for bot integrations).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Discord OAuth Redirect URI</th>
                        <td>
                            <input type="text" readonly value="<?php echo esc_url($redirect_uri); ?>" size="50" style="width:100%;" />
                            <p class="description">Set this URI in your Discord Developer Portal for your application.</p>
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
                    body: new URLSearchParams({ action: "gtaw_clear_logs", module: "discord" })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Logs cleared successfully.");
                        location.reload();
                    } else {
                        alert("Error: " + data.data);
                    }
                })
                .catch(error => alert("Request failed: " + error));
            }
        });
    });
    </script>
    
    <?php
}


/* ========= FRONT-END FUNCTIONALITY ========= */
// Only register front-end functionality if the Discord module is activated.
if ( get_option('gtaw_discord_enabled', 0) == 1 ) {

    // Add a new rewrite endpoint "discord" so customers can access the Discord linking page.
    function gtaw_add_discord_endpoint() {
        add_rewrite_endpoint('discord', EP_ROOT | EP_PAGES);
    }
    add_action('init', 'gtaw_add_discord_endpoint');

    // Add "discord" to the list of query vars.
    function gtaw_discord_query_vars($vars) {
        $vars[] = 'discord';
        return $vars;
    }
    add_filter('query_vars', 'gtaw_discord_query_vars', 0);

    // Add a new item to the WooCommerce My Account navigation menu.
    function gtaw_add_discord_link_my_account($items) {
        $items['discord'] = 'Discord Settings';
        return $items;
    }
    add_filter('woocommerce_account_menu_items', 'gtaw_add_discord_link_my_account');

    // Content for the new WooCommerce "Discord" endpoint.
    function gtaw_discord_endpoint_content() {
        if ( ! is_user_logged_in() ) {
            echo '<p>You must be logged in to link your Discord account.</p>';
            return;
        }
        $user_id    = get_current_user_id();
        $discord_id = get_user_meta($user_id, 'discord_ID', true);
        if ($discord_id) {
            echo '<p>Your account is linked with Discord User ID: ' . esc_html($discord_id) . '</p>';
            // Display the unlink button using our shortcode.
            echo do_shortcode('[gtaw_discord_buttons]');
        } else {
            // If not linked, simply show the shortcode (which outputs the Link Discord Account link).
            echo do_shortcode('[gtaw_discord_buttons]');
        }
    }
    add_action('woocommerce_account_discord_endpoint', 'gtaw_discord_endpoint_content');

    /* ========= DISCORD OAUTH CALLBACK HANDLER ========= */
    // Handle Discord OAuth callback and link the Discord account.
    function gtaw_handle_discord_oauth_callback() {
        if ( isset($_GET['discord_oauth']) && $_GET['discord_oauth'] === 'callback' && isset($_GET['code']) ) {
            $code = sanitize_text_field($_GET['code']);
            $client_id = get_option('gtaw_discord_client_id', '');
            $client_secret = get_option('gtaw_discord_client_secret', '');
            $redirect_uri = site_url('?discord_oauth=callback');
            
            // Exchange the authorization code for an access token.
            $token_response = wp_remote_post('https://discord.com/api/oauth2/token', array(
                'body'    => array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $redirect_uri,
                ),
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded'
                )
            ));
            if ( is_wp_error($token_response) ) {
                wp_die('Error retrieving Discord access token.');
                gtaw_add_log('discord', 'Error', "Failed to link Discord account - Token retrieval error.", 'error');
            }
            $token_body = json_decode( wp_remote_retrieve_body($token_response), true );
            if ( empty($token_body['access_token']) ) {
                wp_die('No Discord access token returned.');
                gtaw_add_log('discord', 'Error', "Failed to link Discord account - No token returned.", 'error');
            }
            $access_token = sanitize_text_field($token_body['access_token']);
            
            // Retrieve the Discord user data.
            $user_response = wp_remote_get('https://discord.com/api/users/@me', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                )
            ));
            if ( is_wp_error($user_response) ) {
                wp_die('Error retrieving Discord user data.');
            }
            $user_body = json_decode( wp_remote_retrieve_body($user_response), true );
            if ( empty($user_body['id']) ) {
                wp_die('No Discord user ID returned.');
            }
            
            // Save the Discord user ID to the current user’s meta.
            if ( is_user_logged_in() ) {
                update_user_meta(get_current_user_id(), 'discord_ID', sanitize_text_field($user_body['id']));
                
                // Log successful linking
                $username = wp_get_current_user()->user_login;
                gtaw_add_log('discord', 'Link', "User {$username} linked their Discord account (ID: {$user_body['id']}).", 'success');
            }
            
            // Redirect back to the WooCommerce My Account Discord page.
            wp_redirect( wc_get_account_endpoint_url('discord') );
            exit;
        }
    }
    add_action('init', 'gtaw_handle_discord_oauth_callback');

    /* ========= AJAX ENDPOINT FOR UNLINKING ========= */
    // AJAX handler to unlink the Discord account (delete the discord_ID meta).
    function gtaw_discord_unlink_account_callback() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error('Not logged in');
        }
        check_ajax_referer('gtaw_discord_unlink_nonce', 'nonce');
        $user_id = get_current_user_id();
        delete_user_meta($user_id, 'discord_ID');
        
        // Log successful unlinking
        $username = wp_get_current_user()->user_login;
        gtaw_add_log('discord', 'Unlink', "User {$username} unlinked their Discord account.", 'success');
        
        wp_send_json_success('Discord account unlinked.');
    }
    add_action('wp_ajax_gtaw_discord_unlink_account', 'gtaw_discord_unlink_account_callback');

    /* ========= SHORTCODE FOR LINK/UNLINK BUTTONS ========= */
    // Helper function to generate the Discord OAuth URL.
    function gtaw_get_discord_auth_url() {
        $client_id = get_option('gtaw_discord_client_id', '');
        if ( empty($client_id) ) {
            return '';
        }
        $redirect_uri = site_url('?discord_oauth=callback');
        return add_query_arg(array(
            'client_id'     => $client_id,
            'redirect_uri'  => urlencode($redirect_uri),
            'response_type' => 'code',
            'scope'         => 'identify'
        ), 'https://discord.com/api/oauth2/authorize');
    }

    // Shortcode that displays either the link or unlink link.
    function gtaw_discord_buttons_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<p>Please log in to manage your Discord account.</p>';
        }
        
        $user_id    = get_current_user_id();
        $discord_id = get_user_meta($user_id, 'discord_ID', true);
        // Common style: bold, no underline, pointer cursor.
        $link_style = 'font-weight: bold; text-decoration: none; cursor: pointer;';
        
        if ( $discord_id ) {
            // Unlink state.
            $nonce = wp_create_nonce('gtaw_discord_unlink_nonce');
            $output = '<a href="javascript:void(0)" id="discord-unlink" style="' . esc_attr($link_style) . '" data-nonce="' . esc_attr($nonce) . '">Unlink Discord Account</a>';
            $output .= '
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $("#discord-unlink").on("click", function(e) {
                        e.preventDefault();
                        var nonce = $(this).data("nonce");
                        $.post("' . admin_url("admin-ajax.php") . '", {
                            action: "gtaw_discord_unlink_account",
                            nonce: nonce
                        }, function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert("Error: " + response.data);
                            }
                        }).fail(function() {
                            alert("An unexpected error occurred.");
                        });
                    });
                });
            </script>';
        } else {
            // Link state.
            $auth_url = gtaw_get_discord_auth_url();
            if ( empty($auth_url) ) {
                $output = '<p>Discord integration is not configured. Please contact the site administrator.</p>';
            } else {
                $output = '<a href="' . esc_url($auth_url) . '" id="discord-link" style="' . esc_attr($link_style) . '">Link Discord Account</a>';
            }
        }
        
        return $output;
    }
    add_shortcode('gtaw_discord_buttons', 'gtaw_discord_buttons_shortcode');
  
    // Add Discord notification opt-in to WooCommerce checkout
    function gtaw_add_discord_checkout_field() {
        $user_id = get_current_user_id();
        $discord_id = get_user_meta($user_id, 'discord_ID', true);
        ?>
        <div id="gtaw-discord-notifications">
            <h3>Discord Notifications</h3>
            <p>Would you like to receive order status updates on Discord?</p>
            <div class="gtaw-discord-options">
                <label><input type="radio" name="gtaw_discord_notify" value="yes" checked> Yes, notify me</label>
                <label><input type="radio" name="gtaw_discord_notify" value="no"> No, I don’t want updates</label>
            </div>
            <?php if (!$discord_id): ?>
                <p class="gtaw-discord-warning">
                  	<span><b>You haven’t linked your Discord account!</b></span>
                    <a href="/my-account/discord/" target="_blank" rel="noopener noreferrer">Click here to link it now.</a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    add_action('woocommerce_after_order_notes', 'gtaw_add_discord_checkout_field');
  
    // Save the Discord notification choice in order meta
    function gtaw_save_discord_checkout_field($order_id) {
        if (!empty($_POST['gtaw_discord_notify'])) {
            update_post_meta($order_id, 'gtaw_discord_notify', sanitize_text_field($_POST['gtaw_discord_notify']));
        }
    }
    add_action('woocommerce_checkout_update_order_meta', 'gtaw_save_discord_checkout_field');
  
    // Display Discord notification opt-in on order details page
    function gtaw_display_discord_notification_order($order) {
        $notify = get_post_meta($order->get_id(), 'gtaw_discord_notify', true);
        if ($notify === 'yes') {
            echo '<p><strong>Discord Notifications:</strong> Enabled</p>';
        }
    }
    add_action('woocommerce_admin_order_data_after_billing_address', 'gtaw_display_discord_notification_order', 10, 1);

}
