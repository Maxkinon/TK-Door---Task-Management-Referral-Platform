<?php
/**
 * Template Name: Indoor Tasks Authentication
 * Description: Modern authentication template for Indoor Tasks plugin
 */

// Prevent direct file access
defined('ABSPATH') || exit;

// Get plugin settings from admin area
$recaptcha_enabled = get_option('indoor_tasks_enable_recaptcha', 0);
$recaptcha_site_key = get_option('indoor_tasks_recaptcha_site_key', '');
$recaptcha_version = get_option('indoor_tasks_recaptcha_version', 'v2');

// Prepare data for JavaScript
// Get Google client ID from settings
$google_client_id = get_option('indoor_tasks_google_client_id', '');
$google_enabled = get_option('indoor_tasks_enable_google_login', 0) && !empty($google_client_id);

$script_data = [
    'ajaxurl' => admin_url('admin-ajax.php'),
    'home_url' => home_url(),
    'recaptcha_enabled' => $recaptcha_enabled,
    'recaptcha_site_key' => $recaptcha_site_key,
    'google_enabled' => $google_enabled,
    'google_client_id' => $google_client_id,
    'nonces' => [
        'firebase' => wp_create_nonce('indoor-tasks-auth-nonce'),
        'auth' => wp_create_nonce('indoor-tasks-auth-nonce'),
        'login' => wp_create_nonce('indoor_tasks_login_nonce'),
        'register' => wp_create_nonce('indoor_tasks_register_nonce'),
        'forgot' => wp_create_nonce('indoor_tasks_forgot_password_nonce')
    ]
];

// Store redirect URL if set
$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
if (!empty($redirect_to)) {
    $_SESSION['indoor_tasks_redirect_after_login'] = $redirect_to;
}

// Handle referral code from URL or cookie
$referral_code_prefill = '';
if (isset($_GET['ref']) || isset($_GET['referral'])) {
    $referral_code_prefill = sanitize_text_field($_GET['ref'] ?? $_GET['referral']);
} elseif (isset($_COOKIE['indoor_tasks_referral'])) {
    $referral_code_prefill = sanitize_text_field($_COOKIE['indoor_tasks_referral']);
}

