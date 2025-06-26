<?php
/**
 * Database migration script for Indoor Tasks plugin
 * Fixes the withdrawal methods database issues
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migrate withdrawal methods from options to custom table
 */
function indoor_tasks_migrate_withdrawal_methods() {
    global $wpdb;
    
    // Check if table exists, if not create it
    $table_name = $wpdb->prefix . 'indoor_task_withdrawal_methods';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    
    if (!$table_exists) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            method VARCHAR(100) NOT NULL,
            conversion_rate FLOAT NOT NULL,
            min_points INT NOT NULL,
            custom_fields TEXT,
            enabled TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Get withdrawal methods from options
    $methods = get_option('indoor_tasks_withdrawal_methods', []);
    
    if (!empty($methods) && is_array($methods)) {
        // Delete existing methods from table to avoid duplicates
        $wpdb->query("TRUNCATE TABLE {$table_name}");
        
        // Insert methods into table
        foreach ($methods as $method) {
            if (!empty($method['name'])) {
                // Convert input fields to JSON for storage
                $custom_fields = '';
                if (!empty($method['input_fields']) && is_array($method['input_fields'])) {
                    $custom_fields = json_encode($method['input_fields']);
                }
                
                $wpdb->insert(
                    $table_name,
                    [
                        'method' => $method['name'],
                        'conversion_rate' => floatval($method['conversion']),
                        'min_points' => intval($method['min_points'] ?? 0),
                        'custom_fields' => $custom_fields,
                        'enabled' => 1
                    ]
                );
            }
        }
        
        return true;
    }
    
    return false;
}

/**
 * Create clients table
 */
function indoor_tasks_create_clients_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indoor_task_clients';
    
    // Check if table already exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    
    if (!$table_exists) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(50),
            company VARCHAR(255),
            address TEXT,
            website VARCHAR(500),
            status ENUM('active', 'inactive') DEFAULT 'active',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_name (name)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        return true;
    }
    
    return false;
}

/**
 * Add short_description column to tasks table
 */
function indoor_tasks_add_short_description_column() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indoor_tasks';
    
    // Check if short_description column already exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'short_description'");
    
    if (empty($column_exists)) {
        // Add the column
        $sql = "ALTER TABLE {$table_name} ADD COLUMN short_description TEXT AFTER description";
        $wpdb->query($sql);
        
        // Update the database version
        update_option('indoor_tasks_db_version', '1.2.0');
        
        return true;
    }
    
    return false;
}

/**
 * Add deadline column to tasks table if it doesn't exist
 */
function indoor_tasks_add_deadline_column() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indoor_tasks';
    
    // Check if deadline column already exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'deadline'");
    
    if (empty($column_exists)) {
        // Add the column
        $sql = "ALTER TABLE {$table_name} ADD COLUMN deadline DATE AFTER points";
        $wpdb->query($sql);
        
        return true;
    }
    
    return false;
}

/**
 * Add image column to tasks table if it doesn't exist
 */
function indoor_tasks_add_image_column() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indoor_tasks';
    
    // Check if image column already exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'image'");
    
    if (empty($column_exists)) {
        // Add the column
        $sql = "ALTER TABLE {$table_name} ADD COLUMN image VARCHAR(500) AFTER description";
        $wpdb->query($sql);
    }
    
    // Check if task_image_id column already exists
    $image_id_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'task_image_id'");
    
    if (empty($image_id_exists)) {
        // Add the task_image_id column
        $sql = "ALTER TABLE {$table_name} ADD COLUMN task_image_id BIGINT UNSIGNED AFTER image";
        $wpdb->query($sql);
        
        // Add index for the image ID
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_task_image_id (task_image_id)");
    }
    
    return true;
}

/**
 * Add client_id column to tasks table
 */
function indoor_tasks_add_client_id_column() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indoor_tasks';
    
    // Check if client_id column already exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'client_id'");
    
    if (empty($column_exists)) {
        // Add the column
        $sql = "ALTER TABLE {$table_name} ADD COLUMN client_id BIGINT UNSIGNED AFTER category_id";
        $wpdb->query($sql);
        
        // Add foreign key index
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_client_id (client_id)");
        
        return true;
    }
    
    return false;
}

/**
 * Run all necessary database migrations
 */
function indoor_tasks_run_migrations() {
    // Run migrations
    indoor_tasks_migrate_withdrawal_methods();
    indoor_tasks_create_clients_table();
    indoor_tasks_add_short_description_column();
    indoor_tasks_add_deadline_column();
    indoor_tasks_add_image_column();
    indoor_tasks_add_client_id_column();
    
    // Update migration flag
    update_option('indoor_tasks_migrations_run', time());
}

// Trigger migration on plugin update
function indoor_tasks_run_migration() {
    $db_version = get_option('indoor_tasks_db_version', '1.0');
    $current_version = '1.1'; // Increase this when making schema changes
    
    if (version_compare($db_version, $current_version, '<')) {
        indoor_tasks_migrate_withdrawal_methods();
        update_option('indoor_tasks_db_version', $current_version);
    }
}

// Hook for running migrations
add_action('plugins_loaded', 'indoor_tasks_run_migration');
