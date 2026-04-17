<?php
/**
 * 解锁密码 / 密钥派生 — 开源骨架入口
 *
 * 本文件不包含可开箱即用的生产算法。请根据你与设备端、表盘/快应用包体的协议，
 * 在 derive() 内自行实现派生逻辑。
 *
 * 历史参考设计（仅供阅读，代码中已移除）见：docs/KEY_DERIVATION.md
 *
 * XFP Activation Key System / XFP 密钥获取系统
 *
 * @author    YcFeller
 * @copyright Copyright (c) 2026 YcFeller
 * @license   AGPL-3.0-or-later
 * @link      https://github.com/YcFeller
 */

declare(strict_types=1);

final class UnlockKeyDerivation
{
    /**
     * 计算单条产品的解锁密码。
     *
     * @param string $deviceCode 设备码（PSN 等）
     * @param string $productId  表盘 ID 或快应用 ID（与订单侧查询结果一致）
     * @param string $productKind  'watchface' | 'quickapp'（便于你区分协议；历史实现曾混用同一模板）
     * @return string 展示给用户/写入激活记录的解锁字符串
     */
    public static function derive(string $deviceCode, string $productId, string $productKind = 'watchface'): string
    {
        // --- 在此实现你的派生协议（示例：哈希、HMAC、查表、远程校验等）---
        // 实现完成后删除下方异常。

        throw new RuntimeException(
            '解锁密钥未实现：请在 app/UnlockKeyDerivation.php 中实现 UnlockKeyDerivation::derive()，'
            . '并阅读 docs/KEY_DERIVATION.md。默认骨架无法直接用于生产。'
        );
    }
}
