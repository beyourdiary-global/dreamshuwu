// Path: src/assets/js/author.js

$(document).ready(function () {
  // Function to handle Image Previews instantly upon selection
  function setupImagePreview(inputId, previewBoxId) {
    $("#" + inputId).on("change", function (e) {
      var file = e.target.files[0];
      if (file) {
        if (!file.type.match("image.*")) {
          Swal.fire("错误", "请选择有效的图片文件 (JPG/PNG)", "error");
          $(this).val("");
          return;
        }

        var box = $("#" + previewBoxId);
        box.css("border", "");
        box.siblings(".custom-file-error").remove();

        var reader = new FileReader();
        reader.onload = function (evt) {
          box.find("img").remove();
          box.append('<img src="' + evt.target.result + '">');
        };
        reader.readAsDataURL(file);
      }
    });
  }

  setupImagePreview("id_photo_front", "box_id_front");
  setupImagePreview("id_photo_back", "box_id_back");
  setupImagePreview("avatar_input", "box_avatar");

  // --- [NEW] REAL-TIME VALIDATION ---

  // 1. Instantly check IC and Phone for alphabets/symbols as the user types
  $("input[name='id_number'], input[name='contact_phone']").on(
    "input blur",
    function () {
      var val = $(this).val();
      var feedbackDiv = $(this).siblings(".invalid-feedback");
      var labelText = $(this).siblings("label").text().replace("*", "").trim();

      // Test if there's any non-numeric character
      if (val.length > 0 && !/^\d+$/.test(val)) {
        $(this).addClass("is-invalid");
        feedbackDiv.text(labelText + "格式错误，仅限输入数字 (Numbers only)");
      } else {
        $(this).removeClass("is-invalid");
      }
    },
  );

  // 2. Validate Email format on blur (when they click away)
  $("input[name='contact_email']").on("blur input", function (e) {
    var val = $(this).val();
    var feedbackDiv = $(this).siblings(".invalid-feedback");

    // Only yell at them for formatting if they clicked away or are trying to fix it
    if (e.type === "blur" && val.length > 0 && !this.checkValidity()) {
      $(this).addClass("is-invalid");
      feedbackDiv.text("电子邮箱格式不正确，请输入有效的邮箱");
    } else if (this.checkValidity()) {
      $(this).removeClass("is-invalid");
    }
  });

  // 3. Clear basic empty errors when user starts typing
  $("input.form-control, textarea.form-control")
    .not(
      "input[name='id_number'], input[name='contact_phone'], input[name='contact_email']",
    )
    .on("input", function () {
      $(this).removeClass("is-invalid");
    });

  // --- FORM SUBMISSION VALIDATION ---
  $("#btnSubmitForm").on("click", function () {
    var formElement = document.getElementById("authorRegForm");
    var isValid = true;

    $(".id-photo-box").css("border", "");
    $(".custom-file-error").remove();

    // Check all text/email/tel inputs
    $(formElement)
      .find("input.form-control, textarea.form-control, select.form-control")
      .each(function () {
        if (!this.checkValidity()) {
          $(this).addClass("is-invalid");
          isValid = false;

          // Smart Error Messages
          var feedbackDiv = $(this).siblings(".invalid-feedback");
          var labelText = $(this)
            .siblings("label")
            .text()
            .replace("*", "")
            .trim();

          if (this.validity.valueMissing) {
            feedbackDiv.text("请填写" + labelText);
          } else if (this.validity.patternMismatch) {
            if (this.name === "id_number" || this.name === "contact_phone") {
              feedbackDiv.text(
                labelText + "格式错误，仅限输入数字 (Numbers only)",
              );
            }
          } else if (this.validity.typeMismatch && this.type === "email") {
            feedbackDiv.text("电子邮箱格式不正确，请输入有效的邮箱");
          }
        }
      });

    // Check custom hidden file inputs
    var checkFile = function (inputId, boxId, errMsg) {
      var el = document.getElementById(inputId);
      if (el && el.hasAttribute("required") && !el.value) {
        var box = $("#" + boxId);
        box.css("border", "2px dashed #dc3545");
        if (box.siblings(".custom-file-error").length === 0) {
          box.after(
            '<div class="text-danger mt-1 custom-file-error" style="font-size: 0.875em;">' +
              errMsg +
              "</div>",
          );
        }
        isValid = false;
      }
    };

    checkFile("id_photo_front", "box_id_front", "请上传正面身份证照片");
    checkFile("id_photo_back", "box_id_back", "请上传反面身份证照片");

    // Stop and scroll to error if invalid
    if (!isValid) {
      var firstInvalid = $(formElement)
        .find(".is-invalid, .custom-file-error")
        .first();
      if (firstInvalid.length) {
        var target = firstInvalid.hasClass("custom-file-error")
          ? firstInvalid.prev()
          : firstInvalid;
        $("html, body").animate({ scrollTop: target.offset().top - 100 }, 300);
      }
      return;
    }

    var originalData = formElement.dataset.originalData;
    if (originalData) {
      var currentData = new URLSearchParams(
        new FormData(formElement),
      ).toString();
      var hasFile = false;
      $(formElement)
        .find('input[type="file"]')
        .each(function () {
          if (this.files.length > 0) hasFile = true;
        });

      if (!hasFile && originalData === currentData) {
        Swal.fire({
          icon: "warning",
          title: "没有修改",
          text: "无需保存",
          timer: 2000,
          showConfirmButton: false,
        });
        return;
      }
    }

    var btn = $(this);
    var originalHtml = btn.html();

    btn
      .prop("disabled", true)
      .html('<i class="fa-solid fa-spinner fa-spin me-2"></i> 提交中...');

    var formData = new FormData(formElement);

    $.ajax({
      url: window.location.href,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (response) {
        if (response.success) {
          Swal.fire({
            title: "成功!",
            text: response.message,
            icon: "success",
            confirmButtonColor: "#233dd2",
          }).then(() => {
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
