/**
 * Intelligence Dashboard JavaScript
 * Handles interactivity, real-time updates, and theme detection
 */

(function ($) {
  "use strict";

  const Dashboard = {
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

      // Start auto-refresh (every 60 seconds)
      this.startAutoRefresh();

      // Initialize charts after short delay
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
        self.filters[filterType] = $(this).val();
        self.loadData();
      });

      // Filter chips
      $(".filter-chip").on("click", function () {
        $(".filter-chip").removeClass("active");
        $(this).addClass("active");

        const filterType = $(this).data("filter-type");
        const filterValue = $(this).data("filter-value");

        self.filters[filterType] = filterValue;
        self.loadData();
      });

      // Analytics toggle
      $("#analyticsToggle").on("click", function () {
        $("#analyticsContent").slideToggle(300);
        $(this).find(".toggle-icon").toggleClass("rotated");
      });

      // Refresh button
      $("#refreshButton").on("click", function () {
        $(this).addClass("spinning");
        self.loadData();
        self.loadStats();

        setTimeout(function () {
          $("#refreshButton").removeClass("spinning");
        }, 1000);
      });

      // Claim action buttons
      $(document).on("click", ".action-investigate", function () {
        const claimId = $(this).closest(".claim-card").data("claim-id");
        self.openInvestigationModal(claimId);
      });

      $(document).on("click", ".action-analytics", function () {
        const claimId = $(this).closest(".claim-card").data("claim-id");
        self.showClaimAnalytics(claimId);
      });
    },

    /**
     * Load dashboard data
     */
    loadData: function () {
      const self = this;

      $("#claimsGrid").html(
        '<div class="loading-spinner"><div class="spinner"></div><p class="loading-text">Loading claims...</p></div>'
      );

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
        },
        success: function (response) {
          if (response.success) {
            self.renderClaims(response.data.claims);
            self.updateCount(response.data.total);
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
        $grid.html(`
                    <div class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <h3 class="empty-title">No Claims Found</h3>
                        <p class="empty-message">Try adjusting your filters or search terms</p>
                    </div>
                `);
        return;
      }

      let html = "";

      claims.forEach((claim, index) => {
        html += this.renderClaimCard(claim, index);
      });

      $grid.html(html);
    },

    /**
     * Render individual claim card
     */
    renderClaimCard: function (claim, index) {
      const claimText = claim.claim || claim.claim_text || "Unknown claim";
      const rating = claim.rating || "Unknown";
      const source = claim.source || "Unknown";
      const velocityStatus = claim.velocity_status || "dormant";
      const velocityScore = claim.velocity_score || 0;
      const sharesPerHour = claim.shares_per_hour || 0;
      const category = claim.category || "general";
      const date = claim.date || claim.last_seen || "";

      // Velocity badge
      let velocityBadge = "";
      if (velocityStatus === "viral") {
        velocityBadge = '<span class="badge badge-viral">🔥 VIRAL</span>';
      } else if (velocityStatus === "emerging") {
        velocityBadge = '<span class="badge badge-emerging">⚡ EMERGING</span>';
      } else if (velocityStatus === "active") {
        velocityBadge = '<span class="badge badge-active">📈 ACTIVE</span>';
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

      // Time ago
      const timeAgo = this.formatTimeAgo(date);

      return `
                <div class="claim-card" data-claim-id="${index}">
                    <div class="claim-card-header">
                        <div class="claim-badges">
                            ${velocityBadge}
                            <span class="badge badge-category">📊 ${this.capitalizeFirst(
                              category
                            )}</span>
                        </div>
                        <div class="claim-rating ${ratingClass}">
                            ${rating}
                        </div>
                    </div>
                    
                    <div class="claim-text">${this.escapeHtml(claimText)}</div>
                    
                    ${
                      claim.description
                        ? `<div class="claim-description">${this.escapeHtml(
                            claim.description
                          )}</div>`
                        : ""
                    }
                    
                    <div class="claim-metrics">
                        ${
                          sharesPerHour > 0
                            ? `
                        <div class="metric-item">
                            <span class="metric-icon">📈</span>
                            <span class="metric-value">${this.formatNumber(
                              sharesPerHour
                            )}</span>
                            <span>shares/hr</span>
                        </div>`
                            : ""
                        }
                        
                        <div class="metric-item">
                            <span class="metric-icon">🌐</span>
                            <span class="metric-value">${source}</span>
                        </div>
                        
                        <div class="metric-item">
                            <span class="metric-icon">⏱️</span>
                            <span>${timeAgo}</span>
                        </div>
                    </div>
                    
                    ${
                      claim.platform_breakdown
                        ? this.renderPlatformBreakdown(claim.platform_breakdown)
                        : ""
                    }
                    
                    <div class="claim-actions">
                        <button class="action-btn action-investigate">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Investigate
                        </button>
                        <button class="action-btn action-analytics">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            Analytics
                        </button>
                    </div>
                </div>
            `;
    },

    /**
     * Render platform breakdown
     */
    renderPlatformBreakdown: function (platforms) {
      if (typeof platforms === "string") {
        platforms = JSON.parse(platforms);
      }

      if (!platforms || Object.keys(platforms).length === 0) {
        return "";
      }

      let html = '<div class="platform-breakdown">';

      for (const [platform, percentage] of Object.entries(platforms)) {
        html += `<span class="platform-tag">${platform}: ${percentage}%</span>`;
      }

      html += "</div>";
      return html;
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
      $("#claimsGrid").html(`
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <h3 class="empty-title">Error</h3>
                    <p class="empty-message">${message}</p>
                </div>
            `);
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
     * Open investigation modal (placeholder)
     */
    openInvestigationModal: function (claimId) {
      console.log("Opening investigation for claim:", claimId);
      alert("Investigation modal coming soon!");
    },

    /**
     * Show claim analytics (placeholder)
     */
    showClaimAnalytics: function (claimId) {
      console.log("Showing analytics for claim:", claimId);
      alert("Analytics view coming soon!");
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
  };

  // Initialize on document ready
  $(document).ready(function () {
    Dashboard.init();
  });
})(jQuery);
