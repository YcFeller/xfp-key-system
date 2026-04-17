<?php
/**
 * 爱发电订单批量更新脚本 - 优化版本
 * 功能：定时批量同步所有用户的爱发电订单数据
 * 优化：分批处理、超时控制、错误重试、进度跟踪
 * 作者：XFP系统
 * 版本：2.0
 */

session_start();

// 引入数据库配置信息
require_once './config.php';

// 脚本配置参数
define('MAX_EXECUTION_TIME', 300); // 最大执行时间（秒）
define('MEMORY_LIMIT', '256M'); // 内存限制
define('BATCH_SIZE', 5); // 每批处理的用户数量
define('ORDER_BATCH_SIZE', 50); // 每批处理的订单数量
define('CURL_TIMEOUT', 30); // cURL超时时间
define('MAX_RETRIES', 3); // 最大重试次数
define('LOCK_FILE', __DIR__ . '/afd_update.lock'); // 锁文件路径
define('LOG_DIR', __DIR__ . '/../logs'); // 日志目录
define('LOG_RETENTION_DAYS', 30); // 日志保留天数
define('WEB_TIMEOUT', 30); // Web访问超时时间（秒）
define('ASYNC_MODE', true); // 是否启用异步模式

// 设置脚本执行环境
ini_set('max_execution_time', MAX_EXECUTION_TIME);
ini_set('memory_limit', MEMORY_LIMIT);
set_time_limit(MAX_EXECUTION_TIME);

/**
 * 日志管理类
 */
class Logger {
    private static $logFiles = [];
    
    /**
     * 初始化日志系统
     */
    public static function init() {
        // 确保日志目录存在
        if (!is_dir(LOG_DIR)) {
            mkdir(LOG_DIR, 0755, true);
        }
        
        $date = date('Y-m-d');
        self::$logFiles = [
            'INFO' => LOG_DIR . "/afd_order_update_{$date}.log",
            'ERROR' => LOG_DIR . "/error/afd_order_error_{$date}.log",
            'WARNING' => LOG_DIR . "/warning/afd_order_warning_{$date}.log",
            'PERFORMANCE' => LOG_DIR . "/performance/afd_order_perf_{$date}.log",
            'DEBUG' => LOG_DIR . "/debug/afd_order_debug_{$date}.log"
        ];
        
        // 创建子目录
        foreach (['error', 'warning', 'performance', 'debug'] as $subDir) {
            $dir = LOG_DIR . '/' . $subDir;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // 清理旧日志
        self::cleanOldLogs();
    }
    
    /**
     * 写入日志
     * @param string $message 日志消息
     * @param string $level 日志级别
     * @param array $context 上下文信息
     */
    public static function log($message, $level = 'INFO', $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $microtime = sprintf('%.3f', microtime(true) - floor(microtime(true)));
        $pid = getmypid();
        $memory = round(memory_get_usage(true) / 1024 / 1024, 2);
        
        // 构建日志消息
        $logMessage = "[{$timestamp}{$microtime}] [PID:{$pid}] [MEM:{$memory}MB] [{$level}] {$message}";
        
        // 添加上下文信息
        if (!empty($context)) {
            $logMessage .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        $logMessage .= PHP_EOL;
        
        // 写入对应级别的日志文件
        $logFile = self::$logFiles[$level] ?? self::$logFiles['INFO'];
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // 同时写入主日志文件
        if ($level !== 'INFO') {
            file_put_contents(self::$logFiles['INFO'], $logMessage, FILE_APPEND | LOCK_EX);
        }
        
        // 控制台输出（仅在CLI模式下）
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    /**
     * 清理旧日志文件
     */
    public static function cleanOldLogs() {
        $cutoffTime = time() - (LOG_RETENTION_DAYS * 24 * 3600);
        
        $directories = [LOG_DIR, LOG_DIR . '/error', LOG_DIR . '/warning', LOG_DIR . '/performance', LOG_DIR . '/debug'];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;
            
            $files = glob($dir . '/*.log');
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    unlink($file);
                    self::log("已清理旧日志文件: {$file}", 'INFO');
                }
            }
        }
    }
    
