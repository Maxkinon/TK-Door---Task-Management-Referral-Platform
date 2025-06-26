<?php
/**
 * Template Name: TKM Door - Help Desk
 * Description: Modern help desk and support center template
 * Version: 1.0.0
 */

// Prevent direct file access
defined('ABSPATH') || exit;

// Redirect if not logged in
if (!is_user_logged_in()) {
    // Try to find auth page
    $login_page = null;
    if (function_exists('indoor_tasks_get_page_by_template')) {
        $login_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tk-indoor-auth.php', 'login');
    }
    
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

// Get page title
$page_title = 'Help Desk / Support Center';

// Set global template variable for sidebar detection
$GLOBALS['indoor_tasks_current_template'] = 'tkm-door-helpdesk.php';

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo esc_html($page_title); ?> - <?php bloginfo('name'); ?></title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Help Desk Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-helpdesk.css?ver=1.0.0">
    
    <?php wp_head(); ?>
</head>

<body class="tkm-door-helpdesk">
    <div class="tkm-wrapper">
        <!-- Include Sidebar Navigation -->
        <?php include_once(INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'); ?>
        
        <div class="tkm-main-content">
            <div class="tkm-container">
                <!-- Header Section -->
                <div class="tkm-header">
                    <div class="tkm-header-content">
                        <h1 class="tkm-title"><?php echo esc_html($page_title); ?></h1>
                        <p class="tkm-subtitle">Get instant help and support through our various channels</p>
                    </div>
                </div>

                <!-- Support Contact Options Section -->
                <div class="tkm-support-section">
                    <h2 class="tkm-section-title">
                        <i class="tkm-icon">💬</i>
                        Contact Support
                    </h2>
                    <p class="tkm-section-description">Choose your preferred method to get in touch with our support team</p>
                    
                    <div class="tkm-support-grid">
                        <!-- Telegram Support -->
                        <div class="tkm-support-card">
                            <div class="tkm-support-icon telegram">
                                <i class="fab fa-telegram-plane"></i>
                            </div>
                            <h3>Telegram Support</h3>
                            <p>Get instant help from our support team via Telegram chat</p>
                            <a href="https://t.me/yoursupportgroup" target="_blank" class="tkm-support-btn telegram-btn">
                                <i class="fab fa-telegram-plane"></i>
                                Join Telegram Support Group
                            </a>
                        </div>

                        <!-- WhatsApp Support -->
                        <div class="tkm-support-card">
                            <div class="tkm-support-icon whatsapp">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            <h3>WhatsApp Support</h3>
                            <p>Chat with our support team directly on WhatsApp</p>
                            <a href="https://wa.me/1234567890" target="_blank" class="tkm-support-btn whatsapp-btn">
                                <i class="fab fa-whatsapp"></i>
                                Chat on WhatsApp
                            </a>
                        </div>

                        <!-- Telegram Channel -->
                        <div class="tkm-support-card">
                            <div class="tkm-support-icon telegram-channel">
                                <i class="fab fa-telegram-plane"></i>
                            </div>
                            <h3>Telegram Channel</h3>
                            <p>Stay updated with announcements and important news</p>
                            <a href="https://t.me/yourchannelname" target="_blank" class="tkm-support-btn telegram-channel-btn">
                                <i class="fab fa-telegram-plane"></i>
                                Join our Telegram Channel
                            </a>
                        </div>

                        <!-- WhatsApp Channel -->
                        <div class="tkm-support-card">
                            <div class="tkm-support-icon whatsapp-channel">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            <h3>WhatsApp Channel</h3>
                            <p>Receive updates and announcements via WhatsApp</p>
                            <a href="https://whatsapp.com/channel/yourchannelid" target="_blank" class="tkm-support-btn whatsapp-channel-btn">
                                <i class="fab fa-whatsapp"></i>
                                Join our WhatsApp Channel
                            </a>
                        </div>
                    </div>
                </div>

                <!-- FAQ Section -->
                <div class="tkm-faq-section">
                    <h2 class="tkm-section-title">
                        <i class="tkm-icon">❓</i>
                        Frequently Asked Questions
                    </h2>
                    <p class="tkm-section-description">Find quick answers to common questions</p>
                    
                    <div class="tkm-faq-container">
                        <!-- FAQ Item 1 -->
                        <div class="tkm-faq-item">
                            <button class="tkm-faq-question" data-faq="1">
                                <span class="tkm-faq-text">How long does it take to process my withdrawal?</span>
                                <span class="tkm-faq-arrow">
                                    <i class="fas fa-chevron-down"></i>
                                </span>
                            </button>
                            <div class="tkm-faq-answer" id="faq-1">
                                <div class="tkm-faq-content">
                                    <p>Withdrawals are usually processed within 2-3 business days. However, processing times may vary depending on your chosen withdrawal method and banking institution. We recommend checking your account regularly for updates on your withdrawal status.</p>
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Item 2 -->
                        <div class="tkm-faq-item">
                            <button class="tkm-faq-question" data-faq="2">
                                <span class="tkm-faq-text">Why is my task submission rejected?</span>
                                <span class="tkm-faq-arrow">
                                    <i class="fas fa-chevron-down"></i>
                                </span>
                            </button>
                            <div class="tkm-faq-answer" id="faq-2">
                                <div class="tkm-faq-content">
                                    <p>Task submissions may be rejected for several reasons:</p>
                                    <ul>
                                        <li>Incomplete or missing required information</li>
                                        <li>Poor quality or invalid proof uploads</li>
                                        <li>Not following the specific task guidelines</li>
                                        <li>Submitting duplicate or fraudulent content</li>
                                    </ul>
                                    <p>Please review the task requirements carefully and ensure all guidelines are followed before resubmitting.</p>
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Item 3 -->
                        <div class="tkm-faq-item">
                            <button class="tkm-faq-question" data-faq="3">
                                <span class="tkm-faq-text">How can I change my KYC information?</span>
                                <span class="tkm-faq-arrow">
                                    <i class="fas fa-chevron-down"></i>
                                </span>
                            </button>
                            <div class="tkm-faq-answer" id="faq-3">
                                <div class="tkm-faq-content">
                                    <p>To update your KYC information:</p>
                                    <ol>
                                        <li>Visit the KYC Verification page from your dashboard</li>
                                        <li>Click on "Update KYC Information" if available</li>
                                        <li>Re-submit the form with your updated information</li>
                                        <li>Upload new documents if required</li>
                                    </ol>
                                    <p>Please note that KYC updates may require additional verification time. Contact support if you need immediate assistance.</p>
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Item 4 -->
                        <div class="tkm-faq-item">
                            <button class="tkm-faq-question" data-faq="4">
                                <span class="tkm-faq-text">How do I contact support?</span>
                                <span class="tkm-faq-arrow">
                                    <i class="fas fa-chevron-down"></i>
                                </span>
                            </button>
                            <div class="tkm-faq-answer" id="faq-4">
                                <div class="tkm-faq-content">
                                    <p>You can reach our support team through multiple channels:</p>
                                    <ul>
                                        <li><strong>Telegram Support:</strong> Join our Telegram support group for instant help</li>
                                        <li><strong>WhatsApp Chat:</strong> Send us a message on WhatsApp for personalized assistance</li>
                                        <li><strong>Telegram Channel:</strong> Follow our official channel for announcements</li>
                                        <li><strong>WhatsApp Channel:</strong> Subscribe to our WhatsApp channel for updates</li>
                                    </ul>
                                    <p>Our support team is available 24/7 to assist you with any questions or concerns.</p>
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Item 5 -->
                        <div class="tkm-faq-item">
                            <button class="tkm-faq-question" data-faq="5">
                                <span class="tkm-faq-text">What should I do if I forgot my password?</span>
                                <span class="tkm-faq-arrow">
                                    <i class="fas fa-chevron-down"></i>
                                </span>
                            </button>
                            <div class="tkm-faq-answer" id="faq-5">
                                <div class="tkm-faq-content">
                                    <p>If you've forgotten your password:</p>
                                    <ol>
                                        <li>Go to the login page</li>
                                        <li>Click on "Forgot Password?" link</li>
                                        <li>Enter your email address</li>
                                        <li>Check your email for password reset instructions</li>
                                        <li>Follow the link in the email to create a new password</li>
                                    </ol>
                                    <p>If you don't receive the password reset email, please check your spam folder or contact support for assistance.</p>
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Item 6 -->
                        <div class="tkm-faq-item">
                            <button class="tkm-faq-question" data-faq="6">
                                <span class="tkm-faq-text">How do I earn more points or rewards?</span>
                                <span class="tkm-faq-arrow">
                                    <i class="fas fa-chevron-down"></i>
                                </span>
                            </button>
                            <div class="tkm-faq-answer" id="faq-6">
                                <div class="tkm-faq-content">
                                    <p>You can earn more points and rewards by:</p>
                                    <ul>
                                        <li>Completing daily tasks and challenges</li>
                                        <li>Referring friends to join the platform</li>
                                        <li>Participating in special events and promotions</li>
                                        <li>Maintaining consistent activity on the platform</li>
                                        <li>Completing your profile and KYC verification</li>
                                    </ul>
                                    <p>Visit your dashboard regularly to see available tasks and opportunities to earn rewards.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Help Section -->
                <div class="tkm-additional-help">
                    <div class="tkm-help-card">
                        <h3>Still Need Help?</h3>
                        <p>Can't find the answer you're looking for? Our support team is here to help you 24/7.</p>
                        <div class="tkm-help-actions">
                            <a href="https://t.me/yoursupportgroup" target="_blank" class="tkm-help-btn">
                                <i class="fab fa-telegram-plane"></i>
                                Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Desk JavaScript -->
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-helpdesk.js?ver=1.0.0"></script>
    
    <?php wp_footer(); ?>
</body>
</html>
