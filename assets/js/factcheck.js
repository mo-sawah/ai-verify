/**
 * Fact-Check System JavaScript (FIXED)
 *
 * IMPROVEMENTS:
 * - Cookie only set AFTER successful AJAX
 * - Email gate shows AFTER report generation (not during loading)
 * - Paywall overlay (not popup modal)
 * - Better error handling
 * - Fixed usage tracking
 */

let currentReportId = null;

/**
 * FIXED: Cookie-Based Usage Tracking
 */
(function ($) {
  ("use strict");

  // Cookie Management (FIXED)
  const FactcheckCookies = {
    set: function (name, value, days) {
      const d = new Date();
      d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
      const expires = "expires=" + d.toUTCString();
      const cookie =
        name + "=" + value + ";" + expires + ";path=/;SameSite=Lax";
      document.cookie = cookie;
      console.log("AI Verify: Set cookie:", name, "=", value);
    },

    get: function (name) {
      const nameEQ = name + "=";
      const ca = document.cookie.split(";");
      for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == " ") c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) {
          const value = c.substring(nameEQ.length, c.length);
          console.log("AI Verify: Found cookie", name, "=", value);
          return value;
        }
      }
      return null;
    },

    delete: function (name) {
      document.cookie =
        name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
      console.log("AI Verify: Deleted cookie:", name);
    },
  };

  // Usage Tracking System (FIXED)
  const UsageTracker = {
    cookieName: "ai_verify_usage",
    reportCookiePrefix: "ai_verify_report_",
    maxFreeUses: 5,

    getUsage: function () {
      const data = FactcheckCookies.get(this.cookieName);
      if (!data) {
        return { count: 0, expires: null };
      }
      try {
        return JSON.parse(decodeURIComponent(data));
      } catch (e) {
        console.error("AI Verify: Failed to parse usage cookie", e);
        return { count: 0, expires: null };
      }
    },

    init: function () {
      const usage = this.getUsage();
      if (!usage.expires || new Date() > new Date(usage.expires)) {
        const expires = new Date();
        expires.setDate(expires.getDate() + 30);
        const newUsage = {
          count: 0,
          expires: expires.toISOString(),
        };
        FactcheckCookies.set(
          this.cookieName,
          encodeURIComponent(JSON.stringify(newUsage)),
          30
        );
        return newUsage;
      }
      return usage;
    },

    hasCompletedReport: function (reportId) {
      const cookieName = this.reportCookiePrefix + reportId;
      return FactcheckCookies.get(cookieName) !== null;
    },

    markReportCompleted: function (reportId) {
      const cookieName = this.reportCookiePrefix + reportId;
      FactcheckCookies.set(cookieName, "completed", 30);
      console.log("AI Verify: Marked report completed:", reportId);
    },

    hasUsesRemaining: function () {
      const usage = this.getUsage();
      return usage.count < this.maxFreeUses;
    },

    getRemainingUses: function () {
      const usage = this.getUsage();
      return Math.max(0, this.maxFreeUses - usage.count);
    },

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
      console.log("AI Verify: Usage count:", usage.count);
      return usage.count;
    },

    updateCounter: function () {
      const remaining = this.getRemainingUses();
      const $counter = $("#usageCounter");
      if ($counter.length) {
        if (remaining > 0) {
          $counter.html(
            `${remaining} fact-check${
              remaining !== 1 ? "s" : ""
            } remaining this month`
          );
        } else {
          $counter.html(
            'No free uses remaining. <a href="#" class="upgrade-link">Upgrade to Pro</a>'
          );
        }
      }
    },
  };

  // Subscription System (FIXED)
  const SubscriptionManager = {
    selectedPlan: "free",

    init: function () {
      $(".plan-card").on("click", function () {
        const plan = $(this).data("plan");
        SubscriptionManager.selectPlan(plan);
      });

      $(".plan-select-btn").on("click", function (e) {
        e.stopPropagation();
        const plan = $(this).data("plan");
        SubscriptionManager.selectPlan(plan);
      });

      $("#freePlanForm").on("submit", function (e) {
        e.preventDefault();
        SubscriptionManager.submitFreePlan();
      });

      $("#proPlanForm").on("submit", function (e) {
        e.preventDefault();
        SubscriptionManager.submitProPlan();
      });

      UsageTracker.init();
      UsageTracker.updateCounter();
    },

    selectPlan: function (plan) {
      this.selectedPlan = plan;

      $(".plan-card").removeClass("active");
      $(`.plan-card[data-plan="${plan}"]`).addClass("active");

      $(".plan-form").removeClass("active").hide();
      if (plan === "free") {
        $("#freePlanForm").addClass("active").fadeIn(300);
      } else {
        $("#proPlanForm").addClass("active").fadeIn(300);
      }
    },

    submitFreePlan: function () {
      if (!UsageTracker.hasUsesRemaining()) {
        alert(
          "You have reached your free limit for this month. Please upgrade to Pro for unlimited access."
        );
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
      $btn.find(".btn-text").hide(); // Use .find() for nested elements
      $btn.find(".btn-loading").show(); // Use .find() for nested elements

      // FIXED: Submit AJAX first, THEN set cookie on success
      $.ajax({
        url: aiVerifyFactcheck.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_submit_email", // This is the fixed AJAX endpoint
          nonce: aiVerifyFactcheck.nonce,
          report_id: currentReportId,
          email: email,
          name: name,
          terms_accepted: terms,
          plan: "free",
        },
        success: function (response) {
          if (response.success) {
            // FIXED: Only set cookie after access is granted via AJAX
            UsageTracker.markReportCompleted(currentReportId);
            UsageTracker.incrementUsage();
            UsageTracker.updateCounter(); // Update UI usage counter

            // Hide paywall and show report (Uses the ID added in the template fix)
            $("#factcheckEmailGate").fadeOut(300, function () {
              // Remove blur from report
              $("#factcheckReport").removeClass("report-blurred");
            });

            console.log("AI Verify: Report unlocked successfully");
          } else {
            alert(response.data.message || "Failed to submit");
            $btn.prop("disabled", false).removeClass("loading");
            $btn.find(".btn-text").show();
            $btn.find(".btn-loading").hide();
          }
        },
        error: function (xhr, status, error) {
          // Added arguments for better logging
          console.error("Access Grant Error:", status, error);
          alert("Connection error. Please try again. Status: " + status);
          $btn.prop("disabled", false).removeClass("loading");
          $btn.find(".btn-text").show();
          $btn.find(".btn-loading").hide();
        },
      });
    },

    submitProPlan: function () {
      alert(
        "Stripe integration will be implemented here.\n\nThis will process payment and grant unlimited access."
      );

      const cardName = $("#cardName").val().trim();
      const cardNumber = $("#cardNumber").val().trim();
      const cardExpiry = $("#cardExpiry").val().trim();
      const cardCvc = $("#cardCvc").val().trim();

      if (!cardName || !cardNumber || !cardExpiry || !cardCvc) {
        alert("Please fill all payment fields");
        return;
      }

      // Set pro subscription cookie
      FactcheckCookies.set("ai_verify_pro", "true", 30);

      // Mark this report as completed
      UsageTracker.markReportCompleted(currentReportId);

      // Hide paywall and show report
      $("#factcheckEmailGate").fadeOut(300, function () {
        $("#factcheckReport").removeClass("report-blurred");
      });
    },
  };

  // Header Search
  const HeaderSearch = {
    currentInputType: "auto",

    init: function () {
      if ($(".factcheck-header-search").length === 0) {
        return;
      }

      $(".filter-btn-mini").on("click", function () {
        $(".filter-btn-mini").removeClass("active");
        $(this).addClass("active");
        HeaderSearch.currentInputType = $(this).data("type");
      });

      $(".example-btn-header").on("click", function () {
        const example = $(this).data("example");
        $("#factcheckInputHeader").val(example).focus();
      });

      $("#factcheckSubmitHeader").on("click", function (e) {
        e.preventDefault();
        HeaderSearch.startFactCheck();
      });

      $("#factcheckInputHeader").on("keypress", function (e) {
        if (e.which === 13) {
          e.preventDefault();
          HeaderSearch.startFactCheck();
        }
      });
    },

    startFactCheck: function () {
      const input = $("#factcheckInputHeader").val().trim();

      if (!input) {
        alert("Please enter a URL, title, or claim to fact-check");
        return;
      }

      let inputType = this.currentInputType;
      if (inputType === "auto") {
        inputType = this.detectInputType(input);
      }

      const $btn = $("#factcheckSubmitHeader");
      $btn.prop("disabled", true).addClass("loading");
      $(".btn-text").hide();
      $(".btn-loading").show();

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
            const resultsUrl =
              aiVerifyFactcheck.results_url +
              "?report=" +
              response.data.report_id;
            window.location.href = resultsUrl;
          } else {
            alert(response.data.message || "Failed to start fact-check");
            HeaderSearch.resetButton();
          }
        },
        error: function () {
          alert("Connection error. Please try again.");
          HeaderSearch.resetButton();
        },
      });
    },

    detectInputType: function (input) {
      if (input.match(/^https?:\/\//)) {
        return "url";
      } else if (input.length > 100) {
        return "phrase";
      } else {
        return "title";
      }
    },

    resetButton: function () {
      const $btn = $("#factcheckSubmitHeader");
      $btn.prop("disabled", false).removeClass("loading");
      $(".btn-text").show();
      $(".btn-loading").hide();
    },
  };

  $(document).ready(function () {
    SubscriptionManager.init();
    HeaderSearch.init();

    setInterval(function () {
      UsageTracker.updateCounter();
    }, 5000);
  });

  window.FactcheckCookies = FactcheckCookies;
  window.UsageTracker = UsageTracker;
  window.SubscriptionManager = SubscriptionManager;
})(jQuery);

