<?php
session_start();

// 验证用户权限
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 3; // 站长权限
if ($user_role === null) {
    header("Location: ../../pages/auth/login.php");
    exit;
} elseif ($user_role < $required_role) {
    header("Location: ../../index.php");
    exit;
}

require_once '../../app/config.php';

// 使用config.php中的mysqli连接
if (!isset($mysqli_conn) || $mysqli_conn === null) {
    die('数据库连接失败。');
}
$conn = $mysqli_conn;

// 获取搜索参数
$order_no_search = $_GET['order_no'] ?? '';
$product_type_filter = $_GET['product_type'] ?? '';
$user_id_search = $_GET['user_id'] ?? '';

// 设置每页显示的记录数
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// 构建查询条件
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($order_no_search)) {
    $where_conditions[] = "ar.order_number LIKE ?";
    $params[] = '%' . $order_no_search . '%';
    $param_types .= 's';
}

if (!empty($product_type_filter)) {
    $where_conditions[] = "ar.product_type = ?";
    $params[] = $product_type_filter;
    $param_types .= 's';
}

if (!empty($user_id_search)) {
    $where_conditions[] = "ar.user_id = ?";
    $params[] = $user_id_search;
    $param_types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 查询激活记录（包含用户信息）
$sql = "
    SELECT ar.*, u.username, u.email
    FROM xfp_activation_records ar
    LEFT JOIN users u ON ar.user_id = u.id
    $where_clause
    ORDER BY ar.activation_time DESC
    LIMIT ?, ?
";

$params[] = $offset;
$params[] = $records_per_page;
$param_types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// 获取总记录数
$count_sql = "
    SELECT COUNT(*) AS total 
    FROM xfp_activation_records ar
    LEFT JOIN users u ON ar.user_id = u.id
    $where_clause
";

$count_params = array_slice($params, 0, -2); // 移除LIMIT参数
$count_param_types = substr($param_types, 0, -2); // 移除LIMIT参数类型

$count_stmt = $conn->prepare($count_sql);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_param_types, ...$count_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];

// 计算总页数
$total_pages = ceil($total_records / $records_per_page);

// 获取统计信息
$stats_sql = "
    SELECT 
        COUNT(*) as total_activations,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(CASE WHEN product_type = 'watchface' THEN 1 END) as watchface_activations,
        COUNT(CASE WHEN product_type = 'quickapp' THEN 1 END) as quickapp_activations,
        COUNT(CASE WHEN DATE(activation_time) = CURDATE() THEN 1 END) as today_activations
    FROM xfp_activation_records
