document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("loginForm");
  const email = document.getElementById("email");
  const password = document.getElementById("password");
  const btn = document.getElementById("loginBtn");
  const errorBox = document.getElementById("loginError");
  const toggle = document.getElementById("togglePassword");
  const redirectInput = document.getElementById("redirect");

  const messages = {
    EMAIL_REQUIRED: "请输入邮箱",
    INVALID_EMAIL: "请输入有效邮箱",
    PASSWORD_REQUIRED: "请输入密码",
    EMAIL_NOT_FOUND: "该邮箱尚未注册",
    PASSWORD_INCORRECT: "密码错误，请重新输入",
    ACCOUNT_DISABLED: "账号已被停用，请联系管理员",
    LOGIN_FAILED: "登录失败，请稍后再试",
  };

  const showError = (code) => {
    // The server might return the actual message or an error code
    errorBox.textContent = messages[code] || code || messages.LOGIN_FAILED;
    errorBox.style.display = "block";
  };

  const clearError = () => {
    errorBox.textContent = "";
    errorBox.style.display = "none";
  };

  if (toggle) {
    toggle.addEventListener("click", () => {
      const isHidden = password.type === "password";
      password.type = isHidden ? "text" : "password";
      toggle.textContent = isHidden ? "隐藏" : "显示";
    });
  }

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    clearError();

    const emailVal = email.value.trim();
    const passVal = password.value;

    if (!emailVal) {
      showError("EMAIL_REQUIRED");
      return;
    }

    // --- UPDATED: Reusing the global regex pattern from header.php ---
    const globalRegex = window.StarAdminConfig
      ? window.StarAdminConfig.emailRegex
      : null;

    if (!globalRegex.test(emailVal)) {
      showError("INVALID_EMAIL");
      return;
    }

    if (!passVal) {
      showError("PASSWORD_REQUIRED");
      return;
    }

    btn.disabled = true;
    btn.textContent = "登录中...";

    $.ajax({
      url: "login.php",
      method: "POST",
      dataType: "json",
      data: {
        email: emailVal,
        password: passVal,
        redirect: redirectInput ? redirectInput.value : "",
        ajax: "1",
      },
    })
      .done((resp) => {
        if (resp && resp.success) {
          window.location.href = resp.redirect || "Home.php";
          return;
        }
        // Handle response errors
        showError(resp && resp.message ? resp.message : "LOGIN_FAILED");
      })
      .fail(() => {
        showError("LOGIN_FAILED");
      })
      .always(() => {
        btn.disabled = false;
        btn.textContent = "立即登录";
      });
  });
});
