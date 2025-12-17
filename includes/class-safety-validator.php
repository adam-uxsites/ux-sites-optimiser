<?php
/**
 * Safety Validator Class
 * 
 * Handles all safety checks and validations
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSO_Safety_Validator {
    
    private static $instance = null;
    
    /**
     * Singleton pattern
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if we're in a safe context for optimizations
     */
    public function isSafeContext() {
        // Never in admin area
        if (is_admin()) {
            return false;
        }
        
        // Never during REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        
        // Never during AJAX requests
        if (wp_doing_ajax()) {
            return false;
        }
        
        // Never during cron jobs
        if (wp_doing_cron()) {
            return false;
        }
        
        // Check WooCommerce pages
        if ($this->isWooCommerceProtectedPage()) {
            return false;
        }
        
        // Check logged-in users setting
        if (is_user_logged_in() && !get_option('sso_affect_logged_in_users', false)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if current page is a protected WooCommerce page
     */
    private function isWooCommerceProtectedPage() {
        if (!function_exists('is_woocommerce')) {
            return false;
        }
        
        // Checkout page
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }
        
        // Cart page  
        if (function_exists('is_cart') && is_cart()) {
            return true;
        }
        
        // Account page
        if (function_exists('is_account_page') && is_account_page()) {
            return true;
        }
        
        // Thank you page
        if (function_exists('is_order_received_page') && is_order_received_page()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if a script handle should never be optimized
     */
    public function isProtectedScript($handle) {
        $protected_scripts = [
            // WordPress core critical scripts
            'wp-polyfill',
            'wp-hooks',
            
            // Admin bar
            'admin-bar',
            'hoverintent-js',
            
            // WooCommerce critical scripts
            'wc-checkout',
            'wc-cart-fragments',
            'wc-add-to-cart',
            'woocommerce',
            
            // Common form scripts
            'contact-form-7',
            'wpcf7-recaptcha',
            'gravity-forms',
            
            // Security plugins
            'wordfence',
            'sucuri',
            
            // Scripts with data-no-defer attribute (checked separately)
        ];
        
        return in_array($handle, $protected_scripts);
    }
    
    /**
     * Check if a stylesheet handle should never be optimized
     */
    public function isProtectedStylesheet($handle) {
        $protected_stylesheets = [
            // Admin bar
            'admin-bar',
            'dashicons',
            
            // Critical WooCommerce styles
            'woocommerce-layout',
            'woocommerce-smallscreen',
            'woocommerce-general',
        ];
        
        return in_array($handle, $protected_stylesheets);
    }
    
    /**
     * Validate script element for safety
     */
    public function isScriptSafe($script_tag) {
        // Never optimize scripts with data-no-defer
        if (strpos($script_tag, 'data-no-defer') !== false) {
            return false;
        }
        
        // Never optimize inline scripts that might be critical
        if (preg_match('/var\s+(wp|woocommerce|ajax|nonce)/i', $script_tag)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if current page type allows optimizations
     */
    public function isPageTypeOptimizable() {
        global $post;
        
        // Skip 404 pages
        if (is_404()) {
            return false;
        }
        
        // Skip search results (might have dynamic content)
        if (is_search()) {
            return true; // Actually, search pages can be optimized
        }
        
        // Skip password protected posts
        if (isset($post) && post_password_required($post)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate URL for optimization
     */
    public function isUrlOptimizable($url) {
        // Skip external URLs
        if (!$this->isInternalUrl($url)) {
            return false;
        }
        
        // Skip admin URLs
        if (strpos($url, '/wp-admin/') !== false) {
            return false;
        }
        
        // Skip login URLs
        if (strpos($url, '/wp-login') !== false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if URL is internal
     */
    private function isInternalUrl($url) {
        $site_url = get_site_url();
        $parsed_site = parse_url($site_url);
        $parsed_url = parse_url($url);
        
        // Relative URLs are internal
        if (!isset($parsed_url['host'])) {
            return true;
        }
        
        // Same domain
        return $parsed_url['host'] === $parsed_site['host'];
    }
    
    /**
     * Emergency rollback check
     * If too many errors occur, temporarily disable optimizations
     */
    public function checkEmergencyRollback() {
        $error_count = get_transient('sso_error_count');
        
        if ($error_count > 10) {
            // Disable all optimizations temporarily
            set_transient('sso_emergency_disable', true, HOUR_IN_SECONDS);
            return true;
        }
        
        return false;
    }
    
    /**
     * Log safety-related error
     */
    public function logSafetyError($message, $context = []) {
        // Increment error count
        $error_count = get_transient('sso_error_count') ?: 0;
        set_transient('sso_error_count', $error_count + 1, DAY_IN_SECONDS);
        
        // Log if debugging enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SSO Safety Error] ' . $message . ' Context: ' . print_r($context, true));
        }
    }
}