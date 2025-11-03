/**
 * AI Verify - Dedicated Assistant Page JavaScript
 */
(function ($) {
  "use strict";

  const AssistantPage = {
    currentSessionId: null,
    sessions: {},
    isProcessing: false,
    // Add other properties as needed

    init: function () {
      console.log("AI Verify: Initializing Dedicated Assistant Page");
      this.detectTheme();
      this.setupEventListeners();
      this.loadSessions();
      this.startNewChat(); // Start with a fresh chat
    },

    detectTheme: function () {
      // Logic to sync with WP admin theme if needed
      if ($("body").hasClass("s-dark")) {
        $(".ai-verify-assistant-page")
          .removeClass("s-light")
          .addClass("s-dark");
      }
    },

    setupEventListeners: function () {
      // Bind events for send button, new chat, conversation history clicks, etc.
      $("#chatSendBtn").on("click", () => this.sendMessage());
      $("#chatInput").on("keypress", (e) => {
        if (e.which === 13 && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });
      $("#newChatBtn").on("click", () => this.startNewChat());
      // More listeners for export, session clicks, etc.
    },

    startNewChat: function () {
      this.currentSessionId = "chat_" + Date.now();
      this.sessions[this.currentSessionId] = {
        title: "Untitled Investigation",
        history: [],
      };
      this.renderChatInterface();
      this.addMessage(
        "assistant",
        "I'm ready to assist with your investigation. How can I help you today?"
      );
      this.updateConversationHistoryUI();
    },

    sendMessage: function () {
      const messageText = $("#chatInput").val().trim();
      if (!messageText || this.isProcessing) return;

      this.isProcessing = true;
      this.addMessage("user", messageText);
      $("#chatInput").val("").prop("disabled", true);
      $("#chatSendBtn").prop("disabled", true);
      this.showTypingIndicator();

      // Get current conversation history to send as context
      const currentHistory = this.sessions[this.currentSessionId].history;

      $.ajax({
        url: aiVerifyDashboard.ajax_url, // You'll need to localize this
        type: "POST",
        data: {
          action: "ai_verify_assistant_page_message",
          nonce: aiVerifyDashboard.assistant_nonce, // Localize a new nonce
          message: messageText,
          session_id: this.currentSessionId,
          history: JSON.stringify(currentHistory),
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
        error: () => {
          this.hideTypingIndicator();
          this.addMessage("error", "A connection error occurred.");
        },
        complete: () => {
          this.isProcessing = false;
          $("#chatInput").prop("disabled", false).focus();
          $("#chatSendBtn").prop("disabled", false);
        },
      });
    },

    addMessage: function (role, content, tools = [], sources = []) {
      // Add message to the current session's history
      if (role !== "error") {
        // Don't save errors to history
        this.sessions[this.currentSessionId].history.push({ role, content });
      }

      // Advanced rendering logic for tables, code blocks, etc.
      const formattedContent = this.parseMarkdown(content);

      // Build and append the message HTML to #chatMessages
      // ... (similar to your existing chat-assistant.js but more advanced)

      this.scrollToBottom();
      this.saveSessions();
    },

    // --- Other necessary functions ---
    // parseMarkdown(text) -> to handle tables, etc.
    // showTypingIndicator()
    // hideTypingIndicator()
    // scrollToBottom()
    // loadSessions() -> from localStorage
    // saveSessions() -> to localStorage
    // updateConversationHistoryUI() -> renders the sidebar
    // renderChatInterface() -> loads a session into the main view
  };

  $(document).ready(() => AssistantPage.init());
})(jQuery);
