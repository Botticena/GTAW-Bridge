<?php
/*
Plugin Name: GTA:W Bridge
Description: GTA:World Roleplay Wordpress Bridge with oAuth.
Version: 0.0.0
Author: Lena
Author URI: https://forum.gta.world/en/profile/56418-lena/
Plugin URI: https://github.com/Botticena/gtaw-bridge/
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GTAW_BRIDGE_VERSION', '1.1');
define('GTAW_BRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GTAW_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));

/* ========= PLUGIN ACTIVATION AND REQUIREMENT CHECKS ========= */
function gtaw_check_requirements() {
    if (is_admin()) {
        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            add_action('admin_notices', 'gtaw_wc_admin_notice');
        }
    }
}
add_action('admin_init', 'gtaw_check_requirements');

function gtaw_wc_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    echo '<div class="notice notice-error is-dismissible">';
    echo '<p><strong>GTAW Bridge Plugin Notice:</strong> WooCommerce must be installed and activated for this plugin to work properly. Please <a href="' . esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')) . '">install WooCommerce</a> or activate it if it is already installed.</p>';
    echo '</div>';
}

/* ========= ENQUEUE SCRIPTS & STYLES ========= */
function gtaw_enqueue_scripts() {
    // Frontend scripts - only load what's needed based on enabled modules
    $oauth_enabled = get_option('gtaw_oauth_enabled', 1) == 1;
    $discord_enabled = get_option('gtaw_discord_enabled', 0) == 1;
    
    // Core styles always needed
    wp_enqueue_style('gtaw-style', GTAW_BRIDGE_PLUGIN_URL . 'assets/css/gtaw-style.css');
    
    // Only load OAuth-related scripts if the module is enabled
    if ($oauth_enabled) {
        wp_enqueue_script('gtaw-script', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-script.js', array('jquery'), GTAW_BRIDGE_VERSION, true);
        wp_localize_script('gtaw-script', 'gtaw_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('gtaw_nonce'),
        ));
    }
    
    // Load Discord-specific scripts only if that module is enabled
    if ($discord_enabled && is_user_logged_in()) {
        // If we have Discord-specific frontend scripts, they would go here
    }
}
add_action('wp_enqueue_scripts', 'gtaw_enqueue_scripts');

