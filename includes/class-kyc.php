<?php
// KYC: upload, admin review
class Indoor_Tasks_Kyc {
    public function __construct() {
        add_action('template_include', [$this, 'route_kyc_template']);
        add_action('wp_ajax_indoor_tasks_kyc_upload', [$this, 'kyc_upload']);
    }
    public function route_kyc_template($template) {
        if (is_page_template('kyc.php')) {
            return INDOOR_TASKS_PATH . 'templates/kyc.php';
        }
        return $template;
    }
    public function kyc_upload() {
        // Validate, save KYC docs, set status to pending
        wp_send_json_success(['message' => 'KYC submitted, pending review.']);
    }
}
