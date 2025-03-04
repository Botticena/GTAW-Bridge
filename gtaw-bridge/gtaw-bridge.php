<?php
/*
Plugin Name: GTA:W Bridge
Description: GTA:World Roleplay Wordpress Bridge with oAuth.
Version: 1.0
Author: Lena
Author URI: https://forum.gta.world/en/profile/56418-lena/
Plugin URI: https://github.com/Botticena/gtaw-bridge/
*/

if ( is_admin() ) {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        add_action( 'admin_notices', 'gtaw_wc_admin_notice' );
        // Stop further execution if WooCommerce is not active.
        return;
    }
}

function gtaw_wc_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    echo '<div class="notice notice-error is-dismissible">';
    echo '<p><strong>GTAW Bridge Plugin Notice:</strong> WooCommerce must be installed and activated for this plugin to work properly. Please <a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">install WooCommerce</a> or activate it if it is already installed.</p>';
    echo '</div>';
}

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ========= ENQUEUE SCRIPTS & STYLES ========= */
function gtaw_enqueue_scripts() {
    wp_enqueue_script('gtaw-script', plugin_dir_url(__FILE__) . 'assets/js/gtaw-script.js', array('jquery'), '1.0', true);
    wp_localize_script('gtaw-script', 'gtaw_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('gtaw_nonce'),
    ));
    wp_enqueue_style('gtaw-style', plugin_dir_url(__FILE__) . 'assets/css/gtaw-style.css');
}
add_action('wp_enqueue_scripts', 'gtaw_enqueue_scripts');

/* ========= MAIN ADMIN MENU ========= */
// Register a main admin menu page.
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

