<?php
defined('ABSPATH') or exit;

/* ========= DISCORD OAUTH MODULE ========= */
/*
 * This module handles Discord OAuth authentication:
 * - User account linking with secure state management
 * - OAuth callback processing with error handling
 * - Account status verification with retry mechanisms
 * - Simplified shortcodes and AJAX endpoints
 *
 * @version 2.0
 */

/**
 * Discord OAuth settings and operation constants
 */
define('GTAW_DISCORD_OAUTH_VERSION', '2.0');
define('GTAW_DISCORD_OAUTH_STATE_EXPIRATION', 10 * MINUTE_IN_SECONDS);
define('GTAW_DISCORD_AUTH_SCOPES', 'identify');
define('GTAW_DISCORD_API_BASE', 'https://discord.com/api/v10');

/* ========= INITIALIZATION ========= */

/**
 * Register Discord OAuth endpoints and AJAX handlers
 */
function gtaw_discord_oauth_init() {
    // Add rewrite endpoint for Discord page
    add_rewrite_endpoint('discord', EP_ROOT | EP_PAGES);
    
    // Register AJAX handlers
    add_action('wp_ajax_gtaw_discord_unlink_account', 'gtaw_discord_unlink_account_callback');
    add_action('wp_ajax_gtaw_check_discord_membership', 'gtaw_check_discord_server_membership');
}
add_action('init', 'gtaw_discord_oauth_init');

/**
 * Add 'discord' to the list of query vars
 *
 * @param array $vars Query vars
 * @return array Updated query vars
 */
function gtaw_discord_query_vars($vars) {
    $vars[] = 'discord';
    return $vars;
}
add_filter('query_vars', 'gtaw_discord_query_vars', 0);

/**
 * Add a new item to the WooCommerce My Account navigation menu
 *
 * @param array $items Menu items
 * @return array Updated menu items
 */
function gtaw_add_discord_link_my_account($items) {
    $items['discord'] = 'Discord Settings';
    return $items;
}
add_filter('woocommerce_account_menu_items', 'gtaw_add_discord_link_my_account');

/**
 * Content for the WooCommerce "Discord" endpoint
 */
function gtaw_discord_endpoint_content() {
    if (!is_user_logged_in()) {
        echo '<p>You must be logged in to manage your Discord account.</p>';
        return;
    }
    
    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    
    echo '<h2>Discord Account Integration</h2>';
    
    if ($discord_id) {
        // Always force a fresh check when the page loads directly (not AJAX)
        // This ensures we get accurate server membership status
        $is_in_server = gtaw_is_user_in_discord_server($discord_id, true);
        
        echo '<div class="discord-account-card" style="background: #f5f5f5; border-radius: 5px; padding: 15px; margin-bottom: 20px;">';
        echo '<p><strong>Status:</strong> Your account is linked to Discord</p>';
        
        // Only show Discord ID if debug mode is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<p><strong>Discord ID:</strong> ' . esc_html($discord_id) . '</p>';
        }
        
        // Show server membership status with refresh button
        echo '<div id="discord-server-status">';
        if ($is_in_server) {
            echo '<p><strong>Server Membership:</strong> <span id="membership-status" style="color: green;">✓ You are a member of our Discord server</span></p>';
        } else {
            $invite_link = get_option('gtaw_discord_invite_link', '');
            echo '<p><strong>Server Membership:</strong> <span id="membership-status" style="color: red;">❌ You are not a member of our Discord server</span></p>';
            
            if (!empty($invite_link)) {
                echo '<p><a href="https://discord.gg/' . esc_attr($invite_link) . '" target="_blank" rel="noopener noreferrer" class="button">Join Our Discord Server</a></p>';
            }
        }
        echo '<button type="button" id="check-discord-membership" class="button" style="margin-top: 10px;">Refresh Membership Status</button>';
        echo '<span id="membership-check-status" style="display: inline-block; margin-left: 10px;"></span>';
        echo '</div>';
        
        // Add JavaScript to handle membership status check
        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                $("#check-discord-membership").on("click", function() {
                    const $button = $(this);
                    const $status = $("#membership-check-status");
                    
                    $button.prop("disabled", true).text("Checking...");
                    $status.html("<em>Verifying your server membership...</em>");
                    
                    $.ajax({
                        url: "' . admin_url('admin-ajax.php') . '",
                        type: "POST",
                        data: {
                            action: "gtaw_check_discord_membership"
                        },
                        success: function(response) {
                            if (response.success) {
                                if (response.data.is_member) {
                                    $("#membership-status").css("color", "green").html("✓ You are a member of our Discord server");
                                    $status.html("<span style=\"color: green;\">✓ Membership confirmed!</span>");
                                } else {
                                    $("#membership-status").css("color", "red").html("❌ You are not a member of our Discord server");
                                    $status.html("<span style=\"color: red;\">Membership check complete - you\'re not in the server.</span>");
                                }
                            } else {
                                $status.html("<span style=\"color: red;\">Error checking membership.</span>");
                            }
                            $button.prop("disabled", false).text("Refresh Membership Status");
                        },
                        error: function() {
                            $status.html("<span style=\"color: red;\">Connection error. Please try again.</span>");
                            $button.prop("disabled", false).text("Refresh Membership Status");
                        }
                    });
                });
            });
        </script>';
        
        
        echo '</div>';
        
        // Display the unlink button using our shortcode
        echo do_shortcode('[gtaw_discord_buttons]');
        
    } else {
        echo '<p>Link your Discord account to enable Discord notifications and features.</p>';
        
        // If not linked, show the shortcode (which outputs the Link Discord Account button)
        echo do_shortcode('[gtaw_discord_buttons]');
        
        // Show joining instructions
        $invite_link = get_option('gtaw_discord_invite_link', '');
        if (!empty($invite_link)) {
            echo '<div style="margin-top: 20px;">';
            echo '<h3>How to Connect Your Discord Account</h3>';
            echo '<ol>';
            echo '<li>Click the "Link Discord Account" button above</li>';
            echo '<li>Authorize the connection in the Discord popup</li>';
            echo '<li>Join our Discord server if you haven\'t already: <a href="https://discord.gg/' . esc_attr($invite_link) . '" target="_blank" rel="noopener noreferrer">Join Server</a></li>';
            echo '</ol>';
            echo '</div>';
        }
    }
}
add_action('woocommerce_account_discord_endpoint', 'gtaw_discord_endpoint_content');

