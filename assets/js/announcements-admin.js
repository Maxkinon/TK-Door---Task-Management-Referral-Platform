/**
 * Indoor Tasks - Announcements Admin JavaScript
 * 
 * Handles interactive functionality for the announcements admin page
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initAnnouncementsAdmin();
    });
    
    function initAnnouncementsAdmin() {
        // Add body class for styling
        $('body').addClass('indoor-tasks-announcements-page');
        
        // Initialize form enhancements
        initFormEnhancements();
        
        // Initialize quick actions
        initQuickActions();
        
        // Initialize confirmation dialogs
        initConfirmationDialogs();
        
        // Initialize auto-refresh for scheduled announcements
        initAutoRefresh();
    }
    
    function initFormEnhancements() {
        // Character counter for message field
        const messageField = $('#message');
        if (messageField.length) {
            const charCounter = $('<div class="char-counter"></div>');
            messageField.after(charCounter);
            
            messageField.on('input', function() {
                const count = $(this).val().length;
                const maxLength = 1000; // Set reasonable limit
                charCounter.text(count + '/' + maxLength + ' characters');
                
                if (count > maxLength * 0.9) {
                    charCounter.addClass('warning');
                } else {
                    charCounter.removeClass('warning');
                }
            });
            
            // Trigger initial count
            messageField.trigger('input');
        }
        
        // Dynamic target audience count
        const targetSelect = $('#target_audience');
        if (targetSelect.length) {
            targetSelect.on('change', function() {
                updateTargetAudienceCount($(this).val());
            });
        }
        
        // Schedule time validation
        const scheduleInput = $('#schedule_time');
        if (scheduleInput.length) {
            scheduleInput.on('change', function() {
                validateScheduleTime($(this).val());
            });
        }
    }
    
    function initQuickActions() {
        // Quick send button with confirmation
        $('.quick-send-btn').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const announcementId = button.data('id');
            const title = button.data('title');
            
            if (confirm('Are you sure you want to send "' + title + '" immediately?')) {
                sendAnnouncement(announcementId, button);
            }
        });
        
        // Bulk actions
        $('#bulk-action-apply').on('click', function(e) {
            e.preventDefault();
            
            const action = $('#bulk-action-selector').val();
            const checkedItems = $('.announcement-checkbox:checked');
            
            if (action === '' || checkedItems.length === 0) {
                alert('Please select an action and at least one announcement.');
                return;
            }
            
            if (confirm('Are you sure you want to ' + action + ' ' + checkedItems.length + ' announcement(s)?')) {
                processBulkAction(action, checkedItems);
            }
        });
        
        // Select all checkbox
        $('#select-all-announcements').on('change', function() {
            $('.announcement-checkbox').prop('checked', $(this).prop('checked'));
        });
    }
    
    function initConfirmationDialogs() {
        // Enhanced delete confirmation
        $('.button-delete').on('click', function(e) {
            const button = $(this);
            const form = button.closest('form');
            const title = button.data('title') || 'this announcement';
            
            e.preventDefault();
            
            const confirmed = confirm(
                'Are you sure you want to delete "' + title + '"?\n\n' +
                'This action cannot be undone.'
            );
            
            if (confirmed) {
                // Add loading state
                button.prop('disabled', true).text('Deleting...');
                form.submit();
            }
        });
        
        // Send confirmation with preview
        $('.button-send').on('click', function(e) {
            const button = $(this);
            const form = button.closest('form');
            const announcementId = button.data('id');
            
            e.preventDefault();
            
            showSendPreview(announcementId, function(confirmed) {
                if (confirmed) {
                    button.prop('disabled', true).text('Sending...');
                    form.submit();
                }
            });
        });
    }
    
    function initAutoRefresh() {
        // Auto-refresh for scheduled announcements every 5 minutes
        if ($('.status-scheduled').length > 0) {
            setInterval(function() {
                refreshAnnouncementStatus();
            }, 300000); // 5 minutes
        }
    }
    
    function updateTargetAudienceCount(audience) {
        // This would typically make an AJAX call to get updated counts
        const countElement = $('#target-count-' + audience);
        if (countElement.length) {
            // Show loading
            countElement.html('<span class="spinner is-active"></span>');
            
            // Make AJAX call (placeholder)
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'indoor_tasks_get_audience_count',
                    audience: audience,
                    nonce: indoorTasksAnnouncementsNonce
                },
                success: function(response) {
                    if (response.success) {
                        countElement.text('(' + response.data.count + ' users)');
                    } else {
                        countElement.text('(count unavailable)');
                    }
                },
                error: function() {
                    countElement.text('(count unavailable)');
                }
            });
        }
    }
    
    function validateScheduleTime(datetime) {
        if (!datetime) return;
        
        const scheduleDate = new Date(datetime);
        const now = new Date();
        
        if (scheduleDate <= now) {
            alert('Schedule time must be in the future.');
            $('#schedule_time').val('');
        }
    }
    
    function sendAnnouncement(announcementId, button) {
        const originalText = button.text();
        
        button.prop('disabled', true).text('Sending...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indoor_tasks_send_announcement_ajax',
                announcement_id: announcementId,
                nonce: indoorTasksAnnouncementsNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Announcement sent successfully!', 'success');
                    
                    // Update the UI
                    button.closest('tr').find('.status')
                        .removeClass('status-pending status-scheduled')
                        .addClass('status-sent')
                        .text('Sent');
                    
                    button.remove(); // Remove send button
                } else {
                    showNotice(response.data.message || 'Failed to send announcement.', 'error');
                }
            },
            error: function() {
                showNotice('Network error occurred.', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function processBulkAction(action, checkedItems) {
        const ids = [];
        checkedItems.each(function() {
            ids.push($(this).val());
        });
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indoor_tasks_bulk_announcement_action',
                bulk_action: action,
                announcement_ids: ids,
                nonce: indoorTasksAnnouncementsNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Bulk action completed successfully!', 'success');
                    location.reload(); // Refresh page to show changes
                } else {
                    showNotice(response.data.message || 'Bulk action failed.', 'error');
                }
            },
            error: function() {
                showNotice('Network error occurred.', 'error');
            }
        });
    }
    
    function showSendPreview(announcementId, callback) {
        // Create modal for send preview
        const modal = $(`
            <div class="indoor-tasks-modal" id="send-preview-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Send Announcement Preview</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="loading">Loading preview...</div>
                    </div>
                    <div class="modal-footer">
                        <button class="button button-primary" id="confirm-send">Send Now</button>
                        <button class="button" id="cancel-send">Cancel</button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        modal.show();
        
        // Load preview content
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indoor_tasks_get_announcement_preview',
                announcement_id: announcementId,
                nonce: indoorTasksAnnouncementsNonce
            },
            success: function(response) {
                if (response.success) {
                    modal.find('.modal-body').html(response.data.preview);
                } else {
                    modal.find('.modal-body').html('<p>Preview unavailable.</p>');
                }
            },
            error: function() {
                modal.find('.modal-body').html('<p>Failed to load preview.</p>');
            }
        });
        
        // Handle modal actions
        modal.find('#confirm-send').on('click', function() {
            modal.remove();
            callback(true);
        });
        
        modal.find('#cancel-send, .modal-close').on('click', function() {
            modal.remove();
            callback(false);
        });
    }
    
    function refreshAnnouncementStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'indoor_tasks_refresh_announcement_status',
                nonce: indoorTasksAnnouncementsNonce
            },
            success: function(response) {
                if (response.success && response.data.updated) {
                    // Soft refresh - update only changed items
                    location.reload();
                }
            }
        });
    }
    
    function showNotice(message, type) {
        const notice = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
        
        // Manual dismiss
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        });
    }
    
})(jQuery);

// Modal styles (inline for simplicity)
jQuery(document).ready(function($) {
    const modalStyles = `
        <style>
        .indoor-tasks-modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .indoor-tasks-modal .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 6px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .indoor-tasks-modal .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e4e7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .indoor-tasks-modal .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .indoor-tasks-modal .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .indoor-tasks-modal .modal-body {
            padding: 24px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .indoor-tasks-modal .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e2e4e7;
            text-align: right;
        }
        
        .indoor-tasks-modal .modal-footer .button {
            margin-left: 8px;
        }
        
        .char-counter {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .char-counter.warning {
            color: #d63638;
        }
        </style>
    `;
    
    $('head').append(modalStyles);
});
