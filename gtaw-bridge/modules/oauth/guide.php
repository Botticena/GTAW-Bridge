<?php
defined('ABSPATH') or exit;

/*
 * In-admin help HTML for OAuth.
 * - Introduction and overview
 * - Setup instructions
 * - Feature documentation
 * - Troubleshooting tips
 */

// Register the guide tab
function gtaw_oauth_guide_tab() {
    ?>
    <div class="gtaw-oauth-guide">
        <h2>GTAW Bridge OAuth Module Guide</h2>
        
        <div class="nav-wrapper" style="margin-bottom: 20px;">
            <div class="nav-tabs" style="display: flex; gap: 10px; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
                <a href="#overview" class="nav-link active" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px; font-weight: bold;">Overview</a>
                <a href="#setup" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Setup</a>
                <a href="#features" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Features</a>
                <a href="#shortcodes" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Shortcodes</a>
                <a href="#troubleshooting" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Troubleshooting</a>
            </div>
        </div>
        
        <div class="content-wrapper">
            <!-- Overview Section -->
            <div id="overview" class="content-section">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">OAuth Module Overview</h2>
                </div>
                
                <div class="section-content" style="margin-bottom: 30px;">
                    <p>The GTAW Bridge OAuth Module provides seamless integration between your WordPress website and GTA World's authentication system, allowing users to log in using their existing GTA:W accounts and characters.</p>
                    
                    <div class="feature-box" style="background: #f0f7ff; padding: 15px; border-radius: 5px; margin-top: 20px;">
                        <h3>Key Features</h3>
                        <ul>
                            <li><strong>Single Sign-On</strong> - Users can log in with their existing GTA:W accounts</li>
                            <li><strong>Character-Based Accounts</strong> - Users can create separate WordPress accounts for each of their GTA:W characters</li>
                            <li><strong>Secure Authentication</strong> - All authentication is handled by GTA:W's secure OAuth system</li>
                            <li><strong>Easy Account Management</strong> - Simple interface for users to link, create, and manage their character accounts</li>
                        </ul>
                    </div>
                    
                    <div class="prerequisites" style="margin-top: 20px;">
                        <h3>Prerequisites</h3>
                        <p>Before setting up the OAuth module, you'll need:</p>
                        <ul>
                            <li>A WordPress website with admin access</li>
                            <li>OAuth credentials from the <a href="https://ucp.gta.world/developers/oauth" target="_blank">GTA:W UCP Developers section</a></li>
                            <li>Knowledge of GTA:W's <a href="https://forum.gta.world/en/topic/141256-gta-world-website-regulations-last-update-march-1st-2025/" target="_blank">Website Regulations</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Setup Section -->
            <div id="setup" class="content-section" style="display: none;">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Setting Up the OAuth Module</h2>
                </div>
                
                <div class="section-content">
                    <div class="setup-step">
                        <h3>Step 1: Get OAuth Credentials from GTA:W</h3>
                        <ol>
                            <li>Go to the <a href="https://ucp.gta.world/developers/oauth" target="_blank">GTA:W UCP Developers section</a></li>
                            <li>Request new OAuth credentials for your application</li>
                            <li>Provide a descriptive name for your application</li>
                            <li>Note down the <strong>Client ID</strong> and <strong>Client Secret</strong> provided</li>
                        </ol>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 2: Configure Callback URL in GTA:W</h3>
                        <ol>
                            <li>In your developer application settings at GTA:W UCP, set your callback URL to:
                                <code><?php echo esc_url(site_url('?gta_oauth=callback')); ?></code>
                            </li>
                            <li>This URL must match exactly what you configure in your plugin settings</li>
                        </ol>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 3: Configure OAuth Settings in WordPress</h3>
                        <ol>
                            <li>Go to the "Settings" tab in the OAuth Module</li>
                            <li>Ensure the "Activate OAuth Module" checkbox is checked</li>
                            <li>Enter your <strong>Client ID</strong> and <strong>Client Secret</strong> from Step 1</li>
                            <li>Verify the Callback URL matches what you configured in Step 2</li>
                            <li>Save your settings</li>
                        </ol>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 4: Add Login Button to Your Site</h3>
                        <ol>
                            <li>Add the <code>[gtaw_login]</code> shortcode to any page where you want users to be able to log in</li>
                            <li>Alternatively, use the <code>[gtaw_login_button]</code> shortcode for a styled button</li>
                            <li>You may also use the automated login modal that appears when a user returns from the authentication process</li>
                        </ol>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 5: Test the Integration</h3>
                        <ol>
                            <li>Click on your login button to start the authentication flow</li>
                            <li>Authorize the application on GTA:W's website</li>
                            <li>You should be redirected back to your site and see the character selection modal</li>
                            <li>Select a character to create an account or log in</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <!-- Features Section -->
            <div id="features" class="content-section" style="display: none;">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">What you get</h2>
                </div>
                
                <div class="section-content">
                    <p>Players log in with their GTA:W account, pick a character, and get a normal WordPress user for that character. They can have one site account per character and switch when you allow it.</p>
                    <p>If you use WooCommerce, character info can show on My Account and orders can line up with who was shopping in-character.</p>
                    <ul>
                        <li>Login and registration go through GTA:W (no extra passwords for them to remember for your site)</li>
                        <li>After they sign in on GTA:W, your site can show a small prompt to create or pick a character account</li>
                    </ul>
                </div>
            </div>
            
            <!-- Shortcodes Section -->
            <div id="shortcodes" class="content-section" style="display: none;">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Available Shortcodes</h2>
                </div>
                
                <div class="section-content">
                    <p>The OAuth module provides several shortcodes to help you integrate GTA:W authentication into your site:</p>
                    
                    <div class="shortcode-details" style="margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
                        <h3><code>[gtaw_login]</code></h3>
                        <p>Displays a basic login link that redirects users to GTA:W authentication.</p>
                        <p><strong>Example:</strong> <code>[gtaw_login]</code></p>
                        <p><strong>Output:</strong> <em>"Login / Create Account via GTA:W"</em> link</p>
                    </div>
                    
                    <div class="shortcode-details" style="margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
                        <h3><code>[gtaw_login_button]</code></h3>
                        <p>Displays a styled button for GTA:W authentication with customizable options.</p>
                        <p><strong>Parameters:</strong></p>
                        <ul>
                            <li><code>text</code> - The button text (default: "Login with GTA:W")</li>
                            <li><code>class</code> - CSS class for styling (default: "gtaw-styled-button")</li>
                            <li><code>redirect</code> - URL to redirect to after authentication (optional)</li>
                        </ul>
                        <p><strong>Example:</strong> <code>[gtaw_login_button text="Join with GTA:W" redirect="/welcome/"]</code></p>
                    </div>
                    
                    <div class="shortcode-details" style="margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
                        <h3><code>[gtaw_user_info]</code></h3>
                        <p>Displays information about the currently logged-in GTA:W character.</p>
                        <p><strong>Parameters:</strong></p>
                        <ul>
                            <li><code>show_id</code> - Whether to show character ID (yes/no, default: "yes")</li>
                        </ul>
                        <p><strong>Example:</strong> <code>[gtaw_user_info show_id="no"]</code></p>
                        <p><strong>Output:</strong> <em>"You are logged in as Firstname Lastname"</em></p>
                    </div>
                    
                    <div class="shortcode-details" style="margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
                        <h3><code>[gtaw_character_info]</code></h3>
                        <p>Displays detailed information about the current GTA:W character.</p>
                        <p><strong>Example:</strong> <code>[gtaw_character_info]</code></p>
                        <p><strong>Output:</strong> A formatted box with character name and ID</p>
                    </div>
                    
                    <div class="shortcode-details" style="margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
                        <h3><code>[gtaw_if_logged_in]</code> and <code>[gtaw_if_not_logged_in]</code></h3>
                        <p>Conditional shortcodes to display content based on GTA:W login status.</p>
                        <p><strong>Example:</strong></p>
                        <pre>[gtaw_if_logged_in]
  Welcome back, character! 
[/gtaw_if_logged_in]

[gtaw_if_not_logged_in]
  Please login with GTA:W to continue.
  [gtaw_login_button]
[/gtaw_if_not_logged_in]</pre>
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
                        <h4>Authentication Fails with "Invalid redirect_uri" Error</h4>
                        <p><strong>Symptoms:</strong> Users are redirected to GTA:W but get an error about an invalid redirect URI.</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>Ensure the callback URL in your OAuth settings exactly matches the URL registered in the GTA:W UCP Developers section</li>
                            <li>Check for trailing slashes or HTTP vs. HTTPS mismatches</li>
                            <li>Try copying the URL from your settings page directly into the GTA:W developer portal</li>
                        </ul>
                    </div>
                    
                    <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                        <h4>Login Button Doesn't Redirect</h4>
                        <p><strong>Symptoms:</strong> Clicking the login button does nothing or results in an error.</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>Verify that your Client ID is correctly entered in the OAuth settings</li>
                            <li>Check the browser console for any JavaScript errors</li>
                            <li>Ensure the OAuth module is activated</li>
                            <li>Try regenerating your OAuth credentials in the GTA:W developer portal</li>
                        </ul>
                    </div>
                    
                    <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                        <h4>Character Selection Modal Doesn't Appear</h4>
                        <p><strong>Symptoms:</strong> After authenticating with GTA:W, users are redirected back but no character selection modal appears.</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>Check if the gtaw-script.js file is being loaded correctly</li>
                            <li>Verify that the 'gtaw_user_data' cookie is being set</li>
                            <li>Look for JavaScript errors in the browser console</li>
                            <li>Make sure there are no JavaScript conflicts with your theme or other plugins</li>
                        </ul>
                    </div>
                    
                    <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                        <h4>Account Creation Fails</h4>
                        <p><strong>Symptoms:</strong> Users can authenticate but get errors when trying to create a WordPress account.</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>Check your server logs for PHP errors</li>
                            <li>Ensure your WordPress user table isn't full (check user count)</li>
                            <li>Verify that your WordPress installation can create new users (permissions)</li>
                            <li>Look at the OAuth logs tab for specific error messages</li>
                        </ul>
                    </div>
                    
                    <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                        <h4>"Invalid Client ID" or "Invalid Client Secret" Errors</h4>
                        <p><strong>Symptoms:</strong> Authentication fails with messages about invalid credentials.</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>Double-check that your Client ID and Client Secret are entered correctly</li>
                            <li>Try regenerating your credentials in the GTA:W developer portal</li>
                            <li>Ensure there are no spaces or special characters accidentally included</li>
                            <li>Verify that your application is still active in the GTA:W developer portal</li>
                        </ul>
                    </div>
                    
                    <h3>Using the Logs</h3>
                    <p>There&rsquo;s a Logs tab if you need to see what broke:</p>
                    <ul>
                        <li>Go to the "Logs" tab in the OAuth module settings</li>
                        <li>Look for error messages related to your issue</li>
                        <li>Pay attention to timestamps to correlate events</li>
                        <li>The logs include authentication attempts, account creations, and API errors</li>
                    </ul>
                    
                    <h3>Getting Support</h3>
                    <p>If you continue to experience issues after trying the troubleshooting steps:</p>
                    <ul>
                        <li>Check the GitHub repository: <a href="https://github.com/Botticena/GTAW-Bridge/" target="_blank">https://github.com/Botticena/GTAW-Bridge/</a></li>
                        <li>Submit an issue with detailed information:
                            <ul>
                                <li>Steps to reproduce the problem</li>
                                <li>Error messages from the logs</li>
                                <li>Your WordPress and plugin versions</li>
                                <li>Any relevant browser console errors</li>
                            </ul>
                        </li>
                        <li>Remember to follow the <a href="https://forum.gta.world/en/topic/141256-gta-world-website-regulations-last-update-march-1st-2025/" target="_blank">GTA:W Website Regulations</a> when reporting issues</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab navigation
        document.querySelectorAll('.nav-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Update active tab
                document.querySelectorAll('.nav-link').forEach(function(el) {
                    el.classList.remove('active');
                    el.style.fontWeight = 'normal';
                });
                this.classList.add('active');
                this.style.fontWeight = 'bold';
                
                // Show corresponding content section
                var target = this.getAttribute('href');
                document.querySelectorAll('.content-section').forEach(function(section) {
                    section.style.display = 'none';
                });
                document.querySelector(target).style.display = 'block';
            });
        });
    });
    </script>
    <?php
}