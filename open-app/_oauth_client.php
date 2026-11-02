<?php
// ============================================================
// 文谶驿站 App · OAuth 客户端配置占位
//
// 站点自建 OAuth 服务（fohok.xin/oauth/）已下线，App 登录统一使用
// 文谶账户 OAuth（account.fohok.xin），客户端配置见 config/config.php：
//   WENCHAN_OAUTH_APP_ID / WENCHAN_OAUTH_APP_SECRET
//
// 本文件仅保留常量定义，供 _lib.php 兼容加载；两项均为空表示
// 未启用旧的自建 OAuth 客户端逻辑。
// 文档：https://account.fohok.xin/doc/oauth-docs.php
// ============================================================

if (!defined('OPENAPP_OAUTH_APP_ID')) {
    define('OPENAPP_OAUTH_APP_ID', '');
}
if (!defined('OPENAPP_OAUTH_APP_SECRET')) {
    define('OPENAPP_OAUTH_APP_SECRET', '');
}
