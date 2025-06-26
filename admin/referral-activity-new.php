<?php
// Enhanced Referral Activity Admin Page
global $wpdb;

// Initialize referral system
if (class_exists('Indoor_Tasks_Referral')) {
    $referral_system = new Indoor_Tasks_Referral();
} else {
    // Include the class if not loaded
    require_once INDOOR_TASKS_PATH . 'includes/class-referral.php';
    $referral_system = new Indoor_Tasks_Referral();
}

// Check if enhanced referral tables exist
$referrals_table = $wpdb->prefix . 'indoor_referrals';
$wallet_table = $wpdb->prefix . 'indoor_task_wallet';
$stats_table = $wpdb->prefix . 'indoor_referral_stats';

$tables_exist = $wpdb->get_var("SHOW TABLES LIKE '$referrals_table'") === $referrals_table;

if (!$tables_exist) {
    // Handle table creation
    if (isset($_POST['create_referral_tables']) && wp_verify_nonce($_POST['_wpnonce'], 'create_referral_tables')) {
        $referral_system->create_referral_tables();
        echo '<div class="notice notice-success"><p><strong>Success!</strong> Enhanced referral tables created successfully. Please refresh the page.</p></div>';
        echo '<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>';
        return;
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Referral Activity', 'indoor-tasks'); ?></h1>
        <div class="notice notice-error">
            <p><strong><?php _e('Enhanced referral tables not found!', 'indoor-tasks'); ?></strong></p>
            <p><?php _e('The enhanced referral system requires updated database tables. Click below to create them.', 'indoor-tasks'); ?></p>
            <form method="post" style="margin-top: 15px;">
                <?php wp_nonce_field('create_referral_tables', '_wpnonce'); ?>
                <button type="submit" name="create_referral_tables" class="button button-primary"><?php _e('Create Enhanced Referral Tables', 'indoor-tasks'); ?></button>
            </form>
        </div>
    </div>
    <?php
    return;
}

// Handle manual bonus processing
if (isset($_POST['process_bonus']) && wp_verify_nonce($_POST['_wpnonce'], 'process_referral_bonus')) {
    $referral_id = intval($_POST['referral_id']);
    $result = $referral_system->award_referral_bonus($referral_id);
    
    if ($result) {
        echo '<div class="notice notice-success"><p>Referral bonus processed successfully!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Failed to process referral bonus. Please check the referral status.</p></div>';
    }
}

// Get comprehensive referral statistics
$stats = $referral_system->get_referral_statistics();

// Get additional statistics from new tables
$total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}") ?: 0;
$recent_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $referrals_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)") ?: 0;
$today_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $referrals_table WHERE DATE(created_at) = CURDATE()") ?: 0;
$qualified_waiting = $wpdb->get_var("SELECT COUNT(*) FROM $referrals_table WHERE status = 'qualified'") ?: 0;
$spam_detected = $wpdb->get_var("SELECT COUNT(*) FROM $referrals_table WHERE status = 'rejected'") ?: 0;

