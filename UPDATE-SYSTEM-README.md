# UX Sites Optimiser - Update System

Your UX Sites Optimiser plugin now includes a complete automatic update system that allows you to push updates to all sites where the plugin is installed, without manual file uploads.

## 🚀 Quick Setup Guide

### Method 1: GitHub Releases (Recommended - Free)

1. **Create a GitHub Repository**
   - Go to GitHub and create a new repository (e.g., `yourname/ux-sites-optimiser`)
   - Make it public or private (both work)

2. **Upload Your Plugin**
   - Upload all plugin files to the repository
   - Make sure the main plugin file is in the root or create proper folder structure

3. **Configure Plugin Settings**
   - Go to **WordPress Admin → Settings → UX Sites Optimiser → Updates & Licensing**
   - Select "GitHub Releases" as update method
   - Enter your repository name: `yourname/ux-sites-optimiser`
   - Save settings

4. **Create Releases**
   - In GitHub, go to your repository
   - Click "Releases" → "Create a new release"
   - Tag version (e.g., `v1.0.2`)
   - Upload a ZIP file of your plugin or let GitHub auto-generate
   - Publish release

5. **Test Updates**
   - In WordPress admin, click "Check for Updates Now"
   - You should see the new version detected
   - Updates will appear in WordPress Updates page

### Method 2: Custom Update Server

1. **Upload Update Server**
   - Use the included `update-server-example.php` as a template
   - Upload to your main website (e.g., `yoursite.com/plugin-updates/`)
   - Edit the configuration variables:
     ```php
     $current_version = '1.0.2'; // Update this for each release
     $download_url = 'https://yoursite.com/releases/ux-sites-optimiser-v1.0.2.zip';
     ```

2. **Configure Plugin Settings**
   - Select "Custom Update Server" as update method
   - Enter your server URL: `https://yoursite.com/plugin-updates/`
   - Save settings

3. **Upload New Versions**
   - Upload ZIP files to your releases folder
   - Update `$current_version` and `$download_url` in update server
   - Plugin will automatically detect updates

## 📋 Update Workflow

### For Each New Release:

**GitHub Method:**
1. Update version in `safe-speed-optimizer.php` (header and constant)
2. Commit and push changes to GitHub
3. Create new GitHub release with version tag
4. All sites will automatically detect the update

**Custom Server Method:**
1. Update version in `safe-speed-optimizer.php`
2. Create ZIP file of entire plugin folder
3. Upload ZIP to your releases folder
4. Update `$current_version` and `$download_url` in update server
5. All sites will detect the update within 12 hours (or immediately with manual check)

## 🔧 Plugin Settings Explained

### Updates & Licensing Tab

- **Update Method**: Choose between GitHub releases or custom server
- **GitHub Repository**: Format `username/repository-name`
- **Custom Update Server**: Full URL to your update endpoint
- **License Key**: Optional - for premium licensing systems
- **Automatic Updates**: Enable WordPress automatic background updates
- **Manual Check**: Force immediate update check for testing

## 🛡️ Security Features

- **Nonce verification**: All update checks use WordPress nonces
- **Capability checks**: Only users with `update_plugins` capability can check for updates
- **Rate limiting**: Update checks are cached for 12 hours to prevent abuse
- **Safe fallbacks**: If update fails, original plugin remains intact

## 📦 File Structure

```
ux-sites-optimiser/
├── safe-speed-optimizer.php          # Main plugin file (version defined here)
├── includes/
│   └── class-plugin-updater.php      # Update system core
├── admin/
│   └── class-admin-interface.php     # Includes Updates tab
└── update-server-example.php         # Template for custom update server
```

## 🧪 Testing Updates

1. **Test on Staging Site First**
   - Set up plugin on staging environment
   - Create test release with higher version number
   - Verify update process works correctly

2. **Manual Update Check**
   - Use "Check for Updates Now" button in plugin settings
   - Verify version detection in browser console
   - Check WordPress Updates page for plugin listing

3. **Automatic Update Testing**
   - Enable automatic updates in plugin settings
   - Wait for WordPress cron to run (or trigger manually)
   - Verify update installs without issues

## 🚨 Troubleshooting

### Updates Not Detecting
- Verify repository name is correct (case-sensitive)
- Check GitHub repository is accessible (public or with proper authentication)
- Ensure version number in plugin file is properly updated
- Clear update cache: go to Updates tab and click "Check for Updates Now"

### Update Server Issues
- Verify update server URL is accessible
- Check server returns valid JSON response
- Ensure ZIP file is downloadable
- Review server error logs for issues

### Permission Errors
- Verify user has `update_plugins` capability
- Check file permissions on WordPress uploads directory
- Ensure WordPress can write to plugin directory

## 🔄 Version Management Best Practices

1. **Semantic Versioning**: Use format `1.0.1` (major.minor.patch)
2. **Always Test**: Test updates on staging before production
3. **Backup First**: Recommend users backup before major updates
4. **Changelog**: Maintain detailed changelog for user transparency
5. **Gradual Rollout**: Consider rolling out to test sites first

## 💡 Advanced Features

### Licensing System
The update system supports licensing:
```php
// In update server
$requires_license = true;
$valid_licenses = ['license-key-1', 'license-key-2'];
```

### Update Notifications
Customize update notices in WordPress admin:
```php
// The updater automatically shows update notices
// Customize in class-plugin-updater.php update_notice() method
```

### Rollback Support
To add rollback capability:
1. Store previous version ZIP files
2. Add rollback option in admin interface  
3. Implement version downgrade logic

## 🎯 Next Steps

1. **Set up your preferred update method** (GitHub recommended for simplicity)
2. **Test the update process** with a version increment
3. **Document your release process** for consistent updates
4. **Consider automatic updates** for non-critical sites
5. **Monitor update success** across your sites

## 📞 Support

- Update system logs errors to WordPress error log
- Check browser console for JavaScript errors during manual updates
- Review server access logs for custom update server issues
- Test update URLs directly in browser to verify accessibility

---

Your plugin now has professional-grade update capabilities! 🎉