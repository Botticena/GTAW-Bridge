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

/* ========= ADMIN SETTINGS ========= */

// Register OAuth core settings
function gtaw_oauth_register_settings() {
    register_setting('gtaw_oauth_settings_group', 'gtaw_oauth_enabled');
    register_setting('gtaw_oauth_settings_group', 'gtaw_client_id');
    register_setting('gtaw_oauth_settings_group', 'gtaw_client_secret');
    register_setting('gtaw_oauth_settings_group', 'gtaw_callback_url');
}
add_action('admin_init', 'gtaw_oauth_register_settings');

// Add OAuth Settings submenu under the main GTA:W Bridge menu
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

// Callback for the OAuth Settings page
function gtaw_oauth_settings_page_callback() {
    // Set the default callback URL.
    $default_oauth_callback = site_url('?gta_oauth=callback');
    $enabled      = get_option('gtaw_oauth_enabled', 1); // Default enabled.
    $client_id    = get_option('gtaw_client_id', '');
    $oauth_callback_url = get_option('gtaw_callback_url', $default_oauth_callback);
    $logs = gtaw_get_logs('oauth');
    
    // Generate the login link.
    $login_link = add_query_arg(array(
        'client_id'     => $client_id,
        'redirect_uri'  => urlencode($oauth_callback_url),
        'response_type' => 'code',
        'scope'         => ''
    ), 'https://ucp.gta.world/oauth/authorize');
    
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
        
        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_id => $tab): ?>
                <a href="?page=gtaw-oauth&tab=<?php echo esc_attr($tab_id); ?>" 
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
                gtaw_oauth_settings_tab();
            }
            ?>
        </div>
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Clear logs functionality
        document.getElementById("clear-logs")?.addEventListener("click", function() {
            if (confirm("Are you sure you want to clear all logs?")) {
                fetch(ajaxurl, {
                    method: "POST",
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: "gtaw_clear_logs", module: "oauth" })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Logs cleared successfully.");
                        location.reload();
                    } else {
                        alert("Failed to clear logs.");
                    }
                }).catch(error => {
                    alert("Request failed. Please try again.");
                });
            }
        });
    });
    </script>
    <?php
}

// Main settings tab
function gtaw_oauth_settings_tab() {
    // Set the default callback URL.
    $default_oauth_callback = site_url('?gta_oauth=callback');
    $enabled      = get_option('gtaw_oauth_enabled', 1); // Default enabled.
    $client_id    = get_option('gtaw_client_id', '');
    $client_secret= get_option('gtaw_client_secret', '');
    $oauth_callback_url = get_option('gtaw_callback_url', $default_oauth_callback);
    
    // Generate the login link.
    $login_link = add_query_arg( array(
        'client_id'     => $client_id,
        'redirect_uri'  => urlencode( $oauth_callback_url ),
        'response_type' => 'code',
        'scope'         => ''
    ), 'https://ucp.gta.world/oauth/authorize' );
    ?>
    <form method="post" action="options.php">
        <?php 
            settings_fields('gtaw_oauth_settings_group'); 
            do_settings_sections('gtaw_oauth_settings_group'); 
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Activate OAuth Module</th>
                <td>
                    <input type="checkbox" name="gtaw_oauth_enabled" value="1" <?php checked($enabled, 1); ?> />
                    <p class="description">Check to activate GTA:W OAuth integration. Uncheck to disable all OAuth functionality.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">OAuth Client ID</th>
                <td>
                    <input type="text" name="gtaw_client_id" value="<?php echo esc_attr($client_id); ?>" size="50" />
                    <p class="description">Enter your OAuth Client ID provided in the <a href="https://ucp.gta.world/developers/oauth" target="_blank">GTA:W UCP Developers section</a>.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">OAuth Client Secret</th>
                <td>
                    <input type="text" name="gtaw_client_secret" value="<?php echo esc_attr($client_secret); ?>" size="50" />
                    <p class="description">Enter your OAuth Client Secret.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">OAuth Callback/Redirect URL</th>
                <td>
                    <input type="text" name="gtaw_callback_url" readonly value="<?php echo esc_attr($oauth_callback_url); ?>" size="50" />
                    <p class="description">This URL is auto-generated. Ensure it matches the one in your GTA:W UCP Developers settings.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">GTA:W Login Link</th>
                <td>
                    <input type="text" readonly value="<?php echo esc_url($login_link); ?>" size="50" style="width:100%;" />
                    <p class="description">Use this link directly or embed it with the shortcode <code>[gtaw_login]</code>.</p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <?php
}

// Logs tab
function gtaw_oauth_logs_tab() {
    $logs = gtaw_get_logs('oauth');
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

/* ========= LOAD OAUTH SUBMODULES ========= */

// Define the submodules path 
define('GTAW_OAUTH_PATH', plugin_dir_path(__FILE__) . 'oauth/');

// Load all OAuth submodules
function gtaw_load_oauth_submodules() {
    // Don't load oAuth module on login page for security reasons.
    if (in_array($GLOBALS['pagenow'], ['wp-login.php'])) {
        return;
    }
    // Load the submodules
    $submodules = [
        'core.php',
        'authentication.php',
        'account-management.php',
        'shortcodes.php',
        'guide.php',
        'character-switching.php',
        'character-switch-fix.php'
    ];
    
    foreach ($submodules as $submodule) {
        if (file_exists(GTAW_OAUTH_PATH . $submodule)) {
            require_once GTAW_OAUTH_PATH . $submodule;
        }
    }
}

// Only load OAuth functionality if the module is enabled
function gtaw_init_oauth_module() {
    // Check if the OAuth module is enabled
    if (get_option('gtaw_oauth_enabled', 1) == 1) {
        gtaw_load_oauth_submodules();
    }
}
add_action('plugins_loaded', 'gtaw_init_oauth_module', 11); // Priority 11 to load after main plugin