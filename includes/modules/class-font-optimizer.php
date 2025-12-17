<?php
/**
 * Font Optimization Module
 * 
 * Reduces Cumulative Layout Shift (CLS) and improves font loading
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSO_Font_Optimizer extends SSO_Base_Module {
    
    private $safety_validator;
    private $preloaded_fonts = [];
    
    public function __construct() {
        parent::__construct('fonts');
        $this->safety_validator = SSO_Safety_Validator::getInstance();
    }
    
    /**
     * Initialize module
     */
    public function init() {
        if (!$this->isSafeContext()) {
            return;
        }
        
        // Preload local fonts
        if ($this->isEnabled('preload_local')) {
            add_action('wp_head', [$this, 'preloadLocalFonts'], 2);
        }
        
        // Add font-display: swap
        if ($this->isEnabled('add_display_swap')) {
            add_action('wp_head', [$this, 'addFontDisplaySwap'], 1);
        }
        
        // Disable Google Fonts
        if ($this->isEnabled('disable_google')) {
            add_action('wp_enqueue_scripts', [$this, 'disableGoogleFonts'], 100);
            add_filter('style_loader_src', [$this, 'removeGoogleFontUrls'], 10, 2);
        }
        
        // Add preconnect for external font domains
        add_action('wp_head', [$this, 'addFontPreconnects'], 1);
    }
    
    /**
     * Preload critical local fonts
     */
    public function preloadLocalFonts() {
        $fonts_to_preload = $this->detectLocalFonts();
        
        if (empty($fonts_to_preload)) {
            return;
        }
        
        foreach ($fonts_to_preload as $font) {
            if (in_array($font['url'], $this->preloaded_fonts)) {
                continue;
            }
            
            $crossorigin = $this->needsCrossorigin($font['url']) ? ' crossorigin' : '';
            
            echo '<link rel="preload" as="font" type="' . esc_attr($font['type']) . '" href="' . esc_url($font['url']) . '"' . $crossorigin . '>' . "\n";
            
            $this->preloaded_fonts[] = $font['url'];
            $this->debug('Preloaded font: ' . $font['url']);
        }
    }
    
    /**
     * Detect local fonts in theme and uploads
     */
    private function detectLocalFonts() {
        $fonts = [];
        
        // Common font locations
        $font_paths = [
            get_template_directory() . '/fonts/',
            get_stylesheet_directory() . '/fonts/',
            WP_CONTENT_DIR . '/uploads/fonts/',
        ];
        
        $font_extensions = ['woff2', 'woff', 'ttf', 'otf', 'eot'];
        
        foreach ($font_paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                $extension = strtolower($file->getExtension());
                
                if (in_array($extension, $font_extensions)) {
                    $relative_path = str_replace(ABSPATH, '', $file->getPathname());
                    $url = home_url($relative_path);
                    
                    // Prioritize woff2 and common font names
                    $priority = $this->getFontPriority($file->getFilename(), $extension);
                    
                    if ($priority > 0) {
                        $fonts[] = [
                            'url' => $url,
                            'type' => $this->getFontMimeType($extension),
                            'priority' => $priority,
                            'filename' => $file->getFilename()
                        ];
                    }
                }
            }
        }
        
        // Sort by priority and return top 3-5 fonts
        usort($fonts, function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
        
        return array_slice($fonts, 0, 5);
    }
    
    /**
     * Get font priority for preloading
     */
    private function getFontPriority($filename, $extension) {
        $priority = 0;
        
        // Higher priority for woff2
        if ($extension === 'woff2') {
            $priority += 3;
        } elseif ($extension === 'woff') {
            $priority += 2;
        }
        
        // Higher priority for common font names
        $important_fonts = [
            'regular', 'normal', '400', 'bold', '700',
            'roboto', 'open-sans', 'lato', 'montserrat',
            'arial', 'helvetica', 'sans-serif'
        ];
        
        $filename_lower = strtolower($filename);
        foreach ($important_fonts as $font) {
            if (strpos($filename_lower, $font) !== false) {
                $priority += 2;
                break;
            }
        }
        
        return $priority;
    }
    
    /**
     * Get font MIME type
     */
    private function getFontMimeType($extension) {
        $types = [
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        return isset($types[$extension]) ? $types[$extension] : 'font/' . $extension;
    }
    
    /**
     * Check if font URL needs crossorigin attribute
     */
    private function needsCrossorigin($url) {
        $site_url = parse_url(home_url(), PHP_URL_HOST);
        $font_url = parse_url($url, PHP_URL_HOST);
        
        return $font_url !== null && $font_url !== $site_url;
    }
    
    /**
     * Add font-display: swap to all fonts
     */
    public function addFontDisplaySwap() {
        ?>
        <style id="sso-font-display">
        @font-face {
            font-display: swap;
        }
        </style>
        <?php
        
        $this->debug('Added font-display: swap');
    }
    
    /**
     * Disable Google Fonts
     */
    public function disableGoogleFonts() {
        global $wp_styles;
        
        if (!isset($wp_styles->registered)) {
            return;
        }
        
        foreach ($wp_styles->registered as $handle => $style) {
            if (strpos($style->src, 'fonts.googleapis.com') !== false) {
                wp_dequeue_style($handle);
                $this->debug('Disabled Google Font: ' . $handle);
            }
        }
    }
    
    /**
     * Remove Google Font URLs from style loader
     */
    public function removeGoogleFontUrls($src, $handle) {
        if (strpos($src, 'fonts.googleapis.com') !== false) {
            return false;
        }
        return $src;
    }
    
    /**
     * Add preconnect for external font domains
     */
    public function addFontPreconnects() {
        $font_domains = [
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
            'https://use.typekit.net',
            'https://cloud.typography.com'
        ];
        
        // Only add preconnects if Google Fonts are not disabled
        if ($this->isEnabled('disable_google')) {
            $font_domains = array_filter($font_domains, function($domain) {
                return strpos($domain, 'fonts.g') === false;
            });
        }
        
        foreach ($font_domains as $domain) {
            echo '<link rel="preconnect" href="' . esc_url($domain) . '" crossorigin>' . "\n";
        }
        
        if (!empty($font_domains)) {
            $this->debug('Added preconnects for font domains');
        }
    }
    
    /**
     * Get settings for admin interface
     */
    public function getSettings() {
        return [
            'preload_local' => [
                'title' => __('Preload Local Fonts', 'safe-speed-optimizer'),
                'description' => __('Automatically detects and preloads local font files', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'medium'
            ],
            'add_display_swap' => [
                'title' => __('Add Font-Display: Swap', 'safe-speed-optimizer'),
                'description' => __('Prevents invisible text during font swaps', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'high'
            ],
            'disable_google' => [
                'title' => __('Disable Google Fonts', 'safe-speed-optimizer'),
                'description' => __('Removes Google Fonts to eliminate external requests', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'medium',
                'advanced' => true
            ]
        ];
    }
}