";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>激活记录管理 - 站长后台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3176FF',
                        secondary: '#6C47FF'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- 页面标题 -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2">激活记录管理</h1>
                <p class="text-gray-400">管理系统中的所有激活记录</p>
            </div>
            <div class="flex gap-4">
                <a href="index.php" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fa fa-arrow-left mr-2"></i>返回首页
                </a>
            </div>
        </div>

        <!-- 统计卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">总激活次数</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['total_activations']); ?></p>
                    </div>
                    <i class="fa fa-key text-primary text-2xl"></i>
                </div>
            </div>
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">独立用户</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['unique_users']); ?></p>
                    </div>
                    <i class="fa fa-users text-green-400 text-2xl"></i>
                </div>
            </div>
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">表盘激活</p>
                        <p class="text-2xl font-bold text-green-400"><?php echo number_format($stats['watchface_activations']); ?></p>
                    </div>
                    <i class="fa fa-clock text-green-400 text-2xl"></i>
                </div>
            </div>
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">快应用激活</p>
                        <p class="text-2xl font-bold text-blue-400"><?php echo number_format($stats['quickapp_activations']); ?></p>
                    </div>
                    <i class="fa fa-mobile-alt text-blue-400 text-2xl"></i>
                </div>
            </div>
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">今日激活</p>
                        <p class="text-2xl font-bold text-yellow-400"><?php echo number_format($stats['today_activations']); ?></p>
                    </div>
                    <i class="fa fa-calendar-day text-yellow-400 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- 搜索和筛选 -->
        <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 mb-8">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">订单号</label>
                    <input type="text" name="order_no" value="<?php echo htmlspecialchars($order_no_search); ?>"
                           class="w-full bg-gray-700/50 border border-gray-600 text-white rounded-lg px-4 py-2 focus:outline-none focus:border-primary"
                           placeholder="搜索订单号...">
                </div>
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">产品类型</label>
                    <select name="product_type" class="w-full bg-gray-700/50 border border-gray-600 text-white rounded-lg px-4 py-2 focus:outline-none focus:border-primary">
                        <option value="">全部类型</option>
                        <option value="watchface" <?php echo $product_type_filter === 'watchface' ? 'selected' : ''; ?>>表盘</option>
                        <option value="quickapp" <?php echo $product_type_filter === 'quickapp' ? 'selected' : ''; ?>>快应用</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">用户ID</label>
                    <input type="number" name="user_id" value="<?php echo htmlspecialchars($user_id_search); ?>"
                           class="w-full bg-gray-700/50 border border-gray-600 text-white rounded-lg px-4 py-2 focus:outline-none focus:border-primary"
                           placeholder="用户ID...">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-primary text-white px-6 py-2 rounded-lg hover:bg-opacity-90 transition-colors">
                        <i class="fa fa-search mr-2"></i>搜索
                    </button>
                    <a href="?" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-500 transition-colors">
                        <i class="fa fa-times"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- 激活记录表格 -->
        <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-600">
                            <th class="text-left py-4 px-4 text-gray-300 font-semibold">ID</th>
                            <th class="text-left py-4 px-4 text-gray-300 font-semibold">订单号</th>
                            <th class="text-left py-4 px-4 text-gray-300 font-semibold">用户</th>
                            <th class="text-left py-4 px-4 text-gray-300 font-semibold">产品类型</th>
                            <th class="text-left py-4 px-4 text-gray-300 font-semibold">产品ID</th>
                            <th class="text-left py-4 px-4 text-gray-300 font-semibold">设备码</th>
                            <th class="text-left py-4 px-4 text-gray-300 font-semibold">解锁密码</th>
                            <th class="text-left py-4 px-4 text-gray-300 font-semibold">激活时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php 
                                    $product_type = $row['product_type'] ?? 'watchface';
                                    $product_type_text = ($product_type === 'quickapp') ? '快应用' : '表盘';
                                    $product_type_color = ($product_type === 'quickapp') ? 'text-blue-400' : 'text-green-400';
                                ?>
                                <tr class="border-b border-gray-700/50 hover:bg-gray-700/30 transition-colors">
                                    <td class="py-4 px-4 text-white"><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td class="py-4 px-4 text-white font-mono text-sm"><?php echo htmlspecialchars($row['order_number']); ?></td>
                                    <td class="py-4 px-4">
                                        <div class="text-white font-medium"><?php echo htmlspecialchars($row['username'] ?? 'N/A'); ?></div>
                                        <div class="text-gray-400 text-sm">ID: <?php echo htmlspecialchars($row['user_id']); ?></div>
                                    </td>
                                    <td class="py-4 px-4 <?php echo $product_type_color; ?> font-semibold">
                                        <i class="fa <?php echo ($product_type === 'quickapp') ? 'fa-mobile-alt' : 'fa-clock'; ?> mr-1"></i>
                                        <?php echo $product_type_text; ?>
                                    </td>
                                    <td class="py-4 px-4 text-white font-mono text-sm"><?php echo htmlspecialchars($row['product_id'] ?? $row['watchface_id']); ?></td>
                                    <td class="py-4 px-4 text-white font-mono text-sm"><?php echo htmlspecialchars($row['device_code']); ?></td>
                                    <td class="py-4 px-4 text-white font-mono text-sm"><?php echo htmlspecialchars($row['unlock_pwd']); ?></td>
                                    <td class="py-4 px-4 text-gray-300 text-sm"><?php echo htmlspecialchars($row['activation_time']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="py-12 px-4 text-center text-gray-400">
                                    <i class="fa fa-inbox text-4xl mb-4 block"></i>
                                    <p class="text-lg">暂无激活记录</p>
                                    <p class="text-sm mt-2">没有找到符合条件的激活记录</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 分页导航 -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center gap-2 mt-8">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&order_no=<?php echo urlencode($order_no_search); ?>&product_type=<?php echo urlencode($product_type_filter); ?>&user_id=<?php echo urlencode($user_id_search); ?>" 
                           class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fa fa-chevron-left mr-1"></i>上一页
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&order_no=<?php echo urlencode($order_no_search); ?>&product_type=<?php echo urlencode($product_type_filter); ?>&user_id=<?php echo urlencode($user_id_search); ?>" 
                           class="px-4 py-2 rounded-lg transition-colors <?php echo ($i == $page) ? 'bg-primary text-white' : 'bg-gray-700 text-white hover:bg-gray-600'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&order_no=<?php echo urlencode($order_no_search); ?>&product_type=<?php echo urlencode($product_type_filter); ?>&user_id=<?php echo urlencode($user_id_search); ?>" 
                           class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                            下一页<i class="fa fa-chevron-right ml-1"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="text-center text-gray-400 text-sm mt-4">
                    显示第 <?php echo $offset + 1; ?> - <?php echo min($offset + $records_per_page, $total_records); ?> 条，共 <?php echo $total_records; ?> 条记录
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>