// Get top referrers from new system
$top_referrers = $wpdb->get_results("
    SELECT r.referrer_id, u.user_login, u.user_email, u.display_name,
           COUNT(*) as total_referrals,
           SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed_referrals,
           SUM(CASE WHEN r.status = 'completed' THEN r.points_awarded ELSE 0 END) as total_points
    FROM $referrals_table r
    LEFT JOIN {$wpdb->users} u ON r.referrer_id = u.ID
    GROUP BY r.referrer_id
    ORDER BY completed_referrals DESC, total_referrals DESC
    LIMIT 10
");

// Handle filtering
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
$filter_user = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : 0;
$filter_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Build query for filtered results
$query = "SELECT r.*, 
                 u1.display_name as referrer_name, u1.user_email as referrer_email,
                 u2.display_name as referee_name, u2.user_email as referee_email,
                 COALESCE(r.referee_bonus, 0) as referee_bonus,
                 r.bonus_scheduled_date
          FROM $referrals_table r
          LEFT JOIN {$wpdb->users} u1 ON r.referrer_id = u1.ID
          LEFT JOIN {$wpdb->users} u2 ON r.referee_id = u2.ID
          WHERE 1=1";

$params = array();

if ($filter_status) {
    $query .= " AND r.status = %s";
    $params[] = $filter_status;
}

if ($filter_user) {
    $query .= " AND r.referrer_id = %d";
    $params[] = $filter_user;
}

if ($filter_date_from) {
    $query .= " AND r.created_at >= %s";
    $params[] = $filter_date_from . ' 00:00:00';
}

if ($filter_date_to) {
    $query .= " AND r.created_at <= %s";
    $params[] = $filter_date_to . ' 23:59:59';
}

$query .= " ORDER BY r.created_at DESC LIMIT 100";

if (!empty($params)) {
    $filtered_referrals = $wpdb->get_results($wpdb->prepare($query, $params));
} else {
    $filtered_referrals = $wpdb->get_results($query);
}

// Pagination for filtered results
$items_per_page = 20;
$total_items = count($filtered_referrals);
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $items_per_page;
$paged_referrals = array_slice($filtered_referrals, $offset, $items_per_page);
$total_pages = ceil($total_items / $items_per_page);

// Get suspicious activity
$suspicious_activity = $wpdb->get_results("
    SELECT r.*, u.display_name as referrer_name, u.user_email as referrer_email
    FROM $referrals_table r
    LEFT JOIN {$wpdb->users} u ON r.referrer_id = u.ID
    WHERE r.status = 'rejected'
    ORDER BY r.created_at DESC
    LIMIT 20
");
?>

<div class="wrap">
<h1><?php _e('Enhanced Referral Activity', 'indoor-tasks'); ?></h1>

<style>
.referral-main-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    grid-gap: 20px;
    margin-bottom: 30px;
}
.referral-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    grid-gap: 15px;
    margin-bottom: 30px;
}
.stat-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.referral-main-stats .stat-card {
    padding: 25px 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.1);
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
.stat-card h3 {
    margin-top: 0;
    color: #555;
    font-size: 16px;
    font-weight: 600;
}
.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #2271b1;
    margin: 10px 0;
}
.referral-main-stats .stat-number {
    font-size: 36px;
    margin: 15px 0;
}
.stat-subtitle {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
.stat-card.primary { border-top: 4px solid #2271b1; }
.stat-card.success { border-top: 4px solid #46b450; }
.stat-card.warning { border-top: 4px solid #ffb900; }
.stat-card.info { border-top: 4px solid #00a0d2; }
.stat-card.danger { border-top: 4px solid #dc3232; }

.referral-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.referral-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    color: #2271b1;
    display: flex;
    align-items: center;
}
.referral-card h2 i {
    margin-right: 8px;
}
.referral-table {
    width: 100%;
    border-collapse: collapse;
}
.referral-table th, .referral-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.referral-table th {
    background: #f9f9f9;
    font-weight: 600;
}
.referral-table tr:hover {
    background: #f9f9f9;
}
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.status-pending { background: #fff3cd; color: #856404; }
.status-qualified { background: #cce5ff; color: #004085; }
.status-completed { background: #d1edff; color: #0f5132; }
.status-rejected { background: #f8d7da; color: #721c24; }
.status-expired { background: #e2e3e5; color: #41464b; }
.referral-filter {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    grid-gap: 10px;
    margin-bottom: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 5px;
}
.pagination {
    margin-top: 20px;
    text-align: center;
}
.pagination a, .pagination span {
    display: inline-block;
    padding: 5px 10px;
    margin-right: 5px;
    border: 1px solid #ddd;
    border-radius: 3px;
    text-decoration: none;
}
.pagination span.current {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}
</style>

<!-- Primary Statistics Cards -->
<div class="referral-main-stats">
    <div class="stat-card primary">
        <h3><?php _e('Total Referrals', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo number_format($stats['total_referrals']); ?></div>
        <div class="stat-subtitle"><?php echo sprintf(__('%d completed, %d pending', 'indoor-tasks'), $stats['completed_referrals'], $stats['pending_referrals']); ?></div>
    </div>
    <div class="stat-card success">
        <h3><?php _e('Total Points Awarded', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo number_format($stats['total_points_awarded']); ?></div>
        <div class="stat-subtitle"><?php echo sprintf(__('From %d completed referrals', 'indoor-tasks'), $stats['completed_referrals']); ?></div>
    </div>
    <div class="stat-card warning">
        <h3><?php _e('Qualified & Waiting', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo number_format($qualified_waiting); ?></div>
        <div class="stat-subtitle"><?php echo sprintf(__('Awaiting bonus delay', 'indoor-tasks')); ?></div>
    </div>
    <div class="stat-card danger">
        <h3><?php _e('Spam Detected', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo number_format($spam_detected); ?></div>
        <div class="stat-subtitle"><?php echo sprintf(__('Blocked fake referrals', 'indoor-tasks')); ?></div>
    </div>
</div>

<!-- Additional Statistics -->
<div class="referral-stats-grid">
    <div class="stat-card info">
        <h3><?php _e('Recent Activity (30 days)', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo number_format($recent_referrals); ?></div>
        <div class="stat-subtitle"><?php echo __('New referrals', 'indoor-tasks'); ?></div>
    </div>
    <div class="stat-card info">
        <h3><?php _e('Today\'s Referrals', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo number_format($today_referrals); ?></div>
        <div class="stat-subtitle"><?php echo __('Registered today', 'indoor-tasks'); ?></div>
    </div>
    <div class="stat-card primary">
        <h3><?php _e('Success Rate', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo round(($stats['completed_referrals'] / max($stats['total_referrals'], 1)) * 100, 1); ?>%</div>
        <div class="stat-subtitle"><?php echo __('Completed vs Total', 'indoor-tasks'); ?></div>
    </div>
    <div class="stat-card success">
        <h3><?php _e('Anti-Spam Effectiveness', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo round(($spam_detected / max($stats['total_referrals'], 1)) * 100, 1); ?>%</div>
        <div class="stat-subtitle"><?php echo __('Blocked attempts', 'indoor-tasks'); ?></div>
    </div>
</div>

<div class="referral-flex-row" style="display: flex; gap: 20px; flex-wrap: wrap;">
    <div class="referral-card" style="flex: 1; min-width: 300px;">
        <h2><i class="dashicons dashicons-groups"></i> <?php _e('Top Referrers', 'indoor-tasks'); ?></h2>
        <table class="referral-table">
            <tr>
                <th><?php _e('User', 'indoor-tasks'); ?></th>
                <th><?php _e('Total', 'indoor-tasks'); ?></th>
                <th><?php _e('Completed', 'indoor-tasks'); ?></th>
                <th><?php _e('Points Earned', 'indoor-tasks'); ?></th>
            </tr>
            <?php foreach ($top_referrers as $referrer): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($referrer->display_name); ?></strong><br>
                    <small><?php echo esc_html($referrer->user_email); ?></small>
                </td>
                <td><?php echo number_format($referrer->total_referrals); ?></td>
                <td><?php echo number_format($referrer->completed_referrals); ?></td>
                <td><?php echo number_format($referrer->total_points); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="referral-card" style="flex: 1; min-width: 300px;">
        <h2><i class="dashicons dashicons-admin-settings"></i> <?php _e('Current System Settings', 'indoor-tasks'); ?></h2>
        <?php
        $referral_enabled = get_option('indoor_tasks_enable_referral', 1);
        $referrer_bonus = get_option('indoor_tasks_referral_reward_amount', 20);
        $referee_bonus = get_option('indoor_tasks_referee_bonus', 20);
        $min_tasks = get_option('indoor_tasks_referral_min_tasks', 1);
        $require_kyc = get_option('indoor_tasks_referral_require_kyc', 1);
        $delay_hours = get_option('indoor_tasks_referral_delay_hours', 24);
        $spam_detection = get_option('indoor_tasks_detect_fake_referrals', 1);
        ?>
        <div class="settings-grid" style="display: grid; gap: 10px;">
            <div class="setting-row" style="display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #eee;">
                <span><?php _e('System Status:', 'indoor-tasks'); ?></span>
                <strong style="color: <?php echo $referral_enabled ? '#46b450' : '#dc3232'; ?>">
                    <?php echo $referral_enabled ? __('Enabled', 'indoor-tasks') : __('Disabled', 'indoor-tasks'); ?>
                </strong>
            </div>
            <div class="setting-row" style="display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #eee;">
                <span><?php _e('Referrer Bonus:', 'indoor-tasks'); ?></span>
                <strong><?php echo number_format($referrer_bonus); ?> pts</strong>
            </div>
            <div class="setting-row" style="display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #eee;">
                <span><?php _e('Referee Bonus:', 'indoor-tasks'); ?></span>
                <strong><?php echo number_format($referee_bonus); ?> pts</strong>
            </div>
            <div class="setting-row" style="display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #eee;">
                <span><?php _e('Min Tasks Required:', 'indoor-tasks'); ?></span>
                <strong><?php echo number_format($min_tasks); ?></strong>
            </div>
            <div class="setting-row" style="display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #eee;">
                <span><?php _e('KYC Required:', 'indoor-tasks'); ?></span>
                <strong><?php echo $require_kyc ? __('Yes', 'indoor-tasks') : __('No', 'indoor-tasks'); ?></strong>
            </div>
            <div class="setting-row" style="display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #eee;">
                <span><?php _e('Bonus Delay:', 'indoor-tasks'); ?></span>
                <strong><?php echo $delay_hours; ?>h</strong>
            </div>
            <div class="setting-row" style="display: flex; justify-content: space-between; padding: 8px;">
                <span><?php _e('Spam Detection:', 'indoor-tasks'); ?></span>
                <strong style="color: <?php echo $spam_detection ? '#46b450' : '#dc3232'; ?>">
                    <?php echo $spam_detection ? __('On', 'indoor-tasks') : __('Off', 'indoor-tasks'); ?>
                </strong>
            </div>
        </div>
        <div style="margin-top: 15px; text-align: center;">
            <a href="admin.php?page=indoor-tasks-settings#tab-referral" class="button button-primary"><?php _e('Edit Settings', 'indoor-tasks'); ?></a>
            <a href="#" class="button" onclick="location.reload();"><?php _e('Refresh Data', 'indoor-tasks'); ?></a>
        </div>
    </div>
</div>

<?php if (!empty($suspicious_activity)): ?>
<div class="referral-card">
    <h2><i class="dashicons dashicons-warning"></i> <?php _e('Suspicious Activity (Recently Blocked)', 'indoor-tasks'); ?></h2>
    <div style="overflow-x: auto;">
        <table class="referral-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Referrer', 'indoor-tasks'); ?></th>
                    <th><?php _e('Referral Code', 'indoor-tasks'); ?></th>
                    <th><?php _e('Referee Email', 'indoor-tasks'); ?></th>
                    <th><?php _e('Reason', 'indoor-tasks'); ?></th>
                    <th><?php _e('Date', 'indoor-tasks'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suspicious_activity as $activity): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($activity->referrer_name ?: 'Unknown'); ?></strong><br>
                        <small><?php echo esc_html($activity->referrer_email ?: ''); ?></small>
                    </td>
                    <td><code><?php echo esc_html($activity->referral_code); ?></code></td>
                    <td><?php echo esc_html($activity->email ?: 'N/A'); ?></td>
                    <td><small><?php echo esc_html($activity->rejection_reason); ?></small></td>
                    <td><?php echo date('M j, Y H:i', strtotime($activity->created_at)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="referral-card">
    <h2><i class="dashicons dashicons-list-view"></i> <?php _e('Referral Activity Table', 'indoor-tasks'); ?></h2>
    
    <!-- Filter Form -->
    <form method="get" class="referral-filter">
        <input type="hidden" name="page" value="indoor-tasks-referral-activity">
        <div>
            <label for="filter_status"><?php _e('Filter by Status:', 'indoor-tasks'); ?></label>
            <select name="filter_status" id="filter_status" style="width: 100%;">
                <option value=""><?php _e('All Statuses', 'indoor-tasks'); ?></option>
                <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php _e('Pending', 'indoor-tasks'); ?></option>
                <option value="qualified" <?php selected($filter_status, 'qualified'); ?>><?php _e('Qualified', 'indoor-tasks'); ?></option>
                <option value="completed" <?php selected($filter_status, 'completed'); ?>><?php _e('Completed', 'indoor-tasks'); ?></option>
                <option value="rejected" <?php selected($filter_status, 'rejected'); ?>><?php _e('Rejected', 'indoor-tasks'); ?></option>
                <option value="expired" <?php selected($filter_status, 'expired'); ?>><?php _e('Expired', 'indoor-tasks'); ?></option>
            </select>
        </div>
        <div>
            <label for="filter_user"><?php _e('Filter by Referrer:', 'indoor-tasks'); ?></label>
            <select name="filter_user" id="filter_user" style="width: 100%;">
                <option value=""><?php _e('All Users', 'indoor-tasks'); ?></option>
                <?php foreach ($top_referrers as $referrer): ?>
                    <option value="<?php echo $referrer->referrer_id; ?>" <?php selected($filter_user, $referrer->referrer_id); ?>>
                        <?php echo esc_html($referrer->display_name . ' (' . $referrer->user_email . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="date_from"><?php _e('From Date:', 'indoor-tasks'); ?></label>
            <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($filter_date_from); ?>" style="width: 100%;">
        </div>
        <div>
            <label for="date_to"><?php _e('To Date:', 'indoor-tasks'); ?></label>
            <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($filter_date_to); ?>" style="width: 100%;">
        </div>
        <div style="display: flex; align-items: flex-end; gap: 5px;">
            <button type="submit" class="button button-primary"><?php _e('Apply Filter', 'indoor-tasks'); ?></button>
            <a href="admin.php?page=indoor-tasks-referral-activity" class="button"><?php _e('Clear', 'indoor-tasks'); ?></a>
        </div>
    </form>
    
    <!-- Results Summary -->
    <?php if ($filter_status || $filter_user || $filter_date_from || $filter_date_to): ?>
    <div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 10px; margin: 10px 0; border-radius: 4px;">
        <strong><?php _e('Filter Results:', 'indoor-tasks'); ?></strong> 
        <?php echo sprintf(__('Found %d referral(s)', 'indoor-tasks'), count($filtered_referrals)); ?>
        <?php if ($filter_status): ?>
            <?php echo sprintf(__(' with status: %s', 'indoor-tasks'), ucfirst($filter_status)); ?>
        <?php endif; ?>
        <?php if ($filter_date_from || $filter_date_to): ?>
            <?php echo sprintf(__(' from %s to %s', 'indoor-tasks'), $filter_date_from ?: 'beginning', $filter_date_to ?: 'now'); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Referrals Table -->
    <div style="overflow-x: auto;">
        <table class="referral-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Referrer', 'indoor-tasks'); ?></th>
                    <th><?php _e('Referee', 'indoor-tasks'); ?></th>
                    <th><?php _e('Code', 'indoor-tasks'); ?></th>
                    <th><?php _e('Status', 'indoor-tasks'); ?></th>
                    <th><?php _e('Points', 'indoor-tasks'); ?></th>
                    <th><?php _e('Date', 'indoor-tasks'); ?></th>
                    <th><?php _e('Actions', 'indoor-tasks'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($paged_referrals)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px; color: #666;">
                        <?php _e('No referrals found matching the selected criteria.', 'indoor-tasks'); ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($paged_referrals as $referral): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($referral->referrer_name ?: 'Unknown'); ?></strong><br>
                            <small><?php echo esc_html($referral->referrer_email ?: ''); ?></small>
                        </td>
                        <td>
                            <?php if ($referral->referee_name): ?>
                                <strong><?php echo esc_html($referral->referee_name); ?></strong><br>
                                <small><?php echo esc_html($referral->referee_email); ?></small>
                            <?php else: ?>
                                <em><?php echo esc_html($referral->email ?: 'Not registered'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo esc_html($referral->referral_code); ?></code></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($referral->status); ?>">
                                <?php echo ucfirst($referral->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($referral->status === 'completed'): ?>
                                <?php 
                                $referee_bonus = isset($referral->referee_bonus) ? intval($referral->referee_bonus) : 0;
                                $points_awarded = isset($referral->points_awarded) ? intval($referral->points_awarded) : 0;
                                $total_points = $points_awarded + $referee_bonus;
                                ?>
                                <strong><?php echo number_format($total_points); ?></strong><br>
                                <small>(<?php echo number_format($points_awarded); ?> + <?php echo number_format($referee_bonus); ?>)</small>
                            <?php else: ?>
                                <em>Pending</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date('M j, Y H:i', strtotime($referral->created_at)); ?><br>
                            <?php if (isset($referral->bonus_scheduled_date) && $referral->bonus_scheduled_date && $referral->status === 'qualified'): ?>
                                <small>Bonus: <?php echo date('M j, H:i', strtotime($referral->bonus_scheduled_date)); ?></small>
                            <?php elseif ($referral->status === 'qualified'): ?>
                                <small>Awaiting delay period</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($referral->status === 'qualified'): ?>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('process_referral_bonus', '_wpnonce'); ?>
                                    <input type="hidden" name="referral_id" value="<?php echo $referral->id; ?>">
                                    <button type="submit" name="process_bonus" class="button button-small button-primary" 
                                            onclick="return confirm('Process referral bonus now?')">
                                        <?php _e('Process Now', 'indoor-tasks'); ?>
                                    </button>
                                </form>
                            <?php elseif ($referral->status === 'rejected'): ?>
                                <small title="<?php echo esc_attr($referral->rejection_reason); ?>">
                                    <?php _e('Blocked', 'indoor-tasks'); ?>
                                </small>
                            <?php else: ?>
                                <em><?php _e('No action', 'indoor-tasks'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $current_page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo add_query_arg('paged', $i); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <div style="text-align: center; margin-top: 10px; color: #666;">
            <?php echo sprintf(__('Showing %d-%d of %d results', 'indoor-tasks'), 
                ($current_page - 1) * $items_per_page + 1, 
                min($current_page * $items_per_page, $total_items), 
                $total_items); ?>
        </div>
    <?php endif; ?>
</div>

</div>
