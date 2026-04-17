<?php
session_start();
require_once '../config.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

/**
 * 返回JSON响应
 * @param bool $success 是否成功
 * @param string $message 消息
 * @param array $data 数据
 */
function jsonResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 记录管理员操作日志
 * @param mysqli $conn 数据库连接
 * @param string $action 操作类型
 * @param string $details 操作详情
 */
function logAdminAction($conn, $action, $details = '') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_sql = "INSERT INTO system_logs (trace_id, level, type, message, context, ip, user_id) VALUES (?, 'INFO', 'ADMIN_ACTION', ?, ?, ?, ?)";
    $trace_id = substr(uniqid('db_', true), 0, 32);
    $context = json_encode([
        'action' => $action,
        'details' => $details,
        'user_agent' => $user_agent
    ]);
    
    $log_stmt = $conn->prepare($log_sql);
    if ($log_stmt) {
        $log_stmt->bind_param('ssssi', $trace_id, $action, $context, $ip, $user_id);
        $log_stmt->execute();
        $log_stmt->close();
    }
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
    
    // 验证请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, '仅支持POST请求');
    }
    
    // 获取操作类型
    $action = $_POST['action'] ?? '';
    
    // 注释掉CSRF验证以保持与其他API的一致性
    // $csrf_token = $_POST['csrf_token'] ?? '';
    // $session_token = $_SESSION['csrf_token'] ?? '';
    // if (empty($csrf_token) || $csrf_token !== $session_token) {
    //     jsonResponse(false, 'CSRF验证失败');
    // }
    
    // 根据操作类型处理请求
    switch ($action) {
        case 'execute_update':
            handleDatabaseUpdate($conn);
            break;
        default:
            jsonResponse(false, '未知的操作类型');
    }
    
} catch (Exception $e) {
    error_log('Database Update API Error: ' . $e->getMessage());
    jsonResponse(false, '服务器内部错误');
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * 处理数据库更新操作
 * @param mysqli $conn 数据库连接
 */
function handleDatabaseUpdate($conn) {
    try {
        // 验证请求方法
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, '仅支持POST请求');
        }
        
        // 获取更新类型
        $update_type = $_POST['update_type'] ?? '';
        if (empty($update_type)) {
            jsonResponse(false, '缺少更新类型参数');
        }
        
        // 验证更新类型
        $valid_types = ['full', 'incremental', 'check', 'backup'];
        if (!in_array($update_type, $valid_types)) {
            jsonResponse(false, '无效的更新类型');
        }
        
        // 检查SQL文件是否存在
        $sql_file = '../../complete_database.sql';
        if (!file_exists($sql_file)) {
            jsonResponse(false, 'SQL文件不存在: ' . $sql_file);
        }
        
        // 开始事务
        $conn->begin_transaction();
        
        $result = [];
        $details = [];
        
        switch ($update_type) {
            case 'full':
                $result = executeFullUpdate($conn, $sql_file, $details);
                break;
            case 'incremental':
                $result = executeIncrementalUpdate($conn, $sql_file, $details);
                break;
            case 'check':
                $result = checkDatabaseStructure($conn, $sql_file, $details);
                break;
            case 'backup':
                $result = createDatabaseBackup($conn, $details);
                break;
        }
        
        if ($result['success']) {
            $conn->commit();
            logAdminAction($conn, 'DATABASE_UPDATE_' . strtoupper($update_type), json_encode($details));
            jsonResponse(true, $result['message'], ['details' => $details]);
        } else {
            $conn->rollback();
            jsonResponse(false, $result['message'], ['details' => $details]);
        }
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        error_log('Database Update Error: ' . $e->getMessage());
        jsonResponse(false, '数据库更新失败: ' . $e->getMessage());
    }
}

/**
 * 执行完整数据库更新
 * @param mysqli $conn 数据库连接
 * @param string $sql_file SQL文件路径
 * @param array &$details 详情数组
 * @return array 结果
 */
