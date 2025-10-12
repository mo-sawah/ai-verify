/**
 * Misinformation Tracker Widget JavaScript
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    // Initialize all widgets on the page
    $(".misinfo-widget").each(function () {
      initMisinfoWidget($(this));
    });
  });

  function initMisinfoWidget($widget) {
    const limit = $widget.data("limit") || 5;

    // Load misinformation data
    loadMisinformation($widget, limit);

    // Setup filter handlers
    $widget.find(".filter-badge").on("click", function () {
      const $badge = $(this);
      const filter = $badge.data("filter");

      // Update active state
      $widget.find(".filter-badge").removeClass("active");
      $badge.addClass("active");

      // Filter items
      filterItems($widget, filter);
    });
  }

  function loadMisinformation($widget, limit) {
    const $content = $widget.find(".widget-content");

    $.ajax({
      url: aiVerifyMisinfo.ajax_url,
      type: "POST",
      data: {
        action: "ai_verify_get_misinformation",
        nonce: aiVerifyMisinfo.nonce,
        limit: limit,
      },
      success: function (response) {
        if (
          response.success &&
          response.data.items &&
          response.data.items.length > 0
        ) {
          renderItems($content, response.data.items);
        } else {
          showEmpty($content);
        }
      },
      error: function () {
        showError($content);
      },
    });
  }

  function renderItems($content, items) {
    $content.empty();

    items.forEach(function (item) {
      const $item = createItemElement(item);
      $content.append($item);
    });
  }

  function createItemElement(item) {
    const rating = normalizeRating(item.rating);
    const ratingClass = getRatingClass(rating);
    const filterType = getFilterType(rating);

    const $item = $("<div>")
      .addClass("misinfo-item")
      .attr("data-filter-type", filterType)
      .attr("data-url", item.url);

    // Click handler
    $item.on("click", function () {
      if (item.url && item.url !== "#") {
        window.open(item.url, "_blank", "noopener,noreferrer");
      }
    });

    // Header
    const $header = $("<div>").addClass("item-header");
    const $rating = $("<div>")
      .addClass("item-rating")
      .addClass(ratingClass)
      .text(rating);
    $header.append($rating);

    // Claim
    const $claim = $("<div>").addClass("item-claim").text(item.claim);

    // Meta
    const $meta = $("<div>").addClass("item-meta");

    // Source
    const $source = $("<div>").addClass("item-source").html(`
            <svg fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path>
            </svg>
            <span>${escapeHtml(item.source)}</span>
        `);

    // Date
    const $date = $("<div>").addClass("item-date").html(`
            <svg fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"></path>
            </svg>
            <span>${escapeHtml(item.date)}</span>
        `);

    $meta.append($source).append($date);

    $item.append($header).append($claim).append($meta);

    return $item;
  }

  function filterItems($widget, filter) {
    const $items = $widget.find(".misinfo-item");

    if (filter === "all") {
      $items.removeClass("hidden");
    } else {
      $items.each(function () {
        const $item = $(this);
        const itemFilter = $item.data("filter-type");

        if (itemFilter === filter) {
          $item.removeClass("hidden");
        } else {
          $item.addClass("hidden");
        }
      });
    }
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
      lowerRating.includes("misleading") ||
      lowerRating.includes("mostly false") ||
      lowerRating.includes("partly false")
    ) {
      return "Misleading";
    } else if (
      lowerRating.includes("mixture") ||
      lowerRating.includes("mixed") ||
      lowerRating.includes("half")
    ) {
      return "Mixture";
    }

    return rating;
  }

  function getRatingClass(rating) {
    const lowerRating = rating.toLowerCase();

    if (lowerRating === "false" || lowerRating.includes("incorrect")) {
      return "rating-false";
    } else {
      return "rating-misleading";
    }
  }

  function getFilterType(rating) {
    const lowerRating = rating.toLowerCase();

    if (lowerRating === "false" || lowerRating.includes("incorrect")) {
      return "false";
    } else {
      return "misleading";
    }
  }

  function showEmpty($content) {
    $content.html(`
            <div class="misinfo-empty">
                <svg width="48" height="48" fill="currentColor" viewBox="0 0 24 24" style="opacity: 0.3; margin-bottom: 12px;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path>
                </svg>
                <p>No misinformation reports found at this time.</p>
            </div>
        `);
  }

  function showError($content) {
    $content.html(`
            <div class="misinfo-empty">
                <svg width="48" height="48" fill="currentColor" viewBox="0 0 24 24" style="opacity: 0.3; margin-bottom: 12px; color: #ff4444;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path>
                </svg>
                <p>Unable to load misinformation data.<br>Please check your API settings.</p>
            </div>
        `);
  }

  function escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }
})(jQuery);
