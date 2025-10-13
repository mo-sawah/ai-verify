/**
 * Fact-Check System JavaScript (SIMPLIFIED ACCESS)
 *
 * - Implements a simple 30-day cookie-based access system.
 * - Removes complex subscription and usage tracking logic.
 * - Handles the new simple access form.
 */

let currentReportId = null;

(function ($) {
  "use strict";

  // --- COOKIE MANAGEMENT UTILITY ---
  const FactcheckCookies = {
    set: function (name, value, days) {
      const d = new Date();
      d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
      const expires = "expires=" + d.toUTCString();
      document.cookie =
        name + "=" + value + ";" + expires + ";path=/;SameSite=Lax";
      console.log(
        "AI Verify: Set cookie:",
        name,
        "=",
        value,
        "for",
        days,
        "days"
      );
    },
    get: function (name) {
      const nameEQ = name + "=";
      const ca = document.cookie.split(";");
      for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === " ") c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) {
          const value = c.substring(nameEQ.length, c.length);
          console.log("AI Verify: Found cookie", name, "=", value);
          return value;
        }
      }
      return null;
    },
  };

  // --- NEW: 30-DAY ACCESS MANAGER ---
  const AccessManager = {
    cookieName: "ai_verify_access_granted",
    hasAccess: function () {
      return FactcheckCookies.get(this.cookieName) === "true";
    },
    grantAccess: function () {
      FactcheckCookies.set(this.cookieName, "true", 30);
      console.log("AI Verify: 30-day access granted.");
    },
  };

  // --- HEADER SEARCH (No changes needed here) ---
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

  // --- MAIN FACT-CHECK INTERFACE ---
  function initSearchInterface() {
    if ($(".factcheck-search-wrapper").length === 0) return;

    let currentInputType = "auto";
    $(".filter-btn").on("click", function () {
      $(".filter-btn").removeClass("active");
      $(this).addClass("active");
      currentInputType = $(this).data("type");
      updatePlaceholder(currentInputType);
    });
    $(".example-btn").on("click", function () {
      $("#factcheck-input").val($(this).data("example")).focus();
    });
    $("#factcheck-submit").on("click", (e) => {
      e.preventDefault();
      startFactCheck(currentInputType);
    });
    $("#factcheck-input").on("keypress", (e) => {
      if (e.which === 13) {
        e.preventDefault();
        startFactCheck(currentInputType);
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

  function startFactCheck(currentInputType) {
    const input = $("#factcheck-input").val().trim();
    if (!input) {
      alert("Please enter a URL, title, or claim to fact-check");
      return;
    }

    let inputType = currentInputType;
    if (inputType === "auto") {
      inputType = HeaderSearch.detectInputType(input); // Reuse detector
    }

    const $btn = $("#factcheck-submit");
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
      success: (response) => {
        if (response.success) {
          window.location.href =
            aiVerifyFactcheck.results_url +
            "?report=" +
            response.data.report_id;
        } else {
          alert(response.data.message || "Failed to start fact-check");
          resetSearchButton();
        }
      },
      error: () => {
        alert("Connection error. Please try again.");
        resetSearchButton();
      },
    });
  }

  function resetSearchButton() {
    const $btn = $("#factcheck-submit");
    $btn.prop("disabled", false).removeClass("loading");
    $btn.find(".btn-text").show();
    $btn.find(".btn-loading").hide();
  }

  // --- RESULTS PAGE LOGIC ---
  function initResultsPage() {
    if ($("#factcheckResults").length === 0) return;

    const urlParams = new URLSearchParams(window.location.search);
    const reportId = urlParams.get("report");
    if (!reportId) {
      $("#factcheckLoading").html("<p>No report ID provided</p>");
      return;
    }
    currentReportId = reportId;

    if (AccessManager.hasAccess()) {
      console.log(
        "AI Verify: 30-day access cookie found. Loading report directly."
      );
      startProcessing(false); // false = don't show gate
    } else {
      console.log(
        "AI Verify: No access cookie. Will show form after processing."
      );
      startProcessing(true); // true = show gate
    }
  }

  function startProcessing(showGate) {
    updateLoadingStep("Extracting content...", 25);
    $.ajax({
      url: aiVerifyFactcheck.ajax_url,
      type: "POST",
      data: {
        action: "ai_verify_process_factcheck",
        nonce: aiVerifyFactcheck.nonce,
        report_id: currentReportId,
      },
      success: (response) => {
        if (response.success) {
          updateLoadingStep("Processing complete!", 100);
          setTimeout(() => loadReport(showGate), 500);
        } else {
          updateLoadingStep(
            "Error: " + (response.data.message || "Processing failed"),
            0
          );
        }
      },
      error: () => updateLoadingStep("Connection error", 0),
    });
  }

  function loadReport(showGate) {
    $.ajax({
      url: aiVerifyFactcheck.ajax_url,
      type: "POST",
      data: {
        action: "ai_verify_get_report",
        nonce: aiVerifyFactcheck.nonce,
        report_id: currentReportId,
      },
      success: (response) => {
        if (response.success && response.data.report) {
          displayReport(response.data.report);
          if (showGate) {
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

  function handleSimpleFormSubmit(e) {
    e.preventDefault();
    const email = $("#userEmail").val().trim();
    const name = $("#userName").val().trim();
    const terms = $("#termsAccept").is(":checked");
    if (!email || !name || !terms) {
      alert("Please fill all fields and accept the terms.");
      return;
    }
    const $btn = $("#simpleAccessSubmit");
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
        plan: "free", // Keep this for backend compatibility
      },
      success: (response) => {
        if (response.success) {
          AccessManager.grantAccess();
          $("#factcheckEmailGate").fadeOut(300, () => {
            $("#factcheckReport").removeClass("report-blurred");
          });
        } else {
          alert(response.data.message || "Failed to submit");
          $btn.prop("disabled", false).removeClass("loading");
          $btn.find(".btn-text").show();
          $btn.find(".btn-loading").hide();
        }
      },
      error: () => {
        alert("Connection error. Please try again.");
        $btn.prop("disabled", false).removeClass("loading");
        $btn.find(".btn-text").show();
        $btn.find(".btn-loading").hide();
      },
    });
  }

  function updateLoadingStep(text, progress) {
    $("#loadingStep").text(text);
    $("#progressBar").css("width", progress + "%");
  }

  function displayReport(report) {
    $("#factcheckLoading").fadeOut(300, () => {
      $("#factcheckReport").fadeIn(300);
    });

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
    const propaganda =
      report.metadata && report.metadata.propaganda_techniques
        ? report.metadata.propaganda_techniques
        : [];
    if (propaganda.length > 0) {
      displayPropaganda(propaganda);
    }
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
    if (!claims || claims.length === 0) {
      $container.html('<p class="no-data">No claims analyzed</p>');
      return;
    }
    claims.forEach((claim, index) => {
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
                ? `<div class="claim-evidence"><div class="evidence-section"><div class="evidence-title">✓ Evidence Supporting:</div><ul class="evidence-list">${claim.evidence_for
                    .map((e) => "<li>" + escapeHtml(e) + "</li>")
                    .join("")}</ul></div></div>`
                : ""
            }
            ${
              claim.evidence_against && claim.evidence_against.length > 0
                ? `<div class="claim-evidence"><div class="evidence-section"><div class="evidence-title">✗ Evidence Contradicting:</div><ul class="evidence-list">${claim.evidence_against
                    .map((e) => "<li>" + escapeHtml(e) + "</li>")
                    .join("")}</ul></div></div>`
                : ""
            }
            ${
              claim.red_flags && claim.red_flags.length > 0
                ? `<div class="red-flags-section"><div class="red-flags-title"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>Red Flags Detected</div><ul class="red-flags-list">${claim.red_flags
                    .map((flag) => "<li>" + escapeHtml(flag) + "</li>")
                    .join("")}</ul></div>`
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
            </div>`);
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
    if (!sources || sources.length === 0) {
      $container.html('<p class="no-data">No sources available</p>');
      return;
    }
    const uniqueSources = [];
    const seen = new Set();
    sources.forEach((source) => {
      const key = source.name + source.url;
      if (!seen.has(key)) {
        seen.add(key);
        uniqueSources.push(source);
      }
    });
    uniqueSources.forEach((source) => {
      const $source = $('<div class="source-card">').html(`
        <div class="source-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg></div>
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
        </div>`);
      $container.append($source);
    });
  }

  function getRatingClass(rating) {
    const r = (rating || "").toLowerCase();
    if (r.includes("true") && !r.includes("false")) return "rating-true";
    if (r.includes("false")) return "rating-false";
    if (r.includes("mixture") || r.includes("mixed")) return "rating-mixture";
    return "rating-unknown";
  }

  function formatDate(dateString) {
    if (!dateString) return "N/A";
    const date = new Date(dateString);
    return date.toLocaleDateString() + " " + date.toLocaleTimeString();
  }

  function escapeHtml(text) {
    if (typeof text !== "string") return "";
    return text.replace(
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

  $(document).ready(function () {
    HeaderSearch.init();
    initSearchInterface();
    initResultsPage();
    // New listener for the simplified form
    $(document).on("submit", "#simpleAccessForm", handleSimpleFormSubmit);
  });
})(jQuery);
