<?php
session_start();
require_once '../../app/config.php';

// 验证用户是否已登录
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

// 查询用户头像等信息
$user_id = $_SESSION['user_id'];
// 使用config.php中的数据库连接
if (isset($mysqli_conn) && $mysqli_conn !== null) {
  $db_connection = $mysqli_conn;
} else {
  // 如果mysqli不可用，使用PDO模拟mysqli接口
  class PDOMysqliWrapper {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function prepare($sql) { return new PDOStatementWrapper($this->pdo->prepare($sql)); }
    public function close() { $this->pdo = null; }
  }
  class PDOStatementWrapper {
    private $stmt;
    public function __construct($stmt) { $this->stmt = $stmt; }
    public function bind_param($types, ...$params) { 
      for($i = 0; $i < count($params); $i++) {
        $this->stmt->bindParam($i + 1, $params[$i]);
      }
    }
    public function execute() { return $this->stmt->execute(); }
    public function get_result() { return new PDOResultWrapper($this->stmt); }
    public function close() { $this->stmt = null; }
  }
  class PDOResultWrapper {
    private $stmt;
    public function __construct($stmt) { $this->stmt = $stmt; }
    public function fetch_assoc() { return $this->stmt->fetch(PDO::FETCH_ASSOC); }
  }
  $db_connection = new PDOMysqliWrapper($conn);
}
$conn = $db_connection;
$sql = "SELECT username, afdian_user_id, afdian_token, email, avatar_link FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>订单列表管理</title>
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
      overflow: auto;
      z-index: 50;
    }
    .modal-content {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background-color: #0F172A;
      padding: 2rem;
      border-radius: 0.5rem;
      max-width: 500px;
      width: 100%;
      margin: auto;
      box-shadow: 0 10px 40px rgba(0,0,0,0.3);
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
    .table th, .table td {
      padding: 0.75rem 0.5rem;
      border-bottom: 1px solid #334155;
      text-align: center;
      max-width: 400px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .table th {
      background: #1e293b;
      color: #fff;
    }
    .table tr:nth-child(even) {
      background: #1e293b44;
    }
    .editBtn {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      background-color: #3176FF;
      color: #fff;
      border-radius: 8px;
      padding: 0.25rem 0.75rem;
      font-size: 0.95em;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: background 0.2s;
    }
    .editBtn:hover {
      background-color: #2556b8;
    }
    
    /* 分页样式 */
    .pagination-btn {
      padding: 0.5rem 0.75rem;
      border-radius: 0.5rem;
      border: 1px solid #374151;
      background-color: #374151;
      color: #d1d5db;
      cursor: pointer;
      transition: all 0.2s ease;
      font-size: 0.875rem;
      min-width: 2.5rem;
      text-align: center;
    }
    
    .pagination-btn:hover:not(:disabled) {
      background-color: #3176FF;
      border-color: #3176FF;
      color: white;
    }
    
    .pagination-btn.active {
      background-color: #3176FF;
      border-color: #3176FF;
      color: white;
      font-weight: 600;
    }
    
    .pagination-btn:disabled {
      opacity: 0.5;
    }
    
    /* 自定义下拉框样式 */
    .custom-select {
      position: relative;
      display: inline-block;
      width: 100%;
    }
    
    .custom-select-trigger {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      background-color: rgba(55, 65, 81, 0.5);
      color: white;
      border: 2px solid #4b5563;
      border-radius: 0.5rem;
      cursor: pointer;
      transition: all 0.2s ease;
      min-height: 2.5rem;
    }
    
    .custom-select-trigger:hover,
    .custom-select-trigger.active {
      border-color: #3176FF;
      box-shadow: 0 0 0 3px rgba(49, 118, 255, 0.3);
    }
    
    .custom-select-trigger .thumbnail {
      width: 24px;
      height: 24px;
      border-radius: 4px;
      object-fit: cover;
      flex-shrink: 0;
    }
    
    .custom-select-trigger .text {
      flex: 1;
      text-align: left;
    }
    
    .custom-select-trigger .arrow {
      transition: transform 0.2s ease;
    }
    
    .custom-select-trigger.active .arrow {
      transform: rotate(180deg);
    }
    
    .custom-select-options {
      position: absolute;
      top: 100%;
      left: 0;
      min-width: 300px;
      width: max-content;
      max-width: 400px;
      background-color: #374151;
      border: 2px solid #4b5563;
      border-radius: 0.5rem;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
      z-index: 1000;
      max-height: 200px;
      overflow-y: auto;
      display: none;
    }
    
    .custom-select-option {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      cursor: pointer;
      transition: background-color 0.2s ease;
      border-bottom: 1px solid #4b5563;
      white-space: nowrap;
      min-width: 0;
    }
    
    .custom-select-option:last-child {
      border-bottom: none;
    }
    
    .custom-select-option:hover {
      background-color: #4b5563;
    }
    
    .custom-select-option.selected {
      background-color: #3176FF;
    }
    
    .custom-select-option .thumbnail {
      width: 32px;
      height: 32px;
      border-radius: 4px;
      object-fit: cover;
      flex-shrink: 0;
    }
    
    .custom-select-option .text {
      flex: 1;
      text-align: left;
      color: white;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      min-width: 0;
    }
    
    /* 隐藏原始select */
    .custom-select select {
      display: none;
      cursor: not-allowed;
      background-color: #1f2937;
      border-color: #1f2937;
    }
    
    .pagination-ellipsis {
      padding: 0.5rem 0.25rem;
      color: #6b7280;
      font-size: 0.875rem;
    }
  </style>
  <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body class="geometric-bg">
  <div class="max-w-[1440px] mx-auto px-8">
    <header class="py-6 flex flex-wrap items-center justify-between">
      <div class="flex items-center gap-4">
        <h1 class="text-2xl sm:text-3xl font-['Pacifico'] text-white whitespace-nowrap">XFP密钥获取系统</h1>
        <nav class="ml-4 sm:ml-8 lg:ml-12">
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
            <li><a href="./orderlist.php" class="text-white font-bold underline transition-colors">订单中心</a></li>
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
    </header>
    <main class="py-20">
      <h1 class="text-5xl font-bold text-white mb-8">订单列表管理</h1>
      <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 mb-8 card-hover shadow-lg">
        <div class="flex flex-col md:flex-row gap-4 mb-4">
          <div class="flex gap-2 flex-1">
            <input type="text" id="search" placeholder="搜索订单..." class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
            <button id="searchBtn" class="btn btn-primary">搜索</button>
          </div>
          <div class="flex gap-2 items-center">
            <label for="planFilter" class="text-gray-300">筛选计划ID:</label>
            <div class="custom-select" style="min-width: 280px;">
              <select id="planFilter" class="px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
                <option value="">所有计划</option>
              </select>
            </div>
          </div>
          <div class="flex gap-2 items-center">
            <label for="batchDownloadsLimit" class="text-gray-300">批量修改下载限制:</label>
            <input type="number" id="batchDownloadsLimit" class="px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" required>
            <button id="batchUpdateBtn" class="btn btn-primary">保存批量修改</button>
          </div>
          <div class="flex gap-2 items-center">
            <button id="cleanupBtn" class="btn btn-danger"><i class="fas fa-trash-alt"></i> 订单清理</button>
          </div>
        </div>
        
        <!-- 分页控件 -->
        <div class="mt-6 flex flex-col md:flex-row justify-between items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
              <label for="perPageSelect" class="text-gray-300 text-sm">每页显示:</label>
              <select id="perPageSelect" class="px-3 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none text-sm">
                <option value="10">10条</option>
                <option value="20" selected>20条</option>
                <option value="50">50条</option>
                <option value="100">100条</option>
              </select>
            </div>
            <div id="recordsInfo" class="text-gray-300 text-sm">
              <!-- 记录信息将在这里显示 -->
            </div>
          </div>
          
          <div id="paginationControls" class="flex items-center gap-2">
            <!-- 分页按钮将在这里显示 -->
          </div>
        </div>

        <br>

        <div class="overflow-x-auto">
          <table id="orderTable" class="table w-full border-collapse">
            <thead>
              <tr>
                <th class="whitespace-nowrap"> <input type="checkbox" id="selectAll"> 全选</th>
                <th class="whitespace-nowrap max-w-[140px] truncate">订单号</th>
                <th class="whitespace-nowrap max-w-[120px] truncate">赞助者用户ID</th>
                <th class="whitespace-nowrap max-w-[120px] truncate">爱发电用户ID</th>
                <th class="whitespace-nowrap max-w-[120px] truncate">系统用户ID</th>
                <th class="whitespace-nowrap max-w-[80px] truncate">总金额</th>
                <th class="whitespace-nowrap max-w-[80px] truncate">下载限制</th>
                <th class="whitespace-nowrap max-w-[400px] truncate">SKU详情</th>
                <th class="whitespace-nowrap max-w-[120px] truncate">计划ID</th>
                <th class="whitespace-nowrap max-w-[80px] truncate">操作</th>
              </tr>
            </thead>
            <tbody class="align-middle text-sm">
              <!-- 订单数据将在这里显示 -->
            </tbody>
          </table>
        </div>
        
        <!-- 分页控件 -->
        <div class="mt-6 flex flex-col md:flex-row justify-between items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
              <label for="perPageSelect" class="text-gray-300 text-sm">每页显示:</label>
              <select id="perPageSelect" class="px-3 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none text-sm">
                <option value="10">10条</option>
                <option value="20" selected>20条</option>
                <option value="50">50条</option>
                <option value="100">100条</option>
              </select>
            </div>
            <div id="recordsInfo" class="text-gray-300 text-sm">
              <!-- 记录信息将在这里显示 -->
            </div>
          </div>
          
          <div id="paginationControls" class="flex items-center gap-2">
            <!-- 分页按钮将在这里显示 -->
          </div>
        </div>
      </div>
    </main>
  </div>
  <!-- 修改下载限制弹窗 -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <h2 class="text-xl font-bold text-white mb-4">编辑下载限制</h2>
      <form id="editForm" class="space-y-4">
        <input type="hidden" id="editOrderId">
        <div>
          <label for="editDownloadsLimit" class="block text-gray-300 mb-1">下载限制:</label>
          <input type="number" id="editDownloadsLimit" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
        </div>
        <button type="submit" class="btn btn-primary w-full">保存修改</button>
      </form>
    </div>
  </div>

  <!-- 订单清理弹窗 -->
  <div id="cleanupModal" class="modal">
    <div class="modal-content">
      <h2 class="text-xl font-bold text-white mb-4"><i class="fas fa-exclamation-triangle text-yellow-500"></i> 订单清理</h2>
      <div class="space-y-4">
        <div class="bg-yellow-900/30 border border-yellow-600 rounded-lg p-4">
          <p class="text-yellow-300 text-sm mb-2"><i class="fas fa-info-circle"></i> 清理说明：</p>
          <ul class="text-yellow-200 text-sm space-y-1 ml-4">
            <li>• 清理超过指定天数的订单记录</li>
            <li>• 此操作不可逆，请谨慎操作</li>
            <li>• 建议定期清理以优化数据库性能</li>
          </ul>
        </div>
        <div>
          <label for="cleanupDays" class="block text-gray-300 mb-2">清理天数 (保留最近N天的订单):</label>
          <input type="number" id="cleanupDays" value="30" min="1" max="365" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
          <p class="text-gray-400 text-xs mt-1">建议保留30-90天的订单记录</p>
        </div>
        <div>
          <label class="flex items-center gap-2 text-gray-300">
            <input type="checkbox" id="confirmCleanup" class="rounded">
            <span class="text-sm">我确认要执行此清理操作，并了解此操作不可逆</span>
          </label>
        </div>
        <div class="flex gap-3">
          <button id="executeCleanupBtn" class="btn btn-danger flex-1" disabled>
            <i class="fas fa-trash-alt"></i> 执行清理
          </button>
          <button id="cancelCleanupBtn" class="btn bg-gray-600 text-white flex-1">
            <i class="fas fa-times"></i> 取消
          </button>
        </div>
      </div>
    </div>
  </div>
  <script>
    $(document).ready(function() {
      // 分页相关变量
      let currentPage = 1;
      let perPage = 20;
      let totalPages = 1;
      let totalRecords = 0;
      
      // 加载订单数据（支持分页）
      function loadOrders(query = '', planId = '', page = 1, perPageCount = 20) {
        currentPage = page;
        perPage = perPageCount;
        
        $.ajax({
          url: '../../app/orderlist_actions.php',
          method: 'POST',
          data: {
            action: 'fetchOrders',
            query: query,
            plan_id: planId,
            page: page,
            per_page: perPageCount
          },
          success: function(response) {
            try {
              const data = JSON.parse(response);
              
              // 更新表格内容
              $('#orderTable tbody').html(data.table_rows);
              
              // 更新分页信息
              if (data.pagination) {
                currentPage = data.pagination.current_page;
                perPage = data.pagination.per_page;
                totalPages = data.pagination.total_pages;
                totalRecords = data.pagination.total_records;
                
                updatePaginationInfo();
                updatePaginationControls();
              }
            } catch (e) {
              console.error('解析响应数据失败:', e);
              $('#orderTable tbody').html('<tr><td colspan="10" class="text-center text-red-400">加载数据失败</td></tr>');
            }
          },
          error: function() {
            $('#orderTable tbody').html('<tr><td colspan="10" class="text-center text-red-400">网络错误，请稍后重试</td></tr>');
          }
        });
      }
      
      // 更新分页信息显示
      function updatePaginationInfo() {
        const start = totalRecords === 0 ? 0 : (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, totalRecords);
        $('#recordsInfo').html(`显示第 ${start}-${end} 条，共 ${totalRecords} 条记录`);
      }
      
      // 更新分页控件
      function updatePaginationControls() {
        let paginationHtml = '';
        
        if (totalPages <= 1) {
          $('#paginationControls').html('');
          return;
        }
        
        // 上一页按钮
        paginationHtml += `<button class="pagination-btn" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>
          <i class="fas fa-chevron-left"></i>
        </button>`;
        
        // 页码按钮
        const maxVisiblePages = 7;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        // 调整起始页
        if (endPage - startPage + 1 < maxVisiblePages) {
          startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        // 第一页
        if (startPage > 1) {
          paginationHtml += `<button class="pagination-btn" data-page="1">1</button>`;
          if (startPage > 2) {
            paginationHtml += `<span class="pagination-ellipsis">...</span>`;
          }
        }
        
        // 中间页码
        for (let i = startPage; i <= endPage; i++) {
          paginationHtml += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }
        
        // 最后一页
        if (endPage < totalPages) {
          if (endPage < totalPages - 1) {
            paginationHtml += `<span class="pagination-ellipsis">...</span>`;
          }
          paginationHtml += `<button class="pagination-btn" data-page="${totalPages}">${totalPages}</button>`;
        }
        
        // 下一页按钮
        paginationHtml += `<button class="pagination-btn" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''}>
          <i class="fas fa-chevron-right"></i>
        </button>`;
        
        $('#paginationControls').html(paginationHtml);
      }
      
      // 加载计划ID选项（包含缩略图）
      function loadPlans() {
        $.ajax({
          url: '../../app/orderlist_actions.php',
          method: 'POST',
          data: {
            action: 'get_plans'
          },
          success: function(data) {
            var plans = JSON.parse(data);
            var options = '<option value="">所有计划</option>';
            plans.forEach(function(plan) {
              var thumbnailHtml = '';
              if (plan.thumbnail && plan.thumbnail.trim() !== '') {
                thumbnailHtml = ' data-thumbnail="' + plan.thumbnail + '"';
              }
              options += '<option value="' + plan.plan_id + '"' + thumbnailHtml + '>' + plan.plan_id + '</option>';
            });
            $('#planFilter').html(options);
            
            // 初始化自定义下拉框以显示缩略图
            initCustomSelect();
          }
        });
      }
      
      // 初始化自定义下拉框
      function initCustomSelect() {
        $('.custom-select').each(function() {
          var $container = $(this);
          var $select = $container.find('select');
          
          // 如果已经初始化过，先清理
          $container.find('.custom-select-trigger, .custom-select-options').remove();
          
          // 创建自定义触发器
          var $trigger = $('<div class="custom-select-trigger"></div>');
          var $triggerContent = $('<div class="text">所有计划</div>');
          var $arrow = $('<i class="fas fa-chevron-down arrow"></i>');
          
          $trigger.append($triggerContent).append($arrow);
          $container.append($trigger);
          
          // 创建选项容器
          var $options = $('<div class="custom-select-options"></div>');
          
          // 添加选项
          $select.find('option').each(function() {
            var $option = $(this);
            var value = $option.val();
            var text = $option.text();
            var thumbnail = $option.data('thumbnail');
            
            var $customOption = $('<div class="custom-select-option" data-value="' + value + '"></div>');
            
            if (thumbnail && thumbnail.trim() !== '') {
              var $img = $('<img class="thumbnail" src="' + thumbnail + '" alt="缩略图" onerror="this.style.display=\'none\'"/>');
              $customOption.append($img);
            }
            
            var $text = $('<div class="text">' + text + '</div>');
            $customOption.append($text);
            
            $options.append($customOption);
          });
          
          $container.append($options);
          
          // 点击触发器
          $trigger.on('click', function(e) {
            e.stopPropagation();
            $('.custom-select-trigger').not($trigger).removeClass('active');
            $('.custom-select-options').not($options).hide();
            
            $trigger.toggleClass('active');
            $options.toggle();
          });
          
          // 点击选项
          $options.on('click', '.custom-select-option', function() {
            var $option = $(this);
            var value = $option.data('value');
            var text = $option.find('.text').text();
            var $thumbnail = $option.find('.thumbnail');
            
            // 更新触发器内容
            $triggerContent.empty();
            if ($thumbnail.length > 0) {
              var $triggerImg = $thumbnail.clone().removeClass('thumbnail').addClass('thumbnail');
              $triggerContent.append($triggerImg);
            }
            $triggerContent.append('<span>' + text + '</span>');
            
            // 更新原始select的值
            $select.val(value).trigger('change');
            
            // 关闭下拉框
            $trigger.removeClass('active');
            $options.hide();
            
            // 更新选中状态
            $options.find('.custom-select-option').removeClass('selected');
            $option.addClass('selected');
          });
        });
        
        // 点击其他地方关闭下拉框
        $(document).on('click', function() {
          $('.custom-select-trigger').removeClass('active');
          $('.custom-select-options').hide();
        });
      }
      
      // 初始化
      loadOrders();
      loadPlans();
      
      // 搜索功能
      $('#searchBtn').on('click', function() {
        var query = $('#search').val();
        var planId = $('#planFilter').val();
        var perPageCount = parseInt($('#perPageSelect').val());
        loadOrders(query, planId, 1, perPageCount); // 搜索时重置到第1页
      });
      
      // 回车键搜索
      $('#search').on('keypress', function(e) {
        if (e.which === 13) {
          $('#searchBtn').click();
        }
      });
      
      // 计划ID筛选
      $('#planFilter').on('change', function() {
        var query = $('#search').val();
        var planId = $(this).val();
        var perPageCount = parseInt($('#perPageSelect').val());
        loadOrders(query, planId, 1, perPageCount); // 筛选时重置到第1页
      });
      
      // 每页数量变化
      $('#perPageSelect').on('change', function() {
        var query = $('#search').val();
        var planId = $('#planFilter').val();
        var perPageCount = parseInt($(this).val());
        loadOrders(query, planId, 1, perPageCount); // 改变每页数量时重置到第1页
      });
      
      // 分页按钮点击事件
      $(document).on('click', '.pagination-btn:not(:disabled)', function() {
        var page = parseInt($(this).data('page'));
        if (page && page !== currentPage) {
          var query = $('#search').val();
          var planId = $('#planFilter').val();
          var perPageCount = parseInt($('#perPageSelect').val());
          loadOrders(query, planId, page, perPageCount);
        }
      });
      // 编辑下载限制
      $(document).on('click', '.editBtn', function() {
        var orderId = $(this).data('order-id');
        $.ajax({
          url: '../../app/orderlist_actions.php',
          method: 'POST',
          data: {
            action: 'get',
            order_id: orderId
          },
          success: function(data) {
            var order = JSON.parse(data);
            $('#editOrderId').val(order.out_trade_no);
            $('#editDownloadsLimit').val(order.downloads_limit);
            $('#editModal').show();
          }
        });
      });
      // 保存修改
      $('#editForm').on('submit', function(e) {
        e.preventDefault();
        var orderId = $('#editOrderId').val();
        var downloadsLimit = $('#editDownloadsLimit').val();
        $.ajax({
          url: '../../app/orderlist_actions.php',
          method: 'POST',
          data: {
            action: 'update',
            order_id: orderId,
            downloads_limit: downloadsLimit
          },
          success: function(response) {
            alert(response);
            $('#editModal').hide();
            // 重新加载当前页面
            var query = $('#search').val();
            var planId = $('#planFilter').val();
            var perPageCount = parseInt($('#perPageSelect').val());
            loadOrders(query, planId, currentPage, perPageCount);
          }
        });
      });
      // 批量修改下载限制
      $('#batchUpdateBtn').on('click', function() {
        var downloadsLimit = $('#batchDownloadsLimit').val();
        var selectedOrders = [];
        $('#orderTable tbody input[type="checkbox"]:checked').each(function() {
          selectedOrders.push($(this).data('order-id'));
        });
        if (selectedOrders.length === 0) {
          alert('请先选择要修改的订单');
          return;
        }
        $.ajax({
          url: '../../app/orderlist_actions.php',
          method: 'POST',
          data: {
            action: 'batch_update',
            order_ids: selectedOrders,
            downloads_limit: downloadsLimit
          },
          success: function(response) {
            alert(response);
            // 重新加载当前页面
            var query = $('#search').val();
            var planId = $('#planFilter').val();
            var perPageCount = parseInt($('#perPageSelect').val());
            loadOrders(query, planId, currentPage, perPageCount);
          }
        });
      });
      // 全选/全不选
      $('#selectAll').on('click', function() {
        var isChecked = $(this).prop('checked');
        $('#orderTable tbody input[type="checkbox"]').prop('checked', isChecked);
      });
      
      // 订单清理功能
      $('#cleanupBtn').on('click', function() {
        $('#cleanupModal').show();
      });
      
      // 确认复选框控制执行按钮
      $('#confirmCleanup').on('change', function() {
        $('#executeCleanupBtn').prop('disabled', !$(this).prop('checked'));
      });
      
      // 取消清理
      $('#cancelCleanupBtn').on('click', function() {
        $('#cleanupModal').hide();
        $('#confirmCleanup').prop('checked', false);
        $('#executeCleanupBtn').prop('disabled', true);
      });
      
      // 执行清理
      $('#executeCleanupBtn').on('click', function() {
        var days = $('#cleanupDays').val();
        if (!days || days < 1) {
          alert('请输入有效的天数');
          return;
        }
        
        if (!confirm('确认要清理 ' + days + ' 天前的订单吗？此操作不可逆！')) {
          return;
        }
        
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 清理中...');
        
        $.ajax({
          url: '../../app/orderlist_actions.php',
          method: 'POST',
          data: {
            action: 'cleanup_orders',
            days: days
          },
          success: function(response) {
            alert(response);
            $('#cleanupModal').hide();
            $('#confirmCleanup').prop('checked', false);
            $('#executeCleanupBtn').prop('disabled', true).html('<i class="fas fa-trash-alt"></i> 执行清理');
            // 重新加载订单列表，可能需要调整页码
            var query = $('#search').val();
            var planId = $('#planFilter').val();
            var perPageCount = parseInt($('#perPageSelect').val());
            loadOrders(query, planId, 1, perPageCount); // 清理后重置到第1页
          },
          error: function() {
            alert('清理操作失败，请稍后重试');
            $('#executeCleanupBtn').prop('disabled', false).html('<i class="fas fa-trash-alt"></i> 执行清理');
          }
        });
      });
      
      // 点击模态框外部关闭模态框
      $(window).on('click', function(e) {
        if (e.target == $('#editModal')[0]) {
          $('#editModal').hide();
        }
        if (e.target == $('#cleanupModal')[0]) {
          $('#cleanupModal').hide();
          $('#confirmCleanup').prop('checked', false);
          $('#executeCleanupBtn').prop('disabled', true);
        }
      });
    });
  </script>
</body>

</html>