<?php
/**
 * Template for the Frontend AI Assistant Shortcode
 */
?>
<div class="ai-verify-assistant-wrapper s-light"> <div class="assistant-hero">
        <div class="hero-content">
            <h1 class="hero-title">
                <svg width="32" height="32" fill="currentColor" viewBox="0 0 20 20" style="display: inline-block; vertical-align: middle; margin-right: 12px;">
                    <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z"/>
                </svg>
                AI Fact-Check Assistant
            </h1>
            <p class="hero-subtitle">Your intelligent partner for navigating the information landscape. Analyze URLs, ask questions, and get clarity on complex topics.</p>
        </div>
    </div>

    <div class="assistant-container">
        <div class="assistant-sidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-title">My Investigations</h2>
                <button id="newChatBtn" class="new-chat-btn" title="Start a New Investigation">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
                    New Chat
                </button>
            </div>
            <div class="conversation-history" id="conversationHistory">
                <p class="no-history">Your conversations will appear here.</p>
            </div>
        </div>

        <div class="chat-main">
            <div class="chat-messages-main" id="chatMessages">
                </div>

            <div class="chat-input-area">
                <button id="stopGeneratingBtn" class="stop-generating-btn" style="display: none;">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3.75A1.25 1.25 0 003.75 5v10A1.25 1.25 0 005 16.25h10A1.25 1.25 0 0016.25 15V5A1.25 1.25 0 0015 3.75H5z"/></svg>
                    Stop
                </button>
                <div class="chat-input-container-main">
                    <textarea id="chatInput" placeholder="Analyze a URL or ask a question (e.g., 'Is it true that...?')" rows="1"></textarea>
                    <button id="chatSendBtn" title="Send Message">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z"/></svg>
                    </button>
                </div>
                 <div class="chat-examples">
                    <button class="chat-example-prompt" data-prompt="What are the top 3 viral misinformation claims this week?">Top 3 viral claims</button>
                    <button class="chat-example-prompt" data-prompt="Analyze this URL for propaganda: https://www.some-news-site.com/article">Analyze a URL</button>
                    <button class="chat-example-prompt" data-prompt="Explain the 'Strawman' fallacy.">Explain a concept</button>
                </div>
            </div>
        </div>
    </div>
</div>