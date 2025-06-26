<?php
/**
 * Helper functions for Indoor Tasks plugin
 */

/**
 * Safely create or get a page for Indoor Tasks templates
 * 
 * @param string $slug The slug for the page
 * @param string $title The title for the page
 * @param string $template The template path for the page
 * @param string $content Optional content for the page
 * @return int|WP_Error|null Page ID if successful, WP_Error on error, null if WordPress not ready
 */
function indoor_tasks_create_or_get_page($slug, $title, $template, $content = '') {
    // Make sure WordPress is fully initialized
    if (!did_action('init')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Indoor Tasks: WordPress init not completed. Aborting page creation for $slug.");
        }
        return null;
    }
    
    // Verify essential WordPress functions exist
    if (!function_exists('wp_insert_post') || !function_exists('get_permalink') || !function_exists('get_pages')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Indoor Tasks: Essential WordPress functions missing. Aborting page creation for $slug.");
        }
        return null;
    }
    
    // Check that the globals we need are available
    global $wpdb, $wp_rewrite;
    if (!isset($wpdb) || !isset($wp_rewrite)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Indoor Tasks: WordPress globals not available. Aborting page creation for $slug.");
        }
        return null;
    }
    
    try {
        // First try to find by template
        $existing_page = get_pages(array(
            'meta_key' => '_wp_page_template',
            'meta_value' => $template,
            'number' => 1,
            'post_status' => array('publish', 'draft', 'pending')
        ));
        
        if (!empty($existing_page)) {
            // Ensure the page is published
            if ($existing_page[0]->post_status !== 'publish') {
                wp_update_post(array(
                    'ID' => $existing_page[0]->ID,
                    'post_status' => 'publish'
                ));
            }
            return $existing_page[0]->ID;
        }
        
        // Next try to find by slug
        $page_by_slug = get_page_by_path($slug);
        if ($page_by_slug) {
            // Update template of existing page
            update_post_meta($page_by_slug->ID, '_wp_page_template', $template);
            
            // Ensure the page is published
            if ($page_by_slug->post_status !== 'publish') {
                wp_update_post(array(
                    'ID' => $page_by_slug->ID,
                    'post_status' => 'publish'
                ));
            }
            
            return $page_by_slug->ID;
        }
        
        // Create the page if it doesn't exist
        $page_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => $slug,
            'comment_status' => 'closed',
        ));
        
        if (!is_wp_error($page_id) && $page_id > 0) {
            update_post_meta($page_id, '_wp_page_template', $template);
            return $page_id;
        }
        
        return $page_id; // Return the error
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Indoor Tasks: Exception in create_or_get_page for $slug: " . $e->getMessage());
        }
        return new WP_Error('page_creation_failed', $e->getMessage());
    }
}

/**
 * Get a page by template, with fallback to slug
 * 
 * @param string $template Template path/name to search for
 * @param string $slug Slug to check as fallback
 * @return WP_Post|null Page object if found, null otherwise
 */
function indoor_tasks_get_page_by_template($template, $slug = '') {
    // Check if WordPress core functions are available
    if (!function_exists('get_pages') || !function_exists('get_page_by_path')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Indoor Tasks: WordPress functions not available in get_page_by_template");
        }
        return null;
    }
    
    try {
        // First try to find by template
        $pages = get_pages(array(
            'meta_key' => '_wp_page_template',
            'meta_value' => $template,
            'number' => 1,
            'post_status' => array('publish', 'draft', 'pending')
        ));
        
        if (!empty($pages)) {
            return $pages[0];
        }
        
        // If slug is provided, try to find by slug
        if (!empty($slug)) {
            $page = get_page_by_path($slug);
            if ($page) {
                return $page;
            }
        }
        
        return null;
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Indoor Tasks: Exception in get_page_by_template: " . $e->getMessage());
        }
        return null;
    }
}

/**
 * Get the URL for a specific Indoor Tasks page type
 * 
 * @param string $page_type The page type ('login', 'dashboard', 'tasks', etc.)
 * @param array $args Optional query args to add to the URL
 * @return string The URL for the page
 */
