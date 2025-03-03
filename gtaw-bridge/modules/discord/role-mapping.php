<?php
defined('ABSPATH') or exit;

/* ========= DISCORD ROLE MAPPING MODULE ========= */
/*
 * This module provides functionality to map Discord roles to WordPress roles
 * Features:
 * - Discord role to WordPress role mappings
 * - WordPress role to Discord role mappings
 * - Automatic role synchronization in both directions
 * - Role sync scheduling
 * - Admin UI for role management
 */

/* ========= ADMIN SETTINGS ========= */

// Register role mapping settings
function gtaw_discord_register_role_mapping_settings() {
    // General settings
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_enabled');
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_sync_on_login');
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_sync_schedule');
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_priority_mode');
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_two_way_sync');
    
    // Store the actual role mappings as an array
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_role_mappings', [
        'sanitize_callback' => 'gtaw_sanitize_role_mappings'
    ]);
}
add_action('admin_init', 'gtaw_discord_register_role_mapping_settings');

// Sanitize role mappings before saving
function gtaw_sanitize_role_mappings($input) {
    if (!is_array($input)) {
        return [];
    }
    
    $sanitized = [];
    foreach ($input as $discord_role_id => $wp_role) {
        // Ensure discord role id is numeric and wp role is a valid role
        if (is_numeric($discord_role_id) && array_key_exists($wp_role, wp_roles()->roles)) {
            $sanitized[$discord_role_id] = sanitize_text_field($wp_role);
        }
    }
    
    return $sanitized;
}

/* ========= ROLE SYNC FUNCTIONALITY (Discord → WordPress) ========= */

/**
 * Synchronize a user's WordPress role based on their Discord roles
 * 
 * @param int $user_id WordPress user ID
 * @return bool True on success, false on failure
 */
