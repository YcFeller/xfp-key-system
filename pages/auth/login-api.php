<?php
session_start();

// 防止会话劫持
session_regenerate_id();

// 链接数据库
include '../../app/config.php';

// 获取用户输入的登录信息
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$captcha = isset($_POST['captcha']) ? $_POST['captcha'] : '';

// 初始化响应数组
$response = ['success' => false, 'message' => ''];

// 对用户输入进行验证和过滤
if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
  $response['message'] = '用户名只能包含字母和数字';
  echo json_encode($response);
  exit();
}
if (strlen($password) < 8) {
  $response['message'] = '密码长度不能少于8个字符';
  echo json_encode($response);
  exit();
}
if ($captcha !== $_SESSION['captcha']) {
  // 清除验证码，使其变成一次性使用
  unset($_SESSION['captcha']);
  $response['message'] = '验证码错误';
  echo json_encode($response);
  exit();
}

// 验证成功后立即清除验证码，使其变成一次性使用
unset($_SESSION['captcha']);

// 使用PDO连接数据库
try {
  $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_user, $db_pass);
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  $response['message'] = "数据库连接失败: " . $e->getMessage();
  echo json_encode($response);
  exit();
}

// 构建查询用户数据的SQL语句
$sql = "SELECT * FROM users WHERE username = :username";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':username', $username, PDO::PARAM_STR);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 检查用户是否存在
if (!$user) {
  $response['message'] = '用户名不存在';
  echo json_encode($response);
  exit();
}

// 检查密码是否正确
if (!password_verify($password, $user['password'])) {
  $response['message'] = '密码错误';
  echo json_encode($response);
  exit();
}

// 设置会话变量
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['user_role'] = $user['role'];

// 登录成功
$response['success'] = true;
$response['message'] = '登录成功';
echo json_encode($response);

// 关闭数据库连接
$conn = null;
exit();
