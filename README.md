# XFP 密钥获取系统（XFP Activation Key System）

面向智能手表表盘与快应用等场景的**密钥查询、激活与订单管理**后台：用户中心、爱发电订单同步、权限申请、邮件验证码登录，以及面向查询接口的**频率限制、黑名单与审计日志**等安全能力。

**English:** A PHP/MySQL web application for activation key distribution and order management (watch faces, quick apps, etc.), with optional Afdian integration, user dashboard, and API security tooling (rate limits, IP controls, logs).

## 功能概览

- 前台：密钥查询、用户中心、激活记录、权限申请、邮箱登录与注册等页面（`index.php`、`pages/`）。
- 后台：`admin/` 用户与站长端管理；安全统计与日志见 [README_SECURITY.md](README_SECURITY.md)。
- 接口与任务：`app/api.php`、爱发电 Webhook、订单与快应用相关 API；定时同步见下文「定时任务」。
- 配置：根目录 `.env`（从 [`.env.example`](.env.example) 复制），无 Composer 亦可运行。

### 代码与工程说明（重要）

本仓库**演进时间较长**，目录与职责划分**并非严格分层**（页面、`app/` API、管理端、脚本混用），部分逻辑存在重复或历史包袱。**开源版本面向「可审计、可 fork、可自建」**，不保证**不经整理即可直接用于严肃生产上线**。

- **部署前**：建议你按团队规范**自行梳理路由、拆分公共逻辑、收紧权限与密钥管理**，并替换各处的演示/占位行为（见下节「解锁密码」与 [docs/KEY_DERIVATION.md](docs/KEY_DERIVATION.md) 中的遗留说明）。
- **社区**：欢迎下载、修复问题、重构为更清晰的模块划分，并通过 Issue / Pull Request 分享更完善的版本与设计（参与方式见 [CONTRIBUTING.md](CONTRIBUTING.md)）。

## 环境要求

- PHP 7.4+（推荐 8.0+），扩展：`pdo_mysql`、`mysqli`、`json`、`session` 等常用扩展
- MySQL 5.7+ / MariaDB 10.x
- Web 服务器（Apache / Nginx / IIS 等），**文档根目录**指向本项目根目录（克隆后目录名可任意，不必使用 `127.0.0.1` 等）

## 快速开始

1. **获取代码**  
   克隆或解压本仓库到服务器可访问路径。

2. **环境变量**  
   ```bash
   cp .env.example .env
   ```  
   编辑 `.env`：**必填** `DB_HOST`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`。  
   按需填写邮件与定时任务：`SMTP_*`、`MAIL_FROM_*`、`CRON_SECRET`。  
   字段说明见 [`.env.example`](.env.example)。

3. **数据库**  
   导入 [`database_files/complete_database.sql`](database_files/complete_database.sql) 作为完整表结构（无业务示例数据）。  
   其他增量脚本见 [`database_files/README.md`](database_files/README.md)。

4. **日志目录**  
   确保 `logs/` 对 Web 运行用户可写（可手工创建；详见 [README_SECURITY.md](README_SECURITY.md)）。

5. **定时任务（可选）**  
   爱发电订单批量同步，例如：  
   `https://你的域名/app/afd_orderlist_update.php?secret=<CRON_SECRET>`  
   或 CLI：  
   `php app/afd_orderlist_update.php <CRON_SECRET>`

## 文档索引

| 文档 | 说明 |
|------|------|
| [README_SECURITY.md](README_SECURITY.md) | API 安全、频率限制、黑名单、日志与后台安全面板 |
| [database_files/README.md](database_files/README.md) | 数据库脚本与表说明 |
| [邮件系统使用说明.md](邮件系统使用说明.md) | 邮件发信与验证码流程 |
| [CONTRIBUTING.md](CONTRIBUTING.md) | 参与贡献与 AGPL 说明 |
| [SECURITY.md](SECURITY.md) | 漏洞报告方式 |
| [docs/KEY_DERIVATION.md](docs/KEY_DERIVATION.md) | **解锁密码/密钥派生**设计说明与开源骨架约定 |

## 解锁密码（密钥）派生 — 必读

激活流程依赖「设备码 + 产品 ID → 解锁字符串」，且须与你在**手表端 / 表盘或快应用包内**的约定一致。

