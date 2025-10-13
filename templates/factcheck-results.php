<?php
/**
 * Professional Fact-Check Results Page Template
 * UPDATED: New paywall overlay structure
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="factcheck-results-wrapper" id="factcheckResults">
    <!-- Loading State -->
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
    
    <!-- NEW: Paywall Overlay (not popup modal) -->
    <div class="factcheck-paywall-overlay">
        <div class="paywall-content-wrapper">
            <div class="paywall-header">
                <div class="paywall-icon">
                    <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <h2>Analysis Complete! 🎉</h2>
                <p>Choose your plan to view the full fact-check report</p>
            </div>
            
            <div class="subscription-plans">
                <!-- Free Plan -->
                <div class="plan-card free-plan active" data-plan="free">
                    <div class="plan-badge">Limited Time</div>
                    <div class="plan-header">
                        <h3>Free Access</h3>
                        <div class="plan-price">
                            <span class="price">$0</span>
                        </div>
                    </div>
                    
                    <ul class="plan-features">
                        <li>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            5 Fact-Checks per Month
                        </li>
                        <li>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Basic Analysis
                        </li>
                        <li>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Source Citations
                        </li>
                        <li>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            30 Days Access
                        </li>
                    </ul>
                    
                    <button type="button" class="plan-select-btn" data-plan="free">
                        Get Free Access
                    </button>
                </div>
                
                <!-- Pro Plan -->
                <div class="plan-card pro-plan" data-plan="pro">
                    <div class="plan-badge recommended">Recommended</div>
                    <div class="plan-header">
                        <h3>Pro Access</h3>
                        <div class="plan-price">
                            <span class="price">$5</span>
                            <span class="period">/month</span>
                        </div>
                    </div>
                    
                    <ul class="plan-features">
                        <li>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Unlimited Fact-Checks
                        </li>
                        <li>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Advanced AI Analysis
                        </li>
                        <li>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Priority Processing
                        </li>
                        <li>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Export Reports (PDF/CSV)
                        </li>
                        <li>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Email Support
                        </li>
                    </ul>
                    
                    <button type="button" class="plan-select-btn" data-plan="pro">
                        Subscribe Now
                    </button>
                </div>
            </div>
            
            <!-- Free Plan Form -->
            <form id="freePlanForm" class="plan-form active">
                <div class="form-group">
                    <label for="userName">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Full Name
                    </label>
                    <input type="text" id="userName" name="user_name" required placeholder="John Doe" autocomplete="name">
                </div>
                
                <div class="form-group">
                    <label for="userEmail">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        Email Address
                    </label>
                    <input type="email" id="userEmail" name="user_email" required placeholder="john@example.com" autocomplete="email">
                </div>
                
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="termsAccept" name="terms_accept" required>
                        <span class="checkbox-text">
                            I agree to receive my fact-check report and accept the 
                            <a href="<?php echo esc_url(get_privacy_policy_url() ?: '#'); ?>" target="_blank">Privacy Policy</a> 
                            and 
                            <a href="<?php echo esc_url(home_url('/terms-of-use/')); ?>" target="_blank">Terms of Use</a>
                        </span>
                    </label>
                </div>
                
                <button type="submit" class="submit-btn" id="freePlanSubmit">
                    <span class="btn-text">View My Report - Free</span>
                    <svg class="btn-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                    <svg class="btn-loading" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" opacity="0.3"></path>
                        <path d="M12 2v4c3.31 0 6 2.69 6 6h4c0-5.52-4.48-10-10-10z"></path>
                    </svg>
                </button>
            </form>
            
            <!-- Pro Plan Form -->
            <form id="proPlanForm" class="plan-form" style="display: none;">
                <div class="stripe-payment-demo">
                    <div class="payment-header">
                        <h4>💳 Secure Payment</h4>
                        <p>Powered by Stripe - Your payment is 100% secure</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="cardName">Cardholder Name</label>
                        <input type="text" id="cardName" placeholder="John Doe" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cardNumber">Card Number</label>
                        <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19" required>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="cardExpiry">Expiry Date</label>
                            <input type="text" id="cardExpiry" placeholder="MM / YY" maxlength="7" required>
                        </div>
                        <div class="form-group">
                            <label for="cardCvc">CVC</label>
                            <input type="text" id="cardCvc" placeholder="123" maxlength="3" required>
                        </div>
                    </div>
                    
                    <div class="payment-summary">
                        <div class="summary-row">
                            <span>Pro Subscription</span>
                            <span>$5.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total Due Today</span>
                            <span>$5.00</span>
                        </div>
                        <p class="billing-note">Billed monthly. Cancel anytime.</p>
                    </div>
                    
                    <button type="submit" class="submit-btn pro-submit" id="proPlanSubmit">
                        <span class="btn-text">Subscribe & View Report</span>
                        <svg class="btn-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </button>
                </div>
            </form>
            
            <div class="email-gate-trust">
                <div class="trust-badges">
                    <span class="badge">🔒 SSL Secured</span>
                    <span class="badge">✓ GDPR Compliant</span>
                    <span class="badge">⚡ Instant Access</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Results Display (can be locked with .report-locked class) -->
    <div class="factcheck-paywall-overlay" id="factcheckEmailGate"  style="display: none;">
        <!-- Report Header -->
        <div class="report-header">
            <div class="report-meta">
                <span class="report-id">Report ID: <strong id="reportId">-</strong></span>
                <span class="report-date">Generated: <strong id="reportDate">-</strong></span>
            </div>
            
            <?php if ($atts['show_export'] === 'yes'): ?>
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
        
        <!-- Overall Score Card -->
        <div class="report-score-card">
            <div class="score-visual">
                <div class="score-circle">
                    <svg class="score-ring" width="200" height="200">
                        <circle cx="100" cy="100" r="90" stroke="#e5e5e5" stroke-width="12" fill="none"></circle>
                        <circle id="scoreCircle" cx="100" cy="100" r="90" stroke="#acd2bf" stroke-width="12" fill="none" stroke-linecap="round" transform="rotate(-90 100 100)"></circle>
                    </svg>
                    <div class="score-number">
                        <span id="overallScore">0</span>
                        <span class="score-label">Credibility Score</span>
                    </div>
                </div>
            </div>
            
            <div class="score-details">
                <h2 id="credibilityRating">Analyzing...</h2>
                <p id="inputValue" class="analyzed-content"></p>
                
                <div class="score-breakdown">
                    <div class="breakdown-item">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Claims Analyzed: <strong id="claimsCount">0</strong></span>
                    </div>
                    <div class="breakdown-item">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <span>Sources Verified: <strong id="sourcesCount">0</strong></span>
                    </div>
                    <div class="breakdown-item">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Analysis Time: <strong id="analysisTime">-</strong></span>
                    </div>
                    <div class="breakdown-item">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        <span>Method: <strong id="analysisMethod">-</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Propaganda Warning -->
        <div class="propaganda-warning" id="propagandaWarning" style="display: none;">
            <div class="warning-header">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <h3>⚠️ Propaganda Techniques Detected</h3>
            </div>
            <ul id="propagandaList" class="propaganda-list"></ul>
        </div>
        
        <!-- Claims Analysis -->
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
                <!-- Claims will be inserted here by JavaScript -->
            </div>
        </div>
        
        <!-- Sources -->
        <div class="report-section">
            <h3 class="section-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                Sources Consulted
            </h3>
            <div id="sourcesList" class="sources-list">
                <!-- Sources will be inserted here -->
            </div>
        </div>
        
        <!-- Methodology -->
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
            </div>
        </div>
    </div>
</div>