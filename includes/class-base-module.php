<?php
/**
 * Base Module Class
 * 
 * All optimization modules extend this class
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SSO_Base_Module {
    
    protected $module_name;
    protected $module_prefix;
    protected $options = [];
    
    /**
     * Constructor
     */
    public function __construct($module_name) {
        $this->module_name = $module_name;
        $this->module_prefix = 'sso_' . $module_name . '_';
        $this->loadOptions();
    }
    
    /**
     * Initialize module - must be implemented by child classes
     */
    abstract public function init();
    
    /**
     * Load module options
     */
    protected function loadOptions() {
        // Get all options for this module
        global $wpdb;
        
        $options = $wpdb->get_results($wpdb->prepare("
            SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", $this->module_prefix . '%'));
        
        foreach ($options as $option) {
            $key = str_replace($this->module_prefix, '', $option->option_name);
            $this->options[$key] = maybe_unserialize($option->option_value);
        }
    }
    
    /**
     * Get option value
     */
    protected function getOption($key, $default = false) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    /**
     * Check if option is enabled
     */
    protected function isEnabled($key) {
        return $this->getOption($key, false) === true || $this->getOption($key, false) === '1';
    }
    
    /**
     * Safety check - ensure we're not in admin or affecting logged-in users
     */
    protected function isSafeContext() {
        // Additional context-specific safety checks can be added here
        return !is_admin() && !wp_doing_ajax() && !wp_doing_cron();
    }
    
    /**
     * Check if script/style should be excluded
     */
    protected function isExcluded($handle, $exclusions = []) {
        if (empty($exclusions)) {
            return false;
        }
        
        // Convert exclusions to array if it's a string
        if (is_string($exclusions)) {
            $exclusions = array_map('trim', explode(',', $exclusions));
        }
        
        // Check if handle matches any exclusion
        foreach ($exclusions as $exclusion) {
            if (empty($exclusion)) {
                continue;
            }
            
            // Exact match
            if ($handle === $exclusion) {
                return true;
            }
            
            // Partial match (contains)
            if (strpos($handle, $exclusion) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add inline script safely
     */
    protected function addInlineScript($script, $position = 'after') {
        if (empty($script)) {
            return;
        }
        
        // Ensure script is properly wrapped
        if (strpos($script, '<script') === false) {
            $script = '<script>' . $script . '</script>';
        }
        
        // Add to wp_head or wp_footer
        $hook = ($position === 'before') ? 'wp_head' : 'wp_footer';
        add_action($hook, function() use ($script) {
            echo $script . "\n";
        });
    }
    
    /**
     * Add inline style safely
     */
    protected function addInlineStyle($css, $position = 'head') {
        if (empty($css)) {
            return;
        }
        
        // Ensure CSS is properly wrapped
        if (strpos($css, '<style') === false) {
            $css = '<style>' . $css . '</style>';
        }
        
        // Add to wp_head or wp_footer
        $hook = ($position === 'footer') ? 'wp_footer' : 'wp_head';
        add_action($hook, function() use ($css) {
            echo $css . "\n";
        });
    }
    
    /**
     * Log debug information (only if WP_DEBUG is enabled)
     */
    protected function debug($message, $data = null) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_message = '[Safe Speed Optimizer - ' . $this->module_name . '] ' . $message;
        
        if ($data !== null) {
            $log_message .= ' | Data: ' . print_r($data, true);
        }
        
        error_log($log_message);
    }
    
    /**
     * Get module settings for admin interface
     */
    public function getSettings() {
        return [];
    }
    
    /**
     * Validate and sanitize options
     */
    public function sanitizeOptions($options) {
        return $options;
    }
}