    /**
     * 记录性能指标
     * @param string $operation 操作名称
     * @param float $startTime 开始时间
     * @param array $metrics 其他指标
     */
    public static function logPerformance($operation, $startTime, $metrics = []) {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $memory = round(memory_get_usage(true) / 1024 / 1024, 2);
        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        
        $perfData = array_merge([
            'operation' => $operation,
            'duration_ms' => $duration,
            'memory_mb' => $memory,
            'peak_memory_mb' => $peakMemory
        ], $metrics);
        
        self::log("Performance: {$operation}", 'PERFORMANCE', $perfData);
    }
}

/**
 * 兼容性函数 - 写入日志
 * @param string $message 日志消息
 * @param string $level 日志级别
 * @param array $context 上下文信息
 */
function writeLog($message, $level = 'INFO', $context = []) {
    Logger::log($message, $level, $context);
}

/**
 * 检查脚本锁定状态
 * @return bool 是否已锁定
 */
function checkLock() {
    if (file_exists(LOCK_FILE)) {
        $lockTime = filemtime(LOCK_FILE);
        // 如果锁文件超过1小时，认为是僵尸锁，删除它
        if (time() - $lockTime > 3600) {
            unlink(LOCK_FILE);
            return false;
        }
        return true;
    }
    return false;
}

/**
 * 创建脚本锁
 */
function createLock() {
    file_put_contents(LOCK_FILE, getmypid());
}

/**
 * 释放脚本锁
 */
function releaseLock() {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

// 初始化日志系统
Logger::init();

/**
 * Web访问处理类
 */
class WebHandler {
    /**
     * 检查是否为Web访问
     * @return bool
     */
    public static function isWebAccess() {
        return isset($_SERVER['HTTP_HOST']) && php_sapi_name() !== 'cli';
    }
    
    /**
     * 发送异步响应
     * @param string $message 响应消息
     */
    public static function sendAsyncResponse($message = '任务已启动，正在后台执行...') {
        if (!self::isWebAccess()) return;
        
        // 设置响应头
        header('Content-Type: text/html; charset=utf-8');
        header('Connection: close');
        
        // 输出响应内容
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>订单更新任务</title></head><body>";
        echo "<h2>爱发电订单更新任务</h2>";
        echo "<p>{$message}</p>";
        echo "<p>任务开始时间: " . date('Y-m-d H:i:s') . "</p>";
        echo "<p>请查看日志文件获取详细进度信息</p>";
        echo "<script>setTimeout(function(){window.close();}, 3000);</script>";
        echo "</body></html>";
        
        // 刷新输出缓冲区并关闭连接
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();
        
        // 关闭连接但继续执行脚本
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
    
    /**
     * 设置Web访问超时
     */
    public static function setWebTimeout() {
        if (self::isWebAccess()) {
            set_time_limit(WEB_TIMEOUT);
            ini_set('max_execution_time', WEB_TIMEOUT);
        }
    }
}

// 检查计划任务访问的身份验证（密钥见 .env 中 CRON_SECRET；CLI：php afd_orderlist_update.php <密钥>）
$cron_secret = env('CRON_SECRET', '');
if ($cron_secret === '') {
    writeLog('CRON_SECRET 未在 .env 中配置', 'ERROR');
    die('CRON_SECRET not configured.');
}
$authorized = false;
if (isset($_GET['secret']) && hash_equals($cron_secret, (string) $_GET['secret'])) {
    $authorized = true;
} elseif (PHP_SAPI === 'cli' && isset($argv[1]) && hash_equals($cron_secret, (string) $argv[1])) {
    $authorized = true;
}
if (!$authorized) {
    writeLog('未授权访问尝试', 'ERROR', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ]);
    die("Unauthorized access.");
}

// 处理Web访问
if (WebHandler::isWebAccess() && ASYNC_MODE) {
    // 设置Web访问超时
    WebHandler::setWebTimeout();
    
    // 发送异步响应
    WebHandler::sendAsyncResponse();
    
    // 重新设置执行时间限制为完整时间
    set_time_limit(MAX_EXECUTION_TIME);
    ini_set('max_execution_time', MAX_EXECUTION_TIME);
}

// 检查脚本是否已在运行
if (checkLock()) {
    writeLog('脚本已在运行中，退出', 'WARNING');
    die("Script is already running.");
}

// 创建锁文件
createLock();

// 注册脚本结束时的清理函数
register_shutdown_function(function() {
    releaseLock();
    writeLog('脚本执行完成，释放锁文件');
});

$scriptStartTime = microtime(true);
writeLog('开始执行爱发电订单更新脚本', 'INFO', [
    'version' => '2.1',
    'php_version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'is_web_access' => WebHandler::isWebAccess(),
    'async_mode' => ASYNC_MODE
]);

// 连接到数据库
$dbConnectStart = microtime(true);
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 30,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    Logger::logPerformance('database_connection', $dbConnectStart);
    writeLog('数据库连接成功', 'INFO', [
        'server' => $servername,
        'database' => $dbname,
        'connection_time_ms' => round((microtime(true) - $dbConnectStart) * 1000, 2)
    ]);
} catch (PDOException $e) {
    writeLog('数据库连接失败', 'ERROR', [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'server' => $servername,
        'database' => $dbname
    ]);
    releaseLock();
    die("数据库连接失败: " . $e->getMessage());
}

