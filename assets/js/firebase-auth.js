/**
 * Firebase Authentication for Indoor Tasks
 */
(function($) {
    'use strict';

    // Check if Firebase is available
    if (typeof firebase === 'undefined') {
        console.warn('Firebase SDK not loaded. Google login will not be available.');
        return;
    }

    // Check if Firebase config is available
    if (typeof indoor_tasks_firebase === 'undefined' || !indoor_tasks_firebase.apiKey) {
        console.warn('Firebase configuration not found. Google login will not be available.');
        return;
    }

    // Initialize Firebase
    firebase.initializeApp(indoor_tasks_firebase);
    
    // Get elements
    const googleLoginBtn = document.getElementById('google-login-btn');
    const googleRegisterBtn = document.getElementById('google-register-btn');
    
    // Use FormUtils from the main auth script or create fallback
    const formUtils = (typeof window.FormUtils !== 'undefined') ? window.FormUtils : {
        showMessage: function(form, type, message) {
            // Fallback message display
            const existingMsg = $(form).find('.tk-form-message');
            existingMsg.remove();
            
            const alertClass = type === 'success' ? 'success' : 'error';
            const messageHtml = `<div class="tk-form-message ${alertClass}">${message}</div>`;
            $(form).prepend(messageHtml);
        }
    };
    
    // Handle Google authentication for both login and register buttons
    function handleGoogleAuth(buttonElement) {
        // Show loading state
        const originalText = buttonElement.innerHTML;
        buttonElement.disabled = true;
        buttonElement.innerHTML = '<span class="spinner"></span> Connecting to Google...';
        
        const provider = new firebase.auth.GoogleAuthProvider();
        provider.addScope('profile');
        provider.addScope('email');
        
        firebase.auth().signInWithPopup(provider)
            .then((result) => {
                // The signed-in user info
                const user = result.user;
                
                // Get the Firebase ID token
                return user.getIdToken().then(idToken => {
                    return {
                        idToken: idToken,
                        user: user
                    };
                });
            })
            .then((authData) => {
                // Send the token to your server
                return $.ajax({
                    url: tkAuth.ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'indoor_tasks_firebase_auth',
                        id_token: authData.idToken,
                        nonce: tkAuth.nonces.firebase,
                        email: authData.user.email,
                        name: authData.user.displayName
                    }
                });
            })
            .then((response) => {
                if (response.success) {
                    // Show success message briefly
                    formUtils.showMessage(buttonElement.closest('form'), 'success', 'Login successful! Redirecting...');
                    
                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = response.data.redirect_url || tkAuth.home_url;
                    }, 1000);
                } else {
                    throw new Error(response.data.message || 'Authentication failed');
                }
            })
            .catch((error) => {
                console.error('Firebase Auth Error:', error);
                
                // Reset button state
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalText;
                
                // Show error message
                let errorMessage = 'Authentication failed. Please try again.';
                
                if (error.code === 'auth/popup-blocked') {
                    errorMessage = 'Popup was blocked. Please allow popups for this site and try again.';
                } else if (error.code === 'auth/cancelled-popup-request') {
                    errorMessage = 'Login cancelled. Please try again.';
                } else if (error.code === 'auth/popup-closed-by-user') {
                    errorMessage = 'Login window was closed. Please try again.';
                } else if (error.code === 'auth/network-request-failed') {
                    errorMessage = 'Network error. Please check your connection and try again.';
                }
                
                formUtils.showMessage(buttonElement.closest('form'), 'error', errorMessage);
            });
    }
    
    // Attach event listeners to both buttons
    if (googleLoginBtn) {
        googleLoginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleGoogleAuth(this);
        });
    }
    
    if (googleRegisterBtn) {
        googleRegisterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleGoogleAuth(this);
        });
    }
})(jQuery);
