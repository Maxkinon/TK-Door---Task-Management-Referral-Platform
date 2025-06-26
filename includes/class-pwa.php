<?php
// PWA support: manifest, service worker
class Indoor_Tasks_Pwa {
    public function __construct() {
        add_action('wp_head', [$this, 'add_manifest']);
        add_action('wp_footer', [$this, 'add_preloader']);
    }
    public function add_manifest() {
        echo '<link rel="manifest" href="' . INDOOR_TASKS_URL . 'assets/pwa/manifest.json">';
    }
    public function add_preloader() {
        include INDOOR_TASKS_PATH . 'templates/loader.php';
    }
}
