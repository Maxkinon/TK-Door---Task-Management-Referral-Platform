<?php
/**
 * User-related functions for Indoor Tasks plugin
 * Handles user profile, logout, and user management
 */

/**
 * Process logout and handle redirects
 * 
 * @param string $redirect_to Optional URL to redirect after logout
 * @return void
 */
function indoor_tasks_process_logout($redirect_to = '') {
    if (!is_user_logged_in()) {
        return;
    }
    
    // Logout the user
    wp_logout();
    
    // Clear any user-specific cookies or transients
    if (isset($_COOKIE['indoor_tasks_user_preferences'])) {
        setcookie('indoor_tasks_user_preferences', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
    
    // Determine redirect location
    if (empty($redirect_to)) {
        // Default to login page
        $login_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tk-indoor-auth.php', 'login');
        
        if ($login_page) {
            $redirect_to = get_permalink($login_page->ID);
        } else {
            $redirect_to = home_url('/login/');
        }
    }
    
    // Add logout parameter for messaging
    $redirect_to = add_query_arg('logged_out', '1', $redirect_to);
    
    // Log the redirect (if debug)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Indoor Tasks: User logged out. Redirecting to: ' . $redirect_to);
    }
    
    wp_redirect($redirect_to);
    exit;
}

/**
 * Endpoint for logout link/button
 * Usage: /wp-admin/admin-ajax.php?action=indoor_tasks_logout&redirect_to=url
 */
function indoor_tasks_logout_ajax_handler() {
    $redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw($_REQUEST['redirect_to']) : '';
    indoor_tasks_process_logout($redirect_to);
}
add_action('wp_ajax_indoor_tasks_logout', 'indoor_tasks_logout_ajax_handler');
add_action('wp_ajax_nopriv_indoor_tasks_logout', 'indoor_tasks_logout_ajax_handler'); // Handle even if already logged out

/**
 * Generate a logout URL with proper redirect
 * 
 * @param string $redirect_to Optional URL to redirect after logout
 * @return string The logout URL
 */
function indoor_tasks_get_logout_url($redirect_to = '') {
    $logout_url = admin_url('admin-ajax.php?action=indoor_tasks_logout');
    
    if (!empty($redirect_to)) {
        $logout_url = add_query_arg('redirect_to', urlencode($redirect_to), $logout_url);
    }
    
    return $logout_url;
}

/**
 * Get user profile data including custom fields
 * 
 * @param int $user_id Optional user ID, defaults to current user
 * @return array User profile data
 */
function indoor_tasks_get_user_profile($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return array();
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return array();
    }
    
    // Basic user data
    $profile = array(
        'id' => $user_id,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'registered' => $user->user_registered,
        'roles' => $user->roles,
    );
    
    // Get avatar URL
    $profile['avatar'] = get_avatar_url($user_id, array('size' => 150));
    
    // Get custom Indoor Tasks user meta
    $custom_fields = array(
        'phone_number', 
        'address', 
        'bio',
        'wallet_balance',
        'completed_tasks',
        'country'
    );
    
    foreach ($custom_fields as $field) {
        $meta_key = 'indoor_tasks_' . $field;
        $profile[$field] = get_user_meta($user_id, $meta_key, true);
    }
    
    return $profile;
}

/**
 * Update user profile data
 * 
 * @param array $data Profile data to update
 * @param int $user_id Optional user ID, defaults to current user
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function indoor_tasks_update_user_profile($data, $user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return new WP_Error('not_logged_in', 'User not logged in');
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('invalid_user', 'Invalid user');
    }
    
    // Standard WordPress fields
    $wp_fields = array(
        'first_name',
        'last_name',
        'display_name',
    );
    
    $user_data = array('ID' => $user_id);
    
    foreach ($wp_fields as $field) {
        if (isset($data[$field])) {
            $user_data[$field] = sanitize_text_field($data[$field]);
        }
    }
    
    // Special handling for email (requires verification)
    if (isset($data['email']) && is_email($data['email']) && $data['email'] !== $user->user_email) {
        // Here you could implement email verification before changing
        // For now, just update directly
        $user_data['user_email'] = $data['email'];
    }
    
    // Update WordPress core user data
    if (count($user_data) > 1) { // If we have more than just ID
        $result = wp_update_user($user_data);
        if (is_wp_error($result)) {
            return $result;
        }
    }
    
    // Custom fields
    $custom_fields = array(
        'phone_number', 
        'address', 
        'bio',
        'country'
    );
    
    foreach ($custom_fields as $field) {
        if (isset($data[$field])) {
            $meta_key = 'indoor_tasks_' . $field;
            update_user_meta($user_id, $meta_key, sanitize_text_field($data[$field]));
        }
    }
    
    return true;
}

/**
 * Get user dashboard data
 * 
 * @param int $user_id Optional user ID, defaults to current user
 * @return array Dashboard data
 */
