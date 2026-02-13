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
