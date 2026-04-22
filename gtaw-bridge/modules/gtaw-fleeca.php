<?php
defined('ABSPATH') or exit;

// Fleeca merchant v2 + WC gateway. Loads heavy stuff only when it has to.

define( 'GTAW_FLEECA_VERSION', '3.0' );
define( 'GTAW_FLEECA_PATH', plugin_dir_path( __FILE__ ) . 'fleeca/' );

function gtaw_fleeca_get_logo_url() {
	if ( ! defined( 'GTAW_BRIDGE_PLUGIN_URL' ) ) {
		return '';
	}
	$default = GTAW_BRIDGE_PLUGIN_URL . 'assets/img/fleeca.webp';
	return (string) apply_filters( 'woocommerce_gtaw_fleeca_icon', $default );
}

function gtaw_fleeca_register_settings() {
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_settings', [
        'sanitize_callback' => 'gtaw_fleeca_sanitize_settings',
        'default' => gtaw_fleeca_default_settings()
    ]);

    // @deprecated 2.0 — old keys still registered for upgrades
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_enabled');
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_api_key');
    register_setting('gtaw_fleeca_settings_group', 'gtaw_fleeca_gateway_name');
}
add_action('admin_init', 'gtaw_fleeca_register_settings');

function gtaw_fleeca_sanitize_settings($input) {
    if (!is_array($input)) {
        return gtaw_fleeca_default_settings();
    }

    $defaults = gtaw_fleeca_default_settings();
    $sanitized = [];

    $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : $defaults['enabled'];

    $sanitized['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : $defaults['api_key'];
    $sanitized['gateway_name'] = isset($input['gateway_name']) ? sanitize_text_field($input['gateway_name']) : $defaults['gateway_name'];

    $sanitized['sandbox_mode'] = isset( $input['sandbox_mode'] ) && $input['sandbox_mode'] ? true : false;
    $sanitized['debug_mode']   = isset( $input['debug_mode'] ) && $input['debug_mode'] ? true : false;

    // @deprecated 2.0
    update_option('gtaw_fleeca_enabled', $sanitized['enabled'] ? 1 : 0);
    update_option('gtaw_fleeca_api_key', $sanitized['api_key']);
    update_option('gtaw_fleeca_gateway_name', $sanitized['gateway_name']);
    
    return $sanitized;
}

function gtaw_fleeca_default_settings() {
    return [
        'enabled'         => false,
        'api_key'         => '',
        'gateway_name'    => 'Fleeca Bank',
        'sandbox_mode'    => false,
        'debug_mode'      => false,
    ];
}

function gtaw_fleeca_get_setting($key, $default = false) {
    $settings = get_option('gtaw_fleeca_settings', gtaw_fleeca_default_settings());

    if ( isset( $settings[ $key ] ) ) {
        return $settings[ $key ];
    }

    // @deprecated 2.0
    switch ($key) {
        case 'enabled':
            return get_option('gtaw_fleeca_enabled', 0) == 1;
        case 'api_key':
            return get_option('gtaw_fleeca_api_key', '');
        case 'gateway_name':
            return get_option('gtaw_fleeca_gateway_name', 'Fleeca Bank');
        case 'sandbox_mode':
        case 'debug_mode':
            return false;
    }
    
    return $default;
}

function gtaw_fleeca_migrate_settings() {
    if (get_option('gtaw_fleeca_settings_migrated', false)) {
        return;
    }
    gtaw_perf_start('fleeca_settings_migration');

    $settings = [
        'enabled'      => get_option( 'gtaw_fleeca_enabled', 0 ) == 1,
        'api_key'      => get_option( 'gtaw_fleeca_api_key', '' ),
        'gateway_name' => get_option( 'gtaw_fleeca_gateway_name', 'Fleeca Bank' ),
        'sandbox_mode' => false,
        'debug_mode'   => false,
    ];

    update_option('gtaw_fleeca_settings', $settings);

    update_option('gtaw_fleeca_settings_migrated', true);
    gtaw_perf_end('fleeca_settings_migration', true);

    gtaw_add_log('fleeca', 'Migration', 'Migrated individual settings to consolidated format', 'success');
}

function gtaw_fleeca_prune_legacy_option_keys() {
    if ( get_option( 'gtaw_fleeca_pruned_legacy_v1' ) ) {
        return;
    }
    $s = get_option( 'gtaw_fleeca_settings' );
    if ( is_array( $s ) ) {
        unset( $s['api_version'], $s['callback_url'] );
        update_option( 'gtaw_fleeca_settings', $s );
    }
    update_option( 'gtaw_fleeca_pruned_legacy_v1', '1' );
}


function gtaw_add_fleeca_settings_submenu() {
    add_submenu_page(
        'gtaw-bridge',
        'Fleeca Bank',
        'Fleeca',
        'manage_options',
        'gtaw-fleeca',
        'gtaw_fleeca_settings_page_callback'
    );
}
add_action('admin_menu', 'gtaw_add_fleeca_settings_submenu');

/**
 * Callback for the Fleeca Settings page
 */
function gtaw_fleeca_settings_page_callback() {
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';

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

    $tabs = apply_filters('gtaw_fleeca_settings_tabs', $tabs);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Fleeca Bank', 'gtaw-bridge' ); ?></h1>
        <?php echo gtaw_generate_tabs_navigation('gtaw-fleeca', $tabs, $active_tab); ?>
        
        <div class="tab-content">
            <?php
            if (isset($tabs[$active_tab]) && is_callable($tabs[$active_tab]['callback'])) {
                call_user_func($tabs[$active_tab]['callback']);
            } else {
                gtaw_fleeca_settings_tab();
            }
            ?>
        </div>
    </div>
    <?php
}

function gtaw_fleeca_settings_tab() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        echo gtaw_admin_notice(
            '<strong>' . esc_html__( 'WooCommerce is required', 'gtaw-bridge' ) . '</strong> ' . esc_html__( 'for the Fleeca module. Please install and activate WooCommerce first.', 'gtaw-bridge' ),
            'error'
        );
        return;
    }

    $settings = get_option( 'gtaw_fleeca_settings', gtaw_fleeca_default_settings() );

    $webhook_url = function_exists( 'gtaw_fleeca_get_webhook_url' ) ? gtaw_fleeca_get_webhook_url() : home_url( '/fleeca/callback' );
    $return_url  = function_exists( 'gtaw_fleeca_get_return_url' ) ? gtaw_fleeca_get_return_url() : home_url( '/fleeca/return/' );

    $fields = [
        [
            'type'        => 'checkbox',
            'name'        => 'gtaw_fleeca_settings[enabled]',
            'label'       => __( 'Enable Fleeca for WooCommerce', 'gtaw-bridge' ),
            'default'     => $settings['enabled'],
            'description' => __( 'Exposes the Fleeca payment method at checkout.', 'gtaw-bridge' ),
        ],
        [
            'type'        => 'text',
            'name'        => 'gtaw_fleeca_settings[api_key]',
            'label'       => __( 'API key', 'gtaw-bridge' ),
            'default'     => $settings['api_key'],
            'size'        => 50,
            'description' => __( 'Create a merchant and copy your key from the', 'gtaw-bridge' ) . ' ' .
                '<a href="https://banking.gta.world/merchant-center" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Fleeca Merchant Center', 'gtaw-bridge' ) . '</a>.',
        ],
        [
            'type'        => 'text',
            'name'        => 'gtaw_fleeca_settings[gateway_name]',
            'label'       => __( 'Checkout label', 'gtaw-bridge' ),
            'default'     => $settings['gateway_name'],
            'size'        => 50,
            'description' => __( 'Name shown in the list of payment methods.', 'gtaw-bridge' ),
        ],
        [
            'type'        => 'checkbox',
            'name'        => 'gtaw_fleeca_settings[sandbox_mode]',
            'label'       => __( 'Sandbox / test mode', 'gtaw-bridge' ),
            'default'     => $settings['sandbox_mode'],
            'description' => __( 'Use for practice payments only. Turn off when you want real charges.', 'gtaw-bridge' ),
        ],
        [
            'type'        => 'checkbox',
            'name'        => 'gtaw_fleeca_settings[debug_mode]',
            'label'       => __( 'Verbose logs', 'gtaw-bridge' ),
            'default'     => $settings['debug_mode'],
            'description' => __( 'If something fails, the Logs tab will show more detail.', 'gtaw-bridge' ),
        ],
    ];

    echo gtaw_generate_settings_form( 'gtaw_fleeca_settings_group', $fields, __( 'Save settings', 'gtaw-bridge' ) );

    if ( current_user_can( 'manage_options' ) ) {
        $flush_url = wp_nonce_url(
            add_query_arg( array( 'fleeca_flush_rules' => 'yes' ), admin_url( 'admin.php?page=gtaw-fleeca' ) ),
            'fleeca_flush_rules'
        );
        echo '<div class="gtaw-fleeca-flush-rewrite" style="max-width: 50rem; margin: 0.25em 0 1.5em;">';
        echo '<a href="' . esc_url( $flush_url ) . '" class="button button-secondary">' . esc_html__( 'Flush rewrite rules', 'gtaw-bridge' ) . '</a>';
        echo '<p class="description" style="margin: 0.5em 0 0;">' . esc_html__( 'Use this if Fleeca return or callback URLs stop working (for example after changing permalinks).', 'gtaw-bridge' ) . '</p>';
        echo '</div>';
    }

    echo '<div class="card" style="max-width: 50rem; margin-top: 0;">';
    echo '<h2 class="title" style="margin-top:0;">' . esc_html__( 'Links for the Merchant Center', 'gtaw-bridge' ) . '</h2>';
    echo '<p class="description" style="margin-top:0;">' . esc_html__( 'Go to the Fleeca Merchant Center and paste these.', 'gtaw-bridge' ) . '</p>';
    echo '<table class="form-table" role="presentation" style="margin:0;">';
    echo '<tr><th scope="row" style="width:12rem;vertical-align:top;">' . esc_html__( 'Callback URI', 'gtaw-bridge' ) . '</th><td><code style="word-break:break-all;user-select:all;">' . esc_html( $webhook_url ) . '</code></td></tr>';
    echo '<tr><th scope="row" style="vertical-align:top;">' . esc_html__( 'Redirect URI', 'gtaw-bridge' ) . '</th><td><code style="word-break:break-all;user-select:all;">' . esc_html( $return_url ) . '</code></td></tr>';
    echo '</table>';
    echo '</div>';

    echo apply_filters( 'gtaw_fleeca_after_settings', '' );
}

