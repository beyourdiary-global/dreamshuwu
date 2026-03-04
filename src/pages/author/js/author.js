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
        if (typeof window.showNoChangeWarning === "function") {
          window.showNoChangeWarning("无需保存");
        }
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
            const isChecked = checkedTagsArray.includes(parseInt(tag.id))
              ? "checked"
              : "";
            const isDisabled = isViewMode ? "disabled" : "";

            // Unique ID to avoid collision between Create and Edit forms
            const uniqueId =
              "tag_" + Math.random().toString(36).substr(2, 9) + "_" + tag.id;

            html += `
                <div class="d-inline-flex align-items-center bg-white border rounded py-1 px-3 me-2 mb-2 hover-shadow-sm transition-all">
                    <input class="form-check-input m-0 me-2 tag-checkbox flex-shrink-0" type="checkbox" name="tags[]" id="${uniqueId}" value="${tag.id}" ${isChecked} ${isDisabled} style="cursor: pointer;">
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
      responsive: false, // Disabled native responsive for custom mobile row details
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

  loadNovelStats();
  initNovelTable();

  // --- MOBILE RESPONSIVE LOGIC FOR NOVEL TABLE ---
  let isNovelMobile = null; // State tracker

  function applyNovelMobileMode() {
    if (!novelTable) return;
    const isMobileOrTablet =
      document.body.classList.contains("is-mobile") ||
      document.body.classList.contains("is-tablet");

    if (isNovelMobile === isMobileOrTablet) return; // Prevent destroy bug
    isNovelMobile = isMobileOrTablet;

    novelTable.column(2).visible(!isMobileOrTablet, false); // Hide Category
    novelTable.column(3).visible(!isMobileOrTablet, false); // Hide Tags
    novelTable.column(5).visible(!isMobileOrTablet, false); // Hide Date
    novelTable.column(6).visible(!isMobileOrTablet, false); // Hide Actions
    novelTable.columns.adjust().draw(false);
  }

  applyNovelMobileMode();
  let novelResizeTimer;
  $(window).on("resize", function () {
    clearTimeout(novelResizeTimer);
    novelResizeTimer = setTimeout(applyNovelMobileMode, 120);
  });

  // Handle Mobile/Tablet Row Clicks
  $("#novelTable tbody").on("click", "td", function (e) {
    if ($(e.target).closest("a, button, .btn, input, select").length) return;

    const tr = $(this).closest("tr");
    if (tr.hasClass("child")) return;

    const isMobileOrTablet =
      document.body.classList.contains("is-mobile") ||
      document.body.classList.contains("is-tablet");
    if (!isMobileOrTablet) return;

    const row = novelTable.row(tr);
    if (!row.data()) return;

    if (row.child.isShown()) {
      row.child.hide();
      tr.removeClass("shown");
    } else {
      const d = row.data();
      let childHtml =
        '<div class="mobile-row-details p-3 bg-light border rounded my-3 shadow-sm">';
      childHtml +=
        '<div class="mb-2"><strong>分类：</strong> ' +
        authorEscapeHtml(d.category_name || "-") +
        "</div>";

      let tagsHtml = "";
      if (d.tags) {
        let tags = d.tags.split(",");
        tags.forEach((tag) => {
          if (tag.trim() !== "")
            tagsHtml +=
              '<span class="badge bg-light text-secondary border border-secondary border-opacity-25 me-1 mb-1">' +
              tag.trim() +
              "</span>";
        });
      }
      childHtml +=
        '<div class="mb-2"><strong>标签：</strong> ' +
        (tagsHtml || "-") +
        "</div>";
      childHtml +=
        '<div class="mb-3 text-muted small"><strong>创建时间：</strong> ' +
        authorEscapeHtml(d.created_at || "-") +
        "</div>";

      const canEdit =
        appContainer.data("can-edit") == 1 ||
        appContainer.data("can-edit") === true ||
        appContainer.data("can-edit") === "1";
      const canDelete =
        appContainer.data("can-delete") == 1 ||
        appContainer.data("can-delete") === true ||
        appContainer.data("can-delete") === "1";

      let actions =
        '<div class="d-flex gap-2 flex-wrap justify-content-start w-100 mt-3 pt-3 border-top">';
      actions +=
        '<a href="/author/novel/' +
        d.id +
        '/chapters/" class="btn btn-sm btn-outline-success flex-fill"><i class="fa-solid fa-list-ol"></i> 章节</a>';
      actions +=
        '<button class="btn btn-sm btn-outline-info flex-fill btn-view-novel" data-id="' +
        d.id +
        '"><i class="fa-solid fa-eye"></i> 查看</button>';
      if (canEdit)
        actions +=
          '<button class="btn btn-sm btn-outline-primary flex-fill btn-edit-novel" data-id="' +
          d.id +
          '"><i class="fa-solid fa-pen"></i> 编辑</button>';
      if (canDelete)
        actions +=
          '<button class="btn btn-sm btn-outline-danger flex-fill btn-delete-novel" data-id="' +
          d.id +
          '"><i class="fa-solid fa-trash"></i> 删除</button>';
      actions += "</div></div>";

      row.child(childHtml).show();
      tr.addClass("shown");
    }
  });

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
      data: { mode: "get_novel", novel_id: novelId },
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
          if (typeof window.showNoChangeWarning === "function") {
            window.showNoChangeWarning(res.message || "无需保存");
          }
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
          data: { mode: "delete", novel_id: novelId, csrf_token: csrfToken },
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
});

function authorEscapeHtml(text) {
  var value = text == null ? "" : String(text);
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function initAuthorVerificationModule() {
  var appRoot = document.getElementById("authorVerificationApp");
  var tableEl = document.getElementById("authorVerificationTable");
  var form = document.getElementById("authorVerifyFilterForm");
  if (
    !appRoot ||
    !tableEl ||
    !form ||
    typeof jQuery === "undefined" ||
    !jQuery.fn.DataTable
  )
    return false;
  if (tableEl.dataset.dtReady === "1") return true;

  var apiUrl = appRoot.getAttribute("data-api-url") || "";
  var csrfToken = appRoot.getAttribute("data-csrf") || "";
  if (!apiUrl) return false;

  var canApprove = appRoot.getAttribute("data-can-approve") === "1";
  var canReject = appRoot.getAttribute("data-can-reject") === "1";
  var canResend = appRoot.getAttribute("data-can-resend") === "1";
  var canDelete = appRoot.getAttribute("data-can-delete") === "1";

  var searchInput = form.querySelector('input[name="search"]');
  var perPageSelect = form.querySelector('select[name="per_page"]');
  var statusSelect = form.querySelector('select[name="status_filter"]');
  var modalEl = document.getElementById("authorVerifyActionModal");
  var actionForm = document.getElementById("authorVerifyActionForm");
  var actionTypeSelect = actionForm
    ? actionForm.querySelector('select[name="action_type"]')
    : null;
  var rejectWrap = document.getElementById("authorVerifyRejectReasonWrap");
  var rejectInput = actionForm
    ? actionForm.querySelector('textarea[name="reject_reason"]')
    : null;
  var idInput = actionForm
    ? actionForm.querySelector('input[name="id"]')
    : null;
  var dashboardPanel = document.getElementById("authorVerifyDashboardPanel");
  var toggleBtn = document.getElementById("toggleAuthorVerifyDashboard");

  var modal = null;
  if (typeof bootstrap !== "undefined" && bootstrap.Modal && modalEl) {
    modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  }

  var verifyDebugEnabled = /(?:\?|&)verifyDebug=1(?:&|$)/.test(
    window.location.search,
  );
  var verifyDebugPanel = null;

  function ensureVerifyDebugPanel() {
    if (!verifyDebugEnabled || verifyDebugPanel) return;
    verifyDebugPanel = document.createElement("div");
    verifyDebugPanel.id = "verifyDebugPanel";
    verifyDebugPanel.style.cssText =
      "position:fixed;right:10px;bottom:70px;z-index:99999;max-width:320px;max-height:40vh;overflow:auto;background:#111;color:#0f0;padding:10px;border-radius:8px;font:12px/1.4 monospace;box-shadow:0 6px 16px rgba(0,0,0,.35);";
    verifyDebugPanel.innerHTML =
      '<div style="color:#fff;margin-bottom:6px;font-weight:bold;">Verify Debug</div>';
    document.body.appendChild(verifyDebugPanel);
  }

  function verifyDebugLog(message, payload) {
    if (!verifyDebugEnabled) return;
    ensureVerifyDebugPanel();
    if (!verifyDebugPanel) return;
    var line = document.createElement("div");
    var stamp = new Date().toLocaleTimeString();
    var text = "[" + stamp + "] " + String(message || "");
    if (payload !== undefined) {
      try {
        text += " | " + JSON.stringify(payload);
      } catch (err) {
        text += " | [payload unserializable]";
      }
    }
    line.textContent = text;
    verifyDebugPanel.appendChild(line);
    verifyDebugPanel.scrollTop = verifyDebugPanel.scrollHeight;
  }

  if (verifyDebugEnabled) {
    ensureVerifyDebugPanel();
    verifyDebugLog("debug enabled", {
      width: window.innerWidth,
      bodyClass: document.body.className,
    });
  }

  function updateRejectReasonUI() {
    if (!actionTypeSelect || !rejectWrap || !rejectInput) return;
    var actionType = actionTypeSelect.value;
    if (actionType === "reject") {
      rejectWrap.style.display = "block";
      rejectInput.setAttribute("required", "required");
    } else {
      rejectWrap.style.display = "none";
      rejectInput.removeAttribute("required");
    }
  }

  var table = jQuery(tableEl).DataTable({
    processing: true,
    serverSide: true,
    searching: true,
    ordering: false,
    lengthChange: false,
    responsive: false, // Disabled native responsive for custom mobile row details
    pageLength: parseInt(perPageSelect ? perPageSelect.value : "10", 10) || 10,
    ajax: {
      url: apiUrl,
      type: "GET",
      data: function (d) {
        d.mode = "data";
        d.status_filter = statusSelect
          ? statusSelect.value
          : "pending,rejected";
      },
    },
    columns: [
      {
        data: null,
        render: function (data, type, row, meta) {
          var start = meta && meta.settings ? meta.settings._iDisplayStart : 0;
          return start + meta.row + 1;
        },
      },
      {
        data: null,
        render: function (data) {
          return (
            "<div>" +
            authorEscapeHtml(data.user_name || "-") +
            "</div><small class='text-muted'>UID: " +
            (data.user_id || 0) +
            "</small>"
          );
        },
      },
      {
        data: "real_name",
        render: function (data) {
          return authorEscapeHtml(data || "-");
        },
      },
      {
        data: "pen_name",
        render: function (data) {
          return authorEscapeHtml(data || "-");
        },
      },
      {
        data: "verification_status",
        render: function (data) {
          var s = (data || "").toLowerCase();
          if (s === "approved")
            return '<span class="badge bg-success">已通过</span>';
          if (s === "rejected")
            return '<span class="badge bg-danger">已驳回</span>';
          return '<span class="badge bg-warning text-dark">待审核</span>';
        },
      },
      {
        data: "reject_reason",
        render: function (data) {
          return authorEscapeHtml(data || "-");
        },
      },
      {
        data: "email_notify_count",
        render: function (data) {
          return parseInt(data || "0", 10);
        },
      },
      {
        data: "updated_at",
        render: function (data) {
          return authorEscapeHtml(data || "-");
        },
      },
      {
        data: null,
        className: "text-center",
        render: function (data) {
          var id = parseInt(data.id || 0, 10);
          var reason = authorEscapeHtml(data.reject_reason || "");
          var html = "";
          if (canApprove)
            html +=
              '<button type="button" class="btn btn-sm btn-outline-success me-1 btn-author-action" data-id="' +
              id +
              '" data-action="approve" title="通过">通过</button>';
          if (canReject)
            html +=
              '<button type="button" class="btn btn-sm btn-outline-danger me-1 btn-author-action" data-id="' +
              id +
              '" data-action="reject" data-reason="' +
              reason +
              '" title="驳回">驳回</button>';
          if (canResend)
            html +=
              '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-author-action" data-id="' +
              id +
              '" data-action="resend" title="重发通知">重发</button>';
          if (canDelete)
            html +=
              '<button type="button" class="btn btn-sm btn-outline-secondary btn-author-delete" data-id="' +
              id +
              '" title="软删除">删除</button>';
          return html === ""
            ? '<span class="text-muted small">无操作权限</span>'
            : '<div class="author-verify-actions">' + html + "</div>";
        },
      },
    ],
    dom: 'rt<"row mt-3 align-items-center"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
    language: {
      processing: "处理中...",
      zeroRecords: "没有匹配结果",
      info: "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
      infoEmpty: "显示第 0 至 0 项结果，共 0 项",
      infoFiltered: "",
      paginate: { previous: "上页", next: "下页" },
    },
  });

  // --- MOBILE RESPONSIVE LOGIC FOR VERIFICATION TABLE ---
  let isVerifyMobile = null; // State tracker

  function isVerifyCompactMode() {
    return (
      document.body.classList.contains("is-mobile") ||
      document.body.classList.contains("is-tablet") ||
      window.matchMedia("(max-width: 1024px)").matches
    );
  }

  function applyVerifyMobileMode() {
    if (!table) return;
    const isMobileOrTablet = isVerifyCompactMode();
    verifyDebugLog("applyVerifyMobileMode", {
      compact: isMobileOrTablet,
      width: window.innerWidth,
    });

    if (isVerifyMobile === isMobileOrTablet) return; // Prevent destroy bug
    isVerifyMobile = isMobileOrTablet;

    table.column(2).visible(!isMobileOrTablet, false); // Real Name
    table.column(3).visible(!isMobileOrTablet, false); // Pen Name
    table.column(5).visible(!isMobileOrTablet, false); // Reject Reason
    table.column(6).visible(!isMobileOrTablet, false); // Notify Count
    table.column(7).visible(!isMobileOrTablet, false); // Updated Time
    table.column(8).visible(!isMobileOrTablet, false); // Actions
    table.columns.adjust().draw(false);
  }

  applyVerifyMobileMode();
  let verifyResizeTimer;
  $(window).on("resize", function () {
    clearTimeout(verifyResizeTimer);
    verifyResizeTimer = setTimeout(applyVerifyMobileMode, 120);
  });

  function buildAuthorVerifyDetailHtml(d) {
    let childHtml =
      '<div class="mobile-row-details p-3 bg-light border rounded my-3 shadow-sm">';
    childHtml +=
      '<div class="mb-2"><strong>真实姓名：</strong> ' +
      authorEscapeHtml(d.real_name || "-") +
      "</div>";
    childHtml +=
      '<div class="mb-2"><strong>笔名：</strong> ' +
      authorEscapeHtml(d.pen_name || "-") +
      "</div>";

    if (d.reject_reason) {
      childHtml +=
        '<div class="mb-2 text-danger"><strong>驳回原因：</strong> ' +
        authorEscapeHtml(d.reject_reason || "-") +
        "</div>";
    }

    childHtml +=
      '<div class="mb-2"><strong>通知次数：</strong> ' +
      parseInt(d.email_notify_count || 0) +
      "</div>";
    childHtml +=
      '<div class="mb-3 text-muted small"><strong>更新时间：</strong> ' +
      authorEscapeHtml(d.updated_at || "-") +
      "</div>";

    let actions =
      '<div class="d-flex gap-2 flex-wrap justify-content-start w-100 mt-3 pt-3 border-top">';
    const id = parseInt(d.id || 0, 10);
    const reason = authorEscapeHtml(d.reject_reason || "");
    if (canApprove)
      actions +=
        '<button type="button" class="btn btn-sm btn-outline-success flex-fill btn-author-action" data-id="' +
        id +
        '" data-action="approve"><i class="fa-solid fa-check"></i> 通过</button>';
    if (canReject)
      actions +=
        '<button type="button" class="btn btn-sm btn-outline-danger flex-fill btn-author-action" data-id="' +
        id +
        '" data-action="reject" data-reason="' +
        reason +
        '"><i class="fa-solid fa-xmark"></i> 驳回</button>';
    if (canResend)
      actions +=
        '<button type="button" class="btn btn-sm btn-outline-primary flex-fill btn-author-action" data-id="' +
        id +
        '" data-action="resend"><i class="fa-solid fa-envelope"></i> 重发</button>';
    if (canDelete)
      actions +=
        '<button type="button" class="btn btn-sm btn-outline-secondary flex-fill btn-author-delete" data-id="' +
        id +
        '"><i class="fa-solid fa-trash"></i> 删除</button>';
    if (!canApprove && !canReject && !canResend && !canDelete)
      actions +=
        '<span class="text-muted small w-100 text-center py-2 bg-light rounded">无操作权限</span>';
    actions += "</div></div>";

    childHtml += actions;
    return childHtml;
  }

  // Handle Mobile/Tablet Row Clicks
  $("#authorVerificationTable tbody").on("click", "tr", function (e) {
    if ($(e.target).closest("a, button, .btn, select, input").length) return;

    const tr = $(this).closest("tr");
    if (tr.hasClass("child") || tr.hasClass("author-inline-detail-row")) return;

    verifyDebugLog("row clicked", {
      className: tr.attr("class") || "",
      index: tr.index(),
      text: (tr.text() || "").trim().slice(0, 40),
    });

    const isMobileOrTablet = isVerifyCompactMode();
    if (!isMobileOrTablet) {
      verifyDebugLog("blocked: not compact mode", { width: window.innerWidth });
      return;
    }

    const row = table.row(tr);
    if (!row.data()) {
      verifyDebugLog("blocked: row.data() empty");
      return;
    }

    if (row.child.isShown() || tr.hasClass("shown")) {
      row.child.hide();
      tr.next("tr.author-inline-detail-row").remove();
      tr.removeClass("shown");
      verifyDebugLog("collapsed row");
    } else {
      const d = row.data();
      verifyDebugLog("expanding row", {
        id: d && d.id ? d.id : null,
        user: d && d.user_name ? d.user_name : null,
      });
      const childHtml = buildAuthorVerifyDetailHtml(d);

      row.child(childHtml).show();
      verifyDebugLog("called row.child().show()", {
        childAfter: tr.next("tr.child").length,
      });

      // Hard fallback: if DataTables child row is still not visible, inject inline detail row
      setTimeout(function () {
        const hasVisibleChild = tr.next("tr.child:visible").length > 0;
        if (!hasVisibleChild) {
          tr.next("tr.author-inline-detail-row").remove();
          const colCount = $(tableEl).find("thead th").length || 9;
          const fallbackRow =
            '<tr class="author-inline-detail-row"><td colspan="' +
            colCount +
            '">' +
            childHtml +
            "</td></tr>";
          tr.after(fallbackRow);
          verifyDebugLog("fallback row injected", { colCount: colCount });
        } else {
          verifyDebugLog("datatable child visible", {
            childCount: tr.next("tr.child").length,
          });
        }
      }, 0);

      tr.addClass("shown");
    }
  });

  var debounceTimer = null;
  if (searchInput)
    searchInput.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        table.search(searchInput.value || "").draw();
      }, 250);
    });
  if (perPageSelect)
    perPageSelect.addEventListener("change", function () {
      table.page.len(parseInt(perPageSelect.value, 10) || 10).draw();
    });
  if (statusSelect)
    statusSelect.addEventListener("change", function () {
      table.draw();
    });

  tableEl.addEventListener("click", function (event) {
    var actionBtn = event.target.closest(".btn-author-action");
    if (actionBtn) {
      if (!modal || !actionForm || !idInput || !actionTypeSelect) return;

      var action = actionBtn.getAttribute("data-action") || "approve";
      idInput.value = actionBtn.getAttribute("data-id") || "0";
      actionTypeSelect.value = action;

      if (rejectInput) {
        rejectInput.value = actionBtn.getAttribute("data-reason") || "";
      }

      updateRejectReasonUI();

      // 1. Define the specific message based on the action
      var noticeMsg = "";
      if (action === "reject") {
        noticeMsg = "驳回时必须填写驳回原因，系统会自动发送驳回邮件。";
      } else if (action === "resend") {
        noticeMsg = "重发将按照当前审核状态调用对应邮件模板。";
      } else {
        noticeMsg = "通过后将清空驳回原因并发送通过邮件。";
      }

      // 2. Trigger the Smart Notice BEFORE showing the modal
      if (typeof window.showSmartNotice === "function") {
        window.showSmartNotice(
          "author_verify_" + action, // Unique LocalStorage ID for this specific action
          "提示",
          noticeMsg,
          "info",
          function () {
            modal.show(); // Only open the modal AFTER they acknowledge (or instantly if bypassed)
          },
        );
      } else {
        modal.show();
      }
      return;
    }

    var deleteBtn = event.target.closest(".btn-author-delete");
    if (!deleteBtn) return;
    var rowIdForDelete = deleteBtn.getAttribute("data-id") || "0";
    var doDelete = function () {
      var body =
        "mode=delete&id=" +
        encodeURIComponent(rowIdForDelete) +
        "&csrf_token=" +
        encodeURIComponent(csrfToken);
      fetch(apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-CSRF-Token": csrfToken,
        },
        body: body,
        credentials: "same-origin",
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (payload) {
          if (payload && payload.success) {
            if (typeof Swal !== "undefined")
              Swal.fire("成功", payload.message || "删除成功", "success");
            table.ajax.reload(null, false);
          } else if (typeof Swal !== "undefined") {
            Swal.fire(
              "错误",
              (payload && payload.message) || "删除失败",
              "error",
            );
          }
        })
        .catch(function () {
          if (typeof Swal !== "undefined")
            Swal.fire("错误", "服务器通信失败", "error");
        });
    };

    if (typeof Swal !== "undefined") {
      Swal.fire({
        title: "确认删除？",
        text: "此操作会进行软删除。",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "确认",
        cancelButtonText: "取消",
      }).then(function (result) {
        if (result.isConfirmed) doDelete();
      });
    } else if (window.confirm("确认删除该记录？")) {
      doDelete();
    }
  });

  if (actionTypeSelect)
    actionTypeSelect.addEventListener("change", updateRejectReasonUI);
  updateRejectReasonUI();

  if (actionForm) {
    actionForm.addEventListener("submit", function (event) {
      event.preventDefault();
      var formData = new FormData(actionForm);
      formData.set("mode", "verify");
      formData.set("csrf_token", csrfToken);
      fetch(apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-CSRF-Token": csrfToken,
        },
        body: new URLSearchParams(formData).toString(),
        credentials: "same-origin",
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (payload) {
          if (payload && payload.success) {
            if (modal) modal.hide();
            if (typeof Swal !== "undefined")
              Swal.fire("成功", payload.message || "操作成功", "success");
            table.ajax.reload(null, false);
          } else if (typeof Swal !== "undefined") {
            Swal.fire(
              "错误",
              (payload && payload.message) || "操作失败",
              "error",
            );
          }
        })
        .catch(function () {
          if (typeof Swal !== "undefined")
            Swal.fire("错误", "服务器通信失败", "error");
        });
    });
  }

  if (toggleBtn && dashboardPanel) {
    toggleBtn.addEventListener("click", function () {
      var isHidden = dashboardPanel.style.display === "none";
      if (isHidden) {
        dashboardPanel.style.display = "block";
        toggleBtn.textContent = "隐藏统计面板";
        document.cookie =
          "hide_author_verify_dashboard=0; path=/; max-age=31536000";
      } else {
        dashboardPanel.style.display = "none";
        toggleBtn.textContent = "显示统计面板";
        document.cookie =
          "hide_author_verify_dashboard=1; path=/; max-age=31536000";
      }
    });
  }

  tableEl.dataset.dtReady = "1";
  return true;
}

function initEmailTemplateModule() {
  var appRoot = document.getElementById("emailTemplateApp");
  var tableEl = document.getElementById("emailTemplateTable");
  var form = document.getElementById("emailTemplateFilterForm");
  if (
    !appRoot ||
    !tableEl ||
    !form ||
    typeof jQuery === "undefined" ||
    !jQuery.fn.DataTable
  )
    return false;
  if (tableEl.dataset.dtReady === "1") return true;

  var apiUrl = appRoot.getAttribute("data-api-url") || "";
  var csrfToken = appRoot.getAttribute("data-csrf") || "";
  if (!apiUrl) return false;

  var canEdit = appRoot.getAttribute("data-can-edit") === "1";
  var canDelete = appRoot.getAttribute("data-can-delete") === "1";
  var searchInput = form.querySelector('input[name="search"]');
  var perPageSelect = form.querySelector('select[name="per_page"]');
  var addBtn = document.getElementById("btnEmailTemplateAdd");
  var modalEl = document.getElementById("emailTemplateModal");
  var templateForm = document.getElementById("emailTemplateForm");
  if (!templateForm) return false;

  var idInput = templateForm.querySelector('input[name="id"]');
  var modeInput = templateForm.querySelector('input[name="mode"]');
  var codeInput = templateForm.querySelector('input[name="template_code"]');
  var nameInput = templateForm.querySelector('input[name="template_name"]');
  var subjectInput = templateForm.querySelector('input[name="subject"]');
  var contentInput = templateForm.querySelector('textarea[name="content"]');
  var statusInput = templateForm.querySelector('select[name="status"]');

  var modal = null;
  if (typeof bootstrap !== "undefined" && bootstrap.Modal && modalEl) {
    modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  }

  var rowCache = {};
  var table = jQuery(tableEl).DataTable({
    processing: true,
    serverSide: true,
    searching: true,
    ordering: false,
    lengthChange: false,
    pageLength: parseInt(perPageSelect ? perPageSelect.value : "10", 10) || 10,
    ajax: {
      url: apiUrl,
      type: "GET",
      data: function (d) {
        d.mode = "data";
      },
      dataSrc: function (json) {
        rowCache = {};
        var list = (json && json.data) || [];
        list.forEach(function (item) {
          rowCache[String(item.id)] = item;
        });
        return list;
      },
    },
    columns: [
      {
        data: null,
        render: function (data, type, row, meta) {
          var start = meta && meta.settings ? meta.settings._iDisplayStart : 0;
          return start + meta.row + 1;
        },
      },
      {
        data: "template_code",
        render: function (data) {
          return authorEscapeHtml(data || "-");
        },
      },
      {
        data: "template_name",
        render: function (data) {
          return authorEscapeHtml(data || "-");
        },
      },
      {
        data: "subject",
        render: function (data) {
          return authorEscapeHtml(data || "-");
        },
      },
      {
        data: "status",
        render: function (data) {
          return data === "A"
            ? '<span class="badge bg-success">启用</span>'
            : '<span class="badge bg-secondary">停用</span>';
        },
      },
      {
        data: "updated_at",
        render: function (data) {
          return authorEscapeHtml(data || "-");
        },
      },
      {
        data: null,
        className: "text-center",
        render: function (data) {
          var id = parseInt(data.id || 0, 10);
          var isRequired = data.is_required === true;
          var html = "";
          if (canEdit)
            html +=
              '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-email-edit" data-id="' +
              id +
              '">编辑</button>';
          if (canDelete && !isRequired)
            html +=
              '<button type="button" class="btn btn-sm btn-outline-danger btn-email-delete" data-id="' +
              id +
              '">删除</button>';
          return html === ""
            ? '<span class="text-muted small">无操作权限</span>'
            : html;
        },
      },
    ],
    dom: 'rt<"row mt-3 align-items-center"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
    language: {
      processing: "处理中...",
      zeroRecords: "没有匹配结果",
      info: "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
      infoEmpty: "显示第 0 至 0 项结果，共 0 项",
      infoFiltered: "",
      paginate: { previous: "上页", next: "下页" },
    },
  });

  function resetTemplateForm(mode, row) {
    idInput.value = row && row.id ? row.id : "0";
    modeInput.value = mode;
    codeInput.value = row && row.template_code ? row.template_code : "";
    nameInput.value = row && row.template_name ? row.template_name : "";
    subjectInput.value = row && row.subject ? row.subject : "";
    contentInput.value = row && row.content ? row.content : "";
    statusInput.value = row && row.status ? row.status : "A";
  }

  if (addBtn)
    addBtn.addEventListener("click", function () {
      resetTemplateForm("create", null);
      if (modal) modal.show();
    });

  tableEl.addEventListener("click", function (event) {
    var editBtn = event.target.closest(".btn-email-edit");
    if (editBtn) {
      var row = rowCache[String(editBtn.getAttribute("data-id") || "0")];
      if (!row) return;
      resetTemplateForm("update", row);
      if (modal) modal.show();
      return;
    }

    var deleteBtn = event.target.closest(".btn-email-delete");
    if (!deleteBtn) return;
    var deleteId = deleteBtn.getAttribute("data-id") || "0";
    var doDelete = function () {
      var body =
        "mode=delete&id=" +
        encodeURIComponent(deleteId) +
        "&csrf_token=" +
        encodeURIComponent(csrfToken);
      fetch(apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-CSRF-Token": csrfToken,
        },
        body: body,
        credentials: "same-origin",
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (payload) {
          if (payload && payload.success) {
            if (typeof Swal !== "undefined")
              Swal.fire("成功", payload.message || "删除成功", "success");
            table.ajax.reload(null, false);
          } else if (
            payload &&
            payload.type === "warning" &&
            typeof window.showNoChangeWarning === "function"
          ) {
            window.showNoChangeWarning("无需保存");
          } else if (typeof Swal !== "undefined") {
            Swal.fire(
              "错误",
              (payload && payload.message) || "删除失败",
              "error",
            );
          }
        })
        .catch(function () {
          if (typeof Swal !== "undefined")
            Swal.fire("错误", "服务器通信失败", "error");
        });
    };

    if (typeof Swal !== "undefined") {
      Swal.fire({
        title: "确认删除？",
        text: "此操作会进行软删除。",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "确认",
        cancelButtonText: "取消",
      }).then(function (result) {
        if (result.isConfirmed) doDelete();
      });
    } else if (window.confirm("确认删除该模板？")) {
      doDelete();
    }
  });

  templateForm.addEventListener("submit", function (event) {
    event.preventDefault();
    if (codeInput && codeInput.value)
      codeInput.value = String(codeInput.value).toUpperCase().trim();
    var formData = new FormData(templateForm);
    formData.set("csrf_token", csrfToken);
    fetch(apiUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-CSRF-Token": csrfToken,
      },
      body: new URLSearchParams(formData).toString(),
      credentials: "same-origin",
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (payload) {
        if (payload && payload.success) {
          if (modal) modal.hide();
          if (typeof Swal !== "undefined")
            Swal.fire("成功", payload.message || "保存成功", "success");
          table.ajax.reload(null, false);
        } else if (
          payload &&
          payload.type === "warning" &&
          typeof window.showNoChangeWarning === "function"
        ) {
          window.showNoChangeWarning("无需保存");
        } else if (typeof Swal !== "undefined") {
          Swal.fire(
            "错误",
            (payload && payload.message) || "保存失败",
            "error",
          );
        }
      })
      .catch(function () {
        if (typeof Swal !== "undefined")
          Swal.fire("错误", "服务器通信失败", "error");
      });
  });

  var debounceTimer = null;
  if (searchInput)
    searchInput.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        table.search(searchInput.value || "").draw();
      }, 250);
    });
  if (perPageSelect)
    perPageSelect.addEventListener("change", function () {
      table.page.len(parseInt(perPageSelect.value, 10) || 10).draw();
    });

  tableEl.dataset.dtReady = "1";
  return true;
}

if (!initAuthorVerificationModule()) {
  window.addEventListener("load", initAuthorVerificationModule);
  setTimeout(initAuthorVerificationModule, 0);
}

if (!initEmailTemplateModule()) {
  window.addEventListener("load", initEmailTemplateModule);
  setTimeout(initEmailTemplateModule, 0);
}
