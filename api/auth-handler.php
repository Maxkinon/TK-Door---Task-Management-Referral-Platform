<?php
/**
 * AJAX Authentication Handler
 * Handles login, registration, and OTP verification
 */

// Add error handling for AJAX requests
add_action('wp_ajax_indoor_tasks_debug', function() {
    error_log('Debug AJAX handler called');
    wp_send_json_success(['message' => 'AJAX is working', 'time' => current_time('mysql')]);
});

add_action('wp_ajax_nopriv_indoor_tasks_debug', function() {
    error_log('Debug AJAX handler called (no auth)');
    wp_send_json_success(['message' => 'AJAX is working (no auth)', 'time' => current_time('mysql')]);
});

// Handle authenticated requests
add_action('wp_ajax_indoor_tasks_auth', 'indoor_tasks_handle_auth_request');
add_action('wp_ajax_nopriv_indoor_tasks_auth', 'indoor_tasks_handle_auth_request');

// Handle login requests
add_action('wp_ajax_indoor_tasks_login', 'indoor_tasks_handle_login');
add_action('wp_ajax_nopriv_indoor_tasks_login', 'indoor_tasks_handle_login');

// Handle Firebase authentication
add_action('wp_ajax_indoor_tasks_firebase_auth', 'indoor_tasks_handle_firebase_auth');
add_action('wp_ajax_nopriv_indoor_tasks_firebase_auth', 'indoor_tasks_handle_firebase_auth');

// Handle Google authentication (called by JavaScript)
add_action('wp_ajax_indoor_tasks_google_auth', 'indoor_tasks_handle_google_auth_new');
add_action('wp_ajax_nopriv_indoor_tasks_google_auth', 'indoor_tasks_handle_google_auth_new');

// Handle registration requests
add_action('wp_ajax_indoor_tasks_register', 'indoor_tasks_handle_register');
add_action('wp_ajax_nopriv_indoor_tasks_register', 'indoor_tasks_handle_register');

// Handle forgot password requests
add_action('wp_ajax_indoor_tasks_forgot_password', 'indoor_tasks_handle_forgot_password_request');
add_action('wp_ajax_nopriv_indoor_tasks_forgot_password', 'indoor_tasks_handle_forgot_password_request');

/**
 * Helper function to verify OTP code
 */
function indoor_tasks_verify_otp_code($email, $otp) {
    if (!$email || !$otp) {
        return false;
    }
    
    $otp_key = 'indoor_tasks_otp_' . md5($email);
    $stored_otp = get_transient($otp_key);
    
    if (!$stored_otp || $otp != $stored_otp) {
        return false;
    }
    
    // Don't delete the OTP here - let the calling function decide
    return true;
}

function indoor_tasks_handle_auth_request() {
    // Enhanced debugging
    indoor_tasks_log_success('Auth request started', $_POST);
    
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'indoor-tasks-auth-nonce')) {
        indoor_tasks_log_error('Auth nonce verification failed', [
            'nonce_provided' => $_POST['nonce'] ?? 'none',
            'expected_action' => 'indoor-tasks-auth-nonce'
        ]);
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    indoor_tasks_log_success('Auth nonce verification passed');
    
    // Get the action step
    $step = isset($_POST['step']) ? sanitize_text_field($_POST['step']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    indoor_tasks_log_success('Auth step processing', ['step' => $step, 'email' => $email]);
    
    // Route to appropriate handler
    switch ($step) {
        case 'send_otp':
            handle_send_otp($email);
            break;
            
        case 'verify_otp':
            handle_verify_otp();
            break;
            
        case 'password_login':
            handle_password_login();
            break;
            
        case 'forgot_password':
            handle_forgot_password();
            break;
            
        default:
            indoor_tasks_log_error('Invalid auth step', ['step' => $step]);
            wp_send_json_error(['message' => 'Invalid action.']);
    }
}

/**
 * Handle OTP sending
 */
function handle_send_otp($email) {
    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Invalid email address.']);
    }
    
    // Verify reCAPTCHA
    $recaptcha_response = isset($_POST['recaptcha']) ? sanitize_text_field($_POST['recaptcha']) : '';
    if (!verify_recaptcha_response($recaptcha_response)) {
        wp_send_json_error(['message' => 'reCAPTCHA verification failed. Please try again.']);
    }
    
    // Rate limiting - prevent too many requests
    $rate_limit_key = 'otp_rate_limit_' . md5($email . $_SERVER['REMOTE_ADDR']);
    $recent_attempts = get_transient($rate_limit_key);
    
    if ($recent_attempts && $recent_attempts >= 3) {
        wp_send_json_error(['message' => 'Too many attempts. Please wait 15 minutes before requesting another OTP.']);
    }
    
    // Generate OTP
    $otp = rand(100000, 999999);
    $otp_key = 'indoor_tasks_otp_' . md5($email);
    
    // Store OTP with 10 minute expiration
    set_transient($otp_key, $otp, 10 * MINUTE_IN_SECONDS);
    
    // Increment rate limiting counter
    set_transient($rate_limit_key, ($recent_attempts ?: 0) + 1, 15 * MINUTE_IN_SECONDS);
    
    // Send OTP email using our email class
    $email_class = Indoor_Tasks_Email::get_instance();
    $sent = $email_class->send_otp_email($email, $otp);
    
    if ($sent) {
        wp_send_json_success(['message' => 'OTP sent successfully! Please check your email.']);
    } else {
        wp_send_json_error(['message' => 'Failed to send OTP. Please check your email address and try again.']);
    }
}

/**
 * Handle OTP verification and user login/registration
 */
function handle_verify_otp() {
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $otp = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';
    $referral_code = isset($_POST['referral_code']) ? sanitize_text_field($_POST['referral_code']) : '';
    
    if (!$email || !$otp) {
        wp_send_json_error(['message' => 'Email and OTP are required.']);
    }
    
    // Verify OTP
    $otp_key = 'indoor_tasks_otp_' . md5($email);
    $stored_otp = get_transient($otp_key);
    
    if (!$stored_otp || $otp != $stored_otp) {
        wp_send_json_error(['message' => 'Invalid or expired OTP. Please request a new one.']);
    }
    
    // Delete used OTP
    delete_transient($otp_key);
    
    // Check if user exists
    $user = get_user_by('email', $email);
    
    if (!$user) {
        // Registration flow
        if (!$name) {
            wp_send_json_error(['message' => 'Name is required for registration.']);
        }
        
        if (!$phone_number) {
            wp_send_json_error(['message' => 'Phone number is required for registration.']);
        }
        
        if (!$country) {
            wp_send_json_error(['message' => 'Country is required for registration.']);
        }
        
        $user = create_new_user($email, $name, $country, $phone_number, $referral_code);
        
        if (is_wp_error($user)) {
            wp_send_json_error(['message' => $user->get_error_message()]);
        }
    }
    
    // Log the user in
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID);
    
    // Clear any rate limiting for successful login
    delete_transient('otp_rate_limit_' . md5($email . $_SERVER['REMOTE_ADDR']));
    
    // Get redirect URL
    $redirect_url = get_auth_redirect_url();
    
    wp_send_json_success([
        'message' => 'Authentication successful! Redirecting...',
        'redirect' => $redirect_url,
        'user_id' => $user->ID
    ]);
}