/**
 * 获取需要更新的用户（分批处理）
 * 只处理 afdian_token 字段有内容的用户
 * @param PDO $conn 数据库连接
 * @param int $offset 偏移量
 * @param int $limit 限制数量
 * @return array 用户列表
 */
function getUsersBatch($conn, $offset = 0, $limit = BATCH_SIZE) {
    $stmt = $conn->prepare("SELECT id, afdian_user_id, afdian_token FROM users WHERE role >= 2 AND afdian_user_id IS NOT NULL AND afdian_user_id != '' AND afdian_token IS NOT NULL AND afdian_token != '' LIMIT :offset, :limit");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取用户总数
 * 只统计 afdian_token 字段有内容的用户
 * @param PDO $conn 数据库连接
 * @return int 用户总数
 */
function getTotalUsers($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role >= 2 AND afdian_user_id IS NOT NULL AND afdian_user_id != '' AND afdian_token IS NOT NULL AND afdian_token != ''");
    $stmt->execute();
    return $stmt->fetchColumn();
}

/**
 * 根据产品信息判断产品类型
 * @param string $product_name 产品名称
 * @param array $sku_detail_array SKU详情数组
 * @return string 产品类型：watchface, quickapp, mixed
 */
function determineProductType($product_name, $sku_detail_array) {
    $product_type = 'watchface'; // 默认为表盘
    
    // 检查产品名称或SKU详情中是否包含快应用相关关键词
    $quickapp_keywords = ['快应用', 'quickapp', 'quick app', '应用', 'app'];
    $search_text = strtolower($product_name . ' ' . json_encode($sku_detail_array, JSON_UNESCAPED_UNICODE));
    
    // 先检查是否包含表盘关键词
    $watchface_keywords = ['表盘', 'watchface', 'watch face', '表面'];
    $has_watchface = false;
    foreach ($watchface_keywords as $keyword) {
        if (strpos($search_text, strtolower($keyword)) !== false) {
            $has_watchface = true;
            break;
        }
    }
    
    // 检查是否包含快应用关键词（排除与表盘相关的组合）
    $has_quickapp = false;
    foreach ($quickapp_keywords as $keyword) {
        if (strpos($search_text, strtolower($keyword)) !== false) {
            // 特殊处理：如果是"watch face"这样的组合，不应该被"face"中的"app"误判
            if ($keyword === 'app' && strpos($search_text, 'watch face') !== false) {
                continue;
            }
            $has_quickapp = true;
            break;
        }
    }
    
    // 根据检测结果确定产品类型
    if ($has_watchface && $has_quickapp) {
        $product_type = 'mixed';
    } elseif ($has_quickapp) {
        $product_type = 'quickapp';
    } else {
        $product_type = 'watchface'; // 默认或仅包含表盘关键词
    }
    
    return $product_type;
}

/**
 * 发送API请求（带重试机制）
 * @param string $url API地址
 * @param array $data 请求数据
 * @param int $retries 重试次数
 * @param string $userId 用户ID（用于日志）
 * @return array|false 响应数据或false
 */
function sendApiRequest($url, $data, $retries = MAX_RETRIES, $userId = '') {
    $requestStart = microtime(true);
    $json_data = json_encode($data);
    $requestId = uniqid('req_');
    
    writeLog("开始API请求", 'DEBUG', [
        'request_id' => $requestId,
        'url' => $url,
        'user_id' => $userId,
        'data_size' => strlen($json_data),
        'max_retries' => $retries
    ]);
    
    for ($i = 0; $i < $retries; $i++) {
        $attemptStart = microtime(true);
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_TIMEOUT => CURL_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data),
                'X-Request-ID: ' . $requestId
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'XFP-OrderUpdate/2.1',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($curl, CURLINFO_TOTAL_TIME);
        $connectTime = curl_getinfo($curl, CURLINFO_CONNECT_TIME);
        $error = curl_error($curl);
        $errno = curl_errno($curl);
        curl_close($curl);
        
        $attemptDuration = round((microtime(true) - $attemptStart) * 1000, 2);
        
        // 记录每次尝试的详细信息
        $attemptContext = [
            'request_id' => $requestId,
            'attempt' => $i + 1,
            'user_id' => $userId,
            'http_code' => $httpCode,
            'total_time_ms' => round($totalTime * 1000, 2),
            'connect_time_ms' => round($connectTime * 1000, 2),
            'attempt_duration_ms' => $attemptDuration,
            'response_size' => $response ? strlen($response) : 0,
            'curl_errno' => $errno,
            'curl_error' => $error
        ];
        
        if ($response && $httpCode == 200) {
            $responseData = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                Logger::logPerformance('api_request_success', $requestStart, [
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'attempts' => $i + 1,
                    'total_duration_ms' => round((microtime(true) - $requestStart) * 1000, 2)
                ]);
                
                writeLog("API请求成功", 'DEBUG', $attemptContext);
                return $responseData;
            } else {
                $attemptContext['json_error'] = json_last_error_msg();
                writeLog("API响应JSON解析失败", 'ERROR', $attemptContext);
            }
        } else {
            $level = ($i == $retries - 1) ? 'ERROR' : 'WARNING';
            writeLog("API请求失败 (尝试 " . ($i + 1) . "/{$retries})", $level, $attemptContext);
        }
        
        // 重试前等待，使用指数退避
        if ($i < $retries - 1) {
            $waitTime = min(pow(2, $i), 8); // 最大等待8秒
            sleep($waitTime);
        }
    }
    
    // 记录最终失败
    Logger::logPerformance('api_request_failed', $requestStart, [
        'request_id' => $requestId,
        'user_id' => $userId,
        'total_attempts' => $retries,
        'total_duration_ms' => round((microtime(true) - $requestStart) * 1000, 2)
    ]);
    
    return false;
}

