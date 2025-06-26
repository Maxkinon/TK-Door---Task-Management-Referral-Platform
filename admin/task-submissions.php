<?php
// Admin review task submissions
?><div class="wrap">
<h1><?php _e('Task Submissions', 'indoor-tasks'); ?></h1>
<!-- Approve/Reject submissions -->
<?php
global $wpdb;
if (isset($_POST['it_submission_action']) && current_user_can('manage_options')) {
    $id = intval($_POST['submission_id']);
    $reason = sanitize_text_field($_POST['admin_reason']);
    if ($_POST['it_submission_action'] === 'approve') {
        $wpdb->update($wpdb->prefix.'indoor_task_submissions', [
            'status' => 'approved',
            'admin_reason' => $reason,
            'reviewed_at' => current_time('mysql')
        ], ['id' => $id]);
        // Reward points
        $sub = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}indoor_task_submissions WHERE id = %d", $id));
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d", $sub->task_id));
        $wpdb->insert($wpdb->prefix.'indoor_task_wallet', [
            'user_id' => $sub->user_id,
            'points' => $task->reward_points,
            'type' => 'reward',
            'reference_id' => $id,
            'description' => 'Task #' . $task->id . ' - ' . $task->title
        ]);
        // Trigger referral bonus
        do_action('indoor_tasks_submission_approved', $sub->user_id, $sub->task_id);
        
        // Fire email notification action
        do_action('indoor_tasks_task_status_changed', $sub->user_id, $sub->task_id, 'approved');
    }
    if ($_POST['it_submission_action'] === 'reject') {
        $wpdb->update($wpdb->prefix.'indoor_task_submissions', [
            'status' => 'rejected',
            'admin_reason' => $reason,
            'reviewed_at' => current_time('mysql')
        ], ['id' => $id]);
        
        // Record task failure for the user
        $sub = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}indoor_task_submissions WHERE id = %d", $id));
        
        // Create failures table if it doesn't exist
        indoor_tasks_create_failures_table();
        
        // Record the failure
        $wpdb->insert($wpdb->prefix.'indoor_task_failures', [
            'task_id' => $sub->task_id,
            'user_id' => $sub->user_id,
            'submission_id' => $id,
            'failed_at' => current_time('mysql'),
            'reason' => $reason
        ]);
        
        // Fire email notification action
        do_action('indoor_tasks_task_status_changed', $sub->user_id, $sub->task_id, 'rejected');
        
        // Check if user has reached max failures (3)
        $failure_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_failures 
             WHERE task_id = %d AND user_id = %d",
            $sub->task_id, $sub->user_id
        ));
        
        if ($failure_count >= 3) {
            // Create notification for user about permanent ban
            indoor_tasks_add_notification(
                $sub->user_id,
                __('Task Permanently Banned', 'indoor-tasks'),
                sprintf(__('You have been permanently banned from task "%s" after 3 failed attempts.', 'indoor-tasks'), 
                        $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}indoor_tasks WHERE id = %d", $sub->task_id))),
                'task_ban',
                $sub->task_id
            );
        }
    }
    echo '<div class="updated"><p>Submission updated.</p></div>';
}
$subs = $wpdb->get_results("
    SELECT s.*, 
           t.title, t.short_description, t.task_image, t.task_image_id, t.category, t.client_id,
           u.user_email, u.display_name,
           c.name as client_name
    FROM {$wpdb->prefix}indoor_task_submissions s 
    LEFT JOIN {$wpdb->prefix}indoor_tasks t ON s.task_id = t.id 
    LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID 
    LEFT JOIN {$wpdb->prefix}indoor_task_clients c ON t.client_id = c.id
    WHERE s.status = 'pending' 
    ORDER BY s.submitted_at ASC
");
?>
<style>
.submission-table {
    border-collapse: collapse;
    width: 100%;
}
.submission-table th,
.submission-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
    vertical-align: top;
}
.submission-table th {
    background-color: #f5f5f5;
    font-weight: 600;
}
.task-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.task-thumb {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #ddd;
}
.task-details h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
}
.task-meta {
    font-size: 12px;
    color: #666;
}
.client-badge {
    background: #e1ecf4;
    color: #0073aa;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
}
.user-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.user-name {
    font-weight: 500;
}
.user-email {
    font-size: 12px;
    color: #666;
}
.proof-content {
    max-width: 200px;
}
.proof-text {
    margin-bottom: 8px;
    font-size: 13px;
    line-height: 1.4;
}
.proof-file {
    display: inline-block;
    padding: 4px 8px;
    background: #f0f6fc;
    border: 1px solid #d1d9e0;
    border-radius: 4px;
    color: #0366d6;
    text-decoration: none;
    font-size: 12px;
}
.proof-file:hover {
    background: #e1ecf4;
}
.action-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 200px;
}
.action-form input[type="text"] {
    width: 100%;
    padding: 4px 8px;
    font-size: 12px;
}
.action-buttons {
    display: flex;
    gap: 4px;
}
.action-buttons .button {
    padding: 4px 8px;
    font-size: 12px;
    height: auto;
    line-height: 1.2;
}
</style>

