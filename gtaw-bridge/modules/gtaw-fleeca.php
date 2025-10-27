<?php
defined('ABSPATH') or exit;

/* ========= FLEECA MODULE MAIN FILE ========= */
/*
 * This file serves as the entry point for the Fleeca module.
 * It handles:
 * - Core settings registration and management
 * - Admin menu setup
 * - Tab navigation
 * - Loading all Fleeca submodules
 * 
 * @version 2.0 - Enhanced with consolidated settings and performance optimizations
 */

// Define the FLEECA module constants
define('GTAW_FLEECA_VERSION', '2.0');
define('GTAW_FLEECA_PATH', plugin_dir_path(__FILE__) . 'fleeca/');

/* ========= CONSOLIDATED SETTINGS ========= */

/**
 * Register Fleeca consolidated settings
 * 
 * @since 2.0 Consolidated settings approach
 */
function gtaw_fleeca_register_settings() {
    // Register the settings group for the consolidated option
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_settings', [
        'sanitize_callback' => 'gtaw_fleeca_sanitize_settings',
        'default' => gtaw_fleeca_default_settings()
    ]);
    
    // Register backward compatibility settings
    // @deprecated 2.0 - Use gtaw_fleeca_settings consolidated option instead
    // These individual options will be synchronized with the consolidated option
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_enabled');
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_api_key');
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_callback_url');
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_gateway_name');
}
add_action('admin_init', 'gtaw_fleeca_register_settings');

/**
 * Sanitize the consolidated Fleeca settings
 *
 * @param array $input The settings input to sanitize
 * @return array Sanitized settings
 */
