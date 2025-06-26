<?php
// Tasks List with filters (admin)
global $wpdb;
$category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$where = [];
if ($category) $where[] = $wpdb->prepare('t.category = %s', $category);
if ($status) $where[] = $wpdb->prepare('t.status = %s', $status);
if ($client_id) $where[] = $wpdb->prepare('t.client_id = %d', $client_id);
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Check if category column exists in the table
$columns = $wpdb->get_col("DESCRIBE {$wpdb->prefix}indoor_tasks");
$has_category_column = in_array('category', $columns);
$has_client_column = in_array('client_id', $columns);

// Enhanced query to include client information
$task_query = "SELECT t.*, c.name as client_name 
               FROM {$wpdb->prefix}indoor_tasks t";

if ($has_client_column) {
    $task_query .= " LEFT JOIN {$wpdb->prefix}indoor_task_clients c ON t.client_id = c.id";
}

$task_query .= " $where_sql ORDER BY t.id DESC";
$tasks = $wpdb->get_results($task_query);

// Get categories from the categories table first, if it exists
$categories = [];
if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_categories'") == $wpdb->prefix.'indoor_task_categories') {
    $categories = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}indoor_task_categories WHERE name != ''");
} 

// If no categories found or if the category column exists in tasks table, get them from there as well
if (empty($categories) && $has_category_column) {
    $categories = $wpdb->get_col("SELECT DISTINCT category FROM {$wpdb->prefix}indoor_tasks WHERE category != ''");
}

// Get clients for filtering
$clients = [];
if ($has_client_column) {
    $clients = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}indoor_task_clients ORDER BY name ASC");
}
?>
<div class="wrap">
<h1>Tasks List</h1>
<form method="get" style="margin-bottom:20px;">
  <input type="hidden" name="page" value="indoor-tasks-tasks-list" />
  <div class="task-filters">
    <div class="filter-row">
      <div class="filter-group">
        <label for="category-filter"><?php _e('Category', 'indoor-tasks'); ?></label>
        <select name="category" id="category-filter">
          <option value=""><?php _e('All Categories', 'indoor-tasks'); ?></option>
          <?php foreach($categories as $cat): ?>
          <option value="<?php echo esc_attr($cat); ?>" <?php echo $category == $cat ? 'selected' : ''; ?>>
            <?php echo esc_html($cat); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="filter-group">
        <label for="status-filter"><?php _e('Status', 'indoor-tasks'); ?></label>
        <select name="status" id="status-filter">
          <option value=""><?php _e('All Status', 'indoor-tasks'); ?></option>
          <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>><?php _e('Active', 'indoor-tasks'); ?></option>
          <option value="expired" <?php echo $status == 'expired' ? 'selected' : ''; ?>><?php _e('Expired', 'indoor-tasks'); ?></option>
        </select>
      </div>
      
      <?php if ($has_client_column && !empty($clients)): ?>
      <div class="filter-group">
        <label for="client-filter"><?php _e('Client', 'indoor-tasks'); ?></label>
        <select name="client_id" id="client-filter">
          <option value=""><?php _e('All Clients', 'indoor-tasks'); ?></option>
          <?php foreach($clients as $client): ?>
          <option value="<?php echo esc_attr($client->id); ?>" <?php echo $client_id == $client->id ? 'selected' : ''; ?>>
            <?php echo esc_html($client->name); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      
      <div class="filter-actions">
        <button type="submit" class="button button-primary"><?php _e('Filter Tasks', 'indoor-tasks'); ?></button>
        <?php if ($category || $status || $client_id): ?>
          <a href="?page=indoor-tasks-tasks-list" class="button"><?php _e('Reset', 'indoor-tasks'); ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>
<style>
.task-list-table {
    border-collapse: collapse;
    width: 100%;
}
.task-list-table th,
.task-list-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}
.task-list-table th {
    background-color: #f5f5f5;
    font-weight: 600;
}
.task-image-thumb {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #ddd;
}
.client-badge {
    background: #e1ecf4;
    color: #0073aa;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}
.status-badge {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
}
.status-active {
    background: #d4edda;
    color: #155724;
}
.status-expired {
    background: #f8d7da;
    color: #721c24;
}
</style>

<table class="widefat task-list-table">
<thead>
<tr>
    <th>ID</th>
    <th><?php _e('Image', 'indoor-tasks'); ?></th>
    <th><?php _e('Title', 'indoor-tasks'); ?></th>
    <th><?php _e('Category', 'indoor-tasks'); ?></th>
    <?php if ($has_client_column): ?>
    <th><?php _e('Client', 'indoor-tasks'); ?></th>
    <?php endif; ?>
    <th><?php _e('Points', 'indoor-tasks'); ?></th>
    <th><?php _e('Budget', 'indoor-tasks'); ?></th>
    <th><?php _e('Profit', 'indoor-tasks'); ?></th>
    <th><?php _e('Deadline', 'indoor-tasks'); ?></th>
    <th><?php _e('Status', 'indoor-tasks'); ?></th>
    <th><?php _e('Actions', 'indoor-tasks'); ?></th>
</tr>
</thead>
<tbody>
<?php foreach($tasks as $task): 
    $budget = isset($task->budget) ? $task->budget : 0;
    $max_users = isset($task->max_users) ? $task->max_users : 1;
    $profit = $budget - ($task->reward_points * $max_users);
    
    // Get task image
    $image_url = '';
    if (!empty($task->task_image_id)) {
        $image_url = wp_get_attachment_url($task->task_image_id);
    } elseif (!empty($task->task_image)) {
        $image_url = $task->task_image;
    }
    
    $is_active = strtotime($task->deadline) > time();
?>
<tr>
  <td><?= $task->id ?></td>
  <td>
    <?php if ($image_url): ?>
        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($task->title); ?>" class="task-image-thumb">
    <?php else: ?>
        <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #666;">
            <span class="dashicons dashicons-format-image" style="font-size: 16px;"></span>
        </div>
    <?php endif; ?>
  </td>
  <td>
    <strong><?= esc_html($task->title) ?></strong>
    <?php if (!empty($task->short_description)): ?>
        <br><small style="color: #666;"><?= esc_html(wp_trim_words($task->short_description, 10, '...')); ?></small>
    <?php endif; ?>
  </td>
  <td><?= esc_html($task->category) ?></td>
  <?php if ($has_client_column): ?>
  <td>
    <?php if (!empty($task->client_name)): ?>
        <span class="client-badge"><?= esc_html($task->client_name) ?></span>
    <?php else: ?>
        <span style="color: #999;">No Client</span>
    <?php endif; ?>
  </td>
  <?php endif; ?>
  <td><?= $task->reward_points ?></td>
  <td style="color: #2271b1; font-weight: 500;">₹<?= number_format($budget, 2) ?></td>
  <td style="color: <?= $profit >= 0 ? '#00a32a' : '#d63638' ?>; font-weight: 600;">
    ₹<?= number_format($profit, 2) ?>
    <?php if ($profit < 0): ?><span style="font-size: 12px;">⚠️</span><?php endif; ?>
  </td>
  <td><?= esc_html($task->deadline) ?></td>
  <td>
    <span class="status-badge <?= $is_active ? 'status-active' : 'status-expired' ?>">
        <?= $is_active ? 'Active' : 'Expired' ?>
    </span>
  </td>
  <td>
    <a href="?page=indoor-tasks-add-task&edit=<?= $task->id ?>" class="button button-small"><?php _e('Edit', 'indoor-tasks'); ?></a>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
