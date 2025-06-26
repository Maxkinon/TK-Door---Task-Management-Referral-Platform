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
    }
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
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'indoor_referrals';
        $this->wallet_table = $wpdb->prefix . 'indoor_task_wallet';
        
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
            scheduled_for datetime DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY reference_id (reference_id),
            KEY status (status),
            KEY scheduled_for (scheduled_for)
        ) $charset_collate;";
        
        dbDelta($wallet_sql);
        
        // Create referral statistics table
        $stats_table = $wpdb->prefix . 'indoor_referral_stats';
        $stats_sql = "CREATE TABLE IF NOT EXISTS {$stats_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            date date NOT NULL,
            referrals_count int(11) DEFAULT 0,
            points_earned int(11) DEFAULT 0,
            ip_addresses text DEFAULT NULL,
            suspicious_activity tinyint(1) DEFAULT 0,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_date (user_id, date),
            KEY user_id (user_id),
            KEY date (date),
            KEY suspicious_activity (suspicious_activity)
        ) $charset_collate;";
        
        dbDelta($stats_sql);
    }
    
    /**
     * Handle referral code from URL
     */
    public function handle_referral_code_url() {
        if (isset($_GET['referral']) || isset($_GET['ref'])) {
            $referral_code = sanitize_text_field($_GET['referral'] ?? $_GET['ref']);
            
            if (!empty($referral_code)) {
                // Set cookie for referral tracking
                $expiry_days = get_option('indoor_tasks_referral_cookie_expiry', 30);
                setcookie('indoor_tasks_referral', $referral_code, time() + (86400 * $expiry_days), '/');
                
                // Store in session as backup
                if (!session_id()) {
                    session_start();
                }
                $_SESSION['indoor_tasks_referral'] = $referral_code;
            }
        }
    }
    
    /**
     * Handle user registration with referral processing
     */
    public function handle_user_registration($user_id, $user_data) {
        $referral_code = $user_data['referral_code'] ?? '';
        
        // Check for referral code from cookie/session if not provided
        if (empty($referral_code)) {
            $referral_code = $_COOKIE['indoor_tasks_referral'] ?? '';
            if (empty($referral_code) && session_id()) {
                $referral_code = $_SESSION['indoor_tasks_referral'] ?? '';
            }
        }
        
        if (!empty($referral_code)) {
            $this->process_referral_registration($user_id, $referral_code, $user_data);
        }
    }
    
    /**
     * Process referral registration with anti-spam checks
     */
    private function process_referral_registration($user_id, $referral_code, $user_data) {
        global $wpdb;
        
        // Find referrer
        $referrer = $this->find_referrer_by_code($referral_code);
        if (!$referrer) {
            error_log("Referral code not found: $referral_code");
            return false;
        }
        
        // Get user info
        $user = get_user_by('id', $user_id);
        $user_ip = $this->get_user_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device_fingerprint = $this->generate_device_fingerprint();
        $email_domain = $this->extract_email_domain($user->user_email);
        
        // Anti-spam checks
        $spam_check = $this->detect_spam_referral($referrer->ID, $user_id, $user_ip, $email_domain, $device_fingerprint);
        
        if ($spam_check['is_spam']) {
            error_log("Spam referral detected: " . $spam_check['reason']);
            
            // Create rejected referral record with backward compatibility
            $rejected_data = array(
                'referrer_id' => $referrer->ID,
                'referee_id' => $user_id,
                'referral_code' => $referral_code,
                'email' => $user->user_email,
                'status' => 'rejected'
            );
            
            // Add additional fields if columns exist
            $columns = $wpdb->get_col("DESCRIBE {$this->table_name}", 0);
            
            if (in_array('ip_address', $columns)) {
                $rejected_data['ip_address'] = $user_ip;
            }
            
            if (in_array('user_agent', $columns)) {
                $rejected_data['user_agent'] = $user_agent;
            }
            
            if (in_array('device_fingerprint', $columns)) {
                $rejected_data['device_fingerprint'] = $device_fingerprint;
            }
            
            if (in_array('email_domain', $columns)) {
                $rejected_data['email_domain'] = $email_domain;
            }
            
            if (in_array('signup_date', $columns)) {
                $rejected_data['signup_date'] = current_time('mysql');
            }
            
            if (in_array('rejection_reason', $columns)) {
                $rejected_data['rejection_reason'] = $spam_check['reason'];
            }
            
            $wpdb->insert(
                $this->table_name,
                $rejected_data,
                array_fill(0, count($rejected_data), '%s')
            );
            
            return false;
        }
        
        // Create pending referral record
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'referrer_id' => $referrer->ID,
                'referee_id' => $user_id,
                'referral_code' => $referral_code,
                'email' => $user->user_email,
                'status' => 'pending',
                'ip_address' => $user_ip,
                'user_agent' => $user_agent,
                'device_fingerprint' => $device_fingerprint,
                'email_domain' => $email_domain,
                'signup_date' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Update user meta with referral info
            update_user_meta($user_id, 'indoor_tasks_referred_by', $referral_code);
            update_user_meta($user_id, 'indoor_tasks_referrer_id', $referrer->ID);
            
            error_log("Pending referral created for user $user_id by referrer {$referrer->ID}");
            
            // Update daily stats
            $this->update_referral_stats($referrer->ID, $user_ip);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle task status change
     */
    public function handle_task_status_change($user_id, $task_id, $status) {
        if ($status === 'approved') {
            $this->check_referral_conditions($user_id, $task_id);
        }
    }
    
    /**
     * Handle KYC status change
     */
    public function handle_kyc_status_change($user_id, $status) {
        if ($status === 'approved') {
            $this->check_referral_conditions_kyc($user_id);
        }
    }
    
    /**
     * Check referral conditions after task completion
     */
    public function check_referral_conditions($user_id, $task_id) {
        global $wpdb;
        
        // Find pending referral for this user
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE referee_id = %d AND status = 'pending'",
            $user_id
        ));
        
        if (!$referral) {
            return;
        }
        
        // Check if this is the first task completion
        $task_count = $this->get_user_completed_tasks_count($user_id);
        $min_tasks = get_option('indoor_tasks_referral_min_tasks', 1);
        
        if ($task_count >= $min_tasks) {
            // Update referral record
            $wpdb->update(
                $this->table_name,
                array(
                    'first_task_date' => current_time('mysql')
                ),
                array('id' => $referral->id),
                array('%s'),
                array('%d')
            );
            
            // Check if profile verification is also required
            $require_kyc = get_option('indoor_tasks_referral_require_kyc', 1);
            
            if (!$require_kyc || $this->is_user_kyc_approved($user_id)) {
                $this->qualify_referral($referral->id);
            }
        }
    }
    
    /**
     * Check referral conditions after KYC approval
     */
    public function check_referral_conditions_kyc($user_id) {
        global $wpdb;
        
        // Find pending referral for this user
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE referee_id = %d AND status = 'pending'",
            $user_id
        ));
        
        if (!$referral) {
            return;
        }
        
        // Update referral record
        $wpdb->update(
            $this->table_name,
            array(
                'kyc_approved_date' => current_time('mysql')
            ),
            array('id' => $referral->id),
            array('%s'),
            array('%d')
        );
        
        // Check if task completion is also satisfied
        $task_count = $this->get_user_completed_tasks_count($user_id);
        $min_tasks = get_option('indoor_tasks_referral_min_tasks', 1);
        
        if ($task_count >= $min_tasks) {
            $this->qualify_referral($referral->id);
        }
    }
    
    /**
     * Qualify referral for bonus (with delay)
     */
    private function qualify_referral($referral_id) {
        global $wpdb;
        
        $delay_hours = get_option('indoor_tasks_referral_delay_hours', 24);
        $bonus_date = date('Y-m-d H:i:s', time() + ($delay_hours * 3600));
        
        // Update referral status to qualified and schedule bonus
        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'qualified',
                'bonus_scheduled_date' => $bonus_date
            ),
            array('id' => $referral_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Schedule bonus processing
        wp_schedule_single_event(time() + ($delay_hours * 3600), 'indoor_tasks_process_referral_bonus', array($referral_id));
        
        error_log("Referral qualified and bonus scheduled for referral ID: $referral_id at $bonus_date");
    }
    
    /**
     * Process delayed referral bonus
     */
    public function process_delayed_referral() {
        if (!isset($_POST['referral_id'])) {
            wp_send_json_error('Missing referral ID');
        }
        
        $referral_id = intval($_POST['referral_id']);
        $this->award_referral_bonus($referral_id);
    }
    
    /**
     * Award referral bonus to both referrer and referee
     */
    public function award_referral_bonus($referral_id) {
        global $wpdb;
        
        // Get referral record
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND status = 'qualified'",
            $referral_id
        ));
        
        if (!$referral) {
            error_log("Referral not found or not qualified: $referral_id");
            return false;
        }
        
        // Get bonus amounts
        $referrer_bonus = get_option('indoor_tasks_referral_reward_amount', 20);
        $referee_bonus = get_option('indoor_tasks_referee_bonus', 20);
        
        // Award bonus to referrer
        $this->add_wallet_transaction($referral->referrer_id, 'referral', $referrer_bonus, 
            "Referral bonus for inviting user ID: {$referral->referee_id}", $referral->referee_id);
        
        // Award bonus to referee
        $this->add_wallet_transaction($referral->referee_id, 'referral_welcome', $referee_bonus,
            "Welcome bonus for joining via referral", $referral->referrer_id);
        
        // Update referral record
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
        
        // Send notifications
        $this->send_referral_notifications($referral->referrer_id, $referral->referee_id, $referrer_bonus, $referee_bonus);
        
        error_log("Referral bonus awarded: $referrer_bonus to referrer {$referral->referrer_id}, $referee_bonus to referee {$referral->referee_id}");
        
        return true;
    }
    
    /**
     * Detect spam/fake referrals
     */
    private function detect_spam_referral($referrer_id, $referee_id, $ip_address, $email_domain, $device_fingerprint) {
        global $wpdb;
        
        $reasons = array();
        
        // Check if referrer and referee are the same user
        if ($referrer_id == $referee_id) {
            $reasons[] = "Self-referral detected";
        }
        
        // Check IP address matching
        if (get_option('indoor_tasks_detect_fake_referrals', 1)) {
            $referrer_ip = get_user_meta($referrer_id, 'indoor_tasks_last_ip', true);
            if (!empty($referrer_ip) && $referrer_ip === $ip_address) {
                $reasons[] = "Same IP address as referrer";
            }
            
            // Check for multiple registrations from same IP today
            $today = date('Y-m-d');
            $ip_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE ip_address = %s AND DATE(signup_date) = %s",
                $ip_address, $today
            ));
            
            $max_per_ip = get_option('indoor_tasks_max_referrals_per_ip', 3);
            if ($ip_count >= $max_per_ip) {
                $reasons[] = "Too many registrations from same IP today ($ip_count)";
            }
        }
        
        // Check disposable email domains
        $disposable_domains = $this->get_disposable_email_domains();
        if (in_array($email_domain, $disposable_domains)) {
            $reasons[] = "Disposable email domain detected: $email_domain";
        }
        
        // Check device fingerprint
        if (!empty($device_fingerprint)) {
            $fingerprint_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE device_fingerprint = %s AND DATE(signup_date) = %s",
                $device_fingerprint, date('Y-m-d')
            ));
            
            if ($fingerprint_count > 0) {
                $reasons[] = "Same device fingerprint detected";
            }
        }
        
        // Check daily referral limits
        $daily_limit = get_option('indoor_tasks_max_referrals_per_user', 10);
        $today_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE referrer_id = %d AND DATE(signup_date) = %s",
            $referrer_id, date('Y-m-d')
        ));
        
        if ($today_count >= $daily_limit) {
            $reasons[] = "Daily referral limit exceeded ($today_count/$daily_limit)";
        }
        
        return array(
            'is_spam' => !empty($reasons),
            'reason' => implode('; ', $reasons)
        );
    }
    
    /**
     * Get list of disposable email domains
     */
    private function get_disposable_email_domains() {
        $default_domains = array(
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com', 
            'tempmail.org', 'temp-mail.org', 'throwaway.email',
            'yopmail.com', 'maildrop.cc', 'temp-mail.io'
        );
        
        $custom_domains = get_option('indoor_tasks_blocked_email_domains', '');
        if (!empty($custom_domains)) {
            $custom_array = array_map('trim', explode(',', $custom_domains));
            $default_domains = array_merge($default_domains, $custom_array);
        }
        
        return $default_domains;
    }
    
    /**
     * Helper functions
     */
    private function find_referrer_by_code($referral_code) {
        $users = get_users(array(
            'meta_key' => 'indoor_tasks_referral_code',
            'meta_value' => $referral_code,
            'number' => 1
        ));
        
        return !empty($users) ? $users[0] : null;
    }
    
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
    }
    
    private function generate_device_fingerprint() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        return md5($user_agent . $accept_language . $accept_encoding);
    }
    
    private function extract_email_domain($email) {
        return strtolower(substr(strrchr($email, "@"), 1));
    }
    
    private function get_user_completed_tasks_count($user_id) {
        global $wpdb;
        
        $task_table = $wpdb->prefix . 'indoor_task_submissions';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $task_table WHERE user_id = %d AND status = 'approved'",
            $user_id
        ));
    }
    
    private function is_user_kyc_approved($user_id) {
        return get_user_meta($user_id, 'indoor_tasks_kyc_status', true) === 'approved';
    }
    
    private function add_wallet_transaction($user_id, $type, $points, $description, $reference_id = null) {
        global $wpdb;
        
        // Update user points
        $current_points = get_user_meta($user_id, 'indoor_tasks_points', true) ?: 0;
        update_user_meta($user_id, 'indoor_tasks_points', $current_points + $points);
        
        // Add wallet transaction
        return $wpdb->insert(
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
    }
    
    private function update_referral_stats($referrer_id, $ip_address) {
        global $wpdb;
        
        $stats_table = $wpdb->prefix . 'indoor_referral_stats';
        $today = date('Y-m-d');
        
        // Get existing stats for today
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $stats_table WHERE user_id = %d AND date = %s",
            $referrer_id, $today
        ));
        
        if ($stats) {
            // Update existing stats
            $ip_addresses = json_decode($stats->ip_addresses, true) ?: array();
            $ip_addresses[] = $ip_address;
            
            $wpdb->update(
                $stats_table,
                array(
                    'referrals_count' => $stats->referrals_count + 1,
                    'ip_addresses' => json_encode(array_unique($ip_addresses))
                ),
                array('id' => $stats->id),
                array('%d', '%s'),
                array('%d')
            );
        } else {
            // Create new stats entry
            $wpdb->insert(
                $stats_table,
                array(
                    'user_id' => $referrer_id,
                    'date' => $today,
                    'referrals_count' => 1,
                    'ip_addresses' => json_encode(array($ip_address))
                ),
                array('%d', '%s', '%d', '%s')
            );
        }
    }
    
    private function send_referral_notifications($referrer_id, $referee_id, $referrer_bonus, $referee_bonus) {
        if (!get_option('indoor_tasks_notify_successful_referral', 1)) {
            return;
        }
        
        $referrer = get_user_by('id', $referrer_id);
        $referee = get_user_by('id', $referee_id);
        
        // Notification for referrer
        if (class_exists('Indoor_Tasks_Notifications')) {
            $notifications = new Indoor_Tasks_Notifications();
            
            $notifications->send_notification($referrer_id, 
                'Referral Bonus Earned!', 
                "You earned $referrer_bonus points for referring {$referee->display_name}!");
                
            $notifications->send_notification($referee_id,
                'Welcome Bonus!',
                "You received $referee_bonus points as a welcome bonus!");
        }
        
        // Email notifications
        $this->send_referral_emails($referrer, $referee, $referrer_bonus, $referee_bonus);
    }
    
    private function send_referral_emails($referrer, $referee, $referrer_bonus, $referee_bonus) {
        // Email to referrer
        $subject = 'Referral Bonus Earned - Indoor Tasks';
        $message = "Hi {$referrer->display_name},\n\n";
        $message .= "Great news! You've earned $referrer_bonus points for referring {$referee->display_name} to Indoor Tasks.\n\n";
        $message .= "Your friend has completed their first task and verified their profile, so both of you have received bonus points!\n\n";
        $message .= "Keep referring friends to earn more rewards!\n\n";
        $message .= "Best regards,\nThe Indoor Tasks Team";
        
        wp_mail($referrer->user_email, $subject, $message);
        
        // Email to referee
        $subject = 'Welcome Bonus - Indoor Tasks';
        $message = "Hi {$referee->display_name},\n\n";
        $message .= "Welcome to Indoor Tasks! You've received $referee_bonus points as a welcome bonus for joining through a referral.\n\n";
        $message .= "You can now use these points to unlock more opportunities on our platform.\n\n";
        $message .= "Start completing tasks to earn even more points!\n\n";
        $message .= "Best regards,\nThe Indoor Tasks Team";
        
        wp_mail($referee->user_email, $subject, $message);
    }
    
    /**
     * Cleanup expired referrals
     */
    public function cleanup_expired_referrals() {
        global $wpdb;
        
        $expiry_days = get_option('indoor_tasks_referral_expiry_days', 30);
        $expiry_date = date('Y-m-d H:i:s', time() - ($expiry_days * 86400));
        
        $wpdb->update(
            $this->table_name,
            array('status' => 'expired'),
            array('status' => 'pending'),
            array('%s'),
            array('%s')
        );
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET status = 'expired' 
            WHERE status = 'pending' AND signup_date < %s",
            $expiry_date
        ));
    }
    
    /**
     * Get referral statistics for admin
     */
    public function get_referral_statistics($user_id = null) {
        global $wpdb;
        
        if ($user_id) {
            $where = $wpdb->prepare("WHERE referrer_id = %d", $user_id);
            $and_where = $wpdb->prepare("WHERE referrer_id = %d AND", $user_id);
        } else {
            $where = "";
            $and_where = "WHERE";
        }
        
        return array(
            'total_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} $where") ?: 0,
            'completed_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} $and_where status = 'completed'") ?: 0,
            'pending_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} $and_where status = 'pending'") ?: 0,
            'qualified_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} $and_where status = 'qualified'") ?: 0,
            'rejected_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} $and_where status = 'rejected'") ?: 0,
            'total_points_awarded' => $wpdb->get_var("SELECT SUM(points_awarded) FROM {$this->table_name} $and_where status = 'completed'") ?: 0
        );
    }
    
    /**
     * Validate email domain against disposable providers
     */
    public function is_disposable_email($email) {
        $domain = $this->extract_email_domain($email);
        return in_array($domain, $this->get_disposable_email_domains());
    }
    
    /**
     * Migrate existing referral database to add missing columns
     */
    public function migrate_referral_database() {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") == $this->table_name;
        
        if (!$table_exists) {
            return; // Table will be created by create_referral_tables()
        }
        
        // Get current table structure
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name}");
        $column_names = array();
        foreach ($columns as $column) {
            $column_names[] = $column->Field;
        }
        
        // Check for missing columns and add them
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

    // ...existing code...
}

// Initialize the class
new Indoor_Tasks_Referral();

// Hook for processing scheduled bonuses
add_action('indoor_tasks_process_referral_bonus', function($referral_id) {
    $referral_system = new Indoor_Tasks_Referral();
    $referral_system->award_referral_bonus($referral_id);
});
