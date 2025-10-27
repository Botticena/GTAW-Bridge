<?php
defined('ABSPATH') or exit;

/* ========= MULTIPLE CHARACTER SWITCHING MODULE ========= */
/*
 * This module adds the ability to switch between GTA:W characters
 * Each character has its own WordPress account
 */

/**
 * Store all available characters when a user first authenticates
 * 
 * @param array $user_data User data from GTA:W API
 */
function gtaw_store_all_characters($user_data) {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Validate user data structure
    if (!isset($user_data['user']) || !isset($user_data['user']['character']) || !is_array($user_data['user']['character'])) {
        return;
    }
    
    // Filter valid characters with required fields
    $valid_characters = array();
    foreach ($user_data['user']['character'] as $character) {
        if (!empty($character['id']) && !empty($character['firstname']) && !empty($character['lastname'])) {
            $valid_characters[] = $character;
        }
    }
    
    // Store all valid characters in user meta
    update_user_meta($user_id, 'gtaw_available_characters', $valid_characters);
    
    // Clear any cached data
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    if (!empty($gtaw_user_id)) {
        delete_transient('gtaw_linked_accounts_' . $gtaw_user_id);
    }
    
    gtaw_add_log('oauth', 'Characters', "Stored " . count($valid_characters) . " characters for user ID: {$user_id}", 'success');
}
add_action('gtaw_oauth_process_started', 'gtaw_store_all_characters');

/**
 * Check if character switching is enabled
 * 
 * @return bool True if character switching is enabled
 */
function gtaw_is_character_switching_enabled() {
    $settings = get_option('gtaw_oauth_settings', array());
    return isset($settings['allow_character_switch']) ? (bool) $settings['allow_character_switch'] : true;
}

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
    return is_array($user->roles) && in_array('administrator', $user->roles);
}

/**
 * Block administrators from using the GTAW OAuth flow
 */
function gtaw_block_admin_oauth() {
    if (isset($_GET['gta_oauth']) || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'oauth/authorize') !== false)) {
        if (gtaw_is_admin_user()) {
            wp_die('Administrator accounts cannot use GTAW authentication for security reasons. Please use standard WordPress login.', 'Access Restricted', array('response' => 403));
        }
    }
}
add_action('init', 'gtaw_block_admin_oauth', 5);

/**
 * Block administrators from using Discord linking
 */
function gtaw_block_admin_discord_linking() {
    if (isset($_GET['discord_oauth']) && $_GET['discord_oauth'] === 'callback' && gtaw_is_admin_user()) {
        wp_die('Administrator accounts cannot link Discord accounts for security reasons.', 'Access Restricted', array('response' => 403));
    }
}
add_action('init', 'gtaw_block_admin_discord_linking', 5);

/**
 * Add warning message on Discord settings page for admins
 */
function gtaw_add_admin_discord_warning() {
    if (gtaw_is_admin_user() && function_exists('is_account_page') && is_account_page() && isset($_GET['discord'])) {
        if (function_exists('wc_add_notice')) {
            wc_add_notice('Administrator accounts cannot link Discord accounts for security reasons. Please use a regular user account.', 'error');
        }
    }
}
add_action('template_redirect', 'gtaw_add_admin_discord_warning');

/**
 * Override Discord widget display for admins
 */
