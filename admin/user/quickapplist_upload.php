<?php
session_start();
echo '<div id="loading_p" style="display:block;text-align:center;"><img src="../../files/imgs/loading.gif" title="加载中"><h2>我知道你很急<br>但是你先别急</h2></div>';
?>

<?php

// 快应用上传表单页面 
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
  <title>XFP - 上传快应用</title>
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

    .geometric-bg {
      background-image: radial-gradient(circle at 10% 20%, rgba(49, 118, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 90% 80%, rgba(108, 71, 255, 0.1) 0%, transparent 50%);
    }

    .card-hover {
      transition: all 0.3s ease;
    }

    .card-hover:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    .input-focus:focus {
      border-color: #3176FF;
      box-shadow: 0 0 0 3px rgba(49, 118, 255, 0.1);
    }

    .file-upload-area {
      border: 2px dashed #374151;
      transition: all 0.3s ease;
    }

    .file-upload-area:hover {
      border-color: #3176FF;
      background-color: rgba(49, 118, 255, 0.05);
    }

    .file-upload-area.dragover {
      border-color: #3176FF;
      background-color: rgba(49, 118, 255, 0.1);
    }
  </style>
  <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body class="geometric-bg min-h-screen bg-[#0F172A] text-white">
  <div class="max-w-[1440px] mx-auto px-8">
    <header class="py-6 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <h1 class="text-3xl font-['Pacifico'] text-white">XFP密钥获取系统</h1>
        <nav class="ml-12">
          <ul class="flex gap-8">
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
                <a href="./facelist_upload.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-t-lg">上传表盘</a>
                <a href="./quickapplist_upload.php" class="block px-4 py-2 text-white bg-gray-700 rounded-b-lg font-bold">上传快应用</a>
              </div>
            </li>
            <li><a href="./shortcut_tool.php" class="text-gray-300 hover:text-white transition-colors">快捷工具</a></li>
          </ul>
        </nav>
      </div>
      <div class="flex items-center gap-4">
        <span class="text-gray-300"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <img src="<?php echo htmlspecialchars($user['avatar_link']);?>" alt="User Image" class="h-8 w-8 rounded-full">
        <a href="../../index.php" class="!rounded-button px-6 py-2 text-gray-300 hover:text-white transition-colors">返回主页</a>
        <a href="../../pages/auth/logout.php" class="!rounded-button px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors">退出登录</a>
      </div>
    </header>
    <main class="py-20">
      <div class="max-w-2xl mx-auto bg-gray-800/30 backdrop-blur-lg rounded-2xl p-8 card-hover shadow-lg">
        <div class="flex items-center justify-between mb-8">
          <h1 class="text-4xl font-bold text-white">上传快应用</h1>
          <a href="./quickapplist.php" class="rounded-button px-6 py-2 bg-primary text-white font-semibold hover:bg-opacity-90 transition-colors shadow">
            返回我的快应用
          </a>
        </div>
        <form id="uploadForm" enctype="multipart/form-data" class="space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="name" class="block text-gray-300 mb-2 font-semibold">快应用名称:</label>
              <input type="text" id="name" name="name" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" required>
            </div>
            <div>
              <label for="quickapp_id" class="block text-gray-300 mb-2 font-semibold">快应用ID:</label>
              <input type="text" id="quickapp_id" name="quickapp_id" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" required>
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="package_name" class="block text-gray-300 mb-2 font-semibold">包名:</label>
              <input type="text" id="package_name" name="package_name" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" placeholder="com.example.app">
            </div>
            <div>
              <label for="version" class="block text-gray-300 mb-2 font-semibold">版本号:</label>
              <input type="text" id="version" name="version" value="1.0.0" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
            </div>
          </div>

          <div>
            <label for="description" class="block text-gray-300 mb-2 font-semibold">应用描述:</label>
            <textarea id="description" name="description" rows="3" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" placeholder="请输入快应用的功能描述..."></textarea>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="category" class="block text-gray-300 mb-2 font-semibold">分类:</label>
              <select id="category" name="category" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
                <option value="general">通用</option>
                <option value="game">游戏</option>
                <option value="tool">工具</option>
                <option value="entertainment">娱乐</option>
                <option value="education">教育</option>
                <option value="business">商务</option>
              </select>
            </div>
            <div>
              <label for="status" class="block text-gray-300 mb-2 font-semibold">状态:</label>
              <select id="status" name="status" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
                <option value="1">启用</option>
                <option value="0">禁用</option>
              </select>
            </div>
          </div>

          <div>
            <label for="plan_id" class="block text-gray-300 mb-2 font-semibold">计划ID:(每个计划id对应一个快应用且不可重复)</label>
            <select id="plan_id" name="plan_id" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
              <?php foreach ($plan_id_options as $option): ?>
                <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label for="icon_link" class="block text-gray-300 mb-2 font-semibold">图标链接:（可选）</label>
            <input type="text" id="icon_link" name="icon_link" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" placeholder="请输入图标链接">
          </div>

          <div>
            <label class="block text-gray-300 mb-2 font-semibold">图标预览</label>
            <div id="iconContainer" class="w-24 h-24 border border-gray-500 rounded-2xl flex justify-center items-center overflow-hidden bg-gray-900">
              <i class="fas fa-mobile-alt text-gray-500 text-2xl"></i>
            </div>
          </div>

          <!-- 文件上传区域 -->
          <div>
            <label class="block text-gray-300 mb-2 font-semibold">快应用文件:（可选）</label>
            <div id="fileUploadArea" class="file-upload-area p-8 rounded-lg text-center cursor-pointer">
              <input type="file" id="quickapp_file" name="quickapp_file" accept=".rpk,.zip,.apk" class="hidden">
              <div id="uploadContent">
                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-300 mb-2">点击或拖拽文件到此处上传</p>
                <p class="text-gray-500 text-sm">支持 .rpk, .zip, .apk 格式</p>
              </div>
              <div id="fileInfo" class="hidden">
                <i class="fas fa-file text-2xl text-primary mb-2"></i>
                <p id="fileName" class="text-white font-semibold"></p>
                <p id="fileSize" class="text-gray-400 text-sm"></p>
                <button type="button" id="removeFile" class="mt-2 text-red-400 hover:text-red-300">
                  <i class="fas fa-times"></i> 移除文件
                </button>
              </div>
            </div>
          </div>

          <button type="submit" class="w-full rounded-button py-4 bg-primary text-white text-lg font-semibold hover:bg-opacity-90 transition-colors flex items-center justify-center gap-2">
            <i class="fas fa-upload"></i>
            上传快应用
          </button>
          <div id="responseMessage" class="mt-3 font-bold"></div>
        </form>
      </div>
    </main>
  </div>
  <script>
    // 隐藏loading
    document.getElementById('loading_p').style.display = 'none';
    
    $(document).ready(function() {
      // 表单提交
      $('#uploadForm').on('submit', function(e) {
        e.preventDefault();
        
        // 显示上传进度
        $('#responseMessage').html('<i class="fas fa-spinner fa-spin"></i> 正在上传...');
        
        $.ajax({
          url: '../../app/quickapplist_upload.php',
          type: 'POST',
          data: new FormData(this),
          processData: false,
          contentType: false,
          success: function(response) {
            $('#responseMessage').html(response);
            if (response.includes('成功')) {
              $('#uploadForm')[0].reset();
              setTimeout(function() {
                window.location.href = './quickapplist.php';
              }, 1500);
            }
          },
          error: function() {
            $('#responseMessage').html('<span class="text-red-400">服务器错误，请重试。</span>');
            if (confirm('服务器错误，是否刷新页面重试?')) {
              location.reload();
            }
          }
        });
      });

      // 图标预览逻辑
      $('#icon_link').on('input', function() {
        const iconUrl = this.value;
        const container = $('#iconContainer');
        if (iconUrl) {
          container.html(`<img src="${iconUrl}" alt="图标预览" class="max-w-full max-h-full rounded-lg">`);
        } else {
          container.html('<i class="fas fa-mobile-alt text-gray-500 text-2xl"></i>');
        }
      });

      // 文件上传区域点击事件
      $('#fileUploadArea').on('click', function() {
        $('#quickapp_file').click();
      });

      // 文件选择事件
      $('#quickapp_file').on('change', function() {
        const file = this.files[0];
        if (file) {
          showFileInfo(file);
        }
      });

      // 拖拽上传
      $('#fileUploadArea').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
      });

      $('#fileUploadArea').on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
      });

      $('#fileUploadArea').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
          const file = files[0];
          $('#quickapp_file')[0].files = files;
          showFileInfo(file);
        }
      });

      // 移除文件
      $(document).on('click', '#removeFile', function(e) {
        e.stopPropagation();
        $('#quickapp_file').val('');
        $('#uploadContent').show();
        $('#fileInfo').hide();
      });
    });

    /**
     * 显示文件信息
     * @param {File} file 选择的文件
     */
    function showFileInfo(file) {
      const fileName = file.name;
      const fileSize = formatFileSize(file.size);
      
      $('#fileName').text(fileName);
      $('#fileSize').text(fileSize);
      $('#uploadContent').hide();
      $('#fileInfo').show();
    }

    /**
     * 格式化文件大小
     * @param {number} bytes 文件大小（字节）
     * @returns {string} 格式化后的文件大小
     */
    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
  </script>
</body>

</html>