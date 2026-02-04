document.addEventListener("DOMContentLoaded", () => {
  // --- Helper: Show Custom Error Message ---
  const alertBox = document.getElementById("js-alert-box");

  function showError(message) {
    if (alertBox) {
      alertBox.textContent = message;
      alertBox.classList.remove("d-none");
      // Scroll to top so user sees the error
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  }

  function hideError() {
    if (alertBox) {
      alertBox.classList.add("d-none");
    }
  }

  // --- 1. Form A Validation (Info) ---
  const infoForm = document.getElementById("infoForm");
  if (infoForm) {
    infoForm.addEventListener("submit", function (e) {
      hideError(); // Clear previous errors

      const name = this.querySelector('[name="display_name"]').value.trim();
      const email = this.querySelector('[name="email"]').value.trim();

      if (!name) {
        e.preventDefault();
        showError("昵称不能为空");
        return;
      }
      if (!email) {
        e.preventDefault();
        showError("电子邮箱不能为空");
        return;
      }
    });
  }

  // --- 2. Form B Validation (Password) ---
  const pwdForm = document.getElementById("passwordForm");
  const newPwd = document.getElementById("new_password");
  const confirmPwd = document.getElementById("confirm_password");
  const currentPwd = pwdForm
    ? pwdForm.querySelector('[name="current_password"]')
    : null;

  if (pwdForm) {
    pwdForm.addEventListener("submit", (e) => {
      hideError(); // Clear previous errors

      // Check Empty
      if (!currentPwd.value || !newPwd.value || !confirmPwd.value) {
        e.preventDefault();
        showError("请填写所有必填项"); // "Please fill all required fields"
        return;
      }

      // Check Match
      if (newPwd.value !== confirmPwd.value) {
        e.preventDefault();
        showError("两次输入的密码不一致"); // "Passwords do not match"
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
        // [UPDATED] Read limit from the HTML attribute data-max-size
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
