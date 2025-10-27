<?php
defined('ABSPATH') or exit;

/* ========= FLEECA MODULE GUIDE ========= */
/*
 * This file provides a comprehensive guide to the Fleeca module's features
 * - Introduction and overview
 * - Setup instructions
 * - Troubleshooting tips
 * 
 * @version 2.0 Enhanced with improved navigation and content organization
 */

/**
 * Register the guide tab
 */
function gtaw_fleeca_guide_tab() {
    // Start performance tracking
    gtaw_perf_start('fleeca_guide_render');
    
    // Use section header utility for consistent styling
    echo gtaw_section_header(
        'Fleeca Bank Module Guide',
        'This guide will help you set up and configure the Fleeca Bank payment gateway for your WooCommerce store.'
    );
    ?>
    <div class="gtaw-fleeca-guide">
        <div class="nav-wrapper" style="margin-bottom: 20px;">
            <div class="nav-tabs" style="display: flex; gap: 10px; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
                <a href="#overview" class="nav-link active" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px; font-weight: bold;">Overview</a>
                <a href="#setup" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Setup</a>
                <a href="#testing" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Testing</a>
                <a href="#troubleshooting" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Troubleshooting</a>
            </div>
        </div>
        
        <div class="content-wrapper">
            <!-- Overview Section -->
            <div id="overview" class="content-section">
                <?php echo gtaw_fleeca_guide_overview_content(); ?>
            </div>
            
            <!-- Setup Section -->
            <div id="setup" class="content-section" style="display: none;">
                <?php echo gtaw_fleeca_guide_setup_content(); ?>
            </div>
            
            <!-- Testing Section -->
            <div id="testing" class="content-section" style="display: none;">
                <?php echo gtaw_fleeca_guide_testing_content(); ?>
            </div>
            
            <!-- Troubleshooting Section -->
            <div id="troubleshooting" class="content-section" style="display: none;">
                <?php echo gtaw_fleeca_guide_troubleshooting_content(); ?>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Tab navigation
        $('.nav-link').on('click', function(e) {
            e.preventDefault();
            
            // Update active tab
            $('.nav-link').removeClass('active').css('font-weight', 'normal');
            $(this).addClass('active').css('font-weight', 'bold');
            
            // Show corresponding content section
            const target = $(this).attr('href');
            $('.content-section').hide();
            $(target).show();
        });
    });
    </script>
    <?php
    
    // End performance tracking
    gtaw_perf_end('fleeca_guide_render');
}

/**
 * Generate overview section content
 *
 * @return string HTML content
 */
