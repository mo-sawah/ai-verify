<?php
/**
 * Intelligence Dashboard Template
 * Completely new design replacing old trending page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get initial data
$stats = AI_Verify_Intelligence_Dashboard::get_dashboard_stats();
$claims = AI_Verify_Intelligence_Dashboard::get_dashboard_data(array());
?>

<div class="ai-verify-intelligence-dashboard">
    
    <!-- Live Stats Bar -->
    <div class="dashboard-stats-bar">
        <div class="stat-item">
            <div class="stat-icon">🔥</div>
            <div class="stat-content">
                <div class="stat-value" id="statActiveClaims"><?php echo number_format($stats['active_claims']); ?></div>
                <div class="stat-label">Active Claims</div>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">⚡</div>
            <div class="stat-content">
                <div class="stat-value" id="statViralClaims"><?php echo number_format($stats['viral_claims']); ?></div>
                <div class="stat-label">Viral Now</div>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">✓</div>
            <div class="stat-content">
                <div class="stat-value" id="statVerified"><?php echo number_format($stats['verified_claims']); ?></div>
                <div class="stat-label">Verified</div>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <div class="stat-value" id="statChecksPerHour"><?php echo number_format($stats['checks_per_hour'], 1); ?>K</div>
                <div class="stat-label">Checks/Hour</div>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">⚠️</div>
            <div class="stat-content">
                <div class="stat-value" id="statHighAlert"><?php echo number_format($stats['high_alert']); ?></div>
                <div class="stat-label">High Alert</div>
            </div>
        </div>
    </div>
    
    <!-- Search & Filters -->
    <div class="dashboard-controls">
        <div class="search-wrapper">
            <svg class="search-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input 
                type="text" 
                id="dashboardSearch" 
                class="dashboard-search-input" 
                placeholder="Search claims, keywords, sources..."
                autocomplete="off"
            >
        </div>
        
        <div class="filter-row">
            <select class="filter-select" data-filter="category">
                <option value="all">All Categories</option>
                <option value="politics">Politics</option>
                <option value="health">Health</option>
                <option value="climate">Climate</option>
                <option value="technology">Technology</option>
                <option value="crime">Crime</option>
                <option value="economy">Economy</option>
                <option value="general">General</option>
            </select>
            
            <select class="filter-select" data-filter="platform">
                <option value="all">All Platforms</option>
                <option value="twitter">Twitter</option>
                <option value="rss">RSS Feeds</option>
                <option value="google">Google Fact Check</option>
                <option value="internal">User Checks</option>
            </select>
            
            <select class="filter-select" data-filter="velocity">
                <option value="all">All Velocity</option>
                <option value="viral">🔥 Viral</option>
                <option value="emerging">⚡ Emerging</option>
                <option value="active">📈 Active</option>
                <option value="slow">🐌 Slow</option>
            </select>
            
            <select class="filter-select" data-filter="timeframe">
                <option value="1day">Last 24 Hours</option>
                <option value="3days">Last 3 Days</option>
                <option value="7days" selected>Last 7 Days</option>
                <option value="30days">Last 30 Days</option>
            </select>
            
            <button id="refreshButton" class="filter-select" style="cursor: pointer; background: var(--accent-primary); color: white; border: none;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </button>
        </div>
        
        <div class="filter-chips">
            <div class="filter-chip active" data-filter-type="velocity" data-filter-value="all">All</div>
            <div class="filter-chip" data-filter-type="velocity" data-filter-value="viral">🔥 Viral</div>
            <div class="filter-chip" data-filter-type="velocity" data-filter-value="emerging">⚡ Emerging</div>
            <div class="filter-chip" data-filter-type="velocity" data-filter-value="active">📈 Active</div>
        </div>
    </div>
    
    <!-- Analytics Section (Collapsible) -->
    <div class="analytics-section">
        <div class="analytics-header">
            <h2 class="analytics-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Analytics Dashboard
            </h2>
            <button id="analyticsToggle" class="analytics-toggle">
                <span class="toggle-icon">▼</span>
                Collapse
            </button>
        </div>
        
        <div id="analyticsContent" class="charts-grid">
            <div class="chart-container">
                <h3 class="chart-title">Claims Timeline</h3>
                <canvas id="timelineChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3 class="chart-title">Category Breakdown</h3>
                <canvas id="categoryChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3 class="chart-title">Viral Velocity</h3>
                <canvas id="velocityChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3 class="chart-title">Platform Distribution</h3>
                <canvas id="platformChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Claims Grid -->
    <div class="claims-section">
        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 20px; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border-color);">
            <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 0;">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                Trending Misinformation
            </h2>
            <span style="color: var(--text-secondary); font-size: 14px;">
                <strong id="claimsCount"><?php echo count($claims); ?></strong> active claims
            </span>
        </div>
        
        <div id="claimsGrid" class="claims-grid">
            <!-- Claims will be loaded via JavaScript -->
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p class="loading-text">Loading intelligence data...</p>
            </div>
        </div>
    </div>
    
    <!-- CTA Section -->
    <?php if ($atts['show_cta'] ?? true): ?>
    <div class="dashboard-cta" style="margin-top: 40px; padding: 40px; background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-active) 100%); border-radius: 16px; text-align: center;">
        <h2 style="font-size: 28px; font-weight: 800; color: white; margin-bottom: 12px;">
            🔍 Fact-Check Your Own Claims
        </h2>
        <p style="font-size: 16px; color: rgba(255, 255, 255, 0.9); margin-bottom: 24px;">
            Use our AI-powered fact-checking tool to verify any claim, URL, or article
        </p>
        <a href="<?php echo esc_url(home_url('/fact-check-search/')); ?>" style="display: inline-block; padding: 14px 32px; background: white; color: var(--accent-primary); border-radius: 10px; font-size: 16px; font-weight: 700; text-decoration: none; transition: all 0.3s ease;">
            Start Fact-Checking →
        </a>
    </div>
    <?php endif; ?>
    
</div>

<style>
/* Additional inline styles for smooth loading */
.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.toggle-icon {
    display: inline-block;
    transition: transform 0.3s ease;
}

.toggle-icon.rotated {
    transform: rotate(180deg);
}
</style>