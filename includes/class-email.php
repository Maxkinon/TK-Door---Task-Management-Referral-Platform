<?php
/**
 * Complete Email System for Indoor Tasks
 * Handles all email notifications to users and admins
 */
class Indoor_Tasks_Email {
    private static $instance = null;
    
    public function __construct() {
        // Hook into various actions for automatic email sending
        add_action('indoor_tasks_user_registered', [$this, 'send_welcome_email'], 10, 2);
        add_action('indoor_tasks_user_referred', [$this, 'send_referral_join_email'], 10, 3);
        add_action('indoor_tasks_new_task_created', [$this, 'send_new_task_alert'], 10, 2);
        add_action('indoor_tasks_task_status_changed', [$this, 'send_task_status_email'], 10, 3);
        add_action('indoor_tasks_kyc_status_changed', [$this, 'send_kyc_status_email'], 10, 2);
        add_action('indoor_tasks_withdrawal_status_changed', [$this, 'send_withdrawal_status_email'], 10, 3);
        add_action('indoor_tasks_announcement_created', [$this, 'send_announcement_email'], 10, 2);
        
        // Admin notifications
        add_action('indoor_tasks_user_registered', [$this, 'send_admin_new_user_email'], 10, 2);
        add_action('indoor_tasks_task_submitted', [$this, 'send_admin_task_submission_email'], 10, 2);
        add_action('indoor_tasks_withdrawal_requested', [$this, 'send_admin_withdrawal_request_email'], 10, 2);
        add_action('indoor_tasks_kyc_submitted', [$this, 'send_admin_kyc_submission_email'], 10, 2);
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get email template with header and footer
     */
    private function get_email_template($content, $title = '') {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $logo_url = INDOOR_TASKS_URL . 'assets/image/indoor-tasks-logo.webp';
        
        $template = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($title) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .email-header { background: linear-gradient(135deg, #00954b 0%, #10b981 100%); padding: 30px; text-align: center; }
        .email-header img { max-height: 60px; margin-bottom: 15px; }
        .email-header h1 { color: white; margin: 0; font-size: 24px; }
        .email-content { padding: 30px; line-height: 1.6; color: #333; }
        .email-footer { background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e9ecef; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #00954b; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 15px 0; }
        .btn:hover { background-color: #059669; }
        .highlight { background-color: #e7f3ff; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #00954b; }
        .text-center { text-align: center; }
        .text-muted { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '">
            <h1>' . esc_html($title) . '</h1>
        </div>
        <div class="email-content">
            ' . $content . '
        </div>
        <div class="email-footer">
            <p class="text-muted">
                &copy; ' . date('Y') . ' ' . esc_html($site_name) . '. All rights reserved.<br>
                <a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a>
            </p>
        </div>
    </div>
</body>
</html>';
        
        return $template;
    }
    
    /**
     * Send email with proper headers
     */
    public static function send($to, $subject, $message, $is_html = true) {
        $headers = [];
        if ($is_html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }
        $headers[] = 'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>';
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * USER EMAILS
     */
    
    /**
     * Welcome Email for New Users
     */
    public function send_welcome_email($user_id, $user_data) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return false;
        
        $dashboard_url = home_url('/dashboard/');
        $tasks_url = home_url('/tasks/');
        
        $content = '
            <h2>Welcome to ' . get_bloginfo('name') . '!</h2>
            <p>Hi ' . esc_html($user->display_name) . ',</p>
            <p>Welcome to our community! We\'re excited to have you on board.</p>
            
            <div class="highlight">
                <strong>What you can do now:</strong>
                <ul>
                    <li>Browse and complete available tasks</li>
                    <li>Earn points for your completed work</li>
                    <li>Track your progress in the dashboard</li>
                    <li>Refer friends and earn bonus rewards</li>
                </ul>
            </div>
            
            <div class="text-center">
                <a href="' . esc_url($dashboard_url) . '" class="btn">Visit Your Dashboard</a>
                <a href="' . esc_url($tasks_url) . '" class="btn">Browse Tasks</a>
            </div>
            
            <p>If you have any questions, feel free to reach out to our support team.</p>
            <p>Happy earning!</p>
        ';
        
        $template = $this->get_email_template($content, 'Welcome to ' . get_bloginfo('name'));
        return self::send($user->user_email, 'Welcome to ' . get_bloginfo('name'), $template);
    }
    
    /**
     * Referral Join Email
     */
    public function send_referral_join_email($referrer_id, $new_user_id, $referral_code) {
        $referrer = get_user_by('ID', $referrer_id);
        $new_user = get_user_by('ID', $new_user_id);
        
        if (!$referrer || !$new_user) return false;
        
        $content = '
            <h2>Great News! Someone Joined Using Your Referral</h2>
            <p>Hi ' . esc_html($referrer->display_name) . ',</p>
            <p>Fantastic! <strong>' . esc_html($new_user->display_name) . '</strong> just joined using your referral code <code>' . esc_html($referral_code) . '</code>.</p>
            
            <div class="highlight">
                <strong>Referral Bonus:</strong><br>
                You\'ll earn bonus points when your referred user completes their first task!
            </div>
            
            <p>Keep sharing your referral link to earn more rewards!</p>
        ';
        
        $template = $this->get_email_template($content, 'New Referral Success!');
        return self::send($referrer->user_email, 'Someone joined using your referral!', $template);
    }
    
    /**
     * New Task Alert Email
     */
    public function send_new_task_alert($task_id, $task_data) {
        // Get all active users who want task notifications
        global $wpdb;
        $users = $wpdb->get_results("
            SELECT u.user_email, u.display_name 
            FROM {$wpdb->users} u 
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'indoor_tasks_email_notifications'
            WHERE (um.meta_value = 'yes' OR um.meta_value IS NULL)
        ");
        
        $task_url = home_url('/task/' . $task_id . '/');
        
        $content = '
            <h2>New Task Available!</h2>
            <p>A new task has been posted and is ready for completion:</p>
            
            <div class="highlight">
                <strong>' . esc_html($task_data['title']) . '</strong><br>
                <strong>Points:</strong> ' . number_format($task_data['points']) . '<br>
                <strong>Category:</strong> ' . esc_html($task_data['category']) . '<br>
            </div>
            
            <div class="text-center">
                <a href="' . esc_url($task_url) . '" class="btn">View Task Details</a>
            </div>
        ';
        
        $template = $this->get_email_template($content, 'New Task Available');
        
        foreach ($users as $user) {
            self::send($user->user_email, 'New Task Available - ' . $task_data['title'], $template);
        }
    }
    
    /**
     * Task Status Emails (Approved/Rejected/Pending)
     */
    public function send_task_status_email($user_id, $task_id, $status) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return false;
        
        global $wpdb;
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d", 
            $task_id
        ));
        
        if (!$task) return false;
        
        $dashboard_url = home_url('/dashboard/');
        
        switch ($status) {
            case 'approved':
                $title = 'Task Approved!';
                $content = '
                    <h2>🎉 Task Approved!</h2>
                    <p>Hi ' . esc_html($user->display_name) . ',</p>
                    <p>Great news! Your task submission has been approved.</p>
                    
                    <div class="highlight">
                        <strong>Task:</strong> ' . esc_html($task->title) . '<br>
                        <strong>Points Earned:</strong> ' . number_format($task->points) . '<br>
                        <strong>Status:</strong> ✅ Approved
                    </div>
                    
                    <p>The points have been added to your wallet. Keep up the great work!</p>
                ';
                break;
                
            case 'rejected':
                $title = 'Task Needs Revision';
                $content = '
                    <h2>Task Needs Revision</h2>
                    <p>Hi ' . esc_html($user->display_name) . ',</p>
                    <p>Your task submission needs some revision before it can be approved.</p>
                    
                    <div class="highlight">
                        <strong>Task:</strong> ' . esc_html($task->title) . '<br>
                        <strong>Status:</strong> ❌ Needs Revision
                    </div>
                    
                    <p>Please review the feedback and resubmit your work. Don\'t worry - you can try again!</p>
                ';
                break;
                
            case 'pending':
                $title = 'Task Submitted Successfully';
                $content = '
                    <h2>Task Submitted Successfully</h2>
                    <p>Hi ' . esc_html($user->display_name) . ',</p>
                    <p>We\'ve received your task submission and it\'s now under review.</p>
                    
                    <div class="highlight">
                        <strong>Task:</strong> ' . esc_html($task->title) . '<br>
                        <strong>Status:</strong> ⏳ Under Review
                    </div>
                    
                    <p>We\'ll notify you once the review is complete. Thank you for your submission!</p>
                ';
                break;
        }
        
        $content .= '
            <div class="text-center">
                <a href="' . esc_url($dashboard_url) . '" class="btn">Check Your Dashboard</a>
            </div>
        ';
        
        $template = $this->get_email_template($content, $title);
        return self::send($user->user_email, $title, $template);
    }
    
    /**
     * KYC Status Emails
     */
    public function send_kyc_status_email($user_id, $status) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return false;
        
        $dashboard_url = home_url('/dashboard/');
        $kyc_url = home_url('/kyc/');
        
        switch ($status) {
            case 'approved':
                $title = 'KYC Approved!';
                $content = '
                    <h2>🎉 Identity Verification Approved!</h2>
                    <p>Hi ' . esc_html($user->display_name) . ',</p>
                    <p>Excellent! Your identity verification has been approved.</p>
                    
                    <div class="highlight">
                        <strong>Status:</strong> ✅ Verified<br>
                        <strong>Benefits Unlocked:</strong>
                        <ul>
                            <li>Withdraw your earnings</li>
                            <li>Access premium tasks</li>
                            <li>Higher trust rating</li>
                        </ul>
                    </div>
                    
                    <p>You can now withdraw your earnings and access all features!</p>
                ';
                break;
                
            case 'rejected':
                $title = 'KYC Verification Needs Attention';
                $content = '
                    <h2>Identity Verification Needs Attention</h2>
                    <p>Hi ' . esc_html($user->display_name) . ',</p>
                    <p>We need some additional information for your identity verification.</p>
                    
                    <div class="highlight">
                        <strong>Status:</strong> ❌ Needs Revision<br>
                        <strong>Next Steps:</strong> Please review the feedback and submit updated documents.
                    </div>
                    
                    <p>Don\'t worry - you can resubmit your verification documents at any time.</p>
                ';
                break;
                
            case 'pending':
                $title = 'KYC Submitted Successfully';
                $content = '
                    <h2>Identity Verification Submitted</h2>
                    <p>Hi ' . esc_html($user->display_name) . ',</p>
                    <p>We\'ve received your identity verification documents.</p>
                    
                    <div class="highlight">
                        <strong>Status:</strong> ⏳ Under Review<br>
                        <strong>Review Time:</strong> 2-5 business days
                    </div>
                    
                    <p>We\'ll notify you once the review is complete. Thank you for your patience!</p>
                ';
                break;
        }
        
        $content .= '
            <div class="text-center">
                <a href="' . esc_url($dashboard_url) . '" class="btn">Check Your Dashboard</a>
                <a href="' . esc_url($kyc_url) . '" class="btn">Manage KYC</a>
            </div>
        ';
        
        $template = $this->get_email_template($content, $title);
        return self::send($user->user_email, $title, $template);
    }
    
    /**
     * Withdrawal Status Emails
     */
    public function send_withdrawal_status_email($user_id, $withdrawal_id, $status) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return false;
        
        global $wpdb;
        $withdrawal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_task_withwithdrawals WHERE id = %d", 
            $withdrawal_id
        ));
        
        if (!$withdrawal) return false;
        
        $dashboard_url = home_url('/dashboard/');
        
        switch ($status) {
            case 'approved':
                $title = 'Withdrawal Approved!';
                $content = '
                    <h2>💰 Withdrawal Approved!</h2>
                    <p>Hi ' . esc_html($user->display_name) . ',</p>
                    <p>Great news! Your withdrawal request has been approved and processed.</p>
                    
                    <div class="highlight">
                        <strong>Amount:</strong> ' . number_format($withdrawal->amount) . '<br>
                        <strong>Method:</strong> ' . esc_html($withdrawal->method) . '<br>
                        <strong>Status:</strong> ✅ Processed
                    </div>
                    
                    <p>The payment should arrive in your account within 3-5 business days.</p>
                ';
                break;
                
            case 'rejected':
                $title = 'Withdrawal Request Issue';
                $content = '
                    <h2>Withdrawal Request Needs Attention</h2>
                    <p>Hi ' . esc_html($user->display_name) . ',</p>
                    <p>There was an issue with your withdrawal request.</p>
                    
                    <div class="highlight">
                        <strong>Amount:</strong> ' . number_format($withdrawal->amount) . '<br>
                        <strong>Method:</strong> ' . esc_html($withdrawal->method) . '<br>
                        <strong>Status:</strong> ❌ Needs Attention
                    </div>
                    
                    <p>Please check the details and submit a new request. Your points have been returned to your wallet.</p>
                ';
                break;
                
            case 'pending':
                $title = 'Withdrawal Request Received';
                $content = '
                    <h2>Withdrawal Request Received</h2>
                    <p>Hi ' . esc_html($user->display_name) . ',</p>
                    <p>We\'ve received your withdrawal request and it\'s now being processed.</p>
                    
                    <div class="highlight">
                        <strong>Amount:</strong> ' . number_format($withdrawal->amount) . '<br>
                        <strong>Method:</strong> ' . esc_html($withdrawal->method) . '<br>
                        <strong>Status:</strong> ⏳ Processing
                    </div>
                    
                    <p>We\'ll notify you once the withdrawal is processed. Processing time: 2-5 business days.</p>
                ';
                break;
        }
        
        $content .= '
            <div class="text-center">
                <a href="' . esc_url($dashboard_url) . '" class="btn">Check Your Dashboard</a>
            </div>
        ';
        
        $template = $this->get_email_template($content, $title);
        return self::send($user->user_email, $title, $template);
    }
    
    /**
     * New Announcement Email
     */
    public function send_announcement_email($announcement_id, $announcement_data) {
        // Get all users who want announcement notifications
        global $wpdb;
        $users = $wpdb->get_results("
            SELECT u.user_email, u.display_name 
            FROM {$wpdb->users} u 
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'indoor_tasks_announcement_notifications'
            WHERE (um.meta_value = 'yes' OR um.meta_value IS NULL)
        ");
        
        $dashboard_url = home_url('/dashboard/');
        
        $content = '
            <h2>📢 New Announcement</h2>
            <p>We have an important update for you:</p>
            
            <div class="highlight">
                <h3>' . esc_html($announcement_data['title']) . '</h3>
                <p>' . wp_kses_post($announcement_data['content']) . '</p>
            </div>
            
            <div class="text-center">
                <a href="' . esc_url($dashboard_url) . '" class="btn">View in Dashboard</a>
            </div>
        ';
        
        $template = $this->get_email_template($content, 'New Announcement');
        
        foreach ($users as $user) {
            self::send($user->user_email, 'New Announcement: ' . $announcement_data['title'], $template);
        }
    }
    
    /**
     * ADMIN EMAILS
     */
    
    /**
     * New User Registration Email to Admin
     */
    public function send_admin_new_user_email($user_id, $user_data) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return false;
        
        $admin_email = get_option('admin_email');
        $user_profile_url = admin_url('user-edit.php?user_id=' . $user_id);
        
        $content = '
            <h2>New User Registration</h2>
            <p>A new user has joined your platform:</p>
            
            <div class="highlight">
                <strong>Name:</strong> ' . esc_html($user->display_name) . '<br>
                <strong>Email:</strong> ' . esc_html($user->user_email) . '<br>
                <strong>Username:</strong> ' . esc_html($user->user_login) . '<br>
                <strong>Registration Date:</strong> ' . date('M j, Y g:i A', strtotime($user->user_registered)) . '
            </div>
            
            <div class="text-center">
                <a href="' . esc_url($user_profile_url) . '" class="btn">View User Profile</a>
            </div>
        ';
        
        $template = $this->get_email_template($content, 'New User Registration');
        return self::send($admin_email, 'New User Registration - ' . $user->display_name, $template);
    }
    
    /**
     * Task Submission Email to Admin
     */
    public function send_admin_task_submission_email($user_id, $task_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return false;
        
        global $wpdb;
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d", 
            $task_id
        ));
        
        if (!$task) return false;
        
        $admin_email = get_option('admin_email');
        $review_url = admin_url('admin.php?page=indoor-tasks-submissions');
        
        $content = '
            <h2>New Task Submission</h2>
            <p>A user has submitted a task for review:</p>
            
            <div class="highlight">
                <strong>User:</strong> ' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')<br>
                <strong>Task:</strong> ' . esc_html($task->title) . '<br>
                <strong>Points:</strong> ' . number_format($task->points) . '<br>
                <strong>Submitted:</strong> ' . date('M j, Y g:i A') . '
            </div>
            
