/**
 * TKM Door KYC Verification JavaScript
 * Multi-step form functionality for KYC verification page
 * Version: 2.0.0
 */

(function() {
    'use strict';
    
    let currentStep = 1;
    const totalSteps = 3;
    let uploadedFiles = {};
    
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initMultiStepForm();
        initFileUploads();
        initFormValidation();
        initStepNavigation();
        initReviewSection();
    });
    
    /**
     * Initialize multi-step form functionality
     */
    function initMultiStepForm() {
        const form = document.getElementById('kyc-multi-step-form');
        if (!form) return;
        
        // Show first step
        showStep(1);
        
        // Handle form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (currentStep !== 3) {
                return false;
            }
            
            if (!validateAllSteps()) {
                showNotification('Please complete all required fields and upload all documents.', 'error');
                return false;
            }
            
            // Show loading overlay
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
            }
            
            // Submit form
            form.submit();
        });
    }
    
    /**
     * Initialize step navigation
     */
    function initStepNavigation() {
        // Next buttons
        document.getElementById('next-step-1')?.addEventListener('click', function() {
            if (validateStep(1)) {
                nextStep();
            }
        });
        
        document.getElementById('next-step-2')?.addEventListener('click', function() {
            if (validateStep(2)) {
                updateReviewSection();
                nextStep();
            }
        });
        
        // Previous buttons
        document.getElementById('prev-step-2')?.addEventListener('click', function() {
            prevStep();
        });
        
        document.getElementById('prev-step-3')?.addEventListener('click', function() {
            prevStep();
        });
    }
    
    /**
     * Show specific step
     */
    function showStep(step) {
        // Hide all steps
        document.querySelectorAll('.tkm-form-step').forEach(stepEl => {
            stepEl.classList.remove('active');
        });
        
        // Show current step
        const currentStepEl = document.getElementById(`step-${step}`);
        if (currentStepEl) {
            currentStepEl.classList.add('active');
        }
        
        // Update progress indicator
        updateStepProgress(step);
        
        currentStep = step;
    }
    
    /**
     * Update step progress indicator
     */
    function updateStepProgress(step) {
        document.querySelectorAll('.tkm-step-item').forEach((item, index) => {
            const stepNumber = index + 1;
            
            item.classList.remove('active', 'completed');
            
            if (stepNumber < step) {
                item.classList.add('completed');
            } else if (stepNumber === step) {
                item.classList.add('active');
            }
        });
        
        // Update step lines
        document.querySelectorAll('.tkm-step-line').forEach((line, index) => {
            line.classList.remove('completed');
            if (index + 1 < step) {
                line.classList.add('completed');
            }
        });
    }
    
    /**
     * Go to next step
     */
    function nextStep() {
        if (currentStep < totalSteps) {
            showStep(currentStep + 1);
        }
    }
    
    /**
     * Go to previous step
     */
    function prevStep() {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    }
    
    /**
     * Validate specific step
     */
    function validateStep(step) {
        const stepEl = document.getElementById(`step-${step}`);
        if (!stepEl) return false;
        
        let isValid = true;
        const requiredFields = stepEl.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!validateField(field)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            showNotification('Please fill in all required fields correctly.', 'error');
            scrollToFirstError();
        }
        
        return isValid;
    }
    
    /**
     * Validate all steps
     */
    function validateAllSteps() {
        let allValid = true;
        
        for (let i = 1; i <= totalSteps; i++) {
            if (!validateStep(i)) {
                allValid = false;
                break;
            }
        }
        
        // Check if all required files are uploaded
        const requiredFiles = ['front_ic', 'back_ic', 'selfie_with_note'];
        requiredFiles.forEach(fileId => {
            const fileInput = document.getElementById(fileId);
            if (fileInput && !fileInput.files.length) {
                allValid = false;
            }
        });
        
        return allValid;
    }
    
    /**
     * Initialize file uploads with enhanced functionality
     */
    function initFileUploads() {
        const fileInputs = document.querySelectorAll('.tkm-file-input');
        
        fileInputs.forEach(function(input) {
            const uploadArea = input.parentElement.querySelector('.tkm-file-upload-area');
            
            if (!uploadArea) return;
            
            // Drag and drop functionality
            uploadArea.addEventListener('dragover', handleDragOver);
            uploadArea.addEventListener('dragleave', handleDragLeave);
            uploadArea.addEventListener('drop', (e) => handleDrop(e, input));
            
            // File input change event
            input.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    handleFileSelection(input, e.target.files[0]);
                }
            });
        });
    }
    
    /**
     * Handle drag over
     */
    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.add('dragover');
    }
    
    /**
     * Handle drag leave
     */
    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('dragover');
    }
    
    /**
     * Handle file drop
     */
    function handleDrop(e, input) {
        e.preventDefault();
        e.stopPropagation();
        
        const uploadArea = input.parentElement.querySelector('.tkm-file-upload-area');
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelection(input, files[0]);
        }
    }
    
    /**
     * Handle file selection and validation
     */
    function handleFileSelection(input, file) {
        const uploadArea = input.parentElement.querySelector('.tkm-file-upload-area');
        
        // Validate file
        const validation = validateFile(file, input.id);
        
        if (!validation.valid) {
            showNotification(validation.message, 'error');
            return;
        }
        
        // Store file info
        uploadedFiles[input.id] = {
            file: file,
            name: file.name,
            size: file.size
        };
        
        // Update upload area
        const fileName = file.name;
        const fileSize = formatFileSize(file.size);
        
        uploadArea.innerHTML = `
            <div class="tkm-upload-icon">✅</div>
            <div class="tkm-upload-text">
                <strong>File Uploaded</strong>
                <br><small>${fileName} (${fileSize})</small>
            </div>
        `;
        uploadArea.style.borderColor = '#10b981';
        uploadArea.style.background = 'rgba(16, 185, 129, 0.1)';
        
        showNotification(`${fileName} uploaded successfully!`, 'success');
        
        // Update review section if on step 3
        if (currentStep === 3) {
            updateReviewSection();
        }
    }
    
    /**
     * Validate uploaded file
     */
    function validateFile(file, inputId) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        // Check file size
        if (file.size > maxSize) {
            return {
                valid: false,
                message: 'File size must be less than 5MB.'
            };
        }
        
        // Check file type
        const allowedTypes = {
            'front_ic': ['image/jpeg', 'image/png', 'application/pdf'],
            'back_ic': ['image/jpeg', 'image/png', 'application/pdf'],
            'selfie_with_note': ['image/jpeg', 'image/png']
        };
        
        const allowed = allowedTypes[inputId] || ['image/jpeg', 'image/png', 'application/pdf'];
        if (!allowed.includes(file.type)) {
            const typeText = inputId === 'selfie_with_note' ? 'JPG or PNG' : 'JPG, PNG, or PDF';
            return {
                valid: false,
                message: `Please upload a ${typeText} file.`
            };
        }
        
        return { valid: true };
    }
    
    /**
     * Format file size for display
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    /**
     * Initialize review section
     */
    function initReviewSection() {
        // Initialize re-upload buttons
        const reuploadButtons = document.querySelectorAll('.tkm-reupload-btn');
        reuploadButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetInput = document.getElementById(targetId);
                if (targetInput) {
                    targetInput.click();
                }
            });
        });
    }
    
    /**
     * Update review section with uploaded files
     */
    function updateReviewSection() {
        const fileMapping = {
            'front_ic': 'review-front-ic',
            'back_ic': 'review-back-ic',
            'selfie_with_note': 'review-selfie-note'
        };
        
        Object.keys(fileMapping).forEach(fileId => {
            const reviewItem = document.getElementById(fileMapping[fileId]);
            if (!reviewItem) return;
            
            const checkItems = reviewItem.querySelectorAll('.tkm-check-item');
            const fileInput = document.getElementById(fileId);
            
            if (fileInput && fileInput.files.length > 0) {
                // Mark all checks as completed for uploaded files
                checkItems.forEach(item => {
                    item.classList.add('completed');
                    const icon = item.querySelector('.tkm-check-icon');
                    if (icon) {
                        icon.textContent = '✅';
                    }
                });
                
                // Update review item styling
                reviewItem.style.borderColor = 'rgba(16, 185, 129, 0.5)';
                reviewItem.style.background = 'rgba(16, 185, 129, 0.05)';
            } else {
                // Reset checks for files not uploaded
                checkItems.forEach(item => {
                    item.classList.remove('completed');
                    const icon = item.querySelector('.tkm-check-icon');
                    if (icon) {
                        icon.textContent = '❌';
                    }
                });
                
                // Reset review item styling
                reviewItem.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                reviewItem.style.background = 'rgba(255, 255, 255, 0.05)';
            }
        });
    }
    
    /**
     * Initialize form validation
     */
    function initFormValidation() {
        const inputs = document.querySelectorAll('.tkm-input, .tkm-textarea, .tkm-select');
        
        inputs.forEach(function(input) {
            input.addEventListener('blur', function() {
                validateField(input);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(input);
            });
        });
        
        // Phone number formatting
        const phoneInput = document.getElementById('phone_number');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                // Remove non-numeric characters
                let value = e.target.value.replace(/\D/g, '');
                e.target.value = value;
            });
        }
    }
    
    /**
     * Validate individual field
     */
    function validateField(field) {
        const value = field.value.trim();
        const fieldName = field.previousElementSibling?.textContent?.replace(/ \*/g, '').replace(/ \(.*\)/g, '') || 'Field';
        
        // Required field validation
        if (field.hasAttribute('required') && !value) {
            showFieldError(field, `${fieldName} is required.`);
            return false;
        }
        
        // Specific field validations
        switch (field.id) {
            case 'full_name':
                return validateFullName(field);
            case 'date_of_birth':
                return validateDateOfBirth(field);
            case 'phone_number':
                return validatePhoneNumber(field);
            case 'postal_code':
                return validatePostalCode(field);
        }
        
        clearFieldError(field);
        return true;
    }
    
    /**
     * Validate full name
     */
    function validateFullName(field) {
        const value = field.value.trim();
        
        if (value.length < 2) {
            showFieldError(field, 'Full name must be at least 2 characters long.');
            return false;
        }
        
        if (!/^[a-zA-Z\s'-@.]+$/.test(value)) {
            showFieldError(field, 'Full name can only contain letters, spaces, hyphens, apostrophes, @ and dots.');
            return false;
        }
        
        clearFieldError(field);
        return true;
    }
    
    /**
     * Validate date of birth
     */
    function validateDateOfBirth(field) {
        const value = field.value;
        
        if (!value) {
            if (field.hasAttribute('required')) {
                showFieldError(field, 'Date of birth is required.');
                return false;
            }
            return true;
        }
        
        const dob = new Date(value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        
        if (age < 18) {
            showFieldError(field, 'You must be at least 18 years old.');
            return false;
        }
        
        if (age > 120) {
            showFieldError(field, 'Please enter a valid date of birth.');
            return false;
        }
        
        clearFieldError(field);
        return true;
    }
    
    /**
     * Validate phone number
     */
    function validatePhoneNumber(field) {
        const value = field.value.trim();
        
        if (value && !/^\d{7,15}$/.test(value)) {
            showFieldError(field, 'Please enter a valid phone number (7-15 digits).');
            return false;
        }
        
        clearFieldError(field);
        return true;
    }
    
    /**
     * Validate postal code
     */
    function validatePostalCode(field) {
        const value = field.value.trim();
        
        if (value && !/^[0-9A-Za-z\s-]{3,10}$/.test(value)) {
            showFieldError(field, 'Please enter a valid postal code.');
            return false;
        }
        
        clearFieldError(field);
        return true;
    }
    
    /**
     * Show field error
     */
    function showFieldError(field, message) {
        clearFieldError(field);
        
        field.style.borderColor = '#ef4444';
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'tkm-field-error';
        errorDiv.style.cssText = `
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 5px;
            padding: 5px 0;
        `;
        errorDiv.textContent = message;
        
        field.parentElement.appendChild(errorDiv);
    }
    
    /**
     * Clear field error
     */
    function clearFieldError(field) {
        field.style.borderColor = 'rgba(255, 255, 255, 0.2)';
        
        const errorDiv = field.parentElement.querySelector('.tkm-field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    /**
     * Scroll to first error
     */
    function scrollToFirstError() {
        const firstError = document.querySelector('.tkm-field-error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    /**
     * Show notification
     */
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `tkm-message tkm-message-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;
        
        const icon = type === 'success' ? '✅' : '❌';
        notification.innerHTML = `
            <div class="tkm-message-icon">${icon}</div>
            <div class="tkm-message-text">${message}</div>
            <button type="button" style="background: none; border: none; color: inherit; cursor: pointer; padding: 5px; margin-left: 10px;" onclick="this.parentElement.remove()">×</button>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(function() {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            if (notification.parentElement) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                
                setTimeout(function() {
                    if (notification.parentElement) {
                        notification.parentElement.removeChild(notification);
                    }
                }, 300);
            }
        }, 5000);
    }
    
    /**
     * Initialize keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Enter to go to next step (except on textareas)
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                if (currentStep < totalSteps) {
                    e.preventDefault();
                    const nextBtn = document.querySelector(`#next-step-${currentStep}`);
                    if (nextBtn) {
                        nextBtn.click();
                    }
                }
            }
            
            // Escape to close notifications
            if (e.key === 'Escape') {
                const notifications = document.querySelectorAll('.tkm-message[style*="position: fixed"]');
                notifications.forEach(notification => notification.remove());
            }
        });
    }
    
    initKeyboardShortcuts();
    
    /**
     * Initialize KYC form functionality (legacy support)
     */
    function initKycForm() {
        const form = document.getElementById('kyc-form');
        const submitBtn = document.getElementById('submit-btn');
        const loadingOverlay = document.getElementById('loading-overlay');
        
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading overlay
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
            }
            
            // Disable submit button
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <div class="tkm-loading-spinner" style="width: 20px; height: 20px; margin-right: 10px;"></div>
                    Processing...
                `;
            }
        });
    }
    
    /**
     * Initialize file upload functionality
     */
    function initFileUploads() {
        const fileInputs = document.querySelectorAll('.tkm-file-input');
        
        fileInputs.forEach(function(input) {
            const uploadArea = input.parentElement.querySelector('.tkm-file-upload-area');
            
            if (!uploadArea) return;
            
            // Drag and drop functionality
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                uploadArea.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFileSelection(input, files[0]);
                }
            });
            
            // File input change event
            input.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    handleFileSelection(input, e.target.files[0]);
                }
            });
        });
    }
    
    /**
     * Handle file selection and validation
     */
    function handleFileSelection(input, file) {
        const uploadArea = input.parentElement.querySelector('.tkm-file-upload-area');
        const fileInfo = input.parentElement.querySelector('.tkm-file-info');
        
        // Validate file
        const validation = validateFile(file, input.id);
        
        if (!validation.valid) {
            showNotification(validation.message, 'error');
            return;
        }
        
        // Show file selected state
        const fileName = file.name;
        const fileSize = formatFileSize(file.size);
        
        // Update upload area
        uploadArea.innerHTML = `
            <div class="tkm-upload-icon">📎</div>
            <div class="tkm-upload-text">
                <strong>File Selected</strong>
                <br><small>${fileName} (${fileSize})</small>
            </div>
        `;
        uploadArea.style.borderColor = '#00954b';
        uploadArea.style.background = '#f0fff4';
        
        // Add file selected indicator
        let selectedDiv = input.parentElement.querySelector('.tkm-file-selected');
        if (!selectedDiv) {
            selectedDiv = document.createElement('div');
            selectedDiv.className = 'tkm-file-selected';
            input.parentElement.appendChild(selectedDiv);
        }
        
        selectedDiv.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>✅ ${fileName} (${fileSize})</span>
                <button type="button" class="tkm-remove-file" data-input="${input.id}" style="background: none; border: none; color: #dc3545; cursor: pointer; padding: 5px;">
                    ❌
                </button>
            </div>
        `;
        
        // Add remove file functionality
        const removeBtn = selectedDiv.querySelector('.tkm-remove-file');
        removeBtn.addEventListener('click', function() {
            removeFile(input);
        });
        
        showNotification('File uploaded successfully!', 'success');
    }
    
    /**
     * Remove selected file
     */
    function removeFile(input) {
        const uploadArea = input.parentElement.querySelector('.tkm-file-upload-area');
        const selectedDiv = input.parentElement.querySelector('.tkm-file-selected');
        
        // Reset file input
        input.value = '';
        
        // Reset upload area
        const isRequired = input.hasAttribute('required');
        const fieldName = input.id === 'government_id' ? 'Government-issued ID' : 'Selfie with ID';
        const acceptedTypes = input.id === 'government_id' ? 'JPG, PNG, PDF (max 5MB)' : 'JPG, PNG (max 5MB)';
        const icon = input.id === 'government_id' ? '📄' : '🤳';
        
        uploadArea.innerHTML = `
            <div class="tkm-upload-icon">${icon}</div>
            <div class="tkm-upload-text">
                <strong>Choose file</strong> or drag and drop
                <br><small>${acceptedTypes}</small>
            </div>
        `;
        uploadArea.style.borderColor = '#dee2e6';
        uploadArea.style.background = '#f8f9fa';
        
        // Remove file selected indicator
        if (selectedDiv) {
            selectedDiv.remove();
        }
    }
    
    /**
     * Validate uploaded file
     */
    function validateFile(file, inputId) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        // Check file size
        if (file.size > maxSize) {
            return {
                valid: false,
                message: 'File size must be less than 5MB.'
            };
        }
        
        // Check file type
        const allowedTypes = {
            'government_id': ['image/jpeg', 'image/png', 'application/pdf'],
            'selfie_with_id': ['image/jpeg', 'image/png']
        };
        
        const allowed = allowedTypes[inputId] || [];
        if (!allowed.includes(file.type)) {
            const typeText = inputId === 'government_id' ? 'JPG, PNG, or PDF' : 'JPG or PNG';
            return {
                valid: false,
                message: `Please upload a ${typeText} file.`
            };
        }
        
        return { valid: true };
    }
    
    /**
     * Format file size for display
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    /**
     * Initialize form validation
     */
    function initFormValidation() {
        const form = document.getElementById('kyc-form');
        if (!form) return;
        
        const inputs = form.querySelectorAll('.tkm-input, .tkm-textarea');
        
        inputs.forEach(function(input) {
            input.addEventListener('blur', function() {
                validateField(input);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(input);
            });
        });
        
        // Real-time validation for date of birth
        const dobInput = document.getElementById('date_of_birth');
        if (dobInput) {
            dobInput.addEventListener('change', function() {
                validateDateOfBirth(dobInput);
            });
        }
    }
    
    /**
     * Validate individual field
     */
    function validateField(field) {
        const value = field.value.trim();
        const fieldName = field.previousElementSibling.textContent.replace(' *', '').replace(' (Optional)', '').replace(' (Recommended)', '');
        
        // Required field validation
        if (field.hasAttribute('required') && !value) {
            showFieldError(field, `${fieldName} is required.`);
            return false;
        }
        
        // Specific field validations
        switch (field.id) {
            case 'full_name':
                return validateFullName(field);
            case 'date_of_birth':
                return validateDateOfBirth(field);
            case 'address':
                return validateAddress(field);
        }
        
        clearFieldError(field);
        return true;
    }
    
    /**
     * Validate full name
     */
    function validateFullName(field) {
        const value = field.value.trim();
        
        if (value.length < 2) {
            showFieldError(field, 'Full name must be at least 2 characters long.');
            return false;
        }
        
        if (!/^[a-zA-Z\s'-]+$/.test(value)) {
            showFieldError(field, 'Full name can only contain letters, spaces, hyphens, and apostrophes.');
            return false;
        }
        
        clearFieldError(field);
        return true;
    }
    
    /**
     * Validate date of birth
     */
    function validateDateOfBirth(field) {
        const value = field.value;
        
        if (!value) {
            if (field.hasAttribute('required')) {
                showFieldError(field, 'Date of birth is required.');
                return false;
            }
            return true;
        }
        
        const dob = new Date(value);
        const today = new Date();
        const age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        
        if (age < 18) {
            showFieldError(field, 'You must be at least 18 years old.');
            return false;
        }
        
        if (age > 120) {
            showFieldError(field, 'Please enter a valid date of birth.');
            return false;
        }
        
        clearFieldError(field);
        return true;
    }
    
    /**
     * Validate address
     */
    function validateAddress(field) {
        const value = field.value.trim();
        
        if (value.length < 10) {
            showFieldError(field, 'Please provide a complete address (at least 10 characters).');
            return false;
        }
        
        clearFieldError(field);
        return true;
    }
    
    /**
     * Show field error
     */
    function showFieldError(field, message) {
        clearFieldError(field);
        
        field.style.borderColor = '#dc3545';
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'tkm-field-error';
        errorDiv.style.color = '#dc3545';
        errorDiv.style.fontSize = '0.875rem';
        errorDiv.style.marginTop = '5px';
        errorDiv.textContent = message;
        
        field.parentElement.appendChild(errorDiv);
    }
    
    /**
     * Clear field error
     */
    function clearFieldError(field) {
        field.style.borderColor = '#e9ecef';
        
        const errorDiv = field.parentElement.querySelector('.tkm-field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    /**
     * Validate entire form
     */
    function validateForm() {
        const form = document.getElementById('kyc-form');
        if (!form) return true;
        
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(function(field) {
            if (!validateField(field)) {
                isValid = false;
            }
        });
        
        // Validate file uploads
        const governmentIdInput = document.getElementById('government_id');
        if (governmentIdInput && governmentIdInput.hasAttribute('required') && !governmentIdInput.files.length) {
            showNotification('Please upload your government-issued ID.', 'error');
            isValid = false;
        }
        
        if (!isValid) {
            showNotification('Please fix the errors above before submitting.', 'error');
            scrollToFirstError();
        }
        
        return isValid;
    }
    
    /**
     * Scroll to first error
     */
    function scrollToFirstError() {
        const firstError = document.querySelector('.tkm-field-error, .tkm-message-error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    /**
     * Initialize progress tracking
     */
    function initProgressTracking() {
        const form = document.getElementById('kyc-form');
        if (!form) return;
        
        const requiredFields = form.querySelectorAll('[required]');
        const progressBar = createProgressBar();
        
        if (progressBar) {
            form.insertBefore(progressBar, form.firstChild);
            updateProgress();
            
            // Update progress on field changes
            requiredFields.forEach(function(field) {
                field.addEventListener('input', updateProgress);
                field.addEventListener('change', updateProgress);
            });
        }
        
        function updateProgress() {
            let completedFields = 0;
            
            requiredFields.forEach(function(field) {
                if (field.type === 'file') {
                    if (field.files.length > 0) completedFields++;
                } else if (field.value.trim()) {
                    completedFields++;
                }
            });
            
            const percentage = (completedFields / requiredFields.length) * 100;
            const progressFill = progressBar.querySelector('.tkm-progress-fill');
            const progressText = progressBar.querySelector('.tkm-progress-text');
            
            if (progressFill) {
                progressFill.style.width = percentage + '%';
            }
            
            if (progressText) {
                progressText.textContent = `${Math.round(percentage)}% Complete`;
            }
        }
    }
    
    /**
     * Create progress bar
     */
    function createProgressBar() {
        const progressContainer = document.createElement('div');
        progressContainer.className = 'tkm-progress-container';
        progressContainer.style.cssText = `
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        `;
        
        progressContainer.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-weight: 600; color: #495057;">Form Progress</span>
                <span class="tkm-progress-text" style="font-weight: 500; color: #00954b;">0% Complete</span>
            </div>
            <div style="background: #dee2e6; border-radius: 4px; height: 8px; overflow: hidden;">
                <div class="tkm-progress-fill" style="background: linear-gradient(90deg, #00954b 0%, #00c851 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
            </div>
        `;
        
        return progressContainer;
    }
    
    /**
     * Initialize notifications
     */
    function initNotifications() {
        // Auto-hide success messages after 5 seconds
        const successMessages = document.querySelectorAll('.tkm-message-success');
        successMessages.forEach(function(message) {
            setTimeout(function() {
                fadeOut(message);
            }, 5000);
        });
    }
    
    /**
     * Show notification
     */
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `tkm-message tkm-message-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;
        
        const icon = type === 'success' ? '✅' : '❌';
        notification.innerHTML = `
            <div class="tkm-message-icon">${icon}</div>
            <div class="tkm-message-text">${message}</div>
            <button type="button" style="background: none; border: none; color: inherit; cursor: pointer; padding: 5px; margin-left: 10px;" onclick="this.parentElement.remove()">×</button>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(function() {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            fadeOut(notification);
        }, 5000);
    }
    
    /**
     * Fade out element
     */
    function fadeOut(element) {
        element.style.opacity = '0';
        element.style.transform = 'translateX(100%)';
        
        setTimeout(function() {
            if (element.parentElement) {
                element.parentElement.removeChild(element);
            }
        }, 300);
    }
    
    /**
     * Initialize responsive sidebar handling
     */
    function initResponsiveSidebar() {
        const content = document.querySelector('.tkm-kyc-content');
        const sidebar = document.querySelector('.tkm-sidebar'); // Assuming sidebar class
        
        if (!content) return;
        
        function handleResize() {
            if (window.innerWidth <= 768) {
                content.style.marginLeft = '0';
            } else if (window.innerWidth <= 1200) {
                content.style.marginLeft = '260px';
            } else {
                content.style.marginLeft = '280px';
            }
        }
        
        window.addEventListener('resize', handleResize);
        handleResize(); // Initial call
    }
    
    // Initialize responsive sidebar
    initResponsiveSidebar();
    
    /**
     * Initialize smooth scrolling for internal links
     */
    function initSmoothScrolling() {
        const links = document.querySelectorAll('a[href^="#"]');
        
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }
    
    initSmoothScrolling();
    
    /**
     * Initialize keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + Enter to submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const form = document.getElementById('kyc-form');
                const submitBtn = document.getElementById('submit-btn');
                
                if (form && submitBtn && !submitBtn.disabled) {
                    e.preventDefault();
                    submitBtn.click();
                }
            }
            
            // Escape to close notifications
            if (e.key === 'Escape') {
                const notifications = document.querySelectorAll('.tkm-message');
                notifications.forEach(function(notification) {
                    if (notification.style.position === 'fixed') {
                        fadeOut(notification);
                    }
                });
            }
        });
    }
     initKeyboardShortcuts();

})();
