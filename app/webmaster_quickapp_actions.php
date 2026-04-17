<?php
/**
 * 站长管理 - 快应用管理API
 * 处理站长对快应用的管理操作
 */

session_start();

// 权限验证
$user_role = $_SESSION['user_role'] ?? null;
if ($user_role === null || $user_role < 3) {
    echo json_encode(['success' => false, 'message' => '权限不足'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once 'config.php';

// 数据库连接
try {
    $conn = new mysqli($servername, $db_user, $db_pass, $dbname);
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 获取快应用列表
 * @param mysqli $conn 数据库连接
 * @param int $page 页码
 * @param string $search 搜索关键词
 * @param string $status 状态筛选
 * @param string $category 分类筛选
 * @return array 快应用列表和分页信息
 */
function getQuickappList($conn, $page = 1, $search = '', $status = '', $category = '') {
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // 构建查询条件
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(q.name LIKE ? OR q.quickapp_id LIKE ? OR q.package_name LIKE ? OR u.username LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= 'ssss';
    }
    
    if ($status !== '') {
        $where_conditions[] = "q.status = ?";
        $params[] = intval($status);
        $types .= 'i';
    }
    
    if (!empty($category)) {
        $where_conditions[] = "q.category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // 获取总数
    $count_sql = "SELECT COUNT(*) as total FROM xfp_quickapp_list q LEFT JOIN users u ON q.system_user_id = u.id {$where_clause}";
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
    
    // 获取数据
    $sql = "SELECT q.*, u.username 
            FROM xfp_quickapp_list q 
            LEFT JOIN users u ON q.system_user_id = u.id 
            {$where_clause} 
            ORDER BY q.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $quickapps = [];
    while ($row = $result->fetch_assoc()) {
        $quickapps[] = $row;
    }
    $stmt->close();
    
    // 分页信息
    $total_pages = ceil($total / $limit);
    $pagination = [
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_items' => $total,
        'items_per_page' => $limit
    ];
    
    return [
        'data' => $quickapps,
        'pagination' => $pagination
    ];
}

/**
 * 获取单个快应用详情
 * @param mysqli $conn 数据库连接
 * @param int $id 快应用ID
 * @return array|null 快应用详情
 */
function getQuickappById($conn, $id) {
    $sql = "SELECT q.*, u.username 
            FROM xfp_quickapp_list q 
            LEFT JOIN users u ON q.system_user_id = u.id 
            WHERE q.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $quickapp = $result->fetch_assoc();
    $stmt->close();
    
    return $quickapp;
}

/**
 * 更新快应用信息
 * @param mysqli $conn 数据库连接
 * @param int $id 快应用ID
 * @param array $data 更新数据
 * @return bool 是否成功
 */
function updateQuickapp($conn, $id, $data) {
    $sql = "UPDATE xfp_quickapp_list SET 
            name = ?, 
            quickapp_id = ?, 
            package_name = ?, 
            version = ?, 
            description = ?, 
            category = ?, 
            status = ?, 
            icon_link = ?, 
            updated_at = NOW() 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssssssi', 
        $data['name'],
        $data['quickapp_id'],
        $data['package_name'],
        $data['version'],
        $data['description'],
        $data['category'],
        $data['status'],
        $data['icon_link'],
        $id
    );
    
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

/**
 * 删除快应用
 * @param mysqli $conn 数据库连接
 * @param int $id 快应用ID
 * @return bool 是否成功
 */
function deleteQuickapp($conn, $id) {
    // 先获取文件路径，用于删除文件
    $sql = "SELECT file_path FROM xfp_quickapp_list WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $quickapp = $result->fetch_assoc();
    $stmt->close();
    
    // 删除数据库记录
    $sql = "DELETE FROM xfp_quickapp_list WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $success = $stmt->execute();
    $stmt->close();
    
    // 删除文件（如果存在）
    if ($success && $quickapp && !empty($quickapp['file_path']) && file_exists($quickapp['file_path'])) {
        unlink($quickapp['file_path']);
    }
    
    return $success;
}

/**
 * 批量删除快应用
 * @param mysqli $conn 数据库连接
 * @param array $ids 快应用ID数组
 * @return bool 是否成功
 */
function batchDeleteQuickapps($conn, $ids) {
    if (empty($ids)) {
        return false;
    }
    
    // 获取所有文件路径
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $sql = "SELECT file_path FROM xfp_quickapp_list WHERE id IN ({$placeholders})";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $file_paths = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['file_path'])) {
            $file_paths[] = $row['file_path'];
        }
    }
    $stmt->close();
    
    // 删除数据库记录
    $sql = "DELETE FROM xfp_quickapp_list WHERE id IN ({$placeholders})";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $success = $stmt->execute();
    $stmt->close();
    
    // 删除文件
    if ($success) {
        foreach ($file_paths as $file_path) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    return $success;
}

/**
 * 记录操作日志
 * @param mysqli $conn 数据库连接
 * @param int $user_id 用户ID
 * @param string $action 操作类型
 * @param string $details 操作详情
 */
function logOperation($conn, $user_id, $action, $details) {
    $sql = "INSERT INTO operation_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('iss', $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}

// 处理请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;
    
    try {
        switch ($action) {
            case 'list':
                $page = intval($_POST['page'] ?? 1);
                $search = trim($_POST['search'] ?? '');
                $status = $_POST['status'] ?? '';
                $category = trim($_POST['category'] ?? '');
                
                $result = getQuickappList($conn, $page, $search, $status, $category);
                echo json_encode(['success' => true] + $result, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'get':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('无效的快应用ID');
                }
                
                $quickapp = getQuickappById($conn, $id);
                if (!$quickapp) {
                    throw new Exception('快应用不存在');
                }
                
                echo json_encode(['success' => true, 'data' => $quickapp], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'update':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('无效的快应用ID');
                }
                
                // 验证数据
                $data = [
                    'name' => trim($_POST['name'] ?? ''),
                    'quickapp_id' => trim($_POST['quickapp_id'] ?? ''),
                    'package_name' => trim($_POST['package_name'] ?? ''),
                    'version' => trim($_POST['version'] ?? '1.0.0'),
                    'description' => trim($_POST['description'] ?? ''),
                    'category' => trim($_POST['category'] ?? 'general'),
                    'status' => intval($_POST['status'] ?? 1),
                    'icon_link' => trim($_POST['icon_link'] ?? '')
                ];
                
                if (empty($data['name'])) {
                    throw new Exception('快应用名称不能为空');
                }
                
                if (empty($data['quickapp_id'])) {
                    throw new Exception('快应用ID不能为空');
                }
                
                // 检查快应用ID是否重复（排除当前记录）
                $check_sql = "SELECT id FROM xfp_quickapp_list WHERE quickapp_id = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param('si', $data['quickapp_id'], $id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    throw new Exception('该快应用ID已存在');
                }
                $check_stmt->close();
                
                if (updateQuickapp($conn, $id, $data)) {
                    logOperation($conn, $user_id, 'quickapp_update', "更新快应用: {$data['name']} (ID: {$id})");
                    echo json_encode(['success' => true, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);
                } else {
                    throw new Exception('更新失败');
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('无效的快应用ID');
                }
                
                // 获取快应用信息用于日志
                $quickapp = getQuickappById($conn, $id);
                if (!$quickapp) {
                    throw new Exception('快应用不存在');
                }
                
                if (deleteQuickapp($conn, $id)) {
                    logOperation($conn, $user_id, 'quickapp_delete', "删除快应用: {$quickapp['name']} (ID: {$id})");
                    echo json_encode(['success' => true, 'message' => '删除成功'], JSON_UNESCAPED_UNICODE);
                } else {
                    throw new Exception('删除失败');
                }
                break;
                
            case 'batch_delete':
                $ids = $_POST['ids'] ?? [];
                if (!is_array($ids) || empty($ids)) {
                    throw new Exception('请选择要删除的快应用');
                }
                
                // 转换为整数数组
                $ids = array_map('intval', $ids);
                $ids = array_filter($ids, function($id) { return $id > 0; });
                
                if (empty($ids)) {
                    throw new Exception('无效的快应用ID');
                }
                
                if (batchDeleteQuickapps($conn, $ids)) {
                    $count = count($ids);
                    logOperation($conn, $user_id, 'quickapp_batch_delete', "批量删除 {$count} 个快应用");
                    echo json_encode(['success' => true, 'message' => "成功删除 {$count} 个快应用"], JSON_UNESCAPED_UNICODE);
                } else {
                    throw new Exception('批量删除失败');
                }
                break;
                
            default:
                throw new Exception('无效的操作');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => '无效的请求方法'], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>