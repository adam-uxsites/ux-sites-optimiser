<?php
/**
 * WordPress Core Cleanup Module
 * 
 * Zero-risk optimizations that should be enabled by default
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSO_Core_Cleanup extends SSO_Base_Module {
    
    public function __construct() {
        parent::__construct('core');
    }
    
    /**
     * Initialize module
     */
    public function init() {
        // Remove wp-embed.js
        if ($this->isEnabled('remove_wp_embed')) {
            add_action('wp_footer', [$this, 'removeWpEmbed']);
        }
        
        // Remove dashicons for logged-out users
        if ($this->isEnabled('remove_dashicons_logged_out')) {
            add_action('wp_enqueue_scripts', [$this, 'removeDashiconsLoggedOut']);
        }
        
        // Disable XML-RPC
        if ($this->isEnabled('disable_xmlrpc')) {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('wp_headers', [$this, 'removeXmlrpcHeaders']);
        }
        
        // Remove REST API links
        if ($this->isEnabled('remove_rest_links')) {
            remove_action('wp_head', 'rest_output_link_wp_head');
            remove_action('template_redirect', 'rest_output_link_header', 11);
        }
        
        // Remove query strings from static assets
        if ($this->isEnabled('remove_query_strings')) {
            add_filter('style_loader_src', [$this, 'removeQueryStrings'], 10, 2);
            add_filter('script_loader_src', [$this, 'removeQueryStrings'], 10, 2);
        }
        
        // Additional WordPress cleanup
        $this->additionalCleanup();
    }
    
    /**
     * Remove wp-embed.js
     */
    public function removeWpEmbed() {
        wp_dequeue_script('wp-embed');
        $this->debug('Removed wp-embed.js');
    }
    
    /**
     * Remove dashicons for logged-out users
     */
    public function removeDashiconsLoggedOut() {
        if (!is_user_logged_in()) {
            wp_dequeue_style('dashicons');
            $this->debug('Removed dashicons for logged-out user');
        }
    }
    
    /**
     * Remove XML-RPC headers
     */
    public function removeXmlrpcHeaders($headers) {
        unset($headers['X-Pingback']);
        return $headers;
    }
    
    /**
     * Remove query strings from static assets
     */
    public function removeQueryStrings($src, $handle) {
        // Skip if no query string
        if (strpos($src, '?') === false) {
            return $src;
        }
        
        // Keep query strings for external resources
        if (!$this->isInternalResource($src)) {
            return $src;
        }
        
        // Keep query strings for dynamic scripts that might need them
        $keep_query_strings = [
            'customize-preview',
            'admin-bar',
            'wp-admin'
        ];
        
        foreach ($keep_query_strings as $keep) {
            if (strpos($handle, $keep) !== false || strpos($src, $keep) !== false) {
                return $src;
            }
        }
        
        // Remove version query strings
        $src = remove_query_arg(['ver', 'version'], $src);
        
        $this->debug('Removed query string from: ' . $handle);
        
        return $src;
    }
    
    /**
     * Additional WordPress cleanup
     */
    private function additionalCleanup() {
        // Remove unnecessary head links
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
        
        // Remove emoji scripts and styles
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        
        // Disable self pingbacks
        add_action('pre_ping', [$this, 'disableSelfPingbacks']);
        
        $this->debug('Applied additional WordPress cleanup');
    }
    
    /**
     * Disable self pingbacks
     */
    public function disableSelfPingbacks(&$links) {
        $home = get_option('home');
        foreach ($links as $l => $link) {
            if (strpos($link, $home) === 0) {
                unset($links[$l]);
            }
        }
    }
    
    /**
     * Check if resource URL is internal
     */
    private function isInternalResource($src) {
        $site_url = home_url();
        $parsed_site = parse_url($site_url);
        $parsed_src = parse_url($src);
        
        // Relative URLs are internal
        if (!isset($parsed_src['host'])) {
            return true;
        }
        
        // Same domain
        return $parsed_src['host'] === $parsed_site['host'];
    }
    
    /**
     * Get settings for admin interface
     */
    public function getSettings() {
        return [
            'remove_wp_embed' => [
                'title' => __('Remove WP Embed Script', 'safe-speed-optimizer'),
                'description' => __('Removes wp-embed.js if not needed', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'safe',
                'default' => true
            ],
            'remove_dashicons_logged_out' => [
                'title' => __('Remove Dashicons for Logged-Out Users', 'safe-speed-optimizer'),
                'description' => __('Removes dashicons CSS for visitors', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'safe',
                'default' => true
            ],
            'disable_xmlrpc' => [
                'title' => __('Disable XML-RPC', 'safe-speed-optimizer'),
                'description' => __('Disables XML-RPC functionality', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'safe',
                'default' => true
            ],
            'remove_rest_links' => [
                'title' => __('Remove REST API Links', 'safe-speed-optimizer'),
                'description' => __('Removes REST API discovery links from HTML head', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'safe',
                'default' => true
            ],
            'remove_query_strings' => [
                'title' => __('Remove Query Strings', 'safe-speed-optimizer'),
                'description' => __('Removes version query strings for better caching', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'safe',
                'default' => true
            ]
        ];
    }
}