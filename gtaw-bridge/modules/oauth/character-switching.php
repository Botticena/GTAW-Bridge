<?php
defined('ABSPATH') or exit;

/* ========= MULTIPLE CHARACTER SWITCHING MODULE ========= */
/*
 * This module adds the ability to switch between GTA:W characters without logout
 * - Stores all available characters for a user
 * - Provides UI for character switching
 * - Handles character switch process
 */

/**
 * Store all available characters when a user first authenticates
 * 
 * @param array $user_data The user data from GTA:W API
 */
function gtaw_store_all_characters($user_data) {
    // Only run this if the user is logged in
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Check if we have character data
    if (empty($user_data['user']['character']) || !is_array($user_data['user']['character'])) {
        return;
    }
    
    // Store all characters in user meta
    update_user_meta($user_id, 'gtaw_available_characters', $user_data['user']['character']);
    
    // Log this action
    gtaw_add_log('oauth', 'Characters', "Stored " . count($user_data['user']['character']) . " characters for user ID: {$user_id}", 'success');
}
add_action('gtaw_oauth_process_started', 'gtaw_store_all_characters');

/**
 * Add switch character form to the WooCommerce My Account page
 */
/**
 * Add unified character management section to WooCommerce My Account page
 */
function gtaw_add_character_switcher() {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    $active_character = get_user_meta($user_id, 'active_gtaw_character', true);
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    
    // Skip if no GTAW user ID or active character
    if (empty($gtaw_user_id) || empty($active_character)) {
        return;
    }
    
    // Get character account status
    $character_status = gtaw_get_character_account_status($available_characters, $gtaw_user_id);
    $has_multiple_linked = count($character_status['linked']) > 1;
    
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
        
        <?php if ($has_multiple_linked): ?>
            <div class="character-switcher">
                <h3>Switch Character</h3>
                <p>Switch to one of your other characters without logging out:</p>
                
                <div class="character-cards" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                    <?php foreach ($character_status['linked'] as $character): ?>
                        <?php
                        // Skip if this is the active character
                        if ($character['id'] == $active_character['id']) continue;
                        ?>
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
        
        <?php if (!empty($character_status['unlinked'])): ?>
            <div class="unlinked-characters" style="margin-top: 25px; border-top: 1px solid #ddd; padding-top: 20px;">
                <h3>Other Characters</h3>
                <p>The following characters don't have accounts on this website yet:</p>
                
                <div class="character-cards" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                    <?php foreach ($character_status['unlinked'] as $character): ?>
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
        
        <?php if (!$has_multiple_linked && empty($character_status['unlinked'])): ?>
            <p><em>You don't have any other characters associated with your GTA:W account.</em></p>
        <?php endif; ?>
    </div>
    <?php
}
add_action('woocommerce_account_dashboard', 'gtaw_add_character_switcher', 5);
add_action('woocommerce_account_dashboard', 'gtaw_add_character_switcher', 5);

/**
 * Process character switch form submission
 */
