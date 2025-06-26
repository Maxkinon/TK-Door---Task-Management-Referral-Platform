<?php
/**
 * Template Name: TKM Door - Leaderboard
 * Description: Modern leaderboard template with rankings, achievements, and user profiles
 * Version: 1.0.0
 */

// Prevent direct file access
defined('ABSPATH') || exit;

// Redirect if not logged in
if (!is_user_logged_in()) {
    $login_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tk-indoor-auth.php', 'login');
    if ($login_page) {
        wp_redirect(get_permalink($login_page->ID));
    } else {
        wp_redirect(home_url('/login/'));
    }
    exit;
}

// Get current user info
$current_user_id = get_current_user_id();

// Get database references
global $wpdb;

// Handle AJAX requests
if ($_POST && isset($_POST['action'])) {
    $response = array();
    
    switch ($_POST['action']) {
        case 'get_leaderboard_data':
            if (wp_verify_nonce($_POST['nonce'], 'tkm_leaderboard_nonce')) {
                $search = sanitize_text_field($_POST['search'] ?? '');
                $category = sanitize_text_field($_POST['category'] ?? 'overall');
                $timeframe = sanitize_text_field($_POST['timeframe'] ?? 'all_time');
                
                $response['success'] = true;
                $response['data'] = tkm_get_leaderboard_data($search, $category, $timeframe);
            } else {
                $response['success'] = false;
                $response['message'] = 'Security check failed';
            }
            break;
            
        case 'get_user_profile':
            if (wp_verify_nonce($_POST['nonce'], 'tkm_leaderboard_nonce')) {
                $user_id = intval($_POST['user_id'] ?? 0);
                
                if ($user_id > 0) {
                    $response['success'] = true;
                    $response['data'] = tkm_get_user_profile_data($user_id);
                } else {
                    $response['success'] = false;
                    $response['message'] = 'Invalid user ID';
                }
            } else {
                $response['success'] = false;
                $response['message'] = 'Security check failed';
            }
            break;
    }
    
    if (isset($response)) {
        wp_send_json($response);
        exit;
    }
}

// Get initial leaderboard data
$leaderboard_data = tkm_get_leaderboard_data('', 'overall', 'all_time');
$current_user_profile = tkm_get_user_profile_data($current_user_id);

// Helper functions
function tkm_get_leaderboard_data($search = '', $category = 'overall', $timeframe = 'all_time') {
    global $wpdb;
    
    // Check which tables exist
    $wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
    
    // Base query to get users with points
    $where_clauses = array();
    
    // Search by username or display name
    if (!empty($search)) {
        $where_clauses[] = $wpdb->prepare(
            "(u.user_login LIKE %s OR u.display_name LIKE %s)",
            '%' . $search . '%',
            '%' . $search . '%'
        );
    }
    
    // Time-based filtering for wallet table
    $date_condition = '';
    if ($timeframe !== 'all_time' && $wallet_table_exists) {
        switch ($timeframe) {
            case 'this_month':
                $date_condition = "AND w.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')";
                break;
            case 'this_week':
                $date_condition = "AND w.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'today':
                $date_condition = "AND DATE(w.created_at) = CURDATE()";
                break;
        }
    }
    
    // Build query based on available tables
    if ($wallet_table_exists) {
        // Use wallet table - points can be positive (rewards, bonuses) or negative (withdrawals)
        $sql = "
            SELECT 
                u.ID,
                u.user_login,
                u.display_name,
                u.user_email,
                COALESCE(SUM(CASE WHEN w.type IN ('reward', 'bonus', 'admin') AND w.points > 0 THEN w.points ELSE 0 END), 0) as total_points,
                COALESCE(um_level.meta_value, '1') as user_level,
                COALESCE(um_avatar.meta_value, '') as custom_avatar,
                COUNT(DISTINCT CASE WHEN w.type = 'reward' THEN w.id END) as tasks_completed
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->prefix}indoor_task_wallet w ON u.ID = w.user_id {$date_condition}
            LEFT JOIN {$wpdb->usermeta} um_level ON u.ID = um_level.user_id AND um_level.meta_key = 'indoor_tasks_level'
            LEFT JOIN {$wpdb->usermeta} um_avatar ON u.ID = um_avatar.user_id AND um_avatar.meta_key = 'indoor_tasks_avatar'
        ";
    } else {
        // Fallback: Use user meta for points
        $sql = "
            SELECT 
                u.ID,
                u.user_login,
                u.display_name,
                u.user_email,
                COALESCE(CAST(um_points.meta_value AS UNSIGNED), 0) as total_points,
                COALESCE(um_level.meta_value, '1') as user_level,
                COALESCE(um_avatar.meta_value, '') as custom_avatar,
                COALESCE(CAST(um_tasks.meta_value AS UNSIGNED), 0) as tasks_completed
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_points ON u.ID = um_points.user_id AND um_points.meta_key = 'indoor_tasks_points'
            LEFT JOIN {$wpdb->usermeta} um_level ON u.ID = um_level.user_id AND um_level.meta_key = 'indoor_tasks_level'
            LEFT JOIN {$wpdb->usermeta} um_avatar ON u.ID = um_avatar.user_id AND um_avatar.meta_key = 'indoor_tasks_avatar'
            LEFT JOIN {$wpdb->usermeta} um_tasks ON u.ID = um_tasks.user_id AND um_tasks.meta_key = 'indoor_tasks_completed'
        ";
    }
    
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= "
        GROUP BY u.ID
        HAVING total_points > 0
        ORDER BY total_points DESC, u.display_name ASC
        LIMIT 100
    ";
    
    $users = $wpdb->get_results($sql);
    
    // Add ranking and additional data
    $ranked_users = array();
    $rank = 1;
    
    foreach ($users as $user) {
        $user_data = array(
            'rank' => $rank,
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'total_points' => intval($user->total_points),
            'level' => intval($user->user_level),
            'avatar_url' => tkm_get_user_avatar($user->ID, $user->custom_avatar),
            'points_change' => tkm_get_points_change($user->ID, $timeframe),
            'achievements_count' => tkm_get_user_achievements_count($user->ID),
            'level_progress' => tkm_get_level_progress($user->user_level, $user->total_points)
        );
        
        $ranked_users[] = $user_data;
        $rank++;
    }
    
    return $ranked_users;
}

