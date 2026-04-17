# 参与贡献

感谢你愿意改进 **XFP Activation Key System（XFP 密钥获取系统）**。

**著作权：** 原始代码与文档的版权由 [YcFeller](https://github.com/YcFeller) 保留（Copyright © 2026 YcFeller）。贡献时请勿删除现有文件头中的 `@author` / `@copyright` 信息；新增文件建议沿用 [README.md](README.md) 中「版权保留与作者」所述风格。

## 基本要求

- 向本仓库提交的改动，在合并后将以 **[GNU AGPL-3.0](LICENSE)** 与现有代码同样方式授权，请确认你同意这一点。
- **解锁密码派生**的默认实现在 [`app/UnlockKeyDerivation.php`](app/UnlockKeyDerivation.php) 为**骨架**；PR 若包含具体生产算法，请确认你不介意公开，且不与你的商用设备端协议冲突。一般建议仅改进结构、文档或示例，具体协议仍由部署者本地实现。
- 请勿在代码、配置示例或 Issue 中粘贴真实数据库密码、SMTP 授权码、`CRON_SECRET`、爱发电 Token 等敏感信息。
- 较大功能或行为变更，建议先在 Issue 中简要说明意图，便于对齐方向。

## 建议流程

1. Fork 本仓库并创建分支。
2. 本地修改并自测（部署相关步骤见 [README.md](README.md)）。
3. 发起 Pull Request，说明变更内容与动机。

## 安全漏洞

请勿在公开 Issue 中讨论可利用细节；请按 [SECURITY.md](SECURITY.md) 联系维护者。
