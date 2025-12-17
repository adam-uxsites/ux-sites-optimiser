<?php
/**
 * Simple Update Server Example for UX Sites Optimiser
 * 
 * Place this file on your server and configure the update URL in the plugin settings
 */

// Prevent direct access
if (!defined('ABSPATH') && !isset($_POST['action'])) {
    die('Direct access not permitted');
}

// Configuration - Update these values
$plugin_slug = 'ux-sites-optimiser';
$current_version = '1.0.2'; // Update this when you release new versions
$download_url = 'https://yoursite.com/releases/ux-sites-optimiser-v1.0.2.zip';
$requires_license = false; // Set to true if you want to require license keys

// Valid license keys (if using licensing)
$valid_licenses = [
    'demo-license-key-123',
    'premium-license-456',
    // Add your license keys here
];

// Handle the update check request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    header('Content-Type: application/json');
    
    $action = sanitize_text_field($_POST['action']);
    $plugin_slug_request = sanitize_text_field($_POST['plugin_slug'] ?? '');
    $current_version_request = sanitize_text_field($_POST['current_version'] ?? '');
    $license_key = sanitize_text_field($_POST['license_key'] ?? '');
    $site_url = sanitize_url($_POST['site_url'] ?? '');
    
    // Log the request (optional)
    error_log("Update check from {$site_url} for {$plugin_slug_request} v{$current_version_request}");
    
    if ($action === 'get_version' && $plugin_slug_request === $plugin_slug) {
        
        // Check license if required
        if ($requires_license && !in_array($license_key, $valid_licenses)) {
            http_response_code(403);
            echo json_encode([
                'error' => 'Invalid or missing license key',
                'message' => 'A valid license key is required for updates.'
            ]);
            exit;
        }
        
        // Check if update is needed
        if (version_compare($current_version_request, $current_version, '<')) {
            
            $response = [
                'new_version' => $current_version,
                'name' => 'UX Sites Optimiser',
                'author' => 'Your Name',
                'author_profile' => 'https://yoursite.com',
                'download_url' => $download_url,
                'details_url' => 'https://yoursite.com/ux-sites-optimiser/',
                'description' => 'Safe, toggleable performance optimizations for WordPress websites with modular class-based structure.',
                'changelog' => get_changelog($current_version),
                'requires' => '5.0',
                'tested' => '6.4',
                'requires_php' => '7.4',
                'compatibility' => [],
                'banners' => [
                    'low' => 'https://yoursite.com/images/ux-sites-optimiser-banner-772x250.png',
                    'high' => 'https://yoursite.com/images/ux-sites-optimiser-banner-1544x500.png'
                ],
                'icons' => [
                    '1x' => 'https://yoursite.com/images/ux-sites-optimiser-icon-128x128.png',
                    '2x' => 'https://yoursite.com/images/ux-sites-optimiser-icon-256x256.png'
                ]
            ];
            
            echo json_encode($response);
            
        } else {
            // No update needed
            echo json_encode([
                'message' => 'Plugin is up to date',
                'current_version' => $current_version_request
            ]);
        }
        
    } else {
        // Invalid request
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid request',
            'message' => 'Action or plugin slug not recognized.'
        ]);
    }
    
} else {
    // Show simple status page for GET requests
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>UX Sites Optimiser Update Server</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .status { background: #e7f5e7; padding: 20px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <h1>UX Sites Optimiser Update Server</h1>
        <div class="status">
            <h2>Status: Online</h2>
            <p><strong>Current Version:</strong> <?php echo $current_version; ?></p>
            <p><strong>Plugin Slug:</strong> <?php echo $plugin_slug; ?></p>
            <p><strong>Requires License:</strong> <?php echo $requires_license ? 'Yes' : 'No'; ?></p>
            <p><strong>Last Updated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <h2>API Usage</h2>
        <p>POST to this URL with the following parameters:</p>
        <ul>
            <li><code>action</code>: "get_version"</li>
            <li><code>plugin_slug</code>: "<?php echo $plugin_slug; ?>"</li>
            <li><code>current_version</code>: Current plugin version</li>
            <li><code>site_url</code>: Website URL</li>
            <?php if ($requires_license): ?>
            <li><code>license_key</code>: Valid license key</li>
            <?php endif; ?>
        </ul>
    </body>
    </html>
    <?php
}

/**
 * Get changelog for a specific version
 */
function get_changelog($version) {
    $changelogs = [
        '1.0.2' => '
            <h4>Version 1.0.2</h4>
            <ul>
                <li>Bumped version to test update functionality</li>
                <li>Minor fixes and documentation updates</li>
                <li>Added CHANGELOG.md and updated update metadata</li>
            </ul>
        ',
        '1.0.1' => '
            <h4>Version 1.0.1</h4>
            <ul>
                <li>Added automatic update system</li>
                <li>Improved preset system reliability</li>
                <li>Fixed debug message display issues</li>
                <li>Enhanced GitHub integration</li>
                <li>Better error handling and logging</li>
            </ul>
        ',
        '1.0.0' => '
            <h4>Version 1.0.0</h4>
            <ul>
                <li>Initial release</li>
                <li>JavaScript optimization module</li>
                <li>CSS optimization with critical CSS support</li>
                <li>Font optimization and preloading</li>
                <li>Image lazy loading and dimensions</li>
                <li>WordPress core cleanup</li>
                <li>Third-party script management</li>
                <li>Resource preloading and hints</li>
                <li>Preset system (Safe/Medium/Risky)</li>
            </ul>
        '
    ];
    
    return $changelogs[$version] ?? 'Bug fixes and improvements.';
}

/**
 * Sanitize text field (WordPress function alternative)
 */
function sanitize_text_field($str) {
    return trim(strip_tags($str));
}

/**
 * Sanitize URL (WordPress function alternative)
 */
function sanitize_url($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}
?>