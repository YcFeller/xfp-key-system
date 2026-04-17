<?php
session_start();
// 设置响应头为JSON格式，UTF-8编码
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';
require_once __DIR__ . '/../UnlockKeyDerivation.php';

// 检查用户是否已登录
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    // 未登录，返回错误信息
    echo json_encode(['success' => false, 'error' => '未登录'], JSON_UNESCAPED_UNICODE); 
    exit;
}

// 获取action参数，支持POST和GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 连接数据库
$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
    // 数据库连接失败
    echo json_encode(['success' => false, 'error' => '数据库连接失败'], JSON_UNESCAPED_UNICODE); 
    exit;
}

// 查询当前用户的afdian_user_id（爱发电用户ID）
$stmt = $conn->prepare("SELECT afdian_user_id FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$afdian_user_id = $user['afdian_user_id'] ?? '';
if (!$afdian_user_id) {
    // 用户未绑定爱发电ID
    echo json_encode(['success' => false, 'error' => '未绑定爱发电ID'], JSON_UNESCAPED_UNICODE); 
    exit;
}

// 处理获取订单列表的请求
if ($action === 'get_orders') {
    $orders = [];
    // 查询该用户的所有订单，按id倒序排列
    $stmt = $conn->prepare("SELECT * FROM xfp_order WHERE user_id = ? ORDER BY id DESC");
    $stmt->bind_param('s', $afdian_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // 格式化sku_detail为数组，便于前端处理
        $sku = $row['sku_detail'];
        $sku_arr = [];
        if ($sku) {
            // 优先尝试json解码
            $json = json_decode($sku, true);
            if (is_array($json)) {
                $sku_arr = $json;
            } else {
                // 如果不是json，则尝试用;或,分割
                $sku_arr = preg_split('/[;,]/', $sku);
            }
        }
        $row['sku_detail_arr'] = $sku_arr;
        $orders[] = $row;
    }
    $stmt->close();
    $conn->close();
    // 返回订单列表
    echo json_encode(['success' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE); 
    exit;
}

// 处理订单激活请求
if ($action === 'activate_order') {
    $order_number = $_POST['order_number'] ?? '';
    $device_code = $_POST['device_code'] ?? '';
    $psw = $_POST['psw'] ?? '';

    // 校验参数
    if (empty($order_number) || empty($device_code)) {
        echo json_encode(['success' => false, 'error' => '请输入订单号和设备码'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (empty($psw)) {
        echo json_encode(['success' => false, 'error' => '请输入验证码'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!isset($_SESSION['captcha'])) {
        echo json_encode(['success' => false, 'error' => '验证码已失效，请刷新验证码'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($psw !== $_SESSION['captcha']) {
        unset($_SESSION['captcha']);
        echo json_encode(['success' => false, 'error' => '验证码错误，请重新输入'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    unset($_SESSION['captcha']);

    // 查询订单信息
    $stmt = $conn->prepare("SELECT downloads_limit, plan_id FROM xfp_order WHERE out_trade_no = ? AND user_id = ?");
    $stmt->bind_param('ss', $order_number, $afdian_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => '订单号不存在或无权操作'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $order = $result->fetch_assoc();
    $stmt->close();

    // 检查剩余下载次数
    if ($order['downloads_limit'] <= 0) {
        echo json_encode(['success' => false, 'error' => '剩余次数为零，无法生成解锁密码'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 扣减下载次数
    $stmt = $conn->prepare("UPDATE xfp_order SET downloads_limit = downloads_limit - 1 WHERE out_trade_no = ?");
    $stmt->bind_param('s', $order_number);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => '更新下载次数失败: ' . $stmt->error], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->close();

    // 查询所有表盘ID
    $stmt = $conn->prepare("SELECT watchface_id FROM xfp_wflist WHERE plan_id = ?");
    $stmt->bind_param('s', $order['plan_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => '未找到匹配的表盘ID'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $unlock_pwds = [];
    try {
    while ($wf = $result->fetch_assoc()) {
        $wf_id = $wf['watchface_id'];
        $unlock_pwd = UnlockKeyDerivation::derive($device_code, $wf_id, 'watchface');
        $unlock_pwds[] = [
            'watchface_id' => $wf_id,
            'unlock_pwd' => $unlock_pwd
        ];
    }
    } catch (Throwable $e) {
        $rb = $conn->prepare("UPDATE xfp_order SET downloads_limit = downloads_limit + 1 WHERE out_trade_no = ?");
        $rb->bind_param('s', $order_number);
        $rb->execute();
        $rb->close();
        $conn->close();
        echo json_encode(['success' => false, 'error' => '解锁密码生成失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->close();

    // 保存激活记录
    $activation_record_saved = false;
    foreach ($unlock_pwds as $pwd) {
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM xfp_activation_records WHERE order_number = ? AND watchface_id = ? AND user_id = ? AND device_code = ? AND unlock_pwd = ?");
        $check_stmt->bind_param("ssiss", $order_number, $pwd['watchface_id'], $user_id, $device_code, $pwd['unlock_pwd']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();
        $check_stmt->close();
        if ($check_row['count'] == 0) {
            $insert_stmt = $conn->prepare("INSERT INTO xfp_activation_records (order_number, watchface_id, user_id, device_code, unlock_pwd, activation_time) VALUES (?, ?, ?, ?, ?, NOW())");
            $insert_stmt->bind_param("ssiss", $order_number, $pwd['watchface_id'], $user_id, $device_code, $pwd['unlock_pwd']);
            if ($insert_stmt->execute()) {
                $activation_record_saved = true;
            }
            $insert_stmt->close();
        }
    }

    // 查询最新剩余下载次数
    $stmt = $conn->prepare("SELECT downloads_limit FROM xfp_order WHERE out_trade_no = ?");
    $stmt->bind_param('s', $order_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $remaining = 0;
    if ($row = $result->fetch_assoc()) {
        $remaining = $row['downloads_limit'];
    }
    $stmt->close();

    $conn->close();
    echo json_encode([
        'success' => true,
        'data' => [
            'unlock_pwds' => $unlock_pwds,
            'device_code' => $device_code,
            'remaining' => $remaining,
            'activation_record_saved' => $activation_record_saved
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 其他action，未定义的操作
$conn->close();
echo json_encode(['success' => false, 'error' => '不支持的操作'], JSON_UNESCAPED_UNICODE);
exit;
