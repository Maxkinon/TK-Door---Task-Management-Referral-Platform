<?php
/**
 * User activity tracking
 *
 * This file contains functions for tracking user activities throughout the plugin
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Log user activity
 *
 * @param int $user_id User ID
 * @param string $activity_type Type of activity (e.g., login, task_submission, level_change)
 * @param string $description Description of the activity
 * @param array $metadata Additional metadata for the activity
 * @return int|bool The activity ID on success, false on failure
 */
function indoor_tasks_log_activity($user_id, $activity_type, $description, $metadata = []) {
    global $wpdb;
    
    // Get user IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Convert metadata to JSON
    $metadata_json = !empty($metadata) ? json_encode($metadata) : null;
    
    // Insert activity into the database
    $result = $wpdb->insert(
        $wpdb->prefix . 'indoor_task_user_activities',
        [
            'user_id' => $user_id,
            'activity_type' => $activity_type,
            'description' => $description,
            'metadata' => $metadata_json,
            'ip_address' => $ip_address,
            'created_at' => current_time('mysql')
        ]
    );
    
    if ($result) {
        return $wpdb->insert_id;
    }
    
    return false;
}

/**
 * Clean up old activity logs
 * 
 * @param int $days Number of days to keep activity logs
 * @return int|bool Number of rows deleted or false on failure
 */
function indoor_tasks_cleanup_activity_logs($days = 90) {
    global $wpdb;
    
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    return $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}indoor_task_user_activities 
         WHERE created_at < %s",
        $cutoff_date
    ));
}

/**
 * Export user activities to CSV
 * 
 * @param array $args Query arguments
 * @return string CSV content
 */
function indoor_tasks_export_activities($args = []) {
    global $wpdb;
    
    $defaults = array(
        'start_date' => '',
        'end_date' => '',
        'activity_type' => '',
        'user_id' => 0
    );
    
    $args = wp_parse_args($args, $defaults);
    
    // Build query conditions
    $where = [];
    $query_params = [];
    
    if (!empty($args['start_date'])) {
        $where[] = 'created_at >= %s';
        $query_params[] = $args['start_date'];
    }
    
    if (!empty($args['end_date'])) {
        $where[] = 'created_at <= %s';
        $query_params[] = $args['end_date'];
    }
    
    if (!empty($args['activity_type'])) {
        $where[] = 'activity_type = %s';
        $query_params[] = $args['activity_type'];
    }
    
    if (!empty($args['user_id'])) {
        $where[] = 'user_id = %d';
        $query_params[] = $args['user_id'];
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get activities
    $activities = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT a.*, u.user_login, u.user_email 
             FROM {$wpdb->prefix}indoor_task_user_activities a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             $where_clause
             ORDER BY created_at DESC",
            $query_params
        )
    );
    
    // Prepare CSV content
    $csv = fopen('php://temp', 'r+');
    
    // Add headers
    fputcsv($csv, array(
        'Date & Time',
        'User',
        'Email',
        'Activity Type',
        'Description',
        'IP Address',
        'Metadata'
    ));
    
    // Add rows
    foreach ($activities as $activity) {
        fputcsv($csv, array(
            $activity->created_at,
            $activity->user_login,
            $activity->user_email,
            $activity->activity_type,
            $activity->description,
            $activity->ip_address,
            $activity->metadata
        ));
    }
    
    // Get CSV content
    rewind($csv);
    $content = stream_get_contents($csv);
    fclose($csv);
    
    return $content;
}

/**
 * Get user activities
 * 
 * @param int $user_id User ID
 * @param int $limit Number of activities to retrieve
 * @param int $offset Offset for pagination
 * @param string $activity_type Filter by activity type
 * @return array Array of activity objects
 */
function indoor_tasks_get_user_activities($user_id, $limit = 10, $offset = 0, $activity_type = null) {
    global $wpdb;
    
    $query = "SELECT * FROM {$wpdb->prefix}indoor_task_user_activities 
              WHERE user_id = %d";
    $params = [$user_id];
    
    if ($activity_type) {
        $query .= " AND activity_type = %s";
        $params[] = $activity_type;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $params[] = $limit;
    $params[] = $offset;
    
    return $wpdb->get_results(
        $wpdb->prepare($query, $params)
    );
}

/**
 * Get recent activities for admin
 *
 * @param int $limit Number of activities to retrieve
 * @return array Array of activity objects with user details
 */
function indoor_tasks_get_recent_activities($limit = 20) {
    global $wpdb;
    
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT a.*, u.user_login, u.user_email 
             FROM {$wpdb->prefix}indoor_task_user_activities a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             ORDER BY a.created_at DESC 
             LIMIT %d",
            $limit
        )
    );
}

