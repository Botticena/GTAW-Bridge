<?php
defined('ABSPATH') or exit;

/* ========= DISCORD ROLE MAPPING MODULE ========= */
/*
 * This module provides functionality to map Discord roles to WordPress roles
 * With optimizations for:
 * - Preventing infinite synchronization loops
 * - Rate limit protection with retry mechanisms
 * - Conflict resolution for role priorities
 * - Enhanced error recovery
 * - Performance improvements for large servers
 *
 * @version 2.0
 */

/**
 * Constants for role mapping module
 */
define('GTAW_DISCORD_ROLE_MAPPING_VERSION', '2.0');
define('GTAW_DISCORD_ROLE_SYNC_LOCK_DURATION', 10); // Reduced from 30 to 10 seconds
define('GTAW_DISCORD_ROLE_SYNC_BATCH_SIZE', 25);    // Users per batch

/* ========= ADMIN SETTINGS ========= */

/**
 * Register role mapping settings
 */
function gtaw_discord_register_role_mapping_settings() {
    // General settings
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_enabled');
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_sync_on_login');
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_sync_schedule');
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_priority_mode');
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_two_way_sync');
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_rolemapping_protect_admins', 'boolval');
    
    // Store the actual role mappings as an array
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_role_mappings', [
        'sanitize_callback' => 'gtaw_sanitize_role_mappings'
    ]);
    
    // Background sync settings
    register_setting('gtaw_discord_rolemapping_group', 'gtaw_discord_role_sync_frequency', [
        'default' => 'daily',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
}
add_action('admin_init', 'gtaw_discord_register_role_mapping_settings');

/**
 * Sanitize role mappings before saving
 *
 * @param array $input Input array of mappings
 * @return array Sanitized mappings
 */
function gtaw_sanitize_role_mappings($input) {
    if (!is_array($input)) {
        return [];
    }
    
    $sanitized = [];
    foreach ($input as $discord_role_id => $wp_role) {
        // Ensure discord role id is valid and wp role exists
        if (is_numeric($discord_role_id) && array_key_exists($wp_role, wp_roles()->roles)) {
            $sanitized[$discord_role_id] = sanitize_text_field($wp_role);
        }
    }
    
    return $sanitized;
}

/* ========= SYNCHRONIZATION MANAGEMENT ========= */

/**
 * Lock mechanism to prevent concurrent role syncs for the same user
 *
 * @param int $user_id WordPress user ID
 * @param string $direction 'discord_to_wp' or 'wp_to_discord'
 * @return bool Whether lock was acquired
 */
function gtaw_acquire_role_sync_lock($user_id, $direction) {
    $lock_key = "gtaw_role_sync_lock_{$user_id}_{$direction}";
    
    // Check if there's an existing lock
    $lock = get_transient($lock_key);
    if ($lock !== false) {
        return false; // Lock exists
    }
    
    // Create a new lock with timestamp
    $lock_data = [
        'time' => time(),
        'process_id' => md5(uniqid(mt_rand(), true))
    ];
    
    // Set the lock
    set_transient($lock_key, $lock_data, GTAW_DISCORD_ROLE_SYNC_LOCK_DURATION);
    
    return true;
}

/**
 * Release a role sync lock
 *
 * @param int $user_id WordPress user ID
 * @param string $direction 'discord_to_wp' or 'wp_to_discord'
 */
function gtaw_release_role_sync_lock($user_id, $direction) {
    $lock_key = "gtaw_role_sync_lock_{$user_id}_{$direction}";
    delete_transient($lock_key);
}

/**
 * Track role sync attempts to prevent loops
 *
 * @param int $user_id WordPress user ID
 * @return bool Whether safe to proceed with sync
 */
function gtaw_check_sync_safety($user_id, $direction = 'any', $reset = false) {
    $key = 'gtaw_role_sync_count_' . $user_id . '_' . $direction;
    
    // If reset requested, clear the counter and return true
    if ($reset) {
        delete_transient($key);
        return true;
    }
    
    $count = get_transient($key) ?: 0;
    
    // If too many syncs in a short period, stop
    if ($count >= 3) {
        gtaw_add_log('discord', 'Role Sync', "Too many sync attempts for user ID {$user_id} ({$direction}) - possible loop detected", 'error');
        return false;
    }
    
    // Increment counter with 1 minute expiration (reduced from 5 minutes)
    set_transient($key, $count + 1, 1 * MINUTE_IN_SECONDS);
    
    return true;
}

/* ========= ROLE SYNC FUNCTIONALITY (Discord → WordPress) ========= */

/**
 * Synchronize a user's WordPress role based on their Discord roles
 * 
 * @param int $user_id WordPress user ID
 * @param bool $force Force sync even if already in progress
 * @return bool|WP_Error True on success, WP_Error on failure, false if skipped
 */
function gtaw_sync_user_discord_roles($user_id, $force = false) {
    // Start timing
    $start_time = microtime(true);
    
    // Skip if role mapping is disabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1') {
        return new WP_Error('disabled', 'Role mapping is disabled');
    }
    
    // Skip if no user ID
    if (empty($user_id)) {
        return new WP_Error('invalid_user', 'Invalid user ID');
    }
    
    // Reset sync safety counter if forced
    if ($force) {
        gtaw_check_sync_safety($user_id, 'discord_to_wp', true);
    }
    
    // Check if there's an active lock unless forcing
    if (!$force && !gtaw_acquire_role_sync_lock($user_id, 'discord_to_wp')) {
        return false; // Skip if already in progress
    }
    
    // Check for sync loops
    if (!gtaw_check_sync_safety($user_id, 'discord_to_wp')) {
        gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
        return new WP_Error('sync_loop', 'Too many sync attempts, possible loop detected');
    }
    
    // Get user's Discord ID
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    if (empty($discord_id)) {
        gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
        return new WP_Error('no_discord_account', 'No Discord account linked');
    }
    
    // Get user's Discord roles - force fresh data
    $discord_roles = gtaw_get_user_discord_roles($discord_id, true);
    
    if (is_wp_error($discord_roles)) {
        $error_code = $discord_roles->get_error_code();
        $error_message = $discord_roles->get_error_message();
        
        // Only log real errors, not "user not found"
        if ($error_code !== 'member_not_found') {
            gtaw_add_log('discord', 'Role Sync', "Failed to get Discord roles for user ID {$user_id}: {$error_message}", 'error');
        }
        
        gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
        return $discord_roles;
    }
    
    // If we got an empty array of roles, that's normal for new users
    if (empty($discord_roles)) {
        gtaw_add_log('discord', 'Role Sync', "User {$user_id} has no Discord roles. Checking role mappings.", 'success');
    }
    
    // Get role mappings
    $role_mappings = get_option('gtaw_discord_role_mappings', []);
    if (empty($role_mappings)) {
        gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
        return new WP_Error('no_mappings', 'No role mappings configured');
    }
    
    // Get all available Discord roles to access their position data
    $all_discord_roles = gtaw_get_discord_roles();
    if (is_wp_error($all_discord_roles)) {
        gtaw_add_log('discord', 'Role Sync', "Failed to get Discord roles: " . $all_discord_roles->get_error_message(), 'error');
        gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
        return $all_discord_roles;
    }
    
    // Build a position lookup array
    $role_positions = [];
    foreach ($all_discord_roles as $role) {
        $role_positions[$role['id']] = $role['position'];
    }
    
    // Get WP user object
    $user = get_userdata($user_id);
    if (!$user) {
        gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
        return new WP_Error('invalid_user', 'User not found');
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
    
    // If no matched roles and user has no Discord roles yet, handle WordPress → Discord direction
    if (empty($matched_roles) && empty($discord_roles)) {
        gtaw_add_log('discord', 'Role Sync', "User {$user_id} has no Discord roles that map to WordPress roles.", 'info');
        
        gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
        
        // If two-way sync is enabled, we'll rely on that to assign Discord roles
        if (get_option('gtaw_discord_rolemapping_two_way_sync', '0') === '1') {
            gtaw_add_log('discord', 'Role Sync', "Two-way sync enabled - trying WordPress → Discord mapping", 'info');
            
            // Avoid infinite loops by using a different lock
            if (gtaw_acquire_role_sync_lock($user_id, 'wp_to_discord')) {
                // Get current WordPress role and trigger Discord sync
                if (!empty($user->roles)) {
                    $primary_role = reset($user->roles);
                    // Trigger sync in the other direction
                    $result = gtaw_sync_discord_roles_from_wp($user_id, $primary_role, []);
                    gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
                    return $result;
                }
                gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
            }
        }
        
        // Nothing to do
        return true;
    }
    elseif (empty($matched_roles)) {
        // User has Discord roles but none match our mappings
        gtaw_add_log('discord', 'Role Sync', "User {$user_id} has Discord roles but none map to WordPress roles.", 'info');
        gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
        return true;
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
        gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
        return new WP_Error('no_role_determined', 'Could not determine appropriate WordPress role');
    }
    
    // Check if the user already has this role
    if (in_array($new_role, $user->roles)) {
        gtaw_add_log('discord', 'Role Sync', "User {$user_id} already has the WordPress role '{$new_role}'", 'info');
        gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
        return true; // Role already assigned
    }
    
    // Store current roles for potential back-sync later
    $old_roles = $user->roles;
    
    // Check admin protection settings
    $protect_admins = get_option('gtaw_discord_rolemapping_protect_admins', true);
    $is_admin = in_array('administrator', $user->roles);
    
    // Check if user currently has administrator role and protection is enabled
    if ($protect_admins && $is_admin && $new_role !== 'administrator') {
        // Log the attempt but don't change the role
        gtaw_add_log('discord', 'Role Sync', "Protected admin user {$user_id} from role downgrade. Discord role would have assigned '{$new_role}'", 'warning');
        
        // Possibly add a Discord role in the other direction if two-way sync is enabled
        if (get_option('gtaw_discord_rolemapping_two_way_sync', '0') === '1') {
            // Tell the other sync direction not to trigger a loop
            update_user_meta($user_id, 'gtaw_skip_discord_sync', '1');
            
            // Release this lock
            gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
            
            // Trigger Discord sync if we can acquire that lock
            if (gtaw_acquire_role_sync_lock($user_id, 'wp_to_discord')) {
                $result = gtaw_sync_discord_roles_from_wp($user_id, 'administrator', $old_roles);
                gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
                return $result;
            }
        }
        
        return true;
    }
    
    // Apply the role change
    $user->set_role($new_role);
    
    // Calculate execution time
    $execution_time = microtime(true) - $start_time;
    
    // Log the role change with timing information
    gtaw_add_log(
        'discord', 
        'Role Sync', 
        sprintf(
            "User %d assigned WordPress role '%s' based on Discord roles (%.2fs)",
            $user_id,
            $new_role,
            $execution_time
        ), 
        'success'
    );
    
    // Handle two-way sync if enabled
    if (get_option('gtaw_discord_rolemapping_two_way_sync', '0') === '1') {
        // Check if we should skip back-sync
        $skip_back_sync = get_user_meta($user_id, 'gtaw_skip_discord_sync', true);
        
        if ($skip_back_sync) {
            // If we were told to skip, remove the flag and don't sync back
            delete_user_meta($user_id, 'gtaw_skip_discord_sync');
        } else {
            // Tell the other direction not to trigger a loop
            update_user_meta($user_id, 'gtaw_skip_wp_sync', '1');
            
            // Release this lock
            gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
            
            // Only sync back if we can acquire the other lock
            if (gtaw_acquire_role_sync_lock($user_id, 'wp_to_discord')) {
                // Sync WordPress roles back to Discord in case WP has roles that should add additional Discord roles
                $result = gtaw_sync_discord_roles_from_wp($user_id, $new_role, $old_roles);
                gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
                return $result;
            }
            return true;
        }
    }
    
    // Release the lock
    gtaw_release_role_sync_lock($user_id, 'discord_to_wp');
    
    return true;
}

/* ========= ROLE SYNC FUNCTIONALITY (WordPress → Discord) ========= */

/**
 * Syncs Discord roles when a WordPress role changes
 *
 * @param int $user_id WordPress user ID
 * @param string $role Role name
 * @param array $old_roles Array of previous roles
 * @return bool|WP_Error True on success, WP_Error on failure, false if skipped
 */
function gtaw_sync_discord_roles_from_wp($user_id, $role, $old_roles) {
    // Start timing
    $start_time = microtime(true);
    
    // Skip if role mapping is disabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1') {
        return new WP_Error('disabled', 'Role mapping is disabled');
    }
    
    // Skip if two-way sync is disabled
    if (get_option('gtaw_discord_rolemapping_two_way_sync', '0') !== '1') {
        return new WP_Error('two_way_disabled', 'Two-way sync is disabled');
    }
    
    // Reset sync safety counter if forced
    if ($force) {
        gtaw_check_sync_safety($user_id, 'wp_to_discord', true);
    }
    
    // Skip if we were told to skip this sync
    $skip_sync = get_user_meta($user_id, 'gtaw_skip_wp_sync', true);
    if ($skip_sync) {
        delete_user_meta($user_id, 'gtaw_skip_wp_sync');
        return false;
    }
    
    // Skip if no user ID
    if (empty($user_id)) {
        return new WP_Error('invalid_user', 'Invalid user ID');
    }
    
    // Check if we can acquire a lock
    if (!gtaw_acquire_role_sync_lock($user_id, 'wp_to_discord')) {
        return false; // Skip if already in progress
    }
    
    // Check for sync loops
    if (!gtaw_check_sync_safety($user_id, 'wp_to_discord')) {
        gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
        return new WP_Error('sync_loop', 'Too many sync attempts, possible loop detected');
    }
    
    // Get user's Discord ID
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    if (empty($discord_id)) {
        gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
        return new WP_Error('no_discord_account', 'No Discord account linked');
    }
    
    // Get current Discord member data including roles
    $member_data = gtaw_get_discord_member($discord_id, true);
    $current_discord_roles = [];

    if (is_wp_error($member_data)) {
        $error_code = $member_data->get_error_code();
        
        // If the user is not in the server
        if ($error_code === 'member_not_found') {
            // For normal syncs, log and exit
            if (!isset($force) || !$force) {
                gtaw_add_log('discord', 'Role Sync', "User {$user_id} not found in Discord server, can't sync roles.", 'error');
                gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
                return $member_data;
            }
            
            // For forced syncs (new accounts), continue with empty roles array
            gtaw_add_log('discord', 'Role Sync', "User {$user_id} not in server yet, but continuing due to force flag", 'warning');
        } else {
            gtaw_add_log('discord', 'Error', "Failed to get Discord member data: " . $member_data->get_error_message(), 'error');
            gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
            return $member_data;
        }
    } else {
        // Get current Discord roles (ensure it's an array even if empty)
        $current_discord_roles = isset($member_data['roles']) && is_array($member_data['roles']) ? $member_data['roles'] : [];
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
        gtaw_add_log('discord', 'Role Sync', "No Discord role changes needed for user {$user_id}", 'info');
        gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
        return true;
    }
    
    // Update Discord member roles
    $guild_id = GTAW_Discord_API::get_instance()->get_guild_id();
    if (empty($guild_id)) {
        gtaw_add_log('discord', 'Error', "Discord role sync failed: Missing guild ID", 'error');
        gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
        return new WP_Error('missing_guild_id', 'Discord Guild ID is required');
    }
    
    gtaw_add_log(
        'discord', 
        'Role Sync', 
        sprintf(
            "Updating Discord roles for user %s (%s) - Adding: %d, Removing: %d",
            $user_id,
            $discord_id,
            count($roles_added),
            count($roles_removed)
        ), 
        'info'
    );

    $result = gtaw_discord_api_request(
        "guilds/{$guild_id}/members/{$discord_id}", 
        [
            'body' => json_encode(['roles' => $new_roles]),
            'headers' => ['Content-Type' => 'application/json']
        ],
        'PATCH'
    );

    if (is_wp_error($result)) {
        $error_message = $result->get_error_message();
        $error_code = $result->get_error_code();
        $error_data = $result->get_error_data();
        
        // Look for specific error codes to give better error messages
        if (isset($error_data['status']) && $error_data['status'] === 403) {
            gtaw_add_log('discord', 'Error', "Failed to update Discord roles: Insufficient permissions. Make sure your bot has the 'Manage Roles' permission and its role is higher than the roles you're trying to manage.", 'error');
        } else {
            gtaw_add_log('discord', 'Error', "Failed to update Discord roles: {$error_message}", 'error');
        }
        
        gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
        return $result;
    }
    
    // Calculate execution time
    $execution_time = microtime(true) - $start_time;
    
    // Log success with timing information
    $added_count = count($roles_added);
    $removed_count = count($roles_removed);
    gtaw_add_log(
        'discord', 
        'Role Sync', 
        sprintf(
            "Updated Discord roles for user %s (%s): Added %d, Removed %d (%.2fs)",
            $user_id,
            $discord_id,
            $added_count,
            $removed_count,
            $execution_time
        ), 
        'success'
    );
    
    // Release the lock
    gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
    
    return true;
}

