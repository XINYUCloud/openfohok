<?php
// 邮箱验证码登录已下线，统一使用文谶账户 OAuth：/api/auth.php?action=start
require_once __DIR__ . '/../bootstrap.php';

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'ok'      => false,
    'msg'     => '邮箱登录已下线，请使用文谶账户登录',
    'login_url' => '/login.php',
], JSON_UNESCAPED_UNICODE);
