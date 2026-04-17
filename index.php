<?php
/**
 * XFP Activation Key System / XFP 密钥获取系统 — 站点首页
 *
 * @author    YcFeller
 * @copyright Copyright (c) 2026 YcFeller
 * @license   AGPL-3.0-or-later
 * @link      https://github.com/YcFeller
 */
session_start();
?>
<!-- V2 2025年5月13日 -->
<!DOCTYPE html>
<html lang="zh">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="YcFeller">
  <link rel="author" href="https://github.com/YcFeller">
  <title>XFP密钥获取系统</title>
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
            'none': '0px',
            'sm': '4px',
            DEFAULT: '8px',
            'md': '12px',
            'lg': '16px',
            'xl': '20px',
            '2xl': '24px',
            '3xl': '32px',
            'full': '9999px',
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
      src: url('./files/font/misans.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
    }

    body {
      font-family: 'MiSans', sans-serif;
      color: #ffffff;
      background-color: #0F172A;
      min-height: 1024px;
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
  <!-- 添加jQuery用于处理表单提交等交互 -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <?php
  if (isset($_SESSION['user_id'])) {
    echo '<script src="./files/js/index.js"></script>';
  };
  ?>
</head>

<body class="geometric-bg">
  <div class="max-w-[1440px] mx-auto px-4 md:px-8">
    <header class="py-6 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-0">
      <div class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
        <h1 class="text-2xl md:text-3xl font-['Pacifico'] text-white">XFP密钥获取系统</h1>
        <nav class="md:ml-12 w-full md:w-auto">
          <ul class="flex flex-col md:flex-row gap-4 md:gap-8 w-full md:w-auto text-center">
            <li><a href="#" class="text-gray-300 hover:text-white transition-colors">首页</a></li>
            <li><a href="#" class="text-gray-300 hover:text-white transition-colors">产品</a></li>
            <li><a href="#" class="text-gray-300 hover:text-white transition-colors">关于我们</a></li>
            <li><a href="#" class="text-gray-300 hover:text-white transition-colors">帮助文档</a></li>
          </ul>
        </nav>
      </div>
      <div class="flex gap-2 md:gap-4 w-full md:w-auto justify-center md:justify-end">
        <?php if (!isset($_SESSION['user_id'])) { ?>
          <a href="../../app/oauth_login.php" class="!rounded-button px-4 md:px-6 py-2 text-gray-300 hover:text-white transition-colors text-sm md:text-base">登录</a>
          <a href="./pages/auth/login.php" class="!rounded-button px-4 md:px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors text-sm md:text-base">注册</a>
        <?php } else { ?>
          <a href="./pages/auth/logout.php" class="!rounded-button px-4 md:px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors text-sm md:text-base">登出</a>
          <a href="./pages/user/activation_records.php" class="!rounded-button px-4 md:px-6 py-2 text-gray-300 hover:text-white transition-colors text-sm md:text-base">历史记录</a>
        <?php } ?>
      </div>
    </header>

    <main class="py-10 md:py-20">
      <div class="text-center mb-10 md:mb-16">
        <h2 class="text-3xl md:text-5xl font-bold text-white mb-4 md:mb-6">快速获取您的专属密钥</h2>
        <p class="text-lg md:text-xl text-gray-400 mb-8 md:mb-12">简单三步，即刻开启您的穿戴个性化之旅</p>


        <!-- 引用弹窗php -->
        <?php include './app/popup_index.php'; ?>


        <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-4 md:p-6 card-hover mb-4 mx-auto max-w-full sm:max-w-2xl lg:max-w-4xl">
          <?php if (!isset($_SESSION['user_id'])) { ?>
            <div class="text-center mb-8">
              <h3 class="text-red-500">登录后</span>才可查询解锁密码</h3>
              <p class="text-gray-400">登录后即可查询密码，并且可以查看自己的激活记录</p>
              <h4 class="text-gray-400">（点击以下图标进行登录）</h4>
              <a href="../../app/oauth_login.php">
                <img src="../../files/imgs/afdlogo.png" class="w-20 h-20 rounded-full mx-auto mb-4">
              </a>
              <a href="./pages/auth/login.php" class="rounded-button px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors">或使用账号密码登录</a>
            </div>
          <?php } else { ?>
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-2xl p-4 md:p-12 card-hover">
              <div class="flex flex-col sm:flex-row items-center justify-center mb-6 md:mb-8 gap-2 md:gap-4">
                <i class="fas fa-key text-primary text-3xl md:text-4xl"></i>
                <h3 class="text-xl md:text-2xl font-bold text-white ml-0 sm:ml-4">秘钥获取</h3>
              </div>
              
              <!-- 激活类型切换 -->
              <div class="mb-6 md:mb-8">
                <div class="flex items-center justify-center mb-4">
                  <span class="text-gray-300 text-sm md:text-base mr-4">激活类型：</span>
                  <div class="flex bg-gray-700/50 rounded-lg p-1">
                    <button type="button" id="watchface-tab" class="activation-type-tab px-4 py-2 rounded-md text-sm md:text-base transition-all duration-200 bg-primary text-white" data-type="watchface">
                      <i class="fas fa-clock mr-2"></i>表盘激活
                    </button>
                    <button type="button" id="quickapp-tab" class="activation-type-tab px-4 py-2 rounded-md text-sm md:text-base transition-all duration-200 text-gray-300 hover:text-white" data-type="quickapp">
                      <i class="fas fa-mobile-alt mr-2"></i>快应用激活
                    </button>
                  </div>
                </div>
                <div class="text-center">
                  <p id="activation-description" class="text-gray-400 text-xs md:text-sm">
                    当前选择：表盘激活 - 请输入您的表盘订单号进行激活
                  </p>
                </div>
              </div>
              
              <form id="search-form" class="search-form">
                <input type="hidden" id="activation-type" name="activation_type" value="watchface">
                <div class="relative mb-6 md:mb-8">
                  <input type="text" id="order-number" name="order_number" placeholder="请输入订单号" value="" class="w-full px-4 md:px-6 py-3 md:py-4 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none text-sm md:text-base" required maxlength="30" pattern="[A-Za-z0-9]{1,30}">
                  <i class="fas fa-search absolute right-4 md:right-6 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
                <div class="relative mb-6 md:mb-8 flex flex-col sm:flex-row items-center gap-2">
                  <input type="text" id="captcha-input" name="captcha" placeholder="请输入验证码(大小写需相同)" value="" class="w-full px-4 md:px-6 py-3 md:py-4 bg-gray-700/50 text-white rounded-lg border-2 border-gray-600 input-focus outline-none text-sm md:text-base" required>
                  <img id="captcha-image" src="./app/captcha.php" alt="验证码" class="w-24 h-12 text-white rounded-lg border-2 border-gray-600 cursor-pointer" onclick="refreshCaptcha()">
                </div>
                <button type="submit" class="w-full rounded-button py-3 md:py-4 bg-primary text-white text-base md:text-lg font-semibold hover:bg-opacity-90 transition-colors flex items-center justify-center gap-2">
                  <i class="fas fa-unlock"></i>
                  查询订单
                </button>
              </form>
              <p class="text-gray-400 text-xs md:text-sm mt-2 md:mt-4 text-center">密钥将马上出现~</p>
              <div id="results" class="mt-2 md:mt-4"></div>
              <div id="unlock-password"></div>
            </div>
            <p class="mt-4 text-center">您的用户名为：<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : ''; ?></p>
            <?php if ($_SESSION['user_role'] >= 2) { ?>
              <p class="text-center mt-2">欢迎你，尊敬的<?php echo ($_SESSION['user_role'] == 2) ? '用户' : '管理员'; ?>！</p>
              <div class="text-center mt-2">
                <a href="./admin/" class="rounded-button px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors">管理中心</a>
                <a href="./pages/user/index.php" class="rounded-button px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors">客户中心</a>
                <a href="./admin/user/shortcut_tool.php" class="rounded-button px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors">快速激活</a>
              </div>
            <?php } else { ?>
              <p class="text-center mt-2">欢迎你！</p>
              <div class="text-center mt-2">
                <a href="./pages/user/index.php" class="rounded-button px-6 py-2 bg-primary text-white hover:bg-opacity-90 transition-colors">客户中心</a>
              </div>
            <?php } ?>
          <?php } ?>
        </div>


        <div class="mt-10 md:mt-20">
          <div class="text-center mb-8 md:mb-12">
            <div class="flex items-center justify-center gap-2 md:gap-3 mb-2 md:mb-4">
              <i class="fas fa-stars text-primary text-xl md:text-2xl"></i>
              <h3 class="text-xl md:text-2xl font-bold text-white">使用流程</h3>
            </div>
            <p class="text-gray-400 text-sm md:text-base">我们提供简单快捷的密钥获取流程，只需三步即可完成</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 md:gap-8">
            <div class="bg-gray-800/30 rounded-xl p-8 text-center">
              <div class="w-16 h-16 bg-primary/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-clipboard-check text-primary text-2xl"></i>
              </div>
              <h3 class="text-xl font-semibold text-white mb-4">输入订单号</h3>
              <p class="text-gray-400">在购买完成后，您将收到一个唯一的订单号，请将其输入上方框内</p>
            </div>
            <div class="bg-gray-800/30 rounded-xl p-8 text-center">
              <div class="w-16 h-16 bg-secondary/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-key text-secondary text-2xl"></i>
              </div>
              <h3 class="text-xl font-semibold text-white mb-4">系统验证</h3>
              <p class="text-gray-400">我们的系统将自动验证您的订单信息，确保安全可靠</p>
            </div>
            <div class="bg-gray-800/30 rounded-xl p-8 text-center">
              <div class="w-16 h-16 bg-primary/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-envelope text-primary text-2xl"></i>
              </div>
              <h3 class="text-xl font-semibold text-white mb-4">邮箱接收</h3>
              <p class="text-gray-400">验证通过后，密钥将立即发送至您的注册邮箱</p>
            </div>
          </div>
        </div>

        <div class="mt-16 md:mt-32">
          <div class="text-center mb-8 md:mb-12">
            <div class="flex items-center justify-center gap-2 md:gap-3 mb-2 md:mb-4">
              <i class="fas fa-fire text-primary text-xl md:text-2xl"></i>
              <h3 class="text-xl md:text-2xl font-bold text-white">热门商品</h3>
            </div>
            <p class="text-gray-400 text-sm md:text-base">精选多款热门密钥产品，满足您的不同需求</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
            <!-- <div class="bg-gray-800/30 rounded-xl p-6 card-hover">
              <div class="bg-gray-700/50 rounded-lg p-4 mb-4">
                <i class="fas fa-shield-alt text-4xl text-primary"></i>
              </div>
              <h4 class="text-lg font-semibold text-white mb-2">专业版密钥</h4>
              <p class="text-gray-400 text-sm mb-4">完整功能访问权限，适合专业用户使用</p>
              <div class="flex items-center justify-between">
                <span class="text-primary font-semibold">¥299.00</span>
                <button class="!rounded-button px-4 py-2 bg-primary text-white text-sm hover:bg-opacity-90">立即购买</button>
              </div>
            </div>
            <div class="bg-gray-800/30 rounded-xl p-6 card-hover">
              <div class="bg-gray-700/50 rounded-lg p-4 mb-4">
                <i class="fas fa-rocket text-4xl text-secondary"></i>
              </div>
              <h4 class="text-lg font-semibold text-white mb-2">企业版密钥</h4>
              <p class="text-gray-400 text-sm mb-4">支持多人协作，适合团队使用</p>
              <div class="flex items-center justify-between">
                <span class="text-primary font-semibold">¥899.00</span>
                <button class="!rounded-button px-4 py-2 bg-primary text-white text-sm hover:bg-opacity-90">立即购买</button>
              </div>
            </div>
            <div class="bg-gray-800/30 rounded-xl p-6 card-hover">
              <div class="bg-gray-700/50 rounded-lg p-4 mb-4">
                <i class="fas fa-gem text-4xl text-primary"></i>
              </div>
              <h4 class="text-lg font-semibold text-white mb-2">高级版密钥</h4>
              <p class="text-gray-400 text-sm mb-4">优先支持服务，适合高要求用户</p>
              <div class="flex items-center justify-between">
                <span class="text-primary font-semibold">¥599.00</span>
                <button class="!rounded-button px-4 py-2 bg-primary text-white text-sm hover:bg-opacity-90">立即购买</button>
              </div>
            </div>
            <div class="bg-gray-800/30 rounded-xl p-6 card-hover">
              <div class="bg-gray-700/50 rounded-lg p-4 mb-4">
                <i class="fas fa-laptop-code text-4xl text-secondary"></i>
              </div>
              <h4 class="text-lg font-semibold text-white mb-2">开发者版密钥</h4>
              <p class="text-gray-400 text-sm mb-4">API完整访问权限，适合开发者使用</p>
              <div class="flex items-center justify-between">
                <span class="text-primary font-semibold">¥799.00</span>
                <button class="!rounded-button px-4 py-2 bg-primary text-white text-sm hover:bg-opacity-90">立即购买</button>
              </div>
            </div> -->
          </div>
        </div>

        <div class="mt-16 md:mt-32">
          <div class="text-center mb-8 md:mb-12">
            <div class="flex items-center justify-center gap-2 md:gap-3 mb-2 md:mb-4">
              <i class="fas fa-question-circle text-primary text-xl md:text-2xl"></i>
              <h3 class="text-xl md:text-2xl font-bold text-white">常见问题</h3>
            </div>
            <p class="text-gray-400 text-sm md:text-base">解答您使用过程中可能遇到的问题</p>
          </div>
          <div class="max-w-full md:max-w-3xl mx-auto space-y-2 md:space-y-4">
            <details class="bg-gray-800/30 rounded-xl p-4 text-white">
              <summary class="cursor-pointer font-semibold">密钥激活失败怎么办？</summary>
              <p class="text-gray-400 mt-2">请检查以下几点：1. 确保安装表盘的方式正确；2. 验证是否在正确的位置输入；3. 检查网络连接是否正常；4. 如果问题依然存在，请联系客服支持。</p>
            </details>
            <!--<details class="bg-gray-800/30 rounded-xl p-4 text-white">-->
            <!--  <summary class="cursor-pointer font-semibold">如何更换绑定设备？</summary>-->
            <!--  <p class="text-gray-400 mt-2">每个密钥支持更换设备的次数有限，您可以登录账号后在"设备管理"中进行解绑和重新绑定操作。</p>-->
            <!--</details>-->
            <details class="bg-gray-800/30 rounded-xl p-4 text-white">
              <summary class="cursor-pointer font-semibold">密钥可以退款吗？</summary>
              <p class="text-gray-400 mt-2">已激活的密钥暂不支持退款。如有特殊情况，请联系客服处理。</p>
            </details>
          </div>
        </div>
    </main>

    <footer class="py-8 md:py-12 border-t border-gray-800">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 md:gap-8 mb-8 md:mb-12">
        <div>
          <h4 class="text-white font-semibold mb-4">关于我们</h4>
          <ul class="space-y-2">
            <li><a href="#" class="text-gray-400 hover:text-white transition-colors">公司简介</a></li>
            <li><a href="#" class="text-gray-400 hover:text-white transition-colors">团队介绍</a></li>
            <li><a href="#" class="text-gray-400 hover:text-white transition-colors">联系方式</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-semibold mb-4">帮助中心</h4>
          <ul class="space-y-2">
            <li><a href="#" class="text-gray-400 hover:text-white transition-colors">使用教程</a></li>
            <li><a href="#" class="text-gray-400 hover:text-white transition-colors">常见问题</a></li>
            <li><a href="#" class="text-gray-400 hover:text-white transition-colors">技术支持</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-semibold mb-4">商务合作</h4>
          <ul class="space-y-2">
            <li><a href="#" class="text-gray-400 hover:text-white transition-colors">渠道合作</a></li>
            <li><a href="#" class="text-gray-400 hover:text-white transition-colors">商务洽谈</a></li>
            <li><a href="#" class="text-gray-400 hover:text-white transition-colors">加入我们</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-semibold mb-4">关注我们</h4>
          <div class="flex gap-4">
            <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-700 transform hover:scale-110 transition-all">
              <i class="fab fa-weixin"></i>
            </a>
            <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-700 transform hover:scale-110 transition-all">
              <i class="fab fa-weibo"></i>
            </a>
            <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-700 transform hover:scale-110 transition-all">
              <i class="fab fa-github"></i>
            </a>
          </div>
        </div>
      </div>
      <div class="text-center text-gray-500 text-xs md:text-sm">
        <p>© 2025 XFP 密钥获取系统. 保留所有权利. | 基于Watchface Locker原理的原生php密钥获取平台</p>
        <span>网站主要制作人：YcFeller | 版本：v3.0.1</span>
      </div>
    </footer>

    <!-- 弹窗内容 -->
    <div id="result-modal" class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 flex items-center justify-center" style="display: none;">
      <div class="bg-white rounded-xl p-8 max-w-md w-full">
        <h3 class="text-xl font-bold mb-4">查询结果</h3>
        <div id="modal-content">
          <!-- 动态加载查询结果 -->
        </div>
        <button onclick="document.getElementById('result-modal').style.display = 'none';" class="mt-6 w-full py-2 bg-gray-500 text-white rounded hover:bg-gray-600">关闭</button>
      </div>
    </div>

    <!-- 自动激活提醒弹窗 -->
    <div id="auto-activation-modal" class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
      <div class="bg-gray-800 rounded-xl p-6 max-w-lg w-full mx-4">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold text-white">未激活订单提醒</h3>
          <button onclick="closeAutoActivationModal()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
        </div>
        <div id="auto-activation-content" class="text-white mb-6">
          <!-- 动态加载未激活订单 -->
        </div>
        <div class="flex gap-3">
          <button onclick="closeAutoActivationModal()" class="flex-1 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">稍后处理</button>
          <button onclick="goToOrders()" class="flex-1 py-2 bg-primary text-white rounded hover:bg-opacity-90">立即查看</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    $(document).ready(function () {
      function updateCaptcha() {
        $('#captcha-image').attr('src', './app/captcha.php?' + new Date().getTime());
      }

      updateCaptcha();
      $('#loading_p').css('display', 'none');

      // 检查未激活订单
      checkUnactivatedOrders();

      $('#search-form').submit(function (event) {
        event.preventDefault();
        let orderNumber = $('#order-number').val().trim();
        let captcha = $('#captcha-input').val().trim();
        // 输入验证
        if (!orderNumber) {
          alert('请输入订单号');
          return;
        }
        if (!/^[A-Za-z0-9]{1,30}$/.test(orderNumber)) {
          alert('订单号只能为1-30位字母或数字');
          return;
        }
        if (!captcha) {
          alert('请输入验证码');
          return;
        }
        // ... existing code ...
      });
    });

    // 检查未激活订单
    function checkUnactivatedOrders() {
      $.ajax({
        url: './app/user/auto_activation_api.php',
        method: 'GET',
        data: { action: 'check_unactivated_orders' },
        success: function(response) {
          if (response.success && response.data.length > 0) {
            showAutoActivationModal(response.data);
          }
        },
        error: function(xhr) {
          console.log('检查未激活订单失败:', xhr.statusText);
        }
      });
    }

    // 显示自动激活弹窗
    function showAutoActivationModal(orders) {
      let content = `
        <p class="text-gray-300 mb-4">检测到您有 ${orders.length} 个未激活的订单，是否立即处理？</p>
        <div class="space-y-3 max-h-60 overflow-y-auto">
      `;
      
      orders.forEach(order => {
        content += `
          <div class="bg-gray-700 rounded-lg p-3">
            <div class="flex justify-between items-start">
              <div>
                <div class="font-semibold text-white">${order.order_number}</div>
                <div class="text-sm text-gray-400">${order.plan_name}</div>
                <div class="text-sm text-gray-400">剩余激活次数: ${order.remaining_activations}</div>
              </div>
              <div class="text-right">
                <div class="text-primary font-semibold">¥${order.amount}</div>
                <div class="text-xs text-gray-400">${formatDate(order.created_at)}</div>
              </div>
            </div>
          </div>
        `;
      });
      
      content += '</div>';
      $('#auto-activation-content').html(content);
      $('#auto-activation-modal').show();
    }

    // 关闭自动激活弹窗
    function closeAutoActivationModal() {
      $('#auto-activation-modal').hide();
    }

    // 跳转到订单页面
    function goToOrders() {
      window.location.href = './pages/user/orders.php';
    }

    // 格式化日期
    function formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('zh-CN');
    }
  </script>
</body>

</html>