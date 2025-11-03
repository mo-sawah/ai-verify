<?php
/**
 * Fact-Check Processing Template
 * Shows real-time analysis progress with engaging updates
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Get report ID from URL
$report_id = isset($_GET['report']) ? sanitize_text_field($_GET['report']) : '';

if (empty($report_id)) {
    echo '<p>Invalid report ID</p>';
    get_footer();
    exit;
}

// Get report data
$report = AI_Verify_Factcheck_Database::get_report($report_id);

if (!$report) {
    echo '<p>Report not found</p>';
    get_footer();
    exit;
}
?>

<style>
.processing-container {
    max-width: 900px;
    margin: 60px auto;
    padding: 40px 20px;
}

.processing-header {
    text-align: center;
    margin-bottom: 50px;
}

.processing-header h1 {
    font-size: 32px;
    color: #111827;
    margin-bottom: 12px;
}

.processing-header p {
    font-size: 16px;
    color: #6b7280;
}

.analyzing-url {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 40px;
    word-break: break-all;
}

.analyzing-url strong {
    display: block;
    margin-bottom: 8px;
    color: #374151;
    font-size: 14px;
}

.analyzing-url .url-text {
    color: #2563eb;
    font-size: 15px;
}

.progress-main {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.progress-spinner {
    width: 80px;
    height: 80px;
    margin: 0 auto 30px;
    position: relative;
}

.progress-spinner svg {
    width: 100%;
    height: 100%;
    animation: rotate 2s linear infinite;
}

.progress-spinner circle {
    fill: none;
    stroke: #acd2bf;
    stroke-width: 4;
    stroke-dasharray: 200;
    stroke-dashoffset: 50;
    animation: dash 1.5s ease-in-out infinite;
}

@keyframes rotate {
    100% { transform: rotate(360deg); }
}

@keyframes dash {
    0% { stroke-dashoffset: 200; }
    50% { stroke-dashoffset: 50; }
    100% { stroke-dashoffset: 200; }
}

.progress-status {
    text-align: center;
    margin-bottom: 30px;
}

.status-main {
    font-size: 20px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 8px;
}

.status-sub {
    font-size: 14px;
    color: #6b7280;
}

.progress-bar-container {
    background: #f3f4f6;
    height: 8px;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 30px;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #acd2bf 0%, #6fb394 100%);
    transition: width 0.5s ease;
    width: 0%;
}

.progress-steps {
    margin-top: 30px;
}

.step-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 16px;
    margin-bottom: 12px;
    background: #f9fafb;
    border-radius: 10px;
    opacity: 0;
    transform: translateY(10px);
    animation: slideIn 0.4s ease forwards;
}

@keyframes slideIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.step-item.completed {
    background: #ecfdf5;
    border-left: 3px solid #10b981;
}

.step-item.active {
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
}

.step-item.pending {
    opacity: 0.6;
}

.step-icon {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 2px;
}

.step-item.completed .step-icon {
    background: #10b981;
    color: white;
}

.step-item.active .step-icon {
    background: #3b82f6;
    color: white;
}

.step-item.pending .step-icon {
    background: #e5e7eb;
    color: #9ca3af;
}

.step-content {
    flex: 1;
}

.step-title {
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
    font-size: 15px;
}

.step-description {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.5;
}

.step-detail {
    font-size: 12px;
    color: #9ca3af;
    margin-top: 6px;
    font-style: italic;
}

.claims-preview {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.claim-item {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
    font-size: 14px;
    color: #374151;
    animation: slideIn 0.4s ease forwards;
}

.claim-number {
    font-weight: 600;
    color: #2563eb;
    margin-right: 8px;
}

.error-message {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    color: #991b1b;
}
</style>

<div class="processing-container">
    <div class="processing-header">
        <h1>🔍 Analyzing Content</h1>
        <p>Our AI is conducting a comprehensive fact-check analysis</p>
    </div>
    
    <div class="analyzing-url">
        <strong>Analyzing:</strong>
        <div class="url-text"><?php echo esc_html($report['input_value']); ?></div>
    </div>
    
    <div class="progress-main">
        <div class="progress-spinner">
            <svg viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="45"></circle>
            </svg>
        </div>
        
        <div class="progress-status">
            <div class="status-main" id="statusMain">Initializing analysis...</div>
            <div class="status-sub" id="statusSub">Please wait while we verify the content</div>
        </div>
        
        <div class="progress-bar-container">
            <div class="progress-bar-fill" id="progressBar"></div>
        </div>
        
        <div class="progress-steps" id="progressSteps">
            <!-- Steps will be dynamically added here -->
        </div>
    </div>
</div>

<script>
(function() {
    const reportId = '<?php echo esc_js($report_id); ?>';
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo wp_create_nonce('ai_verify_factcheck_nonce'); ?>';
    
    let pollInterval;
    let progressPercentage = 0;
    let currentSteps = [];
    
    // Step templates
    const steps = [
        { id: 'scraping', title: 'Content Extraction', desc: 'Retrieving and parsing article content', icon: '📄' },
        { id: 'claims', title: 'Claim Identification', desc: 'Extracting verifiable claims from the text', icon: '🎯' },
        { id: 'verification', title: 'Fact Verification', desc: 'Cross-referencing claims with reliable sources', icon: '✓' },
        { id: 'sources', title: 'Source Analysis', desc: 'Evaluating source credibility and relevance', icon: '📚' },
        { id: 'propaganda', title: 'Bias Detection', desc: 'Analyzing for propaganda techniques', icon: '🔍' },
        { id: 'scoring', title: 'Credibility Scoring', desc: 'Calculating overall credibility rating', icon: '📊' }
    ];
    
    function updateProgress(percentage, mainText, subText) {
        progressPercentage = Math.min(percentage, 95); // Cap at 95% until complete
        document.getElementById('progressBar').style.width = progressPercentage + '%';
        document.getElementById('statusMain').textContent = mainText;
        document.getElementById('statusSub').textContent = subText;
    }
    
    function addStep(stepId, status, detail = '') {
        const step = steps.find(s => s.id === stepId);
        if (!step) return;
        
        const existingStep = document.querySelector(`[data-step="${stepId}"]`);
        if (existingStep) {
            existingStep.className = `step-item ${status}`;
            if (detail) {
                const detailEl = existingStep.querySelector('.step-detail');
                if (detailEl) detailEl.textContent = detail;
            }
            return;
        }
        
        const stepEl = document.createElement('div');
        stepEl.className = `step-item ${status}`;
        stepEl.setAttribute('data-step', stepId);
        stepEl.innerHTML = `
            <div class="step-icon">${step.icon}</div>
            <div class="step-content">
                <div class="step-title">${step.title}</div>
                <div class="step-description">${step.desc}</div>
                ${detail ? `<div class="step-detail">${detail}</div>` : ''}
            </div>
        `;
        
        document.getElementById('progressSteps').appendChild(stepEl);
    }
    
    function showClaimPreview(claimText, claimNumber) {
        let previewContainer = document.querySelector('.claims-preview');
        if (!previewContainer) {
            previewContainer = document.createElement('div');
            previewContainer.className = 'claims-preview';
            document.getElementById('progressSteps').appendChild(previewContainer);
        }
        
        const claimEl = document.createElement('div');
        claimEl.className = 'claim-item';
        claimEl.innerHTML = `<span class="claim-number">Claim ${claimNumber}:</span>${claimText}`;
        previewContainer.appendChild(claimEl);
    }
    
    function startAnalysis() {
        // Trigger the background processing
        jQuery.post(ajaxUrl, {
            action: 'ai_verify_process_factcheck',
            nonce: nonce,
            report_id: reportId
        });
        
        // Start polling for status
        pollForStatus();
    }
    
    function pollForStatus() {
        let pollCount = 0;
        const maxPolls = 180; // 3 minutes max (180 * 1 second)
        
        pollInterval = setInterval(function() {
            pollCount++;
            
            jQuery.post(ajaxUrl, {
                action: 'ai_verify_check_status',
                nonce: nonce,
                report_id: reportId
            }, function(response) {
                if (response.success) {
                    const data = response.data;
                    const progress = parseInt(data.progress) || 0;
                    const message = data.message || '';
                    const status = data.status || '';
                    
                    // Update based on progress
                    if (progress >= 10 && progress < 30) {
                        addStep('scraping', 'completed');
                        addStep('claims', 'active', message);
                        updateProgress(progress, 'Extracting Claims', message);
                    } else if (progress >= 30 && progress < 60) {
                        addStep('scraping', 'completed');
                        addStep('claims', 'completed');
                        addStep('verification', 'active', message);
                        updateProgress(progress, 'Verifying Facts', message);
                        
                        // Show claim previews if available
                        if (data.current_claim) {
                            showClaimPreview(data.current_claim, data.claim_number || 1);
                        }
                    } else if (progress >= 60 && progress < 80) {
                        addStep('scraping', 'completed');
                        addStep('claims', 'completed');
                        addStep('verification', 'active');
                        addStep('sources', 'active', message);
                        updateProgress(progress, 'Analyzing Sources', message);
                    } else if (progress >= 80 && progress < 95) {
                        addStep('scraping', 'completed');
                        addStep('claims', 'completed');
                        addStep('verification', 'completed');
                        addStep('sources', 'completed');
                        addStep('propaganda', 'active', message);
                        addStep('scoring', 'active');
                        updateProgress(progress, 'Finalizing Analysis', message);
                    } else if (progress < 10) {
                        addStep('scraping', 'active', message);
                        updateProgress(progress, 'Extracting Content', message);
                    }
                    
                    // Check if complete
                    if (status === 'completed') {
                        clearInterval(pollInterval);
                        
                        // Mark all steps as completed
                        steps.forEach(step => addStep(step.id, 'completed'));
                        updateProgress(100, 'Analysis Complete!', 'Preparing your report...');
                        
                        // Redirect to report after brief delay
                        setTimeout(function() {
                            const reportUrl = data.report_url || window.location.href.replace(/[?&]processing=1/, '');
                            window.location.href = reportUrl;
                        }, 1500);
                    } else if (status === 'error') {
                        clearInterval(pollInterval);
                        document.querySelector('.progress-main').innerHTML = `
                            <div class="error-message">
                                <h3>❌ Analysis Failed</h3>
                                <p>${message || 'An error occurred during analysis. Please try again.'}</p>
                                <button onclick="window.location.reload()" style="margin-top: 16px; padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer;">
                                    Retry Analysis
                                </button>
                            </div>
                        `;
                    }
                }
            });
            
            // Timeout after max polls
            if (pollCount >= maxPolls) {
                clearInterval(pollInterval);
                document.querySelector('.progress-main').innerHTML = `
                    <div class="error-message">
                        <h3>⏱️ Analysis Timeout</h3>
                        <p>The analysis is taking longer than expected. Please try again or contact support.</p>
                    </div>
                `;
            }
        }, 1000); // Poll every 1 second
    }
    
    // Start the analysis when page loads
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAnalysis();
        }, 500);
    });
    
})();
</script>

<?php
get_footer();
?>