/* ========= DISCORD OAUTH FLOW ========= */

/**
 * Generate a secure OAuth state parameter and store it in user meta
 *
 * @param int $user_id WordPress user ID
 * @return string OAuth state parameter
 */
function gtaw_discord_generate_oauth_state($user_id) {
    // Generate a random state value
    $state = wp_generate_password(32, false);
    
    // Store the state in user meta with an expiration time
    update_user_meta($user_id, 'discord_oauth_state', [
        'value' => $state,
        'expires' => time() + GTAW_DISCORD_OAUTH_STATE_EXPIRATION
    ]);
    
    return $state;
}

/**
 * Verify the OAuth state parameter
 *
 * @param int $user_id WordPress user ID
 * @param string $state State parameter to verify
 * @return bool Whether the state is valid
 */
function gtaw_discord_verify_oauth_state($user_id, $state) {
    $stored_state = get_user_meta($user_id, 'discord_oauth_state', true);
    
    // Check if state exists and is valid
    if (empty($stored_state) || empty($stored_state['value']) || empty($stored_state['expires'])) {
        return false;
    }
    
    // Check if state has expired
    if (time() > $stored_state['expires']) {
        delete_user_meta($user_id, 'discord_oauth_state');
        return false;
    }
    
    // Verify the state value
    $is_valid = hash_equals($stored_state['value'], $state);
    
    // Clean up the state after use
    delete_user_meta($user_id, 'discord_oauth_state');
    
    return $is_valid;
}

/**
 * Get the Discord OAuth authorization URL with proper scopes and state
 *
 * @return string|false Authorization URL or false on failure
 */
function gtaw_get_discord_auth_url() {
    if (!is_user_logged_in()) {
        return false;
    }
    
    $client_id = get_option('gtaw_discord_client_id', '');
    if (empty($client_id)) {
        gtaw_add_log('discord', 'Error', 'Discord Client ID is missing', 'error');
        return false;
    }
    
    $redirect_uri = site_url('?discord_oauth=callback');
    
    // Generate and store a state parameter for security
    $state = gtaw_discord_generate_oauth_state(get_current_user_id());
    
    // Build the authorization URL with required parameters
    return add_query_arg([
        'client_id' => $client_id,
        'redirect_uri' => urlencode($redirect_uri),
        'response_type' => 'code',
        'scope' => GTAW_DISCORD_AUTH_SCOPES,
        'state' => $state,
        'prompt' => 'consent' // Always show consent screen for better UX
    ], 'https://discord.com/api/oauth2/authorize');
}

/**
 * Handle Discord OAuth callback with improved error handling
 */
