<?php
/**
 * Plugin Name: UX Sites Optimiser
 * Plugin URI: https://your-domain.com
 * Description: Safe, toggleable performance optimizations for WordPress websites. Frontend-focused with modular class-based structure.
 * Version: 1.0.1
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: ux-sites-optimiser
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SSO_PLUGIN_VERSION', '1.0.1');
define('SSO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SSO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SSO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class SafeSpeedOptimizer {
    
    private static $instance = null;
    private $modules = [];
    private $updater = null;
    
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
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load required files
        $this->loadDependencies();
        
        // Initialize updater
        $this->initUpdater();
        
        // Initialize modules only if safe to do so
        if ($this->isSafeToOptimize()) {
            $this->initializeModules();
        }
        
        // Always load admin interface
        if (is_admin()) {
            $this->initAdmin();
        }
        
        // Load textdomain
        load_plugin_textdomain('ux-sites-optimiser', false, dirname(SSO_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Check if it's safe to apply optimizations
     */
    private function isSafeToOptimize() {
        // Safety rules - hard-coded and non-negotiable
        
        // Never affect wp-admin
        if (is_admin()) {
            return false;
        }
        
        // Never affect REST requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        
        // Never affect AJAX requests
        if (wp_doing_ajax()) {
            return false;
        }
        
        // Never affect cron jobs
        if (wp_doing_cron()) {
            return false;
        }
        
        // Never affect logged-in users (unless specifically enabled)
        $affect_logged_in = get_option('sso_affect_logged_in_users', false);
        if (is_user_logged_in() && !$affect_logged_in) {
            return false;
        }
        
        // Never break WooCommerce checkout pages
        if (function_exists('is_checkout') && is_checkout()) {
            return false;
        }
        
        // Never break WooCommerce cart pages
        if (function_exists('is_cart') && is_cart()) {
            return false;
        }
        
        // Never break WooCommerce account pages
        if (function_exists('is_account_page') && is_account_page()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Load required files
     */
    private function loadDependencies() {
        // Load updater
        require_once SSO_PLUGIN_PATH . 'includes/class-plugin-updater.php';
        
        // Load base classes
        require_once SSO_PLUGIN_PATH . 'includes/class-base-module.php';
        require_once SSO_PLUGIN_PATH . 'includes/class-safety-validator.php';
        
        // Load modules
        require_once SSO_PLUGIN_PATH . 'includes/modules/class-javascript-optimizer.php';
        require_once SSO_PLUGIN_PATH . 'includes/modules/class-css-optimizer.php';
        require_once SSO_PLUGIN_PATH . 'includes/modules/class-font-optimizer.php';
        require_once SSO_PLUGIN_PATH . 'includes/modules/class-image-optimizer.php';
        require_once SSO_PLUGIN_PATH . 'includes/modules/class-core-cleanup.php';
        require_once SSO_PLUGIN_PATH . 'includes/modules/class-third-party-scripts.php';
        require_once SSO_PLUGIN_PATH . 'includes/modules/class-preloading-hints.php';
        
        // Load admin
        if (is_admin()) {
            require_once SSO_PLUGIN_PATH . 'admin/class-admin-interface.php';
        }
    }
    
    /**
     * Initialize the plugin updater
     */
    private function initUpdater() {
        $update_server = get_option('sso_update_server', '');
        $license_key = get_option('sso_license_key', '');
        
        $this->updater = new SSO_Plugin_Updater(
            __FILE__,
            SSO_PLUGIN_VERSION,
            $update_server,
            $license_key
        );
    }
    
    /**
     * Initialize all optimization modules
     */
    private function initializeModules() {
        $this->modules = [
            'javascript' => new SSO_JavaScript_Optimizer(),
            'css' => new SSO_CSS_Optimizer(),
            'fonts' => new SSO_Font_Optimizer(),
            'images' => new SSO_Image_Optimizer(),
            'core_cleanup' => new SSO_Core_Cleanup(),
            'third_party' => new SSO_Third_Party_Scripts(),
            'preloading' => new SSO_Preloading_Hints()
        ];
        
        // Initialize each module
        foreach ($this->modules as $module) {
            $module->init();
        }
    }
    
    /**
     * Initialize admin interface
     */
    private function initAdmin() {
        new SSO_Admin_Interface();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $defaults = [
            'sso_affect_logged_in_users' => false,
            
            // JavaScript defaults
            'sso_js_move_jquery_footer' => true,
            'sso_js_defer_non_critical' => true,
            'sso_js_delay_until_interaction' => false,
            'sso_js_excluded_scripts' => 'jquery-core',
            
            // CSS defaults
            'sso_css_inline_critical' => false,
            'sso_css_defer_non_critical' => true,
            
            // Fonts defaults
            'sso_fonts_preload_local' => true,
            'sso_fonts_add_display_swap' => true,
            
            // Images defaults
            'sso_images_add_dimensions' => true,
            'sso_images_lazy_load' => true,
            
            // Core cleanup defaults (all enabled by default as they're safe)
            'sso_core_remove_wp_embed' => true,
            'sso_core_remove_dashicons_logged_out' => true,
            'sso_core_disable_xmlrpc' => true,
            'sso_core_remove_rest_links' => true,
            'sso_core_remove_query_strings' => true,
            
            // Preloading defaults
            'sso_preload_lcp_image' => true,
            'sso_preload_fonts' => true,
            'sso_dns_prefetch_third_party' => true,
        ];
        
        foreach ($defaults as $option => $value) {
            add_option($option, $value);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }
    
    /**
     * Get module instance
     */
    public function getModule($module_name) {
        return isset($this->modules[$module_name]) ? $this->modules[$module_name] : null;
    }
    
    /**
     * Get updater instance
     */
    public function getUpdater() {
        return $this->updater;
    }
}

// Initialize plugin
SafeSpeedOptimizer::getInstance();