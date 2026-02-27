(function () {
  var toggle = document.getElementById("pageSelectToggle");
  var menu = document.getElementById("pageSelectMenu");
  var valueInput = document.getElementById("pageSelectValue");
  var form = document.getElementById("pageSelectForm");

  if (!toggle || !menu || !valueInput || !form) return;

  toggle.addEventListener("click", function (e) {
    e.preventDefault();
    menu.classList.toggle("show");
    menu.setAttribute(
      "aria-hidden",
      menu.classList.contains("show") ? "false" : "true",
    );
  });

  menu.addEventListener("click", function (e) {
    var item = e.target.closest(".page-select-item");
    if (!item) return;

    var value = item.getAttribute("data-value") || "";
    valueInput.value = value;
    toggle.childNodes[0].nodeValue = item.textContent.trim();
    menu.classList.remove("show");
    menu.setAttribute("aria-hidden", "true");
    form.submit();
  });

  document.addEventListener("click", function (e) {
    if (!menu.contains(e.target) && !toggle.contains(e.target)) {
      menu.classList.remove("show");
      menu.setAttribute("aria-hidden", "true");
    }
  });
})();

document.addEventListener("DOMContentLoaded", function () {
  // Select all forms that perform a reset/delete action
  const resetForms = document.querySelectorAll(".reset-form");

  resetForms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      e.preventDefault(); // Stop the form from submitting immediately

      Swal.fire({
        title: "Are you sure?",
        text: "Do you want to reset this page to Global Defaults?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545", // Red color for danger
        cancelButtonColor: "#6c757d", // Grey color for cancel
        confirmButtonText: "Yes, Reset it!",
        cancelButtonText: "Cancel",
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit(); // Submit the form programmatically
        }
      });
    });
  });
});

const metaForms = document.querySelectorAll("form.check-changes");
metaForms.forEach((form) => {
  form.addEventListener("submit", function (e) {
    // Exclude the 'delete_page' reset form from this validation
    const formType = form.querySelector("input[name='form_type']");
    if (formType && formType.value === "delete_page") return;

    const inputs = form.querySelectorAll("input[type='text'], textarea");
    if (inputs.length === 0) return;

    let emptyCount = 0;
    inputs.forEach((input) => {
      if (input.value.trim() === "") {
        emptyCount++;
      }
    });

    // Condition 1: "if no data cannot save"
    if (emptyCount === inputs.length) {
      e.preventDefault();
      Swal.fire({
        icon: "warning",
        title: "提交失败 (Save Failed)",
        text: "不能保存空数据，请填写内容。\n(Cannot save empty data.)",
        confirmButtonColor: "#4e73df",
      });
    }
    // Condition 2: "once have data, then will become compulsory for all input"
    else if (emptyCount > 0) {
      e.preventDefault();
      Swal.fire({
        icon: "warning",
        title: "信息不完整 (Incomplete Data)",
        text: "所有字段都是必填的。\n(Once you enter data, ALL fields become compulsory.)",
        confirmButtonColor: "#4e73df",
      });
    }
  });
});