/**
 * MAIN FACT-CHECK INTERFACE
 */
(function ($) {
  ("use strict");

  let currentInputType = "auto";

  $(document).ready(function () {
    initSearchInterface();
    initResultsPage();
  });

  function initSearchInterface() {
    $(".filter-btn").on("click", function () {
      $(".filter-btn").removeClass("active");
      $(this).addClass("active");
      currentInputType = $(this).data("type");
      updatePlaceholder(currentInputType);
    });

    $(".example-btn").on("click", function () {
      const example = $(this).data("example");
      $("#factcheck-input").val(example).focus();
    });

    $("#factcheck-submit").on("click", function (e) {
      e.preventDefault();
      startFactCheck();
    });

    $("#factcheck-input").on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        startFactCheck();
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
      placeholders[type] || placeholders["auto"]
    );
  }

  function startFactCheck() {
    const input = $("#factcheck-input").val().trim();

    if (!input) {
      showError("Please enter a URL, title, or claim to fact-check");
      return;
    }

    let inputType = currentInputType;
    if (inputType === "auto") {
      inputType = detectInputType(input);
    }

    const $btn = $("#factcheck-submit");
    $btn.prop("disabled", true).addClass("loading");
    $(".btn-text").hide();
    $(".btn-loading").show();

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

  function detectInputType(input) {
    if (input.match(/^https?:\/\//)) {
      return "url";
    } else if (input.length > 100) {
      return "phrase";
    } else {
      return "title";
    }
  }

  function resetButton() {
    const $btn = $("#factcheck-submit");
    $btn.prop("disabled", false).removeClass("loading");
    $(".btn-text").show();
    $(".btn-loading").hide();
  }

  function showError(message) {
    alert(message);
  }

  /**
   * FIXED: Initialize results page
   *
   * CHANGES:
   * - Email gate shows AFTER report loads (not during loading)
   * - Proper cookie checking
   * - Paywall overlay instead of popup
   */
  function initResultsPage() {
    if ($("#factcheckResults").length === 0) {
      return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const reportId = urlParams.get("report");

    if (!reportId) {
      $("#factcheckLoading").html("<p>No report ID provided</p>");
      return;
    }

    currentReportId = reportId;
    console.log("AI Verify: Initializing report:", reportId);

    // Initialize usage tracking
    UsageTracker.init();

    // Check if this report was already completed
    if (UsageTracker.hasCompletedReport(reportId)) {
      console.log("AI Verify: Report already unlocked, loading directly");
      // Skip paywall - go straight to processing
      startProcessing();
      return;
    }

    // NEW: Start processing immediately, show paywall AFTER report loads
    console.log(
      "AI Verify: Starting processing, will show paywall after completion"
    );
    startProcessing();

    // Setup export/share buttons
    $(".export-btn").on("click", function () {
      exportReport($(this).data("format"));
    });

    $(".share-btn").on("click", function () {
      shareReport();
    });
  }

  /**
   * FIXED: Start processing fact-check
   * Shows paywall AFTER completion, not before
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

          // FIXED: Load report first, THEN show paywall if needed
          setTimeout(function () {
            loadReportAndShowPaywall();
          }, 500);
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
   * NEW: Load report and show paywall overlay
   */
  function loadReportAndShowPaywall() {
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
          // Display report first (blurred)
          displayReport(response.data.report, true); // true = show blurred

          // Then show paywall overlay on top
          setTimeout(function () {
            $("#factcheckEmailGate").fadeIn(300);
          }, 500);
        } else {
          alert("Failed to load report");
        }
      },
      error: function () {
        alert("Failed to load report");
      },
    });
  }

  function updateLoadingStep(text, progress) {
    $("#loadingStep").text(text);
    $("#progressBar").css("width", progress + "%");
  }

  /**
   * UPDATED: Display report (can be blurred for paywall)
   */
  function displayReport(report, showBlurred) {
    // Hide loading
    $("#factcheckLoading").fadeOut(300, function () {
      $("#factcheckReport").fadeIn(300);

      // Add blur class if needed
      if (showBlurred) {
        $("#factcheckReport").addClass("report-blurred");
      }
    });

    // Populate report data (same as before)
    $("#reportId").text(report.report_id);
    $("#reportDate").text(formatDate(report.created_at));
    $("#inputValue").text(report.input_value);

    const score = parseFloat(report.overall_score) || 0;
    animateScore(score);
    $("#credibilityRating").text(report.credibility_rating || "Unknown");

    const claims = report.factcheck_results || [];
    $("#claimsCount").text(claims.length);
    displayClaims(claims);

    const sources = report.sources || [];
    $("#sourcesCount").text(sources.length);
    displaySources(sources);

    if (claims.length > 0) {
      $("#analysisMethod").text(claims[0].method || "Multiple Sources");
    }

    if (report.created_at && report.completed_at) {
      const start = new Date(report.created_at);
      const end = new Date(report.completed_at);
      const diff = Math.round((end - start) / 1000);
      $("#analysisTime").text(diff + "s");
    }

    const propaganda = report.metadata?.propaganda_techniques || [];
    if (propaganda.length > 0) {
      displayPropaganda(propaganda);
    }

    setupClaimsFilter();
  }

  function displayPropaganda(techniques) {
    const $warning = $("#propagandaWarning");
    const $list = $("#propagandaList");

    $list.empty();
    techniques.forEach(function (technique) {
      $list.append("<li>" + escapeHtml(technique) + "</li>");
    });

    $warning.fadeIn(300);
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
    const $container = $("#claimsAnalysis");
    $container.empty();

    if (claims.length === 0) {
      $container.html('<p class="no-data">No claims analyzed</p>');
      return;
    }

    claims.forEach(function (claim, index) {
      const ratingClass = getRatingClass(claim.rating);
      const confidencePercent = Math.round((claim.confidence || 0.5) * 100);
      const filterType = getFilterType(claim.rating);

      const $claim = $('<div class="claim-card">').attr(
        "data-filter-type",
        filterType
      ).html(`
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
              claim.evidence_for && claim.evidence_for.length > 0
                ? `
                <div class="claim-evidence">
                    <div class="evidence-section">
                        <div class="evidence-title">✓ Evidence Supporting:</div>
                        <ul class="evidence-list">
                            ${claim.evidence_for
                              .map((e) => "<li>" + escapeHtml(e) + "</li>")
                              .join("")}
                        </ul>
                    </div>
                </div>
            `
                : ""
            }
            
            ${
              claim.evidence_against && claim.evidence_against.length > 0
                ? `
                <div class="claim-evidence">
                    <div class="evidence-section">
                        <div class="evidence-title">✗ Evidence Contradicting:</div>
                        <ul class="evidence-list">
                            ${claim.evidence_against
                              .map((e) => "<li>" + escapeHtml(e) + "</li>")
                              .join("")}
                        </ul>
                    </div>
                </div>
            `
                : ""
            }
            
            ${
              claim.red_flags && claim.red_flags.length > 0
                ? `
                <div class="red-flags-section">
                    <div class="red-flags-title">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        Red Flags Detected
                    </div>
                    <ul class="red-flags-list">
                        ${claim.red_flags
                          .map((flag) => "<li>" + escapeHtml(flag) + "</li>")
                          .join("")}
                    </ul>
                </div>
            `
                : ""
            }
            
            <div class="claim-meta">
                <span class="claim-type">${escapeHtml(
                  claim.type || "general"
                )}</span>
                ${
                  claim.method
                    ? '<span class="claim-method">🔡 ' +
                      escapeHtml(claim.method) +
                      "</span>"
                    : ""
                }
            </div>
        `);

      $container.append($claim);
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
          const $card = $(this);
          const cardFilter = $card.data("filter-type");

          if (cardFilter === filter) {
            $card.removeClass("hidden");
          } else {
            $card.addClass("hidden");
          }
        });
      }
    });
  }

  function getFilterType(rating) {
    const r = (rating || "").toLowerCase();

    if (r.includes("true") && !r.includes("false")) {
      return "true";
    } else if (r.includes("false")) {
      return "false";
    } else if (r.includes("misleading") || r.includes("mixture")) {
      return "misleading";
    } else {
      return "unverified";
    }
  }

  function displaySources(sources) {
    const $container = $("#sourcesList");
    $container.empty();

    if (sources.length === 0) {
      $container.html('<p class="no-data">No sources available</p>');
      return;
    }

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

  function shareReport() {
    const url = window.location.href;

    if (navigator.share) {
      navigator.share({
        title: "Fact-Check Report",
        url: url,
      });
    } else {
      const $temp = $("<input>");
      $("body").append($temp);
      $temp.val(url).select();
      document.execCommand("copy");
      $temp.remove();
      alert("Link copied to clipboard!");
    }
  }

  function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + " " + date.toLocaleTimeString();
  }

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

  window.startProcessing = startProcessing;
})(jQuery);
