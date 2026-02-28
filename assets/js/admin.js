(function () {
  /**
   * 1. Page Action Management (API Fetch Logic)
   * Handles deletions via AJAX/Fetch
   */
  var deleteButtons = document.querySelectorAll(".page-action-delete-btn");
  var appRoot = document.getElementById("pageActionApp");
  var deleteApiUrl = appRoot ? appRoot.getAttribute("data-delete-api-url") : "";

  function sendDeleteRequest(rowId, rowName) {
    if (!deleteApiUrl) {
      if (typeof Swal !== "undefined") {
        Swal.fire("错误", "删除接口地址缺失", "error");
      }
      return;
    }

    var body = "mode=delete_api&id=" + encodeURIComponent(rowId);
    fetch(deleteApiUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: body,
      credentials: "same-origin",
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (payload && payload.success) {
          if (typeof Swal !== "undefined") {
            Swal.fire("删除成功", "", "success").then(function () {
              window.location.reload();
            });
          } else {
            window.location.reload();
          }
        } else {
          var message =
            payload && payload.message ? payload.message : "删除失败";
          if (typeof Swal !== "undefined") {
            Swal.fire("错误", message, "error");
          }
        }
      })
      .catch(function () {
        if (typeof Swal !== "undefined") {
          Swal.fire("错误", "服务器通信失败", "error");
        }
      });
  }

  // Attach listeners for Page Action buttons
  deleteButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      var rowId = this.getAttribute("data-id");
      var rowName = this.getAttribute("data-name") || "";

      if (typeof Swal !== "undefined") {
        Swal.fire({
          title: "确认软删除？",
          text: "确定要删除操作 “" + rowName + "” 吗？",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "确认删除",
          cancelButtonText: "取消",
          confirmButtonColor: "#d33",
        }).then(function (result) {
          if (result.isConfirmed) {
            sendDeleteRequest(rowId, rowName);
          }
        });
      } else if (window.confirm("确定要删除操作 “" + rowName + "” 吗？")) {
        sendDeleteRequest(rowId, rowName);
      }
    });
  });

  /**
   * 2. Page Information Management (Form Submit Logic)
   * Handles deletions via Hidden Form Submission
   * Exposed to window for onclick="confirmDelete(id)"
   */
  window.confirmDelete = function (id) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        title: "确定删除?",
        text: "此操作将执行软删除。",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "删除",
        cancelButtonText: "取消",
      }).then(function (result) {
        if (result.isConfirmed) {
          var idInput = document.getElementById("deleteId");
          var form = document.getElementById("deleteForm");
          if (idInput && form) {
            idInput.value = id;
            form.submit();
          } else {
            console.error("Delete form or input not found.");
          }
        }
      });
    } else {
      // Fallback if Swal is missing
      if (confirm("确定删除? 此操作将执行软删除。")) {
        var idInput = document.getElementById("deleteId");
        var form = document.getElementById("deleteForm");
        if (idInput && form) {
          idInput.value = id;
          form.submit();
        }
      }
    }
  };

  /**
   * 3. Mobile UI Utilities
   * Handles accordion expansion for mobile views
   */
  var mobileItems = document.querySelectorAll(".page-action-mobile-item");
  mobileItems.forEach(function (item) {
    var head = item.querySelector(".page-action-mobile-head");
    if (!head) return;
    head.addEventListener("click", function () {
      item.classList.toggle("open");
    });
  });
})();

// Additional Utility Functions for User Role Management Page
function togglePageCard(headerEl) {
  const body = headerEl.nextElementSibling;
  const icon = headerEl.querySelector("i");
  body.style.display = body.style.display === "none" ? "block" : "none";
  icon.classList.toggle("fa-chevron-down");
  icon.classList.toggle("fa-chevron-up");
}

function toggleAllPermissions() {
  const allCheckboxes = document.querySelectorAll(".perm-checkbox");
  const allChecked = Array.from(allCheckboxes).every((cb) => cb.checked);
  allCheckboxes.forEach((cb) => (cb.checked = !allChecked));
}

