<?php
/**
 * Verification Tools Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Google Lens URL (new reverse image search)
$google_image_search = $featured_image_url ? 'https://lens.google.com/uploadbyurl?url=' . urlencode($featured_image_url) : '#';
$yandex_search = $featured_image_url ? 'https://yandex.com/images/search?rpt=imageview&url=' . urlencode($featured_image_url) : '#';
$tineye_search = $featured_image_url ? 'https://tineye.com/search?url=' . urlencode($featured_image_url) : '#';

$cta_title = get_option('ai_verify_cta_title', 'Want More Verification Tools?');
$cta_description = get_option('ai_verify_cta_description', 'Access our full suite of professional disinformation monitoring and investigation tools');
$cta_btn_1_text = get_option('ai_verify_cta_button_1_text', '🔍 OSINT Search');
$cta_btn_1_url = get_option('ai_verify_cta_button_1_url', 'https://disinformationcommission.com');
$cta_btn_2_text = get_option('ai_verify_cta_button_2_text', '🌐 Web Monitor');
$cta_btn_2_url = get_option('ai_verify_cta_button_2_url', 'https://disinformationcommission.com');
$cta_btn_3_text = get_option('ai_verify_cta_button_3_text', '🛡️ All Tools');
$cta_btn_3_url = get_option('ai_verify_cta_button_3_url', 'https://disinformationcommission.com');
?>

<div class="ai-verify-tools">
    <h2 class="ai-verify-title">Verify This Yourself</h2>
    <p class="ai-verify-subtitle">Use these professional tools to fact-check and investigate claims independently</p>

    <?php if ($atts['show_image_search'] === 'yes'): ?>
    <!-- Reverse Image Search -->
    <div class="ai-verify-section">
        <h3 class="ai-verify-section-header">
            <svg class="ai-verify-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Reverse Image Search
        </h3>
        <p class="ai-verify-description">Check if this image has been used elsewhere or in different contexts</p>
        <?php if ($featured_image_url): ?>
        <div class="ai-verify-search-buttons">
            <a href="<?php echo esc_url($google_image_search); ?>" target="_blank" rel="noopener" class="ai-verify-btn">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path>
                </svg>
                Google Images
            </a>
            <a href="<?php echo esc_url($yandex_search); ?>" target="_blank" rel="noopener" class="ai-verify-btn">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path>
                </svg>
                Yandex
            </a>
            <a href="<?php echo esc_url($tineye_search); ?>" target="_blank" rel="noopener" class="ai-verify-btn">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path>
                </svg>
                TinEye
            </a>
        </div>
        <?php else: ?>
        <div class="ai-verify-notice">
            ℹ️ This post doesn't have a featured image. Set a featured image to enable reverse image search.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($atts['show_ai_chat'] === 'yes'): ?>
    <!-- AI Chatbot -->
    <div class="ai-verify-section">
        <h3 class="ai-verify-section-header">
            <svg class="ai-verify-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
            </svg>
            Ask Our AI About This Claim
        </h3>
        <p class="ai-verify-description">Get instant answers with web-powered AI analysis</p>
        <div class="ai-verify-chat-container" id="aiVerifyChatContainer">
            <div class="ai-verify-chat-message ai-verify-ai-message">
                👋 Hi! I can help you understand this fact-check better. Ask me anything about this claim, related context, or how to verify similar content.
            </div>
        </div>
        <div class="ai-verify-chat-input-container">
            <input type="text" id="aiVerifyChatInput" class="ai-verify-chat-input" placeholder="Ask a question about this claim...">
            <button id="aiVerifyChatSend" class="ai-verify-chat-send">Send</button>
        </div>
        <div class="ai-verify-chat-status" id="aiVerifyChatStatus"></div>
    </div>
    <?php endif; ?>

    <?php if ($atts['show_fact_checks'] === 'yes'): ?>
    <!-- Related Fact Checks -->
    <div class="ai-verify-section">
        <h3 class="ai-verify-section-header">
            <svg class="ai-verify-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Related Fact-Checks
        </h3>
        <p class="ai-verify-description">See what other fact-checkers have said about similar claims</p>
        <div id="aiVerifyFactChecks" class="ai-verify-factcheck-list">
            <div class="ai-verify-loading">Loading fact-checks...</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($atts['show_cta'] === 'yes'): ?>
    <!-- CTA Card -->
    <div class="ai-verify-cta-card">
        <h3 class="ai-verify-cta-title"><?php echo esc_html($cta_title); ?></h3>
        <p class="ai-verify-cta-description"><?php echo esc_html($cta_description); ?></p>
        <div class="ai-verify-cta-buttons">
            <?php if (!empty($cta_btn_1_text) && !empty($cta_btn_1_url)): ?>
            <a href="<?php echo esc_url($cta_btn_1_url); ?>" target="_blank" rel="noopener" class="ai-verify-cta-btn">
                <?php echo esc_html($cta_btn_1_text); ?>
            </a>
            <?php endif; ?>
            
            <?php if (!empty($cta_btn_2_text) && !empty($cta_btn_2_url)): ?>
            <a href="<?php echo esc_url($cta_btn_2_url); ?>" target="_blank" rel="noopener" class="ai-verify-cta-btn">
                <?php echo esc_html($cta_btn_2_text); ?>
            </a>
            <?php endif; ?>
            
            <?php if (!empty($cta_btn_3_text) && !empty($cta_btn_3_url)): ?>
            <a href="<?php echo esc_url($cta_btn_3_url); ?>" target="_blank" rel="noopener" class="ai-verify-cta-btn">
                <?php echo esc_html($cta_btn_3_text); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>