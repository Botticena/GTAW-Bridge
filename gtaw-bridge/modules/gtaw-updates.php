<?php
defined('ABSPATH') or exit;

/**
 * GTAW Lightweight Updater
 *
 * Optimized update mechanism for the GTAW Bridge plugin with:
 * - Conditional loading to minimize performance impact
 * - Intelligent caching with longer durations
 * - Minimal API requests with proper headers
 * - Optimized processing for WordPress admin
 */
class GTAW_Lightweight_Updater {
    private $plugin_file;
    private $plugin_data;
    private $slug;
    private $repository = 'Botticena/GTAW-Bridge';
    private $cache_enabled = true;
    private $cache_duration = 86400; // 24 hours by default
    private $last_api_response = null;
    
    /**
     * Initialize the updater with minimal overhead
     * 
     * @param string $plugin_file Path to the main plugin file
     */
    public function __construct($plugin_file) {
        // Store basic plugin info without loading the full plugin data yet
        $this->plugin_file = $plugin_file;
        $this->slug = plugin_basename($plugin_file);
        
        // Only hook into update process when appropriate
        $this->register_hooks();
    }
    
    /**
     * Register hooks conditionally based on current context
     */
    private function register_hooks() {
        // Always check if updates are enabled first
        $settings = get_option('gtaw_update_settings', ['enable_updates' => true]);
        if (!isset($settings['enable_updates']) || !$settings['enable_updates']) {
            return;
        }
        
        // Only hook into specific update checks to reduce overhead
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates'], 50);
        
        // Only load plugin info when specifically requested
        add_filter('plugins_api', [$this, 'plugin_api_info'], 10, 3);
        
        // Handle post-installation cleanup
        add_filter('upgrader_post_install', [$this, 'post_install_cleanup'], 10, 3);
    }
    
    /**
     * Load plugin data only when needed
     */
    private function load_plugin_data() {
        if (empty($this->plugin_data)) {
            $this->plugin_data = get_plugin_data($this->plugin_file);
        }
        return $this->plugin_data;
    }
    
