<?php
/**
 * User level information template
 * 
 * Displays user level information and benefits on the user profile
 */

// Get current user ID
$user_id = get_current_user_id();

// Get user level information
$level_id = get_user_meta($user_id, 'indoor_tasks_user_level', true);

global $wpdb;

// Get level details
$level = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}indoor_task_user_levels WHERE id = %d",
    $level_id
));

// If no level is set, get the lowest level
if (!$level) {
    $level = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}indoor_task_user_levels ORDER BY min_tasks ASC, min_referrals ASC LIMIT 1");
}

// Get next level (if any)
$next_level = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}indoor_task_user_levels 
     WHERE (min_tasks > %d OR min_referrals > %d)
     ORDER BY min_tasks ASC, min_referrals ASC
     LIMIT 1",
    $level->min_tasks,
    $level->min_referrals
));

// Get completed tasks count
$tasks_completed = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions
     WHERE user_id = %d AND status = 'approved'",
    $user_id
));

// Get referrals count
$referrals_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->users WHERE refer_user = %d",
    $user_id
));

// Calculate progress percentage
$tasks_progress = 0;
$referrals_progress = 0;

if ($next_level) {
    $tasks_needed = $next_level->min_tasks - $level->min_tasks;
    $referrals_needed = $next_level->min_referrals - $level->min_referrals;
    
    $tasks_completed_after_current_level = $tasks_completed - $level->min_tasks;
    $referrals_after_current_level = $referrals_count - $level->min_referrals;
    
    if ($tasks_needed > 0) {
        $tasks_progress = min(100, round(($tasks_completed_after_current_level / $tasks_needed) * 100));
    }
    
    if ($referrals_needed > 0) {
        $referrals_progress = min(100, round(($referrals_after_current_level / $referrals_needed) * 100));
    }
}
?>

<div class="indoor-tasks-level-card">
    <div class="level-header" style="background-color: <?php echo esc_attr($level->badge_color); ?>">
        <h3><?php echo esc_html($level->name); ?> Level</h3>
    </div>
    
    <div class="level-body">
        <div class="level-description">
            <?php echo wpautop(esc_html($level->description)); ?>
        </div>
        
        <div class="level-benefits">
            <h4><?php _e('Your Benefits', 'indoor-tasks'); ?></h4>
            <ul>
                <li>
                    <strong><?php _e('Daily Tasks:', 'indoor-tasks'); ?></strong> 
                    <?php echo esc_html($level->max_daily_tasks); ?> <?php _e('tasks per day', 'indoor-tasks'); ?>
                </li>
                <li>
                    <strong><?php _e('Reward Multiplier:', 'indoor-tasks'); ?></strong> 
                    <?php echo esc_html($level->reward_multiplier); ?>x
                </li>
                <li>
                    <strong><?php _e('Withdrawal Time:', 'indoor-tasks'); ?></strong> 
                    <?php echo esc_html($level->withdrawal_time); ?> <?php _e('hours', 'indoor-tasks'); ?>
                </li>
            </ul>
        </div>
        
        <?php if ($next_level): ?>
        <div class="level-progress">
            <h4><?php _e('Progress to Next Level', 'indoor-tasks'); ?></h4>
            <p>
                <?php _e('Next Level:', 'indoor-tasks'); ?> 
                <span class="next-level-badge" style="background-color: <?php echo esc_attr($next_level->badge_color); ?>">
                    <?php echo esc_html($next_level->name); ?>
                </span>
            </p>
            
            <?php if ($next_level->min_tasks > $level->min_tasks): ?>
            <div class="progress-item">
                <div class="progress-label">
                    <?php _e('Tasks:', 'indoor-tasks'); ?> 
                    <?php echo esc_html($tasks_completed); ?>/<?php echo esc_html($next_level->min_tasks); ?>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo esc_attr($tasks_progress); ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($next_level->min_referrals > $level->min_referrals): ?>
            <div class="progress-item">
                <div class="progress-label">
                    <?php _e('Referrals:', 'indoor-tasks'); ?> 
                    <?php echo esc_html($referrals_count); ?>/<?php echo esc_html($next_level->min_referrals); ?>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo esc_attr($referrals_progress); ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.indoor-tasks-level-card {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.level-header {
    padding: 15px;
    color: #fff;
    text-align: center;
}
.level-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}
.level-body {
    padding: 20px;
    background: #fff;
}
.level-description {
    margin-bottom: 15px;
}
.level-benefits ul {
    list-style: none;
    padding: 0;
    margin: 0 0 15px 0;
}
.level-benefits li {
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f0;
}
.next-level-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    color: #fff;
    font-weight: 600;
    font-size: 14px;
}
.progress-item {
    margin-bottom: 10px;
}
.progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 14px;
}
.progress-bar {
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: #4caf50;
}
</style>
