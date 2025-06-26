<?php
/**
 * Template Name: TKM Door - Notifications
 * Description: Modern notifications template for user alerts and updates
 * Version: 1.0.0
 */

// Prevent direct file access
defined('ABSPATH') || exit;

// Redirect if not logged in
if (!is_user_logged_in()) {
    // Try to find auth page
    $login_page = null;
    if (function_exists('indoor_tasks_get_page_by_template')) {
        $login_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tk-indoor-auth.php', 'login');
    }
    
    if ($login_page) {
        wp_redirect(get_permalink($login_page->ID));
    } else {
        wp_redirect(home_url('/login/'));
    }
    exit;
}

// Get current user info
$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Get page title
$page_title = 'Notifications';

// Set global template variable for sidebar detection
$GLOBALS['indoor_tasks_current_template'] = 'tkm-door-notifications.php';

// Get database reference
global $wpdb;

// Handle mark as read action
if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read' && wp_verify_nonce($_POST['nonce'], 'tkm_notifications_nonce')) {
    $notifications_table = $wpdb->prefix . 'indoor_task_notifications';
    $wpdb->update(
        $notifications_table,
        array('is_read' => 1),
        array('user_id' => $current_user_id),
        array('%d'),
        array('%d')
    );
    
    // Redirect to prevent resubmission
    wp_redirect(remove_query_arg(array('action', 'nonce')));
    exit;
}

// Handle single notification mark as read
if (isset($_GET['mark_read']) && wp_verify_nonce($_GET['nonce'], 'tkm_mark_read_nonce')) {
    $notification_id = intval($_GET['mark_read']);
    $notifications_table = $wpdb->prefix . 'indoor_task_notifications';
    $wpdb->update(
        $notifications_table,
        array('is_read' => 1),
        array('id' => $notification_id, 'user_id' => $current_user_id),
        array('%d'),
        array('%d', '%d')
    );
    
    wp_redirect(remove_query_arg(array('mark_read', 'nonce')));
    exit;
}

// Pagination settings
$per_page = 15;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $per_page;

// Get notifications from database
$notifications = array();
$total_notifications = 0;
$unread_count = 0;

// Check if notifications table exists
$notifications_table = $wpdb->prefix . 'indoor_task_notifications';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$notifications_table'") === $notifications_table;

if ($table_exists) {
    // Get total count and unread count
    $total_notifications = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $notifications_table WHERE user_id = %d",
            $current_user_id
        )
    );
    
    $unread_count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $notifications_table WHERE user_id = %d AND is_read = 0",
            $current_user_id
        )
    );
    
    // Get notifications with pagination
    $notifications = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $notifications_table 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $current_user_id,
            $per_page,
            $offset
        )
    );
}

// Calculate pagination
$total_pages = ceil($total_notifications / $per_page);
$has_prev = $page > 1;
$has_next = $page < $total_pages;

// Notification type icons and colors
function get_notification_icon($type) {
    $icons = array(
        'task_completed' => 'fas fa-check-circle',
        'task_pending' => 'fas fa-clock',
        'task_rejected' => 'fas fa-times-circle',
        'withdrawal_approved' => 'fas fa-credit-card',
        'withdrawal_rejected' => 'fas fa-ban',
        'reward_granted' => 'fas fa-gift',
        'referral_successful' => 'fas fa-user-plus',
        'kyc_approved' => 'fas fa-id-card',
        'kyc_rejected' => 'fas fa-id-card',
        'system' => 'fas fa-cog',
        'announcement' => 'fas fa-bullhorn',
        'default' => 'fas fa-bell'
    );
    
    return isset($icons[$type]) ? $icons[$type] : $icons['default'];
}

function get_notification_color($type) {
    $colors = array(
        'task_completed' => 'success',
        'task_pending' => 'warning',
        'task_rejected' => 'danger',
        'withdrawal_approved' => 'success',
        'withdrawal_rejected' => 'danger',
        'reward_granted' => 'success',
        'referral_successful' => 'info',
        'kyc_approved' => 'success',
        'kyc_rejected' => 'danger',
        'system' => 'info',
        'announcement' => 'primary',
        'default' => 'info'
    );
    
    return isset($colors[$type]) ? $colors[$type] : $colors['default'];
}

