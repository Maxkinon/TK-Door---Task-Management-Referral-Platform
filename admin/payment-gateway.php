<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Handle form submissions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) ) {
    if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'payment_gateway_action' ) ) {
        wp_die( 'Security check failed' );
    }
    
    if ( $_POST['action'] === 'save_settings' ) {
        // PayPal Settings
        update_option( 'indoor_tasks_paypal_enabled', isset( $_POST['paypal_enabled'] ) ? 1 : 0 );
        update_option( 'indoor_tasks_paypal_mode', sanitize_text_field( $_POST['paypal_mode'] ) );
        update_option( 'indoor_tasks_paypal_client_id', sanitize_text_field( $_POST['paypal_client_id'] ) );
        update_option( 'indoor_tasks_paypal_client_secret', sanitize_text_field( $_POST['paypal_client_secret'] ) );
        
        // Stripe Settings
        update_option( 'indoor_tasks_stripe_enabled', isset( $_POST['stripe_enabled'] ) ? 1 : 0 );
        update_option( 'indoor_tasks_stripe_mode', sanitize_text_field( $_POST['stripe_mode'] ) );
        update_option( 'indoor_tasks_stripe_publishable_key', sanitize_text_field( $_POST['stripe_publishable_key'] ) );
        update_option( 'indoor_tasks_stripe_secret_key', sanitize_text_field( $_POST['stripe_secret_key'] ) );
        
        // Other Settings
        update_option( 'indoor_tasks_payment_currency', sanitize_text_field( $_POST['payment_currency'] ) );
        update_option( 'indoor_tasks_payment_success_page', intval( $_POST['payment_success_page'] ) );
        update_option( 'indoor_tasks_payment_cancel_page', intval( $_POST['payment_cancel_page'] ) );
        
        echo '<div class="notice notice-success"><p>Payment gateway settings saved successfully!</p></div>';
    }
    
    if ( $_POST['action'] === 'test_connection' ) {
        $gateway = sanitize_text_field( $_POST['gateway'] );
        $test_result = test_payment_gateway_connection( $gateway );
        echo '<div class="notice ' . ( $test_result['success'] ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $test_result['message'] ) . '</p></div>';
    }
}

// Get current settings
$paypal_enabled = get_option( 'indoor_tasks_paypal_enabled', 0 );
$paypal_mode = get_option( 'indoor_tasks_paypal_mode', 'sandbox' );
$paypal_client_id = get_option( 'indoor_tasks_paypal_client_id', '' );
$paypal_client_secret = get_option( 'indoor_tasks_paypal_client_secret', '' );

$stripe_enabled = get_option( 'indoor_tasks_stripe_enabled', 0 );
$stripe_mode = get_option( 'indoor_tasks_stripe_mode', 'test' );
$stripe_publishable_key = get_option( 'indoor_tasks_stripe_publishable_key', '' );
$stripe_secret_key = get_option( 'indoor_tasks_stripe_secret_key', '' );

$payment_currency = get_option( 'indoor_tasks_payment_currency', 'USD' );
$payment_success_page = get_option( 'indoor_tasks_payment_success_page', 0 );
$payment_cancel_page = get_option( 'indoor_tasks_payment_cancel_page', 0 );

// Get payment statistics
global $wpdb;
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_payments'" );
$total_payments = 0;
$successful_payments = 0;
$total_revenue = 0;
$failed_payments = 0;

if ( $table_exists ) {
    $total_payments = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_payments" );
    $successful_payments = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_payments WHERE status = 'completed'" );
    $total_revenue = $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}indoor_task_payments WHERE status = 'completed'" );
    $failed_payments = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_payments WHERE status = 'failed'" );
}

// Function to test payment gateway connection
function test_payment_gateway_connection( $gateway ) {
    switch ( $gateway ) {
        case 'paypal':
            // Test PayPal connection
            $client_id = get_option( 'indoor_tasks_paypal_client_id' );
            $mode = get_option( 'indoor_tasks_paypal_mode' );
            
            if ( empty( $client_id ) ) {
                return array( 'success' => false, 'message' => 'PayPal Client ID is not configured.' );
            }
            
            return array( 'success' => true, 'message' => 'PayPal configuration looks good! (Note: Full API test requires actual credentials)' );
            
        case 'stripe':
            // Test Stripe connection
            $publishable_key = get_option( 'indoor_tasks_stripe_publishable_key' );
            $mode = get_option( 'indoor_tasks_stripe_mode' );
            
            if ( empty( $publishable_key ) ) {
                return array( 'success' => false, 'message' => 'Stripe Publishable Key is not configured.' );
            }
            
            return array( 'success' => true, 'message' => 'Stripe configuration looks good! (Note: Full API test requires actual credentials)' );
            
        default:
            return array( 'success' => false, 'message' => 'Unknown payment gateway.' );
    }
}
?>

