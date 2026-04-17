<?php
session_start();
echo '<div id="loading_p" style="display:block;text-align:center;"><img src="../../files/imgs/loading.gif" title="加载中"><h2>我知道你很急<br>但是你先别急</h2></div>';
?>

<?php

// 上传表单页面 
session_start();
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 2;
if ($user_role === null) {
  echo json_encode(['error' => '未登录，请先登录。'], JSON_UNESCAPED_UNICODE);
  header("Location: ../../pages/auth/login.php");
  exit;
} elseif ($user_role < $required_role) {
  echo json_encode(['error' => '权限不足，无法访问该页面。'], JSON_UNESCAPED_UNICODE);
  header("Location: ../../index.php");
  exit;
}

require_once '../../app/config.php';

$system_user_id = $_SESSION['user_id'] ?? 0;

// 使用面向对象的方式并加上异常处理
try {
    $conn = new mysqli($servername, $db_user, $db_pass, $dbname);
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }

    // 查询用户头像等信息
    $user = null;
    $sql = "SELECT username, afdian_user_id, afdian_token, email, avatar_link FROM users WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $system_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    }

    if (!$user) {
        throw new Exception('用户信息未找到');
    }
} catch (Exception $e) {
    // 更友好的错误提示
    echo '<div style="color:red;text-align:center;margin-top:2em;">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

// 获取 plan_id 的选项
$plan_id_options = [];
$stmt = $conn->prepare("SELECT DISTINCT plan_id FROM xfp_order WHERE system_user_id = ?");
$stmt->bind_param("i", $system_user_id);

if ($stmt->execute()) {
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $plan_id_options[] = $row['plan_id'];
  }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>XFP - 上传表盘</title>
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    @font-face {
      font-family: 'MiSans';
      src: url('../../files/font/misans.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
    }
    body {
      font-family: 'MiSans', sans-serif;
    }
  </style>
  <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body class="geometric-bg min-h-screen bg-[#0F172A] text-white">
  <div class="max-w-[1440px] mx-auto px-8">
    <header class="bg-gray-900/50 backdrop-blur-lg border-b border-gray-700 fixed w-full top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between">
        <div class="flex items-center gap-4 lg:gap-8">
          <h1 class="text-xl sm:text-2xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent whitespace-nowrap">XFP表盘分享</h1>
          <nav class="ml-4 sm:ml-8">
            <ul class="flex flex-wrap gap-2 sm:gap-4 lg:gap-6 text-sm sm:text-base">
              <li><a href="./index.php" class="text-gray-300 hover:text-white transition-colors">个人中心</a></li>
              <li class="relative group">
                <button class="text-gray-300 hover:text-white transition-colors flex items-center gap-1">
                  我的资源 <span class="text-xs">▼</span>
                </button>
                <div class="absolute top-full left-0 mt-1 bg-gray-800 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 min-w-max">
                  <a href="./facelist.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-t-lg">我的表盘</a>
                  <a href="./quickapplist.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-b-lg">我的快应用</a>
                </div>
              </li>
              <li><a href="./orderlist.php" class="text-gray-300 hover:text-white transition-colors">订单中心</a></li>
              <li><a href="./afd_paylist.php" class="text-gray-300 hover:text-white transition-colors">获取爱发电订单</a></li>
              <li class="relative group">
                <button class="text-gray-300 hover:text-white transition-colors flex items-center gap-1">
                  上传管理 <span class="text-xs">▼</span>
                </button>
                <div class="absolute top-full left-0 mt-1 bg-gray-800 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 min-w-max">
                  <a href="./facelist_upload.php" class="block px-4 py-2 text-white bg-gray-700 rounded-t-lg font-bold">上传表盘</a>
                  <a href="./quickapplist_upload.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-b-lg">上传快应用</a>
                </div>
              </li>
              <li><a href="./shortcut_tool.php" class="text-gray-300 hover:text-white transition-colors">快捷工具</a></li>
            </ul>
          </nav>
        </div>
      <div class="flex items-center gap-4">
        <span class="text-gray-300"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <!-- 输出数据库获取到的头像链接： -->
        <img src="<?php echo htmlspecialchars($user['avatar_link']);?>" alt="User Image" class="h-8 w-8 rounded-full">
        <a href="../../index.php" class="!rounded-button px-6 py-2 text-gray-300 hover:text-white transition-colors">返回主页</a>
        <a href="../../pages/auth/logout.php" class="!rounded-button px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors">退出登录</a>
      </div>
    </header>
    <main class="pt-32 pb-20">
      <div class="max-w-xl mx-auto bg-gray-800/30 backdrop-blur-lg rounded-2xl p-8 card-hover shadow-lg">
        <div class="flex items-center justify-between mb-8">
          <h1 class="text-4xl font-bold text-white">上传表盘</h1>
          <a href="./facelist.php" class="rounded-button px-6 py-2 bg-primary text-white font-semibold hover:bg-opacity-90 transition-colors shadow">
            返回我的表盘
          </a>
        </div>
        <form id="uploadForm" enctype="multipart/form-data" class="space-y-6">
          <div>
            <label for="name" class="block text-gray-300 mb-2 font-semibold">表盘名称:(建议直接复制)</label>
            <input type="text" id="name" name="name" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" required>
          </div>
          <div>
            <label for="watchface_id" class="block text-gray-300 mb-2 font-semibold">表盘ID:(用于计算秘钥的ID)</label>
            <input type="text" id="watchface_id" name="watchface_id" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" required>
          </div>
          <div>
            <label for="status" class="block text-gray-300 mb-2 font-semibold">状态:(暂不可用)</label>
            <select id="status" name="status" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
              <option value="1">显示</option>
              <option value="0">隐藏</option>
            </select>
          </div>
          <div>
            <label for="plan_id" class="block text-gray-300 mb-2 font-semibold">计划ID:(每个计划id对应一个表盘且不可重复)</label>
            <select id="plan_id" name="plan_id" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
              <?php foreach ($plan_id_options as $option): ?>
                <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="image_link" class="block text-gray-300 mb-2 font-semibold">表盘图片链接:（可选）</label>
            <input type="text" id="image_link" name="image_link" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" placeholder="请输入图片链接">
          </div>
          <div>
            <label class="block text-gray-300 mb-2 font-semibold">表盘图片预览</label>
            <div id="imageContainer" class="w-72 h-72 border border-gray-500 rounded-2xl flex justify-center items-center overflow-hidden bg-gray-900"></div>
          </div>
          <button type="submit" class="w-full rounded-button py-4 bg-primary text-white text-lg font-semibold hover:bg-opacity-90 transition-colors flex items-center justify-center gap-2">上传表盘</button>
          <div id="responseMessage" class="mt-3 font-bold"></div>
        </form>
      </div>
    </main>
  </div>
  <script>
    // 隐藏loading
    document.getElementById('loading_p').style.display = 'none';
    $(document).ready(function() {
      $('#uploadForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
          url: '../../app/facelist_upload.php',
          type: 'POST',
          data: new FormData(this),
          processData: false,
          contentType: false,
          success: function(response) {
            $('#responseMessage').html(response);
            if (response.includes('成功')) {
              $('#uploadForm')[0].reset();
              window.location.href = './facelist.php';
            }
          },
          error: function() {
            $('#responseMessage').html('服务器错误，请重试。');
            if (confirm('服务器错误，是否刷新页面重试?')) {
              location.reload();
            }
          }
        });
      });
    });
    // 图片预览逻辑
    document.getElementById('image_link').addEventListener('input', function() {
      const imageUrl = this.value;
      document.getElementById('imageContainer').innerHTML = imageUrl ? `<img src="${imageUrl}" alt="从URL加载的图片" class="max-w-full max-h-full">` : '';
    });
  </script>
</body>

</html>