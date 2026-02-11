// Path: src/assets/js/logout-handler.js

document.addEventListener("DOMContentLoaded", function () {
  const logoutBtn = document.querySelector(".logout-btn");

  if (logoutBtn) {
    logoutBtn.addEventListener("click", function (e) {
      e.preventDefault(); // Stop the immediate redirect

      const logoutUrl = this.getAttribute("href");
      const apiUrl = this.getAttribute("data-api-url");
      const pageName = document.title; // Capture current page title for log

      Swal.fire({
        title: "确定要退出吗?",
        text: "您将退出当前会话",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d9534f",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "是的, 退出",
        cancelButtonText: "取消",
      }).then((result) => {
        if (result.isConfirmed) {
          // 1. User Confirmed: Proceed to Logout
          window.location.href = logoutUrl;
        } else {
          // 2. User Cancelled: Log it via AJAX
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
            }).catch((err) => console.error("Audit Log Error:", err));
          }
        }
      });
    });
  }
});
