<?php
defined('ABSPATH') or exit;

/* ========= OAUTH MODULE MAIN FILE ========= */
/*
 * This file serves as the entry point for the OAuth module.
 * It handles:
 * - Core settings registration
 * - Admin menu setup
 * - Tab navigation
 * - Loading all OAuth submodules
 */

// Define the OAUTH module constants
define('GTAW_OAUTH_VERSION', '2.0');
define('GTAW_OAUTH_PATH', plugin_dir_path(__FILE__) . 'oauth/');

/* ========= CONSOLIDATED SETTINGS ========= */

/**
 * Register OAuth consolidated settings
 * 
 * @since 2.0 Consolidated settings approach
 */
function gtaw_oauth_register_settings() {
    // Register the settings group for the consolidated option
    register_setting('gtaw_oauth_settings_group', 'gtaw_oauth_settings', [
        'sanitize_callback' => 'gtaw_oauth_sanitize_settings',
        'default' => gtaw_oauth_default_settings()
    ]);
    
    // Register backward compatibility settings 
    // @deprecated 2.0 - Use gtaw_oauth_settings consolidated option instead
    // These individual options will be synchronized with the consolidated option
    register_setting('gtaw_oauth_settings_group', 'gtaw_oauth_enabled');
    register_setting('gtaw_oauth_settings_group', 'gtaw_client_id');
    register_setting('gtaw_oauth_settings_group', 'gtaw_client_secret');
    register_setting('gtaw_oauth_settings_group', 'gtaw_callback_url');
}
add_action('admin_init', 'gtaw_oauth_register_settings');

/**
 * Sanitize the consolidated OAuth settings
 *
 * @param array $input The settings input to sanitize
 * @return array Sanitized settings
 */
function gtaw_oauth_sanitize_settings($input) {
    // If input is not an array, use defaults
    if (!is_array($input)) {
        return gtaw_oauth_default_settings();
    }
    
    $defaults = gtaw_oauth_default_settings();
    $sanitized = [];
    
    // Module status
    $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : $defaults['enabled'];
    
    // API credentials
    $sanitized['client_id'] = isset($input['client_id']) ? sanitize_text_field($input['client_id']) : $defaults['client_id'];
    $sanitized['client_secret'] = isset($input['client_secret']) ? sanitize_text_field($input['client_secret']) : $defaults['client_secret'];
    
    // Callback URL (use default if empty)
    $sanitized['callback_url'] = !empty($input['callback_url']) ? esc_url_raw($input['callback_url']) : $defaults['callback_url'];
    
    // Character switching settings - fixed boolean handling
    $sanitized['allow_character_switch'] = isset($input['allow_character_switch']) && $input['allow_character_switch'] ? true : false;
    
    // Synchronize with legacy individual options
    // @deprecated 2.0 - This is for backward compatibility
    update_option('gtaw_oauth_enabled', $sanitized['enabled'] ? 1 : 0);
    update_option('gtaw_client_id', $sanitized['client_id']);
    update_option('gtaw_client_secret', $sanitized['client_secret']);
    update_option('gtaw_callback_url', $sanitized['callback_url']);
    
    return $sanitized;
}

/**
 * Get default OAuth settings
 *
 * @return array Default settings
 */
function gtaw_oauth_default_settings() {
    return [
        'enabled' => true, // Enabled by default
        'client_id' => '',
        'client_secret' => '',
        'callback_url' => site_url('?gta_oauth=callback'),
        'allow_character_switch' => true
    ];
}

/**
 * Get an OAuth module setting
 *
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value
 */
function gtaw_oauth_get_setting($key, $default = false) {
    // Get consolidated settings
    $settings = get_option('gtaw_oauth_settings', gtaw_oauth_default_settings());
    
    // Return the setting if it exists
    if (isset($settings[$key])) {
        return $settings[$key];
    }
    
    // Fallback to individual options for backward compatibility
    // @deprecated 2.0 - This is for backward compatibility
    switch ($key) {
        case 'enabled':
            return get_option('gtaw_oauth_enabled', 1) == 1;
        case 'client_id':
            return get_option('gtaw_client_id', '');
        case 'client_secret':
            return get_option('gtaw_client_secret', '');
        case 'callback_url':
            return get_option('gtaw_callback_url', site_url('?gta_oauth=callback'));
        case 'allow_character_switch':
            return true; // Default to true for backward compatibility
    }
    
    return $default;
}

/**
 * Migrate old individual settings to the consolidated format
 * This runs once after updating to v2.0
 */
