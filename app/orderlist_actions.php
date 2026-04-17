<?php
session_start();

// 验证用户是否已登录
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 2;
if ($user_role === null) {
  echo json_encode(['error' => '未登录，请先登录。'], JSON_UNESCAPED_UNICODE);
  header("Location: ../pages/auth/login.php");
  exit;
} elseif ($user_role < $required_role) {
  echo json_encode(['error' => '权限不足，无法访问该页面。'], JSON_UNESCAPED_UNICODE);
  header("Location: ../index.php");
  exit;
}

require_once './config.php';

$user_id = $_SESSION['user_id'];

// 使用config.php中的数据库连接
if (isset($mysqli_conn) && $mysqli_conn !== null) {
  $db_connection = $mysqli_conn;
} else {
  // 如果mysqli不可用，使用PDO模拟mysqli接口
  class PDOMysqliWrapper {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function prepare($sql) { return new PDOStatementWrapper($this->pdo->prepare($sql)); }
    public function close() { $this->pdo = null; }
  }
  class PDOStatementWrapper {
    private $stmt;
    public function __construct($stmt) { $this->stmt = $stmt; }
    public function bind_param($types, ...$params) { 
      for($i = 0; $i < count($params); $i++) {
        $this->stmt->bindValue($i + 1, $params[$i]);
      }
      return true;
    }
    public function execute() { return $this->stmt->execute(); }
    public function get_result() { return new PDOResultWrapper($this->stmt); }
    public function close() { $this->stmt = null; }
  }
  class PDOResultWrapper {
    private $stmt;
    private $rows;
    private $index = 0;
    public function __construct($stmt) { 
      $this->stmt = $stmt; 
      $this->rows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function fetch_assoc() { 
      if ($this->index < count($this->rows)) {
        return $this->rows[$this->index++];
      }
      return false;
    }
    public function num_rows() { return count($this->rows); }
  }
  $db_connection = new PDOMysqliWrapper($conn);
}
$conn = $db_connection;

// 根据请求操作执行相应的函数
$action = $_POST['action'] ?? '';

switch ($action) {
  case 'fetchOrders':
    fetchOrders($db_connection, $user_id);
    break;
  case 'get':
    getOrder($conn);
    break;
  case 'update':
    updateOrder($conn);
    break;
  case 'batch_update':
    batchUpdateOrders($conn);
    break;
  case 'get_plans':
    getPlans($conn);
    break;
  case 'cleanup_orders':
    cleanupOrders($conn, $user_id);
    break;
  default:
    echo '无效操作';
    break;
}

$conn->close();

// 获取订单列表（支持分页）
function fetchOrders($db_connection, $user_id)
{
  $query = $_POST['query'] ?? '';
  $planId = $_POST['plan_id'] ?? '';
  $page = max(1, intval($_POST['page'] ?? 1)); // 当前页码，默认第1页
  $perPage = max(1, min(100, intval($_POST['per_page'] ?? 20))); // 每页数量，默认20条，最大100条
  
  // 计算偏移量
  $offset = ($page - 1) * $perPage;
  
  // 构建基础查询条件
  $whereConditions = "system_user_id = ? AND out_trade_no LIKE ?";
  $params = [$user_id, "%$query%"];
  $types = 'is';

  if ($planId !== '') {
    $whereConditions .= " AND plan_id = ?";
    $params[] = $planId;
    $types .= 's';
  }

  // 获取总记录数
  $countSql = "SELECT COUNT(*) as total FROM xfp_order WHERE $whereConditions";
  $countStmt = $db_connection->prepare($countSql);
  $countStmt->bind_param($types, ...$params);
  $countStmt->execute();
  $countResult = $countStmt->get_result();
  $totalRecords = $countResult->fetch_assoc()['total'];
  $countStmt->close();
  
  // 计算总页数
  $totalPages = ceil($totalRecords / $perPage);
  
  // 获取分页数据（直接拼接LIMIT和OFFSET，因为PDO不支持在这些子句中使用占位符）
  $sql = "SELECT * FROM xfp_order WHERE $whereConditions ORDER BY out_trade_no DESC LIMIT " . intval($perPage) . " OFFSET " . intval($offset);
  
  $stmt = $db_connection->prepare($sql);
  if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $result = $stmt->get_result();

  $tableRows = '';
  while ($row = $result->fetch_assoc()) {
    $skuDetails = json_decode($row['sku_detail'], true);
    $skuOutput = '<div class="flex flex-wrap gap-2">';
    if (is_array($skuDetails)) {
      foreach ($skuDetails as $sku) {
        $skuOutput .= '<div class="bg-gray-900 rounded-lg p-2 flex items-center gap-2 shadow" style="min-width:220px;max-width:320px;">';
        $skuOutput .= '<img src="' . htmlspecialchars($sku['pic'] ?? '') . '" alt="SKU图片" class="h-16 w-16 object-cover rounded">';
        $skuOutput .= '<div class="flex flex-col min-w-0">';
        $skuOutput .= '<div class="font-semibold text-white text-sm truncate max-w-[180px]" title="' . htmlspecialchars($sku['name'] ?? '') . '">名称: ' . htmlspecialchars($sku['name'] ?? '') . '</div>';
        $skuOutput .= '<div class="text-xs text-gray-300 truncate max-w-[180px]" title="' . htmlspecialchars($sku['sku_id'] ?? '') . '">SKU: ' . htmlspecialchars($sku['sku_id'] ?? '') . '</div>';
        $skuOutput .= '<div class="text-xs text-gray-300">价格: ￥' . htmlspecialchars($sku['price'] ?? '') . '</div>';
        $skuOutput .= '<div class="text-xs text-gray-300">数量: ' . htmlspecialchars($sku['count'] ?? '') . '</div>';
        $skuOutput .= '</div></div>';
      }
    }
    $skuOutput .= '</div>';

    $tableRows .= '<tr>';
    $tableRows .= '<td><input type="checkbox" data-order-id="' . htmlspecialchars($row['out_trade_no']) . '"></td>';
    $tableRows .= '<td>' . htmlspecialchars($row['out_trade_no']) . '</td>';
    $tableRows .= '<td>' . htmlspecialchars($row['user_id']) . '</td>';
    $tableRows .= '<td>' . htmlspecialchars($row['afdian_user_id']) . '</td>';
    $tableRows .= '<td>' . htmlspecialchars($row['system_user_id']) . '</td>';
    $tableRows .= '<td>' . $row['total_amount'] . '</td>';
    $tableRows .= '<td>' . $row['downloads_limit'] . '</td>';
    $tableRows .= '<td>' . $skuOutput . '</td>';
    $tableRows .= '<td>' . htmlspecialchars($row['plan_id']) . '</td>';
    $tableRows .= '<td><button class="editBtn btn btn-primary flex items-center gap-1 px-3 py-1 text-sm font-semibold rounded-button hover:bg-opacity-90 transition-colors" data-order-id="' . htmlspecialchars($row['out_trade_no']) . '"><i class="fa fa-edit"></i> 编辑</button></td>';
    $tableRows .= '</tr>';
  }
  
  $stmt->close();

  // 返回JSON格式的分页数据
  $response = [
    'table_rows' => $tableRows,
    'pagination' => [
      'current_page' => $page,
      'per_page' => $perPage,
      'total_records' => $totalRecords,
      'total_pages' => $totalPages,
      'has_prev' => $page > 1,
      'has_next' => $page < $totalPages
    ]
  ];
  
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

// 获取单个订单信息
function getOrder($conn)
{
  $orderId = $_POST['order_id'];
  $sql = "SELECT * FROM xfp_order WHERE out_trade_no = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('s', $orderId);
  $stmt->execute();
  $result = $stmt->get_result();
  $order = $result->fetch_assoc();

  echo json_encode($order);
}

// 更新订单信息（主要是下载限制）
function updateOrder($conn)
{
  $orderId = $_POST['order_id'];
  $downloadsLimit = $_POST['downloads_limit'];

  $sql = "UPDATE xfp_order SET downloads_limit = ? WHERE out_trade_no = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('is', $downloadsLimit, $orderId);

  if ($stmt->execute()) {
    echo '订单信息已更新';
  } else {
    echo '更新失败';
  }
}

// 批量更新订单信息
function batchUpdateOrders($conn)
{
  $orderIds = $_POST['order_ids'] ?? [];
  $downloadsLimit = $_POST['downloads_limit'];

  if (empty($orderIds)) {
    echo '没有选择任何订单';
    return;
  }

  $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
  $sql = "UPDATE xfp_order SET downloads_limit = ? WHERE out_trade_no IN ($placeholders)";
  $stmt = $conn->prepare($sql);

  $params = array_merge([$downloadsLimit], $orderIds);
  $types = str_repeat('s', count($orderIds)) . 'i'; // 参数类型字符串

  $stmt->bind_param($types, ...$params);

  if ($stmt->execute()) {
    echo '批量更新成功';
  } else {
    echo '批量更新失败';
  }
}

// 获取计划ID选项（包含缩略图）
function getPlans($conn)
{
  $user_id = $_SESSION['user_id'];
  
  // 查询用户的所有计划ID
  $sql = "SELECT DISTINCT o.plan_id, o.sku_detail FROM xfp_order o WHERE o.system_user_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $plans = [];
  $seen_plan_ids = []; // 用于记录已处理的plan_id，避免重复
  
  while ($row = $result->fetch_assoc()) {
    $plan_id = $row['plan_id'];
    
    // 如果该plan_id已经处理过，跳过
    if (in_array($plan_id, $seen_plan_ids)) {
      continue;
    }
    
    $seen_plan_ids[] = $plan_id; // 记录已处理的plan_id
    $thumbnail = '';
    
    // 首先尝试从xfp_wflist表获取缩略图
    $img_sql = "SELECT image_link FROM xfp_wflist WHERE plan_id = ? AND image_link IS NOT NULL AND image_link != '' LIMIT 1";
    $img_stmt = $conn->prepare($img_sql);
    $img_stmt->bind_param('s', $plan_id);
    $img_stmt->execute();
    $img_result = $img_stmt->get_result();
    
    if ($img_row = $img_result->fetch_assoc()) {
      $thumbnail = $img_row['image_link'];
    } else {
      // 如果xfp_wflist中没有图片，尝试从sku_detail获取
      $sku_detail = json_decode($row['sku_detail'], true);
      if (is_array($sku_detail) && isset($sku_detail[0]['pic']) && !empty($sku_detail[0]['pic'])) {
        $thumbnail = $sku_detail[0]['pic'];
      }
    }
    $img_stmt->close();
    
    $plans[] = [
      'plan_id' => $plan_id,
      'thumbnail' => $thumbnail
    ];
  }

  echo json_encode($plans);
}

/**
 * 清理指定天数前的订单记录
 * @param mysqli $conn 数据库连接
 * @param int $user_id 用户ID
 */
function cleanupOrders($conn, $user_id)
{
  $days = $_POST['days'] ?? 0;
  
  // 验证输入参数
  if (!is_numeric($days) || $days < 1 || $days > 365) {
    echo '清理天数必须在1-365之间';
    return;
  }
  
  try {
    // 开始事务
    $conn->autocommit(false);
    
    // 计算截止日期（根据订单号格式：202508311703365610049513993）
    $cutoffDate = date('Ymd', strtotime("-{$days} days"));
    
    // 先查询要删除的订单数量（根据订单号前8位判断日期）
    $countSql = "SELECT COUNT(*) as count FROM xfp_order WHERE system_user_id = ? AND SUBSTRING(out_trade_no, 1, 8) < ?";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        throw new Exception('准备查询语句失败: ' . $conn->error);
    }
    $countStmt->bind_param('is', $user_id, $cutoffDate);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $deleteCount = $countRow['count'];
    $countStmt->close();
    
    if ($deleteCount == 0) {
      echo "没有找到{$days}天前的订单记录";
      $conn->rollback();
      return;
    }
    
    // 执行删除操作（根据订单号前8位判断日期）
    $deleteSql = "DELETE FROM xfp_order WHERE system_user_id = ? AND SUBSTRING(out_trade_no, 1, 8) < ?";
    $deleteStmt = $conn->prepare($deleteSql);
    if (!$deleteStmt) {
        throw new Exception('准备删除语句失败: ' . $conn->error);
    }
    $deleteStmt->bind_param('is', $user_id, $cutoffDate);
    
    if ($deleteStmt->execute()) {
      $actualDeleted = $deleteStmt->affected_rows;
      $deleteStmt->close();
      
      // 提交事务
      $conn->commit();
      
      // 记录清理日志
      $logMessage = sprintf(
        "[订单清理] 用户ID: %d, 清理天数: %d, 截止日期: %s, 删除订单数: %d",
        $user_id,
        $days,
        $cutoffDate,
        $actualDeleted
      );
      
      error_log($logMessage, 3, '../logs/order_cleanup.log');
      
      echo "清理完成！已删除 {$actualDeleted} 条{$days}天前的订单记录";
    } else {
      $deleteStmt->close();
      $conn->rollback();
      echo '清理操作失败，请稍后重试';
    }
    
  } catch (Exception $e) {
    $conn->rollback();
    error_log("[订单清理错误] 用户ID: {$user_id}, 错误: " . $e->getMessage(), 3, '../logs/order_cleanup.log');
    echo '清理操作出现错误，请联系管理员';
  } finally {
    // 恢复自动提交
    $conn->autocommit(true);
  }
}
