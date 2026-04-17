<?php
session_start();

// 防止会话劫持
session_regenerate_id();

// 设置响应头为JSON格式
header('Content-Type: application/json; charset=utf-8');

// 链接数据库和邮件发送类
try {
    include '../../app/config.php';
    include '../../app/mail_sender.php';
} catch (Exception $e) {
    $response = ['success' => false, 'message' => '系统配置错误，请稍后重试'];
    echo json_encode($response);
    exit();
}

// 初始化响应数组
$response = ['success' => false, 'message' => ''];

// 获取操作类型
$action = isset($_POST['action']) ? $_POST['action'] : '';

/**
 * 发送验证码
 */
if ($action === 'send_code') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = '请输入有效的邮箱地址';
        echo json_encode($response);
        exit();
    }
    
    // 检查发送频率限制（60秒内只能发送一次）
    if (isset($_SESSION['last_send_time']) && (time() - $_SESSION['last_send_time']) < 60) {
        $remaining = 60 - (time() - $_SESSION['last_send_time']);
        $response['message'] = "请等待 {$remaining} 秒后再发送验证码";
        echo json_encode($response);
        exit();
    }
    
    // 使用PDO连接数据库
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $response['message'] = "数据库连接失败: " . $e->getMessage();
        echo json_encode($response);
        exit();
    }
    
    // 检查邮箱是否已注册
    $sql = "SELECT id, username FROM users WHERE email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $response['message'] = '该邮箱未注册，请先注册账号';
        echo json_encode($response);
        exit();
    }
    
    // 生成6位数验证码
    $verification_code = sprintf('%06d', mt_rand(0, 999999));
    
    // 将验证码存储到会话中，设置5分钟过期
    $_SESSION['email_verification_code'] = $verification_code;
    $_SESSION['email_verification_email'] = $email;
    $_SESSION['email_verification_time'] = time();
    $_SESSION['email_verification_user_id'] = $user['id'];
    $_SESSION['last_send_time'] = time();
    
    // 发送验证码邮件
    try {
        $mailSender = new MailSender();
        $subject = '登录验证码';
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                <h1 style='color: white; margin: 0;'>登录验证码</h1>
            </div>
            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #ddd;'>
                <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>您好，{$user['username']}！</p>
                <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>您正在使用邮箱验证码登录，您的验证码是：</p>
                <div style='background: white; border: 2px solid #667eea; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px;'>{$verification_code}</span>
                </div>
                <p style='font-size: 14px; color: #666; margin-bottom: 10px;'>验证码有效期为5分钟，请及时使用。</p>
                <p style='font-size: 14px; color: #666; margin-bottom: 20px;'>如果这不是您的操作，请忽略此邮件。</p>
                <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                <p style='font-size: 12px; color: #999; text-align: center;'>此邮件由系统自动发送，请勿回复。</p>
            </div>
        </div>
        ";
        
        $result = $mailSender->sendMail($email, $user['username'], $subject, $body);
        
        if ($result) {
            $response['success'] = true;
            $response['message'] = '验证码已发送到您的邮箱，请查收';
        } else {
            $response['message'] = '验证码发送失败，请稍后重试';
        }
    } catch (Exception $e) {
        $response['message'] = '验证码发送失败：' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

/**
 * 验证码登录
 */
if ($action === 'login') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $verification_code = isset($_POST['verification_code']) ? trim($_POST['verification_code']) : '';
    
    // 验证输入
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = '请输入有效的邮箱地址';
        echo json_encode($response);
        exit();
    }
    
    if (empty($verification_code)) {
        $response['message'] = '请输入验证码';
        echo json_encode($response);
        exit();
    }
    
    // 检查验证码是否存在和有效
    if (!isset($_SESSION['email_verification_code']) || 
        !isset($_SESSION['email_verification_email']) || 
        !isset($_SESSION['email_verification_time']) ||
        !isset($_SESSION['email_verification_user_id'])) {
        $response['message'] = '请先获取验证码';
        echo json_encode($response);
        exit();
    }
    
    // 检查验证码是否过期（5分钟）
    if (time() - $_SESSION['email_verification_time'] > 300) {
        unset($_SESSION['email_verification_code']);
        unset($_SESSION['email_verification_email']);
        unset($_SESSION['email_verification_time']);
        unset($_SESSION['email_verification_user_id']);
        $response['message'] = '验证码已过期，请重新获取';
        echo json_encode($response);
        exit();
    }
    
    // 验证邮箱和验证码
    if ($_SESSION['email_verification_email'] !== $email) {
        $response['message'] = '邮箱地址不匹配';
        echo json_encode($response);
        exit();
    }
    
    if ($_SESSION['email_verification_code'] !== $verification_code) {
        $response['message'] = '验证码错误';
        echo json_encode($response);
        exit();
    }
    
    // 使用PDO连接数据库
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $response['message'] = "数据库连接失败: " . $e->getMessage();
        echo json_encode($response);
        exit();
    }
    
    // 获取用户信息
    $sql = "SELECT * FROM users WHERE id = :user_id AND email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['email_verification_user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $response['message'] = '用户不存在';
        echo json_encode($response);
        exit();
    }
    
    // 清除验证码会话数据
    unset($_SESSION['email_verification_code']);
    unset($_SESSION['email_verification_email']);
    unset($_SESSION['email_verification_time']);
    unset($_SESSION['email_verification_user_id']);
    unset($_SESSION['last_send_time']);
    
    // 设置登录会话变量
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    
    // 登录成功
    $response['success'] = true;
    $response['message'] = '登录成功';
    echo json_encode($response);
    
    // 关闭数据库连接
    $conn = null;
    exit();
}

// 无效的操作
$response['message'] = '无效的操作';
echo json_encode($response);
exit();
?>