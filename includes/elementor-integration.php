<?php
/**
 * Elementor Integration for Indoor Tasks
 * Provides shortcodes and widgets for Elementor compatibility
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register shortcode for mobile sidebar toggle button
 */
function indoor_tasks_mobile_toggle_shortcode($atts) {
    // Parse attributes
    $atts = shortcode_atts(array(
        'style' => 'default', // default, minimal, icon-only
        'size' => 'medium',   // small, medium, large
        'text' => __('Menu', 'indoor-tasks'),
        'color' => '#00954b',
        'text_color' => '#ffffff',
        'show_on' => 'mobile', // mobile, always, desktop
    ), $atts, 'indoor_tasks_menu_toggle');

    // Only show if user is logged in
    if (!is_user_logged_in()) {
        return '';
    }

    // Generate unique ID for this button
    $button_id = 'tk-elementor-toggle-' . wp_rand(1000, 9999);
    
    // Determine when to show the button
    $display_class = '';
    switch ($atts['show_on']) {
        case 'mobile':
            $display_class = 'tk-show-mobile-only';
            break;
        case 'desktop':
            $display_class = 'tk-show-desktop-only';
            break;
        case 'always':
        default:
            $display_class = 'tk-show-always';
            break;
    }

    // Size classes
    $size_class = 'tk-size-' . esc_attr($atts['size']);
    
    // Style classes
    $style_class = 'tk-style-' . esc_attr($atts['style']);

    ob_start();
    ?>
    
    <button 
        id="<?php echo esc_attr($button_id); ?>" 
        class="tk-elementor-menu-toggle <?php echo esc_attr($display_class . ' ' . $size_class . ' ' . $style_class); ?>"
        style="--tk-primary-color: <?php echo esc_attr($atts['color']); ?>; --tk-text-color: <?php echo esc_attr($atts['text_color']); ?>; background-color: <?php echo esc_attr($atts['color']); ?> !important; color: <?php echo esc_attr($atts['text_color']); ?> !important;"
        data-toggle="indoor-tasks-sidebar"
        aria-label="<?php esc_attr_e('Toggle Navigation Menu', 'indoor-tasks'); ?>"
    >
        <?php if ($atts['style'] !== 'icon-only'): ?>
            <span class="tk-toggle-text"><?php echo esc_html($atts['text']); ?></span>
        <?php endif; ?>
        
        <span class="tk-toggle-icon">
            <span class="tk-hamburger-line"></span>
            <span class="tk-hamburger-line"></span>
            <span class="tk-hamburger-line"></span>
        </span>
    </button>

    <style>
    /* Elementor Toggle Button Styles */
    .tk-elementor-menu-toggle {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--tk-primary-color, #00954b);
        color: var(--tk-text-color, #ffffff);
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-family: inherit;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        outline: none;
        position: relative;
        overflow: hidden;
    }

    .tk-elementor-menu-toggle:hover {
        opacity: 0.8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .tk-elementor-menu-toggle:active {
        transform: translateY(0);
    }

    /* Size variations */
    .tk-elementor-menu-toggle.tk-size-small {
        padding: 8px 12px;
        font-size: 12px;
        min-height: 36px;
    }

    .tk-elementor-menu-toggle.tk-size-medium {
        padding: 10px 16px;
        font-size: 14px;
        min-height: 44px;
    }

    .tk-elementor-menu-toggle.tk-size-large {
        padding: 12px 20px;
        font-size: 16px;
        min-height: 52px;
    }

    /* Style variations */
    .tk-elementor-menu-toggle.tk-style-minimal {
        background: transparent !important;
        color: var(--tk-primary-color, #00954b) !important;
        border: 2px solid var(--tk-primary-color, #00954b) !important;
    }

    .tk-elementor-menu-toggle.tk-style-minimal:hover {
        background: var(--tk-primary-color, #00954b) !important;
        color: var(--tk-text-color, #ffffff) !important;
    }

    .tk-elementor-menu-toggle.tk-style-icon-only {
        padding: 10px;
        border-radius: 50%;
        width: 44px;
        height: 44px;
        justify-content: center;
    }

    .tk-elementor-menu-toggle.tk-style-icon-only.tk-size-small {
        width: 36px;
        height: 36px;
        padding: 8px;
    }

    .tk-elementor-menu-toggle.tk-style-icon-only.tk-size-large {
        width: 52px;
        height: 52px;
        padding: 12px;
    }

    /* Hamburger icon */
    .tk-toggle-icon {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: 20px;
        height: 16px;
        position: relative;
    }

    .tk-hamburger-line {
        display: block;
        width: 20px;
        height: 2px;
        background: currentColor;
        margin: 2px 0;
        transition: all 0.3s ease;
        border-radius: 1px;
    }

    .tk-elementor-menu-toggle.active .tk-hamburger-line:nth-child(1) {
        transform: rotate(45deg) translate(6px, 6px);
    }

    .tk-elementor-menu-toggle.active .tk-hamburger-line:nth-child(2) {
        opacity: 0;
    }

    .tk-elementor-menu-toggle.active .tk-hamburger-line:nth-child(3) {
        transform: rotate(-45deg) translate(6px, -6px);
    }

    /* Display controls */
    .tk-show-mobile-only {
        display: none;
    }

    .tk-show-desktop-only {
        display: inline-flex;
    }

    .tk-show-always {
        display: inline-flex;
    }

    @media (max-width: 768px) {
        .tk-show-mobile-only {
            display: inline-flex;
        }
        
        .tk-show-desktop-only {
            display: none;
        }
    }

    /* Ripple effect */
    .tk-elementor-menu-toggle::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .tk-elementor-menu-toggle:active::before {
        width: 300px;
        height: 300px;
    }
    </style>

    <script>
    // The toggle functionality is now handled globally by sidebar-nav.php
    // This ensures all toggle buttons work consistently
    console.log('Indoor Tasks menu toggle loaded:', '<?php echo esc_js($button_id); ?>');
    </script>
    
    <?php
    return ob_get_clean();
}
add_shortcode('indoor_tasks_menu_toggle', 'indoor_tasks_mobile_toggle_shortcode');

/**
 * Register shortcode for user info display in Elementor
 */
function indoor_tasks_user_info_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '';
    }

    $atts = shortcode_atts(array(
        'show' => 'name', // name, avatar, points, both
        'size' => 'medium',
        'color' => '#333333',
    ), $atts, 'indoor_tasks_user_info');

    $current_user = wp_get_current_user();
    $user_id = get_current_user_id();
    
    // Get user points
    global $wpdb;
    $user_points = 0;
    $wallet_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_wallet'") === $wpdb->prefix . 'indoor_task_wallet';
    if ($wallet_table_exists) {
        try {
            $points_result = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(points) FROM {$wpdb->prefix}indoor_task_wallet WHERE user_id = %d",
                $user_id
            ));
            $user_points = $points_result ? intval($points_result) : 0;
        } catch (Exception $e) {
            $user_points = get_user_meta($user_id, 'indoor_tasks_points', true) ?: 0;
        }
    }

    ob_start();
    ?>
    <div class="tk-elementor-user-info tk-show-<?php echo esc_attr($atts['show']); ?> tk-size-<?php echo esc_attr($atts['size']); ?>" 
         style="--tk-text-color: <?php echo esc_attr($atts['color']); ?>;">
        
        <?php if (in_array($atts['show'], ['avatar', 'both'])): ?>
            <div class="tk-user-avatar-display">
                <?php echo get_avatar($user_id, 32, '', '', array('class' => 'tk-avatar')); ?>
            </div>
        <?php endif; ?>
        
        <?php if (in_array($atts['show'], ['name', 'both'])): ?>
            <div class="tk-user-details">
                <span class="tk-user-name"><?php echo esc_html($current_user->display_name); ?></span>
                <?php if (in_array($atts['show'], ['points', 'both'])): ?>
                    <span class="tk-user-points"><?php echo number_format($user_points); ?> <?php _e('Points', 'indoor-tasks'); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($atts['show'] === 'points'): ?>
            <div class="tk-points-only">
                <span class="tk-points-label"><?php _e('Points:', 'indoor-tasks'); ?></span>
                <span class="tk-points-value"><?php echo number_format($user_points); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .tk-elementor-user-info {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--tk-text-color, #333333);
        font-family: inherit;
    }

    .tk-elementor-user-info .tk-avatar {
        border-radius: 50%;
        border: 2px solid #00954b;
    }

    .tk-elementor-user-info.tk-size-small .tk-avatar {
        width: 24px;
        height: 24px;
    }

    .tk-elementor-user-info.tk-size-medium .tk-avatar {
        width: 32px;
        height: 32px;
    }

    .tk-elementor-user-info.tk-size-large .tk-avatar {
        width: 40px;
        height: 40px;
    }

    .tk-user-details {
        display: flex;
        flex-direction: column;
    }

    .tk-user-name {
        font-weight: 600;
        font-size: 14px;
        line-height: 1.2;
    }

    .tk-user-points {
        font-size: 12px;
        color: #00954b;
        font-weight: 500;
    }

    .tk-points-only {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .tk-points-label {
        font-size: 14px;
        font-weight: 500;
    }

    .tk-points-value {
        font-size: 16px;
        font-weight: 700;
        color: #00954b;
    }

    @media (max-width: 768px) {
        .tk-elementor-user-info.tk-size-large {
            font-size: 14px;
        }
        
        .tk-user-name {
            font-size: 13px;
        }
    }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('indoor_tasks_user_info', 'indoor_tasks_user_info_shortcode');

/**
 * Custom Elementor Widget for Indoor Tasks Menu Toggle
 */
if (did_action('elementor/loaded')) {
    add_action('elementor/widgets/widgets_registered', 'register_indoor_tasks_elementor_widgets');
}

function register_indoor_tasks_elementor_widgets() {
    require_once(INDOOR_TASKS_PATH . 'includes/elementor-widget-menu-toggle.php');
    \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new \Indoor_Tasks_Menu_Toggle_Widget());
}
