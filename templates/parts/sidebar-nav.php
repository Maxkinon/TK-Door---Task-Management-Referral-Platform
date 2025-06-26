<?php
/**
 * Sidebar Navigation Template Part
 * Modern sidebar navigation for Indoor Tasks
 */

// Get current user info
$current_user = wp_get_current_user();
$user_id = get_current_user_id();

// Get user stats
global $wpdb;
$user_points = 0;
$completed_tasks = 0;

// Get user points from wallet table
$wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
if ($wallet_table_exists) {
    try {
        $points_result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
            $user_id
        ));
        $user_points = $points_result ? intval($points_result) : 0;
    } catch (Exception $e) {
        // Fallback to user meta if table query fails
        $user_points = get_user_meta($user_id, 'indoor_tasks_points', true) ?: 0;
    }
} else {
    // Fallback to user meta if table doesn't exist
    $user_points = get_user_meta($user_id, 'indoor_tasks_points', true) ?: 0;
}

// Get completed tasks count - check if table exists first
$submissions_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_submissions'") === $wpdb->prefix . 'indoor_task_submissions';
if ($submissions_table_exists) {
    try {
        $completed_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE user_id = %d AND status = 'approved'",
            $user_id
        ));
        $completed_tasks = $completed_tasks ? intval($completed_tasks) : 0;
    } catch (Exception $e) {
        $completed_tasks = 0;
    }
}
$completed_tasks = $completed_tasks ? intval($completed_tasks) : 0;

// Current page detection
$current_page = '';
if (isset($_GET['page'])) {
    $current_page = $_GET['page'];
} elseif (isset($GLOBALS['indoor_tasks_current_template'])) {
    $current_page = $GLOBALS['indoor_tasks_current_template'];
}
?>

