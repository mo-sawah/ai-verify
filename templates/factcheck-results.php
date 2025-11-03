<?php
/**
 * Professional Fact-Check Results Page Template
 * UPDATED: Enhanced design with source card and improved visuals
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="factcheck-results-wrapper" id="factcheckResults">
    <?php
    // Correctly include the simple email gate template.
    $gate_template = AI_VERIFY_PLUGIN_DIR . 'templates/factcheck-email-gate.php';
    if (file_exists($gate_template)) {
        include $gate_template;
    }
    ?>

    <div class="factcheck-loading" id="factcheckLoading">
        <div class="loading-spinner">
            <svg width="64" height="64" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" opacity="0.3"></path>
                <path d="M12 2v4c3.31 0 6 2.69 6 6h4c0-5.52-4.48-10-10-10z"></path>
            </svg>
        </div>
        <h3>Analyzing Content...</h3>
        <p class="loading-step" id="loadingStep">Initializing analysis...</p>
        <div class="loading-progress">
            <div class="progress-bar" id="progressBar"></div>
        </div>
    </div>
    
    <div class="factcheck-report" id="factcheckReport" style="display: none;">
        <div class="report-header">
            <div class="report-meta">
                <span class="report-id">Report ID: <strong id="reportId">-</strong></span>
                <span class="report-date">Generated: <strong id="reportDate">-</strong></span>
            </div>
            
            <?php if (isset($atts['show_export']) && $atts['show_export'] === 'yes'): ?>
            <div class="report-actions">
                <button class="export-btn" data-format="html">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                    </svg>
                    Export HTML
                </button>
                <button class="export-btn" data-format="json">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Export JSON
                </button>
                <button class="share-btn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                    </svg>
                    Share
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Enhanced Source Article Card -->
        <div class="source-article-card" id="sourceArticleCard" style="display: none;">
            <div class="source-article-header">
                <div class="source-article-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3>Analyzed Content</h3>
            </div>
            <div class="source-article-content">
                <div class="source-article-image" id="sourceImage" style="display: none;">
                    <img src="" alt="Article thumbnail" id="sourceImageImg">
                </div>
                <div class="source-article-main">
                    <div class="source-article-info">
                        <a href="#" id="sourceUrl" class="source-title-link" target="_blank" rel="noopener noreferrer">
                            <h4 id="sourceTitle" class="source-title">Loading...</h4>
                        </a>
                        <div class="source-meta">
                            <span class="source-favicon-inline" id="sourceFaviconInline"></span>
                            <span class="source-domain" id="sourceDomain">-</span>
                            <span class="source-separator">•</span>
                            <span class="source-date" id="sourceDate">-</span>
                        </div>
                    </div>
                </div>
                <div class="source-article-badge">
                    <div class="credibility-badge" id="credibilityBadge">
                        <div class="badge-score" id="badgeScore">0</div>
                        <div class="badge-rating" id="badgeRating">Analyzing</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Redesigned Score Card -->
        <div class="report-score-card">
            <div class="score-visual">
                <div class="score-circle">
                    <svg class="score-ring" width="200" height="200">
                        <circle cx="100" cy="100" r="90" stroke="#e5e5e5" stroke-width="12" fill="none"></circle>
                        <circle id="scoreCircle" cx="100" cy="100" r="90" stroke="#acd2bf" stroke-width="12" fill="none" stroke-linecap="round" transform="rotate(-90 100 100)"></circle>
                    </svg>
                    <div class="score-number">
                        <span id="overallScore">0</span>
                        <span class="score-label">Overall Score</span>
                    </div>
                </div>
            </div>
            
            <div class="score-details">
                <div class="credibility-rating-display">
                    <div class="rating-badge-large" id="ratingBadgeLarge">
                        <svg class="rating-icon" width="28" height="28" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path>
                        </svg>
                        <span id="credibilityRating">Analyzing...</span>
                    </div>
                </div>
                
                <div class="analysis-summary">
                    <p id="inputValue" class="analyzed-content"></p>
                </div>
                
                <div class="score-breakdown">
                    <div class="breakdown-item">
                        <div class="breakdown-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="breakdown-content">
                            <div class="breakdown-label">Claims Analyzed</div>
                            <div class="breakdown-value" id="claimsCount">0</div>
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
                            <div class="breakdown-value" id="sourcesCount">0</div>
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
                            <div class="breakdown-value" id="analysisTime">-</div>
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
                            <div class="breakdown-value" id="analysisMethod">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="propaganda-warning" id="propagandaWarning" style="display: none;">
            <div class="warning-header">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <h3>⚠️ Propaganda Techniques Detected</h3>
            </div>
            <ul id="propagandaList" class="propaganda-list"></ul>
        </div>
        
        <div class="report-section">
            <div class="section-header-with-filter">
                <h3 class="section-title">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Detailed Claims Analysis
                </h3>
                <div class="claims-filter">
                    <button class="filter-chip active" data-filter="all">All Claims</button>
                    <button class="filter-chip" data-filter="true">✓ True</button>
                    <button class="filter-chip" data-filter="false">✗ False</button>
                    <button class="filter-chip" data-filter="misleading">⚠ Misleading</button>
                    <button class="filter-chip" data-filter="unverified">? Unverified</button>
                </div>
            </div>
            <div id="claimsAnalysis" class="claims-list">
                </div>
        </div>
        
        <div class="report-section">
            <h3 class="section-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                Sources Consulted
            </h3>
            <div id="sourcesList" class="sources-list">
                </div>
        </div>
        
        <div class="report-section methodology">
            <h3 class="section-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Our Methodology
            </h3>
            <div class="methodology-steps">
                <div class="method-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Content Extraction</h4>
                        <p>Scraped and parsed content using advanced web crawling</p>
                    </div>
                </div>
                <div class="method-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Claim Identification</h4>
                        <p>Used ClaimBuster API and AI to extract verifiable claims</p>
                    </div>
                </div>
                <div class="method-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Web Search Verification</h4>
                        <p>Cross-referenced claims with current web sources</p>
                    </div>
                </div>
                <div class="method-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>Propaganda Detection</h4>
                        <p>Analyzed content for manipulation techniques and bias</p>
                    </div>
                </div>
                <div class="method-step">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <h4>Credibility Scoring</h4>
                        <p>Calculated overall score based on verified evidence</p>
                    </div>
                </div>
                <div class="method-step">
                    <div class="step-number">6</div>
                    <div class="step-content">
                        <h4>Top Fact Check APIs</h4>
                        <p>Identified and integrated leading fact-checking APIs for enhanced verification</p>
                    </div>
                </div>
                <div class="method-step">
                    <div class="step-number">7</div>
                    <div class="step-content">
                        <h4>AI Enhanced</h4>
                        <p>Leveraged AI technologies to improve fact-checking accuracy and efficiency</p>
                    </div>
                </div>
                <div class="method-step">
                    <div class="step-number">8</div>
                    <div class="step-content">
                        <h4>Double Checking</h4>
                        <p>Implemented a secondary review process using Google to ensure accuracy</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>