function gtaw_handle_discord_oauth_callback() {
    // Only process if this is our callback
    if (!isset($_GET['discord_oauth']) || $_GET['discord_oauth'] !== 'callback') {
        return;
    }
    
    // Start timing the process for performance monitoring
    $start_time = microtime(true);
    
    // Default redirect location
    $redirect_location = wc_get_account_endpoint_url('discord');
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        gtaw_add_log('discord', 'Error', "Discord OAuth callback - User not logged in", 'error');
        
        wc_add_notice('You must be logged in to link your Discord account.', 'error');
        wp_redirect($redirect_location);
        exit;
    }
    
    $user_id = get_current_user_id();
    
    // Check for error response from Discord
    if (isset($_GET['error'])) {
        $error = sanitize_text_field($_GET['error']);
        $error_description = isset($_GET['error_description']) ? sanitize_text_field($_GET['error_description']) : 'Unknown error';
        
        gtaw_add_log('discord', 'Error', "Discord OAuth error: {$error} - {$error_description}", 'error');
        
        // Add a user-friendly notice
        switch ($error) {
            case 'access_denied':
                wc_add_notice('You denied the request to link your Discord account.', 'error');
                break;
            default:
                wc_add_notice("Error linking Discord account: {$error_description}", 'error');
                break;
        }
        
        wp_redirect($redirect_location);
        exit;
    }
    
    // Check for authorization code
    if (!isset($_GET['code'])) {
        gtaw_add_log('discord', 'Error', "Discord OAuth callback - Missing authorization code", 'error');
        
        wc_add_notice('Missing authorization code from Discord.', 'error');
        wp_redirect($redirect_location);
        exit;
    }
    
    // Verify state parameter
    if (!isset($_GET['state']) || !gtaw_discord_verify_oauth_state($user_id, $_GET['state'])) {
        gtaw_add_log('discord', 'Error', "Discord OAuth callback - Invalid state parameter", 'error');
        
        wc_add_notice('Security check failed. Please try again.', 'error');
        wp_redirect($redirect_location);
        exit;
    }
    
    $code = sanitize_text_field($_GET['code']);
    $client_id = get_option('gtaw_discord_client_id', '');
    $client_secret = get_option('gtaw_discord_client_secret', '');
    $redirect_uri = site_url('?discord_oauth=callback');
    
    // Validate client credentials
    if (empty($client_id) || empty($client_secret)) {
        gtaw_add_log('discord', 'Error', "Discord OAuth callback - Missing client credentials", 'error');
        
        wc_add_notice('Discord integration is not properly configured.', 'error');
        wp_redirect($redirect_location);
        exit;
    }
    
    // Exchange the authorization code for an access token
    $token_response = wp_remote_post(GTAW_DISCORD_API_BASE . '/oauth2/token', [
        'body' => [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect_uri,
        ],
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ],
        'timeout' => 15 // Increased timeout for reliability
    ]);
    
    if (is_wp_error($token_response)) {
        gtaw_add_log('discord', 'Error', "Discord OAuth token request failed: " . $token_response->get_error_message(), 'error');
        
        wc_add_notice('Error communicating with Discord. Please try again later.', 'error');
        wp_redirect($redirect_location);
        exit;
    }
    
    $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
    if (empty($token_body['access_token'])) {
        // Get error details if available
        $error_message = isset($token_body['error_description']) ? $token_body['error_description'] : 'No access token returned';
        gtaw_add_log('discord', 'Error', "Discord OAuth token error: {$error_message}", 'error');
        
        wc_add_notice('Error linking Discord account. Please try again.', 'error');
        wp_redirect($redirect_location);
        exit;
    }
    
    $access_token = sanitize_text_field($token_body['access_token']);
    
    // Retrieve the Discord user data
    $user_response = wp_remote_get(GTAW_DISCORD_API_BASE . '/users/@me', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
        ],
        'timeout' => 15
    ]);
    
    if (is_wp_error($user_response)) {
        gtaw_add_log('discord', 'Error', "Discord user data request failed: " . $user_response->get_error_message(), 'error');
        
        wc_add_notice('Error retrieving your Discord profile. Please try again.', 'error');
        wp_redirect($redirect_location);
        exit;
    }
    
    $user_body = json_decode(wp_remote_retrieve_body($user_response), true);
    if (empty($user_body['id'])) {
        gtaw_add_log('discord', 'Error', "Discord user data missing ID: " . wp_json_encode($user_body), 'error');
        
        wc_add_notice('Your Discord profile information is incomplete. Please try again.', 'error');
        wp_redirect($redirect_location);
        exit;
    }
    
    // Get Discord user info for logging
    $discord_id = sanitize_text_field($user_body['id']);
    $discord_username = isset($user_body['username']) ? sanitize_text_field($user_body['username']) : 'Unknown';
    
    // Check if this Discord account is already linked to another user
    $existing_users = get_users([
        'meta_key' => 'discord_ID',
        'meta_value' => $discord_id,
        'exclude' => [$user_id],
        'fields' => 'ids'
    ]);
    
    if (!empty($existing_users)) {
        gtaw_add_log('discord', 'Error', "Discord account (ID: {$discord_id}, Username: {$discord_username}) already linked to another user", 'error');
        
        wc_add_notice('This Discord account is already linked to another user on this site.', 'error');
        wp_redirect($redirect_location);
        exit;
    }
    
    // Save the Discord user ID to the current user's meta
    update_user_meta($user_id, 'discord_ID', $discord_id);
    
    // Save additional Discord info for better user management
    update_user_meta($user_id, 'discord_username', $discord_username);
    
    // Optionally store Discord avatar for future use
    if (isset($user_body['avatar'])) {
        update_user_meta($user_id, 'discord_avatar', sanitize_text_field($user_body['avatar']));
    }
    
    // Calculate total process time for monitoring
    $process_time = microtime(true) - $start_time;
    
    // Log successful linking with performance data
    $username = wp_get_current_user()->user_login;
    gtaw_add_log(
        'discord',
        'Link',
        sprintf(
            "User %s linked their Discord account (ID: %s, Username: %s) in %.2f seconds",
            $username,
            $discord_id,
            $discord_username,
            $process_time
        ),
        'success'
    );
    
    // Trigger role sync after linking Discord account
    gtaw_discord_trigger_account_linked($user_id, $discord_id);
    
    // Add success message
    wc_add_notice('Your Discord account has been successfully linked!', 'success');
    
    // Redirect back to the Discord settings page
    wp_redirect($redirect_location);
    exit;
}
add_action('init', 'gtaw_handle_discord_oauth_callback', 20); // Higher priority to run after other init hooks