<div class="tk-sidebar-nav">
    <!-- User Profile Section -->
    <div class="tk-sidebar-header">
        <div class="tk-user-avatar">
            <?php echo get_avatar($user_id, 48, '', '', array('class' => 'tk-avatar-img')); ?>
        </div>
        <div class="tk-user-info">
            <div class="tk-user-name"><?php echo esc_html($current_user->display_name); ?></div>
            <div class="tk-user-email"><?php echo esc_html($current_user->user_email); ?></div>
        </div>
    </div>

    <!-- User Stats -->
    <div class="tk-user-stats">
        <div class="tk-stat-item">
            <div class="tk-stat-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
            </div>
            <div class="tk-stat-info">
                <div class="tk-stat-value"><?php echo number_format($user_points); ?></div>
                <div class="tk-stat-label">Points</div>
            </div>
        </div>
        <div class="tk-stat-item">
            <div class="tk-stat-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 11 12 14 22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
            </div>
            <div class="tk-stat-info">
                <div class="tk-stat-value"><?php echo $completed_tasks; ?></div>
                <div class="tk-stat-label">Completed</div>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="tk-sidebar-menu">
        <ul class="tk-menu-list">
            <li class="tk-menu-item <?php echo ($current_page == 'tk-indoor-dashboard.php' || $current_page == 'tkm-door-dashboard.php') ? 'active' : ''; ?>">
                <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('dashboard')) : home_url('/dashboard/'); ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/>
                        <rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <span class="tk-menu-text">Dashboard</span>
                </a>
            </li>
            
            <li class="tk-menu-item <?php echo ($current_page == 'tk-indoor-tasks.php' || $current_page == 'tkm-door-task-archive.php') ? 'active' : ''; ?>">
                <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('tasks')) : home_url('/tasks/'); ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                    </svg>
                    <span class="tk-menu-text">Available Tasks</span>
                </a>
            </li>
            
            <li class="tk-menu-item <?php echo ($current_page == 'tkm-door-task-archive.php') ? 'active' : ''; ?>">
                <a href="<?php 
                    // Try to find a page with the task archive template
                    $archive_page = $wpdb->get_row("SELECT ID FROM {$wpdb->posts} p 
                        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                        WHERE pm.meta_key = '_wp_page_template' 
                        AND pm.meta_value = 'indoor-tasks/templates/tkm-door-task-archive.php' 
                        AND p.post_status = 'publish' 
                        LIMIT 1");
                    if ($archive_page) {
                        echo esc_url(get_permalink($archive_page->ID));
                    } else {
                        echo home_url('/task-archive/');
                    }
                ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                        <path d="M21 8V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2"/>
                        <path d="M3 10v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10"/>
                    </svg>
                    <span class="tk-menu-text">Task Archive</span>
                </a>
            </li>
            
            <li class="tk-menu-item">
                <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('wallet')) : home_url('/wallet/'); ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="3" width="15" height="13"/>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                    </svg>
                    <span class="tk-menu-text">Wallet</span>
                </a>
            </li>
            
            <li class="tk-menu-item">
                <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('withdrawal')) : home_url('/withdrawal/'); ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    <span class="tk-menu-text">Withdraw</span>
                </a>
            </li>
            
            <li class="tk-menu-item <?php echo ($current_page == 'tk-indoor-referrals.php') ? 'active' : ''; ?>">
                <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('referrals')) : home_url('/referrals/'); ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span class="tk-menu-text">Referrals</span>
                </a>
            </li>
            
            <li class="tk-menu-item <?php echo ($current_page == 'modern-notifications.php') ? 'active' : ''; ?>">
                <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('notifications')) : home_url('/notifications/'); ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <span class="tk-menu-text">Notifications</span>
                </a>
            </li>
            
            <li class="tk-menu-item <?php echo ($current_page == 'modern-announcements.php') ? 'active' : ''; ?>">
                <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('announcements')) : home_url('/announcements/'); ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                    <span class="tk-menu-text">Announcements</span>
                </a>
            </li>
            
            <li class="tk-menu-item <?php echo ($current_page == 'modern-profile.php') ? 'active' : ''; ?>">
                <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('profile')) : home_url('/profile/'); ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <span class="tk-menu-text">Profile</span>
                </a>
            </li>
            
            <li class="tk-menu-item <?php echo ($current_page == 'modern-leaderboard.php') ? 'active' : ''; ?>">
                <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('leaderboard')) : home_url('/leaderboard/'); ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
                        <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
                        <path d="M4 22h16"/>
                        <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
                        <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
                        <path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/>
                    </svg>
                    <span class="tk-menu-text">Leaderboard</span>
                </a>
            </li>
            
            <li class="tk-menu-item <?php echo ($current_page == 'modern-help-desk.php' || $current_page == 'tkm-door-helpdesk.php') ? 'active' : ''; ?>">
                <a href="<?php 
                    // Try multiple possible URL patterns for help desk page
                    if (function_exists('indoor_tasks_get_page_url')) {
                        // Try to find a page with Help Desk template
                        $help_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tkm-door-helpdesk.php');
                        if ($help_page) {
                            echo esc_url(get_permalink($help_page->ID));
                        } else {
                            // Try legacy patterns
                            $help_url = indoor_tasks_get_page_url('help-desk');
                            if (empty($help_url) || $help_url === home_url('/help-desk/')) {
                                $help_url = indoor_tasks_get_page_url('help');
                            }
                            if (empty($help_url) || $help_url === home_url('/help/')) {
                                $help_url = indoor_tasks_get_page_url('support');
                            }
                            if (empty($help_url) || $help_url === home_url('/support/')) {
                                // Fallback to direct template access
                                $help_url = add_query_arg('template', 'tkm-door-helpdesk', home_url('/'));
                            }
                            echo esc_url($help_url);
                        }
                    } else {
                        // Fallback URL patterns
                        echo home_url('/help-desk/');
                    }
                ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span class="tk-menu-text">Help & Support</span>
                </a>
            </li>
            
            <li class="tk-menu-item">
                <a href="<?php echo function_exists('indoor_tasks_get_page_url') ? esc_url(indoor_tasks_get_page_url('kyc')) : home_url('/kyc/'); ?>" class="tk-menu-link">
                    <svg class="tk-menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                    <span class="tk-menu-text">KYC Verification</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Logout Button -->
    <div class="tk-sidebar-footer">
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="tk-logout-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Mobile Overlay for Sidebar -->
<div class="tk-mobile-overlay"></div>

<style>
/* Fix WordPress theme header overlap - Updated for proper positioning */
.tk-sidebar-nav {
    z-index: 999999 !important;
    position: fixed !important;
    left: 0 !important;
    width: 280px !important;
    background: #ffffff !important;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1) !important;
    border-radius: 0 20px 20px 0 !important;
    display: flex !important;
    flex-direction: column !important;
    overflow-y: auto !important;
    padding-top: 45px;
}

/* Default positioning - account for most theme headers */
body:not(.admin-bar) .tk-sidebar-nav {
    top: 0px; /* Start from top, let themes position their headers naturally */
    height: 100vh;
}

/* WordPress admin bar adjustments */
body.admin-bar .tk-sidebar-nav {
    top: 32px; /* Just account for admin bar */
    height: calc(100vh - 32px);
}

