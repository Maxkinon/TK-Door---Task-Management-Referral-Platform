<?php
// Admin manage tasks page
global $wpdb;

// Get task stats
$total_tasks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks");
$total_rewards = $wpdb->get_var("SELECT SUM(reward_points) FROM {$wpdb->prefix}indoor_tasks");
$avg_reward = $wpdb->get_var("SELECT AVG(reward_points) FROM {$wpdb->prefix}indoor_tasks");
$total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions");
$completion_rate = $wpdb->get_var("SELECT 
    (SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE status = 'approved') / 
    (SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions) * 100
");
$completion_rate = $completion_rate ? round($completion_rate, 1) : 0;

// Check if category column exists before querying it
$category_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}indoor_tasks LIKE 'category'");
if ($category_exists) {
    $task_categories = $wpdb->get_results("SELECT category, COUNT(*) as count FROM {$wpdb->prefix}indoor_tasks GROUP BY category ORDER BY count DESC");
} else {
    $task_categories = []; // Empty array if category column doesn't exist yet
}

// Handle add/edit/delete
if (isset($_POST['it_task_action']) && current_user_can('manage_options')) {
    $title = sanitize_text_field($_POST['title']);
    $desc = sanitize_textarea_field($_POST['description']);
    $points = intval($_POST['reward_points']);
    $deadline = sanitize_text_field($_POST['deadline']);
    $max_users = intval($_POST['max_users']);
    $budget = floatval($_POST['budget']); // Add budget field
    
    if ($_POST['it_task_action'] === 'add') {
        $category = sanitize_text_field($_POST['category']);
        $client_id = intval($_POST['client_id']) ?: null;
        $how_to = sanitize_textarea_field($_POST['how_to']);
        $task_link = esc_url_raw($_POST['task_link']);
        $guide_link = esc_url_raw($_POST['guide_link']);
        $duration = intval($_POST['duration']);
        $special_message = sanitize_text_field($_POST['special_message']);
        $difficulty_level = sanitize_text_field($_POST['difficulty_level']);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $notification_enabled = isset($_POST['send_notification']) ? 1 : 0;
        $telegram_notification = isset($_POST['send_telegram']) ? 1 : 0;
        $short_description = sanitize_textarea_field($_POST['short_description']);
        $task_image_id = intval($_POST['task_image_id']) ?: null;
        $img_url = '';
        
        // If we have an image ID, get the URL
        if ($task_image_id) {
            $img_url = wp_get_attachment_url($task_image_id);
        }
        
        // Process new fields
        $target_countries = isset($_POST['target_countries']) ? json_encode($_POST['target_countries']) : '';
        $video_link = esc_url_raw($_POST['video_link']);
        
        // Process step-by-step guide
        $step_by_step_guide = [];
        if (isset($_POST['step_descriptions']) && is_array($_POST['step_descriptions'])) {
            foreach ($_POST['step_descriptions'] as $index => $description) {
                if (!empty(trim($description))) {
                    $step_image = '';
                    if (!empty($_FILES['step_images']['tmp_name'][$index])) {
                        require_once(ABSPATH . 'wp-admin/includes/file.php');
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        
                        // Create a temporary $_FILES array for this specific file
                        $step_file = [
                            'name' => $_FILES['step_images']['name'][$index],
                            'type' => $_FILES['step_images']['type'][$index],
                            'tmp_name' => $_FILES['step_images']['tmp_name'][$index],
                            'error' => $_FILES['step_images']['error'][$index],
                            'size' => $_FILES['step_images']['size'][$index]
                        ];
                        
                        $uploaded = wp_handle_upload($step_file, ['test_form' => false]);
                        if (!empty($uploaded['url'])) {
                            $step_image = $uploaded['url'];
                        }
                    }
                    
                    $step_by_step_guide[] = [
                        'description' => sanitize_textarea_field($description),
                        'image' => $step_image
                    ];
                }
            }
        }
        $step_by_step_json = json_encode($step_by_step_guide);
        
        $wpdb->insert($wpdb->prefix.'indoor_tasks', [
            'title' => $title,
            'description' => $desc,
            'short_description' => $short_description,
            'reward_points' => $points,
            'deadline' => $deadline,
            'max_users' => $max_users,
            'created_by' => get_current_user_id(),
            'category' => $category,
            'client_id' => $client_id,
            'how_to' => $how_to,
            'task_link' => $task_link,
            'guide_link' => $guide_link,
            'duration' => $duration,
            'special_message' => $special_message,
            'task_image' => $img_url,
            'task_image_id' => $task_image_id,
            'difficulty_level' => $difficulty_level,
            'featured' => $featured,
            'target_countries' => $target_countries,
            'step_by_step_guide' => $step_by_step_json,
            'video_link' => $video_link,
            'budget' => $budget
        ]);
        
        // Send notification if enabled
        if ($notification_enabled) {
            // Schedule notification to be sent - will be implemented in notifications system
            update_option('indoor_tasks_last_notification_task_id', $wpdb->insert_id);
            update_option('indoor_tasks_last_notification_task_title', $title);
            
            do_action('indoor_tasks_new_task_notification', $wpdb->insert_id, $title);
        }
        
        // Send Telegram notification if enabled
        if ($telegram_notification) {
            do_action('indoor_tasks_telegram_notification', $wpdb->insert_id, $title);
        }
        
        echo '<div class="notice notice-success"><p>' . __('Task added successfully.', 'indoor-tasks') . '</p></div>';
    }
    
    if ($_POST['it_task_action'] === 'edit' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        $category = sanitize_text_field($_POST['category']);
        $client_id = intval($_POST['client_id']) ?: null;
        $how_to = sanitize_textarea_field($_POST['how_to']);
        $task_link = esc_url_raw($_POST['task_link']);
        $guide_link = esc_url_raw($_POST['guide_link']);
        $duration = intval($_POST['duration']);
        $special_message = sanitize_text_field($_POST['special_message']);
        $difficulty_level = sanitize_text_field($_POST['difficulty_level']);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $short_description = sanitize_textarea_field($_POST['short_description']);
        $task_image_id = intval($_POST['task_image_id']) ?: null;
        
        // Process new fields for edit
        $target_countries = isset($_POST['target_countries']) ? json_encode($_POST['target_countries']) : '';
        $video_link = esc_url_raw($_POST['video_link']);
        
        // Process step-by-step guide for edit
        $step_by_step_guide = [];
        if (isset($_POST['step_descriptions']) && is_array($_POST['step_descriptions'])) {
            foreach ($_POST['step_descriptions'] as $index => $description) {
                if (!empty(trim($description))) {
                    $step_image = '';
                    
                    // Check if there's an existing image
                    if (isset($_POST['existing_step_images'][$index]) && !empty($_POST['existing_step_images'][$index])) {
                        $step_image = $_POST['existing_step_images'][$index];
                    }
                    
                    // Check if new image is uploaded
                    if (!empty($_FILES['step_images']['tmp_name'][$index])) {
                        require_once(ABSPATH . 'wp-admin/includes/file.php');
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        
                        $step_file = [
                            'name' => $_FILES['step_images']['name'][$index],
                            'type' => $_FILES['step_images']['type'][$index],
                            'tmp_name' => $_FILES['step_images']['tmp_name'][$index],
                            'error' => $_FILES['step_images']['error'][$index],
                            'size' => $_FILES['step_images']['size'][$index]
                        ];
                        
                        $uploaded = wp_handle_upload($step_file, ['test_form' => false]);
                        if (!empty($uploaded['url'])) {
                            $step_image = $uploaded['url'];
                        }
                    }
                    
                    $step_by_step_guide[] = [
                        'description' => sanitize_textarea_field($description),
                        'image' => $step_image
                    ];
                }
            }
        }
        $step_by_step_json = json_encode($step_by_step_guide);
        
        // Handle image update for edit
        $img_url = '';
        if ($task_image_id) {
            $img_url = wp_get_attachment_url($task_image_id);
        }
        
        $update_data = [
            'title' => $title,
            'description' => $desc,
            'short_description' => $short_description,
            'reward_points' => $points,
            'deadline' => $deadline,
            'max_users' => $max_users,
            'category' => $category,
            'client_id' => $client_id,
            'how_to' => $how_to,
            'task_link' => $task_link,
            'guide_link' => $guide_link,
            'duration' => $duration,
            'special_message' => $special_message,
            'difficulty_level' => $difficulty_level,
            'featured' => $featured,
            'target_countries' => $target_countries,
            'step_by_step_guide' => $step_by_step_json,
            'video_link' => $video_link,
            'budget' => $budget,
            'task_image_id' => $task_image_id
        ];
        
        // Update image URL if we have an image ID
        if ($img_url) {
            $update_data['task_image'] = $img_url;
        }
        if (!empty($_FILES['task_image']['tmp_name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $uploaded = wp_handle_upload($_FILES['task_image'], ['test_form'=>false]);
            if (!empty($uploaded['url'])) {
                $update_data['task_image'] = $uploaded['url'];
            }
        }
        
        $wpdb->update(
            $wpdb->prefix.'indoor_tasks', 
            $update_data,
            ['id' => $task_id]
        );
        
        echo '<div class="notice notice-success"><p>' . __('Task updated successfully.', 'indoor-tasks') . '</p></div>';
    }
    
    if ($_POST['it_task_action'] === 'delete' && isset($_POST['task_id'])) {
        $wpdb->delete($wpdb->prefix.'indoor_tasks', ['id' => intval($_POST['task_id'])]);
        echo '<div class="notice notice-success"><p>' . __('Task deleted successfully.', 'indoor-tasks') . '</p></div>';
    }
}

// Get task to edit if ID provided
$edit_task = null;
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $edit_task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d",
        intval($_GET['edit'])
    ));
}

