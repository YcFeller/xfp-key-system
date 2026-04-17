<?php
session_start();

// 如果用户已登录，则重定向到主页
if (isset($_SESSION['user_id'])) {
  header("Location: ../../index.php");
  exit();
}

// 获取 OAuth2 传递的用户信息
$oauth_user_id = isset($_GET['oauth_user_id']) ? trim($_GET['oauth_user_id']) : '';
$oauth_user_name = isset($_GET['oauth_user_name']) ? trim($_GET['oauth_user_name']) : '';
$oauth_user_avatar = isset($_GET['oauth_user_avatar']) ? trim($_GET['oauth_user_avatar']) : '';
$oauth_afd_private_id = isset($_GET['afd_private_id']) ? trim($_GET['afd_private_id']) : '';

// 检测如果为空则重定向到登录页面
if (empty($oauth_user_id)) {
  // 做出提示后跳转
  echo "<script>alert('请先点击使用爱发电进行登录');window.location.href='./login.php';</script>";
  exit();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>用户注册</title>
  <link rel="stylesheet" href="../../files/css/auth.css">
  <style>
    .inputBox {
      position: relative;
      display: inline-block;
      margin-bottom: 15px;
    }

    .error {
      color: red;
      margin-bottom: 15px;
      text-align: center;
    }

    .success {
      color: green;
      margin-bottom: 15px;
      text-align: center;
    }

    .loading {
      color: blue;
      margin-bottom: 15px;
      text-align: center;
    }
  </style>
</head>

<body>
  <section class="global-box1-section">
    <div class="box1-color"></div>
    <div class="box1-color"></div>
    <div class="box1-color"></div>
    <div class="box1-box">
      <div class="box1-circle" style="--x:0"></div>
      <div class="box1-circle" style="--x:1"></div>
      <div class="box1-circle" style="--x:2"></div>
      <div class="box1-circle" style="--x:3"></div>
      <div class="box1-circle" style="--x:4"></div>
      <div class="global-box1-dv1">
        <div class="global-box1-dv2">
          <h2>注册</h2>
          <p style="color: red; font-weight:bold; font-size: 19px; margin-bottom: 10px; text-align: center">请使用爱发电进行登录注册！</p>

          <!-- 错误和成功消息显示 -->
          <div id="messageBox"></div>

          <form id="registerForm">
            <input type="hidden" name="oauth_user_id" value="<?php echo htmlspecialchars($oauth_user_id); ?>">
            <input type="hidden" name="oauth_user_name" value="<?php echo htmlspecialchars($oauth_user_name); ?>">
            <input type="hidden" name="oauth_user_avatar" value="<?php echo htmlspecialchars($oauth_user_avatar); ?>">

            <!-- 输出测试 -->
            <?php
            echo "<p>oauth_user_id: " . htmlspecialchars($oauth_user_id) . "</p>";
            echo "<p>oauth_user_name: " . htmlspecialchars($oauth_user_name) . "</p>";
            echo "<p>oauth_user_avatar: " . htmlspecialchars($oauth_user_avatar) . "</p>";
            ?>

            <div class="inputBox">
              <input name="username" id="username" placeholder="输入用户名" required value="<?php echo htmlspecialchars($oauth_user_name); ?>">
            </div>
            <div class="inputBox">
              <input type="password" id="password" name="password" placeholder="输入密码" required>
            </div>
            <div class="inputBox">
              <input type="email" id="email" name="email" placeholder="输入电子邮件" required>
            </div>
            <!-- 同时提交私有id，仅做显示不可编辑 -->
            <!-- <div class="inputBox">
              <input type="text" id="afd_private_id" name="afd_private_id" placeholder="afd私有id" value="<?php echo htmlspecialchars($oauth_afd_private_id); ?>">
            </div> -->
            <div class="inputBox">
              <input type="submit" value="注册" id="submitButton">
            </div>
            <p class="text">已有账号？<a href="./login.php">点我登录</a></p>
            <a href="../../index.php" class="back">返回首页</a>
          </form>
        </div>
      </div>
    </div>
  </section>

  <script>
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      e.preventDefault(); // 阻止表单提交的默认行为

      const messageBox = document.getElementById('messageBox');
      const submitButton = document.getElementById('submitButton');
      const formData = new FormData(this);

      // 显示加载动画或消息
      messageBox.className = 'loading';
      messageBox.textContent = '正在提交，请稍候...';

      // 禁用按钮防止重复提交
      submitButton.disabled = true;

      fetch('./register-api.php', {
          method: 'POST',
          body: formData,
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('网络响应错误: ' + response.statusText);
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            messageBox.className = 'success';
            messageBox.textContent = data.message;
            setTimeout(() => {
              window.location.href = './login.php';
            }, 2000);
          } else {
            messageBox.className = 'error';
            messageBox.textContent = data.message;
          }
        })
        .catch(error => {
          messageBox.className = 'error';
          messageBox.textContent = '请求失败，请重试。 错误详情: ' + error.message;
        })
        .finally(() => {
          submitButton.disabled = false;
        });

    });
  </script>
</body>

</html>