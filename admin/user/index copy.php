<?php
session_start();

$user_role = $_SESSION['user_role'] ?? null;
$required_role = 2;

if ($user_role === null) {
  header("Location: ../../pages/auth/login.php");
  exit;
} elseif ($user_role < $required_role) {
  header("Location: ../../index.php");
  exit;
}

require_once '../../app/config.php';

$user_id = $_SESSION['user_id'];

$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
  die("连接失败: " . $conn->connect_error);
}

$sql = "SELECT username, afdian_user_id, afdian_token, email, avatar_link FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
  die('用户信息未找到');
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>XFP - 个人中心</title>
  <link rel="stylesheet" href="../../files/css/user-index.css">
  <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body>
  <header class="header">
    <img src="../../files/imgs/logo.png" alt="Logo" class="logo">
    <div class="header-info">
      <span class="username">用户名: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
      <img src="<?php echo htmlspecialchars($user['avatar_link']); ?>" alt="User Image" class="user-img">
    </div>
  </header>

  <nav class="sidebar">
    <ul>
      <li><a href="./index.php">个人中心</a></li>
      <li><a href="./facelist.php">我的表盘</a></li>
      <li><a href="./orderlist.php">订单中心</a></li>
      <li><a href="./afd_paylist.php">获取爱发电订单(一次就好)</a></li>
      <li><a href="./facelist_upload.php">上传表盘</a></li>
      <li><a href="./shortcut_tool.php">快捷工具</a></li>
      <li><a href="../../index.php">返回主页</a></li>
      <li><a href="../../pages/auth/logout.php">退出登录</a></li>

    </ul>
  </nav>

  <main class="main-content">
    <h1>个人中心</h1>
    <p>用户名：<?php echo htmlspecialchars($_SESSION['username']); ?></p>
    <p>用户ID：<?php echo htmlspecialchars($_SESSION['user_id']); ?></p>
    <p>用户权限：
      <?php
      if ($_SESSION['user_role'] == 1) {
        echo "客户";
      } elseif ($_SESSION['user_role'] == 2) {
        echo "用户";
      } elseif ($_SESSION['user_role'] == 3) {
        echo "管理员";
      } else {
        echo "你从那你来？又到那里去？";
      }
      ?>
    </p>

    <form id="profileForm" class="profile-form">
      <div class="form-group">
        <label for="email">邮箱:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
      </div>
      <div class="form-group">
        <label for="afdian_user_id">爱发电用户ID:</label>
        <input type="text" id="afdian_user_id" name="afdian_user_id" value="<?php echo htmlspecialchars($user['afdian_user_id']); ?>" required>
      </div>
      <div class="form-group">
        <label for="afdian_token">爱发电Token:</label>
        <input type="text" id="afdian_token" name="afdian_token" value="<?php echo htmlspecialchars($user['afdian_token']); ?>" required>
      </div>
      <button type="submit" class="submit-button">保存修改</button>
    </form>

    <div id="message" class="message"></div>
    <hr>
    <h3>如何使订单能够自动更新？<a href="../../pages/wow.html" target="_blank">跳转教程</a></h3>
    <hr>
    <h3>严正声明，宇宙安全声明：<br>
      css正在写，到时候会好看些；<br>
      后面会支持客户登录后自动获取已购订单什么的；<br>
      代码很烂，补要喷沃ToT;<br>
      我是缝合怪，补要喷沃o(╥﹏╥)o
    </h3>
    <p style="color: red;font-weight:bold;font-size:20px;">
      请详细阅读，若您继续使用则代表您同意此声明！<br>
      安全声明：<br>

      XFP是一个简单的小型工具站点，我们尽力确保我们的网站和工具的安全性，但无法保证完全没有泄露可能性。我们使用各种安全措施来保护您的数据，包括使用加密技术、防火墙和定期安全扫描。然而，没有任何安全措施可以保证100%的安全性，因此我们建议您在使用我们的服务时采取适当的预防措施。

      我们不保证我们的网站和工具没有漏洞或不受到攻击。我们鼓励您报告任何潜在的安全问题，我们将认真对待并尽快解决。<br>

      隐私声明：<br>

      XFP尊重您的隐私。在使用我们的服务时，我们可能会收集一些关于您的信息，例如您的IP地址、浏览器类型和访问时间、爱发电相关信息（订单列表，id，秘钥等等！）。我们使用这些信息来改善我们的服务，但不会将其用于任何其他目的，也不会将其出售给第三方。

      在使用我们的服务时，您可能会提供一些敏感信息，例如您的密码和支付信息。我们使用各种安全措施来保护这些信息，包括加密技术、防火墙和定期安全扫描。然而，没有任何安全措施可以保证100%的安全性，因此我们建议您在使用我们的服务时采取适当的预防措施。

      我们可能会使用cookies来改善我们的服务。您可以禁用cookies，但这可能会影响您使用我们的服务的功能。

      总之，XFP是一个简单的小型工具站点，我们尽力确保我们的网站和工具的安全性，但无法保证完全没有泄露可能性。在使用我们的服务时，请务必采取适当的预防措施。
    </p>
  </main>

  <script>
    $(document).ready(function() {
      $('#profileForm').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();

        $.ajax({
          url: '../../app/userback_action.php',
          method: 'POST',
          data: formData,
          success: function(response) {
            $('#message').html('<p>' + response + '</p>');
          },
          error: function(xhr) {
            $('#message').html('<p>发生错误: ' + xhr.statusText + '</p>');
          }
        });
      });
    });
  </script>
</body>

</html>