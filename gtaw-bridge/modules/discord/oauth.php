<?php
defined('ABSPATH') or exit;

/* ========= DISCORD OAUTH MODULE ========= */
/*
 * This module handles Discord OAuth authentication:
 * - User account linking
 * - OAuth callback processing
 * - Account status verification
 */

/* ========= ADMIN SETTINGS ========= */

// Add tab callback for OAuth settings
function gtaw_discord_oauth_tab() {
    $redirect_uri = site_url('?discord_oauth=callback');
    ?>
    <form method="post" action="options.php">
        <?php 
            settings_fields('gtaw_discord_settings_group');
            do_settings_sections('gtaw_discord_settings_group');
        ?>
        
        <h3>Discord OAuth Settings</h3>
        <p>These settings control how users connect their Discord accounts to your site.</p>
        
        <div class="discord-oauth-config" style="margin-bottom: 20px; background: #f0f0f0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Discord OAuth Redirect URI</th>
                    <td>
                        <input type="text" readonly value="<?php echo esc_url($redirect_uri); ?>" size="50" style="width:100%;" />
                        <p class="description">This is the OAuth callback URL. Set this in your Discord Developer Portal.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button('Save OAuth Settings'); ?>
    </form>
    <?php
}

// Register the OAuth tab
add_filter('gtaw_discord_settings_tabs', function($tabs) {
    $tabs['oauth'] = [
        'title' => 'OAuth',
        'callback' => 'gtaw_discord_oauth_tab'
    ];
    return $tabs;
});

/* ========= FRONT-END FUNCTIONALITY ========= */

// Add a new rewrite endpoint "discord" for the account management page
function gtaw_add_discord_endpoint() {
    add_rewrite_endpoint('discord', EP_ROOT | EP_PAGES);
}
add_action('init', 'gtaw_add_discord_endpoint');

// Add "discord" to the list of query vars
function gtaw_discord_query_vars($vars) {
    $vars[] = 'discord';
    return $vars;
}
add_filter('query_vars', 'gtaw_discord_query_vars', 0);

// Add a new item to the WooCommerce My Account navigation menu
function gtaw_add_discord_link_my_account($items) {
    $items['discord'] = 'Discord Settings';
    return $items;
}
add_filter('woocommerce_account_menu_items', 'gtaw_add_discord_link_my_account');

// Content for the new WooCommerce "Discord" endpoint
function gtaw_discord_endpoint_content() {
    if (!is_user_logged_in()) {
        echo '<p>You must be logged in to link your Discord account.</p>';
        return;
    }
    
    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    
    if ($discord_id) {
        echo '<p>Your account is linked with Discord User ID: ' . esc_html($discord_id) . '</p>';
        // Display the unlink button using our shortcode
        echo do_shortcode('[gtaw_discord_buttons]');
    } else {
        // If not linked, simply show the shortcode (which outputs the Link Discord Account link)
        echo do_shortcode('[gtaw_discord_buttons]');
    }
}
add_action('woocommerce_account_discord_endpoint', 'gtaw_discord_endpoint_content');

/* ========= DISCORD OAUTH CALLBACK HANDLER ========= */

// Handle Discord OAuth callback and link the Discord account
function gtaw_handle_discord_oauth_callback() {
    if (isset($_GET['discord_oauth']) && $_GET['discord_oauth'] === 'callback' && isset($_GET['code'])) {
        $code = sanitize_text_field($_GET['code']);
        $client_id = get_option('gtaw_discord_client_id', '');
        $client_secret = get_option('gtaw_discord_client_secret', '');
        $redirect_uri = site_url('?discord_oauth=callback');
        
        // Exchange the authorization code for an access token
        $token_response = wp_remote_post('https://discord.com/api/oauth2/token', array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
            ),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($token_response)) {
            wp_die('Error retrieving Discord access token.');
            gtaw_add_log('discord', 'Error', "Failed to link Discord account - Token retrieval error.", 'error');
        }
        
        $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
        if (empty($token_body['access_token'])) {
            wp_die('No Discord access token returned.');
            gtaw_add_log('discord', 'Error', "Failed to link Discord account - No token returned.", 'error');
        }
        
        $access_token = sanitize_text_field($token_body['access_token']);
        
        // Retrieve the Discord user data
        $user_response = wp_remote_get('https://discord.com/api/users/@me', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            )
        ));
        
        if (is_wp_error($user_response)) {
            wp_die('Error retrieving Discord user data.');
        }
        
        $user_body = json_decode(wp_remote_retrieve_body($user_response), true);
        if (empty($user_body['id'])) {
            wp_die('No Discord user ID returned.');
        }
        
        // Save the Discord user ID to the current user's meta
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $discord_id = sanitize_text_field($user_body['id']);
            
            update_user_meta($user_id, 'discord_ID', $discord_id);
            
            // Log successful linking
            $username = wp_get_current_user()->user_login;
            gtaw_add_log('discord', 'Link', "User {$username} linked their Discord account (ID: {$discord_id}).", 'success');
            
            // Trigger role sync after linking Discord account
            gtaw_discord_trigger_account_linked($user_id, $discord_id);
        }
        
        // Redirect back to the WooCommerce My Account Discord page
        wp_redirect(wc_get_account_endpoint_url('discord'));
        exit;
    }
}
add_action('init', 'gtaw_handle_discord_oauth_callback');

/* ========= AJAX ENDPOINTS ========= */

// AJAX handler to unlink the Discord account
function gtaw_discord_unlink_account_callback() {
    if (!is_user_logged_in()) {
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

// AJAX endpoint to check Discord server membership
function gtaw_check_discord_server_membership() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_ID', true);

    if (empty($discord_id)) {
        wp_send_json_error('No Discord account linked');
    }

    // Clear cache to force a fresh check
    $cache_key = 'discord_member_' . $discord_id;
    delete_transient($cache_key);

    $is_member = gtaw_is_user_in_discord_server($discord_id);

    wp_send_json_success(['is_member' => $is_member]);
}
add_action('wp_ajax_gtaw_check_discord_membership', 'gtaw_check_discord_server_membership');

/* ========= SHORTCODES ========= */

// Helper function to generate the Discord OAuth URL
function gtaw_get_discord_auth_url() {
    $client_id = get_option('gtaw_discord_client_id', '');
    if (empty($client_id)) {
        return '';
    }
    
    $redirect_uri = site_url('?discord_oauth=callback');
    
    return add_query_arg(array(
        'client_id' => $client_id,
        'redirect_uri' => urlencode($redirect_uri),
        'response_type' => 'code',
        'scope' => 'identify'
    ), 'https://discord.com/api/oauth2/authorize');
}

// Shortcode that displays either the link or unlink link
function gtaw_discord_buttons_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to manage your Discord account.</p>';
    }
    
    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    
    // Common style: bold, no underline, pointer cursor
    $link_style = 'font-weight: bold; text-decoration: none; cursor: pointer;';
    
    if ($discord_id) {
        // Unlink state
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
        // Link state
        $auth_url = gtaw_get_discord_auth_url();
        if (empty($auth_url)) {
            $output = '<p>Discord integration is not configured. Please contact the site administrator.</p>';
        } else {
            $output = '<a href="' . esc_url($auth_url) . '" id="discord-link" style="' . esc_attr($link_style) . '">Link Discord Account</a>';
        }
    }
    
    return $output;
}
add_shortcode('gtaw_discord_buttons', 'gtaw_discord_buttons_shortcode');