function gtaw_fleeca_logs_tab() {
    $page = isset($_GET['logs_page']) ? max(1, intval($_GET['logs_page'])) : 1;

    $logs_per_page = isset($_GET['logs_per_page']) ? absint($_GET['logs_per_page']) : gtaw_get_logs_per_page();

    echo gtaw_display_module_logs('fleeca', $logs_per_page, $page);
}

function gtaw_init_fleeca_module() {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    gtaw_perf_start('fleeca_module_load');

    if (!class_exists('WooCommerce')) {
        gtaw_perf_end('fleeca_module_load');
        return;
    }

    gtaw_fleeca_migrate_settings();
    gtaw_fleeca_prune_legacy_option_keys();

    if (!gtaw_fleeca_get_setting('enabled', false)) {
        gtaw_perf_end('fleeca_module_load');
        return;
    }

    if (file_exists(GTAW_FLEECA_PATH . 'core.php')) {
        require_once GTAW_FLEECA_PATH . 'core.php';
    }

    if (file_exists(GTAW_FLEECA_PATH . 'callback-handler.php')) {
        require_once GTAW_FLEECA_PATH . 'callback-handler.php';
    }

    // gateway has to load or WC never registers the method
    if (file_exists(GTAW_FLEECA_PATH . 'gateway.php')) {
        require_once GTAW_FLEECA_PATH . 'gateway.php';
    }

    if (file_exists(GTAW_FLEECA_PATH . 'blocks-payment-method.php')) {
        require_once GTAW_FLEECA_PATH . 'blocks-payment-method.php';
    }

    if (is_admin() && file_exists(GTAW_FLEECA_PATH . 'guide.php')) {
        require_once GTAW_FLEECA_PATH . 'guide.php';
    }

    $initialized = true;
    gtaw_perf_end('fleeca_module_load', true);
}
add_action('plugins_loaded', 'gtaw_init_fleeca_module', 15);

