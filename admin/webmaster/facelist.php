<?php
session_start();
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 3;
if ($user_role === null) {
  echo json_encode(['error' => '未登录，请先登录。'], JSON_UNESCAPED_UNICODE);
  header("Location: ../../pages/auth/login.php");
  exit;
} elseif ($user_role < $required_role) {
  echo json_encode(['error' => '权限不足，无法访问该页面。'], JSON_UNESCAPED_UNICODE);
  header("Location: ../../index.php");
  exit;
}
?>


<!DOCTYPE html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>我的表盘</title>
  <link rel="stylesheet" href="../../files/css/facelist.css">
  <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body>
  <div class="container">
    <h1 class="title">我的表盘管理</h1>
    <div class="search-bar">
      <input type="text" id="search" placeholder="搜索表盘...">
      <button id="searchBtn">搜索</button>
    </div>
    <a href="./facelist_upload.php" class="btn btn-primary">上传表盘</a>
    <a href="./index.php" class="btn btn-primary">个人中心</a>
    <a href="./afd_paylist.php" class="btn btn-primary">爱发电订单中心（测试）</a>
    <a href="../../index.php" class="btn btn-primary">主页</a>
    <br><br>

    <!-- 批量操作 -->
    <button id="bulkDelete" class="btn btn-danger">批量删除</button>
    <button id="bulkEditLimit" class="btn btn-primary">批量修改下载限制</button>
    <input type="number" id="newDownloadsLimit" placeholder="新的下载限制" />

    <table id="watchfaceTable" class="table">
      <thead>
        <tr>
          <th><input type="checkbox" id="selectAll"></th>
          <th>ID</th>
          <th>名称</th>
          <th>预览图</th>
          <th>表盘ID</th>
          <th>状态</th>
          <th>上传时间</th>
          <th>下载次数限制</th>
          <th>计划ID</th>
          <th>归属ID</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <!-- 表盘数据将在这里显示 -->
      </tbody>
    </table>
  </div>

  <!-- 修改/删除弹窗 -->
  <div id="editModal" class="modal" style="display:none;">
    <div class="modal-content">
      <h2 class="modal-title">编辑表盘信息</h2>
      <form id="editForm" class="modal-form">
        <input type="hidden" id="editId">
        <div class="form-group">
          <label for="editName">名称:</label>
          <input type="text" id="editName" required>
        </div>
        <div class="form-group">
          <label for="editWatchfaceId">表盘ID:</label>
          <input type="text" id="editWatchfaceId" required>
        </div>
        <div class="form-group">
          <label for="editStatus">状态:</label>
          <select id="editStatus">
            <option value="1">显示</option>
            <option value="0">隐藏</option>
          </select>
        </div>
        <div class="form-group">
          <label for="editDownloadsLimit">下载次数限制:</label>
          <input type="number" id="editDownloadsLimit" required>
        </div>
        <div class="form-group">
          <label for="editImageLink">表盘图片:</label>
          <input type="text" id="editImageLink" placeholder="不设置就显示订单图片"><br><br>
          <img id="previewImage" src="" alt="预览图" height="150px">
        </div>
        <button type="submit" class="btn btn-primary">保存修改</button>
        <button id="deleteBtn" class="btn btn-danger">删除</button>
      </form>
    </div>
  </div>

  <script>
    $(document).ready(function() {
      loadWatchfaces();

      // 搜索功能
      $('#searchBtn').on('click', function() {
        var query = $('#search').val();
        loadWatchfaces(query);
      });

      // 加载表盘数据
      function loadWatchfaces(query = '') {
        $.ajax({
          url: '../../app/webmaster/facelist_actions.php',
          method: 'POST',
          data: {
            action: 'fetch',
            query: query
          },
          success: function(data) {
            $('#watchfaceTable tbody').html(data);
          }
        });
      }

      // 编辑表盘信息
      $(document).on('click', '.editBtn', function() {
        var id = $(this).data('id');
        $.ajax({
          url: '../../app/webmaster/facelist_actions.php',
          method: 'POST',
          data: {
            action: 'get',
            id: id
          },
          success: function(data) {
            var watchface = JSON.parse(data);
            $('#editId').val(watchface.id);
            $('#editName').val(watchface.name);
            $('#editWatchfaceId').val(watchface.watchface_id);
            $('#editStatus').val(watchface.status);
            $('#editDownloadsLimit').val(watchface.downloads_limit);
            $('#editImageLink').val(watchface.image_link);
            $('#previewImage').attr('src', watchface.image_link || 'default_image.jpg');
            $('#editModal').show();
          }
        });
      });

      // 保存修改
      $('#editForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#editId').val();
        var name = $('#editName').val();
        var watchface_id = $('#editWatchfaceId').val();
        var status = $('#editStatus').val();
        var downloads_limit = $('#editDownloadsLimit').val();
        var image_link = $('#editImageLink').val();
        $.ajax({
          url: '../../app/webmaster/facelist_actions.php',
          method: 'POST',
          data: {
            action: 'update',
            id: id,
            name: name,
            watchface_id: watchface_id,
            status: status,
            downloads_limit: downloads_limit,
            image_link: image_link
          },
          success: function(response) {
            alert(response); // 返回提示
            $('#editModal').hide();
            loadWatchfaces();
          }
        });
      });

      // 删除表盘
      $('#deleteBtn').on('click', function() {
        var id = $('#editId').val();
        if (confirm('确定要删除此表盘吗？')) {
          $.ajax({
            url: '../../app/webmaster/facelist_actions.php',
            method: 'POST',
            data: {
              action: 'delete',
              id: id
            },
            success: function(response) {
              alert(response);
              $('#editModal').hide();
              loadWatchfaces();
            }
          });
        }
      });

      // 批量删除
      $('#bulkDelete').on('click', function() {
        var selected = [];
        $('input[name="selectWatchface"]:checked').each(function() {
          selected.push($(this).val());
        });

        if (selected.length > 0 && confirm('确定要删除选中的表盘吗？')) {
          $.ajax({
            url: '../../app/webmaster/facelist_actions.php',
            method: 'POST',
            data: {
              action: 'bulkDelete',
              ids: selected
            },
            success: function(response) {
              alert(response);
              loadWatchfaces();
            }
          });
        } else {
          alert('请选择要删除的表盘');
        }
      });

      // 批量修改下载限制
      $('#bulkEditLimit').on('click', function() {
        var selected = [];
        var newLimit = $('#newDownloadsLimit').val();
        if (newLimit === '') {
          alert('请输入新的下载限制');
          return;
        }

        $('input[name="selectWatchface"]:checked').each(function() {
          selected.push($(this).val());
        });

        if (selected.length > 0) {
          $.ajax({
            url: '../../app/webmaster/facelist_actions.php',
            method: 'POST',
            data: {
              action: 'bulkEditLimit',
              ids: selected,
              newLimit: newLimit
            },
            success: function(response) {
              alert(response);
              loadWatchfaces();
            }
          });
        } else {
          alert('请选择要修改的表盘');
        }
      });

      // 全选功能
      $('#selectAll').on('click', function() {
        $('input[name="selectWatchface"]').prop('checked', this.checked);
      });
    });
  </script>
</body>

</html>