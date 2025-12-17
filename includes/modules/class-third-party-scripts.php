<?php
/**
 * Third-Party Scripts Control Module
 * 
 * Huge performance wins by controlling external scripts and tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSO_Third_Party_Scripts extends SSO_Base_Module {
    
    private $delayed_analytics = [];
    
    public function __construct() {
        parent::__construct('third_party');
    }
    
    /**
     * Initialize module
     */
    public function init() {
        if (!$this->isSafeContext()) {
            return;
        }
        
        // Delay analytics scripts
        if ($this->isEnabled('delay_analytics')) {
            add_action('wp_footer', [$this, 'delayAnalyticsScripts'], 999);
            add_filter('script_loader_tag', [$this, 'interceptAnalyticsScripts'], 10, 3);
        }
        
        // Delay tracking scripts until consent
        if ($this->isEnabled('delay_tracking')) {
            add_action('wp_footer', [$this, 'addConsentManager'], 998);
        }
        
        // Disable Cloudflare email decode
        if ($this->isEnabled('disable_cf_email_decode')) {
            add_action('wp_head', [$this, 'disableCloudflareEmailDecode']);
        }
    }
    
    /**
     * Intercept and delay analytics scripts
     */
    public function interceptAnalyticsScripts($tag, $handle, $src) {
        if (empty($src)) {
            return $tag;
        }
        
        $analytics_domains = [
            'google-analytics.com',
            'googletagmanager.com',
            'facebook.net',
            'hotjar.com',
            'crazyegg.com',
            'mouseflow.com',
            'fullstory.com',
            'segment.com',
            'mixpanel.com'
        ];
        
        foreach ($analytics_domains as $domain) {
            if (strpos($src, $domain) !== false) {
                // Convert to delayed script
                $delayed_tag = str_replace(
                    '<script ',
                    '<script type="sso/analytics" data-sso-analytics="true" data-sso-src="' . esc_attr($src) . '" ',
                    $tag
                );
                
                // Remove src attribute
                $delayed_tag = preg_replace('/\ssrc=["\'][^"\']*["\']/i', '', $delayed_tag);
                
                $this->delayed_analytics[] = $handle;
                $this->debug('Delayed analytics script: ' . $handle);
                
                return $delayed_tag;
            }
        }
        
        return $tag;
    }
    
    /**
     * Add analytics delay script
     */
    public function delayAnalyticsScripts() {
        if (empty($this->delayed_analytics)) {
            return;
        }
        
        ?>
        <script>
        (function() {
            'use strict';
            
            let analyticsLoaded = false;
            let delayedAnalytics = [];
            
            // Collect delayed analytics scripts
            document.addEventListener('DOMContentLoaded', function() {
                delayedAnalytics = document.querySelectorAll('script[type="sso/analytics"][data-sso-analytics="true"]');
            });
            
            // Load analytics scripts
            function loadAnalytics() {
                if (analyticsLoaded || delayedAnalytics.length === 0) {
                    return;
                }
                
                analyticsLoaded = true;
                
                delayedAnalytics.forEach(function(script) {
                    const src = script.getAttribute('data-sso-src');
                    if (src) {
                        const newScript = document.createElement('script');
                        newScript.src = src;
                        newScript.async = true;
                        
                        // Copy other attributes
                        Array.from(script.attributes).forEach(function(attr) {
                            if (attr.name !== 'type' && !attr.name.startsWith('data-sso-')) {
                                newScript.setAttribute(attr.name, attr.value);
                            }
                        });
                        
                        document.head.appendChild(newScript);
                        script.remove();
                    }
                });
                
                console.log('Safe Speed Optimizer: Loaded ' + delayedAnalytics.length + ' analytics scripts');
            }
            
            // User interaction events
            const events = ['click', 'scroll', 'keydown', 'touchstart', 'mouseover'];
            
            events.forEach(function(event) {
                document.addEventListener(event, function() {
                    loadAnalytics();
                    
                    // Remove listeners after first load
                    events.forEach(function(e) {
                        document.removeEventListener(e, loadAnalytics);
                    });
                }, { passive: true, once: true });
            });
            
            // Fallback: load after 10 seconds
            setTimeout(function() {
                if (!analyticsLoaded) {
                    loadAnalytics();
                }
            }, 10000);
            
        })();
        </script>
        <?php
    }
    
    /**
     * Add consent management for tracking
     */
    public function addConsentManager() {
        ?>
        <script>
        (function() {
            'use strict';
            
            // Simple consent detection
            function hasConsent() {
                // Check common consent cookie names
                const consentCookies = [
                    'cookie-consent',
                    'gdpr-consent', 
                    'cookie-notice-accepted',
                    'cookieConsent',
                    'consent-given'
                ];
                
                for (let i = 0; i < consentCookies.length; i++) {
                    if (document.cookie.indexOf(consentCookies[i] + '=') !== -1) {
                        return true;
                    }
                }
                
                // Check localStorage
                try {
                    const storageKeys = ['consent', 'cookie-consent', 'gdpr-consent'];
                    for (let key of storageKeys) {
                        if (localStorage.getItem(key)) {
                            return true;
                        }
                    }
                } catch(e) {
                    // localStorage not available
                }
                
                return false;
            }
            
            // Load tracking only after consent
            function initTrackingAfterConsent() {
                if (!hasConsent()) {
                    // Check again in 2 seconds
                    setTimeout(initTrackingAfterConsent, 2000);
                    return;
                }
                
                // Trigger analytics loading if consent is given
                const analyticsEvent = new Event('click');
                document.dispatchEvent(analyticsEvent);
                
                console.log('Safe Speed Optimizer: Tracking initialized after consent');
            }
            
            // Start checking for consent
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initTrackingAfterConsent, 1000);
            });
            
        })();
        </script>
        <?php
    }
    
    /**
     * Disable Cloudflare email decode
     */
    public function disableCloudflareEmailDecode() {
        ?>
        <script>
        if (typeof window.CloudFlare !== 'undefined') {
            window.CloudFlare.push(function() {
                window.CloudFlare.EmailDecode = function() {};
            });
        }
        </script>
        <?php
        
        $this->debug('Disabled Cloudflare email decode');
    }
    
    /**
     * Get settings for admin interface
     */
    public function getSettings() {
        return [
            'delay_analytics' => [
                'title' => __('Delay Analytics Scripts', 'safe-speed-optimizer'),
                'description' => __('Delays Google Analytics, GTM, and other tracking until user interaction', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'high'
            ],
            'delay_tracking' => [
                'title' => __('Load Tracking Only After Consent', 'safe-speed-optimizer'),
                'description' => __('Waits for cookie consent before loading tracking scripts', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'medium'
            ],
            'disable_cf_email_decode' => [
                'title' => __('Disable Cloudflare Email Decode', 'safe-speed-optimizer'),
                'description' => __('Disables Cloudflare email obfuscation decode script', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'low'
            ]
        ];
    }
}