<?php
/**
 * 从项目根目录 .env 加载环境变量（无 Composer 依赖）。
 * 若已在系统/服务器中设置同名变量，不会覆盖。
 *
 * XFP Activation Key System / XFP 密钥获取系统
 *
 * @author    YcFeller
 * @copyright Copyright (c) 2026 YcFeller
 * @license   AGPL-3.0-or-later
 * @link      https://github.com/YcFeller
 */
if (defined('XFP_ENV_LOADED')) {
    return;
}
define('XFP_ENV_LOADED', true);

$__xfp_env_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_readable($__xfp_env_path)) {
    foreach (file($__xfp_env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $len = strlen($value);
        if ($len >= 2) {
            $q = $value[0];
            if (($q === '"' || $q === "'") && $value[$len - 1] === $q) {
                $value = substr($value, 1, -1);
            }
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

if (!function_exists('env')) {
    /**
     * @param mixed $default
     * @return mixed
     */
    function env($key, $default = null)
    {
        $v = getenv($key);
        return $v !== false ? $v : $default;
    }
}