function gtaw_override_discord_widget_for_admins() {
    if (gtaw_is_admin_user()) {
        // Remove the original widget if it exists
        if (has_action('woocommerce_account_discord_endpoint', 'gtaw_add_discord_roles_to_account')) {
            remove_action('woocommerce_account_discord_endpoint', 'gtaw_add_discord_roles_to_account', 20);
        }
        
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

/**
 * Get all WordPress accounts linked to a GTA:W user
 * 
 * @param string $gtaw_user_id The GTA:W user ID
 * @param bool $bypass_cache Whether to bypass the cache
 * @return array Array of WP user objects
 */
function gtaw_get_linked_accounts($gtaw_user_id, $bypass_cache = false) {
    if (empty($gtaw_user_id)) {
        return array();
    }
    
    // Check transient cache unless bypassing
    if (!$bypass_cache) {
        $cached_accounts = get_transient('gtaw_linked_accounts_' . $gtaw_user_id);
        if ($cached_accounts !== false) {
            return $cached_accounts;
        }
    }
    
    // Query the database directly to avoid any potential plugin conflicts
    global $wpdb;
    
    // First get all users with this GTAW user ID
    $query = $wpdb->prepare(
        "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'gtaw_user_id' AND meta_value = %s",
        $gtaw_user_id
    );
    
    $user_ids = $wpdb->get_col($query);
    
    // No users found
    if (empty($user_ids)) {
        return array();
    }
    
    // Now get the full user objects
    $linked_users = array();
    foreach ($user_ids as $user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            $linked_users[] = $user;
        }
    }
    
    // Cache the result
    set_transient('gtaw_linked_accounts_' . $gtaw_user_id, $linked_users, 300); // 5 minute cache
    
    return $linked_users;
}

/**
 * Find WordPress user by character ID
 * 
 * @param string $gtaw_user_id The GTA:W user ID
 * @param string $character_id The character ID
 * @return WP_User|false WordPress user or false if not found
 */
function gtaw_find_user_by_character($gtaw_user_id, $character_id) {
    // Get all linked accounts, bypassing cache to ensure we have the latest
    $linked_users = gtaw_get_linked_accounts($gtaw_user_id, true);
    
    foreach ($linked_users as $user) {
        $active_character = get_user_meta($user->ID, 'active_gtaw_character', true);
        if (is_array($active_character) && isset($active_character['id']) && $active_character['id'] == $character_id) {
            return $user;
        }
    }
    
    return false;
}

/**
 * Switch to a different character account
 * 
 * @param int $user_id Current WordPress user ID
 * @param array $character_data Character data for the target character
 * @return bool Success status
 */
function gtaw_perform_character_switch($user_id, $character_data) {
    // Get user meta to find GTAW user ID
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    
    if (empty($gtaw_user_id)) {
        gtaw_add_log('oauth', 'Error', "No GTAW user ID found for user {$user_id}", 'error');
        return false;
    }
    
    // Get target character information
    $character_id = isset($character_data['id']) ? $character_data['id'] : '';
    if (empty($character_id)) {
        return false;
    }
    
    // Find if an account already exists for this character
    $target_user = gtaw_find_user_by_character($gtaw_user_id, $character_id);
    
    if (!$target_user) {
        gtaw_add_log('oauth', 'Error', "No WordPress account found for character ID: {$character_id}", 'error');
        return false;
    }
    
    // Don't switch if already on this account
    if ($user_id == $target_user->ID) {
        return true;
    }
    
    // Log the user out
    wp_logout();
    
    // Log in as the target user
    wp_set_auth_cookie($target_user->ID, true);
    wp_set_current_user($target_user->ID);
    
    // Log the switch
    $character_firstname = isset($character_data['firstname']) ? $character_data['firstname'] : '';
    $character_lastname = isset($character_data['lastname']) ? $character_data['lastname'] : '';
    gtaw_add_log('oauth', 'Switch', "Switched from user ID {$user_id} to user ID {$target_user->ID} (Character: {$character_firstname} {$character_lastname})", 'success');
    
    // Clear linked accounts cache
    delete_transient('gtaw_linked_accounts_' . $gtaw_user_id);
    
    return true;
}

/**
 * Process character switch form submission
 */
function gtaw_process_character_switch() {
    // Check if this is a character switch request and WooCommerce is active
    if (!isset($_POST['gtaw_switch_character']) || !isset($_POST['gtaw_character_nonce']) || !function_exists('wc_add_notice')) {
        return;
    }
    
    // Skip for admin users - security measure
    if (gtaw_is_admin_user()) {
        wc_add_notice('Administrator accounts cannot use character switching for security reasons.', 'error');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['gtaw_character_nonce'], 'gtaw_switch_character')) {
        wc_add_notice('Security check failed. Please try again.', 'error');
        return;
    }
    
    // Check for required fields
    if (empty($_POST['character_id']) || empty($_POST['character_firstname']) || empty($_POST['character_lastname'])) {
        wc_add_notice('Missing character information. Please try again.', 'error');
        return;
    }
    
    // Get user ID
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    
    // Ensure character switching is enabled
    if (!gtaw_is_character_switching_enabled()) {
        wc_add_notice('Character switching is currently disabled.', 'error');
        return;
    }
    
    // Sanitize inputs
    $character_id = sanitize_text_field($_POST['character_id']);
    $character_firstname = sanitize_text_field($_POST['character_firstname']);
    $character_lastname = sanitize_text_field($_POST['character_lastname']);
    
    // Verify character exists in available characters
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    $character_exists = false;
    
    if (is_array($available_characters)) {
        foreach ($available_characters as $character) {
            if (isset($character['id']) && $character['id'] == $character_id && 
                isset($character['firstname']) && $character['firstname'] == $character_firstname && 
                isset($character['lastname']) && $character['lastname'] == $character_lastname) {
                $character_exists = true;
                break;
            }
        }
    }
    
    if (!$character_exists) {
        wc_add_notice('Invalid character selection.', 'error');
        return;
    }
    
    // Build character data
    $character_data = array(
        'id' => $character_id,
        'firstname' => $character_firstname,
        'lastname' => $character_lastname
    );
    
    // Store these in a cookie for the redirect
    setcookie('gtaw_switch_character_name', $character_firstname . ' ' . $character_lastname, time() + 300, COOKIEPATH, COOKIE_DOMAIN);
    
    // Clear linked accounts cache
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    if (!empty($gtaw_user_id)) {
        delete_transient('gtaw_linked_accounts_' . $gtaw_user_id);
    }
    
    // Perform the character switch - this will log the user out
    $switch_result = gtaw_perform_character_switch($user_id, $character_data);
    
    if ($switch_result) {
        // Redirect to account page - we are now logged in as the other user
        if (function_exists('wc_get_account_endpoint_url')) {
            wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
        } else {
            wp_safe_redirect(home_url());
        }
        exit;
    } else {
        wc_add_notice('Failed to switch characters. Please try again.', 'error');
        if (function_exists('wc_get_account_endpoint_url')) {
            wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
        } else {
            wp_safe_redirect(home_url());
        }
        exit;
    }
}
add_action('template_redirect', 'gtaw_process_character_switch');