// 获取用户总数
$getUsersStart = microtime(true);
$totalUsers = getTotalUsers($conn);
Logger::logPerformance('get_total_users', $getUsersStart);

writeLog("发现需要更新的用户", 'INFO', [
    'total_users' => $totalUsers,
    'query_duration_ms' => round((microtime(true) - $getUsersStart) * 1000, 2)
]);

if ($totalUsers === 0) {
    writeLog('没有需要更新的用户', 'INFO');
    Logger::logPerformance('script_execution', $scriptStartTime, [
        'total_users' => 0,
        'processed_users' => 0,
        'total_orders' => 0,
        'exit_reason' => 'no_users_to_process'
    ]);
    exit;
}

$processedUsers = 0;
$totalOrders = 0;
$totalApiRequests = 0;
$totalDbOperations = 0;
$startTime = time();

/**
 * 处理单个用户的订单数据
 * @param PDO $conn 数据库连接
 * @param array $user 用户信息
 * @return int 处理的订单数量
 */
function processUserOrders($conn, $user) {
    $userProcessStart = microtime(true);
    $system_user_id = $user['id'];
    $afdian_user_id = $user['afdian_user_id'];
    $afdian_token = $user['afdian_token'];
    
    writeLog("开始处理用户订单数据", 'INFO', [
        'system_user_id' => $system_user_id,
        'afdian_user_id' => $afdian_user_id
    ]);
    
    $ts = time();
    $totalProcessed = 0;
    $apiRequestCount = 0;
    $dbOperationCount = 0;
    
    // 准备批量插入语句
    $insert_stmt = $conn->prepare(
        "INSERT INTO xfp_order (out_trade_no, user_id, afdian_user_id, system_user_id, total_amount, remark, discount, sku_detail, product_name, plan_id, product_type)
         VALUES (:out_trade_no, :user_id, :afdian_user_id, :system_user_id, :total_amount, :remark, :discount, :sku_detail, :product_name, :plan_id, :product_type)
         ON DUPLICATE KEY UPDATE 
         total_amount = VALUES(total_amount), 
         remark = VALUES(remark), 
         discount = VALUES(discount), 
         sku_detail = VALUES(sku_detail), 
         product_name = VALUES(product_name), 
         plan_id = VALUES(plan_id),
         product_type = VALUES(product_type)"
    );
    
    // 只获取第一页（最新50条订单数据）
    $params = ["page" => 1];
    $kv_string = "params" . json_encode($params) . "ts" . $ts . "user_id" . $afdian_user_id;
    $sign = md5($afdian_token . $kv_string);
    $request_data = [
        "user_id" => $afdian_user_id,
        "params" => json_encode($params),
        "ts" => $ts,
        "sign" => $sign
    ];
    
    $apiRequestCount++;
    $data = sendApiRequest("https://afdian.com/api/open/query-order", $request_data, MAX_RETRIES, $system_user_id);
    
    if (!$data) {
        writeLog("API请求失败，跳过用户", 'ERROR', [
            'system_user_id' => $system_user_id,
            'afdian_user_id' => $afdian_user_id,
            'api_request_count' => $apiRequestCount
        ]);
        return 0;
    }
    
    if (!isset($data['ec']) || $data['ec'] != 200) {
        writeLog("查询订单失败", 'ERROR', [
            'system_user_id' => $system_user_id,
            'afdian_user_id' => $afdian_user_id,
            'error_code' => $data['ec'] ?? 'unknown',
            'error_message' => $data['em'] ?? '未知错误',
            'api_request_count' => $apiRequestCount
        ]);
        return 0;
    }
    
    $page_sponsors = $data['data']['list'] ?? [];
    if (empty($page_sponsors)) {
        writeLog("用户无订单数据", 'INFO', [
            'system_user_id' => $system_user_id,
            'afdian_user_id' => $afdian_user_id
        ]);
        return 0;
    }
        
    // 分批处理订单数据（最新50条）
    $orderBatches = array_chunk($page_sponsors, ORDER_BATCH_SIZE);
    foreach ($orderBatches as $batchIndex => $batch) {
        $batchStart = microtime(true);
        $dbOperationCount++;
        
        $conn->beginTransaction();
        try {
            $batchProcessed = 0;
            foreach ($batch as $sponsor) {
                $sku_detail = json_encode($sponsor['sku_detail'] ?? [], JSON_UNESCAPED_UNICODE);
                $product_name = $sponsor['product_name'] ?? '';
                
                // 根据产品名称和SKU详情判断产品类型
                $sku_detail_array = $sponsor['sku_detail'] ?? [];
                $product_type = determineProductType($product_name, $sku_detail_array);
                
                $insert_stmt->execute([
                    ':out_trade_no' => $sponsor['out_trade_no'],
                    ':user_id' => $sponsor['user_id'],
                    ':afdian_user_id' => $afdian_user_id,
                    ':system_user_id' => $system_user_id,
                    ':total_amount' => $sponsor['total_amount'],
                    ':remark' => $sponsor['remark'] ?? '',
                    ':discount' => $sponsor['discount'] ?? 0,
                    ':sku_detail' => $sku_detail,
                    ':product_name' => $product_name,
                    ':plan_id' => $sponsor['plan_id'] ?? '',
                    ':product_type' => $product_type
                ]);
                $batchProcessed++;
                $totalProcessed++;
            }
            $conn->commit();
            
            Logger::logPerformance('database_batch_insert', $batchStart, [
                'system_user_id' => $system_user_id,
                'batch_index' => $batchIndex,
                'batch_size' => $batchProcessed,
                'total_processed' => $totalProcessed
            ]);
            
            writeLog("批量处理订单成功", 'INFO', [
                'system_user_id' => $system_user_id,
                'batch_size' => $batchProcessed,
                'batch_duration_ms' => round((microtime(true) - $batchStart) * 1000, 2)
            ]);
            
        } catch (PDOException $e) {
            $conn->rollback();
            writeLog("批量处理订单失败", 'ERROR', [
                'system_user_id' => $system_user_id,
                'batch_index' => $batchIndex,
                'batch_size' => count($batch),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'sql_state' => $e->errorInfo[0] ?? 'unknown'
            ]);
        }
    }
    
    // 记录用户处理完成的性能统计
    Logger::logPerformance('user_orders_processing', $userProcessStart, [
        'system_user_id' => $system_user_id,
        'afdian_user_id' => $afdian_user_id,
        'total_orders_processed' => $totalProcessed,
        'api_request_count' => $apiRequestCount,
        'db_operation_count' => $dbOperationCount,
        'latest_orders_only' => true
    ]);
    
    writeLog("用户订单处理完成（仅最新50条）", 'INFO', [
        'system_user_id' => $system_user_id,
        'afdian_user_id' => $afdian_user_id,
        'total_orders_processed' => $totalProcessed,
        'api_request_count' => $apiRequestCount,
        'db_operation_count' => $dbOperationCount,
        'pages_processed' => $page - 1,
        'processing_duration_ms' => round((microtime(true) - $userProcessStart) * 1000, 2)
    ]);
    
    return $totalProcessed;
}

