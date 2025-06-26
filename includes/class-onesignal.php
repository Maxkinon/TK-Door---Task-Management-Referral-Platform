<?php
/**
 * Class Indoor_Tasks_OneSignal
 * 
 * Handles OneSignal integration for PWA push notifications
 */
class Indoor_Tasks_OneSignal {
    /**
     * Constructor
     */
    public function __construct() {
        // Add OneSignal settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add OneSignal scripts
        add_action('wp_head', array($this, 'add_onesignal_script'), 5);
        
        // Add OneSignal initialization
        add_action('wp_footer', array($this, 'add_onesignal_init'), 99);
        
        // Add REST API endpoint for managing subscriptions
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Add filters to send notifications
        add_filter('indoor_tasks_new_task_notification', array($this, 'send_new_task_notification'), 10, 2);
        add_filter('indoor_tasks_task_approved_notification', array($this, 'send_task_approved_notification'), 10, 2);
    }
    
    /**
     * Register OneSignal settings
     */
    public function register_settings() {
        register_setting('indoor_tasks_settings', 'indoor_tasks_onesignal_app_id');
        register_setting('indoor_tasks_settings', 'indoor_tasks_onesignal_api_key');
        register_setting('indoor_tasks_settings', 'indoor_tasks_onesignal_safari_web_id');
        register_setting('indoor_tasks_settings', 'indoor_tasks_enable_push_notifications');
        register_setting('indoor_tasks_settings', 'indoor_tasks_onesignal_notification_types');
    }
    
    /**
     * Add OneSignal script to head
     */
    public function add_onesignal_script() {
        $enable_push = get_option('indoor_tasks_enable_push_notifications', 0);
        $app_id = get_option('indoor_tasks_onesignal_app_id', '');
        
        if (!$enable_push || empty($app_id)) {
            return;
        }
        
        $safari_web_id = get_option('indoor_tasks_onesignal_safari_web_id', '');
        ?>
        <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" async></script>
        <?php
    }
    
