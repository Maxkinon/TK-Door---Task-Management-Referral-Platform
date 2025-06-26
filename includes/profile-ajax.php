<?php
/**
 * Ajax handler for user profile updates
 */

// Register AJAX handlers
add_action('wp_ajax_indoor_tasks_update_profile', 'indoor_tasks_ajax_update_profile');
add_action('wp_ajax_indoor_tasks_update_avatar', 'indoor_tasks_ajax_update_avatar');

/**
 * AJAX handler for updating user profile
 */
function indoor_tasks_ajax_update_profile() {
    // Check nonce
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'indoor-tasks-profile-nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    // Collect profile data
    $profile_data = [
        'first_name' => isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '',
        'last_name' => isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '',
        'display_name' => isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '',
        'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
        'phone_number' => isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '',
        'address' => isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '',
        'bio' => isset($_POST['bio']) ? sanitize_textarea_field($_POST['bio']) : '',
        'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
    ];
    
    // Update profile
    $result = indoor_tasks_update_user_profile($profile_data);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    } else {
        // Get updated profile data
        $updated_profile = indoor_tasks_get_user_profile();
        
        wp_send_json_success([
            'message' => 'Profile updated successfully.',
            'profile' => $updated_profile
        ]);
    }
}

/**
 * AJAX handler for updating user avatar
 */
function indoor_tasks_ajax_update_avatar() {
    // Check nonce
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'indoor-tasks-profile-nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    // Check if file is uploaded
    if (!isset($_FILES['avatar']) || !is_uploaded_file($_FILES['avatar']['tmp_name'])) {
        wp_send_json_error(['message' => 'No avatar file uploaded.']);
    }
    
    // Get user ID
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'User not logged in.']);
    }
    
    // Check file type
    $file = $_FILES['avatar'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    
    if (!in_array($file['type'], $allowed_types)) {
        wp_send_json_error(['message' => 'Invalid file type. Please upload a JPG, PNG or GIF image.']);
    }
    
    // Check file size (max 2MB)
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        wp_send_json_error(['message' => 'File too large. Maximum size is 2MB.']);
    }
    
    // Handle the upload using WordPress functions
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    $upload_overrides = ['test_form' => false];
    $uploaded_file = wp_handle_upload($file, $upload_overrides);
    
    if (isset($uploaded_file['error'])) {
        wp_send_json_error(['message' => $uploaded_file['error']]);
    }
    
    // Save avatar URL to user meta
    update_user_meta($user_id, 'indoor_tasks_avatar', $uploaded_file['url']);
    
    // Return success with URL
    wp_send_json_success([
        'message' => 'Avatar updated successfully.',
        'avatar_url' => $uploaded_file['url']
    ]);
}

/**
 * Filter to use custom avatar if available
 */
function indoor_tasks_custom_avatar($avatar, $id_or_email, $size, $default, $alt) {
    // Get user ID
    $user_id = 0;
    if (is_numeric($id_or_email)) {
        $user_id = (int) $id_or_email;
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        if ($user) {
            $user_id = $user->ID;
        }
    } elseif (is_object($id_or_email)) {
        if (!empty($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif (!empty($id_or_email->comment_author_email)) {
            $user = get_user_by('email', $id_or_email->comment_author_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
    }
    
    if ($user_id) {
        $custom_avatar = get_user_meta($user_id, 'indoor_tasks_avatar', true);
        
        if (!empty($custom_avatar)) {
            $avatar = "<img alt='{$alt}' src='{$custom_avatar}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
        }
    }
    
    return $avatar;
}
add_filter('get_avatar', 'indoor_tasks_custom_avatar', 10, 5);
