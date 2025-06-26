<?php
// Admin dashboard overview
?><div class="wrap">
<h1><?php _e('Indoor Tasks - Admin Dashboard', 'indoor-tasks'); ?></h1>

<?php
global $wpdb;
$users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
$tasks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks");
$subs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions");
$pending_kyc = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_kyc WHERE status = 'pending'");
$pending_withdraw = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_withdrawals WHERE status = 'pending'");

// Stats for summary cards
$total_tasks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks");
$completed_tasks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE status = 'approved'");
$pending_review = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE status = 'pending'");
$rejected_tasks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE status = 'rejected'");
$completion_rate = $total_tasks ? round(($completed_tasks / $total_tasks) * 100, 2) : 0;

$total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
$new_users = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s", date('Y-m-d', strtotime('-30 days'))));
$active_users = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}indoor_task_submissions WHERE submitted_at >= %s", date('Y-m-d', strtotime('-30 days'))));
$pending_kyc = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_kyc WHERE status = 'pending'");
$avg_tasks_per_user = $total_users ? round($completed_tasks / $total_users, 2) : 0;

$total_issued = $wpdb->get_var("SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet WHERE points > 0");
$total_redeemed = $wpdb->get_var("SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet WHERE points < 0");
$points_in_circ = $wpdb->get_var("SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet");
$avg_points_per_user = $total_users ? round($points_in_circ / $total_users, 2) : 0;
$pending_withdraw_value = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}indoor_task_withdrawals WHERE status = 'pending'");

// Recent users query
$recent_users = $wpdb->get_results("SELECT ID, user_login, user_email, user_registered FROM {$wpdb->users} ORDER BY user_registered DESC LIMIT 5");

// Recent tasks query
$recent_tasks = $wpdb->get_results("SELECT id, title, reward_points, deadline, created_at FROM {$wpdb->prefix}indoor_tasks ORDER BY created_at DESC LIMIT 5");

