<?php
/**
 * This file will create the necessary PWA icon directories if they don't exist.
 * 
 * Usage: Include this file in the main plugin to ensure the directory structure exists.
 */

// Base path for icons
$icons_path = plugin_dir_path(__FILE__) . 'assets/image/icons';

// Create the directory if it doesn't exist
if (!file_exists($icons_path)) {
    wp_mkdir_p($icons_path);
}

// Icon sizes needed for the PWA
$icon_sizes = [
    '72x72',
    '96x96',
    '128x128',
    '144x144',
    '152x152',
    '192x192',
    '384x384',
    '512x512'
];

// Source icon (we'll use the verified.png as a base if no icons exist)
$source_icon = plugin_dir_path(__FILE__) . 'assets/image/verified.png';

// Check if we need to generate icons
$need_generation = false;

foreach ($icon_sizes as $size) {
    $icon_file = $icons_path . '/icon-' . $size . '.png';
    if (!file_exists($icon_file)) {
        $need_generation = true;
        break;
    }
}

// Display admin notice if icons need to be generated
if ($need_generation && is_admin()) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>Indoor Tasks:</strong> PWA icons are missing. Please add icon images to the plugin's assets/image/icons/ directory for a complete PWA experience.</p>
        </div>
        <?php
    });
}
