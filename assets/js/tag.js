$(document).ready(function () {
  $(".alert-success")
    .delay(3000)
    .fadeOut("slow", function () {
      $(this).remove();
    });

  const $table = $("#tagTable");
  const apiUrl = $table.data("api-url") || "index.php?mode=data";
  const deleteUrl = $table.data("delete-url") || "index.php";

  // Initialize DataTable with server-side processing
  const table = $("#tagTable").DataTable({
    processing: true,
    serverSide: true,
    ajax: { url: apiUrl, type: "GET" },
    columns: [{ data: 0 }, { data: 1, orderable: false }],
    order: [],
    dom:
      "<'row'<'col-sm-12 d-flex justify-content-end'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'row'<'col-sm-5'i><'col-sm-7'p>>",

    // Chinese language configuration
    language: {
      sProcessing: "处理中...",
      sLengthMenu: "显示 _MENU_ 项结果",
      sZeroRecords: "没有匹配结果",
      sInfo: "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
      sInfoEmpty: "显示第 0 至 0 项结果，共 0 项",
      sInfoFiltered: "(由 _MAX_ 项结果过滤)",
      sInfoPostFix: "",
      sSearch: "",
      sUrl: "",
      sEmptyTable: "表中数据为空",
      sLoadingRecords: "载入中...",
      sInfoThousands: ",",
      oPaginate: {
        sFirst: "首页",
        sPrevious: "上页",
        sNext: "下页",
        sLast: "末页",
      },
      search: "",
      searchPlaceholder: "搜索标签...",
    },
  });

  // Handle delete button click
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
            // Add a check to ensure res is not null or undefined
            if (res && res.success) {
              Swal.fire("删除成功！", "", "success");
              table.ajax.reload();
            } else {
              // Provide a default error message if res or res.success is missing
              // or use res.message if it exists and res is not null
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
