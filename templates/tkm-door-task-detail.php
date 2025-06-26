<?php
/**
 * Template Name: TKM Door - Task Detail
 * Description: Modern task detail template with countdown, submission, and step-by-step guide
 * Version: 2.0.0
 */

// Prevent direct file access
defined('ABSPATH') || exit;

// Redirect if not logged in
if (!is_user_logged_in()) {
    $login_page = indoor_tasks_get_page_by_template('indoor-tasks/templates/tk-indoor-auth.php', 'login');
    if ($login_page) {
        wp_redirect(get_permalink($login_page->ID));
    } else {
        wp_redirect(home_url('/login/'));
    }
    exit;
}

// Get current user info
$user_id = get_current_user_id();

// Get task ID from URL parameter
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

if (!$task_id) {
    wp_redirect(home_url());
    exit;
}

// Get database references
global $wpdb;

// Check if tables exist
$tasks_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_tasks'") === $wpdb->prefix . 'indoor_tasks';
$submissions_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_submissions'") === $wpdb->prefix . 'indoor_task_submissions';
$categories_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_categories'") === $wpdb->prefix . 'indoor_task_categories';
$failures_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_failures'") === $wpdb->prefix . 'indoor_task_failures';

if (!$tasks_table_exists) {
    wp_die('Tasks system not properly configured.');
}

// Get task details with client information
$task = null;
try {
    $join_query = "FROM {$wpdb->prefix}indoor_tasks t";
    if ($categories_table_exists) {
        $join_query .= " LEFT JOIN {$wpdb->prefix}indoor_task_categories c ON t.category_id = c.id";
    }
    
    // Check if clients table exists and join
    $clients_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_clients'") === $wpdb->prefix . 'indoor_task_clients';
    if ($clients_table_exists) {
        $join_query .= " LEFT JOIN {$wpdb->prefix}indoor_task_clients cl ON t.client_id = cl.id";
    }
    
    $select_fields = "t.*";
    if ($categories_table_exists) {
        $select_fields .= ", c.name as category_name, c.color as category_color";
    }
    if ($clients_table_exists) {
        $select_fields .= ", cl.name as client_name";
    }
    
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT {$select_fields} {$join_query} WHERE t.id = %d AND t.status = 'active'",
        $task_id
    ));
} catch (Exception $e) {
    // Fallback
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}indoor_tasks WHERE id = %d AND status = 'active'",
        $task_id
    ));
}

if (!$task) {
    wp_die('Task not found or no longer available.');
}

// Get user's submission for this task
$user_submission = null;
if ($submissions_table_exists) {
    try {
        $user_submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}indoor_task_submissions WHERE task_id = %d AND user_id = %d ORDER BY submitted_at DESC LIMIT 1",
            $task_id,
            $user_id
        ));
    } catch (Exception $e) {
        // Continue without submission data
    }
}

// Get user's failure count for this task
$failure_count = 0;
if ($failures_table_exists) {
    try {
        $failure_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_failures WHERE task_id = %d AND user_id = %d",
            $task_id,
            $user_id
        ));
    } catch (Exception $e) {
        $failure_count = 0;
    }
}

// Check if user is banned from this task (3+ failures)
$is_banned = $failure_count >= 3;

// Get or set task start time from user meta
$task_start_meta_key = 'task_start_' . $task_id;
$task_start_time = get_user_meta($user_id, $task_start_meta_key, true);
$task_started = !empty($task_start_time);

