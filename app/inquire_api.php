<?php
session_start();

$user_role = $_SESSION['user_role'] ?? null;
$required_role = 1;
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

$order_number = $_POST['order_number'] ?? '';
$captcha = $_POST['captcha'] ?? '';
$activation_type = $_POST['activation_type'] ?? 'watchface'; // 默认为表盘激活

if (empty($order_number)) {
  echo json_encode(['error' => '订单号不能为空']);
  exit;
}

// 验证验证码
if (!isset($_SESSION['captcha']) || $captcha !== $_SESSION['captcha']) {
  // 清除验证码，使其变成一次性使用
  unset($_SESSION['captcha']);
  echo json_encode(['error' => '验证码错误，请重新输入']);
  exit;
}

// 验证成功后立即清除验证码，使其变成一次性使用
unset($_SESSION['captcha']);

// 连接到数据库
$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
  echo json_encode(['error' => '数据库连接失败，请稍后重试']);
  exit;
}

// 获取当前用户的afdian_user_id
$user_id = $_SESSION['user_id'];
$stmt_user = $conn->prepare("SELECT afdian_user_id FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows === 0) {
  echo json_encode(['error' => '用户信息未找到，请重新登录']);
  exit;
}

$user_data = $result_user->fetch_assoc();
$user_afdian_id = $user_data['afdian_user_id'];

if (empty($user_afdian_id)) {
  echo json_encode(['error' => '您的爱发电用户ID未设置，请联系管理员']);
  exit;
}

// 查询订单信息，确保只能查询自己的订单，并检查产品类型
$sql_order = "
    SELECT o.plan_id, o.sku_detail, o.downloads_limit, o.user_id, o.out_trade_no, o.product_type
    FROM xfp_order o
    WHERE o.out_trade_no = ? AND o.user_id = ? AND (o.product_type = ? OR o.product_type IS NULL)
";
$stmt_order = $conn->prepare($sql_order);
$stmt_order->bind_param("sss", $order_number, $user_afdian_id, $activation_type);
$stmt_order->execute();
$result_order = $stmt_order->get_result();

// 如果没有找到对应类型的订单，尝试查找旧订单（product_type为NULL的订单默认为表盘）
if ($result_order->num_rows === 0 && $activation_type === 'watchface') {
    $sql_order_legacy = "
        SELECT o.plan_id, o.sku_detail, o.downloads_limit, o.user_id, o.out_trade_no, o.product_type
        FROM xfp_order o
        WHERE o.out_trade_no = ? AND o.user_id = ? AND o.product_type IS NULL
    ";
    $stmt_order = $conn->prepare($sql_order_legacy);
    $stmt_order->bind_param("ss", $order_number, $user_afdian_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();
}
$result_order = $stmt_order->get_result();

if ($result_order->num_rows === 0) {
  // 检查订单是否存在但不属于当前用户
  $check_sql = "SELECT COUNT(*) as count FROM xfp_order WHERE out_trade_no = ?";
  $check_stmt = $conn->prepare($check_sql);
  $check_stmt->bind_param("s", $order_number);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();
  $check_data = $check_result->fetch_assoc();
  
  if ($check_data['count'] > 0) {
    echo json_encode(['error' => '该订单不属于您，无法查询。请确认订单号是否正确']);
  } else {
    echo json_encode(['error' => '订单号不存在，请检查后重新输入']);
  }
  exit;
}

$row_order = $result_order->fetch_assoc();
$plan_id = $row_order['plan_id'];
$sku_detail = json_decode($row_order['sku_detail'], true);
$downloads_limit = $row_order['downloads_limit'];

// 检查下载次数是否已用完
if ($downloads_limit <= 0) {
  echo json_encode(['error' => '该订单的下载次数已用完，无法继续查询']);
  exit;
}

// 根据激活类型查询对应的产品信息
$products = [];
$has_hidden_product = false;

if ($activation_type === 'watchface') {
    // 查询表盘信息
    $sql_product = "
        SELECT w.name as product_name, w.watchface_id as product_id, w.status, w.image_link
        FROM xfp_wflist w
        WHERE w.plan_id = ?
    ";
    $stmt_product = $conn->prepare($sql_product);
    $stmt_product->bind_param("s", $plan_id);
    $stmt_product->execute();
    $result_product = $stmt_product->get_result();
    
    while ($row_product = $result_product->fetch_assoc()) {
        // 检查产品状态，如果status为0则隐藏
        if ($row_product['status'] == 0) {
            $has_hidden_product = true;
            break;
        }
        
        $products[] = [
            'product_name' => $row_product['product_name'],
            'product_image' => !empty($row_product['image_link']) ? $row_product['image_link'] : ($sku_detail[0]['pic'] ?? ''),
            'status' => $row_product['status'],
            'product_type' => 'watchface'
        ];
    }
    
    $error_message = '该表盘暂时无法激活';
    $not_found_message = '该订单对应的表盘信息未找到，请联系管理员';
    
} elseif ($activation_type === 'quickapp') {
    // 查询快应用信息
    $sql_product = "
        SELECT q.name as product_name, q.quickapp_id as product_id, q.status, q.image_link
        FROM xfp_quickapp_list q
        WHERE q.plan_id = ?
    ";
    $stmt_product = $conn->prepare($sql_product);
    $stmt_product->bind_param("s", $plan_id);
    $stmt_product->execute();
    $result_product = $stmt_product->get_result();
    
    while ($row_product = $result_product->fetch_assoc()) {
        // 检查产品状态，如果status为0则隐藏
        if ($row_product['status'] == 0) {
            $has_hidden_product = true;
            break;
        }
        
        $products[] = [
            'product_name' => $row_product['product_name'],
            'product_image' => !empty($row_product['image_link']) ? $row_product['image_link'] : ($sku_detail[0]['pic'] ?? ''),
            'status' => $row_product['status'],
            'product_type' => 'quickapp'
        ];
    }
    
    $error_message = '该快应用暂时无法激活';
    $not_found_message = '该订单对应的快应用信息未找到，请联系管理员';
} else {
    echo json_encode(['error' => '不支持的激活类型']);
    exit;
}

// 如果发现隐藏的产品，返回错误信息
if ($has_hidden_product) {
    echo json_encode(['error' => $error_message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($products)) {
    echo json_encode(['error' => $not_found_message]);
    exit;
}

$data = [
  'products' => $products,
  'activation_type' => $activation_type,
  'downloads_limit' => $downloads_limit,
  'order_number' => $order_number,
  // 为了向后兼容，保留watchfaces字段
  'watchfaces' => $activation_type === 'watchface' ? $products : []
];

echo json_encode($data);

$stmt_user->close();
$stmt_order->close();
if (isset($stmt_product)) {
    $stmt_product->close();
}
$conn->close();
