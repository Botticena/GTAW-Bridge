<?php
defined('ABSPATH') or exit;

/**
 * Log events for the plugin.
 * 
 * @param string $module Either "oauth" or "discord" (or any future module).
 * @param string $type Type of log, e.g., "Register", "Login", "Error", "Link", "Unlink".
 * @param string $message Detailed log message.
 * @param string $status Either "success" (green) or "error" (red).
 */
function gtaw_add_log($module, $type, $message, $status = 'success') {
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
    update_option($log_option, $logs);
}

/**
 * Retrieve logs with pagination.
 *
 * @param string $module "oauth" or "discord".
 * @param int $limit Number of logs to return (default 20).
 * @param int $offset Offset for pagination.
 * @return array
 */
function gtaw_get_logs($module, $limit = 20, $offset = 0) {
    $log_option = "gtaw_{$module}_logs";
    $logs = get_option($log_option, []);
    return array_slice(array_reverse($logs), $offset, $limit);
}

/**
 * Clear logs for a given module.
 *
 * @param string $module "oauth" or "discord".
 */
function gtaw_clear_logs() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error("Permission denied.");
    }

    if (!isset($_POST['module'])) {
        wp_send_json_error("Module not specified.");
    }

    $module = sanitize_text_field($_POST['module']);
    $log_option = "gtaw_{$module}_logs";

    if (delete_option($log_option)) {
        wp_send_json_success("Logs cleared.");
    } else {
        update_option($log_option, []); // Ensure logs are reset if delete fails
        wp_send_json_success("Logs cleared.");
    }
}
add_action('wp_ajax_gtaw_clear_logs', 'gtaw_clear_logs');
