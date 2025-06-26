<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Get filter parameters
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$gateway_filter = isset( $_GET['gateway'] ) ? sanitize_text_field( $_GET['gateway'] ) : '';
$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';

// Pagination
$per_page = 20;
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset = ( $current_page - 1 ) * $per_page;

global $wpdb;

// Create payments table if it doesn't exist
$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}indoor_task_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    gateway VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(255),
    gateway_response TEXT,
    status ENUM('pending','completed','failed','cancelled','refunded') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) {$wpdb->get_charset_collate()};");

// Build query with filters
$where_conditions = array( '1=1' );
$query_params = array();

if ( $status_filter ) {
    $where_conditions[] = 'status = %s';
    $query_params[] = $status_filter;
}

if ( $gateway_filter ) {
    $where_conditions[] = 'gateway = %s';
    $query_params[] = $gateway_filter;
}

if ( $date_from ) {
    $where_conditions[] = 'DATE(created_at) >= %s';
    $query_params[] = $date_from;
}

if ( $date_to ) {
    $where_conditions[] = 'DATE(created_at) <= %s';
    $query_params[] = $date_to;
}

$where_clause = implode( ' AND ', $where_conditions );

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_payments WHERE $where_clause";
if ( ! empty( $query_params ) ) {
    $total_items = $wpdb->get_var( $wpdb->prepare( $count_query, $query_params ) );
} else {
    $total_items = $wpdb->get_var( $count_query );
}

// Get payments data
$payments_query = "
    SELECT p.*, u.display_name, u.user_email, mp.name as plan_name
    FROM {$wpdb->prefix}indoor_task_payments p
    LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
    LEFT JOIN {$wpdb->prefix}indoor_task_membership_plans mp ON p.plan_id = mp.id
    WHERE $where_clause
    ORDER BY p.created_at DESC
    LIMIT %d OFFSET %d
";

$final_params = array_merge( $query_params, array( $per_page, $offset ) );
if ( ! empty( $final_params ) ) {
    $payments = $wpdb->get_results( $wpdb->prepare( $payments_query, $final_params ) );
} else {
    $payments = $wpdb->get_results( $payments_query );
}

// Get payment statistics
$stats_query_base = "SELECT 
    COUNT(*) as total_payments,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount END), 0) as total_revenue,
    COALESCE(AVG(CASE WHEN status = 'completed' THEN amount END), 0) as avg_payment
    FROM {$wpdb->prefix}indoor_task_payments";

$stats = $wpdb->get_row( $stats_query_base );

// Get recent payment trends (last 7 days)
$trends = $wpdb->get_results( "
    SELECT 
        DATE(created_at) as payment_date,
        COUNT(*) as daily_count,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN amount END), 0) as daily_revenue
    FROM {$wpdb->prefix}indoor_task_payments 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY payment_date DESC
" );

// Calculate pagination
$total_pages = ceil( $total_items / $per_page );

