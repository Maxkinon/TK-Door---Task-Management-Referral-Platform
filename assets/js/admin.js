/**
 * Indoor Tasks Admin JavaScript
 * 
 * Handles admin-specific functionality including:
 * - Settings tabs navigation
 * - Color picker for level badges
 * - Dynamic form fields
 */

jQuery(document).ready(function($) {
    // Settings tabs
    $('.indoor-tasks-tab-button').on('click', function() {
        var tabId = $(this).data('tab');
        $('.indoor-tasks-tab-button').removeClass('active');
        $('.indoor-tasks-settings-section').removeClass('active');
        $(this).addClass('active');
        $('#' + tabId).addClass('active');
        
        // Save active tab to localStorage
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('indoor_tasks_active_tab', tabId);
        }
    });
    
    // Restore last active tab
    if (typeof(Storage) !== "undefined") {
        var lastTab = localStorage.getItem('indoor_tasks_active_tab');
        if (lastTab) {
            $('.indoor-tasks-tab-button[data-tab="' + lastTab + '"]').click();
        } else {
            // Default to first tab
            $('.indoor-tasks-tab-button:first').click();
        }
    } else {
        // If localStorage is not available, default to first tab
        $('.indoor-tasks-tab-button:first').click();
    }
    
    // If no tab is active (initial page load), activate the first tab
    if ($('.indoor-tasks-tab-button.active').length === 0) {
        $('.indoor-tasks-tab-button:first').click();
    }
    
    // Initialize color pickers if wp-color-picker is available
    if ($.fn.wpColorPicker) {
        $('.color-picker').wpColorPicker();
    }
    
    // Handle input fields in withdrawal methods
    var inputFieldCount = 0;
    
    function addInputField(data = {}) {
        var template = `
            <div class="input-field" data-index="${inputFieldCount}">
                <a class="remove-field" title="Remove field">×</a>
                <div class="method-grid">
                    <div class="input-group">
                        <label>Field Label:</label>
                        <input type="text" name="input_fields[${inputFieldCount}][label]" value="${data.label || ''}" 
                               class="widefat" placeholder="UPI ID, Wallet Address" required>
                    </div>
                    <div class="input-group">
                        <label>Field Type:</label>
                        <select name="input_fields[${inputFieldCount}][type]" class="widefat">
                            <option value="text" ${(data.type || 'text') === 'text' ? 'selected' : ''}>Text</option>
                            <option value="email" ${(data.type || '') === 'email' ? 'selected' : ''}>Email</option>
                            <option value="number" ${(data.type || '') === 'number' ? 'selected' : ''}>Number</option>
                            <option value="tel" ${(data.type || '') === 'tel' ? 'selected' : ''}>Phone</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Required:</label>
                        <input type="checkbox" name="input_fields[${inputFieldCount}][required]" value="1" 
                               ${data.required ? 'checked' : ''}>
                    </div>
                    <div class="input-group">
                        <label>Placeholder:</label>
                        <input type="text" name="input_fields[${inputFieldCount}][placeholder]" 
                               value="${data.placeholder || ''}" class="widefat">
                    </div>
                </div>
            </div>
        `;
        
        $('#input-fields-container').append(template);
        inputFieldCount++;
    }
    
    // Add input field button
    $('#add-input-field').on('click', function() {
        addInputField();
    });
    
    // Remove input field
    $(document).on('click', '.remove-field', function() {
        $(this).closest('.input-field').remove();
    });
    
    // Initialize existing input fields from custom_fields data
    if ($('#input-fields-container').length > 0) {
        var existingFields = $('#input-fields-container').data('fields');
        if (existingFields) {
            try {
                var fields = JSON.parse(existingFields);
                fields.forEach(function(field) {
                    addInputField(field);
                });
            } catch(e) {
                console.error('Error parsing existing fields:', e);
            }
        }
    }
    
    // Handle form submission
    $('.method-form').on('submit', function() {
        // Validate required fields
        var required = ['method', 'conversion_rate', 'min_points'];
        var valid = true;
        
        required.forEach(function(field) {
            if (!$('#' + field).val()) {
                valid = false;
                $('#' + field).addClass('error');
            }
        });
        
        if (!valid) {
            alert('Please fill in all required fields');
            return false;
        }
        
        // Make sure there's at least one input field
        if ($('.input-field').length === 0) {
            if (!confirm('No input fields added. Are you sure you want to continue?')) {
                return false;
            }
        }
    });
        var template = $('#withdrawal-method-template').html();
        var newMethod = template.replace(/\{index\}/g, methodCount);
        $('#withdrawal-methods-container').append(newMethod);
        
        // Initialize color picker for new elements if wp-color-picker is available
        if ($.fn.wpColorPicker) {
            $('#withdrawal-methods-container .color-picker').wpColorPicker();
        }
    });
    
    // Remove withdrawal method
    $(document).on('click', '.remove-method', function() {
        $(this).closest('.withdrawal-method').remove();
    });
    
    // Add new input field to withdrawal method
    $(document).on('click', '.add-input-field', function() {
        var methodIndex = $(this).data('method-index');
        var fieldCount = $(this).closest('.withdrawal-method').find('.input-field').length;
        var template = $('#input-field-template').html();
        var newField = template.replace(/\{method_index\}/g, methodIndex).replace(/\{field_index\}/g, fieldCount);
        $(this).closest('.withdrawal-method').find('.input-fields-container').append(newField);
    });
    
    // Remove input field from withdrawal method
    $(document).on('click', '.remove-field', function() {
        $(this).closest('.input-field').remove();
    });
    
    // Toggle sections based on settings
    function toggleDependentSections() {
        // Example: Toggle KYC fields based on KYC requirement
        var kycRequired = $('input[name="kyc_required"]').is(':checked');
        if (kycRequired) {
            $('textarea[name="kyc_fields"]').closest('tr').show();
        } else {
            $('textarea[name="kyc_fields"]').closest('tr').hide();
        }
        
        // Toggle level definitions based on level system enabled
        var levelSystemEnabled = $('input[name="enable_level_system"]').is(':checked');
        if (levelSystemEnabled) {
            $('.level-definitions-wrapper').closest('tr').show();
            $('select[name="level_type"]').closest('tr').show();
        } else {
            $('.level-definitions-wrapper').closest('tr').hide();
            $('select[name="level_type"]').closest('tr').hide();
        }
    }
    
    // Run on page load
    toggleDependentSections();
    
    // Run when checkboxes change
    $('input[type="checkbox"]').on('change', toggleDependentSections);
});
