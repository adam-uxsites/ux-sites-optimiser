# UX Sites Optimiser

A WordPress plugin for safe, toggleable performance optimizations with a focus on frontend performance while maintaining admin functionality and user experience.

## Core Principles

- **Everything toggleable** - Every optimization can be enabled/disabled individually
- **Nothing breaks the admin** - Zero interference with WordPress admin area
- **Frontend only by default** - Optimizations only affect logged-out users unless explicitly enabled
- **Modular class-based structure** - Clean, maintainable code architecture

## Safety Rules (Hard-coded and non-negotiable)

- ❌ Never affects `/wp-admin`
- ❌ Never affects REST requests  
- ❌ Never affects logged-in users (unless enabled)
- ❌ Never breaks WooCommerce checkout pages
- ❌ Never affects AJAX requests
- ❌ Never affects cron jobs

## Modules

### MODULE 1: JavaScript Optimization (High Impact)
- ✅ Move jQuery to footer
- ✅ Defer all non-critical JS
- ✅ Delay JS until user interaction
- ✅ Exclusion list support
- Excludes jquery-core from delay (but allows defer)
- Never touches admin scripts
- Never defers scripts with `data-no-defer`

### MODULE 2: CSS Optimization (LCP Critical)
- ✅ Inline Critical CSS (manual input for safety)
- ✅ Defer non-critical CSS
- Page-specific critical CSS support
- Post-type specific CSS
- Template-specific CSS

### MODULE 3: Font Optimization (CLS Prevention)
- ✅ Preload local fonts
- ✅ Add font-display: swap
- ✅ Disable Google Fonts option
- Automatic font detection
- Preconnect to font domains

### MODULE 4: Image Optimization (Safe Wins)
- ✅ Add missing width and height attributes
- ✅ Native lazy loading
- ✅ Exclude above-the-fold images
- Smart dimension detection
- Layout shift prevention

### MODULE 5: WordPress Core Cleanup (Zero Risk)
All enabled by default as they're completely safe:
- ✅ Remove wp-embed.js
- ✅ Remove dashicons for logged-out users
- ✅ Disable XML-RPC
- ✅ Remove REST API links in head
- ✅ Remove query strings from static assets
- Additional cleanup (generators, emoji scripts, etc.)

### MODULE 6: Third-Party Scripts Control
- ✅ Delay analytics (GA, GTM, Facebook, Hotjar, etc.)
- ✅ Load tracking scripts only after consent
- ✅ Disable Cloudflare email decode
- Smart script detection
- Consent detection

### MODULE 7: Preloading & Resource Hints
- ✅ Preload LCP image (automatic detection)
- ✅ Preload fonts
- ✅ DNS-prefetch for third-party domains
- ✅ Preconnect external APIs
- Smart resource prioritization

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → UX Sites Optimiser to configure options

## Configuration

Navigate to **Settings → UX Sites Optimiser** in your WordPress admin. The interface is organized into tabs for each module:

- **JavaScript** - Script optimization settings
- **CSS** - Stylesheet optimization settings  
- **Fonts** - Font loading optimizations
- **Images** - Image performance settings
- **Core Cleanup** - WordPress core optimizations
- **Third-Party** - External script control
- **Preloading** - Resource hint settings
- **Global Settings** - Plugin-wide options

## Default Settings

The plugin ships with safe defaults:

**Enabled by default:**
- Move jQuery to footer
- Defer non-critical JavaScript  
- Defer non-critical CSS
- Preload local fonts
- Add font-display: swap
- Add missing image dimensions
- Image lazy loading
- All WordPress core cleanup options
- Preload LCP image
- Preload fonts
- DNS prefetch third-party domains

**Disabled by default (advanced features):**
- Delay JavaScript until interaction
- Inline critical CSS  
- Disable Google Fonts
- Delay analytics scripts
- Load tracking after consent

## Advanced Features

### Critical CSS Management
- Global critical CSS setting
- Page-specific critical CSS via post meta
- Post-type specific CSS
- Template-specific CSS

### Script Exclusions
Exclude specific scripts from optimization using:
- Script handles (comma-separated)
- `data-no-defer` attribute
- Built-in exclusion list for critical scripts

### Image Exclusions
Prevent lazy loading for above-the-fold images using CSS selectors:
```
.logo img, .hero-image, #header img
```

### Emergency Rollback
If errors exceed threshold, plugin automatically disables optimizations temporarily.

## Debugging

Enable WordPress debug logging to see plugin activity:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Compatibility

**Tested with:**
- WordPress 5.0+
- PHP 7.4+
- WooCommerce
- Popular page builders
- Common themes

**Known Exclusions:**
- Admin area (by design)
- WooCommerce checkout/cart/account pages
- Password-protected posts
- REST API requests

## Performance Impact

**Expected improvements:**
- Reduced render-blocking resources
- Faster Largest Contentful Paint (LCP)
- Lower Cumulative Layout Shift (CLS)
- Reduced third-party script impact
- Better caching through query string removal

## Support

This plugin follows WordPress coding standards and best practices. For issues:

1. Check the debug log for errors
2. Disable modules one by one to isolate issues  
3. Verify settings in Global Settings tab
4. Test with default theme/no other plugins

## Security

- All user inputs are sanitized
- Nonce verification for admin actions
- Capability checks for admin access
- No external requests without user consent
- Safe HTML output escaping

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- All 7 modules implemented
- Full admin interface
- Safety validation system
- Debug logging support