<?php
// Helper functions for Indoor Tasks plugin

function indoor_tasks_is_kyc_approved($user_id) {
    global $wpdb;
    $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}indoor_task_kyc WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id));
    return $status === 'approved';
}

function indoor_tasks_get_wallet_points($user_id) {
    global $wpdb;
    $points = $wpdb->get_var($wpdb->prepare("SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d", $user_id));
    return intval($points);
}

/**
 * Get task categories
 * 
 * @return array Array of task categories
 */
function indoor_tasks_get_categories() {
    global $wpdb;
    
    // Check if the categories table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_categories'") === $wpdb->prefix . 'indoor_task_categories';
    
    if (!$table_exists) {
        return array();
    }
    
    // Get all categories
    $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}indoor_task_categories ORDER BY name ASC");
    
    return $categories;
}

/**
 * Get task category by ID
 * 
 * @param int $category_id Category ID
 * @return object|null Category object if found, null otherwise
 */
function indoor_tasks_get_category($category_id) {
    global $wpdb;
    
    // Check if the categories table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_categories'") === $wpdb->prefix . 'indoor_task_categories';
    
    if (!$table_exists) {
        return null;
    }
    
    // Get category by ID
    $category = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_categories WHERE id = %d",
        $category_id
    ));
    
    return $category;
}

/**
 * Get unread notifications count for current user
 * 
 * @param int $user_id User ID (defaults to current user)
 * @return int Number of unread notifications
 */
function indoor_tasks_get_unread_notifications_count($user_id = null) {
    if (null === $user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return 0;
    }
    
    global $wpdb;
    
    // Check if the table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_notifications'") === $wpdb->prefix . 'indoor_task_notifications';
    
    if (!$table_exists) {
        return 0;
    }
    
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_notifications 
         WHERE user_id = %d AND is_read = 0",
        $user_id
    ));
}

/**
 * Add a notification for a user
 * 
 * @param int    $user_id      User ID to add notification for
 * @param string $title        Notification title
 * @param string $message      Notification message
 * @param string $type         Notification type (task, payment, etc.)
 * @param int    $reference_id Optional reference ID (task ID, submission ID, etc.)
 * @return int|false           Notification ID if successful, false on failure
 */
function indoor_tasks_add_notification($user_id, $title, $message, $type = 'general', $reference_id = 0) {
    if (!$user_id) {
        return false;
    }
    
    global $wpdb;
    
    // Check if the table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_notifications'") === $wpdb->prefix . 'indoor_task_notifications';
    
    if (!$table_exists) {
        return false;
    }
    
    // Insert notification
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'indoor_task_notifications',
        array(
            'user_id'      => $user_id,
            'title'        => $title,
            'message'      => $message,
            'type'         => $type,
            'reference_id' => $reference_id,
            'is_read'      => 0,
            'created_at'   => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s', '%d', '%d', '%s')
    );
    
    if ($inserted) {
        return $wpdb->insert_id;
    }
    
    return false;
}

/**
 * Create notifications table if it doesn't exist
 */
function indoor_tasks_create_notifications_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'indoor_task_notifications';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    
    if (!$table_exists) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'general',
            reference_id BIGINT UNSIGNED DEFAULT 0,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (type),
            INDEX (is_read)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return true;
    }
    
    return false;
}

/**
 * Check if notifications feature is available
 * 
 * @return bool True if notifications feature is available
 */
function indoor_tasks_notifications_available() {
    global $wpdb;
    return $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_notifications'") === $wpdb->prefix . 'indoor_task_notifications';
}

/**
 * Send announcement to users
 * 
 * @param int $announcement_id Announcement ID
 * @return bool True if sent successfully
 */
function indoor_tasks_send_announcement($announcement_id) {
    global $wpdb;
    
    // Get announcement details
    $announcement = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_announcements WHERE id = %d",
        $announcement_id
    ));
    
    if (!$announcement) {
        return false;
    }
    
    // Get target users
    $users = indoor_tasks_get_announcement_users($announcement->target_audience);
    
    if (empty($users)) {
        return false;
    }
    
    $success = true;
    $sent_count = 0;
    $failed_count = 0;
    
    foreach ($users as $user) {
        $user_success = true;
        
        // Send email notification
        if ($announcement->send_email) {
            if (!indoor_tasks_send_announcement_email($user, $announcement)) {
                $user_success = false;
            }
        }
        
        // Send push notification
        if ($announcement->send_push) {
            if (!indoor_tasks_send_announcement_push($user, $announcement)) {
                $user_success = false;
            }
        }
        
        // Add to user notifications
        indoor_tasks_add_user_notification($user->ID, $announcement->title, $announcement->message, 'announcement');
        
        if ($user_success) {
            $sent_count++;
        } else {
            $failed_count++;
            $success = false;
        }
    }
    
    // Send to Telegram channel if enabled
    if ($announcement->send_telegram) {
        indoor_tasks_send_announcement_telegram($announcement);
    }
    
    // Update announcement status
    $status = $success ? 'sent' : ($sent_count > 0 ? 'partial' : 'failed');
    $wpdb->update(
        $wpdb->prefix . 'indoor_task_announcements',
        [
            'status' => $status,
            'sent_at' => current_time('mysql'),
            'sent_count' => $sent_count,
            'failed_count' => $failed_count
        ],
        ['id' => $announcement_id],
        ['%s', '%s', '%d', '%d'],
        ['%d']
    );
    
    return $success;
}

