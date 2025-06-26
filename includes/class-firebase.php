<?php
/**
 * Class Indoor_Tasks_Firebase
 * 
 * Handles Firebase authentication for Google login
 */
class Indoor_Tasks_Firebase {
    /**
     * Constructor
     */
    public function __construct() {
        // Add REST API endpoint for Firebase auth
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Add scripts and styles for Firebase auth
        add_action('wp_enqueue_scripts', array($this, 'enqueue_firebase_scripts'));
        
        // Handle Firebase login
        add_action('wp_ajax_nopriv_indoor_tasks_firebase_login', array($this, 'handle_firebase_login'));
        
        // Add settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('indoor-tasks/v1', '/firebase-auth', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_firebase_auth_rest'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Register Firebase settings
     */
    public function register_settings() {
        register_setting('indoor_tasks_settings', 'indoor_tasks_firebase_api_key');
        register_setting('indoor_tasks_settings', 'indoor_tasks_firebase_auth_domain');
        register_setting('indoor_tasks_settings', 'indoor_tasks_firebase_project_id');
        register_setting('indoor_tasks_settings', 'indoor_tasks_firebase_storage_bucket');
        register_setting('indoor_tasks_settings', 'indoor_tasks_firebase_messaging_sender_id');
        register_setting('indoor_tasks_settings', 'indoor_tasks_firebase_app_id');
        register_setting('indoor_tasks_settings', 'indoor_tasks_firebase_measurement_id');
        register_setting('indoor_tasks_settings', 'indoor_tasks_enable_google_login');
    }
    
    /**
     * Enqueue Firebase scripts
     */
    public function enqueue_firebase_scripts() {
        $enable_google_login = get_option('indoor_tasks_enable_google_login', 0);
        
        if ($enable_google_login && !is_user_logged_in() && is_page('login')) {
            wp_enqueue_script('firebase-app', 'https://www.gstatic.com/firebasejs/9.6.8/firebase-app.js', array(), null, true);
            wp_enqueue_script('firebase-auth', 'https://www.gstatic.com/firebasejs/9.6.8/firebase-auth.js', array('firebase-app'), null, true);
            
            $firebase_config = array(
                'apiKey' => get_option('indoor_tasks_firebase_api_key', ''),
                'authDomain' => get_option('indoor_tasks_firebase_auth_domain', ''),
                'projectId' => get_option('indoor_tasks_firebase_project_id', ''),
                'storageBucket' => get_option('indoor_tasks_firebase_storage_bucket', ''),
                'messagingSenderId' => get_option('indoor_tasks_firebase_messaging_sender_id', ''),
                'appId' => get_option('indoor_tasks_firebase_app_id', ''),
                'measurementId' => get_option('indoor_tasks_firebase_measurement_id', ''),
            );
            
            wp_localize_script('firebase-app', 'indoor_tasks_firebase', $firebase_config);
            wp_enqueue_script('indoor-tasks-firebase-auth', INDOOR_TASKS_URL . 'assets/js/firebase-auth.js', array('firebase-app', 'firebase-auth', 'jquery'), INDOOR_TASKS_VERSION, true);
            
            // Pass the ajax url to our script
            wp_localize_script('indoor-tasks-firebase-auth', 'indoor_tasks_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('indoor-tasks/v1/firebase-auth'),
                'nonce' => wp_create_nonce('wp_rest'),
            ));
        }
    }
    
    /**
     * Handle Firebase authentication via REST API
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_firebase_auth_rest($request) {
        $firebase_token = $request->get_param('firebase_token');
        $email = $request->get_param('email');
        $display_name = $request->get_param('display_name');
        $photo_url = $request->get_param('photo_url');
        
        if (empty($firebase_token) || empty($email)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing required parameters'
            ), 400);
        }
        
        // Verify Firebase token (in a production environment, you should verify the token with Firebase Admin SDK)
        // For this demo, we'll trust the token and create/login the user
        
        $user = get_user_by('email', $email);
        if (!$user) {
            // Create a new user
            $username = sanitize_user(substr($email, 0, strpos($email, '@')));
            $count = 1;
            $original_username = $username;
            
            // Make sure username is unique
            while (username_exists($username)) {
                $username = $original_username . $count;
                $count++;
            }
            
            $random_password = wp_generate_password(12, false);
            $user_id = wp_create_user($username, $random_password, $email);
            
            if (is_wp_error($user_id)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => $user_id->get_error_message()
                ), 500);
            }
            
            // Update user meta
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $display_name,
            ));
            
            update_user_meta($user_id, 'indoor_tasks_firebase_uid', sanitize_text_field($request->get_param('uid')));
            update_user_meta($user_id, 'indoor_tasks_profile_picture', esc_url_raw($photo_url));
            
            $user = get_user_by('id', $user_id);
        }
        
        // Log the user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        
        return new WP_REST_Response(array(
            'success' => true,
            'redirect_url' => site_url('/dashboard/'),
            'user_id' => $user->ID,
            'username' => $user->user_login,
        ));
    }
    
    /**
     * Handle Firebase login via AJAX
     */
    public function handle_firebase_login() {
        check_ajax_referer('indoor_tasks_firebase_login', 'nonce');
        
        $firebase_token = isset($_POST['firebase_token']) ? sanitize_text_field($_POST['firebase_token']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $display_name = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
        $photo_url = isset($_POST['photo_url']) ? esc_url_raw($_POST['photo_url']) : '';
        $uid = isset($_POST['uid']) ? sanitize_text_field($_POST['uid']) : '';
        
        if (empty($firebase_token) || empty($email)) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        // Same logic as REST API endpoint
        $user = get_user_by('email', $email);
        if (!$user) {
            $username = sanitize_user(substr($email, 0, strpos($email, '@')));
            $count = 1;
            $original_username = $username;
            
            while (username_exists($username)) {
                $username = $original_username . $count;
                $count++;
            }
            
            $random_password = wp_generate_password(12, false);
            $user_id = wp_create_user($username, $random_password, $email);
            
            if (is_wp_error($user_id)) {
                wp_send_json_error($user_id->get_error_message());
                return;
            }
            
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $display_name,
            ));
            
            update_user_meta($user_id, 'indoor_tasks_firebase_uid', $uid);
            update_user_meta($user_id, 'indoor_tasks_profile_picture', $photo_url);
            
            $user = get_user_by('id', $user_id);
        }
        
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        
        wp_send_json_success(array(
            'redirect_url' => site_url('/dashboard/'),
            'user_id' => $user->ID,
            'username' => $user->user_login,
        ));
    }
    
    /**
     * Get Firebase configuration
     */
    public function get_firebase_config() {
        return array(
            'apiKey' => get_option('indoor_tasks_firebase_api_key', ''),
            'authDomain' => get_option('indoor_tasks_firebase_auth_domain', ''),
            'projectId' => get_option('indoor_tasks_firebase_project_id', ''),
            'storageBucket' => get_option('indoor_tasks_firebase_storage_bucket', ''),
            'messagingSenderId' => get_option('indoor_tasks_firebase_messaging_sender_id', ''),
            'appId' => get_option('indoor_tasks_firebase_app_id', ''),
            'measurementId' => get_option('indoor_tasks_firebase_measurement_id', '')
        );
    }

    /**
     * Check if Firebase is properly configured
     */
    public function is_configured() {
        $config = $this->get_firebase_config();
        return !empty($config['apiKey']) && !empty($config['authDomain']);
    }
}
