<?php
// 站点自建 OAuth 授权服务已下线，应用统一接入文谶账户
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'ok'   => false,
    'msg'  => '站点自建 OAuth 服务已下线，请使用文谶账户 OAuth',
    'docs' => 'https://account.fohok.xin/doc/oauth-docs.php',
], JSON_UNESCAPED_UNICODE);
exit;
