(function () {
  "use strict";

  function getMetaContent(name) {
    var meta = document.querySelector('meta[name="' + name + '"]');
    return meta ? meta.getAttribute("content") || "" : "";
  }

  function buildConfig() {
    var emailPattern =
      getMetaContent("staradmin-email-regex") ||
      "^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$";
    var pwdPattern =
      getMetaContent("staradmin-pwd-regex") ||
      "^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[\\W_]).{8,}$";

    try {
      window.StarAdminConfig = {
        emailRegex: new RegExp(emailPattern),
        pwdRegex: new RegExp(pwdPattern),
      };
    } catch (e) {
      window.StarAdminConfig = {
        emailRegex: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        pwdRegex: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/,
      };
    }
  }

  function showNoChangeWarning(message) {
    var text = message || "无需保存";
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "warning",
        title: "没有修改",
        text: text,
        timer: 2000,
        showConfirmButton: false,
        showCloseButton: true,
      });
    } else {
      alert("没有修改，" + text);
    }
  }

  function initCheckChangesForms() {
    document.querySelectorAll("form.check-changes").forEach(function (form) {
      form.dataset.originalData = new URLSearchParams(
        new FormData(form),
      ).toString();

      form.addEventListener("submit", function (e) {
        var fileInputs = form.querySelectorAll('input[type="file"]');
        var hasFile = false;
        fileInputs.forEach(function (input) {
          if (input.files.length > 0) hasFile = true;
        });

        var currentData = new URLSearchParams(new FormData(form)).toString();

        if (!hasFile && form.dataset.originalData === currentData) {
          e.preventDefault();
          showNoChangeWarning("无需保存");
        }
      });
    });
  }

  function initBreadcrumbConsolidation() {
    var breadcrumbs = Array.from(
      document.querySelectorAll(".page-action-breadcrumb"),
    );
    if (breadcrumbs.length === 0) return;

    var globalSourceBreadcrumb = document.querySelector(
      "#globalBreadcrumbSource .page-action-breadcrumb",
    );

    var preferredBreadcrumb =
      globalSourceBreadcrumb ||
      breadcrumbs.find(function (el) {
        return el.closest(".global-breadcrumb-inline");
      }) ||
      breadcrumbs.find(function (el) {
        return !el.closest(".card-header");
      }) ||
      breadcrumbs[0];

    var mountParent =
      document.querySelector(".app-page-shell") ||
      document.querySelector(".container.main-content") ||
      document.querySelector("main.audit-container") ||
      document.querySelector(".dashboard-main > .container-fluid") ||
      document.querySelector(".dashboard-main > .container") ||
      document.querySelector(".dashboard-main") ||
      document.querySelector(".dashboard-container") ||
      preferredBreadcrumb.closest(".container-fluid, .container") ||
      preferredBreadcrumb.parentElement;

    if (mountParent) {
      var inlineHost = mountParent.querySelector(
        ":scope > .global-breadcrumb-inline",
      );
      if (!inlineHost) {
        inlineHost = document.createElement("div");
        inlineHost.className = "global-breadcrumb-inline";
        mountParent.insertBefore(inlineHost, mountParent.firstElementChild);
      }

      inlineHost.innerHTML = "";
      inlineHost.appendChild(preferredBreadcrumb);
      preferredBreadcrumb.classList.add("global-page-breadcrumb");
    }

    breadcrumbs.forEach(function (el) {
      if (el !== preferredBreadcrumb) {
        el.remove();
      }
    });

    var sourceContainer = document.getElementById("globalBreadcrumbSource");
    if (sourceContainer && sourceContainer.children.length === 0) {
      sourceContainer.remove();
    }
  }

  function initLogoutHandler() {
    var logoutBtn = document.querySelector(".logout-btn");
    if (!logoutBtn) return;

    logoutBtn.addEventListener("click", function (e) {
      e.preventDefault();

      var logoutUrl = this.getAttribute("href");
      var apiUrl = this.getAttribute("data-api-url");
      var pageName = document.title;

      if (typeof Swal === "undefined") {
        window.location.href = logoutUrl;
        return;
      }

      Swal.fire({
        title: "确定要退出吗?",
        text: "您将退出当前会话",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d9534f",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "是的, 退出",
        cancelButtonText: "取消",
      }).then(function (result) {
        if (result.isConfirmed) {
          window.location.href = logoutUrl;
          return;
        }

        if (apiUrl) {
          fetch(apiUrl, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              action: "Logout Cancelled",
              message: "User clicked logout but cancelled the prompt",
              page: pageName,
            }),
          }).catch(function () {});
        }
      });
    });
  }

  window.GlobalFormValidation = {
    normalizeLabelText: function (value) {
      return (value || "")
        .replace(/[*：:]/g, " ")
        .replace(/\s+/g, " ")
        .trim();
    },

    humanizeFieldName: function (value) {
      var text = (value || "").trim();
      if (!text) return "此字段";

      text = text
        .replace(/\[\]$/, "")
        .replace(/[_\-.]+/g, " ")
        .replace(/([a-z\d])([A-Z])/g, "$1 $2")
        .replace(/\s+/g, " ")
        .trim();

      if (!text) return "此字段";

      return text
        .split(" ")
        .map(function (part) {
          if (!part) return part;
          return part.charAt(0).toUpperCase() + part.slice(1);
        })
        .join(" ");
    },

    getFieldLabel: function (input) {
      if (!input) return "此字段";

      var explicitLabel =
        input.getAttribute("data-label") ||
        input.getAttribute("data-field-label") ||
        input.getAttribute("aria-label") ||
        input.getAttribute("title");
      var normalizedExplicit = this.normalizeLabelText(explicitLabel);
      if (normalizedExplicit) return normalizedExplicit;

      var byFor = null;
      if (input.id) {
        byFor = document.querySelector('label[for="' + input.id + '"]');
      }
      if (byFor) {
        var byForText = this.normalizeLabelText(byFor.textContent);
        if (byForText) return byForText;
      }

      var wrappingLabel = input.closest("label");
      if (wrappingLabel) {
        var wrappingText = this.normalizeLabelText(wrappingLabel.textContent);
        if (wrappingText) return wrappingText;
      }

      var prev = input.previousElementSibling;
      while (prev) {
        if (prev.tagName && prev.tagName.toLowerCase() === "label") {
          var prevText = this.normalizeLabelText(prev.textContent);
          if (prevText) return prevText;
        }
        var prevInnerLabel = prev.querySelector
          ? prev.querySelector("label")
          : null;
        if (prevInnerLabel) {
          var prevInnerText = this.normalizeLabelText(
            prevInnerLabel.textContent,
          );
          if (prevInnerText) return prevInnerText;
        }
        prev = prev.previousElementSibling;
      }

      var nearestContainer = input.closest(
        ".form-group, .form-floating, .form-field, .auth-field, .mb-3, .mb-2, .col, .col-md-6, .col-lg-6",
      );
      if (nearestContainer) {
        var nearbyLabel = nearestContainer.querySelector("label");
        if (nearbyLabel) {
          var nearbyText = this.normalizeLabelText(nearbyLabel.textContent);
          if (nearbyText) return nearbyText;
        }
      }

      var byName = input.form
        ? input.form.querySelector('label[for="' + (input.name || "") + '"]')
        : null;
      if (byName) {
        var byNameText = this.normalizeLabelText(byName.textContent);
        if (byNameText) return byNameText;
      }

      var fallbackName = this.normalizeLabelText(input.name || input.id);
      if (fallbackName && /[\u4e00-\u9fff]/.test(fallbackName)) {
        return fallbackName;
      }

      return "此字段";
    },

    getRequiredMessage: function (input) {
      return this.getFieldLabel(input) + "不能为空";
    },

    showError: function (input, message) {
      var isCheck = input.type === "checkbox" || input.type === "radio";
      var container = input.parentElement;

      var errorDiv = container.querySelector(".custom-error-msg");
      if (!errorDiv) {
        errorDiv = document.createElement("div");
        errorDiv.className = "custom-error-msg text-danger small mt-1";
        errorDiv.style.cssText =
          "font-size: 12px; color: #dc3545; margin-top: 4px; display: block;";

        if (input.nextSibling) {
          container.insertBefore(errorDiv, input.nextSibling);
        } else {
          container.appendChild(errorDiv);
        }
      }

      errorDiv.innerText = message;
      input.classList.add("is-invalid");
      if (!isCheck) {
        input.style.setProperty("border-color", "#dc3545", "important");
      }
    },

    clearError: function (input) {
      var container = input.parentElement;
      var errorDiv = container.querySelector(".custom-error-msg");

      if (errorDiv) {
        errorDiv.remove();
      }

      input.classList.remove("is-invalid");
      input.style.removeProperty("border-color");
    },

    isEmpty: function (input) {
      if (input.type === "checkbox" || input.type === "radio")
        return !input.checked;
      return input.value.trim() === "";
    },
  };

  // 2. Initialize global form validation for ALL pages
  function initGlobalValidation() {
    document.querySelectorAll("form").forEach(function (form) {
      form.setAttribute("novalidate", "novalidate");

      var requiredInputs = form.querySelectorAll(
        "input[required], select[required], textarea[required]",
      );

      requiredInputs.forEach(function (input) {
        var eventType =
          input.type === "checkbox" || input.type === "radio"
            ? "change"
            : "input";
        input.addEventListener(eventType, function () {
          if (!window.GlobalFormValidation.isEmpty(input)) {
            window.GlobalFormValidation.clearError(input);
          }
        });

        input.addEventListener("invalid", function (e) {
          e.preventDefault();
        });
      });

      form.addEventListener("submit", function (e) {
        var authForms = ["loginForm", "regForm", "forgotForm", "resetForm"];
        if (authForms.indexOf(form.id) !== -1) return;

        var hasError = false;

        requiredInputs.forEach(function (input) {
          if (window.GlobalFormValidation.isEmpty(input)) {
            hasError = true;
            var msg = window.GlobalFormValidation.getRequiredMessage(input);
            window.GlobalFormValidation.showError(input, msg);
          }
        });

        if (hasError) {
          e.preventDefault();
          e.stopImmediatePropagation();

          var firstError = form.querySelector(".is-invalid");
          if (firstError) {
            firstError.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        }
      });
    });
  }

  // 3. Automatically convert HTML Flash Messages into SweetAlert Popups
  function initFlashPopups() {
    function convertAlertToPopup(alertBox) {
      // ESCAPE HATCH: If the alert has 'native-alert', DO NOT convert it to a popup
      if (alertBox.classList.contains("native-alert")) return;

      if (
        typeof Swal !== "undefined" &&
        !alertBox.classList.contains("popup-processed")
      ) {
        alertBox.classList.add("popup-processed"); // Prevent infinite loops

        var type = "info";
        var title = "提示";

        // Determine style
        if (alertBox.classList.contains("alert-success")) {
          type = "success";
          title = "操作成功";
        } else if (alertBox.classList.contains("alert-danger")) {
          type = "error";
          title = "操作失败";
        } else if (alertBox.classList.contains("alert-warning")) {
          type = "warning";
          title = "警告";
        }

        // Clean up the message text
        var closeBtn = alertBox.querySelector(".btn-close");
        if (closeBtn) closeBtn.remove();
        var msg = alertBox.innerHTML.trim();

        // Destroy the HTML box
        alertBox.remove();

        // Launch the SweetAlert
        Swal.fire({
          icon: type,
          title: title,
          html: msg,
          confirmButtonColor: "#233dd2",
          confirmButtonText: "我知道了",
        });
      }
    }

    // A. Convert any alerts that loaded naturally via PHP
    document.querySelectorAll(".alert").forEach(convertAlertToPopup);

    // B. Watch the DOM for any NEW alerts injected dynamically by AJAX (like admin.js)
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) {
            // If it's an HTML element
            if (node.classList.contains("alert")) {
              convertAlertToPopup(node);
            } else {
              // Check if an alert was injected inside the new element
              node.querySelectorAll(".alert").forEach(convertAlertToPopup);
            }
          }
        });
      });
    });

    // Start watching the entire body for changes
    observer.observe(document.body, { childList: true, subtree: true });
  }
  window.showNoChangeWarning = showNoChangeWarning;

  buildConfig();

  document.addEventListener("DOMContentLoaded", function () {
    initGlobalValidation();
    initCheckChangesForms();
    initBreadcrumbConsolidation();
    initLogoutHandler();
    initFlashPopups();

    if (typeof jQuery !== "undefined") {
      // Automatically add the .form-select class whenever a table loads or redraws
      jQuery(document).on("draw.dt init.dt", function () {
        jQuery(".dataTables_length select").addClass("form-select");
      });
    }
  });
})();

