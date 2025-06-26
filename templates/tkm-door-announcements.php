<?php
/**
 * Template Name: TKM Door - Announcements
 * Description: Modern announcements template for displaying latest news and updates
 * Version: 1.0.0
 */

// Prevent direct file access
defined('ABSPATH') || exit;

// Redirect if not logged in
if (!is_user_logged_in()) {
    // Try to find auth page
    $login_page = null;
    if (function_exists('indoor_tasks_get_page_by_template')) {
        $login_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tk-indoor-auth.php', 'login');
    }
    
    if ($login_page) {
        wp_redirect(get_permalink($login_page->ID));
    } else {
        wp_redirect(home_url('/login/'));
    }
    exit;
}

// Get current user info
$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Get page title
$page_title = 'Latest Announcements';

// Set global template variable for sidebar detection
$GLOBALS['indoor_tasks_current_template'] = 'tkm-door-announcements.php';

// Get database reference
global $wpdb;

// Pagination settings
$per_page = 10;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $per_page;

// Get announcements from database
$announcements = array();
$total_announcements = 0;

// Check if announcements table exists
$announcements_table = $wpdb->prefix . 'indoor_task_announcements';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$announcements_table'") === $announcements_table;

if ($table_exists) {
    // Get total count for pagination (include sent and partial announcements)
    $total_announcements = $wpdb->get_var(
        "SELECT COUNT(*) FROM $announcements_table WHERE status IN ('sent', 'partial')"
    );
    
    // Get announcements with pagination (include sent and partial announcements)
    $announcements = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $announcements_table 
             WHERE status IN ('sent', 'partial') 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        )
    );
}

// Calculate pagination
$total_pages = ceil($total_announcements / $per_page);
$has_prev = $page > 1;
$has_next = $page < $total_pages;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo esc_html($page_title); ?> - <?php bloginfo('name'); ?></title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Announcements Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-announcements.css?ver=1.0.1">
    
    <?php wp_head(); ?>
</head>

<body class="tkm-door-announcements">
    <div class="tkm-wrapper">
        <!-- Include Sidebar Navigation -->
        <?php include_once(INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'); ?>
        
        <div class="tkm-main-content">
            <div class="tkm-container">
                <!-- Header Section -->
                <div class="tkm-header">
                    <div class="tkm-header-content">
                        <h1 class="tkm-title"><?php echo esc_html($page_title); ?></h1>
                        <p class="tkm-subtitle">Stay updated with the latest news and important announcements</p>
                    </div>
                    <div class="tkm-header-stats">
                        <div class="tkm-stat">
                            <span class="tkm-stat-number"><?php echo number_format($total_announcements); ?></span>
                            <span class="tkm-stat-label">Total Announcements</span>
                        </div>
                    </div>
                </div>

                <!-- Announcements Section -->
                <div class="tkm-announcements-section">
                    <?php if (!empty($announcements)): ?>
                        <div class="tkm-announcements-grid">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="tkm-announcement-card" data-type="<?php echo esc_attr($announcement->type); ?>">
                                    <div class="tkm-announcement-header">
                                        <div class="tkm-announcement-type-badge">
                                            <?php 
                                            $type_icons = array(
                                                'general' => 'fas fa-info-circle',
                                                'feature' => 'fas fa-star',
                                                'promotion' => 'fas fa-gift',
                                                'maintenance' => 'fas fa-wrench',
                                                'urgent' => 'fas fa-exclamation-triangle'
                                            );
                                            $type_icon = isset($type_icons[$announcement->type]) ? $type_icons[$announcement->type] : 'fas fa-info-circle';
                                            ?>
                                            <span class="tkm-type-badge tkm-type-<?php echo esc_attr($announcement->type); ?>">
                                                <i class="<?php echo $type_icon; ?>"></i>
                                                <?php echo esc_html(ucfirst($announcement->type)); ?>
                                            </span>
                                        </div>
                                        <div class="tkm-announcement-date">
                                            <i class="fas fa-clock"></i>
                                            <span><?php echo human_time_diff(strtotime($announcement->created_at), current_time('timestamp')) . ' ago'; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="tkm-announcement-content">
                                        <h3 class="tkm-announcement-title">
                                            <?php echo esc_html($announcement->title); ?>
                                        </h3>
                                        
                                        <div class="tkm-announcement-description">
                                            <?php echo wpautop(esc_html($announcement->message)); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="tkm-announcement-footer">
                                        <div class="tkm-announcement-meta">
                                            <div class="tkm-meta-item">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span><?php echo date('M j, Y', strtotime($announcement->created_at)); ?></span>
                                            </div>
                                            <div class="tkm-meta-item">
                                                <i class="fas fa-clock"></i>
                                                <span><?php echo date('g:i A', strtotime($announcement->created_at)); ?></span>
                                            </div>
                                        </div>
                                        <div class="tkm-announcement-actions">
                                            <button class="tkm-btn tkm-btn-outline tkm-btn-sm tkm-toggle-details" type="button">
                                                <i class="fas fa-eye"></i>
                                                <span>View Details</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="tkm-pagination">
                                <div class="tkm-pagination-info">
                                    <span>Showing <?php echo (($page - 1) * $per_page + 1); ?> to <?php echo min($page * $per_page, $total_announcements); ?> of <?php echo $total_announcements; ?> announcements</span>
                                </div>
                                
                                <div class="tkm-pagination-controls">
                                    <?php if ($has_prev): ?>
                                        <a href="<?php echo add_query_arg('paged', $page - 1); ?>" class="tkm-pagination-btn tkm-prev">
                                            <i class="fas fa-chevron-left"></i>
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="tkm-pagination-numbers">
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        if ($start_page > 1): ?>
                                            <a href="<?php echo add_query_arg('paged', 1); ?>" class="tkm-pagination-number">1</a>
                                            <?php if ($start_page > 2): ?>
                                                <span class="tkm-pagination-dots">...</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <a href="<?php echo add_query_arg('paged', $i); ?>" 
                                               class="tkm-pagination-number <?php echo $i === $page ? 'active' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?>
                                                <span class="tkm-pagination-dots">...</span>
                                            <?php endif; ?>
                                            <a href="<?php echo add_query_arg('paged', $total_pages); ?>" class="tkm-pagination-number"><?php echo $total_pages; ?></a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($has_next): ?>
                                        <a href="<?php echo add_query_arg('paged', $page + 1); ?>" class="tkm-pagination-btn tkm-next">
                                            Next
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="tkm-empty-state">
                            <div class="tkm-empty-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <h3 class="tkm-empty-title">No Announcements Yet</h3>
                            <p class="tkm-empty-description">
                                There are currently no announcements to display. Check back later for important updates and news.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcements JavaScript -->
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-announcements.js?ver=1.0.2"></script>
    
    <?php wp_footer(); ?>
</body>
</html>
