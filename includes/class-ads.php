<?php
/**
 * Google AdSense handler
 * 
 * Manages the output of Google AdSense ads on the site
 */
class Indoor_Tasks_Ads {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_head', [$this, 'output_adsense_head_code']);
        add_filter('indoor_tasks_before_content', [$this, 'maybe_show_ads_before_content'], 10, 1);
        add_filter('indoor_tasks_after_content', [$this, 'maybe_show_ads_after_content'], 10, 1);
    }
    
    /**
     * Output the AdSense script in the head
     */
    public function output_adsense_head_code() {
        $enable_ads = get_option('indoor_tasks_enable_ads', 1);
        $publisher_id = get_option('indoor_tasks_adsense_publisher_id', '');
        $adsense_code = get_option('indoor_tasks_adsense', '');
        
        // If ads are disabled, don't output anything
        if (!$enable_ads) {
            return;
        }
        
        // If using publisher ID, output the standard AdSense code
        if (!empty($publisher_id)) {
            echo '<!-- Google AdSense Code -->' . "\n";
            echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . esc_attr($publisher_id) . '" crossorigin="anonymous"></script>' . "\n";
        } 
        // Otherwise, output the custom code if provided
        elseif (!empty($adsense_code)) {
            echo '<!-- Google AdSense Custom Code -->' . "\n";
            echo $adsense_code . "\n";
        }
    }
    
    /**
     * Show ads before content if enabled for current page
     * 
     * @param string $content The current content
     * @return string The filtered content
     */
    public function maybe_show_ads_before_content($content) {
        $enable_ads = get_option('indoor_tasks_enable_ads', 1);
        $ad_placement = get_option('indoor_tasks_ad_placement', 'top');
        $current_template = $this->get_current_template();
        $ad_sections = (array) get_option('indoor_tasks_ad_display_sections', []);
        
        // If ads are disabled or not set to show at the top, return content unchanged
        if (!$enable_ads || ($ad_placement !== 'top' && $ad_placement !== 'both')) {
            return $content;
        }
        
        // Check if ads are enabled for this template
        if (!in_array($current_template, $ad_sections)) {
            return $content;
        }
        
        // Get the ad code and add it before the content
        $ad_code = $this->get_ad_code();
        if (!empty($ad_code)) {
            $content = '<div class="indoor-tasks-ad indoor-tasks-ad-top">' . $ad_code . '</div>' . $content;
        }
        
        return $content;
    }
    
    /**
     * Show ads after content if enabled for current page
     * 
     * @param string $content The current content
     * @return string The filtered content
     */
    public function maybe_show_ads_after_content($content) {
        $enable_ads = get_option('indoor_tasks_enable_ads', 1);
        $ad_placement = get_option('indoor_tasks_ad_placement', 'top');
        $current_template = $this->get_current_template();
        $ad_sections = (array) get_option('indoor_tasks_ad_display_sections', []);
        
        // If ads are disabled or not set to show at the bottom, return content unchanged
        if (!$enable_ads || ($ad_placement !== 'bottom' && $ad_placement !== 'both')) {
            return $content;
        }
        
        // Check if ads are enabled for this template
        if (!in_array($current_template, $ad_sections)) {
            return $content;
        }
        
        // Get the ad code and add it after the content
        $ad_code = $this->get_ad_code();
        if (!empty($ad_code)) {
            $content .= '<div class="indoor-tasks-ad indoor-tasks-ad-bottom">' . $ad_code . '</div>';
        }
        
        return $content;
    }
    
    /**
     * Get the current template being used
     * 
     * @return string The current template
     */
    private function get_current_template() {
        global $template;
        
        $template_path = basename($template);
        $template_name = str_replace('.php', '', $template_path);
        
        // Map to the sections we use in settings
        $map = [
            'dashboard' => 'dashboard',
            'task-list' => 'task-list',
            'task-detail' => 'task-detail',
            'wallet' => 'wallet',
            'withdrawal' => 'withdrawal',
        ];
        
        return isset($map[$template_name]) ? $map[$template_name] : '';
    }
    
    /**
     * Get the ad code to display
     * 
     * @return string The ad code
     */
    private function get_ad_code() {
        $publisher_id = get_option('indoor_tasks_adsense_publisher_id', '');
        
        if (!empty($publisher_id)) {
            // Return a responsive AdSense ad unit
            return '<ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="' . esc_attr($publisher_id) . '"
                 data-ad-slot="auto"
                 data-ad-format="auto"
                 data-full-width-responsive="true"></ins>
            <script>
                 (adsbygoogle = window.adsbygoogle || []).push({});
            </script>';
        }
        
        return '';
    }
}