function gtaw_oauth_migrate_settings() {
    // Check if migration is needed
    if (get_option('gtaw_oauth_settings_migrated', false)) {
        return;
    }
    
    // Start performance tracking
    gtaw_perf_start('oauth_settings_migration');
    
    // Get existing individual settings
    $settings = [
        'enabled' => get_option('gtaw_oauth_enabled', 1) == 1,
        'client_id' => get_option('gtaw_client_id', ''),
        'client_secret' => get_option('gtaw_client_secret', ''),
        'callback_url' => get_option('gtaw_callback_url', site_url('?gta_oauth=callback')),
        'allow_character_switch' => true // Default setting
    ];
    
    // Save consolidated settings
    update_option('gtaw_oauth_settings', $settings);
    
    // Mark as migrated
    update_option('gtaw_oauth_settings_migrated', true);
    
    // End performance tracking
    gtaw_perf_end('oauth_settings_migration', true);
    
    // Log the migration
    gtaw_add_log('oauth', 'Migration', 'Migrated individual settings to consolidated format', 'success');
}

/* ========= ADMIN MENU SETUP ========= */

/**
 * Add OAuth Settings submenu under the main GTA:W Bridge menu
 */
function gtaw_add_oauth_settings_submenu() {
    add_submenu_page(
        'gtaw-bridge',           // Parent slug
        'OAuth Module',          // Page title
        'OAuth Module',          // Menu title
        'manage_options',        // Capability
        'gtaw-oauth',            // Menu slug
        'gtaw_oauth_settings_page_callback' // Callback function
    );
}
add_action('admin_menu', 'gtaw_add_oauth_settings_submenu');

/**
 * Callback for the OAuth Settings page
 */
