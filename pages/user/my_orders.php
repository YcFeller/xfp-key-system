<?php
session_start();
require_once '../../app/config.php';
// 验证用户是否登录
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 1;
if ($user_role === null) {
    header("Location: ../auth/login.php");
    exit;
} elseif ($user_role < $required_role) {
    header("Location: ../../index.php");
    exit;
}
// 连接数据库
$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
    die('数据库连接失败。');
}
// 获取当前用户的afdian_user_id
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT afdian_user_id FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$afdian_user_id = $user['afdian_user_id'] ?? '';
$orders = [];
if ($afdian_user_id) {
    $stmt = $conn->prepare("SELECT * FROM xfp_order WHERE user_id = ? ORDER BY id DESC");
    $stmt->bind_param('s', $afdian_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的订单 - XFP密钥获取系统</title>
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
    <script src="../../files/js/jquery-3.6.0.min.js"></script>
    <style>
        @font-face {
            font-family: 'MiSans';
            src: url('../../files/font/misans.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        body { font-family: 'MiSans', sans-serif; }
    </style>
</head>
<body class="geometric-bg bg-[#0F172A] min-h-screen">
    <div class="max-w-[1200px] mx-auto px-4 py-8">
        <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-8 mb-8 flex flex-col md:flex-row md:items-center md:justify-between shadow card-hover">
            <div>
                <h1 class="text-3xl font-bold text-white flex items-center gap-3"><i class="fa fa-shopping-cart text-primary"></i> 我的订单</h1>
                <p class="text-gray-400 mt-2">显示您通过爱发电购买的所有订单</p>
            </div>
            <a href="index.php" class="mt-4 md:mt-0 px-6 py-2 bg-secondary text-white rounded-button font-semibold hover:bg-opacity-90 transition-colors flex items-center gap-2"><i class="fa fa-arrow-left"></i> 返回客户中心</a>
        </div>
        <div id="globalMessage" class="mb-6"></div>
        <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-8 shadow card-hover mb-8">
            <h2 class="text-2xl font-semibold text-white flex items-center gap-2 mb-6"><i class="fa fa-list"></i> 订单列表</h2>
            <div id="ordersContainer">
                <div class="text-center text-gray-400 py-12"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>
            </div>
        </div>
    </div>
    <!-- 激活订单弹窗 -->
    <div id="activationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-backdrop">
        <div class="bg-gray-800 rounded-2xl p-6 w-full max-w-md mx-4 modal-content">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white flex items-center gap-2"><i class="fa fa-key text-primary"></i> 快速激活订单</h3>
                <button class="text-gray-400 hover:text-white transition-colors" onclick="closeActivationModal()"><i class="fa fa-times text-xl"></i></button>
            </div>
            <form id="activationForm" class="space-y-4">
                <input type="hidden" id="activationOrderNumber" name="order_number">
                <div class="mb-2 p-3 bg-yellow-900/20 border-l-4 border-yellow-500 rounded text-yellow-300 text-sm flex items-center gap-2">
                    <i class="fa fa-exclamation-triangle"></i>
                    激活操作会减少下载次数，请确认设备码和验证码无误后再操作。
                </div>
                <div>
                    <label class="block text-gray-300 mb-1">设备码 (PSN)</label>
                    <input type="text" id="deviceCode" name="device_code" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" placeholder="请输入设备码" required>
                </div>
                <div>
                    <label class="block text-gray-300 mb-1">验证码</label>
                    <div class="flex items-center gap-2">
                        <input type="text" id="captchaCode" name="psw" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" placeholder="请输入验证码" required autocomplete="off" maxlength="6" style="max-width:120px;">
                        <img id="captchaImg" src="../../app/captcha.php" alt="验证码" class="h-10 rounded border cursor-pointer select-none" title="点击刷新验证码" onclick="refreshCaptcha()">
                        <button type="button" class="text-gray-400 hover:text-white px-2 py-1" onclick="refreshCaptcha()" title="刷新验证码"><i class="fa fa-sync-alt"></i></button>
                    </div>
                </div>
                <div class="flex gap-3 pt-2 justify-end">
                    <button type="button" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-500 transition-colors" onclick="closeActivationModal()">取消</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-opacity-90 transition-colors flex items-center gap-2">
                        <span id="activateBtnText">立即激活</span>
                        <span id="activateBtnLoading" class="hidden"><i class="fa fa-spinner fa-spin"></i> 激活中...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- 激活结果弹窗 -->
    <div id="resultModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-backdrop">
        <div class="bg-gray-800 rounded-2xl p-6 w-full max-w-md mx-4 modal-content">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white flex items-center gap-2"><i class="fa fa-check-circle text-primary"></i> 激活结果</h3>
                <button class="text-gray-400 hover:text-white transition-colors" onclick="closeResultModal()"><i class="fa fa-times text-xl"></i></button>
            </div>
            <div id="resultContent" class="text-white mb-6"></div>
            <div id="resultMailNotice" class="mb-4 hidden">
                <div class="p-3 bg-blue-900/20 border-l-4 border-blue-500 rounded text-blue-300 text-sm flex items-center gap-2">
                    <i class="fa fa-envelope"></i>
                    激活信息已发送到您的邮箱，请注意查收。如未收到请检查垃圾箱。
                </div>
            </div>
            <div class="flex gap-3 pt-2 justify-end">
                <button type="button" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-500 transition-colors" onclick="closeResultModal()">关闭</button>
            </div>
        </div>
    </div>
    <script>
        // 全局消息提示函数
        function showMessage(message, type = 'success') {
            const colorClass = type === 'error' ? 'text-red-400' : 'text-green-400';
            const bgClass = type === 'error' ? 'bg-red-900/20 border-red-500/30' : 'bg-green-900/20 border-green-500/30';
            const iconClass = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
            const messageHtml = `
                <div class="p-4 rounded-lg border-2 ${bgClass} flex items-center gap-3">
                    <i class="fa ${iconClass} text-lg ${colorClass}"></i>
                    <span class="${colorClass} font-medium">${message}</span>
                    <button onclick="hideGlobalMessage()" class="ml-auto text-gray-400 hover:text-white transition-colors message-close-btn">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            `;
            $('#globalMessage').html(messageHtml);
            if (type === 'success') {
                setTimeout(() => { hideGlobalMessage(); }, 5000);
            }
        }
        function hideGlobalMessage() { $('#globalMessage').empty(); }
        // 激活弹窗
        function openActivationModal(orderNumber) {
            $('#activationOrderNumber').val(orderNumber);
            $('#deviceCode').val('');
            $('#captchaCode').val('');
            refreshCaptcha();
            $('#activationModal').removeClass('hidden');
        }
        function closeActivationModal() {
            $('#activationModal').addClass('hidden');
        }
        function closeResultModal() {
            $('#resultModal').addClass('hidden');
            // 隐藏邮箱通知提示
            $('#resultMailNotice').addClass('hidden');
        }
        function refreshCaptcha() {
            $('#captchaImg').attr('src', '../../app/captcha.php?' + Date.now());
        }
        // 激活订单表单提交
        $('#activationForm').on('submit', function(e) {
            e.preventDefault();
            const order_number = $('#activationOrderNumber').val();
            const device_code = $('#deviceCode').val();
            const psw = $('#captchaCode').val();
            if (!device_code) {
                showMessage('请输入设备码', 'error');
                return;
            }
            if (!psw) {
                showMessage('请输入验证码', 'error');
                return;
            }
            const $btn = $('#activateBtnText');
            const $loading = $('#activateBtnLoading');
            $btn.addClass('hidden');
            $loading.removeClass('hidden');
            $.ajax({
                url: '../../app/user/user_orders_api.php',
                method: 'POST',
                data: {
                    action: 'activate_order',
                    order_number: order_number,
                    device_code: device_code,
                    psw: psw
                },
                success: function(response) {
                    if (response.success) {
                        closeActivationModal();
                        showActivationResult(response.data);
                        showMessage('激活成功！请查看下方激活结果。');
                    } else {
                        showMessage(response.error || '激活失败，请重试', 'error');
                        refreshCaptcha();
                    }
                    resetActivateButton();
                },
                error: function(xhr) {
                    showMessage('激活失败: ' + xhr.statusText, 'error');
                    refreshCaptcha();
                    resetActivateButton();
                }
            });
            function resetActivateButton() {
                $btn.removeClass('hidden');
                $loading.addClass('hidden');
            }
        });
        // 显示激活结果（支持多表盘）
        function showActivationResult(data) {
            let content = '';
            if (data.unlock_pwds && Array.isArray(data.unlock_pwds)) {
                content += '<div class="bg-gray-700/50 rounded-lg p-4 mb-4">';
                content += '<div class="mb-2"><strong class="text-green-400">激活成功！</strong></div>';
                content += `<div class="mb-1"><span class="text-gray-400">设备码：</span><span class="text-white">${data.device_code}</span></div>`;
                data.unlock_pwds.forEach(item => {
                    content += `<div class="mb-1"><span class="text-gray-400">表盘ID：</span><span class="text-white">${item.watchface_id}</span></div>`;
                    content += `<div class="mb-2"><span class="text-gray-400">解锁密码：</span><span class="text-primary font-bold text-lg">${item.unlock_pwd}</span></div>`;
                });
                if (typeof data.remaining !== 'undefined' && data.remaining !== null) {
                    content += `<div class="mt-2 text-yellow-300 text-sm">剩余下载次数：<span class="font-bold">${data.remaining}</span></div>`;
                }
                content += '</div>';
                content += '<p class="text-gray-400 text-sm">请将解锁密码输入到您的设备中完成激活。密码已自动保存到激活记录中。</p>';
            } else {
                content = '<div class="text-red-400">未获取到激活结果，请重试。</div>';
            }
            $('#resultContent').html(content);
            $('#resultModal').removeClass('hidden');
            // 显示邮箱通知提示
            $('#resultMailNotice').removeClass('hidden');
            // 激活后自动刷新订单列表
            loadOrders();
        }
        // 渲染订单卡片，sku只显示name和pic
        function renderOrders(orders) {
            if (!orders || orders.length === 0) {
                $('#ordersContainer').html(`
                    <div class=\"text-center text-gray-400 py-12\">
                        <i class=\"fa fa-shopping-cart text-4xl mb-4\"></i>
                        <p class=\"text-lg\">暂无订单记录</p>
                        <p class=\"text-sm mt-2\">您还没有任何订单，请先购买产品</p>
                    </div>
                `);
                return;
            }
            let html = '<div class="grid gap-6">';
            orders.forEach(order => {
                let skuHtml = '';
                if (order.sku_detail_arr && order.sku_detail_arr.length > 0) {
                    skuHtml = '<ul class="list-disc pl-5 text-xs text-blue-300">';
                    order.sku_detail_arr.forEach(item => {
                        let name = '', pic = '';
                        if (typeof item === 'object') {
                            name = item.name || '';
                            pic = item.pic || '';
                        } else {
                            name = item;
                        }
                        skuHtml += `<li class=\"flex items-center gap-2\">`;
                        if (pic) {
                            skuHtml += `<img src=\"${pic}\" alt=\"订单图片\" class=\"w-8 h-8 rounded shadow border\">`;
                        }
                        skuHtml += `<span>${name}</span></li>`;
                    });
                    skuHtml += '</ul>';
                }
                html += `
                <div class=\"bg-gray-700/50 rounded-xl p-6 border-l-4 border-primary shadow flex flex-col gap-3\">
                    <div class=\"flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-2\">
                        <div class=\"text-white font-bold text-lg\">订单号：${order.out_trade_no}</div>
                        <span class=\"px-3 py-1 rounded-full text-xs font-semibold border bg-green-900/20 text-green-400 border-green-500/30\">有效</span>
                    </div>
                    <div class=\"grid grid-cols-2 md:grid-cols-4 gap-4 text-sm\">
                        <div><span class=\"text-gray-400\">商品名：</span><span class=\"text-white font-medium\">${order.product_name}</span></div>
                        <div><span class=\"text-gray-400\">金额：</span><span class=\"text-white\">¥${order.total_amount}</span></div>
                        <div><span class=\"text-gray-400\">套餐ID：</span><span class=\"text-white\">${order.plan_id}</span></div>
                        <div><span class=\"text-gray-400\">下载次数：</span><span class=\"text-white\">${order.downloads_limit}</span></div>
                        <div><span class=\"text-gray-400\">备注：</span><span class=\"text-white\">${order.remark || ''}</span></div>
                        <div><span class=\"text-gray-400\">折扣：</span><span class=\"text-white\">${order.discount || ''}</span></div>
                        <div><span class=\"text-gray-400\">SKU：</span>${skuHtml}</div>
                    </div>
                    <div class=\"flex flex-wrap gap-3 mt-2\">
                        <button class=\"px-4 py-2 bg-green-600 text-white rounded-button font-semibold hover:bg-green-700 transition-colors flex items-center gap-2\" onclick=\"openActivationModal('${order.out_trade_no}')\">
                            <i class=\"fa fa-key\"></i> 快速激活
                        </button>
                    </div>
                </div>
                `;
            });
            html += '</div>';
            $('#ordersContainer').html(html);
        }
        function loadOrders() {
            $('#ordersContainer').html('<div class="text-center text-gray-400 py-12"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>');
            $.ajax({
                url: '../../app/user/user_orders_api.php',
                method: 'POST',
                data: { action: 'get_orders' },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        renderOrders(res.orders);
                    } else {
                        $('#ordersContainer').html('<div class="text-center text-red-400 py-12">' + (res.error || '加载失败') + '</div>');
                    }
                },
                error: function(xhr) {
                    $('#ordersContainer').html('<div class="text-center text-red-400 py-12">加载失败: ' + xhr.statusText + '</div>');
                }
            });
        }
        $(document).ready(function() {
            loadOrders();
        });
    </script>
</body>
</html> 