function gtaw_fleeca_sanitize_settings($input) {
    // If input is not an array, use defaults
    if (!is_array($input)) {
        return gtaw_fleeca_default_settings();
    }
    
    $defaults = gtaw_fleeca_default_settings();
    $sanitized = [];
    
    // Module status
    $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : $defaults['enabled'];
    
    // API credentials and settings
    $sanitized['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : $defaults['api_key'];
    $sanitized['gateway_name'] = isset($input['gateway_name']) ? sanitize_text_field($input['gateway_name']) : $defaults['gateway_name'];
    
    // Callback URL (use default if empty)
    $sanitized['callback_url'] = !empty($input['callback_url']) ? esc_url_raw($input['callback_url']) : $defaults['callback_url'];
    
    // Advanced settings
    $sanitized['sandbox_mode'] = isset($input['sandbox_mode']) && $input['sandbox_mode'] ? true : false;
    $sanitized['debug_mode'] = isset($input['debug_mode']) && $input['debug_mode'] ? true : false;
    
    // Synchronize with legacy individual options
    // @deprecated 2.0 - This is for backward compatibility
    update_option('gtaw_fleeca_enabled', $sanitized['enabled'] ? 1 : 0);
    update_option('gtaw_fleeca_api_key', $sanitized['api_key']);
    update_option('gtaw_fleeca_callback_url', $sanitized['callback_url']);
    update_option('gtaw_fleeca_gateway_name', $sanitized['gateway_name']);
    
    return $sanitized;
}

/**
 * Get default Fleeca settings
 *
 * @return array Default settings
 */
function gtaw_fleeca_default_settings() {
    return [
        'enabled' => false, // Disabled by default
        'api_key' => '',
        'callback_url' => site_url('gateway?token='),
        'gateway_name' => 'Fleeca Bank',
        'sandbox_mode' => false,
        'debug_mode' => false
    ];
}

/**
 * Get a Fleeca module setting
 *
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value
 */
function gtaw_fleeca_get_setting($key, $default = false) {
    // Get consolidated settings
    $settings = get_option('gtaw_fleeca_settings', gtaw_fleeca_default_settings());
    
    // Return the setting if it exists
    if (isset($settings[$key])) {
        return $settings[$key];
    }
    
    // Fallback to individual options for backward compatibility
    // @deprecated 2.0 - This is for backward compatibility
    switch ($key) {
        case 'enabled':
            return get_option('gtaw_fleeca_enabled', 0) == 1;
        case 'api_key':
            return get_option('gtaw_fleeca_api_key', '');
        case 'callback_url':
            return get_option('gtaw_fleeca_callback_url', site_url('gateway?token='));
        case 'gateway_name':
            return get_option('gtaw_fleeca_gateway_name', 'Fleeca Bank');
        case 'sandbox_mode':
        case 'debug_mode':
            return false; // Default for new settings
    }
    
    return $default;
}

/**
 * Migrate old individual settings to the consolidated format
 * This runs once after updating to v2.0
 */
function gtaw_fleeca_migrate_settings() {
    // Check if migration is needed
    if (get_option('gtaw_fleeca_settings_migrated', false)) {
        return;
    }
    
    // Start performance tracking
    gtaw_perf_start('fleeca_settings_migration');
    
    // Get existing individual settings
    $settings = [
        'enabled' => get_option('gtaw_fleeca_enabled', 0) == 1,
        'api_key' => get_option('gtaw_fleeca_api_key', ''),
        'callback_url' => get_option('gtaw_fleeca_callback_url', site_url('gateway?token=')),
        'gateway_name' => get_option('gtaw_fleeca_gateway_name', 'Fleeca Bank'),
        'sandbox_mode' => false,
        'debug_mode' => false
    ];
    
    // Save consolidated settings
    update_option('gtaw_fleeca_settings', $settings);
    
    // Mark as migrated
    update_option('gtaw_fleeca_settings_migrated', true);
    
    // End performance tracking
    gtaw_perf_end('fleeca_settings_migration', true);
    
    // Log the migration
    gtaw_add_log('fleeca', 'Migration', 'Migrated individual settings to consolidated format', 'success');
}

/* ========= ADMIN MENU SETUP ========= */

/**
 * Add Fleeca Settings submenu under the main GTA:W Bridge menu
 */
function gtaw_add_fleeca_settings_submenu() {
    add_submenu_page(
        'gtaw-bridge',                // Parent slug
        'Fleeca Module',              // Page title
        'Fleeca Module',              // Menu title
        'manage_options',             // Capability
        'gtaw-fleeca',                // Menu slug
        'gtaw_fleeca_settings_page_callback' // Callback function
    );
}
add_action('admin_menu', 'gtaw_add_fleeca_settings_submenu');

/**
 * Callback for the Fleeca Settings page
 */
function gtaw_fleeca_settings_page_callback() {
    // Determine active tab
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
    
    // Define available tabs
    $tabs = [
        'settings' => [
            'title' => 'Settings',
            'callback' => 'gtaw_fleeca_settings_tab'
        ],
        'logs' => [
            'title' => 'Logs',
            'callback' => 'gtaw_fleeca_logs_tab'
        ],
        'guide' => [
            'title' => 'Guide',
            'callback' => 'gtaw_fleeca_guide_tab'
        ]
    ];
    
    // Allow other modules to add tabs
    $tabs = apply_filters('gtaw_fleeca_settings_tabs', $tabs);
    ?>
    <div class="wrap">
        <h1>Fleeca Module</h1>
        
        <?php echo gtaw_generate_tabs_navigation('gtaw-fleeca', $tabs, $active_tab); ?>
        
        <div class="tab-content">
            <?php 
            // Display the active tab content
            if (isset($tabs[$active_tab]) && is_callable($tabs[$active_tab]['callback'])) {
                call_user_func($tabs[$active_tab]['callback']);
            } else {
                // Fallback to the settings tab
                gtaw_fleeca_settings_tab();
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Main settings tab content using the enhanced utilities
 */
function gtaw_fleeca_settings_tab() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        echo gtaw_admin_notice(
            '<strong>Error:</strong> WooCommerce is required for the Fleeca Bank module to work. Please install and activate WooCommerce first.', 
            'error'
        );
        return;
    }
    
    // Get consolidated settings
    $settings = get_option('gtaw_fleeca_settings', gtaw_fleeca_default_settings());
    
    // Generate default callback URL if needed
    $default_callback_url = site_url('gateway?token=');
    $callback_url = !empty($settings['callback_url']) ? $settings['callback_url'] : $default_callback_url;
    
    // Display section header
    echo gtaw_section_header('Fleeca Bank Payment Gateway', 'Configure the integration with GTA World\'s Fleeca Bank payment system.');
    
    // Settings form fields
    $fields = [
        [
            'type' => 'checkbox',
            'name' => 'gtaw_fleeca_settings[enabled]',
            'label' => 'Enable Fleeca Module',
            'default' => $settings['enabled'],
            'description' => 'Enable Fleeca Bank payment gateway for WooCommerce'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_fleeca_settings[api_key]',
            'label' => 'Fleeca API Key',
            'default' => $settings['api_key'],
            'size' => 50,
            'description' => 'Enter your Fleeca Bank API key provided in the <a href="https://ucp.gta.world/developers" target="_blank">GTA:W UCP Developers section</a>.'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_fleeca_settings[gateway_name]',
            'label' => 'Gateway Display Name',
            'default' => $settings['gateway_name'],
            'size' => 50,
            'description' => 'The name that will be displayed for this payment method during checkout.'
        ],
        [
            'type' => 'text',
            'name' => 'gtaw_fleeca_settings[callback_url]',
            'label' => 'Callback URL',
            'default' => $callback_url,
            'size' => 50,
            'readonly' => true,
            'description' => 'This URL should be provided when requesting your Fleeca API key. The URL should end with <code>?token=</code>.<br><strong>Suggested:</strong> <code>' . esc_html($default_callback_url) . '</code>'
        ],
        [
            'type' => 'checkbox',
            'name' => 'gtaw_fleeca_settings[sandbox_mode]',
            'label' => 'Sandbox Mode',
            'default' => $settings['sandbox_mode'],
            'description' => 'Enable sandbox mode for testing the payment flow without processing real transactions. Note: This setting only affects local behavior; the Fleeca Bank server controls the final sandbox status.'
        ],
        [
            'type' => 'checkbox',
            'name' => 'gtaw_fleeca_settings[debug_mode]',
            'label' => 'Debug Mode',
            'default' => $settings['debug_mode'],
            'description' => 'Enable detailed debug logging for troubleshooting. Only enable this when necessary as it generates additional logs.'
        ]
    ];
    
    // Generate the settings form
    echo gtaw_generate_settings_form('gtaw_fleeca_settings_group', $fields, 'Save Fleeca Settings');
    
    // Add hook for additional content after settings
    echo apply_filters('gtaw_fleeca_after_settings', '');
}

/**
 * Logs tab using the enhanced utility function
 */
function gtaw_fleeca_logs_tab() {
    // Get current page from URL
    $page = isset($_GET['logs_page']) ? max(1, intval($_GET['logs_page'])) : 1;
    
    // Get logs per page from URL or use the saved setting
    $logs_per_page = isset($_GET['logs_per_page']) ? absint($_GET['logs_per_page']) : gtaw_get_logs_per_page();
    
    // Display the logs
    echo gtaw_display_module_logs('fleeca', $logs_per_page, $page);
}

/* ========= SUBMODULE LOADER ========= */

/**
 * Load Fleeca submodules efficiently with conditional loading
 *
 * @param bool $conditional_loading Whether to use conditional loading (default: true)
 */
function gtaw_load_fleeca_submodules($conditional_loading = true) {
    // Don't load Fleeca module on irrelevant pages
    if ($conditional_loading && !gtaw_fleeca_should_load()) {
        return;
    }
    
    // Core functionality must be loaded first as other modules depend on it
    if (file_exists(GTAW_FLEECA_PATH . 'core.php')) {
        require_once GTAW_FLEECA_PATH . 'core.php';
    }
    
    // Load the rest of the submodules
    $submodules = [
        'gateway.php',
        'callback-handler.php'
    ];
    
    foreach ($submodules as $submodule) {
        if (file_exists(GTAW_FLEECA_PATH . $submodule)) {
            require_once GTAW_FLEECA_PATH . $submodule;
        }
    }
    
    // Only load guide in admin
    if (is_admin() && file_exists(GTAW_FLEECA_PATH . 'guide.php')) {
        require_once GTAW_FLEECA_PATH . 'guide.php';
    }
}

/**
 * Check if Fleeca module should be loaded
 * Optimizes performance by only loading on relevant pages
 * 
 * @return bool Whether to load Fleeca module
 */
function gtaw_fleeca_should_load() {
    // Always load in admin context
    if (is_admin()) {
        return true;
    }
    
    // Special handling for REST API and AJAX requests
    if (defined('REST_REQUEST') || defined('DOING_AJAX')) {
        // Only load for specific AJAX actions
        if (defined('DOING_AJAX') && isset($_REQUEST['action'])) {
            $fleeca_ajax_actions = [
                'gtaw_fleeca_flush_rules',
                'gtaw_fleeca_validate_token',
                'gtaw_fleeca_clear_token_cache',
                'gtaw_fleeca_reprocess_payment',
                // WooCommerce AJAX actions that might need Fleeca
                'woocommerce_checkout',
                'woocommerce_apply_coupon',
                'woocommerce_remove_coupon',
                'woocommerce_update_shipping_method',
                'woocommerce_update_order_review'
            ];
            
            return in_array($_REQUEST['action'], $fleeca_ajax_actions);
        }
        return false;
    }
    
    // Check for gateway callback in URL - this must be loaded always
    // This is an early check that doesn't rely on WordPress query
    if (isset($_GET['token']) && 
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/gateway') === 0)) {
        return true;
    }
    
    // At this point we need to be cautious about when we check WooCommerce conditionals
    // Don't use conditional tags too early (before 'wp' action)
    if (!did_action('wp')) {
        // Default to false for early requests, proper check will happen after 'wp'
        return false;
    }
    
    // Now we can safely use conditional tags
    if (function_exists('is_woocommerce') && is_woocommerce()) {
        return true;
    }
    
    if (function_exists('is_cart') && is_cart()) {
        return true;
    }
    
    if (function_exists('is_checkout') && is_checkout()) {
        return true;
    }
    
    if (function_exists('is_account_page') && is_account_page()) {
        return true;
    }
    
    // Default to not loading unless specifically needed
    return false;
}

/**
 * Initialize the Fleeca module
 */
function gtaw_init_fleeca_module() {
    // Prevent multiple initializations for the same request
    static $initialized = false;
    if ($initialized) {
        return;
    }
    
    // Start performance tracking
    gtaw_perf_start('fleeca_module_load');
    
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        gtaw_perf_end('fleeca_module_load');
        return;
    }
    
    // Run settings migration
    gtaw_fleeca_migrate_settings();
    
    // Check if the Fleeca module is enabled
    if (!gtaw_fleeca_get_setting('enabled', false)) {
        gtaw_perf_end('fleeca_module_load');
        return;
    }
    
    // Always load core file - it's needed for settings and basic functionality
    if (file_exists(GTAW_FLEECA_PATH . 'core.php')) {
        require_once GTAW_FLEECA_PATH . 'core.php';
    }
    
    // Load the callback handler for rewrite rules and endpoint registration
    if (file_exists(GTAW_FLEECA_PATH . 'callback-handler.php')) {
        require_once GTAW_FLEECA_PATH . 'callback-handler.php';
    }
    
    // IMPORTANT: Always load the gateway file for WooCommerce integration
    // This ensures the payment method appears at checkout
    if (file_exists(GTAW_FLEECA_PATH . 'gateway.php')) {
        require_once GTAW_FLEECA_PATH . 'gateway.php';
    }
    
    // Only load guide in admin
    if (is_admin() && file_exists(GTAW_FLEECA_PATH . 'guide.php')) {
        require_once GTAW_FLEECA_PATH . 'guide.php';
    }
    
    // Mark as initialized to prevent duplicate operations
    $initialized = true;
    

    
    // End performance tracking
    gtaw_perf_end('fleeca_module_load', true);
}
add_action('plugins_loaded', 'gtaw_init_fleeca_module', 15); // Priority 15 to load after WooCommerce and main plugin

