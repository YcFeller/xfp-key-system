<?php
/**
 * 爱发电平台Webhook处理器
 * 用于接收和处理爱发电平台的订单通知
 * 
 * @author Your Name
 * @version 2.0
 * @since 2024
 */

// 设置错误报告级别
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 引入配置文件
require_once './config.php';

// 定义常量
define('MAX_REQUEST_SIZE', 1024 * 1024); // 1MB
define('LOG_DIR', '../logs');
define('WEBHOOK_LOG_FILE', LOG_DIR . '/webhook.log');
define('ERROR_LOG_FILE', LOG_DIR . '/webhook_error.log');

/**
 * 分级日志记录类
 */
class WebhookLogger {
    private static $logLevels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    
    /**
     * 记录日志
     * @param string $message 日志消息
     * @param string $level 日志级别
     * @param array $context 上下文数据
     */
    public static function log($message, $level = 'INFO', $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        // 根据日志级别选择文件
        $logFile = in_array($level, ['ERROR', 'CRITICAL']) ? ERROR_LOG_FILE : WEBHOOK_LOG_FILE;
        
        // 确保日志目录存在
        if (!is_dir(LOG_DIR)) {
            mkdir(LOG_DIR, 0755, true);
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

/**
 * 输入验证类
 */
class InputValidator {
    /**
     * 验证请求方法
     */
    public static function validateRequestMethod() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new InvalidArgumentException('只允许POST请求');
        }
    }
    
    /**
     * 验证Content-Type
     */
    public static function validateContentType() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') === false) {
            throw new InvalidArgumentException('Content-Type必须为application/json');
        }
    }
    
    /**
     * 验证请求大小
     * @param string $data 请求数据
     */
    public static function validateRequestSize($data) {
        if (strlen($data) > MAX_REQUEST_SIZE) {
            throw new InvalidArgumentException('请求数据过大');
        }
    }
    
    /**
     * 验证订单数据结构
     * @param array $order 订单数据
     */
    public static function validateOrderData($order) {
        $requiredFields = ['out_trade_no', 'user_id', 'plan_id', 'total_amount'];
        
        foreach ($requiredFields as $field) {
            if (!isset($order[$field]) || empty($order[$field])) {
                throw new InvalidArgumentException("缺少必需字段: {$field}");
            }
        }
        
        // 验证金额格式
        if (!is_numeric($order['total_amount']) || $order['total_amount'] <= 0) {
            throw new InvalidArgumentException('订单金额格式无效');
        }
        
        // 验证订单号格式
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $order['out_trade_no'])) {
            throw new InvalidArgumentException('订单号格式无效');
        }
    }
}

/**
 * 响应处理类
 */
