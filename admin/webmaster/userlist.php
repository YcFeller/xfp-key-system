<?php
session_start();
$user_role = $_SESSION['user_role'] ?? null;
if ($user_role === null || $user_role < 3) {
  header("Location: ../../index.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>用户管理</title>
  <link rel="stylesheet" href="../../files/css/admin/wm_userlist.css">
  <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body>
  <div class="container">
    <h2>用户管理</h2>
    <!-- 当前用户信息 -->
    <div class="user-info">
      <p>当前用户：<?php echo htmlspecialchars($_SESSION['username']); ?></p>
      <p>角色：<?php echo htmlspecialchars($_SESSION['user_role']); ?></p>
      <p>角色ID：<?php echo htmlspecialchars($_SESSION['user_id']); ?></p>
      <a href="../../pages/auth/logout.php">退出登录</a>
    </div>

    <!-- 用户搜索与筛选 -->
    <div class="filter-section">
      <input type="text" id="searchQuery" placeholder="搜索用户名..." class="filter-input">
      <select id="roleFilter" class="filter-select">
        <option value="">选择角色</option>
        <option value="1">客户</option>
        <option value="2">普通用户</option>
        <option value="3">管理员</option>
      </select>
      <select id="statusFilter" class="filter-select">
        <option value="">选择状态</option>
        <option value="1">激活</option>
        <option value="0">禁用</option>
      </select>
      <button id="searchBtn" class="filter-btn">搜索</button>
    </div>

    <!-- 用户列表 -->
    <div class="table-container">
      <table class="user-table">
        <thead>
          <tr>
            <th>选择</th>
            <th>ID</th>
            <th>用户名</th>
            <th>邮箱</th>
            <th>角色</th>
            <th>状态</th>
            <th>头像</th>
            <th>Afdian Token</th>
            <th>快速登录</th>
            <th>编辑</th>
            <th>删除</th>
          </tr>
        </thead>
        <tbody id="userList">
          <!-- 用户列表数据将通过 AJAX 填充 -->
        </tbody>
      </table>
    </div>

    <!-- 编辑用户模态框 -->
    <div id="editModal" class="modal">
      <input type="hidden" id="editUserId">
      <input type="text" id="editUsername" placeholder="用户名">
      <input type="email" id="editEmail" placeholder="邮箱">
      <select id="editRole">
        <option value="1">客户</option>
        <option value="2">用户</option>
        <option value="3">管理员</option>
      </select>
      <select id="editStatus">
        <option value="1">激活</option>
        <option value="0">禁用</option>
      </select>
      <input type="text" id="editAfdianToken" placeholder="Afdian Token">
      <input type="password" id="editPassword" placeholder="密码（可选）">
      <button id="saveBtn">保存</button>
      <button id="cancelBtn">取消</button>
    </div>
  </div>

  <script>
    // 加载用户列表
    $(document).ready(function() {
      $.ajax({
        url: '../../app/webmaster/user_actions.php',
        method: 'POST',
        data: {
          action: 'fetch'
        },
        success: function(response) {
          $('#userList').html(response);
        }
      });
    });

    // 搜索按钮点击事件
    $('#searchBtn').click(function() {
      var query = $('#searchQuery').val();
      var role = $('#roleFilter').val();
      var status = $('#statusFilter').val();

      $.ajax({
        url: '../../app/webmaster/user_actions.php',
        method: 'POST',
        data: {
          action: 'fetch',
          query: query,
          role: role,
          status: status
        },
        success: function(response) {
          $('#userList').html(response);
        },
        error: function(xhr, status, error) {
          console.error('AJAX错误: ', error);
        }
      });
    });


    // 编辑用户按钮点击事件
    $(document).on('click', '.editBtn', function() {
      var userId = $(this).data('user-id');

      $.ajax({
        url: '../../app/webmaster/user_actions.php',
        method: 'POST',
        data: {
          action: 'get',
          user_id: userId
        },
        success: function(response) {
          var user = JSON.parse(response);
          $('#editUserId').val(user.id);
          $('#editUsername').val(user.username);
          $('#editEmail').val(user.email);
          $('#editRole').val(user.role);
          $('#editStatus').val(user.status);
          $('#editAfdianToken').val(user.afdian_token);
          $('#editModal').show();
        }
      });
    });

    // 保存编辑
    $('#saveBtn').click(function() {
      var userId = $('#editUserId').val();
      var username = $('#editUsername').val();
      var email = $('#editEmail').val();
      var role = $('#editRole').val();
      var status = $('#editStatus').val();
      var afdianToken = $('#editAfdianToken').val();
      var password = $('#editPassword').val();

      $.ajax({
        url: '../../app/webmaster/user_actions.php',
        method: 'POST',
        data: {
          action: 'update',
          user_id: userId,
          username: username,
          email: email,
          role: role,
          status: status,
          afdian_token: afdianToken,
          password: password
        },
        success: function(response) {
          alert(response);
          $('#editModal').hide();
          $('#searchBtn').click(); // 重新加载用户列表
        }
      });
    });

    // 删除用户
    $(document).on('click', '.deleteBtn', function() {
      var userId = $(this).data('user-id');

      if (confirm('确定删除此用户吗？')) {
        $.ajax({
          url: '../../app/webmaster/user_actions.php',
          method: 'POST',
          data: {
            action: 'delete',
            user_id: userId
          },
          success: function(response) {
            alert(response);
            $('#searchBtn').click(); // 重新加载用户列表
          }
        });
      }
    });

    // 取消编辑
    $('#cancelBtn').click(function() {
      $('#editModal').hide();
    });

    // 快速登录按钮点击事件
    $(document).on('click', '.quickLoginBtn', function() {
      var userId = $(this).data('user-id');
      var username = $(this).data('username');
      var role = $(this).data('role');

      $.ajax({
        url: '../../app/webmaster/user_actions.php',
        method: 'POST',
        data: {
          action: 'quick_login',
          user_id: userId
        },
        success: function(response) {
          alert(response);
          window.location.reload();
        }
      });
    });
  </script>

</body>

</html>