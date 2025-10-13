/**
 * Fact-Check System JavaScript
 */

let currentReportId = null;

/**
 * Cookie-Based Usage Tracking & Subscription System
 * ADD THIS CODE TO THE BEGINNING OF factcheck.js
 */

(function ($) {
  "use strict";

  // Cookie Management
  const FactcheckCookies = {
    // Set cookie
    set: function (name, value, days) {
      const d = new Date();
      d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
      const expires = "expires=" + d.toUTCString();
      document.cookie = name + "=" + value + ";" + expires + ";path=/";
    },

    // Get cookie
    get: function (name) {
      const nameEQ = name + "=";
      const ca = document.cookie.split(";");
      for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == " ") c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
      }
      return null;
    },

    // Delete cookie
    delete: function (name) {
      document.cookie =
        name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    },
  };

  // Usage Tracking System
  const UsageTracker = {
    cookieName: "ai_verify_usage",
    maxFreeUses: 5,

    // Get current usage data
    getUsage: function () {
      const data = FactcheckCookies.get(this.cookieName);
      if (!data) {
        return { count: 0, expires: null };
      }
      try {
        return JSON.parse(data);
      } catch (e) {
        return { count: 0, expires: null };
      }
    },

    // Initialize usage tracking (30 days)
    init: function () {
      const usage = this.getUsage();
      if (!usage.expires || new Date() > new Date(usage.expires)) {
        // Reset for new month
        const expires = new Date();
        expires.setDate(expires.getDate() + 30);
        const newUsage = {
          count: 0,
          expires: expires.toISOString(),
        };
        FactcheckCookies.set(this.cookieName, JSON.stringify(newUsage), 30);
        return newUsage;
      }
      return usage;
    },

    // Check if user has uses remaining
    hasUsesRemaining: function () {
      const usage = this.getUsage();
      return usage.count < this.maxFreeUses;
    },

    // Get remaining uses
    getRemainingUses: function () {
      const usage = this.getUsage();
      return Math.max(0, this.maxFreeUses - usage.count);
    },

    // Increment usage counter
    incrementUsage: function () {
      const usage = this.getUsage();
      usage.count = (usage.count || 0) + 1;

      if (!usage.expires) {
        const expires = new Date();
        expires.setDate(expires.getDate() + 30);
        usage.expires = expires.toISOString();
      }

      FactcheckCookies.set(this.cookieName, JSON.stringify(usage), 30);
      return usage.count;
    },

    // Update counter display
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

  // Subscription System
  const SubscriptionManager = {
    selectedPlan: "free",

    init: function () {
      // Plan selection
      $(".plan-card").on("click", function () {
        const plan = $(this).data("plan");
        SubscriptionManager.selectPlan(plan);
      });

      $(".plan-select-btn").on("click", function (e) {
        e.stopPropagation();
        const plan = $(this).data("plan");
        SubscriptionManager.selectPlan(plan);
      });

      // Form submissions
      $("#freePlanForm").on("submit", function (e) {
        e.preventDefault();
        SubscriptionManager.submitFreePlan();
      });

      $("#proPlanForm").on("submit", function (e) {
        e.preventDefault();
        SubscriptionManager.submitProPlan();
      });

      // Initialize usage counter
      UsageTracker.init();
      UsageTracker.updateCounter();
    },

    selectPlan: function (plan) {
      this.selectedPlan = plan;

      // Update UI
      $(".plan-card").removeClass("active");
      $(`.plan-card[data-plan="${plan}"]`).addClass("active");

      // Show appropriate form
      $(".plan-form").removeClass("active").hide();
      if (plan === "free") {
        $("#freePlanForm").addClass("active").fadeIn(300);
      } else {
        $("#proPlanForm").addClass("active").fadeIn(300);
      }
    },

    submitFreePlan: function () {
      // Check usage limit
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

      // Disable button
      $("#freePlanSubmit").prop("disabled", true).addClass("loading");
      $(".btn-text").hide();
      $(".btn-loading").show();

      // Increment usage
      UsageTracker.incrementUsage();

      // Submit via AJAX (same as before)
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
            $("#factcheckEmailGate").fadeOut(300, function () {
              $("#factcheckLoading").fadeIn(300);
              startProcessing();
            });
          } else {
            alert(response.data.message || "Failed to submit");
            $("#freePlanSubmit").prop("disabled", false).removeClass("loading");
            $(".btn-text").show();
            $(".btn-loading").hide();
          }
        },
        error: function () {
          alert("Connection error");
          $("#freePlanSubmit").prop("disabled", false).removeClass("loading");
          $(".btn-text").show();
          $(".btn-loading").hide();
        },
      });
    },

    submitProPlan: function () {
      // For now, just show a demo message
      // In production, integrate with Stripe
      alert(
        "Stripe integration will be implemented here.\n\nThis will process the payment and grant unlimited access."
      );

      // TODO: Integrate Stripe payment
      // After successful payment:
      // 1. Set pro cookie
      // 2. Submit email to backend
      // 3. Show results

      // Demo: Just proceed for now
      const cardName = $("#cardName").val().trim();
      const cardNumber = $("#cardNumber").val().trim();
      const cardExpiry = $("#cardExpiry").val().trim();
      const cardCvc = $("#cardCvc").val().trim();

      if (!cardName || !cardNumber || !cardExpiry || !cardCvc) {
        alert("Please fill all payment fields");
        return;
      }

      // Set pro subscription cookie (30 days for demo)
      FactcheckCookies.set("ai_verify_pro", "true", 30);

      // Close modal and show results
      $("#factcheckEmailGate").fadeOut(300, function () {
        $("#factcheckLoading").fadeIn(300);
        startProcessing();
      });
    },
  };

  // Header Search Functionality
  const HeaderSearch = {
    currentInputType: "auto",

    init: function () {
      if ($(".factcheck-header-search").length === 0) {
        return;
      }

      // Filter buttons
      $(".filter-btn-mini").on("click", function () {
        $(".filter-btn-mini").removeClass("active");
        $(this).addClass("active");
        HeaderSearch.currentInputType = $(this).data("type");
      });

      // Example buttons
      $(".example-btn-header").on("click", function () {
        const example = $(this).data("example");
        $("#factcheckInputHeader").val(example).focus();
      });

      // Submit button
      $("#factcheckSubmitHeader").on("click", function (e) {
        e.preventDefault();
        HeaderSearch.startFactCheck();
      });

      // Enter key
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

      // Detect input type if auto
      let inputType = this.currentInputType;
      if (inputType === "auto") {
        inputType = this.detectInputType(input);
      }

      // Show loading state
      const $btn = $("#factcheckSubmitHeader");
      $btn.prop("disabled", true).addClass("loading");
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
            // Redirect to results page
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

  // Initialize everything on document ready
  $(document).ready(function () {
    SubscriptionManager.init();
    HeaderSearch.init();

    // Update usage counter periodically
    setInterval(function () {
      UsageTracker.updateCounter();
    }, 5000);
  });

  // Export for use in other parts of the script
  window.FactcheckCookies = FactcheckCookies;
  window.UsageTracker = UsageTracker;
  window.SubscriptionManager = SubscriptionManager;
})(jQuery);

