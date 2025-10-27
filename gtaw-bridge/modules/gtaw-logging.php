<?php
defined('ABSPATH') or exit;

/**
 * Enhanced Logging System for GTAW Bridge
 * 
 * Provides a high-performance, database-backed logging system with backward
 * compatibility for existing implementations.
 * 
 * @version 2.0 - Optimized with database storage instead of options API
 */

/**
 * Initialize the logging system, creating the database table if needed
 */
function gtaw_logging_init() {
    // Only create table if it doesn't exist
    if (!gtaw_logging_table_exists()) {
        gtaw_create_logs_table();
    }
    
    // Check if we need to migrate legacy logs
    if (get_option('gtaw_logs_migration_needed', '1') === '1') {
        gtaw_migrate_legacy_logs();
    }
}
add_action('init', 'gtaw_logging_init');

/**
 * Check if the logs table exists
 * 
 * @return bool Whether the table exists
 */
function gtaw_logging_table_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtaw_logs';
    
    // First check cached value
    $cache_key = 'gtaw_logs_table_exists';
    $cached = wp_cache_get($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    // Query the database
    $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);
    
    // Cache the result (for 1 hour - should rarely change)
    wp_cache_set($cache_key, $table_exists, '', HOUR_IN_SECONDS);
    
    return $table_exists;
}

/**
 * Create the logs database table
 * 
 * @return bool Success status
 */
function gtaw_create_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtaw_logs';
    
    // Define character set
    $charset_collate = $wpdb->get_charset_collate();
    
    // Define SQL query to create the table
    $sql = "CREATE TABLE $table_name (
        log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        module VARCHAR(50) NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL,
        date DATETIME NOT NULL,
        context TEXT NULL,
        user_id BIGINT(20) UNSIGNED NULL,
        ip VARCHAR(45) NULL,
        PRIMARY KEY (log_id),
        KEY module (module),
        KEY status (status),
        KEY date (date),
        KEY type (type)
    ) $charset_collate;";
    
    // We need to include the WordPress database upgrade functions
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Create the table - dbDelta handles both creation and updates
    $result = dbDelta($sql);
    
    // Clear the table exists cache
    wp_cache_delete('gtaw_logs_table_exists');
    
    // Set the schema version
    update_option('gtaw_logs_schema_version', '1.0');
    
    // Mark that we'll need to migrate legacy logs
    update_option('gtaw_logs_migration_needed', '1');
    
    return !empty($result);
}

/**
 * Migrate legacy logs from options table to the new database table
 */
function gtaw_migrate_legacy_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtaw_logs';
    
    // Only migrate if the table exists
    if (!gtaw_logging_table_exists()) {
        return;
    }
    
    // Check for each module's logs
    $modules = array('oauth', 'discord', 'fleeca');
    $migrated_count = 0;
    
    foreach ($modules as $module) {
        $legacy_option = "gtaw_{$module}_logs";
        $logs = get_option($legacy_option, array());
        
        // Skip if no logs for this module
        if (empty($logs)) {
            continue;
        }
        
        // Begin transaction for better performance
        $wpdb->query('START TRANSACTION');
        
        try {
            // Process each log entry
            foreach ($logs as $log) {
                // Skip if invalid data
                if (!isset($log['type']) || !isset($log['message']) || !isset($log['status']) || !isset($log['date'])) {
                    continue;
                }
                
                // Default values
                $user_id = is_user_logged_in() ? get_current_user_id() : 0;
                
                // Insert the log entry
                $wpdb->insert(
                    $table_name,
                    array(
                        'module'  => $module,
                        'type'    => sanitize_text_field($log['type']),
                        'message' => sanitize_text_field($log['message']),
                        'status'  => sanitize_text_field($log['status']),
                        'date'    => $log['date'],
                        'user_id' => $user_id,
                        'ip'      => null, // Never store IP address
                        'context' => null // No context in legacy logs
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
                );
                
                $migrated_count++;
            }
            
            // Commit the transaction
            $wpdb->query('COMMIT');
            
            // Clear the legacy logs only if successfully migrated
            delete_option($legacy_option);
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            
            // Log the error, but not using our own logging to avoid recursion
            error_log('GTAW Bridge: Error migrating logs for module ' . $module . ': ' . $e->getMessage());
        }
    }
    
    // Mark migration as complete
    update_option('gtaw_logs_migration_needed', '0');
    update_option('gtaw_logs_migration_count', $migrated_count);
    update_option('gtaw_logs_migration_date', current_time('mysql'));
}