// Handle AJAX actions
if ($_POST && isset($_POST['action'])) {
    $response = array();
    
    switch ($_POST['action']) {
        case 'start_task':
            if (!$task_started && !$is_banned && wp_verify_nonce($_POST['nonce'], 'start_task_' . $task_id)) {
                $start_time = current_time('timestamp');
                update_user_meta($user_id, $task_start_meta_key, $start_time);
                $response['success'] = true;
                $response['start_time'] = $start_time;
                $response['duration'] = intval($task->duration ?? 30) * 60; // Convert minutes to seconds
            } else {
                $response['success'] = false;
                $response['message'] = 'Cannot start task';
            }
            break;
            
        case 'submit_task':
            if ($task_started && !$is_banned && wp_verify_nonce($_POST['nonce'], 'submit_task_' . $task_id)) {
                $proof_text = sanitize_textarea_field($_POST['proof_text'] ?? '');
                
                // Handle file upload
                $proof_file = '';
                if (!empty($_FILES['proof_file']['tmp_name'])) {
                    $upload_dir = wp_upload_dir();
                    $upload_path = $upload_dir['basedir'] . '/indoor-tasks-proofs/';
                    
                    if (!file_exists($upload_path)) {
                        wp_mkdir_p($upload_path);
                    }
                    
                    $file_extension = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
                    $filename = 'proof_' . $task_id . '_' . $user_id . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_path . $filename;
                    
                    // Check file size (5MB max)
                    if ($_FILES['proof_file']['size'] <= 5 * 1024 * 1024) {
                        if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $file_path)) {
                            $proof_file = $upload_dir['baseurl'] . '/indoor-tasks-proofs/' . $filename;
                        }
                    }
                }
                
                if (!empty($proof_text) || !empty($proof_file)) {
                    try {
                        if ($user_submission && $user_submission->status === 'pending') {
                            // Update existing submission
                            $result = $wpdb->update(
                                $wpdb->prefix . 'indoor_task_submissions',
                                array(
                                    'proof_text' => $proof_text,
                                    'proof_file' => $proof_file,
                                    'status' => 'pending',
                                    'submitted_at' => current_time('mysql')
                                ),
                                array('id' => $user_submission->id),
                                array('%s', '%s', '%s', '%s'),
                                array('%d')
                            );
                        } else {
                            // Create new submission
                            $result = $wpdb->insert(
                                $wpdb->prefix . 'indoor_task_submissions',
                                array(
                                    'task_id' => $task_id,
                                    'user_id' => $user_id,
                                    'proof_text' => $proof_text,
                                    'proof_file' => $proof_file,
                                    'status' => 'pending',
                                    'submitted_at' => current_time('mysql')
                                ),
                                array('%d', '%d', '%s', '%s', '%s', '%s')
                            );
                        }
                        
                        if ($result !== false) {
                            // Clear the task start time
                            delete_user_meta($user_id, $task_start_meta_key);
                            
                            $response['success'] = true;
                            $response['message'] = 'Task submitted successfully! Your submission is now pending review.';
                        } else {
                            $response['success'] = false;
                            $response['message'] = 'Failed to submit task. Please try again.';
                        }
                    } catch (Exception $e) {
                        $response['success'] = false;
                        $response['message'] = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $response['success'] = false;
                    $response['message'] = 'Please provide proof text or upload an image.';
                }
            }
            break;
    }
    
    if (isset($response)) {
        wp_send_json($response);
        exit;
    }
}

