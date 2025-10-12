/**
 * Fact-Check System JavaScript
 */

(function ($) {
  "use strict";

  let currentInputType = "auto";
  let currentReportId = null;

  $(document).ready(function () {
    initSearchInterface();
    initResultsPage();
  });

  /**
   * Initialize search interface
   */
  function initSearchInterface() {
    // Filter buttons
    $(".filter-btn").on("click", function () {
      $(".filter-btn").removeClass("active");
      $(this).addClass("active");
      currentInputType = $(this).data("type");
      updatePlaceholder(currentInputType);
    });

    // Example buttons
    $(".example-btn").on("click", function () {
      const example = $(this).data("example");
      $("#factcheck-input").val(example).focus();
    });

    // Submit button
    $("#factcheck-submit").on("click", function (e) {
      e.preventDefault();
      startFactCheck();
    });

    // Enter key
    $("#factcheck-input").on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        startFactCheck();
      }
    });
  }

  /**
   * Update placeholder based on input type
   */
  function updatePlaceholder(type) {
    const placeholders = {
      auto: "Paste URL or enter text to fact-check...",
      url: "https://example.com/article",
      title: "Enter article title...",
      phrase: "Enter claim to fact-check...",
    };

    $("#factcheck-input").attr(
      "placeholder",
      placeholders[type] || placeholders["auto"]
    );
  }

  /**
   * Start fact-check process
   */
  function startFactCheck() {
    const input = $("#factcheck-input").val().trim();

    if (!input) {
      showError("Please enter a URL, title, or claim to fact-check");
      return;
    }

    // Detect input type if auto
    let inputType = currentInputType;
    if (inputType === "auto") {
      inputType = detectInputType(input);
    }

    // Show loading state
    const $btn = $("#factcheck-submit");
    $btn.prop("disabled", true);
    $btn.addClass("loading");
    $(".btn-text").hide();
    $(".btn-loading").show();

    // Create report
    $.ajax({
      url: aiVerifyFactcheck.ajax_url,
      type: "POST",
      data: {
        action: "ai_verify_start_factcheck",
        nonce: aiVerifyFactcheck.nonce,
        input_type: inputType,
        input_value: input,
      },
      success: function (response) {
        if (response.success) {
          currentReportId = response.data.report_id;
          // Redirect to results page with report ID
          const resultsUrl =
            aiVerifyFactcheck.results_url + "?report=" + currentReportId;
          window.location.href = resultsUrl;
        } else {
          showError(response.data.message || "Failed to start fact-check");
          resetButton();
        }
      },
      error: function () {
        showError("Connection error. Please try again.");
        resetButton();
      },
    });
  }

  /**
   * Detect input type automatically
   */
  function detectInputType(input) {
    if (input.match(/^https?:\/\//)) {
      return "url";
    } else if (input.length > 100) {
      return "phrase";
    } else {
      return "title";
    }
  }

  /**
   * Reset submit button
   */
  function resetButton() {
    const $btn = $("#factcheck-submit");
    $btn.prop("disabled", false);
    $btn.removeClass("loading");
    $(".btn-text").show();
    $(".btn-loading").hide();
  }

  /**
   * Show error message
   */
  function showError(message) {
    alert(message); // Replace with better UI
  }

  /**
   * Initialize results page
   */
  function initResultsPage() {
    if ($("#factcheckResults").length === 0) {
      return;
    }

    // Get report ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const reportId = urlParams.get("report");

    if (!reportId) {
      $("#factcheckLoading").html("<p>No report ID provided</p>");
      return;
    }

    currentReportId = reportId;

    // Start processing flow
    setTimeout(function () {
      showEmailGate();
    }, 2000);

    // Email form submission
    $("#emailGateForm").on("submit", function (e) {
      e.preventDefault();
      submitEmail();
    });

    // Export buttons
    $(".export-btn").on("click", function () {
      exportReport($(this).data("format"));
    });

    // Share button
    $(".share-btn").on("click", function () {
      shareReport();
    });
  }

  /**
   * Show email gate modal
   */
  function showEmailGate() {
    $("#factcheckLoading").fadeOut(300, function () {
      $("#factcheckEmailGate").fadeIn(300);
    });
  }

  /**
   * Submit email and process
   */
  function submitEmail() {
    const email = $("#userEmail").val().trim();
    const name = $("#userName").val().trim();
    const terms = $("#termsAccept").is(":checked");

    if (!email || !name || !terms) {
      alert("Please fill all fields and accept the terms");
      return;
    }

    // Disable form
    $("#emailGateForm button").prop("disabled", true);

    $.ajax({
      url: aiVerifyFactcheck.ajax_url,
      type: "POST",
      data: {
        action: "ai_verify_submit_email",
        nonce: aiVerifyFactcheck.nonce,
        report_id: currentReportId,
        email: email,
        name: name,
        terms_accepted: terms,
      },
      success: function (response) {
        if (response.success) {
          $("#factcheckEmailGate").fadeOut(300, function () {
            $("#factcheckLoading").fadeIn(300);
            startProcessing();
          });
        } else {
          alert(response.data.message || "Failed to submit");
          $("#emailGateForm button").prop("disabled", false);
        }
      },
      error: function () {
        alert("Connection error");
        $("#emailGateForm button").prop("disabled", false);
      },
    });
  }

  /**
   * Start processing fact-check
   */
  function startProcessing() {
    updateLoadingStep("Extracting content...", 25);

    $.ajax({
      url: aiVerifyFactcheck.ajax_url,
      type: "POST",
      data: {
        action: "ai_verify_process_factcheck",
        nonce: aiVerifyFactcheck.nonce,
        report_id: currentReportId,
      },
      success: function (response) {
        if (response.success) {
          updateLoadingStep("Processing complete!", 100);
          setTimeout(function () {
            loadReport();
          }, 1000);
        } else {
          updateLoadingStep(
            "Error: " + (response.data.message || "Processing failed"),
            0
          );
        }
      },
      error: function () {
        updateLoadingStep("Connection error", 0);
      },
    });
  }

  /**
   * Update loading step
   */
  function updateLoadingStep(text, progress) {
    $("#loadingStep").text(text);
    $("#progressBar").css("width", progress + "%");
  }

  /**
   * Load and display report
   */
  function loadReport() {
    $.ajax({
      url: aiVerifyFactcheck.ajax_url,
      type: "POST",
      data: {
        action: "ai_verify_get_report",
        nonce: aiVerifyFactcheck.nonce,
        report_id: currentReportId,
      },
      success: function (response) {
        if (response.success && response.data.report) {
          displayReport(response.data.report);
        } else {
          alert("Failed to load report");
        }
      },
      error: function () {
        alert("Failed to load report");
      },
    });
  }

  /**
   * Display report data
   */
  function displayReport(report) {
    // Hide loading, show report
    $("#factcheckLoading").fadeOut(300, function () {
      $("#factcheckReport").fadeIn(300);
    });

    // Basic info
    $("#reportId").text(report.report_id);
    $("#reportDate").text(formatDate(report.created_at));
    $("#inputValue").text(report.input_value);

    // Score
    const score = parseFloat(report.overall_score) || 0;
    animateScore(score);
    $("#credibilityRating").text(report.credibility_rating || "Unknown");

    // Claims
    const claims = report.factcheck_results || [];
    $("#claimsCount").text(claims.length);
    displayClaims(claims);

    // Sources
    const sources = report.sources || [];
    $("#sourcesCount").text(sources.length);
    displaySources(sources);

    // Analysis time
    if (report.created_at && report.completed_at) {
      const start = new Date(report.created_at);
      const end = new Date(report.completed_at);
      const diff = Math.round((end - start) / 1000);
      $("#analysisTime").text(diff + "s");
    }
  }

  /**
   * Animate score circle
   */
  function animateScore(targetScore) {
    const circumference = 2 * Math.PI * 90;
    const offset = circumference - (targetScore / 100) * circumference;

    $("#scoreCircle").css({
      "stroke-dasharray": circumference,
      "stroke-dashoffset": offset,
    });

    // Animate number
    $({ value: 0 }).animate(
      { value: targetScore },
      {
        duration: 1500,
        easing: "swing",
        step: function () {
          $("#overallScore").text(Math.round(this.value));
        },
      }
    );
  }

  /**
   * Display claims
   */
  function displayClaims(claims) {
    const $container = $("#claimsAnalysis");
    $container.empty();

    if (claims.length === 0) {
      $container.html('<p class="no-data">No claims analyzed</p>');
      return;
    }

    claims.forEach(function (claim, index) {
      const ratingClass = getRatingClass(claim.rating);
      const confidencePercent = Math.round((claim.confidence || 0.5) * 100);

      const $claim = $('<div class="claim-card">').html(`
        <div class="claim-header">
          <span class="claim-number">#${index + 1}</span>
          <span class="claim-rating ${ratingClass}">${escapeHtml(
        claim.rating || "Unknown"
      )}</span>
        </div>
        <div class="claim-text">${escapeHtml(claim.claim)}</div>
        <div class="claim-explanation">${escapeHtml(
          claim.explanation || "No explanation available"
        )}</div>
        <div class="claim-meta">
          <span class="claim-type">${escapeHtml(claim.type || "general")}</span>
          <span class="claim-confidence">Confidence: ${confidencePercent}%</span>
        </div>
      `);

      $container.append($claim);
    });
  }

  /**
   * Display sources
   */
  function displaySources(sources) {
    const $container = $("#sourcesList");
    $container.empty();

    if (sources.length === 0) {
      $container.html('<p class="no-data">No sources available</p>');
      return;
    }

    // Remove duplicates
    const uniqueSources = [];
    const seen = new Set();

    sources.forEach(function (source) {
      const key = source.name + source.url;
      if (!seen.has(key)) {
        seen.add(key);
        uniqueSources.push(source);
      }
    });

    uniqueSources.forEach(function (source) {
      const $source = $('<div class="source-card">').html(`
        <div class="source-icon">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
          </svg>
        </div>
        <div class="source-content">
          <div class="source-name">${escapeHtml(
            source.name || "Unknown Source"
          )}</div>
          ${
            source.url
              ? `<a href="${escapeHtml(
                  source.url
                )}" target="_blank" class="source-url">${escapeHtml(
                  source.url
                )}</a>`
              : ""
          }
        </div>
      `);

      $container.append($source);
    });
  }

  /**
   * Get rating CSS class
   */
  function getRatingClass(rating) {
    const r = (rating || "").toLowerCase();

    if (r.includes("true") && !r.includes("false")) {
      return "rating-true";
    } else if (r.includes("false")) {
      return "rating-false";
    } else if (r.includes("mixture") || r.includes("mixed")) {
      return "rating-mixture";
    } else {
      return "rating-unknown";
    }
  }

  /**
   * Export report
   */
  function exportReport(format) {
    const url =
      aiVerifyFactcheck.ajax_url +
      "?action=ai_verify_export_report" +
      "&nonce=" +
      aiVerifyFactcheck.nonce +
      "&report_id=" +
      currentReportId +
      "&format=" +
      format;

    window.open(url, "_blank");
  }

  /**
   * Share report
   */
  function shareReport() {
    const url = window.location.href;

    if (navigator.share) {
      navigator.share({
        title: "Fact-Check Report",
        url: url,
      });
    } else {
      // Fallback: copy to clipboard
      const $temp = $("<input>");
      $("body").append($temp);
      $temp.val(url).select();
      document.execCommand("copy");
      $temp.remove();
      alert("Link copied to clipboard!");
    }
  }

  /**
   * Format date
   */
  function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + " " + date.toLocaleTimeString();
  }

  /**
   * Escape HTML
   */
  function escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return String(text).replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }
})(jQuery);
