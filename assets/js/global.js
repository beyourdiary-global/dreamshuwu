/**
 * Consolidates: Password Toggles, Field Validation, Error Messaging, and Form Handling
 */

document.addEventListener("DOMContentLoaded", () => {
  // --- 1. Universal Auth Utilities ---
  window.AuthUtils = {
    /**
     * Consistently renders error messages under inputs
     */
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

        // Append after the specific terms container or at the end of the field
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

  // --- 3. Register Form Handler ---
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

    // Server-side error display
    const serverErrors = JSON.parse(
      regForm.getAttribute("data-field-errors") || "{}",
    );
    Object.keys(serverErrors).forEach((name) => {
      const field = document.getElementById(name);
      if (field && name !== "form")
        window.AuthUtils.showError(field, serverErrors[name]);
    });

    // Live Validation Listeners
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

    // Password Strength
    if (pwd && strengthText) {
      pwd.addEventListener("input", () => {
        const val = pwd.value;
        let score =
          val.length > 0
            ? (val.length >= 8) +
              /[A-Z]/.test(val) +
              /[0-9]/.test(val) +
              /[\W_]/.test(val)
            : 0;
        const levels = ["未填写", "弱", "中", "强", "极强"];
        const colors = ["#888", "#d9534f", "#f0ad4e", "#5cb85c", "#2e7d32"];
        strengthText.innerText = levels[score];
        strengthText.style.color = colors[score];
      });
    }

    // Submit Handler
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

  setupPasswordToggles();
  initializeRegisterForm();
});