// Check if user is already logged in
$is_logged_in = is_user_logged_in();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <title><?php echo wp_get_document_title(); ?></title>
    
    <!-- Essential styles and scripts -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tk-indoor-auth.css?ver=1.0.1">
    <script src="<?php echo includes_url('js/jquery/jquery.min.js'); ?>"></script>
    
    <!-- Google Sign-In -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    
    <?php if ($recaptcha_enabled): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr($recaptcha_site_key); ?>" async defer></script>
    <?php endif; ?>

    <!-- Firebase Config -->
    <?php 
    if (class_exists('Indoor_Tasks_Firebase')) {
        $firebase = new Indoor_Tasks_Firebase();
        $firebase_config = $firebase->get_firebase_config();
        if (!empty($firebase_config['apiKey'])) : ?>
            <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
            <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-auth-compat.js"></script>
            <script>
                var indoor_tasks_firebase = <?php echo json_encode($firebase_config); ?>;
            </script>
            <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/firebase-auth.js?ver=1.0.0"></script>
        <?php endif; 
    } else {
        // Firebase class not found, disable Google login
        echo '<!-- Firebase class not found -->';
    } ?>
    
    <script>
        var tkAuth = <?php echo json_encode($script_data); ?>;
        tkAuth.nonces = {
            login: '<?php echo wp_create_nonce("indoor_tasks_login_nonce"); ?>',
            register: '<?php echo wp_create_nonce("indoor_tasks_register_nonce"); ?>',
            forgot_password: '<?php echo wp_create_nonce("indoor_tasks_forgot_password_nonce"); ?>',
            auth: '<?php echo wp_create_nonce("indoor-tasks-auth-nonce"); ?>'
        };
    </script>
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tk-indoor-auth.js?ver=1.0.1" defer></script>
</head>
<body <?php body_class('tk-auth-page'); ?>>

    <div class="tk-auth-container">
        <!-- Left side: Authentication forms -->
        <div class="tk-auth-content">
            <?php if ($is_logged_in): ?>
                <div class="tk-auth-card tk-auth-logged-in">
                    <img src="<?php echo INDOOR_TASKS_URL; ?>assets/image/indoor-tasks-logo.webp" alt="Indoor Tasks Logo" class="tk-auth-logo">
                    <h2 class="tk-auth-title">Welcome Back!</h2>
                    <p>You are already logged in to your account.</p>
                    <a href="<?php echo home_url('/dashboard/'); ?>" class="tk-auth-btn">Go to Dashboard</a>
                    <a href="<?php echo wp_logout_url(home_url()); ?>" class="tk-auth-secondary-btn">Logout</a>
                </div>
            <?php else: ?>
                <div class="tk-auth-inner">
                    <img src="<?php echo INDOOR_TASKS_URL; ?>assets/image/indoor-tasks-logo.webp" alt="Indoor Tasks Logo" class="tk-auth-logo">

                    <!-- Login Form -->
                    <div class="tk-auth-card" id="tk-login-card">
                        <h2 class="tk-auth-title">Welcome Back</h2>
                        <p class="tk-auth-subtitle">Sign in to continue to Indoor Tasks</p>

                        <form class="tk-auth-form" id="tk-login-form" method="post">
                            <?php wp_nonce_field('indoor_tasks_login_nonce', 'login_nonce'); ?>
                            <?php wp_nonce_field('indoor-tasks-auth-nonce', 'auth_nonce'); ?>
                            
                            <div class="tk-form-group">
                                <label for="login-username">Username or Email</label>
                                <input type="text" id="login-username" name="username" placeholder="Enter your username or email" required>
                            </div>

                            <div class="tk-form-group">
                                <div class="tk-password-header">
                                    <label for="login-password">Password</label>
                                    <a href="#" id="tk-show-forgot" class="tk-forgot-link">Forgot Password?</a>
                                </div>
                                <div class="tk-password-field">
                                    <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
                                    <button type="button" class="tk-password-toggle"></button>
                                </div>
                            </div>

                            <div class="tk-remember-me">
                                <input type="checkbox" id="login-remember" name="remember">
                                <label for="login-remember">Keep me signed in</label>
                            </div>

                            <?php if ($recaptcha_enabled): ?>
                                <input type="hidden" name="recaptcha_token" id="recaptcha_token_login">
                            <?php endif; ?>

                            <button type="submit" class="tk-auth-btn">Sign In</button>
                        </form>

                        <div class="tk-social-login">
                            <div class="tk-divider">
                                <span>Or continue with</span>
                            </div>
                            <button type="button" class="tk-google-btn" id="google-login-btn" style="display: block;">
                                <img src="<?php echo INDOOR_TASKS_URL; ?>assets/icons/google.svg" alt="Google">
                                Sign in with Google
                            </button>
                            <button type="button" class="tk-otp-btn" id="tk-show-otp-login">
                                <img src="<?php echo INDOOR_TASKS_URL; ?>assets/icons/mail.svg" alt="Email">
                                Sign in with OTP
                            </button>
                        </div>

                        <div class="tk-auth-links">
                            Don't have an account? <a href="#" id="tk-show-register">Create one now</a>
                        </div>
                    </div>

                    <!-- Registration Form -->
                    <div class="tk-auth-card" id="tk-register-card" style="display:none;">
                        <h2 class="tk-auth-title">Create Account</h2>
                        <p class="tk-auth-subtitle">Join Indoor Tasks to start earning</p>

                        <form class="tk-auth-form" id="tk-register-form">
                            <?php wp_nonce_field('indoor_tasks_register_nonce', 'register_nonce'); ?>
                            <?php wp_nonce_field('indoor-tasks-auth-nonce', 'auth_nonce'); ?>
                            
                            <div class="tk-form-group">
                                <label for="register-email">Email Address</label>
                                <input type="email" id="register-email" name="email" placeholder="Enter your email" required>
                            </div>

                            <div class="tk-form-group">
                                <label for="register-name">Full Name</label>
                                <input type="text" id="register-name" name="name" placeholder="Enter your full name" required>
                            </div>

                            <div class="tk-form-group">
                                <label for="register-phone">Phone Number</label>
                                <input type="tel" id="register-phone" name="phone_number" placeholder="Enter your phone number" required>
                            </div>

                            <div class="tk-form-group">
                                <label for="register-country">Country</label>
                                <div class="tk-select-wrapper">
                                    <select id="register-country" name="country" required>
                                        <option value="">Select your country</option>
                                        <?php
                                    $countries = array(
                                        'AF' => 'Afghanistan',
                                        'AL' => 'Albania',
                                        'DZ' => 'Algeria',
                                        'AR' => 'Argentina',
                                        'AU' => 'Australia',
                                        'AT' => 'Austria',
                                        'BD' => 'Bangladesh',
                                        'BE' => 'Belgium',
                                        'BR' => 'Brazil',
                                        'BG' => 'Bulgaria',
                                        'CA' => 'Canada',
                                        'CL' => 'Chile',
                                        'CN' => 'China',
                                        'CO' => 'Colombia',
                                        'HR' => 'Croatia',
                                        'CZ' => 'Czech Republic',
                                        'DK' => 'Denmark',
                                        'EG' => 'Egypt',
                                        'FI' => 'Finland',
                                        'FR' => 'France',
                                        'DE' => 'Germany',
                                        'GR' => 'Greece',
                                        'HU' => 'Hungary',
                                        'IN' => 'India',
                                        'ID' => 'Indonesia',
                                        'IR' => 'Iran',
                                        'IQ' => 'Iraq',
                                        'IE' => 'Ireland',
                                        'IT' => 'Italy',
                                        'JP' => 'Japan',
                                        'MY' => 'Malaysia',
                                        'MX' => 'Mexico',
                                        'NL' => 'Netherlands',
                                        'NG' => 'Nigeria',
                                        'NO' => 'Norway',
                                        'PK' => 'Pakistan',
                                        'PH' => 'Philippines',
                                        'PL' => 'Poland',
                                        'PT' => 'Portugal',
                                        'RO' => 'Romania',
                                        'RU' => 'Russia',
                                        'SA' => 'Saudi Arabia',
                                        'SG' => 'Singapore',
                                        'ZA' => 'South Africa',
                                        'KR' => 'South Korea',
                                        'ES' => 'Spain',
                                        'LK' => 'Sri Lanka',
                                        'SE' => 'Sweden',
                                        'CH' => 'Switzerland',
                                        'TH' => 'Thailand',
                                        'TR' => 'Turkey',
                                        'UA' => 'Ukraine',
                                        'AE' => 'United Arab Emirates',
                                        'GB' => 'United Kingdom',
                                        'US' => 'United States',
                                        'VN' => 'Vietnam'
                                    );
                                    foreach ($countries as $code => $name) {
                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                    }
                                    ?>
                                    </select>
                                </div>
                            </div>

                            <div class="tk-form-group">
                                <label for="register-referral">Referral Code (Optional)</label>
                                <input type="text" id="register-referral" name="referral_code" placeholder="Enter referral code if you have one" value="<?php echo esc_attr($referral_code_prefill); ?>">
                            </div>

                            <div class="tk-form-group">
                                <label for="register-password">Password</label>
                                <div class="tk-password-field">
                                    <input type="password" id="register-password" name="password" placeholder="Create a strong password" required>
                                    <button type="button" class="tk-password-toggle"></button>
                                </div>
                                <div class="tk-password-strength">
                                    <div class="tk-password-strength-bar"></div>
                                </div>
                            </div>

                            <?php if ($recaptcha_enabled): ?>
                                <input type="hidden" name="recaptcha_token" id="recaptcha_token_register">
                            <?php endif; ?>

                            <button type="submit" class="tk-auth-btn">Create Account</button>
                        </form>

                        <div class="tk-social-login">
                            <div class="tk-divider">
                                <span>Or sign up with</span>
                            </div>
                            <button type="button" class="tk-google-btn" id="google-register-btn" style="display: block;">
                                <img src="<?php echo INDOOR_TASKS_URL; ?>assets/icons/google.svg" alt="Google">
                                Sign up with Google
                            </button>
                        </div>

                        <div class="tk-auth-links">
                            Already have an account? <a href="#" id="tk-back-to-login">Sign in</a>
                        </div>
                    </div>

                    <!-- Registration OTP Verification Form -->
                    <div class="tk-auth-card" id="tk-register-otp-verify-card" style="display:none;">
                        <h2 class="tk-auth-title">Verify Your Email</h2>
                        <p class="tk-auth-subtitle">Enter the 6-digit code sent to your email to complete registration</p>

                        <form class="tk-auth-form" id="tk-register-otp-verify-form">
                            <?php wp_nonce_field('indoor_tasks_register_nonce', 'register_otp_verify_nonce'); ?>
                            <?php wp_nonce_field('indoor-tasks-auth-nonce', 'auth_nonce'); ?>
                            
                            <input type="hidden" id="register-otp-email" name="email">
                            
                            <div class="tk-form-group">
                                <label for="register-otp-code">Verification Code</label>
                                <input type="text" id="register-otp-code" name="otp" placeholder="Enter 6-digit code" maxlength="6" required>
                            </div>

                            <div class="tk-otp-actions">
                                <button type="submit" class="tk-auth-btn">Verify & Complete Registration</button>
                                <button type="button" class="tk-auth-secondary-btn" id="tk-resend-register-otp">Resend Code</button>
                            </div>
                        </form>

                        <div class="tk-auth-links">
                            <a href="#" id="tk-back-to-register">Back to Registration</a>
                        </div>
                    </div>

                    <!-- Password Reset Form -->
                    <div class="tk-auth-card" id="tk-reset-card" style="display:none;">
                        <h2 class="tk-auth-title">Reset Password</h2>
                        <p class="tk-auth-subtitle">Enter your email to receive a password reset link</p>

                        <form class="tk-auth-form" id="tk-reset-form">
                            <?php wp_nonce_field('indoor_tasks_forgot_password_nonce', 'reset_nonce'); ?>
                            
                            <div class="tk-form-group">
                                <label for="reset-email">Email Address</label>
                                <input type="email" id="reset-email" name="email" placeholder="Enter your email" required>
                            </div>

                            <?php if ($recaptcha_enabled): ?>
                                <input type="hidden" name="recaptcha_token" id="recaptcha_token_reset">
                            <?php endif; ?>

                            <button type="submit" class="tk-auth-btn">Send Reset Link</button>
                        </form>

                        <div class="tk-auth-links">
                            <a href="#" id="tk-back-to-login-reset">Back to Sign In</a>
                        </div>
                    </div>

                    <!-- OTP Login Form -->
                    <div class="tk-auth-card" id="tk-otp-login-card" style="display:none;">
                        <h2 class="tk-auth-title">Sign In with OTP</h2>
                        <p class="tk-auth-subtitle">We'll send a verification code to your email</p>

                        <form class="tk-auth-form" id="tk-otp-request-form">
                            <?php wp_nonce_field('indoor-tasks-auth-nonce', 'otp_nonce'); ?>
                            
                            <div class="tk-form-group">
                                <label for="otp-email">Email Address</label>
                                <input type="email" id="otp-email" name="email" placeholder="Enter your email" required>
                            </div>

                            <?php if ($recaptcha_enabled): ?>
                                <input type="hidden" name="recaptcha_token" id="recaptcha_token_otp">
                            <?php endif; ?>

                            <button type="submit" class="tk-auth-btn">Send OTP</button>
                        </form>

                        <div class="tk-auth-links">
                            <a href="#" id="tk-back-to-login-otp">Back to Sign In</a>
                        </div>
                    </div>

                    <!-- OTP Verification Form -->
                    <div class="tk-auth-card" id="tk-otp-verify-card" style="display:none;">
                        <h2 class="tk-auth-title">Verify OTP</h2>
                        <p class="tk-auth-subtitle">Enter the 6-digit code sent to your email</p>

                        <form class="tk-auth-form" id="tk-otp-verify-form">
                            <?php wp_nonce_field('indoor-tasks-auth-nonce', 'otp_verify_nonce'); ?>
                            
                            <input type="hidden" id="otp-verify-email" name="email">
                            
                            <div class="tk-form-group">
                                <label for="otp-code">Verification Code</label>
                                <input type="text" id="otp-code" name="otp" placeholder="Enter 6-digit code" maxlength="6" required>
                            </div>

                            <div class="tk-otp-actions">
                                <button type="submit" class="tk-auth-btn">Verify & Sign In</button>
                                <button type="button" class="tk-auth-secondary-btn" id="tk-resend-otp">Resend Code</button>
                            </div>
                        </form>

                        <div class="tk-auth-links">
                            <a href="#" id="tk-back-to-otp-request">Change Email</a>
                        </div>
                    </div>

                    <!-- OTP Registration Form -->
                    <div class="tk-auth-card" id="tk-otp-register-card" style="display:none;">
                        <h2 class="tk-auth-title">Complete Registration</h2>
                        <p class="tk-auth-subtitle">Please provide your details to create your account</p>

                        <form class="tk-auth-form" id="tk-otp-register-form">
                            <?php wp_nonce_field('indoor-tasks-auth-nonce', 'otp_register_nonce'); ?>
                            
                            <input type="hidden" id="otp-register-email" name="email">
                            <input type="hidden" id="otp-register-code" name="otp">
                            
                            <div class="tk-form-group">
                                <label for="otp-register-name">Full Name</label>
                                <input type="text" id="otp-register-name" name="name" placeholder="Enter your full name" required>
                            </div>

                            <div class="tk-form-group">
                                <label for="otp-register-country">Country</label>
                                <div class="tk-select-wrapper">
                                    <select id="otp-register-country" name="country" required>
                                        <option value="">Select your country</option>
                                        <?php
                                        $countries = array(
                                            'AF' => 'Afghanistan',
                                            'AL' => 'Albania',
                                            'DZ' => 'Algeria',
                                            'AR' => 'Argentina',
                                            'AU' => 'Australia',
                                            'AT' => 'Austria',
                                            'BD' => 'Bangladesh',
                                            'BE' => 'Belgium',
                                            'BR' => 'Brazil',
                                            'BG' => 'Bulgaria',
                                            'CA' => 'Canada',
                                            'CL' => 'Chile',
                                            'CN' => 'China',
                                            'CO' => 'Colombia',
                                            'HR' => 'Croatia',
                                            'CZ' => 'Czech Republic',
                                            'DK' => 'Denmark',
                                            'EG' => 'Egypt',
                                            'FI' => 'Finland',
                                            'FR' => 'France',
                                            'DE' => 'Germany',
                                            'GR' => 'Greece',
                                            'HU' => 'Hungary',
                                            'IN' => 'India',
                                            'ID' => 'Indonesia',
                                            'IR' => 'Iran',
                                            'IQ' => 'Iraq',
                                            'IE' => 'Ireland',
                                            'IT' => 'Italy',
                                            'JP' => 'Japan',
                                            'MY' => 'Malaysia',
                                            'MX' => 'Mexico',
                                            'NL' => 'Netherlands',
                                            'NG' => 'Nigeria',
                                            'NO' => 'Norway',
                                            'PK' => 'Pakistan',
                                            'PH' => 'Philippines',
                                            'PL' => 'Poland',
                                            'PT' => 'Portugal',
                                            'RO' => 'Romania',
                                            'RU' => 'Russia',
                                            'SA' => 'Saudi Arabia',
                                            'SG' => 'Singapore',
                                            'ZA' => 'South Africa',
                                            'KR' => 'South Korea',
                                            'ES' => 'Spain',
                                            'LK' => 'Sri Lanka',
                                            'SE' => 'Sweden',
                                            'CH' => 'Switzerland',
                                            'TH' => 'Thailand',
                                            'TR' => 'Turkey',
                                            'UA' => 'Ukraine',
                                            'AE' => 'United Arab Emirates',
                                            'GB' => 'United Kingdom',
                                            'US' => 'United States',
                                            'VN' => 'Vietnam'
                                        );
                                        foreach ($countries as $code => $name) {
                                            echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="tk-form-group">
                                <label for="otp-register-phone">Phone Number</label>
                                <input type="tel" id="otp-register-phone" name="phone_number" placeholder="Enter your phone number" required>
                            </div>

                            <div class="tk-form-group">
                                <label for="otp-register-referral">Referral Code (Optional)</label>
                                <input type="text" id="otp-register-referral" name="referral_code" placeholder="Enter referral code if you have one" value="<?php echo esc_attr($referral_code_prefill); ?>">
                            </div>

                            <button type="submit" class="tk-auth-btn">Create Account</button>
                        </form>

                        <div class="tk-auth-links">
                            <a href="#" id="tk-back-to-otp-verify">Back to Verification</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right side: Branding image and content -->
        <div class="tk-auth-image">
            <div class="tk-auth-image-overlay">
                <div class="tk-auth-image-content">
                    <h2>Complete Tasks, Earn Rewards</h2>
                    <p>Join our community of task completers and start earning rewards for your skills and dedication.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($recaptcha_enabled): ?>
    <script>
        // reCAPTCHA token refresh
        function refreshRecaptcha(action) {
            grecaptcha.ready(function() {
                grecaptcha.execute('<?php echo esc_js($recaptcha_site_key); ?>', {action: action})
                .then(function(token) {
                    document.getElementById('recaptcha_token_' + action).value = token;
                });
            });
        }
        
        // Initialize reCAPTCHA tokens
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof grecaptcha !== 'undefined') {
                refreshRecaptcha('login');
            }
        });
    </script>
    <?php endif; ?>

    <?php wp_footer(); ?>
</body>
</html>