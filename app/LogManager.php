<?php

/**
 * 日志管理类
 * 负责系统日志的记录、查询和管理功能
 */
class LogManager {
    private $conn;
    private $logDir;
    private $maxFileSize;
    private $maxFiles;
    
    // 日志级别
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    
    // 日志类型
    const TYPE_SECURITY = 'SECURITY';
    const TYPE_API = 'API';
    const TYPE_AUTH = 'AUTH';
    const TYPE_SYSTEM = 'SYSTEM';
    const TYPE_DATABASE = 'DATABASE';
    const TYPE_MAIL = 'MAIL';
    
    /**
     * 构造函数
     * @param mysqli $conn 数据库连接
     * @param string $logDir 日志目录
     * @param int $maxFileSize 最大文件大小（字节）
     * @param int $maxFiles 最大文件数量
     */
    public function __construct($conn, $logDir = null, $maxFileSize = 10485760, $maxFiles = 30) {
        $this->conn = $conn;
        $this->logDir = $logDir ?: dirname(__DIR__) . '/logs';
        $this->maxFileSize = $maxFileSize; // 10MB
        $this->maxFiles = $maxFiles;
        
        $this->initLogDir();
        $this->initTables();
    }
    
    /**
     * 初始化日志目录
     */
    private function initLogDir() {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        // 创建.htaccess文件保护日志目录
        $htaccessFile = $this->logDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Order Deny,Allow\nDeny from all");
        }
    }
    
    /**
     * 初始化数据库表
     */
    private function initTables() {
        // 创建系统日志表
        $sql = "CREATE TABLE IF NOT EXISTS system_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') NOT NULL,
            type ENUM('SECURITY', 'API', 'AUTH', 'SYSTEM', 'DATABASE', 'MAIL') NOT NULL,
            message TEXT NOT NULL,
            context JSON,
            user_id INT DEFAULT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            request_uri VARCHAR(500),
            session_id VARCHAR(128),
            trace_id VARCHAR(64),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_level (level),
            INDEX idx_type (type),
            INDEX idx_user_id (user_id),
            INDEX idx_ip_address (ip_address),
            INDEX idx_created_at (created_at),
            INDEX idx_trace_id (trace_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统日志表'";
        $this->conn->query($sql);
        
        // 创建API访问日志表
        $sql = "CREATE TABLE IF NOT EXISTS api_access_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            endpoint VARCHAR(100) NOT NULL,
            method VARCHAR(10) NOT NULL,
            user_id INT DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            request_data JSON,
            response_status INT,
            response_time DECIMAL(8,3),
            memory_usage INT,
            success TINYINT(1) DEFAULT 1,
            error_message TEXT,
            trace_id VARCHAR(64),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_endpoint (endpoint),
            INDEX idx_user_id (user_id),
            INDEX idx_ip_address (ip_address),
            INDEX idx_success (success),
            INDEX idx_created_at (created_at),
            INDEX idx_trace_id (trace_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API访问日志表'";
        $this->conn->query($sql);
    }
    
    /**
     * 记录系统日志
     * @param string $level 日志级别
     * @param string $type 日志类型
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param string $traceId 追踪ID
     */
    public function log($level, $type, $message, $context = [], $traceId = null) {
        $traceId = $traceId ?: $this->generateTraceId();
        
        // 记录到数据库
        $this->logToDatabase($level, $type, $message, $context, $traceId);
        
        // 记录到文件
        $this->logToFile($level, $type, $message, $context, $traceId);
    }
    
    /**
     * 记录API访问日志
     * @param string $endpoint 接口端点
     * @param string $method 请求方法
     * @param array $requestData 请求数据
     * @param int $responseStatus 响应状态
     * @param float $responseTime 响应时间
     * @param bool $success 是否成功
     * @param string $errorMessage 错误消息
     * @param string $traceId 追踪ID
     */
    public function logApiAccess($endpoint, $method, $requestData = [], $responseStatus = 200, 
                                $responseTime = 0, $success = true, $errorMessage = null, $traceId = null) {
        $traceId = $traceId ?: $this->generateTraceId();
        
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO api_access_logs 
                 (trace_id, endpoint, method, ip, user_id, request_data, 
                  response_code, response_time, success, error_message, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            
            $userId = $_SESSION['user_id'] ?? null;
            $ipAddress = $this->getRealIP();
            $requestDataJson = json_encode($requestData, JSON_UNESCAPED_UNICODE);
            
            $stmt->execute([
                $traceId, $endpoint, $method, $ipAddress, $userId, $requestDataJson,
                $responseStatus, $responseTime, $success ? 1 : 0, $errorMessage
            ]);
            
        } catch (Exception $e) {
            error_log("[LogManager] API access logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * 记录到数据库
     */
    private function logToDatabase($level, $type, $message, $context, $traceId) {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO system_logs 
                 (trace_id, level, type, message, context, user_id, ip, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            
            $userId = $_SESSION['user_id'] ?? null;
            $ipAddress = $this->getRealIP();
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
            
            $stmt->execute([
                $traceId, $level, $type, $message, $contextJson, $userId, $ipAddress
            ]);
            
        } catch (Exception $e) {
            // 数据库记录失败时记录到文件
            error_log("[LogManager] Database logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * 记录到文件
     */
    private function logToFile($level, $type, $message, $context, $traceId) {
        $logFile = $this->getLogFile($type);
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $this->getRealIP(),
            'trace_id' => $traceId
        ];
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
        
        // 检查文件大小并轮转
        if (file_exists($logFile) && filesize($logFile) > $this->maxFileSize) {
            $this->rotateLogFile($logFile);
        }
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 获取日志文件路径
     */
    private function getLogFile($type) {
        $date = date('Y-m-d');
        return $this->logDir . "/" . strtolower($type) . "_{$date}.log";
    }
    
    /**
     * 轮转日志文件
     */
    private function rotateLogFile($logFile) {
        $timestamp = date('His');
        $rotatedFile = $logFile . '.' . $timestamp;
        rename($logFile, $rotatedFile);
        
        // 清理旧文件
        $this->cleanupOldLogs(dirname($logFile));
    }
    
    /**
     * 清理旧日志文件
     */
    private function cleanupOldLogs($logDir) {
        $files = glob($logDir . '/*.log*');
        if (count($files) > $this->maxFiles) {
            // 按修改时间排序
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // 删除最旧的文件
            $filesToDelete = array_slice($files, 0, count($files) - $this->maxFiles);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * 生成追踪ID
     */
    private function generateTraceId() {
        return uniqid('trace_', true);
    }
    
    /**
     * 获取真实IP地址
     */
    private function getRealIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * 便捷方法：记录调试日志
     */
    public function debug($type, $message, $context = [], $traceId = null) {
        $this->log(self::LEVEL_DEBUG, $type, $message, $context, $traceId);
    }
    
    /**
     * 便捷方法：记录信息日志
     */
    public function info($type, $message, $context = [], $traceId = null) {
        $this->log(self::LEVEL_INFO, $type, $message, $context, $traceId);
    }
    
    /**
     * 便捷方法：记录警告日志
     */
    public function warning($type, $message, $context = [], $traceId = null) {
        $this->log(self::LEVEL_WARNING, $type, $message, $context, $traceId);
    }
    
    /**
     * 便捷方法：记录错误日志
     */
    public function error($type, $message, $context = [], $traceId = null) {
        $this->log(self::LEVEL_ERROR, $type, $message, $context, $traceId);
    }
    
    /**
     * 便捷方法：记录严重错误日志
     */
    public function critical($type, $message, $context = [], $traceId = null) {
        $this->log(self::LEVEL_CRITICAL, $type, $message, $context, $traceId);
    }
    
    /**
     * 查询日志
     * @param array $filters 过滤条件
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array
     */
    public function queryLogs($filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM system_logs WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['level'])) {
            $sql .= " AND level = ?";
            $params[] = $filters['level'];
            $types .= 's';
        }
        
        if (!empty($filters['type'])) {
            $sql .= " AND type = ?";
            $params[] = $filters['type'];
            $types .= 's';
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['ip_address'])) {
            $sql .= " AND ip_address = ?";
            $params[] = $filters['ip_address'];
            $types .= 's';
        }
        
        if (!empty($filters['start_time'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['start_time'];
            $types .= 's';
        }
        
        if (!empty($filters['end_time'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['end_time'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];
        
        while ($row = $result->fetch_assoc()) {
            $row['context'] = json_decode($row['context'], true);
            $logs[] = $row;
        }
        
        $stmt->close();
        return $logs;
    }
    
    /**
     * 清理过期日志
     * @param int $days 保留天数
     */
    public function cleanup($days = 30) {
        // 清理数据库日志
        $this->conn->query(
            "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)"
        );
        
        $this->conn->query(
            "DELETE FROM api_access_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)"
        );
        
        // 清理文件日志
        $cutoffTime = time() - ($days * 24 * 3600);
        $files = glob($this->logDir . '/*.log*');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
}