<?php
session_start();

// 生成随机验证码（使用数字和字母组合）
$chars = '0123456789abcdefghijklmnopqrstuvwxyz';
$captcha = '';
for ($i = 0; $i < 4; $i++) {
    $captcha .= $chars[rand(0, strlen($chars) - 1)];
}

// 保存验证码到会话变量
$_SESSION['captcha'] = $captcha;

// 创建画布
$image = imagecreatetruecolor(80, 35);

// 设置背景颜色（深色背景）
$bg_color = imagecolorallocate($image, 30, 30, 30);
imagefill($image, 0, 0, $bg_color);

// 添加干扰线
for ($i = 0; $i < 3; $i++) {
    $line_color = imagecolorallocate($image, rand(100, 200), rand(100, 200), rand(100, 200));
    imageline($image, rand(0, 80), rand(0, 35), rand(0, 80), rand(0, 35), $line_color);
}

// 添加干扰点
for ($i = 0; $i < 50; $i++) {
    $pixel_color = imagecolorallocate($image, rand(100, 200), rand(100, 200), rand(100, 200));
    imagesetpixel($image, rand(0, 80), rand(0, 35), $pixel_color);
}

// 设置验证码文本颜色（白色）
$text_color = imagecolorallocate($image, 255, 255, 255);

// 在画布上绘制验证码（使用更好的字体大小和位置）
imagestring($image, 5, 15, 8, $captcha, $text_color);

// 设置响应头，告诉浏览器输出的是图片
header("Content-type: image/png");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// 输出图像
imagepng($image);

// 释放内存
imagedestroy($image);