function tkm_get_user_profile_data($user_id) {
    $user = get_userdata($user_id);
    if (!$user) return null;
    
    global $wpdb;
    
    // Check if wallet table exists
    $wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
    
    if ($wallet_table_exists) {
        // Get user points from wallet table
        $total_points = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type IN ('reward', 'bonus', 'admin') AND points > 0 THEN points ELSE 0 END), 0) 
             FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
            $user_id
        ));
    } else {
        // Fallback to user meta
        $total_points = get_user_meta($user_id, 'indoor_tasks_points', true) ?: 0;
    }
    
    $user_level = get_user_meta($user_id, 'indoor_tasks_level', true) ?: 1;
    $custom_avatar = get_user_meta($user_id, 'indoor_tasks_avatar', true);
    
    return array(
        'user_id' => $user_id,
        'username' => $user->user_login,
        'display_name' => $user->display_name,
        'email' => $user->user_email,
        'total_points' => intval($total_points),
        'level' => intval($user_level),
        'avatar_url' => tkm_get_user_avatar($user_id, $custom_avatar),
        'achievements' => tkm_get_user_achievements($user_id),
        'level_progress' => tkm_get_level_progress($user_level, $total_points),
        'badges' => tkm_get_user_badges($user_id),
        'stats' => tkm_get_user_stats($user_id)
    );
}

function tkm_get_user_avatar($user_id, $custom_avatar = '') {
    if (!empty($custom_avatar)) {
        return $custom_avatar;
    }
    return get_avatar_url($user_id, array('size' => 80, 'default' => 'mp'));
}

function tkm_get_points_change($user_id, $timeframe) {
    global $wpdb;
    
    // Check if wallet table exists
    $wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
    
    if (!$wallet_table_exists) {
        return 0; // No way to calculate change without transaction history
    }
    
    $date_condition = '';
    switch ($timeframe) {
        case 'this_month':
            $date_condition = "AND created_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 1 MONTH) 
                              AND created_at < DATE_FORMAT(NOW(), '%Y-%m-01')";
            break;
        case 'this_week':
            $date_condition = "AND created_at >= DATE_SUB(DATE_SUB(NOW(), INTERVAL 1 WEEK), INTERVAL 1 WEEK) 
                              AND created_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        default:
            return 0;
    }
    
    if (empty($date_condition)) return 0;
    
    $previous_points = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type IN ('reward', 'bonus', 'admin') AND points > 0 THEN points ELSE 0 END), 0) 
         FROM {$wpdb->prefix}indoor_task_wallet 
         WHERE user_id = %d {$date_condition}",
        $user_id
    ));
    
    $current_points = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type IN ('reward', 'bonus', 'admin') AND points > 0 THEN points ELSE 0 END), 0) 
         FROM {$wpdb->prefix}indoor_task_wallet 
         WHERE user_id = %d",
        $user_id
    ));
    
    return intval($current_points) - intval($previous_points);
}

