<?php
/*
Plugin Name: Indoor Tasks
Description: Complete tasks, earn points, and withdraw with KYC. Admin can manage tasks, users, and payouts. PWA, AdSense, and mobile optimized.
Version: 1.0.0
Author: Your Name
Text Domain: indoor-tasks
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'INDOOR_TASKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'INDOOR_TASKS_URL', plugin_dir_url( __FILE__ ) );
define( 'INDOOR_TASKS_VERSION', '1.0.0' );

// Autoload classes
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'Indoor_Tasks_' ) === 0 ) {
        $file = INDOOR_TASKS_PATH . 'includes/class-' . strtolower( str_replace( 'Indoor_Tasks_', '', $class ) ) . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
});

// Load helpers
require_once INDOOR_TASKS_PATH . 'includes/helpers.php';
require_once INDOOR_TASKS_PATH . 'includes/template-helpers.php';
require_once INDOOR_TASKS_PATH . 'includes/user-functions.php';
require_once INDOOR_TASKS_PATH . 'includes/profile-ajax.php';
require_once INDOOR_TASKS_PATH . 'includes/pwa-icons-check.php';
require_once INDOOR_TASKS_PATH . 'includes/database-update.php';
require_once INDOOR_TASKS_PATH . 'includes/database-migration.php';
require_once INDOOR_TASKS_PATH . 'includes/announcements-migration.php';
require_once INDOOR_TASKS_PATH . 'includes/user-activity.php';
require_once INDOOR_TASKS_PATH . 'includes/admin-user-fields.php';
require_once INDOOR_TASKS_PATH . 'includes/database-compatibility.php';
require_once INDOOR_TASKS_PATH . 'includes/elementor-integration.php';

// Load classes
require_once INDOOR_TASKS_PATH . 'includes/class-firebase.php';
require_once INDOOR_TASKS_PATH . 'includes/class-auth.php';
require_once INDOOR_TASKS_PATH . 'includes/class-referral.php'; // Enhanced referral system

// Load API handlers
require_once INDOOR_TASKS_PATH . 'api/auth-handler.php';
require_once INDOOR_TASKS_PATH . 'api/kyc-upload.php';
require_once INDOOR_TASKS_PATH . 'api/notification-fetch.php';
require_once INDOOR_TASKS_PATH . 'api/submit-task.php';
require_once INDOOR_TASKS_PATH . 'api/withdraw-request.php';
require_once INDOOR_TASKS_PATH . 'api/announcements-api.php';

// Load text domain
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'indoor-tasks', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
});

// Initialize Firebase class
add_action('init', function() {
    if (class_exists('Indoor_Tasks_Firebase')) {
        new Indoor_Tasks_Firebase();
    }
    if (class_exists('Indoor_Tasks_Auth')) {
        new Indoor_Tasks_Auth();
    }
});

// Include database migrations
require_once INDOOR_TASKS_PATH . 'includes/database-migration.php';

// Main activation hook - consolidated
register_activation_hook(__FILE__, 'indoor_tasks_activate_plugin');

function indoor_tasks_activate_plugin() {
    // Run database migrations (from includes/database-migration.php)
    if (function_exists('indoor_tasks_run_migrations')) {
        indoor_tasks_run_migrations();
    }
    
    // Run database updates
    if (function_exists('indoor_tasks_update_database')) {
        indoor_tasks_update_database();
    }
    
    // Create template pages
    if (function_exists('indoor_tasks_create_template_pages')) {
        indoor_tasks_create_template_pages();
    }
    
    // Initialize referral system tables
    if (class_exists('Indoor_Tasks_Referral')) {
        $referral_system = new Indoor_Tasks_Referral();
        // Tables are created automatically in the constructor
    }
    
    // Schedule activity log cleanup
    if (!wp_next_scheduled('indoor_tasks_cleanup_activities')) {
        wp_schedule_event(time(), 'daily', 'indoor_tasks_cleanup_activities');
    }
    
    // Schedule announcement processing
    if (!wp_next_scheduled('indoor_tasks_process_announcements')) {
        wp_schedule_event(time(), 'hourly', 'indoor_tasks_process_announcements');
    }
    
    // Set plugin activation flag
    update_option('indoor_tasks_activated', true);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Run database setup and migrations on admin_init (fallback for updates)
add_action('admin_init', function() {
    $last_migration = get_option('indoor_tasks_migrations_run', 0);
    
    // Run migrations if not run in the last 24 hours or never run
    if ((time() - $last_migration) > DAY_IN_SECONDS || $last_migration === 0) {
        if (function_exists('indoor_tasks_run_migrations')) {
            indoor_tasks_run_migrations();
        }
    }
    
    // Ensure referral system tables exist
    if (class_exists('Indoor_Tasks_Referral')) {
        // Tables are checked and created automatically
    }
}, 10);

// Run database update after init hook to ensure WordPress is fully loaded
add_action('init', function() {
    if (function_exists('indoor_tasks_update_database')) {
        indoor_tasks_update_database();
    }
}, 10);

// Add cleanup action
add_action('indoor_tasks_cleanup_activities', function() {
    indoor_tasks_cleanup_activity_logs(90); // Keep 90 days of logs
});

// Add announcement processing action
add_action('indoor_tasks_process_announcements', function() {
    if (function_exists('indoor_tasks_process_scheduled_announcements')) {
        indoor_tasks_process_scheduled_announcements();
    }
});

// Init core classes
add_action( 'init', function() {
    new Indoor_Tasks_Auth();
    new Indoor_Tasks_Tasks();
    new Indoor_Tasks_Wallet();
    new Indoor_Tasks_Withdrawal();
    new Indoor_Tasks_Kyc();
    new Indoor_Tasks_Levels();
    new Indoor_Tasks_Notifications();
    new Indoor_Tasks_Email();
    new Indoor_Tasks_Ads();
    new Indoor_Tasks_Pwa();
    new Indoor_Tasks_Telegram();
    new Indoor_Tasks_Firebase();
    new Indoor_Tasks_OneSignal();
});

// Hide WordPress admin bar for non-admin users
add_action('after_setup_theme', function() {
    if (!current_user_can('administrator') && !is_admin()) {
        show_admin_bar(false);
    }
});

// Additional admin bar hiding for frontend
add_action('wp', function() {
    if (!current_user_can('administrator') && !is_admin()) {
        add_filter('show_admin_bar', '__return_false');
    }
});

// Add meta tag to Indoor Tasks pages for preloader identification
add_action('wp_head', function() {
    if (function_exists('indoor_tasks_is_any_page') && indoor_tasks_is_any_page()) {
        echo '<meta name="indoor-tasks-page" content="true">' . "\n";
        echo '<meta name="theme-color" content="#667eea">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        
        // Add CSS to remove admin bar spacing for non-admin users
        if (!current_user_can('administrator')) {
            echo '<style>html { margin-top: 0 !important; } body { margin-top: 0 !important; }</style>' . "\n";
        }
    }
});

// Enqueue assets
add_action( 'wp_enqueue_scripts', function() {
    // Only load essential styles that exist
    // wp_enqueue_style( 'indoor-tasks-style', INDOOR_TASKS_URL . 'assets/css/style.css', [], INDOOR_TASKS_VERSION );
    // wp_enqueue_style( 'indoor-tasks-mobile-navbar', INDOOR_TASKS_URL . 'assets/css/mobile-navbar.css', [], INDOOR_TASKS_VERSION );
    
    // Enqueue template spacing fixes for all Indoor Tasks pages
    if (function_exists('indoor_tasks_is_any_page') && indoor_tasks_is_any_page()) {
        // wp_enqueue_style( 'indoor-tasks-template-spacing-fix', INDOOR_TASKS_URL . 'assets/css/template-spacing-fix.css', [], INDOOR_TASKS_VERSION );
        // TK Indoor templates handle their own asset loading
    } else {
        wp_enqueue_style( 'indoor-tasks-preloader', INDOOR_TASKS_URL . 'assets/css/preloader.css', [], INDOOR_TASKS_VERSION );
    }
    // wp_enqueue_style( 'indoor-tasks-level-badges', INDOOR_TASKS_URL . 'assets/css/level-badges.css', [], INDOOR_TASKS_VERSION );
    
    // wp_enqueue_script( 'indoor-tasks-main', INDOOR_TASKS_URL . 'assets/js/main.js', [ 'jquery' ], INDOOR_TASKS_VERSION, true );
    
    // TK Indoor templates handle their own JavaScript loading
    if (!function_exists('indoor_tasks_is_any_page') || !indoor_tasks_is_any_page()) {
        wp_enqueue_script( 'indoor-tasks-preloader', INDOOR_TASKS_URL . 'assets/js/preloader.js', [ 'jquery' ], INDOOR_TASKS_VERSION, true );
    }
    
    wp_enqueue_script( 'indoor-tasks-pwa', INDOOR_TASKS_URL . 'assets/js/pwa.js', [], INDOOR_TASKS_VERSION, true );
});

// Enqueue admin assets
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'indoor-tasks') !== false) {
        wp_enqueue_style('indoor-tasks-admin-style', INDOOR_TASKS_URL . 'assets/css/admin.css', [], INDOOR_TASKS_VERSION);
        wp_enqueue_style('indoor-tasks-admin-fix', INDOOR_TASKS_URL . 'assets/css/admin-fix.css', [], INDOOR_TASKS_VERSION);
        // wp_enqueue_style('indoor-tasks-level-badges', INDOOR_TASKS_URL . 'assets/css/level-badges.css', [], INDOOR_TASKS_VERSION);
        
        // Load announcements CSS only on announcements page
        if (isset($_GET['page']) && $_GET['page'] === 'indoor-tasks-announcements') {
            wp_enqueue_style('indoor-tasks-announcements-admin', INDOOR_TASKS_URL . 'assets/css/announcements-admin.css', [], INDOOR_TASKS_VERSION);
            wp_enqueue_script('indoor-tasks-announcements-admin', INDOOR_TASKS_URL . 'assets/js/announcements-admin.js', ['jquery'], INDOOR_TASKS_VERSION, true);
            
            // Localize script for announcements
            wp_localize_script('indoor-tasks-announcements-admin', 'indoorTasksAnnouncementsNonce', wp_create_nonce('indoor_tasks_announcements_nonce'));
        }
        
        // Load user activity detail CSS
        if (isset($_GET['page']) && $_GET['page'] === 'indoor-tasks-user-activity' && isset($_GET['user_id'])) {
            wp_enqueue_style('indoor-tasks-user-activity-detail', INDOOR_TASKS_URL . 'assets/css/user-activity-detail.css', [], INDOOR_TASKS_VERSION);
        }
        
        // Load withdrawal methods CSS only on withdrawal methods page
        if (isset($_GET['page']) && $_GET['page'] === 'indoor-tasks-withdrawal-methods') {
            wp_enqueue_style('indoor-tasks-withdrawal-methods', INDOOR_TASKS_URL . 'assets/css/withdrawal-methods.css', [], INDOOR_TASKS_VERSION);
        }
        
        // Add wp-color-picker for badge colors
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        wp_enqueue_script('indoor-tasks-admin-script', INDOOR_TASKS_URL . 'assets/js/admin.js', ['jquery', 'wp-color-picker'], INDOOR_TASKS_VERSION, true );
        
        // Add a class to the body once JS loads
        wp_add_inline_script('indoor-tasks-admin-script', 'jQuery(document).ready(function($) { $("body").addClass("admin-loaded"); });');
    }
});

// Add admin menu
add_action( 'admin_menu', function() {
    // Main menu (Indoor Tasks)
    add_menu_page( __( 'Indoor Tasks', 'indoor-tasks' ), __( 'Indoor Tasks', 'indoor-tasks' ), 'manage_options', 'indoor-tasks', function() {
        include INDOOR_TASKS_PATH . 'admin/dashboard.php';
    }, 'dashicons-clipboard', 3 );

    // Submenus
    add_submenu_page('indoor-tasks', __( 'Dashboard', 'indoor-tasks' ), __( 'Dashboard', 'indoor-tasks' ), 'manage_options', 'indoor-tasks');
    
    add_submenu_page('indoor-tasks', __( 'Tasks', 'indoor-tasks' ), __( 'Tasks', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-tasks', function() {
        echo '<div class="wrap"><h1>' . __('Tasks Management', 'indoor-tasks') . '</h1>';
        echo '<p>' . __('Use the links below to manage tasks.', 'indoor-tasks') . '</p>';
        echo '<p><a href="admin.php?page=indoor-tasks-add-task" class="button button-primary">' . __('Add New Task', 'indoor-tasks') . '</a> ';
        echo '<a href="admin.php?page=indoor-tasks-tasks-list" class="button">' . __('View All Tasks', 'indoor-tasks') . '</a> ';
        echo '<a href="admin.php?page=indoor-tasks-task-category" class="button">' . __('Manage Categories', 'indoor-tasks') . '</a></p>';
        echo '</div>';
    });
    
    add_submenu_page('indoor-tasks', __( 'Add Task', 'indoor-tasks' ), __( '↳ Add Task', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-add-task', function() {
        include INDOOR_TASKS_PATH . 'admin/manage-tasks.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Tasks List', 'indoor-tasks' ), __( '↳ Tasks List', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-tasks-list', function() {
        include INDOOR_TASKS_PATH . 'admin/tasks-list.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Task Category', 'indoor-tasks' ), __( '↳ Task Categories', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-task-category', function() {
        include INDOOR_TASKS_PATH . 'admin/task-category.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Task Submissions', 'indoor-tasks' ), __( 'Task Submissions', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-task-submissions', function() {
        include INDOOR_TASKS_PATH . 'admin/task-submissions.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Manage Users', 'indoor-tasks' ), __( 'Manage Users', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-manage-users', function() {
        include INDOOR_TASKS_PATH . 'admin/manage-users.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Withdrawals', 'indoor-tasks' ), __( 'Withdrawals', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-withdrawal', function() {
        echo '<div class="wrap"><h1>' . __('Withdrawal Management', 'indoor-tasks') . '</h1>';
        echo '<p>' . __('Use the links below to manage withdrawals.', 'indoor-tasks') . '</p>';
        echo '<p><a href="admin.php?page=indoor-tasks-withdrawal-requests" class="button button-primary">' . __('Withdrawal Requests', 'indoor-tasks') . '</a> ';
        echo '<a href="admin.php?page=indoor-tasks-withdrawal-methods" class="button">' . __('Manage Methods', 'indoor-tasks' ) . '</a></p>';
        echo '</div>';
    });
    
    add_submenu_page('indoor-tasks', __( 'Withdrawal Requests', 'indoor-tasks' ), __( '↳ Withdrawal Requests', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-withdrawal-requests', function() {
        include INDOOR_TASKS_PATH . 'admin/withdrawal-requests.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Withdrawal Methods', 'indoor-tasks' ), __( '↳ Withdrawal Methods', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-withdrawal-methods', function() {
        include INDOOR_TASKS_PATH . 'admin/withdrawal-methods.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Manage KYC', 'indoor-tasks' ), __( 'Manage KYC', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-manage-kyc', function() {
        include INDOOR_TASKS_PATH . 'admin/manage-kyc.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Referral Activity', 'indoor-tasks' ), __( 'Referral Activity', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-referral-activity', function() {
        include INDOOR_TASKS_PATH . 'admin/referral-activity.php';
    });
    
    // Add Wallet Transactions submenu
    add_submenu_page('indoor-tasks', __( 'Wallet Transactions', 'indoor-tasks' ), __( 'Wallet Transactions', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-wallet-transactions', function() {
        include INDOOR_TASKS_PATH . 'admin/wallet-transactions.php';
    });
    
    // Add Membership parent menu
    add_submenu_page('indoor-tasks', __( 'Membership', 'indoor-tasks' ), __( 'Membership', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-membership', function() {
        echo '<div class="wrap"><h1>' . __('Membership Management', 'indoor-tasks') . '</h1>';
        echo '<p>' . __('Use the links below to manage memberships and plans.', 'indoor-tasks') . '</p>';
        echo '<p><a href="admin.php?page=indoor-tasks-membership-statistics" class="button button-primary">' . __('Membership Statistics', 'indoor-tasks') . '</a> ';
        echo '<a href="admin.php?page=indoor-tasks-membership-plans" class="button">' . __('Membership Plans', 'indoor-tasks' ) . '</a> ';
        echo '<a href="admin.php?page=indoor-tasks-payment-gateway" class="button">' . __('Payment Gateway', 'indoor-tasks' ) . '</a> ';
        echo '<a href="admin.php?page=indoor-tasks-recent-payments" class="button">' . __('Recent Payments', 'indoor-tasks' ) . '</a></p>';
        echo '</div>';
    });
    
    // Membership submenus
    add_submenu_page('indoor-tasks', __( 'Membership Statistics', 'indoor-tasks' ), __( '↳ Membership Statistics', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-membership-statistics', function() {
        include INDOOR_TASKS_PATH . 'admin/membership-statistics.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Membership Plans', 'indoor-tasks' ), __( '↳ Membership Plans', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-membership-plans', function() {
        include INDOOR_TASKS_PATH . 'admin/membership-plans.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Payment Gateway', 'indoor-tasks' ), __( '↳ Payment Gateway', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-payment-gateway', function() {
        include INDOOR_TASKS_PATH . 'admin/payment-gateway.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Recent Payments', 'indoor-tasks' ), __( '↳ Recent Payments', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-recent-payments', function() {
        include INDOOR_TASKS_PATH . 'admin/recent-payments.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'User Activity', 'indoor-tasks' ), __( 'User Activity', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-user-activity', function() {
        include INDOOR_TASKS_PATH . 'admin/user-activity.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Clients', 'indoor-tasks' ), __( 'Clients', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-clients', function() {
        include INDOOR_TASKS_PATH . 'admin/clients.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Profit Calculation', 'indoor-tasks' ), __( 'Profit Calculation', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-profit-calculation', function() {
        include INDOOR_TASKS_PATH . 'admin/profit-calculation.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Country Statistics', 'indoor-tasks' ), __( 'Country Statistics', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-country-statistics', function() {
        include INDOOR_TASKS_PATH . 'admin/country-statistics.php';
    });
    
    add_submenu_page('indoor-tasks', __( 'Announcements', 'indoor-tasks' ), __( 'Announcements', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-announcements', function() {
        include INDOOR_TASKS_PATH . 'admin/announcements.php';
    });
    
    // Add health check page under membership
    add_submenu_page('indoor-tasks', __( 'Health Check', 'indoor-tasks' ), __( '↳ Health Check', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-health-check', function() {
        include INDOOR_TASKS_PATH . 'admin/health-check.php';
    });

    add_submenu_page('indoor-tasks', __( 'Settings', 'indoor-tasks' ), __( 'Settings', 'indoor-tasks' ), 'manage_options', 'indoor-tasks-settings', function() {
        include INDOOR_TASKS_PATH . 'admin/settings.php';
    });
});

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
    // No action needed for now
});

// Register uninstall hook
register_uninstall_hook( __FILE__, 'indoor_tasks_uninstall' );
function indoor_tasks_uninstall() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}indoor_tasks");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}indoor_task_submissions");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}indoor_task_wallet");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}indoor_task_withdrawals");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}indoor_task_kyc");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}indoor_task_notifications");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}indoor_task_announcements");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}indoor_task_announcement_reads");
}

// Include mobile bottom navigation (without the broken intk-sidebar-nav)
add_action('wp_footer', function() {
    if (wp_is_mobile() && is_user_logged_in()) {
        include INDOOR_TASKS_PATH . 'templates/parts/mobile-nav.php';
    }
});

/**
 * Register custom page templates from plugin
 */
