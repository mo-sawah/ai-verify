/**
 * Fact-Check System JavaScript (FINAL FIX)
 *
 * FIXES:
 * - Uses event delegation for form submission to prevent page reloads.
 * - Increases AJAX timeout to prevent connection errors on long analyses.
 * - Consolidates script into a single block for reliability.
 */

let currentReportId = null;

(function ($) {
  "use strict";

  // --- UTILITIES ---

  const FactcheckCookies = {
    set: function (name, value, days) {
      const d = new Date();
      d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
      const expires = "expires=" + d.toUTCString();
      document.cookie =
        name + "=" + value + ";" + expires + ";path=/;SameSite=Lax";
      console.log("AI Verify: Set cookie:", name, "=", value);
    },
    get: function (name) {
      const nameEQ = name + "=";
      const ca = document.cookie.split(";");
      for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === " ") c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) {
          return c.substring(nameEQ.length, c.length);
        }
      }
      return null;
    },
  };

  const UsageTracker = {
    cookieName: "ai_verify_usage",
    reportCookiePrefix: "ai_verify_report_",
    maxFreeUses: 5,
    getUsage: function () {
      const data = FactcheckCookies.get(this.cookieName);
      if (!data) return { count: 0, expires: null };
      try {
        return JSON.parse(decodeURIComponent(data));
      } catch (e) {
        return { count: 0, expires: null };
      }
    },
    init: function () {
      const usage = this.getUsage();
      if (!usage.expires || new Date() > new Date(usage.expires)) {
        const expires = new Date();
        expires.setDate(expires.getDate() + 30);
        const newUsage = { count: 0, expires: expires.toISOString() };
        FactcheckCookies.set(
          this.cookieName,
          encodeURIComponent(JSON.stringify(newUsage)),
          30
        );
        return newUsage;
      }
      return usage;
    },
    hasCompletedReport: (reportId) =>
      FactcheckCookies.get(UsageTracker.reportCookiePrefix + reportId) !== null,
    markReportCompleted: (reportId) =>
      FactcheckCookies.set(
        UsageTracker.reportCookiePrefix + reportId,
        "completed",
        30
      ),
    hasUsesRemaining: () =>
      UsageTracker.getUsage().count < UsageTracker.maxFreeUses,
    getRemainingUses: () =>
      Math.max(0, UsageTracker.maxFreeUses - UsageTracker.getUsage().count),
    incrementUsage: function () {
      const usage = this.getUsage();
      usage.count = (usage.count || 0) + 1;
      if (!usage.expires) {
        const expires = new Date();
        expires.setDate(expires.getDate() + 30);
        usage.expires = expires.toISOString();
      }
      FactcheckCookies.set(
        this.cookieName,
        encodeURIComponent(JSON.stringify(usage)),
        30
      );
      return usage.count;
    },
    updateCounter: function () {
      const remaining = this.getRemainingUses();
      const $counter = $("#usageCounter");
      if ($counter.length) {
        $counter.html(
          `${remaining} fact-check${
            remaining !== 1 ? "s" : ""
          } remaining this month`
        );
        if (remaining <= 0) {
          $counter.html(
            'No free uses remaining. <a href="#" class="upgrade-link">Upgrade to Pro</a>'
          );
        }
      }
    },
  };

  const SubscriptionManager = {
    init: function () {
      // Use event delegation for dynamically shown elements
      $(document).on("click", ".plan-card, .plan-select-btn", function (e) {
        e.stopPropagation();
        const plan = $(this).closest(".plan-card").data("plan");
        SubscriptionManager.selectPlan(plan);
      });
      // ** THE FIX IS HERE: Use event delegation for the form submission **
      $(document).on("submit", "#simpleAccessForm", function (e) {
        // <-- CHANGE IS HERE
        e.preventDefault(); // This is the crucial part that stops the page reload
        SubscriptionManager.submitFreePlan();
      });
      $(document).on("submit", "#proPlanForm", (e) => {
        e.preventDefault();
        SubscriptionManager.submitProPlan();
      });
    },
    selectPlan: function (plan) {
      $(".plan-card").removeClass("active");
      $(`.plan-card[data-plan="${plan}"]`).addClass("active");
      $(".plan-form").removeClass("active").hide();
      $(`#${plan}PlanForm`).addClass("active").fadeIn(300);
    },
    submitFreePlan: function () {
      if (!UsageTracker.hasUsesRemaining()) {
        alert("You have reached your free limit. Please upgrade to Pro.");
        this.selectPlan("pro");
        return;
      }
      const email = $("#userEmail").val().trim();
      const name = $("#userName").val().trim();
      const terms = $("#termsAccept").is(":checked");
      if (!email || !name || !terms) {
        alert("Please fill all fields and accept the terms");
        return;
      }
      const $btn = $("#freePlanSubmit");
      $btn.prop("disabled", true).addClass("loading");
      $btn.find(".btn-text").hide();
      $btn.find(".btn-loading").show();

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
          plan: "free",
        },
        success: function (response) {
          if (response.success) {
            UsageTracker.markReportCompleted(currentReportId);
            UsageTracker.incrementUsage();
            $("#factcheckEmailGate").fadeOut(300, () =>
              $("#factcheckReport").removeClass("report-blurred")
            );
          } else {
            alert(response.data.message || "Failed to submit");
            $btn.prop("disabled", false).removeClass("loading");
            $btn.find(".btn-text").show();
            $btn.find(".btn-loading").hide();
          }
        },
        error: function () {
          alert("Connection error. Please try again.");
          $btn.prop("disabled", false).removeClass("loading");
          $btn.find(".btn-text").show();
          $btn.find(".btn-loading").hide();
        },
      });
    },
    submitProPlan: function () {
      alert("Stripe integration will be implemented here.");
      // Logic for pro plan...
    },
  };

  function initSearchInterface(selector, isHeader) {
    const context = $(selector);
    if (context.length === 0) return;

    let currentInputType = "auto";
    const inputId = isHeader ? "#factcheckInputHeader" : "#factcheck-input";
    const submitId = isHeader ? "#factcheckSubmitHeader" : "#factcheck-submit";
    const filterClass = isHeader ? ".filter-btn-mini" : ".filter-btn";
    const exampleClass = isHeader ? ".example-btn-header" : ".example-btn";

    context.on("click", filterClass, function () {
      context.find(filterClass).removeClass("active");
      $(this).addClass("active");
      currentInputType = $(this).data("type");
      if (!isHeader) updatePlaceholder(currentInputType);
    });

    context.on("click", exampleClass, function () {
      context.find(inputId).val($(this).data("example")).focus();
    });

    context.on("click", submitId, function (e) {
      e.preventDefault();
      startFactCheck(context, currentInputType, isHeader);
    });

    context.on("keypress", inputId, function (e) {
      if (e.which === 13) {
        e.preventDefault();
        startFactCheck(context, currentInputType, isHeader);
      }
    });
  }

  function updatePlaceholder(type) {
    const placeholders = {
      auto: "Paste URL or enter text to fact-check...",
      url: "https://example.com/article",
      title: "Enter article title...",
      phrase: "Enter claim to fact-check...",
    };
    $("#factcheck-input").attr(
      "placeholder",
      placeholders[type] || placeholders.auto
    );
  }

  function startFactCheck(context, currentInputType, isHeader) {
    const inputId = isHeader ? "#factcheckInputHeader" : "#factcheck-input";
    const submitId = isHeader ? "#factcheckSubmitHeader" : "#factcheck-submit";
    const input = context.find(inputId).val().trim();

    if (!input) {
      alert("Please enter a URL, title, or claim to fact-check");
      return;
    }

    let inputType = currentInputType;
    if (inputType === "auto") {
      inputType = detectInputType(input);
    }

    const $btn = context.find(submitId);
    $btn.prop("disabled", true).addClass("loading");
    $btn.find(".btn-text").hide();
    $btn.find(".btn-loading").show();

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
          window.location.href =
            aiVerifyFactcheck.results_url +
            "?report=" +
            response.data.report_id;
        } else {
          alert(response.data.message || "Failed to start fact-check");
          $btn
            .prop("disabled", false)
            .removeClass("loading")
            .find(".btn-text")
            .show()
            .end()
            .find(".btn-loading")
            .hide();
        }
      },
      error: function () {
        alert("Connection error. Please try again.");
        $btn
          .prop("disabled", false)
          .removeClass("loading")
          .find(".btn-text")
          .show()
          .end()
          .find(".btn-loading")
          .hide();
      },
    });
  }

  function detectInputType(input) {
    if (input.match(/^https?:\/\//)) return "url";
    if (input.length > 100) return "phrase";
    return "title";
  }

  function initResultsPage() {
    if ($("#factcheckResults").length === 0) return;

    const urlParams = new URLSearchParams(window.location.search);
    currentReportId = urlParams.get("report");

    if (!currentReportId) {
      $("#factcheckLoading").html("<p>No report ID provided</p>");
      return;
    }

    UsageTracker.init();
    UsageTracker.updateCounter();

    if (UsageTracker.hasCompletedReport(currentReportId)) {
      startProcessing(false); // false = don't show paywall
    } else {
      startProcessing(true); // true = show paywall
    }

    $(".export-btn").on("click", function () {
      exportReport($(this).data("format"));
    });
    $(".share-btn").on("click", shareReport);
  }

  function startProcessing(showPaywall) {
    updateLoadingStep("Starting analysis...", 0);

    // Start the processing
    $.ajax({
      url: aiVerifyFactcheck.ajax_url,
      type: "POST",
      timeout: 30000, // Only 30 seconds - we just start the process
      data: {
        action: "ai_verify_process_factcheck",
        nonce: aiVerifyFactcheck.nonce,
        report_id: currentReportId,
      },
      success: function (response) {
        if (response.success && response.data.status === "processing") {
          // Process started successfully - now poll for status
          pollProcessingStatus(showPaywall);
        } else {
          updateLoadingStep(
            "Error: " + (response.data.message || "Failed to start processing"),
            0
          );
        }
      },
      error: function () {
        updateLoadingStep("Connection error - failed to start", 0);
      },
    });
  }

  function pollProcessingStatus(showPaywall) {
    const pollInterval = setInterval(function () {
      $.ajax({
        url: aiVerifyFactcheck.ajax_url,
        type: "POST",
        timeout: 10000, // Short timeout for status checks
        data: {
          action: "ai_verify_check_status",
          nonce: aiVerifyFactcheck.nonce,
          report_id: currentReportId,
        },
        success: function (response) {
          if (response.success) {
            const status = response.data.status;
            const progress = response.data.progress || 0;
            const message = response.data.progress_message || "Processing...";

            updateLoadingStep(message, progress);

            if (status === "completed") {
              clearInterval(pollInterval);
              updateLoadingStep("Processing complete!", 100);
              setTimeout(() => loadReport(showPaywall), 500);
            } else if (status === "failed") {
              clearInterval(pollInterval);
              updateLoadingStep("Processing failed", 0);
            }
            // If status is 'processing', keep polling
          }
        },
        error: function () {
          // Don't stop polling on single error - might be temporary
          console.log("Status check failed, retrying...");
        },
      });
    }, 3000); // Poll every 3 seconds

    // Failsafe: stop polling after 5 minutes
    setTimeout(function () {
      clearInterval(pollInterval);
      updateLoadingStep("Process took too long - please check back later", 0);
    }, 300000); // 5 minutes
  }

  function loadReport(showPaywall) {
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
          if (showPaywall) {
            $("#factcheckReport").addClass("report-blurred");
            $("#factcheckEmailGate").fadeIn(300);
          }
        } else {
          alert("Failed to load report");
        }
      },
      error: () => alert("Failed to load report"),
    });
  }

  function updateLoadingStep(text, progress) {
    $("#loadingStep").text(text);
    $("#progressBar").css("width", progress + "%");
  }

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
    if (claims.length === 0) {
      $container.html('<p class="no-data">No claims analyzed</p>');
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
              <span class="claim-confidence">${confidencePercent}%</span>
            </div>
          </div>
          <div class="claim-text">${escapeHtml(claim.claim)}</div>
          <div class="claim-explanation">${escapeHtml(
            claim.explanation || "No explanation available"
          )}</div>
          ${
            claim.evidence_for?.length > 0
              ? `
            <div class="claim-evidence">
              <div class="evidence-title">✓ Evidence Supporting:</div>
              <ul class="evidence-list">${claim.evidence_for
                .map((e) => "<li>" + escapeHtml(e) + "</li>")
                .join("")}</ul>
            </div>`
              : ""
          }
          ${
            claim.evidence_against?.length > 0
              ? `
            <div class="claim-evidence">
              <div class="evidence-title">✗ Evidence Contradicting:</div>
              <ul class="evidence-list">${claim.evidence_against
                .map((e) => "<li>" + escapeHtml(e) + "</li>")
                .join("")}</ul>
            </div>`
              : ""
          }
          ${
            claim.red_flags?.length > 0
              ? `
            <div class="red-flags-section">
              <div class="red-flags-title">... Red Flags Detected</div>
              <ul class="red-flags-list">${claim.red_flags
                .map((flag) => "<li>" + escapeHtml(flag) + "</li>")
                .join("")}</ul>
            </div>`
              : ""
          }
          <div class="claim-meta">
            <span class="claim-type">${escapeHtml(
              claim.type || "general"
            )}</span>
            ${
              claim.method
                ? `<span class="claim-method">🔡 ${escapeHtml(
                    claim.method
                  )}</span>`
                : ""
            }
          </div>
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
        $(".claim-card").removeClass("hidden");
      } else {
        $(".claim-card").each(function () {
          $(this).toggleClass("hidden", $(this).data("filter-type") !== filter);
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
    if (sources.length === 0) {
      $container.html('<p class="no-data">No sources available</p>');
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
                  )}" target="_blank" class="source-url">${escapeHtml(
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
      navigator.share({ title: "Fact-Check Report", url });
    } else {
      navigator.clipboard
        .writeText(url)
        .then(() => alert("Link copied to clipboard!"));
    }
  }

  function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + " " + date.toLocaleTimeString();
  }

  function escapeHtml(text) {
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

  // Initialize all components on document ready
  $(document).ready(function () {
    initSearchInterface(".factcheck-search-wrapper", false);
    initSearchInterface(".factcheck-header-search", true);
    initResultsPage();
    SubscriptionManager.init();

    // FIX: Wrap in function to preserve 'this' context
    setInterval(function () {
      UsageTracker.updateCounter();
    }, 5000);
  });
})(jQuery);
