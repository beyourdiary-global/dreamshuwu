$(document).ready(function () {
  // 1. Helper to Format Details (Child Row)
  function format(d) {
    if (!d.details) return "";
    var det = d.details;
    var html = '<div class="detail-box">';

    // Show Query if available
    if (det.query) {
      html +=
        '<div class="mb-3"><strong><i class="fa-solid fa-database"></i> SQL Query:</strong>';
      html += "<pre>" + det.query + "</pre></div>";
    }

    // Try parsing JSON if strings
    var oldV = det.old,
      newV = det.new;
    try {
      if (typeof oldV === "string")
        oldV = JSON.stringify(JSON.parse(oldV), null, 2);
    } catch (e) {}
    try {
      if (typeof newV === "string")
        newV = JSON.stringify(JSON.parse(newV), null, 2);
    } catch (e) {}

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
      { data: "page" },
      { data: "action" },
      { data: "message" },
      { data: "user" },
      { data: "date" },
      { data: "time" },
    ],
    order: [[5, "desc"]], // Sort by Date by default
    language: {
      emptyTable: "No logs found",
      processing: "<i class='fa fa-spinner fa-spin'></i> Loading...",
    },
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
