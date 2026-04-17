<?php
session_start();
require_once '../../app/config.php';

// 验证管理员权限
$user_role = $_SESSION['user_role'] ?? null;
if ($user_role === null || $user_role < 3) {
    header("Location: ../../index.php");
    exit;
}

// 使用config.php中的mysqli连接
if (!isset($mysqli_conn) || $mysqli_conn === null) {
    die('数据库连接失败。');
}
$conn = $mysqli_conn;

// 获取筛选参数
$status_filter = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 构建查询条件
$where_clause = "";
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $where_clause = "WHERE status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// 获取申请总数
$count_sql = "SELECT COUNT(*) as total FROM permission_applications $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_count = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_count / $per_page);

// 获取申请列表
$sql = "SELECT * FROM permission_applications $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 获取统计数据
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM permission_applications";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>权限申请审核 - 站长管理</title>
    <link rel="stylesheet" href="../../files/css/admin/wm_index.css">
    <script src="../../files/js/jquery-3.6.0.min.js"></script>
    <style>
        /* 扩展样式 */
        .stats-container {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            min-width: 120px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .filters {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 20px 0;
        }
        
        .application-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .application-table th,
        .application-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .application-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-under_review { background-color: #d1ecf1; color: #0c5460; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary { background-color: #007bff; color: white; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-warning { background-color: #ffc107; color: #212529; }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .pagination a {
            padding: 8px 12px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #007bff;
        }
        
        .pagination a.current {
            background-color: #007bff;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        
        .alert {
            padding: 12px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>

<body>
    <!-- 导航栏 -->
    <nav>
        <ul>
            <li><a href="index.php">仪表盘</a></li>
            <li><a href="orderlist.php">订单管理</a></li>
            <li><a href="../../pages/user/activation_records.php">激活记录管理</a></li>
            <li><a href="userlist.php">用户管理</a></li>
            <li><a href="permission_review.php" style="color: #ffd700;">权限审核</a></li>
            <li><a href="security_dashboard.php">安全管理</a></li>
        </ul>
    </nav>

    <h1>权限申请审核</h1>

    <!-- 统计卡片 -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">总申请数</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">待审核</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['under_review']; ?></div>
            <div class="stat-label">审核中</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">已通过</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['rejected']; ?></div>
            <div class="stat-label">已拒绝</div>
        </div>
    </div>

    <!-- 筛选器 -->
    <div class="filters">
        <form method="GET" style="display: flex; gap: 15px; align-items: center;">
            <label for="status">状态筛选：</label>
            <select name="status" id="status" onchange="this.form.submit()">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>全部</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>待审核</option>
                <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>审核中</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>已通过</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>已拒绝</option>
            </select>
        </form>
    </div>

    <!-- 申请列表 -->
    <table class="application-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>用户信息</th>
                <th>申请类型</th>
                <th>公司名称</th>
                <th>申请时间</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($applications)): ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px;">暂无申请记录</td>
            </tr>
            <?php else: ?>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td><?php echo htmlspecialchars($app['id']); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($app['username']); ?></strong><br>
                    <small><?php echo htmlspecialchars($app['email']); ?></small><br>
                    <small>ID: <?php echo htmlspecialchars($app['user_id']); ?></small>
                </td>
                <td><?php echo htmlspecialchars($app['application_type']); ?></td>
                <td><?php echo htmlspecialchars($app['company_name'] ?: '-'); ?></td>
                <td><?php echo date('Y-m-d H:i', strtotime($app['created_at'])); ?></td>
                <td>
                    <?php
                    $status_classes = [
                        'pending' => 'status-pending',
                        'under_review' => 'status-under_review',
                        'approved' => 'status-approved',
                        'rejected' => 'status-rejected'
                    ];
                    $status_texts = [
                        'pending' => '待审核',
                        'under_review' => '审核中',
                        'approved' => '已通过',
                        'rejected' => '已拒绝'
                    ];
                    $status_class = $status_classes[$app['status']] ?? '';
                    $status_text = $status_texts[$app['status']] ?? $app['status'];
                    ?>
                    <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="viewApplication(<?php echo $app['id']; ?>)">查看</button>
                        <?php if ($app['status'] === 'pending' || $app['status'] === 'under_review'): ?>
                        <button class="btn btn-success" onclick="reviewApplication(<?php echo $app['id']; ?>, 'approved')">通过</button>
                        <button class="btn btn-danger" onclick="reviewApplication(<?php echo $app['id']; ?>, 'rejected')">拒绝</button>
                        <?php endif; ?>
                        <button class="btn btn-warning" onclick="deleteApplication(<?php echo $app['id']; ?>)" title="删除申请记录">删除</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 分页 -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>">&laquo; 上一页</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>" 
           class="<?php echo $i === $page ? 'current' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>">下一页 &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 查看申请详情模态框 -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('viewModal')">&times;</span>
            <h2>申请详情</h2>
            <div id="applicationDetails"></div>
        </div>
    </div>

    <!-- 审核模态框 -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('reviewModal')">&times;</span>
            <h2>审核申请</h2>
            <div id="reviewContent">
                <div class="form-group">
                    <label for="adminComment">审核意见：</label>
                    <textarea id="adminComment" rows="4" placeholder="请输入审核意见（可选）"></textarea>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button class="btn btn-secondary" onclick="closeModal('reviewModal')">取消</button>
                    <button class="btn btn-success" id="confirmReview">确认</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 消息提示 -->
    <div id="messageAlert" style="display: none;"></div>

    <script>
        let currentApplicationId = null;
        let currentAction = null;

        /**
         * 查看申请详情
         * @param {number} applicationId 申请ID
         */
        function viewApplication(applicationId) {
            $.ajax({
                url: '../../app/webmaster/permission_review_api.php',
                type: 'GET',
                data: { action: 'get_application', id: applicationId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const app = response.data;
                        const detailsHtml = `
                            <div style="line-height: 1.6;">
                                <p><strong>申请ID：</strong> ${app.id}</p>
                                <p><strong>用户名：</strong> ${app.username}</p>
                                <p><strong>邮箱：</strong> ${app.email}</p>
                                <p><strong>用户ID：</strong> ${app.user_id}</p>
                                <p><strong>申请类型：</strong> ${app.application_type}</p>
                                <p><strong>公司名称：</strong> ${app.company_name || '未填写'}</p>
                                <p><strong>联系电话：</strong> ${app.contact_phone || '未填写'}</p>
                                <p><strong>申请时间：</strong> ${app.created_at}</p>
                                <p><strong>更新时间：</strong> ${app.updated_at}</p>
                                <p><strong>当前状态：</strong> ${getStatusText(app.status)}</p>
                                ${app.reviewed_at ? `<p><strong>审核时间：</strong> ${app.reviewed_at}</p>` : ''}
                                ${app.admin_comment ? `<p><strong>审核意见：</strong> ${app.admin_comment}</p>` : ''}
                                <hr>
                                <p><strong>项目描述：</strong></p>
                                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0;">
                                    ${app.project_description.replace(/\n/g, '<br>')}
                                </div>
                                <p><strong>预期使用情况：</strong></p>
                                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0;">
                                    ${app.expected_usage.replace(/\n/g, '<br>')}
                                </div>
                                ${app.technical_background ? `
                                    <p><strong>技术背景：</strong></p>
                                    <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0;">
                                        ${app.technical_background.replace(/\n/g, '<br>')}
                                    </div>
                                ` : ''}
                            </div>
                        `;
                        $('#applicationDetails').html(detailsHtml);
                        $('#viewModal').show();
                    } else {
                        showMessage(response.message || '获取申请详情失败', 'error');
                    }
                },
                error: function() {
                    showMessage('获取申请详情失败，请稍后重试', 'error');
                }
            });
        }

        /**
         * 审核申请
         * @param {number} applicationId 申请ID
         * @param {string} action 审核动作：approved 或 rejected
         */
        function reviewApplication(applicationId, action) {
            currentApplicationId = applicationId;
            currentAction = action;
            
            const actionText = action === 'approved' ? '通过' : '拒绝';
            $('#reviewModal h2').text(`${actionText}申请`);
            $('#confirmReview').text(`确认${actionText}`);
            $('#confirmReview').removeClass('btn-success btn-danger').addClass(action === 'approved' ? 'btn-success' : 'btn-danger');
            $('#adminComment').val('');
            $('#reviewModal').show();
        }

        /**
         * 确认审核
         */
        $('#confirmReview').click(function() {
            const comment = $('#adminComment').val().trim();
            
            $.ajax({
                url: '../../app/webmaster/permission_review_api.php',
                type: 'POST',
                data: {
                    action: 'review_application',
                    id: currentApplicationId,
                    status: currentAction,
                    comment: comment
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(response.message || '审核成功', 'success');
                        closeModal('reviewModal');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage(response.message || '审核失败', 'error');
                    }
                },
                error: function() {
                    showMessage('审核失败，请稍后重试', 'error');
                }
            });
        });

        /**
         * 关闭模态框
         * @param {string} modalId 模态框ID
         */
        function closeModal(modalId) {
            $('#' + modalId).hide();
        }

        /**
         * 删除申请记录
         * @param {number} applicationId 申请ID
         */
        function deleteApplication(applicationId) {
            if (!confirm('确定要删除这条申请记录吗？此操作不可撤销！')) {
                return;
            }
            
            $.ajax({
                url: '../../app/webmaster/permission_review_api.php',
                type: 'POST',
                data: {
                    action: 'delete_application',
                    id: applicationId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage('申请记录删除成功', 'success');
                        // 刷新页面
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showMessage(response.message || '删除失败', 'error');
                    }
                },
                error: function() {
                    showMessage('删除失败，请稍后重试', 'error');
                }
            });
        }

        /**
         * 获取状态文本
         * @param {string} status 状态
         * @returns {string} 状态文本
         */
        function getStatusText(status) {
            const statusMap = {
                'pending': '待审核',
                'under_review': '审核中',
                'approved': '已通过',
                'rejected': '已拒绝'
            };
            return statusMap[status] || status;
        }

        /**
         * 显示消息
         * @param {string} message 消息内容
         * @param {string} type 消息类型：success 或 error
         */
        function showMessage(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            $('#messageAlert').removeClass('alert-success alert-error').addClass(`alert ${alertClass}`).text(message).show();
            
            setTimeout(function() {
                $('#messageAlert').hide();
            }, 3000);
        }

        // 点击模态框外部关闭
        $(window).click(function(event) {
            if (event.target.classList.contains('modal')) {
                $(event.target).hide();
            }
        });
    </script>
</body>
</html>