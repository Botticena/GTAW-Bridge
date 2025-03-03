<?php
defined('ABSPATH') or exit;

/* ========= DISCORD MEMBER CARD MODULE ========= */
/*
 * This module adds Discord role display to user profiles:
 * - Shows Discord roles on My Account page
 * - Adds Discord information to admin user profiles
 * - Provides role sync controls for admins
 */

/**
 * Add Discord roles to the My Account page
 */
function gtaw_add_discord_roles_to_account() {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    
    if (empty($discord_id)) {
        return; // No Discord account linked
    }
    
    // Get user's Discord roles
    $discord_roles = gtaw_get_user_discord_roles($discord_id);
    if (is_wp_error($discord_roles) || empty($discord_roles)) {
        return;
    }
    
    // Get all Discord roles to show names/colors
    $all_discord_roles = gtaw_get_discord_roles();
    if (is_wp_error($all_discord_roles)) {
        return;
    }
    
    // Create a lookup by ID for faster access
    $roles_by_id = [];
    foreach ($all_discord_roles as $role) {
        $roles_by_id[$role['id']] = $role;
    }
    
    // Filter out roles that don't exist in our lookup
    $user_roles = [];
    foreach ($discord_roles as $role_id) {
        if (isset($roles_by_id[$role_id])) {
            $user_roles[] = $roles_by_id[$role_id];
        }
    }
    
    // Skip if no roles found
    if (empty($user_roles)) {
        return;
    }
    
    // Sort roles by position (highest first)
    usort($user_roles, function($a, $b) {
        return $b['position'] <=> $a['position'];
    });
    
    ?>
    <div class="discord-member-card" style="margin-top: 30px; border: 1px solid #ddd; border-radius: 5px; padding: 15px;">
        <h3>Discord Roles</h3>
        <div class="discord-roles-list">
            <?php foreach ($user_roles as $role): ?>
                <?php 
                // Skip @everyone role
                if ($role['name'] === '@everyone') continue;
                
                // Convert decimal color to hex
                $color = !empty($role['color']) ? '#' . dechex($role['color']) : '#99AAB5';
                ?>
                <span class="discord-role" style="display: inline-block; margin: 3px; padding: 5px 8px; border-radius: 3px; background-color: <?php echo esc_attr($color); ?>; color: #fff; font-size: 12px; font-weight: bold;">
                    <?php echo esc_html($role['name']); ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Hook the function to display after the Discord account section
 */
function gtaw_hook_discord_roles_display() {
    // Only run if role mapping is enabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1') {
        return;
    }
    
    add_action('woocommerce_account_discord_endpoint', 'gtaw_add_discord_roles_to_account', 20);
}
add_action('init', 'gtaw_hook_discord_roles_display');

/**
 * Add user Discord roles to admin user profile
 *
 * @param WP_User $user User object
 */
function gtaw_add_discord_roles_to_admin_profile($user) {
    // Only run if role mapping is enabled
    if (get_option('gtaw_discord_rolemapping_enabled', '0') !== '1') {
        return;
    }
    
    // Check if user has Discord ID
    $discord_id = get_user_meta($user->ID, 'discord_ID', true);
    if (empty($discord_id)) {
        return;
    }
    
    // Get user's Discord roles
    $discord_roles = gtaw_get_user_discord_roles($discord_id);
    if (is_wp_error($discord_roles) || empty($discord_roles)) {
        echo '<h3>Discord Information</h3>';
        echo '<p>Discord ID: ' . esc_html($discord_id) . '</p>';
        echo '<p><em>No Discord roles found or unable to fetch roles.</em></p>';
        echo '<button type="button" class="button" id="sync-user-discord-roles" data-user-id="' . esc_attr($user->ID) . '">Sync Discord Roles</button>';
        return;
    }
    
    // Get all Discord roles to show names/colors
    $all_discord_roles = gtaw_get_discord_roles();
    if (is_wp_error($all_discord_roles)) {
        echo '<h3>Discord Information</h3>';
        echo '<p>Discord ID: ' . esc_html($discord_id) . '</p>';
        echo '<p><em>Error fetching Discord roles: ' . esc_html($all_discord_roles->get_error_message()) . '</em></p>';
        return;
    }
    
    // Create a lookup by ID for faster access
    $roles_by_id = [];
    foreach ($all_discord_roles as $role) {
        $roles_by_id[$role['id']] = $role;
    }
    
    // Filter out roles that don't exist in our lookup
    $user_roles = [];
    foreach ($discord_roles as $role_id) {
        if (isset($roles_by_id[$role_id])) {
            $user_roles[] = $roles_by_id[$role_id];
        }
    }
    
    // Sort roles by position (highest first)
    usort($user_roles, function($a, $b) {
        return $b['position'] <=> $a['position'];
    });
    
    // Get role mappings to show which WP role each Discord role maps to
    $role_mappings = get_option('gtaw_discord_role_mappings', []);
    
    ?>
    <h3>Discord Information</h3>
    <table class="form-table">
        <tr>
            <th>Discord ID</th>
            <td><?php echo esc_html($discord_id); ?></td>
        </tr>
        <tr>
            <th>Discord Roles</th>
            <td>
                <?php if (empty($user_roles)): ?>
                    <em>No roles found</em>
                <?php else: ?>
                    <div class="discord-roles-list">
                        <?php foreach ($user_roles as $role): ?>
                            <?php 
                            // Skip @everyone role
                            if ($role['name'] === '@everyone') continue;
                            
                            // Convert decimal color to hex
                            $color = !empty($role['color']) ? '#' . dechex($role['color']) : '#99AAB5';
                            ?>
                            <div style="margin-bottom: 5px;">
                                <span style="display: inline-block; padding: 3px 8px; border-radius: 3px; background-color: <?php echo esc_attr($color); ?>; color: #fff; font-size: 12px; font-weight: bold;">
                                    <?php echo esc_html($role['name']); ?>
                                </span>
                                
                                <?php if (isset($role_mappings[$role['id']])): ?>
                                    <span style="font-size: 12px; color: #666; margin-left: 5px;">
                                        → Maps to: <strong><?php echo esc_html(wp_roles()->role_names[$role_mappings[$role['id']]] ?? $role_mappings[$role['id']]); ?></strong>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <br>
                <button type="button" class="button" id="sync-user-discord-roles" data-user-id="<?php echo esc_attr($user->ID); ?>">Sync Discord Roles</button>
                <span id="discord-sync-status" style="margin-left: 10px;"></span>
            </td>
        </tr>
    </table>
    
    <script>
    jQuery(document).ready(function($) {
        $('#sync-user-discord-roles').on('click', function() {
            const $button = $(this);
            const $status = $('#discord-sync-status');
            const userId = $button.data('user-id');
            
            $button.prop('disabled', true);
            $status.html('<span style="color: blue;">Syncing roles...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gtaw_sync_user_discord_roles',
                    user_id: userId,
                    nonce: '<?php echo wp_create_nonce('gtaw_sync_user_roles_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">' + response.data.message + '</span>');
                        if (response.data.refresh) {
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        $status.html('<span style="color: red;">Error: ' + response.data + '</span>');
                    }
                    $button.prop('disabled', false);
                },
                error: function() {
                    $status.html('<span style="color: red;">Connection error</span>');
                    $button.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}
add_action('show_user_profile', 'gtaw_add_discord_roles_to_admin_profile');
add_action('edit_user_profile', 'gtaw_add_discord_roles_to_admin_profile');