function indoor_tasks_get_dashboard_data($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return array();
    }
    
    // Get basic profile
    $data = indoor_tasks_get_user_profile($user_id);
    
    // Add dashboard-specific data
    $data['wallet_balance'] = get_user_meta($user_id, 'indoor_tasks_wallet_balance', true) ?: '0.00';
    
    // Get tasks data
    $data['tasks'] = array(
        'completed' => intval(get_user_meta($user_id, 'indoor_tasks_completed_tasks', true) ?: 0),
        'pending' => intval(get_user_meta($user_id, 'indoor_tasks_pending_tasks', true) ?: 0),
        'active' => intval(get_user_meta($user_id, 'indoor_tasks_active_tasks', true) ?: 0),
    );
    
    // Get recent activities
    $activities = get_user_meta($user_id, 'indoor_tasks_recent_activities', true);
    if (!is_array($activities)) {
        $activities = array();
    }
    
    $data['recent_activities'] = $activities;
    
    return $data;
}

// Toggle notification read/unread status
add_action('wp_ajax_indoor_tasks_toggle_notification_read', 'indoor_tasks_toggle_notification_read');
function indoor_tasks_toggle_notification_read() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'indoor-tasks-notifications-nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'indoor-tasks')]);
    }
    
    // Get parameters
    $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
    $is_read = isset($_POST['is_read']) ? intval($_POST['is_read']) : 1;
    $user_id = get_current_user_id();
    
    // Validate
    if (!$notification_id) {
        wp_send_json_error(['message' => __('Invalid notification ID.', 'indoor-tasks')]);
    }
    
    global $wpdb;
    
    // Check if the notification exists and belongs to the current user
    $notification = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_notifications 
         WHERE id = %d AND user_id = %d",
        $notification_id, $user_id
    ));
    
    if (!$notification) {
        wp_send_json_error(['message' => __('Notification not found.', 'indoor-tasks')]);
    }
    
    // Update the read status
    $updated = $wpdb->update(
        $wpdb->prefix . 'indoor_task_notifications',
        ['is_read' => $is_read],
        ['id' => $notification_id],
        ['%d'],
        ['%d']
    );
    
    if ($updated !== false) {
        wp_send_json_success(['message' => __('Notification updated.', 'indoor-tasks')]);
    } else {
        wp_send_json_error(['message' => __('Failed to update notification.', 'indoor-tasks')]);
    }
}

// Mark all notifications as read
add_action('wp_ajax_indoor_tasks_mark_all_notifications_read', 'indoor_tasks_mark_all_notifications_read');
function indoor_tasks_mark_all_notifications_read() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'indoor-tasks-notifications-nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'indoor-tasks')]);
    }
    
    $user_id = get_current_user_id();
    
    global $wpdb;
    
    // Update all unread notifications for the current user
    $updated = $wpdb->update(
        $wpdb->prefix . 'indoor_task_notifications',
        ['is_read' => 1],
        ['user_id' => $user_id, 'is_read' => 0],
        ['%d'],
        ['%d', '%d']
    );
    
    if ($updated !== false) {
        wp_send_json_success(['message' => __('All notifications marked as read.', 'indoor-tasks')]);
    } else {
        wp_send_json_error(['message' => __('Failed to update notifications.', 'indoor-tasks')]);
    }
}

// Get unread notifications count
add_action('wp_ajax_indoor_tasks_get_unread_count', 'indoor_tasks_get_unread_count');
function indoor_tasks_get_unread_count() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'indoor-tasks-notifications-nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'indoor-tasks')]);
    }
    
    $user_id = get_current_user_id();
    
    global $wpdb;
    
    // Check if the table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_notifications'") === $wpdb->prefix . 'indoor_task_notifications';
    
    if ($table_exists) {
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_notifications 
             WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    } else {
        $count = 0;
    }
    
    wp_send_json_success(['count' => $count]);
}

/**
 * Get user wallet balance
 * 
 * @param int $user_id User ID
 * @return int Wallet balance
 */
if (!function_exists('indoor_tasks_get_wallet_balance')) {
    function indoor_tasks_get_wallet_balance($user_id) {
        global $wpdb;
        
        // Calculate balance from wallet transactions
        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
            $user_id
        ));
        
        // If no transactions found, return 0
        if (is_null($balance)) {
            return 0;
        }
        
        return (int) $balance;
    }
}

/**
 * Get unread notifications count for a user
 * 
 * @param int $user_id User ID
 * @return int Unread notifications count
 */
if (!function_exists('indoor_tasks_get_unread_notifications_count')) {
    function indoor_tasks_get_unread_notifications_count($user_id) {
        global $wpdb;
        
        // Check if notifications table exists
        $table_name = $wpdb->prefix . 'indoor_task_notifications';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return 0;
        }
        
        // Get unread notifications count
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
        
        return (int) $count;
    }
}
