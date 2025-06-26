<?php
// User activity detail view
?>
<div class="wrap">
    <h1>
        <i class="dashicons dashicons-admin-users"></i> 
        <?php echo sprintf(__('User Activity: %s', 'indoor-tasks'), $user->user_login); ?>
    </h1>
    
    <!-- User Overview -->
    <div class="user-overview-card">
        <!-- User Header -->
        <div class="user-profile-header">
            <div class="user-avatar-wrapper">
                <?php echo get_avatar($user_id, 120); ?>
                <?php if ($kyc_status === 'approved'): ?>
                    <span class="verification-badge" title="<?php _e('KYC Verified', 'indoor-tasks'); ?>">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </span>
                <?php endif; ?>
            </div>
            <div class="user-info-wrapper">
                <h2><?php echo esc_html($user->display_name); ?></h2>
                <p class="user-email"><?php echo esc_html($user->user_email); ?></p>
                <div class="user-meta">
                    <span class="level-badge level-<?php echo sanitize_html_class(strtolower($level->name)); ?>">
                        <span class="dashicons dashicons-awards"></span>
                        <?php echo esc_html($level->name); ?> Level
                    </span>
                    <span class="member-since">
                        <span class="dashicons dashicons-calendar"></span>
                        <?php echo sprintf(__('Member since %s', 'indoor-tasks'), date('M j, Y', strtotime($user->user_registered))); ?>
                    </span>
                </div>
            </div>
            <div class="user-actions">
                <a href="admin.php?page=indoor-tasks-manage-users&user_id=<?php echo $user_id; ?>" class="button">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php _e('Manage User', 'indoor-tasks'); ?>
                </a>
                <a href="admin.php?page=indoor-tasks-task-submissions&user_id=<?php echo $user_id; ?>" class="button">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('View Submissions', 'indoor-tasks'); ?>
                </a>
            </div>
        </div>
            
        <!-- Activity Stats Cards -->
        <div class="activity-stats-grid">
            <div class="stat-card total-activities">
                <div class="stat-icon">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($stats['total_activities']); ?></div>
                    <div class="stat-label"><?php _e('Total Activities', 'indoor-tasks'); ?></div>
                    <?php if ($stats['total_activities'] == 0): ?>
                        <div class="stat-empty-notice"><?php _e('No activities recorded yet', 'indoor-tasks'); ?></div>
                    <?php else: ?>
                        <?php 
                        $activity_trend = isset($stats['activity_trend']) ? $stats['activity_trend'] : 0;
                        $trend_class = $activity_trend > 0 ? 'trend-up' : ($activity_trend < 0 ? 'trend-down' : 'trend-neutral');
                        $trend_icon = $activity_trend > 0 ? 'arrow-up-alt' : ($activity_trend < 0 ? 'arrow-down-alt' : 'minus');
                        ?>
                        <div class="stat-trend <?php echo $trend_class; ?>">
                            <span class="dashicons dashicons-<?php echo $trend_icon; ?>"></span>
                            <?php echo abs($activity_trend) . '%'; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card logins">
                <div class="stat-icon">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($stats['total_logins']); ?></div>
                    <div class="stat-label">
                        <?php _e('Logins', 'indoor-tasks'); ?>
                        <?php if ($stats['total_logins'] > 0): ?>
                            <span class="stat-sublabel"><?php echo sprintf(__('%.1f per week', 'indoor-tasks'), $stats['logins_per_week'] ?? 0); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($stats['total_logins'] == 0): ?>
                        <div class="stat-empty-notice"><?php _e('No logins recorded yet', 'indoor-tasks'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card tasks">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-value">
                        <?php echo number_format($stats['tasks_completed']); ?>
                        <?php if ($stats['tasks_completed'] > 0): ?>
                            <span class="completion-rate"><?php echo sprintf('(%.1f%%)', ($stats['task_completion_rate'] ?? 0) * 100); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label"><?php _e('Tasks Completed', 'indoor-tasks'); ?></div>
                    <?php if ($stats['tasks_completed'] == 0): ?>
                        <div class="stat-empty-notice"><?php _e('No tasks completed yet', 'indoor-tasks'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card points">
                <div class="stat-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-value">
                        <?php echo number_format($stats['total_points']); ?>
                        <?php if ($stats['total_points'] > 0): ?>
                            <div class="points-rate"><?php echo sprintf(__('%s points/week', 'indoor-tasks'), number_format($stats['points_per_week'] ?? 0)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label"><?php _e('Total Points Earned', 'indoor-tasks'); ?></div>
                    <?php if ($stats['total_points'] == 0): ?>
                        <div class="stat-empty-notice"><?php _e('No points earned yet', 'indoor-tasks'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card referrals">
                <div class="stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-value">
                        <?php echo number_format($stats['successful_referrals']); ?>
                        <?php if ($stats['successful_referrals'] > 0): ?>
                            <div class="referral-rate"><?php echo sprintf(__('%.1f%% conversion', 'indoor-tasks'), ($stats['referral_conversion_rate'] ?? 0) * 100); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label"><?php _e('Successful Referrals', 'indoor-tasks'); ?></div>
                    <?php if ($stats['successful_referrals'] == 0): ?>
                        <div class="stat-empty-notice"><?php _e('No referrals made yet', 'indoor-tasks'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card last-login">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-value">
                        <?php 
                        if ($last_login) {
                            echo human_time_diff(strtotime($last_login), current_time('timestamp')) . ' ' . __('ago', 'indoor-tasks');
                            $inactive_days = floor((current_time('timestamp') - strtotime($last_login)) / DAY_IN_SECONDS);
                            if ($inactive_days > 7) {
                                echo '<div class="inactivity-warning">' . sprintf(__('Inactive for %d days', 'indoor-tasks'), $inactive_days) . '</div>';
                            }
                        } else {
                            _e('Never', 'indoor-tasks');
                        }
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('Last Login', 'indoor-tasks'); ?></div>
                    <?php if (!$last_login): ?>
                        <div class="stat-empty-notice"><?php _e('User has not logged in yet', 'indoor-tasks'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- IP Addresses -->
    <div class="activity-section">
        <h3><?php _e('Recent IP Addresses', 'indoor-tasks'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('IP Address', 'indoor-tasks'); ?></th>
                    <th><?php _e('Occurrences', 'indoor-tasks'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_ips)): ?>
                    <tr>
                        <td colspan="2"><?php _e('No IP address data available.', 'indoor-tasks'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_ips as $ip): ?>
                        <tr>
                            <td><?php echo esc_html($ip->ip_address); ?></td>
                            <td><?php echo $ip->count; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Activity Timeline -->
    <div class="activity-section">
        <h3><?php _e('Activity Timeline', 'indoor-tasks'); ?></h3>
        <?php if (empty($activities)): ?>
            <p><?php _e('No activities found for this user.', 'indoor-tasks'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date & Time', 'indoor-tasks'); ?></th>
                        <th><?php _e('Activity Type', 'indoor-tasks'); ?></th>
                        <th><?php _e('Description', 'indoor-tasks'); ?></th>
                        <th><?php _e('Details', 'indoor-tasks'); ?></th>
                        <th><?php _e('IP Address', 'indoor-tasks'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): 
                        try {
                            $metadata = !empty($activity->metadata) ? 
                                json_decode($activity->metadata, true, 512, JSON_THROW_ON_ERROR) : 
                                null;
                        } catch (JsonException $e) {
                            $metadata = null;
                        }
                    ?>
                        <tr>
                            <td><?php echo date('M j, Y H:i:s', strtotime($activity->created_at)); ?></td>
                            <td>
                                <span class="activity-type-badge activity-type-<?php echo sanitize_html_class($activity->activity_type); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $activity->activity_type)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($activity->description); ?></td>
                            <td>
                                <?php if ($metadata): ?>
                                    <details>
                                        <summary><?php _e('View Details', 'indoor-tasks'); ?></summary>
                                        <pre><?php echo esc_html(json_encode($metadata, JSON_PRETTY_PRINT)); ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($activity->ip_address); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
