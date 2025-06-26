<?php
/**
 * Indoor Tasks - Announcements API
 * 
 * REST API endpoints for managing announcements
 */

if (!defined('ABSPATH')) exit;

/**
 * Register REST API routes for announcements
 */
add_action('rest_api_init', function() {
    // Create announcement endpoint
    register_rest_route('indoor-tasks/v1', '/announcements', array(
        'methods' => 'POST',
        'callback' => 'indoor_tasks_api_create_announcement',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Send announcement endpoint
    register_rest_route('indoor-tasks/v1', '/announcements/(?P<id>\d+)/send', array(
        'methods' => 'POST',
        'callback' => 'indoor_tasks_api_send_announcement',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Get announcements endpoint
    register_rest_route('indoor-tasks/v1', '/announcements', array(
        'methods' => 'GET',
        'callback' => 'indoor_tasks_api_get_announcements',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
});

/**
 * Create a new announcement via API
 */
function indoor_tasks_api_create_announcement($request) {
    $params = $request->get_params();
    
    // Validate required fields
    if (empty($params['title']) || empty($params['message'])) {
        return new WP_Error('missing_fields', 'Title and message are required', array('status' => 400));
    }
    
    global $wpdb;
    
    // Sanitize input
    $title = sanitize_text_field($params['title']);
    $message = sanitize_textarea_field($params['message']);
    $type = sanitize_text_field($params['type'] ?? 'general');
    $target_audience = sanitize_text_field($params['target_audience'] ?? 'all');
    $send_email = isset($params['send_email']) ? (bool)$params['send_email'] : true;
    $send_push = isset($params['send_push']) ? (bool)$params['send_push'] : true;
    $send_telegram = isset($params['send_telegram']) ? (bool)$params['send_telegram'] : false;
    $schedule_time = !empty($params['schedule_time']) ? sanitize_text_field($params['schedule_time']) : null;
    $send_immediately = isset($params['send_immediately']) ? (bool)$params['send_immediately'] : false;
    
    // Insert announcement
    $result = $wpdb->insert(
        $wpdb->prefix . 'indoor_task_announcements',
        array(
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'target_audience' => $target_audience,
            'send_email' => $send_email ? 1 : 0,
            'send_push' => $send_push ? 1 : 0,
            'send_telegram' => $send_telegram ? 1 : 0,
            'schedule_time' => $schedule_time,
            'status' => ($schedule_time && !$send_immediately) ? 'scheduled' : 'pending',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s')
    );
    
    if ($result === false) {
        return new WP_Error('db_error', 'Failed to create announcement', array('status' => 500));
    }
    
    $announcement_id = $wpdb->insert_id;
    
    // Send immediately if requested and not scheduled
    if ((!$schedule_time || $send_immediately) && function_exists('indoor_tasks_send_announcement')) {
        $sent = indoor_tasks_send_announcement($announcement_id);
        if (!$sent) {
            return new WP_Error('send_error', 'Announcement created but failed to send', array('status' => 201));
        }
    }
    
    return array(
        'success' => true,
        'announcement_id' => $announcement_id,
        'message' => $send_immediately ? 'Announcement created and sent successfully' : 'Announcement created successfully'
    );
}

/**
 * Send an existing announcement via API
 */
function indoor_tasks_api_send_announcement($request) {
    $announcement_id = (int) $request['id'];
    
    if (!$announcement_id) {
        return new WP_Error('invalid_id', 'Invalid announcement ID', array('status' => 400));
    }
    
    global $wpdb;
    
    // Check if announcement exists
    $announcement = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_announcements WHERE id = %d",
        $announcement_id
    ));
    
    if (!$announcement) {
        return new WP_Error('not_found', 'Announcement not found', array('status' => 404));
    }
    
    // Check if already sent
    if ($announcement->status === 'sent') {
        return new WP_Error('already_sent', 'Announcement has already been sent', array('status' => 400));
    }
    
    // Send announcement
    if (function_exists('indoor_tasks_send_announcement')) {
        $sent = indoor_tasks_send_announcement($announcement_id);
        
        if ($sent) {
            return array(
                'success' => true,
                'message' => 'Announcement sent successfully'
            );
        } else {
            return new WP_Error('send_error', 'Failed to send announcement', array('status' => 500));
        }
    } else {
        return new WP_Error('function_missing', 'Announcement send function not available', array('status' => 500));
    }
}

/**
 * Get announcements via API
 */
function indoor_tasks_api_get_announcements($request) {
    global $wpdb;
    
    $params = $request->get_params();
    $page = max(1, intval($params['page'] ?? 1));
    $per_page = max(1, min(100, intval($params['per_page'] ?? 20))); // Limit to 100 per page
    $status = !empty($params['status']) ? sanitize_text_field($params['status']) : null;
    
    $offset = ($page - 1) * $per_page;
    
    // Build query
    $where_clause = '1=1';
    $query_params = array();
    
    if ($status) {
        $where_clause .= ' AND status = %s';
        $query_params[] = $status;
    }
    
    // Get announcements
    $query = "
        SELECT a.*, u.display_name as created_by_name 
        FROM {$wpdb->prefix}indoor_task_announcements a 
        LEFT JOIN {$wpdb->users} u ON a.created_by = u.ID 
        WHERE {$where_clause}
        ORDER BY a.created_at DESC 
        LIMIT %d OFFSET %d
    ";
    
    $query_params[] = $per_page;
    $query_params[] = $offset;
    
    $announcements = $wpdb->get_results($wpdb->prepare($query, ...$query_params));
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_announcements WHERE {$where_clause}";
    if ($status) {
        $total = $wpdb->get_var($wpdb->prepare($count_query, $status));
    } else {
        $total = $wpdb->get_var($count_query);
    }
    
    return array(
        'announcements' => $announcements,
        'pagination' => array(
            'page' => $page,
            'per_page' => $per_page,
            'total' => (int)$total,
            'total_pages' => ceil($total / $per_page)
        )
    );
}

/**
 * AJAX endpoint for quick announcement creation from admin
 */
add_action('wp_ajax_indoor_tasks_quick_announcement', function() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'indoor_tasks_admin_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $title = sanitize_text_field($_POST['title'] ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    $send_email = isset($_POST['send_email']);
    $send_push = isset($_POST['send_push']);
    $send_telegram = isset($_POST['send_telegram']);
    
    if (empty($title) || empty($message)) {
        wp_send_json_error(array('message' => 'Title and message are required'));
    }
    
    global $wpdb;
    
    // Create announcement
    $result = $wpdb->insert(
        $wpdb->prefix . 'indoor_task_announcements',
        array(
            'title' => $title,
            'message' => $message,
            'type' => 'general',
            'target_audience' => 'all',
            'send_email' => $send_email ? 1 : 0,
            'send_push' => $send_push ? 1 : 0,
            'send_telegram' => $send_telegram ? 1 : 0,
            'status' => 'pending',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        )
    );
    
    if ($result === false) {
        wp_send_json_error(array('message' => 'Failed to create announcement'));
    }
    
    $announcement_id = $wpdb->insert_id;
    
    // Send immediately
    if (function_exists('indoor_tasks_send_announcement')) {
        $sent = indoor_tasks_send_announcement($announcement_id);
        
        if ($sent) {
            wp_send_json_success(array(
                'message' => 'Announcement sent successfully',
                'announcement_id' => $announcement_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Announcement created but failed to send'));
        }
    } else {
        wp_send_json_error(array('message' => 'Send function not available'));
    }
});

/**
 * Additional AJAX handlers for announcements functionality
 */

/**
 * AJAX handler to send announcement
 */
add_action('wp_ajax_indoor_tasks_send_announcement_ajax', function() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'indoor_tasks_announcements_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $announcement_id = intval($_POST['announcement_id']);
    
    if (!$announcement_id) {
        wp_send_json_error(array('message' => 'Invalid announcement ID'));
    }
    
    if (function_exists('indoor_tasks_send_announcement')) {
        $sent = indoor_tasks_send_announcement($announcement_id);
        
        if ($sent) {
            wp_send_json_success(array('message' => 'Announcement sent successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to send announcement'));
        }
    } else {
        wp_send_json_error(array('message' => 'Send function not available'));
    }
});

/**
 * AJAX handler to get audience count
 */
add_action('wp_ajax_indoor_tasks_get_audience_count', function() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'indoor_tasks_announcements_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $audience = sanitize_text_field($_POST['audience']);
    
    if (function_exists('indoor_tasks_get_announcement_users')) {
        $users = indoor_tasks_get_announcement_users($audience);
        wp_send_json_success(array('count' => count($users)));
    } else {
        wp_send_json_error(array('message' => 'Function not available'));
    }
});

/**
 * AJAX handler to get announcement preview
 */
add_action('wp_ajax_indoor_tasks_get_announcement_preview', function() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'indoor_tasks_announcements_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $announcement_id = intval($_POST['announcement_id']);
    
    if (!$announcement_id) {
        wp_send_json_error(array('message' => 'Invalid announcement ID'));
    }
    
    global $wpdb;
    
    $announcement = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_announcements WHERE id = %d",
        $announcement_id
    ));
    
    if (!$announcement) {
        wp_send_json_error(array('message' => 'Announcement not found'));
    }
    
    // Get target users count
    $users = function_exists('indoor_tasks_get_announcement_users') 
        ? indoor_tasks_get_announcement_users($announcement->target_audience) 
        : array();
    
    $preview_html = '
        <div class="announcement-preview">
            <h4>Announcement Details</h4>
            <table class="preview-table">
                <tr>
                    <td><strong>Title:</strong></td>
                    <td>' . esc_html($announcement->title) . '</td>
                </tr>
                <tr>
                    <td><strong>Type:</strong></td>
                    <td>' . esc_html(ucfirst($announcement->type)) . '</td>
                </tr>
                <tr>
                    <td><strong>Target Audience:</strong></td>
                    <td>' . esc_html(ucfirst($announcement->target_audience)) . ' (' . count($users) . ' users)</td>
                </tr>
                <tr>
                    <td><strong>Delivery Channels:</strong></td>
                    <td>';
    
    $channels = array();
    if ($announcement->send_email) $channels[] = 'Email';
    if ($announcement->send_push) $channels[] = 'Push Notification';
    if ($announcement->send_telegram) $channels[] = 'Telegram';
    
    $preview_html .= esc_html(implode(', ', $channels));
    $preview_html .= '</td>
                </tr>
            </table>
            
            <h4>Message Preview</h4>
            <div class="message-preview">
                ' . wp_kses_post(nl2br($announcement->message)) . '
            </div>
        </div>
        
        <style>
        .announcement-preview { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .preview-table { width: 100%; margin-bottom: 16px; }
        .preview-table td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        .preview-table td:first-child { width: 150px; }
        .message-preview { 
            background: #f9f9f9; 
            padding: 16px; 
            border-radius: 4px; 
            border-left: 4px solid #2271b1;
            line-height: 1.5;
        }
        </style>
    ';
    
    wp_send_json_success(array('preview' => $preview_html));
});

/**
 * AJAX handler for bulk actions
 */
add_action('wp_ajax_indoor_tasks_bulk_announcement_action', function() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'indoor_tasks_announcements_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $action = sanitize_text_field($_POST['bulk_action']);
    $ids = array_map('intval', $_POST['announcement_ids']);
    
    if (empty($ids)) {
        wp_send_json_error(array('message' => 'No announcements selected'));
    }
    
    global $wpdb;
    $success_count = 0;
    $error_count = 0;
    
    foreach ($ids as $id) {
        switch ($action) {
            case 'send':
                if (function_exists('indoor_tasks_send_announcement')) {
                    if (indoor_tasks_send_announcement($id)) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
                break;
                
            case 'delete':
                $deleted = $wpdb->delete(
                    $wpdb->prefix . 'indoor_task_announcements',
                    array('id' => $id),
                    array('%d')
                );
                
                if ($deleted) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                break;
                
            default:
                $error_count++;
        }
    }
    
    if ($error_count === 0) {
        wp_send_json_success(array(
            'message' => sprintf('Successfully processed %d announcement(s)', $success_count)
        ));
    } else {
        wp_send_json_error(array(
            'message' => sprintf('Processed %d announcement(s), %d failed', $success_count, $error_count)
        ));
    }
});

/**
 * AJAX handler to refresh announcement status
 */
add_action('wp_ajax_indoor_tasks_refresh_announcement_status', function() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'indoor_tasks_announcements_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    // Process any scheduled announcements that are due
    if (function_exists('indoor_tasks_process_scheduled_announcements')) {
        indoor_tasks_process_scheduled_announcements();
    }
    
    // Check if any announcements were updated
    global $wpdb;
    $recent_updates = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_announcements 
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    
    wp_send_json_success(array('updated' => $recent_updates > 0));
});

/**
 * AJAX handler to load more announcements for frontend users
 */
add_action('wp_ajax_indoor_tasks_load_more_announcements', function() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'indoor_tasks_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Login required'));
    }
    
    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = 10; // Fixed number for frontend
    $type_filter = !empty($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    
    global $wpdb;
    
    $offset = ($page - 1) * $per_page;
    
    // Build query for sent announcements only
    $where_clause = "status = 'sent'";
    $query_params = array();
    
    if ($type_filter && $type_filter !== 'all') {
        $where_clause .= ' AND type = %s';
        $query_params[] = $type_filter;
    }
    
    // Get announcements
    $query = "
        SELECT id, title, message, type, created_at 
        FROM {$wpdb->prefix}indoor_task_announcements 
        WHERE {$where_clause}
        ORDER BY created_at DESC 
        LIMIT %d OFFSET %d
    ";
    
    $query_params[] = $per_page;
    $query_params[] = $offset;
    
    $announcements = $wpdb->get_results($wpdb->prepare($query, ...$query_params));
    
    // Format announcements for frontend
    $formatted_announcements = array();
    foreach ($announcements as $announcement) {
        $formatted_announcements[] = array(
            'id' => $announcement->id,
            'title' => $announcement->title,
            'message' => wp_trim_words($announcement->message, 30, '...'),
            'full_message' => $announcement->message,
            'type' => ucfirst($announcement->type),
            'type_class' => 'type-' . $announcement->type,
            'date' => date_i18n(get_option('date_format'), strtotime($announcement->created_at)),
            'time_ago' => human_time_diff(strtotime($announcement->created_at), current_time('timestamp')) . ' ' . __('ago', 'indoor-tasks'),
            'link' => add_query_arg('announcement_id', $announcement->id, indoor_tasks_get_page_url('announcement-detail'))
        );
    }
    
    // Check if there are more announcements
    $total_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_announcements WHERE {$where_clause}",
        ...(empty($query_params) ? array() : array_slice($query_params, 0, -2))
    ));
    
    $has_more = ($page * $per_page) < $total_count;
    
    wp_send_json_success(array(
        'announcements' => $formatted_announcements,
        'has_more' => $has_more,
        'next_page' => $page + 1,
        'total' => (int)$total_count
    ));
});

