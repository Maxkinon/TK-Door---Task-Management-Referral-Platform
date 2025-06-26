<?php
/**
 * Database Compatibility Helper Functions
 * 
 * This file contains helper functions to handle database compatibility issues
 * and provide safe fallbacks for missing tables or columns.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if a database table exists
 * 
 * @param string $table_name Table name (with prefix)
 * @return bool True if table exists
 */
function indoor_tasks_table_exists($table_name) {
    global $wpdb;
    return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
}

/**
 * Check if a column exists in a table
 * This function is defined with function_exists check to prevent redeclaration errors
 * since it may also be defined in database-setup.php and tk-indoor-tasks.php
 * 
 * @param string $table_name Table name (with prefix)
 * @param string $column_name Column name
 * @return bool True if column exists
 */
if (!function_exists('indoor_tasks_column_exists')) {
    function indoor_tasks_column_exists($table_name, $column_name) {
        global $wpdb;
        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, $table_name, $column_name
        ));
        return !empty($columns);
    }
}

/**
 * Safe referral count query - handles different table structures
 * 
 * @param int $user_id User ID to count referrals for
 * @return int Number of referrals
 */
function indoor_tasks_get_safe_referral_count($user_id) {
    global $wpdb;
    
    // Check if referrals table exists
    if (indoor_tasks_table_exists($wpdb->prefix . 'indoor_task_referrals')) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_referrals WHERE referrer_id = %d AND status = 'approved'",
            $user_id
        )) ?: 0;
    }
    
    // Check if users table has refer_user column
    if (indoor_tasks_column_exists($wpdb->users, 'refer_user')) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE refer_user = %d",
            $user_id
        )) ?: 0;
    }
    
    // Fallback to user meta
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'indoor_tasks_referred_by' AND meta_value = %d",
        $user_id
    )) ?: 0;
}

/**
 * Safe user level query - handles different level storage methods
 * 
 * @param int $user_id User ID
 * @return string Level name
 */
function indoor_tasks_get_safe_user_level($user_id) {
    // Use the Indoor_Tasks_Levels class if available
    if (class_exists('Indoor_Tasks_Levels')) {
        return Indoor_Tasks_Levels::get_user_level($user_id);
    }
    
    global $wpdb;
    
    // Check if user_levels table exists
    if (indoor_tasks_table_exists($wpdb->prefix . 'indoor_task_user_levels')) {
        $level_id = get_user_meta($user_id, 'indoor_tasks_user_level', true);
        if ($level_id) {
            $level_name = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}indoor_task_user_levels WHERE id = %d",
                $level_id
            ));
            if ($level_name) {
                return $level_name;
            }
        }
    }
    
    // Fallback calculation based on completed tasks
    $completed_tasks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
         WHERE user_id = %d AND status = 'approved'",
        $user_id
    )) ?: 0;
    
    $level_number = max(1, floor($completed_tasks / 10) + 1);
    return "Level " . $level_number;
}

/**
 * Safe KYC status query - handles different KYC storage methods
 * 
 * @param int $user_id User ID
 * @return string KYC status
 */
function indoor_tasks_get_safe_kyc_status($user_id) {
    // Check user meta first
    $kyc_meta = get_user_meta($user_id, 'indoor_tasks_kyc_status', true);
    if (!empty($kyc_meta)) {
        return $kyc_meta;
    }
    
    global $wpdb;
    
    // Check KYC table if it exists
    if (indoor_tasks_table_exists($wpdb->prefix . 'indoor_task_kyc')) {
        $kyc_record = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}indoor_task_kyc WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $user_id
        ));
        if ($kyc_record) {
            return $kyc_record->status;
        }
    }
    
    return 'pending';
}

/**
 * Safe wallet points query
 * 
 * @param int $user_id User ID
 * @return int Total wallet points
 */
function indoor_tasks_get_safe_wallet_points($user_id) {
    global $wpdb;
    
    if (indoor_tasks_table_exists($wpdb->prefix . 'indoor_task_wallet')) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
            $user_id
        )) ?: 0;
    }
    
    // Fallback to user meta
    return get_user_meta($user_id, 'indoor_tasks_wallet_balance', true) ?: 0;
}

/**
 * Safe task completion count query
 * 
 * @param int $user_id User ID
 * @return int Number of completed tasks
 */
function indoor_tasks_get_safe_completed_tasks($user_id) {
    global $wpdb;
    
    if (indoor_tasks_table_exists($wpdb->prefix . 'indoor_task_submissions')) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
             WHERE user_id = %d AND status = 'approved'",
            $user_id
        )) ?: 0;
    }
    
    // Fallback to user meta
    return get_user_meta($user_id, 'indoor_tasks_completed_tasks', true) ?: 0;
}

/**
 * Safe pending task count query
 * 
 * @param int $user_id User ID
 * @return int Number of pending tasks
 */
function indoor_tasks_get_safe_pending_tasks($user_id) {
    global $wpdb;
    
    if (indoor_tasks_table_exists($wpdb->prefix . 'indoor_task_submissions')) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
             WHERE user_id = %d AND status = 'pending'",
            $user_id
        )) ?: 0;
    }
    
    // Fallback to user meta
    return get_user_meta($user_id, 'indoor_tasks_pending_tasks', true) ?: 0;
}

/**
 * Initialize database compatibility - creates missing tables/columns
 */
function indoor_tasks_init_database_compatibility() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create referrals table if it doesn't exist
    if (!indoor_tasks_table_exists($wpdb->prefix . 'indoor_task_referrals')) {
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}indoor_task_referrals (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            referrer_id BIGINT UNSIGNED NOT NULL,
            referred_id BIGINT UNSIGNED NOT NULL,
            status ENUM('pending','approved','rejected') DEFAULT 'approved',
            commission_earned INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (referrer_id),
            INDEX (referred_id)
        ) $charset_collate;");
        
        // Migrate existing referrals from user meta
        $referrals = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} 
             WHERE meta_key = 'indoor_tasks_referred_by'"
        );
        
        foreach ($referrals as $referral) {
            $wpdb->insert(
                $wpdb->prefix . 'indoor_task_referrals',
                [
                    'referrer_id' => $referral->meta_value,
                    'referred_id' => $referral->user_id,
                    'status' => 'approved',
                    'created_at' => current_time('mysql')
                ]
            );
        }
    }
    
    // Add refer_user column to users table if it doesn't exist
    if (!indoor_tasks_column_exists($wpdb->users, 'refer_user')) {
        $wpdb->query("ALTER TABLE {$wpdb->users} ADD COLUMN refer_user BIGINT UNSIGNED DEFAULT NULL");
        
        // Migrate referral data to the new column
        $referrals = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} 
             WHERE meta_key = 'indoor_tasks_referred_by'"
        );
        
        foreach ($referrals as $referral) {
            $wpdb->update(
                $wpdb->users,
                ['refer_user' => $referral->meta_value],
                ['ID' => $referral->user_id]
            );
        }
    }
}

// Initialize compatibility on plugin load
add_action('init', 'indoor_tasks_init_database_compatibility');
