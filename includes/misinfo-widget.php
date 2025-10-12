<?php
/**
 * Misinformation Tracker Widget
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Misinfo_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'ai_verify_misinfo_widget',
            '⚠️ Misinformation Tracker',
            array(
                'description' => 'Display latest confirmed false claims and misinformation'
            )
        );
    }
    
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Latest False Claims';
        $limit = !empty($instance['limit']) ? absint($instance['limit']) : 5;
        $show_filter = !empty($instance['show_filter']) ? 1 : 0;
        $link_url = !empty($instance['link_url']) ? $instance['link_url'] : '';
        
        echo $args['before_widget'];
        
        $this->render_widget($title, $limit, $show_filter, $link_url);
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Latest False Claims';
        $limit = !empty($instance['limit']) ? absint($instance['limit']) : 5;
        $show_filter = !empty($instance['show_filter']) ? 1 : 0;
        $link_url = !empty($instance['link_url']) ? $instance['link_url'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Title:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>">Number of items:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('limit')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('limit')); ?>" 
                   type="number" value="<?php echo esc_attr($limit); ?>" min="1" max="20">
        </p>
        <p>
            <label>
                <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_filter')); ?>" 
                       name="<?php echo esc_attr($this->get_field_name('show_filter')); ?>" 
                       value="1" <?php checked($show_filter, 1); ?>>
                Show filter badges
            </label>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('link_url')); ?>">"View All" Link URL:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('link_url')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('link_url')); ?>" 
                   type="url" value="<?php echo esc_url($link_url); ?>" 
                   placeholder="https://yourmainsite.com">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = (!empty($new_instance['limit'])) ? absint($new_instance['limit']) : 5;
        $instance['show_filter'] = (!empty($new_instance['show_filter'])) ? 1 : 0;
        $instance['link_url'] = (!empty($new_instance['link_url'])) ? esc_url_raw($new_instance['link_url']) : '';
        return $instance;
    }
    
    private function render_widget($title, $limit, $show_filter, $link_url) {
        $widget_id = 'misinfo-widget-' . uniqid();
        ?>
        <div class="misinfo-widget" id="<?php echo esc_attr($widget_id); ?>" data-limit="<?php echo esc_attr($limit); ?>">
            <div class="widget-header">
                <h3>
                    <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <?php echo esc_html($title); ?>
                </h3>
                <div class="live-indicator">
                    <span class="live-dot"></span>
                    <span>Live Updates</span>
                </div>
            </div>

            <?php if ($show_filter): ?>
            <div class="widget-filter">
                <span class="filter-badge all active" data-filter="all">All</span>
                <span class="filter-badge false" data-filter="false">False</span>
                <span class="filter-badge misleading" data-filter="misleading">Misleading</span>
            </div>
            <?php endif; ?>

            <div class="widget-content">
                <div class="misinfo-loading">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" class="spin">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" opacity="0.3"></path>
                        <path d="M12 2v4c3.31 0 6 2.69 6 6h4c0-5.52-4.48-10-10-10z"></path>
                    </svg>
                    Loading latest misinformation...
                </div>
            </div>

            <?php if (!empty($link_url)): ?>
            <div class="widget-footer">
                <a href="<?php echo esc_url($link_url); ?>" class="view-all-btn" target="_blank" rel="noopener">View All Misinformation →</a>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Shortcode handler
function ai_verify_misinfo_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => 'Latest Confirmed Misinformation',
        'limit' => 10,
        'show_filter' => 'yes',
        'link_url' => '',
        'layout' => 'full'
    ), $atts);
    
    $show_filter = ($atts['show_filter'] === 'yes') ? 1 : 0;
    $widget_id = 'misinfo-widget-' . uniqid();
    
    ob_start();
    ?>
    <div class="misinfo-widget-container <?php echo esc_attr($atts['layout']); ?>">
        <div class="misinfo-widget" id="<?php echo esc_attr($widget_id); ?>" data-limit="<?php echo esc_attr($atts['limit']); ?>">
            <div class="widget-header">
                <h3>
                    <svg class="header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <?php echo esc_html($atts['title']); ?>
                </h3>
                <div class="live-indicator">
                    <span class="live-dot"></span>
                    <span>Live Updates</span>
                </div>
            </div>

            <?php if ($show_filter): ?>
            <div class="widget-filter">
                <span class="filter-badge all active" data-filter="all">All</span>
                <span class="filter-badge false" data-filter="false">False</span>
                <span class="filter-badge misleading" data-filter="misleading">Misleading</span>
            </div>
            <?php endif; ?>

            <div class="widget-content">
                <div class="misinfo-loading">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" class="spin">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" opacity="0.3"></path>
                        <path d="M12 2v4c3.31 0 6 2.69 6 6h4c0-5.52-4.48-10-10-10z"></path>
                    </svg>
                    Loading latest misinformation...
                </div>
            </div>

            <?php if (!empty($atts['link_url'])): ?>
            <div class="widget-footer">
                <a href="<?php echo esc_url($atts['link_url']); ?>" class="view-all-btn" target="_blank" rel="noopener">View All Misinformation →</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('misinfo_tracker', 'ai_verify_misinfo_shortcode');

// Register widget
function ai_verify_register_misinfo_widget() {
    register_widget('AI_Verify_Misinfo_Widget');
}
add_action('widgets_init', 'ai_verify_register_misinfo_widget');