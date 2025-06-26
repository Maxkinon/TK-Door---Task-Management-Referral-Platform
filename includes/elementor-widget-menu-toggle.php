<?php
/**
 * Elementor Menu Toggle Widget for Indoor Tasks
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Indoor_Tasks_Menu_Toggle_Widget extends \Elementor\Widget_Base {

    /**
     * Widget name
     */
    public function get_name() {
        return 'indoor_tasks_menu_toggle';
    }

    /**
     * Widget title
     */
    public function get_title() {
        return __('Indoor Tasks Menu Toggle', 'indoor-tasks');
    }

    /**
     * Widget icon
     */
    public function get_icon() {
        return 'eicon-menu-toggle';
    }

    /**
     * Widget categories
     */
    public function get_categories() {
        return ['general'];
    }

    /**
     * Widget keywords
     */
    public function get_keywords() {
        return ['indoor', 'tasks', 'menu', 'toggle', 'sidebar', 'navigation'];
    }

    /**
     * Widget controls
     */
    protected function _register_controls() {
        
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'indoor-tasks'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'indoor-tasks'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Menu', 'indoor-tasks'),
                'placeholder' => __('Enter button text', 'indoor-tasks'),
            ]
        );

        $this->add_control(
            'button_style',
            [
                'label' => __('Button Style', 'indoor-tasks'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'default',
                'options' => [
                    'default' => __('Default', 'indoor-tasks'),
                    'minimal' => __('Minimal', 'indoor-tasks'),
                    'icon-only' => __('Icon Only', 'indoor-tasks'),
                ],
            ]
        );

        $this->add_control(
            'button_size',
            [
                'label' => __('Button Size', 'indoor-tasks'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'medium',
                'options' => [
                    'small' => __('Small', 'indoor-tasks'),
                    'medium' => __('Medium', 'indoor-tasks'),
                    'large' => __('Large', 'indoor-tasks'),
                ],
            ]
        );

        $this->add_control(
            'show_on',
            [
                'label' => __('Show On', 'indoor-tasks'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'mobile',
                'options' => [
                    'mobile' => __('Mobile Only', 'indoor-tasks'),
                    'desktop' => __('Desktop Only', 'indoor-tasks'),
                    'always' => __('Always', 'indoor-tasks'),
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'indoor-tasks'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'background_color',
            [
                'label' => __('Background Color', 'indoor-tasks'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#00954b',
                'selectors' => [
                    '{{WRAPPER}} .tk-elementor-menu-toggle' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => __('Text Color', 'indoor-tasks'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .tk-elementor-menu-toggle' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'hover_background_color',
            [
                'label' => __('Hover Background Color', 'indoor-tasks'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#02934a',
                'selectors' => [
                    '{{WRAPPER}} .tk-elementor-menu-toggle:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'label' => __('Typography', 'indoor-tasks'),
                'selector' => '{{WRAPPER}} .tk-elementor-menu-toggle',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'label' => __('Border', 'indoor-tasks'),
                'selector' => '{{WRAPPER}} .tk-elementor-menu-toggle',
            ]
        );

        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'indoor-tasks'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 8,
                    'left' => 8,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .tk-elementor-menu-toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'label' => __('Box Shadow', 'indoor-tasks'),
                'selector' => '{{WRAPPER}} .tk-elementor-menu-toggle',
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'indoor-tasks'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .tk-elementor-menu-toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_margin',
            [
                'label' => __('Margin', 'indoor-tasks'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .tk-elementor-menu-toggle' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Widget output
     */
    protected function render() {
        // Only show if user is logged in
        if (!is_user_logged_in()) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div style="padding: 20px; background: #f0f0f0; text-align: center; border-radius: 8px;">';
                echo __('Indoor Tasks Menu Toggle (visible to logged-in users only)', 'indoor-tasks');
                echo '</div>';
            }
            return;
        }

        $settings = $this->get_settings_for_display();
        
        $button_id = 'tk-elementor-toggle-' . $this->get_id();
        
        // Determine display class
        $display_class = 'tk-show-' . esc_attr($settings['show_on']);
        $size_class = 'tk-size-' . esc_attr($settings['button_size']);
        $style_class = 'tk-style-' . esc_attr($settings['button_style']);
        
        ?>
        <button 
            id="<?php echo esc_attr($button_id); ?>" 
            class="tk-elementor-menu-toggle <?php echo esc_attr($display_class . ' ' . $size_class . ' ' . $style_class); ?>"
            data-toggle="indoor-tasks-sidebar"
            aria-label="<?php esc_attr_e('Toggle Navigation Menu', 'indoor-tasks'); ?>"
        >
            <?php if ($settings['button_style'] !== 'icon-only' && !empty($settings['button_text'])): ?>
                <span class="tk-toggle-text"><?php echo esc_html($settings['button_text']); ?></span>
            <?php endif; ?>
            
            <span class="tk-toggle-icon">
                <span class="tk-hamburger-line"></span>
                <span class="tk-hamburger-line"></span>
                <span class="tk-hamburger-line"></span>
            </span>
        </button>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('<?php echo esc_js($button_id); ?>');
            
            if (toggleButton && !toggleButton.hasAttribute('data-initialized')) {
                toggleButton.setAttribute('data-initialized', 'true');
                
                toggleButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Find the Indoor Tasks sidebar
                    const sidebar = document.querySelector('.tk-sidebar-nav');
                    const overlay = document.querySelector('.tk-mobile-overlay');
                    
                    if (sidebar && overlay) {
                        // Toggle the sidebar
                        const isOpen = sidebar.classList.contains('mobile-open');
                        
                        if (isOpen) {
                            // Close sidebar
                            sidebar.classList.remove('mobile-open');
                            overlay.classList.remove('active');
                            toggleButton.classList.remove('active');
                        } else {
                            // Open sidebar
                            sidebar.classList.add('mobile-open');
                            overlay.classList.add('active');
                            toggleButton.classList.add('active');
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Widget content template (for Elementor editor)
     */
    protected function _content_template() {
        ?>
        <#
        var button_id = 'tk-elementor-toggle-' + view.getID();
        var display_class = 'tk-show-' + settings.show_on;
        var size_class = 'tk-size-' + settings.button_size;
        var style_class = 'tk-style-' + settings.button_style;
        #>
        
        <button 
            id="{{ button_id }}" 
            class="tk-elementor-menu-toggle {{ display_class }} {{ size_class }} {{ style_class }}"
            data-toggle="indoor-tasks-sidebar"
        >
            <# if (settings.button_style !== 'icon-only' && settings.button_text) { #>
                <span class="tk-toggle-text">{{{ settings.button_text }}}</span>
            <# } #>
            
            <span class="tk-toggle-icon">
                <span class="tk-hamburger-line"></span>
                <span class="tk-hamburger-line"></span>
                <span class="tk-hamburger-line"></span>
            </span>
        </button>
        <?php
    }
}
