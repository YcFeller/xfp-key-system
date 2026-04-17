<?php
/**
 * 产品类型识别功能测试脚本
 * 用于验证订单更新系统中的产品类型自动识别功能
 */

/**
 * 根据产品信息判断产品类型
 * @param string $product_name 产品名称
 * @param array $sku_detail_array SKU详情数组
 * @return string 产品类型：watchface, quickapp, mixed
 */
function determineProductType($product_name, $sku_detail_array) {
    $product_type = 'watchface'; // 默认为表盘

    $quickapp_keywords = ['快应用', 'quickapp', 'quick app', '应用', 'app'];
    $search_text = strtolower($product_name . ' ' . json_encode($sku_detail_array, JSON_UNESCAPED_UNICODE));

    $watchface_keywords = ['表盘', 'watchface', 'watch face', '表面'];
    $has_watchface = false;
    foreach ($watchface_keywords as $keyword) {
        if (strpos($search_text, strtolower($keyword)) !== false) {
            $has_watchface = true;
            break;
        }
    }

    $has_quickapp = false;
    foreach ($quickapp_keywords as $keyword) {
        if (strpos($search_text, strtolower($keyword)) !== false) {
            if ($keyword === 'app' && strpos($search_text, 'watch face') !== false) {
                continue;
            }
            $has_quickapp = true;
            break;
        }
    }

    if ($has_watchface && $has_quickapp) {
        $product_type = 'mixed';
    } elseif ($has_quickapp) {
        $product_type = 'quickapp';
    } else {
        $product_type = 'watchface';
    }

    return $product_type;
}

/**
 * 测试产品类型识别功能
 */
function testProductTypeDetection() {
    $dataPath = __DIR__ . DIRECTORY_SEPARATOR . 'test_order_data.json';
    $testData = json_decode(file_get_contents($dataPath), true);

    if (!$testData) {
        echo "❌ 无法读取测试数据文件\n";
        return false;
    }

    $orders = $testData['orders'];
    $expectedTypes = $testData['expected_product_types'];

    echo "🧪 开始测试产品类型识别功能...\n\n";

    $totalTests = count($orders);
    $passedTests = 0;

    foreach ($orders as $order) {
        $outTradeNo = $order['out_trade_no'];
        $productName = $order['product_name'];
        $skuDetail = $order['sku_detail'];
        $expectedType = $expectedTypes[$outTradeNo];

        $detectedType = determineProductType($productName, $skuDetail);

        $status = ($detectedType === $expectedType) ? '✅ 通过' : '❌ 失败';
        echo "订单号: {$outTradeNo}\n";
        echo "产品名称: {$productName}\n";
        echo "预期类型: {$expectedType}\n";
        echo "识别类型: {$detectedType}\n";
        echo "测试结果: {$status}\n";
        echo str_repeat('-', 50) . "\n";

        if ($detectedType === $expectedType) {
            $passedTests++;
        }
    }

    echo "\n📊 测试总结:\n";
    echo "总测试数: {$totalTests}\n";
    echo "通过数: {$passedTests}\n";
    echo "失败数: " . ($totalTests - $passedTests) . "\n";
    echo "通过率: " . round(($passedTests / $totalTests) * 100, 2) . "%\n";

    return $passedTests === $totalTests;
}

/**
 * 测试关键词识别功能
 */
function testKeywordDetection() {
    echo "\n🔍 测试关键词识别功能...\n\n";

    $testCases = [
        ['华为GT4表盘', [], 'watchface'],
        ['Apple Watch Face Collection', [], 'watchface'],
        ['智能手表表面设计', [], 'watchface'],
        ['天气快应用', [], 'quickapp'],
        ['Calculator QuickApp', [], 'quickapp'],
        ['智能手表应用商店', [], 'quickapp'],
        ['手表小游戏app', [], 'quickapp'],
        ['表盘与快应用套餐', [], 'mixed'],
        ['Watch Face + QuickApp Bundle', [], 'mixed'],
        ['智能手表表盘应用大礼包', [], 'mixed'],
        ['普通商品', [], 'watchface'],
        ['', [], 'watchface'],
    ];

    $passed = 0;
    $total = count($testCases);

    foreach ($testCases as $index => $case) {
        list($productName, $skuDetail, $expected) = $case;
        $detected = determineProductType($productName, $skuDetail);

        $status = ($detected === $expected) ? '✅' : '❌';
        echo sprintf(
            "测试 %02d: %s | 预期: %s | 实际: %s | %s\n",
            $index + 1,
            $productName ?: '(空)',
            $expected,
            $detected,
            $status
        );

        if ($detected === $expected) {
            $passed++;
        }
    }

    echo "\n关键词测试通过率: " . round(($passed / $total) * 100, 2) . "%\n";

    return $passed === $total;
}

if (php_sapi_name() === 'cli') {
    $test1 = testProductTypeDetection();
    $test2 = testKeywordDetection();

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "🎯 最终测试结果: " . (($test1 && $test2) ? '✅ 全部通过' : '❌ 存在失败') . "\n";
    echo str_repeat('=', 60) . "\n";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><meta charset='utf-8'><title>产品类型识别测试</title></head><body>";
    echo "<h1>产品类型识别功能测试</h1>";
    echo "<pre>";

    ob_start();
    $test1 = testProductTypeDetection();
    $test2 = testKeywordDetection();
    $output = ob_get_clean();

    echo htmlspecialchars($output);
    echo "</pre>";
    echo "<h2>最终结果: " . (($test1 && $test2) ? '✅ 全部通过' : '❌ 存在失败') . "</h2>";
    echo "</body></html>";
}
