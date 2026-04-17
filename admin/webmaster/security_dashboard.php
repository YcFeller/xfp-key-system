<?php
/**
 * 安全管理仪表板 - Webmaster版本
 * 提供安全日志查看、IP管理、系统监控等功能
 * 
 * @author Security System
 * @version 1.0
 * @created 2024
 */

session_start();
require_once '../../app/config.php';
require_once '../../app/SecurityManager.php';
require_once '../../app/LogManager.php';
require_once '../../app/SecurityConfig.php';

// 检查webmaster权限
$user_role = $_SESSION['user_role'] ?? null;
if ($user_role === null || $user_role < 3) {
  header("Location: ../../index.php");
  exit;
}

$securityManager = new SecurityManager($conn);
$logManager = new LogManager($conn);

// 处理AJAX请求
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_stats':
            echo json_encode(getSecurityStats($conn));
            break;
            
        case 'get_logs':
            $page = (int)($_GET['page'] ?? 1);
            $type = $_GET['type'] ?? 'all';
            echo json_encode(getSecurityLogs($conn, $page, $type));
            break;
            
        case 'get_blacklist':
            echo json_encode(getBlacklist($conn));
            break;
            
        case 'ban_ip':
            // 只有admin可以封禁IP
            if ($_SESSION['user_role'] !== 'admin') {
                echo json_encode(['success' => false, 'message' => '权限不足']);
                break;
            }
            $ip = $_POST['ip'] ?? '';
            $reason = $_POST['reason'] ?? '手动封禁';
            $duration = (int)($_POST['duration'] ?? 3600);
            echo json_encode(banIp($securityManager, $ip, $reason, $duration));
            break;
            
        case 'unban_ip':
            // 只有admin可以解封IP
            if ($_SESSION['user_role'] !== 'admin') {
                echo json_encode(['success' => false, 'message' => '权限不足']);
                break;
            }
            $ip = $_POST['ip'] ?? '';
            echo json_encode(unbanIp($securityManager, $ip));
            break;
            
        case 'cleanup_logs':
            // 只有admin可以清理日志
            if ($_SESSION['user_role'] !== 'admin') {
                echo json_encode(['success' => false, 'message' => '权限不足']);
                break;
            }
            $days = (int)($_POST['days'] ?? 30);
            echo json_encode(cleanupLogs($securityManager, $logManager, $days));
            break;
    }
    exit;
}

/**
 * 获取安全统计数据
 */
