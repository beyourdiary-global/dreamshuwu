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
    // This wrapper ensures elements stack nicely
    dom:
      "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    buttons: [
      {
        extend: "colvis",
        text: '<i class="fa-solid fa-columns"></i> Columns',
        className: "btn btn-sm btn-outline-secondary",
        columns: ":not(.dtr-control):not(.none)", // Don't let users hide the (+) button
      },
    ],
    // [NEW] Responsive Configuration
    responsive: {
      details: {
        type: "column",
        target: 0, // The first column (index 0) is the click target
      },
    },
    columns: [
      // [NEW] Column 0: Dedicated Expand Button (+)
      {
        className: "dtr-control",
        orderable: false,
        data: null,
        defaultContent: "",
        responsivePriority: 1,
      },

      // Existing Columns (Shifted Indices +1)
      { data: 0, responsivePriority: 1 }, // Page
      { data: 1, responsivePriority: 1 }, // Action
      { data: 2, responsivePriority: 3 }, // Message
      { data: 3, responsivePriority: 4 }, // User (Hidden on mobile)
      { data: 4, responsivePriority: 2 }, // Date
      { data: 5, responsivePriority: 5 }, // Time (Hidden on mobile)

      // Details Column (Always Hidden, shows in expansion)
      {
        className: "none",
        orderable: false,
        data: null,
        render: function (data) {
          return formatDetails(data);
        },
      },
    ],
    order: [
      [5, "desc"], // Sort by Date (index 5 now)
      [6, "desc"], // Sort by Time (index 6 now)
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

    var html = '<div class="row p-3 bg-light border-top">';

    // SQL Query
    html += '<div class="col-12 mb-2">';
    html += '<strong><i class="fa-solid fa-database"></i> SQL Query:</strong>';
    html +=
      '<div class="bg-white p-2 border rounded mt-1 text-break"><code>' +
      (d.query || "N/A") +
      "</code></div>";
    html += "</div>";

    // Changes
    if (d.changes) {
      html += '<div class="col-12">';
      html +=
        '<strong><i class="fa-solid fa-pen-to-square"></i> Specific Changes:</strong>';
      html += "<pre>" + safeJson(d.changes) + "</pre>";
      html += "</div>";
    } else {
      html += '<div class="col-md-6">';
      html +=
        "<strong>Old Value:</strong><pre>" + safeJson(d.old_value) + "</pre>";
      html += "</div>";
      html += '<div class="col-md-6">';
      html +=
        "<strong>New Value:</strong><pre>" + safeJson(d.new_value) + "</pre>";
      html += "</div>";
    }

    html += "</div>";
    return html;
  }
});