/**
 * Immediately sync WordPress roles to Discord when account is linked
 * This function handles the special case of a new Discord user with no roles
 *
 * @param int $user_id WordPress user ID
 * @param string $discord_id Discord user ID
 */
function gtaw_sync_roles_on_discord_link_for_new_users($user_id, $discord_id) {
    // Only run if role mapping and two-way sync are enabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1' || 
        get_option('gtaw_discord_rolemapping_two_way_sync', '0') !== '1') {
        return;
    }
    
    // Reset any existing sync safety counters
    gtaw_check_sync_safety($user_id, 'wp_to_discord', true);
    gtaw_check_sync_safety($user_id, 'discord_to_wp', true);
    
    // Skip if we can't acquire a lock
    if (!gtaw_acquire_role_sync_lock($user_id, 'wp_to_discord')) {
        return;
    }
    
    // Get the user's WordPress role
    $user = get_userdata($user_id);
    if (!$user) {
        gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
        return;
    }
    
    // Give time for the Discord API to register the user
    sleep(2);
    
    // Get the user's current roles
    $roles = $user->roles;
    if (empty($roles)) {
        // User has no roles - look up the default WordPress role
        $default_role = get_option('default_role', 'subscriber');
        
        if (!empty($default_role)) {
            $roles = [$default_role];
            gtaw_add_log('discord', 'Role Sync', "Using default role '{$default_role}' for user {$user_id}", 'info');
        } else {
            gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
            return;
        }
    }
    
    // Get the primary role (first one in the array)
    $primary_role = reset($roles);
    
    // Clear all Discord-related caches for this user to ensure fresh data
    gtaw_clear_discord_user_cache($discord_id);
    
    // Direct role mapping without checking member existence
    gtaw_direct_discord_role_assignment($user_id, $discord_id, $primary_role);
    
    gtaw_release_role_sync_lock($user_id, 'wp_to_discord');
}