// Fetch tasks with pagination
$items_per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Filter tasks if filter parameters are provided
$where_clause = "1=1";
$filter_category = isset($_GET['filter_category']) ? sanitize_text_field($_GET['filter_category']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';

if (!empty($filter_category)) {
    $where_clause .= $wpdb->prepare(" AND category = %s", $filter_category);
}

if ($filter_status === 'active') {
    $where_clause .= " AND deadline >= CURDATE()";
} elseif ($filter_status === 'expired') {
    $where_clause .= " AND deadline < CURDATE()";
} elseif ($filter_status === 'featured') {
    $where_clause .= " AND featured = 1";
}

$total_filtered_tasks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks WHERE $where_clause");
$total_pages = ceil($total_filtered_tasks / $items_per_page);

$tasks = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE $where_clause ORDER BY id DESC LIMIT %d OFFSET %d",
    $items_per_page,
    $offset
));
?>

<div class="wrap">
<h1><?php _e('Manage Tasks', 'indoor-tasks'); ?></h1>

<style>
.task-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    grid-gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
.stat-card h3 {
    margin-top: 0;
    color: #555;
    font-size: 16px;
}
.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #2271b1;
    margin: 10px 0;
}
.stat-card.primary { border-top: 3px solid #2271b1; }
.stat-card.success { border-top: 3px solid #46b450; }
.stat-card.warning { border-top: 3px solid #ffb900; }
.stat-card.info { border-top: 3px solid #00a0d2; }

.task-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 24px;
    margin-bottom: 25px;
}
.task-card h2 {
    margin-top: 0;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
    color: #2271b1;
    display: flex;
    align-items: center;
}
.task-card h2 i {
    margin-right: 8px;
}
.it-section {
    background: #f8f8f8;
    padding: 20px 24px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.it-section h2 {
    margin-top: 0;
    color: #333;
}
.it-fields-row {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}
.it-fields-row > div {
    flex: 1;
    min-width: 180px;
}
.it-fields-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}
.task-table {
    width: 100%;
    border-collapse: collapse;
}
.task-table th, .task-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.task-table th {
    background: #f9f9f9;
    font-weight: 600;
}
.task-table tr:hover {
    background: #f9f9f9;
}
.task-filter {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 15px;
    align-items: flex-end;
}
.task-filter > div {
    min-width: 150px;
}
.task-filter label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}
.pagination {
    margin-top: 20px;
    text-align: center;
}
.pagination a, .pagination span {
    display: inline-block;
    padding: 5px 10px;
    margin-right: 5px;
    border: 1px solid #ddd;
    border-radius: 3px;
    text-decoration: none;
}
.pagination span.current {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}
.task-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}
.badge-active { background: #e3f1e7; color: #0a8528; }
.badge-expired { background: #ffe9e9; color: #d63638; }
.badge-featured { background: #fff8e5; color: #b97e00; }

.category-select-wrapper {
    position: relative;
    display: inline-block;
    width: 100%;
}

.category-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    color: #333;
    background: #fff;
    appearance: none;
    -moz-appearance: none;
    -webkit-appearance: none;
    transition: border-color 0.3s ease;
}

.category-select:focus {
    border-color: #2271b1;
    outline: none;
}

.category-notice {
    color: #d63638;
    font-size: 12px;
    margin-top: 5px;
}

.add-category-link {
    display: inline-block;
    margin-top: 8px;
    font-size: 14px;
    color: #2271b1;
    text-decoration: none;
    transition: color 0.3s ease;
}

.add-category-link:hover {
    color: #125a8c;
}

.add-category-link .dashicons {
    margin-right: 5px;
}

/* Country Selection Styles */
.countries-selection-container {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 15px;
}

.countries-list {
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    background: #fafafa;
}

.country-item {
    border-bottom: 1px solid #f0f0f0;
}

.country-item:last-child {
    border-bottom: none;
}

.country-item label {
    margin: 0 !important;
    font-weight: normal !important;
}

.country-item input[type="checkbox"] {
    accent-color: #2271b1;
}

#country-search {
    border: 1px solid #ddd;
    font-size: 14px;
}

#country-search:focus {
    border-color: #2271b1;
    outline: none;
    box-shadow: 0 0 0 1px #2271b1;
}

#selected-count {
    font-style: italic;
}

