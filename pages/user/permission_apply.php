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

// 检查用户是否已有待审核或已通过的申请
$stmt = $conn->prepare("SELECT * FROM permission_applications WHERE user_id = ? AND status IN ('pending', 'approved', 'under_review') ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$existing_application = $result->fetch_assoc();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>权限申请 - XFP密钥获取系统</title>
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
                        <li><a href="./index.php" class="text-gray-300 hover:text-white transition-colors">客户中心</a></li>
                        <li><a href="./my_orders.php" class="text-gray-300 hover:text-white transition-colors">我的订单</a></li>
                        <li><a href="./activation_records.php" class="text-gray-300 hover:text-white transition-colors">激活记录</a></li>
                        <li><a href="./permission_apply.php" class="text-primary font-semibold">权限申请</a></li>
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
            <h1 class="text-5xl font-bold text-white mb-8">权限申请</h1>
            <p class="text-xl text-gray-300 mb-12">申请开发者权限，快速开通平台使用权限</p>

            <?php if ($existing_application): ?>
                <!-- 已有申请状态显示 -->
                <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-8 mb-8">
                    <h3 class="text-2xl font-bold text-white mb-6 flex items-center gap-2">
                        <i class="fa fa-info-circle text-primary"></i>
                        <span>申请状态</span>
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-gray-300 mb-2"><strong>申请类型：</strong> <?php echo htmlspecialchars($existing_application['application_type']); ?></p>
                            <p class="text-gray-300 mb-2"><strong>申请时间：</strong> <?php echo date('Y-m-d H:i:s', strtotime($existing_application['created_at'])); ?></p>
                            <p class="text-gray-300 mb-2"><strong>当前状态：</strong> 
                                <?php 
                                $status_map = [
                                    'pending' => '<span class="text-yellow-400">待审核</span>',
                                    'under_review' => '<span class="text-blue-400">审核中</span>',
                                    'approved' => '<span class="text-green-400">已通过</span>',
                                    'rejected' => '<span class="text-red-400">已拒绝</span>'
                                ];
                                echo $status_map[$existing_application['status']] ?? $existing_application['status'];
                                ?>
                            </p>
                        </div>
                        <div>
                            <?php if ($existing_application['company_name']): ?>
                            <p class="text-gray-300 mb-2"><strong>公司名称：</strong> <?php echo htmlspecialchars($existing_application['company_name']); ?></p>
                            <?php endif; ?>
                            <?php if ($existing_application['reviewed_at']): ?>
                            <p class="text-gray-300 mb-2"><strong>审核时间：</strong> <?php echo date('Y-m-d H:i:s', strtotime($existing_application['reviewed_at'])); ?></p>
                            <?php endif; ?>
                            <?php if ($existing_application['admin_comment']): ?>
                            <p class="text-gray-300 mb-2"><strong>审核意见：</strong> <?php echo htmlspecialchars($existing_application['admin_comment']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($existing_application['status'] === 'rejected'): ?>
                    <div class="mt-6">
                        <button onclick="showApplicationForm()" class="bg-primary text-white px-6 py-3 rounded-button hover:bg-opacity-90 transition-colors">
                            <i class="fa fa-plus mr-2"></i>重新申请
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- 申请表单 -->
            <div id="applicationForm" class="<?php echo ($existing_application && $existing_application['status'] !== 'rejected') ? 'hidden' : ''; ?>">
                <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-8 card-hover">
                    <h3 class="text-2xl font-bold text-white mb-6 flex items-center gap-2">
                        <i class="fa fa-file-alt text-primary"></i>
                        <span>开发者权限申请表</span>
                    </h3>
                    
                    <form id="permissionForm" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="company_name" class="block text-sm font-medium text-gray-300 mb-2">公司名称（可选）</label>
                                <input type="text" id="company_name" name="company_name" 
                                       class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 input-focus" 
                                       placeholder="请输入公司名称">
                            </div>
                            <div>
                                <label for="contact_phone" class="block text-sm font-medium text-gray-300 mb-2">联系电话（可选）</label>
                                <input type="tel" id="contact_phone" name="contact_phone" 
                                       class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 input-focus" 
                                       placeholder="请输入联系电话">
                            </div>
                        </div>
                        
                        <div>
                            <label for="project_description" class="block text-sm font-medium text-gray-300 mb-2">项目描述 <span class="text-red-400">*</span></label>
                            <textarea id="project_description" name="project_description" rows="4" required
                                      class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 input-focus" 
                                      placeholder="请详细描述您的项目，包括项目目标、功能特点等"></textarea>
                        </div>
                        
                        <div>
                            <label for="expected_usage" class="block text-sm font-medium text-gray-300 mb-2">预期使用情况 <span class="text-red-400">*</span></label>
                            <textarea id="expected_usage" name="expected_usage" rows="4" required
                                      class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 input-focus" 
                                      placeholder="请描述您预期如何使用我们的API服务，包括调用频率、数据量等"></textarea>
                        </div>
                        
                        <div>
                            <label for="technical_background" class="block text-sm font-medium text-gray-300 mb-2">技术背景（可选）</label>
                            <textarea id="technical_background" name="technical_background" rows="3"
                                      class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 input-focus" 
                                      placeholder="请简要介绍您的技术背景和开发经验"></textarea>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <button type="submit" class="bg-primary text-white px-8 py-3 rounded-button hover:bg-opacity-90 transition-colors flex items-center gap-2">
                                <i class="fa fa-paper-plane"></i>
                                <span>提交申请</span>
                            </button>
                            <button type="button" onclick="resetForm()" class="bg-gray-600 text-white px-6 py-3 rounded-button hover:bg-opacity-90 transition-colors">
                                <i class="fa fa-undo mr-2"></i>重置表单
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        /**
         * 显示全局消息提示
         * @param {string} message - 消息内容
         * @param {string} type - 消息类型：success, error, warning, info
         */
        function showMessage(message, type = 'info') {
            const colors = {
                success: { bg: 'bg-green-500', border: 'border-green-400', icon: 'fa-check-circle' },
                error: { bg: 'bg-red-500', border: 'border-red-400', icon: 'fa-exclamation-circle' },
                warning: { bg: 'bg-yellow-500', border: 'border-yellow-400', icon: 'fa-exclamation-triangle' },
                info: { bg: 'bg-blue-500', border: 'border-blue-400', icon: 'fa-info-circle' }
            };
            
            const color = colors[type] || colors.info;
            
            const messageHtml = `
                <div class="${color.bg} ${color.border} border-l-4 text-white p-4 rounded-r-lg shadow-lg max-w-md mx-4 pointer-events-auto">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas ${color.icon} mr-3"></i>
                            <span>${message}</span>
                        </div>
                        <button onclick="hideGlobalMessage()" class="ml-4 text-white hover:text-gray-200 message-close-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            $('#globalMessage').html(messageHtml).removeClass('hidden');
            
            // 3秒后自动隐藏
            setTimeout(hideGlobalMessage, 3000);
        }

        /**
         * 隐藏全局消息
         */
        function hideGlobalMessage() {
            $('#globalMessage').addClass('hidden').html('');
        }

        /**
         * 显示申请表单
         */
        function showApplicationForm() {
            $('#applicationForm').removeClass('hidden');
        }

        /**
         * 重置表单
         */
        function resetForm() {
            $('#permissionForm')[0].reset();
        }

        // 表单提交处理
        $('#permissionForm').on('submit', function(e) {
            e.preventDefault();
            
            // 获取表单数据
            const formData = {
                company_name: $('#company_name').val().trim(),
                contact_phone: $('#contact_phone').val().trim(),
                project_description: $('#project_description').val().trim(),
                expected_usage: $('#expected_usage').val().trim(),
                technical_background: $('#technical_background').val().trim()
            };
            
            // 验证必填字段
            if (!formData.project_description) {
                showMessage('请填写项目描述', 'error');
                return;
            }
            
            if (!formData.expected_usage) {
                showMessage('请填写预期使用情况', 'error');
                return;
            }
            
            // 提交申请
            $.ajax({
                url: '../../app/user/permission_apply_api.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage('申请提交成功，请等待管理员审核', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showMessage(response.message || '申请提交失败', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('申请提交错误:', error);
                    let errorMsg = '申请提交失败，请稍后重试';
                    if (xhr.status === 400) {
                        errorMsg = '请求参数错误';
                    } else if (xhr.status === 500) {
                        errorMsg = '服务器内部错误';
                    }
                    showMessage(errorMsg, 'error');
                }
            });
        });
    </script>
</body>
</html>