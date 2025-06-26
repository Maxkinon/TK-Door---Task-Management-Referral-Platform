<?php
// Admin user activity page
global $wpdb;

// Enqueue the user activity styles
wp_enqueue_style(
    'indoor-tasks-user-activity',
    plugins_url('assets/css/user-activity.css', dirname(__FILE__)),
    array(),
    INDOOR_TASKS_VERSION
);

// Process any actions
if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $user = get_user_by('id', $user_id);
    
    // If user doesn't exist, redirect back to main page
    if (!$user) {
        echo '<script>window.location.href = "admin.php?page=indoor-tasks-user-activity";</script>';
        exit;
    }
    
    // Get user activities
    $activities = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_user_activities 
         WHERE user_id = %d 
         ORDER BY created_at DESC 
         LIMIT 100",
        $user_id
    ));
    
    // Calculate stats
    $stats = array(
        'total_activities' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_activities WHERE user_id = %d",
            $user_id
        )),
        'total_logins' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_activities WHERE user_id = %d AND activity_type = 'login'",
            $user_id
        )),
        'tasks_completed' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_activities WHERE user_id = %d AND activity_type = 'task_approved'",
            $user_id
        )),
        'total_points' => $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(metadata->>'$.points' AS SIGNED)) FROM {$wpdb->prefix}indoor_task_user_activities 
             WHERE user_id = %d AND activity_type = 'points_awarded'",
            $user_id
        )) ?: 0,
        'successful_referrals' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_activities WHERE user_id = %d AND activity_type = 'referral_created'",
            $user_id
        ))
    );
    
    // Get last login
    $last_login = $wpdb->get_var($wpdb->prepare(
        "SELECT created_at FROM {$wpdb->prefix}indoor_task_user_activities 
         WHERE user_id = %d AND activity_type = 'login' 
         ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));
    
    // Get recent IPs with geolocation (if available)
    $recent_ips = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT ip_address, COUNT(*) as count 
         FROM {$wpdb->prefix}indoor_task_user_activities 
         WHERE user_id = %d 
         GROUP BY ip_address 
         ORDER BY count DESC 
         LIMIT 5",
        $user_id
    ));
    
    // Get KYC status
    $kyc_status = get_user_meta($user_id, 'indoor_tasks_kyc_status', true);
    if (empty($kyc_status)) {
        $kyc_status = 'pending';
    }
    
    // Get user level
    $level_id = get_user_meta($user_id, 'indoor_tasks_user_level', true);
    $level = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_user_levels WHERE id = %d",
        $level_id
    ));
    
    // If no level is set, get the default level
    if (!$level) {
        $level = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}indoor_task_user_levels ORDER BY min_tasks ASC LIMIT 1");
    }
    
    // Display individual user activity
    include(plugin_dir_path(__FILE__) . 'views/user-activity-detail.php');
} else {
    // Build the query with filters
    $where_clauses = [];
    $query_params = [];
    
    // Activity type filter
    if (!empty($_GET['activity_type'])) {
        $where_clauses[] = "a.activity_type = %s";
        $query_params[] = sanitize_text_field($_GET['activity_type']);
    }
    
    // Date range filter
    if (!empty($_GET['date_range'])) {
        switch ($_GET['date_range']) {
            case 'today':
                $where_clauses[] = "DATE(a.created_at) = CURDATE()";
                break;
            case 'yesterday':
                $where_clauses[] = "DATE(a.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'week':
                $where_clauses[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $where_clauses[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }
    }
    
    // Search filter
    if (!empty($_GET['search'])) {
        $search_term = '%' . $wpdb->esc_like(sanitize_text_field($_GET['search'])) . '%';
        $where_clauses[] = "(u.user_login LIKE %s OR u.user_email LIKE %s)";
        $query_params[] = $search_term;
        $query_params[] = $search_term;
    }
    
    // Build the WHERE clause
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Get paginated results
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Get total count for pagination
    $total_items = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT a.user_id) 
             FROM {$wpdb->prefix}indoor_task_user_activities a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             $where_sql",
            $query_params
        )
    );
    
    // Get users with activity
    $users_with_activity = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT a.user_id, u.user_login, u.user_email, u.user_registered, 
             COUNT(*) as activity_count, 
             MAX(a.created_at) as last_activity,
             (SELECT activity_type FROM {$wpdb->prefix}indoor_task_user_activities 
              WHERE user_id = a.user_id ORDER BY created_at DESC LIMIT 1) as last_activity_type
             FROM {$wpdb->prefix}indoor_task_user_activities a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             $where_sql
             GROUP BY a.user_id
             ORDER BY last_activity DESC
             LIMIT %d OFFSET %d",
            array_merge($query_params, array($per_page, $offset))
        )
    );
    
    // Overall activity stats
    $stats = array(
        'total_activities' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_activities"),
        'login_activities' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_activities WHERE activity_type = 'login'"),
        'task_activities' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_activities WHERE activity_type IN ('task_submission', 'task_approved')"),
        'level_changes' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_activities WHERE activity_type = 'level_change'"),
        'total_points_awarded' => $wpdb->get_var("SELECT SUM(CAST(metadata->>'$.points' AS SIGNED)) FROM {$wpdb->prefix}indoor_task_user_activities WHERE activity_type = 'points_awarded'") ?: 0,
        'total_active_users' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}indoor_task_user_activities")
    );
    
    // Recent activity
    $recent_activities = $wpdb->get_results(
        "SELECT a.*, u.user_login, u.user_email 
         FROM {$wpdb->prefix}indoor_task_user_activities a
         LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
         ORDER BY a.created_at DESC
         LIMIT 10"
    );
    
    // Display overview page
    include(plugin_dir_path(__FILE__) . 'views/user-activity-overview.php');
}
