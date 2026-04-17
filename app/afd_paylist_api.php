<?php
/**
 * 爱发电订单支付列表API
 * 用于获取和处理爱发电的订单支付信息
 */

/**
 * 根据产品信息判断产品类型
 * @param string $product_name 产品名称
 * @param array $sku_detail_array SKU详情数组
 * @return string 产品类型：watchface, quickapp, mixed
 */
function determineProductType($product_name, $sku_detail_array) {
    $product_type = 'watchface'; // 默认为表盘
    
    // 检查产品名称或SKU详情中是否包含快应用相关关键词
    $quickapp_keywords = ['快应用', 'quickapp', 'quick app', '应用', 'app'];
    $search_text = strtolower($product_name . ' ' . json_encode($sku_detail_array, JSON_UNESCAPED_UNICODE));
    
    // 先检查是否包含表盘关键词
    $watchface_keywords = ['表盘', 'watchface', 'watch face', '表面'];
    $has_watchface = false;
    foreach ($watchface_keywords as $keyword) {
        if (strpos($search_text, strtolower($keyword)) !== false) {
            $has_watchface = true;
            break;
        }
    }
    
    // 检查是否包含快应用关键词（排除与表盘相关的组合）
    $has_quickapp = false;
    foreach ($quickapp_keywords as $keyword) {
        if (strpos($search_text, strtolower($keyword)) !== false) {
            // 特殊处理：如果是"watch face"这样的组合，不应该被"face"中的"app"误判
            if ($keyword === 'app' && strpos($search_text, 'watch face') !== false) {
                continue;
            }
            $has_quickapp = true;
            break;
        }
    }
    
    // 根据检测结果确定产品类型
    if ($has_watchface && $has_quickapp) {
        $product_type = 'mixed';
    } elseif ($has_quickapp) {
        $product_type = 'quickapp';
    } else {
        $product_type = 'watchface'; // 默认或仅包含表盘关键词
    }
    
    return $product_type;
}

session_start();
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

// 获取用户的afdian_user_id和afdian_token
$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => '数据库连接失败']);
    exit;
}
$stmt = $conn->prepare("SELECT afdian_user_id, afdian_token FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => '用户数据未找到']);
    exit;
}
$user_data = $result->fetch_assoc();
$afdian_user_id = $user_data['afdian_user_id'];
$afdian_token = $user_data['afdian_token'];
$stmt->close();

// 分页参数
$page = max(1, intval($_GET['page'] ?? 1));
$page_size = 10;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 拉取订单
    $ts = time();
    $params = ["page" => $page];
    $kv_string = "params" . json_encode($params) . "ts" . $ts . "user_id" . $afdian_user_id;
    $sign = md5($afdian_token . $kv_string);
    $request_data = [
        "user_id" => $afdian_user_id,
        "params" => json_encode($params),
        "ts" => $ts,
        "sign" => $sign
    ];
    $json_data = json_encode($request_data);
    $url = "https://afdian.com/api/open/query-order";
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json_data,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        echo json_encode(['success' => false, 'error' => '请求失败: ' . $err]);
        exit;
    }
    $data = json_decode($response, true);
    if (!isset($data['ec']) || $data['ec'] != 200) {
        echo json_encode(['success' => false, 'error' => '接口错误: ' . ($data['em'] ?? '未知错误')]);
        exit;
    }
    $orders = $data['data']['list'] ?? [];
    $total_count = $data['data']['total_count'] ?? count($orders);
    $total_pages = max(1, ceil($total_count / $page_size));
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'page' => $page,
        'total_pages' => $total_pages
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 同步订单
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['action']) || $input['action'] !== 'sync' || !is_array($input['orders'])) {
        echo json_encode(['success' => false, 'error' => '参数错误']);
        exit;
    }
    $orders = $input['orders'];
    $insert_stmt = $conn->prepare(
        "INSERT INTO xfp_order (out_trade_no, user_id, afdian_user_id, system_user_id, total_amount, remark, discount, sku_detail, product_name, plan_id, product_type) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
         ON DUPLICATE KEY UPDATE 
         total_amount = VALUES(total_amount), 
         remark = VALUES(remark), 
         discount = VALUES(discount), 
         sku_detail = VALUES(sku_detail), 
         product_name = VALUES(product_name), 
         plan_id = VALUES(plan_id),
         product_type = VALUES(product_type)"
    );
    if (!$insert_stmt) {
        echo json_encode(['success' => false, 'error' => '预处理语句错误: ' . $conn->error]);
        exit;
    }
    $ok = 0; $fail = 0; $fail_msgs = [];
    foreach ($orders as $sponsor) {
        $sku_detail = json_encode($sponsor['sku_detail'] ?? []);
        $product_name = $sponsor['product_name'] ?? '';
        
        // 根据产品名称和SKU详情判断产品类型
        $sku_detail_array = $sponsor['sku_detail'] ?? [];
        $product_type = determineProductType($product_name, $sku_detail_array);
        
        $success = $insert_stmt->bind_param(
            "sssssssssss",
            $sponsor['out_trade_no'],
            $sponsor['user_id'],
            $afdian_user_id,
            $user_id,
            $sponsor['total_amount'],
            $sponsor['remark'],
            $sponsor['discount'],
            $sku_detail,
            $product_name,
            $sponsor['plan_id'],
            $product_type
        ) && $insert_stmt->execute();
        if ($success) {
            $ok++;
        } else {
            $fail++;
            $fail_msgs[] = $sponsor['out_trade_no'] . ': ' . $insert_stmt->error;
        }
    }
    $insert_stmt->close();
    $conn->close();
    echo json_encode([
        'success' => $fail === 0,
        'message' => "成功同步{$ok}条，失败{$fail}条" . ($fail ? (': ' . implode('; ', $fail_msgs)) : '')
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => '不支持的请求方式']);