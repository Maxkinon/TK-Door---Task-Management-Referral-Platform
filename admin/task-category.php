<?php
// Task Category management (admin)
global $wpdb;

// Add styles for color picker
wp_enqueue_style('wp-color-picker');
wp_enqueue_script('wp-color-picker');
wp_enqueue_script('jquery');

// Add custom inline script for color picker
add_action('admin_footer', function() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('.color-field').wpColorPicker();
        
        // Initialize color pickers for rows added dynamically
        function initColorPickers() {
            $('.color-field:not(.wp-color-picker)').wpColorPicker();
        }
        
        // Re-init after adding a row
        $(document).on('click', '.add-category-btn', function() {
            setTimeout(initColorPickers, 100);
        });
    });
    </script>
    <?php
});

// Process form submissions
if (isset($_POST['action'])) {
    // Add new category
    if ($_POST['action'] === 'add_category' && !empty($_POST['name'])) {
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $color = sanitize_hex_color($_POST['color'] ?? '#3b82f6');
        
        // Make sure color is valid
        if (empty($color)) {
            $color = '#3b82f6'; // Default blue
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'indoor_task_categories', 
            [
                'name' => $name,
                'description' => $description,
                'color' => $color,
                'status' => 'active'
            ],
            ['%s', '%s', '%s', '%s']
        );
        
        if ($result) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Category added successfully.', 'indoor-tasks') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Error adding category.', 'indoor-tasks') . '</p></div>';
        }
    }
    
    // Update category
    if ($_POST['action'] === 'update_category' && isset($_POST['cat_id'])) {
        $cat_id = intval($_POST['cat_id']);
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $color = sanitize_hex_color($_POST['color'] ?? '#3b82f6');
        $status = sanitize_text_field($_POST['status'] ?? 'active');
        
        $result = $wpdb->update(
            $wpdb->prefix . 'indoor_task_categories',
            [
                'name' => $name,
                'description' => $description,
                'color' => $color,
                'status' => $status
            ],
            ['id' => $cat_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Category updated successfully.', 'indoor-tasks') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Error updating category.', 'indoor-tasks') . '</p></div>';
        }
    }
    
    // Delete category
    if ($_POST['action'] === 'delete_category' && isset($_POST['cat_id'])) {
        $cat_id = intval($_POST['cat_id']);
        
        // Check if tasks are using this category
        $tasks_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks WHERE category_id = %d",
            $cat_id
        ));
        
        if ($tasks_count > 0) {
            echo '<div class="notice notice-error is-dismissible"><p>' . 
                sprintf(__('Cannot delete category: %d tasks are using this category.', 'indoor-tasks'), $tasks_count) . 
                '</p></div>';
        } else {
            $result = $wpdb->delete(
                $wpdb->prefix . 'indoor_task_categories',
                ['id' => $cat_id],
                ['%d']
            );
            
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Category deleted successfully.', 'indoor-tasks') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error deleting category.', 'indoor-tasks') . '</p></div>';
            }
        }
    }
}

// Check if we're editing a category
$edit_mode = false;
$category = null;

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['cat_id'])) {
    $edit_mode = true;
    $cat_id = intval($_GET['cat_id']);
    
    $category = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_task_categories WHERE id = %d",
        $cat_id
    ));
    
    if (!$category) {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Category not found.', 'indoor-tasks') . '</p></div>';
        $edit_mode = false;
    }
}