/**
 * Process admin bar character switch
 */
function gtaw_process_admin_bar_character_switch() {
    // Check if this is an admin bar character switch request and WooCommerce is active
    if (!isset($_GET['gtaw_switch_character']) || !isset($_GET['gtaw_character_nonce']) || !function_exists('wc_add_notice')) {
        return;
    }
    
    // Skip for admin users - security measure
    if (gtaw_is_admin_user()) {
        wc_add_notice('Administrator accounts cannot use character switching for security reasons.', 'error');
        wp_safe_redirect(home_url());
        exit;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_GET['gtaw_character_nonce'], 'gtaw_switch_character')) {
        wc_add_notice('Security check failed. Please try again.', 'error');
        return;
    }
    
    // Check for required fields
    if (empty($_GET['character_id']) || empty($_GET['character_firstname']) || empty($_GET['character_lastname'])) {
        wc_add_notice('Missing character information. Please try again.', 'error');
        return;
    }
    
    // Get user ID
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    
    // Ensure character switching is enabled
    if (!gtaw_is_character_switching_enabled()) {
        wc_add_notice('Character switching is currently disabled.', 'error');
        return;
    }
    
    // Sanitize inputs
    $character_id = sanitize_text_field($_GET['character_id']);
    $character_firstname = sanitize_text_field($_GET['character_firstname']);
    $character_lastname = sanitize_text_field($_GET['character_lastname']);
    
    // Verify character exists in available characters
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    $character_exists = false;
    
    if (is_array($available_characters)) {
        foreach ($available_characters as $character) {
            if (isset($character['id']) && $character['id'] == $character_id && 
                isset($character['firstname']) && $character['firstname'] == $character_firstname && 
                isset($character['lastname']) && $character['lastname'] == $character_lastname) {
                $character_exists = true;
                break;
            }
        }
    }
    
    if (!$character_exists) {
        wc_add_notice('Invalid character selection.', 'error');
        return;
    }
    
    // Build character data
    $character_data = array(
        'id' => $character_id,
        'firstname' => $character_firstname,
        'lastname' => $character_lastname
    );
    
    // Store these in a cookie for the redirect
    setcookie('gtaw_switch_character_name', $character_firstname . ' ' . $character_lastname, time() + 300, COOKIEPATH, COOKIE_DOMAIN);
    
    // Calculate return URL without the character switch parameters
    $return_url = remove_query_arg(array(
        'gtaw_switch_character', 
        'character_id', 
        'character_firstname', 
        'character_lastname', 
        'gtaw_character_nonce'
    ));
    
    // Clear linked accounts cache
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    if (!empty($gtaw_user_id)) {
        delete_transient('gtaw_linked_accounts_' . $gtaw_user_id);
    }
    
    // Perform the character switch - this will log the user out
    $switch_result = gtaw_perform_character_switch($user_id, $character_data);
    
    if ($switch_result) {
        // Redirect to the original page without the query parameters
        wp_safe_redirect($return_url);
        exit;
    } else {
        wc_add_notice('Failed to switch characters. Please try again.', 'error');
        wp_safe_redirect($return_url);
        exit;
    }
}
add_action('template_redirect', 'gtaw_process_admin_bar_character_switch', 5);