    /**
     * Get release data from cache or GitHub with optimized API request
     *
     * @param bool $force_refresh Force a cache refresh
     * @return array|false Latest release data or false on failure
     */
    private function get_release_data($force_refresh = false) {
        // Use class property to avoid multiple API calls in the same request
        if ($this->last_api_response !== null) {
            return $this->last_api_response;
        }
        
        // Generate cache key based on repository
        $cache_key = 'gtaw_github_release_' . md5($this->repository);
        
        // Check cache first unless forcing refresh
        if (!$force_refresh && $this->cache_enabled) {
            $cached_data = get_transient($cache_key);
            if (false !== $cached_data) {
                $this->last_api_response = $cached_data;
                return $cached_data;
            }
        }
        
        // Calculate optimal cache duration based on update check frequency
        $update_frequency = $this->get_optimal_cache_duration();
        
        // Get current plugin version for conditional request headers
        $plugin_data = $this->load_plugin_data();
        $current_version = !empty($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';
        
        // Use GitHub API with minimal data and conditional request
        $api_url = "https://api.github.com/repos/{$this->repository}/releases/latest";
        $request_args = [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ],
            // Use HTTP/1.1 to support conditional requests
            'httpversion' => '1.1'
        ];
        
        // Add conditional request headers if we have ETag or Last-Modified from previous request
        $etag = get_option('gtaw_github_etag', '');
        if (!empty($etag)) {
            $request_args['headers']['If-None-Match'] = $etag;
        }
        
        $last_modified = get_option('gtaw_github_last_modified', '');
        if (!empty($last_modified)) {
            $request_args['headers']['If-Modified-Since'] = $last_modified;
        }
        
        // Make the API request
        $response = wp_remote_get($api_url, $request_args);
        
        // Handle errors in request
        if (is_wp_error($response)) {
            // On network error, use cached data if available or return false
            $cached_data = get_transient($cache_key);
            if (false !== $cached_data) {
                $this->last_api_response = $cached_data;
                return $cached_data;
            }
            return false;
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        
        // Handle 304 Not Modified - use existing cache
        if ($response_code === 304) {
            // The resource hasn't changed - refresh cache and return cached data
            $cached_data = get_transient($cache_key);
            if (false !== $cached_data) {
                // Update the transient with a fresh expiration
                set_transient($cache_key, $cached_data, $update_frequency);
                $this->last_api_response = $cached_data;
                return $cached_data;
            }
            // If we get 304 but have no cache, we need to fetch full data
            // This shouldn't happen, but just in case...
        }
        
        // Handle non-200 responses
        if ($response_code !== 200) {
            // Use cached data if available or return false
            $cached_data = get_transient($cache_key);
            if (false !== $cached_data) {
                $this->last_api_response = $cached_data;
                return $cached_data;
            }
            return false;
        }
        
        // Store ETag and Last-Modified for future conditional requests
        $headers = wp_remote_retrieve_headers($response);
        if (isset($headers['etag'])) {
            update_option('gtaw_github_etag', $headers['etag']);
        }
        
        if (isset($headers['last-modified'])) {
            update_option('gtaw_github_last_modified', $headers['last-modified']);
        }
        
        // Check for rate limit headers and adjust cache time if needed
        if (isset($headers['x-ratelimit-remaining'])) {
            $rate_limit_remaining = intval($headers['x-ratelimit-remaining']);
            // If we're close to rate limit, extend cache time significantly
            if ($rate_limit_remaining < 10) {
                $update_frequency = 48 * HOUR_IN_SECONDS; // 48 hours if close to limit
            }
        }
        
        // Process response body
        $body = wp_remote_retrieve_body($response);
        $release_data = json_decode($body, true);
        
        // Validate response data
        if (!is_array($release_data) || empty($release_data['tag_name'])) {
            // Invalid response - use cached data if available
            $cached_data = get_transient($cache_key);
            if (false !== $cached_data) {
                $this->last_api_response = $cached_data;
                return $cached_data;
            }
            return false;
        }
        
        // Clean and optimize the response data to store only what we need
        $optimized_data = $this->optimize_release_data($release_data);
        
        // Cache the release data
        set_transient($cache_key, $optimized_data, $update_frequency);
        $this->last_api_response = $optimized_data;
        
        return $optimized_data;
    }
    
    /**
     * Optimize GitHub release data to store only necessary fields
     *
     * @param array $release_data The full GitHub release data
     * @return array Optimized data with only required fields
     */
    private function optimize_release_data($release_data) {
        // Only store the fields we actually need to save memory and database space
        $optimized = [
            'tag_name' => $release_data['tag_name'],
            'html_url' => $release_data['html_url'],
            'published_at' => $release_data['published_at'],
            'body' => $release_data['body'],
        ];
        
        // Store asset download URL if available
        if (!empty($release_data['assets']) && !empty($release_data['assets'][0]['browser_download_url'])) {
            $optimized['download_url'] = $release_data['assets'][0]['browser_download_url'];
        } elseif (!empty($release_data['zipball_url'])) {
            $optimized['download_url'] = $release_data['zipball_url'];
        }
        
        return $optimized;
    }
    
    /**
     * Calculate optimal cache duration based on update frequency and site activity
     *
     * @return int Cache duration in seconds
     */
    private function get_optimal_cache_duration() {
        // Base duration - 24 hours by default
        $duration = $this->cache_duration;
        
        // If this is a development/staging site, check more frequently
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $duration = 6 * HOUR_IN_SECONDS; // 6 hours
        }
        
        // If this is a high-traffic site, extend cache time
        $user_count = function_exists('get_user_count') ? get_user_count() : 0;
        if ($user_count > 1000) {
            $duration = 48 * HOUR_IN_SECONDS; // 48 hours for high-traffic sites
        }
        
        // Allow filtering the cache duration
        return apply_filters('gtaw_update_cache_duration', $duration);
    }
    
    /**
     * Normalize version for comparison
     * 
     * @param string $version Version to normalize
     * @return string Normalized version
     */
    private function normalize_version($version) {
        // Remove 'v' prefix if exists
        if (strpos($version, 'v') === 0) {
            $version = substr($version, 1);
        }
        
        // Convert to x.y.z format
        $parts = explode('.', $version);
        while (count($parts) < 3) {
            $parts[] = '0';
        }
        
        return implode('.', array_slice($parts, 0, 3));
    }
    
