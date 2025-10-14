/**
 * Public Widget JavaScript for Trending Misinformation
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    initTrendsWidget();
  });

  function initTrendsWidget() {
    // Handle fact-check button clicks
    $(".fact-check-btn").on("click", function (e) {
      e.preventDefault();

      const claim = $(this).data("claim");

      if (claim && aiVerifyTrendsWidget.factcheck_url) {
        // Redirect to fact-check page with prefilled claim
        const url = new URL(aiVerifyTrendsWidget.factcheck_url);
        url.searchParams.append("prefill_claim", claim);
        window.location.href = url.toString();
      }
    });

    // Optionally: Add click tracking
    $(".trend-item").on("click", function () {
      const trendId = $(this).data("trend-id");
      trackTrendClick(trendId);
    });
  }

  function trackTrendClick(trendId) {
    // Optional: Track which trends users are interested in
    if (!trendId) return;

    $.ajax({
      url: aiVerifyTrendsWidget.ajax_url,
      type: "POST",
      data: {
        action: "ai_verify_track_trend_click",
        nonce: aiVerifyTrendsWidget.nonce,
        trend_id: trendId,
      },
    });
  }
})(jQuery);
