<?php
/*
Plugin Name: GTA:W Bridge
Description: GTA:World Roleplay WordPress Bridge with oAuth and Fleeca Bank integration.
Version: 1.2.0
Author: Lena
Author URI: https://forum.gta.world/en/profile/56418-lena/
Plugin URI: https://github.com/Botticena/GTAW-Bridge/
*/

if (!defined('ABSPATH')) {
    exit;
}

define( 'GTAW_BRIDGE_VERSION', '1.2.0' );
define('GTAW_BRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GTAW_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));

function gtaw_check_requirements() {
    if (!is_admin()) {
        return;
    }
    if (get_option('gtaw_fleeca_enabled', 0) != 1) {
        return;
    }
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        add_action('admin_notices', 'gtaw_wc_admin_notice');
    }
}
add_action('admin_init', 'gtaw_check_requirements');

function gtaw_wc_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    echo '<div class="notice notice-warning is-dismissible">';
    echo '<p><strong>GTAW Bridge:</strong> The Fleeca Bank module requires <strong>WooCommerce</strong> to be installed and active. Other modules (OAuth, Discord) do not. Please <a href="' . esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')) . '">install WooCommerce</a> or activate it if you use Fleeca payments.</p>';
    echo '</div>';
}

function gtaw_enqueue_scripts() {
    $oauth_enabled = get_option('gtaw_oauth_enabled', 1) == 1;

    wp_enqueue_style('gtaw-style', GTAW_BRIDGE_PLUGIN_URL . 'assets/css/gtaw-style.css');

    if ($oauth_enabled) {
        wp_enqueue_script('gtaw-script', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-script.js', array('jquery'), GTAW_BRIDGE_VERSION, true);
        wp_localize_script('gtaw-script', 'gtaw_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('gtaw_nonce'),
        ));
    }
}
add_action('wp_enqueue_scripts', 'gtaw_enqueue_scripts');

function gtaw_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'gtaw-bridge') === false) {
        return;
    }

    wp_enqueue_style('gtaw-admin-style', GTAW_BRIDGE_PLUGIN_URL . 'assets/css/gtaw-admin.css', array(), GTAW_BRIDGE_VERSION);
    wp_enqueue_script('gtaw-admin-script', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-admin.js', array('jquery'), GTAW_BRIDGE_VERSION, true);
}
add_action('admin_enqueue_scripts', 'gtaw_admin_enqueue_scripts');

function gtaw_add_main_menu() {
    add_menu_page(
        'GTA:W Bridge',
        'GTA:W Bridge',
        'manage_options',
        'gtaw-bridge',
        'gtaw_main_page_callback',
        'dashicons-admin-site',
        2
    );
}
add_action('admin_menu', 'gtaw_add_main_menu');

require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-logging.php';
require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-utilities.php';
require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-updates.php';

function gtaw_load_modules() {
    if (get_option('gtaw_oauth_enabled', 1) == 1) {
        require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-oauth.php';
    }

    require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-discord.php';

    if (get_option('gtaw_fleeca_enabled', 0) == 1 && class_exists('WooCommerce')) {
        require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-fleeca.php';
    }
}
add_action('plugins_loaded', 'gtaw_load_modules', 10);

function gtaw_main_page_callback() {
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
        
        wp_redirect(admin_url('admin.php?page=gtaw-bridge&updated=1'));
        exit;
    }

    $updated = isset($_GET['updated']) && $_GET['updated'] == 1;

    $oauth_status = get_option('gtaw_oauth_enabled', 1);
    $discord_status = get_option('gtaw_discord_enabled', 0);
    $fleeca_status = get_option('gtaw_fleeca_enabled', 0);

    $modules_for_logs = array('oauth', 'discord', 'fleeca');
    $combined_logs = gtaw_get_combined_logs($modules_for_logs, 10, 0);

    if (empty($combined_logs) && !gtaw_logging_table_exists()) {
        $combined_logs = array();
        
        foreach ($modules_for_logs as $module) {
            $logs = get_option("gtaw_{$module}_logs", array());
            foreach ($logs as $log) {
                $log['module'] = $module;
                $combined_logs[] = $log;
            }
        }
        
        usort($combined_logs, function($a, $b) {
            $a_time = isset($a['date']) ? strtotime($a['date']) : 0;
            $b_time = isset($b['date']) ? strtotime($b['date']) : 0;
            return $b_time - $a_time;
        });
        
        $combined_logs = array_slice($combined_logs, 0, 10);
    }

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

    include GTAW_BRIDGE_PLUGIN_DIR . 'templates/admin-dashboard.php';
}