// 4. Smart Notice with "Don't show again" (不再提示) Checkbox
window.showSmartNotice = function (noticeId, title, message, icon, callback) {
  // 1. Check if the user already checked "Don't show again"
  if (localStorage.getItem("hide_notice_" + noticeId) === "true") {
    if (typeof callback === "function") callback();
    return;
  }

  // 2. Show the SweetAlert with a custom injected checkbox HTML
  if (typeof Swal !== "undefined") {
    Swal.fire({
      title: title || "提示",
      html:
        message +
        '<div style="margin-top: 25px; font-size: 14px; text-align: center;"><label style="cursor:pointer; display:inline-flex; align-items:center; gap:6px; color:#555;"><input type="checkbox" id="swal-dont-show-again" style="width:16px; height:16px; cursor:pointer;"> 不再提示 (Don\'t show again)</label></div>',
      icon: icon || "info",
      confirmButtonColor: "#233dd2",
      confirmButtonText: "我知道了",
      allowOutsideClick: false,
      preConfirm: function () {
        // BUG FIX: Return an object. Returning 'false' directly prevents SWAL from closing!
        var checkbox = document.getElementById("swal-dont-show-again");
        return { isChecked: checkbox ? checkbox.checked : false };
      },
    }).then(function (result) {
      if (result.isConfirmed) {
        // If the object says the box was checked, save it to local storage
        if (result.value && result.value.isChecked) {
          localStorage.setItem("hide_notice_" + noticeId, "true");
        }
        // Proceed with opening the modal
        if (typeof callback === "function") callback();
      }
    });
  } else {
    alert(message);
    if (typeof callback === "function") callback();
  }
};

