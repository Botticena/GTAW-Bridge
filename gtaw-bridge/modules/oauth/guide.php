<?php
defined('ABSPATH') or exit;

/* ========= OAUTH MODULE GUIDE ========= */
/*
 * This file provides a comprehensive guide to the OAuth module's features
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
                <a href="#developers" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">For Developers</a>
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
                    <h2 style="margin: 0;">Core Features</h2>
                </div>
                
                <div class="section-content">
                    <div class="feature-details">
                        <h3>Single Sign-On Authentication</h3>
                        <p>The OAuth module allows users to log in to your WordPress site using their existing GTA:W accounts, eliminating the need for separate credentials. The authentication flow works as follows:</p>
                        <ol>
                            <li>User clicks the login button on your site</li>
                            <li>User is redirected to GTA:W's authentication page</li>
                            <li>After successful authentication, the user is redirected back to your site</li>
                            <li>If it's their first visit, they can select which character to use for registration</li>
                            <li>For returning users, they can choose which character account to log in with</li>
                        </ol>
                    </div>
                    
                    <div class="feature-details">
                        <h3>Character-Based Account System</h3>
                        <p>Each GTA:W character can have its own WordPress account on your site. This allows for character-specific content, permissions, and experiences:</p>
                        <ul>
                            <li>Users can create separate accounts for each of their GTA:W characters</li>
                            <li>The system automatically recognizes which accounts belong to the same GTA:W user</li>
                            <li>Character information is stored in user meta for easy access in your theme or plugins</li>
                            <li>Users can switch between character accounts easily</li>
                        </ul>
                    </div>
                    
                    <div class="feature-details">
                        <h3>Modal Login Experience</h3>
                        <p>When users return from GTA:W's authentication, they're presented with a modal dialog that offers options based on their account status:</p>
                        <ul>
                            <li><strong>First-time Users:</strong> Select which character to register with</li>
                            <li><strong>Returning Users:</strong> Choose which existing character account to log in with, or create an account for a new character</li>
                            <li>The modal provides a streamlined experience without page reloads</li>
                        </ul>
                    </div>
                    
                    <div class="feature-details">
                        <h3>WordPress Registration Override</h3>
                        <p>The module can optionally disable standard WordPress registration, ensuring all users come through the GTA:W authentication flow:</p>
                        <ul>
                            <li>Redirects standard registration page to GTA:W login</li>
                            <li>Adds GTA:W login option to the WordPress login page</li>
                            <li>Creates a consistent authentication experience across your site</li>
                        </ul>
                    </div>
                    
                    <div class="feature-details">
                        <h3>WooCommerce Integration</h3>
                        <p>If WooCommerce is installed, the OAuth module integrates with it for a seamless shopping experience:</p>
                        <ul>
                            <li>Adds character information to the My Account dashboard</li>
                            <li>Links orders to specific GTA:W characters</li>
                            <li>Allows for character-specific product access and permissions</li>
                        </ul>
                    </div>
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
            
            <!-- For Developers Section -->
            <div id="developers" class="content-section" style="display: none;">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">For Developers</h2>
                </div>
                
                <div class="section-content">
                    <p>This section provides information for developers who want to extend or customize the OAuth module's functionality.</p>
                    
                    <div class="developer-section">
                        <h3>Available Actions and Filters</h3>
                        <p>The OAuth module provides several action and filter hooks for extending its functionality:</p>
                        
                        <div class="hook-details" style="margin-top: 10px; border-left: 3px solid #0073aa; padding-left: 15px;">
                            <h4><code>gtaw_oauth_process_started</code> Action</h4>
                            <p>Triggered when a GTA:W user completes the OAuth authentication process.</p>
                            <p><strong>Parameters:</strong> <code>$user_data</code> - Array of user data from GTA:W API</p>
                            <p><strong>Example:</strong></p>
                            <pre>add_action('gtaw_oauth_process_started', function($user_data) {
    // Do something with the user data
    $username = $user_data['user']['username'] ?? '';
    error_log("GTA:W user $username authenticated");
});</pre>
                        </div>
                        
                        <div class="hook-details" style="margin-top: 10px; border-left: 3px solid #0073aa; padding-left: 15px;">
                            <h4><code>gtaw_oauth_character_registered</code> Action</h4>
                            <p>Triggered when a GTA:W character is registered as a WordPress user.</p>
                            <p><strong>Parameters:</strong></p>
                            <ul>
                                <li><code>$user_id</code> - WordPress user ID</li>
                                <li><code>$character_data</code> - Array of character data from GTA:W</li>
                            </ul>
                            <p><strong>Example:</strong></p>
                            <pre>add_action('gtaw_oauth_character_registered', function($user_id, $character_data) {
    // Assign a specific role based on character data
    $user = new WP_User($user_id);
    $user->set_role('subscriber');
    
    // Store additional character metadata
    update_user_meta($user_id, 'character_registered_date', current_time('mysql'));
}, 10, 2);</pre>
                        </div>
                    </div>
                    
                    <div class="developer-section">
                        <h3>User Meta Fields</h3>
                        <p>The OAuth module stores several user meta fields that you can use in your code:</p>
                        <ul>
                            <li><code>gtaw_user_id</code> - The GTA:W UCP user ID</li>
                            <li><code>active_gtaw_character</code> - Array containing the active character's data:
                                <ul>
                                    <li><code>id</code> - Character ID</li>
                                    <li><code>firstname</code> - Character's first name</li>
                                    <li><code>lastname</code> - Character's last name</li>
                                </ul>
                            </li>
                        </ul>
                        <p><strong>Example:</strong></p>
                        <pre>$user_id = get_current_user_id();
$character = get_user_meta($user_id, 'active_gtaw_character', true);

if ($character) {
    echo "Hello, {$character['firstname']} {$character['lastname']}!";
}</pre>
                    </div>
                    
                    <div class="developer-section">
                        <h3>Helper Functions</h3>
                        <p>The module provides several helper functions for working with GTA:W data:</p>
                        <ul>
                            <li><code>gtaw_is_user_linked_to_gtaw($user_id)</code> - Check if a user is linked to a GTA:W account</li>
                            <li><code>gtaw_get_oauth_url()</code> - Get the complete OAuth authorization URL</li>
                            <li><code>gtaw_reset_gtaw_connection($user_id)</code> - Reset a user's GTA:W connection</li>
                        </ul>
                        <p><strong>Example:</strong></p>
                        <pre>if (gtaw_is_user_linked_to_gtaw(get_current_user_id())) {
    // User is linked to GTA:W
    $login_url = gtaw_get_oauth_url();
    echo "&lt;a href='$login_url'>Switch Character&lt;/a>";
}</pre>
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
                    <p>The OAuth module includes a comprehensive logging system to help diagnose issues:</p>
                    <ul>
                        <li>Go to the "Logs" tab in the OAuth module settings</li>
                        <li>Look for error messages related to your issue</li>
                        <li>Pay attention to timestamps to correlate events</li>
                        <li>The logs include authentication attempts, account creations, and API errors</li>
                    </ul>
                    
                    <h3>Getting Support</h3>
                    <p>If you continue to experience issues after trying the troubleshooting steps:</p>
                    <ul>
                        <li>Check the GitHub repository: <a href="https://github.com/Botticena/gtaw-bridge/" target="_blank">https://github.com/Botticena/gtaw-bridge/</a></li>
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