function gtaw_main_page_callback() {
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
    
    // Get the last 10 logs from all modules
    $combined_logs = array();
    $modules_for_logs = array('oauth', 'discord', 'fleeca');
    
    foreach ($modules_for_logs as $module) {
        $logs = get_option("gtaw_{$module}_logs", array());
        foreach ($logs as $log) {
            $log['module'] = $module;
            $combined_logs[] = $log;
        }
    }
    
    // Sort logs by date (newest first)
    usort($combined_logs, function($a, $b) {
        $a_time = isset($a['date']) ? strtotime($a['date']) : 0;
        $b_time = isset($b['date']) ? strtotime($b['date']) : 0;
        return $b_time - $a_time;
    });
    
    // Limit to 10 logs
    $combined_logs = array_slice($combined_logs, 0, 10);
    
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
    ?>
    <style>
        /* Dashboard Layout */
        .gtaw-dashboard {
            margin-top: 20px;
        }
        
        .gtaw-dashboard-main {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .gtaw-dashboard-modules,
        .gtaw-dashboard-logs {
            flex: 1 1 100%;
            padding: 0 15px;
            margin-bottom: 30px;
        }
        
        @media (min-width: 1200px) {
            .gtaw-dashboard-modules {
                flex: 0 0 60%;
            }
            
            .gtaw-dashboard-logs {
                flex: 0 0 40%;
            }
        }
        
        /* Module Cards */
        .gtaw-module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .gtaw-module-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 220px;
        }
        
        .gtaw-module-card.active {
            border-left: 4px solid #46b450;
        }
        
        .gtaw-module-card.inactive {
            border-left: 4px solid #dc3232;
        }
        
        /* Module Headers */
        .gtaw-module-header {
            display: flex;
            align-items: center;
            padding: 15px 20px 5px;
        }
        
        .gtaw-module-icon {
            margin-right: 15px;
        }
        
        .gtaw-module-icon .dashicons {
            font-size: 32px;
            width: 32px;
            height: 32px;
            color: #555;
        }
        
        .gtaw-module-title {
            flex: 1;
        }
        
        .gtaw-module-title h3 {
            margin: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        /* Module Content */
        .gtaw-module-description {
            padding: 0 20px;
            flex: 1;
        }
        
        .gtaw-module-card p {
            margin-top: 0;
        }
        
        /* Module Actions */
        .gtaw-module-actions {
            padding: 15px 20px 20px;
            margin-top: auto;
        }
        
        .gtaw-button-container {
            display: flex;
            gap: 10px;
        }
        
        .gtaw-module-actions .button {
            flex: 1;
            text-align: center;
            justify-content: center;
            display: flex;
        }
        
        .gtaw-module-actions .activate {
            background: #46b450;
            border-color: #46b450;
            color: white;
        }
        
        .gtaw-module-actions .deactivate {
            background: #dc3232;
            border-color: #dc3232;
            color: white;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: normal;
            line-height: 1;
            margin-left: 6px;
        }
        
        .status-badge.active {
            background: #46b450;
            color: white;
        }
        
        .status-badge.inactive {
            background: #dc3232;
            color: white;
        }
        
        /* Logs */
        .log-entry.success {
            color: #46b450;
        }
        
        .log-entry.error {
            color: #dc3232;
        }
        
        .gtaw-logs-more {
            text-align: right;
            margin-top: 10px;
        }
    </style>
    <div class="wrap gtaw-dashboard">
        <h1>GTA:W Bridge Dashboard</h1>
        
        <?php if ($updated): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>Settings updated successfully!</strong></p>
        </div>
        <?php endif; ?>
        
        <div class="gtaw-dashboard-header">
            <div class="welcome-panel">
                <div class="welcome-panel-content">
                    <h2>Welcome to GTA:W Bridge</h2>
                    <p class="about-description">Connect your WordPress site with the GTA World roleplay platform for seamless integration.</p>
                    
                    <div class="welcome-panel-column-container">
                        <div class="welcome-panel-column">
                            <h3>Getting Started</h3>
                            <ul>
                                <li><a href="https://github.com/Botticena/gtaw-bridge/" target="_blank" class="button button-primary">Documentation</a></li>
                                <li>Activate the modules you need below</li>
                                <li>Configure each module's settings</li>
                            </ul>
                        </div>
                        <div class="welcome-panel-column">
                            <h3>Quick Actions</h3>
                            <ul>
                                <?php if ($oauth_status): ?>
                                <li><a href="<?php echo admin_url('admin.php?page=gtaw-oauth'); ?>">OAuth Settings</a></li>
                                <?php endif; ?>
                                <?php if ($discord_status): ?>
                                <li><a href="<?php echo admin_url('admin.php?page=gtaw-discord'); ?>">Discord Settings</a></li>
                                <?php endif; ?>
                                <?php if ($fleeca_status): ?>
                                <li><a href="<?php echo admin_url('admin.php?page=gtaw-fleeca'); ?>">Fleeca Bank Settings</a></li>
                                <?php endif; ?>
                                <?php if (!$oauth_status && !$discord_status && !$fleeca_status): ?>
                                <li>No active modules. Activate one below to access its settings.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="welcome-panel-column welcome-panel-last">
                            <h3>About</h3>
                            <p>Version: 1.1<br>
                            Created by: <a href="https://forum.gta.world/en/profile/56418-lena/" target="_blank">Lena</a><br>
                            Need help? Open an issue on <a href="https://github.com/Botticena/gtaw-bridge/issues" target="_blank">GitHub</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="gtaw-dashboard-main">
            <div class="gtaw-dashboard-modules">
                <h2>Module Management</h2>
                <p>Enable or disable modules based on your needs. Click on Settings to configure an active module.</p>
                
                <div class="gtaw-module-grid">
                    <?php foreach ($modules as $module_id => $module): ?>
                    <div class="gtaw-module-card <?php echo $module['status'] ? 'active' : 'inactive'; ?>">
                        <div class="gtaw-module-header">
                            <div class="gtaw-module-icon">
                                <span class="dashicons <?php echo esc_attr($module['icon']); ?>"></span>
                            </div>
                            <div class="gtaw-module-title">
                                <h3><?php echo esc_html($module['name']); ?>
                                    <span class="status-badge <?php echo $module['status'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $module['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </h3>
                            </div>
                        </div>
                        
                        <div class="gtaw-module-description">
                            <p><?php echo esc_html($module['description']); ?></p>
                        </div>
                        
                        <div class="gtaw-module-actions">
                            <form method="post" class="module-toggle-form">
                                <?php wp_nonce_field('gtaw_module_toggle', 'gtaw_module_nonce'); ?>
                                <input type="hidden" name="gtaw_module_update" value="1">
                                <input type="hidden" name="gtaw_module_name" value="<?php echo esc_attr($module_id); ?>">
                                <input type="hidden" name="gtaw_module_status" value="<?php echo $module['status'] ? 'off' : 'on'; ?>">
                                
                                <div class="gtaw-button-container">
                                    <button type="submit" class="button <?php echo $module['status'] ? 'deactivate' : 'activate'; ?>">
                                        <?php echo $module['status'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                    
                                    <?php if ($module['status']): ?>
                                    <a href="<?php echo esc_url($module['settings_url']); ?>" class="button settings-button">
                                        Settings
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="gtaw-dashboard-logs">
                <h2>Recent Activity Logs</h2>
                <p>Here are the most recent logs from all modules.</p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($combined_logs)): ?>
                            <tr>
                                <td colspan="4">No logs available yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($combined_logs as $log): ?>
                                <tr class="log-entry <?php echo esc_attr($log['status']); ?>">
                                    <td><?php echo esc_html(ucfirst($log['module'])); ?></td>
                                    <td><?php echo esc_html($log['type']); ?></td>
                                    <td><?php echo esc_html($log['message']); ?></td>
                                    <td><?php echo esc_html($log['date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p class="gtaw-logs-more">
                    
                    <?php if ($oauth_status): ?>
                    <a href="<?php echo admin_url('admin.php?page=gtaw-oauth&tab=logs'); ?>">View OAuth Logs</a>&emsp;
                    <?php endif; ?>
                    <?php if ($discord_status): ?>
                    <a href="<?php echo admin_url('admin.php?page=gtaw-discord&tab=logs'); ?>">View Discord Logs</a>&emsp;
                    <?php endif; ?>
                    <?php if ($fleeca_status): ?>
                    <a href="<?php echo admin_url('admin.php?page=gtaw-fleeca&tab=logs'); ?>">View Fleeca Logs</a>&emsp;
                    <?php endif; ?>
                    <?php if (!$oauth_status && !$discord_status && !$fleeca_status): ?>
                    No active modules. Activate one to access logs.&emsp;
                    <?php endif; ?>
                    
                </p>
            </div>
        </div>
    </div>
    <?php
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

/* ========= MODULE LOADER ========= */
// Dynamically load all modules from the "modules" directory.
function gtaw_load_modules() {
    $modules_dir = plugin_dir_path(__FILE__) . 'modules/';
    if ( is_dir( $modules_dir ) ) {
        foreach ( glob( $modules_dir . '*.php' ) as $module_file ) {
            include_once $module_file;
        }
    }
}
add_action('plugins_loaded', 'gtaw_load_modules');

function gtaw_bridge_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=gtaw-bridge') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'gtaw_bridge_action_links');