/**
 * Log events for the plugin with optimized database storage
 * 
 * @param string $module Either "oauth" or "discord" (or any future module).
 * @param string $type Type of log, e.g., "Register", "Login", "Error", "Link", "Unlink".
 * @param string $message Detailed log message.
 * @param string $status Either "success" (green) or "error" (red).
 * @param array $context Optional. Additional contextual data (will be JSON encoded)
 * @return int|false The log ID on success, false on failure
 */
function gtaw_add_log($module, $type, $message, $status = 'success', $context = array()) {
    // Validate inputs
    if (empty($module) || empty($type) || empty($message)) {
        return false;
    }
    
    // Check if we should use the new database table or legacy options
    if (gtaw_logging_table_exists()) {
        return gtaw_add_log_db($module, $type, $message, $status, $context);
    } else {
        // Fall back to legacy option-based storage
        return gtaw_add_log_legacy($module, $type, $message, $status);
    }
}

/**
 * Add log using the database table (new method)
 *
 * @param string $module Module name
 * @param string $type Log type
 * @param string $message Log message
 * @param string $status Log status (success/error)
 * @param array $context Optional context data
 * @return int|false Log ID or false on failure
 */
function gtaw_add_log_db($module, $type, $message, $status = 'success', $context = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtaw_logs';
    
    // Get current user ID if logged in
    $user_id = is_user_logged_in() ? get_current_user_id() : 0;
    
    // Process context data if provided
    $context_json = null;
    if (!empty($context) && is_array($context)) {
        $context_json = json_encode($context);
    }
    
    // Insert the log entry - without collecting IP
    $result = $wpdb->insert(
        $table_name,
        array(
            'module'  => sanitize_text_field($module),
            'type'    => sanitize_text_field($type),
            'message' => sanitize_text_field($message),
            'status'  => sanitize_text_field($status),
            'date'    => current_time('mysql'),
            'user_id' => $user_id,
            'ip'      => null, // Never store IP address
            'context' => $context_json
        ),
        array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
    );
    
    if ($result) {
        return $wpdb->insert_id;
    }
    
    return false;
}

/**
 * Legacy method to store logs in options table
 * Kept for backward compatibility
 *
 * @param string $module Module name
 * @param string $type Log type
 * @param string $message Log message
 * @param string $status Log status
 * @return bool Success status
 */
function gtaw_add_log_legacy($module, $type, $message, $status = 'success') {
    // Set the appropriate log storage option
    $log_option = "gtaw_{$module}_logs";

    // Retrieve existing logs
    $logs = get_option($log_option, []);

    // Limit log storage to avoid excessive database usage (store max 500 logs)
    if (count($logs) >= 500) {
        array_shift($logs); // Remove the oldest log entry
    }

    // Add a new log entry
    $logs[] = [
        'type'    => $type,     
        'message' => $message,  
        'status'  => $status,   
        'date'    => current_time('mysql'), 
    ];

    // Save logs back to the database
    return update_option($log_option, $logs);
}

/**
 * Retrieve logs with pagination and filtering
 *
 * @param string $module Module name (or 'all' for all modules)
 * @param int $limit Number of logs to return (default 20)
 * @param int $offset Offset for pagination
 * @param array $filters Optional. Associative array of filters (status, type, date_from, date_to)
 * @return array Logs array
 */
function gtaw_get_logs($module, $limit = 20, $offset = 0, $filters = array()) {
    // Check if we should use the database table or legacy options
    if (gtaw_logging_table_exists()) {
        return gtaw_get_logs_db($module, $limit, $offset, $filters);
    } else {
        // Fall back to legacy option-based retrieval
        return gtaw_get_logs_legacy($module, $limit, $offset);
    }
}

/**
 * Get logs from the database with advanced filtering
 *
 * @param string $module Module name or 'all'
 * @param int $limit Result limit
 * @param int $offset Result offset
 * @param array $filters Filter criteria
 * @return array Logs array
 */
