<?php

/**
 * 安全管理类
 * 负责处理API请求的安全验证、频率限制、IP黑名单等功能
 */
class SecurityManager {
    private $conn;
    private $config;
    
    // 默认配置
    private $defaultConfig = [
        'rate_limit_per_minute' => 10,     // 每分钟最大请求次数
        'rate_limit_per_hour' => 100,     // 每小时最大请求次数
        'rate_limit_per_day' => 500,      // 每天最大请求次数
        'max_failed_attempts' => 5,       // 最大失败尝试次数
        'lockout_duration' => 1800,       // 锁定时长（秒）
        'suspicious_threshold' => 20,     // 可疑行为阈值
        'auto_ban_duration' => 3600,      // 自动封禁时长（秒）
        'max_order_queries_per_day' => 50 // 每天最大订单查询次数
    ];
    
    /**
     * 构造函数
     * @param mysqli $conn 数据库连接
     * @param array $config 配置参数
     */
    public function __construct($conn, $config = []) {
        $this->conn = $conn;
        $this->config = array_merge($this->defaultConfig, $config);
        $this->initTables();
    }
    
    /**
     * 初始化安全相关数据表
     */
    private function initTables() {
        // 创建请求限制表
        $sql = "CREATE TABLE IF NOT EXISTS api_rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            user_id INT DEFAULT NULL,
            endpoint VARCHAR(100) NOT NULL,
            request_count INT DEFAULT 1,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            window_type ENUM('minute', 'hour', 'day') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ip_endpoint_window (ip_address, endpoint, window_type),
            INDEX idx_user_endpoint_window (user_id, endpoint, window_type),
            INDEX idx_window_start (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API请求频率限制表'";
        $this->conn->query($sql);
        
        // 创建IP黑名单表
        $sql = "CREATE TABLE IF NOT EXISTS ip_blacklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL UNIQUE,
            reason VARCHAR(255) NOT NULL,
            banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            is_permanent TINYINT(1) DEFAULT 0,
            ban_count INT DEFAULT 1,
            created_by INT DEFAULT NULL,
            INDEX idx_ip_address (ip_address),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IP黑名单表'";
        $this->conn->query($sql);
        
        // 创建失败尝试记录表
        $sql = "CREATE TABLE IF NOT EXISTS failed_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            user_id INT DEFAULT NULL,
            endpoint VARCHAR(100) NOT NULL,
            failure_type VARCHAR(50) NOT NULL,
            failure_reason TEXT,
            attempt_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_endpoint (ip_address, endpoint),
            INDEX idx_user_endpoint (user_id, endpoint),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='失败尝试记录表'";
        $this->conn->query($sql);
    }
    
    /**
     * 检查IP是否在黑名单中
     * @param string $ip IP地址
     * @return bool
     */
    public function isIPBlacklisted($ip) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT id FROM ip_blacklist 
                 WHERE ip = ? 
                 AND (banned_until IS NULL OR banned_until > NOW())"
            );
            $stmt->execute([$ip]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("[SecurityManager] Check IP blacklist failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 检查请求频率限制
     * @param string $ip IP地址
     * @param int $userId 用户ID
     * @param string $endpoint 接口端点
     * @return array 检查结果
     */
    public function checkRateLimit($ip, $userId = null, $endpoint = 'api') {
        $checks = [
            'minute' => $this->config['rate_limit_per_minute'],
            'hour' => $this->config['rate_limit_per_hour'],
            'day' => $this->config['rate_limit_per_day']
        ];
        
        foreach ($checks as $window => $limit) {
            $count = $this->getRateLimitCount($ip, $userId, $endpoint, $window);
            if ($count >= $limit) {
                return [
                    'allowed' => false,
                    'window' => $window,
                    'limit' => $limit,
                    'current' => $count,
                    'reset_time' => $this->getResetTime($window)
                ];
            }
        }
        
        // 记录请求
        $this->recordRequest($ip, $userId, $endpoint);
        
        return ['allowed' => true];
    }
    
    /**
     * 获取指定时间窗口内的请求次数
     * @param string $ip IP地址
     * @param int|null $userId 用户ID
     * @param string $endpoint 接口端点
     * @param string $window 时间窗口 (minute/hour/day)
     * @return int 请求次数
     */
    public function getRateLimitCount($ip, $userId, $endpoint, $window = 'minute') {
        $windowStart = $this->getWindowStart($window);
        
        try {
            $sql = "SELECT SUM(request_count) as total FROM api_rate_limits 
                    WHERE ip = ? AND endpoint = ? AND window_type = ? 
                    AND window_start >= ?";
            $params = [$ip, $endpoint, $window, $windowStart];
            
            if ($userId) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)($row['total'] ?? 0);
        } catch (Exception $e) {
            error_log("[SecurityManager] Get rate limit count failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 记录请求
     */
    private function recordRequest($ip, $userId, $endpoint) {
        foreach (['minute', 'hour', 'day'] as $window) {
            $windowStart = $this->getWindowStart($window);
            
            try {
                // 使用INSERT ... ON DUPLICATE KEY UPDATE 简化逻辑
                $stmt = $this->conn->prepare(
                    "INSERT INTO api_rate_limits (ip, user_id, endpoint, window_type, window_start, request_count, created_at) 
                     VALUES (?, ?, ?, ?, ?, 1, NOW()) 
                     ON DUPLICATE KEY UPDATE 
                     request_count = request_count + 1, 
                     updated_at = NOW()"
                );
                $stmt->execute([$ip, $userId, $endpoint, $window, $windowStart]);
            } catch (Exception $e) {
                error_log("[SecurityManager] Record request failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * 记录失败尝试
     * @param string $ip IP地址
     * @param int $userId 用户ID
     * @param string $endpoint 接口端点
     * @param string $failureType 失败类型
     * @param string $failureReason 失败原因
     * @param array $attemptData 尝试数据
     */
    public function recordFailedAttempt($ip, $userId, $endpoint, $failureType, $failureReason, $attemptData = []) {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO failed_attempts (ip, user_id, endpoint, failure_type, failure_reason, attempt_data, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $attemptDataJson = json_encode($attemptData, JSON_UNESCAPED_UNICODE);
            $stmt->execute([$ip, $userId, $endpoint, $failureType, $failureReason, $attemptDataJson]);
            
            // 检查是否需要自动封禁
            $this->checkAutoban($ip, $userId, $endpoint);
        } catch (Exception $e) {
            error_log("[SecurityManager] Record failed attempt failed: " . $e->getMessage());
        }
    }
    
    /**
     * 检查是否需要自动封禁
     */
    private function checkAutoban($ip, $userId, $endpoint) {
        try {
            // 检查最近1小时内的失败次数
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) as count FROM failed_attempts 
                 WHERE ip = ? AND endpoint = ? 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $stmt->execute([$ip, $endpoint]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row['count'] >= $this->config['max_failed_attempts']) {
                $this->banIP($ip, '自动封禁：失败尝试次数过多', $this->config['auto_ban_duration']);
            }
        } catch (Exception $e) {
            error_log("[SecurityManager] Check autoban failed: " . $e->getMessage());
        }
    }
    
    /**
     * 封禁IP地址
     * @param string $ip IP地址
     * @param string $reason 封禁原因
     * @param int $duration 封禁时长（秒），0表示永久
     */
    public function banIP($ip, $reason, $duration = 0) {
        $bannedUntil = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration) : null;
        
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO ip_blacklist (ip, reason, banned_until, created_at) 
                 VALUES (?, ?, ?, NOW()) 
                 ON DUPLICATE KEY UPDATE 
                 reason = VALUES(reason), 
                 banned_until = VALUES(banned_until), 
                 updated_at = NOW()"
            );
            $stmt->execute([$ip, $reason, $bannedUntil]);
        } catch (Exception $e) {
            error_log("[SecurityManager] Ban IP failed: " . $e->getMessage());
        }
    }
    
    /**
     * 解封IP地址
     * @param string $ip IP地址
     */
    public function unbanIp($ip) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM ip_blacklist WHERE ip = ?");
            $stmt->execute([$ip]);
        } catch (Exception $e) {
            error_log("[SecurityManager] Unban IP failed: " . $e->getMessage());
        }
    }
    
    /**
     * 获取时间窗口开始时间
     */
    private function getWindowStart($window) {
        $now = time();
        switch ($window) {
            case 'minute':
                return date('Y-m-d H:i:00', $now);
            case 'hour':
                return date('Y-m-d H:00:00', $now);
            case 'day':
                return date('Y-m-d 00:00:00', $now);
            default:
                return date('Y-m-d H:i:s', $now);
        }
    }
    
    /**
     * 获取重置时间
     */
    private function getResetTime($window) {
        $now = time();
        switch ($window) {
            case 'minute':
                return $now + (60 - ($now % 60));
            case 'hour':
                return $now + (3600 - ($now % 3600));
            case 'day':
                return strtotime('tomorrow 00:00:00');
            default:
                return $now + 60;
        }
    }
    
    /**
     * 清理过期数据
     */
    public function cleanup() {
        // 清理过期的频率限制记录（保留7天）
        $this->conn->query(
            "DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // 清理过期的失败尝试记录（保留30天）
        $this->conn->query(
            "DELETE FROM failed_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // 清理过期的IP黑名单
        $this->conn->query(
            "DELETE FROM ip_blacklist WHERE is_permanent = 0 AND expires_at < NOW()"
        );
    }
    
    /**
     * 获取客户端真实IP地址
     * @return string
     */
    public static function getRealIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // 代理服务器
            'HTTP_X_FORWARDED',          // 代理服务器
            'HTTP_X_CLUSTER_CLIENT_IP',  // 集群
            'HTTP_FORWARDED_FOR',        // 代理服务器
            'HTTP_FORWARDED',            // 代理服务器
            'HTTP_CLIENT_IP',            // 代理服务器
            'REMOTE_ADDR'                // 标准
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}