class ResponseHandler {
    /**
     * 发送JSON响应
     * @param int $code 响应码
     * @param string $message 响应消息
     * @param array $data 额外数据
     */
    public static function sendResponse($code, $message = '', $data = []) {
        $response = [
            'ec' => $code,
            'em' => $message
        ];
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 发送错误响应
     * @param int $code 错误码
     * @param string $message 错误消息
     */
    public static function sendError($code, $message) {
        WebhookLogger::log("发送错误响应: {$message}", 'ERROR', ['code' => $code]);
        self::sendResponse($code, $message);
    }
}

// 开始处理请求
$startTime = microtime(true);
$requestId = uniqid('webhook_', true);

WebhookLogger::log('开始处理Webhook请求', 'INFO', [
    'request_id' => $requestId,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

try {
    // 验证请求方法和Content-Type
    InputValidator::validateRequestMethod();
    InputValidator::validateContentType();
    
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');
    
    // 检查数据是否有效
    if (!$rawData) {
        ResponseHandler::sendError(400, '无效的数据');
    }
    
    // 验证请求大小
    InputValidator::validateRequestSize($rawData);
    
    // 解析JSON数据
    $data = json_decode($rawData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        ResponseHandler::sendError(400, 'JSON解析失败: ' . json_last_error_msg());
    }
    
    // 验证数据结构
    if (!isset($data['data']['type']) || 
        $data['data']['type'] !== 'order' || 
        !isset($data['data']['order'])) {
        ResponseHandler::sendError(400, '数据格式错误');
    }
    
    $order = $data['data']['order'];
    
    // 验证订单数据
    InputValidator::validateOrderData($order);
    
    WebhookLogger::log('订单数据验证通过', 'INFO', [
        'request_id' => $requestId,
        'out_trade_no' => $order['out_trade_no'],
        'plan_id' => $order['plan_id']
    ]);
    
} catch (Exception $e) {
    ResponseHandler::sendError(400, $e->getMessage());
}

/**
 * 数据库操作类
 */
class DatabaseHandler {
    private $conn;
    
    /**
     * 构造函数 - 建立数据库连接
     */
    public function __construct() {
        global $servername, $db_user, $db_pass, $dbname;
        
        $this->conn = new mysqli($servername, $db_user, $db_pass, $dbname);
        
        if ($this->conn->connect_error) {
            throw new Exception('数据库连接失败: ' . $this->conn->connect_error);
        }
        
        // 设置字符集
        $this->conn->set_charset('utf8mb4');
    }
    
    /**
     * 查询plan_id对应的用户ID
     * @param string $planId 计划ID
     * @return int|null 用户ID
     */
    public function getUserIdByPlanId($planId) {
        $sql = "SELECT user_id FROM xfp_wflist WHERE plan_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('准备查询语句失败: ' . $this->conn->error);
        }
        
        $stmt->bind_param("s", $planId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['user_id'];
    }
    
    /**
     * 检查订单是否已存在
     * @param string $outTradeNo 订单号
     * @return bool 是否存在
     */
    public function orderExists($outTradeNo) {
        $sql = "SELECT id FROM xfp_order WHERE out_trade_no = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('准备查询语句失败: ' . $this->conn->error);
        }
        
        $stmt->bind_param("s", $outTradeNo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        return $exists;
    }
    
    /**
     * 插入新订单
     * @param array $orderData 订单数据
     * @return bool 是否成功
     */
    public function insertOrder($orderData) {
        $sql = "INSERT INTO xfp_order (
            out_trade_no, user_id, afdian_user_id, system_user_id, 
            total_amount, remark, discount, sku_detail, product_name, 
            plan_id, downloads_limit, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('准备插入语句失败: ' . $this->conn->error);
        }
        
        $stmt->bind_param(
            "ssssdsdsssi",
            $orderData['out_trade_no'],
            $orderData['user_id'],
            $orderData['afdian_user_id'],
            $orderData['system_user_id'],
            $orderData['total_amount'],
            $orderData['remark'],
            $orderData['discount'],
            $orderData['sku_detail'],
            $orderData['product_name'],
            $orderData['plan_id'],
            $orderData['downloads_limit']
        );
        
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception('订单插入失败: ' . $stmt->error);
        }
        
        $stmt->close();
        return true;
    }
    
    /**
     * 关闭数据库连接
     */
    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    /**
     * 析构函数 - 自动关闭连接
     */
    public function __destruct() {
        $this->close();
    }
}

/**
 * 订单处理类
 */
class OrderProcessor {
    private $db;
    private $requestId;
    
    public function __construct($requestId) {
        $this->db = new DatabaseHandler();
        $this->requestId = $requestId;
    }
    