add_filter('theme_page_templates', 'indoor_tasks_add_page_templates');
add_filter('template_include', 'indoor_tasks_load_page_templates');

/**
 * Add our custom page templates to the template dropdown
 * 
 * @param array $templates Existing templates
 * @return array Updated templates
 */
function indoor_tasks_add_page_templates($templates) {
    
    // TKM Door templates - New modern template system
    $templates['indoor-tasks/templates/tk-indoor-auth.php'] = 'TKM Door - Auth';
    $templates['indoor-tasks/templates/tkm-door-dashboard.php'] = 'TKM Door - Dashboard';
    $templates['indoor-tasks/templates/tkm-door-task-archive.php'] = 'TKM Door - Task Archive';
    $templates['indoor-tasks/templates/tkm-door-task-detail.php'] = 'TKM Door - Task Detail';
    $templates['indoor-tasks/templates/tkm-door-referrals.php'] = 'TKM Door - Referrals';
    $templates['indoor-tasks/templates/tkm-door-leaderboard.php'] = 'TKM Door - Leaderboard';
    $templates['indoor-tasks/templates/tkm-door-wallet.php'] = 'TKM Door - Wallet';
    $templates['indoor-tasks/templates/tkm-door-withdraw.php'] = 'TKM Door - Withdraw';
    $templates['indoor-tasks/templates/tkm-door-kyc.php'] = 'TKM Door - KYC';
    $templates['indoor-tasks/templates/tkm-door-profile.php'] = 'TKM Door - Profile';
    $templates['indoor-tasks/templates/tkm-door-helpdesk.php'] = 'TKM Door - Help Desk';
    $templates['indoor-tasks/templates/tkm-door-announcements.php'] = 'TKM Door - Announcements';
    $templates['indoor-tasks/templates/tkm-door-notifications.php'] = 'TKM Door - Notifications';
    $templates['indoor-tasks/templates/tkm-door-helpdesk-test.php'] = 'TKM Door - Help Desk Test';

    
    return $templates;
}

