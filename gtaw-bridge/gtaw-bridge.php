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
    ?>
    <div class="wrap">
        <h1>GTA:W Bridge</h1>
        <p>Welcome to GTA:W Bridge. Use the submenus to access the various functionalities.</p>
    </div>
    <?php
}

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