function tkm_get_user_achievements_count($user_id) {
    // Mock achievement count - replace with actual achievement system
    return rand(2, 12);
}

function tkm_get_level_progress($level, $points) {
    $level = intval($level);
    $points = intval($points);
    
    // Define level thresholds (points needed for each level)
    $level_thresholds = array(
        1 => 0,
        2 => 100,
        3 => 300,
        4 => 600,
        5 => 1000,
        6 => 1500,
        7 => 2100,
        8 => 2800,
        9 => 3600,
        10 => 4500
    );
    
    $current_threshold = $level_thresholds[$level] ?? 0;
    $next_threshold = $level_thresholds[$level + 1] ?? $level_thresholds[$level] + 1000;
    
    if ($points >= $next_threshold) {
        return 100; // Max progress
    }
    
    $progress_points = $points - $current_threshold;
    $points_needed = $next_threshold - $current_threshold;
    
    return $points_needed > 0 ? min(100, round(($progress_points / $points_needed) * 100)) : 100;
}

function tkm_get_user_achievements($user_id) {
    // Mock achievements data - replace with actual achievement system
    return array(
        array('name' => 'First Task', 'icon' => '🎯', 'earned' => true),
        array('name' => 'Level 2', 'icon' => '⭐', 'earned' => true),
        array('name' => 'Referral Master', 'icon' => '👥', 'earned' => false),
        array('name' => 'Task Streak', 'icon' => '🔥', 'earned' => true),
        array('name' => 'Level 5', 'icon' => '💎', 'earned' => false)
    );
}

function tkm_get_user_badges($user_id) {
    // Mock badges data
    return array(
        array('name' => 'Best Performer', 'color' => '#00954b'),
        array('name' => 'Active User', 'color' => '#3b82f6'),
        array('name' => 'Rising Star', 'color' => '#f59e0b')
    );
}

function tkm_get_user_stats($user_id) {
    global $wpdb;
    
    // Check if wallet table exists
    $wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
    
    if ($wallet_table_exists) {
        // Count completed tasks from wallet table (reward type entries)
        $completed_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d AND type = 'reward'",
            $user_id
        ));
    } else {
        // Fallback to user meta
        $completed_tasks = get_user_meta($user_id, 'indoor_tasks_completed', true) ?: 0;
    }
    
    // Check if referrals table exists
    $referrals_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_referrals'") === $wpdb->prefix . 'indoor_referrals';
    
    if ($referrals_table_exists) {
        $referrals = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_referrals WHERE referrer_id = %d AND status = 'completed'",
            $user_id
        ));
    } else {
        // Fallback to user meta
        $referrals = get_user_meta($user_id, 'indoor_tasks_referrals', true) ?: 0;
    }
    
    return array(
        'completed_tasks' => intval($completed_tasks),
        'referrals' => intval($referrals),
        'days_active' => rand(15, 120) // Mock data
    );
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Leaderboard - <?php bloginfo('name'); ?></title>
    
    <?php wp_head(); ?>
    
    <!-- TKM Door Leaderboard Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-leaderboard.css?ver=1.0.0">