function getSecurityStats($conn) {
    $stats = [];
    
    // 今日API调用次数
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM api_access_logs WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $stats['today_api_calls'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // 今日失败次数
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM failed_attempts WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $stats['today_failures'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // 当前黑名单IP数量
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ip_blacklist WHERE banned_until IS NULL OR banned_until > NOW()");
    $stmt->execute();
    $stats['blacklisted_ips'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // 最近1小时请求数
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM api_rate_limits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();
    $stats['hourly_requests'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    return $stats;
}

/**
 * 获取安全日志
 */
function getSecurityLogs($conn, $page = 1, $type = 'all') {
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    $whereClause = "";
    if ($type !== 'all') {
        $whereClause = "WHERE type = '" . $conn->real_escape_string($type) . "'";
    }
    
    $stmt = $conn->prepare("SELECT * FROM system_logs {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
    
    return $logs;
}

/**
 * 获取IP黑名单
 */
function getBlacklist($conn) {
    $stmt = $conn->prepare("SELECT * FROM ip_blacklist ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $blacklist = [];
    while ($row = $result->fetch_assoc()) {
        $blacklist[] = $row;
    }
    $stmt->close();
    
    return $blacklist;
}

/**
 * 封禁IP
 */
function banIp($securityManager, $ip, $reason, $duration) {
    try {
        $bannedUntil = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration) : null;
        $securityManager->banIp($ip, $reason, $bannedUntil);
        return ['success' => true, 'message' => 'IP封禁成功'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => '封禁失败: ' . $e->getMessage()];
    }
}

/**
 * 解封IP
 */
function unbanIp($securityManager, $ip) {
    try {
        $securityManager->unbanIp($ip);
        return ['success' => true, 'message' => 'IP解封成功'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => '解封失败: ' . $e->getMessage()];
    }
}

/**
 * 清理日志
 */
function cleanupLogs($securityManager, $logManager, $days) {
    try {
        $securityManager->cleanupExpiredData($days);
        $logManager->cleanupOldLogs($days);
        return ['success' => true, 'message' => "成功清理{$days}天前的日志"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => '清理失败: ' . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安全管理仪表板 - Webmaster</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .role-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        
        .tabs {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tab-buttons {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tab-button {
            flex: 1;
            padding: 1rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            background: white;
            color: #667eea;
            font-weight: 600;
        }
        
        .tab-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .tab-content {
            padding: 1.5rem;
            min-height: 400px;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .log-entry {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }
        
        .log-entry:hover {
            background-color: #f8f9fa;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .log-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .log-level {
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .log-level.info { background: #d1ecf1; color: #0c5460; }
        .log-level.warning { background: #fff3cd; color: #856404; }
        .log-level.error { background: #f8d7da; color: #721c24; }
        
        .log-time {
            color: #666;
            font-size: 0.9rem;
        }
        
        .log-message {
            margin-bottom: 0.5rem;
        }
        
        .log-context {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.8rem;
            color: #666;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .blacklist-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .blacklist-info {
            flex: 1;
        }
        
        .blacklist-ip {
            font-weight: 600;
            color: #333;
        }
        
        .blacklist-reason {
            color: #666;
            font-size: 0.9rem;
        }
        
        .blacklist-time {
            color: #999;
            font-size: 0.8rem;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .permission-notice {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ 安全管理仪表板</h1>
        <div class="user-info">
            <span class="role-badge"><?php echo ucfirst($_SESSION['user_role']); ?></span>
            <div class="nav-links">
                <a href="index.php">返回首页</a>
                <a href="../../pages/auth/logout.php">退出登录</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- 统计卡片 -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <h3>今日API调用</h3>
                <div class="value" id="todayApiCalls">-</div>
            </div>
            <div class="stat-card">
                <h3>今日失败次数</h3>
                <div class="value" id="todayFailures">-</div>
            </div>
            <div class="stat-card">
                <h3>黑名单IP数量</h3>
                <div class="value" id="blacklistedIps">-</div>
            </div>
            <div class="stat-card">
                <h3>最近1小时请求</h3>
                <div class="value" id="hourlyRequests">-</div>
            </div>
        </div>
        
        <!-- 选项卡 -->
        <div class="tabs">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="switchTab('logs')">安全日志</button>
                <button class="tab-button" onclick="switchTab('blacklist')">IP黑名单</button>
                <button class="tab-button" onclick="switchTab('management')" <?php echo $_SESSION['user_role'] !== 'admin' ? 'disabled' : ''; ?>>系统管理</button>
            </div>
            
            <div class="tab-content">
                <!-- 安全日志选项卡 -->
                <div id="logs" class="tab-pane active">
                    <div style="margin-bottom: 1rem;">
                        <select id="logTypeFilter" onchange="loadLogs()">
                            <option value="all">所有日志</option>
                            <option value="security">安全日志</option>
                            <option value="api">API日志</option>
                            <option value="system">系统日志</option>
                        </select>
                    </div>
                    <div id="logsContainer" class="loading">加载中...</div>
                </div>
                
                <!-- IP黑名单选项卡 -->
                <div id="blacklist" class="tab-pane">
                    <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                        <div class="permission-notice">
                            <strong>权限提示:</strong> 您只能查看IP黑名单，封禁和解封操作需要管理员权限。
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-bottom: 1rem;">
                        <button class="btn btn-primary" onclick="showBanForm()" <?php echo $_SESSION['user_role'] !== 'admin' ? 'disabled' : ''; ?>>添加IP封禁</button>
                    </div>
                    
                    <div id="banForm" style="display: none; margin-bottom: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                        <div class="form-group">
                            <label>IP地址:</label>
                            <input type="text" id="banIp" placeholder="请输入IP地址">
                        </div>
                        <div class="form-group">
                            <label>封禁原因:</label>
                            <input type="text" id="banReason" placeholder="请输入封禁原因">
                        </div>
                        <div class="form-group">
                            <label>封禁时长:</label>
                            <select id="banDuration">
                                <option value="3600">1小时</option>
                                <option value="7200">2小时</option>
                                <option value="86400">24小时</option>
                                <option value="604800">7天</option>
                                <option value="0">永久</option>
                            </select>
                        </div>
                        <button class="btn btn-danger" onclick="banIp()">确认封禁</button>
                        <button class="btn" onclick="hideBanForm()">取消</button>
                    </div>
                    
                    <div id="blacklistContainer" class="loading">加载中...</div>
                </div>
                
                <!-- 系统管理选项卡 -->
                <div id="management" class="tab-pane">
                    <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                        <div class="permission-notice">
                            <strong>权限不足:</strong> 系统管理功能仅限管理员使用。
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom: 2rem;">
                            <h3>日志清理</h3>
                            <p>清理指定天数之前的日志记录</p>
                            <div class="form-group">
                                <label>保留天数:</label>
                                <input type="number" id="cleanupDays" value="30" min="1" max="365">
                            </div>
                            <button class="btn btn-danger" onclick="cleanupLogs()">清理日志</button>
                        </div>
                        
                        <div>
                            <h3>系统状态</h3>
                            <div id="systemStatus">
                                <p>✅ 安全管理系统运行正常</p>
                                <p>✅ 日志记录功能正常</p>
                                <p>✅ IP黑名单功能正常</p>
                                <p>✅ 频率限制功能正常</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 当前用户角色
        const userRole = '<?php echo $_SESSION['user_role']; ?>';
        
        // 页面加载时初始化
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadLogs();
            
            // 每30秒刷新统计数据
            setInterval(loadStats, 30000);
        });
        
        // 加载统计数据
        function loadStats() {
            fetch('?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('todayApiCalls').textContent = data.today_api_calls || 0;
                    document.getElementById('todayFailures').textContent = data.today_failures || 0;
                    document.getElementById('blacklistedIps').textContent = data.blacklisted_ips || 0;
                    document.getElementById('hourlyRequests').textContent = data.hourly_requests || 0;
                })
                .catch(error => console.error('加载统计数据失败:', error));
        }
        
        // 切换选项卡
        function switchTab(tabName) {
            // 检查权限
            if (tabName === 'management' && userRole !== 'admin') {
                return;
            }
            
            // 隐藏所有选项卡
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            
            // 移除所有按钮的激活状态
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // 显示选中的选项卡
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
            
            // 根据选项卡加载相应数据
            if (tabName === 'logs') {
                loadLogs();
            } else if (tabName === 'blacklist') {
                loadBlacklist();
            }
        }
        
        // 加载日志
        function loadLogs() {
            const type = document.getElementById('logTypeFilter').value;
            const container = document.getElementById('logsContainer');
            container.innerHTML = '<div class="loading">加载中...</div>';
            
            fetch(`?action=get_logs&type=${type}`)
                .then(response => response.json())
                .then(logs => {
                    if (logs.length === 0) {
                        container.innerHTML = '<div class="loading">暂无日志记录</div>';
                        return;
                    }
                    
                    let html = '';
                    logs.forEach(log => {
                        const context = log.context ? JSON.stringify(JSON.parse(log.context), null, 2) : '';
                        html += `
                            <div class="log-entry">
                                <div class="log-meta">
                                    <span class="log-level ${log.level}">${log.level}</span>
                                    <span class="log-time">${log.created_at}</span>
                                </div>
                                <div class="log-message">${log.message}</div>
                                ${context ? `<div class="log-context">${context}</div>` : ''}
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('加载日志失败:', error);
                    container.innerHTML = '<div class="loading">加载失败</div>';
                });
        }
        
        // 加载黑名单
        function loadBlacklist() {
            const container = document.getElementById('blacklistContainer');
            container.innerHTML = '<div class="loading">加载中...</div>';
            
            fetch('?action=get_blacklist')
                .then(response => response.json())
                .then(blacklist => {
                    if (blacklist.length === 0) {
                        container.innerHTML = '<div class="loading">暂无封禁IP</div>';
                        return;
                    }
                    
                    let html = '';
                    blacklist.forEach(item => {
                        const isActive = !item.banned_until || new Date(item.banned_until) > new Date();
                        const statusText = isActive ? '有效' : '已过期';
                        const statusClass = isActive ? 'text-danger' : 'text-muted';
                        
                        html += `
                            <div class="blacklist-item">
                                <div class="blacklist-info">
                                    <div class="blacklist-ip">${item.ip_address} <span class="${statusClass}">(${statusText})</span></div>
                                    <div class="blacklist-reason">原因: ${item.reason}</div>
                                    <div class="blacklist-time">封禁时间: ${item.created_at}</div>
                                    ${item.banned_until ? `<div class="blacklist-time">解封时间: ${item.banned_until}</div>` : '<div class="blacklist-time">永久封禁</div>'}
                                </div>
                                <div>
                                    ${userRole === 'admin' && isActive ? `<button class="btn btn-success" onclick="unbanIp('${item.ip_address}')">解封</button>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('加载黑名单失败:', error);
                    container.innerHTML = '<div class="loading">加载失败</div>';
                });
        }
        
        // 显示封禁表单
        function showBanForm() {
            if (userRole !== 'admin') {
                alert('权限不足');
                return;
            }
            document.getElementById('banForm').style.display = 'block';
        }
        
        // 隐藏封禁表单
        function hideBanForm() {
            document.getElementById('banForm').style.display = 'none';
            document.getElementById('banIp').value = '';
            document.getElementById('banReason').value = '';
        }
        
        // 封禁IP
        function banIp() {
            if (userRole !== 'admin') {
                alert('权限不足');
                return;
            }
            
            const ip = document.getElementById('banIp').value;
            const reason = document.getElementById('banReason').value;
            const duration = document.getElementById('banDuration').value;
            
            if (!ip || !reason) {
                alert('请填写完整信息');
                return;
            }
            
            const formData = new FormData();
            formData.append('ip', ip);
            formData.append('reason', reason);
            formData.append('duration', duration);
            
            fetch('?action=ban_ip', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    hideBanForm();
                    loadBlacklist();
                    loadStats();
                } else {
                    alert(result.message);
                }
            })
            .catch(error => {
                console.error('封禁失败:', error);
                alert('封禁失败');
            });
        }
        
        // 解封IP
        function unbanIp(ip) {
            if (userRole !== 'admin') {
                alert('权限不足');
                return;
            }
            
            if (!confirm(`确定要解封IP ${ip} 吗？`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('ip', ip);
            
            fetch('?action=unban_ip', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    loadBlacklist();
                    loadStats();
                } else {
                    alert(result.message);
                }
            })
            .catch(error => {
                console.error('解封失败:', error);
                alert('解封失败');
            });
        }
        
        // 清理日志
        function cleanupLogs() {
            if (userRole !== 'admin') {
                alert('权限不足');
                return;
            }
            
            const days = document.getElementById('cleanupDays').value;
            
            if (!confirm(`确定要清理 ${days} 天前的日志吗？此操作不可恢复！`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('days', days);
            
            fetch('?action=cleanup_logs', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    loadLogs();
                    loadStats();
                } else {
                    alert(result.message);
                }
            })
            .catch(error => {
                console.error('清理失败:', error);
                alert('清理失败');
            });
        }
    </script>
</body>
</html>