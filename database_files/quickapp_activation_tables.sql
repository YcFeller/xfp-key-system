-- 快应用激活系统数据库表结构
-- 生成时间: 2025-01-09
-- 用途: 为系统添加快应用激活功能，与表盘激活并行运行
-- 设计原则: 复用现有逻辑，通过激活类型区分表盘和快应用

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ========================================
-- 快应用激活相关表结构
-- ========================================

--
-- 表的结构 `xfp_quickapp_list` - 快应用列表（对应表盘列表）
--
CREATE TABLE IF NOT EXISTS `xfp_quickapp_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '快应用名称',
  `quickapp_id` char(12) DEFAULT NULL COMMENT '快应用ID（对应表盘ID）',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态：1=启用，0=禁用',
  `upload_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  `downloads_limit` int(11) DEFAULT '1' COMMENT '下载限制次数',
  `plan_id` varchar(50) DEFAULT NULL COMMENT '计划ID（关联订单）',
  `user_id` int(11) NOT NULL COMMENT '上传用户ID',
  `image_link` varchar(255) DEFAULT NULL COMMENT '快应用图标链接',
  `package_name` varchar(255) DEFAULT NULL COMMENT '快应用包名',
  `version` varchar(50) DEFAULT NULL COMMENT '快应用版本',
  `description` text DEFAULT NULL COMMENT '快应用描述',
  PRIMARY KEY (`id`),
  KEY `idx_quickapp_id` (`quickapp_id`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='快应用列表管理表';

--
-- 修改现有激活记录表，添加激活类型字段
-- 注意：这是对现有表的修改，需要谨慎执行
--
ALTER TABLE `xfp_activation_records` 
ADD COLUMN `activation_type` enum('watchface','quickapp') NOT NULL DEFAULT 'watchface' COMMENT '激活类型：watchface=表盘，quickapp=快应用' AFTER `watchface_id`,
ADD COLUMN `quickapp_id` char(12) DEFAULT NULL COMMENT '快应用ID（当activation_type=quickapp时使用）' AFTER `activation_type`,
ADD INDEX `idx_activation_type` (`activation_type`),
ADD INDEX `idx_quickapp_id` (`quickapp_id`);

-- 为了保持向后兼容，更新现有记录的激活类型
UPDATE `xfp_activation_records` SET `activation_type` = 'watchface' WHERE `activation_type` IS NULL OR `activation_type` = '';

--
-- 修改订单表，添加产品类型字段
-- 注意：这是对现有表的修改，需要谨慎执行
--
ALTER TABLE `xfp_order` 
ADD COLUMN `product_type` enum('watchface','quickapp','mixed') NOT NULL DEFAULT 'watchface' COMMENT '产品类型：watchface=表盘，quickapp=快应用，mixed=混合' AFTER `product_name`,
ADD INDEX `idx_product_type` (`product_type`);

-- 为了保持向后兼容，更新现有订单的产品类型
UPDATE `xfp_order` SET `product_type` = 'watchface' WHERE `product_type` IS NULL OR `product_type` = '';

-- ========================================
-- 创建视图以便统一查询
-- ========================================

--
-- 创建统一产品视图（表盘+快应用）
--
CREATE OR REPLACE VIEW `v_unified_products` AS
SELECT 
    'watchface' as product_type,
    id,
    name,
    watchface_id as product_id,
    status,
    upload_time,
    downloads_limit,
    plan_id,
    user_id,
    image_link,
    NULL as package_name,
    NULL as version,
    NULL as description
FROM xfp_wflist
UNION ALL
SELECT 
    'quickapp' as product_type,
    id,
    name,
    quickapp_id as product_id,
    status,
    upload_time,
    downloads_limit,
    plan_id,
    user_id,
    image_link,
    package_name,
    version,
    description
FROM xfp_quickapp_list;

--
-- 创建统一激活记录视图
--
CREATE OR REPLACE VIEW `v_unified_activation_records` AS
SELECT 
    id,
    order_number,
    activation_type,
    CASE 
        WHEN activation_type = 'watchface' THEN watchface_id
        WHEN activation_type = 'quickapp' THEN quickapp_id
        ELSE NULL
    END as product_id,
    user_id,
    device_code,
    unlock_pwd,
    activation_time
FROM xfp_activation_records;

-- ========================================
-- 插入示例数据（可选）
-- ========================================

-- 插入示例快应用数据
INSERT INTO `xfp_quickapp_list` (`name`, `quickapp_id`, `status`, `plan_id`, `user_id`, `package_name`, `version`, `description`) VALUES
('示例快应用1', '123456789012', 1, 'quickapp_plan_001', 1, 'com.example.quickapp1', '1.0.0', '这是一个示例快应用'),
('示例快应用2', '123456789013', 1, 'quickapp_plan_002', 1, 'com.example.quickapp2', '1.1.0', '这是另一个示例快应用');

-- ========================================
-- 存储过程和函数
-- ========================================

--
-- 创建获取产品信息的存储过程
--
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `GetProductsByPlanId`(
    IN p_plan_id VARCHAR(50),
    IN p_product_type ENUM('watchface', 'quickapp', 'all')
)
BEGIN
    IF p_product_type = 'watchface' THEN
        SELECT 'watchface' as product_type, watchface_id as product_id, name 
        FROM xfp_wflist 
        WHERE plan_id = p_plan_id AND status = 1;
    ELSEIF p_product_type = 'quickapp' THEN
        SELECT 'quickapp' as product_type, quickapp_id as product_id, name 
        FROM xfp_quickapp_list 
        WHERE plan_id = p_plan_id AND status = 1;
    ELSE
        SELECT * FROM v_unified_products 
        WHERE plan_id = p_plan_id AND status = 1;
    END IF;
END//
DELIMITER ;

--
-- 创建激活统计函数
--
DELIMITER //
CREATE FUNCTION IF NOT EXISTS `GetActivationCount`(
    p_user_id INT,
    p_activation_type ENUM('watchface', 'quickapp', 'all'),
    p_date_range ENUM('today', 'week', 'month', 'all')
) RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE result_count INT DEFAULT 0;
    DECLARE date_condition VARCHAR(100) DEFAULT '';
    
    -- 设置日期条件
    CASE p_date_range
        WHEN 'today' THEN SET date_condition = 'AND DATE(activation_time) = CURDATE()';
        WHEN 'week' THEN SET date_condition = 'AND activation_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        WHEN 'month' THEN SET date_condition = 'AND activation_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        ELSE SET date_condition = '';
    END CASE;
    
    -- 构建查询
    IF p_activation_type = 'all' THEN
        SET @sql = CONCAT('SELECT COUNT(*) FROM xfp_activation_records WHERE user_id = ', p_user_id, ' ', date_condition);
    ELSE
        SET @sql = CONCAT('SELECT COUNT(*) FROM xfp_activation_records WHERE user_id = ', p_user_id, ' AND activation_type = "', p_activation_type, '" ', date_condition);
    END IF;
    
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
    RETURN result_count;
END//
DELIMITER ;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ========================================
-- 数据库结构说明
-- ========================================
-- 
-- 新增表：
-- - xfp_quickapp_list: 快应用列表管理表，结构类似xfp_wflist
--
-- 修改表：
-- - xfp_activation_records: 添加activation_type和quickapp_id字段
-- - xfp_order: 添加product_type字段
--
-- 新增视图：
-- - v_unified_products: 统一产品视图（表盘+快应用）
-- - v_unified_activation_records: 统一激活记录视图
--
-- 新增存储过程：
-- - GetProductsByPlanId: 根据计划ID和产品类型获取产品信息
--
-- 新增函数：
-- - GetActivationCount: 获取激活统计数据
--
-- 设计特点：
-- 1. 保持向后兼容性，现有表盘功能不受影响
-- 2. 通过枚举类型区分表盘和快应用
-- 3. 复用现有的订单和激活逻辑
-- 4. 提供统一视图便于查询和管理
-- 5. 支持混合订单（同时包含表盘和快应用）
-- ========================================