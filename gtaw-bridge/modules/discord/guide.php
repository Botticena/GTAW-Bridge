<?php
defined('ABSPATH') or exit;

/* ========= DISCORD MODULE GUIDE ========= */
/*
 * This file provides a comprehensive guide to the Discord module's features
 * - Introduction and overview
 * - Setup instructions
 * - Feature documentation
 * - Troubleshooting tips
 */

// Register the guide tab
add_filter('gtaw_discord_settings_tabs', function($tabs) {
    $tabs['guide'] = [
        'title' => 'Guide',
        'callback' => 'gtaw_discord_guide_tab'
    ];
    return $tabs;
});

// Guide tab content
function gtaw_discord_guide_tab() {
    ?>
    <div class="gtaw-discord-guide">
        <h2>GTAW Bridge Discord Module Guide</h2>
        
        <div class="nav-wrapper" style="margin-bottom: 20px;">
            <div class="nav-tabs" style="display: flex; gap: 10px; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
                <a href="#overview" class="nav-link active" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px; font-weight: bold;">Overview</a>
                <a href="#setup" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Setup</a>
                <a href="#oauth" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Account Linking</a>
                <a href="#notifications" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Notifications</a>
                <a href="#store-notifications" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Store Notifications</a>
                <a href="#role-mapping" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Role Mapping</a>
                <a href="#troubleshooting" class="nav-link" style="text-decoration: none; padding: 8px 12px; background: #f0f0f0; border-radius: 4px;">Troubleshooting</a>
            </div>
        </div>
        
        <div class="content-wrapper">
            <!-- Overview Section -->
            <div id="overview" class="content-section">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Discord Module Overview</h2>
                </div>
                
                <div class="section-content" style="margin-bottom: 30px;">
                    <p>The GTAW Bridge Discord Module provides comprehensive integration between your WordPress site and Discord server, enhancing community engagement and administration.</p>
                    
                    <div class="feature-box" style="background: #f0f7ff; padding: 15px; border-radius: 5px; margin-top: 20px;">
                        <h3>Key Features</h3>
                        <ul>
                            <li><strong>Discord Account Linking</strong> - Allow users to link their Discord accounts to their existing GTA:W accounts</li>
                            <li><strong>Order Notifications</strong> - Send order status updates to customers via designated Discord channels</li>
                            <li><strong>Store Notifications</strong> - Receive order alerts and admin notifications in your Discord server</li>
                            <li><strong>Role Mapping</strong> - Synchronize WordPress user roles with Discord server roles</li>
                        </ul>
                    </div>
                    
                    <div class="prerequisites" style="margin-top: 20px;">
                        <h3>Prerequisites</h3>
                        <p>Before setting up the Discord module, you'll need:</p>
                        <ul>
                            <li>A Discord server where you have administrative permissions</li>
                            <li>A Discord application created at the <a href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</a></li>
                            <li>A Discord bot added to your application with appropriate permissions</li>
                            <li>The bot invited to your Discord server</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Setup Section -->
            <div id="setup" class="content-section" style="display: none;">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Setting Up the Discord Module</h2>
                </div>
                
                <div class="section-content">
                    <div class="setup-step">
                        <h3>Step 1: Create a Discord Application</h3>
                        <ol>
                            <li>Go to the <a href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</a> and sign in</li>
                            <li>Click "New Application" and give it a name (e.g., "GTAW Bridge")</li>
                            <li>Note down the <strong>Application ID</strong> (Client ID) for later use</li>
                            <li>Go to the "OAuth2" section and add a redirect URL:
                                <ul>
                                    <li>Enter: <code><?php echo site_url('?discord_oauth=callback'); ?></code></li>
                                    <li>This must match exactly what's in your Discord settings</li>
                                </ul>
                            </li>
                            <li>In the same section, under "Client Information", copy the <strong>Client Secret</strong></li>
                        </ol>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 2: Create a Bot</h3>
                        <ol>
                            <li>In your Discord application page, go to the "Bot" section</li>
                            <li>Click "Add Bot" and confirm</li>
                            <li>Under the bot's username, click "Reset Token" and copy the <strong>Bot Token</strong></li>
                            <li>Enable the following Privileged Gateway Intents:
                                <ul>
                                    <li>Server Members Intent</li>
                                    <li>Message Content Intent</li>
                                </ul>
                            </li>
                        </ol>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 3: Set Bot Permissions</h3>
                        <ol>
                            <li>Go to the "OAuth2" section, then "URL Generator"</li>
                            <li>Select the <strong>bot</strong> and <strong>applications.commands</strong> scopes</li>
                            <li>Under Bot Permissions, select:
                                <ul>
                                    <li>Manage Roles (for role mapping)</li>
                                    <li>Manage Channels</li>
                                    <li>Send Messages</li>
                                    <li>Embed Links</li>
                                    <li>Read Message History</li>
                                </ul>
                            </li>
                            <li>Copy the generated URL and open it in a new tab</li>
                            <li>Select your Discord server and authorize the bot</li>
                        </ol>
                    </div>
                    
                    <div class="setup-step">
                        <h3>Step 4: Configure the Discord Module</h3>
                        <ol>
                            <li>Go to the "Settings" tab in the Discord Module</li>
                            <li>Enter your <strong>Client ID</strong>, <strong>Client Secret</strong>, and <strong>Bot Token</strong></li>
                            <li>Enter your Discord <strong>Guild ID</strong> (Server ID):
                                <ul>
                                    <li>Enable Developer Mode in Discord (User Settings → Advanced → Developer Mode)</li>
                                    <li>Right-click your server icon and select "Copy ID"</li>
                                </ul>
                            </li>
                            <li>Enter a public <strong>Invite Code</strong> for your server (optional)</li>
                            <li>Save changes and verify the connection</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <!-- Account Linking Section -->
            <div id="oauth" class="content-section" style="display: none;">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Discord Account Linking</h2>
                </div>
                
                <div class="section-content">
                    <p>The account linking feature allows your users to connect their Discord accounts with their existing GTAW WordPress accounts.</p>
                    
                    <h3>Key Benefits</h3>
                    <ul>
                        <li>Enables Discord channel notifications for order updates</li>
                        <li>Allows role synchronization between platforms</li>
                        <li>Verifies that users are members of your Discord server</li>
                        <li>Creates stronger community integration</li>
                    </ul>
                    
                    <h3>User Account Linking</h3>
                    <p>Users can link their Discord accounts through:</p>
                    <ul>
                        <li>The "Discord Settings" page in their WooCommerce account dashboard</li>
                        <li>During checkout if Discord notifications are enabled</li>
                        <li>Any page where you add the <code>[gtaw_discord_buttons]</code> shortcode</li>
                    </ul>
                    
                    <h3>How Account Linking Works</h3>
                    <p>The account linking flow works as follows:</p>
                    <ol>
                        <li>User has already registered/logged in via the GTAW OAuth system</li>
                        <li>User initiates the Discord connection</li>
                        <li>They're redirected to Discord's authorization page</li>
                        <li>After authorizing, Discord redirects back to your site with a code</li>
                        <li>The plugin exchanges this code for an access token</li>
                        <li>User's Discord ID is stored and linked to their existing WordPress account</li>
                        <li>Role mapping and notifications are enabled for this user</li>
                    </ol>
                    
                    <div class="best-practices" style="background: #f0f7ff; padding: 15px; border-radius: 5px; margin-top: 20px;">
                        <h3>Best Practices</h3>
                        <ul>
                            <li>Add the Discord linking button to relevant user account pages</li>
                            <li>Encourage users to link their accounts for a better experience</li>
                            <li>Consider making Discord linking mandatory for customer support reasons</li>
                            <li>Explain the benefits of linking accounts to your users</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Section -->
            <div id="notifications" class="content-section" style="display: none;">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Order Notifications</h2>
                </div>
                
                <div class="section-content">
                    <p>The order notifications feature allows you to send order updates to your customers via a designated Discord channel.</p>
                    
                    <h3>Setting Up Order Notifications</h3>
                    <ol>
                        <li>Go to the "Customer Notifications" tab</li>
                        <li>Enter the Discord Channel ID where notifications will be sent</li>
                        <li>Optionally enable "Require Discord Membership" to make Discord notifications mandatory for checkout</li>
                        <li>Customize notification templates for different order statuses</li>
                    </ol>
                    
                    <h3>Available Shortcodes</h3>
                    <p>You can use these shortcodes in your notification templates:</p>
                    <ul>
                        <li><code>[order_id]</code> - The order number</li>
                        <li><code>[order_date]</code> - The date and time when the order was placed</li>
                        <li><code>[customer_name]</code> - Customer's full name</li>
                        <li><code>[total]</code> - Order total amount</li>
                        <li><code>[payment_method]</code> - Payment method used</li>
                        <li><code>[shipping_method]</code> - Shipping method selected</li>
                        <li><code>[discord_mention]</code> - Discord @mention for the customer</li>
                    </ul>
                    
                    <h3>Notification Flow</h3>
                    <ol>
                        <li>Customer places an order and opts in for Discord notifications</li>
                        <li>When order status changes, the system generates a notification</li>
                        <li>If the notification is enabled for that status, it's sent to the designated Discord channel</li>
                        <li>The notification will include a mention of the customer if they've linked their Discord account</li>
                    </ol>
                    
                    <div class="note" style="background: #fff8e5; padding: 15px; border-left: 4px solid #ffb900; margin-top: 20px;">
                        <h4>Important Note</h4>
                        <p>For customers to be mentioned in notifications, they must:</p>
                        <ul>
                            <li>Have their Discord account linked to your site</li>
                            <li>Be a member of your Discord server</li>
                            <li>Have opted in to receive Discord notifications for their order</li>
                        </ul>
                    </div>
                    
                    <h3>Channel Setup</h3>
                    <p>Consider these tips when setting up your notification channel:</p>
                    <ul>
                        <li>Create a dedicated channel for order notifications</li>
                        <li>Set appropriate permissions to protect customer privacy</li>
                        <li>Consider categorizing channels by order type or status</li>
                        <li>Use channel description to explain the purpose to your users</li>
                    </ul>
                </div>
            </div>
            
            <!-- Store Notifications Section -->
            <div id="store-notifications" class="content-section" style="display: none;">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Store Notifications</h2>
                </div>
                
                <div class="section-content">
                    <p>Store notifications send alerts to your Discord server when important store events occur, such as new orders.</p>
                    
                    <h3>Setting Up Store Notifications</h3>
                    <ol>
                        <li>Go to the "Store Notifications" tab</li>
                        <li>Enable store notifications</li>
                        <li>Enter the Discord Channel ID where store notifications will be sent</li>
                        <li>Customize the embed color and title format</li>
                        <li>Select which information fields to include in notifications</li>
                    </ol>
                    
                    <h3>Role Mentions</h3>
                    <p>You can mention specific Discord roles when a notification is sent:</p>
                    <ol>
                        <li>Enable "Mention Role" in the settings</li>
                        <li>Enter the Discord Role ID:
                            <ul>
                                <li>Right-click the role in Discord server settings and select "Copy ID"</li>
                            </ul>
                        </li>
                        <li>When notifications are sent, the role will be @mentioned</li>
                    </ol>
                    
                    <h3>Customizing Notification Content</h3>
                    <p>You can choose which information to include in store notifications:</p>
                    <ul>
                        <li>Customer Name and Phone</li>
                        <li>Order Total and Payment Method</li>
                        <li>Shipping Method and Address</li>
                        <li>Order Items (with quantities)</li>
                        <li>Customer Notes</li>
                    </ul>
                    
                    <div class="best-practices" style="background: #f0f7ff; padding: 15px; border-radius: 5px; margin-top: 20px;">
                        <h3>Best Practices</h3>
                        <ul>
                            <li>Create a dedicated private channel for store notifications</li>
                            <li>Only include essential information in the notification (avoid sensitive data)</li>
                            <li>Use role mentions for staff who need to be alerted to new orders</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Role Mapping Section -->
            <div id="role-mapping" class="content-section" style="display: none;">
                <div class="section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Role Mapping</h2>
                </div>
                
                <div class="section-content">
                    <p>Role mapping allows you to synchronize user roles between your WordPress site and Discord server, ensuring consistent permissions across both platforms.</p>
                    
                    <h3>Key Features</h3>
                    <ul>
                        <li><strong>Two-Way Synchronization</strong> - Changes in either system update the other</li>
                        <li><strong>Automatic Role Assignment</strong> - Users get appropriate roles when linking accounts</li>
                        <li><strong>Multiple Sync Triggers</strong> - Sync on login, account linking, role changes, and via schedule</li>
                        <li><strong>Role Priority Management</strong> - Control which roles take precedence</li>
                    </ul>
                    
                    <h3>Setting Up Role Mapping</h3>
                    <ol>
                        <li>Go to the "Role Mapping" tab</li>
                        <li>Enable role mapping</li>
                        <li>Choose whether to sync roles on login</li>
                        <li>Select a role priority mode:
                            <ul>
                                <li><strong>Highest Position</strong> - Use the Discord role highest in the hierarchy</li>
                                <li><strong>First Match</strong> - Use the first matching role in the mapping list</li>
                            </ul>
                        </li>
                        <li>Enable two-way synchronization if desired</li>
                        <li>Configure role mappings by selecting the WordPress role for each Discord role</li>
                    </ol>
                    
                    <h3>Discord → WordPress Sync</h3>
                    <p>This direction assigns WordPress roles based on a user's Discord roles:</p>
                    <ul>
                        <li>When a user links their Discord account, their WordPress role is updated</li>
                        <li>If a user's Discord roles change, their WordPress role is updated (on next login or sync)</li>
                        <li>Priority settings determine which role is used when a user has multiple Discord roles</li>
                    </ul>
                    
                    <h3>WordPress → Discord Sync</h3>
                    <p>This direction assigns Discord roles based on a user's WordPress role:</p>
                    <ul>
                        <li>When a user's WordPress role changes, their Discord roles are updated</li>
                        <li>Useful for ensuring Discord permissions match WordPress capabilities</li>
                        <li>Requires your Discord bot to have "Manage Roles" permission</li>
                        <li>The bot's role must be higher than any roles it manages</li>
                    </ul>
                    
                    <div class="note" style="background: #fff8e5; padding: 15px; border-left: 4px solid #ffb900; margin-top: 20px;">
                        <h4>Important Requirements</h4>
                        <p>For role mapping to work properly:</p>
                        <ul>
                            <li>Your Discord bot must have the "Manage Roles" permission</li>
                            <li>The bot's role must be positioned <strong>higher than</strong> any roles it needs to manage</li>
                            <li>Users must link their Discord accounts to their WordPress accounts</li>
                            <li>Users must be members of your Discord server</li>
                        </ul>
                    </div>
                    
                    <h3>Manual Role Sync</h3>
                    <p>You can manually trigger role synchronization in several ways:</p>
                    <ul>
                        <li>Use the "Sync All User Roles" button in the Role Mapping tab</li>
                        <li>Sync individual users from their WordPress profile page</li>
                        <li>Users can trigger a sync by re-linking their Discord account</li>
                    </ul>
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
                        <h4>Account Linking Issues</h4>
                        <p><strong>Symptoms:</strong> Users can't link their Discord accounts; OAuth flow fails</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>Verify that your Redirect URI in Discord Developer Portal exactly matches: <code><?php echo site_url('?discord_oauth=callback'); ?></code></li>
                            <li>Ensure your Client ID and Client Secret are entered correctly</li>
                            <li>Check that your application has the "identify" OAuth scope enabled</li>
                        </ul>
                    </div>
                    
                    <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                        <h4>Notifications Not Sending</h4>
                        <p><strong>Symptoms:</strong> Discord notifications are not being sent to the channel</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>Verify the bot is in your Discord server and has appropriate permissions</li>
                            <li>Check Channel IDs are correct (right-click channel in Discord → Copy ID)</li>
                            <li>Ensure the Bot Token is entered correctly</li>
                            <li>Check if notifications are enabled for the specific order status</li>
                            <li>Review the Logs tab for specific error messages</li>
                        </ul>
                    </div>
                    
                    <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                        <h4>Role Mapping Issues</h4>
                        <p><strong>Symptoms:</strong> Roles are not syncing between WordPress and Discord</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>Ensure the bot has "Manage Roles" permission</li>
                            <li>Verify the bot's role is higher than the roles it's managing</li>
                            <li>Check that role mappings are configured correctly</li>
                            <li>Confirm users have linked their Discord accounts</li>
                            <li>Try using the "Sync All User Roles" button</li>
                            <li>Check the Logs tab for specific error messages</li>
                        </ul>
                    </div>
                    
                    <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                        <h4>"Method Not Allowed" Error</h4>
                        <p><strong>Symptoms:</strong> 405 Method Not Allowed errors in the logs</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>This typically occurs with API permission issues</li>
                            <li>Verify all required scopes are enabled for your Discord application</li>
                            <li>Ensure the bot has all necessary permissions in your server</li>
                            <li>Check that you're using the Bot Token (not the Client Secret) for API calls</li>
                        </ul>
                    </div>
                    
                    <div class="issue-solution" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                        <h4>Users Cannot See the Discord Server</h4>
                        <p><strong>Symptoms:</strong> Users link their accounts but don't join your Discord server</p>
                        <p><strong>Solutions:</strong></p>
                        <ul>
                            <li>Ensure you've provided a valid invite link in the settings</li>
                            <li>Verify the invite link hasn't expired (set it to never expire)</li>
                            <li>Consider using the Discord widget to promote server visibility</li>
                        </ul>
                    </div>
                    
                    <h3>Using the Logs</h3>
                    <p>The Logs tab provides valuable information for troubleshooting:</p>
                    <ul>
                        <li>Check for error messages with specific details</li>
                        <li>Note timestamps to correlate with specific events</li>
                        <li>Look for patterns in repeated errors</li>
                        <li>Clear logs periodically to make new issues easier to spot</li>
                    </ul>
                    
                    <h3>Getting Support</h3>
                    <p>If you continue experiencing issues:</p>
                    <ul>
                        <li>Check the GitHub repository: <a href="https://github.com/Botticena/gtaw-bridge/" target="_blank">https://github.com/Botticena/gtaw-bridge/</a></li>
                        <li>Open an issue with detailed steps to reproduce</li>
                        <li>Include relevant log entries</li>
                        <li>Specify your WordPress and WooCommerce versions</li>
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