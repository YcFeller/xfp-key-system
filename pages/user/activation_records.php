<?php
session_start();
require_once '../../app/config.php';

// 验证用户是否登录
$user_role = $_SESSION['user_role'] ?? null;
$required_role = 1;
if ($user_role === null) {
    header("Location: ../auth/login.php");
    exit;
} elseif ($user_role < $required_role) {
    header("Location: ../../index.php");
    exit;
}

// 使用config.php中的mysqli连接
if (!isset($mysqli_conn) || $mysqli_conn === null) {
    die('数据库连接失败。');
}
$conn = $mysqli_conn;

// 获取用户信息
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result_user = $stmt->get_result();
$user = $result_user->fetch_assoc();
$stmt->close();

// 获取用户ID
$user_id = $_SESSION['user_id'];

// 获取搜索订单号（如果有）
$order_no_search = $_GET['order_no'] ?? '';

// 设置每页显示的记录数
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// 查询激活记录
$sql = "
    SELECT * 
    FROM xfp_activation_records 
    WHERE user_id = ?
    " . (!empty($order_no_search) ? " AND order_number LIKE ?" : "") . "
    ORDER BY activation_time DESC
    LIMIT ?, ?
";
$stmt = $conn->prepare($sql);

if (!empty($order_no_search)) {
  $search_term = '%' . $order_no_search . '%';
  $stmt->bind_param("ssii", $user_id, $search_term, $offset, $records_per_page);
} else {
  $stmt->bind_param("sii", $user_id, $offset, $records_per_page);
}

$stmt->execute();
$result = $stmt->get_result();

// 获取总记录数
$count_sql = "SELECT COUNT(*) AS total FROM xfp_activation_records WHERE user_id = ?" . (!empty($order_no_search) ? " AND order_number LIKE ?" : "");
$count_stmt = $conn->prepare($count_sql);
if (!empty($order_no_search)) {
  $count_stmt->bind_param("ss", $user_id, $search_term);
} else {
  $count_stmt->bind_param("s", $user_id);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];