/* Step-by-step Guide Styles */
.step-item {
    position: relative;
    transition: all 0.3s ease;
}

.step-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.step-item .remove-step {
    background: #fff;
    border: 1px solid #d63638;
    color: #d63638;
    transition: all 0.2s ease;
}

.step-item .remove-step:hover {
    background: #d63638;
    color: #fff;
}

.step-item textarea {
    resize: vertical;
    min-height: 80px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
    font-size: 14px;
    line-height: 1.4;
    padding: 8px 12px;
}

.step-item textarea:focus {
    border-color: #2271b1;
    outline: none;
    box-shadow: 0 0 0 1px #2271b1;
}

.step-item input[type="file"] {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 6px;
    background: #fff;
}

.step-item .image-preview img {
    transition: transform 0.2s ease;
}

.step-item .image-preview img:hover {
    transform: scale(1.05);
}

#add-step {
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    color: #2c3338;
    transition: all 0.2s ease;
}

#add-step:hover {
    background: #2271b1;
    border-color: #2271b1;
    color: #fff;
}

#add-step .dashicons {
    vertical-align: middle;
}
</style>

<!-- Summary Stats -->
<div class="task-stats-grid">
    <div class="stat-card primary">
        <h3><?php _e('Total Tasks', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo $total_tasks; ?></div>
    </div>
    <div class="stat-card success">
        <h3><?php _e('Total Rewards', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo !empty($total_rewards) ? number_format($total_rewards) : 0; ?></div>
    </div>
    <div class="stat-card info">
        <h3><?php _e('Avg. Reward', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo !empty($avg_reward) ? number_format((float)$avg_reward) : 0; ?></div>
    </div>
    <div class="stat-card warning">
        <h3><?php _e('Completion Rate', 'indoor-tasks'); ?></h3>
        <div class="stat-number"><?php echo !empty($completion_rate) ? $completion_rate : 0; ?>%</div>
    </div>
</div>

<div class="task-flex-row" style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
    <div class="task-card" style="flex: 1; min-width: 300px;">
        <h2><i class="dashicons dashicons-category"></i> <?php _e('Task Categories', 'indoor-tasks'); ?></h2>
        <div id="category-chart" style="height: 250px;"></div>
    </div>
    
    <div class="task-card" style="flex: 1; min-width: 300px;">
        <h2><i class="dashicons dashicons-chart-line"></i> <?php _e('Task Submissions', 'indoor-tasks'); ?></h2>
        <?php
        $submission_stats = $wpdb->get_results("
            SELECT status, COUNT(*) as count
            FROM {$wpdb->prefix}indoor_task_submissions
            GROUP BY status
        ");
        $approved = 0;
        $pending = 0;
        $rejected = 0;
        foreach ($submission_stats as $stat) {
            if ($stat->status === 'approved') $approved = $stat->count;
            if ($stat->status === 'pending') $pending = $stat->count;
            if ($stat->status === 'rejected') $rejected = $stat->count;
        }
        ?>
        <div id="submission-chart" style="height: 250px;"></div>
    </div>
</div>

<!-- Add/Edit Task Form -->
<div class="task-card">
    <h2><i class="dashicons dashicons-<?php echo $edit_task ? 'edit' : 'plus-alt'; ?>"></i> <?php echo $edit_task ? __('Edit Task', 'indoor-tasks') : __('Add New Task', 'indoor-tasks'); ?></h2>
    
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="it_task_action" value="<?php echo $edit_task ? 'edit' : 'add'; ?>" />
        <?php if ($edit_task): ?>
            <input type="hidden" name="task_id" value="<?php echo $edit_task->id; ?>" />
        <?php endif; ?>
        
        <div class="it-fields-row">
            <div>
                <label><?php _e('Title', 'indoor-tasks'); ?></label>
                <input type="text" name="title" required style="width:100%" value="<?php echo $edit_task ? esc_attr($edit_task->title) : ''; ?>">
            </div>
            <div>
                <label><?php _e('Category', 'indoor-tasks'); ?></label>
                <div class="category-select-wrapper">
                    <select name="category" required class="category-select">
                        <option value=""><?php _e('Select Category', 'indoor-tasks'); ?></option>
                        <?php 
                        $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}indoor_task_categories ORDER BY name ASC");
                        if ($categories): 
                            foreach ($categories as $cat): 
                        ?>
                            <option value="<?php echo esc_attr($cat->name); ?>" 
                                <?php echo ($edit_task && $edit_task->category === $cat->name) ? 'selected' : ''; ?>>
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php 
                            endforeach; 
                        endif;
                        ?>
                    </select>
                    <?php if (empty($categories)): ?>
                        <p class="category-notice"><?php _e('No categories found. Please add categories first.', 'indoor-tasks'); ?></p>
                    <?php endif; ?>
                    <a href="admin.php?page=indoor-tasks-task-category" class="add-category-link">
                        <span class="dashicons dashicons-plus-alt"></span> 
                        <?php _e('Add Category', 'indoor-tasks'); ?>
                    </a>
                </div>
            </div>
            <div>
                <label><?php _e('Client', 'indoor-tasks'); ?></label>
                <div class="client-select-wrapper">
                    <select name="client_id" class="client-select">
                        <option value=""><?php _e('Select Client (Optional)', 'indoor-tasks'); ?></option>
                        <?php 
                        $clients = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}indoor_task_clients WHERE status = 'active' ORDER BY name ASC");
                        if ($clients): 
                            foreach ($clients as $client): 
                        ?>
                            <option value="<?php echo esc_attr($client->id); ?>" 
                                <?php echo ($edit_task && $edit_task->client_id == $client->id) ? 'selected' : ''; ?>>
                                <?php echo esc_html($client->name); ?>
                                <?php if ($client->company): ?>
                                    - <?php echo esc_html($client->company); ?>
                                <?php endif; ?>
                            </option>
                        <?php 
                            endforeach; 
                        endif;
                        ?>
                    </select>
                    <?php if (empty($clients)): ?>
                        <p class="client-notice"><?php _e('No clients found.', 'indoor-tasks'); ?></p>
                    <?php endif; ?>
                    <a href="admin.php?page=indoor-tasks-clients" class="add-client-link">
                        <span class="dashicons dashicons-plus-alt"></span> 
                        <?php _e('Add Client', 'indoor-tasks'); ?>
                    </a>
                </div>
            </div>
            <div>
                <label><?php _e('Reward Points', 'indoor-tasks'); ?></label>
                <input type="number" name="reward_points" required style="width:100%" value="<?php echo $edit_task ? esc_attr($edit_task->reward_points) : ''; ?>">
            </div>
            <div>
                <label><?php _e('Budget (Client Budget)', 'indoor-tasks'); ?></label>
                <input type="number" name="budget" step="0.01" min="0" style="width:100%" placeholder="0.00" value="<?php echo $edit_task ? esc_attr($edit_task->budget) : ''; ?>">
                <p style="margin-top: 5px; font-size: 12px; color: #666;"><?php _e('Client budget for this task (for profit calculation)', 'indoor-tasks'); ?></p>
            </div>
        </div>
        
        <div class="it-fields-row">
            <div>
                <label><?php _e('Deadline', 'indoor-tasks'); ?></label>
                <input type="date" name="deadline" required style="width:100%" value="<?php echo $edit_task ? esc_attr($edit_task->deadline) : ''; ?>">
            </div>
            <div>
                <label><?php _e('Task Duration (minutes)', 'indoor-tasks'); ?></label>
                <input type="number" name="duration" required style="width:100%" value="<?php echo $edit_task ? esc_attr($edit_task->duration) : ''; ?>">
            </div>
            <div>
                <label><?php _e('Max Users', 'indoor-tasks'); ?></label>
                <input type="number" name="max_users" required style="width:100%" value="<?php echo $edit_task ? esc_attr($edit_task->max_users) : ''; ?>">
            </div>
        </div>
        
        <div>
            <label><?php _e('Description', 'indoor-tasks'); ?></label>
            <textarea name="description" style="width:100%;height:80px;"><?php echo $edit_task ? esc_textarea($edit_task->description) : ''; ?></textarea>
        </div>
        
        <div>
            <label><?php _e('Short Description', 'indoor-tasks'); ?></label>
            <textarea name="short_description" style="width:100%;height:60px;" placeholder="Brief description for task list display (max 150 characters recommended)"><?php echo $edit_task ? esc_textarea($edit_task->short_description) : ''; ?></textarea>
            <p style="margin-top: 5px; font-size: 12px; color: #666;"><?php _e('This will be displayed in the task list. Keep it short and engaging.', 'indoor-tasks'); ?></p>
        </div>
        
        <div>
            <label><?php _e('How to do the Task', 'indoor-tasks'); ?></label>
            <textarea name="how_to" style="width:100%;height:60px;"><?php echo $edit_task ? esc_textarea($edit_task->how_to) : ''; ?></textarea>
        </div>

        <!-- Step-by-Step Guide Section -->
        <div style="margin-bottom: 25px;">
            <label style="font-weight: 600; margin-bottom: 10px; display: block;"><?php _e('Step-by-Step Guide (with Images)', 'indoor-tasks'); ?></label>
            <p style="margin-bottom: 15px; color: #666; font-size: 14px;"><?php _e('Add detailed steps with optional images to help users complete the task.', 'indoor-tasks'); ?></p>
            
            <div id="step-by-step-container">
                <?php 
                $existing_steps = [];
                if ($edit_task && !empty($edit_task->step_by_step_guide)) {
                    $existing_steps = json_decode($edit_task->step_by_step_guide, true);
                    if (!is_array($existing_steps)) {
                        $existing_steps = [];
                    }
                }
                
                if (empty($existing_steps)) {
                    $existing_steps = [['description' => '', 'image' => '']]; // At least one step
                }
                
                foreach ($existing_steps as $index => $step): 
                ?>
                <div class="step-item" style="background: #f9f9f9; padding: 15px; margin-bottom: 15px; border-radius: 6px; border-left: 4px solid #2271b1;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <strong><?php echo sprintf(__('Step %d', 'indoor-tasks'), $index + 1); ?></strong>
                        <button type="button" class="remove-step button button-small" style="color: #d63638;"><?php _e('Remove', 'indoor-tasks'); ?></button>
                    </div>
                    
                    <div style="margin-bottom: 10px;">
                        <label><?php _e('Description', 'indoor-tasks'); ?></label>
                        <textarea name="step_descriptions[]" style="width: 100%; height: 80px;" placeholder="<?php _e('Describe this step...', 'indoor-tasks'); ?>"><?php echo esc_textarea($step['description']); ?></textarea>
                    </div>
                    
                    <div>
                        <label><?php _e('Step Image (Optional)', 'indoor-tasks'); ?></label>
                        <input type="file" name="step_images[]" accept="image/*" style="width: 100%;">
                        <?php if (!empty($step['image'])): ?>
                            <div style="margin-top: 5px;">
                                <img src="<?php echo esc_url($step['image']); ?>" style="max-height: 60px; border-radius: 4px;">
                                <input type="hidden" name="existing_step_images[]" value="<?php echo esc_url($step['image']); ?>">
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="existing_step_images[]" value="">
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" id="add-step" class="button button-secondary">
                <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add Step', 'indoor-tasks'); ?>
            </button>
        </div>

        <div class="it-fields-row">
            <div>
                <label><?php _e('Task Link (for completion)', 'indoor-tasks'); ?></label>
                <input type="url" name="task_link" style="width:100%" value="<?php echo $edit_task ? esc_url($edit_task->task_link) : ''; ?>">
            </div>
            <div>
                <label><?php _e('Guide Link (suggestion)', 'indoor-tasks'); ?></label>
                <input type="url" name="guide_link" style="width:100%" value="<?php echo $edit_task ? esc_url($edit_task->guide_link) : ''; ?>">
            </div>
            <div>
                <label><?php _e('Video Tutorial Link', 'indoor-tasks'); ?></label>
                <input type="url" name="video_link" style="width:100%" placeholder="<?php _e('https://youtube.com/watch?v=...', 'indoor-tasks'); ?>" value="<?php echo $edit_task ? esc_url($edit_task->video_link) : ''; ?>">
                <p style="margin-top: 5px; font-size: 12px; color: #666;"><?php _e('YouTube, Vimeo, or any video URL to help users understand the task', 'indoor-tasks'); ?></p>
            </div>
        </div>

        <!-- Target Countries Section -->
        <div style="margin-bottom: 25px;">
            <label style="font-weight: 600; margin-bottom: 10px; display: block;"><?php _e('Target Countries', 'indoor-tasks'); ?></label>
            <p style="margin-bottom: 15px; color: #666; font-size: 14px;"><?php _e('Select countries where this task should be available. Leave empty to make it available worldwide.', 'indoor-tasks'); ?></p>
            
            <div class="countries-selection-container">
                <div style="position: relative; margin-bottom: 10px;">
                    <input type="text" id="country-search" placeholder="<?php _e('Search countries...', 'indoor-tasks'); ?>" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="countries-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #fff;">
                    <?php 
                    $countries = [
                        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina', 
                        'AU' => 'Australia', 'AT' => 'Austria', 'BD' => 'Bangladesh', 'BE' => 'Belgium',
                        'BR' => 'Brazil', 'CA' => 'Canada', 'CN' => 'China', 'CO' => 'Colombia',
                        'EG' => 'Egypt', 'FR' => 'France', 'DE' => 'Germany', 'GH' => 'Ghana',
                        'GR' => 'Greece', 'IN' => 'India', 'ID' => 'Indonesia', 'IT' => 'Italy',
                        'JP' => 'Japan', 'KE' => 'Kenya', 'MY' => 'Malaysia', 'MX' => 'Mexico',
                        'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'PK' => 'Pakistan',
                        'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'RU' => 'Russia',
                        'SA' => 'Saudi Arabia', 'SG' => 'Singapore', 'ZA' => 'South Africa', 'KR' => 'South Korea',
                        'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland',
                        'TW' => 'Taiwan', 'TH' => 'Thailand', 'TR' => 'Turkey', 'AE' => 'UAE',
                        'GB' => 'United Kingdom', 'US' => 'United States', 'VN' => 'Vietnam', 'ZW' => 'Zimbabwe'
                    ];
                    
                    $selected_countries = [];
                    if ($edit_task && !empty($edit_task->target_countries)) {
                        $selected_countries = json_decode($edit_task->target_countries, true);
                        if (!is_array($selected_countries)) {
                            $selected_countries = [];
                        }
                    }
                    
                    foreach ($countries as $code => $name):
                        $is_selected = in_array($code, $selected_countries);
                    ?>
                    <div class="country-item" style="margin-bottom: 5px;">
                        <label style="display: flex; align-items: center; cursor: pointer; padding: 5px; border-radius: 3px; transition: background-color 0.2s;" 
                               onmouseover="this.style.backgroundColor='#f0f0f0'" onmouseout="this.style.backgroundColor='transparent'">
                            <input type="checkbox" name="target_countries[]" value="<?php echo esc_attr($code); ?>" 
                                   <?php echo $is_selected ? 'checked' : ''; ?>
                                   style="margin-right: 8px;">
                            <span class="country-name"><?php echo esc_html($name); ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 10px;">
                    <button type="button" id="select-all-countries" class="button button-small"><?php _e('Select All', 'indoor-tasks'); ?></button>
                    <button type="button" id="deselect-all-countries" class="button button-small"><?php _e('Deselect All', 'indoor-tasks'); ?></button>
                    <span id="selected-count" style="margin-left: 15px; color: #666; font-size: 12px;"></span>
                </div>
            </div>
        </div>
        
        <div class="it-fields-row">
            <div>
                <label><?php _e('Featured Image', 'indoor-tasks'); ?></label>
                <div class="featured-image-wrapper">
                    <input type="hidden" name="task_image_id" id="task_image_id" value="<?php echo $edit_task ? esc_attr($edit_task->task_image_id ?? '') : ''; ?>">
                    <div id="task_image_preview">
                        <?php 
                        $image_url = '';
                        if ($edit_task) {
                            if (!empty($edit_task->task_image_id)) {
                                $image_url = wp_get_attachment_url($edit_task->task_image_id);
                            } elseif (!empty($edit_task->task_image)) {
                                $image_url = $edit_task->task_image;
                            }
                        }
                        if ($image_url): 
                        ?>
                            <img src="<?php echo esc_url($image_url); ?>" style="max-width: 200px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;">
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <button type="button" id="select_task_image" class="button">
                            <?php echo $image_url ? __('Change Image', 'indoor-tasks') : __('Select Image', 'indoor-tasks'); ?>
                        </button>
                        <?php if ($image_url): ?>
                            <button type="button" id="remove_task_image" class="button" style="margin-left: 5px;"><?php _e('Remove', 'indoor-tasks'); ?></button>
                        <?php endif; ?>
                    </div>
                    <p style="margin-top: 5px; font-size: 12px; color: #666;"><?php _e('Click "Select Image" to choose from Media Library or upload new image', 'indoor-tasks'); ?></p>
                </div>
            </div>
            <div>
                <label><?php _e('Special Message', 'indoor-tasks'); ?></label>
                <input type="text" name="special_message" style="width:100%" value="<?php echo $edit_task ? esc_attr($edit_task->special_message) : ''; ?>">
            </div>
            <div>
                <label><?php _e('Difficulty Level', 'indoor-tasks'); ?></label>
                <select name="difficulty_level" style="width:100%">
                    <option value="easy" <?php echo ($edit_task && $edit_task->difficulty_level === 'easy') ? 'selected' : ''; ?>><?php _e('Easy', 'indoor-tasks'); ?></option>
                    <option value="medium" <?php echo ($edit_task && $edit_task->difficulty_level === 'medium') ? 'selected' : ''; ?>><?php _e('Medium', 'indoor-tasks'); ?></option>
                    <option value="hard" <?php echo ($edit_task && $edit_task->difficulty_level === 'hard') ? 'selected' : ''; ?>><?php _e('Hard', 'indoor-tasks'); ?></option>
                </select>
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: inline-flex; align-items: center;">
                <input type="checkbox" name="featured" value="1" <?php echo ($edit_task && $edit_task->featured) ? 'checked' : ''; ?>>
                <span style="margin-left: 5px;"><?php _e('Featured Task (will be highlighted on the task list)', 'indoor-tasks'); ?></span>
            </label>
        </div>
        
        <?php if (!$edit_task): ?>
        <div style="margin-bottom: 20px;">
            <label style="display: inline-flex; align-items: center;">
                <input type="checkbox" name="send_notification" value="1">
                <span style="margin-left: 5px;"><?php _e('Send notification to users about this new task', 'indoor-tasks'); ?></span>
            </label>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: inline-flex; align-items: center;">
                <input type="checkbox" name="send_telegram" value="1">
                <span style="margin-left: 5px;"><?php _e('Send Telegram notification for this new task', 'indoor-tasks'); ?></span>
            </label>
            <p class="description" style="margin-left: 25px;"><?php _e('This will send a notification to the Telegram channel/group configured in Notification Settings.', 'indoor-tasks'); ?></p>
        </div>
        <?php endif; ?>
        
        <button type="submit" class="button button-primary">
            <?php echo $edit_task ? __('Update Task', 'indoor-tasks') : __('Add Task', 'indoor-tasks'); ?>
        </button>
        
        <?php if ($edit_task): ?>
            <a href="?page=indoor-tasks-add-task" class="button" style="margin-left: 10px;"><?php _e('Cancel', 'indoor-tasks'); ?></a>
        <?php endif; ?>
    </form>
</div>

<!-- Task List -->
<div class="task-card">
    <h2><i class="dashicons dashicons-list-view"></i> <?php _e('All Tasks', 'indoor-tasks'); ?></h2>
    
    <!-- Filter Form -->
    <form method="get" class="task-filter">
        <input type="hidden" name="page" value="indoor-tasks-add-task">
        <div>
            <label for="filter_category"><?php _e('Category:', 'indoor-tasks'); ?></label>
            <select name="filter_category" id="filter_category" style="min-width: 150px;">
                <option value=""><?php _e('All Categories', 'indoor-tasks'); ?></option>
                <?php foreach ($task_categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->category); ?>" <?php selected($filter_category, $cat->category); ?>><?php echo esc_html($cat->category); ?> (<?php echo $cat->count; ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filter_status"><?php _e('Status:', 'indoor-tasks'); ?></label>
            <select name="filter_status" id="filter_status" style="min-width: 150px;">
                <option value=""><?php _e('All Status', 'indoor-tasks'); ?></option>
                <option value="active" <?php selected($filter_status, 'active'); ?>><?php _e('Active', 'indoor-tasks'); ?></option>
                <option value="expired" <?php selected($filter_status, 'expired'); ?>><?php _e('Expired', 'indoor-tasks'); ?></option>
                <option value="featured" <?php selected($filter_status, 'featured'); ?>><?php _e('Featured', 'indoor-tasks'); ?></option>
            </select>
        </div>
        <div>
            <button type="submit" class="button"><?php _e('Filter', 'indoor-tasks'); ?></button>
            <?php if ($filter_category || $filter_status): ?>
                <a href="?page=indoor-tasks-add-task" class="button" style="margin-left: 5px;"><?php _e('Reset', 'indoor-tasks'); ?></a>
            <?php endif; ?>
        </div>
    </form>
    
    <!-- Tasks Table -->
    <table class="task-table">
        <thead>
            <tr>
                <th><?php _e('ID', 'indoor-tasks'); ?></th>
                <th><?php _e('Title', 'indoor-tasks'); ?></th>
                <th><?php _e('Category', 'indoor-tasks'); ?></th>
                <th><?php _e('Points', 'indoor-tasks'); ?></th>
                <th><?php _e('Budget', 'indoor-tasks'); ?></th>
                <th><?php _e('Profit', 'indoor-tasks'); ?></th>
                <th><?php _e('Deadline', 'indoor-tasks'); ?></th>
                <th><?php _e('Status', 'indoor-tasks'); ?></th>
                <th><?php _e('Submissions', 'indoor-tasks'); ?></th>
                <th><?php _e('Actions', 'indoor-tasks'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tasks)): ?>
                <tr>
                    <td colspan="10"><?php _e('No tasks found.', 'indoor-tasks'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($tasks as $task): 
                    $is_expired = strtotime($task->deadline) < time();
                    $is_featured = $task->featured;
                    $budget = isset($task->budget) ? $task->budget : 0;
                    $max_users = isset($task->max_users) ? $task->max_users : 1;
                    $profit = $budget - ($task->reward_points * $max_users);
                    
                    // Get submission count
                    $submission_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions WHERE task_id = %d",
                        $task->id
                    ));
                ?>
                    <tr>
                        <td><?php echo $task->id; ?></td>
                        <td>
                            <?php echo esc_html($task->title); ?>
                            <?php if ($is_featured): ?>
                                <span class="dashicons dashicons-star-filled" style="color: #ffb900; font-size: 16px; vertical-align: middle;" title="<?php _e('Featured Task', 'indoor-tasks'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($task->category); ?></td>
                        <td><?php echo $task->reward_points; ?></td>
                        <td style="color: #2271b1; font-weight: 500;">₹<?php echo number_format($budget, 2); ?></td>
                        <td style="color: <?php echo $profit >= 0 ? '#00a32a' : '#d63638'; ?>; font-weight: 600;">
                            ₹<?php echo number_format($profit, 2); ?>
                            <?php if ($profit < 0): ?><span style="font-size: 12px; margin-left: 2px;">⚠️</span><?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($task->deadline)); ?></td>
                        <td>
                            <?php if ($is_expired): ?>
                                <span class="task-badge badge-expired"><?php _e('Expired', 'indoor-tasks'); ?></span>
                            <?php else: ?>
                                <span class="task-badge badge-active"><?php _e('Active', 'indoor-tasks'); ?></span>
                            <?php endif; ?>
                            
                            <?php if ($is_featured): ?>
                                <span class="task-badge badge-featured"><?php _e('Featured', 'indoor-tasks'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $submission_count; ?></td>
                        <td>
                            <a href="?page=indoor-tasks-add-task&edit=<?php echo $task->id; ?>" class="button button-small"><?php _e('Edit', 'indoor-tasks'); ?></a>
                            <a href="?page=indoor-tasks-task-submissions&task_id=<?php echo $task->id; ?>" class="button button-small"><?php _e('Submissions', 'indoor-tasks'); ?></a>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="it_task_action" value="delete" />
                                <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                                <button type="submit" class="button button-small" onclick="return confirm('<?php _e('Are you sure you want to delete this task?', 'indoor-tasks'); ?>');"><?php _e('Delete', 'indoor-tasks'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $url_params = array_merge($_GET, ['paged' => '']);
            $base_url = '?' . http_build_query($url_params) . '%_%';
            
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == $current_page) {
                    echo '<span class="current">' . $i . '</span>';
                } else {
                    echo '<a href="' . str_replace('%_%', '&paged=' . $i, $base_url) . '">' . $i . '</a>';
                }
            }
            ?>
        </div>
    <?php endif; ?>
