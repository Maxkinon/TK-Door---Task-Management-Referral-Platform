<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Handle health check actions
if ( isset( $_POST['action'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'health_check_action' ) ) {
    switch ( $_POST['action'] ) {
        case 'fix_tables':
            $fix_result = fix_database_tables();
            if ( $fix_result['success'] ) {
                echo '<div class="notice notice-success"><p>' . esc_html( $fix_result['message'] ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html( $fix_result['message'] ) . '</p></div>';
            }
            break;
            
        case 'clear_cache':
            wp_cache_flush();
            if ( function_exists( 'wp_cache_clear_cache' ) ) {
                wp_cache_clear_cache();
            }
            echo '<div class="notice notice-success"><p>Cache cleared successfully!</p></div>';
            break;
            
        case 'reset_counters':
            $reset_result = reset_plugin_counters();
            if ( $reset_result['success'] ) {
                echo '<div class="notice notice-success"><p>' . esc_html( $reset_result['message'] ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html( $reset_result['message'] ) . '</p></div>';
            }
            break;
    }
}

// Perform health checks
$health_checks = perform_health_checks();

// Database health check functions
function perform_health_checks() {
    global $wpdb;
    
    $checks = array();
    
    // 1. Check database tables
    $required_tables = array(
        'indoor_tasks',
        'indoor_task_submissions',
        'indoor_task_wallet',
        'indoor_task_withwithdrawals',
        'indoor_task_kyc',
        'indoor_task_notifications',
        'indoor_task_announcements'
    );
    
    $missing_tables = array();
    $existing_tables = array();
    
    foreach ( $required_tables as $table ) {
        $full_table_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '$full_table_name'" ) === $full_table_name;
        
        if ( $exists ) {
            $existing_tables[] = $table;
        } else {
            $missing_tables[] = $table;
        }
    }
    
    $checks['database'] = array(
        'title' => 'Database Tables',
        'status' => empty( $missing_tables ) ? 'success' : 'warning',
        'message' => empty( $missing_tables ) 
            ? 'All required tables exist (' . count( $existing_tables ) . '/' . count( $required_tables ) . ')'
            : 'Missing tables: ' . implode( ', ', $missing_tables ),
        'details' => array(
            'existing' => $existing_tables,
            'missing' => $missing_tables
        )
    );
    
    // 2. Check file permissions
    $critical_dirs = array(
        INDOOR_TASKS_PATH . 'admin/',
        INDOOR_TASKS_PATH . 'assets/',
        INDOOR_TASKS_PATH . 'includes/',
        wp_upload_dir()['basedir'] . '/indoor-tasks/'
    );
    
    $permission_issues = array();
    $good_permissions = array();
    
    foreach ( $critical_dirs as $dir ) {
        if ( file_exists( $dir ) ) {
            if ( is_writable( $dir ) ) {
                $good_permissions[] = basename( $dir );
            } else {
                $permission_issues[] = basename( $dir );
            }
        }
    }
    
    $checks['permissions'] = array(
        'title' => 'File Permissions',
        'status' => empty( $permission_issues ) ? 'success' : 'error',
        'message' => empty( $permission_issues )
            ? 'All critical directories are writable'
            : 'Permission issues in: ' . implode( ', ', $permission_issues ),
        'details' => array(
            'writable' => $good_permissions,
            'issues' => $permission_issues
        )
    );
    
    // 3. Check WordPress requirements
    $wp_version = get_bloginfo( 'version' );
    $php_version = PHP_VERSION;
    $mysql_version = $wpdb->get_var( 'SELECT VERSION()' );
    
    $wp_ok = version_compare( $wp_version, '5.0', '>=' );
    $php_ok = version_compare( $php_version, '7.4', '>=' );
    $mysql_ok = version_compare( $mysql_version, '5.6', '>=' );
    
    $requirements_ok = $wp_ok && $php_ok && $mysql_ok;
    
    $checks['requirements'] = array(
        'title' => 'System Requirements',
        'status' => $requirements_ok ? 'success' : 'warning',
        'message' => $requirements_ok ? 'All requirements met' : 'Some requirements not optimal',
        'details' => array(
            'wordpress' => array( 'version' => $wp_version, 'ok' => $wp_ok, 'min' => '5.0' ),
            'php' => array( 'version' => $php_version, 'ok' => $php_ok, 'min' => '7.4' ),
            'mysql' => array( 'version' => $mysql_version, 'ok' => $mysql_ok, 'min' => '5.6' )
        )
    );
    
    // 4. Check plugin data integrity
    $data_issues = array();
    $data_stats = array();
    
    // Check for orphaned submissions
    $orphaned_submissions = $wpdb->get_var( "
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}indoor_task_submissions s 
        LEFT JOIN {$wpdb->prefix}indoor_tasks t ON s.task_id = t.id 
        WHERE t.id IS NULL
    " );
    
    if ( $orphaned_submissions > 0 ) {
        $data_issues[] = "$orphaned_submissions orphaned task submissions";
    }
    $data_stats['submissions'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions" );
    
    // Check for orphaned wallet entries
    $orphaned_wallet = $wpdb->get_var( "
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}indoor_task_wallet w 
        LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID 
        WHERE u.ID IS NULL
    " );
    
    if ( $orphaned_wallet > 0 ) {
        $data_issues[] = "$orphaned_wallet orphaned wallet entries";
    }
    $data_stats['wallet_entries'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_wallet" );
    
    // Check wallet balance consistency
    $negative_balances = $wpdb->get_var( "
        SELECT COUNT(DISTINCT user_id) 
        FROM {$wpdb->prefix}indoor_task_wallet 
        GROUP BY user_id 
        HAVING SUM(points) < 0
    " );
    
    if ( $negative_balances > 0 ) {
        $data_issues[] = "$negative_balances users with negative wallet balance";
    }
    
    $checks['data_integrity'] = array(
        'title' => 'Data Integrity',
        'status' => empty( $data_issues ) ? 'success' : 'warning',
        'message' => empty( $data_issues ) ? 'No data integrity issues found' : implode( ', ', $data_issues ),
        'details' => array(
            'issues' => $data_issues,
            'stats' => $data_stats
        )
    );
    
    // 5. Check plugin performance
    $performance_issues = array();
    $performance_stats = array();
    
    // Check for large tables
    $table_sizes = $wpdb->get_results( "
        SELECT 
            table_name as name,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
            table_rows as rows
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name LIKE '{$wpdb->prefix}indoor_task%'
        ORDER BY (data_length + index_length) DESC
    " );
    
    foreach ( $table_sizes as $table ) {
        $performance_stats['tables'][] = array(
            'name' => str_replace( $wpdb->prefix, '', $table->name ),
            'size' => $table->size_mb . ' MB',
            'rows' => $table->rows
        );
        
        if ( $table->size_mb > 100 ) {
            $performance_issues[] = $table->name . ' is large (' . $table->size_mb . ' MB)';
        }
    }
    
    // Check memory usage
    $memory_limit = ini_get( 'memory_limit' );
    $memory_usage = memory_get_usage( true );
    $memory_peak = memory_get_peak_usage( true );
    
    $performance_stats['memory'] = array(
        'limit' => $memory_limit,
        'current' => round( $memory_usage / 1024 / 1024, 2 ) . ' MB',
        'peak' => round( $memory_peak / 1024 / 1024, 2 ) . ' MB'
    );
    
    $checks['performance'] = array(
        'title' => 'Performance',
        'status' => empty( $performance_issues ) ? 'success' : 'info',
        'message' => empty( $performance_issues ) ? 'No performance issues detected' : implode( ', ', $performance_issues ),
        'details' => $performance_stats
    );
    
    return $checks;
}

function fix_database_tables() {
    global $wpdb;
    
    $fixed = 0;
    $errors = array();
    
    // Recreate missing tables
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $tables_sql = array(
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}indoor_tasks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            reward_points INT NOT NULL,
            deadline DATETIME,
            max_users INT DEFAULT 0,
            created_by BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;",
        
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}indoor_task_submissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            task_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            proof_text TEXT,
            proof_file VARCHAR(255),
            status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
            admin_reason TEXT,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME
        ) $charset_collate;",
        
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}indoor_task_wallet (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            points INT NOT NULL,
            type ENUM('reward','bonus','withdrawal','admin','referral') DEFAULT 'reward',
            reference_id BIGINT UNSIGNED,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;"
    );
    
    foreach ( $tables_sql as $sql ) {
        $result = $wpdb->query( $sql );
        if ( $result !== false ) {
            $fixed++;
        } else {
            $errors[] = $wpdb->last_error;
        }
    }
    
    // Clean up orphaned data
    $wpdb->query( "DELETE s FROM {$wpdb->prefix}indoor_task_submissions s LEFT JOIN {$wpdb->prefix}indoor_tasks t ON s.task_id = t.id WHERE t.id IS NULL" );
    $wpdb->query( "DELETE w FROM {$wpdb->prefix}indoor_task_wallet w LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID WHERE u.ID IS NULL" );
    
    if ( empty( $errors ) ) {
        return array( 'success' => true, 'message' => "Database fixes applied successfully. $fixed tables processed." );
    } else {
        return array( 'success' => false, 'message' => 'Some errors occurred: ' . implode( ', ', $errors ) );
    }
}

function reset_plugin_counters() {
    global $wpdb;
    
    try {
        // Reset user meta counters
        $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'indoor_task_%_count'" );
        
        // Recalculate wallet balances
        $users = $wpdb->get_results( "SELECT DISTINCT user_id FROM {$wpdb->prefix}indoor_task_wallet" );
        
        foreach ( $users as $user ) {
            $balance = $wpdb->get_var( $wpdb->prepare( 
                "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d", 
                $user->user_id 
            ) );
            
            update_user_meta( $user->user_id, 'indoor_task_wallet_balance', $balance );
        }
        
        return array( 'success' => true, 'message' => 'Plugin counters reset successfully.' );
    } catch ( Exception $e ) {
        return array( 'success' => false, 'message' => 'Error resetting counters: ' . $e->getMessage() );
    }
}

// Check critical files
$critical_files = [
    'admin/manage-users.php',
    'admin/clients.php',
    'templates/tkm-door-task-archive.php'
];

$missing_files = [];
foreach ($critical_files as $file) {
    if (!file_exists(INDOOR_TASKS_PATH . $file)) {
        $missing_files[] = $file;
    }
}

if (!empty($missing_files)) {
    echo '<div class="notice notice-error"><p>Missing files: ' . implode(', ', $missing_files) . '</p></div>';
} else {
    echo '<div class="notice notice-success"><p>Indoor Tasks plugin health check passed!</p></div>';
}
?>

<div class="wrap">
    <h1><?php _e( 'Health Check', 'indoor-tasks' ); ?></h1>
    <p>This page helps you monitor the health and performance of the Indoor Tasks plugin.</p>
    
    <!-- Overall Health Status -->
    <div class="card" style="margin-bottom: 20px;">
        <h2 style="margin-top: 0; display: flex; align-items: center;">
            <?php
            $overall_status = 'success';
            $critical_issues = 0;
            
            foreach ( $health_checks as $check ) {
                if ( $check['status'] === 'error' ) {
                    $overall_status = 'error';
                    $critical_issues++;
                } elseif ( $check['status'] === 'warning' && $overall_status !== 'error' ) {
                    $overall_status = 'warning';
                }
            }
            
            switch ( $overall_status ) {
                case 'success':
                    echo '<span style="color: #00a32a; margin-right: 10px;">✅</span> Overall Health: Good';
                    break;
                case 'warning':
                    echo '<span style="color: #dba617; margin-right: 10px;">⚠️</span> Overall Health: Needs Attention';
                    break;
                case 'error':
                    echo '<span style="color: #dc2626; margin-right: 10px;">❌</span> Overall Health: Critical Issues';
                    break;
            }
            ?>
        </h2>
        
        <?php if ( $overall_status !== 'success' ): ?>
            <p>We found some issues that may need your attention. Please review the checks below.</p>
        <?php else: ?>
            <p>All health checks passed successfully! Your Indoor Tasks plugin is running smoothly.</p>
        <?php endif; ?>
    </div>
    
    <!-- Health Check Results -->
    <div class="indoor-health-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <?php foreach ( $health_checks as $check_id => $check ): ?>
            <div class="health-check-card" style="
                background: #fff; 
                border: 1px solid #ddd; 
                border-left: 4px solid <?php 
                    switch ( $check['status'] ) {
                        case 'success': echo '#00a32a'; break;
                        case 'warning': echo '#dba617'; break;
                        case 'error': echo '#dc2626'; break;
                        case 'info': echo '#2271b1'; break;
                        default: echo '#ddd';
                    }
                ?>; 
                border-radius: 4px; 
                padding: 20px; 
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            ">
                <h3 style="margin: 0 0 10px 0; display: flex; align-items: center;">
                    <span style="margin-right: 10px;">
                        <?php
                        switch ( $check['status'] ) {
                            case 'success': echo '✅'; break;
                            case 'warning': echo '⚠️'; break;
                            case 'error': echo '❌'; break;
                            case 'info': echo 'ℹ️'; break;
                        }
                        ?>
                    </span>
                    <?php echo esc_html( $check['title'] ); ?>
                </h3>
                
                <p style="margin: 0 0 15px 0; color: #666;">
                    <?php echo esc_html( $check['message'] ); ?>
                </p>
                
                <?php if ( ! empty( $check['details'] ) ): ?>
                    <details style="margin-top: 15px;">
                        <summary style="cursor: pointer; font-weight: bold; color: #2271b1;">View Details</summary>
                        <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                            <?php if ( $check_id === 'database' ): ?>
                                <p><strong>Existing Tables:</strong> <?php echo implode( ', ', $check['details']['existing'] ); ?></p>
                                <?php if ( ! empty( $check['details']['missing'] ) ): ?>
                                    <p><strong>Missing Tables:</strong> <?php echo implode( ', ', $check['details']['missing'] ); ?></p>
                                <?php endif; ?>
                                
                            <?php elseif ( $check_id === 'requirements' ): ?>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <?php foreach ( $check['details'] as $req => $info ): ?>
                                        <li>
                                            <strong><?php echo ucfirst( $req ); ?>:</strong> 
                                            <?php echo $info['version']; ?>
                                            <?php echo $info['ok'] ? '✅' : '❌ (min: ' . $info['min'] . ')'; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                
                            <?php elseif ( $check_id === 'performance' && ! empty( $check['details']['tables'] ) ): ?>
                                <p><strong>Table Sizes:</strong></p>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <?php foreach ( $check['details']['tables'] as $table ): ?>
                                        <li><?php echo $table['name']; ?>: <?php echo $table['size']; ?> (<?php echo number_format( $table['rows'] ); ?> rows)</li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <?php if ( ! empty( $check['details']['memory'] ) ): ?>
                                    <p><strong>Memory:</strong> <?php echo $check['details']['memory']['current']; ?> / <?php echo $check['details']['memory']['limit']; ?> (Peak: <?php echo $check['details']['memory']['peak']; ?>)</p>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <pre style="white-space: pre-wrap; font-size: 11px;"><?php echo esc_html( json_encode( $check['details'], JSON_PRETTY_PRINT ) ); ?></pre>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <h2 style="margin-top: 0;">Quick Actions</h2>
        <p>Use these tools to fix common issues or optimize your plugin performance.</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field( 'health_check_action' ); ?>
                <input type="hidden" name="action" value="fix_tables">
                <button type="submit" class="button button-primary" style="width: 100%; padding: 10px;">
                    🔧 Fix Database Tables
                </button>
                <p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Recreate missing tables and clean orphaned data</p>
            </form>
            
            <form method="post" style="display: inline;">
                <?php wp_nonce_field( 'health_check_action' ); ?>
                <input type="hidden" name="action" value="clear_cache">
                <button type="submit" class="button" style="width: 100%; padding: 10px;">
                    🗑️ Clear Cache
                </button>
                <p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Clear WordPress and plugin caches</p>
            </form>
            
            <form method="post" style="display: inline;">
                <?php wp_nonce_field( 'health_check_action' ); ?>
                <input type="hidden" name="action" value="reset_counters">
                <button type="submit" class="button" style="width: 100%; padding: 10px;">
                    🔄 Reset Counters
                </button>
                <p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Recalculate user balances and stats</p>
            </form>
            
            <a href="<?php echo admin_url( 'admin.php?page=indoor-tasks-health-check&debug=1' ); ?>" class="button" style="width: 100%; padding: 10px; text-align: center; text-decoration: none;">
                🐛 Debug Info
            </a>
            <p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">View detailed debug information</p>
        </div>
    </div>
    
    <?php if ( isset( $_GET['debug'] ) ): ?>
        <!-- Debug Information -->
        <div class="card">
            <h2 style="margin-top: 0;">Debug Information</h2>
            <textarea readonly style="width: 100%; height: 300px; font-family: monospace; font-size: 12px;"><?php
                echo "Indoor Tasks Plugin Debug Info\n";
                echo "================================\n\n";
                echo "Plugin Version: " . INDOOR_TASKS_VERSION . "\n";
                echo "WordPress Version: " . get_bloginfo( 'version' ) . "\n";
                echo "PHP Version: " . PHP_VERSION . "\n";
                echo "MySQL Version: " . $wpdb->get_var( 'SELECT VERSION()' ) . "\n";
                echo "Site URL: " . home_url() . "\n";
                echo "Admin URL: " . admin_url() . "\n";
                echo "Plugin Path: " . INDOOR_TASKS_PATH . "\n";
                echo "Plugin URL: " . INDOOR_TASKS_URL . "\n\n";
                
                echo "Health Check Results:\n";
                foreach ( $health_checks as $check_id => $check ) {
                    echo "- " . $check['title'] . ": " . $check['status'] . " - " . $check['message'] . "\n";
                }
                
                echo "\nDatabase Tables:\n";
                $tables = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}indoor_task%'" );
                foreach ( $tables as $table ) {
                    $table_name = array_values( (array) $table )[0];
                    $count = $wpdb->get_var( "SELECT COUNT(*) FROM `$table_name`" );
                    echo "- $table_name: $count rows\n";
                }
                
                echo "\nActive Plugins:\n";
                $active_plugins = get_option( 'active_plugins' );
                foreach ( $active_plugins as $plugin ) {
                    echo "- $plugin\n";
                }
            ?></textarea>
        </div>
    <?php endif; ?>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.health-check-card:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease;
}
details summary {
    outline: none;
}
details[open] summary {
    margin-bottom: 10px;
}
</style>
