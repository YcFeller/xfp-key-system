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

<!-- 邮箱验证码登录表单 -->
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>邮箱验证码登录</title>
  <link rel="stylesheet" href="../../files/css/auth.css">
  <style>
    .inputBox {
      position: relative;
      display: inline-block;
      margin-bottom: 15px;
    }

    .send-code-btn {
      position: absolute;
      right: 5px;
      top: 50%;
      transform: translateY(-50%);
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 15px;
      padding: 8px 12px;
      font-size: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .send-code-btn:hover {
      background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
      transform: translateY(-50%) scale(1.05);
    }

    .send-code-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: translateY(-50%);
    }

    .error {
      color: red;
      font-size: 20px;
      font-weight: bold;
      margin-bottom: 15px;
      text-align: center;
    }

    .success {
      color: green;
      font-size: 16px;
      font-weight: bold;
      margin-bottom: 15px;
      text-align: center;
    }

    .login-mode-switch {
      text-align: center;
      margin-bottom: 20px;
    }

    .login-mode-switch a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
      padding: 8px 16px;
      border: 2px solid #667eea;
      border-radius: 20px;
      transition: all 0.3s ease;
    }

    .login-mode-switch a:hover {
      background: #667eea;
      color: white;
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
          <h2>邮箱验证码登录</h2>
          
          <!-- 登录方式切换 -->
          <div class="login-mode-switch">
            <a href="login.php">密码登录</a>
          </div>
          
          <!-- 错误和成功信息显示 -->
          <div id="errorMessage" class="error"></div>
          <div id="successMessage" class="success"></div>

          <form id="emailLoginForm">
            <div class="inputBox">
              <input name="email" id="email" type="email" placeholder="输入邮箱地址" required>
            </div>
            
            <div class="inputBox">
              <input type="text" id="verification_code" name="verification_code" placeholder="输入验证码" required>
              <button type="button" class="send-code-btn" id="sendCodeBtn" onclick="sendVerificationCode()">发送验证码</button>
            </div>
            
            <div class="inputBox">
              <input type="submit" value="登录">
            </div>
            
            <div class="otherLogin" style="text-align: center;">
              <h3>其他登录/注册方式</h3>
              <a href="../../app/oauth_login.php">
                <img src="../../files/imgs/afdlogo.png" width="70px" height="70px" title="使用爱发电登录/注册？" style="border-radius: 50%; margin:10px;">
              </a>
              <p>（推荐使用）</p>
            </div>
            <br>
            <a href="../../index.php" class="back">返回首页</a>
          </form>
        </div>
      </div>
    </div>
  </section>

  <script>
    let countdown = 0;
    let countdownTimer = null;

    /**
     * 发送验证码
     */
    function sendVerificationCode() {
      const email = document.getElementById('email').value;
      const sendBtn = document.getElementById('sendCodeBtn');
      
      if (!email) {
        showError('请先输入邮箱地址');
        return;
      }
      
      if (!isValidEmail(email)) {
        showError('请输入有效的邮箱地址');
        return;
      }
      
      // 禁用按钮
      sendBtn.disabled = true;
      sendBtn.textContent = '发送中...';
      
      // 发送验证码请求
      fetch('./email_login_api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=send_code&email=' + encodeURIComponent(email)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showSuccess('验证码已发送到您的邮箱，请查收');
          startCountdown();
        } else {
          showError(data.message || '发送验证码失败，请重试');
          sendBtn.disabled = false;
          sendBtn.textContent = '发送验证码';
        }
      })
      .catch(error => {
        console.error('发送验证码错误:', error);
        showError('网络错误，请重试。错误详情: ' + error.message);
        sendBtn.disabled = false;
        sendBtn.textContent = '发送验证码';
      });
    }

    /**
     * 开始倒计时
     */
    function startCountdown() {
      countdown = 60;
      const sendBtn = document.getElementById('sendCodeBtn');
      
      countdownTimer = setInterval(() => {
        sendBtn.textContent = `${countdown}秒后重发`;
        countdown--;
        
        if (countdown < 0) {
          clearInterval(countdownTimer);
          sendBtn.disabled = false;
          sendBtn.textContent = '发送验证码';
        }
      }, 1000);
    }

    /**
     * 验证邮箱格式
     */
    function isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    }

    /**
     * 显示错误信息
     */
    function showError(message) {
      document.getElementById('errorMessage').textContent = message;
      document.getElementById('successMessage').textContent = '';
    }

    /**
     * 显示成功信息
     */
    function showSuccess(message) {
      document.getElementById('successMessage').textContent = message;
      document.getElementById('errorMessage').textContent = '';
    }

    /**
     * 处理登录表单提交
     */
    document.getElementById('emailLoginForm').addEventListener('submit', function(e) {
      e.preventDefault(); // 阻止表单提交的默认行为

      // 获取表单数据
      const formData = new FormData(this);
      formData.append('action', 'login');

      // 使用 fetch 提交表单数据
      fetch('./email_login_api.php', {
          method: 'POST',
          body: formData,
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // 登录成功，重定向到主页
            showSuccess('登录成功，正在跳转...');
            setTimeout(() => {
              window.location.href = '../../index.php';
            }, 1000);
          } else {
            // 登录失败，显示错误信息
            showError(data.message || '登录失败，请重试');
          }
        })
        .catch(error => {
          console.error('登录请求错误:', error);
          showError('请求失败，请重试。错误详情: ' + error.message);
        });
    });
  </script>
</body>

</html>