function gtaw_get_logs_db($module, $limit = 20, $offset = 0, $filters = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtaw_logs';
    
    // Start building the query
    $query = "SELECT * FROM $table_name";
    $where_clauses = array();
    $query_args = array();
    
    // Filter by module (unless 'all' is specified)
    if ($module !== 'all') {
        $where_clauses[] = "module = %s";
        $query_args[] = sanitize_text_field($module);
    }
    
    // Apply status filter if provided
    if (isset($filters['status']) && !empty($filters['status'])) {
        $where_clauses[] = "status = %s";
        $query_args[] = sanitize_text_field($filters['status']);
    }
    
    // Apply type filter if provided
    if (isset($filters['type']) && !empty($filters['type'])) {
        $where_clauses[] = "type = %s";
        $query_args[] = sanitize_text_field($filters['type']);
    }
    
    // Apply date range filters if provided
    if (isset($filters['date_from']) && !empty($filters['date_from'])) {
        $where_clauses[] = "date >= %s";
        $query_args[] = sanitize_text_field($filters['date_from']);
    }
    
    if (isset($filters['date_to']) && !empty($filters['date_to'])) {
        $where_clauses[] = "date <= %s";
        $query_args[] = sanitize_text_field($filters['date_to']);
    }
    
    // Apply text search if provided
    if (isset($filters['search']) && !empty($filters['search'])) {
        $where_clauses[] = "(message LIKE %s OR type LIKE %s)";
        $search_term = '%' . $wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
        $query_args[] = $search_term;
        $query_args[] = $search_term;
    }
    
    // Complete the WHERE clause if we have conditions
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // Add ordering
    $query .= " ORDER BY date DESC";
    
    // Add limit and offset
    $query .= " LIMIT %d OFFSET %d";
    $query_args[] = (int)$limit;
    $query_args[] = (int)$offset;
    
    // Prepare the final query
    $prepared_query = $wpdb->prepare($query, $query_args);
    
    // Execute the query
    $results = $wpdb->get_results($prepared_query, ARRAY_A);
    
    // Return empty array if no results
    if (!$results) {
        return array();
    }
    
    // Process the results to decode context data
    foreach ($results as &$log) {
        if (isset($log['context']) && !empty($log['context'])) {
            $log['context'] = json_decode($log['context'], true);
        } else {
            $log['context'] = array();
        }
    }
    
    return $results;
}

/**
 * Legacy method to retrieve logs from options table
 * Kept for backward compatibility
 *
 * @param string $module Module name
 * @param int $limit Number of logs
 * @param int $offset Pagination offset
 * @return array Logs array
 */
function gtaw_get_logs_legacy($module, $limit = 20, $offset = 0) {
    $log_option = "gtaw_{$module}_logs";
    $logs = get_option($log_option, []);
    return array_slice(array_reverse($logs), $offset, $limit);
}

/**
 * Count total logs for a module with filtering
 *
 * @param string $module Module name or 'all'
 * @param array $filters Optional. Associative array of filters
 * @return int Total number of logs
 */
function gtaw_count_logs($module, $filters = array()) {
    // Check if we should use the database table or legacy options
    if (gtaw_logging_table_exists()) {
        return gtaw_count_logs_db($module, $filters);
    } else {
        // Fall back to legacy option-based counting
        $log_option = "gtaw_{$module}_logs";
        $logs = get_option($log_option, []);
        return count($logs);
    }
}

/**
 * Count logs from the database with filtering
 *
 * @param string $module Module name or 'all'
 * @param array $filters Filter criteria
 * @return int Total count
 */
