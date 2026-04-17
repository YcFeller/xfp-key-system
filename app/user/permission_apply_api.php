<?php
/**
 * 用户权限申请API
 * 处理用户提交的权限申请请求
 */

// 禁用错误输出到浏览器，防止破坏JSON格式
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// 设置错误处理函数
set_error_handler(function($severity, $message, $file, $line) {
    $error_log = "[" . date('Y-m-d H:i:s') . "] PHP Error: $message in $file on line $line\n";
    error_log($error_log, 3, __DIR__ . '/../../logs/api_errors.log');
    
    // 如果是致命错误，返回JSON错误响应
    if ($severity === E_ERROR || $severity === E_PARSE || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '系统内部错误，请稍后重试',
            'data' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// 设置异常处理函数
set_exception_handler(function($exception) {
    $error_log = "[" . date('Y-m-d H:i:s') . "] Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
    error_log($error_log, 3, __DIR__ . '/../../logs/api_errors.log');
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => '系统异常，请稍后重试',
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../SecurityManager.php';
require_once __DIR__ . '/../LogManager.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// 记录API调用开始
$api_start_time = microtime(true);
$request_id = uniqid('req_', true);

// 创建日志目录（如果不存在）
$log_dir = __DIR__ . '/../../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// 记录请求日志
$request_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] API Call Started - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "\n";
error_log($request_log, 3, $log_dir . '/api_requests.log');

// 数据库连接
if (!isset($mysqli_conn) || $mysqli_conn === null) {
    $error_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] Database connection failed: mysqli_conn is null\n";
    error_log($error_log, 3, $log_dir . '/api_errors.log');
    jsonResponse(false, '数据库连接失败');
}
if (!isset($conn) || $conn === null) {
    $error_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] PDO connection failed: conn is null\n";
    error_log($error_log, 3, $log_dir . '/api_errors.log');
    jsonResponse(false, 'PDO数据库连接失败');
}

$debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] Database connections established successfully\n";
error_log($debug_log, 3, $log_dir . '/api_debug.log');

// 初始化安全管理器和日志管理器
try {
    $securityManager = new SecurityManager($conn); // 使用PDO连接
    $logManager = new LogManager($mysqli_conn); // 使用mysqli连接
    $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] SecurityManager and LogManager initialized successfully\n";
    error_log($debug_log, 3, $log_dir . '/api_debug.log');
} catch (Exception $e) {
    $error_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] Failed to initialize managers: " . $e->getMessage() . "\n";
    error_log($error_log, 3, $log_dir . '/api_errors.log');
    jsonResponse(false, '系统初始化失败');
}

/**
 * 返回JSON响应
 * @param bool $success 是否成功
 * @param string $message 消息内容
 * @param array $data 附加数据
 */
function jsonResponse($success, $message, $data = []) {
    global $request_id, $api_start_time, $log_dir;
    
    // 计算API执行时间
    $execution_time = microtime(true) - $api_start_time;
    
    // 记录响应日志
    $response_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] API Response - Success: " . ($success ? 'true' : 'false') . ", Message: $message, Execution Time: " . round($execution_time, 4) . "s\n";
    error_log($response_log, 3, $log_dir . '/api_responses.log');
    
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 验证输入数据
 * @param array $data 输入数据
 * @return array 验证结果
 */
function validateInput($data) {
    $errors = [];
    
    // 验证项目描述
    if (empty($data['project_description'])) {
        $errors[] = '项目描述不能为空';
    } elseif (strlen($data['project_description']) < 10) {
        $errors[] = '项目描述至少需要10个字符';
    } elseif (strlen($data['project_description']) > 1000) {
        $errors[] = '项目描述不能超过1000个字符';
    }
    
    // 验证预期使用情况
    if (empty($data['expected_usage'])) {
        $errors[] = '预期使用情况不能为空';
    } elseif (strlen($data['expected_usage']) < 10) {
        $errors[] = '预期使用情况至少需要10个字符';
    } elseif (strlen($data['expected_usage']) > 1000) {
        $errors[] = '预期使用情况不能超过1000个字符';
    }
    
    // 验证公司名称（可选）
    if (!empty($data['company_name']) && strlen($data['company_name']) > 100) {
        $errors[] = '公司名称不能超过100个字符';
    }
    
    // 验证联系电话（可选）
    if (!empty($data['contact_phone'])) {
        if (!preg_match('/^[\d\-\+\(\)\s]{7,20}$/', $data['contact_phone'])) {
            $errors[] = '联系电话格式不正确';
        }
    }
    
    // 验证技术背景（可选）
    if (!empty($data['technical_background']) && strlen($data['technical_background']) > 500) {
        $errors[] = '技术背景不能超过500个字符';
    }
    
    return $errors;
}

