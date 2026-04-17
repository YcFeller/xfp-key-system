<?php
/**
 * 站长管理 - 快应用管理页面
 * 管理所有用户的快应用
 */

session_start();
$user_role = $_SESSION['user_role'] ?? null;
if ($user_role === null || $user_role < 3) {
  header("Location: ../../index.php");
  exit;
}

require_once '../../app/config.php';

// 数据库连接
try {
    $conn = new mysqli($servername, $db_user, $db_pass, $dbname);
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die('数据库连接失败: ' . htmlspecialchars($e->getMessage()));
}

// 获取统计数据
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM xfp_quickapp_list")->fetch_assoc()['count'];
$stats['active'] = $conn->query("SELECT COUNT(*) as count FROM xfp_quickapp_list WHERE status = 1")->fetch_assoc()['count'];
$stats['inactive'] = $conn->query("SELECT COUNT(*) as count FROM xfp_quickapp_list WHERE status = 0")->fetch_assoc()['count'];
$stats['today'] = $conn->query("SELECT COUNT(*) as count FROM xfp_quickapp_list WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];

// 获取分类统计
$category_stats = [];
$category_result = $conn->query("SELECT category, COUNT(*) as count FROM xfp_quickapp_list GROUP BY category ORDER BY count DESC");
while ($row = $category_result->fetch_assoc()) {
    $category_stats[] = $row;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>快应用管理 - 站长管理</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3176FF',
                        secondary: '#6C47FF'
                    }
                }
            }
        }
    </script>
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
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
    </style>
    <script src="../../files/js/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-900 text-white min-h-screen">
    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- 头部导航 -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-bold text-white">快应用管理</h1>
                <span class="px-3 py-1 bg-primary/20 text-primary rounded-full text-sm">站长权限</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="./index.php" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>返回管理首页
                </a>
                <a href="../../pages/auth/logout.php" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>退出登录
                </a>
            </div>
        </div>

        <!-- 统计卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">总快应用数</p>
                        <p class="text-2xl font-bold text-white"><?php echo $stats['total']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-mobile-alt text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">启用状态</p>
                        <p class="text-2xl font-bold text-green-400"><?php echo $stats['active']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">禁用状态</p>
                        <p class="text-2xl font-bold text-red-400"><?php echo $stats['inactive']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-500/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-times-circle text-red-400 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">今日新增</p>
                        <p class="text-2xl font-bold text-yellow-400"><?php echo $stats['today']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus-circle text-yellow-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 搜索和筛选 -->
        <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-300 text-sm mb-2">搜索快应用</label>
                    <input type="text" id="searchInput" placeholder="搜索名称、ID或包名..." 
                           class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
                </div>
                <div>
                    <label class="block text-gray-300 text-sm mb-2">状态筛选</label>
                    <select id="statusFilter" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
                        <option value="">全部状态</option>
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-300 text-sm mb-2">分类筛选</label>
                    <select id="categoryFilter" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
                        <option value="">全部分类</option>
                        <option value="general">通用</option>
                        <option value="game">游戏</option>
                        <option value="tool">工具</option>
                        <option value="entertainment">娱乐</option>
                        <option value="education">教育</option>
                        <option value="business">商务</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button id="searchBtn" class="w-full px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                        <i class="fas fa-search mr-2"></i>搜索
                    </button>
                </div>
            </div>
        </div>

        <!-- 快应用列表 -->
        <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl overflow-hidden">
            <div class="p-6 border-b border-gray-700">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white">快应用列表</h2>
                    <div class="flex items-center gap-4">
                        <button id="refreshBtn" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fas fa-sync-alt mr-2"></i>刷新
                        </button>
                        <button id="batchDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors" disabled>
                            <i class="fas fa-trash mr-2"></i>批量删除
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-4 text-left">
                                <input type="checkbox" id="selectAll" class="rounded border-gray-600 bg-gray-700 text-primary focus:ring-primary">
                            </th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">快应用信息</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">开发者</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">分类</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">状态</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">创建时间</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">操作</th>
                        </tr>
                    </thead>
                    <tbody id="quickappTableBody">
                        <!-- 数据将通过AJAX加载 -->
                    </tbody>
                </table>
            </div>
            
            <!-- 分页 -->
            <div class="p-6 border-t border-gray-700">
                <div id="pagination" class="flex items-center justify-between">
                    <!-- 分页控件将通过JavaScript生成 -->
                </div>
            </div>
        </div>
    </div>

    <!-- 编辑快应用模态框 -->
    <div id="editModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-xl p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-white">编辑快应用</h3>
                <button id="closeEditModal" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="editForm" class="space-y-4">
                <input type="hidden" id="editId">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-300 text-sm mb-2">快应用名称</label>
                        <input type="text" id="editName" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-300 text-sm mb-2">快应用ID</label>
                        <input type="text" id="editQuickappId" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-300 text-sm mb-2">包名</label>
                        <input type="text" id="editPackageName" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-300 text-sm mb-2">版本号</label>
                        <input type="text" id="editVersion" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-gray-300 text-sm mb-2">应用描述</label>
                    <textarea id="editDescription" rows="3" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-300 text-sm mb-2">分类</label>
                        <select id="editCategory" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
                            <option value="general">通用</option>
                            <option value="game">游戏</option>
                            <option value="tool">工具</option>
                            <option value="entertainment">娱乐</option>
                            <option value="education">教育</option>
                            <option value="business">商务</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-sm mb-2">状态</label>
                        <select id="editStatus" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
                            <option value="1">启用</option>
                            <option value="0">禁用</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-gray-300 text-sm mb-2">图标链接</label>
                    <input type="text" id="editIconLink" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
                </div>
                <div class="flex justify-end gap-4 pt-4">
                    <button type="button" id="cancelEdit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        取消
                    </button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                        保存更改
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            let currentPage = 1;
            let selectedItems = [];

            // 加载快应用列表
            function loadQuickappList(page = 1, search = '', status = '', category = '') {
                $.ajax({
                    url: '../../app/webmaster_quickapp_actions.php',
                    type: 'POST',
                    data: {
                        action: 'list',
                        page: page,
                        search: search,
                        status: status,
                        category: category
                    },
                    success: function(response) {
                        const data = JSON.parse(response);
                        if (data.success) {
                            renderQuickappTable(data.data);
                            renderPagination(data.pagination);
                        } else {
                            alert('加载失败: ' + data.message);
                        }
                    },
                    error: function() {
                        alert('服务器错误，请重试');
                    }
                });
            }

            // 渲染快应用表格
            function renderQuickappTable(quickapps) {
                const tbody = $('#quickappTableBody');
                tbody.empty();

                if (quickapps.length === 0) {
                    tbody.append(`
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400">
                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                <p>暂无快应用数据</p>
                            </td>
                        </tr>
                    `);
                    return;
                }

                quickapps.forEach(function(quickapp) {
                    const statusBadge = quickapp.status == 1 
                        ? '<span class="px-2 py-1 bg-green-500/20 text-green-400 rounded-full text-xs">启用</span>'
                        : '<span class="px-2 py-1 bg-red-500/20 text-red-400 rounded-full text-xs">禁用</span>';
                    
                    const categoryMap = {
                        'general': '通用',
                        'game': '游戏', 
                        'tool': '工具',
                        'entertainment': '娱乐',
                        'education': '教育',
                        'business': '商务'
                    };
                    
                    const iconHtml = quickapp.icon_link 
                        ? `<img src="${quickapp.icon_link}" alt="图标" class="w-8 h-8 rounded-lg object-cover">`
                        : '<i class="fas fa-mobile-alt text-gray-400 text-lg"></i>';

                    tbody.append(`
                        <tr class="border-b border-gray-700/50 hover:bg-gray-700/30">
                            <td class="px-6 py-4">
                                <input type="checkbox" class="item-checkbox rounded border-gray-600 bg-gray-700 text-primary focus:ring-primary" data-id="${quickapp.id}">
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    ${iconHtml}
                                    <div>
                                        <p class="text-white font-semibold">${quickapp.name}</p>
                                        <p class="text-gray-400 text-sm">ID: ${quickapp.quickapp_id}</p>
                                        ${quickapp.package_name ? `<p class="text-gray-500 text-xs">${quickapp.package_name}</p>` : ''}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-white">${quickapp.username}</p>
                                <p class="text-gray-400 text-sm">ID: ${quickapp.system_user_id}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded-full text-xs">${categoryMap[quickapp.category] || quickapp.category}</span>
                            </td>
                            <td class="px-6 py-4">${statusBadge}</td>
                            <td class="px-6 py-4">
                                <p class="text-gray-300">${quickapp.created_at}</p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <button class="edit-btn px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors" data-id="${quickapp.id}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="delete-btn px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition-colors" data-id="${quickapp.id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `);
                });
            }

            // 渲染分页
            function renderPagination(pagination) {
                const paginationDiv = $('#pagination');
                paginationDiv.empty();

                if (pagination.total_pages <= 1) return;

                let paginationHtml = `
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400">第 ${pagination.current_page} 页，共 ${pagination.total_pages} 页</span>
                    </div>
                    <div class="flex items-center gap-2">
                `;

                // 上一页
                if (pagination.current_page > 1) {
                    paginationHtml += `<button class="page-btn px-3 py-1 bg-gray-700 text-white rounded hover:bg-gray-600" data-page="${pagination.current_page - 1}">上一页</button>`;
                }

                // 页码
                for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
                    const activeClass = i === pagination.current_page ? 'bg-primary' : 'bg-gray-700 hover:bg-gray-600';
                    paginationHtml += `<button class="page-btn px-3 py-1 ${activeClass} text-white rounded" data-page="${i}">${i}</button>`;
                }

                // 下一页
                if (pagination.current_page < pagination.total_pages) {
                    paginationHtml += `<button class="page-btn px-3 py-1 bg-gray-700 text-white rounded hover:bg-gray-600" data-page="${pagination.current_page + 1}">下一页</button>`;
                }

                paginationHtml += '</div>';
                paginationDiv.html(paginationHtml);
            }

            // 搜索功能
            $('#searchBtn').click(function() {
                currentPage = 1;
                loadQuickappList(currentPage, $('#searchInput').val(), $('#statusFilter').val(), $('#categoryFilter').val());
            });

            // 回车搜索
            $('#searchInput').keypress(function(e) {
                if (e.which === 13) {
                    $('#searchBtn').click();
                }
            });

            // 刷新按钮
            $('#refreshBtn').click(function() {
                loadQuickappList(currentPage, $('#searchInput').val(), $('#statusFilter').val(), $('#categoryFilter').val());
            });

            // 分页点击
            $(document).on('click', '.page-btn', function() {
                currentPage = parseInt($(this).data('page'));
                loadQuickappList(currentPage, $('#searchInput').val(), $('#statusFilter').val(), $('#categoryFilter').val());
            });

            // 全选功能
            $('#selectAll').change(function() {
                const isChecked = $(this).is(':checked');
                $('.item-checkbox').prop('checked', isChecked);
                updateSelectedItems();
            });

            // 单选功能
            $(document).on('change', '.item-checkbox', function() {
                updateSelectedItems();
                const totalItems = $('.item-checkbox').length;
                const checkedItems = $('.item-checkbox:checked').length;
                $('#selectAll').prop('checked', totalItems > 0 && checkedItems === totalItems);
            });

            // 更新选中项目
            function updateSelectedItems() {
                selectedItems = [];
                $('.item-checkbox:checked').each(function() {
                    selectedItems.push($(this).data('id'));
                });
                $('#batchDeleteBtn').prop('disabled', selectedItems.length === 0);
            }

            // 编辑功能
            $(document).on('click', '.edit-btn', function() {
                const id = $(this).data('id');
                // 获取快应用详情并显示编辑模态框
                $.ajax({
                    url: '../../app/webmaster_quickapp_actions.php',
                    type: 'POST',
                    data: { action: 'get', id: id },
                    success: function(response) {
                        const data = JSON.parse(response);
                        if (data.success) {
                            const quickapp = data.data;
                            $('#editId').val(quickapp.id);
                            $('#editName').val(quickapp.name);
                            $('#editQuickappId').val(quickapp.quickapp_id);
                            $('#editPackageName').val(quickapp.package_name);
                            $('#editVersion').val(quickapp.version);
                            $('#editDescription').val(quickapp.description);
                            $('#editCategory').val(quickapp.category);
                            $('#editStatus').val(quickapp.status);
                            $('#editIconLink').val(quickapp.icon_link);
                            $('#editModal').removeClass('hidden').addClass('flex');
                        } else {
                            alert('获取快应用信息失败: ' + data.message);
                        }
                    }
                });
            });

            // 关闭编辑模态框
            $('#closeEditModal, #cancelEdit').click(function() {
                $('#editModal').removeClass('flex').addClass('hidden');
            });

            // 提交编辑表单
            $('#editForm').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: '../../app/webmaster_quickapp_actions.php',
                    type: 'POST',
                    data: {
                        action: 'update',
                        id: $('#editId').val(),
                        name: $('#editName').val(),
                        quickapp_id: $('#editQuickappId').val(),
                        package_name: $('#editPackageName').val(),
                        version: $('#editVersion').val(),
                        description: $('#editDescription').val(),
                        category: $('#editCategory').val(),
                        status: $('#editStatus').val(),
                        icon_link: $('#editIconLink').val()
                    },
                    success: function(response) {
                        const data = JSON.parse(response);
                        if (data.success) {
                            alert('更新成功');
                            $('#editModal').removeClass('flex').addClass('hidden');
                            loadQuickappList(currentPage, $('#searchInput').val(), $('#statusFilter').val(), $('#categoryFilter').val());
                        } else {
                            alert('更新失败: ' + data.message);
                        }
                    }
                });
            });

            // 删除功能
            $(document).on('click', '.delete-btn', function() {
                const id = $(this).data('id');
                if (confirm('确定要删除这个快应用吗？此操作不可恢复。')) {
                    $.ajax({
                        url: '../../app/webmaster_quickapp_actions.php',
                        type: 'POST',
                        data: { action: 'delete', id: id },
                        success: function(response) {
                            const data = JSON.parse(response);
                            if (data.success) {
                                alert('删除成功');
                                loadQuickappList(currentPage, $('#searchInput').val(), $('#statusFilter').val(), $('#categoryFilter').val());
                            } else {
                                alert('删除失败: ' + data.message);
                            }
                        }
                    });
                }
            });

            // 批量删除
            $('#batchDeleteBtn').click(function() {
                if (selectedItems.length === 0) return;
                if (confirm(`确定要删除选中的 ${selectedItems.length} 个快应用吗？此操作不可恢复。`)) {
                    $.ajax({
                        url: '../../app/webmaster_quickapp_actions.php',
                        type: 'POST',
                        data: { action: 'batch_delete', ids: selectedItems },
                        success: function(response) {
                            const data = JSON.parse(response);
                            if (data.success) {
                                alert('批量删除成功');
                                selectedItems = [];
                                $('#selectAll').prop('checked', false);
                                loadQuickappList(currentPage, $('#searchInput').val(), $('#statusFilter').val(), $('#categoryFilter').val());
                            } else {
                                alert('批量删除失败: ' + data.message);
                            }
                        }
                    });
                }
            });

            // 初始加载
            loadQuickappList();
        });
    </script>
</body>
</html>