function gtaw_count_logs_db($module, $filters = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtaw_logs';
    
    // Start building the query
    $query = "SELECT COUNT(*) FROM $table_name";
    $where_clauses = array();
    $query_args = array();
    
    // Filter by module (unless 'all' is specified)
    if ($module !== 'all') {
        $where_clauses[] = "module = %s";
        $query_args[] = sanitize_text_field($module);
    }
    
    // Apply status filter if provided
    if (isset($filters['status']) && !empty($filters['status'])) {
        $where_clauses[] = "status = %s";
        $query_args[] = sanitize_text_field($filters['status']);
    }
    
    // Apply type filter if provided
    if (isset($filters['type']) && !empty($filters['type'])) {
        $where_clauses[] = "type = %s";
        $query_args[] = sanitize_text_field($filters['type']);
    }
    
    // Apply date range filters if provided
    if (isset($filters['date_from']) && !empty($filters['date_from'])) {
        $where_clauses[] = "date >= %s";
        $query_args[] = sanitize_text_field($filters['date_from']);
    }
    
    if (isset($filters['date_to']) && !empty($filters['date_to'])) {
        $where_clauses[] = "date <= %s";
        $query_args[] = sanitize_text_field($filters['date_to']);
    }
    
    // Apply text search if provided
    if (isset($filters['search']) && !empty($filters['search'])) {
        $where_clauses[] = "(message LIKE %s OR type LIKE %s)";
        $search_term = '%' . $wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
        $query_args[] = $search_term;
        $query_args[] = $search_term;
    }
    
    // Complete the WHERE clause if we have conditions
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // Prepare the final query
    $prepared_query = $wpdb->prepare($query, $query_args);
    
    // Execute the query and return the count
    return (int)$wpdb->get_var($prepared_query);
}

/**
 * Clear logs for a given module
 *
 * @param string $module Module name or 'all'
 * @return bool Success status
 */
function gtaw_clear_logs_handler() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error("Permission denied.");
    }

    if (!isset($_POST['module'])) {
        wp_send_json_error("Module not specified.");
    }

    $module = sanitize_text_field($_POST['module']);
    
    // Clear logs based on storage method
    if (gtaw_logging_table_exists()) {
        $success = gtaw_clear_logs_db($module);
    } else {
        $log_option = "gtaw_{$module}_logs";
        $success = delete_option($log_option);
        
        if (!$success) {
            // Ensure logs are reset if delete fails
            update_option($log_option, []);
            $success = true;
        }
    }
    
    if ($success) {
        wp_send_json_success("Logs cleared.");
    } else {
        wp_send_json_error("Failed to clear logs.");
    }
}
add_action('wp_ajax_gtaw_clear_logs', 'gtaw_clear_logs_handler');

/**
 * Clear logs from the database
 *
 * @param string $module Module name or 'all'
 * @return bool Success status
 */
function gtaw_clear_logs_db($module) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtaw_logs';
    
    // Delete logs for specific module or all modules
    if ($module === 'all') {
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        return $result !== false;
    } else {
        $result = $wpdb->delete(
            $table_name,
            array('module' => $module),
            array('%s')
        );
        return $result !== false;
    }
}

/**
 * Purge old logs automatically to keep the database clean
 * 
 * @param int $days_to_keep Number of days to keep logs (default 30)
 * @return int Number of logs deleted
 */
function gtaw_purge_old_logs($days_to_keep = 30) {
    // Only purge if using database storage
    if (!gtaw_logging_table_exists()) {
        return 0;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtaw_logs';
    
    // Calculate cutoff date
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days_to_keep days"));
    
    // Delete old logs
    $result = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table_name WHERE date < %s",
            $cutoff_date
        )
    );
    
    return $result !== false ? (int)$result : 0;
}

/**
 * Register and perform regular log cleanup via WP-Cron
 */
function gtaw_register_log_cleanup() {
    // Schedule daily log cleanup if not already scheduled
    if (!wp_next_scheduled('gtaw_daily_log_cleanup')) {
        wp_schedule_event(time(), 'daily', 'gtaw_daily_log_cleanup');
    }
}
add_action('init', 'gtaw_register_log_cleanup');

/**
 * Daily log cleanup cron job
 */
function gtaw_daily_log_cleanup() {
    // Get retention setting - default to 30 days
    $days_to_keep = apply_filters('gtaw_log_retention_days', 30);
    
    // Purge old logs
    $deleted = gtaw_purge_old_logs($days_to_keep);
    
    // Log the cleanup (won't trigger recursion since this adds a new log)
    if ($deleted > 0) {
        gtaw_add_log(
            'system', 
            'Cleanup', 
            "Removed $deleted old log entries older than $days_to_keep days", 
            'success'
        );
    }
}
add_action('gtaw_daily_log_cleanup', 'gtaw_daily_log_cleanup');