function gtaw_fleeca_guide_overview_content() {
    ob_start();
    ?>
    <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
        <h2 style="margin: 0;">Fleeca Module Overview</h2>
    </div>
    
    <div class="section-content" style="margin-bottom: 30px;">
        <p>The GTAW Bridge Fleeca Module provides integration between your WordPress website's WooCommerce store and GTA World's Fleeca Bank payment system, allowing customers to pay using their in-character bank accounts.</p>
        
        <div class="feature-box" style="background: #f0f7ff; padding: 15px; border-radius: 5px; margin-top: 20px;">
            <h3>Key Features</h3>
            <ul>
                <li><strong>WooCommerce Integration</strong> - Adds Fleeca Bank as a payment method in your store</li>
                <li><strong>Secure Payments</strong> - All payments are processed on the official Fleeca Bank server</li>
                <li><strong>Automatic Order Updates</strong> - Order statuses are updated automatically after payment</li>
                <li><strong>Transaction Logging</strong> - Detailed logs of all payment activity</li>
                <li><strong>Sandbox Mode</strong> - Test the integration without real money changing hands</li>
                <li><strong>Debug Tools</strong> - Troubleshooting utilities for developers</li>
            </ul>
        </div>
        
        <div class="prerequisites" style="margin-top: 20px;">
            <h3>Prerequisites</h3>
            <p>Before setting up the Fleeca module, you'll need:</p>
            <ul>
                <li>A WordPress website with WooCommerce installed and activated</li>
                <li>A Fleeca Bank API key from the <a href="https://ucp.gta.world/developers" target="_blank">GTA:W UCP Developers section</a></li>
                <li>Administrator access to your WordPress site</li>
                <li>Store pricing set up in GTA$ (USD in WooCommerce)</li>
            </ul>
        </div>
        
        <div class="important-note" style="background: #fff8e5; padding: 15px; border-left: 4px solid #ffb900; margin-top: 20px;">
            <h3 style="margin-top: 0;">Important Notes</h3>
            <ul>
                <li>Fleeca Bank integration only works with stores that price items in GTA$ (USD in WooCommerce)</li>
                <li>The gateway requires proper configuration of callback URLs and rewrite rules</li>
                <li>API keys are specific to each website and must be requested through the GTA:W UCP</li>
                <li>All transactions must comply with GTA World's economic regulations</li>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Generate setup section content
 *
 * @return string HTML content
 */
function gtaw_fleeca_guide_setup_content() {
    ob_start();
    ?>
    <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
        <h2 style="margin: 0;">Setting Up the Fleeca Module</h2>
    </div>
    
    <div class="section-content">
        <div class="setup-step">
            <h3>Step 1: Get a Fleeca Bank API Key</h3>
            <ol>
                <li>Go to the <a href="https://ucp.gta.world/developers" target="_blank">GTA:W UCP Developers section</a></li>
                <li>Request a Fleeca Bank API key for your website</li>
                <li>You'll need to provide:
                    <ul>
                        <li>Your website URL</li>
                        <li>A callback URL (see below)</li>
                        <li>Business purpose description</li>
                    </ul>
                </li>
                <li>For the callback URL, use: <code><?php echo esc_html(site_url('gateway?token=')); ?></code></li>
                <li>Once approved, you'll receive an API key</li>
            </ol>
        </div>
        
        <div class="setup-step">
            <h3>Step 2: Configure the Fleeca Module</h3>
            <ol>
                <li>Go to the "Settings" tab in the Fleeca Module</li>
                <li>Check "Enable Fleeca Module" to activate the integration</li>
                <li>Enter your Fleeca Bank API key</li>
                <li>Set your preferred gateway display name (e.g., "Fleeca Bank")</li>
                <li>Verify the callback URL matches what you provided when requesting your API key</li>
                <li>Optional: Enable sandbox mode for testing</li>
                <li>Optional: Enable debug mode for detailed logging</li>
                <li>Save your settings</li>
            </ol>
        </div>
        
        <div class="setup-step">
            <h3>Step 3: Enable Fleeca Bank in WooCommerce</h3>
            <ol>
                <li>Go to WooCommerce → Settings → Payments</li>
                <li>You should see "Fleeca Bank" (or your custom name) in the payment methods list</li>
                <li>Ensure it's enabled by toggling the switch to ON</li>
                <li>You can click "Manage" to customize the display title and description if needed</li>
                <li>Save changes</li>
            </ol>
        </div>
        
        <div class="setup-step">
            <h3>Step 4: Verify URL Configuration</h3>
            <ol>
                <li>Go back to the Fleeca Module settings page</li>
                <li>Click the "Flush Rewrite Rules" button to ensure your callback URL is properly registered</li>
                <li>Test the URL configuration with the testing process described in the next section</li>
            </ol>
        </div>
        
        <div class="important-note" style="background: #fff8e5; padding: 15px; border-left: 4px solid #ffb900; margin-top: 20px;">
            <h3 style="margin-top: 0;">Important URL Configuration Notes</h3>
            <p>The callback URL is crucial for the payment process to work correctly. Make sure:</p>
            <ul>
                <li>The URL must be accessible publicly (no authentication required)</li>
                <li>The URL must end with <code>?token=</code> or <code>&token=</code></li>
                <li>The URL must match exactly between your module settings and the GTA:W UCP</li>
                <li>Your site's permalink structure must support the gateway endpoint</li>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Generate testing section content
 *
 * @return string HTML content
 */
function gtaw_fleeca_guide_testing_content() {
    ob_start();
    ?>
    <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
        <h2 style="margin: 0;">Testing Your Integration</h2>
    </div>
    
    <div class="section-content">
        <h3>Sandbox Mode</h3>
        <p>When you first receive your API key, it will be in "sandbox" mode. This allows you to test the integration without real money changing hands. Here's what you need to know about sandbox mode:</p>
        
        <ul>
            <li>Sandbox mode is controlled by GTA:W, not by your settings</li>
            <li>You will see "sandbox: true" in the token validation response</li>
            <li>Orders will still be marked as complete, but no real money is transferred</li>
            <li>After testing is complete, GTA:W will switch your API key to production mode</li>
            <li>The module's local "Sandbox Mode" setting only affects local behavior, such as displaying test mode notices</li>
        </ul>
        
        <h3>Test Procedure</h3>
        <p>To properly test your Fleeca Bank integration:</p>
        
        <ol>
            <li>Create a test product with a low price (e.g., $1)</li>
            <li>Add it to cart and proceed to checkout</li>
            <li>Select Fleeca Bank as the payment method</li>
            <li>Complete the checkout process</li>
            <li>On the Fleeca Bank page, complete the payment</li>
            <li>You should be redirected back to your thank you page</li>
            <li>Check the order status and payment details in WooCommerce</li>
            <li>Verify the transaction logs in the Fleeca Module logs tab</li>
        </ol>
        
        <div class="best-practices" style="background: #f0f7ff; padding: 15px; border-radius: 5px; margin-top: 20px;">
            <h3>Best Practices for Testing</h3>
            <ul>
                <li>Test with various order amounts</li>
                <li>Test the checkout flow multiple times</li>
                <li>Test what happens if a customer cancels payment</li>
                <li>Check how order statuses update after payment</li>
                <li>Verify the details in your order notes</li>
                <li>Check the logs to ensure everything is being tracked correctly</li>
                <li>Test both logged-in user and guest checkout scenarios</li>
                <li>Verify that the order details show Fleeca information correctly</li>
            </ul>
        </div>
        
        <div class="debug-mode" style="background: #f3f7ff; padding: 15px; border-left: 4px solid #4a6ee0; margin-top: 20px;">
            <h3 style="margin-top: 0;">Using Debug Mode</h3>
            <p>The Fleeca module includes a debug mode for troubleshooting:</p>
            <ol>
                <li>Enable "Debug Mode" in the Fleeca Module settings</li>
                <li>This will add verbose logging of API interactions</li>
                <li>Process a test payment and check the logs tab</li>
                <li>You'll see detailed information about token validation, payment processing, and any errors</li>
                <li>Administrators can also access debugging tools from the WooCommerce order actions menu</li>
            </ol>
            <p>Note: Disable debug mode in production to avoid filling logs with unnecessary information.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Generate troubleshooting section content
 *
 * @return string HTML content
 */
function gtaw_fleeca_guide_troubleshooting_content() {
    ob_start();
    ?>
    <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
        <h2 style="margin: 0;">Troubleshooting</h2>
    </div>
    
    <div class="section-content">
        <h3>Common Issues and Solutions</h3>
        
        <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h4>Fleeca Bank Payment Method Not Showing</h4>
            <p><strong>Symptoms:</strong> The Fleeca Bank payment option doesn't appear during checkout.</p>
            <p><strong>Solutions:</strong></p>
            <ul>
                <li>Verify that the Fleeca Module is activated in the module settings</li>
                <li>Check that you've entered a valid API key</li>
                <li>Confirm that the payment method is enabled in WooCommerce → Settings → Payments</li>
                <li>Ensure your site currency is set to USD (representing GTA$)</li>
                <li>Try clearing your browser cache and WooCommerce cache</li>
            </ul>
        </div>
        
        <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h4>Redirect to Fleeca Bank Fails</h4>
            <p><strong>Symptoms:</strong> Clicking the "Place order" button doesn't redirect to Fleeca Bank.</p>
            <p><strong>Solutions:</strong></p>
            <ul>
                <li>Check your API key for typos or extra spaces</li>
                <li>Verify that your website can make outgoing connections</li>
                <li>Check the logs tab for specific error messages</li>
                <li>Enable debug mode for more detailed information</li>
                <li>Contact GTA:W to verify your API key is active</li>
            </ul>
        </div>
        
        <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h4>No Redirect After Payment</h4>
            <p><strong>Symptoms:</strong> After completing payment on Fleeca Bank, you're not redirected back to the store.</p>
            <p><strong>Solutions:</strong></p>
            <ul>
                <li>Verify your callback URL is correct in both your module settings and in your GTA:W application</li>
                <li>Click the "Flush Rewrite Rules" button in the Fleeca settings page</li>
                <li>Ensure your callback URL is publicly accessible (not behind authentication)</li>
                <li>Check your server logs for any redirection errors</li>
                <li>Make sure your site doesn't have redirect loops or blocking rules</li>
            </ul>
        </div>
        
        <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h4>Payment Completes But Order Status Doesn't Update</h4>
            <p><strong>Symptoms:</strong> You're redirected back to your site, but the order remains in "Pending" status.</p>
            <p><strong>Solutions:</strong></p>
            <ul>
                <li>Check the Fleeca Module logs for token validation errors</li>
                <li>Enable debug mode for more detailed information</li>
                <li>From WooCommerce order page, use the "Debug Fleeca Payment" action in the order actions dropdown (admin only)</li>
                <li>Verify that your callback handler is processing the token correctly</li>
                <li>Ensure WooCommerce has permissions to update order statuses</li>
                <li>Look for any conflicts with other payment or order status plugins</li>
            </ul>
        </div>
        
        <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h4>404 Errors When Returning from Fleeca Bank</h4>
            <p><strong>Symptoms:</strong> After payment, redirecting back results in a 404 "Not Found" error.</p>
            <p><strong>Solutions:</strong></p>
            <ul>
                <li>Click the "Flush Rewrite Rules" button in the Fleeca settings page</li>
                <li>Check your permalink structure in WordPress Settings → Permalinks</li>
                <li>Try saving your permalinks again to regenerate rewrite rules</li>
                <li>Check for any security plugins that might block the callback URL</li>
                <li>Verify that your server's .htaccess file is correctly configured for WordPress</li>
            </ul>
        </div>
        
        <h3>Using the Logs</h3>
        <p>The Fleeca Module includes a comprehensive logging system to help diagnose issues:</p>
        <ul>
            <li>Go to the "Logs" tab in the Fleeca Module settings</li>
            <li>Enable "Debug Mode" in settings for more detailed logs</li>
            <li>Look for error messages or warnings related to your issue</li>
            <li>Note the timestamps to correlate events with user reports</li>
            <li>The logs include payment attempts, redirections, token validations, and API errors</li>
            <li>Log entries are color-coded: errors in red, success in green</li>
        </ul>
        
        <h3>Advanced Debugging Tools</h3>
        <p>For administrators and developers, additional debugging tools are available:</p>
        <ul>
            <li><strong>Token Validation Debug:</strong> From a WooCommerce order with Fleeca payment, use the "Debug Fleeca Payment" action</li>
            <li><strong>Manual Order Processing:</strong> Administrators can manually reprocess payments that failed to complete</li>
            <li><strong>Cache Clearing:</strong> Clear token validation cache to force fresh validation</li>
            <li><strong>Rewrite Rule Tools:</strong> Use the "Flush Rewrite Rules" button to refresh URL routing</li>
        </ul>
        
        <h3>Getting Support</h3>
        <p>If you continue to experience issues after trying the troubleshooting steps:</p>
        <ul>
            <li>Check the GitHub repository: <a href="https://github.com/Botticena/gtaw-bridge/" target="_blank">https://github.com/Botticena/gtaw-bridge/</a></li>
            <li>Submit an issue with detailed information:
                <ul>
                    <li>Steps to reproduce the problem</li>
                    <li>Error messages from the logs</li>
                    <li>Your WordPress, WooCommerce, and plugin versions</li>
                    <li>Screenshots of the issue (if applicable)</li>
                    <li>Export from logs tab (click "Export" button)</li>
                </ul>
            </li>
            <li>For API-specific issues, you may need to contact GTA:W UCP support directly</li>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}