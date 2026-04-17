<?php
session_start();
require_once 'config.php';
require_once 'mail_sender.php';

header('Content-Type: application/json; charset=utf-8');

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
        case 'send_code':
            sendVerificationCode($conn, $userId);
            break;
        case 'verify_and_reset':
            verifyAndResetPassword($conn, $userId);
            break;
        case 'get_user_info':
            getUserPasswordInfo($conn, $userId);
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
 * 发送验证码
 */
function sendVerificationCode($conn, $userId) {
    // 获取用户邮箱
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || empty($user['email'])) {
        echo json_encode(['success' => false, 'error' => '用户邮箱不存在']);
        return;
    }

    // 检查是否在冷却时间内（60秒内只能发送一次）
    $stmt = $conn->prepare("SELECT created_at FROM verification_codes WHERE user_id = ? AND type = 'password_reset' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $lastCode = $result->fetch_assoc();

    if ($lastCode) {
        $lastTime = strtotime($lastCode['created_at']);
        $currentTime = time();
        if ($currentTime - $lastTime < 60) {
            $remainingTime = 60 - ($currentTime - $lastTime);
            echo json_encode(['success' => false, 'error' => "请等待 {$remainingTime} 秒后再试"]);
            return;
        }
    }

    // 生成验证码
    $verificationCode = MailSender::generateVerificationCode(6);
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10分钟后过期

    // 保存验证码到数据库
    $stmt = $conn->prepare("INSERT INTO verification_codes (user_id, code, type, expires_at, created_at) VALUES (?, ?, 'password_reset', ?, NOW())");
    $stmt->bind_param('iss', $userId, $verificationCode, $expiresAt);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => '验证码保存失败']);
        return;
    }

    // 发送邮件
    $mailSender = new MailSender();
    $success = $mailSender->sendVerificationCode($user['email'], $user['username'], $verificationCode, 'password_reset');

    if ($success) {
        echo json_encode(['success' => true, 'message' => '验证码已发送到您的邮箱']);
    } else {
        echo json_encode(['success' => false, 'error' => '邮件发送失败，请稍后重试']);
    }
}

/**
 * 验证验证码并重置密码
 */
function verifyAndResetPassword($conn, $userId) {
    $verificationCode = $_POST['verification_code'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // 验证输入
    if (empty($verificationCode) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'error' => '请填写所有字段']);
        return;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'error' => '两次输入的密码不一致']);
        return;
    }

    // 密码强度验证
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'error' => '密码长度至少8位']);
        return;
    }

    // 检查密码是否包含字母和数字
    if (!preg_match('/[a-zA-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        echo json_encode(['success' => false, 'error' => '密码必须包含字母和数字']);
        return;
    }

    // 验证验证码
    $stmt = $conn->prepare("SELECT id FROM verification_codes WHERE user_id = ? AND code = ? AND type = 'password_reset' AND expires_at > NOW() AND used = 0 ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param('is', $userId, $verificationCode);
    $stmt->execute();
    $result = $stmt->get_result();
    $codeRecord = $result->fetch_assoc();

    if (!$codeRecord) {
        echo json_encode(['success' => false, 'error' => '验证码无效或已过期']);
        return;
    }

    // 检查新密码是否与旧密码相同
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentUser = $result->fetch_assoc();

    if ($currentUser && password_verify($newPassword, $currentUser['password'])) {
        echo json_encode(['success' => false, 'error' => '新密码不能与当前密码相同']);
        return;
    }

    // 更新密码
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $hashedPassword, $userId);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => '密码更新失败']);
        return;
    }

    // 标记验证码为已使用
    $stmt = $conn->prepare("UPDATE verification_codes SET used = 1 WHERE id = ?");
    $stmt->bind_param('i', $codeRecord['id']);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => '密码修改成功']);
}

/**
 * 获取用户密码修改信息
 */
function getUserPasswordInfo($conn, $userId) {
    // 获取用户密码修改信息
    $stmt = $conn->prepare("SELECT updated_at FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && $user['updated_at']) {
        $lastUpdate = strtotime($user['updated_at']);
        $currentTime = time();
        $timeDiff = $currentTime - $lastUpdate;
        
        // 24小时 = 86400秒
        $remainingHours = ceil((86400 - $timeDiff) / 3600);
        $lastUpdateFormatted = date('Y-m-d H:i:s', $lastUpdate);
        $currentTimeFormatted = date('Y-m-d H:i:s', $currentTime);
        $info = [
            'last_update' => $lastUpdateFormatted,
            'current_time' => $currentTimeFormatted,
            'time_diff' => $timeDiff,
            'remaining_hours' => $remainingHours
        ];
        echo json_encode(['success' => true, 'info' => $info]);
    } else {
        echo json_encode(['success' => false, 'error' => '用户密码修改信息不存在']);
    }
}
?> 