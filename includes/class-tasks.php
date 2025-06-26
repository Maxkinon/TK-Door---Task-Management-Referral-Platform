<?php
// Task management: create, view, submit, admin review
class Indoor_Tasks_Tasks {
    public function __construct() {
        add_action('template_include', [$this, 'route_templates']);
        add_action('wp_ajax_indoor_tasks_start_task', [$this, 'start_task']);
        add_action('wp_ajax_indoor_tasks_submit_proof', [$this, 'submit_proof']);
    }
    public function route_templates($template) {
        if (is_page_template('task-list.php')) {
            return INDOOR_TASKS_PATH . 'templates/task-list.php';
        }
        if (is_page_template('task-detail.php')) {
            return INDOOR_TASKS_PATH . 'templates/task-detail.php';
        }
        if (is_page_template('task-submit.php')) {
            return INDOOR_TASKS_PATH . 'templates/task-submit.php';
        }
        return $template;
    }
    public function start_task() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to start tasks.', 'indoor-tasks')]);
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'indoor_tasks_start_task')) {
            wp_send_json_error(['message' => __('Security check failed.', 'indoor-tasks')]);
        }
        
        // Get task data
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$task_id) {
            wp_send_json_error(['message' => __('Invalid task ID.', 'indoor-tasks')]);
        }
        
        global $wpdb;
        
        // Check if task exists
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d",
            $task_id
        ));
        
        if (!$task) {
            wp_send_json_error(['message' => __('Task not found.', 'indoor-tasks')]);
        }
        
        // Check if task is expired
        if (strtotime($task->deadline) < time()) {
            wp_send_json_error(['message' => __('This task has expired.', 'indoor-tasks')]);
        }
        
        // Check if user already submitted this task
        $submission_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
             WHERE task_id = %d AND user_id = %d",
            $task_id, $user_id
        ));
        
        if ($submission_exists) {
            wp_send_json_error(['message' => __('You have already submitted this task.', 'indoor-tasks')]);
        }
        
        // Check if user has already started this task
        $task_started = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_starts 
             WHERE task_id = %d AND user_id = %d AND status = 'active'",
            $task_id, $user_id
        ));
        
        if (!$task_started) {
            // Create the table if it doesn't exist
            $this->create_task_starts_table();
            
            // Record task start
            $wpdb->insert(
                $wpdb->prefix . 'indoor_task_starts',
                [
                    'task_id' => $task_id,
                    'user_id' => $user_id,
                    'started_at' => current_time('mysql'),
                    'status' => 'active'
                ],
                ['%d', '%d', '%s', '%s']
            );
        }
        
        wp_send_json_success(['message' => __('Task started successfully!', 'indoor-tasks')]);
    }
    
    /**
     * Create task starts table if it doesn't exist
     */
    private function create_task_starts_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'indoor_task_starts';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            task_id int(11) NOT NULL,
            user_id bigint(20) NOT NULL,
            started_at datetime NOT NULL,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            UNIQUE KEY task_user_active (task_id, user_id, status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function submit_proof() {
        if (!is_user_logged_in()) wp_send_json_error(['message'=>'Login required.']);
        global $wpdb;
        $user_id = get_current_user_id();
        $task_id = intval($_POST['task_id']);
        $proof_text = sanitize_textarea_field($_POST['proof_text']);
        $file_url = '';
        if (!empty($_FILES['proof_file']['tmp_name'])) {
            $uploaded = wp_handle_upload($_FILES['proof_file'], ['test_form'=>false]);
            if (!empty($uploaded['url'])) $file_url = $uploaded['url'];
        }
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d", $task_id));
        if (!$task) wp_send_json_error(['message'=>'Task not found.']);
        $deadline = strtotime($task->deadline);
        if (time() > $deadline) wp_send_json_error(['message'=>'Deadline passed.']);
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE task_id = %d AND user_id = %d", $task_id, $user_id));
        if ($exists) wp_send_json_error(['message'=>'Already submitted.']);
        $wpdb->insert($wpdb->prefix.'indoor_task_submissions', [
            'task_id' => $task_id,
            'user_id' => $user_id,
            'proof_text' => $proof_text,
            'proof_file' => $file_url,
            'status' => 'pending',
            'submitted_at' => current_time('mysql')
        ]);
        wp_send_json_success(['message'=>'Submitted! Pending review.']);
    }
}