// Recent submissions query
$recent_submissions = $wpdb->get_results("SELECT s.id, s.status, s.submitted_at, t.title, u.user_login 
    FROM {$wpdb->prefix}indoor_task_submissions s 
    LEFT JOIN {$wpdb->prefix}indoor_tasks t ON s.task_id = t.id 
    LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID 
    ORDER BY s.submitted_at DESC LIMIT 5");
?>

<!-- Dashboard style -->
<style>
.it-dashboard-container {
    max-width: 1200px;
}
.it-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    padding: 24px;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.it-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.it-summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    grid-gap: 20px;
    margin-bottom: 30px;
}
.it-stat-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    padding: 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.it-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
}
.it-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
}
.it-stat-card.primary::before { background: #2271b1; }
.it-stat-card.success::before { background: #46b450; }
.it-stat-card.warning::before { background: #ffb900; }
.it-stat-card.danger::before { background: #dc3232; }
.it-stat-card.info::before { background: #00a0d2; }

.it-stat-icon {
    width: 50px;
    height: 50px;
    margin: 0 auto 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f0f5ff;
    color: #2271b1;
    font-size: 20px;
}
.it-stat-card.primary .it-stat-icon { background: #eef5ff; color: #2271b1; }
.it-stat-card.success .it-stat-icon { background: #eef9ee; color: #46b450; }
.it-stat-card.warning .it-stat-icon { background: #fff8e5; color: #ffb900; }
.it-stat-card.danger .it-stat-icon { background: #fde8e8; color: #dc3232; }
.it-stat-card.info .it-stat-icon { background: #e5f5fa; color: #00a0d2; }

.it-stat-number {
    font-size: 28px;
    font-weight: bold;
    margin: 5px 0;
    color: #333;
}
.it-stat-card.primary .it-stat-number { color: #2271b1; }
.it-stat-card.success .it-stat-number { color: #46b450; }
.it-stat-card.warning .it-stat-number { color: #ffb900; }
.it-stat-card.danger .it-stat-number { color: #dc3232; }
.it-stat-card.info .it-stat-number { color: #00a0d2; }

.it-stat-title {
    font-size: 16px;
    font-weight: 500;
    color: #555;
    margin-bottom: 5px;
}
.it-stat-subtitle {
    font-size: 13px;
    color: #777;
}
.it-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    grid-gap: 20px;
    margin-bottom: 30px;
}
.it-stats-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    padding: 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.it-stats-card h3 {
    margin-top: 0;
    font-size: 16px;
    color: #555;
}
.it-stats-number {
    font-size: 36px;
    font-weight: bold;
    color: #2271b1;
    margin: 15px 0;
}
.it-stats-label {
    color: #666;
    font-size: 13px;
}
.it-stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #2271b1;
}
.it-flex-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.it-column {
    flex: 1;
    min-width: 300px;
}
.it-card h3 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    color: #2271b1;
    display: flex;
    align-items: center;
}
.it-card h3 i {
    margin-right: 8px;
}
.it-table {
    width: 100%;
    border-collapse: collapse;
}
.it-table th, .it-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.it-table th {
    font-weight: 600;
    color: #444;
    background: #f9f9f9;
}
.it-table tr:hover {
    background: #f9f9f9;
}
.it-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.it-badge-success {background: #e3f1e7; color: #0a8528;}
.it-badge-warning {background: #fff8e5; color: #b97e00;}
.it-badge-danger {background: #ffe9e9; color: #d63638;}
.it-quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    grid-gap: 20px;
    margin-bottom: 30px;
}
.it-stat-box {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 15px;
    text-align: center;
}
.it-stat-box .number {
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
    margin: 5px 0;
}
.it-stat-box .label {
    font-size: 13px;
    color: #666;
}
.it-section-header {
    margin: 30px 0 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    color: #333;
    font-size: 18px;
}
.it-action-row {
    margin-top: 30px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.it-action-row .button {
    padding: 6px 15px;
}
</style>

<div class="it-dashboard-container">
<!-- Summary stats cards -->
<div class="it-summary-stats">
    <div class="it-stat-card primary">
        <div class="it-stat-icon">
            <span class="dashicons dashicons-list-view"></span>
        </div>
        <div class="it-stat-title">Tasks</div>
        <div class="it-stat-number"><?= $total_tasks ?></div>
        <div class="it-stat-subtitle"><?= $pending_review ?> pending review</div>
    </div>
    <div class="it-stat-card success">
        <div class="it-stat-icon">
            <span class="dashicons dashicons-groups"></span>
        </div>
        <div class="it-stat-title">Users</div>
        <div class="it-stat-number"><?= $total_users ?></div>
        <div class="it-stat-subtitle"><?= $new_users ?> new in 30 days</div>
    </div>
    <div class="it-stat-card info">
        <div class="it-stat-icon">
            <span class="dashicons dashicons-chart-area"></span>
        </div>
        <div class="it-stat-title">Points Issued</div>
        <div class="it-stat-number"><?= intval($total_issued) ?></div>
        <div class="it-stat-subtitle"><?= intval($points_in_circ) ?> in circulation</div>
    </div>
    <div class="it-stat-card warning">
        <div class="it-stat-icon">
            <span class="dashicons dashicons-money-alt"></span>
        </div>
        <div class="it-stat-title">Withdrawals</div>
        <div class="it-stat-number"><?= $pending_withdraw ?></div>
        <div class="it-stat-subtitle">Value: <?= intval($pending_withdraw_value) ?></div>
    </div>
    <div class="it-stat-card danger">
        <div class="it-stat-icon">
            <span class="dashicons dashicons-id-alt"></span>
        </div>
        <div class="it-stat-title">KYC Pending</div>
        <div class="it-stat-number"><?= $pending_kyc ?></div>
        <div class="it-stat-subtitle">Requires verification</div>
    </div>
</div>

<h2 class="it-section-header">Performance Metrics</h2>
<!-- Detailed stats -->
<div class="it-stats-grid">
    <div class="it-stats-card">
        <h3>Task Completion Rate</h3>
        <div class="it-stats-number"><?= $completion_rate ?>%</div>
        <div class="it-stats-label"><?= $completed_tasks ?> completed of <?= $total_tasks ?> tasks</div>
    </div>
    <div class="it-stats-card">
        <h3>Active Users (30 days)</h3>
        <div class="it-stats-number"><?= $active_users ?></div>
        <div class="it-stats-label"><?= round(($active_users/$total_users)*100, 1) ?>% of total users</div>
    </div>
    <div class="it-stats-card">
        <h3>Average Points per User</h3>
        <div class="it-stats-number"><?= $avg_points_per_user ?></div>
        <div class="it-stats-label">From <?= intval($points_in_circ) ?> total points</div>
    </div>
</div>

<h2 class="it-section-header">Recent Activity</h2>
<!-- Recent activity -->
<div class="it-flex-row">
    <div class="it-column">
        <div class="it-card">
            <h3><i class="dashicons dashicons-admin-users"></i> Recently Registered Users</h3>
            <table class="it-table">
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Registered</th>
                </tr>
                <?php foreach($recent_users as $user): ?>
                <tr>
                    <td><a href="admin.php?page=indoor-tasks-manage-users&user_id=<?= $user->ID ?>"><?= esc_html($user->user_login) ?></a></td>
                    <td><?= esc_html($user->user_email) ?></td>
                    <td><?= date('M j, Y', strtotime($user->user_registered)) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    <div class="it-column">
        <div class="it-card">
            <h3><i class="dashicons dashicons-clipboard"></i> Recent Tasks</h3>
            <table class="it-table">
                <tr>
                    <th>Task</th>
                    <th>Points</th>
                    <th>Deadline</th>
                </tr>
                <?php foreach($recent_tasks as $task): ?>
                <tr>
                    <td><a href="admin.php?page=indoor-tasks-tasks-list&task_id=<?= $task->id ?>"><?= esc_html($task->title) ?></a></td>
                    <td><?= $task->reward_points ?></td>
                    <td><?= date('M j, Y', strtotime($task->deadline)) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<div class="it-card">
    <h3><i class="dashicons dashicons-list-view"></i> Recent Task Submissions</h3>
    <table class="it-table">
        <tr>
            <th>User</th>
            <th>Task</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>Actions</th>
        </tr>
        <?php foreach($recent_submissions as $sub): ?>
        <tr>
            <td><?= esc_html($sub->user_login) ?></td>
            <td><?= esc_html($sub->title) ?></td>
            <td>
                <?php if($sub->status == 'approved'): ?>
                    <span class="it-badge it-badge-success">Approved</span>
                <?php elseif($sub->status == 'pending'): ?>
                    <span class="it-badge it-badge-warning">Pending</span>
                <?php else: ?>
                    <span class="it-badge it-badge-danger">Rejected</span>
                <?php endif; ?>
            </td>
            <td><?= date('M j, Y H:i', strtotime($sub->submitted_at)) ?></td>
            <td>
                <?php if($sub->status == 'pending'): ?>
                <a href="admin.php?page=indoor-tasks-task-submissions&submission_id=<?= $sub->id ?>" class="button button-small">Review</a>
                <?php else: ?>
                <a href="admin.php?page=indoor-tasks-task-submissions&submission_id=<?= $sub->id ?>" class="button button-small">View</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="it-action-row">
  <a href="admin.php?page=indoor-tasks-add-task" class="button button-primary">Add New Task</a>
  <a href="admin.php?page=indoor-tasks-task-submissions" class="button">Review Submissions</a>
  <a href="admin.php?page=indoor-tasks-manage-users" class="button">Manage Users</a>
  <a href="admin.php?page=indoor-tasks-withdrawal-requests" class="button">Withdrawals</a>
  <a href="admin.php?page=indoor-tasks-manage-kyc" class="button">KYC</a>
  <a href="admin.php?page=indoor-tasks-settings" class="button">Settings</a>
</div>
</div>