/**
 * Generate username from name with specified requirements
 */
function generate_username_from_name($name, $prefix = 'TK-', $is_google = false) {
    // Clean the name - remove special characters and spaces
    $clean_name = preg_replace('/[^a-zA-Z0-9]/', '', $name);
    
    // Get first 5-8 characters
    $name_part = substr($clean_name, 0, 8);
    
    // Ensure minimum length of 5
    if (strlen($name_part) < 5) {
        $name_part = str_pad($name_part, 5, '0', STR_PAD_RIGHT);
    }
    
    // Set prefix based on registration type
    if ($is_google) {
        $prefix = 'TKG-';
    }
    
    $username = $prefix . $name_part;
    
    // Ensure unique username
    $count = 1;
    $original_username = $username;
    
    while (username_exists($username)) {
        $username = $original_username . $count;
        $count++;
        
        // Prevent infinite loop
        if ($count > 999) {
            $username = $prefix . uniqid();
            break;
        }
    }
    
    return sanitize_user($username);
}

/**
 * Create a new user account
 */
function create_new_user($email, $name, $country = '', $phone_number = '', $referral_code = '', $is_google = false) {
    // Generate username using new system
    $username = generate_username_from_name($name, 'TK-', $is_google);
    
    // Create user
    $user_id = wp_create_user($username, wp_generate_password(12, false), $email);
    
    if (is_wp_error($user_id)) {
        return $user_id;
    }
    
    // Update user profile
    wp_update_user(array(
        'ID' => $user_id,
        'display_name' => $name,
        'first_name' => $name
    ));
    
    // Save additional metadata
    if ($country) {
        update_user_meta($user_id, 'indoor_tasks_country', $country);
    }
    
    if ($phone_number) {
        update_user_meta($user_id, 'indoor_tasks_phone_number', $phone_number);
    }
    
    if ($referral_code) {
        update_user_meta($user_id, 'indoor_tasks_referred_by', $referral_code);
        process_referral_bonus($referral_code, $user_id);
    }
    
    // Initialize user points
    update_user_meta($user_id, 'indoor_tasks_points', 0);
    
    // Generate unique referral code for this user
    $user_referral_code = generate_unique_referral_code($user_id);
    update_user_meta($user_id, 'indoor_tasks_referral_code', $user_referral_code);
    
    // Fire action hooks for email notifications
    do_action('indoor_tasks_user_registered', $user_id, array(
        'email' => $email,
        'name' => $name,
        'country' => $country,
        'phone_number' => $phone_number,
        'referral_code' => $referral_code,
        'user_referral_code' => $user_referral_code
    ));
    
    // Log registration activity
    if (function_exists('indoor_tasks_log_user_activity')) {
        indoor_tasks_log_user_activity($user_id, 'registration', 'User registered successfully');
    }
    
    return get_user_by('id', $user_id);
}

/**
 * Send OTP email using our improved email class
 */
function send_otp_email($email, $otp) {
    // Make sure the email class is initialized
    if (!class_exists('Indoor_Tasks_Email')) {
        require_once INDOOR_TASKS_PATH . 'includes/class-email.php';
    }
    
    $email_class = Indoor_Tasks_Email::get_instance();
    return $email_class->send_otp_email($email, $otp);
}

/**
 * Verify reCAPTCHA response
 */
function verify_recaptcha_response($recaptcha_response) {
    // Get reCAPTCHA settings from admin area
    $recaptcha_enabled = get_option('indoor_tasks_enable_recaptcha', 0);
    $recaptcha_secret = get_option('indoor_tasks_recaptcha_secret_key', '');
    
    // If reCAPTCHA is not enabled, skip verification
    if (!$recaptcha_enabled || empty($recaptcha_secret)) {
        return true;
    }
    
    // If no response provided, fail verification
    if (empty($recaptcha_response)) {
        return false;
    }
    
    // Verify with Google
    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret' => $recaptcha_secret,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]
    ]);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    
    return isset($result['success']) && $result['success'] === true;
}

/**
 * Verify Firebase ID token
 */
function verify_firebase_id_token($id_token) {
    // Basic validation - you should implement proper Firebase token verification
    // This is a simplified version for demonstration
    if (empty($id_token)) {
        return false;
    }
    
    // You would typically use Firebase Admin SDK or verify against Firebase's public keys
    // For now, we'll do basic validation
    $parts = explode('.', $id_token);
    if (count($parts) !== 3) {
        return false;
    }
    
    // Decode the payload (this is not secure verification - implement proper verification)
    $payload = json_decode(base64_decode($parts[1]), true);
    
    if (!$payload || !isset($payload['email'])) {
        return false;
    }
    
    // Check expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }
    
    return true;
}

/**
 * Get authentication redirect URL
 */
function get_auth_redirect_url() {
    // Check for stored redirect URL
    if (isset($_SESSION['indoor_tasks_redirect_after_login']) && !empty($_SESSION['indoor_tasks_redirect_after_login'])) {
        $redirect_url = $_SESSION['indoor_tasks_redirect_after_login'];
        unset($_SESSION['indoor_tasks_redirect_after_login']);
        return $redirect_url;
    }
    
    // Default redirect to dashboard or home
    $dashboard_page = get_option('indoor_tasks_dashboard_page');
    if ($dashboard_page) {
        return get_permalink($dashboard_page);
    }
    
    return home_url();
}

/**
 * Process referral bonus
 */
