<?php
/**
 * Intelligence Dashboard Template (Professional Design)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get initial data
$stats = AI_Verify_Intelligence_Dashboard::get_dashboard_stats();
?>

<div class="ai-verify-intelligence-dashboard">
    
    <!-- Professional Header -->
    <div class="dashboard-hero">
        <div class="hero-content">
            <h1 class="hero-title">
                <svg width="32" height="32" fill="currentColor" viewBox="0 0 20 20" style="display: inline-block; vertical-align: middle; margin-right: 12px;">
                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                </svg>
                Misinformation Intelligence Dashboard
            </h1>
            <p class="hero-subtitle">Real-time monitoring and analysis of trending claims across global fact-checking networks</p>
        </div>
    </div>
    
    <!-- Live Stats Bar -->
    <div class="dashboard-stats-bar">
        <div class="stat-item">
            <div class="stat-icon">
                <svg width="28" height="28" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"/>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="statActiveClaims"><?php echo number_format($stats['active_claims']); ?></div>
                <div class="stat-label">Active Claims</div>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">
                <svg width="28" height="28" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="statViralClaims"><?php echo number_format($stats['viral_claims']); ?></div>
                <div class="stat-label">Viral Now</div>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">
                <svg width="28" height="28" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="statVerified"><?php echo number_format($stats['verified_claims']); ?></div>
                <div class="stat-label">Verified</div>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">
                <svg width="28" height="28" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="statChecksPerHour"><?php echo number_format($stats['checks_per_hour'], 1); ?>K</div>
                <div class="stat-label">Checks/Hour</div>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">
                <svg width="28" height="28" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"/>
                </svg>
            </div>
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
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
            <div class="filter-select-wrapper">
                <select class="filter-select" data-filter="category" id="filterCategory">
                    <option value="all">All Categories</option>
                    <option value="politics">Politics</option>
                    <option value="health">Health</option>
                    <option value="climate">Climate</option>
                    <option value="technology">Technology</option>
                    <option value="crime">Crime</option>
                    <option value="economy">Economy</option>
                    <option value="general">General</option>
                </select>
                <svg class="select-arrow" width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                </svg>
            </div>
            
            <div class="filter-select-wrapper">
                <select class="filter-select" data-filter="platform" id="filterPlatform">
                    <option value="all">All Platforms</option>
                    <option value="rss">RSS Feeds</option>
                    <option value="google">Google Fact Check</option>
                    <option value="twitter">Twitter</option>
                    <option value="internal">User Checks</option>
                </select>
                <svg class="select-arrow" width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                </svg>
            </div>
            
            <div class="filter-select-wrapper">
                <select class="filter-select" data-filter="velocity" id="filterVelocity">
                    <option value="all">All Velocity</option>
                    <option value="viral">Viral</option>
                    <option value="emerging">Emerging</option>
                    <option value="active">Active</option>
                    <option value="dormant">Dormant</option>
                </select>
                <svg class="select-arrow" width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                </svg>
            </div>
            
            <div class="filter-select-wrapper">
                <select class="filter-select" data-filter="timeframe" id="filterTimeframe">
                    <option value="1day">Last 24 Hours</option>
                    <option value="3days">Last 3 Days</option>
                    <option value="7days" selected>Last 7 Days</option>
                    <option value="30days">Last 30 Days</option>
                </select>
                <svg class="select-arrow" width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                </svg>
            </div>
            
            <button id="refreshButton" class="filter-button refresh-btn">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
        </div>
        
        <div class="filter-chips">
            <button class="filter-chip active" data-filter-type="velocity" data-filter-value="all">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"/></svg>
                All
            </button>
            <button class="filter-chip" data-filter-type="velocity" data-filter-value="viral">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg>
                Viral
            </button>
            <button class="filter-chip" data-filter-type="velocity" data-filter-value="emerging">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                Emerging
            </button>
            <button class="filter-chip" data-filter-type="velocity" data-filter-value="active">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"/></svg>
                Active
            </button>
        </div>
    </div>
    
    <!-- Analytics Section -->
    <div class="analytics-section">
        <div class="analytics-header">
            <h2 class="analytics-title">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
</svg>
Analytics Dashboard
</h2>
<button id="analyticsToggle" class="analytics-toggle">
<svg class="toggle-icon" width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
</svg>
<span>Collapse</span>
</button>
</div>    <div id="analyticsContent" class="charts-grid">
        <div class="chart-container">
            <h3 class="chart-title">Claims Timeline</h3>
            <canvas id="timelineChart"></canvas>
        </div>        <div class="chart-container">
            <h3 class="chart-title">Category Breakdown</h3>
            <canvas id="categoryChart"></canvas>
        </div>        <div class="chart-container">
            <h3 class="chart-title">Viral Velocity</h3>
            <canvas id="velocityChart"></canvas>
        </div>        <div class="chart-container">
            <h3 class="chart-title">Platform Distribution</h3>
            <canvas id="platformChart"></canvas>
        </div>
    </div>
</div><!-- Claims Grid -->
<div class="claims-section">
    <div class="section-header">
        <h2 class="section-title">
            <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"/>
            </svg>
            Trending Misinformation
        </h2>
        <div class="section-meta">
            <span class="results-count">
                <strong id="claimsCount">0</strong> active claims
            </span>
        </div>
    </div>    <div id="claimsGrid" class="claims-grid">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p class="loading-text">Loading intelligence data...</p>
        </div>
    </div>
</div></div><style>
/* Additional Professional Styles */
.dashboard-hero {
    background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-active) 100%);
    border-radius: 16px;
    padding: 40px;
    margin-bottom: 24px;
    text-align: center;
    box-shadow: var(--shadow-lg);
}.hero-title {
font-size: 32px;
font-weight: 800;
color: white;
margin: 0 0 12px 0;
line-height: 1.2;
}.hero-subtitle {
font-size: 16px;
color: rgba(255, 255, 255, 0.9);
margin: 0;
font-weight: 500;
}.filter-select-wrapper {
position: relative;
flex: 1;
min-width: 180px;
}.filter-select {
width: 100%;
padding-right: 36px !important;
appearance: none;
-webkit-appearance: none;
-moz-appearance: none;
}.select-arrow {
position: absolute;
right: 14px;
top: 50%;
transform: translateY(-50%);
pointer-events: none;
color: var(--text-tertiary);
}.filter-button {
padding: 0px 20px;
background: var(--accent-primary);
border: none;
border-radius: 10px;
color: white;
font-size: 14px;
font-weight: 600;
cursor: pointer;
transition: all 0.2s ease;
display: flex;
align-items: center;
gap: 8px;
white-space: nowrap;
}.filter-button:hover {
background: var(--accent-active);
transform: translateY(-1px);
box-shadow: var(--shadow-md);
}.filter-button svg {
transition: transform 0.3s ease;
}.filter-button.spinning svg {
animation: spin 1s linear infinite;
}.section-header {
display: flex;
justify-content: space-between;
align-items: center;
margin-bottom: 24px;
padding: 20px;
background: var(--bg-secondary);
border-radius: 12px;
border: 1px solid var(--border-color);
}.section-title {
font-size: 20px;
font-weight: 700;
color: var(--text-primary);
margin: 0;
display: flex;
align-items: center;
gap: 10px;
}.section-meta {
display: flex;
align-items: center;
gap: 16px;
}.results-count {
color: var(--text-secondary);
font-size: 14px;
}.results-count strong {
color: var(--text-primary);
font-size: 18px;
font-weight: 700;
}@media (max-width: 768px) {
.hero-title {
font-size: 24px;
}.hero-subtitle {
    font-size: 14px;
}.filter-row {
    grid-template-columns: 1fr;
}.filter-select-wrapper {
    width: 100%;
}.section-header {
    flex-direction: column;
    gap: 12px;
    text-align: center;
}
}

/* Two stats per row on mobile only - FORCE OVERRIDE */
@media (max-width: 767px) {
    .dashboard-stats-bar {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px !important;
    }
    
    .stat-item {
        flex-direction: column !important;
        text-align: center !important;
        padding: 16px 12px !important;
    }
    
    .stat-icon {
        margin: 0 auto 10px !important;
    }
    
    .stat-content {
        align-items: center !important;
    }
    
    .stat-value {
        font-size: 24px !important;
    }
    
    .stat-label {
        font-size: 11px !important;
    }
}
</style>