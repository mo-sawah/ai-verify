/**
 * Admin Dashboard JavaScript for Trends
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    initCharts();
  });

  function initCharts() {
    // Category Breakdown Chart
    if (typeof categoryData !== "undefined" && categoryData.length > 0) {
      const categoryChart = new Chart(
        document.getElementById("categoryChart"),
        {
          type: "doughnut",
          data: {
            labels: categoryData.map((item) => capitalizeFirst(item.category)),
            datasets: [
              {
                data: categoryData.map((item) => item.total_checks),
                backgroundColor: [
                  "#93c5fd", // blue
                  "#86efac", // green
                  "#fde047", // yellow
                  "#c4b5fd", // purple
                  "#fda4af", // pink
                  "#fca5a5", // red
                  "#d1d5db", // gray
                ],
                borderWidth: 2,
                borderColor: "#fff",
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
                  font: {
                    size: 12,
                  },
                },
              },
              tooltip: {
                callbacks: {
                  label: function (context) {
                    const label = context.label || "";
                    const value = context.parsed || 0;
                    const total = context.dataset.data.reduce(
                      (a, b) => a + b,
                      0
                    );
                    const percentage = ((value / total) * 100).toFixed(1);
                    return `${label}: ${value} checks (${percentage}%)`;
                  },
                },
              },
            },
          },
        }
      );
    }

    // Credibility Timeline Chart
    loadTimelineChart();
  }

  function loadTimelineChart() {
    $.ajax({
      url: aiVerifyTrends.ajax_url,
      type: "POST",
      data: {
        action: "ai_verify_get_trends_data",
        nonce: aiVerifyTrends.nonce,
        data_type: "timeline",
        timeframe: getUrlParameter("timeframe") || "7days",
      },
      success: function (response) {
        if (response.success && response.data.length > 0) {
          renderTimelineChart(response.data);
        }
      },
    });
  }

  function renderTimelineChart(data) {
    const ctx = document.getElementById("timelineChart");

    new Chart(ctx, {
      type: "line",
      data: {
        labels: data.map((item) => formatDate(item.date)),
        datasets: [
          {
            label: "Average Credibility Score",
            data: data.map((item) => parseFloat(item.avg_score)),
            borderColor: "#acd2bf",
            backgroundColor: "rgba(172, 210, 191, 0.1)",
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: "#acd2bf",
            pointBorderColor: "#fff",
            pointBorderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: "top",
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                return `Credibility: ${context.parsed.y.toFixed(1)}%`;
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: {
              callback: function (value) {
                return value + "%";
              },
            },
            grid: {
              color: "rgba(0, 0, 0, 0.05)",
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
  }

  // Helper functions
  function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  }

  function getUrlParameter(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    const regex = new RegExp("[\\?&]" + name + "=([^&#]*)");
    const results = regex.exec(location.search);
    return results === null
      ? ""
      : decodeURIComponent(results[1].replace(/\+/g, " "));
  }
})(jQuery);
