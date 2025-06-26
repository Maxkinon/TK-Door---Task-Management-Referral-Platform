<?php
/**
 * Withdrawals: request, admin approve, conversion
 * 
 * Handles withdrawal requests, approval, rejection, and notifications
 */
class Indoor_Tasks_Withdrawal {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_indoor_tasks_withdraw_request', array($this, 'withdraw_request'));
        add_action('admin_post_approve_withdrawal', array($this, 'approve_withdrawal'));
        add_action('admin_post_reject_withdrawal', array($this, 'reject_withdrawal'));
    }

    /**
     * Get withdrawal method details by name
     *
     * @param string $method_name The method name to find
     * @return array|false The method details or false if not found
     */
    private function get_withdrawal_method($method_name) {
        global $wpdb;
        
        // First try to get from the custom table
        $table_name = $wpdb->prefix . 'indoor_task_withdrawal_methods';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            $method = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE method = %s AND enabled = 1",
                    $method_name
                ),
                ARRAY_A
            );
            
            if ($method) {
                // Convert from database format to the format used in the code
                return array(
                    'name' => $method['method'],
                    'conversion' => $method['conversion_rate'],
                    'min_points' => $method['min_points'],
                    'input_fields' => !empty($method['custom_fields']) ? json_decode($method['custom_fields'], true) : array()
                );
            }
        }
        
        // Fallback to options table
        $methods = get_option('indoor_tasks_withdrawal_methods', array());
        
        foreach ($methods as $method) {
            if ($method['name'] === $method_name) {
                return $method;
            }
        }
        
        return false;
    }
    
    /**
     * Process withdrawal request from user
     */
    public function withdraw_request() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'indoor_tasks_withdraw')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }
        
        $user_id = get_current_user_id();
        $method = sanitize_text_field($_POST['method']);
        $points = intval($_POST['points']);
        $custom_fields = isset($_POST['custom_fields']) ? sanitize_textarea_field($_POST['custom_fields']) : '';
        
        global $wpdb;
        
        // Check if withdrawals are enabled
        $withdrawals_enabled = get_option('indoor_tasks_enable_withdrawals', 1);
        if (!$withdrawals_enabled) {
            wp_send_json_error(['message' => 'Withdrawals are currently disabled.']);
        }
        
        // Get available wallet balance
        $wallet = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
            $user_id
        ));
        
        if (empty($wallet) || $wallet < $points) {
            wp_send_json_error(['message' => 'Insufficient wallet balance.']);
        }
        
        // Get minimum withdrawal points
        $min_points = get_option('indoor_tasks_min_withdraw_points', 1000);
        if ($points < $min_points) {
            wp_send_json_error(['message' => sprintf('Minimum withdrawal amount is %d points.', $min_points)]);
        }
        
        // Get conversion rate for the selected method
        $method_info = $this->get_withdrawal_method($method);
        if (!$method_info) {
            wp_send_json_error(['message' => 'Invalid withdrawal method.']);
        }
        
        // Calculate converted amount
        $amount = $points / $method_info['conversion_rate'];
        
        // Insert withdrawal record
        $wpdb->insert(
            $wpdb->prefix . 'indoor_task_withdrawals',
            [
                'user_id' => $user_id,
                'method' => $method,
                'amount' => $amount,
                'points' => $points,
                'status' => 'pending',
                'custom_fields' => $custom_fields,
                'requested_at' => current_time('mysql')
            ]
        );
        
        if (!$wpdb->insert_id) {
            wp_send_json_error(['message' => 'Failed to create withdrawal request.']);
        }
        
        // Deduct points from wallet (marked as pending)
        $wpdb->insert(
            $wpdb->prefix . 'indoor_task_wallet',
            [
                'user_id' => $user_id,
                'points' => -$points,
                'type' => 'withdrawal',
                'reference_id' => $wpdb->insert_id,
                'description' => sprintf('Withdrawal request: %s', $method),
                'created_at' => current_time('mysql')
            ]
        );
        
        // Log the activity
        $wpdb->insert(
            $wpdb->prefix . 'indoor_task_user_activities',
            [
                'user_id' => $user_id,
                'activity_type' => 'withdrawal_request',
                'description' => sprintf('Withdrawal request submitted: %s - %d points', $method, $points),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => current_time('mysql')
            ]
        );
        
        // Send notification to user
        $wpdb->insert(
            $wpdb->prefix . 'indoor_task_notifications',
            [
                'user_id' => $user_id,
                'type' => 'withdrawal_request',
                'message' => sprintf('Your withdrawal request for %d points has been submitted.', $points),
                'created_at' => current_time('mysql')
            ]
        );
        
        wp_send_json_success(['message' => 'Withdrawal request submitted successfully.']);
    }
    
    /**
     * Approve a withdrawal request
     */
    public function approve_withdrawal() {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access.');
        }
        
        $withdrawal_id = isset($_POST['withdrawal_id']) ? intval($_POST['withdrawal_id']) : 0;
        if (!$withdrawal_id) {
            wp_die('Invalid withdrawal ID.');
        }
        
        global $wpdb;
        
        // Update withdrawal status
        $updated = $wpdb->update(
            $wpdb->prefix . 'indoor_task_withdrawals',
            [
                'status' => 'approved',
                'processed_at' => current_time('mysql')
            ],
            ['id' => $withdrawal_id]
        );
        
        if ($updated) {
            // Get withdrawal details
            $withdrawal = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}indoor_task_withdrawals WHERE id = %d",
                $withdrawal_id
            ));
            
            // Send notification to user
            $wpdb->insert(
                $wpdb->prefix . 'indoor_task_notifications',
                [
                    'user_id' => $withdrawal->user_id,
                    'type' => 'withdrawal_approved',
                    'message' => sprintf('Your withdrawal request for %d points has been approved.', $withdrawal->points),
                    'created_at' => current_time('mysql')
                ]
            );
            
            // Log the activity
            $wpdb->insert(
                $wpdb->prefix . 'indoor_task_user_activities',
                [
                    'user_id' => $withdrawal->user_id,
                    'activity_type' => 'withdrawal_approved',
                    'description' => sprintf('Withdrawal request approved: %s - %d points', $withdrawal->method, $withdrawal->points),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'created_at' => current_time('mysql')
                ]
            );
            
            // Trigger action for Telegram notification
            do_action('indoor_tasks_withdrawal_status_changed', $withdrawal_id, 'approved');
        }
        
        wp_redirect(admin_url('admin.php?page=indoor-tasks-withdrawal-requests&status=approved'));
        exit;
    }
    
    /**
     * Reject a withdrawal request
     */
    public function reject_withdrawal() {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access.');
        }
        
        $withdrawal_id = isset($_POST['withdrawal_id']) ? intval($_POST['withdrawal_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
        
        if (!$withdrawal_id) {
            wp_die('Invalid withdrawal ID.');
        }
        
        global $wpdb;
        
        // Get withdrawal details before updating
        $withdrawal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_task_withdrawals WHERE id = %d",
            $withdrawal_id
        ));
        
        if (!$withdrawal) {
            wp_die('Withdrawal request not found.');
        }
        
        // Update withdrawal status
        $updated = $wpdb->update(
            $wpdb->prefix . 'indoor_task_withdrawals',
            [
                'status' => 'rejected',
                'admin_reason' => $reason,
                'processed_at' => current_time('mysql')
            ],
            ['id' => $withdrawal_id]
        );
        
        if ($updated) {
            // Refund points to user's wallet
            $wpdb->insert(
                $wpdb->prefix . 'indoor_task_wallet',
                [
                    'user_id' => $withdrawal->user_id,
                    'points' => $withdrawal->points,
                    'type' => 'refund',
                    'reference_id' => $withdrawal_id,
                    'description' => 'Refund from rejected withdrawal request',
                    'created_at' => current_time('mysql')
                ]
            );
            
            // Send notification to user
            $wpdb->insert(
                $wpdb->prefix . 'indoor_task_notifications',
                [
                    'user_id' => $withdrawal->user_id,
                    'type' => 'withdrawal_rejected',
                    'message' => sprintf('Your withdrawal request for %d points has been rejected. Reason: %s', $withdrawal->points, $reason),
                    'created_at' => current_time('mysql')
                ]
            );
            
            // Log the activity
            $wpdb->insert(
                $wpdb->prefix . 'indoor_task_user_activities',
                [
                    'user_id' => $withdrawal->user_id,
                    'activity_type' => 'withdrawal_rejected',
                    'description' => sprintf('Withdrawal request rejected: %s - %d points. Reason: %s', $withdrawal->method, $withdrawal->points, $reason),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'created_at' => current_time('mysql')
                ]
            );
            
            // Trigger action for Telegram notification
            do_action('indoor_tasks_withdrawal_status_changed', $withdrawal_id, 'rejected');
        }
        
        wp_redirect(admin_url('admin.php?page=indoor-tasks-withdrawal-requests&status=rejected'));
        exit;
    }
}