/**
 * Directly assign Discord roles based on WordPress role
 * This bypasses the standard flow and works even for new users not yet in server
 *
 * @param int $user_id WordPress user ID
 * @param string $discord_id Discord user ID
 * @param string $wp_role WordPress role to map from
 * @return bool|WP_Error Result of operation
 */
function gtaw_direct_discord_role_assignment($user_id, $discord_id, $wp_role) {
    // Log what we're doing
    gtaw_add_log('discord', 'Role Sync', "Attempting direct Discord role assignment for user {$user_id} with role {$wp_role}", 'info');
    
    // Get role mappings
    $role_mappings = get_option('gtaw_discord_role_mappings', []);
    if (empty($role_mappings)) {
        return new WP_Error('no_mappings', 'No role mappings configured');
    }
    
    // Create inverted mapping (WordPress role → Discord role IDs)
    $wp_to_discord_mappings = [];
    foreach ($role_mappings as $discord_role_id => $mapped_wp_role) {
        if (!isset($wp_to_discord_mappings[$mapped_wp_role])) {
            $wp_to_discord_mappings[$mapped_wp_role] = [];
        }
        $wp_to_discord_mappings[$mapped_wp_role][] = $discord_role_id;
    }
    
    // Determine which Discord roles to add based on current WordPress role
    if (!isset($wp_to_discord_mappings[$wp_role])) {
        return new WP_Error('no_mapping_for_role', "No Discord roles mapped to WordPress role '{$wp_role}'");
    }
    
    $discord_roles_to_add = $wp_to_discord_mappings[$wp_role];
    
    // Get Guild ID for Discord server
    $guild_id = get_option('gtaw_discord_guild_id', '');
    if (empty($guild_id)) {
        return new WP_Error('missing_guild_id', 'Discord Guild ID is required');
    }
    
    // Make direct API call to Discord
    $result = gtaw_discord_api_request(
        "guilds/{$guild_id}/members/{$discord_id}",
        [
            'body' => json_encode(['roles' => $discord_roles_to_add]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30 // Extended timeout
        ],
        'PATCH'
    );
    
    if (is_wp_error($result)) {
        // Check for 404 (user not in guild yet)
        $error_data = $result->get_error_data();
        if (isset($error_data['status']) && $error_data['status'] === 404) {
            gtaw_add_log('discord', 'Role Sync', "User {$user_id} not in Discord server yet. Roles will be assigned when they join.", 'warning');
            return false;
        }
        
        // Log other errors
        gtaw_add_log('discord', 'Error', "Failed to assign Discord roles: " . $result->get_error_message(), 'error');
        return $result;
    }
    
    // Log success
    gtaw_add_log('discord', 'Role Sync', "Successfully assigned Discord roles to user {$user_id} ({$discord_id}): " . implode(', ', $discord_roles_to_add), 'success');
    
    return true;
}

/**
 * Force sync roles when visiting the Discord settings page
 */
function gtaw_force_discord_role_sync_on_page_load() {
    // Only run on My Account Discord page
    if (!function_exists('is_account_page') || !is_account_page() || 
        !isset($_GET['discord']) || !is_user_logged_in()) {
        return;
    }
    
    // Get current user ID
    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    
    // Only proceed if Discord is linked
    if (empty($discord_id)) {
        return;
    }
    
    // Only run if role mapping and two-way sync are enabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1' || 
        get_option('gtaw_discord_rolemapping_two_way_sync', '0') !== '1') {
        return;
    }
    
    // Get user's WordPress role
    $user = get_userdata($user_id);
    if (!$user || empty($user->roles)) {
        return;
    }
    
    // Get primary role
    $primary_role = reset($user->roles);
    
    // Do a forced sync with empty old_roles
    gtaw_sync_discord_roles_from_wp($user_id, $primary_role, [], true);
}
add_action('template_redirect', 'gtaw_force_discord_role_sync_on_page_load', 20);