/**
 * Check if a specific Indoor Tasks template is being used
 * 
 * This function uses multiple detection methods to reliably identify if
 * a specific Indoor Tasks template is being used in the current page.
 * 
 * @param string $template_name The template filename (e.g. 'modern-auth.php')
 * @return bool True if the template is being used, false otherwise
 */
function is_indoor_tasks_template($template_name) {
    // Method 1: Check our custom global variable (most reliable)
    if (isset($GLOBALS['indoor_tasks_current_template']) && $GLOBALS['indoor_tasks_current_template'] === $template_name) {
        return true;
    }
    
    // Method 2: Check current page's template metadata
    global $post;
    if (is_object($post)) {
        $current_template = get_post_meta($post->ID, '_wp_page_template', true);
        
        // Exact match check
        if ($current_template === 'indoor-tasks/templates/' . $template_name) {
            return true;
        }
        
        // Partial match check (more flexible)
        if (strpos($current_template, $template_name) !== false) {
            return true;
        }
    }
    
    // Method 3: Check page slug against common template names
    if (is_page() && is_object($post)) {
        $slug = $post->post_name;
        $template_base = str_replace('.php', '', $template_name);
        $template_base = str_replace('tk-indoor-', '', $template_base);
        
        // Common mappings between slugs and TK Indoor templates
        $slug_map = [
            'login' => ['tk-indoor-auth.php'],
            'auth' => ['tk-indoor-auth.php'],
            'dashboard' => ['tk-indoor-dashboard.php'],
            'profile' => ['tk-indoor-profile.php'],
            'tasks' => ['tk-indoor-tasks.php'],
            'task-list' => ['tk-indoor-tasks.php'],
            'task-detail' => ['tk-indoor-task-detail.php'],
            'wallet' => ['tk-indoor-wallet.php'],
            'withdraw' => ['tk-indoor-withdraw.php'],
            'withdrawal' => ['tk-indoor-withdraw.php'],
            'kyc' => ['tk-indoor-kyc.php'],
            'verification' => ['tk-indoor-kyc.php'],
            'referrals' => ['tk-indoor-referrals.php'],
            'leaderboard' => ['tk-indoor-leaderboard.php'],
            'help' => ['tk-indoor-help.php', 'tkm-door-helpdesk.php'],
            'support' => ['tk-indoor-help.php', 'tkm-door-helpdesk.php'],
            'helpdesk' => ['tkm-door-helpdesk.php'],
            'help-desk' => ['tkm-door-helpdesk.php'],
            'announcements' => ['tk-indoor-announcements.php'],
            'notifications' => ['tk-indoor-notifications.php']
        ];
        
        if (isset($slug_map[$slug]) && in_array($template_name, $slug_map[$slug])) {
            return true;
        }
    }
    
    // Method 4: Check filename of current template
    $current_file = basename(get_page_template());
    if ($current_file === $template_name) {
        return true;
    }
    
    return false;
}