// 分批处理用户
$offset = 0;
while (true) {
    // 检查执行时间
    if (time() - $startTime > MAX_EXECUTION_TIME - 60) {
        writeLog('接近最大执行时间，停止处理', 'WARNING');
        break;
    }
    
    $users = getUsersBatch($conn, $offset, BATCH_SIZE);
    if (empty($users)) {
        break;
    }
    
    writeLog("开始处理用户批次", 'INFO', [
        'batch_start' => $offset + 1,
        'batch_end' => $offset + count($users),
        'batch_size' => count($users),
        'total_users' => $totalUsers
    ]);
    
    foreach ($users as $userIndex => $user) {
        $userStartTime = microtime(true);
        $userOrders = processUserOrders($conn, $user);
        $totalOrders += $userOrders;
        $processedUsers++;
        
        // 记录单个用户处理统计
        writeLog("用户处理完成", 'INFO', [
            'batch_user_index' => $userIndex + 1,
            'global_user_index' => $processedUsers,
            'system_user_id' => $user['id'],
            'orders_processed' => $userOrders,
            'user_processing_time_ms' => round((microtime(true) - $userStartTime) * 1000, 2)
        ]);
        
        // 内存清理和监控
        if ($processedUsers % 10 == 0) {
            $memoryBefore = memory_get_usage(true);
            gc_collect_cycles();
            $memoryAfter = memory_get_usage(true);
            
            writeLog("内存清理完成", 'DEBUG', [
                'processed_users' => $processedUsers,
                'memory_before_mb' => round($memoryBefore / 1024 / 1024, 2),
                'memory_after_mb' => round($memoryAfter / 1024 / 1024, 2),
                'memory_freed_mb' => round(($memoryBefore - $memoryAfter) / 1024 / 1024, 2)
            ]);
        }
    }
    
    $offset += BATCH_SIZE;
    
    // 详细进度报告
    $progress = round(($processedUsers / $totalUsers) * 100, 2);
    $avgOrdersPerUser = $processedUsers > 0 ? round($totalOrders / $processedUsers, 2) : 0;
    $elapsedTime = time() - $startTime;
    $estimatedTotalTime = $processedUsers > 0 ? round($elapsedTime * $totalUsers / $processedUsers) : 0;
    $estimatedRemainingTime = max(0, $estimatedTotalTime - $elapsedTime);
    
    writeLog("批次处理完成", 'INFO', [
        'progress_percentage' => $progress,
        'processed_users' => $processedUsers,
        'total_users' => $totalUsers,
        'remaining_users' => $totalUsers - $processedUsers,
        'total_orders_processed' => $totalOrders,
        'avg_orders_per_user' => $avgOrdersPerUser,
        'elapsed_time_seconds' => $elapsedTime,
        'estimated_remaining_seconds' => $estimatedRemainingTime,
        'current_memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
    ]);
}

