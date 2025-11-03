<?php
/**
 * Template for the Dedicated AI Fact-Check Assistant Page
 */
?>
<div class="wrap ai-verify-assistant-page s-light"> <div class="assistant-container">
        <div class="assistant-sidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-title">Investigations</h2>
                <button id="newChatBtn" class="new-chat-btn" title="Start New Investigation">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
                    New Chat
                </button>
            </div>
            <div class="conversation-history" id="conversationHistory">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                </div>
            </div>
            <div class="sidebar-footer">
                <p>AI Verify Assistant v2.0</p>
            </div>
        </div>

        <div class="chat-main">
            <div class="chat-header">
                <h1 id="chatTitle" class="chat-title-main">Untitled Investigation</h1>
                <div class="chat-main-actions">
                    <button id="exportChatBtn" class="chat-action-btn-main" title="Export Conversation">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>
                        Export
                    </button>
                </div>
            </div>
            <div class="chat-messages-main" id="chatMessages">
                </div>

            <div class="chat-input-area">
                <button id="stopGeneratingBtn" class="stop-generating-btn" style="display: none;">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3.75A1.25 1.25 0 003.75 5v10A1.25 1.25 0 005 16.25h10A1.25 1.25 0 0016.25 15V5A1.25 1.25 0 0015 3.75H5z"/></svg>
                    Stop Generating
                </button>
                <div class="chat-input-container-main">
                    <textarea id="chatInput" placeholder="Ask about trends, analyze a URL, or request a debrief..." rows="1"></textarea>
                    <button id="chatSendBtn">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z"/></svg>
                    </button>
                </div>
                 <div class="chat-examples">
                    <button class="chat-example-prompt" data-prompt="What are the top 3 viral claims today? Generate a table.">Top 3 viral claims</button>
                    <button class="chat-example-prompt" data-prompt="Analyze https://example.com/news-article for propaganda.">Analyze URL</button>
                    <button class="chat-example-prompt" data-prompt="Compare health vs. politics misinformation velocity.">Compare categories</button>
                </div>
            </div>
        </div>
    </div>
</div>