/**
 * Export logs to CSV
 * 
 * @param string $module Module name or 'all'
 * @param array $filters Optional. Associative array of filters
 * @return string CSV content
 */
function gtaw_export_logs_to_csv($module, $filters = array()) {
    // Get all logs for the module without pagination
    $logs = gtaw_get_logs($module, 9999, 0, $filters);
    
    if (empty($logs)) {
        return '';
    }
    
    // Open output buffer
    ob_start();
    
    // Create a file handle in memory
    $output = fopen('php://output', 'w');
    
    // Add headers - Removed IP column
    fputcsv($output, array('Module', 'Type', 'Message', 'Status', 'Date', 'User ID'));
    
    // Add data
    foreach ($logs as $log) {
        $row = array(
            isset($log['module']) ? $log['module'] : $module,
            $log['type'],
            $log['message'],
            $log['status'],
            $log['date'],
            isset($log['user_id']) ? $log['user_id'] : ''
            // IP address removed for privacy
        );
        fputcsv($output, $row);
    }
    
    // Close the file handle
    fclose($output);
    
    // Get the contents
    $csv = ob_get_clean();
    
    return $csv;
}

/**
 * AJAX handler for exporting logs
 */
function gtaw_export_logs_handler() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied.');
    }
    
    // Get module from request
    if (!isset($_POST['module'])) {
        wp_send_json_error('Module not specified.');
    }
    
    $module = sanitize_text_field($_POST['module']);
    
    // Get filters
    $filters = array();
    if (isset($_POST['filters']) && is_array($_POST['filters'])) {
        foreach ($_POST['filters'] as $key => $value) {
            $filters[sanitize_key($key)] = sanitize_text_field($value);
        }
    }
    
    // Generate CSV
    $csv = gtaw_export_logs_to_csv($module, $filters);
    
    if (empty($csv)) {
        wp_send_json_error('No logs found to export.');
    }
    
    // Return CSV content
    wp_send_json_success(array(
        'csv' => $csv,
        'filename' => 'gtaw_' . $module . '_logs_' . date('Y-m-d') . '.csv'
    ));
}
add_action('wp_ajax_gtaw_export_logs', 'gtaw_export_logs_handler');

/**
 * Get a list of all log types for a module
 * Useful for building filter dropdowns
 * 
 * @param string $module Module name or 'all'
 * @return array List of log types
 */
function gtaw_get_log_types($module) {
    // For database-backed logs
    if (gtaw_logging_table_exists()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gtaw_logs';
        
        $query = "SELECT DISTINCT type FROM $table_name";
        $args = array();
        
        // Filter by module if not 'all'
        if ($module !== 'all') {
            $query .= " WHERE module = %s";
            $args[] = $module;
        }
        
        // Order alphabetically
        $query .= " ORDER BY type ASC";
        
        // Prepare and execute
        if (!empty($args)) {
            $query = $wpdb->prepare($query, $args);
        }
        
        $results = $wpdb->get_col($query);
        return $results ? $results : array();
    }
    
    // Legacy option-based approach
    $log_option = "gtaw_{$module}_logs";
    $logs = get_option($log_option, array());
    
    $types = array();
    foreach ($logs as $log) {
        if (isset($log['type']) && !in_array($log['type'], $types)) {
            $types[] = $log['type'];
        }
    }
    
    sort($types);
    return $types;
}

/**
 * Get combined logs from multiple modules
 * 
 * @param array $modules Array of module names
 * @param int $limit Number of logs to return
 * @param int $offset Offset for pagination
 * @param array $filters Optional filters
 * @return array Combined logs
 */
