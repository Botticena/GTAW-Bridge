<?php
defined('ABSPATH') or exit;

/**
 * Fleeca module — in-admin guide (same layout as OAuth / Discord).
 */

/**
 * Guide tab content
 */
function gtaw_fleeca_guide_tab() {
    ?>
    <div class="gtaw-fleeca-guide">
        <h2><?php esc_html_e( 'Fleeca Bank Module Guide', 'gtaw-bridge' ); ?></h2>

        <div class="nav-wrapper" style="margin-bottom: 20px;">
            <div class="nav-tabs" style="display: flex; gap: 10px; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
                <a href="#overview" class="nav-link active" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px; font-weight: bold;"><?php esc_html_e( 'Overview', 'gtaw-bridge' ); ?></a>
                <a href="#setup" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;"><?php esc_html_e( 'Setup', 'gtaw-bridge' ); ?></a>
                <a href="#testing" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;"><?php esc_html_e( 'Testing', 'gtaw-bridge' ); ?></a>
                <a href="#troubleshooting" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;"><?php esc_html_e( 'Troubleshooting', 'gtaw-bridge' ); ?></a>
            </div>
        </div>

        <div class="content-wrapper">
            <div id="overview" class="content-section">
                <?php echo gtaw_fleeca_guide_overview_content(); ?>
            </div>
            <div id="setup" class="content-section" style="display: none;">
                <?php echo gtaw_fleeca_guide_setup_content(); ?>
            </div>
            <div id="testing" class="content-section" style="display: none;">
                <?php echo gtaw_fleeca_guide_testing_content(); ?>
            </div>
            <div id="troubleshooting" class="content-section" style="display: none;">
                <?php echo gtaw_fleeca_guide_troubleshooting_content(); ?>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.nav-link').on('click', function(e) {
            e.preventDefault();
            $('.nav-link').removeClass('active').css('font-weight', 'normal');
            $(this).addClass('active').css('font-weight', 'bold');
            const target = $(this).attr('href');
            $('.content-section').hide();
            $(target).show();
        });
    });
    </script>
    <?php
}

/**
 * Overview
 *
 * @return string
 */