// Helper function to get task image
function get_task_detail_image($task) {
    // Check task_image_id first (WordPress attachment)
    if (!empty($task->task_image_id)) {
        $image_url = wp_get_attachment_url($task->task_image_id);
        if ($image_url) {
            return $image_url;
        }
    }
    
    // Check task_image field
    if (!empty($task->task_image)) {
        if (filter_var($task->task_image, FILTER_VALIDATE_URL)) {
            return $task->task_image;
        }
        if (strpos($task->task_image, '/') === 0 || strpos($task->task_image, 'assets/') === 0) {
            return INDOOR_TASKS_URL . ltrim($task->task_image, '/');
        }
    }
    
    // Check other image fields
    if (!empty($task->image)) {
        if (filter_var($task->image, FILTER_VALIDATE_URL)) {
            return $task->image;
        }
        if (is_numeric($task->image)) {
            $attachment_url = wp_get_attachment_url($task->image);
            if ($attachment_url) {
                return $attachment_url;
            }
        }
    }
    
    // Default placeholder based on category color
    if (!empty($task->category_color)) {
        return 'data:image/svg+xml;base64,' . base64_encode('
            <svg width="400" height="250" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:' . $task->category_color . ';stop-opacity:1" />
                        <stop offset="100%" style="stop-color:' . $task->category_color . '88;stop-opacity:1" />
                    </linearGradient>
                </defs>
                <rect width="400" height="250" fill="url(#grad1)"/>
                <text x="200" y="135" font-family="Arial, sans-serif" font-size="64" fill="white" text-anchor="middle" font-weight="bold">
                    ' . strtoupper(substr($task->title ?: 'T', 0, 1)) . '
                </text>
            </svg>
        ');
    }
    
    // Default task icon
    return INDOOR_TASKS_URL . 'assets/image/task-placeholder.jpg';
}

// Parse step by step guide
$step_by_step_guide = array();
if (!empty($task->step_by_step_guide)) {
    try {
        $guide_data = json_decode($task->step_by_step_guide, true);
        if (is_array($guide_data)) {
            $step_by_step_guide = $guide_data;
        }
    } catch (Exception $e) {
        // Continue without step guide
    }
}
?>
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#00954b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo esc_html($task->title); ?> - <?php echo wp_get_document_title(); ?></title>
    
    <?php wp_head(); ?>
    
    <!-- TKM Door Task Detail Styles -->
    <link rel="stylesheet" href="<?php echo INDOOR_TASKS_URL; ?>assets/css/tkm-door-task-detail.css?ver=2.1.0">
</head>
<body class="tkm-door-task-detail">
    <div class="tkm-task-detail-container">
        <!-- Include Sidebar -->
        <?php include INDOOR_TASKS_PATH . 'templates/parts/sidebar-nav.php'; ?>
        
        <div class="tkm-task-detail-content">
            <!-- Back Navigation -->
            <div class="tkm-back-nav">
                <button onclick="history.back()" class="tkm-back-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                    Back to Tasks
                </button>
            </div>
            
            <!-- Feature Image with Overlay Content -->
            <div class="tkm-hero-section">
                <div class="tkm-hero-image">
                    <img src="<?php echo esc_url(get_task_detail_image($task)); ?>" alt="<?php echo esc_attr($task->title); ?>" />
                </div>
                <div class="tkm-hero-overlay">
                    <div class="tkm-hero-content">
                        <div class="tkm-hero-badges">
                            <?php if (!empty($task->category_name)): ?>
                                <span class="tkm-category-badge" style="background-color: <?php echo esc_attr($task->category_color ?: '#e5e7eb'); ?>;">
                                    <?php echo esc_html($task->category_name); ?>
                                </span>
                            <?php endif; ?>
                            <div class="tkm-points-badge">
                                +<?php echo number_format($task->reward_points ?? 0); ?> Points
                            </div>
                        </div>
                        
                        <h1 class="tkm-hero-title"><?php echo esc_html($task->title); ?></h1>
                        
                        <?php if (!empty($task->short_description)): ?>
                            <p class="tkm-hero-description"><?php echo esc_html($task->short_description); ?></p>
                        <?php endif; ?>
                        
                        <div class="tkm-hero-meta">
                            <div class="tkm-meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <span><?php echo esc_html($task->duration ?? '30'); ?> min</span>
                            </div>
                            
                            <?php if (!empty($task->deadline)): ?>
                            <div class="tkm-meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                <span><?php echo date('M j', strtotime($task->deadline)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ban/Failure Message -->
            <?php if ($is_banned): ?>
                <div class="tkm-ban-message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M15 9l-6 6"/>
                        <path d="M9 9l6 6"/>
                    </svg>
                    <h3>Task No Longer Available</h3>
                    <p>You are no longer eligible to do this task due to 3 failed attempts.</p>
                </div>
            <?php elseif ($user_submission && $user_submission->status === 'approved'): ?>
                <div class="tkm-success-message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <h3>Task Completed Successfully!</h3>
                    <p>Your submission has been approved. Points have been added to your account.</p>
                </div>
            <?php elseif ($user_submission && $user_submission->status === 'pending'): ?>
                <div class="tkm-pending-message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <h3>Submission Under Review</h3>
                    <p>Your task submission is currently being reviewed by our team.</p>
                </div>
            <?php elseif ($failure_count > 0 && $failure_count < 3): ?>
                <div class="tkm-warning-message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <h3>Previous Attempts: <?php echo $failure_count; ?>/3</h3>
                    <p>You have <?php echo (3 - $failure_count); ?> attempt(s) remaining for this task.</p>
                </div>
            <?php endif; ?>
            
            <!-- Short Description Section (removed as it's now in hero) -->
            
            <!-- Full Description -->
            <div class="tkm-content-section">
                <h2>About This Task</h2>
                <div class="tkm-description-content">
                    <?php if (!empty($task->description)): ?>
                        <?php echo wp_kses_post(wpautop($task->description)); ?>
                    <?php else: ?>
                        <p>Complete this task to earn <?php echo number_format($task->reward_points ?? 0); ?> points.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- How to Complete This Task -->
            <?php if (!empty($step_by_step_guide) && is_array($step_by_step_guide)): ?>
            <div class="tkm-content-section tkm-steps-section">
                <h2>How to Complete This Task</h2>
                <div class="tkm-steps-container">
                    <?php foreach ($step_by_step_guide as $index => $step): ?>
                        <?php if (!empty($step['title']) || !empty($step['description'])): ?>
                        <div class="tkm-step-item">
                            <div class="tkm-step-number"><?php echo ($index + 1); ?></div>
                            <div class="tkm-step-content">
                                <?php if (!empty($step['title'])): ?>
                                    <h3><?php echo esc_html($step['title']); ?></h3>
                                <?php endif; ?>
                                <?php if (!empty($step['description'])): ?>
                                    <p><?php echo esc_html($step['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Guide Resources -->
            <?php if (!empty($task->guide_link) || !empty($task->video_link)): ?>
            <div class="tkm-content-section tkm-resources-section">
                <h2>Helpful Resources</h2>
                
                <?php if (!empty($task->video_link)): ?>
                <div class="tkm-video-embed">
                    <h3>Video Guide</h3>
                    <?php 
                    $video_url = $task->video_link;
                    // Convert YouTube URLs to embed format
                    if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches);
                        if (!empty($matches[1])) {
                            $video_id = $matches[1];
                            $embed_url = "https://www.youtube.com/embed/{$video_id}";
                            echo '<iframe src="' . esc_url($embed_url) . '" frameborder="0" allowfullscreen></iframe>';
                        }
                    } else {
                        echo '<iframe src="' . esc_url($video_url) . '" frameborder="0" allowfullscreen></iframe>';
                    }
                    ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($task->guide_link)): ?>
                <div class="tkm-guide-link">
                    <h3>Additional Guide</h3>
                    <a href="<?php echo esc_url($task->guide_link); ?>" target="_blank" class="tkm-external-link">
                        View Complete Guide
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            <polyline points="15 3 21 3 21 9"/>
                            <line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Task Action Section -->
            <?php if (!$is_banned && (!$user_submission || $user_submission->status === 'rejected')): ?>
            <div class="tkm-content-section tkm-action-section">
                <?php if (!$task_started): ?>
                    <!-- Start Task Button -->
                    <div class="tkm-start-section">
                        <h2>Ready to Begin?</h2>
                        <p>Start your <?php echo esc_html($task->duration ?? '30'); ?>-minute timer and get access to all task resources.</p>
                        <button id="start-task-btn" class="tkm-start-btn" data-task-id="<?php echo $task_id; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                            Start Task Now
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Timer Section -->
                    <div class="tkm-timer-section">
                        <h2>Task in Progress</h2>
                        <div class="tkm-countdown-timer" id="countdown-timer" 
                             data-start-time="<?php echo $task_start_time; ?>" 
                             data-duration="<?php echo intval($task->duration ?? 30) * 60; ?>">
                            <div class="tkm-timer-circle">
                                <div class="tkm-timer-display">
                                    <span id="timer-minutes">00</span>:<span id="timer-seconds">00</span>
                                </div>
                                <div class="tkm-timer-label">Time Remaining</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Task Links -->
                    <div class="tkm-task-links">
                        <?php if (!empty($task->task_link)): ?>
                        <a href="<?php echo esc_url($task->task_link); ?>" target="_blank" class="tkm-task-link tkm-primary-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                            Complete Task Here
                        </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($task->guide_link)): ?>
                        <a href="<?php echo esc_url($task->guide_link); ?>" target="_blank" class="tkm-guide-link-btn tkm-secondary-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            View Guide
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Submission Form -->
                    <div class="tkm-submission-section">
                        <h2>Submit Your Proof</h2>
                        <form id="task-submission-form" enctype="multipart/form-data">
                            <div class="tkm-form-group">
                                <label for="proof-text">Describe what you completed <span class="tkm-required">*</span></label>
                                <textarea id="proof-text" name="proof_text" rows="4" 
                                         placeholder="Tell us about your task completion..."></textarea>
                            </div>
                            
                            <div class="tkm-form-group">
                                <label for="proof-file">Upload Screenshot <span class="tkm-optional">(Optional, Max 5MB)</span></label>
                                <div class="tkm-file-upload">
                                    <input type="file" id="proof-file" name="proof_file" accept="image/*">
                                    <div class="tkm-file-upload-display">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                        <span>Click to upload or drag image here</span>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="tkm-submit-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13"/>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                </svg>
                                Submit Task
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="tkm-loading-overlay" style="display: none;">
        <div class="tkm-loading-spinner"></div>
        <p>Processing...</p>
    </div>
    
    <!-- Success/Error Messages -->
    <div id="message-container" class="tkm-message-container"></div>
    
    <?php wp_footer(); ?>
    
    <!-- TKM Door Task Detail Scripts -->
    <script>
        // Pass PHP variables to JavaScript
        window.tkmTaskDetail = {
            taskId: <?php echo $task_id; ?>,
            userId: <?php echo $user_id; ?>,
            taskStarted: <?php echo $task_started ? 'true' : 'false'; ?>,
            taskStartTime: <?php echo $task_start_time ? $task_start_time : 'null'; ?>,
            taskDuration: <?php echo intval($task->duration ?? 30) * 60; ?>,
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonces: {
                startTask: '<?php echo wp_create_nonce('start_task_' . $task_id); ?>',
                submitTask: '<?php echo wp_create_nonce('submit_task_' . $task_id); ?>'
            }
        };
    </script>
    <script src="<?php echo INDOOR_TASKS_URL; ?>assets/js/tkm-door-task-detail.js?ver=2.0.0"></script>
</body>
</html>
