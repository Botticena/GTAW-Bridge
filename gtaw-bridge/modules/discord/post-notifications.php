<?php
defined('ABSPATH') or exit;

/* ========= DISCORD POST NOTIFICATIONS MODULE ========= */
/*
 * This module adds support for rich Discord embed notifications when new posts are published:
 * - Customizable embed structure per post type
 * - Different Discord channels per post type
 * - Role mentions for notifications
 * - Flexible content options
 * - Post type-specific fields
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
        if (!isset($notification['post_type']) || !isset($notification['channel_id'])) {
            continue;
        }
        
        $sanitized[$id] = [
            'post_type' => sanitize_text_field($notification['post_type']),
            'channel_id' => sanitize_text_field($notification['channel_id']),
            'role_id' => isset($notification['role_id']) ? sanitize_text_field($notification['role_id']) : '',
            'role_enabled' => isset($notification['role_enabled']) ? (bool) $notification['role_enabled'] : false,
            'title' => isset($notification['title']) ? sanitize_text_field($notification['title']) : 'New [post_type_label]: [post_title]',
            'color' => isset($notification['color']) ? sanitize_text_field($notification['color']) : '#5865F2',
            'description_type' => isset($notification['description_type']) ? sanitize_text_field($notification['description_type']) : 'excerpt',
            'include_thumbnail' => isset($notification['include_thumbnail']) ? (bool) $notification['include_thumbnail'] : true,
            'enabled' => isset($notification['enabled']) ? (bool) $notification['enabled'] : true,
            // Field options
            'show_category' => isset($notification['show_category']) ? (bool) $notification['show_category'] : true,
            'show_tags' => isset($notification['show_tags']) ? (bool) $notification['show_tags'] : true,
            'show_author' => isset($notification['show_author']) ? (bool) $notification['show_author'] : true,
            'show_date' => isset($notification['show_date']) ? (bool) $notification['show_date'] : true,
            // Product-specific fields
            'show_price' => isset($notification['show_price']) ? (bool) $notification['show_price'] : true,
            'show_sku' => isset($notification['show_sku']) ? (bool) $notification['show_sku'] : false,
            'show_stock' => isset($notification['show_stock']) ? (bool) $notification['show_stock'] : true,
            // Categories filter
            'category_filter' => isset($notification['category_filter']) ? array_map('intval', $notification['category_filter']) : [],
            'use_category_filter' => isset($notification['use_category_filter']) ? (bool) $notification['use_category_filter'] : false,
        ];
    }
    
    return $sanitized;
}

// Enqueue Discord post notification assets
function gtaw_enqueue_discord_post_notification_assets($hook) {
    // Only load on Discord post notification settings page
    if ($hook !== 'gtaw-bridge_page_gtaw-discord' || !isset($_GET['tab']) || $_GET['tab'] !== 'post-notifications') {
        return;
    }
    
    // Enqueue the dedicated CSS and JS
    wp_enqueue_style('gtaw-discord-styles', GTAW_BRIDGE_PLUGIN_URL . 'assets/css/gtaw-discord.css', array(), GTAW_BRIDGE_VERSION);
    wp_enqueue_script('gtaw-discord-post-notifications', GTAW_BRIDGE_PLUGIN_URL . 'assets/js/gtaw-discord-post-notifications.js', array('jquery'), GTAW_BRIDGE_VERSION, true);
    
    // Pass data to script
    wp_localize_script('gtaw-discord-post-notifications', 'gtaw_discord_post_notifications', array(
        'nonce' => wp_create_nonce('gtaw_discord_post_notification_nonce'),
        'image_url' => plugins_url('assets/img/post-preview.webp', dirname(dirname(__FILE__)))
    ));
}
add_action('admin_enqueue_scripts', 'gtaw_enqueue_discord_post_notification_assets');

// Add Post Notifications tab to Discord settings
function gtaw_discord_post_notifications_tab() {
    // Only include specific post types
    $post_types = [
        'post' => get_post_type_object('post'),
        'page' => get_post_type_object('page'),
        'product' => get_post_type_object('product'),
        'attachment' => get_post_type_object('attachment')
    ];
    
    // Filter out post types that don't exist (e.g., if WooCommerce isn't active)
    $post_types = array_filter($post_types);
    
    // Get current notification settings
    $notifications = get_option('gtaw_discord_post_notifications', []);
    ?>
    <form method="post" action="options.php" id="post-notification-form">
        <?php 
            settings_fields('gtaw_discord_post_notification_group');
            do_settings_sections('gtaw_discord_post_notification_group');
        ?>
        
        <div class="post-notifications-intro" style="margin-bottom: 20px; background: #f0f0f0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
            <h3>Post Type Notifications</h3>
            <p>Configure Discord notifications to be sent when new posts of specific types are published.</p>
            <p>Each notification can be sent to a different Discord channel with customized embed appearance.</p>
        </div>
        
        <div class="notification-templates" id="notification-templates">
            <?php if (empty($notifications)): ?>
                <!-- No templates yet, will be added via JS -->
                <div class="no-notifications">
                    <p>No notification templates configured yet. Click the button below to add your first template.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $id => $notification): ?>
                    <?php 
                    // Get post type object
                    $post_type = isset($post_types[$notification['post_type']]) ? $post_types[$notification['post_type']] : null;
                    // Skip if post type no longer exists
                    if (!$post_type) continue;
                    $type_label = $post_type->labels->singular_name;
                    ?>
                    <div class="notification-template" data-id="<?php echo esc_attr($id); ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h3 style="margin: 0;">
                                <?php echo esc_html($type_label); ?> Notification
                            </h3>
                            <div>
                                <button type="button" class="button remove-notification" data-id="<?php echo esc_attr($id); ?>">Remove</button>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <!-- Left Column - Settings -->
                            <div class="notification-settings">
                                <!-- The rest of the form remains the same, just using our CSS classes -->
                                <!-- General Settings -->
                                <div style="margin-bottom: 15px;">
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Post Type</label>
                                        <select name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][post_type]" class="post-type-selector" data-id="<?php echo esc_attr($id); ?>" style="width: 100%;">
                                            <?php foreach ($post_types as $type): ?>
                                                <option value="<?php echo esc_attr($type->name); ?>" <?php selected($notification['post_type'], $type->name); ?>>
                                                    <?php echo esc_html($type->labels->singular_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Discord Channel ID</label>
                                        <input type="text" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][channel_id]" value="<?php echo esc_attr($notification['channel_id']); ?>" style="width: 100%;" placeholder="Enter Discord channel ID">
                                    </div>
                                    
                                    <div style="margin-bottom: 10px; display: flex; align-items: center;">
                                        <label style="margin-right: 10px;"><input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][role_enabled]" value="1" <?php checked(isset($notification['role_enabled']) ? $notification['role_enabled'] : false); ?>> Mention Role</label>
                                        <input type="text" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][role_id]" value="<?php echo esc_attr(isset($notification['role_id']) ? $notification['role_id'] : ''); ?>" placeholder="Role ID" style="width: 60%;">
                                    </div>
                                </div>
                                
                                <!-- Category Filter (for post and product) -->
                                <?php if ($notification['post_type'] === 'post' || $notification['post_type'] === 'product'): ?>
                                    <div class="category-filter-container">
                                        <div class="category-filter">
                                            <label style="display: flex; align-items: center; margin-bottom: 10px;">
                                                <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][use_category_filter]" value="1" <?php checked(isset($notification['use_category_filter']) ? $notification['use_category_filter'] : false); ?>>
                                                <span style="margin-left: 5px;"><strong>Only notify for specific categories</strong></span>
                                            </label>
                                            
                                            <div class="category-selector">
                                                <?php 
                                                // Get the appropriate taxonomy based on post type
                                                $taxonomy = $notification['post_type'] === 'product' ? 'product_cat' : 'category';
                                                $categories = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                                                
                                                if (!is_wp_error($categories) && !empty($categories)):
                                                    $selected_cats = isset($notification['category_filter']) ? (array)$notification['category_filter'] : [];
                                                    foreach ($categories as $category):
                                                ?>
                                                    <label style="display: block; margin-bottom: 5px;">
                                                        <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][category_filter][]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked(in_array($category->term_id, $selected_cats)); ?>>
                                                        <?php echo esc_html($category->name); ?>
                                                    </label>
                                                <?php 
                                                    endforeach;
                                                else:
                                                ?>
                                                    <p>No categories found.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Embed Appearance -->
                                <div style="margin-bottom: 15px;">
                                    <h4 style="margin-top: 0;">Embed Appearance</h4>
                                    
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Embed Title</label>
                                        <input type="text" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][title]" value="<?php echo esc_attr(isset($notification['title']) ? $notification['title'] : 'New [post_type_label]: [post_title]'); ?>" style="width: 100%;">
                                    </div>
                                    
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Embed Color</label>
                                        <input type="color" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][color]" value="<?php echo esc_attr(isset($notification['color']) ? $notification['color'] : '#5865F2'); ?>" class="color-picker" data-id="<?php echo esc_attr($id); ?>">
                                    </div>
                                    
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Description Content</label>
                                        <select name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][description_type]" class="description-type-selector" data-id="<?php echo esc_attr($id); ?>" style="width: 100%;">
                                            <option value="excerpt" <?php selected(isset($notification['description_type']) ? $notification['description_type'] : 'excerpt', 'excerpt'); ?>>Post Excerpt</option>
                                            <option value="content" <?php selected(isset($notification['description_type']) ? $notification['description_type'] : 'excerpt', 'content'); ?>>Short Content</option>
                                            <option value="both" <?php selected(isset($notification['description_type']) ? $notification['description_type'] : 'excerpt', 'both'); ?>>Both Excerpt and Content</option>
                                            <option value="none" <?php selected(isset($notification['description_type']) ? $notification['description_type'] : 'excerpt', 'none'); ?>>No Description</option>
                                        </select>
                                    </div>
                                    
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: flex; align-items: center;">
                                            <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][include_thumbnail]" value="1" class="thumbnail-toggle" data-id="<?php echo esc_attr($id); ?>" <?php checked(isset($notification['include_thumbnail']) ? $notification['include_thumbnail'] : true); ?>>
                                            <span style="margin-left: 5px;">Include Featured Image</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Embed Fields -->
                                <div style="margin-bottom: 15px;">
                                    <h4 style="margin-top: 0;">Embed Fields</h4>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <label style="display: flex; align-items: center;">
                                            <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][show_category]" value="1" class="field-toggle" data-field="category" data-id="<?php echo esc_attr($id); ?>" <?php checked(isset($notification['show_category']) ? $notification['show_category'] : true); ?>>
                                            <span style="margin-left: 5px;">Categories</span>
                                        </label>
                                        
                                        <label style="display: flex; align-items: center;">
                                            <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][show_tags]" value="1" class="field-toggle" data-field="tags" data-id="<?php echo esc_attr($id); ?>" <?php checked(isset($notification['show_tags']) ? $notification['show_tags'] : true); ?>>
                                            <span style="margin-left: 5px;">Tags</span>
                                        </label>
                                        
                                        <label style="display: flex; align-items: center;">
                                            <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][show_author]" value="1" class="field-toggle" data-field="author" data-id="<?php echo esc_attr($id); ?>" <?php checked(isset($notification['show_author']) ? $notification['show_author'] : true); ?>>
                                            <span style="margin-left: 5px;">Author</span>
                                        </label>
                                        
                                        <label style="display: flex; align-items: center;">
                                            <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][show_date]" value="1" class="field-toggle" data-field="date" data-id="<?php echo esc_attr($id); ?>" <?php checked(isset($notification['show_date']) ? $notification['show_date'] : true); ?>>
                                            <span style="margin-left: 5px;">Publication Date</span>
                                        </label>
                                    </div>
                                    
                                    <?php if ($notification['post_type'] === 'product'): ?>
                                        <div class="product-fields-container">
                                            <div style="margin-top: 10px; border-top: 1px solid #ddd; padding-top: 10px;">
                                                <h5 style="margin-top: 0;">Product Fields</h5>
                                                
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                                    <label style="display: flex; align-items: center;">
                                                        <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][show_price]" value="1" class="field-toggle" data-field="price" data-id="<?php echo esc_attr($id); ?>" <?php checked(isset($notification['show_price']) ? $notification['show_price'] : true); ?>>
                                                        <span style="margin-left: 5px;">Price</span>
                                                    </label>
                                                    
                                                    <label style="display: flex; align-items: center;">
                                                        <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][show_sku]" value="1" class="field-toggle" data-field="sku" data-id="<?php echo esc_attr($id); ?>" <?php checked(isset($notification['show_sku']) ? $notification['show_sku'] : false); ?>>
                                                        <span style="margin-left: 5px;">SKU</span>
                                                    </label>
                                                    
                                                    <label style="display: flex; align-items: center;">
                                                        <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][show_stock]" value="1" class="field-toggle" data-field="stock" data-id="<?php echo esc_attr($id); ?>" <?php checked(isset($notification['show_stock']) ? $notification['show_stock'] : true); ?>>
                                                        <span style="margin-left: 5px;">Stock Status</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Enable/Disable Toggle -->
                                <div style="margin-top: 20px;">
                                    <label style="display: flex; align-items: center; font-weight: bold;">
                                        <input type="checkbox" name="gtaw_discord_post_notifications[<?php echo esc_attr($id); ?>][enabled]" value="1" <?php checked(isset($notification['enabled']) ? $notification['enabled'] : true); ?>>
                                        <span style="margin-left: 5px;">Enable Notification</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Right Column - Preview -->
                            <div class="notification-preview">
                                <h4 style="margin-top: 0;">Preview</h4>
                                <div class="discord-embed-preview" style="border-left-color: <?php echo esc_attr(isset($notification['color']) ? $notification['color'] : '#5865F2'); ?>;">
                                    <!-- Preview will be populated by JavaScript -->
                                </div>
                            </div>
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
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Left Column - Settings -->
                <div class="notification-settings">
                    <!-- General Settings -->
                    <div style="margin-bottom: 15px;">
                        <div style="margin-bottom: 10px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Post Type</label>
                            <select name="gtaw_discord_post_notifications[{id}][post_type]" class="post-type-selector" data-id="{id}" style="width: 100%;">
                                <?php foreach ($post_types as $type): ?>
                                    <option value="<?php echo esc_attr($type->name); ?>">
                                        <?php echo esc_html($type->labels->singular_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Discord Channel ID</label>
                            <input type="text" name="gtaw_discord_post_notifications[{id}][channel_id]" value="" style="width: 100%;" placeholder="Enter Discord channel ID">
                        </div>
                        
                        <div style="margin-bottom: 10px; display: flex; align-items: center;">
                            <label style="margin-right: 10px;"><input type="checkbox" name="gtaw_discord_post_notifications[{id}][role_enabled]" value="1"> Mention Role</label>
                            <input type="text" name="gtaw_discord_post_notifications[{id}][role_id]" value="" placeholder="Role ID" style="width: 60%;">
                        </div>
                    </div>
                    
                    <!-- Category Filter Container - Will be populated by JS -->
                    <div class="category-filter-container" style="margin-bottom: 15px;"></div>
                    
                    <!-- Embed Appearance -->
                    <div style="margin-bottom: 15px;">
                        <h4 style="margin-top: 0;">Embed Appearance</h4>
                        
                        <div style="margin-bottom: 10px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Embed Title</label>
                            <input type="text" name="gtaw_discord_post_notifications[{id}][title]" value="New [post_type_label]: [post_title]" style="width: 100%;">
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Embed Color</label>
                            <input type="color" name="gtaw_discord_post_notifications[{id}][color]" value="#5865F2" class="color-picker" data-id="{id}">
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Description Content</label>
                            <select name="gtaw_discord_post_notifications[{id}][description_type]" class="description-type-selector" data-id="{id}" style="width: 100%;">
                                <option value="excerpt">Post Excerpt</option>
                                <option value="content">Short Content</option>
                                <option value="both">Both Excerpt and Content</option>
                                <option value="none">No Description</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label style="display: flex; align-items: center;">
                                <input type="checkbox" name="gtaw_discord_post_notifications[{id}][include_thumbnail]" value="1" class="thumbnail-toggle" data-id="{id}" checked>
                                <span style="margin-left: 5px;">Include Featured Image</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Embed Fields -->
                    <div style="margin-bottom: 15px;">
                        <h4 style="margin-top: 0;">Embed Fields</h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <label style="display: flex; align-items: center;">
                                <input type="checkbox" name="gtaw_discord_post_notifications[{id}][show_category]" value="1" class="field-toggle" data-field="category" data-id="{id}" checked>
                                <span style="margin-left: 5px;">Categories</span>
                            </label>
                            
                            <label style="display: flex; align-items: center;">
                                <input type="checkbox" name="gtaw_discord_post_notifications[{id}][show_tags]" value="1" class="field-toggle" data-field="tags" data-id="{id}" checked>
                                <span style="margin-left: 5px;">Tags</span>
                            </label>
                            
                            <label style="display: flex; align-items: center;">
                                <input type="checkbox" name="gtaw_discord_post_notifications[{id}][show_author]" value="1" class="field-toggle" data-field="author" data-id="{id}" checked>
                                <span style="margin-left: 5px;">Author</span>
                            </label>
                            
                            <label style="display: flex; align-items: center;">
                                <input type="checkbox" name="gtaw_discord_post_notifications[{id}][show_date]" value="1" class="field-toggle" data-field="date" data-id="{id}" checked>
                                <span style="margin-left: 5px;">Publication Date</span>
                            </label>
                        </div>
                        
                        <!-- Product-specific fields container - Will be populated by JS -->
                        <div class="product-fields-container"></div>
                    </div>
                    
                    <!-- Enable/Disable Toggle -->
                    <div style="margin-top: 20px;">
                        <label style="display: flex; align-items: center; font-weight: bold;">
                            <input type="checkbox" name="gtaw_discord_post_notifications[{id}][enabled]" value="1" checked>
                            <span style="margin-left: 5px;">Enable Notification</span>
                        </label>
                    </div>
                </div>
                
                <!-- Right Column - Preview -->
                <div class="notification-preview">
                    <h4 style="margin-top: 0;">Preview</h4>
                    
                    <div class="discord-embed-preview" style="border-left: 4px solid #5865F2; background: #36393f; color: #fff; padding: 12px 15px; border-radius: 0 3px 3px 0; font-family: Arial, sans-serif;">
                        <!-- Title -->
                        <div style="font-weight: bold; margin-bottom: 8px; font-size: 16px; color: white;">
                            New Post: Sample Post Title
                        </div>
                        
                        <!-- Description -->
                        <div style="margin-bottom: 10px; font-size: 14px; color: #dcddde;">
                            <p>This is a sample post excerpt that provides a brief summary of the content.</p>
                        </div>
                        
                        <!-- Image -->
                        <div style="margin: 10px 0;">
                            <img src="<?php echo esc_url(plugins_url('/assets/img/post-preview.webp', dirname(dirname(__FILE__)))); ?>" style="max-width: 100%; height: auto; border-radius: 3px; max-height: 300px;" alt="Featured Image">
                        </div>
                        
                        <!-- Fields -->
                        <div style="margin-top: 10px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 14px;">
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: bold; color: #dcddde;">Categories</div>
                                <div>Sample Category</div>
                            </div>
                            
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: bold; color: #dcddde;">Tags</div>
                                <div>sample, preview, test</div>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div style="font-size: 12px; color: #dcddde; margin-top: 10px;">
                            By: Sample Author • <?php echo date('F j, Y'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
    
    <!-- Template for Category Filter -->
    <template id="category-filter-template">
        <div class="category-filter" style="border: 1px solid #ddd; padding: 10px; background: #f5f5f5;">
            <label style="display: flex; align-items: center; margin-bottom: 10px;">
                <input type="checkbox" name="gtaw_discord_post_notifications[{id}][use_category_filter]" value="1">
                <span style="margin-left: 5px;"><strong>Only notify for specific categories</strong></span>
            </label>
            
            <div class="category-selector" style="max-height: 200px; overflow-y: auto; padding: 10px; background: white; border: 1px solid #ddd;">
                <p>Select a post type to see available categories.</p>
            </div>
        </div>
    </template>
    
    <!-- Template for Product Fields -->
    <template id="product-fields-template">
        <div style="margin-top: 10px; border-top: 1px solid #ddd; padding-top: 10px;">
            <h5 style="margin-top: 0;">Product Fields</h5>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <label style="display: flex; align-items: center;">
                    <input type="checkbox" name="gtaw_discord_post_notifications[{id}][show_price]" value="1" class="field-toggle" data-field="price" data-id="{id}" checked>
                    <span style="margin-left: 5px;">Price</span>
                </label>
                
                <label style="display: flex; align-items: center;">
                    <input type="checkbox" name="gtaw_discord_post_notifications[{id}][show_sku]" value="1" class="field-toggle" data-field="sku" data-id="{id}">
                    <span style="margin-left: 5px;">SKU</span>
                </label>
                
                <label style="display: flex; align-items: center;">
                    <input type="checkbox" name="gtaw_discord_post_notifications[{id}][show_stock]" value="1" class="field-toggle" data-field="stock" data-id="{id}" checked>
                    <span style="margin-left: 5px;">Stock Status</span>
                </label>
            </div>
        </div>
    </template>
    
    <script>
    jQuery(document).ready(function($) {
        // Generate unique ID for templates
        function generateUniqueId() {
            return 'n' + Date.now() + Math.floor(Math.random() * 1000);
        }
        
        // Function to update the preview based on settings
        function updatePreview(id) {
            const $container = $(`div[data-id="${id}"]`);
            const postType = $container.find(`select[name="gtaw_discord_post_notifications[${id}][post_type]"]`).val();
            const title = $container.find(`input[name="gtaw_discord_post_notifications[${id}][title]"]`).val();
            const color = $container.find(`input[name="gtaw_discord_post_notifications[${id}][color]"]`).val();
            const descType = $container.find(`select[name="gtaw_discord_post_notifications[${id}][description_type]"]`).val();
            const includeThumbnail = $container.find(`input[name="gtaw_discord_post_notifications[${id}][include_thumbnail]"]`).prop('checked');
            
            // Update embed color
            $container.find('.discord-embed-preview').css('border-left-color', color);
            
            // Update title
            let postTypeLabel = 'Post';
            if (postType === 'product') postTypeLabel = 'Product';
            if (postType === 'page') postTypeLabel = 'Page';
            if (postType === 'attachment') postTypeLabel = 'Media';
            
            const processedTitle = title.replace('[post_type_label]', postTypeLabel).replace('[post_title]', 'Sample Post Title');
            $container.find('.discord-embed-preview .title').text(processedTitle);
            
            // Update description
            const $description = $container.find('.discord-embed-preview .description');
            if (descType === 'none') {
                $description.hide();
            } else {
                $description.show();
                $description.html('');
                
                if (descType === 'excerpt' || descType === 'both') {
                    $description.append('<p>This is a sample post excerpt that provides a brief summary of the content.</p>');
                }
                
                if (descType === 'content' || descType === 'both') {
                    $description.append('<p>This is a sample of the post content, truncated to a reasonable length for Discord embedding...</p>');
                }
            }
            
            // Update thumbnail visibility
            if (includeThumbnail) {
                $container.find('.discord-embed-preview .thumbnail').show();
            } else {
                $container.find('.discord-embed-preview .thumbnail').hide();
            }
            
            // Update fields visibility based on toggles
            $container.find('.field-toggle').each(function() {
                const field = $(this).data('field');
                const checked = $(this).prop('checked');
                $container.find(`.discord-embed-preview .field-${field}`).toggle(checked);
            });
            
            // Update footer visibility
            const showAuthor = $container.find(`input[name="gtaw_discord_post_notifications[${id}][show_author]"]`).prop('checked');
            const showDate = $container.find(`input[name="gtaw_discord_post_notifications[${id}][show_date]"]`).prop('checked');
            
            if (showAuthor || showDate) {
                $container.find('.discord-embed-preview .footer').show();
                
                let footerText = [];
                if (showAuthor) footerText.push('By: Sample Author');
                if (showDate) footerText.push(new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));
                
                $container.find('.discord-embed-preview .footer').text(footerText.join(' • '));
            } else {
                $container.find('.discord-embed-preview .footer').hide();
            }
        }
        
        // Function to load categories for a post type
        function loadCategories(id, postType) {
            const $container = $(`div[data-id="${id}"]`);
            const $selector = $container.find('.category-selector');
            
            // Skip if there's no category selector
            if ($selector.length === 0) return;
            
            // Determine taxonomy based on post type
            let taxonomy = 'category';
            if (postType === 'product') taxonomy = 'product_cat';
            
            // Skip for post types without categories
            if (postType === 'attachment' || postType === 'page') {
                $container.find('.category-filter').hide();
                return;
            } else {
                $container.find('.category-filter').show();
            }
            
            // Show loading indicator
            $selector.html('<p>Loading categories...</p>');
            
            // Fetch categories via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gtaw_get_post_categories',
                    post_type: postType,
                    taxonomy: taxonomy,
                    nonce: '<?php echo $nonce; ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // No categories
                        if (response.data.length === 0) {
                            $selector.html('<p>No categories found for this post type.</p>');
                            return;
                        }
                        
                        // Populate category checkboxes
                        let html = '';
                        response.data.forEach(function(category) {
                            html += `
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="gtaw_discord_post_notifications[${id}][category_filter][]" value="${category.term_id}">
                                    ${category.name}
                                </label>
                            `;
                        });
                        
                        $selector.html(html);
                    } else {
                        $selector.html('<p>Error loading categories.</p>');
                    }
                },
                error: function() {
                    $selector.html('<p>Error connecting to server.</p>');
                }
            });
        }
        
        // Handle post type change
        $(document).on('change', '.post-type-selector', function() {
            const id = $(this).data('id');
            const postType = $(this).val();
            const $container = $(`div[data-id="${id}"]`);
            
            // Update preview post type
            updatePreview(id);
            
            // Add or remove product fields
            const $productFields = $container.find('.product-fields-container');
            if (postType === 'product') {
                // If product fields don't exist, add them
                if ($productFields.children().length === 0) {
                    const template = $('#product-fields-template').html();
                    $productFields.html(template.replace(/{id}/g, id));
                }
            } else {
                // Remove product fields for non-product post types
                $productFields.empty();
            }
            
            // Add category filter if it doesn't exist
            if (postType === 'post' || postType === 'product') {
                const $categoryFilter = $container.find('.category-filter-container');
                if ($categoryFilter.children().length === 0) {
                    const template = $('#category-filter-template').html();
                    $categoryFilter.html(template.replace(/{id}/g, id));
                    
                    // Load categories for this post type
                    loadCategories(id, postType);
                } else {
                    // Just reload categories
                    loadCategories(id, postType);
                }
            } else {
                // Remove category filter for post types without categories
                $container.find('.category-filter-container').empty();
            }
        });
        
        // Handle visual updates for toggles
        $(document).on('change', '.field-toggle, .thumbnail-toggle, input[name$="[show_author]"], input[name$="[show_date]"]', function() {
            const id = $(this).data('id');
            updatePreview(id);
        });
        
        // Handle description type change
        $(document).on('change', '.description-type-selector', function() {
            const id = $(this).data('id');
            updatePreview(id);
        });
        
        // Handle color picker change
        $(document).on('input', '.color-picker', function() {
            const id = $(this).data('id');
            const color = $(this).val();
            $(`div[data-id="${id}"] .discord-embed-preview`).css('border-left-color', color);
        });
        
        // Add new notification template
        $('#add-notification').on('click', function() {
            const template = $('#notification-template').html();
            const id = generateUniqueId();
            const newTemplate = template.replace(/{id}/g, id);
            
            $('.no-notifications').remove(); // Remove placeholder if exists
            $('#notification-templates').append(newTemplate);
            
            // Create better preview structure
            const $preview = $(`div[data-id="${id}"] .discord-embed-preview`);
            $preview.html(`
                <div class="title" style="font-weight: bold; margin-bottom: 8px; font-size: 16px; color: white;">New Post: Sample Post Title</div>
                <div class="description" style="margin-bottom: 10px; font-size: 14px; color: #dcddde;">
                    <p>This is a sample post excerpt that provides a brief summary of the content.</p>
                </div>
                <div class="thumbnail" style="margin: 10px 0;">
                    <img src="<?php echo esc_url(plugins_url('/assets/img/post-preview.webp', dirname(dirname(__FILE__)))); ?>" style="max-width: 100%; height: auto; border-radius: 3px; max-height: 300px;" alt="Featured Image">
                </div>
                <div class="fields" style="margin-top: 10px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 14px;">
                    <div class="field-category" style="margin-bottom: 6px;">
                        <div style="font-weight: bold; color: #dcddde;">Categories</div>
                        <div>Sample Category</div>
                    </div>
                    <div class="field-tags" style="margin-bottom: 6px;">
                        <div style="font-weight: bold; color: #dcddde;">Tags</div>
                        <div>sample, preview, test</div>
                    </div>
                </div>
                <div class="footer" style="font-size: 12px; color: #dcddde; margin-top: 10px;">
                    By: Sample Author • ${new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
                </div>
            `);
            
            // Trigger post type change to set up fields correctly
            $(`div[data-id="${id}"] .post-type-selector`).trigger('change');
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
        
        // Update all existing previews for better structure
        $('.notification-template').each(function() {
            const id = $(this).data('id');
            const $preview = $(this).find('.discord-embed-preview');
            
            // Enhanced preview structure
            $preview.html(`
                <div class="title" style="font-weight: bold; margin-bottom: 8px; font-size: 16px; color: white;">
                    ${$preview.find('div:first').text()}
                </div>
                <div class="description" style="margin-bottom: 10px; font-size: 14px; color: #dcddde;">
                    ${$preview.find('div:eq(1)').html() || '<p>This is a sample post excerpt that provides a brief summary of the content.</p>'}
                </div>
                <div class="thumbnail" style="margin: 10px 0;">
                    <img src="<?php echo esc_url(plugins_url('/assets/img/post-preview.webp', dirname(dirname(__FILE__)))); ?>" style="max-width: 100%; height: auto; border-radius: 3px; max-height: 300px;" alt="Featured Image">
                </div>
                <div class="fields" style="margin-top: 10px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 14px;">
                    <div class="field-category" style="margin-bottom: 6px;">
                        <div style="font-weight: bold; color: #dcddde;">Categories</div>
                        <div>Sample Category</div>
                    </div>
                    <div class="field-tags" style="margin-bottom: 6px;">
                        <div style="font-weight: bold; color: #dcddde;">Tags</div>
                        <div>sample, preview, test</div>
                    </div>
                    <div class="field-price" style="margin-bottom: 6px; display: none;">
                        <div style="font-weight: bold; color: #dcddde;">Price</div>
                        <div>$99.99</div>
                    </div>
                    <div class="field-sku" style="margin-bottom: 6px; display: none;">
                        <div style="font-weight: bold; color: #dcddde;">SKU</div>
                        <div>PRD12345</div>
                    </div>
                    <div class="field-stock" style="margin-bottom: 6px; display: none;">
                        <div style="font-weight: bold; color: #dcddde;">Stock</div>
                        <div>In Stock (10)</div>
                    </div>
                </div>
                <div class="footer" style="font-size: 12px; color: #dcddde; margin-top: 10px;">
                    By: Sample Author • ${new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
                </div>
            `);
            
            // Show/hide elements based on current settings
            updatePreview(id);
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

/**
 * AJAX handler to get categories for a post type
 */
