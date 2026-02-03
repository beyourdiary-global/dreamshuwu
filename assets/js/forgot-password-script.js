document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("forgotForm");
  const email = document.getElementById("email");
  const btn = document.getElementById("forgotBtn");
  const clientError = document.getElementById("clientError");

  if (!form) return;

  const showError = (msg) => {
    clientError.textContent = msg;
    clientError.style.display = "block";
  };

  const clearError = () => {
    clientError.textContent = "";
    clientError.style.display = "none";
  };

  form.addEventListener("submit", (e) => {
    clearError();
    const emailVal = email.value.trim();

    if (!emailVal) {
      e.preventDefault();
      showError("请输入邮箱");
      return;
    }

    // --- UPDATED: Calling the centralized constant from init via window.StarAdminConfig ---
    const globalRegex = window.StarAdminConfig
      ? window.StarAdminConfig.emailRegex
      : null;

    if (!emailRegex.test(emailVal)) {
      e.preventDefault();
      showError("请输入有效邮箱");
      return;
    }

    btn.disabled = true;
    btn.textContent = "处理中...";
  });
});
