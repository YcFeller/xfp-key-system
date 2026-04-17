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
        case 'get_settings':
            getUserSettings($conn, $userId);
            break;
        case 'update_settings':
            updateUserSettings($conn, $userId);
            break;
        case 'toggle_auto_activation':
            toggleAutoActivation($conn, $userId);
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
 * 获取用户设置
 */
function getUserSettings($conn, $userId) {
    $stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = $result->fetch_assoc();
    $stmt->close();

    if (!$settings) {
        // 如果用户设置不存在，创建默认设置
        $stmt = $conn->prepare("INSERT INTO user_settings (user_id, auto_activation_enabled, email_notifications, theme_preference, language_preference) VALUES (?, 0, 1, 'dark', 'zh-CN')");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $settings = [
            'user_id' => $userId,
            'auto_activation_enabled' => 1,
            'email_notifications' => 1,
            'theme_preference' => 'dark',
            'language_preference' => 'zh-CN'
        ];
    }

    echo json_encode(['success' => true, 'data' => $settings]);
}

/**
 * 更新用户设置
 */
function updateUserSettings($conn, $userId) {
    $autoActivation = isset($_POST['auto_activation_enabled']) ? (int)$_POST['auto_activation_enabled'] : 0;
    $emailNotifications = isset($_POST['email_notifications']) ? (int)$_POST['email_notifications'] : 1;
    $themePreference = $_POST['theme_preference'] ?? 'dark';
    $languagePreference = $_POST['language_preference'] ?? 'zh-CN';

    // 验证输入
    if (!in_array($themePreference, ['dark', 'light'])) {
        echo json_encode(['success' => false, 'error' => '无效的主题设置']);
        return;
    }

    if (!in_array($languagePreference, ['zh-CN', 'en-US'])) {
        echo json_encode(['success' => false, 'error' => '无效的语言设置']);
        return;
    }

    // 检查设置是否存在
    $stmt = $conn->prepare("SELECT id FROM user_settings WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->fetch_assoc();
    $stmt->close();

    if ($exists) {
        // 更新现有设置
        $stmt = $conn->prepare("UPDATE user_settings SET 
            auto_activation_enabled = ?, 
            email_notifications = ?, 
            theme_preference = ?, 
            language_preference = ?,
            updated_at = NOW()
            WHERE user_id = ?");
        $stmt->bind_param('iissi', $autoActivation, $emailNotifications, $themePreference, $languagePreference, $userId);
    } else {
        // 创建新设置
        $stmt = $conn->prepare("INSERT INTO user_settings 
            (user_id, auto_activation_enabled, email_notifications, theme_preference, language_preference) 
            VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iiiss', $userId, $autoActivation, $emailNotifications, $themePreference, $languagePreference);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '设置更新成功']);
    } else {
        echo json_encode(['success' => false, 'error' => '设置更新失败: ' . $stmt->error]);
    }
    $stmt->close();
}

/**
 * 切换自动激活开关
 */
function toggleAutoActivation($conn, $userId) {
    $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;

    // 检查设置是否存在
    $stmt = $conn->prepare("SELECT id FROM user_settings WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->fetch_assoc();
    $stmt->close();

    if ($exists) {
        // 更新现有设置
        $stmt = $conn->prepare("UPDATE user_settings SET auto_activation_enabled = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param('ii', $enabled, $userId);
    } else {
        // 创建新设置
        $stmt = $conn->prepare("INSERT INTO user_settings (user_id, auto_activation_enabled) VALUES (?, ?)");
        $stmt->bind_param('ii', $userId, $enabled);
    }

    if ($stmt->execute()) {
        $status = $enabled ? '开启' : '关闭';
        echo json_encode(['success' => true, 'message' => "自动激活功能已{$status}"]);
    } else {
        echo json_encode(['success' => false, 'error' => '设置更新失败']);
    }
    $stmt->close();
}
?> 