/* ========= ADMIN ASSETS ========= */
function gtaw_admin_enqueue_scripts($hook) {
    // Only load admin assets on our plugin pages
    if (strpos($hook, 'gtaw-bridge') === false) {
        return;
    }
    
    // Admin dashboard styles
    wp_enqueue_style('gtaw-admin-style', GTAW_BRIDGE_PLUGIN_URL . 'assets/css/gtaw-admin.css', array(), GTAW_BRIDGE_VERSION);
    
    // Admin common JavaScript
    wp_enqueue_script('gtaw-admin-script', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-admin.js', array('jquery'), GTAW_BRIDGE_VERSION, true);

    // Module-specific admin scripts
    $discord_enabled = get_option('gtaw_discord_enabled', 0) == 1;
    if ($discord_enabled && isset($_GET['page']) && $_GET['page'] === 'gtaw-discord') {
        wp_enqueue_script('gtaw-discord-admin', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-discord-admin.js', array('jquery'), GTAW_BRIDGE_VERSION, true);
    }
}
add_action('admin_enqueue_scripts', 'gtaw_admin_enqueue_scripts');

/* ========= MAIN ADMIN MENU ========= */
function gtaw_add_main_menu() {
    add_menu_page(
        'GTA:W Bridge',          // Page title.
        'GTA:W Bridge',          // Menu title.
        'manage_options',        // Capability.
        'gtaw-bridge',           // Menu slug.
        'gtaw_main_page_callback', // Callback function.
        'dashicons-admin-site',  // Icon.
        2                        // Position.
    );
}
add_action('admin_menu', 'gtaw_add_main_menu');

/* ========= CORE LOGGING FUNCTIONALITY ========= */
// Always include the logging module as it's used by all other modules
require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-logging.php';
// Load shared utility functions used across modules
require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-utilities.php';
// Load Github Updater module
require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-updates.php';

/* ========= MODULE LOADER ========= */
function gtaw_load_modules() {
    // Check module status for each module and only load if enabled
    
    // 1. OAuth Module - enabled by default
    if (get_option('gtaw_oauth_enabled', 1) == 1) {
        require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-oauth.php';
    }
    
    // 2. Discord Module - disabled by default
    if (get_option('gtaw_discord_enabled', 0) == 1) {
        require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-discord.php';
    }
    
    // 3. Fleeca Module - disabled by default, requires WooCommerce
    if (get_option('gtaw_fleeca_enabled', 0) == 1 && class_exists('WooCommerce')) {
        require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-fleeca.php';
    }
}
add_action('plugins_loaded', 'gtaw_load_modules', 10); // Priority 10 is default



// Main page callback function (keeping the existing implementation)
function gtaw_main_page_callback() {
    // Existing code for the main dashboard page
    // Check if settings were updated
    $updated = false;
    if (isset($_POST['gtaw_module_update']) && isset($_POST['gtaw_module_nonce']) && wp_verify_nonce($_POST['gtaw_module_nonce'], 'gtaw_module_toggle')) {
        $module = sanitize_text_field($_POST['gtaw_module_name']);
        $status = isset($_POST['gtaw_module_status']) ? $_POST['gtaw_module_status'] : 'off';
        
        switch ($module) {
            case 'oauth':
                update_option('gtaw_oauth_enabled', $status === 'on' ? 1 : 0);
                break;
            case 'discord':
                update_option('gtaw_discord_enabled', $status === 'on' ? 1 : 0);
                break;
            case 'fleeca':
                update_option('gtaw_fleeca_enabled', $status === 'on' ? 1 : 0);
                break;
        }
        
        // After updating, redirect to refresh the page and menu
        wp_redirect(admin_url('admin.php?page=gtaw-bridge&updated=1'));
        exit;
    }
    
    // Check for the updated flag from redirect
    $updated = isset($_GET['updated']) && $_GET['updated'] == 1;
    
    // Get current module statuses
    $oauth_status = get_option('gtaw_oauth_enabled', 1); // Default enabled
    $discord_status = get_option('gtaw_discord_enabled', 0);
    $fleeca_status = get_option('gtaw_fleeca_enabled', 0);
    
    // Get recent logs from all modules using the new logging system
    $modules_for_logs = array('oauth', 'discord', 'fleeca');
    $combined_logs = gtaw_get_combined_logs($modules_for_logs, 10, 0);

    // If no logs found from database, try legacy option method as fallback
    if (empty($combined_logs) && !gtaw_logging_table_exists()) {
        $combined_logs = array();
        
        foreach ($modules_for_logs as $module) {
            $logs = get_option("gtaw_{$module}_logs", array());
            foreach ($logs as $log) {
                $log['module'] = $module;
                $combined_logs[] = $log;
            }
        }
        
        // Sort by date (newest first)
        usort($combined_logs, function($a, $b) {
            $a_time = isset($a['date']) ? strtotime($a['date']) : 0;
            $b_time = isset($b['date']) ? strtotime($b['date']) : 0;
            return $b_time - $a_time;
        });
        
        // Limit to 10 logs
        $combined_logs = array_slice($combined_logs, 0, 10);
    }
    
    // Define our modules
    $modules = array(
        'oauth' => array(
            'name' => 'OAuth Module',
            'description' => 'Enables GTA:W single sign-on authentication and character-based accounts.',
            'status' => $oauth_status,
            'settings_url' => admin_url('admin.php?page=gtaw-oauth'),
            'icon' => 'dashicons-lock'
        ),
        'discord' => array(
            'name' => 'Discord Module',
            'description' => 'Integrates with Discord for account linking, role mapping, and notifications.',
            'status' => $discord_status,
            'settings_url' => admin_url('admin.php?page=gtaw-discord'),
            'icon' => 'dashicons-format-chat'
        ),
        'fleeca' => array(
            'name' => 'Fleeca Bank Module',
            'description' => 'Adds Fleeca Bank as a payment method for WooCommerce.',
            'status' => $fleeca_status,
            'settings_url' => admin_url('admin.php?page=gtaw-fleeca'),
            'icon' => 'dashicons-money-alt'
        )
    );
    
    // Include the dashboard template
    include GTAW_BRIDGE_PLUGIN_DIR . 'templates/admin-dashboard.php';
}

/**
 * Modifications for module menus
 */
function gtaw_filter_module_submenus() {
    global $submenu;
    
    // If submenu for gtaw-bridge doesn't exist, bail
    if (!isset($submenu['gtaw-bridge'])) {
        return;
    }
    
    // Get module statuses
    $oauth_status = get_option('gtaw_oauth_enabled', 1);
    $discord_status = get_option('gtaw_discord_enabled', 0);
    $fleeca_status = get_option('gtaw_fleeca_enabled', 0);
    
    // Storage for menu items to remove
    $items_to_remove = array();
    
    // Check each submenu item
    foreach ($submenu['gtaw-bridge'] as $key => $item) {
        // OAuth Module
        if ($item[2] === 'gtaw-oauth' && !$oauth_status) {
            $items_to_remove[] = $key;
        }
        
        // Discord Module
        if ($item[2] === 'gtaw-discord' && !$discord_status) {
            $items_to_remove[] = $key;
        }
        
        // Fleeca Module
        if ($item[2] === 'gtaw-fleeca' && !$fleeca_status) {
            $items_to_remove[] = $key;
        }
    }
    
    // Remove identified menu items
    foreach ($items_to_remove as $key) {
        unset($submenu['gtaw-bridge'][$key]);
    }
}
add_action('admin_menu', 'gtaw_filter_module_submenus', 999);

/**
 * Preserve module enabled status when saving module settings
 */
function gtaw_preserve_module_status() {
    // Only run on admin pages
    if (!is_admin()) {
        return;
    }
    
    // Check if we're processing a settings form submission
    if (isset($_POST['option_page'])) {
        $option_page = $_POST['option_page'];
        
        // OAuth settings form
        if ($option_page === 'gtaw_oauth_settings_group') {
            // Add the module status to the $_POST data
            $_POST['gtaw_oauth_enabled'] = get_option('gtaw_oauth_enabled', 1);
        }
        
        // Discord settings form
        if ($option_page === 'gtaw_discord_settings_group') {
            // Add the module status to the $_POST data
            $_POST['gtaw_discord_enabled'] = get_option('gtaw_discord_enabled', 0);
        }
        
        // Fleeca settings form
        if ($option_page === 'gtaw_fleeca_settings_group') {
            // Add the module status to the $_POST data
            $_POST['gtaw_fleeca_enabled'] = get_option('gtaw_fleeca_enabled', 0);
        }
    }
}
add_action('admin_init', 'gtaw_preserve_module_status', 5); // Early priority

/**
 * Check if a module is enabled
 *
 * @param string $module The module name (oauth, discord, fleeca)
 * @return bool Whether the module is enabled
 */
function gtaw_is_module_enabled($module) {
    switch ($module) {
        case 'oauth':
            return get_option('gtaw_oauth_enabled', 1) == 1;
        case 'discord':
            return get_option('gtaw_discord_enabled', 0) == 1;
        case 'fleeca':
            return get_option('gtaw_fleeca_enabled', 0) == 1;
        default:
            return false;
    }
}

/**
 * Prevent direct access to disabled module pages
 */
function gtaw_prevent_disabled_module_access() {
    global $pagenow;
    
    // Only check on admin pages
    if (!is_admin() || $pagenow !== 'admin.php') {
        return;
    }
    
    // Check if we're on a module page
    if (isset($_GET['page'])) {
        $page = $_GET['page'];
        
        // OAuth Module
        if ($page === 'gtaw-oauth' && !gtaw_is_module_enabled('oauth')) {
            wp_redirect(admin_url('admin.php?page=gtaw-bridge'));
            exit;
        }
        
        // Discord Module
        if ($page === 'gtaw-discord' && !gtaw_is_module_enabled('discord')) {
            wp_redirect(admin_url('admin.php?page=gtaw-bridge'));
            exit;
        }
        
        // Fleeca Module
        if ($page === 'gtaw-fleeca' && !gtaw_is_module_enabled('fleeca')) {
            wp_redirect(admin_url('admin.php?page=gtaw-bridge'));
            exit;
        }
    }
}
add_action('admin_init', 'gtaw_prevent_disabled_module_access');

function gtaw_bridge_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=gtaw-bridge') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'gtaw_bridge_action_links');

/**
 * Plugin activation hook
 */
function gtaw_bridge_activation() {
    // Create log table immediately on activation
    if (function_exists('gtaw_create_logs_table')) {
        gtaw_create_logs_table();
    } else {
        // Schedule the table creation for when the logging module is loaded
        update_option('gtaw_logs_create_on_init', '1');
    }

    // Register Discord endpoint if module is enabled
    if (get_option('gtaw_discord_enabled', 0) == 1) {
        add_rewrite_endpoint('discord', EP_ROOT | EP_PAGES);
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'gtaw_bridge_activation');

/**
 * Plugin deactivation hook
 */
function gtaw_bridge_deactivation() {
    // Remove scheduled log cleanup
    wp_clear_scheduled_hook('gtaw_daily_log_cleanup');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'gtaw_bridge_deactivation');

// Add this check to your plugin init to handle activation when functions are available
add_action('init', function() {
    if (get_option('gtaw_logs_create_on_init', '0') === '1') {
        if (function_exists('gtaw_create_logs_table')) {
            gtaw_create_logs_table();
            delete_option('gtaw_logs_create_on_init');
        }
    }
}, 5); // Priority 5 ensures it runs early