function gtaw_filter_module_submenus() {
    global $submenu;

    if (!isset($submenu['gtaw-bridge'])) {
        return;
    }

    $oauth_status = get_option('gtaw_oauth_enabled', 1);
    $discord_status = get_option('gtaw_discord_enabled', 0);
    $fleeca_status = get_option('gtaw_fleeca_enabled', 0);

    $items_to_remove = array();

    foreach ($submenu['gtaw-bridge'] as $key => $item) {
        if ($item[2] === 'gtaw-oauth' && !$oauth_status) {
            $items_to_remove[] = $key;
        }

        if ($item[2] === 'gtaw-discord' && !$discord_status) {
            $items_to_remove[] = $key;
        }

        if ($item[2] === 'gtaw-fleeca' && !$fleeca_status) {
            $items_to_remove[] = $key;
        }
    }

    foreach ($items_to_remove as $key) {
        unset($submenu['gtaw-bridge'][$key]);
    }
}
add_action('admin_menu', 'gtaw_filter_module_submenus', 999);

function gtaw_preserve_module_status() {
    if (!is_admin()) {
        return;
    }

    if (isset($_POST['option_page'])) {
        $option_page = $_POST['option_page'];

        if ($option_page === 'gtaw_oauth_settings_group') {
            $_POST['gtaw_oauth_enabled'] = get_option('gtaw_oauth_enabled', 1);
        }

        if ($option_page === 'gtaw_discord_settings_group') {
            $_POST['gtaw_discord_enabled'] = get_option('gtaw_discord_enabled', 0);
        }

        if ($option_page === 'gtaw_fleeca_settings_group') {
            $_POST['gtaw_fleeca_enabled'] = get_option('gtaw_fleeca_enabled', 0);
        }
    }
}
add_action('admin_init', 'gtaw_preserve_module_status', 5);

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

function gtaw_prevent_disabled_module_access() {
    global $pagenow;

    if (!is_admin() || $pagenow !== 'admin.php') {
        return;
    }

    if (isset($_GET['page'])) {
        $page = $_GET['page'];

        if ($page === 'gtaw-oauth' && !gtaw_is_module_enabled('oauth')) {
            wp_redirect(admin_url('admin.php?page=gtaw-bridge'));
            exit;
        }

        if ($page === 'gtaw-discord' && !gtaw_is_module_enabled('discord')) {
            wp_redirect(admin_url('admin.php?page=gtaw-bridge'));
            exit;
        }

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

function gtaw_bridge_activation() {
    if (function_exists('gtaw_create_logs_table')) {
        gtaw_create_logs_table();
    } else {
        update_option('gtaw_logs_create_on_init', '1');
    }

    if (get_option('gtaw_discord_enabled', 0) == 1) {
        add_rewrite_endpoint('discord', EP_ROOT | EP_PAGES);
    }

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'gtaw_bridge_activation');

function gtaw_bridge_deactivation() {
    wp_clear_scheduled_hook('gtaw_daily_log_cleanup');
    wp_clear_scheduled_hook('gtaw_discord_role_sync_event');

    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'gtaw_bridge_deactivation');

add_action('init', function() {
    if (get_option('gtaw_logs_create_on_init', '0') === '1') {
        if (function_exists('gtaw_create_logs_table')) {
            gtaw_create_logs_table();
            delete_option('gtaw_logs_create_on_init');
        }
    }
}, 5);