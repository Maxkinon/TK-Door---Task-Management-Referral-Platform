<?php
// AJAX endpoint for withdrawal request
add_action('wp_ajax_indoor_tasks_withdraw_request', function() {
    // Validate, save request, set status to pending
    wp_send_json_success(['message' => 'Withdrawal request submitted.']);
});
add_action('wp_ajax_nopriv_indoor_tasks_withdraw_request', function() {
    wp_send_json_error(['message' => 'Login required.']);
});