// Add this hook with higher priority (9) to run before the regular sync
add_action('gtaw_discord_account_linked', 'gtaw_sync_roles_on_discord_link_for_new_users', 9, 2);

/* ========= SCHEDULING & HOOKS ========= */

/**
 * Register background syncing schedule
 */
function gtaw_schedule_discord_role_sync() {
    $frequency = get_option('gtaw_discord_role_sync_frequency', 'daily');
    
    // Clear existing schedule first
    wp_clear_scheduled_hook('gtaw_discord_role_sync_event');
    
    // Schedule based on user preference
    if ($frequency !== 'disabled') {
        if (!wp_next_scheduled('gtaw_discord_role_sync_event')) {
            wp_schedule_event(time(), $frequency, 'gtaw_discord_role_sync_event');
        }
    }
}
add_action('admin_init', 'gtaw_schedule_discord_role_sync');
add_action('update_option_gtaw_discord_role_sync_frequency', 'gtaw_schedule_discord_role_sync');

/**
 * Add custom cron schedules
 *
 * @param array $schedules Current cron schedules
 * @return array Modified schedules
 */
function gtaw_add_custom_cron_schedules($schedules) {
    $schedules['weekly'] = [
        'interval' => 7 * DAY_IN_SECONDS,
        'display' => __('Once Weekly')
    ];
    
    $schedules['monthly'] = [
        'interval' => 30 * DAY_IN_SECONDS,
        'display' => __('Once Monthly')
    ];
    
    return $schedules;
}
add_filter('cron_schedules', 'gtaw_add_custom_cron_schedules');

