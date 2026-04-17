<?php
session_start();

// 设置会话变量来标识期望的页面
$_SESSION['expected_page'] = true;

// 检查用户是否登录，如果没有登录则重定向到登录页面
if (isset($_SESSION['user_id'])) {
  header("Location: ../../index.php");
  exit();
}

// 获取并清除会话中的错误信息
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>

<!-- 登录表单，包含验证码 -->
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>用户登录</title>
  <link rel="stylesheet" href="../../files/css/auth.css">
  <style>
    .inputBox {
      position: relative;
      display: inline-block;
      margin-bottom: 15px;
    }

    .captchaImage {
      position: absolute;
      right: 0;
      top: 0;
      bottom: 0;
      display: flex;
      align-items: center;
      padding-right: 5px;
      cursor: pointer;
    }

    .captchaImage img {
      border-radius: 20px;
    }

    .forget {
      position: relative;
      cursor: pointer;
    }

    .forget:hover::after {
      content: "安全起见，请联系我重置密码！";
      position: absolute;
      top: 22px;
      left: 50%;
      transform: translateX(-50%);
      padding: 10px;
      background-color: black;
      color: white;
      border-radius: 5px;
      z-index: 1;
      white-space: nowrap;
    }

    .error {
      color: red;
      font-size: 20px;
      font-weight: bold;
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
          <h2>登录</h2>
          <p style="color: red; text-align: center; margin-bottom: 15px;">请先点击底部爱发电图标进行注册！<br>网站用户名只允许字母数字，请先修改！</p>
          <!-- 错误显示 -->
          <div id="errorMessage" class="error"></div>

          <form id="loginForm">
            <div class="inputBox">
              <input name="username" id="username" placeholder="输入用户名" required>
            </div>
            <div class="inputBox">
              <input type="password" id="password" name="password" placeholder="输入密码" required>
            </div>
            <div class="inputBox">
              <input type="text" id="captcha" name="captcha" placeholder="输入验证码，点击可刷新" required>
              <div class="captchaImage" onclick="this.firstElementChild.src='../../app/captcha.php?' + Math.random();">
                <img src="../../app/captcha.php" alt="验证码">
              </div>
            </div>
            <div class="inputBox">
              <input type="submit" value="登录">
            </div>
            <div class="otherLogin" style="text-align: center;">
              <h3>其他登录/注册方式</h3>
              <div style="margin-bottom: 15px;">
                <a href="email_login.php" style="color: #667eea; text-decoration: none; font-weight: 600; padding: 8px 16px; border: 2px solid #667eea; border-radius: 20px; transition: all 0.3s ease; display: inline-block;" onmouseover="this.style.background='#667eea'; this.style.color='white';" onmouseout="this.style.background='transparent'; this.style.color='#667eea';">邮箱验证码登录</a>
              </div>
              <a href="../../app/oauth_login.php">
                <img src="../../files/imgs/afdlogo.png" width="70px" height="70px" title="使用爱发电登录/注册？" style="border-radius: 50%; margin:10px;">
              </a>
              <p>（推荐使用）</p>
            </div>
            <br>
            <!-- <a href="register.php" class="register">没有账号？立即注册</a><br> -->
            <a href="../../index.php" class="back">返回首页</a>
            <p class="forget">忘记密码?</p>
          </form>
        </div>
      </div>
    </div>
  </section>

  <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      e.preventDefault(); // 阻止表单提交的默认行为

      // 获取表单数据
      const formData = new FormData(this);

      // 使用 fetch 提交表单数据
      fetch('./login-api.php', {
          method: 'POST',
          body: formData,
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // 登录成功，重定向到主页
            window.location.href = '../../index.php';
          } else {
            // 登录失败，显示错误信息
            document.getElementById('errorMessage').textContent = data.message;
          }
        })
        .catch(error => {
          document.getElementById('errorMessage').textContent = '请求失败，请重试。';
        });
    });
  </script>
</body>

</html>