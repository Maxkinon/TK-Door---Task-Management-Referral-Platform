<?php
/**
 * User levels: logic, limits, and level management
 * 
 * Handles user levels based on tasks completed, referrals, and admin settings
 */
class Indoor_Tasks_Levels {
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into task approval to update user level
        add_action('indoor_tasks_submission_approved', array($this, 'check_level_upgrade'), 10, 2);
        
        // Hook into referral creation to update user level if referral-based
        add_action('indoor_tasks_referral_created', array($this, 'check_referral_level_upgrade'), 10, 1);
    }
    
    /**
     * Get a user's current level
     *
     * @param int $user_id The user ID
     * @return string The user's level name
     */
    public static function get_user_level($user_id) {
        // First check if admin has manually set a level
        $admin_set_level = get_user_meta($user_id, 'indoor_tasks_admin_level', true);
        if (!empty($admin_set_level)) {
            return $admin_set_level;
        }
        
        // Get level system settings
        $enable_level_system = get_option('indoor_tasks_enable_level_system', 1);
        $level_type = get_option('indoor_tasks_level_type', 'task');
        $default_level = get_option('indoor_tasks_default_level', 'Bronze');
        
        // If level system is disabled, return default level
        if (!$enable_level_system) {
            return $default_level;
        }
        
        global $wpdb;
        
        // Check if the user_levels table exists
        $table_name = $wpdb->prefix . 'indoor_task_user_levels';
        $level_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($level_table_exists) {
            // Get the user's current level from the database
            $user_level = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}indoor_task_user_levels WHERE id = (
                    SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'indoor_tasks_user_level'
                )",
                $user_id
            ));
            
            if (!empty($user_level)) {
                return $user_level;
            }
        }
        
        // Get completed tasks count
        $task_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE user_id = %d AND status = 'approved'", 
            $user_id
        ));
        
        // Get referrals count - check if referral system is available
        $referral_count = 0;
        
        // Check if referrals table exists
        $referrals_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_referrals'") === $wpdb->prefix . 'indoor_task_referrals';
        
        if ($referrals_table_exists) {
            $referral_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_referrals WHERE referrer_id = %d AND status = 'approved'", 
                $user_id
            ));
        } else {
            // Fallback to user meta
            $referral_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'indoor_tasks_referred_by' AND meta_value = %d",
                $user_id
            ));
        }
        
        $referral_count = $referral_count ?: 0;
        
        // Get all available levels
        $levels = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}indoor_task_user_levels ORDER BY min_tasks ASC, min_referrals ASC");
        
        if (empty($levels)) {
            return $default_level;
        }
        
        // Determine level based on level type
        $current_level_id = null;
        $current_level_name = $default_level;
        
        foreach ($levels as $level) {
            $meets_requirement = false;
            
            if ($level_type === 'task' && $task_count >= $level->min_tasks) {
                $meets_requirement = true;
            } else if ($level_type === 'referral' && $referral_count >= $level->min_referrals) {
                $meets_requirement = true;
            } else if ($level_type === 'mixed' && $task_count >= $level->min_tasks && $referral_count >= $level->min_referrals) {
                $meets_requirement = true;
            }
            
            if ($meets_requirement) {
                $current_level_id = $level->id;
                $current_level_name = $level->name;
            }
        }
        
        // Save the user's level to usermeta if it has changed
        if ($current_level_id) {
            update_user_meta($user_id, 'indoor_tasks_user_level', $current_level_id);
        }
        
        return $current_level_name;
    }
    
    /**
     * Parse level definitions from settings
     *
     * @return array Array of level definitions
     */
    public static function get_level_definitions() {
        $definitions = get_option('indoor_tasks_level_definitions', '');
        $levels = array();
        
        if (empty($definitions)) {
            return $levels;
        }
        
        $lines = explode("\n", $definitions);
        foreach ($lines as $line) {
            $parts = str_getcsv($line);
            if (count($parts) >= 7) {
                $level_name = trim($parts[0]);
                $levels[$level_name] = array(
                    'tasks' => intval($parts[1]),
                    'referrals' => intval($parts[2]),
                    'max_daily_tasks' => intval($parts[3]),
                    'reward_multiplier' => floatval(str_replace('x', '', $parts[4])),
                    'withdrawal_time' => trim($parts[5]),
                    'badge_icon' => trim($parts[6])
                );
            }
        }
        
        return $levels;
    }
    
    /**
     * Check if user should be upgraded after completing a task
     *
     * @param int $user_id The user ID
     * @param int $task_id The task ID
     */
    public function check_level_upgrade($user_id, $task_id) {
        // Only check if level system is enabled and task-based or mixed
        $enable_level_system = get_option('indoor_tasks_enable_level_system', 1);
        $level_type = get_option('indoor_tasks_level_type', 'task');
        
        if (!$enable_level_system || ($level_type !== 'task' && $level_type !== 'mixed')) {
            return;
        }
        
        // Get current level
        $current_level = self::get_user_level($user_id);
        
        // Store the current level before recalculating
        $old_level = $current_level;
        
        // Re-calculate to see if it changed
        $new_level = self::get_user_level($user_id);
        
        if ($old_level !== $new_level) {
            // Level has changed, trigger notification
            do_action('indoor_tasks_level_changed', $user_id, $old_level, $new_level);
            
            global $wpdb;
            
            // Log the level change in user activity
            $wpdb->insert($wpdb->prefix.'indoor_task_user_activities', [
                'user_id' => $user_id,
                'activity_type' => 'level_upgrade',
                'description' => sprintf('User level upgraded from %s to %s', $old_level, $new_level),
                'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                'created_at' => current_time('mysql')
            ]);
            
            // Add notification for the user
            $wpdb->insert($wpdb->prefix.'indoor_task_notifications', [
                'user_id' => $user_id,
                'type' => 'level_upgrade',
                'message' => sprintf('Congratulations! Your level has been upgraded from %s to %s', $old_level, $new_level),
                'created_at' => current_time('mysql')
            ]);
        }
    }
    
    /**
     * Check if user should be upgraded after getting a new referral
     *
     * @param int $user_id The user ID
     */
    public function check_referral_level_upgrade($user_id) {
        // Only check if level system is enabled and referral-based or mixed
        $enable_level_system = get_option('indoor_tasks_enable_level_system', 1);
        $level_type = get_option('indoor_tasks_level_type', 'task');
        
        if (!$enable_level_system || ($level_type !== 'referral' && $level_type !== 'mixed')) {
            return;
        }
        
        // Get current level
        $current_level = self::get_user_level($user_id);
        
        // Store the current level before recalculating
        $old_level = $current_level;
        
        // Re-calculate to see if it changed
        $new_level = self::get_user_level($user_id);
        
        if ($old_level !== $new_level) {
            // Level has changed, trigger notification
            do_action('indoor_tasks_level_changed', $user_id, $old_level, $new_level);
            
            global $wpdb;
            
            // Log the level change in user activity
            $wpdb->insert($wpdb->prefix.'indoor_task_user_activities', [
                'user_id' => $user_id,
                'activity_type' => 'level_upgrade',
                'description' => sprintf('User level upgraded from %s to %s through referrals', $old_level, $new_level),
                'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                'created_at' => current_time('mysql')
            ]);
            
            // Add notification for the user
            $wpdb->insert($wpdb->prefix.'indoor_task_notifications', [
                'user_id' => $user_id,
                'type' => 'level_upgrade',
                'message' => sprintf('Congratulations! Your level has been upgraded from %s to %s by referring new users', $old_level, $new_level),
                'created_at' => current_time('mysql')
            ]);
        }
    }
    
    /**
     * Get the maximum number of daily tasks for a user
     *
     * @param int $user_id The user ID
     * @return int The maximum number of daily tasks
     */
    public static function get_max_daily_tasks($user_id) {
        global $wpdb;
        $default_max = get_option('indoor_tasks_max_tasks_per_day', 10);
        
        // Get the user's level ID
        $level_id = get_user_meta($user_id, 'indoor_tasks_user_level', true);
        
        if (!empty($level_id)) {
            // Get the max daily tasks from the user_levels table
            $max_tasks = $wpdb->get_var($wpdb->prepare(
                "SELECT max_daily_tasks FROM {$wpdb->prefix}indoor_task_user_levels WHERE id = %d",
                $level_id
            ));
            
            if (!empty($max_tasks)) {
                return intval($max_tasks);
            }
        }
        
        // Fallback to the default if level not found
        return $default_max;
    }
    
    /**
     * Get the reward multiplier for a user
     *
     * @param int $user_id The user ID
     * @return float The reward multiplier
     */
    public static function get_reward_multiplier($user_id) {
        global $wpdb;
        
        // Get the user's level ID
        $level_id = get_user_meta($user_id, 'indoor_tasks_user_level', true);
        
        if (!empty($level_id)) {
            // Get the reward multiplier from the user_levels table
            $multiplier = $wpdb->get_var($wpdb->prepare(
                "SELECT reward_multiplier FROM {$wpdb->prefix}indoor_task_user_levels WHERE id = %d",
                $level_id
            ));
            
            if (!empty($multiplier)) {
                return floatval($multiplier);
            }
        }
        
        // Fallback to the default multiplier if level not found or no multiplier
        return 1.0;
    }
    
    /**
     * Set a user's level manually (for admin use)
     *
     * @param int $user_id The user ID
     * @param int $level_id The level ID to set
     * @return bool Whether the level was set successfully
     */
    public static function set_user_level($user_id, $level_id) {
        if (empty($user_id) || empty($level_id)) {
            return false;
        }
        
        global $wpdb;
        
        // Check if level exists
        $level_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_user_levels WHERE id = %d",
            $level_id
        ));
        
        if (!$level_exists) {
            return false;
        }
        
        // Get the old level for comparison
        $old_level_id = get_user_meta($user_id, 'indoor_tasks_user_level', true);
        
        // Update the user's level
        update_user_meta($user_id, 'indoor_tasks_user_level', $level_id);
        
        // Add to activity log
        $level_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}indoor_task_user_levels WHERE id = %d",
            $level_id
        ));
        
        $wpdb->insert($wpdb->prefix.'indoor_task_user_activities', [
            'user_id' => $user_id,
            'activity_type' => 'level_change',
            'description' => sprintf('User level changed to %s by admin', $level_name),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'created_at' => current_time('mysql')
        ]);
        
        return true;
    }
}
