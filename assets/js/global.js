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

    var preferredBreadcrumb =
      breadcrumbs.find(function (el) {
        return el.closest(".card-header");
      }) || breadcrumbs[0];

    var dashboardMain = document.querySelector(".dashboard-main");
    var fallbackParent =
      preferredBreadcrumb.closest(".container-fluid, .container") ||
      preferredBreadcrumb.parentElement;
    var mountParent = fallbackParent;

    if (dashboardMain) {
      var innerContainer = Array.from(dashboardMain.children).find(
        function (child) {
          return (
            child.classList &&
            (child.classList.contains("container-fluid") ||
              child.classList.contains("container"))
          );
        },
      );
      mountParent = innerContainer || dashboardMain;
    }

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
  }

  window.showNoChangeWarning = showNoChangeWarning;

  buildConfig();
  document.addEventListener("DOMContentLoaded", function () {
    initCheckChangesForms();
    initBreadcrumbConsolidation();
  });
})();
