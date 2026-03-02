$(document).ready(function () {
  // Keep pagination compact on small screens
  if (
    $.fn &&
    $.fn.dataTable &&
    $.fn.dataTable.ext &&
    $.fn.dataTable.ext.pager
  ) {
    $.fn.dataTable.ext.pager.numbers_length = 4;
  }

  // 1. Helper to Format Details (Child Row)
  function format(d) {
    if (!d.details) return "";
    var det = d.details;
    var html = '<div class="detail-box">';

    var isSmall = window.matchMedia("(max-width: 767px)").matches;

    // Inside audit-log.js -> function format(d)
    if (isSmall) {
      html +=
        '<div class="mb-2"><strong>页面:</strong> ' + (d.page || "") + "</div>";
      html +=
        '<div class="mb-2"><strong>操作:</strong> ' +
        (d.action || "") +
        "</div>";
      html +=
        '<div class="mb-2"><strong>信息:</strong> ' +
        (d.message || "") +
        "</div>";
      html +=
        '<div class="mb-2"><strong>用户:</strong> ' + (d.user || "") + "</div>";
      html +=
        '<div class="mb-2"><strong>日期:</strong> ' + (d.date || "") + "</div>";
      html +=
        '<div class="mb-3"><strong>时间:</strong> ' + (d.time || "") + "</div>";
    }

    if (oldV || newV) {
      html += '<div class="row">';
      if (oldV) {
        html +=
          '<div class="col-xs-12 col-md-6"><strong><i class="fa-solid fa-minus-circle text-danger"></i> 旧值:</strong>'; // Changed from Old Value
        html += "<pre>" + oldV + "</pre></div>";
      }
      if (newV) {
        html +=
          '<div class="col-xs-12 col-md-6"><strong><i class="fa-solid fa-plus-circle text-success"></i> 新值:</strong>'; // Changed from New Value
        html += "<pre>" + newV + "</pre></div>";
      }
      html += "</div>";
    }

    // Show Query if available
    if (det.query) {
      html +=
        '<div class="mb-3"><strong><i class="fa-solid fa-database"></i> SQL Query:</strong>';
      html += "<pre>" + det.query + "</pre></div>";
    }

    // Try parsing JSON if strings, or stringify objects for display
    var oldV = det.old,
      newV = det.new;

    // Format old value for display
    if (oldV !== null && oldV !== undefined) {
      if (typeof oldV === "object") {
        oldV = JSON.stringify(oldV, null, 2);
      } else if (typeof oldV === "string") {
        try {
          oldV = JSON.stringify(JSON.parse(oldV), null, 2);
        } catch (e) {
          // Keep as-is if not valid JSON
        }
      }
    }

    // Format new value for display
    if (newV !== null && newV !== undefined) {
      if (typeof newV === "object") {
        newV = JSON.stringify(newV, null, 2);
      } else if (typeof newV === "string") {
        try {
          newV = JSON.stringify(JSON.parse(newV), null, 2);
        } catch (e) {
          // Keep as-is if not valid JSON
        }
      }
    }

    // Show Changes if available
    if (oldV || newV) {
      html += '<div class="row">';
      if (oldV) {
        html +=
          '<div class="col-xs-12 col-md-6"><strong><i class="fa-solid fa-minus-circle text-danger"></i> Old Value:</strong>';
        html += "<pre>" + oldV + "</pre></div>";
      }
      if (newV) {
        html +=
          '<div class="col-xs-12 col-md-6"><strong><i class="fa-solid fa-plus-circle text-success"></i> New Value:</strong>';
        html += "<pre>" + newV + "</pre></div>";
      }
      html += "</div>";
    }

    html += "</div>";
    return html;
  }

  // 2. Initialize DataTable
  var tableUrl = $("#auditTable").data("api-url");
  var table = $("#auditTable").DataTable({
    processing: true,
    serverSide: true,
    responsive: true,
    ajax: {
      url: tableUrl,
      data: function (d) {
        d.filter_action = $("#actionFilter").val();
      },
    },
    columns: [
      {
        className: "dt-control",
        orderable: false,
        data: null,
        defaultContent: "",
        width: "30px",
      },
      {
        data: null,
        orderable: false,
        searchable: false,
        render: function (data, type, row, meta) {
          var start = meta && meta.settings ? meta.settings._iDisplayStart : 0;
          return start + meta.row + 1;
        },
      },
      { data: "page" },
      { data: "action" },
      { data: "message" },
      { data: "user" },
      { data: "date" },
      { data: "time" },
    ],
    order: [[6, "desc"]],
    pagingType: "simple_numbers",
    language: {
      processing: "处理中...",
      search: "搜索:",
      lengthMenu: "显示 _MENU_ 项结果",
      emptyTable: "没有匹配结果",
      zeroRecords: "没有匹配结果",
      info: "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
      infoEmpty: "显示第 0 至 0 项结果，共 0 项",
      infoFiltered: "(从 _MAX_ 条数据中筛选)",
      paginate: {
        previous: "上页",
        next: "下页",
      },
    },
  });

  function applyMobileColumnVisibility() {
    var isSmall = window.matchMedia("(max-width: 767px)").matches;
    // Keep: [0]=details button, [1]=Page, [2]=Action
    // Hide on small: Message/User/Date/Time
    table.column(4).visible(!isSmall, false);
    table.column(5).visible(!isSmall, false);
    table.column(6).visible(!isSmall, false);
    table.column(7).visible(!isSmall, false);
    table.columns.adjust().draw(false);
  }

  // Apply once on load and keep in sync on resize
  applyMobileColumnVisibility();
  var resizeTimer;
  $(window).on("resize", function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(applyMobileColumnVisibility, 150);
  });

  // 3. Handle Filter
  $("#actionFilter").on("change", function () {
    table.draw();
  });

  // 4. Handle Expand Click
  $("#auditTable tbody").on("click", "td.dt-control", function () {
    var tr = $(this).closest("tr");
    var row = table.row(tr);

    if (row.child.isShown()) {
      row.child.hide();
      tr.removeClass("shown");
    } else {
      row.child(format(row.data())).show();
      tr.addClass("shown");
    }
  });
});
