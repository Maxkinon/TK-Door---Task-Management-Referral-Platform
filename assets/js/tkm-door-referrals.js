/**
 * TKM Door Referrals - JavaScript functionality
 * Version: 1.0.0
 */

(function() {
    'use strict';
    
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initReferrals();
    });
    
    function initReferrals() {
        // Initialize invitation form
        initInvitationForm();
        
        // Initialize copy link functionality
        initCopyLink();
        
        // Initialize message auto-hide
        initMessageAutoHide();
    }
    
    // Handle invitation form submission
    function initInvitationForm() {
        const form = document.getElementById('invitation-form');
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const emails = document.getElementById('friend-emails').value.trim();
            if (!emails) {
                showMessage('Please enter at least one email address', 'error');
                return;
            }
            
            // Validate email format
            const emailList = emails.split(',').map(email => email.trim()).filter(email => email);
            const invalidEmails = emailList.filter(email => !isValidEmail(email));
            
            if (invalidEmails.length > 0) {
                showMessage(`Invalid email format: ${invalidEmails.join(', ')}`, 'error');
                return;
            }
            
            sendInvitations(emails);
        });
    }
    
    // Send invitations via AJAX
    function sendInvitations(emails) {
        showLoading(true);
        
        const formData = new FormData();
        formData.append('action', 'send_invitations');
        formData.append('emails', emails);
        formData.append('nonce', window.tkmReferrals.nonces.sendInvitations);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            
            if (data.success) {
                showMessage(data.message, 'success');
                document.getElementById('friend-emails').value = '';
                
                // Show errors if any
                if (data.errors && data.errors.length > 0) {
                    setTimeout(() => {
                        data.errors.forEach(error => {
                            showMessage(error, 'warning');
                        });
                    }, 1000);
                }
                
                // Optionally reload page to update statistics
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                showMessage(data.message || 'Failed to send invitations', 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Error:', error);
            showMessage('An error occurred while sending invitations', 'error');
        });
    }
    
    // Initialize copy link functionality
    function initCopyLink() {
        // Make copyReferralLink globally available
        window.copyReferralLink = function() {
            const linkInput = document.getElementById('referral-link');
            if (!linkInput) return;
            
            // Select and copy the text
            linkInput.select();
            linkInput.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand('copy');
                showMessage('Referral link copied to clipboard!', 'success');
                
                // Visual feedback
                const copyBtn = document.querySelector('.tkm-copy-btn');
                if (copyBtn) {
                    const originalText = copyBtn.innerHTML;
                    copyBtn.innerHTML = `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Copied!
                    `;
                    
                    setTimeout(() => {
                        copyBtn.innerHTML = originalText;
                    }, 2000);
                }
            } catch (err) {
                // Fallback for browsers that don't support execCommand
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(linkInput.value).then(() => {
                        showMessage('Referral link copied to clipboard!', 'success');
                    }).catch(() => {
                        showMessage('Failed to copy link. Please copy manually.', 'error');
                    });
                } else {
                    showMessage('Please copy the link manually', 'warning');
                }
            }
        };
    }
    
    // Show/hide loading overlay
    function showLoading(show) {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = show ? 'flex' : 'none';
        }
    }
    
    // Show message to user
    function showMessage(message, type = 'success') {
        const container = document.getElementById('message-container');
        if (!container) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `tkm-message ${type}`;
        messageDiv.textContent = message;
        
        // Add click to dismiss
        messageDiv.addEventListener('click', function() {
            messageDiv.remove();
        });
        
        container.appendChild(messageDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }
    
    // Initialize message auto-hide functionality
    function initMessageAutoHide() {
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('tkm-message')) {
                e.target.remove();
            }
        });
    }
    
    // Email validation helper
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Add animation classes when elements come into view
    function initScrollAnimations() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationDelay = Math.random() * 0.5 + 's';
                    entry.target.classList.add('tkm-animate-in');
                }
            });
        }, {
            threshold: 0.1
        });
        
        // Observe all sections
        document.querySelectorAll('.tkm-section').forEach(section => {
            observer.observe(section);
        });
        
        // Observe stat cards
        document.querySelectorAll('.tkm-stat-card').forEach(card => {
            observer.observe(card);
        });
        
        // Observe step cards
        document.querySelectorAll('.tkm-step-card').forEach(card => {
            observer.observe(card);
        });
    }
    
    // Initialize scroll animations if supported
    if ('IntersectionObserver' in window) {
        document.addEventListener('DOMContentLoaded', initScrollAnimations);
    }
    
    // Add hover effects for better UX
    function initHoverEffects() {
        // Add hover sound effect (optional)
        document.querySelectorAll('.tkm-step-card, .tkm-stat-card, .tkm-network-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    }
    
    // Initialize additional effects
    document.addEventListener('DOMContentLoaded', function() {
        initHoverEffects();
        
        // Add ripple effect to buttons
        document.querySelectorAll('.tkm-invite-btn, .tkm-copy-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255, 255, 255, 0.5);
                    border-radius: 50%;
                    pointer-events: none;
                    animation: ripple 0.6s ease-out;
                `;
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    });
    
    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            from {
                transform: scale(0);
                opacity: 1;
            }
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
        
        .tkm-animate-in {
            animation: tkm-fadeInUp 0.6s ease forwards;
        }
    `;
    document.head.appendChild(style);
    
})();