function indoor_tasks_get_page_url($page_type, $args = array()) {
    $template = '';
    $slug = '';
    
    switch ($page_type) {
        case 'login':
        case 'auth':
            $template = 'indoor-tasks/templates/tk-indoor-auth.php';
            $slug = 'login';
            break;
        case 'dashboard':
            $template = 'indoor-tasks/templates/tk-indoor-dashboard.php';
            $slug = 'dashboard';
            break;
        case 'profile':
            $template = 'indoor-tasks/templates/tk-indoor-profile.php';
            $slug = 'profile';
            break;
        case 'tasks':
        case 'task-list':
            $template = 'indoor-tasks/templates/tk-indoor-tasks.php';
            $slug = 'tasks';
            break;
        case 'task-detail':
            $template = 'indoor-tasks/templates/tk-indoor-task-detail.php';
            $slug = 'task-detail';
            break;
        case 'notifications':
            $template = 'indoor-tasks/templates/tk-indoor-notifications.php';
            $slug = 'notifications';
            break;
        case 'announcements':
            $template = 'indoor-tasks/templates/tk-indoor-announcements.php';
            $slug = 'announcements';
            break;
        case 'wallet':
            $template = 'indoor-tasks/templates/tk-indoor-wallet.php';
            $slug = 'wallet';
            break;
        case 'withdrawal':
        case 'withdraw':
            $template = 'indoor-tasks/templates/tk-indoor-withdraw.php';
            $slug = 'withdrawal';
            break;
        case 'kyc':
        case 'verification':
            $template = 'indoor-tasks/templates/tk-indoor-kyc.php';
            $slug = 'kyc';
            break;
        case 'referrals':
            $template = 'indoor-tasks/templates/tk-indoor-referrals.php';
            $slug = 'referrals';
            break;
        case 'leaderboard':
            $template = 'indoor-tasks/templates/tk-indoor-leaderboard.php';
            $slug = 'leaderboard';
            break;
        case 'help':
        case 'support':
            $template = 'indoor-tasks/templates/tk-indoor-help.php';
            $slug = 'help';
            break;
        case 'kyc':
            $template = 'indoor-tasks/templates/modern-kyc.php';
            $slug = 'kyc';
            break;
        case 'tutorials':
            $template = 'indoor-tasks/templates/modern-tutorials.php';
            $slug = 'tutorials';
            break;
        case 'referrals':
            $template = 'indoor-tasks/templates/modern-referrals.php';
            $slug = 'referrals';
            break;
        case 'announcements':
            $template = 'indoor-tasks/templates/modern-announcements.php';
            $slug = 'announcements';
            break;
        case 'leaderboard':
            $template = 'indoor-tasks/templates/modern-leaderboard.php';
            $slug = 'leaderboard';
            break;
        case 'help-desk':
        case 'help':
        case 'support':
            $template = 'indoor-tasks/templates/modern-help-desk.php';
            $slug = 'help-desk';
            break;
        case 'tasks':
        case 'task-list':
            $template = 'indoor-tasks/templates/modern-task-list.php';
            $slug = 'tasks';
            break;
        case 'task-detail':
            $template = 'indoor-tasks/templates/modern-task-detail.php';
            $slug = 'task-detail';
            // Ensure consistent parameter naming
            if (isset($args['task_id'])) {
                $args['id'] = $args['task_id'];
                unset($args['task_id']);
            }
            break;
        case 'debug':
            $template = 'indoor-tasks/templates/debug.php';
            $slug = 'debug';
            break;
        case 'wallet':
            $template = 'indoor-tasks/templates/wallet.php';
            $slug = 'wallet';
            break;
        case 'withdraw':
        case 'withdrawal':
            $template = 'indoor-tasks/templates/withdrawal.php';
            $slug = 'withdraw';
            break;
        case 'kyc':
            $template = 'indoor-tasks/templates/kyc.php';
            $slug = 'kyc';
            break;
        case 'debug':
            $template = 'indoor-tasks/templates/debug.php';
            $slug = 'debug';
            break;
    }
    
    // Make sure WordPress is ready before using functions
    if (!function_exists('get_permalink') || !function_exists('home_url') || !function_exists('add_query_arg')) {
        // Emergency fallback
        return home_url('/' . $slug . '/');
    }
    
    // Get the page
    $page = indoor_tasks_get_page_by_template($template, $slug);
    
    if ($page && isset($page->ID)) {
        try {
            $url = get_permalink($page->ID);
            if (!$url || is_wp_error($url)) {
                // Fallback if permalink function fails
                $url = home_url('/' . $slug . '/');
            }
        } catch (Exception $e) {
            // Exception fallback
            $url = home_url('/' . $slug . '/');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Indoor Tasks: Error getting permalink for $page_type: " . $e->getMessage());
            }
        }
    } else {
        // Fallback to home URL with slug
        $url = home_url('/' . $slug . '/');
    }
    
    // Add query args if provided
    if (!empty($args)) {
        $url = add_query_arg($args, $url);
    }
    
    return $url;
}

