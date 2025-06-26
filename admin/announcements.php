<?php
/**
 * Indoor Tasks - Announcements Management
 * Admin page for creating and managing announcements
 */

if (!defined('ABSPATH')) exit;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!wp_verify_nonce($_POST['indoor_tasks_nonce'], 'indoor_tasks_announcements')) {
        wp_die(__('Security check failed', 'indoor-tasks'));
    }

    global $wpdb;

    switch ($_POST['action']) {
        case 'create_announcement':
            $title = sanitize_text_field($_POST['title']);
            $message = sanitize_textarea_field($_POST['message']);
            $type = sanitize_text_field($_POST['type']);
            $target_audience = sanitize_text_field($_POST['target_audience']);
            $send_email = isset($_POST['send_email']) ? 1 : 0;
            $send_push = isset($_POST['send_push']) ? 1 : 0;
            $send_telegram = isset($_POST['send_telegram']) ? 1 : 0;
            $schedule_time = !empty($_POST['schedule_time']) ? sanitize_text_field($_POST['schedule_time']) : null;

            // Insert announcement
            $announcement_id = $wpdb->insert(
                $wpdb->prefix . 'indoor_task_announcements',
                [
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'target_audience' => $target_audience,
                    'send_email' => $send_email,
                    'send_push' => $send_push,
                    'send_telegram' => $send_telegram,
                    'schedule_time' => $schedule_time,
                    'status' => $schedule_time ? 'scheduled' : 'pending',
                    'created_by' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                ]
            );

            if ($announcement_id) {
                if (!$schedule_time) {
                    // Send immediately
                    indoor_tasks_send_announcement($wpdb->insert_id);
                    $message = __('Announcement created and sent successfully!', 'indoor-tasks');
                } else {
                    $message = __('Announcement scheduled successfully!', 'indoor-tasks');
                }
                echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Failed to create announcement.', 'indoor-tasks') . '</p></div>';
            }
            break;

        case 'send_announcement':
            $announcement_id = intval($_POST['announcement_id']);
            if (indoor_tasks_send_announcement($announcement_id)) {
                echo '<div class="notice notice-success"><p>' . __('Announcement sent successfully!', 'indoor-tasks') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Failed to send announcement.', 'indoor-tasks') . '</p></div>';
            }
            break;

        case 'delete_announcement':
            $announcement_id = intval($_POST['announcement_id']);
            $deleted = $wpdb->delete(
                $wpdb->prefix . 'indoor_task_announcements',
                ['id' => $announcement_id],
                ['%d']
            );
            
            if ($deleted) {
                echo '<div class="notice notice-success"><p>' . __('Announcement deleted successfully!', 'indoor-tasks') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Failed to delete announcement.', 'indoor-tasks') . '</p></div>';
            }
            break;
    }
}

// Get announcements
global $wpdb;
$announcements = $wpdb->get_results("
    SELECT a.*, u.display_name as created_by_name 
    FROM {$wpdb->prefix}indoor_task_announcements a 
    LEFT JOIN {$wpdb->users} u ON a.created_by = u.ID 
    ORDER BY a.created_at DESC 
    LIMIT 50
");

// Enqueue admin scripts and styles
wp_enqueue_style('indoor-tasks-announcements-admin', INDOOR_TASKS_URL . 'assets/css/announcements-admin.css', [], INDOOR_TASKS_VERSION);

// Only enqueue and localize script if file exists and we're in admin
$admin_js_file = INDOOR_TASKS_PATH . 'assets/js/announcements-admin.js';
if (file_exists($admin_js_file) && is_admin()) {
    wp_enqueue_script('indoor-tasks-announcements-admin', INDOOR_TASKS_URL . 'assets/js/announcements-admin.js', ['jquery'], INDOOR_TASKS_VERSION, true);
    
    // Ensure script is enqueued before localizing
    add_action('wp_enqueue_scripts', function() {
        // This will run after scripts are enqueued
    }, 999);
    
    // Localize script for AJAX - only if script handle exists
    $localize_data = array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('indoor_tasks_announcements_nonce'),
        'strings' => array(
            'sending' => __('Sending...', 'indoor-tasks'),
            'sent' => __('Sent', 'indoor-tasks'),
            'failed' => __('Failed', 'indoor-tasks'),
            'confirm_send' => __('Are you sure you want to send this announcement?', 'indoor-tasks'),
            'confirm_delete' => __('Are you sure you want to delete this announcement? This action cannot be undone.', 'indoor-tasks')
        )
    );
    
    wp_localize_script('indoor-tasks-announcements-admin', 'indoor_tasks_admin', $localize_data);
}