function gtaw_fleeca_guide_overview_content() {
    ob_start();
    ?>
    <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
        <h2 style="margin: 0;"><?php esc_html_e( 'What this module does', 'gtaw-bridge' ); ?></h2>
    </div>
    <div class="section-content" style="margin-bottom: 30px;">
        <p><?php esc_html_e( 'It adds “Fleeca Bank” as a payment method in WooCommerce. Customers pay through the Fleeca flow; your shop gets the order status from that payment.', 'gtaw-bridge' ); ?></p>
        <div class="feature-box" style="background: #f0f7ff; padding: 15px; border-radius: 5px; margin-top: 20px;">
            <h3><?php esc_html_e( 'You will need', 'gtaw-bridge' ); ?></h3>
            <ul>
                <li><?php esc_html_e( 'WooCommerce installed and your store currency set to USD (GTA$).', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'A merchant and API key from the', 'gtaw-bridge' ); ?> <a href="https://banking.gta.world/merchant-center" target="_blank" rel="noopener"><?php esc_html_e( 'Fleeca Merchant Center', 'gtaw-bridge' ); ?></a>.</li>
                <li><?php esc_html_e( 'The “Callback” and “Return” links from this plugin’s Settings tab — copy them into the Merchant Center when it asks for those URLs.', 'gtaw-bridge' ); ?></li>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Setup steps
 *
 * @return string
 */
function gtaw_fleeca_guide_setup_content() {
    ob_start();
    ?>
    <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
        <h2 style="margin: 0;"><?php esc_html_e( 'Setting up Fleeca', 'gtaw-bridge' ); ?></h2>
    </div>
    <div class="section-content">
        <div class="setup-step">
            <h3><?php esc_html_e( 'Step 1: Fleeca Merchant Center', 'gtaw-bridge' ); ?></h3>
            <ol>
                <li><?php esc_html_e( 'Open the', 'gtaw-bridge' ); ?> <a href="https://banking.gta.world/merchant-center" target="_blank" rel="noopener"><?php esc_html_e( 'Fleeca Merchant Center', 'gtaw-bridge' ); ?></a> <?php esc_html_e( 'and create or open your merchant.', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'Paste the two links from GTA:W Bridge → Fleeca → Settings (Callback and Return) into the fields the Merchant Center asks for.', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'Create an API key and copy it — you will paste it into WordPress next.', 'gtaw-bridge' ); ?></li>
            </ol>
        </div>
        <div class="setup-step">
            <h3><?php esc_html_e( 'Step 2: This plugin', 'gtaw-bridge' ); ?></h3>
            <ol>
                <li><?php esc_html_e( 'Go to GTA:W Bridge → Fleeca → Settings.', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'Turn on Fleeca, paste your API key, and choose the name shoppers see at checkout (e.g. “Fleeca Bank”).', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'Save. If you only want tests, turn on “Sandbox” until you go live.', 'gtaw-bridge' ); ?></li>
            </ol>
        </div>
        <div class="setup-step">
            <h3><?php esc_html_e( 'Step 3: WooCommerce', 'gtaw-bridge' ); ?></h3>
            <ol>
                <li><?php esc_html_e( 'Go to WooCommerce → Settings → Payments.', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'Find Fleeca (or your custom name) and enable it.', 'gtaw-bridge' ); ?></li>
            </ol>
        </div>
        <div class="important-note" style="background: #fff8e5; padding: 15px; border-left: 4px solid #ffb900; margin-top: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'If links stop working', 'gtaw-bridge' ); ?></h3>
            <p><?php esc_html_e( 'After changing site URL or permalinks, visit the Fleeca settings page and use your host’s usual “flush permalinks” step (Saving Permalinks in WordPress often fixes return/callback 404s).', 'gtaw-bridge' ); ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Testing
 *
 * @return string
 */
function gtaw_fleeca_guide_testing_content() {
    ob_start();
    ?>
    <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
        <h2 style="margin: 0;"><?php esc_html_e( 'Testing', 'gtaw-bridge' ); ?></h2>
    </div>
    <div class="section-content">
        <p><?php esc_html_e( 'Turn on “Sandbox” in Fleeca settings, place a cheap test order, pay on the Fleeca screen, and confirm the order moves out of “Pending” in WooCommerce. Check the Logs tab if something looks wrong.', 'gtaw-bridge' ); ?></p>
        <ul>
            <li><?php esc_html_e( 'Try a small amount first.', 'gtaw-bridge' ); ?></li>
            <li><?php esc_html_e( 'Turn off Sandbox when you want real payments.', 'gtaw-bridge' ); ?></li>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Troubleshooting
 *
 * @return string
 */
function gtaw_fleeca_guide_troubleshooting_content() {
    ob_start();
    ?>
    <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
        <h2 style="margin: 0;"><?php esc_html_e( 'Troubleshooting', 'gtaw-bridge' ); ?></h2>
    </div>
    <div class="section-content">
        <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h4><?php esc_html_e( 'Fleeca does not show at checkout', 'gtaw-bridge' ); ?></h4>
            <ul>
                <li><?php esc_html_e( 'Confirm the Fleeca module is enabled in GTA:W Bridge and the API key is saved.', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'In WooCommerce → Payments, make sure Fleeca is turned on.', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'Currency must be USD (GTA$).', 'gtaw-bridge' ); ?></li>
            </ul>
        </div>
        <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h4><?php esc_html_e( 'Order stays “Pending” after pay', 'gtaw-bridge' ); ?></h4>
            <ul>
                <li><?php esc_html_e( 'Check that the Callback URL in the Merchant Center matches the one on the Fleeca Settings page.', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'Open the Fleeca Logs tab and enable “Verbose logs” temporarily to see errors.', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'On the order in wp-admin, use the Fleeca actions (if shown) to refresh or check the payment.', 'gtaw-bridge' ); ?></li>
            </ul>
        </div>
        <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h4><?php esc_html_e( '404 after paying', 'gtaw-bridge' ); ?></h4>
            <ul>
                <li><?php esc_html_e( 'In WordPress, go to Settings → Permalinks and click Save.', 'gtaw-bridge' ); ?></li>
                <li><?php esc_html_e( 'Confirm the Return URL in the Merchant Center matches the plugin.', 'gtaw-bridge' ); ?></li>
            </ul>
        </div>
        <h3><?php esc_html_e( 'More help', 'gtaw-bridge' ); ?></h3>
        <p>
            <a href="https://github.com/Botticena/GTAW-Bridge/issues" target="_blank" rel="noopener"><?php esc_html_e( 'GitHub Issues', 'gtaw-bridge' ); ?></a>
        </p>
    </div>
    <?php
    return ob_get_clean();
}
