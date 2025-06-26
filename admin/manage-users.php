<?php
// Admin manage users page
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

global $wpdb;

// Handle filter submissions
$filter_kyc = isset($_GET['filter_kyc']) ? sanitize_text_field($_GET['filter_kyc']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
$search_term = isset($_GET['search_term']) ? sanitize_text_field($_GET['search_term']) : '';

// Build the WHERE clause based on filters
$where = [];
$join = "LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'indoor_tasks_kyc_status'";

if (!empty($filter_kyc)) {
    if ($filter_kyc === 'verified') {
        $where[] = "um.meta_value = 'approved'";
    } elseif ($filter_kyc === 'rejected') {
        $where[] = "um.meta_value = 'rejected'";
    } elseif ($filter_kyc === 'pending') {
        $where[] = "(um.meta_value = 'pending' OR um.meta_value IS NULL)";
    }
}

if (!empty($filter_status)) {
    if ($filter_status === 'banned') {
        $where[] = "u.user_status = 1";
    } elseif ($filter_status === 'active') {
        $where[] = "u.user_status = 0";
    }
}

if (!empty($search_term)) {
    $search_term_escaped = $wpdb->esc_like($search_term);
    $where[] = $wpdb->prepare("(u.user_email LIKE %s OR u.display_name LIKE %s)", '%' . $search_term_escaped . '%', '%' . $search_term_escaped . '%');
}

$where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';

// Execute query with filters
try {
    $users = $wpdb->get_results("
        SELECT u.ID, u.user_email, u.display_name, u.user_status, um.meta_value as kyc_status 
        FROM {$wpdb->users} u 
        {$join}
        {$where_clause}
        ORDER BY u.ID DESC
    ");
} catch (Exception $e) {
    $users = [];
    echo '<div class="notice notice-error"><p>' . __('Error loading users: ', 'indoor-tasks') . esc_html($e->getMessage()) . '</p></div>';
}

// Process form submissions
if (isset($_POST['it_user_action']) && current_user_can('manage_options')) {
    $user_id = intval($_POST['user_id']);
    
    if ($_POST['it_user_action'] === 'add_points') {
        $points = intval($_POST['points']);
        $desc = sanitize_text_field($_POST['description']);
        
        $wpdb->insert($wpdb->prefix.'indoor_task_wallet', [
            'user_id' => $user_id,
            'points' => $points,
            'type' => 'admin',
            'description' => $desc
        ]);
        
        // Log the activity
        $wpdb->insert($wpdb->prefix.'indoor_task_user_activities', [
            'user_id' => $user_id,
            'activity_type' => 'admin_add_points',
            'description' => sprintf(__('Admin added %d points: %s', 'indoor-tasks'), $points, $desc),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'created_at' => current_time('mysql')
        ]);
        
        echo '<div class="updated"><p>' . __('Wallet updated.', 'indoor-tasks') . '</p></div>';
    }
    
    if ($_POST['it_user_action'] === 'remove_points') {
        $points = intval($_POST['points']);
        $desc = sanitize_text_field($_POST['description']);
        
        $wpdb->insert($wpdb->prefix.'indoor_task_wallet', [
            'user_id' => $user_id,
            'points' => -abs($points),
            'type' => 'admin',
            'description' => $desc
        ]);
        
        // Log the activity
        $wpdb->insert($wpdb->prefix.'indoor_task_user_activities', [
            'user_id' => $user_id,
            'activity_type' => 'admin_remove_points',
            'description' => sprintf(__('Admin removed %d points: %s', 'indoor-tasks'), $points, $desc),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'created_at' => current_time('mysql')
        ]);
        
        echo '<div class="updated"><p>' . __('Wallet updated.', 'indoor-tasks') . '</p></div>';
    }
    
    // Ban user action
    if ($_POST['it_user_action'] === 'ban_user') {
        $wpdb->update($wpdb->users, ['user_status' => 1], ['ID' => $user_id]);
        
        // Log the activity
        $wpdb->insert($wpdb->prefix.'indoor_task_user_activities', [
            'user_id' => $user_id,
            'activity_type' => 'admin_ban_user',
            'description' => __('User was banned by admin', 'indoor-tasks'),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'created_at' => current_time('mysql')
        ]);
        
        echo '<div class="updated"><p>' . __('User banned successfully.', 'indoor-tasks') . '</p></div>';
    }
    
    // Unban user action
    if ($_POST['it_user_action'] === 'unban_user') {
        $wpdb->update($wpdb->users, ['user_status' => 0], ['ID' => $user_id]);
        
        // Log the activity
        $wpdb->insert($wpdb->prefix.'indoor_task_user_activities', [
            'user_id' => $user_id,
            'activity_type' => 'admin_unban_user',
            'description' => __('User was unbanned by admin', 'indoor-tasks'),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'created_at' => current_time('mysql')
        ]);
        
        echo '<div class="updated"><p>' . __('User unbanned successfully.', 'indoor-tasks') . '</p></div>';
    }
    
    // Change user level action
    if ($_POST['it_user_action'] === 'change_level') {
        $level_id = intval($_POST['level_id']);
        
        if (class_exists('Indoor_Tasks_Levels')) {
            $result = Indoor_Tasks_Levels::set_user_level($user_id, $level_id);
            
            if ($result) {
                echo '<div class="updated"><p>' . __('User level changed successfully.', 'indoor-tasks') . '</p></div>';
            } else {
                echo '<div class="error"><p>' . __('Failed to change user level.', 'indoor-tasks') . '</p></div>';
            }
        } else {
            echo '<div class="error"><p>' . __('Levels functionality is not available.', 'indoor-tasks') . '</p></div>';
        }
    }
    
    // Send KYC reminder
    if ($_POST['it_user_action'] === 'kyc_reminder') {
        // Use the notifications class to send the reminder
        if (class_exists('Indoor_Tasks_Notifications')) {
            $notifications = new Indoor_Tasks_Notifications();
            $sent = $notifications->send_kyc_reminder($user_id);
            
            if ($sent) {
                // Log the activity
                $wpdb->insert($wpdb->prefix.'indoor_task_user_activities', [
                    'user_id' => $user_id,
                    'activity_type' => 'kyc_reminder_sent',
                    'description' => __('KYC reminder sent by admin', 'indoor-tasks'),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'created_at' => current_time('mysql')
                ]);
                
                echo '<div class="updated"><p>' . __('KYC reminder sent successfully.', 'indoor-tasks') . '</p></div>';
            } else {
                echo '<div class="error"><p>' . __('Failed to send KYC reminder.', 'indoor-tasks') . '</p></div>';
            }
        } else {
            // Fallback if the class doesn't exist
            $user_info = get_userdata($user_id);
            $user_email = $user_info->user_email;
            
            // Get notification template
            $template = get_option('indoor_tasks_notify_kyc', 'Your KYC verification is pending. Please complete it to enable withdrawals.');
            
            // Send email notification
            $sent = wp_mail($user_email, __('KYC Verification Reminder', 'indoor-tasks'), $template);
            
            // Store notification in database
            $wpdb->insert($wpdb->prefix.'indoor_task_notifications', [
                'user_id' => $user_id,
                'type' => 'kyc_reminder',
                'message' => __('KYC verification reminder sent by admin.', 'indoor-tasks'),
                'created_at' => current_time('mysql')
            ]);
            
            // Log the activity
            $wpdb->insert($wpdb->prefix.'indoor_task_user_activities', [
                'user_id' => $user_id,
                'activity_type' => 'kyc_reminder_sent',
                'description' => __('KYC reminder sent by admin', 'indoor-tasks'),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => current_time('mysql')
            ]);
            
            echo '<div class="updated"><p>' . __('KYC reminder sent.', 'indoor-tasks') . '</p></div>';
        }
    }
}
?>

<!-- User Management Dashboard -->
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Manage Users', 'indoor-tasks'); ?></h1>
    
    <!-- Filter Form -->
    <div class="tablenav top">
        <form method="get" class="it-filters-form">
            <input type="hidden" name="page" value="indoor-tasks-manage-users">
            
            <div class="filter-group">
                <label for="filter_kyc"><?php _e('KYC Status', 'indoor-tasks'); ?></label>
                <select name="filter_kyc" id="filter_kyc" class="it-select">
                    <option value=""><?php _e('All KYC Status', 'indoor-tasks'); ?></option>
                    <option value="verified" <?php selected($filter_kyc, 'verified'); ?>><?php _e('Verified', 'indoor-tasks'); ?></option>
                    <option value="pending" <?php selected($filter_kyc, 'pending'); ?>><?php _e('Pending', 'indoor-tasks'); ?></option>
                    <option value="rejected" <?php selected($filter_kyc, 'rejected'); ?>><?php _e('Rejected', 'indoor-tasks'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter_status"><?php _e('User Status', 'indoor-tasks'); ?></label>
                <select name="filter_status" id="filter_status" class="it-select">
                    <option value=""><?php _e('All User Status', 'indoor-tasks'); ?></option>
                    <option value="active" <?php selected($filter_status, 'active'); ?>><?php _e('Active', 'indoor-tasks'); ?></option>
                    <option value="banned" <?php selected($filter_status, 'banned'); ?>><?php _e('Banned', 'indoor-tasks'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <label for="search_term"><?php _e('Search', 'indoor-tasks'); ?></label>
                <input type="text" 
                       id="search_term"
                       name="search_term" 
                       class="it-search" 
                       placeholder="<?php _e('Search users...', 'indoor-tasks'); ?>" 
                       value="<?php echo esc_attr($search_term); ?>">
            </div>

            <div class="filter-actions">
                <button type="submit" class="button action">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Filter', 'indoor-tasks'); ?>
                </button>

                <?php if (!empty($filter_kyc) || !empty($filter_status) || !empty($search_term)): ?>
                    <a href="?page=indoor-tasks-manage-users" class="button">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php _e('Reset', 'indoor-tasks'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="it-users-table-wrapper">
        <table class="wp-list-table widefat fixed striped it-users-table">
            <thead>
                <tr>
                    <th class="column-id"><?php _e('ID', 'indoor-tasks'); ?></th>
                    <th class="column-user"><?php _e('User', 'indoor-tasks'); ?></th>
                    <th class="column-wallet"><?php _e('Wallet', 'indoor-tasks'); ?></th>
                    <th class="column-level"><?php _e('Level', 'indoor-tasks'); ?></th>
                    <th class="column-kyc"><?php _e('KYC Status', 'indoor-tasks'); ?></th>
                    <th class="column-status"><?php _e('Status', 'indoor-tasks'); ?></th>
                    <th class="column-actions"><?php _e('Actions', 'indoor-tasks'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="no-items">
                            <?php _e('No users found matching your criteria.', 'indoor-tasks'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($users as $user):
                        $wallet = $wpdb->get_var($wpdb->prepare(
                            "SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
                            $user->ID
                        ));
                        
                        $level_id = get_user_meta($user->ID, 'indoor_tasks_user_level', true);
                        $level_name = "Bronze"; // Default level
                        
                        if (!empty($level_id)) {
                            $level_name = $wpdb->get_var($wpdb->prepare(
                                "SELECT name FROM {$wpdb->prefix}indoor_task_user_levels WHERE id = %d",
                                $level_id
                            ));
                        } else if (class_exists('Indoor_Tasks_Levels')) {
                            $level_name = Indoor_Tasks_Levels::get_user_level($user->ID);
                        }
                        
                        $kyc_status = !empty($user->kyc_status) ? $user->kyc_status : 'pending';
                        $is_banned = $user->user_status == 1;
                    ?>
                        <tr>
                            <td class="column-id"><?php echo $user->ID; ?></td>
                            <td class="column-user">
                                <div class="user-info">
                                    <?php echo get_avatar($user->ID, 32); ?>
                                    <div class="user-details">
                                        <strong><?php echo esc_html($user->display_name); ?></strong>
                                        <span class="user-email"><?php echo esc_html($user->user_email); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="column-wallet">
                                <span class="points-badge">
                                    <?php echo number_format((float)$wallet); ?>
                                </span>
                            </td>
                            <td class="column-level">
                                <span class="level-badge level-<?php echo sanitize_html_class(strtolower($level_name)); ?>">
                                    <?php echo esc_html($level_name); ?>
                                </span>
                            </td>
                            <td class="column-kyc">
                                <span class="status-badge status-<?php echo sanitize_html_class($kyc_status); ?>">
                                    <?php echo ucfirst($kyc_status); ?>
                                </span>
                            </td>
                            <td class="column-status">
                                <span class="status-badge status-<?php echo $is_banned ? 'banned' : 'active'; ?>">
                                    <?php echo $is_banned ? __('Banned', 'indoor-tasks') : __('Active', 'indoor-tasks'); ?>
                                </span>
                            </td>
                            <td class="column-actions">
                                <div class="action-buttons">
                                    <!-- Points Management -->
                                    <div class="points-management">
                                        <form method="post" class="points-form">
                                            <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>" />
                                            <div class="points-input-group">
                                                <input type="number" 
                                                       name="points" 
                                                       placeholder="<?php _e('Points', 'indoor-tasks'); ?>" 
                                                       required 
                                                       min="1"
                                                       class="small-text" />
                                                <input type="text" 
                                                       name="description" 
                                                       placeholder="<?php _e('Description', 'indoor-tasks'); ?>" 
                                                       class="medium-text" />
                                                <button type="submit" name="it_user_action" value="add_points" class="button action">
                                                    <span class="dashicons dashicons-plus-alt"></span>
                                                </button>
                                                <button type="submit" name="it_user_action" value="remove_points" class="button action">
                                                    <span class="dashicons dashicons-minus"></span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- User Actions -->
                                    <div class="user-actions-group">
                                        <form method="post" class="action-form">
                                            <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>" />
                                            
                                            <?php if($is_banned): ?>
                                                <button type="submit" 
                                                        name="it_user_action" 
                                                        value="unban_user" 
                                                        class="button action"
                                                        onclick="return confirm('<?php _e('Are you sure you want to unban this user?', 'indoor-tasks'); ?>');">
                                                    <span class="dashicons dashicons-unlock"></span>
                                                    <?php _e('Unban', 'indoor-tasks'); ?>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" 
                                                        name="it_user_action" 
                                                        value="ban_user" 
                                                        class="button action"
                                                        onclick="return confirm('<?php _e('Are you sure you want to ban this user?', 'indoor-tasks'); ?>');">
                                                    <span class="dashicons dashicons-lock"></span>
                                                    <?php _e('Ban', 'indoor-tasks'); ?>
                                                </button>
                                            <?php endif; ?>

                                            <?php if($kyc_status != 'approved'): ?>
                                                <button type="submit" name="it_user_action" value="kyc_reminder" class="button action">
                                                    <span class="dashicons dashicons-email-alt"></span>
                                                    <?php _e('KYC Reminder', 'indoor-tasks'); ?>
                                                </button>
                                            <?php endif; ?>

                                            <a href="?page=indoor-tasks-user-activity&user_id=<?php echo $user->ID; ?>" 
                                               class="button action">
                                                <span class="dashicons dashicons-chart-bar"></span>
                                                <?php _e('Activity', 'indoor-tasks'); ?>
                                            </a>

                                            <?php if (!empty($user_levels)): ?>
                                                <select name="level_id" class="it-select">
                                                    <?php foreach($user_levels as $level): ?>
                                                        <option value="<?php echo $level->id; ?>" 
                                                                <?php selected($level_name == $level->name); ?>>
                                                            <?php echo esc_html($level->name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="it_user_action" value="change_level" class="button action">
                                                    <span class="dashicons dashicons-awards"></span>
                                                    <?php _e('Change Level', 'indoor-tasks'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Filter Styles */
.it-filters-form {
    margin-bottom: 20px;
}

.filter-group {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    align-items: start;
    max-width: 800px;
}

.it-select {
    width: 100%;
    height: 32px;
}

.it-search {
    width: 100%;
    height: 32px;
}

/* Table Styles */
.it-users-table-wrapper {
    margin-top: 20px;
}

.it-users-table {
    border-collapse: collapse;
    width: 100%;
}

.it-users-table th {
    padding: 12px;
    text-align: left;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.it-users-table td {
    padding: 12px;
    vertical-align: middle;
    border-bottom: 1px solid #dee2e6;
}

/* User Info */
.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-email {
    color: #6c757d;
    font-size: 0.9em;
}

/* Badges */
.points-badge {
    background: #e8f0fe;
    color: #174ea6;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
}

.level-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
}

.level-bronze { background: #fff3e0; color: #e65100; }
.level-silver { background: #eceff1; color: #455a64; }
.level-gold { background: #fff8e1; color: #f57f17; }
.level-platinum { background: #f3e5f5; color: #6a1b9a; }
.level-diamond { background: #e3f2fd; color: #1565c0; }

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
}

.status-approved { background: #e6f4ea; color: #137333; }
.status-pending { background: #fff8e1; color: #f57c00; }
.status-rejected { background: #fce8e6; color: #c5221f; }
.status-banned { background: #fce8e6; color: #c5221f; }
.status-active { background: #e6f4ea; color: #137333; }

/* Action Buttons */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.points-management {
    margin-bottom: 8px;
}

.points-input-group {
    display: flex;
    gap: 4px;
    align-items: center;
}

.points-input-group input {
    height: 28px;
}

.user-actions-group {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.action-form {
    display: flex;
    gap: 4px;
    align-items: center;
}

.button.action {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    height: 28px;
}

.button.action .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* Responsive */
@media screen and (max-width: 782px) {
    .it-filters-form {
        flex-direction: column;
    }

    .filter-group {
        flex-direction: column;
    }

    .it-select,
    .it-search {
        width: 100%;
    }

    .action-buttons {
        flex-direction: column;
    }

    .points-input-group {
        flex-wrap: wrap;
    }

    .user-actions-group {
        flex-direction: column;
    }

    .action-form {
        flex-direction: column;
        width: 100%;
    }

    .button.action {
        width: 100%;
        justify-content: center;
    }
}
</style>
