<?php
/**
 * XFP Activation Key System / XFP 密钥获取系统
 *
 * @author    YcFeller
 * @copyright Copyright (c) 2026 YcFeller
 * @license   AGPL-3.0-or-later
 * @link      https://github.com/YcFeller
 */
require_once __DIR__ . '/load_env.php';

// 检测是否为本地环境（仅用于调试日志等，数据库凭据统一来自 .env）
$http_host = $_SERVER['HTTP_HOST'] ?? '';
$server_name = $_SERVER['SERVER_NAME'] ?? '';
$server_addr = $_SERVER['SERVER_ADDR'] ?? '';

$is_local = php_sapi_name() === 'cli' ||
            in_array($http_host, ['localhost', '127.0.0.1', '::1', 'localhost:8080', '127.0.0.1:8080']) ||
            in_array($server_name, ['localhost', '127.0.0.1', '::1']) ||
            in_array($server_addr, ['127.0.0.1', '::1']) ||
            strpos($http_host, '.local') !== false ||
            strpos($http_host, '.test') !== false ||
            strpos($http_host, 'localhost:') === 0 ||
            strpos($http_host, '127.0.0.1:') === 0;

if (defined('DEBUG_CONFIG') && DEBUG_CONFIG) {
    error_log("Config Debug - HTTP_HOST: {$http_host}, SERVER_NAME: {$server_name}, SERVER_ADDR: {$server_addr}, is_local: " . ($is_local ? 'true' : 'false'));
}

$servername = env('DB_HOST', 'localhost');
$dbname = env('DB_DATABASE', '');
$db_user = env('DB_USERNAME', '');
$db_pass = env('DB_PASSWORD', '');

if ($dbname === '' || $db_user === '') {
    throw new Exception('数据库未配置：请在项目根目录复制 .env.example 为 .env 并填写 DB_DATABASE、DB_USERNAME、DB_PASSWORD。');
}

$all_debug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if (extension_loaded('mysqli')) {
        $mysqli_conn = new mysqli($servername, $db_user, $db_pass, $dbname);
        if ($mysqli_conn->connect_error) {
            $mysqli_conn = null;
        } else {
            $mysqli_conn->set_charset("utf8mb4");
        }
    } else {
        $mysqli_conn = null;
    }
} catch (Exception $e) {
    if ($all_debug) {
        error_log("数据库连接错误: " . $e->getMessage());
    }
    throw new Exception("数据库连接失败: " . $e->getMessage());
}
