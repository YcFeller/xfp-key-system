<?php


session_start();
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

require_once './config.php'; // 数据库配置

$system_user_id = $_SESSION['user_id'];
$conn = new mysqli($servername, $db_user, $db_pass, $dbname);

if ($conn->connect_error) {
  die("数据库连接失败: " . $conn->connect_error);
}

// 预处理查询
$stmt = $conn->prepare("SELECT id, name, watchface_id, status, upload_time, downloads_limit, plan_id FROM xfp_wflist WHERE user_id = ?");
$stmt->bind_param("i", $system_user_id);

if ($stmt->execute()) {
  $result = $stmt->get_result();
  $watchfaces = [];

  while ($row = $result->fetch_assoc()) {
    $watchfaces[] = $row;
  }

  echo json_encode($watchfaces);
} else {
  echo json_encode([]);
}

$stmt->close();
$conn->close();
