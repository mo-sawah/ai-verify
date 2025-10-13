<?php
/**
 * Email Gate Template - Subscription Plans
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

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