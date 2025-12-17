<?php
/**
 * Admin Interface Class
 * 
 * Handles the WordPress admin interface for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSO_Admin_Interface {
    
    private $page_hook;
    
    public function __construct() {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        add_filter('plugin_action_links_' . SSO_PLUGIN_BASENAME, [$this, 'addActionLinks']);
        
        // AJAX handlers
        add_action('wp_ajax_sso_check_updates', [$this, 'ajax_check_updates']);
    }
    
    /**
     * Add menu page
     */
    public function addMenuPage() {
        $this->page_hook = add_options_page(
            __('UX Sites Optimiser', 'ux-sites-optimiser'),
            __('UX Sites Optimiser', 'ux-sites-optimiser'),
            'manage_options',
            'ux-sites-optimiser',
            [$this, 'renderPage']
        );
    }
    
    /**
     * Register settings
     */
    public function registerSettings() {
        // Global settings
        register_setting('sso_global_settings', 'sso_affect_logged_in_users', ['type' => 'boolean']);
        
        // JavaScript settings
        register_setting('sso_javascript_settings', 'sso_js_move_jquery_footer', ['type' => 'boolean']);
        register_setting('sso_javascript_settings', 'sso_js_defer_non_critical', ['type' => 'boolean']);
        register_setting('sso_javascript_settings', 'sso_js_delay_until_interaction', ['type' => 'boolean']);
        register_setting('sso_javascript_settings', 'sso_js_excluded_scripts', ['type' => 'string']);
        
        // CSS settings
        register_setting('sso_css_settings', 'sso_css_inline_critical', ['type' => 'boolean']);
        register_setting('sso_css_settings', 'sso_css_defer_non_critical', ['type' => 'boolean']);
        register_setting('sso_css_settings', 'sso_css_critical_css', ['type' => 'string']);
        
        // Font settings
        register_setting('sso_font_settings', 'sso_fonts_preload_local', ['type' => 'boolean']);
        register_setting('sso_font_settings', 'sso_fonts_add_display_swap', ['type' => 'boolean']);
        register_setting('sso_font_settings', 'sso_fonts_disable_google', ['type' => 'boolean']);
        
        // Image settings
        register_setting('sso_image_settings', 'sso_images_add_dimensions', ['type' => 'boolean']);
        register_setting('sso_image_settings', 'sso_images_lazy_load', ['type' => 'boolean']);
        register_setting('sso_image_settings', 'sso_images_exclude_above_fold', ['type' => 'string']);
        
        // Core cleanup settings
        register_setting('sso_core_settings', 'sso_core_remove_wp_embed', ['type' => 'boolean']);
        register_setting('sso_core_settings', 'sso_core_remove_dashicons_logged_out', ['type' => 'boolean']);
        register_setting('sso_core_settings', 'sso_core_disable_xmlrpc', ['type' => 'boolean']);
        register_setting('sso_core_settings', 'sso_core_remove_rest_links', ['type' => 'boolean']);
        register_setting('sso_core_settings', 'sso_core_remove_query_strings', ['type' => 'boolean']);
        
        // Third-party settings
        register_setting('sso_third_party_settings', 'sso_third_party_delay_analytics', ['type' => 'boolean']);
        register_setting('sso_third_party_settings', 'sso_third_party_delay_tracking', ['type' => 'boolean']);
        
        // Preloading settings
        register_setting('sso_preloading_settings', 'sso_preload_lcp_image', ['type' => 'boolean']);
        register_setting('sso_preloading_settings', 'sso_preload_fonts', ['type' => 'boolean']);
        register_setting('sso_preloading_settings', 'sso_dns_prefetch_third_party', ['type' => 'boolean']);
        
        // Update settings
        register_setting('sso_update_settings', 'sso_github_repo', ['type' => 'string']);
        register_setting('sso_update_settings', 'sso_update_server', ['type' => 'string']);
        register_setting('sso_update_settings', 'sso_license_key', ['type' => 'string']);
        register_setting('sso_update_settings', 'sso_auto_updates', ['type' => 'boolean']);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueScripts($hook) {
        if ($hook !== $this->page_hook) {
            return;
        }
        
        wp_enqueue_style('sso-admin', SSO_PLUGIN_URL . 'admin/css/admin.css', [], SSO_PLUGIN_VERSION);
        wp_enqueue_script('sso-admin', SSO_PLUGIN_URL . 'admin/js/admin.js', ['jquery'], SSO_PLUGIN_VERSION, true);
    }
    
    /**
     * Add action links to plugin page
     */
    public function addActionLinks($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=ux-sites-optimiser') . '">' . __('Settings', 'ux-sites-optimiser') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Render admin page
     */
    public function renderPage() {
        // Get active tab first since it's used in redirects
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'javascript';
        
        // Debug: Log all POST data
        if (!empty($_POST)) {
            error_log('SSO: POST data received: ' . print_r($_POST, true));
            echo '<div class="notice notice-info"><p><strong>DEBUG:</strong> POST request received with keys: ' . implode(', ', array_keys($_POST)) . '</p></div>';
        }
        
        if (isset($_POST['preset_type']) && isset($_POST['sso_preset_nonce'])) {
            error_log('SSO: preset_type found in POST');
            echo '<div class="notice notice-info"><p><strong>DEBUG:</strong> Applying preset: ' . ($_POST['preset_type'] ?? 'unknown') . '</p></div>';
            if (wp_verify_nonce($_POST['sso_preset_nonce'], 'sso_preset_nonce')) {
                error_log('SSO: Nonce verified successfully');
                echo '<div class="notice notice-success"><p><strong>DEBUG:</strong> Nonce verified, calling applyPreset()</p></div>';
                $preset_type = $_POST['preset_type'] ?? 'safe';
                if ($this->applyPreset($preset_type)) {
                    echo '<div class="notice notice-success"><p><strong>SUCCESS:</strong> Preset "' . ucfirst($preset_type) . '" applied successfully!</p></div>';
                    // Redirect to refresh the page and show updated settings
                    $redirect_url = add_query_arg(array(
                        'page' => 'ux-sites-optimiser',
                        'tab' => $active_tab,
                        'preset_applied' => $preset_type
                    ), admin_url('options-general.php'));
                    echo '<script>setTimeout(function(){ window.location.href = "' . esc_url($redirect_url) . '"; }, 1000);</script>';
                } else {
                    echo '<div class="notice notice-error"><p><strong>ERROR:</strong> Failed to apply preset.</p></div>';
                }
            } else {
                error_log('SSO: Nonce verification FAILED');
                echo '<div class="notice notice-error"><p>Security check failed. Please try again.</p></div>';
            }
        } elseif (isset($_POST['submit']) && wp_verify_nonce($_POST['sso_nonce'], 'sso_save_settings')) {
            $this->saveSettings();
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('UX Sites Optimiser', 'ux-sites-optimiser'); ?></h1>
            
            <?php 
            // Show preset applied success message
            if (isset($_GET['preset_applied'])) {
                $applied_preset = sanitize_text_field($_GET['preset_applied']);
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('%s preset applied successfully! All settings have been updated.', 'ux-sites-optimiser'), ucfirst($applied_preset)) . '</p></div>';
            }
            ?>
            
            <!-- Preset Selector -->
            <div class="sso-presets" style="background: #f1f1f1; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3><?php _e('Quick Setup Presets', 'ux-sites-optimiser'); ?></h3>
                <?php 
                $current_preset = $this->detectCurrentPreset();
                $stored_preset = get_option('sso_current_preset', '');
                if ($current_preset) {
                    echo '<p><strong>' . sprintf(__('Current preset: %s', 'ux-sites-optimiser'), '<span style="color: #0073aa;">' . ucfirst($current_preset) . '</span>') . '</strong></p>';
                } else {
                    echo '<p><em>' . __('Custom settings (no preset applied)', 'ux-sites-optimiser') . '</em></p>';
                }
                // Debug info
                if (current_user_can('manage_options') && isset($_GET['debug'])) {
                    echo '<p style="font-size: 11px; color: #666;">Debug: Stored=' . $stored_preset . ', Detected=' . $current_preset . '</p>';
                }
                ?>
                <p><?php _e('Choose a preset to automatically configure all optimization settings:', 'ux-sites-optimiser'); ?></p>
                <form method="post" action="" style="display: inline-block;" id="preset-form">
                    <?php wp_nonce_field('sso_preset_nonce', 'sso_preset_nonce'); ?>
                    <select name="preset_type" id="preset-selector" style="margin-right: 10px;">
                        <option value="safe" <?php selected($current_preset, 'safe'); ?>><?php _e('Safe - Zero-risk optimizations only', 'ux-sites-optimiser'); ?></option>
                        <option value="medium" <?php selected($current_preset, 'medium'); ?>><?php _e('Medium - Balanced performance gains', 'ux-sites-optimiser'); ?></option>
                        <option value="risky" <?php selected($current_preset, 'risky'); ?>><?php _e('Risky - Maximum performance (test first!)', 'ux-sites-optimiser'); ?></option>
                    </select>
                    <button type="button" id="preview-preset" class="button button-secondary" style="margin-right: 10px;"><?php _e('Preview Settings', 'ux-sites-optimiser'); ?></button>
                    <input type="submit" name="apply_preset" class="button button-secondary" value="<?php _e('Apply Preset', 'ux-sites-optimiser'); ?>">
                </form>
                
                <!-- Preset Preview -->
                <div id="preset-preview" style="display: none; background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-top: 15px;">
                    <h4 style="margin-top: 0;"><?php _e('Preset Settings Preview', 'ux-sites-optimiser'); ?></h4>
                    <div id="preset-settings-list"></div>
                    <button type="button" id="close-preview" class="button button-small"><?php _e('Close Preview', 'ux-sites-optimiser'); ?></button>
                </div>
                <p class="description"><?php _e('Note: Applying a preset will overwrite your current settings. Critical CSS must still be configured manually.', 'ux-sites-optimiser'); ?></p>
            </div>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=ux-sites-optimiser&tab=javascript" class="nav-tab <?php echo $active_tab === 'javascript' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('JavaScript', 'ux-sites-optimiser'); ?>
                </a>
                <a href="?page=ux-sites-optimiser&tab=css" class="nav-tab <?php echo $active_tab === 'css' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('CSS', 'ux-sites-optimiser'); ?>
                </a>
                <a href="?page=ux-sites-optimiser&tab=fonts" class="nav-tab <?php echo $active_tab === 'fonts' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Fonts', 'ux-sites-optimiser'); ?>
                </a>
                <a href="?page=ux-sites-optimiser&tab=images" class="nav-tab <?php echo $active_tab === 'images' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Images', 'ux-sites-optimiser'); ?>
                </a>
                <a href="?page=ux-sites-optimiser&tab=core" class="nav-tab <?php echo $active_tab === 'core' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Core Cleanup', 'ux-sites-optimiser'); ?>
                </a>
                <a href="?page=ux-sites-optimiser&tab=third-party" class="nav-tab <?php echo $active_tab === 'third-party' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Third-Party', 'ux-sites-optimiser'); ?>
                </a>
                <a href="?page=ux-sites-optimiser&tab=preloading" class="nav-tab <?php echo $active_tab === 'preloading' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Preloading', 'ux-sites-optimiser'); ?>
                </a>
                <a href="?page=ux-sites-optimiser&tab=global" class="nav-tab <?php echo $active_tab === 'global' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Global Settings', 'ux-sites-optimiser'); ?>
                </a>
                <a href="?page=ux-sites-optimiser&tab=updates" class="nav-tab <?php echo $active_tab === 'updates' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Updates & Licensing', 'ux-sites-optimiser'); ?>
                </a>
            </nav>
            
            <form method="post" action="">
                <?php wp_nonce_field('sso_save_settings', 'sso_nonce'); ?>
                <input type="hidden" name="active_tab" value="<?php echo esc_attr($active_tab); ?>">
                
                <div class="tab-content">
                    <?php
                    switch ($active_tab) {
                        case 'javascript':
                            $this->renderJavaScriptTab();
                            break;
                        case 'css':
                            $this->renderCSSTab();
                            break;
                        case 'fonts':
                            $this->renderFontsTab();
                            break;
                        case 'images':
                            $this->renderImagesTab();
                            break;
                        case 'core':
                            $this->renderCoreTab();
                            break;
                        case 'third-party':
                            $this->renderThirdPartyTab();
                            break;
                        case 'preloading':
                            $this->renderPreloadingTab();
                            break;
                        case 'global':
                            $this->renderGlobalTab();
                            break;
                        case 'updates':
                            $this->renderUpdatesTab();
                            break;
                    }
                    ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <!-- Custom Confirmation Modal -->
        <div id="sso-modal-overlay" class="sso-modal-overlay" style="display: none;">
            <div class="sso-modal">
                <div class="sso-modal-header">
                    <h3 id="sso-modal-title"><?php _e('Confirm Preset Application', 'ux-sites-optimiser'); ?></h3>
                    <button type="button" id="sso-modal-close" class="sso-modal-close">&times;</button>
                </div>
                <div class="sso-modal-body">
                    <p id="sso-modal-message"></p>
                    <div id="sso-modal-settings-preview"></div>
                </div>
                <div class="sso-modal-footer">
                    <button type="button" id="sso-modal-cancel" class="button button-secondary"><?php _e('Cancel', 'ux-sites-optimiser'); ?></button>
                    <button type="button" id="sso-modal-confirm" class="button button-primary"><?php _e('Apply Preset', 'ux-sites-optimiser'); ?></button>
                </div>
            </div>
        </div>
        
        <script>
            // Preset configurations for JavaScript
            window.ssoPresets = {
                safe: {
                    name: '<?php _e('Safe', 'ux-sites-optimiser'); ?>',
                    description: '<?php _e('Zero-risk optimizations that are safe for all sites', 'ux-sites-optimiser'); ?>',
                    settings: {
                        'Move jQuery to Footer': true,
                        'Defer JavaScript': false,
                        'Delay JavaScript Until Interaction': false,
                        'Inline Critical CSS': false,
                        'Defer Non-Critical CSS': false,
                        'Preload Local Fonts': true,
                        'Add Font Display Swap': true,
                        'Disable Google Fonts': false,
                        'Add Image Dimensions': true,
                        'Lazy Load Images': true,
                        'Remove WP Embed': true,
                        'Remove Dashicons (Logged Out)': true,
                        'Disable XML-RPC': true,
                        'Remove REST API Links': true,
                        'Remove Query Strings': true,
                        'Delay Analytics': false,
                        'Delay Tracking Scripts': false,
                        'Preload LCP Image': true,
                        'Preload Fonts': true,
                        'DNS Prefetch Third-Party': true
                    }
                },
                medium: {
                    name: '<?php _e('Medium', 'ux-sites-optimiser'); ?>',
                    description: '<?php _e('Balanced optimizations with some JavaScript/CSS deferring', 'ux-sites-optimiser'); ?>',
                    settings: {
                        'Move jQuery to Footer': true,
                        'Defer JavaScript': true,
                        'Delay JavaScript Until Interaction': false,
                        'Inline Critical CSS': false,
                        'Defer Non-Critical CSS': true,
                        'Preload Local Fonts': true,
                        'Add Font Display Swap': true,
                        'Disable Google Fonts': false,
                        'Add Image Dimensions': true,
                        'Lazy Load Images': true,
                        'Remove WP Embed': true,
                        'Remove Dashicons (Logged Out)': true,
                        'Disable XML-RPC': true,
                        'Remove REST API Links': true,
                        'Remove Query Strings': true,
                        'Delay Analytics': true,
                        'Delay Tracking Scripts': false,
                        'Preload LCP Image': true,
                        'Preload Fonts': true,
                        'DNS Prefetch Third-Party': true
                    }
                },
                risky: {
                    name: '<?php _e('Risky', 'ux-sites-optimiser'); ?>',
                    description: '<?php _e('Aggressive optimizations - test thoroughly before using on production', 'ux-sites-optimiser'); ?>',
                    settings: {
                        'Move jQuery to Footer': true,
                        'Defer JavaScript': true,
                        'Delay JavaScript Until Interaction': true,
                        'Inline Critical CSS': false,
                        'Defer Non-Critical CSS': true,
                        'Preload Local Fonts': true,
                        'Add Font Display Swap': true,
                        'Disable Google Fonts': true,
                        'Add Image Dimensions': true,
                        'Lazy Load Images': true,
                        'Remove WP Embed': true,
                        'Remove Dashicons (Logged Out)': true,
                        'Disable XML-RPC': true,
                        'Remove REST API Links': true,
                        'Remove Query Strings': true,
                        'Delay Analytics': true,
                        'Delay Tracking Scripts': true,
                        'Preload LCP Image': true,
                        'Preload Fonts': true,
                        'DNS Prefetch Third-Party': true
                    }
                }
            };
        </script>
        
        <style>
        .sso-notice {
            background: #e7f5e7;
            border: 1px solid #4caf50;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .sso-notice h3 {
            color: #2e7d32;
            margin-top: 0;
        }
        .sso-setting {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .sso-setting h4 {
            margin-top: 0;
            color: #333;
        }
        .sso-description {
            color: #666;
            font-style: italic;
            margin-bottom: 10px;
        }
        .sso-impact {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .impact-high { background: #4caf50; color: white; }
        .impact-medium { background: #ff9800; color: white; }
        .impact-low { background: #2196f3; color: white; }
        .impact-safe { background: #e7f5e7; color: #2e7d32; }
        </style>
        <?php
    }
    
    /**
     * Render JavaScript settings tab
     */
    private function renderJavaScriptTab() {
        ?>
        <h2><?php _e('JavaScript Optimization', 'ux-sites-optimiser'); ?></h2>
        <p><?php _e('High impact optimizations for JavaScript loading and execution.', 'ux-sites-optimiser'); ?></p>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Move jQuery to Footer', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-high"><?php _e('High Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Moves jQuery to the footer to prevent render blocking. Safe for most themes.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_js_move_jquery_footer" value="1" <?php checked(get_option('sso_js_move_jquery_footer', true)); ?> />
                <?php _e('Enable jQuery footer loading', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Defer Non-Critical JavaScript', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-high"><?php _e('High Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Adds defer attribute to JavaScript files to prevent render blocking.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_js_defer_non_critical" value="1" <?php checked(get_option('sso_js_defer_non_critical', false)); ?> />
                <?php _e('Defer JavaScript loading', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Delay JavaScript Until User Interaction', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-high"><?php _e('High Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Delays JavaScript execution until user clicks, scrolls, or touches. Use with caution.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_js_delay_until_interaction" value="1" <?php checked(get_option('sso_js_delay_until_interaction', false)); ?> />
                <?php _e('Delay JavaScript until interaction', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4><?php _e('Excluded Scripts', 'ux-sites-optimiser'); ?></h4>
            <p class="sso-description"><?php _e('Script handles to exclude from optimization. One per line or comma-separated.', 'ux-sites-optimiser'); ?></p>
            <textarea name="sso_js_excluded_scripts" rows="4" cols="50" placeholder="jquery-core, contact-form-7"><?php echo esc_textarea(get_option('sso_js_excluded_scripts', 'jquery-core')); ?></textarea>
        </div>
        <?php
    }
    
    /**
     * Render CSS settings tab
     */
    private function renderCSSTab() {
        ?>
        <h2><?php _e('CSS Optimization', 'ux-sites-optimiser'); ?></h2>
        <p><?php _e('Critical for improving Largest Contentful Paint (LCP) scores.', 'ux-sites-optimiser'); ?></p>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Defer Non-Critical CSS', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-high"><?php _e('High Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Loads CSS asynchronously to prevent render blocking.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_css_defer_non_critical" value="1" <?php checked(get_option('sso_css_defer_non_critical', false)); ?> />
                <?php _e('Defer CSS loading', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Inline Critical CSS', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-medium"><?php _e('Medium Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Inlines critical CSS to eliminate render-blocking. Requires manual CSS input for safety.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_css_inline_critical" value="1" <?php checked(get_option('sso_css_inline_critical', false)); ?> />
                <?php _e('Enable critical CSS inlining', 'ux-sites-optimiser'); ?>
            </label>
            <br><br>
            <label for="sso_css_critical_css"><?php _e('Critical CSS:', 'ux-sites-optimiser'); ?></label><br>
            <textarea name="sso_css_critical_css" id="sso_css_critical_css" rows="15" cols="80" placeholder="/* Add your critical CSS here - see instructions below */"><?php echo esc_textarea(get_option('sso_css_critical_css', '')); ?></textarea>
            <div class="sso-help-text">
                <h4><?php _e('TailwindCSS Critical CSS Guide:', 'ux-sites-optimiser'); ?></h4>
                <p><?php _e('For TailwindCSS sites, include only the CSS needed for above-the-fold content:', 'ux-sites-optimiser'); ?></p>
                <ul>
                    <li><strong><?php _e('Essential base:', 'ux-sites-optimiser'); ?></strong> <?php _e('Tailwind\'s reset/normalize if using custom fonts', 'ux-sites-optimiser'); ?></li>
                    <li><strong><?php _e('Layout utilities:', 'ux-sites-optimiser'); ?></strong> <?php _e('container, flex, grid, block, hidden (for visible elements)', 'ux-sites-optimiser'); ?></li>
                    <li><strong><?php _e('Typography:', 'ux-sites-optimiser'); ?></strong> <?php _e('text-*, font-*, leading-* for headings and visible text', 'ux-sites-optimiser'); ?></li>
                    <li><strong><?php _e('Spacing:', 'ux-sites-optimiser'); ?></strong> <?php _e('p-*, m-*, space-* for hero section and navigation', 'ux-sites-optimiser'); ?></li>
                    <li><strong><?php _e('Colors:', 'ux-sites-optimiser'); ?></strong> <?php _e('bg-*, text-* for above-the-fold background and text colors', 'ux-sites-optimiser'); ?></li>
                </ul>
                <p><strong><?php _e('How to extract:', 'ux-sites-optimiser'); ?></strong></p>
                <ol>
                    <li><?php _e('Use browser DevTools to inspect your hero section/header', 'ux-sites-optimiser'); ?></li>
                    <li><?php _e('Note all Tailwind classes used in the viewport', 'ux-sites-optimiser'); ?></li>
                    <li><?php _e('Copy corresponding CSS rules from your compiled stylesheet', 'ux-sites-optimiser'); ?></li>
                    <li><?php _e('Paste them above - typically 2-5KB for most hero sections', 'ux-sites-optimiser'); ?></li>
                </ol>
                <p><em><?php _e('Example: If your hero uses "container mx-auto text-center bg-blue-500", include those exact CSS rules.', 'ux-sites-optimiser'); ?></em></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render other tabs (continuing in next message due to length)
     */
    private function renderFontsTab() {
        ?>
        <h2><?php _e('Font Optimization', 'ux-sites-optimiser'); ?></h2>
        <p><?php _e('Reduces Cumulative Layout Shift (CLS) and improves font loading.', 'ux-sites-optimiser'); ?></p>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Preload Local Fonts', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-medium"><?php _e('Medium Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Automatically detects and preloads local font files.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_fonts_preload_local" value="1" <?php checked(get_option('sso_fonts_preload_local', true)); ?> />
                <?php _e('Preload local fonts', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Add Font-Display: Swap', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-high"><?php _e('High Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Prevents invisible text during font swaps. Improves user experience.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_fonts_add_display_swap" value="1" <?php checked(get_option('sso_fonts_add_display_swap', true)); ?> />
                <?php _e('Add font-display: swap', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Disable Google Fonts', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-medium"><?php _e('Medium Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Removes Google Fonts to eliminate external requests. May affect design.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_fonts_disable_google" value="1" <?php checked(get_option('sso_fonts_disable_google', false)); ?> />
                <?php _e('Disable Google Fonts', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        <?php
    }
    
    private function renderImagesTab() {
        ?>
        <h2><?php _e('Image Optimization', 'ux-sites-optimiser'); ?></h2>
        <p><?php _e('Safe image optimizations that improve loading and layout stability.', 'ux-sites-optimiser'); ?></p>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Add Missing Width and Height', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-high"><?php _e('High Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Prevents layout shifts by adding dimensions to images missing width/height attributes.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_images_add_dimensions" value="1" <?php checked(get_option('sso_images_add_dimensions', true)); ?> />
                <?php _e('Add missing image dimensions', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Lazy Load Images', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-high"><?php _e('High Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Adds native lazy loading to images below the fold.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_images_lazy_load" value="1" <?php checked(get_option('sso_images_lazy_load', true)); ?> />
                <?php _e('Enable image lazy loading', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4><?php _e('Exclude Above-the-Fold Images', 'ux-sites-optimiser'); ?></h4>
            <p class="sso-description"><?php _e('CSS selectors for images that should not be lazy loaded (logo, hero images, etc.).', 'ux-sites-optimiser'); ?></p>
            <textarea name="sso_images_exclude_above_fold" rows="3" cols="50" placeholder=".logo img, .hero-image, #header img"><?php echo esc_textarea(get_option('sso_images_exclude_above_fold', '')); ?></textarea>
        </div>
        <?php
    }
    
    private function renderCoreTab() {
        ?>
        <h2><?php _e('WordPress Core Cleanup', 'ux-sites-optimiser'); ?></h2>
        <p><?php _e('Zero-risk optimizations that should be enabled by default.', 'ux-sites-optimiser'); ?></p>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Remove WP Embed Script', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-safe"><?php _e('Safe', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Removes wp-embed.js if not needed. Safe unless you use WordPress embeds.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_core_remove_wp_embed" value="1" <?php checked(get_option('sso_core_remove_wp_embed', true)); ?> />
                <?php _e('Remove wp-embed.js', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Remove Dashicons for Logged-Out Users', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-safe"><?php _e('Safe', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Removes dashicons CSS for visitors who can\'t see the admin bar.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_core_remove_dashicons_logged_out" value="1" <?php checked(get_option('sso_core_remove_dashicons_logged_out', true)); ?> />
                <?php _e('Remove dashicons for logged-out users', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Disable XML-RPC', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-safe"><?php _e('Safe', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Disables XML-RPC functionality. Safe unless you use mobile apps or remote publishing.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_core_disable_xmlrpc" value="1" <?php checked(get_option('sso_core_disable_xmlrpc', true)); ?> />
                <?php _e('Disable XML-RPC', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Remove REST API Links', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-safe"><?php _e('Safe', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Removes REST API discovery links from HTML head. API still works.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_core_remove_rest_links" value="1" <?php checked(get_option('sso_core_remove_rest_links', true)); ?> />
                <?php _e('Remove REST API links', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Remove Query Strings', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-safe"><?php _e('Safe', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Removes version query strings from CSS and JS files for better caching.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_core_remove_query_strings" value="1" <?php checked(get_option('sso_core_remove_query_strings', true)); ?> />
                <?php _e('Remove version query strings', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        <?php
    }
    
    private function renderThirdPartyTab() {
        ?>
        <h2><?php _e('Third-Party Scripts Control', 'ux-sites-optimiser'); ?></h2>
        <p><?php _e('Huge performance wins by controlling external scripts and tracking.', 'ux-sites-optimiser'); ?></p>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Delay Analytics Scripts', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-high"><?php _e('High Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Delays Google Analytics, GTM, and other tracking until user interaction.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_third_party_delay_analytics" value="1" <?php checked(get_option('sso_third_party_delay_analytics', false)); ?> />
                <?php _e('Delay analytics scripts', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Load Tracking Only After Consent', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-medium"><?php _e('Medium Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Waits for cookie consent before loading tracking scripts. Improves privacy compliance.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_third_party_delay_tracking" value="1" <?php checked(get_option('sso_third_party_delay_tracking', false)); ?> />
                <?php _e('Wait for consent before tracking', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        <?php
    }
    
    private function renderPreloadingTab() {
        ?>
        <h2><?php _e('Preloading & Resource Hints', 'ux-sites-optimiser'); ?></h2>
        <p><?php _e('Low effort, high reward optimizations for critical resources.', 'ux-sites-optimiser'); ?></p>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Preload LCP Image', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-high"><?php _e('High Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Automatically detects and preloads the Largest Contentful Paint image.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_preload_lcp_image" value="1" <?php checked(get_option('sso_preload_lcp_image', true)); ?> />
                <?php _e('Preload LCP image', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('Preload Fonts', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-medium"><?php _e('Medium Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Preloads important font files to prevent font swap delays.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_preload_fonts" value="1" <?php checked(get_option('sso_preload_fonts', true)); ?> />
                <?php _e('Preload important fonts', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4>
                <?php _e('DNS Prefetch Third-Party Domains', 'ux-sites-optimiser'); ?>
                <span class="sso-impact impact-low"><?php _e('Low Impact', 'ux-sites-optimiser'); ?></span>
            </h4>
            <p class="sso-description"><?php _e('Adds DNS prefetch hints for external domains to reduce connection time.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_dns_prefetch_third_party" value="1" <?php checked(get_option('sso_dns_prefetch_third_party', true)); ?> />
                <?php _e('Enable DNS prefetch', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        <?php
    }
    
    private function renderGlobalTab() {
        ?>
        <h2><?php _e('Global Settings', 'ux-sites-optimiser'); ?></h2>
        <p><?php _e('Overall plugin behavior and safety settings.', 'ux-sites-optimiser'); ?></p>
        
        <div class="sso-setting">
            <h4><?php _e('Affect Logged-In Users', 'ux-sites-optimiser'); ?></h4>
            <p class="sso-description"><?php _e('By default, optimizations only affect logged-out visitors for safety. Enable this to optimize for logged-in users too.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_affect_logged_in_users" value="1" <?php checked(get_option('sso_affect_logged_in_users', false)); ?> />
                <?php _e('Apply optimizations to logged-in users', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4><?php _e('Plugin Status', 'ux-sites-optimiser'); ?></h4>
            <p><?php _e('Current plugin status and safety information.', 'ux-sites-optimiser'); ?></p>
            <ul>
                <li><strong><?php _e('Admin Area:', 'ux-sites-optimiser'); ?></strong> <?php _e('Never affected (protected)', 'ux-sites-optimiser'); ?></li>
                <li><strong><?php _e('REST API:', 'ux-sites-optimiser'); ?></strong> <?php _e('Never affected (protected)', 'ux-sites-optimiser'); ?></li>
                <li><strong><?php _e('AJAX Requests:', 'ux-sites-optimiser'); ?></strong> <?php _e('Never affected (protected)', 'ux-sites-optimiser'); ?></li>
                <li><strong><?php _e('WooCommerce Checkout:', 'ux-sites-optimiser'); ?></strong> <?php _e('Never affected (protected)', 'ux-sites-optimiser'); ?></li>
                <li><strong><?php _e('Logged-in Users:', 'ux-sites-optimiser'); ?></strong> 
                    <?php echo get_option('sso_affect_logged_in_users') ? __('Affected', 'ux-sites-optimiser') : __('Protected', 'ux-sites-optimiser'); ?>
                </li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Render Updates & Licensing tab
     */
    private function renderUpdatesTab() {
        ?>
        <h2><?php _e('Updates & Licensing', 'ux-sites-optimiser'); ?></h2>
        <p><?php _e('Configure automatic updates for your UX Sites Optimiser plugin.', 'ux-sites-optimiser'); ?></p>
        
        <div class="sso-setting">
            <h4><?php _e('Update Method', 'ux-sites-optimiser'); ?></h4>
            <p class="sso-description"><?php _e('Choose how you want to receive plugin updates.', 'ux-sites-optimiser'); ?></p>
            
            <fieldset>
                <legend class="screen-reader-text"><?php _e('Update Method', 'ux-sites-optimiser'); ?></legend>
                
                <label>
                    <input type="radio" name="update_method" value="github" <?php checked(get_option('sso_update_method', 'github'), 'github'); ?> />
                    <?php _e('GitHub Releases (Free)', 'ux-sites-optimiser'); ?>
                </label><br>
                
                <label>
                    <input type="radio" name="update_method" value="custom" <?php checked(get_option('sso_update_method', 'github'), 'custom'); ?> />
                    <?php _e('Custom Update Server', 'ux-sites-optimiser'); ?>
                </label>
            </fieldset>
        </div>
        
        <div class="sso-setting" id="github-settings">
            <h4><?php _e('GitHub Repository', 'ux-sites-optimiser'); ?></h4>
            <p class="sso-description"><?php _e('Enter your GitHub repository (e.g., "username/ux-sites-optimiser") to check for releases.', 'ux-sites-optimiser'); ?></p>
            <input type="text" name="sso_github_repo" value="<?php echo esc_attr(get_option('sso_github_repo', '')); ?>" 
                   placeholder="username/ux-sites-optimiser" class="regular-text" />
        </div>
        
        <div class="sso-setting" id="custom-server-settings" style="display: none;">
            <h4><?php _e('Custom Update Server', 'ux-sites-optimiser'); ?></h4>
            <p class="sso-description"><?php _e('URL to your custom update server endpoint.', 'ux-sites-optimiser'); ?></p>
            <input type="url" name="sso_update_server" value="<?php echo esc_attr(get_option('sso_update_server', '')); ?>" 
                   placeholder="https://yoursite.com/wp-content/plugins/update-server/" class="regular-text" />
        </div>
        
        <div class="sso-setting">
            <h4><?php _e('License Key', 'ux-sites-optimiser'); ?></h4>
            <p class="sso-description"><?php _e('Enter your license key if required for updates (optional for GitHub releases).', 'ux-sites-optimiser'); ?></p>
            <input type="text" name="sso_license_key" value="<?php echo esc_attr(get_option('sso_license_key', '')); ?>" 
                   placeholder="Enter license key..." class="regular-text" />
        </div>
        
        <div class="sso-setting">
            <h4><?php _e('Automatic Updates', 'ux-sites-optimiser'); ?></h4>
            <p class="sso-description"><?php _e('Enable automatic background updates for this plugin.', 'ux-sites-optimiser'); ?></p>
            <label>
                <input type="checkbox" name="sso_auto_updates" value="1" <?php checked(get_option('sso_auto_updates', false)); ?> />
                <?php _e('Enable automatic updates', 'ux-sites-optimiser'); ?>
            </label>
        </div>
        
        <div class="sso-setting">
            <h4><?php _e('Update Status', 'ux-sites-optimiser'); ?></h4>
            <?php
            $current_version = SSO_PLUGIN_VERSION;
            $last_checked = get_option('sso_last_update_check', 0);
            $next_check = $last_checked ? date('Y-m-d H:i:s', $last_checked + (12 * HOUR_IN_SECONDS)) : 'Never';
            ?>
            <ul>
                <li><strong><?php _e('Current Version:', 'ux-sites-optimiser'); ?></strong> <?php echo $current_version; ?></li>
                <li><strong><?php _e('Last Checked:', 'ux-sites-optimiser'); ?></strong> 
                    <?php echo $last_checked ? date('Y-m-d H:i:s', $last_checked) : __('Never', 'ux-sites-optimiser'); ?>
                </li>
                <li><strong><?php _e('Next Check:', 'ux-sites-optimiser'); ?></strong> <?php echo $next_check; ?></li>
            </ul>
            
            <p>
                <button type="button" id="check-for-updates" class="button button-secondary">
                    <?php _e('Check for Updates Now', 'ux-sites-optimiser'); ?>
                </button>
                <span id="update-check-result" style="margin-left: 10px;"></span>
            </p>
        </div>
        
        <div class="sso-setting">
            <h4><?php _e('Setup Instructions', 'ux-sites-optimiser'); ?></h4>
            <div class="sso-help-text">
                <p><strong><?php _e('Method 1: GitHub Releases (Recommended)', 'ux-sites-optimiser'); ?></strong></p>
                <ol>
                    <li><?php _e('Create a GitHub repository for your plugin', 'ux-sites-optimiser'); ?></li>
                    <li><?php _e('Upload your plugin files to the repository', 'ux-sites-optimiser'); ?></li>
                    <li><?php _e('Create releases using GitHub\'s release system', 'ux-sites-optimiser'); ?></li>
                    <li><?php _e('Enter your repository name above (e.g., "yourname/ux-sites-optimiser")', 'ux-sites-optimiser'); ?></li>
                </ol>
                
                <p><strong><?php _e('Method 2: Custom Update Server', 'ux-sites-optimiser'); ?></strong></p>
                <ol>
                    <li><?php _e('Set up a custom endpoint that returns update information', 'ux-sites-optimiser'); ?></li>
                    <li><?php _e('The endpoint should accept POST requests with plugin info', 'ux-sites-optimiser'); ?></li>
                    <li><?php _e('Return JSON with new_version, download_url, and other details', 'ux-sites-optimiser'); ?></li>
                    <li><?php _e('Enter your update server URL above', 'ux-sites-optimiser'); ?></li>
                </ol>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Show/hide update method settings
            $('input[name="update_method"]').change(function() {
                if ($(this).val() === 'github') {
                    $('#github-settings').show();
                    $('#custom-server-settings').hide();
                } else {
                    $('#github-settings').hide();
                    $('#custom-server-settings').show();
                }
            });
            
            // Manual update check
            $('#check-for-updates').click(function() {
                var button = $(this);
                var result = $('#update-check-result');
                
                button.prop('disabled', true).text('<?php _e('Checking...', 'ux-sites-optimiser'); ?>');
                result.html('');
                
                $.post(ajaxurl, {
                    action: 'sso_check_updates',
                    nonce: '<?php echo wp_create_nonce('sso_check_updates'); ?>'
                }, function(response) {
                    button.prop('disabled', false).text('<?php _e('Check for Updates Now', 'ux-sites-optimiser'); ?>');
                    
                    if (response.success) {
                        if (response.data.update_available) {
                            result.html('<span style="color: green;">✓ <?php _e('Update available:', 'ux-sites-optimiser'); ?> ' + response.data.new_version + '</span>');
                        } else {
                            result.html('<span style="color: green;">✓ <?php _e('Plugin is up to date', 'ux-sites-optimiser'); ?></span>');
                        }
                    } else {
                        result.html('<span style="color: red;">✗ <?php _e('Error checking for updates', 'ux-sites-optimiser'); ?></span>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save settings
     */
    private function saveSettings() {
        // Handle preset application
        if (isset($_POST['apply_preset'])) {
            // Debug logging
            error_log('SSO: Applying preset: ' . $_POST['preset_type']);
            
            $preset_applied = $this->applyPreset($_POST['preset_type']);
            if ($preset_applied) {
                error_log('SSO: Preset applied successfully, redirecting');
                // Redirect to show success and refresh settings display
                wp_redirect(add_query_arg([
                    'page' => 'ux-sites-optimiser',
                    'preset_applied' => $_POST['preset_type']
                ], admin_url('options-general.php')));
                exit;
            } else {
                error_log('SSO: Preset application failed');
                echo '<div class="notice notice-error"><p>' . __('Invalid preset selected.', 'ux-sites-optimiser') . '</p></div>';
                return;
            }
        }
        
        // Only update settings that are actually posted to preserve other tab settings
        $posted_settings = [
            // JavaScript settings
            'sso_js_move_jquery_footer',
            'sso_js_defer_non_critical', 
            'sso_js_delay_until_interaction',
            'sso_js_excluded_scripts',
            
            // CSS settings
            'sso_css_inline_critical',
            'sso_css_defer_non_critical',
            'sso_css_critical_css',
            
            // Font settings
            'sso_fonts_preload_local',
            'sso_fonts_add_display_swap',
            'sso_fonts_disable_google',
            
            // Image settings
            'sso_images_add_dimensions',
            'sso_images_lazy_load',
            'sso_images_exclude_above_fold',
            
            // Core settings
            'sso_core_remove_wp_embed',
            'sso_core_remove_dashicons_logged_out',
            'sso_core_disable_xmlrpc',
            'sso_core_remove_rest_links',
            'sso_core_remove_query_strings',
            
            // Third-party settings
            'sso_third_party_delay_analytics',
            'sso_third_party_delay_tracking',
            
            // Preloading settings
            'sso_preload_lcp_image',
            'sso_preload_fonts',
            'sso_dns_prefetch_third_party',
            
            // Global settings
            'sso_affect_logged_in_users'
        ];
        
        // Determine which tab was submitted
        $active_tab = $_POST['active_tab'] ?? 'javascript';
        
        // Define settings by tab
        $tab_settings = [
            'javascript' => ['sso_js_move_jquery_footer', 'sso_js_defer_non_critical', 'sso_js_delay_until_interaction', 'sso_js_excluded_scripts'],
            'css' => ['sso_css_inline_critical', 'sso_css_defer_non_critical', 'sso_css_critical_css'],
            'fonts' => ['sso_fonts_preload_local', 'sso_fonts_add_display_swap', 'sso_fonts_disable_google'],
            'images' => ['sso_images_add_dimensions', 'sso_images_lazy_load', 'sso_images_exclude_above_fold'],
            'core' => ['sso_core_remove_wp_embed', 'sso_core_remove_dashicons_logged_out', 'sso_core_disable_xmlrpc', 'sso_core_remove_rest_links', 'sso_core_remove_query_strings'],
            'third-party' => ['sso_third_party_delay_analytics', 'sso_third_party_delay_tracking'],
            'preloading' => ['sso_preload_lcp_image', 'sso_preload_fonts', 'sso_dns_prefetch_third_party'],
            'global' => ['sso_affect_logged_in_users'],
            'updates' => ['sso_github_repo', 'sso_update_server', 'sso_license_key', 'sso_auto_updates']
        ];
        
        // Only update settings for the current tab
        $current_tab_settings = $tab_settings[$active_tab] ?? [];
        
        foreach ($current_tab_settings as $setting) {
            if (strpos($setting, '_excluded_') !== false || strpos($setting, '_critical_css') !== false || 
                strpos($setting, '_github_repo') !== false || strpos($setting, '_update_server') !== false || 
                strpos($setting, '_license_key') !== false) {
                // Handle text/textarea fields
                update_option($setting, sanitize_textarea_field($_POST[$setting] ?? ''));
            } else {
                // Handle checkbox fields
                update_option($setting, isset($_POST[$setting]));
            }
        }
        
        // Handle special update method setting
        if ($active_tab === 'updates' && isset($_POST['update_method'])) {
            update_option('sso_update_method', sanitize_text_field($_POST['update_method']));
        }
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'ux-sites-optimiser') . '</p></div>';
    }
    
    /**
     * Apply preset configuration
     */
    private function applyPreset($preset) {
        error_log('SSO: applyPreset called with: ' . $preset);
        
        $presets = $this->getPresets();
        
        if (!isset($presets[$preset])) {
            error_log('SSO: Preset not found: ' . $preset);
            return false;
        }
        
        $config = $presets[$preset];
        error_log('SSO: Applying ' . count($config) . ' settings');
        
        // Apply all settings from preset
        foreach ($config as $setting => $value) {
            $old_value = get_option($setting);
            update_option($setting, $value);
            error_log('SSO: Updated ' . $setting . ' from ' . var_export($old_value, true) . ' to ' . var_export($value, true));
        }
        
        // Store which preset was applied
        update_option('sso_current_preset', $preset);
        error_log('SSO: Stored current preset as: ' . $preset);
        
        return true;
    }
    
    /**
     * Get preset configurations
     */
    private function getPresets() {
        return [
            'safe' => [
                // JavaScript - Safe options only
                'sso_js_move_jquery_footer' => true,
                'sso_js_defer_non_critical' => false,
                'sso_js_delay_until_interaction' => false,
                'sso_js_excluded_scripts' => '',
                
                // CSS - Manual critical CSS only
                'sso_css_inline_critical' => false,
                'sso_css_defer_non_critical' => false,
                'sso_css_critical_css' => '',
                
                // Fonts - Safe improvements
                'sso_fonts_preload_local' => true,
                'sso_fonts_add_display_swap' => true,
                'sso_fonts_disable_google' => false,
                
                // Images - Safe lazy loading
                'sso_images_add_dimensions' => true,
                'sso_images_lazy_load' => true,
                'sso_images_exclude_above_fold' => '',
                
                // Core - All safe cleanups
                'sso_core_remove_wp_embed' => true,
                'sso_core_remove_dashicons_logged_out' => true,
                'sso_core_disable_xmlrpc' => true,
                'sso_core_remove_rest_links' => true,
                'sso_core_remove_query_strings' => true,
                
                // Third-party - Conservative
                'sso_third_party_delay_analytics' => false,
                'sso_third_party_delay_tracking' => false,
                
                // Preloading - Safe options
                'sso_preload_lcp_image' => true,
                'sso_preload_fonts' => true,
                'sso_dns_prefetch_third_party' => true,
                
                // Global - Logged out only
                'sso_affect_logged_in_users' => false
            ],
            'medium' => [
                // JavaScript - Add defer
                'sso_js_move_jquery_footer' => true,
                'sso_js_defer_non_critical' => true,
                'sso_js_delay_until_interaction' => false,
                'sso_js_excluded_scripts' => '',
                
                // CSS - Add critical CSS if configured
                'sso_css_inline_critical' => false, // User must configure manually
                'sso_css_defer_non_critical' => true,
                'sso_css_critical_css' => '',
                
                // Fonts - All optimizations
                'sso_fonts_preload_local' => true,
                'sso_fonts_add_display_swap' => true,
                'sso_fonts_disable_google' => false,
                
                // Images - Full optimization
                'sso_images_add_dimensions' => true,
                'sso_images_lazy_load' => true,
                'sso_images_exclude_above_fold' => '',
                
                // Core - All cleanups
                'sso_core_remove_wp_embed' => true,
                'sso_core_remove_dashicons_logged_out' => true,
                'sso_core_disable_xmlrpc' => true,
                'sso_core_remove_rest_links' => true,
                'sso_core_remove_query_strings' => true,
                
                // Third-party - Some delays
                'sso_third_party_delay_analytics' => true,
                'sso_third_party_delay_tracking' => false,
                
                // Preloading - All options
                'sso_preload_lcp_image' => true,
                'sso_preload_fonts' => true,
                'sso_dns_prefetch_third_party' => true,
                
                // Global - Logged out only
                'sso_affect_logged_in_users' => false
            ],
            'risky' => [
                // JavaScript - All optimizations
                'sso_js_move_jquery_footer' => true,
                'sso_js_defer_non_critical' => true,
                'sso_js_delay_until_interaction' => true,
                'sso_js_excluded_scripts' => '',
                
                // CSS - Aggressive optimization
                'sso_css_inline_critical' => false, // Still requires manual setup
                'sso_css_defer_non_critical' => true,
                'sso_css_critical_css' => '',
                
                // Fonts - Aggressive including Google Fonts disable
                'sso_fonts_preload_local' => true,
                'sso_fonts_add_display_swap' => true,
                'sso_fonts_disable_google' => true,
                
                // Images - All optimizations
                'sso_images_add_dimensions' => true,
                'sso_images_lazy_load' => true,
                'sso_images_exclude_above_fold' => '',
                
                // Core - All cleanups
                'sso_core_remove_wp_embed' => true,
                'sso_core_remove_dashicons_logged_out' => true,
                'sso_core_disable_xmlrpc' => true,
                'sso_core_remove_rest_links' => true,
                'sso_core_remove_query_strings' => true,
                
                // Third-party - Delay everything
                'sso_third_party_delay_analytics' => true,
                'sso_third_party_delay_tracking' => true,
                
                // Preloading - All options
                'sso_preload_lcp_image' => true,
                'sso_preload_fonts' => true,
                'sso_dns_prefetch_third_party' => true,
                
                // Global - Could affect logged in users
                'sso_affect_logged_in_users' => false // Still conservative on this
            ]
        ];
    }
    
    /**
     * Detect current preset based on settings
     */
    private function detectCurrentPreset() {
        $presets = $this->getPresets();
        $stored_preset = get_option('sso_current_preset', '');
        
        // If we have a stored preset, verify it matches current settings
        if ($stored_preset && isset($presets[$stored_preset])) {
            $preset_config = $presets[$stored_preset];
            $matches = true;
            
            // Check if current settings match the stored preset
            foreach ($preset_config as $setting => $expected_value) {
                $current_value = get_option($setting);
                if ($current_value != $expected_value) {
                    $matches = false;
                    break;
                }
            }
            
            if ($matches) {
                return $stored_preset;
            } else {
                // Settings have been modified, clear the preset
                delete_option('sso_current_preset');
                return '';
            }
        }
        
        return '';
    }
    
    /**
     * AJAX handler for manual update checks
     */
    public function ajax_check_updates() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sso_check_updates')) {
            wp_die('Security check failed');
        }
        
        // Check if user has capability
        if (!current_user_can('update_plugins')) {
            wp_die('Insufficient permissions');
        }
        
        // Get the main plugin instance and force update check
        $main_instance = SafeSpeedOptimizer::getInstance();
        if (method_exists($main_instance, 'getUpdater')) {
            $updater = $main_instance->getUpdater();
            if ($updater) {
                $update_info = $updater->force_update_check();
                
                if ($update_info && version_compare(SSO_PLUGIN_VERSION, $update_info->new_version, '<')) {
                    wp_send_json_success([
                        'update_available' => true,
                        'new_version' => $update_info->new_version,
                        'current_version' => SSO_PLUGIN_VERSION
                    ]);
                } else {
                    wp_send_json_success([
                        'update_available' => false,
                        'current_version' => SSO_PLUGIN_VERSION
                    ]);
                }
            }
        }
        
        wp_send_json_error('Could not check for updates');
    }
}