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
      .then(function (res) {
        return res.text();
      })
      .then(function (text) {
        var cleanText = (text || "").replace(/^\uFEFF/, "").trim();
        var payload = null;

        // 1. Robust JSON Extraction (ignores PHP warnings)
        var jsonStart = cleanText.indexOf("{");
        var jsonEnd = cleanText.lastIndexOf("}");
        if (jsonStart !== -1 && jsonEnd !== -1 && jsonEnd >= jsonStart) {
          try {
            payload = JSON.parse(cleanText.substring(jsonStart, jsonEnd + 1));
          } catch (e) {}
        }

        // 2. Handle the Payload
        if (payload) {
          if (payload.success) {
            if (typeof Swal !== "undefined") {
              Swal.fire({
                title: "删除成功！",
                icon: "success",
                confirmButtonText: "OK",
              }).then(function () {
                window.location.reload(); // Refresh the page to show updated list
              });
            } else {
              window.location.reload();
            }
          } else {
            if (typeof Swal !== "undefined") {
              Swal.fire("错误", payload.message || "删除失败", "error");
            }
          }
          return;
        }

        // 3. Fallback if JSON completely failed but success was printed
        if (
          cleanText.indexOf('"success":true') !== -1 ||
          cleanText.indexOf('"success": true') !== -1
        ) {
          if (typeof Swal !== "undefined") {
            Swal.fire("删除成功！", "", "success").then(function () {
              window.location.reload();
            });
          } else {
            window.location.reload();
          }
          return;
        }

        // If it reaches here, the server truly returned an error
        if (typeof Swal !== "undefined") {
          Swal.fire("错误", "服务器返回异常数据", "error");
        }
      })
      .catch(function (err) {
        if (typeof Swal !== "undefined") {
          Swal.fire("错误", "网络请求或脚本执行失败", "error");
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
          title: "确认删除？",
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
        text: "此操作将执行删除。",
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
      if (confirm("确定删除? 此操作将执行删除。")) {
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
  if (!headerEl) return;

  var currentBody = headerEl.nextElementSibling;
  var currentIcon = headerEl.querySelector(".page-card-chevron");
  if (!currentBody) return;

  var willOpen =
    currentBody.style.display === "none" || !currentBody.style.display;
  var isDesktopView = document.body.classList.contains("is-desktop");

  function applyCardState(cardHeader, openState) {
    if (!cardHeader) return;
    var cardBody = cardHeader.nextElementSibling;
    var cardIcon = cardHeader.querySelector(".page-card-chevron");
    if (cardBody) {
      cardBody.style.display = openState ? "block" : "none";
    }
    if (cardIcon) {
      cardIcon.classList.toggle("fa-chevron-down", !openState);
      cardIcon.classList.toggle("fa-chevron-up", openState);
    }
  }

  if (!isDesktopView) {
    applyCardState(headerEl, willOpen);
    return;
  }

  var card = headerEl.closest(".page-card");
  var container = document.getElementById("pageCardsContainer");
  if (!card || !container) {
    applyCardState(headerEl, willOpen);
    return;
  }

  var visibleCards = Array.from(
    container.querySelectorAll(".page-card"),
  ).filter(function (item) {
    return item.offsetParent !== null;
  });

  var index = visibleCards.indexOf(card);
  if (index === -1) {
    applyCardState(headerEl, willOpen);
    return;
  }

  var rowStart = Math.floor(index / 3) * 3;
  var rowCards = visibleCards.slice(rowStart, rowStart + 3);

  rowCards.forEach(function (rowCard) {
    var rowHeader = rowCard.querySelector(".page-card-header");
    applyCardState(rowHeader, willOpen);
  });
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

function syncMobileFooterForTable(tableEl) {
  if (!tableEl) return;

  var wrapper = document.getElementById(tableEl.id + "_wrapper");
  if (!wrapper) return;

  var cardBody = tableEl.closest(".card-body");
  if (!cardBody) return;

  var mobileList = cardBody.querySelector(".page-action-mobile-list");
  var footerRow = wrapper.querySelector(".row.mt-3.align-items-center");
  if (!mobileList || !footerRow) return;

  var isMobileView = document.body.classList.contains("is-mobile");
  if (isMobileView) {
    if (mobileList.parentElement !== wrapper) {
      wrapper.insertBefore(mobileList, footerRow);
    }
  } else {
    if (mobileList.parentElement !== cardBody) {
      cardBody.appendChild(mobileList);
    }
    if (footerRow.parentElement !== wrapper) {
      wrapper.appendChild(footerRow);
    }
  }
}

function syncMobileCardsForTable(table, tableEl) {
  if (!table || !tableEl) return;

  var cardBody = tableEl.closest(".card-body");
  if (!cardBody) return;

  var mobileList = cardBody.querySelector(".page-action-mobile-list");
  if (!mobileList) return;

  var items = Array.from(
    mobileList.querySelectorAll(".page-action-mobile-item"),
  );
  if (!items.length) return;

  var isMobileView = document.body.classList.contains("is-mobile");
  if (!isMobileView) {
    items.forEach(function (item) {
      item.style.display = "";
    });
    return;
  }

  var currentPageIndexes = table
    .rows({ search: "applied", order: "applied", page: "current" })
    .indexes()
    .toArray();
  var visibleSet = new Set(currentPageIndexes);

  items.forEach(function (item, index) {
    item.style.display = visibleSet.has(index) ? "" : "none";
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

  function syncPageActionMobileFooter() {
    syncMobileFooterForTable(tableEl);
    syncMobileCardsForTable(table, tableEl);
  }

  table.on("draw", syncPageActionMobileFooter);
  window.addEventListener("resize", syncPageActionMobileFooter);
  setTimeout(syncPageActionMobileFooter, 0);

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

  function syncPageInfoMobileFooter() {
    syncMobileFooterForTable(tableEl);
    syncMobileCardsForTable(table, tableEl);
  }

  table.on("draw", syncPageInfoMobileFooter);
  window.addEventListener("resize", syncPageInfoMobileFooter);
  setTimeout(syncPageInfoMobileFooter, 0);

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

  function syncUserRoleMobileFooter() {
    syncMobileFooterForTable(tableEl);
    syncMobileCardsForTable(table, tableEl);
  }

  table.on("draw", syncUserRoleMobileFooter);
  window.addEventListener("resize", syncUserRoleMobileFooter);
  setTimeout(syncUserRoleMobileFooter, 0);

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

function renderInlineFormAlert(form, message, type) {
  if (typeof Swal !== "undefined") {
    // Map Bootstrap alert classes to SweetAlert icons
    var swalType = "error";
    var title = "操作失败";

    if (type === "success") {
      swalType = "success";
      title = "操作成功";
    } else if (type === "warning") {
      swalType = "warning";
      title = "警告";
    } else if (type === "info") {
      swalType = "info";
      title = "提示";
    }

    Swal.fire({
      icon: swalType,
      title: title,
      text: message || "操作失败",
      confirmButtonColor: "#233dd2",
      confirmButtonText: "我知道了",
    });
  } else {
    // Fallback just in case SweetAlert hasn't loaded
    alert(message || "操作失败");
  }
}

function extractAlertFromHtmlResponse(htmlText) {
  try {
    var parser = new DOMParser();
    var doc = parser.parseFromString(htmlText || "", "text/html");
    var alertEl = doc.querySelector(".alert");
    if (!alertEl) return null;

    var alertType = "danger";
    if (alertEl.classList.contains("alert-success")) alertType = "success";
    else if (alertEl.classList.contains("alert-warning")) alertType = "warning";
    else if (alertEl.classList.contains("alert-info")) alertType = "info";

    var clone = alertEl.cloneNode(true);
    var closeBtn = clone.querySelector(".btn-close");
    if (closeBtn) closeBtn.remove();

    var text = (clone.textContent || "").trim();
    if (!text) return null;

    return { message: text, type: alertType };
  } catch (e) {
    return null;
  }
}

function extractMessageFromMixedResponse(rawText) {
  var text = (rawText || "").replace(/^\uFEFF/, "").trim();
  if (!text) return "";

  var jsonMatch = text.match(
    /\{[\s\S]*?"message"\s*:\s*"(?:\\.|[^"\\])*"[\s\S]*?\}/,
  );
  if (jsonMatch && jsonMatch[0]) {
    try {
      var parsed = JSON.parse(jsonMatch[0]);
      if (parsed && parsed.message) {
        return String(parsed.message).trim();
      }
    } catch (e) {}
  }

  var msgMatch = text.match(/"message"\s*:\s*"((?:\\.|[^"\\])*)"/);
  if (msgMatch && msgMatch[1]) {
    try {
      return JSON.parse('"' + msgMatch[1] + '"').trim();
    } catch (e) {
      return msgMatch[1].replace(/\\"/g, '"').trim();
    }
  }

  return "";
}

function initInlineAjaxAdminForms() {
  var forms = document.querySelectorAll('form[data-ajax-error-inline="1"]');
  if (!forms.length) return;

  forms.forEach(function (form) {
    if (form.dataset.ajaxInlineBound === "1") return;
    form.dataset.ajaxInlineBound = "1";

    form.addEventListener("submit", function (event) {
      if (event.defaultPrevented) return;

      // Required validation logic
      var requiredInputs = form.querySelectorAll(
        "input[required], select[required], textarea[required]",
      );
      var hasRequiredError = false;
      requiredInputs.forEach(function (input) {
        if (input.type === "checkbox" || input.type === "radio") {
          if (!input.checked) hasRequiredError = true;
          return;
        }
        if ((input.value || "").trim() === "") hasRequiredError = true;
      });
      if (hasRequiredError) return;

      // Check changes logic
      if (
        form.classList.contains("check-changes") &&
        form.dataset.originalData
      ) {
        var hasFile = false;
        var fileInputs = form.querySelectorAll('input[type="file"]');
        fileInputs.forEach(function (input) {
          if (input.files && input.files.length > 0) hasFile = true;
        });

        var currentData = new URLSearchParams(new FormData(form)).toString();
        if (!hasFile && currentData === form.dataset.originalData) {
          if (typeof window.showNoChangeWarning === "function")
            window.showNoChangeWarning("无需保存");
          return;
        }
      }

      event.preventDefault();

      var body = new URLSearchParams(new FormData(form)).toString();
      fetch(form.getAttribute("action"), {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
        body: body,
      })
        .then(function (res) {
          return res.text();
        })
        .then(function (text) {
          var cleanText = (text || "").replace(/^\uFEFF/, "").trim();
          var payload = null;

          // Secure JSON Extraction
          var jsonStart = cleanText.indexOf("{");
          var jsonEnd = cleanText.lastIndexOf("}");
          if (jsonStart !== -1 && jsonEnd !== -1 && jsonEnd >= jsonStart) {
            try {
              payload = JSON.parse(cleanText.substring(jsonStart, jsonEnd + 1));
            } catch (e) {}
          }

          // [FIX] URL Sanitizer to remove double slashes (e.g. localhost:8000//dashboard.php)
          function sanitizeUrl(url) {
            if (!url) return "";
            // Unescape \/ and remove // except when following http: or https:
            return url
              .replace(/\\\//g, "/")
              .replace(/([^:]\/)\/+/g, "$1")
              .replace(/^\/\//, "/");
          }

          var cancelBtn =
            form.querySelector("a.btn-light") ||
            document.querySelector("a.btn-outline-secondary");
          var fallbackListUrl = cancelBtn
            ? cancelBtn.getAttribute("href")
            : window.location.pathname +
              "?view=" +
              (new URLSearchParams(window.location.search).get("view") || "");

          // --- SCENARIO A: Valid JSON Payload ---
          if (payload) {
            if (payload.success) {
              window.location.replace(
                sanitizeUrl(payload.redirect || fallbackListUrl),
              );
            } else {
              renderInlineFormAlert(
                form,
                payload.message || "保存失败",
                "danger",
              );
            }
            return;
          }

          // --- SCENARIO B: Fallback if JSON fails entirely ---
          if (cleanText.indexOf("无需保存") !== -1) {
            if (typeof window.showNoChangeWarning === "function")
              window.showNoChangeWarning("无需保存");
            return;
          }

          var isSuccess =
            cleanText.indexOf('"success":true') !== -1 ||
            cleanText.indexOf('"success": true') !== -1;
          if (isSuccess) {
            var redirectMatch = cleanText.match(/"redirect"\s*:\s*"([^"]+)"/);
            if (redirectMatch && redirectMatch[1]) {
              window.location.replace(sanitizeUrl(redirectMatch[1]));
            } else {
              window.location.replace(sanitizeUrl(fallbackListUrl));
            }
            return;
          }

          var mixedMessage =
            typeof extractMessageFromMixedResponse === "function"
              ? extractMessageFromMixedResponse(cleanText)
              : null;
          if (mixedMessage) {
            renderInlineFormAlert(form, mixedMessage, "danger");
            return;
          }

          renderInlineFormAlert(
            form,
            "服务器通信失败或返回了异常数据",
            "danger",
          );
        })
        .catch(function (err) {
          renderInlineFormAlert(form, "网络请求失败", "danger");
        });
    });
  });
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

window.addEventListener("load", initInlineAjaxAdminForms);
setTimeout(initInlineAjaxAdminForms, 0);
