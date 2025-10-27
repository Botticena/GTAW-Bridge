<?php
defined('ABSPATH') or exit;

/* ========= DISCORD CUSTOMER NOTIFICATIONS MODULE ========= */
/*
 * This module handles customer order notifications via Discord:
 * - Order status change notifications
 * - Notification templates
 * - Checkout Discord integration
 */

/* ========= ADMIN SETTINGS ========= */

// Register Discord notification settings
function gtaw_discord_register_notification_settings() {
    // Register the templates settings group
    add_option('gtaw_discord_templates_group');
    
    // Register the mandatory notifications setting
    register_setting('gtaw_discord_templates_group', 'gtaw_discord_notifications_mandatory');
    register_setting('gtaw_discord_templates_group', 'gtaw_discord_channel_id');
    
    // Get all WooCommerce order statuses to dynamically register all settings
    $statuses = wc_get_order_statuses();
    foreach ($statuses as $status_key => $status_label) {
        $clean_key = str_replace('wc-', '', $status_key);
        $enabled_key = 'gtaw_discord_notify_' . $clean_key . '_enabled';
        $template_key = 'gtaw_discord_notify_' . $clean_key . '_template';
        
        register_setting('gtaw_discord_templates_group', $enabled_key);
        register_setting('gtaw_discord_templates_group', $template_key);
    }
}
add_action('admin_init', 'gtaw_discord_register_notification_settings');

// Add tab callback for customer notifications
function gtaw_discord_notifications_tab() {
    // Get all WooCommerce order statuses
    $statuses = wc_get_order_statuses();
    ?>
    <form method="post" action="options.php">
        <?php 
            settings_fields('gtaw_discord_settings_group'); // For channel_id
            settings_fields('gtaw_discord_templates_group'); // For templates
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
                <tr valign="top">
                    <th scope="row">Require Discord Membership</th>
                    <td>
                        <input type="checkbox" name="gtaw_discord_notifications_mandatory" value="1" <?php checked(get_option('gtaw_discord_notifications_mandatory', '0'), '1'); ?> />
                        <p class="description">If enabled, customers must join your Discord server before they can place an order. Discord notifications will be mandatory.</p>
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
            // Important statuses to show first
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
                    }
                    ?>
                </div>
            </details>
        </div>

        <?php submit_button('Save Templates'); ?>
    </form>
    <?php
}

// Register the notifications tab
add_filter('gtaw_discord_settings_tabs', function($tabs) {
    $tabs['notifications'] = [
        'title' => 'Customer Notifications',
        'callback' => 'gtaw_discord_notifications_tab'
    ];
    return $tabs;
});

/* ========= CHECKOUT INTEGRATION ========= */

// Add Discord notification opt-in to WooCommerce checkout
function gtaw_add_discord_checkout_field() {
    // Skip for administrators using direct role check
    $current_user = wp_get_current_user();
    if (in_array('administrator', (array) $current_user->roles)) {
        return;
    }
    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_ID', true);
    $guild_id = get_option('gtaw_discord_guild_id', '');
    $is_in_server = false;
    $is_mandatory = get_option('gtaw_discord_notifications_mandatory', '0') == '1';

    if ($discord_id) {
        // Force a fresh check on checkout page load
        $is_in_server = gtaw_is_user_in_discord_server($discord_id, true);
    }
    ?>
    <div id="gtaw-discord-notifications">

        <h3>Discord Notifications</h3>
        <?php if ($is_mandatory): ?>
            <p>In order to place an order, you must join our Discord server to receive order updates.</p>
            <input type="hidden" name="gtaw_discord_notify" value="yes" />
        <?php else: ?>
            <p>Would you like to receive order status updates on Discord?</p>
            <div class="gtaw-discord-options">
                <label><input type="radio" name="gtaw_discord_notify" value="yes" checked> Yes, notify me</label>
                <label><input type="radio" name="gtaw_discord_notify" value="no"> No, I don't want updates</label>
            </div>
        <?php endif; ?>
        
        <?php if (!$discord_id): ?>
            <p class="gtaw-discord-warning">
                <span><b><?php echo $is_mandatory ? 'You must link your Discord account to place an order!' : 'You haven\'t linked your Discord account!'; ?></b></span>
                <a href="/my-account/discord/" target="_blank" rel="noopener noreferrer">Click here to link it now.</a>
            </p>
        <?php elseif (!$is_in_server): ?>
            <p class="gtaw-discord-warning" id="discord-server-warning">
                <span><b><?php echo $is_mandatory ? 'You must join our Discord server to place an order!' : 'You\'re not a member of our Discord server!'; ?></b></span>
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
      	<?php else: ?>
      		<span style="color: green;"><b>✓ Your discord account is linked and you are on your server.</b></span>
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

// Add checkout validation
function gtaw_validate_discord_checkout() {
    $is_mandatory = get_option('gtaw_discord_notifications_mandatory', '0') == '1';
    $wants_notifications = isset($_POST['gtaw_discord_notify']) && $_POST['gtaw_discord_notify'] === 'yes';

    // If notifications are mandatory or user wants them, check Discord status
    if ($is_mandatory || $wants_notifications) {
        $user_id = get_current_user_id();
        $discord_id = get_user_meta($user_id, 'discord_ID', true);

        // Check if Discord account is linked
        if (empty($discord_id)) {
            $message = $is_mandatory 
                ? 'You must link your Discord account to place an order.' 
                : 'You must link your Discord account to receive notifications or deselect Discord notifications.';
            wc_add_notice($message, 'error');
            return;
        }

        // Check if user is in the Discord server
        $is_in_server = gtaw_is_user_in_discord_server($discord_id, true);
        if (!$is_in_server) {
            $message = $is_mandatory 
                ? 'You must join our Discord server to place an order.' 
                : 'You must join our Discord server to receive notifications or deselect Discord notifications.';
            wc_add_notice($message, 'error');
        }
    }
}
add_action('woocommerce_checkout_process', 'gtaw_validate_discord_checkout');

// Display Discord notification opt-in on order details page
function gtaw_display_discord_notification_order($order) {
    $notify = get_post_meta($order->get_id(), 'gtaw_discord_notify', true);
    if ($notify === 'yes') {
        echo '<p><strong>Discord Notifications:</strong> Enabled</p>';
    }
}
add_action('woocommerce_admin_order_data_after_billing_address', 'gtaw_display_discord_notification_order', 10, 1);

/* ========= NOTIFICATION SENDING ========= */

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
    $channel_id = get_option('gtaw_discord_channel_id', '');
    if (empty($channel_id)) {
        gtaw_add_log('discord', 'Error', "Discord notification failed: Missing channel ID", 'error');
        return;
    }

    // Send message using our core utility function
    $result = gtaw_discord_send_message($channel_id, $message);
    
    if (is_wp_error($result)) {
        gtaw_add_log('discord', 'Error', "Failed to send Discord notification: " . $result->get_error_message(), 'error');
    } else {
        gtaw_add_log('discord', 'Notification', "Order #{$order->get_order_number()} status change notification sent to Discord channel", 'success');
    }
}
add_action('woocommerce_order_status_changed', 'gtaw_send_discord_order_notification', 10, 3);