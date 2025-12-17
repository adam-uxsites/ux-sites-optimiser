<?php
/**
 * Image Optimization Module
 * 
 * Safe image optimizations that improve loading and layout stability
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSO_Image_Optimizer extends SSO_Base_Module {
    
    private $safety_validator;
    
    public function __construct() {
        parent::__construct('images');
        $this->safety_validator = SSO_Safety_Validator::getInstance();
    }
    
    /**
     * Initialize module
     */
    public function init() {
        if (!$this->isSafeContext()) {
            return;
        }
        
        // Add missing dimensions
        if ($this->isEnabled('add_dimensions')) {
            add_filter('the_content', [$this, 'addMissingDimensions']);
            add_filter('post_thumbnail_html', [$this, 'addMissingDimensions']);
        }
        
        // Lazy load images
        if ($this->isEnabled('lazy_load')) {
            add_filter('the_content', [$this, 'addLazyLoading']);
            add_filter('post_thumbnail_html', [$this, 'addLazyLoading']);
            add_filter('wp_get_attachment_image_attributes', [$this, 'addLazyLoadingToAttachments'], 10, 2);
        }
    }
    
    /**
     * Add missing width and height attributes to images
     */
    public function addMissingDimensions($content) {
        if (empty($content)) {
            return $content;
        }
        
        // Find all img tags
        preg_match_all('/<img[^>]+>/i', $content, $matches);
        
        if (empty($matches[0])) {
            return $content;
        }
        
        foreach ($matches[0] as $img_tag) {
            $new_img_tag = $this->processImageDimensions($img_tag);
            if ($new_img_tag !== $img_tag) {
                $content = str_replace($img_tag, $new_img_tag, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Process individual image tag for dimensions
     */
    private function processImageDimensions($img_tag) {
        // Skip if already has both width and height
        if (preg_match('/\swidth\s*=/', $img_tag) && preg_match('/\sheight\s*=/', $img_tag)) {
            return $img_tag;
        }
        
        // Extract src attribute
        if (!preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $img_tag, $src_match)) {
            return $img_tag;
        }
        
        $src = $src_match[1];
        
        // Skip external images or data URLs
        if (!$this->isInternalImage($src) || strpos($src, 'data:') === 0) {
            return $img_tag;
        }
        
        // Get image dimensions
        $dimensions = $this->getImageDimensions($src);
        
        if (!$dimensions) {
            return $img_tag;
        }
        
        // Add missing dimensions
        $modified_tag = $img_tag;
        
        if (!preg_match('/\swidth\s*=/', $modified_tag)) {
            $modified_tag = str_replace('<img ', '<img width="' . $dimensions['width'] . '" ', $modified_tag);
        }
        
        if (!preg_match('/\sheight\s*=/', $modified_tag)) {
            $modified_tag = str_replace('<img ', '<img height="' . $dimensions['height'] . '" ', $modified_tag);
        }
        
        $this->debug('Added dimensions to image: ' . basename($src));
        
        return $modified_tag;
    }
    
    /**
     * Get image dimensions from various sources
     */
    private function getImageDimensions($src) {
        // Try to get from WordPress attachment
        $attachment_id = $this->getAttachmentIdFromUrl($src);
        if ($attachment_id) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (isset($metadata['width'], $metadata['height'])) {
                return [
                    'width' => $metadata['width'],
                    'height' => $metadata['height']
                ];
            }
        }
        
        // Try to get from file system
        $file_path = $this->urlToPath($src);
        if ($file_path && file_exists($file_path)) {
            $size = getimagesize($file_path);
            if ($size) {
                return [
                    'width' => $size[0],
                    'height' => $size[1]
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Add lazy loading to images
     */
    public function addLazyLoading($content) {
        if (empty($content)) {
            return $content;
        }
        
        // Find all img tags that don't already have loading attribute
        preg_match_all('/<img(?![^>]*\sloading\s*=)[^>]+>/i', $content, $matches);
        
        if (empty($matches[0])) {
            return $content;
        }
        
        $excluded_selectors = $this->getExcludedSelectors();
        
        foreach ($matches[0] as $img_tag) {
            // Skip if image should be excluded from lazy loading
            if ($this->shouldExcludeFromLazyLoad($img_tag, $excluded_selectors)) {
                continue;
            }
            
            // Add loading="lazy" attribute
            $lazy_img_tag = str_replace('<img ', '<img loading="lazy" ', $img_tag);
            $content = str_replace($img_tag, $lazy_img_tag, $content);
            
            $this->debug('Added lazy loading to image');
        }
        
        return $content;
    }
    
    /**
     * Add lazy loading to attachment images
     */
    public function addLazyLoadingToAttachments($attr, $attachment) {
        if (!isset($attr['loading'])) {
            $excluded_selectors = $this->getExcludedSelectors();
            
            // Check if this attachment should be excluded
            $should_exclude = false;
            
            // Check by class
            if (isset($attr['class'])) {
                foreach ($excluded_selectors as $selector) {
                    if (strpos($selector, '.') === 0 && strpos($attr['class'], substr($selector, 1)) !== false) {
                        $should_exclude = true;
                        break;
                    }
                }
            }
            
            if (!$should_exclude) {
                $attr['loading'] = 'lazy';
            }
        }
        
        return $attr;
    }
    
    /**
     * Check if image should be excluded from lazy loading
     */
    private function shouldExcludeFromLazyLoad($img_tag, $excluded_selectors) {
        foreach ($excluded_selectors as $selector) {
            $selector = trim($selector);
            
            if (empty($selector)) {
                continue;
            }
            
            // Class selector
            if (strpos($selector, '.') === 0) {
                $class = substr($selector, 1);
                if (preg_match('/\sclass\s*=\s*["\'][^"\']*' . preg_quote($class) . '[^"\']*["\']/i', $img_tag)) {
                    return true;
                }
            }
            
            // ID selector
            if (strpos($selector, '#') === 0) {
                $id = substr($selector, 1);
                if (preg_match('/\sid\s*=\s*["\']' . preg_quote($id) . '["\']/i', $img_tag)) {
                    return true;
                }
            }
            
            // Alt attribute contains selector
            if (preg_match('/\salt\s*=\s*["\'][^"\']*' . preg_quote($selector) . '[^"\']*["\']/i', $img_tag)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get excluded selectors for lazy loading
     */
    private function getExcludedSelectors() {
        $excluded = $this->getOption('exclude_above_fold', '.logo img, .hero-image, #header img');
        
        if (is_string($excluded)) {
            $excluded = preg_split('/[,\n\r]+/', $excluded);
            $excluded = array_map('trim', $excluded);
            $excluded = array_filter($excluded);
        }
        
        // Always exclude common above-the-fold elements
        $always_excluded = [
            '.logo',
            '.site-logo',
            '.header-logo',
            '.hero-image',
            '.banner-image',
            '#logo',
            '#site-logo'
        ];
        
        return array_merge($always_excluded, $excluded);
    }
    
    /**
     * Check if image URL is internal
     */
    private function isInternalImage($src) {
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
     * Convert URL to file path
     */
    private function urlToPath($url) {
        $upload_dir = wp_upload_dir();
        
        if (strpos($url, $upload_dir['baseurl']) === 0) {
            return str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        }
        
        // Handle theme URLs
        $theme_url = get_template_directory_uri();
        if (strpos($url, $theme_url) === 0) {
            return str_replace($theme_url, get_template_directory(), $url);
        }
        
        return false;
    }
    
    /**
     * Get attachment ID from image URL
     */
    private function getAttachmentIdFromUrl($url) {
        return attachment_url_to_postid($url);
    }
    
    /**
     * Get settings for admin interface
     */
    public function getSettings() {
        return [
            'add_dimensions' => [
                'title' => __('Add Missing Width and Height', 'safe-speed-optimizer'),
                'description' => __('Prevents layout shifts by adding dimensions to images', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'high'
            ],
            'lazy_load' => [
                'title' => __('Lazy Load Images', 'safe-speed-optimizer'),
                'description' => __('Adds native lazy loading to images below the fold', 'safe-speed-optimizer'),
                'type' => 'checkbox',
                'impact' => 'high'
            ],
            'exclude_above_fold' => [
                'title' => __('Exclude Above-the-Fold Images', 'safe-speed-optimizer'),
                'description' => __('CSS selectors for images that should not be lazy loaded', 'safe-speed-optimizer'),
                'type' => 'textarea',
                'rows' => 3
            ]
        ];
    }
    
    /**
     * Sanitize options
     */
    public function sanitizeOptions($options) {
        if (isset($options['exclude_above_fold'])) {
            $options['exclude_above_fold'] = sanitize_textarea_field($options['exclude_above_fold']);
        }
        
        return $options;
    }
}