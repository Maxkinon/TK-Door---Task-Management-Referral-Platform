<?php
// Authentication: login, register, OTP, Google login
class Indoor_Tasks_Auth {
    public function __construct() {
        add_action('template_include', [$this, 'route_login_template']);
        // Note: AJAX handlers are now in api/auth-handler.php to avoid conflicts
    }
    public function route_login_template($template) {
        // Use our custom helper function instead of is_page_template
        if (function_exists('is_indoor_tasks_template') && is_indoor_tasks_template('tk-indoor-auth.php')) {
            return INDOOR_TASKS_PATH . 'templates/tk-indoor-auth.php';
        }
        return $template;
    }
}
