<?php
// Admin notification templates/settings page
?><div class="wrap">
<h1><?php _e('Notification Settings', 'indoor-tasks'); ?></h1>
<!-- Configure notification templates -->
<?php
// Handle Telegram test
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

// Handle OneSignal test
if (isset($_GET['test_onesignal']) && $_GET['test_onesignal'] == 1 && current_user_can('manage_options')) {
    $enable_push = get_option('indoor_tasks_enable_push_notifications', 0);
    $app_id = get_option('indoor_tasks_onesignal_app_id', '');
    $api_key = get_option('indoor_tasks_onesignal_api_key', '');
    
    if ($enable_push && !empty($app_id) && !empty($api_key)) {
        $fields = array(
            'app_id' => $app_id,
            'headings' => array('en' => 'Test Notification'),
            'contents' => array('en' => 'This is a test notification from Indoor Tasks'),
            'included_segments' => array('All')
        );
        
        $response = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $api_key
            ),
            'body' => json_encode($fields),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
            echo '<div class="updated"><p>Test notification sent successfully via OneSignal!</p></div>';
        } else {
            echo '<div class="error"><p>Failed to send test notification. Please check your OneSignal configuration.</p></div>';
        }
    } else {
        echo '<div class="error"><p>OneSignal is not properly configured. Please enable it and set App ID and API Key.</p></div>';
    }
}

if (isset($_POST['it_notify_save']) && current_user_can('manage_options')) {
    update_option('indoor_tasks_notify_task_approved', sanitize_textarea_field($_POST['notify_task_approved']));
    update_option('indoor_tasks_notify_task_rejected', sanitize_textarea_field($_POST['notify_task_rejected']));
    update_option('indoor_tasks_notify_withdrawal', sanitize_textarea_field($_POST['notify_withdrawal']));
    update_option('indoor_tasks_notify_kyc', sanitize_textarea_field($_POST['notify_kyc']));
    update_option('indoor_tasks_telegram_bot_token', sanitize_text_field($_POST['telegram_bot_token']));
    update_option('indoor_tasks_telegram_chat_id', sanitize_text_field($_POST['telegram_chat_id']));
    update_option('indoor_tasks_telegram_enabled', isset($_POST['telegram_enabled']) ? 1 : 0);
    update_option('indoor_tasks_telegram_new_task_template', sanitize_textarea_field($_POST['telegram_new_task_template']));
    update_option('indoor_tasks_telegram_level_change_template', sanitize_textarea_field($_POST['telegram_level_change_template']));
    update_option('indoor_tasks_telegram_withdrawal_template', sanitize_textarea_field($_POST['telegram_withdrawal_template']));
    
    // OneSignal settings
    update_option('indoor_tasks_enable_push_notifications', isset($_POST['enable_push_notifications']) ? 1 : 0);
    update_option('indoor_tasks_onesignal_app_id', sanitize_text_field($_POST['onesignal_app_id']));
    update_option('indoor_tasks_onesignal_api_key', sanitize_text_field($_POST['onesignal_api_key']));
    update_option('indoor_tasks_onesignal_safari_web_id', sanitize_text_field($_POST['onesignal_safari_web_id']));
    update_option('indoor_tasks_onesignal_notification_types', isset($_POST['onesignal_notification_types']) ? $_POST['onesignal_notification_types'] : array());
    
    echo '<div class="updated"><p>Notification templates saved.</p></div>';
}
$task_approved = get_option('indoor_tasks_notify_task_approved', 'Your task submission has been approved!');
$task_rejected = get_option('indoor_tasks_notify_task_rejected', 'Your task submission was rejected.');
$withdrawal = get_option('indoor_tasks_notify_withdrawal', 'Your withdrawal request status has changed.');
$kyc = get_option('indoor_tasks_notify_kyc', 'Your KYC status has changed.');

