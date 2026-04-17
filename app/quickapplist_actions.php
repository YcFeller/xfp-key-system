<?php
session_start();
require_once 'config.php';

// 检查用户权限
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 2;
if ($user_role === null || $user_role < $required_role) {
  echo json_encode(['error' => '权限不足'], JSON_UNESCAPED_UNICODE);
  exit;
}

$user_id = $_SESSION['user_id'];

// 数据库连接
$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
  die("连接失败: " . $conn->connect_error);
}

// 设置字符集
$conn->set_charset("utf8mb4");

$action = $_POST['action'] ?? '';

switch ($action) {
  case 'fetchQuickapps':
    fetchQuickapps($conn, $user_id);
    break;
  case 'getQuickapp':
    getQuickapp($conn);
    break;
  case 'updateQuickapp':
    updateQuickapp($conn, $user_id);
    break;
  case 'deleteQuickapp':
    deleteQuickapp($conn, $user_id);
    break;
  case 'bulkDelete':
    bulkDeleteQuickapps($conn, $user_id);
    break;
  case 'bulkEditLimit':
    bulkEditLimit($conn, $user_id);
    break;
  default:
    echo '无效的操作';
    break;
}

$conn->close();

/**
 * 获取快应用列表
 * @param mysqli $conn 数据库连接
 * @param int $user_id 用户ID
 */
function fetchQuickapps($conn, $user_id)
{
  $query = $_POST['query'] ?? '';
  $sql = "SELECT * FROM xfp_quickapp_list WHERE user_id = ? AND name LIKE ? ORDER BY upload_time DESC";
  $stmt = $conn->prepare($sql);
  $searchTerm = "%$query%";
  $stmt->bind_param('is', $user_id, $searchTerm);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $icon_link = $row['icon_link'];
    // 如果没有设置图标，使用默认图标
    if (empty($icon_link)) {
      $icon_link = '../../files/imgs/default_quickapp_icon.png';
    }
    
    $status_class = $row['status'] == 1 ? 'status-active' : 'status-inactive';
    $status_text = $row['status'] == 1 ? '启用' : '禁用';
    
    echo "<tr>
            <td><input type='checkbox' name='selectQuickapp' value='" . htmlspecialchars($row['id']) . "'></td>
            <td>" . htmlspecialchars($row['id']) . "</td>
            <td>" . htmlspecialchars($row['name']) . "</td>
            <td><img src='" . htmlspecialchars($icon_link) . "' alt='图标' class='appicon'></td>
            <td>" . htmlspecialchars($row['quickapp_id']) . "</td>
            <td>" . htmlspecialchars($row['package_name']) . "</td>
            <td>" . htmlspecialchars($row['version']) . "</td>
            <td><span class='status-badge $status_class'>$status_text</span></td>
            <td>" . htmlspecialchars($row['upload_time']) . "</td>
            <td>" . htmlspecialchars($row['downloads_limit']) . "</td>
            <td>" . htmlspecialchars($row['category']) . "</td>
            <td><button class='editBtn btn btn-primary' data-id='" . htmlspecialchars($row['id']) . "'>编辑</button></td>
          </tr>";
  }
}

/**
 * 获取单个快应用信息
 * @param mysqli $conn 数据库连接
 */
function getQuickapp($conn)
{
  $id = $_POST['id'];
  $sql = "SELECT * FROM xfp_quickapp_list WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $quickapp = $result->fetch_assoc();

  echo json_encode($quickapp);
}

/**
 * 更新快应用信息
 * @param mysqli $conn 数据库连接
 * @param int $user_id 用户ID
 */
function updateQuickapp($conn, $user_id)
{
  $id = $_POST['id'];
  $name = $_POST['name'];
  $quickapp_id = $_POST['quickapp_id'];
  $package_name = $_POST['package_name'];
  $version = $_POST['version'];
  $description = $_POST['description'];
  $status = $_POST['status'];
  $downloads_limit = $_POST['downloads_limit'];
  $category = $_POST['category'];
  $icon_link = $_POST['icon_link'];

  // 验证用户权限
  $check_sql = "SELECT user_id FROM xfp_quickapp_list WHERE id = ?";
  $check_stmt = $conn->prepare($check_sql);
  $check_stmt->bind_param('i', $id);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();
  $quickapp = $check_result->fetch_assoc();

  if (!$quickapp || $quickapp['user_id'] != $user_id) {
    echo '权限不足或快应用不存在';
    return;
  }

  $sql = "UPDATE xfp_quickapp_list SET name = ?, quickapp_id = ?, package_name = ?, version = ?, description = ?, status = ?, downloads_limit = ?, category = ?, icon_link = ? WHERE id = ? AND user_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ssssssissii', $name, $quickapp_id, $package_name, $version, $description, $status, $downloads_limit, $category, $icon_link, $id, $user_id);

  if ($stmt->execute()) {
    echo '快应用信息已更新';
  } else {
    echo '更新失败: ' . $stmt->error;
  }
}