function gtaw_sync_user_discord_roles($user_id) {
    // Skip if role mapping is disabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1') {
        return false;
    }
    
    // Get user's Discord ID
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    if (empty($discord_id)) {
        return false; // No Discord account linked
    }
    
    // Get user's Discord roles
    $discord_roles = gtaw_get_user_discord_roles($discord_id);
    if (is_wp_error($discord_roles)) {
        gtaw_add_log('discord', 'Role Sync', "Failed to get Discord roles for user ID $user_id: " . 
            $discord_roles->get_error_message(), 'error');
        return false;
    }
    
    // If we got an empty array of roles, that's okay for new users
    // Log it but continue with the process
    if (empty($discord_roles)) {
        gtaw_add_log('discord', 'Role Sync', "User $user_id has no Discord roles yet. Will check role mappings.", 'success');
    }
    
    // Get role mappings
    $role_mappings = get_option('gtaw_discord_role_mappings', []);
    if (empty($role_mappings)) {
        return false; // No mappings configured
    }
    
    // Get all available Discord roles to access their position data
    $all_discord_roles = gtaw_get_discord_roles();
    if (is_wp_error($all_discord_roles)) {
        gtaw_add_log('discord', 'Role Sync', "Failed to get Discord roles: " . $all_discord_roles->get_error_message(), 'error');
        return false;
    }
    
    // Build a position lookup array
    $role_positions = [];
    foreach ($all_discord_roles as $role) {
        $role_positions[$role['id']] = $role['position'];
    }
    
    // Get WP user object
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Determine which WordPress role to assign based on Discord roles
    $priority_mode = get_option('gtaw_discord_rolemapping_priority_mode', 'highest');
    $matched_roles = [];
    
    foreach ($discord_roles as $discord_role_id) {
        if (isset($role_mappings[$discord_role_id])) {
            $matched_roles[$discord_role_id] = [
                'wp_role' => $role_mappings[$discord_role_id],
                'position' => $role_positions[$discord_role_id] ?? 0
            ];
        }
    }
    
    // If no matched roles and user has no Discord roles yet, we'll handle this
    // in the WordPress → Discord direction instead
    if (empty($matched_roles) && empty($discord_roles)) {
        gtaw_add_log('discord', 'Role Sync', "User $user_id has no Discord roles that map to WordPress roles.", 'success');
        // If two-way sync is enabled, we'll rely on that to assign Discord roles
        if (get_option('gtaw_discord_rolemapping_two_way_sync', '0') === '1') {
            gtaw_add_log('discord', 'Role Sync', "Two-way sync is enabled - will check for WordPress → Discord mapping", 'success');
            
            // Get current WordPress role and trigger Discord sync
            if (!empty($user->roles)) {
                $primary_role = reset($user->roles);
                // Trigger sync in the other direction
                gtaw_sync_discord_roles_from_wp($user_id, $primary_role, []);
            }
        }
        return false;
    }
    else if (empty($matched_roles)) {
        // User has Discord roles but none match our mappings
        gtaw_add_log('discord', 'Role Sync', "User $user_id has Discord roles but none map to WordPress roles.", 'success');
        return false;
    }
    
    // Process based on priority mode
    $new_role = '';
    
    if ($priority_mode === 'highest') {
        // Find the highest position Discord role
        $highest_role = null;
        $highest_position = -1;
        
        foreach ($matched_roles as $role_id => $role_data) {
            if ($role_data['position'] > $highest_position) {
                $highest_position = $role_data['position'];
                $highest_role = $role_data['wp_role'];
            }
        }
        
        $new_role = $highest_role;
    } 
    else if ($priority_mode === 'first_match') {
        // Just use the first match in the admin-defined order
        foreach ($role_mappings as $discord_role_id => $wp_role) {
            if (in_array($discord_role_id, $discord_roles)) {
                $new_role = $wp_role;
                break;
            }
        }
    }
    
    if (empty($new_role)) {
        return false;
    }
    
    // Check if the user already has this role
    if (in_array($new_role, $user->roles)) {
        return true; // Role already assigned
    }
    
    // Store current roles for potential back-sync later
    $old_roles = $user->roles;
    
    // Remove existing roles and assign the new role
    $user->set_role($new_role);
    
    gtaw_add_log('discord', 'Role Sync', "User $user_id assigned WordPress role '$new_role' based on Discord roles", 'success');
    
    // If this change was triggered by a Discord role change and two-way sync is enabled,
    // we don't want to trigger a loop, so we'll skip the back-sync
    $skip_back_sync = defined('GTAW_SKIP_BACK_SYNC') && GTAW_SKIP_BACK_SYNC;
    
    if (!$skip_back_sync && get_option('gtaw_discord_rolemapping_two_way_sync', '0') === '1') {
        // We'll define this constant to prevent infinite loops
        define('GTAW_SKIP_BACK_SYNC', true);
        
        // Sync WordPress roles back to Discord in case WP has roles that should add additional Discord roles
        gtaw_sync_discord_roles_from_wp($user_id, $new_role, $old_roles);
    }
    
    return true;
}

/* ========= ROLE SYNC FUNCTIONALITY (WordPress → Discord) ========= */

/**
 * Syncs Discord roles when a WordPress role changes
 *
 * @param int $user_id WordPress user ID
 * @param string $role Role name
 * @param array $old_roles Array of previous roles
 * @return bool True on success, false on failure
 */
