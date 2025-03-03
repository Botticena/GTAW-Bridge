<?php 
defined('ABSPATH') or exit;

/* ========= ADMIN SETTINGS ========= */

// Register Discord settings.
function gtaw_discord_register_settings() {
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_enabled');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_client_id');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_client_secret');
    register_setting('gtaw_discord_settings_group', 'gtaw_discord_bot_token');
  	register_setting('gtaw_discord_settings_group', 'gtaw_discord_guild_id');
	register_setting('gtaw_discord_settings_group', 'gtaw_discord_channel_id');
  	register_setting('gtaw_discord_settings_group', 'gtaw_discord_invite_link');
  
    // Register the templates settings group
	add_option('gtaw_discord_templates_group');

}
add_action('admin_init', 'gtaw_discord_register_settings');

// Register store notification settings
function gtaw_discord_register_store_notify_settings() {
    // Basic settings
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_enabled');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_channel');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_color');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_title');
    
    // Fields to include
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_customer');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_email');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_total');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_payment');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_shipping');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_items');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_address');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_notes');
}
add_action('admin_init', 'gtaw_discord_register_store_notify_settings');

// Add Discord Settings submenu under the main GTA:W Bridge menu.
function gtaw_add_discord_settings_submenu() {
    add_submenu_page(
        'gtaw-bridge',                   // Parent slug.
        'Discord Module',              // Page title.
        'Discord Module',              // Menu title.
        'manage_options',                // Capability.
        'gtaw-discord',         // Menu slug.
        'gtaw_discord_settings_page_callback' // Callback function.
    );
}
add_action('admin_menu', 'gtaw_add_discord_settings_submenu');

