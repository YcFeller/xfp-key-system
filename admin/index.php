<?php
session_start();
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 2;
if ($user_role === null) {
  header("Location: ../pages/auth/login.php");
  exit;
} elseif ($user_role < $required_role) {
  header("Location: ../index.php");
  exit;
}
// 设置页面自动跳转
header("refresh:1;url=./user/index.php");
?>
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>管理中心 - XFP系统</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#3176FF',
            secondary: '#6C47FF'
          },
          borderRadius: {
            'button': '8px'
          }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    @font-face {
      font-family: 'MiSans';
      src: url('../files/font/misans.ttf') format('truetype');
    }
    body {
      font-family: 'MiSans', sans-serif;
      color: #ffffff;
      background-color: #0F172A; /* 与首页主背景色一致 */
      min-height: 100vh;
    }
    .geometric-bg {
      background-image: radial-gradient(circle at 10% 20%, rgba(49, 118, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 90% 80%, rgba(108, 71, 255, 0.1) 0%, transparent 50%);
    }
    .card-hover {
      transition: all 0.3s ease;
    }
    .card-hover:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }
  </style>
</head>
<body class="geometric-bg">
  <div class="max-w-[1440px] mx-auto px-8 min-h-screen flex items-center justify-center">
    <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-12 w-full max-w-2xl text-center card-hover">
      <div class="mb-8">
        <i class="fas fa-shield-alt text-4xl text-primary"></i>
      </div>
      
      <h1 class="text-3xl font-['Pacifico'] mb-6">权限验证通过！</h1>
      <div class="space-y-4 mb-8">
        <p class="text-gray-400">
          <i class="fas fa-user-shield mr-2"></i>
          当前用户角色：<?= $user_role == 2 ? '管理员' : '超级管理员' ?>
        </p>
        <p class="text-sm text-primary/80">
          <i class="fas fa-clock mr-2"></i>
          正在跳转至管理面板...
        </p>
      </div>
      <div class="loader animate-spin h-12 w-12 border-4 border-primary rounded-full border-t-transparent mx-auto"></div>
    </div>
  </div>

</body>
</html>
