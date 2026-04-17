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

require_once './config.php';

$system_user_id = $_SESSION['user_id'];
$name = $_POST['name'] ?? '';
$watchface_id = $_POST['watchface_id'] ?? '';
$status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
$plan_id = $_POST['plan_id'] ?? '';
$image_link = $_POST['image_link'] ?? '';

// 验证表单数据
if (empty($name) || empty($watchface_id) || empty($plan_id)) {
  echo "所有字段都是必需的";
  exit;
}

$conn = new mysqli($servername, $db_user, $db_pass, $dbname);

if ($conn->connect_error) {
  echo "数据库连接失败: " . $conn->connect_error;
  exit;
}

// 检查是否存在重复的表盘ID
$stmt = $conn->prepare("SELECT COUNT(*) FROM xfp_wflist WHERE watchface_id = ? AND user_id = ?");
$stmt->bind_param("si", $watchface_id, $system_user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['COUNT(*)'] > 0) {
  echo "表盘ID已存在";
  exit;
}

// 插入新表盘
$sql = "
    INSERT INTO xfp_wflist (name, watchface_id, status, upload_time, downloads_limit, plan_id, user_id, image_link)
    VALUES (?, ?, ?, NOW(), 0, ?, ?, ?)
";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
  echo "准备 SQL 语句失败: " . $conn->error;
  exit;
}

$stmt->bind_param("ssisis", $name, $watchface_id, $status, $plan_id, $system_user_id, $image_link);

if ($stmt->execute()) {
  echo "表盘上传成功！";
} else {
  echo "数据插入失败: " . $stmt->error;
}

$stmt->close();
$conn->close();
