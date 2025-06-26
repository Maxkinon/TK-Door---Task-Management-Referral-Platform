<?php
/**
 * Indoor Tasks System Status Check
 * This file helps verify that all new features are properly implemented
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check system status
 */
function indoor_tasks_system_status_check() {
    global $wpdb;
    
    $status = [
        'database' => [],
        'features' => [],
        'files' => []
    ];
    
    // Check database tables and columns
    $tables_to_check = [
        'indoor_tasks' => ['client_id', 'task_image_id', 'short_description', 'deadline'],
        'indoor_task_clients' => ['id', 'name', 'email', 'status'],
        'indoor_task_categories' => ['id', 'name', 'color'],
        'indoor_task_submissions' => ['id', 'task_id', 'user_id']
    ];
    
    foreach ($tables_to_check as $table => $columns) {
        $full_table_name = $wpdb->prefix . $table;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") === $full_table_name;
        
        $status['database'][$table] = [
            'exists' => $table_exists,
            'columns' => []
        ];
        
        if ($table_exists) {
            foreach ($columns as $column) {
                $column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$full_table_name} LIKE '{$column}'");
                $status['database'][$table]['columns'][$column] = !empty($column_exists);
            }
        }
    }
    
    // Check key features
    $features_to_check = [
        'clients_menu' => function_exists('indoor_tasks_admin_menu') && has_action('admin_menu', 'indoor_tasks_admin_menu'),
        'database_migration' => function_exists('indoor_tasks_run_migrations'),
        'media_library_integration' => file_exists(INDOOR_TASKS_PATH . 'admin/manage-tasks.php'),
        'enhanced_task_list' => file_exists(INDOOR_TASKS_PATH . 'admin/tasks-list.php'),
        'enhanced_submissions' => file_exists(INDOOR_TASKS_PATH . 'admin/task-submissions.php')
    ];
    
    $status['features'] = $features_to_check;
    
    // Check critical files
    $files_to_check = [
        'admin/clients.php',
        'admin/manage-tasks.php', 
        'admin/tasks-list.php',
        'admin/task-submissions.php',
        'includes/database-migration.php',
        'templates/tkm-door-task-archive.php',
        'templates/tkm-door-task-detail.php'
    ];
    
    foreach ($files_to_check as $file) {
        $status['files'][$file] = file_exists(INDOOR_TASKS_PATH . $file);
    }
    
    return $status;
}

/**
 * Display system status in admin
 */
