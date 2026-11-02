<?php
// 已登录用户补授权：申请 notification:read，回调后不重建会话
require_once __DIR__ . '/bootstrap.php';

$user = requireLogin();
wenchanNotificationsConfig();

if (!wenchanReadEnabled()) {
    header('Location: /messages.php?wenchan=not_enabled');
    exit;
}

$userId = (int)($user['user_id'] ?? $user['id']);

$next = '/messages.php';
$to   = (string)($_GET['next'] ?? '');
if ($to !== '' && $to[0] === '/' && !(isset($to[1]) && $to[1] === '/') && strpos($to, '\\') === false) {
    $next = $to;
}

wenchanOauthStart($next, 'notification:read', $userId);