/**
 * Load the appropriate template file from our plugin directory
 * 
 * @param string $template The current template path
 * @return string The modified template path
 */
function indoor_tasks_load_page_templates($template) {
    global $post;
    
    if (is_singular() && isset($post->ID)) {
        $template_file = get_post_meta($post->ID, '_wp_page_template', true);
        
        // Check if the template is from our plugin
        if (strpos($template_file, 'indoor-tasks/templates/') === 0) {
            $file = WP_PLUGIN_DIR . '/' . $template_file;
            if (file_exists($file)) {
                // Set a global to indicate we're using this template
                $GLOBALS['indoor_tasks_current_template'] = basename($template_file);
                
                // Log for debugging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Indoor Tasks template loaded: ' . $file);
                }
                
                return $file;
            } else {
                // Try with direct path as fallback
                $direct_file = INDOOR_TASKS_PATH . 'templates/' . basename($template_file);
                if (file_exists($direct_file)) {
                    // Set a global to indicate we're using this template
                    $GLOBALS['indoor_tasks_current_template'] = basename($template_file);
                    
                    // Log for debugging
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Indoor Tasks template loaded (fallback): ' . $direct_file);
                    }
                    
                    return $direct_file;
                }
                
                // Log missing template file
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Indoor Tasks template file not found: ' . $file);
                }
            }
        }
    }
    
    return $template;
}

/**
 * Handle redirects for Indoor Tasks pages
 * This function ensures users are logged in to access protected pages
 * and prevents redirect loops by carefully checking page types.
 */