function gtaw_sync_discord_roles_from_wp($user_id, $role, $old_roles) {
    // Skip if role mapping is disabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1') {
        return false;
    }
    
    // Skip if two-way sync is disabled
    if (get_option('gtaw_discord_rolemapping_two_way_sync', '0') !== '1') {
        return false;
    }
    
    // Skip if we're in a loop (defined in gtaw_sync_user_discord_roles)
    if (defined('GTAW_SKIP_BACK_SYNC') && GTAW_SKIP_BACK_SYNC) {
        return false;
    }
    
    // Get user's Discord ID
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    if (empty($discord_id)) {
        return false; // No Discord account linked
    }
    
    // Get current Discord member data including roles
    $member_data = gtaw_get_discord_member($discord_id, true);
    if (is_wp_error($member_data)) {
        $error_code = $member_data->get_error_code();
        
        // If the error is just that the user has no roles yet, we can continue
        // Discord API returns 404 if user is not in server
        if ($error_code === 'discord_api_error' && isset($member_data->get_error_data()['status']) && $member_data->get_error_data()['status'] === 404) {
            gtaw_add_log('discord', 'Role Sync', "User $user_id not found in Discord server, can't sync roles.", 'error');
            return false;
        }
        
        gtaw_add_log('discord', 'Error', "Failed to get Discord member data: " . $member_data->get_error_message(), 'error');
        return false;
    }
    
    // Get role mappings (we need to invert it for WordPress → Discord direction)
    $role_mappings = get_option('gtaw_discord_role_mappings', []);
    $wp_to_discord_mappings = [];
    
    // Create inverted mapping (WordPress role → Discord role IDs)
    foreach ($role_mappings as $discord_role_id => $wp_role) {
        if (!isset($wp_to_discord_mappings[$wp_role])) {
            $wp_to_discord_mappings[$wp_role] = [];
        }
        $wp_to_discord_mappings[$wp_role][] = $discord_role_id;
    }
    
    // Determine which Discord roles to add based on current WordPress role
    $discord_roles_to_add = [];
    if (isset($wp_to_discord_mappings[$role])) {
        $discord_roles_to_add = $wp_to_discord_mappings[$role];
    }
    
    // Get current Discord roles (ensure it's an array even if empty)
    $current_discord_roles = isset($member_data['roles']) && is_array($member_data['roles']) ? $member_data['roles'] : [];
    
    // Determine roles to remove (based on old WordPress roles that are no longer assigned)
    $discord_roles_to_remove = [];
    foreach ($old_roles as $old_role) {
        // Skip the role that's still assigned
        if ($old_role === $role) continue;
        
        // Add corresponding Discord roles to the removal list
        if (isset($wp_to_discord_mappings[$old_role])) {
            $discord_roles_to_remove = array_merge($discord_roles_to_remove, $wp_to_discord_mappings[$old_role]);
        }
    }
    
    // Keep track of role changes for logging
    $roles_added = [];
    $roles_removed = [];
    
    // Calculate the final set of roles
    $new_roles = array_unique(array_merge(
        // Keep roles that don't need to be removed
        array_diff($current_discord_roles, $discord_roles_to_remove),
        // Add new roles
        $discord_roles_to_add
    ));
    
    // Find differences for logging
    $roles_added = array_diff($new_roles, $current_discord_roles);
    $roles_removed = array_diff($current_discord_roles, $new_roles);
    
    // Skip if no changes needed (no roles added and no roles removed)
    if (empty($roles_added) && empty($roles_removed)) {
        return true;
    }
    
    // Update Discord member roles
    $guild_id = get_option('gtaw_discord_guild_id', '');
    if (empty($guild_id)) {
        gtaw_add_log('discord', 'Error', "Discord role sync failed: Missing guild ID", 'error');
        return false;
    }
    
    gtaw_add_log('discord', 'Role Sync', "Attempting to update Discord roles for user $user_id ($discord_id) with roles: " . implode(', ', $new_roles), 'success');

    $result = gtaw_discord_api_request(
        "guilds/{$guild_id}/members/{$discord_id}", 
        [
            'body' => json_encode(['roles' => $new_roles]),
            'headers' => ['Content-Type' => 'application/json']
        ],
        'PATCH'
    );

    if (is_wp_error($result)) {
        gtaw_add_log('discord', 'Error', "Failed to update Discord roles: " . $result->get_error_message(), 'error');
        return false;
    }
    
    // Log success
    $added_count = count($roles_added);
    $removed_count = count($roles_removed);
    gtaw_add_log('discord', 'Role Sync', "Updated Discord roles for user $user_id ($discord_id): Added $added_count, Removed $removed_count", 'success');
    
    return true;
}

/**
 * Immediately sync WordPress roles to Discord when account is linked
 * This function handles the special case of a new Discord user with no roles
 */
function gtaw_sync_roles_on_discord_link_for_new_users($user_id, $discord_id) {
    // Only run if role mapping and two-way sync are enabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1' || 
        get_option('gtaw_discord_rolemapping_two_way_sync', '0') !== '1') {
        return;
    }
    
    // Get the user's WordPress role
    $user = get_userdata($user_id);
    if (!$user) {
        return;
    }
    
    // Get the user's current roles
    $roles = $user->roles;
    if (empty($roles)) {
        return;
    }
    
    // Get the primary role (first one in the array)
    $primary_role = reset($roles);
    
    // Call the sync function with empty old_roles (we're adding fresh roles)
    gtaw_sync_discord_roles_from_wp($user_id, $primary_role, []);
    
    gtaw_add_log('discord', 'Role Sync', "Triggered WordPress → Discord sync for new user $user_id with role $primary_role", 'success');
}