function process_referral_bonus($referral_code, $new_user_id) {
    if (empty($referral_code)) {
        return;
    }
    
    // Find the referrer by code
    $referrer = get_users([
        'meta_key' => 'indoor_tasks_referral_code',
        'meta_value' => $referral_code,
        'number' => 1
    ]);
    
    if (empty($referrer)) {
        error_log('Referral code not found: ' . $referral_code);
        return;
    }
    
    $referrer_id = $referrer[0]->ID;
    
    // Add referral bonus points
    $bonus_points = get_option('indoor_tasks_referral_bonus', 100);
    $current_points = get_user_meta($referrer_id, 'indoor_tasks_points', true) ?: 0;
    update_user_meta($referrer_id, 'indoor_tasks_points', $current_points + $bonus_points);
    
    // Log the transaction in wallet table
    global $wpdb;
    
    // Check if wallet table exists, if not create it
    $table_name = $wpdb->prefix . 'indoor_task_wallet';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        // Create wallet table
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            type varchar(50) NOT NULL,
            points int(11) NOT NULL,
            description text,
            reference_id int(11),
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY reference_id (reference_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Insert referral transaction
    $insert_result = $wpdb->insert(
        $table_name,
        [
            'user_id' => $referrer_id,
            'type' => 'referral',
            'points' => $bonus_points,
            'description' => "Referral bonus for inviting user: " . get_user_by('id', $new_user_id)->user_login,
            'reference_id' => $new_user_id,
            'created_at' => current_time('mysql')
        ],
        ['%d', '%s', '%d', '%s', '%d', '%s']
    );
    
    if ($insert_result === false) {
        error_log('Failed to insert referral transaction: ' . $wpdb->last_error);
    } else {
        error_log("Referral bonus added: $bonus_points points to user $referrer_id for referring user $new_user_id");
    }
    
    // Fire action hook for referral email
    do_action('indoor_tasks_user_referred', $referrer_id, $new_user_id, $referral_code);
    
    // Log the referral activity
    if (function_exists('indoor_tasks_log_user_activity')) {
        indoor_tasks_log_user_activity($referrer_id, 'referral_bonus', "Earned $bonus_points points for referring user ID: $new_user_id");
        indoor_tasks_log_user_activity($new_user_id, 'referred_by', "Referred by user ID: $referrer_id");
    }
}

/**
 * Get user's real IP address
 */
function get_user_ip() {
    // Check for IP from various headers
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

/**
 * Handle password-based login
 */
function handle_password_login() {
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (!$email || !$password) {
        wp_send_json_error(['message' => 'Email and password are required.']);
    }
    
    // Attempt to authenticate user
    $user = wp_authenticate($email, $password);
    
    if (is_wp_error($user)) {
        // Check if user exists but password is wrong
        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            wp_send_json_error(['message' => 'Invalid password. Please try again or use OTP login.']);
        } else {
            wp_send_json_error(['message' => 'No account found with this email address. Please register first.']);
        }
    }
    
    // Log the user in
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID);
    
    // Get redirect URL
    $redirect_url = get_auth_redirect_url();
    
    wp_send_json_success([
        'message' => 'Login successful! Redirecting...',
        'redirect' => $redirect_url,
        'user_id' => $user->ID
    ]);
}

/**
 * Handle forgot password request
 */
function handle_forgot_password() {
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (!$email || !is_email($email)) {
        wp_send_json_error(['message' => 'Please provide a valid email address.']);
    }
    
    // Check if user exists
    $user = get_user_by('email', $email);
    
    if (!$user) {
        // For security, don't reveal whether email exists or not
        wp_send_json_success(['message' => 'If an account with this email exists, you will receive a password reset link shortly.']);
        return;
    }
    
    // Generate reset token
    $reset_key = wp_generate_password(20, false);
    $reset_key_hash = wp_hash_password($reset_key);
    
    // Store reset key with user (expires in 1 hour)
    $expiry = time() + HOUR_IN_SECONDS;
    update_user_meta($user->ID, 'password_reset_key', $reset_key_hash);
    update_user_meta($user->ID, 'password_reset_expiry', $expiry);
    
    // Send password reset email
    $sent = send_password_reset_email($email, $reset_key, $user->display_name);
    
    if ($sent) {
        wp_send_json_success(['message' => 'Password reset link sent! Check your email.']);
    } else {
        wp_send_json_error(['message' => 'Failed to send password reset email. Please try again.']);
    }
}

/**
 * Send password reset email
 */
function send_password_reset_email($email, $reset_key, $user_name) {
    $site_name = get_bloginfo('name');
    $subject = sprintf(__('Password Reset Request - %s', 'indoor-tasks'), $site_name);
    
    // Create reset URL
    $reset_url = add_query_arg([
        'action' => 'rp',
        'key' => $reset_key,
        'login' => urlencode($email)
    ], wp_login_url());
    
    // Get email settings
    $sender_name = get_option('indoor_tasks_email_sender_name', $site_name);
    $sender_email = get_option('indoor_tasks_email_sender_address', get_bloginfo('admin_email'));
    
    // HTML email template
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset</title>
        <style>
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                line-height: 1.6; 
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 40px 20px;
                background-color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .header { 
                text-align: center; 
                margin-bottom: 40px;
                border-bottom: 2px solid #e5e7eb;
                padding-bottom: 20px;
            }
            .logo {
                font-size: 28px;
                font-weight: bold;
                color: #4f46e5;
                margin-bottom: 8px;
            }
            .reset-button {
                display: inline-block;
                background: #4f46e5;
                color: white;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                margin: 20px 0;
                text-align: center;
            }
            .content {
                margin: 30px 0;
            }
            .warning {
                background-color: #fef3cd;
                border: 1px solid #fbbf24;
                padding: 15px;
                border-radius: 6px;
                margin: 20px 0;
            }
            .footer { 
                color: #6b7280; 
                font-size: 14px; 
                margin-top: 40px;
                text-align: center;
                border-top: 1px solid #e5e7eb;
                padding-top: 20px;
            }
            .link {
                word-break: break-all;
                color: #4f46e5;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">' . esc_html($site_name) . '</div>
                <h2 style="margin: 0; color: #374151;">Password Reset Request</h2>
            </div>
            
            <div class="content">
                <p>Hello ' . esc_html($user_name) . ',</p>
                <p>You have requested to reset your password for your account on <strong>' . esc_html($site_name) . '</strong>.</p>
                
                <p>Click the button below to reset your password:</p>
                
                <div style="text-align: center;">
                    <a href="' . esc_url($reset_url) . '" class="reset-button">Reset My Password</a>
                </div>
                
                <p>If the button doesn\'t work, you can copy and paste this link into your browser:</p>
                <p class="link">' . esc_url($reset_url) . '</p>
                
                <div class="warning">
                    <strong>⏰ This link will expire in 1 hour.</strong><br>
                    If you did not request this password reset, please ignore this email. Your account remains secure.
                </div>
            </div>
            
            <div class="footer">
                <p>Best regards,<br><strong>' . esc_html($site_name) . ' Team</strong></p>
                <p style="font-size: 12px; color: #9ca3af;">
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    // Email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $sender_name . ' <' . $sender_email . '>',
        'Reply-To: ' . $sender_email
    );
    
    return wp_mail($email, $subject, $message, $headers);
}

// Google Login Handler
add_action('wp_ajax_tk_indoor_google_login', 'handle_google_login');
add_action('wp_ajax_nopriv_tk_indoor_google_login', 'handle_google_login');

