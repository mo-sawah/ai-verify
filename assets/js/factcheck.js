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
  ("use strict");

  // --- UTILITIES ---

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
          return true;
        }
      }
      return false;
    },
  };

  const EmailGate = {
    init: function () {
      // Use event delegation for a robust form handler that prevents page reloads
      $(document).on("submit", "#simpleAccessForm", function (e) {
        e.preventDefault(); // This is the crucial part that stops the page reload
        EmailGate.handleSubmission();
      });
    },

    handleSubmission: function () {
      const email = $("#userEmail").val().trim();
      const name = $("#userName").val().trim();

      if (!email || !name || !$("#termsAccept").is(":checked")) {
        alert("Please fill in all fields and accept the terms to continue.");
        return;
      }

      const $btn = $("#simpleAccessSubmit"); // Make sure your button ID is correct
      $btn.prop("disabled", true).addClass("loading");
      $btn.find(".btn-text").hide();
      $btn.find(".btn-loading").css("display", "inline-block");

      $.ajax({
        url: aiVerifyFactcheck.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_submit_email",
          nonce: aiVerifyFactcheck.nonce,
          report_id: currentReportId,
          email: email,
          name: name,
          plan: "free", // Keep this for backend consistency if needed
        },
        success: function (response) {
          if (response.success) {
            // On success, set the cookie and unlock the content
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
          // Use the report URL from server response
          window.location.href = response.data.report_url;
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

    // Start the report generation process immediately.
    // The decision to show the paywall will be made after the report loads.
    startProcessing();

    // Keep event listeners for export and share buttons
    $(".export-btn").on("click", function () {
      exportReport($(this).data("format"));
    });
    $(".share-btn").on("click", shareReport);
  }

  function startProcessing() {
    updateLoadingStep("Starting analysis...", 0);

    // Start the background process
    $.ajax({
      url: aiVerifyFactcheck.ajax_url,
      type: "POST",
      timeout: 30000, // Only 30 seconds - just to start
      data: {
        action: "ai_verify_process_factcheck",
        nonce: aiVerifyFactcheck.nonce,
        report_id: currentReportId,
      },
      success: function (response) {
        if (response.success) {
          // Process started - begin polling
          updateLoadingStep("Processing claims...", 10);
          pollForCompletion();
        } else {
          updateLoadingStep(
            "Error: " + (response.data.message || "Failed to start"),
            0
          );
        }
      },
      error: function (xhr, status, error) {
        console.error("Start Error:", status, error);
        updateLoadingStep("Failed to start processing", 0);
      },
    });
  }

  function pollForCompletion() {
    let attempts = 0;
    const maxAttempts = 120; // 120 attempts × 3 seconds = 6 minutes max

    const pollInterval = setInterval(function () {
      attempts++;

      // Update progress bar based on time (fake progress)
      const fakeProgress = Math.min(10 + attempts * 0.7, 95);
      updateLoadingStep("Analyzing content...", fakeProgress);

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
            const status = response.data.status;

            if (status === "completed") {
              clearInterval(pollInterval);
              updateLoadingStep("Processing complete!", 100);
              setTimeout(() => loadReport(), 500);
            } else if (status === "failed") {
              clearInterval(pollInterval);
              updateLoadingStep("Processing failed", 0);
            }
            // Otherwise keep polling
          }
        },
        error: function () {
          console.log("Poll attempt " + attempts + " failed, retrying...");
        },
      });

      // Stop after max attempts
      if (attempts >= maxAttempts) {
        clearInterval(pollInterval);
        updateLoadingStep("Processing timeout - please refresh the page", 0);
      }
    }, 3000); // Poll every 3 seconds
  }

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
          // 1. Display the report data first, so it's ready in the background.
          displayReport(response.data.report);

          // 2. NOW, check if the user has access.
          if (AccessManager.hasAccessCookie()) {
            // If they have access, make sure the content is not blurred.
            EmailGate.unlockContent();
          } else {
            // If they DON'T have access, show the email gate which will blur the content.
            EmailGate.show();
          }
        } else {
          alert("Failed to load report data.");
        }
      },
      error: () =>
        alert("A connection error occurred while loading the report."),
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

    // Populate source article card
    populateSourceCard(report);

    // Animate score with color
    const score = parseFloat(report.overall_score) || 0;
    animateScore(score);

    // Update credibility rating with enhanced styling
    updateCredibilityRating(report.credibility_rating || "Unknown", score);

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

  /**
   * IMPROVED populateSourceCard Function
   * Replace lines 411-505 in factcheck.js with this version
   * This version correctly uses metadata instead of trying to parse HTML
   */

  function populateSourceCard(report) {
    if (report.input_type === "url" && report.input_value) {
      try {
        const url = new URL(report.input_value);
        const domain = url.hostname.replace("www.", "");

        // Show the card
        $("#sourceArticleCard").fadeIn(300);

        // Set inline favicon before domain name
        const faviconUrl =
          report.metadata?.favicon ||
          `https://www.google.com/s2/favicons?domain=${domain}&sz=32`;
        $("#sourceFaviconInline").html(
          `<img src="${faviconUrl}" alt="${domain}" onerror="this.style.display='none'">`
        );

        // Get title from metadata (preferred) or fall back to other sources
        let title = report.metadata?.title || report.input_value;

        // Get description from metadata
        let description = report.metadata?.description || "";

        // Get featured image from metadata
        let featuredImage = report.metadata?.featured_image || null;

        // Fallback image if no featured image found
        const fallbackImage =
          "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0ic3lzdGVtLXVpLCAtYXBwbGUtc3lzdGVtLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjE4IiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2UgQXZhaWxhYmxlPC90ZXh0Pjwvc3ZnPg==";

        // Set title as clickable link
        $("#sourceTitle").text(title);
        $("#sourceUrl").attr("href", report.input_value);

        // Set domain
        const displayDomain = report.metadata?.domain || domain;
        $("#sourceDomain").text(displayDomain);

        // Build date display with author and modification date
        let dateInfo = [];

        // Add publish date
        if (report.metadata?.date) {
          const publishDate = new Date(report.metadata.date).toLocaleDateString(
            "en-US",
            {
              year: "numeric",
              month: "short",
              day: "numeric",
            }
          );
          dateInfo.push(`Published: ${publishDate}`);
        } else {
          dateInfo.push(formatDate(report.created_at));
        }

        // Add author if available
        if (report.metadata?.author) {
          dateInfo.push(`By ${report.metadata.author}`);
        }

        // Add modified date if different from publish date
        if (
          report.metadata?.date_modified &&
          report.metadata.date_modified !== report.metadata.date
        ) {
          const modifiedDate = new Date(
            report.metadata.date_modified
          ).toLocaleDateString("en-US", {
            year: "numeric",
            month: "short",
            day: "numeric",
          });
          dateInfo.push(`Updated: ${modifiedDate}`);
        }

        $("#sourceDate").html(
          dateInfo.join(' <span class="source-separator">•</span> ')
        );

        // Display featured image or fallback
        const imageToDisplay = featuredImage || fallbackImage;
        const $img = $("#sourceImageImg");

        $img.on("error", function () {
          // If real image fails to load, use fallback
          if (this.src !== fallbackImage) {
            this.src = fallbackImage;
          }
        });

        $img.attr("src", imageToDisplay).attr("alt", title);
        $("#sourceImage").fadeIn(200);

        // Update badge
        const score = parseFloat(report.overall_score) || 0;
        $("#badgeScore").text(Math.round(score));
        $("#badgeRating").text(
          getShortRating(report.credibility_rating || "Unknown")
        );

        // Apply badge color based on score
        const badgeEl = $("#credibilityBadge");
        badgeEl.removeClass("badge-high badge-medium badge-low badge-very-low");
        if (score >= 75) {
          badgeEl.addClass("badge-high");
        } else if (score >= 50) {
          badgeEl.addClass("badge-medium");
        } else if (score >= 25) {
          badgeEl.addClass("badge-low");
        } else {
          badgeEl.addClass("badge-very-low");
        }

        // Log metadata for debugging
        console.log("Source Card Metadata:", {
          title: title,
          description: description,
          image: featuredImage ? "YES" : "NO (using fallback)",
          domain: displayDomain,
          date: displayDate,
        });
      } catch (e) {
        console.log("Could not parse URL for source card:", e);
      }
    }
  }

  function getShortRating(fullRating) {
    const ratingMap = {
      "Highly Credible": "Highly Credible",
      "Mostly Credible": "Credible",
      "Mixed Credibility": "Mixed",
      "Low Credibility": "Low Credibility",
      "Not Credible": "Not Credible",
    };
    return ratingMap[fullRating] || fullRating;
  }

  function updateCredibilityRating(rating, score) {
    const ratingEl = $("#ratingBadgeLarge");
    const ratingText = $("#credibilityRating");

    // Set text
    ratingText.text(rating);

    // Apply color class based on rating
    ratingEl.removeClass(
      "rating-high rating-medium rating-low rating-very-low"
    );
    if (score >= 75) {
      ratingEl.addClass("rating-high");
    } else if (score >= 50) {
      ratingEl.addClass("rating-medium");
    } else if (score >= 25) {
      ratingEl.addClass("rating-low");
    } else {
      ratingEl.addClass("rating-very-low");
    }
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
  // Initialize all components on document ready
  $(document).ready(function () {
    // Initializes the search handlers ("Analyze" button)
    initSearchInterface(".factcheck-search-wrapper", false);
    initSearchInterface(".factcheck-header-search", true);

    // Initializes the results page logic (loading, polling, displaying)
    initResultsPage();

    // Initializes our new email gate form handler
    EmailGate.init();
  });
})(jQuery);
