<?php
defined('ABSPATH') or exit;

/* ========= DISCORD MODULE MAIN FILE ========= */
/*
 * This file serves as the entry point for the Discord module.
 * It handles:
 * - Core settings registration
 * - Admin menu setup
 * - Tab navigation
 * - Loading all Discord submodules
 */

/* ========= ADMIN SETTINGS ========= */

// Register Discord core settings
function gtaw_discord_register_settings() {
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_enabled');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_client_id');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_client_secret');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_bot_token');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_guild_id');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_invite_link');
}
add_action('admin_init', 'gtaw_discord_register_settings');

// Add Discord Settings submenu under the main GTA:W Bridge menu
function gtaw_add_discord_settings_submenu() {
    add_submenu_page(
        'gtaw-bridge',                // Parent slug
        'Discord Module',             // Page title
        'Discord Module',             // Menu title
        'manage_options',             // Capability
        'gtaw-discord',               // Menu slug
        'gtaw_discord_settings_page_callback' // Callback function
    );
}
add_action('admin_menu', 'gtaw_add_discord_settings_submenu');

// Callback for the Discord Settings page
function gtaw_discord_settings_page_callback() {
    // Auto-generate the Discord OAuth redirect URI
    $redirect_uri = site_url('?discord_oauth=callback');
    
    // Get basic settings
    $enabled      = get_option('gtaw_discord_enabled', 0);
    $client_id    = get_option('gtaw_discord_client_id', '');
    $client_secret= get_option('gtaw_discord_client_secret', '');
    $bot_token    = get_option('gtaw_discord_bot_token', '');
    $logs = gtaw_get_logs('discord');
    
    // Determine active tab
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
    
    // Define available tabs
    $tabs = [
        'settings' => [
            'title' => 'Settings',
            'callback' => 'gtaw_discord_settings_tab'
        ],
        'notifications' => [
            'title' => 'Customer Notifications',
            'callback' => 'gtaw_discord_notifications_tab'
        ],
        'store-notifications' => [
            'title' => 'Store Notifications',
            'callback' => 'gtaw_discord_store_notifications_tab'
        ],
        'role-mapping' => [
            'title' => 'Role Mapping',
            'callback' => 'gtaw_discord_role_mapping_tab'
        ],
        'logs' => [
            'title' => 'Logs',
            'callback' => 'gtaw_discord_logs_tab'
        ],
        'guide' => [
            'title' => 'Guide',
            'callback' => 'gtaw_discord_guide_tab'
        ]
    ];
    
    // Allow other modules to add tabs
    $tabs = apply_filters('gtaw_discord_settings_tabs', $tabs);
    ?>
    <div class="wrap">
        <h1>Discord Module</h1>
        
        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_id => $tab): ?>
                <a href="?page=gtaw-discord&tab=<?php echo esc_attr($tab_id); ?>" 
                   class="nav-tab <?php echo $active_tab == $tab_id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab['title']); ?>
                </a>
            <?php endforeach; ?>
        </h2>
        
        <!-- Tab Content -->
        <div class="tab-content">
            <?php 
            // Display the active tab content
            if (isset($tabs[$active_tab]) && is_callable($tabs[$active_tab]['callback'])) {
                call_user_func($tabs[$active_tab]['callback']);
            } else {
                // Fallback to the settings tab
                gtaw_discord_settings_tab();
            }
            ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Clear logs functionality
        $(document).on('click', '#clear-logs', function() {
            if (confirm("Are you sure you want to clear all logs?")) {
                $.post(ajaxurl, {
                    action: "gtaw_clear_logs",
                    module: "discord"
                }, function(response) {
                    if (response.success) {
                        alert("Logs cleared successfully.");
                        location.reload();
                    } else {
                        alert("Error: " + response.data);
                    }
                }).fail(function() {
                    alert("Request failed. Please try again.");
                });
            }
        });
    });
    </script>
    <?php
}

