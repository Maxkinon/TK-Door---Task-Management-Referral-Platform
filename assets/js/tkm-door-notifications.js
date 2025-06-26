/**
 * TKM Door Notifications JavaScript
 * Handles notification interactions and real-time updates
 * Version: 1.0.0
 */

(function() {
    'use strict';

    let unreadCount = 0;
    let refreshInterval;

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeNotifications();
    });

    function initializeNotifications() {
        initializeAnimations();
        initializeMarkAsRead();
        initializeMarkAllAsRead();
        initializePagination();
        initializeFilters();
        initializeRealTimeUpdates();
        updateUnreadCount();
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

        // Observe notification items
        const notifications = document.querySelectorAll('.tkm-notification-item');
        notifications.forEach(notification => {
            observer.observe(notification);
        });
    }

    // Initialize individual mark as read functionality
    function initializeMarkAsRead() {
        const markReadButtons = document.querySelectorAll('.tkm-mark-read-btn');
        
        markReadButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const notificationItem = this.closest('.tkm-notification-item');
                const notificationId = this.href.match(/mark_read=(\d+)/)[1];
                
                // Add loading state
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                this.style.pointerEvents = 'none';
                
                // Simulate API call or use the existing href
                setTimeout(() => {
                    markNotificationAsRead(notificationItem, notificationId);
                    window.location.href = this.href;
                }, 500);
            });
        });
    }

    // Mark notification as read with animation
    function markNotificationAsRead(notificationItem, notificationId) {
        if (notificationItem.classList.contains('unread')) {
            notificationItem.classList.remove('unread');
            notificationItem.classList.add('read');
            
            // Update unread count
            updateUnreadCountDisplay(-1);
            
            // Remove the mark as read button
            const markReadBtn = notificationItem.querySelector('.tkm-mark-read-btn');
            if (markReadBtn) {
                markReadBtn.style.opacity = '0';
                markReadBtn.style.transform = 'scale(0)';
                setTimeout(() => {
                    if (markReadBtn.parentNode) {
                        markReadBtn.parentNode.removeChild(markReadBtn);
                    }
                }, 300);
            }
            
            // Show success feedback
            showNotificationFeedback('Notification marked as read', 'success');
        }
    }

    // Initialize mark all as read functionality
    function initializeMarkAllAsRead() {
        const markAllBtn = document.querySelector('.tkm-mark-all-btn');
        
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const unreadNotifications = document.querySelectorAll('.tkm-notification-item.unread');
                
                if (unreadNotifications.length === 0) {
                    showNotificationFeedback('No unread notifications to mark', 'info');
                    return;
                }
                
                // Show confirmation
                if (confirm(`Mark all ${unreadNotifications.length} notifications as read?`)) {
                    // Add loading state
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking all as read...';
                    this.style.pointerEvents = 'none';
                    
                    // Animate each notification
                    unreadNotifications.forEach((notification, index) => {
                        setTimeout(() => {
                            notification.classList.remove('unread');
                            notification.classList.add('read');
                            
                            // Remove mark as read button
                            const markReadBtn = notification.querySelector('.tkm-mark-read-btn');
                            if (markReadBtn) {
                                markReadBtn.style.opacity = '0';
                                markReadBtn.style.transform = 'scale(0)';
                                setTimeout(() => {
                                    if (markReadBtn.parentNode) {
                                        markReadBtn.parentNode.removeChild(markReadBtn);
                                    }
                                }, 300);
                            }
                        }, index * 100);
                    });
                    
                    // Update UI after animations complete
                    setTimeout(() => {
                        updateUnreadCountDisplay(-unreadNotifications.length);
                        this.closest('form').submit();
                    }, unreadNotifications.length * 100 + 500);
                }
            });
        }
    }

    // Initialize pagination functionality
    function initializePagination() {
        const paginationLinks = document.querySelectorAll('.tkm-pagination-number, .tkm-pagination-btn');
        
        paginationLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Add loading state
                const container = document.querySelector('.tkm-notifications-list');
                if (container) {
                    container.classList.add('tkm-loading');
                }
                
                // Show loading spinner
                showLoadingSpinner();
            });
        });
    }

    // Initialize notification filters
    function initializeFilters() {
        const filterButtons = document.querySelectorAll('.tkm-filter-btn');
        
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Filter notifications
                filterNotifications(filter);
            });
        });
    }

    // Filter notifications by type or read status
    function filterNotifications(filter) {
        const notifications = document.querySelectorAll('.tkm-notification-item');
        let visibleCount = 0;
        
        notifications.forEach(notification => {
            let shouldShow = false;
            
            switch(filter) {
                case 'all':
                    shouldShow = true;
                    break;
                case 'unread':
                    shouldShow = notification.classList.contains('unread');
                    break;
                case 'read':
                    shouldShow = !notification.classList.contains('unread');
                    break;
                default:
                    // Filter by notification type
                    const icon = notification.querySelector('.tkm-notification-icon');
                    shouldShow = icon && icon.classList.contains(filter);
                    break;
            }
            
            if (shouldShow) {
                notification.style.display = 'flex';
                visibleCount++;
            } else {
                notification.style.display = 'none';
            }
        });
        
        // Show no results message if needed
        showNoResultsMessage(visibleCount === 0);
    }

    // Show no results message
    function showNoResultsMessage(show) {
        let noResultsMsg = document.querySelector('.tkm-no-results');
        
        if (show && !noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.className = 'tkm-no-results tkm-empty-state';
            noResultsMsg.innerHTML = `
                <div class="tkm-empty-icon">
                    <i class="fas fa-filter"></i>
                </div>
                <h3 class="tkm-empty-title">No Notifications Found</h3>
                <p class="tkm-empty-description">
                    No notifications match the current filter. Try selecting a different filter option.
                </p>
            `;
            
            const list = document.querySelector('.tkm-notifications-list');
            if (list) {
                list.parentNode.insertBefore(noResultsMsg, list.nextSibling);
            }
        } else if (!show && noResultsMsg) {
            noResultsMsg.remove();
        }
    }

    // Update unread count display
    function updateUnreadCountDisplay(change) {
        const unreadBadge = document.querySelector('.tkm-unread-badge');
        const unreadStatNumber = document.querySelector('.tkm-stat-icon.unread').nextElementSibling.querySelector('.tkm-stat-number');
        const readStatNumber = document.querySelector('.tkm-stat-icon.read').nextElementSibling.querySelector('.tkm-stat-number');
        
        unreadCount += change;
        
        if (unreadBadge) {
            if (unreadCount > 0) {
                unreadBadge.textContent = unreadCount;
                unreadBadge.style.display = 'inline-block';
            } else {
                unreadBadge.style.display = 'none';
            }
        }
        
        if (unreadStatNumber) {
            unreadStatNumber.textContent = unreadCount.toLocaleString();
        }
        
        if (readStatNumber) {
            const currentRead = parseInt(readStatNumber.textContent.replace(/,/g, ''));
            readStatNumber.textContent = (currentRead - change).toLocaleString();
        }
        
        // Update page title if there are unread notifications
        updatePageTitle();
    }

    // Update page title with unread count
    function updatePageTitle() {
        const originalTitle = document.title.replace(/^\(\d+\)\s+/, '');
        
        if (unreadCount > 0) {
            document.title = `(${unreadCount}) ${originalTitle}`;
        } else {
            document.title = originalTitle;
        }
    }

    // Get current unread count from page
    function updateUnreadCount() {
        const unreadBadge = document.querySelector('.tkm-unread-badge');
        if (unreadBadge) {
            unreadCount = parseInt(unreadBadge.textContent) || 0;
        } else {
            const unreadNotifications = document.querySelectorAll('.tkm-notification-item.unread');
            unreadCount = unreadNotifications.length;
        }
        
        updatePageTitle();
    }

    // Initialize real-time updates
    function initializeRealTimeUpdates() {
        // Check for new notifications every 30 seconds
        refreshInterval = setInterval(() => {
            if (!document.hidden) {
                checkForNewNotifications();
            }
        }, 30000);
        
        // Stop checking when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                }
            } else {
                // Restart checking when page becomes visible
                initializeRealTimeUpdates();
            }
        });
    }

    // Check for new notifications
    function checkForNewNotifications() {
        // This would typically make an AJAX call to check for new notifications
        // For demonstration, we'll simulate the check
        
        const lastCheck = localStorage.getItem('tkm_last_notification_check');
        const now = Date.now();
        
        if (!lastCheck || (now - parseInt(lastCheck)) > 60000) { // 1 minute
            localStorage.setItem('tkm_last_notification_check', now.toString());
            
            // Simulate finding new notifications (remove in production)
            if (Math.random() < 0.1) { // 10% chance of new notification
                showNewNotificationAlert();
            }
        }
    }

    // Show alert for new notifications
    function showNewNotificationAlert() {
        const alertBanner = document.createElement('div');
        alertBanner.className = 'tkm-new-notification-alert';
        alertBanner.innerHTML = `
            <div class="tkm-alert-content">
                <i class="fas fa-bell"></i>
                <span>You have new notifications!</span>
                <button class="tkm-refresh-btn" onclick="location.reload()">Refresh</button>
                <button class="tkm-dismiss-btn">&times;</button>
            </div>
        `;
        
        document.body.appendChild(alertBanner);
        
        // Auto-dismiss after 8 seconds
        setTimeout(() => {
            dismissAlert(alertBanner);
        }, 8000);
        
        // Manual dismiss
        alertBanner.querySelector('.tkm-dismiss-btn').addEventListener('click', function() {
            dismissAlert(alertBanner);
        });
        
        function dismissAlert(alert) {
            alert.classList.add('tkm-fade-out');
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 300);
        }
    }

    // Show loading spinner
    function showLoadingSpinner() {
        const spinner = document.createElement('div');
        spinner.className = 'tkm-loading-spinner';
        spinner.innerHTML = `
            <div class="tkm-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading notifications...</span>
            </div>
        `;
        
        document.body.appendChild(spinner);
        
        // Remove spinner after a short delay
        setTimeout(() => {
            if (spinner.parentNode) {
                spinner.parentNode.removeChild(spinner);
            }
        }, 1000);
    }

    // Show notification feedback
    function showNotificationFeedback(message, type = 'info') {
        const feedback = document.createElement('div');
        feedback.className = `tkm-notification-feedback tkm-feedback-${type}`;
        feedback.innerHTML = `
            <div class="tkm-feedback-content">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(feedback);
        
        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            feedback.classList.add('tkm-fade-out');
            setTimeout(() => {
                if (feedback.parentNode) {
                    feedback.parentNode.removeChild(feedback);
                }
            }, 300);
        }, 3000);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Shift + A = Mark all as read
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'A') {
            e.preventDefault();
            const markAllBtn = document.querySelector('.tkm-mark-all-btn');
            if (markAllBtn) {
                markAllBtn.click();
            }
        }
        
        // Ctrl/Cmd + R = Refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            location.reload();
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });

    // Export functions for external use if needed
    window.TkmNotifications = {
        markNotificationAsRead: markNotificationAsRead,
        filterNotifications: filterNotifications,
        updateUnreadCountDisplay: updateUnreadCountDisplay,
        showNotificationFeedback: showNotificationFeedback
    };

})();