add_action('wp_ajax_nopriv_indoor_tasks_load_more_announcements', function() {
    wp_send_json_error(array('message' => 'Login required'));
});

/**
 * AJAX handler to mark announcement as read
 */
add_action('wp_ajax_indoor_tasks_mark_announcement_read', function() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'indoor_tasks_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Login required'));
    }
    
    $announcement_id = intval($_POST['announcement_id']);
    $user_id = get_current_user_id();
    
    if (!$announcement_id || !$user_id) {
        wp_send_json_error(array('message' => 'Invalid data'));
    }
    
    global $wpdb;
    
    // Check if announcement exists
    $announcement_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}indoor_task_announcements WHERE id = %d AND status = 'sent'",
        $announcement_id
    ));
    
    if (!$announcement_exists) {
        wp_send_json_error(array('message' => 'Announcement not found'));
    }
    
    // Mark as read (create or update read status)
    $table_name = $wpdb->prefix . 'indoor_task_announcement_reads';
    
    $existing_read = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE announcement_id = %d AND user_id = %d",
        $announcement_id,
        $user_id
    ));
    
    if (!$existing_read) {
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'announcement_id' => $announcement_id,
                'user_id' => $user_id,
                'read_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s')
        );
        
        if ($inserted) {
            wp_send_json_success(array('message' => 'Marked as read'));
        } else {
            wp_send_json_error(array('message' => 'Failed to mark as read'));
        }
    } else {
        wp_send_json_success(array('message' => 'Already marked as read'));
    }
});

add_action('wp_ajax_nopriv_indoor_tasks_mark_announcement_read', function() {
    wp_send_json_error(array('message' => 'Login required'));
});
