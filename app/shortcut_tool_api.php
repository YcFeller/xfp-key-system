<?php
session_start();
// 检查用户是否已登录
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 2;
if ($user_role === null) {
  echo json_encode(['error' => '未登录，请先登录。'], JSON_UNESCAPED_UNICODE);
  header("Location: ../../pages/auth/login.php");
  exit;
} elseif ($user_role < $required_role) {
  echo json_encode(['error' => '权限不足，无法访问该页面。'], JSON_UNESCAPED_UNICODE);
  header("Location: ../../index.php");
  exit;
}

// 引入数据库配置文件
require_once './config.php';
require_once __DIR__ . '/UnlockKeyDerivation.php';

// 每秒请求5次限制
$limit = 5;
$interval = 1; // 秒

// 获取客户端IP地址
$ip = $_SERVER['REMOTE_ADDR'];

// 获取当前时间戳
$now = $_SERVER['REQUEST_TIME_FLOAT'];

// 检查请求次数是否超过限制
if (isset($requests[$ip]) && $now - $requests[$ip]['time'] < $interval) {
  $requests[$ip]['count']++;
  if ($requests[$ip]['count'] > $limit) {
    echo json_encode(['error' => '请求次数超过限制。'], JSON_UNESCAPED_UNICODE);
    exit;
  }
} else {
  $requests[$ip] = ['time' => $now, 'count' => 1];
}

// 判断请求方法是否为GET或POST
if ($_SERVER["REQUEST_METHOD"] == "GET" || $_SERVER["REQUEST_METHOD"] == "POST") {
  $action = $_POST['action'] ?? $_GET['action'] ?? '';
  
  // 订单搜索功能
  if ($action === 'search_order') {
    searchOrder();
  }
  // 订单激活功能
  else if ($action === 'activate_order') {
    activateOrder();
  }
  // 原有的直接激活功能
  else {
    directActivation();
  }
} else {
  echo json_encode(['error' => '无效的请求方法。'], JSON_UNESCAPED_UNICODE);
}

