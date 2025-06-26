<?php
/**
 * Notifications: in-app, email, admin
 * 
 * Handles in-app notifications, email notifications, and admin notifications
 */
class Indoor_Tasks_Notifications {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('template_include', [$this, 'route_notifications_template']);
        add_action('wp_ajax_indoor_tasks_fetch_notifications', [$this, 'fetch_notifications']);
        add_action('indoor_tasks_submission_approved', [$this, 'notify_task_approved'], 10, 2);
        add_action('indoor_tasks_submission_rejected', [$this, 'notify_task_rejected'], 10, 2);
        add_action('indoor_tasks_kyc_status_changed', [$this, 'notify_kyc_status_changed'], 10, 2);
        add_action('indoor_tasks_withdrawal_status_changed', [$this, 'notify_withdrawal_status_changed'], 10, 2);
        add_action('indoor_tasks_level_changed', [$this, 'notify_level_changed'], 10, 3);
    }
    
    /**
     * Route notifications template
     * 
     * @param string $template The template path
     * @return string The modified template path
     */
    public function route_notifications_template($template) {
        if (is_page_template('notifications.php')) {
            return INDOOR_TASKS_PATH . 'templates/notifications.php';
        }
        return $template;
    }
    
    /**
     * Fetch notifications for the current user
     */
    public function fetch_notifications() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'User not logged in']);
            return;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_task_notifications 
            WHERE user_id = %d OR user_id = 0 
            ORDER BY created_at DESC 
            LIMIT 20",
            $user_id
        ));
        
        // Mark notifications as read
        if (!empty($notifications)) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}indoor_task_notifications 
                SET is_read = 1 
                WHERE user_id = %d AND is_read = 0",
                $user_id
            ));
        }
        
        wp_send_json_success(['notifications' => $notifications]);
    }
    
    /**
     * Create a notification
     * 
     * @param int $user_id The user ID
     * @param string $type The notification type
     * @param string $message The notification message
     * @param int $reference_id Optional reference ID
     * @return int|false The notification ID or false on failure
     */
    public function create_notification($user_id, $type, $message, $reference_id = 0) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'indoor_task_notifications',
            [
                'user_id' => $user_id,
                'type' => $type,
                'message' => $message,
                'reference_id' => $reference_id,
                'is_read' => 0,
                'created_at' => current_time('mysql')
            ]
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Send an email notification
     * 
     * @param string $to The recipient email
     * @param string $subject The email subject
     * @param string $message The email message
     * @return bool Whether the email was sent successfully
     */
    public function send_email($to, $subject, $message) {
        $enable_email = get_option('indoor_tasks_enable_email_notify', 1);
        if (!$enable_email) {
            return false;
        }
        
        $sender_name = get_option('indoor_tasks_email_sender_name', get_bloginfo('name'));
        $sender_email = get_option('indoor_tasks_email_sender_address', get_bloginfo('admin_email'));
        
        $headers = [
            'From: ' . $sender_name . ' <' . $sender_email . '>',
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Notify user when their task is approved
     * 
     * @param int $user_id The user ID
     * @param int $submission_id The submission ID
     */
    public function notify_task_approved($user_id, $submission_id) {
        // Implementation here
    }
    
    /**
     * Notify user when their task is rejected
     * 
     * @param int $user_id The user ID
     * @param int $submission_id The submission ID
     */
    public function notify_task_rejected($user_id, $submission_id) {
        // Implementation here
    }
    
    /**
     * Notify user when their KYC status changes
     * 
     * @param int $user_id The user ID
     * @param string $status The new status
     */
    public function notify_kyc_status_changed($user_id, $status) {
        // Implementation here
    }
    
    /**
     * Notify user when their withdrawal status changes
     * 
     * @param int $user_id The user ID
     * @param int $withdrawal_id The withdrawal ID
     */
    public function notify_withdrawal_status_changed($user_id, $withdrawal_id) {
        // Implementation here
    }
    
    /**
     * Notify user when their level changes
     * 
     * @param int $user_id The user ID
     * @param string $old_level The old level
     * @param string $new_level The new level
     */
    public function notify_level_changed($user_id, $old_level, $new_level) {
        // Implementation here
    }
    
    /**
     * Send a KYC reminder to a user
     * 
     * @param int $user_id The user ID
     * @return bool Whether the notification was sent successfully
     */
    public function send_kyc_reminder($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Create in-app notification
        $notification_id = $this->create_notification(
            $user_id,
            'kyc_reminder',
            __('Please complete your KYC verification to enable withdrawals.', 'indoor-tasks')
        );
        
        // Send email notification
        $template = get_option('indoor_tasks_notify_kyc', 'Your KYC verification is pending. Please complete it to enable withdrawals.');
        $subject = __('KYC Verification Reminder', 'indoor-tasks');
        
        $email_sent = $this->send_email($user->user_email, $subject, $template);
        
        // Return true if either notification was successful
        return ($notification_id || $email_sent);
    }
}
