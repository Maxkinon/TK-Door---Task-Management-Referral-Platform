<?php
/**
 * Template Name: TKM Door - Task Archive
 * Description: Modern task archive template for Indoor Tasks plugin
 */

// Prevent direct file access
defined('ABSPATH') || exit;

// Redirect if not logged in
if (!is_user_logged_in()) {
    $login_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tk-indoor-auth.php', 'login');
    if ($login_page) {
        wp_redirect(get_permalink($login_page->ID));
    } else {
        wp_redirect(home_url('/login/'));
    }
    exit;
}

// Get current user info
$user_id = get_current_user_id();

// Get filter parameters
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
$sort_by = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'newest';

// Get database data
global $wpdb;

// Get task statistics for current user
$submissions_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_submissions'") === $wpdb->prefix . 'indoor_task_submissions';
$tasks_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_tasks'") === $wpdb->prefix . 'indoor_tasks';
$categories_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_categories'") === $wpdb->prefix . 'indoor_task_categories';

$stats = array(
    'completed_today' => 0,
    'pending_submissions' => 0,
    'total_completed' => 0,
    'total_rejected' => 0
);

if ($submissions_table_exists) {
    try {
        // Completed today
        $stats['completed_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
             WHERE user_id = %d AND status = 'approved' AND DATE(reviewed_at) = CURDATE()",
            $user_id
        ));
        
        // Pending submissions
        $stats['pending_submissions'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
             WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));
        
        // Total completed
        $stats['total_completed'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
             WHERE user_id = %d AND status = 'approved'",
            $user_id
        ));
        
        // Total rejected
        $stats['total_rejected'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
             WHERE user_id = %d AND status = 'rejected'",
            $user_id
        ));
    } catch (Exception $e) {
        // Keep defaults
    }
}

// Get categories for filter
$categories = array();
if ($categories_table_exists) {
    try {
        // Check if status column exists
        $status_column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}indoor_task_categories LIKE 'status'");
        if ($status_column_exists) {
            $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}indoor_task_categories WHERE status = 'active' ORDER BY name ASC");
        } else {
            $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}indoor_task_categories ORDER BY name ASC");
        }
    } catch (Exception $e) {
        // Fallback: try without status filter
        try {
            $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}indoor_task_categories ORDER BY name ASC");
        } catch (Exception $e2) {
            // Keep empty
        }
    }
}

// Build query to show available tasks with user submission status
$where_conditions = array("t.status = 'active'");
$join_tables = "FROM {$wpdb->prefix}indoor_tasks t";

if ($categories_table_exists) {
    $join_tables .= " LEFT JOIN {$wpdb->prefix}indoor_task_categories c ON t.category_id = c.id";
}

if ($submissions_table_exists) {
    $join_tables .= " LEFT JOIN {$wpdb->prefix}indoor_task_submissions s ON t.id = s.task_id AND s.user_id = {$user_id}";
}

// Apply filters
if ($category_filter > 0) {
    $where_conditions[] = "t.category_id = {$category_filter}";
}

if ($status_filter !== 'all') {
    $valid_statuses = array('available', 'pending', 'approved', 'rejected');
    if (in_array($status_filter, $valid_statuses)) {
        if ($status_filter === 'available') {
            $where_conditions[] = "s.id IS NULL"; // No submission exists
        } elseif ($status_filter === 'pending') {
            $where_conditions[] = "s.status = 'pending'";
        } elseif ($status_filter === 'approved') {
            $where_conditions[] = "s.status = 'approved'";
        } elseif ($status_filter === 'rejected') {
            $where_conditions[] = "s.status = 'rejected'";
        }
    }
}

// Build ORDER BY clause
$order_by = "ORDER BY t.created_at DESC"; // Default: Newest first
switch ($sort_by) {
    case 'oldest':
        $order_by = "ORDER BY t.created_at ASC";
        break;
    case 'highest_points':
        $order_by = "ORDER BY t.points DESC, t.created_at DESC";
        break;
    case 'newest':
    default:
        $order_by = "ORDER BY t.created_at DESC";
        break;
}

