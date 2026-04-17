<?php
session_start();
require_once '../config.php';

/**
 * 权限申请审核API
 * 处理后台管理员对权限申请的审核操作
 */

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

/**
 * 返回JSON响应
 * @param bool $success 是否成功
 * @param string $message 消息
 * @param mixed $data 数据
 */
function jsonResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 记录操作日志
 * @param string $action 操作类型
 * @param string $details 操作详情
 */
function logAdminAction($action, $details) {
    $log_file = '../../logs/admin_actions.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $user_id = $_SESSION['user_id'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_entry = "[{$timestamp}] Admin Action - User ID: {$user_id}, IP: {$ip}, Action: {$action}, Details: {$details}, User-Agent: {$user_agent}" . PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

try {
    // 验证管理员权限
    $user_role = $_SESSION['user_role'] ?? null;
    if ($user_role === null || $user_role < 3) {
        jsonResponse(false, '权限不足，需要管理员权限');
    }
    
    // 验证数据库连接
    if (!isset($mysqli_conn) || $mysqli_conn === null) {
        jsonResponse(false, '数据库连接失败');
    }
    $conn = $mysqli_conn;
    
    // 获取请求方法和操作类型
    $request_method = $_SERVER['REQUEST_METHOD'];
    $action = $_REQUEST['action'] ?? '';
    
    // 安全检查：防止CSRF攻击
    if ($request_method === 'POST') {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!str_contains($referer, $host)) {
            logAdminAction('SECURITY_VIOLATION', 'Invalid referer in POST request');
            jsonResponse(false, '安全验证失败');
        }
    }
    
    switch ($action) {
        case 'get_application':
            handleGetApplication($conn);
            break;
            
        case 'review_application':
            handleReviewApplication($conn);
            break;
            
        case 'delete_application':
            handleDeleteApplication($conn);
            break;
            
        default:
            jsonResponse(false, '无效的操作类型');
    }
    
} catch (Exception $e) {
    error_log('Permission Review API Error: ' . $e->getMessage());
    jsonResponse(false, '服务器内部错误，请稍后重试');
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * 获取申请详情
 * @param mysqli $conn 数据库连接
 */
function handleGetApplication($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, '请求方法错误');
    }
    
    $application_id = intval($_GET['id'] ?? 0);
    if ($application_id <= 0) {
        jsonResponse(false, '无效的申请ID');
    }
    
    // 查询申请详情
    $sql = "SELECT * FROM permission_applications WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        jsonResponse(false, '数据库查询准备失败');
    }
    
    $stmt->bind_param('i', $application_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        jsonResponse(false, '申请记录不存在');
    }
    
    $application = $result->fetch_assoc();
    $stmt->close();
    
    // 格式化时间
    $application['created_at'] = date('Y-m-d H:i:s', strtotime($application['created_at']));
    $application['updated_at'] = date('Y-m-d H:i:s', strtotime($application['updated_at']));
    if ($application['reviewed_at']) {
        $application['reviewed_at'] = date('Y-m-d H:i:s', strtotime($application['reviewed_at']));
    }
    
    logAdminAction('VIEW_APPLICATION', "Viewed application ID: {$application_id}");
    jsonResponse(true, '获取申请详情成功', $application);
}

/**
 * 审核申请
 * @param mysqli $conn 数据库连接
 */
