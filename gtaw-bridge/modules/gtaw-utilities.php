<?php
defined('ABSPATH') or exit;

// Shared admin UI, logs table UI, performance timing, and AJAX security helpers.

/**
 * Resolve a settings field value for forms (top-level or one-level array options).
 *
 * @param string     $name    Field name, e.g. my_option or my_option[subkey].
 * @param mixed|null $default Fallback when the option or key is missing.
 * @return mixed
 */
function gtaw_get_settings_form_field_value( $name, $default = null ) {
    if ( preg_match( '/^([^\[]+)\[([^\]]+)\]$/', $name, $m ) ) {
        $opt = get_option( $m[1], array() );
        if ( is_array( $opt ) && array_key_exists( $m[2], $opt ) ) {
            return $opt[ $m[2] ];
        }
        return $default;
    }
    return get_option( $name, $default );
}

/**
 * Generate tab navigation for module settings pages
 *
 * @param string $page_slug Admin page slug
 * @param array $tabs Array of tabs with 'title' and 'callback' keys
 * @param string $active_tab The currently active tab
 * @return string HTML output of tab navigation
 */
function gtaw_generate_tabs_navigation($page_slug, $tabs, $active_tab) {
    ob_start();
    ?>
    <h2 class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_id => $tab): ?>
            <a href="?page=<?php echo esc_attr($page_slug); ?>&tab=<?php echo esc_attr($tab_id); ?>" 
               class="nav-tab <?php echo $active_tab == $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab['title']); ?>
            </a>
        <?php endforeach; ?>
    </h2>
    <?php
    return ob_get_clean();
}

/**
 * Generate a settings form with consistent styling
 *
 * @param string $group_name Settings group name
 * @param array $fields Array of field definitions
 * @param string $submit_text Text for submit button
 * @return string HTML output of settings form
 */