/**
 * Display success message after character switch
 */
function gtaw_character_switch_success_message() {
    if (isset($_COOKIE['gtaw_switch_character_name']) && function_exists('wc_add_notice')) {
        $character_name = sanitize_text_field($_COOKIE['gtaw_switch_character_name']);
        wc_add_notice(sprintf('Successfully switched to character: %s', $character_name), 'success');
        
        // Clear the cookie
        setcookie('gtaw_switch_character_name', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
}
add_action('woocommerce_before_account_navigation', 'gtaw_character_switch_success_message', 5);

/**
 * Process character account creation from the account dashboard
 */
function gtaw_process_character_account_creation() {
    // Check if this is a character account creation request and WooCommerce is active
    if (!isset($_POST['gtaw_create_account']) || !isset($_POST['gtaw_character_nonce']) || !function_exists('wc_add_notice')) {
        return;
    }
    
    // Skip for admin users - security measure
    if (gtaw_is_admin_user()) {
        wc_add_notice('Administrator accounts cannot create additional character accounts for security reasons.', 'error');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['gtaw_character_nonce'], 'gtaw_create_account')) {
        wc_add_notice('Security check failed. Please try again.', 'error');
        return;
    }
    
    // Check for required fields
    if (empty($_POST['character_id']) || empty($_POST['character_firstname']) || empty($_POST['character_lastname'])) {
        wc_add_notice('Missing character information. Please try again.', 'error');
        return;
    }
    
    // Get user ID and GTAW user ID
    $user_id = get_current_user_id();
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    
    if (empty($gtaw_user_id)) {
        wc_add_notice('Error: Unable to identify your GTA:W account.', 'error');
        return;
    }
    
    // Sanitize inputs
    $character_id = sanitize_text_field($_POST['character_id']);
    $character_firstname = sanitize_text_field($_POST['character_firstname']);
    $character_lastname = sanitize_text_field($_POST['character_lastname']);
    
    // Check if a WP account for this character already exists
    $existing_user = gtaw_find_user_by_character($gtaw_user_id, $character_id);
    if ($existing_user) {
        wc_add_notice('An account for this character already exists.', 'error');
        return;
    }
    
    // Generate the expected email for this character
    $email = strtolower($character_firstname . '.' . $character_lastname) . '@mail.sa';
    
    // Create a username based on the character's name
    $new_username = sanitize_user($character_firstname . '_' . $character_lastname);
    
    // Check if the username already exists
    if (username_exists($new_username)) {
        $new_username .= '_' . substr(md5($gtaw_user_id . $character_id), 0, 6);
        $new_username = sanitize_user($new_username);
    }
    
    // Create the WordPress user
    $new_user_id = wp_insert_user(array(
        'user_login' => $new_username,
        'user_pass'  => wp_generate_password(),
        'first_name' => $character_firstname,
        'last_name'  => $character_lastname,
        'display_name' => "$character_firstname $character_lastname",
        'user_email' => $email,
        'role'       => 'subscriber' // Default role
    ));
    
    if (is_wp_error($new_user_id)) {
        wc_add_notice('Error creating account: ' . $new_user_id->get_error_message(), 'error');
        return;
    }
    
    // Store GTA:W data in user meta
    update_user_meta($new_user_id, 'gtaw_user_id', $gtaw_user_id);
    update_user_meta($new_user_id, 'active_gtaw_character', array(
        'id' => $character_id,
        'firstname' => $character_firstname,
        'lastname' => $character_lastname
    ));
    
    // Store timestamp for connection freshness check
    update_user_meta($new_user_id, 'gtaw_last_connection', time());
    
    // If user has available characters, copy them to the new account
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    if (!empty($available_characters) && is_array($available_characters)) {
        update_user_meta($new_user_id, 'gtaw_available_characters', $available_characters);
    }
    
    // Log the account creation
    gtaw_add_log('oauth', 'Register', "Account created for character {$character_firstname} {$character_lastname} (ID: {$character_id})", 'success');
    
    // Clear linked accounts cache
    delete_transient('gtaw_linked_accounts_' . $gtaw_user_id);
    
    // Add success notice
    wc_add_notice('Account created successfully for ' . $character_firstname . ' ' . $character_lastname . '.', 'success');
    
    // Store cookie for switch notification
    setcookie('gtaw_switch_character_name', $character_firstname . ' ' . $character_lastname, time() + 300, COOKIEPATH, COOKIE_DOMAIN);
    
    // Redirect to prevent form resubmission
    if (function_exists('wc_get_account_endpoint_url')) {
        wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
    } else {
        wp_safe_redirect(home_url());
    }
    exit;
}
add_action('template_redirect', 'gtaw_process_character_account_creation');

/**
 * Flush cache for character data when needed
 * This improves refresh capabilities
 */
function gtaw_force_refresh_character_data() {
    // Only run on account dashboard
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    
    if (!empty($gtaw_user_id)) {
        // Clear the transient to force a refresh
        delete_transient('gtaw_linked_accounts_' . $gtaw_user_id);
    }
}
add_action('wp', 'gtaw_force_refresh_character_data');

/**
 * Add unified character management section to WooCommerce My Account page
 */
function gtaw_add_character_switcher() {
    // Skip if not appropriate
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) {
        return;
    }
    
    // Skip for admin users - security measure
    if (gtaw_is_admin_user()) {
        return;
    }
    
    // Exit if character switching is disabled
    if (!gtaw_is_character_switching_enabled()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    $active_character = get_user_meta($user_id, 'active_gtaw_character', true);
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    
    // Skip if no GTAW user ID or active character
    if (empty($gtaw_user_id) || empty($active_character) || !is_array($active_character)) {
        return;
    }
    
    // Direct database query to find all character accounts
    $character_accounts = gtaw_find_character_accounts($gtaw_user_id);
    $linked_account_count = count($character_accounts);
    
    // Debug info if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo "<!-- GTAW Debug: User ID: {$user_id}, GTAW User ID: {$gtaw_user_id} -->";
        echo "<!-- GTAW Debug: Directly found character accounts: {$linked_account_count} -->";
        echo "<!-- GTAW Debug: Available characters count: " . (is_array($available_characters) ? count($available_characters) : 0) . " -->";
        
        // Dump character data for debugging
        echo "<!-- Character Accounts: " . json_encode($character_accounts) . " -->";
        echo "<!-- Active Character: " . json_encode($active_character) . " -->";
        echo "<!-- Available Characters: " . json_encode($available_characters) . " -->";
    }
    
    ?>
    <div class="gtaw-character-manager" style="margin-bottom: 30px; padding: 20px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 5px;">
        <h2 style="margin-top: 0;">Your GTA:W Character</h2>
        
        <div class="active-character" style="padding: 15px; background: #e7f0fd; border: 1px solid #cae0fd; border-radius: 4px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin-top: 0; margin-bottom: 5px;"><?php echo esc_html($active_character['firstname'] . ' ' . $active_character['lastname']); ?></h3>
                    <p style="margin: 0; color: #666;">Character ID: <?php echo esc_html($active_character['id']); ?></p>
                </div>
                <div>
                    <span class="active-badge" style="background: #0073aa; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">Active</span>
                </div>
            </div>
        </div>
        
        <?php 
        // Check if there are other character accounts (not including the current one)
        $other_character_accounts = array();
        foreach ($character_accounts as $char_id => $char) {
            if ($char_id != $active_character['id'] && isset($char['wp_user_id'])) {
                $other_character_accounts[] = $char;
            }
        }
        
        if (!empty($other_character_accounts)): 
        ?>
            <div class="character-switcher">
                <h3>Switch Character</h3>
                <p>Switch to one of your other character accounts:</p>
                
                <div class="character-cards" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                    <?php foreach ($other_character_accounts as $character): ?>
                        <div class="character-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                            <h4 style="margin-top: 0; margin-bottom: 10px;"><?php echo esc_html($character['firstname'] . ' ' . $character['lastname']); ?></h4>
                            <p style="margin: 0 0 10px; font-size: 12px; color: #666;">ID: <?php echo esc_html($character['id']); ?></p>
                            
                            <form method="post" action="">
                                <?php wp_nonce_field('gtaw_switch_character', 'gtaw_character_nonce'); ?>
                                <input type="hidden" name="gtaw_switch_character" value="1">
                                <input type="hidden" name="character_id" value="<?php echo esc_attr($character['id']); ?>">
                                <input type="hidden" name="character_firstname" value="<?php echo esc_attr($character['firstname']); ?>">
                                <input type="hidden" name="character_lastname" value="<?php echo esc_attr($character['lastname']); ?>">
                                <button type="submit" class="button">Switch to this Character</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php 
        // Find unlinked characters (available but no WordPress account)
        $unlinked_characters = array();
        if (is_array($available_characters)) {
            foreach ($available_characters as $character) {
                if (isset($character['id']) && !isset($character_accounts[$character['id']])) {
                    $unlinked_characters[] = $character;
                }
            }
        }
        
        if (!empty($unlinked_characters)): 
        ?>
            <div class="unlinked-characters" style="margin-top: 25px; border-top: 1px solid #ddd; padding-top: 20px;">
                <h3>Create Character Accounts</h3>
                <p>Create WordPress accounts for these characters:</p>
                
                <div class="character-cards" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                    <?php foreach ($unlinked_characters as $character): ?>
                        <div class="character-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                            <h4 style="margin-top: 0; margin-bottom: 10px;"><?php echo esc_html($character['firstname'] . ' ' . $character['lastname']); ?></h4>
                            <p style="margin: 0 0 10px; font-size: 12px; color: #666;">ID: <?php echo esc_html($character['id']); ?></p>
                            
                            <form method="post" action="">
                                <?php wp_nonce_field('gtaw_create_account', 'gtaw_character_nonce'); ?>
                                <input type="hidden" name="gtaw_create_account" value="1">
                                <input type="hidden" name="character_id" value="<?php echo esc_attr($character['id']); ?>">
                                <input type="hidden" name="character_firstname" value="<?php echo esc_attr($character['firstname']); ?>">
                                <input type="hidden" name="character_lastname" value="<?php echo esc_attr($character['lastname']); ?>">
                                <button type="submit" class="button">Create Account</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($other_character_accounts) && empty($unlinked_characters)): ?>
            <p><em>You don't have any other characters associated with your GTA:W account.</em></p>
        <?php endif; ?>
    </div>
    <?php
}
add_action('woocommerce_account_dashboard', 'gtaw_add_character_switcher', 5);