// 执行完成统计
$scriptEndTime = microtime(true);
$totalExecutionTime = $scriptEndTime - $scriptStartTime;
$mainLoopTime = time() - $startTime;
$avgTimePerUser = $processedUsers > 0 ? round($mainLoopTime / $processedUsers, 2) : 0;
$avgOrdersPerUser = $processedUsers > 0 ? round($totalOrders / $processedUsers, 2) : 0;
$finalMemoryUsage = memory_get_usage(true);
$peakMemoryUsage = memory_get_peak_usage(true);

// 记录最终性能统计
Logger::logPerformance('script_execution', $scriptStartTime, [
    'total_users' => $totalUsers,
    'processed_users' => $processedUsers,
    'total_orders' => $totalOrders,
    'total_api_requests' => $totalApiRequests,
    'total_db_operations' => $totalDbOperations,
    'avg_time_per_user_seconds' => $avgTimePerUser,
    'avg_orders_per_user' => $avgOrdersPerUser,
    'final_memory_mb' => round($finalMemoryUsage / 1024 / 1024, 2),
    'peak_memory_mb' => round($peakMemoryUsage / 1024 / 1024, 2),
    'completion_status' => $processedUsers >= $totalUsers ? 'complete' : 'partial'
]);

writeLog("=== 脚本执行完成统计 ===", 'INFO', [
    'total_execution_time_seconds' => round($totalExecutionTime, 2),
    'main_loop_time_seconds' => $mainLoopTime,
    'processed_users' => $processedUsers,
    'total_users' => $totalUsers,
    'completion_percentage' => round(($processedUsers / max($totalUsers, 1)) * 100, 2),
    'total_orders_processed' => $totalOrders,
    'avg_time_per_user_seconds' => $avgTimePerUser,
    'avg_orders_per_user' => $avgOrdersPerUser,
    'final_memory_mb' => round($finalMemoryUsage / 1024 / 1024, 2),
    'peak_memory_mb' => round($peakMemoryUsage / 1024 / 1024, 2)
]);

