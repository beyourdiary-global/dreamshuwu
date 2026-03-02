$(document).ready(function () {
  // --- Existing Alert and Table Logic ---
  $(".alert-success")
    .delay(3000)
    .fadeOut("slow", function () {
      $(this).remove();
    });

  const $table = $("#tagTable");
  const apiUrl = $table.data("api-url") || "index.php?mode=data";
  const deleteUrl = $table.data("delete-url") || "index.php";

  const table = $("#tagTable").DataTable({
    processing: true,
    serverSide: true,
    ajax: { url: apiUrl, type: "GET" },
    columns: [
      {
        data: null,
        orderable: false, // [FIX] Added to disable sorting
        searchable: false, // [FIX] Added to disable searching
        render: function (data, type, row, meta) {
          var start = meta && meta.settings ? meta.settings._iDisplayStart : 0;
          return start + meta.row + 1;
        },
      },
      { data: 1, orderable: false },
      { data: 2, orderable: false, className: "text-center" },
    ],
    order: [],
    dom:
      "<'row mb-3 align-items-center'<'col-sm-12 col-md-6 d-flex justify-content-start'l><'col-sm-12 col-md-6 d-flex justify-content-end'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'row mt-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>",

    language: {
      sProcessing: "处理中...",
      sLengthMenu: "显示 _MENU_ 项结果",
      sZeroRecords: "没有匹配结果",
      sInfo: "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
      sInfoEmpty: "显示第 0 至 0 项结果，共 0 项",
      sInfoFiltered: "(由 _MAX_ 项结果过滤)",
      sSearch: "",
      searchPlaceholder: "搜索标签...",
      oPaginate: {
        sFirst: "首页",
        sPrevious: "上页",
        sNext: "下页",
        sLast: "末页",
      },
    },
  });

  function buildTagMobileActions(rawActionHtml) {
    const tempWrap = $("<div>").html(rawActionHtml || "");
    const editBtn = tempWrap.find("a.btn-outline-primary").first();
    const deleteBtn = tempWrap.find("button.delete-btn").first();

    let mobileHtml = "";

    if (editBtn.length) {
      mobileHtml +=
        '<a href="' +
        (editBtn.attr("href") || "#") +
        '" class="btn btn-sm btn-outline-primary me-2"><i class="fa-solid fa-pen"></i> 编辑</a>';
    }

    if (deleteBtn.length) {
      mobileHtml +=
        '<button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="' +
        (deleteBtn.attr("data-id") || "") +
        '" data-name="' +
        (deleteBtn.attr("data-name") || "") +
        '"><i class="fa-solid fa-trash"></i> 删除</button>';
    }

    return mobileHtml || '<span class="text-muted small">无操作权限</span>';
  }

  function applyTagMobileActionMode() {
    const isMobileView = document.body.classList.contains("is-mobile");
    const viewportWidth =
      window.innerWidth || document.documentElement.clientWidth || 0;
    const hideIndexColumn = isMobileView && viewportWidth <= 380;

    table.column(0).visible(!hideIndexColumn, false);
    table.column(2).visible(!isMobileView, false);
    table.columns.adjust().draw(false);
  }

  applyTagMobileActionMode();
  let tagResizeTimer;
  $(window).on("resize", function () {
    clearTimeout(tagResizeTimer);
    tagResizeTimer = setTimeout(applyTagMobileActionMode, 120);
  });

  $("#tagTable tbody").on("click", "tr", function (e) {
    if (!document.body.classList.contains("is-mobile")) return;
    if ($(e.target).closest("a,button,.btn,.delete-btn").length) return;

    const row = table.row(this);
    if (!row.data()) return;

    const actionHtml = buildTagMobileActions(row.data()[2] || "");
    if (!actionHtml) return;

    if (row.child.isShown()) {
      row.child.hide();
      $(this).removeClass("shown");
    } else {
      row
        .child('<div class="mobile-row-actions">' + actionHtml + "</div>")
        .show();
      $(this).addClass("shown");
    }
  });

  // --- Existing Delete Handler ---
  $(document).on("click", ".delete-btn", function () {
    const id = $(this).data("id");
    const name = $(this).data("name");

    Swal.fire({
      title: "删除标签？",
      text: `您确定要删除 "${name}" 吗？`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      confirmButtonText: "确认删除",
      cancelButtonText: "取消",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: deleteUrl,
          type: "POST",
          data: { mode: "delete", id: id, name: name },
          dataType: "json",
          success: function (res) {
            if (res && res.success) {
              Swal.fire("删除成功！", "", "success");
              table.ajax.reload();
            } else {
              Swal.fire(
                "错误",
                res ? res.message || "未知错误" : "服务器返回空响应",
                "error",
              );
            }
          },
          error: function () {
            Swal.fire("错误", "系统错误", "error");
          },
        });
      }
    });
  });
});