// Main settings tab
function gtaw_discord_settings_tab() {
    $enabled      = get_option('gtaw_discord_enabled', 0);
    $client_id    = get_option('gtaw_discord_client_id', '');
    $client_secret= get_option('gtaw_discord_client_secret', '');
    $bot_token    = get_option('gtaw_discord_bot_token', '');
    $redirect_uri = site_url('?discord_oauth=callback');
    ?>
    <form method="post" action="options.php">
        <?php 
            settings_fields('gtaw_discord_settings_group');
            do_settings_sections('gtaw_discord_settings_group');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Activate Discord Module</th>
                <td>
                    <input type="checkbox" name="gtaw_discord_enabled" value="1" <?php checked($enabled, 1); ?> />
                    <p class="description">Check to activate Discord integration. Uncheck to disable all Discord functionality.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Discord Client ID</th>
                <td>
                    <input type="text" name="gtaw_discord_client_id" value="<?php echo esc_attr($client_id); ?>" size="50" />
                    <p class="description">Enter your Discord application's Client ID for OAuth.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Discord Client Secret</th>
                <td>
                    <input type="text" name="gtaw_discord_client_secret" value="<?php echo esc_attr($client_secret); ?>" size="50" />
                    <p class="description">Enter your Discord application's Client Secret for OAuth.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Discord Bot Token</th>
                <td>
                    <input type="text" name="gtaw_discord_bot_token" value="<?php echo esc_attr($bot_token); ?>" size="50" />
                    <p class="description">Enter your Discord Bot Token (for bot integrations).</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Discord OAuth Redirect URI</th>
                <td>
                    <input type="text" readonly value="<?php echo esc_url($redirect_uri); ?>" size="50" style="width:100%;" />
                    <p class="description">Set this URI in your Discord Developer Portal for your application.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Discord Guild ID</th>
                <td>
                    <input type="text" name="gtaw_discord_guild_id" value="<?php echo esc_attr(get_option('gtaw_discord_guild_id', '')); ?>" size="50" />
                    <p class="description">Enter your Discord Server ID where notifications will be sent.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Discord Invite Code</th>
                <td>
                    <input type="text" name="gtaw_discord_invite_link" value="<?php echo esc_attr(get_option('gtaw_discord_invite_link', '')); ?>" size="50" />
                    <p class="description">Enter your Discord server invite code (only the code part, not the full URL).</p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <?php
}

// Logs tab
function gtaw_discord_logs_tab() {
    $logs = gtaw_get_logs('discord');
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead><tr><th>Type</th><th>Message</th><th>Date</th></tr></thead>
        <tbody>
            <?php if (!empty($logs)) : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr style="color: <?php echo ($log['status'] === 'success') ? 'green' : 'red'; ?>;">
                        <td><?php echo esc_html($log['type']); ?></td>
                        <td><?php echo esc_html($log['message']); ?></td>
                        <td><?php echo esc_html($log['date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="3">No logs found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <button id="clear-logs" class="button button-danger">Clear Logs</button>
    <?php
}

/* ========= LOAD DISCORD SUBMODULES ========= */

// Define the submodules path 
define('GTAW_DISCORD_PATH', plugin_dir_path(__FILE__) . 'discord/');

// Load all Discord submodules
function gtaw_load_discord_submodules() {
    // Core functionality must be loaded first as other modules depend on it
    if (file_exists(GTAW_DISCORD_PATH . 'core.php')) {
        require_once GTAW_DISCORD_PATH . 'core.php';
    }
    
    // Load the rest of the submodules
    $submodules = [
        'oauth.php',
        'notifications.php',
        'store-notifications.php',
        'role-mapping.php',
        'member-card.php',
		'guide.php'
    ];
    
    foreach ($submodules as $submodule) {
        if (file_exists(GTAW_DISCORD_PATH . $submodule)) {
            require_once GTAW_DISCORD_PATH . $submodule;
        }
    }
}

// Only load Discord functionality if the module is enabled
function gtaw_init_discord_module() {
    // Check if the Discord module is enabled
    if (get_option('gtaw_discord_enabled', 0) == 1) {
        gtaw_load_discord_submodules();
    }
}
add_action('plugins_loaded', 'gtaw_init_discord_module', 11); // Priority 11 to load after main plugin