function filterPageCards() {
  const searchTerm = document.getElementById("searchPages").value.toLowerCase();
  const cards = document.querySelectorAll(".page-card");
  cards.forEach((card) => {
    const name = card.getAttribute("data-page-name");
    card.style.display = name.includes(searchTerm) ? "block" : "none";
  });
}

function initPageActionFilter() {
  var form = document.getElementById("pageActionFilterForm");
  var tableEl = document.getElementById("pageActionTable");
  if (
    !form ||
    !tableEl ||
    typeof jQuery === "undefined" ||
    !jQuery.fn.DataTable
  )
    return false;
  if (tableEl.dataset.dtReady === "1") return true;

  var searchInput = form.querySelector('input[name="search_name"]');
  var perPageSelect = form.querySelector('select[name="per_page"]');
  var table = jQuery(tableEl).DataTable({
    pageLength: parseInt(perPageSelect ? perPageSelect.value : "10", 10) || 10,
    lengthChange: false,
    searching: true,
    info: true,
    ordering: false,
    dom: 'rt<"row mt-3 align-items-center"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
    language: {
      zeroRecords: "没有匹配结果",
      info: "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
      infoEmpty: "显示第 0 至 0 项结果，共 0 项",
      infoFiltered: "",
      paginate: {
        previous: "上页",
        next: "下页",
      },
    },
  });
  var debounceTimer = null;

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    if (searchInput) table.search(searchInput.value || "").draw();
  });

  if (searchInput) {
    table.search(searchInput.value || "").draw();
    searchInput.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        table.search(searchInput.value || "").draw();
      }, 250);
    });
  }

  if (perPageSelect) {
    perPageSelect.addEventListener("change", function () {
      var size = parseInt(perPageSelect.value, 10) || 10;
      table.page.len(size).draw();
    });
  }

  tableEl.dataset.dtReady = "1";
  return true;
}

function initPageInfoFilter() {
  var form = document.getElementById("pageInfoFilterForm");
  var tableEl = document.getElementById("pageInfoTable");
  if (
    !form ||
    !tableEl ||
    typeof jQuery === "undefined" ||
    !jQuery.fn.DataTable
  )
    return false;
  if (tableEl.dataset.dtReady === "1") return true;

  var searchInput = form.querySelector('input[name="search"]');
  var perPageSelect = form.querySelector('select[name="per_page"]');
  var table = jQuery(tableEl).DataTable({
    pageLength: parseInt(perPageSelect ? perPageSelect.value : "10", 10) || 10,
    lengthChange: false,
    searching: true,
    info: true,
    ordering: false,
    dom: 'rt<"row mt-3 align-items-center"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
    language: {
      zeroRecords: "没有匹配结果",
      info: "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
      infoEmpty: "显示第 0 至 0 项结果，共 0 项",
      infoFiltered: "",
      paginate: {
        previous: "上页",
        next: "下页",
      },
    },
  });
  var debounceTimer = null;

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    if (searchInput) table.search(searchInput.value || "").draw();
  });

  if (searchInput) {
    table.search(searchInput.value || "").draw();
    searchInput.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        table.search(searchInput.value || "").draw();
      }, 250);
    });
  }

  if (perPageSelect) {
    perPageSelect.addEventListener("change", function () {
      var size = parseInt(perPageSelect.value, 10) || 10;
      table.page.len(size).draw();
    });
  }

  tableEl.dataset.dtReady = "1";
  return true;
}

function initUserRoleFilter() {
  var form = document.getElementById("userRoleFilterForm");
  var tableEl = document.getElementById("userRoleTable");
  if (
    !form ||
    !tableEl ||
    typeof jQuery === "undefined" ||
    !jQuery.fn.DataTable
  )
    return false;
  if (tableEl.dataset.dtReady === "1") return true;

  var searchInput = form.querySelector('input[name="search"]');
  var perPageSelect = form.querySelector('select[name="per_page"]');
  var table = jQuery(tableEl).DataTable({
    pageLength: parseInt(perPageSelect ? perPageSelect.value : "10", 10) || 10,
    lengthChange: false,
    searching: true,
    info: true,
    ordering: false,
    dom: 'rt<"row mt-3 align-items-center"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
    language: {
      zeroRecords: "没有匹配结果",
      info: "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
      infoEmpty: "显示第 0 至 0 项结果，共 0 项",
      infoFiltered: "",
      paginate: {
        previous: "上页",
        next: "下页",
      },
    },
  });
  var debounceTimer = null;

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    if (searchInput) table.search(searchInput.value || "").draw();
  });

  if (searchInput) {
    table.search(searchInput.value || "").draw();
    searchInput.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        table.search(searchInput.value || "").draw();
      }, 250);
    });
  }

  if (perPageSelect) {
    perPageSelect.addEventListener("change", function () {
      var size = parseInt(perPageSelect.value, 10) || 10;
      table.page.len(size).draw();
    });
  }

  tableEl.dataset.dtReady = "1";
  return true;
}

