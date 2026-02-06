document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("resetForm");
  if (!form) return;

  const newPass = document.getElementById("new_password");
  const confirmPass = document.getElementById("confirm_password");
  const btn = document.getElementById("resetBtn");
  const clientError = document.getElementById("clientError");
  const strengthText = document.getElementById("strength-text");

  // Access the global regex pattern from StarAdminConfig
  const globalPwdRegex = window.StarAdminConfig
    ? window.StarAdminConfig.pwdRegex
    : null;
  const fallbackPwdRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

  const showError = (msg) => {
    clientError.textContent = msg;
    clientError.style.display = "block";
  };

  const clearError = () => {
    clientError.textContent = "";
    clientError.style.display = "none";
  };

  const isStrong = (pwd) => {
    // Reuse centralized regex if available, otherwise fallback
    const regex = globalPwdRegex || fallbackPwdRegex;
    return regex.test(pwd);
  };

  const updateStrength = () => {
    const val = newPass.value;
    if (!val) {
      strengthText.textContent = "未填写";
      strengthText.style.color = "#666";
      return;
    }
    if (val.length < 8) {
      strengthText.textContent = "太短";
      strengthText.style.color = "#d9534f";
      return;
    }
    if (isStrong(val)) {
      strengthText.textContent = "强";
      strengthText.style.color = "#28a745";
      return;
    }
    strengthText.textContent = "一般";
    strengthText.style.color = "#f0ad4e";
  };

  newPass.addEventListener("input", updateStrength);

  form.addEventListener("submit", (e) => {
    clearError();

    const passVal = newPass.value;
    const confirmVal = confirmPass.value;

    if (!passVal || !confirmVal) {
      e.preventDefault();
      showError("请输入新密码");
      return;
    }

    if (passVal !== confirmVal) {
      e.preventDefault();
      showError("两次输入的密码不一致");
      return;
    }

    if (!isStrong(passVal)) {
      e.preventDefault();
      showError("密码不符合安全要求");
      return;
    }

    btn.disabled = true;
    btn.textContent = "处理中...";
  });
});