function handle_google_login() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tk-indoor-auth-nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    $id_token = isset($_POST['id_token']) ? sanitize_text_field($_POST['id_token']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $picture = isset($_POST['picture']) ? esc_url_raw($_POST['picture']) : '';
    
    if (!$id_token || !$email) {
        wp_send_json_error(['message' => 'Invalid Google login data.']);
    }
    
    // Verify the Google ID token
    if (!verify_google_id_token($id_token, $email)) {
        wp_send_json_error(['message' => 'Google authentication failed.']);
    }
    
    // Check if user exists
    $user = get_user_by('email', $email);
    
    if (!$user) {
        // Create new user account
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        $count = 1;
        $original_username = $username;
        
        // Ensure unique username
        while (username_exists($username)) {
            $username = $original_username . $count;
            $count++;
        }
        
        // Create user
        $user_id = wp_create_user($username, wp_generate_password(12, false), $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => 'Failed to create account.']);
        }
        
        // Update user profile
        $name_parts = explode(' ', $name, 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
        
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $first_name,
            'last_name' => $last_name
        ));
        
        // Save Google profile picture
        if ($picture) {
            update_user_meta($user_id, 'google_profile_picture', $picture);
        }
        
        // Mark as Google account
        update_user_meta($user_id, 'google_account', true);
        
        // Initialize user points
        update_user_meta($user_id, 'indoor_tasks_points', 0);
        
        $user = get_user_by('id', $user_id);
        
        // Log registration activity
        if (function_exists('indoor_tasks_log_user_activity')) {
            indoor_tasks_log_user_activity($user_id, 'google_registration', 'User registered via Google');
        }
    } else {
        // Update Google info for existing user
        update_user_meta($user->ID, 'google_account', true);
        if ($picture) {
            update_user_meta($user->ID, 'google_profile_picture', $picture);
        }
        
        // Log login activity
        if (function_exists('indoor_tasks_log_user_activity')) {
            indoor_tasks_log_user_activity($user->ID, 'google_login', 'User logged in via Google');
        }
    }
    
    // Log the user in
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    
    // Get redirect URL
    $redirect_url = indoor_tasks_get_page_url('dashboard');
    
    wp_send_json_success([
        'message' => 'Google login successful!',
        'redirect_url' => $redirect_url,
        'user_id' => $user->ID
    ]);
}

// Enhanced OTP Handlers
add_action('wp_ajax_tk_indoor_send_otp', 'handle_enhanced_send_otp');
add_action('wp_ajax_nopriv_tk_indoor_send_otp', 'handle_enhanced_send_otp');

function handle_enhanced_send_otp() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tk-indoor-auth-nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (!$email || !is_email($email)) {
        wp_send_json_error(['message' => 'Please provide a valid email address.']);
    }
    
    // Rate limiting - prevent too many requests
    $rate_limit_key = 'tk_otp_rate_limit_' . md5($email . $_SERVER['REMOTE_ADDR']);
    $recent_attempts = get_transient($rate_limit_key);
    
    if ($recent_attempts && $recent_attempts >= 3) {
        wp_send_json_error(['message' => 'Too many attempts. Please wait 15 minutes before requesting another code.']);
    }
    
    // Generate OTP
    $otp = sprintf('%06d', rand(100000, 999999));
    $otp_key = 'tk_indoor_otp_' . md5($email);
    
    // Store OTP with 10 minute expiration
    set_transient($otp_key, [
        'code' => $otp,
        'created' => time(),
        'attempts' => 0
    ], 10 * MINUTE_IN_SECONDS);
    
    // Increment rate limiting counter
    set_transient($rate_limit_key, ($recent_attempts ?: 0) + 1, 15 * MINUTE_IN_SECONDS);
    
    // Send OTP email
    $sent = send_enhanced_otp_email($email, $otp);
    
    if ($sent) {
        wp_send_json_success([
            'message' => 'Verification code sent successfully!',
            'expires_in' => 600 // 10 minutes
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to send verification code. Please try again.']);
    }
}

add_action('wp_ajax_tk_indoor_verify_otp', 'handle_enhanced_verify_otp');
add_action('wp_ajax_nopriv_tk_indoor_verify_otp', 'handle_enhanced_verify_otp');

function handle_enhanced_verify_otp() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tk-indoor-auth-nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $otp = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';
    
    if (!$email || !$otp) {
        wp_send_json_error(['message' => 'Email and verification code are required.']);
    }
    
    // Verify OTP
    $otp_key = 'tk_indoor_otp_' . md5($email);
    $stored_otp_data = get_transient($otp_key);
    
    if (!$stored_otp_data || !is_array($stored_otp_data)) {
        wp_send_json_error(['message' => 'Verification code has expired. Please request a new one.']);
    }
    
    // Check attempt limit
    if ($stored_otp_data['attempts'] >= 3) {
        delete_transient($otp_key);
        wp_send_json_error(['message' => 'Too many failed attempts. Please request a new code.']);
    }
    
    // Verify the code
    if ($otp !== $stored_otp_data['code']) {
        // Increment attempt counter
        $stored_otp_data['attempts']++;
        set_transient($otp_key, $stored_otp_data, 10 * MINUTE_IN_SECONDS);
        
        $remaining_attempts = 3 - $stored_otp_data['attempts'];
        wp_send_json_error([
            'message' => "Invalid verification code. {$remaining_attempts} attempts remaining."
        ]);
    }
    
    // Delete used OTP
    delete_transient($otp_key);
    
    // Clear rate limiting
    $rate_limit_key = 'tk_otp_rate_limit_' . md5($email . $_SERVER['REMOTE_ADDR']);
    delete_transient($rate_limit_key);
    
    wp_send_json_success([
        'message' => 'Email verified successfully!',
        'verified' => true
    ]);
}

/**
 * Verify Google ID Token
 */