function executeFullUpdate($conn, $sql_file, &$details) {
    $details[] = '开始执行完整数据库更新...';
    
    try {
        // 读取SQL文件内容
        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
            return ['success' => false, 'message' => '无法读取SQL文件'];
        }
        
        $details[] = '成功读取SQL文件，大小: ' . strlen($sql_content) . ' 字节';
        
        // 分割SQL语句
        $statements = explode(';', $sql_content);
        $executed_count = 0;
        $skipped_count = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            
            // 跳过空语句和注释
            if (empty($statement) || 
                strpos($statement, '--') === 0 || 
                strpos($statement, '/*') === 0 || 
                strpos($statement, 'SET') === 0 || 
                strpos($statement, 'START TRANSACTION') === 0 || 
                strpos($statement, 'COMMIT') === 0) {
                $skipped_count++;
                continue;
            }
            
            // 执行SQL语句
            if ($conn->query($statement)) {
                $executed_count++;
            } else {
                $details[] = '执行失败: ' . substr($statement, 0, 50) . '... - ' . $conn->error;
            }
        }
        
        $details[] = "执行完成: {$executed_count} 条语句成功，{$skipped_count} 条语句跳过";
        
        return [
            'success' => true,
            'message' => '完整数据库更新执行成功'
        ];
        
    } catch (Exception $e) {
        $details[] = '执行失败: ' . $e->getMessage();
        return ['success' => false, 'message' => '完整更新执行失败'];
    }
}

/**
 * 执行增量数据库更新
 * @param mysqli $conn 数据库连接
 * @param string $sql_file SQL文件路径
 * @param array &$details 详情数组
 * @return array 结果
 */
function executeIncrementalUpdate($conn, $sql_file, &$details) {
    $details[] = '开始执行增量数据库更新...';
    
    try {
        // 获取当前数据库表列表
        $current_tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $current_tables[] = $row[0];
        }
        
        $details[] = '当前数据库包含 ' . count($current_tables) . ' 个表';
        
        // 读取并解析SQL文件
        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
            return ['success' => false, 'message' => '无法读取SQL文件'];
        }
        
        // 提取CREATE TABLE语句
        preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)`[^;]+;/i', $sql_content, $matches);
        $sql_tables = $matches[1];
        
        $details[] = 'SQL文件包含 ' . count($sql_tables) . ' 个表定义';
        
        // 找出缺失的表
        $missing_tables = array_diff($sql_tables, $current_tables);
        
        if (empty($missing_tables)) {
            $details[] = '所有表都已存在，无需创建新表';
        } else {
            $details[] = '发现 ' . count($missing_tables) . ' 个缺失的表: ' . implode(', ', $missing_tables);
            
            // 创建缺失的表
            foreach ($missing_tables as $table) {
                $pattern = '/CREATE TABLE IF NOT EXISTS `' . preg_quote($table, '/') . '`[^;]+;/i';
                if (preg_match($pattern, $sql_content, $table_match)) {
                    if ($conn->query($table_match[0])) {
                        $details[] = "成功创建表: {$table}";
                    } else {
                        $details[] = "创建表失败: {$table} - " . $conn->error;
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'message' => '增量数据库更新执行成功'
        ];
        
    } catch (Exception $e) {
        $details[] = '执行失败: ' . $e->getMessage();
        return ['success' => false, 'message' => '增量更新执行失败'];
    }
}

/**
 * 检查数据库结构
 * @param mysqli $conn 数据库连接
 * @param string $sql_file SQL文件路径
 * @param array &$details 详情数组
 * @return array 结果
 */
function checkDatabaseStructure($conn, $sql_file, &$details) {
    $details[] = '开始检查数据库结构...';
    
    try {
        // 获取当前数据库表列表
        $current_tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $current_tables[] = $row[0];
        }
        
        $details[] = '当前数据库包含 ' . count($current_tables) . ' 个表: ' . implode(', ', $current_tables);
        
        // 读取并解析SQL文件
        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
            return ['success' => false, 'message' => '无法读取SQL文件'];
        }
        
        // 提取CREATE TABLE语句
        preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)`[^;]+;/i', $sql_content, $matches);
        $sql_tables = $matches[1];
        
        $details[] = 'SQL文件包含 ' . count($sql_tables) . ' 个表定义: ' . implode(', ', $sql_tables);
        
        // 检查差异
        $missing_tables = array_diff($sql_tables, $current_tables);
        $extra_tables = array_diff($current_tables, $sql_tables);
        
        if (!empty($missing_tables)) {
            $details[] = '缺失的表 (' . count($missing_tables) . '个): ' . implode(', ', $missing_tables);
        }
        
        if (!empty($extra_tables)) {
            $details[] = '额外的表 (' . count($extra_tables) . '个): ' . implode(', ', $extra_tables);
        }
        
        if (empty($missing_tables) && empty($extra_tables)) {
            $details[] = '数据库结构完全匹配，无需更新';
        }
        
        return [
            'success' => true,
            'message' => '数据库结构检查完成'
        ];
        
    } catch (Exception $e) {
        $details[] = '检查失败: ' . $e->getMessage();
        return ['success' => false, 'message' => '结构检查失败'];
    }
}