// Get available tasks with user submission status
$user_tasks = array();
if ($tasks_table_exists) {
    try {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        // Check what columns exist in submissions table
        $points_awarded_exists = false;
        $admin_notes_exists = false;
        
        if ($submissions_table_exists) {
            $points_awarded_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}indoor_task_submissions LIKE 'points_awarded'");
            $admin_notes_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}indoor_task_submissions LIKE 'admin_notes'");
        }
        
        // Build select fields based on available columns
        $select_fields = "t.*, s.status as submission_status, s.submitted_at";
        if ($points_awarded_exists) {
            $select_fields .= ", s.points_awarded";
        }
        if ($admin_notes_exists) {
            $select_fields .= ", s.admin_notes";
        }
        if ($categories_table_exists) {
            $select_fields .= ", c.name as category_name, c.color as category_color";
        }
        
        $query = "SELECT {$select_fields} {$join_tables} {$where_clause} {$order_by} LIMIT 50";
        
        $user_tasks = $wpdb->get_results($query);
        
        // Ensure we always have an array
        if (!$user_tasks) {
            $user_tasks = [];
        }        } catch (Exception $e) {
            // Simple fallback - just get all active tasks
            try {
                $user_tasks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE status = 'active' ORDER BY created_at DESC LIMIT 10");
            } catch (Exception $e2) {
                // Continue with empty array if all fails
                $user_tasks = [];
            }
        }
}

