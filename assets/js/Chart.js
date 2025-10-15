/**
 * Dashboard Charts using Chart.js
 */

(function ($) {
  "use strict";

  const DashboardCharts = {
    charts: {},

    /**
     * Initialize all charts
     */
    init: function () {
      console.log("AI Verify: Initializing charts");

      // Detect theme for chart colors
      const isDark = $("body").hasClass("s-dark");
      this.setTheme(isDark);

      // Initialize each chart
      this.initTimelineChart();
      this.initCategoryChart();
      this.initVelocityChart();
      this.initPlatformChart();
    },

    /**
     * Set chart theme
     */
    setTheme: function (isDark) {
      this.theme = {
        textColor: isDark ? "#cbd5e1" : "#475569",
        gridColor: isDark ? "#334155" : "#e2e8f0",
        backgroundColor: isDark ? "#1e293b" : "#ffffff",
      };

      // Set Chart.js defaults
      if (typeof Chart !== "undefined") {
        Chart.defaults.color = this.theme.textColor;
        Chart.defaults.borderColor = this.theme.gridColor;
      }
    },

    /**
     * Timeline Chart - Claims over time
     */
    initTimelineChart: function () {
      const ctx = document.getElementById("timelineChart");
      if (!ctx) return;

      // Sample data - replace with AJAX call
      const data = {
        labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
        datasets: [
          {
            label: "Claims",
            data: [12, 19, 15, 25, 22, 30, 28],
            borderColor: "#3b82f6",
            backgroundColor: "rgba(59, 130, 246, 0.1)",
            tension: 0.4,
            fill: true,
          },
        ],
      };

      this.charts.timeline = new Chart(ctx, {
        type: "line",
        data: data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false,
            },
            tooltip: {
              mode: "index",
              intersect: false,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: this.theme.gridColor,
              },
            },
            x: {
              grid: {
                display: false,
              },
            },
          },
        },
      });
    },

    /**
     * Category Breakdown - Pie chart
     */
    initCategoryChart: function () {
      const ctx = document.getElementById("categoryChart");
      if (!ctx) return;

      const data = {
        labels: [
          "Politics",
          "Health",
          "Climate",
          "Technology",
          "Crime",
          "Other",
        ],
        datasets: [
          {
            data: [35, 25, 15, 12, 8, 5],
            backgroundColor: [
              "#3b82f6",
              "#10b981",
              "#f59e0b",
              "#8b5cf6",
              "#ef4444",
              "#6b7280",
            ],
          },
        ],
      };

      this.charts.category = new Chart(ctx, {
        type: "doughnut",
        data: data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "bottom",
              labels: {
                padding: 15,
                color: this.theme.textColor,
              },
            },
          },
        },
      });
    },

    /**
     * Velocity Chart - Top viral claims
     */
    initVelocityChart: function () {
      const ctx = document.getElementById("velocityChart");
      if (!ctx) return;

      const data = {
        labels: ["Claim 1", "Claim 2", "Claim 3", "Claim 4", "Claim 5"],
        datasets: [
          {
            label: "Velocity Score",
            data: [85, 72, 68, 55, 48],
            backgroundColor: [
              "#ef4444",
              "#f59e0b",
              "#f59e0b",
              "#3b82f6",
              "#3b82f6",
            ],
          },
        ],
      };

      this.charts.velocity = new Chart(ctx, {
        type: "bar",
        data: data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: "y",
          plugins: {
            legend: {
              display: false,
            },
          },
          scales: {
            x: {
              beginAtZero: true,
              max: 100,
              grid: {
                color: this.theme.gridColor,
              },
            },
            y: {
              grid: {
                display: false,
              },
            },
          },
        },
      });
    },

    /**
     * Platform Distribution - Bar chart
     */
    initPlatformChart: function () {
      const ctx = document.getElementById("platformChart");
      if (!ctx) return;

      const data = {
        labels: ["Twitter", "Facebook", "RSS", "Google", "TikTok"],
        datasets: [
          {
            label: "Claims",
            data: [45, 30, 15, 7, 3],
            backgroundColor: "#3b82f6",
          },
        ],
      };

      this.charts.platform = new Chart(ctx, {
        type: "bar",
        data: data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: this.theme.gridColor,
              },
            },
            x: {
              grid: {
                display: false,
              },
            },
          },
        },
      });
    },

    /**
     * Update chart data (for real-time updates)
     */
    updateChart: function (chartName, newData) {
      if (this.charts[chartName]) {
        this.charts[chartName].data = newData;
        this.charts[chartName].update();
      }
    },
  };

  // Expose globally
  window.DashboardCharts = DashboardCharts;
})(jQuery);
