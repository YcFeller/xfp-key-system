<?php
session_start();

$user_role = $_SESSION['user_role'] ?? null;
$required_role = 2;

if ($user_role === null) {
  header("Location: ../../pages/auth/login.php");
  exit;
} elseif ($user_role < $required_role) {
  header("Location: ../../index.php");
  exit;
}

require_once '../../app/config.php';

$user_id = $_SESSION['user_id'];

// 使用config.php中的mysqli连接，如果不可用则使用PDO
if (isset($mysqli_conn) && $mysqli_conn !== null) {
    $conn = $mysqli_conn;
} else {
    // mysqli不可用，使用PDO模拟mysqli接口
    class PDOWrapper {
        private $pdo;
        
        public function __construct($pdo) {
            $this->pdo = $pdo;
        }
        
        public function prepare($sql) {
            return new PDOStmtWrapper($this->pdo->prepare($sql));
        }
        
        public function close() {
            $this->pdo = null;
        }
    }
    
    class PDOStmtWrapper {
        private $stmt;
        
        public function __construct($stmt) {
            $this->stmt = $stmt;
        }
        
        public function bind_param($types, ...$params) {
            for ($i = 0; $i < count($params); $i++) {
                $this->stmt->bindParam($i + 1, $params[$i]);
            }
        }
        
        public function execute() {
            return $this->stmt->execute();
        }
        
        public function get_result() {
            return new PDOResultWrapper($this->stmt);
        }
        
        public function close() {
            $this->stmt = null;
        }
    }
    
    class PDOResultWrapper {
        private $stmt;
        
        public function __construct($stmt) {
            $this->stmt = $stmt;
        }
        
        public function fetch_assoc() {
            return $this->stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    $conn = new PDOWrapper($conn);
}

$sql = "SELECT username, afdian_user_id, afdian_token, email, avatar_link FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
  die('用户信息未找到');
}

// 获取用户设置
$userSettings = [
  'auto_activation_enabled' => false,
  'email_notifications' => true
];

$settingsSql = "SELECT auto_activation_enabled, email_notifications FROM user_settings WHERE user_id = ?";
$settingsStmt = $conn->prepare($settingsSql);
$settingsStmt->bind_param('i', $user_id);
$settingsStmt->execute();
$settingsResult = $settingsStmt->get_result();

if ($setting = $settingsResult->fetch_assoc()) {
  $userSettings['auto_activation_enabled'] = (bool)$setting['auto_activation_enabled'];
  $userSettings['email_notifications'] = (bool)$setting['email_notifications'];
}

$settingsStmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>XFP - 个人中心</title>
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

    .time-filter-btn {
      background-color: rgba(75, 85, 99, 0.5);
      color: #9CA3AF;
      border: 1px solid rgba(75, 85, 99, 0.3);
    }

    .time-filter-btn:hover {
      background-color: rgba(49, 118, 255, 0.2);
      color: #3176FF;
      border-color: rgba(49, 118, 255, 0.3);
    }

    .time-filter-btn.active {
      background-color: rgba(49, 118, 255, 0.3);
      color: #3176FF;
      border-color: rgba(49, 118, 255, 0.5);
    }

    /* 弹窗样式 */
    .modal-backdrop {
      backdrop-filter: blur(4px);
    }

    .modal-content {
      animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    /* 密码强度指示器动画 */
    .strength-indicator {
      transition: all 0.3s ease;
    }

    /* 验证码输入框样式 */
    .verification-input {
      font-family: 'Courier New', monospace;
      letter-spacing: 0.5em;
    }

    /* 全局消息样式 */
    #globalMessage {
      animation: messageSlideIn 0.3s ease-out;
    }

    @keyframes messageSlideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .message-close-btn {
      transition: all 0.2s ease;
    }

    .message-close-btn:hover {
      transform: scale(1.1);
    }
  </style>
  <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body class="geometric-bg">
  <div id="globalMessage" class="fixed top-0 left-0 w-full z-[9999] flex justify-center pointer-events-none" style="transition: all 0.3s;">
    <!-- 消息内容由JS动态插入 -->
  </div>
  <div class="max-w-[1440px] mx-auto px-8">
    <header class="py-6 flex items-center justify-between flex-wrap">
      <div class="flex items-center gap-4 flex-wrap">
        <h1 class="text-2xl lg:text-3xl font-['Pacifico'] text-white whitespace-nowrap">XFP密钥获取系统</h1>
        <nav class="ml-4 lg:ml-12">
          <ul class="flex flex-wrap gap-3 lg:gap-6 xl:gap-8 text-sm lg:text-base">
            <li><a href="./index.php" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30">个人中心</a></li>
            <li class="relative group">
              <a href="#" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30 flex items-center gap-1">
                我的资源 <i class="fas fa-chevron-down text-xs"></i>
              </a>
              <ul class="absolute top-full left-0 mt-1 bg-gray-800/95 backdrop-blur-lg rounded-lg shadow-lg border border-gray-700/50 min-w-[140px] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                <li><a href="./facelist.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700/50 transition-colors rounded-t-lg">我的表盘</a></li>
                <li><a href="./quickapplist.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700/50 transition-colors rounded-b-lg">我的快应用</a></li>
              </ul>
            </li>
            <li><a href="./orderlist.php" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30">订单中心</a></li>
            <li><a href="./afd_paylist.php" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30">订单同步</a></li>
            <li class="relative group">
              <a href="#" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30 flex items-center gap-1">
                上传管理 <i class="fas fa-chevron-down text-xs"></i>
              </a>
              <ul class="absolute top-full left-0 mt-1 bg-gray-800/95 backdrop-blur-lg rounded-lg shadow-lg border border-gray-700/50 min-w-[140px] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                <li><a href="./facelist_upload.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700/50 transition-colors rounded-t-lg">上传表盘</a></li>
                <li><a href="./quickapplist_upload.php" class="block px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700/50 transition-colors rounded-b-lg">上传快应用</a></li>
              </ul>
            </li>
            <li><a href="./shortcut_tool.php" class="text-gray-300 hover:text-white transition-colors whitespace-nowrap px-2 py-1 rounded hover:bg-gray-700/30">快捷工具</a></li>
          </ul>
        </nav>
      </div>
      <div class="flex items-center gap-4">
        <span class="text-gray-300"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <img src="<?php echo htmlspecialchars($user['avatar_link']); ?>" alt="User Image" class="h-8 w-8 rounded-full">
        <a href="../../index.php" class="!rounded-button px-6 py-2 text-gray-300 hover:text-white transition-colors">返回主页</a>
        <a href="../../pages/auth/logout.php" class="!rounded-button px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors">退出登录</a>
      </div>
    </header>

    <main class="py-20">
      <h1 class="text-5xl font-bold text-white mb-8">个人中心</h1>

      <!-- 数据统计卡片（与首页卡片风格一致） -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
        <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover flex items-center gap-4">
          <div class="flex items-center justify-center w-14 h-14 rounded-full bg-primary/20">
            <i class="fa fa-list-alt text-3xl text-primary"></i>
          </div>
          <div>
            <h3 class="text-xl font-semibold text-white mb-1">表盘订单总数</h3>
            <p class="text-4xl font-bold text-primary" id="watchfaceOrderCount">-</p>
          </div>
        </div>
        <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover flex items-center gap-4">
          <div class="flex items-center justify-center w-14 h-14 rounded-full bg-primary/20">
            <i class="fa fa-clock text-3xl text-primary"></i>
          </div>
          <div>
            <h3 class="text-xl font-semibold text-white mb-1">表盘数量</h3>
            <p class="text-4xl font-bold text-primary" id="watchfaceCount">-</p>
          </div>
        </div>
        <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover flex items-center gap-4">
          <div class="flex items-center justify-center w-14 h-14 rounded-full bg-primary/20">
            <i class="fa fa-bolt text-3xl text-primary"></i>
          </div>
          <div>
            <h3 class="text-xl font-semibold text-white mb-1">激活率</h3>
            <p class="text-4xl font-bold text-primary" id="activationRate">-</p>
          </div>
        </div>
        <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-primary/20">
              <i class="fa fa-yen-sign text-3xl text-primary"></i>
            </div>
            <div>
              <h3 class="text-xl font-semibold text-white mb-1">表盘总金额</h3>
              <p class="text-4xl font-bold text-primary" id="totalAmount">-</p>
            </div>
          </div>
          <div class="flex gap-2">
            <button class="time-filter-btn px-3 py-1 text-sm rounded-lg transition-colors" data-filter="all">全部</button>
            <button class="time-filter-btn px-3 py-1 text-sm rounded-lg transition-colors" data-filter="month">本月</button>
            <button class="time-filter-btn px-3 py-1 text-sm rounded-lg transition-colors" data-filter="week">本周</button>
            <button class="time-filter-btn px-3 py-1 text-sm rounded-lg transition-colors" data-filter="today">今日</button>
      </div>
        </div>
      </div>

      <!-- 统一消息显示区域 -->
      <div id="globalMessage" class="mb-6"></div>

      <!-- 个人信息 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- 左侧：个人信息表单 -->
        <div class="lg:col-span-2 bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6">
          <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa fa-user text-primary"></i>
            <span>个人信息</span>
          </h3>
        <div class="mb-6 flex flex-col md:flex-row md:items-center md:gap-8">
          <div class="flex items-center gap-4 mb-4 md:mb-0">
            <img src="<?php echo htmlspecialchars($user['avatar_link']); ?>" alt="User Image" class="h-16 w-16 rounded-full border-4 border-primary shadow">
            <div>
              <p class="text-xl text-white font-bold mb-1 truncate max-w-xs">用户名：<?php echo htmlspecialchars($user['username']); ?></p>
              <p class="text-sm text-gray-300 mb-1">用户ID：<?php echo htmlspecialchars($user_id); ?></p>
              <p class="text-sm text-gray-300">用户权限：
                <?php
                if ($_SESSION['user_role'] == 1) {
                  echo "客户";
                } elseif ($_SESSION['user_role'] == 2) {
                  echo "用户";
                } elseif ($_SESSION['user_role'] == 3) {
                  echo "管理员";
                } else {
                  echo "未知角色";
                }
                ?>
              </p>
            </div>
          </div>
        </div>
          <form id="profileForm" class="space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="block text-gray-300">邮箱地址</label>
              <input type="email" id="email" name="email" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" value="<?php echo htmlspecialchars($user['email']); ?>" required maxlength="100">
            </div>
            <div class="space-y-2">
              <label class="block text-gray-300">爱发电用户ID</label>
              <input type="text" id="afdian_user_id" name="afdian_user_id" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" value="<?php echo htmlspecialchars($user['afdian_user_id']); ?>" required maxlength="100">
            </div>
            <div class="space-y-2 md:col-span-2">
              <label class="block text-gray-300">爱发电Token</label>
              <input type="text" id="afdian_token" name="afdian_token" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" value="<?php echo htmlspecialchars($user['afdian_token']); ?>" required maxlength="200">
            </div>
          </div>

            <div class="mt-4 bg-gray-700/30 rounded-lg p-4">
              <h4 class="text-base font-semibold text-white mb-2 flex items-center gap-2">
                <i class="fa fa-shield-alt text-primary"></i>安全验证
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                  <input type="password" id="currentPassword" name="current_password" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none mb-2" placeholder="当前密码（二选一）">
                </div>
                <div class="flex gap-2">
                  <input type="text" id="profileVerificationCode" name="profile_verification_code" class="flex-1 px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" placeholder="邮箱验证码（二选一）" maxlength="6">
                  <button type="button" id="sendProfileCodeBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-opacity-90 transition-colors whitespace-nowrap text-sm flex items-center gap-1">
                    <span id="sendProfileCodeBtnText">发送</span>
                    <span id="sendProfileCodeBtnLoading" class="hidden"><i class="fa fa-spinner fa-spin"></i></span>
                  </button>
                </div>
              </div>
              <p class="text-xs text-gray-400 mt-2">验证码将发送到：<?php echo htmlspecialchars($user['email']); ?>。修改信息前请验证当前密码或邮箱验证码（二选一）。</p>
            </div>

            <button type="submit" id="saveBtn" class="w-full rounded-button py-4 bg-primary text-white text-lg font-semibold hover:bg-opacity-90 transition-colors flex items-center justify-center gap-2 disabled:opacity-60 mt-2">
            <span id="saveBtnText">保存修改</span>
            <span id="saveBtnLoading" class="hidden"><i class="fa fa-spinner fa-spin"></i> 保存中...</span>
          </button>

            <!-- 新增：用户设置区 -->
            <div class="mt-6 bg-gray-700/30 rounded-lg p-4">
              <h4 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fa fa-cog text-primary"></i>用户设置
              </h4>
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <div>
                    <div class="text-white font-medium">自动激活提醒</div>
                    <div class="text-sm text-gray-400">开启后，用户访问首页时会自动检查未激活订单并提醒</div>
                  </div>
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="userAutoActivationToggle" class="sr-only peer" <?php echo $userSettings['auto_activation_enabled'] ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                  </label>
                </div>
                <div class="flex items-center justify-between">
                  <div>
                    <div class="text-white font-medium">邮件通知</div>
                    <div class="text-sm text-gray-400">接收重要通知和更新提醒</div>
                  </div>
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="userEmailNotificationsToggle" class="sr-only peer" <?php echo $userSettings['email_notifications'] ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                  </label>
                </div>
              </div>
              <button type="button" id="saveSettingsBtn" class="mt-4 w-full rounded-button py-3 bg-secondary text-white text-base font-semibold hover:bg-opacity-90 transition-colors flex items-center justify-center gap-2 disabled:opacity-60">
                <span id="saveSettingsBtnText">保存设置</span>
                <span id="saveSettingsBtnLoading" class="hidden"><i class="fa fa-spinner fa-spin"></i> 保存中...</span>
              </button>
            </div>
        </form>

          <!-- 密码修改部分 -->
          <hr class="my-8 border-gray-700">
          <div class="mt-6">
            <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
              <i class="fa fa-lock text-primary"></i>
              <span>账户安全</span>
            </h3>
            <div class="flex gap-4">
              <button id="changePasswordBtn" class="px-6 py-3 bg-secondary text-white rounded-lg hover:bg-opacity-90 transition-colors flex items-center gap-2">
                <i class="fa fa-key"></i>
                <span>修改密码</span>
              </button>
              <div class="text-sm text-gray-400 flex items-center">
                <i class="fa fa-info-circle mr-2"></i>
                上次修改：<span id="lastPasswordChange"><?php echo $user['updated_at'] ?? '从未修改'; ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- 右侧：快速跳转卡片 -->
        <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6">
          <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa fa-rocket text-primary"></i>
            <span>快速跳转</span>
          </h3>
          <div class="space-y-3">
            <a href="./facelist.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
              <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/20 group-hover:bg-primary/30 transition-colors">
                <i class="fa fa-clock text-lg text-primary"></i>
              </div>
              <div>
                <p class="text-white font-semibold">我的表盘</p>
                <p class="text-xs text-gray-400">管理您的表盘列表</p>
              </div>
              <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-primary transition-colors"></i>
            </a>
            
            <a href="./orderlist.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
              <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/20 group-hover:bg-primary/30 transition-colors">
                <i class="fa fa-list-alt text-lg text-primary"></i>
              </div>
              <div>
                <p class="text-white font-semibold">订单中心</p>
                <p class="text-xs text-gray-400">查看和管理订单</p>
              </div>
              <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-primary transition-colors"></i>
            </a>
            
            <a href="./afd_paylist.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
              <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/20 group-hover:bg-primary/30 transition-colors">
                <i class="fa fa-sync-alt text-lg text-primary"></i>
              </div>
              <div>
                <p class="text-white font-semibold">订单同步</p>
                <p class="text-xs text-gray-400">同步爱发电订单</p>
              </div>
              <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-primary transition-colors"></i>
            </a>
            
            <a href="./facelist_upload.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
              <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/20 group-hover:bg-primary/30 transition-colors">
                <i class="fa fa-upload text-lg text-primary"></i>
              </div>
              <div>
                <p class="text-white font-semibold">上传表盘</p>
                <p class="text-xs text-gray-400">添加新的表盘</p>
              </div>
              <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-primary transition-colors"></i>
            </a>
            
            <a href="./shortcut_tool.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
              <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/20 group-hover:bg-primary/30 transition-colors">
                <i class="fa fa-tools text-lg text-primary"></i>
              </div>
              <div>
                <p class="text-white font-semibold">快捷工具</p>
                <p class="text-xs text-gray-400">使用便捷工具</p>
              </div>
              <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-primary transition-colors"></i>
            </a>
            
            <a href="../../app/test_mail.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
              <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/20 group-hover:bg-primary/30 transition-colors">
                <i class="fa fa-envelope text-lg text-primary"></i>
              </div>
              <div>
                <p class="text-white font-semibold">邮件测试</p>
                <p class="text-xs text-gray-400">测试邮件发送功能</p>
              </div>
              <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-primary transition-colors"></i>
            </a>
            
            <a href="../../pages/user/activation_records.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
              <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/20 group-hover:bg-primary/30 transition-colors">
                <i class="fa fa-history text-lg text-primary"></i>
              </div>
              <div>
                <p class="text-white font-semibold">激活记录</p>
                <p class="text-xs text-gray-400">查看激活历史</p>
              </div>
              <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-primary transition-colors"></i>
            </a>
          </div>
        </div>
      </div>

      <hr class="my-8 border-gray-700">
      
      <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6">
        <h3 class="text-2xl font-bold text-white mb-4">如何使订单能够自动更新？</h3>
        <p class="text-gray-300 mb-4">请参考<a href="../../pages/wow.html" target="_blank" class="text-primary hover:underline">跳转教程</a></p>
        
        <h3 class="text-2xl font-bold text-white mt-6 mb-4">严正声明</h3>
        <p class="text-red-400 font-bold text-lg">请详细阅读，若您继续使用则代表您同意此声明！</p>
        <p class="text-gray-300 mt-2">XFP是一个简单的小型工具站点，我们尽力确保网站和工具的安全性，但无法保证完全没有泄露可能性。我们使用加密技术、防火墙和定期安全扫描保护数据，但无措施能保证100%安全，建议您采取适当预防措施。</p>
        <p class="text-gray-300 mt-2">我们尊重您的隐私，收集的信息仅用于改进服务，不会出售给第三方。使用服务即视为同意本声明。</p>
      </div>

      <hr class="my-8 border-gray-700">

      <!-- 版本信息展示 -->
      <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 mb-8 w-full max-w-3xl card-hover text-left">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-2xl font-bold text-white flex items-center gap-2">
            <i class="fa fa-code-branch text-primary"></i>
            <span>版本信息</span>
          </h2>
          <div class="flex items-center gap-2">
            <button id="prevVersion" class="px-3 py-1 text-sm bg-gray-700/50 text-white rounded-lg hover:bg-gray-600/50 transition-colors disabled:opacity-50" disabled>
              <i class="fa fa-chevron-up"></i>
            </button>
            <button id="nextVersion" class="px-3 py-1 text-sm bg-gray-700/50 text-white rounded-lg hover:bg-gray-600/50 transition-colors disabled:opacity-50">
              <i class="fa fa-chevron-down"></i>
            </button>
          </div>
        </div>
        
        <div id="versionContent">
          <p class="text-lg text-primary font-semibold mb-3">当前版本：v3.1.1</p>
          <div class="text-gray-300 text-sm">
            <h3 class="font-semibold text-white mb-2">更新记录：</h3>
            <div class="space-y-2">
              <div class="p-3 bg-gray-700/30 rounded-lg">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-primary font-semibold">v3.1.1</span>
                  <span class="text-gray-400 text-xs">2025-06-18</span>
                </div>
                <ul class="text-xs space-y-1 ml-4">
                  <li>• 新增订单搜索激活模式，可直接搜索订单号和设备码，并确认激活</li>
                  <li>• 新增版本号和更新记录显示</li>
                  <li>• 等等</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
      
    </main>
  </div>

  <!-- 密码修改弹窗 -->
  <div id="passwordModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-backdrop">
    <div class="bg-gray-800 rounded-2xl p-6 w-full max-w-md mx-4 modal-content">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-xl font-bold text-white flex items-center gap-2">
          <i class="fa fa-lock text-primary"></i>
          <span>修改密码</span>
        </h3>
        <button id="closePasswordModal" class="text-gray-400 hover:text-white transition-colors">
          <i class="fa fa-times text-xl"></i>
        </button>
      </div>
      
      <form id="passwordForm" class="space-y-4">
        <div class="space-y-2">
          <label class="block text-gray-300 text-sm">邮箱验证码</label>
          <div class="flex gap-2">
            <input type="text" id="verificationCode" name="verification_code" class="flex-1 px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none text-center text-lg tracking-widest verification-input" placeholder="000000" maxlength="6" required>
            <button type="button" id="sendCodeBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-opacity-90 transition-colors whitespace-nowrap text-sm">
              <span id="sendCodeBtnText">发送</span>
              <span id="sendCodeBtnLoading" class="hidden"><i class="fa fa-spinner fa-spin"></i></span>
            </button>
          </div>
          <p class="text-xs text-gray-400">验证码将发送到：<?php echo htmlspecialchars($user['email']); ?></p>
        </div>
        
        <div class="space-y-2">
          <label class="block text-gray-300 text-sm">新密码</label>
          <input type="password" id="newPassword" name="new_password" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" placeholder="请输入新密码" required minlength="6">
          <div class="flex items-center gap-2 text-xs text-gray-400">
            <span>密码强度：</span>
            <div class="flex gap-1">
              <div id="strength1" class="w-2 h-2 rounded-full bg-gray-600 strength-indicator"></div>
              <div id="strength2" class="w-2 h-2 rounded-full bg-gray-600 strength-indicator"></div>
              <div id="strength3" class="w-2 h-2 rounded-full bg-gray-600 strength-indicator"></div>
              <div id="strength4" class="w-2 h-2 rounded-full bg-gray-600 strength-indicator"></div>
            </div>
          </div>
        </div>
        
        <div class="space-y-2">
          <label class="block text-gray-300 text-sm">确认新密码</label>
          <input type="password" id="confirmPassword" name="confirm_password" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" placeholder="请再次输入新密码" required minlength="6">
        </div>
        
        <div class="bg-gray-700/30 rounded-lg p-3">
          <h4 class="text-sm font-semibold text-white mb-2">密码要求：</h4>
          <ul class="text-xs text-gray-300 space-y-1">
            <li id="reqLength" class="flex items-center gap-2">
              <i class="fa fa-circle text-gray-500"></i>
              至少8位字符
            </li>
            <li id="reqMatch" class="flex items-center gap-2">
              <i class="fa fa-circle text-gray-500"></i>
              两次输入一致
            </li>
            <li id="reqStrength" class="flex items-center gap-2">
              <i class="fa fa-circle text-gray-500"></i>
              包含字母和数字
            </li>
          </ul>
        </div>
        
        <div class="flex gap-3 pt-2">
          <button type="button" id="cancelPasswordBtn" class="flex-1 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-500 transition-colors">
            取消
          </button>
          <button type="submit" id="resetPasswordBtn" class="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:bg-opacity-90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            <span id="resetPasswordBtnText">确认修改</span>
            <span id="resetPasswordBtnLoading" class="hidden"><i class="fa fa-spinner fa-spin"></i></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    $(document).ready(function() {
      // 版本数据
      const versions = [
        {
          version: 'v3.1.2',
          date: '2025-06-19',
          changes: [
            '新增总金额数据统计',
            '新增密码修改功能',
            '新增版本号和更新记录显示',
            '优化大部分代码逻辑',
            '优化消息提示功能',
            '优化订单查询逻辑',
            '等等'
          ]
        },
        {
          version: 'v3.1.1',
          date: '2025-06-18',
          changes: [
            '新增订单搜索激活模式，可直接搜索订单号和设备码，并确认激活',
            '新增版本号和更新记录显示',
            '等等'
          ]
        },
        {
          version: 'v3.0.1(大版本更新)',
          date: '2025-06-18',
          changes: [
            '重构后台全部样式，优化部分代码逻辑',
            '优化个人中心页面样式，修复部分显示问题',
            '新增爱发电订单同步功能入口',
            '增加版本号和更新记录显示',
            '等等'
          ]
        }
      ];
      
      // 全局变量
      let currentTimeFilter = 'all';
      let currentVersionIndex = 0;
      
      // 时间筛选功能
      $('.time-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        currentTimeFilter = filter;
        
        // 更新按钮状态
        $('.time-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // 重新加载统计数据
        loadStats();
      });

      // 默认选中"全部"按钮
      $('.time-filter-btn[data-filter="all"]').addClass('active');

      // 加载统计数据函数
      function loadStats() {
        $.ajax({
          url: '../../app/user_dashboard_api.php',
          method: 'GET',
          data: { time_filter: currentTimeFilter },
          success: function(data) {
            let stats;
            try {
              stats = typeof data === 'string' ? JSON.parse(data) : data;
            } catch {
              stats = {};
            }
            
            const elements = ['watchfaceOrderCount', 'watchfaceCount', 'activationRate', 'totalAmount'];
            const values = [stats.watchfaceOrderCount, stats.watchfaceCount, stats.activationRate, stats.totalAmount];
            
            if (stats.success) {
              elements.forEach((element, index) => {
                $(`#${element}`).text(values[index] ?? '-');
              });
            } else {
              elements.forEach(element => {
                $(`#${element}`).text('-');
              });
              if (stats.error) {
                showMessage('统计数据加载失败：' + stats.error, 'error');
              }
            }
          },
          error: function() {
            ['watchfaceOrderCount', 'watchfaceCount', 'activationRate', 'totalAmount'].forEach(element => {
              $(`#${element}`).text('-');
            });
            showMessage('统计数据加载失败', 'error');
          }
        });
      }

      // 显示消息函数
      function showMessage(message, type = 'success') {
        let colorClass, bgClass, borderClass, iconClass;
        if (type === 'error') {
          colorClass = 'text-red-500';
          bgClass = 'bg-red-50';
          borderClass = 'border-red-400/60';
          iconClass = 'fa-exclamation-circle';
        } else {
          colorClass = 'text-emerald-600';
          bgClass = 'bg-emerald-50';
          borderClass = 'border-emerald-400/60';
          iconClass = 'fa-check-circle';
        }
        const messageHtml = `
          <div class="p-4 rounded-lg border-2 ${bgClass} ${borderClass} flex items-center gap-3 shadow-xl max-w-xl w-full mt-4 pointer-events-auto animate-slideDown">
            <i class="fa ${iconClass} text-lg ${colorClass}"></i>
            <span class="${colorClass} font-medium">${message}</span>
            <button onclick="hideGlobalMessage()" class="ml-auto text-gray-400 hover:text-gray-700 transition-colors message-close-btn">
              <i class="fa fa-times"></i>
            </button>
          </div>
        `;
        $('#globalMessage').html(messageHtml);
        setTimeout(() => { hideGlobalMessage(); }, 3000);
      }

      // 隐藏全局消息
      function hideGlobalMessage() {
        $('#globalMessage').empty();
      }

      // 显示密码相关消息
      function showPasswordMessage(message, type = 'success') {
        showMessage(message, type);
      }

      // 更新版本显示函数
      function updateVersionDisplay() {
        const version = versions[currentVersionIndex];
        const content = `
          <p class="text-lg text-primary font-semibold mb-3">当前版本：${version.version}</p>
          <div class="text-gray-300 text-sm">
            <h3 class="font-semibold text-white mb-2">更新记录：</h3>
            <div class="space-y-2">
              <div class="p-3 bg-gray-700/30 rounded-lg">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-primary font-semibold">${version.version}</span>
                  <span class="text-gray-400 text-xs">${version.date}</span>
                </div>
                <ul class="text-xs space-y-1 ml-4">
                  ${version.changes.map(change => `<li>• ${change}</li>`).join('')}
                </ul>
              </div>
            </div>
          </div>
        `;
        $('#versionContent').html(content);
        
        // 更新按钮状态
        $('#prevVersion').prop('disabled', currentVersionIndex === 0);
        $('#nextVersion').prop('disabled', currentVersionIndex === versions.length - 1);
      }

      // 版本切换事件
      $('#prevVersion').click(function() {
        if (currentVersionIndex > 0) {
          currentVersionIndex--;
          updateVersionDisplay();
        }
      });
      
      $('#nextVersion').click(function() {
        if (currentVersionIndex < versions.length - 1) {
          currentVersionIndex++;
          updateVersionDisplay();
        }
      });

      // 个人资料表单提交
      $('#profileForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        const $saveBtn = $('#saveBtn');
        const $saveBtnText = $('#saveBtnText');
        const $saveBtnLoading = $('#saveBtnLoading');
        const password = $('#currentPassword').val().trim();
        const code = $('#profileVerificationCode').val().trim();
        
        // 必须二选一
        if (!password && !code) {
          showMessage('请填写当前密码或邮箱验证码进行验证', 'error');
          return;
        }
        
        // 禁用按钮并显示加载状态
        $saveBtn.prop('disabled', true);
        $saveBtnText.addClass('hidden');
        $saveBtnLoading.removeClass('hidden');
        
        $.ajax({
          url: '../../app/userback_action.php',
          method: 'POST',
          data: formData,
          success: function(response) {
            showMessage(response);
            resetButtonState();
          },
          error: function(xhr) {
            showMessage('发生错误: ' + xhr.statusText, 'error');
            resetButtonState();
          }
        });
        
        function resetButtonState() {
          $saveBtn.prop('disabled', false);
          $saveBtnText.removeClass('hidden');
          $saveBtnLoading.addClass('hidden');
        }
      });

      // 发送邮箱验证码按钮逻辑
      $('#sendProfileCodeBtn').on('click', function() {
        const $btn = $(this);
        const $btnText = $('#sendProfileCodeBtnText');
        const $btnLoading = $('#sendProfileCodeBtnLoading');
        $btn.prop('disabled', true);
        $btnText.addClass('hidden');
        $btnLoading.removeClass('hidden');
      $.ajax({
          url: '../../app/password_reset_api.php',
          method: 'POST',
          data: { action: 'send_code' },
          success: function(response) {
            let result;
            try {
              result = typeof response === 'string' ? JSON.parse(response) : response;
          } catch {
              result = { success: false, error: '响应解析失败' };
            }
            if (result.success) {
              showMessage('验证码已发送，请查收邮箱', 'success');
              startProfileCodeCountdown();
            } else {
              showMessage(result.error || '发送失败', 'error');
              resetProfileCodeBtn();
            }
          },
          error: function(xhr) {
            showMessage('发送失败: ' + xhr.statusText, 'error');
            resetProfileCodeBtn();
          }
        });
        function resetProfileCodeBtn() {
          $btn.prop('disabled', false);
          $btnText.removeClass('hidden');
          $btnLoading.addClass('hidden');
        }
      });
      function startProfileCodeCountdown() {
        let countdown = 60;
        const $btn = $('#sendProfileCodeBtn');
        const $btnText = $('#sendProfileCodeBtnText');
        const $btnLoading = $('#sendProfileCodeBtnLoading');
        $btn.prop('disabled', true);
        $btnText.removeClass('hidden');
        $btnLoading.addClass('hidden');
        $btnText.text(`${countdown}s`);
        const timer = setInterval(function() {
          countdown--;
          $btnText.text(`${countdown}s`);
          if (countdown <= 0) {
            clearInterval(timer);
            $btn.prop('disabled', false);
            $btnText.text('发送验证码');
          }
        }, 1000);
      }

      // 密码修改表单提交
      $('#passwordForm').on('submit', function(e) {
        e.preventDefault();
        
        // 检查密码要求是否满足
        if (!validatePasswordRequirements()) {
          showPasswordMessage('请满足所有密码要求', 'error');
          return;
        }
        
        const formData = $(this).serialize();
        const $resetPasswordBtn = $('#resetPasswordBtn');
        const $resetPasswordBtnText = $('#resetPasswordBtnText');
        const $resetPasswordBtnLoading = $('#resetPasswordBtnLoading');
        
        // 禁用按钮并显示加载状态
        $resetPasswordBtn.prop('disabled', true);
        $resetPasswordBtnText.addClass('hidden');
        $resetPasswordBtnLoading.removeClass('hidden');
        
        $.ajax({
          url: '../../app/password_reset_api.php',
          method: 'POST',
          data: formData + '&action=verify_and_reset',
          success: function(response) {
            let result;
            try {
              result = typeof response === 'string' ? JSON.parse(response) : response;
            } catch {
              result = { success: false, error: '响应解析失败' };
            }
            
            if (result.success) {
              showPasswordMessage(result.message, 'success');
              $('#passwordForm')[0].reset();
              resetPasswordStrength();
              // 3秒后关闭弹窗
              setTimeout(() => {
                closePasswordModal();
              }, 3000);
          } else {
              showPasswordMessage(result.error, 'error');
            }
            resetButtonState();
          },
          error: function(xhr) {
            showPasswordMessage('发生错误: ' + xhr.statusText, 'error');
            resetButtonState();
          }
        });
        
        function resetButtonState() {
          $resetPasswordBtn.prop('disabled', false);
          $resetPasswordBtnText.removeClass('hidden');
          $resetPasswordBtnLoading.addClass('hidden');
        }
      });

      // 弹窗控制
      $('#changePasswordBtn').on('click', function() {
        openPasswordModal();
      });
      
      $('#closePasswordModal, #cancelPasswordBtn').on('click', function() {
        closePasswordModal();
      });
      
      // 点击弹窗外部关闭
      $('#passwordModal').on('click', function(e) {
        if (e.target === this) {
          closePasswordModal();
        }
      });
      
      // 键盘事件处理
      $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && !$('#passwordModal').hasClass('hidden')) {
          closePasswordModal();
        }
      });
      
      // 验证码输入框自动跳转
      $('#verificationCode').on('input', function() {
        const value = $(this).val();
        if (value.length === 6) {
          $('#newPassword').focus();
        }
      });
      
      function openPasswordModal() {
        $('#passwordModal').removeClass('hidden');
        $('body').addClass('overflow-hidden');
        $('#verificationCode').focus();
      }
      
      function closePasswordModal() {
        $('#passwordModal').addClass('hidden');
        $('body').removeClass('overflow-hidden');
        $('#passwordForm')[0].reset();
        resetPasswordStrength();
      }

      // 密码强度验证
      $('#newPassword').on('input', function() {
        checkPasswordStrength();
        validatePasswordRequirements();
      });
      
      $('#confirmPassword').on('input', function() {
        validatePasswordRequirements();
      });
      
      function checkPasswordStrength() {
        const password = $('#newPassword').val();
        const strength = calculatePasswordStrength(password);
        
        // 重置强度指示器
        $('.w-2.h-2.rounded-full').removeClass('bg-red-500 bg-yellow-500 bg-green-500').addClass('bg-gray-600');
        
        // 根据强度设置颜色
        if (strength >= 1) $('#strength1').removeClass('bg-gray-600').addClass('bg-red-500');
        if (strength >= 2) $('#strength2').removeClass('bg-gray-600').addClass('bg-yellow-500');
        if (strength >= 3) $('#strength3').removeClass('bg-gray-600').addClass('bg-green-500');
        if (strength >= 4) $('#strength4').removeClass('bg-gray-600').addClass('bg-green-500');
      }
      
      function calculatePasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.length >= 10) strength++;
        if (/[a-zA-Z]/.test(password) && /[0-9]/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        
        return strength;
      }
      
      function validatePasswordRequirements() {
        const password = $('#newPassword').val();
        const confirmPassword = $('#confirmPassword').val();
        
        // 检查长度
        const lengthValid = password.length >= 8;
        $('#reqLength i').removeClass('text-gray-500 text-green-500 text-red-500')
          .addClass(lengthValid ? 'text-green-500' : 'text-red-500');
        
        // 检查匹配
        const matchValid = password === confirmPassword && password.length > 0;
        $('#reqMatch i').removeClass('text-gray-500 text-green-500 text-red-500')
          .addClass(matchValid ? 'text-green-500' : 'text-red-500');
        
        // 检查强度
        const strengthValid = /[a-zA-Z]/.test(password) && /[0-9]/.test(password);
        $('#reqStrength i').removeClass('text-gray-500 text-green-500 text-red-500')
          .addClass(strengthValid ? 'text-green-500' : 'text-red-500');
        
        // 更新提交按钮状态
        const allValid = lengthValid && matchValid && strengthValid;
        $('#resetPasswordBtn').prop('disabled', !allValid);
        
        return allValid;
      }
      
      function resetPasswordStrength() {
        $('.w-2.h-2.rounded-full').removeClass('bg-red-500 bg-yellow-500 bg-green-500').addClass('bg-gray-600');
        $('#reqLength i, #reqMatch i, #reqStrength i').removeClass('text-green-500 text-red-500').addClass('text-gray-500');
        $('#resetPasswordBtn').prop('disabled', true);
      }

      // 发送验证码按钮点击事件
      $('#sendCodeBtn').on('click', function() {
        const $sendCodeBtn = $(this);
        const $sendCodeBtnText = $('#sendCodeBtnText');
        const $sendCodeBtnLoading = $('#sendCodeBtnLoading');
        
        // 禁用按钮并显示加载状态
        $sendCodeBtn.prop('disabled', true);
        $sendCodeBtnText.addClass('hidden');
        $sendCodeBtnLoading.removeClass('hidden');
        
        $.ajax({
          url: '../../app/password_reset_api.php',
          method: 'POST',
          data: { action: 'send_code' },
          success: function(response) {
            let result;
            try {
              result = typeof response === 'string' ? JSON.parse(response) : response;
            } catch {
              result = { success: false, error: '响应解析失败' };
            }
            
            if (result.success) {
              showPasswordMessage(result.message, 'success');
              startCountdown();
            } else {
              showPasswordMessage(result.error, 'error');
              resetSendCodeButton();
            }
          },
          error: function(xhr) {
            showPasswordMessage('发送失败: ' + xhr.statusText, 'error');
            resetSendCodeButton();
          }
        });
      });

      // 重置发送验证码按钮状态
      function resetSendCodeButton() {
        const $sendCodeBtn = $('#sendCodeBtn');
        const $sendCodeBtnText = $('#sendCodeBtnText');
        const $sendCodeBtnLoading = $('#sendCodeBtnLoading');
        
        $sendCodeBtn.prop('disabled', false);
        $sendCodeBtnText.removeClass('hidden');
        $sendCodeBtnLoading.addClass('hidden');
      }

      // 开始倒计时
      function startCountdown() {
        let countdown = 60;
        const $sendCodeBtn = $('#sendCodeBtn');
        const $sendCodeBtnText = $('#sendCodeBtnText');
        const $sendCodeBtnLoading = $('#sendCodeBtnLoading');
        
        $sendCodeBtn.prop('disabled', true);
        $sendCodeBtnText.removeClass('hidden');
        $sendCodeBtnLoading.addClass('hidden');
        
        const timer = setInterval(function() {
          $sendCodeBtnText.text(`${countdown}s`);
          countdown--;
          
          if (countdown < 0) {
            clearInterval(timer);
            $sendCodeBtn.prop('disabled', false);
            $sendCodeBtnText.text('发送');
          }
        }, 1000);
      }

      // 初始化
      loadStats();
      updateVersionDisplay();
      loadUserPasswordInfo();

      // 加载用户密码修改信息
      function loadUserPasswordInfo() {
        $.ajax({
          url: '../../app/password_reset_api.php',
          method: 'POST',
          data: { action: 'get_user_info' },
          success: function(response) {
            let result;
            try {
              result = typeof response === 'string' ? JSON.parse(response) : response;
            } catch {
              result = { success: false };
            }
            
            if (result.success && result.info) {
              const info = result.info;
              $('#lastPasswordChange').text(`上次修改：${info.last_update}`);
            } else {
              $('#lastPasswordChange').text('从未修改');
          }
        },
        error: function() {
            $('#lastPasswordChange').text('获取信息失败');
          }
        });
      }

      // 用户设置保存
      $('#saveSettingsBtn').on('click', function() {
        const $btn = $(this);
        const $btnText = $('#saveSettingsBtnText');
        const $btnLoading = $('#saveSettingsBtnLoading');
        const autoActivation = $('#userAutoActivationToggle').is(':checked') ? 1 : 0;
        const emailNotifications = $('#userEmailNotificationsToggle').is(':checked') ? 1 : 0;
        
        $btn.prop('disabled', true);
        $btnText.hide();
        $btnLoading.show();
        
        $.ajax({
          url: '../../app/user/user_settings_api.php',
          method: 'POST',
          data: {
            action: 'update_settings',
            auto_activation_enabled: autoActivation,
            email_notifications: emailNotifications,
            theme_preference: 'dark',
            language_preference: 'zh-CN'
          },
          success: function(response) {
            if (response.success) {
              showMessage(response.message);
            } else {
              showMessage(response.error, 'error');
            }
            resetSettingsButton();
          },
          error: function(xhr) {
            showMessage('设置更新失败: ' + xhr.statusText, 'error');
            resetSettingsButton();
          }
        });
        
        function resetSettingsButton() {
          $btn.prop('disabled', false);
          $btnText.show();
          $btnLoading.hide();
        }
      });
    });
  </script>
</body>

</html>