/**
 * Clear scheduled events on deactivation
 */
function gtaw_clear_discord_role_sync_schedule() {
    wp_clear_scheduled_hook('gtaw_discord_role_sync_event');
}
register_deactivation_hook(plugin_basename(GTAW_DISCORD_PATH . '../gtaw-discord.php'), 'gtaw_clear_discord_role_sync_schedule');

/**
 * Hook for when a user connects their Discord account
 *
 * @param int $user_id WordPress user ID
 * @param string $discord_id Discord user ID
 */
function gtaw_sync_roles_on_discord_link($user_id, $discord_id) {
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1') {
        return;
    }
    
    // Give time for the Discord API to recognize the new member
    // This helps avoid errors when trying to sync roles immediately
    sleep(1);
    
    gtaw_sync_user_discord_roles($user_id);
}
add_action('gtaw_discord_account_linked', 'gtaw_sync_roles_on_discord_link', 10, 2);

/**
 * Hook for user login to sync roles conditionally
 *
 * @param string $user_login Username
 * @param WP_User $user User object
 */
function gtaw_sync_roles_on_login($user_login, $user) {
    // Only sync if both role mapping and login sync are enabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1' || 
        get_option('gtaw_discord_rolemapping_sync_on_login', '0') !== '1') {
        return;
    }
    
    // Don't slow down the login process - schedule the sync to happen shortly after
    wp_schedule_single_event(time() + 10, 'gtaw_delayed_role_sync', [$user->ID]);
}
add_action('wp_login', 'gtaw_sync_roles_on_login', 10, 2);

/**
 * Delayed role sync after login
 *
 * @param int $user_id User ID to sync
 */