// Action hooks for tracking various activities
add_action('wp_login', function($user_login, $user) {
    indoor_tasks_log_activity($user->ID, 'login', 'User logged in');
}, 10, 2);

add_action('indoor_tasks_submission_created', function($user_id, $task_id, $submission_id) {
    $task = get_task_by_id($task_id);
    indoor_tasks_log_activity(
        $user_id, 
        'task_submission', 
        'Submitted proof for task: ' . $task->title,
        ['task_id' => $task_id, 'submission_id' => $submission_id]
    );
}, 10, 3);

add_action('indoor_tasks_submission_approved', function($user_id, $task_id) {
    $task = get_task_by_id($task_id);
    indoor_tasks_log_activity(
        $user_id, 
        'task_approved', 
        'Task approved: ' . $task->title,
        ['task_id' => $task_id]
    );
}, 10, 2);

add_action('indoor_tasks_level_changed', function($user_id, $old_level, $new_level) {
    indoor_tasks_log_activity(
        $user_id, 
        'level_change', 
        'User level changed from ' . $old_level->name . ' to ' . $new_level->name,
        ['old_level_id' => $old_level->id, 'new_level_id' => $new_level->id]
    );
}, 10, 3);

add_action('indoor_tasks_withdrawal_requested', function($user_id, $withdrawal_id, $amount, $method) {
    indoor_tasks_log_activity(
        $user_id, 
        'withdrawal_request', 
        'Withdrawal requested: ' . $amount . ' points via ' . $method,
        ['withdrawal_id' => $withdrawal_id, 'amount' => $amount, 'method' => $method]
    );
}, 10, 4);

add_action('indoor_tasks_withdrawal_status_changed', function($user_id, $withdrawal_id, $status) {
    indoor_tasks_log_activity(
        $user_id, 
        'withdrawal_status', 
        'Withdrawal status changed to: ' . $status,
        ['withdrawal_id' => $withdrawal_id, 'status' => $status]
    );
}, 10, 3);

add_action('indoor_tasks_kyc_submitted', function($user_id, $kyc_id) {
    indoor_tasks_log_activity(
        $user_id, 
        'kyc_submission', 
        'KYC verification documents submitted',
        ['kyc_id' => $kyc_id]
    );
}, 10, 2);

add_action('indoor_tasks_kyc_status_changed', function($user_id, $status) {
    indoor_tasks_log_activity(
        $user_id, 
        'kyc_status', 
        'KYC verification status changed to: ' . $status,
        ['status' => $status]
    );
}, 10, 2);

// Track referral activity
add_action('indoor_tasks_referral_created', function($user_id, $referred_id) {
    $referred_user = get_userdata($referred_id);
    indoor_tasks_log_activity(
        $user_id, 
        'referral_created', 
        'User referred: ' . $referred_user->user_login,
        ['referred_id' => $referred_id]
    );
}, 10, 2);

// Track points awarded
add_action('indoor_tasks_points_awarded', function($user_id, $points, $reason) {
    indoor_tasks_log_activity(
        $user_id, 
        'points_awarded', 
        'Points awarded: ' . $points . ' - ' . $reason,
        ['points' => $points, 'reason' => $reason]
    );
}, 10, 3);

// Track task creation (for admin users)
add_action('indoor_tasks_task_created', function($admin_id, $task_id) {
    $task = get_task_by_id($task_id);
    indoor_tasks_log_activity(
        $admin_id, 
        'task_created', 
        'Task created: ' . $task->title,
        ['task_id' => $task_id]
    );
}, 10, 2);

// Track profile updates
add_action('profile_update', function($user_id, $old_user_data) {
    indoor_tasks_log_activity(
        $user_id, 
        'profile_update', 
        'User profile updated',
        ['old_data' => json_encode([
            'display_name' => $old_user_data->display_name,
            'user_email' => $old_user_data->user_email
        ])]
    );
}, 10, 2);

// Track user registration
add_action('user_register', function($user_id) {
    indoor_tasks_log_activity(
        $user_id, 
        'user_register', 
        'User registered',
        ['registration_date' => current_time('mysql')]
    );
}, 10, 1);

/**
 * Helper function to get task by ID
 *
 * @param int $task_id Task ID
 * @return object Task object
 */
function get_task_by_id($task_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d",
        $task_id
    ));
}