// Add this hook with higher priority (9) to run before the regular sync
add_action('gtaw_discord_account_linked', 'gtaw_sync_roles_on_discord_link_for_new_users', 9, 2);

/* ========= SCHEDULING & HOOKS ========= */

// Schedule role sync for all users
function gtaw_schedule_discord_role_sync() {
    if (!wp_next_scheduled('gtaw_discord_role_sync_event')) {
        wp_schedule_event(time(), 'hourly', 'gtaw_discord_role_sync_event');
    }
}
add_action('admin_init', 'gtaw_schedule_discord_role_sync');

// Clear scheduled events on deactivation
function gtaw_clear_discord_role_sync_schedule() {
    wp_clear_scheduled_hook('gtaw_discord_role_sync_event');
}
register_deactivation_hook(plugin_basename(GTAW_DISCORD_PATH . '../gtaw-discord.php'), 'gtaw_clear_discord_role_sync_schedule');

// Hook for when a user connects their Discord account
function gtaw_sync_roles_on_discord_link($user_id, $discord_id) {
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1') {
        return;
    }
    
    gtaw_sync_user_discord_roles($user_id);
}
add_action('gtaw_discord_account_linked', 'gtaw_sync_roles_on_discord_link', 10, 2);

// Hook for user login
function gtaw_sync_roles_on_login($user_login, $user) {
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1' || 
        get_option('gtaw_discord_rolemapping_sync_on_login', '0') !== '1') {
        return;
    }
    
    gtaw_sync_user_discord_roles($user->ID);
}
add_action('wp_login', 'gtaw_sync_roles_on_login', 10, 2);

// Hook for WordPress role changes (WordPress → Discord)
function gtaw_detect_role_changes($user_id, $new_role, $old_roles) {
    gtaw_sync_discord_roles_from_wp($user_id, $new_role, $old_roles);
}
add_action('set_user_role', 'gtaw_detect_role_changes', 10, 3);

// Scheduled sync for all users
function gtaw_run_scheduled_role_sync() {
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1') {
        return;
    }
    
    // Get all users with Discord IDs
    $users = get_users([
        'meta_key' => 'discord_ID',
        'fields' => ['ID']
    ]);
    
    if (empty($users)) {
        return;
    }
    
    $count = 0;
    foreach ($users as $user) {
        $success = gtaw_sync_user_discord_roles($user->ID);
        if ($success) {
            $count++;
        }
        
        // Sleep briefly to avoid rate limits
        usleep(100000); // 100ms
    }
    
    gtaw_add_log('discord', 'Scheduled Sync', "Completed scheduled role sync for $count users", 'success');
}
add_action('gtaw_discord_role_sync_event', 'gtaw_run_scheduled_role_sync');

/* ========= AJAX ENDPOINTS ========= */

// AJAX endpoint for manual role sync
function gtaw_ajax_sync_discord_roles() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    // Verify nonce
    check_ajax_referer('gtaw_discord_roles_nonce', 'nonce');
    
    // Run the sync
    $users = get_users([
        'meta_key' => 'discord_ID',
        'fields' => ['ID']
    ]);
    
    if (empty($users)) {
        wp_send_json_error('No users with linked Discord accounts found');
    }
    
    $success_count = 0;
    $failed_count = 0;
    
    foreach ($users as $user) {
        $success = gtaw_sync_user_discord_roles($user->ID);
        if ($success) {
            $success_count++;
        } else {
            $failed_count++;
        }
        
        // Sleep briefly to avoid rate limits
        usleep(100000); // 100ms
    }
    
    wp_send_json_success([
        'message' => "Role sync completed. $success_count users updated, $failed_count failed.",
        'success_count' => $success_count,
        'failed_count' => $failed_count
    ]);
}
add_action('wp_ajax_gtaw_sync_discord_roles', 'gtaw_ajax_sync_discord_roles');

