/**
 * AI Verify - Fact-Check System JavaScript (REVISED)
 *
 * Implements a reliable, cookie-based "blur and unlock" email gate.
 * - Removes the complex 5-use limit in favor of a simple 30-day access cookie.
 * - Report is blurred until the user submits their email.
 * - Unlocking happens instantly via AJAX without a page reload.
 * - Uses event delegation for robust form handling.
 */

// Global variable to hold the current report's ID
let currentReportId = null;

(function ($) {
  "use strict";

  // --- 1. SIMPLIFIED COOKIE & ACCESS MANAGEMENT ---
  const AccessManager = {
    cookieName: "ai_verify_access_granted",

    /**
     * Sets a cookie to grant access for 30 days.
     */
    setAccessCookie: function () {
      const d = new Date();
      d.setTime(d.getTime() + 30 * 24 * 60 * 60 * 1000); // 30 days
      const expires = "expires=" + d.toUTCString();
      document.cookie = `${this.cookieName}=true;${expires};path=/;SameSite=Lax`;
      console.log("AI Verify: Access cookie set for 30 days.");
    },

    /**
     * Checks if the user has a valid access cookie.
     * @returns {boolean} - True if the cookie exists, false otherwise.
     */
    hasAccessCookie: function () {
      const nameEQ = this.cookieName + "=";
      const ca = document.cookie.split(";");
      for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === " ") c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) {
          console.log("AI Verify: Access cookie found.");
          return true;
        }
      }
      console.log("AI Verify: No access cookie found.");
      return false;
    },
  };

  // --- 2. EMAIL GATE AND FORM SUBMISSION LOGIC ---
  const EmailGate = {
    /**
     * Initializes the email gate functionality.
     */
    init: function () {
      // Use event delegation to handle form submission.
      // This is crucial for preventing page reloads.
      $(document).on("submit", "#simpleAccessForm", function (e) {
        e.preventDefault(); // Stop the default form submission (page reload)
        EmailGate.handleSubmission();
      });
    },

    /**
     * Handles the AJAX submission of the email form.
     */
    handleSubmission: function () {
      const email = $("#userEmail").val().trim();
      const name = $("#userName").val().trim();
      const terms = $("#termsAccept").is(":checked");

      if (!email || !name || !terms) {
        alert("Please fill in all fields and accept the terms to continue.");
        return;
      }

      const $btn = $("#simpleAccessSubmit");
      $btn.prop("disabled", true).addClass("loading");
      $btn.find(".btn-text").hide();
      $btn.find(".btn-loading").css("display", "inline-block");

      $.ajax({
        url: aiVerifyFactcheck.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_submit_email", // This action is handled in factcheck-ajax.php
          nonce: aiVerifyFactcheck.nonce,
          report_id: currentReportId,
          email: email,
          name: name,
        },
        success: function (response) {
          if (response.success) {
            // On success, set the access cookie and unlock the content
            AccessManager.setAccessCookie();
            EmailGate.unlockContent();
          } else {
            alert(
              response.data.message || "An error occurred. Please try again."
            );
            $btn.prop("disabled", false).removeClass("loading");
            $btn.find(".btn-text").show();
            $btn.find(".btn-loading").hide();
          }
        },
        error: function () {
          alert(
            "There was a connection error. Please check your internet and try again."
          );
          $btn.prop("disabled", false).removeClass("loading");
          $btn.find(".btn-text").show();
          $btn.find(".btn-loading").hide();
        },
      });
    },

    /**
     * Shows the email gate and blurs the report.
     */
    show: function () {
      $("#factcheckReport").addClass("report-blurred");
      $("#factcheckEmailGate").fadeIn(300);
    },

    /**
     * Hides the email gate and unblurs the report.
     */
    unlockContent: function () {
      $("#factcheckEmailGate").fadeOut(300);
      $("#factcheckReport").removeClass("report-blurred");
    },
  };

  // --- 3. REPORT PAGE INITIALIZATION AND DISPLAY ---

  /**
   * Initializes the entire results page logic.
   */
  function initResultsPage() {
    if ($("#factcheckResults").length === 0) return;

    const urlParams = new URLSearchParams(window.location.search);
    currentReportId = urlParams.get("report");

    if (!currentReportId) {
      $("#factcheckLoading").html("<p>Error: No report ID was provided.</p>");
      return;
    }

    // Start the process
    startProcessing();

    // Setup event listeners for report actions
    $(".export-btn").on("click", function () {
      exportReport($(this).data("format"));
    });
    $(".share-btn").on("click", shareReport);
  }

  /**
   * Begins the fact-check process on the backend and polls for completion.
   */
  function startProcessing() {
    updateLoadingStep("Starting analysis...", 0);

    // This AJAX call kicks off the background processing in PHP
    $.ajax({
      url: aiVerifyFactcheck.ajax_url,
      type: "POST",
      timeout: 30000, // Timeout for starting the process
      data: {
        action: "ai_verify_process_factcheck",
        nonce: aiVerifyFactcheck.nonce,
        report_id: currentReportId,
      },
      success: function (response) {
        if (response.success) {
          updateLoadingStep("Processing claims...", 10);
          pollForCompletion(); // Start polling for the result
        } else {
          updateLoadingStep(
            "Error: " + (response.data.message || "Could not start analysis."),
            0
          );
        }
      },
      error: function () {
        updateLoadingStep(
          "Failed to start the analysis process due to a connection error.",
          0
        );
      },
    });
  }

  /**
   * Polls the server to check if the report generation is complete.
   */
  function pollForCompletion() {
    let attempts = 0;
    const maxAttempts = 120; // 6 minutes max (120 attempts * 3s)

    const pollInterval = setInterval(function () {
      attempts++;

      // Simulate progress while waiting
      const fakeProgress = Math.min(10 + attempts * 0.7, 95);
      updateLoadingStep("Analyzing content and sources...", fakeProgress);

      $.ajax({
        url: aiVerifyFactcheck.ajax_url,
        type: "POST",
        timeout: 10000,
        data: {
          action: "ai_verify_check_status",
          nonce: aiVerifyFactcheck.nonce,
          report_id: currentReportId,
        },
        success: function (response) {
          if (response.success) {
            if (response.data.status === "completed") {
              clearInterval(pollInterval);
              updateLoadingStep("Analysis complete!", 100);
              setTimeout(loadReport, 500); // Load the final report
            } else if (response.data.status === "failed") {
              clearInterval(pollInterval);
              updateLoadingStep("The analysis failed to complete.", 0);
            }
            // If still 'processing', the interval will continue
          }
        },
        error: function () {
          // Don't stop polling on a single failed request
          console.log("Polling attempt " + attempts + " failed, retrying...");
        },
      });

      if (attempts >= maxAttempts) {
        clearInterval(pollInterval);
        updateLoadingStep("The analysis timed out. Please try again later.", 0);
      }
    }, 3000); // Poll every 3 seconds
  }

  /**
   * Loads the final report data from the server.
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

          // ** KEY LOGIC: Check for cookie and show/hide the email gate **
          if (AccessManager.hasAccessCookie()) {
            EmailGate.unlockContent();
          } else {
            EmailGate.show();
          }
        } else {
          alert("Failed to load the final report data.");
        }
      },
      error: () =>
        alert("A connection error occurred while loading the report."),
    });
  }

  /**
   * Renders the report data into the HTML.
   * (This function and its helpers remain largely the same)
   */
  function displayReport(report) {
    $("#factcheckLoading").fadeOut(300, () =>
      $("#factcheckReport").fadeIn(300)
    );
    $("#reportId").text(report.report_id);
    $("#reportDate").text(formatDate(report.created_at));
    $("#inputValue").text(report.input_value);
    animateScore(parseFloat(report.overall_score) || 0);
    $("#credibilityRating").text(report.credibility_rating || "Unknown");
    const claims = report.factcheck_results || [];
    $("#claimsCount").text(claims.length);
    displayClaims(claims);
    const sources = report.sources || [];
    $("#sourcesCount").text(sources.length);
    displaySources(sources);
    if (claims.length > 0)
      $("#analysisMethod").text(claims[0].method || "Multiple Sources");
    if (report.created_at && report.completed_at) {
      const diff = Math.round(
        (new Date(report.completed_at) - new Date(report.created_at)) / 1000
      );
      $("#analysisTime").text(diff + "s");
    }
    const propaganda = report.metadata?.propaganda_techniques || [];
    if (propaganda.length > 0) displayPropaganda(propaganda);
    setupClaimsFilter();
  }

  // --- HELPER & UTILITY FUNCTIONS (Mostly Unchanged) ---

  function updateLoadingStep(text, progress) {
    $("#loadingStep").text(text);
    $("#progressBar").css("width", progress + "%");
  }

  function displayPropaganda(techniques) {
    const $list = $("#propagandaList").empty();
    techniques.forEach((technique) =>
      $list.append("<li>" + escapeHtml(technique) + "</li>")
    );
    $("#propagandaWarning").fadeIn(300);
  }

  function animateScore(targetScore) {
    const circumference = 2 * Math.PI * 90;
    const offset = circumference - (targetScore / 100) * circumference;
    $("#scoreCircle").css({
      "stroke-dasharray": circumference,
      "stroke-dashoffset": offset,
    });
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

  function displayClaims(claims) {
    const $container = $("#claimsAnalysis").empty();
    if (!claims || claims.length === 0) {
      $container.html(
        '<p class="no-data">No verifiable claims were found in the content.</p>'
      );
      return;
    }
    claims.forEach((claim, index) => {
      const ratingClass = getRatingClass(claim.rating);
      const confidencePercent = Math.round((claim.confidence || 0.5) * 100);
      const filterType = getFilterType(claim.rating);
      const claimCardHTML = `
        <div class="claim-card" data-filter-type="${filterType}">
          <div class="claim-header">
            <span class="claim-number">#${index + 1}</span>
            <div style="display: flex; gap: 8px; align-items: center;">
              <span class="claim-rating ${ratingClass}">${escapeHtml(
        claim.rating || "Unknown"
      )}</span>
              <span class="claim-confidence">${confidencePercent}% confidence</span>
            </div>
          </div>
          <div class="claim-text">${escapeHtml(claim.claim)}</div>
          <div class="claim-explanation">${escapeHtml(
            claim.explanation || "No explanation available."
          )}</div>
        </div>`;
      $container.append(claimCardHTML);
    });
  }

  function setupClaimsFilter() {
    $(".filter-chip").on("click", function () {
      const filter = $(this).data("filter");
      $(".filter-chip").removeClass("active");
      $(this).addClass("active");
      if (filter === "all") {
        $(".claim-card").show();
      } else {
        $(".claim-card").each(function () {
          $(this).toggle($(this).data("filter-type") === filter);
        });
      }
    });
  }

  function getFilterType(rating) {
    const r = (rating || "").toLowerCase();
    if (r.includes("true") && !r.includes("false")) return "true";
    if (r.includes("false")) return "false";
    if (r.includes("misleading") || r.includes("mixture")) return "misleading";
    return "unverified";
  }

  function displaySources(sources) {
    const $container = $("#sourcesList").empty();
    if (!sources || sources.length === 0) {
      $container.html(
        '<p class="no-data">No sources were consulted for this analysis.</p>'
      );
      return;
    }
    const uniqueSources = Array.from(
      new Map(sources.map((s) => [s.url || s.name, s])).values()
    );
    uniqueSources.forEach((source) => {
      const sourceCardHTML = `
        <div class="source-card">
          <div class="source-icon">...</div>
          <div class="source-content">
            <div class="source-name">${escapeHtml(
              source.name || "Unknown Source"
            )}</div>
            ${
              source.url
                ? `<a href="${escapeHtml(
                    source.url
                  )}" target="_blank" rel="noopener noreferrer" class="source-url">${escapeHtml(
                    source.url
                  )}</a>`
                : ""
            }
          </div>
        </div>`;
      $container.append(sourceCardHTML);
    });
  }

  function getRatingClass(rating) {
    const r = (rating || "").toLowerCase();
    if (r.includes("true") && !r.includes("false")) return "rating-true";
    if (r.includes("false")) return "rating-false";
    if (r.includes("mixture") || r.includes("mixed")) return "rating-mixture";
    return "rating-unknown";
  }

  function exportReport(format) {
    window.open(
      `${aiVerifyFactcheck.ajax_url}?action=ai_verify_export_report&nonce=${aiVerifyFactcheck.nonce}&report_id=${currentReportId}&format=${format}`,
      "_blank"
    );
  }

  function shareReport() {
    const url = window.location.href;
    if (navigator.share) {
      navigator.share({ title: "Fact-Check Report", url: url });
    } else {
      navigator.clipboard
        .writeText(url)
        .then(() => alert("Report link copied to clipboard!"));
    }
  }

  function formatDate(dateString) {
    if (!dateString) return "-";
    const date = new Date(dateString);
    return date.toLocaleDateString() + " " + date.toLocaleTimeString();
  }

  function escapeHtml(text) {
    if (text === null || typeof text === "undefined") return "";
    return String(text).replace(
      /[&<>"']/g,
      (m) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        }[m])
    );
  }

  // --- 4. INITIALIZE EVERYTHING ON DOCUMENT READY ---
  $(document).ready(function () {
    initResultsPage();
    EmailGate.init();

    // The search interface logic is separate and can be kept as is,
    // assuming it redirects to the results page correctly.
  });
})(jQuery);
