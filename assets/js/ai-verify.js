/**
 * AI Verify JavaScript
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    // Initialize AI Chatbot
    initChatbot();

    // Load fact-checks
    loadFactChecks();
  });

  function initChatbot() {
    const $chatInput = $("#aiVerifyChatInput");
    const $chatSend = $("#aiVerifyChatSend");
    const $chatContainer = $("#aiVerifyChatContainer");
    const $chatStatus = $("#aiVerifyChatStatus");

    if (!$chatInput.length || !$chatSend.length) {
      return;
    }

    // Send message on button click
    $chatSend.on("click", function () {
      sendMessage();
    });

    // Send message on Enter key
    $chatInput.on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        sendMessage();
      }
    });

    function sendMessage() {
      const message = $chatInput.val().trim();

      if (!message) {
        return;
      }

      // Disable input
      $chatInput.prop("disabled", true);
      $chatSend.prop("disabled", true);
      $chatStatus.text("Thinking...");

      // Add user message to chat
      appendMessage(message, "user");

      // Clear input
      $chatInput.val("");

      // Get article context
      const postTitle = document.title;
      const context = `Article: "${postTitle}"`;

      // Send AJAX request
      $.ajax({
        url: aiVerifyData.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_chat",
          nonce: aiVerifyData.nonce,
          message: message,
          post_id: aiVerifyData.post_id,
          context: context,
        },
        success: function (response) {
          if (response.success) {
            appendMessage(response.data.message, "ai");
            $chatStatus.text("");
          } else {
            appendMessage(
              response.data.message ||
                "Sorry, I encountered an error. Please try again.",
              "ai"
            );
            $chatStatus.text("");
          }
        },
        error: function () {
          appendMessage(
            "Sorry, I encountered a connection error. Please try again.",
            "ai"
          );
          $chatStatus.text("");
        },
        complete: function () {
          $chatInput.prop("disabled", false);
          $chatSend.prop("disabled", false);
          $chatInput.focus();
        },
      });
    }

    function appendMessage(message, type) {
      const className =
        type === "user" ? "ai-verify-user-message" : "ai-verify-ai-message";
      const $message = $("<div>")
        .addClass("ai-verify-chat-message")
        .addClass(className)
        .text(message);

      $chatContainer.append($message);

      // Scroll to bottom
      $chatContainer.scrollTop($chatContainer[0].scrollHeight);
    }
  }

  function loadFactChecks() {
    const $factCheckContainer = $("#aiVerifyFactChecks");

    if (!$factCheckContainer.length) {
      return;
    }

    // Get post title and extract key terms
    let postTitle = document.title
      .split("|")[0]
      .split("–")[0]
      .split("-")[0]
      .trim();

    // Extract key names and topics (remove common words)
    const stopWords = [
      "the",
      "a",
      "an",
      "and",
      "or",
      "but",
      "in",
      "on",
      "at",
      "to",
      "for",
      "of",
      "about",
      "as",
      "by",
      "with",
      "from",
      "never",
      "claimed",
      "makes",
      "false",
      "claims",
      "says",
      "said",
    ];
    const words = postTitle
      .toLowerCase()
      .split(" ")
      .filter((word) => word.length > 3 && !stopWords.includes(word));

    // Use first 3-5 important words
    const searchQuery = words.slice(0, 5).join(" ");

    console.log("Original title:", postTitle);
    console.log("Searching fact-checks for:", searchQuery);

    $.ajax({
      url: aiVerifyData.ajax_url,
      type: "POST",
      data: {
        action: "ai_verify_get_factchecks",
        nonce: aiVerifyData.nonce,
        query: postTitle,
      },
      success: function (response) {
        console.log("Fact-check response:", response);

        if (
          response.success &&
          response.data.factchecks &&
          response.data.factchecks.length > 0
        ) {
          renderFactChecks(response.data.factchecks);
        } else {
          $factCheckContainer.html(
            '<div class="ai-verify-notice">ℹ️ No related fact-checks found for this topic. This could mean the claim hasn\'t been fact-checked yet by major organizations.</div>'
          );
        }
      },
      error: function (xhr, status, error) {
        console.error("Fact-check error:", error);
        $factCheckContainer.html(
          '<div class="ai-verify-notice">⚠️ Unable to load fact-checks. Make sure Google Fact Check API key is configured in Settings → AI Verify.</div>'
        );
      },
    });
  }

  function renderFactChecks(factchecks) {
    const $container = $("#aiVerifyFactChecks");
    $container.empty();

    factchecks.forEach(function (factcheck) {
      const rating = normalizeRating(factcheck.rating);
      const ratingClass = getRatingClass(rating);

      const $item = $("<a>")
        .addClass("ai-verify-factcheck-item")
        .attr("href", factcheck.url)
        .attr("target", "_blank")
        .attr("rel", "noopener noreferrer");

      const $header = $("<div>").addClass("ai-verify-factcheck-header");

      const $content = $("<div>");
      const $claim = $("<div>")
        .addClass("ai-verify-factcheck-claim")
        .text(factcheck.claim);
      const $source = $("<div>")
        .addClass("ai-verify-factcheck-source")
        .text(
          factcheck.source + (factcheck.date ? " · " + factcheck.date : "")
        );

      $content.append($claim).append($source);

      const $badge = $("<span>")
        .addClass("ai-verify-rating-badge")
        .addClass(ratingClass)
        .text(rating);

      $header.append($content).append($badge);
      $item.append($header);

      $container.append($item);
    });
  }

  function normalizeRating(rating) {
    const lowerRating = rating.toLowerCase();

    if (
      lowerRating.includes("false") ||
      lowerRating.includes("incorrect") ||
      lowerRating.includes("fake")
    ) {
      return "False";
    } else if (
      lowerRating.includes("true") ||
      lowerRating.includes("correct") ||
      lowerRating.includes("accurate")
    ) {
      return "True";
    } else if (
      lowerRating.includes("mixture") ||
      lowerRating.includes("mixed") ||
      lowerRating.includes("partly")
    ) {
      return "Mixture";
    } else if (
      lowerRating.includes("unproven") ||
      lowerRating.includes("unverified") ||
      lowerRating.includes("unclear")
    ) {
      return "Unproven";
    }

    return rating;
  }

  function getRatingClass(rating) {
    const lowerRating = rating.toLowerCase();

    if (lowerRating === "false" || lowerRating.includes("incorrect")) {
      return "ai-verify-rating-false";
    } else if (lowerRating === "true" || lowerRating.includes("accurate")) {
      return "ai-verify-rating-true";
    } else {
      return "ai-verify-rating-mixture";
    }
  }
})(jQuery);