/**
 * 删除快应用
 * @param mysqli $conn 数据库连接
 * @param int $user_id 用户ID
 */
function deleteQuickapp($conn, $user_id)
{
  $id = $_POST['id'];

  // 验证用户权限
  $check_sql = "SELECT user_id, file_path FROM xfp_quickapp_list WHERE id = ?";
  $check_stmt = $conn->prepare($check_sql);
  $check_stmt->bind_param('i', $id);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();
  $quickapp = $check_result->fetch_assoc();

  if (!$quickapp || $quickapp['user_id'] != $user_id) {
    echo '权限不足或快应用不存在';
    return;
  }

  // 删除文件（如果存在）
  if (!empty($quickapp['file_path']) && file_exists($quickapp['file_path'])) {
    unlink($quickapp['file_path']);
  }

  $sql = "DELETE FROM xfp_quickapp_list WHERE id = ? AND user_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ii', $id, $user_id);

  if ($stmt->execute()) {
    echo '快应用已删除';
  } else {
    echo '删除失败: ' . $stmt->error;
  }
}

/**
 * 批量删除快应用
 * @param mysqli $conn 数据库连接
 * @param int $user_id 用户ID
 */
function bulkDeleteQuickapps($conn, $user_id)
{
  $ids = $_POST['ids'];
  
  if (!is_array($ids) || empty($ids)) {
    echo '请选择要删除的快应用';
    return;
  }

  $placeholders = str_repeat('?,', count($ids) - 1) . '?';
  
  // 先获取文件路径用于删除文件
  $file_sql = "SELECT file_path FROM xfp_quickapp_list WHERE id IN ($placeholders) AND user_id = ?";
  $file_stmt = $conn->prepare($file_sql);
  $file_params = array_merge($ids, [$user_id]);
  $file_stmt->bind_param(str_repeat('i', count($file_params)), ...$file_params);
  $file_stmt->execute();
  $file_result = $file_stmt->get_result();
  
  // 删除文件
  while ($row = $file_result->fetch_assoc()) {
    if (!empty($row['file_path']) && file_exists($row['file_path'])) {
      unlink($row['file_path']);
    }
  }

  // 删除数据库记录
  $sql = "DELETE FROM xfp_quickapp_list WHERE id IN ($placeholders) AND user_id = ?";
  $stmt = $conn->prepare($sql);
  $params = array_merge($ids, [$user_id]);
  $stmt->bind_param(str_repeat('i', count($params)), ...$params);

  if ($stmt->execute()) {
    $affected_rows = $stmt->affected_rows;
    echo "成功删除 $affected_rows 个快应用";
  } else {
    echo '批量删除失败: ' . $stmt->error;
  }
}

/**
 * 批量修改下载限制
 * @param mysqli $conn 数据库连接
 * @param int $user_id 用户ID
 */
function bulkEditLimit($conn, $user_id)
{
  $ids = $_POST['ids'];
  $downloads_limit = $_POST['downloads_limit'];
  
  if (!is_array($ids) || empty($ids)) {
    echo '请选择要修改的快应用';
    return;
  }

  if (!is_numeric($downloads_limit) || $downloads_limit < 0) {
    echo '请输入有效的下载限制数量';
    return;
  }

  $placeholders = str_repeat('?,', count($ids) - 1) . '?';
  $sql = "UPDATE xfp_quickapp_list SET downloads_limit = ? WHERE id IN ($placeholders) AND user_id = ?";
  $stmt = $conn->prepare($sql);
  $params = array_merge([$downloads_limit], $ids, [$user_id]);
  $stmt->bind_param(str_repeat('i', count($params)), ...$params);

  if ($stmt->execute()) {
    $affected_rows = $stmt->affected_rows;
    echo "成功修改 $affected_rows 个快应用的下载限制";
  } else {
    echo '批量修改失败: ' . $stmt->error;
  }
}
?>