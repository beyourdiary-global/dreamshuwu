// Path: src/assets/js/chapter.js
$(document).ready(function () {
  const appContainer = $("#chapterApp");
  if (appContainer.length === 0) return;

  const API_URL = appContainer.data("api-url");
  const NOVEL_ID = appContainer.data("novel-id");

  let chapterTable;
  let autoSaveTimer = null;
  let lastSavedContent = "";
  let sensitiveWords = [];

  // 1. Fetch Sensitive Words to cache in frontend for fast scanning
  $.get(
    API_URL,
    { mode: "get_sensitive_words", novel_id: NOVEL_ID },
    function (res) {
      if (res.success) sensitiveWords = res.data;
    },
  );

  // 2. Initialize DataTables
  chapterTable = $("#chapterTable").DataTable({
    processing: true,
    serverSide: true,
    responsive: true,
    ajax: {
      url: API_URL,
      type: "POST",
      data: function (d) {
        d.mode = "data";
        d.novel_id = NOVEL_ID;
      },
    },
    columns: [
      { data: "chapter_number", className: "text-center fw-bold" },
      { data: "title", className: "fw-bold text-dark" },
      { data: "word_count" },
      {
        data: "publish_status",
        render: function (data) {
          if (data === "published")
            return '<span class="badge bg-success">已发布</span>';
          if (data === "scheduled")
            return '<span class="badge bg-info">定时发布</span>';
          return '<span class="badge bg-secondary">草稿</span>';
        },
      },
      { data: "scheduled_publish_at", render: (data) => (data ? data : "-") },
      {
        data: "version_count",
        render: (data, type, row) =>
          `<button class="btn btn-sm btn-link btn-versions" data-id="${row.id}">${data} 版本</button>`,
      },
      { data: "updated_at", className: "small text-muted" },
      {
        data: "id",
        orderable: false,
        render: function (data) {
          // --- UPDATED: Permission-based button rendering ---
          let buttons = `<div class="d-flex justify-content-center gap-2">`;

          if (typeof PERM_CAN_EDIT !== "undefined" && PERM_CAN_EDIT) {
            buttons += `<button class="btn btn-sm btn-outline-primary btn-edit" data-id="${data}" title="编辑"><i class="fa-solid fa-pen"></i></button>`;
          }

          if (typeof PERM_CAN_DELETE !== "undefined" && PERM_CAN_DELETE) {
            buttons += `<button class="btn btn-sm btn-outline-danger btn-delete" data-id="${data}" title="删除"><i class="fa-solid fa-trash"></i></button>`;
          }

          buttons += `</div>`;
          return buttons;
        },
      },
    ],
    language: {
      sProcessing: "处理中...",
      sLengthMenu: "显示 _MENU_ 项结果",
      sZeroRecords: "没有匹配结果",
      sInfo: "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
      sInfoEmpty: "显示第 0 至 0 项结果，共 0 项",
      sInfoFiltered: "(由 _MAX_ 项结果过滤)",
      sInfoPostFix: "",
      sSearch: "搜索 (Search):",
      sUrl: "",
      sEmptyTable: "表中数据为空",
      sLoadingRecords: "载入中...",
      sInfoThousands: ",",
      oPaginate: {
        sFirst: "首页",
        sPrevious: "上一页",
        sNext: "下一页",
        sLast: "末页",
      },
      oAria: {
        sSortAscending: ": 以升序排列此列",
        sSortDescending: ": 以降序排列此列",
      },
    },
  });

  // 3. UI Toggles
  $("#publish_status").on("change", function () {
    if ($(this).val() === "scheduled") {
      $("#scheduleTimeContainer").removeClass("d-none");
      $("#scheduled_publish_at").attr("required", true);
    } else {
      $("#scheduleTimeContainer").addClass("d-none");
      $("#scheduled_publish_at").attr("required", false).val("");
    }
  });

  // 4. Strict Content Filter & Word Counter
  $("#chapter_content").on("input", function () {
    let val = $(this).val();

    // Remove URLs and basic HTML instantly (UX level strictness)
    val = val.replace(/<[^>]*>?/gm, ""); // Strip HTML
    val = val.replace(/[a-zA-Z]+:\/\/[^\s]+/g, ""); // Strip URLs

    if (val !== $(this).val()) {
      $(this).val(val);
    } // apply stripped val back

    // Count Words (Remove all whitespaces, count characters)
    let cleanText = val.replace(/\s+/g, "");
    let count = cleanText.length;
    $("#wordCountNum").text(count);

    let box = $("#wordCountBox");
    box.removeClass("word-warning word-danger text-success");
    if (count < 300) box.addClass("word-danger");
    else if (count >= 50000) box.addClass("word-danger");
    else if (count >= 20000) box.addClass("word-warning");
    else box.addClass("text-success");
  });

  // 5. Save Logic (Manual)
  $("#btnSaveChapter").on("click", function () {
    saveChapter("save");
  });

  function saveChapter(saveMode) {
    const form = $("#chapterForm")[0];
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    // Quick frontend Sensitive Word Scan (UX warning before backend)
    let text = $("#chapter_content").val();
    let blockWord = sensitiveWords.find(
      (w) => w.severity_level == 3 && text.includes(w.word),
    );
    if (blockWord && saveMode === "save") {
      Swal.fire(
        "违规内容",
        `内容包含违禁词汇 [${blockWord.word}]，禁止保存！`,
        "error",
      );
      return;
    }

    let formData = new FormData(form);
    formData.append("mode", saveMode);

    if (saveMode === "save")
      $("#btnSaveChapter")
        .prop("disabled", true)
        .html('<i class="fa-solid fa-spinner fa-spin"></i> 保存中...');

    $.ajax({
      url: API_URL,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (res) {
        if (res.success) {
          $("#edit_chapter_id").val(res.chapter_id);
          lastSavedContent = text;
          if (saveMode === "save") {
            Swal.fire({
              icon: "success",
              title: "成功",
              text: res.message,
              timer: 1500,
              showConfirmButton: false,
            });
            chapterTable.ajax.reload(null, false);
          } else {
            // Auto-Save UI
            let now = new Date();
            $("#autoSaveTime").text(
              now.getHours() +
                ":" +
                String(now.getMinutes()).padStart(2, "0") +
                ":" +
                String(now.getSeconds()).padStart(2, "0"),
            );
            $("#autoSaveIndicator")
              .removeClass("d-none")
              .fadeIn()
              .delay(3000)
              .fadeOut();
          }
        } else {
          if (saveMode === "save") Swal.fire("错误", res.message, "error");
        }
      },
      error: function (xhr) {
        // Handle 400 Bad Request for Level 3 blocks
        if (xhr.status === 400) {
          let res = xhr.responseJSON;
          Swal.fire(
            "违规拦截",
            res.message || "由于违规词汇，系统已拒绝保存。",
            "error",
          );
        } else {
          Swal.fire("网络错误", "无法连接到服务器", "error");
        }
      },
      complete: function () {
        if (saveMode === "save")
          $("#btnSaveChapter")
            .prop("disabled", false)
            .html('<i class="fa-solid fa-cloud-arrow-up"></i> 保存章节');
      },
    });
  }

  // 6. Auto-Save (Every 30 seconds if content changed)
  autoSaveTimer = setInterval(function () {
    let currentContent = $("#chapter_content").val().trim();
    let title = $("#chapter_title").val().trim();
    if (
      currentContent !== "" &&
      title !== "" &&
      currentContent !== lastSavedContent
    ) {
      saveChapter("auto_save");
    }
  }, 30000);

  // 6.5 Real-Time Sensitive Word Scanner (Every 1.5 seconds)
  setInterval(function () {
    let contentBox = $("#chapter_content");
    if (contentBox.length === 0) return;

    let text = contentBox.val();
    if (!text || sensitiveWords.length === 0) return;

    let originalText = text;
    let triggeredLevel2 = false;

    // Helper to safely escape regex characters in words
    const escapeRegExp = (string) =>
      string.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

    // Scan and replace Level 1 and Level 2 words
    sensitiveWords.forEach((rule) => {
      if (rule.severity_level == 1 || rule.severity_level == 2) {
        let regex = new RegExp(escapeRegExp(rule.word), "gi");

        if (regex.test(text)) {
          text = text.replace(regex, rule.replacement);
          if (rule.severity_level == 2) {
            triggeredLevel2 = true;
          }
        }
      }
    });

    // If changes were made, update the textarea in real-time
    if (text !== originalText) {
      // Save cursor position
      let cursorStart = contentBox.prop("selectionStart");
      let cursorEnd = contentBox.prop("selectionEnd");
      let lengthDiff = text.length - originalText.length;

      contentBox.val(text);
      contentBox.trigger("input");

      // Restore cursor position
      contentBox.prop("selectionStart", cursorStart + lengthDiff);
      contentBox.prop("selectionEnd", cursorEnd + lengthDiff);

      if (triggeredLevel2) {
        Swal.fire({
          toast: true,
          position: "top-end",
          icon: "warning",
          title: "系统提示",
          text: "包含不当词汇，已自动替换",
          showConfirmButton: false,
          timer: 3000,
          timerProgressBar: true,
        });
      }
    }
  }, 1500);

  // 7. Edit Chapter
  $("#chapterTable").on("click", ".btn-edit", function () {
    const id = $(this).data("id");
    $.get(
      API_URL,
      { mode: "get", novel_id: NOVEL_ID, chapter_id: id },
      function (res) {
        if (res.success) {
          $("#edit_chapter_id").val(res.data.id);
          $("#chapter_number").val(res.data.chapter_number);
          $("#chapter_title").val(res.data.title);
          $("#chapter_content").val(res.data.content).trigger("input");
          $("#publish_status").val(res.data.publish_status).trigger("change");
          if (res.data.scheduled_publish_at) {
            $("#scheduled_publish_at").val(res.data.scheduled_publish_at);
          }
          lastSavedContent = res.data.content;
          $("html, body").animate({ scrollTop: 0 }, "slow");
        }
      },
    );
  });

  // 8. Delete Chapter
  $("#chapterTable").on("click", ".btn-delete", function () {
    const id = $(this).data("id");
    Swal.fire({
      title: "确认删除？",
      text: "该章节将被移至回收站",
      icon: "warning",
      showCancelButton: true,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          API_URL,
          {
            mode: "delete",
            novel_id: NOVEL_ID,
            chapter_id: id,
            csrf_token: $('input[name="csrf_token"]').val(),
          },
          function (res) {
            if (res.success) {
              chapterTable.ajax.reload(null, false);
            } else {
              Swal.fire("错误", res.message, "error");
            }
          },
          "json",
        );
      }
    });
  });

  $("#btnResetEditor").click(() => {
    $("#chapterForm")[0].reset();
    $("#edit_chapter_id").val("0");
    $("#chapter_content").trigger("input");
    $("#publish_status").trigger("change");
  });

  // 9. Version History Modal Logic (MOVED INSIDE READY FUNCTION)
  $("#chapterTable").on("click", ".btn-versions", function () {
    const id = $(this).data("id");
    const modal = new bootstrap.Modal(document.getElementById("versionModal"));

    $("#versionTableBody").html(
      '<tr><td colspan="4" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> 载入中...</td></tr>',
    );
    modal.show();

    $.get(
      API_URL,
      { mode: "get_versions", novel_id: NOVEL_ID, chapter_id: id },
      function (res) {
        if (res.success && res.data.length > 0) {
          let html = "";
          res.data.forEach((v) => {
            html += `
              <tr>
                <td class="fw-bold">v${v.version_number}</td>
                <td class="small">${v.created_at}</td>
                <td>${v.word_count} 词</td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary btn-restore" 
                          data-chapter-id="${id}" 
                          data-version-id="${v.id}">
                    <i class="fa-solid fa-rotate-left"></i> 预览/恢复
                  </button>
                </td>
              </tr>`;
          });
          $("#versionTableBody").html(html);
        } else {
          $("#versionTableBody").html(
            '<tr><td colspan="4" class="text-center text-muted">暂无历史版本</td></tr>',
          );
        }
      },
    );
  });

  // 10. Restore Version Logic (MOVED INSIDE READY FUNCTION)
  $(document).on("click", ".btn-restore", function () {
    const chapterId = $(this).data("chapter-id");
    const versionId = $(this).data("version-id");

    $.get(
      API_URL,
      { mode: "get_version_detail", novel_id: NOVEL_ID, version_id: versionId },
      function (res) {
        if (res.success) {
          $("#edit_chapter_id").val(chapterId);
          $("#chapter_title").val(res.data.title);
          $("#chapter_content").val(res.data.content).trigger("input");

          bootstrap.Modal.getInstance(
            document.getElementById("versionModal"),
          ).hide();
          $("html, body").animate({ scrollTop: 0 }, "slow");

          Swal.fire({
            icon: "info",
            title: "已加载版本 v" + res.data.version_number,
            text: "内容已加载到编辑器，点击“保存章节”以应用更改。",
            timer: 3000,
          });
        }
      },
    );
  });
});