function gtaw_oauth_settings_page_callback() {
    // Determine active tab
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
    
    // Define available tabs
    $tabs = [
        'settings' => [
            'title' => 'Settings',
            'callback' => 'gtaw_oauth_settings_tab'
        ],
        'logs' => [
            'title' => 'Logs',
            'callback' => 'gtaw_oauth_logs_tab'
        ],
        'guide' => [
            'title' => 'Guide',
            'callback' => 'gtaw_oauth_guide_tab'
        ]
    ];
    
    // Allow other modules to add tabs
    $tabs = apply_filters('gtaw_oauth_settings_tabs', $tabs);
    ?>
    <div class="wrap">
        <h1>OAuth Module</h1>
        
        <?php echo gtaw_generate_tabs_navigation('gtaw-oauth', $tabs, $active_tab); ?>
        
        <div class="tab-content">
            <?php 
            // Display the active tab content
            if (isset($tabs[$active_tab]) && is_callable($tabs[$active_tab]['callback'])) {
                call_user_func($tabs[$active_tab]['callback']);
            } else {
                // Fallback to the settings tab
                gtaw_oauth_settings_tab();
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Main settings tab content using the enhanced utilities
 */
function gtaw_oauth_settings_tab() {
    // Get consolidated settings
    $settings = get_option('gtaw_oauth_settings', gtaw_oauth_default_settings());
    
    // Generate default callback URL if needed
    $default_oauth_callback = site_url('?gta_oauth=callback');
    $oauth_callback_url = !empty($settings['callback_url']) ? $settings['callback_url'] : $default_oauth_callback;
    
    // Generate the login link
    $login_link = add_query_arg([
        'client_id'     => $settings['client_id'],
        'redirect_uri'  => urlencode($oauth_callback_url),
        'response_type' => 'code',
        'scope'         => ''
    ], 'https://ucp.gta.world/oauth/authorize');
    
    // Settings form fields
    $fields = [
        [
            'type' => 'text',
            'name' => 'gtaw_oauth_settings[client_id]',
            'label' => 'OAuth Client ID',
            'default' => $settings['client_id'],
            'size' => 50,
            'description' => 'Enter your OAuth Client ID provided in the <a href="https://ucp.gta.world/developers/oauth" target="_blank">GTA:W UCP Developers section</a>.'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_oauth_settings[client_secret]',
            'label' => 'OAuth Client Secret',
            'default' => $settings['client_secret'],
            'size' => 50,
            'description' => 'Enter your OAuth Client Secret.'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_oauth_settings[callback_url]',
            'label' => 'OAuth Callback/Redirect URL',
            'default' => $oauth_callback_url,
            'size' => 50,
            'readonly' => true,
            'description' => 'This URL is auto-generated. Ensure it matches the one in your GTA:W UCP Developers settings.'
        ],
        [
            'type' => 'checkbox',
            'name' => 'gtaw_oauth_settings[allow_character_switch]',
            'label' => 'Allow Character Switching',
            'default' => $settings['allow_character_switch'],
            'description' => 'Allow users to switch between their GTA:W characters without logging out'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_login_link',
            'label' => 'GTA:W Login Link',
            'default' => $login_link,
            'size' => 50,
            'readonly' => true,
            'description' => 'Use this link directly or embed it with the shortcode <code>[gtaw_login]</code>.'
        ]
    ];
    
    // Generate the settings form
    echo gtaw_generate_settings_form('gtaw_oauth_settings_group', $fields, 'Save OAuth Settings');
}

/**
 * Logs tab using the enhanced utility function
 */
function gtaw_oauth_logs_tab() {
    // Get current page from URL
    $page = isset($_GET['logs_page']) ? max(1, intval($_GET['logs_page'])) : 1;
    
    // Get logs per page from URL or use the saved setting
    $logs_per_page = isset($_GET['logs_per_page']) ? absint($_GET['logs_per_page']) : gtaw_get_logs_per_page();
    
    // Display the logs
    echo gtaw_display_module_logs('oauth', $logs_per_page, $page);
}

/* ========= SUBMODULE LOADER ========= */

/**
 * Load OAuth submodules efficiently with lazy loading option
 *
 * @param bool $lazy_load Whether to use lazy loading (default: false)
 */
function gtaw_load_oauth_submodules($lazy_load = false) {
    // Don't load oAuth module on login page for better performance
    global $pagenow;
    if (in_array($pagenow, ['wp-login.php'])) {
        return;
    }
    
    // Use lazy loading if enabled
    if ($lazy_load) {
        // Define submodules that should be loaded immediately
        $core_submodules = ['core.php'];
        
        // Load core submodules immediately
        foreach ($core_submodules as $submodule) {
            if (file_exists(GTAW_OAUTH_PATH . $submodule)) {
                include_once GTAW_OAUTH_PATH . $submodule;
            }
        }
        
        // Register lazy loading hooks for other submodules
        add_action('init', function() {
            if (file_exists(GTAW_OAUTH_PATH . 'authentication.php')) {
                include_once GTAW_OAUTH_PATH . 'authentication.php';
            }
        }, 20);
        
        add_action('wp', function() {
            if (file_exists(GTAW_OAUTH_PATH . 'account-management.php')) {
                include_once GTAW_OAUTH_PATH . 'account-management.php';
            }
            if (file_exists(GTAW_OAUTH_PATH . 'character-switching.php')) {
                include_once GTAW_OAUTH_PATH . 'character-switching.php';
            }
        }, 5);
        
        // Only load shortcodes when needed
        add_action('init', function() {
            if (file_exists(GTAW_OAUTH_PATH . 'shortcodes.php')) {
                include_once GTAW_OAUTH_PATH . 'shortcodes.php';
            }
        }, 10);
        
        // Only load guide on admin page
        add_action('admin_init', function() {
            if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'gtaw-oauth') {
                if (file_exists(GTAW_OAUTH_PATH . 'guide.php')) {
                    include_once GTAW_OAUTH_PATH . 'guide.php';
                }
            }
        });
        
        // Load character switch fix
        add_action('init', function() {
            if (file_exists(GTAW_OAUTH_PATH . 'character-switch-fix.php')) {
                include_once GTAW_OAUTH_PATH . 'character-switch-fix.php';
            }
        }, 15);
    } else {
        // Traditional loading method (all at once)
        // This is the backward-compatible approach
        $submodules = [
            'core.php',
            'authentication.php',
            'account-management.php',
            'shortcodes.php',
            'guide.php',
            'character-switching.php'
        ];
        
        foreach ($submodules as $submodule) {
            if (file_exists(GTAW_OAUTH_PATH . $submodule)) {
                include_once GTAW_OAUTH_PATH . $submodule;
            }
        }
    }
}

/**
 * Initialize the OAuth module
 */
function gtaw_init_oauth_module() {
    // Run settings migration
    gtaw_oauth_migrate_settings();
    
    // Check if the OAuth module is enabled
    if (gtaw_oauth_get_setting('enabled', true)) {
        // Start performance tracking
        gtaw_perf_start('oauth_module_load');
        
        // Load submodules - use traditional loading for backward compatibility
        // @todo In future versions, enable lazy loading by passing true to this function
        gtaw_load_oauth_submodules(false);
        
        // End performance tracking
        gtaw_perf_end('oauth_module_load', true);
    }
}
add_action('plugins_loaded', 'gtaw_init_oauth_module', 11); // Priority 11 to load after main plugin

/**
 * Conditionally load admin-specific scripts and styles
 */
function gtaw_oauth_admin_scripts($hook) {
    // Only load on our module page
    if (gtaw_is_plugin_page($hook, 'gtaw-oauth')) {
        // Enqueue scripts/styles specific to OAuth admin
        wp_enqueue_style('gtaw-oauth-admin-style', GTAW_BRIDGE_PLUGIN_URL . 'assets/css/gtaw-oauth-admin.css', [], GTAW_OAUTH_VERSION);
        wp_enqueue_script('gtaw-oauth-admin-script', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-oauth-admin.js', ['jquery'], GTAW_OAUTH_VERSION, true);
    }
}
add_action('admin_enqueue_scripts', 'gtaw_oauth_admin_scripts');