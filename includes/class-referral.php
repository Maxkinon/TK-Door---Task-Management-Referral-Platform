<?php
/**
 * Indoor Tasks Referral System
 * Handles referral logic, anti-spam measures, and bonus distribution
 */

if (!defined('ABSPATH')) {
    exit;
}

class Indoor_Tasks_Referral {
    
    private $table_name;
    private $wallet_table;
    
    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'indoor_referrals';
        $this->wallet_table = $wpdb->prefix . 'indoor_wallet_transactions';
        
        $this->init_hooks();
        
        // Initialize hooks
        add_action('init', array($this, 'init_hooks'));
        add_action('wp_ajax_process_delayed_referral', array($this, 'process_delayed_referral'));
        add_action('wp_ajax_nopriv_process_delayed_referral', array($this, 'process_delayed_referral'));
        
        // Hook into user registration
        add_action('indoor_tasks_user_registered', array($this, 'handle_user_registration'), 10, 2);
        
        // Hook into task completion
        add_action('indoor_tasks_task_status_changed', array($this, 'handle_task_status_change'), 10, 3);
        
        // Hook into KYC approval
        add_action('indoor_tasks_kyc_status_changed', array($this, 'handle_kyc_status_change'), 10, 2);
        
        // Schedule cleanup of expired referrals
        if (!wp_next_scheduled('indoor_tasks_cleanup_referrals')) {
            wp_schedule_event(time(), 'daily', 'indoor_tasks_cleanup_referrals');
        }
        add_action('indoor_tasks_cleanup_referrals', array($this, 'cleanup_expired_referrals'));
    }
    
    public function init_hooks() {
        // Create database tables if they don't exist
        $this->create_referral_tables();
        
        // Run database migration for existing tables
        $this->migrate_referral_database();
        
        // Handle referral codes in URL
        add_action('init', array($this, 'handle_referral_code_url'));
    }
    
    /**
     * Create referral tables
     */
    public function create_referral_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Enhanced referrals table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            referrer_id int(11) NOT NULL,
            referee_id int(11) DEFAULT NULL,
            referral_code varchar(50) NOT NULL,
            email varchar(255) DEFAULT NULL,
            status enum('pending','qualified','completed','expired','rejected') DEFAULT 'pending',
            points_awarded int(11) DEFAULT 0,
            referee_bonus int(11) DEFAULT 0,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            device_fingerprint varchar(255) DEFAULT NULL,
            email_domain varchar(100) DEFAULT NULL,
            signup_date datetime DEFAULT NULL,
            first_task_date datetime DEFAULT NULL,
            kyc_approved_date datetime DEFAULT NULL,
            bonus_scheduled_date datetime DEFAULT NULL,
            bonus_awarded_date datetime DEFAULT NULL,
            rejection_reason text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY referral_code (referral_code),
            KEY referrer_id (referrer_id),
            KEY referee_id (referee_id),
            KEY status (status),
            KEY ip_address (ip_address),
            KEY email_domain (email_domain),
            KEY signup_date (signup_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create wallet table if it doesn't exist
        $wallet_sql = "CREATE TABLE IF NOT EXISTS {$this->wallet_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            type varchar(50) NOT NULL,
            points int(11) NOT NULL,
            description text,
            reference_id int(11),
            status enum('pending','completed','cancelled') DEFAULT 'completed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($wallet_sql);
    }
    
    /**
     * Handle referral code from URL and store in cookie/session
     */
    public function handle_referral_code_url() {
        if (!is_admin() && isset($_GET['ref'])) {
            $referral_code = sanitize_text_field($_GET['ref']);
            
            // Validate the referral code exists
            if ($this->validate_referral_code($referral_code)) {
                // Set cookie for 30 days
                setcookie('indoor_referral_code', $referral_code, time() + (30 * 24 * 60 * 60), '/');
                $_COOKIE['indoor_referral_code'] = $referral_code;
                
                // Also store in session as backup
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['indoor_referral_code'] = $referral_code;
                
                error_log("Referral code stored: " . $referral_code);
            } else {
                error_log("Invalid referral code attempted: " . $referral_code);
            }
        }
    }
    
    /**
     * Validate if referral code exists and is active
     */
    public function validate_referral_code($referral_code) {
        global $wpdb;
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE user_login = %s OR user_email = %s",
            $referral_code, $referral_code
        ));
        
        return $user !== null;
    }
    
    /**
     * Get referral code from various sources
     */
    public function get_referral_code() {
        // Check URL parameter first
        if (isset($_GET['ref'])) {
            return sanitize_text_field($_GET['ref']);
        }
        
        // Check cookie
        if (isset($_COOKIE['indoor_referral_code'])) {
            return sanitize_text_field($_COOKIE['indoor_referral_code']);
        }
        
        // Check session
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['indoor_referral_code'])) {
            return sanitize_text_field($_SESSION['indoor_referral_code']);
        }
        
        return null;
    }
    
    /**
     * Process referral for new user registration
     */
    public function process_referral($user_id, $referral_code = null) {
        global $wpdb;
        
        error_log("Processing referral for user $user_id with code: " . ($referral_code ?? 'none'));
        
        if (!$referral_code) {
            $referral_code = $this->get_referral_code();
        }
        
        if (!$referral_code) {
            error_log("No referral code found for user $user_id");
            return false;
        }
        
        // Find the referrer
        $referrer = get_user_by('login', $referral_code);
        if (!$referrer) {
            $referrer = get_user_by('email', $referral_code);
        }
        
        if (!$referrer) {
            error_log("Referrer not found for code: $referral_code");
            return false;
        }
        
        if ($referrer->ID == $user_id) {
            error_log("User cannot refer themselves");
            return false;
        }
        
        // Check for existing referral
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE referee_id = %d",
            $user_id
        ));
        
        if ($existing) {
            error_log("User $user_id already has a referral record");
            return false;
        }
        
        // Anti-spam checks
        $user = get_user_by('ID', $user_id);
        $user_ip = $this->get_user_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $email_domain = substr(strrchr($user->user_email, "@"), 1);
        
        // Check database columns to avoid errors
        $columns = $wpdb->get_col("DESC {$this->table_name}", 0);
        
        // Prepare referral data with fallback for missing columns
        $referral_data = array(
            'referrer_id' => $referrer->ID,
            'referee_id' => $user_id,
            'referral_code' => $referral_code,
            'email' => $user->user_email,
            'status' => 'pending'
        );
        
        // Add optional fields if columns exist
        if (in_array('ip_address', $columns)) {
            $referral_data['ip_address'] = $user_ip;
        }
        
        if (in_array('user_agent', $columns)) {
            $referral_data['user_agent'] = $user_agent;
        }
        
        if (in_array('email_domain', $columns)) {
            $referral_data['email_domain'] = $email_domain;
        }
        
        if (in_array('signup_date', $columns)) {
            $referral_data['signup_date'] = current_time('mysql');
        }
        
        $result = $wpdb->insert(
            $this->table_name,
            $referral_data,
            array_fill(0, count($referral_data), '%s')
        );
        
        if ($result === false) {
            error_log("Failed to insert referral record: " . $wpdb->last_error);
            
            // Fallback: Try basic insert with only required fields
            $basic_data = array(
                'referrer_id' => $referrer->ID,
                'referee_id' => $user_id,
                'referral_code' => $referral_code,
                'status' => 'pending'
            );
            
            $result = $wpdb->insert(
                $this->table_name,
                $basic_data,
                array('%d', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                error_log("Failed fallback referral insert: " . $wpdb->last_error);
                return false;
            } else {
                error_log("Referral created with basic data only");
            }
        }
        
        $referral_id = $wpdb->insert_id;
        
        // Clear referral code from cookie/session
        setcookie('indoor_referral_code', '', time() - 3600, '/');
        unset($_COOKIE['indoor_referral_code']);
        if (session_status() != PHP_SESSION_NONE) {
            unset($_SESSION['indoor_referral_code']);
        }
        
        error_log("Referral processed successfully - ID: $referral_id");
        return $referral_id;
    }
    
    /**
     * Handle user registration hook
     */
    public function handle_user_registration($user_id, $user_data = null) {
        $referral_code = null;
        
        // Check if referral code was passed in user_data
        if (is_array($user_data) && isset($user_data['referral_code'])) {
            $referral_code = $user_data['referral_code'];
        }
        
        $this->process_referral($user_id, $referral_code);
    }
    
    /**
     * Handle task status changes
     */
    public function handle_task_status_change($task_id, $user_id, $status) {
        if ($status === 'approved') {
            $this->check_referral_qualification($user_id);
        }
    }
    
    /**
     * Handle KYC status changes
     */
    public function handle_kyc_status_change($user_id, $status) {
        if ($status === 'approved') {
            global $wpdb;
            
            $wpdb->update(
                $this->table_name,
                array('kyc_approved_date' => current_time('mysql')),
                array('referee_id' => $user_id),
                array('%s'),
                array('%d')
            );
            
            $this->check_referral_qualification($user_id);
        }
    }
    
    /**
     * Check if referral qualifies for bonus
     */
    public function check_referral_qualification($user_id) {
        global $wpdb;
        
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE referee_id = %d AND status = 'pending'",
            $user_id
        ));
        
        if (!$referral) {
            return false;
        }
        
        // Check qualification criteria
        $qualified = true;
        
        // Check if user has completed at least one task
        $completed_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
             WHERE user_id = %d AND status = 'approved'",
            $user_id
        ));
        
        if ($completed_tasks < 1) {
            $qualified = false;
        }
        
        // Check KYC status if required
        $kyc_status = get_user_meta($user_id, 'kyc_status', true);
        if ($kyc_status !== 'approved') {
            $qualified = false;
        }
        
        if ($qualified) {
            $this->mark_referral_qualified($referral->id);
        }
        
        return $qualified;
    }
    
    /**
     * Mark referral as qualified and schedule bonus
     */
    public function mark_referral_qualified($referral_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            array(
                'status' => 'qualified',
                'bonus_scheduled_date' => current_time('mysql')
            ),
            array('id' => $referral_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Schedule bonus award (can be immediate or delayed)
            wp_schedule_single_event(time() + 300, 'indoor_tasks_process_referral_bonus', array($referral_id));
            error_log("Referral $referral_id marked as qualified and bonus scheduled");
        }
    }
    
    /**
     * Award referral bonus
     */
    public function award_referral_bonus($referral_id) {
        global $wpdb;
        
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND status = 'qualified'",
            $referral_id
        ));
        
        if (!$referral) {
            error_log("Referral $referral_id not found or not qualified");
            return false;
        }
        
        // Get bonus amounts from settings - use the admin setting as primary source
        $referrer_bonus = get_option('indoor_tasks_referral_reward_amount', 20);
        $referee_bonus = get_option('indoor_tasks_referee_bonus', intval($referrer_bonus / 2));
        
        // Award bonus to referrer
        $this->add_wallet_transaction($referral->referrer_id, 'referral_bonus', $referrer_bonus, 
            "Referral bonus for user ID: {$referral->referee_id}", $referral_id);
        
        // Award bonus to referee
        if ($referee_bonus > 0) {
            $this->add_wallet_transaction($referral->referee_id, 'referee_bonus', $referee_bonus, 
                "Welcome bonus for being referred", $referral_id);
        }
        
        // Update referral status
        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'completed',
                'points_awarded' => $referrer_bonus,
                'referee_bonus' => $referee_bonus,
                'bonus_awarded_date' => current_time('mysql')
            ),
            array('id' => $referral_id),
            array('%s', '%d', '%d', '%s'),
            array('%d')
        );
        
        error_log("Referral bonus awarded - Referral ID: $referral_id, Referrer: {$referral->referrer_id}, Referee: {$referral->referee_id}");
        return true;
    }
    
    /**
     * Add wallet transaction
     */
    private function add_wallet_transaction($user_id, $type, $points, $description, $reference_id = null) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->wallet_table,
            array(
                'user_id' => $user_id,
                'type' => $type,
                'points' => $points,
                'description' => $description,
                'reference_id' => $reference_id,
                'status' => 'completed'
            ),
            array('%d', '%s', '%d', '%s', '%d', '%s')
        );
        
        if ($result !== false) {
            // Update user's total points
            $current_points = get_user_meta($user_id, 'total_points', true) ?: 0;
            update_user_meta($user_id, 'total_points', $current_points + $points);
            
            error_log("Wallet transaction added - User: $user_id, Type: $type, Points: $points");
        } else {
            error_log("Failed to add wallet transaction: " . $wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * Get user's IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Clean up expired referrals
     */
    public function cleanup_expired_referrals() {
        global $wpdb;
        
        $expiry_days = get_option('indoor_tasks_referral_expiry_days', 30);
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET status = 'expired' 
             WHERE status = 'pending' 
             AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $expiry_days
        ));
        
        if ($result > 0) {
            error_log("Expired $result referral records");
        }
    }
    
    /**
     * Get referral statistics for a user
     */
    public function get_user_referral_stats($user_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_referrals,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_referrals,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_referrals,
                SUM(points_awarded) as total_points_earned
             FROM {$this->table_name} 
             WHERE referrer_id = %d",
            $user_id
        ), ARRAY_A);
        
        return $stats;
    }
    
    /**
     * Get overall referral system statistics
     */
    public function get_referral_statistics() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_referrals,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_referrals,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_referrals,
                SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified_referrals,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_referrals,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_referrals,
                COALESCE(SUM(points_awarded), 0) as total_points_awarded,
                COALESCE(SUM(referee_bonus), 0) as total_referee_bonuses
             FROM {$this->table_name}",
            ARRAY_A
        );
        
        // Ensure all values are integers/numbers and not null
        $stats = array_map(function($value) {
            return $value === null ? 0 : (int)$value;
        }, $stats);
        
        return $stats;
    }
    
    /**
     * Get referral leaderboard
     */
    public function get_referral_leaderboard($limit = 10) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                u.display_name,
                u.user_login,
                COUNT(r.id) as total_referrals,
                SUM(r.points_awarded) as total_points
             FROM {$wpdb->users} u
             LEFT JOIN {$this->table_name} r ON u.ID = r.referrer_id AND r.status = 'completed'
             WHERE r.id IS NOT NULL
             GROUP BY u.ID
             ORDER BY total_points DESC, total_referrals DESC
             LIMIT %d",
            $limit
        ));
        
        return $results;
    }
    
    /**
     * Process delayed referral (AJAX handler)
     */
    public function process_delayed_referral() {
        check_ajax_referer('indoor_tasks_referral', 'nonce');
        
        $user_id = intval($_POST['user_id']);
        $referral_code = sanitize_text_field($_POST['referral_code']);
        
        if (!$user_id || !$referral_code) {
            wp_die('Invalid parameters');
        }
        
        $result = $this->process_referral($user_id, $referral_code);
        
        wp_send_json(array(
            'success' => $result !== false,
            'referral_id' => $result
        ));
    }
    
    /**
     * Migrate referral database to add missing columns
     */
    public function migrate_referral_database() {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        if (!$table_exists) {
            return;
        }
        
        // Get current columns
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name}");
        $column_names = array_column($columns, 'Field');
        
        // Define required columns and their ALTER statements
        $required_columns = array(
            'ip_address' => "ADD COLUMN ip_address varchar(45) DEFAULT NULL",
            'user_agent' => "ADD COLUMN user_agent text DEFAULT NULL", 
            'device_fingerprint' => "ADD COLUMN device_fingerprint varchar(255) DEFAULT NULL",
            'email_domain' => "ADD COLUMN email_domain varchar(100) DEFAULT NULL",
            'signup_date' => "ADD COLUMN signup_date datetime DEFAULT NULL",
            'first_task_date' => "ADD COLUMN first_task_date datetime DEFAULT NULL",
            'kyc_approved_date' => "ADD COLUMN kyc_approved_date datetime DEFAULT NULL",
            'bonus_scheduled_date' => "ADD COLUMN bonus_scheduled_date datetime DEFAULT NULL",
            'bonus_awarded_date' => "ADD COLUMN bonus_awarded_date datetime DEFAULT NULL",
            'rejection_reason' => "ADD COLUMN rejection_reason text DEFAULT NULL",
            'points_awarded' => "ADD COLUMN points_awarded int(11) DEFAULT 0",
            'referee_bonus' => "ADD COLUMN referee_bonus int(11) DEFAULT 0"
        );
        
        $migrations_applied = false;
        
        foreach ($required_columns as $column => $alter_sql) {
            if (!in_array($column, $column_names)) {
                $full_sql = "ALTER TABLE {$this->table_name} {$alter_sql}";
                $result = $wpdb->query($full_sql);
                
                if ($result !== false) {
                    error_log("Indoor Tasks: Added missing column '{$column}' to referral table");
                    $migrations_applied = true;
                } else {
                    error_log("Indoor Tasks: Failed to add column '{$column}' - " . $wpdb->last_error);
                }
            }
        }
        
        // Add missing indexes if migrations were applied
        if ($migrations_applied) {
            $index_sql = array(
                "ALTER TABLE {$this->table_name} ADD INDEX IF NOT EXISTS ip_address (ip_address)",
                "ALTER TABLE {$this->table_name} ADD INDEX IF NOT EXISTS email_domain (email_domain)",
                "ALTER TABLE {$this->table_name} ADD INDEX IF NOT EXISTS signup_date (signup_date)"
            );
            
            foreach ($index_sql as $sql) {
                $wpdb->query($sql);
            }
            
            error_log("Indoor Tasks: Referral database migration completed");
        }
    }
}

// Initialize the class
new Indoor_Tasks_Referral();

// Hook for processing scheduled bonuses
add_action('indoor_tasks_process_referral_bonus', function($referral_id) {
    $referral_system = new Indoor_Tasks_Referral();
    $referral_system->award_referral_bonus($referral_id);
});