// Fetch all categories
$categories = $wpdb->get_results(
    "SELECT c.*, 
     (SELECT COUNT(*) FROM {$wpdb->prefix}indoor_tasks WHERE category_id = c.id) as tasks_count 
     FROM {$wpdb->prefix}indoor_task_categories c 
     ORDER BY c.name ASC"
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Task Categories', 'indoor-tasks'); ?></h1>
    
    <hr class="wp-header-end">
    
    <?php if ($edit_mode && $category): ?>
        <h2><?php _e('Edit Category', 'indoor-tasks'); ?></h2>
        <form method="post" class="form-table">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="cat_id" value="<?php echo esc_attr($category->id); ?>">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="name"><?php _e('Name', 'indoor-tasks'); ?></label></th>
                    <td>
                        <input type="text" name="name" id="name" value="<?php echo esc_attr($category->name); ?>" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="description"><?php _e('Description', 'indoor-tasks'); ?></label></th>
                    <td>
                        <textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea($category->description); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="color"><?php _e('Color', 'indoor-tasks'); ?></label></th>
                    <td>
                        <input type="text" name="color" id="color" value="<?php echo esc_attr($category->color); ?>" class="color-field" data-default-color="#3b82f6">
                        <p class="description"><?php _e('Select a color for this category.', 'indoor-tasks'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="status"><?php _e('Status', 'indoor-tasks'); ?></label></th>
                    <td>
                        <select name="status" id="status">
                            <option value="active" <?php selected($category->status, 'active'); ?>><?php _e('Active', 'indoor-tasks'); ?></option>
                            <option value="inactive" <?php selected($category->status, 'inactive'); ?>><?php _e('Inactive', 'indoor-tasks'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Update Category', 'indoor-tasks'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=indoor-tasks-categories')); ?>" class="button"><?php _e('Cancel', 'indoor-tasks'); ?></a>
            </p>
        </form>
    <?php else: ?>
        <h2><?php _e('Add New Category', 'indoor-tasks'); ?></h2>
        <form method="post" class="form-table">
            <input type="hidden" name="action" value="add_category">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="name"><?php _e('Name', 'indoor-tasks'); ?></label></th>
                    <td>
                        <input type="text" name="name" id="name" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="description"><?php _e('Description', 'indoor-tasks'); ?></label></th>
                    <td>
                        <textarea name="description" id="description" rows="3" class="large-text"></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="color"><?php _e('Color', 'indoor-tasks'); ?></label></th>
                    <td>
                        <input type="text" name="color" id="color" value="#3b82f6" class="color-field" data-default-color="#3b82f6">
                        <p class="description"><?php _e('Select a color for this category.', 'indoor-tasks'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Add Category', 'indoor-tasks'); ?></button>
            </p>
        </form>
        
        <h2><?php _e('Categories', 'indoor-tasks'); ?></h2>
        
        <?php if (empty($categories)): ?>
            <div class="notice notice-info">
                <p><?php _e('No categories found. Add your first category above.', 'indoor-tasks'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('ID', 'indoor-tasks'); ?></th>
                        <th scope="col"><?php _e('Name', 'indoor-tasks'); ?></th>
                        <th scope="col"><?php _e('Description', 'indoor-tasks'); ?></th>
                        <th scope="col"><?php _e('Color', 'indoor-tasks'); ?></th>
                        <th scope="col"><?php _e('Status', 'indoor-tasks'); ?></th>
                        <th scope="col"><?php _e('Tasks', 'indoor-tasks'); ?></th>
                        <th scope="col"><?php _e('Actions', 'indoor-tasks'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?php echo esc_html($cat->id); ?></td>
                            <td>
                                <strong><?php echo esc_html($cat->name); ?></strong>
                            </td>
                            <td><?php echo esc_html(wp_trim_words($cat->description, 10, '...')); ?></td>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <span style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo esc_attr($cat->color); ?>; border-radius: 4px; margin-right: 8px;"></span>
                                    <?php echo esc_html($cat->color); ?>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $status_class = $cat->status === 'active' ? 'status-active' : 'status-inactive';
                                $status_text = $cat->status === 'active' ? __('Active', 'indoor-tasks') : __('Inactive', 'indoor-tasks');
                                ?>
                                <span class="<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($cat->tasks_count); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=indoor-tasks-categories&action=edit&cat_id=' . $cat->id)); ?>" class="button button-small"><?php _e('Edit', 'indoor-tasks'); ?></a>
                                
                                <?php if ($cat->tasks_count == 0): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="cat_id" value="<?php echo esc_attr($cat->id); ?>">
                                        <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this category?', 'indoor-tasks'); ?>');"><?php _e('Delete', 'indoor-tasks'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.status-active {
    color: #46b450;
    font-weight: 600;
}
.status-inactive {
    color: #dc3232;
}
</style>
