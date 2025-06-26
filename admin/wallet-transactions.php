<?php
// Wallet Transactions (admin)
global $wpdb;

// Check if wallet table exists
$wallet_table = $wpdb->prefix . 'indoor_task_wallet';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$wallet_table'") === $wallet_table;

if (!$table_exists) {
    ?>
    <div class="wrap">
        <h1><?php _e('Wallet Transactions', 'indoor-tasks'); ?></h1>
        <div class="notice notice-error">
            <p><strong><?php _e('Wallet table not found!', 'indoor-tasks'); ?></strong></p>
            <p><?php _e('The wallet table is required for tracking transactions. Please create it first.', 'indoor-tasks'); ?></p>
            <p>
                <a href="<?php echo plugin_dir_url(dirname(__FILE__)) . 'fix-wallet-referrals.php'; ?>" class="button button-primary" target="_blank">
                    <?php _e('Setup Wallet System', 'indoor-tasks'); ?>
                </a>
            </p>
        </div>
    </div>
    <?php
    return;
}

// Get wallet statistics
$total_transactions = $wpdb->get_var("SELECT COUNT(*) FROM $wallet_table") ?: 0;
$total_credits = $wpdb->get_var("SELECT SUM(points) FROM $wallet_table WHERE points > 0") ?: 0;
$total_debits = $wpdb->get_var("SELECT SUM(ABS(points)) FROM $wallet_table WHERE points < 0") ?: 0;
$total_balance = $total_credits - $total_debits;

// Get transaction breakdown by type
$transaction_types = $wpdb->get_results("SELECT type, COUNT(*) as count, SUM(points) as total_points FROM $wallet_table GROUP BY type ORDER BY count DESC") ?: [];

// Handle filtering
$filter_user = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : 0;
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';
$filter_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Build query
$query = "SELECT w.*, u.user_email, u.user_login, r.user_email as ref_email, r.user_login as ref_login 
    FROM $wallet_table w 
    LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID 
    LEFT JOIN {$wpdb->users} r ON w.reference_id = r.ID 
    WHERE 1=1";

if ($filter_user) {
    $query .= $wpdb->prepare(" AND w.user_id = %d", $filter_user);
}

if ($filter_type) {
    $query .= $wpdb->prepare(" AND w.type = %s", $filter_type);
}

if ($filter_date_from) {
    $query .= $wpdb->prepare(" AND w.created_at >= %s", $filter_date_from . ' 00:00:00');
}

if ($filter_date_to) {
    $query .= $wpdb->prepare(" AND w.created_at <= %s", $filter_date_to . ' 23:59:59');
}

$query .= " ORDER BY w.created_at DESC";

// Execute query
$transactions = $wpdb->get_results($query) ?: [];

// Pagination
$items_per_page = 25;
$total_items = count($transactions);
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $items_per_page;
$paged_transactions = array_slice($transactions, $offset, $items_per_page);
$total_pages = ceil($total_items / $items_per_page);

// Get recent activity (last 7 days)
$recent_transactions = $wpdb->get_var("SELECT COUNT(*) FROM $wallet_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)") ?: 0;
?>

<div class="wrap">
<h1><?php _e('Wallet Transactions', 'indoor-tasks'); ?></h1>

