/**
 * Deepfake Detector JavaScript
 * Handles file uploads, API calls, and UI updates
 */

(function ($) {
  "use strict";

  const DeepfakeDetector = {
    selectedFile: null,
    processingStartTime: null,

    init: function () {
      this.setupEventListeners();
      this.loadHistory();
    },

    setupEventListeners: function () {
      const self = this;

      // Tab switching
      $(".upload-tab").on("click", function () {
        const tab = $(this).data("tab");
        $(".upload-tab").removeClass("active");
        $(this).addClass("active");
        $(".upload-content").removeClass("active");
        $("#upload-" + tab).addClass("active");
      });

      // File upload area - FIXED: Use native DOM click, not jQuery
      $("#uploadArea").on("click", function (e) {
        // Don't trigger if clicking on the input itself
        if (e.target.id === "mediaFile") {
          return;
        }
        e.preventDefault();
        e.stopPropagation();
        // Use native DOM click instead of jQuery
        document.getElementById("mediaFile").click();
      });

      // File selection - FIXED: Stop propagation immediately
      $("#mediaFile")
        .on("change", function (e) {
          e.stopPropagation();
          if (e.target.files.length > 0) {
            self.handleFileSelect(e.target.files[0]);
          }
        })
        .on("click", function (e) {
          // Stop click from bubbling to parent
          e.stopPropagation();
        });

      // Drag and drop - FIXED
      $("#uploadArea")
        .on("dragover", function (e) {
          e.preventDefault();
          e.stopPropagation();
          $(this).addClass("drag-over");
        })
        .on("dragleave", function (e) {
          e.preventDefault();
          e.stopPropagation();
          $(this).removeClass("drag-over");
        })
        .on("drop", function (e) {
          e.preventDefault();
          e.stopPropagation();
          $(this).removeClass("drag-over");

          const files = e.originalEvent.dataTransfer.files;
          if (files.length > 0) {
            self.handleFileSelect(files[0]);
          }
        });

      // Remove file
      $("#removeFile").on("click", function (e) {
        e.stopPropagation();
        self.clearFile();
      });

      // Detect buttons
      $("#detectFileBtn").on("click", function () {
        self.detectFromFile();
      });

      $("#detectUrlBtn").on("click", function () {
        self.detectFromUrl();
      });

      // New scan button
      $("#newScanBtn").on("click", function () {
        self.resetInterface();
      });

      // Technical details toggle
      $("#detailsToggle").on("click", function () {
        $(this).toggleClass("active");
        $("#detailsContent").slideToggle(300);
      });

      // Refresh history
      $("#refreshHistory").on("click", function () {
        self.loadHistory();
      });
    },

    handleFileSelect: function (file) {
      // Validate file type
      if (!aiVerifyDeepfake.allowed_types.includes(file.type)) {
        alert(
          "Invalid file type. Please upload an image (JPG, PNG, WebP) or audio file (MP3, WAV, OGG)."
        );
        return;
      }

      // Validate file size
      if (file.size > aiVerifyDeepfake.max_file_size) {
        alert("File size exceeds 10MB limit. Please choose a smaller file.");
        return;
      }

      this.selectedFile = file;

      // Show preview
      $(".upload-placeholder").hide();
      $("#uploadPreview").show();
      $("#previewFilename").text(file.name);
      $("#previewFilesize").text(this.formatFileSize(file.size));

      // Show media preview
      if (file.type.startsWith("image/")) {
        const reader = new FileReader();
        reader.onload = function (e) {
          $("#previewImage").attr("src", e.target.result).show();
          $("#previewAudio").hide();
        };
        reader.readAsDataURL(file);
      } else if (file.type.startsWith("audio/")) {
        const reader = new FileReader();
        reader.onload = function (e) {
          $("#previewAudio").attr("src", e.target.result).show();
          $("#previewImage").hide();
        };
        reader.readAsDataURL(file);
      }

      // Enable detect button
      $("#detectFileBtn").prop("disabled", false);
    },

    clearFile: function () {
      this.selectedFile = null;
      $("#mediaFile").val("");
      $("#uploadPreview").hide();
      $(".upload-placeholder").show();
      $("#previewImage").hide().attr("src", "");
      $("#previewAudio").hide().attr("src", "");
      $("#detectFileBtn").prop("disabled", true);
    },

    detectFromFile: function () {
      if (!this.selectedFile) {
        alert("Please select a file first");
        return;
      }

      const formData = new FormData();
      formData.append("action", "ai_verify_detect_deepfake");
      formData.append("nonce", aiVerifyDeepfake.nonce);
      formData.append("input_type", "file");
      formData.append("media_file", this.selectedFile);

      this.startDetection(formData);
    },

    detectFromUrl: function () {
      const url = $("#mediaUrl").val().trim();

      if (!url) {
        alert("Please enter a URL");
        return;
      }

      if (!this.isValidUrl(url)) {
        alert("Please enter a valid URL");
        return;
      }

      const formData = new FormData();
      formData.append("action", "ai_verify_detect_deepfake");
      formData.append("nonce", aiVerifyDeepfake.nonce);
      formData.append("input_type", "url");
      formData.append("media_url", url);

      this.startDetection(formData);
    },

    startDetection: function (formData) {
      const self = this;
      self.processingStartTime = Date.now();

      // Update UI
      const $btn =
        formData.get("input_type") === "file"
          ? $("#detectFileBtn")
          : $("#detectUrlBtn");
      $btn.addClass("loading").prop("disabled", true);

      // Hide upload section, show loading
      $(".upload-section").fadeOut(300);
      $("#resultsSection").fadeIn(300);

      // Show initial loading state
      this.showLoadingState();

      // Make AJAX request
      $.ajax({
        url: aiVerifyDeepfake.ajax_url,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        timeout: 120000, // 2 minutes
        success: function (response) {
          $btn.removeClass("loading").prop("disabled", false);

          if (response.success) {
            self.displayResults(response.data);
            self.loadHistory(); // Refresh history
          } else {
            alert(
              "Detection failed: " + (response.data.message || "Unknown error")
            );
            self.resetInterface();
          }
        },
        error: function (xhr, status, error) {
          $btn.removeClass("loading").prop("disabled", false);
          alert("Connection error. Please try again.");
          self.resetInterface();
        },
      });
    },

    showLoadingState: function () {
      $("#scoreNumber").text("0");
      $("#verdictText").text("Analyzing media...");
      $("#confidenceText").text("Please wait while we analyze the content");
      $("#verdictBadge").removeClass("deepfake authentic");
    },

    displayResults: function (data) {
      const self = this;
      const processingTime = (
        (Date.now() - this.processingStartTime) /
        1000
      ).toFixed(1);

      // Animate score
      this.animateScore(data.detection_score);

      // Update verdict
      const $verdictBadge = $("#verdictBadge");
      if (data.is_deepfake) {
        $verdictBadge.addClass("deepfake").removeClass("authentic");
      } else {
        $verdictBadge.addClass("authentic").removeClass("deepfake");
      }

      $("#verdictText").text(data.verdict);
      $("#confidenceText").text(self.getConfidenceText(data.confidence_level));

      // Update metrics
      $("#mediaType").text(data.media_type === "image" ? "Image" : "Audio");
      $("#confidenceLevel").text(
        self.formatConfidenceLevel(data.confidence_level)
      );

      // Update recommendations
      this.displayRecommendations(data.recommendations);

      // Update analysis details
      this.displayAnalysis(data.analysis);

      // Update technical details
      $("#detectionId").text(data.detection_id);
      $("#timestamp").text(new Date(data.timestamp).toLocaleString());
      $("#processingTime").text(processingTime + "s");
    },

    animateScore: function (targetScore) {
      const circumference = 2 * Math.PI * 85;
      const offset = circumference - (targetScore / 100) * circumference;

      $("#scoreRing").css({
        "stroke-dasharray": circumference,
        "stroke-dashoffset": offset,
        transition: "stroke-dashoffset 1.5s ease",
      });

      // Change color based on score
      let color = "#10b981"; // Green (authentic)
      if (targetScore >= 60) {
        color = "#ef4444"; // Red (deepfake)
      } else if (targetScore >= 40) {
        color = "#f59e0b"; // Orange (uncertain)
      }

      $("#scoreRing").css("stroke", color);

      // Animate number
      $({ value: 0 }).animate(
        { value: targetScore },
        {
          duration: 1500,
          easing: "swing",
          step: function () {
            $("#scoreNumber").text(Math.round(this.value));
          },
        }
      );
    },

    displayRecommendations: function (recommendations) {
      const $list = $("#recommendationsList").empty();

      if (!recommendations || recommendations.length === 0) {
        $list.append("<li>No specific recommendations</li>");
        return;
      }

      recommendations.forEach(function (rec) {
        $list.append("<li>" + self.escapeHtml(rec) + "</li>");
      });
    },

    displayAnalysis: function (analysis) {
      // Models Used
      const $models = $("#modelsUsed").empty();
      if (analysis.models_used && analysis.models_used.length > 0) {
        analysis.models_used.forEach(function (model) {
          $models.append("<li>" + self.escapeHtml(model) + "</li>");
        });
      } else {
        $models.append("<li>Multi-model ensemble detection</li>");
      }

      // Manipulation Types
      const $manipulation = $("#manipulationTypes").empty();
      if (
        analysis.manipulation_types &&
        analysis.manipulation_types.length > 0
      ) {
        analysis.manipulation_types.forEach(function (type) {
          $manipulation.append("<li>" + self.escapeHtml(type) + "</li>");
        });
      } else {
        $manipulation.append("<li>No manipulation detected</li>");
      }

      // Regions Analyzed
      const $regions = $("#regionsAnalyzed").empty();
      if (analysis.regions_analyzed && analysis.regions_analyzed.length > 0) {
        analysis.regions_analyzed.forEach(function (region) {
          $regions.append("<li>" + self.escapeHtml(region) + "</li>");
        });
      } else {
        $regions.append("<li>Full content analysis</li>");
      }

      // Artifacts Detected
      const $artifacts = $("#artifactsDetected").empty();
      if (
        analysis.artifacts_detected &&
        analysis.artifacts_detected.length > 0
      ) {
        analysis.artifacts_detected.forEach(function (artifact) {
          $artifacts.append("<li>" + self.escapeHtml(artifact) + "</li>");
        });
      } else {
        $artifacts.append("<li>No suspicious artifacts found</li>");
      }
    },

    loadHistory: function () {
      const self = this;

      $.ajax({
        url: aiVerifyDeepfake.ajax_url,
        type: "POST",
        data: {
          action: "ai_verify_get_detection_history",
          nonce: aiVerifyDeepfake.nonce,
          limit: 10,
        },
        success: function (response) {
          if (response.success) {
            self.displayHistory(response.data.history);
          }
        },
      });
    },

    displayHistory: function (history) {
      const $list = $("#historyList");
      $list.empty();

      if (!history || history.length === 0) {
        $list.html(
          '<div class="history-loading"><p>No detection history yet</p></div>'
        );
        return;
      }

      history.forEach(function (item) {
        const detectionClass = item.is_deepfake == 1 ? "deepfake" : "authentic";
        const scoreClass = item.is_deepfake == 1 ? "deepfake" : "authentic";

        const html = `
          <div class="history-item ${detectionClass}">
            <div class="history-item-header">
              <div>
                <div class="history-filename">${self.escapeHtml(
                  item.file_name
                )}</div>
                <div class="history-date">${self.formatDate(
                  item.detected_at
                )}</div>
              </div>
              <div class="history-score ${scoreClass}">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                </svg>
                ${Math.round(item.detection_score)}%
              </div>
            </div>
            <div class="history-verdict">
              ${self.getVerdictFromScore(
                item.detection_score,
                item.is_deepfake == 1
              )}
            </div>
          </div>
        `;

        $list.append(html);
      });
    },

    resetInterface: function () {
      this.clearFile();
      $("#mediaUrl").val("");
      $(".upload-section").fadeIn(300);
      $("#resultsSection").fadeOut(300);
      $("#detailsContent").hide();
      $("#detailsToggle").removeClass("active");
    },

    // Utility functions
    formatFileSize: function (bytes) {
      if (bytes === 0) return "0 Bytes";
      const k = 1024;
      const sizes = ["Bytes", "KB", "MB"];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
    },

    formatConfidenceLevel: function (level) {
      const levels = {
        very_high: "Very High",
        high: "High",
        medium: "Medium",
        low: "Low",
        very_low: "Very Low",
      };
      return levels[level] || level;
    },

    getConfidenceText: function (level) {
      const texts = {
        very_high: "Extremely confident in this assessment",
        high: "High confidence in detection results",
        medium: "Moderate confidence - verify if critical",
        low: "Low confidence - manual review recommended",
        very_low: "Very low confidence - inconclusive results",
      };
      return texts[level] || "Unknown confidence level";
    },

    getVerdictFromScore: function (score, isDeepfake) {
      if (isDeepfake) {
        if (score >= 80) return "Highly Likely Deepfake";
        if (score >= 60) return "Likely Deepfake";
        return "Possibly Manipulated";
      } else {
        if (score <= 20) return "Highly Likely Authentic";
        if (score <= 40) return "Likely Authentic";
        return "Possibly Authentic";
      }
    },

    formatDate: function (dateString) {
      const date = new Date(dateString);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);

      if (diffMins < 1) return "Just now";
      if (diffMins < 60) return diffMins + " minutes ago";

      const diffHours = Math.floor(diffMins / 60);
      if (diffHours < 24) return diffHours + " hours ago";

      const diffDays = Math.floor(diffHours / 24);
      if (diffDays < 7) return diffDays + " days ago";

      return date.toLocaleDateString();
    },

    isValidUrl: function (string) {
      try {
        new URL(string);
        return true;
      } catch (_) {
        return false;
      }
    },

    escapeHtml: function (text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    DeepfakeDetector.init();
  });
})(jQuery);
