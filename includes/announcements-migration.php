<?php
/**
 * Announcements Database Migration
 * 
 * Creates the announcements tables if they don't exist
 */

if (!defined('ABSPATH')) exit;

/**
 * Create announcements tables
 */
function indoor_tasks_create_announcements_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Main announcements table
    $announcements_table = $wpdb->prefix . 'indoor_task_announcements';
    
    $sql_announcements = "CREATE TABLE IF NOT EXISTS {$announcements_table} (
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
    ) $charset_collate;";
    
    // Announcement reads tracking table
    $reads_table = $wpdb->prefix . 'indoor_task_announcement_reads';
    
    $sql_reads = "CREATE TABLE IF NOT EXISTS {$reads_table} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        announcement_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (announcement_id, user_id),
        INDEX (announcement_id),
        INDEX (user_id)
    ) $charset_collate;";
    
    // Execute table creation
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $result1 = $wpdb->query($sql_announcements);
    $result2 = $wpdb->query($sql_reads);
    
    // Log results
    if ($result1 !== false && $result2 !== false) {
        error_log('Indoor Tasks: Announcements tables created successfully');
        return true;
    } else {
        error_log('Indoor Tasks: Failed to create announcements tables - ' . $wpdb->last_error);
        return false;
    }
}

/**
 * Check if announcements tables exist
 */
function indoor_tasks_announcements_tables_exist() {
    global $wpdb;
    
    $announcements_table = $wpdb->prefix . 'indoor_task_announcements';
    $reads_table = $wpdb->prefix . 'indoor_task_announcement_reads';
    
    $announcements_exists = $wpdb->get_var("SHOW TABLES LIKE '{$announcements_table}'") === $announcements_table;
    $reads_exists = $wpdb->get_var("SHOW TABLES LIKE '{$reads_table}'") === $reads_table;
    
    return $announcements_exists && $reads_exists;
}

/**
 * Run migration if needed
 */
function indoor_tasks_run_announcements_migration() {
    if (!indoor_tasks_announcements_tables_exist()) {
        indoor_tasks_create_announcements_tables();
    }
}

// Auto-run migration on admin pages
if (is_admin()) {
    add_action('admin_init', 'indoor_tasks_run_announcements_migration');
}
