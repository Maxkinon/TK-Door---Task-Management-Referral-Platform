<?php
// Admin manage KYC page
?>
<style>
.kyc-status {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}
.kyc-status-pending {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}
.kyc-status-approved {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}
.kyc-status-rejected {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.nav-tab-wrapper {
    border-bottom: 1px solid #ccd0d4;
}
.widefat th, .widefat td {
    padding: 12px;
}
</style>
<div class="wrap">
<h1><?php _e('Manage KYC', 'indoor-tasks'); ?></h1>
<!-- KYC documents & status updates -->
<?php
global $wpdb;
if (isset($_POST['it_kyc_action']) && current_user_can('manage_options')) {
    $id = intval($_POST['kyc_id']);
    $reason = sanitize_text_field($_POST['admin_reason']);
    
    // Get the KYC record to find the user ID
    $kyc_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_kyc WHERE id = %d", 
        $id
    ));
    
    if ($kyc_record) {
        $user_id = $kyc_record->user_id;
        $updated = false;
        $new_status = '';
        
        if ($_POST['it_kyc_action'] === 'approve') {
            $updated = $wpdb->update($wpdb->prefix.'indoor_task_kyc', [
                'status' => 'approved',
                'admin_reason' => $reason,
                'reviewed_at' => current_time('mysql')
            ], ['id' => $id]);
            $new_status = 'approved';
        }
        
        if ($_POST['it_kyc_action'] === 'reject') {
            $updated = $wpdb->update($wpdb->prefix.'indoor_task_kyc', [
                'status' => 'rejected',
                'admin_reason' => $reason,
                'reviewed_at' => current_time('mysql')
            ], ['id' => $id]);
            $new_status = 'rejected';
        }
        
        if ($updated !== false && $new_status) {
            // Update user meta for dashboard display
            update_user_meta($user_id, 'indoor_tasks_kyc_status', $new_status);
            
            // Update other verification meta keys for compatibility
            if ($new_status === 'approved') {
                update_user_meta($user_id, 'indoor_tasks_verified', 'yes');
                update_user_meta($user_id, 'account_verified', 'yes');
                update_user_meta($user_id, 'user_verified', 'yes');
            } else {
                update_user_meta($user_id, 'indoor_tasks_verified', 'no');
                update_user_meta($user_id, 'account_verified', 'no');
                update_user_meta($user_id, 'user_verified', 'no');
            }
            
            // Fire the action hook for notifications and logging
            do_action('indoor_tasks_kyc_status_changed', $user_id, $new_status);
            
            echo '<div class="updated"><p>KYC ' . ucfirst($new_status) . ' successfully. User verification status updated.</p></div>';
        } else {
            echo '<div class="error"><p>Failed to update KYC status. Please try again.</p></div>';
        }
    } else {
        echo '<div class="error"><p>KYC record not found.</p></div>';
    }
}
// Handle sync action for existing records
if (isset($_GET['action']) && $_GET['action'] === 'sync_kyc_meta' && current_user_can('manage_options')) {
    // Sync all approved/rejected KYC records with user meta
    $kyc_records = $wpdb->get_results("
        SELECT id, user_id, status 
        FROM {$wpdb->prefix}indoor_task_kyc 
        WHERE status IN ('approved', 'rejected')
    ");
    
    $synced = 0;
    foreach ($kyc_records as $record) {
        update_user_meta($record->user_id, 'indoor_tasks_kyc_status', $record->status);
        
        if ($record->status === 'approved') {
            update_user_meta($record->user_id, 'indoor_tasks_verified', 'yes');
            update_user_meta($record->user_id, 'account_verified', 'yes');
            update_user_meta($record->user_id, 'user_verified', 'yes');
        } else {
            update_user_meta($record->user_id, 'indoor_tasks_verified', 'no');
            update_user_meta($record->user_id, 'account_verified', 'no');
            update_user_meta($record->user_id, 'user_verified', 'no');
        }
        $synced++;
    }
    
    echo '<div class="updated"><p>' . sprintf(__('Successfully synced %d KYC records with user meta data.', 'indoor-tasks'), $synced) . '</p></div>';
}

// Get KYC records - show all statuses with filter option
$status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'pending';
$where_clause = '';
if ($status_filter !== 'all') {
    $where_clause = $wpdb->prepare("WHERE k.status = %s", $status_filter);
}

$kycs = $wpdb->get_results("
    SELECT k.*, u.user_email, u.display_name 
    FROM {$wpdb->prefix}indoor_task_kyc k 
    LEFT JOIN {$wpdb->users} u ON k.user_id = u.ID 
    {$where_clause}
    ORDER BY k.submitted_at DESC
");

// Count by status for filter tabs
$status_counts = $wpdb->get_results("
    SELECT status, COUNT(*) as count 
    FROM {$wpdb->prefix}indoor_task_kyc 
    GROUP BY status
", OBJECT_K);
?>

<!-- Status Filter Tabs -->
<div class="nav-tab-wrapper" style="margin-bottom: 20px;">
    <a href="?page=indoor-tasks-manage-kyc&status_filter=pending" class="nav-tab <?php echo $status_filter === 'pending' ? 'nav-tab-active' : ''; ?>">
        Pending <?php echo isset($status_counts['pending']) ? '(' . $status_counts['pending']->count . ')' : '(0)'; ?>
    </a>
    <a href="?page=indoor-tasks-manage-kyc&status_filter=approved" class="nav-tab <?php echo $status_filter === 'approved' ? 'nav-tab-active' : ''; ?>">
        Approved <?php echo isset($status_counts['approved']) ? '(' . $status_counts['approved']->count . ')' : '(0)'; ?>
    </a>
    <a href="?page=indoor-tasks-manage-kyc&status_filter=rejected" class="nav-tab <?php echo $status_filter === 'rejected' ? 'nav-tab-active' : ''; ?>">
        Rejected <?php echo isset($status_counts['rejected']) ? '(' . $status_counts['rejected']->count . ')' : '(0)'; ?>
    </a>
    <a href="?page=indoor-tasks-manage-kyc&status_filter=all" class="nav-tab <?php echo $status_filter === 'all' ? 'nav-tab-active' : ''; ?>">
        All Records
    </a>
</div>

<!-- Sync Button -->
<div style="margin-bottom: 15px;">
    <a href="?page=indoor-tasks-manage-kyc&action=sync_kyc_meta&status_filter=<?php echo $status_filter; ?>" 
       class="button button-secondary" 
       onclick="return confirm('This will sync all existing KYC records with user meta data. Continue?')">
        🔄 Sync KYC Meta Data
    </a>
    <span style="color: #666; margin-left: 10px; font-size: 12px;">
        Use this if dashboard is not showing correct verification status
    </span>
</div>

<?php if (empty($kycs)): ?>
    <div class="notice notice-info">
        <p><?php _e('No KYC records found for the selected filter.', 'indoor-tasks'); ?></p>
    </div>
<?php else: ?>
<table class="widefat"><thead><tr><th>User</th><th>Email</th><th>Document</th><th>Status</th><th>Submitted</th><th>Reviewed</th><th>Action</th></tr></thead><tbody>
<?php foreach($kycs as $k): ?>
<tr>
  <td><?= esc_html($k->display_name ?: 'N/A') ?></td>
  <td><?= esc_html($k->user_email) ?></td>
  <td>
    <?php if ($k->document): ?>
      <?php 
        // Construct proper document URL
        $document_url = $k->document;
        if (!filter_var($document_url, FILTER_VALIDATE_URL)) {
          // If not a full URL, construct it from upload directory
          $upload_dir = wp_upload_dir();
          if (strpos($document_url, 'http') !== 0) {
            $document_url = $upload_dir['baseurl'] . '/' . ltrim($document_url, '/');
          }
        }
      ?>
      <a href="<?= esc_url($document_url) ?>" target="_blank" class="button button-small">
        📄 View Document
      </a>
    <?php else: ?>
      <span style="color: #999;">No document</span>
    <?php endif; ?>
  </td>
  <td>
    <span class="kyc-status kyc-status-<?= esc_attr($k->status) ?>">
      <?= ucfirst(esc_html($k->status)) ?>
    </span>
  </td>
  <td><?= date('M j, Y g:i A', strtotime($k->submitted_at)) ?></td>
  <td><?= $k->reviewed_at ? date('M j, Y g:i A', strtotime($k->reviewed_at)) : 'Not reviewed' ?></td>
  <td>
    <?php if ($k->status === 'pending'): ?>
      <form method="post" style="display:inline;">
        <input type="hidden" name="kyc_id" value="<?= $k->id ?>" />
        <input type="text" name="admin_reason" placeholder="Reason (optional)" style="width: 150px;" />
        <button type="submit" name="it_kyc_action" value="approve" class="button button-primary" onclick="return confirm('Are you sure you want to approve this KYC?')">Approve</button>
        <button type="submit" name="it_kyc_action" value="reject" class="button" onclick="return confirm('Are you sure you want to reject this KYC?')">Reject</button>
      </form>
    <?php else: ?>
      <span style="color: #666;">
        <?= esc_html($k->admin_reason ?: 'No reason provided') ?>
      </span>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div>
