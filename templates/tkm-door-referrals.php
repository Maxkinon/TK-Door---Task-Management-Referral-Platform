<?php
/**
 * Template Name: TKM Door - Referrals
 * Description: Modern referrals template with MLM features, invitations, and statistics
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
$user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Get database references
global $wpdb;

// Check if referrals table exists
$referrals_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_referrals'") === $wpdb->prefix . 'indoor_referrals';

// Create referrals table if it doesn't exist
if (!$referrals_table_exists) {
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$wpdb->prefix}indoor_referrals (
        id int(11) NOT NULL AUTO_INCREMENT,
        referrer_id int(11) NOT NULL,
        referee_id int(11) DEFAULT NULL,
        referral_code varchar(50) NOT NULL,
        email varchar(255) DEFAULT NULL,
        status enum('pending','completed','expired') DEFAULT 'pending',
        points_awarded int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        completed_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY referral_code (referral_code),
        KEY referrer_id (referrer_id),
        KEY referee_id (referee_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Generate user's referral code if they don't have one
$user_referral_code = get_user_meta($user_id, 'indoor_tasks_referral_code', true);
if (empty($user_referral_code)) {
    // Generate consistent format with dashboard
    $user_referral_code = strtoupper(substr(md5($user_id . time()), 0, 8));
    update_user_meta($user_id, 'indoor_tasks_referral_code', $user_referral_code);
}

// Get referral URL - use consistent parameter name
$referral_url = home_url('/?ref=' . $user_referral_code);

// Handle AJAX actions
if ($_POST && isset($_POST['action'])) {
    $response = array();
    
    switch ($_POST['action']) {
        case 'send_invitations':
            if (wp_verify_nonce($_POST['nonce'], 'send_invitations_' . $user_id)) {
                $emails = sanitize_textarea_field($_POST['emails'] ?? '');
                $email_list = array_filter(array_map('trim', explode(',', $emails)));
                
                $sent_count = 0;
                $errors = array();
                
                foreach ($email_list as $email) {
                    if (!is_email($email)) {
                        $errors[] = $email . ' is not a valid email address';
                        continue;
                    }
                    
                    // Check if email already exists as a user
                    if (email_exists($email)) {
                        $errors[] = $email . ' is already registered';
                        continue;
                    }
                    
                    // Check if invitation already sent
                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}indoor_referrals WHERE referrer_id = %d AND email = %s AND status = 'pending'",
                        $user_id,
                        $email
                    ));
                    
                    if ($existing) {
                        $errors[] = 'Invitation already sent to ' . $email;
                        continue;
                    }
                    
                    // Generate unique referral code for this invitation
                    $invitation_code = $user_referral_code . '_' . substr(md5($email . time()), 0, 8);
                    
                    // Insert referral record
                    $result = $wpdb->insert(
                        $wpdb->prefix . 'indoor_referrals',
                        array(
                            'referrer_id' => $user_id,
                            'referral_code' => $invitation_code,
                            'email' => $email,
                            'status' => 'pending'
                        ),
                        array('%d', '%s', '%s', '%s')
                    );
                    
                    if ($result) {
                        // Send invitation email
                        $subject = 'Join Indoor Tasks and Earn Free Points!';
                        $invitation_url = home_url('/?referral=' . $invitation_code);
                        
                        $message = "Hi there!\n\n";
                        $message .= $current_user->display_name . " has invited you to join Indoor Tasks!\n\n";
                        $message .= "🎉 Sign up now and both of you will get 20 points for free!\n\n";
                        $message .= "Click here to register: " . $invitation_url . "\n\n";
                        $message .= "Indoor Tasks is a platform where you can complete simple tasks and earn points that can be converted to real rewards!\n\n";
                        $message .= "Don't miss out on this opportunity!\n\n";
                        $message .= "Best regards,\nThe Indoor Tasks Team";
                        
                        if (wp_mail($email, $subject, $message)) {
                            $sent_count++;
                        } else {
                            $errors[] = 'Failed to send email to ' . $email;
                        }
                    }
                }
                
                $response['success'] = true;
                $response['sent_count'] = $sent_count;
                $response['errors'] = $errors;
                $response['message'] = $sent_count > 0 ? 
                    "Successfully sent {$sent_count} invitation(s)!" : 
                    "No invitations were sent.";
            } else {
                $response['success'] = false;
                $response['message'] = 'Security check failed';
            }
            break;
    }
    
    if (isset($response)) {
        wp_send_json($response);
        exit;
    }
}

// Get user's referral statistics
$total_referrals = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_referrals WHERE referrer_id = %d AND status = 'completed'",
    $user_id
));

$total_points_earned = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(points_awarded) FROM {$wpdb->prefix}indoor_referrals WHERE referrer_id = %d AND status = 'completed'",
    $user_id
));

$pending_referrals = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_referrals WHERE referrer_id = %d AND status = 'pending'",
    $user_id
));

// Get user's upline (who referred them)
$upline_referral = $wpdb->get_row($wpdb->prepare(
    "SELECT r.*, u.display_name as referrer_name, u.user_login as referrer_username 
     FROM {$wpdb->prefix}indoor_referrals r 
     LEFT JOIN {$wpdb->users} u ON r.referrer_id = u.ID 
     WHERE r.referee_id = %d AND r.status = 'completed'",
    $user_id
));

// Get user's downline (people they referred)
$downline = $wpdb->get_results($wpdb->prepare(
    "SELECT r.*, u.display_name as referee_name, u.user_login as referee_username, u.user_email as referee_email
     FROM {$wpdb->prefix}indoor_referrals r 
     LEFT JOIN {$wpdb->users} u ON r.referee_id = u.ID 
     WHERE r.referrer_id = %d AND r.status = 'completed'
     ORDER BY r.completed_at DESC",
    $user_id
));

// Get pending invitations
$pending_invitations = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}indoor_referrals 
     WHERE referrer_id = %d AND status = 'pending' 
     ORDER BY created_at DESC",
    $user_id
));

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Referrals - <?php bloginfo('name'); ?></title>
    
    <?php wp_head(); ?>
    
    <!-- TKM Door Referrals Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-referrals.css?ver=1.0.0">
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-referrals-fix.css?ver=1.0.0">
</head>
<body class="tkm-door-referrals">
    <div class="tkm-referrals-container">
        <!-- Include Sidebar -->
        <?php include INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'; ?>
        
        <div class="tkm-referrals-content">
            <!-- Header Section -->
            <div class="tkm-referrals-header">
                <div class="tkm-header-content">
                    <h1>Referrals</h1>
                    <p class="tkm-header-subtitle">Invite your friends to Indoor Tasks.<br>
                    If they sign up, you and your friend will get <strong>20 pts for free!</strong></p>
                </div>
                <div class="tkm-header-illustration">
                    <svg viewBox="0 0 200 150" fill="none">
                        <circle cx="100" cy="75" r="60" fill="#00954b" opacity="0.1"/>
                        <circle cx="100" cy="75" r="40" fill="#00954b" opacity="0.2"/>
                        <circle cx="100" cy="75" r="20" fill="#00954b"/>
                        <path d="M80 65 L100 85 L120 65" stroke="white" stroke-width="3" fill="none"/>
                    </svg>
                </div>
            </div>
            
            <!-- Your Referral Code Section -->
            <div class="tkm-section tkm-referral-code-section">
                <h2>Your Referral Code</h2>
                <div class="tkm-referral-code-card">
                    <div class="tkm-referral-code-content">
                        <div class="tkm-referral-code-label">Your unique referral code:</div>
                        <div class="tkm-referral-code-display"><?php echo esc_html($user_referral_code); ?></div>
                        <div class="tkm-referral-code-help">Share this code with friends during registration</div>
                    </div>
                    <button type="button" class="tkm-copy-code-btn" onclick="copyReferralCode('<?php echo esc_js($user_referral_code); ?>')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                        Copy Code
                    </button>
                </div>
            </div>
            
            <!-- Enhanced How It Works Section with Conditions -->
            <div class="tkm-section tkm-how-it-works">
                <h2>How the Referral System Works</h2>
                <div class="tkm-steps-grid">
                    <div class="tkm-step-card">
                        <div class="tkm-step-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <span class="tkm-step-number">1</span>
                        </div>
                        <h3>Share Your Link</h3>
                        <p>Send your unique referral link to friends via email, social media, or messaging apps.</p>
                    </div>
                    
                    <div class="tkm-step-card">
                        <div class="tkm-step-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <line x1="20" y1="8" x2="20" y2="14"/>
                                <line x1="23" y1="11" x2="17" y2="11"/>
                            </svg>
                            <span class="tkm-step-number">2</span>
                        </div>
                        <h3>Friend Registers</h3>
                        <p>Your friend clicks the link and creates an account with a valid email address.</p>
                    </div>
                    
                    <div class="tkm-step-card">
                        <div class="tkm-step-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 12l2 2 4-4"/>
                                <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/>
                            </svg>
                            <span class="tkm-step-number">3</span>
                        </div>
                        <h3>Complete Requirements</h3>
                        <p>Your friend must complete <?php echo get_option('indoor_tasks_referral_min_tasks', 1); ?> task(s)<?php if (get_option('indoor_tasks_referral_require_kyc', 1)): ?> and verify their profile<?php endif; ?>.</p>
                    </div>
                    
                    <div class="tkm-step-card">
                        <div class="tkm-step-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                            <span class="tkm-step-number">4</span>
                        </div>
                        <h3>Get Rewards!</h3>
                        <p>After <?php echo get_option('indoor_tasks_referral_delay_hours', 24); ?> hours, you both receive points!</p>
                    </div>
                </div>
                
                <!-- Referral Rules & Conditions -->
                <div class="tkm-referral-conditions" style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-top: 30px;">
                    <h3 style="color: #2271b1; margin-top: 0;">📋 Referral Rules & Conditions</h3>
                    
                    <div class="tkm-conditions-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div class="tkm-condition-card" style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #46b450;">
                            <h4 style="color: #46b450; margin: 0 0 10px 0;">✅ Requirements for Bonus</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                                <li>Your friend must complete <strong><?php echo get_option('indoor_tasks_referral_min_tasks', 1); ?> real task(s)</strong></li>
                                <?php if (get_option('indoor_tasks_referral_require_kyc', 1)): ?>
                                <li>Profile verification (KYC) must be completed</li>
                                <?php endif; ?>
                                <li>Different IP address from yours</li>
                                <li>Valid email address (no temporary emails)</li>
                                <li>Different device/browser from yours</li>
                            </ul>
                        </div>
                        
                        <div class="tkm-condition-card" style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #2271b1;">
                            <h4 style="color: #2271b1; margin: 0 0 10px 0;">🎁 Reward Details</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                                <li>You earn: <strong><?php echo number_format(get_option('indoor_tasks_referral_reward_amount', 20)); ?> points</strong></li>
                                <li>Your friend earns: <strong><?php echo number_format(get_option('indoor_tasks_referee_bonus', 20)); ?> points</strong></li>
                                <li>Bonus delay: <strong><?php echo get_option('indoor_tasks_referral_delay_hours', 24); ?> hours</strong></li>
                                <li>Daily limit: <strong><?php echo get_option('indoor_tasks_max_referrals_per_user', 10); ?> referrals</strong></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="tkm-warning-note" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin-top: 15px;">
                        <h4 style="color: #856404; margin: 0 0 10px 0;">⚠️ Anti-Spam Protection</h4>
                        <p style="margin: 0; font-size: 14px; color: #856404;">
                            Our system automatically detects and blocks fake referrals. Attempting to abuse the referral system by:
                            creating multiple accounts, using the same device/IP, or using temporary emails will result in 
                            <strong>immediate rejection</strong> and potential account suspension.
                        </p>
                    </div>
                    
                    <div class="tkm-success-note" style="background: #d1edff; border: 1px solid #74c0fc; border-radius: 6px; padding: 15px; margin-top: 15px;">
                        <h4 style="color: #004085; margin: 0 0 10px 0;">💡 Pro Tips for Success</h4>
                        <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #004085;">
                            <li>Share with real friends who are genuinely interested</li>
                            <li>Explain how Indoor Tasks works and its benefits</li>
                            <li>Help your friends complete their first task</li>
                            <li>Be patient - bonuses are awarded after the delay period</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Section -->
            <div class="tkm-section tkm-statistics">
                <h2>Your Referral Statistics</h2>
                <div class="tkm-stats-grid">
                    <div class="tkm-stat-card">
                        <div class="tkm-stat-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <div class="tkm-stat-info">
                            <div class="tkm-stat-number"><?php echo number_format($total_referrals ?: 0); ?></div>
                            <div class="tkm-stat-label">Total Referrals</div>
                        </div>
                    </div>
                    
                    <div class="tkm-stat-card">
                        <div class="tkm-stat-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                        <div class="tkm-stat-info">
                            <div class="tkm-stat-number"><?php echo number_format($total_points_earned ?: 0); ?></div>
                            <div class="tkm-stat-label">Points Earned</div>
                        </div>
                    </div>
                    
                    <div class="tkm-stat-card">
                        <div class="tkm-stat-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div class="tkm-stat-info">
                            <div class="tkm-stat-number"><?php echo number_format($pending_referrals ?: 0); ?></div>
                            <div class="tkm-stat-label">Pending Invitations</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Invite Friends Section -->
            <div class="tkm-section tkm-invite-friends">
                <h2>Invite Friends</h2>
                <p class="tkm-section-description">Insert your friends' email addresses and send them invitations to join Indoor Tasks!</p>
                
                <form id="invitation-form" class="tkm-invite-form">
                    <div class="tkm-form-group">
                        <label for="friend-emails">Email Addresses (separate multiple emails with commas)</label>
                        <textarea id="friend-emails" name="emails" rows="4" 
                                placeholder="friend1@example.com, friend2@example.com, friend3@example.com..."
                                required></textarea>
                        <div class="tkm-form-help">
                            Enter multiple email addresses separated by commas. We'll send them a personalized invitation from you!
                        </div>
                    </div>
                    
                    <button type="submit" class="tkm-invite-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        Send Invitations
                    </button>
                </form>
            </div>
            
            <!-- Share Referral Link Section -->
            <div class="tkm-section tkm-share-link">
                <h2>Share Referral Link</h2>
                <p class="tkm-section-description">Share your personal referral link with friends and family!</p>
                
                <div class="tkm-referral-link-box">
                    <input type="text" id="referral-link" value="<?php echo esc_url($referral_url); ?>" readonly>
                    <button type="button" class="tkm-copy-btn" onclick="copyReferralLink()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                        Copy Link
                    </button>
                </div>
                
                <div class="tkm-social-share">
                    <h3>Share on Social Media</h3>
                    <div class="tkm-social-buttons">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_url); ?>" 
                           target="_blank" class="tkm-social-btn tkm-facebook">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                            Facebook
                        </a>
                        
                        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Join Indoor Tasks and earn points by completing simple tasks! Use my referral link: '); ?><?php echo urlencode($referral_url); ?>" 
                           target="_blank" class="tkm-social-btn tkm-twitter">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                            Twitter
                        </a>
                        
                        <a href="https://wa.me/?text=<?php echo urlencode('Join Indoor Tasks and earn points by completing simple tasks! Use my referral link: ' . $referral_url); ?>" 
                           target="_blank" class="tkm-social-btn tkm-whatsapp">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488z"/>
                            </svg>
                            WhatsApp
                        </a>
                        
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($referral_url); ?>" 
                           target="_blank" class="tkm-social-btn tkm-linkedin">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                            LinkedIn
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- MLM Network Features -->
            <div class="tkm-section tkm-network">
                <h2>Your Network</h2>
                
                <!-- Upline Section -->
                <div class="tkm-network-section">
                    <h3>Your Upline</h3>
                    <?php if ($upline_referral): ?>
                        <div class="tkm-network-card tkm-upline-card">
                            <div class="tkm-network-avatar">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                            <div class="tkm-network-info">
                                <div class="tkm-network-name"><?php echo esc_html($upline_referral->referrer_name); ?></div>
                                <div class="tkm-network-username">@<?php echo esc_html($upline_referral->referrer_username); ?></div>
                                <div class="tkm-network-date">Joined: <?php echo date('M j, Y', strtotime($upline_referral->completed_at)); ?></div>
                            </div>
                            <div class="tkm-network-badge">
                                <span class="tkm-upline-badge">Upline</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="tkm-empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <p>You don't have an upline. You joined directly!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Downline Section -->
                <div class="tkm-network-section">
                    <h3>Your Downline</h3>
                    <?php if (!empty($downline)): ?>
                        <div class="tkm-downline-grid">
                            <?php foreach ($downline as $member): ?>
                                <div class="tkm-network-card tkm-downline-card">
                                    <div class="tkm-network-avatar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                            <circle cx="12" cy="7" r="4"/>
                                        </svg>
                                    </div>
                                    <div class="tkm-network-info">
                                        <div class="tkm-network-name"><?php echo esc_html($member->referee_name ?: 'User'); ?></div>
                                        <div class="tkm-network-username">@<?php echo esc_html($member->referee_username ?: 'user'); ?></div>
                                        <div class="tkm-network-date">Joined: <?php echo date('M j, Y', strtotime($member->completed_at)); ?></div>
                                    </div>
                                    <div class="tkm-network-badge">
                                        <span class="tkm-downline-badge">+<?php echo $member->points_awarded; ?> pts</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="tkm-empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <p>No referrals yet. Start inviting friends to build your network!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pending Invitations -->
                <?php if (!empty($pending_invitations)): ?>
                <div class="tkm-network-section">
                    <h3>Pending Invitations</h3>
                    <div class="tkm-pending-invitations">
                        <?php foreach ($pending_invitations as $invitation): ?>
                            <div class="tkm-pending-card">
                                <div class="tkm-pending-info">
                                    <div class="tkm-pending-email"><?php echo esc_html($invitation->email); ?></div>
                                    <div class="tkm-pending-date">Sent: <?php echo date('M j, Y', strtotime($invitation->created_at)); ?></div>
                                </div>
                                <div class="tkm-pending-status">
                                    <span class="tkm-pending-badge">Pending</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="tkm-loading-overlay" style="display: none;">
        <div class="tkm-loading-spinner"></div>
        <p>Sending invitations...</p>
    </div>
    
    <!-- Success/Error Messages -->
    <div id="message-container" class="tkm-message-container"></div>
    
    <?php wp_footer(); ?>
    
    <!-- TKM Door Referrals Scripts -->
    <script>
        // Function to copy referral code
        function copyReferralCode(code) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(code).then(function() {
                    showMessage('Referral code copied to clipboard!', 'success');
                }).catch(function(err) {
                    fallbackCopyTextToClipboard(code);
                });
            } else {
                fallbackCopyTextToClipboard(code);
            }
        }
        
        // Function to copy referral link
        function copyReferralLink() {
            const linkInput = document.getElementById('referral-link');
            if (linkInput) {
                copyReferralCode(linkInput.value);
            }
        }
        
        // Fallback copy function for older browsers
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showMessage('Referral code copied to clipboard!', 'success');
                } else {
                    showMessage('Failed to copy referral code', 'error');
                }
            } catch (err) {
                showMessage('Failed to copy referral code', 'error');
            }
            
            document.body.removeChild(textArea);
        }
        
        // Function to show messages
        function showMessage(message, type) {
            const container = document.getElementById('message-container');
            const messageDiv = document.createElement('div');
            messageDiv.className = `tkm-message tkm-message-${type}`;
            messageDiv.innerHTML = message;
            
            container.appendChild(messageDiv);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 3000);
        }
        
        // Pass PHP variables to JavaScript
        window.tkmReferrals = {
            userId: <?php echo $user_id; ?>,
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonces: {
                sendInvitations: '<?php echo wp_create_nonce('send_invitations_' . $user_id); ?>'
            }
        };
    </script>
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-referrals.js?ver=1.0.0"></script>
</body>
</html>