            <div class="text-center">
                <a href="' . esc_url($review_url) . '" class="btn">Review Submission</a>
            </div>
        ';
        
        $template = $this->get_email_template($content, 'New Task Submission');
        return self::send($admin_email, 'New Task Submission - ' . $task->title, $template);
    }
    
    /**
     * Withdrawal Request Email to Admin
     */
    public function send_admin_withdrawal_request_email($user_id, $withdrawal_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return false;
        
        global $wpdb;
        $withdrawal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_task_withwithdrawals WHERE id = %d", 
            $withdrawal_id
        ));
        
        if (!$withdrawal) return false;
        
        $admin_email = get_option('admin_email');
        $review_url = admin_url('admin.php?page=indoor-tasks-withdrawal-requests');
        
        $content = '
            <h2>New Withdrawal Request</h2>
            <p>A user has requested a withdrawal:</p>
            
            <div class="highlight">
                <strong>User:</strong> ' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')<br>
                <strong>Amount:</strong> ' . number_format($withdrawal->amount) . '<br>
                <strong>Method:</strong> ' . esc_html($withdrawal->method) . '<br>
                <strong>Requested:</strong> ' . date('M j, Y g:i A', strtotime($withdrawal->created_at)) . '
            </div>
            
            <div class="text-center">
                <a href="' . esc_url($review_url) . '" class="btn">Review Request</a>
            </div>
        ';
        
        $template = $this->get_email_template($content, 'New Withdrawal Request');
        return self::send($admin_email, 'New Withdrawal Request - ' . number_format($withdrawal->amount), $template);
    }
    
    /**
     * KYC Submission Email to Admin
     */
    public function send_admin_kyc_submission_email($user_id, $kyc_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return false;
        
        $admin_email = get_option('admin_email');
        $review_url = admin_url('admin.php?page=indoor-tasks-manage-kyc');
        
        $content = '
            <h2>New KYC Submission</h2>
            <p>A user has submitted KYC documents for verification:</p>
            
            <div class="highlight">
                <strong>User:</strong> ' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')<br>
                <strong>Submitted:</strong> ' . date('M j, Y g:i A') . '<br>
                <strong>Status:</strong> Pending Review
            </div>
            
            <div class="text-center">
                <a href="' . esc_url($review_url) . '" class="btn">Review KYC Documents</a>
            </div>
        ';
        
        $template = $this->get_email_template($content, 'New KYC Submission');
        return self::send($admin_email, 'New KYC Submission - ' . $user->display_name, $template);
    }
    
    /**
     * Send OTP Email
     */
    public function send_otp_email($email, $otp) {
        $content = '
            <h2>Your Verification Code</h2>
            <p>Use the following code to verify your email address:</p>
            
            <div class="highlight text-center">
                <h1 style="font-size: 36px; color: #00954b; margin: 20px 0; letter-spacing: 5px;">' . esc_html($otp) . '</h1>
            </div>
            
            <p class="text-muted">This code will expire in 10 minutes for security reasons.</p>
            <p class="text-muted">If you didn\'t request this code, please ignore this email.</p>
        ';
        
        $template = $this->get_email_template($content, 'Email Verification Code');
        return self::send($email, 'Your verification code: ' . $otp, $template);
    }
}
