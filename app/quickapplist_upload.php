<?php
/**
 * 快应用上传处理API
 * 处理快应用的上传、验证和存储
 */

session_start();

// 权限验证
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 2;
if ($user_role === null) {
    echo '<span class="text-red-400">未登录，请先登录。</span>';
    exit;
} elseif ($user_role < $required_role) {
    echo '<span class="text-red-400">权限不足，无法访问该页面。</span>';
    exit;
}

require_once 'config.php';

$user_id = $_SESSION['user_id'] ?? 0;

// 数据库连接
try {
    $conn = new mysqli($servername, $db_user, $db_pass, $dbname);
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    echo '<span class="text-red-400">数据库连接失败: ' . htmlspecialchars($e->getMessage()) . '</span>';
    exit;
}

/**
 * 验证快应用ID是否已存在
 * @param mysqli $conn 数据库连接
 * @param string $quickapp_id 快应用ID
 * @param int $system_user_id 用户ID
 * @return bool 是否已存在
 */
function isQuickappIdExists($conn, $quickapp_id, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM xfp_quickapp_list WHERE quickapp_id = ? AND user_id = ?");
    $stmt->bind_param("si", $quickapp_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * 验证计划ID是否已被使用
 * @param mysqli $conn 数据库连接
 * @param string $plan_id 计划ID
 * @param int $user_id 用户ID
 * @return bool 是否已被使用
 */
function isPlanIdUsed($conn, $plan_id, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM xfp_quickapp_list WHERE plan_id = ? AND user_id = ?");
    $stmt->bind_param("si", $plan_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $used = $result->num_rows > 0;
    $stmt->close();
    return $used;
}

/**
 * 处理文件上传
 * @param array $file 上传的文件信息
 * @param string $quickapp_id 快应用ID
 * @return array 上传结果
 */
function handleFileUpload($file, $quickapp_id) {
    $upload_dir = '../files/quickapps/';
    
    // 创建目录（如果不存在）
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // 验证文件类型
    $allowed_types = ['application/zip', 'application/x-zip-compressed', 'application/vnd.android.package-archive'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['rpk', 'zip', 'apk'];
    
    if (!in_array($file['type'], $allowed_types) && !in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => '不支持的文件类型，请上传 .rpk, .zip 或 .apk 文件'];
    }
    
    // 验证文件大小（限制为50MB）
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => '文件大小超过限制（最大50MB）'];
    }
    
    // 生成安全的文件名
    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $quickapp_id) . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $safe_filename;
    
    // 移动文件
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'file_path' => $upload_path, 'filename' => $safe_filename];
    } else {
        return ['success' => false, 'message' => '文件上传失败'];
    }
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $name = trim($_POST['name'] ?? '');
    $quickapp_id = trim($_POST['quickapp_id'] ?? '');
    $package_name = trim($_POST['package_name'] ?? '');
    $version = trim($_POST['version'] ?? '1.0.0');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    $status = intval($_POST['status'] ?? 1);
    $plan_id = trim($_POST['plan_id'] ?? '');
    $icon_link = trim($_POST['icon_link'] ?? '');
    
    // 基本验证
    if (empty($name)) {
        echo '<span class="text-red-400">请输入快应用名称</span>';
        exit;
    }
    
    if (empty($quickapp_id)) {
        echo '<span class="text-red-400">请输入快应用ID</span>';
        exit;
    }
    
    if (empty($plan_id)) {
        echo '<span class="text-red-400">请选择计划ID</span>';
        exit;
    }
    
    // 验证快应用ID格式（只允许字母、数字、下划线、点号）
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $quickapp_id)) {
        echo '<span class="text-red-400">快应用ID格式不正确，只允许字母、数字、下划线、点号和短横线</span>';
        exit;
    }
    
    // 验证包名格式（如果提供）
    if (!empty($package_name) && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)*$/', $package_name)) {
        echo '<span class="text-red-400">包名格式不正确，应类似 com.example.app</span>';
        exit;
    }
    
    // 检查快应用ID是否已存在
    if (isQuickappIdExists($conn, $quickapp_id, $user_id)) {
        echo '<span class="text-red-400">该快应用ID已存在，请使用其他ID</span>';
        exit;
    }
    
    // 检查计划ID是否已被使用
    if (isPlanIdUsed($conn, $plan_id, $user_id)) {
        echo '<span class="text-red-400">该计划ID已被使用，请选择其他计划ID</span>';
        exit;
    }
    
    // 处理文件上传（如果有文件）
    $file_path = null;
    $file_size = 0;
    if (isset($_FILES['quickapp_file']) && $_FILES['quickapp_file']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handleFileUpload($_FILES['quickapp_file'], $quickapp_id);
        if (!$upload_result['success']) {
            echo '<span class="text-red-400">' . $upload_result['message'] . '</span>';
            exit;
        }
        $file_path = $upload_result['file_path'];
        $file_size = $_FILES['quickapp_file']['size'];
    }
    
    // 插入数据库
    try {
        $sql = "INSERT INTO xfp_quickapp_list (name, quickapp_id, package_name, version, description, category, status, plan_id, icon_link, file_path, file_size, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('SQL准备失败: ' . $conn->error);
        }
        
        $stmt->bind_param("ssssssisssii", $name, $quickapp_id, $package_name, $version, $description, $category, $status, $plan_id, $icon_link, $file_path, $file_size, $user_id);
        
        if ($stmt->execute()) {
            echo '<span class="text-green-400"><i class="fas fa-check-circle"></i> 快应用上传成功！</span>';
            
            // 记录操作日志（可选）
            $log_sql = "INSERT INTO operation_logs (user_id, action, details, created_at) VALUES (?, 'quickapp_upload', ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            if ($log_stmt) {
                $log_details = "上传快应用: {$name} (ID: {$quickapp_id})";
                $log_stmt->bind_param("is", $system_user_id, $log_details);
                $log_stmt->execute();
                $log_stmt->close();
            }
        } else {
            throw new Exception('数据插入失败: ' . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        // 如果数据库插入失败，删除已上传的文件
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        echo '<span class="text-red-400">上传失败: ' . htmlspecialchars($e->getMessage()) . '</span>';
    }
    
} else {
    echo '<span class="text-red-400">无效的请求方法</span>';
}

$conn->close();
?>