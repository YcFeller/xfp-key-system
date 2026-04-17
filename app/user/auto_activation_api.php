<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// 检查用户登录状态
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => '数据库连接失败']);
    exit;
}

try {
    switch ($action) {
        case 'check_unactivated_orders':
            checkUnactivatedOrders($conn, $userId);
            break;
        case 'get_user_auto_activation_setting':
            getUserAutoActivationSetting($conn, $userId);
            break;
        default:
            echo json_encode(['success' => false, 'error' => '无效的操作']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();

/**
 * 检查用户未激活订单
 */
function checkUnactivatedOrders($conn, $userId) {
    // 首先检查用户是否开启了自动激活提醒
    $stmt = $conn->prepare("SELECT auto_activation_enabled FROM user_settings WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = $result->fetch_assoc();
    $stmt->close();

    if (!$settings || !$settings['auto_activation_enabled']) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }

    // 获取用户的afdian_user_id
    $stmt = $conn->prepare("SELECT afdian_user_id FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['afdian_user_id'])) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }

    // 查询未激活订单
    $sql = "SELECT 
                o.*,
                COUNT(ar.id) as activation_count,
                o.downloads_limit - COUNT(ar.id) as remaining_activations
            FROM xfp_order o
            LEFT JOIN xfp_activation_records ar ON o.out_trade_no = ar.order_number
            WHERE o.user_id = ? AND o.downloads_limit > COUNT(ar.id)
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $user['afdian_user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'id' => $row['id'],
            'order_number' => $row['out_trade_no'],
            'plan_name' => $row['plan_name'],
            'amount' => $row['amount'],
            'remaining_activations' => $row['remaining_activations'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $orders]);
}

/**
 * 获取用户自动激活设置
 */
function getUserAutoActivationSetting($conn, $userId) {
    $stmt = $conn->prepare("SELECT auto_activation_enabled FROM user_settings WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = $result->fetch_assoc();
    $stmt->close();

    if (!$settings) {
        // 如果用户设置不存在，创建默认设置
        $stmt = $conn->prepare("INSERT INTO user_settings (user_id, auto_activation_enabled) VALUES (?, 0)");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'data' => ['auto_activation_enabled' => 0]]);
        return;
    }

    echo json_encode(['success' => true, 'data' => $settings]);
}
?> 