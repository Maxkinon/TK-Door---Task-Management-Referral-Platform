/**
 * TKM Door Help Desk JavaScript
 * Handles FAQ accordion functionality and interactions
 * Version: 1.0.0
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeHelpDesk();
    });

    function initializeHelpDesk() {
        initializeFaqAccordion();
        initializeSmoothScrolling();
        initializeExternalLinks();
        initializeAnimations();
    }

    // FAQ Accordion functionality
    function initializeFaqAccordion() {
        const faqQuestions = document.querySelectorAll('.tkm-faq-question');
        
        faqQuestions.forEach(question => {
            question.addEventListener('click', function() {
                const faqId = this.getAttribute('data-faq');
                const faqAnswer = document.getElementById(`faq-${faqId}`);
                const isActive = this.classList.contains('active');
                
                // Close all FAQ items
                closeAllFaqItems();
                
                // If this item wasn't active, open it
                if (!isActive) {
                    this.classList.add('active');
                    faqAnswer.classList.add('active');
                    
                    // Scroll to the opened FAQ for better UX on mobile
                    setTimeout(() => {
                        if (window.innerWidth <= 768) {
                            this.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    }, 300);
                }
            });
        });
    }

    // Close all FAQ items
    function closeAllFaqItems() {
        const activeQuestions = document.querySelectorAll('.tkm-faq-question.active');
        const activeAnswers = document.querySelectorAll('.tkm-faq-answer.active');
        
        activeQuestions.forEach(question => {
            question.classList.remove('active');
        });
        
        activeAnswers.forEach(answer => {
            answer.classList.remove('active');
        });
    }

    // Smooth scrolling for internal links
    function initializeSmoothScrolling() {
        const internalLinks = document.querySelectorAll('a[href^="#"]');
        
        internalLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // Handle external links
    function initializeExternalLinks() {
        const externalLinks = document.querySelectorAll('a[target="_blank"]');
        
        externalLinks.forEach(link => {
            // Add click tracking or analytics if needed
            link.addEventListener('click', function() {
                const linkText = this.textContent.trim();
                const linkUrl = this.href;
                
                // Track link clicks for analytics (optional)
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'click', {
                        'event_category': 'Help Desk',
                        'event_label': linkText,
                        'value': linkUrl
                    });
                }
                
                // Add visual feedback
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    }

    // Initialize animations and effects
    function initializeAnimations() {
        // Fade in elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('tkm-fade-in');
                }
            });
        }, observerOptions);

        // Observe support cards and FAQ items
        const animateElements = document.querySelectorAll('.tkm-support-card, .tkm-faq-item, .tkm-help-card');
        animateElements.forEach(element => {
            observer.observe(element);
        });
    }

    // Keyboard navigation for accessibility
    document.addEventListener('keydown', function(e) {
        // Handle FAQ navigation with arrow keys
        const activeElement = document.activeElement;
        
        if (activeElement && activeElement.classList.contains('tkm-faq-question')) {
            const faqQuestions = Array.from(document.querySelectorAll('.tkm-faq-question'));
            const currentIndex = faqQuestions.indexOf(activeElement);
            
            let nextIndex = -1;
            
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    nextIndex = currentIndex + 1;
                    if (nextIndex >= faqQuestions.length) nextIndex = 0;
                    break;
                    
                case 'ArrowUp':
                    e.preventDefault();
                    nextIndex = currentIndex - 1;
                    if (nextIndex < 0) nextIndex = faqQuestions.length - 1;
                    break;
                    
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    activeElement.click();
                    break;
            }
            
            if (nextIndex !== -1) {
                faqQuestions[nextIndex].focus();
            }
        }
    });

    // Handle search functionality (if search input is added later)
    function initializeSearch() {
        const searchInput = document.getElementById('help-search');
        if (!searchInput) return;
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const faqItems = document.querySelectorAll('.tkm-faq-item');
            
            faqItems.forEach(item => {
                const questionText = item.querySelector('.tkm-faq-text').textContent.toLowerCase();
                const answerText = item.querySelector('.tkm-faq-content').textContent.toLowerCase();
                
                if (questionText.includes(searchTerm) || answerText.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Mobile menu toggle (if needed for responsive navigation)
    function initializeMobileMenu() {
        const mobileToggle = document.querySelector('.tkm-mobile-toggle');
        const sidebar = document.querySelector('.tkm-sidebar');
        
        if (mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                this.classList.toggle('active');
            });
            
            // Close sidebar when clicking outside
            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    mobileToggle.classList.remove('active');
                }
            });
        }
    }

    // Utility function to show notifications
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `tkm-notification tkm-notification-${type}`;
        notification.textContent = message;
        
        // Style the notification
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#00954b' : type === 'error' ? '#e74c3c' : '#3498db'};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 10000;
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    // Handle contact form submissions (if contact form is added)
    function initializeContactForm() {
        const contactForm = document.getElementById('help-contact-form');
        if (!contactForm) return;
        
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Sending...';
            submitBtn.disabled = true;
            
            // Simulate form submission (replace with actual AJAX call)
            setTimeout(() => {
                showNotification('Your message has been sent successfully!', 'success');
                this.reset();
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }, 2000);
        });
    }

    // Initialize all features when page is fully loaded
    window.addEventListener('load', function() {
        initializeMobileMenu();
        initializeSearch();
        initializeContactForm();
    });

    // Export functions for external use if needed
    window.TkmHelpDesk = {
        showNotification: showNotification,
        closeAllFaqItems: closeAllFaqItems
    };

})();
