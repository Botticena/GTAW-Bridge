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
    // Use the new utility function to register settings
    gtaw_register_settings_group('gtaw_discord_settings_group', [
        'gtaw_discord_enabled',
        'gtaw_discord_client_id',
        'gtaw_discord_client_secret',
        'gtaw_discord_bot_token',
        'gtaw_discord_guild_id',
        'gtaw_discord_invite_link'
    ]);
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
        'post-notifications' => [
            'title' => 'Post Notifications',
            'callback' => 'gtaw_discord_post_notifications_tab'
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
        
        <!-- Tab Navigation - Using our new utility function -->
        <?php echo gtaw_generate_tabs_navigation('gtaw-discord', $tabs, $active_tab); ?>
        
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
    <?php
}

// Main settings tab
function gtaw_discord_settings_tab() {
    // Use our utility function to generate the settings form
    echo gtaw_generate_settings_form('gtaw_discord_settings_group', [
        [
            'type' => 'text',
            'name' => 'gtaw_discord_client_id',
            'label' => 'Discord Client ID',
            'size' => 50,
            'description' => 'Enter your Discord application\'s Client ID for OAuth.'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_discord_client_secret',
            'label' => 'Discord Client Secret',
            'size' => 50,
            'description' => 'Enter your Discord application\'s Client Secret for OAuth.'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_discord_bot_token',
            'label' => 'Discord Bot Token',
            'size' => 50,
            'description' => 'Enter your Discord Bot Token (for bot integrations).'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_discord_redirect_uri',
            'label' => 'Discord OAuth Redirect URI',
            'default' => site_url('?discord_oauth=callback'),
            'size' => 50,
            'readonly' => true,
            'description' => 'Set this URI in your Discord Developer Portal for your application.'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_discord_guild_id',
            'label' => 'Discord Guild ID',
            'size' => 50,
            'description' => 'Enter your Discord Server ID where notifications will be sent.'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_discord_invite_link',
            'label' => 'Discord Invite Code',
            'size' => 50,
            'description' => 'Enter your Discord server invite code (only the code part, not the full URL).'
        ]
    ], 'Save Discord Settings');
}

// Logs tab using our new utility function
function gtaw_discord_logs_tab() {
    // Get current page from URL
    $page = isset($_GET['logs_page']) ? max(1, intval($_GET['logs_page'])) : 1;
    
    // Get logs per page from URL or use the saved setting
    $logs_per_page = isset($_GET['logs_per_page']) ? absint($_GET['logs_per_page']) : gtaw_get_logs_per_page();
    
    // Display the logs
    echo gtaw_display_module_logs('discord', $logs_per_page, $page);
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
        'guide.php',
        'post-notifications.php'
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
        // Add styles to admin
        add_action('admin_enqueue_scripts', 'gtaw_enqueue_discord_admin_assets');
        
        // Load submodules
        gtaw_load_discord_submodules();
    }
}
add_action('plugins_loaded', 'gtaw_init_discord_module', 11); // Priority 11 to load after main plugin

function gtaw_enqueue_discord_admin_assets($hook) {
    // Check if we're on a Discord module page
    if (strpos($hook, 'gtaw-bridge_page_gtaw-discord') !== false) {
        wp_enqueue_style('gtaw-discord-styles', GTAW_BRIDGE_PLUGIN_URL . 'assets/css/gtaw-discord.css', array(), GTAW_BRIDGE_VERSION);
    }
}