    /**
     * Check for updates by comparing current version with GitHub release
     *
     * @param object $transient The update_plugins transient
     * @return object Modified transient
     */
    public function check_for_updates($transient) {
        // Skip if not a proper transient with checked plugins
        if (empty($transient->checked) || empty($transient->checked[$this->slug])) {
            return $transient;
        }
        
        // Load plugin data if not already loaded
        $plugin_data = $this->load_plugin_data();
        $current_version = $this->normalize_version($plugin_data['Version']);
        
        // Get release data with automatic caching
        $release_data = $this->get_release_data();
        if (empty($release_data)) {
            return $transient; // No release data available
        }
        
        // Normalize the release version
        $release_version = $this->normalize_version($release_data['tag_name']);
        
        // Check if update is available
        if (version_compare($release_version, $current_version, '>')) {
            $package_url = $release_data['download_url'] ?? '';
            
            if (empty($package_url)) {
                return $transient; // No download URL available
            }
            
            // Build the update information
            $transient->response[$this->slug] = (object) [
                'slug' => $this->slug,
                'plugin' => $this->slug,
                'new_version' => $release_version,
                'url' => $release_data['html_url'],
                'package' => $package_url,
                'icons' => [],
                'banners' => [],
                'tested' => get_bloginfo('version'),
                'requires_php' => $plugin_data['RequiresPHP'] ?? '',
            ];
        }
        
        return $transient;
    }
    
    /**
     * Provide plugin information for the update dialog
     *
     * @param mixed $result The current result
     * @param string $action The action type
     * @param object $args Request arguments
     * @return object|mixed Plugin information or original result
     */
    public function plugin_api_info($result, $action, $args) {
        // Only process plugin_information queries for this plugin
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== dirname($this->slug)) {
            return $result;
        }
        
        // Load plugin data
        $plugin_data = $this->load_plugin_data();
        
        // Get release data
        $release_data = $this->get_release_data();
        if (empty($release_data)) {
            return $result;
        }
        
        // Normalize version
        $release_version = $this->normalize_version($release_data['tag_name']);
        
        // Convert markdown to HTML more efficiently
        $html_release_notes = $this->process_markdown($release_data['body']);
        
        // Create plugin info object
        $plugin_info = (object) [
            'name' => $plugin_data['Name'],
            'slug' => dirname($this->slug),
            'version' => $release_version,
            'author' => $plugin_data['Author'],
            'author_profile' => $plugin_data['AuthorURI'] ?? '',
            'last_updated' => date('Y-m-d', strtotime($release_data['published_at'])),
            'requires' => $plugin_data['RequiresWP'] ?? '',
            'tested' => get_bloginfo('version'),
            'requires_php' => $plugin_data['RequiresPHP'] ?? '',
            'homepage' => $plugin_data['PluginURI'] ?? $release_data['html_url'],
            'download_link' => $release_data['download_url'],
            'sections' => [
                'description' => $plugin_data['Description'],
                'changelog' => $html_release_notes,
                'installation' => $this->get_installation_instructions(),
            ],
            'banners' => [],
            'icons' => [],
        ];
        
        return $plugin_info;
    }
    
    /**
     * Process markdown to HTML more efficiently
     *
     * @param string $markdown The markdown content
     * @return string HTML content
     */
    private function process_markdown($markdown) {
        // Create a cache key based on content to avoid processing the same content repeatedly
        $cache_key = 'gtaw_md_' . md5($markdown);
        $cached_html = get_transient($cache_key);
        
        if (false !== $cached_html) {
            return $cached_html;
        }
        
        // Simple markdown processing - much faster than full Markdown parser
        // Basic heading conversion
        $html = preg_replace('/^#####\s+(.+?)$/m', '<h5>$1</h5>', $markdown);
        $html = preg_replace('/^####\s+(.+?)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.+?)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^##\s+(.+?)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#\s+(.+?)$/m', '<h1>$1</h1>', $html);
        
        // Basic list conversion
        $html = preg_replace('/^\*\s+(.+?)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^-\s+(.+?)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/<\/li>\n<li>/', '</li><li>', $html);
        $html = preg_replace('/(<li>.*?<\/li>)/', '<ul>$1</ul>', $html);
        $html = preg_replace('/<\/ul>\s*<ul>/', '', $html);
        
        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $html);
        
        // Code blocks
        $html = preg_replace('/```(?:\w+)?\n(.*?)\n```/s', '<pre><code>$1</code></pre>', $html);
        
        // Basic paragraph handling
        $html = preg_replace('/^(?!<[a-z]).+/m', '<p>$0</p>', $html);
        $html = str_replace('<p></p>', '', $html);
        
        // Cache the result for future use (8 hour cache - HTML doesn't change often)
        set_transient($cache_key, $html, 8 * HOUR_IN_SECONDS);
        
        return $html;
    }
    
    /**
     * Get installation instructions
     *
     * @return string Installation instructions HTML
     */
    private function get_installation_instructions() {
        return '<h4>Automatic Installation</h4>
                <ol>
                    <li>Go to Plugins > Add New in your WordPress admin</li>
                    <li>Click "Upload Plugin" at the top of the page</li>
                    <li>Select the zip file you downloaded</li>
                    <li>Click "Install Now"</li>
                    <li>Activate the plugin</li>
                </ol>
                
                <h4>Manual Installation</h4>
                <ol>
                    <li>Upload the plugin files to the <code>/wp-content/plugins/gtaw-bridge</code> directory</li>
                    <li>Activate the plugin through the \'Plugins\' screen in WordPress</li>
                </ol>';
    }
    
    /**
     * Post-installation cleanup
     *
     * @param bool $response Installation response
     * @param array $hook_extra Extra info
     * @param array $result Installation result
     * @return array Installation result
     */
    public function post_install_cleanup($response, $hook_extra, $result) {
        // Only process for this plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->slug) {
            return $result;
        }
        
        // Clear caches to ensure fresh data after update
        $cache_key = 'gtaw_github_release_' . md5($this->repository);
        delete_transient($cache_key);
        
        return $result;
    }
}

