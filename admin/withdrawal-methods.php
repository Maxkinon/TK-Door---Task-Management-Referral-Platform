<?php
// Admin withdrawal methods management
if (isset($_POST['it_withdraw_method_action']) && current_user_can('manage_options')) {
    global $wpdb;
    $method = sanitize_text_field($_POST['method']);
    $rate = floatval($_POST['conversion_rate']);
    $min = intval($_POST['min_points']);
    $max = !empty($_POST['max_points']) ? intval($_POST['max_points']) : 0;
    $fields = isset($_POST['input_fields']) ? json_encode($_POST['input_fields']) : '';
    
    // Prepare common data for insert/update
    $data = [
        'method' => $method,
        'conversion_rate' => $rate,
        'min_points' => $min,
        'max_points' => $max,
        'custom_fields' => $fields,
        'icon_url' => esc_url_raw($_POST['icon_url'] ?? ''),
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'payout_label' => sanitize_text_field($_POST['payout_label'] ?? ''),
        'currency_symbol' => sanitize_text_field($_POST['currency_symbol'] ?? ''),
        'processing_time' => sanitize_text_field($_POST['processing_time'] ?? ''),
        'manual_approval' => isset($_POST['manual_approval']) ? 1 : 0,
        'fee' => sanitize_text_field($_POST['fee'] ?? ''),
        'enabled' => isset($_POST['enabled']) ? 1 : 0,
        'sort_order' => intval($_POST['sort_order'] ?? 0)
    ];
    
    // Make sure the table exists with all required columns
    indoor_tasks_update_database();
    $table_name = $wpdb->prefix . 'indoor_task_withdrawal_methods';
    
    if ($_POST['it_withdraw_method_action'] === 'add') {
        $wpdb->insert($table_name, $data);
        
        // Also update the options-based system for backward compatibility
        $methods = get_option('indoor_tasks_withdrawal_methods', []);
        $methods[] = [
            'name' => $method,
            'conversion' => $rate,
            'min_points' => $min,
            'input_fields' => json_decode($fields, true) ?: []
        ];
        update_option('indoor_tasks_withdrawal_methods', $methods);
        
        echo '<div class="notice notice-success"><p>Withdrawal method added successfully.</p></div>';
    }
    
    if ($_POST['it_withdraw_method_action'] === 'delete' && isset($_POST['method_id'])) {
        $method_id = intval($_POST['method_id']);
        
        // Delete from database table
        $wpdb->delete($table_name, ['id' => $method_id]);
        
        // Also remove from options
        $method_to_delete = $wpdb->get_row($wpdb->prepare(
            "SELECT method FROM {$table_name} WHERE id = %d",
            $method_id
        ));
        
        if ($method_to_delete) {
            $methods = get_option('indoor_tasks_withdrawal_methods', []);
            foreach ($methods as $key => $method) {
                if ($method['name'] === $method_to_delete->method) {
                    unset($methods[$key]);
                    break;
                }
            }
            update_option('indoor_tasks_withdrawal_methods', array_values($methods));
        }
        
        echo '<div class="notice notice-success"><p>Withdrawal method deleted successfully.</p></div>';
    }
}

// Get methods from database table
global $wpdb;
$table_name = $wpdb->prefix . 'indoor_task_withdrawal_methods';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

if ($table_exists) {
    $methods = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC");
} else {
    // Fallback to options-based methods
    $option_methods = get_option('indoor_tasks_withdrawal_methods', []);
    $methods = [];
    
    // Convert to the format expected by the admin page
    foreach ($option_methods as $index => $method) {
        $method_obj = new stdClass();
        $method_obj->id = $index + 1;
        $method_obj->method = $method['name'];
        $method_obj->conversion_rate = $method['conversion'];
        $method_obj->min_points = $method['min_points'] ?? 0;
        $method_obj->custom_fields = !empty($method['input_fields']) ? json_encode($method['input_fields']) : '';
        $method_obj->enabled = 1;
        $methods[] = $method_obj;
    }
}
?>
<div class="wrap">
<h1><?php _e('Withdrawal Methods', 'indoor-tasks'); ?></h1>
<p>Manage payment methods available to users for withdrawing their points. Configure conversion rates and required fields.</p>

