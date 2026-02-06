$(document).ready(function () {
  const tableElement = $("#auditTable");
  const apiUrl = tableElement.data("api-url");

  // Safety Check
  if (!$.fn.DataTable) {
    console.error("DataTables library not found.");
    return;
  }

  var table = tableElement.DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: apiUrl,
      data: function (d) {
        d.filter_action = $("#actionFilter").val();
      },
    },
    // Bootstrap 3 Grid Structure for Controls
    dom:
      "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    buttons: [
      {
        extend: "colvis",
        text: '<i class="fa-solid fa-columns"></i> Columns',
        // [FIX] Changed 'btn-outline-secondary' (BS5) to 'btn-default' (BS3)
        className: "btn btn-sm btn-default",
        columns: ":not(.dtr-control):not(.none)",
      },
    ],
    responsive: {
      details: {
        type: "column",
        target: 0,
      },
    },
    columns: [
      // Index 0: Expand Button (+)
      {
        className: "dtr-control",
        orderable: false,
        data: null,
        defaultContent: "",
        responsivePriority: 1,
      },
      // Index 1: Page
      { data: 0, responsivePriority: 1 },
      // Index 2: Action
      { data: 1, responsivePriority: 1 },
      // Index 3: Message
      { data: 2, responsivePriority: 3 },
      // Index 4: User
      { data: 3, responsivePriority: 4 },
      // Index 5: Date
      { data: 4, responsivePriority: 2 },
      // Index 6: Time
      { data: 5, responsivePriority: 5 },
      // Index 7: Details
      {
        className: "none",
        orderable: false,
        data: null,
        render: function (data) {
          return formatDetails(data);
        },
      },
    ],
    // [VERIFIED] Date is at Index 5, Time is at Index 6. This is CORRECT.
    order: [
      [5, "desc"],
      [6, "desc"],
    ],
    pageLength: 10,
  });

  $("#actionFilter").change(function () {
    table.draw();
  });

  function formatDetails(d) {
    function safeJson(str) {
      if (!str) return "<em>None</em>";
      try {
        return JSON.stringify(JSON.parse(str), null, 2);
      } catch (e) {
        return str;
      }
    }

    var html =
      '<div class="row" style="padding: 15px; background: #f9f9f9; border-top: 1px solid #ddd;">';

    html += '<div class="col-xs-12 mb-2">';
    html += '<strong><i class="fa-solid fa-database"></i> SQL Query:</strong>';
    html +=
      '<div style="background: #fff; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-top: 5px; word-wrap: break-word;"><code>' +
      (d.query || "N/A") +
      "</code></div>";
    html += "</div>";

    if (d.changes) {
      html += '<div class="col-xs-12">';
      html +=
        '<strong><i class="fa-solid fa-pen-to-square"></i> Specific Changes:</strong>';
      html += "<pre>" + safeJson(d.changes) + "</pre>";
      html += "</div>";
    } else {
      // Use col-sm-6 for side-by-side on tablet/desktop, col-xs-12 for mobile
      html += '<div class="col-xs-12 col-sm-6">';
      html +=
        "<strong>Old Value:</strong><pre>" + safeJson(d.old_value) + "</pre>";
      html += "</div>";
      html += '<div class="col-xs-12 col-sm-6">';
      html +=
        "<strong>New Value:</strong><pre>" + safeJson(d.new_value) + "</pre>";
      html += "</div>";
    }

    html += "</div>";
    return html;
  }
});