// Helper function to get task detail URL
function get_task_detail_url($task_id) {
    // Try to find a page with TKM Door task detail template
    global $wpdb;
    $task_detail_page = $wpdb->get_row("SELECT ID FROM {$wpdb->posts} p 
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        WHERE pm.meta_key = '_wp_page_template' 
        AND pm.meta_value = 'indoor-tasks/templates/tkm-door-task-detail.php' 
        AND p.post_status = 'publish' 
        LIMIT 1");
    
    if ($task_detail_page) {
        return add_query_arg('task_id', $task_id, get_permalink($task_detail_page->ID));
    }
    
    // Try to find any task detail template
    $task_detail_page = $wpdb->get_row("SELECT ID FROM {$wpdb->posts} p 
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        WHERE pm.meta_key = '_wp_page_template' 
        AND pm.meta_value LIKE '%task-detail%' 
        AND p.post_status = 'publish' 
        LIMIT 1");
    
    if ($task_detail_page) {
        return add_query_arg('task_id', $task_id, get_permalink($task_detail_page->ID));
    }
    
    // Fallback to a simple URL
    return home_url('/task-detail/?task_id=' . $task_id);
}

// Helper function to get task image
function get_task_image($task) {
    // Check task_image field first (this is what the admin saves to)
    if (!empty($task->task_image)) {
        // If it's a URL, return it directly
        if (filter_var($task->task_image, FILTER_VALIDATE_URL)) {
            return $task->task_image;
        }
        // If it's a relative path, make it absolute
        if (strpos($task->task_image, '/') === 0 || strpos($task->task_image, 'assets/') === 0) {
            return INDOOR_TASKS_URL . ltrim($task->task_image, '/');
        }
    }
    
    // Check if task has an image field (alternative field name)
    if (!empty($task->image)) {
        // If it's a URL, return it directly
        if (filter_var($task->image, FILTER_VALIDATE_URL)) {
            return $task->image;
        }
        // If it's an attachment ID, get the URL
        if (is_numeric($task->image)) {
            $attachment_url = wp_get_attachment_url($task->image);
            if ($attachment_url) {
                return $attachment_url;
            }
        }
        // If it's a relative path, make it absolute
        if (strpos($task->image, '/') === 0 || strpos($task->image, 'assets/') === 0) {
            return INDOOR_TASKS_URL . ltrim($task->image, '/');
        }
    }
    
    // Check if task has a featured image or attachment
    if (!empty($task->featured_image)) {
        return $task->featured_image;
    }
    
    // Check if task has an attachment_id
    if (!empty($task->attachment_id)) {
        $attachment_url = wp_get_attachment_url($task->attachment_id);
        if ($attachment_url) {
            return $attachment_url;
        }
    }
    
    // If we have a category color, create a nice SVG
    if (!empty($task->category_color)) {
        $first_letter = strtoupper(substr($task->title ?: 'T', 0, 1));
        return 'data:image/svg+xml;base64,' . base64_encode('
            <svg width="120" height="120" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:' . $task->category_color . ';stop-opacity:1" />
                        <stop offset="100%" style="stop-color:' . $task->category_color . '88;stop-opacity:1" />
                    </linearGradient>
                </defs>
                <rect width="120" height="120" fill="url(#grad1)" rx="12"/>
                <text x="60" y="75" font-family="Arial, sans-serif" font-size="48" font-weight="bold" fill="white" text-anchor="middle">
                    ' . $first_letter . '
                </text>
            </svg>
        ');
    }
    
    // Default task placeholder - always use fallback SVG for consistency
    return 'data:image/svg+xml;base64,' . base64_encode('
        <svg width="120" height="120" xmlns="http://www.w3.org/2000/svg">
            <rect width="120" height="120" fill="#e5e7eb" rx="12"/>
            <g transform="translate(35, 35)">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" 
                      fill="#9ca3af" stroke="none"/>
            </g>
        </svg>
    ');
}

// Helper function to get status badge class
function get_status_badge_class($status) {
    switch ($status) {
        case 'approved': return 'tkm-status-approved';
        case 'pending': return 'tkm-status-pending';
        case 'rejected': return 'tkm-status-rejected';
        case null: return 'tkm-status-available';
        default: return 'tkm-status-available';
    }
}

// Helper function to get status text
function get_status_text($status) {
    switch ($status) {
        case 'approved': return 'Approved';
        case 'pending': return 'Pending';
        case 'rejected': return 'Rejected';
        case null: return 'Available';
        default: return 'Available';
    }
}

// Helper function to get difficulty badge class
function get_difficulty_class($difficulty) {
    switch ($difficulty) {
        case 'easy': return 'tkm-difficulty-easy';
        case 'medium': return 'tkm-difficulty-medium';
        case 'hard': return 'tkm-difficulty-hard';
        default: return 'tkm-difficulty-easy';
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <title><?php echo wp_get_document_title(); ?></title>
    
    <?php wp_head(); ?>
    
    <!-- TKM Door Template Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-templates.css?ver=1.0.0">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .tkm-archive-container {
            display: flex;
            min-height: 100vh;
        }
        
        .tkm-archive-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .tkm-archive-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
        
        .tkm-page-header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .tkm-page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }
        
        .tkm-page-subtitle {
            color: #6b7280;
            font-size: 1rem;
        }
        
        .tkm-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .tkm-stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .tkm-stat-icon {
            width: 48px;
            height: 48px;
            background: #00954b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
        }
        
        .tkm-stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }
        
        .tkm-stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .tkm-filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .tkm-filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .tkm-filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .tkm-filter-label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .tkm-filter-select {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            color: #374151;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }
        
        .tkm-filter-select:focus {
            outline: none;
            border-color: #00954b;
        }
        
        .tkm-tasks-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tkm-section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .tkm-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a202c;
        }
        
        .tkm-task-card {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
        }
        
        .tkm-task-card:hover {
            background: #f9fafb;
        }
        
        .tkm-task-card:last-child {
            border-bottom: none;
        }
        
        .tkm-task-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .tkm-task-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .tkm-task-image svg {
            width: 32px;
            height: 32px;
            color: #6b7280;
        }
        
        .tkm-task-info {
            flex: 1;
            min-width: 0;
        }
        
        .tkm-task-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .tkm-task-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.25rem;
        }
        
        .tkm-task-category {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            background: #e5e7eb;
            color: #374151;
        }
        
        .tkm-task-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 0.75rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .tkm-task-meta-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .tkm-task-meta-item svg {
            width: 14px;
            height: 14px;
        }
        
        .tkm-task-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }
        
        .tkm-task-points {
            font-size: 1.25rem;
            font-weight: 700;
            color: #00954b;
        }
        
        .tkm-status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .tkm-status-approved {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .tkm-status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .tkm-status-rejected {
            background: #fecaca;
            color: #dc2626;
        }
        
        .tkm-status-available {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .tkm-difficulty-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .tkm-difficulty-easy {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .tkm-difficulty-medium {
            background: #fef3c7;
            color: #d97706;
        }
        
        .tkm-difficulty-hard {
            background: #fecaca;
            color: #dc2626;
        }
        
        .tkm-no-tasks {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        
        .tkm-no-tasks svg {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            color: #d1d5db;
        }
        
        .tkm-no-tasks h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .tkm-task-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .tkm-task-image {
                width: 60px;
                height: 60px;
                margin-right: 0;
            }
            
            .tkm-task-actions {
                align-self: stretch;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .tkm-filters-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="tkm-archive-container">
        <!-- Include Sidebar Navigation -->
        <?php include INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'; ?>
        
        <div class="tkm-archive-content">
            <!-- Page Header -->
            <div class="tkm-page-header">
                <h1 class="tkm-page-title">Available Tasks</h1>
                <p class="tkm-page-subtitle">Browse all tasks and track your progress</p>
            </div>
            
            <!-- Stats Summary -->
            <div class="tkm-stats-grid">
                <div class="tkm-stat-card">
                    <div class="tkm-stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="tkm-stat-value"><?php echo number_format($stats['completed_today']); ?></div>
                    <div class="tkm-stat-label">Completed Today</div>
                </div>
                
                <div class="tkm-stat-card">
                    <div class="tkm-stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                    </div>
                    <div class="tkm-stat-value"><?php echo number_format($stats['pending_submissions']); ?></div>
                    <div class="tkm-stat-label">Pending Submissions</div>
                </div>
                
                <div class="tkm-stat-card">
                    <div class="tkm-stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 11 12 14 22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                    </div>
                    <div class="tkm-stat-value"><?php echo number_format($stats['total_completed']); ?></div>
                    <div class="tkm-stat-label">Total Completed</div>
                </div>
                
                <div class="tkm-stat-card">
                    <div class="tkm-stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="15" y1="9" x2="9" y2="15"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                    </div>
                    <div class="tkm-stat-value"><?php echo number_format($stats['total_rejected']); ?></div>
                    <div class="tkm-stat-label">Total Rejected</div>
                </div>
            </div>
            
            <!-- Filters Section -->
            <div class="tkm-filters-section">
                <form method="GET" action="">
                    <div class="tkm-filters-grid">
                        <div class="tkm-filter-group">
                            <label class="tkm-filter-label">Category Filter</label>
                            <select name="category" class="tkm-filter-select" onchange="this.form.submit()">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category->id; ?>" <?php selected($category_filter, $category->id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="tkm-filter-group">
                            <label class="tkm-filter-label">Sort By</label>
                            <select name="sort" class="tkm-filter-select" onchange="this.form.submit()">
                                <option value="newest" <?php selected($sort_by, 'newest'); ?>>Newest</option>
                                <option value="oldest" <?php selected($sort_by, 'oldest'); ?>>Oldest</option>
                                <option value="highest_points" <?php selected($sort_by, 'highest_points'); ?>>Highest Points</option>
                            </select>
                        </div>
                        
                        <div class="tkm-filter-group">
                            <label class="tkm-filter-label">Status</label>
                            <select name="status" class="tkm-filter-select" onchange="this.form.submit()">
                                <option value="all" <?php selected($status_filter, 'all'); ?>>All Status</option>
                                <option value="available" <?php selected($status_filter, 'available'); ?>>Available</option>
                                <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                                <option value="approved" <?php selected($status_filter, 'approved'); ?>>Approved</option>
                                <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>Rejected</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Tasks List -->
            <div class="tkm-tasks-section">
                <div class="tkm-section-header">
                    <h2 class="tkm-section-title">Available Tasks</h2>
                </div>
                
                <?php if (!empty($user_tasks)): ?>
                    <?php foreach ($user_tasks as $task): ?>
                        <div class="tkm-task-card" onclick="window.location.href='<?php echo esc_url(get_task_detail_url($task->id)); ?>'" style="cursor: pointer;">
                            <div class="tkm-task-image">
                                <img src="<?php echo esc_url(get_task_image($task)); ?>" alt="<?php echo esc_attr($task->title); ?>" loading="lazy" />
                            </div>
                            
                            <div class="tkm-task-info">
                                <div class="tkm-task-header">
                                    <div>
                                        <h3 class="tkm-task-title">
                                            <a href="<?php echo esc_url(get_task_detail_url($task->id)); ?>" class="tkm-task-link">
                                                <?php echo esc_html($task->title ?: 'Task #' . $task->id); ?>
                                            </a>
                                        </h3>
                                        <?php if (!empty($task->category_name)): ?>
                                            <span class="tkm-task-category" style="background-color: <?php echo esc_attr($task->category_color ?: '#e5e7eb'); ?>22; color: <?php echo esc_attr($task->category_color ?: '#374151'); ?>;">
                                                <?php echo esc_html($task->category_name); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($task->short_description)): ?>
                                <div class="tkm-task-description">
                                    <?php echo esc_html($task->short_description); ?>
                                </div>
                                <?php elseif (!empty($task->description)): ?>
                                <div class="tkm-task-description">
                                    <?php echo esc_html(wp_trim_words($task->description, 15, '...')); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="tkm-task-meta">
                                    <div class="tkm-task-meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        <?php echo esc_html($task->estimated_time ?: '30 mins'); ?>
                                    </div>
                                                     <div class="tkm-task-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <?php if (!empty($task->deadline)): ?>
                            Deadline: <?php echo date('M j, Y', strtotime($task->deadline)); ?>
                        <?php elseif (!empty($task->due_date)): ?>
                            Deadline: <?php echo date('M j, Y', strtotime($task->due_date)); ?>
                        <?php else: ?>
                            No Deadline
                        <?php endif; ?>
                    </div>
                                    
                                    <?php if ($task->difficulty): ?>
                                        <span class="tkm-difficulty-badge <?php echo get_difficulty_class($task->difficulty); ?>">
                                            <?php echo ucfirst($task->difficulty); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="tkm-task-actions">
                                <div class="tkm-task-points">
                                    +<?php 
                                        if (isset($task->points_awarded) && $task->points_awarded > 0) {
                                            echo number_format($task->points_awarded);
                                        } else {
                                            echo number_format($task->points);
                                        }
                                    ?> pts
                                </div>
                                <span class="tkm-status-badge <?php echo get_status_badge_class($task->submission_status); ?>">
                                    <?php echo get_status_text($task->submission_status); ?>
                                </span>
                                <?php if ($task->submission_status === null): ?>
                                    <a href="<?php echo esc_url(get_task_detail_url($task->id)); ?>" class="tkm-start-btn" onclick="event.stopPropagation();">
                                        Start Task
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="tkm-no-tasks">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                        </svg>
                        <h3>No Tasks Found</h3>
                        <p>No tasks match your current filter criteria. Try adjusting the filters above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php wp_footer(); ?>
    
    <!-- TKM Door Template Scripts -->
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-templates.js?ver=1.0.0"></script>
</body>
</html>