try {
    // 记录请求开始处理
    $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] Starting request processing\n";
    error_log($debug_log, 3, $log_dir . '/api_debug.log');
    
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] Invalid request method: " . $_SERVER['REQUEST_METHOD'] . "\n";
        error_log($debug_log, 3, $log_dir . '/api_debug.log');
        jsonResponse(false, '仅支持POST请求');
    }
    
    // 验证用户登录状态
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] User not logged in - Session data: " . json_encode($_SESSION) . "\n";
        error_log($debug_log, 3, $log_dir . '/api_debug.log');
        jsonResponse(false, '用户未登录');
    }
    
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'];
    
    $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] User authenticated - ID: $user_id, Role: $user_role\n";
    error_log($debug_log, 3, $log_dir . '/api_debug.log');
    
    // 验证用户权限
    if ($user_role < 1) {
        jsonResponse(false, '权限不足');
    }
    
    // 安全检查
    $clientIP = SecurityManager::getRealIP();
    if ($securityManager->isIPBlacklisted($clientIP)) {
        $logManager->logSecurity('BLOCKED_REQUEST', "Blocked permission application from blacklisted IP: $clientIP", $user_id);
        jsonResponse(false, '访问被拒绝');
    }
    
    // 检查请求频率限制
    $rateLimitResult = $securityManager->checkRateLimit($clientIP, $user_id, 'permission_apply');
    if (!$rateLimitResult['allowed']) {
        $logManager->logSecurity('RATE_LIMIT', "Permission application rate limit exceeded for IP: $clientIP", $user_id);
        jsonResponse(false, '申请过于频繁，请稍后再试');
    }
    
    // 获取POST数据
    $input_data = [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'project_description' => trim($_POST['project_description'] ?? ''),
        'expected_usage' => trim($_POST['expected_usage'] ?? ''),
        'technical_background' => trim($_POST['technical_background'] ?? '')
    ];
    
    $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] POST data received - Company: " . $input_data['company_name'] . ", Project length: " . strlen($input_data['project_description']) . " chars\n";
    error_log($debug_log, 3, $log_dir . '/api_debug.log');
    
    // 验证输入数据
    $validation_errors = validateInput($input_data);
    if (!empty($validation_errors)) {
        $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] Validation failed: " . implode('; ', $validation_errors) . "\n";
        error_log($debug_log, 3, $log_dir . '/api_debug.log');
        jsonResponse(false, implode('; ', $validation_errors));
    }
    
    $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] Input validation passed\n";
    error_log($debug_log, 3, $log_dir . '/api_debug.log');
    
    // 使用已初始化的数据库连接
    $conn = $mysqli_conn;
    
    $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] Starting database operations\n";
    error_log($debug_log, 3, $log_dir . '/api_debug.log');
    
    // 获取用户信息
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
    if (!$stmt) {
        $error_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] Failed to prepare user query: " . $conn->error . "\n";
        error_log($error_log, 3, $log_dir . '/api_errors.log');
        $logManager->logError('DATABASE_ERROR', 'Failed to prepare user query: ' . $conn->error, $user_id);
        jsonResponse(false, '数据库查询失败');
    }
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] User not found in database - ID: $user_id\n";
        error_log($debug_log, 3, $log_dir . '/api_debug.log');
        jsonResponse(false, '用户信息不存在');
    }
    
    $debug_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] User found - Username: " . $user['username'] . ", Email: " . $user['email'] . "\n";
    error_log($debug_log, 3, $log_dir . '/api_debug.log');
    
    // 检查是否已有待审核或已通过的申请
    $stmt = $conn->prepare("SELECT id, status FROM permission_applications WHERE user_id = ? AND status IN ('pending', 'approved', 'under_review') ORDER BY created_at DESC LIMIT 1");
    if (!$stmt) {
        $logManager->logError('DATABASE_ERROR', 'Failed to prepare application check query: ' . $conn->error, $user_id);
        jsonResponse(false, '数据库查询失败');
    }
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_application = $result->fetch_assoc();
    $stmt->close();
    
    if ($existing_application) {
        $status_map = [
            'pending' => '待审核',
            'under_review' => '审核中',
            'approved' => '已通过'
        ];
        $status_text = $status_map[$existing_application['status']] ?? $existing_application['status'];
        jsonResponse(false, "您已有一个{$status_text}的申请，无需重复提交");
    }
    
    // 插入新的申请记录
    $stmt = $conn->prepare("
        INSERT INTO permission_applications 
        (user_id, username, email, application_type, company_name, project_description, expected_usage, technical_background, contact_phone, status, created_at) 
        VALUES (?, ?, ?, 'developer', ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    if (!$stmt) {
        $logManager->logError('DATABASE_ERROR', 'Failed to prepare application insert query: ' . $conn->error, $user_id);
        jsonResponse(false, '数据库操作失败');
    }
    
    $stmt->bind_param(
        'isssssss',
        $user_id,
        $user['username'],
        $user['email'],
        $input_data['company_name'],
        $input_data['project_description'],
        $input_data['expected_usage'],
        $input_data['technical_background'],
        $input_data['contact_phone']
    );
    
    if ($stmt->execute()) {
        $application_id = $conn->insert_id;
        $stmt->close();
        
        // 记录操作日志
        $logManager->logActivity('PERMISSION_APPLICATION', "User submitted permission application (ID: $application_id)", $user_id);
        
        // 记录安全日志
        $logManager->logSecurity('PERMISSION_APPLICATION', "Permission application submitted by user {$user['username']} (ID: $user_id) from IP: $clientIP", $user_id);
        
        jsonResponse(true, '申请提交成功，请等待管理员审核', [
            'application_id' => $application_id
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $logManager->logError('DATABASE_ERROR', 'Failed to insert permission application: ' . $error, $user_id);
        jsonResponse(false, '申请提交失败，请稍后重试');
    }
    
} catch (Exception $e) {
    $error_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] Exception caught: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    $error_log .= "Stack trace: " . $e->getTraceAsString() . "\n";
    error_log($error_log, 3, $log_dir . '/api_errors.log');
    
    if (isset($logManager)) {
        $logManager->logError('SYSTEM_ERROR', 'Permission application API error: ' . $e->getMessage(), $_SESSION['user_id'] ?? null);
    }
    jsonResponse(false, '系统错误，请稍后重试');
} finally {
    // 记录API调用结束
    $final_log = "[" . date('Y-m-d H:i:s') . "] [REQUEST_ID: $request_id] API call finished\n";
    error_log($final_log, 3, $log_dir . '/api_debug.log');
    
    // 确保数据库连接关闭
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>