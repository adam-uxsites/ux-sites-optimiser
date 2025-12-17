<?php
/**
 * CSS Optimization Module
 * 
 * Critical for improving Largest Contentful Paint (LCP)
 * Includes critical CSS inlining and non-critical CSS deferring
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSO_CSS_Optimizer extends SSO_Base_Module {
    
    private $safety_validator;
    private $critical_css_added = false;
    
    public function __construct() {
        parent::__construct('css');
        $this->safety_validator = SSO_Safety_Validator::getInstance();
    }
    
    /**
     * Initialize module
     */
    public function init() {
        if (!$this->isSafeContext()) {
            return;
        }
        
        // Inline critical CSS
        if ($this->isEnabled('inline_critical')) {
            add_action('wp_head', [$this, 'inlineCriticalCSS'], 1);
        }
        
        // Defer non-critical CSS
        if ($this->isEnabled('defer_non_critical')) {
            add_filter('style_loader_tag', [$this, 'deferNonCriticalCSS'], 10, 4);
            add_action('wp_footer', [$this, 'addCSSLoadScript']);
        }
    }
    
    /**
     * Inline critical CSS in head
     */
    public function inlineCriticalCSS() {
        if ($this->critical_css_added) {
            return;
        }
        
        $critical_css = $this->getCriticalCSS();
        
        if (empty($critical_css)) {
            return;
        }
        
        echo '<style id="sso-critical-css">' . $critical_css . '</style>' . "\n";
        $this->critical_css_added = true;
        
        $this->debug('Inlined critical CSS (' . strlen($critical_css) . ' characters)');
    }
    
    /**
     * Get critical CSS based on current page/post type
     */
    private function getCriticalCSS() {
        global $post;
        
        // Check for page-specific critical CSS first
        if (isset($post->ID)) {
            $page_critical_css = get_post_meta($post->ID, 'sso_critical_css', true);
            if (!empty($page_critical_css)) {
                return $page_critical_css;
            }
        }
        
        // Check for post-type specific CSS
        $post_type = get_post_type();
        if ($post_type) {
            $post_type_css = get_option('sso_critical_css_' . $post_type, '');
            if (!empty($post_type_css)) {
                return $post_type_css;
            }
        }
        
        // Check for template-specific CSS
        $template = get_page_template_slug();
        if ($template) {
            $template_css = get_option('sso_critical_css_template_' . sanitize_key($template), '');
            if (!empty($template_css)) {
                return $template_css;
            }
        }
        
        // Fall back to global critical CSS
        return $this->getOption('critical_css', '');
    }
    
    /**
     * Defer non-critical CSS loading
     */
    public function deferNonCriticalCSS($tag, $handle, $href, $media) {
        // Skip protected stylesheets
        if ($this->safety_validator->isProtectedStylesheet($handle)) {
            return $tag;
        }
        
        // Skip admin stylesheets
        if (strpos($handle, 'admin') !== false) {
            return $tag;
        }
        
        // Skip critical stylesheets that should load immediately
        $critical_stylesheets = $this->getCriticalStylesheets();
        if (in_array($handle, $critical_stylesheets)) {
            return $tag;
        }
        
        // Skip print stylesheets
        if ($media === 'print') {
            return $tag;
        }
        
        // Convert to preload with fallback
        $deferred_tag = str_replace(
            "<link rel='stylesheet'",
            "<link rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\" data-sso-deferred='true'",
            $tag
        );
        
        // Add noscript fallback
        $noscript_fallback = '<noscript>' . $tag . '</noscript>';
        
        $this->debug('Deferred CSS: ' . $handle);
        
        return $deferred_tag . $noscript_fallback;
    }
    
    /**
     * Get list of critical stylesheets that should not be deferred
     */
    private function getCriticalStylesheets() {
        $critical = [
            'wp-block-library',  // Gutenberg blocks
            'wc-blocks-style',   // WooCommerce blocks
        ];
        
        // Allow themes to specify critical stylesheets
        return apply_filters('sso_critical_stylesheets', $critical);
    }
    
    /**
     * Add CSS loading script for fallback support
     */
    public function addCSSLoadScript() {
        static $script_added = false;
        
        if ($script_added) {
            return;
        }
        
        ?>
        <script>
        (function() {
            'use strict';
            
            // CSS loading polyfill for older browsers
            function loadCSS(href, before, media) {
                var doc = window.document;
                var ss = doc.createElement("link");
                var ref;
                if (before) {
                    ref = before;
                } else {
                    var refs = (doc.body || doc.getElementsByTagName("head")[0]).childNodes;
                    ref = refs[refs.length - 1];
                }
                
                var sheets = doc.styleSheets;
                ss.rel = "stylesheet";
                ss.href = href;
                ss.media = "only x";
                
                function ready(cb) {
                    if (doc.body) {
                        return cb();
                    }
                    setTimeout(function() {
                        ready(cb);
                    });
                }
                
                ready(function() {
                    ref.parentNode.insertBefore(ss, (before ? ref : ref.nextSibling));
                });
                
                var onloadcssdefined = function(cb) {
                    var resolvedHref = ss.href;
                    var i = sheets.length;
                    while (i--) {
                        if (sheets[i].href === resolvedHref) {
                            return cb();
                        }
                    }
                    setTimeout(function() {
                        onloadcssdefined(cb);
                    });
                };
                
                function loadCB() {
                    if (ss.addEventListener) {
                        ss.removeEventListener("load", loadCB);
                    }
                    ss.media = media || "all";
                }
                
                if (ss.addEventListener) {
                    ss.addEventListener("load", loadCB);
                }
                ss.onloadcssdefined = onloadcssdefined;
                onloadcssdefined(loadCB);
                return ss;
            }
            
            // Load deferred stylesheets
            var deferredStyles = document.querySelectorAll('link[data-sso-deferred="true"]');
            deferredStyles.forEach(function(link) {
                if (link.rel !== 'stylesheet') {
                    link.rel = 'stylesheet';
                }
            });
        })();
        </script>
        <?php
        
        $script_added = true;
    }
    
    /**
     * Get settings for admin interface
     */
    public function getSettings() {
        return [
            'defer_non_critical' => [
                'title' => __('Defer Non-Critical CSS', 'safe-speed-optimizer'),
                'description' => __('Loads CSS asynchronously to prevent render blocking', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'high'
            ],
            'inline_critical' => [
                'title' => __('Inline Critical CSS', 'safe-speed-optimizer'),
                'description' => __('Inlines critical CSS to eliminate render-blocking', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'medium',
                'advanced' => true
            ],
            'critical_css' => [
                'title' => __('Critical CSS', 'safe-speed-optimizer'),
                'description' => __('CSS that should be inlined for above-the-fold content', 'safe-speed-optimizer'),
                'type' => 'textarea',
                'rows' => 10
            ]
        ];
    }
    
    /**
     * Add meta box for page-specific critical CSS
     */
    public function addMetaBox() {
        add_meta_box(
            'sso_critical_css',
            __('Critical CSS', 'safe-speed-optimizer'),
            [$this, 'renderMetaBox'],
            ['page', 'post'],
            'normal',
            'low'
        );
    }
    
    /**
     * Render meta box for page-specific critical CSS
     */
    public function renderMetaBox($post) {
        wp_nonce_field('sso_critical_css_nonce', 'sso_critical_css_nonce');
        
        $critical_css = get_post_meta($post->ID, 'sso_critical_css', true);
        
        echo '<p>' . __('Add page-specific critical CSS that will be inlined in the head.', 'safe-speed-optimizer') . '</p>';
        echo '<textarea name="sso_critical_css" rows="10" cols="80" style="width:100%;">' . esc_textarea($critical_css) . '</textarea>';
    }
    
    /**
     * Save page-specific critical CSS
     */
    public function saveMetaBox($post_id) {
        if (!isset($_POST['sso_critical_css_nonce']) || !wp_verify_nonce($_POST['sso_critical_css_nonce'], 'sso_critical_css_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $critical_css = sanitize_textarea_field($_POST['sso_critical_css']);
        
        if (!empty($critical_css)) {
            update_post_meta($post_id, 'sso_critical_css', $critical_css);
        } else {
            delete_post_meta($post_id, 'sso_critical_css');
        }
    }
    
    /**
     * Sanitize options
     */
    public function sanitizeOptions($options) {
        if (isset($options['critical_css'])) {
            $options['critical_css'] = sanitize_textarea_field($options['critical_css']);
        }
        
        return $options;
    }
}