add_action('template_redirect', function() {
    // Public pages that don't require login
    $public_pages = array(
        'tk-indoor-auth.php'
    );
    
    // Don't redirect in these cases
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return;
    }
    
    // Check if already logged in - no need to redirect to login
    if (is_user_logged_in()) {
        return;
    }
    
    // Determine if current page is a login/public page
    $is_public_page = false;
    
    // Method 1: Check using our helper function
    foreach ($public_pages as $template) {
        if (is_indoor_tasks_template($template)) {
            $is_public_page = true;
            break;
        }
    }
    
    // Method 2: Check by slug
    if (!$is_public_page && is_page() && get_post()) {
        $slug = get_post()->post_name;
        if (in_array($slug, ['login', 'auth', 'debug', 'register'])) {
            $is_public_page = true;
        }
    }
    
    // Method 3: Check template path directly
    if (!$is_public_page && is_page()) {
        $template = get_post_meta(get_the_ID(), '_wp_page_template', true);
        foreach ($public_pages as $public_template) {
            if (strpos($template, $public_template) !== false) {
                $is_public_page = true;
                break;
            }
        }
    }
    
    // If not on a public page and not logged in, redirect to login
    if (!$is_public_page) {
        // Get the login page - first try by template
        $login_page = get_pages(array(
            'meta_key' => '_wp_page_template',
            'meta_value' => 'indoor-tasks/templates/tk-indoor-auth.php',
            'number' => 1
        ));
        
        if (!empty($login_page)) {
            // Store the current URL as a redirect_to parameter
            $redirect_url = add_query_arg('redirect_to', urlencode($_SERVER['REQUEST_URI']), get_permalink($login_page[0]->ID));
            wp_redirect($redirect_url);
        } else {
            // Try to find by slug if template search failed
            $login_by_slug = get_page_by_path('login');
            if ($login_by_slug) {
                $redirect_url = add_query_arg('redirect_to', urlencode($_SERVER['REQUEST_URI']), get_permalink($login_by_slug->ID));
                wp_redirect($redirect_url);
            } else {
                // Default fallback
                wp_redirect(home_url('/login/?redirect_to=' . urlencode($_SERVER['REQUEST_URI'])));
            }
        }
        exit;
    }
});

/**
 * Create or update necessary pages with Indoor Tasks templates
 * This function ensures that login and dashboard pages exist with correct templates
 */
function indoor_tasks_create_template_pages() {
    // Check if WordPress core is fully loaded and globals are available
    if (!function_exists('get_permalink') || !function_exists('wp_insert_post')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Indoor Tasks: Critical WordPress functions not available. Aborting template page creation.");
        }
        return;
    }
    
    // Check global WordPress objects are initialized
    global $wp_rewrite, $wp;
    if (null === $wp_rewrite || !isset($wp)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Indoor Tasks: WordPress globals not fully initialized. Aborting template page creation.");
        }
        return;
    }

    // Prevent execution during REST API requests, AJAX calls, or WP-CLI
    if (defined('REST_REQUEST') || wp_doing_ajax() || (defined('WP_CLI') && WP_CLI)) {
        return;
    }
    
    // Check if rewrite rules are loaded (important for permalinks)
    if (!$wp_rewrite->using_permalinks()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Indoor Tasks: WordPress permalinks not properly initialized. Continuing with caution.");
        }
    }
    
    // Check if we already ran this function recently (transient-based throttling)
    $throttle_key = 'indoor_tasks_template_pages_check';
    if (get_transient($throttle_key) && !isset($_GET['force_page_creation'])) {
        return;
    }
    
    // Set throttle for 1 hour to prevent repeated runs
    // Use set_transient only if it exists (for better compatibility)
    if (function_exists('set_transient')) {
        set_transient($throttle_key, true, HOUR_IN_SECONDS);
    }
    
    // Log start of page creation process
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Indoor Tasks: Starting template page creation process");
    }
    
    // Array of essential pages to check/create
    $essential_pages = [
        'login' => [
            'title' => 'Login',
            'template' => 'indoor-tasks/templates/tk-indoor-auth.php',
            'content' => '',
        ],
        'dashboard' => [
            'title' => 'Dashboard',
            'template' => 'indoor-tasks/templates/tk-indoor-dashboard.php',
            'content' => '',
        ],
        'profile' => [
            'title' => 'Profile',
            'template' => 'indoor-tasks/templates/tk-indoor-profile.php',
            'content' => '',
        ],
        'tasks' => [
            'title' => 'Tasks',
            'template' => 'indoor-tasks/templates/tk-indoor-tasks.php',
            'content' => '',
        ],
        'task-detail' => [
            'title' => 'Task Detail',
            'template' => 'indoor-tasks/templates/tk-indoor-task-detail.php',
            'content' => '',
        ],
        'announcements' => [
            'title' => 'Announcements',
            'template' => 'indoor-tasks/templates/tk-indoor-announcements.php',
            'content' => '',
        ],
        'notifications' => [
            'title' => 'Notifications',
            'template' => 'indoor-tasks/templates/tk-indoor-notifications.php',
            'content' => '',
        ],
        'wallet' => [
            'title' => 'Wallet',
            'template' => 'indoor-tasks/templates/tk-indoor-wallet.php',
            'content' => '',
        ],
        'withdrawal' => [
            'title' => 'Withdrawal',
            'template' => 'indoor-tasks/templates/tk-indoor-withdraw.php',
            'content' => '',
        ],
        'kyc' => [
            'title' => 'KYC Verification',
            'template' => 'indoor-tasks/templates/tk-indoor-kyc.php',
            'content' => '',
        ],
        'referrals' => [
            'title' => 'Referrals',
            'template' => 'indoor-tasks/templates/tk-indoor-referrals.php',
            'content' => '',
        ],
        'leaderboard' => [
            'title' => 'Leaderboard',
            'template' => 'indoor-tasks/templates/tk-indoor-leaderboard.php',
            'content' => '',
        ],
        'help' => [
            'title' => 'Help & Support',
            'template' => 'indoor-tasks/templates/tk-indoor-help.php',
            'content' => '',
        ],
    ];
    
    foreach ($essential_pages as $slug => $page_data) {
        // First try to find by template
        $existing_page = get_pages([
            'meta_key' => '_wp_page_template',
            'meta_value' => $page_data['template'],
            'number' => 1
        ]);
        
        // If not found by template, try by slug
        if (empty($existing_page)) {
            $page_by_slug = get_page_by_path($slug);
            
            if (!$page_by_slug) {
                // Make sure WordPress is ready for post operations
                if (!function_exists('wp_insert_post') || !function_exists('get_permalink')) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Indoor Tasks: WordPress not ready for page creation. Aborting page creation for $slug.");
                    }
                    continue;
                }
                
                // Create page if it doesn't exist
                try {
                    // Extra validation before creating the page
                    if (!function_exists('wp_insert_post')) {
                        throw new Exception('wp_insert_post function not available');
                    }
                    
                    // Use safer page creation with more checks
                    $page_id = indoor_tasks_create_or_get_page(
                        $slug,
                        $page_data['title'], 
                        $page_data['template'],
                        $page_data['content']
                    );
                    
                    if (is_wp_error($page_id)) {
                        throw new Exception($page_id->get_error_message());
                    } elseif (!$page_id) {
                        throw new Exception('Failed to create page (unknown error)');
                    }
                    
                    // Log creation
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Indoor Tasks: Created or updated {$page_data['title']} page with ID $page_id");
                    }
                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Indoor Tasks: Exception while processing {$page_data['title']} page: " . $e->getMessage());
                    }
                }
            } else {
                // Update template of existing page
                update_post_meta($page_by_slug->ID, '_wp_page_template', $page_data['template']);
                
                // Log update
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Indoor Tasks: Updated template for existing {$page_data['title']} page (ID: {$page_by_slug->ID})");
                }
            }
        }
    }
}

