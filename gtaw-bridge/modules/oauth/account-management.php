<?php
defined('ABSPATH') or exit;

// Profile extra fields, character shortcode, login/register tweaks, WC email.

function gtaw_add_user_profile_fields($user) {
    gtaw_perf_start('profile_fields_render');
    
    $gtaw_user_id = get_user_meta($user->ID, 'gtaw_user_id', true);
    $character = get_user_meta($user->ID, 'active_gtaw_character', true);

    $available_characters = get_user_meta($user->ID, 'gtaw_available_characters', true);

    if (empty($gtaw_user_id)) {
        gtaw_perf_end('profile_fields_render');
        return;
    }
    
    $connection_time = get_user_meta($user->ID, 'gtaw_last_connection', true);
    $connection_status = empty($connection_time) ? 'Unknown' : human_time_diff($connection_time) . ' ago';
    
    ?>
    <h3><span class="dashicons dashicons-admin-users" style="color: #2271b1;"></span> GTA World Character Information</h3>
    <table class="form-table">
        <tr>
            <th><label for="gtaw_user_id">GTA:W User ID</label></th>
            <td>
                <input type="text" name="gtaw_user_id" id="gtaw_user_id" value="<?php echo esc_attr($gtaw_user_id); ?>" class="regular-text" readonly />
                <p class="description">This is the user's GTA:W UCP ID.</p>
            </td>
        </tr>
        <tr>
            <th><label>Last Connection</label></th>
            <td>
                <span><?php echo esc_html($connection_status); ?></span>
                <p class="description">Last time the user authenticated with GTA:W.</p>
            </td>
        </tr>
        <?php if (!empty($character)): ?>
        <tr>
            <th><label>Active Character</label></th>
            <td>
                <div style="background: #f0f7ff; padding: 10px 15px; border-left: 4px solid #2271b1; margin-bottom: 10px;">
                    <p style="margin-top: 0;"><strong>Name:</strong> <?php echo esc_html($character['firstname'] . ' ' . $character['lastname']); ?></p>
                    <p style="margin-bottom: 0;"><strong>Character ID:</strong> <?php echo esc_html($character['id']); ?></p>
                </div>
                <?php if (!empty($available_characters) && count($available_characters) > 1): ?>
                    <p class="description">This user has <?php echo count($available_characters); ?> characters available for switching.</p>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
        
        <?php if (current_user_can('manage_options')): ?>
        <tr>
            <th><label>Account Actions</label></th>
            <td>
                <button type="button" class="button" onclick="if(confirm('Are you sure you want to reset this user\'s GTA:W connection? This will remove all character data.')) { gtaw_reset_connection(<?php echo esc_js($user->ID); ?>); }">Reset GTA:W Connection</button>
                <span id="gtaw-reset-status"></span>
                
                <script>
                function gtaw_reset_connection(user_id) {
                    jQuery.post(ajaxurl, {
                        action: 'gtaw_admin_reset_connection',
                        user_id: user_id,
                        nonce: '<?php echo wp_create_nonce('gtaw_admin_reset_connection'); ?>'
                    }, function(response) {
                        if (response.success) {
                            jQuery('#gtaw-reset-status').html('<span style="color:green;margin-left:10px;">' + response.data + '</span>');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            jQuery('#gtaw-reset-status').html('<span style="color:red;margin-left:10px;">' + response.data + '</span>');
                        }
                    });
                }
                </script>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    <?php
    gtaw_perf_end('profile_fields_render');
}
add_action('show_user_profile', 'gtaw_add_user_profile_fields');
add_action('edit_user_profile', 'gtaw_add_user_profile_fields');

function gtaw_admin_reset_connection_callback() {
    if (!gtaw_ajax_security_check('oauth', 'nonce', 'gtaw_admin_reset_connection', 'manage_options', 'reset connection')) {
        return;
    }
    
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if (empty($user_id)) {
        wp_send_json_error('Invalid user ID');
        return;
    }
    
    $result = gtaw_reset_gtaw_connection($user_id);
    
    if ($result) {
        wp_send_json_success('Connection reset successfully');
    } else {
        wp_send_json_error('Failed to reset connection');
    }
}
add_action('wp_ajax_gtaw_admin_reset_connection', 'gtaw_admin_reset_connection_callback');

