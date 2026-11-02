<?php
// ============================================================
// 环境变量加载器
//
// 读取项目根目录 .env（若存在），已存在的环境变量优先。
// 仓库内不得提交 .env、真实密钥或生产数据。
// ============================================================

if (!function_exists('fohokLoadEnvFile')) {
    function fohokLoadEnvFile(string $path): void {
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            if ($key === '' || getenv($key) !== false) {
                continue;
            }
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = substr($value, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

if (!function_exists('fohokEnv')) {
    /** 读取环境变量，未设置或为空时返回默认值 */
    function fohokEnv(string $key, string $default = ''): string {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return (string)$value;
    }
}

fohokLoadEnvFile(__DIR__ . '/../.env');
