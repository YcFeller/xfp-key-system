<?php
// 配置爱发电 OAuth2 相关信息
$client_id = 'xurikeji';  // 你需要替换为爱发电提供的 client_id
$redirect_uri = 'https://xfp.fs0.top/app/oauth_callback.php';  // 你需要将此回调URL添加到你在爱发电上的应用配置中

// 生成跳转链接
$auth_url = "https://afdian.com/oauth2/authorize?response_type=code&scope=basic&client_id={$client_id}&redirect_uri=" . urlencode($redirect_uri) . "&state=111";

// 重定向用户到授权页面
header('Location: ' . $auth_url);
exit;
