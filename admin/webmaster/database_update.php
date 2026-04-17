<?php
session_start();
require_once '../../app/config.php';

// 验证管理员权限
$user_role = $_SESSION['user_role'] ?? null;
if ($user_role === null || $user_role < 3) {
    header("Location: ../../index.php");
    exit;
}

// 生成CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 使用config.php中的mysqli连接
if (!isset($mysqli_conn) || $mysqli_conn === null) {
    die('数据库连接失败。');
}
$conn = $mysqli_conn;

// 检查数据库表结构
function checkDatabaseStructure($pdo) {
    // 从complete_database.sql文件中解析所需的表
    $sql_file_path = '../../database_files/complete_database.sql';
    $required_tables = [];
    
    if (file_exists($sql_file_path)) {
        $sql_content = file_get_contents($sql_file_path);
        // 使用正则表达式提取CREATE TABLE语句中的表名
        preg_match_all('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $sql_content, $matches);
        $required_tables = $matches[1];
    } else {
        // 如果SQL文件不存在，使用默认的表列表
        $required_tables = [
            'users', 'xfp_order', 'xfp_wflist', 'xfp_activation_records',
            'permission_applications', 'verification_codes', 'user_action_logs',
            'user_settings', 'api_rate_limits', 'ip_blacklist', 'failed_attempts',
            'system_logs', 'api_access_logs'
        ];
    }
    
    $missing_tables = [];
    $existing_tables = [];
    
    foreach ($required_tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            $existing_tables[] = $table;
        } else {
            $missing_tables[] = $table;
        }
    }
    
    return [
        'required' => $required_tables,
        'existing' => $existing_tables,
        'missing' => $missing_tables,
        'complete' => empty($missing_tables),
        'sql_file_found' => file_exists($sql_file_path)
    ];
}

// 获取当前数据库表信息
$tables_sql = "SHOW TABLES";
$tables_result = $conn->query($tables_sql);
$current_tables = [];
while ($row = $tables_result->fetch_array()) {
    $current_tables[] = $row[0];
}

// 获取数据库状态信息
$sql_file_path = '../../database_files/complete_database.sql';