function gtaw_character_info_shortcode($atts = []) {
    $atts = shortcode_atts([
        'style' => 'default', // default, compact, or expanded
        'show_id' => 'yes',
        'show_connection' => 'no'
    ], $atts, 'gtaw_character_info');
    
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your character information.</p>';
    }
    gtaw_perf_start('character_info_shortcode');
    
    $user_id = get_current_user_id();
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    if (empty($character)) {
        gtaw_perf_end('character_info_shortcode');
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
            $output .= '<div class="gtaw-character-compact">';
            $output .= '<span class="gtaw-character-name">' . $fullname . '</span>';
            if ($atts['show_id'] === 'yes') {
                $output .= ' <span class="gtaw-character-id">(ID: ' . $character_id . ')</span>';
            }
            $output .= '</div>';
            break;
            
        case 'expanded':
            $output .= '<div class="gtaw-character-expanded">';
            $output .= '<h3 class="gtaw-character-title">Your GTA:W Character</h3>';
            $output .= '<div class="gtaw-character-card">';
            $output .= '<div class="gtaw-character-avatar"><span class="dashicons dashicons-businessman"></span></div>';
            $output .= '<div class="gtaw-character-details">';
            $output .= '<h4>' . $fullname . '</h4>';
            if ($atts['show_id'] === 'yes') {
                $output .= '<p><strong>Character ID:</strong> ' . $character_id . '</p>';
            }
            $output .= $connection_html;
            $output .= '</div></div></div>';
            
            // Add some inline CSS for the expanded style
            $output .= '<style>
                .gtaw-character-expanded { background: #f8f8f8; padding: 15px; border-radius: 5px; }
                .gtaw-character-card { display: flex; align-items: center; }
                .gtaw-character-avatar { margin-right: 15px; }
                .gtaw-character-avatar .dashicons { font-size: 48px; width: 48px; height: 48px; color: #2271b1; }
                .gtaw-character-details h4 { margin: 0 0 10px 0; }
                .gtaw-character-details p { margin: 5px 0; }
            </style>';
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
    gtaw_perf_end('character_info_shortcode');
    
    return $output;
}

function gtaw_block_wp_registration() {
    if (gtaw_oauth_get_setting('enabled', true)) {
        global $pagenow;
        if ($pagenow == 'wp-login.php' && isset($_GET['action']) && $_GET['action'] == 'register') {
            $login_link = gtaw_get_oauth_url();

            if (!empty($login_link)) {
                wp_redirect(add_query_arg('registration_method', 'gtaw', $login_link));
                exit;
            }
        }
    }
}
add_action('init', 'gtaw_block_wp_registration');

function gtaw_modify_login_form() {
    if (gtaw_oauth_get_setting('enabled', true)) {
        $login_link = gtaw_get_oauth_url();
        
        if (!empty($login_link)) {
            if (isset($_GET['registration_method']) && $_GET['registration_method'] === 'gtaw') {
                echo '<div class="message" style="background: #f8f8f8; border-left: 4px solid #2271b1; padding: 10px 15px; margin: 20px 0; border-radius: 3px;">';
                echo 'Standard registration is disabled. Please use GTA:W authentication to create an account.';
                echo '</div>';
            }
            
            echo '<div style="text-align: center; margin: 20px 0; padding: 10px; background: #f8f8f8; border-radius: 4px;">';
            echo '<p style="margin-bottom: 10px; font-size: 14px;">Log in with your GTA:W account:</p>';
            echo '<a href="' . esc_url($login_link) . '" class="button button-primary" style="margin: 0 auto; display: inline-block; padding: 5px 15px; background: #0085ba; color: white; text-decoration: none; border-radius: 3px; border: 1px solid #006799; font-size: 13px; line-height: 2; width: 100%; max-width: 240px; box-sizing: border-box; text-align: center;">';
            echo '<span style="vertical-align: middle; margin-right: 5px; display: inline-block; width: 16px; height: 16px;">🔑</span> Login with GTA:W';
            echo '</a>';
            echo '</div>';
        }
    }
}
add_action('login_form', 'gtaw_modify_login_form');
add_action('register_form', 'gtaw_modify_login_form');

function gtaw_add_woocommerce_dashboard_widget() {
    // legacy: superseded by gtaw_add_character_switcher when thats hooked

    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) {
        return;
    }
    
    if (has_action('woocommerce_account_dashboard', 'gtaw_add_character_switcher')) {
        return;
    }
    
    $user_id = get_current_user_id();
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    
    if (empty($character)) {
        return;
    }
    gtaw_perf_start('dashboard_widget');
    
    ?>
    <div class="woocommerce-MyAccount-content-widget gtaw-character-widget" style="margin-bottom: 30px; padding: 20px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 5px;">
        <h4 style="margin-top: 0; display: flex; align-items: center;">
            <span class="dashicons dashicons-admin-users" style="color: #2271b1; margin-right: 8px;"></span>
            Your GTA:W Character
        </h4>
        
        <div style="background: #fff; padding: 15px; border: 1px solid #eee; border-radius: 4px; margin-bottom: 15px;">
            <p style="margin-top: 0;"><strong>Name:</strong> <?php echo esc_html($character['firstname'] . ' ' . $character['lastname']); ?></p>
            <p style="margin-bottom: 0;"><strong>Character ID:</strong> <?php echo esc_html($character['id']); ?></p>
        </div>
        
        <?php if (!empty($available_characters) && is_array($available_characters) && count($available_characters) > 1): ?>
            <button type="button" class="button toggle-character-selector" style="margin-bottom: 10px;">
                <span class="dashicons dashicons-randomize" style="vertical-align: middle; margin: 0 5px 0 -5px;"></span>
                Switch Character
            </button>
            
            <div class="character-selector-container" style="display: none; margin-top: 15px; background: #f0f7ff; padding: 15px; border-radius: 4px;">
                <h5 style="margin-top: 0; margin-bottom: 10px;">Select Character</h5>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                    <?php foreach ($available_characters as $char): ?>
                        <?php 
                        // Skip if missing required fields
                        if (empty($char['id']) || empty($char['firstname']) || empty($char['lastname'])) {
                            continue;
                        }
                        
                        // Skip current character
                        if ($char['id'] == $character['id']) {
                            continue;
                        }
                        ?>
                        <div style="background: #fff; padding: 10px; border-radius: 3px; border: 1px solid #d5e5ff;">
                            <form method="post" action="">
                                <?php wp_nonce_field('gtaw_switch_character', 'gtaw_character_nonce'); ?>
                                <input type="hidden" name="gtaw_switch_character" value="1">
                                <input type="hidden" name="character_id" value="<?php echo esc_attr($char['id']); ?>">
                                <input type="hidden" name="character_firstname" value="<?php echo esc_attr($char['firstname']); ?>">
                                <input type="hidden" name="character_lastname" value="<?php echo esc_attr($char['lastname']); ?>">
                                <button type="submit" class="button" style="width: 100%; text-align: center;">
                                    <?php echo esc_html($char['firstname'] . ' ' . $char['lastname']); ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('.toggle-character-selector').on('click', function() {
                    $('.character-selector-container').slideToggle();
                    $(this).toggleClass('active');
                    
                    if ($(this).hasClass('active')) {
                        $(this).html('<span class="dashicons dashicons-arrow-up-alt2" style="vertical-align: middle; margin: 0 5px 0 -5px;"></span> Hide Characters');
                    } else {
                        $(this).html('<span class="dashicons dashicons-randomize" style="vertical-align: middle; margin: 0 5px 0 -5px;"></span> Switch Character');
                    }
                });
            });
            </script>
        <?php else: ?>
            <!-- This user only has one character in GTA:W or we couldn't retrieve multiple characters -->
            <p>
                <em>To switch to a different character, you'll need to log out first.</em>
            </p>
            <form method="post" action="">
                <?php wp_nonce_field('gtaw_logout_and_switch', 'gtaw_switch_nonce'); ?>
                <input type="hidden" name="gtaw_logout_and_switch" value="1">
                <button type="submit" class="button" style="display: flex; align-items: center;">
                    <span class="dashicons dashicons-exit" style="margin-right: 5px;"></span>
                    Log Out & Switch Character
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php
    gtaw_perf_end('dashboard_widget');
}
add_action('woocommerce_account_dashboard', 'gtaw_add_woocommerce_dashboard_widget');

