<?php
/**
 * XFP Activation Key System / XFP 密钥获取系统 — 激活与查询等 API
 *
 * @author    YcFeller
 * @copyright Copyright (c) 2026 YcFeller
 * @license   AGPL-3.0-or-later
 * @link      https://github.com/YcFeller
 */
session_start();

// 引入数据库配置文件
require_once './config.php';
// 引入邮件发送类
require_once 'mail_sender.php';
// 引入安全管理类
require_once 'SecurityManager.php';
// 引入日志管理类
require_once 'LogManager.php';
require_once 'UnlockKeyDerivation.php';

// 记录请求开始时间
$requestStartTime = microtime(true);
$traceId = uniqid('api_', true);

// 连接数据库
$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => '数据库连接失败。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 初始化安全管理器和日志管理器
$securityManager = new SecurityManager($conn);
$logManager = new LogManager($conn);

// 获取客户端真实IP地址
$ip = SecurityManager::getRealIP();

// 记录API访问开始
$logManager->info(LogManager::TYPE_API, 'API访问开始', [
    'endpoint' => '/api.php',
    'method' => $_SERVER['REQUEST_METHOD'],
    'ip' => $ip,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'request_data' => $_POST
], $traceId);

// 检查用户登录状态
$user_role = $_SESSION['user_role'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;
$required_role = 1;

if ($user_role === null) {
    $logManager->warning(LogManager::TYPE_AUTH, '未登录用户尝试访问API', [
        'ip' => $ip,
        'endpoint' => '/api.php'
    ], $traceId);
    echo json_encode(['success' => false, 'error' => '未登录，请先登录。'], JSON_UNESCAPED_UNICODE);
    exit;
} elseif ($user_role < $required_role) {
    $logManager->warning(LogManager::TYPE_AUTH, '权限不足用户尝试访问API', [
        'user_id' => $user_id,
        'user_role' => $user_role,
        'required_role' => $required_role,
        'ip' => $ip
    ], $traceId);
    echo json_encode(['success' => false, 'error' => '权限不足，无法访问该页面。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查IP是否被封禁
if ($securityManager->isIPBlacklisted($ip)) {
    $logManager->warning(LogManager::TYPE_SECURITY, '被封禁IP尝试访问API', [
        'ip' => $ip,
        'user_id' => $user_id
    ], $traceId);
    echo json_encode(['success' => false, 'error' => '访问被拒绝。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查请求频率限制
$rateLimitResult = $securityManager->checkRateLimit($ip, $user_id, 'activation_api');
if (!$rateLimitResult['allowed']) {
    $logManager->warning(LogManager::TYPE_SECURITY, '请求频率超限', [
        'ip' => $ip,
        'user_id' => $user_id,
        'window' => $rateLimitResult['window'],
        'limit' => $rateLimitResult['limit'],
        'current' => $rateLimitResult['current']
    ], $traceId);
    
    $securityManager->recordFailedAttempt(
        $ip, $user_id, 'activation_api', 'rate_limit_exceeded', 
        "请求频率超限: {$rateLimitResult['window']}窗口内{$rateLimitResult['current']}/{$rateLimitResult['limit']}"
    );
    
    echo json_encode([
        'success' => false, 
        'error' => '请求过于频繁，请稍后再试。',
        'retry_after' => $rateLimitResult['reset_time'] - time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 判断请求方法是否为POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $order_no = trim($_POST['order_no'] ?? '');
    $psn = trim($_POST['psn'] ?? '');
    $psw = trim($_POST['psw'] ?? '');
    
    // 记录请求参数（敏感信息脱敏）
    $logManager->info(LogManager::TYPE_API, '激活密码查询请求', [
        'order_no' => substr($order_no, 0, 8) . '***',
        'psn' => substr($psn, 0, 6) . '***',
        'user_id' => $user_id,
        'ip' => $ip
    ], $traceId);
    
    // 输入验证和清理
    if (empty($order_no) || empty($psn)) {
        $logManager->warning(LogManager::TYPE_SECURITY, '参数验证失败：订单号或设备码为空', [
            'order_no_empty' => empty($order_no),
            'psn_empty' => empty($psn),
            'user_id' => $user_id,
            'ip' => $ip
        ], $traceId);
        
        $securityManager->recordFailedAttempt(
            $ip, $user_id, 'activation_api', 'invalid_parameters', 
            '订单号或设备码为空'
        );
        
        echo json_encode(['success' => false, 'error' => '请输入订单号和设备码。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 验证订单号格式（假设订单号应该是特定格式）
    if (!preg_match('/^[A-Za-z0-9_-]{8,50}$/', $order_no)) {
        $logManager->warning(LogManager::TYPE_SECURITY, '订单号格式无效', [
            'order_no' => substr($order_no, 0, 8) . '***',
            'user_id' => $user_id,
            'ip' => $ip
        ], $traceId);
        
        $securityManager->recordFailedAttempt(
            $ip, $user_id, 'activation_api', 'invalid_order_format', 
            '订单号格式无效'
        );
        
        echo json_encode(['success' => false, 'error' => '订单号格式无效。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 验证设备码格式
    if (!preg_match('/^[A-Za-z0-9]{10,50}$/', $psn)) {
        $logManager->warning(LogManager::TYPE_SECURITY, '设备码格式无效', [
            'psn' => substr($psn, 0, 6) . '***',
            'user_id' => $user_id,
            'ip' => $ip
        ], $traceId);
        
        $securityManager->recordFailedAttempt(
            $ip, $user_id, 'activation_api', 'invalid_psn_format', 
            '设备码格式无效'
        );
        
        echo json_encode(['success' => false, 'error' => '设备码格式无效。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (empty($psw)) {
        $logManager->warning(LogManager::TYPE_SECURITY, '验证码为空', [
            'user_id' => $user_id,
            'ip' => $ip
        ], $traceId);
        
        echo json_encode(['success' => false, 'error' => '请输入验证码。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (!isset($_SESSION['captcha'])) {
        $logManager->warning(LogManager::TYPE_SECURITY, '验证码已失效', [
            'user_id' => $user_id,
            'ip' => $ip
        ], $traceId);
        
        echo json_encode(['success' => false, 'error' => '验证码已失效，请刷新验证码。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($psw !== $_SESSION['captcha']) {
        unset($_SESSION['captcha']);
        
        $logManager->warning(LogManager::TYPE_SECURITY, '验证码错误', [
            'user_id' => $user_id,
            'ip' => $ip,
            'provided_captcha' => $psw
        ], $traceId);
        
        $securityManager->recordFailedAttempt(
            $ip, $user_id, 'activation_api', 'invalid_captcha', 
            '验证码错误'
        );
        
        echo json_encode(['success' => false, 'error' => '验证码错误，请重新输入。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    unset($_SESSION['captcha']);

    // 检查用户每日查询限制
    $dailyQueryCount = $securityManager->getRateLimitCount($ip, $user_id, 'activation_api', 'day');
    if ($dailyQueryCount >= 50) { // 每日最多50次查询
        $logManager->warning(LogManager::TYPE_SECURITY, '用户每日查询次数超限', [
            'user_id' => $user_id,
            'ip' => $ip,
            'daily_count' => $dailyQueryCount
        ], $traceId);
        
        echo json_encode(['success' => false, 'error' => '今日查询次数已达上限，请明日再试。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 查询订单信息
    $stmt = $conn->prepare("SELECT downloads_limit, plan_id, system_user_id FROM xfp_order WHERE out_trade_no = ?");
    $stmt->bind_param("s", $order_no);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        
        $logManager->warning(LogManager::TYPE_SECURITY, '查询不存在的订单号', [
            'order_no' => substr($order_no, 0, 8) . '***',
            'user_id' => $user_id,
            'ip' => $ip
        ], $traceId);
        
        $securityManager->recordFailedAttempt(
            $ip, $user_id, 'activation_api', 'order_not_found', 
            '订单号不存在: ' . substr($order_no, 0, 8) . '***'
        );
        
        echo json_encode(['success' => false, 'error' => '订单号不存在。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // 验证订单所有权（确保用户只能查询自己的订单）
    if ($order['system_user_id'] != $user_id) {
        $logManager->warning(LogManager::TYPE_SECURITY, '用户尝试查询他人订单', [
            'order_no' => substr($order_no, 0, 8) . '***',
            'user_id' => $user_id,
            'order_owner_id' => $order['system_user_id'],
            'ip' => $ip
        ], $traceId);
        
        $securityManager->recordFailedAttempt(
            $ip, $user_id, 'activation_api', 'unauthorized_order_access', 
            '尝试访问他人订单: ' . substr($order_no, 0, 8) . '***'
        );
        
        echo json_encode(['success' => false, 'error' => '无权访问此订单。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 检查剩余下载次数
    if ($order['downloads_limit'] <= 0) {
        $logManager->info(LogManager::TYPE_API, '订单下载次数已用完', [
            'order_no' => substr($order_no, 0, 8) . '***',
            'user_id' => $user_id,
            'downloads_limit' => $order['downloads_limit']
        ], $traceId);
        
        echo json_encode(['success' => false, 'error' => '剩余次数为零，无法生成解锁密码。'], JSON_UNESCAPED_UNICODE);
        exit;
    }

  // 扣减下载次数
  $stmt = $conn->prepare("UPDATE xfp_order SET downloads_limit = downloads_limit - 1 WHERE out_trade_no = ?");
  $stmt->bind_param('s', $order_no);
  if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'error' => '更新下载次数失败: ' . $stmt->error], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $stmt->close();

  // 获取激活类型参数
  $activation_type = $_POST['activation_type'] ?? 'watchface'; // 默认为表盘激活
  
  // 根据激活类型查询对应的产品信息
  $unlock_pwds = [];
  try {
  if ($activation_type === 'watchface') {
      // 查询所有表盘名称和ID
      $stmt = $conn->prepare("SELECT watchface_id, name FROM xfp_wflist WHERE plan_id = ?");
      $stmt->bind_param('s', $order['plan_id']);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows === 0) {
          $stmt->close();
          $conn->close();
          echo json_encode(['success' => false, 'error' => '未找到匹配的表盘ID。'], JSON_UNESCAPED_UNICODE);
          exit;
      }
      while ($wf = $result->fetch_assoc()) {
          $wf_id = $wf['watchface_id'];
          $wf_name = $wf['name'];
          $unlock_pwd = UnlockKeyDerivation::derive($psn, $wf_id, 'watchface');
          $unlock_pwds[] = [
              'product_id' => $wf_id,
              'product_name' => $wf_name,
              'unlock_pwd' => $unlock_pwd,
              'product_type' => 'watchface',
              // 为了向后兼容，保留原字段
              'watchface_id' => $wf_id,
              'watchface_name' => $wf_name
          ];
      }
      $stmt->close();
  } elseif ($activation_type === 'quickapp') {
      // 查询所有快应用名称和ID
      $stmt = $conn->prepare("SELECT quickapp_id, name FROM xfp_quickapp_list WHERE plan_id = ?");
      $stmt->bind_param('s', $order['plan_id']);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows === 0) {
          $stmt->close();
          $conn->close();
          echo json_encode(['success' => false, 'error' => '未找到匹配的快应用ID。'], JSON_UNESCAPED_UNICODE);
          exit;
      }
      while ($qa = $result->fetch_assoc()) {
          $qa_id = $qa['quickapp_id'];
          $qa_name = $qa['name'];
          $unlock_pwd = UnlockKeyDerivation::derive($psn, $qa_id, 'quickapp');
          $unlock_pwds[] = [
              'product_id' => $qa_id,
              'product_name' => $qa_name,
              'unlock_pwd' => $unlock_pwd,
              'product_type' => 'quickapp',
              // 为了向后兼容，保留原字段
              'watchface_id' => $qa_id,
              'watchface_name' => $qa_name
          ];
      }
      $stmt->close();
  } else {
      $conn->close();
      echo json_encode(['success' => false, 'error' => '不支持的激活类型。'], JSON_UNESCAPED_UNICODE);
      exit;
  }
  } catch (Throwable $e) {
      $rb = $conn->prepare("UPDATE xfp_order SET downloads_limit = downloads_limit + 1 WHERE out_trade_no = ?");
      $rb->bind_param('s', $order_no);
      $rb->execute();
      $rb->close();
      $conn->close();
      echo json_encode([
          'success' => false,
          'error' => '解锁密码生成失败：' . $e->getMessage(),
      ], JSON_UNESCAPED_UNICODE);
      exit;
  }

  $response = [
    'unlock_pwds' => $unlock_pwds,
    'remaining' => $order['downloads_limit'] - 1
  ];

  // 保存激活记录（每个产品都查重）
  if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    foreach ($unlock_pwds as $pwd) {
      // 检查是否已存在相同的激活记录
      $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM xfp_activation_records WHERE order_number = ? AND product_id = ? AND user_id = ? AND device_code = ? AND unlock_pwd = ? AND product_type = ?");
      $check_stmt->bind_param("ssssss", $order_no, $pwd['product_id'], $user_id, $psn, $pwd['unlock_pwd'], $pwd['product_type']);
      $check_stmt->execute();
      $check_result = $check_stmt->get_result();
      $check_row = $check_result->fetch_assoc();
      $check_stmt->close();
      
      if ($check_row['count'] == 0) {
        // 插入新的激活记录
        $insert_stmt = $conn->prepare("INSERT INTO xfp_activation_records (order_number, product_id, watchface_id, user_id, device_code, unlock_pwd, activation_time, product_type) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
        $insert_stmt->bind_param("sssssss", $order_no, $pwd['product_id'], $pwd['watchface_id'], $user_id, $psn, $pwd['unlock_pwd'], $pwd['product_type']);
        $insert_stmt->execute();
        $insert_stmt->close();
      }
    }
    $response['activation_record_saved'] = true;
  }

  // 邮件发送逻辑（共用数据库连接）
  if (isset($_SESSION['user_id'])) {
    try {
      $user_id = $_SESSION['user_id'];
      $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $user = $result->fetch_assoc();
      $stmt->close();
      if ($user && !empty($user['email'])) {
        $mailSender = new MailSender();
        
        // 根据激活类型设置邮件标题和内容
        $product_type_name = ($activation_type === 'quickapp') ? '快应用' : '表盘';
        $subject = $product_type_name . '订单激活成功通知 - XFP密钥获取系统';
        $body = '<h2>您的' . $product_type_name . '订单已激活，以下为激活信息备份：</h2>';
        $body .= '<ul>';
        $body .= '<li><b>订单号：</b>' . htmlspecialchars($order_no) . '</li>';
        $body .= '<li><b>设备码：</b>' . htmlspecialchars($psn) . '</li>';
        $body .= '<li><b>激活类型：</b>' . $product_type_name . '</li>';
        $body .= '<li><b>剩余下载次数：</b>' . htmlspecialchars($response['remaining']) . '</li>';
        $body .= '</ul>';
        $body .= '<h3>激活' . $product_type_name . '信息：</h3>';
        $body .= '<table border="1" cellpadding="6" style="border-collapse:collapse;">';
        $body .= '<tr><th>' . $product_type_name . '名称</th><th>解锁密码</th></tr>';
        foreach ($unlock_pwds as $item) {
          $body .= '<tr><td>' . htmlspecialchars($item['product_name']) . '</td><td>' . htmlspecialchars($item['unlock_pwd']) . '</td></tr>';
        }
        $body .= '</table>';
        $body .= '<p style="color:#888;font-size:12px;">本邮件为系统自动发送，请妥善保存激活信息。如有疑问请联系平台客服。</p>';
        $mailSender->sendMail($user['email'], $user['username'] ?? '', $subject, $body, true);
      }
    } catch (Exception $e) {
      error_log('[激活邮件发送失败] ' . $e->getMessage());
    }
  }

    // 记录成功的激活操作
    $logManager->info(LogManager::TYPE_API, '激活密码生成成功', [
        'order_no' => substr($order_no, 0, 8) . '***',
        'psn' => substr($psn, 0, 6) . '***',
        'user_id' => $user_id,
        'ip' => $ip,
        'watchface_count' => count($unlock_pwds),
        'remaining_downloads' => $order['downloads_limit'] - 1
    ], $traceId);
    
    $conn->close();
    
    // 计算响应时间
    $responseTime = microtime(true) - $requestStartTime;
    
    // 记录API访问日志
    $logManager->logApiAccess(
        'activation_api', 
        'POST', 
        [
            'order_no' => substr($order_no, 0, 8) . '***',
            'psn' => substr($psn, 0, 6) . '***'
        ],
        200,
        $responseTime,
        true,
        null,
        $traceId
    );
    
    echo json_encode([
        'success' => true,
        'unlock_pwds' => $unlock_pwds,
        'remaining' => $order['downloads_limit'] - 1,
        'activation_record_saved' => isset($response['activation_record_saved']) ? $response['activation_record_saved'] : null
    ], JSON_UNESCAPED_UNICODE);
    exit;
} else {
    // 记录无效请求方法
    $logManager->warning(LogManager::TYPE_SECURITY, '无效的请求方法', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $ip,
        'user_id' => $user_id ?? null
    ], $traceId);
    
    $securityManager->recordFailedAttempt(
        $ip, $user_id ?? null, 'activation_api', 'invalid_method', 
        '使用了无效的请求方法: ' . $_SERVER['REQUEST_METHOD']
    );
    
    echo json_encode(['success' => false, 'error' => '无效的请求方法。'], JSON_UNESCAPED_UNICODE);
}
