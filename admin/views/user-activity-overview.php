<?php
// User activity overview page
?>
<div class="wrap">
    <h1><?php _e('User Activity', 'indoor-tasks'); ?></h1>
    
    <!-- Export Form -->
    <div class="activity-export">
        <form method="post" action="" class="export-form">
            <?php wp_nonce_field('indoor_tasks_export_activities', 'export_nonce'); ?>
            <input type="hidden" name="action" value="export_activities">
            
            <div class="export-fields">
                <div class="date-range">
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" />
                    <span>to</span>
                    <input type="date" name="end_date" value="<?php echo date('Y-m-d'); ?>" />
                </div>
                
                <select name="export_activity_type">
                    <option value=""><?php _e('All Activity Types', 'indoor-tasks'); ?></option>
                    <option value="login"><?php _e('Logins', 'indoor-tasks'); ?></option>
                    <option value="task_submission"><?php _e('Task Submissions', 'indoor-tasks'); ?></option>
                    <option value="task_approved"><?php _e('Task Approvals', 'indoor-tasks'); ?></option>
                    <option value="level_change"><?php _e('Level Changes', 'indoor-tasks'); ?></option>
                    <option value="withdrawal_request"><?php _e('Withdrawal Requests', 'indoor-tasks'); ?></option>
                    <option value="kyc_submission"><?php _e('KYC Submissions', 'indoor-tasks'); ?></option>
                    <option value="points_awarded"><?php _e('Points Awarded', 'indoor-tasks'); ?></option>
                    <option value="referral_created"><?php _e('Referrals', 'indoor-tasks'); ?></option>
                </select>
                
                <button type="submit" class="button button-primary">
                    <i class="dashicons dashicons-download"></i> <?php _e('Export to CSV', 'indoor-tasks'); ?>
                </button>
            </div>
        </form>
    </div>
    
    <!-- Filter Form -->
    <div class="activity-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="indoor-tasks-user-activity" />
            
            <select name="activity_type">
                <option value=""><?php _e('All Activity Types', 'indoor-tasks'); ?></option>
                <option value="login" <?php echo isset($_GET['activity_type']) && $_GET['activity_type'] === 'login' ? 'selected' : ''; ?>><?php _e('Logins', 'indoor-tasks'); ?></option>
                <option value="task_submission" <?php echo isset($_GET['activity_type']) && $_GET['activity_type'] === 'task_submission' ? 'selected' : ''; ?>><?php _e('Task Submissions', 'indoor-tasks'); ?></option>
                <option value="task_approved" <?php echo isset($_GET['activity_type']) && $_GET['activity_type'] === 'task_approved' ? 'selected' : ''; ?>><?php _e('Task Approvals', 'indoor-tasks'); ?></option>
                <option value="level_change" <?php echo isset($_GET['activity_type']) && $_GET['activity_type'] === 'level_change' ? 'selected' : ''; ?>><?php _e('Level Changes', 'indoor-tasks'); ?></option>
                <option value="withdrawal_request" <?php echo isset($_GET['activity_type']) && $_GET['activity_type'] === 'withdrawal_request' ? 'selected' : ''; ?>><?php _e('Withdrawal Requests', 'indoor-tasks'); ?></option>
                <option value="kyc_submission" <?php echo isset($_GET['activity_type']) && $_GET['activity_type'] === 'kyc_submission' ? 'selected' : ''; ?>><?php _e('KYC Submissions', 'indoor-tasks'); ?></option>
                <option value="points_awarded" <?php echo isset($_GET['activity_type']) && $_GET['activity_type'] === 'points_awarded' ? 'selected' : ''; ?>><?php _e('Points Awarded', 'indoor-tasks'); ?></option>
                <option value="referral_created" <?php echo isset($_GET['activity_type']) && $_GET['activity_type'] === 'referral_created' ? 'selected' : ''; ?>><?php _e('Referrals', 'indoor-tasks'); ?></option>
            </select>
            
            <select name="date_range">
                <option value=""><?php _e('All Time', 'indoor-tasks'); ?></option>
                <option value="today" <?php echo isset($_GET['date_range']) && $_GET['date_range'] === 'today' ? 'selected' : ''; ?>><?php _e('Today', 'indoor-tasks'); ?></option>
                <option value="yesterday" <?php echo isset($_GET['date_range']) && $_GET['date_range'] === 'yesterday' ? 'selected' : ''; ?>><?php _e('Yesterday', 'indoor-tasks'); ?></option>
                <option value="week" <?php echo isset($_GET['date_range']) && $_GET['date_range'] === 'week' ? 'selected' : ''; ?>><?php _e('Last 7 Days', 'indoor-tasks'); ?></option>
                <option value="month" <?php echo isset($_GET['date_range']) && $_GET['date_range'] === 'month' ? 'selected' : ''; ?>><?php _e('Last 30 Days', 'indoor-tasks'); ?></option>
            </select>
            
            <input type="text" name="search" placeholder="<?php _e('Search users...', 'indoor-tasks'); ?>" value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>" />
            
            <button type="submit" class="button"><?php _e('Apply Filters', 'indoor-tasks'); ?></button>
            <a href="?page=indoor-tasks-user-activity" class="button"><?php _e('Reset', 'indoor-tasks'); ?></a>
        </form>
    </div>
    
    <!-- Activity Stats -->
    <div class="activity-overview">
        <div class="activity-overview-card">
            <div class="overview-value"><?php echo number_format($stats['total_activities']); ?></div>
            <div class="overview-label"><?php _e('Total Activities', 'indoor-tasks'); ?></div>
        </div>
        <div class="activity-overview-card">
            <div class="overview-value"><?php echo number_format($stats['login_activities']); ?></div>
            <div class="overview-label"><?php _e('Logins', 'indoor-tasks'); ?></div>
        </div>
        <div class="activity-overview-card">
            <div class="overview-value"><?php echo number_format($stats['task_activities']); ?></div>
            <div class="overview-label"><?php _e('Task Activities', 'indoor-tasks'); ?></div>
        </div>
        <div class="activity-overview-card">
            <div class="overview-value"><?php echo number_format($stats['level_changes']); ?></div>
            <div class="overview-label"><?php _e('Level Changes', 'indoor-tasks'); ?></div>
        </div>
        <div class="activity-overview-card">
            <div class="overview-value"><?php echo number_format($stats['total_points_awarded']); ?></div>
            <div class="overview-label"><?php _e('Points Awarded', 'indoor-tasks'); ?></div>
        </div>
        <div class="activity-overview-card">
            <div class="overview-value"><?php echo number_format($stats['total_active_users']); ?></div>
            <div class="overview-label"><?php _e('Active Users', 'indoor-tasks'); ?></div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="activity-section">
        <h3><?php _e('Recent Activity', 'indoor-tasks'); ?></h3>
        <?php if (empty($recent_activities)): ?>
            <p class="no-activity"><?php _e('No recent activities found.', 'indoor-tasks'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-date"><?php _e('Date & Time', 'indoor-tasks'); ?></th>
                        <th class="column-user"><?php _e('User', 'indoor-tasks'); ?></th>
                        <th class="column-type"><?php _e('Activity Type', 'indoor-tasks'); ?></th>
                        <th class="column-description"><?php _e('Description', 'indoor-tasks'); ?></th>
                        <th class="column-details"><?php _e('Details', 'indoor-tasks'); ?></th>
                        <th class="column-ip"><?php _e('IP Address', 'indoor-tasks'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activities as $activity): 
                        try {
                            $metadata = !empty($activity->metadata) ? 
                                json_decode($activity->metadata, true, 512, JSON_THROW_ON_ERROR) : 
                                null;
                        } catch (JsonException $e) {
                            $metadata = null;
                        }
                    ?>
                        <tr>
                            <td class="column-date">
                                <?php 
                                $date = strtotime($activity->created_at);
                                echo date('M j, Y', $date) . '<br>';
                                echo '<span class="time">' . date('H:i:s', $date) . '</span>';
                                ?>
                            </td>
                            <td class="column-user">
                                <a href="admin.php?page=indoor-tasks-user-activity&user_id=<?php echo $activity->user_id; ?>">
                                    <?php echo esc_html($activity->user_login); ?>
                                </a>
                                <br>
                                <small><?php echo esc_html($activity->user_email); ?></small>
                            </td>
                            <td class="column-type">
                                <span class="activity-type-badge activity-type-<?php echo sanitize_html_class($activity->activity_type); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $activity->activity_type)); ?>
                                </span>
                            </td>
                            <td class="column-description">
                                <?php echo esc_html($activity->description); ?>
                            </td>
                            <td class="column-details">
                                <?php if ($metadata): ?>
                                    <details>
                                        <summary>
                                            <span class="dashicons dashicons-info-outline"></span>
                                            <?php _e('View Details', 'indoor-tasks'); ?>
                                        </summary>
                                        <div class="metadata-content">
                                            <?php foreach ($metadata as $key => $value): ?>
                                                <div class="metadata-item">
                                                    <span class="metadata-key"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</span>
                                                    <span class="metadata-value"><?php 
                                                        if (is_array($value)) {
                                                            echo esc_html(json_encode($value, JSON_PRETTY_PRINT));
                                                        } else {
                                                            echo esc_html($value);
                                                        }
                                                    ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="column-ip">
                                <?php echo esc_html($activity->ip_address); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Users With Activity -->
    <div class="activity-section">
        <h3><?php _e('Users With Activity', 'indoor-tasks'); ?></h3>
        <?php if (empty($users_with_activity)): ?>
            <p><?php _e('No user activity data available.', 'indoor-tasks'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('User', 'indoor-tasks'); ?></th>
                        <th><?php _e('Email', 'indoor-tasks'); ?></th>
                        <th><?php _e('Registered', 'indoor-tasks'); ?></th>
                        <th><?php _e('Activity Count', 'indoor-tasks'); ?></th>
                        <th><?php _e('Last Activity', 'indoor-tasks'); ?></th>
                        <th><?php _e('Actions', 'indoor-tasks'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_with_activity as $user): ?>
                        <tr>
                            <td><?php echo esc_html($user->user_login); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user->user_registered)); ?></td>
                            <td><?php echo $user->activity_count; ?></td>
                            <td>
                                <?php if ($user->last_activity): ?>
                                    <span class="activity-type-badge activity-type-<?php echo sanitize_html_class($user->last_activity_type); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $user->last_activity_type)); ?>
                                    </span>
                                    <br>
                                    <small><?php echo human_time_diff(strtotime($user->last_activity), current_time('timestamp')); ?> <?php _e('ago', 'indoor-tasks'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="admin.php?page=indoor-tasks-user-activity&user_id=<?php echo $user->user_id; ?>" class="button button-small">
                                    <?php _e('View Activity', 'indoor-tasks'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php
            // Add pagination
            $total_pages = ceil($total_items / $per_page);
            if ($total_pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page
                ));
                echo '</div></div>';
            }
            ?>
        <?php endif; ?>
    </div>
</div>
