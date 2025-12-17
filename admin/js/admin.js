/**
 * UX Sites Optimiser Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Debug: Check if elements exist
    console.log('SSO: jQuery ready, preset form exists:', $('#preset-form').length > 0);
    console.log('SSO: Modal overlay exists:', $('#sso-modal-overlay').length > 0);
    
    // Preset configurations
    var presets = {
        safe: {
            name: 'Safe',
            description: 'Zero-risk optimizations that are safe for all sites',
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
            name: 'Medium',
            description: 'Balanced optimizations with some JavaScript/CSS deferring',
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
            name: 'Risky',
            description: 'Aggressive optimizations - test thoroughly before using on production',
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
    
    // Add CSS for modal and settings preview
    var modalCSS = `
    <style id="sso-modal-styles">
        .sso-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; display: flex; align-items: center; justify-content: center; }
        .sso-modal { background: #fff; border-radius: 8px; min-width: 500px; max-width: 90%; max-height: 90%; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .sso-modal-header { padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
        .sso-modal-header h3 { margin: 0; color: #23282d; }
        .sso-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; padding: 0; width: 30px; height: 30px; }
        .sso-modal-close:hover { color: #000; }
        .sso-modal-body { padding: 20px; }
        .sso-modal-footer { padding: 20px; border-top: 1px solid #ddd; text-align: right; }
        .sso-modal-footer .button { margin-left: 10px; }
        .settings-preview { background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 15px 0; }
        .settings-preview h4 { margin: 0 0 10px 0; color: #0073aa; }
        .settings-list { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 0; }
        .setting-item { display: flex; align-items: center; gap: 8px; padding: 4px 0; }
        .setting-enabled { color: #46b450; font-weight: bold; }
        .setting-disabled { color: #dc3232; }
        .setting-icon { width: 16px; text-align: center; }
    </style>
    `;
    if ($('#sso-modal-styles').length === 0) {
        $('head').append(modalCSS);
    }
    
    // Custom modal functions
    function showModal(title, message, settingsPreview, onConfirm) {
        console.log('SSO: showModal called with title:', title);
        
        // Check if modal elements exist
        if ($('#sso-modal-overlay').length === 0) {
            console.error('SSO: Modal overlay not found in DOM!');
            // Fallback to browser confirm
            if (confirm(message)) {
                if (onConfirm) onConfirm();
            }
            return;
        }
        
        $('#sso-modal-title').text(title);
        $('#sso-modal-message').text(message);
        $('#sso-modal-settings-preview').html(settingsPreview);
        $('#sso-modal-overlay').show();
        console.log('SSO: Modal shown');
        
        // Handle confirm
        $('#sso-modal-confirm').off('click').on('click', function() {
            console.log('SSO: Modal confirm clicked');
            hideModal();
            if (onConfirm) onConfirm();
        });
    }
    
    function hideModal() {
        console.log('SSO: hideModal called');
        $('#sso-modal-overlay').hide();
    }
    
    function generateSettingsPreview(presetType) {
        var preset = presets[presetType];
        if (!preset) return '';
        
        var html = '<div class="settings-preview">';
        html += '<h4>' + preset.name + ' Preset</h4>';
        html += '<p style="font-style: italic; margin-bottom: 15px;">' + preset.description + '</p>';
        html += '<div class="settings-list">';
        
        for (var setting in preset.settings) {
            var enabled = preset.settings[setting];
            var icon = enabled ? '✓' : '✗';
            var className = enabled ? 'setting-enabled' : 'setting-disabled';
            
            html += '<div class="setting-item">';
            html += '<span class="setting-icon ' + className + '">' + icon + '</span>';
            html += '<span class="' + className + '">' + setting + '</span>';
            html += '</div>';
        }
        
        html += '</div></div>';
        return html;
    }
    
    // Preview preset button
    $('#preview-preset').on('click', function() {
        var presetType = $('#preset-selector').val();
        var preview = generateSettingsPreview(presetType);
        $('#preset-preview #preset-settings-list').html(preview);
        $('#preset-preview').show();
    });
    
    // Close preview
    $('#close-preview').on('click', function() {
        $('#preset-preview').hide();
    });
    
    // Modal close buttons
    $('#sso-modal-close, #sso-modal-cancel').on('click', hideModal);
    
    // Close modal on overlay click
    $('#sso-modal-overlay').on('click', function(e) {
        if (e.target === this) {
            hideModal();
        }
    });
    
    // Handle preset form submission with custom modal
    var bypassConfirmation = false;
    
    $('#preset-form').on('submit', function(e) {
        console.log('SSO: Form submit triggered, bypass=' + bypassConfirmation);
        
        // If we're bypassing confirmation (after user confirmed), allow submission
        if (bypassConfirmation) {
            console.log('SSO: Bypassing confirmation, allowing form submission');
            bypassConfirmation = false;
            return true;
        }
        
        console.log('SSO: Preventing default and showing modal');
        e.preventDefault();
        
        var presetType = $('#preset-selector').val();
        var preset = presets[presetType];
        console.log('SSO: Selected preset:', presetType, preset);
        
        if (!preset) {
            console.error('SSO: Preset not found!');
            return;
        }
        
        var message = 'Are you sure you want to apply the "' + preset.name + '" preset? This will overwrite your current settings.';
        var settingsPreview = generateSettingsPreview(presetType);
        
        console.log('SSO: Showing modal with message:', message);
        showModal('Confirm Preset Application', message, settingsPreview, function() {
            console.log('SSO: User confirmed, submitting form');
            
            // Show loading state
            var submitButton = $('#preset-form input[name="apply_preset"]');
            submitButton.prop('disabled', true).val('Applying...');
            
            // Add timeout to detect if redirect fails
            setTimeout(function() {
                console.error('SSO: No redirect after 5 seconds - something is wrong!');
                submitButton.prop('disabled', false).val('Apply Preset');
            }, 5000);
            
            // Set bypass flag and submit the form
            bypassConfirmation = true;
            console.log('SSO: Set bypass=true, calling submit()');
            
            // Ensure the apply_preset field is properly set
            var form = document.getElementById('preset-form');
            var applyField = form.querySelector('input[name="apply_preset"]');
            if (applyField) {
                applyField.value = 'Apply Preset';
                console.log('SSO: apply_preset field found and set');
            } else {
                console.log('SSO: ERROR - apply_preset field not found!');
            }
            
            // Debug: Check form data before submission
            var formData = new FormData(document.getElementById('preset-form'));
            console.log('SSO: Form data before submit:');
            for (var pair of formData.entries()) {
                console.log('SSO: ' + pair[0] + ': ' + pair[1]);
            }
            
            // Try form submission first
            $('#preset-form').submit();
            
            // Fallback with AJAX if form doesn't redirect after 2 seconds
            setTimeout(function() {
                console.log('SSO: Form submission timeout, trying AJAX fallback');
                var formData = new FormData(document.getElementById('preset-form'));
                formData.append('apply_preset', 'Apply Preset');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(function(response) {
                    console.log('SSO: AJAX response received');
                    if (response.ok) {
                        window.location.reload();
                    }
                }).catch(function(error) {
                    console.log('SSO: AJAX error:', error);
                });
            }, 2000);
        });
    });
    
    // Add visual feedback for regular form submissions
    $('form:not(#preset-form)').on('submit', function() {
        var submitButton = $(this).find('input[type="submit"]');
        submitButton.prop('disabled', true);
        submitButton.val('Saving...');
        
        // Re-enable after 3 seconds as fallback
        setTimeout(function() {
            submitButton.prop('disabled', false);
            submitButton.val(submitButton.data('original-value') || 'Save Settings');
        }, 3000);
    });
    
    // Store original button values
    $('input[type="submit"]').each(function() {
        $(this).data('original-value', $(this).val());
    });
    
    // Highlight active tab content
    $('.nav-tab-active').closest('.nav-tab-wrapper').next('.tab-content').addClass('active-tab');
    
});