function gtaw_get_post_categories_callback() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gtaw_discord_post_notification_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Get post type and taxonomy
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : 'category';
    
    // Get categories
    $categories = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false
    ]);
    
    if (is_wp_error($categories)) {
        wp_send_json_error($categories->get_error_message());
    }
    
    // Format categories for response
    $formatted_categories = [];
    foreach ($categories as $category) {
        $formatted_categories[] = [
            'term_id' => $category->term_id,
            'name' => $category->name
        ];
    }
    
    wp_send_json_success($formatted_categories);
}
add_action('wp_ajax_gtaw_get_post_categories', 'gtaw_get_post_categories_callback');

/* ========= NOTIFICATION PROCESSING ========= */

/**
 * Process post data for Discord embed
 *
 * @param WP_Post $post The post object
 * @param array $notification Notification settings
 * @return array Discord embed data
 */
function gtaw_prepare_post_notification_embed($post, $notification) {
    // Basic post data
    $post_type_obj = get_post_type_object($post->post_type);
    $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;
    $author = get_user_by('ID', $post->post_author);
    $author_name = $author ? $author->display_name : 'Unknown';
    $post_url = get_permalink($post);
    
    // Process title template
    $title = str_replace(
        ['[post_type_label]', '[post_title]'],
        [$post_type_label, $post->post_title],
        isset($notification['title']) ? $notification['title'] : 'New [post_type_label]: [post_title]'
    );
    
    // Prepare the embed structure
    $embed = [
        'title' => $title,
        'url' => $post_url,
        'color' => hexdec(ltrim(isset($notification['color']) ? $notification['color'] : '#5865F2', '#')),
        'fields' => []
    ];
    
    // Add description based on settings
    if (isset($notification['description_type']) && $notification['description_type'] !== 'none') {
        $description = '';
        
        if ($notification['description_type'] === 'excerpt' || $notification['description_type'] === 'both') {
            $excerpt = has_excerpt($post->ID) ? get_the_excerpt($post) : '';
            if (!empty($excerpt)) {
                $description .= $excerpt;
            }
        }
        
        if ($notification['description_type'] === 'content' || $notification['description_type'] === 'both') {
            $content = wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 100);
            if (!empty($content)) {
                if (!empty($description)) {
                    $description .= "\n\n";
                }
                $description .= $content;
            }
        }
        
        if (!empty($description)) {
            $embed['description'] = $description;
        }
    }
    
    // Add thumbnail if enabled and exists
    if (isset($notification['include_thumbnail']) && $notification['include_thumbnail']) {
        $image_url = '';
        
        if ($post->post_type === 'attachment') {
            // For media attachments, use the attachment URL directly
            $image_url = wp_get_attachment_url($post->ID);
        } else if (has_post_thumbnail($post->ID)) {
            // For regular posts, use the featured image
            $image_url = get_the_post_thumbnail_url($post->ID, 'large');
        }
        
        if (!empty($image_url)) {
            $embed['image'] = ['url' => $image_url];
        }
    }
    
    // Add categories if enabled
    if (isset($notification['show_category']) && $notification['show_category'] && is_object_in_taxonomy($post->post_type, 'category')) {
        $post_categories = get_the_category($post->ID);
        if (!empty($post_categories)) {
            $cat_names = array_map(function($cat) {
                return $cat->name;
            }, $post_categories);
            $categories = implode(', ', $cat_names);
            
            $embed['fields'][] = [
                'name' => 'Categories',
                'value' => $categories,
                'inline' => true
            ];
        }
    }
    
    // Add product categories for WooCommerce products
    if ($post->post_type === 'product' && isset($notification['show_category']) && $notification['show_category']) {
        $product_categories = get_the_terms($post->ID, 'product_cat');
        if (!empty($product_categories) && !is_wp_error($product_categories)) {
            $cat_names = array_map(function($cat) {
                return $cat->name;
            }, $product_categories);
            $categories = implode(', ', $cat_names);
            
            $embed['fields'][] = [
                'name' => 'Product Categories',
                'value' => $categories,
                'inline' => true
            ];
        }
    }
    
    // Add tags if enabled
    if (isset($notification['show_tags']) && $notification['show_tags'] && is_object_in_taxonomy($post->post_type, 'post_tag')) {
        $post_tags = get_the_tags($post->ID);
        if (!empty($post_tags)) {
            $tag_names = array_map(function($tag) {
                return $tag->name;
            }, $post_tags);
            $tags = implode(', ', $tag_names);
            
            $embed['fields'][] = [
                'name' => 'Tags',
                'value' => $tags,
                'inline' => true
            ];
        }
    }
    
    // Add product-specific fields
    if ($post->post_type === 'product') {
        $product = wc_get_product($post->ID);
        
        if ($product) {
            // Add price - properly formatted
            if (isset($notification['show_price']) && $notification['show_price']) {
                $price_html = $product->get_price_html();
                // Strip HTML tags but decode entities to ensure proper display
                $price = html_entity_decode(strip_tags($price_html));
                
                // Ensure currency is shown properly
                if (strpos($price, ' ') === false) {
                    $currency = get_woocommerce_currency();
                    $price .= ' ' . $currency;
                }
                
                $embed['fields'][] = [
                    'name' => 'Price',
                    'value' => $price,
                    'inline' => true
                ];
            }
            
            // Add SKU
            if (isset($notification['show_sku']) && $notification['show_sku']) {
                $sku = $product->get_sku();
                if (!empty($sku)) {
                    $embed['fields'][] = [
                        'name' => 'SKU',
                        'value' => $sku,
                        'inline' => true
                    ];
                }
            }
            
            // Add stock status with proper spacing
            if (isset($notification['show_stock']) && $notification['show_stock']) {
                $stock_status = $product->get_stock_status();
                
                // Format the stock status with proper spacing
                switch ($stock_status) {
                    case 'instock':
                        $stock_text = 'In Stock';
                        break;
                    case 'outofstock':
                        $stock_text = 'Out of Stock';
                        break;
                    case 'onbackorder':
                        $stock_text = 'On Backorder';
                        break;
                    default:
                        $stock_text = ucfirst($stock_status);
                        break;
                }
                
                // Add stock quantity if available
                $stock_quantity = $product->get_stock_quantity();
                if ($stock_status === 'instock' && $stock_quantity !== null) {
                    $stock_text .= ' (' . $stock_quantity . ')';
                }
                
                $embed['fields'][] = [
                    'name' => 'Stock',
                    'value' => $stock_text,
                    'inline' => true
                ];
            }
        }
    }
    
    // Add footer with author and date
    $footer_parts = [];
    if (isset($notification['show_author']) && $notification['show_author']) {
        $footer_parts[] = 'By: ' . $author_name;
    }
    
    if (isset($notification['show_date']) && $notification['show_date']) {
        $footer_parts[] = get_the_date('', $post);
    }
    
    if (!empty($footer_parts)) {
        $embed['footer'] = [
            'text' => implode(' • ', $footer_parts)
        ];
    }
    
    return $embed;
}