/**
 * Initialize the updater only when needed to minimize performance impact
 */
function gtaw_init_lightweight_updater() {
    // Only initialize in specific contexts where updates are checked
    $load_updater = false;
    
    // Load on plugins.php page
    global $pagenow;
    if ($pagenow === 'plugins.php') {
        $load_updater = true;
    }
    
    // Load when WordPress is checking for updates
    if (doing_action('wp_maybe_auto_update') || doing_action('upgrader_process_complete')) {
        $load_updater = true;
    }
    
    // Load during update API calls
    if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['plugin_information', 'update-plugin'])) {
        $load_updater = true;
    }
    
    // Check for transient updates
    if (doing_action('pre_set_site_transient_update_plugins')) {
        $load_updater = true;
    }
    
    // Load when plugin update is forced
    if (isset($_GET['force-check']) && $_GET['force-check'] == '1') {
        $load_updater = true;
    }
    
    // Allow other plugins to force load the updater
    $load_updater = apply_filters('gtaw_load_updater', $load_updater);
    
    // Skip loading if not needed
    if (!$load_updater) {
        return;
    }
    
    // Initialize the updater
    $plugin_file = GTAW_BRIDGE_PLUGIN_DIR . 'gtaw-bridge.php';
    new GTAW_Lightweight_Updater($plugin_file);
}

// Hook into WordPress with low priority to let other essential functions run first
add_action('plugins_loaded', 'gtaw_init_lightweight_updater', 30);

/**
 * Register updater settings
 */
function gtaw_register_updater_settings() {
    register_setting('gtaw_general_settings', 'gtaw_update_settings');
}
add_action('admin_init', 'gtaw_register_updater_settings');

/**
 * Add settings field to enable/disable updates
 * 
 * @param array $fields Current settings fields
 * @return array Modified fields array
 */
function gtaw_add_updater_settings_field($fields) {
    $settings = get_option('gtaw_update_settings', ['enable_updates' => true]);
    
    $fields[] = [
        'type' => 'checkbox',
        'name' => 'gtaw_update_settings[enable_updates]',
        'label' => 'Enable Auto Updates',
        'default' => isset($settings['enable_updates']) ? $settings['enable_updates'] : true,
        'description' => 'Check to enable automatic update checks from GitHub.'
    ];
    
    return $fields;
}
add_filter('gtaw_general_settings_fields', 'gtaw_add_updater_settings_field');

/**
 * Force refresh update data when requested
 */
function gtaw_handle_update_refresh() {
    // Only process admin requests with proper permissions
    if (!is_admin() || !current_user_can('update_plugins')) {
        return;
    }
    
    // Check if refresh was requested
    if (isset($_GET['gtaw_refresh_updates']) && isset($_GET['_wpnonce'])) {
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'gtaw_refresh_updates')) {
            wp_die('Security check failed');
        }
        
        // Clear update cache
        delete_transient('gtaw_github_release_' . md5('Botticena/GTAW-Bridge'));
        delete_site_transient('update_plugins');
        
        // Redirect back without the refresh parameter
        wp_redirect(remove_query_arg(['gtaw_refresh_updates', '_wpnonce']));
        exit;
    }
}
add_action('admin_init', 'gtaw_handle_update_refresh');