/**
 * Add character switcher to admin bar for quick access
 * 
 * @param WP_Admin_Bar $admin_bar The admin bar object
 */
function gtaw_admin_bar_character_switcher($admin_bar) {
    if (!is_user_logged_in() || !$admin_bar) {
        return;
    }
    
    // Skip for admin users - security measure
    if (gtaw_is_admin_user()) {
        return;
    }
    
    // Exit if character switching is disabled
    if (!gtaw_is_character_switching_enabled()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    $active_character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    if (empty($gtaw_user_id) || empty($active_character) || !is_array($active_character)) {
        return;
    }
    
    // Direct database query to find all character accounts
    $character_accounts = gtaw_find_character_accounts($gtaw_user_id);
    
    // Skip if only one account
    if (count($character_accounts) <= 1) {
        return;
    }
    
    // Active character name
    $active_name = isset($active_character['firstname']) && isset($active_character['lastname']) 
        ? $active_character['firstname'] . ' ' . $active_character['lastname'] 
        : 'Unknown Character';
    
    // Add parent menu
    $admin_bar->add_menu(array(
        'id' => 'gtaw-character-switcher',
        'title' => '<span class="ab-icon dashicons dashicons-businessman"></span>' . esc_html($active_name),
        'href' => function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') . '#gtaw-character-switcher' : '#',
        'meta' => array(
            'title' => 'Switch Character'
        )
    ));
    
    // Add character submenu items
    foreach ($character_accounts as $character) {
        // Skip if missing required fields or current character
        if (empty($character['id']) || empty($character['firstname']) || empty($character['lastname']) || 
            $character['id'] == $active_character['id']) {
            continue;
        }
        
        $admin_bar->add_menu(array(
            'id' => 'gtaw-character-' . $character['id'],
            'parent' => 'gtaw-character-switcher',
            'title' => esc_html($character['firstname'] . ' ' . $character['lastname']),
            'href' => add_query_arg(array(
                'gtaw_switch_character' => 1,
                'character_id' => $character['id'],
                'character_firstname' => $character['firstname'],
                'character_lastname' => $character['lastname'],
                'gtaw_character_nonce' => wp_create_nonce('gtaw_switch_character')
            ), function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : home_url()),
            'meta' => array(
                'title' => 'Switch to this Character'
            )
        ));
    }
}
add_action('admin_bar_menu', 'gtaw_admin_bar_character_switcher', 100);

/**
 * Add CSS for admin bar character switcher
 */
function gtaw_admin_bar_character_switcher_css() {
    if (!is_user_logged_in() || gtaw_is_admin_user() || !gtaw_is_character_switching_enabled()) {
        return;
    }
    
    ?>
    <style>
        #wpadminbar #wp-admin-bar-gtaw-character-switcher .ab-icon {
            top: 2px;
        }
        #wpadminbar .gtaw-active-character {
            font-weight: bold;
            color: #72aee6;
        }
    </style>
    <?php
}
add_action('wp_head', 'gtaw_admin_bar_character_switcher_css');
add_action('admin_head', 'gtaw_admin_bar_character_switcher_css');

