<?php
defined('ABSPATH') or exit;

/* ========= FLEECA MODULE MAIN FILE ========= */
/*
 * This file serves as the entry point for the Fleeca module.
 * It handles:
 * - Core settings registration
 * - Admin menu setup
 * - Tab navigation
 * - Loading all Fleeca submodules
 */

/* ========= ADMIN SETTINGS ========= */

// Register Fleeca core settings
function gtaw_fleeca_register_settings() {
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_enabled');
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_api_key');
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_callback_url');
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_gateway_name');
}
add_action('admin_init', 'gtaw_fleeca_register_settings');

// Add Fleeca Settings submenu under the main GTA:W Bridge menu
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

// Callback for the Fleeca Settings page
function gtaw_fleeca_settings_page_callback() {
    // Get basic settings
    $enabled = get_option('gtaw_fleeca_enabled', 0);
    $api_key = get_option('gtaw_fleeca_api_key', '');
    $logs = gtaw_get_logs('fleeca');
    
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
        
        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_id => $tab): ?>
                <a href="?page=gtaw-fleeca&tab=<?php echo esc_attr($tab_id); ?>" 
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
                gtaw_fleeca_settings_tab();
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
                    module: "fleeca"
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
function gtaw_fleeca_settings_tab() {
    $enabled = get_option('gtaw_fleeca_enabled', 0);
    $api_key = get_option('gtaw_fleeca_api_key', '');
    $gateway_name = get_option('gtaw_fleeca_gateway_name', 'Fleeca Bank');
    
    // Generate the suggested callback URL
    $suggested_callback_url = site_url('gateway?token=');
    $callback_url = get_option('gtaw_fleeca_callback_url', $suggested_callback_url);
    ?>
    <?php
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error"><p><strong>Error:</strong> WooCommerce is required for the Fleeca Bank module to work. Please install and activate WooCommerce first.</p></div>';
    }
    ?>
    <form method="post" action="options.php">
        <?php 
            settings_fields('gtaw_fleeca_settings_group');
            do_settings_sections('gtaw_fleeca_settings_group');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Activate Fleeca Module</th>
                <td>
                    <input type="checkbox" name="gtaw_fleeca_enabled" value="1" <?php checked($enabled, 1); ?> <?php echo !class_exists('WooCommerce') ? 'disabled' : ''; ?> />
                    <p class="description">Check to activate Fleeca Bank integration for WooCommerce. Uncheck to disable.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Fleeca API Key</th>
                <td>
                    <input type="text" name="gtaw_fleeca_api_key" value="<?php echo esc_attr($api_key); ?>" size="50" />
                    <p class="description">Enter your Fleeca Bank API key provided in the <a href="https://ucp.gta.world/developers" target="_blank">GTA:W UCP Developers section</a>.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Gateway Display Name</th>
                <td>
                    <input type="text" name="gtaw_fleeca_gateway_name" value="<?php echo esc_attr($gateway_name); ?>" size="50" />
                    <p class="description">The name that will be displayed for this payment method during checkout.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Callback URL</th>
                <td>
                    <input type="text" name="gtaw_fleeca_callback_url" value="<?php echo esc_url($callback_url); ?>" size="50" />
                    <p class="description">This URL should be provided when requesting your Fleeca API key. The URL should end with <code>?token=</code>.</p>
                    <p class="description"><strong>Suggested:</strong> <code><?php echo esc_html($suggested_callback_url); ?></code></p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <?php
}

// Logs tab
function gtaw_fleeca_logs_tab() {
    $logs = gtaw_get_logs('fleeca');
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

/* ========= LOAD FLEECA SUBMODULES ========= */

// Define the submodules path 
define('GTAW_FLEECA_PATH', plugin_dir_path(__FILE__) . 'fleeca/');

// Load all Fleeca submodules
function gtaw_load_fleeca_submodules() {
    // Core functionality must be loaded first as other modules depend on it
    if (file_exists(GTAW_FLEECA_PATH . 'core.php')) {
        require_once GTAW_FLEECA_PATH . 'core.php';
    }
    
    // Load the rest of the submodules
    $submodules = [
        'gateway.php',
        'callback-handler.php',
        'guide.php'
    ];
    
    foreach ($submodules as $submodule) {
        if (file_exists(GTAW_FLEECA_PATH . $submodule)) {
            require_once GTAW_FLEECA_PATH . $submodule;
        }
    }
}

// Only load Fleeca functionality if the module is enabled and WooCommerce is active
function gtaw_init_fleeca_module() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Check if the Fleeca module is enabled
    if (get_option('gtaw_fleeca_enabled', 0) == 1) {
        gtaw_load_fleeca_submodules();
    }
}
add_action('plugins_loaded', 'gtaw_init_fleeca_module', 15); // Priority 15 to load after WooCommerce and main plugin