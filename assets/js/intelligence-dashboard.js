/**
 * Intelligence Dashboard JavaScript
 * Handles interactivity, real-time updates, and theme detection
 */

(function ($) {
  "use strict";

  const Dashboard = {
    // State
    currentPage: 1,
    itemsPerPage: 20,
    totalClaims: 0,

    // State
    filters: {
      category: "all",
      platform: "all",
      velocity: "all",
      timeframe: "7days",
      search: "",
    },

    refreshInterval: null,

    /**
     * Initialize dashboard
     */
    init: function () {
      console.log("AI Verify: Initializing Intelligence Dashboard");

      // Detect and apply theme
      this.detectTheme();

      // Setup event listeners
      this.setupEventListeners();

      // Initial data load
      this.loadData();

      // Load stats
      this.loadStats();

      // Load chart data (with retry logic for DashboardCharts)
      this.loadChartData();

      // Load propaganda data
      this.loadPropagandaData();

      // Start auto-refresh (every 60 seconds)
      this.startAutoRefresh();

      // Initialize DashboardCharts base (without data)
      setTimeout(() => {
        if (typeof DashboardCharts !== "undefined") {
          DashboardCharts.init();
        }
      }, 500);
    },

    /**
     * Detect and apply theme class
     */
    detectTheme: function () {
      const body = $("body");

      // Check if theme class already exists
      if (body.hasClass("s-dark")) {
        console.log("AI Verify: Dark theme detected");
        return;
      }

      if (body.hasClass("s-light")) {
        console.log("AI Verify: Light theme detected");
        return;
      }

      // Default to light if no class found
      body.addClass("s-light");
      console.log("AI Verify: No theme detected, defaulting to light");
    },

    /**
     * Load chart data via AJAX (with retry logic)
     */
    loadChartData: function () {
      const self = this;

      console.log("AI Verify: Loading chart data...");

      $.ajax({
        url: aiVerifyDashboard.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_get_chart_data",
          nonce: aiVerifyDashboard.nonce,
          timeframe: self.filters.timeframe,
        },
        success: function (response) {
          console.log("Chart data response:", response);

          if (response && response.success && response.data) {
            console.log("Chart data received:", response.data);

            // Wait for DashboardCharts to be available, then initialize
            self.initChartsWhenReady(response.data);
          } else {
            console.error("Invalid chart data response:", response);
          }
        },
        error: function (xhr, status, error) {
          console.error("Chart data AJAX error:", error);
          console.error("Status:", status);
          console.error("Response:", xhr.responseText);
        },
      });
    },

    /**
     * Wait for DashboardCharts to load, then initialize with data
     */
    initChartsWhenReady: function (data, attempts) {
      attempts = attempts || 0;
      const self = this;
      const maxAttempts = 20; // Wait up to 2 seconds

      if (typeof DashboardCharts !== "undefined") {
        console.log(
          "AI Verify: DashboardCharts found, initializing with data..."
        );

        // Trigger custom event with data
        $(document).trigger("charts:dataLoaded", [data]);

        // Initialize charts with real data
        DashboardCharts.initWithRealData(data);
      } else {
        if (attempts < maxAttempts) {
          console.log(
            "AI Verify: Waiting for DashboardCharts... (attempt " +
              (attempts + 1) +
              "/" +
              maxAttempts +
              ")"
          );

          // Try again after 100ms
          setTimeout(function () {
            self.initChartsWhenReady(data, attempts + 1);
          }, 100);
        } else {
          console.error(
            "AI Verify: DashboardCharts failed to load after " +
              maxAttempts +
              " attempts"
          );
          console.error(
            "Check if Chart.js file exists at: " +
              (aiVerifyDashboard.plugin_url || "") +
              "assets/js/Chart.js"
          );

          // Show error message to user
          $("#analyticsContent").prepend(
            '<div style="background: #fee; color: #c53030; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">' +
              "<strong>⚠️ Charts failed to load.</strong> Please refresh the page or contact support." +
              "</div>"
          );
        }
      }
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function () {
      const self = this;

      // Search input
      $("#dashboardSearch").on("input", function () {
        clearTimeout(self.searchTimeout);
        self.searchTimeout = setTimeout(function () {
          self.filters.search = $("#dashboardSearch").val();
          self.loadData();
        }, 500);
      });

      // Filter dropdowns
      $(".filter-select").on("change", function () {
        const filterType = $(this).data("filter");
        const filterValue = $(this).val();
        self.filters[filterType] = filterValue;

        console.log("Filter changed:", filterType, "=", filterValue);
        console.log("Current filters:", self.filters);

        self.loadData();

        // Reload charts when timeframe changes
        if (filterType === "timeframe") {
          self.loadChartData();
        }
      });

      // Filter chips
      $(".filter-chip").on("click", function () {
        $(".filter-chip").removeClass("active");
        $(this).addClass("active");

        const filterType = $(this).data("filter-type");
        const filterValue = $(this).data("filter-value");

        self.filters[filterType] = filterValue;

        // Also update the dropdown
        $("#filterVelocity").val(filterValue);

        console.log("Chip clicked:", filterType, "=", filterValue);

        self.loadData();
      });

      // Analytics toggle
      $("#analyticsToggle").on("click", function () {
        $("#analyticsContent").slideToggle(300);
        $(this).toggleClass("collapsed");
        const isCollapsed = $(this).hasClass("collapsed");
        $(this)
          .find("span")
          .text(isCollapsed ? "Expand" : "Collapse");
      });

      // Refresh button
      $("#refreshButton").on("click", function () {
        $(this).addClass("spinning");
        self.loadData();
        self.loadStats();
        self.loadChartData(); // Also refresh charts

        setTimeout(function () {
          $("#refreshButton").removeClass("spinning");
        }, 1000);
      });

      // Claim action buttons
      $(document).on("click", ".action-investigate", function () {
        const url = $(this).data("url");
        if (url && url !== "#") {
          window.open(url, "_blank");
        } else {
          alert("Investigation modal coming in Phase 3!");
        }
      });
    },

    /**
     * Load dashboard data (with pagination)
     */
    loadData: function (loadMore) {
      loadMore = loadMore || false;
      const self = this;

      if (!loadMore) {
        self.currentPage = 1;
        $("#claimsGrid").html(
          '<div class="loading-spinner"><div class="spinner"></div><p class="loading-text">Loading claims...</p></div>'
        );
      } else {
        self.currentPage++;
      }

      $.ajax({
        url: aiVerifyDashboard.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_dashboard_refresh",
          nonce: aiVerifyDashboard.nonce,
          category: self.filters.category,
          platform: self.filters.platform,
          velocity: self.filters.velocity,
          timeframe: self.filters.timeframe,
          search: self.filters.search,
          page: self.currentPage,
          per_page: self.itemsPerPage,
        },
        success: function (response) {
          if (response.success) {
            self.totalClaims = response.data.total;

            if (loadMore) {
              // Append to existing claims
              response.data.claims.forEach(function (claim, index) {
                const html = self.renderClaimCard(
                  claim,
                  (self.currentPage - 1) * self.itemsPerPage + index
                );
                $("#claimsGrid .load-more-wrapper").before(html);
              });
            } else {
              // Replace all claims
              self.renderClaims(response.data.claims);
            }

            self.updateCount(response.data.total);
            self.updateLoadMoreButton();
          } else {
            self.showError("Failed to load data");
          }
        },
        error: function () {
          self.showError("Connection error");
        },
      });
    },

    /**
     * Update or add Load More button
     */
    updateLoadMoreButton: function () {
      const self = this;
      const loaded = self.currentPage * self.itemsPerPage;
      const remaining = self.totalClaims - loaded;

      // Remove existing button
      $(".load-more-wrapper").remove();

      if (remaining > 0) {
        const buttonHtml =
          '<div class="load-more-wrapper" style="text-align: center; padding: 30px;">' +
          '<button id="loadMoreBtn" class="load-more-btn">' +
          '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">' +
          '<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>' +
          "</svg>" +
          " Load More (" +
          remaining +
          " remaining)" +
          "</button>" +
          "</div>";

        $("#claimsGrid").append(buttonHtml);

        // Add click handler
        $("#loadMoreBtn").on("click", function () {
          $(this)
            .prop("disabled", true)
            .html(
              '<span class="spinner" style="width: 20px; height: 20px;"></span> Loading...'
            );
          self.loadData(true);
        });
      }
    },

    /**
     * Load dashboard statistics
     */
    loadStats: function () {
      $.ajax({
        url: aiVerifyDashboard.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_dashboard_stats",
          nonce: aiVerifyDashboard.nonce,
        },
        success: function (response) {
          if (response.success) {
            $("#statActiveClaims").text(response.data.active_claims);
            $("#statViralClaims").text(response.data.viral_claims);
            $("#statVerified").text(response.data.verified_claims);
            $("#statChecksPerHour").text(response.data.checks_per_hour + "K");
            $("#statHighAlert").text(response.data.high_alert);
          }
        },
      });
    },

    /**
     * Render claims grid
     */
    renderClaims: function (claims) {
      const $grid = $("#claimsGrid");

      if (!claims || claims.length === 0) {
        $grid.html(
          '<div class="empty-state">' +
            '<div class="empty-icon">' +
            '<svg width="64" height="64" fill="currentColor" viewBox="0 0 20 20">' +
            '<path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"/>' +
            "</svg>" +
            "</div>" +
            '<h3 class="empty-title">No Claims Found</h3>' +
            '<p class="empty-message">Try adjusting your filters or search terms</p>' +
            "</div>"
        );
        return;
      }

      let html = "";

      claims.forEach(function (claim, index) {
        html += Dashboard.renderClaimCard(claim, index);
      });

      $grid.html(html);
    },

    /**
     * Render individual claim card
     */
    renderClaimCard: function (claim, index) {
      const claimText =
        claim.claim ||
        claim.claim_text ||
        claim.source_title ||
        "Unknown claim";
      const rating = claim.rating || "Unknown";
      const source = claim.source || claim.author_handle || "Unknown";
      const velocityStatus = claim.velocity_status || "dormant";
      const velocityScore = parseFloat(claim.velocity_score) || 0;
      const sharesPerHour = parseFloat(claim.shares_per_hour) || 0;
      const category = claim.category || "general";
      const date = claim.date || claim.last_seen || claim.posted_at || "";
      const platform = claim.platform || "unknown";
      const url = claim.url || claim.source_url || "#";

      // Velocity badge
      let velocityBadge = "";
      if (velocityStatus === "viral" || velocityScore >= 50) {
        velocityBadge =
          '<span class="badge badge-viral"><svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg> VIRAL</span>';
      } else if (velocityStatus === "emerging" || velocityScore >= 20) {
        velocityBadge =
          '<span class="badge badge-emerging"><svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg> EMERGING</span>';
      } else if (velocityStatus === "active" || velocityScore > 0) {
        velocityBadge =
          '<span class="badge badge-active"><svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"/></svg> ACTIVE</span>';
      }

      // Rating class
      let ratingClass = "rating-unknown";
      if (rating.toLowerCase().includes("false")) ratingClass = "rating-false";
      else if (rating.toLowerCase().includes("true"))
        ratingClass = "rating-true";
      else if (
        rating.toLowerCase().includes("misleading") ||
        rating.toLowerCase().includes("mixed")
      )
        ratingClass = "rating-misleading";

      // Platform icon
      const platformIcons = {
        rss: '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a1 1 0 000 2c5.523 0 10 4.477 10 10a1 1 0 102 0C17 8.373 11.627 3 5 3z"/><path d="M4 9a1 1 0 011-1 7 7 0 017 7 1 1 0 11-2 0 5 5 0 00-5-5 1 1 0 01-1-1zM3 15a2 2 0 114 0 2 2 0 01-4 0z"/></svg>',
        google:
          '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"/></svg>',
        twitter:
          '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M6.29 18.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0020 3.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.073 4.073 0 01.8 7.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 010 16.407a11.616 11.616 0 006.29 1.84"/></svg>',
        internal:
          '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg>',
      };
      const platformIcon = platformIcons[platform] || platformIcons["rss"];

      // Time ago
      const timeAgo = Dashboard.formatTimeAgo(date);

      // Category icon
      const categoryIcons = {
        politics:
          '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z"/><path d="M2 13.692V16a2 2 0 002 2h12a2 2 0 002-2v-2.308A24.974 24.974 0 0110 15c-2.796 0-5.487-.46-8-1.308z"/></svg>',
        health:
          '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/></svg>',
        climate:
          '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z"/></svg>',
        technology:
          '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z"/></svg>',
        general:
          '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/></svg>',
      };
      const categoryIcon = categoryIcons[category] || categoryIcons["general"];

      return (
        '<div class="claim-card" data-claim-id="' +
        index +
        '" data-platform="' +
        platform +
        '" data-category="' +
        category +
        '" data-velocity="' +
        velocityStatus +
        '">' +
        '<div class="claim-card-header">' +
        '<div class="claim-badges">' +
        velocityBadge +
        '<span class="badge badge-category">' +
        categoryIcon +
        " " +
        Dashboard.capitalizeFirst(category) +
        "</span>" +
        "</div>" +
        '<div class="claim-rating ' +
        ratingClass +
        '">' +
        rating +
        "</div>" +
        "</div>" +
        '<div class="claim-text">' +
        Dashboard.escapeHtml(claimText) +
        "</div>" +
        (claim.description
          ? '<div class="claim-description">' +
            Dashboard.escapeHtml(claim.description) +
            "</div>"
          : "") +
        '<div class="claim-metrics">' +
        (sharesPerHour > 0
          ? '<div class="metric-item">' +
            '<svg class="metric-icon" width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"/></svg>' +
            '<span class="metric-value">' +
            Dashboard.formatNumber(sharesPerHour) +
            "</span>" +
            "<span>/hr</span>" +
            "</div>"
          : "") +
        '<div class="metric-item">' +
        platformIcon +
        '<span class="metric-value">' +
        source +
        "</span>" +
        "</div>" +
        '<div class="metric-item">' +
        '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/></svg>' +
        "<span>" +
        timeAgo +
        "</span>" +
        "</div>" +
        "</div>" +
        '<div class="claim-actions">' +
        '<a href="' +
        url +
        '" target="_blank" class="action-btn action-source">' +
        '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>' +
        " View Source" +
        "</a>" +
        '<button class="action-btn action-investigate" data-url="' +
        url +
        '">' +
        '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>' +
        " Investigate" +
        "</button>" +
        "</div>" +
        "</div>"
      );
    },

    /**
     * Update claim count
     */
    updateCount: function (count) {
      $("#claimsCount").text(count);
    },

    /**
     * Show error message
     */
    showError: function (message) {
      $("#claimsGrid").html(
        '<div class="empty-state">' +
          '<div class="empty-icon">⚠️</div>' +
          '<h3 class="empty-title">Error</h3>' +
          '<p class="empty-message">' +
          message +
          "</p>" +
          "</div>"
      );
    },

    /**
     * Start auto-refresh
     */
    startAutoRefresh: function () {
      const self = this;

      this.refreshInterval = setInterval(function () {
        console.log("AI Verify: Auto-refreshing data");
        self.loadStats();
      }, 60000); // Every 60 seconds
    },

    /**
     * Utility: Format time ago
     */
    formatTimeAgo: function (dateString) {
      if (!dateString) return "Recently";

      const date = new Date(dateString);
      const now = new Date();
      const seconds = Math.floor((now - date) / 1000);

      if (seconds < 60) return "Just now";
      if (seconds < 3600) return Math.floor(seconds / 60) + "m ago";
      if (seconds < 86400) return Math.floor(seconds / 3600) + "h ago";
      if (seconds < 604800) return Math.floor(seconds / 86400) + "d ago";

      return date.toLocaleDateString();
    },

    /**
     * Utility: Format number
     */
    formatNumber: function (num) {
      if (num >= 1000000) return (num / 1000000).toFixed(1) + "M";
      if (num >= 1000) return (num / 1000).toFixed(1) + "K";
      return num.toString();
    },

    /**
     * Utility: Capitalize first letter
     */
    capitalizeFirst: function (str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    },

    /**
     * Utility: Escape HTML
     */
    escapeHtml: function (text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },

    /**
     * Load propaganda data
     */
    loadPropagandaData: function () {
      const self = this;

      $.ajax({
        url: aiVerifyDashboard.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_get_propaganda_data",
          nonce: aiVerifyDashboard.nonce,
          timeframe: self.filters.timeframe,
          category: self.filters.category,
        },
        success: function (response) {
          if (response.success) {
            self.renderPropagandaData(response.data);
          }
        },
        error: function () {
          console.error("Failed to load propaganda data");
        },
      });
    },

    /**
     * Render propaganda data
     */
    renderPropagandaData: function (data) {
      // Update stats
      $("#propagandaPercentage").text(data.propaganda_percentage + "%");
      $("#totalPropagandaClaims").text(data.propaganda_claims);
      $("#uniqueTechniques").text(Object.keys(data.top_techniques).length);

      const topTechniqueName = Object.keys(data.top_techniques)[0] || "None";
      $("#mostCommonTechnique").text(topTechniqueName);

      // Render techniques list
      const $techniquesList = $("#propagandaTechniquesList");
      $techniquesList.empty();

      if (Object.keys(data.top_techniques).length === 0) {
        $techniquesList.html(
          '<p class="no-data">No propaganda techniques detected in selected timeframe</p>'
        );
        return;
      }

      let techniquesHtml = '<div class="techniques-grid">';

      for (const [technique, count] of Object.entries(data.top_techniques)) {
        const percentage = ((count / data.propaganda_claims) * 100).toFixed(1);
        const definition =
          data.definitions[technique] || "No definition available";

        techniquesHtml += `
                <div class="technique-card">
                    <div class="technique-header">
                        <h4 class="technique-name">${this.escapeHtml(
                          technique
                        )}</h4>
                        <span class="technique-count">${count}</span>
                    </div>
                    <div class="technique-bar">
                        <div class="technique-bar-fill" style="width: ${percentage}%"></div>
                    </div>
                    <p class="technique-definition">${this.escapeHtml(
                      definition
                    )}</p>
                    <div class="technique-meta">
                        <span class="technique-percentage">${percentage}% of propaganda claims</span>
                    </div>
                </div>
            `;
      }

      techniquesHtml += "</div>";
      $techniquesList.html(techniquesHtml);

      // Render claims with propaganda
      this.renderPropagandaClaims(data.claims_with_propaganda);
    },

    /**
     * Render propaganda claims
     */
    renderPropagandaClaims: function (claims) {
      const $claimsList = $("#propagandaClaimsList");
      $claimsList.empty();

      if (claims.length === 0) {
        return;
      }

      let html =
        '<h3 class="subsection-title">Recent Claims with Propaganda</h3>';
      html += '<div class="propaganda-claims-grid">';

      claims.forEach((claim) => {
        const severityColors = {
          critical: "#ef4444",
          high: "#f59e0b",
          moderate: "#eab308",
          low: "#10b981",
        };

        const techniqueCount = claim.techniques.length;
        const severity =
          techniqueCount >= 5
            ? "critical"
            : techniqueCount >= 3
            ? "high"
            : techniqueCount >= 1
            ? "moderate"
            : "low";
        const severityColor = severityColors[severity];

        html += `
            <div class="propaganda-claim-card">
                <div class="propaganda-claim-header">
                    <span class="propaganda-badge" style="background: ${severityColor}15; color: ${severityColor}; border-color: ${severityColor}">
                        ${techniqueCount} Technique${
          techniqueCount !== 1 ? "s" : ""
        }
                    </span>
                    <span class="claim-category-badge">${this.escapeHtml(
                      claim.category
                    )}</span>
                </div>
                <div class="propaganda-claim-text">${this.escapeHtml(
                  claim.claim
                )}</div>
                <div class="propaganda-techniques-tags">
                    ${claim.techniques
                      .map(
                        (t) =>
                          `<span class="technique-tag">${this.escapeHtml(
                            t
                          )}</span>`
                      )
                      .join("")}
                </div>
                <div class="propaganda-claim-meta">
                    <span class="credibility-score" style="color: ${
                      claim.credibility >= 70
                        ? "#10b981"
                        : claim.credibility >= 40
                        ? "#eab308"
                        : "#ef4444"
                    }">
                        Score: ${claim.credibility}/100
                    </span>
                    <span class="velocity-status">${this.capitalizeFirst(
                      claim.velocity
                    )}</span>
                    <span class="check-count">${claim.checks} checks</span>
                </div>
            </div>
        `;
      });

      html += "</div>";
      $claimsList.html(html);
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    Dashboard.init();
  });
})(jQuery);
