<?php
/**
 * Profit Calculation Admin Page
 * Shows statistics and task-wise profit calculation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Calculate profit statistics with correct formula: Budget - (Reward Points × Max Users)
$total_budget = $wpdb->get_var("SELECT SUM(budget) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0");
$total_task_cost = $wpdb->get_var("SELECT SUM(reward_points * max_users) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0");
$total_profit = $total_budget - $total_task_cost;

// Last 7 days profit - using a safer approach
$seven_days_ago = date('Y-m-d', strtotime('-7 days'));

// Check if created_at column exists, fallback to id comparison if not
$has_created_at = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}indoor_tasks LIKE 'created_at'");
if ($has_created_at) {
    $date_filter = "DATE(created_at) >= %s";
} else {
    // Fallback to recent IDs as a proxy for recent creation
    $recent_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0 ORDER BY id DESC LIMIT 1 OFFSET %d",
        50 // Approximate last week's tasks
    ));
    $date_filter = $recent_id ? "id > %d" : "1=1";
}

if ($has_created_at) {
    $last_7_days_budget = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(budget) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0 AND $date_filter",
        $seven_days_ago
    ));
    $last_7_days_cost = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(reward_points * max_users) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0 AND $date_filter",
        $seven_days_ago
    ));
} else {
    $last_7_days_budget = $recent_id ? $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(budget) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0 AND id > %d",
        $recent_id
    )) : 0;
    $last_7_days_cost = $recent_id ? $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(reward_points * max_users) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0 AND id > %d",
        $recent_id
    )) : 0;
}
$last_7_days_profit = $last_7_days_budget - $last_7_days_cost;

// Last 30 days profit - similar approach
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
if ($has_created_at) {
    $last_30_days_budget = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(budget) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0 AND DATE(created_at) >= %s",
        $thirty_days_ago
    ));
    $last_30_days_cost = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(reward_points * max_users) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0 AND DATE(created_at) >= %s",
        $thirty_days_ago
    ));
} else {
    $recent_30_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0 ORDER BY id DESC LIMIT 1 OFFSET %d",
        200 // Approximate last month's tasks
    ));
    $last_30_days_budget = $recent_30_id ? $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(budget) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0 AND id > %d",
        $recent_30_id
    )) : 0;
    $last_30_days_cost = $recent_30_id ? $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(reward_points * max_users) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0 AND id > %d",
        $recent_30_id
    )) : 0;
}
$last_30_days_profit = $last_30_days_budget - $last_30_days_cost;

// Most profitable task with correct formula
$most_profitable_task = $wpdb->get_row("
    SELECT id, title, budget, reward_points, max_users, (budget - (reward_points * max_users)) as profit 
    FROM {$wpdb->prefix}indoor_tasks 
    WHERE budget > 0 
    ORDER BY (budget - (reward_points * max_users)) DESC 
    LIMIT 1
");

// Get task-wise profit data with pagination
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($current_page - 1) * $per_page;

// Use the same approach for the main query
if ($has_created_at) {
    $order_by = "created_at DESC";
    $date_column = "created_at";
} else {
    $order_by = "id DESC";
    $date_column = "CURRENT_TIMESTAMP"; // Fallback placeholder
}

$tasks_query = "
    SELECT id, title, budget, reward_points, max_users,
           " . ($has_created_at ? "created_at" : "NULL as created_at") . ",
           (budget - (reward_points * max_users)) as admin_profit
    FROM {$wpdb->prefix}indoor_tasks 
    WHERE budget > 0 
    ORDER BY $order_by 
    LIMIT $per_page OFFSET $offset
";
$tasks = $wpdb->get_results($tasks_query);

// Get total count for pagination
$total_tasks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks WHERE budget > 0");
$total_pages = ceil($total_tasks / $per_page);

// Format currency - Admin calculations use Indian Rupee (₹)
function format_currency($amount) {
    return '₹' . number_format($amount, 2);
}
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-chart-line" style="font-size: 30px; margin-right: 10px;"></span>
        <?php _e('Profit Calculation', 'indoor-tasks'); ?>
    </h1>

    <!-- Statistics Cards -->
    <div class="profit-stats-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        
        <!-- Total Profit Card -->
        <div class="profit-stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="margin: 0; font-size: 16px; opacity: 0.9;"><?php _e('Total Profit', 'indoor-tasks'); ?></h3>
                    <p style="margin: 5px 0 0 0; font-size: 32px; font-weight: bold;"><?php echo format_currency($total_profit ?: 0); ?></p>
                </div>
                <div class="profit-icon" style="font-size: 40px; opacity: 0.7;">💰</div>
            </div>
        </div>

        <!-- Last 7 Days Profit Card -->
        <div class="profit-stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="margin: 0; font-size: 16px; opacity: 0.9;"><?php _e('Last 7 Days Profit', 'indoor-tasks'); ?></h3>
                    <p style="margin: 5px 0 0 0; font-size: 32px; font-weight: bold;"><?php echo format_currency($last_7_days_profit ?: 0); ?></p>
                </div>
                <div class="profit-icon" style="font-size: 40px; opacity: 0.7;">📈</div>
            </div>
        </div>

        <!-- Last 30 Days Profit Card -->
        <div class="profit-stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="margin: 0; font-size: 16px; opacity: 0.9;"><?php _e('Last 30 Days Profit', 'indoor-tasks'); ?></h3>
                    <p style="margin: 5px 0 0 0; font-size: 32px; font-weight: bold;"><?php echo format_currency($last_30_days_profit ?: 0); ?></p>
                </div>
                <div class="profit-icon" style="font-size: 40px; opacity: 0.7;">📊</div>
            </div>
        </div>

        <!-- Most Profitable Task Card -->
        <div class="profit-stat-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="margin: 0; font-size: 16px; opacity: 0.8;"><?php _e('Most Profitable Task', 'indoor-tasks'); ?></h3>
                    <?php if ($most_profitable_task): ?>
                        <p style="margin: 5px 0 2px 0; font-size: 24px; font-weight: bold;"><?php echo format_currency($most_profitable_task->profit); ?></p>
                        <p style="margin: 0; font-size: 14px; opacity: 0.8;"><?php echo esc_html(substr($most_profitable_task->title, 0, 30)) . (strlen($most_profitable_task->title) > 30 ? '...' : ''); ?></p>
                    <?php else: ?>
                        <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold;">$0.00</p>
                        <p style="margin: 0; font-size: 14px; opacity: 0.8;"><?php _e('No profitable tasks yet', 'indoor-tasks'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="profit-icon" style="font-size: 40px; opacity: 0.7;">🏆</div>
            </div>
        </div>

    </div>

    <!-- Task-wise Profit Table -->
    <div class="profit-table-container" style="background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-top: 30px;">
        <div style="padding: 20px; border-bottom: 1px solid #eee;">
            <h2 style="margin: 0; display: flex; align-items: center;">
                <span class="dashicons dashicons-list-view" style="margin-right: 10px;"></span>
                <?php _e('Task-wise Profit Analysis', 'indoor-tasks'); ?>
            </h2>
        </div>

        <?php if (!empty($tasks)): ?>
            <div style="overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="padding: 15px; font-weight: 600;"><?php _e('Task ID', 'indoor-tasks'); ?></th>
                            <th style="padding: 15px; font-weight: 600;"><?php _e('Task Name', 'indoor-tasks'); ?></th>
                            <th style="padding: 15px; font-weight: 600; text-align: right;"><?php _e('Total Budget', 'indoor-tasks'); ?></th>
                            <th style="padding: 15px; font-weight: 600; text-align: right;"><?php _e('Total Task Cost (Reward × Max Users)', 'indoor-tasks'); ?></th>
                            <th style="padding: 15px; font-weight: 600; text-align: right;"><?php _e('Admin Profit', 'indoor-tasks'); ?></th>
                            <th style="padding: 15px; font-weight: 600;"><?php _e('Created Date', 'indoor-tasks'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td style="padding: 15px; font-weight: 500;">#<?php echo esc_html($task->id); ?></td>
                                <td style="padding: 15px;">
                                    <strong><?php echo esc_html($task->title); ?></strong>
                                </td>
                                <td style="padding: 15px; text-align: right; font-weight: 500; color: #2271b1;">
                                    <?php echo format_currency($task->budget); ?>
                                </td>
                                <td style="padding: 15px; text-align: right; font-weight: 500; color: #d63638;">
                                    <?php echo format_currency($task->reward_points * $task->max_users); ?>
                                    <span style="font-size: 12px; color: #666; display: block;"><?php echo $task->reward_points; ?> points × <?php echo $task->max_users; ?> users</span>
                                </td>
                                <td style="padding: 15px; text-align: right; font-weight: 600; color: <?php echo $task->admin_profit >= 0 ? '#00a32a' : '#d63638'; ?>;">
                                    <?php echo format_currency($task->admin_profit); ?>
                                    <?php if ($task->admin_profit < 0): ?>
                                        <span style="font-size: 12px; margin-left: 5px;">⚠️</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px; color: #666;">
                                    <?php 
                                    if ($task->created_at) {
                                        echo date_i18n(get_option('date_format'), strtotime($task->created_at));
                                    } else {
                                        echo __('N/A', 'indoor-tasks');
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div style="padding: 20px; display: flex; justify-content: center; border-top: 1px solid #eee;">
                    <div class="tablenav-pages">
                        <?php
                        $base_url = admin_url('admin.php?page=indoor-tasks-profit-calculation');
                        
                        // Previous page
                        if ($current_page > 1) {
                            echo '<a class="prev-page button" href="' . esc_url($base_url . '&paged=' . ($current_page - 1)) . '">' . __('‹ Previous', 'indoor-tasks') . '</a>';
                        }
                        
                        // Page numbers
                        $start = max(1, $current_page - 2);
                        $end = min($total_pages, $current_page + 2);
                        
                        for ($i = $start; $i <= $end; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="current-page" style="padding: 8px 12px; background: #2271b1; color: white; border-radius: 4px; margin: 0 2px;">' . $i . '</span>';
                            } else {
                                echo '<a class="page-numbers button" href="' . esc_url($base_url . '&paged=' . $i) . '" style="margin: 0 2px;">' . $i . '</a>';
                            }
                        }
                        
                        // Next page
                        if ($current_page < $total_pages) {
                            echo '<a class="next-page button" href="' . esc_url($base_url . '&paged=' . ($current_page + 1)) . '">' . __('Next ›', 'indoor-tasks') . '</a>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="padding: 40px; text-align: center; color: #666;">
                <div style="font-size: 48px; margin-bottom: 20px;">📊</div>
                <h3><?php _e('No profit data available', 'indoor-tasks'); ?></h3>
                <p><?php _e('Start adding tasks with budget information to see profit calculations.', 'indoor-tasks'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=indoor-tasks-add-task'); ?>" class="button button-primary" style="margin-top: 15px;">
                    <?php _e('Add New Task', 'indoor-tasks'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Summary Information -->
    <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-top: 30px; border-left: 4px solid #2271b1;">
        <h3 style="margin-top: 0; color: #2271b1;">
            <span class="dashicons dashicons-info" style="margin-right: 8px;"></span>
            <?php _e('Profit Calculation Information', 'indoor-tasks'); ?>
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div>
                <h4><?php _e('How Profit is Calculated:', 'indoor-tasks'); ?></h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><?php _e('Admin Profit = Client Budget - (Reward Points × Max Users)', 'indoor-tasks'); ?></li>
                    <li><?php _e('Only tasks with budget > 0 are included', 'indoor-tasks'); ?></li>
                    <li><?php _e('Negative profit indicates a loss', 'indoor-tasks'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php _e('Statistics Summary:', 'indoor-tasks'); ?></h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><?php _e('Total Tasks with Budget: ', 'indoor-tasks'); ?><strong><?php echo number_format($total_tasks); ?></strong></li>
                    <li><?php _e('Total Budget: ', 'indoor-tasks'); ?><strong><?php echo format_currency($total_budget ?: 0); ?></strong></li>
                    <li><?php _e('Total Task Costs: ', 'indoor-tasks'); ?><strong><?php echo format_currency($total_task_cost ?: 0); ?></strong></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.profit-stat-card:hover {
    transform: translateY(-2px);
    transition: transform 0.3s ease;
}

.wp-list-table tbody tr:hover {
    background-color: #f8f9fa;
}

.tablenav-pages .button {
    padding: 8px 12px;
    border-radius: 4px;
    text-decoration: none;
}

.tablenav-pages .button:hover {
    background: #f0f0f1;
}

@media (max-width: 768px) {
    .profit-stats-container {
        grid-template-columns: 1fr !important;
    }
    
    .profit-table-container table {
        font-size: 14px;
    }
    
    .profit-table-container th,
    .profit-table-container td {
        padding: 10px 8px !important;
    }
}
</style>
