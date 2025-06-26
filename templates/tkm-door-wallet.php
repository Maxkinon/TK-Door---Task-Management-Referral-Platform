<?php
/**
 * Template Name: TKM Door - Wallet
 * Description: Modern wallet template showing balance, transactions, and withdrawal options
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
$current_user = wp_get_current_user();

// Get database references
global $wpdb;

// Get user's wallet data
function tkm_get_user_wallet_data($user_id) {
    global $wpdb;
    
    // Check if wallet table exists
    $wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
    
    if ($wallet_table_exists) {
        // Get total points from wallet table
        $total_points = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
            $user_id
        ));
        
        // Get recent transactions
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_task_wallet 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT 20",
            $user_id
        ));
    } else {
        // Fallback to user meta
        $total_points = get_user_meta($user_id, 'indoor_tasks_points', true) ?: 0;
        $transactions = array();
    }
    
    return array(
        'total_points' => intval($total_points),
        'transactions' => $transactions
    );
}

// Get withdrawal requests
function tkm_get_user_withdrawals($user_id) {
    global $wpdb;
    
    $withdrawals_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_withwithdrawals'") === $wpdb->prefix . 'indoor_task_withwithdrawals';
    
    if ($withdrawals_table_exists) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_task_withwithdrawals 
             WHERE user_id = %d 
             ORDER BY requested_at DESC 
             LIMIT 10",
            $user_id
        ));
    }
    
    return array();
}

// Get withdrawal settings
function tkm_get_withdrawal_settings() {
    return array(
        'min_points' => get_option('indoor_tasks_min_withdraw_points', 500),
        'conversion_rate' => get_option('indoor_tasks_conversion_rate', 0.01), // 1 point = $0.01
        'enabled' => get_option('indoor_tasks_enable_withdrawals', 1)
    );
}

$wallet_data = tkm_get_user_wallet_data($current_user_id);
$withdrawal_history = tkm_get_user_withdrawals($current_user_id);
$withdrawal_settings = tkm_get_withdrawal_settings();

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>My Wallet - <?php bloginfo('name'); ?></title>
    
    <?php wp_head(); ?>
    
    <!-- TKM Door Wallet Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-wallet.css?ver=1.0.0">
</head>
<body class="tkm-door-wallet">
    <div class="tkm-wallet-container">
        <!-- Include Sidebar -->
        <?php include INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'; ?>
        
        <div class="tkm-wallet-content">
            <!-- Header Section -->
            <div class="tkm-wallet-header">
                <div class="tkm-header-content">
                    <h1>💰 My Wallet</h1>
                    <p class="tkm-header-subtitle">Manage your points, view transactions, and withdraw funds</p>
                </div>
            </div>
            
            <!-- Balance Section -->
            <div class="tkm-balance-section">
                <div class="tkm-balance-card">
                    <div class="tkm-balance-header">
                        <h2>Current Balance</h2>
                        <div class="tkm-balance-icon">💎</div>
                    </div>
                    
                    <div class="tkm-balance-amount">
                        <span class="tkm-points"><?php echo number_format($wallet_data['total_points']); ?></span>
                        <span class="tkm-points-label">Points</span>
                    </div>
                    
                    <?php if ($withdrawal_settings['conversion_rate'] > 0): ?>
                    <div class="tkm-balance-equivalent">
                        ≈ $<?php echo number_format($wallet_data['total_points'] * $withdrawal_settings['conversion_rate'], 2); ?> USD
                    </div>
                    <?php endif; ?>
                    
                    <div class="tkm-balance-actions">
                        <?php if ($withdrawal_settings['enabled']): ?>
                            <?php 
                            $withdraw_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tkm-door-withdraw.php', 'withdraw');
                            $withdraw_url = $withdraw_page ? get_permalink($withdraw_page->ID) : '#';
                            ?>
                            <a href="<?php echo $withdraw_url; ?>" class="tkm-btn tkm-btn-primary">
                                <svg class="tkm-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 7v10c0 1.1.9 2 2 2h14c0-1.1-.9-2-2-2H5V7"/>
                                    <path d="M7 3h10c1.1 0 2 .9 2 2v4"/>
                                    <path d="M3 11h14"/>
                                </svg>
                                Withdraw Funds
                            </a>
                        <?php endif; ?>
                        
                        <button type="button" class="tkm-btn tkm-btn-secondary" onclick="tkm_refreshTransactions()">
                            <svg class="tkm-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 4v6h6"/>
                                <path d="M23 20v-6h-6"/>
                                <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
                            </svg>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Recent Transactions Section -->
            <div class="tkm-transactions-section">
                <div class="tkm-section-header">
                    <h3>Recent Transactions</h3>
                    <div class="tkm-filter-tabs">
                        <button class="tkm-tab active" data-filter="all">All</button>
                        <button class="tkm-tab" data-filter="reward">Earned</button>
                        <button class="tkm-tab" data-filter="withdrawal">Withdrawals</button>
                        <button class="tkm-tab" data-filter="bonus">Bonuses</button>
                    </div>
                </div>
                
                <div class="tkm-transactions-table">
                    <div class="tkm-table-header">
                        <span>Date</span>
                        <span>Type</span>
                        <span>Description</span>
                        <span>Points</span>
                        <span>Status</span>
                    </div>
                    
                    <div class="tkm-table-body" id="transactions-list">
                        <?php if (!empty($wallet_data['transactions'])): ?>
                            <?php foreach ($wallet_data['transactions'] as $transaction): ?>
                                <div class="tkm-transaction-row" data-type="<?php echo esc_attr($transaction->type); ?>">
                                    <div class="tkm-transaction-date">
                                        <?php echo date('M j, Y', strtotime($transaction->created_at)); ?>
                                        <span class="tkm-transaction-time"><?php echo date('g:i A', strtotime($transaction->created_at)); ?></span>
                                    </div>
                                    
                                    <div class="tkm-transaction-type">
                                        <?php
                                        $type_icons = array(
                                            'reward' => '🎯',
                                            'bonus' => '🎁',
                                            'admin' => '⚙️',
                                            'withdrawal' => '💸'
                                        );
                                        echo $type_icons[$transaction->type] ?? '📝';
                                        ?>
                                        <span><?php echo ucfirst($transaction->type); ?></span>
                                    </div>
                                    
                                    <div class="tkm-transaction-description">
                                        <?php echo esc_html($transaction->description ?: 'Transaction'); ?>
                                    </div>
                                    
                                    <div class="tkm-transaction-points <?php echo $transaction->points >= 0 ? 'positive' : 'negative'; ?>">
                                        <?php echo $transaction->points >= 0 ? '+' : ''; ?><?php echo number_format($transaction->points); ?>
                                    </div>
                                    
                                    <div class="tkm-transaction-status completed">
                                        <span class="tkm-status-dot"></span>
                                        Completed
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="tkm-empty-state">
                                <div class="tkm-empty-icon">📊</div>
                                <h4>No Transactions Yet</h4>
                                <p>Complete tasks and earn points to see your transaction history here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Withdrawal History Section -->
            <?php if (!empty($withdrawal_history)): ?>
            <div class="tkm-withdrawals-section">
                <div class="tkm-section-header">
                    <h3>Withdrawal History</h3>
                </div>
                
                <div class="tkm-withdrawals-grid">
                    <?php foreach ($withdrawal_history as $withdrawal): ?>
                        <div class="tkm-withdrawal-card">
                            <div class="tkm-withdrawal-header">
                                <div class="tkm-withdrawal-method">
                                    <span class="tkm-method-icon">💳</span>
                                    <span><?php echo esc_html($withdrawal->method); ?></span>
                                </div>
                                <div class="tkm-withdrawal-status <?php echo esc_attr($withdrawal->status); ?>">
                                    <?php
                                    $status_labels = array(
                                        'pending' => '⏳ Pending',
                                        'approved' => '✅ Approved',
                                        'rejected' => '❌ Rejected'
                                    );
                                    echo $status_labels[$withdrawal->status] ?? ucfirst($withdrawal->status);
                                    ?>
                                </div>
                            </div>
                            
                            <div class="tkm-withdrawal-amount">
                                <?php echo number_format($withdrawal->points); ?> Points
                                <?php if ($withdrawal->amount > 0): ?>
                                    <span class="tkm-amount-equivalent">($<?php echo number_format($withdrawal->amount, 2); ?>)</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="tkm-withdrawal-date">
                                Requested: <?php echo date('M j, Y g:i A', strtotime($withdrawal->requested_at)); ?>
                                <?php if ($withdrawal->processed_at): ?>
                                    <br>Processed: <?php echo date('M j, Y g:i A', strtotime($withdrawal->processed_at)); ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($withdrawal->admin_reason): ?>
                                <div class="tkm-withdrawal-reason">
                                    <strong>Note:</strong> <?php echo esc_html($withdrawal->admin_reason); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="tkm-loading-overlay" style="display: none;">
        <div class="tkm-loading-spinner"></div>
        <p>Loading...</p>
    </div>
    
    <!-- Messages -->
    <div id="message-container" class="tkm-message-container"></div>
    
    <?php wp_footer(); ?>
    
    <!-- TKM Door Wallet Scripts -->
    <script>
        // Pass PHP variables to JavaScript
        window.tkmWallet = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('tkm_wallet_nonce'); ?>',
            currentUserId: <?php echo $current_user_id; ?>
        };
    </script>
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-wallet.js?ver=1.0.0"></script>
</body>
</html>
