<?php
/**
 * Single Fact-Check Report Template
 * Displays a completed fact-check report as a WordPress post
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Get report data from post meta
$report_id = get_post_meta(get_the_ID(), 'report_id', true);
$report_json = get_post_meta(get_the_ID(), 'report_data', true);
$report_data = json_decode($report_json, true);

if (empty($report_data)) {
    // Fallback: try to get from database
    $report_data = AI_Verify_Factcheck_Database::get_report($report_id);
}

?>

<div class="ai-verify-report-wrapper">
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        
        <div class="factcheck-results-wrapper" id="factcheckResults">
            <div class="factcheck-report" id="factcheckReport" style="display: block;">
                
                <div class="report-header">
                    <div class="report-meta">
                        <span class="report-id">Report ID: <strong><?php echo esc_html($report_id); ?></strong></span>
                        <span class="report-date">Generated: <strong><?php echo esc_html(get_the_date('M j, Y g:i A')); ?></strong></span>
                    </div>
                    
                    <div class="report-actions">
                        <button class="export-btn" onclick="window.print()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print Report
                        </button>
                        <button class="share-btn" onclick="navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied!'))">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                            </svg>
                            Share
                        </button>
                    </div>
                </div>
                
                <?php if ($report_data['input_type'] === 'url' && !empty($report_data['input_value'])): 
                    $url = $report_data['input_value'];
                    $parsed = parse_url($url);
                    $domain = isset($parsed['host']) ? str_replace('www.', '', $parsed['host']) : '';
                    $favicon_url = "https://www.google.com/s2/favicons?domain={$domain}&sz=32";
                    
                    // Extract title from scraped content
                    $title = $url;
                    if (!empty($report_data['scraped_content'])) {
                        if (preg_match('/<title>(.*?)<\/title>/i', $report_data['scraped_content'], $match)) {
                            $title = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
                        }
                    }
                    
                    // Get featured image
                    $featured_image = '';
                    if (has_post_thumbnail()) {
                        $featured_image = get_the_post_thumbnail_url(get_the_ID(), 'medium');
                    }
                ?>
                
                <!-- Source Article Card -->
                <div class="source-article-card">
                    <div class="source-article-header">
                        <div class="source-article-icon">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3>Analyzed Content</h3>
                    </div>
                    <div class="source-article-content">
                        <?php if ($featured_image): ?>
                        <div class="source-article-image">
                            <img src="<?php echo esc_url($featured_image); ?>" alt="<?php echo esc_attr($title); ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="source-article-main">
                            <div class="source-article-info">
                                <a href="<?php echo esc_url($url); ?>" class="source-title-link" target="_blank" rel="noopener noreferrer">
                                    <h4 class="source-title"><?php echo esc_html($title); ?></h4>
                                </a>
                                <div class="source-meta">
                                    <span class="source-favicon-inline">
                                        <img src="<?php echo esc_url($favicon_url); ?>" alt="<?php echo esc_attr($domain); ?>" onerror="this.style.display='none'">
                                    </span>
                                    <span class="source-domain"><?php echo esc_html($domain); ?></span>
                                    <span class="source-separator">•</span>
                                    <span class="source-date"><?php echo esc_html(get_the_date('M j, Y')); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="source-article-badge">
                            <?php
                            $score = round($report_data['overall_score']);
                            $rating = $report_data['credibility_rating'];
                            $badge_class = '';
                            if ($score >= 75) $badge_class = 'badge-high';
                            elseif ($score >= 50) $badge_class = 'badge-medium';
                            elseif ($score >= 25) $badge_class = 'badge-low';
                            else $badge_class = 'badge-very-low';
                            
                            $short_rating = $rating;
                            if ($rating === 'Mostly Credible') $short_rating = 'Credible';
                            elseif ($rating === 'Mixed Credibility') $short_rating = 'Mixed';
                            ?>
                            <div class="credibility-badge <?php echo esc_attr($badge_class); ?>">
                                <div class="badge-score"><?php echo esc_html($score); ?></div>
                                <div class="badge-rating"><?php echo esc_html($short_rating); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php endif; ?>
                
                <!-- Score Card -->
                <div class="report-score-card">
                    <?php
                    $score = round($report_data['overall_score']);
                    $circumference = 2 * pi() * 90;
                    $offset = $circumference - ($score / 100) * $circumference;
                    
                    $score_class = '';
                    if ($score >= 75) $score_class = 'score-high';
                    elseif ($score >= 50) $score_class = 'score-medium';
                    elseif ($score >= 25) $score_class = 'score-low';
                    else $score_class = 'score-very-low';
                    
                    $rating_class = '';
                    if ($score >= 75) $rating_class = 'rating-high';
                    elseif ($score >= 50) $rating_class = 'rating-medium';
                    elseif ($score >= 25) $rating_class = 'rating-low';
                    else $rating_class = 'rating-very-low';
                    ?>
                    
                    <div class="score-visual">
                        <div class="score-circle <?php echo esc_attr($score_class); ?>">
                            <svg class="score-ring" width="200" height="200">
                                <circle cx="100" cy="100" r="90" stroke="#e5e5e5" stroke-width="12" fill="none"></circle>
                                <circle cx="100" cy="100" r="90" stroke-width="12" fill="none" stroke-linecap="round" 
                                        transform="rotate(-90 100 100)" 
                                        style="stroke-dasharray: <?php echo $circumference; ?>; stroke-dashoffset: <?php echo $offset; ?>;"></circle>
                            </svg>
                            <div class="score-number">
                                <span><?php echo esc_html($score); ?></span>
                                <span class="score-label">Overall Score</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="score-details">
                        <div class="credibility-rating-display">
                            <div class="rating-badge-large <?php echo esc_attr($rating_class); ?>">
                                <svg class="rating-icon" width="28" height="28" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path>
                                </svg>
                                <span><?php echo esc_html($report_data['credibility_rating']); ?></span>
                            </div>
                        </div>
                        
                        <div class="analysis-summary">
                            <p class="analyzed-content"><?php echo esc_html($report_data['input_value']); ?></p>
                        </div>
                        
                        <?php
                        $claims_count = count($report_data['factcheck_results'] ?? array());
                        $sources_count = count($report_data['sources'] ?? array());
                        $analysis_time = '';
                        if (!empty($report_data['created_at']) && !empty($report_data['completed_at'])) {
                            $diff = strtotime($report_data['completed_at']) - strtotime($report_data['created_at']);
                            $analysis_time = round($diff) . 's';
                        }
                        $method = !empty($report_data['factcheck_results'][0]['method']) ? $report_data['factcheck_results'][0]['method'] : 'Multiple Sources';
                        ?>
                        
                        <div class="score-breakdown">
                            <div class="breakdown-item">
                                <div class="breakdown-icon">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="breakdown-content">
                                    <div class="breakdown-label">Claims Analyzed</div>
                                    <div class="breakdown-value"><?php echo esc_html($claims_count); ?></div>
                                </div>
                            </div>
                            <div class="breakdown-item">
                                <div class="breakdown-icon">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                </div>
                                <div class="breakdown-content">
                                    <div class="breakdown-label">Sources Verified</div>
                                    <div class="breakdown-value"><?php echo esc_html($sources_count); ?></div>
                                </div>
                            </div>
                            <div class="breakdown-item">
                                <div class="breakdown-icon">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="breakdown-content">
                                    <div class="breakdown-label">Analysis Time</div>
                                    <div class="breakdown-value"><?php echo esc_html($analysis_time); ?></div>
                                </div>
                            </div>
                            <div class="breakdown-item">
                                <div class="breakdown-icon">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                    </svg>
                                </div>
                                <div class="breakdown-content">
                                    <div class="breakdown-label">Method</div>
                                    <div class="breakdown-value"><?php echo esc_html($method); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php 
                // Display propaganda warning if techniques detected
                $propaganda = $report_data['metadata']['propaganda_techniques'] ?? array();
                if (!empty($propaganda)):
                ?>
                <div class="propaganda-warning">
                    <div class="warning-header">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <h3>⚠️ Propaganda Techniques Detected</h3>
                    </div>
                    <ul class="propaganda-list">
                        <?php foreach ($propaganda as $technique): ?>
                        <li><?php echo esc_html($technique); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Rest of the report template would continue here with claims, sources, etc. -->
                <!-- For brevity, using the shortcode renderer -->
                <div class="report-content-body">
                    <?php the_content(); ?>
                </div>
                
            </div>
        </div>
        
    </article>
</div>

<script>
// Inject report data for any JavaScript that needs it
window.aiVerifyReportData = <?php echo json_encode($report_data); ?>;
</script>

<?php
get_footer();