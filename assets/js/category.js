$(document).ready(function () {
  // Auto-hide success alerts after 3 seconds
  $(".alert-success").delay(3000).fadeOut("slow");

  const tableEl = $("#categoryTable");
  // [FIX] Get the absolute URL from the data attribute, or fallback to default
  const apiUrl = tableEl.data("api-url") || "index.php?mode=data";
  const deleteUrl = tableEl.data("delete-url") || "index.php";

  // Initialize DataTable
  const table = tableEl.DataTable({
    processing: true,
    serverSide: true,
    // [FIX] Use the dynamic URL
    ajax: { url: apiUrl, type: "GET" },
    columns: [
      { data: 0 }, // Category Name
      { data: 1, orderable: false }, // Tags
      { data: 2, orderable: false, className: "text-center" }, // Actions
    ],
    order: [],
    dom:
      "<'row'<'col-sm-12 d-flex justify-content-end'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'row'<'col-sm-12 d-flex justify-content-between align-items-center'ip>>",
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
          url: deleteUrl, // [FIX] Use dynamic delete URL
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
});
