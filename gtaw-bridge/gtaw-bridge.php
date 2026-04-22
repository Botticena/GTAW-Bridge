<?php
/*
Plugin Name: GTA:W Bridge
Description: GTA:World Roleplay WordPress Bridge with oAuth and Fleeca Bank integration.
Version: 1.2.1
Author: Lena
Author URI: https://forum.gta.world/en/profile/56418-lena/
Plugin URI: https://github.com/Botticena/GTAW-Bridge/
*/

if (!defined('ABSPATH')) {
    exit;
}

define( 'GTAW_BRIDGE_VERSION', '1.2.1' );
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
	if ( 'toplevel_page_gtaw-bridge' === $hook ) {
		$inline = "(function(){function m(){var s=document.getElementById('gtaw-bridge-notice-slot'),r=document.getElementById('wpbody-content');if(!s||!r)return;var n=r.querySelectorAll('div.notice,div.error,p.notice,div.missing,p.updated,div.updated,p.update-nag,#message'),i,e;for(i=0;i<n.length;i++){e=n[i];if(!e||!e.parentNode)continue;if(s.contains(e))continue;if(e.id==='dismissed_old_spacer')continue;if(e.closest&&e.closest('#screen-meta, .gtaw-module-card'))continue;s.appendChild(e);}}m();setTimeout(m,0);setTimeout(m,200);})();";
		wp_add_inline_script( 'gtaw-admin-script', $inline, 'after' );
	}
}
add_action('admin_enqueue_scripts', 'gtaw_admin_enqueue_scripts');

add_filter(
	'admin_body_class',
	function ( $classes ) {
		if ( isset( $_GET['page'] ) && 'gtaw-bridge' === $_GET['page'] && is_string( $classes ) ) {
			return trim( $classes ) . ' gtaw-bridge-main-dashboard';
		}
		return $classes;
	}
);

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
require_once GTAW_BRIDGE_PLUGIN_DIR . 'modules/gtaw-woocommerce.php';

add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['gtaw_dismiss'], $_GET['_wpnonce'] ) || 'wc_block_cart_checkout' !== $_GET['gtaw_dismiss'] ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gtaw_dismiss_wc_block_notice' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_user_meta( get_current_user_id(), 'gtaw_bridge_dismissed_wc_block_pages_notice', '1' );
		$redir = wp_get_referer();
		$url = ( $redir && wp_validate_redirect( $redir, false ) ) ? remove_query_arg( '_wpnonce', remove_query_arg( 'gtaw_dismiss', $redir ) ) : admin_url( 'index.php' );
		wp_safe_redirect( $url );
		exit;
	}
);

