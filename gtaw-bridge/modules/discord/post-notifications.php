<?php
defined('ABSPATH') or exit;

/* ========= DISCORD POST NOTIFICATIONS MODULE ========= */
/*
 * This module adds support for Discord notifications when new posts are published:
 * - Custom notification templates per post type
 * - Different Discord channels per post type
 * - Placeholder system for dynamic content
 * - Admin UI for managing notification templates
 */

/* ========= ADMIN SETTINGS ========= */

// Register post notification settings
function gtaw_discord_register_post_notification_settings() {
    register_setting('gtaw_discord_post_notification_group', 'gtaw_discord_post_notifications', [
        'sanitize_callback' => 'gtaw_sanitize_post_notifications'
    ]);
}
add_action('admin_init', 'gtaw_discord_register_post_notification_settings');

// Sanitize post notifications before saving
function gtaw_sanitize_post_notifications($input) {
    if (!is_array($input)) {
        return [];
    }
    
    $sanitized = [];
    foreach ($input as $id => $notification) {
        if (!isset($notification['post_type']) || !isset($notification['channel_id']) || !isset($notification['template'])) {
            continue;
        }
        
        $sanitized[$id] = [
            'post_type' => sanitize_text_field($notification['post_type']),
            'channel_id' => sanitize_text_field($notification['channel_id']),
            'template' => wp_kses_post($notification['template']),
            'enabled' => isset($notification['enabled']) ? (bool) $notification['enabled'] : false,
            'include_thumbnail' => isset($notification['include_thumbnail']) ? (bool) $notification['include_thumbnail'] : false,
            'include_fields' => isset($notification['include_fields']) ? (bool) $notification['include_fields'] : false,
        ];
    }
    
    return $sanitized;
}

