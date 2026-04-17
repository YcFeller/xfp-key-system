<?php
session_start();

// 验证用户是否已登录并具有管理员权限
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 3;
if ($user_role === null) {
  echo json_encode(['error' => '未登录，请先登录。'], JSON_UNESCAPED_UNICODE);
  exit;
} elseif ($user_role < $required_role) {
  echo json_encode(['error' => '权限不足，无法访问该页面。'], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once '../config.php';
$user_id = $_SESSION['user_id'];

// 连接数据库
$conn = new mysqli($servername, $db_user, $db_pass, $dbname);

// 检查连接
if ($conn->connect_error) {
  die("连接失败: " . $conn->connect_error);
}

$action = $_POST['action'] ?? '';

switch ($action) {
  case 'fetch':
    fetchUsers($conn);
    break;
  case 'get':
    getUser($conn);
    break;
  case 'update':
    updateUser($conn);
    break;
  case 'delete':
    deleteUser($conn);
    break;
  case 'quick_login':
    quickLogin($conn);
    break;
  default:
    echo '无效操作';
    break;
}

$conn->close();

// 获取用户列表
function fetchUsers($conn)
{
  $query = $_POST['query'] ?? '';
  $role = $_POST['role'] ?? '';
  $status = $_POST['status'] ?? '';

  $sql = "SELECT * FROM users WHERE 1=1"; // 基础查询

  if (!empty($query)) {
    $sql .= " AND username LIKE ?";
    $params[] = "%{$query}%";
    $types .= "s";
  }

  if (!empty($role)) {
    $sql .= " AND role = ?";
    $params[] = $role;
    $types .= "i";
  }

  if ($status !== '') { // 注意：状态为0时仍需要传递
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "i";
  }

  $sql .= " ORDER BY id ASC";

  $stmt = $conn->prepare($sql);

  if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
  }

  $stmt->execute();
  $result = $stmt->get_result();

  // 构建输出
  $output = '';
  while ($row = $result->fetch_assoc()) {
    $avatar = htmlspecialchars($row['avatar_link']);
    $role = getRoleName($row['role']);
    $status = $row['status'] ? '激活' : '未激活';

    $output .= '<tr>';
    $output .= '<td><input type="checkbox" data-user-id="' . htmlspecialchars($row['id']) . '"></td>';
    $output .= '<td>' . htmlspecialchars($row['id']) . '</td>';
    $output .= '<td>' . htmlspecialchars($row['username']) . '</td>';
    $output .= '<td>' . htmlspecialchars($row['email']) . '</td>';
    $output .= '<td>' . $role . '</td>';
    $output .= '<td>' . $status . '</td>';
    $output .= '<td><img src="' . $avatar . '" alt="头像" class="user-avatar"></td>';
    $output .= '<td>' . htmlspecialchars($row['afdian_token']) . '</td>';
    $output .= '<td><button class="editBtn" data-user-id="' . htmlspecialchars($row['id']) . '">编辑</button></td>';
    $output .= '<td><button class="quickLoginBtn" data-user-id="' . htmlspecialchars($row['id']) . '">快速登录</button></td>';
    $output .= '<td><button class="deleteBtn" data-user-id="' . htmlspecialchars($row['id']) . '">删除</button></td>';
    $output .= '</tr>';
  }

  echo $output;
}


// 获取单个用户信息
function getUser($conn)
{
  $userId = $_POST['user_id'];
  $sql = "SELECT * FROM users WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();

  echo json_encode($user);
}

// 获取角色名称
function getRoleName($role)
{
  switch ($role) {
    case 1:
      return '客户';
    case 2:
      return '用户';
    case 3:
      return '管理员';
    default:
      return '未知';
  }
}

// 获取状态名称
function getStatusName($status)
{
  return $status == 1 ? '激活' : '禁用';
}

// 更新用户信息
function updateUser($conn)
{
  $userId = $_POST['user_id'];
  $username = $_POST['username'];
  $email = $_POST['email'];
  $role = $_POST['role'];
  $status = $_POST['status'];
  $afdian_token = $_POST['afdian_token'];
  $password = $_POST['password'] ?? null; // 可选，密码为空时不更新

  // 确保传递过来的数据有效
  if (!isset($userId) || !isset($username) || !isset($email) || !isset($role) || !isset($status)) {
    echo "数据不完整";
    exit;
  }

  if ($password) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET username = ?, email = ?, role = ?, status = ?, afdian_token = ?, password = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssisssi', $username, $email, $role, $status, $afdian_token, $hashedPassword, $userId);
  } else {
    $sql = "UPDATE users SET username = ?, email = ?, role = ?, status = ?, afdian_token = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssissi', $username, $email, $role, $status, $afdian_token, $userId);
  }

  if ($stmt->execute()) {
    echo '用户信息已更新';
  } else {
    echo '更新失败';
  }
}

// 删除用户
function deleteUser($conn)
{
  $userId = $_POST['user_id'];
  $sql = "DELETE FROM users WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $userId);

  if ($stmt->execute()) {
    echo '用户已删除';
  } else {
    echo '删除失败';
  }
}

// 快速登录
function quickLogin($conn)
{
  $userId = $_POST['user_id'];
  $sql = "SELECT * FROM users WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();

  if ($user) {
    // 需要先销毁原有的session进行新的登录
    session_unset();
    session_destroy();
    session_start();

    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    echo json_encode(['success' => 'Login successful as ' . $user['username']]);
  } else {
    echo json_encode(['error' => '登录失败']);
    echo json_encode(['error' => '用户不存在']);
  }
}
