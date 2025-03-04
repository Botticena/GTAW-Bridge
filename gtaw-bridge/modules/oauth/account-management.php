<?php
defined('ABSPATH') or exit;

/* ========= OAUTH ACCOUNT MANAGEMENT MODULE ========= */
/*
 * This module handles user account management:
 * - User profile modifications
 * - Character linking/switching
 * - Account status utilities
 */

/**
 * Adds custom user profile fields for GTA:W data
 *
 * @param WP_User $user The user object being edited
 */
function gtaw_add_user_profile_fields($user) {
    // Get the GTA:W user ID
    $gtaw_user_id = get_user_meta($user->ID, 'gtaw_user_id', true);
    $character = get_user_meta($user->ID, 'active_gtaw_character', true);
    
    if (empty($gtaw_user_id)) {
        return;
    }
    ?>
    <h3>GTA World Character Information</h3>
    <table class="form-table">
        <tr>
            <th><label for="gtaw_user_id">GTA:W User ID</label></th>
            <td>
                <input type="text" name="gtaw_user_id" id="gtaw_user_id" value="<?php echo esc_attr($gtaw_user_id); ?>" class="regular-text" readonly />
                <p class="description">This is the user's GTA:W UCP ID.</p>
            </td>
        </tr>
        <?php if (!empty($character)): ?>
        <tr>
            <th><label>Active Character</label></th>
            <td>
                <p><strong>Name:</strong> <?php echo esc_html($character['firstname'] . ' ' . $character['lastname']); ?></p>
                <p><strong>Character ID:</strong> <?php echo esc_html($character['id']); ?></p>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    <?php
}
add_action('show_user_profile', 'gtaw_add_user_profile_fields');
add_action('edit_user_profile', 'gtaw_add_user_profile_fields');

/**
 * Adds a shortcode to display character information
 *
 * @return string HTML output for character information
 */
function gtaw_character_info_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your character information.</p>';
    }
    
    $user_id = get_current_user_id();
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    if (empty($character)) {
        return '<p>No GTA:W character information found.</p>';
    }
    
    $output = '<div class="gtaw-character-info">';
    $output .= '<h3>Your GTA:W Character</h3>';
    $output .= '<p><strong>Name:</strong> ' . esc_html($character['firstname'] . ' ' . $character['lastname']) . '</p>';
    $output .= '<p><strong>Character ID:</strong> ' . esc_html($character['id']) . '</p>';
    $output .= '</div>';
    
    return $output;
}
add_shortcode('gtaw_character_info', 'gtaw_character_info_shortcode');

/**
 * Block normal WordPress registration if OAuth is enabled
 */
function gtaw_block_wp_registration() {
    if (get_option('gtaw_oauth_enabled', 1) == 1) {
        // Check if this is the registration page
        global $pagenow;
        if ($pagenow == 'wp-login.php' && isset($_GET['action']) && $_GET['action'] == 'register') {
            // Redirect to the login URL with a message
            $login_link = gtaw_get_oauth_url();
            wp_redirect(add_query_arg('registration_disabled', '1', $login_link));
            exit;
        }
    }
}
add_action('init', 'gtaw_block_wp_registration');

/**
 * Modify the login form to include the GTA:W login option
 */
function gtaw_modify_login_form() {
    if (get_option('gtaw_oauth_enabled', 1) == 1) {
        $login_link = gtaw_get_oauth_url();
        
        if (!empty($login_link)) {
            echo '<div style="text-align: center; margin: 20px 0;">';
            echo '<p>Log in with your GTA:W account:</p>';
            echo '<a href="' . esc_url($login_link) . '" class="button button-primary" style="margin: 0 auto; display: inline-block; padding: 5px 15px; background: #0085ba; color: white; text-decoration: none; border-radius: 3px; border: 1px solid #006799;">Login with GTA:W</a>';
            echo '</div>';
            
            if (isset($_GET['registration_disabled'])) {
                echo '<div style="color: red; text-align: center; margin: 10px 0;">';
                echo 'Regular registration is disabled. Please use GTA:W login.';
                echo '</div>';
            }
        }
    }
}
add_action('login_form', 'gtaw_modify_login_form');
add_action('register_form', 'gtaw_modify_login_form');

/**
 * Add a widget to the WooCommerce dashboard showing GTA:W character info
 */
function gtaw_add_woocommerce_dashboard_widget() {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    
    if (empty($character)) {
        return;
    }
    
    ?>
    <div class="woocommerce-MyAccount-content-widget gtaw-character-widget" style="margin-bottom: 30px; padding: 20px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 5px;">
        <h4>Your GTA:W Character</h4>
        <p><strong>Name:</strong> <?php echo esc_html($character['firstname'] . ' ' . $character['lastname']); ?></p>
        <p><strong>Character ID:</strong> <?php echo esc_html($character['id']); ?></p>
        
        <?php if (!empty($available_characters) && is_array($available_characters) && count($available_characters) > 1): ?>
            <button type="button" class="button toggle-character-selector">Switch Character</button>
            
            <div class="character-selector-container" style="display: none; margin-top: 15px;">
                <h5>Select Character</h5>
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
                    <div style="margin-bottom: 10px;">
                        <form method="post" action="">
                            <?php wp_nonce_field('gtaw_switch_character', 'gtaw_character_nonce'); ?>
                            <input type="hidden" name="gtaw_switch_character" value="1">
                            <input type="hidden" name="character_id" value="<?php echo esc_attr($char['id']); ?>">
                            <input type="hidden" name="character_firstname" value="<?php echo esc_attr($char['firstname']); ?>">
                            <input type="hidden" name="character_lastname" value="<?php echo esc_attr($char['lastname']); ?>">
                            <button type="submit" class="button button-small">
                                <?php echo esc_html($char['firstname'] . ' ' . $char['lastname']); ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('.toggle-character-selector').on('click', function() {
                    $('.character-selector-container').slideToggle();
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
                <button type="submit" class="button">Log Out & Switch Character</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
}
add_action('woocommerce_account_dashboard', 'gtaw_add_woocommerce_dashboard_widget');

/**
 * Check if a WordPress user is linked to a GTA:W account
 *
 * @param int $user_id The WordPress user ID to check
 * @return bool True if user is linked to GTA:W
 */
function gtaw_is_user_linked_to_gtaw($user_id) {
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    return !empty($gtaw_user_id) && !empty($character);
}

/**
 * Reset a user's GTA:W connection
 *
 * @param int $user_id The WordPress user ID
 * @return bool True on success
 */
function gtaw_reset_gtaw_connection($user_id) {
    if (!current_user_can('edit_users') && get_current_user_id() != $user_id) {
        return false;
    }
    
    delete_user_meta($user_id, 'gtaw_user_id');
    delete_user_meta($user_id, 'active_gtaw_character');
    
    gtaw_add_log('oauth', 'Reset', "GTA:W connection reset for user ID: $user_id", 'success');
    
    return true;
}