/**
 * Send Discord notification when a post is published
 *
 * @param int $post_id The post ID
 * @param WP_Post $post The post object
 * @param bool $update Whether this is an update
 */
function gtaw_discord_send_enhanced_post_notification($post_id, $post, $update) {
    // Skip if this is a revision or auto-save
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
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
            // Check category filter if enabled
            if (isset($notification['use_category_filter']) && $notification['use_category_filter'] && 
                isset($notification['category_filter']) && is_array($notification['category_filter']) && !empty($notification['category_filter'])) {
                
                // Determine which taxonomy to check
                $taxonomy = $post->post_type === 'product' ? 'product_cat' : 'category';
                
                // Get post categories
                $post_categories = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
                
                // Check if any category matches the filter
                $category_matches = array_intersect($notification['category_filter'], $post_categories);
                
                // Skip this notification if no categories match
                if (empty($category_matches)) {
                    continue;
                }
            }
            
            $matching_notifications[] = $notification;
        }
    }
    
    if (empty($matching_notifications)) {
        return;
    }
    
    // Process each matching notification
    foreach ($matching_notifications as $notification) {
        // Skip if missing required fields
        if (empty($notification['channel_id'])) {
            continue;
        }
        
        // Prepare the mention content if enabled
        $message_content = '';
        if (isset($notification['role_enabled']) && $notification['role_enabled'] && 
            !empty($notification['role_id'])) {
            $message_content = '<@&' . $notification['role_id'] . '>';
        }
        
        // Prepare the embed
        $embed = gtaw_prepare_post_notification_embed($post, $notification);
        
        // Send the notification
        $result = gtaw_discord_api_request("channels/{$notification['channel_id']}/messages", [
            'body' => json_encode([
                'content' => $message_content,
                'embeds' => [$embed]
            ]),
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ], 'POST');
        
        if (is_wp_error($result)) {
            gtaw_add_log('discord', 'Error', "Failed to send post notification for post ID {$post_id}: " . $result->get_error_message(), 'error');
        } else {
            gtaw_add_log('discord', 'Notification', "Sent post notification for {$post->post_type} ID {$post_id} to channel {$notification['channel_id']}", 'success');
            update_post_meta($post_id, '_gtaw_discord_notified', 'published');
        }
    }
}
add_action('wp_insert_post', 'gtaw_discord_send_enhanced_post_notification', 10, 3);

