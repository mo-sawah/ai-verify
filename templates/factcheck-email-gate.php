<?php
/**
 * Email Gate Template - Lead Capture Form
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="factcheck-email-gate" id="factcheckEmailGate" style="display: none;">
    <div class="email-gate-overlay"></div>
    <div class="email-gate-modal">
        <div class="email-gate-content">
            <button class="email-gate-close" id="emailGateClose" aria-label="Close">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            
            <div class="email-gate-header">
                <div class="email-gate-icon">
                    <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <h2>Analysis Complete! 🎉</h2>
                <p>Get your comprehensive fact-check report with detailed analysis, sources, and credibility score</p>
            </div>
            
            <div class="email-gate-features">
                <div class="feature">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Detailed Credibility Score</span>
                </div>
                <div class="feature">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span>Claim-by-Claim Analysis</span>
                </div>
                <div class="feature">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <span>Verified Sources</span>
                </div>
                <div class="feature">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                    </svg>
                    <span>Downloadable Report</span>
                </div>
            </div>
            
            <form id="emailGateForm" class="email-gate-form">
                <div class="form-row">
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
                
                <button type="submit" class="email-gate-submit" id="emailGateSubmit">
                    <span class="btn-text">View My Fact-Check Report</span>
                    <svg class="btn-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                    <svg class="btn-loading" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" opacity="0.3"></path>
                        <path d="M12 2v4c3.31 0 6 2.69 6 6h4c0-5.52-4.48-10-10-10z"></path>
                    </svg>
                </button>
                
                <p class="email-gate-note">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Your information is secure and will never be shared with third parties
                </p>
            </form>
            
            <div class="email-gate-trust">
                <p>Trusted by thousands of fact-checkers worldwide</p>
                <div class="trust-badges">
                    <span class="badge">🔒 SSL Secured</span>
                    <span class="badge">✓ GDPR Compliant</span>
                    <span class="badge">⚡ Instant Access</span>
                </div>
            </div>
        </div>
    </div>
</div>