function gtaw_delayed_role_sync_handler($user_id) {
    gtaw_sync_user_discord_roles($user_id);
}
add_action('gtaw_delayed_role_sync', 'gtaw_delayed_role_sync_handler');

/**
 * Hook for WordPress role changes (WordPress → Discord)
 *
 * @param int $user_id User ID
 * @param string $new_role New role
 * @param array $old_roles Old roles
 */
function gtaw_detect_role_changes($user_id, $new_role, $old_roles) {
    // Only sync if both role mapping and two-way sync are enabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1' || 
        get_option('gtaw_discord_rolemapping_two_way_sync', '0') !== '1') {
        return;
    }
    
    gtaw_sync_discord_roles_from_wp($user_id, $new_role, $old_roles);
}
add_action('set_user_role', 'gtaw_detect_role_changes', 10, 3);

/**
 * Scheduled sync for all users with performance optimizations
 */
function gtaw_run_scheduled_role_sync() {
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1') {
        return;
    }
    
    // Add a random delay to avoid all sites hitting Discord API at the same time
    $rand_delay = rand(0, 60);
    sleep($rand_delay);
    
    // Get all users with Discord IDs
    $users = get_users([
        'meta_key' => 'discord_ID',
        'fields' => ['ID'],
        'number' => -1 // Get all users
    ]);
    
    if (empty($users)) {
        gtaw_add_log('discord', 'Scheduled Sync', "No users with linked Discord accounts found", 'info');
        return;
    }
    
    // Process users in batches
    $total_users = count($users);
    $success_count = 0;
    $failed_count = 0;
    $skipped_count = 0;
    $batches = array_chunk($users, GTAW_DISCORD_ROLE_SYNC_BATCH_SIZE);
    
    gtaw_add_log('discord', 'Scheduled Sync', "Starting scheduled role sync for {$total_users} users", 'info');
    
    foreach ($batches as $batch_index => $batch) {
        foreach ($batch as $user) {
            $result = gtaw_sync_user_discord_roles($user->ID);
            
            if ($result === true) {
                $success_count++;
            } elseif ($result === false) {
                $skipped_count++;
            } else {
                $failed_count++;
            }
        }
        
        // Pause between batches to avoid rate limits
        if ($batch_index < count($batches) - 1) {
            sleep(2);
        }
    }
    
    gtaw_add_log(
        'discord',
        'Scheduled Sync',
        "Completed scheduled role sync: {$success_count} updated, {$failed_count} failed, {$skipped_count} skipped",
        'success'
    );
}
add_action('gtaw_discord_role_sync_event', 'gtaw_run_scheduled_role_sync');

/* ========= AJAX ENDPOINTS ========= */

/**
 * AJAX endpoint for manual role sync with progress tracking
 */
function gtaw_ajax_sync_discord_roles() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    // Verify nonce
    check_ajax_referer('gtaw_discord_roles_nonce', 'nonce');
    
    // Get sync options
    $sync_all = isset($_POST['sync_all']) && $_POST['sync_all'] === 'true';
    $direction = isset($_POST['direction']) ? sanitize_text_field($_POST['direction']) : 'both';
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : GTAW_DISCORD_ROLE_SYNC_BATCH_SIZE;
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    
    // Get total user count for progress calculation
    $total_count = get_option('gtaw_role_sync_total_count');
    
    // If we're starting a new sync, get the total count
    if ($offset === 0) {
        // Get users with Discord IDs
        $count_query = new WP_User_Query([
            'meta_key' => 'discord_ID',
            'count_total' => true,
            'fields' => 'ID'
        ]);
        
        $total_count = $count_query->get_total();
        update_option('gtaw_role_sync_total_count', $total_count);
        
        // If we have nothing to do, exit early
        if ($total_count === 0) {
            wp_send_json_error([
                'message' => 'No users with linked Discord accounts found.',
                'complete' => true
            ]);
        }
        
        gtaw_add_log('discord', 'Manual Sync', "Starting manual role sync for {$total_count} users", 'info');
    }
    
    // Get batch of users
    $users = get_users([
        'meta_key' => 'discord_ID',
        'fields' => ['ID'],
        'number' => $batch_size,
        'offset' => $offset
    ]);
    
    if (empty($users)) {
        // We've processed all users
        delete_option('gtaw_role_sync_total_count');
        
        wp_send_json_success([
            'message' => "Role sync completed for all users.",
            'complete' => true,
            'offset' => $offset,
            'progress' => 100
        ]);
    }
    
    $success_count = 0;
    $failed_count = 0;
    $skipped_count = 0;
    
    foreach ($users as $user) {
        // Perform the sync based on direction
        if ($direction === 'both' || $direction === 'discord_to_wp') {
            $result_to_wp = gtaw_sync_user_discord_roles($user->ID, true);
            
            if ($result_to_wp === true) {
                $success_count++;
            } elseif ($result_to_wp === false) {
                $skipped_count++;
            } else {
                $failed_count++;
            }
        }
        
        if ($direction === 'both' || $direction === 'wp_to_discord') {
            // Get user's current role
            $wp_user = get_userdata($user->ID);
            if ($wp_user && !empty($wp_user->roles)) {
                $primary_role = reset($wp_user->roles);
                $result_to_discord = gtaw_sync_discord_roles_from_wp($user->ID, $primary_role, []);
                
                // Only count these if we didn't already count a success from the first direction
                if ($direction !== 'both' || $result_to_wp !== true) {
                    if ($result_to_discord === true) {
                        $success_count++;
                    } elseif ($result_to_discord === false) {
                        $skipped_count++;
                    } else {
                        $failed_count++;
                    }
                }
            }
        }
    }
    
    // Calculate new offset and progress
    $new_offset = $offset + count($users);
    $progress = ($total_count > 0) ? round(($new_offset / $total_count) * 100) : 100;
    $progress = min(100, $progress); // Cap at 100%
    
    // Check if we're done
    $is_complete = $new_offset >= $total_count || empty($users);
    
    // Log progress
    if ($is_complete) {
        gtaw_add_log(
            'discord',
            'Manual Sync',
            "Completed manual role sync: {$success_count} updated, {$failed_count} failed, {$skipped_count} skipped",
            'success'
        );
        
        delete_option('gtaw_role_sync_total_count');
    }
    
    wp_send_json_success([
        'message' => "Processed batch: {$success_count} updated, {$failed_count} failed, {$skipped_count} skipped.",
        'complete' => $is_complete,
        'offset' => $new_offset,
        'total' => $total_count,
        'progress' => $progress
    ]);
}
add_action('wp_ajax_gtaw_sync_discord_roles', 'gtaw_ajax_sync_discord_roles');

