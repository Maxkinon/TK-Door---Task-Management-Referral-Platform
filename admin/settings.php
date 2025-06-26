<?php
// Admin general settings page
?><div class="wrap">
<h1><?php _e('Indoor Tasks Settings', 'indoor-tasks'); ?></h1>
<?php
// Handle form submissions
if (isset($_POST['it_general_save']) && current_user_can('manage_options')) {
    // Process file upload if present
    if (!empty($_FILES['site_logo']['tmp_name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $upload = wp_handle_upload($_FILES['site_logo'], ['test_form' => false]);
        if (!empty($upload['url'])) {
            update_option('indoor_tasks_site_logo', $upload['url']);
        }
    }
    
    update_option('indoor_tasks_currency_label', sanitize_text_field($_POST['currency_label']));
    update_option('indoor_tasks_timezone', sanitize_text_field($_POST['timezone']));
    update_option('indoor_tasks_task_approval_time', intval($_POST['task_approval_time']));
    update_option('indoor_tasks_max_tasks_per_day', intval($_POST['max_tasks_per_day']));
    update_option('indoor_tasks_max_upload_size', intval($_POST['max_upload_size']));
    update_option('indoor_tasks_enable_loader', isset($_POST['enable_loader']) ? 1 : 0);
    echo '<div class="updated"><p>General settings saved.</p></div>';
}

if (isset($_POST['it_security_save']) && current_user_can('manage_options')) {
    // Save reCAPTCHA settings
    update_option('indoor_tasks_enable_recaptcha', isset($_POST['enable_recaptcha']) ? 1 : 0);
    update_option('indoor_tasks_recaptcha_site_key', sanitize_text_field($_POST['recaptcha_site_key']));
    update_option('indoor_tasks_recaptcha_secret_key', sanitize_text_field($_POST['recaptcha_secret_key']));
    update_option('indoor_tasks_recaptcha_version', sanitize_text_field($_POST['recaptcha_version']));
    
    // Save reCAPTCHA v3 specific settings
    if (isset($_POST['recaptcha_v3_score'])) {
        update_option('indoor_tasks_recaptcha_v3_score', sanitize_text_field($_POST['recaptcha_v3_score']));
    }
    
    // Save Firebase settings
    update_option('indoor_tasks_enable_google_login', isset($_POST['enable_google_login']) ? 1 : 0);
    update_option('indoor_tasks_google_client_id', sanitize_text_field($_POST['google_client_id']));
    update_option('indoor_tasks_google_client_secret', sanitize_text_field($_POST['google_client_secret']));
    update_option('indoor_tasks_firebase_api_key', sanitize_text_field($_POST['firebase_api_key']));
    update_option('indoor_tasks_firebase_auth_domain', sanitize_text_field($_POST['firebase_auth_domain']));
    update_option('indoor_tasks_firebase_project_id', sanitize_text_field($_POST['firebase_project_id']));
    update_option('indoor_tasks_firebase_storage_bucket', sanitize_text_field($_POST['firebase_storage_bucket']));
    update_option('indoor_tasks_firebase_messaging_sender_id', sanitize_text_field($_POST['firebase_messaging_sender_id']));
    update_option('indoor_tasks_firebase_app_id', sanitize_text_field($_POST['firebase_app_id']));
    update_option('indoor_tasks_firebase_measurement_id', sanitize_text_field($_POST['firebase_measurement_id']));
    
    echo '<div class="updated"><p>Security settings saved.</p></div>';
}

if (isset($_POST['it_level_save']) && current_user_can('manage_options')) {
    update_option('indoor_tasks_enable_level_system', isset($_POST['enable_level_system']) ? 1 : 0);
    update_option('indoor_tasks_level_type', sanitize_text_field($_POST['level_type']));
    update_option('indoor_tasks_default_level', sanitize_text_field($_POST['default_level']));
    update_option('indoor_tasks_max_levels', intval($_POST['max_levels']));
    update_option('indoor_tasks_admin_set_level', isset($_POST['admin_set_level']) ? 1 : 0);
    update_option('indoor_tasks_show_level_badge', isset($_POST['show_level_badge']) ? 1 : 0);
    
    // New settings
    update_option('indoor_tasks_level_progression_display', sanitize_text_field($_POST['level_progression_display'] ?? 'progress_bar'));
    update_option('indoor_tasks_show_level_benefits', isset($_POST['show_level_benefits']) ? 1 : 0);
    update_option('indoor_tasks_level_benefits_text', sanitize_textarea_field($_POST['level_benefits_text'] ?? ''));
    
    // Save level progression requirements
    update_option('indoor_tasks_level_task_requirement', sanitize_text_field($_POST['level_task_requirement'] ?? 'total'));
    update_option('indoor_tasks_level_icon_directory', esc_url_raw($_POST['level_icon_directory'] ?? ''));
    
    // Save level benefits
    if (isset($_POST['level_benefits']) && !empty($_POST['level_benefits'])) {
        update_option('indoor_tasks_level_benefits', sanitize_textarea_field($_POST['level_benefits']));
    }
    
    // Process level definitions
    if (isset($_POST['level_definitions']) && !empty($_POST['level_definitions'])) {
        update_option('indoor_tasks_level_definitions', sanitize_textarea_field($_POST['level_definitions']));
        
        // Process the CSV data to update the user_levels table
        global $wpdb;
        $level_defs = explode("\n", sanitize_textarea_field($_POST['level_definitions']));
        
        // First, truncate the existing levels
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}indoor_task_user_levels");
        
        // Then insert the new levels
        foreach ($level_defs as $level_def) {
            $parts = explode(',', $level_def);
            if (count($parts) >= 7) {
                $wpdb->insert(
                    $wpdb->prefix . 'indoor_task_user_levels',
                    [
                        'name' => sanitize_text_field($parts[0]),
                        'min_tasks' => intval($parts[1]),
                        'min_referrals' => intval($parts[2]),
                        'max_daily_tasks' => intval($parts[3]),
                        'reward_multiplier' => floatval($parts[4]),
                        'withdrawal_time' => intval($parts[5]),
                        'badge_color' => sanitize_text_field($parts[6]),
                        'created_at' => current_time('mysql')
                    ]
                );
            }
        }
    }
    
    update_option('indoor_tasks_kyc_required', isset($_POST['kyc_required']) ? 1 : 0);
    update_option('indoor_tasks_kyc_fields', sanitize_textarea_field($_POST['kyc_fields']));
    echo '<div class="updated"><p>User & Level settings saved.</p></div>';
}

if (isset($_POST['it_wallet_save']) && current_user_can('manage_options')) {
    update_option('indoor_tasks_currency_unit', sanitize_text_field($_POST['currency_unit']));
    update_option('indoor_tasks_decimal_places', intval($_POST['decimal_places']));
    update_option('indoor_tasks_min_withdraw_points', intval($_POST['min_withdraw_points']));
    update_option('indoor_tasks_wallet_conversion_rules', sanitize_textarea_field($_POST['wallet_conversion_rules']));
    echo '<div class="updated"><p>Wallet & Currency settings saved.</p></div>';
}

if (isset($_POST['it_withdrawal_save']) && current_user_can('manage_options')) {
    update_option('indoor_tasks_enable_withdrawals', isset($_POST['enable_withdrawals']) ? 1 : 0);
    
    // Save withdrawal methods from repeater
    if (isset($_POST['withdrawal_methods']) && is_array($_POST['withdrawal_methods'])) {
        $methods = [];
        foreach ($_POST['withdrawal_methods'] as $method) {
            if (!empty($method['name'])) {
                $fields = [];
                if (!empty($method['input_fields']) && is_array($method['input_fields'])) {
                    foreach ($method['input_fields'] as $field) {
                        if (!empty($field['label'])) {
                            $fields[] = [
                                'label' => sanitize_text_field($field['label']),
                                'type' => sanitize_text_field($field['type'] ?? 'text'),
                                'required' => isset($field['required']) && $field['required'] ? true : false
                            ];
                        }
                    }
                }
                
                $methods[] = [
                    'name' => sanitize_text_field($method['name']),
                    'conversion' => floatval($method['conversion']),
                    'payout_label' => sanitize_text_field($method['payout_label'] ?? ''),
                    'currency_symbol' => sanitize_text_field($method['currency_symbol'] ?? ''),
                    'icon' => esc_url_raw($method['icon'] ?? ''),
                    'input_fields' => $fields,
                    'min_points' => intval($method['min_points'] ?? 0),
                    'max_points' => !empty($method['max_points']) ? intval($method['max_points']) : 0,
                    'fee' => sanitize_text_field($method['fee'] ?? ''),
                    'processing_time' => sanitize_text_field($method['processing_time'] ?? ''),
                    'manual_approval' => isset($method['manual_approval']) && $method['manual_approval'] ? true : false
                ];
            }
        }
        update_option('indoor_tasks_withdrawal_methods', $methods);
    }
    
    update_option('indoor_tasks_withdrawal_fees', intval($_POST['withdrawal_fees']));
    update_option('indoor_tasks_manual_approval_time', sanitize_text_field($_POST['manual_approval_time']));
    echo '<div class="updated"><p>Withdrawal settings saved.</p></div>';
}

if (isset($_POST['it_notify_save']) && current_user_can('manage_options')) {
    update_option('indoor_tasks_enable_inapp_notify', isset($_POST['enable_inapp_notify']) ? 1 : 0);
    update_option('indoor_tasks_enable_email_notify', isset($_POST['enable_email_notify']) ? 1 : 0);
    update_option('indoor_tasks_email_sender_name', sanitize_text_field($_POST['email_sender_name']));
    update_option('indoor_tasks_email_sender_address', sanitize_email($_POST['email_sender_address']));
    update_option('indoor_tasks_notify_templates', sanitize_textarea_field($_POST['notify_templates']));
    
    // Save Telegram notification settings
    update_option('indoor_tasks_telegram_enabled', isset($_POST['enable_telegram_notify']) ? 1 : 0);
    update_option('indoor_tasks_telegram_bot_token', sanitize_text_field($_POST['telegram_bot_token']));
    update_option('indoor_tasks_telegram_chat_id', sanitize_text_field($_POST['telegram_chat_id']));
    update_option('indoor_tasks_telegram_new_task_template', sanitize_textarea_field($_POST['telegram_new_task_template']));
    update_option('indoor_tasks_telegram_level_change_template', sanitize_textarea_field($_POST['telegram_level_change_template']));
    update_option('indoor_tasks_telegram_task_completion_template', sanitize_textarea_field($_POST['telegram_task_completion_template']));
    update_option('indoor_tasks_telegram_withdrawal_template', sanitize_textarea_field($_POST['telegram_withdrawal_template']));
    
    echo '<div class="updated"><p>Notification settings saved.</p></div>';
}

if (isset($_POST['it_task_save']) && current_user_can('manage_options')) {
    update_option('indoor_tasks_allow_repeated_tasks', isset($_POST['allow_repeated_tasks']) ? 1 : 0);
    update_option('indoor_tasks_max_users_per_task', intval($_POST['max_users_per_task']));
    update_option('indoor_tasks_auto_close_task', isset($_POST['auto_close_task']) ? 1 : 0);
    update_option('indoor_tasks_enable_proof_attachments', isset($_POST['enable_proof_attachments']) ? 1 : 0);
    update_option('indoor_tasks_required_fields_submission', sanitize_textarea_field($_POST['required_fields_submission']));
    
    // Task Submission Settings
    update_option('indoor_tasks_task_expiry_hours', intval($_POST['task_expiry_hours']));
    update_option('indoor_tasks_submission_time_limit', intval($_POST['submission_time_limit']));
    update_option('indoor_tasks_allow_submission_edit', isset($_POST['allow_submission_edit']) ? 1 : 0);
    update_option('indoor_tasks_enable_task_ratings', isset($_POST['enable_task_ratings']) ? 1 : 0);
    
    // Task Category Settings
    update_option('indoor_tasks_show_task_categories', isset($_POST['show_task_categories']) ? 1 : 0);
    update_option('indoor_tasks_allow_category_filtering', isset($_POST['allow_category_filtering']) ? 1 : 0);
    
    // Task Notification Settings
    update_option('indoor_tasks_notify_new_tasks', isset($_POST['notify_new_tasks']) ? 1 : 0);
    update_option('indoor_tasks_notify_task_completion', isset($_POST['notify_task_completion']) ? 1 : 0);
    update_option('indoor_tasks_notify_featured_tasks', isset($_POST['notify_featured_tasks']) ? 1 : 0);
    
    echo '<div class="updated"><p>Task settings saved.</p></div>';
}

if (isset($_POST['it_adsense_save']) && current_user_can('manage_options')) {
    update_option('indoor_tasks_enable_ads', isset($_POST['enable_ads']) ? 1 : 0);
    update_option('indoor_tasks_adsense_publisher_id', sanitize_text_field($_POST['adsense_publisher_id']));
    update_option('indoor_tasks_adsense', wp_kses_post($_POST['adsense_code']));
    update_option('indoor_tasks_ad_display_sections', isset($_POST['ad_display_sections']) ? $_POST['ad_display_sections'] : []);
    update_option('indoor_tasks_ad_placement', sanitize_text_field($_POST['ad_placement']));
    echo '<div class="updated"><p>AdSense settings saved.</p></div>';
}

if (isset($_POST['it_security_save']) && current_user_can('manage_options')) {
    // Save reCAPTCHA settings
    update_option('indoor_tasks_enable_recaptcha', isset($_POST['enable_recaptcha']) ? 1 : 0);
    update_option('indoor_tasks_recaptcha_site_key', sanitize_text_field($_POST['recaptcha_site_key']));
    update_option('indoor_tasks_recaptcha_secret_key', sanitize_text_field($_POST['recaptcha_secret_key']));
    update_option('indoor_tasks_recaptcha_version', sanitize_text_field($_POST['recaptcha_version']));
    
    // Save reCAPTCHA v3 specific settings
    if (isset($_POST['recaptcha_v3_score'])) {
        update_option('indoor_tasks_recaptcha_v3_score', sanitize_text_field($_POST['recaptcha_v3_score']));
    }
    
    // Save Firebase settings
    update_option('indoor_tasks_enable_google_login', isset($_POST['enable_google_login']) ? 1 : 0);
    update_option('indoor_tasks_firebase_api_key', sanitize_text_field($_POST['firebase_api_key']));
    update_option('indoor_tasks_firebase_auth_domain', sanitize_text_field($_POST['firebase_auth_domain']));
    update_option('indoor_tasks_firebase_project_id', sanitize_text_field($_POST['firebase_project_id']));
    update_option('indoor_tasks_firebase_storage_bucket', sanitize_text_field($_POST['firebase_storage_bucket']));
    update_option('indoor_tasks_firebase_messaging_sender_id', sanitize_text_field($_POST['firebase_messaging_sender_id']));
    update_option('indoor_tasks_firebase_app_id', sanitize_text_field($_POST['firebase_app_id']));
    update_option('indoor_tasks_firebase_measurement_id', sanitize_text_field($_POST['firebase_measurement_id']));
    
    echo '<div class="updated"><p>Security settings saved.</p></div>';
}

if (isset($_POST['it_referral_save']) && current_user_can('manage_options')) {
    // Basic settings
    update_option('indoor_tasks_enable_referral', isset($_POST['enable_referral']) ? 1 : 0);
    update_option('indoor_tasks_referral_reward_amount', intval($_POST['referral_reward_amount']));
    update_option('indoor_tasks_referee_bonus', intval($_POST['referee_bonus']));
    update_option('indoor_tasks_referral_link_base', sanitize_text_field($_POST['referral_link_base']));
    update_option('indoor_tasks_referral_cookie_expiry', intval($_POST['referral_cookie_expiry']));
    
    // Bonus conditions & timing
    update_option('indoor_tasks_referral_min_tasks', intval($_POST['referral_min_tasks']));
    update_option('indoor_tasks_referral_require_kyc', isset($_POST['referral_require_kyc']) ? 1 : 0);
    update_option('indoor_tasks_referral_delay_hours', intval($_POST['referral_delay_hours']));
    update_option('indoor_tasks_referral_expiry_days', intval($_POST['referral_expiry_days']));
    
    // Anti-spam settings
    update_option('indoor_tasks_detect_fake_referrals', isset($_POST['detect_fake_referrals']) ? 1 : 0);
    update_option('indoor_tasks_max_referrals_per_user', intval($_POST['max_referrals_per_user']));
    update_option('indoor_tasks_max_referrals_per_ip', intval($_POST['max_referrals_per_ip']));
    update_option('indoor_tasks_blocked_email_domains', sanitize_textarea_field($_POST['blocked_email_domains']));
    update_option('indoor_tasks_block_same_ip_referrals', isset($_POST['block_same_ip_referrals']) ? 1 : 0);
    update_option('indoor_tasks_block_same_device_referrals', isset($_POST['block_same_device_referrals']) ? 1 : 0);
    
    // Multi-level referrals
    update_option('indoor_tasks_enable_referral_levels', isset($_POST['enable_referral_levels']) ? 1 : 0);
    update_option('indoor_tasks_referral_level_commission', sanitize_textarea_field($_POST['referral_level_commission']));
    update_option('indoor_tasks_max_referral_levels', intval($_POST['max_referral_levels']));
    
    // Notification settings
    update_option('indoor_tasks_notify_successful_referral', isset($_POST['notify_successful_referral']) ? 1 : 0);
    update_option('indoor_tasks_referral_email_notifications', isset($_POST['referral_email_notifications']) ? 1 : 0);
    update_option('indoor_tasks_referral_history_page', isset($_POST['referral_history_page']) ? 1 : 0);
    
    echo '<div class="updated"><p>Referral settings saved successfully!</p></div>';
}

// Get existing withdrawal methods
$withdrawal_methods = get_option('indoor_tasks_withdrawal_methods', []);
if (empty($withdrawal_methods) || !is_array($withdrawal_methods)) {
    $withdrawal_methods = [];
}
?>

<style>
/* Tab styling */
.indoor-tasks-settings-wrapper {
    max-width: 1200px;
}
.indoor-tasks-settings-tabs {
    display: flex;
    flex-wrap: wrap;
    margin-bottom: 20px;
    border-bottom: 1px solid #ccc;
    position: sticky;
    top: 32px;
    background: white;
    z-index: 10;
    padding: 10px 0 0;
}
.indoor-tasks-tab-button {
    padding: 12px 18px;
    background: #f8f8f8;
    border: 1px solid #ccc;
    border-bottom: none;
    margin-right: 5px;
    cursor: pointer;
    border-radius: 5px 5px 0 0;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
}
.indoor-tasks-tab-button i {
    margin-right: 5px;
}
.indoor-tasks-tab-button:hover {
    background: #f0f0f0;
}
.indoor-tasks-tab-button.active {
    background: #fff;
    border-bottom: 1px solid #fff;
    margin-bottom: -1px;
    color: #2271b1;
}
.indoor-tasks-settings-section {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    padding: 32px 28px;
    margin-bottom: 32px;
    max-width: 1100px;
    display: none;
    transition: all 0.3s ease;
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.indoor-tasks-settings-section.active {
    display: block;
}
.indoor-tasks-settings-section h2 {
    margin-top: 0;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
    color: #2271b1;
}
.indoor-tasks-settings-table {
    width: 100%;
    border-collapse: collapse;
}
.indoor-tasks-settings-table th {
    text-align: left;
    vertical-align: top;
    width: 200px;
    padding: 15px 20px 15px 0;
}
.indoor-tasks-settings-table td {
    padding: 12px 0;
    vertical-align: middle;
}
.indoor-tasks-settings-table input[type="text"],
.indoor-tasks-settings-table input[type="number"],
.indoor-tasks-settings-table input[type="email"],
.indoor-tasks-settings-table select,
.indoor-tasks-settings-table textarea {
    min-width: 350px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.indoor-tasks-settings-table small {
    display: block;
    color: #666;
    margin-top: 5px;
}
.indoor-tasks-settings-table input[type="checkbox"] {
    width: 16px;
    height: 16px;
}

/* Section headers */
.settings-section-header {
    padding-bottom: 10px;
    margin: 20px 0 15px;
    border-bottom: 1px solid #eee;
    font-size: 16px;
    color: #444;
}

/* Repeater field styling */
.withdrawal-method {
    background: #f8f8f8;
    border: 1px solid #ddd;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 5px;
    position: relative;
    transition: all 0.3s ease;
}
.withdrawal-method:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.withdrawal-method h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
}
.withdrawal-method h3 i {
    margin-right: 8px;
    color: #2271b1;
}
.remove-method {
    position: absolute;
    top: 15px;
    right: 15px;
    color: #d63638;
    text-decoration: none;
    cursor: pointer;
    font-size: 18px;
    background: #fff;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #f0f0f0;
    transition: all 0.2s ease;
}
.remove-method:hover {
    background: #f9e3e3;
}
.method-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    grid-gap: 15px;
    margin-bottom: 20px;
}
.method-grid > div {
    margin-bottom: 5px;
}
.method-grid label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #444;
}
.input-fields-container {
    border: 1px solid #ddd;
    padding: 20px;
    margin-bottom: 15px;
    background: #fff;
    border-radius: 5px;
}
.input-fields-container h4 {
    margin-top: 0;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    color: #444;
}
.input-field {
    padding: 15px;
    background: #f8f8f8;
    margin-bottom: 15px;
    border-radius: 5px;
    position: relative;
    border: 1px solid #eee;
}
.remove-field {
    position: absolute;
    top: 10px;
    right: 10px;
    color: #d63638;
    cursor: pointer;
    font-size: 16px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 1px solid #f0f0f0;
}
.remove-field:hover {
    background: #f9e3e3;
}
.add-button {
    background: #f0f0f1;
    border: 1px dashed #999;
    padding: 10px;
    text-align: center;
    cursor: pointer;
    border-radius: 4px;
    margin-top: 10px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.add-button i {
    margin-right: 5px;
}
.add-button:hover {
    background: #e5e5e5;
    border-color: #666;
}
.settings-submit-btn {
    margin-top: 20px;
    padding: 8px 20px;
}
</style>

<div class="indoor-tasks-settings-wrapper">
<!-- Tab navigation -->
<div class="indoor-tasks-settings-tabs">
    <div class="indoor-tasks-tab-button" data-tab="tab-general">
        <i class="dashicons dashicons-admin-settings"></i> General
    </div>
    <div class="indoor-tasks-tab-button" data-tab="tab-users">
        <i class="dashicons dashicons-admin-users"></i> User & Level
    </div>
    <div class="indoor-tasks-tab-button" data-tab="tab-wallet">
        <i class="dashicons dashicons-money-alt"></i> Wallet
    </div>
    <div class="indoor-tasks-tab-button" data-tab="tab-withdrawal">
        <i class="dashicons dashicons-money"></i> Withdrawal
    </div>
    <div class="indoor-tasks-tab-button" data-tab="tab-notification">
        <i class="dashicons dashicons-bell"></i> Notification
    </div>
    <div class="indoor-tasks-tab-button" data-tab="tab-task">
        <i class="dashicons dashicons-clipboard"></i> Task
    </div>
    <div class="indoor-tasks-tab-button" data-tab="tab-adsense">
        <i class="dashicons dashicons-google"></i> AdSense
    </div>
    <div class="indoor-tasks-tab-button" data-tab="tab-security">
        <i class="dashicons dashicons-shield"></i> Security
    </div>
    <div class="indoor-tasks-tab-button" data-tab="tab-referral">
        <i class="dashicons dashicons-groups"></i> Referral
    </div>
</div>

<!-- General Settings Tab -->
<div class="indoor-tasks-settings-section active" id="tab-general">
  <h2><i class="dashicons dashicons-admin-settings"></i> General Settings</h2>
  <form method="post" enctype="multipart/form-data">
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Site Logo</th><td><input type="file" name="site_logo" accept="image/*" />
      <?php if ($logo = get_option('indoor_tasks_site_logo')): ?>
        <br><img src="<?= esc_url($logo) ?>" style="max-height:40px;margin-top:5px;">
      <?php endif; ?>
      </td></tr>
      <tr><th>Default Currency Label</th><td><input type="text" name="currency_label" value="<?= esc_attr(get_option('indoor_tasks_currency_label', 'Points')) ?>" /></td></tr>
      <tr><th>Timezone</th><td><select name="timezone"><?php foreach(timezone_identifiers_list() as $tz): ?><option value="<?= $tz ?>" <?= get_option('indoor_tasks_timezone','UTC')==$tz?'selected':'' ?>><?= $tz ?></option><?php endforeach; ?></select></td></tr>
      <tr><th>Task Approval Time (hours)</th><td><input type="number" name="task_approval_time" value="<?= esc_attr(get_option('indoor_tasks_task_approval_time', 24)) ?>" /></td></tr>
      <tr><th>Max Tasks Per Day (Default)</th><td><input type="number" name="max_tasks_per_day" value="<?= esc_attr(get_option('indoor_tasks_max_tasks_per_day', 10)) ?>" /></td></tr>
      <tr><th>Max Task Submission Upload Size (MB)</th><td><input type="number" name="max_upload_size" value="<?= esc_attr(get_option('indoor_tasks_max_upload_size', 5)) ?>" /></td></tr>
      <tr><th>Enable Loader Animation</th><td><input type="checkbox" name="enable_loader" value="1" <?= get_option('indoor_tasks_enable_loader', 1) ? 'checked' : '' ?> /></td></tr>
    </table>
    <button type="submit" name="it_general_save" class="button button-primary settings-submit-btn">Save General Settings</button>
  </form>
</div>

<!-- User & Level Settings Tab -->
<div class="indoor-tasks-settings-section" id="tab-users">
  <h2><i class="dashicons dashicons-admin-users"></i> User & Level Settings</h2>
  <form method="post">
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Enable Level System</th><td><input type="checkbox" name="enable_level_system" value="1" <?= get_option('indoor_tasks_enable_level_system', 1) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Level Type</th><td><select name="level_type"><option value="task" <?= get_option('indoor_tasks_level_type','task')=='task'?'selected':'' ?>>Task-Based</option><option value="referral" <?= get_option('indoor_tasks_level_type','task')=='referral'?'selected':'' ?>>Referral-Based</option><option value="mixed" <?= get_option('indoor_tasks_level_type','task')=='mixed'?'selected':'' ?>>Mixed</option></select><small>Determines how users progress through levels</small></td></tr>
      
      <tr class="level-divider"><td colspan="2"><hr></td></tr>
      <tr><th colspan="2" class="level-section-header">Level Configuration</th></tr>
      
      <tr><th>Default Level on Register</th><td>
        <select name="default_level">
          <option value="Bronze" <?= get_option('indoor_tasks_default_level', 'Bronze')=='Bronze'?'selected':'' ?>>Bronze</option>
          <option value="Silver" <?= get_option('indoor_tasks_default_level', 'Bronze')=='Silver'?'selected':'' ?>>Silver</option>
          <option value="Gold" <?= get_option('indoor_tasks_default_level', 'Bronze')=='Gold'?'selected':'' ?>>Gold</option>
          <option value="Platinum" <?= get_option('indoor_tasks_default_level', 'Bronze')=='Platinum'?'selected':'' ?>>Platinum</option>
          <option value="Diamond" <?= get_option('indoor_tasks_default_level', 'Bronze')=='Diamond'?'selected':'' ?>>Diamond</option>
        </select>
        <small>Level assigned to new users upon registration</small>
      </td></tr>
      
      <tr><th>Max Levels</th><td><input type="number" name="max_levels" value="<?= esc_attr(get_option('indoor_tasks_max_levels', 5)) ?>" />
      <small>Maximum number of levels in the system (1-10)</small></td></tr>
      
      <tr><th>Show Level Badge in UI</th><td><input type="checkbox" name="show_level_badge" value="1" <?= get_option('indoor_tasks_show_level_badge', 1) ? 'checked' : '' ?> />
      <small>Display level badges next to usernames throughout the site</small></td></tr>
      
      <tr><th>Allow Admin to Manually Set Level</th><td><input type="checkbox" name="admin_set_level" value="1" <?= get_option('indoor_tasks_admin_set_level', 1) ? 'checked' : '' ?> />
      <small>Enable administrators to manually change user levels</small></td></tr>
      
      <tr><th>Level Progression Display</th><td>
        <select name="level_progression_display">
          <option value="progress_bar" <?= get_option('indoor_tasks_level_progression_display', 'progress_bar')=='progress_bar'?'selected':'' ?>>Progress Bar</option>
          <option value="percentage" <?= get_option('indoor_tasks_level_progression_display', 'progress_bar')=='percentage'?'selected':'' ?>>Percentage</option>
          <option value="fraction" <?= get_option('indoor_tasks_level_progression_display', 'progress_bar')=='fraction'?'selected':'' ?>>Fraction (e.g., 15/20 tasks)</option>
          <option value="none" <?= get_option('indoor_tasks_level_progression_display', 'progress_bar')=='none'?'selected':'' ?>>Don't Show</option>
        </select>
        <small>How to display level progression to users</small>
      </td></tr>
      
      <tr class="level-divider"><td colspan="2"><hr></td></tr>
      <tr><th colspan="2" class="level-section-header">Level Definitions</th></tr>
      
      <tr><th>Level Definitions</th><td>
        <div class="level-definitions-wrapper">
          <div class="level-definition-header">
            <div class="level-col">Level Name</div>
            <div class="level-col">Tasks Required</div>
            <div class="level-col">Referrals Required</div>
            <div class="level-col">Max Daily Tasks</div>
            <div class="level-col">Reward Multiplier</div>
            <div class="level-col">Withdrawal Time (hours)</div>
            <div class="level-col">Badge Color</div>
          </div>
          
          <?php 
          $level_definitions = get_option('indoor_tasks_level_definitions', '');
          $levels = [];
          
          if (!empty($level_definitions)) {
            $rows = explode("\n", $level_definitions);
            foreach ($rows as $row) {
              $levels[] = explode(',', $row);
            }
          }
          
          // Ensure we have at least 3 levels (Bronze, Silver, Gold)
          if (count($levels) < 3) {
            $levels = [
              ['Bronze', '0', '0', '5', '1.0', '48', '#cd7f32'],
              ['Silver', '10', '3', '10', '1.2', '24', '#c0c0c0'],
              ['Gold', '30', '10', '20', '1.5', '12', '#ffd700']
            ];
          }
          
          foreach ($levels as $i => $level) {
            echo '<div class="level-definition-row">';
            echo '<div class="level-col"><input type="text" name="level_name[]" value="' . esc_attr($level[0]) . '" placeholder="Level name"></div>';
            echo '<div class="level-col"><input type="number" name="tasks_required[]" value="' . esc_attr($level[1]) . '" placeholder="0"></div>';
            echo '<div class="level-col"><input type="number" name="referrals_required[]" value="' . esc_attr($level[2]) . '" placeholder="0"></div>';
            echo '<div class="level-col"><input type="number" name="max_daily_tasks[]" value="' . esc_attr($level[3]) . '" placeholder="10"></div>';
            echo '<div class="level-col"><input type="text" name="reward_multiplier[]" value="' . esc_attr($level[4]) . '" placeholder="1.0"></div>';
            echo '<div class="level-col"><input type="number" name="withdrawal_time[]" value="' . esc_attr($level[5]) . '" placeholder="24"></div>';
            echo '<div class="level-col"><input type="text" class="color-picker" name="badge_color[]" value="' . esc_attr($level[6]) . '" placeholder="#cccccc"></div>';
            echo '</div>';
          }
          ?>
          
          <button type="button" id="add-level-btn" class="button">Add Level</button>
        </div>
        
        <input type="hidden" id="level-definitions-hidden" name="level_definitions" value="<?= esc_attr($level_definitions) ?>">
        <script>
        jQuery(document).ready(function($) {
          // Update hidden field with all level definitions on form submit
          $('#add-level-btn').on('click', function() {
            var newRow = `<div class="level-definition-row">
              <div class="level-col"><input type="text" name="level_name[]" placeholder="Level name"></div>
              <div class="level-col"><input type="number" name="tasks_required[]" placeholder="0"></div>
              <div class="level-col"><input type="number" name="referrals_required[]" placeholder="0"></div>
              <div class="level-col"><input type="number" name="max_daily_tasks[]" placeholder="10"></div>
              <div class="level-col"><input type="text" name="reward_multiplier[]" placeholder="1.0"></div>
              <div class="level-col"><input type="number" name="withdrawal_time[]" placeholder="24"></div>
              <div class="level-col"><input type="text" class="color-picker" name="badge_color[]" placeholder="#cccccc"></div>
            </div>`;
            $(newRow).insertBefore($(this));
          });
          
          // Update hidden field on form submit
          $('form').on('submit', function() {
            var levelData = [];
            $('.level-definition-row').each(function() {
              var row = $(this);
              var name = row.find('input[name="level_name[]"]').val() || '';
              var tasks = row.find('input[name="tasks_required[]"]').val() || '0';
              var referrals = row.find('input[name="referrals_required[]"]').val() || '0';
              var maxTasks = row.find('input[name="max_daily_tasks[]"]').val() || '10';
              var multiplier = row.find('input[name="reward_multiplier[]"]').val() || '1.0';
              var withdrawal = row.find('input[name="withdrawal_time[]"]').val() || '24';
              var color = row.find('input[name="badge_color[]"]').val() || '';
              
              levelData.push([name, tasks, referrals, maxTasks, multiplier, withdrawal, color].join(','));
            });
            
            $('#level-definitions-hidden').val(levelData.join('\n'));
          });
        });
        </script>
        <style>
        .level-section-header {
          font-size: 1.2em;
          padding: 10px 0;
          color: #2271b1;
        }
        .level-divider hr {
          border: none;
          border-top: 1px solid #eee;
          margin: 20px 0;
        }
        .level-definitions-wrapper {
          background: #f9f9f9;
          border: 1px solid #ddd;
          border-radius: 5px;
          padding: 15px;
          margin: 10px 0;
        }
        .level-definition-header {
          display: flex;
          font-weight: bold;
          margin-bottom: 10px;
          padding-bottom: 8px;
          border-bottom: 1px solid #ddd;
        }
        .level-definition-row {
          display: flex;
          margin-bottom: 10px;
          align-items: center;
        }
        .level-col {
          flex: 1;
          padding: 0 5px;
        }
        .level-col input {
          width: 100% !important;
          min-width: 0 !important;
        }
        #add-level-btn {
          margin-top: 10px;
        }
        
        /* Task section styling */
        .task-section-header {
          font-size: 1.2em;
          padding: 10px 0;
          color: #2271b1;
        }
        .task-divider hr {
          border: none;
          border-top: 1px solid #eee;
          margin: 20px 0;
        }
        </style>
      </td></tr>
      
      <tr class="level-divider"><td colspan="2"><hr></td></tr>
      <tr><th colspan="2" class="level-section-header">Level Benefits Display</th></tr>
      
      <tr><th>Show Level Benefits</th><td><input type="checkbox" name="show_level_benefits" value="1" <?= get_option('indoor_tasks_show_level_benefits', 1) ? 'checked' : '' ?> />
      <small>Display level benefits and progression requirements to users</small></td></tr>
      
      <tr><th>Level Benefits Text</th><td>
        <textarea name="level_benefits_text" style="width:100%;height:80px;"><?= esc_textarea(get_option('indoor_tasks_level_benefits_text', 'Higher levels give you access to more tasks, better rewards, and faster withdrawals. Complete tasks and refer friends to level up!')) ?></textarea>
        <small>Text to display above the level benefits table</small>
      </td></tr>
      
      <tr class="level-divider"><td colspan="2"><hr></td></tr>
      <tr><th colspan="2" class="level-section-header">Level Progression Requirements</th></tr>
      
      <tr><th>Task Completion Requirement</th><td>
        <select name="level_task_requirement" style="width:100%">
          <option value="total" <?= get_option('indoor_tasks_level_task_requirement', 'total') == 'total' ? 'selected' : '' ?>>Total Tasks Completed</option>
          <option value="monthly" <?= get_option('indoor_tasks_level_task_requirement', 'total') == 'monthly' ? 'selected' : '' ?>>Monthly Tasks Completed</option>
          <option value="streak" <?= get_option('indoor_tasks_level_task_requirement', 'total') == 'streak' ? 'selected' : '' ?>>Consecutive Days with Task Completion</option>
        </select>
        <small>How task completions are counted for level progression</small>
      </td></tr>
      
      <tr><th>Level Icon Directory</th><td>
        <input type="text" name="level_icon_directory" value="<?= esc_attr(get_option('indoor_tasks_level_icon_directory', INDOOR_TASKS_URL . 'assets/icons/levels/')) ?>" style="width:100%" />
        <small>URL to the directory containing level badge icons (e.g., bronze.png, silver.png, gold.png)</small>
      </td></tr>
      
      <tr><th>Level Benefits</th><td>
        <div class="level-benefits-wrapper">
          <div class="benefits-header">
            <div class="benefit-col">Level</div>
            <div class="benefit-col">Special Tasks Access</div>
            <div class="benefit-col">Fee Reduction (%)</div>
            <div class="benefit-col">Bonus Points (%)</div>
            <div class="benefit-col">Priority Support</div>
          </div>
          
          <?php
          $level_benefits = get_option('indoor_tasks_level_benefits', '');
          $benefits = [];
          
          if (!empty($level_benefits)) {
            $rows = explode("\n", $level_benefits);
            foreach ($rows as $row) {
              $benefits[] = explode(',', $row);
            }
          }
          
          // Default benefits if none are set
          if (count($benefits) < 3) {
            $benefits = [
              ['Bronze', 'No', '0', '0', 'No'],
              ['Silver', 'No', '5', '2', 'No'],
              ['Gold', 'Yes', '10', '5', 'Yes']
            ];
          }
          
          foreach ($benefits as $i => $benefit) {
            echo '<div class="benefit-row">';
            echo '<div class="benefit-col"><input type="text" name="benefit_level[]" value="' . esc_attr($benefit[0]) . '" readonly></div>';
            echo '<div class="benefit-col"><select name="benefit_special_tasks[]"><option value="Yes" ' . (isset($benefit[1]) && $benefit[1] == 'Yes' ? 'selected' : '') . '>Yes</option><option value="No" ' . (isset($benefit[1]) && $benefit[1] == 'No' ? 'selected' : '') . '>No</option></select></div>';
            echo '<div class="benefit-col"><input type="number" name="benefit_fee_reduction[]" value="' . esc_attr($benefit[2] ?? '0') . '" min="0" max="100"></div>';
            echo '<div class="benefit-col"><input type="number" name="benefit_bonus_points[]" value="' . esc_attr($benefit[3] ?? '0') . '" min="0" max="100"></div>';
            echo '<div class="benefit-col"><select name="benefit_priority_support[]"><option value="Yes" ' . (isset($benefit[4]) && $benefit[4] == 'Yes' ? 'selected' : '') . '>Yes</option><option value="No" ' . (isset($benefit[4]) && $benefit[4] == 'No' ? 'selected' : '') . '>No</option></select></div>';
            echo '</div>';
          }
          ?>
        </div>
        
        <input type="hidden" id="level-benefits-hidden" name="level_benefits" value="<?= esc_attr($level_benefits) ?>">
        <script>
        jQuery(document).ready(function($) {
          // Update hidden field on form submit
          $('form').on('submit', function() {
            var benefitData = [];
            $('.benefit-row').each(function() {
              var row = $(this);
              var level = row.find('input[name="benefit_level[]"]').val() || '';
              var tasks = row.find('select[name="benefit_special_tasks[]"]').val() || 'No';
              var fee = row.find('input[name="benefit_fee_reduction[]"]').val() || '0';
              var bonus = row.find('input[name="benefit_bonus_points[]"]').val() || '0';
              var support = row.find('select[name="benefit_priority_support[]"]').val() || 'No';
              
              benefitData.push([level, tasks, fee, bonus, support].join(','));
            });
            
            $('#level-benefits-hidden').val(benefitData.join('\n'));
          });
          
          // Sync benefit level names with level names
          $('input[name="level_name[]"]').on('change', function() {
            var levelNames = [];
            $('input[name="level_name[]"]').each(function() {
              levelNames.push($(this).val());
            });
            
            // Update benefit level names
            $('input[name="benefit_level[]"]').each(function(index) {
              if (index < levelNames.length) {
                $(this).val(levelNames[index]);
              }
            });
          });
        });
        </script>
        <style>
        .level-benefits-wrapper {
          background: #f9f9f9;
          border: 1px solid #ddd;
          border-radius: 5px;
          padding: 15px;
          margin: 10px 0;
        }
        .benefits-header {
          display: flex;
          font-weight: bold;
          margin-bottom: 10px;
          padding-bottom: 8px;
          border-bottom: 1px solid #ddd;
        }
        .benefit-row {
          display: flex;
          margin-bottom: 10px;
          align-items: center;
        }
        .benefit-col {
          flex: 1;
          padding: 0 5px;
        }
        .benefit-col input, .benefit-col select {
          width: 100% !important;
          min-width: 0 !important;
        }
        </style>
      </td></tr>
      
      <tr class="level-divider"><td colspan="2"><hr></td></tr>
      <tr><th colspan="2" class="level-section-header">KYC Settings</th></tr>
      
      <tr><th>KYC Required for Withdrawal</th><td><input type="checkbox" name="kyc_required" value="1" <?= get_option('indoor_tasks_kyc_required', 1) ? 'checked' : '' ?> />
      <small>Require KYC verification before users can withdraw</small></td></tr>
      
      <tr><th>KYC Form Fields</th><td><textarea name="kyc_fields" style="width:100%;height:40px;" placeholder="Aadhar,PAN,Address Proof"><?= esc_textarea(get_option('indoor_tasks_kyc_fields','Aadhar,PAN,Address Proof')) ?></textarea>
      <small>Comma-separated list of KYC document types required</small></td></tr>
    </table>
    <button type="submit" name="it_level_save" class="button button-primary settings-submit-btn">Save User & Level Settings</button>
  </form>