function gtaw_get_combined_logs($modules, $limit = 20, $offset = 0, $filters = array()) {
    $combined_logs = array();
    
    // For database-backed logs
    if (gtaw_logging_table_exists()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gtaw_logs';
        
        // Start building the query
        $query = "SELECT * FROM $table_name";
        $where_clauses = array();
        $query_args = array();
        
        // Filter by modules
        if (!empty($modules) && !in_array('all', $modules)) {
            $placeholders = array_fill(0, count($modules), '%s');
            $where_clauses[] = "module IN (" . implode(', ', $placeholders) . ")";
            foreach ($modules as $module) {
                $query_args[] = sanitize_text_field($module);
            }
        }
        
        // Apply additional filters
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where_clauses[] = "status = %s";
            $query_args[] = sanitize_text_field($filters['status']);
        }
        
        if (isset($filters['type']) && !empty($filters['type'])) {
            $where_clauses[] = "type = %s";
            $query_args[] = sanitize_text_field($filters['type']);
        }
        
        // Complete the WHERE clause if we have conditions
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        // Add ordering, limit and offset
        $query .= " ORDER BY date DESC LIMIT %d OFFSET %d";
        $query_args[] = (int)$limit;
        $query_args[] = (int)$offset;
        
        // Prepare and execute
        $prepared_query = $wpdb->prepare($query, $query_args);
        $combined_logs = $wpdb->get_results($prepared_query, ARRAY_A);
        
        return $combined_logs ? $combined_logs : array();
    }
    
    // Legacy option-based approach
    foreach ($modules as $module) {
        $log_option = "gtaw_{$module}_logs";
        $logs = get_option($log_option, array());
        
        foreach ($logs as $log) {
            $log['module'] = $module;
            $combined_logs[] = $log;
        }
    }
    
    // Sort by date (newest first)
    usort($combined_logs, function($a, $b) {
        $a_time = isset($a['date']) ? strtotime($a['date']) : 0;
        $b_time = isset($b['date']) ? strtotime($b['date']) : 0;
        return $b_time - $a_time;
    });
    
    // Apply limit and offset
    return array_slice($combined_logs, $offset, $limit);
}

/**
 * Setup plugin activation hook to create logs table
 */
function gtaw_logging_activation() {
    // This will be called when the plugin is activated
    gtaw_create_logs_table();
}
// Hook will be registered in the main plugin file

/**
 * Register logs display settings
 */
function gtaw_register_logs_display_settings() {
    register_setting('gtaw_general_settings', 'gtaw_logs_per_page', [
        'type' => 'integer',
        'default' => 100,
        'sanitize_callback' => 'absint',
    ]);
}
add_action('admin_init', 'gtaw_register_logs_display_settings');

/**
 * Add logs display settings field to general settings
 * 
 * @param array $fields Current fields
 * @return array Modified fields
 */
function gtaw_add_logs_display_settings_field($fields) {
    // Get current logs per page setting
    $logs_per_page = get_option('gtaw_logs_per_page', 100);
    
    // Add field to the fields array
    $fields[] = [
        'type' => 'select',
        'name' => 'gtaw_logs_per_page',
        'label' => 'Logs Per Page',
        'options' => [
            '25' => '25 logs',
            '50' => '50 logs',
            '100' => '100 logs',
            '200' => '200 logs',
            '500' => '500 logs',
        ],
        'default' => $logs_per_page,
        'description' => 'Number of logs to display per page in the logs viewer.'
    ];
    
    return $fields;
}
add_filter('gtaw_general_settings_fields', 'gtaw_add_logs_display_settings_field');

/**
 * Get logs per page setting with fallback
 * 
 * @return int Number of logs per page
 */
function gtaw_get_logs_per_page() {
    $logs_per_page = (int)get_option('gtaw_logs_per_page', 100);
    
    // Make sure we have a reasonable value
    if ($logs_per_page < 1) {
        $logs_per_page = 100;
    }
    
    return $logs_per_page;
}

/**
 * AJAX handler to update logs per page setting
 */
function gtaw_update_logs_per_page_handler() {
    // Security checks
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gtaw_update_logs_per_page_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Get and validate the logs per page value
    if (!isset($_POST['logs_per_page'])) {
        wp_send_json_error('Missing logs per page value');
    }
    
    $logs_per_page = absint($_POST['logs_per_page']);
    
    // Ensure a valid value
    if ($logs_per_page < 1) {
        $logs_per_page = 100;
    }
    
    // Update the option
    update_option('gtaw_logs_per_page', $logs_per_page);
    
    // Send success response
    wp_send_json_success([
        'message' => 'Logs per page setting updated',
        'logs_per_page' => $logs_per_page
    ]);
}
add_action('wp_ajax_gtaw_update_logs_per_page', 'gtaw_update_logs_per_page_handler');