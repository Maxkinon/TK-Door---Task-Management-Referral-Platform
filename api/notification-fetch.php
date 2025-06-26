<?php
// AJAX endpoint for fetching notifications
add_action('wp_ajax_indoor_tasks_fetch_notifications', function() {
    // Fetch notifications for user
    wp_send_json_success(['notifications' => []]);
});
add_action('wp_ajax_nopriv_indoor_tasks_fetch_notifications', function() {
    wp_send_json_error(['message' => 'Login required.']);
});
