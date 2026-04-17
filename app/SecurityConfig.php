<?php
/**
 * 安全配置类
 * 集中管理系统安全相关的配置参数
 * 
 * @author Security System
 * @version 1.0
 * @created 2024
 */

class SecurityConfig {
    
    // API频率限制配置
    const RATE_LIMITS = [
        'activation_api' => [
            'minute' => 5,      // 每分钟最多5次请求
            'hour' => 30,       // 每小时最多30次请求
            'day' => 50         // 每天最多50次请求
        ],
        'default' => [
            'minute' => 10,     // 默认每分钟最多10次请求
            'hour' => 60,       // 默认每小时最多60次请求
            'day' => 200        // 默认每天最多200次请求
        ]
    ];
    
    // 自动封禁配置
    const AUTO_BAN_CONFIG = [
        'activation_api' => [
            'max_failures' => 10,           // 最大失败次数
            'time_window' => 3600,          // 时间窗口（秒）
            'ban_duration' => 7200          // 封禁时长（秒）
        ],
        'default' => [
            'max_failures' => 20,           // 默认最大失败次数
            'time_window' => 3600,          // 默认时间窗口（秒）
            'ban_duration' => 3600          // 默认封禁时长（秒）
        ]
    ];
    
    // 日志配置
    const LOG_CONFIG = [
        'max_file_size' => 10485760,        // 单个日志文件最大大小（10MB）
        'max_files' => 30,                  // 最大保留文件数
        'retention_days' => 30,             // 日志保留天数
        'log_directory' => 'logs',          // 日志目录
        'enable_database_log' => true,      // 是否启用数据库日志
        'enable_file_log' => true           // 是否启用文件日志
    ];
    
    // 安全检查配置
    const SECURITY_CONFIG = [
        'enable_ip_whitelist' => false,     // 是否启用IP白名单
        'enable_ip_blacklist' => true,      // 是否启用IP黑名单
        'enable_rate_limiting' => true,     // 是否启用频率限制
        'enable_auto_ban' => true,          // 是否启用自动封禁
        'enable_captcha_check' => true,     // 是否启用验证码检查
        'enable_order_ownership' => true,   // 是否启用订单所有权检查
        'max_daily_queries' => 50,          // 每日最大查询次数
        'sensitive_data_mask' => true       // 是否对敏感数据脱敏
    ];
    
    // 可信IP列表（白名单）
    const TRUSTED_IPS = [
        '127.0.0.1',
        '::1',
        // 可以添加更多可信IP
    ];
    
    // 敏感数据脱敏配置
    const MASK_CONFIG = [
        'order_no' => [
            'show_start' => 8,      // 显示开头8位
            'show_end' => 0,        // 显示结尾0位
            'mask_char' => '*'      // 脱敏字符
        ],
        'psn' => [
            'show_start' => 6,      // 显示开头6位
            'show_end' => 0,        // 显示结尾0位
            'mask_char' => '*'      // 脱敏字符
        ],
        'email' => [
            'show_start' => 3,      // 显示开头3位
            'show_end' => 0,        // 显示结尾0位（@后保留）
            'mask_char' => '*'      // 脱敏字符
        ]
    ];
    
    /**
     * 获取API频率限制配置
     * @param string $endpoint 接口端点
     * @param string $window 时间窗口
     * @return int 限制次数
     */
    public static function getRateLimit($endpoint, $window) {
        $limits = self::RATE_LIMITS[$endpoint] ?? self::RATE_LIMITS['default'];
        return $limits[$window] ?? $limits['minute'];
    }
    
    /**
     * 获取自动封禁配置
     * @param string $endpoint 接口端点
     * @return array 封禁配置
     */
    public static function getAutoBanConfig($endpoint) {
        return self::AUTO_BAN_CONFIG[$endpoint] ?? self::AUTO_BAN_CONFIG['default'];
    }
    
    /**
     * 检查IP是否在白名单中
     * @param string $ip IP地址
     * @return bool 是否在白名单中
     */
    public static function isTrustedIp($ip) {
        return in_array($ip, self::TRUSTED_IPS);
    }
    
    /**
     * 对敏感数据进行脱敏处理
     * @param string $data 原始数据
     * @param string $type 数据类型
     * @return string 脱敏后的数据
     */
    public static function maskSensitiveData($data, $type) {
        if (!self::SECURITY_CONFIG['sensitive_data_mask']) {
            return $data;
        }
        
        $config = self::MASK_CONFIG[$type] ?? self::MASK_CONFIG['order_no'];
        $dataLen = strlen($data);
        
        if ($dataLen <= $config['show_start']) {
            return $data;
        }
        
        $showStart = substr($data, 0, $config['show_start']);
        $showEnd = $config['show_end'] > 0 ? substr($data, -$config['show_end']) : '';
        $maskLen = $dataLen - $config['show_start'] - $config['show_end'];
        
        if ($maskLen <= 0) {
            return $data;
        }
        
        return $showStart . str_repeat($config['mask_char'], min($maskLen, 3)) . $showEnd;
    }
    
    /**
     * 获取日志配置
     * @param string $key 配置键名
     * @return mixed 配置值
     */
    public static function getLogConfig($key = null) {
        if ($key === null) {
            return self::LOG_CONFIG;
        }
        return self::LOG_CONFIG[$key] ?? null;
    }
    
    /**
     * 获取安全配置
     * @param string $key 配置键名
     * @return mixed 配置值
     */
    public static function getSecurityConfig($key = null) {
        if ($key === null) {
            return self::SECURITY_CONFIG;
        }
        return self::SECURITY_CONFIG[$key] ?? false;
    }
    
    /**
     * 验证配置的有效性
     * @return array 验证结果
     */
    public static function validateConfig() {
        $errors = [];
        
        // 检查日志目录是否可写
        $logDir = dirname(__DIR__) . '/' . self::LOG_CONFIG['log_directory'];
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                $errors[] = "无法创建日志目录: {$logDir}";
            }
        } elseif (!is_writable($logDir)) {
            $errors[] = "日志目录不可写: {$logDir}";
        }
        
        // 检查频率限制配置
        foreach (self::RATE_LIMITS as $endpoint => $limits) {
            foreach ($limits as $window => $limit) {
                if (!is_int($limit) || $limit <= 0) {
                    $errors[] = "无效的频率限制配置: {$endpoint}.{$window} = {$limit}";
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

?>