/**
 * Get users based on target audience
 * 
 * @param string $target_audience Target audience type
 * @return array Array of user objects
 */
function indoor_tasks_get_announcement_users($target_audience) {
    global $wpdb;
    
    switch ($target_audience) {
        case 'all':
            return $wpdb->get_results("SELECT ID, user_email, display_name FROM {$wpdb->users}");
            
        case 'verified':
            return $wpdb->get_results("
                SELECT DISTINCT u.ID, u.user_email, u.display_name 
                FROM {$wpdb->users} u 
                INNER JOIN {$wpdb->prefix}indoor_task_kyc k ON u.ID = k.user_id 
                WHERE k.status = 'approved'
            ");
            
        case 'active':
            return $wpdb->get_results("
                SELECT DISTINCT u.ID, u.user_email, u.display_name 
                FROM {$wpdb->users} u 
                INNER JOIN {$wpdb->prefix}indoor_task_submissions s ON u.ID = s.user_id 
                WHERE s.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
        case 'new':
            return $wpdb->get_results("
                SELECT ID, user_email, display_name 
                FROM {$wpdb->users} 
                WHERE user_registered >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            
        default:
            return [];
    }
}

/**
 * Send announcement email to user
 * 
 * @param object $user User object
 * @param object $announcement Announcement object
 * @return bool True if sent successfully
 */
function indoor_tasks_send_announcement_email($user, $announcement) {
    $subject = '[' . get_bloginfo('name') . '] ' . $announcement->title;
    
    $message = "
    <html>
    <body>
        <h2>{$announcement->title}</h2>
        <p>Hello {$user->display_name},</p>
        <p>" . nl2br(esc_html($announcement->message)) . "</p>
        <hr>
        <p><small>This is an automated message from " . get_bloginfo('name') . ".</small></p>
    </body>
    </html>
    ";
    
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    ];
    
    return wp_mail($user->user_email, $subject, $message, $headers);
}

/**
 * Send announcement push notification to user
 * 
 * @param object $user User object
 * @param object $announcement Announcement object
 * @return bool True if sent successfully
 */
function indoor_tasks_send_announcement_push($user, $announcement) {
    // Check if OneSignal or Firebase is configured
    $onesignal_enabled = get_option('indoor_tasks_onesignal_enabled', false);
    $firebase_enabled = get_option('indoor_tasks_firebase_enabled', false);
    
    if (!$onesignal_enabled && !$firebase_enabled) {
        return false;
    }
    
    $notification_data = [
        'title' => $announcement->title,
        'message' => $announcement->message,
        'type' => 'announcement',
        'user_id' => $user->ID
    ];
    
    // Try OneSignal first
    if ($onesignal_enabled && class_exists('Indoor_Tasks_OneSignal')) {
        $onesignal = new Indoor_Tasks_OneSignal();
        if (method_exists($onesignal, 'send_notification')) {
            return $onesignal->send_notification($user->ID, $notification_data);
        }
    }
    
    // Try Firebase
    if ($firebase_enabled && class_exists('Indoor_Tasks_Firebase')) {
        $firebase = new Indoor_Tasks_Firebase();
        if (method_exists($firebase, 'send_notification')) {
            return $firebase->send_notification($user->ID, $notification_data);
        }
    }
    
    return false;
}

/**
 * Send announcement to Telegram channel
 * 
 * @param object $announcement Announcement object
 * @return bool True if sent successfully
 */
function indoor_tasks_send_announcement_telegram($announcement) {
    if (!class_exists('Indoor_Tasks_Telegram')) {
        return false;
    }
    
    $telegram = new Indoor_Tasks_Telegram();
    
    if (!method_exists($telegram, 'send_channel_message')) {
        return false;
    }
    
    $message = "🔔 *{$announcement->title}*\n\n{$announcement->message}";
    
    return $telegram->send_channel_message($message);
}

/**
 * Add notification to user's notification list
 * 
 * @param int $user_id User ID
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type
 * @return bool True if added successfully
 */
function indoor_tasks_add_user_notification($user_id, $title, $message, $type = 'general') {
    global $wpdb;
    
    if (!indoor_tasks_notifications_available()) {
        return false;
    }
    
    return $wpdb->insert(
        $wpdb->prefix . 'indoor_task_notifications',
        [
            'user_id' => $user_id,
            'type' => $type,
            'message' => $title . ': ' . $message,
            'is_read' => 0,
            'created_at' => current_time('mysql')
        ],
        ['%d', '%s', '%s', '%d', '%s']
    ) !== false;
}

/**
 * Process scheduled announcements
 * This function should be called by a cron job
 */
function indoor_tasks_process_scheduled_announcements() {
    global $wpdb;
    
    // Get announcements scheduled for now or earlier
    $scheduled_announcements = $wpdb->get_results("
        SELECT id FROM {$wpdb->prefix}indoor_task_announcements 
        WHERE status = 'scheduled' 
        AND schedule_time <= NOW()
    ");
    
    foreach ($scheduled_announcements as $announcement) {
        indoor_tasks_send_announcement($announcement->id);
    }
}

/**
 * Get announcement statistics
 * 
 * @return array Statistics array
 */
function indoor_tasks_get_announcement_stats() {
    global $wpdb;
    
    $stats = [];
    
    // Total announcements
    $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_announcements");
    
    // By status
    $stats['sent'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_announcements WHERE status = 'sent'");
    $stats['scheduled'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_announcements WHERE status = 'scheduled'");
    $stats['failed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_announcements WHERE status = 'failed'");
    
    // Total recipients reached
    $stats['total_sent'] = $wpdb->get_var("SELECT SUM(sent_count) FROM {$wpdb->prefix}indoor_task_announcements");
    
    return $stats;
}

/**
 * Calculate user level based on completed tasks
 * This is a fallback function for templates that expect this function
 * 
 * @param int $user_id User ID
 * @return int User level
 */
function indoor_tasks_calculate_user_level($user_id) {
    // Use the existing Indoor_Tasks_Levels class if available
    if (class_exists('Indoor_Tasks_Levels')) {
        $level_name = Indoor_Tasks_Levels::get_user_level($user_id);
        // Extract number from level name if it exists
        if (preg_match('/(\d+)/', $level_name, $matches)) {
            return intval($matches[1]);
        }
        // Return level ID if numeric
        $user_level_id = get_user_meta($user_id, 'indoor_tasks_user_level', true);
        if ($user_level_id && is_numeric($user_level_id)) {
            return intval($user_level_id);
        }
    }
    
    // Fallback calculation using safe database functions
    $completed_tasks = indoor_tasks_get_safe_completed_tasks($user_id);
    
    return max(1, floor($completed_tasks / 10) + 1); // 10 tasks per level
}

/**
 * Get next level requirement for a user
 * 
 * @param int $user_level Current user level
 * @return array Array with current and required values
 */
function indoor_tasks_get_next_level_requirement($user_level) {
    $user_id = get_current_user_id();
    
    $completed_tasks = indoor_tasks_get_safe_completed_tasks($user_id);
    
    return array(
        'current' => $completed_tasks,
        'required' => ($user_level * 10) // 10 tasks per level
    );
}

/**
 * Get user level string name
 * 
 * @param int $user_id User ID
 * @return string Level name
 */
function indoor_tasks_get_user_level($user_id) {
    return indoor_tasks_get_safe_user_level($user_id);
}

/**
 * Get user level progress
 * 
 * @param int $user_id User ID
 * @return array Progress data
 */
function indoor_tasks_get_level_progress($user_id) {
    $completed_tasks = indoor_tasks_get_safe_completed_tasks($user_id);
    
    $current_level = indoor_tasks_calculate_user_level($user_id);
    $tasks_for_current_level = ($current_level - 1) * 10;
    $tasks_for_next_level = $current_level * 10;
    
    return array(
        'current' => max(0, $completed_tasks - $tasks_for_current_level),
        'required' => $tasks_for_next_level - $tasks_for_current_level
    );
}

/**
 * Get KYC status for a user
 * 
 * @param int $user_id User ID
 * @return string KYC status
 */
function indoor_tasks_get_kyc_status($user_id) {
    return indoor_tasks_get_safe_kyc_status($user_id);
}

/**
 * Get comprehensive user statistics
 * 
 * @param int $user_id User ID
 * @return array User statistics
 */
function indoor_tasks_get_comprehensive_user_stats($user_id) {
    return array(
        'total_tasks' => indoor_tasks_get_safe_completed_tasks($user_id) + indoor_tasks_get_safe_pending_tasks($user_id),
        'completed_tasks' => indoor_tasks_get_safe_completed_tasks($user_id),
        'pending_tasks' => indoor_tasks_get_safe_pending_tasks($user_id),
        'total_points' => indoor_tasks_get_safe_wallet_points($user_id),
        'total_referrals' => indoor_tasks_get_safe_referral_count($user_id)
    );
}

/**
 * Get level points required for a specific level
 * 
 * @param int $level Level number
 * @return int Points required
 */
function indoor_tasks_get_level_points_required($level) {
    // Simple calculation: each level requires 100 more points than the previous
    return $level * 100;
}

/**
 * Get user total points
 * 
 * @param int $user_id User ID
 * @return int Total points
 */
function indoor_tasks_get_user_points($user_id) {
    return indoor_tasks_get_safe_wallet_points($user_id);
}
