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

?>

<!DOCTYPE html>
<html lang="zh">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>我的快应用</title>
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
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }

    .btn-primary {
      background: linear-gradient(135deg, #3176FF, #6C47FF);
      color: white;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(49, 118, 255, 0.4);
    }

    .btn-danger {
      background: linear-gradient(135deg, #FF4757, #FF3742);
      color: white;
    }

    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 71, 87, 0.4);
    }

    .input-focus:focus {
      border-color: #3176FF;
      box-shadow: 0 0 0 3px rgba(49, 118, 255, 0.1);
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(5px);
    }

    .modal-content {
      background: linear-gradient(135deg, #1E293B, #334155);
      margin: 5% auto;
      padding: 30px;
      border-radius: 16px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }

    .form-group {
      margin-bottom: 20px;
    }

    .quickapp-list tr {
      border-bottom: 1px solid #374151;
    }

    .quickapp-list tr:hover {
      background-color: rgba(49, 118, 255, 0.1);
    }

    .quickapp-list td {
      padding: 12px 16px;
      vertical-align: middle;
    }

    .quickapp-list th {
      background-color: rgba(55, 65, 81, 0.5);
      color: #D1D5DB;
      font-weight: 600;
      text-align: left;
    }

    .appicon {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 12px;
      border: 2px solid #374151;
    }

    .status-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    .status-active {
      background-color: rgba(34, 197, 94, 0.2);
      color: #22C55E;
    }

    .status-inactive {
      background-color: rgba(239, 68, 68, 0.2);
      color: #EF4444;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="geometric-bg">
  <div class="min-h-screen">
    <header class="bg-gray-900/50 backdrop-blur-lg border-b border-gray-700 fixed w-full top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between">
        <div class="flex items-center gap-4 lg:gap-8">
          <h1 class="text-xl sm:text-2xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent whitespace-nowrap">
            XFP表盘分享
          </h1>
          <nav class="ml-4 sm:ml-8">
            <ul class="flex flex-wrap gap-2 sm:gap-4 lg:gap-6 text-sm sm:text-base">
              <li><a href="./index.php" class="text-gray-300 hover:text-white transition-colors">个人中心</a></li>
              <li class="relative group">
                <button class="text-gray-300 hover:text-white transition-colors flex items-center gap-1">
                  我的资源 <span class="text-xs">▼</span>
                </button>
                <div class="absolute top-full left-0 mt-1 bg-gray-800 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 min-w-max">
                  <a href="./facelist.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-t-lg">我的表盘</a>
                  <a href="./quickapplist.php" class="block px-4 py-2 text-white bg-gray-700 rounded-b-lg font-bold">我的快应用</a>
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
                  <a href="./quickapplist_upload.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-b-lg">上传快应用</a>
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
      </div>
    </header>

    <main class="py-20">
      <div class="max-w-7xl mx-auto px-6">
        <h1 class="text-5xl font-bold text-white mb-8">我的快应用管理</h1>
        <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 mb-8">
          <div class="search-bar flex gap-4 mb-4">
            <input type="text" id="search" placeholder="搜索快应用..." class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
            <button id="searchBtn" class="btn btn-primary">搜索</button>
          </div>
          <div class="flex gap-4 mb-4">
            <a href="./quickapplist_upload.php" class="btn btn-primary">上传快应用</a>
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

          <table id="quickappTable" class="w-full border-collapse">
            <thead>
              <tr>
                <th class="px-4 py-2 border-b border-gray-600"><input type="checkbox" id="selectAll"></th>
                <th class="px-4 py-2 border-b border-gray-600">ID</th>
                <th class="px-4 py-2 border-b border-gray-600">名称</th>
                <th class="px-4 py-2 border-b border-gray-600">图标</th>
                <th class="px-4 py-2 border-b border-gray-600">快应用ID</th>
                <th class="px-4 py-2 border-b border-gray-600">包名</th>
                <th class="px-4 py-2 border-b border-gray-600">版本</th>
                <th class="px-4 py-2 border-b border-gray-600">状态</th>
                <th class="px-4 py-2 border-b border-gray-600">上传时间</th>
                <th class="px-4 py-2 border-b border-gray-600">下载限制</th>
                <th class="px-4 py-2 border-b border-gray-600">分类</th>
                <th class="px-4 py-2 border-b border-gray-600">操作</th>
              </tr>
            </thead>
            <tbody class="quickapp-list">
              <!-- 快应用数据将在这里显示 -->
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- 修改/删除弹窗 -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <h2 class="text-xl font-bold text-white mb-4">编辑快应用信息</h2>
      <form id="editForm">
        <input type="hidden" id="editId">
        <div class="form-group">
          <label for="editName" class="block text-gray-300 mb-1">名称:</label>
          <input type="text" id="editName" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
        </div>
        <div class="form-group">
          <label for="editQuickappId" class="block text-gray-300 mb-1">快应用ID:</label>
          <input type="text" id="editQuickappId" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
        </div>
        <div class="form-group">
          <label for="editPackageName" class="block text-gray-300 mb-1">包名:</label>
          <input type="text" id="editPackageName" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
        </div>
        <div class="form-group">
          <label for="editVersion" class="block text-gray-300 mb-1">版本:</label>
          <input type="text" id="editVersion" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
        </div>
        <div class="form-group">
          <label for="editDescription" class="block text-gray-300 mb-1">描述:</label>
          <textarea id="editDescription" rows="3" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none"></textarea>
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
          <label for="editCategory" class="block text-gray-300 mb-1">分类:</label>
          <select id="editCategory" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
            <option value="general">通用</option>
            <option value="game">游戏</option>
            <option value="tool">工具</option>
            <option value="entertainment">娱乐</option>
            <option value="education">教育</option>
            <option value="business">商务</option>
          </select>
        </div>
        <div class="form-group">
          <label for="editIconLink" class="block text-gray-300 mb-1">图标链接:</label>
          <input type="text" id="editIconLink" placeholder="快应用图标URL" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
          <img id="previewIcon" src="" alt="预览图标" class="mt-2 max-h-20 rounded-lg">
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
      loadQuickapps();

      // 搜索功能
      $('#searchBtn').click(function() {
        loadQuickapps();
      });

      $('#search').keypress(function(e) {
        if (e.which == 13) {
          loadQuickapps();
        }
      });

      // 全选功能
      $('#selectAll').change(function() {
        $('input[name="selectQuickapp"]').prop('checked', this.checked);
      });

      // 批量删除
      $('#bulkDelete').click(function() {
        var selectedIds = [];
        $('input[name="selectQuickapp"]:checked').each(function() {
          selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
          alert('请选择要删除的快应用');
          return;
        }

        if (confirm('确定要删除选中的 ' + selectedIds.length + ' 个快应用吗？')) {
          $.post('../../app/quickapplist_actions.php', {
            action: 'bulkDelete',
            ids: selectedIds
          }, function(response) {
            alert(response);
            loadQuickapps();
          });
        }
      });

      // 批量修改下载限制
      $('#bulkEditLimit').click(function() {
        var selectedIds = [];
        $('input[name="selectQuickapp"]:checked').each(function() {
          selectedIds.push($(this).val());
        });

        var newLimit = $('#newDownloadsLimit').val();

        if (selectedIds.length === 0) {
          alert('请选择要修改的快应用');
          return;
        }

        if (!newLimit || newLimit < 0) {
          alert('请输入有效的下载限制数量');
          return;
        }

        if (confirm('确定要将选中的 ' + selectedIds.length + ' 个快应用的下载限制修改为 ' + newLimit + ' 吗？')) {
          $.post('../../app/quickapplist_actions.php', {
            action: 'bulkEditLimit',
            ids: selectedIds,
            downloads_limit: newLimit
          }, function(response) {
            alert(response);
            loadQuickapps();
            $('#newDownloadsLimit').val('');
          });
        }
      });

      // 编辑按钮点击事件
      $(document).on('click', '.editBtn', function() {
        var id = $(this).data('id');
        $.post('../../app/quickapplist_actions.php', {
          action: 'getQuickapp',
          id: id
        }, function(response) {
          var quickapp = JSON.parse(response);
          $('#editId').val(quickapp.id);
          $('#editName').val(quickapp.name);
          $('#editQuickappId').val(quickapp.quickapp_id);
          $('#editPackageName').val(quickapp.package_name);
          $('#editVersion').val(quickapp.version);
          $('#editDescription').val(quickapp.description);
          $('#editStatus').val(quickapp.status);
          $('#editDownloadsLimit').val(quickapp.downloads_limit);
          $('#editCategory').val(quickapp.category);
          $('#editIconLink').val(quickapp.icon_link);
          if (quickapp.icon_link) {
            $('#previewIcon').attr('src', quickapp.icon_link).show();
          } else {
            $('#previewIcon').hide();
          }
          $('#editModal').show();
        });
      });

      // 图标预览
      $('#editIconLink').on('input', function() {
        var iconUrl = $(this).val();
        if (iconUrl) {
          $('#previewIcon').attr('src', iconUrl).show();
        } else {
          $('#previewIcon').hide();
        }
      });

      // 编辑表单提交
      $('#editForm').submit(function(e) {
        e.preventDefault();
        $.post('../../app/quickapplist_actions.php', {
          action: 'updateQuickapp',
          id: $('#editId').val(),
          name: $('#editName').val(),
          quickapp_id: $('#editQuickappId').val(),
          package_name: $('#editPackageName').val(),
          version: $('#editVersion').val(),
          description: $('#editDescription').val(),
          status: $('#editStatus').val(),
          downloads_limit: $('#editDownloadsLimit').val(),
          category: $('#editCategory').val(),
          icon_link: $('#editIconLink').val()
        }, function(response) {
          alert(response);
          $('#editModal').hide();
          loadQuickapps();
        });
      });

      // 删除按钮
      $('#deleteBtn').click(function(e) {
        e.preventDefault();
        if (confirm('确定要删除这个快应用吗？')) {
          $.post('../../app/quickapplist_actions.php', {
            action: 'deleteQuickapp',
            id: $('#editId').val()
          }, function(response) {
            alert(response);
            $('#editModal').hide();
            loadQuickapps();
          });
        }
      });

      // 点击模态框背景关闭
      $(window).click(function(event) {
        if (event.target.id === 'editModal') {
          $('#editModal').hide();
        }
      });
    });

    /**
     * 加载快应用列表
     */
    function loadQuickapps() {
      var query = $('#search').val();
      $.post('../../app/quickapplist_actions.php', {
        action: 'fetchQuickapps',
        query: query
      }, function(response) {
        $('.quickapp-list').html(response);
      });
    }
  </script>

</body>

</html>