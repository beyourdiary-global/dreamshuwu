// Used when user changed password
document.addEventListener("DOMContentLoaded", () => {
  const pwdRedirect = document.getElementById("pwd-redirect");
  if (pwdRedirect) {
    const targetUrl = pwdRedirect.dataset.url || "";
    const delay = Number(pwdRedirect.dataset.delay || 1500);
    const redirectMessage =
      pwdRedirect.dataset.message || "密码修改成功，请使用新密码重新登录。";

    if (targetUrl) {
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "success",
          title: "操作成功",
          text: redirectMessage,
          confirmButtonText: "我知道了",
          confirmButtonColor: "#233dd2",
          allowOutsideClick: false,
          allowEscapeKey: false,
        }).then(() => {
          window.location.href = targetUrl;
        });
      } else {
        setTimeout(() => {
          window.location.href = targetUrl;
        }, delay);
      }
    }

    return;
  }

  // --- 2. Form B Validation (Password) ---
  const pwdForm = document.getElementById("passwordForm");
  const newPwd = document.getElementById("new_password");
  const confirmPwd = document.getElementById("confirm_password");
  const currentPwd = pwdForm
    ? pwdForm.querySelector('[name="current_password"]')
    : null;

  // --- 2.1 Password Toggle ---
  document.querySelectorAll(".toggle-password").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();

      const targetId = btn.getAttribute("data-target");
      const input = targetId
        ? document.getElementById(targetId)
        : btn.closest(".password-field")?.querySelector("input");

      if (!input) return;

      const toText = input.type === "password";
      input.type = toText ? "text" : "password";

      const icon = btn.querySelector("i");
      if (icon) {
        icon.className = toText ? "fa fa-eye-slash" : "fa fa-eye";
      }
      btn.setAttribute("title", toText ? "隐藏密码" : "显示密码");
    });
  });

  function showFieldError(input, message) {
    if (!input) return;
    const container = input.closest(".form-group") || input.parentElement;
    if (!container) return;

    let errorDiv = container.querySelector(".custom-error-msg");
    if (!errorDiv) {
      errorDiv = document.createElement("div");
      errorDiv.className = "custom-error-msg text-danger small mt-1";
      container.appendChild(errorDiv);
    }
    errorDiv.innerText = message || "输入有误";
    input.classList.add("is-invalid");
    input.style.setProperty("border-color", "#dc3545", "important");
  }

  function clearFieldError(input) {
    if (!input) return;
    const container = input.closest(".form-group") || input.parentElement;
    if (!container) return;
    const errorDiv = container.querySelector(".custom-error-msg");
    if (errorDiv) errorDiv.remove();
    input.classList.remove("is-invalid");
    input.style.removeProperty("border-color");
  }

  if (pwdForm) {
    [currentPwd, newPwd, confirmPwd].forEach((input) => {
      if (!input) return;
      input.addEventListener("input", () => clearFieldError(input));
    });

    const serverError = (pwdForm.dataset.serverError || "").trim();
    const serverErrorTarget = (pwdForm.dataset.serverErrorTarget || "").trim();
    if (serverError && serverErrorTarget) {
      const targetInput = document.getElementById(serverErrorTarget);
      if (targetInput) {
        showFieldError(targetInput, serverError);
        targetInput.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    }

    pwdForm.addEventListener("submit", (e) => {
      [currentPwd, newPwd, confirmPwd].forEach((input) =>
        clearFieldError(input),
      );

      // Check Empty
      if (!currentPwd.value || !newPwd.value || !confirmPwd.value) {
        e.preventDefault();
        const missingField = !currentPwd.value
          ? currentPwd
          : !newPwd.value
            ? newPwd
            : confirmPwd;
        const requiredMessage =
          window.GlobalFormValidation &&
          typeof window.GlobalFormValidation.getRequiredMessage === "function"
            ? window.GlobalFormValidation.getRequiredMessage(missingField)
            : "此字段不能为空";
        showFieldError(missingField, requiredMessage);
        return;
      }

      // Check Match
      if (newPwd.value !== confirmPwd.value) {
        e.preventDefault();
        showFieldError(confirmPwd, "两次输入的密码不一致");
        return;
      }
    });
  }

  // --- 3. Avatar Preview Logic ---
  const avatarInput = document.getElementById("avatarInput");
  const avatarImg = document.getElementById("avatarPreview");

  if (avatarInput && avatarImg) {
    avatarInput.addEventListener("change", function (e) {
      const file = e.target.files[0];
      if (file) {
        // Read limit from the HTML attribute data-max-size
        const maxSize = this.dataset.maxSize;

        if (file.size > maxSize) {
          alert("图片大小不能超过 2MB");
          this.value = ""; // Clear input
          return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
          avatarImg.src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    });

    avatarImg.addEventListener("click", () => {
      avatarInput.click();
    });
  }
});