@media screen and (max-width: 782px) {
    body.admin-bar .tk-sidebar-nav {
        top: 46px; /* Mobile admin bar height */
        height: calc(100vh - 46px);
    }
}

/* For themes with custom headers - these will override above */
.site-header ~ * .tk-sidebar-nav,
.header ~ * .tk-sidebar-nav,
.masthead ~ * .tk-sidebar-nav {
    top: 80px; /* Common header height */
    height: calc(100vh - 80px);
}

body.admin-bar .site-header ~ * .tk-sidebar-nav,
body.admin-bar .header ~ * .tk-sidebar-nav,
body.admin-bar .masthead ~ * .tk-sidebar-nav {
    top: 112px; /* 32px admin bar + 80px theme header */
    height: calc(100vh - 112px);
}

/* Dashboard wrapper compatibility */
.dashboard-wrapper .tk-sidebar-nav {
    width: 280px !important;
    background: #ffffff !important;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1) !important;
    border-radius: 0 20px 20px 0 !important;
    display: flex !important;
    flex-direction: column !important;
    position: fixed !important;
    left: 0 !important;
    z-index: 999999 !important;
    overflow-y: auto !important;
}

.tk-sidebar-header {
    padding: 20px 10px 1px 10px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.tk-user-avatar .tk-avatar-img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 3px solid #00954b;
}

.tk-user-info .tk-user-name {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 2px;
}

.tk-user-info .tk-user-email {
    font-size: 12px;
    color: #718096;
}

.tk-user-stats {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    gap: 20px;
}

.tk-stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
}

.tk-stat-icon {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #00954b 0%, #02934a 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.tk-stat-value {
    font-weight: 700;
    font-size: 16px;
    color: #2d3748;
}

.tk-stat-label {
    font-size: 12px;
    color: #718096;
}

.tk-sidebar-menu {
    flex: 1;
    padding: 20px 0;
}

.tk-menu-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.tk-menu-item {
    margin-bottom: 4px;
}

.tk-menu-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: #4a5568;
    text-decoration: none;
    border-radius: 0 25px 25px 0;
    margin-right: 20px;
    transition: all 0.3s ease;
}

.tk-menu-link:hover {
    background: #f7fafc;
    color: #00954b;
    text-decoration: none;
}

