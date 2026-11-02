<?php
/**
 * 文谶驿站 · RSS 模块全局配置
 * 密钥通过环境变量或项目根目录 .env 提供，请勿写入仓库。
 */

require_once __DIR__ . '/../config/env.php';

// ------------------------- Resend 邮件服务 -------------------------
define('RESEND_API_KEY',    fohokEnv('RESEND_API_KEY'));
define('RESEND_FROM_EMAIL', fohokEnv('RESEND_FROM_EMAIL', 'open-fohok@xgorb.cn'));
define('RESEND_FROM_NAME',  fohokEnv('RESEND_FROM_NAME',  '文谶驿站'));
define('RESEND_API_URL',    fohokEnv('RESEND_API_URL',    'https://api.resend.com/emails'));

// ------------------------- 网页截图系统 -------------------------
define('SCREENSHOT_API_BASE', fohokEnv('SCREENSHOT_API_BASE', 'https://fohok.xin/webscr/api'));
define('SCREENSHOT_API_KEY',  fohokEnv('SCREENSHOT_API_KEY'));

// ------------------------- 站点信息 -------------------------
define('SITE_BASE_URL', rtrim(fohokEnv('RSS_SITE_BASE_URL', 'https://fohok.xin/rss'), '/'));

// ------------------------- 日志 -------------------------
define('LOG_DIR', __DIR__ . '/data/logs');
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

/**
 * 简单的文件日志函数，供各脚本统一调用。
 */
function app_log($channel, $message) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents(LOG_DIR . '/' . $channel . '.log', $line, FILE_APPEND | LOCK_EX);
}
