<?php
/**
 * Admin Dashboard for Misinformation Trends
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Trends_Admin {
    
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'), 21);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        add_action('wp_ajax_ai_verify_get_trends_data', array(__CLASS__, 'ajax_get_trends_data'));
    }
    
    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Misinformation Trends',
            'Misinfo Trends',
            'manage_options',
            'ai-verify-trends',
            array(__CLASS__, 'render_dashboard_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        if ('settings_page_ai-verify-trends' !== $hook) {
            return;
        }
        
        // Chart.js for visualizations
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );
        
        wp_enqueue_style(
            'ai-verify-trends-admin',
            AI_VERIFY_PLUGIN_URL . 'assets/css/trends-admin.css',
            array(),
            AI_VERIFY_VERSION
        );
        
        wp_enqueue_script(
            'ai-verify-trends-admin',
            AI_VERIFY_PLUGIN_URL . 'assets/js/trends-admin.js',
            array('jquery', 'chartjs'),
            AI_VERIFY_VERSION,
            true
        );
        
        wp_localize_script('ai-verify-trends-admin', 'aiVerifyTrends', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_verify_trends_nonce')
        ));
    }
    
    /**
     * Render dashboard page
     */
    public static function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get filter parameters
        $timeframe = isset($_GET['timeframe']) ? sanitize_text_field($_GET['timeframe']) : '7days';
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : 'all';
        
        // Get data
        $trending_claims = AI_Verify_Trends_Database::get_trending_claims(10, $category, $timeframe);
        $category_breakdown = AI_Verify_Trends_Database::get_category_breakdown($timeframe);
        $top_domains = AI_Verify_Trends_Database::get_top_domains(10, $timeframe);
        $propaganda_heatmap = AI_Verify_Trends_Database::get_propaganda_heatmap($timeframe);
        
        // Get statistics
        $stats = self::get_dashboard_stats($timeframe);
        
        ?>
        <div class="wrap ai-verify-trends-dashboard">
            <h1 class="wp-heading-inline">📊 Misinformation Trends Dashboard</h1>
            <hr class="wp-header-end">
            
            <!-- Filters -->
            <div class="trends-filters">
                <form method="get" class="filter-form">
                    <input type="hidden" name="page" value="ai-verify-trends">
                    
                    <select name="timeframe" onchange="this.form.submit()">
                        <option value="1day" <?php selected($timeframe, '1day'); ?>>Last 24 Hours</option>
                        <option value="3days" <?php selected($timeframe, '3days'); ?>>Last 3 Days</option>
                        <option value="7days" <?php selected($timeframe, '7days'); ?>>Last 7 Days</option>
                        <option value="30days" <?php selected($timeframe, '30days'); ?>>Last 30 Days</option>
                    </select>
                    
                    <select name="category" onchange="this.form.submit()">
                        <option value="all" <?php selected($category, 'all'); ?>>All Categories</option>
                        <option value="politics" <?php selected($category, 'politics'); ?>>Politics</option>
                        <option value="health" <?php selected($category, 'health'); ?>>Health</option>
                        <option value="climate" <?php selected($category, 'climate'); ?>>Climate</option>
                        <option value="technology" <?php selected($category, 'technology'); ?>>Technology</option>
                        <option value="economics" <?php selected($category, 'economics'); ?>>Economics</option>
                        <option value="crime" <?php selected($category, 'crime'); ?>>Crime</option>
                        <option value="other" <?php selected($category, 'other'); ?>>Other</option>
                    </select>
                </form>
            </div>
            
            <!-- Statistics Cards -->
            <div class="trends-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dbeafe;">
                        <span style="color: #2563eb;">📈</span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['total_checks']); ?></div>
                        <div class="stat-label">Total Fact-Checks</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fef3c7;">
                        <span style="color: #f59e0b;">🔥</span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['unique_claims']); ?></div>
                        <div class="stat-label">Unique Claims</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dcfce7;">
                        <span style="color: #16a34a;">✓</span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['avg_credibility'], 1); ?>%</div>
                        <div class="stat-label">Avg Credibility</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fee2e2;">
                        <span style="color: #dc2626;">⚠️</span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['low_credibility']); ?></div>
                        <div class="stat-label">Low Credibility (<40%)</div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="trends-content-grid">
                
                <!-- Left Column: Trending Claims -->
                <div class="trends-section trending-claims-section">
                    <h2>🔥 Top Trending Misinformation</h2>
                    
                    <?php if (empty($trending_claims)): ?>
                        <div class="no-data">
                            <p>No trending claims found for this timeframe.</p>
                            <p>Claims will appear here once users start fact-checking content.</p>
                        </div>
                    <?php else: ?>
                        <div class="trending-claims-list">
                            <?php foreach ($trending_claims as $index => $claim): ?>
                                <div class="trending-claim-card">
                                    <div class="claim-rank">#<?php echo $index + 1; ?></div>
                                    <div class="claim-content">
                                        <div class="claim-header">
                                            <span class="claim-category category-<?php echo esc_attr($claim['category']); ?>">
                                                <?php echo esc_html(ucfirst($claim['category'])); ?>
                                            </span>
                                            <span class="claim-checks">
                                                <?php echo number_format($claim['check_count']); ?> checks
                                            </span>
                                        </div>
                                        
                                        <div class="claim-text">
                                            <?php echo esc_html($claim['claim_text']); ?>
                                        </div>
                                        
                                        <div class="claim-meta">
                                            <div class="credibility-badge score-<?php echo self::get_score_class($claim['avg_credibility_score']); ?>">
                                                <span class="score-value"><?php echo number_format($claim['avg_credibility_score'], 1); ?>%</span>
                                                <span class="score-label">Credibility</span>
                                            </div>
                                            
                                            <div class="claim-stats">
                                                <span class="stat">
                                                    <strong>Velocity:</strong> <?php echo number_format($claim['velocity_score'], 2); ?>/day
                                                </span>
                                                <span class="stat">
                                                    <strong>First Seen:</strong> <?php echo human_time_diff(strtotime($claim['first_seen']), current_time('timestamp')); ?> ago
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($claim['keywords'])): ?>
                                            <div class="claim-keywords">
                                                <?php foreach (array_slice($claim['keywords'], 0, 5) as $keyword): ?>
                                                    <span class="keyword-tag"><?php echo esc_html($keyword); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Column: Analytics -->
                <div class="trends-section analytics-section">
                    
                    <!-- Category Breakdown -->
                    <div class="chart-container">
                        <h3>📊 Category Breakdown</h3>
                        <canvas id="categoryChart" width="400" height="300"></canvas>
                        <script>
                            var categoryData = <?php echo json_encode($category_breakdown); ?>;
                        </script>
                    </div>
                    
                    <!-- Top Domains -->
                    <div class="top-domains-container">
                        <h3>🌐 Most Fact-Checked Domains</h3>
                        <?php if (empty($top_domains)): ?>
                            <p class="no-data-small">No domain data available</p>
                        <?php else: ?>
                            <div class="domains-list">
                                <?php foreach ($top_domains as $index => $domain): ?>
                                    <div class="domain-item">
                                        <span class="domain-rank"><?php echo $index + 1; ?>.</span>
                                        <span class="domain-name"><?php echo esc_html($domain['source_domain']); ?></span>
                                        <span class="domain-count"><?php echo number_format($domain['check_count']); ?> checks</span>
                                        <span class="domain-score score-<?php echo self::get_score_class($domain['avg_credibility']); ?>">
                                            <?php echo number_format($domain['avg_credibility'], 1); ?>%
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Propaganda Heatmap -->
                    <div class="propaganda-heatmap-container">
                        <h3>🎭 Propaganda Techniques Detected</h3>
                        <?php if (empty($propaganda_heatmap)): ?>
                            <p class="no-data-small">No propaganda data available</p>
                        <?php else: ?>
                            <div class="heatmap-list">
                                <?php 
                                $max_count = max($propaganda_heatmap);
                                foreach (array_slice($propaganda_heatmap, 0, 10, true) as $technique => $count): 
                                    $intensity = ($count / $max_count) * 100;
                                ?>
                                    <div class="heatmap-item">
                                        <span class="technique-name"><?php echo esc_html($technique); ?></span>
                                        <div class="technique-bar">
                                            <div class="bar-fill" style="width: <?php echo $intensity; ?>%;"></div>
                                        </div>
                                        <span class="technique-count"><?php echo number_format($count); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>
            
            <!-- Credibility Timeline Chart -->
            <div class="trends-section full-width">
                <h2>📈 Credibility Score Timeline</h2>
                <canvas id="timelineChart" width="100%" height="60"></canvas>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Get dashboard statistics
     */
    private static function get_dashboard_stats($timeframe) {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        $date_threshold = self::get_date_threshold($timeframe);
        
        $total_checks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_instances WHERE checked_at >= %s",
            $date_threshold
        ));
        
        $unique_claims = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_trends WHERE last_seen >= %s",
            $date_threshold
        ));
        
        $avg_credibility = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(credibility_score) FROM $table_instances WHERE checked_at >= %s",
            $date_threshold
        ));
        
        $low_credibility = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_instances WHERE checked_at >= %s AND credibility_score < 40",
            $date_threshold
        ));
        
        return array(
            'total_checks' => intval($total_checks),
            'unique_claims' => intval($unique_claims),
            'avg_credibility' => floatval($avg_credibility),
            'low_credibility' => intval($low_credibility)
        );
    }
    
    /**
     * AJAX: Get trends data for charts
     */
    public static function ajax_get_trends_data() {
        check_ajax_referer('ai_verify_trends_nonce', 'nonce');
        
        $timeframe = isset($_POST['timeframe']) ? sanitize_text_field($_POST['timeframe']) : '7days';
        $data_type = isset($_POST['data_type']) ? sanitize_text_field($_POST['data_type']) : 'timeline';
        
        if ($data_type === 'timeline') {
            $days = $timeframe === '30days' ? 30 : 7;
            $data = AI_Verify_Trends_Database::get_credibility_timeline($days);
        } elseif ($data_type === 'categories') {
            $data = AI_Verify_Trends_Database::get_category_breakdown($timeframe);
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Helper: Get score class for styling
     */
    private static function get_score_class($score) {
        if ($score >= 75) return 'high';
        if ($score >= 50) return 'medium';
        if ($score >= 25) return 'low';
        return 'very-low';
    }
    
    /**
     * Helper: Get date threshold
     */
    private static function get_date_threshold($timeframe) {
        switch ($timeframe) {
            case '1day': return date('Y-m-d H:i:s', strtotime('-1 day'));
            case '3days': return date('Y-m-d H:i:s', strtotime('-3 days'));
            case '7days': return date('Y-m-d H:i:s', strtotime('-7 days'));
            case '30days': return date('Y-m-d H:i:s', strtotime('-30 days'));
            default: return date('Y-m-d H:i:s', strtotime('-7 days'));
        }
    }
}