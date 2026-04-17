-- 快应用激活功能数据库更新脚本
-- 执行时间: 2025-01-09
-- 用途: 为现有系统添加快应用激活支持
-- 注意: 此脚本会修改现有表结构，请在执行前备份数据库

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ========================================
-- 步骤1: 创建快应用列表表
-- ========================================

-- 创建快应用列表表
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快应用列表管理表';

-- ========================================
-- 步骤2: 为xfp_activation_records表添加新字段
-- ========================================

-- 添加product_id字段（产品ID，用于存储表盘ID或快应用ID）
ALTER TABLE `xfp_activation_records` 
ADD COLUMN `product_id` varchar(255) DEFAULT NULL COMMENT '产品ID（表盘ID或快应用ID）' AFTER `watchface_id`;

-- 添加product_type字段（产品类型，区分表盘和快应用）
ALTER TABLE `xfp_activation_records` 
ADD COLUMN `product_type` varchar(20) NOT NULL DEFAULT 'watchface' COMMENT '产品类型：watchface-表盘，quickapp-快应用' AFTER `product_id`;

-- 修改watchface_id字段为可空（保持向下兼容）
ALTER TABLE `xfp_activation_records` 
MODIFY COLUMN `watchface_id` char(12) DEFAULT NULL COMMENT '表盘ID（兼容字段）';

-- ========================================
-- 步骤3: 添加索引优化查询性能
-- ========================================

-- 为product_type字段添加索引
ALTER TABLE `xfp_activation_records` 
ADD INDEX `idx_product_type` (`product_type`);

-- 为product_id字段添加索引
ALTER TABLE `xfp_activation_records` 
ADD INDEX `idx_product_id` (`product_id`);

-- 添加复合索引优化常用查询
ALTER TABLE `xfp_activation_records` 
ADD INDEX `idx_product_type_time` (`product_type`, `activation_time`);

ALTER TABLE `xfp_activation_records` 
ADD INDEX `idx_user_product_type` (`user_id`, `product_type`);

-- ========================================
-- 步骤4: 数据迁移（将现有数据适配新结构）
-- ========================================

-- 将现有记录的watchface_id复制到product_id字段
UPDATE `xfp_activation_records` 
SET `product_id` = `watchface_id` 
WHERE `watchface_id` IS NOT NULL AND `product_id` IS NULL;

-- 确保所有现有记录的product_type为'watchface'
UPDATE `xfp_activation_records` 
SET `product_type` = 'watchface' 
WHERE `product_type` IS NULL OR `product_type` = '';

-- ========================================
-- 步骤5: 验证数据完整性
-- ========================================

-- 检查数据迁移结果
SELECT 
    COUNT(*) as total_records,
    COUNT(CASE WHEN product_type = 'watchface' THEN 1 END) as watchface_records,
    COUNT(CASE WHEN product_type = 'quickapp' THEN 1 END) as quickapp_records,
    COUNT(CASE WHEN product_id IS NOT NULL THEN 1 END) as records_with_product_id
FROM `xfp_activation_records`;

-- 显示表结构变更结果
DESCRIBE `xfp_activation_records`;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ========================================
-- 更新完成说明
-- ========================================
-- 
-- 本次更新内容：
-- 1. 为xfp_activation_records表添加了product_id字段
-- 2. 为xfp_activation_records表添加了product_type字段
-- 3. 修改watchface_id字段为可空，保持向下兼容
-- 4. 添加了相关索引优化查询性能
-- 5. 迁移了现有数据到新结构
--
-- 新功能支持：
-- - 表盘激活（product_type = 'watchface'）
-- - 快应用激活（product_type = 'quickapp'）
-- - 统一的产品ID管理（product_id字段）
-- - 向下兼容现有表盘激活功能
--
-- 执行后请验证：
-- 1. 所有现有激活记录的product_type应为'watchface'
-- 2. 所有现有激活记录的product_id应等于watchface_id
-- 3. 新的快应用激活功能正常工作
-- ========================================