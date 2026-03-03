/**
 * Sidebar Component JavaScript
 * Manages sidebar state and body class
 */
(function () {
  "use strict";

  function initSidebar() {
    var sidebar = document.getElementById("dashboardSidebar");
    if (!sidebar) return;

    // Add body class to enable layout offset
    document.body.classList.add("has-sidebar");

    // Highlight active sidebar link based on current URL
    highlightActiveLink(sidebar);
  }

  function highlightActiveLink(sidebar) {
    var links = sidebar.querySelectorAll(".sidebar-menu a[href]");
    var currentPath = window.location.pathname;
    var currentSearch = window.location.search;
    var currentFull = currentPath + currentSearch;

    links.forEach(function (link) {
      // Skip logout link
      if (link.classList.contains("logout-btn")) return;

      var href = link.getAttribute("href") || "";

      // Parse the href to get just the path portion
      try {
        var linkUrl = new URL(href, window.location.origin);
        var linkPath = linkUrl.pathname;

        // Check if current page matches this link
        if (linkPath === currentPath && linkPath !== "/index.php") {
          // Remove active from all first
          links.forEach(function (l) {
            if (!l.classList.contains("logout-btn")) {
              l.classList.remove("active");
            }
          });
          link.classList.add("active");
        }
      } catch (e) {
        // Ignore invalid URLs
      }
    });
  }

  // Initialize on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSidebar);
  } else {
    initSidebar();
  }
})();
