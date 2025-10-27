<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap gtaw-dashboard">
    <div class="gtaw-dashboard-header">
        <div class="gtaw-hero-header">
            <div class="gtaw-hero-overlay"></div>
            <div class="gtaw-hero-content">
                <div class="gtaw-hero-text">
                    <h1 class="gtaw-hero-title"><b>GTA:W Bridge</b></h1>
                    <p class="gtaw-hero-description">WordPress Plugin for the GTA World Roleplay community.</p>
                    <p class="gtaw-hero-version">Version <?php echo GTAW_BRIDGE_VERSION; ?></p>
                </div>
                <img src="<?php echo GTAW_BRIDGE_PLUGIN_URL; ?>assets/img/logo.webp" alt="GTA:W Bridge Logo" class="gtaw-hero-logo">
            </div>
        </div>
        
        <div class="gtaw-quick-info-panel">
            <div class="gtaw-quick-info-section">
                <span class="dashicons dashicons-book-alt gtaw-quick-info-icon"></span>
                <div class="gtaw-quick-info-content">
                    <h3>Getting Started</h3>
                    <ul>
                        <li><a href="https://github.com/Botticena/gtaw-bridge/">Documentation</a></li>
                        <li>Activate modules below</li>
                        <li>Configure each module</li>
                    </ul>
                </div>
            </div>
            
            <div class="gtaw-quick-info-section">
                <span class="dashicons dashicons-admin-tools gtaw-quick-info-icon"></span>
                <div class="gtaw-quick-info-content">
                    <h3>Quick Actions</h3>
                    <ul>
                        <?php if ($oauth_status): ?>
                        <li><a href="<?php echo admin_url('admin.php?page=gtaw-oauth'); ?>">OAuth Settings</a></li>
                        <?php endif; ?>
                        <?php if ($discord_status): ?>
                        <li><a href="<?php echo admin_url('admin.php?page=gtaw-discord'); ?>">Discord Settings</a></li>
                        <?php endif; ?>
                        <?php if ($fleeca_status): ?>
                        <li><a href="<?php echo admin_url('admin.php?page=gtaw-fleeca'); ?>">Fleeca Bank Settings</a></li>
                        <?php endif; ?>
                        <?php if (!$oauth_status && !$discord_status && !$fleeca_status): ?>
                        <li>No active modules</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="gtaw-quick-info-section">
                <span class="dashicons dashicons-info-outline gtaw-quick-info-icon"></span>
                <div class="gtaw-quick-info-content">
                    <h3>About</h3>
                    <p>Created by <a href="https://forum.gta.world/en/profile/56418-lena/" target="_blank">Lena</a></p>
                    <p>Need help? <a href="https://github.com/Botticena/gtaw-bridge/issues" target="_blank">GitHub Issues</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="gtaw-dashboard-main">
        <div class="gtaw-dashboard-modules">
            <h2>Module Management</h2>
            <p>Enable or disable modules based on your needs. Click on Settings to configure an active module.</p>
            
            <div class="gtaw-module-grid">
                <?php foreach ($modules as $module_id => $module): ?>
                <div class="gtaw-module-card <?php echo $module['status'] ? 'active' : 'inactive'; ?>">
                    <div class="gtaw-module-header">
                        <div class="gtaw-module-icon">
                            <span class="dashicons <?php echo esc_attr($module['icon']); ?>"></span>
                        </div>
                        <div class="gtaw-module-title">
                            <h3><?php echo esc_html($module['name']); ?>
                                <span class="status-badge <?php echo $module['status'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $module['status'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </h3>
                        </div>
                    </div>
                    
                    <div class="gtaw-module-description">
                        <p><?php echo esc_html($module['description']); ?></p>
                    </div>
                    
                    <div class="gtaw-module-actions">
                        <form method="post" class="module-toggle-form">
                            <?php wp_nonce_field('gtaw_module_toggle', 'gtaw_module_nonce'); ?>
                            <input type="hidden" name="gtaw_module_update" value="1">
                            <input type="hidden" name="gtaw_module_name" value="<?php echo esc_attr($module_id); ?>">
                            <input type="hidden" name="gtaw_module_status" value="<?php echo $module['status'] ? 'off' : 'on'; ?>">
                            
                            <div class="gtaw-button-container">
                                <button type="submit" class="button <?php echo $module['status'] ? 'deactivate' : 'activate'; ?>">
                                    <?php echo $module['status'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                                
                                <?php if ($module['status']): ?>
                                <a href="<?php echo esc_url($module['settings_url']); ?>" class="button settings-button">
                                    Settings
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="gtaw-dashboard-logs">
            <br><h2>Recent Activity Logs</h2>
            <p>Here are the most recent logs from all modules.</p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($combined_logs)): ?>
                        <tr>
                            <td colspan="4">No logs available yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($combined_logs as $log): ?>
                            <tr class="log-entry <?php echo esc_attr($log['status']); ?>">
                                <td><?php echo esc_html(ucfirst($log['module'])); ?></td>
                                <td><?php echo esc_html($log['type']); ?></td>
                                <td><?php echo esc_html($log['message']); ?></td>
                                <td><?php echo esc_html($log['date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <p class="gtaw-logs-more">
                <?php if ($oauth_status): ?>
                <a href="<?php echo admin_url('admin.php?page=gtaw-oauth&tab=logs'); ?>">View OAuth Logs</a>&emsp;
                <?php endif; ?>
                <?php if ($discord_status): ?>
                <a href="<?php echo admin_url('admin.php?page=gtaw-discord&tab=logs'); ?>">View Discord Logs</a>&emsp;
                <?php endif; ?>
                <?php if ($fleeca_status): ?>
                <a href="<?php echo admin_url('admin.php?page=gtaw-fleeca&tab=logs'); ?>">View Fleeca Logs</a>&emsp;
                <?php endif; ?>
                <?php if (!$oauth_status && !$discord_status && !$fleeca_status): ?>
                No active modules. Activate one to access logs.&emsp;
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>