// 计算总页数
$total_pages = ceil($total_records / $records_per_page);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>激活记录 - XFP密钥获取系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3176FF',
                        secondary: '#6C47FF'
                    },
                    borderRadius: {
                        'button': '8px'
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'MiSans';
            src: url('../../files/font/misans.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        body {
            font-family: 'MiSans', sans-serif;
            color: #ffffff;
            background-color: #0F172A;
            min-height: 100vh;
        }

        .geometric-bg {
            background-image: radial-gradient(circle at 10% 20%, rgba(49, 118, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(108, 71, 255, 0.1) 0%, transparent 50%);
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(49, 118, 255, 0.3);
        }
    </style>
    <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body class="geometric-bg">
    <div class="max-w-[1440px] mx-auto px-8">
        <!-- 头部导航 -->
        <header class="py-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-['Pacifico'] text-white">XFP密钥获取系统</h1>
                <nav class="ml-12">
                    <ul class="flex gap-8">
                        <li><a href="./index.php" class="text-gray-300 hover:text-white transition-colors">客户中心</a></li>
                        <li><a href="./my_orders.php" class="text-gray-300 hover:text-white transition-colors">我的订单</a></li>
                        <li><a href="./activation_records.php" class="text-primary font-semibold">激活记录</a></li>
                        <li><a href="./permission_apply.php" class="text-gray-300 hover:text-white transition-colors">权限申请</a></li>
                    </ul>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-gray-300"><?php echo htmlspecialchars($user['username']); ?></span>
                <a href="../../index.php" class="!rounded-button px-6 py-2 text-gray-300 hover:text-white transition-colors">返回主页</a>
                <a href="../auth/logout.php" class="!rounded-button px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors">退出登录</a>
            </div>
        </header>

        <main class="py-20">
            <h1 class="text-5xl font-bold text-white mb-8">激活记录</h1>
            <p class="text-xl text-gray-300 mb-12">查看您的所有激活记录</p>

            <!-- 激活记录卡片 -->
            <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-8 card-hover">
                <!-- 搜索表单 -->
                <div class="mb-6">
                    <form method="GET" action="" class="flex gap-4 items-center flex-wrap">
                        <input type="text" id="order_no" name="order_no" value="<?php echo htmlspecialchars($order_no_search); ?>" 
                               class="bg-gray-700/50 border border-gray-600 text-white rounded-lg px-4 py-2 focus:outline-none focus:border-primary input-focus" 
                               placeholder="搜索订单号...">
                        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-opacity-90 transition-colors">
                            <i class="fa fa-search mr-2"></i>搜索
                        </button>
                        <?php if (!empty($order_no_search)): ?>
                            <a href="?" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-500 transition-colors">
                                <i class="fa fa-times mr-2"></i>清除
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if (isset($_GET['order_no']) && $order_no_search !== ''): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-300 rounded-lg p-3 mb-6">
                        <i class="fa fa-info-circle mr-2"></i>
                        已筛选订单号包含"<?php echo htmlspecialchars($order_no_search); ?>"的记录
                    </div>
                <?php endif; ?>

                <!-- 数据表格 -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-600">
                                <th class="text-left py-4 px-4 text-gray-300 font-semibold">ID</th>
                                <th class="text-left py-4 px-4 text-gray-300 font-semibold">订单号</th>
                                <th class="text-left py-4 px-4 text-gray-300 font-semibold">产品类型</th>
                                <th class="text-left py-4 px-4 text-gray-300 font-semibold">设备码</th>
                                <th class="text-left py-4 px-4 text-gray-300 font-semibold">解锁密码</th>
                                <th class="text-left py-4 px-4 text-gray-300 font-semibold">激活时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php 
                                        // 确定产品类型显示文本
                                        $product_type = $row['product_type'] ?? 'watchface';
                                        $product_type_text = ($product_type === 'quickapp') ? '快应用' : '表盘';
                                        $product_type_color = ($product_type === 'quickapp') ? 'text-blue-400' : 'text-green-400';
                                    ?>
                                    <tr class="border-b border-gray-700/50 hover:bg-gray-700/30 transition-colors">
                                        <td class="py-4 px-4 text-white"><?php echo htmlspecialchars($row['id']); ?></td>
                                        <td class="py-4 px-4 text-white font-mono"><?php echo htmlspecialchars($row['order_number']); ?></td>
                                        <td class="py-4 px-4 <?php echo $product_type_color; ?> font-semibold">
                                            <i class="fa <?php echo ($product_type === 'quickapp') ? 'fa-mobile-alt' : 'fa-clock'; ?> mr-1"></i>
                                            <?php echo $product_type_text; ?>
                                        </td>
                                        <td class="py-4 px-4 text-white font-mono"><?php echo htmlspecialchars($row['device_code']); ?></td>
                                        <td class="py-4 px-4 text-white font-mono"><?php echo htmlspecialchars($row['unlock_pwd']); ?></td>
                                        <td class="py-4 px-4 text-gray-300"><?php echo htmlspecialchars($row['activation_time']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-12 px-4 text-center text-gray-400">
                                        <i class="fa fa-inbox text-4xl mb-4 block"></i>
                                        <p class="text-lg">暂无激活记录</p>
                                        <p class="text-sm mt-2">您还没有任何激活记录</p>
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
                            <a href="?page=<?php echo $page - 1; ?>&order_no=<?php echo htmlspecialchars($order_no_search); ?>" 
                               class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                                <i class="fa fa-chevron-left mr-1"></i>上一页
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&order_no=<?php echo htmlspecialchars($order_no_search); ?>" 
                               class="px-4 py-2 rounded-lg transition-colors <?php echo ($i == $page) ? 'bg-primary text-white' : 'bg-gray-700 text-white hover:bg-gray-600'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&order_no=<?php echo htmlspecialchars($order_no_search); ?>" 
                               class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                                下一页<i class="fa fa-chevron-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>