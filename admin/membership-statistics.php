<?php
// Membership Statistics (admin)
global $wpdb;

// Get user counts by status
$total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}") ?: 0;
$active_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} WHERE user_status = 0") ?: 0;
$inactive_users = $total_users - $active_users;

// Get KYC statistics
$kyc_approved = count(get_users([
    'meta_key' => 'indoor_tasks_kyc_status',
    'meta_value' => 'approved',
    'fields' => 'ID'
]));
$kyc_pending = count(get_users([
    'meta_key' => 'indoor_tasks_kyc_status',
    'meta_value' => 'pending',
    'fields' => 'ID'
]));
$kyc_rejected = count(get_users([
    'meta_key' => 'indoor_tasks_kyc_status',
    'meta_value' => 'rejected',
    'fields' => 'ID'
]));
$kyc_not_submitted = $total_users - ($kyc_approved + $kyc_pending + $kyc_rejected);

// Get registration trends (last 12 months)
$monthly_registrations = $wpdb->get_results("
    SELECT 
        YEAR(user_registered) as year,
        MONTH(user_registered) as month,
        COUNT(*) as count
    FROM {$wpdb->users} 
    WHERE user_registered >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY YEAR(user_registered), MONTH(user_registered)
    ORDER BY year DESC, month DESC
    LIMIT 12
") ?: [];

// Get country distribution
$country_stats = [];
$users_with_countries = get_users([
    'meta_key' => 'indoor_tasks_country',
    'meta_compare' => 'EXISTS',
    'fields' => 'ID'
]);

foreach ($users_with_countries as $user_id) {
    $country = get_user_meta($user_id, 'indoor_tasks_country', true);
    if ($country) {
        $country_stats[$country] = ($country_stats[$country] ?? 0) + 1;
    }
}
arsort($country_stats);

// Get wallet statistics
$wallet_table = $wpdb->prefix . 'indoor_task_wallet';
$wallet_exists = $wpdb->get_var("SHOW TABLES LIKE '$wallet_table'") === $wallet_table;

$users_with_points = 0;
$total_points_distributed = 0;
$avg_points_per_user = 0;

if ($wallet_exists) {
    $users_with_points = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $wallet_table WHERE points > 0") ?: 0;
    $total_points_distributed = $wpdb->get_var("SELECT SUM(points) FROM $wallet_table WHERE points > 0") ?: 0;
    $avg_points_per_user = $users_with_points > 0 ? round($total_points_distributed / $users_with_points, 2) : 0;
}

// Recent activity (last 30 days)
$recent_registrations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY)") ?: 0;
$recent_kyc_submissions = count(get_users([
    'meta_key' => 'indoor_tasks_kyc_status',
    'meta_value' => 'pending',
    'fields' => 'ID',
    'date_query' => [
        [
            'key' => 'user_registered',
            'value' => date('Y-m-d', strtotime('-30 days')),
            'compare' => '>='
        ]
    ]
]));

// Country mapping
$countries = [
    'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina',
    'AU' => 'Australia', 'AT' => 'Austria', 'BD' => 'Bangladesh', 'BE' => 'Belgium',
    'BR' => 'Brazil', 'CA' => 'Canada', 'CN' => 'China', 'CO' => 'Colombia',
    'EG' => 'Egypt', 'FR' => 'France', 'DE' => 'Germany', 'GH' => 'Ghana',
    'GR' => 'Greece', 'IN' => 'India', 'ID' => 'Indonesia', 'IT' => 'Italy',
    'JP' => 'Japan', 'KE' => 'Kenya', 'MY' => 'Malaysia', 'MX' => 'Mexico',
    'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'PK' => 'Pakistan',
    'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'RU' => 'Russia',
    'SA' => 'Saudi Arabia', 'SG' => 'Singapore', 'ZA' => 'South Africa', 'KR' => 'South Korea',
    'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland',
    'TW' => 'Taiwan', 'TH' => 'Thailand', 'TR' => 'Turkey', 'AE' => 'UAE',
    'GB' => 'United Kingdom', 'US' => 'United States', 'VN' => 'Vietnam', 'ZW' => 'Zimbabwe'
];
?>

<div class="wrap">
<h1><?php _e('Membership Statistics', 'indoor-tasks'); ?></h1>