/**
 * AJAX handler to refresh Discord roles list with progress indicator
 */
function gtaw_ajax_refresh_discord_roles() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    check_ajax_referer('gtaw_discord_roles_nonce', 'nonce');
    
    // Clear roles cache and fetch fresh data
    delete_transient('gtaw_discord_roles');
    $roles = gtaw_fetch_discord_roles();
    
    if (is_wp_error($roles)) {
        wp_send_json_error($roles->get_error_message());
    }
    
    wp_send_json_success([
        'roles' => $roles,
        'message' => 'Discord roles refreshed successfully.',
        'count' => count($roles)
    ]);
}
add_action('wp_ajax_gtaw_refresh_discord_roles', 'gtaw_ajax_refresh_discord_roles');

/**
 * AJAX endpoint to sync a single user's Discord roles
 */
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
    
    // Get direction
    $direction = isset($_POST['direction']) ? sanitize_text_field($_POST['direction']) : 'both';
    
    // Track results
    $results = [];
    
    // Discord → WordPress sync
    if ($direction === 'both' || $direction === 'discord_to_wp') {
        // Force sync even if lock exists
        $result_to_wp = gtaw_sync_user_discord_roles($user_id, true);
        
        if ($result_to_wp === true) {
            $results['wp'] = 'success';
        } elseif (is_wp_error($result_to_wp)) {
            $results['wp'] = 'error: ' . $result_to_wp->get_error_message();
        } else {
            $results['wp'] = 'skipped';
        }
    }
    
    // WordPress → Discord sync
    if ($direction === 'both' || $direction === 'wp_to_discord') {
        // Get user's current role
        $user = get_userdata($user_id);
        if ($user && !empty($user->roles)) {
            $primary_role = reset($user->roles);
            
            // Force sync even if lock exists
            $result_to_discord = gtaw_sync_discord_roles_from_wp($user_id, $primary_role, []);
            
            if ($result_to_discord === true) {
                $results['discord'] = 'success';
            } elseif (is_wp_error($result_to_discord)) {
                $results['discord'] = 'error: ' . $result_to_discord->get_error_message();
            } else {
                $results['discord'] = 'skipped';
            }
        } else {
            $results['discord'] = 'error: User has no WordPress roles';
        }
    }
    
    // Determine overall result
    $success = false;
    $message = '';
    
    if (isset($results['wp']) && $results['wp'] === 'success') {
        $success = true;
        $message = 'Discord roles synced to WordPress successfully!';
    }
    
    if (isset($results['discord']) && $results['discord'] === 'success') {
        $success = true;
        $message = isset($results['wp']) && $results['wp'] === 'success' 
            ? 'Roles synced successfully in both directions!' 
            : 'WordPress roles synced to Discord successfully!';
    }
    
    if (!$success) {
        $errors = [];
        if (isset($results['wp']) && $results['wp'] !== 'success' && $results['wp'] !== 'skipped') {
            $errors[] = 'Discord → WordPress: ' . $results['wp'];
        }
        
        if (isset($results['discord']) && $results['discord'] !== 'success' && $results['discord'] !== 'skipped') {
            $errors[] = 'WordPress → Discord: ' . $results['discord'];
        }
        
        if (empty($errors)) {
            $message = 'No role changes were needed.';
            $success = true;
        } else {
            $message = 'Failed to sync roles: ' . implode(', ', $errors);
        }
    }
    
    // Send response
    if ($success) {
        wp_send_json_success([
            'message' => $message,
            'refresh' => true,
            'results' => $results
        ]);
    } else {
        wp_send_json_error($message);
    }
}
add_action('wp_ajax_gtaw_sync_user_discord_roles', 'gtaw_ajax_sync_user_discord_roles');

/* ========= ADMIN UI ========= */

