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

// Check if report is still processing
if (!empty($report_data) && in_array($report_data['status'], array('pending', 'processing'))) {
    // Show processing page instead
    include AI_VERIFY_PLUGIN_DIR . 'templates/factcheck-processing.php';
    get_footer();
    return;
}

// Check for processing parameter in URL
if (isset($_GET['processing']) && $_GET['processing'] == '1' && !empty($report_data) && $report_data['status'] !== 'completed') {
    // Show processing page
    include AI_VERIFY_PLUGIN_DIR . 'templates/factcheck-processing.php';
    get_footer();
    return;
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
                        
                        <div class="share-dropdown-wrapper">
                            <button class="share-btn" onclick="toggleShareMenu(event)">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                                </svg>
                                Share
                            </button>
                            <div class="share-menu" style="display: none;">
                                <a href="#" onclick="copyReportLink(event)" class="share-option">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                    Copy Link
                                </a>
                                <a href="#" onclick="shareTwitter(event)" class="share-option">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></svg>
                                    Share on X
                                </a>
                                <a href="#" onclick="shareFacebook(event)" class="share-option">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                    Share on Facebook
                                </a>
                                <a href="#" onclick="shareLinkedIn(event)" class="share-option">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                    Share on LinkedIn
                                </a>
                                <a href="#" onclick="shareEmail(event)" class="share-option">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    Share via Email
                                </a>
                            </div>
                        </div>
                        
                        <button class="recheck-btn" onclick="recheckReport()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Re-check
                        </button>
                    </div>
                </div>
                
                <script>
                    <?php
                    // Prepare a SMALL, safe object for JavaScript, containing only what's needed.
                    $js_report_data = array(
                        'input_type'  => $report_data['input_type'] ?? 'url',
                        'input_value' => $report_data['input_value'] ?? ''
                    );
                    ?>
                    // Inject *only* the small, safe data object
                    window.aiVerifyReportData = <?php echo json_encode($js_report_data); ?>;
                function toggleShareMenu(e) {
                    e.preventDefault();
                    const menu = document.querySelector('.share-menu');
                    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                }
                
                function copyReportLink(e) {
                    e.preventDefault();
                    navigator.clipboard.writeText(window.location.href).then(() => {
                        alert('Link copied to clipboard!');
                        document.querySelector('.share-menu').style.display = 'none';
                    });
                }
                
                function shareTwitter(e) {
                    e.preventDefault();
                    const url = encodeURIComponent(window.location.href);
                    const text = encodeURIComponent('Check out this fact-check report:');
                    window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank', 'width=600,height=400');
                }
                
                function shareFacebook(e) {
                    e.preventDefault();
                    const url = encodeURIComponent(window.location.href);
                    window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank', 'width=600,height=400');
                }
                
                function shareLinkedIn(e) {
                    e.preventDefault();
                    const url = encodeURIComponent(window.location.href);
                    window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${url}`, '_blank', 'width=600,height=400');
                }
                
                function shareEmail(e) {
                    e.preventDefault();
                    const url = encodeURIComponent(window.location.href);
                    const subject = encodeURIComponent('Fact-Check Report');
                    const body = encodeURIComponent(`I thought you might find this fact-check report interesting:\n\n${window.location.href}`);
                    window.location.href = `mailto:?subject=${subject}&body=${body}`;
                }
                
                function recheckReport() {
                    if (!confirm('This will create a new analysis of the same content. Continue?')) {
                        return;
                    }
                    
                    const reportData = window.aiVerifyReportData;
                    if (!reportData || !reportData.input_value) {
                        alert('Unable to recheck: missing report data');
                        return;
                    }
                    
                    // Show loading state
                    const btn = document.querySelector('.recheck-btn');
                    btn.disabled = true;
                    btn.innerHTML = '<svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="animation: spin 1s linear infinite;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" opacity="0.3"></path><path d="M12 2v4c3.31 0 6 2.69 6 6h4c0-5.52-4.48-10-10-10z"></path></svg> Starting...';
                    
                    // Start new fact-check via AJAX
                    jQuery.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'ai_verify_start_factcheck',
                            nonce: '<?php echo wp_create_nonce('ai_verify_factcheck_nonce'); ?>',
                            input_type: reportData.input_type,
                            input_value: reportData.input_value
                        },
                        success: function(response) {
                            if (response.success && response.data.report_url) {
                                window.location.href = response.data.report_url;
                            } else {
                                alert(response.data.message || 'Failed to start recheck');
                                btn.disabled = false;
                                btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Re-check';
                            }
                        },
                        error: function() {
                            alert('Network error. Please try again.');
                            btn.disabled = false;
                            btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Re-check';
                        }
                    });
                }
                
                
                // Close share menu when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.share-dropdown-wrapper')) {
                        const menu = document.querySelector('.share-menu');
                        if (menu) menu.style.display = 'none';
                    }
                });
                </script>
                <style>
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
                </style>
                <?php 
                // UPDATED: Extract all metadata fields including date_modified, domain, favicon
                $url = $report_data['input_value'] ?? '';
                $metadata = $report_data['metadata'] ?? array();
                
                // Get domain and favicon - prioritize metadata
                $domain = $metadata['domain'] ?? '';
                $favicon_url = $metadata['favicon'] ?? '';
                
                // Fallback: extract from URL if not in metadata
                if (empty($domain) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $parsed = parse_url($url);
                    $domain = isset($parsed['host']) ? str_replace('www.', '', $parsed['host']) : '';
                }
                if (empty($favicon_url) && !empty($domain)) {
                    $favicon_url = "https://www.google.com/s2/favicons?domain={$domain}&sz=32";
                }
                
                // Get title - prioritize metadata title
                $title = $metadata['title'] ?? get_the_title();
                if ($title === 'Auto Draft' || empty($title)) {
                    $title = $report_data['input_type'] === 'url' ? $url : $report_data['input_value'];
                }
                
                // Clean up title - remove site names
                if ($title && $title !== $url) {
                    $title = preg_replace('/[\|\-–—]\s*[^|\-–—]*$/', '', $title);
                    $title = trim($title);
                    // Limit length
                    if (mb_strlen($title) > 120) {
                        $title = mb_substr($title, 0, 117) . '...';
                    }
                }
                
                // Get author and dates
                $author = $metadata['author'] ?? '';
                $date_published = $metadata['date'] ?? '';
                $date_modified = $metadata['date_modified'] ?? '';
                
                // Format publish date
                $date_display = '';
                if (!empty($date_published)) {
                    $date_display = date('M j, Y', strtotime($date_published));
                } else {
                    $date_display = get_the_date('M j, Y');
                }
                
                // Format modified date (only if different from published)
                $modified_display = '';
                if (!empty($date_modified) && $date_modified !== $date_published) {
                    $modified_display = date('M j, Y', strtotime($date_modified));
                }
                
                $description = $metadata['description'] ?? $metadata['excerpt'] ?? '';
                
                // Get featured image - prioritize metadata
                $featured_image = $metadata['featured_image'] ?? '';
                
                // Fallback to post thumbnail
                if (empty($featured_image) && has_post_thumbnail()) {
                    $featured_image = get_the_post_thumbnail_url(get_the_ID(), 'large');
                }
                
                // Fallback SVG placeholder if still no image
                $fallback_image = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0ic3lzdGVtLXVpLCAtYXBwbGUtc3lzdGVtLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjE4IiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2UgQXZhaWxhYmxlPC90ZXh0Pjwvc3ZnPg==';
                ?>
                
                <!-- Enhanced Source Article Card with All Metadata -->
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
                        <!-- ALWAYS show image (with fallback) -->
                        <div class="source-article-image">
                            <img src="<?php echo esc_url($featured_image ?: $fallback_image); ?>" 
                                 alt="<?php echo esc_attr($title); ?>" 
                                 onerror="this.src='<?php echo esc_js($fallback_image); ?>'">
                        </div>
                        
                        <div class="source-article-main">
                            <div class="source-article-info">
                                <?php if (filter_var($url, FILTER_VALIDATE_URL)): ?>
                                <a href="<?php echo esc_url($url); ?>" class="source-title-link" target="_blank" rel="noopener noreferrer">
                                    <h4 class="source-title"><?php echo esc_html($title); ?></h4>
                                </a>
                                <?php else: ?>
                                <h4 class="source-title"><?php echo esc_html($title); ?></h4>
                                <?php endif; ?>
                                
                                <?php if (!empty($description)): ?>
                                <p class="source-excerpt"><?php echo esc_html(wp_trim_words($description, 30)); ?></p>
                                <?php endif; ?>
                                
                                <!-- Enhanced metadata display -->
                                <div class="source-meta">
                                    <?php if (!empty($favicon_url)): ?>
                                    <span class="source-favicon-inline">
                                        <img src="<?php echo esc_url($favicon_url); ?>" alt="<?php echo esc_attr($domain); ?>" onerror="this.style.display='none'">
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($domain)): ?>
                                    <span class="source-domain"><?php echo esc_html($domain); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($author)): ?>
                                    <span class="source-separator">•</span>
                                    <span class="source-author">By <?php echo esc_html($author); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($date_display)): ?>
                                    <span class="source-separator">•</span>
                                    <span class="source-date">Published: <?php echo esc_html($date_display); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($modified_display)): ?>
                                    <span class="source-separator">•</span>
                                    <span class="source-date-modified">Updated: <?php echo esc_html($modified_display); ?></span>
                                    <?php endif; ?>
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
                                <circle cx="100" cy="100" r="90" 
                                        stroke="<?php echo $score >= 75 ? '#10b981' : ($score >= 50 ? '#3b82f6' : ($score >= 30 ? '#f59e0b' : '#ef4444')); ?>" 
                                        stroke-width="12" fill="none" stroke-linecap="round" 
                                        transform="rotate(-90 100 100)" 
                                        style="stroke-dasharray: <?php echo $circumference; ?>; stroke-dashoffset: <?php echo $offset; ?>; transition: stroke-dashoffset 1s ease;"></circle>
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
                
                <!-- Claims Analysis Section -->
                <div class="report-section">
                    <div class="section-header-with-filter">
                        <h3 class="section-title">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            Detailed Claims Analysis
                        </h3>
                    </div>
                    
                    <div class="claims-list">
                        <?php if (!empty($report_data['factcheck_results']) && is_array($report_data['factcheck_results'])): ?>
                            <?php foreach ($report_data['factcheck_results'] as $index => $claim): 
                                $rating_lower = strtolower($claim['rating'] ?? 'unknown');
                                $rating_class = 'rating-unknown';
                                
                                if (strpos($rating_lower, 'true') !== false && strpos($rating_lower, 'false') === false) {
                                    $rating_class = 'rating-true';
                                } elseif (strpos($rating_lower, 'false') !== false) {
                                    $rating_class = 'rating-false';
                                } elseif (strpos($rating_lower, 'misleading') !== false || strpos($rating_lower, 'mixed') !== false) {
                                    $rating_class = 'rating-misleading';
                                }
                            ?>
                            <div class="claim-card <?php echo esc_attr($rating_class); ?>">
                                <div class="claim-header">
                                    <div class="claim-rating-badge <?php echo esc_attr($rating_class); ?>">
                                        <?php echo esc_html($claim['rating'] ?? 'Unknown'); ?>
                                    </div>
                                    <div class="claim-confidence">
                                        Confidence: <?php echo esc_html(round(($claim['confidence'] ?? 0.5) * 100)); ?>%
                                    </div>
                                </div>
                                <div class="claim-text">
                                    <?php echo esc_html($claim['claim']); ?>
                                </div>
                                <div class="claim-explanation">
                                    <?php echo wp_kses_post(wpautop($claim['explanation'] ?? 'No explanation available')); ?>
                                </div>
                                
                                <?php if (!empty($claim['sources']) && is_array($claim['sources'])): ?>
                                <div class="claim-sources">
                                    <strong>Sources:</strong>
                                    <ul>
                                        <?php foreach ($claim['sources'] as $source): ?>
                                            <?php if (!empty($source['url']) && !empty($source['name'])): ?>
                                            <li>
                                                <a href="<?php echo esc_url($source['url']); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php echo esc_html($source['name']); ?>
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No claims analyzed yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sources Section -->
                <?php if (!empty($report_data['sources']) && is_array($report_data['sources'])): ?>
                <div class="report-section">
                    <h3 class="section-title">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        Sources Consulted
                    </h3>
                    <div class="sources-list">
                        <?php 
                        $unique_sources = array();
                        foreach ($report_data['sources'] as $source) {
                            $url = $source['url'] ?? '';
                            if (!empty($url) && !isset($unique_sources[$url])) {
                                $unique_sources[$url] = $source;
                            }
                        }
                        
                        foreach ($unique_sources as $source): 
                            $domain = parse_url($source['url'] ?? '', PHP_URL_HOST);
                            $favicon = "https://www.google.com/s2/favicons?domain={$domain}&sz=32";
                        ?>
                        <div class="source-item">
                            <div class="source-icon">
                                <img src="<?php echo esc_url($favicon); ?>" alt="<?php echo esc_attr($domain); ?>" onerror="this.style.display='none'">
                            </div>
                            <div class="source-info">
                                <a href="<?php echo esc_url($source['url']); ?>" target="_blank" rel="noopener noreferrer" class="source-name">
                                    <?php echo esc_html($source['name'] ?? $domain); ?>
                                </a>
                                <div class="source-domain"><?php echo esc_html($domain); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        
    </article>
</div>

<!-- ============================================= -->
<!-- START: NEW CONTENT BLOCKS (Platform & Signup) -->
<!-- ============================================= -->

<!-- Styles for the new blocks -->
<style>
  .bottom-modules-wrapper {
    display: grid;
    /* Creates the 2-column layout on desktop */
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin: 40px auto;
    /* Re-uses the max-width from your report for consistency */
    max-width: 1200px; 
    padding: 0 20px; /* Matches .factcheck-results-wrapper padding */
  }

  /* Stacks the 2 columns into 1 on mobile */
  @media (max-width: 900px) {
    .bottom-modules-wrapper {
      grid-template-columns: 1fr;
      padding: 0; /* Full width on mobile */
    }
  }

  /* We re-use your existing .report-section class for the containers */
  .bottom-modules-wrapper .report-section {
    margin-bottom: 0; 
  }

  /* We re-use your .breakdown-item styles */
  .bottom-modules-wrapper .breakdown-value {
    /* Custom font size for this module's title */
    font-size: 18px; 
  }

  .bottom-modules-wrapper .breakdown-label {
    /* Custom margin for the description text */
    margin-top: 6px; 
    /* Overriding default uppercase for a friendlier description */
    text-transform: none; 
    letter-spacing: normal;
    color: #64748b;
    font-size: 13px;
    font-weight: 500;
  }

  /* We re-use your .form-group and .submit-btn styles from factcheck.css */
  .bottom-modules-wrapper .signup-form .form-group {
    margin-bottom: 20px;
  }
  
  .bottom-modules-wrapper .signup-form label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #374151;
    font-size: 14px;
  }
  
  .bottom-modules-wrapper .signup-form input[type="email"],
  .bottom-modules-wrapper .signup-form input[type="text"] {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 16px;
    background: white;
    transition: all 0.2s ease;
  }

  .bottom-modules-wrapper .signup-form input:focus {
    outline: none;
    border-color: #acd2bf;
    box-shadow: 0 0 0 3px rgba(172, 210, 191, 0.1);
  }
  
  .bottom-modules-wrapper .signup-form .submit-btn {
    width: 100%;
    padding: 16px 0;
    background: linear-gradient(135deg, #acd2bf 0%, #8fc4a8 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 17px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .bottom-modules-wrapper .signup-form .submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(172, 210, 191, 0.4);
  }

  .report-section .submit-btn, .report-section .signup-btn {
    background: linear-gradient(135deg, #acd2bf 0%, #8fc4a8 100%) !important;
    color: #444444 !important;
    padding: 5px 0px !important;
}
</style>

<!-- HTML for the new blocks -->
<div class="bottom-modules-wrapper">
    
    <!-- Block 1: Our Intelligence Platform -->
    <div class="report-section">
      <h3 class="section-title">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        Our Intelligence Platform (IDIP)
      </h3>
      <p class="analyzed-content" style="margin-bottom: 24px;">
        This fact-check is one part of our Integrated Disinformation Intelligence Platform, designed to monitor, analyze, and counter threats.
      </p>
      
      <div class="score-breakdown" style="grid-template-columns: 1fr; gap: 20px;">
        
        <div class="breakdown-item">
          <div class="breakdown-icon">
            <!-- X Monitor Icon -->
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1.5-1.5M17 17l.75 3 1.5-1.5M10 14l-1.5 6m5.5-6l1.5 6M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
          </div>
          <div class="breakdown-content">
            <div class="breakdown-value">X Monitor</div>
            <div class="breakdown-label">Monitor social media disinformation in real-time.</div>
          </div>
        </div>
        
        <div class="breakdown-item">
          <div class="breakdown-icon">
            <!-- News Fact Checker Icon -->
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          </div>
          <div class="breakdown-content">
            <div class="breakdown-value">News Fact Checker</div>
            <div class="breakdown-label">Verify news articles and fact-check claims.</div>
          </div>
        </div>
        
        <div class="breakdown-item">
          <div class="breakdown-icon">
            <!-- OSINT Search Icon -->
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
          </div>
          <div class="breakdown-content">
            <div class="breakdown-value">OSINT Search</div>
            <div class="breakdown-label">Open source intelligence gathering tools.</div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Block 2: Sign Up for Alerts -->
    <div class="report-section">
      <h3 class="section-title">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
        Get Misinformation Alerts
      </h3>
      <p class="analyzed-content" style="margin-bottom: 24px;">
        Stay ahead of false narratives. Sign up for our weekly intelligence briefing on emerging disinformation threats, delivered straight to your inbox.
      </p>
      
      <form class="signup-form">
        <div class="form-group">
          <label for="signup_name">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
            Your Name
          </label>
          <input type="text" id="signup_name" placeholder="John Doe">
        </div>

        <div class="form-group">
          <label for="signup_email">
             <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 10.884l7.997-5H2.003zM0 5v10a2 2 0 002 2h16a2 2 0 002-2V5a2 2 0 00-2-2H2a2 2 0 00-2 2z"></path></svg>
            Your Email
          </label>
          <input type="email" id="signup_email" placeholder="example@email.com">
        </div>

        <button type="submit" class="submit-btn">
          Subscribe Now
        </button>
      </form>
    </div>
    
</div>
<!-- ============================================= -->
<!-- END: NEW CONTENT BLOCKS -->
<!-- ============================================= -->


<?php
// --- START: ADD GOOGLE FACT-CHECK SCHEMA ---

// 1. Prepare key variables from your report data
$schema_metadata = $report_data['metadata'] ?? array();
$original_article_url = $report_data['input_value'] ?? get_permalink();
$original_article_title = $schema_metadata['title'] ?? get_the_title();
$original_article_author = $schema_metadata['author'] ?? 'Unknown';

// Ensure the date is valid and not 'none' before formatting
$original_article_date = '';
if (!empty($schema_metadata['date']) && $schema_metadata['date'] !== 'none') {
    $original_article_date = date('Y-m-d', strtotime($schema_metadata['date']));
}

$overall_score = $report_data['overall_score'] ?? 0;
$overall_rating_text = $report_data['credibility_rating'] ?? 'Unverified';

// Convert your 0-100 score to Google's required 1-5 rating scale
$overall_rating_value = round(($overall_score / 20), 1);
if ($overall_rating_value < 1) { $overall_rating_value = 1; }
if ($overall_rating_value > 5) { $overall_rating_value = 5; }

$site_name = get_bloginfo('name');
$site_url = home_url();
$site_logo = get_site_icon_url(512); // Get site icon for schema

// 2. Build array of all individual claim reviews
$schema_claims = array();
if (!empty($report_data['factcheck_results']) && is_array($report_data['factcheck_results'])) {
    foreach ($report_data['factcheck_results'] as $claim) {
        
        $rating_text = $claim['rating'] ?? 'Unverified';
        $rating_value = 3; // Default for "Mixed", "Unverified"
        $rating_lower = strtolower($rating_text);

        if (strpos($rating_lower, 'true') !== false && strpos($rating_lower, 'false') === false) {
            $rating_text = 'True';
            $rating_value = 5;
        } elseif (strpos($rating_lower, 'false') !== false) {
            $rating_text = 'False';
            $rating_value = 1;
        } elseif (strpos($rating_lower, 'misleading') !== false) {
            $rating_text = 'Misleading';
            $rating_value = 2; // "Misleading" is between Mixed and False
        }

        $schema_claims[] = array(
            '@type'         => 'Claim',
            // The article where the claim appeared
            'appearance'    => array(
                '@type'     => 'ClaimAppearance',
                'url'       => $original_article_url,
                'headline'  => $original_article_title,
                'author'    => $original_article_author,
                'datePublished' => $original_article_date
            ),
            'claimReviewed' => $claim['claim'] ?? '',
            // Your review of that specific claim
            'review'        => array(
                '@type'         => 'ClaimReview',
                'author'        => array(
                    '@type' => 'Organization',
                    'name'  => $site_name,
                    'url'   => $site_url
                ),
                'reviewRating'  => array(
                    '@type'         => 'Rating',
                    'ratingValue'   => $rating_value,
                    'bestRating'    => 5,
                    'worstRating'   => 1,
                    'alternateName' => $rating_text
                ),
                'claimReviewed' => $claim['claim'] ?? '',
                'reviewBody'    => $claim['explanation'] ?? ''
            )
        );
    }
}

// 3. Build the final JSON-LD schema for the whole page
$json_ld_schema = array(
    '@context'  => 'https://schema.org',
    '@type'     => 'FactCheck',
    'url'       => get_permalink(), // The URL of *this* fact-check report
    'datePublished' => get_the_date('c'), // The publish date of *this* fact-check report
    // The author of *this* fact-check report (your organization)
    'author'    => array(
        '@type' => 'Organization',
        'name'  => $site_name,
        'url'   => $site_url,
        'logo'  => array(
            '@type' => 'ImageObject',
            'url' => $site_logo
        )
    ),
    'claimReviewed' => $original_article_title, // The main claim (e.g., the article's title)
    // The item being reviewed (the original article)
    'itemReviewed'  => array(
        '@type' => 'CreativeWork',
        'url'   => $original_article_url,
        'author' => array(
            '@type' => 'Organization', // Can also be 'Person'
            'name'  => $original_article_author
        ),
        'datePublished' => $original_article_date
    ),
    // The overall rating for the *entire* original article
    'reviewRating' => array(
        '@type'         => 'Rating',
        'ratingValue'   => $overall_rating_value,
        'bestRating'    => 5,
        'worstRating'   => 1,
        'alternateName' => $overall_rating_text
    ),
    'relatedClaim'  => $schema_claims // Embed all the individual claims you reviewed
);

// 4. Print the schema in a script tag
if (!empty($schema_claims)) {
    echo '<script type="application/ld+json">' . json_encode($json_ld_schema, JSON_UNESCAPED_SLASHES) . '</script>';
}
// --- END: ADD GOOGLE FACT-CHECK SCHEMA ---
?>
<?php
get_footer();