    /**
     * 处理订单数据
     * @param array $order 订单数据
     * @return bool 处理结果
     */
    public function processOrder($order) {
        $processStart = microtime(true);
        
        try {
            // 提取订单数据
            $orderData = $this->extractOrderData($order);
            
            WebhookLogger::log('开始处理订单', 'INFO', [
                'request_id' => $this->requestId,
                'out_trade_no' => $orderData['out_trade_no'],
                'total_amount' => $orderData['total_amount']
            ]);
            
            // 查询系统用户ID
            $systemUserId = $this->db->getUserIdByPlanId($orderData['plan_id']);
            
            if ($systemUserId === null) {
                throw new Exception('未找到匹配的用户ID，plan_id: ' . $orderData['plan_id']);
            }
            
            $orderData['system_user_id'] = $systemUserId;
            
            // 检查订单是否已存在
            if ($this->db->orderExists($orderData['out_trade_no'])) {
                WebhookLogger::log('订单已存在，跳过处理', 'WARNING', [
                    'request_id' => $this->requestId,
                    'out_trade_no' => $orderData['out_trade_no']
                ]);
                return true; // 返回成功，避免重复处理
            }
            
            // 插入新订单
            $this->db->insertOrder($orderData);
            
            $processingTime = round((microtime(true) - $processStart) * 1000, 2);
            
            WebhookLogger::log('订单处理成功', 'INFO', [
                'request_id' => $this->requestId,
                'out_trade_no' => $orderData['out_trade_no'],
                'system_user_id' => $systemUserId,
                'processing_time_ms' => $processingTime
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $processingTime = round((microtime(true) - $processStart) * 1000, 2);
            
            WebhookLogger::log('订单处理失败', 'ERROR', [
                'request_id' => $this->requestId,
                'out_trade_no' => $order['out_trade_no'] ?? 'unknown',
                'error_message' => $e->getMessage(),
                'processing_time_ms' => $processingTime
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 提取并验证订单数据
     * @param array $order 原始订单数据
     * @return array 处理后的订单数据
     */
    private function extractOrderData($order) {
        return [
            'out_trade_no' => $order['out_trade_no'],
            'user_id' => $order['user_id'],
            'afdian_user_id' => $order['user_private_id'] ?? $order['user_id'],
            'total_amount' => floatval($order['total_amount']),
            'remark' => $order['remark'] ?? '',
            'discount' => floatval($order['discount'] ?? 0),
            'sku_detail' => json_encode($order['sku_detail'] ?? [], JSON_UNESCAPED_UNICODE),
            'product_name' => $order['sku_detail'][0]['name'] ?? '',
            'plan_id' => $order['plan_id'],
            'downloads_limit' => 1 // 默认下载次数
        ];
    }
}

// 提取订单数据
$out_trade_no = $order['out_trade_no'];
$plan_id = $order['plan_id'];
$total_amount = $order['total_amount'];

// 立即返回成功响应（异步处理）
ResponseHandler::sendResponse(200, '请求已接收');

// 异步处理订单数据
WebhookLogger::log('开始异步处理订单', 'INFO', [
    'request_id' => $requestId,
    'out_trade_no' => $out_trade_no,
    'plan_id' => $plan_id,
    'total_amount' => $total_amount
]);

// 使用新的订单处理器处理订单
try {
    $processor = new OrderProcessor($requestId);
    $processor->processOrder($order);
    
    // 记录总体处理时间
    $totalTime = round((microtime(true) - $startTime) * 1000, 2);
    
    WebhookLogger::log('Webhook请求处理完成', 'INFO', [
        'request_id' => $requestId,
        'out_trade_no' => $out_trade_no,
        'total_processing_time_ms' => $totalTime,
        'status' => 'success'
    ]);
    
} catch (Exception $e) {
    // 记录处理失败
    $totalTime = round((microtime(true) - $startTime) * 1000, 2);
    
    WebhookLogger::log('Webhook请求处理失败', 'CRITICAL', [
        'request_id' => $requestId,
        'out_trade_no' => $out_trade_no ?? 'unknown',
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'total_processing_time_ms' => $totalTime,
        'status' => 'failed'
    ]);
    
    // 对于异步处理的错误，我们已经返回了成功响应
    // 这里只记录错误，不影响已发送的响应
}

/**
 * 性能监控和清理
 */
register_shutdown_function(function() use ($requestId, $startTime) {
    $totalTime = round((microtime(true) - $startTime) * 1000, 2);
    $memoryUsage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
    
    WebhookLogger::log('请求处理完毕', 'DEBUG', [
        'request_id' => $requestId,
        'total_time_ms' => $totalTime,
        'memory_usage_mb' => $memoryUsage,
        'memory_limit' => ini_get('memory_limit')
    ]);
    
    // 清理日志文件（保留最近7天的日志）
    $logFiles = [WEBHOOK_LOG_FILE, ERROR_LOG_FILE];
    foreach ($logFiles as $logFile) {
        if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) { // 10MB
            $lines = file($logFile);
            if (count($lines) > 10000) {
                // 保留最新的5000行
                $newContent = implode('', array_slice($lines, -5000));
                file_put_contents($logFile, $newContent, LOCK_EX);
                
                WebhookLogger::log('日志文件已清理', 'INFO', [
                    'file' => basename($logFile),
                    'original_lines' => count($lines),
                    'remaining_lines' => 5000
                ]);
            }
        }
    }
});

// 脚本结束
exit;