function updateDeviceClass() {
  const width = window.innerWidth;
  const body = document.body;
  const previousMode = body.classList.contains("is-mobile")
    ? "mobile"
    : body.classList.contains("is-tablet")
      ? "tablet"
      : "desktop";

  // Clear existing classes
  body.classList.remove(
    "is-mobile",
    "is-tablet",
    "is-desktop",
    "is-mobile-480",
    "is-mobile-420",
    "is-mobile-320",
  );

  // Apply the strict global standards
  if (width <= 767) {
    body.classList.add("is-mobile");
    if (width <= 480) body.classList.add("is-mobile-480");
    if (width <= 420) body.classList.add("is-mobile-420");
    if (width <= 320) body.classList.add("is-mobile-320");
  } else if (width >= 768 && width <= 1023) {
    body.classList.add("is-tablet");
  } else {
    body.classList.add("is-desktop");
  }

  const currentMode = body.classList.contains("is-mobile")
    ? "mobile"
    : body.classList.contains("is-tablet")
      ? "tablet"
      : "desktop";

  window.dispatchEvent(
    new CustomEvent("deviceclasschange", {
      detail: {
        previousMode: previousMode,
        mode: currentMode,
        width: width,
      },
    }),
  );
}

// Run on load and whenever the screen is resized
window.addEventListener("resize", updateDeviceClass);
document.addEventListener("DOMContentLoaded", updateDeviceClass);