// Get user statistics for target audience
$total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
$verified_users = $wpdb->get_var("
    SELECT COUNT(DISTINCT u.ID) 
    FROM {$wpdb->users} u 
    INNER JOIN {$wpdb->prefix}indoor_task_kyc k ON u.ID = k.user_id 
    WHERE k.status = 'approved'
");
$active_users = $wpdb->get_var("
    SELECT COUNT(DISTINCT s.user_id) 
    FROM {$wpdb->prefix}indoor_task_submissions s 
    WHERE s.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");

?>

<div class="wrap">
    <h1><?php _e('Announcements', 'indoor-tasks'); ?></h1>
    
    <!-- Create New Announcement -->
    <div class="indoor-tasks-card">
        <h2><?php _e('Create New Announcement', 'indoor-tasks'); ?></h2>
        
        <form method="post" class="announcement-form">
            <?php wp_nonce_field('indoor_tasks_announcements', 'indoor_tasks_nonce'); ?>
            <input type="hidden" name="action" value="create_announcement">
            
            <table class="form-table">
                <tr>
                    <th><label for="title"><?php _e('Title', 'indoor-tasks'); ?></label></th>
                    <td>
                        <input type="text" id="title" name="title" class="regular-text" required>
                        <p class="description"><?php _e('Enter a descriptive title for the announcement', 'indoor-tasks'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="message"><?php _e('Message', 'indoor-tasks'); ?></label></th>
                    <td>
                        <textarea id="message" name="message" rows="5" cols="50" class="large-text" required></textarea>
                        <p class="description"><?php _e('Enter the announcement message content', 'indoor-tasks'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="type"><?php _e('Type', 'indoor-tasks'); ?></label></th>
                    <td>
                        <select id="type" name="type" required>
                            <option value="general"><?php _e('General', 'indoor-tasks'); ?></option>
                            <option value="task"><?php _e('Task Related', 'indoor-tasks'); ?></option>
                            <option value="maintenance"><?php _e('Maintenance', 'indoor-tasks'); ?></option>
                            <option value="promotion"><?php _e('Promotion', 'indoor-tasks'); ?></option>
                            <option value="urgent"><?php _e('Urgent', 'indoor-tasks'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="target_audience"><?php _e('Target Audience', 'indoor-tasks'); ?></label></th>
                    <td>
                        <select id="target_audience" name="target_audience" required>
                            <option value="all"><?php printf(__('All Users (%d)', 'indoor-tasks'), $total_users); ?></option>
                            <option value="verified"><?php printf(__('Verified Users (%d)', 'indoor-tasks'), $verified_users); ?></option>
                            <option value="active"><?php printf(__('Active Users (%d)', 'indoor-tasks'), $active_users); ?></option>
                            <option value="new"><?php _e('New Users (Last 7 days)', 'indoor-tasks'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th><?php _e('Delivery Channels', 'indoor-tasks'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="send_email" value="1" checked>
                                <?php _e('Send Email Notification', 'indoor-tasks'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="send_push" value="1" checked>
                                <?php _e('Send Push Notification', 'indoor-tasks'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="send_telegram" value="1">
                                <?php _e('Send to Telegram Channel', 'indoor-tasks'); ?>
                            </label>
                        </fieldset>
                        <p class="description"><?php _e('Select which channels to use for delivery', 'indoor-tasks'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="schedule_time"><?php _e('Schedule Time', 'indoor-tasks'); ?></label></th>
                    <td>
                        <input type="datetime-local" id="schedule_time" name="schedule_time">
                        <p class="description"><?php _e('Leave empty to send immediately, or set a future time to schedule', 'indoor-tasks'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Create Announcement', 'indoor-tasks'); ?>">
            </p>
        </form>
    </div>
    
    <!-- Announcements List -->
    <div class="indoor-tasks-card">
        <h2><?php _e('Recent Announcements', 'indoor-tasks'); ?></h2>
        
        <?php if (empty($announcements)): ?>
            <p><?php _e('No announcements found.', 'indoor-tasks'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Title', 'indoor-tasks'); ?></th>
                        <th><?php _e('Type', 'indoor-tasks'); ?></th>
                        <th><?php _e('Target', 'indoor-tasks'); ?></th>
                        <th><?php _e('Channels', 'indoor-tasks'); ?></th>
                        <th><?php _e('Status', 'indoor-tasks'); ?></th>
                        <th><?php _e('Created', 'indoor-tasks'); ?></th>
                        <th><?php _e('Actions', 'indoor-tasks'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $announcement): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($announcement->title); ?></strong>
                                <div class="announcement-message"><?php echo esc_html(wp_trim_words($announcement->message, 15)); ?></div>
                            </td>
                            <td>
                                <span class="announcement-type type-<?php echo esc_attr($announcement->type); ?>">
                                    <?php echo esc_html(ucfirst($announcement->type)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(ucfirst($announcement->target_audience)); ?></td>
                            <td>
                                <div class="channels">
                                    <?php if ($announcement->send_email): ?>
                                        <span class="channel email" title="<?php _e('Email', 'indoor-tasks'); ?>">📧</span>
                                    <?php endif; ?>
                                    <?php if ($announcement->send_push): ?>
                                        <span class="channel push" title="<?php _e('Push Notification', 'indoor-tasks'); ?>">🔔</span>
                                    <?php endif; ?>
                                    <?php if ($announcement->send_telegram): ?>
                                        <span class="channel telegram" title="<?php _e('Telegram', 'indoor-tasks'); ?>">📱</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status status-<?php echo esc_attr($announcement->status); ?>">
                                    <?php echo esc_html(ucfirst($announcement->status)); ?>
                                </span>
                                <?php if ($announcement->schedule_time && $announcement->status === 'scheduled'): ?>
                                    <br><small><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($announcement->schedule_time))); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo esc_html($announcement->created_by_name); ?></div>
                                <small><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($announcement->created_at))); ?></small>
                            </td>
                            <td>
                                <?php if ($announcement->status === 'pending' || $announcement->status === 'scheduled'): ?>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('indoor_tasks_announcements', 'indoor_tasks_nonce'); ?>
                                        <input type="hidden" name="action" value="send_announcement">
                                        <input type="hidden" name="announcement_id" value="<?php echo esc_attr($announcement->id); ?>">
                                        <input type="submit" class="button button-small" value="<?php _e('Send Now', 'indoor-tasks'); ?>">
                                    </form>
                                <?php endif; ?>
                                
                                <form method="post" style="display: inline;" onsubmit="return confirm('<?php _e('Are you sure you want to delete this announcement?', 'indoor-tasks'); ?>')">
                                    <?php wp_nonce_field('indoor_tasks_announcements', 'indoor_tasks_nonce'); ?>
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="announcement_id" value="<?php echo esc_attr($announcement->id); ?>">
                                    <input type="submit" class="button button-small button-link-delete" value="<?php _e('Delete', 'indoor-tasks'); ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
.indoor-tasks-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.announcement-form .form-table th {
    width: 150px;
}

.announcement-message {
    color: #666;
    font-size: 12px;
    margin-top: 4px;
}

.announcement-type {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.type-general { background: #e3f2fd; color: #1976d2; }
.type-task { background: #f3e5f5; color: #7b1fa2; }
.type-maintenance { background: #fff3e0; color: #f57c00; }
.type-promotion { background: #e8f5e8; color: #388e3c; }
.type-urgent { background: #ffebee; color: #d32f2f; }

.channels {
    display: flex;
    gap: 4px;
}

.channel {
    display: inline-block;
    font-size: 16px;
    cursor: help;
}

.status {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-scheduled { background: #d1ecf1; color: #0c5460; }
.status-sent { background: #d4edda; color: #155724; }
.status-failed { background: #f8d7da; color: #721c24; }
</style>
