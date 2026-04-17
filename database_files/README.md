# 数据库文件专用文件夹

## 文件夹说明

本文件夹专门用于存放完整的数据库结构文件和相关的数据库管理文件。

## 文件列表

### complete_database.sql

**完整数据库结构文件**

- **生成时间**: 2025-01-09 (最后更新)
- **数据库**: xfp_fs0_xr
- **版本**: v2.0 (已完善)

#### 包含的表结构 (共11个表):

**核心业务表：**
- `users` - 用户基础信息表
- `xfp_order` - 订单管理表
- `xfp_wflist` - 表盘列表管理表
- `xfp_activation_records` - 激活记录表

**权限申请管理：**
- `permission_applications` - 用户权限申请表

**验证码管理：**
- `verification_codes` - 验证码表

**用户行为日志和设置：**
- `user_action_logs` - 用户行为日志表
- `user_settings` - 用户个性化设置表

**安全管理和日志：**
- `api_rate_limits` - API请求频率限制表
- `ip_blacklist` - IP黑名单表
- `failed_attempts` - 失败尝试记录表
- `system_logs` - 系统日志表
- `api_access_logs` - API访问日志表

#### 更新内容 (v2.0):

基于此前内部库结构参考，本次更新添加了以下表结构：

1. **user_action_logs 表** - 用户行为日志表
   - 记录用户的各种操作行为
   - 包含操作类型、描述、IP地址、用户代理等信息
   - 支持用户行为追踪和审计

2. **user_settings 表** - 用户个性化设置表
   - 存储用户的个性化配置
   - 包含自动激活开关、邮件通知、主题偏好、语言偏好等设置
   - 支持用户个性化体验

#### 特性：

- ✅ 完整的表结构定义
- ✅ 详细的字段注释
- ✅ 合理的索引设计
- ✅ 外键约束关系
- ✅ 字符集和引擎配置
- ✅ 自动清理机制（可选）

#### 使用说明：

1. **导入数据库**：
   ```sql
   mysql -u username -p database_name < complete_database.sql
   ```

2. **创建新数据库**：
   ```sql
   CREATE DATABASE xfp_fs0_xr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE xfp_fs0_xr;
   SOURCE complete_database.sql;
   ```

3. **更新现有数据库**：
   - 建议先备份现有数据
   - 可选择性执行新增表的CREATE语句

---

**维护说明**：
- 本文件基于生产环境的实际需求进行完善
- 包含了完整的业务功能、用户管理和安全管理需求
- 定期更新以保持与系统功能的同步