function verify_google_id_token($id_token, $email) {
    $google_client_id = get_option('tk_indoor_google_client_id', '');
    
    if (empty($google_client_id)) {
        // If no Google client ID is configured, skip verification in development
        return true;
    }
    
    // Verify with Google's API
    $verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token;
    
    $response = wp_remote_get($verify_url, [
        'timeout' => 10
    ]);
    
    if (is_wp_error($response)) {
        error_log('Google token verification failed: ' . $response->get_error_message());
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    // Check if token is valid and for our app
    if (isset($data['aud']) && $data['aud'] === $google_client_id && 
        isset($data['email']) && $data['email'] === $email) {
        return true;
    }
    
    return false;
}

/**
 * Send enhanced OTP email
 */
function send_enhanced_otp_email($email, $otp) {
    $site_name = get_bloginfo('name');
    $subject = sprintf(__('Your %s verification code: %s', 'indoor-tasks'), $site_name, $otp);
    
    // Get email settings
    $sender_name = get_option('tk_indoor_email_sender_name', $site_name);
    $sender_email = get_option('tk_indoor_email_sender_address', get_bloginfo('admin_email'));
    
    // Enhanced HTML email template
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verification</title>
        <style>
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                line-height: 1.6; 
                color: #1f2937;
                margin: 0;
                padding: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 40px 20px;
            }
            .card {
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .header { 
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                color: white;
                text-align: center; 
                padding: 40px 20px 30px;
            }
            .logo {
                font-size: 32px;
                font-weight: bold;
                margin-bottom: 8px;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .header h2 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
                opacity: 0.95;
            }
            .content {
                padding: 40px 30px;
            }
            .otp-section {
                text-align: center;
                margin: 30px 0;
            }
            .otp-label {
                font-size: 18px;
                font-weight: 600;
                color: #374151;
                margin-bottom: 20px;
            }
            .otp-code { 
                display: inline-block;
                font-size: 36px; 
                font-weight: bold; 
                color: #4f46e5; 
                padding: 20px 40px; 
                background: linear-gradient(135deg, #f8fafc 0%, #e5e7eb 100%);
                border: 3px solid #4f46e5;
                border-radius: 16px; 
                letter-spacing: 8px;
                font-family: "Courier New", monospace;
                box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
            }
            .info-box {
                background: #f0f9ff;
                border: 1px solid #0ea5e9;
                border-left: 4px solid #0ea5e9;
                padding: 20px;
                border-radius: 8px;
                margin: 30px 0;
            }
            .warning-box {
                background: #fefce8;
                border: 1px solid #eab308;
                border-left: 4px solid #eab308;
                padding: 20px;
                border-radius: 8px;
                margin: 30px 0;
            }
            .footer { 
                background: #f9fafb;
                color: #6b7280; 
                font-size: 14px; 
                text-align: center;
                padding: 30px 20px;
                border-top: 1px solid #e5e7eb;
            }
            .security-notice {
                background: #fef2f2;
                border: 1px solid #f87171;
                border-left: 4px solid #ef4444;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                font-size: 14px;
            }
            @media (max-width: 600px) {
                .container { padding: 20px 10px; }
                .content { padding: 30px 20px; }
                .otp-code { 
                    font-size: 28px; 
                    padding: 15px 25px;
                    letter-spacing: 4px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="header">
                    <div class="logo">🚀 ' . esc_html($site_name) . '</div>
                    <h2>Email Verification</h2>
                </div>
                
                <div class="content">
                    <p style="font-size: 16px; margin-bottom: 20px;">Hello there! 👋</p>
                    
                    <p>You\'re almost ready to start your journey with <strong>' . esc_html($site_name) . '</strong>! We just need to verify your email address to ensure your account security.</p>
                    
                    <div class="otp-section">
                        <div class="otp-label">Your Verification Code</div>
                        <div class="otp-code">' . esc_html($otp) . '</div>
                    </div>
                    
                    <div class="info-box">
                        <strong>📋 How to use this code:</strong><br>
                        1. Return to the registration/login page<br>
                        2. Enter this 6-digit code when prompted<br>
                        3. Complete your account setup
                    </div>
                    
                    <div class="warning-box">
                        <strong>⏰ Important:</strong> This verification code will expire in <strong>10 minutes</strong> for security reasons.
                    </div>
                    
                    <div class="security-notice">
                        <strong>🔒 Security Notice:</strong> Never share this code with anyone. Our team will never ask for your verification code.
                    </div>
                    
                    <p style="margin-top: 30px;">If you didn\'t request this verification code, please ignore this email. Your account remains secure.</p>
                </div>
                
                <div class="footer">
                    <p><strong>' . esc_html($site_name) . ' Team</strong></p>
                    <p style="font-size: 12px; color: #9ca3af; margin-top: 10px;">
                        This is an automated message. Please do not reply to this email.
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    // Email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $sender_name . ' <' . $sender_email . '>',
        'Reply-To: ' . $sender_email,
        'X-Mailer: WordPress/' . get_bloginfo('version'),
        'X-Priority: 1 (High)',
        'X-MSMail-Priority: High',
        'Importance: High'
    );
    
    // Log email attempt
    error_log('[TK Indoor OTP] Sending OTP to: ' . $email);
    
    $result = wp_mail($email, $subject, $message, $headers);
    
    if ($result) {
        error_log('[TK Indoor OTP] Email sent successfully to: ' . $email);
    } else {
        error_log('[TK Indoor OTP] Failed to send email to: ' . $email);
    }
    
    return $result;
}

/**
 * Clear rate limiting for an email/IP combination (for development/testing)
 */
function indoor_tasks_clear_rate_limits($email = '', $ip = '') {
    if (empty($email)) $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    if (empty($ip)) $ip = $_SERVER['REMOTE_ADDR'];
    
    $keys_to_clear = [
        'login_rate_limit_' . md5($email . $ip),
        'otp_rate_limit_' . md5($email . $ip),
        'tk_otp_rate_limit_' . md5($email . $ip),
        'forgot_password_rate_limit_' . md5($email . $ip)
    ];
    
    foreach ($keys_to_clear as $key) {
        delete_transient($key);
    }
    
    error_log("Indoor Tasks: Cleared rate limits for email: $email, IP: $ip");
}

// Add AJAX handler to clear rate limits (for development only)
add_action('wp_ajax_indoor_tasks_clear_rate_limits', 'indoor_tasks_clear_rate_limits');
add_action('wp_ajax_nopriv_indoor_tasks_clear_rate_limits', 'indoor_tasks_clear_rate_limits');

/**
 * Handle standard login requests
 */
function indoor_tasks_handle_login() {
    // Debug logging
    error_log('Indoor Tasks Login Handler Called');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('Action: ' . ($_POST['action'] ?? 'not set'));
    error_log('Nonce received: ' . ($_POST['nonce'] ?? 'not set'));
    
    // Check if function exists to avoid fatal errors
    if (!function_exists('wp_verify_nonce')) {
        error_log('Indoor Tasks Login: wp_verify_nonce function not available');
        wp_send_json_error(['message' => 'WordPress functions not available.'], 500);
    }
    
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'indoor_tasks_login_nonce')) {
        error_log('Indoor Tasks Login: Nonce verification failed');
        error_log('Expected nonce action: indoor_tasks_login_nonce');
        wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.'], 403);
    }
    
    error_log('Indoor Tasks Login: Nonce verification passed');
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']) ? (bool) $_POST['remember'] : false;
    
    if (!$email || !$password) {
        wp_send_json_error(['message' => 'Email and password are required.'], 400);
    }
    
    // Verify reCAPTCHA if enabled
    $recaptcha_response = isset($_POST['recaptcha']) ? sanitize_text_field($_POST['recaptcha']) : '';
    if (!verify_recaptcha_response($recaptcha_response)) {
        wp_send_json_error(['message' => 'reCAPTCHA verification failed. Please try again.'], 400);
    }
    
    // Rate limiting - only check after we have basic validation
    $rate_limit_key = 'login_rate_limit_' . md5($email . $_SERVER['REMOTE_ADDR']);
    $recent_attempts = get_transient($rate_limit_key);
    
    // For development: disable rate limiting if WP_DEBUG is true
    $rate_limit_enabled = !defined('WP_DEBUG') || !WP_DEBUG;
    
    // Allow more attempts (10 instead of 5) and only block after many failures
    if ($rate_limit_enabled && $recent_attempts && $recent_attempts >= 10) {
        error_log("Indoor Tasks Login: Rate limit hit for $email from " . $_SERVER['REMOTE_ADDR'] . " - attempts: $recent_attempts");
        wp_send_json_error(['message' => 'Too many failed login attempts. Please wait 15 minutes before trying again.'], 429);
    }
    
    // Attempt login
    $credentials = array(
        'user_login'    => $email,
        'user_password' => $password,
        'remember'      => $remember,
    );
    
    $user = wp_signon($credentials, false);
    
    if (is_wp_error($user)) {
        // Increment rate limiting counter only if rate limiting is enabled
        if ($rate_limit_enabled) {
            set_transient($rate_limit_key, ($recent_attempts ?: 0) + 1, 15 * MINUTE_IN_SECONDS);
        }
        
        $error_message = 'Invalid email or password.';
        if ($user->get_error_code() === 'invalid_username') {
            $error_message = 'No account found with this email address.';
        } elseif ($user->get_error_code() === 'incorrect_password') {
            $error_message = 'Incorrect password. Please try again.';
        }
        
        wp_send_json_error(['message' => $error_message], 400);
    }
    
    // Clear rate limiting on successful login
    delete_transient($rate_limit_key);
    
    // Get redirect URL
    $redirect_url = get_auth_redirect_url();
    
    wp_send_json_success([
        'message' => 'Login successful! Redirecting...',
        'redirect_url' => $redirect_url,
        'user_id' => $user->ID
    ]);
}

/**
 * Handle Google authentication (new handler for JS calls)
 */
function indoor_tasks_handle_google_auth_new() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'indoor-tasks-auth-nonce')) {
        wp_send_json_error(['message' => 'Security check failed.'], 403);
    }
    
    $credential = isset($_POST['credential']) ? sanitize_text_field($_POST['credential']) : '';
    $recaptcha_token = isset($_POST['recaptcha_token']) ? sanitize_text_field($_POST['recaptcha_token']) : '';
    
    if (!$credential) {
        wp_send_json_error(['message' => 'Invalid Google credential.'], 400);
    }
    
    // Verify reCAPTCHA if enabled
    if (!verify_recaptcha_response($recaptcha_token)) {
        wp_send_json_error(['message' => 'reCAPTCHA verification failed. Please try again.'], 400);
    }
    
    // Decode the Google JWT token to get user info
    $token_parts = explode('.', $credential);
    if (count($token_parts) !== 3) {
        wp_send_json_error(['message' => 'Invalid Google token format.'], 400);
    }
    
    // Decode the payload (Google ID tokens are base64url encoded)
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $token_parts[1])), true);
    
    if (!$payload || !isset($payload['email']) || !isset($payload['name'])) {
        wp_send_json_error(['message' => 'Invalid Google token payload.'], 400);
    }
    
    $email = sanitize_email($payload['email']);
    $name = sanitize_text_field($payload['name']);
    $picture = isset($payload['picture']) ? esc_url_raw($payload['picture']) : '';
    
    // Get referral code if provided (sent from frontend)
    $referral_code = isset($_POST['referral_code']) ? sanitize_text_field($_POST['referral_code']) : '';
    
    error_log('Google Auth - Email: ' . $email . ', Name: ' . $name . ', Referral: ' . ($referral_code ? $referral_code : 'none'));
    
    // Check if user exists
    $user = get_user_by('email', $email);
    
    if (!$user) {
        // Create new user with Google prefix
        $username = generate_username_from_name($name, 'TKG-', true);
        
        $user_id = wp_create_user($username, wp_generate_password(12, false), $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => 'Failed to create account. Please try again.'], 500);
        }
        
        // Update user profile
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $name
        ));
        
        // Save Google profile picture
        if ($picture) {
            update_user_meta($user_id, 'google_profile_picture', $picture);
        }
        
        // Mark as Google account
        update_user_meta($user_id, 'google_account', true);
        update_user_meta($user_id, 'indoor_tasks_points', 0);
        
        // Generate unique referral code for this user
        $user_referral_code = generate_unique_referral_code($user_id);
        update_user_meta($user_id, 'indoor_tasks_referral_code', $user_referral_code);
        
        // Process referral if provided
        if ($referral_code) {
            error_log('Google Auth - Processing referral code: ' . $referral_code . ' for user: ' . $user_id);
            update_user_meta($user_id, 'indoor_tasks_referred_by', $referral_code);
            process_referral_bonus($referral_code, $user_id);
        }
        
        // Fire action hooks for email notifications
        do_action('indoor_tasks_user_registered', $user_id, array(
            'email' => $email,
            'name' => $name,
            'referral_code' => $referral_code,
            'user_referral_code' => $user_referral_code
        ));
        
        // Log registration activity
        if (function_exists('indoor_tasks_log_user_activity')) {
            indoor_tasks_log_user_activity($user_id, 'google_registration', 'User registered via Google');
        }
        
        $user = get_user_by('id', $user_id);
    } else {
        // Update Google info for existing user
        update_user_meta($user->ID, 'google_account', true);
        if ($picture) {
            update_user_meta($user->ID, 'google_profile_picture', $picture);
        }
        
        // Log login activity
        if (function_exists('indoor_tasks_log_user_activity')) {
            indoor_tasks_log_user_activity($user->ID, 'google_login', 'User logged in via Google');
        }
    }
    
    // Log the user in
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID);
    
    // Get redirect URL
    $redirect_url = get_auth_redirect_url();
    
    wp_send_json_success([
        'message' => 'Google authentication successful! Redirecting...',
        'redirect_url' => $redirect_url,
        'user_id' => $user->ID
    ]);
}

