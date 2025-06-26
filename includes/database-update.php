<?php
/**
 * Database update script for the Indoor Tasks plugin
 * 
 * This file should be included in the main plugin file to update database tables
 * and fix any missing columns or tables.
 */

// Add missing columns to the indoor_tasks table
function indoor_tasks_update_task_table_columns() {
    global $wpdb;
    
    // Check for missing columns in the tasks table
    $columns_to_add = [
        'category' => "VARCHAR(100) DEFAULT 'General'",
        'how_to' => "TEXT",
        'task_link' => "VARCHAR(255)",
        'guide_link' => "VARCHAR(255)",
        'duration' => "INT DEFAULT 10",
        'special_message' => "TEXT",
        'task_image' => "VARCHAR(255)",
        'difficulty_level' => "VARCHAR(50) DEFAULT 'medium'",
        'featured' => "TINYINT(1) DEFAULT 0",
        'priority' => "INT DEFAULT 0",
        'target_countries' => "TEXT",
        'step_by_step_guide' => "LONGTEXT",
        'video_link' => "VARCHAR(500)",
        'budget' => "DECIMAL(10,2) DEFAULT 0.00"
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, $wpdb->prefix . 'indoor_tasks', $column
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}indoor_tasks ADD COLUMN {$column} {$definition}");
        }
    }
}