    /**
     * Add OneSignal initialization to footer
     */
    public function add_onesignal_init() {
        $enable_push = get_option('indoor_tasks_enable_push_notifications', 0);
        $app_id = get_option('indoor_tasks_onesignal_app_id', '');
        
        if (!$enable_push || empty($app_id)) {
            return;
        }
        
        $safari_web_id = get_option('indoor_tasks_onesignal_safari_web_id', '');
        $user_id = get_current_user_id();
        $user_data = array();
        
        if ($user_id) {
            $user = get_userdata($user_id);
            $user_data = array(
                'user_id' => $user_id,
                'email' => $user->user_email,
                'username' => $user->user_login
            );
        }
        ?>
        <script>
        window.OneSignal = window.OneSignal || [];
        OneSignal.push(function() {
            OneSignal.init({
                appId: "<?php echo esc_js($app_id); ?>",
                <?php if (!empty($safari_web_id)) : ?>
                safari_web_id: "<?php echo esc_js($safari_web_id); ?>",
                <?php endif; ?>
                notifyButton: {
                    enable: true,
                    size: 'medium',
                    position: 'bottom-right',
                    text: {
                        'tip.state.unsubscribed': 'Subscribe to notifications',
                        'tip.state.subscribed': 'You are subscribed to notifications',
                        'tip.state.blocked': 'You have blocked notifications',
                        'message.prenotify': 'Click to subscribe to notifications',
                        'message.action.subscribed': 'Thanks for subscribing!',
                        'message.action.resubscribed': 'You are subscribed to notifications',
                        'message.action.unsubscribed': 'You won\'t receive notifications again',
                        'dialog.main.title': 'Manage Site Notifications',
                        'dialog.main.button.subscribe': 'SUBSCRIBE',
                        'dialog.main.button.unsubscribe': 'UNSUBSCRIBE',
                        'dialog.blocked.title': 'Unblock Notifications',
                        'dialog.blocked.message': 'Follow these instructions to allow notifications:'
                    }
                },
                welcomeNotification: {
                    title: "Welcome to Indoor Tasks!",
                    message: "Thanks for subscribing to notifications"
                },
                promptOptions: {
                    slidedown: {
                        prompts: [
                            {
                                type: "push",
                                autoPrompt: true,
                                text: {
                                    actionMessage: "Would you like to receive notifications about new tasks and updates?",
                                    acceptButton: "Allow",
                                    cancelButton: "No Thanks"
                                },
                                delay: {
                                    pageViews: 1,
                                    timeDelay: 20
                                }
                            }
                        ]
                    }
                }
            });
            
            <?php if ($user_id) : ?>
            // Set user data for segmentation
            OneSignal.setExternalUserId("<?php echo esc_js($user_id); ?>");
            OneSignal.sendTag("user_id", "<?php echo esc_js($user_id); ?>");
            <?php endif; ?>
        });
        </script>
        <?php
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('indoor-tasks/v1', '/onesignal/subscribe', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_subscription'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Handle subscription
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_subscription($request) {
        $player_id = $request->get_param('player_id');
        $user_id = get_current_user_id();
        
        if (!$player_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Player ID is required'
            ), 400);
        }
        
        if ($user_id) {
            update_user_meta($user_id, 'indoor_tasks_onesignal_player_id', sanitize_text_field($player_id));
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Subscription updated'
            ));
        }
        
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'User not logged in'
        ), 401);
    }
    
    /**
     * Send notification using OneSignal API
     * 
     * @param array $data Notification data
     * @return bool|array Success status or API response
     */
    public function send_notification($data) {
        $enable_push = get_option('indoor_tasks_enable_push_notifications', 0);
        $app_id = get_option('indoor_tasks_onesignal_app_id', '');
        $api_key = get_option('indoor_tasks_onesignal_api_key', '');
        
        if (!$enable_push || empty($app_id) || empty($api_key)) {
            return false;
        }
        
        $fields = array(
            'app_id' => $app_id,
            'headings' => array('en' => $data['title']),
            'contents' => array('en' => $data['message']),
        );
        
        // Include target filters (segments, user IDs, etc.)
        if (!empty($data['filters'])) {
            $fields['filters'] = $data['filters'];
        }
        
        // Include specific user IDs
        if (!empty($data['include_player_ids'])) {
            $fields['include_player_ids'] = $data['include_player_ids'];
        }
        
        // Include URL if provided
        if (!empty($data['url'])) {
            $fields['url'] = $data['url'];
        }
        
        $response = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $api_key
            ),
            'body' => json_encode($fields),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
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
        
        $notification_types = get_option('indoor_tasks_onesignal_notification_types', array());
        
        if (!in_array('new_task', $notification_types)) {
            return false;
        }
        
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d",
            $task_id
        ));
        
        if (!$task) {
            return false;
        }
        
        $data = array(
            'title' => 'New Task Available',
            'message' => "Complete '{$task->title}' to earn {$task->reward_points} points!",
            'url' => site_url('/tasks/?task_id=' . $task_id),
            'filters' => array(
                array('field' => 'tag', 'key' => 'user_id', 'relation' => 'exists')
            )
        );
        
        return $this->send_notification($data);
    }
    
    /**
     * Send notification for an approved task
     * 
     * @param int $submission_id The submission ID
     * @param int $user_id The user ID
     * @return bool Whether the notification was sent successfully
     */
    public function send_task_approved_notification($submission_id, $user_id) {
        global $wpdb;
        
        $notification_types = get_option('indoor_tasks_onesignal_notification_types', array());
        
        if (!in_array('task_approved', $notification_types)) {
            return false;
        }
        
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, t.title, t.reward_points 
            FROM {$wpdb->prefix}indoor_task_submissions s
            JOIN {$wpdb->prefix}indoor_tasks t ON s.task_id = t.id
            WHERE s.id = %d",
            $submission_id
        ));
        
        if (!$submission) {
            return false;
        }
        
        $player_id = get_user_meta($user_id, 'indoor_tasks_onesignal_player_id', true);
        
        if (!$player_id) {
            return false;
        }
        
        $data = array(
            'title' => 'Task Approved',
            'message' => "Your submission for '{$submission->title}' has been approved! You've earned {$submission->reward_points} points.",
            'url' => site_url('/wallet/'),
            'include_player_ids' => array($player_id)
        );
        
        return $this->send_notification($data);
    }
}
