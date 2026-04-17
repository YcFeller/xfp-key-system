<?php
session_start();
// 快捷工具页面
// 检查用户是否已登录
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
  <title>解锁密码生成</title>
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
    .mode-btn {
      transition: all 0.3s ease;
    }
    .mode-btn.active {
      background-color: #3176FF;
      color: white;
    }
    .mode-btn:not(.active) {
      background-color: #374151;
      color: #9CA3AF;
    }
  </style>
</head>

<body class="geometric-bg">
  <div class="max-w-[1440px] mx-auto px-8">
    <header class="py-6 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <h1 class="text-3xl font-['Pacifico'] text-white">XFP密钥获取系统</h1>
        <nav class="ml-12">
          <ul class="flex flex-wrap gap-4 lg:gap-8 text-sm sm:text-base">
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
                <a href="./quickapplist_upload.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-b-lg">上传快应用</a>
              </div>
            </li>
            <li><a href="./shortcut_tool.php" class="text-white font-bold underline transition-colors">快捷工具</a></li>
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
      <div class="max-w-xl mx-auto bg-gray-800/30 backdrop-blur-lg rounded-2xl p-8 card-hover shadow-lg">
        <div class="flex items-center justify-between mb-4">
          <h1 class="text-4xl font-bold text-white flex items-center gap-2">
            <i class="fa fa-key"></i> 解锁密码生成/API
          </h1>
          <button onclick="window.history.back();" class="rounded-button px-4 py-2 bg-gray-700 text-white hover:bg-gray-600 transition-colors flex items-center gap-2 text-base">
            <i class="fa fa-arrow-left"></i> 返回上一页
          </button>
        </div>
        <p class="text-gray-300 mb-4">Watchface Locker专用，PHP源码请群内获取~<br><span class="text-red-400">注意，此网页仅供内部使用，请勿传播！</span></p>
        <div class="mb-4">
          <p>用户名：<span class="text-primary font-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></span></p>
          <p>用户ID：<span class="text-primary font-semibold"><?php echo htmlspecialchars($_SESSION['user_id']); ?></span></p>
          <p>用户权限：<span class="text-primary font-semibold"><?php echo htmlspecialchars($_SESSION['user_role']); ?></span></p>
        </div>
        
        <!-- 模式切换按钮 -->
        <div class="flex gap-2 mb-6">
          <button id="directMode" class="mode-btn active flex-1 py-3 px-4 rounded-button font-semibold" onclick="switchMode('direct')">
            <i class="fa fa-magic mr-2"></i>直接激活模式
          </button>
          <button id="orderMode" class="mode-btn flex-1 py-3 px-4 rounded-button font-semibold" onclick="switchMode('order')">
            <i class="fa fa-search mr-2"></i>订单搜索激活
          </button>
        </div>

        <!-- 直接激活模式表单 -->
        <form id="directForm" class="space-y-4" onsubmit="event.preventDefault(); generateUnlockPassword();">
          <div>
            <label for="psn" class="block text-gray-300 mb-1">设备码:</label>
            <input type="text" id="psn" name="psn" placeholder="您的设备码" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
          </div>
          <div>
            <label for="wfId" class="block text-gray-300 mb-1">表盘ID:</label>
            <input type="text" id="wfId" name="wfId" placeholder="表盘ID" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
          </div>
          <div>
            <label for="psw" class="block text-gray-300 mb-1">内测验证码（废弃，现仅api需要）:</label>
            <input type="text" id="psw" name="psw" placeholder="登录后可用" value="xr1688s" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
          </div>
          <button type="submit" class="w-full rounded-button py-3 bg-primary text-white text-lg font-semibold hover:bg-opacity-90 transition-colors flex items-center justify-center gap-2"><i class="fa fa-magic"></i> 生成解锁密码</button>
        </form>

        <!-- 订单搜索激活模式表单 -->
        <form id="orderForm" class="space-y-4 hidden" onsubmit="event.preventDefault(); searchOrder();">
          <div>
            <label for="orderNumber" class="block text-gray-300 mb-1">订单号:</label>
            <input type="text" id="orderNumber" name="orderNumber" placeholder="请输入订单号" required maxlength="30" pattern="[A-Za-z0-9]{1,30}" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
          </div>
          <div>
            <label for="deviceCode" class="block text-gray-300 mb-1">设备码:</label>
            <input type="text" id="deviceCode" name="deviceCode" placeholder="您的设备码" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
          </div>
          <div>
            <label for="orderCaptcha" class="block text-gray-300 mb-1">验证码:</label>
            <input type="text" id="orderCaptcha" name="orderCaptcha" placeholder="登录后可用" value="xr1688s" required class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none">
          </div>
          <button type="submit" class="w-full rounded-button py-3 bg-secondary text-white text-lg font-semibold hover:bg-opacity-90 transition-colors flex items-center justify-center gap-2"><i class="fa fa-search"></i> 搜索订单</button>
        </form>

        <!-- 订单信息显示区域 -->
        <div id="orderInfo" class="mt-6 hidden">
          <div class="bg-gray-700/50 rounded-lg p-4 mb-4">
            <h3 class="text-lg font-semibold text-white mb-3">订单信息</h3>
            <div id="orderDetails" class="space-y-2 text-gray-300"></div>
            <div class="mt-4">
              <button id="activateBtn" onclick="activateOrder()" class="w-full rounded-button py-3 bg-green-600 text-white text-lg font-semibold hover:bg-green-700 transition-colors flex items-center justify-center gap-2">
                <i class="fa fa-check"></i> 确认激活（扣除1次激活次数）
              </button>
            </div>
          </div>
        </div>

        <button id="toggleSidebar" class="w-full mt-4 rounded-button py-3 bg-secondary text-white text-lg font-semibold hover:bg-opacity-90 transition-colors flex items-center justify-center gap-2"><i class="fa fa-info-circle"></i> 显示/隐藏 API使用方式</button>
        <div id="result" class="mt-6 text-center">
          <p class="font-bold text-lg">点击"生成解锁密码"按钮后将会在这里输出结果!</p>
        </div>
      </div>
      <div class="sidebar bg-gray-900/90 rounded-2xl p-6 mt-8 max-w-xl mx-auto shadow-lg" style="display: none;">
        <div class="box">
          <h2 class="text-2xl font-bold text-white mb-4">API请求示例：</h2>
          <div class="box_demo mb-4">
            <h3 class="text-lg font-semibold text-primary mb-1">GET请求:</h3>
            <p class="text-gray-300 mb-1">GET请求用于从服务器获取数据。它通常用于查询或检索数据。</p>
            <p class="text-gray-300 mb-1">在URL中添加参数，参数以<code>?</code>开头，每个参数之间用<code>&</code>分隔。</p>
            <p class="text-gray-300 mb-1">格式：<span class="bg-gray-800 px-2 py-1 rounded">https://api.fs0.top/?psn=<span class="text-primary">your_psn</span>&wfId=<span class="text-primary">your_wfId</span>&psw=<span class="text-primary">测试密码</span></span></p>
            <p class="text-gray-300 mb-1">访问体验：<a href="https://api.fs0.top/wflocker/api.php?psn=123&wfId=123&psw=请先获取" class="text-primary underline" target="_blank">https://api.fs0.top/wflocker/api.php?psn=123&wfId=123&psw=请先获取</a></p>
          </div>
          <div class="box_demo mb-4">
            <h3 class="text-lg font-semibold text-primary mb-1">POST请求:</h3>
            <p class="text-gray-300 mb-1">POST请求用于向服务器发送数据。它通常用于提交表单数据或上传文件。</p>
            <p class="text-gray-300 mb-1">使用<code>curl</code>命令时，使用<code>-d</code>选项来指定要发送的数据。</p>
            <p class="text-gray-300 mb-1">例如：<span class="bg-gray-800 px-2 py-1 rounded">curl -X POST -d "psn=your_psn&wfId=your_wfId&psw=123456" https://api.fs0.top/</span></p>
          </div>
          <p class="text-gray-400">懒得整了，手机覆盖了刷新就行了</p>
        </div>
      </div>
    </main>
  </div>
  <script>
    let currentMode = 'direct';
    let currentOrderData = null;

    function switchMode(mode) {
      currentMode = mode;
      
      // 更新按钮状态
      document.getElementById('directMode').classList.toggle('active', mode === 'direct');
      document.getElementById('orderMode').classList.toggle('active', mode === 'order');
      
      // 切换表单显示
      document.getElementById('directForm').classList.toggle('hidden', mode !== 'direct');
      document.getElementById('orderForm').classList.toggle('hidden', mode !== 'order');
      
      // 隐藏订单信息和结果
      document.getElementById('orderInfo').classList.add('hidden');
      document.getElementById('result').innerHTML = '<p class="font-bold text-lg">点击"生成解锁密码"按钮后将会在这里输出结果!</p>';
    }

    function generateUnlockPassword() {
      const psn = document.getElementById('psn').value;
      const wfId = document.getElementById('wfId').value;
      const psw = document.getElementById('psw').value;
      if (!psn || !wfId || !psw) {
        document.getElementById('result').innerHTML = '<p class="text-red-400">请填写所有必填项。</p>';
        return;
      }
      document.getElementById('result').innerHTML = '<span class="text-primary"><i class="fa fa-spinner fa-spin"></i> 正在生成...</span>';
      fetch('../../app/shortcut_tool_api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `psn=${encodeURIComponent(psn)}&wfId=${encodeURIComponent(wfId)}&psw=${encodeURIComponent(psw)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.error) {
          document.getElementById('result').innerHTML = `<p class="text-red-400">${data.error}</p>`;
        } else {
          document.getElementById('result').innerHTML = `<p class="text-green-400">解锁密码: <strong>${data.unlock_pwd}</strong></p>`;
        }
      })
      .catch(error => {
        document.getElementById('result').innerHTML = `<p class="text-red-400">请求失败，请稍后重试。</p>`;
      });
    }

    function searchOrder() {
      const orderNumber = document.getElementById('orderNumber').value.trim();
      const deviceCode = document.getElementById('deviceCode').value;
      const orderCaptcha = document.getElementById('orderCaptcha').value;
      if (!orderNumber || !deviceCode || !orderCaptcha) {
        document.getElementById('result').innerHTML = '<p class="text-red-400">请填写所有必填项。</p>';
        return;
      }
      if (!/^[A-Za-z0-9]{1,30}$/.test(orderNumber)) {
        document.getElementById('result').innerHTML = '<p class="text-red-400">订单号只能为1-30位字母或数字。</p>';
        return;
      }

      document.getElementById('result').innerHTML = '<span class="text-primary"><i class="fa fa-spinner fa-spin"></i> 正在搜索订单...</span>';
      
      fetch('../../app/shortcut_tool_api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `action=search_order&order_number=${encodeURIComponent(orderNumber)}&device_code=${encodeURIComponent(deviceCode)}&order_captcha=${encodeURIComponent(orderCaptcha)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.error) {
          document.getElementById('result').innerHTML = `<p class="text-red-400">${data.error}</p>`;
          document.getElementById('orderInfo').classList.add('hidden');
        } else {
          currentOrderData = data;
          displayOrderInfo(data);
          document.getElementById('result').innerHTML = '<p class="text-green-400">订单搜索成功！请查看订单信息并确认激活。</p>';
        }
      })
      .catch(error => {
        document.getElementById('result').innerHTML = `<p class="text-red-400">请求失败，请稍后重试。</p>`;
      });
    }

    function displayOrderInfo(orderData) {
      const orderDetails = document.getElementById('orderDetails');
      let html = '';
      
      html += `<p><strong>订单号:</strong> ${orderData.order_number}</p>`;
      html += `<p><strong>剩余激活次数:</strong> <span class="text-yellow-400">${orderData.downloads_limit}</span></p>`;
      html += `<p><strong>设备码:</strong> ${orderData.device_code}</p>`;
      
      if (orderData.watchfaces && orderData.watchfaces.length > 0) {
        html += '<div class="mt-3"><strong>关联表盘:</strong></div>';
        orderData.watchfaces.forEach((watchface, index) => {
          html += `<div class="ml-4 mt-2 p-2 bg-gray-600/50 rounded">`;
          html += `<p><strong>表盘名称:</strong> ${watchface.watchface_name}</p>`;
          html += `<p><strong>表盘ID:</strong> ${watchface.watchface_id}</p>`;
          if (watchface.watchface_image) {
            html += `<img src="${watchface.watchface_image}" alt="表盘图片" class="w-16 h-16 object-cover rounded mt-2">`;
          }
          html += `</div>`;
        });
      }
      
      orderDetails.innerHTML = html;
      document.getElementById('orderInfo').classList.remove('hidden');
    }

    function activateOrder() {
      if (!currentOrderData) {
        document.getElementById('result').innerHTML = '<p class="text-red-400">没有可激活的订单数据。</p>';
        return;
      }

      document.getElementById('result').innerHTML = '<span class="text-primary"><i class="fa fa-spinner fa-spin"></i> 正在激活...</span>';
      
      fetch('../../app/shortcut_tool_api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `action=activate_order&order_number=${encodeURIComponent(currentOrderData.order_number)}&device_code=${encodeURIComponent(currentOrderData.device_code)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.error) {
          document.getElementById('result').innerHTML = `<p class="text-red-400">${data.error}</p>`;
        } else {
          let resultHtml = '<div class="text-green-400">';
          resultHtml += '<p><strong>激活成功！</strong></p>';
          if (data.unlock_pwds && data.unlock_pwds.length > 0) {
            resultHtml += '<p class="mt-2"><strong>解锁密码:</strong></p>';
            data.unlock_pwds.forEach(pwd => {
              resultHtml += `<p class="ml-4">表盘ID ${pwd.watchface_id}: <strong>${pwd.unlock_pwd}</strong></p>`;
            });
          }
          resultHtml += `<p class="mt-2">剩余激活次数: <strong>${data.remaining}</strong></p>`;
          resultHtml += '</div>';
          document.getElementById('result').innerHTML = resultHtml;
          
          // 隐藏订单信息
          document.getElementById('orderInfo').classList.add('hidden');
        }
      })
      .catch(error => {
        document.getElementById('result').innerHTML = `<p class="text-red-400">激活失败，请稍后重试。</p>`;
      });
    }

    document.getElementById('toggleSidebar').addEventListener('click', function() {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar.style.display === 'none') {
        sidebar.style.display = 'block';
      } else {
        sidebar.style.display = 'none';
      }
    });
  </script>
</body>

</html>