/**
 * Delayed loading for WooCommerce-related pages
 * Only runs after WordPress has set up the query and we can safely use conditional tags
 */
function gtaw_maybe_load_fleeca_gateway() {
    // Only load if not already loaded
    if (class_exists('WC_Gateway_Fleeca')) {
        return;
    }
    
    // Now it's safe to use WooCommerce conditional tags
    if (function_exists('is_woocommerce') && is_woocommerce() ||
        function_exists('is_checkout') && is_checkout() ||
        function_exists('is_cart') && is_cart() ||
        function_exists('is_account_page') && is_account_page()) {
        
        if (file_exists(GTAW_FLEECA_PATH . 'gateway.php')) {
            require_once GTAW_FLEECA_PATH . 'gateway.php';
        }
    }
}

/**
 * Check if Fleeca AJAX functionality is required for current request
 * This prevents unnecessary initialization for most AJAX calls
 * 
 * @return bool Whether Fleeca needs to be loaded for current AJAX request
 */
function gtaw_fleeca_ajax_required() {
    if (!isset($_REQUEST['action'])) {
        return false;
    }
    
    // List of AJAX actions that require Fleeca functionality
    $fleeca_ajax_actions = [
        'gtaw_fleeca_flush_rules',
        'gtaw_fleeca_validate_token',
        'gtaw_fleeca_clear_token_cache',
        'gtaw_fleeca_reprocess_payment'
    ];
    
    return in_array($_REQUEST['action'], $fleeca_ajax_actions);
}