</div>

<!-- Wallet & Currency Settings Tab -->
<div class="indoor-tasks-settings-section" id="tab-wallet">
  <h2><i class="dashicons dashicons-money-alt"></i> Wallet & Currency Settings</h2>
  <form method="post">
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Default Currency Unit</th><td><input type="text" name="currency_unit" value="<?= esc_attr(get_option('indoor_tasks_currency_unit', 'Points')) ?>" /></td></tr>
      <tr><th>Decimal Places</th><td><input type="number" name="decimal_places" value="<?= esc_attr(get_option('indoor_tasks_decimal_places', 0)) ?>" /></td></tr>
      <tr><th>Minimum Withdrawal Points</th><td><input type="number" name="min_withdraw_points" value="<?= esc_attr(get_option('indoor_tasks_min_withdraw_points', 1000)) ?>" /></td></tr>
      <tr><th>Wallet Conversion Rules</th><td><textarea name="wallet_conversion_rules" style="width:100%;height:60px;" placeholder="UPI,100,₹100
USDT,100,$1"><?= esc_textarea(get_option('indoor_tasks_wallet_conversion_rules','')) ?></textarea><br><small>CSV: Method,Points,Value</small></td></tr>
    </table>
    <button type="submit" name="it_wallet_save" class="button button-primary settings-submit-btn">Save Wallet & Currency Settings</button>
  </form>