(function ($) {
  ("use strict");

  let currentInputType = "auto";

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
      // --- THIS IS THE ONLY CHANGE HERE ---
      // Instead of the old showEmailGate(), just fade in the new one by its ID
      $("#factcheckEmailGate").fadeIn(300);

      // The new SubscriptionManager will handle everything else
      $("#factcheckLoading").fadeOut(300);
    }, 2000);

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
   * Display report data (UPDATED)
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

    // Analysis method
    if (claims.length > 0) {
      $("#analysisMethod").text(claims[0].method || "Multiple Sources");
    }

    // Analysis time
    if (report.created_at && report.completed_at) {
      const start = new Date(report.created_at);
      const end = new Date(report.completed_at);
      const diff = Math.round((end - start) / 1000);
      $("#analysisTime").text(diff + "s");
    }

    // Propaganda warning
    const propaganda = report.metadata?.propaganda_techniques || [];
    if (propaganda.length > 0) {
      displayPropaganda(propaganda);
    }

    // Setup filter buttons
    setupClaimsFilter();
  }

  /**
   * Display propaganda techniques
   */
  function displayPropaganda(techniques) {
    const $warning = $("#propagandaWarning");
    const $list = $("#propagandaList");

    $list.empty();
    techniques.forEach(function (technique) {
      $list.append("<li>" + escapeHtml(technique) + "</li>");
    });

    $warning.fadeIn(300);
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
   * Display claims (UPDATED - Shows ALL claims with filters)
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
                    ? '<span class="claim-method">📡 ' +
                      escapeHtml(claim.method) +
                      "</span>"
                    : ""
                }
            </div>
        `);

      $container.append($claim);
    });
  }

  /**
   * Setup claims filter buttons
   */
  function setupClaimsFilter() {
    $(".filter-chip").on("click", function () {
      const filter = $(this).data("filter");

      // Update active state
      $(".filter-chip").removeClass("active");
      $(this).addClass("active");

      // Filter claims
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

  /**
   * Get filter type from rating
   */
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

  window.startProcessing = startProcessing;
})(jQuery);
