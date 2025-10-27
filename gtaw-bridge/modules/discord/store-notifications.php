<?php
defined('ABSPATH') or exit;

/* ========= DISCORD STORE NOTIFICATIONS MODULE ========= */
/*
 * This module handles store owner notifications via Discord:
 * - New order notifications
 * - Customizable embeds
 * - Role mentions
 */

/* ========= ADMIN SETTINGS ========= */

// Register store notification settings
function gtaw_discord_register_store_notify_settings() {
    // Basic settings
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_enabled');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_channel');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_color');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_title');
    
    // Role mention settings
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_role_id');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_role_enabled');
    
    // Fields to include
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_customer');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_phone');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_total');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_payment');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_shipping');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_items');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_address');
    register_setting('gtaw_discord_storenotify_group', 'gtaw_discord_storenotify_field_notes');
}
add_action('admin_init', 'gtaw_discord_register_store_notify_settings');

// Add tab callback for store notifications
function gtaw_discord_store_notifications_tab() {
    ?>
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
                    <th scope="row">Mention Role</th>
                    <td>
                        <input type="checkbox" name="gtaw_discord_storenotify_role_enabled" value="1" <?php checked(get_option('gtaw_discord_storenotify_role_enabled', '0'), '1'); ?> />
                        <input type="text" name="gtaw_discord_storenotify_role_id" value="<?php echo esc_attr(get_option('gtaw_discord_storenotify_role_id', '')); ?>" placeholder="Role ID" size="20" />
                        <p class="description">Enable to mention a role when a new order notification is sent. Enter the Discord Role ID.</p>
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
    <?php
}

// Register the store notifications tab
add_filter('gtaw_discord_settings_tabs', function($tabs) {
    $tabs['store-notifications'] = [
        'title' => 'Store Notifications',
        'callback' => 'gtaw_discord_store_notifications_tab'
    ];
    return $tabs;
});

/* ========= HELPER FUNCTIONS ========= */

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

    // Get order items
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

/* ========= NOTIFICATION SENDING ========= */

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

    // Log the order processing
    gtaw_add_log('discord', 'Debug', "Processing notification for Order #$order_id", 'success');

    // Get Discord configuration
    $channel_id = get_option('gtaw_discord_storenotify_channel', '');
    $color = get_option('gtaw_discord_storenotify_color', '#5865F2');

    if (empty($channel_id)) {
        gtaw_add_log('discord', 'Error', "Discord store notification failed: Missing channel ID", 'error');
        return;
    }

    // Convert hex color to decimal (Discord uses decimal color values)
    $color = hexdec(ltrim($color, '#'));

    // Prepare mention content if enabled
    $message_content = '';
    if (get_option('gtaw_discord_storenotify_role_enabled', '0') == '1' && 
        !empty(get_option('gtaw_discord_storenotify_role_id', ''))) {
        $role_id = get_option('gtaw_discord_storenotify_role_id', '');
        $message_content = '<@&' . $role_id . '>';
    }

    // Build the embed
    $embed = [
        'title' => str_replace('[order_id]', $order->get_order_number(), 
                 get_option('gtaw_discord_storenotify_title', 'New Order #[order_id]')),
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

    // Send the message using our core utility function
    $result = gtaw_discord_api_request("channels/{$channel_id}/messages", [
        'body' => json_encode([
            'content' => $message_content,
            'embeds' => [$embed]
        ]),
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ], 'POST');

    // Log the result
    if (is_wp_error($result)) {
        gtaw_add_log('discord', 'Error', "Failed to send store notification: " . $result->get_error_message(), 'error');
    } else {
        gtaw_add_log('discord', 'Notification', "Store notification sent for Order #{$order->get_order_number()}", 'success');
    }
}

// Send store notification only when order status changes to processing
function gtaw_send_store_notification_on_processing($order_id, $old_status, $new_status) {
    // Only trigger when status changes to processing
    if ($new_status !== 'processing') {
        return;
    }

    // Call the notification function
    gtaw_discord_send_store_notification($order_id);
}
add_action('woocommerce_order_status_changed', 'gtaw_send_store_notification_on_processing', 10, 3);

// Make phone field mandatory in WooCommerce checkout
function gtaw_make_phone_field_required($fields) {
    $fields['billing']['billing_phone']['required'] = true;
    return $fields;
}
add_filter('woocommerce_checkout_fields', 'gtaw_make_phone_field_required');