</div>

<!-- Withdrawal Settings Tab -->
<div class="indoor-tasks-settings-section" id="tab-withdrawal">
  <h2><i class="dashicons dashicons-money"></i> Withdrawal Settings</h2>
  <form method="post" id="withdrawal-settings-form">
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Enable Withdrawals</th><td><input type="checkbox" name="enable_withdrawals" value="1" <?= get_option('indoor_tasks_enable_withdrawals', 1) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Withdrawal Fees (Optional)</th><td><input type="number" name="withdrawal_fees" value="<?= esc_attr(get_option('indoor_tasks_withdrawal_fees', 0)) ?>" /></td></tr>
      <tr><th>Manual Approval Time</th><td><input type="text" name="manual_approval_time" value="<?= esc_attr(get_option('indoor_tasks_manual_approval_time', 'Within 2 working days')) ?>" /></td></tr>
    </table>
    
    <h3 class="settings-section-header"><i class="dashicons dashicons-money"></i> Withdrawal Methods</h3>
    <p><small>Add unlimited withdrawal methods with customizable fields for users</small></p>
    
    <div id="withdrawal-methods-container">
      <?php if (!empty($withdrawal_methods)): ?>
        <?php foreach ($withdrawal_methods as $index => $method): ?>
          <div class="withdrawal-method">
            <h3><i class="dashicons dashicons-media-text"></i> Withdrawal Method</h3>
            <a class="remove-method" title="Remove this method">×</a>
            
            <div class="method-grid">
              <div>
                <label>Method Name:</label>
                <input type="text" name="withdrawal_methods[<?= $index ?>][name]" value="<?= esc_attr($method['name']) ?>" class="widefat" placeholder="UPI, USDT, PayPal">
              </div>
              <div>
                <label>Conversion Rate:</label>
                <input type="number" step="0.01" name="withdrawal_methods[<?= $index ?>][conversion]" value="<?= esc_attr($method['conversion']) ?>" class="widefat" placeholder="100">
              </div>
              <div>
                <label>Payout Label:</label>
                <input type="text" name="withdrawal_methods[<?= $index ?>][payout_label]" value="<?= esc_attr($method['payout_label'] ?? '') ?>" class="widefat" placeholder="₹ per 100 points">
              </div>
              <div>
                <label>Currency Symbol:</label>
                <input type="text" name="withdrawal_methods[<?= $index ?>][currency_symbol]" value="<?= esc_attr($method['currency_symbol'] ?? '') ?>" class="widefat" placeholder="₹, $, €">
              </div>
              <div>
                <label>Icon URL:</label>
                <input type="text" name="withdrawal_methods[<?= $index ?>][icon]" value="<?= esc_attr($method['icon'] ?? '') ?>" class="widefat" placeholder="https://example.com/icon.png">
              </div>
              <div>
                <label>Minimum Points:</label>
                <input type="number" name="withdrawal_methods[<?= $index ?>][min_points]" value="<?= esc_attr($method['min_points'] ?? 0) ?>" class="widefat" placeholder="500">
              </div>
              <div>
                <label>Maximum Points (Optional):</label>
                <input type="number" name="withdrawal_methods[<?= $index ?>][max_points]" value="<?= esc_attr($method['max_points'] ?? '') ?>" class="widefat" placeholder="5000">
              </div>
              <div>
                <label>Fee (% or flat):</label>
                <input type="text" name="withdrawal_methods[<?= $index ?>][fee]" value="<?= esc_attr($method['fee'] ?? '') ?>" class="widefat" placeholder="5% or 50">
              </div>
              <div>
                <label>Processing Time:</label>
                <input type="text" name="withdrawal_methods[<?= $index ?>][processing_time]" value="<?= esc_attr($method['processing_time'] ?? '') ?>" class="widefat" placeholder="1-3 working days">
              </div>
              <div>
                <label>Manual Approval:</label>
                <input type="checkbox" name="withdrawal_methods[<?= $index ?>][manual_approval]" value="1" <?= isset($method['manual_approval']) && $method['manual_approval'] ? 'checked' : '' ?>>
              </div>
            </div>
            
            <div class="input-fields-container">
              <h4><i class="dashicons dashicons-forms"></i> Input Fields for Users</h4>
              <div class="input-fields">
                <?php if (!empty($method['input_fields']) && is_array($method['input_fields'])): ?>
                  <?php foreach ($method['input_fields'] as $fieldIndex => $field): ?>
                    <div class="input-field">
                      <a class="remove-field" title="Remove field">×</a>
                      <div class="method-grid">
                        <div>
                          <label>Field Label:</label>
                          <input type="text" name="withdrawal_methods[<?= $index ?>][input_fields][<?= $fieldIndex ?>][label]" value="<?= esc_attr($field['label']) ?>" class="widefat" placeholder="UPI ID, Wallet Address">
                        </div>
                        <div>
                          <label>Field Type:</label>
                          <select name="withdrawal_methods[<?= $index ?>][input_fields][<?= $fieldIndex ?>][type]" class="widefat">
                            <option value="text" <?= ($field['type'] ?? 'text') == 'text' ? 'selected' : '' ?>>Text</option>
                            <option value="email" <?= ($field['type'] ?? '') == 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="number" <?= ($field['type'] ?? '') == 'number' ? 'selected' : '' ?>>Number</option>
                          </select>
                        </div>
                        <div>
                          <label>Required:</label>
                          <input type="checkbox" name="withdrawal_methods[<?= $index ?>][input_fields][<?= $fieldIndex ?>][required]" value="1" <?= isset($field['required']) && $field['required'] ? 'checked' : '' ?>>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div class="add-button add-field" data-method-index="<?= $index ?>"><i class="dashicons dashicons-plus-alt"></i> Add Input Field</div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    
    <div class="add-button" id="add-withdrawal-method"><i class="dashicons dashicons-plus-alt"></i> Add Withdrawal Method</div>
    
    <button type="submit" name="it_withdrawal_save" class="button button-primary settings-submit-btn" style="margin-top:20px;">Save Withdrawal Settings</button>
  </form>
