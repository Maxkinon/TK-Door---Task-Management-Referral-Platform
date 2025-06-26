<?php
/**
 * Template Name: TKM Door - Withdraw
 * Description: Modern withdrawal template for requesting fund withdrawals
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

// Handle withdrawal request
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'submit_withdrawal') {
    if (wp_verify_nonce($_POST['nonce'], 'tkm_withdraw_nonce')) {
        $points = intval($_POST['points']);
        $method = sanitize_text_field($_POST['method']);
        $account_details = sanitize_textarea_field($_POST['account_details']);
        
        // Get user's current balance
        $wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
        
        if ($wallet_table_exists) {
            $current_balance = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
                $current_user_id
            ));
        } else {
            $current_balance = get_user_meta($current_user_id, 'indoor_tasks_points', true) ?: 0;
        }
        
        $min_points = get_option('indoor_tasks_min_withdraw_points', 500);
        $withdrawal_enabled = get_option('indoor_tasks_enable_withdrawals', 1);
        
        $errors = array();
        
        // Validation
        if (!$withdrawal_enabled) {
            $errors[] = 'Withdrawals are currently disabled.';
        }
        
        if ($points < $min_points) {
            $errors[] = "Minimum withdrawal amount is {$min_points} points.";
        }
        
        if ($points > $current_balance) {
            $errors[] = 'Insufficient balance.';
        }
        
        if (empty($method)) {
            $errors[] = 'Please select a withdrawal method.';
        }
        
        if (empty($account_details)) {
            $errors[] = 'Please provide account details for the selected method.';
        }
        
        if (empty($errors)) {
            // Check if withdrawal table exists
            $withdrawals_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_withwithdrawals'") === $wpdb->prefix . 'indoor_task_withwithdrawals';
            
            if ($withdrawals_table_exists) {
                // Calculate amount in currency
                $conversion_rate = get_option('indoor_tasks_conversion_rate', 0.01);
                $amount = $points * $conversion_rate;
                
                // Insert withdrawal request
                $result = $wpdb->insert(
                    $wpdb->prefix . 'indoor_task_withwithdrawals',
                    array(
                        'user_id' => $current_user_id,
                        'method' => $method,
                        'amount' => $amount,
                        'points' => $points,
                        'status' => 'pending',
                        'custom_fields' => $account_details,
                        'requested_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%f', '%d', '%s', '%s', '%s')
                );
                
                if ($result !== false) {
                    // Deduct points from wallet (pending withdrawal)
                    $wpdb->insert(
                        $wpdb->prefix . 'indoor_task_wallet',
                        array(
                            'user_id' => $current_user_id,
                            'points' => -$points,
                            'type' => 'withdrawal',
                            'description' => "Withdrawal request - {$method}",
                            'reference_id' => $wpdb->insert_id,
                            'created_at' => current_time('mysql')
                        ),
                        array('%d', '%d', '%s', '%s', '%d', '%s')
                    );
                    
                    $success_message = 'Withdrawal request submitted successfully! You will be notified once it is processed.';
                } else {
                    $errors[] = 'Failed to submit withdrawal request. Please try again.';
                }
            } else {
                $errors[] = 'Withdrawal system is not properly configured.';
            }
        }
    } else {
        $errors[] = 'Security check failed. Please try again.';
    }
}

// Get user's wallet data
function tkm_get_user_balance($user_id) {
    global $wpdb;
    
    $wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
    
    if ($wallet_table_exists) {
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
            $user_id
        )));
    } else {
        return intval(get_user_meta($user_id, 'indoor_tasks_points', true) ?: 0);
    }
}

// Get withdrawal methods
function tkm_get_withdrawal_methods() {
    global $wpdb;
    
    $methods_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_withdrawal_methods'") === $wpdb->prefix . 'indoor_task_withdrawal_methods';
    
    if ($methods_table_exists) {
        $methods = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}indoor_task_withdrawal_methods WHERE enabled = 1 ORDER BY sort_order ASC",
            ARRAY_A
        );
        
        if (!empty($methods)) {
            return $methods;
        }
    }
    
    // Fallback to options
    return get_option('indoor_tasks_withdrawal_methods', array(
        array(
            'method' => 'PayPal',
            'conversion_rate' => 0.01,
            'min_points' => 500,
            'description' => 'Withdraw to your PayPal account',
            'icon_url' => '',
            'processing_time' => '2-3 business days'
        ),
        array(
            'method' => 'Bank Transfer',
            'conversion_rate' => 0.01,
            'min_points' => 1000,
            'description' => 'Direct bank transfer',
            'icon_url' => '',
            'processing_time' => '3-5 business days'
        )
    ));
}

// Get withdrawal settings
$withdrawal_settings = array(
    'min_points' => get_option('indoor_tasks_min_withdraw_points', 500),
    'conversion_rate' => get_option('indoor_tasks_conversion_rate', 0.01),
    'enabled' => get_option('indoor_tasks_enable_withdrawals', 1),
    'max_per_week' => get_option('indoor_tasks_max_withdraw_per_week', 0)
);

$current_balance = tkm_get_user_balance($current_user_id);
$withdrawal_methods = tkm_get_withdrawal_methods();

// Check if user meets minimum requirements
$can_withdraw = $withdrawal_settings['enabled'] && ($current_balance >= $withdrawal_settings['min_points']);

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Withdraw Funds - <?php bloginfo('name'); ?></title>
    
    <?php wp_head(); ?>
    
    <!-- TKM Door Withdraw Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-withdraw.css?ver=1.0.0">
</head>
<body class="tkm-door-withdraw">
    <div class="tkm-withdraw-container">
        <!-- Include Sidebar -->
        <?php include INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'; ?>
        
        <div class="tkm-withdraw-content">
            <!-- Header Section -->
            <div class="tkm-withdraw-header">
                <div class="tkm-header-content">
                    <h1>💸 Withdraw Funds</h1>
                    <p class="tkm-header-subtitle">Convert your points to cash and request a withdrawal</p>
                </div>
                
                <div class="tkm-balance-display">
                    <div class="tkm-balance-label">Available Balance</div>
                    <div class="tkm-balance-amount"><?php echo number_format($current_balance); ?> Points</div>
                    <?php if ($withdrawal_settings['conversion_rate'] > 0): ?>
                        <div class="tkm-balance-equivalent">
                            ≈ $<?php echo number_format($current_balance * $withdrawal_settings['conversion_rate'], 2); ?> USD
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="tkm-message tkm-message-success">
                    <div class="tkm-message-icon">✅</div>
                    <div class="tkm-message-text"><?php echo esc_html($success_message); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="tkm-message tkm-message-error">
                    <div class="tkm-message-icon">❌</div>
                    <div class="tkm-message-text">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo esc_html($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Withdrawal Requirements -->
            <div class="tkm-requirements-section">
                <h3>Withdrawal Requirements</h3>
                <div class="tkm-requirements-grid">
                    <div class="tkm-requirement-item <?php echo $current_balance >= $withdrawal_settings['min_points'] ? 'met' : 'not-met'; ?>">
                        <div class="tkm-requirement-icon">
                            <?php echo $current_balance >= $withdrawal_settings['min_points'] ? '✅' : '❌'; ?>
                        </div>
                        <div class="tkm-requirement-text">
                            <strong>Minimum Balance:</strong> <?php echo number_format($withdrawal_settings['min_points']); ?> points
                            <br><small>You have <?php echo number_format($current_balance); ?> points</small>
                        </div>
                    </div>
                    
                    <div class="tkm-requirement-item <?php echo $withdrawal_settings['enabled'] ? 'met' : 'not-met'; ?>">
                        <div class="tkm-requirement-icon">
                            <?php echo $withdrawal_settings['enabled'] ? '✅' : '❌'; ?>
                        </div>
                        <div class="tkm-requirement-text">
                            <strong>Withdrawals Status:</strong> 
                            <?php echo $withdrawal_settings['enabled'] ? 'Enabled' : 'Disabled'; ?>
                        </div>
                    </div>
                    
                    <div class="tkm-requirement-item met">
                        <div class="tkm-requirement-icon">✅</div>
                        <div class="tkm-requirement-text">
                            <strong>Account Verified:</strong> Active user
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($can_withdraw): ?>
            <!-- Withdrawal Form -->
            <div class="tkm-withdrawal-form-section">
                <h3>Request Withdrawal</h3>
                
                <form method="post" class="tkm-withdrawal-form" id="withdrawal-form">
                    <?php wp_nonce_field('tkm_withdraw_nonce', 'nonce'); ?>
                    <input type="hidden" name="action" value="submit_withdrawal">
                    
                    <div class="tkm-form-group">
                        <label for="points">Amount to Withdraw (Points)</label>
                        <div class="tkm-input-group">
                            <input 
                                type="number" 
                                id="points" 
                                name="points" 
                                min="<?php echo $withdrawal_settings['min_points']; ?>" 
                                max="<?php echo $current_balance; ?>" 
                                step="1" 
                                required
                                class="tkm-input"
                                placeholder="Enter points amount"
                            >
                            <div class="tkm-input-suffix">Points</div>
                        </div>
                        <div class="tkm-conversion-display" id="conversion-display">
                            = $0.00 USD
                        </div>
                    </div>
                    
                    <div class="tkm-form-group">
                        <label for="method">Withdrawal Method</label>
                        <select id="method" name="method" required class="tkm-select">
                            <option value="">Select withdrawal method</option>
                            <?php foreach ($withdrawal_methods as $method): ?>
                                <option 
                                    value="<?php echo esc_attr($method['method']); ?>"
                                    data-conversion="<?php echo esc_attr($method['conversion_rate'] ?? $withdrawal_settings['conversion_rate']); ?>"
                                    data-min="<?php echo esc_attr($method['min_points'] ?? $withdrawal_settings['min_points']); ?>"
                                >
                                    <?php echo esc_html($method['method']); ?>
                                    <?php if (!empty($method['processing_time'])): ?>
                                        (<?php echo esc_html($method['processing_time']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="tkm-form-group">
                        <label for="account_details">Account Details</label>
                        <textarea 
                            id="account_details" 
                            name="account_details" 
                            required 
                            class="tkm-textarea"
                            rows="4"
                            placeholder="Enter your account details (email for PayPal, account number for bank transfer, etc.)"
                        ></textarea>
                        <div class="tkm-field-help" id="method-help">
                            Please provide the necessary account information for your selected withdrawal method.
                        </div>
                    </div>
                    
                    <div class="tkm-form-actions">
                        <button type="submit" class="tkm-btn tkm-btn-primary" id="submit-btn">
                            <svg class="tkm-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11H1m0 0l3-3m-3 3l3 3m8-13l3 3m-3-3v12"/>
                            </svg>
                            Submit Withdrawal Request
                        </button>
                        
                        <?php 
                        $wallet_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tkm-door-wallet.php', 'wallet');
                        $wallet_url = $wallet_page ? get_permalink($wallet_page->ID) : '#';
                        ?>
                        <a href="<?php echo $wallet_url; ?>" class="tkm-btn tkm-btn-secondary">
                            <svg class="tkm-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 7H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h14a2 2 0 0 1 2-2V9a2 2 0 0 1-2-2z"/>
                                <path d="M3 7v2a3 3 0 0 0 3 3h12a3 3 0 0 0 3-3V7"/>
                                <circle cx="12" cy="13" r="1"/>
                            </svg>
                            Back to Wallet
                        </a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <!-- Requirements Not Met -->
            <div class="tkm-requirements-not-met">
                <div class="tkm-empty-state">
                    <div class="tkm-empty-icon">⚠️</div>
                    <h4>Withdrawal Requirements Not Met</h4>
                    <?php if (!$withdrawal_settings['enabled']): ?>
                        <p>Withdrawals are currently disabled. Please check back later.</p>
                    <?php elseif ($current_balance < $withdrawal_settings['min_points']): ?>
                        <p>You need at least <?php echo number_format($withdrawal_settings['min_points']); ?> points to make a withdrawal.</p>
                        <p>You currently have <?php echo number_format($current_balance); ?> points.</p>
                        <p>Earn <?php echo number_format($withdrawal_settings['min_points'] - $current_balance); ?> more points to unlock withdrawals.</p>
                    <?php endif; ?>
                    
                    <?php 
                    $dashboard_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tkm-door-dashboard.php', 'dashboard');
                    $dashboard_url = $dashboard_page ? get_permalink($dashboard_page->ID) : '#';
                    ?>
                    <a href="<?php echo $dashboard_url; ?>" class="tkm-btn tkm-btn-primary">
                        <svg class="tkm-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 11H1m0 0l3-3m-3 3l3 3"/>
                            <path d="M22 12h-10"/>
                        </svg>
                        Complete Tasks to Earn Points
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Information Section -->
            <div class="tkm-info-section">
                <h3>Important Information</h3>
                <div class="tkm-info-cards">
                    <div class="tkm-info-card">
                        <div class="tkm-info-icon">⏱️</div>
                        <div class="tkm-info-content">
                            <h4>Processing Time</h4>
                            <p>Withdrawals are typically processed within 2-3 business days after approval.</p>
                        </div>
                    </div>
                    
                    <div class="tkm-info-card">
                        <div class="tkm-info-icon">🔒</div>
                        <div class="tkm-info-content">
                            <h4>Security</h4>
                            <p>All withdrawal requests are manually reviewed for security purposes.</p>
                        </div>
                    </div>
                    
                    <div class="tkm-info-card">
                        <div class="tkm-info-icon">📧</div>
                        <div class="tkm-info-content">
                            <h4>Notifications</h4>
                            <p>You'll receive email updates on the status of your withdrawal request.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="tkm-loading-overlay" style="display: none;">
        <div class="tkm-loading-spinner"></div>
        <p>Processing withdrawal request...</p>
    </div>
    
    <?php wp_footer(); ?>
    
    <!-- TKM Door Withdraw Scripts -->
    <script>
        // Pass PHP variables to JavaScript
        window.tkmWithdraw = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('tkm_withdraw_nonce'); ?>',
            currentUserId: <?php echo $current_user_id; ?>,
            conversionRate: <?php echo $withdrawal_settings['conversion_rate']; ?>,
            currentBalance: <?php echo $current_balance; ?>
        };
    </script>
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-withdraw.js?ver=1.0.0"></script>
</body>
</html>