// 订单搜索功能
function searchOrder() {
  $order_number = $_POST['order_number'] ?? $_GET['order_number'] ?? '';
  $device_code = $_POST['device_code'] ?? $_GET['device_code'] ?? '';
  $captcha = $_POST['order_captcha'] ?? $_GET['order_captcha'] ?? '';
  
  if (empty($order_number) || empty($device_code)) {
    echo json_encode(['error' => '订单号和设备码不能为空。'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 验证验证码（简化验证，使用固定值）
  if (empty($captcha) || $captcha !== "xr1688s") {
    echo json_encode(['error' => '验证码错误，请重新输入'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $conn = new mysqli($GLOBALS['servername'], $GLOBALS['db_user'], $GLOBALS['db_pass'], $GLOBALS['dbname']);
  if ($conn->connect_error) {
    echo json_encode(['error' => '数据库连接失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 直接按订单号查找（不限定user_id）
  $sql_order = "SELECT plan_id, sku_detail, downloads_limit, user_id, out_trade_no, product_name FROM xfp_order WHERE out_trade_no = ?";
  $stmt_order = $conn->prepare($sql_order);
  $stmt_order->bind_param("s", $order_number);
  $stmt_order->execute();
  $result_order = $stmt_order->get_result();

  if ($result_order->num_rows === 0) {
    echo json_encode(['error' => '订单号不存在，请检查后重新输入'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $row_order = $result_order->fetch_assoc();
  $plan_id = $row_order['plan_id'];
  $sku_detail = json_decode($row_order['sku_detail'], true);
  $downloads_limit = $row_order['downloads_limit'];

  if ($downloads_limit <= 0) {
    echo json_encode(['error' => '该订单的下载次数已用完，无法继续查询'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $sql_watchface = "SELECT w.name as watchface_name, w.watchface_id, w.status, w.image_link FROM xfp_wflist w WHERE w.plan_id = ?";
  $stmt_watchface = $conn->prepare($sql_watchface);
  $stmt_watchface->bind_param("s", $plan_id);
  $stmt_watchface->execute();
  $result_watchface = $stmt_watchface->get_result();

  $watchfaces = [];
  $has_hidden_watchface = false;
  while ($row_watchface = $result_watchface->fetch_assoc()) {
    if ($row_watchface['status'] == 0) {
      $has_hidden_watchface = true;
      break;
    }
    $watchfaces[] = [
      'watchface_name' => $row_watchface['watchface_name'],
      'watchface_id' => $row_watchface['watchface_id'],
      'watchface_image' => !empty($row_watchface['image_link']) ? $row_watchface['image_link'] : ($sku_detail[0]['pic'] ?? ''),
      'status' => $row_watchface['status']
    ];
  }
  if ($has_hidden_watchface) {
    echo json_encode(['error' => '该表盘暂时无法激活'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  if (empty($watchfaces)) {
    echo json_encode(['error' => '该订单对应的表盘信息未找到，请联系管理员'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $data = [
    'order_number' => $order_number,
    'device_code' => $device_code,
    'watchfaces' => $watchfaces,
    'downloads_limit' => $downloads_limit,
    'product_name' => $row_order['product_name']
  ];
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  $stmt_order->close();
  $stmt_watchface->close();
  $conn->close();
}

// 订单激活功能
function activateOrder() {
  $order_number = $_POST['order_number'] ?? $_GET['order_number'] ?? '';
  $device_code = $_POST['device_code'] ?? $_GET['device_code'] ?? '';
  if (empty($order_number) || empty($device_code)) {
    echo json_encode(['error' => '订单号和设备码不能为空。'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $conn = new mysqli($GLOBALS['servername'], $GLOBALS['db_user'], $GLOBALS['db_pass'], $GLOBALS['dbname']);
  if ($conn->connect_error) {
    echo json_encode(['error' => '数据库连接失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  // 直接按订单号查找（不限定user_id）
  $sql_order = "SELECT plan_id, sku_detail, downloads_limit, user_id, out_trade_no FROM xfp_order WHERE out_trade_no = ?";
  $stmt_order = $conn->prepare($sql_order);
  $stmt_order->bind_param("s", $order_number);
  $stmt_order->execute();
  $result_order = $stmt_order->get_result();
  if ($result_order->num_rows === 0) {
    echo json_encode(['error' => '订单号不存在'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $order = $result_order->fetch_assoc();
  if ($order['downloads_limit'] <= 0) {
    echo json_encode(['error' => '剩余次数为零，无法生成解锁密码。'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $wf_sql = "SELECT watchface_id, status FROM xfp_wflist WHERE plan_id = ?";
  $wf_stmt = $conn->prepare($wf_sql);
  $wf_stmt->bind_param("s", $order['plan_id']);
  $wf_stmt->execute();
  $wf_result = $wf_stmt->get_result();
  if ($wf_result->num_rows === 0) {
    echo json_encode(['error' => '未找到匹配的表盘ID。'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $has_hidden_watchface = false;
  $watchface_data = [];
  while ($wf = $wf_result->fetch_assoc()) {
    if ($wf['status'] == 0) {
      $has_hidden_watchface = true;
      break;
    }
    $watchface_data[] = $wf;
  }
  if ($has_hidden_watchface) {
    echo json_encode(['error' => '该表盘暂时无法激活'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $sql = "UPDATE xfp_order SET downloads_limit = downloads_limit - 1 WHERE out_trade_no = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $order_number);
  if (!$stmt->execute()) {
    echo json_encode(['error' => '更新下载次数失败: ' . $stmt->error], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $unlock_pwds = [];
  try {
  foreach ($watchface_data as $wf) {
    $wf_id = $wf['watchface_id'];
    $unlock_pwd = UnlockKeyDerivation::derive($device_code, $wf_id, 'watchface');
    $unlock_pwds[] = [
      'watchface_id' => $wf_id,
      'unlock_pwd' => $unlock_pwd
    ];
  }
  } catch (Throwable $e) {
    $rb = $conn->prepare("UPDATE xfp_order SET downloads_limit = downloads_limit + 1 WHERE out_trade_no = ?");
    $rb->bind_param("s", $order_number);
    $rb->execute();
    $rb->close();
    $conn->close();
    echo json_encode(['error' => '解锁密码生成失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $response = [
    'unlock_pwds' => $unlock_pwds,
    'remaining' => $order['downloads_limit'] - 1
  ];
  // 暂时关闭激活记录保存功能（已注释）
  /*
  // 保存激活记录
  $check_sql = "SELECT COUNT(*) as count FROM xfp_activation_records WHERE order_number = ? AND device_code = ?";
  $check_stmt = $conn->prepare($check_sql);
  $check_stmt->bind_param("ss", $order_number, $device_code);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();
  $check_row = $check_result->fetch_assoc();
  if ($check_row['count'] == 0) {
    $insert_sql = "INSERT INTO xfp_activation_records (order_number, watchface_id, user_id, device_code, unlock_pwd, activation_time) VALUES (?, ?, ?, ?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    foreach ($unlock_pwds as $pwd) {
      $insert_stmt->bind_param("sssss", $order_number, $pwd['watchface_id'], $order['user_id'], $device_code, $pwd['unlock_pwd']);
      if (!$insert_stmt->execute()) {
        echo json_encode(['error' => '保存激活记录失败: ' . $insert_stmt->error], JSON_UNESCAPED_UNICODE);
        exit;
      }
    }
    $insert_stmt->close();
    $response['activation_record_saved'] = true;
  } else {
    $response['activation_record_saved'] = false;
  }
  */
  // 直接返回响应，不保存激活记录
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
  $stmt_order->close();
  $wf_stmt->close();
  $conn->close();
}

// 原有的直接激活功能
function directActivation() {
  $PSN = $_POST['psn'] ?? $_GET['psn']; // 获取设备码
  $WFID = $_POST['wfId'] ?? $_GET['wfId']; // 获取表盘ID
  $PSW = $_POST['psw'] ?? $_GET['psw']; // 获取验证码

  // 判断设备码和表盘ID是否为空
  if (empty($PSN) || empty($WFID)) {
    echo json_encode(['error' => '请输入设备码和表盘ID。'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  // 判断验证码是否为空
  if (empty($PSW)) {
    echo json_encode(['error' => '请输入验证码。'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  // 判断验证码是否正确
  if ($PSW != "xr1688s") {
    echo json_encode(['error' => '验证失败！请联系我获取密码！'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    $UNLOCK_PWD = UnlockKeyDerivation::derive($PSN, $WFID, 'watchface');
  } catch (Throwable $e) {
    echo json_encode(['error' => '解锁密码生成失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
  echo json_encode(['unlock_pwd' => $UNLOCK_PWD], JSON_UNESCAPED_UNICODE);
}
