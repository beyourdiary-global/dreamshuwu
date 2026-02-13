(function () {
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

  var mobileItems = document.querySelectorAll(".page-action-mobile-item");
  mobileItems.forEach(function (item) {
    var head = item.querySelector(".page-action-mobile-head");
    if (!head) return;
    head.addEventListener("click", function () {
      item.classList.toggle("open");
    });
  });
})();
