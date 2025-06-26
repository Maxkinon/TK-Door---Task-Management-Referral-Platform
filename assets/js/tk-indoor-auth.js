/**
 * Indoor Tasks Authentication
 * Handles all authentication related functionality
 */

(function($) {
    'use strict';

    // Store form states
    const state = {
        currentForm: 'login',
        isSubmitting: false,
        recaptchaInitialized: false
    };

    /**
     * Form Utilities
     */
    const FormUtils = {
        // Show loading state on button
        setLoading: function(button, isLoading) {
            if (isLoading) {
                button.prop('disabled', true).addClass('loading');
                button.data('original-text', button.text());
                button.text('');
            } else {
                button.prop('disabled', false).removeClass('loading');
                if (button.data('original-text')) {
                    button.text(button.data('original-text'));
                }
            }
        },

        // Show form message
        showMessage: function(form, type, message) {
            const messageHtml = `<div class="tk-form-message ${type}">${message}</div>`;
            form.find('.tk-form-message').remove();
            form.prepend(messageHtml);
        },

        // Clear form messages
        clearMessages: function(form) {
            form.find('.tk-form-message').remove();
        },

        // Reset form fields
        resetForm: function(form) {
            form[0].reset();
            form.find('.tk-password-strength-bar').removeClass('weak medium strong');
            FormUtils.clearMessages(form);
        },

        // Check password strength
        checkPasswordStrength: function(password) {
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength++;
            
            // Character type checks
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            return {
                score: strength,
                label: strength <= 2 ? 'weak' : strength === 3 ? 'medium' : 'strong'
            };
        },

        // Update password strength indicator
        updatePasswordStrength: function(password) {
            const strength = FormUtils.checkPasswordStrength(password);
            const bar = $('.tk-password-strength-bar');
            
            bar.removeClass('weak medium strong').addClass(strength.label);
            
            return strength.score >= 3; // Return true if password is acceptable
        },

        // Frontend email domain validation - only allow specific domains
        validateEmailDomain: function(email) {
            const allowedDomains = ['gmail.com', 'outlook.com', 'yahoo.com', 'icloud.com'];
            const emailDomain = email.split('@')[1];
            
            if (!emailDomain || !allowedDomains.includes(emailDomain.toLowerCase())) {
                return Promise.reject('We only accept emails from Gmail, Outlook, Yahoo, or iCloud.');
            }
            
            return Promise.resolve();
        },

        // Real-time email domain checker (visual feedback)
        checkEmailDomainRealTime: function(emailInput) {
            const email = emailInput.val();
            const formGroup = emailInput.closest('.tk-form-group');
            let feedbackElement = formGroup.find('.tk-email-feedback');
            
            // Create feedback element if it doesn't exist
            if (feedbackElement.length === 0) {
                feedbackElement = $('<div class="tk-email-feedback"></div>');
                emailInput.after(feedbackElement);
            }
            
            // Clear previous feedback
            feedbackElement.removeClass('valid invalid').empty();
            
            if (!email || !email.includes('@')) {
                return;
            }
            
            const allowedDomains = ['gmail.com', 'outlook.com', 'yahoo.com', 'icloud.com'];
            const emailDomain = email.split('@')[1];
            
            if (!emailDomain) {
                return;
            }
            
            if (allowedDomains.includes(emailDomain.toLowerCase())) {
                feedbackElement.addClass('valid').html('✓ Email domain accepted');
            } else {
                feedbackElement.addClass('invalid').html('✗ We only accept Gmail, Outlook, Yahoo, or iCloud emails');
            }
        }
    };

    /**
     * Form Navigation
     */
    const FormNavigation = {
        // Show specified form
        showForm: function(formId) {
            const currentCard = $(`.tk-auth-card:visible`);
            const targetCard = $(`#tk-${formId}-card`);
            
            // Add hiding class to current form
            currentCard.addClass('hiding');
            
            // After brief animation, switch forms
            setTimeout(() => {
                currentCard.hide().removeClass('hiding');
                targetCard.show();
                
                // Clear form and messages
                FormUtils.resetForm(targetCard.find('form'));
                
                // Update state
                state.currentForm = formId;
                
                // Refresh reCAPTCHA if enabled
                FormNavigation.refreshRecaptcha(formId);
            }, 300);
        },

        // Refresh reCAPTCHA token
        refreshRecaptcha: function(action) {
            if (typeof grecaptcha !== 'undefined' && tkAuth.recaptcha_enabled) {
                grecaptcha.ready(function() {
                    grecaptcha.execute(tkAuth.recaptcha_site_key, { action: action })
                        .then(function(token) {
                            $(`#recaptcha_token_${action}`).val(token);
                        });
                });
            }
        }
    };

    /**
     * Form Submissions
     */
    const FormHandler = {
        // Handle login form submission
        handleLogin: function(e) {
            e.preventDefault();
            if (state.isSubmitting) return;

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            
            // Get form data
            const data = {
                action: 'indoor_tasks_login',
                email: form.find('input[name="username"]').val(),
                password: form.find('input[name="password"]').val(),
                remember: form.find('input[name="remember"]').prop('checked'),
                nonce: form.find('input[name="login_nonce"]').val()
            };

            // Add reCAPTCHA token if enabled
            if (tkAuth.recaptcha_enabled) {
                data.recaptcha = form.find('input[name="recaptcha_token"]').val();
            }

            // Update state and UI
            state.isSubmitting = true;
            FormUtils.setLoading(submitBtn, true);
            FormUtils.clearMessages(form);

            // Submit request
            $.post(tkAuth.ajaxurl, data, function(response) {
                if (response.success) {
                    FormUtils.showMessage(form, 'success', response.data.message || 'Login successful!');
                    setTimeout(() => {
                        window.location.href = response.data.redirect || tkAuth.home_url;
                    }, 1000);
                } else {
                    FormUtils.showMessage(form, 'error', response.data.message || 'Login failed. Please try again.');
                    FormUtils.setLoading(submitBtn, false);
                    FormNavigation.refreshRecaptcha('login');
                }
            }).fail(function() {
                FormUtils.showMessage(form, 'error', 'Connection error. Please try again.');
                FormUtils.setLoading(submitBtn, false);
                FormNavigation.refreshRecaptcha('login');
            }).always(function() {
                state.isSubmitting = false;
            });
        },

        // Handle registration form submission
        handleRegister: function(e) {
            e.preventDefault();
            if (state.isSubmitting) return;

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const password = form.find('input[name="password"]').val();
            const email = form.find('input[name="email"]').val();

            // Validate password strength
            if (!FormUtils.updatePasswordStrength(password)) {
                FormUtils.showMessage(form, 'error', 'Please choose a stronger password');
                return;
            }

            // Update state and UI
            state.isSubmitting = true;
            FormUtils.setLoading(submitBtn, true);
            FormUtils.clearMessages(form);

            // Validate email domain first
            if (typeof FormUtils.validateEmailDomain !== 'function') {
                console.error('FormUtils.validateEmailDomain is not a function');
                FormUtils.showMessage(form, 'error', 'Email validation function not available');
                FormUtils.setLoading(submitBtn, false);
                state.isSubmitting = false;
                return;
            }

            FormUtils.validateEmailDomain(email).then(() => {
                // Send OTP first for email verification
                FormUtils.showMessage(form, 'info', 'Sending verification code to your email...');
                
                // Get reCAPTCHA token if enabled
                let recaptchaToken = '';
                if (tkAuth.recaptcha_enabled) {
                    recaptchaToken = form.find('input[name="recaptcha_token"]').val();
                }

                // Send OTP for registration verification
                const authNonce = form.find('input[name="auth_nonce"]').val() || 
                                 $('input[name="auth_nonce"]').val() ||
                                 tkAuth.nonces?.auth || '';
                console.log('🔒 Using auth nonce for OTP request:', authNonce ? authNonce.substring(0, 10) + '...' : 'NOT FOUND');
                
                if (!authNonce) {
                    console.error('❌ Auth nonce not found! Available inputs:', form.find('input[name]').map(function() { return this.name; }).get());
                    FormUtils.showMessage(form, 'error', 'Security token not found. Please refresh the page and try again.');
                    FormUtils.setLoading(submitBtn, false);
                    state.isSubmitting = false;
                    return;
                }
                
                $.post(tkAuth.ajaxurl, {
                    action: 'indoor_tasks_auth',
                    step: 'send_otp',
                    email: email,
                    recaptcha: recaptchaToken,
                    nonce: authNonce
                }).done(function(response) {
                    console.log('OTP Send Response:', response);
                    if (response.success) {
                        // Store registration data temporarily
                        const registrationData = {
                            name: form.find('input[name="name"]').val(),
                            email: email,
                            password: password,
                            phone_number: form.find('input[name="phone_number"]').val(),
                            country: form.find('select[name="country"]').val(),
                            referral_code: form.find('input[name="referral_code"]').val()
                        };
                        
                        // Store in sessionStorage for verification step
                        sessionStorage.setItem('pending_registration', JSON.stringify(registrationData));
                        
                        // Show OTP verification form
                        $('#register-otp-email').val(email);
                        FormNavigation.showForm('register-otp-verify');
                        FormUtils.showMessage($('#tk-register-otp-verify-card form'), 'success', response.data.message);
                    } else {
                        FormUtils.showMessage(form, 'error', response.data.message || 'Failed to send verification code.');
                        FormUtils.setLoading(submitBtn, false);
                        FormNavigation.refreshRecaptcha('register');
                    }
                }).fail(function() {
                    FormUtils.showMessage(form, 'error', 'Connection error. Please try again.');
                    FormUtils.setLoading(submitBtn, false);
                    FormNavigation.refreshRecaptcha('register');
                }).always(function() {
                    state.isSubmitting = false;
                });
            }).catch((error) => {
                // Email domain validation failed
                FormUtils.showMessage(form, 'error', error);
                FormUtils.setLoading(submitBtn, false);
                state.isSubmitting = false;
            });
        },

        // Handle password reset form submission
        handleReset: function(e) {
            e.preventDefault();
            if (state.isSubmitting) return;

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');

            // Get form data
            const data = {
                action: 'indoor_tasks_forgot_password',
                email: form.find('input[name="email"]').val(),
                nonce: form.find('input[name="reset_nonce"]').val()
            };

            // Add reCAPTCHA token if enabled
            if (tkAuth.recaptcha_enabled) {
                data.recaptcha = form.find('input[name="recaptcha_token"]').val();
            }

            // Update state and UI
            state.isSubmitting = true;
            FormUtils.setLoading(submitBtn, true);
            FormUtils.clearMessages(form);

            // Submit request
            $.post(tkAuth.ajaxurl, data, function(response) {
                if (response.success) {
                    FormUtils.showMessage(form, 'success', response.data.message || 'Reset link sent!');
                    form[0].reset();
                } else {
                    FormUtils.showMessage(form, 'error', response.data.message || 'Failed to send reset link.');
                }
            }).fail(function() {
                FormUtils.showMessage(form, 'error', 'Connection error. Please try again.');
            }).always(function() {
                FormUtils.setLoading(submitBtn, false);
                state.isSubmitting = false;
                FormNavigation.refreshRecaptcha('reset');
            });
        },

        // Handle registration OTP verification
        handleRegisterOTPVerification: function(e) {
            e.preventDefault();
            if (state.isSubmitting) return;

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const email = form.find('input[name="email"]').val();
            const otp = form.find('input[name="otp"]').val();

            if (!email || !otp) {
                FormUtils.showMessage(form, 'error', 'Please enter the verification code.');
                return;
            }

            // Get registration data from sessionStorage
            const registrationDataStr = sessionStorage.getItem('pending_registration');
            if (!registrationDataStr) {
                FormUtils.showMessage(form, 'error', 'Registration data not found. Please try again.');
                FormNavigation.showForm('register');
                return;
            }

            const registrationData = JSON.parse(registrationDataStr);

            // Update state and UI
            state.isSubmitting = true;
            FormUtils.setLoading(submitBtn, true);
            FormUtils.clearMessages(form);

            // Complete registration with OTP verification
            console.log('🔍 Available nonce fields in form:', form.find('input[name*="nonce"]').map(function() {
                return this.name + ': ' + this.value.substring(0, 10) + '...';
            }).get());
            
            let nonceValue = form.find('input[name="register_otp_verify_nonce"]').val();
            
            // Fallback: try to get nonce from other sources if primary is not found
            if (!nonceValue) {
                nonceValue = form.find('input[name="auth_nonce"]').val() || 
                           $('input[name="register_otp_verify_nonce"]').val() ||
                           tkAuth.nonces?.register || '';
                console.log('🔄 Primary nonce not found, using fallback');
            }
            
            console.log('🔒 Using register nonce for OTP verification:', nonceValue ? nonceValue.substring(0, 10) + '...' : 'NOT FOUND');
            
            if (!nonceValue) {
                console.error('❌ No valid nonce found for registration!');
                FormUtils.showMessage(form, 'error', 'Security token not found. Please refresh the page and try again.');
                FormUtils.setLoading(submitBtn, false);
                state.isSubmitting = false;
                return;
            }
            
            const data = {
                action: 'indoor_tasks_register',
                ...registrationData,
                otp: otp,
                verify_otp: true,
                register_otp_verify_nonce: nonceValue // Use the correct field name
            };
            
            console.log('Registration OTP Verification Data:', data);
            console.log('📋 Detailed registration data:');
            console.log('- Email:', data.email);
            console.log('- Name:', data.name);
            console.log('- Phone:', data.phone_number);
            console.log('- Country:', data.country);
            console.log('- OTP:', data.otp);
            console.log('- Referral Code:', data.referral_code || 'none');
            console.log('- Nonce field:', 'register_otp_verify_nonce');
            console.log('- Nonce value:', data.register_otp_verify_nonce ? data.register_otp_verify_nonce.substring(0, 10) + '...' : 'MISSING');

            $.post(tkAuth.ajaxurl, data, function(response) {
                console.log('Registration OTP Verification Response:', response);
                if (response.success) {
                    // Clear stored registration data
                    sessionStorage.removeItem('pending_registration');
                    
                    FormUtils.showMessage(form, 'success', response.data.message || 'Registration successful!');
                    
                    // Clear referral code from storage after successful registration
                    ReferralSystem.clearReferralCode();
                    
                    setTimeout(() => {
                        window.location.href = response.data.redirect || tkAuth.home_url;
                    }, 1500);
                } else {
                    console.error('❌ Registration failed:', response);
                    console.error('- Error message:', response.data?.message || 'Unknown error');
                    console.error('- HTTP status:', response.status || 'Unknown');
                    FormUtils.showMessage(form, 'error', response.data?.message || 'Invalid verification code.');
                }
            }).fail(function(xhr, status, error) {
                console.error('❌ Registration AJAX failed:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                let errorMessage = 'Connection error. Please try again.';
                if (xhr.status === 400) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        errorMessage = errorData.data?.message || 'Invalid request data.';
                    } catch (e) {
                        errorMessage = 'Invalid request. Please check your input.';
                    }
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please refresh the page and try again.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please try again later.';
                }
                
                FormUtils.showMessage(form, 'error', errorMessage);
            }).always(function() {
                FormUtils.setLoading(submitBtn, false);
                state.isSubmitting = false;
            });
        }
    };

    /**
     * Google Sign-In Integration
     */
    const GoogleAuth = {
        init: function() {
            console.log('🔍 GoogleAuth.init() called');
            console.log('📋 tkAuth object:', tkAuth);
            console.log('🔑 Google enabled:', tkAuth.google_enabled);
            console.log('🆔 Google client ID:', tkAuth.google_client_id ? tkAuth.google_client_id.substring(0, 20) + '...' : 'NOT SET');
            console.log('🌐 Current origin:', window.location.origin);
            
            // Show buttons initially (will be hidden later if needed)
            $('#google-login-btn, #google-register-btn').show();
            
            if (!tkAuth.google_enabled || !tkAuth.google_client_id) {
                console.log('❌ Google Sign-In disabled: missing client ID or not enabled');
                $('#google-login-btn, #google-register-btn').hide();
                return;
            }

            console.log('✅ Google Sign-In conditions met, initializing...');

            // Wait for Google API to load
            const initializeGoogle = () => {
                if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
                    try {
                        console.log('🚀 Initializing Google Identity Services...');
                        
                        google.accounts.id.initialize({
                            client_id: tkAuth.google_client_id,
                            callback: this.handleCredentialResponse.bind(this),
                            auto_select: false,
                            cancel_on_tap_outside: true,
                            ux_mode: 'popup',
                            use_fedcm_for_prompt: false
                        });

                        // Setup Google Sign-In buttons
                        $('#google-login-btn').off('click').on('click', (e) => {
                            e.preventDefault();
                            console.log('🔐 Google login button clicked');
                            state.currentForm = 'login';
                            this.showGooglePrompt();
                        });

                        $('#google-register-btn').off('click').on('click', (e) => {
                            e.preventDefault();
                            console.log('📝 Google register button clicked');
                            state.currentForm = 'register';
                            this.showGooglePrompt();
                        });
                        
                        console.log('✅ Google Sign-In initialized successfully');
                        
                        // Test the configuration
                        this.testGoogleConfiguration();
                        
                    } catch (error) {
                        console.error('❌ Google Sign-In initialization error:', error);
                        this.handleGoogleError(error);
                    }
                } else {
                    console.warn('⏳ Google API not loaded yet, retrying in 1 second...');
                    setTimeout(initializeGoogle, 1000);
                }
            };

            initializeGoogle();
        },

        testGoogleConfiguration: function() {
            console.log('🧪 Testing Google configuration...');
            
            // Check if current domain is allowed
            const currentDomain = window.location.hostname;
            const currentOrigin = window.location.origin;
            
            console.log('🏠 Current domain:', currentDomain);
            console.log('🌐 Current origin:', currentOrigin);
            
            if (currentDomain === 'localhost' || currentDomain.includes('127.0.0.1')) {
                console.warn('⚠️ Running on localhost - Google Sign-In may not work unless localhost is configured in Google Cloud Console');
            }
        },

        showGooglePrompt: function() {
            try {
                console.log('📲 Showing Google Sign-In prompt...');
                google.accounts.id.prompt((notification) => {
                    console.log('📋 Google prompt notification:', notification);
                    
                    if (notification.isNotDisplayed()) {
                        console.warn('⚠️ Google prompt not displayed:', notification.getNotDisplayedReason());
                        this.handlePromptNotDisplayed(notification.getNotDisplayedReason());
                    } else if (notification.isSkippedMoment()) {
                        console.log('⏭️ Google prompt was skipped:', notification.getSkippedReason());
                    } else if (notification.isDismissedMoment()) {
                        console.log('❌ Google prompt was dismissed:', notification.getDismissedReason());
                    }
                });
            } catch (error) {
                console.error('❌ Error showing Google prompt:', error);
                this.handleGoogleError(error);
            }
        },

        handlePromptNotDisplayed: function(reason) {
            console.error('❌ Google Sign-In prompt not displayed. Reason:', reason);
            
            let userMessage = 'Google Sign-In is not available. ';
            
            switch (reason) {
                case 'invalid_client':
                    userMessage += 'The Google Client ID is invalid. Please contact the administrator.';
                    console.error('🔧 ADMIN ACTION REQUIRED: Check Google Client ID in settings');
                    break;
                    
                case 'unknown_reason':
                    userMessage += 'This may be due to domain configuration issues. Please contact the administrator.';
                    console.error('🔧 ADMIN ACTION REQUIRED: Check if current domain (' + window.location.origin + ') is added to Google Cloud Console as an authorized origin');
                    break;
                    
                default:
                    userMessage += 'Please try again or contact the administrator.';
                    console.error('🔧 Unknown Google Sign-In error. Check Google Cloud Console configuration');
            }
            
            const form = state.currentForm === 'login' ? $('#tk-login-form') : $('#tk-register-form');
            FormUtils.showMessage(form, 'error', userMessage);
            
            // Show debug info for developers
            console.group('🔧 Google Sign-In Debug Information');
            console.log('Domain:', window.location.hostname);
            console.log('Origin:', window.location.origin);
            console.log('Client ID (partial):', tkAuth.google_client_id ? tkAuth.google_client_id.substring(0, 20) + '...' : 'NOT SET');
            console.log('Required action: Add this origin to Google Cloud Console > APIs & Services > Credentials > OAuth 2.0 Client IDs');
            console.groupEnd();
        },

        handleGoogleError: function(error) {
            console.error('❌ Google Sign-In error:', error);
            console.log('🔧 Hiding Google buttons due to error');
            
            $('#google-login-btn, #google-register-btn').hide();
            
            // Show user-friendly error message
            const errorMessage = error.message && error.message.includes('origin') 
                ? 'Google Sign-In is not configured for this website. Please contact the administrator.'
                : 'Google Sign-In is temporarily unavailable. Please try manual registration.';
                
            $('.tk-auth-container').prepend(`
                <div class="tk-form-message error" style="margin-bottom: 20px;">
                    <strong>Google Sign-In Error:</strong> ${errorMessage}
                </div>
            `);
            
            // Developer debugging
            if (window.location.hostname === 'localhost' || window.location.hostname.includes('127.0.0.1')) {
                console.warn('💡 TIP: For localhost development, add http://localhost:YOUR_PORT to Google Cloud Console authorized origins');
            }
        },

        handleCredentialResponse: function(response) {
            console.log('🎯 Google credential response received');
            
            if (!response.credential) {
                console.error('❌ No credential in Google response');
                return;
            }

            console.log('✅ Google credential received, processing...');
            
            const submitBtn = state.currentForm === 'login' 
                ? $('#tk-login-form button[type="submit"]')
                : $('#tk-register-form button[type="submit"]');

            FormUtils.setLoading(submitBtn, true);

            // Get reCAPTCHA token if enabled
            const getToken = tkAuth.recaptcha_enabled
                ? this.getRecaptchaToken(state.currentForm)
                : Promise.resolve(null);

            getToken.then(recaptchaToken => {
                console.log('📤 Sending Google auth request to server...');
                
                // Get nonce for Google auth
                const currentForm = state.currentForm === 'login' ? $('#tk-login-form') : $('#tk-register-form');
                const googleNonce = currentForm.find('input[name="auth_nonce"]').val() || 
                                   $('input[name="auth_nonce"]').val() ||
                                   tkAuth.nonces?.auth || '';
                
                console.log('🔒 Using nonce for Google auth:', googleNonce ? googleNonce.substring(0, 10) + '...' : 'NOT FOUND');
                
                // Get referral code for Google Sign-In (especially for new user registration)
                const referralCode = ReferralSystem.getCurrentReferralCode();
                console.log('🎯 Referral code for Google auth:', referralCode || 'none');
                
                $.ajax({
                    url: tkAuth.ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'indoor_tasks_google_auth',
                        credential: response.credential,
                        form_type: state.currentForm,
                        recaptcha_token: recaptchaToken,
                        referral_code: referralCode, // Include referral code
                        nonce: googleNonce
                    },
                    success: function(response) {
                        console.log('📥 Server response:', response);
                        
                        if (response.success) {
                            console.log('✅ Google authentication successful');
                            FormUtils.showMessage(
                                state.currentForm === 'login' ? $('#tk-login-form') : $('#tk-register-form'),
                                'success',
                                response.data.message || 'Authentication successful!'
                            );
                            
                            // Clear referral code after successful Google registration
                            if (state.currentForm === 'register') {
                                ReferralSystem.clearReferralCode();
                            }
                            
                            setTimeout(() => {
                                window.location.href = response.data.redirect || tkAuth.home_url;
                            }, 1500);
                        } else {
                            console.error('❌ Google authentication failed:', response.data.message);
                            FormUtils.showMessage(
                                state.currentForm === 'login' ? $('#tk-login-form') : $('#tk-register-form'),
                                'error',
                                response.data.message || 'Authentication failed'
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ AJAX error during Google auth:', {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            responseText: xhr.responseText,
                            error: error
                        });
                        
                        let errorMessage = 'Connection error. Please try again.';
                        if (xhr.status === 403) {
                            errorMessage = 'Access denied. Please refresh the page and try again.';
                        } else if (xhr.status === 500) {
                            errorMessage = 'Server error. Please try again later.';
                        }
                        
                        FormUtils.showMessage(
                            state.currentForm === 'login' ? $('#tk-login-form') : $('#tk-register-form'),
                            'error',
                            errorMessage
                        );
                    },
                    complete: function() {
                        FormUtils.setLoading(submitBtn, false);
                    }
                });
            }).catch(error => {
                console.error('❌ reCAPTCHA token error:', error);
                FormUtils.showMessage(
                    state.currentForm === 'login' ? $('#tk-login-form') : $('#tk-register-form'),
                    'error',
                    'Security verification failed. Please try again.'
                );
                FormUtils.setLoading(submitBtn, false);
            });
        },

        getRecaptchaToken: function(action) {
            if (!tkAuth.recaptcha_enabled) return Promise.resolve(null);
            return new Promise((resolve, reject) => {
                grecaptcha.ready(function() {
                    grecaptcha.execute(tkAuth.recaptcha_site_key, { action: action })
                        .then(resolve)
                        .catch(reject);
                });
            });
        }
    };

    /**
     * OTP Authentication
     */
    const OTPAuth = {
        // Send OTP to email
        sendOTP: function(email, recaptchaToken) {
            return $.post(tkAuth.ajaxurl, {
                action: 'indoor_tasks_auth',
                step: 'send_otp',
                email: email,
                recaptcha: recaptchaToken,
                nonce: $('input[name="otp_nonce"]').val()
            });
        },

        // Verify OTP and handle login/registration
        verifyOTP: function(email, otp, userData = {}) {
            const data = {
                action: 'indoor_tasks_auth',
                step: 'verify_otp',
                email: email,
                otp: otp,
                nonce: $('input[name="otp_verify_nonce"]').val()
            };

            // Add registration data if provided
            if (userData.name) {
                data.name = userData.name;
                data.country = userData.country;
                data.phone_number = userData.phone_number;
                data.referral_code = userData.referral_code;
            }

            return $.post(tkAuth.ajaxurl, data);
        },

        // Handle OTP request form submission
        handleOTPRequest: function(e) {
            e.preventDefault();
            if (state.isSubmitting) return;

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const email = form.find('input[name="email"]').val();

            if (!email) {
                FormUtils.showMessage(form, 'error', 'Please enter your email address.');
                return;
            }

            // Update state and UI
            state.isSubmitting = true;
            FormUtils.setLoading(submitBtn, true);
            FormUtils.clearMessages(form);

            // Get reCAPTCHA token if enabled
            let recaptchaToken = '';
            if (tkAuth.recaptcha_enabled) {
                recaptchaToken = form.find('input[name="recaptcha_token"]').val();
            }

            // Send OTP
            OTPAuth.sendOTP(email, recaptchaToken)
                .done(function(response) {
                    if (response.success) {
                        // Store email and show verification form
                        $('#otp-verify-email').val(email);
                        FormNavigation.showForm('otp-verify');
                        FormUtils.showMessage($('#tk-otp-verify-card form'), 'success', response.data.message);
                    } else {
                        FormUtils.showMessage(form, 'error', response.data.message || 'Failed to send OTP.');
                    }
                })
                .fail(function() {
                    FormUtils.showMessage(form, 'error', 'Connection error. Please try again.');
                })
                .always(function() {
                    FormUtils.setLoading(submitBtn, false);
                    state.isSubmitting = false;
                    if (tkAuth.recaptcha_enabled) {
                        FormNavigation.refreshRecaptcha('otp');
                    }
                });
        },

        // Handle OTP verification
        handleOTPVerification: function(e) {
            e.preventDefault();
            if (state.isSubmitting) return;

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const email = form.find('input[name="email"]').val();
            const otp = form.find('input[name="otp"]').val();

            if (!email || !otp) {
                FormUtils.showMessage(form, 'error', 'Please enter the verification code.');
                return;
            }

            // Update state and UI
            state.isSubmitting = true;
            FormUtils.setLoading(submitBtn, true);
            FormUtils.clearMessages(form);

            // Verify OTP
            OTPAuth.verifyOTP(email, otp)
                .done(function(response) {
                    if (response.success) {
                        if (response.data.redirect) {
                            // User exists, redirect to dashboard
                            FormUtils.showMessage(form, 'success', response.data.message);
                            setTimeout(() => {
                                window.location.href = response.data.redirect;
                            }, 1500);
                        } else {
                            // New user, show registration form
                            $('#otp-register-email').val(email);
                            $('#otp-register-code').val(otp);
                            FormNavigation.showForm('otp-register');
                        }
                    } else {
                        FormUtils.showMessage(form, 'error', response.data.message || 'Invalid verification code.');
                    }
                })
                .fail(function() {
                    FormUtils.showMessage(form, 'error', 'Connection error. Please try again.');
                })
                .always(function() {
                    FormUtils.setLoading(submitBtn, false);
                    state.isSubmitting = false;
                });
        },

        // Handle OTP registration
        handleOTPRegistration: function(e) {
            e.preventDefault();
            if (state.isSubmitting) return;

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const email = form.find('input[name="email"]').val();
            const otp = form.find('input[name="otp"]').val();
            const name = form.find('input[name="name"]').val();
            const country = form.find('select[name="country"]').val();
            const phone_number = form.find('input[name="phone_number"]').val();

            if (!email || !otp || !name || !country || !phone_number) {
                FormUtils.showMessage(form, 'error', 'Please fill in all required fields.');
                return;
            }

            // Update state and UI
            state.isSubmitting = true;
            FormUtils.setLoading(submitBtn, true);
            FormUtils.clearMessages(form);

            // Get additional user data
            const userData = {
                name: name,
                country: country,
                phone_number: phone_number,
                referral_code: form.find('input[name="referral_code"]').val()
            };

            // Complete registration
            OTPAuth.verifyOTP(email, otp, userData)
                .done(function(response) {
                    if (response.success) {
                        FormUtils.showMessage(form, 'success', response.data.message || 'Registration successful!');
                        
                        // Clear referral code from storage after successful registration
                        ReferralSystem.clearReferralCode();
                        
                        setTimeout(() => {
                            window.location.href = response.data.redirect || tkAuth.home_url;
                        }, 1500);
                    } else {
                        FormUtils.showMessage(form, 'error', response.data.message || 'Registration failed.');
                    }
                })
                .fail(function() {
                    FormUtils.showMessage(form, 'error', 'Connection error. Please try again.');
                })
                .always(function() {
                    FormUtils.setLoading(submitBtn, false);
                    state.isSubmitting = false;
                });
        },

        // Resend OTP
        resendOTP: function() {
            const email = $('#otp-verify-email').val();
            if (!email) return;

            const btn = $('#tk-resend-otp');
            btn.prop('disabled', true).text('Sending...');

            // Get reCAPTCHA token if enabled
            let recaptchaToken = '';
            if (tkAuth.recaptcha_enabled) {
                recaptchaToken = $('#recaptcha_token_otp').val();
            }

            OTPAuth.sendOTP(email, recaptchaToken)
                .done(function(response) {
                    if (response.success) {
                        FormUtils.showMessage($('#tk-otp-verify-card form'), 'success', 'New code sent to your email.');
                    } else {
                        FormUtils.showMessage($('#tk-otp-verify-card form'), 'error', response.data.message || 'Failed to resend code.');
                    }
                })
                .fail(function() {
                    FormUtils.showMessage($('#tk-otp-verify-card form'), 'error', 'Connection error. Please try again.');
                })
                .always(function() {
                    btn.prop('disabled', false).text('Resend Code');
                    if (tkAuth.recaptcha_enabled) {
                        FormNavigation.refreshRecaptcha('otp');
                    }
                });
        }
    };

    /**
     * Referral System - Enhanced with cookies
     */
    const ReferralSystem = {
        // Cookie management
        setCookie: function(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
        },

        getCookie: function(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for(let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        },

        deleteCookie: function(name) {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        },

        // Auto-populate referral code from URL and cookies
        initReferralCode: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const referralCode = urlParams.get('ref') || urlParams.get('referral') || urlParams.get('referral_code');
            
            if (referralCode) {
                // Store in both localStorage and cookies for maximum persistence
                localStorage.setItem('indoor_tasks_referral_code', referralCode);
                this.setCookie('indoor_tasks_referral_code', referralCode, 30); // 30 days
                
                // Populate all referral fields
                this.populateReferralFields(referralCode);
                
                // Show success message
                console.log('Referral code detected:', referralCode);
                this.showReferralMessage('Referral code applied! You\'re signing up through a referral link.');
                
                // Clean URL to remove referral parameters (optional)
                if (window.history && window.history.replaceState) {
                    const cleanUrl = window.location.pathname + window.location.hash;
                    window.history.replaceState({}, document.title, cleanUrl);
                }
            } else {
                // Check localStorage first, then cookies
                const storedCode = localStorage.getItem('indoor_tasks_referral_code') || 
                                   this.getCookie('indoor_tasks_referral_code');
                
                if (storedCode) {
                    this.populateReferralFields(storedCode);
                    this.showReferralMessage('Referral code restored from previous visit.');
                }
            }
        },

        // Populate referral code fields
        populateReferralFields: function(code) {
            const referralFields = [
                '#register-referral',
                '#otp-register-referral'
            ];
            
            referralFields.forEach(fieldId => {
                const field = $(fieldId);
                if (field.length && !field.val()) {
                    field.val(code);
                    field.closest('.tk-form-group').addClass('tk-referral-applied');
                    
                    // Make field readonly to prevent accidental changes
                    field.attr('readonly', true);
                    field.attr('title', 'Referral code automatically applied');
                }
            });
        },

        // Show referral message
        showReferralMessage: function(message) {
            // Create or update referral message
            let msgElement = $('.tk-referral-message');
            if (msgElement.length === 0) {
                msgElement = $('<div class="tk-referral-message"></div>');
                $('.tk-auth-inner').prepend(msgElement);
            }
            
            msgElement.html(`
                <div class="tk-referral-notification">
                    <span class="tk-referral-icon">🎉</span>
                    <span class="tk-referral-text">${message}</span>
                    <button type="button" class="tk-referral-close" onclick="$(this).closest('.tk-referral-message').hide()">×</button>
                </div>
            `).show();
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                msgElement.fadeOut();
            }, 10000);
        },

        // Clear stored referral code after successful registration
        clearReferralCode: function() {
            localStorage.removeItem('indoor_tasks_referral_code');
            this.deleteCookie('indoor_tasks_referral_code');
            $('.tk-referral-applied').removeClass('tk-referral-applied');
            $('.tk-referral-message').hide();
        },

        // Get current referral code
        getCurrentReferralCode: function() {
            return localStorage.getItem('indoor_tasks_referral_code') || 
                   this.getCookie('indoor_tasks_referral_code') || 
                   $('#register-referral').val() || 
                   $('#otp-register-referral').val() || 
                   '';
        },

        // Validate referral code format (optional)
        isValidReferralCode: function(code) {
            // Add your validation logic here
            return code && code.length >= 4 && code.length <= 20;
        }
    };

    /**
     * Debug utilities (for development only)
     */
    const DebugUtils = {
        clearRateLimits: function() {
            if (typeof console !== 'undefined') {
                console.log('Clearing rate limits...');
            }
            
            $.post(tkAuth.ajaxurl, {
                action: 'indoor_tasks_clear_rate_limits',
                email: $('#login-username').val() || $('#register-email').val() || 'test@example.com'
            }, function(response) {
                if (typeof console !== 'undefined') {
                    console.log('Rate limits cleared:', response);
                }
            });
        },
        
        testAjax: function() {
            console.log('Testing AJAX connection...');
            $.post(tkAuth.ajaxurl, {
                action: 'indoor_tasks_debug'
            }, function(response) {
                console.log('AJAX test successful:', response);
            }).fail(function(xhr, status, error) {
                console.error('AJAX test failed:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
            });
        }
    };
    
    // Make debug utils globally available
    window.IndoorTasksDebug = DebugUtils;

    /**
     * Event Bindings
     */
    // Form navigation
    $('#tk-show-register').on('click', function(e) {
        e.preventDefault();
        FormNavigation.showForm('register');
    });

    $('#tk-show-forgot').on('click', function(e) {
        e.preventDefault();
        FormNavigation.showForm('reset');
    });

    $('#tk-back-to-login, #tk-back-to-login-reset').on('click', function(e) {
        e.preventDefault();
        FormNavigation.showForm('login');
    });

    // Password visibility toggle
    $('.tk-password-toggle').on('click', function() {
        const input = $(this).closest('.tk-password-field').find('input');
        const type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).toggleClass('visible');
    });

    // Password strength checker
    $('#register-password').on('input', function() {
        FormUtils.updatePasswordStrength($(this).val());
    });

    // Real-time email domain validation
    $('#register-email').on('input blur', function() {
        FormUtils.checkEmailDomainRealTime($(this));
    });

    $('#otp-email').on('input blur', function() {
        FormUtils.checkEmailDomainRealTime($(this));
    });

    $('#reset-email').on('input blur', function() {
        FormUtils.checkEmailDomainRealTime($(this));
    });

    // Form submissions
    $('#tk-login-form').on('submit', FormHandler.handleLogin);
    $('#tk-register-form').on('submit', FormHandler.handleRegister);
    $('#tk-reset-form').on('submit', FormHandler.handleReset);
    $('#tk-register-otp-verify-form').on('submit', FormHandler.handleRegisterOTPVerification);

    // Registration OTP navigation
    $('#tk-back-to-register').on('click', function(e) {
        e.preventDefault();
        // Clear stored registration data
        sessionStorage.removeItem('pending_registration');
        FormNavigation.showForm('register');
    });

    // Resend registration OTP
    $('#tk-resend-register-otp').on('click', function() {
        const email = $('#register-otp-email').val();
        if (!email) return;

        const btn = $(this);
        btn.prop('disabled', true).text('Sending...');

        // Get reCAPTCHA token if enabled
        let recaptchaToken = '';
        if (tkAuth.recaptcha_enabled) {
            recaptchaToken = $('#recaptcha_token_register').val();
        }

        const authNonce = $('#tk-register-otp-verify-form input[name="auth_nonce"]').val() ||
                          $('input[name="auth_nonce"]').val() ||
                          tkAuth.nonces?.auth || '';
        console.log('🔒 Using auth nonce for resend OTP:', authNonce ? authNonce.substring(0, 10) + '...' : 'NOT FOUND');
        
        if (!authNonce) {
            console.error('❌ Auth nonce not found for resend OTP!');
            FormUtils.showMessage($('#tk-register-otp-verify-card form'), 'error', 'Security token not found. Please refresh the page and try again.');
            btn.prop('disabled', false).text('Resend Code');
            return;
        }

        $.post(tkAuth.ajaxurl, {
            action: 'indoor_tasks_auth',
            step: 'send_otp',
            email: email,
            recaptcha: recaptchaToken,
            nonce: authNonce
        }).done(function(response) {
            if (response.success) {
                FormUtils.showMessage($('#tk-register-otp-verify-card form'), 'success', 'New code sent to your email.');
            } else {
                FormUtils.showMessage($('#tk-register-otp-verify-card form'), 'error', response.data.message || 'Failed to resend code.');
            }
        }).fail(function() {
            FormUtils.showMessage($('#tk-register-otp-verify-card form'), 'error', 'Connection error. Please try again.');
        }).always(function() {
            btn.prop('disabled', false).text('Resend Code');
            if (tkAuth.recaptcha_enabled) {
                FormNavigation.refreshRecaptcha('register');
            }
        });
    });

    // Auto-format registration OTP input (digits only, 6 chars max)
    $('#register-otp-code').on('input', function() {
        let value = this.value.replace(/[^0-9]/g, '');
        if (value.length > 6) {
            value = value.substr(0, 6);
        }
        this.value = value;
    });

    // Initialize
    $(document).ready(function() {
        // Initialize referral system
        ReferralSystem.initReferralCode();
        
        // Initialize Google Sign-In with delay to ensure API is loaded
        setTimeout(() => {
            GoogleAuth.init();
        }, 1000);

        // Setup reCAPTCHA token refresh
        if (tkAuth.recaptcha_enabled) {
            setInterval(function() {
                refreshRecaptcha(state.currentForm);
            }, 90000); // Refresh every 90 seconds
        }

        // Refresh initial reCAPTCHA token
        FormNavigation.refreshRecaptcha('login');

        // OTP form event listeners
        $('#tk-otp-request-form').on('submit', OTPAuth.handleOTPRequest);
        $('#tk-otp-verify-form').on('submit', OTPAuth.handleOTPVerification);
        $('#tk-otp-register-form').on('submit', OTPAuth.handleOTPRegistration);
        $('#tk-resend-otp').on('click', OTPAuth.resendOTP);

        // OTP navigation event listeners
        $('#tk-show-otp-login').on('click', function(e) {
            e.preventDefault();
            FormNavigation.showForm('otp-login');
            if (tkAuth.recaptcha_enabled) {
                FormNavigation.refreshRecaptcha('otp');
            }
        });

        $('#tk-back-to-login-otp').on('click', function(e) {
            e.preventDefault();
            FormNavigation.showForm('login');
        });

        $('#tk-back-to-otp-request').on('click', function(e) {
            e.preventDefault();
            FormNavigation.showForm('otp-login');
        });

        $('#tk-back-to-otp-verify').on('click', function(e) {
            e.preventDefault();
            FormNavigation.showForm('otp-verify');
        });

        // Auto-format OTP input (digits only, 6 chars max)
        $('#otp-code').on('input', function() {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value.length > 6) {
                value = value.substr(0, 6);
            }
            this.value = value;
        });

        // Initialize referral code population
        ReferralSystem.initReferralCode();
    });

    // Form error handler
    const handleFormError = function(form, error) {
        if (error.status === 400) {
            FormUtils.showMessage(form, 'error', 'Invalid request. Please check your input.');
        } else if (error.status === 403) {
            FormUtils.showMessage(form, 'error', 'Access denied. Please refresh the page and try again.');
        } else if (error.statusText === 'timeout') {
            FormUtils.showMessage(form, 'error', 'Request timed out. Please check your connection.');
        } else {
            FormUtils.showMessage(form, 'error', 'Connection error. Please try again.');
        }
        console.error('Form submission error:', error);
    };

    // Add timeout and error handling to AJAX requests
    $.ajaxSetup({
        timeout: 30000, // 30 second timeout
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    });

    // Initialize reCAPTCHA v3
    const initRecaptcha = function() {
        if (!tkAuth.recaptcha_enabled || state.recaptchaInitialized) return;
        
        try {
            grecaptcha.ready(function() {
                state.recaptchaInitialized = true;
                refreshRecaptcha(state.currentForm);
            });
        } catch (error) {
            console.error('reCAPTCHA initialization error:', error);
        }
    };

    // Refresh reCAPTCHA token
    const refreshRecaptcha = function(action) {
        if (!tkAuth.recaptcha_enabled || !state.recaptchaInitialized) return;

        try {
            grecaptcha.execute(tkAuth.recaptcha_site_key, { action: action })
                .then(function(token) {
                    $(`#recaptcha_token_${action}`).val(token);
                })
                .catch(function(error) {
                    console.error('reCAPTCHA token error:', error);
                });
        } catch (error) {
            console.error('reCAPTCHA refresh error:', error);
        }
    };

})(jQuery);