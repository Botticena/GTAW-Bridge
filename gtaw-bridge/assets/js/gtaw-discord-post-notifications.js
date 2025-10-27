/**
 * GTAW Bridge Discord Post Notifications
 * Handles post notification template management in the admin interface
 */
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
                nonce: gtaw_discord_post_notifications.nonce
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
                <img src="${gtaw_discord_post_notifications.image_url}" style="max-width: 100%; height: auto; border-radius: 3px; max-height: 300px;" alt="Featured Image">
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
                <img src="${gtaw_discord_post_notifications.image_url}" style="max-width: 100%; height: auto; border-radius: 3px; max-height: 300px;" alt="Featured Image">
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