/**
 * Reset notification status when post is updated from non-published to published
 *
 * @param string $new_status New post status
 * @param string $old_status Old post status
 * @param WP_Post $post Post object
 */
function gtaw_reset_enhanced_discord_notification_status($new_status, $old_status, $post) {
    if ($old_status !== 'publish' && $new_status === 'publish') {
        delete_post_meta($post->ID, '_gtaw_discord_notified');
    }
}
add_action('transition_post_status', 'gtaw_reset_enhanced_discord_notification_status', 10, 3);

/**
 * Add test button to post edit screen
 *
 * @param WP_Post $post Post object
 */
function gtaw_add_enhanced_discord_notification_test_button($post) {
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
                    action: 'gtaw_test_enhanced_discord_notification',
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
add_action('post_submitbox_misc_actions', 'gtaw_add_enhanced_discord_notification_test_button');

/**
 * Handle test notification AJAX request
 */
function gtaw_test_enhanced_discord_notification_callback() {
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
    
    // Get notification settings
    $notifications = get_option('gtaw_discord_post_notifications', []);
    if (empty($notifications)) {
        wp_send_json_error('No notification templates configured');
    }
    
    // Find matching notification for this post type
    $matching_notifications = [];
    foreach ($notifications as $notification) {
        if ($notification['post_type'] === $post->post_type && isset($notification['enabled']) && $notification['enabled']) {
            // Check category filter if enabled
            if (isset($notification['use_category_filter']) && $notification['use_category_filter'] && 
                isset($notification['category_filter']) && is_array($notification['category_filter']) && !empty($notification['category_filter'])) {
                
                // Determine which taxonomy to check
                $taxonomy = $post->post_type === 'product' ? 'product_cat' : 'category';
                
                // Get post categories
                $post_categories = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
                
                // Check if any category matches the filter
                $category_matches = array_intersect($notification['category_filter'], $post_categories);
                
                // Skip this notification if no categories match
                if (empty($category_matches)) {
                    continue;
                }
            }
            
            $matching_notifications[] = $notification;
        }
    }
    
    if (empty($matching_notifications)) {
        wp_send_json_error('No notification templates enabled for this post type or post categories');
    }
    
    // Process each matching notification
    $success_count = 0;
    $error_messages = [];
    
    foreach ($matching_notifications as $notification) {
        // Skip if missing required fields
        if (empty($notification['channel_id'])) {
            $error_messages[] = 'Missing channel ID';
            continue;
        }
        
        // Prepare the mention content if enabled
        $message_content = '';
        if (isset($notification['role_enabled']) && $notification['role_enabled'] && 
            !empty($notification['role_id'])) {
            $message_content = '<@&' . $notification['role_id'] . '>';
        }
        
        // Prepare the embed
        $embed = gtaw_prepare_post_notification_embed($post, $notification);
        
        // Send the notification
        $result = gtaw_discord_api_request("channels/{$notification['channel_id']}/messages", [
            'body' => json_encode([
                'content' => $message_content,
                'embeds' => [$embed]
            ]),
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ], 'POST');
        
        if (is_wp_error($result)) {
            $error_messages[] = $result->get_error_message();
            gtaw_add_log('discord', 'Error', "Failed to send test notification for post ID {$post_id}: " . $result->get_error_message(), 'error');
        } else {
            $success_count++;
            gtaw_add_log('discord', 'Notification', "Sent test notification for post ID {$post_id} to channel {$notification['channel_id']}", 'success');
        }
    }
    
    // Send response
    if ($success_count > 0) {
        if (count($error_messages) > 0) {
            wp_send_json_success("Sent {$success_count} notifications with some errors: " . implode(", ", $error_messages));
        } else {
            wp_send_json_success("Successfully sent {$success_count} notification" . ($success_count > 1 ? 's' : '') . "!");
        }
    } else {
        wp_send_json_error("Failed to send notifications: " . implode(", ", $error_messages));
    }
}
add_action('wp_ajax_gtaw_test_enhanced_discord_notification', 'gtaw_test_enhanced_discord_notification_callback');

// Remove the original post notifications module to avoid conflicts
remove_action('wp_insert_post', 'gtaw_discord_send_post_notification', 10);

/**
 * Hook for attachment uploads to trigger Discord notifications
 * 
 * @param int $attachment_id The attachment ID
 */
function gtaw_discord_attachment_notification($attachment_id) {
    // Get the attachment post object
    $attachment = get_post($attachment_id);
    
    // Skip if this is a revision or auto-save
    if (!$attachment || $attachment->post_type !== 'attachment') {
        return;
    }
    
    // Skip if already notified
    if (get_post_meta($attachment_id, '_gtaw_discord_notified', true) === 'published') {
        return;
    }
    
    // Get notification settings
    $notifications = get_option('gtaw_discord_post_notifications', []);
    if (empty($notifications)) {
        return;
    }
    
    // Find matching notification for attachment type
    $matching_notifications = [];
    foreach ($notifications as $notification) {
        if ($notification['post_type'] === 'attachment' && isset($notification['enabled']) && $notification['enabled']) {
            $matching_notifications[] = $notification;
        }
    }
    
    if (empty($matching_notifications)) {
        return;
    }
    
    // Process each matching notification
    foreach ($matching_notifications as $notification) {
        // Skip if missing required fields
        if (empty($notification['channel_id'])) {
            continue;
        }
        
        // Prepare the mention content if enabled
        $message_content = '';
        if (isset($notification['role_enabled']) && $notification['role_enabled'] && 
            !empty($notification['role_id'])) {
            $message_content = '<@&' . $notification['role_id'] . '>';
        }
        
        // Prepare the embed
        $embed = gtaw_prepare_post_notification_embed($attachment, $notification);
        
        // Send the notification
        $result = gtaw_discord_api_request("channels/{$notification['channel_id']}/messages", [
            'body' => json_encode([
                'content' => $message_content,
                'embeds' => [$embed]
            ]),
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ], 'POST');
        
        if (is_wp_error($result)) {
            gtaw_add_log('discord', 'Error', "Failed to send attachment notification for ID {$attachment_id}: " . $result->get_error_message(), 'error');
        } else {
            gtaw_add_log('discord', 'Notification', "Sent attachment notification for ID {$attachment_id} to channel {$notification['channel_id']}", 'success');
            update_post_meta($attachment_id, '_gtaw_discord_notified', 'published');
        }
    }
}
add_action('add_attachment', 'gtaw_discord_attachment_notification');
add_action('edit_attachment', 'gtaw_discord_attachment_notification');