/**
 * Handle Firebase authentication
 */
function indoor_tasks_handle_firebase_auth() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'indoor-tasks-auth-nonce')) {
        wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.'], 403);
    }
    
    $id_token = isset($_POST['id_token']) ? sanitize_text_field($_POST['id_token']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    
    if (!$id_token || !$email) {
        wp_send_json_error(['message' => 'Invalid authentication data.'], 400);
    }
    
    // Verify Firebase ID token (you'll need to implement this based on your Firebase setup)
    if (!verify_firebase_id_token($id_token)) {
        wp_send_json_error(['message' => 'Invalid Firebase authentication token.'], 400);
    }
    
    // Check if user exists
    $user = get_user_by('email', $email);
    
    if (!$user) {
        // Create new user with Google prefix
        $display_name = $name ?: 'GoogleUser';
        $username = generate_username_from_name($display_name, 'TKG-', true);
        
        $user_id = wp_create_user($username, wp_generate_password(12, false), $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => 'Failed to create user account.'], 500);
        }
        
        // Update user profile
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name,
            'first_name' => $display_name
        ));
        
        // Mark as Google user
        update_user_meta($user_id, 'indoor_tasks_google_user', true);
        update_user_meta($user_id, 'indoor_tasks_points', 0);
        
        // Generate unique referral code for this user
        $user_referral_code = generate_unique_referral_code($user_id);
        update_user_meta($user_id, 'indoor_tasks_referral_code', $user_referral_code);
        
        // Fire action hooks for email notifications
        do_action('indoor_tasks_user_registered', $user_id, array(
            'email' => $email,
            'name' => $display_name,
            'user_referral_code' => $user_referral_code
        ));
        
        // Log registration activity
        if (function_exists('indoor_tasks_log_user_activity')) {
            indoor_tasks_log_user_activity($user_id, 'registration', 'User registered via Google');
        }
        
        $user = get_user_by('id', $user_id);
    }
    
    // Log the user in
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID);
    
    // Get redirect URL
    $redirect_url = get_auth_redirect_url();
    
    wp_send_json_success([
        'message' => 'Google login successful! Redirecting...',
        'redirect_url' => $redirect_url,
        'user_id' => $user->ID
    ]);
}

