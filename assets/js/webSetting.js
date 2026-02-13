/**
 * assets/js/webSetting.js
 * Handles confirmation logic for Web Settings page.
 */

function confirmAction(actionValue, messageText, iconType = "warning") {
  // Check if SweetAlert2 is loaded
  if (typeof Swal !== "undefined") {
    Swal.fire({
      title: "提示", // "Tip"
      text: messageText,
      icon: iconType,
      showCancelButton: true,
      confirmButtonColor: "#d33", // Red for destructive actions
      cancelButtonColor: "#3085d6", // Blue for cancel
      confirmButtonText: "确定", // "Confirm"
      cancelButtonText: "取消", // "Cancel"
    }).then((result) => {
      if (result.isConfirmed) {
        submitWebSettingForm(actionValue);
      }
    });
  } else {
    // Fallback for standard browser confirm
    if (confirm(messageText)) {
      submitWebSettingForm(actionValue);
    }
  }
}

/**
 * Helper to update hidden input and submit the form
 */
function submitWebSettingForm(actionValue) {
  var actionInput = document.getElementById("action_type_input");
  var form = document.getElementById("webSettingsForm");

  if (actionInput && form) {
    actionInput.value = actionValue;
    form.submit();
  } else {
    console.error("Form or Action Input not found!");
  }
}

// Function to calculate relative luminance for WCAG contrast checking
function getLuminance(hex) {
  var rgb = hex
    .substring(1)
    .match(/.{2}/g)
    .map((x) => {
      var s = parseInt(x, 16) / 255;
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    });
  return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
}

function checkContrast() {
  const bg = document.querySelector('input[name="theme_bg_color"]').value;
  const text = document.querySelector('input[name="theme_text_color"]').value;

  const L1 = getLuminance(bg);
  const L2 = getLuminance(text);
  const ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);

  if (ratio < 4.5) {
    // Use SweetAlert to warn user if contrast is poor
    Swal.fire({
      title: "易用性提示",
      text:
        "背景色与文字颜色的对比度较低 (" +
        ratio.toFixed(2) +
        ":1)，可能导致阅读困难。建议最小对比度为 4.5:1。",
      icon: "info",
    });
  }
}

// Attach listeners to color pickers
document
  .querySelector('input[name="theme_bg_color"]')
  .addEventListener("change", checkContrast);
document
  .querySelector('input[name="theme_text_color"]')
  .addEventListener("change", checkContrast);