// Handle bulk actions
if ( isset( $_POST['bulk_action'] ) && isset( $_POST['payment_ids'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bulk_payment_action' ) ) {
    $action = sanitize_text_field( $_POST['bulk_action'] );
    $payment_ids = array_map( 'intval', $_POST['payment_ids'] );
    
    if ( $action === 'refund' ) {
        foreach ( $payment_ids as $payment_id ) {
            $wpdb->update(
                $wpdb->prefix . 'indoor_task_payments',
                array( 'status' => 'refunded', 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $payment_id )
            );
        }
        echo '<div class="notice notice-success"><p>Selected payments have been marked as refunded.</p></div>';
    }
}
?>

<div class="wrap">
    <h1><?php _e( 'Recent Payments', 'indoor-tasks' ); ?></h1>
    
    <!-- Statistics Cards -->
    <div class="indoor-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="indoor-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #666; font-size: 14px; font-weight: normal;">Total Payments</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #333;"><?php echo number_format( $stats->total_payments ); ?></p>
                </div>
                <div style="width: 40px; height: 40px; background: #2271b1; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <span style="color: white; font-size: 18px;">💳</span>
                </div>
            </div>
        </div>
        
        <div class="indoor-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #666; font-size: 14px; font-weight: normal;">Successful</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo number_format( $stats->successful_payments ); ?></p>
                </div>
                <div style="width: 40px; height: 40px; background: #00a32a; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <span style="color: white; font-size: 18px;">✅</span>
                </div>
            </div>
        </div>
        
        <div class="indoor-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #666; font-size: 14px; font-weight: normal;">Total Revenue</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #1d4ed8;">$<?php echo number_format( $stats->total_revenue, 2 ); ?></p>
                </div>
                <div style="width: 40px; height: 40px; background: #1d4ed8; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <span style="color: white; font-size: 18px;">💰</span>
                </div>
            </div>
        </div>
        
        <div class="indoor-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #666; font-size: 14px; font-weight: normal;">Average Payment</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #7c3aed;">$<?php echo number_format( $stats->avg_payment, 2 ); ?></p>
                </div>
                <div style="width: 40px; height: 40px; background: #7c3aed; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <span style="color: white; font-size: 18px;">📊</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Trends -->
    <?php if ( ! empty( $trends ) ): ?>
    <div class="card" style="margin-bottom: 20px;">
        <h2 style="margin-top: 0;">Recent Payment Trends (Last 7 Days)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Payments</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $trends as $trend ): ?>
                    <tr>
                        <td><?php echo date( 'M j, Y', strtotime( $trend->payment_date ) ); ?></td>
                        <td><?php echo number_format( $trend->daily_count ); ?></td>
                        <td>$<?php echo number_format( $trend->daily_revenue, 2 ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="tablenav top">
        <form method="get" style="display: inline-block;">
            <input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>">
            
            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending" <?php selected( $status_filter, 'pending' ); ?>>Pending</option>
                <option value="completed" <?php selected( $status_filter, 'completed' ); ?>>Completed</option>
                <option value="failed" <?php selected( $status_filter, 'failed' ); ?>>Failed</option>
                <option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>>Cancelled</option>
                <option value="refunded" <?php selected( $status_filter, 'refunded' ); ?>>Refunded</option>
            </select>
            
            <select name="gateway">
                <option value="">All Gateways</option>
                <option value="paypal" <?php selected( $gateway_filter, 'paypal' ); ?>>PayPal</option>
                <option value="stripe" <?php selected( $gateway_filter, 'stripe' ); ?>>Stripe</option>
            </select>
            
            <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="From Date">
            <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="To Date">
            
            <input type="submit" class="button" value="Filter">
            <a href="<?php echo admin_url( 'admin.php?page=' . $_GET['page'] ); ?>" class="button">Clear</a>
        </form>
    </div>
    
    <!-- Payments List -->
    <form method="post" action="">
        <?php wp_nonce_field( 'bulk_payment_action' ); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action">
                    <option value="">Bulk Actions</option>
                    <option value="refund">Mark as Refunded</option>
                </select>
                <input type="submit" class="button action" value="Apply">
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th>Payment ID</th>
                    <th>User</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Gateway</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Transaction ID</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $payments ) ): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            <p>No payments found.</p>
                            <?php if ( $status_filter || $gateway_filter || $date_from || $date_to ): ?>
                                <p><a href="<?php echo admin_url( 'admin.php?page=' . $_GET['page'] ); ?>">Clear filters</a> to see all payments.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ( $payments as $payment ): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="payment_ids[]" value="<?php echo $payment->id; ?>">
                            </th>
                            <td><strong>#<?php echo $payment->id; ?></strong></td>
                            <td>
                                <?php if ( $payment->display_name ): ?>
                                    <strong><?php echo esc_html( $payment->display_name ); ?></strong><br>
                                    <small><?php echo esc_html( $payment->user_email ); ?></small>
                                <?php else: ?>
                                    <em>User not found</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $payment->plan_name ? esc_html( $payment->plan_name ) : '<em>N/A</em>'; ?></td>
                            <td><strong>$<?php echo number_format( $payment->amount, 2 ); ?> <?php echo esc_html( $payment->currency ); ?></strong></td>
                            <td>
                                <span style="text-transform: capitalize;">
                                    <?php echo esc_html( $payment->gateway ); ?>
                                </span>
                            </td>
                            <td>
                                <span class="payment-status payment-status-<?php echo $payment->status; ?>" style="
                                    padding: 4px 8px;
                                    border-radius: 4px;
                                    font-size: 12px;
                                    font-weight: bold;
                                    text-transform: capitalize;
                                    <?php
                                    switch ( $payment->status ) {
                                        case 'completed':
                                            echo 'background: #d1fae5; color: #065f46;';
                                            break;
                                        case 'pending':
                                            echo 'background: #fef3c7; color: #92400e;';
                                            break;
                                        case 'failed':
                                            echo 'background: #fee2e2; color: #991b1b;';
                                            break;
                                        case 'cancelled':
                                            echo 'background: #f3f4f6; color: #374151;';
                                            break;
                                        case 'refunded':
                                            echo 'background: #e0e7ff; color: #3730a3;';
                                            break;
                                        default:
                                            echo 'background: #f3f4f6; color: #374151;';
                                    }
                                    ?>
                                ">
                                    <?php echo esc_html( $payment->status ); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date( 'M j, Y g:i A', strtotime( $payment->created_at ) ); ?>
                                <?php if ( $payment->updated_at && $payment->updated_at !== $payment->created_at ): ?>
                                    <br><small style="color: #666;">Updated: <?php echo date( 'M j, g:i A', strtotime( $payment->updated_at ) ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $payment->transaction_id ): ?>
                                    <code style="font-size: 11px;"><?php echo esc_html( $payment->transaction_id ); ?></code>
                                <?php else: ?>
                                    <em>N/A</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ( $total_pages > 1 ): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format( $total_items ); ?> items</span>
                    <span class="pagination-links">
                        <?php
                        $base_url = admin_url( 'admin.php?page=' . $_GET['page'] );
                        $query_args = array_filter( array(
                            'status' => $status_filter,
                            'gateway' => $gateway_filter,
                            'date_from' => $date_from,
                            'date_to' => $date_to
                        ) );
                        
                        if ( $current_page > 1 ):
                            $prev_url = add_query_arg( array_merge( $query_args, array( 'paged' => $current_page - 1 ) ), $base_url );
                            echo '<a class="prev-page button" href="' . esc_url( $prev_url ) . '">‹</a>';
                        endif;
                        
                        echo '<span class="paging-input">';
                        echo '<span class="tablenav-paging-text">' . $current_page . ' of ' . number_format( $total_pages ) . '</span>';
                        echo '</span>';
                        
                        if ( $current_page < $total_pages ):
                            $next_url = add_query_arg( array_merge( $query_args, array( 'paged' => $current_page + 1 ) ), $base_url );
                            echo '<a class="next-page button" href="' . esc_url( $next_url ) . '">›</a>';
                        endif;
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
document.getElementById('cb-select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="payment_ids[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});
</script>

<style>
.indoor-stats-grid {
    margin-bottom: 30px;
}
.indoor-stat-card:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease;
}
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.tablenav {
    margin: 10px 0;
}
.tablenav select, .tablenav input[type="date"] {
    margin-right: 5px;
}
</style>