// Callback for the Discord Settings page.
function gtaw_discord_settings_page_callback() {
    // Auto-generate the Discord OAuth redirect URI.
    $redirect_uri = site_url('?discord_oauth=callback');
    $enabled      = get_option('gtaw_discord_enabled', 0);
    $client_id    = get_option('gtaw_discord_client_id', '');
    $client_secret= get_option('gtaw_discord_client_secret', '');
    $bot_token    = get_option('gtaw_discord_bot_token', '');
    $logs = gtaw_get_logs('discord');
    ?>
    <div class="wrap">
        <h1>Discord Module</h1>
        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="#settings" class="nav-tab nav-tab-active">Settings</a>
          	<a href="#templates" class="nav-tab">Customer Notifications</a>
          	<a href="#store-notifications" class="nav-tab">Store Notifications</a>
            <a href="#logs" class="nav-tab">Logs</a>
        </h2>
        <!-- Tab Content -->
        <div id="settings" class="tab-content">
            <form method="post" action="options.php">
                <?php 
                    settings_fields('gtaw_discord_settings_group');
                    do_settings_sections('gtaw_discord_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Activate Discord Module</th>
                        <td>
                            <input type="checkbox" name="gtaw_discord_enabled" value="1" <?php checked($enabled, 1); ?> />
                            <p class="description">Check to activate Discord integration. Uncheck to disable all Discord functionality.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Discord Client ID</th>
                        <td>
                            <input type="text" name="gtaw_discord_client_id" value="<?php echo esc_attr($client_id); ?>" size="50" />
                            <p class="description">Enter your Discord application’s Client ID for OAuth.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Discord Client Secret</th>
                        <td>
                            <input type="text" name="gtaw_discord_client_secret" value="<?php echo esc_attr($client_secret); ?>" size="50" />
                            <p class="description">Enter your Discord application’s Client Secret for OAuth.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Discord Bot Token</th>
                        <td>
                            <input type="text" name="gtaw_discord_bot_token" value="<?php echo esc_attr($bot_token); ?>" size="50" />
                            <p class="description">Enter your Discord Bot Token (for bot integrations).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Discord OAuth Redirect URI</th>
                        <td>
                            <input type="text" readonly value="<?php echo esc_url($redirect_uri); ?>" size="50" style="width:100%;" />
                            <p class="description">Set this URI in your Discord Developer Portal for your application.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Discord Guild ID</th>
                        <td>
                            <input type="text" name="gtaw_discord_guild_id" value="<?php echo esc_attr(get_option('gtaw_discord_guild_id', '')); ?>" size="50" />
                            <p class="description">Enter your Discord Server ID where notifications will be sent.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Discord Invite Code</th>
                        <td>
                            <input type="text" name="gtaw_discord_invite_link" value="<?php echo esc_attr(get_option('gtaw_discord_invite_link', '')); ?>" size="50" />
                            <p class="description">Enter your Discord server invite code (only the code part, not the full URL).</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        
        <div id="templates" class="tab-content" style="display:none;">
            <form method="post" action="options.php">
                <?php 
                    settings_fields('gtaw_discord_templates_group');
                    do_settings_sections('gtaw_discord_templates_group');
                ?>

                <div class="channel-settings" style="margin-bottom: 20px; background: #f0f0f0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                    <h3>Notification Settings</h3>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Channel ID</th>
                            <td>
                                <input type="text" name="gtaw_discord_channel_id" value="<?php echo esc_attr(get_option('gtaw_discord_channel_id', '')); ?>" size="50" />
                                <p class="description">Enter the channel ID where customer order notifications will be posted.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p>Customize your Discord notification templates for different order statuses. Available shortcodes:</p>
                <ul>
                    <li><code>[order_id]</code> - The order number</li>
                    <li><code>[order_date]</code> - The date and time when the order was placed</li>
                    <li><code>[customer_name]</code> - Customer's full name</li>
                    <li><code>[total]</code> - Order total amount (currency formatted)</li>
                    <li><code>[payment_method]</code> - Payment method used</li>
                    <li><code>[shipping_method]</code> - Shipping method selected</li>
                    <li><code>[discord_mention]</code> - Discord @mention for the customer (if linked)</li>
                </ul>

                <div class="primary-templates" style="margin-bottom: 30px;">
                    <h3>Essential Notification Templates</h3>
                    <p>These are the most commonly used notification templates for keeping customers informed about their orders.</p>

                    <?php
                    // Get all WooCommerce order statuses
                    $statuses = wc_get_order_statuses();
                    $primary_statuses = ['processing', 'completed'];

                    foreach ($primary_statuses as $status) {
                        $status_key = 'wc-' . $status;
                        $status_label = isset($statuses[$status_key]) ? $statuses[$status_key] : ucfirst($status);
                        $clean_key = $status;
                        $enabled_key = 'gtaw_discord_notify_' . $clean_key . '_enabled';
                        $template_key = 'gtaw_discord_notify_' . $clean_key . '_template';
                        $default_template = "Order #[order_id] status updated to {$status_label}\n\nCustomer: [customer_name]\nTotal: [total]\nPayment: [payment_method]";
                        ?>
                        <div class="template-box" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                            <h3><?php echo esc_html($status_label); ?> Template</h3>
                            <p>
                                <input type="checkbox" name="<?php echo esc_attr($enabled_key); ?>" value="1" <?php checked(get_option($enabled_key, '0'), '1'); ?> />
                                Enable notifications for this status
                            </p>
                            <textarea name="<?php echo esc_attr($template_key); ?>" rows="6" style="width: 100%;"><?php echo esc_textarea(get_option($template_key, $default_template)); ?></textarea>
                        </div>
                        <?php
                        // Register each setting
                        register_setting('gtaw_discord_templates_group', $enabled_key);
                        register_setting('gtaw_discord_templates_group', $template_key);

                        // Remove these from the full statuses list so we don't show them twice
                        unset($statuses[$status_key]);
                    }
                    ?>
                </div>

                <div class="additional-templates">
                    <details>
                        <summary style="cursor: pointer; font-size: 16px; font-weight: bold; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; margin-bottom: 15px;">
                            Additional Status Templates (Optional)
                        </summary>
                        <div style="padding: 15px; border: 1px solid #ddd; border-top: none; margin-bottom: 20px;">
                            <p><em>These templates are optional and typically not needed for most stores. Configure them only if you want notifications for these specific order statuses.</em></p>

                            <?php
                            // Output the remaining statuses
                            foreach ($statuses as $status_key => $status_label) {
                                $clean_key = str_replace('wc-', '', $status_key);
                                $enabled_key = 'gtaw_discord_notify_' . $clean_key . '_enabled';
                                $template_key = 'gtaw_discord_notify_' . $clean_key . '_template';
                                $default_template = "Order #[order_id] status updated to {$status_label}\n\nCustomer: [customer_name]\nTotal: [total]\nPayment: [payment_method]";
                                ?>
                                <div class="template-box" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
                                    <h3><?php echo esc_html($status_label); ?> Template</h3>
                                    <p>
                                        <input type="checkbox" name="<?php echo esc_attr($enabled_key); ?>" value="1" <?php checked(get_option($enabled_key, '0'), '1'); ?> />
                                        Enable notifications for this status
                                    </p>
                                    <textarea name="<?php echo esc_attr($template_key); ?>" rows="4" style="width: 100%;"><?php echo esc_textarea(get_option($template_key, $default_template)); ?></textarea>
                                </div>
                                <?php
                                // Register each setting
                                register_setting('gtaw_discord_templates_group', $enabled_key);
                                register_setting('gtaw_discord_templates_group', $template_key);
                            }
                            ?>
                        </div>
                    </details>
                </div>

                <?php submit_button('Save Templates'); ?>
            </form>
        </div>
      
        <div id="store-notifications" class="tab-content" style="display:none;">
            <form method="post" action="options.php">
                <?php 
                    settings_fields('gtaw_discord_storenotify_group');
                    do_settings_sections('gtaw_discord_storenotify_group');
                ?>

                <div class="channel-settings" style="margin-bottom: 20px; background: #f0f0f0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                    <h3>Store Notification Settings</h3>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Enable Store Notifications</th>
                            <td>
                                <input type="checkbox" name="gtaw_discord_storenotify_enabled" value="1" <?php checked(get_option('gtaw_discord_storenotify_enabled', '0'), '1'); ?> />
                                <p class="description">Receive Discord notifications when new orders are placed</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Store Channel ID</th>
                            <td>
                                <input type="text" name="gtaw_discord_storenotify_channel" value="<?php echo esc_attr(get_option('gtaw_discord_storenotify_channel', '')); ?>" size="50" />
                                <p class="description">Enter the channel ID where store owner notifications will be sent (different from customer notifications)</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Embed Color</th>
                            <td>
                                <input type="color" name="gtaw_discord_storenotify_color" value="<?php echo esc_attr(get_option('gtaw_discord_storenotify_color', '#5865F2')); ?>" />
                                <p class="description">Select a color for the embed sidebar</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Embed Title</th>
                            <td>
                                <input type="text" name="gtaw_discord_storenotify_title" value="<?php echo esc_attr(get_option('gtaw_discord_storenotify_title', 'New Order #[order_id]')); ?>" size="50" />
                                <p class="description">Title format for notification embeds. You can use [order_id] shortcode.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="embed-fields" style="margin-bottom: 30px;">
                    <h3>Embed Fields</h3>
                    <p>Select the information to display in your new order notifications:</p>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div class="field-option">
                            <label>
                                <input type="checkbox" name="gtaw_discord_storenotify_field_customer" value="1" <?php checked(get_option('gtaw_discord_storenotify_field_customer', '1'), '1'); ?> />
                                Customer Name
                            </label>
                        </div>
                        <div class="field-option">
                            <label>
                                <input type="checkbox" name="gtaw_discord_storenotify_field_phone" value="1" <?php checked(get_option('gtaw_discord_storenotify_field_phone', '1'), '1'); ?> />
                                Customer Phone
                            </label>
                        </div>
                        <div class="field-option">
                            <label>
                                <input type="checkbox" name="gtaw_discord_storenotify_field_total" value="1" <?php checked(get_option('gtaw_discord_storenotify_field_total', '1'), '1'); ?> />
                                Order Total
                            </label>
                        </div>
                        <div class="field-option">
                            <label>
                                <input type="checkbox" name="gtaw_discord_storenotify_field_payment" value="1" <?php checked(get_option('gtaw_discord_storenotify_field_payment', '1'), '1'); ?> />
                                Payment Method
                            </label>
                        </div>
                        <div class="field-option">
                            <label>
                                <input type="checkbox" name="gtaw_discord_storenotify_field_shipping" value="1" <?php checked(get_option('gtaw_discord_storenotify_field_shipping', '1'), '1'); ?> />
                                Shipping Method
                            </label>
                        </div>
                        <div class="field-option">
                            <label>
                                <input type="checkbox" name="gtaw_discord_storenotify_field_items" value="1" <?php checked(get_option('gtaw_discord_storenotify_field_items', '1'), '1'); ?> />
                                Order Items (with quantities)
                            </label>
                        </div>
                        <div class="field-option">
                            <label>
                                <input type="checkbox" name="gtaw_discord_storenotify_field_address" value="1" <?php checked(get_option('gtaw_discord_storenotify_field_address', '0'), '1'); ?> />
                                Shipping Address
                            </label>
                        </div>
                        <div class="field-option">
                            <label>
                                <input type="checkbox" name="gtaw_discord_storenotify_field_notes" value="1" <?php checked(get_option('gtaw_discord_storenotify_field_notes', '1'), '1'); ?> />
                                Customer Notes
                            </label>
                        </div>
                    </div>
                </div>

                <div class="notification-preview" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                    <h3>Preview</h3>
                    <p>This is how your notifications will appear in Discord (approximate):</p>

                    <div class="discord-embed-preview" style="border-left: 4px solid <?php echo esc_attr(get_option('gtaw_discord_storenotify_color', '#5865F2')); ?>; background: #36393f; color: #fff; padding: 8px 15px; border-radius: 0 3px 3px 0; max-width: 500px; font-family: sans-serif;">
                        <div style="font-weight: bold; margin-bottom: 8px; font-size: 16px;">
                            <?php echo esc_html(str_replace('[order_id]', '12345', get_option('gtaw_discord_storenotify_title', 'New Order #[order_id]'))); ?>
                        </div>
                        <div style="color: #dcddde; margin-bottom: 10px;">
                            A new order has been placed on your store.
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 14px;">
                            <?php if (get_option('gtaw_discord_storenotify_field_customer', '1') == '1'): ?>
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: bold; color: #dcddde;">Customer</div>
                                <div>John Doe</div>
                            </div>
                            <?php endif; ?>

                            <?php if (get_option('gtaw_discord_storenotify_field_phone', '1') == '1'): ?>
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: bold; color: #dcddde;">Phone</div>
                                <div>555-0123</div>
                            </div>
                            <?php endif; ?>

                            <?php if (get_option('gtaw_discord_storenotify_field_total', '1') == '1'): ?>
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: bold; color: #dcddde;">Total</div>
                                <div>$129 USD</div>
                            </div>
                            <?php endif; ?>

                            <?php if (get_option('gtaw_discord_storenotify_field_payment', '1') == '1'): ?>
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: bold; color: #dcddde;">Payment</div>
                                <div>Fleeca Bank</div>
                            </div>
                            <?php endif; ?>

                            <?php if (get_option('gtaw_discord_storenotify_field_shipping', '1') == '1'): ?>
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: bold; color: #dcddde;">Shipping</div>
                                <div>Postal Express</div>
                            </div>
                            <?php endif; ?>
                          
                            <?php if (get_option('gtaw_discord_storenotify_field_address', '1') == '1'): ?>
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: bold; color: #dcddde;">Shipping Address</div>
                                <div>123 Vinewood Hills Dr, Los Santos</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (get_option('gtaw_discord_storenotify_field_items', '1') == '1'): ?>
                        <div style="margin-top: 10px; font-size: 14px;">
                            <div style="font-weight: bold; color: #dcddde; margin-bottom: 4px;">Items</div>
                            <div>Premium Widget × 2<br>Deluxe Gadget × 1</div>
                        </div>
                        <?php endif; ?>

                        <?php if (get_option('gtaw_discord_storenotify_field_notes', '1') == '1'): ?>
                        <div style="margin-top: 10px; font-size: 14px;">
                            <div style="font-weight: bold; color: #dcddde; margin-bottom: 4px;">Customer Notes</div>
                            <div>Please leave package by the garage door.</div>
                        </div>
                        <?php endif; ?>

                        <div style="font-size: 11px; color: #dcddde; margin-top: 8px;">
                            Today at 12:34 PM
                        </div>
                    </div>
                </div>

                <?php submit_button('Save Store Notifications'); ?>
            </form>
        </div>
      
        <div id="logs" class="tab-content" style="display:none;">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Type</th><th>Message</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if (!empty($logs)) : ?>
                        <?php foreach ($logs as $log) : ?>
                            <tr style="color: <?php echo ($log['status'] === 'success') ? 'green' : 'red'; ?>;">
                                <td><?php echo esc_html($log['type']); ?></td>
                                <td><?php echo esc_html($log['message']); ?></td>
                                <td><?php echo esc_html($log['date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="3">No logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <button id="clear-logs" class="button button-danger">Clear Logs</button>
        </div>
        
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".nav-tab").forEach(tab => {
            tab.addEventListener("click", function() {
                document.querySelectorAll(".tab-content").forEach(content => content.style.display = "none");
                document.querySelector(this.getAttribute("href")).style.display = "block";
                document.querySelectorAll(".nav-tab").forEach(t => t.classList.remove("nav-tab-active"));
                this.classList.add("nav-tab-active");
            });
        });

        document.getElementById("clear-logs").addEventListener("click", function() {
            if (confirm("Are you sure you want to clear all logs?")) {
                fetch(ajaxurl, {
                    method: "POST",
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: "gtaw_clear_logs", module: "discord" })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Logs cleared successfully.");
                        location.reload();
                    } else {
                        alert("Error: " + data.data);
                    }
                })
                .catch(error => alert("Request failed: " + error));
            }
        });
    });
    </script>
    
    <?php
}