/* ========= AJAX ENDPOINTS ========= */

/**
 * AJAX handler to unlink Discord account with proper security
 */
function gtaw_discord_unlink_account_callback() {
    // Start monitoring execution time
    $start_time = microtime(true);
    
    // Verify user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to unlink your Discord account.');
        return;
    }
    
    // Verify nonce for security
    if (!check_ajax_referer('gtaw_discord_unlink_nonce', 'nonce', false)) {
        gtaw_add_log('discord', 'Security', 'Invalid nonce in unlink request', 'error');
        wp_send_json_error('Security check failed. Please refresh the page and try again.');
        return;
    }
    
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    
    // Get Discord ID and username before unlinking for logging
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    $discord_username = get_user_meta($user_id, 'discord_username', true) ?: 'Unknown';
    
    // Remove all Discord-related user meta
    delete_user_meta($user_id, 'discord_ID');
    delete_user_meta($user_id, 'discord_username');
    delete_user_meta($user_id, 'discord_avatar');
    
    // Clear any Discord-related caches for this user
    if (!empty($discord_id)) {
        gtaw_clear_discord_user_cache($discord_id);
    }
    
    // Calculate process time
    $process_time = microtime(true) - $start_time;
    
    // Log successful unlinking with timing
    gtaw_add_log(
        'discord',
        'Unlink',
        sprintf(
            "User %s unlinked their Discord account (ID: %s, Username: %s) in %.2f seconds",
            $user->user_login,
            $discord_id ?: 'Unknown',
            $discord_username,
            $process_time
        ),
        'success'
    );
    
    // Send success response
    wp_send_json_success('Discord account successfully unlinked.');
}

/**
 * AJAX endpoint to check Discord server membership with timeout handling
 */
function gtaw_check_discord_server_membership() {
    // Verify user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    
    if (empty($discord_id)) {
        wp_send_json_error([
            'message' => 'No Discord account linked',
            'is_member' => false
        ]);
    }
    
    // Clear all Discord-related caches for this user to ensure a completely fresh check
    gtaw_clear_discord_user_cache($discord_id);
    
    // Set a shorter timeout for this specific API call
    add_filter('http_request_timeout', function($timeout) {
        return 5; // 5 seconds timeout for faster user feedback
    });
    
    // Check membership status with forced refresh
    $is_member = gtaw_is_user_in_discord_server($discord_id, true);
    
    // Log the verification result
    if ($is_member) {
        gtaw_add_log('discord', 'Verification', "User $user_id confirmed Discord server membership", 'success');
    } else {
        gtaw_add_log('discord', 'Verification', "User $user_id is not a member of the Discord server", 'warning');
    }
    
    // If not a member, also check if we can still get the user information from Discord API
    // This helps distinguish between "left server" and "invalid token/Discord ID"
    $discord_user_valid = false;
    if (!$is_member) {
        // Try to get user's basic info from Discord without checking server membership
        // This ensures the Discord ID itself is still valid
        $bot_token = get_option('gtaw_discord_bot_token', '');
        if (!empty($bot_token)) {
            $response = wp_remote_get(GTAW_DISCORD_API_BASE . "/users/{$discord_id}", [
                'headers' => [
                    'Authorization' => 'Bot ' . $bot_token
                ],
                'timeout' => 5
            ]);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $discord_user_valid = true;
            }
        }
    }
    
    wp_send_json_success([
        'is_member' => $is_member,
        'discord_id' => $discord_id,
        'discord_account_valid' => $is_member || $discord_user_valid
    ]);
}

