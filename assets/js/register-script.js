document.addEventListener("DOMContentLoaded", () => {
  const regForm = document.getElementById("regForm");
  const submitBtn = document.getElementById("submitBtn");
  const pwd = document.getElementById("password");
  const strengthText = document.getElementById("strength-text");

  // Safety check: if form doesn't exist, stop execution
  if (!regForm) return;

  // CRITICAL FIX: Disable browser's default validation tooltips
  // This allows our custom JavaScript 'submit' event to fire even if fields are empty.
  regForm.noValidate = true;

  // Select all required inputs
  const requiredInputs = regForm.querySelectorAll(
    "input[required], select[required], textarea[required]",
  );

  /**
   * Helper: Show Error Message
   */
  const showError = (input, message) => {
    // Find the auth-field container
    const authField = input.closest(".auth-field");
    if (!authField) return;

    // Find or Create Error Div
    let errorDiv = authField.querySelector(".custom-error-msg");

    if (!errorDiv) {
      errorDiv = document.createElement("div");
      errorDiv.className = "custom-error-msg text-danger small mt-1";
      errorDiv.style.fontSize = "12px";
      errorDiv.style.color = "#dc3545";
      errorDiv.style.marginTop = "4px";
      errorDiv.style.display = "block";

      // Append to the auth-field container
      authField.appendChild(errorDiv);
    }

    // Set Message and Styles
    errorDiv.innerText = message;
    input.classList.add("is-invalid");
    input.style.borderColor = "#dc3545";
  };

  /**
   * Helper: Clear Error Message
   */
  const clearError = (input) => {
    const authField = input.closest(".auth-field");
    if (authField) {
      const errorDiv = authField.querySelector(".custom-error-msg");
      if (errorDiv) {
        errorDiv.remove();
      }
    }
    input.classList.remove("is-invalid");
    input.style.borderColor = "";
  };

  /**
   * Helper: Show/Clear error for terms checkbox
   */
  const showErrorForTerms = (show) => {
    const termsCheckbox = document.getElementById("terms");
    const termsContainer = termsCheckbox?.closest(".terms-container");

    if (!termsContainer) return;

    // Find or create error div
    let errorDiv = termsContainer.nextElementSibling;
    if (show) {
      if (!errorDiv || !errorDiv.classList.contains("custom-error-msg")) {
        errorDiv = document.createElement("div");
        errorDiv.className = "custom-error-msg text-danger small mt-1";
        errorDiv.style.fontSize = "12px";
        errorDiv.style.color = "#dc3545";
        errorDiv.style.marginTop = "4px";
        errorDiv.style.display = "block";
        termsContainer.parentNode.insertBefore(
          errorDiv,
          termsContainer.nextSibling,
        );
      }
      errorDiv.innerText = "必须同意条款与条件";
    } else {
      if (errorDiv && errorDiv.classList.contains("custom-error-msg")) {
        errorDiv.remove();
      }
    }
  };

  // Display server-side validation errors on page load
  const fieldErrorsAttr = regForm.getAttribute("data-field-errors");
  const serverErrors = fieldErrorsAttr ? JSON.parse(fieldErrorsAttr) : {};

  Object.keys(serverErrors).forEach((fieldName) => {
    const errorMessage = serverErrors[fieldName];
    if (fieldName === "form") {
      // Form-level error - just skip for now
      return;
    }
    const field = document.getElementById(fieldName);
    if (field) {
      showError(field, errorMessage);
    }
  });

  // 1. Live Error Clearing on Input
  requiredInputs.forEach((input) => {
    const handler = () => {
      let isFilled = false;
      if (input.type === "checkbox" || input.type === "radio") {
        isFilled = input.checked;
      } else {
        isFilled = input.value.trim() !== "";
      }

      if (isFilled) {
        clearError(input);
      }
    };

    input.addEventListener("input", handler);
    input.addEventListener("change", handler);
  });

  // Handle terms checkbox error clearing
  const termsCheckbox = document.getElementById("terms");
  if (termsCheckbox) {
    termsCheckbox.addEventListener("change", () => {
      if (termsCheckbox.checked) {
        showErrorForTerms(false);
      }
    });
  }

  // 2. Password Strength Logic
  if (pwd && strengthText) {
    pwd.addEventListener("input", () => {
      const val = pwd.value;
      let score = 0;
      if (val.length > 0) {
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[\W_]/.test(val)) score++;
      }

      const levels = ["未填写", "弱", "中", "强", "极强"];
      const colors = ["#888", "#d9534f", "#f0ad4e", "#5cb85c", "#2e7d32"];

      strengthText.innerText = levels[score];
      strengthText.style.color = colors[score];
    });
  }

  // 2.5 Password Show/Hide Toggle
  const togglePasswordBtn = document.getElementById("togglePassword");
  if (togglePasswordBtn && pwd) {
    togglePasswordBtn.addEventListener("click", (e) => {
      e.preventDefault();
      const type =
        pwd.getAttribute("type") === "password" ? "text" : "password";
      pwd.setAttribute("type", type);

      // Update icon
      const icon = togglePasswordBtn.querySelector("i");
      if (type === "password") {
        icon.className = "fa fa-eye";
        togglePasswordBtn.title = "显示密码";
      } else {
        icon.className = "fa fa-eye-slash";
        togglePasswordBtn.title = "隐藏密码";
      }
    });
  }

  // 3. Submit Handler
  let isSubmitting = false;

  regForm.addEventListener("submit", (e) => {
    let hasError = false;

    // Check all required fields (except terms checkbox which is handled separately)
    requiredInputs.forEach((input) => {
      if (input.id === "terms") return; // Skip terms here, handle separately below

      let isEmpty = false;
      if (input.type === "checkbox" || input.type === "radio") {
        if (!input.checked) isEmpty = true;
      } else {
        if (input.value.trim() === "") isEmpty = true;
      }

      if (isEmpty) {
        hasError = true;
        showError(input, "此字段不能为空");
      } else {
        clearError(input);
      }
    });

    // Check terms checkbox separately
    const termsCheckbox = document.getElementById("terms");
    if (!termsCheckbox || !termsCheckbox.checked) {
      hasError = true;
      showErrorForTerms(true);
    } else {
      showErrorForTerms(false);
    }

    // If there are errors, STOP everything
    if (hasError) {
      e.preventDefault();
      return;
    }

    // Prevent double submission
    if (isSubmitting) {
      e.preventDefault();
      return;
    }

    // Lock button and allow submit
    isSubmitting = true;
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerText = "注册中...";
      submitBtn.style.opacity = "0.8";
      submitBtn.style.cursor = "not-allowed";
    }
  });
});