// Add Post Notifications tab to Discord settings
function gtaw_discord_post_notifications_tab() {
    // Get registered post types
    $post_types = get_post_types(['public' => true], 'objects');
    
    // Get current notification settings
    $notifications = get_option('gtaw_discord_post_notifications', []);
    
    // Create nonce for AJAX
    $nonce = wp_create_nonce('gtaw_discord_post_notification_nonce');
    ?>
    <form method="post" action="options.php" id="post-notification-form">
        <?php 
            settings_fields('gtaw_discord_post_notification_group');
            do_settings_sections('gtaw_discord_post_notification_group');
        ?>
        
        <div class="post-notifications-intro" style="margin-bottom: 20px; background: #f0f0f0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
            <h3>Post Type Notifications</h3>
            <p>Configure Discord notifications to be sent when new posts of specific types are published.</p>
            <p>Each notification can be sent to a different Discord channel and use a custom template.</p>
        </div>
        
        <div class="post-notifications-placeholders" style="margin-bottom: 20px; background: #fff8e5; padding: 15px; border: 1px solid #ffb900; border-radius: 5px;">
            <h3>Available Placeholders</h3>
            <p>Use these placeholders in your notification templates:</p>
            <ul style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
                <li><code>[post_title]</code> - Post title</li>
                <li><code>[post_excerpt]</code> - Post excerpt</li>
                <li><code>[post_content]</code> - Short version of content</li>
                <li><code>[post_author]</code> - Author name</li>
                <li><code>[post_date]</code> - Publication date</li>
                <li><code>[post_url]</code> - Direct link to post</li>
                <li><code>[post_type_label]</code> - Post type label</li>
                <li><code>[categories]</code> - Post categories</li>
                <li><code>[tags]</code> - Post tags</li>
            </ul>
        </div>
        
        <div class="notification-templates" id="notification-templates">
            <?php if (empty($notifications)): ?>
                <!-- No templates yet, will be added via JS -->
                <div class="no-notifications" style="text-align: center; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 20px;">
                    <p>No notification templates configured yet. Click the button below to add your first template.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $id => $notification): ?>
                    <div class="notification-template" data-id="<?php echo esc_attr($id); ?>" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h3 style="margin: 0;">
                                <?php 
                                $post_type_obj = get_post_type_object($notification['post_type']);
                                $type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $notification['post_type'];
                                echo esc_html($type_label) . ' Notification'; 
                                ?>
                            </h3>
                            <div>
                                <button type="button" class="button remove-notification" data-id="<?php echo esc_attr($id); ?>">Remove</button>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Post Type</label>
                                <select name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][post_type]" style="width: 100%;">
                                    <?php foreach ($post_types as $post_type): ?>
                                        <option value="<?php echo esc_attr($post_type->name); ?>" <?php selected($notification['post_type'], $post_type->name); ?>>
                                            <?php echo esc_html($post_type->labels->singular_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Discord Channel ID</label>
                                <input type="text" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][channel_id]" value="<?php echo esc_attr($notification['channel_id']); ?>" style="width: 100%;" placeholder="Enter Discord channel ID">
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Notification Template</label>
                            <textarea name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][template]" rows="6" style="width: 100%;" placeholder="Enter your notification template using the available placeholders"><?php echo esc_textarea($notification['template']); ?></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 20px;">
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][enabled]" value="1" <?php checked(isset($notification['enabled']) ? $notification['enabled'] : false); ?>>
                                <span>Enable Notification</span>
                            </label>
                            
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][include_thumbnail]" value="1" <?php checked(isset($notification['include_thumbnail']) ? $notification['include_thumbnail'] : false); ?>>
                                <span>Include Featured Image</span>
                            </label>
                            
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][include_fields]" value="1" <?php checked(isset($notification['include_fields']) ? $notification['include_fields'] : false); ?>>
                                <span>Include Custom Fields (if available)</span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-controls" style="margin-bottom: 20px;">
            <button type="button" id="add-notification" class="button button-primary">Add New Notification Template</button>
            <span id="notification-status" style="margin-left: 10px;"></span>
        </div>
        
        <?php submit_button('Save Notification Settings'); ?>
    </form>
    
    <!-- Template for new notification entries -->
    <template id="notification-template">
        <div class="notification-template" data-id="{id}" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0;">New Notification</h3>
                <div>
                    <button type="button" class="button remove-notification" data-id="{id}">Remove</button>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Post Type</label>
                    <select name="gtaw_discord_post_notifications[{id}][post_type]" style="width: 100%;">
                        <?php foreach ($post_types as $post_type): ?>
                            <option value="<?php echo esc_attr($post_type->name); ?>">
                                <?php echo esc_html($post_type->labels->singular_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Discord Channel ID</label>
                    <input type="text" name="gtaw_discord_post_notifications[{id}][channel_id]" value="" style="width: 100%;" placeholder="Enter Discord channel ID">
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Notification Template</label>
                <textarea name="gtaw_discord_post_notifications[{id}][template]" rows="6" style="width: 100%;" placeholder="Enter your notification template using the available placeholders">**New [post_type_label] Published!**

**[post_title]**

[post_excerpt]

👉 [post_url]</textarea>
            </div>
            
            <div style="display: flex; gap: 20px;">
                <label style="display: flex; align-items: center; gap: 5px;">
                    <input type="checkbox" name="gtaw_discord_post_notifications[{id}][enabled]" value="1" checked>
                    <span>Enable Notification</span>
                </label>
                
                <label style="display: flex; align-items: center; gap: 5px;">
                    <input type="checkbox" name="gtaw_discord_post_notifications[{id}][include_thumbnail]" value="1">
                    <span>Include Featured Image</span>
                </label>
                
                <label style="display: flex; align-items: center; gap: 5px;">
                    <input type="checkbox" name="gtaw_discord_post_notifications[{id}][include_fields]" value="1">
                    <span>Include Custom Fields (if available)</span>
                </label>
            </div>
        </div>
    </template>
    
    <script>
    jQuery(document).ready(function($) {
        // Add new notification template
        $('#add-notification').on('click', function() {
            const template = $('#notification-template').html();
            const id = 'n' + Date.now(); // Generate unique ID
            const newTemplate = template.replace(/{id}/g, id);
            
            $('.no-notifications').remove(); // Remove placeholder if exists
            $('#notification-templates').append(newTemplate);
        });
        
        // Remove notification template
        $(document).on('click', '.remove-notification', function() {
            if (confirm('Are you sure you want to remove this notification template?')) {
                const id = $(this).data('id');
                $(`div[data-id="${id}"]`).remove();
                
                // Add placeholder if no templates left
                if ($('.notification-template').length === 0) {
                    $('#notification-templates').html('<div class="no-notifications" style="text-align: center; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 20px;"><p>No notification templates configured yet. Click the button below to add your first template.</p></div>');
                }
            }
        });
        
        // Add initial template if none exist
        if ($('.notification-template').length === 0 && $('.no-notifications').length === 0) {
            $('#add-notification').trigger('click');
        }
    });
    </script>
    <?php
}

