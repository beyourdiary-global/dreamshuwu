/**
 * assets/js/header-script.js
 * Handles mobile dropdown toggles and outside click detection.
 */

// Function called by the onclick attribute in HTML
function toggleMobileDropdown() {
  const dropdown = document.getElementById("mobileAuthDropdown");
  if (dropdown) {
    dropdown.classList.toggle("show");
  }
}

// Close dropdown when clicking outside
window.addEventListener("click", function (e) {
  const trigger = document.querySelector(".user-trigger");
  const dropdown = document.getElementById("mobileAuthDropdown");

  // Check if the click was OUTSIDE both the trigger and the dropdown
  if (
    trigger &&
    dropdown &&
    !trigger.contains(e.target) &&
    !dropdown.contains(e.target)
  ) {
    dropdown.classList.remove("show");
  }
});
