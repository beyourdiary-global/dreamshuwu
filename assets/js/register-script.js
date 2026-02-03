document.addEventListener("DOMContentLoaded", () => {
  const regForm = document.getElementById("regForm");
  const submitBtn = document.getElementById("submitBtn");
  const pwd = document.getElementById("password");
  const strengthText = document.getElementById("strength-text");
  const requiredInputs = regForm.querySelectorAll("input[required]");

  // 1. Real-time form validation
  const validateForm = () => {
    let allFilled = true;
    requiredInputs.forEach((input) => {
      if (input.type === "checkbox") {
        if (!input.checked) allFilled = false;
      } else {
        if (input.value.trim() === "") allFilled = false;
      }
    });
    submitBtn.disabled = !allFilled;
  };

  regForm.addEventListener("input", validateForm);
  regForm.addEventListener("change", validateForm);

  // 2. Password Strength Logic
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

  // 3. RESOLVED: PREVENT DUPLICATE SUBMISSION
  let isSubmitting = false;

  regForm.addEventListener("submit", (e) => {
    if (isSubmitting) {
      e.preventDefault();
      return;
    }

    if (!regForm.checkValidity()) {
      e.preventDefault();
      regForm.reportValidity();
      return;
    }

    isSubmitting = true;

    submitBtn.disabled = true;
    submitBtn.innerText = "注册中...";
    submitBtn.style.opacity = "0.8";
    submitBtn.style.cursor = "not-allowed";
  });

  // Run validation on load
  validateForm();
});