/**
 * Handle registration requests
 */
function indoor_tasks_handle_register() {
    // Enhanced debugging for 400 error diagnosis
    error_log('=== INDOOR TASKS REGISTRATION START ===');
    error_log('POST data keys: ' . implode(', ', array_keys($_POST)));
    error_log('POST action: ' . ($_POST['action'] ?? 'not set'));
    error_log('POST nonce: ' . ($_POST['nonce'] ?? 'not set'));
    
    // Security check - handle multiple nonce field possibilities for different registration flows
    $nonce_field = null;
    $nonce_value = null;
    
    // Check for different nonce fields based on registration flow
    if (isset($_POST['register_otp_verify_nonce'])) {
        $nonce_field = 'register_otp_verify_nonce';
        $nonce_value = $_POST['register_otp_verify_nonce'];
    } else if (isset($_POST['register_nonce'])) {
        $nonce_field = 'register_nonce';
        $nonce_value = $_POST['register_nonce'];
    } else if (isset($_POST['nonce'])) {
        $nonce_field = 'nonce';
        $nonce_value = $_POST['nonce'];
    }
    
    if (!$nonce_field || !$nonce_value) {
        error_log('REGISTRATION ERROR: No valid nonce field provided. Available fields: ' . implode(', ', array_keys($_POST)));
        wp_send_json_error(['message' => 'Security check failed - no nonce provided. Please refresh the page and try again.'], 400);
    }
    
    error_log('Nonce field used: ' . $nonce_field);
    error_log('Nonce value (partial): ' . substr($nonce_value, 0, 10) . '...');
    
    $nonce_check = wp_verify_nonce($nonce_value, 'indoor_tasks_register_nonce');
    error_log('Nonce verification result: ' . ($nonce_check ? 'VALID' : 'INVALID'));
    
    if (!$nonce_check) {
        error_log('REGISTRATION ERROR: Invalid nonce - Expected action: indoor_tasks_register_nonce, Field: ' . $nonce_field . ', Value: ' . substr($nonce_value, 0, 10) . '...');
        wp_send_json_error(['message' => 'Security check failed - invalid nonce. Please refresh the page and try again.'], 403);
    }
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
    $referral_code = isset($_POST['referral_code']) ? sanitize_text_field($_POST['referral_code']) : '';
    
    // Check if this is OTP verification step
    $verify_otp = isset($_POST['verify_otp']) && $_POST['verify_otp'];
    $otp_code = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';
    
    if ($verify_otp) {
        // This is the OTP verification step - verify OTP before proceeding with registration
        if (!$otp_code) {
            error_log('REGISTRATION ERROR: OTP verification requested but no OTP provided');
            wp_send_json_error(['message' => 'Verification code is required.'], 400);
        }
        
        // Verify OTP
        $otp_verified = indoor_tasks_verify_otp_code($email, $otp_code);
        if (!$otp_verified) {
            error_log('REGISTRATION ERROR: Invalid OTP provided for email: ' . $email);
            wp_send_json_error(['message' => 'Invalid verification code. Please try again.'], 400);
        }
        
        error_log('REGISTRATION SUCCESS: OTP verified for email: ' . $email);
        
        // Delete OTP after successful verification to prevent reuse
        $otp_key = 'indoor_tasks_otp_' . md5($email);
        delete_transient($otp_key);
        
        // Continue with registration process after OTP verification
    }
    
    // Log field values for debugging
    error_log('Registration fields - Email: ' . ($email ? 'provided' : 'MISSING') . 
              ', Name: ' . ($name ? 'provided' : 'MISSING') . 
              ', Phone: ' . ($phone_number ? 'provided' : 'MISSING') . 
              ', Country: ' . ($country ? 'provided' : 'MISSING'));
    
    if (!$email || !$password || !$name || !$phone_number || !$country) {
        $missing = [];
        if (!$email) $missing[] = 'email';
        if (!$password) $missing[] = 'password';
        if (!$name) $missing[] = 'name';
        if (!$phone_number) $missing[] = 'phone_number';
        if (!$country) $missing[] = 'country';
        
        error_log('REGISTRATION ERROR: Missing required fields: ' . implode(', ', $missing));
        wp_send_json_error(['message' => 'Required fields missing: ' . implode(', ', $missing)], 400);
    }
    
    // Enhanced email validation - check for disposable domains
    if (class_exists('Indoor_Tasks_Referral')) {
        $referral_system = new Indoor_Tasks_Referral();
        if ($referral_system->is_disposable_email($email)) {
            error_log('REGISTRATION ERROR: Disposable email detected - ' . $email);
            wp_send_json_error(['message' => 'Please use a valid email address. Temporary email services are not allowed.'], 400);
        }
    }
    
    // Verify reCAPTCHA if enabled (skip for OTP verification step)
    if (!$verify_otp) {
        $recaptcha_response = isset($_POST['recaptcha_token']) ? sanitize_text_field($_POST['recaptcha_token']) : '';
        if (!verify_recaptcha_response($recaptcha_response)) {
            error_log('REGISTRATION ERROR: reCAPTCHA verification failed');
            wp_send_json_error(['message' => 'reCAPTCHA verification failed. Please try again.'], 400);
        }
    } else {
        error_log('REGISTRATION: Skipping reCAPTCHA verification for OTP verification step');
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        error_log('REGISTRATION ERROR: Password too short');
        wp_send_json_error(['message' => 'Password must be at least 8 characters long.'], 400);
    }
    
    // Check if user already exists
    if (email_exists($email)) {
        error_log('REGISTRATION ERROR: Email already exists - ' . $email);
        wp_send_json_error(['message' => 'An account with this email already exists.'], 400);
    }
    
    error_log('Registration validation passed - proceeding with user creation');
    
    // Generate username using new system (TK- prefix for manual registration)
    $username = generate_username_from_name($name, 'TK-', false);
    
    // Create user
    $user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => 'Failed to create account. Please try again.'], 500);
    }
    
    // Update user profile
    wp_update_user(array(
        'ID' => $user_id,
        'display_name' => $name,
        'first_name' => $name
    ));
    
    // Save additional metadata
    if ($country) {
        update_user_meta($user_id, 'indoor_tasks_country', $country);
    }
    
    if ($phone_number) {
        update_user_meta($user_id, 'indoor_tasks_phone_number', $phone_number);
    }
    
    // Store user IP for referral validation
    $user_ip = get_user_ip();
    update_user_meta($user_id, 'indoor_tasks_last_ip', $user_ip);
    
    // Initialize user points
    update_user_meta($user_id, 'indoor_tasks_points', 0);
    
    // Generate unique referral code for this user
    $user_referral_code = generate_unique_referral_code($user_id);
    update_user_meta($user_id, 'indoor_tasks_referral_code', $user_referral_code);
    
    // Process referral if provided - now handled by the new referral system
    if ($referral_code) {
        update_user_meta($user_id, 'indoor_tasks_referred_by', $referral_code);
        
        // Process referral bonus using existing function
        process_referral_bonus($referral_code, $user_id);
    }
    
    // Fire action hooks for email notifications and referral processing
    do_action('indoor_tasks_user_registered', $user_id, array(
        'email' => $email,
        'name' => $name,
        'country' => $country,
        'phone_number' => $phone_number,
        'referral_code' => $referral_code,
        'user_referral_code' => $user_referral_code,
        'ip_address' => $user_ip
    ));
    
    // Log the user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    
    // Log registration activity
    if (function_exists('indoor_tasks_log_user_activity')) {
        indoor_tasks_log_user_activity($user_id, 'registration', 'User registered successfully');
    }
    
    // Get redirect URL
    $redirect_url = get_auth_redirect_url();
    
    wp_send_json_success([
        'message' => 'Registration successful! Redirecting...',
        'redirect_url' => $redirect_url,
        'user_id' => $user_id
    ]);
}