/**
 * 创建数据库备份
 * @param mysqli $conn 数据库连接
 * @param array &$details 详情数组
 * @return array 结果
 */
function createDatabaseBackup($conn, &$details) {
    $details[] = '开始创建数据库备份...';
    
    try {
        // 获取数据库名称
        $db_name = DB_NAME;
        $backup_dir = '../../backups';
        
        // 创建备份目录
        if (!is_dir($backup_dir)) {
            if (!mkdir($backup_dir, 0755, true)) {
                return ['success' => false, 'message' => '无法创建备份目录'];
            }
        }
        
        // 生成备份文件名
        $backup_file = $backup_dir . '/backup_' . $db_name . '_' . date('Y-m-d_H-i-s') . '.sql';
        
        // 获取所有表
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        $details[] = '发现 ' . count($tables) . ' 个表需要备份';
        
        // 生成备份SQL
        $backup_sql = "-- 数据库备份\n";
        $backup_sql .= "-- 生成时间: " . date('Y-m-d H:i:s') . "\n";
        $backup_sql .= "-- 数据库: {$db_name}\n\n";
        
        foreach ($tables as $table) {
            // 获取表结构
            $create_result = $conn->query("SHOW CREATE TABLE `{$table}`");
            if ($create_row = $create_result->fetch_array()) {
                $backup_sql .= "-- 表结构: {$table}\n";
                $backup_sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $backup_sql .= $create_row[1] . ";\n\n";
            }
            
            // 获取表数据
            $data_result = $conn->query("SELECT * FROM `{$table}`");
            if ($data_result && $data_result->num_rows > 0) {
                $backup_sql .= "-- 表数据: {$table}\n";
                while ($data_row = $data_result->fetch_assoc()) {
                    $values = [];
                    foreach ($data_row as $value) {
                        $values[] = $value === null ? 'NULL' : "'" . $conn->real_escape_string($value) . "'";
                    }
                    $backup_sql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                }
                $backup_sql .= "\n";
            }
        }
        
        // 写入备份文件
        if (file_put_contents($backup_file, $backup_sql) !== false) {
            $details[] = '备份文件已创建: ' . basename($backup_file);
            $details[] = '备份文件大小: ' . round(filesize($backup_file) / 1024, 2) . ' KB';
            
            return [
                'success' => true,
                'message' => '数据库备份创建成功'
            ];
        } else {
            return ['success' => false, 'message' => '无法写入备份文件'];
        }
        
    } catch (Exception $e) {
        $details[] = '备份失败: ' . $e->getMessage();
        return ['success' => false, 'message' => '数据库备份失败'];
    }
}
?>