/**
 * Enqueue optional Fleeca admin assets when present (avoids 404s if no custom CSS/JS is shipped).
 */
function gtaw_fleeca_admin_scripts($hook) {
    if (!gtaw_is_plugin_page($hook, 'gtaw-fleeca')) {
        return;
    }
    $dir = defined('GTAW_BRIDGE_PLUGIN_DIR') ? GTAW_BRIDGE_PLUGIN_DIR : '';
    $css = $dir . 'assets/css/gtaw-fleeca-admin.css';
    $js  = $dir . 'assets/js/gtaw-fleeca-admin.js';
    if ($dir && is_readable($css)) {
        wp_enqueue_style('gtaw-fleeca-admin-style', GTAW_BRIDGE_PLUGIN_URL . 'assets/css/gtaw-fleeca-admin.css', [], GTAW_FLEECA_VERSION);
    }
    if ($dir && is_readable($js)) {
        wp_enqueue_script('gtaw-fleeca-admin-script', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-fleeca-admin.js', ['jquery'], GTAW_FLEECA_VERSION, true);
    }
}
add_action('admin_enqueue_scripts', 'gtaw_fleeca_admin_scripts');

function gtaw_fleeca_ajax_handlers() {
    add_action('wp_ajax_gtaw_fleeca_flush_rules', function() {
        if (!gtaw_ajax_security_check('fleeca', 'nonce', 'gtaw_fleeca_flush_rules', 'manage_options', 'flush rules')) {
            return;
        }
        
        flush_rewrite_rules();
        wp_send_json_success('Rewrite rules have been flushed successfully.');
    });
}
add_action('admin_init', 'gtaw_fleeca_ajax_handlers');