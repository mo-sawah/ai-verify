/**
 * AI Verify - Advanced Frontend Chat Assistant
 * Professional multi-tool AI with streaming, history, and export
 */

(function ($) {
  "use strict";

  const ChatAssistant = {
    // State management
    currentSessionId: null,
    sessions: {},
    isProcessing: false,
    abortController: null,

    // Configuration
    maxHistoryLength: 20,
    autoSaveInterval: null,

    /**
     * Initialize
     */
    init: function () {
      if ($(".ai-verify-assistant-wrapper").length === 0) return;

      console.log("AI Verify: Initializing Advanced Chat Assistant");

      this.setupEventListeners();
      this.loadSessionsFromStorage();
      this.startNewChat();
      this.startAutoSave();
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function () {
      const self = this;

      // Send message
      $("#chatSendBtn").on("click", () => self.sendMessage());

      // Enter to send (Shift+Enter for new line)
      $("#chatInput").on("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          self.sendMessage();
        }
      });

      // Auto-resize textarea
      $("#chatInput").on("input", function () {
        this.style.height = "auto";
        this.style.height = Math.min(this.scrollHeight, 150) + "px";
      });

      // New chat button
      $("#newChatBtn").on("click", () => self.startNewChat());

      // Stop generation button
      $("#stopGeneratingBtn").on("click", () => self.stopGeneration());

      // Conversation item click
      $(document).on("click", ".conversation-item", function () {
        const sessionId = $(this).data("session-id");
        self.loadSession(sessionId);
      });

      // Delete conversation
      $(document).on("click", ".delete-conversation", function (e) {
        e.stopPropagation();
        const sessionId = $(this)
          .closest(".conversation-item")
          .data("session-id");
        self.deleteSession(sessionId);
      });

      // Example prompts
      $(".chat-example-prompt").on("click", function () {
        const prompt = $(this).data("prompt");
        $("#chatInput").val(prompt).trigger("input").focus();
      });

      // Export conversation
      $(document).on("click", "#exportConversationBtn", () =>
        self.exportConversation()
      );

      // Clear all conversations
      $(document).on("click", "#clearAllBtn", () => self.clearAllSessions());
    },

    /**
     * Start new chat session
     */
    startNewChat: function () {
      this.currentSessionId =
        "session_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9);

      this.sessions[this.currentSessionId] = {
        title: "New Investigation",
        history: [],
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      };

      this.clearChatMessages();
      this.addMessage(
        "assistant",
        "👋 **Welcome! I'm your AI fact-checking partner.**\n\n" +
          "I can help you:\n\n" +
          "🔍 **Verify claims** - Search our database of verified fact-checks\n" +
          "🌐 **Analyze websites** - Paste any URL for instant credibility analysis\n" +
          "📰 **Research topics** - Access real-time web search and news\n" +
          "🎯 **Detect propaganda** - Identify manipulation techniques in content\n" +
          "📚 **Search articles** - Find relevant content from our knowledge base\n\n" +
          "_Just ask a question, paste a URL, or try one of the examples below!_"
      );

      this.updateSidebarUI();
      this.saveToStorage();
    },

    /**
     * Send message
     */
    sendMessage: function () {
      const messageText = $("#chatInput").val().trim();

      if (!messageText || this.isProcessing) return;

      // Prevent sending
      this.isProcessing = true;
      $("#chatInput").val("").trigger("input").prop("disabled", true);
      $("#chatSendBtn").prop("disabled", true);
      $("#stopGeneratingBtn").show();

      // Add user message to UI and history
      this.addMessage("user", messageText);

      // Show typing indicator
      this.showTypingIndicator();

      // Create abort controller
      this.abortController = new AbortController();

      // Prepare conversation history (last 20 messages, excluding the current one we just added)
      const allHistory = this.sessions[this.currentSessionId].history;
      const history = allHistory.slice(0, -1).slice(-20); // Exclude last message (current), then take last 20

      // Send AJAX request
      $.ajax({
        url: aiVerifyAssistant.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_assistant_chat",
          nonce: aiVerifyAssistant.nonce,
          message: messageText,
          history: JSON.stringify(history),
          session_id: this.currentSessionId,
        },
        success: (response) => {
          this.hideTypingIndicator();

          if (response.success) {
            this.addMessage(
              "assistant",
              response.data.message,
              response.data.tools_used,
              response.data.sources
            );

            // Update session title if first message
            if (
              this.sessions[this.currentSessionId].history.filter(
                (m) => m.role === "user"
              ).length === 1
            ) {
              this.sessions[this.currentSessionId].title =
                messageText.substring(0, 50) +
                (messageText.length > 50 ? "..." : "");
              this.updateSidebarUI();
            }
          } else {
            this.addMessage(
              "error",
              response.data.message || "An error occurred. Please try again."
            );
          }
        },
        error: (xhr, status) => {
          this.hideTypingIndicator();

          if (status === "abort") {
            this.addMessage("system", "⛔ Generation stopped by user.");
          } else {
            this.addMessage(
              "error",
              "Connection error. Please check your internet and try again."
            );
          }
        },
        complete: () => {
          this.isProcessing = false;
          $("#chatInput").prop("disabled", false).focus();
          $("#chatSendBtn").prop("disabled", false);
          $("#stopGeneratingBtn").hide();
        },
      });
    },

    /**
     * Stop generation
     */
    stopGeneration: function () {
      if (this.abortController) {
        this.abortController.abort();
      }
    },

    /**
     * Add message to chat
     */
    addMessage: function (role, content, toolsUsed = [], sources = []) {
      const timestamp = new Date();
      const timeString = timestamp.toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      });

      // Add to session history
      if (role === "user" || role === "assistant") {
        this.sessions[this.currentSessionId].history.push({
          role: role,
          content: content,
          timestamp: timestamp.toISOString(),
          toolsUsed: toolsUsed,
          sources: sources,
        });

        this.sessions[this.currentSessionId].updatedAt =
          timestamp.toISOString();
        this.saveToStorage();
      }

      // Render message
      this.renderMessage(role, content, timeString, toolsUsed, sources);
    },

    /**
     * Render message in UI
     */
    renderMessage: function (
      role,
      content,
      timeString,
      toolsUsed = [],
      sources = []
    ) {
      const $messagesContainer = $("#chatMessages");

      // Parse markdown
      const formattedContent = this.parseMarkdown(content);

      // Build tools HTML
      let toolsHtml = "";
      if (toolsUsed && toolsUsed.length > 0) {
        toolsHtml = '<div class="tools-used">';
        toolsUsed.forEach((tool) => {
          const toolIcons = {
            web_search: "🔍",
            web_scrape: "🌐",
            database_query: "💾",
            post_search: "📄",
          };
          const icon = toolIcons[tool] || "🔧";
          toolsHtml += `<span class="tool-badge">${icon} ${this.formatToolName(
            tool
          )}</span>`;
        });
        toolsHtml += "</div>";
      }

      // Build sources HTML
      let sourcesHtml = "";
      if (sources && sources.length > 0) {
        sourcesHtml = '<div class="message-sources">';
        sourcesHtml += '<div class="sources-title">📚 Sources:</div>';
        sources.forEach((source) => {
          sourcesHtml += `<a href="${source.url}" target="_blank" class="source-link" title="${source.title}">${source.title}</a>`;
        });
        sourcesHtml += "</div>";
      }

      // Build message HTML
      const avatarMap = {
        user: "You",
        assistant: "AI",
        system: "ℹ️",
        error: "⚠️",
      };

      const messageHtml = `
        <div class="chat-message message-${role}" data-timestamp="${new Date().toISOString()}">
          <div class="message-avatar">${avatarMap[role] || "AI"}</div>
          <div class="message-content">
            ${toolsHtml}
            <div class="message-text">${formattedContent}</div>
            ${sourcesHtml}
            <div class="message-time">${timeString}</div>
          </div>
        </div>
      `;

      $messagesContainer.append(messageHtml);
      this.scrollToBottom();

      // Animate new message
      $messagesContainer.find(".chat-message:last-child").hide().fadeIn(300);
    },

    /**
     * Show typing indicator
     */
    showTypingIndicator: function () {
      const typingHtml = `
        <div class="chat-message message-assistant typing-indicator" id="typingIndicator">
          <div class="message-avatar">AI</div>
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
     * Parse markdown to HTML
     */
    parseMarkdown: function (text) {
      // Bold
      text = text.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");

      // Italic
      text = text.replace(/\*(.*?)\*/g, "<em>$1</em>");

      // Code blocks
      text = text.replace(/```([\s\S]*?)```/g, "<pre><code>$1</code></pre>");

      // Inline code
      text = text.replace(/`(.*?)`/g, "<code>$1</code>");

      // Links
      text = text.replace(
        /\[([^\]]+)\]\(([^)]+)\)/g,
        '<a href="$2" target="_blank">$1</a>'
      );

      // Lists (bullet points)
      text = text.replace(/^- (.*?)$/gm, "<li>$1</li>");
      text = text.replace(/(<li>.*<\/li>)/s, function (match) {
        return "<ul>" + match + "</ul>";
      });

      // Remove duplicate ul tags
      text = text.replace(/<\/ul>\s*<ul>/g, "");

      // Line breaks
      text = text.replace(/\n/g, "<br>");

      return text;
    },

    /**
     * Format tool name
     */
    formatToolName: function (tool) {
      const names = {
        web_search: "Web Search",
        web_scrape: "Website Analysis",
        database_query: "Database Query",
        post_search: "Article Search",
      };
      return names[tool] || tool;
    },

    /**
     * Scroll to bottom
     */
    scrollToBottom: function () {
      const $messages = $("#chatMessages");
      $messages.animate({ scrollTop: $messages[0].scrollHeight }, 300);
    },

    /**
     * Clear chat messages
     */
    clearChatMessages: function () {
      $("#chatMessages").empty();
    },

    /**
     * Load session
     */
    loadSession: function (sessionId) {
      if (!this.sessions[sessionId]) return;

      this.currentSessionId = sessionId;
      this.clearChatMessages();

      // Mark as active in sidebar
      $(".conversation-item").removeClass("active");
      $(`.conversation-item[data-session-id="${sessionId}"]`).addClass(
        "active"
      );

      // Render all messages
      const session = this.sessions[sessionId];
      session.history.forEach((msg) => {
        const time = new Date(msg.timestamp).toLocaleTimeString([], {
          hour: "2-digit",
          minute: "2-digit",
        });
        this.renderMessage(
          msg.role,
          msg.content,
          time,
          msg.toolsUsed || [],
          msg.sources || []
        );
      });
    },

    /**
     * Delete session
     */
    deleteSession: function (sessionId) {
      if (!confirm("Delete this conversation?")) return;

      delete this.sessions[sessionId];
      this.saveToStorage();

      // If current session, start new one
      if (this.currentSessionId === sessionId) {
        this.startNewChat();
      } else {
        this.updateSidebarUI();
      }
    },

    /**
     * Clear all sessions
     */
    clearAllSessions: function () {
      if (!confirm("Delete ALL conversations? This cannot be undone.")) return;

      this.sessions = {};
      this.saveToStorage();
      this.startNewChat();
    },

    /**
     * Update sidebar UI
     */
    updateSidebarUI: function () {
      const $historyContainer = $("#conversationHistory");
      $historyContainer.empty();

      // Sort sessions by updated date
      const sortedSessions = Object.keys(this.sessions).sort((a, b) => {
        return (
          new Date(this.sessions[b].updatedAt) -
          new Date(this.sessions[a].updatedAt)
        );
      });

      if (sortedSessions.length === 0) {
        $historyContainer.html(
          '<p class="no-history">Your conversations will appear here.</p>'
        );
        return;
      }

      sortedSessions.forEach((sessionId) => {
        const session = this.sessions[sessionId];
        const activeClass = sessionId === this.currentSessionId ? "active" : "";
        const date = new Date(session.updatedAt);
        const timeAgo = this.getTimeAgo(date);

        const html = `
          <div class="conversation-item ${activeClass}" data-session-id="${sessionId}">
            <div class="conversation-info">
              <div class="conversation-title">${session.title}</div>
              <div class="conversation-time">${timeAgo}</div>
            </div>
            <button class="delete-conversation" title="Delete">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"/>
              </svg>
            </button>
          </div>
        `;

        $historyContainer.append(html);
      });
    },

    /**
     * Get time ago string
     */
    getTimeAgo: function (date) {
      const seconds = Math.floor((new Date() - date) / 1000);

      if (seconds < 60) return "Just now";
      if (seconds < 3600) return Math.floor(seconds / 60) + "m ago";
      if (seconds < 86400) return Math.floor(seconds / 3600) + "h ago";
      if (seconds < 604800) return Math.floor(seconds / 86400) + "d ago";

      return date.toLocaleDateString();
    },

    /**
     * Save to localStorage
     */
    saveToStorage: function () {
      try {
        localStorage.setItem(
          "aiVerifyAssistantSessions",
          JSON.stringify(this.sessions)
        );
      } catch (e) {
        console.error("Failed to save sessions:", e);
      }
    },

    /**
     * Load from localStorage
     */
    loadSessionsFromStorage: function () {
      try {
        const saved = localStorage.getItem("aiVerifyAssistantSessions");
        if (saved) {
          this.sessions = JSON.parse(saved);
        }
      } catch (e) {
        console.error("Failed to load sessions:", e);
        this.sessions = {};
      }
    },

    /**
     * Start auto-save interval
     */
    startAutoSave: function () {
      // Auto-save every 30 seconds
      this.autoSaveInterval = setInterval(() => {
        this.saveToStorage();
      }, 30000);
    },

    /**
     * Export conversation
     */
    exportConversation: function () {
      if (!this.currentSessionId) {
        alert("No conversation to export");
        return;
      }

      const session = this.sessions[this.currentSessionId];
      const exportData = {
        title: session.title,
        createdAt: session.createdAt,
        updatedAt: session.updatedAt,
        messages: session.history.map((msg) => ({
          role: msg.role,
          content: msg.content,
          timestamp: msg.timestamp,
          toolsUsed: msg.toolsUsed || [],
          sources: msg.sources || [],
        })),
      };

      // Create formatted text
      let textContent = `AI Verify Chat Export\n`;
      textContent += `Title: ${session.title}\n`;
      textContent += `Created: ${new Date(
        session.createdAt
      ).toLocaleString()}\n`;
      textContent += `Updated: ${new Date(
        session.updatedAt
      ).toLocaleString()}\n`;
      textContent += `\n${"=".repeat(60)}\n\n`;

      exportData.messages.forEach((msg, idx) => {
        textContent += `[${msg.role.toUpperCase()}] ${new Date(
          msg.timestamp
        ).toLocaleString()}\n`;
        if (msg.toolsUsed && msg.toolsUsed.length > 0) {
          textContent += `Tools: ${msg.toolsUsed.join(", ")}\n`;
        }
        textContent += `${msg.content}\n\n`;
        if (msg.sources && msg.sources.length > 0) {
          textContent += `Sources:\n`;
          msg.sources.forEach((source) => {
            textContent += `  - ${source.title}: ${source.url}\n`;
          });
          textContent += `\n`;
        }
        textContent += `${"-".repeat(60)}\n\n`;
      });

      // Download as text file
      const blob = new Blob([textContent], { type: "text/plain" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `chat-export-${Date.now()}.txt`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);

      // Show success message
      this.showNotification("✅ Conversation exported successfully!");
    },

    /**
     * Show notification
     */
    showNotification: function (message) {
      const $notification = $(`
        <div class="chat-notification">
          ${message}
        </div>
      `);

      $("body").append($notification);

      setTimeout(() => {
        $notification.addClass("show");
      }, 10);

      setTimeout(() => {
        $notification.removeClass("show");
        setTimeout(() => $notification.remove(), 300);
      }, 3000);
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    ChatAssistant.init();
  });
})(jQuery);