.tk-menu-item.active .tk-menu-link {
    background: linear-gradient(135deg, #00954b 0%, #02934a 100%);
    color: white;
}

.tk-menu-icon {
    flex-shrink: 0;
}

.tk-menu-text {
    font-weight: 500;
}

.tk-sidebar-footer {
    padding: 20px;
    border-top: 1px solid #e2e8f0;
}

.tk-logout-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #fed7d7;
    color: #742a2a;
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.tk-logout-btn:hover {
    background: #feb2b2;
    text-decoration: none;
    color: #742a2a;
}

/* Mobile Responsive - High Specificity for Elementor and theme compatibility */
@media (max-width: 768px) {
    .dashboard-wrapper .tk-sidebar-nav,
    .tk-sidebar-nav {
        transform: translateX(-100%) !important;
        transition: transform 0.3s ease !important;
        z-index: 999999 !important;
        top: 0 !important;
        height: 100vh !important;
    }
    
    .dashboard-wrapper .tk-sidebar-nav.mobile-open,
    .tk-sidebar-nav.mobile-open,
    .tk-sidebar-nav.open {
        transform: translateX(0) !important;
        z-index: 999999 !important;
    }
    
    /* Ensure sidebar is above all Elementor elements */
    .elementor-element .tk-sidebar-nav,
    .elementor-widget-container .tk-sidebar-nav,
    .elementor-section .tk-sidebar-nav {
        z-index: 999999 !important;
    }
    
    /* Override any theme or Elementor z-index conflicts */
    body .tk-sidebar-nav {
        z-index: 999999 !important;
    }
    
    body.admin-bar .tk-sidebar-nav {
        top: 0 !important;
        height: 100vh !important;
        z-index: 999999 !important;
    }
    
    /* Hide desktop content margins on mobile */
    .modern-dashboard-container,
    .dashboard-wrapper,
    .indoor-tasks-dashboard-container,
    .main-content {
        margin-left: 0 !important;
    }
}

/* Content area adjustments to prevent overlap */
.modern-dashboard-container,
.dashboard-wrapper,
.indoor-tasks-dashboard-container,
.main-content {
    margin-left: 280px !important;
    margin-top: 0px !important; /* Let WordPress handle header spacing naturally */
    min-height: 100vh !important;
    padding-top: 20px !important;
}

/* Override for themes that need extra spacing */
.site-header ~ * .modern-dashboard-container,
.site-header ~ * .dashboard-wrapper,
.site-header ~ * .indoor-tasks-dashboard-container,
.site-header ~ * .main-content,
.header ~ * .modern-dashboard-container,
.header ~ * .dashboard-wrapper,
.header ~ * .indoor-tasks-dashboard-container,
.header ~ * .main-content {
    margin-top: 80px !important;
    min-height: calc(100vh - 80px) !important;
}

body.admin-bar .modern-dashboard-container,
body.admin-bar .dashboard-wrapper,
body.admin-bar .indoor-tasks-dashboard-container,
body.admin-bar .main-content {
    margin-top: 32px !important; /* Just admin bar spacing */
    min-height: calc(100vh - 32px) !important;
}

body.admin-bar .site-header ~ * .modern-dashboard-container,
body.admin-bar .site-header ~ * .dashboard-wrapper,
body.admin-bar .site-header ~ * .indoor-tasks-dashboard-container,
body.admin-bar .site-header ~ * .main-content,
body.admin-bar .header ~ * .modern-dashboard-container,
body.admin-bar .header ~ * .dashboard-wrapper,
body.admin-bar .header ~ * .indoor-tasks-dashboard-container,
body.admin-bar .header ~ * .main-content {
    margin-top: 112px !important; /* 32px admin bar + 80px theme header */
    min-height: calc(100vh - 112px) !important;
}

@media screen and (max-width: 782px) {
    body.admin-bar .modern-dashboard-container,
    body.admin-bar .dashboard-wrapper,
    body.admin-bar .indoor-tasks-dashboard-container,
    body.admin-bar .main-content {
        margin-top: 46px !important; /* Mobile admin bar */
        min-height: calc(100vh - 46px) !important;
    }
    
    body.admin-bar .site-header ~ * .modern-dashboard-container,
    body.admin-bar .site-header ~ * .dashboard-wrapper,
    body.admin-bar .site-header ~ * .indoor-tasks-dashboard-container,
    body.admin-bar .site-header ~ * .main-content,
    body.admin-bar .header ~ * .modern-dashboard-container,
    body.admin-bar .header ~ * .dashboard-wrapper,
    body.admin-bar .header ~ * .indoor-tasks-dashboard-container,
    body.admin-bar .header ~ * .main-content {
        margin-top: 126px !important; /* 46px mobile admin bar + 80px theme header */
        min-height: calc(100vh - 126px) !important;
    }
}

/* Mobile Overlay for Sidebar */
.tk-mobile-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999998;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.tk-mobile-overlay.active {
    display: block;
    opacity: 1;
}

/* Elementor specific fixes */
.elementor *,
.elementor-element *,
.elementor-widget *,
.elementor-section *,
.elementor-container * {
    z-index: auto !important;
}

.elementor .tk-sidebar-nav,
.elementor-element .tk-sidebar-nav,
.elementor-widget .tk-sidebar-nav,
.elementor-section .tk-sidebar-nav,
.elementor-container .tk-sidebar-nav {
    z-index: 999999 !important;
}

/* Make sure mobile navigation appears above everything */
@media (max-width: 768px) {
    .tk-sidebar-nav {
        z-index: 999999 !important;
        position: fixed !important;
    }
    
    /* Override Elementor footer and other high z-index elements */
    .elementor-location-footer,
    .elementor-location-header,
    .elementor-sticky,
    .elementor-sticky--active {
        z-index: 999990 !important;
    }
    
    /* Ensure our sidebar is always on top */
    .tk-sidebar-nav,
    .tk-sidebar-nav.mobile-open,
    .tk-sidebar-nav.open {
        z-index: 999999 !important;
    }
}
</style>



<script>
// Mobile overlay functionality for Elementor integration
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.tk-sidebar-nav');
    const overlay = document.querySelector('.tk-mobile-overlay');
    
    if (sidebar && overlay) {
        // Close sidebar when clicking overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            // Also close any active toggle buttons
            const activeButtons = document.querySelectorAll('.tk-elementor-menu-toggle.active');
            activeButtons.forEach(btn => btn.classList.remove('active'));
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                // Close any active toggle buttons
                const activeButtons = document.querySelectorAll('.tk-elementor-menu-toggle.active');
                activeButtons.forEach(btn => btn.classList.remove('active'));
            }
        });
    }
    
    // Initialize all toggle buttons on the page
    function initializeToggleButtons() {
        const toggleButtons = document.querySelectorAll('.tk-elementor-menu-toggle');
        
        toggleButtons.forEach(function(button) {
            if (!button.hasAttribute('data-initialized')) {
                button.setAttribute('data-initialized', 'true');
                
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const sidebar = document.querySelector('.tk-sidebar-nav');
                    const overlay = document.querySelector('.tk-mobile-overlay');
                    
                    if (sidebar && overlay) {
                        const isOpen = sidebar.classList.contains('mobile-open');
                        
                        if (isOpen) {
                            // Close sidebar
                            sidebar.classList.remove('mobile-open');
                            overlay.classList.remove('active');
                            button.classList.remove('active');
                        } else {
                            // Open sidebar
                            sidebar.classList.add('mobile-open');
                            overlay.classList.add('active');
                            button.classList.add('active');
                        }
                    }
                });
            }
        });
    }
    
    // Initialize existing buttons
    initializeToggleButtons();
    
    // Watch for dynamically added buttons (for shortcodes loaded via AJAX)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                initializeToggleButtons();
            }
        });
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
});
</script>