function gtaw_process_character_switch() {
    // Check if this is a character switch request
    if (!isset($_POST['gtaw_switch_character']) || !isset($_POST['gtaw_character_nonce'])) {
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['gtaw_character_nonce'], 'gtaw_switch_character')) {
        wc_add_notice(__('Security check failed. Please try again.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Check for required fields
    if (empty($_POST['character_id']) || empty($_POST['character_firstname']) || empty($_POST['character_lastname'])) {
        wc_add_notice(__('Missing character information. Please try again.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Get user ID
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    
    // Get available characters
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    if (empty($available_characters) || !is_array($available_characters)) {
        wc_add_notice(__('No available characters found.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Sanitize inputs
    $character_id = sanitize_text_field($_POST['character_id']);
    $character_firstname = sanitize_text_field($_POST['character_firstname']);
    $character_lastname = sanitize_text_field($_POST['character_lastname']);
    
    // Verify character exists in available characters
    $character_exists = false;
    foreach ($available_characters as $character) {
        if ($character['id'] == $character_id && 
            $character['firstname'] == $character_firstname && 
            $character['lastname'] == $character_lastname) {
            $character_exists = true;
            break;
        }
    }
    
    if (!$character_exists) {
        wc_add_notice(__('Invalid character selection.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Create character data array
    $character_data = array(
        'id' => $character_id,
        'firstname' => $character_firstname,
        'lastname' => $character_lastname
    );
    
    // Update active character
    update_user_meta($user_id, 'active_gtaw_character', $character_data);
    
    // Update user display name
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $character_firstname . ' ' . $character_lastname,
        'first_name' => $character_firstname,
        'last_name' => $character_lastname
    ]);
    
    // Trigger role sync if Discord integration is active
    if (function_exists('gtaw_sync_user_discord_roles') && 
        get_option('gtaw_discord_rolemapping_enabled', '0') === '1') {
        gtaw_sync_user_discord_roles($user_id);
    }
    
    // Log the character switch
    gtaw_add_log('oauth', 'Switch', "User ID {$user_id} switched to character: {$character_firstname} {$character_lastname} (ID: {$character_id})", 'success');
    
    // Add success notice
    wc_add_notice(__('Character switched successfully! You are now playing as ' . $character_firstname . ' ' . $character_lastname . '.', 'gtaw-bridge'), 'success');
    
    // Redirect to account page to prevent form resubmission
    wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
    exit;
}
add_action('template_redirect', 'gtaw_process_character_switch');

/**
 * Add character switcher to admin bar for quick access
 */
function gtaw_admin_bar_character_switcher($admin_bar) {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $active_character = get_user_meta($user_id, 'active_gtaw_character', true);
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    
    // Skip if no characters available or only one character
    if (empty($available_characters) || !is_array($available_characters) || count($available_characters) <= 1) {
        return;
    }
    
    // Get active character details
    $active_name = isset($active_character['firstname']) && isset($active_character['lastname']) 
        ? $active_character['firstname'] . ' ' . $active_character['lastname'] 
        : 'Unknown Character';
    
    // Add parent menu
    $admin_bar->add_menu([
        'id' => 'gtaw-character-switcher',
        'title' => '<span class="ab-icon dashicons dashicons-businessman"></span>' . esc_html($active_name),
        'href' => wc_get_account_endpoint_url('dashboard') . '#gtaw-character-switcher',
        'meta' => [
            'title' => __('Switch Character', 'gtaw-bridge')
        ]
    ]);
    
    // Add character submenu items
    foreach ($available_characters as $character) {
        // Skip if missing required fields
        if (empty($character['id']) || empty($character['firstname']) || empty($character['lastname'])) {
            continue;
        }
        
        // Determine if this is the active character
        $is_active = ($active_character && $character['id'] == $active_character['id']);
        $char_name = $character['firstname'] . ' ' . $character['lastname'];
        
        $admin_bar->add_menu([
            'id' => 'gtaw-character-' . $character['id'],
            'parent' => 'gtaw-character-switcher',
            'title' => $is_active ? '✓ ' . esc_html($char_name) : esc_html($char_name),
            'href' => $is_active ? '#' : add_query_arg([
                'gtaw_switch_character' => 1,
                'character_id' => $character['id'],
                'character_firstname' => $character['firstname'],
                'character_lastname' => $character['lastname'],
                'gtaw_character_nonce' => wp_create_nonce('gtaw_switch_character')
            ], wc_get_account_endpoint_url('dashboard')),
            'meta' => [
                'title' => $is_active ? __('Current Character', 'gtaw-bridge') : __('Switch to this Character', 'gtaw-bridge'),
                'class' => $is_active ? 'gtaw-active-character' : ''
            ]
        ]);
    }
}
add_action('admin_bar_menu', 'gtaw_admin_bar_character_switcher', 100);

/**
 * Process admin bar character switch
 */
function gtaw_process_admin_bar_character_switch() {
    // Check if this is an admin bar character switch request
    if (!isset($_GET['gtaw_switch_character']) || !isset($_GET['gtaw_character_nonce'])) {
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_GET['gtaw_character_nonce'], 'gtaw_switch_character')) {
        wc_add_notice(__('Security check failed. Please try again.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Check for required fields
    if (empty($_GET['character_id']) || empty($_GET['character_firstname']) || empty($_GET['character_lastname'])) {
        wc_add_notice(__('Missing character information. Please try again.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Get user ID
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    
    // The rest of the process is identical to the form submission handler
    // Get available characters
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    if (empty($available_characters) || !is_array($available_characters)) {
        wc_add_notice(__('No available characters found.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Sanitize inputs
    $character_id = sanitize_text_field($_GET['character_id']);
    $character_firstname = sanitize_text_field($_GET['character_firstname']);
    $character_lastname = sanitize_text_field($_GET['character_lastname']);
    
    // Verify character exists in available characters
    $character_exists = false;
    foreach ($available_characters as $character) {
        if ($character['id'] == $character_id && 
            $character['firstname'] == $character_firstname && 
            $character['lastname'] == $character_lastname) {
            $character_exists = true;
            break;
        }
    }
    
    if (!$character_exists) {
        wc_add_notice(__('Invalid character selection.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Create character data array
    $character_data = array(
        'id' => $character_id,
        'firstname' => $character_firstname,
        'lastname' => $character_lastname
    );
    
    // Update active character
    update_user_meta($user_id, 'active_gtaw_character', $character_data);
    
    // Update user display name
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $character_firstname . ' ' . $character_lastname,
        'first_name' => $character_firstname,
        'last_name' => $character_lastname
    ]);
    
    // Trigger role sync if Discord integration is active
    if (function_exists('gtaw_sync_user_discord_roles') && 
        get_option('gtaw_discord_rolemapping_enabled', '0') === '1') {
        gtaw_sync_user_discord_roles($user_id);
    }
    
    // Log the character switch
    gtaw_add_log('oauth', 'Switch', "User ID {$user_id} switched to character: {$character_firstname} {$character_lastname} (ID: {$character_id})", 'success');
    
    // Add success notice
    wc_add_notice(__('Character switched successfully! You are now playing as ' . $character_firstname . ' ' . $character_lastname . '.', 'gtaw-bridge'), 'success');
    
    // Redirect to remove the query parameters
    $redirect_url = remove_query_arg(['gtaw_switch_character', 'character_id', 'character_firstname', 'character_lastname', 'gtaw_character_nonce']);
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('template_redirect', 'gtaw_process_admin_bar_character_switch', 5);

/**
 * Add CSS for admin bar character switcher
 */
function gtaw_admin_bar_character_switcher_css() {
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

/**
 * Maintain character selection cookie for new sessions
 */
function gtaw_remember_character_selection() {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $active_character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    // If user has an active character, set a cookie to remember it
    if (!empty($active_character) && isset($active_character['id'])) {
        if (!isset($_COOKIE['gtaw_active_character']) || $_COOKIE['gtaw_active_character'] != $active_character['id']) {
            setcookie('gtaw_active_character', $active_character['id'], time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
        }
    }
}
add_action('wp', 'gtaw_remember_character_selection');

/**
 * Function to immediately populate available characters for existing users
 */
function gtaw_populate_character_data_for_existing_users() {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $available_characters = get_user_meta($user_id, 'gtaw_available_characters', true);
    
    // If user already has character data, skip this
    if (!empty($available_characters) && is_array($available_characters) && count($available_characters) > 1) {
        return;
    }
    
    // Get the active character
    $active_character = get_user_meta($user_id, 'active_gtaw_character', true);
    if (empty($active_character)) {
        return;
    }
    
    // Get the GTAW user ID
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    if (empty($gtaw_user_id)) {
        return;
    }
    
    // Add debug message to check what's happening
    ?>
    <script>
        console.log("Checking for character data...");
    </script>
    <?php
    
    // If we have an active character but no available characters list,
    // we need to re-authenticate to get all characters
    // We'll show a message to the user
    ?>
    <div class="woocommerce-message" role="alert">
        <p><strong>Character switching update:</strong> To enable seamless character switching without logging out, 
        you need to <a href="<?php echo esc_url(gtaw_get_oauth_url()); ?>">re-authenticate once with GTA:W</a>. 
        This is a one-time update.</p>
    </div>
    <?php
}
add_action('woocommerce_account_dashboard', 'gtaw_populate_character_data_for_existing_users', 1);

/**
 * Get characters that already have WordPress accounts
 * 
 * @param array $characters Array of characters from GTA:W
 * @param int $gtaw_user_id The GTA:W user ID
 * @return array Associative array with 'linked' and 'unlinked' character arrays
 */
function gtaw_get_character_account_status($characters, $gtaw_user_id) {
    $result = [
        'linked' => [],
        'unlinked' => []
    ];
    
    // If no characters, return empty arrays
    if (empty($characters) || !is_array($characters)) {
        return $result;
    }
    
    // Get all WordPress users linked to this GTA:W user
    $linked_users = get_users([
        'meta_key' => 'gtaw_user_id',
        'meta_value' => $gtaw_user_id,
    ]);
    
    // Create a lookup of character IDs that have WordPress accounts
    $linked_character_ids = [];
    foreach ($linked_users as $user) {
        $active_char = get_user_meta($user->ID, 'active_gtaw_character', true);
        if (!empty($active_char) && isset($active_char['id'])) {
            $linked_character_ids[$active_char['id']] = $user->ID;
        }
    }
    
    // Additional check: look for character accounts by email pattern
    foreach ($characters as $character) {
        if (empty($character['id']) || isset($linked_character_ids[$character['id']])) {
            continue; // Skip if already found or invalid
        }
        
        // Check if a user exists with the expected email format
        $email = strtolower($character['firstname'] . '.' . $character['lastname']) . '@mail.sa';
        $user = get_user_by('email', $email);
        
        if ($user) {
            // Verify this is a GTA:W user account by checking for gtaw_user_id
            $user_gtaw_id = get_user_meta($user->ID, 'gtaw_user_id', true);
            if ($user_gtaw_id == $gtaw_user_id) {
                $linked_character_ids[$character['id']] = $user->ID;
            }
        }
    }
    
    // Also check for the current user's active character, which definitely has an account
    $current_user_id = get_current_user_id();
    $current_active_char = get_user_meta($current_user_id, 'active_gtaw_character', true);
    
    if (!empty($current_active_char) && isset($current_active_char['id'])) {
        $linked_character_ids[$current_active_char['id']] = $current_user_id;
    }
    
    // Sort characters into linked and unlinked arrays
    foreach ($characters as $character) {
        if (empty($character['id'])) {
            continue;
        }
        
        // Add WordPress user ID to linked characters
        if (isset($linked_character_ids[$character['id']])) {
            $character['wp_user_id'] = $linked_character_ids[$character['id']];
            $result['linked'][] = $character;
        } else {
            $result['unlinked'][] = $character;
        }
    }
    
    return $result;
}

/**
 * Process character account creation
 */
function gtaw_process_character_account_creation() {
    // Check if this is a character account creation request
    if (!isset($_POST['gtaw_create_account']) || !isset($_POST['gtaw_character_nonce'])) {
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['gtaw_character_nonce'], 'gtaw_create_account')) {
        wc_add_notice(__('Security check failed. Please try again.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Check for required fields
    if (empty($_POST['character_id']) || empty($_POST['character_firstname']) || empty($_POST['character_lastname'])) {
        wc_add_notice(__('Missing character information. Please try again.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Get user ID and GTAW user ID
    $user_id = get_current_user_id();
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    
    if (empty($gtaw_user_id)) {
        wc_add_notice(__('Error: Unable to identify your GTA:W account.', 'gtaw-bridge'), 'error');
        return;
    }
    
    // Sanitize inputs
    $character_id = sanitize_text_field($_POST['character_id']);
    $firstname = sanitize_text_field($_POST['character_firstname']);
    $lastname = sanitize_text_field($_POST['character_lastname']);
    
    // Generate the expected email for this character
    $email = strtolower($firstname . '.' . $lastname) . '@mail.sa';
    
    // Check if an account with this email already exists
    $existing_user = get_user_by('email', $email);
    if ($existing_user) {
        // Check if this account belongs to the current GTAW user
        $existing_gtaw_id = get_user_meta($existing_user->ID, 'gtaw_user_id', true);
        
        if ($existing_gtaw_id == $gtaw_user_id) {
            // Account exists and belongs to this GTAW user, update active character
            update_user_meta($existing_user->ID, 'active_gtaw_character', [
                'id' => $character_id,
                'firstname' => $firstname,
                'lastname' => $lastname
            ]);
            
            wc_add_notice(__('Account updated for ' . $firstname . ' ' . $lastname . '.', 'gtaw-bridge'), 'success');
            wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
            exit;
        } else {
            wc_add_notice(__('An account with this character\'s email already exists but is not associated with your GTA:W account.', 'gtaw-bridge'), 'error');
            return;
        }
    }
    
    // Create a username based on the character's name
    $new_username = sanitize_user($firstname . '_' . $lastname);
    
    // Check if the username already exists
    if (get_user_by('login', $new_username)) {
        $new_username .= '_' . time();
        $new_username = sanitize_user($new_username);
    }
    
    // Create the WordPress user
    $new_user_id = wp_insert_user(array(
        'user_login' => $new_username,
        'user_pass'  => wp_generate_password(),
        'first_name' => $firstname,
        'last_name'  => $lastname,
        'user_email' => $email,
    ));
    
    if (is_wp_error($new_user_id)) {
        wc_add_notice(__('Error creating account: ' . $new_user_id->get_error_message(), 'gtaw-bridge'), 'error');
        return;
    }
    
    // Store GTA:W data in user meta
    update_user_meta($new_user_id, 'gtaw_user_id', $gtaw_user_id);
    update_user_meta($new_user_id, 'active_gtaw_character', [
        'id' => $character_id,
        'firstname' => $firstname,
        'lastname' => $lastname
    ]);
    
    // Log the account creation
    gtaw_add_log('oauth', 'Register', "Account created for character {$firstname} {$lastname} (ID: {$character_id})", 'success');
    
    // Add success notice
    wc_add_notice(__('Account created successfully for ' . $firstname . ' ' . $lastname . '.', 'gtaw-bridge'), 'success');
    
    // Redirect to prevent form resubmission
    wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
    exit;
}
add_action('template_redirect', 'gtaw_process_character_account_creation');

// Remove the original character widget to avoid duplication
remove_action('woocommerce_account_dashboard', 'gtaw_add_woocommerce_dashboard_widget');

/**
 * Verify GTAW connection is still valid
 * 
 * @param int $user_id WordPress user ID
 * @return bool True if connection is valid
 */
function gtaw_verify_gtaw_connection($user_id) {
    // Get GTAW user ID
    $gtaw_user_id = get_user_meta($user_id, 'gtaw_user_id', true);
    
    if (empty($gtaw_user_id)) {
        return false;
    }
    
    // Get GTAW connection timestamp
    $last_connection = get_user_meta($user_id, 'gtaw_last_connection', true);
    
    // If no timestamp or it's older than 24 hours, require re-authentication
    if (empty($last_connection) || (time() - intval($last_connection)) > 86400) {
        return false;
    }
    
    return true;
}

/**
 * Update GTAW connection timestamp during OAuth process
 */
function gtaw_update_connection_timestamp($user_data) {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    update_user_meta($user_id, 'gtaw_last_connection', time());
}
add_action('gtaw_oauth_process_started', 'gtaw_update_connection_timestamp');

/**
 * Add security check to character switching
 */
function gtaw_secure_character_switching() {
    // Check if this is a character switch request
    if (!isset($_POST['gtaw_switch_character']) || !isset($_POST['gtaw_character_nonce'])) {
        return;
    }
    
    // Get user ID
    $user_id = get_current_user_id();
    
    // Verify GTAW connection
    if (!gtaw_verify_gtaw_connection($user_id)) {
        // Connection invalid, show notice and redirect to dashboard
        wc_add_notice(__('For security purposes, please re-authenticate with GTA:W before switching characters.', 'gtaw-bridge'), 'error');
        
        // Show re-authentication link
        $auth_url = gtaw_get_oauth_url();
        wc_add_notice(__('Please <a href="' . esc_url($auth_url) . '">click here to re-authenticate</a>.', 'gtaw-bridge'), 'notice');
        
        // Redirect to dashboard
        wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
        exit;
    }
}
add_action('template_redirect', 'gtaw_secure_character_switching', 4); // Run before the regular handler