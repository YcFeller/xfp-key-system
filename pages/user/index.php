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
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// 获取用户设置
$stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userSettings = $result->fetch_assoc();
$stmt->close();

// 如果用户设置不存在，创建默认设置
if (!$userSettings) {
    $stmt = $conn->prepare("INSERT INTO user_settings (user_id, auto_activation_enabled, email_notifications, theme_preference, language_preference) VALUES (?, 0, 1, 'dark', 'zh-CN')");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    
    $userSettings = [
        'auto_activation_enabled' => 0,
        'email_notifications' => 1,
        'theme_preference' => 'dark',
        'language_preference' => 'zh-CN'
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客户中心 - XFP密钥获取系统</title>
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

        /* 切换开关样式 */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #6c757d;
            transition: 0.4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #3176FF;
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slideDown { animation: slideDown 0.3s ease; }
    </style>
    <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body class="geometric-bg">
    <div id="globalMessage" class="fixed top-0 left-0 w-full z-[9999] flex justify-center pointer-events-none" style="transition: all 0.3s;">
        <!-- 消息内容由JS动态插入 -->
    </div>
    <div class="max-w-[1440px] mx-auto px-8">
        <!-- 头部导航 -->
        <header class="py-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-['Pacifico'] text-white">XFP密钥获取系统</h1>
                <nav class="ml-12">
                    <ul class="flex gap-8">
                        <li><a href="./index.php" class="text-primary font-semibold">客户中心</a></li>
                        <li><a href="./my_orders.php" class="text-gray-300 hover:text-white transition-colors">我的订单</a></li>
                        <li><a href="./activation_records.php" class="text-gray-300 hover:text-white transition-colors">激活记录</a></li>
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
            <h1 class="text-5xl font-bold text-white mb-8">客户中心</h1>
            <p class="text-xl text-gray-300 mb-12">欢迎回来，<?php echo htmlspecialchars($user['username']); ?>！</p>

            <!-- 数据统计卡片 -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
                <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover flex items-center gap-4">
                    <div class="flex items-center justify-center w-14 h-14 rounded-full bg-primary/20">
                        <i class="fa fa-shopping-cart text-3xl text-primary"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-white mb-1">总订单数</h3>
                        <p class="text-4xl font-bold text-primary" id="totalOrders">-</p>
                    </div>
                </div>
                <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover flex items-center gap-4">
                    <div class="flex items-center justify-center w-14 h-14 rounded-full bg-primary/20">
                        <i class="fa fa-bolt text-3xl text-primary"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-white mb-1">可激活订单</h3>
                        <p class="text-4xl font-bold text-primary" id="activeOrders">-</p>
                    </div>
                </div>
                <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover flex items-center gap-4">
                    <div class="flex items-center justify-center w-14 h-14 rounded-full bg-primary/20">
                        <i class="fa fa-history text-3xl text-primary"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-white mb-1">总激活次数</h3>
                        <p class="text-4xl font-bold text-primary" id="totalActivations">-</p>
                    </div>
                </div>
                <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover flex items-center gap-4">
                    <div class="flex items-center justify-center w-14 h-14 rounded-full bg-primary/20">
                        <i class="fa fa-clock text-3xl text-primary"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-white mb-1">剩余激活次数</h3>
                        <p class="text-4xl font-bold text-primary" id="remainingActivations">-</p>
                    </div>
                </div>
            </div>

            <!-- 主要内容区域 -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- 左侧：个人信息和设置 -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- 个人信息 -->
                    <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover">
                        <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                            <i class="fa fa-user text-primary"></i>
                            <span>个人信息</span>
                        </h3>
                        
                        <div class="mb-6 flex flex-col md:flex-row md:items-center md:gap-8">
                            <div class="flex items-center gap-4 mb-4 md:mb-0">
                                <div class="flex items-center justify-center w-16 h-16 rounded-full bg-primary/20 border-4 border-primary shadow">
                                    <i class="fa fa-user-circle text-2xl text-primary"></i>
                                </div>
                                <div>
                                    <p class="text-xl text-white font-bold mb-1">用户名：<?php echo htmlspecialchars($user['username']); ?></p>
                                    <p class="text-sm text-gray-300 mb-1">用户ID：<?php echo htmlspecialchars($user_id); ?></p>
                                    <p class="text-sm text-gray-300">用户权限：客户</p>
                                </div>
                            </div>
                        </div>

                        <!-- 修改间隔提示信息 -->
                        <div class="bg-yellow-600/20 border border-yellow-500/50 rounded-lg p-4 mb-6">
                            <div class="flex items-start gap-3">
                                <i class="fa fa-exclamation-triangle text-yellow-400 text-lg mt-0.5"></i>
                                <div>
                                    <h4 class="text-yellow-300 font-semibold mb-2">修改间隔限制</h4>
                                    <p class="text-yellow-100 text-sm leading-relaxed">
                                        为了保护账户安全，个人信息修改需要间隔 <strong>6小时</strong> 以上。<br>
                                        如果您刚刚修改过信息，请等待足够时间后再次尝试修改。
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form id="profileForm" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="block text-gray-300">用户名</label>
                                    <input type="text" name="username" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" value="<?php echo htmlspecialchars($user['username']); ?>" required maxlength="50" pattern="[a-zA-Z0-9_\u4e00-\u9fa5]+" title="用户名只能包含字母、数字、下划线和中文字符">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-gray-300">邮箱地址</label>
                                    <input type="email" name="email" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" value="<?php echo htmlspecialchars($user['email']); ?>" required maxlength="100">
                                </div>
                                <?php if ($user_role > 1): ?>
                                <div class="space-y-2">
                                    <label class="block text-gray-300">爱发电用户ID</label>
                                    <input type="text" name="afdian_user_id" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" value="<?php echo htmlspecialchars($user['afdian_user_id'] ?? ''); ?>" maxlength="100" placeholder="请输入爱发电用户ID">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-gray-300">爱发电Token</label>
                                    <input type="text" name="afdian_token" class="w-full px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" value="<?php echo htmlspecialchars($user['afdian_token'] ?? ''); ?>" maxlength="200" placeholder="请输入爱发电Token">
                                </div>
                                <?php endif; ?>
                            </div>
                            <!-- 安全验证区（仅邮箱验证码） -->
                            <div class="bg-gray-700/30 rounded-lg p-4">
                                <h4 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                                    <i class="fa fa-shield-alt text-primary"></i>安全验证
                                </h4>
                                <div class="flex gap-2 items-end">
                                    <input type="password" id="currentPassword" name="current_password" class="flex-1 px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none mb-2" placeholder="当前密码（二选一）">
                                    <input type="text" id="profileVerificationCode" name="profile_verification_code" class="flex-1 px-4 py-2 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none" placeholder="邮箱验证码（二选一）" maxlength="6">
                                    <button type="button" id="sendProfileCodeBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-opacity-90 transition-colors whitespace-nowrap text-sm flex items-center gap-1">
                                        <span id="sendProfileCodeBtnText">发送</span>
                                        <span id="sendProfileCodeBtnLoading" class="hidden"><i class="fa fa-spinner fa-spin"></i></span>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-400 mt-2">验证码将发送到：<?php echo htmlspecialchars($user['email']); ?>。修改信息前请验证邮箱。</p>
                            </div>
                            <button type="submit" id="saveBtn" class="w-full rounded-button py-4 bg-primary text-white text-lg font-semibold hover:bg-opacity-90 transition-colors flex items-center justify-center gap-2 disabled:opacity-60">
                                <span id="saveBtnText">保存修改</span>
                                <span id="saveBtnLoading" class="hidden"><i class="fa fa-spinner fa-spin"></i> 保存中...</span>
                            </button>
                        </form>
                        <div class="mt-4 flex justify-end">
                            <button id="changePasswordBtn" class="px-6 py-3 bg-secondary text-white rounded-lg hover:bg-opacity-90 transition-colors flex items-center gap-2">
                                <i class="fa fa-key"></i>
                                <span>修改密码</span>
                            </button>
                        </div>
                    </div>

                    <!-- 个人设置 -->
                    <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover">
                        <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                            <i class="fa fa-cog text-primary"></i>
                            <span>个人设置</span>
                        </h3>
                        
                        <form id="settingsForm" class="space-y-6">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg">
                                    <div>
                                        <div class="text-white font-medium">自动激活提醒</div>
                                        <div class="text-sm text-gray-400">开启后，访问首页时会自动检查未激活订单并提醒</div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="autoActivationToggle" <?php echo $userSettings['auto_activation_enabled'] ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg">
                                    <div>
                                        <div class="text-white font-medium">邮件通知</div>
                                        <div class="text-sm text-gray-400">接收重要通知和更新提醒</div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="emailNotificationsToggle" <?php echo $userSettings['email_notifications'] ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="w-full rounded-button py-3 bg-secondary text-white text-base font-semibold hover:bg-opacity-90 transition-colors flex items-center justify-center gap-2">
                                <i class="fa fa-save"></i>
                                <span>保存设置</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- 右侧：快捷操作和账户信息 -->
                <div class="space-y-6">
                    <!-- 快捷操作 -->
                    <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover">
                        <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                            <i class="fa fa-rocket text-primary"></i>
                            <span>快捷操作</span>
                        </h3>
                        
                        <div class="space-y-3">
                            <a href="./my_orders.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/20 group-hover:bg-primary/30 transition-colors">
                                    <i class="fa fa-shopping-cart text-lg text-primary"></i>
                                </div>
                                <div>
                                    <p class="text-white font-semibold">我的订单</p>
                                    <p class="text-xs text-gray-400">查看和管理订单</p>
                                </div>
                                <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-primary transition-colors"></i>
                            </a>
                            
                            <a href="./activation_records.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/20 group-hover:bg-primary/30 transition-colors">
                                    <i class="fa fa-history text-lg text-primary"></i>
                                </div>
                                <div>
                                    <p class="text-white font-semibold">激活记录</p>
                                    <p class="text-xs text-gray-400">查看激活历史</p>
                                </div>
                                <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-primary transition-colors"></i>
                            </a>
                            
                            <a href="../../index.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/20 group-hover:bg-primary/30 transition-colors">
                                    <i class="fa fa-home text-lg text-primary"></i>
                                </div>
                                <div>
                                    <p class="text-white font-semibold">返回首页</p>
                                    <p class="text-xs text-gray-400">回到系统首页</p>
                                </div>
                                <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-primary transition-colors"></i>
                            </a>
                            
                            <a href="../auth/logout.php" class="flex items-center gap-3 p-3 bg-gray-700/30 rounded-lg hover:bg-gray-600/30 transition-colors group">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-500/20 group-hover:bg-red-500/30 transition-colors">
                                    <i class="fa fa-sign-out-alt text-lg text-red-500"></i>
                                </div>
                                <div>
                                    <p class="text-white font-semibold">退出登录</p>
                                    <p class="text-xs text-gray-400">安全退出系统</p>
                                </div>
                                <i class="fa fa-chevron-right text-gray-400 ml-auto group-hover:text-red-500 transition-colors"></i>
                            </a>
                        </div>
                    </div>

                    <!-- 账户信息 -->
                    <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover">
                        <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                            <i class="fa fa-info-circle text-primary"></i>
                            <span>账户信息</span>
                        </h3>
                        
                        <div class="space-y-4 text-sm">
                            <div class="flex justify-between items-center p-3 bg-gray-700/30 rounded-lg">
                                <span class="text-gray-300">用户ID</span>
                                <span class="text-white font-medium"><?php echo $user['id']; ?></span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-700/30 rounded-lg">
                                <span class="text-gray-300">注册时间</span>
                                <span class="text-white font-medium"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-700/30 rounded-lg">
                                <span class="text-gray-300">最后更新</span>
                                <span class="text-white font-medium"><?php echo $user['updated_at'] ? date('Y-m-d H:i', strtotime($user['updated_at'])) : '未更新'; ?></span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-700/30 rounded-lg">
                                <span class="text-gray-300">账户状态</span>
                                <span class="font-medium <?php echo $user['status'] ? 'text-green-400' : 'text-red-400'; ?>">
                                    <?php echo $user['status'] ? '正常' : '禁用'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 使用说明 -->
            <!-- <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover">
                <h3 class="text-2xl font-bold text-white mb-4">如何使订单能够自动更新？</h3>
                <p class="text-gray-300 mb-4">请参考<a href="../../pages/wow.html" target="_blank" class="text-primary hover:underline">跳转教程</a></p>
                
                <h3 class="text-2xl font-bold text-white mt-6 mb-4">严正声明</h3>
                <p class="text-red-400 font-bold text-lg">请详细阅读，若您继续使用则代表您同意此声明！</p>
                <p class="text-gray-300 mt-2">XFP是一个简单的小型工具站点，我们尽力确保网站和工具的安全性，但无法保证完全没有泄露可能性。我们使用加密技术、防火墙和定期安全扫描保护数据，但无措施能保证100%安全，建议您采取适当预防措施。</p>
                <p class="text-gray-300 mt-2">我们尊重您的隐私，收集的信息仅用于改进服务，不会出售给第三方。使用服务即视为同意本声明。</p>
            </div> -->
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
        // 全局消息提示函数（无透明背景，优化配色，2s自动隐藏）
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

        // 显示6小时间隔限制错误提示
        function showIntervalLimitError(errorMsg) {
            const messageHtml = `
                <div class="p-4 rounded-lg border-2 bg-orange-50 border-orange-400/60 flex items-start gap-3 shadow-xl max-w-2xl w-full mt-4 pointer-events-auto animate-slideDown">
                    <i class="fa fa-clock text-lg text-orange-500 mt-0.5"></i>
                    <div class="flex-1">
                        <div class="text-orange-600 font-semibold mb-2">修改间隔限制</div>
                        <div class="text-orange-700 text-sm leading-relaxed">
                            ${errorMsg}<br>
                            <span class="text-orange-600 font-medium">请耐心等待，这是为了保护您的账户安全。</span>
                        </div>
                    </div>
                    <button onclick="hideGlobalMessage()" class="ml-auto text-gray-400 hover:text-gray-700 transition-colors message-close-btn">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            `;
            $('#globalMessage').html(messageHtml);
            // 6小时间隔错误不自动隐藏，需要用户手动关闭
        }

        // 加载统计数据
        function loadStats() {
            $.ajax({
                url: '../../app/user/user_orders_api.php',
                method: 'GET',
                data: { action: 'get_orders', page: 1, limit: 1 },
                success: function(response) {
                    if (response.success) {
                        const orders = response.data.orders;
                        let totalOrders = 0;
                        let activeOrders = 0;
                        let totalActivations = 0;
                        let remainingActivations = 0;

                        orders.forEach(order => {
                            totalOrders++;
                            if (order.can_activate) activeOrders++;
                            totalActivations += order.activation_count;
                            remainingActivations += order.remaining_activations;
                        });

                        $('#totalOrders').text(totalOrders);
                        $('#activeOrders').text(activeOrders);
                        $('#totalActivations').text(totalActivations);
                        $('#remainingActivations').text(remainingActivations);
                    }
                }
            });
        }

        // 个人信息表单提交
        $('#profileForm').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serializeArray();
            // 根据用户角色过滤表单数据
            <?php if ($user_role == 1): ?>
            // 客户只提交邮箱和用户名
            formData = formData.filter(function(item){
                return item.name === 'email' || item.name === 'username' || item.name === 'current_password' || item.name === 'profile_verification_code';
            });
            <?php else: ?>
            // 其他角色用户可以提交所有字段，包括爱发电相关字段
            formData = formData.filter(function(item){
                return item.name === 'email' || item.name === 'username' || item.name === 'afdian_user_id' || item.name === 'afdian_token' || item.name === 'current_password' || item.name === 'profile_verification_code';
            });
            <?php endif; ?>
            const $saveBtn = $('#saveBtn');
            const $saveBtnText = $('#saveBtnText');
            const $saveBtnLoading = $('#saveBtnLoading');
            const password = $('#currentPassword').val().trim();
            const code = $('#profileVerificationCode').val().trim();
            
            if (!password && !code) {
                showMessage('请填写当前密码或邮箱验证码进行验证', 'error');
                return;
            }
            
            $saveBtn.prop('disabled', true);
            $saveBtnText.addClass('hidden');
            $saveBtnLoading.removeClass('hidden');
            
            $.ajax({
                url: '../../app/userback_action.php',
                method: 'POST',
                data: $.param(formData),
                success: function(response, textStatus, xhr) {
                    console.log('AJAX Success - 原始响应:', response);
                    console.log('AJAX Success - 响应类型:', typeof response);
                    console.log('AJAX Success - HTTP状态:', xhr.status);
                    console.log('AJAX Success - textStatus:', textStatus);
                    
                    let result;
                    try {
                        result = typeof response === 'string' ? JSON.parse(response) : response;
                        console.log('解析后的结果:', result);
                    } catch (parseError) {
                        console.error('JSON解析错误:', parseError);
                        console.error('原始响应内容:', response);
                        showMessage('响应解析失败，原始响应: ' + JSON.stringify(response), 'error');
                        resetButtonState();
                        return;
                    }
                    
                    if (result && result.success) {
                        showMessage(result.message || '更新成功', 'success');
                        // 延迟1秒后刷新页面以显示最新数据
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        const errorMsg = result ? (result.error || result.message || '更新失败') : '未知错误';
                        console.error('操作失败:', result);
                        
                        // 检查是否为6小时间隔限制错误
                        if (errorMsg.includes('修改间隔需大于6小时')) {
                            showIntervalLimitError(errorMsg);
                        } else {
                            showMessage('操作失败: ' + errorMsg, 'error');
                        }
                    }
                    resetButtonState();
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('AJAX Error - HTTP状态:', xhr.status);
                    console.error('AJAX Error - textStatus:', textStatus);
                    console.error('AJAX Error - errorThrown:', errorThrown);
                    console.error('AJAX Error - 响应文本:', xhr.responseText);
                    
                    let errorMessage = '请求失败';
                    if (xhr.status === 0) {
                        errorMessage = '网络连接失败，请检查网络连接';
                    } else if (xhr.status >= 400 && xhr.status < 500) {
                        errorMessage = '客户端错误 (HTTP ' + xhr.status + '): ' + (xhr.statusText || '未知错误');
                    } else if (xhr.status >= 500) {
                        errorMessage = '服务器错误 (HTTP ' + xhr.status + '): ' + (xhr.statusText || '服务器内部错误');
                    } else {
                        errorMessage = 'HTTP错误 (' + xhr.status + '): ' + xhr.statusText;
                    }
                    
                    if (xhr.responseText) {
                        errorMessage += '\n响应内容: ' + xhr.responseText;
                    }
                    
                    showMessage(errorMessage, 'error');
                    resetButtonState();
                }
            });
            
            function resetButtonState() {
                $saveBtn.prop('disabled', false);
                $saveBtnText.removeClass('hidden');
                $saveBtnLoading.addClass('hidden');
            }
        })

        // 发送邮箱验证码
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
                        showMessage('验证码已发送，请查收邮箱');
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

        // 设置表单提交
        $('#settingsForm').on('submit', function(e) {
            e.preventDefault();
            const autoActivation = $('#autoActivationToggle').is(':checked') ? 1 : 0;
            const emailNotifications = $('#emailNotificationsToggle').is(':checked') ? 1 : 0;
            const $btn = $(this).find('button[type="submit"]');
            const $btnText = $btn.find('span:not(.hidden)');
            const $btnLoading = $btn.find('.fa-spinner').parent();
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

        // 页面加载完成后执行
        $(document).ready(function() {
            loadStats();
        });

        // 密码弹窗控制
        $('#changePasswordBtn').on('click', function() {
            $('#passwordModal').removeClass('hidden');
            $('body').addClass('overflow-hidden');
            $('#verificationCode').focus();
        });
        $('#closePasswordModal, #cancelPasswordBtn').on('click', function() {
            $('#passwordModal').addClass('hidden');
            $('body').removeClass('overflow-hidden');
            $('#passwordForm')[0].reset();
            resetPasswordStrength();
        });
        $('#passwordModal').on('click', function(e) {
            if (e.target === this) {
                $('#passwordModal').addClass('hidden');
                $('body').removeClass('overflow-hidden');
                $('#passwordForm')[0].reset();
                resetPasswordStrength();
            }
        });
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && !$('#passwordModal').hasClass('hidden')) {
                $('#passwordModal').addClass('hidden');
                $('body').removeClass('overflow-hidden');
                $('#passwordForm')[0].reset();
                resetPasswordStrength();
            }
        });

        // 密码强度校验
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
            $('.w-2.h-2.rounded-full').removeClass('bg-red-500 bg-yellow-500 bg-green-500').addClass('bg-gray-600');
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
            const lengthValid = password.length >= 8;
            $('#reqLength i').removeClass('text-gray-500 text-green-500 text-red-500')
                .addClass(lengthValid ? 'text-green-500' : 'text-red-500');
            const matchValid = password === confirmPassword && password.length > 0;
            $('#reqMatch i').removeClass('text-gray-500 text-green-500 text-red-500')
                .addClass(matchValid ? 'text-green-500' : 'text-red-500');
            const strengthValid = /[a-zA-Z]/.test(password) && /[0-9]/.test(password);
            $('#reqStrength i').removeClass('text-gray-500 text-green-500 text-red-500')
                .addClass(strengthValid ? 'text-green-500' : 'text-red-500');
            const allValid = lengthValid && matchValid && strengthValid;
            $('#resetPasswordBtn').prop('disabled', !allValid);
            return allValid;
        }
        function resetPasswordStrength() {
            $('.w-2.h-2.rounded-full').removeClass('bg-red-500 bg-yellow-500 bg-green-500').addClass('bg-gray-600');
            $('#reqLength i, #reqMatch i, #reqStrength i').removeClass('text-green-500 text-red-500').addClass('text-gray-500');
            $('#resetPasswordBtn').prop('disabled', true);
        }

        // 发送验证码按钮
        $('#sendCodeBtn').on('click', function() {
            const $sendCodeBtn = $(this);
            const $sendCodeBtnText = $('#sendCodeBtnText');
            const $sendCodeBtnLoading = $('#sendCodeBtnLoading');
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
                        showMessage('验证码已发送，请查收邮箱', 'success');
                        startCountdown();
                    } else {
                        showMessage(result.error, 'error');
                        resetSendCodeButton();
                    }
                },
                error: function(xhr) {
                    showMessage('发送失败: ' + xhr.statusText, 'error');
                    resetSendCodeButton();
                }
            });
        });
        function resetSendCodeButton() {
            const $sendCodeBtn = $('#sendCodeBtn');
            const $sendCodeBtnText = $('#sendCodeBtnText');
            const $sendCodeBtnLoading = $('#sendCodeBtnLoading');
            $sendCodeBtn.prop('disabled', false);
            $sendCodeBtnText.removeClass('hidden');
            $sendCodeBtnLoading.addClass('hidden');
        }
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

        // 密码表单提交
        $('#passwordForm').on('submit', function(e) {
            e.preventDefault();
            if (!validatePasswordRequirements()) {
                showMessage('请满足所有密码要求', 'error');
                return;
            }
            const formData = $(this).serialize();
            const $resetPasswordBtn = $('#resetPasswordBtn');
            const $resetPasswordBtnText = $('#resetPasswordBtnText');
            const $resetPasswordBtnLoading = $('#resetPasswordBtnLoading');
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
                        showMessage(result.message, 'success');
                        $('#passwordForm')[0].reset();
                        resetPasswordStrength();
                        setTimeout(() => {
                            $('#passwordModal').addClass('hidden');
                            $('body').removeClass('overflow-hidden');
                        }, 2000);
                    } else {
                        showMessage(result.error, 'error');
                    }
                    resetButtonState();
                },
                error: function(xhr) {
                    showMessage('发生错误: ' + xhr.statusText, 'error');
                    resetButtonState();
                }
            });
            function resetButtonState() {
                $resetPasswordBtn.prop('disabled', false);
                $resetPasswordBtnText.removeClass('hidden');
                $resetPasswordBtnLoading.addClass('hidden');
            }
        });
    </script>
</body>
</html>