function gtaw_generate_settings_form($group_name, $fields, $submit_text = 'Save Changes') {
    ob_start();
    ?>
    <form method="post" action="options.php">
        <?php 
            settings_fields($group_name);
            do_settings_sections($group_name);
        ?>
        
        <table class="form-table">
            <?php foreach ($fields as $field): ?>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html($field['label']); ?></th>
                    <td>
                        <?php
                        $def = isset( $field['default'] ) ? $field['default'] : ( 'checkbox' === ( $field['type'] ?? '' ) ? '0' : ( 'color' === ( $field['type'] ?? '' ) ? '#000000' : '' ) );
                        $fv  = gtaw_get_settings_form_field_value( $field['name'], $def );
                        ?>
                        <?php if ($field['type'] === 'text'): ?>
                            <input type="text" 
                                   name="<?php echo esc_attr($field['name']); ?>" 
                                   value="<?php echo esc_attr( $fv ); ?>" 
                                   <?php echo isset($field['size']) ? 'size="' . esc_attr($field['size']) . '"' : ''; ?>
                                   <?php echo isset($field['readonly']) && $field['readonly'] ? 'readonly' : ''; ?> />
                        
                        <?php elseif ($field['type'] === 'checkbox'): ?>
                            <input type="checkbox" 
                                   name="<?php echo esc_attr($field['name']); ?>" 
                                   value="1" 
                                   <?php checked( $fv, '1' ); ?> />
                        
                        <?php elseif ($field['type'] === 'select'): ?>
                            <select name="<?php echo esc_attr($field['name']); ?>">
                                <?php foreach ($field['options'] as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" 
                                            <?php selected( $fv, $value ); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        
                        <?php elseif ($field['type'] === 'textarea'): ?>
                            <textarea name="<?php echo esc_attr($field['name']); ?>" 
                                      rows="<?php echo isset($field['rows']) ? esc_attr($field['rows']) : '5'; ?>" 
                                      cols="<?php echo isset($field['cols']) ? esc_attr($field['cols']) : '50'; ?>"><?php 
                                echo esc_textarea( $fv ); 
                            ?></textarea>
                        
                        <?php elseif ($field['type'] === 'color'): ?>
                            <input type="color" 
                                   name="<?php echo esc_attr($field['name']); ?>" 
                                   value="<?php echo esc_attr( ( is_string( $fv ) && $fv !== '' ) ? $fv : ( is_string( $def ) && $def !== '' ? $def : '#000000' ) ); ?>" />
                        <?php endif; ?>
                        
                        <?php if (isset($field['description'])): ?>
                            <p class="description"><?php echo wp_kses_post($field['description']); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        
        <?php submit_button($submit_text); ?>
    </form>
    <?php
    return ob_get_clean();
}

/**
 * Admin log table + export/clear
 * Updated to support both legacy option storage and new database storage
 *
 * @param string $module Module name (oauth, discord, fleeca)
 * @param int $limit Number of logs to display (default 100)
 * @param int $page Current page for pagination
 * @return string HTML output of logs table
 */
function gtaw_display_module_logs($module, $limit = null, $page = 1) {
    // Use the logs per page setting if limit not specified
    if ($limit === null && function_exists('gtaw_get_logs_per_page')) {
        $limit = gtaw_get_logs_per_page();
    } else {
        // Fallback if function doesn't exist or limit is specified
        $limit = $limit ?: 100;
    }
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $limit;
    
    // Get current filters from querystring
    $current_filters = array();
    if (isset($_GET['log_filter'])) {
        $current_filters['status'] = sanitize_text_field($_GET['log_filter']);
    }
    
    if (isset($_GET['log_type']) && !empty($_GET['log_type'])) {
        $current_filters['type'] = sanitize_text_field($_GET['log_type']);
    }
    
    if (isset($_GET['log_search']) && !empty($_GET['log_search'])) {
        $current_filters['search'] = sanitize_text_field($_GET['log_search']);
    }
    
    // Get paginated logs with filters
    $logs = gtaw_get_logs($module, $limit, $offset, $current_filters);
    
    // Get total count for pagination
    $total_logs = gtaw_count_logs($module, $current_filters);
    $total_pages = ceil($total_logs / $limit);
    
    // Get available log types for filter dropdown
    $log_types = gtaw_get_log_types($module);
    
    ob_start();
    ?>
    <div class="gtaw-logs-table-container">
        <br>
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" id="gtaw-logs-filter-form">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
                    <input type="hidden" name="tab" value="logs">
                    
                    <!-- Status filter -->
                    <select name="log_filter">
                        <option value="">All Statuses</option>
                        <option value="success" <?php selected(isset($_GET['log_filter']) && $_GET['log_filter'] === 'success'); ?>>Success</option>
                        <option value="error" <?php selected(isset($_GET['log_filter']) && $_GET['log_filter'] === 'error'); ?>>Error</option>
                    </select>
                    
                    <!-- Type filter -->
                    <?php if (!empty($log_types)): ?>
                        <select name="log_type">
                            <option value="">All Types</option>
                            <?php foreach ($log_types as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>" <?php selected(isset($_GET['log_type']) && $_GET['log_type'] === $type); ?>><?php echo esc_html($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    
                    <!-- Search filter -->
                    <input type="text" name="log_search" placeholder="Search logs..." value="<?php echo isset($_GET['log_search']) ? esc_attr($_GET['log_search']) : ''; ?>">
                    
                    <!-- Logs per page selector -->
                    <select name="logs_per_page" id="logs-per-page">
                        <option value="25" <?php selected($limit, 25); ?>>25 per page</option>
                        <option value="50" <?php selected($limit, 50); ?>>50 per page</option>
                        <option value="100" <?php selected($limit, 100); ?>>100 per page</option>
                        <option value="200" <?php selected($limit, 200); ?>>200 per page</option>
                        <option value="500" <?php selected($limit, 500); ?>>500 per page</option>
                    </select>
                    
                    <input type="submit" class="button" value="Apply">
                    
                    <?php if (isset($_GET['log_filter']) || isset($_GET['log_type']) || isset($_GET['log_search']) || isset($_GET['logs_per_page'])): ?>
                        <a href="?page=<?php echo esc_attr($_GET['page'] ?? ''); ?>&tab=logs" class="button">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- export -->
            <div class="alignright">
                <button id="export-logs" class="button" data-module="<?php echo esc_attr($module); ?>">Export to CSV</button>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html($total_logs); ?> items</span>
                <span class="pagination-links">
                    <?php
                    // Previous page link
                    if ($page > 1) {
                        $prev_url = add_query_arg(array_merge(
                            ['logs_page' => $page - 1, 'logs_per_page' => $limit],
                            $current_filters
                        ));
                        echo '<a class="prev-page button" href="' . esc_url($prev_url) . '"><span>&lsaquo;</span></a>';
                    } else {
                        echo '<span class="prev-page button disabled"><span>&lsaquo;</span></span>';
                    }
                    
                    // Page numbers
                    echo '<span class="paging-input">&nbsp;&nbsp;' . esc_html($page) . ' of ' . esc_html($total_pages) . '&nbsp;&nbsp;</span>';
                    
                    // Next page link
                    if ($page < $total_pages) {
                        $next_url = add_query_arg(array_merge(
                            ['logs_page' => $page + 1, 'logs_per_page' => $limit],
                            $current_filters
                        ));
                        echo '<a class="next-page button" href="' . esc_url($next_url) . '"><span>&rsaquo;</span></a>&nbsp;&nbsp;';
                    } else {
                        echo '<span class="next-page button disabled"><span>&rsaquo;</span></span>';
                    }
                    ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <table class="wp-list-table widefat fixed striped gtaw-logs-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Type</th>
                    <th style="width: 60%;">Message</th>
                    <th style="width: 20%;">Date</th>
                    <th style="width: 10%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="4">No logs found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="<?php echo esc_attr($log['status']); ?>">
                            <td><?php echo esc_html($log['type']); ?></td>
                            <td><?php echo esc_html($log['message']); ?></td>
                            <td><?php echo esc_html($log['date']); ?></td>
                            <td>
                                <span class="log-status-indicator status-<?php echo esc_attr($log['status']); ?>">
                                    <?php echo esc_html(ucfirst($log['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html($total_logs); ?> items</span>
                <span class="pagination-links">
                    <?php
                    // Previous page link
                    if ($page > 1) {
                        $prev_url = add_query_arg(array_merge(
                            ['logs_page' => $page - 1, 'logs_per_page' => $limit],
                            $current_filters
                        ));
                        echo '<a class="prev-page button" href="' . esc_url($prev_url) . '"><span>&lsaquo;</span></a>';
                    } else {
                        echo '<span class="prev-page button disabled"><span>&lsaquo;</span></span>';
                    }
                    
                    // Page numbers
                    echo '<span class="paging-input">&nbsp;&nbsp;' . esc_html($page) . ' of ' . esc_html($total_pages) . '&nbsp;&nbsp;</span>';
                    
                    // Next page link
                    if ($page < $total_pages) {
                        $next_url = add_query_arg(array_merge(
                            ['logs_page' => $page + 1, 'logs_per_page' => $limit],
                            $current_filters
                        ));
                        echo '<a class="next-page button" href="' . esc_url($next_url) . '"><span>&rsaquo;</span></a>';
                    } else {
                        echo '<span class="next-page button disabled"><span>&rsaquo;</span></span>';
                    }
                    ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
        
        <br>
        <div class="gtaw-logs-actions">
            <button id="clear-logs" class="button" data-module="<?php echo esc_attr($module); ?>" data-nonce="<?php echo wp_create_nonce('gtaw_clear_logs_nonce'); ?>">Clear Logs</button>
            <div id="logs-status" style="display: inline-block; margin-left: 10px;"></div>
        </div>
    </div>
    
    <style>
        .gtaw-logs-table tr.success td { color: #46b450; }
        .gtaw-logs-table tr.error td { color: #dc3232; }
        .log-status-indicator {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 12px;
        }
        .status-success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .status-error {
            background-color: #f2dede;
            color: #a94442;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Handle logs per page selector changes
        $('#logs-per-page').on('change', function() {
            // Update the global setting via AJAX
            $.post(ajaxurl, {
                action: 'gtaw_update_logs_per_page',
                logs_per_page: $(this).val(),
                nonce: '<?php echo wp_create_nonce('gtaw_update_logs_per_page_nonce'); ?>'
            });
        });
        
        // Clear logs functionality
        $('#clear-logs').on('click', function() {
            const module = $(this).data('module');
            const nonce = $(this).data('nonce');
            const $status = $('#logs-status');
            
            if (confirm("Are you sure you want to clear all logs? This cannot be undone.")) {
                $(this).prop('disabled', true);
                $status.html('<span style="color: blue;">Clearing logs...</span>');
                
                $.post(ajaxurl, {
                    action: "gtaw_clear_logs",
                    module: module,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">Logs cleared successfully.</span>');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $status.html('<span style="color: red;">Error: ' + response.data + '</span>');
                        $('#clear-logs').prop('disabled', false);
                    }
                }).fail(function() {
                    $status.html('<span style="color: red;">Request failed. Please try again.</span>');
                    $('#clear-logs').prop('disabled', false);
                });
            }
        });
        
        // Export logs functionality
        $('#export-logs').on('click', function() {
            const module = $(this).data('module');
            
            // Get current filters
            const filters = {};
            const $form = $('#gtaw-logs-filter-form');
            
            if ($form.find('select[name="log_filter"]').val()) {
                filters.status = $form.find('select[name="log_filter"]').val();
            }
            
            if ($form.find('select[name="log_type"]').val()) {
                filters.type = $form.find('select[name="log_type"]').val();
            }
            
            if ($form.find('input[name="log_search"]').val()) {
                filters.search = $form.find('input[name="log_search"]').val();
            }
            
            $(this).prop('disabled', true).text('Generating CSV...');
            
            $.post(ajaxurl, {
                action: "gtaw_export_logs",
                module: module,
                filters: filters
            }, function(response) {
                if (response.success) {
                    // Create download link
                    const blob = new Blob([response.data.csv], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = response.data.filename;
                    
                    // Trigger download
                    document.body.appendChild(a);
                    a.click();
                    
                    // Cleanup
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                } else {
                    alert('Error: ' + response.data);
                }
                
                $('#export-logs').prop('disabled', false).text('Export to CSV');
            }).fail(function() {
                alert('Export request failed. Please try again.');
                $('#export-logs').prop('disabled', false).text('Export to CSV');
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Display informational notice within admin pages
 *
 * @param string $message Message to display
 * @param string $type Notice type (info, success, warning, error)
 * @return string HTML output of notice
 */
function gtaw_admin_notice($message, $type = 'info') {
    $colors = [
        'info' => '#2271b1', 
        'success' => '#46b450',
        'warning' => '#ffb900',
        'error' => '#dc3232'
    ];
    
    $color = isset($colors[$type]) ? $colors[$type] : $colors['info'];
    
    ob_start();
    ?>
    <div class="gtaw-admin-notice" style="background: #fff; border-left: 4px solid <?php echo esc_attr($color); ?>; margin: 5px 0 15px; padding: 12px;">
        <p><?php echo wp_kses_post($message); ?></p>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Generate a consistent section header for settings pages
 *
 * @param string $title Section title
 * @param string $description Optional section description
 * @return string HTML output of section header
 */
function gtaw_section_header($title, $description = '') {
    ob_start();
    ?>
    <div class="gtaw-section-header" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
        <h2 style="margin: 0;"><?php echo esc_html($title); ?></h2>
        <?php if (!empty($description)): ?>
            <p style="margin: 10px 0 0 0;"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Load admin scripts only on specific plugin pages
 *
 * @param string $hook Current admin page hook
 * @param string $page_slug The page slug to check for
 * @param string|array $tabs Specific tab(s) to check for, or empty for any tab
 * @return bool Whether we're on the specified page/tab
 */
function gtaw_is_plugin_page($hook, $page_slug, $tabs = '') {
    // Check if we're on the right page
    if (strpos($hook, $page_slug) === false) {
        return false;
    }
    
    // If no tab specified, we're done
    if (empty($tabs)) {
        return true;
    }
    
    // Convert tabs to array if string
    if (!is_array($tabs)) {
        $tabs = array($tabs);
    }
    
    // Check if we're on one of the specified tabs
    $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
    return in_array($current_tab, $tabs);
}

/**
 * Register settings for a settings group
 *
 * @param string $group_name Settings group name
 * @param array $settings Array of setting names to register
 */
function gtaw_register_settings_group($group_name, $settings) {
    foreach ($settings as $setting) {
        register_setting($group_name, $setting);
    }
}

/**
 * Start timing a specific operation
 *
 * @param string $operation Operation name to time
 * @return void
 */
function gtaw_perf_start($operation) {
    // Store the start time in a global variable
    global $gtaw_perf_timers;
    
    if (!isset($gtaw_perf_timers)) {
        $gtaw_perf_timers = [];
    }
    
    $gtaw_perf_timers[$operation] = microtime(true);
}

/**
 * End timing a specific operation and get the elapsed time
 *
 * @param string $operation Operation name to stop timing
 * @param bool $log Whether to log the timing
 * @return float Elapsed time in seconds
 */
function gtaw_perf_end($operation, $log = true) {
    global $gtaw_perf_timers;
    
    // Return 0 if timer wasn't started
    if (!isset($gtaw_perf_timers[$operation])) {
        return 0;
    }
    
    // Calculate elapsed time
    $elapsed = microtime(true) - $gtaw_perf_timers[$operation];
    
    // Log timing if requested
    if ($log && defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf('GTAW Performance: %s took %.4f seconds', $operation, $elapsed));
    }
    
    // Clean up
    unset($gtaw_perf_timers[$operation]);
    
    return $elapsed;
}

/**
 * Verify user capabilities for specific module operations
 *
 * @param string $capability WordPress capability to check
 * @param string $module Module name for logging
 * @param string $operation Operation being performed
 * @return bool Whether user has permission
 */
function gtaw_verify_capability($capability, $module, $operation) {
    // Always allow for CLI operations
    if (defined('WP_CLI') && WP_CLI) {
        return true;
    }
    
    // Check capability
    if (!current_user_can($capability)) {
        gtaw_add_log($module, 'Security', "Unauthorized {$operation} attempt", 'error');
        return false;
    }
    
    return true;
}

/**
 * Verify nonce for secure operations
 * 
 * @param string $nonce Nonce to verify
 * @param string $action Nonce action
 * @param string $module Module name for logging
 * @param string $operation Operation being performed
 * @return bool Whether nonce is valid
 */
function gtaw_verify_nonce($nonce, $action, $module, $operation) {
    if (!wp_verify_nonce($nonce, $action)) {
        gtaw_add_log($module, 'Security', "Invalid nonce for {$operation}", 'error');
        return false;
    }
    
    return true;
}

/**
 * Secure AJAX response helper
 *
 * @param string $module Module name
 * @param string $nonce_key Name of nonce in $_POST
 * @param string $nonce_action Nonce action
 * @param string $capability Required capability
 * @param string $operation Operation name for logging
 * @return bool Whether security checks passed
 */
function gtaw_ajax_security_check($module, $nonce_key, $nonce_action, $capability, $operation) {
    // Check if user is logged in and has permission
    if (!gtaw_verify_capability($capability, $module, $operation)) {
        wp_send_json_error('Permission denied');
        return false;
    }
    
    // Verify nonce
    if (!isset($_POST[$nonce_key]) || !gtaw_verify_nonce($_POST[$nonce_key], $nonce_action, $module, $operation)) {
        wp_send_json_error('Security check failed');
        return false;
    }
    
    return true;
}

/**
 * Standardized AJAX security check for OAuth operations
 *
 * @param string $nonce_key Name of nonce in POST data
 * @param string $nonce_action Action name for nonce verification
 * @param string $operation Operation name for logging
 * @return bool Whether security check passed
 */
function gtaw_oauth_ajax_security_check($nonce_key, $nonce_action, $operation) {
    if (!check_ajax_referer($nonce_action, $nonce_key, false)) {
        gtaw_add_log('oauth', 'Security', "Invalid nonce in {$operation}", 'error');
        wp_send_json_error("Security check failed. Please refresh and try again.");
        return false;
    }
    return true;
}

/**
 * Validate character data from POST request
 *
 * @param string $context Context for error logging
 * @return array|false Character data or false if invalid
 */
function gtaw_validate_character_post_data($context) {
    if (empty($_POST['character_id']) || empty($_POST['character_firstname']) || empty($_POST['character_lastname'])) {
        gtaw_add_log('oauth', 'Error', "Invalid character data in {$context}", 'error');
        return false;
    }
    
    return [
        'id' => sanitize_text_field($_POST['character_id']),
        'firstname' => sanitize_text_field($_POST['character_firstname']),
        'lastname' => sanitize_text_field($_POST['character_lastname'])
    ];
}

/**
 * Get active character with caching
 *
 * @param int $user_id WordPress user ID
 * @return array|false Character data or false
 */
function gtaw_get_active_character($user_id) {
    $cache_key = 'gtaw_active_char_' . $user_id;
    $cached = wp_cache_get($cache_key, 'gtaw');
    
    if ($cached !== false) {
        return $cached;
    }
    
    $character = get_user_meta($user_id, 'active_gtaw_character', true);
    
    if (!empty($character)) {
        wp_cache_set($cache_key, $character, 'gtaw', 60); // Cache for 1 minute
    }
    
    return $character;
}