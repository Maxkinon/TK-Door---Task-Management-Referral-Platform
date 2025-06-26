<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Handle form submissions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) ) {
    if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'membership_plans_action' ) ) {
        wp_die( 'Security check failed' );
    }
    
    global $wpdb;
    
    if ( $_POST['action'] === 'add_plan' ) {
        $name = sanitize_text_field( $_POST['plan_name'] );
        $description = sanitize_textarea_field( $_POST['plan_description'] );
        $price = floatval( $_POST['plan_price'] );
        $duration = intval( $_POST['plan_duration'] );
        $benefits = sanitize_textarea_field( $_POST['plan_benefits'] );
        $max_tasks = intval( $_POST['max_tasks'] );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'indoor_task_membership_plans',
            array(
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'duration_days' => $duration,
                'benefits' => $benefits,
                'max_tasks_per_day' => $max_tasks,
                'status' => 'active',
                'created_at' => current_time( 'mysql' )
            )
        );
        
        if ( $result ) {
            echo '<div class="notice notice-success"><p>Membership plan added successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error adding membership plan.</p></div>';
        }
    }
    
    if ( $_POST['action'] === 'update_plan' && isset( $_POST['plan_id'] ) ) {
        $plan_id = intval( $_POST['plan_id'] );
        $status = sanitize_text_field( $_POST['plan_status'] );
        
        $result = $wpdb->update(
            $wpdb->prefix . 'indoor_task_membership_plans',
            array( 'status' => $status ),
            array( 'id' => $plan_id )
        );
        
        if ( $result !== false ) {
            echo '<div class="notice notice-success"><p>Plan status updated successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error updating plan status.</p></div>';
        }
    }
}

// Create table if it doesn't exist
global $wpdb;
$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}indoor_task_membership_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    duration_days INT NOT NULL DEFAULT 30,
    benefits TEXT,
    max_tasks_per_day INT DEFAULT 10,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) {$wpdb->get_charset_collate()};");

// Get membership plans
$plans = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}indoor_task_membership_plans ORDER BY created_at DESC" );

// Get plan statistics
$total_plans = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_membership_plans" );
$active_plans = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_membership_plans WHERE status = 'active'" );
$total_revenue = $wpdb->get_var( "SELECT COALESCE(SUM(price), 0) FROM {$wpdb->prefix}indoor_task_membership_plans WHERE status = 'active'" );

// Get user memberships if table exists
$user_memberships = 0;
$active_memberships = 0;
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_user_memberships'" );
if ( $table_exists ) {
    $user_memberships = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_memberships" );
    $active_memberships = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_memberships WHERE status = 'active' AND expires_at > NOW()" );
}
?>

<div class="wrap">
    <h1><?php _e( 'Membership Plans', 'indoor-tasks' ); ?></h1>
    
    <!-- Statistics Cards -->
    <div class="indoor-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="indoor-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #666; font-size: 14px; font-weight: normal;">Total Plans</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #333;"><?php echo number_format( $total_plans ); ?></p>
                </div>
                <div style="width: 40px; height: 40px; background: #2271b1; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <span style="color: white; font-size: 18px;">📋</span>
                </div>
            </div>
        </div>
        
        <div class="indoor-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #666; font-size: 14px; font-weight: normal;">Active Plans</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo number_format( $active_plans ); ?></p>
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
                    <h3 style="margin: 0; color: #666; font-size: 14px; font-weight: normal;">Active Memberships</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #7c3aed;"><?php echo number_format( $active_memberships ); ?></p>
                </div>
                <div style="width: 40px; height: 40px; background: #7c3aed; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <span style="color: white; font-size: 18px;">👥</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add New Plan Form -->
    <div class="card" style="margin-bottom: 20px;">
        <h2 style="margin-top: 0;">Add New Membership Plan</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'membership_plans_action' ); ?>
            <input type="hidden" name="action" value="add_plan">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="plan_name">Plan Name</label></th>
                    <td><input type="text" id="plan_name" name="plan_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="plan_description">Description</label></th>
                    <td><textarea id="plan_description" name="plan_description" rows="3" class="large-text"></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="plan_price">Price ($)</label></th>
                    <td><input type="number" id="plan_price" name="plan_price" step="0.01" min="0" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="plan_duration">Duration (Days)</label></th>
                    <td><input type="number" id="plan_duration" name="plan_duration" min="1" class="regular-text" required value="30"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_tasks">Max Tasks Per Day</label></th>
                    <td><input type="number" id="max_tasks" name="max_tasks" min="1" class="regular-text" required value="10"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="plan_benefits">Benefits</label></th>
                    <td><textarea id="plan_benefits" name="plan_benefits" rows="4" class="large-text" placeholder="One benefit per line"></textarea></td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button-primary" value="Add Plan">
            </p>
        </form>
    </div>
    
    <!-- Plans List -->
    <div class="card">
        <h2 style="margin-top: 0;">Existing Plans</h2>
        
        <?php if ( empty( $plans ) ): ?>
            <p>No membership plans found. Add your first plan above.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Max Tasks/Day</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $plans as $plan ): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $plan->name ); ?></strong>
                                <?php if ( $plan->description ): ?>
                                    <br><small style="color: #666;"><?php echo esc_html( substr( $plan->description, 0, 80 ) ); ?><?php echo strlen( $plan->description ) > 80 ? '...' : ''; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>$<?php echo number_format( $plan->price, 2 ); ?></td>
                            <td><?php echo $plan->duration_days; ?> days</td>
                            <td><?php echo $plan->max_tasks_per_day; ?></td>
                            <td>
                                <span class="<?php echo $plan->status === 'active' ? 'indoor-status-active' : 'indoor-status-inactive'; ?>" style="
                                    padding: 4px 8px;
                                    border-radius: 4px;
                                    font-size: 12px;
                                    font-weight: bold;
                                    <?php echo $plan->status === 'active' ? 'background: #d1fae5; color: #065f46;' : 'background: #fee2e2; color: #991b1b;'; ?>
                                ">
                                    <?php echo ucfirst( $plan->status ); ?>
                                </span>
                            </td>
                            <td><?php echo date( 'M j, Y', strtotime( $plan->created_at ) ); ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field( 'membership_plans_action' ); ?>
                                    <input type="hidden" name="action" value="update_plan">
                                    <input type="hidden" name="plan_id" value="<?php echo $plan->id; ?>">
                                    <select name="plan_status" onchange="this.form.submit()">
                                        <option value="active" <?php selected( $plan->status, 'active' ); ?>>Active</option>
                                        <option value="inactive" <?php selected( $plan->status, 'inactive' ); ?>>Inactive</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php if ( $plan->benefits ): ?>
                            <tr>
                                <td colspan="7" style="padding-left: 40px; color: #666; font-size: 12px;">
                                    <strong>Benefits:</strong> <?php echo nl2br( esc_html( $plan->benefits ) ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
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
</style>
