<?php
// Handle activity export
if (isset($_POST['action']) && $_POST['action'] === 'export_activities') {
    if (!wp_verify_nonce($_POST['export_nonce'], 'indoor_tasks_export_activities')) {
        wp_die('Invalid nonce');
    }
    
    $args = array(
        'start_date' => isset($_POST['start_date']) ? $_POST['start_date'] . ' 00:00:00' : '',
        'end_date' => isset($_POST['end_date']) ? $_POST['end_date'] . ' 23:59:59' : '',
        'activity_type' => isset($_POST['export_activity_type']) ? sanitize_text_field($_POST['export_activity_type']) : ''
    );
    
    $csv_content = indoor_tasks_export_activities($args);
    
    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="user-activities-' . date('Y-m-d') . '.csv"');
    
    echo $csv_content;
    exit;
}
