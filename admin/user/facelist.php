<?php
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

// 用户数据查询
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
//获取的数据输出测试
// echo json_encode(['user' => $user], JSON_UNESCAPED_UNICODE);

?>


<!DOCTYPE html>
<html lang="zh">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>我的表盘</title>
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
            'none': '0px',
            'sm': '4px',
            DEFAULT: '8px',
            'md': '12px',
            'lg': '16px',
            'xl': '20px',
            '2xl': '24px',
            '3xl': '32px',
            'full': '9999px',
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
      color: #ffffff;
      background-color: #0F172A;
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

    .input-focus:focus {
      box-shadow: 0 0 0 3px rgba(49, 118, 255, 0.3);
    }

    .modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      display: none;
      justify-content: center;
      align-items: center;
      overflow: auto; /* 添加溢出自动滚动 */
    }

    .modal-content {/* 上下左右居中 */
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background-color: #0F172A;
      padding: 2rem;
      border-radius: 0.5rem;
      max-width: 500px;
      width: 100%;
      margin: auto; /* 确保内容居中 */
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .btn {
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      cursor: pointer;
      transition: opacity 0.3s ease;
    }

    .btn-primary {
      background-color: #3176FF;
      color: white;
    }

    .btn-danger {
      background-color: #dc2626;
      color: white;
    }

    .btn:hover {
      opacity: 0.9;
    }

    .faceimg {
      height: 200px;
      object-fit: cover;
      border-radius: 0.5rem;
    }
    
    /*列表样式，设置间隔等等*/
    .watchface-list {
      text-align: center;
    }

    .watchface-list tr {
      height: 250px;
      border-bottom: 1px rgba(252, 252, 252, 0.45) solid;
    }

    .editBtn {
      height: 40px;
      width: 80px;
      border-radius: 5px;
      background-color: #3176FF;
      color: white;
      border: none;
    }

  </style>
  <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body class="geometric-bg">
  <div class="max-w-[1440px] mx-auto px-8">
    <header class="py-6 flex items-center justify-between flex-wrap">
      <div class="flex items-center gap-4 flex-wrap">
        <h1 class="text-2xl lg:text-3xl font-['Pacifico'] text-white whitespace-nowrap">XFP密钥获取系统</h1>
        <nav class="ml-4 lg:ml-12">
          <ul class="flex flex-wrap gap-3 lg:gap-6 xl:gap-8 text-sm lg:text-base">
            <li><a href="./index.php" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30">个人中心</a></li>
            <li class="relative group">
              <a href="#" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30 flex items-center gap-1">
                我的资源 <i class="fas fa-chevron-down text-xs"></i>
              </a>
              <ul class="absolute top-full left-0 mt-1 bg-gray-800/95 backdrop-blur-lg rounded-lg shadow-lg border border-gray-700/50 min-w-[140px] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                <li><a href="./facelist.php" class="block px-4 py-2 text-white bg-gray-700/50 hover:bg-gray-600/50 transition-colors rounded-t-lg font-medium">我的表盘</a></li>
                <li><a href="./quickapplist.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700/50 transition-colors rounded-b-lg">我的快应用</a></li>
              </ul>
            </li>
            <li><a href="./orderlist.php" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30">订单中心</a></li>
            <li><a href="./afd_paylist.php" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30">订单同步</a></li>
            <li class="relative group">
              <a href="#" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30 flex items-center gap-1">
                上传管理 <i class="fas fa-chevron-down text-xs"></i>
              </a>
              <ul class="absolute top-full left-0 mt-1 bg-gray-800/95 backdrop-blur-lg rounded-lg shadow-lg border border-gray-700/50 min-w-[140px] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                <li><a href="./facelist_upload.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700/50 transition-colors rounded-t-lg">上传表盘</a></li>
                <li><a href="./quickapplist_upload.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700/50 transition-colors rounded-b-lg">上传快应用</a></li>
              </ul>
            </li>
            <li><a href="./shortcut_tool.php" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30">快捷工具</a></li>
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

    <main class="py-20">
      <h1 class="text-5xl font-bold text-white mb-8">我的表盘管理</h1>
      <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 mb-8">
        <div class="search-bar flex gap-4 mb-4">
          <input type="text" id="search" placeholder="搜索表盘..." class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
          <button id="searchBtn" class="btn btn-primary">搜索</button>
        </div>
        <div class="flex gap-4 mb-4">
          <a href="./facelist_upload.php" class="btn btn-primary">上传表盘</a>
          <a href="./index.php" class="btn btn-primary">个人中心</a>
          <a href="./afd_paylist.php" class="btn btn-primary">爱发电订单中心（测试）</a>
          <a href="../../index.php" class="btn btn-primary">主页</a>
        </div>
        <!-- 批量操作 -->
        <div class="flex gap-4 mb-4">
          <button id="bulkDelete" class="btn btn-danger">批量删除</button>
          <button id="bulkEditLimit" class="btn btn-primary">批量修改下载限制</button>
          <input type="number" id="newDownloadsLimit" placeholder="新的下载限制" class="px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
        </div>

        <table id="watchfaceTable" class="w-full border-collapse">
          <thead>
            <tr>
              <th class="px-4 py-2 border-b border-gray-600"><input type="checkbox" id="selectAll"></th>
              <th class="px-4 py-2 border-b border-gray-600">ID</th>
              <th class="px-4 py-2 border-b border-gray-600">名称</th>
              <th class="px-4 py-2 border-b border-gray-600">预览图</th>
              <th class="px-4 py-2 border-b border-gray-600">表盘ID</th>
              <th class="px-4 py-2 border-b border-gray-600">状态</th>
              <th class="px-4 py-2 border-b border-gray-600">上传时间</th>
              <th class="px-4 py-2 border-b border-gray-600">下载次数限制</th>
              <th class="px-4 py-2 border-b border-gray-600">计划ID</th>
              <th class="px-4 py-2 border-b border-gray-600">操作</th>
            </tr>
          </thead>
          <tbody class="watchface-list">
            <!-- 表盘数据将在这里显示 -->
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- 修改/删除弹窗 -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <h2 class="text-xl font-bold text-white mb-4">编辑表盘信息</h2>
      <form id="editForm">
        <input type="hidden" id="editId">
        <div class="form-group">
          <label for="editName" class="block text-gray-300 mb-1">名称:</label>
          <input type="text" id="editName" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
        </div>
        <div class="form-group">
          <label for="editWatchfaceId" class="block text-gray-300 mb-1">表盘ID:</label>
          <input type="text" id="editWatchfaceId" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
        </div>
        <div class="form-group">
          <label for="editStatus" class="block text-gray-300 mb-1">状态:</label>
          <select id="editStatus" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
            <option value="1">可激活</option>
            <option value="0">禁止激活</option>
          </select>
        </div>
        <div class="form-group">
          <label for="editDownloadsLimit" class="block text-gray-300 mb-1">下载次数限制:</label>
          <input type="number" id="editDownloadsLimit" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
        </div>
        <div class="form-group">
          <label for="editImageLink" class="block text-gray-300 mb-1">表盘图片:</label>
          <input type="text" id="editImageLink" placeholder="不设置就显示订单图片" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
          <img id="previewImage" src="" alt="预览图" class="mt-2 max-h-40">
        </div>
        <div class="flex gap-4 mt-4">
          <button type="submit" class="btn btn-primary">保存修改</button>
          <button id="deleteBtn" class="btn btn-danger">删除</button>
        </div>
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
          url: '../../app/facelist_actions.php',
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
          url: '../../app/facelist_actions.php',
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
          url: '../../app/facelist_actions.php',
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
            url: '../../app/facelist_actions.php',
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
            url: '../../app/facelist_actions.php',
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
            url: '../../app/facelist_actions.php',
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

      // 点击模态框外部关闭模态框
      $(window).on('click', function(e) {
        if (e.target == $('#editModal')[0]) {
          $('#editModal').hide();
        }
      });
    });
  </script>
</body>

</html>