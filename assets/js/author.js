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

  // --- REAL-TIME VALIDATION ---

  // 1. Instantly check IC and Phone for alphabets/symbols as the user types
  $("input[name='id_number'], input[name='contact_phone']").on(
    "input blur",
    function () {
      var val = $(this).val();
      var feedbackDiv = $(this).siblings(".invalid-feedback");
      var labelText = $(this).siblings("label").text().replace("*", "").trim();

      if (val.length > 0 && !/^\d+$/.test(val)) {
        $(this).addClass("is-invalid");
        feedbackDiv.text(labelText + "格式错误，仅限输入数字 (Numbers only)");
      } else {
        $(this).removeClass("is-invalid");
      }
    },
  );

  // 2. Validate Email format on blur
  $("input[name='contact_email']").on("blur input", function (e) {
    var val = $(this).val();
    var feedbackDiv = $(this).siblings(".invalid-feedback");

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

    $(formElement)
      .find("input.form-control, textarea.form-control, select.form-control")
      .each(function () {
        if (!this.checkValidity()) {
          $(this).addClass("is-invalid");
          isValid = false;

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
      },
    });
  });
});

// ========================================================
// --- NOVEL MANAGEMENT LOGIC ---
// ========================================================
$(document).ready(function () {
  // Grab the main container element
  const appContainer = $("#novelManagementApp");

  // If the container doesn't exist, we are not on the Novel Management page, so exit
  if (appContainer.length === 0) return;

  // Read configuration and permissions from data attributes cleanly
  const API_URL = appContainer.data("api-url");
  const CAN_EDIT = appContainer.data("can-edit") === 1;
  const CAN_DELETE = appContainer.data("can-delete") === 1;

  let novelTable;

  // Function to load dynamic tags for either Create form or Edit Modal
  function loadDynamicTags(
    catId,
    containerObj,
    checkedTagsArray = [],
    isViewMode = false,
  ) {
    if (!catId) {
      containerObj.html(
        '<span class="text-muted small">请先选择分类 (Please select a category first)</span>',
      );
      return;
    }

    containerObj.html(
      '<i class="fa-solid fa-spinner fa-spin text-primary"></i> <span class="small text-muted">正在加载标签...</span>',
    );

    $.ajax({
      url: API_URL,
      type: "GET",
      data: { mode: "get_tags", category_id: catId },
      dataType: "json",
      success: function (res) {
        if (res.success && res.data.length > 0) {
          let html = "";
          res.data.forEach((tag) => {
            // Check if tag is in pre-selected array
            const isChecked = checkedTagsArray.includes(tag.name)
              ? "checked"
              : "";
            const isDisabled = isViewMode ? "disabled" : "";
            // Unique ID to avoid collision between Create and Edit forms
            const uniqueId =
              "tag_" + Math.random().toString(36).substr(2, 9) + "_" + tag.id;

            html += `
                <div class="d-inline-flex align-items-center bg-white border rounded py-1 px-3 me-2 mb-2 hover-shadow-sm transition-all">
                    <input class="form-check-input m-0 me-2 tag-checkbox flex-shrink-0" type="checkbox" name="tags[]" id="${uniqueId}" value="${tag.name}" ${isChecked} ${isDisabled} style="cursor: pointer;">
                    <label class="form-check-label small user-select-none m-0" for="${uniqueId}" style="cursor: pointer;">${tag.name}</label>
                </div>
            `;
          });
          containerObj.html(html);
        } else {
          containerObj.html(
            '<span class="text-muted small">此分类暂无关联标签。</span>',
          );
        }
      },
    });
  }

  // 1. Fetch Dynamic Tags based on Category (CREATE FORM)
  $("#categorySelect").on("change", function () {
    loadDynamicTags($(this).val(), $("#dynamicTagsContainer"));
  });

  // 2. Fetch Dynamic Tags based on Category (EDIT MODAL)
  $("#modalCategorySelect").on("change", function () {
    loadDynamicTags($(this).val(), $("#modalDynamicTagsContainer"));
  });

  // 3. Limit Tag Selection to Max 10 (Global)
  $(document).on("change", ".tag-checkbox", function () {
    // Scope checking to closest form
    const formContext = $(this).closest("form");
    if (formContext.find(".tag-checkbox:checked").length > 10) {
      this.checked = false;
      Swal.fire({
        icon: "warning",
        title: "限制",
        text: "每本小说最多只能选择 10 个标签",
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
      });
    }
  });

  // 4. Fetch Statistics
  function loadNovelStats() {
    $.ajax({
      url: API_URL,
      type: "GET",
      data: { mode: "stats" },
      dataType: "json",
      success: function (res) {
        if (res.success && res.data) {
          $("#statTotalNovels").text(res.data.total);
          $("#statOngoingNovels").text(res.data.ongoing);
          $("#statCompletedNovels").text(res.data.completed);
        }
      },
    });
  }

  // 5. Initialize DataTables
  function initNovelTable() {
    novelTable = $("#novelTable").DataTable({
      processing: true,
      serverSide: true,
      responsive: true,
      ajax: {
        url: API_URL,
        type: "POST",
        data: function (d) {
          d.mode = "data";
        },
      },
      columns: [
        {
          data: "cover_image",
          orderable: false,
          searchable: false,
          render: function (data) {
            return `<img src="${data}" style="width: 50px; height: 66px; object-fit: cover; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">`;
          },
        },
        { data: "title", className: "fw-bold text-dark" },
        { data: "category_name" },
        {
          data: "tags",
          render: function (data) {
            if (!data) return "";
            let tags = data.split(",");
            let html = "";
            tags.forEach((tag) => {
              if (tag.trim() !== "")
                html += `<span class="badge bg-light text-secondary border me-1 mb-1">${tag.trim()}</span>`;
            });
            return html;
          },
        },
        {
          data: "completion_status",
          render: function (data) {
            if (data === "ongoing")
              return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">连载中</span>';
            if (data === "completed")
              return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">已完结</span>';
            return data;
          },
        },
        { data: "created_at", className: "text-muted small" },
        {
          data: "id",
          orderable: false,
          searchable: false,
          className: "text-center",
          render: function (data) {
            let buttons = '<div class="d-flex justify-content-center gap-2">';

            // [NEW] 管理章节按钮 (Chapter Management Entry Point)
            buttons += `<a href="/author/novel/${data}/chapters/" class="btn btn-sm btn-outline-success" title="管理章节 (Manage Chapters)"><i class="fa-solid fa-list-ol"></i></a>`;

            // View button is generally available to anyone who can access the page
            buttons += `<button class="btn btn-sm btn-outline-info btn-view-novel" data-id="${data}" title="查看 (View)"><i class="fa-solid fa-eye"></i></button>`;

            // Edit button permission check
            if (CAN_EDIT) {
              buttons += `<button class="btn btn-sm btn-outline-primary btn-edit-novel" data-id="${data}" title="编辑 (Edit)"><i class="fa-solid fa-pen"></i></button>`;
            }

            // Delete button permission check
            if (CAN_DELETE) {
              buttons += `<button class="btn btn-sm btn-outline-danger btn-delete-novel" data-id="${data}" title="删除 (Delete)"><i class="fa-solid fa-trash"></i></button>`;
            }

            buttons += "</div>";
            return buttons;
          },
        },
      ],

      // Hardcode Chinese language pack
      language: {
        processing: "处理中...",
        lengthMenu: "显示 _MENU_ 项",
        zeroRecords: "没有匹配结果",
        info: "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
        infoEmpty: "显示第 0 至 0 项结果，共 0 项",
        infoFiltered: "(由 _MAX_ 项结果过滤)",
        infoPostFix: "",
        search: "搜索:",
        url: "",
        emptyTable: "表中数据为空",
        loadingRecords: "载入中...",
        infoThousands: ",",
        paginate: {
          first: "首页",
          previous: "上页",
          next: "下页",
          last: "末页",
        },
        aria: {
          sortAscending: ": 以升序排列此列",
          sortDescending: ": 以降序排列此列",
        },
      },
    });
  }

  // 6. Image Preview & Validation (Both Forms)
  $("#cover_image_input, #modal_cover_image_input").on("change", function (e) {
    var file = e.target.files[0];
    var isModal = $(this).attr("id") === "modal_cover_image_input";
    var boxId = isModal ? "#modal_box_cover_image" : "#box_cover_image";
    var errId = isModal ? "#modalCoverError" : "#coverError";

    if (file) {
      if (!file.type.match("image.*") || file.size > 2 * 1024 * 1024) {
        Swal.fire("错误", "请选择2MB以内的JPG/PNG图片", "error");
        $(this).val("");
        return;
      }

      var box = $(boxId);
      box.removeClass("border-danger border-2");
      $(errId).addClass("d-none");

      box.css("border-color", "#233dd2");
      var reader = new FileReader();
      reader.onload = function (evt) {
        box.find("img, .placeholder").remove(); // Remove icon/placeholder
        box.append(
          `<img src="${evt.target.result}" style="border-radius:6px; position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; z-index:2;">`,
        );
      };
      reader.readAsDataURL(file);
    }
  });

  // 7. Submit Novel (CREATE FORM)
  $("#btnSubmitNovel").on("click", function () {
    const form = document.getElementById("novelForm");
    let isValid = true;

    // Reset error states
    $(form).find(".is-invalid").removeClass("is-invalid");
    $("#tagsError").addClass("d-none");
    $("#coverError").addClass("d-none");
    $("#box_cover_image").removeClass("border-danger border-2");

    $(form)
      .find("input[required], select[required], textarea[required]")
      .each(function () {
        if (this.type === "checkbox") {
          if (!this.checked) {
            $(this).addClass("is-invalid");
            isValid = false;
          }
        } else if (!this.value.trim()) {
          $(this).addClass("is-invalid");
          isValid = false;
        }
      });

    if (
      $(form).find(".tag-checkbox").length > 0 &&
      $(form).find(".tag-checkbox:checked").length === 0
    ) {
      $("#tagsError").removeClass("d-none");
      isValid = false;
    }

    if ($("#cover_image_input")[0].files.length === 0) {
      $("#coverError").removeClass("d-none");
      $("#box_cover_image").addClass("border-danger border-2");
      isValid = false;
    }

    if (!isValid) return;

    const btn = $(this);
    const originalText = btn.html();
    btn
      .prop("disabled", true)
      .html('<i class="fa-solid fa-spinner fa-spin me-2"></i> 提交中...');

    $.ajax({
      url: API_URL,
      type: "POST",
      data: new FormData(form),
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (res) {
        if (res.success) {
          Swal.fire({
            icon: "success",
            title: "发布成功!",
            text: res.message,
            confirmButtonColor: "#233dd2",
          }).then(() => {
            form.reset();
            $(form).find(".is-invalid").removeClass("is-invalid");
            $("#box_cover_image img").remove();
            $("#box_cover_image").append(
              '<div class="placeholder"><i class="fa-solid fa-cloud-arrow-up fa-2x mb-2"></i><br>点击上传<br>(JPG/PNG, 建议3:4)</div>',
            );
            $("#box_cover_image").css("border-color", "");
            $("#dynamicTagsContainer").html(
              '<span class="text-muted small">请先选择分类 (Please select a category first)</span>',
            );
            loadNovelStats();
            if (novelTable) novelTable.ajax.reload(null, false);
          });
        } else {
          Swal.fire("发布失败", res.message, "error");
        }
      },
      complete: function () {
        btn.prop("disabled", false).html(originalText);
      },
    });
  });

  // 8. Open EDIT/VIEW Modal & Populate Data
  $("#novelTable").on("click", ".btn-edit-novel, .btn-view-novel", function () {
    const novelId = $(this).data("id");
    const isViewMode = $(this).hasClass("btn-view-novel");
    const modal = $("#novelModal");
    const form = $("#novelModalForm")[0];

    // Reset Form & UI
    form.reset();
    $(form).find(".is-invalid").removeClass("is-invalid");
    $("#modalTagsError").addClass("d-none");

    // Show Loading state on modal fields
    $("#modal_title").val("加载中...");
    $("#modal_introduction").val("加载中...");

    $.ajax({
      url: API_URL,
      type: "GET",
      data: { mode: "get_novel", id: novelId },
      dataType: "json",
      success: function (res) {
        if (res.success) {
          const novel = res.data;

          // Populate Fields
          $("#modal_novel_id").val(novel.id);
          $("#modal_title").val(novel.title);
          $("#modalCategorySelect").val(novel.category_id);
          $("#modal_introduction").val(novel.introduction);
          $("#modal_completion_status").val(novel.completion_status);

          // Fetch tags and pre-select them based on novel.tags array
          loadDynamicTags(
            novel.category_id,
            $("#modalDynamicTagsContainer"),
            novel.tags,
            isViewMode,
          );

          // Setup Cover Image Box
          const coverBox = $("#modal_box_cover_image");
          coverBox.find("img, .placeholder").remove();
          if (novel.cover_image) {
            coverBox.append(
              `<img src="${novel.cover_image}" style="border-radius:6px; position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; z-index:2;">`,
            );
          } else {
            coverBox.append(
              '<div class="placeholder"><i class="fa-solid fa-cloud-arrow-up fa-2x mb-2"></i><br>暂无封面</div>',
            );
          }

          // Handle Access Mode UI (View vs Edit)
          $(form).find(":input").prop("disabled", isViewMode);
          $(form)
            .find('input[type="hidden"], button[data-bs-dismiss="modal"]')
            .prop("disabled", false);
          if (isViewMode) {
            $("#btnUpdateNovel").hide();
            modal.find(".modal-title").text("小说详情 (Novel Details)");
            modal.find(".req-star").hide();
            $("#modal_box_cover_image").addClass("pe-none"); // Prevent image click
          } else {
            // Re-enable necessary hidden inputs that were disabled by the blanket rule
            $(form)
              .find('input[type="hidden"], .btn-close, .btn-secondary')
              .prop("disabled", false);
            $("#btnUpdateNovel").show().prop("disabled", false);
            modal.find(".modal-title").text("编辑小说 (Edit Novel)");
            modal.find(".req-star").show();
            $("#modal_box_cover_image").removeClass("pe-none");
          }

          modal.modal("show");
        } else {
          Swal.fire("加载失败", res.message, "error");
        }
      },
      error: function () {
        Swal.fire("错误", "网络请求失败，请稍后重试", "error");
      },
    });
  });

  // 9. Submit Novel Update (EDIT MODAL)
  $("#btnUpdateNovel").on("click", function () {
    const form = document.getElementById("novelModalForm");
    let isValid = true;

    $(form).find(".is-invalid").removeClass("is-invalid");
    $("#modalTagsError").addClass("d-none");

    $(form)
      .find("input[required], select[required], textarea[required]")
      .each(function () {
        if (!this.value.trim()) {
          $(this).addClass("is-invalid");
          isValid = false;
        }
      });

    if (
      $(form).find(".tag-checkbox").length > 0 &&
      $(form).find(".tag-checkbox:checked").length === 0
    ) {
      $("#modalTagsError").removeClass("d-none");
      isValid = false;
    }

    if (!isValid) return;

    const btn = $(this);
    const originalText = btn.html();
    btn
      .prop("disabled", true)
      .html('<i class="fa-solid fa-spinner fa-spin me-2"></i> 保存中...');

    $.ajax({
      url: API_URL,
      type: "POST",
      data: new FormData(form),
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (res) {
        if (res.success) {
          Swal.fire({
            icon: "success",
            title: "更新成功!",
            text: res.message,
            timer: 2000,
            showConfirmButton: false,
          }).then(() => {
            $("#novelModal").modal("hide");
            if (novelTable) novelTable.ajax.reload(null, false);
          });
        } else if (res.is_warning) {
          // [NEW] Trigger the specific "No modifications" warning popup
          Swal.fire({
            icon: "warning",
            title: "没有修改",
            text: res.message, // Uses '无需保存' from the API
            timer: 2000,
            showConfirmButton: false,
          });
        } else {
          Swal.fire("更新失败", res.message, "error");
        }
      },
      complete: function () {
        btn.prop("disabled", false).html(originalText);
      },
    });
  });

  // 10. Soft Delete
  $("#novelTable").on("click", ".btn-delete-novel", function () {
    const novelId = $(this).data("id");
    const csrfToken = $('input[name="csrf_token"]').val();

    Swal.fire({
      title: "确认删除？",
      text: "此操作不可恢复，小说将被移至回收站。",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#dc3545",
      confirmButtonText: "确认删除",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: API_URL,
          type: "POST",
          data: { mode: "delete", id: novelId, csrf_token: csrfToken },
          dataType: "json",
          success: function (res) {
            if (res.success) {
              Swal.fire({
                icon: "success",
                title: "已删除",
                text: res.message,
                timer: 2000,
                showConfirmButton: false,
              });
              loadNovelStats();
              if (novelTable) novelTable.ajax.reload(null, false);
            } else {
              Swal.fire("错误", res.message, "error");
            }
          },
        });
      }
    });
  });

  loadNovelStats();
  initNovelTable();
});