if ($processedUsers < $totalUsers) {
    $unprocessedUsers = $totalUsers - $processedUsers;
    writeLog("部分用户未处理完成", 'WARNING', [
        'unprocessed_users' => $unprocessedUsers,
        'unprocessed_percentage' => round(($unprocessedUsers / $totalUsers) * 100, 2),
        'reason' => 'time_limit_reached',
        'suggestion' => 'consider_increasing_execution_time_or_reducing_batch_size'
    ]);
}

// 清理内存和资源
$memoryBeforeCleanup = memory_get_usage(true);
gc_collect_cycles();
$memoryAfterCleanup = memory_get_usage(true);

writeLog("资源清理完成", 'INFO', [
    'memory_before_cleanup_mb' => round($memoryBeforeCleanup / 1024 / 1024, 2),
    'memory_after_cleanup_mb' => round($memoryAfterCleanup / 1024 / 1024, 2),
    'memory_freed_mb' => round(($memoryBeforeCleanup - $memoryAfterCleanup) / 1024 / 1024, 2)
]);

// 关闭数据库连接
$conn = null;

writeLog('爱发电订单更新脚本执行完成', 'INFO', [
    'script_version' => '2.1',
    'execution_mode' => WebHandler::isWebAccess() ? 'web' : 'cli',
    'async_mode' => ASYNC_MODE
]);