/**
 * Check if a WordPress user is linked to a GTA:W account with caching
 *
 * @param int $user_id The WordPress user ID to check
 * @return bool True if user is linked to GTA:W
 * @since 2.0 Added caching for performance
 */
function gtaw_is_user_linked_to_gtaw($user_id) {
    // Generate cache key
    $cache_key = 'gtaw_user_linked_' . $user_id;
    
    // Check cache first
    $cached = wp_cache_get($cache_key, 'gtaw');
    if ($cached !== false) {
        return $cached;
    }
    
    // Get user meta
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    // Determine if linked
    $is_linked = !empty($gtaw_user_id) && !empty($character);
    
    // Cache the result (short TTL since this is a critical status)
    wp_cache_set($cache_key, $is_linked, 'gtaw', 60); // Cache for 1 minute
    
    return $is_linked;
}

function gtaw_reset_gtaw_connection($user_id) {
    gtaw_perf_start('reset_connection');
    
    if (!current_user_can('edit_users') && get_current_user_id() != $user_id) {
        gtaw_add_log('oauth', 'Security', "Unauthorized attempt to reset connection for user ID: $user_id", 'error');
        gtaw_perf_end('reset_connection');
        return false;
    }
    
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    delete_user_meta($user_id, 'gtaw_user_id');
    delete_user_meta($user_id, 'active_gtaw_character');
    delete_user_meta($user_id, 'gtaw_available_characters');
    delete_user_meta($user_id, 'gtaw_last_connection');
    
    wp_cache_delete('gtaw_user_linked_' . $user_id, 'gtaw');

    $character_info = '';
    if (!empty($character) && isset($character['firstname']) && isset($character['lastname'])) {
        $character_info = " ({$character['firstname']} {$character['lastname']}, ID: {$character['id']})";
    }
    
    gtaw_add_log(
        'oauth', 
        'Reset', 
        "GTA:W connection reset for user ID: $user_id, GTA:W ID: $gtaw_user_id$character_info", 
        'success'
    );
    gtaw_perf_end('reset_connection');
    
    return true;
}


