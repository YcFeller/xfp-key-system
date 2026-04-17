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
  <title>站长管理首页</title>
  <link rel="stylesheet" href="../../files/css/admin/wm_index.css">
  <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body>

  <!-- 站长管理首页的导航栏 -->
  <nav>
    <ul>
      <li><a href="dashboard.php">仪表盘</a></li>
      <li><a href="orderlist.php">订单管理</a></li>
      <li><a href="activation_records.php">激活记录管理</a></li>
      <li><a href="quickapplist.php">快应用管理</a></li>
      <li><a href="userlist.php">用户管理</a></li>
      <li><a href="security_dashboard.php">安全管理</a></li>
      <li><a href="system_settings.php">系统设置</a></li>
      <li><a href="logs.php">日志管理</a></li>
      <li><a href="permission_review.php">权限审核</a></li>
      <li><a href="database_update.php">数据库更新</a></li>
    </ul>
  </nav>

  <h1>站长管理首页</h1>

  <div class="dashboard">
    <div class="dashboard-item">
      <h2>订单管理</h2>
      <p>查看和管理所有订单</p>
      <a href="orderlist.php">进入订单管理</a>
    </div>

    <div class="dashboard-item">
      <h2>表盘管理</h2>
      <p>查看和管理所有表盘</p>
      <a href="facelist.php">进入表盘管理</a>
    </div>

    <div class="dashboard-item">
      <h2>快应用管理</h2>
      <p>查看和管理所有快应用</p>
      <a href="quickapplist.php">进入快应用管理</a>
    </div>

    <div class="dashboard-item">
      <h2>用户管理</h2>
      <p>管理系统中的所有用户信息</p>
      <a href="userlist.php">进入用户管理</a>
    </div>

    <div class="dashboard-item">
      <h2>安全管理</h2>
      <p>查看安全日志、管理IP黑名单和系统监控</p>
      <a href="security_dashboard.php">进入安全管理</a>
    </div>

    <div class="dashboard-item">
      <h2>系统设置</h2>
      <p>配置和管理系统设置</p>
      <a href="system_settings.php">进入系统设置</a>
    </div>

    <div class="dashboard-item">
      <h2>日志管理</h2>
      <p>查看系统日志记录</p>
      <a href="logs.php">进入日志管理</a>
    </div>

    <div class="dashboard-item">
      <h2>权限审核</h2>
      <p>审核用户权限申请，管理开发者权限</p>
      <a href="permission_review.php">进入权限审核</a>
    </div>

    <div class="dashboard-item">
      <h2>数据库更新</h2>
      <p>管理数据库结构更新，同步新功能</p>
      <a href="database_update.php">进入数据库更新</a>
    </div>
  </div>

  <script>
    // 示例的动态功能：你可以根据需要在这里添加AJAX请求等
    $(document).ready(function() {
      // 可在这里添加一些初始化代码
    });
  </script>

</body>

</html>