<style>
/* Enhanced mobile sidebar positioning */
@media (max-width: 768px) {
    body.admin-bar .tk-sidebar-nav {
        top: 0 !important;
        height: 100vh !important;
    }
}

/* Elementor Toggle Button Integration Styles */
.tk-elementor-menu-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--tk-primary-color, #00954b);
    color: var(--tk-text-color, #ffffff);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    outline: none;
    position: relative;
    overflow: hidden;
    padding: 10px 16px;
    font-size: 14px;
    min-height: 44px;
}

.tk-elementor-menu-toggle:hover {
    background: var(--tk-primary-color, #00954b);
    opacity: 0.8;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.tk-elementor-menu-toggle:active {
    transform: translateY(0);
}

/* Size variations */
.tk-elementor-menu-toggle.tk-size-small {
    padding: 8px 12px;
    font-size: 12px;
    min-height: 36px;
}

.tk-elementor-menu-toggle.tk-size-medium {
    padding: 10px 16px;
    font-size: 14px;
    min-height: 44px;
}

.tk-elementor-menu-toggle.tk-size-large {
    padding: 12px 20px;
    font-size: 16px;
    min-height: 52px;
}

/* Style variations */
.tk-elementor-menu-toggle.tk-style-minimal {
    background: transparent;
    color: var(--tk-primary-color, #00954b);
    border: 2px solid var(--tk-primary-color, #00954b);
}

.tk-elementor-menu-toggle.tk-style-minimal:hover {
    background: var(--tk-primary-color, #00954b);
    color: var(--tk-text-color, #ffffff);
}

.tk-elementor-menu-toggle.tk-style-icon-only {
    padding: 10px;
    border-radius: 50%;
    width: 44px;
    height: 44px;
    justify-content: center;
}

.tk-elementor-menu-toggle.tk-style-icon-only.tk-size-small {
    width: 36px;
    height: 36px;
    padding: 8px;
}

.tk-elementor-menu-toggle.tk-style-icon-only.tk-size-large {
    width: 52px;
    height: 52px;
    padding: 12px;
}

/* Hamburger icon */
.tk-elementor-menu-toggle .tk-toggle-icon {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    width: 20px;
    height: 16px;
    position: relative;
}

.tk-elementor-menu-toggle .tk-hamburger-line {
    display: block;
    width: 20px;
    height: 2px;
    background: currentColor;
    margin: 2px 0;
    transition: all 0.3s ease;
    border-radius: 1px;
}

.tk-elementor-menu-toggle.active .tk-hamburger-line:nth-child(1) {
    transform: rotate(45deg) translate(6px, 6px);
}

.tk-elementor-menu-toggle.active .tk-hamburger-line:nth-child(2) {
    opacity: 0;
}

.tk-elementor-menu-toggle.active .tk-hamburger-line:nth-child(3) {
    transform: rotate(-45deg) translate(6px, -6px);
}

/* Display controls */
.tk-show-mobile-only {
    display: none;
}

.tk-show-desktop-only {
    display: inline-flex;
}

.tk-show-always {
    display: inline-flex;
}

@media (max-width: 768px) {
    .tk-show-mobile-only {
        display: inline-flex;
    }
    
    .tk-show-desktop-only {
        display: none;
    }
}

/* Ripple effect */
.tk-elementor-menu-toggle::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.tk-elementor-menu-toggle:active::before {
    width: 300px;
    height: 300px;
}
</style>