// Try to remove the original character widget to avoid duplication
// First check if the function exists to avoid errors
if (function_exists('gtaw_add_woocommerce_dashboard_widget') && 
    has_action('woocommerce_account_dashboard', 'gtaw_add_woocommerce_dashboard_widget')) {
    remove_action('woocommerce_account_dashboard', 'gtaw_add_woocommerce_dashboard_widget');
}

/**
 * Find character accounts directly using database queries
 * Bypasses all caching and meta lookups which might be unreliable
 * 
 * @param string $gtaw_user_id The GTA:W user ID
 * @return array Character data with WP user IDs
 */
function gtaw_find_character_accounts($gtaw_user_id) {
    global $wpdb;
    
    if (empty($gtaw_user_id)) {
        return array();
    }
    
    // First get all WordPress users with this GTAW ID
    $query = $wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
        'gtaw_user_id', $gtaw_user_id
    );
    
    $user_ids = $wpdb->get_col($query);
    
    if (empty($user_ids)) {
        return array();
    }
    
    // Get character info for each user
    $characters = array();
    foreach ($user_ids as $user_id) {
        $active_character_serialized = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
            $user_id, 'active_gtaw_character'
        ));
        
        if ($active_character_serialized) {
            $active_character = maybe_unserialize($active_character_serialized);
            if (is_array($active_character) && !empty($active_character['id'])) {
                $active_character['wp_user_id'] = $user_id;
                $characters[$active_character['id']] = $active_character;
            }
        }
    }
    
    return $characters;
}