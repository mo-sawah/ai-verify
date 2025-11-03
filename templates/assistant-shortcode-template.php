<?php
/**
 * Template for Advanced AI Assistant Shortcode
 * Professional, feature-rich chat interface
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ai-verify-assistant-wrapper s-light">
    
    <!-- Hero Header -->
    <div class="assistant-hero">
        <div class="hero-content">
            <h1 class="hero-title">
                <svg width="40" height="40" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z"/>
                </svg>
                AI Fact-Check Assistant
            </h1>
            <p class="hero-subtitle">
                Your intelligent partner for investigating claims, analyzing content, and navigating the information landscape. 
                Powered by real-time web search, comprehensive databases, and advanced AI.
            </p>
        </div>
    </div>

    <!-- Main Container -->
    <div class="assistant-container">
        
        <!-- Sidebar with Conversation History -->
        <aside class="assistant-sidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-title">Conversations</h2>
                <button id="newChatBtn" class="new-chat-btn" title="Start a new conversation">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/>
                    </svg>
                    New Chat
                </button>
            </div>
            
            <div class="conversation-history" id="conversationHistory">
                <p class="no-history">Your conversations will appear here.</p>
            </div>
        </aside>

        <!-- Chat Main Area -->
        <main class="chat-main">
            
            <!-- Messages Container -->
            <div class="chat-messages-main" id="chatMessages">
                <!-- Messages will be inserted here dynamically -->
            </div>

            <!-- Input Area -->
            <div class="chat-input-area">
                
                <!-- Stop Generating Button (hidden by default) -->
                <button id="stopGeneratingBtn" class="stop-generating-btn">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5 3.75A1.25 1.25 0 003.75 5v10A1.25 1.25 0 005 16.25h10A1.25 1.25 0 0016.25 15V5A1.25 1.25 0 0015 3.75H5z"/>
                    </svg>
                    Stop Generating
                </button>

                <!-- Input Container -->
                <div class="chat-input-container-main">
                    <textarea 
                        id="chatInput" 
                        placeholder="Ask a question, paste a URL to analyze, or search for fact-checks..."
                        rows="1"
                        maxlength="5000"
                    ></textarea>
                    
                    <button id="chatSendBtn" title="Send message (Enter)" aria-label="Send message">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z"/>
                        </svg>
                    </button>
                </div>

                <!-- Example Prompts -->
                <div class="chat-examples">
                    <button class="chat-example-prompt" data-prompt="What are the top 3 viral misinformation claims this week?">
                        🔥 Top viral claims
                    </button>
                    <button class="chat-example-prompt" data-prompt="Search for fact-checks about climate change">
                        🌍 Climate fact-checks
                    </button>
                    <button class="chat-example-prompt" data-prompt="Analyze this URL for propaganda techniques">
                        🔍 Analyze URL
                    </button>
                    <button class="chat-example-prompt" data-prompt="Show me your capabilities and tools">
                        💡 What can you do?
                    </button>
                </div>

            </div>

        </main>

    </div>

</div>

<style>
/* Ensure proper theme detection */
body.s-dark .ai-verify-assistant-wrapper {
    color-scheme: dark;
}

body.s-light .ai-verify-assistant-wrapper {
    color-scheme: light;
}

/* Inherit theme from body if available */
body.s-dark .ai-verify-assistant-wrapper {
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-tertiary: #334155;
    --bg-hover: #475569;
    --border-color: #334155;
    --border-hover: #475569;
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-tertiary: #94a3b8;
}
</style>

<script>
// Auto-detect theme from body
(function() {
    const updateTheme = function() {
        const wrapper = document.querySelector('.ai-verify-assistant-wrapper');
        if (!wrapper) return;
        
        // Check body class
        if (document.body.classList.contains('s-dark')) {
            wrapper.classList.remove('s-light');
            wrapper.classList.add('s-dark');
        } else {
            wrapper.classList.remove('s-dark');
            wrapper.classList.add('s-light');
        }
    };
    
    // Run on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateTheme);
    } else {
        updateTheme();
    }
    
    // Watch for theme changes
    const observer = new MutationObserver(updateTheme);
    observer.observe(document.body, { 
        attributes: true, 
        attributeFilter: ['class'] 
    });
})();
</script>