<?php
// ============================================================
// 文谶驿站开发者开放仓库 - 配置文件
//
// 所有密钥与部署相关配置均通过环境变量（或项目根目录 .env）提供。
// 请勿把真实密钥、数据库密码、邮箱地址等隐私数据提交到仓库。
// 可参考根目录 .env.example。
// ============================================================

require_once __DIR__ . '/env.php';

// 数据库配置
define('DB_HOST', fohokEnv('DB_HOST', 'localhost'));
define('DB_NAME', fohokEnv('DB_NAME', 'open-user'));
define('DB_USER', fohokEnv('DB_USER', 'open-user'));
define('DB_PASS', fohokEnv('DB_PASS'));
define('DB_CHARSET', fohokEnv('DB_CHARSET', 'utf8mb4'));

// 应用配置
define('APP_NAME', fohokEnv('APP_NAME', '文谶驿站 - 开发者开放仓库'));
define('APP_URL', rtrim(fohokEnv('APP_URL', 'https://fohok.xin'), '/'));
define('APP_CALLBACK', APP_URL . '/callback.php');

// 会话有效期：12天
define('SESSION_LIFETIME', (int)fohokEnv('SESSION_LIFETIME', '1080000'));

// 文谶账户 OAuth 配置（account.fohok.xin 账号中心）
// 回调地址必须与账号中心为该应用登记的“授权回调链接”完全一致
define('WENCHAN_OAUTH_APP_ID', fohokEnv('WENCHAN_OAUTH_APP_ID'));
define('WENCHAN_OAUTH_APP_SECRET', fohokEnv('WENCHAN_OAUTH_APP_SECRET'));
define('WENCHAN_OAUTH_AUTH_URL', fohokEnv('WENCHAN_OAUTH_AUTH_URL', 'https://account.fohok.xin/oauth/authorize.php'));
define('WENCHAN_OAUTH_VERIFY_URL', fohokEnv('WENCHAN_OAUTH_VERIFY_URL', 'https://account.fohok.xin/oauth/verify.php'));
define('WENCHAN_OAUTH_USERINFO_URL', fohokEnv('WENCHAN_OAUTH_USERINFO_URL', 'https://account.fohok.xin/oauth/userinfo.php'));
define('WENCHAN_OAUTH_CALLBACK', APP_URL . '/callback.php');
// 站内信通知通道（通知中心每用户 OAuth 授权后，向账号中心推送站内信）
define('WENCHAN_OAUTH_NOTIFY_URL', fohokEnv('WENCHAN_OAUTH_NOTIFY_URL', 'https://account.fohok.xin/oauth/notify.php'));
define('WENCHAN_OAUTH_REFRESH_URL', fohokEnv('WENCHAN_OAUTH_REFRESH_URL', 'https://account.fohok.xin/oauth/refresh.php'));
// 请求权限：notification:write 用于站内信转发到账号中心
define('WENCHAN_OAUTH_SCOPE', fohokEnv('WENCHAN_OAUTH_SCOPE', 'openid profile email notification:write'));

// 极验配置
define('CAPTCHA_ID', fohokEnv('CAPTCHA_ID'));
define('CAPTCHA_KEY', fohokEnv('CAPTCHA_KEY'));
define('CAPTCHA_URL', fohokEnv('CAPTCHA_URL', 'https://gcaptcha4.geetest.com/validate'));

// 邮件发信配置 (people-mail API)
define('MAIL_API_URL', fohokEnv('MAIL_API_URL', 'https://people-mail.fohok.xin/'));
define('MAIL_AUTH_CODE', fohokEnv('MAIL_AUTH_CODE'));
define('MAIL_FROM_EMAIL', fohokEnv('MAIL_FROM_EMAIL', 'open@fohok.xin'));
define('MAIL_FROM_NAME', fohokEnv('MAIL_FROM_NAME', '文谶驿站 - 开发者开放仓库'));
define('MAIL_SMTP_HOST', fohokEnv('MAIL_SMTP_HOST', 'smtp.qiye.aliyun.com'));
define('MAIL_SMTP_PORT', (int)fohokEnv('MAIL_SMTP_PORT', '465'));
define('MAIL_SMTP_SECURE', fohokEnv('MAIL_SMTP_SECURE', 'ssl'));
define('MAIL_SMTP_USERNAME', fohokEnv('MAIL_SMTP_USERNAME', 'open@fohok.xin'));
define('ADMIN_EMAILS', array_values(array_filter(array_map('trim', explode(',', fohokEnv('ADMIN_EMAILS'))))));

// 网页截图 API 配置
define('WEBSCR_API_URL', fohokEnv('WEBSCR_API_URL', 'https://work.fohok.xin/webscr/api'));
define('WEBSCR_API_KEY', fohokEnv('WEBSCR_API_KEY'));

// 时区
date_default_timezone_set(fohokEnv('APP_TIMEZONE', 'Asia/Shanghai'));
