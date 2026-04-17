<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$userId = $_SESSION['user_id'];

// 使用config.php中的mysqli连接，如果不可用则使用PDO
if (isset($mysqli_conn) && $mysqli_conn !== null) {
    $conn = $mysqli_conn;
} else {
    // mysqli不可用，使用PDO模拟mysqli接口
    class PDOWrapper {
        private $pdo;
        
        public function __construct($pdo) {
            $this->pdo = $pdo;
        }
        
        public function prepare($sql) {
            return new PDOStmtWrapper($this->pdo->prepare($sql));
        }
        
        public function close() {
            $this->pdo = null;
        }
    }
    
    class PDOStmtWrapper {
        private $stmt;
        
        public function __construct($stmt) {
            $this->stmt = $stmt;
        }
        
        public function bind_param($types, ...$params) {
            for ($i = 0; $i < count($params); $i++) {
                $this->stmt->bindParam($i + 1, $params[$i]);
            }
        }
        
        public function execute() {
            return $this->stmt->execute();
        }
        
        public function get_result() {
            return new PDOResultWrapper($this->stmt);
        }
        
        public function close() {
            $this->stmt = null;
        }
    }
    
    class PDOResultWrapper {
        private $stmt;
        
        public function __construct($stmt) {
            $this->stmt = $stmt;
        }
        
        public function fetch_assoc() {
            return $this->stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    $conn = new PDOWrapper($conn);
}

if (!$conn) {
    echo json_encode(['success' => false, 'error' => '数据库连接失败']);
    exit;
}

try {
    // 1. 表盘订单总数（仅计算已添加表盘的订单）
    $watchfaceOrderCount = 0;
    $watchfaceOrderStmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.out_trade_no) AS count 
        FROM xfp_order o 
        INNER JOIN xfp_wflist w ON o.plan_id = w.plan_id 
        WHERE o.system_user_id = ? AND w.user_id = ?
    ");
    $watchfaceOrderStmt->bind_param('ii', $userId, $userId);
    $watchfaceOrderStmt->execute();
    $watchfaceOrderResult = $watchfaceOrderStmt->get_result();
    $watchfaceOrderCount = (int)($watchfaceOrderResult->fetch_assoc()['count'] ?? 0);

    // 2. 表盘数量
    $watchfaceCount = 0;
    $watchfaceStmt = $conn->prepare("SELECT COUNT(*) AS count FROM xfp_wflist WHERE user_id = ?");
    $watchfaceStmt->bind_param('i', $userId);
    $watchfaceStmt->execute();
    $watchfaceResult = $watchfaceStmt->get_result();
    $watchfaceCount = (int)($watchfaceResult->fetch_assoc()['count'] ?? 0);

    // 3. 激活率（检查每个表盘订单的下载限制次数是否为0）
    $activationStmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN o.downloads_limit = 0 THEN 1 END) AS zero_limit_count,
            COUNT(*) AS total_orders
        FROM xfp_order o
        INNER JOIN xfp_wflist w ON o.plan_id = w.plan_id 
        WHERE o.system_user_id = ? AND w.user_id = ?
    ");
    $activationStmt->bind_param('ii', $userId, $userId);
    $activationStmt->execute();
    $activationResult = $activationStmt->get_result();
    $activationData = $activationResult->fetch_assoc();

    $zeroLimitCount = (int)($activationData['zero_limit_count'] ?? 0);
    $totalOrders = (int)($activationData['total_orders'] ?? 0);
    $activationRate = ($totalOrders > 0) ? (round(($zeroLimitCount / $totalOrders) * 100, 1) . '%') : '-';

    // 4. 表盘总金额（全部/本月/本周/今日）
    $timeFilter = $_GET['time_filter'] ?? 'all'; // 获取时间筛选参数
    
    $dateCondition = '';
    switch ($timeFilter) {
        case 'month':
            $dateCondition = "AND o.out_trade_no >= '" . date('Ymd') . "000000000000000000000000'";
            break;
        case 'week':
            $weekStart = date('Ymd', strtotime('monday this week'));
            $dateCondition = "AND o.out_trade_no >= '" . $weekStart . "000000000000000000000000'";
            break;
        case 'today':
            $today = date('Ymd');
            $dateCondition = "AND o.out_trade_no >= '" . $today . "000000000000000000000000' AND o.out_trade_no < '" . $today . "999999999999999999999999'";
            break;
        default:
            $dateCondition = '';
    }

    $amountStmt = $conn->prepare("
        SELECT SUM(o.total_amount) AS total_amount
        FROM xfp_order o 
        INNER JOIN xfp_wflist w ON o.plan_id = w.plan_id 
        WHERE o.system_user_id = ? AND w.user_id = ? $dateCondition
    ");
    $amountStmt->bind_param('ii', $userId, $userId);
    $amountStmt->execute();
    $amountResult = $amountStmt->get_result();
    $totalAmount = (float)($amountResult->fetch_assoc()['total_amount'] ?? 0);

    echo json_encode([
        'success' => true,
        'watchfaceOrderCount' => $watchfaceOrderCount,
        'watchfaceCount' => $watchfaceCount,
        'activationRate' => $activationRate,
        'totalAmount' => number_format($totalAmount, 2),
        'timeFilter' => $timeFilter
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => '数据统计异常']);
}
$conn->close();
?>