// Register the post notifications tab
add_filter('gtaw_discord_settings_tabs', function($tabs) {
    $tabs['post-notifications'] = [
        'title' => 'Post Notifications',
        'callback' => 'gtaw_discord_post_notifications_tab'
    ];
    return $tabs;
});

/* ========= NOTIFICATION PROCESSING ========= */

/**
 * Process placeholders in notification templates
 *
 * @param string $template The notification template
 * @param WP_Post $post The post object
 * @return string Processed template with placeholders replaced
 */
function gtaw_process_post_notification_template($template, $post) {
    // Get post data
    $author = get_user_by('ID', $post->post_author);
    $author_name = $author ? $author->display_name : 'Unknown';
    
    // Get post type
    $post_type_obj = get_post_type_object($post->post_type);
    $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;
    
    // Get excerpt
    $excerpt = has_excerpt($post->ID) ? get_the_excerpt($post) : wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 30);
    
    // Get categories and tags
    $categories = '';
    $tags = '';
    
    if (is_object_in_taxonomy($post->post_type, 'category')) {
        $post_categories = get_the_category($post->ID);
        if (!empty($post_categories)) {
            $cat_names = array_map(function($cat) {
                return $cat->name;
            }, $post_categories);
            $categories = implode(', ', $cat_names);
        }
    }
    
    if (is_object_in_taxonomy($post->post_type, 'post_tag')) {
        $post_tags = get_the_tags($post->ID);
        if (!empty($post_tags)) {
            $tag_names = array_map(function($tag) {
                return $tag->name;
            }, $post_tags);
            $tags = implode(', ', $tag_names);
        }
    }
    
    // Replace placeholders
    $replacements = [
        '[post_title]' => $post->post_title,
        '[post_excerpt]' => $excerpt,
        '[post_content]' => wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 100),
        '[post_author]' => $author_name,
        '[post_date]' => get_the_date('', $post),
        '[post_url]' => get_permalink($post),
        '[post_type_label]' => $post_type_label,
        '[categories]' => $categories,
        '[tags]' => $tags,
    ];
    
    $processed_template = str_replace(array_keys($replacements), array_values($replacements), $template);
    
    return $processed_template;
}

/**
 * Send Discord notification when a post is published
 *
 * @param int $post_id The post ID
 * @param WP_Post $post The post object
 * @param bool $update Whether this is an update
 */
