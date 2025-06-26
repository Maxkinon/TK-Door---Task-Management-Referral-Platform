<?php
/**
 * Template Name: TKM Door - Dashboard
 * Description: Modern dashboard template for Indoor Tasks plugin
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
$current_user = wp_get_current_user();
$user_id = get_current_user_id();

// Get user stats from database
global $wpdb;

// Get user points from wallet table
$user_points = 0;
$wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
if ($wallet_table_exists) {
    try {
        $points_result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
            $user_id
        ));
        $user_points = $points_result ? intval($points_result) : 0;
    } catch (Exception $e) {
        $user_points = get_user_meta($user_id, 'indoor_tasks_points', true) ?: 0;
    }
} else {
    $user_points = get_user_meta($user_id, 'indoor_tasks_points', true) ?: 0;
}

// Get task statistics
$submissions_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_submissions'") === $wpdb->prefix . 'indoor_task_submissions';
$tasks_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_tasks'") === $wpdb->prefix . 'indoor_tasks';

$completed_tasks = 0;
$pending_tasks = 0;
$available_tasks = 0;
$today_points = 0;

if ($submissions_table_exists) {
    try {
        // Completed tasks
        $completed_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE user_id = %d AND status = 'approved'",
            $user_id
        ));
        
        // Pending tasks
        $pending_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));
        
        // Today's points - using wallet table for accurate points
        $today_points = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(w.points), 0) FROM {$wpdb->prefix}indoor_task_wallet w
             JOIN {$wpdb->prefix}indoor_task_submissions s ON w.reference_id = s.id 
             WHERE w.user_id = %d AND w.type = 'reward' AND s.status = 'approved' AND DATE(w.created_at) = CURDATE()",
            $user_id
        ));
    } catch (Exception $e) {
        // Keep defaults
    }
}

if ($tasks_table_exists) {
    try {
        // Get submitted task IDs for this user
        $submitted_task_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT task_id FROM {$wpdb->prefix}indoor_task_submissions WHERE user_id = %d",
            $user_id
        ));
        
        if (!empty($submitted_task_ids)) {
            $available_tasks = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks 
                WHERE status = 'active' AND id NOT IN (" . implode(',', array_map('intval', $submitted_task_ids)) . ")
            ");
        } else {
            $available_tasks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks WHERE status = 'active'");
        }
    } catch (Exception $e) {
        // Keep default
    }
}

// Get user level information
$user_level = get_user_meta($user_id, 'indoor_tasks_level', true) ?: 1;
$level_progress = get_user_meta($user_id, 'indoor_tasks_level_progress', true) ?: 0;

// Get recent activities (last 5)
$recent_activities = array();
if ($submissions_table_exists) {
    try {
        $recent_activities = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, t.title as task_title, t.points as task_points, w.points as earned_points
             FROM {$wpdb->prefix}indoor_task_submissions s 
             LEFT JOIN {$wpdb->prefix}indoor_tasks t ON s.task_id = t.id 
             LEFT JOIN {$wpdb->prefix}indoor_task_wallet w ON (w.reference_id = s.id AND w.type = 'reward' AND w.user_id = s.user_id)
             WHERE s.user_id = %d 
             ORDER BY s.submitted_at DESC 
             LIMIT 5",
            $user_id
        ));
    } catch (Exception $e) {
        // Keep empty array
    }
}

// Get recent transactions
$recent_transactions = array();
if ($wallet_table_exists) {
    try {
        $recent_transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_task_wallet 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT 5",
            $user_id
        ));
    } catch (Exception $e) {
        // Keep empty array
    }
}

// Check if user can withdraw (needs at least one completed task)
$can_withdraw = $completed_tasks > 0;

// Calculate tasks needed for next level
$tasks_needed_for_next_level = 10; // Default
if ($user_level < 5) {
    $level_requirements = array(
        1 => 10, // Bronze to Silver
        2 => 25, // Silver to Gold  
        3 => 50, // Gold to Platinum
        4 => 100 // Platinum to Diamond
    );
    $tasks_needed_for_next_level = isset($level_requirements[$user_level]) ? 
        max(0, $level_requirements[$user_level] - $completed_tasks) : 0;
}

// Get referral code and stats
$referral_code = get_user_meta($user_id, 'indoor_tasks_referral_code', true);
if (!$referral_code) {
    $referral_code = strtoupper(substr(md5($user_id . time()), 0, 8));
    update_user_meta($user_id, 'indoor_tasks_referral_code', $referral_code);
}

$referral_url = home_url('/?ref=' . $referral_code);
$total_referrals = 0;
$referral_earnings = 0;

// Get referral statistics
$referrals_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_referrals'") === $wpdb->prefix . 'indoor_task_referrals';
if ($referrals_table_exists) {
    try {
        $total_referrals = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_referrals WHERE referrer_id = %d",
            $user_id
        )) ?: 0;
        
        $referral_earnings = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(commission_earned) FROM {$wpdb->prefix}indoor_task_referrals WHERE referrer_id = %d",
            $user_id
        )) ?: 0;
    } catch (Exception $e) {
        // Keep defaults
    }
}

// Additional statistics for new sections
$monthly_earnings = 0;
$monthly_tasks = 0;
$success_rate = 0;
$current_streak = 0;
$member_since = '';
$best_task_points = 0;
$kyc_status = 'Pending';

// Calculate monthly performance
if ($submissions_table_exists) {
    try {
        // Monthly earnings from task submissions (current month) - using wallet table for accurate earnings
        $monthly_earnings_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(w.points), 0) FROM {$wpdb->prefix}indoor_task_wallet w
             JOIN {$wpdb->prefix}indoor_task_submissions s ON w.reference_id = s.id 
             WHERE w.user_id = %d AND w.type = 'reward' AND s.status = 'approved'
             AND YEAR(w.created_at) = YEAR(CURDATE()) AND MONTH(w.created_at) = MONTH(CURDATE())",
            $user_id
        )) ?: 0;
        
        // Monthly tasks completed (current month)
        $monthly_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
             WHERE user_id = %d AND status = 'approved' 
             AND YEAR(reviewed_at) = YEAR(CURDATE()) AND MONTH(reviewed_at) = MONTH(CURDATE())",
            $user_id
        )) ?: 0;
        
        // Calculate success rate
        $total_submissions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE user_id = %d",
            $user_id
        )) ?: 0;
        
        if ($total_submissions > 0) {
            $success_rate = round(($completed_tasks / $total_submissions) * 100, 1);
        }
        
        // Get best task points - using wallet table for accurate points
        $best_task_points = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(w.points) FROM {$wpdb->prefix}indoor_task_wallet w
             JOIN {$wpdb->prefix}indoor_task_submissions s ON w.reference_id = s.id 
             WHERE w.user_id = %d AND w.type = 'reward' AND s.status = 'approved'",
            $user_id
        )) ?: 0;
    } catch (Exception $e) {
        $monthly_earnings_tasks = 0;
        // Keep defaults
    }
}

// Get monthly earnings from wallet table if it exists
$monthly_earnings_wallet = 0;
if ($wallet_table_exists) {
    try {
        $monthly_earnings_wallet = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}indoor_task_wallet 
             WHERE user_id = %d AND type = 'credit'
             AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())",
            $user_id
        )) ?: 0;
    } catch (Exception $e) {
        // Keep default
    }
}

// Combine monthly earnings from both sources
$monthly_earnings = max($monthly_earnings_tasks, $monthly_earnings_wallet);

// Continue with streak calculation if submissions table exists
if ($submissions_table_exists) {
    try {
        
        // Calculate current streak (consecutive days with completed tasks)
        $streak_query = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(reviewed_at) as task_date 
             FROM {$wpdb->prefix}indoor_task_submissions 
             WHERE user_id = %d AND status = 'approved' 
             GROUP BY DATE(reviewed_at) 
             ORDER BY DATE(reviewed_at) DESC 
             LIMIT 30",
            $user_id
        ));
        
        if (!empty($streak_query)) {
            $current_streak = 1;
            $last_date = strtotime($streak_query[0]->task_date);
            
            for ($i = 1; $i < count($streak_query); $i++) {
                $current_date = strtotime($streak_query[$i]->task_date);
                $diff_days = ($last_date - $current_date) / (60 * 60 * 24);
                
                if ($diff_days == 1) {
                    $current_streak++;
                    $last_date = $current_date;
                } else {
                    break;
                }
            }
        }
    } catch (Exception $e) {
        // Keep defaults
    }
}

// Get member since date
$user_registered = $current_user->user_registered;
$member_since = date('d/m/Y', strtotime($user_registered));

// Check KYC status
$kyc_status = get_user_meta($user_id, 'indoor_tasks_kyc_status', true) ?: 'Pending';
$kyc_status = ucfirst($kyc_status);

// Check if user is verified (multiple possible sources)
$is_verified = false;
if ($kyc_status === 'Approved' || $kyc_status === 'Verified') {
    $is_verified = true;
} else {
    // Check other possible verification meta keys
    $verified_meta = get_user_meta($user_id, 'indoor_tasks_verified', true);
    $account_verified = get_user_meta($user_id, 'account_verified', true);
    $user_verified = get_user_meta($user_id, 'user_verified', true);
    
    if ($verified_meta === 'yes' || $verified_meta === '1' || $verified_meta === true ||
        $account_verified === 'yes' || $account_verified === '1' || $account_verified === true ||
        $user_verified === 'yes' || $user_verified === '1' || $user_verified === true) {
        $is_verified = true;
        $kyc_status = 'Approved'; // Update status to match verification
    }
}

// Determine user level name
$level_names = array(
    1 => 'Bronze',
    2 => 'Silver', 
    3 => 'Gold',
    4 => 'Platinum',
    5 => 'Diamond'
);
$user_level_name = isset($level_names[$user_level]) ? $level_names[$user_level] : 'Bronze';

// Next level goal
$next_level = $user_level + 1;
$next_level_name = isset($level_names[$next_level]) ? $level_names[$next_level] : 'Max Level';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <title><?php echo wp_get_document_title(); ?></title>
    
    <?php wp_head(); ?>
    
    <!-- TKM Door Template Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-templates.css?ver=1.0.0">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .tkm-dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .tkm-dashboard-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .tkm-dashboard-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .tkm-stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .tkm-stat-card {
                padding: 1rem;
            }
            
            .tkm-stat-value {
                font-size: 1.5rem;
            }
        }
        
        .tkm-dashboard-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }
        
        .tkm-dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #00954b, #10b981, #06d6a0);
        }
        
        .tkm-welcome-section {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .tkm-welcome-section {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 1.5rem;
            }
            
            .tkm-dashboard-header {
                padding: 1.5rem;
            }
        }
        
        .tkm-user-stats-mini {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .tkm-mini-stat {
            background: rgba(0, 149, 75, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: 1px solid rgba(0, 149, 75, 0.2);
            text-align: center;
            min-width: 80px;
        }
        
        .tkm-mini-stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #00954b;
            display: block;
        }
        
        .tkm-mini-stat-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .tkm-user-stats-mini {
                justify-content: center;
            }
            
            .tkm-mini-stat {
                min-width: 70px;
                padding: 0.4rem 0.8rem;
            }
        }
        
        .tkm-user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #00954b;
            position: relative;
        }
        
        .tkm-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .tkm-verified-badge {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 24px;
            height: 24px;
            background: #00954b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .tkm-verified-badge svg {
            width: 12px;
            height: 12px;
            color: white;
        }
        
        .tkm-user-info h1 {
            font-size: 1.8rem;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }
        
        .tkm-user-level {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .tkm-level-badge {
            background: #00954b;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .tkm-progress-section {
            margin-top: 1rem;
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .tkm-progress-label {
            font-size: 0.875rem;
            color: #374151;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .tkm-progress-bar {
            background: #e5e7eb;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
        }
        
        .tkm-progress-fill {
            background: linear-gradient(90deg, #00954b, #10b981);
            height: 100%;
            border-radius: 5px;
            transition: width 0.3s ease;
            position: relative;
        }
        
        .tkm-progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .tkm-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .tkm-stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .tkm-stat-icon {
            width: 48px;
            height: 48px;
            background: #00954b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
        }
        
        .tkm-stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }
        
        .tkm-stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .tkm-content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .tkm-content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .tkm-quick-actions {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .tkm-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 1rem;
        }
        
        .tkm-action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .tkm-action-btn {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            padding: 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: #4a5568;
            text-align: center;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tkm-action-btn:hover {
            border-color: #00954b;
            background: #f0fdf4;
            transform: translateY(-2px);
        }
        
        .tkm-action-btn svg {
            width: 24px;
            height: 24px;
        }
        
        .tkm-recent-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tkm-activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .tkm-activity-item:last-child {
            border-bottom: none;
        }
        
        .tkm-activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
        }
        
        .tkm-activity-approved {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .tkm-activity-pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .tkm-activity-rejected {
            background: #fecaca;
            color: #dc2626;
        }
        
        .tkm-activity-info {
            flex: 1;
        }
        
        .tkm-activity-title {
            font-weight: 500;
            color: #1a202c;
            margin-bottom: 0.25rem;
            font-size: 13px;
        }
        
        .tkm-activity-meta {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .tkm-activity-points {
            font-weight: 600;
            color: #00954b;
        }
        
        .tkm-no-data {
            text-align: center;
            color: #6b7280;
            padding: 2rem;
            font-style: italic;
        }
        
        /* New Dashboard Sections Styles - Compact & Responsive */
        .tkm-detailed-stats,
        .tkm-monthly-performance,
        .tkm-account-status,
        .tkm-earnings-detail,
        .tkm-referral-program {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
        }
        
        .tkm-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.75rem;
        }
        
        .tkm-detailed-grid,
        .tkm-monthly-grid,
        .tkm-status-grid,
        .tkm-earnings-grid,
        .tkm-referral-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 0.75rem;
            margin-top: 0.75rem;
        }
        
        .tkm-detail-card,
        .tkm-earnings-card,
        .tkm-referral-card {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 8px;
            border-left: 3px solid #00954b;
            transition: all 0.2s ease;
        }
        
        .tkm-detail-card:hover,
        .tkm-earnings-card:hover,
        .tkm-referral-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .tkm-detail-icon,
        .tkm-earnings-icon,
        .tkm-referral-icon {
            color: #00954b;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }
        
        .tkm-detail-content,
        .tkm-earnings-content,
        .tkm-referral-content {
            flex: 1;
        }
        
        .tkm-detail-value,
        .tkm-earnings-amount {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.25rem;
        }
        
        .tkm-detail-label,
        .tkm-earnings-label,
        .tkm-referral-label {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .tkm-monthly-card {
            background: linear-gradient(135deg, #00954b, #059669);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 149, 75, 0.2);
        }
        
        .tkm-monthly-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .tkm-monthly-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .tkm-monthly-period {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .tkm-monthly-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        
        .tkm-monthly-stat {
            text-align: center;
            padding: 0.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
        }
        
        .tkm-monthly-value {
            display: block;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .tkm-monthly-label {
            font-size: 0.75rem;
            opacity: 0.9;
        }
        
        .tkm-status-card {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.75rem;
        }
        
        .tkm-status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .tkm-status-label {
            color: #64748b;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .tkm-status-value {
            font-weight: 600;
            color: #1a202c;
            font-size: 0.85rem;
        }
        
        .tkm-referral-url {
            font-size: 0.75rem;
            color: #64748b;
            word-break: break-all;
            margin-top: 0.25rem;
        }
        
        .tkm-copy-btn {
            background: #00954b;
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-left: 0.5rem;
            flex-shrink: 0;
        }
        
        .tkm-copy-btn:hover {
            background: #059669;
            transform: scale(1.05);
        }
        
        .tkm-level-badge {
            background: #00954b;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .tkm-kyc-approved {
            color: #00954b;
        }
        
        .tkm-kyc-pending {
            color: #f59e0b;
        }
        
        .tkm-kyc-rejected {
            color: #ef4444;
        }
        
        /* Responsive adjustments for new sections */
        @media (max-width: 768px) {
            .tkm-detailed-grid,
            .tkm-earnings-grid,
            .tkm-referral-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .tkm-status-card {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .tkm-monthly-stats {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .tkm-detail-card,
            .tkm-earnings-card,
            .tkm-referral-card {
                padding: 0.75rem;
            }
            
            .tkm-detail-value,
            .tkm-earnings-amount {
                font-size: 1.1rem;
            }
            
            .tkm-detail-label,
            .tkm-earnings-label,
            .tkm-referral-label {
                font-size: 0.75rem;
            }
            
            .tkm-detailed-stats,
            .tkm-monthly-performance,
            .tkm-account-status,
            .tkm-earnings-detail,
            .tkm-referral-program {
                padding: 0.75rem;
            }
            
            .tkm-section-title {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .tkm-detailed-grid,
            .tkm-earnings-grid,
            .tkm-referral-grid,
            .tkm-status-card {
                gap: 0.25rem;
            }
            
            .tkm-detail-card,
            .tkm-earnings-card,
            .tkm-referral-card {
                padding: 0.5rem;
            }
            
            .tkm-monthly-card {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="tkm-dashboard-container">
        <!-- Include Sidebar Navigation -->
        <?php include INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'; ?>
        
        <div class="tkm-dashboard-content">
            <!-- Welcome Section -->
            <div class="tkm-dashboard-header">
                <div class="tkm-welcome-section">
                    <div class="tkm-user-avatar">
                        <?php echo get_avatar($user_id, 80, '', '', array('class' => 'tkm-avatar-img')); ?>
                        <?php if ($is_verified): ?>
                            <div class="tkm-verified-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <polyline points="9 11 12 14 22 4"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="tkm-user-info">
                        <h1>Welcome back, <?php echo esc_html($current_user->display_name); ?>!</h1>
                        <div class="tkm-user-level">
                            <span class="tkm-level-badge">Level <?php echo $user_level; ?> - <?php echo $user_level_name; ?></span>
                            <?php if ($is_verified): ?>
                                <span style="color: #00954b; font-size: 0.8rem; margin-left: 0.5rem;">✓ Verified Account</span>
                            <?php endif; ?>
                        </div>
                        <div class="tkm-progress-section">
                            <div class="tkm-progress-label">
                                <?php if ($user_level >= 5): ?>
                                    🏆 Maximum level reached! You're a Diamond member!
                                <?php elseif ($tasks_needed_for_next_level > 0): ?>
                                    🎯 Complete <?php echo $tasks_needed_for_next_level; ?> more tasks to reach <?php echo $next_level_name; ?> level
                                <?php else: ?>
                                    ⚡ Ready to level up to <?php echo $next_level_name; ?>!
                                <?php endif; ?>
                            </div>
                            <div class="tkm-progress-bar">
                                <div class="tkm-progress-fill" style="width: <?php echo min(100, $level_progress); ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="tkm-user-stats-mini">
                        <div class="tkm-mini-stat">
                            <span class="tkm-mini-stat-value"><?php echo number_format($user_points ?: 0); ?></span>
                            <span class="tkm-mini-stat-label">Balance</span>
                        </div>
                        <div class="tkm-mini-stat">
                            <span class="tkm-mini-stat-value"><?php echo number_format($completed_tasks ?: 0); ?></span>
                            <span class="tkm-mini-stat-label">Completed</span>
                        </div>
                        <div class="tkm-mini-stat">
                            <span class="tkm-mini-stat-value"><?php echo number_format($current_streak ?: 0); ?></span>
                            <span class="tkm-mini-stat-label">Streak</span>
                        </div>
                        <div class="tkm-mini-stat">
                            <span class="tkm-mini-stat-value"><?php echo number_format($success_rate, 1); ?>%</span>
                            <span class="tkm-mini-stat-label">Success</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="tkm-stats-grid">
                <div class="tkm-stat-card" data-stat="today-points">
                    <div class="tkm-stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </div>
                    <div class="tkm-stat-value"><?php echo number_format($today_points ?: 0); ?></div>
                    <div class="tkm-stat-label">Points Today</div>
                </div>
                
                <div class="tkm-stat-card" data-stat="completed-tasks">
                    <div class="tkm-stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 11 12 14 22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                    </div>
                    <div class="tkm-stat-value"><?php echo number_format($completed_tasks ?: 0); ?></div>
                    <div class="tkm-stat-label">Completed Tasks</div>
                </div>
                
                <div class="tkm-stat-card" data-stat="pending-tasks">
                    <div class="tkm-stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="tkm-stat-value"><?php echo number_format($pending_tasks ?: 0); ?></div>
                    <div class="tkm-stat-label">Pending Tasks</div>
                </div>
                
                <div class="tkm-stat-card" data-stat="available-tasks">
                    <div class="tkm-stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                        </svg>
                    </div>
                    <div class="tkm-stat-value"><?php echo number_format($available_tasks ?: 0); ?></div>
                    <div class="tkm-stat-label">Available Tasks</div>
                </div>
            </div>
            
            <!-- New Dashboard Sections -->
            
            <!-- Detailed Statistics Section -->
            <div class="tkm-detailed-stats">
                <h2 class="tkm-section-title">Detailed Statistics</h2>
                <div class="tkm-detailed-grid">
                    <div class="tkm-detail-card">
                        <div class="tkm-detail-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="20" x2="18" y2="10"/>
                                <line x1="12" y1="20" x2="12" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                        </div>
                        <div class="tkm-detail-content">
                            <div class="tkm-detail-value"><?php echo number_format($success_rate, 1); ?>%</div>
                            <div class="tkm-detail-label">Success Rate</div>
                        </div>
                    </div>
                    
                    <div class="tkm-detail-card">
                        <div class="tkm-detail-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 9l6 6 6-6"/>
                            </svg>
                        </div>
                        <div class="tkm-detail-content">
                            <div class="tkm-detail-value"><?php echo number_format($best_task_points ?: 0); ?></div>
                            <div class="tkm-detail-label">Best Task Points</div>
                        </div>
                    </div>
                    
                    <div class="tkm-detail-card">
                        <div class="tkm-detail-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        </div>
                        <div class="tkm-detail-content">
                            <div class="tkm-detail-value"><?php echo $current_streak; ?></div>
                            <div class="tkm-detail-label">Current Streak</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Monthly Performance Section -->
            <div class="tkm-monthly-performance">
                <h2 class="tkm-section-title">Monthly Performance</h2>
                <div class="tkm-monthly-grid">
                    <div class="tkm-monthly-card">
                        <div class="tkm-monthly-header">
                            <h3>This Month</h3>
                            <span class="tkm-monthly-period"><?php echo date('F Y'); ?></span>
                        </div>
                        <div class="tkm-monthly-stats">
                            <div class="tkm-monthly-stat">
                                <span class="tkm-monthly-value"><?php echo number_format($monthly_earnings ?: 0); ?></span>
                                <span class="tkm-monthly-label">Points Earned</span>
                            </div>
                            <div class="tkm-monthly-stat">
                                <span class="tkm-monthly-value"><?php echo number_format($monthly_tasks ?: 0); ?></span>
                                <span class="tkm-monthly-label">Tasks Completed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Status Section -->
            <div class="tkm-account-status">
                <h2 class="tkm-section-title">Account Status</h2>
                <div class="tkm-status-grid">
                    <div class="tkm-status-card">
                        <div class="tkm-status-item">
                            <span class="tkm-status-label">Level</span>
                            <span class="tkm-status-value tkm-level-badge"><?php echo esc_html($user_level_name); ?> (<?php echo $user_level; ?>)</span>
                        </div>
                        <div class="tkm-status-item">
                            <span class="tkm-status-label">KYC Status</span>
                            <span class="tkm-status-value tkm-kyc-<?php echo strtolower($kyc_status); ?>"><?php echo esc_html($kyc_status); ?></span>
                        </div>
                        <div class="tkm-status-item">
                            <span class="tkm-status-label">Member Since</span>
                            <span class="tkm-status-value"><?php echo esc_html($member_since); ?></span>
                        </div>
                        <div class="tkm-status-item">
                            <span class="tkm-status-label">Next Goal</span>
                            <span class="tkm-status-value"><?php echo esc_html($next_level_name); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Earnings Detail Section -->
            <div class="tkm-earnings-detail">
                <h2 class="tkm-section-title">Earning Details</h2>
                <div class="tkm-earnings-grid">
                    <div class="tkm-earnings-card">
                        <div class="tkm-earnings-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M16 8l-8 8"/>
                                <path d="M16 16l-8-8"/>
                            </svg>
                        </div>
                        <div class="tkm-earnings-content">
                            <div class="tkm-earnings-amount"><?php echo number_format($user_points ?: 0); ?></div>
                            <div class="tkm-earnings-label">Total Balance</div>
                        </div>
                    </div>
                    
                    <div class="tkm-earnings-card">
                        <div class="tkm-earnings-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        </div>
                        <div class="tkm-earnings-content">
                            <div class="tkm-earnings-amount"><?php echo number_format($today_points ?: 0); ?></div>
                            <div class="tkm-earnings-label">Today's Earnings</div>
                        </div>
                    </div>
                    
                    <div class="tkm-earnings-card">
                        <div class="tkm-earnings-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                        <div class="tkm-earnings-content">
                            <div class="tkm-earnings-amount"><?php echo number_format($monthly_earnings ?: 0); ?></div>
                            <div class="tkm-earnings-label">This Month</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Referral Program Section -->
            <div class="tkm-referral-program">
                <h2 class="tkm-section-title">🎁 Referral Program</h2>
                <div class="tkm-referral-grid">
                    <div class="tkm-referral-card">
                        <div class="tkm-referral-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <div class="tkm-referral-content">
                            <div class="tkm-referral-label">Your Referral Code</div>
                            <div class="tkm-detail-value"><?php echo esc_html($referral_code); ?></div>
                            <div class="tkm-referral-label">Referral Link</div>
                            <div class="tkm-referral-url"><?php echo esc_html($referral_url); ?></div>
                        </div>
                        <button class="tkm-copy-btn" onclick="copyReferralCode('<?php echo esc_js($referral_url); ?>')">
                            Copy URL
                        </button>
                    </div>
                    
                    <div class="tkm-referral-card">
                        <div class="tkm-referral-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <div class="tkm-referral-content">
                            <div class="tkm-detail-value"><?php echo number_format($total_referrals ?: 0); ?></div>
                            <div class="tkm-referral-label">Total Referrals</div>
                        </div>
                    </div>
                    
                    <div class="tkm-referral-card">
                        <div class="tkm-referral-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                        <div class="tkm-referral-content">
                            <div class="tkm-detail-value"><?php echo number_format($referral_earnings ?: 0); ?></div>
                            <div class="tkm-referral-label">Referral Earnings</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tkm-content-grid">
                <div>
                    <!-- Quick Actions -->
                    <div class="tkm-quick-actions">
                        <h2 class="tkm-section-title">Quick Actions</h2>
                        <div class="tkm-action-grid">
                            <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('tasks')) : home_url('/tasks/'); ?>" class="tkm-action-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                                </svg>
                                Browse Tasks
                            </a>
                            
                            <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('wallet')) : home_url('/wallet/'); ?>" class="tkm-action-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="3" width="15" height="13"/>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                </svg>
                                View Wallet
                            </a>
                            
                            <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('withdrawal')) : home_url('/withdrawal/'); ?>" class="tkm-action-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                                Withdraw
                            </a>
                            
                            <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('referrals')) : home_url('/referrals/'); ?>" class="tkm-action-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                Refer Friends
                            </a>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <div class="tkm-recent-section">
                        <h2 class="tkm-section-title">Recent Activities</h2>
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="tkm-activity-item">
                                    <div class="tkm-activity-icon tkm-activity-<?php echo esc_attr($activity->status); ?>">
                                        <?php if ($activity->status === 'approved'): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="9 11 12 14 22 4"/>
                                            </svg>
                                        <?php elseif ($activity->status === 'pending'): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <polyline points="12 6 12 12 16 14"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="15" y1="9" x2="9" y2="15"/>
                                                <line x1="9" y1="9" x2="15" y2="15"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tkm-activity-info">
                                        <div class="tkm-activity-title"><?php echo esc_html($activity->task_title ?: 'Task #' . $activity->task_id); ?></div>
                                        <div class="tkm-activity-meta">
                                            <?php echo ucfirst($activity->status); ?> • <?php echo date('M j, Y', strtotime($activity->submitted_at)); ?>
                                            <?php if ($activity->status === 'approved' && isset($activity->earned_points) && $activity->earned_points > 0): ?>
                                                <span class="tkm-activity-points">+<?php echo number_format($activity->earned_points ?: 0); ?> points</span>
                                            <?php elseif ($activity->status === 'approved' && isset($activity->task_points) && $activity->task_points > 0): ?>
                                                <span class="tkm-activity-points">+<?php echo number_format($activity->task_points ?: 0); ?> points</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="tkm-no-data">No recent activities found. Start completing tasks to see your progress here!</div>
                        <?php endif; ?>
                    </div>
                </div
                
                <div>
                    <!-- Recent Transactions -->
                    <div class="tkm-recent-section">
                        <h2 class="tkm-section-title">Recent Transactions</h2>
                        <?php if (!empty($recent_transactions)): ?>
                            <?php foreach ($recent_transactions as $transaction): ?>
                                <div class="tkm-activity-item">
                                    <div class="tkm-activity-icon <?php echo $transaction->type === 'credit' ? 'tkm-activity-approved' : 'tkm-activity-pending'; ?>">
                                        <?php if ($transaction->type === 'credit'): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="5" x2="12" y2="19"/>
                                                <polyline points="19 12 12 19 5 12"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="19" x2="12" y2="5"/>
                                                <polyline points="5 12 12 5 19 12"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tkm-activity-info">
                                        <div class="tkm-activity-title"><?php echo esc_html($transaction->description); ?></div>
                                        <div class="tkm-activity-meta">
                                            <?php echo date('M j, Y', strtotime($transaction->created_at)); ?> • 
                                            <span class="tkm-activity-points">
                                                <?php echo $transaction->type === 'credit' ? '+' : '-'; ?><?php echo abs($transaction->points); ?> points
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="tkm-no-data">No transactions found yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php wp_footer(); ?>
    
    <!-- TKM Door Template Scripts -->
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-templates.js?ver=1.0.0"></script>
    
    <script>
        // Copy referral code function
        function copyReferralCode(code) {
            navigator.clipboard.writeText(code).then(function() {
                // Success feedback
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.background = '#10b981';
                
                setTimeout(function() {
                    btn.textContent = originalText;
                    btn.style.background = '#00954b';
                }, 2000);
            }).catch(function() {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.background = '#10b981';
                
                setTimeout(function() {
                    btn.textContent = originalText;
                    btn.style.background = '#00954b';
                }, 2000);
            });
        }
        
        // Add copy URL functionality 
        function copyReferralUrl(url) {
            navigator.clipboard.writeText(url).then(function() {
                alert('Referral URL copied to clipboard!');
            });
        }
    </script>
</body>
</html>
