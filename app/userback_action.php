<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// 检查用户登录状态
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 2;

if ($user_role === null) {
    echo json_encode(['success' => false, 'error' => '未登录，请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
} elseif ($user_role < $required_role) {
    echo json_encode(['success' => false, 'error' => '权限不足，无法访问该页面'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once './config.php';

try {
    // 获取并验证用户ID
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('用户ID无效');
    }

    // 获取并验证提交的数据
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $afdian_user_id = trim($_POST['afdian_user_id'] ?? '');
    $afdian_token = trim($_POST['afdian_token'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $profile_verification_code = $_POST['profile_verification_code'] ?? '';

    // 数据验证
    if (empty($username)) {
        throw new Exception('用户名不能为空');
    }
    if (empty($email)) {
        throw new Exception('邮箱地址不能为空');
    }
    if ($user_role > 1) {
        if (empty($afdian_user_id)) {
            throw new Exception('爱发电用户ID不能为空');
        }
        if (empty($afdian_token)) {
            throw new Exception('爱发电Token不能为空');
        }
    }
    if (empty($current_password) && empty($profile_verification_code)) {
        throw new Exception('请填写当前密码或邮箱验证码进行验证');
    }

    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('邮箱地址格式不正确');
    }

    // 验证用户名格式
    if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
        throw new Exception('用户名只能包含字母、数字、下划线和中文字符');
    }

    // 验证用户名长度
    if (strlen($username) > 50) {
        throw new Exception('用户名长度不能超过50个字符');
    }

    // 验证邮箱长度
    if (strlen($email) > 100) {
        throw new Exception('邮箱地址长度不能超过100个字符');
    }

    if ($user_role > 1) {
        // 验证爱发电用户ID格式
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $afdian_user_id)) {
            throw new Exception('爱发电用户ID只能包含字母、数字、下划线和连字符');
        }

        // 验证爱发电用户ID长度
        if (strlen($afdian_user_id) > 100) {
            throw new Exception('爱发电用户ID长度不能超过100个字符');
        }

        // 验证Token长度
        if (strlen($afdian_token) > 200) {
            throw new Exception('爱发电Token长度不能超过200个字符');
        }
    }

    // 连接数据库
    $conn = new mysqli($servername, $db_user, $db_pass, $dbname);
    if ($conn->connect_error) {
        throw new Exception('数据库连接失败: ' . $conn->connect_error);
    }

    // 设置字符集
    $conn->set_charset("utf8mb4");

    // 校验密码或验证码
    $verified = false;
    if (!empty($current_password)) {
        // 校验当前密码
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        if ($user && password_verify($current_password, $user['password'])) {
            $verified = true;
        }
    }
    if (!$verified && !empty($profile_verification_code)) {
        // 校验验证码（10分钟内、未用过、类型为 password_reset）
        $stmt = $conn->prepare("SELECT id FROM verification_codes WHERE user_id = ? AND code = ? AND type = 'password_reset' AND expires_at > NOW() AND used = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param('is', $user_id, $profile_verification_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $codeRecord = $result->fetch_assoc();
        $stmt->close();
        if ($codeRecord) {
            $verified = true;
            // 标记验证码为已使用
            $stmt = $conn->prepare("UPDATE verification_codes SET used = 1 WHERE id = ?");
            $stmt->bind_param('i', $codeRecord['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    if (!$verified) {
        throw new Exception('密码或验证码验证失败，请重试');
    }

    // 检查用户是否存在
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $userExists = $result->fetch_array()[0] > 0;
    $stmt->close();

    if (!$userExists) {
        throw new Exception('用户不存在');
    }

    // 检查邮箱是否被其他用户使用
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param('si', $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $emailExists = $result->fetch_array()[0] > 0;
    $stmt->close();

    if ($emailExists) {
        throw new Exception('该邮箱已被其他用户使用');
    }

    // 检查用户名是否被其他用户使用
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param('si', $username, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $usernameExists = $result->fetch_array()[0] > 0;
    $stmt->close();

    if ($usernameExists) {
        throw new Exception('该用户名已被其他用户使用');
    }

    if ($user_role > 1) {
        // 检查爱发电用户ID是否被其他用户使用
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE afdian_user_id = ? AND id != ?");
        $stmt->bind_param('si', $afdian_user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $afdianIdExists = $result->fetch_array()[0] > 0;
        $stmt->close();

        if ($afdianIdExists) {
        throw new Exception('爱发电用户ID已被其他用户使用');
    }
}

// 检查修改间隔时间（需大于6小时）
$stmt = $conn->prepare("SELECT updated_at FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

if ($user_data && $user_data['updated_at']) {
    $last_update = new DateTime($user_data['updated_at']);
    $current_time = new DateTime();
    $interval = $current_time->diff($last_update);
    
    // 计算总小时数
    $hours_diff = ($interval->days * 24) + $interval->h + ($interval->i / 60);
    
    if ($hours_diff < 6) {
        $remaining_hours = 6 - $hours_diff;
        $remaining_minutes = ($remaining_hours - floor($remaining_hours)) * 60;
        throw new Exception(sprintf('修改间隔需大于6小时，还需等待 %.0f 小时 %.0f 分钟', floor($remaining_hours), $remaining_minutes));
    }
}

// 更新用户信息
    if ($user_role > 1) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, afdian_user_id = ?, afdian_token = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ssssi', $username, $email, $afdian_user_id, $afdian_token, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ssi', $username, $email, $user_id);
    }
    
    if ($stmt->execute()) {
        // 记录操作日志
        try {
            $createTableSQL = "
                CREATE TABLE IF NOT EXISTS user_action_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    action VARCHAR(50) NOT NULL,
                    description TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ";
            $conn->query($createTableSQL);

            $logStmt = $conn->prepare("INSERT INTO user_action_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $action = 'update_profile';
            $description = '用户更新了个人信息';
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $logStmt->bind_param('issss', $user_id, $action, $description, $ip_address, $user_agent);
            $logStmt->execute();
            $logStmt->close();
        } catch (Exception $e) {
            // 日志记录失败不影响主流程
            error_log("Failed to log user action: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true, 
            'message' => '个人信息更新成功',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('更新失败，请稍后重试');
    }

    $stmt->close();

} catch (Exception $e) {
    // 记录错误日志
    error_log("User profile update error: " . $e->getMessage() . " User ID: " . ($user_id ?? 'unknown'));
    
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