function gtaw_discord_send_post_notification($post_id, $post, $update) {
    // Skip if this is a revision
    if (wp_is_post_revision($post_id)) {
        return;
    }
    
    // Skip if post is not published
    if ($post->post_status !== 'publish') {
        return;
    }
    
    // Skip if this is an update to an already published post
    if ($update) {
        $old_status = get_post_meta($post_id, '_gtaw_discord_notified', true);
        if ($old_status === 'published') {
            return;
        }
    }
    
    // Get notification settings
    $notifications = get_option('gtaw_discord_post_notifications', []);
    if (empty($notifications)) {
        return;
    }
    
    // Find matching notification for this post type
    $matching_notifications = [];
    foreach ($notifications as $notification) {
        if ($notification['post_type'] === $post->post_type && isset($notification['enabled']) && $notification['enabled']) {
            $matching_notifications[] = $notification;
        }
    }
    
    if (empty($matching_notifications)) {
        return;
    }
    
    // Process each matching notification
    foreach ($matching_notifications as $notification) {
        // Skip if missing required fields
        if (empty($notification['channel_id']) || empty($notification['template'])) {
            continue;
        }
        
        // Process template
        $message = gtaw_process_post_notification_template($notification['template'], $post);
        
        // Prepare embeds if needed
        $embeds = [];
        
        // Add featured image if enabled
        if (isset($notification['include_thumbnail']) && $notification['include_thumbnail'] && has_post_thumbnail($post_id)) {
            $thumbnail_url = get_the_post_thumbnail_url($post_id, 'large');
            if ($thumbnail_url) {
                $embeds[] = [
                    'image' => [
                        'url' => $thumbnail_url
                    ]
                ];
            }
        }
        
        // Include custom fields if enabled
        if (isset($notification['include_fields']) && $notification['include_fields']) {
            $custom_fields = get_post_custom($post_id);
            if (!empty($custom_fields)) {
                $fields = [];
                $public_fields = [
                    'price', 'duration', 'location', 'event_date', 'contact', 'phone', 
                    'email', 'website', 'capacity', 'instructor', 'requirements'
                ];
                
                // Filter to public fields only
                foreach ($public_fields as $field_key) {
                    if (isset($custom_fields[$field_key]) && !empty($custom_fields[$field_key][0])) {
                        $fields[] = [
                            'name' => ucfirst(str_replace('_', ' ', $field_key)),
                            'value' => $custom_fields[$field_key][0],
                            'inline' => true
                        ];
                    }
                }
                
                if (!empty($fields)) {
                    $embeds[] = [
                        'title' => $post->post_title,
                        'url' => get_permalink($post_id),
                        'fields' => $fields
                    ];
                }
            }
        }
        
        // Send the notification
        $result = gtaw_discord_send_message($notification['channel_id'], $message, $embeds);
        
        if (is_wp_error($result)) {
            gtaw_add_log('discord', 'Error', "Failed to send post notification for post ID {$post_id}: " . $result->get_error_message(), 'error');
        } else {
            gtaw_add_log('discord', 'Notification', "Sent post notification for post ID {$post_id} to channel {$notification['channel_id']}", 'success');
            update_post_meta($post_id, '_gtaw_discord_notified', 'published');
        }
    }
}
add_action('wp_insert_post', 'gtaw_discord_send_post_notification', 10, 3);

/**
 * Reset notification status when post is updated from non-published to published
 *
 * @param string $new_status New post status
 * @param string $old_status Old post status
 * @param WP_Post $post Post object
 */
function gtaw_reset_discord_notification_status($new_status, $old_status, $post) {
    if ($old_status !== 'publish' && $new_status === 'publish') {
        delete_post_meta($post->ID, '_gtaw_discord_notified');
    }
}
add_action('transition_post_status', 'gtaw_reset_discord_notification_status', 10, 3);

/**
 * Add test button to post edit screen
 *
 * @param WP_Post $post Post object
 */
function gtaw_add_discord_notification_test_button($post) {
    // Only add button if post is published
    if ($post->post_status !== 'publish') {
        return;
    }
    
    // Check if this post type has notifications enabled
    $notifications = get_option('gtaw_discord_post_notifications', []);
    $has_notifications = false;
    
    foreach ($notifications as $notification) {
        if ($notification['post_type'] === $post->post_type && isset($notification['enabled']) && $notification['enabled']) {
            $has_notifications = true;
            break;
        }
    }
    
    if (!$has_notifications) {
        return;
    }
    
    // Add the test button
    $nonce = wp_create_nonce('gtaw_test_discord_notification');
    ?>
    <div class="misc-pub-section">
        <span class="dashicons dashicons-discord" style="color: #5865F2;"></span>
        Discord Notification:
        <a href="#" id="test-discord-notification" class="button button-small" data-nonce="<?php echo $nonce; ?>" data-post-id="<?php echo $post->ID; ?>">Test Send</a>
        <span id="discord-notification-status" style="margin-left: 10px;"></span>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-discord-notification').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $status = $('#discord-notification-status');
            
            $button.prop('disabled', true);
            $status.html('<span style="color: blue;">Sending test notification...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gtaw_test_discord_notification',
                    post_id: $button.data('post-id'),
                    nonce: $button.data('nonce')
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">' + response.data + '</span>');
                    } else {
                        $status.html('<span style="color: red;">Error: ' + response.data + '</span>');
                    }
                    $button.prop('disabled', false);
                },
                error: function() {
                    $status.html('<span style="color: red;">Connection error</span>');
                    $button.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}
add_action('post_submitbox_misc_actions', 'gtaw_add_discord_notification_test_button');

