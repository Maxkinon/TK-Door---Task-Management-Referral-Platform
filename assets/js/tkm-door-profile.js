/**
 * TKM Door Profile JavaScript
 * Handles profile management interactions and validations
 * Version: 1.0.0
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeProfile();
    });

    function initializeProfile() {
        initializePasswordToggles();
        initializePasswordStrength();
        initializePasswordMatch();
        initializeFormValidation();
        initializeMediaLibrary();
        initializeCopyButtons();
        initializeFormSubmissions();
    }

    // Password visibility toggles
    function initializePasswordToggles() {
        const toggleButtons = document.querySelectorAll('.tkm-password-toggle');
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetInput = document.getElementById(targetId);
                
                if (targetInput) {
                    if (targetInput.type === 'password') {
                        targetInput.type = 'text';
                        this.textContent = '🙈';
                    } else {
                        targetInput.type = 'password';
                        this.textContent = '👁️';
                    }
                }
            });
        });
    }

    // Password strength indicator
    function initializePasswordStrength() {
        const passwordInput = document.getElementById('new_password');
        const strengthIndicator = document.getElementById('password-strength');
        
        if (passwordInput && strengthIndicator) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);
                
                strengthIndicator.className = 'tkm-password-strength ' + strength.class;
                strengthIndicator.textContent = strength.text;
            });
        }
    }

    function calculatePasswordStrength(password) {
        if (password.length === 0) {
            return { class: '', text: '' };
        }
        
        let score = 0;
        
        // Length check
        if (password.length >= 8) score += 1;
        if (password.length >= 12) score += 1;
        
        // Character variety checks
        if (/[a-z]/.test(password)) score += 1;
        if (/[A-Z]/.test(password)) score += 1;
        if (/[0-9]/.test(password)) score += 1;
        if (/[^A-Za-z0-9]/.test(password)) score += 1;
        
        if (score < 3) {
            return { class: 'weak', text: '⚠️ Weak password' };
        } else if (score < 5) {
            return { class: 'medium', text: '⚡ Medium strength' };
        } else {
            return { class: 'strong', text: '✅ Strong password' };
        }
    }

    // Password confirmation matching
    function initializePasswordMatch() {
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const matchIndicator = document.getElementById('password-match');
        
        if (newPasswordInput && confirmPasswordInput && matchIndicator) {
            function checkPasswordMatch() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword.length === 0) {
                    matchIndicator.className = 'tkm-password-match';
                    matchIndicator.textContent = '';
                    return;
                }
                
                if (newPassword === confirmPassword) {
                    matchIndicator.className = 'tkm-password-match match';
                    matchIndicator.textContent = '✅ Passwords match';
                } else {
                    matchIndicator.className = 'tkm-password-match no-match';
                    matchIndicator.textContent = '❌ Passwords do not match';
                }
            }
            
            newPasswordInput.addEventListener('input', checkPasswordMatch);
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
    }

    // Form validation
    function initializeFormValidation() {
        const profileForm = document.getElementById('profile-form');
        const passwordForm = document.getElementById('password-form');
        
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                if (!validateProfileForm()) {
                    e.preventDefault();
                    showMessage('Please fix the errors in the form.', 'error');
                }
            });
        }
        
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                if (!validatePasswordForm()) {
                    e.preventDefault();
                    showMessage('Please fix the errors in the password form.', 'error');
                }
            });
        }
    }

    function validateProfileForm() {
        const fullName = document.getElementById('full_name');
        const email = document.getElementById('email');
        let isValid = true;
        
        // Clear previous validation
        clearValidationErrors();
        
        // Validate full name
        if (!fullName.value.trim()) {
            showFieldError(fullName, 'Full name is required');
            isValid = false;
        }
        
        // Validate email
        if (!email.value.trim()) {
            showFieldError(email, 'Email is required');
            isValid = false;
        } else if (!isValidEmail(email.value)) {
            showFieldError(email, 'Please enter a valid email address');
            isValid = false;
        }
        
        return isValid;
    }

    function validatePasswordForm() {
        const currentPassword = document.getElementById('current_password');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        let isValid = true;
        
        // Clear previous validation
        clearValidationErrors();
        
        // Validate current password
        if (!currentPassword.value.trim()) {
            showFieldError(currentPassword, 'Current password is required');
            isValid = false;
        }
        
        // Validate new password
        if (!newPassword.value.trim()) {
            showFieldError(newPassword, 'New password is required');
            isValid = false;
        } else if (newPassword.value.length < 8) {
            showFieldError(newPassword, 'Password must be at least 8 characters long');
            isValid = false;
        }
        
        // Validate password confirmation
        if (newPassword.value !== confirmPassword.value) {
            showFieldError(confirmPassword, 'Password confirmation does not match');
            isValid = false;
        }
        
        return isValid;
    }

    function showFieldError(field, message) {
        field.style.borderColor = '#ef4444';
        
        // Remove existing error message
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        
        // Add new error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.color = '#ef4444';
        errorDiv.style.fontSize = '0.875rem';
        errorDiv.style.marginTop = '4px';
        errorDiv.textContent = message;
        
        field.parentNode.appendChild(errorDiv);
    }

    function clearValidationErrors() {
        // Reset border colors
        document.querySelectorAll('.tkm-input, .tkm-textarea').forEach(input => {
            input.style.borderColor = '';
        });
        
        // Remove error messages
        document.querySelectorAll('.field-error').forEach(error => {
            error.remove();
        });
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Media library for profile picture
    function initializeMediaLibrary() {
        const changePictureBtn = document.getElementById('change-picture-btn');
        
        if (changePictureBtn && typeof wp !== 'undefined' && wp.media) {
            changePictureBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const mediaUploader = wp.media({
                    title: 'Choose Profile Picture',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    const imageUrl = attachment.sizes && attachment.sizes.medium 
                        ? attachment.sizes.medium.url 
                        : attachment.url;
                    
                    // Update preview
                    const preview = document.getElementById('profile-picture-preview');
                    if (preview) {
                        preview.src = imageUrl;
                    }
                    
                    // Update hidden input
                    const hiddenInput = document.getElementById('profile_picture_url');
                    if (hiddenInput) {
                        hiddenInput.value = imageUrl;
                    }
                    
                    showMessage('Profile picture updated! Remember to save your changes.', 'success');
                });
                
                mediaUploader.open();
            });
        } else if (changePictureBtn) {
            // Fallback for when media library is not available
            changePictureBtn.addEventListener('click', function(e) {
                e.preventDefault();
                showMessage('Media library is not available. Please contact support.', 'error');
            });
        }
    }

    // Copy to clipboard functionality
    function initializeCopyButtons() {
        const copyButtons = document.querySelectorAll('.tkm-copy-btn');
        
        copyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-copy');
                const targetInput = document.getElementById(targetId);
                
                if (targetInput) {
                    targetInput.select();
                    targetInput.setSelectionRange(0, 99999); // For mobile devices
                    
                    try {
                        document.execCommand('copy');
                        this.textContent = '✅ Copied!';
                        this.style.background = '#10b981';
                        
                        setTimeout(() => {
                            this.textContent = targetId === 'referral-code' ? '📋 Copy' : '📋 Copy Link';
                            this.style.background = '';
                        }, 2000);
                        
                        showMessage('Copied to clipboard!', 'success');
                    } catch (err) {
                        console.error('Copy failed:', err);
                        showMessage('Failed to copy. Please copy manually.', 'error');
                    }
                }
            });
        });
    }

    // Form submission with loading states
    function initializeFormSubmissions() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                showLoading();
                
                // Auto-hide loading after 10 seconds as failsafe
                setTimeout(hideLoading, 10000);
            });
        });
        
        // Hide loading on page load (in case of redirect back)
        hideLoading();
    }

    function showLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    function hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    function showMessage(message, type) {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.tkm-temp-message');
        existingMessages.forEach(msg => msg.remove());
        
        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `tkm-message tkm-message-${type} tkm-temp-message fade-in-up`;
        
        const icon = type === 'success' ? '✅' : '❌';
        messageDiv.innerHTML = `
            <div class="tkm-message-icon">${icon}</div>
            <div class="tkm-message-text">${message}</div>
        `;
        
        // Insert after header
        const header = document.querySelector('.tkm-profile-header');
        if (header && header.nextSibling) {
            header.parentNode.insertBefore(messageDiv, header.nextSibling);
        } else {
            document.querySelector('.tkm-profile-content').appendChild(messageDiv);
        }
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
        
        // Scroll to message
        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Utility functions
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Export for global access if needed
    window.TkmProfile = {
        showMessage,
        showLoading,
        hideLoading,
        validateProfileForm,
        validatePasswordForm
    };

})();