<div class="add-new-method">
    <h2><?php _e('Add New Withdrawal Method', 'indoor-tasks'); ?></h2>
    <form method="post" class="method-form">
        <input type="hidden" name="it_withdraw_method_action" value="add">
        
        <div class="method-grid">
            <div class="input-group">
                <label for="method">Method Name *</label>
                <input type="text" name="method" id="method" required placeholder="PayPal, Bank Transfer, etc.">
                <p class="description">Enter a unique name for this withdrawal method</p>
            </div>
            
            <div class="input-group">
                <label for="conversion_rate">Conversion Rate *</label>
                <input type="number" step="0.01" name="conversion_rate" id="conversion_rate" required placeholder="100">
                <p class="description">Points to currency conversion rate (e.g., 100 points = 1 USD)</p>
            </div>
            
            <div class="input-group">
                <label for="currency_symbol">Currency Symbol</label>
                <input type="text" name="currency_symbol" id="currency_symbol" placeholder="$, €, ₹">
                <p class="description">Symbol for the payout currency</p>
            </div>
            
            <div class="input-group">
                <label for="payout_label">Payout Label</label>
                <input type="text" name="payout_label" id="payout_label" placeholder="USD per 100 points">
                <p class="description">Label shown to users during withdrawal</p>
            </div>
            
            <div class="input-group">
                <label for="min_points">Minimum Points *</label>
                <input type="number" name="min_points" id="min_points" required placeholder="1000">
                <p class="description">Minimum points required for withdrawal</p>
            </div>
            
            <div class="input-group">
                <label for="max_points">Maximum Points</label>
                <input type="number" name="max_points" id="max_points" placeholder="10000">
                <p class="description">Maximum points per withdrawal (0 for no limit)</p>
            </div>
            
            <div class="input-group">
                <label for="fee">Fee</label>
                <input type="text" name="fee" id="fee" placeholder="5% or 50">
                <p class="description">Withdrawal fee (percentage or flat amount)</p>
            </div>
            
            <div class="input-group">
                <label for="processing_time">Processing Time</label>
                <input type="text" name="processing_time" id="processing_time" placeholder="24-48 hours">
                <p class="description">Expected processing time for withdrawals</p>
            </div>
            
            <div class="input-group">
                <label for="icon_url">Icon URL</label>
                <input type="url" name="icon_url" id="icon_url" placeholder="https://example.com/icon.png">
                <p class="description">URL to the payment method's icon</p>
            </div>
            
            <div class="input-group" style="grid-column: 1/-1;">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="3" style="width:100%" placeholder="Explain any special requirements or notes about this withdrawal method"></textarea>
            </div>
            
            <div class="input-group">
                <label>
                    <input type="checkbox" name="manual_approval" value="1" checked>
                    Require Manual Approval
                </label>
                <p class="description">Check if withdrawals need admin review</p>
            </div>
            
            <div class="input-group">
                <label>
                    <input type="checkbox" name="enabled" value="1" checked>
                    Enable Method
                </label>
                <p class="description">Uncheck to temporarily disable this method</p>
            </div>
        </div>
        
        <div class="input-fields-section">
            <h3>Required Input Fields</h3>
            <p class="description">Add fields that users must fill when requesting a withdrawal</p>
            
            <div id="input-fields-container"></div>
            
            <div class="add-button" id="add-input-field">
                <i class="dashicons dashicons-plus-alt"></i> Add Input Field
            </div>
        </div>
        
        <p class="submit">
            <button type="submit" class="button button-primary">Add Withdrawal Method</button>
        </p>
    </form>
</div>

