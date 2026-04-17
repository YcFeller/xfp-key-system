-- 完整数据库结构文件
-- 生成时间: 2025-01-09
-- 数据库: xfp_fs0_xr
-- 包含所有表结构：核心业务表、安全管理表、权限申请表、验证码表

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库: `xfp_fs0_xr`
--

-- ========================================
-- 核心业务表
-- ========================================

--
-- 表的结构 `users` - 用户表
--
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `afdian_user_id` varchar(255) DEFAULT NULL,
  `afdian_token` varchar(128) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL,
  `avatar_link` varchar(256) NOT NULL,
  `status` varchar(20) NOT NULL,
  `activation_code` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `afdian_user_id` (`afdian_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 表的结构 `xfp_order` - 订单表
--
CREATE TABLE IF NOT EXISTS `xfp_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `out_trade_no` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `afdian_user_id` varchar(255) NOT NULL,
  `system_user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `remark` text,
  `discount` decimal(5,2) DEFAULT NULL,
  `sku_detail` text,
  `product_name` varchar(255) DEFAULT NULL,
  `plan_id` varchar(50) DEFAULT NULL,
  `downloads_limit` int(50) NOT NULL DEFAULT '2',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order` (`out_trade_no`,`system_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 表的结构 `xfp_wflist` - 表盘列表
--
CREATE TABLE IF NOT EXISTS `xfp_wflist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `watchface_id` char(12) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `upload_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `downloads_limit` int(11) DEFAULT '1',
  `plan_id` varchar(50) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `image_link` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- 表的结构 `xfp_quickapp_list` - 快应用列表
--
CREATE TABLE IF NOT EXISTS `xfp_quickapp_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '快应用名称',
  `quickapp_id` varchar(255) DEFAULT NULL COMMENT '快应用ID',
  `package_name` varchar(255) DEFAULT NULL COMMENT '包名',
  `version` varchar(50) DEFAULT '1.0.0' COMMENT '版本号',
  `description` text DEFAULT NULL COMMENT '应用描述',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态：1-启用，0-禁用',
  `upload_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  `downloads_limit` int(11) DEFAULT '1' COMMENT '下载限制',
  `plan_id` varchar(50) DEFAULT NULL COMMENT '计划ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `icon_link` varchar(255) DEFAULT NULL COMMENT '图标链接',
  `file_path` varchar(500) DEFAULT NULL COMMENT '文件路径',
  `file_size` bigint(20) DEFAULT NULL COMMENT '文件大小（字节）',
  `category` varchar(100) DEFAULT 'general' COMMENT '分类',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_quickapp_id` (`quickapp_id`),
  KEY `idx_status` (`status`),
  KEY `idx_upload_time` (`upload_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快应用列表管理表';}]}}}

--
-- 表的结构 `xfp_activation_records` - 激活记录表（支持表盘和快应用激活）
--
CREATE TABLE IF NOT EXISTS `xfp_activation_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(255) NOT NULL,
  `watchface_id` char(12) DEFAULT NULL COMMENT '表盘ID（兼容字段）',
  `product_id` varchar(255) DEFAULT NULL COMMENT '产品ID（表盘ID或快应用ID）',
  `product_type` varchar(20) NOT NULL DEFAULT 'watchface' COMMENT '产品类型：watchface-表盘，quickapp-快应用',
  `user_id` int(11) NOT NULL,
  `device_code` varchar(255) NOT NULL,
  `unlock_pwd` varchar(255) NOT NULL,
  `activation_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  KEY `product_type` (`product_type`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `xfp_activation_records_ibfk_1` FOREIGN KEY (`order_number`) REFERENCES `xfp_order` (`out_trade_no`),
  CONSTRAINT `xfp_activation_records_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 权限申请管理表
-- ========================================

--
-- 表的结构 `permission_applications` - 用户权限申请表
--
CREATE TABLE IF NOT EXISTS `permission_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '申请ID',
  `user_id` int(11) NOT NULL COMMENT '申请用户ID',
  `username` varchar(50) NOT NULL COMMENT '申请用户名',
  `email` varchar(100) NOT NULL COMMENT '申请用户邮箱',
  `application_type` varchar(50) NOT NULL DEFAULT 'developer' COMMENT '申请类型：developer-开发者权限',
  `company_name` varchar(100) DEFAULT NULL COMMENT '公司名称',
  `project_description` text NOT NULL COMMENT '项目描述',
  `expected_usage` text NOT NULL COMMENT '预期使用情况',
  `technical_background` text DEFAULT NULL COMMENT '技术背景',
  `contact_phone` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `status` enum('pending','approved','rejected','under_review') NOT NULL DEFAULT 'pending' COMMENT '申请状态：pending-待审核，approved-已通过，rejected-已拒绝，under_review-审核中',
  `admin_id` int(11) DEFAULT NULL COMMENT '审核管理员ID',
  `admin_comment` text DEFAULT NULL COMMENT '管理员审核意见',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT '审核时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `permission_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户权限申请表';

-- ========================================
-- 验证码管理表
-- ========================================

--
-- 表的结构 `verification_codes` - 验证码表
--
CREATE TABLE IF NOT EXISTS `verification_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'password_reset',
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `verification_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 用户行为日志和设置表
-- ========================================

--
-- 表的结构 `user_action_logs` - 用户行为日志表
--
CREATE TABLE IF NOT EXISTS `user_action_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `action` varchar(50) NOT NULL COMMENT '操作类型',
  `description` text COMMENT '操作描述',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text COMMENT '用户代理',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `user_action_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户行为日志表';

--
-- 表的结构 `user_settings` - 用户个性化设置表
--
CREATE TABLE IF NOT EXISTS `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `auto_activation_enabled` tinyint(1) DEFAULT '0' COMMENT '自动激活开关：0=关闭，1=开启',
  `email_notifications` tinyint(1) DEFAULT '1' COMMENT '邮件通知：0=关闭，1=开启',
  `theme_preference` varchar(20) DEFAULT 'dark' COMMENT '主题偏好：dark/light',
  `language_preference` varchar(10) DEFAULT 'zh-CN' COMMENT '语言偏好',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户个性化设置表';

-- ========================================
-- 安全管理和日志记录表
-- ========================================

--
-- 表的结构 `api_rate_limits` - API请求频率限制表
--
CREATE TABLE IF NOT EXISTS `api_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL COMMENT 'IP地址',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `endpoint` varchar(100) NOT NULL COMMENT '接口端点',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_ip_endpoint_time` (`ip`, `endpoint`, `created_at`),
  KEY `idx_user_endpoint_time` (`user_id`, `endpoint`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API请求频率限制记录表';

--
-- 表的结构 `ip_blacklist` - IP黑名单表
--
CREATE TABLE IF NOT EXISTS `ip_blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL COMMENT 'IP地址',
  `reason` varchar(255) DEFAULT NULL COMMENT '封禁原因',
  `banned_until` timestamp NULL DEFAULT NULL COMMENT '封禁到期时间，NULL表示永久封禁',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ip` (`ip`),
  KEY `idx_banned_until` (`banned_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IP黑名单表';

--
-- 表的结构 `failed_attempts` - 失败尝试记录表
--
CREATE TABLE IF NOT EXISTS `failed_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL COMMENT 'IP地址',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `endpoint` varchar(100) NOT NULL COMMENT '接口端点',
  `attempt_type` varchar(50) NOT NULL COMMENT '尝试类型',
  `details` text DEFAULT NULL COMMENT '详细信息',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_ip_endpoint_time` (`ip`, `endpoint`, `created_at`),
  KEY `idx_user_endpoint_time` (`user_id`, `endpoint`, `created_at`),
  KEY `idx_attempt_type` (`attempt_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='失败尝试记录表';

--
-- 表的结构 `system_logs` - 系统日志表
--
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(32) NOT NULL COMMENT '追踪ID',
  `level` varchar(20) NOT NULL COMMENT '日志级别',
  `type` varchar(50) NOT NULL COMMENT '日志类型',
  `message` text NOT NULL COMMENT '日志消息',
  `context` json DEFAULT NULL COMMENT '上下文数据',
  `ip` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_trace_id` (`trace_id`),
  KEY `idx_level_type_time` (`level`, `type`, `created_at`),
  KEY `idx_user_time` (`user_id`, `created_at`),
  KEY `idx_ip_time` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统日志表';

--
-- 表的结构 `api_access_logs` - API访问日志表
--
CREATE TABLE IF NOT EXISTS `api_access_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(32) NOT NULL COMMENT '追踪ID',
  `endpoint` varchar(100) NOT NULL COMMENT '接口端点',
  `method` varchar(10) NOT NULL COMMENT '请求方法',
  `ip` varchar(45) NOT NULL COMMENT 'IP地址',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `request_data` json DEFAULT NULL COMMENT '请求数据',
  `response_code` int(11) NOT NULL COMMENT '响应状态码',
  `response_time` decimal(10,4) DEFAULT NULL COMMENT '响应时间(秒)',
  `success` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否成功',
  `error_message` text DEFAULT NULL COMMENT '错误信息',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_trace_id` (`trace_id`),
  KEY `idx_endpoint_time` (`endpoint`, `created_at`),
  KEY `idx_ip_time` (`ip`, `created_at`),
  KEY `idx_user_time` (`user_id`, `created_at`),
  KEY `idx_success_time` (`success`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API访问日志表';

-- ========================================
-- 数据库清理和维护
-- ========================================

-- 创建日志清理事件（可选，需要开启事件调度器）
-- 使用说明：执行 SET GLOBAL event_scheduler = ON; 启用事件调度器
/*
DELIMITER //
CREATE EVENT IF NOT EXISTS `cleanup_old_logs`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
  -- 清理30天前的日志
  DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
  DELETE FROM api_access_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
  DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
  DELETE FROM failed_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END//
DELIMITER ;
*/

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ========================================
-- 数据库结构说明
-- ========================================
-- 
-- 核心业务表：
-- - users: 用户基础信息表
-- - xfp_order: 订单管理表
-- - xfp_wflist: 表盘列表管理表
-- - xfp_activation_records: 激活记录表
--
-- 权限申请管理：
-- - permission_applications: 用户权限申请表
--
-- 验证码管理：
-- - verification_codes: 验证码表
--
-- 用户行为日志和设置：
-- - user_action_logs: 用户行为日志表
-- - user_settings: 用户个性化设置表
--
-- 安全管理和日志：
-- - api_rate_limits: API请求频率限制表
-- - ip_blacklist: IP黑名单表
-- - failed_attempts: 失败尝试记录表
-- - system_logs: 系统日志表
-- - api_access_logs: API访问日志表
--
-- 数据库更新说明：
-- 1. xfp_activation_records表已更新支持快应用激活功能
-- 2. 新增product_type字段区分激活类型（watchface/quickapp）
-- 3. 新增product_id字段统一存储产品标识
-- 4. watchface_id字段保留用于向下兼容
-- 5. 新增相关索引优化查询性能
--
-- 总计：11个数据表，涵盖完整的业务功能、用户管理和安全管理需求
-- 支持表盘和快应用双重激活功能
-- ========================================