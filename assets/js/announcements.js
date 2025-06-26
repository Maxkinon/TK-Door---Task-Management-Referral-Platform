/**
 * Indoor Tasks - Announcements Frontend JavaScript
 * 
 * Handles interactive functionality for announcements on the frontend
 */

(function($) {
    'use strict';
    
    let currentPage = 1;
    let loading = false;
    let hasMore = true;
    
    // Initialize when document is ready
    $(document).ready(function() {
        initAnnouncements();
    });
    
    function initAnnouncements() {
        // Only initialize if we're on a page with announcement elements
        if ($('#announcements-grid').length || $('.announcement-card').length) {
            // Initialize type filter
            initTypeFilter();
            
            // Initialize load more functionality
            initLoadMore();
            
            // Initialize mark as read functionality
            initMarkAsRead();
            
            // Initialize share functionality
            initShareFunctionality();
            
            // Initialize auto-mark as read on scroll
            initAutoMarkAsRead();
        }
    }
    
    function initTypeFilter() {
        const typeFilter = $('#type-filter');
        if (typeFilter.length) {
            typeFilter.on('change', function() {
                const selectedType = $(this).val();
                
                // Reset pagination
                currentPage = 1;
                hasMore = true;
                
                // Clear existing announcements
                const announcementsGrid = $('#announcements-grid');
                if (announcementsGrid.length) {
                    announcementsGrid.empty();
                }
                
                // Load announcements with new filter
                loadMoreAnnouncements(selectedType, true);
                
                // Show load more button if it was hidden
                const loadMoreBtn = $('#load-more-announcements');
                if (loadMoreBtn.length) {
                    loadMoreBtn.show();
                }
            });
        }
    }
    
    function initLoadMore() {
        const loadMoreBtn = $('#load-more-announcements');
        if (loadMoreBtn.length) {
            loadMoreBtn.on('click', function() {
                if (loading || !hasMore) return;
                
                const typeFilter = $('#type-filter');
                const typeFilterVal = typeFilter.length ? typeFilter.val() : '';
                loadMoreAnnouncements(typeFilterVal);
            });
        }
    }
    
    function initMarkAsRead() {
        // Mark announcement as read when clicked
        $(document).on('click', '.announcement-card a, .read-more-btn', function() {
            const announcementCard = $(this).closest('.announcement-card');
            const announcementId = announcementCard.data('id');
            
            if (announcementId) {
                markAnnouncementAsRead(announcementId, announcementCard);
            }
        });
    }
    
    function initShareFunctionality() {
        $(document).on('click', '.share-announcement', function(e) {
            e.preventDefault();
            
            const title = $(this).data('title');
            const url = $(this).data('url') || window.location.href;
            
            if (navigator.share) {
                // Use native Web Share API if available
                navigator.share({
                    title: title,
                    url: url
                }).catch((error) => {
                    console.log('Error sharing:', error);
                    fallbackShare(title, url);
                });
            } else {
                fallbackShare(title, url);
            }
        });
    }
    
    function initAutoMarkAsRead() {
        // Use Intersection Observer to auto-mark announcements as read when they come into view
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const announcementCard = $(entry.target);
                        const announcementId = announcementCard.data('id');
                        
                        if (announcementId && !announcementCard.hasClass('read')) {
                            // Mark as read after 3 seconds of being in view
                            setTimeout(() => {
                                if (entry.isIntersecting) {
                                    markAnnouncementAsRead(announcementId, announcementCard, true);
                                }
                            }, 3000);
                        }
                    }
                });
            }, {
                threshold: 0.5,
                rootMargin: '0px 0px -100px 0px'
            });
            
            // Observe existing announcement cards
            $('.announcement-card').each(function() {
                observer.observe(this);
            });
            
            // Store observer for later use with new cards
            window.announcementObserver = observer;
        }
    }
    
    function loadMoreAnnouncements(typeFilter = '', isFilter = false) {
        if (loading) return;
        
        loading = true;
        const button = $('#load-more-announcements');
        const spinner = $('.loading-spinner');
        
        // Update button state
        button.prop('disabled', true).text(indoorTasksAnnouncements.loadingText);
        spinner.show();
        
        $.ajax({
            url: indoorTasksAnnouncements.ajaxurl,
            type: 'POST',
            data: {
                action: 'indoor_tasks_load_more_announcements',
                nonce: indoorTasksAnnouncements.nonce,
                page: isFilter ? 1 : currentPage,
                type: typeFilter
            },
            success: function(response) {
                if (response.success && response.data.announcements.length > 0) {
                    let html = '';
                    
                    response.data.announcements.forEach(function(announcement) {
                        html += createAnnouncementHTML(announcement);
                    });
                    
                    if (isFilter) {
                        $('#announcements-grid').html(html);
                        currentPage = 2;
                    } else {
                        $('#announcements-grid').append(html);
                        currentPage++;
                    }
                    
                    hasMore = response.data.has_more;
                    
                    // Observe new cards for auto-mark as read
                    if (window.announcementObserver) {
                        $('.announcement-card').each(function() {
                            if (!$(this).data('observed')) {
                                window.announcementObserver.observe(this);
                                $(this).data('observed', true);
                            }
                        });
                    }
                    
                    if (hasMore) {
                        button.prop('disabled', false).text(indoorTasksAnnouncements.loadMoreText);
                    } else {
                        button.hide();
                        showNoMoreMessage();
                    }
                } else {
                    if (isFilter && response.data.announcements.length === 0) {
                        $('#announcements-grid').html('<div class="no-announcements"><p>No announcements found for this type.</p></div>');
                    }
                    hasMore = false;
                    button.hide();
                    if (!isFilter) {
                        showNoMoreMessage();
                    }
                }
            },
            error: function() {
                showError('Error loading announcements. Please try again.');
                button.prop('disabled', false).text(indoorTasksAnnouncements.loadMoreText);
            },
            complete: function() {
                loading = false;
                spinner.hide();
            }
        });
    }
    
    function createAnnouncementHTML(announcement) {
        return `
            <div class="announcement-card" data-id="${announcement.id}">
                <div class="announcement-header">
                    <div class="announcement-type ${announcement.type_class}">
                        ${announcement.type}
                    </div>
                    <div class="announcement-date">
                        ${announcement.time_ago}
                    </div>
                </div>
                
                <div class="announcement-content">
                    <h3 class="announcement-title">
                        <a href="${announcement.link}">
                            ${announcement.title}
                        </a>
                    </h3>
                    <div class="announcement-message">
                        ${announcement.message}
                    </div>
                </div>
                
                <div class="announcement-footer">
                    <a href="${announcement.link}" class="read-more-btn">
                        Read More
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </a>
                </div>
            </div>
        `;
    }
    
    function markAnnouncementAsRead(announcementId, announcementCard, silent = false) {
        // Skip if already marked as read
        if (announcementCard.hasClass('read')) {
            return;
        }
        
        $.ajax({
            url: indoorTasksAnnouncements.ajaxurl,
            type: 'POST',
            data: {
                action: 'indoor_tasks_mark_announcement_read',
                nonce: indoorTasksAnnouncements.nonce,
                announcement_id: announcementId
            },
            success: function(response) {
                if (response.success) {
                    announcementCard.addClass('read');
                    
                    if (!silent) {
                        // Optional: Show a subtle indicator that it was marked as read
                        showReadIndicator(announcementCard);
                    }
                }
            },
            error: function() {
                if (!silent) {
                    console.log('Failed to mark announcement as read');
                }
            }
        });
    }
    
    function showReadIndicator(announcementCard) {
        const indicator = $('<div class="read-indicator">✓ Read</div>');
        announcementCard.append(indicator);
        
        setTimeout(() => {
            indicator.fadeOut(300, function() {
                $(this).remove();
            });
        }, 2000);
    }
    
    function fallbackShare(title, url) {
        // Fallback share functionality
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(() => {
                showSuccess('Link copied to clipboard!');
            }).catch(() => {
                showCopyDialog(title, url);
            });
        } else {
            showCopyDialog(title, url);
        }
    }
    
    function showCopyDialog(title, url) {
        const modal = $(`
            <div class="share-modal-overlay">
                <div class="share-modal">
                    <h3>Share Announcement</h3>
                    <p><strong>${title}</strong></p>
                    <div class="share-links">
                        <a href="https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}" target="_blank" class="share-link twitter">
                            <i class="dashicons dashicons-twitter"></i> Twitter
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}" target="_blank" class="share-link facebook">
                            <i class="dashicons dashicons-facebook"></i> Facebook
                        </a>
                        <a href="mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent(url)}" class="share-link email">
                            <i class="dashicons dashicons-email"></i> Email
                        </a>
                    </div>
                    <div class="copy-url">
                        <input type="text" value="${url}" readonly>
                        <button class="copy-btn">Copy</button>
                    </div>
                    <button class="close-modal">×</button>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Handle copy button
        modal.find('.copy-btn').on('click', function() {
            const input = modal.find('input[type="text"]');
            input.select();
            document.execCommand('copy');
            $(this).text('Copied!');
            setTimeout(() => {
                modal.remove();
            }, 1000);
        });
        
        // Handle close
        modal.find('.close-modal, .share-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                modal.remove();
            }
        });
    }
    
    function showNoMoreMessage() {
        const announcementsGrid = $('#announcements-grid');
        if (announcementsGrid.length && !$('.no-more-announcements').length) {
            announcementsGrid.after(`
                <div class="no-more-announcements">
                    <p>${indoorTasksAnnouncements.noMoreText}</p>
                </div>
            `);
        }
    }
    
    function showSuccess(message) {
        showNotification(message, 'success');
    }
    
    function showError(message) {
        showNotification(message, 'error');
    }
    
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="announcement-notification ${type}">
                ${message}
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(() => {
            notification.addClass('show');
        }, 100);
        
        setTimeout(() => {
            notification.removeClass('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
    
})(jQuery);

// Additional CSS for notifications and modals
const additionalCSS = `
.read-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #4CAF50;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    z-index: 10;
}

.announcement-card.read {
    opacity: 0.8;
}

.announcement-card.read::after {
    content: '';
    position: absolute;
    top: 8px;
    right: 8px;
    width: 8px;
    height: 8px;
    background: #4CAF50;
    border-radius: 50%;
}

.share-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.share-modal {
    background: white;
    padding: 24px;
    border-radius: 12px;
    max-width: 400px;
    width: 90%;
    position: relative;
}

.share-modal h3 {
    margin: 0 0 16px 0;
    color: #333;
}

.share-links {
    display: flex;
    gap: 12px;
    margin: 16px 0;
}

.share-link {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
}

.share-link.twitter {
    background: #1DA1F2;
    color: white;
}

.share-link.facebook {
    background: #4267B2;
    color: white;
}

.share-link.email {
    background: #f0f0f0;
    color: #333;
}

.copy-url {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

.copy-url input {
    flex: 1;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.copy-btn {
    padding: 8px 16px;
    background: #0073aa;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.close-modal {
    position: absolute;
    top: 8px;
    right: 12px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.announcement-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 1001;
    transform: translateX(100%);
    transition: transform 0.3s ease;
}

.announcement-notification.show {
    transform: translateX(0);
}

.announcement-notification.success {
    background: #4CAF50;
}

.announcement-notification.error {
    background: #f44336;
}

.announcement-notification.info {
    background: #2196F3;
}

.no-more-announcements {
    text-align: center;
    padding: 20px;
    color: #666;
    font-style: italic;
}

.no-announcements {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

@media (max-width: 768px) {
    .share-links {
        flex-direction: column;
    }
    
    .share-modal {
        margin: 20px;
    }
    
    .announcement-notification {
        left: 20px;
        right: 20px;
        transform: translateY(-100%);
    }
    
    .announcement-notification.show {
        transform: translateY(0);
    }
}
`;

// Inject additional CSS
if (typeof document !== 'undefined') {
    const style = document.createElement('style');
    style.textContent = additionalCSS;
    document.head.appendChild(style);
}
