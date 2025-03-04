<?php
defined('ABSPATH') or exit;

/* ========= CHARACTER SWITCH LOGIN MODAL FIX ========= */
/*
 * This fixes the character switching modal login issue:
 * - Correctly identifies characters that already have accounts
 * - Updates the modal display
 * - Prevents admin users from using GTAW OAuth and Discord linking
 */

/**
 * Improve character detection in the login modal
 * Modify the gtaw_check_account_callback function to better detect existing accounts
 */
function gtaw_improve_login_modal() {
    // Remove the original function
    remove_action('wp_ajax_gtaw_check_account', 'gtaw_check_account_callback');
    remove_action('wp_ajax_nopriv_gtaw_check_account', 'gtaw_check_account_callback');
    
    // Add our improved version
    add_action('wp_ajax_gtaw_check_account', 'gtaw_improved_check_account_callback');
    add_action('wp_ajax_nopriv_gtaw_check_account', 'gtaw_improved_check_account_callback');
}
add_action('init', 'gtaw_improve_login_modal');

/**
 * Improved AJAX handler to check if a GTA:W user has existing WordPress accounts
 * This version checks both active_gtaw_character and also performs email checks
 */
function gtaw_improved_check_account_callback() {
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
    
    // Extract all characters from GTAW data
    $gtaw_characters = isset($user_data['user']['character']) ? $user_data['user']['character'] : [];
    
    // Create a map of character IDs to verify against
    $character_id_map = [];
    foreach ($gtaw_characters as $character) {
        if (isset($character['id'])) {
            $character_id_map[$character['id']] = $character;
        }
    }
    
    // Create a collection of already linked accounts
    $accounts = [];
    $linked_character_ids = [];
    
    // First, get accounts directly through active_gtaw_character meta
    foreach ($users as $user) {
        $active = get_user_meta($user->ID, 'active_gtaw_character', true);
        if (!empty($active) && isset($active['id'])) {
            $accounts[] = array(
                'wp_user_id' => $user->ID,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'active'     => $active,
            );
            $linked_character_ids[] = $active['id'];
        }
    }
    
    // Now check for any accounts based on email pattern
    foreach ($gtaw_characters as $character) {
        if (empty($character['id']) || in_array($character['id'], $linked_character_ids)) {
            continue; // Skip if already found or invalid
        }
        
        // Check if a user exists with the expected email format
        $email = strtolower($character['firstname'] . '.' . $character['lastname']) . '@mail.sa';
        $user = get_user_by('email', $email);
        
        if ($user) {
            // Verify this is a GTA:W user account by checking for gtaw_user_id
            $user_gtaw_id = get_user_meta($user->ID, 'gtaw_user_id', true);
            if ($user_gtaw_id == $gtaw_user_id) {
                // This account exists but wasn't found through the regular meta query
                // Update the active_gtaw_character meta to fix the issue for the future
                update_user_meta($user->ID, 'active_gtaw_character', [
                    'id' => $character['id'],
                    'firstname' => $character['firstname'],
                    'lastname' => $character['lastname']
                ]);
                
                // Add to accounts list
                $accounts[] = array(
                    'wp_user_id' => $user->ID,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'active'     => [
                        'id' => $character['id'],
                        'firstname' => $character['firstname'],
                        'lastname' => $character['lastname']
                    ],
                );
                $linked_character_ids[] = $character['id'];
            }
        }
    }
    
    wp_send_json_success(array('exists' => !empty($accounts), 'accounts' => $accounts));
}

/**
 * Prevent administrators from using GTAW OAuth and Discord linking
 */

/**
 * Check if current user is an administrator
 * 
 * @return bool True if user is an administrator
 */
function gtaw_is_admin_user() {
    if (!is_user_logged_in()) {
        return false;
    }
    
    $user = wp_get_current_user();
    return in_array('administrator', $user->roles);
}

/**
 * Block administrators from using the GTAW OAuth flow
 */
function gtaw_block_admin_oauth() {
    // Check if this is an OAuth request
    if (isset($_GET['gta_oauth']) || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'oauth/authorize') !== false)) {
        // Block if user is an administrator
        if (gtaw_is_admin_user()) {
            wp_die('Administrator accounts cannot use GTAW authentication for security reasons. Please use standard WordPress login.', 'Access Restricted', ['response' => 403]);
        }
    }
}
add_action('init', 'gtaw_block_admin_oauth', 5);

/**
 * Block administrators from using Discord linking
 */
function gtaw_block_admin_discord_linking() {
    // Check if this is a Discord OAuth callback
    if (isset($_GET['discord_oauth']) && $_GET['discord_oauth'] === 'callback') {
        // Block if user is an administrator
        if (gtaw_is_admin_user()) {
            wp_die('Administrator accounts cannot link Discord accounts for security reasons.', 'Access Restricted', ['response' => 403]);
        }
    }
}
add_action('init', 'gtaw_block_admin_discord_linking', 5);

/**
 * Add warning message on Discord settings page for admins
 */
function gtaw_add_admin_discord_warning() {
    if (gtaw_is_admin_user() && is_account_page() && isset($_GET['discord'])) {
        wc_add_notice('Administrator accounts cannot link Discord accounts for security reasons. Please use a regular user account.', 'error');
    }
}
add_action('template_redirect', 'gtaw_add_admin_discord_warning');

/**
 * Override Discord widget display for admins
 */
function gtaw_override_discord_widget_for_admins() {
    if (gtaw_is_admin_user()) {
        // Remove the original widget
        remove_action('woocommerce_account_discord_endpoint', 'gtaw_add_discord_roles_to_account', 20);
        
        // Add our warning message
        add_action('woocommerce_account_discord_endpoint', 'gtaw_admin_discord_warning', 10);
    }
}
add_action('init', 'gtaw_override_discord_widget_for_admins');

/**
 * Display admin warning in Discord endpoint
 */
function gtaw_admin_discord_warning() {
    ?>
    <div class="woocommerce-message woocommerce-message--error">
        <p><strong>Administrator Access Restricted</strong></p>
        <p>As a website administrator, you cannot use Discord integration features due to security considerations.</p>
        <p>To use Discord integration, please create and use a regular user account.</p>
    </div>
    <?php
}