/* ========= FRONT-END FUNCTIONALITY ========= */
// Only register front-end functionality if the Discord module is activated.
if ( get_option('gtaw_discord_enabled', 0) == 1 ) {

    // Add a new rewrite endpoint "discord" so customers can access the Discord linking page.
    function gtaw_add_discord_endpoint() {
        add_rewrite_endpoint('discord', EP_ROOT | EP_PAGES);
    }
    add_action('init', 'gtaw_add_discord_endpoint');

    // Add "discord" to the list of query vars.
    function gtaw_discord_query_vars($vars) {
        $vars[] = 'discord';
        return $vars;
    }
    add_filter('query_vars', 'gtaw_discord_query_vars', 0);

    // Add a new item to the WooCommerce My Account navigation menu.
    function gtaw_add_discord_link_my_account($items) {
        $items['discord'] = 'Discord Settings';
        return $items;
    }
    add_filter('woocommerce_account_menu_items', 'gtaw_add_discord_link_my_account');

    // Content for the new WooCommerce "Discord" endpoint.
    function gtaw_discord_endpoint_content() {
        if ( ! is_user_logged_in() ) {
            echo '<p>You must be logged in to link your Discord account.</p>';
            return;
        }
        $user_id    = get_current_user_id();
        $discord_id = get_user_meta($user_id, 'discord_ID', true);
        if ($discord_id) {
            echo '<p>Your account is linked with Discord User ID: ' . esc_html($discord_id) . '</p>';
            // Display the unlink button using our shortcode.
            echo do_shortcode('[gtaw_discord_buttons]');
        } else {
            // If not linked, simply show the shortcode (which outputs the Link Discord Account link).
            echo do_shortcode('[gtaw_discord_buttons]');
        }
    }
    add_action('woocommerce_account_discord_endpoint', 'gtaw_discord_endpoint_content');

    /* ========= DISCORD OAUTH CALLBACK HANDLER ========= */
    // Handle Discord OAuth callback and link the Discord account.
    function gtaw_handle_discord_oauth_callback() {
        if ( isset($_GET['discord_oauth']) && $_GET['discord_oauth'] === 'callback' && isset($_GET['code']) ) {
            $code = sanitize_text_field($_GET['code']);
            $client_id = get_option('gtaw_discord_client_id', '');
            $client_secret = get_option('gtaw_discord_client_secret', '');
            $redirect_uri = site_url('?discord_oauth=callback');
            
            // Exchange the authorization code for an access token.
            $token_response = wp_remote_post('https://discord.com/api/oauth2/token', array(
                'body'    => array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $redirect_uri,
                ),
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded'
                )
            ));
            if ( is_wp_error($token_response) ) {
                wp_die('Error retrieving Discord access token.');
                gtaw_add_log('discord', 'Error', "Failed to link Discord account - Token retrieval error.", 'error');
            }
            $token_body = json_decode( wp_remote_retrieve_body($token_response), true );
            if ( empty($token_body['access_token']) ) {
                wp_die('No Discord access token returned.');
                gtaw_add_log('discord', 'Error', "Failed to link Discord account - No token returned.", 'error');
            }
            $access_token = sanitize_text_field($token_body['access_token']);
            
            // Retrieve the Discord user data.
            $user_response = wp_remote_get('https://discord.com/api/users/@me', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                )
            ));
            if ( is_wp_error($user_response) ) {
                wp_die('Error retrieving Discord user data.');
            }
            $user_body = json_decode( wp_remote_retrieve_body($user_response), true );
            if ( empty($user_body['id']) ) {
                wp_die('No Discord user ID returned.');
            }
            
            // Save the Discord user ID to the current user’s meta.
            if ( is_user_logged_in() ) {
                update_user_meta(get_current_user_id(), 'discord_ID', sanitize_text_field($user_body['id']));
                
                // Log successful linking
                $username = wp_get_current_user()->user_login;
                gtaw_add_log('discord', 'Link', "User {$username} linked their Discord account (ID: {$user_body['id']}).", 'success');
            }
            
            // Redirect back to the WooCommerce My Account Discord page.
            wp_redirect( wc_get_account_endpoint_url('discord') );
            exit;
        }
    }
    add_action('init', 'gtaw_handle_discord_oauth_callback');

    /* ========= AJAX ENDPOINT FOR UNLINKING ========= */
    // AJAX handler to unlink the Discord account (delete the discord_ID meta).
    function gtaw_discord_unlink_account_callback() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error('Not logged in');
        }
        check_ajax_referer('gtaw_discord_unlink_nonce', 'nonce');
        $user_id = get_current_user_id();
        delete_user_meta($user_id, 'discord_ID');
        
        // Log successful unlinking
        $username = wp_get_current_user()->user_login;
        gtaw_add_log('discord', 'Unlink', "User {$username} unlinked their Discord account.", 'success');
        
        wp_send_json_success('Discord account unlinked.');
    }
    add_action('wp_ajax_gtaw_discord_unlink_account', 'gtaw_discord_unlink_account_callback');

    /* ========= SHORTCODE FOR LINK/UNLINK BUTTONS ========= */
    // Helper function to generate the Discord OAuth URL.
    function gtaw_get_discord_auth_url() {
        $client_id = get_option('gtaw_discord_client_id', '');
        if ( empty($client_id) ) {
            return '';
        }
        $redirect_uri = site_url('?discord_oauth=callback');
        return add_query_arg(array(
            'client_id'     => $client_id,
            'redirect_uri'  => urlencode($redirect_uri),
            'response_type' => 'code',
            'scope'         => 'identify'
        ), 'https://discord.com/api/oauth2/authorize');
    }

    // Shortcode that displays either the link or unlink link.
    function gtaw_discord_buttons_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<p>Please log in to manage your Discord account.</p>';
        }
        
        $user_id    = get_current_user_id();
        $discord_id = get_user_meta($user_id, 'discord_ID', true);
        // Common style: bold, no underline, pointer cursor.
        $link_style = 'font-weight: bold; text-decoration: none; cursor: pointer;';
        
        if ( $discord_id ) {
            // Unlink state.
            $nonce = wp_create_nonce('gtaw_discord_unlink_nonce');
            $output = '<a href="javascript:void(0)" id="discord-unlink" style="' . esc_attr($link_style) . '" data-nonce="' . esc_attr($nonce) . '">Unlink Discord Account</a>';
            $output .= '
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $("#discord-unlink").on("click", function(e) {
                        e.preventDefault();
                        var nonce = $(this).data("nonce");
                        $.post("' . admin_url("admin-ajax.php") . '", {
                            action: "gtaw_discord_unlink_account",
                            nonce: nonce
                        }, function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert("Error: " + response.data);
                            }
                        }).fail(function() {
                            alert("An unexpected error occurred.");
                        });
                    });
                });
            </script>';
        } else {
            // Link state.
            $auth_url = gtaw_get_discord_auth_url();
            if ( empty($auth_url) ) {
                $output = '<p>Discord integration is not configured. Please contact the site administrator.</p>';
            } else {
                $output = '<a href="' . esc_url($auth_url) . '" id="discord-link" style="' . esc_attr($link_style) . '">Link Discord Account</a>';
            }
        }
        
        return $output;
    }
    add_shortcode('gtaw_discord_buttons', 'gtaw_discord_buttons_shortcode');
  
    // Function to check if user is in the discord server
    function gtaw_is_user_in_discord_server($discord_id, $force_check = false) {
        $bot_token = get_option('gtaw_discord_bot_token', '');
        $guild_id = get_option('gtaw_discord_guild_id', '');

        if (empty($bot_token) || empty($guild_id) || empty($discord_id)) {
            return false;
        }

        // Check cache first to reduce API calls, unless forced check
        if (!$force_check) {
            $cache_key = 'discord_member_' . $discord_id;
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached === 'yes';
            }
        }

        // Call Discord API to check membership
        $response = wp_remote_get("https://discord.com/api/v10/guilds/{$guild_id}/members/{$discord_id}", [
            'headers' => [
                'Authorization' => 'Bot ' . $bot_token
            ]
        ]);

        $is_member = false;
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $is_member = true;
        }

        // Cache result for 5 minutes on checkout page, 1 hour elsewhere
        // This allows more frequent checks on checkout where it matters most
        $cache_duration = is_checkout() ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS;
        $cache_key = 'discord_member_' . $discord_id;
        set_transient($cache_key, $is_member ? 'yes' : 'no', $cache_duration);

        return $is_member;
    }
  
    // Add Discord notification opt-in to WooCommerce checkout
    function gtaw_add_discord_checkout_field() {
        $user_id = get_current_user_id();
        $discord_id = get_user_meta($user_id, 'discord_ID', true);
        $guild_id = get_option('gtaw_discord_guild_id', '');
        $is_in_server = false;

        if ($discord_id) {
            // Force a fresh check on checkout page load
        	$is_in_server = gtaw_is_user_in_discord_server($discord_id, true);
        }
        ?>
        <div id="gtaw-discord-notifications">

          	<h3>Discord Notifications</h3>
            <p>Would you like to receive order status updates on Discord?</p>
            <div class="gtaw-discord-options">
                <label><input type="radio" name="gtaw_discord_notify" value="yes" checked> Yes, notify me</label>
                <label><input type="radio" name="gtaw_discord_notify" value="no"> No, I don't want updates</label>
            </div>
            <?php if (!$discord_id): ?>
                <p class="gtaw-discord-warning">
                    <span><b>You haven't linked your Discord account!</b></span>
                    <a href="/my-account/discord/" target="_blank" rel="noopener noreferrer">Click here to link it now.</a>
                </p>
            <?php elseif (!$is_in_server): ?>
                <p class="gtaw-discord-warning" id="discord-server-warning">
                    <span><b>You're not a member of our Discord server!</b></span>
                    <a href="https://discord.gg/<?php echo esc_attr(get_option('gtaw_discord_invite_link', '')); ?>" target="_blank" rel="noopener noreferrer">Join our server to receive notifications</a>
                    <button type="button" id="check-discord-membership" class="button" style="margin-left: 10px;">I've joined - Verify</button>
                </p>
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Handle manual verification
                    $('#check-discord-membership').on('click', function() {
                        const $button = $(this);
                        const $warning = $('#discord-server-warning');

                        $button.prop('disabled', true).text('Checking...');

                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'gtaw_check_discord_membership',
                            },
                            success: function(response) {
                                if (response.success && response.data.is_member) {
                                    $warning.html('<span style="color: green;"><b>✓ Discord server membership confirmed!</b></span>');
                                } else {
                                    $button.prop('disabled', false).text('Try Again');
                                    alert('Server membership not detected. Please make sure you\'ve joined the server and try again.');
                                }
                            },
                            error: function() {
                                $button.prop('disabled', false).text('Try Again');
                                alert('Error checking server membership. Please try again.');
                            }
                        });
                    });

                    // Auto-check every 30 seconds if warning is visible
                    if ($('#discord-server-warning').length) {
                        const checkInterval = setInterval(function() {
                            // Don't check if warning is gone or user already verified
                            if (!$('#discord-server-warning').is(':visible')) {
                                clearInterval(checkInterval);
                                return;
                            }

                            $.ajax({
                                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                type: 'POST',
                                data: {
                                    action: 'gtaw_check_discord_membership',
                                },
                                success: function(response) {
                                    if (response.success && response.data.is_member) {
                                        $('#discord-server-warning').html('<span style="color: green;"><b>✓ Discord server membership confirmed!</b></span>');
                                        clearInterval(checkInterval);
                                    }
                                }
                            });
                        }, 30000); // Check every 30 seconds
                    }
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    add_action('woocommerce_after_order_notes', 'gtaw_add_discord_checkout_field');
  
    // Save the Discord notification choice in order meta
    function gtaw_save_discord_checkout_field($order_id) {
        if (!empty($_POST['gtaw_discord_notify'])) {
            update_post_meta($order_id, 'gtaw_discord_notify', sanitize_text_field($_POST['gtaw_discord_notify']));
        }
    }
    add_action('woocommerce_checkout_update_order_meta', 'gtaw_save_discord_checkout_field');
  
    // Display Discord notification opt-in on order details page
    function gtaw_display_discord_notification_order($order) {
        $notify = get_post_meta($order->get_id(), 'gtaw_discord_notify', true);
        if ($notify === 'yes') {
            echo '<p><strong>Discord Notifications:</strong> Enabled</p>';
        }
    }
    add_action('woocommerce_admin_order_data_after_billing_address', 'gtaw_display_discord_notification_order', 10, 1);
  
    // Add an AJAX endpoint to check server membership
    function gtaw_check_discord_server_membership() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }

        $user_id = get_current_user_id();
        $discord_id = get_user_meta($user_id, 'discord_ID', true);

        if (empty($discord_id)) {
            wp_send_json_error('No Discord account linked');
        }

        // Clear cache to force a fresh check
        $cache_key = 'discord_member_' . $discord_id;
        delete_transient($cache_key);

        $is_member = gtaw_is_user_in_discord_server($discord_id);

        wp_send_json_success(['is_member' => $is_member]);
    }
    add_action('wp_ajax_gtaw_check_discord_membership', 'gtaw_check_discord_server_membership');
  
    // Function to process template shortcodes
    function gtaw_process_discord_template($template, $order) {
        $user_id = $order->get_user_id();
        $discord_id = get_user_meta($user_id, 'discord_ID', true);

        // Format the total price without HTML tags or decimals, with dollar sign
        $total = $order->get_total();
        $currency = $order->get_currency();
        $formatted_total = '$' . number_format($total, 0) . ' ' . $currency;

        // Get order date and format it
        $order_date = $order->get_date_created();
        $formatted_date = $order_date ? $order_date->date_i18n(get_option('date_format') . ' ' . get_option('time_format')) : '';

        $replacements = [
            '[order_id]' => $order->get_order_number(),
            '[order_date]' => $formatted_date,
            '[customer_name]' => $order->get_formatted_billing_full_name(),
            '[total]' => $formatted_total, // Now formats as $1,000 USD
            '[payment_method]' => $order->get_payment_method_title(),
            '[shipping_method]' => $order->get_shipping_method(),
            '[discord_mention]' => $discord_id ? "<@{$discord_id}>" : $order->get_formatted_billing_full_name()
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    // Customer Notification function
    function gtaw_send_discord_order_notification($order_id, $old_status, $new_status) {
        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Check if customer wants Discord notifications
        $notify = get_post_meta($order_id, 'gtaw_discord_notify', true);
        if ($notify !== 'yes') return;

        // Clean status name without wc- prefix
        $clean_status = str_replace('wc-', '', $new_status);

        // Check if notifications are enabled for this status
        $enabled_key = 'gtaw_discord_notify_' . $clean_status . '_enabled';
        if (get_option($enabled_key, '0') !== '1') return;

        // Get the template for this status
        $template_key = 'gtaw_discord_notify_' . $clean_status . '_template';
        $template = get_option($template_key, '');
        if (empty($template)) return;

        // Process template with order data
        $message = gtaw_process_discord_template($template, $order);

        // Get Discord configuration
        $bot_token = get_option('gtaw_discord_bot_token', '');
        $channel_id = get_option('gtaw_discord_channel_id', '');

        if (empty($bot_token) || empty($channel_id)) {
            gtaw_add_log('discord', 'Error', "Discord notification failed: Missing bot token or channel ID", 'error');
            return;
        }

        // Send message to the channel
        $response = wp_remote_post("https://discord.com/api/v10/channels/{$channel_id}/messages", [
            'headers' => [
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode(['content' => $message])
        ]);

        if (is_wp_error($response)) {
            gtaw_add_log('discord', 'Error', "Failed to send Discord notification: " . $response->get_error_message(), 'error');
        } else {
            gtaw_add_log('discord', 'Notification', "Order #{$order->get_order_number()} status change notification sent to Discord channel", 'success');
        }
    }
    add_action('woocommerce_order_status_changed', 'gtaw_send_discord_order_notification', 10, 3);

    function gtaw_whitelist_discord_options($allowed_options) {
        $allowed_options['gtaw_discord_templates_group'] = array();

        // Store notifications group
        $allowed_options['gtaw_discord_storenotify_group'] = array(
            'gtaw_discord_storenotify_enabled',
            'gtaw_discord_storenotify_channel',
            'gtaw_discord_storenotify_color',
            'gtaw_discord_storenotify_title',
            'gtaw_discord_storenotify_field_customer',
            'gtaw_discord_storenotify_field_phone',
            'gtaw_discord_storenotify_field_total',
            'gtaw_discord_storenotify_field_payment',
            'gtaw_discord_storenotify_field_shipping',
            'gtaw_discord_storenotify_field_items',
            'gtaw_discord_storenotify_field_address',
            'gtaw_discord_storenotify_field_notes'
        );

        // Get all WooCommerce order statuses to dynamically add all possible settings
        $statuses = wc_get_order_statuses();
        foreach ($statuses as $status_key => $status_label) {
            $clean_key = str_replace('wc-', '', $status_key);
            $allowed_options['gtaw_discord_templates_group'][] = 'gtaw_discord_notify_' . $clean_key . '_enabled';
            $allowed_options['gtaw_discord_templates_group'][] = 'gtaw_discord_notify_' . $clean_key . '_template';
        }

        return $allowed_options;
    }

    // Use the correct filter based on WordPress version
    global $wp_version;
    if (version_compare($wp_version, '5.5', '>=')) {
        add_filter('allowed_options', 'gtaw_whitelist_discord_options');
    } else {
        add_filter('whitelist_options', 'gtaw_whitelist_discord_options');
    }

    // Function to get clean street address
    function gtaw_get_street_address($order) {
        $shipping_address = $order->get_shipping_address_1();
        $billing_address = $order->get_billing_address_1();

        // Use shipping address if available, otherwise billing address
        return !empty($shipping_address) ? $shipping_address : $billing_address;
    }

    // Function to get order items in a readable format
    function gtaw_get_order_items_text($order) {
        $items_text = '';

        if (!$order) {
            return 'No order data';
        }

        // Get order items directly from order data
        $order_items = $order->get_items();

        if (empty($order_items)) {
            return 'No items in order';
        }

        foreach ($order_items as $item_id => $item) {
            // Direct data access for maximum compatibility
            $product_name = $item->get_data()['name'] ?? 'Unknown Product';
            $quantity = $item->get_data()['quantity'] ?? 1;

            // Add to items text
            $items_text .= "$product_name × $quantity\n";
        }

        return !empty($items_text) ? rtrim($items_text) : 'Items data unavailable';
    }

    // Function to get shipping method name
    function gtaw_get_shipping_method($order) {
        // Try to get shipping method through standard function
        $shipping_method = $order->get_shipping_method();

        // If that didn't work, try direct access to shipping items
        if (empty($shipping_method)) {
            $shipping_items = $order->get_items('shipping');
            $methods = [];

            if (!empty($shipping_items)) {
                foreach ($shipping_items as $shipping_item) {
                    $method_name = $shipping_item->get_data()['name'] ?? '';
                    if (!empty($method_name)) {
                        $methods[] = $method_name;
                    }
                }

                if (!empty($methods)) {
                    $shipping_method = implode(', ', $methods);
                }
            }
        }

        // Default value if still empty
        return !empty($shipping_method) ? $shipping_method : 'Local Pickup';
    }

    // Main notification function
    function gtaw_discord_send_store_notification($order_id) {
        // Check if store notifications are enabled
        if (get_option('gtaw_discord_storenotify_enabled', '0') !== '1') return;

        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            gtaw_add_log('discord', 'Error', "Failed to get order data for ID: $order_id", 'error');
            return;
        }

        // Log the order structure for debugging
        gtaw_add_log('discord', 'Debug', "Processing notification for Order #$order_id", 'success');

        // Get Discord settings
        $bot_token = get_option('gtaw_discord_bot_token', '');
        $channel_id = get_option('gtaw_discord_storenotify_channel', '');
        $color = get_option('gtaw_discord_storenotify_color', '#5865F2');

        if (empty($bot_token) || empty($channel_id)) {
            gtaw_add_log('discord', 'Error', "Discord notification failed: Missing bot token or channel ID", 'error');
            return;
        }

        // Convert hex color to decimal (Discord uses decimal color values)
        $color = hexdec(ltrim($color, '#'));

        // Build the embed
        $embed = [
            'title' => str_replace('[order_id]', $order->get_order_number(), get_option('gtaw_discord_storenotify_title', 'New Order #[order_id]')),
            'description' => 'A new order has been placed on your store.',
            'color' => $color,
            'fields' => [],
            'timestamp' => date('c') // ISO 8601 format
        ];

        // Add fields based on settings
        if (get_option('gtaw_discord_storenotify_field_customer', '1') == '1') {
            $embed['fields'][] = [
                'name' => 'Customer',
                'value' => $order->get_formatted_billing_full_name(),
                'inline' => true
            ];
        }

        if (get_option('gtaw_discord_storenotify_field_phone', '1') == '1') {
            $embed['fields'][] = [
                'name' => 'Phone',
                'value' => $order->get_billing_phone() ?: 'Not provided',
                'inline' => true
            ];
        }

        if (get_option('gtaw_discord_storenotify_field_total', '1') == '1') {
            $total = $order->get_total();
            $currency = $order->get_currency();
            $formatted_total = '$' . number_format($total, 0) . ' ' . $currency;

            $embed['fields'][] = [
                'name' => 'Total',
                'value' => $formatted_total,
                'inline' => true
            ];
        }

        if (get_option('gtaw_discord_storenotify_field_payment', '1') == '1') {
            $embed['fields'][] = [
                'name' => 'Payment Method',
                'value' => $order->get_payment_method_title(),
                'inline' => true
            ];
        }

        if (get_option('gtaw_discord_storenotify_field_shipping', '1') == '1') {
            $embed['fields'][] = [
                'name' => 'Shipping Method',
                'value' => gtaw_get_shipping_method($order),
                'inline' => true
            ];
        }

        if (get_option('gtaw_discord_storenotify_field_address', '0') == '1') {
            $street_address = gtaw_get_street_address($order);

            $embed['fields'][] = [
                'name' => 'Shipping Address',
                'value' => !empty($street_address) ? $street_address : 'No address provided',
                'inline' => false
            ];
        }

        if (get_option('gtaw_discord_storenotify_field_items', '1') == '1') {
            $items_text = gtaw_get_order_items_text($order);

            $embed['fields'][] = [
                'name' => 'Items',
                'value' => $items_text,
                'inline' => false
            ];
        }

        if (get_option('gtaw_discord_storenotify_field_notes', '1') == '1') {
            $notes = $order->get_customer_note();

            if (!empty($notes)) {
                $embed['fields'][] = [
                    'name' => 'Customer Notes',
                    'value' => $notes,
                    'inline' => false
                ];
            }
        }

        // Prepare the API request
        $response = wp_remote_post("https://discord.com/api/v10/channels/{$channel_id}/messages", [
            'headers' => [
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'embeds' => [$embed]
            ])
        ]);

        // Log the result
        if (is_wp_error($response)) {
            gtaw_add_log('discord', 'Error', "Failed to send store notification: " . $response->get_error_message(), 'error');
        } else {
            gtaw_add_log('discord', 'Notification', "Store notification sent for Order #{$order->get_order_number()}", 'success');
        }
    }

    // Add a hook to debug order structure
    add_action('woocommerce_new_order', 'gtaw_debug_order_structure', 10);
    function gtaw_debug_order_structure($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Log order items count
        $items = $order->get_items();
        gtaw_add_log('discord', 'Debug', "Order #$order_id has " . count($items) . " items", 'success');

        // Log the raw item data for the first item
        if (!empty($items)) {
            $first_item = reset($items);
            gtaw_add_log('discord', 'Debug', "First item data: " . print_r($first_item->get_data(), true), 'success');
        }
    }

    // Send store notification only when order status changes to processing
    function gtaw_send_store_notification_on_processing($order_id, $old_status, $new_status) {
        // Only trigger when status changes to processing
        if ($new_status !== 'processing') {
            return;
        }

        // Call the existing notification function
        gtaw_discord_send_store_notification($order_id);
    }
    add_action('woocommerce_order_status_changed', 'gtaw_send_store_notification_on_processing', 10, 3);

    // Make phone field mandatory in WooCommerce checkout
    function gtaw_make_phone_field_required($fields) {
        $fields['billing']['billing_phone']['required'] = true;
        return $fields;
    }
    add_filter('woocommerce_checkout_fields', 'gtaw_make_phone_field_required');

}


