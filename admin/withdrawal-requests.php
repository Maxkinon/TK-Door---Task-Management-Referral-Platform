<?php
// Admin withdrawal requests page
?><div class="wrap">
<h1><?php _e('Withdrawal Requests', 'indoor-tasks'); ?></h1>
<!-- Approve/Reject payouts -->
<?php
global $wpdb;
if (isset($_POST['it_withdraw_action']) && current_user_can('manage_options')) {
    $id = intval($_POST['withdrawal_id']);
    $reason = sanitize_text_field($_POST['admin_reason']);
    $withdraw = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}indoor_task_withdrawals WHERE id = %d", $id));
    if ($_POST['it_withdraw_action'] === 'approve') {
        $wpdb->update($wpdb->prefix.'indoor_task_withdrawals', [
            'status' => 'approved',
            'admin_reason' => $reason,
            'processed_at' => current_time('mysql')
        ], ['id' => $id]);
        // Deduct points from wallet
        $wpdb->insert($wpdb->prefix.'indoor_task_wallet', [
            'user_id' => $withdraw->user_id,
            'points' => -abs($withdraw->points),
            'type' => 'withdrawal',
            'reference_id' => $id,
            'description' => 'Withdrawal approved'
        ]);
    }
    if ($_POST['it_withdraw_action'] === 'reject') {
        $wpdb->update($wpdb->prefix.'indoor_task_withdrawals', [
            'status' => 'rejected',
            'admin_reason' => $reason,
            'processed_at' => current_time('mysql')
        ], ['id' => $id]);
    }
    echo '<div class="updated"><p>Withdrawal updated.</p></div>';
}
$withdrawals = $wpdb->get_results("SELECT w.*, u.user_email FROM {$wpdb->prefix}indoor_task_withdrawals w LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID WHERE w.status = 'pending' ORDER BY w.requested_at ASC");
?>
<table class="widefat"><thead><tr><th>User</th><th>Method</th><th>Points</th><th>Amount</th><th>Requested</th><th>Action</th></tr></thead><tbody>
<?php foreach($withdrawals as $w): ?>
<tr>
  <td><?= esc_html($w->user_email) ?></td>
  <td><?= esc_html($w->method) ?></td>
  <td><?= $w->points ?></td>
  <td><?= $w->amount ?></td>
  <td><?= $w->requested_at ?></td>
  <td>
    <form method="post" style="display:inline;">
      <input type="hidden" name="withdrawal_id" value="<?= $w->id ?>" />
      <input type="text" name="admin_reason" placeholder="Reason (optional)" />
      <button type="submit" name="it_withdraw_action" value="approve" class="button button-primary">Approve</button>
      <button type="submit" name="it_withdraw_action" value="reject" class="button">Reject</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