add_action(
	'admin_notices',
	function () {
		if ( ! is_admin() || wp_doing_ajax() || ! is_user_logged_in() || ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( '1' === get_user_meta( get_current_user_id(), 'gtaw_bridge_dismissed_wc_block_pages_notice', true ) || ! apply_filters( 'gtaw_show_wc_block_pages_notice', true ) ) {
			return;
		}
		$has = function ( $id, $block ) {
			$id = (int) $id;
			if ( $id < 1 ) {
				return false;
			}
			if ( class_exists( '\WC_Blocks_Utils' ) && is_callable( array( '\WC_Blocks_Utils', 'has_block_in_page' ) ) ) {
				return (bool) \WC_Blocks_Utils::has_block_in_page( $id, $block );
			}
			$post = get_post( $id );
			if ( ! $post ) {
				return false;
			}
			if ( function_exists( 'has_block' ) && has_block( $block, $post ) ) {
				return true;
			}
			$c = (string) $post->post_content;
			$m = 'wp:' . $block;
			return $c !== '' && ( false !== strpos( $c, $m ) || false !== strpos( $c, '<!-- ' . $m ) );
		};

		$cart  = (int) wc_get_page_id( 'cart' );
		$check = (int) wc_get_page_id( 'checkout' );
		if ( $cart < 1 && $check < 1 ) {
			return;
		}
		$need_c = $cart > 0 && ! $has( $cart, 'woocommerce/cart' );
		$need_o = $check > 0 && ! $has( $check, 'woocommerce/checkout' );
		$sit    = ( $need_c && $need_o ) ? 'both' : ( $need_c ? 'cart' : ( $need_o ? 'checkout' : null ) );
		if ( null === $sit ) {
			return;
		}
		$lc      = ( $cart > 0 && current_user_can( 'edit_page', $cart ) ) ? get_edit_post_link( $cart, 'raw' ) : '';
		$lo      = ( $check > 0 && current_user_can( 'edit_page', $check ) ) ? get_edit_post_link( $check, 'raw' ) : '';
		$dismiss = wp_nonce_url( add_query_arg( 'gtaw_dismiss', 'wc_block_cart_checkout' ), 'gtaw_dismiss_wc_block_notice' );
		$intro   = 'both' === $sit
			? esc_html__( 'GTAW Bridge now supports block-based cart and checkout—replace the shortcodes on both pages with the blocks.', 'gtaw-bridge' )
			: ( 'cart' === $sit
				? esc_html__( 'GTAW Bridge now supports block-based cart and checkout—switch the Cart page to the Cart block.', 'gtaw-bridge' )
				: esc_html__( 'GTAW Bridge now supports block-based cart and checkout—switch the Checkout page to the Checkout block.', 'gtaw-bridge' ) );
		$parts   = array();
		if ( ( 'cart' === $sit || 'both' === $sit ) && $lc ) {
			$parts[] = '<a href="' . esc_url( $lc ) . '">' . esc_html__( 'Cart page', 'gtaw-bridge' ) . '</a>';
		}
		if ( ( 'checkout' === $sit || 'both' === $sit ) && $lo ) {
			$parts[] = '<a href="' . esc_url( $lo ) . '">' . esc_html__( 'Checkout page', 'gtaw-bridge' ) . '</a>';
		}
		echo '<div class="notice notice-info"><p>' . $intro . ' ';
		echo wp_kses( implode( ' | ', $parts ), array( 'a' => array( 'href' => array() ) ) );
		echo ' <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss for my account', 'gtaw-bridge' ) . '</a></p></div>';
	},
	20
);

function gtaw_bridge_oauth_off_shortcode_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }
    return '<p class="gtaw-oauth-off">' . esc_html__( 'GTA:W OAuth is disabled. Enable the OAuth module on the GTA:W Bridge dashboard to use login shortcodes.', 'gtaw-bridge' ) . '</p>';
}

function gtaw_bridge_register_oauth_off_shortcode_stubs() {
    if ( (int) get_option( 'gtaw_oauth_enabled', 1 ) === 1 ) {
        return;
    }

    $cb = 'gtaw_bridge_oauth_off_shortcode_html';
    add_shortcode( 'gtaw_login', $cb );
    add_shortcode( 'gtaw_login_button', $cb );
    add_shortcode( 'gtaw_login_logout', $cb );
    add_shortcode( 'gtaw_user_info', $cb );
    add_shortcode( 'gtaw_if_logged_in', $cb );
    add_shortcode( 'gtaw_if_not_logged_in', $cb );
    add_shortcode( 'gtaw_character_info', $cb );
    add_shortcode( 'gtaw_character_details', $cb );
}
add_action( 'init', 'gtaw_bridge_register_oauth_off_shortcode_stubs', 4 );

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
    if (isset($_POST['gtaw_module_update']) && isset($_POST['gtaw_module_nonce']) && wp_verify_nonce($_POST['gtaw_module_nonce'], 'gtaw_module_toggle') && current_user_can('manage_options')) {
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

    $gtaw_module_settings_updated = isset($_GET['updated']) && '1' === (string) $_GET['updated'];

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