/**
 * Conditionally load admin-specific scripts and styles
 */
function gtaw_fleeca_admin_scripts($hook) {
    // Only load on our module page
    if (gtaw_is_plugin_page($hook, 'gtaw-fleeca')) {
        // Enqueue scripts/styles specific to Fleeca admin
        wp_enqueue_style('gtaw-fleeca-admin-style', GTAW_BRIDGE_PLUGIN_URL . 'assets/css/gtaw-fleeca-admin.css', [], GTAW_FLEECA_VERSION);
        wp_enqueue_script('gtaw-fleeca-admin-script', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-fleeca-admin.js', ['jquery'], GTAW_FLEECA_VERSION, true);
    }
}
add_action('admin_enqueue_scripts', 'gtaw_fleeca_admin_scripts');

/**
 * Add AJAX handlers for Fleeca module
 */
function gtaw_fleeca_ajax_handlers() {
    // Manual flush rewrite rules for troubleshooting
    add_action('wp_ajax_gtaw_fleeca_flush_rules', function() {
        // Security check with enhanced utility function
        if (!gtaw_ajax_security_check('fleeca', 'nonce', 'gtaw_fleeca_flush_rules', 'manage_options', 'flush rules')) {
            return;
        }
        
        flush_rewrite_rules();
        wp_send_json_success('Rewrite rules have been flushed successfully.');
    });
}
add_action('admin_init', 'gtaw_fleeca_ajax_handlers');