/* ========= SHORTCODES ========= */

/**
 * Shortcode that displays either the link or unlink button
 *
 * @return string HTML for the button
 */
function gtaw_discord_buttons_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to manage your Discord account.</p>';
    }
    
    // Check if current user is an administrator - hide buttons for admins
    $current_user = wp_get_current_user();
    if (in_array('administrator', (array) $current_user->roles)) {
        return ''; // Return empty for admins - no buttons shown
    }
    
    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    
    // Common button styling
    $button_style = 'display: inline-block; padding: 10px 15px; background: #5865F2; color: white; border-radius: 5px; text-decoration: none; font-weight: bold; margin-top: 10px; text-align: center; border: none; cursor: pointer;';
    
    // Add hover effect with inline JS
    $hover_script = "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var discordButtons = document.querySelectorAll('.discord-button');
        
        discordButtons.forEach(function(button) {
            button.addEventListener('mouseover', function() {
                this.style.backgroundColor = '#4752C4';
            });
            
            button.addEventListener('mouseout', function() {
                this.style.backgroundColor = '#5865F2';
            });
        });
    });
    </script>
    ";
    
    if ($discord_id) {
        // Unlink state - show confirmation dialog
        $nonce = wp_create_nonce('gtaw_discord_unlink_nonce');
        $output = '<button id="discord-unlink" class="discord-button" style="' . esc_attr($button_style) . '" data-nonce="' . esc_attr($nonce) . '">Unlink Discord Account</button>';
        $output .= '
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $("#discord-unlink").on("click", function(e) {
                    e.preventDefault();
                    
                    if (!confirm("Are you sure you want to unlink your Discord account? This will disable Discord notifications and features.")) {
                        return;
                    }
                    
                    var nonce = $(this).data("nonce");
                    var $button = $(this);
                    
                    $button.prop("disabled", true).text("Unlinking...");
                    
                    $.post("' . admin_url("admin-ajax.php") . '", {
                        action: "gtaw_discord_unlink_account",
                        nonce: nonce
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert("Error: " + response.data);
                            $button.prop("disabled", false).text("Unlink Discord Account");
                        }
                    }).fail(function() {
                        alert("An unexpected error occurred. Please try again.");
                        $button.prop("disabled", false).text("Unlink Discord Account");
                    });
                });
            });
        </script>' . $hover_script;
    } else {
        // Link state - redirect to Discord OAuth
        $auth_url = gtaw_get_discord_auth_url();
        if (empty($auth_url)) {
            $output = '<p>Discord integration is not configured. Please contact the site administrator.</p>';
        } else {
            $discord_logo = '<svg style="height: 16px; width: 16px; margin-right: 8px; vertical-align: text-bottom;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" fill="#fff"><path d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>';
            $output = '<a href="' . esc_url($auth_url) . '" id="discord-link" class="discord-button" style="' . esc_attr($button_style) . '">' . $discord_logo . 'Link Discord Account</a>' . $hover_script;
        }
    }
    
    return $output;
}
add_shortcode('gtaw_discord_buttons', 'gtaw_discord_buttons_shortcode');

/**
 * Flush rewrite rules when the Discord module is enabled/disabled
 * Only runs when the option actually changes
 *
 * @param string $option Option name
 * @param mixed $old_value Old option value
 * @param mixed $value New option value
 */
function gtaw_handle_discord_module_toggle($option, $old_value, $value) {
    if ($option === 'gtaw_discord_enabled' && $old_value !== $value) {
        flush_rewrite_rules();
        gtaw_add_log('discord', 'System', 'Flushed rewrite rules after Discord module status change', 'success');
    }
}
add_action('update_option', 'gtaw_handle_discord_module_toggle', 10, 3);