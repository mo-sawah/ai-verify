/**
 * AI Chat Assistant JavaScript
 * Handles chat interface and interactions
 */

(function ($) {
  "use strict";

  const ChatAssistant = {
    sessionId: null,
    isProcessing: false,

    /**
     * Initialize chat assistant
     */
    init: function () {
      console.log("AI Verify: Initializing Chat Assistant");

      // Generate or retrieve session ID
      this.sessionId =
        localStorage.getItem("ai_verify_chat_session") ||
        this.generateSessionId();
      localStorage.setItem("ai_verify_chat_session", this.sessionId);

      // Setup event listeners
      this.setupEventListeners();

      // Load chat history
      this.loadHistory();
    },

    /**
     * Generate session ID
     */
    generateSessionId: function () {
      return (
        "chat_" + Date.now() + "_" + Math.random().toString(36).substring(2, 9)
      );
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function () {
      const self = this;

      // Send button
      $("#chatSendBtn").on("click", function () {
        self.sendMessage();
      });

      // Enter key
      $("#chatInput").on("keypress", function (e) {
        if (e.which === 13 && !e.shiftKey) {
          e.preventDefault();
          self.sendMessage();
        }
      });

      // Clear chat button
      $("#chatClearBtn").on("click", function () {
        if (
          confirm("Are you sure you want to clear the conversation history?")
        ) {
          self.clearChat();
        }
      });

      // Toggle chat (if you want collapsible)
      $("#chatToggleBtn").on("click", function () {
        $("#chatMessages").slideToggle(300);
        $(this).find("svg").toggleClass("rotate-180");
      });

      // Example prompts
      $(".chat-example-prompt").on("click", function () {
        const prompt = $(this).data("prompt");
        $("#chatInput").val(prompt);
        self.sendMessage();
      });
    },

    /**
     * Send message
     */
    sendMessage: function () {
      const message = $("#chatInput").val().trim();

      if (!message || this.isProcessing) {
        return;
      }

      this.isProcessing = true;

      // Add user message to UI
      this.addMessage("user", message);

      // Clear input
      $("#chatInput").val("").prop("disabled", true);

      // Show typing indicator
      this.showTypingIndicator();

      // Disable send button
      $("#chatSendBtn").prop("disabled", true).addClass("loading");

      // Send to backend
      $.ajax({
        url: aiVerifyDashboard.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_chat_message",
          nonce: aiVerifyDashboard.nonce,
          message: message,
          session_id: this.sessionId,
        },
        success: (response) => {
          this.hideTypingIndicator();

          if (response.success) {
            this.addMessage(
              "assistant",
              response.data.response,
              response.data.tools_used,
              response.data.sources
            );
          } else {
            this.addMessage(
              "error",
              response.data.message || "Failed to get response"
            );
          }

          this.isProcessing = false;
          $("#chatInput").prop("disabled", false).focus();
          $("#chatSendBtn").prop("disabled", false).removeClass("loading");
        },
        error: (xhr, status, error) => {
          console.error("Chat error:", error);
          this.hideTypingIndicator();
          this.addMessage(
            "error",
            "Connection error. Please check your internet and try again."
          );

          this.isProcessing = false;
          $("#chatInput").prop("disabled", false).focus();
          $("#chatSendBtn").prop("disabled", false).removeClass("loading");
        },
      });
    },

    /**
     * Add message to chat
     */
    addMessage: function (role, content, tools = [], sources = []) {
      const $messages = $("#chatMessages");
      const timestamp = new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      });

      let messageHtml = "";

      if (role === "user") {
        messageHtml = `
                <div class="chat-message user-message">
                    <div class="message-avatar">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                        </svg>
                    </div>
                    <div class="message-content">
                        <div class="message-text">${this.escapeHtml(
                          content
                        )}</div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                </div>
            `;
      } else if (role === "assistant") {
        // Parse markdown in assistant messages
        const formattedContent = this.parseMarkdown(content);

        let toolsHtml = "";
        if (tools && tools.length > 0) {
          const toolIcons = {
            tavily: "🔍",
            firecrawl: "🌐",
            database: "💾",
          };

          toolsHtml =
            '<div class="tools-used">' +
            tools
              .map(
                (tool) =>
                  `<span class="tool-badge">${
                    toolIcons[tool] || "🔧"
                  } ${tool}</span>`
              )
              .join("") +
            "</div>";
        }

        let sourcesHtml = "";
        if (sources && sources.length > 0) {
          sourcesHtml =
            '<div class="message-sources"><div class="sources-title">📚 Sources:</div>';
          sources.forEach((source) => {
            sourcesHtml += `<a href="${
              source.url
            }" target="_blank" class="source-link">${
              source.title || source.url
            }</a>`;
          });
          sourcesHtml += "</div>";
        }

        messageHtml = `
                <div class="chat-message assistant-message">
                    <div class="message-avatar">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                        </svg>
                    </div>
                    <div class="message-content">
                        ${toolsHtml}
                        <div class="message-text">${formattedContent}</div>
                        ${sourcesHtml}
                        <div class="message-time">${timestamp}</div>
                    </div>
                </div>
            `;
      } else if (role === "error") {
        messageHtml = `
                <div class="chat-message error-message">
                    <div class="message-avatar">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"/>
                        </svg>
                    </div>
                    <div class="message-content">
                        <div class="message-text">${this.escapeHtml(
                          content
                        )}</div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                </div>
            `;
      }

      $messages.append(messageHtml);
      this.scrollToBottom();
    },

    /**
     * Show typing indicator
     */
    showTypingIndicator: function () {
      const typingHtml = `
            <div class="chat-message assistant-message typing-indicator" id="typingIndicator">
                <div class="message-avatar">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                    </svg>
                </div>
                <div class="message-content">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        `;

      $("#chatMessages").append(typingHtml);
      this.scrollToBottom();
    },

    /**
     * Hide typing indicator
     */
    hideTypingIndicator: function () {
      $("#typingIndicator").remove();
    },

    /**
     * Scroll to bottom
     */
    scrollToBottom: function () {
      const $messages = $("#chatMessages");
      $messages.animate(
        {
          scrollTop: $messages[0].scrollHeight,
        },
        300
      );
    },

    /**
     * Parse markdown (simple implementation)
     */
    parseMarkdown: function (text) {
      // Bold
      text = text.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");

      // Bullet points
      text = text.replace(/^- (.*?)$/gm, "<li>$1</li>");
      text = text.replace(/(<li>.*<\/li>)/s, "<ul>$1</ul>");

      // Line breaks
      text = text.replace(/\n/g, "<br>");

      return text;
    },

    /**
     * Escape HTML
     */
    escapeHtml: function (text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },

    /**
     * Load chat history
     */
    loadHistory: function () {
      $.ajax({
        url: aiVerifyDashboard.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_chat_history",
          nonce: aiVerifyDashboard.nonce,
          session_id: this.sessionId,
        },
        success: (response) => {
          if (response.success && response.data.history.length > 0) {
            response.data.history.forEach((msg) => {
              this.addMessage(msg.role, msg.content);
            });
          } else {
            // Show welcome message
            this.showWelcomeMessage();
          }
        },
      });
    },

    /**
     * Show welcome message
     */
    showWelcomeMessage: function () {
      const welcomeMsg = `👋 Hello! I'm your AI fact-checking assistant. I can help you:

**💾 Query our database** of verified claims and propaganda techniques
**🔍 Search the web** for the latest information  
**🌐 Analyze URLs** you provide
**📊 Explain trends** and credibility scores

Try asking me about current misinformation trends, or give me a URL to analyze!`;

      this.addMessage("assistant", welcomeMsg);
    },

    /**
     * Clear chat
     */
    clearChat: function () {
      $("#chatMessages").empty();
      this.sessionId = this.generateSessionId();
      localStorage.setItem("ai_verify_chat_session", this.sessionId);
      this.showWelcomeMessage();
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    // Only initialize if chat container exists
    if ($("#chatAssistant").length > 0) {
      ChatAssistant.init();
    }
  });
})(jQuery);
