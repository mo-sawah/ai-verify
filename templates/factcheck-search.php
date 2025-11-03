<?php
/**
 * Fact-Check Search Interface Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="factcheck-search-wrapper">
    <div class="factcheck-search-container">
        <?php if (!empty($atts['title'])): ?>
            <h1 class="factcheck-title"><?php echo esc_html($atts['title']); ?></h1>
        <?php endif; ?>
        
        <?php if (!empty($atts['subtitle'])): ?>
            <p class="factcheck-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
        <?php endif; ?>
        
        <div class="factcheck-search-box">
            <?php if ($atts['show_filters'] === 'yes'): ?>
            <div class="factcheck-filters">
                <button class="filter-btn active" data-type="auto">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Auto-Detect
                </button>
                <button class="filter-btn" data-type="url">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                    URL
                </button>
                <button class="filter-btn" data-type="title">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                    </svg>
                    Title
                </button>
                <button class="filter-btn" data-type="phrase">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Claim
                </button>
            </div>
            <?php endif; ?>
            
            <div class="factcheck-input-wrapper">
                <div class="factcheck-input-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input 
                    type="text" 
                    id="factcheck-input" 
                    class="factcheck-input" 
                    placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                    autocomplete="off"
                >
                <button id="factcheck-submit" class="factcheck-submit-btn">
                    <span class="btn-text"><?php echo esc_html($atts['button_text']); ?></span>
                    <svg class="btn-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                    <svg class="btn-loading" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" opacity="0.3"></path>
                        <path d="M12 2v4c3.31 0 6 2.69 6 6h4c0-5.52-4.48-10-10-10z"></path>
                    </svg>
                </button>
            </div>
            
            <div class="factcheck-examples">
                <span class="examples-label">Try examples:</span>
                <button class="example-btn" data-example="https://www.bbc.com/news">News Article URL</button>
                <button class="example-btn" data-example="COVID-19 vaccine effectiveness">Article Title</button>
                <button class="example-btn" data-example="The earth is flat">Simple Claim</button>
            </div>
        </div>
        
        <div class="factcheck-features">
            <div class="feature-item">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Multi-Source Verification</span>
            </div>
            <div class="feature-item">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <span>AI-Powered Analysis</span>
            </div>
            <div class="feature-item">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Instant Results</span>
            </div>
            <div class="feature-item">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                </svg>
                <span>Downloadable Reports</span>
            </div>
        </div>
    </div>
</div>

<script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const params = new URLSearchParams(window.location.search);
                const urlToAnalyze = params.get('prefill_url');

                if (urlToAnalyze) {
                    const inputField = document.getElementById('factcheck-input');
                    const submitButton = document.getElementById('factcheck-submit');

                    if (inputField && submitButton) {
                        // Decode the URL, set it in the input, and click the button
                        inputField.value = decodeURIComponent(urlToAnalyze);
                        submitButton.click();
                    }
                }
            } catch (e) {
                console.error("Error auto-analyzing URL:", e);
            }
        });
    </script>