</div>

<!-- Chart scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
jQuery(document).ready(function($) {
    // Category distribution chart
    var catCtx = document.getElementById('category-chart').getContext('2d');
    var catData = {
        labels: [
            <?php 
            $cat_names = [];
            $cat_counts = [];
            foreach ($task_categories as $cat) {
                $cat_names[] = "'" . esc_js($cat->category) . "'";
                $cat_counts[] = $cat->count;
            }
            echo implode(', ', $cat_names);
            ?>
        ],
        datasets: [{
            label: '<?php _e('Tasks per Category', 'indoor-tasks'); ?>',
            data: [<?php echo implode(', ', $cat_counts); ?>],
            backgroundColor: [
                'rgba(54, 162, 235, 0.6)',
                'rgba(255, 99, 132, 0.6)',
                'rgba(255, 206, 86, 0.6)',
                'rgba(75, 192, 192, 0.6)',
                'rgba(153, 102, 255, 0.6)',
                'rgba(255, 159, 64, 0.6)',
                'rgba(199, 199, 199, 0.6)'
            ],
            borderWidth: 1
        }]
    };
    
    new Chart(catCtx, {
        type: 'pie',
        data: catData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
    
    // Submission stats chart
    var subCtx = document.getElementById('submission-chart').getContext('2d');
    var subData = {
        labels: ['<?php _e('Approved', 'indoor-tasks'); ?>', '<?php _e('Pending', 'indoor-tasks'); ?>', '<?php _e('Rejected', 'indoor-tasks'); ?>'],
        datasets: [{
            label: '<?php _e('Submissions', 'indoor-tasks'); ?>',
            data: [<?php echo $approved; ?>, <?php echo $pending; ?>, <?php echo $rejected; ?>],
            backgroundColor: [
                'rgba(46, 204, 113, 0.6)',
                'rgba(52, 152, 219, 0.6)',
                'rgba(231, 76, 60, 0.6)'
            ],
            borderWidth: 1
        }]
    };
    
    new Chart(subCtx, {
        type: 'doughnut',
        data: subData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
});

// Country Selection and Step Repeater JavaScript
jQuery(document).ready(function($) {
    // Country selection functionality
    function updateSelectedCount() {
        var selectedCount = $('.countries-list input[type="checkbox"]:checked').length;
        $('#selected-count').text(selectedCount > 0 ? selectedCount + ' countries selected' : '');
    }
    
    // Country search functionality
    $('#country-search').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('.country-item').each(function() {
            var countryName = $(this).find('.country-name').text().toLowerCase();
            if (countryName.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Select/Deselect all countries
    $('#select-all-countries').on('click', function() {
        $('.countries-list input[type="checkbox"]:visible').prop('checked', true);
        updateSelectedCount();
    });
    
    $('#deselect-all-countries').on('click', function() {
        $('.countries-list input[type="checkbox"]').prop('checked', false);
        updateSelectedCount();
    });
    
    // Update count when individual countries are selected
    $('.countries-list').on('change', 'input[type="checkbox"]', function() {
        updateSelectedCount();
    });
    
    // Initialize selected count
    updateSelectedCount();
    
    // Step-by-step guide functionality
    var stepCounter = $('.step-item').length || 0;
    
    function updateStepNumbers() {
        $('.step-item').each(function(index) {
            $(this).find('strong').first().text('Step ' + (index + 1));
        });
    }
    
    // Add new step
    $('#add-step').on('click', function() {
        var stepHtml = `
            <div class="step-item" style="background: #f9f9f9; padding: 15px; margin-bottom: 15px; border-radius: 6px; border-left: 4px solid #2271b1;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong>Step ${stepCounter + 1}</strong>
                    <button type="button" class="remove-step button button-small" style="color: #d63638;">Remove</button>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <label>Description</label>
                    <textarea name="step_descriptions[]" style="width: 100%; height: 80px;" placeholder="Describe this step..."></textarea>
                </div>
                
                <div>
                    <label>Step Image (Optional)</label>
                    <input type="file" name="step_images[]" accept="image/*" style="width: 100%;">
                    <input type="hidden" name="existing_step_images[]" value="">
                </div>
            </div>
        `;
        
        $('#step-by-step-container').append(stepHtml);
        stepCounter++;
        updateStepNumbers();
    });
    
    // Remove step
    $(document).on('click', '.remove-step', function() {
        if ($('.step-item').length > 1) {
            $(this).closest('.step-item').fadeOut(300, function() {
                $(this).remove();
                updateStepNumbers();
            });
        } else {
            alert('At least one step is required.');
        }
    });
    
    // Image preview functionality
    $(document).on('change', 'input[type="file"][name="step_images[]"]', function() {
        var input = this;
        var $container = $(input).parent();
        
        // Remove existing preview
        $container.find('.image-preview').remove();
        
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var $preview = $('<div class="image-preview" style="margin-top: 5px;"><img src="' + e.target.result + '" style="max-height: 60px; border-radius: 4px; border: 1px solid #ddd;"></div>');
                $container.append($preview);
            };
            reader.readAsDataURL(input.files[0]);
        }
    });
    
    // Initialize step numbers on page load
    updateStepNumbers();
});
</script>

<script>
jQuery(document).ready(function($) {
    // WordPress Media Library for Featured Image
    var mediaFrame;
    
    $('#select_task_image').on('click', function(e) {
        e.preventDefault();
        
        // If the media frame already exists, reopen it
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }
        
        // Create the media frame
        mediaFrame = wp.media({
            title: 'Select Task Image',
            button: {
                text: 'Use this image'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        // When an image is selected, run a callback
        mediaFrame.on('select', function() {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            
            // Set the image ID
            $('#task_image_id').val(attachment.id);
            
            // Update the preview
            var previewHtml = '<img src="' + attachment.url + '" style="max-width: 200px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;">';
            $('#task_image_preview').html(previewHtml);
            
            // Update button text
            $('#select_task_image').text('Change Image');
            
            // Show remove button if it doesn't exist
            if (!$('#remove_task_image').length) {
                $('#select_task_image').after('<button type="button" id="remove_task_image" class="button" style="margin-left: 5px;">Remove</button>');
            }
        });
        
        // Finally, open the modal
        mediaFrame.open();
    });
    
    // Remove featured image
    $(document).on('click', '#remove_task_image', function(e) {
        e.preventDefault();
        
        // Clear the image ID
        $('#task_image_id').val('');
        
        // Clear the preview
        $('#task_image_preview').html('');
        
        // Update button text
        $('#select_task_image').text('Select Image');
        
        // Remove the remove button
        $(this).remove();
    });
});
</script>

<?php
// Enqueue WordPress media scripts
wp_enqueue_media();
?>
