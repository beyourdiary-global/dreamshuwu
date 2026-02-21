// Path: src/assets/js/author.js

$(document).ready(function () {
  // Function to handle Image Previews instantly upon selection
  function setupImagePreview(inputId, previewBoxId) {
    $("#" + inputId).on("change", function (e) {
      var file = e.target.files[0];
      if (file) {
        // Simple front-end validation for type
        if (!file.type.match("image.*")) {
          Swal.fire("错误", "请选择有效的图片文件 (JPG/PNG)", "error");
          $(this).val("");
          return;
        }

        var reader = new FileReader();
        reader.onload = function (evt) {
          var box = $("#" + previewBoxId);
          // Remove old image if exists
          box.find("img").remove();
          // Append new image overlay
          box.append('<img src="' + evt.target.result + '">');
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Attach listeners
  setupImagePreview("id_photo_front", "box_id_front");
  setupImagePreview("id_photo_back", "box_id_back");
  setupImagePreview("avatar_input", "box_avatar");

  // Submit Action using standard JSON API (AJAX)
  $("#btnSubmitForm").on("click", function () {
    var formElement = document.getElementById("authorRegForm");

    // Basic HTML5 Validity Check
    if (!formElement.checkValidity()) {
      formElement.reportValidity();
      return;
    }

    var btn = $(this);
    var originalHtml = btn.html();

    // Show a loading state to prevent double-clicking
    btn
      .prop("disabled", true)
      .html('<i class="fa-solid fa-spinner fa-spin me-2"></i> 提交中...');

    // Use FormData to handle text fields + file uploads automatically
    var formData = new FormData(formElement);

    $.ajax({
      url: window.location.href, // Send to the current PHP file
      type: "POST",
      data: formData,
      processData: false, // Required for FormData
      contentType: false, // Required for FormData
      dataType: "json", // Expect a standard JSON response
      success: function (response) {
        if (response.success) {
          Swal.fire({
            title: "成功!",
            text: response.message,
            icon: "success",
            confirmButtonColor: "#233dd2",
          }).then(() => {
            // Reload the page to show the "Pending" status alert
            window.location.reload();
          });
        } else {
          Swal.fire("错误", response.message, "error");
          btn.prop("disabled", false).html(originalHtml);
        }
      },
      error: function (xhr, status, error) {
        Swal.fire("系统错误", "无法连接到服务器，请稍后再试。", "error");
        btn.prop("disabled", false).html(originalHtml);
        console.error("API Error:", error);
      },
    });
  });
});
