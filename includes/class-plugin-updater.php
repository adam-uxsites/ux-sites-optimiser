<?php
/**
 * Plugin Updater Class
 * 
 * Handles automatic updates for the UX Sites Optimiser plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSO_Plugin_Updater {
    
    private $plugin_file;
    private $plugin_slug;
    private $version;
    private $update_server;
    private $license_key;
    
    public function __construct($plugin_file, $version, $update_server = '', $license_key = '') {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = $version;
        $this->update_server = $update_server;
        $this->license_key = $license_key;
        
        $this->init();
    }
    
    private function init() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'after_update'], 10, 2);
        
        // Add update notice in admin
        add_action('admin_notices', [$this, 'update_notice']);
        
        // Add update server settings if needed
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get remote version info
        $remote_info = $this->get_remote_info();
        
        if ($remote_info && version_compare($this->version, $remote_info->new_version, '<')) {
            $transient->response[$this->plugin_slug] = (object) [
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_info->new_version,
                'url' => $remote_info->details_url ?? '',
                'package' => $remote_info->download_url ?? '',
                'compatibility' => $remote_info->compatibility ?? []
            ];
        }
        
        return $transient;
    }
    
    /**
     * Get plugin info for update screen
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }
        
        $remote_info = $this->get_remote_info();
        
        if ($remote_info) {
            return (object) [
                'name' => $remote_info->name ?? 'UX Sites Optimiser',
                'slug' => dirname($this->plugin_slug),
                'version' => $remote_info->new_version,
                'author' => $remote_info->author ?? 'Your Name',
                'author_profile' => $remote_info->author_profile ?? '',
                'requires' => $remote_info->requires ?? '5.0',
                'tested' => $remote_info->tested ?? get_bloginfo('version'),
                'requires_php' => $remote_info->requires_php ?? '7.4',
                'download_link' => $remote_info->download_url ?? '',
                'sections' => [
                    'description' => $remote_info->description ?? 'Safe, toggleable performance optimizations for WordPress websites.',
                    'changelog' => $remote_info->changelog ?? 'Bug fixes and improvements.'
                ],
                'banners' => $remote_info->banners ?? [],
                'icons' => $remote_info->icons ?? []
            ];
        }
        
        return $result;
    }
    
    /**
     * Get remote plugin information
     */
    private function get_remote_info() {
        $transient_key = 'sso_update_info_' . md5($this->plugin_slug);
        $cached_info = get_transient($transient_key);
        
        if ($cached_info !== false) {
            return $cached_info;
        }
        
        // If no update server configured, use default GitHub approach
        if (empty($this->update_server)) {
            $info = $this->check_github_releases();
        } else {
            $info = $this->check_custom_server();
        }
        
        // Cache for 12 hours
        if ($info) {
            set_transient($transient_key, $info, 12 * HOUR_IN_SECONDS);
        }
        
        return $info;
    }
    
    /**
     * Check GitHub releases for updates
     */
    private function check_github_releases() {
        $github_repo = get_option('sso_github_repo', ''); // e.g., 'username/ux-sites-optimiser'
        
        if (empty($github_repo)) {
            return false;
        }
        
        $api_url = "https://api.github.com/repos/{$github_repo}/releases/latest";
        
        $response = wp_remote_get($api_url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'UX-Sites-Optimiser/' . $this->version
            ]
        ]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$body || !isset($body['tag_name'])) {
            return false;
        }
        
        // Find the .zip asset
        $download_url = '';
        if (isset($body['assets'])) {
            foreach ($body['assets'] as $asset) {
                if (strpos($asset['name'], '.zip') !== false) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }
        
        // Fallback to zipball if no .zip asset found
        if (empty($download_url)) {
            $download_url = $body['zipball_url'];
        }
        
        return (object) [
            'new_version' => ltrim($body['tag_name'], 'v'),
            'name' => 'UX Sites Optimiser',
            'download_url' => $download_url,
            'details_url' => $body['html_url'],
            'description' => 'Safe, toggleable performance optimizations for WordPress websites.',
            'changelog' => $body['body'] ?? 'See GitHub release for details.',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4'
        ];
    }
    
    /**
     * Check custom update server
     */
    private function check_custom_server() {
        $request_args = [
            'timeout' => 15,
            'body' => [
                'action' => 'get_version',
                'plugin_slug' => dirname($this->plugin_slug),
                'current_version' => $this->version,
                'site_url' => home_url(),
                'license_key' => $this->license_key
            ]
        ];
        
        $response = wp_remote_post($this->update_server, $request_args);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$body || !isset($body['new_version'])) {
            return false;
        }
        
        return (object) $body;
    }
    
    /**
     * Actions after plugin update
     */
    public function after_update($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin' && 
            isset($options['plugins']) && in_array($this->plugin_slug, $options['plugins'])) {
            
            // Clear update cache
            delete_transient('sso_update_info_' . md5($this->plugin_slug));
            
            // Run any post-update tasks
            $this->run_post_update_tasks();
        }
    }
    
    /**
     * Run tasks after plugin update
     */
    private function run_post_update_tasks() {
        // Clear any plugin caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Update database if needed
        $db_version = get_option('sso_db_version', '1.0.0');
        if (version_compare($db_version, $this->version, '<')) {
            // Run database migrations here if needed
            update_option('sso_db_version', $this->version);
        }
        
        // Log successful update
        error_log('UX Sites Optimiser updated to version ' . $this->version);
    }
    
    /**
     * Show update notice in admin
     */
    public function update_notice() {
        if (!current_user_can('update_plugins')) {
            return;
        }
        
        $transient = get_site_transient('update_plugins');
        
        if (isset($transient->response[$this->plugin_slug])) {
            $update_info = $transient->response[$this->plugin_slug];
            
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>UX Sites Optimiser:</strong> ';
            echo sprintf(
                __('Version %s is available. <a href="%s">Update now</a> or <a href="%s">view details</a>.', 'ux-sites-optimiser'),
                $update_info->new_version,
                wp_nonce_url(admin_url('update.php?action=upgrade-plugin&plugin=' . $this->plugin_slug), 'upgrade-plugin_' . $this->plugin_slug),
                admin_url('plugin-install.php?tab=plugin-information&plugin=' . dirname($this->plugin_slug) . '&TB_iframe=true&width=600&height=550')
            );
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Register update settings
     */
    public function register_settings() {
        register_setting('sso_update_settings', 'sso_github_repo');
        register_setting('sso_update_settings', 'sso_update_server');
        register_setting('sso_update_settings', 'sso_license_key');
        register_setting('sso_update_settings', 'sso_auto_updates');
    }
    
    /**
     * Force check for updates (useful for testing)
     */
    public function force_update_check() {
        delete_transient('sso_update_info_' . md5($this->plugin_slug));
        delete_site_transient('update_plugins');
        
        // Trigger WordPress to check for updates
        wp_update_plugins();
        
        return $this->get_remote_info();
    }
}