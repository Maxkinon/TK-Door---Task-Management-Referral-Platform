<?php
/**
 * Template Name: TKM Door - KYC Verification
 * Description: Modern KYC verification template for document submission and status tracking
 * Version: 1.0.0
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
$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Get database references
global $wpdb;

// Handle KYC form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'submit_kyc') {
    if (wp_verify_nonce($_POST['nonce'], 'tkm_kyc_nonce')) {
        $full_name = sanitize_text_field($_POST['full_name']);
        $date_of_birth = sanitize_text_field($_POST['date_of_birth']);
        $country = sanitize_text_field($_POST['country']);
        $phone_country = sanitize_text_field($_POST['phone_country']);
        $phone = sanitize_text_field($_POST['phone']);
        $gender = sanitize_text_field($_POST['gender']);
        $postal_code = sanitize_text_field($_POST['postal_code']);
        $address = sanitize_textarea_field($_POST['address']);
        $city = sanitize_text_field($_POST['city']);
        $state = sanitize_text_field($_POST['state']);
        
        $errors = array();
        $uploaded_files = array();
        
        // Validation
        if (empty($full_name)) {
            $errors[] = 'Full name is required.';
        }
        
        if (empty($date_of_birth)) {
            $errors[] = 'Date of birth is required.';
        }
        
        if (empty($country)) {
            $errors[] = 'Country is required.';
        }
        
        if (empty($phone)) {
            $errors[] = 'Phone number is required.';
        }
        
        if (empty($gender)) {
            $errors[] = 'Gender is required.';
        }
        
        if (empty($postal_code)) {
            $errors[] = 'Postal code is required.';
        }
        
        if (empty($address)) {
            $errors[] = 'Address is required.';
        }
        
        if (empty($city)) {
            $errors[] = 'City is required.';
        }
        
        if (empty($state)) {
            $errors[] = 'State is required.';
        }
        
        // Handle file uploads
        if (!empty($_FILES['front_ic']['name'])) {
            $front_ic_upload = tkm_handle_kyc_file_upload('front_ic');
            if (is_wp_error($front_ic_upload)) {
                $errors[] = 'Front IC upload error: ' . $front_ic_upload->get_error_message();
            } else {
                $uploaded_files['front_ic'] = $front_ic_upload;
            }
        } else {
            $errors[] = 'Front of IC upload is required.';
        }
        
        if (!empty($_FILES['back_ic']['name'])) {
            $back_ic_upload = tkm_handle_kyc_file_upload('back_ic');
            if (is_wp_error($back_ic_upload)) {
                $errors[] = 'Back IC upload error: ' . $back_ic_upload->get_error_message();
            } else {
                $uploaded_files['back_ic'] = $back_ic_upload;
            }
        } else {
            $errors[] = 'Back of IC upload is required.';
        }
        
        if (!empty($_FILES['selfie_with_note']['name'])) {
            $selfie_upload = tkm_handle_kyc_file_upload('selfie_with_note');
            if (is_wp_error($selfie_upload)) {
                $errors[] = 'Selfie with note upload error: ' . $selfie_upload->get_error_message();
            } else {
                $uploaded_files['selfie_with_note'] = $selfie_upload;
            }
        } else {
            $errors[] = 'Selfie with note & IC upload is required.';
        }
        
        if (empty($errors)) {
            // Check if KYC table exists
            $kyc_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_kyc'") === $wpdb->prefix . 'indoor_task_kyc';
            
            if ($kyc_table_exists) {
                // Prepare KYC data
                $kyc_data = array(
                    'full_name' => $full_name,
                    'date_of_birth' => $date_of_birth,
                    'country' => $country,
                    'phone_country' => $phone_country,
                    'phone' => $phone,
                    'gender' => $gender,
                    'postal_code' => $postal_code,
                    'address' => $address,
                    'city' => $city,
                    'state' => $state,
                    'front_ic' => $uploaded_files['front_ic'] ?? '',
                    'back_ic' => $uploaded_files['back_ic'] ?? '',
                    'selfie_with_note' => $uploaded_files['selfie_with_note'] ?? ''
                );
                
                // Insert KYC submission
                $result = $wpdb->insert(
                    $wpdb->prefix . 'indoor_task_kyc',
                    array(
                        'user_id' => $current_user_id,
                        'document' => json_encode($kyc_data),
                        'status' => 'pending',
                        'submitted_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s')
                );
                
                if ($result !== false) {
                    // Log the activity
                    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_user_activities'") === $wpdb->prefix . 'indoor_task_user_activities') {
                        $wpdb->insert(
                            $wpdb->prefix . 'indoor_task_user_activities',
                            array(
                                'user_id' => $current_user_id,
                                'activity_type' => 'kyc_submission',
                                'description' => 'KYC documents submitted for verification',
                                'ip_address' => $_SERVER['REMOTE_ADDR'],
                                'created_at' => current_time('mysql')
                            )
                        );
                    }
                    
                    // Send notification
                    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_notifications'") === $wpdb->prefix . 'indoor_task_notifications') {
                        $wpdb->insert(
                            $wpdb->prefix . 'indoor_task_notifications',
                            array(
                                'user_id' => $current_user_id,
                                'type' => 'kyc_submitted',
                                'message' => 'Your KYC has been submitted and is under review.',
                                'created_at' => current_time('mysql')
                            )
                        );
                    }
                    
                    $success_message = 'Your KYC documents have been submitted successfully! Our team will review your application within 1-3 business days. You will receive a notification once the verification is complete.';
                } else {
                    $errors[] = 'Failed to submit KYC. Please try again.';
                }
            } else {
                $errors[] = 'KYC system is not properly configured.';
            }
        }
    } else {
        $errors[] = 'Security check failed. Please try again.';
    }
}

// Get user's KYC status and data
function tkm_get_user_kyc_status($user_id) {
    global $wpdb;
    
    $kyc_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_kyc'") === $wpdb->prefix . 'indoor_task_kyc';
    
    if ($kyc_table_exists) {
        $kyc_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_task_kyc WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $user_id
        ));
        
        if ($kyc_record) {
            $document_data = json_decode($kyc_record->document, true) ?: array();
            
            return array(
                'status' => $kyc_record->status,
                'submitted_at' => $kyc_record->submitted_at,
                'reviewed_at' => $kyc_record->reviewed_at,
                'admin_reason' => $kyc_record->admin_reason,
                'document_data' => $document_data
            );
        }
    }
    
    return array(
        'status' => 'not_submitted',
        'submitted_at' => null,
        'reviewed_at' => null,
        'admin_reason' => null,
        'document_data' => array()
    );
}

// File upload handler
function tkm_handle_kyc_file_upload($field_name) {
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('upload_error', 'File upload failed.');
    }
    
    $file = $_FILES[$field_name];
    
    // Validate file size (5MB max)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return new WP_Error('file_too_large', 'File size must be less than 5MB.');
    }
    
    // Validate file type
    $allowed_types = array('image/jpeg', 'image/png', 'application/pdf');
    if (!in_array($file['type'], $allowed_types)) {
        return new WP_Error('invalid_file_type', 'Only JPG, PNG, and PDF files are allowed.');
    }
    
    // Handle upload
    $upload_overrides = array('test_form' => false);
    $uploaded_file = wp_handle_upload($file, $upload_overrides);
    
    if (isset($uploaded_file['error'])) {
        return new WP_Error('upload_failed', $uploaded_file['error']);
    }
    
    return $uploaded_file['url'];
}

$kyc_status = tkm_get_user_kyc_status($current_user_id);
$can_submit = in_array($kyc_status['status'], ['not_submitted', 'rejected']);

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>KYC Verification - <?php bloginfo('name'); ?></title>
    
    <!-- Preload critical resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <?php wp_head(); ?>
    
    <!-- TKM Door KYC Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-kyc.css?ver=3.0.0">
    
    <!-- Additional Meta Tags for Better Mobile Experience -->
    <meta name="apple-mobile-web-app-title" content="KYC Verification">
    <meta name="application-name" content="KYC Verification">
    <meta name="msapplication-TileColor" content="#00954b">
    <meta name="theme-color" content="#00954b">
</head>
<body class="tkm-door-kyc">
    <div class="tkm-kyc-container">
        <!-- Include Sidebar -->
        <?php include INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'; ?>
        
        <div class="tkm-kyc-content">
            <!-- Header Section -->
            <div class="tkm-kyc-header">
                <div class="tkm-header-content">
                    <h1>Identity Verification</h1>
                    <p class="tkm-header-subtitle">Complete your KYC verification to unlock all platform features</p>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="tkm-message tkm-message-success">
                    <div class="tkm-message-icon">✅</div>
                    <div class="tkm-message-text"><?php echo esc_html($success_message); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="tkm-message tkm-message-error">
                    <div class="tkm-message-icon">❌</div>
                    <div class="tkm-message-text">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo esc_html($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- KYC Status Section -->
            <div class="tkm-status-section">
                <h3>Verification Status</h3>
                <div class="tkm-status-card tkm-glass-container">
                    <div class="tkm-status-header">
                        <div class="tkm-status-badge <?php echo $kyc_status['status']; ?>">
                            <?php
                            $status_labels = array(
                                'not_submitted' => '⏳ Not Submitted',
                                'pending' => '🔄 Under Review',
                                'approved' => '✅ Approved',
                                'rejected' => '❌ Rejected'
                            );
                            echo $status_labels[$kyc_status['status']] ?? 'Unknown';
                            ?>
                        </div>
                        
                        <?php if ($kyc_status['submitted_at']): ?>
                            <div class="tkm-status-date">
                                📅 Submitted: <?php echo date('M j, Y g:i A', strtotime($kyc_status['submitted_at'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($kyc_status['status'] === 'approved'): ?>
                        <div class="tkm-status-content">
                            <div class="tkm-success-message">
                                <h3>🎉 Verification Complete!</h3>
                                <p>Your identity has been verified successfully. You now have access to all platform features including withdrawals and premium tasks.</p>
                                
                                <?php if (!empty($kyc_status['document_data'])): ?>
                                    <div class="tkm-verified-details">
                                        <h4>Verified Information:</h4>
                                        <div class="tkm-detail-item">
                                            <strong>Name:</strong> <?php echo esc_html($kyc_status['document_data']['full_name'] ?? 'N/A'); ?>
                                        </div>
                                        <?php if ($kyc_status['reviewed_at']): ?>
                                            <div class="tkm-detail-item">
                                                <strong>Verified on:</strong> <?php echo date('M j, Y g:i A', strtotime($kyc_status['reviewed_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    
                    <?php elseif ($kyc_status['status'] === 'rejected'): ?>
                        <div class="tkm-status-content">
                            <div class="tkm-rejection-message">
                                <h3>⚠️ Verification Rejected</h3>
                                <p>Unfortunately, your KYC submission was rejected. Please review the reason below and submit again with the correct information.</p>
                                
                                <?php if ($kyc_status['admin_reason']): ?>
                                    <div class="tkm-rejection-reason">
                                        <strong>Rejection Reason:</strong>
                                        <p><?php echo esc_html($kyc_status['admin_reason']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="tkm-resubmit-note">
                                    <p>You can submit new documents using the form below.</p>
                                </div>
                            </div>
                        </div>
                    
                    <?php elseif ($kyc_status['status'] === 'pending'): ?>
                        <div class="tkm-status-content">
                            <div class="tkm-pending-message">
                                <h3>📋 Review in Progress</h3>
                                <p>Your KYC documents are currently being reviewed by our team. This process typically takes 1-3 business days.</p>
                                
                                <div class="tkm-review-timeline">
                                    <div class="tkm-timeline-item completed">
                                        <div class="tkm-timeline-dot"></div>
                                        <div class="tkm-timeline-content">
                                            <strong>Documents Submitted</strong>
                                            <span><?php echo date('M j, Y g:i A', strtotime($kyc_status['submitted_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="tkm-timeline-item active">
                                        <div class="tkm-timeline-dot"></div>
                                        <div class="tkm-timeline-content">
                                            <strong>Under Review</strong>
                                            <span>In progress...</span>
                                        </div>
                                    </div>
                                    <div class="tkm-timeline-item">
                                        <div class="tkm-timeline-dot"></div>
                                        <div class="tkm-timeline-content">
                                            <strong>Verification Complete</strong>
                                            <span>Pending</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    
                    <?php else: ?>
                        <div class="tkm-status-content">
                            <div class="tkm-not-submitted-message">
                                <h3>📋 Start Your Verification</h3>
                                <p>Complete your KYC verification to unlock all platform features including:</p>
                                <ul class="tkm-benefits-list">
                                    <li>💰 Withdraw earnings</li>
                                    <li>🎯 Access premium tasks</li>
                                    <li>🏆 Participate in leaderboards</li>
                                    <li>🎁 Receive bonus rewards</li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- KYC Multi-Step Form Section -->
            <?php if ($can_submit): ?>
            <div class="tkm-form-section">
                <h2>Know Your Customer (KYC) Verification</h2>
                
                <!-- Step Progress Indicator -->
                <div class="tkm-step-progress tkm-glass-container">
                    <div class="tkm-step-item active" data-step="1">
                        <div class="tkm-step-number">1</div>
                        <div class="tkm-step-label">Personal Details</div>
                    </div>
                    <div class="tkm-step-line"></div>
                    <div class="tkm-step-item" data-step="2">
                        <div class="tkm-step-number">2</div>
                        <div class="tkm-step-label">Upload Documents</div>
                    </div>
                    <div class="tkm-step-line"></div>
                    <div class="tkm-step-item" data-step="3">
                        <div class="tkm-step-number">3</div>
                        <div class="tkm-step-label">Review & Submit</div>
                    </div>
                </div>
                
                <form method="post" enctype="multipart/form-data" class="tkm-kyc-form tkm-glass-container" id="kyc-multi-step-form">
                    <?php wp_nonce_field('tkm_kyc_nonce', 'nonce'); ?>
                    <input type="hidden" name="action" value="submit_kyc">
                    
                    <!-- Step 1: Personal Details -->
                    <div class="tkm-form-step active" id="step-1">
                        <div class="tkm-step-header">
                            <h3>Step 1: Personal Details Form</h3>
                            <p>Please provide your personal information exactly as it appears on your identification document</p>
                        </div>
                        
                        <div class="tkm-form-row">
                            <div class="tkm-form-group">
                                <label for="full_name">Full Name <span class="required">*</span></label>
                                <input 
                                    type="text" 
                                    id="full_name" 
                                    name="full_name" 
                                    required 
                                    class="tkm-input"
                                    placeholder="Enter your full legal name"
                                    value="<?php echo esc_attr($kyc_status['document_data']['full_name'] ?? ''); ?>"
                                    autocomplete="name"
                                >
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                                <input 
                                    type="date" 
                                    id="date_of_birth" 
                                    name="date_of_birth" 
                                    required 
                                    class="tkm-input"
                                    value="<?php echo esc_attr($kyc_status['document_data']['date_of_birth'] ?? ''); ?>"
                                    max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                                    autocomplete="bday"
                                >
                            </div>
                        </div>
                        
                        <div class="tkm-form-row">
                            <div class="tkm-form-group">
                                <label for="country">Country <span class="required">*</span></label>
                                <select id="country" name="country" required class="tkm-input tkm-select">
                                    <option value="">Select your country</option>
                                    <option value="US" <?php echo ($kyc_status['document_data']['country'] ?? '') === 'US' ? 'selected' : ''; ?>>United States</option>
                                    <option value="CA" <?php echo ($kyc_status['document_data']['country'] ?? '') === 'CA' ? 'selected' : ''; ?>>Canada</option>
                                    <option value="GB" <?php echo ($kyc_status['document_data']['country'] ?? '') === 'GB' ? 'selected' : ''; ?>>United Kingdom</option>
                                    <option value="AU" <?php echo ($kyc_status['document_data']['country'] ?? '') === 'AU' ? 'selected' : ''; ?>>Australia</option>
                                    <option value="DE" <?php echo ($kyc_status['document_data']['country'] ?? '') === 'DE' ? 'selected' : ''; ?>>Germany</option>
                                    <option value="FR" <?php echo ($kyc_status['document_data']['country'] ?? '') === 'FR' ? 'selected' : ''; ?>>France</option>
                                    <option value="JP" <?php echo ($kyc_status['document_data']['country'] ?? '') === 'JP' ? 'selected' : ''; ?>>Japan</option>
                                    <option value="SG" <?php echo ($kyc_status['document_data']['country'] ?? '') === 'SG' ? 'selected' : ''; ?>>Singapore</option>
                                    <option value="MY" <?php echo ($kyc_status['document_data']['country'] ?? '') === 'MY' ? 'selected' : ''; ?>>Malaysia</option>
                                    <option value="IN" <?php echo ($kyc_status['document_data']['country'] ?? '') === 'IN' ? 'selected' : ''; ?>>India</option>
                                </select>
                            </div>                                <div class="tkm-form-group">
                                    <label for="phone_number">Phone Number <span class="required">*</span></label>
                                    <div class="tkm-phone-input">
                                        <select id="phone_country" name="phone_country" class="tkm-phone-country">
                                            <option value="+60" <?php echo ($kyc_status['document_data']['phone_country'] ?? '') === '+60' ? 'selected' : ''; ?>>�� +60</option>
                                            <option value="+65" <?php echo ($kyc_status['document_data']['phone_country'] ?? '') === '+65' ? 'selected' : ''; ?>>�� +65</option>
                                            <option value="+62" <?php echo ($kyc_status['document_data']['phone_country'] ?? '') === '+62' ? 'selected' : ''; ?>>�� +62</option>
                                            <option value="+66" <?php echo ($kyc_status['document_data']['phone_country'] ?? '') === '+66' ? 'selected' : ''; ?>>�� +66</option>
                                            <option value="+63" <?php echo ($kyc_status['document_data']['phone_country'] ?? '') === '+63' ? 'selected' : ''; ?>>�� +63</option>
                                            <option value="+84" <?php echo ($kyc_status['document_data']['phone_country'] ?? '') === '+84' ? 'selected' : ''; ?>>�� +84</option>
                                            <option value="+1" <?php echo ($kyc_status['document_data']['phone_country'] ?? '') === '+1' ? 'selected' : ''; ?>>🇺🇸 +1</option>
                                            <option value="+44" <?php echo ($kyc_status['document_data']['phone_country'] ?? '') === '+44' ? 'selected' : ''; ?>>�� +44</option>
                                            <option value="+61" <?php echo ($kyc_status['document_data']['phone_country'] ?? '') === '+61' ? 'selected' : ''; ?>>�� +61</option>
                                        </select>
                                        <input 
                                            type="tel" 
                                            id="phone_number" 
                                            name="phone" 
                                            required 
                                            class="tkm-input"
                                            placeholder="Enter phone number"
                                            value="<?php echo esc_attr($kyc_status['document_data']['phone'] ?? ''); ?>"
                                            autocomplete="tel"
                                        >
                                    </div>
                                </div>
                        </div>
                        
                        <div class="tkm-form-row">
                            <div class="tkm-form-group">
                                <label for="gender">⚧ Gender <span class="required">*</span></label>
                                <select id="gender" name="gender" required class="tkm-input tkm-select">
                                    <option value="">Select your gender</option>
                                    <option value="male" <?php echo ($kyc_status['document_data']['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($kyc_status['document_data']['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($kyc_status['document_data']['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    <option value="prefer_not_to_say" <?php echo ($kyc_status['document_data']['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                </select>
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="postal_code">📮 Postal Code <span class="required">*</span></label>
                                <input 
                                    type="text" 
                                    id="postal_code" 
                                    name="postal_code" 
                                    required 
                                    class="tkm-input"
                                    placeholder="Enter postal code"
                                    value="<?php echo esc_attr($kyc_status['document_data']['postal_code'] ?? ''); ?>"
                                    autocomplete="postal-code"
                                >
                            </div>
                        </div>
                        
                        <div class="tkm-form-group">
                            <label for="address">🏠 Address <span class="required">*</span></label>
                            <textarea 
                                id="address" 
                                name="address" 
                                required 
                                class="tkm-textarea"
                                rows="3"
                                placeholder="Enter your complete residential address"
                                autocomplete="street-address"
                            ><?php echo esc_textarea($kyc_status['document_data']['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="tkm-form-row">
                            <div class="tkm-form-group">
                                <label for="city">🏙️ City <span class="required">*</span></label>
                                <input 
                                    type="text" 
                                    id="city" 
                                    name="city" 
                                    required 
                                    class="tkm-input"
                                    placeholder="Enter your city"
                                    value="<?php echo esc_attr($kyc_status['document_data']['city'] ?? ''); ?>"
                                    autocomplete="address-level2"
                                >
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="state">🗺️ State <span class="required">*</span></label>
                                <select id="state" name="state" required class="tkm-input tkm-select">
                                    <option value="">Select your state</option>
                                    <option value="Johor" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Johor' ? 'selected' : ''; ?>>Johor</option>
                                    <option value="Kedah" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Kedah' ? 'selected' : ''; ?>>Kedah</option>
                                    <option value="Kelantan" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Kelantan' ? 'selected' : ''; ?>>Kelantan</option>
                                    <option value="Kuala Lumpur" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Kuala Lumpur' ? 'selected' : ''; ?>>Kuala Lumpur</option>
                                    <option value="Labuan" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Labuan' ? 'selected' : ''; ?>>Labuan</option>
                                    <option value="Malacca" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Malacca' ? 'selected' : ''; ?>>Malacca</option>
                                    <option value="Negeri Sembilan" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Negeri Sembilan' ? 'selected' : ''; ?>>Negeri Sembilan</option>
                                    <option value="Pahang" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Pahang' ? 'selected' : ''; ?>>Pahang</option>
                                    <option value="Penang" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Penang' ? 'selected' : ''; ?>>Penang</option>
                                    <option value="Perak" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Perak' ? 'selected' : ''; ?>>Perak</option>
                                    <option value="Perlis" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Perlis' ? 'selected' : ''; ?>>Perlis</option>
                                    <option value="Putrajaya" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Putrajaya' ? 'selected' : ''; ?>>Putrajaya</option>
                                    <option value="Sabah" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Sabah' ? 'selected' : ''; ?>>Sabah</option>
                                    <option value="Sarawak" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Sarawak' ? 'selected' : ''; ?>>Sarawak</option>
                                    <option value="Selangor" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Selangor' ? 'selected' : ''; ?>>Selangor</option>
                                    <option value="Terengganu" <?php echo ($kyc_status['document_data']['state'] ?? '') === 'Terengganu' ? 'selected' : ''; ?>>Terengganu</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="tkm-form-actions">
                            <button type="button" class="tkm-btn tkm-btn-primary" id="next-step-1">
                                NEXT
                                <svg class="tkm-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 18l6-6-6-6"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Upload Documents -->
                    <div class="tkm-form-step" id="step-2">
                        <div class="tkm-step-header">
                            <h3>Step 2: Upload Documents</h3>
                            <p>Upload a clear image of your chosen personal document to each category</p>
                        </div>
                        
                        <div class="tkm-upload-grid">
                            <div class="tkm-form-group">
                                <label for="front_ic">📄 Front of IC <span class="required">*</span></label>
                                <div class="tkm-file-upload">
                                    <input 
                                        type="file" 
                                        id="front_ic" 
                                        name="front_ic" 
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        required
                                        class="tkm-file-input"
                                    >
                                    <div class="tkm-file-upload-area">
                                        <div class="tkm-upload-icon">📄</div>
                                        <div class="tkm-upload-text">
                                            <strong>Choose file</strong> or drag and drop
                                            <br><small>JPG, PNG, PDF (max 5MB)</small>
                                        </div>
                                    </div>
                                    <div class="tkm-file-info">
                                        <small>💡 Upload the front side of your IC clearly</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="back_ic">📄 Back of IC <span class="required">*</span></label>
                                <div class="tkm-file-upload">
                                    <input 
                                        type="file" 
                                        id="back_ic" 
                                        name="back_ic" 
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        required
                                        class="tkm-file-input"
                                    >
                                    <div class="tkm-file-upload-area">
                                        <div class="tkm-upload-icon">📄</div>
                                        <div class="tkm-upload-text">
                                            <strong>Choose file</strong> or drag and drop
                                            <br><small>JPG, PNG, PDF (max 5MB)</small>
                                        </div>
                                    </div>
                                    <div class="tkm-file-info">
                                        <small>💡 Upload the back side of your IC clearly</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="selfie_with_note">🤳 Selfie with Note & IC <span class="required">*</span></label>
                                <div class="tkm-file-upload">
                                    <input 
                                        type="file" 
                                        id="selfie_with_note" 
                                        name="selfie_with_note" 
                                        accept=".jpg,.jpeg,.png"
                                        required
                                        class="tkm-file-input"
                                    >
                                    <div class="tkm-file-upload-area">
                                        <div class="tkm-upload-icon">🤳</div>
                                        <div class="tkm-upload-text">
                                            <strong>Choose file</strong> or drag and drop
                                            <br><small>JPG, PNG (max 5MB)</small>
                                        </div>
                                    </div>
                                    <div class="tkm-file-info">
                                        <small>📸 Take a selfie holding your IC and a note with today's date: <?php echo date('d/m/Y'); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tkm-form-actions">
                            <button type="button" class="tkm-btn tkm-btn-secondary" id="prev-step-2">
                                <svg class="tkm-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 18l-6-6 6-6"/>
                                </svg>
                                PREVIOUS
                            </button>
                            <button type="button" class="tkm-btn tkm-btn-primary" id="next-step-2">
                                NEXT
                                <svg class="tkm-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 18l6-6-6-6"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Review & Submit -->
                    <div class="tkm-form-step" id="step-3">
                        <div class="tkm-step-header">
                            <h3>Step 3: Final Review & Submit</h3>
                            <p>Please review your uploaded documents before final submission</p>
                        </div>
                        
                        <div class="tkm-review-section">
                            <div class="tkm-review-item" id="review-front-ic">
                                <div class="tkm-review-header">
                                    <h4>📄 Front of IC</h4>
                                    <button type="button" class="tkm-btn tkm-btn-outline tkm-btn-small" onclick="document.getElementById('front_ic').click()">BROWSE</button>
                                </div>
                                <div class="tkm-review-checklist">
                                    <div class="tkm-check-item">
                                        <span class="tkm-check-icon">⚠️</span>
                                        <span>Image is complete</span>
                                    </div>
                                    <div class="tkm-check-item">
                                        <span class="tkm-check-icon">⚠️</span>
                                        <span>Clearly visible</span>
                                    </div>
                                    <div class="tkm-check-item">
                                        <span class="tkm-check-icon">⚠️</span>
                                        <span>Document is valid</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tkm-review-item" id="review-back-ic">
                                <div class="tkm-review-header">
                                    <h4>📄 Back of IC</h4>
                                    <button type="button" class="tkm-btn tkm-btn-outline tkm-btn-small" onclick="document.getElementById('back_ic').click()">BROWSE</button>
                                </div>
                                <div class="tkm-review-checklist">
                                    <div class="tkm-check-item">
                                        <span class="tkm-check-icon">⚠️</span>
                                        <span>Image is complete</span>
                                    </div>
                                    <div class="tkm-check-item">
                                        <span class="tkm-check-icon">⚠️</span>
                                        <span>Clearly visible</span>
                                    </div>
                                    <div class="tkm-check-item">
                                        <span class="tkm-check-icon">⚠️</span>
                                        <span>Document is valid</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tkm-review-item" id="review-selfie-note">
                                <div class="tkm-review-header">
                                    <h4>🤳 Selfie with Note & IC</h4>
                                    <button type="button" class="tkm-btn tkm-btn-outline tkm-btn-small" onclick="document.getElementById('selfie_with_note').click()">BROWSE</button>
                                </div>
                                <div class="tkm-review-checklist">
                                    <div class="tkm-check-item">
                                        <span class="tkm-check-icon">⚠️</span>
                                        <span>Face clearly visible</span>
                                    </div>
                                    <div class="tkm-check-item">
                                        <span class="tkm-check-icon">⚠️</span>
                                        <span>Document clearly visible</span>
                                    </div>
                                    <div class="tkm-check-item">
                                        <span class="tkm-check-icon">⚠️</span>
                                        <span>Note has today's date</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tkm-form-actions">
                            <button type="button" class="tkm-btn tkm-btn-secondary" id="prev-step-3">
                                <svg class="tkm-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 18l-6-6 6-6"/>
                                </svg>
                                PREVIOUS
                            </button>
                            <button type="submit" class="tkm-btn tkm-btn-primary" id="submit-kyc">
                                <svg class="tkm-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 12l2 2 4-4"/>
                                    <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9c1.66 0 3.22.45 4.56 1.23"/>
                                </svg>
                                SUBMIT
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Information Section -->
            <div class="tkm-info-section">
                <h3>🔍 Important Information</h3>
                <div class="tkm-info-cards">
                    <div class="tkm-info-card tkm-glass-container">
                        <div class="tkm-info-icon">🔒</div>
                        <div class="tkm-info-content">
                            <h4>Bank-Level Security</h4>
                            <p>Your documents are encrypted with AES-256 encryption and stored securely in compliance with international data protection standards. We never share your personal information with third parties.</p>
                        </div>
                    </div>
                    
                    <div class="tkm-info-card tkm-glass-container">
                        <div class="tkm-info-icon">⚡</div>
                        <div class="tkm-info-content">
                            <h4>Lightning Fast Review</h4>
                            <p>Our advanced AI-powered verification system processes most KYC submissions within 30 minutes to 24 hours. Complex cases may take up to 3 business days for manual review.</p>
                        </div>
                    </div>
                    
                    <div class="tkm-info-card tkm-glass-container">
                        <div class="tkm-info-icon">💎</div>
                        <div class="tkm-info-content">
                            <h4>Premium Features Unlocked</h4>
                            <p>Once verified, gain access to instant withdrawals, premium high-paying tasks, exclusive bonuses, priority customer support, and advanced trading features.</p>
                        </div>
                    </div>
                    
                    <div class="tkm-info-card tkm-glass-container">
                        <div class="tkm-info-icon">🛡️</div>
                        <div class="tkm-info-content">
                            <h4>Global Compliance</h4>
                            <p>Our KYC process meets international regulatory standards including GDPR, PCI DSS, and anti-money laundering requirements. Your verification enables secure cross-border transactions.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Tips Section -->
                <div class="tkm-verification-tips" style="margin-top: 40px;">
                    <div class="tkm-glass-container" style="padding: 30px;">
                        <h4 style="color: #1a202c; margin-bottom: 20px; font-size: 1.3rem;">💡 Verification Tips for Success</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <span style="font-size: 1.5rem;">📸</span>
                                <div>
                                    <strong style="color: #1a202c;">Clear Photos</strong>
                                    <p style="color: #4a5568; margin: 5px 0 0 0; font-size: 0.9rem;">Ensure all text on your ID is clearly readable and the image is well-lit</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <span style="font-size: 1.5rem;">✅</span>
                                <div>
                                    <strong style="color: #1a202c;">Valid Documents</strong>
                                    <p style="color: #4a5568; margin: 5px 0 0 0; font-size: 0.9rem;">Use current, unexpired government-issued photo identification</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <span style="font-size: 1.5rem;">🎯</span>
                                <div>
                                    <strong style="color: #1a202c;">Exact Match</strong>
                                    <p style="color: #4a5568; margin: 5px 0 0 0; font-size: 0.9rem;">Ensure your name matches exactly as shown on your ID document</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Loading Overlay -->
    <div id="loading-overlay" class="tkm-loading-overlay" style="display: none;">
        <div class="tkm-loading-spinner"></div>
        <p>🚀 Submitting your KYC documents securely...</p>
        <div style="margin-top: 15px; color: rgba(255,255,255,0.7); font-size: 0.9rem;">
            Please don't close this window while we process your information
        </div>
    </div>
    
    <?php wp_footer(); ?>
    
    <!-- TKM Door KYC Scripts -->
    <script>
        // Pass PHP variables to JavaScript
        window.tkmKyc = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('tkm_kyc_nonce'); ?>',
            currentUserId: <?php echo $current_user_id; ?>,
            canSubmit: <?php echo $can_submit ? 'true' : 'false'; ?>,
            kycStatus: '<?php echo $kyc_status['status']; ?>',
            assetsUrl: '<?php echo INDOOR_TASKS_URL; ?>assets/',
            isRejected: <?php echo ($kyc_status['status'] === 'rejected') ? 'true' : 'false'; ?>
        };
        
        // Add some visual enhancements on load
        document.addEventListener('DOMContentLoaded', function() {
            // Add stagger animation to info cards
            const cards = document.querySelectorAll('.tkm-info-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${0.8 + (index * 0.1)}s`;
            });
            
            // Add pulse effect to required fields
            const requiredSpans = document.querySelectorAll('.required');
            requiredSpans.forEach(span => {
                span.style.animation = 'pulse 2s infinite';
            });
        });
    </script>
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-kyc.js?ver=3.0.0"></script>
</body>
</html>