function get_notification_title($type) {
    $titles = array(
        'task_completed' => 'Task Completed',
        'task_pending' => 'Task Pending Review',
        'task_rejected' => 'Task Rejected',
        'withdrawal_approved' => 'Withdrawal Approved',
        'withdrawal_rejected' => 'Withdrawal Rejected',
        'reward_granted' => 'Reward Granted',
        'referral_successful' => 'Referral Successful',
        'kyc_approved' => 'KYC Approved',
        'kyc_rejected' => 'KYC Rejected',
        'system' => 'System Notification',
        'announcement' => 'New Announcement',
        'default' => 'Notification'
    );
    
    return isset($titles[$type]) ? $titles[$type] : $titles['default'];
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo esc_html($page_title); ?> - <?php bloginfo('name'); ?></title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Notifications Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-notifications.css?ver=1.0.0">
    
    <?php wp_head(); ?>
</head>

<body class="tkm-door-notifications">
    <div class="tkm-wrapper">
        <!-- Include Sidebar Navigation -->
        <?php include_once(INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'); ?>
        
        <div class="tkm-main-content">
            <div class="tkm-container">
                <!-- Header Section -->
                <div class="tkm-header">
                    <div class="tkm-header-content">
                        <h1 class="tkm-title">
                            <?php echo esc_html($page_title); ?>
                            <?php if ($unread_count > 0): ?>
                                <span class="tkm-unread-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </h1>
                        <p class="tkm-subtitle">Stay updated with your latest activities and system notifications</p>
                    </div>
                    
                    <?php if ($unread_count > 0): ?>
                        <div class="tkm-header-actions">
                            <form method="post" class="tkm-mark-all-form">
                                <input type="hidden" name="action" value="mark_all_read">
                                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('tkm_notifications_nonce'); ?>">
                                <button type="submit" class="tkm-mark-all-btn">
                                    <i class="fas fa-check-double"></i>
                                    Mark All as Read
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Notifications Stats -->
                <div class="tkm-stats-section">
                    <div class="tkm-stats-grid">
                        <div class="tkm-stat-card">
                            <div class="tkm-stat-icon total">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="tkm-stat-info">
                                <div class="tkm-stat-number"><?php echo number_format($total_notifications); ?></div>
                                <div class="tkm-stat-label">Total Notifications</div>
                            </div>
                        </div>
                        
                        <div class="tkm-stat-card">
                            <div class="tkm-stat-icon unread">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="tkm-stat-info">
                                <div class="tkm-stat-number"><?php echo number_format($unread_count); ?></div>
                                <div class="tkm-stat-label">Unread</div>
                            </div>
                        </div>
                        
                        <div class="tkm-stat-card">
                            <div class="tkm-stat-icon read">
                                <i class="fas fa-envelope-open"></i>
                            </div>
                            <div class="tkm-stat-info">
                                <div class="tkm-stat-number"><?php echo number_format($total_notifications - $unread_count); ?></div>
                                <div class="tkm-stat-label">Read</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications Section -->
                <div class="tkm-notifications-section">
                    <?php if (!empty($notifications)): ?>
                        <div class="tkm-notifications-list">
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                $is_unread = !$notification->is_read;
                                $notification_type = $notification->type ?? 'default';
                                $icon_class = get_notification_icon($notification_type);
                                $color_class = get_notification_color($notification_type);
                                ?>
                                <div class="tkm-notification-item <?php echo $is_unread ? 'unread' : 'read'; ?>">
                                    <div class="tkm-notification-icon <?php echo $color_class; ?>">
                                        <i class="<?php echo $icon_class; ?>"></i>
                                    </div>
                                    
                                    <div class="tkm-notification-content">
                                        <div class="tkm-notification-header">
                                            <h4 class="tkm-notification-title">
                                                <?php 
                                                // Generate title based on notification type
                                                $notification_title = get_notification_title($notification->type);
                                                echo esc_html($notification_title); 
                                                ?>
                                                <?php if ($is_unread): ?>
                                                    <span class="tkm-new-badge">New</span>
                                                <?php endif; ?>
                                            </h4>
                                            <div class="tkm-notification-meta">
                                                <span class="tkm-notification-time">
                                                    <?php echo human_time_diff(strtotime($notification->created_at), current_time('timestamp')) . ' ago'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="tkm-notification-message">
                                            <?php echo wpautop(esc_html($notification->message)); ?>
                                        </div>
                                        
                                        <?php if (!empty($notification->action_url)): ?>
                                            <div class="tkm-notification-actions">
                                                <a href="<?php echo esc_url($notification->action_url); ?>" class="tkm-notification-action-btn">
                                                    <?php echo esc_html($notification->action_text ?: 'View Details'); ?>
                                                    <i class="fas fa-arrow-right"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="tkm-notification-controls">
                                        <?php if ($is_unread): ?>
                                            <a href="<?php echo add_query_arg(array('mark_read' => $notification->id, 'nonce' => wp_create_nonce('tkm_mark_read_nonce'))); ?>" 
                                               class="tkm-mark-read-btn" 
                                               title="Mark as read">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <div class="tkm-notification-date">
                                            <?php echo date('M d, Y', strtotime($notification->created_at)); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="tkm-pagination">
                                <div class="tkm-pagination-info">
                                    <span>Showing <?php echo (($page - 1) * $per_page + 1); ?> to <?php echo min($page * $per_page, $total_notifications); ?> of <?php echo $total_notifications; ?> notifications</span>
                                </div>
                                
                                <div class="tkm-pagination-controls">
                                    <?php if ($has_prev): ?>
                                        <a href="<?php echo add_query_arg('paged', $page - 1); ?>" class="tkm-pagination-btn tkm-prev">
                                            <i class="fas fa-chevron-left"></i>
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="tkm-pagination-numbers">
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        if ($start_page > 1): ?>
                                            <a href="<?php echo add_query_arg('paged', 1); ?>" class="tkm-pagination-number">1</a>
                                            <?php if ($start_page > 2): ?>
                                                <span class="tkm-pagination-dots">...</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <a href="<?php echo add_query_arg('paged', $i); ?>" 
                                               class="tkm-pagination-number <?php echo $i === $page ? 'active' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?>
                                                <span class="tkm-pagination-dots">...</span>
                                            <?php endif; ?>
                                            <a href="<?php echo add_query_arg('paged', $total_pages); ?>" class="tkm-pagination-number"><?php echo $total_pages; ?></a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($has_next): ?>
                                        <a href="<?php echo add_query_arg('paged', $page + 1); ?>" class="tkm-pagination-btn tkm-next">
                                            Next
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="tkm-empty-state">
                            <div class="tkm-empty-icon">
                                <i class="fas fa-bell-slash"></i>
                            </div>
                            <h3 class="tkm-empty-title">No Notifications Yet</h3>
                            <p class="tkm-empty-description">
                                You don't have any notifications at the moment. When there are updates about your tasks, withdrawals, or other activities, they'll appear here.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications JavaScript -->
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-notifications.js?ver=1.0.0"></script>
    
    <?php wp_footer(); ?>
</body>
</html>
