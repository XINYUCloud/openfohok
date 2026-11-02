<?php
// 旧版站点自建 OAuth 回调已停用，App 登录统一走文谶账户 OAuth
require_once __DIR__ . '/_lib.php';

http_response_code(410);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>授权入口已停用</title>
<style>
  body{font-family:system-ui,-apple-system,sans-serif;background:#fafafa;color:#171717;
       display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .card{background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:32px 28px;
        text-align:center;max-width:340px;width:88%}
  h1{font-size:16px;font-weight:600;margin:0 0 10px}
  p{font-size:13px;line-height:1.7;color:#525252;margin:0}
</style>
</head>
<body>
  <div class="card">
    <h1>授权入口已停用</h1>
    <p>旧的 OAuth 授权回调不再提供服务。<br>请在 App 中重新发起登录。</p>
  </div>
</body>
</html>
