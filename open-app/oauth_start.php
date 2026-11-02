<?php
// ============================================================
// App 内嵌 Webview 登录入口
// 调用：open-app/oauth_start.php?ticket=<32位票据>
// App 先调 index.php?action=oauth_ticket_create 拿到 ticket，
// 再在 <web-view> 中打开本地址。
//
// 统一走文谶账户 OAuth：wenchanOauthStart → 账号中心授权
// → 站点 callback.php 建立会话 → oauth_landing.php 写入 AppToken
// → App 轮询 oauth_ticket_poll 取回 token 完成登录。
//
// 票据会绑定到当前浏览器会话，落地页只承认本会话发起过的票据，
// 防止他人把有效票据链接发给已登录用户来窃取 AppToken。
// ============================================================

require_once __DIR__ . '/_lib.php';

startSecureSession();

$ticket  = (string)($_GET['ticket'] ?? '');
$landing = '/open-app/oauth_landing.php';
if (preg_match('/^[a-f0-9]{32}$/', $ticket)) {
    $_SESSION['openapp_pending_ticket'] = $ticket;
    $landing .= '?ticket=' . $ticket;
}

wenchanOauthStart($landing);