</head>
<body class="tkm-door-leaderboard">
    <div class="tkm-leaderboard-container">
        <!-- Include Sidebar -->
        <?php include INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'; ?>
        
        <div class="tkm-leaderboard-content">
            <!-- Header Section -->
            <div class="tkm-leaderboard-header">
                <div class="tkm-header-content">
                    <h1>🏆 Leaderboard</h1>
                    <p class="tkm-header-subtitle">See how you rank against other users and track your progress</p>
                </div>
            </div>
            
            <!-- Filters Section -->
            <div class="tkm-filters-section">
                <div class="tkm-filters-row">
                    <div class="tkm-search-box">
                        <input type="text" id="search-users" placeholder="Search by username..." />
                        <svg class="tkm-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21L16.65 16.65"/>
                        </svg>
                    </div>
                    
                    <div class="tkm-filter-group">
                        <select id="category-filter">
                            <option value="overall">Overall</option>
                            <option value="tasks">Tasks</option>
                            <option value="referrals">Referrals</option>
                        </select>
                        
                        <select id="timeframe-filter">
                            <option value="all_time">All Time</option>
                            <option value="this_month">This Month</option>
                            <option value="this_week">This Week</option>
                            <option value="today">Today</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="tkm-leaderboard-main">
                <!-- Left Panel - Leaderboard -->
                <div class="tkm-leaderboard-panel">
                    <!-- Top 3 Podium -->
                    <div class="tkm-podium-section">
                        <h2>🥇 Top Performers</h2>
                        <div class="tkm-podium" id="top-three-podium">
                            <!-- Top 3 will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Full Leaderboard List -->
                    <div class="tkm-leaderboard-list">
                        <h3>Full Rankings</h3>
                        <div class="tkm-list-header">
                            <span>Rank</span>
                            <span>User</span>
                            <span>Level</span>
                            <span>Points</span>
                            <span>Change</span>
                        </div>
                        <div id="leaderboard-list" class="tkm-list-content">
                            <!-- Leaderboard entries will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <!-- Right Panel - Profile Summary -->
                <div class="tkm-profile-panel">
                    <div class="tkm-profile-card" id="selected-profile">
                        <div class="tkm-profile-header">
                            <img src="<?php echo $current_user_profile['avatar_url']; ?>" alt="Profile" class="tkm-profile-avatar" id="profile-avatar">
                            <div class="tkm-profile-info">
                                <h3 id="profile-name"><?php echo esc_html($current_user_profile['display_name']); ?></h3>
                                <div class="tkm-profile-level">Level <span id="profile-level"><?php echo $current_user_profile['level']; ?></span></div>
                                <div class="tkm-profile-stars" id="profile-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="tkm-star <?php echo $i <= min(5, $current_user_profile['level']) ? 'active' : ''; ?>">⭐</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tkm-profile-badges" id="profile-badges">
                            <?php foreach ($current_user_profile['badges'] as $badge): ?>
                                <span class="tkm-badge" style="background-color: <?php echo $badge['color']; ?>">
                                    <?php echo esc_html($badge['name']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="tkm-achievements-section">
                            <h4>Achievements</h4>
                            <div class="tkm-achievements-grid" id="profile-achievements">
                                <?php foreach (array_slice($current_user_profile['achievements'], 0, 4) as $achievement): ?>
                                    <div class="tkm-achievement-card <?php echo $achievement['earned'] ? 'earned' : 'locked'; ?>">
                                        <span class="tkm-achievement-icon"><?php echo $achievement['icon']; ?></span>
                                        <span class="tkm-achievement-name"><?php echo esc_html($achievement['name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="tkm-progress-section">
                                <div class="tkm-progress-info">
                                    <span>Level <?php echo $current_user_profile['level']; ?> Progress</span>
                                    <span><?php echo $current_user_profile['level_progress']; ?>%</span>
                                </div>
                                <div class="tkm-progress-bar">
                                    <div class="tkm-progress-fill" style="width: <?php echo $current_user_profile['level_progress']; ?>%"></div>
                                </div>
                            </div>
                            
                            <a href="#" class="tkm-more-achievements">5 More Achievements</a>
                        </div>
                        
                        <div class="tkm-user-stats" id="profile-stats">
                            <div class="tkm-stat-item">
                                <span class="tkm-stat-value"><?php echo $current_user_profile['stats']['completed_tasks']; ?></span>
                                <span class="tkm-stat-label">Tasks Completed</span>
                            </div>
                            <div class="tkm-stat-item">
                                <span class="tkm-stat-value"><?php echo $current_user_profile['stats']['referrals']; ?></span>
                                <span class="tkm-stat-label">Referrals</span>
                            </div>
                            <div class="tkm-stat-item">
                                <span class="tkm-stat-value"><?php echo $current_user_profile['stats']['days_active']; ?></span>
                                <span class="tkm-stat-label">Days Active</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="tkm-loading-overlay" style="display: none;">
        <div class="tkm-loading-spinner"></div>
        <p>Loading leaderboard...</p>
    </div>
    
    <!-- Messages -->
    <div id="message-container" class="tkm-message-container"></div>
    
    <?php wp_footer(); ?>
    
    <!-- TKM Door Leaderboard Scripts -->
    <script>
        // Pass PHP variables to JavaScript
        window.tkmLeaderboard = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('tkm_leaderboard_nonce'); ?>',
            currentUserId: <?php echo $current_user_id; ?>,
            initialData: <?php echo json_encode($leaderboard_data); ?>,
            currentUserProfile: <?php echo json_encode($current_user_profile); ?>
        };
    </script>
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-leaderboard.js?ver=1.0.0"></script>
</body>
</html>
