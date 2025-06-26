<?php
/**
 * AJAX endpoint for task proof submission
 * Handles task submission with file upload, validation, and notification
 */

// Task submission handler for logged in users
add_action('wp_ajax_indoor_tasks_submit_proof', 'indoor_tasks_handle_proof_submission');

// Redirect non-logged in users
add_action('wp_ajax_nopriv_indoor_tasks_submit_proof', function() {
    wp_send_json_error(['message' => __('You must be logged in to submit tasks.', 'indoor-tasks')]);
});

/**
 * Handle task proof submission
 */
function indoor_tasks_handle_proof_submission() {
    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Task submission started - POST data: ' . print_r($_POST, true));
        error_log('Task submission started - FILES data: ' . print_r($_FILES, true));
    }
    
    // Check for task ID
    if (!isset($_POST['task_id']) || empty($_POST['task_id'])) {
        wp_send_json_error(['message' => __('Missing task ID.', 'indoor-tasks')]);
    }
    
    // Check for proof text
    if (!isset($_POST['proof_text']) || empty($_POST['proof_text'])) {
        wp_send_json_error(['message' => __('Please provide proof details.', 'indoor-tasks')]);
    }
    
    // Check for mandatory file upload
    if (!isset($_FILES['proof_file']) || empty($_FILES['proof_file']['tmp_name'])) {
        wp_send_json_error(['message' => __('Please upload a proof file (screenshot, document, etc.).', 'indoor-tasks')]);
    }
    
    // Get task and user data
    $task_id = intval($_POST['task_id']);
    $user_id = get_current_user_id();
    $proof_text = sanitize_textarea_field($_POST['proof_text']);
    
    global $wpdb;
    
    // Check if task exists
    $task_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks WHERE id = %d",
        $task_id
    ));
    
    if (!$task_exists) {
        wp_send_json_error(['message' => __('Task not found.', 'indoor-tasks')]);
    }
    
    // Check if user already submitted this task
    $previous_submission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_submissions 
         WHERE task_id = %d AND user_id = %d",
        $task_id, $user_id
    ));
    
    if ($previous_submission) {
        wp_send_json_error(['message' => __('You have already submitted proof for this task.', 'indoor-tasks')]);
    }
    
    // Check task failure count for this user
    $failure_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_failures 
         WHERE task_id = %d AND user_id = %d",
        $task_id, $user_id
    ));
    
    // Maximum failures allowed (3 attempts)
    $max_failures = 3;
    if ($failure_count >= $max_failures) {
        wp_send_json_error(['message' => __('You have exceeded the maximum number of attempts for this task and are permanently banned from it.', 'indoor-tasks')]);
    }
    
    // Get task deadline
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d",
        $task_id
    ));
    
    // Check if task is expired
    if (strtotime($task->deadline) < time()) {
        wp_send_json_error(['message' => __('This task has expired and is no longer accepting submissions.', 'indoor-tasks')]);
    }
    
    // Handle file upload if provided
    $proof_file_url = '';
    if (isset($_FILES['proof_file']) && !empty($_FILES['proof_file']['tmp_name'])) {
        // Check file type - expanded to include documents
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/jpg',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        if (!in_array($_FILES['proof_file']['type'], $allowed_types)) {
            wp_send_json_error(['message' => __('Invalid file type. Please upload JPG, PNG, GIF, PDF, DOC, or DOCX files only.', 'indoor-tasks')]);
        }
        
        // Check file size (max 5MB)
        if ($_FILES['proof_file']['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(['message' => __('File too large. Maximum size is 5MB.', 'indoor-tasks')]);
        }
        
        // Upload the file
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $upload_overrides = ['test_form' => false];
        $uploaded_file = wp_handle_upload($_FILES['proof_file'], $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            wp_send_json_error(['message' => $uploaded_file['error']]);
        }
        
        $proof_file_url = $uploaded_file['url'];
    }
    
    // Insert submission
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'indoor_task_submissions',
        [
            'task_id' => $task_id,
            'user_id' => $user_id,
            'proof_text' => $proof_text,
            'proof_file' => $proof_file_url,
            'status' => 'pending',
            'submitted_at' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%s', '%s', '%s']
    );
    
    if (!$inserted) {
        wp_send_json_error(['message' => __('Failed to save submission. Please try again.', 'indoor-tasks')]);
    }
    
    $submission_id = $wpdb->insert_id;
    
    // Fire action hooks for email notifications
    do_action('indoor_tasks_task_submitted', $user_id, $task_id);
    do_action('indoor_tasks_task_status_changed', $user_id, $task_id, 'pending');
    
    // Create a notification for admin
    $admin_users = get_users(['role' => 'administrator']);
    if (!empty($admin_users)) {
        foreach ($admin_users as $admin) {
            indoor_tasks_add_notification(
                $admin->ID,
                __('New Task Submission', 'indoor-tasks'),
                sprintf(__('User %s has submitted proof for task: %s', 'indoor-tasks'), 
                        wp_get_current_user()->display_name, 
                        $task->title),
                'task_submission',
                $submission_id
            );
        }
    }
    
    // Return success
    wp_send_json_success(['message' => __('Your proof has been submitted and is pending review.', 'indoor-tasks')]);
}

/**
 * Create task failures table if it doesn't exist
 */
function indoor_tasks_create_failures_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indoor_task_failures';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        task_id int(11) NOT NULL,
        user_id bigint(20) NOT NULL,
        submission_id int(11) DEFAULT NULL,
        failed_at datetime NOT NULL,
        reason text,
        PRIMARY KEY (id),
        KEY task_id (task_id),
        KEY user_id (user_id),
        KEY submission_id (submission_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
