<?php
session_start();

ini_set('display_errors', 0); // 不在页面上显示错误
ini_set('log_errors', 1); // 启用错误日志记录
ini_set('error_log', '../../logs/php_error.log'); // 设置错误日志路径

// 链接数据库
include '../../app/config.php';

// 获取用户输入的注册信息
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$oauth_user_avatar = isset($_POST['oauth_user_avatar']) ? trim($_POST['oauth_user_avatar']) : '';
$oauth_user_id = isset($_POST['oauth_user_id']) ? trim($_POST['oauth_user_id']) : '';

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
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $response['message'] = '无效的电子邮件地址';
  echo json_encode($response);
  exit();
}

// 邮箱白名单
// $whitelisted_emails = ['ycfeller@163.com', 'example2@example.com']; // 允许注册的邮箱地址列表

// if (!in_array($email, $whitelisted_emails)) {
//   $response['message'] = '该电子邮件地址不在允许注册的白名单中';
//   echo json_encode($response);
//   exit();
// }

// 使用PDO连接数据库
try {
  $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_user, $db_pass);
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  error_log("数据库连接失败: " . $e->getMessage());
  $response['message'] = "数据库连接失败";
  echo json_encode($response);
  exit();
}

// 检查用户名或电子邮件是否已存在
try {
  $sql = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':username', $username, PDO::PARAM_STR);
  $stmt->bindParam(':email', $email, PDO::PARAM_STR);
  $stmt->execute();
  $count = $stmt->fetchColumn();

  if ($count > 0) {
    $response['message'] = '用户名或电子邮件已被使用';
    echo json_encode($response);
    exit();
  }

  // 生成激活码
  $activation_code = bin2hex(random_bytes(16));

  // 哈希密码
  $hashed_password = password_hash($password, PASSWORD_BCRYPT);

  // 插入用户数据
  $sql = "INSERT INTO users (username, password, email, role, status, activation_code, created_at, updated_at, avatar_link, afdian_user_id) 
        VALUES (:username, :password, :email, 1, 'pending', :activation_code, NOW(), NOW(), :avatar_link, :afdian_user_id)";

  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':username', $username, PDO::PARAM_STR);
  $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
  $stmt->bindParam(':email', $email, PDO::PARAM_STR);
  $stmt->bindParam(':activation_code', $activation_code, PDO::PARAM_STR);
  $stmt->bindParam(':avatar_link', $oauth_user_avatar, PDO::PARAM_STR); // 绑定oauth_user_avatar
  $stmt->bindParam(':afdian_user_id', $oauth_user_id, PDO::PARAM_STR); // 绑定oauth_user_id

  if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = '注册成功，请进行登录~';
  } else {
    error_log("用户数据插入失败: " . implode(", ", $stmt->errorInfo()));
    $response['message'] = '注册失败，请重试。';
  }
} catch (Exception $e) {
  error_log("SQL错误: " . $e->getMessage());
  $response['message'] = '注册失败，请重试。';
}

echo json_encode($response);
$conn = null;
exit();
