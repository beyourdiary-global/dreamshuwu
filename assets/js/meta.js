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