/**
 * Register tab in the Discord settings
 * This renders the role mapping settings page
 */
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
    $protect_admins = get_option('gtaw_discord_rolemapping_protect_admins', true);
    $sync_frequency = get_option('gtaw_discord_role_sync_frequency', 'daily');
    
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
                <tr valign="top">
                    <th scope="row">Protect Administrators</th>
                    <td>
                        <input type="checkbox" name="gtaw_discord_rolemapping_protect_admins" value="1" <?php checked($protect_admins, '1'); ?> />
                        <p class="description">Prevent administrator accounts from being downgraded via Discord role sync</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Background Sync Frequency</th>
                    <td>
                        <select name="gtaw_discord_role_sync_frequency">
                            <option value="disabled" <?php selected($sync_frequency, 'disabled'); ?>>Disabled</option>
                            <option value="hourly" <?php selected($sync_frequency, 'hourly'); ?>>Hourly</option>
                            <option value="twicedaily" <?php selected($sync_frequency, 'twicedaily'); ?>>Twice Daily</option>
                            <option value="daily" <?php selected($sync_frequency, 'daily'); ?>>Daily</option>
                            <option value="weekly" <?php selected($sync_frequency, 'weekly'); ?>>Weekly</option>
                            <option value="monthly" <?php selected($sync_frequency, 'monthly'); ?>>Monthly</option>
                        </select>
                        <p class="description">How often to automatically sync roles in the background</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="role-mapping-controls" style="margin-bottom: 20px;">
            <button type="button" id="refresh-discord-roles" class="button">Refresh Discord Roles</button>
            <button type="button" id="sync-all-users" class="button">Sync All User Roles</button>
            <span id="role-mapping-status" style="margin-left: 10px; display: inline-block;"></span>
            
            <div id="sync-progress" style="margin-top: 10px; display: none;">
                <div class="progress-bar" style="height: 20px; background-color: #e5e5e5; border-radius: 3px; margin-bottom: 5px;">
                    <div class="progress-bar-fill" style="height: 100%; width: 0%; background-color: #2271b1; border-radius: 3px;"></div>
                </div>
                <div class="progress-text">0%</div>
            </div>
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
        
        // Sync all users with batching and progress
        $('#sync-all-users').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('This will update roles for all users with linked Discord accounts. Continue?')) {
                return;
            }
            
            const $button = $(this);
            const $status = $('#role-mapping-status');
            const $progress = $('#sync-progress');
            const $progressBar = $progress.find('.progress-bar-fill');
            const $progressText = $progress.find('.progress-text');
            
            // Configure sync
            const direction = 'both'; // both, discord_to_wp, wp_to_discord
            const batchSize = 25;
            let offset = 0;
            
            // Start the sync
            $button.prop('disabled', true);
            $('#refresh-discord-roles').prop('disabled', true);
            $status.html('<span style="color: blue;">Syncing user roles. This may take a while...</span>');
            $progress.show();
            
            // Function to process a batch
            function processBatch() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gtaw_sync_discord_roles',
                        nonce: '<?php echo $nonce; ?>',
                        direction: direction,
                        batch_size: batchSize,
                        offset: offset,
                        sync_all: true
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update progress
                            const progress = response.data.progress;
                            $progressBar.css('width', progress + '%');
                            $progressText.text(progress + '%');
                            
                            // Update status with batch results
                            $status.html('<span style="color: blue;">' + response.data.message + '</span>');
                            
                            if (response.data.complete) {
                                // We're done
                                $status.html('<span style="color: green;">Role sync completed successfully!</span>');
                                $button.prop('disabled', false);
                                $('#refresh-discord-roles').prop('disabled', false);
                                
                                // Hide progress after a delay
                                setTimeout(function() {
                                    $progress.hide();
                                }, 3000);
                            } else {
                                // Process next batch
                                offset = response.data.offset;
                                setTimeout(processBatch, 500); // Small delay between batches
                            }
                        } else {
                            $status.html('<span style="color: red;">Error: ' + response.data.message + '</span>');
                            $button.prop('disabled', false);
                            $('#refresh-discord-roles').prop('disabled', false);
                            $progress.hide();
                        }
                    },
                    error: function() {
                        $status.html('<span style="color: red;">Connection error. Please try again.</span>');
                        $button.prop('disabled', false);
                        $('#refresh-discord-roles').prop('disabled', false);
                        $progress.hide();
                    }
                });
            }
            
            // Start the batch processing
            processBatch();
        });
    });
    </script>
    <?php
}

/**
 * Sync WordPress roles to Discord when users are created and roles assigned
 * 
 * @param int $user_id The new user ID
 */
function gtaw_sync_new_user_roles_to_discord($user_id) {
    // Only run if role mapping and two-way sync are enabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1' || 
        get_option('gtaw_discord_rolemapping_two_way_sync', '0') !== '1') {
        return;
    }
    
    // Check if user has a linked Discord account
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    if (empty($discord_id)) {
        return; // No Discord account linked yet
    }
    
    // Get the user's WordPress role
    $user = get_userdata($user_id);
    if (!$user || empty($user->roles)) {
        return;
    }
    
    // Get the primary role (first one in the array)
    $primary_role = reset($user->roles);
    
    // Sync the WordPress role to Discord
    gtaw_add_log('discord', 'Role Sync', "Triggering initial role sync for new user {$user_id}", 'info');
    gtaw_sync_discord_roles_from_wp($user_id, $primary_role, []);
}
add_action('user_register', 'gtaw_sync_new_user_roles_to_discord', 20); // After roles are set