// Web访问结果输出（仅在非异步模式或CLI模式下）
if (WebHandler::isWebAccess() && !ASYNC_MODE) {
    $completionStatus = $processedUsers >= $totalUsers ? '完成' : '部分完成';
    $statusColor = $processedUsers >= $totalUsers ? 'green' : 'orange';
    
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>订单更新结果</title>";
    echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .status{color:{$statusColor};font-weight:bold;} .stats{background:#f5f5f5;padding:15px;border-radius:5px;margin:10px 0;} .warning{color:orange;} .error{color:red;}</style>";
    echo "</head><body>";
    echo "<h2>爱发电订单更新任务结果</h2>";
    echo "<div class='stats'>";
    echo "<p><strong>执行状态:</strong> <span class='status'>{$completionStatus}</span></p>";
    echo "<p><strong>总执行时间:</strong> " . round($totalExecutionTime, 2) . " 秒</p>";
    echo "<p><strong>处理用户数:</strong> {$processedUsers}/{$totalUsers} (" . round(($processedUsers / max($totalUsers, 1)) * 100, 2) . "%)</p>";
    echo "<p><strong>处理订单数:</strong> {$totalOrders}</p>";
    echo "<p><strong>平均处理时间:</strong> {$avgTimePerUser} 秒/用户</p>";
    echo "<p><strong>内存使用:</strong> " . round($peakMemoryUsage / 1024 / 1024, 2) . " MB (峰值)</p>";
    echo "</div>";
    
    if ($processedUsers < $totalUsers) {
        echo "<p class='warning'><strong>注意:</strong> 由于时间限制，还有 " . ($totalUsers - $processedUsers) . " 个用户未处理完成。</p>";
        echo "<p class='warning'>建议调整批处理大小或增加执行时间限制。</p>";
    }
    
    echo "<p><strong>详细日志文件:</strong></p>";
    echo "<ul>";
    echo "<li>主日志: " . LOG_DIR . "/afd_order_update_" . date('Y-m-d') . ".log</li>";
    echo "<li>错误日志: " . LOG_DIR . "/error/afd_order_error_" . date('Y-m-d') . ".log</li>";
    echo "<li>性能日志: " . LOG_DIR . "/performance/afd_order_perf_" . date('Y-m-d') . ".log</li>";
    echo "</ul>";
    echo "<p><small>任务完成时间: " . date('Y-m-d H:i:s') . "</small></p>";
    echo "</body></html>";
}