// Telegram settings
$telegram_enabled = get_option('indoor_tasks_telegram_enabled', 0);
$telegram_bot_token = get_option('indoor_tasks_telegram_bot_token', '');
$telegram_chat_id = get_option('indoor_tasks_telegram_chat_id', '');
$telegram_new_task_template = get_option('indoor_tasks_telegram_new_task_template', "🔔 *NEW TASK AVAILABLE*\n\n*{{title}}*\n\n📝 {{description}}\n\n💰 Reward: {{reward}} points\n\n⏱️ Deadline: {{deadline}}\n\nComplete now to earn points!");
$telegram_level_change_template = get_option('indoor_tasks_telegram_level_change_template', "🎉 *USER LEVEL UPGRADED*\n\nUser: {{username}}\nOld Level: {{old_level}}\nNew Level: {{new_level}}\n\nCongratulations on reaching a higher level!");
$telegram_withdrawal_template = get_option('indoor_tasks_telegram_withdrawal_template', "💰 *WITHDRAWAL {{status}}*\n\nUser: {{username}}\nAmount: {{amount}}\nMethod: {{method}}\nDate: {{date}}");

// OneSignal settings
$enable_push = get_option('indoor_tasks_enable_push_notifications', 0);
$onesignal_app_id = get_option('indoor_tasks_onesignal_app_id', '');
$onesignal_api_key = get_option('indoor_tasks_onesignal_api_key', '');
$onesignal_safari_web_id = get_option('indoor_tasks_onesignal_safari_web_id', '');
$onesignal_notification_types = get_option('indoor_tasks_onesignal_notification_types', array());
?>
<form method="post">
  <h2><?php _e('Email Notifications', 'indoor-tasks'); ?></h2>
  <table class="form-table">
    <tr><th>Task Approved</th><td><textarea name="notify_task_approved" style="width:100%;height:40px;"><?= esc_textarea($task_approved) ?></textarea></td></tr>
    <tr><th>Task Rejected</th><td><textarea name="notify_task_rejected" style="width:100%;height:40px;"><?= esc_textarea($task_rejected) ?></textarea></td></tr>
    <tr><th>Withdrawal Status</th><td><textarea name="notify_withdrawal" style="width:100%;height:40px;"><?= esc_textarea($withdrawal) ?></textarea></td></tr>
    <tr><th>KYC Status</th><td><textarea name="notify_kyc" style="width:100%;height:40px;"><?= esc_textarea($kyc) ?></textarea></td></tr>
  </table>
  
  <h2><?php _e('Telegram Notifications', 'indoor-tasks'); ?></h2>
  <p><?php _e('Configure Telegram notifications for new tasks and important updates.', 'indoor-tasks'); ?></p>
  <table class="form-table">
    <tr>
      <th><?php _e('Enable Telegram', 'indoor-tasks'); ?></th>
      <td><input type="checkbox" name="telegram_enabled" value="1" <?php checked(1, $telegram_enabled); ?>> <?php _e('Enable Telegram notifications', 'indoor-tasks'); ?></td>
    </tr>
    <tr>
      <th><?php _e('Bot Token', 'indoor-tasks'); ?></th>
      <td>
        <input type="text" name="telegram_bot_token" value="<?php echo esc_attr($telegram_bot_token); ?>" style="width:100%;">
        <p class="description"><?php _e('Your Telegram Bot Token from BotFather.', 'indoor-tasks'); ?></p>
      </td>
    </tr>
    <tr>
      <th><?php _e('Chat ID', 'indoor-tasks'); ?></th>
      <td>
        <input type="text" name="telegram_chat_id" value="<?php echo esc_attr($telegram_chat_id); ?>" style="width:100%;">
        <p class="description"><?php _e('Chat ID where notifications will be sent (can be group ID or channel ID).', 'indoor-tasks'); ?></p>
      </td>
    </tr>
    <tr>
      <th><?php _e('New Task Template', 'indoor-tasks'); ?></th>
      <td>
        <textarea name="telegram_new_task_template" style="width:100%;height:120px;"><?= esc_textarea($telegram_new_task_template) ?></textarea>
        <p class="description"><?php _e('Template for new task notifications. Available variables: {{title}}, {{description}}, {{reward}}, {{deadline}}, {{category}}, {{level}}, {{featured}}.', 'indoor-tasks'); ?></p>
      </td>
    </tr>
    <tr>
      <th><?php _e('Level Change Template', 'indoor-tasks'); ?></th>
      <td>
        <textarea name="telegram_level_change_template" style="width:100%;height:120px;"><?= esc_textarea($telegram_level_change_template) ?></textarea>
        <p class="description"><?php _e('Template for user level change notifications. Available variables: {{username}}, {{user_id}}, {{old_level}}, {{new_level}}, {{date}}.', 'indoor-tasks'); ?></p>
      </td>
    </tr>
    <tr>
      <th><?php _e('Withdrawal Template', 'indoor-tasks'); ?></th>
      <td>
        <textarea name="telegram_withdrawal_template" style="width:100%;height:120px;"><?= esc_textarea($telegram_withdrawal_template) ?></textarea>
        <p class="description"><?php _e('Template for withdrawal status notifications. Available variables: {{username}}, {{user_id}}, {{amount}}, {{points}}, {{method}}, {{date}}, {{status}}.', 'indoor-tasks'); ?></p>
      </td>
    </tr>
    <tr>
      <th><?php _e('Test Connection', 'indoor-tasks'); ?></th>
      <td>
        <a href="<?php echo admin_url('admin.php?page=indoor-tasks-notifications-settings&test_telegram=1'); ?>" class="button"><?php _e('Send Test Message', 'indoor-tasks'); ?></a>
      </td>
    </tr>
  </table>
  
  <h2><?php _e('PWA Push Notifications', 'indoor-tasks'); ?></h2>
  <p><?php _e('Configure push notifications using OneSignal for Progressive Web App.', 'indoor-tasks'); ?></p>
  <table class="form-table">
    <tr>
      <th><?php _e('Enable Push Notifications', 'indoor-tasks'); ?></th>
      <td><input type="checkbox" name="enable_push_notifications" value="1" <?php checked(1, $enable_push); ?>> <?php _e('Enable OneSignal push notifications', 'indoor-tasks'); ?></td>
    </tr>
    <tr>
      <th><?php _e('OneSignal App ID', 'indoor-tasks'); ?></th>
      <td>
        <input type="text" name="onesignal_app_id" value="<?php echo esc_attr($onesignal_app_id); ?>" style="width:100%;">
        <p class="description"><?php _e('Your OneSignal App ID from the OneSignal dashboard.', 'indoor-tasks'); ?></p>
      </td>
    </tr>
    <tr>
      <th><?php _e('OneSignal REST API Key', 'indoor-tasks'); ?></th>
      <td>
        <input type="text" name="onesignal_api_key" value="<?php echo esc_attr($onesignal_api_key); ?>" style="width:100%;">
        <p class="description"><?php _e('Your OneSignal REST API Key from the OneSignal dashboard.', 'indoor-tasks'); ?></p>
      </td>
    </tr>
    <tr>
      <th><?php _e('Safari Web ID', 'indoor-tasks'); ?></th>
      <td>
        <input type="text" name="onesignal_safari_web_id" value="<?php echo esc_attr($onesignal_safari_web_id); ?>" style="width:100%;">
        <p class="description"><?php _e('Your Safari Web ID (optional, for Safari browser support).', 'indoor-tasks'); ?></p>
      </td>
    </tr>
    <tr>
      <th><?php _e('Notification Types', 'indoor-tasks'); ?></th>
      <td>
        <p><input type="checkbox" name="onesignal_notification_types[]" value="new_task" <?php checked(in_array('new_task', $onesignal_notification_types), true); ?>> <?php _e('New Tasks', 'indoor-tasks'); ?></p>
        <p><input type="checkbox" name="onesignal_notification_types[]" value="task_approved" <?php checked(in_array('task_approved', $onesignal_notification_types), true); ?>> <?php _e('Task Approvals', 'indoor-tasks'); ?></p>
        <p><input type="checkbox" name="onesignal_notification_types[]" value="withdrawal" <?php checked(in_array('withdrawal', $onesignal_notification_types), true); ?>> <?php _e('Withdrawal Status', 'indoor-tasks'); ?></p>
        <p><input type="checkbox" name="onesignal_notification_types[]" value="kyc" <?php checked(in_array('kyc', $onesignal_notification_types), true); ?>> <?php _e('KYC Status', 'indoor-tasks'); ?></p>
      </td>
    </tr>
    <tr>
      <th><?php _e('Test Notification', 'indoor-tasks'); ?></th>
      <td>
        <a href="<?php echo admin_url('admin.php?page=indoor-tasks-notifications-settings&test_onesignal=1'); ?>" class="button"><?php _e('Send Test Notification', 'indoor-tasks'); ?></a>
      </td>
    </tr>
  </table>
  
  <button type="submit" name="it_notify_save" class="button button-primary">Save Templates</button>
</form>
</div>
