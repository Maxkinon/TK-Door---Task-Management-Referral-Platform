/**
 * TKM Door Announcements JavaScript
 * Handles announcements interactions and animations
 * Version: 1.0.0
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeAnnouncements();
    });

    // Initialize announcements features
    function initializeAnnouncements() {
        initializeAnimations();
        initializeReadMore();
        initializePagination();
        initializeFilters();
        initializeSearch();
        initializeToggle(); // Add toggle initialization
        initializeToggleButtons(); // Add direct button event listeners
    }

    // Initialize scroll animations
    function initializeAnimations() {
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

        // Observe announcement cards
        const cards = document.querySelectorAll('.tkm-announcement-card');
        cards.forEach(card => {
            observer.observe(card);
        });
    }

    // Initialize read more functionality for long announcements
    function initializeReadMore() {
        const descriptions = document.querySelectorAll('.tkm-announcement-description');
        
        descriptions.forEach(description => {
            // Set initial collapsed state for long content
            const content = description.innerHTML;
            const words = content.split(' ');
            
            if (words.length > 30) { // More than 30 words, enable collapse
                description.classList.add('collapsible');
                description.style.maxHeight = '100px';
                description.style.overflow = 'hidden';
            }
        });
    }

    // Initialize pagination functionality
    function initializePagination() {
        const paginationLinks = document.querySelectorAll('.tkm-pagination-number, .tkm-pagination-btn');
        
        paginationLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Add loading state
                const container = document.querySelector('.tkm-announcements-grid');
                if (container) {
                    container.classList.add('tkm-loading');
                }
                
                // Show loading spinner
                showLoadingSpinner();
            });
        });
    }

    // Initialize announcement filters (if needed in future)
    function initializeFilters() {
        const filterButtons = document.querySelectorAll('.tkm-filter-btn');
        
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Filter announcements
                filterAnnouncements(filter);
            });
        });
    }

    // Filter announcements by type/priority
    function filterAnnouncements(filter) {
        const announcements = document.querySelectorAll('.tkm-announcement-card');
        
        announcements.forEach(announcement => {
            if (filter === 'all') {
                announcement.style.display = 'block';
            } else {
                const priority = announcement.querySelector('.tkm-priority-badge');
                if (priority && priority.classList.contains('tkm-priority-' + filter)) {
                    announcement.style.display = 'block';
                } else {
                    announcement.style.display = 'none';
                }
            }
        });
        
        // Animate filtered results
        setTimeout(() => {
            const visibleCards = document.querySelectorAll('.tkm-announcement-card[style*="block"]');
            visibleCards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('tkm-fade-in');
                }, index * 100);
            });
        }, 100);
    }

    // Initialize search functionality
    function initializeSearch() {
        const searchInput = document.getElementById('announcement-search');
        if (!searchInput) return;
        
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchTerm = this.value.toLowerCase();
                searchAnnouncements(searchTerm);
            }, 300);
        });
    }

    // Search announcements
    function searchAnnouncements(searchTerm) {
        const announcements = document.querySelectorAll('.tkm-announcement-card');
        let visibleCount = 0;
        
        announcements.forEach(announcement => {
            const title = announcement.querySelector('.tkm-announcement-title').textContent.toLowerCase();
            const description = announcement.querySelector('.tkm-announcement-description').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                announcement.style.display = 'block';
                visibleCount++;
            } else {
                announcement.style.display = 'none';
            }
        });
        
        // Show no results message if needed
        showNoResultsMessage(visibleCount === 0 && searchTerm !== '');
    }

    // Show no results message
    function showNoResultsMessage(show) {
        let noResultsMsg = document.querySelector('.tkm-no-results');
        
        if (show && !noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.className = 'tkm-no-results tkm-empty-state';
            noResultsMsg.innerHTML = `
                <div class="tkm-empty-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="tkm-empty-title">No Announcements Found</h3>
                <p class="tkm-empty-description">
                    Try adjusting your search terms or browse all announcements.
                </p>
            `;
            
            const grid = document.querySelector('.tkm-announcements-grid');
            if (grid) {
                grid.parentNode.insertBefore(noResultsMsg, grid.nextSibling);
            }
        } else if (!show && noResultsMsg) {
            noResultsMsg.remove();
        }
    }

    // Show loading spinner
    function showLoadingSpinner() {
        const spinner = document.createElement('div');
        spinner.className = 'tkm-loading-spinner';
        spinner.innerHTML = `
            <div class="tkm-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading announcements...</span>
            </div>
        `;
        
        document.body.appendChild(spinner);
        
        // Remove spinner after a short delay (simulating loading)
        setTimeout(() => {
            if (spinner.parentNode) {
                spinner.parentNode.removeChild(spinner);
            }
        }, 1000);
    }

    // Initialize toggle functionality
    function initializeToggle() {
        // Make toggleAnnouncementDetails available globally
        window.toggleAnnouncementDetails = function(button) {
            console.log('toggleAnnouncementDetails called', button);
            
            const card = button.closest('.tkm-announcement-card');
            const description = card.querySelector('.tkm-announcement-description');
            const icon = button.querySelector('i');
            const text = button.querySelector('span');
            
            console.log('Elements found:', { card, description, icon, text });
            
            if (!card || !description || !icon || !text) {
                console.error('Missing required elements for toggle functionality');
                return;
            }
            
            if (description.classList.contains('expanded')) {
                // Collapse
                description.classList.remove('expanded');
                description.style.maxHeight = '100px';
                description.style.overflow = 'hidden';
                icon.className = 'fas fa-eye';
                text.textContent = 'View Details';
                console.log('Collapsed');
            } else {
                // Expand
                description.classList.add('expanded');
                description.style.maxHeight = 'none';
                description.style.overflow = 'visible';
                icon.className = 'fas fa-eye-slash';
                text.textContent = 'Hide Details';
                console.log('Expanded');
            }
        };
        
        console.log('toggleAnnouncementDetails function initialized');
    }

    // Initialize toggle buttons with direct event listeners
    function initializeToggleButtons() {
        const toggleButtons = document.querySelectorAll('.tkm-toggle-details');
        console.log('Found toggle buttons:', toggleButtons.length);
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Button clicked via event listener');
                
                const card = this.closest('.tkm-announcement-card');
                const description = card.querySelector('.tkm-announcement-description');
                const icon = this.querySelector('i');
                const text = this.querySelector('span');
                
                if (!card || !description || !icon || !text) {
                    console.error('Missing required elements');
                    return;
                }
                
                if (description.classList.contains('expanded')) {
                    // Collapse
                    description.classList.remove('expanded');
                    description.style.maxHeight = '100px';
                    description.style.overflow = 'hidden';
                    icon.className = 'fas fa-eye';
                    text.textContent = 'View Details';
                } else {
                    // Expand
                    description.classList.add('expanded');
                    description.style.maxHeight = 'none';
                    description.style.overflow = 'visible';
                    icon.className = 'fas fa-eye-slash';
                    text.textContent = 'Hide Details';
                }
            });
        });
    }

    // Handle external links
    function initializeExternalLinks() {
        const externalLinks = document.querySelectorAll('.tkm-announcement-link[target="_blank"]');
        
        externalLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Track external link clicks for analytics (optional)
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'click', {
                        'event_category': 'Announcements',
                        'event_label': this.textContent.trim(),
                        'value': this.href
                    });
                }
            });
        });
    }

    // Auto-refresh announcements (optional)
    function initializeAutoRefresh() {
        // Only refresh if user is active and on the page
        let refreshInterval;
        let isActive = true;
        
        document.addEventListener('visibilitychange', function() {
            isActive = !document.hidden;
            
            if (isActive) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
        
        function startAutoRefresh() {
            // Refresh every 5 minutes
            refreshInterval = setInterval(() => {
                if (isActive) {
                    checkForNewAnnouncements();
                }
            }, 5 * 60 * 1000);
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        }
        
        // Start auto-refresh
        startAutoRefresh();
    }

    // Check for new announcements
    function checkForNewAnnouncements() {
        // This would typically make an AJAX call to check for new announcements
        // For now, we'll just show a notification if there might be new content
        
        const lastCheck = localStorage.getItem('tkm_last_announcement_check');
        const now = Date.now();
        
        if (!lastCheck || (now - parseInt(lastCheck)) > 10 * 60 * 1000) { // 10 minutes
            localStorage.setItem('tkm_last_announcement_check', now.toString());
            
            // You could implement actual checking logic here
            // showNewAnnouncementNotification();
        }
    }

    // Show notification for new announcements
    function showNewAnnouncementNotification() {
        const notification = document.createElement('div');
        notification.className = 'tkm-new-announcement-notification';
        notification.innerHTML = `
            <div class="tkm-notification-content">
                <i class="fas fa-bullhorn"></i>
                <span>New announcements are available!</span>
                <button class="tkm-refresh-btn" onclick="location.reload()">Refresh</button>
                <button class="tkm-dismiss-btn">&times;</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-dismiss after 10 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.add('tkm-fade-out');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }, 10000);
        
        // Manual dismiss
        notification.querySelector('.tkm-dismiss-btn').addEventListener('click', function() {
            notification.classList.add('tkm-fade-out');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        });
    }

    // Initialize all features when page is fully loaded
    window.addEventListener('load', function() {
        initializeExternalLinks();
        // initializeAutoRefresh(); // Uncomment if auto-refresh is desired
    });

    // Export functions for external use if needed
    window.TkmAnnouncements = {
        filterAnnouncements: filterAnnouncements,
        searchAnnouncements: searchAnnouncements,
        showLoadingSpinner: showLoadingSpinner
    };

})();
