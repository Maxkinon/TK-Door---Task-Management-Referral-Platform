<?php
// Wallet: points, history, admin add/remove
class Indoor_Tasks_Wallet {
    public function __construct() {
        add_action('template_include', [$this, 'route_wallet_template']);
    }
    public function route_wallet_template($template) {
        if (is_page_template('wallet.php')) {
            return INDOOR_TASKS_PATH . 'templates/wallet.php';
        }
        if (is_page_template('withdrawal.php')) {
            return INDOOR_TASKS_PATH . 'templates/withdrawal.php';
        }
        if (is_page_template('withdrawal-history.php')) {
            return INDOOR_TASKS_PATH . 'templates/withdrawal-history.php';
        }
        return $template;
    }
    // Add wallet logic here
}