function handleReviewApplication($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, '请求方法错误');
    }
    
    // 获取并验证参数
    $application_id = intval($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $admin_comment = trim($_POST['comment'] ?? '');
    
    if ($application_id <= 0) {
        jsonResponse(false, '无效的申请ID');
    }
    
    if (!in_array($status, ['approved', 'rejected'])) {
        jsonResponse(false, '无效的审核状态');
    }
    
    // 验证审核意见长度
    if (strlen($admin_comment) > 1000) {
        jsonResponse(false, '审核意见不能超过1000个字符');
    }
    
    // 开始事务
    $conn->autocommit(false);
    
    try {
        // 检查申请是否存在且可以审核
        $check_sql = "SELECT id, user_id, username, email, status FROM permission_applications WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception('数据库查询准备失败');
        }
        
        $check_stmt->bind_param('i', $application_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $check_stmt->close();
            throw new Exception('申请记录不存在');
        }
        
        $application = $check_result->fetch_assoc();
        $check_stmt->close();
        
        // 检查申请状态是否可以审核
        if (!in_array($application['status'], ['pending', 'under_review'])) {
            throw new Exception('该申请已经被审核过，无法重复审核');
        }
        
        // 更新申请状态
        $update_sql = "UPDATE permission_applications SET 
                       status = ?, 
                       admin_comment = ?, 
                       reviewed_at = NOW(), 
                       reviewed_by = ?, 
                       updated_at = NOW() 
                       WHERE id = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        if (!$update_stmt) {
            throw new Exception('数据库更新准备失败');
        }
        
        $admin_id = $_SESSION['user_id'];
        $update_stmt->bind_param('ssii', $status, $admin_comment, $admin_id, $application_id);
        
        if (!$update_stmt->execute()) {
            $update_stmt->close();
            throw new Exception('更新申请状态失败');
        }
        
        $update_stmt->close();
        
        // 如果审核通过，更新用户权限
        if ($status === 'approved') {
            $user_update_sql = "UPDATE users SET role = 2, updated_at = NOW() WHERE id = ? AND role < 2";
            $user_update_stmt = $conn->prepare($user_update_sql);
            if (!$user_update_stmt) {
                throw new Exception('用户权限更新准备失败');
            }
            
            $user_update_stmt->bind_param('i', $application['user_id']);
            $user_update_stmt->execute();
            $user_update_stmt->close();
        }
        
        // 提交事务
        $conn->commit();
        
        // 记录操作日志
        $status_text = $status === 'approved' ? '通过' : '拒绝';
        logAdminAction('REVIEW_APPLICATION', "Application ID: {$application_id}, Status: {$status_text}, User: {$application['username']} ({$application['email']})");
        
        $message = $status === 'approved' ? '申请已通过审核，用户权限已更新' : '申请已被拒绝';
        jsonResponse(true, $message);
        
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        error_log('Review Application Error: ' . $e->getMessage());
        jsonResponse(false, $e->getMessage());
    } finally {
        // 恢复自动提交
        $conn->autocommit(true);
    }
}

/**
 * 删除申请记录
 * @param mysqli $conn 数据库连接
 */
function handleDeleteApplication($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, '请求方法错误');
    }
    
    // 获取并验证参数
    $application_id = intval($_POST['id'] ?? 0);
    
    if ($application_id <= 0) {
        jsonResponse(false, '无效的申请ID');
    }
    
    // 开始事务
    $conn->autocommit(false);
    
    try {
        // 检查申请是否存在
        $check_sql = "SELECT id, user_id, username, email, status FROM permission_applications WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception('数据库查询准备失败');
        }
        
        $check_stmt->bind_param('i', $application_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $check_stmt->close();
            throw new Exception('申请记录不存在');
        }
        
        $application = $check_result->fetch_assoc();
        $check_stmt->close();
        
        // 删除申请记录
        $delete_sql = "DELETE FROM permission_applications WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        if (!$delete_stmt) {
            throw new Exception('数据库删除准备失败');
        }
        
        $delete_stmt->bind_param('i', $application_id);
        
        if (!$delete_stmt->execute()) {
            $delete_stmt->close();
            throw new Exception('删除申请记录失败');
        }
        
        $delete_stmt->close();
        
        // 提交事务
        $conn->commit();
        
        // 记录管理员操作日志
        $admin_id = $_SESSION['user_id'];
        $log_details = "Deleted application ID: {$application_id}, User: {$application['username']} (ID: {$application['user_id']}), Status: {$application['status']}";
        logAdminAction('DELETE_APPLICATION', $log_details);
        
        jsonResponse(true, '申请记录删除成功');
        
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        error_log('Delete Application Error: ' . $e->getMessage());
        jsonResponse(false, $e->getMessage());
    } finally {
        // 恢复自动提交
        $conn->autocommit(true);
    }
}
?>