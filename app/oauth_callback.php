<?php
session_start();
require_once './config.php';

// 验证 state 参数
if ($_GET['state'] !== '111') {
  die('非法请求');
}

// 获取 code 参数
$code = $_GET['code'];

// 准备 POST 请求数据
$post_data = [
  'grant_type' => 'authorization_code',
  'client_id' => 'xurikeji',  // 你需要替换为爱发电提供的 client_id
  'client_secret' => 'f1b2c3d4e5f6g7h8i9j0k1l',  // 你需要替换为爱发电提供的 client_secret
  'code' => $code,
  'redirect_uri' => 'https://xfp.fs0.top/app/oauth_callback.php'
];

// 发送 POST 请求以获取 access_token
$ch = curl_init('https://afdian.com/api/oauth2/access_token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
$response = curl_exec($ch);
curl_close($ch);

// 解析响应
$response_data = json_decode($response, true);

if ($response_data['ec'] === 200) {
  $user_id = $response_data['data']['user_id'];
  $name = $response_data['data']['name'];
  $avatar = $response_data['data']['avatar'];
  $afd_private_id = $response_data['data']['user_private_id'];

  //如果已经注册过，直接登录
  //查询数据库，name和afd_private_id是否存在
  $conn = new mysqli($servername, $db_user, $db_pass, $dbname);
  if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
  }

  $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE afdian_user_id = ?");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    // 用户已存在，更新信息并登录
    $user = $result->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    // 弹窗提醒后跳转到首页
    echo '<script>alert("登录成功！");</script>';
    header('Location: ../../index.php');
  } else {
    // 如果没有注册过，跳转到注册页面
    // 传递到注册页面的 URL
    $register_url = 'https://xfp.fs0.top/pages/auth/register.php?' . http_build_query([
      'oauth_user_id' => $user_id,
      'oauth_user_name' => $name,
      'oauth_user_avatar' => $avatar,
    ]);

    // 数据输出测试
    if ($all_debug == true) {
      echo "$user_id用户ID: " . $user_id . "<br>";
      echo "$name用户名: " . $name . "<br>";
      echo "$avatar头像: " . $avatar . "<br>";
      echo '<img src="' . $avatar . '" alt="用户头像"><br>';
    } else {
      // 重定向到注册页面
      echo '<script>alert("请在下个页面完成注册！");</script>';
      header('Location: ' . $register_url);
    }
  }

  $stmt->close();
  $conn->close();
  exit;
} else {
  echo "获取用户信息失败: " . $response_data['em'];
}