/**
 * Redirect to a specific Indoor Tasks page
 * 
 * @param string $page_type The page type to redirect to
 * @param array $args Optional query args to add to the URL
 */
function indoor_tasks_redirect_to_page($page_type, $args = array()) {
    $url = indoor_tasks_get_page_url($page_type, $args);
    wp_redirect($url);
    exit;
}

/**
 * Check if current page is a specific Indoor Tasks page type
 * 
 * @param string $page_type The page type to check
 * @return bool True if current page matches the specified type
 */
function indoor_tasks_is_page($page_type) {
    switch ($page_type) {
        case 'login':
        case 'auth':
            return is_indoor_tasks_template('tk-indoor-auth.php');
        case 'dashboard':
            return is_indoor_tasks_template('tk-indoor-dashboard.php');
        case 'profile':
            return is_indoor_tasks_template('tk-indoor-profile.php');
        case 'tasks':
        case 'task-list':
            return is_indoor_tasks_template('tk-indoor-tasks.php');
        case 'task-detail':
            return is_indoor_tasks_template('tk-indoor-task-detail.php');
        case 'wallet':
            return is_indoor_tasks_template('tk-indoor-wallet.php');
        case 'withdraw':
        case 'withdrawal':
            return is_indoor_tasks_template('tk-indoor-withdraw.php');
        case 'kyc':
        case 'verification':
            return is_indoor_tasks_template('tk-indoor-kyc.php');
        case 'referrals':
            return is_indoor_tasks_template('tk-indoor-referrals.php');
        case 'leaderboard':
            return is_indoor_tasks_template('tk-indoor-leaderboard.php');
        case 'help':
        case 'support':
            return is_indoor_tasks_template('tk-indoor-help.php');
        case 'announcements':
            return is_indoor_tasks_template('tk-indoor-announcements.php');
        case 'notifications':
            return is_indoor_tasks_template('tk-indoor-notifications.php');
    }
    
    return false;
}

/**
 * Check if current page is any Indoor Tasks page (without specifying page type)
 * 
 * @return bool True if current page is an Indoor Tasks page
 */
function indoor_tasks_is_any_page() {
    // Check if we have the global template indicator
    if (isset($GLOBALS['indoor_tasks_current_template'])) {
        return true;
    }
    
    // Check current page's template metadata
    global $post;
    if (is_object($post)) {
        $current_template = get_post_meta($post->ID, '_wp_page_template', true);
        
        // Check if template is from our plugin
        if (strpos($current_template, 'indoor-tasks/templates/') === 0) {
            return true;
        }
    }
    
    // Check if current page has Indoor Tasks related content
    if (is_page()) {
        $indoor_tasks_templates = [
            'tk-indoor-auth.php',
            'tk-indoor-dashboard.php', 
            'tk-indoor-tasks.php',
            'tk-indoor-task-detail.php',
            'tk-indoor-wallet.php',
            'tk-indoor-withdraw.php',
            'tk-indoor-kyc.php',
            'tk-indoor-referrals.php',
            'tk-indoor-profile.php',
            'tk-indoor-leaderboard.php',
            'tk-indoor-help.php',
            'tk-indoor-notifications.php',
            'tk-indoor-announcements.php'
        ];
        
        foreach ($indoor_tasks_templates as $template) {
            if (is_indoor_tasks_template($template)) {
                return true;
            }
        }
    }
    
    return false;
}