// 创建PDO连接用于检查数据库结构
try {
    $pdo = new PDO("mysql:host={$servername};dbname={$dbname};charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $structure_check = checkDatabaseStructure($pdo);
} catch (PDOException $e) {
    $structure_check = [
        'required' => [],
        'existing' => $current_tables,
        'missing' => [],
        'complete' => false,
        'sql_file_found' => file_exists($sql_file_path)
    ];
}

$db_status = [
    'total_tables' => count($current_tables),
    'required_tables' => count($structure_check['required']),
    'missing_tables' => count($structure_check['missing']),
    'last_update' => date('Y-m-d H:i:s'),
    'sql_file_exists' => file_exists($sql_file_path),
    'sql_file_size' => file_exists($sql_file_path) ? filesize($sql_file_path) : 0,
    'structure_complete' => $structure_check['complete']
];

// 处理数据库更新请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // 验证CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'CSRF token验证失败']);
        exit;
    }
    
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'full_update':
                // 执行完整数据库更新
                $sql_file = '../../database_files/complete_database.sql';
                if (file_exists($sql_file)) {
                    $sql_content = file_get_contents($sql_file);
                    
                    // 分割SQL语句并逐个执行
                    $statements = explode(';', $sql_content);
                    $executed = 0;
                    $errors = [];
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                            try {
                                $conn->query($statement);
                                $executed++;
                            } catch (Exception $e) {
                                $errors[] = $e->getMessage();
                            }
                        }
                    }
                    
                    if (empty($errors)) {
                        $response = ['success' => true, 'message' => "数据库完整更新成功，执行了 {$executed} 条SQL语句"];
                    } else {
                        $response = ['success' => false, 'message' => "部分更新失败，执行了 {$executed} 条语句，错误: " . implode('; ', array_slice($errors, 0, 3))];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'SQL文件不存在: database_files/complete_database.sql'];
                }
                break;
                
            case 'incremental_update':
                // 执行增量更新（仅创建缺失的表）
                $sql_file = '../../database_files/complete_database.sql';
                if (file_exists($sql_file)) {
                    $pdo = new PDO("mysql:host={$servername};dbname={$dbname};charset=utf8mb4", $db_user, $db_pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    $structure_check = checkDatabaseStructure($pdo);
                    
                    if (empty($structure_check['missing'])) {
                        $response = ['success' => true, 'message' => '数据库结构已完整，无需更新'];
                    } else {
                        $sql_content = file_get_contents($sql_file);
                        $created_tables = [];
                        $errors = [];
                        
                        foreach ($structure_check['missing'] as $missing_table) {
                            // 提取特定表的CREATE TABLE语句
                            $pattern = '/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?' . preg_quote($missing_table, '/') . '`?[^;]*;/is';
                            if (preg_match($pattern, $sql_content, $matches)) {
                                try {
                                    $conn->query($matches[0]);
                                    $created_tables[] = $missing_table;
                                } catch (Exception $e) {
                                    $errors[] = "创建表 {$missing_table} 失败: " . $e->getMessage();
                                }
                            }
                        }
                        
                        if (empty($errors)) {
                            $response = ['success' => true, 'message' => '增量更新成功，创建了 ' . count($created_tables) . ' 个数据表: ' . implode(', ', $created_tables)];
                        } else {
                            $response = ['success' => false, 'message' => '增量更新部分失败: ' . implode('; ', $errors)];
                        }
                    }
                } else {
                    $response = ['success' => false, 'message' => 'SQL文件不存在: database_files/complete_database.sql'];
                }
                break;
                
            case 'backup_update':
                // 备份当前数据库
                $backup_dir = '../../database_files/backups/';
                if (!is_dir($backup_dir)) {
                    mkdir($backup_dir, 0755, true);
                }
                
                $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $command = "mysqldump -h{$servername} -u{$db_user} -p{$db_pass} {$dbname} > {$backup_file}";
                
                exec($command, $output, $return_code);
                
                if ($return_code === 0 && file_exists($backup_file)) {
                    $response = ['success' => true, 'message' => '数据库备份成功: ' . basename($backup_file)];
                } else {
                    $response = ['success' => false, 'message' => '数据库备份失败'];
                }
                break;
                
            case 'check_update':
                // 检查数据库结构差异
                $pdo = new PDO("mysql:host={$servername};dbname={$dbname};charset=utf8mb4", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $structure_check = checkDatabaseStructure($pdo);
                
                $details = [];
                $details[] = '当前数据表: ' . count($structure_check['existing']) . ' 个';
                $details[] = '标准数据表: ' . count($structure_check['required']) . ' 个';
                $details[] = '缺失数据表: ' . count($structure_check['missing']) . ' 个';
                
                if (!empty($structure_check['missing'])) {
                    $details[] = '缺失的表: ' . implode(', ', $structure_check['missing']);
                }
                
                $response = [
                    'success' => true, 
                    'message' => '数据库结构检查完成',
                    'details' => $details
                ];
                break;
                
            default:
                $response = ['success' => false, 'message' => '未知操作'];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => '操作失败: ' . $e->getMessage()];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库更新管理 - 站长管理</title>
    <link rel="stylesheet" href="../../files/css/admin/wm_index.css">
    <script src="../../files/js/jquery-3.6.0.min.js"></script>
    <style>
        /* 扩展样式 */
        .stats-container {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            min-width: 120px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .update-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 20px 0;
        }
        
        .update-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .option-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .option-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.1);
        }
        
        .option-icon {
            font-size: 48px;
            margin-bottom: 15px;
            color: #007bff;
        }
        
        .option-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .option-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #1e7e34;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .tables-list {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 20px 0;
        }
        
        .table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .table-item {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #007bff;
            font-family: monospace;
        }
        
        .progress-container {
            display: none;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 20px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background-color: #007bff;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .log-container {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .alert {
            padding: 12px 20px;
            margin: 15px 0;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        
        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>数据库更新管理</h1>
            <div class="nav-links">
                <a href="index.php">返回首页</a>
                <a href="permission_review.php">权限审核</a>
                <a href="../../logout.php">退出登录</a>
            </div>
        </div>
        
        <!-- 数据库状态统计 -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_status['total_tables']; ?></div>
                <div class="stat-label">当前数据表</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_status['required_tables']; ?></div>
                <div class="stat-label">标准表数量</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: <?php echo $db_status['missing_tables'] > 0 ? '#dc3545' : '#28a745'; ?>">
                    <?php echo $db_status['missing_tables']; ?>
                </div>
                <div class="stat-label">缺失表数量</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_status['sql_file_exists'] ? '✓' : '✗'; ?></div>
                <div class="stat-label">SQL文件状态</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo round($db_status['sql_file_size'] / 1024, 1); ?>KB</div>
                <div class="stat-label">SQL文件大小</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_status['last_update']; ?></div>
                <div class="stat-label">最后检查时间</div>
            </div>
        </div>
        
        <?php if (!$db_status['structure_complete']): ?>
        <div class="alert alert-warning">
            <strong>注意：</strong> 检测到数据库结构不完整，缺失 <?php echo $db_status['missing_tables']; ?> 个数据表。建议执行增量更新来补全缺失的表结构。
        </div>
        <?php endif; ?>
        
        <!-- 更新操作选项 -->
        <div class="update-section">
            <h2>数据库更新操作</h2>
            <div class="alert alert-info">
                <strong>注意：</strong> 数据库更新操作可能会影响系统正常运行，请在执行前确保已做好数据备份。
                <br><strong>SQL文件位置：</strong> database_files/complete_database.sql
            </div>
            
            <div class="update-options">
                <div class="option-card">
                    <div class="option-icon">🔄</div>
                    <div class="option-title">完整更新</div>
                    <div class="option-description">
                        执行完整的数据库结构更新，包括所有表的创建和修改。适用于新功能部署或重大更新。
                    </div>
                    <button class="btn btn-primary" onclick="executeUpdate('full')">
                        执行完整更新
                    </button>
                </div>
                
                <div class="option-card">
                    <div class="option-icon">⚡</div>
                    <div class="option-title">增量更新</div>
                    <div class="option-description">
                        仅更新缺失的表和字段，保持现有数据不变。适用于日常功能更新和修复。
                    </div>
                    <button class="btn btn-success" onclick="executeUpdate('incremental')">
                        执行增量更新
                    </button>
                </div>
                
                <div class="option-card">
                    <div class="option-icon">🔍</div>
                    <div class="option-title">结构检查</div>
                    <div class="option-description">
                        检查当前数据库结构与标准结构的差异，不执行任何修改操作。
                    </div>
                    <button class="btn btn-warning" onclick="executeUpdate('check')">
                        检查结构差异
                    </button>
                </div>
                
                <div class="option-card">
                    <div class="option-icon">💾</div>
                    <div class="option-title">备份数据库</div>
                    <div class="option-description">
                        创建当前数据库的完整备份，建议在执行更新操作前先进行备份。
                    </div>
                    <button class="btn btn-warning" onclick="executeUpdate('backup')">
                        创建备份
                    </button>
                </div>
            </div>
        </div>
        
        <!-- 进度显示 -->
        <div class="progress-container" id="progressContainer">
            <h3>更新进度</h3>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div class="log-container" id="logContainer"></div>
        </div>
        
        <!-- 当前数据表列表 -->
        <div class="tables-list">
            <h2>数据表状态对比</h2>
            
            <h3>当前数据表 (<?php echo count($current_tables); ?>个)</h3>
            <div class="table-grid">
                <?php foreach ($current_tables as $table): ?>
                    <div class="table-item"><?php echo htmlspecialchars($table); ?></div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($structure_check['missing'])): ?>
            <h3 style="color: #dc3545; margin-top: 30px;">缺失的数据表 (<?php echo count($structure_check['missing']); ?>个)</h3>
            <div class="table-grid">
                <?php foreach ($structure_check['missing'] as $table): ?>
                    <div class="table-item" style="border-left-color: #dc3545; background-color: #f8d7da;">
                        <?php echo htmlspecialchars($table); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($structure_check['required'])): ?>
            <h3 style="color: #28a745; margin-top: 30px;">标准数据表 (<?php echo count($structure_check['required']); ?>个)</h3>
            <div class="table-grid">
                <?php foreach ($structure_check['required'] as $table): ?>
                    <div class="table-item" style="border-left-color: <?php echo in_array($table, $structure_check['existing']) ? '#28a745' : '#dc3545'; ?>; background-color: <?php echo in_array($table, $structure_check['existing']) ? '#d4edda' : '#f8d7da'; ?>;">
                        <?php echo htmlspecialchars($table); ?>
                        <?php if (in_array($table, $structure_check['existing'])): ?>
                            <span style="color: #28a745; float: right;">✓</span>
                        <?php else: ?>
                            <span style="color: #dc3545; float: right;">✗</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        /**
         * 执行数据库更新操作
         * @param {string} type - 更新类型：full, incremental, check, backup
         */
        function executeUpdate(type) {
            if (!confirm('确定要执行此操作吗？请确保已做好数据备份。')) {
                return;
            }
            
            // 显示进度容器
            const progressContainer = document.getElementById('progressContainer');
            const progressFill = document.getElementById('progressFill');
            const logContainer = document.getElementById('logContainer');
            
            progressContainer.style.display = 'block';
            progressFill.style.width = '0%';
            logContainer.innerHTML = '';
            
            // 添加初始日志
            addLog('开始执行数据库更新操作...');
            addLog('更新类型: ' + getUpdateTypeName(type));
            
            // 发送AJAX请求
            $.ajax({
                url: 'database_update.php',
                method: 'POST',
                data: {
                    action: type + '_update',
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        progressFill.style.width = '100%';
                        addLog('操作完成！');
                        if (response.message) {
                            addLog(response.message);
                        }
                        if (response.details) {
                            response.details.forEach(detail => addLog(detail));
                        }
                        
                        // 如果是更新操作，3秒后刷新页面
                        if (type !== 'check') {
                            setTimeout(() => {
                                location.reload();
                            }, 3000);
                        }
                    } else {
                        addLog('操作失败: ' + (response.message || '未知错误'));
                        if (response.details) {
                            response.details.forEach(detail => addLog(detail));
                        }
                    }
                },
                error: function(xhr, status, error) {
                    addLog('请求失败: ' + error);
                },
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    // 监听进度
                    xhr.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            progressFill.style.width = percentComplete + '%';
                        }
                    }, false);
                    return xhr;
                }
            });
        }
        
        /**
         * 添加日志信息
         * @param {string} message - 日志消息
         */
        function addLog(message) {
            const logContainer = document.getElementById('logContainer');
            const timestamp = new Date().toLocaleTimeString();
            logContainer.innerHTML += `[${timestamp}] ${message}\n`;
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        /**
         * 获取更新类型的中文名称
         * @param {string} type - 更新类型
         * @returns {string} 中文名称
         */
        function getUpdateTypeName(type) {
            const names = {
                'full': '完整更新',
                'incremental': '增量更新',
                'check': '结构检查',
                'backup': '数据库备份'
            };
            return names[type] || type;
        }
    </script>
</body>
</html>