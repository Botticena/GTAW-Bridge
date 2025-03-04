<?php
defined('ABSPATH') or exit;

/* ========= FLEECA MODULE GUIDE ========= */
/*
 * This file provides a comprehensive guide to the Fleeca module's features
 * - Introduction and overview
 * - Setup instructions
 * - Troubleshooting tips
 */

// Register the guide tab
function gtaw_fleeca_guide_tab() {
    ?>
    <div class="gtaw-fleeca-guide">
        <h2>GTAW Bridge Fleeca Module Guide</h2>
        
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
                        </ul>
                    </div>
                    
                    <div class="prerequisites" style="margin-top: 20px;">
                        <h3>Prerequisites</h3>
                        <p>Before setting up the Fleeca module, you'll need:</p>
                        <ul>
                            <li>A WordPress website with WooCommerce installed and activated</li>
                            <li>A Fleeca Bank API key from the <a href="https://ucp.gta.world/developers" target="_blank">GTA:W UCP Developers section</a></li>
                            <li>Administrator access to your WordPress site</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Setup Section -->
            <div id="setup" class="content-section" style="display: none;">
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
                            <li>Check "Activate Fleeca Module" to enable the integration</li>
                            <li>Enter your Fleeca Bank API key</li>
                            <li>Set your preferred gateway display name (e.g., "Fleeca Bank")</li>
                            <li>Verify the callback URL matches what you provided when requesting your API key</li>
                            <li>Save your settings</li>
                        </ol>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 3: Enable Fleeca Bank in WooCommerce</h3>
                        <ol>
                            <li>Go to WooCommerce → Settings → Payments</li>
                            <li>You should see "Fleeca Bank" (or your custom name) in the payment methods list</li>
                            <li>Ensure it's enabled by toggling the switch to ON</li>
                            <li>Save changes</li>
                        </ol>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 4: Test the Integration</h3>
                        <ol>
                            <li>Create a test product in your WooCommerce store</li>
                            <li>Add it to the cart and proceed to checkout</li>
                            <li>Select "Fleeca Bank" as your payment method</li>
                            <li>Complete the checkout process</li>
                            <li>You should be redirected to the Fleeca Bank payment page</li>
                            <li>After payment, you'll be redirected back to your site</li>
                            <li>Your order should be marked as completed</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <!-- Testing Section -->
            <div id="testing" class="content-section" style="display: none;">
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
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Troubleshooting Section -->
            <div id="troubleshooting" class="content-section" style="display: none;">
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
                            <li>Contact GTA:W to verify your API key is active</li>
                        </ul>
                    </div>
                    
                    <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                        <h4>No Redirect After Payment</h4>
                        <p><strong>Symptoms:</strong> After completing payment on Fleeca Bank, you're not redirected back to the store.</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>Verify your callback URL is correct in both your module settings and in your GTA:W application</li>
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
                            <li>Verify that your callback handler is processing the token correctly</li>
                            <li>Ensure WooCommerce has permissions to update order statuses</li>
                            <li>Look for any conflicts with other payment or order status plugins</li>
                        </ul>
                    </div>
                    
                    <h3>Using the Logs</h3>
                    <p>The Fleeca Module includes a comprehensive logging system to help diagnose issues:</p>
                    <ul>
                        <li>Go to the "Logs" tab in the Fleeca Module settings</li>
                        <li>Look for error messages or warnings related to your issue</li>
                        <li>Note the timestamps to correlate events with user reports</li>
                        <li>The logs include payment attempts, redirections, token validations, and API errors</li>
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
                            </ul>
                        </li>
                        <li>For API-specific issues, you may need to contact GTA:W UCP support directly</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Tab navigation
        $('.nav-link').on('click', function(e) {
            e.preventDefault();
            
            // Update active tab
            $('.nav-link').removeClass('active');
            $(this).addClass('active');
            
            // Show corresponding content section
            const target = $(this).attr('href');
            $('.content-section').hide();
            $(target).show();
        });
    });
    </script>
    <?php
}