// Function to create template pages
function indoor_tasks_activation_tasks() {
    // Create database tables using existing update functions
    indoor_tasks_update_database();
    
    // Initialize referral system tables
    if (class_exists('Indoor_Tasks_Referral')) {
        $referral_system = new Indoor_Tasks_Referral();
        $referral_system->create_referral_tables();
    }
    
    // Run database migrations
    indoor_tasks_run_migrations();
    
    // Create template pages
    indoor_tasks_create_template_pages();
    
    // Set plugin activation flag
    update_option('indoor_tasks_activated', true);
}

// Also run on plugin load to ensure tables exist
add_action('plugins_loaded', function() {
    if (get_option('indoor_tasks_activated')) {
        // Ensure all database tables are created/updated
        indoor_tasks_update_database();
        
        // Ensure referral tables exist
        if (class_exists('Indoor_Tasks_Referral')) {
            $referral_system = new Indoor_Tasks_Referral();
            $referral_system->create_referral_tables();
        }
    }
});

// Referral logic: add bonus when referred user completes a task
add_action('indoor_tasks_submission_approved', function($user_id, $task_id) {
    global $wpdb;
    $referrer = $wpdb->get_var($wpdb->prepare("SELECT refer_user FROM {$wpdb->users} WHERE ID = %d", $user_id));
    if ($referrer) {
        $bonus = intval(get_option('indoor_tasks_referral_bonus', 50));
        $wpdb->insert($wpdb->prefix.'indoor_task_wallet', [
            'user_id' => $referrer,
            'points' => $bonus,
            'type' => 'referral',
            'reference_id' => $user_id,
            'description' => 'Referral bonus for user #' . $user_id
        ]);
    }
}, 10, 2);

// Force migration check on plugin update
add_action('admin_init', 'indoor_tasks_check_and_run_migrations');

function indoor_tasks_check_and_run_migrations() {
    // Check if migrations need to be run
    $migrations_run = get_option('indoor_tasks_migrations_run', 0);
    $plugin_version = get_option('indoor_tasks_version', '0.0.0');
    
    // If this is a new version or migrations haven't been run, run them
    if (version_compare($plugin_version, INDOOR_TASKS_VERSION, '<') || !$migrations_run) {
        indoor_tasks_run_migrations();
        update_option('indoor_tasks_version', INDOOR_TASKS_VERSION);
    }
}

/**
 * Referral System Functions
 */

// Process referral when new user registers
add_action('user_register', 'indoor_tasks_process_referral');

function indoor_tasks_process_referral($user_id) {
    // Check if there's a referral code in the session or URL
    $referral_code = '';
    
    // First check session
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['referral_code'])) {
        $referral_code = sanitize_text_field($_SESSION['referral_code']);
    } elseif (isset($_GET['referral'])) {
        $referral_code = sanitize_text_field($_GET['referral']);
    } elseif (isset($_COOKIE['indoor_tasks_referral'])) {
        $referral_code = sanitize_text_field($_COOKIE['indoor_tasks_referral']);
    }
    
    if (empty($referral_code)) {
        return;
    }
    
    global $wpdb;
    
    // Find the referral record
    $referral = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_referrals WHERE referral_code = %s AND status = 'pending'",
        $referral_code
    ));
    
    if (!$referral) {
        return;
    }
    
    // Update the referral record
    $result = $wpdb->update(
        $wpdb->prefix . 'indoor_referrals',
        array(
            'referee_id' => $user_id,
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'points_awarded' => 20
        ),
        array('id' => $referral->id),
        array('%d', '%s', '%s', '%d'),
        array('%d')
    );
    
    if ($result) {
        // Award points to both referrer and referee
        indoor_tasks_award_referral_points($referral->referrer_id, $referral->referee_id);
        
        // Clear the referral code from session/cookie
        unset($_SESSION['referral_code']);
        setcookie('indoor_tasks_referral', '', time() - 3600, '/');
    }
}