// AJAX endpoint to refresh Discord roles list
function gtaw_ajax_refresh_discord_roles() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    check_ajax_referer('gtaw_discord_roles_nonce', 'nonce');
    
    $roles = gtaw_fetch_discord_roles();
    
    if (is_wp_error($roles)) {
        wp_send_json_error($roles->get_error_message());
    }
    
    wp_send_json_success([
        'roles' => $roles,
        'message' => 'Discord roles refreshed successfully.'
    ]);
}
add_action('wp_ajax_gtaw_refresh_discord_roles', 'gtaw_ajax_refresh_discord_roles');

// AJAX endpoint to sync a single user's Discord roles
function gtaw_ajax_sync_user_discord_roles() {
    // Security checks
    if (!current_user_can('edit_users')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    check_ajax_referer('gtaw_sync_user_roles_nonce', 'nonce');
    
    // Get user ID
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) {
        wp_send_json_error('Invalid user ID');
    }
    
    // Sync the roles
    $success = gtaw_sync_user_discord_roles($user_id);
    
    if ($success) {
        wp_send_json_success([
            'message' => 'Roles synced successfully!',
            'refresh' => true
        ]);
    } else {
        // Even if Discord → WordPress sync failed, try WordPress → Discord sync
        $user = get_userdata($user_id);
        if ($user && !empty($user->roles)) {
            $primary_role = reset($user->roles);
            $wp_to_discord_success = gtaw_sync_discord_roles_from_wp($user_id, $primary_role, []);
            
            if ($wp_to_discord_success) {
                wp_send_json_success([
                    'message' => 'Roles synced from WordPress to Discord successfully!',
                    'refresh' => true
                ]);
                return;
            }
        }
        
        wp_send_json_error('Failed to sync Discord roles. Check logs for details.');
    }
}
add_action('wp_ajax_gtaw_sync_user_discord_roles', 'gtaw_ajax_sync_user_discord_roles');

/* ========= ADMIN UI ========= */