<style>
.method-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.method-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    color: #2271b1;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.method-card h2 .title {
    display: flex;
    align-items: center;
}
.method-card h2 i {
    margin-right: 8px;
}
.method-card h2 .actions {
    display: flex;
    gap: 10px;
}
.method-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}
.method-card .section-title {
    grid-column: 1/-1;
    margin: 15px 0 5px;
    color: #1d2327;
    font-size: 14px;
    font-weight: 600;
}
.input-group {
    position: relative;
}
.input-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}
.input-group input,
.input-group select,
.input-group textarea {
    width: 100%;
}
.input-group .description {
    font-size: 12px;
    color: #646970;
    margin-top: 5px;
}
.input-fields-container {
    border-top: 1px solid #eee;
    margin-top: 20px;
    padding-top: 15px;
}
.input-field {
    background: #f7f7f7;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 10px;
    position: relative;
}
.input-field .remove-field {
    position: absolute;
    right: 10px;
    top: 10px;
    cursor: pointer;
    color: #b32d2e;
    text-decoration: none;
}
.add-button {
    background: #f0f0f1;
    border: 1px dashed #c3c4c7;
    border-radius: 4px;
    padding: 10px;
    text-align: center;
    cursor: pointer;
    color: #2271b1;
    transition: all 0.2s;
}
.add-button:hover {
    background: #f6f7f7;
    border-color: #2271b1;
}
/* Table styles for existing methods */
.methods-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 30px;
}
.methods-table th,
.methods-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e5e5e5;
}
.methods-table th {
    background: #f0f0f1;
    font-weight: 600;
}
.methods-table tr:hover {
    background: #f6f7f7;
}
.methods-table .actions {
    display: flex;
    gap: 8px;
}
.methods-table .status-enabled {
    color: #00a32a;
}
.methods-table .status-disabled {
    color: #b32d2e;
}
    display: block;
    margin-bottom: 5px;
    color: #444;
    font-weight: 500;
}
.method-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.method-table th {
    text-align: left;
    background: #f8f8f8;
    padding: 10px;
    border-bottom: 1px solid #ddd;
}
.method-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}
.method-table tr:hover {
    background: #f9f9f9;
}
.method-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 4px;
    background: #f0f0f0;
    font-size: 12px;
}
.method-action {
    display: inline-block;
    margin-left: 5px;
}
.existing-methods {
    margin-top: 30px;
}
.method-title {
    display: flex;
    align-items: center;
}
.method-icon {
    width: 24px;
    height: 24px;
    margin-right: 8px;
}
.method-actions {
    margin-left: auto;
    font-size: 14px;
}
.method-actions a {
    color: #2271b1;
    text-decoration: none;
}
.method-actions a:hover {
    text-decoration: underline;
}
.method-details {
    margin-top: 10px;
}
.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
}
.detail-label {
    font-weight: 500;
    color: #333;
}
.detail-value {
    text-align: right;
    color: #555;
}
.status-enabled {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    background: #e3f1e7;
    color: #0a8528;
    font-size: 12px;
}
.status-disabled {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    background: #ffe9e9;
    color: #d63638;
    font-size: 12px;
}
.method-description {
    margin-top: 10px;
    padding: 10px;
    background: #f9f9f9;
    border-left: 4px solid #2271b1;
}
.method-input-fields {
    margin-top: 10px;
}
.input-fields-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}
.field-item {
    background: #f0f0f1;
    padding: 8px;
    border-radius: 4px;
    font-size: 14px;
}
</style>

<div class="method-card">
  <h2><i class="dashicons dashicons-plus-alt"></i> Add New Withdrawal Method</h2>
  <form method="post">
    <input type="hidden" name="it_withdraw_method_action" value="add" />
    
    <div class="method-grid">
      <div>
        <label>Method Name:</label>
        <input type="text" name="method" placeholder="Method Name (e.g. UPI, PayPal)" required style="width:100%;" />
      </div>
      <div>
        <label>Conversion Rate:</label>
        <input type="number" step="0.01" name="conversion_rate" placeholder="Conversion Rate (e.g. 1)" required style="width:100%;" />
      </div>
      <div>
        <label>Minimum Points:</label>
        <input type="number" name="min_points" placeholder="Min Points" required style="width:100%;" />
      </div>
      <div>
        <label>Custom Fields:</label>
        <textarea name="custom_fields" placeholder="Custom Fields (e.g. UPI ID, USDT Address)" style="width:100%;height:40px;"></textarea>
      </div>
    </div>
    
    <button type="submit" class="button button-primary">Add Method</button>
  </form>
</div>

<div class="method-card">
  <h2><i class="dashicons dashicons-list-view"></i> Available Withdrawal Methods</h2>
  
  <?php if (empty($methods)): ?>
    <p>No withdrawal methods have been added yet. Add your first method above.</p>
  <?php else: ?>
  <table class="method-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Method</th>
        <th>Conversion Rate</th>
        <th>Min Points</th>
        <th>Fields</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($methods as $m): ?>
      <tr>
        <td><?= $m->id ?></td>
        <td><strong><?= esc_html($m->method) ?></strong></td>
        <td><?= $m->conversion_rate ?></td>
        <td><?= $m->min_points ?></td>
        <td><?= esc_html($m->custom_fields) ?></td>
        <td>
          <?php if ($m->enabled): ?>
            <span class="method-badge" style="background:#e3f1e7;color:#0a8528;">Active</span>
          <?php else: ?>
            <span class="method-badge" style="background:#ffe9e9;color:#d63638;">Inactive</span>
          <?php endif; ?>
        </td>
        <td>
          <form method="post" style="display:inline;">
            <input type="hidden" name="it_withdraw_method_action" value="delete" />
            <input type="hidden" name="method_id" value="<?= $m->id ?>" />
            <button type="submit" class="button method-action" onclick="return confirm('Are you sure you want to delete this method?');">Delete</button>
          </form>
          <a href="admin.php?page=indoor-tasks-settings#withdrawal" class="button button-primary method-action">Configure</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  
  <p style="margin-top:15px;"><a href="admin.php?page=indoor-tasks-settings#withdrawal" class="button">Advanced Configuration</a></p>
</div>

