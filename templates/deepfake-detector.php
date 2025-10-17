<?php
/**
 * Deepfake Detector Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="deepfake-detector-wrapper">
    <div class="deepfake-detector-container">
        <!-- Header -->
        <div class="deepfake-header">
            <div class="header-icon">
                <svg width="80" height="80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            
            <?php if (!empty($atts['title'])): ?>
                <h1 class="deepfake-title"><?php echo esc_html($atts['title']); ?></h1>
            <?php endif; ?>
            
            <?php if (!empty($atts['subtitle'])): ?>
                <p class="deepfake-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
            <?php endif; ?>
            
            <div class="detection-stats">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-green">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">98%</div>
                        <div class="stat-label">Accuracy Rate</div>
                        <div class="stat-desc">Enterprise-grade precision</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-icon-blue">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">&lt;5 sec</div>
                        <div class="stat-label">Detection Time</div>
                        <div class="stat-desc">Real-time analysis</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-icon-purple">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">Multi-Model</div>
                        <div class="stat-label">AI Detection</div>
                        <div class="stat-desc">Advanced algorithms</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-icon-orange">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">Secure</div>
                        <div class="stat-label">Privacy Protected</div>
                        <div class="stat-desc">Data encrypted</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Section -->
        <div class="upload-section">
            <div class="upload-tabs">
                <button class="upload-tab active" data-tab="file">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/>
                    </svg>
                    Upload File
                </button>
                <button class="upload-tab" data-tab="url">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z"/>
                    </svg>
                    Paste URL
                </button>
            </div>

            <!-- File Upload Tab -->
            <div class="upload-content active" id="upload-file">
                <div class="upload-area" id="uploadArea">
                    <input type="file" id="mediaFile" accept="image/*,audio/*" style="display: none;">
                    <div class="upload-placeholder">
                        <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <h3>Drop files here or click to upload</h3>
                        <p>Supports: JPG, PNG, WebP, MP3, WAV, OGG (Max 10MB)</p>
                    </div>
                    <div class="upload-preview" id="uploadPreview" style="display: none;">
                        <div class="preview-content">
                            <img id="previewImage" style="display: none; max-width: 100%; border-radius: 8px;">
                            <audio id="previewAudio" controls style="display: none; width: 100%;"></audio>
                            <div class="preview-info">
                                <span class="preview-filename" id="previewFilename"></span>
                                <span class="preview-filesize" id="previewFilesize"></span>
                            </div>
                        </div>
                        <button class="preview-remove" id="removeFile">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button class="detect-btn" id="detectFileBtn" disabled>
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                    </svg>
                    <span class="btn-text">Analyze for Deepfakes</span>
                    <svg class="btn-loading" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" opacity="0.3"></path>
                        <path d="M12 2v4c3.31 0 6 2.69 6 6h4c0-5.52-4.48-10-10-10z"></path>
                    </svg>
                </button>
            </div>

            <!-- URL Upload Tab -->
            <div class="upload-content" id="upload-url">
                <div class="url-input-wrapper">
                    <input type="url" id="mediaUrl" class="url-input" placeholder="https://example.com/image.jpg or https://example.com/audio.mp3">
                    <button class="detect-btn" id="detectUrlBtn">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                        </svg>
                        <span class="btn-text">Analyze URL</span>
                        <svg class="btn-loading" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="display: none;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" opacity="0.3"></path>
                            <path d="M12 2v4c3.31 0 6 2.69 6 6h4c0-5.52-4.48-10-10-10z"></path>
                        </svg>
                    </button>
                </div>
                <p class="url-hint">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                    </svg>
                    Enter a direct link to an image or audio file
                </p>
            </div>
        </div>

        <!-- Results Section -->
        <div class="results-section" id="resultsSection" style="display: none;">
            <div class="results-header">
                <h2>Detection Results</h2>
                <button class="new-scan-btn" id="newScanBtn">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"/>
                    </svg>
                    New Scan
                </button>
            </div>

            <!-- Score Card -->
            <div class="score-card">
                <div class="score-visual">
                    <div class="score-circle" id="scoreCircle">
                        <svg width="200" height="200">
                            <circle cx="100" cy="100" r="85" stroke="#e5e7eb" stroke-width="15" fill="none"></circle>
                            <circle id="scoreRing" cx="100" cy="100" r="85" stroke="#acd2bf" stroke-width="15" fill="none" stroke-linecap="round" transform="rotate(-90 100 100)"></circle>
                        </svg>
                        <div class="score-content">
                            <span class="score-number" id="scoreNumber">0</span>
                            <span class="score-label">Detection Score</span>
                        </div>
                    </div>
                </div>
                
                <div class="score-details">
                    <div class="verdict-badge" id="verdictBadge">
                        <svg width="32" height="32" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                        </svg>
                        <div>
                            <span class="verdict-text" id="verdictText">Analyzing...</span>
                            <span class="confidence-text" id="confidenceText">Calculating confidence...</span>
                        </div>
                    </div>
                    
                    <div class="analysis-metrics">
                        <div class="metric">
                            <span class="metric-label">Media Type</span>
                            <span class="metric-value" id="mediaType">-</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Confidence Level</span>
                            <span class="metric-value" id="confidenceLevel">-</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Detection Method</span>
                            <span class="metric-value">Multi-Model AI</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div class="recommendations-section" id="recommendationsSection">
                <h3>
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                    </svg>
                    Recommendations
                </h3>
                <ul class="recommendations-list" id="recommendationsList"></ul>
            </div>

            <!-- Detailed Analysis -->
            <div class="analysis-section">
                <h3>
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"/>
                    </svg>
                    Detailed Analysis
                </h3>
                
                <div class="analysis-grid">
                    <div class="analysis-card">
                        <div class="card-header">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <h4>AI Models Used</h4>
                        </div>
                        <ul class="analysis-list" id="modelsUsed">
                            <li>Loading...</li>
                        </ul>
                    </div>
                    
                    <div class="analysis-card">
                        <div class="card-header">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"/>
                            </svg>
                            <h4>Manipulation Types</h4>
                        </div>
                        <ul class="analysis-list" id="manipulationTypes">
                            <li>Analyzing...</li>
                        </ul>
                    </div>
                    
                    <div class="analysis-card">
                        <div class="card-header">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12 1.586l-4 4v12.828l4-4V1.586zM3.707 3.293A1 1 0 002 4v10a1 1 0 00.293.707L6 18.414V5.586L3.707 3.293zM17.707 5.293L14 1.586v12.828l2.293 2.293A1 1 0 0018 16V6a1 1 0 00-.293-.707z"/>
                            </svg>
                            <h4>Regions Analyzed</h4>
                        </div>
                        <ul class="analysis-list" id="regionsAnalyzed">
                            <li>Processing...</li>
                        </ul>
                    </div>
                    
                    <div class="analysis-card">
                        <div class="card-header">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"/>
                            </svg>
                            <h4>Artifacts Detected</h4>
                        </div>
                        <ul class="analysis-list" id="artifactsDetected">
                            <li>Scanning...</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Technical Details -->
            <div class="technical-details">
                <button class="details-toggle" id="detailsToggle">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                    </svg>
                    <span>View Technical Details</span>
                </button>
                <div class="details-content" id="detailsContent" style="display: none;">
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Detection ID</span>
                            <span class="detail-value" id="detectionId">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Timestamp</span>
                            <span class="detail-value" id="timestamp">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Processing Time</span>
                            <span class="detail-value" id="processingTime">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detection History -->
        <?php if ($atts['show_history'] === 'yes'): ?>
        <div class="history-section">
            <div class="history-header">
                <h3>
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                    </svg>
                    Recent Detections
                </h3>
                <button class="refresh-history" id="refreshHistory">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"/>
                    </svg>
                    Refresh
                </button>
            </div>
            <div class="history-list" id="historyList">
                <div class="history-loading">
                    <svg width="32" height="32" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" opacity="0.3"></path>
                        <path d="M12 2v4c3.31 0 6 2.69 6 6h4c0-5.52-4.48-10-10-10z"></path>
                    </svg>
                    <p>Loading history...</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info Section -->
        <div class="info-section">
            <h3>How Deepfake Detection Works</h3>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-icon">🔍</div>
                    <h4>Multi-Model Analysis</h4>
                    <p>Uses multiple AI models to detect various types of manipulations and synthetic media generation techniques.</p>
                </div>
                <div class="info-card">
                    <div class="info-icon">🧠</div>
                    <h4>Context-Aware Detection</h4>
                    <p>Analyzes not just faces, but entire images and audio patterns to identify inconsistencies invisible to humans.</p>
                </div>
                <div class="info-card">
                    <div class="info-icon">⚡</div>
                    <h4>Real-Time Results</h4>
                    <p>Delivers accurate detection results in seconds, powered by enterprise-grade AI infrastructure.</p>
                </div>
                <div class="info-card">
                    <div class="info-icon">🛡️</div>
                    <h4>Constantly Updated</h4>
                    <p>Models are regularly updated to detect the latest deepfake generation techniques and AI tools.</p>
                </div>
            </div>
        </div>

        <!-- Trust Indicators -->
        <div class="trust-section">
            <p class="trust-text">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                </svg>
                Powered by Reality Defender - Enterprise-Grade Deepfake Detection
            </p>
            <div class="trust-badges">
                <span class="badge">✓ 98% Accuracy</span>
                <span class="badge">✓ Multi-Model AI</span>
                <span class="badge">✓ Real-Time Detection</span>
                <span class="badge">✓ Privacy Protected</span>
            </div>
        </div>
    </div>
</div>