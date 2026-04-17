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
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>订单同步 - 爱发电订单</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
      width: 100vw;
      height: 100vh;
      background: rgba(0,0,0,0.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }
    .modal-content {
      background: #1e293b;
      border-radius: 1rem;
      padding: 2rem;
      max-width: 90vw;
      width: 400px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.3);
      text-align: center;
    }
  </style>
</head>
<body class="geometric-bg">
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
              <li><a href="./afd_paylist.php" class="text-white font-bold underline transition-colors">获取爱发电订单</a></li>
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
        <a href="../../index.php" class="!rounded-button px-6 py-2 text-gray-300 hover:text-white transition-colors">返回主页</a>
        <a href="../../pages/auth/logout.php" class="!rounded-button px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors">退出登录</a>
      </div>
    </header>
    <main class="py-20">
      <div class="max-w-5xl mx-auto bg-gray-800/30 backdrop-blur-lg rounded-2xl p-8 card-hover shadow-lg">
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-4xl font-bold text-white flex items-center gap-2"><i class="fa fa-cloud-download-alt"></i> 爱发电订单同步</h1>
          <button onclick="window.history.back();" class="rounded-button px-4 py-2 bg-gray-700 text-white hover:bg-gray-600 transition-colors flex items-center gap-2 text-base">
            <i class="fa fa-arrow-left"></i> 返回上一页
          </button>
        </div>
        <div id="errorMsg" class="mb-4"></div>
        <div id="loading" class="text-primary text-lg font-semibold flex items-center gap-2 mb-4" style="display:none;"><i class="fa fa-spinner fa-spin"></i> 正在加载订单...</div>
        <div id="ordersTable"></div>
        <div class="flex justify-between items-center mt-6">
          <button id="prevPage" class="rounded-button px-4 py-2 bg-primary text-white font-semibold hover:bg-opacity-90 transition-colors" disabled>上一页</button>
          <span id="pageInfo" class="text-gray-300"></span>
          <button id="nextPage" class="rounded-button px-4 py-2 bg-primary text-white font-semibold hover:bg-opacity-90 transition-colors" disabled>下一页</button>
        </div>
      </div>
    </main>
  </div>
  <!-- 同步弹窗 -->
  <div id="syncModal" class="modal">
    <div class="modal-content">
      <h2 class="text-xl font-bold text-white mb-4">是否同步更新这些订单到本地数据库？</h2>
      <div class="flex gap-4 justify-center mt-4">
        <button id="confirmSync" class="rounded-button px-6 py-2 bg-primary text-white font-semibold hover:bg-opacity-90 transition-colors">确认同步</button>
        <button id="cancelSync" class="rounded-button px-6 py-2 bg-gray-600 text-white font-semibold hover:bg-gray-500 transition-colors">取消</button>
      </div>
    </div>
  </div>
  <script>
    let currentPage = 1;
    let totalPages = 1;
    let lastOrders = [];
    function showError(msg) {
      document.getElementById('errorMsg').innerHTML = `<div class='bg-red-500/80 text-white rounded p-3 mb-2'>${msg}</div>`;
    }
    function clearError() {
      document.getElementById('errorMsg').innerHTML = '';
    }
    function showLoading(show) {
      document.getElementById('loading').style.display = show ? '' : 'none';
    }
    function renderOrders(orders) {
      if (!orders.length) {
        document.getElementById('ordersTable').innerHTML = '<div class="text-gray-400 text-center py-8">暂无订单数据</div>';
        return;
      }
      let html = `<table class='w-full text-sm text-left'><thead><tr>
        <th class='px-2 py-2'>用户ID</th><th class='px-2 py-2'>订单号</th><th class='px-2 py-2'>计划ID</th><th class='px-2 py-2'>月数</th><th class='px-2 py-2'>总金额</th><th class='px-2 py-2'>状态</th><th class='px-2 py-2'>备注</th><th class='px-2 py-2'>SKU详情</th></tr></thead><tbody>`;
      for (const sponsor of orders) {
        html += `<tr class='border-b border-gray-700'>
          <td class='px-2 py-2'>${sponsor.user_id}</td>
          <td class='px-2 py-2'>${sponsor.out_trade_no}</td>
          <td class='px-2 py-2'>${sponsor.plan_id}</td>
          <td class='px-2 py-2'>${sponsor.month}</td>
          <td class='px-2 py-2'>${sponsor.total_amount}</td>
          <td class='px-2 py-2'>${sponsor.status}</td>
          <td class='px-2 py-2 truncate max-w-[120px]' title='${sponsor.remark}'>${sponsor.remark}</td>
          <td class='px-2 py-2'>`;
        if (Array.isArray(sponsor.sku_detail)) {
          html += sponsor.sku_detail.map(sku => `<div class='mb-1 bg-gray-900 rounded p-2'><span class='text-xs text-gray-300'>${sku.name}</span><br><span class='text-xs text-gray-400'>数量: ${sku.count}</span></div>`).join('');
        }
        html += `</td></tr>`;
      }
      html += '</tbody></table>';
      document.getElementById('ordersTable').innerHTML = html;
    }
    function fetchOrders(page) {
      clearError();
      showLoading(true);
      fetch(`../../app/afd_paylist_api.php?page=${page}`)
        .then(res => res.json())
        .then(data => {
          showLoading(false);
          if (!data.success) {
            showError(data.error || '订单加载失败');
            document.getElementById('ordersTable').innerHTML = '';
            return;
          }
          lastOrders = data.orders;
          renderOrders(data.orders);
          currentPage = data.page;
          totalPages = data.total_pages;
          document.getElementById('pageInfo').textContent = `第 ${currentPage} / ${totalPages} 页`;
          document.getElementById('prevPage').disabled = currentPage <= 1;
          document.getElementById('nextPage').disabled = currentPage >= totalPages;
          // 加载完第一页后弹窗询问是否同步
          if (currentPage === 1 && data.orders.length > 0) {
            document.getElementById('syncModal').style.display = 'flex';
          }
        })
        .catch(err => {
          showLoading(false);
          showError('请求失败: ' + err);
        });
    }
    document.getElementById('prevPage').onclick = function() {
      if (currentPage > 1) fetchOrders(currentPage - 1);
    };
    document.getElementById('nextPage').onclick = function() {
      if (currentPage < totalPages) fetchOrders(currentPage + 1);
    };
    // 同步弹窗按钮
    document.getElementById('confirmSync').onclick = function() {
      document.getElementById('syncModal').style.display = 'none';
      showLoading(true);
      fetch('../../app/afd_paylist_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'sync', orders: lastOrders })
      })
      .then(res => res.json())
      .then(data => {
        showLoading(false);
        if (data.success) {
          showError('<span class="text-green-400">同步成功！' + (data.message || '') + '</span>');
  } else {
          showError(data.error || '同步失败');
        }
      })
      .catch(err => {
        showLoading(false);
        showError('同步请求失败: ' + err);
      });
    };
    document.getElementById('cancelSync').onclick = function() {
      document.getElementById('syncModal').style.display = 'none';
    };
    // 点击弹窗外部关闭弹窗
    window.onclick = function(e) {
      if (e.target === document.getElementById('syncModal')) {
        document.getElementById('syncModal').style.display = 'none';
      }
    };
    // 页面加载时拉取第一页
    fetchOrders(1);
  </script>
</body>
</html>