// Award points for successful referral
function indoor_tasks_award_referral_points($referrer_id, $referee_id) {
    // Award 20 points to referrer
    $referrer_points = get_user_meta($referrer_id, 'indoor_tasks_points', true);
    $referrer_points = intval($referrer_points) + 20;
    update_user_meta($referrer_id, 'indoor_tasks_points', $referrer_points);
    
    // Award 20 points to referee
    $referee_points = get_user_meta($referee_id, 'indoor_tasks_points', true);
    $referee_points = intval($referee_points) + 20;
    update_user_meta($referee_id, 'indoor_tasks_points', $referee_points);
    
    // Log the transaction (if transactions table exists)
    global $wpdb;
    
    $transactions_table = $wpdb->prefix . 'indoor_task_transactions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$transactions_table'") === $transactions_table) {
        // Log for referrer
        $wpdb->insert(
            $transactions_table,
            array(
                'user_id' => $referrer_id,
                'type' => 'referral_bonus',
                'amount' => 20,
                'description' => 'Referral bonus - friend joined',
                'status' => 'completed',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s')
        );
        
        // Log for referee
        $wpdb->insert(
            $transactions_table,
            array(
                'user_id' => $referee_id,
                'type' => 'referral_bonus',
                'amount' => 20,
                'description' => 'Welcome bonus - joined via referral',
                'status' => 'completed',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s')
        );
    }
}

// Store referral code in session/cookie when someone visits with referral link
add_action('init', 'indoor_tasks_store_referral_code');

function indoor_tasks_store_referral_code() {
    if (isset($_GET['referral']) && !is_user_logged_in()) {
        $referral_code = sanitize_text_field($_GET['referral']);
        
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Store in session
        $_SESSION['referral_code'] = $referral_code;
        
        // Also store in cookie as backup (expires in 30 days)
        setcookie('indoor_tasks_referral', $referral_code, time() + (30 * 24 * 60 * 60), '/');
    }
}

// Function to create referrals table
function indoor_tasks_create_referrals_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'indoor_referrals';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        referrer_id int(11) NOT NULL,
        referee_id int(11) DEFAULT NULL,
        referral_code varchar(50) NOT NULL,
        email varchar(255) DEFAULT NULL,
        status enum('pending','completed','expired') DEFAULT 'pending',
        points_awarded int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        completed_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY referral_code (referral_code),
        KEY referrer_id (referrer_id),
        KEY referee_id (referee_id),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * AJAX Handlers for TKM Door Referrals Template
 */

// Handle referral invitation sending
add_action('wp_ajax_tkm_send_referral_invitations', 'tkm_handle_referral_invitations');

function tkm_handle_referral_invitations() {
    // Check nonce for security
    if (!check_ajax_referer('tkm_referral_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to send invitations');
    }
    
    $user_id = get_current_user_id();
    $emails = isset($_POST['emails']) ? $_POST['emails'] : array();
    
    if (empty($emails) || !is_array($emails)) {
        wp_send_json_error('Please provide valid email addresses');
    }
    
    global $wpdb;
    $sent_count = 0;
    $errors = array();
    
    foreach ($emails as $email) {
        $email = sanitize_email(trim($email));
        
        if (!is_email($email)) {
            $errors[] = "Invalid email: $email";
            continue;
        }
        
        // Check if invitation already sent to this email
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}indoor_referrals WHERE referrer_id = %d AND email = %s AND status = 'pending'",
            $user_id,
            $email
        ));
        
        if ($existing) {
            $errors[] = "Invitation already sent to: $email";
            continue;
        }
        
        // Generate unique referral code
        $referral_code = tkm_generate_referral_code($user_id);
        
        // Insert referral record
        $result = $wpdb->insert(
            $wpdb->prefix . 'indoor_referrals',
            array(
                'referrer_id' => $user_id,
                'referral_code' => $referral_code,
                'email' => $email,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Send invitation email
            $sent = tkm_send_invitation_email($email, $referral_code, $user_id);
            if ($sent) {
                $sent_count++;
            } else {
                $errors[] = "Failed to send email to: $email";
            }
        } else {
            $errors[] = "Database error for: $email";
        }
    }
    
    if ($sent_count > 0) {
        wp_send_json_success(array(
            'message' => "$sent_count invitation(s) sent successfully",
            'sent_count' => $sent_count,
            'errors' => $errors
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'No invitations were sent',
            'errors' => $errors
        ));
    }
}

// Generate unique referral code
function tkm_generate_referral_code($user_id) {
    global $wpdb;
    
    do {
        $code = 'REF' . $user_id . rand(1000, 9999);
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}indoor_referrals WHERE referral_code = %s",
            $code
        ));
    } while ($exists);
    
    return $code;
}

// Send invitation email
function tkm_send_invitation_email($email, $referral_code, $referrer_id) {
    $referrer = get_userdata($referrer_id);
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $signup_url = home_url("/?referral=$referral_code");
    
    $subject = sprintf('%s - You\'re invited to join by %s', $site_name, $referrer->display_name);
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #00954b; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .button { display: inline-block; background-color: #00954b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>You're Invited to Join $site_name!</h1>
            </div>
            <div class='content'>
                <p>Hi there!</p>
                <p><strong>{$referrer->display_name}</strong> has invited you to join $site_name - a platform where you can complete tasks and earn money.</p>
                <p>By joining through this invitation, you'll get:</p>
                <ul>
                    <li>Welcome bonus points</li>
                    <li>Access to exclusive tasks</li>
                    <li>Ability to earn and withdraw money</li>
                </ul>
                <p style='text-align: center;'>
                    <a href='$signup_url' class='button'>Join Now</a>
                </p>
                <p>Your referral code: <strong>$referral_code</strong></p>
                <p>Don't miss out on this opportunity to start earning today!</p>
            </div>
            <div class='footer'>
                <p>This invitation was sent by {$referrer->display_name} ({$referrer->user_email})</p>
                <p>&copy; " . date('Y') . " $site_name. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <noreply@' . parse_url($site_url, PHP_URL_HOST) . '>'
    );
    
    return wp_mail($email, $subject, $message, $headers);
}

// Get user's referral link
function tkm_get_user_referral_link($user_id) {
    global $wpdb;
    
    // Check if user already has a referral code
    $existing_code = $wpdb->get_var($wpdb->prepare(
        "SELECT referral_code FROM {$wpdb->prefix}indoor_referrals WHERE referrer_id = %d LIMIT 1",
        $user_id
    ));
    
    if ($existing_code) {
        return home_url("/?referral=$existing_code");
    }
    
    // Generate new referral code for sharing
    $referral_code = tkm_generate_referral_code($user_id);
    
    // Insert a placeholder record for the sharing link
    $wpdb->insert(
        $wpdb->prefix . 'indoor_referrals',
        array(
            'referrer_id' => $user_id,
            'referral_code' => $referral_code,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s')
    );
    
    return home_url("/?referral=$referral_code");
}

// AJAX handler to get referral link
add_action('wp_ajax_tkm_get_referral_link', 'tkm_get_referral_link_ajax');

function tkm_get_referral_link_ajax() {
    if (!check_ajax_referer('tkm_referral_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
    }
    
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in');
    }
    
    $user_id = get_current_user_id();
    $referral_link = tkm_get_user_referral_link($user_id);
    
    wp_send_json_success(array(
        'referral_link' => $referral_link
    ));
}

/**
 * AJAX Handlers for TKM Door Leaderboard Template
 */

// Handle leaderboard data requests
add_action('wp_ajax_tkm_get_leaderboard_data', 'tkm_handle_leaderboard_data');

function tkm_handle_leaderboard_data() {
    // Check nonce for security
    if (!check_ajax_referer('tkm_leaderboard_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in');
    }
    
    $search = sanitize_text_field($_POST['search'] ?? '');
    $category = sanitize_text_field($_POST['category'] ?? 'overall');
    $timeframe = sanitize_text_field($_POST['timeframe'] ?? 'all_time');
    
    try {
        $leaderboard_data = tkm_get_leaderboard_data($search, $category, $timeframe);
        wp_send_json_success($leaderboard_data);
    } catch (Exception $e) {
        wp_send_json_error('Error retrieving leaderboard data');
    }
}

// Handle user profile requests
add_action('wp_ajax_tkm_get_user_profile', 'tkm_handle_user_profile');

function tkm_handle_user_profile() {
    // Check nonce for security
    if (!check_ajax_referer('tkm_leaderboard_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in');
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        wp_send_json_error('Invalid user ID');
    }
    
    try {
        $user_profile = tkm_get_user_profile_data($user_id);
        if ($user_profile) {
            wp_send_json_success($user_profile);
        } else {
            wp_send_json_error('User not found');
        }
    } catch (Exception $e) {
        wp_send_json_error('Error retrieving user profile');
    }
}

// Helper function to get user level based on points
function tkm_calculate_user_level($points) {
    $level_thresholds = array(
        1 => 0,
        2 => 100,
        3 => 300,
        4 => 600,
        5 => 1000,
        6 => 1500,
        7 => 2100,
        8 => 2800,
        9 => 3600,
        10 => 4500
    );
    
    $level = 1;
    foreach ($level_thresholds as $threshold_level => $threshold_points) {
        if ($points >= $threshold_points) {
            $level = $threshold_level;
        }
    }
    
    return $level;
}

// Update user level based on current points
function tkm_update_user_level($user_id) {
    global $wpdb;
    
    // Get user's total points
    $total_points = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type IN ('task_completion', 'referral_bonus', 'daily_bonus') THEN amount ELSE 0 END), 0) 
         FROM {$wpdb->prefix}indoor_task_transactions WHERE user_id = %d",
        $user_id
    ));
    
    $new_level = tkm_calculate_user_level($total_points);
    $current_level = get_user_meta($user_id, 'indoor_tasks_level', true) ?: 1;
    
    if ($new_level > $current_level) {
        update_user_meta($user_id, 'indoor_tasks_level', $new_level);
        
        // Award level-up bonus
        $bonus_points = $new_level * 10;
        
        // Insert level-up transaction
        $wpdb->insert(
            $wpdb->prefix . 'indoor_task_transactions',
            array(
                'user_id' => $user_id,
                'type' => 'level_bonus',
                'amount' => $bonus_points,
                'description' => "Level {$new_level} bonus",
                'status' => 'completed',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s')
        );
        
        return true; // Level changed
    }
    
    return false; // No level change
}

