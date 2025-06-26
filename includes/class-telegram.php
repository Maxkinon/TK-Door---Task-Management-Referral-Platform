<?php
/**
 * Class Indoor_Tasks_Telegram
 * 
 * Handles Telegram integration for sending notifications
 */
class Indoor_Tasks_Telegram {
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into task creation
        add_action('indoor_tasks_new_task_notification', array($this, 'send_new_task_notification'), 10, 2);
        add_action('indoor_tasks_telegram_notification', array($this, 'send_new_task_notification'), 10, 2);
        
        // Hook into task completion
        add_action('indoor_tasks_task_completed', array($this, 'send_task_completion_notification'), 10, 2);
        
        // Hook into level change
        add_action('indoor_tasks_level_changed', array($this, 'send_level_change_notification'), 10, 3);
        
        // Hook into withdrawal status changes
        add_action('indoor_tasks_withdrawal_status_changed', array($this, 'send_withdrawal_notification'), 10, 2);
    }
    
    /**
     * Send a notification to Telegram
     *
     * @param string $message The message to send
     * @param array $options Additional options for the message
     * @return bool Whether the message was sent successfully
     */
    public function send_notification($message, $options = []) {
        $telegram_enabled = get_option('indoor_tasks_telegram_enabled', 0);
        $bot_token = get_option('indoor_tasks_telegram_bot_token', '');
        $chat_id = get_option('indoor_tasks_telegram_chat_id', '');
        
        if (!$telegram_enabled || empty($bot_token) || empty($chat_id)) {
            return false;
        }
        
        // Set up the request body
        $body = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => isset($options['parse_mode']) ? $options['parse_mode'] : 'Markdown'
        ];
        
        // Add optional parameters if provided
        if (isset($options['disable_web_page_preview'])) {
            $body['disable_web_page_preview'] = $options['disable_web_page_preview'];
        }
        
        if (isset($options['disable_notification'])) {
            $body['disable_notification'] = $options['disable_notification'];
        }
        
        // Send the request to Telegram API
        $response = wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body' => $body
        ]);
        
        // Log the attempt
        if (isset($options['log_activity']) && $options['log_activity']) {
            global $wpdb;
            $wpdb->insert($wpdb->prefix.'indoor_task_user_activities', [
                'user_id' => isset($options['user_id']) ? intval($options['user_id']) : 0,
                'activity_type' => 'telegram_notification',
                'description' => sprintf('Telegram notification sent: %s', substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '')),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => current_time('mysql')
            ]);
        }
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200;
    }
    
    /**
     * Send notification for a new task
     *
     * @param int $task_id The task ID
     * @param string $task_title The task title
     * @return bool Whether the notification was sent successfully
     */
    public function send_new_task_notification($task_id, $task_title) {
        global $wpdb;
        
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d",
            $task_id
        ));
        
        if (!$task) {
            return false;
        }
        
        // Check notification settings
        $notify_new_tasks = get_option('indoor_tasks_notify_new_tasks', 1);
        $notify_featured_tasks = get_option('indoor_tasks_notify_featured_tasks', 1);
        
        // If notifications are disabled and it's not a featured task that should always be notified
        if (!$notify_new_tasks && !($notify_featured_tasks && !empty($task->featured) && $task->featured)) {
            return false;
        }
        
        $template = get_option('indoor_tasks_telegram_new_task_template', 
            "🔔 *NEW TASK AVAILABLE*\n\n*{{title}}*\n\n📝 {{description}}\n\n💰 Reward: {{reward}} points\n\n⏱️ Deadline: {{deadline}}\n\n🏆 Level: {{level}}\n\nComplete now to earn points!"
        );
        
        // Get the difficulty level name
        $difficulty = !empty($task->difficulty_level) ? ucfirst($task->difficulty_level) : 'Medium';
        
        // Get the category name if available
        $category_name = '';
        if (!empty($task->category)) {
            $category = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}indoor_task_categories WHERE id = %d",
                $task->category
            ));
            $category_name = !empty($category) ? $category : $task->category;
        }
        
        // Replace placeholders with actual data
        $replacements = [
            '{{title}}' => $task->title,
            '{{description}}' => wp_trim_words($task->description, 30, '...'),
            '{{reward}}' => $task->reward_points,
            '{{deadline}}' => date('M j, Y', strtotime($task->deadline)),
            '{{category}}' => $category_name,
            '{{level}}' => $difficulty,
            '{{featured}}' => !empty($task->featured) && $task->featured ? '⭐ Featured Task' : '',
        ];
        
        $message = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        // Set options for the notification
        $options = [
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
            'log_activity' => true
        ];
        
        return $this->send_notification($message, $options);
    }
    
    /**
     * Send notification for user level change
     *
     * @param int $user_id The user ID
     * @param string $old_level The old level name
     * @param string $new_level The new level name
     * @return bool Whether the notification was sent successfully
     */
    public function send_level_change_notification($user_id, $old_level, $new_level) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        $template = get_option('indoor_tasks_telegram_level_change_template', 
            "🎉 *USER LEVEL UPGRADED*\n\nUser: {{username}}\nOld Level: {{old_level}}\nNew Level: {{new_level}}\n\nCongratulations on reaching a higher level!"
        );
        
        // Replace placeholders with actual data
        $replacements = [
            '{{username}}' => $user->display_name,
            '{{user_id}}' => $user_id,
            '{{old_level}}' => $old_level,
            '{{new_level}}' => $new_level,
            '{{date}}' => date('M j, Y'),
        ];
        
        $message = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        // Set options for the notification
        $options = [
            'parse_mode' => 'Markdown',
            'log_activity' => true,
            'user_id' => $user_id
        ];
        
        return $this->send_notification($message, $options);
    }
    
    /**
     * Send notification for withdrawal status change
     *
     * @param int $withdrawal_id The withdrawal ID
     * @param string $status The new status (approved/rejected)
     * @return bool Whether the notification was sent successfully
     */
    public function send_withdrawal_notification($withdrawal_id, $status) {
        global $wpdb;
        
        $withdrawal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_task_withdrawals WHERE id = %d",
            $withdrawal_id
        ));
        
        if (!$withdrawal) {
            return false;
        }
        
        $user = get_userdata($withdrawal->user_id);
        if (!$user) {
            return false;
        }
        
        $template = get_option('indoor_tasks_telegram_withdrawal_template', 
            "💰 *WITHDRAWAL {{status}}*\n\nUser: {{username}}\nAmount: {{amount}}\nMethod: {{method}}\nDate: {{date}}"
        );
        
        // Format the status text
        $status_text = ($status === 'approved') ? 'APPROVED' : 'REJECTED';
        
        // Replace placeholders with actual data
        $replacements = [
            '{{username}}' => $user->display_name,
            '{{user_id}}' => $withdrawal->user_id,
            '{{amount}}' => $withdrawal->amount,
            '{{points}}' => $withdrawal->points,
            '{{method}}' => $withdrawal->method,
            '{{date}}' => date('M j, Y'),
            '{{status}}' => $status_text,
        ];
        
        $message = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        // Set options for the notification
        $options = [
            'parse_mode' => 'Markdown',
            'log_activity' => true,
            'user_id' => $withdrawal->user_id
        ];
        
        return $this->send_notification($message, $options);
    }
    
    /**
     * Send notification for task completion
     *
     * @param int $task_id The task ID
     * @param int $user_id The user ID who completed the task
     * @return bool Whether the notification was sent successfully
     */
    public function send_task_completion_notification($task_id, $user_id) {
        global $wpdb;
        
        // Check if task completion notifications are enabled
        $notify_task_completion = get_option('indoor_tasks_notify_task_completion', 0);
        if (!$notify_task_completion) {
            return false;
        }
        
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d",
            $task_id
        ));
        
        if (!$task) {
            return false;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $template = get_option('indoor_tasks_telegram_task_completion_template', 
            "✅ *TASK COMPLETED*\n\nTask: {{title}}\nCompleted by: {{username}}\nPoints Earned: {{reward}}\nDate: {{date}}"
        );
        
        // Replace placeholders with actual data
        $replacements = [
            '{{title}}' => $task->title,
            '{{username}}' => $user->display_name,
            '{{user_id}}' => $user_id,
            '{{reward}}' => $task->reward_points,
            '{{date}}' => date('M j, Y H:i'),
        ];
        
        $message = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        // Set options for the notification
        $options = [
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
            'log_activity' => true,
            'user_id' => $user_id
        ];
        
        return $this->send_notification($message, $options);
    }
}