</div>

<!-- Notification Settings Tab -->
<div class="indoor-tasks-settings-section" id="tab-notification">
  <h2><i class="dashicons dashicons-bell"></i> Notification Settings</h2>
  <form method="post">
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Enable In-App Notifications</th><td><input type="checkbox" name="enable_inapp_notify" value="1" <?= get_option('indoor_tasks_enable_inapp_notify', 1) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Enable Email Notifications</th><td><input type="checkbox" name="enable_email_notify" value="1" <?= get_option('indoor_tasks_enable_email_notify', 1) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Email Sender Name</th><td><input type="text" name="email_sender_name" value="<?= esc_attr(get_option('indoor_tasks_email_sender_name', get_bloginfo('name'))) ?>" /></td></tr>
      <tr><th>Email Sender Address</th><td><input type="email" name="email_sender_address" value="<?= esc_attr(get_option('indoor_tasks_email_sender_address', get_bloginfo('admin_email'))) ?>" /></td></tr>
      <tr><th>Templates</th><td><textarea name="notify_templates" style="width:100%;height:80px;" placeholder="Task Approval: Your task is approved!
Task Rejection: Your task was rejected.
KYC Status: Your KYC status changed.
Withdrawal Status: Your withdrawal status changed."><?= esc_textarea(get_option('indoor_tasks_notify_templates','')) ?></textarea></td></tr>
    </table>
    
    <h3 class="settings-section-header"><i class="dashicons dashicons-megaphone"></i> Telegram Notifications</h3>
    <table class="form-table indoor-tasks-settings-table">
      <tr>
        <th>Enable Telegram Notifications</th>
        <td>
          <input type="checkbox" name="enable_telegram_notify" value="1" <?= get_option('indoor_tasks_telegram_enabled', 0) ? 'checked' : '' ?> />
          <p class="description"><?php _e('Check this box to enable Telegram notifications for important events.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
      <tr>
        <th>Telegram Bot Token</th>
        <td>
          <input type="text" name="telegram_bot_token" style="width:100%" value="<?= esc_attr(get_option('indoor_tasks_telegram_bot_token', '')) ?>" placeholder="123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" />
          <p class="description"><?php _e('Enter your Telegram Bot Token obtained from @BotFather.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
      <tr>
        <th>Telegram Chat ID</th>
        <td>
          <input type="text" name="telegram_chat_id" style="width:100%" value="<?= esc_attr(get_option('indoor_tasks_telegram_chat_id', '')) ?>" placeholder="-1001234567890" />
          <p class="description"><?php _e('Enter your Telegram Chat ID or Group/Channel ID where notifications should be sent.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
      <tr>
        <th>New Task Template</th>
        <td>
          <textarea name="telegram_new_task_template" style="width:100%;height:80px;"><?= esc_textarea(get_option('indoor_tasks_telegram_new_task_template', "🔔 *NEW TASK AVAILABLE*\n\n*{{title}}*\n\n📝 {{description}}\n\n💰 Reward: {{reward}} points\n\n⏱️ Deadline: {{deadline}}\n\n🏆 Level: {{level}}\n\nComplete now to earn points!")) ?></textarea>
          <p class="description"><?php _e('Template for new task notifications. Available placeholders: {{title}}, {{description}}, {{reward}}, {{deadline}}, {{category}}, {{level}}, {{featured}}.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
      <tr>
        <th>Level Change Template</th>
        <td>
          <textarea name="telegram_level_change_template" style="width:100%;height:80px;"><?= esc_textarea(get_option('indoor_tasks_telegram_level_change_template', "🎉 *USER LEVEL UPGRADED*\n\nUser: {{username}}\nOld Level: {{old_level}}\nNew Level: {{new_level}}\n\nCongratulations on reaching a higher level!")) ?></textarea>
          <p class="description"><?php _e('Template for level change notifications. Available placeholders: {{username}}, {{user_id}}, {{old_level}}, {{new_level}}, {{date}}.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
      <tr>
        <th>Task Completion Template</th>
        <td>
          <textarea name="telegram_task_completion_template" style="width:100%;height:80px;"><?= esc_textarea(get_option('indoor_tasks_telegram_task_completion_template', "✅ *TASK COMPLETED*\n\nTask: {{title}}\nCompleted by: {{username}}\nPoints Earned: {{reward}}\nDate: {{date}}")) ?></textarea>
          <p class="description"><?php _e('Template for task completion notifications. Available placeholders: {{title}}, {{username}}, {{user_id}}, {{reward}}, {{date}}.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
      <tr>
        <th>Withdrawal Template</th>
        <td>
          <textarea name="telegram_withdrawal_template" style="width:100%;height:80px;"><?= esc_textarea(get_option('indoor_tasks_telegram_withdrawal_template', "💰 *WITHDRAWAL {{status}}*\n\nUser: {{username}}\nAmount: {{amount}}\nMethod: {{method}}\nDate: {{date}}")) ?></textarea>
          <p class="description"><?php _e('Template for withdrawal status notifications. Available placeholders: {{username}}, {{user_id}}, {{amount}}, {{points}}, {{method}}, {{date}}, {{status}}.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
      <tr>
        <th>Test Telegram Integration</th>
        <td>
          <a href="<?= admin_url('admin.php?page=indoor-tasks-settings&tab=notification&test_telegram=1') ?>" class="button button-secondary">
            <i class="dashicons dashicons-controls-play"></i> Send Test Message
          </a>
          <p class="description"><?php _e('Click to send a test message to verify your Telegram integration is working properly.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
    </table>
    
    <button type="submit" name="it_notify_save" class="button button-primary settings-submit-btn">Save Notification Settings</button>
  </form>
</div>

<!-- Task Settings Tab -->
<div class="indoor-tasks-settings-section" id="tab-task">
  <h2><i class="dashicons dashicons-clipboard"></i> Task Settings</h2>
  <form method="post">
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Allow Repeated Tasks</th><td><input type="checkbox" name="allow_repeated_tasks" value="1" <?= get_option('indoor_tasks_allow_repeated_tasks', 0) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Max Users per Task (Default)</th><td><input type="number" name="max_users_per_task" value="<?= esc_attr(get_option('indoor_tasks_max_users_per_task', 100)) ?>" /></td></tr>
      <tr><th>Auto-Close Task on Max Limit</th><td><input type="checkbox" name="auto_close_task" value="1" <?= get_option('indoor_tasks_auto_close_task', 1) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Enable Proof Attachments</th><td><input type="checkbox" name="enable_proof_attachments" value="1" <?= get_option('indoor_tasks_enable_proof_attachments', 1) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Required Fields on Submission</th><td><textarea name="required_fields_submission" style="width:100%;height:40px;" placeholder="Text Description,Screenshot,Link"><?= esc_textarea(get_option('indoor_tasks_required_fields_submission','Text Description,Screenshot,Link')) ?></textarea></td></tr>
      
      <tr class="task-divider"><td colspan="2"><hr></td></tr>
      <tr><th colspan="2" class="task-section-header">Task Submission Settings</th></tr>
      
      <tr><th>Default Task Expiry (hours)</th><td><input type="number" name="task_expiry_hours" value="<?= esc_attr(get_option('indoor_tasks_task_expiry_hours', 72)) ?>" /></td></tr>
      <tr><th>Submission Time Limit (minutes)</th><td><input type="number" name="submission_time_limit" value="<?= esc_attr(get_option('indoor_tasks_submission_time_limit', 30)) ?>" />
      <small>Time limit for completing a task after claiming it (0 = no limit)</small></td></tr>
      <tr><th>Allow Submission Edit</th><td><input type="checkbox" name="allow_submission_edit" value="1" <?= get_option('indoor_tasks_allow_submission_edit', 0) ? 'checked' : '' ?> />
      <small>Allow users to edit their submissions before admin review</small></td></tr>
      <tr><th>Task Ratings</th><td><input type="checkbox" name="enable_task_ratings" value="1" <?= get_option('indoor_tasks_enable_task_ratings', 1) ? 'checked' : '' ?> />
      <small>Allow users to rate tasks after completion</small></td></tr>
      
      <tr class="task-divider"><td colspan="2"><hr></td></tr>
      <tr><th colspan="2" class="task-section-header">Task Category Settings</th></tr>
      
      <tr><th>Show Task Categories</th><td><input type="checkbox" name="show_task_categories" value="1" <?= get_option('indoor_tasks_show_task_categories', 1) ? 'checked' : '' ?> />
      <small>Display task categories on the task list page</small></td></tr>
      <tr><th>Allow Category Filtering</th><td><input type="checkbox" name="allow_category_filtering" value="1" <?= get_option('indoor_tasks_allow_category_filtering', 1) ? 'checked' : '' ?> />
      <small>Allow users to filter tasks by category</small></td></tr>
      
      <tr class="task-divider"><td colspan="2"><hr></td></tr>
      <tr><th colspan="2" class="task-section-header">Task Notification Settings</th></tr>
      
      <tr><th>Notify New Tasks</th><td><input type="checkbox" name="notify_new_tasks" value="1" <?= get_option('indoor_tasks_notify_new_tasks', 1) ? 'checked' : '' ?> />
      <small>Send notification when new tasks are published</small></td></tr>
      <tr><th>Notify Task Completion</th><td><input type="checkbox" name="notify_task_completion" value="1" <?= get_option('indoor_tasks_notify_task_completion', 0) ? 'checked' : '' ?> />
      <small>Send notification when tasks are completed</small></td></tr>
      <tr><th>Notify Featured Tasks</th><td><input type="checkbox" name="notify_featured_tasks" value="1" <?= get_option('indoor_tasks_notify_featured_tasks', 1) ? 'checked' : '' ?> />
      <small>Always notify for featured tasks regardless of other settings</small></td></tr>
    </table>
    <button type="submit" name="it_task_save" class="button button-primary settings-submit-btn">Save Task Settings</button>
  </form>
</div>

<!-- AdSense Settings Tab -->
<div class="indoor-tasks-settings-section" id="tab-adsense">
  <h2><i class="dashicons dashicons-google"></i> Google AdSense Settings</h2>
  <form method="post">
    <table class="form-table indoor-tasks-settings-table">
      <tr>
        <th>Enable Ads</th>
        <td>
          <input type="checkbox" name="enable_ads" value="1" <?= get_option('indoor_tasks_enable_ads', 1) ? 'checked' : '' ?> />
          <p class="description"><?php _e('Check this box to enable advertisements on your task platform.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
      <tr>
        <th>AdSense Publisher ID</th>
        <td>
          <input type="text" name="adsense_publisher_id" style="width:100%" value="<?= esc_attr(get_option('indoor_tasks_adsense_publisher_id', '')) ?>" placeholder="ca-pub-1234567890123456" />
          <p class="description"><?php _e('Enter your AdSense Publisher ID (e.g., ca-pub-1234567890123456).', 'indoor-tasks'); ?></p>
        </td>
      </tr>
      <tr>
        <th>AdSense Script</th>
        <td>
          <textarea name="adsense_code" style="width:100%;height:80px;" placeholder="Paste AdSense script here..."><?= esc_textarea(get_option('indoor_tasks_adsense', '')) ?></textarea>
          <p class="description"><?php _e('Paste your full AdSense code snippet here if you prefer to use the complete script rather than just the Publisher ID.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
      <tr>
        <th>Ad Display Locations</th>
        <td>
          <div style="margin-bottom: 5px;">
            <label><input type="checkbox" name="ad_display_sections[]" value="dashboard" <?= in_array('dashboard', (array)get_option('indoor_tasks_ad_display_sections', [])) ? 'checked' : '' ?>> <?php _e('Dashboard', 'indoor-tasks'); ?></label>
          </div>
          <div style="margin-bottom: 5px;">
            <label><input type="checkbox" name="ad_display_sections[]" value="task-list" <?= in_array('task-list', (array)get_option('indoor_tasks_ad_display_sections', [])) ? 'checked' : '' ?>> <?php _e('Task List', 'indoor-tasks'); ?></label>
          </div>
          <div style="margin-bottom: 5px;">
            <label><input type="checkbox" name="ad_display_sections[]" value="task-detail" <?= in_array('task-detail', (array)get_option('indoor_tasks_ad_display_sections', [])) ? 'checked' : '' ?>> <?php _e('Task Detail', 'indoor-tasks'); ?></label>
          </div>
          <div style="margin-bottom: 5px;">
            <label><input type="checkbox" name="ad_display_sections[]" value="wallet" <?= in_array('wallet', (array)get_option('indoor_tasks_ad_display_sections', [])) ? 'checked' : '' ?>> <?php _e('Wallet Page', 'indoor-tasks'); ?></label>
          </div>
          <div>
            <label><input type="checkbox" name="ad_display_sections[]" value="withdrawal" <?= in_array('withdrawal', (array)get_option('indoor_tasks_ad_display_sections', [])) ? 'checked' : '' ?>> <?php _e('Withdrawal Page', 'indoor-tasks'); ?></label>
          </div>
        </td>
      </tr>
      <tr>
        <th>Ad Placement</th>
        <td>
          <select name="ad_placement" style="width:100%">
            <option value="top" <?= get_option('indoor_tasks_ad_placement', 'top') == 'top' ? 'selected' : '' ?>><?php _e('Top of Content', 'indoor-tasks'); ?></option>
            <option value="bottom" <?= get_option('indoor_tasks_ad_placement', 'top') == 'bottom' ? 'selected' : '' ?>><?php _e('Bottom of Content', 'indoor-tasks'); ?></option>
            <option value="both" <?= get_option('indoor_tasks_ad_placement', 'top') == 'both' ? 'selected' : '' ?>><?php _e('Both Top and Bottom', 'indoor-tasks'); ?></option>
          </select>
          <p class="description"><?php _e('Choose where to place the ads within each page.', 'indoor-tasks'); ?></p>
        </td>
      </tr>
    </table>
    <button type="submit" name="it_adsense_save" class="button button-primary settings-submit-btn">Save AdSense Settings</button>
  </form>
</div>

<!-- Security Settings Tab -->
<div class="indoor-tasks-settings-section" id="tab-security">
  <h2><i class="dashicons dashicons-shield"></i> Security Settings</h2>
  <form method="post">
    <h3 class="settings-section-header"><i class="dashicons dashicons-lock"></i> reCAPTCHA Settings</h3>
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Enable Google reCAPTCHA</th><td><input type="checkbox" name="enable_recaptcha" value="1" <?= get_option('indoor_tasks_enable_recaptcha', 0) ? 'checked' : '' ?> /></td></tr>
      <tr>
        <th>reCAPTCHA Version</th>
        <td>
          <select name="recaptcha_version" id="recaptcha_version">
            <option value="v2" <?= get_option('indoor_tasks_recaptcha_version', 'v2') === 'v2' ? 'selected' : '' ?>>reCAPTCHA v2</option>
            <option value="v3" <?= get_option('indoor_tasks_recaptcha_version', 'v2') === 'v3' ? 'selected' : '' ?>>reCAPTCHA v3</option>
          </select>
          <p class="description">V3 is recommended for better user experience. No challenge required for users.</p>
        </td>
      </tr>
      <tr><th>reCAPTCHA Site Key</th><td><input type="text" name="recaptcha_site_key" value="<?= esc_attr(get_option('indoor_tasks_recaptcha_site_key', '')) ?>" /></td></tr>
      <tr><th>reCAPTCHA Secret Key</th><td><input type="text" name="recaptcha_secret_key" value="<?= esc_attr(get_option('indoor_tasks_recaptcha_secret_key', '')) ?>" /></td></tr>
      <tr id="recaptcha-v3-score" <?= get_option('indoor_tasks_recaptcha_version', 'v2') === 'v3' ? '' : 'style="display:none;"' ?>>
        <th>reCAPTCHA v3 Threshold Score</th>
        <td>
          <select name="recaptcha_v3_score">
            <option value="0.1" <?= get_option('indoor_tasks_recaptcha_v3_score', '0.5') === '0.1' ? 'selected' : '' ?>>0.1 (Very lenient)</option>
            <option value="0.3" <?= get_option('indoor_tasks_recaptcha_v3_score', '0.5') === '0.3' ? 'selected' : '' ?>>0.3 (Lenient)</option>
            <option value="0.5" <?= get_option('indoor_tasks_recaptcha_v3_score', '0.5') === '0.5' ? 'selected' : '' ?>>0.5 (Moderate)</option>
            <option value="0.7" <?= get_option('indoor_tasks_recaptcha_v3_score', '0.5') === '0.7' ? 'selected' : '' ?>>0.7 (Strict)</option>
            <option value="0.9" <?= get_option('indoor_tasks_recaptcha_v3_score', '0.5') === '0.9' ? 'selected' : '' ?>>0.9 (Very strict)</option>
          </select>
          <p class="description">Higher values are more strict but may reject more legitimate users.</p>
        </td>
      </tr>
    </table>
    
    <h3 class="settings-section-header"><i class="dashicons dashicons-google"></i> Google Login with Firebase</h3>
    <table class="form-table indoor-tasks-settings-table">
      <tr>
        <th>Enable Google Login</th>
        <td><input type="checkbox" name="enable_google_login" value="1" <?= get_option('indoor_tasks_enable_google_login', 0) ? 'checked' : '' ?> /></td>
      </tr>
      <tr>
        <th>OAuth Redirect/Callback URL</th>
        <td>
          <code style="background: #f1f1f1; padding: 8px; display: block; margin-bottom: 5px;"><?= home_url('/auth/') ?></code>
          <small>Copy this URL and add it to your Google OAuth application's authorized redirect URIs.</small>
        </td>
      </tr>
      <tr>
        <th>Google Client ID</th>
        <td><input type="text" name="google_client_id" value="<?= esc_attr(get_option('indoor_tasks_google_client_id', '')) ?>" placeholder="Your Google OAuth Client ID" style="width: 100%;" /></td>
      </tr>
      <tr>
        <th>Google Client Secret</th>
        <td><input type="password" name="google_client_secret" value="<?= esc_attr(get_option('indoor_tasks_google_client_secret', '')) ?>" placeholder="Your Google OAuth Client Secret" style="width: 100%;" /></td>
      </tr>
      <tr>
        <th>Firebase API Key</th>
        <td><input type="text" name="firebase_api_key" value="<?= esc_attr(get_option('indoor_tasks_firebase_api_key', '')) ?>" /></td>
      </tr>
      <tr>
        <th>Firebase Auth Domain</th>
        <td><input type="text" name="firebase_auth_domain" value="<?= esc_attr(get_option('indoor_tasks_firebase_auth_domain', '')) ?>" /></td>
      </tr>
      <tr>
        <th>Firebase Project ID</th>
        <td><input type="text" name="firebase_project_id" value="<?= esc_attr(get_option('indoor_tasks_firebase_project_id', '')) ?>" /></td>
      </tr>
      <tr>
        <th>Firebase Storage Bucket</th>
        <td><input type="text" name="firebase_storage_bucket" value="<?= esc_attr(get_option('indoor_tasks_firebase_storage_bucket', '')) ?>" /></td>
      </tr>
      <tr>
        <th>Firebase Messaging Sender ID</th>
        <td><input type="text" name="firebase_messaging_sender_id" value="<?= esc_attr(get_option('indoor_tasks_firebase_messaging_sender_id', '')) ?>" /></td>
      </tr>
      <tr>
        <th>Firebase App ID</th>
        <td><input type="text" name="firebase_app_id" value="<?= esc_attr(get_option('indoor_tasks_firebase_app_id', '')) ?>" /></td>
      </tr>
      <tr>
        <th>Firebase Measurement ID</th>
        <td><input type="text" name="firebase_measurement_id" value="<?= esc_attr(get_option('indoor_tasks_firebase_measurement_id', '')) ?>" /></td>
      </tr>
    </table>
    
    <button type="submit" name="it_security_save" class="button button-primary settings-submit-btn">Save Security Settings</button>
  </form>
</div>

<!-- Referral Settings Tab -->
<div class="indoor-tasks-settings-section" id="tab-referral">
  <h2><i class="dashicons dashicons-groups"></i> Referral Settings</h2>
  <form method="post">
    <h3 class="settings-section-header"><i class="dashicons dashicons-admin-settings"></i> Basic Referral Settings</h3>
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Enable Referral System</th><td><input type="checkbox" name="enable_referral" value="1" <?= get_option('indoor_tasks_enable_referral', 1) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Referrer Bonus (Points)</th><td><input type="number" name="referral_reward_amount" value="<?= esc_attr(get_option('indoor_tasks_referral_reward_amount', 20)) ?>" min="0" /><br><small>Points awarded to the referrer</small></td></tr>
      <tr><th>Referee Welcome Bonus (Points)</th><td><input type="number" name="referee_bonus" value="<?= esc_attr(get_option('indoor_tasks_referee_bonus', 20)) ?>" min="0" /><br><small>Points awarded to the new user</small></td></tr>
      <tr><th>Referral Link Base URL</th><td><input type="text" name="referral_link_base" value="<?= esc_attr(get_option('indoor_tasks_referral_link_base', home_url('/register?ref='))) ?>" style="width: 100%;" /></td></tr>
      <tr><th>Referral Cookie Expiry (days)</th><td><input type="number" name="referral_cookie_expiry" value="<?= esc_attr(get_option('indoor_tasks_referral_cookie_expiry', 30)) ?>" min="1" max="365" /></td></tr>
    </table>
    
    <h3 class="settings-section-header"><i class="dashicons dashicons-clock"></i> Bonus Conditions & Timing</h3>
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Minimum Tasks Required</th><td><input type="number" name="referral_min_tasks" value="<?= esc_attr(get_option('indoor_tasks_referral_min_tasks', 1)) ?>" min="1" /><br><small>Number of tasks the referee must complete</small></td></tr>
      <tr><th>Require Profile Verification (KYC)</th><td><input type="checkbox" name="referral_require_kyc" value="1" <?= get_option('indoor_tasks_referral_require_kyc', 1) ? 'checked' : '' ?> /><br><small>Referee must verify their profile</small></td></tr>
      <tr><th>Bonus Delay (Hours)</th><td><input type="number" name="referral_delay_hours" value="<?= esc_attr(get_option('indoor_tasks_referral_delay_hours', 24)) ?>" min="0" max="168" /><br><small>Delay before awarding bonus (0 = immediate)</small></td></tr>
      <tr><th>Referral Expiry (Days)</th><td><input type="number" name="referral_expiry_days" value="<?= esc_attr(get_option('indoor_tasks_referral_expiry_days', 30)) ?>" min="1" max="365" /><br><small>Time limit for referee to complete requirements</small></td></tr>
    </table>
    
    <h3 class="settings-section-header"><i class="dashicons dashicons-shield"></i> Anti-Spam & Abuse Prevention</h3>
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Enable Spam Detection</th><td><input type="checkbox" name="detect_fake_referrals" value="1" <?= get_option('indoor_tasks_detect_fake_referrals', 1) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Max Referrals per User (Daily)</th><td><input type="number" name="max_referrals_per_user" value="<?= esc_attr(get_option('indoor_tasks_max_referrals_per_user', 10)) ?>" min="1" /><br><small>Daily limit per referrer</small></td></tr>
      <tr><th>Max Referrals per IP (Daily)</th><td><input type="number" name="max_referrals_per_ip" value="<?= esc_attr(get_option('indoor_tasks_max_referrals_per_ip', 3)) ?>" min="1" /><br><small>Daily limit per IP address</small></td></tr>
      <tr><th>Blocked Email Domains</th><td><textarea name="blocked_email_domains" style="width:100%;height:60px;" placeholder="tempmail.org,10minutemail.com,guerrillamail.com"><?= esc_textarea(get_option('indoor_tasks_blocked_email_domains','')) ?></textarea><br><small>Comma-separated list of disposable email domains to block</small></td></tr>
      <tr><th>Block Same IP Referrals</th><td><input type="checkbox" name="block_same_ip_referrals" value="1" <?= get_option('indoor_tasks_block_same_ip_referrals', 1) ? 'checked' : '' ?> /><br><small>Prevent referrals from same IP as referrer</small></td></tr>
      <tr><th>Block Same Device Referrals</th><td><input type="checkbox" name="block_same_device_referrals" value="1" <?= get_option('indoor_tasks_block_same_device_referrals', 1) ? 'checked' : '' ?> /><br><small>Prevent referrals from same device fingerprint</small></td></tr>
    </table>
    
    <h3 class="settings-section-header"><i class="dashicons dashicons-networking"></i> Multi-Level Referrals (MLM)</h3>
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Enable Multi-Level Referrals</th><td><input type="checkbox" name="enable_referral_levels" value="1" <?= get_option('indoor_tasks_enable_referral_levels', 0) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Referral Level Commission Structure</th><td><textarea name="referral_level_commission" style="width:100%;height:60px;" placeholder="Level 1,10
Level 2,5
Level 3,2"><?= esc_textarea(get_option('indoor_tasks_referral_level_commission','')) ?></textarea><br><small>CSV format: Level,Points (e.g., Level 1,10)</small></td></tr>
      <tr><th>Max Referral Levels</th><td><input type="number" name="max_referral_levels" value="<?= esc_attr(get_option('indoor_tasks_max_referral_levels', 3)) ?>" min="1" max="10" /><br><small>Maximum depth of referral chain</small></td></tr>
    </table>
    
    <h3 class="settings-section-header"><i class="dashicons dashicons-bell"></i> Notification Settings</h3>
    <table class="form-table indoor-tasks-settings-table">
      <tr><th>Notify on Successful Referral</th><td><input type="checkbox" name="notify_successful_referral" value="1" <?= get_option('indoor_tasks_notify_successful_referral', 1) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Email Notifications</th><td><input type="checkbox" name="referral_email_notifications" value="1" <?= get_option('indoor_tasks_referral_email_notifications', 1) ? 'checked' : '' ?> /></td></tr>
      <tr><th>Show Referral History</th><td><input type="checkbox" name="referral_history_page" value="1" <?= get_option('indoor_tasks_referral_history_page', 1) ? 'checked' : '' ?> /></td></tr>
    </table>
    
    <div style="background: #f0f8ff; border: 1px solid #b3d9ff; padding: 15px; margin: 20px 0; border-radius: 5px;">
      <h4 style="margin-top: 0; color: #2271b1;"><i class="dashicons dashicons-info"></i> Referral System Overview</h4>
      <p><strong>How it works:</strong></p>
      <ol>
        <li>User A shares their referral link</li>
        <li>User B clicks the link and registers</li>
        <li>User B must:
          <ul>
            <li>Complete <strong><?= get_option('indoor_tasks_referral_min_tasks', 1) ?></strong> task(s)</li>
            <?php if (get_option('indoor_tasks_referral_require_kyc', 1)): ?>
            <li>Verify their profile (KYC)</li>
            <?php endif; ?>
            <li>Have different IP address and device from User A</li>
          </ul>
        </li>
        <li>After <strong><?= get_option('indoor_tasks_referral_delay_hours', 24) ?> hours</strong>, both users receive points:
          <ul>
            <li>User A: <strong><?= get_option('indoor_tasks_referral_reward_amount', 20) ?></strong> points</li>
            <li>User B: <strong><?= get_option('indoor_tasks_referee_bonus', 20) ?></strong> points</li>
          </ul>
        </li>
      </ol>
    </div>
    
    <button type="submit" name="it_referral_save" class="button button-primary settings-submit-btn">Save Referral Settings</button>
  </form>
</div>

<!-- Handle Telegram test message -->
<?php
if (isset($_GET['test_telegram']) && $_GET['test_telegram'] == 1 && current_user_can('manage_options')) {
    $telegram_enabled = get_option('indoor_tasks_telegram_enabled', 0);
    $bot_token = get_option('indoor_tasks_telegram_bot_token', '');
    $chat_id = get_option('indoor_tasks_telegram_chat_id', '');
    
    if ($telegram_enabled && !empty($bot_token) && !empty($chat_id)) {
        $test_message = "🧪 *TEST MESSAGE*\n\nYour Indoor Tasks Telegram integration is working correctly!";
        $response = wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body' => [
                'chat_id' => $chat_id,
                'text' => $test_message,
                'parse_mode' => 'Markdown'
            ]
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
            echo '<div class="updated"><p>Test message sent successfully to Telegram!</p></div>';
        } else {
            echo '<div class="error"><p>Failed to send test message. Please check your Telegram configuration.</p></div>';
        }
    } else {
        echo '<div class="error"><p>Telegram is not properly configured. Please enable it and set Bot Token and Chat ID.</p></div>';
    }
}
?>

<!-- JavaScript for tab functionality and withdrawal method repeater -->
<script>
jQuery(document).ready(function($) {
    // Tab functionality
    $('.indoor-tasks-tab-button').on('click', function() {
        var tabId = $(this).data('tab');
        $('.indoor-tasks-tab-button').removeClass('active');
        $('.indoor-tasks-settings-section').removeClass('active');
        $(this).addClass('active');
        $('#' + tabId).addClass('active');
        
        // Save active tab to localStorage
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('indoor_tasks_active_tab', tabId);
        }
    });
    
    // Restore last active tab from localStorage
    if (typeof(Storage) !== "undefined") {
        var lastTab = localStorage.getItem('indoor_tasks_active_tab');
        if (lastTab) {
            $('.indoor-tasks-tab-button[data-tab="' + lastTab + '"]').click();
        } else {
            // Default to first tab
            $('.indoor-tasks-tab-button:first').click();
        }
    } else {
        // If localStorage is not available, default to first tab
        $('.indoor-tasks-tab-button:first').click();
    }
    
    // Add withdrawal method
    $('#add-withdrawal-method').on('click', function() {
        var template = `
            <div class="withdrawal-method">
                <h3><i class="dashicons dashicons-media-text"></i> Withdrawal Method</h3>
                <a class="remove-method" title="Remove this method">×</a>
                
                <div class="method-grid">
                    <div>
                        <label>Method Name:</label>
                        <input type="text" name="withdrawal_methods[${methodCount}][name]" class="widefat" placeholder="UPI, USDT, PayPal">
                    </div>
                    <div>
                        <label>Conversion Rate:</label>
                        <input type="number" step="0.01" name="withdrawal_methods[${methodCount}][conversion]" class="widefat" placeholder="100">
                    </div>
                    <div>
                        <label>Payout Label:</label>
                        <input type="text" name="withdrawal_methods[${methodCount}][payout_label]" class="widefat" placeholder="₹ per 100 points">
                    </div>
                    <div>
                        <label>Currency Symbol:</label>
                        <input type="text" name="withdrawal_methods[${methodCount}][currency_symbol]" class="widefat" placeholder="₹, $, €">
                    </div>
                    <div>
                        <label>Icon URL:</label>
                        <input type="text" name="withdrawal_methods[${methodCount}][icon]" class="widefat" placeholder="https://example.com/icon.png">
                    </div>
                    <div>
                        <label>Minimum Points:</label>
                        <input type="number" name="withdrawal_methods[${methodCount}][min_points]" class="widefat" placeholder="500">
                    </div>
                    <div>
                        <label>Maximum Points (Optional):</label>
                        <input type="number" name="withdrawal_methods[${methodCount}][max_points]" class="widefat" placeholder="5000">
                    </div>
                    <div>
                        <label>Fee (% or flat):</label>
                        <input type="text" name="withdrawal_methods[${methodCount}][fee]" class="widefat" placeholder="5% or 50">
                    </div>
                    <div>
                        <label>Processing Time:</label>
                        <input type="text" name="withdrawal_methods[${methodCount}][processing_time]" class="widefat" placeholder="1-3 working days">
                    </div>
                    <div>
                        <label>Manual Approval:</label>
                        <input type="checkbox" name="withdrawal_methods[${methodCount}][manual_approval]" value="1">
                    </div>
                </div>
                
                <div class="input-fields-container">
                    <h4><i class="dashicons dashicons-forms"></i> Input Fields for Users</h4>
                    <div class="input-fields"></div>
                    <div class="add-button add-field" data-method-index="${methodCount}"><i class="dashicons dashicons-plus-alt"></i> Add Input Field</div>
                </div>
            </div>
        `;
        
        $('#withdrawal-methods-container').append(template);
        methodCount++;
    });
    
    // Remove withdrawal method
    $(document).on('click', '.remove-method', function() {
        $(this).closest('.withdrawal-method').remove();
    });
    
    // Add input field
    $(document).on('click', '.add-field', function() {
        var methodIndex = $(this).data('method-index');
        var fieldCount = $(this).prev('.input-fields').children().length;
        
        var template = `
            <div class="input-field">
                <a class="remove-field" title="Remove field">×</a>
                <div class="method-grid">
                    <div>
                        <label>Field Label:</label>
                        <input type="text" name="withdrawal_methods[${methodIndex}][input_fields][${fieldCount}][label]" class="widefat" placeholder="UPI ID, Wallet Address">
                    </div>
                    <div>
                        <label>Field Type:</label>
                        <select name="withdrawal_methods[${methodIndex}][input_fields][${fieldCount}][type]" class="widefat">
                            <option value="text">Text</option>
                            <option value="email">Email</option>
                            <option value="number">Number</option>
                        </select>
                    </div>
                    <div>
                        <label>Required:</label>
                        <input type="checkbox" name="withdrawal_methods[${methodIndex}][input_fields][${fieldCount}][required]" value="1">
                    </div>
                </div>
            </div>
        `;
        
        $(this).prev('.input-fields').append(template);
    });
    
    // Remove input field
    $(document).on('click', '.remove-field', function() {
        $(this).closest('.input-field').remove();
    });
    
    // Form validation before submit
    $('#withdrawal-settings-form').on('submit', function() {
        var valid = true;
        $('.withdrawal-method').each(function(index) {
            var methodName = $(this).find('input[name^="withdrawal_methods"][name$="[name]"]').val();
            if (!methodName) {
                alert('Method name is required for all withdrawal methods.');
                valid = false;
                return false;
            }
        });
        return valid;
    });
    
    // Handle reCAPTCHA version change
    $('#recaptcha_version').on('change', function() {
        if ($(this).val() === 'v3') {
            $('#recaptcha-v3-score').show();
        } else {
            $('#recaptcha-v3-score').hide();
        }
    });
});

// JavaScript to handle reCAPTCHA version toggling and form submission
jQuery(document).ready(function($) {
    // Handle reCAPTCHA version change
    $('#recaptcha_version').on('change', function() {
        if ($(this).val() === 'v3') {
            $('#recaptcha-v3-score').show();
        } else {
            $('#recaptcha-v3-score').hide();
        }
    });
    
    // Initialize the visibility of the v3 score settings based on the selected version
    if ($('#recaptcha_version').val() === 'v3') {
        $('#recaptcha-v3-score').show();
    } else {
        $('#recaptcha-v3-score').hide();
    }
});
</script>
</div>