function indoor_tasks_display_system_status() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $status = indoor_tasks_system_status_check();
    
    ?>
    <div class="wrap">
        <h1><?php _e('Indoor Tasks - System Status', 'indoor-tasks'); ?></h1>
        
        <div class="notice notice-info">
            <p><?php _e('This page shows the status of all new features implemented in the Indoor Tasks system.', 'indoor-tasks'); ?></p>
        </div>
        
        <!-- Database Status -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e('Database Status', 'indoor-tasks'); ?></h2>
            </div>
            <div class="inside">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Table', 'indoor-tasks'); ?></th>
                            <th><?php _e('Status', 'indoor-tasks'); ?></th>
                            <th><?php _e('Columns', 'indoor-tasks'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status['database'] as $table => $info): ?>
                        <tr>
                            <td><code><?php echo esc_html($table); ?></code></td>
                            <td>
                                <?php if ($info['exists']): ?>
                                    <span style="color: green;">✓ <?php _e('Exists', 'indoor-tasks'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php _e('Missing', 'indoor-tasks'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($info['exists']): ?>
                                    <?php foreach ($info['columns'] as $column => $column_exists): ?>
                                        <span style="color: <?php echo $column_exists ? 'green' : 'red'; ?>;">
                                            <?php echo $column_exists ? '✓' : '✗'; ?> <?php echo esc_html($column); ?>
                                        </span><br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php _e('N/A', 'indoor-tasks'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Features Status -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e('Features Status', 'indoor-tasks'); ?></h2>
            </div>
            <div class="inside">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Feature', 'indoor-tasks'); ?></th>
                            <th><?php _e('Status', 'indoor-tasks'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status['features'] as $feature => $feature_status): ?>
                        <tr>
                            <td><?php echo esc_html(str_replace('_', ' ', ucwords($feature))); ?></td>
                            <td>
                                <?php if ($feature_status): ?>
                                    <span style="color: green;">✓ <?php _e('Active', 'indoor-tasks'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php _e('Inactive', 'indoor-tasks'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Files Status -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e('Critical Files Status', 'indoor-tasks'); ?></h2>
            </div>
            <div class="inside">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('File', 'indoor-tasks'); ?></th>
                            <th><?php _e('Status', 'indoor-tasks'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status['files'] as $file => $file_exists): ?>
                        <tr>
                            <td><code><?php echo esc_html($file); ?></code></td>
                            <td>
                                <?php if ($file_exists): ?>
                                    <span style="color: green;">✓ <?php _e('Exists', 'indoor-tasks'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php _e('Missing', 'indoor-tasks'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Summary -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e('Summary', 'indoor-tasks'); ?></h2>
            </div>
            <div class="inside">
                <?php
                $total_checks = 0;
                $passed_checks = 0;
                
                // Count database checks
                foreach ($status['database'] as $table_info) {
                    $total_checks++;
                    if ($table_info['exists']) $passed_checks++;
                    foreach ($table_info['columns'] as $column_exists) {
                        $total_checks++;
                        if ($column_exists) $passed_checks++;
                    }
                }
                
                // Count feature checks
                foreach ($status['features'] as $feature_status) {
                    $total_checks++;
                    if ($feature_status) $passed_checks++;
                }
                
                // Count file checks
                foreach ($status['files'] as $file_exists) {
                    $total_checks++;
                    if ($file_exists) $passed_checks++;
                }
                
                $percentage = $total_checks > 0 ? round(($passed_checks / $total_checks) * 100) : 0;
                ?>
                
                <p><strong><?php _e('Overall System Health:', 'indoor-tasks'); ?></strong> 
                   <?php echo $passed_checks; ?>/<?php echo $total_checks; ?> (<?php echo $percentage; ?>%)</p>
                
                <?php if ($percentage >= 90): ?>
                    <div class="notice notice-success inline">
                        <p><strong><?php _e('Excellent!', 'indoor-tasks'); ?></strong> <?php _e('All major features are properly implemented and functioning.', 'indoor-tasks'); ?></p>
                    </div>
                <?php elseif ($percentage >= 70): ?>
                    <div class="notice notice-warning inline">
                        <p><strong><?php _e('Good!', 'indoor-tasks'); ?></strong> <?php _e('Most features are working, but some issues need attention.', 'indoor-tasks'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-error inline">
                        <p><strong><?php _e('Issues Detected!', 'indoor-tasks'); ?></strong> <?php _e('Several critical features are missing or not functioning properly.', 'indoor-tasks'); ?></p>
                    </div>
                <?php endif; ?>
                
                <h4><?php _e('New Features Implemented:', 'indoor-tasks'); ?></h4>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Client Management System (CRUD, Stats, Task Linking)', 'indoor-tasks'); ?></li>
                    <li><?php _e('Enhanced Task Images (Media Library Integration)', 'indoor-tasks'); ?></li>
                    <li><?php _e('Short Description Field for Tasks', 'indoor-tasks'); ?></li>
                    <li><?php _e('Deadline Management for Tasks', 'indoor-tasks'); ?></li>
                    <li><?php _e('Enhanced Admin Task List with Images and Client Info', 'indoor-tasks'); ?></li>
                    <li><?php _e('Improved Task Submissions Display', 'indoor-tasks'); ?></li>
                    <li><?php _e('Robust Database Migration System', 'indoor-tasks'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

// Add admin menu for system status (only in debug/development)
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_menu', function() {
        add_submenu_page(
            'indoor-tasks',
            __('System Status', 'indoor-tasks'),
            __('System Status', 'indoor-tasks'),
            'manage_options',
            'indoor-tasks-system-status',
            'indoor_tasks_display_system_status'
        );
    });
}