/**
 * Handle test notification AJAX request
 */
function gtaw_test_discord_notification_callback() {
    // Check permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gtaw_test_discord_notification')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check post ID
    if (empty($_POST['post_id'])) {
        wp_send_json_error('Missing post ID');
    }
    
    $post_id = intval($_POST['post_id']);
    $post = get_post($post_id);
    
    if (!$post) {
        wp_send_json_error('Invalid post ID');
    }
    
    // Temporarily remove the hook to avoid duplicate notification recording
    remove_action('wp_insert_post', 'gtaw_discord_send_post_notification', 10);
    
    // Get notification settings
    $notifications = get_option('gtaw_discord_post_notifications', []);
    if (empty($notifications)) {
        wp_send_json_error('No notification templates configured');
    }
    
    // Find matching notification for this post type
    $matching_notifications = [];
    foreach ($notifications as $notification) {
        if ($notification['post_type'] === $post->post_type && isset($notification['enabled']) && $notification['enabled']) {
            $matching_notifications[] = $notification;
        }
    }
    
    if (empty($matching_notifications)) {
        wp_send_json_error('No notification templates enabled for this post type');
    }
    
    // Process each matching notification
    $success_count = 0;
    $error_messages = [];
    
    foreach ($matching_notifications as $notification) {
        // Skip if missing required fields
        if (empty($notification['channel_id']) || empty($notification['template'])) {
            $error_messages[] = 'Missing channel ID or template in notification configuration';
            continue;
        }
        
        // Process template
        $message = gtaw_process_post_notification_template($notification['template'], $post);
        
        // Prepare embeds if needed
        $embeds = [];
        
        // Add featured image if enabled
        if (isset($notification['include_thumbnail']) && $notification['include_thumbnail'] && has_post_thumbnail($post_id)) {
            $thumbnail_url = get_the_post_thumbnail_url($post_id, 'large');
            if ($thumbnail_url) {
                $embeds[] = [
                    'image' => [
                        'url' => $thumbnail_url
                    ]
                ];
            }
        }
        
        // Include custom fields if enabled
        if (isset($notification['include_fields']) && $notification['include_fields']) {
            $custom_fields = get_post_custom($post_id);
            if (!empty($custom_fields)) {
                $fields = [];
                $public_fields = [
                    'price', 'duration', 'location', 'event_date', 'contact', 'phone', 
                    'email', 'website', 'capacity', 'instructor', 'requirements'
                ];
                
                // Filter to public fields only
                foreach ($public_fields as $field_key) {
                    if (isset($custom_fields[$field_key]) && !empty($custom_fields[$field_key][0])) {
                        $fields[] = [
                            'name' => ucfirst(str_replace('_', ' ', $field_key)),
                            'value' => $custom_fields[$field_key][0],
                            'inline' => true
                        ];
                    }
                }
                
                if (!empty($fields)) {
                    $embeds[] = [
                        'title' => $post->post_title,
                        'url' => get_permalink($post_id),
                        'fields' => $fields
                    ];
                }
            }
        }
        
        // Send the notification
        $result = gtaw_discord_send_message($notification['channel_id'], $message, $embeds);
        
        if (is_wp_error($result)) {
            $error_messages[] = $result->get_error_message();
            gtaw_add_log('discord', 'Error', "Failed to send test notification for post ID {$post_id}: " . $result->get_error_message(), 'error');
        } else {
            $success_count++;
            gtaw_add_log('discord', 'Notification', "Sent test notification for post ID {$post_id} to channel {$notification['channel_id']}", 'success');
        }
    }
    
    // Add the action back
    add_action('wp_insert_post', 'gtaw_discord_send_post_notification', 10, 3);
    
    // Send response
    if ($success_count > 0) {
        if (count($error_messages) > 0) {
            wp_send_json_success("Sent {$success_count} notifications with some errors: " . implode(", ", $error_messages));
        } else {
            wp_send_json_success("Successfully sent {$success_count} notifications!");
        }
    } else {
        wp_send_json_error("Failed to send notifications: " . implode(", ", $error_messages));
    }
}
add_action('wp_ajax_gtaw_test_discord_notification', 'gtaw_test_discord_notification_callback');