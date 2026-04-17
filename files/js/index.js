function refreshCaptcha() {
  var captchaImage = document.getElementById('captcha-image');
  captchaImage.src = './app/captcha.php?' + new Date().getTime();
}

function refreshCaptcha2() {
  var captchaImage = document.getElementById('captcha-image2');
  captchaImage.src = './app/captcha.php?' + new Date().getTime();
}

$(document).ready(function () {
  function updateCaptcha() {
    $('#captcha-image').attr('src', './app/captcha.php?' + new Date().getTime());
  }

  updateCaptcha();
  $('#loading_p').css('display', 'none');
  
  // 激活类型切换处理
  $('.activation-type-tab').click(function() {
    const selectedType = $(this).data('type');
    
    // 更新按钮样式
    $('.activation-type-tab').removeClass('bg-primary text-white').addClass('text-gray-300 hover:text-white');
    $(this).removeClass('text-gray-300 hover:text-white').addClass('bg-primary text-white');
    
    // 更新隐藏字段
    $('#activation-type').val(selectedType);
    
    // 更新描述文本和占位符
    if (selectedType === 'watchface') {
      $('#activation-description').text('当前选择：表盘激活 - 请输入您的表盘订单号进行激活');
      $('#order-number').attr('placeholder', '请输入表盘订单号');
      $('#order-number').attr('maxlength', '30');
      $('#order-number').attr('pattern', '[A-Za-z0-9]{1,30}');
    } else if (selectedType === 'quickapp') {
      $('#activation-description').text('当前选择：快应用激活 - 请输入您的快应用订单号进行激活');
      $('#order-number').attr('placeholder', '请输入快应用订单号');
      $('#order-number').attr('maxlength', '30');
      $('#order-number').attr('pattern', '[A-Za-z0-9]{1,30}');
    }
    
    // 清空之前的查询结果
    $('#results').empty();
    $('#unlock-password').empty();
    $('#order-number').val('');
    $('#captcha-input').val('');
    updateCaptcha();
    refreshCaptcha();
  });

  $('#search-form').submit(function (event) {
    event.preventDefault();
    let orderNumber = $('#order-number').val().trim();
    let captcha = $('#captcha-input').val().trim();
    
    // 输入验证
    const activationType = $('#activation-type').val();
    const productTypeName = activationType === 'quickapp' ? '快应用' : '表盘';
    
    if (!orderNumber) {
      alert('请输入' + productTypeName + '订单号');
      return;
    }
    
    // 验证订单号格式
    if (!/^[A-Za-z0-9]{8,30}$/.test(orderNumber)) {
      alert(productTypeName + '订单号格式不正确，应为8-30位字母和数字组合');
      return;
    }
    
    if (!captcha) {
      alert('请输入验证码');
      return;
    }
    
    // 显示加载状态
    $('#results').html('<div class="text-center py-8"><i class="fa fa-spinner fa-spin text-primary text-2xl"></i><p class="text-gray-400 mt-2">正在查询订单信息...</p></div>');
    $('#unlock-password').empty();
    
    $.ajax({
      url: './app/inquire_api.php',
      type: 'POST',
      data: {
        order_number: orderNumber,
        captcha: captcha,
        activation_type: $('#activation-type').val()
      },
      success: function (response) {
        let data;
        try {
          data = JSON.parse(response);
        } catch (e) {
          $('#results').html('<div class="bg-red-500/80 text-white rounded p-4 text-center">服务器响应异常，请重试</div>');
          return;
        }
        
        if (data.error) {
          $('#results').html(`<div class="bg-red-500/80 text-white rounded p-4 text-center">
            <i class="fa fa-exclamation-triangle mr-2"></i>${data.error}
            ${data.error.includes('验证码') ? '<br><button onclick="updateCaptcha(); refreshCaptcha();" class="mt-2 px-4 py-1 bg-white text-red-500 rounded text-sm">刷新验证码</button>' : ''}
          </div>`);
          if (data.error.includes('验证码')) {
            updateCaptcha();
            refreshCaptcha();
          }
        } else {
          let productsHtml = '';
          const activationType = data.activation_type || 'watchface';
          const products = data.products || data.watchfaces || [];
          
          products.forEach(function (product) {
            const isWatchface = activationType === 'watchface';
            const productTypeText = isWatchface ? '表盘' : '快应用';
            const productNameField = product.product_name || product.watchface_name;
            const productImageField = product.product_image || product.watchface_image;
            const productIcon = isWatchface ? 'fa-clock' : 'fa-mobile-alt';
            
            productsHtml += `
              <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover mb-4">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-xl font-semibold text-white">查询结果</h3>
                  <span class="text-green-400 text-sm"><i class="fa fa-check-circle"></i> 验证成功</span>
                </div>
                <div class="space-y-3">
                  <p class="flex items-center text-gray-300">
                    <i class="fas ${productIcon} mr-2 text-primary"></i>
                    <span>${productTypeText}名称: ${productNameField}</span>
                  </p>
                  <p class="flex items-center text-gray-300">
                    <i class="fas fa-image mr-2 text-primary"></i>
                    <span>${productTypeText}图片:</span>
                    <img src="${productImageField}" alt="${productTypeText}图片" class="ml-2 product-image" style="max-width: 100px; max-height: 100px;" onerror="this.style.display='none'">
                  </p>
                  <p class="flex items-center text-gray-300">
                    <i class="fas fa-repeat mr-2 text-primary"></i>
                    <span>剩余激活次数: <b class="text-primary">${data.downloads_limit}</b></span>
                  </p>
                  <p class="flex items-center text-gray-300">
                    <i class="fas fa-toggle-on mr-2 text-primary"></i>
                    <span>状态: ${product.status == 1 ? '<span class="text-green-400">显示</span>' : '<span class="text-red-400">隐藏</span>'}</span>
                  </p>
                </div>
              </div>
            `;
          });

          $('#results').html(productsHtml + `
            <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover">
              <div class="flex items-center justify-between mb-4">
                  <h3 class="text-xl font-semibold text-white">获取秘钥</h3>
                </div>
              <form id="unlock-form" class="unlock-form space-y-4">
                <input type="hidden" name="order_no" value="${data.order_number}">
                <div class="flex flex-col">
                  <label for="verification_code" class="text-gray-300 mb-2">验证码：</label>
                  <div class="relative">
                    <input type="text" id="verification_code" name="psw" placeholder="请输入验证码" class="px-4 py-2 bg-gray-700/50 text-white rounded-lg border border-gray-600 input-focus outline-none" required>
                    <img id="captcha-image2" src="./app/captcha.php" alt="验证码" class="index-captcha absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer" style="width: 100px; height: 40px;">
                  </div>
                </div>
                <div class="flex flex-col">
                  <label for="device_code" class="text-gray-300 mb-2">设备码:（注意大小写！）</label>
                  <input type="text" id="device_code" name="psn" class="px-4 py-2 bg-gray-700/50 text-white rounded-lg border border-gray-600 input-focus outline-none" minlength="10" maxlength="50" placeholder="请输入设备码（10-50位字母数字）" pattern="[A-Za-z0-9]{10,50}" required>
                </div>
                <div class="bg-yellow-500/20 border border-yellow-500/30 rounded-lg p-3">
                  <p class="text-yellow-400 text-sm text-center">
                    <i class="fa fa-exclamation-triangle mr-1"></i>
                    查询将会消耗可用次数，错了不补！！<br>
                    登录即可记录历史查询密码
                  </p>
                </div>
                <button type="submit" class="w-full py-3 bg-primary text-white rounded-lg font-semibold hover:bg-primary/90 transition-colors flex items-center justify-center gap-2">
                  <i class="fa fa-key"></i>
                  查询解锁密码
                </button>
              </form>
            </div>
          `);
          
          // 绑定验证码图片点击事件
          $('#captcha-image2').off('click').on('click', function() {
            refreshCaptcha2();
          });
        }
      },
      error: function (xhr, status, error) {
        $('#results').html(`<div class="bg-red-500/80 text-white rounded p-4 text-center">
          <i class="fa fa-exclamation-triangle mr-2"></i>
          网络请求失败，请检查网络连接后重试<br>
          <small class="text-red-200">错误代码: ${xhr.status}</small>
        </div>`);
      }
    });
  });

  $(document).on('submit', '#unlock-form', function (event) {
    event.preventDefault();
    const form = $(this);
    
    // 验证设备码格式
    const deviceCode = $('#device_code').val().trim();
    const verificationCode = $('#verification_code').val().trim();
    
    if (!deviceCode) {
      alert('请输入设备码');
      return;
    }
    
    if (!/^[A-Za-z0-9]{10,50}$/.test(deviceCode)) {
      alert('设备码格式不正确，应为10-50位字母和数字组合');
      return;
    }
    
    if (!verificationCode) {
      alert('请输入验证码');
      return;
    }

    if (form.data('is-processing')) {
      alert('为了安全，你必须刷新以进行下一次提交！');
      if (confirm("是否刷新页面？")) {
        window.location.reload();
      }
      return;
    }

    if (!confirm("确定要兑换吗？这将会消耗一次数量！")) {
      return;
    }

    form.data('is-processing', true);
    
    // 显示加载状态
    $('#unlock-password').html('<div class="text-center py-8"><i class="fa fa-spinner fa-spin text-primary text-2xl"></i><p class="text-gray-400 mt-2">正在生成解锁密码...</p></div>');

    $.ajax({
      url: './app/api.php',
      type: 'POST',
      data: form.serialize() + '&activation_type=' + encodeURIComponent($('#activation-type').val()),
      success: function (response) {
        let data;
        try {
          data = JSON.parse(response);
        } catch (e) {
          $('#unlock-password').html('<div class="bg-red-500/80 text-white rounded p-4 text-center">服务器响应异常，请重试</div>');
          return;
        }

        if (data.error) {
          $('#unlock-password').html(`<div class="bg-red-500/80 text-white rounded p-4 text-center">
            <i class="fa fa-exclamation-triangle mr-2"></i>${data.error}
            ${data.error.includes('验证码') ? '<br><button onclick="refreshCaptcha2();" class="mt-2 px-4 py-1 bg-white text-red-500 rounded text-sm">刷新验证码</button>' : ''}
          </div>`);
          // 如果是验证码错误，自动刷新验证码
          if (data.error.includes('验证码')) {
            refreshCaptcha2();
          }
        } else {
          // 清除之前的结果
          $('#unlock-password').empty();

          // 处理并显示多个解锁密码
          if (data.unlock_pwds && data.unlock_pwds.length > 0) {
            let resultHtml = `
              <div class="bg-gray-800/30 backdrop-blur-lg rounded-2xl p-6 card-hover">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-xl font-semibold text-white">解锁密码</h3>
                  <span class="text-green-400 text-sm"><i class="fa fa-check-circle"></i> 生成成功</span>
                </div>
                <div class="space-y-3 text-center">
                  <div class="bg-green-500/20 border border-green-500/30 rounded-lg p-3">
                    <p class="text-green-400 font-bold">请复制以下密码，并截图保存！</p>
                  </div>
            `;

            data.unlock_pwds.forEach(item => {
              resultHtml += `
                <div class="p-4 bg-gray-700/50 rounded-xl mb-3">
                  <p class="text-2xl font-bold text-white">解锁密码: ${item.unlock_pwd}</p>
                  <button class="copy-btn ml-2 text-primary hover:text-primary/80 transition-colors" data-pwd="${item.unlock_pwd}">
                    <i class="fas fa-copy"></i> 复制
                  </button>
                </div>
              `;
            });

            resultHtml += `
                <p class="text-gray-400 mt-4">密码已复制到剪贴板，可以粘贴使用了</p>
              </div>
            `;
            $('#unlock-password').html(resultHtml);
            
            // 添加复制功能
            $('.copy-btn').click(function() {
              let pwd = $(this).data('pwd');
              navigator.clipboard.writeText(pwd).then(function() {
                alert('密码已复制到剪贴板！');
              }, function() {
                alert('复制失败，请手动复制！');
              });
            });
          } else {
            const currentActivationType = $('#activation-type').val();
            const currentProductTypeName = currentActivationType === 'quickapp' ? '快应用' : '表盘';
            $('#unlock-password').html(`
              <div class="bg-red-500/80 text-white rounded p-4 text-center">
                <i class="fa fa-exclamation-triangle mr-2"></i>
                未找到${currentProductTypeName}解锁密码，请检查订单号和设备码。
              </div>
            `);
          }
        }

        // form.data('is-processing', false);
      },
      error: function (xhr, status, error) {
        $('#unlock-password').html(`<div class="bg-red-500/80 text-white rounded p-4 text-center">
          <i class="fa fa-exclamation-triangle mr-2"></i>
          网络请求失败，请检查网络连接后重试<br>
          <small class="text-red-200">错误代码: ${xhr.status}</small>
        </div>`);
        // form.data('is-processing', false);
      }
    });
  });

});