<style>
.membership-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    grid-gap: 20px;
    margin-bottom: 30px;
}
.membership-stat-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.membership-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
.membership-stat-card h3 {
    margin-top: 0;
    color: #555;
    font-size: 16px;
}
.membership-stat-number {
    font-size: 32px;
    font-weight: bold;
    margin: 10px 0;
}
.membership-stat-subtitle {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
.membership-stat-card.users { border-top: 4px solid #2271b1; }
.membership-stat-card.users .membership-stat-number { color: #2271b1; }
.membership-stat-card.kyc { border-top: 4px solid #46b450; }
.membership-stat-card.kyc .membership-stat-number { color: #46b450; }
.membership-stat-card.activity { border-top: 4px solid #00a0d2; }
.membership-stat-card.activity .membership-stat-number { color: #00a0d2; }
.membership-stat-card.points { border-top: 4px solid #f39c12; }
.membership-stat-card.points .membership-stat-number { color: #f39c12; }

.membership-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.membership-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    color: #2271b1;
    display: flex;
    align-items: center;
}
.membership-card h2 i {
    margin-right: 8px;
}
.membership-table {
    width: 100%;
    border-collapse: collapse;
}
.membership-table th, .membership-table td {
    padding: 12px 8px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.membership-table th {
    background: #f9f9f9;
    font-weight: 600;
    color: #555;
}
.membership-table tr:hover {
    background: #f9f9f9;
}
.kyc-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.kyc-status.approved { background: #e8f5e8; color: #2e7d32; }
.kyc-status.pending { background: #fff3e0; color: #f57c00; }
.kyc-status.rejected { background: #ffebee; color: #d32f2f; }
.kyc-status.not-submitted { background: #f5f5f5; color: #666; }
.progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    margin: 5px 0;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #46b450);
    border-radius: 10px;
    transition: width 0.3s ease;
}
</style>

<!-- Statistics Overview -->
<div class="membership-stats-grid">
    <div class="membership-stat-card users">
        <h3><?php _e('Total Members', 'indoor-tasks'); ?></h3>
        <div class="membership-stat-number"><?php echo number_format($total_users); ?></div>
        <div class="membership-stat-subtitle">
            <?php echo sprintf(__('%d active, %d inactive', 'indoor-tasks'), $active_users, $inactive_users); ?>
        </div>
        <div class="membership-stat-subtitle">
            <?php echo sprintf(__('%d new in last 30 days', 'indoor-tasks'), $recent_registrations); ?>
        </div>
    </div>
    
    <div class="membership-stat-card kyc">
        <h3><?php _e('KYC Verified', 'indoor-tasks'); ?></h3>
        <div class="membership-stat-number"><?php echo number_format($kyc_approved); ?></div>
        <div class="membership-stat-subtitle">
            <?php echo sprintf(__('%.1f%% verification rate', 'indoor-tasks'), $total_users > 0 ? ($kyc_approved / $total_users) * 100 : 0); ?>
        </div>
        <div class="membership-stat-subtitle">
            <?php echo sprintf(__('%d pending review', 'indoor-tasks'), $kyc_pending); ?>
        </div>
    </div>
    
    <div class="membership-stat-card activity">
        <h3><?php _e('Active Earners', 'indoor-tasks'); ?></h3>
        <div class="membership-stat-number"><?php echo number_format($users_with_points); ?></div>
        <div class="membership-stat-subtitle">
            <?php echo sprintf(__('%.1f%% of total users', 'indoor-tasks'), $total_users > 0 ? ($users_with_points / $total_users) * 100 : 0); ?>
        </div>
        <div class="membership-stat-subtitle">
            <?php _e('Users with wallet points', 'indoor-tasks'); ?>
        </div>
    </div>
    
    <div class="membership-stat-card points">
        <h3><?php _e('Points Distributed', 'indoor-tasks'); ?></h3>
        <div class="membership-stat-number"><?php echo number_format($total_points_distributed); ?></div>
        <div class="membership-stat-subtitle">
            <?php echo sprintf(__('Avg: %s per user', 'indoor-tasks'), number_format($avg_points_per_user, 1)); ?>
        </div>
        <div class="membership-stat-subtitle">
            <?php _e('Total wallet value', 'indoor-tasks'); ?>
        </div>
    </div>
</div>

<!-- KYC Status Breakdown -->
<div class="membership-card">
    <h2><i class="dashicons dashicons-id-alt"></i> <?php _e('KYC Verification Status', 'indoor-tasks'); ?></h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div>
            <h4><?php _e('Approved', 'indoor-tasks'); ?></h4>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $total_users > 0 ? ($kyc_approved / $total_users) * 100 : 0; ?>%; background: #46b450;"></div>
            </div>
            <p><strong><?php echo number_format($kyc_approved); ?></strong> users (<?php echo $total_users > 0 ? round(($kyc_approved / $total_users) * 100, 1) : 0; ?>%)</p>
        </div>
        
        <div>
            <h4><?php _e('Pending Review', 'indoor-tasks'); ?></h4>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $total_users > 0 ? ($kyc_pending / $total_users) * 100 : 0; ?>%; background: #f39c12;"></div>
            </div>
            <p><strong><?php echo number_format($kyc_pending); ?></strong> users (<?php echo $total_users > 0 ? round(($kyc_pending / $total_users) * 100, 1) : 0; ?>%)</p>
        </div>
        
        <div>
            <h4><?php _e('Rejected', 'indoor-tasks'); ?></h4>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $total_users > 0 ? ($kyc_rejected / $total_users) * 100 : 0; ?>%; background: #dc3545;"></div>
            </div>
            <p><strong><?php echo number_format($kyc_rejected); ?></strong> users (<?php echo $total_users > 0 ? round(($kyc_rejected / $total_users) * 100, 1) : 0; ?>%)</p>
        </div>
        
        <div>
            <h4><?php _e('Not Submitted', 'indoor-tasks'); ?></h4>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $total_users > 0 ? ($kyc_not_submitted / $total_users) * 100 : 0; ?>%; background: #999;"></div>
            </div>
            <p><strong><?php echo number_format($kyc_not_submitted); ?></strong> users (<?php echo $total_users > 0 ? round(($kyc_not_submitted / $total_users) * 100, 1) : 0; ?>%)</p>
        </div>
    </div>
</div>

<!-- Registration Trends -->
<?php if (!empty($monthly_registrations)): ?>
<div class="membership-card">
    <h2><i class="dashicons dashicons-chart-line"></i> <?php _e('Registration Trends (Last 12 Months)', 'indoor-tasks'); ?></h2>
    <div id="registration-chart" style="height: 300px;"></div>
</div>
<?php endif; ?>

<!-- Top Countries -->
<?php if (!empty($country_stats)): ?>
<div class="membership-card">
    <h2><i class="dashicons dashicons-admin-site"></i> <?php _e('Top Countries', 'indoor-tasks'); ?></h2>
    <table class="membership-table">
        <thead>
            <tr>
                <th><?php _e('Country', 'indoor-tasks'); ?></th>
                <th><?php _e('Users', 'indoor-tasks'); ?></th>
                <th><?php _e('Percentage', 'indoor-tasks'); ?></th>
                <th><?php _e('Distribution', 'indoor-tasks'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($country_stats, 0, 10, true) as $country_code => $count): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($countries[$country_code] ?? $country_code); ?></strong>
                    <br><small style="color: #666;"><?php echo esc_html($country_code); ?></small>
                </td>
                <td><strong><?php echo number_format($count); ?></strong></td>
                <td><?php echo round(($count / count($users_with_countries)) * 100, 1); ?>%</td>
                <td>
                    <div class="progress-bar" style="height: 15px;">
                        <div class="progress-fill" style="width: <?php echo ($count / max($country_stats)) * 100; ?>%;"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if (count($country_stats) > 10): ?>
    <p style="text-align: center; color: #666; margin-top: 15px;">
        <em><?php echo sprintf(__('Showing top 10 of %d countries', 'indoor-tasks'), count($country_stats)); ?></em>
    </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="membership-card">
    <h2><i class="dashicons dashicons-admin-tools"></i> <?php _e('Quick Actions', 'indoor-tasks'); ?></h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="<?php echo admin_url('users.php'); ?>" class="button button-primary" style="text-align: center; padding: 15px;">
            <span class="dashicons dashicons-groups" style="vertical-align: middle;"></span>
            <?php _e('Manage Users', 'indoor-tasks'); ?>
        </a>
        <a href="?page=indoor-tasks-manage-kyc" class="button button-secondary" style="text-align: center; padding: 15px;">
            <span class="dashicons dashicons-id-alt" style="vertical-align: middle;"></span>
            <?php _e('Review KYC', 'indoor-tasks'); ?>
        </a>
        <a href="?page=indoor-tasks-wallet-transactions" class="button button-secondary" style="text-align: center; padding: 15px;">
            <span class="dashicons dashicons-money-alt" style="vertical-align: middle;"></span>
            <?php _e('Wallet Transactions', 'indoor-tasks'); ?>
        </a>
        <a href="?page=indoor-tasks-referral-activity" class="button button-secondary" style="text-align: center; padding: 15px;">
            <span class="dashicons dashicons-share" style="vertical-align: middle;"></span>
            <?php _e('Referral Activity', 'indoor-tasks'); ?>
        </a>
    </div>
</div>

</div>

<?php if (!empty($monthly_registrations)): ?>
<!-- Chart Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
jQuery(document).ready(function($) {
    var ctx = document.getElementById('registration-chart').getContext('2d');
    var data = {
        labels: [
            <?php 
            $labels = [];
            $counts = [];
            foreach (array_reverse($monthly_registrations) as $month) {
                $month_name = date('M Y', strtotime($month->year . '-' . $month->month . '-01'));
                $labels[] = "'" . $month_name . "'";
                $counts[] = $month->count;
            }
            echo implode(', ', $labels);
            ?>
        ],
        datasets: [{
            label: 'New Registrations',
            data: [<?php echo implode(', ', $counts); ?>],
            backgroundColor: 'rgba(34, 113, 177, 0.2)',
            borderColor: 'rgba(34, 113, 177, 1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    };
    
    var options = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    };
    
    new Chart(ctx, {
        type: 'line',
        data: data,
        options: options
    });
});
</script>
<?php endif; ?>
