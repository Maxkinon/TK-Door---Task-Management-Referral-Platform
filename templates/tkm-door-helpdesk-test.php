<?php
/**
 * Template Name: TKM Door - Help Desk Test
 * Description: Simple test template for troubleshooting
 * Version: 1.0.0
 */

// Prevent direct file access
defined('ABSPATH') || exit;

echo '<h1>Help Desk Test Template</h1>';
echo '<p>This is a simple test to verify the template loading works.</p>';
echo '<p>Current user: ' . (is_user_logged_in() ? wp_get_current_user()->display_name : 'Not logged in') . '</p>';
echo '<p>Template file: ' . __FILE__ . '</p>';
echo '<p>Plugin URL: ' . (defined('INDOOR_TASKS_URL') ? INDOOR_TASKS_URL : 'Not defined') . '</p>';

// Check if CSS file exists
$css_file = plugin_dir_path(__FILE__) . '../assets/css/tkm-door-helpdesk.css';
echo '<p>CSS file exists: ' . (file_exists($css_file) ? 'Yes' : 'No') . ' - ' . $css_file . '</p>';

// Check if JS file exists
$js_file = plugin_dir_path(__FILE__) . '../assets/js/tkm-door-helpdesk.js';
echo '<p>JS file exists: ' . (file_exists($js_file) ? 'Yes' : 'No') . ' - ' . $js_file . '</p>';

// Check if sidebar exists
$sidebar_file = plugin_dir_path(__FILE__) . 'parts/sidebar-nav.php';
echo '<p>Sidebar file exists: ' . (file_exists($sidebar_file) ? 'Yes' : 'No') . ' - ' . $sidebar_file . '</p>';
?>
