<?php
session_start();

// 验证用户是否已登录
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 3;
if ($user_role === null) {
  echo json_encode(['error' => '未登录，请先登录。'], JSON_UNESCAPED_UNICODE);
  header("Location: ../pages/auth/login.php");
  exit;
} elseif ($user_role < $required_role) {
  echo json_encode(['error' => '权限不足，无法访问该页面。'], JSON_UNESCAPED_UNICODE);
  header("Location: ../index.php");
  exit;
}

require_once '../config.php';

// 连接数据库
$conn = new mysqli($servername, $db_user, $db_pass, $dbname);

// 检查连接
if ($conn->connect_error) {
  die("连接失败: " . $conn->connect_error);
}

// 根据请求操作执行相应的函数
$action = $_POST['action'] ?? '';

switch ($action) {
  case 'fetch':
    fetchOrders($conn);
    break;
  case 'get_plans':
    getPlans($conn);
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
  default:
    echo json_encode(['error' => '无效的请求']);
    break;
}

$conn->close();

// 获取订单列表
function fetchOrders($conn)
{
  $query = $_POST['query'] ?? '';
  $planId = $_POST['plan_id'] ?? '';
  $systemUserId = $_POST['system_user_id'] ?? '';
  $afdianUserId = $_POST['afdian_user_id'] ?? '';
  $page = $_POST['page'] ?? 1;
  $limit = 50; // 每页显示的数量
  $offset = ($page - 1) * $limit;

  // 基础 SQL 查询
  $sql = "SELECT * FROM xfp_order WHERE out_trade_no LIKE ?";
  $params = ["%$query%"];
  $types = 's';

  // 根据筛选条件添加 SQL 查询条件
  if ($planId !== '') {
    $sql .= " AND plan_id = ?";
    $params[] = $planId;
    $types .= 's';
  }

  if ($systemUserId !== '') {
    $sql .= " AND system_user_id = ?";
    $params[] = $systemUserId;
    $types .= 's';
  }

  if ($afdianUserId !== '') {
    $sql .= " AND afdian_user_id = ?";
    $params[] = $afdianUserId;
    $types .= 's';
  }

  $sql .= " ORDER BY out_trade_no DESC LIMIT ? OFFSET ?";
  $params[] = $limit;
  $params[] = $offset;
  $types .= 'ii';

  // 准备和执行 SQL 查询
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();

  $output = '';
  while ($row = $result->fetch_assoc()) {
    // 解码 SKU 详情 JSON 数据
    $skuDetails = json_decode($row['sku_detail'], true);
    $skuOutput = '<ul>';
    foreach ($skuDetails as $sku) {
      $skuOutput .= '<li>';
      $skuOutput .= 'SKU ID: ' . htmlspecialchars($sku['sku_id']) . '<br>';
      $skuOutput .= '价格: ' . htmlspecialchars($sku['price']) . '<br>';
      $skuOutput .= '数量: ' . htmlspecialchars($sku['count']) . '<br>';
      $skuOutput .= '名称: ' . htmlspecialchars($sku['name']) . '<br>';
      $skuOutput .= '图片: <img src="' . htmlspecialchars($sku['pic']) . '" alt="SKU 图片" class="sku-image"><br>';
      $skuOutput .= '</li>';
    }
    $skuOutput .= '</ul>';

    // 输出订单信息
    $output .= '<tr>';
    $output .= '<td><input type="checkbox" data-order-id="' . htmlspecialchars($row['out_trade_no']) . '"></td>';
    $output .= '<td>' . htmlspecialchars($row['out_trade_no']) . '</td>';
    $output .= '<td>' . htmlspecialchars($row['sponsor_user_id']) . '</td>';
    $output .= '<td>' . htmlspecialchars($row['afdian_user_id']) . '</td>';
    $output .= '<td>' . htmlspecialchars($row['system_user_id']) . '</td>';
    $output .= '<td>' . $row['total_amount'] . '</td>';
    $output .= '<td>' . $row['downloads_limit'] . '</td>';
    $output .= '<td>' . $skuOutput . '</td>'; // 显示 SKU 详情
    $output .= '<td>' . htmlspecialchars($row['plan_id']) . '</td>';
    $output .= '<td><button class="editBtn" data-order-id="' . htmlspecialchars($row['out_trade_no']) . '">编辑</button></td>';
    $output .= '</tr>';
  }

  // 获取总记录数
  $sqlCount = "SELECT COUNT(*) as total FROM xfp_order WHERE out_trade_no LIKE ?";
  $stmtCount = $conn->prepare($sqlCount);
  $stmtCount->bind_param('s', $params[0]);
  $stmtCount->execute();
  $resultCount = $stmtCount->get_result();
  $totalRecords = $resultCount->fetch_assoc()['total'];
  $totalPages = ceil($totalRecords / $limit);

  echo json_encode([
    'orders' => $output,
    'totalPages' => $totalPages
  ]);
}

function getPlans($conn)
{
  $sql = "SELECT DISTINCT plan_id FROM xfp_order";
  $result = $conn->query($sql);
  $plans = [];
  while ($row = $result->fetch_assoc()) {
    $plans[] = $row;
  }
  echo json_encode($plans);
}

function getOrder($conn)
{
  $order_id = $_POST['order_id'];
  $sql = "SELECT * FROM xfp_order WHERE out_trade_no = '$order_id'";
  $result = $conn->query($sql);
  $order = $result->fetch_assoc();
  echo json_encode($order);
}

function updateOrder($conn)
{
  $order_id = $_POST['order_id'];
  $downloads_limit = $_POST['downloads_limit'];
  $sql = "UPDATE xfp_order SET downloads_limit = '$downloads_limit' WHERE out_trade_no = '$order_id'";
  if ($conn->query($sql) === TRUE) {
    echo "更新成功";
  } else {
    echo "更新失败: " . $conn->error;
  }
}

function batchUpdateOrders($conn)
{
  $order_ids = $_POST['order_ids'];
  $downloads_limit = $_POST['downloads_limit'];
  $order_ids_str = implode("','", $order_ids);
  $sql = "UPDATE xfp_order SET downloads_limit = '$downloads_limit' WHERE out_trade_no IN ('$order_ids_str')";
  if ($conn->query($sql) === TRUE) {
    echo "批量更新成功";
  } else {
    echo "批量更新失败: " . $conn->error;
  }
}
