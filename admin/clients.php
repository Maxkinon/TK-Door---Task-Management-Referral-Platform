<?php
/**
 * Admin Clients Management Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle client actions
if (isset($_POST['client_action']) && current_user_can('manage_options')) {
    $client_name = sanitize_text_field($_POST['client_name']);
    $client_email = sanitize_email($_POST['client_email']);
    $client_phone = sanitize_text_field($_POST['client_phone']);
    $client_company = sanitize_text_field($_POST['client_company']);
    $client_address = sanitize_textarea_field($_POST['client_address']);
    $client_website = esc_url_raw($_POST['client_website']);
    $client_status = sanitize_text_field($_POST['client_status']);
    $client_notes = sanitize_textarea_field($_POST['client_notes']);
    
    if ($_POST['client_action'] === 'add') {
        $result = $wpdb->insert(
            $wpdb->prefix . 'indoor_task_clients',
            array(
                'name' => $client_name,
                'email' => $client_email,
                'phone' => $client_phone,
                'company' => $client_company,
                'address' => $client_address,
                'website' => $client_website,
                'status' => $client_status,
                'notes' => $client_notes,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . __('Client added successfully.', 'indoor-tasks') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Error adding client.', 'indoor-tasks') . '</p></div>';
        }
    }
    
    if ($_POST['client_action'] === 'edit' && isset($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $result = $wpdb->update(
            $wpdb->prefix . 'indoor_task_clients',
            array(
                'name' => $client_name,
                'email' => $client_email,
                'phone' => $client_phone,
                'company' => $client_company,
                'address' => $client_address,
                'website' => $client_website,
                'status' => $client_status,
                'notes' => $client_notes,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $client_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . __('Client updated successfully.', 'indoor-tasks') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Error updating client.', 'indoor-tasks') . '</p></div>';
        }
    }
}

// Handle delete client
if (isset($_GET['delete_client']) && current_user_can('manage_options')) {
    $client_id = intval($_GET['delete_client']);
    $wpdb->delete($wpdb->prefix . 'indoor_task_clients', array('id' => $client_id), array('%d'));
    echo '<div class="notice notice-success"><p>' . __('Client deleted successfully.', 'indoor-tasks') . '</p></div>';
}

// Get client for editing
$edit_client = null;
if (isset($_GET['edit_client'])) {
    $edit_client = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_clients WHERE id = %d",
        intval($_GET['edit_client'])
    ));
}

// Get all clients
$clients = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}indoor_task_clients ORDER BY created_at DESC");

// Get client statistics
$total_clients = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_clients");
$active_clients = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_clients WHERE status = 'active'");
$total_tasks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks WHERE client_id IS NOT NULL");
$total_revenue = $wpdb->get_var("SELECT SUM(budget) FROM {$wpdb->prefix}indoor_tasks WHERE client_id IS NOT NULL");

?>

<style>
.clients-page {
    padding: 20px;
}

.client-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #00954b;
    margin-bottom: 10px;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.client-form {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    min-height: 80px;
    resize: vertical;
}

.btn-primary {
    background: #00954b;
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.btn-primary:hover {
    background: #047857;
}

.clients-table {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.clients-table table {
    width: 100%;
    border-collapse: collapse;
}

.clients-table th,
.clients-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.clients-table th {
    background: #f8f9fa;
    font-weight: bold;
    color: #333;
}

.clients-table tr:hover {
    background: #f8f9fa;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-active {
    background: #dcfce7;
    color: #16a34a;
}

.status-inactive {
    background: #fecaca;
    color: #dc2626;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.btn-small {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
    display: inline-block;
}

.btn-edit {
    background: #3b82f6;
    color: white;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-edit:hover {
    background: #2563eb;
}

.btn-delete:hover {
    background: #dc2626;
}
</style>

<div class="clients-page">
    <h1><?php _e('Client Management', 'indoor-tasks'); ?></h1>
    
    <!-- Client Statistics -->
    <div class="client-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_clients); ?></div>
            <div class="stat-label"><?php _e('Total Clients', 'indoor-tasks'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($active_clients); ?></div>
            <div class="stat-label"><?php _e('Active Clients', 'indoor-tasks'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_tasks); ?></div>
            <div class="stat-label"><?php _e('Client Tasks', 'indoor-tasks'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-number">$<?php echo number_format($total_revenue, 2); ?></div>
            <div class="stat-label"><?php _e('Total Revenue', 'indoor-tasks'); ?></div>
        </div>
    </div>
    
    <!-- Add/Edit Client Form -->
    <div class="client-form">
        <h2><?php echo $edit_client ? __('Edit Client', 'indoor-tasks') : __('Add New Client', 'indoor-tasks'); ?></h2>
        
        <form method="post">
            <input type="hidden" name="client_action" value="<?php echo $edit_client ? 'edit' : 'add'; ?>">
            <?php if ($edit_client): ?>
                <input type="hidden" name="client_id" value="<?php echo $edit_client->id; ?>">
            <?php endif; ?>
            
            <div class="form-grid">
                <div class="form-group">
                    <label><?php _e('Client Name', 'indoor-tasks'); ?> *</label>
                    <input type="text" name="client_name" required value="<?php echo $edit_client ? esc_attr($edit_client->name) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label><?php _e('Email', 'indoor-tasks'); ?></label>
                    <input type="email" name="client_email" value="<?php echo $edit_client ? esc_attr($edit_client->email) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label><?php _e('Phone', 'indoor-tasks'); ?></label>
                    <input type="text" name="client_phone" value="<?php echo $edit_client ? esc_attr($edit_client->phone) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label><?php _e('Company', 'indoor-tasks'); ?></label>
                    <input type="text" name="client_company" value="<?php echo $edit_client ? esc_attr($edit_client->company) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label><?php _e('Website', 'indoor-tasks'); ?></label>
                    <input type="url" name="client_website" value="<?php echo $edit_client ? esc_attr($edit_client->website) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label><?php _e('Status', 'indoor-tasks'); ?></label>
                    <select name="client_status" required>
                        <option value="active" <?php echo ($edit_client && $edit_client->status === 'active') ? 'selected' : ''; ?>><?php _e('Active', 'indoor-tasks'); ?></option>
                        <option value="inactive" <?php echo ($edit_client && $edit_client->status === 'inactive') ? 'selected' : ''; ?>><?php _e('Inactive', 'indoor-tasks'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label><?php _e('Address', 'indoor-tasks'); ?></label>
                    <textarea name="client_address"><?php echo $edit_client ? esc_textarea($edit_client->address) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label><?php _e('Notes', 'indoor-tasks'); ?></label>
                    <textarea name="client_notes"><?php echo $edit_client ? esc_textarea($edit_client->notes) : ''; ?></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn-primary">
                <?php echo $edit_client ? __('Update Client', 'indoor-tasks') : __('Add Client', 'indoor-tasks'); ?>
            </button>
            
            <?php if ($edit_client): ?>
                <a href="admin.php?page=indoor-tasks-clients" class="btn-primary" style="background: #6b7280; margin-left: 10px; text-decoration: none;"><?php _e('Cancel', 'indoor-tasks'); ?></a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Clients List -->
    <div class="clients-table">
        <h2 style="padding: 20px; margin: 0; background: #f8f9fa; border-bottom: 1px solid #eee;"><?php _e('All Clients', 'indoor-tasks'); ?></h2>
        
        <?php if (!empty($clients)): ?>
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Name', 'indoor-tasks'); ?></th>
                        <th><?php _e('Company', 'indoor-tasks'); ?></th>
                        <th><?php _e('Email', 'indoor-tasks'); ?></th>
                        <th><?php _e('Phone', 'indoor-tasks'); ?></th>
                        <th><?php _e('Status', 'indoor-tasks'); ?></th>
                        <th><?php _e('Created', 'indoor-tasks'); ?></th>
                        <th><?php _e('Actions', 'indoor-tasks'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><strong><?php echo esc_html($client->name); ?></strong></td>
                            <td><?php echo esc_html($client->company); ?></td>
                            <td><?php echo esc_html($client->email); ?></td>
                            <td><?php echo esc_html($client->phone); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($client->status); ?>">
                                    <?php echo ucfirst($client->status); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($client->created_at)); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="admin.php?page=indoor-tasks-clients&edit_client=<?php echo $client->id; ?>" class="btn-small btn-edit"><?php _e('Edit', 'indoor-tasks'); ?></a>
                                    <a href="admin.php?page=indoor-tasks-clients&delete_client=<?php echo $client->id; ?>" class="btn-small btn-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this client?', 'indoor-tasks'); ?>')"><?php _e('Delete', 'indoor-tasks'); ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="padding: 40px; text-align: center; color: #666;">
                <p><?php _e('No clients found. Add your first client above.', 'indoor-tasks'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
