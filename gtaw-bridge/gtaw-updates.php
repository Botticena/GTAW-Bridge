<?php
defined('ABSPATH') or exit;

/**
 * GTAW Updater
 *
 * Provides an optimized update mechanism for the GTAW Bridge plugin by
 * checking for updates from the GitHub repository and handling the update process.
 */
class GTAW_Updater {
    private $plugin_file;
    private $plugin_data;
    private $repository = 'Botticena/GTAW-Bridge';
    private $github_api_base = 'https://api.github.com/repos/';

    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_data = get_plugin_data($plugin_file);

        // Check if updates are disabled
        $settings = get_option('gtaw_update_settings', ['enable_updates' => true]);
        $enable_updates = isset($settings['enable_updates']) ? (bool)$settings['enable_updates'] : true;
        
        if ($enable_updates) {
            // Hook into WordPress update checks and plugin API
            add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
            add_filter('plugins_api', [$this, 'plugin_api_info'], 10, 3);
            add_filter('upgrader_post_install', [$this, 'post_install_cleanup'], 10, 3);
        }
    }

    /**
     * Retrieve the latest release data from GitHub with caching.
     *
     * @return array|false Latest release data or false on failure.
     */
    private function get_latest_release() {
        $cache_key = 'gtaw_latest_release_' . md5($this->repository);
        $cached_data = get_transient($cache_key);
        
        if (false !== $cached_data) {
            return $cached_data;
        }
        
        $url = $this->github_api_base . $this->repository . '/releases/latest';

        $response = wp_remote_get($url, [
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version'),
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        // Check for rate limiting
        $headers = wp_remote_retrieve_headers($response);
        $rate_limit_remaining = isset($headers['x-ratelimit-remaining']) ? intval($headers['x-ratelimit-remaining']) : null;
        
        // Default cache time: 6 hours
        $cache_time = 6 * HOUR_IN_SECONDS;
        
        // If near rate limit, extend cache time
        if ($rate_limit_remaining !== null && $rate_limit_remaining < 10) {
            $cache_time = 24 * HOUR_IN_SECONDS; // 24 hours
        }

        $body = wp_remote_retrieve_body($response);
        $release = json_decode($body, true);

        if (!$release || !isset($release['tag_name'])) {
            return false;
        }

        // Cache the release data
        set_transient($cache_key, $release, $cache_time);

        return $release;
    }

    /**
     * Convert Markdown text to HTML using GitHub's Markdown API with fallback.
     * The result is cached for 12 hours.
     *
     * @param string $markdown The raw Markdown text.
     * @param string $version  The release version (for cache key).
     * @return string Converted HTML.
     */
    private function markdown_to_html($markdown, $version) {
        $cache_key = 'gtaw_release_html_' . md5($version);
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }
        
        // Prepare fallback HTML in case GitHub API fails
        $fallback_html = $this->simple_markdown_to_html($markdown);

        $url = "https://api.github.com/markdown";
        $args = [
            'body'    => json_encode([
                'text' => $markdown,
                'mode' => 'gfm'
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'WordPress/' . get_bloginfo('version'),
            ],
            'timeout' => 15,
        ];

        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $html = $fallback_html;
        } else {
            $html = wp_remote_retrieve_body($response);
            if (empty($html)) {
                $html = $fallback_html;
            }
        }

        // Cache the HTML conversion for 12 hours
        set_transient($cache_key, $html, 12 * HOUR_IN_SECONDS);
        
        return $html;
    }

    /**
     * Simple Markdown to HTML converter as fallback.
     *
     * @param string $markdown The markdown text
     * @return string Basic HTML conversion
     */
    private function simple_markdown_to_html($markdown) {
      
      	// Basic heading conversion
        $html = preg_replace('/^#####\s+(.+?)$/m', '<h5>$1</h5>', $markdown);
        $html = preg_replace('/^####\s+(.+?)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.+?)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^##\s+(.+?)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#\s+(.+?)$/m', '<h1>$1</h1>', $html);
        
        // Basic list conversion
        $html = preg_replace('/^\*\s+(.+?)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^-\s+(.+?)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/<li>(.*?)<\/li>\n<li>/s', '<li>$1</li><li>', $html);
        $html = preg_replace('/(<li>.*?<\/li>)/', '<ul>$1</ul>', $html);
        $html = preg_replace('/<\/ul>\s*<ul>/', '', $html);
        
        // Basic link conversion
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);
        
        // Code blocks
        $html = preg_replace('/```(?:\w+)?\n(.*?)\n```/s', '<pre><code>$1</code></pre>', $html);
        
        // Basic paragraph handling
        $html = preg_replace('/^(?!<[a-z]).+/m', '<p>$0</p>', $html);
        $html = str_replace('<p></p>', '', $html);
        
        return $html;
    }

    /**
     * Normalize version for comparison.
     * 
     * @param string $version The version to normalize
     * @return string Normalized version
     */
    private function normalize_version($version) {
        // Remove 'v' prefix if exists
        if (strpos($version, 'v') === 0) {
            $version = substr($version, 1);
        }
        
        // Remove non-numeric characters except dots
        $version = preg_replace('/[^0-9.]/', '', $version);
        
        // Ensure we have at least x.y.z format
        $parts = explode('.', $version);
        while (count($parts) < 3) {
            $parts[] = '0';
        }
        
        return implode('.', $parts);
    }

    /**
     * Check for updates by comparing the current plugin version
     * with the latest GitHub release.
     *
     * @param object $transient The update transient.
     * @return object Modified transient.
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (false === $release) {
            return $transient;
        }

        // Normalize versions for comparison
        $latest_version = $this->normalize_version($release['tag_name']);
        $current_version = $this->normalize_version($this->plugin_data['Version']);

        if (version_compare($latest_version, $current_version, '>')) {
            $plugin_slug = plugin_basename($this->plugin_file);

            // Use the first asset as the package URL; fallback to the zipball URL
            $package_url = '';
            if (!empty($release['assets']) && !empty($release['assets'][0]['browser_download_url'])) {
                $package_url = $release['assets'][0]['browser_download_url'];
            } else {
                $package_url = $release['zipball_url'];
            }

            $transient->response[$plugin_slug] = (object) [
                'slug'        => $plugin_slug,
                'plugin'      => $plugin_slug,
                'new_version' => $latest_version,
                'url'         => $release['html_url'],
                'package'     => $package_url,
                'icons'       => [],
                'banners'     => [],
            ];
        }

        return $transient;
    }

    /**
     * Provide detailed plugin information for the update dialog.
     *
     * @param mixed  $result The current result.
     * @param string $action The action type.
     * @param object $args   Request arguments.
     * @return object Plugin information.
     */
    public function plugin_api_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== plugin_basename($this->plugin_file)) {
            return $result;
        }

        $release = $this->get_latest_release();
        if (false === $release) {
            return $result;
        }

        $latest_version = $this->normalize_version($release['tag_name']);
        $raw_markdown = isset($release['body']) ? $release['body'] : 'No release notes provided.';
        $html_release_notes = $this->markdown_to_html($raw_markdown, $latest_version);

        // Determine the download link
        $download_link = '';
        if (!empty($release['assets']) && !empty($release['assets'][0]['browser_download_url'])) {
            $download_link = $release['assets'][0]['browser_download_url'];
        } else {
            $download_link = $release['zipball_url'];
        }

        $plugin_info = [
            'name'           => $this->plugin_data['Name'],
            'slug'           => plugin_basename($this->plugin_file),
            'version'        => $latest_version,
            'author'         => $this->plugin_data['Author'],
            'author_profile' => $this->plugin_data['AuthorURI'] ?? '',
            'last_updated'   => $release['published_at'],
            'requires'       => $this->plugin_data['RequiresWP'] ?? '',
            'tested'         => $this->plugin_data['TestedUpTo'] ?? '',
            'requires_php'   => $this->plugin_data['RequiresPHP'] ?? '',
            'homepage'       => $this->plugin_data['PluginURI'] ?? $release['html_url'],
            'sections'       => [
                'description' => $this->plugin_data['Description'] ?? 'GTA:W Bridge WordPress Plugin',
                'changelog'   => $html_release_notes,
                'github_info' => sprintf(
                    '<p>This plugin is maintained on GitHub. <a href="%s" target="_blank">View Repository</a></p>',
                    'https://github.com/' . $this->repository
                ),
            ],
            'download_link'  => $download_link,
        ];

        return (object) $plugin_info;
    }

    /**
     * Post-install cleanup.
     *
     * @param mixed $response The installation response.
     * @param array $hook_extra Extra info.
     * @param array $result Installation result.
     * @return mixed
     */
    public function post_install_cleanup($response, $hook_extra, $result) {
        if (is_wp_error($response)) {
            return $response;
        }
               
        // Clear all transients related to updates
        delete_transient('update_plugins');
        delete_site_transient('update_plugins');
        
        // Clear our specific cache
        $cache_key = 'gtaw_latest_release_' . md5($this->repository);
        delete_transient($cache_key);
        
        return $result;
    }
}

/**
 * Initialize the GTAW Updater.
 * Adjust the file path below if your main plugin file location changes.
 */
function gtaw_init_updater() {
    // Only initialize if not in an AJAX request to reduce overhead
    if (wp_doing_ajax()) {
        return;
    }
    
    $plugin_file = GTAW_BRIDGE_PLUGIN_DIR . 'gtaw-bridge.php';
    new GTAW_Updater($plugin_file);
}
add_action('init', 'gtaw_init_updater', 20); // Lower priority to ensure dependencies are loaded