- **设计文档**（含历史参考算法说明，**仅作文档、不在 PHP 中实现**）：[docs/KEY_DERIVATION.md](docs/KEY_DERIVATION.md)
- **唯一实现入口**：[app/UnlockKeyDerivation.php](app/UnlockKeyDerivation.php) 中的 `UnlockKeyDerivation::derive()`  
  **默认骨架会抛出异常**，不包含可开箱即用的生产算法；你必须在该方法内**自行实现**自有协议（哈希、HMAC、或与设备端一致的规则）。未实现前，激活相关接口会返回错误，**无法生成真实可用解锁密码**。
- 若在派生失败前已扣减订单次数，`app/api.php` 等路径会尝试**回滚下载次数**；仍建议你在联调完成后再开放生产流量。

## 开发与测试

- 产品类型识别测试：`php dev/tests/test_product_type.php`（数据：`dev/tests/test_order_data.json`）。
- 调试：在 `.env` 中设置 `APP_DEBUG=true`，数据库连接错误会写入 PHP 错误日志；**生产环境请关闭**。

## 第三方组件

- **[PHPMailer](app/PHPMailer/)** 以源码形式随仓库分发，许可为 **LGPL-2.1**，见 `app/PHPMailer/LICENSE`。与本项目整体授权独立，使用时请遵守其条款。

## 安全说明

若仓库或 `.env` 曾在不安全环境暴露，请**轮换**数据库密码、SMTP 授权码、`CRON_SECRET` 及爱发电相关凭证。漏洞披露见 [SECURITY.md](SECURITY.md)。

---

## 版权保留与作者

| 项目 | 说明 |
|------|------|
| **英文名称** | **XFP Activation Key System** |
| **开发者** | [YcFeller](https://github.com/YcFeller) |
| **作者主页** | https://github.com/YcFeller |

**版权保留：** 本软件及相关文档的著作权由 **YcFeller** 保留（`Copyright © 2026 YcFeller`）。在**不违反**本仓库所附 [GNU Affero General Public License v3.0](LICENSE) 的前提下，你可以行使该许可证授予的使用、修改与再分发等权利；**不得**擅自移除或篡改著作权声明、许可证全文及本项目中已标注的作者信息。第三方组件（如 PHPMailer）仍适用其各自许可证。

---

## 许可与再分发（AGPL-3.0）

本项目以 **[GNU Affero General Public License v3.0](LICENSE)**（AGPL-3.0）发布，[NOTICE](NOTICE) 中有简要版权说明。

**你可以：** 使用、研究、修改并以 AGPL-3.0 要求的方式再发布本软件（包括收费提供服务——但须满足下面「须履行」的义务）。

**我们希望你避免的：** 在**不公开对应源码**的前提下，将本项目的修改版作为**闭源商业产品**再分发，或通过公网向用户提供修改版服务而不提供源码。AGPL 正是为网络服务场景下的「源码回馈」而设计。

**你通常需要做的（非法律意见，详情请阅读 LICENSE 全文）：**

- 若**再发布**本软件或其修改版（例如分发副本、镜像），须以 **AGPL-3.0** 授权，并向接收者提供**对应版本的完整源码**（含你的修改）。
- 若通过**网络**向用户提供你修改后的版本（例如 SaaS、托管站），AGPL 第 13 条要求你向用户提供**对应修改版的源码**（具体条件与例外以许可证英文原文为准）。
- **不得**移除版权声明与许可证文本；若包含交互式界面，需保留适当的法律声明（见许可证第 7 节等）。

若你需要将本代码与闭源组件结合、或无法遵守 AGPL 义务，请勿使用本仓库代码，或另行联系作者 [YcFeller](https://github.com/YcFeller) 商议**单独商业授权**（本 README 不构成法律承诺，以实际书面协议为准）。

**Why AGPL?** This is a network-facing application. AGPL extends copyleft to many public-network use cases so that improvements to deployed versions can remain available to the community, not only when binaries are distributed.

---

## 相关链接

- 作者主页：https://github.com/YcFeller  
- AGPL-3.0 全文：[LICENSE](LICENSE)  
- 如何参与：[CONTRIBUTING.md](CONTRIBUTING.md)