<table class="widefat submission-table">
<thead>
<tr>
    <th><?php _e('User', 'indoor-tasks'); ?></th>
    <th><?php _e('Task', 'indoor-tasks'); ?></th>
    <th><?php _e('Proof', 'indoor-tasks'); ?></th>
    <th><?php _e('Submitted', 'indoor-tasks'); ?></th>
    <th><?php _e('Action', 'indoor-tasks'); ?></th>
</tr>
</thead>
<tbody>
<?php foreach($subs as $sub): 
    // Get task image
    $image_url = '';
    if (!empty($sub->task_image_id)) {
        $image_url = wp_get_attachment_url($sub->task_image_id);
    } elseif (!empty($sub->task_image)) {
        $image_url = $sub->task_image;
    }
?>
<tr>
  <td>
    <div class="user-info">
        <div class="user-name"><?= esc_html($sub->display_name ?: 'Unknown User') ?></div>
        <div class="user-email"><?= esc_html($sub->user_email) ?></div>
    </div>
  </td>
  <td>
    <div class="task-info">
        <?php if ($image_url): ?>
            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($sub->title); ?>" class="task-thumb">
        <?php else: ?>
            <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                <span class="dashicons dashicons-format-image" style="font-size: 20px; color: #666;"></span>
            </div>
        <?php endif; ?>
        <div class="task-details">
            <h4><?= esc_html($sub->title) ?></h4>
            <div class="task-meta">
                <div>ID: #<?= $sub->task_id ?></div>
                <?php if (!empty($sub->category)): ?>
                    <div>Category: <?= esc_html($sub->category) ?></div>
                <?php endif; ?>
                <?php if (!empty($sub->client_name)): ?>
                    <div>Client: <span class="client-badge"><?= esc_html($sub->client_name) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($sub->short_description)): ?>
                    <div style="margin-top: 4px; font-style: italic;"><?= esc_html(wp_trim_words($sub->short_description, 8, '...')) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </td>
  <td>
    <div class="proof-content">
        <?php if (!empty($sub->proof_text)): ?>
            <div class="proof-text"><?= esc_html($sub->proof_text) ?></div>
        <?php endif; ?>
        <?php if ($sub->proof_file): ?>
            <a href="<?= esc_url($sub->proof_file) ?>" target="_blank" class="proof-file">
                <span class="dashicons dashicons-format-image" style="font-size: 12px; vertical-align: text-top;"></span>
                View Screenshot
            </a>
        <?php endif; ?>
    </div>
  </td>
  <td>
    <div style="font-size: 13px;">
        <?= date('M j, Y', strtotime($sub->submitted_at)) ?><br>
        <span style="color: #666;"><?= date('H:i:s', strtotime($sub->submitted_at)) ?></span>
    </div>
  </td>
  <td>
    <form method="post" class="action-form">
      <input type="hidden" name="submission_id" value="<?= $sub->id ?>" />
      <input type="text" name="admin_reason" placeholder="Reason (optional)" />
      <div class="action-buttons">
        <button type="submit" name="it_submission_action" value="approve" class="button button-primary">
            <?php _e('Approve', 'indoor-tasks'); ?>
        </button>
        <button type="submit" name="it_submission_action" value="reject" class="button">
            <?php _e('Reject', 'indoor-tasks'); ?>
        </button>
      </div>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
