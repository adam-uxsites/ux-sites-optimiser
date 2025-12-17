<?php
/**
 * JavaScript Optimization Module
 * 
 * High-impact JavaScript optimizations including jQuery footer loading,
 * script deferring, and delay until user interaction
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSO_JavaScript_Optimizer extends SSO_Base_Module {
    
    private $safety_validator;
    private $delayed_scripts = [];
    private $deferred_scripts = [];
    
    public function __construct() {
        parent::__construct('js');
        $this->safety_validator = SSO_Safety_Validator::getInstance();
    }
    
    /**
     * Initialize module
     */
    public function init() {
        if (!$this->isSafeContext()) {
            return;
        }
        
        // Move jQuery to footer
        if ($this->isEnabled('move_jquery_footer')) {
            add_action('wp_enqueue_scripts', [$this, 'moveJqueryToFooter'], 100);
        }
        
        // Defer non-critical JavaScript
        if ($this->isEnabled('defer_non_critical')) {
            add_filter('script_loader_tag', [$this, 'deferScripts'], 10, 3);
        }
        
        // Delay JavaScript until user interaction
        if ($this->isEnabled('delay_until_interaction')) {
            add_action('wp_footer', [$this, 'addDelayScript'], 999);
            add_filter('script_loader_tag', [$this, 'delayScripts'], 10, 3);
        }
        
        // Remove jQuery migrate if not needed
        add_action('wp_enqueue_scripts', [$this, 'removeJqueryMigrate'], 100);
    }
    
    /**
     * Move jQuery to footer for non-admin pages
     */
    public function moveJqueryToFooter() {
        if ($this->safety_validator->isProtectedScript('jquery')) {
            return;
        }
        
        // Remove jQuery from header
        wp_scripts()->add_data('jquery', 'group', 1);
        wp_scripts()->add_data('jquery-core', 'group', 1);
        wp_scripts()->add_data('jquery-migrate', 'group', 1);
        
        $this->debug('Moved jQuery to footer');
    }
    
    /**
     * Add defer attribute to non-critical scripts
     */
    public function deferScripts($tag, $handle, $src) {
        // Skip if already processed for delay
        if (in_array($handle, $this->delayed_scripts)) {
            return $tag;
        }
        
        // Skip protected scripts
        if ($this->safety_validator->isProtectedScript($handle)) {
            return $tag;
        }
        
        // Skip excluded scripts
        $excluded_scripts = $this->getExcludedScripts();
        if ($this->isExcluded($handle, $excluded_scripts)) {
            return $tag;
        }
        
        // Skip if script has data-no-defer
        if (strpos($tag, 'data-no-defer') !== false) {
            return $tag;
        }
        
        // Skip inline scripts
        if (empty($src)) {
            return $tag;
        }
        
        // Skip already deferred/async scripts
        if (strpos($tag, 'defer') !== false || strpos($tag, 'async') !== false) {
            return $tag;
        }
        
        // Add defer attribute
        $deferred_tag = str_replace('<script ', '<script defer ', $tag);
        $this->deferred_scripts[] = $handle;
        
        $this->debug('Deferred script: ' . $handle);
        
        return $deferred_tag;
    }
    
    /**
     * Delay scripts until user interaction
     */
    public function delayScripts($tag, $handle, $src) {
        // Skip protected scripts
        if ($this->safety_validator->isProtectedScript($handle)) {
            return $tag;
        }
        
        // Skip excluded scripts
        $excluded_scripts = $this->getExcludedScripts();
        if ($this->isExcluded($handle, $excluded_scripts)) {
            return $tag;
        }
        
        // Skip jQuery core (but allow other jQuery plugins to be delayed)
        if ($handle === 'jquery-core' || $handle === 'jquery') {
            return $tag;
        }
        
        // Skip if script has data-no-defer
        if (strpos($tag, 'data-no-defer') !== false) {
            return $tag;
        }
        
        // Skip inline scripts without src
        if (empty($src)) {
            return $tag;
        }
        
        // Skip if already processed
        if (strpos($tag, 'data-sso-delay') !== false) {
            return $tag;
        }
        
        // Convert script to delayed version
        $delayed_tag = str_replace(
            '<script ',
            '<script type="sso/javascript" data-sso-delay="true" data-sso-src="' . esc_attr($src) . '" ',
            $tag
        );
        
        // Remove src attribute from delayed script
        $delayed_tag = preg_replace('/\ssrc=["\'][^"\']*["\']/i', '', $delayed_tag);
        
        $this->delayed_scripts[] = $handle;
        
        $this->debug('Delayed script: ' . $handle);
        
        return $delayed_tag;
    }
    
    /**
     * Add script to handle delayed loading
     */
    public function addDelayScript() {
        if (empty($this->delayed_scripts)) {
            return;
        }
        
        ?>
        <script>
        (function() {
            'use strict';
            
            let userInteracted = false;
            let delayedScripts = [];
            
            // Collect all delayed scripts
            document.addEventListener('DOMContentLoaded', function() {
                delayedScripts = document.querySelectorAll('script[type="sso/javascript"][data-sso-delay="true"]');
            });
            
            // Function to load delayed scripts
            function loadDelayedScripts() {
                if (userInteracted || delayedScripts.length === 0) {
                    return;
                }
                
                userInteracted = true;
                
                delayedScripts.forEach(function(script) {
                    const src = script.getAttribute('data-sso-src');
                    if (src) {
                        const newScript = document.createElement('script');
                        newScript.src = src;
                        
                        // Copy attributes except type and data-sso-*
                        Array.from(script.attributes).forEach(function(attr) {
                            if (attr.name !== 'type' && !attr.name.startsWith('data-sso-')) {
                                newScript.setAttribute(attr.name, attr.value);
                            }
                        });
                        
                        // Insert before the delayed script
                        script.parentNode.insertBefore(newScript, script);
                        script.remove();
                    }
                });
                
                console.log('Safe Speed Optimizer: Loaded ' + delayedScripts.length + ' delayed scripts');
            }
            
            // User interaction events
            const events = ['click', 'scroll', 'keydown', 'touchstart', 'mouseover'];
            
            events.forEach(function(event) {
                document.addEventListener(event, function() {
                    loadDelayedScripts();
                    
                    // Remove event listeners after first interaction
                    events.forEach(function(e) {
                        document.removeEventListener(e, loadDelayedScripts);
                    });
                }, { passive: true, once: true });
            });
            
            // Fallback: load after 5 seconds if no interaction
            setTimeout(function() {
                if (!userInteracted) {
                    loadDelayedScripts();
                }
            }, 5000);
            
        })();
        </script>
        <?php
    }
    
    /**
     * Remove jQuery migrate if not needed
     */
    public function removeJqueryMigrate() {
        if (!is_admin()) {
            wp_deregister_script('jquery');
            wp_register_script('jquery', false, ['jquery-core'], false);
        }
    }
    
    /**
     * Get excluded scripts as array
     */
    private function getExcludedScripts() {
        $excluded = $this->getOption('excluded_scripts', 'jquery-core');
        
        if (is_string($excluded)) {
            // Split by comma or newline
            $excluded = preg_split('/[,\n\r]+/', $excluded);
            $excluded = array_map('trim', $excluded);
            $excluded = array_filter($excluded); // Remove empty values
        }
        
        // Always exclude critical scripts
        $always_excluded = [
            'jquery-core',
            'admin-bar',
            'hoverintent-js'
        ];
        
        return array_merge($always_excluded, $excluded);
    }
    
    /**
     * Get settings for admin interface
     */
    public function getSettings() {
        return [
            'move_jquery_footer' => [
                'title' => __('Move jQuery to Footer', 'safe-speed-optimizer'),
                'description' => __('Moves jQuery to footer to prevent render blocking', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'high'
            ],
            'defer_non_critical' => [
                'title' => __('Defer Non-Critical JavaScript', 'safe-speed-optimizer'),
                'description' => __('Adds defer attribute to JavaScript files', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'high'
            ],
            'delay_until_interaction' => [
                'title' => __('Delay JavaScript Until Interaction', 'safe-speed-optimizer'),
                'description' => __('Delays JS execution until user interacts with page', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'high',
                'advanced' => true
            ],
            'excluded_scripts' => [
                'title' => __('Excluded Scripts', 'safe-speed-optimizer'),
                'description' => __('Script handles to exclude from optimization', 'safe-speed-optimizer'),
                'type' => 'textarea'
            ]
        ];
    }
    
    /**
     * Sanitize options
     */
    public function sanitizeOptions($options) {
        if (isset($options['excluded_scripts'])) {
            $options['excluded_scripts'] = sanitize_textarea_field($options['excluded_scripts']);
        }
        
        return $options;
    }
}