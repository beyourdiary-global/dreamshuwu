/**
 * Consolidates: Password Toggles, Field Validation, Error Messaging, and Form Handling
 */

document.addEventListener("DOMContentLoaded", () => {
  // --- 1. Universal Auth Utilities ---
  window.AuthUtils = {
    showError: (input, message) => {
      const isCheck = input.type === "checkbox" || input.type === "radio";
      const container = isCheck
        ? input.closest(".terms-container")?.parentElement
        : input.closest(".auth-field, .form-floating, .form-group");

      if (!container) return;

      let errorDiv = container.querySelector(".custom-error-msg");
      if (!errorDiv) {
        errorDiv = document.createElement("div");
        errorDiv.className = "custom-error-msg text-danger small mt-1";
        errorDiv.style.cssText =
          "font-size: 12px; color: #dc3545; margin-top: 4px; display: block;";

        const termsBox = container.querySelector(".terms-container");
        termsBox ? termsBox.after(errorDiv) : container.appendChild(errorDiv);
      }

      errorDiv.innerText = message;
      input.classList.add("is-invalid");
      if (!isCheck)
        input.style.setProperty("border-color", "#dc3545", "important");
    },

    clearError: (input) => {
      const isCheck = input.type === "checkbox" || input.type === "radio";
      const container = isCheck
        ? input.closest(".terms-container")?.parentElement
        : input.closest(".auth-field, .form-floating, .form-group");

      if (container) {
        const errorDiv = container.querySelector(".custom-error-msg");
        if (errorDiv) errorDiv.remove();
      }
      input.classList.remove("is-invalid");
      input.style.borderColor = "";
    },

    isValidEmail: (email) => {
      const pattern =
        window.StarAdminConfig?.emailRegex || "^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$";
      return new RegExp(pattern).test(email);
    },

    isEmpty: (input) => {
      if (input.type === "checkbox" || input.type === "radio")
        return !input.checked;
      return input.value.trim() === "";
    },

    isStrongPassword: (pwd) => {
      const globalPwdRegex = window.StarAdminConfig
        ? window.StarAdminConfig.pwdRegex
        : null;
      const regex = globalPwdRegex
        ? new RegExp(globalPwdRegex)
        : /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
      return regex.test(pwd);
    },
  };

  // --- 2. Universal Password Toggle ---
  const setupPasswordToggles = () => {
    document
      .querySelectorAll(".toggle-password, #togglePassword")
      .forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          const targetId = btn.getAttribute("data-target");
          const pwdInput = targetId
            ? document.getElementById(targetId)
            : btn.parentElement.querySelector("input");

          if (!pwdInput) return;

          const isPassword = pwdInput.type === "password";
          pwdInput.type = isPassword ? "text" : "password";

          const icon = btn.querySelector("i");
          if (icon)
            icon.className = isPassword ? "fa fa-eye-slash" : "fa fa-eye";
          btn.title = isPassword ? "隐藏密码" : "显示密码";
        });
      });
  };

  // --- 3. Login Form Handler ---
  const initializeLoginForm = () => {
    const loginForm = document.getElementById("loginForm");
    if (!loginForm) return;

    const submitBtn = document.getElementById("loginBtn");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const errorDiv = document.getElementById("loginError");
    let isSubmitting = false;

    loginForm.noValidate = true;

    if (emailInput) {
      emailInput.addEventListener("input", () => {
        if (errorDiv) errorDiv.style.display = "none";
      });
    }
    if (passwordInput) {
      passwordInput.addEventListener("input", () => {
        if (errorDiv) errorDiv.style.display = "none";
      });
    }

    loginForm.addEventListener("submit", (e) => {
      e.preventDefault();
      let hasError = false;

      if (!emailInput || window.AuthUtils.isEmpty(emailInput)) {
        hasError = true;
        window.AuthUtils.showError(emailInput, "请输入邮箱");
      } else if (!window.AuthUtils.isValidEmail(emailInput.value.trim())) {
        hasError = true;
        window.AuthUtils.showError(emailInput, "请输入有效邮箱");
      } else {
        window.AuthUtils.clearError(emailInput);
      }

      if (!passwordInput || window.AuthUtils.isEmpty(passwordInput)) {
        hasError = true;
        window.AuthUtils.showError(passwordInput, "请输入密码");
      } else {
        window.AuthUtils.clearError(passwordInput);
      }

      if (hasError || isSubmitting) return;

      isSubmitting = true;
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "登录中...";
        submitBtn.style.cssText = "opacity: 0.8; cursor: not-allowed;";
      }

      const emailVal = emailInput.value.trim();
      const passVal = passwordInput.value;
      const redirectInput = loginForm.querySelector('input[name="redirect"]');

      $.ajax({
        url: loginForm.action || window.location.href,
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
          const message =
            resp && resp.message ? resp.message : "登录失败，请稍后再试";
          if (errorDiv) {
            errorDiv.querySelector(".error-text").textContent = message;
            errorDiv.style.display = "block";
          } else if (emailInput) {
            window.AuthUtils.showError(emailInput, message);
          }
        })
        .fail(() => {
          const message = "网络错误，请稍后再试";
          if (errorDiv) {
            errorDiv.querySelector(".error-text").textContent = message;
            errorDiv.style.display = "block";
          } else if (emailInput) {
            window.AuthUtils.showError(emailInput, message);
          }
        })
        .always(() => {
          isSubmitting = false;
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "立即登录";
            submitBtn.style.cssText = "";
          }
        });
    });
  };

  // --- 4. Forgot Password Form Handler ---
  const initializeForgotForm = () => {
    const forgotForm = document.getElementById("forgotForm");
    if (!forgotForm) return;

    const submitBtn = forgotForm.querySelector('button[type="submit"]');
    const emailInput = document.getElementById("email");
    let isSubmitting = false;

    forgotForm.noValidate = true;

    if (emailInput) {
      emailInput.addEventListener("input", () => {
        if (!window.AuthUtils.isEmpty(emailInput)) {
          window.AuthUtils.clearError(emailInput);
        }
      });
    }

    forgotForm.addEventListener("submit", (e) => {
      if (!emailInput) return;
      const emailVal = emailInput.value.trim();
      let hasError = false;

      if (!emailVal) {
        hasError = true;
        window.AuthUtils.showError(emailInput, "请输入邮箱");
      } else if (!window.AuthUtils.isValidEmail(emailVal)) {
        hasError = true;
        window.AuthUtils.showError(emailInput, "请输入有效邮箱");
      } else {
        window.AuthUtils.clearError(emailInput);
      }

      if (hasError) {
        e.preventDefault();
        return;
      }

      if (isSubmitting) {
        e.preventDefault();
        return;
      }

      isSubmitting = true;
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "处理中...";
        submitBtn.style.cssText = "opacity: 0.8; cursor: not-allowed;";
      }
    });
  };

  // --- 5. Register Form Handler ---
  const initializeRegisterForm = () => {
    const regForm = document.getElementById("regForm");
    if (!regForm) return;

    const submitBtn = document.getElementById("submitBtn");
    const pwd = document.getElementById("password");
    const strengthText = document.getElementById("strength-text");
    let isSubmitting = false;

    regForm.noValidate = true;
    const requiredInputs = regForm.querySelectorAll(
      "input[required], select[required], textarea[required]",
    );

    const serverErrors = JSON.parse(
      regForm.getAttribute("data-field-errors") || "{}",
    );
    Object.keys(serverErrors).forEach((name) => {
      const field = document.getElementById(name);
      if (field && name !== "form")
        window.AuthUtils.showError(field, serverErrors[name]);
    });

    requiredInputs.forEach((input) => {
      const eventType =
        input.type === "checkbox" || input.type === "radio"
          ? "change"
          : "input";
      input.addEventListener(eventType, () => {
        if (!window.AuthUtils.isEmpty(input))
          window.AuthUtils.clearError(input);
      });
    });

    if (pwd && strengthText) {
      pwd.addEventListener("input", () => {
        const val = pwd.value;
        let score = 0;

        if (val.length > 0) {
          score =
            (val.length >= 8) +
            /[A-Z]/.test(val) +
            /[0-9]/.test(val) +
            /[\W_]/.test(val);
          // FIX: Force to "弱" (Weak) if only lowercase letters are entered
          if (score === 0) {
            score = 1;
          }
        }

        const levels = ["未填写", "弱", "中", "强", "极强"];
        const colors = ["#888", "#d9534f", "#f0ad4e", "#5cb85c", "#2e7d32"];
        strengthText.innerText = levels[score];
        strengthText.style.color = colors[score];
      });
    }

    regForm.addEventListener("submit", (e) => {
      let hasError = false;

      requiredInputs.forEach((input) => {
        if (window.AuthUtils.isEmpty(input)) {
          hasError = true;
          const msg =
            input.id === "terms" ? "必须同意条款与条件" : "此字段不能为空";
          window.AuthUtils.showError(input, msg);
        } else {
          window.AuthUtils.clearError(input);
        }
      });

      if (hasError || isSubmitting) return e.preventDefault();

      isSubmitting = true;
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerText = "注册中...";
        submitBtn.style.cssText = "opacity: 0.8; cursor: not-allowed;";
      }
    });
  };

  // --- 6. Reset Password Form Handler ---
  const initializeResetForm = () => {
    const resetForm = document.getElementById("resetForm");
    if (!resetForm) return;

    const pwd = document.getElementById("password");
    const confirmPwd = document.getElementById("confirm_password");
    const submitBtn = resetForm.querySelector('button[type="submit"]');
    const strengthText = document.getElementById("strength-text");
    let isSubmitting = false;

    resetForm.noValidate = true;

    [pwd, confirmPwd].forEach((input) => {
      if (input) {
        input.addEventListener("input", () =>
          window.AuthUtils.clearError(input),
        );
      }
    });

    if (pwd && strengthText) {
      pwd.addEventListener("input", () => {
        const val = pwd.value;
        let score = 0;

        if (val.length > 0) {
          score =
            (val.length >= 8) +
            /[A-Z]/.test(val) +
            /[0-9]/.test(val) +
            /[\W_]/.test(val);
          // FIX: Force to "弱" (Weak) if only lowercase letters are entered
          if (score === 0) {
            score = 1;
          }
        }

        const levels = ["未填写", "弱", "中", "强", "极强"];
        const colors = ["#888", "#d9534f", "#f0ad4e", "#5cb85c", "#2e7d32"];
        strengthText.innerText = levels[score];
        strengthText.style.color = colors[score];
      });
    }

    resetForm.addEventListener("submit", (e) => {
      let hasError = false;

      if (window.AuthUtils.isEmpty(pwd)) {
        hasError = true;
        window.AuthUtils.showError(pwd, "请输入新密码");
      } else if (!window.AuthUtils.isStrongPassword(pwd.value)) {
        hasError = true;
        window.AuthUtils.showError(
          pwd,
          "密码不符合要求 (最低8个字符，需包含大小写字母、数字和特殊字符)",
        );
      }

      if (window.AuthUtils.isEmpty(confirmPwd)) {
        hasError = true;
        window.AuthUtils.showError(confirmPwd, "请确认新密码");
      } else if (pwd.value !== confirmPwd.value) {
        hasError = true;
        window.AuthUtils.showError(confirmPwd, "两次输入的密码不一致");
      }

      if (hasError || isSubmitting) {
        e.preventDefault();
        return;
      }

      isSubmitting = true;
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerText = "处理中...";
        submitBtn.style.cssText = "opacity: 0.8; cursor: not-allowed;";
      }
    });
  };

  // Initialize all elements
  setupPasswordToggles();
  initializeLoginForm();
  initializeForgotForm();
  initializeRegisterForm();
  initializeResetForm();
});
