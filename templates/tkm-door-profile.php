<?php
/**
 * Template Name: TKM Door - My Profile
 * Description: Modern profile management template for user account settings
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
$user_meta = get_user_meta($current_user_id);

// Get database references
global $wpdb;

$success_message = '';
$error_message = '';

// Handle profile update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (wp_verify_nonce($_POST['profile_nonce'], 'tkm_profile_nonce')) {
        $full_name = sanitize_text_field($_POST['full_name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $date_of_birth = sanitize_text_field($_POST['date_of_birth']);
        $gender = sanitize_text_field($_POST['gender']);
        $address = sanitize_textarea_field($_POST['address']);
        $profile_picture = sanitize_url($_POST['profile_picture']);
        
        $errors = array();
        
        // Validation
        if (empty($full_name)) {
            $errors[] = 'Full name is required.';
        }
        
        if (empty($email) || !is_email($email)) {
            $errors[] = 'Valid email address is required.';
        }
        
        // Check if email is already taken by another user
        if ($email !== $current_user->user_email) {
            $email_exists = email_exists($email);
            if ($email_exists && $email_exists !== $current_user_id) {
                $errors[] = 'Email address is already in use by another account.';
            }
        }
        
        if (empty($errors)) {
            // Update user data
            $user_data = array(
                'ID' => $current_user_id,
                'user_email' => $email,
                'display_name' => $full_name
            );
            
            $updated = wp_update_user($user_data);
            
            if (!is_wp_error($updated)) {
                // Update user meta
                update_user_meta($current_user_id, 'full_name', $full_name);
                update_user_meta($current_user_id, 'phone', $phone);
                update_user_meta($current_user_id, 'date_of_birth', $date_of_birth);
                update_user_meta($current_user_id, 'gender', $gender);
                update_user_meta($current_user_id, 'address', $address);
                
                if (!empty($profile_picture)) {
                    update_user_meta($current_user_id, 'profile_picture', $profile_picture);
                }
                
                // Log the activity
                if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_user_activities'") === $wpdb->prefix . 'indoor_task_user_activities') {
                    $wpdb->insert(
                        $wpdb->prefix . 'indoor_task_user_activities',
                        array(
                            'user_id' => $current_user_id,
                            'activity_type' => 'profile_update',
                            'description' => 'Profile information updated',
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'created_at' => current_time('mysql')
                        )
                    );
                }
                
                $success_message = 'Profile updated successfully!';
                
                // Refresh user data
                $current_user = wp_get_current_user();
                $user_meta = get_user_meta($current_user_id);
            } else {
                $error_message = 'Failed to update profile. Please try again.';
            }
        } else {
            $error_message = implode('<br>', $errors);
        }
    } else {
        $error_message = 'Security check failed. Please try again.';
    }
}

// Handle password change
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (wp_verify_nonce($_POST['password_nonce'], 'tkm_password_nonce')) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = array();
        
        // Validation
        if (empty($current_password)) {
            $errors[] = 'Current password is required.';
        } elseif (!wp_check_password($current_password, $current_user->user_pass, $current_user_id)) {
            $errors[] = 'Current password is incorrect.';
        }
        
        if (empty($new_password)) {
            $errors[] = 'New password is required.';
        } elseif (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Password confirmation does not match.';
        }
        
        if (empty($errors)) {
            wp_set_password($new_password, $current_user_id);
            
            // Log the activity
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_user_activities'") === $wpdb->prefix . 'indoor_task_user_activities') {
                $wpdb->insert(
                    $wpdb->prefix . 'indoor_task_user_activities',
                    array(
                        'user_id' => $current_user_id,
                        'activity_type' => 'password_change',
                        'description' => 'Password changed successfully',
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'created_at' => current_time('mysql')
                    )
                );
            }
            
            $success_message = 'Password changed successfully!';
        } else {
            $error_message = implode('<br>', $errors);
        }
    } else {
        $error_message = 'Security check failed. Please try again.';
    }
}

// Get user stats and additional info
function tkm_get_user_stats($user_id) {
    global $wpdb;
    
    $stats = array(
        'join_date' => '',
        'last_login' => '',
        'user_level' => '',
        'points' => 0,
        'referral_code' => ''
    );
    
    $user = get_userdata($user_id);
    if ($user) {
        $stats['join_date'] = $user->user_registered;
        $stats['last_login'] = get_user_meta($user_id, 'last_login', true);
        $stats['user_level'] = get_user_meta($user_id, 'user_level', true) ?: 'Basic';
        $stats['points'] = get_user_meta($user_id, 'user_points', true) ?: 0;
        $stats['referral_code'] = get_user_meta($user_id, 'referral_code', true) ?: strtoupper(substr(md5($user_id), 0, 8));
        
        // Update referral code if not exists
        if (empty(get_user_meta($user_id, 'referral_code', true))) {
            update_user_meta($user_id, 'referral_code', $stats['referral_code']);
        }
    }
    
    return $stats;
}

$user_stats = tkm_get_user_stats($current_user_id);
$profile_picture = get_user_meta($current_user_id, 'profile_picture', true);
$default_avatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($current_user->user_email))) . '?s=150&d=mp';

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>My Profile - <?php bloginfo('name'); ?></title>
    
    <!-- Preload critical resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <?php wp_head(); ?>
    
    <!-- TKM Door Profile Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-profile.css?ver=1.0.0">
    
    <!-- Additional Meta Tags for Better Mobile Experience -->
    <meta name="apple-mobile-web-app-title" content="My Profile">
    <meta name="application-name" content="My Profile">
    <meta name="msapplication-TileColor" content="#00954b">
    <meta name="theme-color" content="#00954b">
</head>
<body class="tkm-door-profile">
    <div class="tkm-profile-container">
        <!-- Include Sidebar -->
        <?php include INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'; ?>
        
        <div class="tkm-profile-content">
            <!-- Header Section -->
            <div class="tkm-profile-header">
                <div class="tkm-header-content">
                    <h1>My Profile</h1>
                    <p class="tkm-header-subtitle">Manage your account settings and personal information</p>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="tkm-message tkm-message-success">
                    <div class="tkm-message-icon">✅</div>
                    <div class="tkm-message-text"><?php echo esc_html($success_message); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="tkm-message tkm-message-error">
                    <div class="tkm-message-icon">❌</div>
                    <div class="tkm-message-text"><?php echo wp_kses_post($error_message); ?></div>
                </div>
            <?php endif; ?>
            
            <div class="tkm-profile-grid">
                <!-- Profile Information Section -->
                <div class="tkm-profile-section tkm-glass-container">
                    <div class="tkm-section-header">
                        <h2>👤 Profile Information</h2>
                        <p>Update your personal details and profile picture</p>
                    </div>
                    
                    <form method="post" class="tkm-profile-form" id="profile-form">
                        <?php wp_nonce_field('tkm_profile_nonce', 'profile_nonce'); ?>
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="profile_picture" id="profile_picture_url" value="<?php echo esc_url($profile_picture); ?>">
                        
                        <!-- Profile Picture -->
                        <div class="tkm-profile-picture-section">
                            <div class="tkm-profile-picture">
                                <img src="<?php echo esc_url($profile_picture ?: $default_avatar); ?>" alt="Profile Picture" id="profile-picture-preview">
                                <div class="tkm-picture-overlay">
                                    <button type="button" class="tkm-change-picture-btn" id="change-picture-btn">
                                        📷 Change Photo
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tkm-form-grid">
                            <div class="tkm-form-group">
                                <label for="full_name">Full Name <span class="required">*</span></label>
                                <input 
                                    type="text" 
                                    id="full_name" 
                                    name="full_name" 
                                    required 
                                    class="tkm-input"
                                    value="<?php echo esc_attr($user_meta['full_name'][0] ?? $current_user->display_name); ?>"
                                    placeholder="Enter your full name"
                                >
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="username">Username</label>
                                <input 
                                    type="text" 
                                    id="username" 
                                    class="tkm-input tkm-input-readonly"
                                    value="<?php echo esc_attr($current_user->user_login); ?>"
                                    readonly
                                    title="Username cannot be changed"
                                >
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    required 
                                    class="tkm-input"
                                    value="<?php echo esc_attr($current_user->user_email); ?>"
                                    placeholder="Enter your email address"
                                >
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="phone">Phone Number</label>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    name="phone" 
                                    class="tkm-input"
                                    value="<?php echo esc_attr($user_meta['phone'][0] ?? ''); ?>"
                                    placeholder="Enter your phone number"
                                >
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input 
                                    type="date" 
                                    id="date_of_birth" 
                                    name="date_of_birth" 
                                    class="tkm-input"
                                    value="<?php echo esc_attr($user_meta['date_of_birth'][0] ?? ''); ?>"
                                >
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" class="tkm-input tkm-select">
                                    <option value="">Select gender</option>
                                    <option value="male" <?php echo ($user_meta['gender'][0] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($user_meta['gender'][0] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($user_meta['gender'][0] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    <option value="prefer_not_to_say" <?php echo ($user_meta['gender'][0] ?? '') === 'prefer_not_to_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="tkm-form-group tkm-form-group-full">
                            <label for="address">Address</label>
                            <textarea 
                                id="address" 
                                name="address" 
                                class="tkm-textarea"
                                rows="3"
                                placeholder="Enter your complete address"
                            ><?php echo esc_textarea($user_meta['address'][0] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="tkm-form-actions">
                            <button type="submit" class="tkm-btn tkm-btn-primary">
                                💾 Save Changes
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Change Password Section -->
                <div class="tkm-profile-section tkm-glass-container">
                    <div class="tkm-section-header">
                        <h2>🔒 Change Password</h2>
                        <p>Update your account password for security</p>
                    </div>
                    
                    <form method="post" class="tkm-password-form" id="password-form">
                        <?php wp_nonce_field('tkm_password_nonce', 'password_nonce'); ?>
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="tkm-form-group">
                            <label for="current_password">Current Password <span class="required">*</span></label>
                            <div class="tkm-password-input">
                                <input 
                                    type="password" 
                                    id="current_password" 
                                    name="current_password" 
                                    required 
                                    class="tkm-input"
                                    placeholder="Enter your current password"
                                >
                                <button type="button" class="tkm-password-toggle" data-target="current_password">
                                    👁️
                                </button>
                            </div>
                        </div>
                        
                        <div class="tkm-form-group">
                            <label for="new_password">New Password <span class="required">*</span></label>
                            <div class="tkm-password-input">
                                <input 
                                    type="password" 
                                    id="new_password" 
                                    name="new_password" 
                                    required 
                                    class="tkm-input"
                                    placeholder="Enter new password (min 8 characters)"
                                    minlength="8"
                                >
                                <button type="button" class="tkm-password-toggle" data-target="new_password">
                                    👁️
                                </button>
                            </div>
                            <div class="tkm-password-strength" id="password-strength"></div>
                        </div>
                        
                        <div class="tkm-form-group">
                            <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                            <div class="tkm-password-input">
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    required 
                                    class="tkm-input"
                                    placeholder="Confirm your new password"
                                >
                                <button type="button" class="tkm-password-toggle" data-target="confirm_password">
                                    👁️
                                </button>
                            </div>
                            <div class="tkm-password-match" id="password-match"></div>
                        </div>
                        
                        <div class="tkm-form-actions">
                            <button type="submit" class="tkm-btn tkm-btn-primary">
                                🔐 Change Password
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Account Information Section -->
                <div class="tkm-profile-section tkm-glass-container">
                    <div class="tkm-section-header">
                        <h2>📊 Account Information</h2>
                        <p>Your account details and statistics</p>
                    </div>
                    
                    <div class="tkm-account-info">
                        <div class="tkm-info-grid">
                            <div class="tkm-info-item">
                                <div class="tkm-info-label">📅 Join Date</div>
                                <div class="tkm-info-value"><?php echo date('M j, Y', strtotime($user_stats['join_date'])); ?></div>
                            </div>
                            
                            <div class="tkm-info-item">
                                <div class="tkm-info-label">🕒 Last Login</div>
                                <div class="tkm-info-value">
                                    <?php 
                                    if ($user_stats['last_login']) {
                                        echo date('M j, Y g:i A', strtotime($user_stats['last_login']));
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="tkm-info-item">
                                <div class="tkm-info-label">🏆 User Level</div>
                                <div class="tkm-info-value"><?php echo esc_html($user_stats['user_level']); ?></div>
                            </div>
                            
                            <div class="tkm-info-item">
                                <div class="tkm-info-label">⭐ Points</div>
                                <div class="tkm-info-value"><?php echo number_format($user_stats['points']); ?></div>
                            </div>
                        </div>
                        
                        <div class="tkm-referral-section">
                            <div class="tkm-referral-header">
                                <h3>🔗 Your Referral Code</h3>
                                <p>Share this code with friends to earn rewards</p>
                            </div>
                            <div class="tkm-referral-code">
                                <input 
                                    type="text" 
                                    id="referral-code" 
                                    class="tkm-input tkm-input-readonly" 
                                    value="<?php echo esc_attr($user_stats['referral_code']); ?>" 
                                    readonly
                                >
                                <button type="button" class="tkm-btn tkm-btn-outline tkm-copy-btn" data-copy="referral-code">
                                    📋 Copy
                                </button>
                            </div>
                            <div class="tkm-referral-link">
                                <input 
                                    type="text" 
                                    id="referral-link" 
                                    class="tkm-input tkm-input-readonly" 
                                    value="<?php echo home_url('/?ref=' . $user_stats['referral_code']); ?>" 
                                    readonly
                                >
                                <button type="button" class="tkm-btn tkm-btn-outline tkm-copy-btn" data-copy="referral-link">
                                    📋 Copy Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="tkm-loading-overlay" style="display: none;">
        <div class="tkm-loading-spinner"></div>
        <p>Updating your profile...</p>
    </div>
    
    <?php wp_footer(); ?>
    
    <!-- TKM Door Profile Scripts -->
    <script>
        // Pass PHP variables to JavaScript
        window.tkmProfile = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('tkm_profile_nonce'); ?>',
            currentUserId: <?php echo $current_user_id; ?>,
            assetsUrl: '<?php echo INDOOR_TASKS_URL; ?>assets/',
            defaultAvatar: '<?php echo esc_js($default_avatar); ?>'
        };
    </script>
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-profile.js?ver=1.0.0"></script>
</body>
</html>