function adminEscapeHtml(text) {
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
  ) {
    return false;
  }
  if (tableEl.dataset.dtReady === "1") return true;

  var apiUrl = appRoot.getAttribute("data-api-url") || "";
  var csrfToken = appRoot.getAttribute("data-csrf") || "";
  if (!apiUrl) return false;

  // Read permissions from data attributes for Author Module
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
  var hintEl = document.getElementById("authorVerifyActionHint");
  var dashboardPanel = document.getElementById("authorVerifyDashboardPanel");
  var toggleBtn = document.getElementById("toggleAuthorVerifyDashboard");

  var modal = null;
  if (typeof bootstrap !== "undefined" && bootstrap.Modal && modalEl) {
    modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  }

  function updateRejectReasonUI() {
    if (!actionTypeSelect || !rejectWrap || !rejectInput || !hintEl) return;
    var actionType = actionTypeSelect.value;
    if (actionType === "reject") {
      rejectWrap.style.display = "block";
      rejectInput.setAttribute("required", "required");
      hintEl.innerText = "驳回时必须填写驳回原因，系统会自动发送驳回邮件。";
    } else if (actionType === "resend") {
      rejectWrap.style.display = "none";
      rejectInput.removeAttribute("required");
      hintEl.innerText = "重发将按照当前审核状态调用对应邮件模板。";
    } else {
      rejectWrap.style.display = "none";
      rejectInput.removeAttribute("required");
      hintEl.innerText = "通过后将清空驳回原因并发送通过邮件。";
    }
  }

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
        d.status_filter = statusSelect
          ? statusSelect.value
          : "pending,rejected";
      },
      error: function () {
        if (typeof Swal !== "undefined")
          Swal.fire("错误", "读取作者审核数据失败", "error");
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
          var name = adminEscapeHtml(data.user_name || "-");
          var userId = data.user_id || 0;
          return (
            "<div>" +
            name +
            "</div><small class='text-muted'>UID: " +
            userId +
            "</small>"
          );
        },
      },
      {
        data: "real_name",
        render: function (data) {
          return adminEscapeHtml(data || "-");
        },
      },
      {
        data: "pen_name",
        render: function (data) {
          return adminEscapeHtml(data || "-");
        },
      },
      {
        data: "verification_status",
        render: function (data) {
          var status = (data || "").toLowerCase();
          if (status === "approved")
            return '<span class="badge bg-success">已通过</span>';
          if (status === "rejected")
            return '<span class="badge bg-danger">已驳回</span>';
          return '<span class="badge bg-warning text-dark">待审核</span>';
        },
      },
      {
        data: "reject_reason",
        render: function (data) {
          return adminEscapeHtml(data || "-");
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
          return adminEscapeHtml(data || "-");
        },
      },
      {
        data: null,
        className: "text-center",
        render: function (data) {
          var id = parseInt(data.id || 0, 10);
          var reason = adminEscapeHtml(data.reject_reason || "");
          var html = "";

          if (canApprove) {
            html +=
              '<button type="button" class="btn btn-sm btn-outline-success me-1 btn-author-action" data-id="' +
              id +
              '" data-action="approve" title="通过">通过</button>';
          }
          if (canReject) {
            html +=
              '<button type="button" class="btn btn-sm btn-outline-danger me-1 btn-author-action" data-id="' +
              id +
              '" data-action="reject" data-reason="' +
              reason +
              '" title="驳回">驳回</button>';
          }
          if (canResend) {
            html +=
              '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-author-action" data-id="' +
              id +
              '" data-action="resend" title="重发通知">重发</button>';
          }
          if (canDelete) {
            html +=
              '<button type="button" class="btn btn-sm btn-outline-secondary btn-author-delete" data-id="' +
              id +
              '" title="软删除">删除</button>';
          }

          if (html === "") {
            html = '<span class="text-muted small">无操作权限</span>';
          }

          return '<div class="author-verify-actions">' + html + "</div>";
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

  var debounceTimer = null;
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        table.search(searchInput.value || "").draw();
      }, 250);
    });
  }
  if (perPageSelect) {
    perPageSelect.addEventListener("change", function () {
      table.page.len(parseInt(perPageSelect.value, 10) || 10).draw();
    });
  }
  if (statusSelect) {
    statusSelect.addEventListener("change", function () {
      table.draw();
    });
  }

  tableEl.addEventListener("click", function (event) {
    var actionBtn = event.target.closest(".btn-author-action");
    if (actionBtn) {
      if (!modal || !actionForm || !idInput || !actionTypeSelect) return;
      var rowId = actionBtn.getAttribute("data-id") || "0";
      var actionType = actionBtn.getAttribute("data-action") || "approve";
      var reason = actionBtn.getAttribute("data-reason") || "";
      idInput.value = rowId;
      actionTypeSelect.value = actionType;
      if (rejectInput) rejectInput.value = reason;
      updateRejectReasonUI();
      modal.show();
      return;
    }

    var deleteBtn = event.target.closest(".btn-author-delete");
    if (deleteBtn) {
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
              if (typeof Swal !== "undefined") {
                Swal.fire("成功", payload.message || "删除成功", "success");
              }
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
    }
  });

  if (actionTypeSelect) {
    actionTypeSelect.addEventListener("change", updateRejectReasonUI);
  }
  updateRejectReasonUI();

  if (actionForm) {
    actionForm.addEventListener("submit", function (event) {
      event.preventDefault();
      var formData = new FormData(actionForm);
      formData.set("mode", "verify");
      formData.set("csrf_token", csrfToken);
      var body = new URLSearchParams(formData).toString();

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
            if (
              document.activeElement &&
              typeof document.activeElement.blur === "function"
            ) {
              document.activeElement.blur();
            }
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
  ) {
    return false;
  }
  if (tableEl.dataset.dtReady === "1") return true;

  var apiUrl = appRoot.getAttribute("data-api-url") || "";
  var csrfToken = appRoot.getAttribute("data-csrf") || "";
  if (!apiUrl) return false;

  // Read strictly enforced permissions for Email Template Module
  var canEdit = appRoot.getAttribute("data-can-edit") === "1";
  var canDelete = appRoot.getAttribute("data-can-delete") === "1";

  var searchInput = form.querySelector('input[name="search"]');
  var perPageSelect = form.querySelector('select[name="per_page"]');
  var addBtn = document.getElementById("btnEmailTemplateAdd");
  var modalEl = document.getElementById("emailTemplateModal");
  var templateForm = document.getElementById("emailTemplateForm");

  var idInput = templateForm
    ? templateForm.querySelector('input[name="id"]')
    : null;
  var modeInput = templateForm
    ? templateForm.querySelector('input[name="mode"]')
    : null;
  var codeInput = templateForm
    ? templateForm.querySelector('input[name="template_code"]')
    : null;
  var nameInput = templateForm
    ? templateForm.querySelector('input[name="template_name"]')
    : null;
  var subjectInput = templateForm
    ? templateForm.querySelector('input[name="subject"]')
    : null;
  var contentInput = templateForm
    ? templateForm.querySelector('textarea[name="content"]')
    : null;
  var statusInput = templateForm
    ? templateForm.querySelector('select[name="status"]')
    : null;

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
      error: function () {
        if (typeof Swal !== "undefined")
          Swal.fire("错误", "读取模板数据失败", "error");
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
          return adminEscapeHtml(data || "-");
        },
      },
      {
        data: "template_name",
        render: function (data) {
          return adminEscapeHtml(data || "-");
        },
      },
      {
        data: "subject",
        render: function (data) {
          return adminEscapeHtml(data || "-");
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
          return adminEscapeHtml(data || "-");
        },
      },
      {
        data: null,
        className: "text-center",
        render: function (data) {
          var id = parseInt(data.id || 0, 10);
          var isRequired = data.is_required === true;
          var html = "";

          if (canEdit) {
            html +=
              '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-email-edit" data-id="' +
              id +
              '">编辑</button>';
          }

          if (canDelete && !isRequired) {
            html +=
              '<button type="button" class="btn btn-sm btn-outline-danger btn-email-delete" data-id="' +
              id +
              '">删除</button>';
          }

          if (html === "") {
            html = '<span class="text-muted small">无操作权限</span>';
          }

          return html;
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
    if (
      !templateForm ||
      !idInput ||
      !modeInput ||
      !codeInput ||
      !nameInput ||
      !subjectInput ||
      !contentInput ||
      !statusInput
    )
      return;
    idInput.value = row && row.id ? row.id : "0";
    modeInput.value = mode;
    codeInput.value = row && row.template_code ? row.template_code : "";
    nameInput.value = row && row.template_name ? row.template_name : "";
    subjectInput.value = row && row.subject ? row.subject : "";
    contentInput.value = row && row.content ? row.content : "";
    statusInput.value = row && row.status ? row.status : "A";
  }

  if (addBtn) {
    addBtn.addEventListener("click", function () {
      resetTemplateForm("create", null);
      if (modal) modal.show();
    });
  }

  tableEl.addEventListener("click", function (event) {
    var editBtn = event.target.closest(".btn-email-edit");
    if (editBtn) {
      var editId = String(editBtn.getAttribute("data-id") || "0");
      var row = rowCache[editId];
      if (!row) return;
      resetTemplateForm("update", row);
      if (modal) modal.show();
      return;
    }

    var deleteBtn = event.target.closest(".btn-email-delete");
    if (deleteBtn) {
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
              typeof Swal !== "undefined"
            ) {
              if (typeof window.showNoChangeWarning === "function") {
                window.showNoChangeWarning("无需保存");
              }
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
    }
  });

  if (templateForm) {
    templateForm.addEventListener("submit", function (event) {
      event.preventDefault();
      var formData = new FormData(templateForm);
      if (codeInput && codeInput.value) {
        codeInput.value = String(codeInput.value).toUpperCase().trim();
      }
      formData.set("csrf_token", csrfToken);
      var body = new URLSearchParams(formData).toString();

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
            if (
              document.activeElement &&
              typeof document.activeElement.blur === "function"
            ) {
              document.activeElement.blur();
            }
            if (modal) modal.hide();
            if (typeof Swal !== "undefined")
              Swal.fire("成功", payload.message || "保存成功", "success");
            table.ajax.reload(null, false);
          } else if (
            payload &&
            payload.type === "warning" &&
            typeof Swal !== "undefined"
          ) {
            if (typeof window.showNoChangeWarning === "function") {
              window.showNoChangeWarning("无需保存");
            }
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
  }

  var debounceTimer = null;
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        table.search(searchInput.value || "").draw();
      }, 250);
    });
  }
  if (perPageSelect) {
    perPageSelect.addEventListener("change", function () {
      table.page.len(parseInt(perPageSelect.value, 10) || 10).draw();
    });
  }

  tableEl.dataset.dtReady = "1";
  return true;
}

if (!initPageActionFilter()) {
  window.addEventListener("load", initPageActionFilter);
  setTimeout(initPageActionFilter, 0);
}

if (!initPageInfoFilter()) {
  window.addEventListener("load", initPageInfoFilter);
  setTimeout(initPageInfoFilter, 0);
}

if (!initUserRoleFilter()) {
  window.addEventListener("load", initUserRoleFilter);
  setTimeout(initUserRoleFilter, 0);
}

if (!initAuthorVerificationModule()) {
  window.addEventListener("load", initAuthorVerificationModule);
  setTimeout(initAuthorVerificationModule, 0);
}

if (!initEmailTemplateModule()) {
  window.addEventListener("load", initEmailTemplateModule);
  setTimeout(initEmailTemplateModule, 0);
}