// Schedule automatic level updates
add_action('tkm_update_levels_cron', 'tkm_update_all_user_levels');

function tkm_update_all_user_levels() {
    global $wpdb;
    
    // Get all users with transactions
    $users = $wpdb->get_results(
        "SELECT DISTINCT user_id FROM {$wpdb->prefix}indoor_task_transactions"
    );
    
    foreach ($users as $user) {
        tkm_update_user_level($user->user_id);
    }
}

// Schedule the cron job if not already scheduled
if (!wp_next_scheduled('tkm_update_levels_cron')) {
    wp_schedule_event(time(), 'daily', 'tkm_update_levels_cron');
}

/**
 * AJAX Handlers for TKM Door Wallet & Withdraw Templates
 */

// Handle wallet data requests
add_action('wp_ajax_tkm_get_wallet_data', 'tkm_handle_wallet_data');

function tkm_handle_wallet_data() {
    // Check nonce for security
    if (!check_ajax_referer('tkm_wallet_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in');
    }
    
    $user_id = get_current_user_id();
    
    try {
        // Get wallet data (using the function from wallet template)
        $wallet_data = tkm_get_user_wallet_data($user_id);
        wp_send_json_success($wallet_data);
    } catch (Exception $e) {
        wp_send_json_error('Error retrieving wallet data');
    }
}

// Handle withdrawal request submission
add_action('wp_ajax_tkm_submit_withdrawal', 'tkm_handle_withdrawal_submission');

function tkm_handle_withdrawal_submission() {
    // Check nonce for security
    if (!check_ajax_referer('tkm_withdraw_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in');
    }
    
    $user_id = get_current_user_id();
    $points = intval($_POST['points'] ?? 0);
    $method = sanitize_text_field($_POST['method'] ?? '');
    $account_details = sanitize_textarea_field($_POST['account_details'] ?? '');
    
    // Validation
    $errors = array();
    
    if ($points <= 0) {
        $errors[] = 'Invalid points amount';
    }
    
    if (empty($method)) {
        $errors[] = 'Please select a withdrawal method';
    }
    
    if (empty($account_details)) {
        $errors[] = 'Please provide account details';
    }
    
    // Check user balance
    global $wpdb;
    $wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
    
    if ($wallet_table_exists) {
        $current_balance = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
            $user_id
        ));
    } else {
        $current_balance = get_user_meta($user_id, 'indoor_tasks_points', true) ?: 0;
    }
    
    $min_points = get_option('indoor_tasks_min_withdraw_points', 500);
    
    if ($points < $min_points) {
        $errors[] = "Minimum withdrawal amount is {$min_points} points";
    }
    
    if ($points > $current_balance) {
        $errors[] = 'Insufficient balance';
    }
    
    if (!empty($errors)) {
        wp_send_json_error(implode(', ', $errors));
    }
    
    try {
        // Check if withdrawal table exists
        $withdrawals_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_withwithdrawals'") === $wpdb->prefix . 'indoor_task_withwithdrawals';
        
        if ($withdrawals_table_exists) {
            // Calculate amount in currency
            $conversion_rate = get_option('indoor_tasks_conversion_rate', 0.01);
            $amount = $points * $conversion_rate;
            
            // Insert withdrawal request
            $result = $wpdb->insert(
                $wpdb->prefix . 'indoor_task_withwithdrawals',
                array(
                    'user_id' => $user_id,
                    'method' => $method,
                    'amount' => $amount,
                    'points' => $points,
                    'status' => 'pending',
                    'custom_fields' => $account_details,
                    'requested_at' => current_time('mysql')
                ),
                array('%d', '%s', '%f', '%d', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                // Deduct points from wallet (pending withdrawal)
                if ($wallet_table_exists) {
                    $wpdb->insert(
                        $wpdb->prefix . 'indoor_task_wallet',
                        array(
                            'user_id' => $user_id,
                            'points' => -$points,
                            'type' => 'withdrawal',
                            'description' => "Withdrawal request - {$method}",
                            'reference_id' => $wpdb->insert_id,
                            'created_at' => current_time('mysql')
                        ),
                        array('%d', '%d', '%s', '%s', '%d', '%s')
                    );
                }
                
                wp_send_json_success('Withdrawal request submitted successfully!');
            } else {
                wp_send_json_error('Failed to submit withdrawal request');
            }
        } else {
            wp_send_json_error('Withdrawal system is not properly configured');
        }
    } catch (Exception $e) {
        wp_send_json_error('Error processing withdrawal request');
    }
}