/**
 * Remove password fields from account details form with better organization
 * 
 * @param array $fields The form fields
 * @return array Modified fields
 */
function gtaw_remove_account_password_fields($fields) {
    unset($fields['account_password']);
    unset($fields['account_password-2']);
    return $fields;
}
add_filter('woocommerce_account_details_form_fields', 'gtaw_remove_account_password_fields', 10, 1);

/**
 * Remove password fields from required fields
 * 
 * @param array $required_fields The required fields
 * @return array Modified required fields
 */
function gtaw_remove_required_password_fields($required_fields) {
    unset($required_fields['password_1']);
    unset($required_fields['password_2']);
    return $required_fields;
}
add_filter('woocommerce_save_account_details_required_fields', 'gtaw_remove_required_password_fields', 10, 1);

/**
 * Prevent password validation with high priority
 */
function gtaw_prevent_password_validation() {
    add_filter('woocommerce_save_account_details_required_fields', '__return_empty_array', 999);
}
add_action('woocommerce_save_account_details', 'gtaw_prevent_password_validation', 1);

/**
 * Remove password strength meter script for better performance
 */
function gtaw_remove_password_strength_meter() {
    if (function_exists('is_account_page') && is_account_page()) {
        wp_dequeue_script('wc-password-strength-meter');
    }
}
add_action('wp_print_scripts', 'gtaw_remove_password_strength_meter', 100);

/**
 * Add custom CSS to hide password fields (as a fallback)
 */
function gtaw_hide_account_password_fields() {
    if (function_exists('is_account_page') && is_account_page()) {
        ?>
        <style>
            .woocommerce-EditAccountForm fieldset {
                display: none !important;
            }
        </style>
        <?php
    }
}
add_action('wp_head', 'gtaw_hide_account_password_fields');

/**
 * Set email field from Account Details "readonly" with proper event timing
 */
function gtaw_make_email_field_readonly() {
    // Only for account fields on account page
    if (function_exists('is_account_page') && is_account_page()) {
        ?>
        <script type='text/javascript'>
        jQuery(function($) {
            // Use MutationObserver to ensure field exists even after AJAX updates
            if (typeof MutationObserver !== 'undefined') {
                const observer = new MutationObserver(function() {
                    const emailField = $('form.edit-account input#account_email');
                    if (emailField.length && !emailField.prop('readonly')) {
                        emailField.prop('readonly', true);
                        emailField.after('<p class="description">This email is based on your GTA:W character and cannot be changed.</p>');
                    }
                });
                
                observer.observe(document.body, { childList: true, subtree: true });
            } else {
                // Fallback for browsers without MutationObserver
                $('form.edit-account input#account_email').prop('readonly', true);
            }
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'gtaw_make_email_field_readonly');

/**
 * Set email field from Checkout "readonly" with improved attributes
 * 
 * @param array $fields The billing fields
 * @return array Modified fields
 */
function gtaw_make_billing_email_readonly($fields) {
    if (isset($fields['billing_email'])) {
        $fields['billing_email']['custom_attributes'] = [
            'readonly' => 'readonly'
        ];
        $fields['billing_email']['description'] = 'This email is based on your GTA:W character and cannot be changed.';
    }
    return $fields;
}
add_filter('woocommerce_billing_fields', 'gtaw_make_billing_email_readonly', 20, 1);

/**
 * Register the notification status endpoint to fix Ajax fragment loading
 * 
 * @since 2.0 New function to prevent 404 errors with account fragments
 */
function gtaw_register_user_endpoints() {
    add_rewrite_endpoint('notification-status', EP_PAGES);
}
add_action('init', 'gtaw_register_user_endpoints');

/**
 * Flush rewrite rules on plugin activation to register endpoints
 * 
 * @since 2.0 New function to ensure endpoints work immediately after activation
 */
function gtaw_activate_flush_rewrite_rules() {
    gtaw_register_user_endpoints();
    flush_rewrite_rules();
}
register_activation_hook(GTAW_BRIDGE_PLUGIN_DIR . 'gtaw-bridge.php', 'gtaw_activate_flush_rewrite_rules');