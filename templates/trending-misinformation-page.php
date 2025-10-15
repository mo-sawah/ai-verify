<?php
/**
 * Professional Trending Misinformation Page Template
 * Shortcode: [ai_verify_trending_page]
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get parameters
$category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : 'all';
$source_filter = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : 'all';
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Get trending claims
$external_claims = AI_Verify_External_Factcheck_Aggregator::get_all_trending(50, $category);

// Get internal claims if any
$internal_claims = array();
if (class_exists('AI_Verify_Trends_Database')) {
    $internal_claims = AI_Verify_Trends_Database::get_trending_claims(10, $category, '7days');
}

// Combine and deduplicate
$all_claims = array_merge($external_claims, $internal_claims);

// Apply search filter
if (!empty($search_query)) {
    $all_claims = array_filter($all_claims, function($claim) use ($search_query) {
        $claim_text = isset($claim['claim']) ? $claim['claim'] : $claim['claim_text'];
        return stripos($claim_text, $search_query) !== false;
    });
}

// Apply source filter
if ($source_filter !== 'all') {
    $all_claims = array_filter($all_claims, function($claim) use ($source_filter) {
        return isset($claim['source']) && strpos(strtolower($claim['source']), $source_filter) !== false;
    });
}

?>

<div class="ai-verify-trending-page">
    
    <!-- Hero Section -->
    <div class="trending-hero">
        <div class="hero-content">
            <h1 class="hero-title">
                <span class="emoji">🔥</span>
                Trending Misinformation
            </h1>
            <p class="hero-subtitle">
                Real-time fact-checks from the world's leading verification organizations
            </p>
        </div>
    </div>
    
    <!-- Search & Filter Bar -->
    <div class="trending-controls">
        <form method="get" class="trending-search-form">
            <div class="search-wrapper">
                <svg class="search-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input 
                    type="text" 
                    name="s" 
                    class="trending-search-input" 
                    placeholder="Search fact-checks..." 
                    value="<?php echo esc_attr($search_query); ?>"
                >
                <button type="submit" class="search-submit-btn">Search</button>
            </div>
            
            <div class="filter-row">
                <select name="category" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?php selected($category, 'all'); ?>>All Categories</option>
                    <option value="politics" <?php selected($category, 'politics'); ?>>Politics</option>
                    <option value="health" <?php selected($category, 'health'); ?>>Health</option>
                    <option value="climate" <?php selected($category, 'climate'); ?>>Climate</option>
                    <option value="technology" <?php selected($category, 'technology'); ?>>Technology</option>
                    <option value="crime" <?php selected($category, 'crime'); ?>>Crime</option>
                    <option value="economy" <?php selected($category, 'economy'); ?>>Economy</option>
                    <option value="immigration" <?php selected($category, 'immigration'); ?>>Immigration</option>
                </select>
                
                <select name="source" onchange="this.form.submit()" class="filter-select">
                    <option value="all" <?php selected($source_filter, 'all'); ?>>All Sources</option>
                    <option value="politifact" <?php selected($source_filter, 'politifact'); ?>>PolitiFact</option>
                    <option value="snopes" <?php selected($source_filter, 'snopes'); ?>>Snopes</option>
                    <option value="factcheck" <?php selected($source_filter, 'factcheck'); ?>>FactCheck.org</option>
                    <option value="afp" <?php selected($source_filter, 'afp'); ?>>AFP Fact Check</option>
                    <option value="full fact" <?php selected($source_filter, 'full fact'); ?>>Full Fact</option>
                    <option value="reuters" <?php selected($source_filter, 'reuters'); ?>>Reuters</option>
                </select>
                
                <?php if ($category !== 'all' || $source_filter !== 'all' || !empty($search_query)): ?>
                    <a href="?" class="clear-filters-btn">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Stats Bar -->
    <div class="trending-stats-bar">
        <div class="stat-item">
            <span class="stat-number"><?php echo count($all_claims); ?></span>
            <span class="stat-label">Fact-Checks</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo count(array_unique(array_column($all_claims, 'source'))); ?></span>
            <span class="stat-label">Sources</span>
        </div>
        <div class="stat-item">
            <span class="stat-number">24/7</span>
            <span class="stat-label">Monitoring</span>
        </div>
    </div>
    
    <!-- Fact-Check Grid -->
    <div class="trending-grid">
        <?php if (empty($all_claims)): ?>
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <h3>No fact-checks found</h3>
                <p>Try adjusting your filters or search terms.</p>
            </div>
        <?php else: ?>
            <?php foreach ($all_claims as $index => $claim): 
                $claim_text = isset($claim['claim']) ? $claim['claim'] : ($claim['claim_text'] ?? '');
                $rating = $claim['rating'] ?? 'Unknown';
                $source = $claim['source'] ?? 'Unknown';
                $url = $claim['url'] ?? '#';
                $date = $claim['date'] ?? '';
                $category_name = $claim['category'] ?? 'general';
                $description = $claim['description'] ?? '';
            ?>
                <div class="fact-check-card">
                    <div class="card-header">
                        <span class="card-category category-<?php echo esc_attr($category_name); ?>">
                            <?php echo esc_html(ucfirst($category_name)); ?>
                        </span>
                        <span class="card-date">
                            <?php echo $date ? human_time_diff(strtotime($date), current_time('timestamp')) . ' ago' : 'Recent'; ?>
                        </span>
                    </div>
                    
                    <div class="card-content">
                        <h3 class="card-title"><?php echo esc_html($claim_text); ?></h3>
                        
                        <?php if (!empty($description)): ?>
                            <p class="card-description"><?php echo esc_html($description); ?></p>
                        <?php endif; ?>
                        
                        <div class="card-rating rating-<?php echo esc_attr(ai_verify_sanitize_rating($rating)); ?>">
                            <span class="rating-icon"><?php echo ai_verify_get_rating_icon($rating); ?></span>
                            <span class="rating-text"><?php echo esc_html($rating); ?></span>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="source-badge">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <?php echo esc_html($source); ?>
                        </div>
                        
                        <a href="<?php echo esc_url($url); ?>" target="_blank" class="read-more-btn">
                            Read More
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- CTA Section -->
    <div class="trending-cta-section">
        <div class="cta-content">
            <h2>🔍 Fact-Check Your Own Claims</h2>
            <p>Use our AI-powered fact-checking tool to verify any claim, URL, or article</p>
            <a href="<?php echo esc_url(home_url('/fact-check-search/')); ?>" class="cta-button">
                Start Fact-Checking →
            </a>
        </div>
    </div>
    
</div>

<?php

// Helper functions
function sanitize_rating($rating) {
    $rating_lower = strtolower($rating);
    if (strpos($rating_lower, 'false') !== false) return 'false';
    if (strpos($rating_lower, 'true') !== false && strpos($rating_lower, 'mostly') === false) return 'true';
    if (strpos($rating_lower, 'mostly true') !== false) return 'mostly-true';
    if (strpos($rating_lower, 'mostly false') !== false) return 'mostly-false';
    if (strpos($rating_lower, 'misleading') !== false || strpos($rating_lower, 'mixture') !== false) return 'misleading';
    return 'unknown';
}

function get_rating_icon($rating) {
    $rating_lower = strtolower($rating);
    if (strpos($rating_lower, 'false') !== false && strpos($rating_lower, 'mostly') === false) return '❌';
    if (strpos($rating_lower, 'true') !== false && strpos($rating_lower, 'mostly') === false) return '✅';
    if (strpos($rating_lower, 'mostly true') !== false) return '✓';
    if (strpos($rating_lower, 'mostly false') !== false) return '✗';
    if (strpos($rating_lower, 'misleading') !== false || strpos($rating_lower, 'mixture') !== false) return '⚠️';
    return '❓';
}