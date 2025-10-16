/**
 * AI Verify - Frontend Assistant Shortcode JavaScript
 */
(function ($) {
  "use strict";

  const AssistantShortcode = {
    currentSessionId: null,
    sessions: {},
    isProcessing: false,
    apiAbortController: null,

    init: function () {
      if ($(".ai-verify-assistant-wrapper").length === 0) return;
      console.log("AI Verify: Initializing Frontend Assistant");

      this.setupEventListeners();
      this.loadSessions();
      this.startNewChat();
    },

    setupEventListeners: function () {
      const self = this;
      $("#chatSendBtn").on("click", () => self.sendMessage());
      $("#chatInput").on("keypress", (e) => {
        if (e.which === 13 && !e.shiftKey) {
          e.preventDefault();
          self.sendMessage();
        }
      });
      $("#newChatBtn").on("click", () => self.startNewChat());
      $("#stopGeneratingBtn").on("click", () => self.stopGeneration());

      $(document).on("click", ".conversation-item", function () {
        const sessionId = $(this).data("session-id");
        self.loadChat(sessionId);
      });

      $(".chat-example-prompt").on("click", function () {
        $("#chatInput").val($(this).data("prompt")).focus();
        self.sendMessage();
      });
    },

    startNewChat: function () {
      this.currentSessionId = "session_" + Date.now();
      this.sessions[this.currentSessionId] = {
        title: "New Investigation",
        history: [],
      };
      this.addMessage(
        "assistant",
        "Hello! I'm your AI assistant. How can I help you investigate a claim or analyze information today?"
      );
      this.updateConversationListUI();
      this.loadChat(this.currentSessionId);
    },

    loadChat: function (sessionId) {
      if (!this.sessions[sessionId]) return;
      this.currentSessionId = sessionId;

      $(".conversation-item.active").removeClass("active");
      $(`.conversation-item[data-session-id="${sessionId}"]`).addClass(
        "active"
      );

      const chatHistory = this.sessions[sessionId].history;
      const $messagesContainer = $("#chatMessages");
      $messagesContainer.empty();
      chatHistory.forEach((msg) =>
        this.renderMessage(msg.role, msg.content, msg.tools, msg.sources)
      );
    },

    sendMessage: function () {
      const messageText = $("#chatInput").val().trim();
      if (!messageText || this.isProcessing) return;

      this.isProcessing = true;
      this.addMessage("user", messageText);
      $("#chatInput").val("").prop("disabled", true);
      $("#chatSendBtn").prop("disabled", true);
      $("#stopGeneratingBtn").show();
      this.showTypingIndicator();

      this.apiAbortController = new AbortController();

      $.ajax({
        url: aiVerifyAssistant.ajax_url,
        type: "POST",
        signal: this.apiAbortController.signal,
        data: {
          action: "ai_verify_public_chat_message",
          nonce: aiVerifyAssistant.nonce,
          message: messageText,
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
              response.data.message || "An error occurred."
            );
          }
        },
        error: (xhr) => {
          if (xhr.statusText === "abort") {
            this.hideTypingIndicator();
            this.addMessage("error", "Generation stopped by user.");
          } else {
            this.hideTypingIndicator();
            this.addMessage(
              "error",
              "A connection error occurred. Please try again."
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

    stopGeneration: function () {
      if (this.apiAbortController) {
        this.apiAbortController.abort();
      }
    },

    addMessage: function (role, content, tools = [], sources = []) {
      if (role !== "system") {
        this.sessions[this.currentSessionId].history.push({
          role,
          content,
          tools,
          sources,
        });
        if (
          role === "user" &&
          this.sessions[this.currentSessionId].history.length === 1
        ) {
          const newTitle =
            content.substring(0, 30) + (content.length > 30 ? "..." : "");
          this.sessions[this.currentSessionId].title = newTitle;
          this.updateConversationListUI();
        }
      }
      this.renderMessage(role, content, tools, sources);
      this.saveSessions();
    },

    renderMessage: function (role, content, tools = [], sources = []) {
      const $messagesContainer = $("#chatMessages");
      const timestamp = new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      });

      const formattedContent = this.parseMarkdown(content);
      let toolsHtml =
        tools.length > 0
          ? `<div class="tools-used">${tools
              .map((tool) => `<span class="tool-badge">${tool}</span>`)
              .join("")}</div>`
          : "";
      let sourcesHtml =
        sources.length > 0
          ? `<div class="message-sources"><div class="sources-title">Sources:</div>${sources
              .map(
                (source) =>
                  `<a href="${
                    source.url
                  }" target="_blank" class="source-link">${
                    source.title || source.url
                  }</a>`
              )
              .join("")}</div>`
          : "";

      const messageHtml = `
                <div class="chat-message message-${role}">
                    <div class="message-avatar">${
                      role === "user" ? "You" : "AI"
                    }</div>
                    <div class="message-content">
                        ${toolsHtml}
                        <div class="message-text">${formattedContent}</div>
                        ${sourcesHtml}
                        <div class="message-time">${timestamp}</div>
                    </div>
                </div>
            `;
      $messagesContainer.append(messageHtml);
      this.scrollToBottom();
    },

    showTypingIndicator: function () {
      const typingHtml = `<div class="chat-message message-assistant typing-indicator" id="typingIndicator"><div class="message-avatar">AI</div><div class="message-content"><div class="typing-dots"><span></span><span></span><span></span></div></div></div>`;
      $("#chatMessages").append(typingHtml);
      this.scrollToBottom();
    },

    hideTypingIndicator: function () {
      $("#typingIndicator").remove();
    },

    parseMarkdown: function (text) {
      text = text.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
      text = text.replace(/\*(.*?)\*/g, "<em>$1</em>");
      text = text.replace(/^- (.*?)$/gm, "<li>$1</li>");
      text = text
        .replace(/(<li>.*<\/li>)/s, "<ul>$1</ul>")
        .replace(/<\/ul>\s?<ul>/g, "");
      return text.replace(/\n/g, "<br>");
    },

    scrollToBottom: function () {
      const $messages = $("#chatMessages");
      $messages.scrollTop($messages[0].scrollHeight);
    },

    loadSessions: function () {
      const savedSessions = localStorage.getItem("aiVerifyAssistantSessions");
      if (savedSessions) {
        this.sessions = JSON.parse(savedSessions);
      }
      this.updateConversationListUI();
    },

    saveSessions: function () {
      localStorage.setItem(
        "aiVerifyAssistantSessions",
        JSON.stringify(this.sessions)
      );
    },

    updateConversationListUI: function () {
      const $historyContainer = $("#conversationHistory");
      $historyContainer.empty();
      const sortedSessions = Object.keys(this.sessions).sort().reverse();

      if (sortedSessions.length === 0) {
        $historyContainer.html(
          '<p class="no-history">Your conversations will appear here.</p>'
        );
        return;
      }

      sortedSessions.forEach((sessionId) => {
        const session = this.sessions[sessionId];
        const activeClass = sessionId === this.currentSessionId ? "active" : "";
        $historyContainer.append(
          `<div class="conversation-item ${activeClass}" data-session-id="${sessionId}">${session.title}</div>`
        );
      });
    },
  };

  $(document).ready(() => AssistantShortcode.init());
})(jQuery);