<style>
.wallet-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    grid-gap: 20px;
    margin-bottom: 30px;
}
.wallet-stat-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.wallet-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
.wallet-stat-card h3 {
    margin-top: 0;
    color: #555;
    font-size: 16px;
}
.wallet-stat-number {
    font-size: 32px;
    font-weight: bold;
    margin: 10px 0;
}
.wallet-stat-subtitle {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
.wallet-stat-card.balance { border-top: 4px solid #2271b1; }
.wallet-stat-card.balance .wallet-stat-number { color: #2271b1; }
.wallet-stat-card.transactions { border-top: 4px solid #00a0d2; }
.wallet-stat-card.transactions .wallet-stat-number { color: #00a0d2; }
.wallet-stat-card.credits { border-top: 4px solid #46b450; }
.wallet-stat-card.credits .wallet-stat-number { color: #46b450; }
.wallet-stat-card.debits { border-top: 4px solid #dc3545; }
.wallet-stat-card.debits .wallet-stat-number { color: #dc3545; }

.wallet-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.wallet-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    color: #2271b1;
    display: flex;
    align-items: center;
}
.wallet-card h2 i {
    margin-right: 8px;
}
.wallet-table {
    width: 100%;
    border-collapse: collapse;
}
.wallet-table th, .wallet-table td {
    padding: 12px 8px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.wallet-table th {
    background: #f9f9f9;
    font-weight: 600;
    color: #555;
}
.wallet-table tr:hover {
    background: #f9f9f9;
}
.wallet-filter {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    grid-gap: 15px;
    margin-bottom: 20px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}
.transaction-type {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.transaction-type.referral { background: #e3f2fd; color: #1976d2; }
.transaction-type.task { background: #e8f5e8; color: #2e7d32; }
.transaction-type.withdrawal { background: #fff3e0; color: #f57c00; }
.transaction-type.bonus { background: #f3e5f5; color: #7b1fa2; }
.transaction-type.penalty { background: #ffebee; color: #d32f2f; }
.points-positive { color: #2e7d32; font-weight: bold; }
.points-negative { color: #d32f2f; font-weight: bold; }
.points-zero { color: #666; }
.pagination {
    margin-top: 20px;
    text-align: center;
}
.pagination a, .pagination span {
    display: inline-block;
    padding: 8px 12px;
    margin-right: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #2271b1;
}
.pagination span.current {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}
.pagination a:hover {
    background: #f0f0f0;
}
</style>

<!-- Statistics Cards -->
<div class="wallet-stats-grid">
    <div class="wallet-stat-card balance">
        <h3><?php _e('Total Wallet Balance', 'indoor-tasks'); ?></h3>
        <div class="wallet-stat-number"><?php echo number_format($total_balance); ?></div>
        <div class="wallet-stat-subtitle"><?php _e('Points across all users', 'indoor-tasks'); ?></div>
    </div>
    <div class="wallet-stat-card transactions">
        <h3><?php _e('Total Transactions', 'indoor-tasks'); ?></h3>
        <div class="wallet-stat-number"><?php echo number_format($total_transactions); ?></div>
        <div class="wallet-stat-subtitle"><?php echo sprintf(__('%d in last 7 days', 'indoor-tasks'), $recent_transactions); ?></div>
    </div>
    <div class="wallet-stat-card credits">
        <h3><?php _e('Total Credits', 'indoor-tasks'); ?></h3>
        <div class="wallet-stat-number">+<?php echo number_format($total_credits); ?></div>
        <div class="wallet-stat-subtitle"><?php _e('Points added to wallets', 'indoor-tasks'); ?></div>
    </div>
    <div class="wallet-stat-card debits">
        <h3><?php _e('Total Debits', 'indoor-tasks'); ?></h3>
        <div class="wallet-stat-number">-<?php echo number_format($total_debits); ?></div>
        <div class="wallet-stat-subtitle"><?php _e('Points removed from wallets', 'indoor-tasks'); ?></div>
    </div>
</div>

<!-- Transaction Types Breakdown -->
<?php if (!empty($transaction_types)): ?>
<div class="wallet-card">
    <h2><i class="dashicons dashicons-chart-pie"></i> <?php _e('Transaction Types Breakdown', 'indoor-tasks'); ?></h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
        <?php foreach ($transaction_types as $type): ?>
        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; text-align: center;">
            <div class="transaction-type <?php echo esc_attr($type->type); ?>"><?php echo esc_html(ucfirst($type->type)); ?></div>
            <div style="font-size: 20px; font-weight: bold; margin: 8px 0;"><?php echo number_format($type->count); ?></div>
            <div style="font-size: 12px; color: #666;"><?php echo number_format($type->total_points); ?> points</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filters and Transactions Table -->
<div class="wallet-card">
    <h2><i class="dashicons dashicons-filter"></i> <?php _e('Transaction History', 'indoor-tasks'); ?></h2>
    
    <!-- Filter Form -->
    <form method="get" class="wallet-filter">
        <input type="hidden" name="page" value="indoor-tasks-wallet-transactions">
        <div>
            <label for="filter_user"><?php _e('Filter by User:', 'indoor-tasks'); ?></label>
            <select name="filter_user" id="filter_user" style="width: 100%;">
                <option value=""><?php _e('All Users', 'indoor-tasks'); ?></option>
                <?php
                $users = $wpdb->get_results("SELECT DISTINCT w.user_id, u.user_login 
                    FROM $wallet_table w 
                    LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID 
                    ORDER BY u.user_login");
                foreach ($users as $user) {
                    if ($user->user_login) {
                        echo '<option value="' . $user->user_id . '" ' . selected($filter_user, $user->user_id, false) . '>' . esc_html($user->user_login) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
        <div>
            <label for="filter_type"><?php _e('Transaction Type:', 'indoor-tasks'); ?></label>
            <select name="filter_type" id="filter_type" style="width: 100%;">
                <option value=""><?php _e('All Types', 'indoor-tasks'); ?></option>
                <?php foreach ($transaction_types as $type): ?>
                    <option value="<?php echo esc_attr($type->type); ?>" <?php selected($filter_type, $type->type); ?>>
                        <?php echo esc_html(ucfirst($type->type)); ?>
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
        <div style="display: flex; align-items: flex-end; gap: 10px;">
            <button type="submit" class="button button-primary"><?php _e('Apply Filters', 'indoor-tasks'); ?></button>
            <?php if ($filter_user || $filter_type || $filter_date_from || $filter_date_to): ?>
                <a href="?page=indoor-tasks-wallet-transactions" class="button"><?php _e('Clear Filters', 'indoor-tasks'); ?></a>
            <?php endif; ?>
        </div>
    </form>
    
    <!-- Transactions Table -->
    <table class="wallet-table">
        <thead>
            <tr>
                <th><?php _e('ID', 'indoor-tasks'); ?></th>
                <th><?php _e('User', 'indoor-tasks'); ?></th>
                <th><?php _e('Type', 'indoor-tasks'); ?></th>
                <th><?php _e('Points', 'indoor-tasks'); ?></th>
                <th><?php _e('Description', 'indoor-tasks'); ?></th>
                <th><?php _e('Reference', 'indoor-tasks'); ?></th>
                <th><?php _e('Date', 'indoor-tasks'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($paged_transactions)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #666; padding: 20px;">
                        <?php _e('No transactions found.', 'indoor-tasks'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($paged_transactions as $transaction): ?>
                    <tr>
                        <td><strong>#<?php echo $transaction->id; ?></strong></td>
                        <td>
                            <?php if ($transaction->user_login): ?>
                                <strong><?php echo esc_html($transaction->user_login); ?></strong><br>
                                <small style="color: #666;"><?php echo esc_html($transaction->user_email); ?></small>
                            <?php else: ?>
                                <em style="color: #999;"><?php _e('User not found', 'indoor-tasks'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="transaction-type <?php echo esc_attr($transaction->type); ?>">
                                <?php echo esc_html(ucfirst($transaction->type)); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $points_class = $transaction->points > 0 ? 'points-positive' : ($transaction->points < 0 ? 'points-negative' : 'points-zero');
                            $points_prefix = $transaction->points > 0 ? '+' : '';
                            ?>
                            <span class="<?php echo $points_class; ?>">
                                <?php echo $points_prefix . number_format($transaction->points); ?>
                            </span>
                        </td>
                        <td style="max-width: 200px; word-wrap: break-word;">
                            <?php echo esc_html($transaction->description ?: __('No description', 'indoor-tasks')); ?>
                        </td>
                        <td>
                            <?php if ($transaction->reference_id && $transaction->ref_login): ?>
                                <small>
                                    <strong><?php echo esc_html($transaction->ref_login); ?></strong><br>
                                    <span style="color: #666;"><?php echo esc_html($transaction->ref_email); ?></span>
                                </small>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo date('M j, Y', strtotime($transaction->created_at)); ?></strong><br>
                            <small style="color: #666;"><?php echo date('H:i', strtotime($transaction->created_at)); ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $url_params = $_GET;
            unset($url_params['paged']);
            $base_url = '?' . http_build_query($url_params);
            
            if ($current_page > 1) {
                echo '<a href="' . $base_url . '&paged=' . ($current_page - 1) . '">&laquo; ' . __('Previous', 'indoor-tasks') . '</a>';
            }
            
            for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
                if ($i == $current_page) {
                    echo '<span class="current">' . $i . '</span>';
                } else {
                    echo '<a href="' . $base_url . '&paged=' . $i . '">' . $i . '</a>';
                }
            }
            
            if ($current_page < $total_pages) {
                echo '<a href="' . $base_url . '&paged=' . ($current_page + 1) . '">' . __('Next', 'indoor-tasks') . ' &raquo;</a>';
            }
            ?>
        </div>
        
        <div style="text-align: center; margin-top: 10px; color: #666; font-size: 14px;">
            <?php echo sprintf(__('Showing %d-%d of %d transactions', 'indoor-tasks'), 
                $offset + 1, 
                min($offset + $items_per_page, $total_items), 
                $total_items
            ); ?>
        </div>
    <?php endif; ?>
</div>

</div>
