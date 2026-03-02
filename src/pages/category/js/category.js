$(document).ready(function () {
  // Auto-hide success alerts after 3 seconds
  $(".alert-success").delay(3000).fadeOut("slow");

  const tableEl = $("#categoryTable");

  // 1. Only initialize DataTables if the table exists (List View)
  if (tableEl.length > 0) {
    const apiUrl = tableEl.data("api-url") || "index.php?mode=data";
    const deleteUrl = tableEl.data("delete-url") || "index.php";

    // Initialize DataTable
    const table = tableEl.DataTable({
      processing: true,
      serverSide: true,
      ajax: { url: apiUrl, type: "GET" },
      columns: [
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data, type, row, meta) {
            var start =
              meta && meta.settings ? meta.settings._iDisplayStart : 0;
            return start + meta.row + 1;
          },
        },
        { data: 1 }, // Category Name
        { data: 2, orderable: false }, // Tags
        { data: 3, orderable: false, className: "text-center" }, // Actions
      ],
      order: [],
      dom:
        "<'row mb-3 align-items-center'<'col-sm-12 col-md-6 d-flex justify-content-start'l><'col-sm-12 col-md-6 d-flex justify-content-end'f>>" +
        "<'row'<'col-sm-12'tr>>" +
        "<'row mt-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>",
      language: {
        sProcessing: "处理中...",
        sLengthMenu: "显示 _MENU_ 项",
        sZeroRecords: "没有匹配结果",
        sInfo: "显示 _START_ 至 _END_ 项，共 _TOTAL_ 项",
        sInfoEmpty: "显示 0 至 0 项，共 0 项",
        sSearch: "",
        searchPlaceholder: "搜索分类...",
        oPaginate: {
          sFirst: "首页",
          sPrevious: "上页",
          sNext: "下页",
          sLast: "末页",
        },
      },
    });

    function buildCategoryMobileActions(rawActionHtml) {
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

    function applyCategoryMobileActionMode() {
      const isMobileView = document.body.classList.contains("is-mobile");
      const viewportWidth =
        window.innerWidth || document.documentElement.clientWidth || 0;

      const hideTagsColumn = isMobileView && viewportWidth <= 560;
      const hideIndexColumn = isMobileView && viewportWidth <= 380;

      table.column(0).visible(!hideIndexColumn, false);
      table.column(2).visible(!hideTagsColumn, false);
      table.column(3).visible(!isMobileView, false);
      table.columns.adjust().draw(false);
    }

    applyCategoryMobileActionMode();
    let catResizeTimer;
    $(window).on("resize", function () {
      clearTimeout(catResizeTimer);
      catResizeTimer = setTimeout(applyCategoryMobileActionMode, 120);
    });

    $("#categoryTable tbody").on("click", "tr", function (e) {
      if (!document.body.classList.contains("is-mobile")) return;
      if ($(e.target).closest("a,button,.btn,.delete-btn").length) return;

      const row = table.row(this);
      if (!row.data()) return;

      const actionHtml = buildCategoryMobileActions(row.data()[3] || "");
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

    // Handle Delete Button Click
    $(document).on("click", ".delete-btn", function () {
      const id = $(this).data("id");
      const name = $(this).data("name");

      Swal.fire({
        title: "删除分类？",
        text: `确定要删除 "${name}" 吗？\n该分类下的所有标签关联也将被移除。`,
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
              if (res.success) {
                Swal.fire("删除成功", "", "success");
                table.ajax.reload();
              } else {
                Swal.fire("错误", res.message || "删除失败", "error");
              }
            },
            error: function () {
              Swal.fire("错误", "服务器通信失败", "error");
            },
          });
        }
      });
    });
  }

  $(document).on("click", "#categoryForm button[type='submit']", function (e) {
    // Count how many checkboxes are ticked
    let checkedTags = $("input[name='tags[]']:checked").length;

    if (checkedTags === 0) {
      e.preventDefault();

      Swal.fire({
        icon: "warning",
        text: "请至少选择一个关联标签",
        confirmButtonColor: "#4e73df",
        confirmButtonText: "我知道了",
      });
    }
  });
});
