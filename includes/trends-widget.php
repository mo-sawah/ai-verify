<?php
/**
 * Public-Facing Trends Widget
 * Shortcode: [ai_verify_trends]
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Trends_Widget {
    
    public static function init() {
        add_shortcode('ai_verify_trends', array(__CLASS__, 'render_widget'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_widget_assets'));
    }
    
    /**
     * Enqueue widget assets
     */
    public static function enqueue_widget_assets() {
        global $post;
        
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'ai_verify_trends')) {
            return;
        }
        
        wp_enqueue_style(
            'ai-verify-trends-widget',
            AI_VERIFY_PLUGIN_URL . 'assets/css/trends-widget.css',
            array(),
            AI_VERIFY_VERSION
        );
        
        wp_enqueue_script(
            'ai-verify-trends-widget',
            AI_VERIFY_PLUGIN_URL . 'assets/js/trends-widget.js',
            array('jquery'),
            AI_VERIFY_VERSION,
            true
        );
        
        wp_localize_script('ai-verify-trends-widget', 'aiVerifyTrendsWidget', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_verify_trends_widget_nonce'),
            'factcheck_url' => get_option('ai_verify_results_page_url', home_url('/fact-check-results/'))
        ));
    }
    
    /**
     * Render widget
     */
    public static function render_widget($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Trending Misinformation',
            'count' => 5,
            'timeframe' => '7days',
            'category' => 'all',
            'show_score' => 'yes',
            'show_category' => 'yes',
            'show_date' => 'yes',
            'theme' => 'light' // light or dark
        ), $atts);
        
        $trending_claims = AI_Verify_Trends_Database::get_trending_claims(
            intval($atts['count']),
            $atts['category'],
            $atts['timeframe']
        );
        
        ob_start();
        ?>
        
        <div class="ai-verify-trends-widget theme-<?php echo esc_attr($atts['theme']); ?>">
            
            <?php if (!empty($atts['title'])): ?>
                <div class="widget-header">
                    <h3 class="widget-title">
                        <span class="title-icon">🔥</span>
                        <?php echo esc_html($atts['title']); ?>
                    </h3>
                    <span class="widget-subtitle">Last <?php echo self::format_timeframe($atts['timeframe']); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="widget-content">
                <?php if (empty($trending_claims)): ?>
                    <div class="widget-empty">
                        <p>No trending claims found for this period.</p>
                    </div>
                <?php else: ?>
                    <div class="trends-list">
                        <?php foreach ($trending_claims as $index => $claim): ?>
                            <div class="trend-item" data-trend-id="<?php echo esc_attr($claim['id']); ?>">
                                <div class="trend-rank">
                                    <span class="rank-number"><?php echo $index + 1; ?></span>
                                </div>
                                
                                <div class="trend-content">
                                    <div class="trend-text">
                                        <?php echo esc_html(self::truncate_text($claim['claim_text'], 120)); ?>
                                    </div>
                                    
                                    <div class="trend-meta">
                                        <?php if ($atts['show_category'] === 'yes' && !empty($claim['category'])): ?>
                                            <span class="meta-item category-tag category-<?php echo esc_attr($claim['category']); ?>">
                                                <?php echo esc_html(ucfirst($claim['category'])); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($atts['show_score'] === 'yes'): ?>
                                            <span class="meta-item credibility-badge score-<?php echo self::get_score_class($claim['avg_credibility_score']); ?>">
                                                <?php echo number_format($claim['avg_credibility_score'], 0); ?>% credible
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="meta-item check-count">
                                            <?php echo number_format($claim['check_count']); ?> checks
                                        </span>
                                        
                                        <?php if ($atts['show_date'] === 'yes'): ?>
                                            <span class="meta-item date">
                                                <?php echo human_time_diff(strtotime($claim['first_seen']), current_time('timestamp')); ?> ago
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button class="fact-check-btn" data-claim="<?php echo esc_attr($claim['claim_text']); ?>">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                        Fact-Check This
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($trending_claims)): ?>
                <div class="widget-footer">
                    <a href="<?php echo esc_url(get_option('ai_verify_results_page_url', home_url('/fact-check-results/'))); ?>" class="view-all-link">
                        View All Trends →
                    </a>
                </div>
            <?php endif; ?>
            
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Helper: Truncate text
     */
    private static function truncate_text($text, $length = 100) {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . '...';
    }
    
    /**
     * Helper: Get score class
     */
    private static function get_score_class($score) {
        if ($score >= 75) return 'high';
        if ($score >= 50) return 'medium';
        if ($score >= 25) return 'low';
        return 'very-low';
    }
    
    /**
     * Helper: Format timeframe
     */
    private static function format_timeframe($timeframe) {
        switch ($timeframe) {
            case '1day': return '24 Hours';
            case '3days': return '3 Days';
            case '7days': return '7 Days';
            case '30days': return '30 Days';
            default: return '7 Days';
        }
    }
}