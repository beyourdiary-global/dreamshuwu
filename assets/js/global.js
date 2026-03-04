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
            var msg =
              input.type === "checkbox" ? "必须同意此选项" : "此字段不能为空";
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

  window.showNoChangeWarning = showNoChangeWarning;

  buildConfig();

  document.addEventListener("DOMContentLoaded", function () {
    initGlobalValidation();
    initCheckChangesForms();
    initBreadcrumbConsolidation();
    initLogoutHandler();

    if (typeof jQuery !== "undefined") {
      // Automatically add the .form-select class whenever a table loads or redraws
      jQuery(document).on("draw.dt init.dt", function () {
        jQuery(".dataTables_length select").addClass("form-select");
      });
    }
  });
})();

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