function indoor_tasks_update_database() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Update task table columns first
    indoor_tasks_update_task_table_columns();
    
    // Log database update for debugging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Indoor Tasks: Database update function executed');
        
        // Check if category column exists now
        $category_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'category'",
            DB_NAME, $wpdb->prefix . 'indoor_tasks'
        ));
        
        if (!empty($category_exists)) {
            error_log('Indoor Tasks: Category column exists in tasks table');
        } else {
            error_log('Indoor Tasks: Category column does NOT exist in tasks table');
        }
    }

    // Create task categories table if it doesn't exist
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}indoor_task_categories (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;");
    
    // Add difficulty_level to tasks table if it doesn't exist
    $column = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'difficulty_level'",
        DB_NAME, $wpdb->prefix . 'indoor_tasks'
    ));
    if (empty($column)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}indoor_tasks ADD COLUMN difficulty_level VARCHAR(50) DEFAULT 'medium'");
    }
    
    // Add featured column to tasks table if it doesn't exist
    $column = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'featured'",
        DB_NAME, $wpdb->prefix . 'indoor_tasks'
    ));
    if (empty($column)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}indoor_tasks ADD COLUMN featured TINYINT(1) DEFAULT 0");
    }
    
    // Add reference_id to notifications table if it doesn't exist
    $column = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'reference_id'",
        DB_NAME, $wpdb->prefix . 'indoor_task_notifications'
    ));
    if (empty($column)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}indoor_task_notifications ADD COLUMN reference_id BIGINT UNSIGNED DEFAULT 0");
    }
    
    // Add user_activities table if it doesn't exist
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}indoor_task_user_activities (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        activity_type VARCHAR(50) NOT NULL,
        description TEXT,
        metadata TEXT,
        ip_address VARCHAR(50),
        device VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;");
    
    // Add user_levels table if it doesn't exist
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}indoor_task_user_levels (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(255),
        min_tasks INT DEFAULT 0,
        min_referrals INT DEFAULT 0,
        max_daily_tasks INT DEFAULT 10,
        reward_multiplier FLOAT DEFAULT 1.0,
        withdrawal_time INT DEFAULT 24,
        badge_color VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;");
    
    // Create referrals table if it doesn't exist
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}indoor_task_referrals (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        referrer_id BIGINT UNSIGNED NOT NULL,
        referred_id BIGINT UNSIGNED NOT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (referrer_id),
        INDEX (referred_id)
    ) $charset_collate;");
    
    // Check if there are any categories and add default ones if empty
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_categories");
    if ($count == 0) {
        $default_categories = [
            ['name' => 'Social Media', 'description' => 'Tasks related to social media platforms'],
            ['name' => 'Reviews', 'description' => 'Product or service review tasks'],
            ['name' => 'Surveys', 'description' => 'Survey completion tasks'],
            ['name' => 'App Testing', 'description' => 'Mobile application testing tasks'],
            ['name' => 'Content Creation', 'description' => 'Content writing or creation tasks']
        ];
        
        foreach ($default_categories as $category) {
            $wpdb->insert(
                $wpdb->prefix . 'indoor_task_categories',
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'created_at' => current_time('mysql')
                ]
            );
        }
    }
    
    // Check if there are any user levels and add default ones if empty
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_levels");
    if ($count == 0) {
        $default_levels = [
            [
                'name' => 'Bronze',
                'description' => 'Starting level for all users',
                'min_tasks' => 0,
                'min_referrals' => 0,
                'max_daily_tasks' => 5,
                'reward_multiplier' => 1.0,
                'withdrawal_time' => 48,
                'badge_color' => '#cd7f32'
            ],
            [
                'name' => 'Silver',
                'description' => 'Intermediate level with better benefits',
                'min_tasks' => 10,
                'min_referrals' => 3,
                'max_daily_tasks' => 10,
                'reward_multiplier' => 1.2,
                'withdrawal_time' => 24,
                'badge_color' => '#c0c0c0'
            ],
            [
                'name' => 'Gold',
                'description' => 'Advanced level with premium benefits',
                'min_tasks' => 30,
                'min_referrals' => 10,
                'max_daily_tasks' => 20,
                'reward_multiplier' => 1.5,
                'withdrawal_time' => 12,
                'badge_color' => '#ffd700'
            ]
        ];
        
        foreach ($default_levels as $level) {
            $wpdb->insert(
                $wpdb->prefix . 'indoor_task_user_levels',
                [
                    'name' => $level['name'],
                    'description' => $level['description'],
                    'min_tasks' => $level['min_tasks'],
                    'min_referrals' => $level['min_referrals'],
                    'max_daily_tasks' => $level['max_daily_tasks'],
                    'reward_multiplier' => $level['reward_multiplier'],
                    'withdrawal_time' => $level['withdrawal_time'],
                    'badge_color' => $level['badge_color'],
                    'created_at' => current_time('mysql')
                ]
            );
        }
    }
    
    // Check and update withdrawal methods table
    $withdrawal_methods_table = $wpdb->prefix . 'indoor_task_withdrawal_methods';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$withdrawal_methods_table}'") === $withdrawal_methods_table;
    
    if (!$table_exists) {
        $sql = "CREATE TABLE {$withdrawal_methods_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            method VARCHAR(100) NOT NULL,
            conversion_rate FLOAT NOT NULL,
            min_points INT NOT NULL,
            custom_fields TEXT,
            enabled TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            sort_order INT DEFAULT 0,
            icon_url VARCHAR(255),
            description TEXT,
            payout_label VARCHAR(100),
            currency_symbol VARCHAR(10),
            max_points INT DEFAULT 0,
            processing_time VARCHAR(100),
            manual_approval TINYINT(1) DEFAULT 1,
            fee VARCHAR(50)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Migrate existing withdrawal methods from options
        $old_methods = get_option('indoor_tasks_withdrawal_methods', []);
        if (!empty($old_methods) && is_array($old_methods)) {
            foreach ($old_methods as $index => $method) {
                if (!empty($method['name'])) {
                    $input_fields = !empty($method['input_fields']) ? json_encode($method['input_fields']) : '';
                    
                    $wpdb->insert(
                        $withdrawal_methods_table,
                        [
                            'method' => $method['name'],
                            'conversion_rate' => floatval($method['conversion']),
                            'min_points' => intval($method['min_points'] ?? 0),
                            'custom_fields' => $input_fields,
                            'enabled' => 1,
                            'sort_order' => $index,
                            'icon_url' => $method['icon'] ?? '',
                            'description' => $method['description'] ?? '',
                            'payout_label' => $method['payout_label'] ?? '',
                            'currency_symbol' => $method['currency_symbol'] ?? '',
                            'max_points' => intval($method['max_points'] ?? 0),
                            'processing_time' => $method['processing_time'] ?? '',
                            'manual_approval' => isset($method['manual_approval']) ? 1 : 0,
                            'fee' => $method['fee'] ?? ''
                        ]
                    );
                }
            }
        }
    } else {
        // Check for new columns and add them if they don't exist
        $columns_to_add = [
            'sort_order' => 'INT DEFAULT 0',
            'icon_url' => 'VARCHAR(255)',
            'description' => 'TEXT',
            'payout_label' => 'VARCHAR(100)',
            'currency_symbol' => 'VARCHAR(10)',
            'max_points' => 'INT DEFAULT 0',
            'processing_time' => 'VARCHAR(100)',
            'manual_approval' => 'TINYINT(1) DEFAULT 1',
            'fee' => 'VARCHAR(50)'
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, $withdrawal_methods_table, $column
            ));
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$withdrawal_methods_table} ADD COLUMN {$column} {$definition}");
            }
        }
    }
    
    // Update submissions table to add submitted_at if not exist and remove created_at if exists
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'submitted_at'",
        DB_NAME, $wpdb->prefix . 'indoor_task_submissions'
    ));

    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}indoor_task_submissions 
                     ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        
        // If there was a column named created_at, migrate data and drop it
        $created_at_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'created_at'",
            DB_NAME, $wpdb->prefix . 'indoor_task_submissions'
        ));
        
        if (!empty($created_at_exists)) {
            // Copy data from created_at to submitted_at
            $wpdb->query("UPDATE {$wpdb->prefix}indoor_task_submissions 
                         SET submitted_at = created_at 
                         WHERE submitted_at IS NULL");
            
            // Drop the created_at column
            $wpdb->query("ALTER TABLE {$wpdb->prefix}indoor_task_submissions 
                         DROP COLUMN created_at");
        }
    }

    // Check if the referrals table exists and create it if not
    $referrals_table = $wpdb->prefix . 'indoor_task_referrals';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$referrals_table}'") === $referrals_table;

    if (!$table_exists) {
        $sql = "CREATE TABLE {$referrals_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            referrer_id BIGINT UNSIGNED NOT NULL,
            referred_id BIGINT UNSIGNED NOT NULL,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (referrer_id),
            INDEX (referred_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Migrate existing referrals from user meta
        $referrals = $wpdb->get_results(
            "SELECT ID, meta_value FROM {$wpdb->usermeta} 
             WHERE meta_key = 'indoor_tasks_referred_by'"
        );
        
        if (!empty($referrals)) {
            foreach ($referrals as $referral) {
                $wpdb->insert(
                    $referrals_table,
                    [
                        'referrer_id' => $referral->meta_value,
                        'referred_id' => $referral->ID,
                        'status' => 'approved',
                        'created_at' => current_time('mysql')
                    ]
                );
            }
        }
    }
    
    // Create notifications table if it doesn't exist
    $notifications_table = $wpdb->prefix . 'indoor_task_notifications';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$notifications_table}'") === $notifications_table;
    
    if (!$table_exists) {
        $sql = "CREATE TABLE {$notifications_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'general',
            reference_id BIGINT UNSIGNED DEFAULT 0,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (type),
            INDEX (is_read)
        ) $charset_collate;";
        
        dbDelta($sql);
    } else {
        // Check for reference_id column and add if missing
        $reference_id_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'reference_id'",
            DB_NAME, $notifications_table
        ));
        
        if (empty($reference_id_exists)) {
            $wpdb->query("ALTER TABLE {$notifications_table} ADD COLUMN reference_id BIGINT UNSIGNED DEFAULT 0");
        }
    }
    
    // Create announcements tables if they don't exist
    $announcements_table = $wpdb->prefix . 'indoor_task_announcements';
    $announcements_exists = $wpdb->get_var("SHOW TABLES LIKE '{$announcements_table}'") === $announcements_table;
    
    if (!$announcements_exists) {
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$announcements_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'general',
            target_audience VARCHAR(50) DEFAULT 'all',
            send_email TINYINT(1) DEFAULT 1,
            send_push TINYINT(1) DEFAULT 1,
            send_telegram TINYINT(1) DEFAULT 0,
            schedule_time DATETIME NULL,
            status ENUM('pending','scheduled','sent','failed','partial') DEFAULT 'pending',
            sent_at DATETIME NULL,
            sent_count INT DEFAULT 0,
            failed_count INT DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (status),
            INDEX (created_by),
            INDEX (schedule_time)
        ) $charset_collate;");
    }
    
    // Create announcement reads table if it doesn't exist
    $reads_table = $wpdb->prefix . 'indoor_task_announcement_reads';
    $reads_exists = $wpdb->get_var("SHOW TABLES LIKE '{$reads_table}'") === $reads_table;
    
    if (!$reads_exists) {
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$reads_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            announcement_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_read (announcement_id, user_id),
            INDEX (announcement_id),
            INDEX (user_id)
        ) $charset_collate;");
    }
}
