<?php
/**
 * Preloading & Resource Hints Module
 * 
 * Low effort, high reward optimizations for critical resources
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSO_Preloading_Hints extends SSO_Base_Module {
    
    private $preloaded_resources = [];
    
    public function __construct() {
        parent::__construct('preloading');
    }
    
    /**
     * Initialize module
     */
    public function init() {
        if (!$this->isSafeContext()) {
            return;
        }
        
        // Preload LCP image
        if ($this->isEnabled('lcp_image')) {
            add_action('wp_head', [$this, 'preloadLcpImage'], 1);
        }
        
        // Preload fonts
        if ($this->isEnabled('fonts')) {
            add_action('wp_head', [$this, 'preloadFonts'], 2);
        }
        
        // DNS prefetch third-party domains
        if ($this->isEnabled('dns_prefetch_third_party')) {
            add_action('wp_head', [$this, 'addDnsPrefetch'], 1);
        }
        
        // Preconnect external APIs
        if ($this->isEnabled('preconnect_external')) {
            add_action('wp_head', [$this, 'addPreconnects'], 1);
        }
    }
    
    /**
     * Preload LCP (Largest Contentful Paint) image
     */
    public function preloadLcpImage() {
        $lcp_image = $this->detectLcpImage();
        
        if (!$lcp_image) {
            return;
        }
        
        $fetchpriority = 'high';
        $as = $this->getResourceType($lcp_image);
        
        echo '<link rel="preload" as="' . esc_attr($as) . '" href="' . esc_url($lcp_image) . '" fetchpriority="' . esc_attr($fetchpriority) . '">' . "\n";
        
        $this->preloaded_resources[] = $lcp_image;
        $this->debug('Preloaded LCP image: ' . $lcp_image);
    }
    
    /**
     * Detect LCP image based on page context
     */
    private function detectLcpImage() {
        global $post;
        
        // Try featured image first
        if (isset($post->ID) && has_post_thumbnail($post->ID)) {
            $featured_image_id = get_post_thumbnail_id($post->ID);
            $featured_image = wp_get_attachment_image_src($featured_image_id, 'large');
            
            if ($featured_image) {
                return $featured_image[0];
            }
        }
        
        // Try to detect from content
        if (isset($post->post_content)) {
            $content = $post->post_content;
            
            // Look for first image in content
            if (preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $content, $matches)) {
                $image_url = $matches[1];
                
                // Prefer larger images
                if (strpos($image_url, '-150x') === false && 
                    strpos($image_url, '-300x') === false && 
                    strpos($image_url, 'thumbnail') === false) {
                    return $image_url;
                }
            }
        }
        
        // Try theme-specific locations
        $theme_lcp_candidates = [
            get_template_directory_uri() . '/images/hero.jpg',
            get_template_directory_uri() . '/images/hero.png',
            get_template_directory_uri() . '/images/banner.jpg',
            get_template_directory_uri() . '/assets/images/hero.jpg',
        ];
        
        foreach ($theme_lcp_candidates as $candidate) {
            $file_path = str_replace(get_template_directory_uri(), get_template_directory(), $candidate);
            if (file_exists($file_path)) {
                return $candidate;
            }
        }
        
        return false;
    }
    
    /**
     * Preload important fonts
     */
    public function preloadFonts() {
        $fonts_to_preload = $this->getFontPreloadCandidates();
        
        foreach ($fonts_to_preload as $font) {
            if (in_array($font['url'], $this->preloaded_resources)) {
                continue;
            }
            
            $crossorigin = $this->needsCrossorigin($font['url']) ? ' crossorigin' : '';
            
            echo '<link rel="preload" as="font" type="' . esc_attr($font['type']) . '" href="' . esc_url($font['url']) . '"' . $crossorigin . '>' . "\n";
            
            $this->preloaded_resources[] = $font['url'];
            $this->debug('Preloaded font: ' . $font['url']);
        }
    }
    
    /**
     * Get font preload candidates
     */
    private function getFontPreloadCandidates() {
        $fonts = [];
        
        // Check for common font locations
        $font_directories = [
            get_template_directory() . '/fonts/',
            get_stylesheet_directory() . '/fonts/',
            WP_CONTENT_DIR . '/uploads/fonts/',
        ];
        
        foreach ($font_directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            
            $font_files = glob($dir . '*.{woff2,woff}', GLOB_BRACE);
            
            foreach ($font_files as $font_file) {
                $filename = basename($font_file);
                $extension = pathinfo($font_file, PATHINFO_EXTENSION);
                
                // Prioritize regular/normal weight fonts
                if (preg_match('/(regular|normal|400|bold|700)/i', $filename)) {
                    $relative_path = str_replace(ABSPATH, '', $font_file);
                    $font_url = home_url($relative_path);
                    
                    $fonts[] = [
                        'url' => $font_url,
                        'type' => $this->getFontMimeType($extension),
                        'weight' => $this->extractFontWeight($filename)
                    ];
                    
                    // Limit to 3 most important fonts
                    if (count($fonts) >= 3) {
                        break 2;
                    }
                }
            }
        }
        
        return $fonts;
    }
    
    /**
     * Add DNS prefetch hints
     */
    public function addDnsPrefetch() {
        $third_party_domains = $this->detectThirdPartyDomains();
        
        foreach ($third_party_domains as $domain) {
            echo '<link rel="dns-prefetch" href="' . esc_url($domain) . '">' . "\n";
        }
        
        if (!empty($third_party_domains)) {
            $this->debug('Added DNS prefetch for ' . count($third_party_domains) . ' domains');
        }
    }
    
    /**
     * Add preconnect hints for external APIs
     */
    public function addPreconnects() {
        $preconnect_domains = [
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
            'https://www.google-analytics.com',
            'https://www.googletagmanager.com',
            'https://connect.facebook.net',
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com'
        ];
        
        foreach ($preconnect_domains as $domain) {
            $crossorigin = (strpos($domain, 'fonts.') !== false) ? ' crossorigin' : '';
            echo '<link rel="preconnect" href="' . esc_url($domain) . '"' . $crossorigin . '>' . "\n";
        }
        
        $this->debug('Added preconnects for external domains');
    }
    
    /**
     * Detect third-party domains from current page
     */
    private function detectThirdPartyDomains() {
        global $wp_scripts, $wp_styles;
        
        $domains = [];
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        
        // Check enqueued scripts
        if (isset($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $script) {
                if (!empty($script->src)) {
                    $parsed = parse_url($script->src);
                    if (isset($parsed['host']) && $parsed['host'] !== $site_host) {
                        $domains[] = $parsed['scheme'] . '://' . $parsed['host'];
                    }
                }
            }
        }
        
        // Check enqueued styles
        if (isset($wp_styles->registered)) {
            foreach ($wp_styles->registered as $style) {
                if (!empty($style->src)) {
                    $parsed = parse_url($style->src);
                    if (isset($parsed['host']) && $parsed['host'] !== $site_host) {
                        $domains[] = $parsed['scheme'] . '://' . $parsed['host'];
                    }
                }
            }
        }
        
        return array_unique($domains);
    }
    
    /**
     * Get resource type for preload
     */
    private function getResourceType($url) {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        $types = [
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'webp' => 'image',
            'svg' => 'image',
            'woff2' => 'font',
            'woff' => 'font',
            'ttf' => 'font',
            'otf' => 'font',
            'css' => 'style',
            'js' => 'script'
        ];
        
        return isset($types[$extension]) ? $types[$extension] : 'image';
    }
    
    /**
     * Get font MIME type
     */
    private function getFontMimeType($extension) {
        $types = [
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf'
        ];
        
        return isset($types[$extension]) ? $types[$extension] : 'font/' . $extension;
    }
    
    /**
     * Extract font weight from filename
     */
    private function extractFontWeight($filename) {
        if (preg_match('/(\d{3})/', $filename, $matches)) {
            return (int)$matches[1];
        }
        
        if (stripos($filename, 'bold') !== false) {
            return 700;
        }
        
        if (stripos($filename, 'light') !== false) {
            return 300;
        }
        
        return 400; // normal weight
    }
    
    /**
     * Check if resource needs crossorigin
     */
    private function needsCrossorigin($url) {
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        $resource_host = parse_url($url, PHP_URL_HOST);
        
        return $resource_host !== null && $resource_host !== $site_host;
    }
    
    /**
     * Get settings for admin interface
     */
    public function getSettings() {
        return [
            'lcp_image' => [
                'title' => __('Preload LCP Image', 'safe-speed-optimizer'),
                'description' => __('Automatically detects and preloads the Largest Contentful Paint image', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'high'
            ],
            'fonts' => [
                'title' => __('Preload Fonts', 'safe-speed-optimizer'),
                'description' => __('Preloads important font files to prevent font swap delays', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'medium'
            ],
            'dns_prefetch_third_party' => [
                'title' => __('DNS Prefetch Third-Party Domains', 'safe-speed-optimizer'),
                'description' => __('Adds DNS prefetch hints for external domains', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'low'
            ],
            'preconnect_external' => [
                'title' => __('Preconnect External APIs', 'safe-speed-optimizer'),
                'description' => __('Adds preconnect hints for common external services', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'low'
            ]
        ];
    }
}