<?php
/**
 * KYC Upload API Handler
 * Indoor Tasks Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle KYC document upload
add_action('wp_ajax_indoor_tasks_kyc_upload', 'handle_kyc_upload');
add_action('wp_ajax_nopriv_indoor_tasks_kyc_upload', function() {
    wp_send_json_error(['message' => 'Login required.']);
});

// Handle KYC history request
add_action('wp_ajax_get_kyc_history', 'get_kyc_history');

function handle_kyc_upload() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'indoor_tasks_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'User not logged in.']);
    }

    // Get form data
    $kyc_level = intval($_POST['kyc_level'] ?? 1);
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['last_name'] ?? '');
    $date_of_birth = sanitize_text_field($_POST['date_of_birth'] ?? '');
    $nationality = sanitize_text_field($_POST['nationality'] ?? '');
    $address_line1 = sanitize_text_field($_POST['address_line1'] ?? '');
    $address_line2 = sanitize_text_field($_POST['address_line2'] ?? '');
    $city = sanitize_text_field($_POST['city'] ?? '');
    $postal_code = sanitize_text_field($_POST['postal_code'] ?? '');

    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'date_of_birth', 'nationality'];
    if ($kyc_level >= 2) {
        $required_fields = array_merge($required_fields, ['address_line1', 'city', 'postal_code']);
    }

    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error(['message' => "Field '{$field}' is required."]);
        }
    }

    // Validate age (must be 18+)
    $birth_date = new DateTime($date_of_birth);
    $age = $birth_date->diff(new DateTime())->y;
    if ($age < 18) {
        wp_send_json_error(['message' => 'You must be 18 or older to complete verification.']);
    }

    // Handle file uploads
    $required_documents = get_required_documents($kyc_level);
    $uploaded_documents = [];
    $upload_errors = [];

    foreach ($required_documents as $doc_type) {
        if (!isset($_FILES[$doc_type]) || $_FILES[$doc_type]['error'] !== UPLOAD_ERR_OK) {
            $upload_errors[] = "Document '{$doc_type}' is required.";
            continue;
        }

        $file = $_FILES[$doc_type];
        
        // Validate file
        $validation = validate_kyc_file($file, $doc_type);
        if (!$validation['valid']) {
            $upload_errors[] = $validation['message'];
            continue;
        }

        // Upload file
        $upload_result = upload_kyc_document($file, $user_id, $doc_type);
        if ($upload_result['success']) {
            $uploaded_documents[$doc_type] = $upload_result['file_path'];
        } else {
            $upload_errors[] = $upload_result['message'];
        }
    }

    if (!empty($upload_errors)) {
        wp_send_json_error(['message' => implode(' ', $upload_errors)]);
    }

    // Save KYC data
    $kyc_data = [
        'level' => $kyc_level,
        'personal_info' => [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'date_of_birth' => $date_of_birth,
            'nationality' => $nationality
        ],
        'address_info' => [
            'address_line1' => $address_line1,
            'address_line2' => $address_line2,
            'city' => $city,
            'postal_code' => $postal_code
        ],
        'documents' => $uploaded_documents,
        'submitted_date' => current_time('mysql'),
        'status' => 'pending'
    ];

    // Update user meta
    update_user_meta($user_id, 'kyc_data', $kyc_data);
    update_user_meta($user_id, 'kyc_status', 'pending');
    update_user_meta($user_id, 'kyc_level_pending', $kyc_level);
    update_user_meta($user_id, 'kyc_submitted_date', current_time('mysql'));
    
    // Clear any previous rejection reason
    delete_user_meta($user_id, 'kyc_rejection_reason');

    // Add to KYC history
    add_kyc_history_entry($user_id, 'KYC Submitted', "Level {$kyc_level} verification documents submitted for review.");

    // Send notification to admin (you can implement this based on your notification system)
    do_action('indoor_tasks_kyc_submitted', $user_id, $kyc_level);

    wp_send_json_success([
        'message' => 'KYC documents submitted successfully. Review typically takes 1-3 business days.',
        'status' => 'pending',
        'level' => $kyc_level
    ]);
}

function get_required_documents($level) {
    $requirements = [
        1 => ['id_front', 'selfie'],
        2 => ['id_front', 'id_back', 'selfie', 'address_proof'],
        3 => ['id_front', 'id_back', 'selfie', 'address_proof', 'income_proof']
    ];

    return $requirements[$level] ?? [];
}

function validate_kyc_file($file, $doc_type) {
    $max_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = [
        'selfie' => ['image/jpeg', 'image/png'],
        'id_front' => ['image/jpeg', 'image/png', 'application/pdf'],
        'id_back' => ['image/jpeg', 'image/png', 'application/pdf'],
        'address_proof' => ['image/jpeg', 'image/png', 'application/pdf'],
        'income_proof' => ['image/jpeg', 'image/png', 'application/pdf']
    ];

    if ($file['size'] > $max_size) {
        return [
            'valid' => false,
            'message' => "File size for {$doc_type} must be less than 5MB."
        ];
    }

    if (!isset($allowed_types[$doc_type]) || !in_array($file['type'], $allowed_types[$doc_type])) {
        return [
            'valid' => false,
            'message' => "Invalid file type for {$doc_type}. Please upload JPG, PNG, or PDF files only."
        ];
    }

    return ['valid' => true];
}

function upload_kyc_document($file, $user_id, $doc_type) {
    // Create KYC upload directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $kyc_dir = $upload_dir['basedir'] . '/kyc-documents/' . $user_id;
    
    if (!file_exists($kyc_dir)) {
        wp_mkdir_p($kyc_dir);
        
        // Add .htaccess to protect files
        $htaccess_content = "Order deny,allow\nDeny from all";
        file_put_contents($kyc_dir . '/.htaccess', $htaccess_content);
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $doc_type . '_' . time() . '_' . wp_generate_password(8, false) . '.' . $file_extension;
    $file_path = $kyc_dir . '/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return [
            'success' => true,
            'file_path' => $file_path,
            'filename' => $filename
        ];
    } else {
        return [
            'success' => false,
            'message' => "Failed to upload {$doc_type} document."
        ];
    }
}

function add_kyc_history_entry($user_id, $title, $description) {
    $history = get_user_meta($user_id, 'kyc_history', true) ?: [];
    
    $entry = [
        'date' => current_time('mysql'),
        'title' => $title,
        'description' => $description
    ];
    
    array_unshift($history, $entry);
    
    // Keep only last 10 entries
    $history = array_slice($history, 0, 10);
    
    update_user_meta($user_id, 'kyc_history', $history);
}

function get_kyc_history() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'indoor_tasks_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'User not logged in.']);
    }

    $history = get_user_meta($user_id, 'kyc_history', true) ?: [];
    
    wp_send_json_success($history);
}