/**
 * Handle forgot password requests
 */
function indoor_tasks_handle_forgot_password_request() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'indoor_tasks_forgot_password_nonce')) {
        wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.'], 403);
    }
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (!$email) {
        wp_send_json_error(['message' => 'Email address is required.'], 400);
    }
    
    // Verify reCAPTCHA if enabled
    $recaptcha_response = isset($_POST['recaptcha']) ? sanitize_text_field($_POST['recaptcha']) : '';
    if (!verify_recaptcha_response($recaptcha_response)) {
        wp_send_json_error(['message' => 'reCAPTCHA verification failed. Please try again.'], 400);
    }
    
    // Rate limiting
    $rate_limit_key = 'forgot_password_rate_limit_' . md5($email . $_SERVER['REMOTE_ADDR']);
    $recent_attempts = get_transient($rate_limit_key);
    
    // For development: disable rate limiting if WP_DEBUG is true
    $rate_limit_enabled = !defined('WP_DEBUG') || !WP_DEBUG;
    
    if ($rate_limit_enabled && $recent_attempts && $recent_attempts >= 3) {
        wp_send_json_error(['message' => 'Too many password reset requests. Please wait 15 minutes before trying again.'], 429);
    }
    
    // Check if user exists
    $user = get_user_by('email', $email);
    
    if (!$user) {
        // For security, don't reveal whether the email exists or not
        wp_send_json_success(['message' => 'If an account with this email exists, you will receive password reset instructions.']);
    }
    
    // Generate reset key
    $reset_key = get_password_reset_key($user);
    
    if (is_wp_error($reset_key)) {
        wp_send_json_error(['message' => 'Failed to generate reset key. Please try again.'], 500);
    }
    
    // Send reset email
    $sent = send_password_reset_email($user->user_email, $reset_key, $user->display_name);
    
    if ($sent) {
        // Increment rate limiting counter only if rate limiting is enabled
        if ($rate_limit_enabled) {
            set_transient($rate_limit_key, ($recent_attempts ?: 0) + 1, 15 * MINUTE_IN_SECONDS);
        }
        
        wp_send_json_success(['message' => 'Password reset instructions have been sent to your email.']);
    } else {
        wp_send_json_error(['message' => 'Failed to send reset email. Please try again.'], 500);
    }
}

/**
 * Generate a unique referral code for a user
 */
function generate_unique_referral_code($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    
    // Generate code based on user data
    $name_part = substr(preg_replace('/[^A-Za-z0-9]/', '', $user->display_name), 0, 4);
    $id_part = str_pad($user_id, 4, '0', STR_PAD_LEFT);
    $random_part = strtoupper(substr(md5(uniqid()), 0, 4));
    
    $referral_code = $name_part . $id_part . $random_part;
    
    // Ensure it's unique
    $existing = get_users([
        'meta_key' => 'indoor_tasks_referral_code',
        'meta_value' => $referral_code,
        'number' => 1
    ]);
    
    // If not unique, add more random characters
    $counter = 1;
    while (!empty($existing)) {
        $referral_code = $name_part . $id_part . $random_part . $counter;
        $existing = get_users([
            'meta_key' => 'indoor_tasks_referral_code',
            'meta_value' => $referral_code,
            'number' => 1
        ]);
        $counter++;
        
        // Prevent infinite loop
        if ($counter > 100) {
            $referral_code = 'USER' . $user_id . time();
            break;
        }
    }
    
    return strtoupper($referral_code);
}

// Add AJAX endpoint for email validation on the front-end
add_action('wp_ajax_validate_email_domain', 'indoor_tasks_validate_email_domain');
add_action('wp_ajax_nopriv_validate_email_domain', 'indoor_tasks_validate_email_domain');

function indoor_tasks_validate_email_domain() {
    if (!isset($_POST['email'])) {
        wp_send_json_error('Email is required');
    }
    
    $email = sanitize_email($_POST['email']);
    
    if (class_exists('Indoor_Tasks_Referral')) {
        $referral_system = new Indoor_Tasks_Referral();
        if ($referral_system->is_disposable_email($email)) {
            wp_send_json_error('Please use a valid email address. Temporary email services are not allowed.');
        }
    }
    
    wp_send_json_success('Email is valid');
}

// Add enhanced debugging and error handling to help identify authentication issues

// Enhanced debugging mode
define('INDOOR_TASKS_DEBUG_AUTH', defined('WP_DEBUG') && WP_DEBUG);

// Enhanced error logging function
function indoor_tasks_log_error($message, $data = null) {
    $log_message = '[Indoor Tasks Auth] ' . $message;
    if ($data) {
        $log_message .= ' | Data: ' . print_r($data, true);
    }
    error_log($log_message);
    
    // In debug mode, also log to a custom file
    if (INDOOR_TASKS_DEBUG_AUTH) {
        $log_file = INDOOR_TASKS_PATH . 'debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] $log_message\n", FILE_APPEND | LOCK_EX);
    }
}

// Enhanced success logging function
function indoor_tasks_log_success($message, $data = null) {
    if (INDOOR_TASKS_DEBUG_AUTH) {
        indoor_tasks_log_error($message, $data);
    }
}

// Enhanced auth request handler with better error handling
function indoor_tasks_handle_auth_request_enhanced() {
    // Enhanced debugging
    indoor_tasks_log_auth_error('Auth request received', $_POST);
    
    // Security check with detailed error
    if (!isset($_POST['nonce'])) {
        indoor_tasks_log_auth_error('No nonce provided in request');
        wp_send_json_error(['message' => 'Security check failed: No nonce provided.'], 400);
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'indoor-tasks-auth-nonce')) {
        indoor_tasks_log_auth_error('Nonce verification failed', [
            'provided_nonce' => $_POST['nonce'],
            'expected_action' => 'indoor-tasks-auth-nonce'
        ]);
        wp_send_json_error(['message' => 'Security check failed: Invalid nonce.'], 400);
    }
    
    // Call original handler
    return indoor_tasks_handle_auth_request();
}