<div class="method-card">
  <h2><i class="dashicons dashicons-info"></i> Withdrawal Method Help</h2>
  <p>Withdrawal methods define how users can convert their earned points into real-world value.</p>
  <ul style="list-style:disc;margin-left:20px;">
    <li><strong>Method Name:</strong> The name of the payment method (e.g., PayPal, UPI, Bank Transfer)</li>
    <li><strong>Conversion Rate:</strong> How many points convert to one unit of currency</li>
    <li><strong>Min Points:</strong> Minimum points required to request a withdrawal</li>
    <li><strong>Custom Fields:</strong> Fields users need to fill when requesting a withdrawal (comma-separated)</li>
  </ul>
  <p>For more advanced configuration including processing times, icons, and dynamic fields, visit the <a href="admin.php?page=indoor-tasks-settings#withdrawal">Withdrawal tab in Settings</a>.</p>
</div>

<?php if (!empty($methods)): ?>
    <div class="existing-methods">
        <h2><?php _e('Existing Withdrawal Methods', 'indoor-tasks'); ?></h2>
        <table class="methods-table">
            <thead>
                <tr>
                    <th>Method Name</th>
                    <th>Conversion Rate</th>
                    <th>Min Points</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($methods as $method): ?>
                    <tr>
                        <td>
                            <?php if (!empty($method->icon_url)): ?>
                                <img src="<?php echo esc_url($method->icon_url); ?>" alt="" style="height: 20px; width: auto; vertical-align: middle; margin-right: 8px;">
                            <?php endif; ?>
                            <?php echo esc_html($method->method); ?>
                        </td>
                        <td>
                            <?php 
                            echo esc_html($method->currency_symbol ?? '');
                            echo number_format($method->conversion_rate, 2);
                            echo !empty($method->payout_label) ? ' ' . esc_html($method->payout_label) : '';
                            ?>
                        </td>
                        <td><?php echo number_format($method->min_points); ?></td>
                        <td>
                            <span class="<?php echo $method->enabled ? 'status-enabled' : 'status-disabled'; ?>">
                                <?php echo $method->enabled ? 'Active' : 'Disabled'; ?>
                            </span>
                        </td>
                        <td class="actions">
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="it_withdraw_method_action" value="delete">
                                <input type="hidden" name="method_id" value="<?php echo esc_attr($method->id); ?>">
                                <button type="submit" class="button button-small button-link-delete" onclick="return confirm('Are you sure you want to delete this withdrawal method?');">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="notice notice-info">
        <p><?php _e('No withdrawal methods found. Add your first method using the form above.', 'indoor-tasks'); ?></p>
    </div>
<?php endif; ?>

</div> <!-- .wrap -->
<?php
// JavaScript for dynamic field management
?>
<script>
jQuery(document).ready(function($) {
    let inputFieldCount = 0;
    
    function addInputField(data = {}) {
        const template = `
            <div class="input-field">
                <a href="#" class="remove-field" title="Remove field">
                    <span class="dashicons dashicons-no"></span>
                </a>
                <div class="method-grid">
                    <div class="input-group">
                        <label>Field Label</label>
                        <input type="text" name="input_fields[${inputFieldCount}][label]" value="${data.label || ''}" required placeholder="Account Number, Email, etc.">
                    </div>
                    <div class="input-group">
                        <label>Field Type</label>
                        <select name="input_fields[${inputFieldCount}][type]" required>
                            <option value="text" ${data.type === 'text' ? 'selected' : ''}>Text</option>
                            <option value="email" ${data.type === 'email' ? 'selected' : ''}>Email</option>
                            <option value="number" ${data.type === 'number' ? 'selected' : ''}>Number</option>
                            <option value="textarea" ${data.type === 'textarea' ? 'selected' : ''}>Textarea</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Required?</label>
                        <select name="input_fields[${inputFieldCount}][required]">
                            <option value="1" ${data.required ? 'selected' : ''}>Yes</option>
                            <option value="0" ${!data.required ? 'selected' : ''}>No</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Placeholder</label>
                        <input type="text" name="input_fields[${inputFieldCount}][placeholder]" value="${data.placeholder || ''}" placeholder="Enter placeholder text">
                    </div>
                </div>
            </div>
        `;
        
        $('#input-fields-container').append(template);
        inputFieldCount++;
    }
    
    $('#add-input-field').on('click', function(e) {
        e.preventDefault();
        addInputField();
    });
    
    $(document).on('click', '.remove-field', function(e) {
        e.preventDefault();
        $(this).closest('.input-field').remove();
    });
    
    // Add initial field if none exists
    if ($('#input-fields-container').children().length === 0) {
        addInputField();
    }
});
</script>
