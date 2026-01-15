(function ($) {
  "use strict";

  const api = {
    request: function (endpoint, method, data) {
      return $.ajax({
        url: shadowOrmAdmin.restUrl + endpoint,
        method: method,
        contentType: "application/json",
        data: data ? JSON.stringify(data) : undefined,
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", shadowOrmAdmin.nonce);
        },
      });
    },

    getSettings: function () {
      return this.request("settings", "GET");
    },

    saveSettings: function (data) {
      return this.request("settings", "POST", data);
    },

    getStatus: function () {
      return this.request("status", "GET");
    },

    sync: function (postType) {
      return this.request("sync", "POST", { post_type: postType });
    },

    rollback: function (postType) {
      return this.request("rollback", "POST", { post_type: postType });
    },
  };

  function updatePostTypes() {
    const enabled = [];
    $(".shadow-orm-type-toggle:checked").each(function () {
      enabled.push($(this).data("post-type"));
    });
    return enabled;
  }

  function showProgress(text) {
    $("#shadow-orm-progress").show();
    $(".progress-text").text(text);
  }

  function hideProgress() {
    $("#shadow-orm-progress").hide();
    $(".progress-fill").css("width", "0%");
  }

  function updateProgress(percent, text) {
    $(".progress-fill").css("width", percent + "%");
    $(".progress-text").text(text);
  }

  function refreshStatus() {
    api.getStatus().done(function (response) {
      Object.keys(response.post_types).forEach(function (postType) {
        const status = response.post_types[postType];
        const $row = $('tr[data-post-type="' + postType + '"]');
        const $statusCell = $row.find(".status-cell");

        if (status.exists) {
          $statusCell.html(
            '<span class="status-migrated">' +
              status.migrated +
              " / " +
              status.total +
              "</span>" +
              '<span class="status-size">(' +
              formatBytes(status.size) +
              ")</span>"
          );

          if (!$row.find(".shadow-orm-rollback").length) {
            $row
              .find(".actions-cell")
              .append(
                '<button type="button" class="button shadow-orm-rollback" data-post-type="' +
                  postType +
                  '">Rollback</button>'
              );
          }
        } else {
          $statusCell.html(
            '<span class="status-pending">Nie zmigrowane</span>'
          );
          $row.find(".shadow-orm-rollback").remove();
        }
      });
    });
  }

  function formatBytes(bytes) {
    if (bytes === 0) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  $(document).ready(function () {
    $("#shadow-orm-enabled").on("change", function () {
      api.saveSettings({ enabled: $(this).is(":checked") });
    });

    $(".shadow-orm-type-toggle").on("change", function () {
      api.saveSettings({ post_types: updatePostTypes() });
    });

    $("#shadow-orm-save-settings").on("click", function () {
      const $btn = $(this);
      const $status = $("#shadow-orm-save-status");
      
      $btn.prop("disabled", true);
      $status.text("Zapisywanie...");
      
      api.saveSettings({
        enabled: $("#shadow-orm-enabled").is(":checked"),
        post_types: updatePostTypes()
      }).done(function () {
        $status.text("Zapisano!").css("color", "green");
        setTimeout(function () { $status.text(""); }, 2000);
      }).fail(function () {
        $status.text("Błąd zapisu").css("color", "red");
      }).always(function () {
        $btn.prop("disabled", false);
      });
    });

    $(document).on("click", ".shadow-orm-sync", function () {
      const $btn = $(this);
      const postType = $btn.data("post-type");

      $btn.addClass("syncing").prop("disabled", true);
      showProgress("Przygotowanie migracji...");

      // Start batch migration
      api
        .request("sync/start", "POST", { post_type: postType })
        .done(function (response) {
          const state = response.state;
          updateProgress(0, "Migracja: 0 / " + state.total);

          // Process batches
          function processBatch() {
            api
              .request("sync/batch", "POST", { post_type: postType })
              .done(function (batchResponse) {
                const s = batchResponse.state;
                const percent = Math.round((s.migrated / s.total) * 100);
                updateProgress(percent, "Migracja: " + s.migrated + " / " + s.total);

                if (s.status === "running") {
                  setTimeout(processBatch, 100);
                } else {
                  updateProgress(100, "Zakończono! " + s.migrated + " rekordów");
                  setTimeout(function () {
                    hideProgress();
                    refreshStatus();
                  }, 1500);
                  $btn.removeClass("syncing").prop("disabled", false);
                }
              })
              .fail(function () {
                updateProgress(0, "Błąd migracji");
                $btn.removeClass("syncing").prop("disabled", false);
              });
          }

          processBatch();
        })
        .fail(function () {
          updateProgress(0, "Błąd startu migracji");
          $btn.removeClass("syncing").prop("disabled", false);
        });
    });

    $(document).on("click", ".shadow-orm-rollback", function () {
      const $btn = $(this);
      const postType = $btn.data("post-type");

      if (!confirm(shadowOrmAdmin.i18n.confirm_rollback)) {
        return;
      }

      $btn.addClass("rolling-back").prop("disabled", true);

      api
        .rollback(postType)
        .done(function () {
          refreshStatus();
        })
        .always(function () {
          $btn.removeClass("rolling-back").prop("disabled", false);
        });
    });
  });
})(jQuery);
