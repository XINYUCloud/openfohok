<?php
// ============================================================
// App Webview OAuth 落地页（授权流程终点）
// 三种到达方式：
//   1. 标准流程：oauth_callback.php 校验成功后 302 到本页，
//      AppToken 已写入票据 → 本页读出结果交回 App
//   2. 站点会话通道：站点 callback.php 建立会话后跳转本页，
//      本页读取会话用户签发 AppToken 写入票据
//   3. 用户拒绝授权：oauth_callback.php / callback.php 带 denied 标记跳转
//
// 交回 App 的两条通道：
//   ① 票据轮询（主通道，最可靠）：App 轮询 oauth_ticket_poll 取回 token
//   ② uni.webview JS SDK postMessage（加速通道）
// ============================================================

require_once __DIR__ . '/_lib.php';

// 通过同一域名的 Cookie Session 还原用户（页面与 open-app 同源）
$user = getCurrentUser();

header('Content-Type: text/html; charset=utf-8');

$ticket      = $_GET['ticket'] ?? '';
$ticketValid = preg_match('/^[a-f0-9]{32}$/', $ticket) === 1;
$denied      = ($_GET['denied'] ?? '') === '1';

// ── 用户在授权页点了「拒绝」 ─────────────────────────────────
if ($denied) {
    $payload = json_encode(['type' => 'app_oauth', 'denied' => true], JSON_UNESCAPED_UNICODE);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>已取消授权</title><style>body{font-family:system-ui,sans-serif;background:#fafafa;color:#171717;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
.card{background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:28px;text-align:center;max-width:320px}
p{margin:0 0 14px;font-size:14px;color:#525252}</style></head><body>
<div class="card"><h3>已取消授权</h3><p>你已拒绝授权，请返回 App 重新尝试。</p></div>
<script src="https://js.cdn.dcloud.net.cn/dev/uni-app/uni.webview.1.5.6.js"></script>
<script>(function(){function post(){try{if(window.uni&&window.uni.webView&&typeof window.uni.webView.postMessage==="function"){window.uni.webView.postMessage({data:' . json_encode($payload) . '});}}catch(e){}}
if(window.uni&&window.uni.webView){post();}else{document.addEventListener("UniAppJSBridgeReady",post,false);setTimeout(post,1200);}})();
</script></body></html>';
    exit;
}

// ── 主通道：写入票据，等待 App 轮询取走 ────────────────────────
$mintedToken = null;
$payloadUser = null;

// 情况 1：标准流程——oauth_callback.php 已完成校验并写入票据
if ($ticketValid) {
    $res = getOauthTicketResult($ticket);
    if ($res) {
        $mintedToken = $res['token'];
        $payloadUser = $res['user'];
    }
}

// 情况 2：站点会话通道——本会话确实发起过该票据时才补签发
if (!$mintedToken && $user && $ticketValid
    && hash_equals((string)($_SESSION['openapp_pending_ticket'] ?? ''), $ticket)) {
    unset($_SESSION['openapp_pending_ticket']);
    try {
        $mintedToken = completeOauthTicket($ticket, $user);
        if ($mintedToken) $payloadUser = $user;
    } catch (\Throwable $e) { $mintedToken = null; }
}

// 两条通道都没拿到：提示失败
if (!$mintedToken) {
    $retry = '/open-app/oauth_start.php' . ($ticketValid ? '?ticket=' . $ticket : '');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>登录未成功</title><style>body{font-family:system-ui,sans-serif;background:#fafafa;color:#171717;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
.card{background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:28px;text-align:center;max-width:320px}
a{color:#2563eb;text-decoration:none}</style></head><body>
<div class="card"><h3>登录未成功</h3><p>请返回 App 重新尝试。</p><a href="' . htmlspecialchars($retry) . '">重新授权</a></div>
</body></html>';
    exit;
}

$displayUser = $payloadUser ?: ($user ?: []);
$name = htmlspecialchars($displayUser['name'] ?? '用户', ENT_QUOTES, 'UTF-8');
$payload = json_encode([
    'type'  => 'app_oauth',
    'token' => $mintedToken,
    'user'  => [
        'id'      => (int)($displayUser['id'] ?? $displayUser['user_id'] ?? 0),
        'name'    => $displayUser['name'] ?? '',
        'email'   => $displayUser['email'] ?? '',
        'picture' => $displayUser['picture'] ?? '',
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>登录成功</title>
<style>
  * { box-sizing: border-box; }
  body { margin:0; font-family:"Noto Sans SC",system-ui,sans-serif; background:#fafafa; color:#171717;
         display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .card { background:#fff; border:1px solid #e5e5e5; border-radius:10px; padding:36px 28px;
          text-align:center; max-width:320px; width:90%; box-shadow:0 1px 3px rgba(0,0,0,.06); }
  .badge { display:inline-block; background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;
           border-radius:99px; padding:3px 10px; font-size:12px; font-weight:600; letter-spacing:.06em; }
  h3 { margin:16px 0 6px; }
  p  { margin:0 0 18px; color:#737373; font-size:13px; }
  .spin { width:26px; height:26px; border:3px solid #e5e5e5; border-top-color:#171717;
          border-radius:50%; animation:r 1s linear infinite; margin:0 auto 14px; }
  @keyframes r { to { transform:rotate(360deg); } }
</style>
</head>
<body>
  <div class="card">
    <span class="badge">授权成功</span>
    <h3>你好，<?php echo $name; ?></h3>
    <div id="box">
      <div class="spin"></div>
      <p>正在为你登录 App…</p>
    </div>
    <div id="done" style="display:none">
      <p>登录成功，请返回 App 继续操作。</p>
    </div>
  </div>
<script src="https://js.cdn.dcloud.net.cn/dev/uni-app/uni.webview.1.5.6.js"></script>
<script>
/* 加速通道：引入 uni.webview SDK 后通知 App；票据轮询为主通道（更可靠） */
(function () {
  function post() {
    try {
      if (window.uni && window.uni.webView && typeof window.uni.webView.postMessage === 'function') {
        window.uni.webView.postMessage({ data: <?php echo $payload; ?> });
      }
    } catch (e) {}
  }
  // SDK 异步加载完成后再发
  if (window.uni && window.uni.webView) { post(); }
  else { document.addEventListener('UniAppJSBridgeReady', post, false); setTimeout(post, 1200); }
})();
setTimeout(function () {
  var box = document.getElementById('box'), done = document.getElementById('done');
  if (box) box.style.display = 'none';
  if (done) done.style.display = 'block';
}, 900);
</script>
</body>
</html>
<?php
exit;
