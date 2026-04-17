<?php
session_start();
require_once './config.php';

$user_role = $_SESSION['user_role'] ?? null;
$required_role = 2;
if ($user_role === null || $user_role < $required_role) {
  echo json_encode(['error' => '未登录或权限不足。'], JSON_UNESCAPED_UNICODE);
  exit;
}

$user_id = $_SESSION['user_id'];

// 连接数据库
$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
  die("连接失败: " . $conn->connect_error);
}

$action = $_POST['action'] ?? '';

switch ($action) {
  case 'fetch':
    fetchWatchfaces($conn, $user_id);
    break;
  case 'get':
    getWatchface($conn);
    break;
  case 'update':
    updateWatchface($conn);
    break;
  case 'delete':
    deleteWatchface($conn);
    break;
  case 'bulkDelete':
    bulkDeleteWatchfaces($conn);
    break;
  case 'bulkEditLimit':
    bulkEditDownloadsLimit($conn);
    break;
  default:
    echo '无效操作';
    break;
}

$conn->close();

// 获取表盘列表
function fetchWatchfaces($conn, $user_id)
{
  $query = $_POST['query'] ?? '';
  $sql = "SELECT * FROM xfp_wflist WHERE user_id = ? AND name LIKE ? ORDER BY upload_time DESC";
  $stmt = $conn->prepare($sql);
  $searchTerm = "%$query%";
  $stmt->bind_param('is', $user_id, $searchTerm);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $image_link = $row['image_link'];
    // 如果没有设置表盘图片，自动查找订单表的sku_detail
    if (empty($image_link) && !empty($row['plan_id'])) {
      $order_stmt = $conn->prepare("SELECT sku_detail FROM xfp_order WHERE plan_id = ? LIMIT 1");
      $order_stmt->bind_param('s', $row['plan_id']);
      $order_stmt->execute();
      $order_result = $order_stmt->get_result();
      if ($order_row = $order_result->fetch_assoc()) {
        $sku_detail = json_decode($order_row['sku_detail'], true);
        if (is_array($sku_detail) && isset($sku_detail[0]['pic'])) {
          $image_link = $sku_detail[0]['pic'];
        }
      }
      $order_stmt->close();
    }
    echo "<tr>
            <td><input type='checkbox' name='selectWatchface' value='" . htmlspecialchars($row['id']) . "'></td>
            <td>" . htmlspecialchars($row['id']) . "</td>
            <td>" . htmlspecialchars($row['name']) . "</td>
            <td><img src='" . htmlspecialchars($image_link) . "' alt='预览图' class='faceimg'></td>
            <td>" . htmlspecialchars($row['watchface_id']) . "</td>
            <td>" . ($row['status'] == 1 ? '可激活' : '禁止激活') . "</td>
            <td>" . htmlspecialchars($row['upload_time']) . "</td>
            <td>" . htmlspecialchars($row['downloads_limit']) . "</td>
            <td>" . htmlspecialchars($row['plan_id']) . "</td>
            <td><button class='editBtn' data-id='" . htmlspecialchars($row['id']) . "'>编辑</button></td>
          </tr>";
  }
}

// 获取单个表盘信息
function getWatchface($conn)
{
  $id = $_POST['id'];
  $sql = "SELECT * FROM xfp_wflist WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $watchface = $result->fetch_assoc();

  echo json_encode($watchface);
}

// 更新表盘信息
function updateWatchface($conn)
{
  $id = $_POST['id'];
  $name = $_POST['name'];
  $watchface_id = $_POST['watchface_id'];
  $status = $_POST['status'];
  $downloads_limit = $_POST['downloads_limit'];
  $image_link = $_POST['image_link'];

  $sql = "UPDATE xfp_wflist SET name = ?, watchface_id = ?, status = ?, downloads_limit = ?, image_link = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('siiisi', $name, $watchface_id, $status, $downloads_limit, $image_link, $id);

  if ($stmt->execute()) {
    echo '表盘信息已更新';
  } else {
    echo '更新失败: ' . $stmt->error;
  }
}

// 删除表盘
function deleteWatchface($conn)
{
  $id = $_POST['id'];

  $sql = "DELETE FROM xfp_wflist WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id);

  if ($stmt->execute()) {
    echo '表盘已删除';
  } else {
    echo '删除失败: ' . $stmt->error;
  }
}

// 批量删除表盘
function bulkDeleteWatchfaces($conn)
{
  $ids = array_map('intval', $_POST['ids']);
  if (empty($ids)) {
    echo '没有要删除的表盘';
    return;
  }

  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $sql = "DELETE FROM xfp_wflist WHERE id IN ($placeholders)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);

  if ($stmt->execute()) {
    echo '表盘已批量删除成功';
  } else {
    echo '批量删除失败: ' . $stmt->error;
  }
}

// 批量修改下载限制次数
function bulkEditDownloadsLimit($conn)
{
  $ids = array_map('intval', $_POST['ids']);
  $newLimit = intval($_POST['newLimit']);
  if (empty($ids)) {
    echo '没有要修改的表盘';
    return;
  }

  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $sql = "UPDATE xfp_wflist SET downloads_limit = ? WHERE id IN ($placeholders)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param(str_repeat('i', count($ids) + 1), $newLimit, ...$ids);

  if ($stmt->execute()) {
    echo '下载限制已批量修改成功';
  } else {
    echo '批量修改失败: ' . $stmt->error;
  }
}
