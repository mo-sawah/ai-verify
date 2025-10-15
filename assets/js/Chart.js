/**
 * Dashboard Charts using Chart.js with REAL DATA
 */

(function ($) {
  "use strict";

  const DashboardCharts = {
    charts: {},
    theme: {},

    init: function () {
      console.log("AI Verify: Initializing charts");

      const isDark = $("body").hasClass("s-dark");
      this.setTheme(isDark);

      // Wait for data to be loaded
      $(document).on("charts:dataLoaded", (e, data) => {
        this.initWithRealData(data);
      });
    },

    setTheme: function (isDark) {
      this.theme = {
        textColor: isDark ? "#cbd5e1" : "#475569",
        gridColor: isDark ? "#334155" : "#e2e8f0",
        backgroundColor: isDark ? "#1e293b" : "#ffffff",
      };

      if (typeof Chart !== "undefined") {
        Chart.defaults.color = this.theme.textColor;
        Chart.defaults.borderColor = this.theme.gridColor;
      }
    },

    initWithRealData: function (data) {
      if (!data) {
        console.error("No chart data provided");
        return;
      }

      this.initTimelineChart(data.timeline || []);
      this.initCategoryChart(data.categories || []);
      this.initVelocityChart(data.velocity || []);
      this.initPlatformChart(data.platforms || []);
    },

    initTimelineChart: function (timelineData) {
      const ctx = document.getElementById("timelineChart");
      if (!ctx) return;

      const labels = timelineData.map((item) => {
        const date = new Date(item.date);
        return date.toLocaleDateString("en-US", {
          month: "short",
          day: "numeric",
        });
      });

      const counts = timelineData.map((item) => parseInt(item.count));

      this.charts.timeline = new Chart(ctx, {
        type: "line",
        data: {
          labels: labels.length ? labels : ["No data"],
          datasets: [
            {
              label: "Claims",
              data: counts.length ? counts : [0],
              borderColor: "#3b82f6",
              backgroundColor: "rgba(59, 130, 246, 0.1)",
              tension: 0.4,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              mode: "index",
              intersect: false,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: this.theme.gridColor },
            },
            x: {
              grid: { display: false },
            },
          },
        },
      });
    },

    initCategoryChart: function (categoryData) {
      const ctx = document.getElementById("categoryChart");
      if (!ctx) return;

      const labels = categoryData.map((item) => item.category || "Unknown");
      const counts = categoryData.map((item) => parseInt(item.count));

      this.charts.category = new Chart(ctx, {
        type: "doughnut",
        data: {
          labels: labels.length ? labels : ["No data"],
          datasets: [
            {
              data: counts.length ? counts : [1],
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
        },
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

    initVelocityChart: function (velocityData) {
      const ctx = document.getElementById("velocityChart");
      if (!ctx) return;

      const labels = velocityData.map((item) =>
        item.claim_text ? item.claim_text.substring(0, 30) + "..." : "Unknown"
      );
      const scores = velocityData.map(
        (item) => parseFloat(item.velocity_score) || 0
      );

      this.charts.velocity = new Chart(ctx, {
        type: "bar",
        data: {
          labels: labels.length ? labels : ["No data"],
          datasets: [
            {
              label: "Velocity Score",
              data: scores.length ? scores : [0],
              backgroundColor: scores.map((score) => {
                if (score >= 50) return "#ef4444";
                if (score >= 20) return "#f59e0b";
                return "#3b82f6";
              }),
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: "y",
          plugins: { legend: { display: false } },
          scales: {
            x: {
              beginAtZero: true,
              max: 100,
              grid: { color: this.theme.gridColor },
            },
            y: { grid: { display: false } },
          },
        },
      });
    },

    initPlatformChart: function (platformData) {
      const ctx = document.getElementById("platformChart");
      if (!ctx) return;

      const labels = platformData.map((item) => item.platform || "Unknown");
      const counts = platformData.map((item) => parseInt(item.count));

      this.charts.platform = new Chart(ctx, {
        type: "bar",
        data: {
          labels: labels.length ? labels : ["No data"],
          datasets: [
            {
              label: "Claims",
              data: counts.length ? counts : [0],
              backgroundColor: "#3b82f6",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: this.theme.gridColor },
            },
            x: { grid: { display: false } },
          },
        },
      });
    },
  };

  window.DashboardCharts = DashboardCharts;
})(jQuery);