// Register tab in the Discord settings
function gtaw_discord_role_mapping_tab() {
    // Get WordPress roles
    $wp_roles = wp_roles();
    
    // Get Discord roles
    $discord_roles = gtaw_get_discord_roles();
    $role_error = is_wp_error($discord_roles);
    
    // Get current mappings
    $role_mappings = get_option('gtaw_discord_role_mappings', []);
    
    // Settings
    $enabled = get_option('gtaw_discord_rolemapping_enabled', '0');
    $sync_on_login = get_option('gtaw_discord_rolemapping_sync_on_login', '1');
    $priority_mode = get_option('gtaw_discord_rolemapping_priority_mode', 'highest');
    $two_way_sync = get_option('gtaw_discord_rolemapping_two_way_sync', '0');
    
    // Create nonce for AJAX requests
    $nonce = wp_create_nonce('gtaw_discord_roles_nonce');
    ?>
    <form method="post" action="options.php">
        <?php 
            settings_fields('gtaw_discord_rolemapping_group');
            do_settings_sections('gtaw_discord_rolemapping_group');
        ?>
        
        <div class="role-mapping-settings" style="margin-bottom: 20px; background: #f0f0f0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
            <h3>Role Mapping Settings</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable Role Mapping</th>
                    <td>
                        <input type="checkbox" name="gtaw_discord_rolemapping_enabled" value="1" <?php checked($enabled, '1'); ?> />
                        <p class="description">Automatically assign WordPress roles based on Discord roles</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Sync on Login</th>
                    <td>
                        <input type="checkbox" name="gtaw_discord_rolemapping_sync_on_login" value="1" <?php checked($sync_on_login, '1'); ?> />
                        <p class="description">Update user roles when they log in</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Role Priority Mode</th>
                    <td>
                        <select name="gtaw_discord_rolemapping_priority_mode">
                            <option value="highest" <?php selected($priority_mode, 'highest'); ?>>Highest Position (Discord hierarchy)</option>
                            <option value="first_match" <?php selected($priority_mode, 'first_match'); ?>>First Match (in mapping order)</option>
                        </select>
                        <p class="description">How to determine which role to assign when a user has multiple Discord roles</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Two-Way Synchronization</th>
                    <td>
                        <input type="checkbox" name="gtaw_discord_rolemapping_two_way_sync" value="1" <?php checked($two_way_sync, '1'); ?> />
                        <p class="description">Also update Discord roles when WordPress roles change. <strong>Requires Discord "Manage Roles" permission</strong></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="role-mapping-controls" style="margin-bottom: 20px;">
            <button type="button" id="refresh-discord-roles" class="button">Refresh Discord Roles</button>
            <button type="button" id="sync-all-users" class="button">Sync All User Roles</button>
            <span id="role-mapping-status" style="margin-left: 10px; display: inline-block;"></span>
        </div>
        
        <?php if ($role_error): ?>
            <div class="notice notice-error inline">
                <p>Error retrieving Discord roles: <?php echo esc_html($discord_roles->get_error_message()); ?></p>
                <p>Please check your Discord Bot Token and Guild ID in the Settings tab.</p>
            </div>
        <?php else: ?>
            <div class="role-mapping-info notice notice-info inline" style="margin-bottom: 20px;">
                <p><strong>How Two-Way Role Mapping Works:</strong></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li>Discord → WordPress: When users' Discord roles change, their WordPress role will be updated accordingly</li>
                    <li>WordPress → Discord: When users' WordPress role changes, their Discord roles will be updated accordingly</li>
                    <li>Bot Permissions: Your Discord bot must have the "Manage Roles" permission</li>
                    <li>Role Hierarchy: Your bot's role must be higher than any roles it manages</li>
                </ul>
            </div>
            
            <div class="role-mapping-table">
                <h3>Discord to WordPress Role Mapping</h3>
                <p>Map Discord roles to WordPress roles. Users will be assigned WordPress roles based on their Discord roles.</p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Discord Role</th>
                            <th>WordPress Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($discord_roles as $role): ?>
                            <?php if ($role['name'] === '@everyone') continue; // Skip @everyone role ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php if (!empty($role['color'])): ?>
                                            <span style="display: inline-block; width: 15px; height: 15px; background-color: #<?php echo dechex($role['color']); ?>; border-radius: 3px;"></span>
                                        <?php endif; ?>
                                        <strong><?php echo esc_html($role['name']); ?></strong>
                                        <code style="font-size: 0.8em;"><?php echo esc_html($role['id']); ?></code>
                                    </div>
                                </td>
                                <td>
                                    <select name="gtaw_discord_role_mappings[<?php echo esc_attr($role['id']); ?>]">
                                        <option value="">— No Role —</option>
                                        <?php foreach ($wp_roles->role_names as $role_key => $role_name): ?>
                                            <option value="<?php echo esc_attr($role_key); ?>" <?php selected(isset($role_mappings[$role['id']]) ? $role_mappings[$role['id']] : '', $role_key); ?>>
                                                <?php echo esc_html($role_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <?php submit_button('Save Role Mappings'); ?>
    </form>
    
    <script>
    jQuery(document).ready(function($) {
        // Refresh Discord roles
        $('#refresh-discord-roles').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $status = $('#role-mapping-status');
            
            $button.prop('disabled', true);
            $status.html('<span style="color: blue;">Refreshing Discord roles...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gtaw_refresh_discord_roles',
                    nonce: '<?php echo $nonce; ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">' + response.data.message + '</span>');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $status.html('<span style="color: red;">Error: ' + response.data + '</span>');
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.html('<span style="color: red;">Connection error. Please try again.</span>');
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Sync all users
        $('#sync-all-users').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('This will update WordPress roles for all users with linked Discord accounts. Continue?')) {
                return;
            }
            
            const $button = $(this);
            const $status = $('#role-mapping-status');
            
            $button.prop('disabled', true);
            $status.html('<span style="color: blue;">Syncing user roles... This may take a while.</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gtaw_sync_discord_roles',
                    nonce: '<?php echo $nonce; ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">' + response.data.message + '</span>');
                    } else {
                        $status.html('<span style="color: red;">Error: ' + response.data + '</span>');
                    }
                    $button.prop('disabled', false);
                },
                error: function() {
                    $status.html('<span style="color: red;">Connection error. Please try again.</span>');
                    $button.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}

// Register the tab callback
add_filter('gtaw_discord_settings_tabs', function($tabs) {
    $tabs['role-mapping'] = [
        'title' => 'Role Mapping',
        'callback' => 'gtaw_discord_role_mapping_tab'
    ];
    return $tabs;
});