<div class="wrap">
    <h1><?php _e( 'Payment Gateway Settings', 'indoor-tasks' ); ?></h1>
    
    <!-- Statistics Cards -->
    <div class="indoor-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="indoor-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #666; font-size: 14px; font-weight: normal;">Total Payments</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #333;"><?php echo number_format( $total_payments ); ?></p>
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
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo number_format( $successful_payments ); ?></p>
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
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #1d4ed8;">$<?php echo number_format( $total_revenue, 2 ); ?></p>
                </div>
                <div style="width: 40px; height: 40px; background: #1d4ed8; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <span style="color: white; font-size: 18px;">💰</span>
                </div>
            </div>
        </div>
        
        <div class="indoor-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #666; font-size: 14px; font-weight: normal;">Failed Payments</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #dc2626;"><?php echo number_format( $failed_payments ); ?></p>
                </div>
                <div style="width: 40px; height: 40px; background: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <span style="color: white; font-size: 18px;">❌</span>
                </div>
            </div>
        </div>
    </div>
    
    <form method="post" action="">
        <?php wp_nonce_field( 'payment_gateway_action' ); ?>
        <input type="hidden" name="action" value="save_settings">
        
        <!-- PayPal Settings -->
        <div class="card" style="margin-bottom: 20px;">
            <h2 style="margin-top: 0; display: flex; align-items: center;">
                <img src="https://www.paypalobjects.com/webstatic/icon/pp258.png" alt="PayPal" style="width: 24px; height: 24px; margin-right: 10px;">
                PayPal Settings
                <form method="post" style="margin-left: auto;">
                    <?php wp_nonce_field( 'payment_gateway_action' ); ?>
                    <input type="hidden" name="action" value="test_connection">
                    <input type="hidden" name="gateway" value="paypal">
                    <input type="submit" class="button button-secondary button-small" value="Test Connection">
                </form>
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Enable PayPal</th>
                    <td>
                        <label>
                            <input type="checkbox" name="paypal_enabled" value="1" <?php checked( $paypal_enabled, 1 ); ?>>
                            Enable PayPal payments
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Mode</th>
                    <td>
                        <select name="paypal_mode">
                            <option value="sandbox" <?php selected( $paypal_mode, 'sandbox' ); ?>>Sandbox (Testing)</option>
                            <option value="live" <?php selected( $paypal_mode, 'live' ); ?>>Live (Production)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Client ID</th>
                    <td>
                        <input type="text" name="paypal_client_id" class="regular-text" value="<?php echo esc_attr( $paypal_client_id ); ?>" placeholder="Your PayPal Client ID">
                        <p class="description">Get this from your PayPal Developer Dashboard</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Client Secret</th>
                    <td>
                        <input type="password" name="paypal_client_secret" class="regular-text" value="<?php echo esc_attr( $paypal_client_secret ); ?>" placeholder="Your PayPal Client Secret">
                        <p class="description">Keep this secure and never share it publicly</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Stripe Settings -->
        <div class="card" style="margin-bottom: 20px;">
            <h2 style="margin-top: 0; display: flex; align-items: center;">
                <svg width="24" height="24" viewBox="0 0 24 24" style="margin-right: 10px;"><path fill="#635bff" d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/></svg>
                Stripe Settings
                <form method="post" style="margin-left: auto;">
                    <?php wp_nonce_field( 'payment_gateway_action' ); ?>
                    <input type="hidden" name="action" value="test_connection">
                    <input type="hidden" name="gateway" value="stripe">
                    <input type="submit" class="button button-secondary button-small" value="Test Connection">
                </form>
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Stripe</th>
                    <td>
                        <label>
                            <input type="checkbox" name="stripe_enabled" value="1" <?php checked( $stripe_enabled, 1 ); ?>>
                            Enable Stripe payments
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Mode</th>
                    <td>
                        <select name="stripe_mode">
                            <option value="test" <?php selected( $stripe_mode, 'test' ); ?>>Test Mode</option>
                            <option value="live" <?php selected( $stripe_mode, 'live' ); ?>>Live Mode</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Publishable Key</th>
                    <td>
                        <input type="text" name="stripe_publishable_key" class="regular-text" value="<?php echo esc_attr( $stripe_publishable_key ); ?>" placeholder="pk_test_... or pk_live_...">
                        <p class="description">This key is safe to share publicly</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Secret Key</th>
                    <td>
                        <input type="password" name="stripe_secret_key" class="regular-text" value="<?php echo esc_attr( $stripe_secret_key ); ?>" placeholder="sk_test_... or sk_live_...">
                        <p class="description">Keep this secure and never share it publicly</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- General Settings -->
        <div class="card">
            <h2 style="margin-top: 0;">General Payment Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Currency</th>
                    <td>
                        <select name="payment_currency">
                            <option value="USD" <?php selected( $payment_currency, 'USD' ); ?>>USD - US Dollar</option>
                            <option value="EUR" <?php selected( $payment_currency, 'EUR' ); ?>>EUR - Euro</option>
                            <option value="GBP" <?php selected( $payment_currency, 'GBP' ); ?>>GBP - British Pound</option>
                            <option value="CAD" <?php selected( $payment_currency, 'CAD' ); ?>>CAD - Canadian Dollar</option>
                            <option value="AUD" <?php selected( $payment_currency, 'AUD' ); ?>>AUD - Australian Dollar</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Success Page</th>
                    <td>
                        <?php wp_dropdown_pages( array(
                            'name' => 'payment_success_page',
                            'selected' => $payment_success_page,
                            'show_option_none' => 'Select a page...',
                            'option_none_value' => 0
                        ) ); ?>
                        <p class="description">Page to redirect users after successful payment</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Cancel Page</th>
                    <td>
                        <?php wp_dropdown_pages( array(
                            'name' => 'payment_cancel_page',
                            'selected' => $payment_cancel_page,
                            'show_option_none' => 'Select a page...',
                            'option_none_value' => 0
                        ) ); ?>
                        <p class="description">Page to redirect users when they cancel payment</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" class="button-primary" value="Save Payment Settings">
        </p>
    </form>
</div>

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
.button-small {
    font-size: 11px;
    height: 24px;
    line-height: 22px;
    padding: 0 8px;
}
</style>
