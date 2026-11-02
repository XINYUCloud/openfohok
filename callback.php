<?php
require_once __DIR__ . '/bootstrap.php';

$code  = is_string($_GET['code'] ?? null) ? $_GET['code'] : '';
$state = is_string($_GET['state'] ?? null) ? $_GET['state'] : '';
$error = is_string($_GET['error'] ?? null) ? $_GET['error'] : '';

/**
 * 出错时按流程类型回到合适页面
 */
function callbackFailure(string $state, string $code): void {
    $mode = 'login';
    $next = '/login.php';

    if ($state !== '' && preg_match('/^[a-f0-9]{64}$/', $state)) {
        try {
            $stmt = getDB()->prepare('SELECT mode, next_path FROM wenchan_oauth_flows WHERE state = ? LIMIT 1');
            $stmt->execute([$state]);
            $flow = $stmt->fetch();
            if ($flow) {
                $mode = (string)$flow['mode'];
                $next = securitySafeNextPath($flow['next_path'] ?? '', $mode === 'link' ? '/messages.php' : '/home.php');
            }
        } catch (\Throwable) {}
    }

    if ($mode === 'link') {
        $flag = $code === 'account_mismatch' ? 'account_mismatch' : ($code === 'access_denied' ? 'cancelled' : 'failed');
        header('Location: /messages.php?wenchan=' . urlencode($flag));
        exit;
    }

    // App Webview 登录被拒绝：让票据直接进入 denied，App 轮询可立即感知
    if ($code === 'access_denied' && str_starts_with($next, '/open-app/oauth_landing.php')) {
        if (preg_match('/[?&]ticket=([a-f0-9]{32})/', $next, $m)) {
            denyOauthTicket($m[1]);
        }
        $sep = str_contains($next, '?') ? '&' : '?';
        header('Location: ' . $next . $sep . 'denied=1');
        exit;
    }

    header('Location: /login.php?error=' . urlencode($code));
    exit;
}

if ($error !== '') {
    callbackFailure($state, $error);
}

$result = wenchanOauthCallback($state, $code);

if (!$result['ok']) {
    callbackFailure($state, (string)$result['code']);
}

$next = securitySafeNextPath($result['next'] ?? '', '/home.php');

if (($result['mode'] ?? 'login') === 'link') {
    startSecureSession();
    unset($_SESSION['wenchan_notif_cache']);
    $sep = str_contains($next, '?') ? '&' : '?';
    header('Location: ' . $next . $sep . 'wenchan=read_ok');
    exit;
}

$userId = (int)$result['user_id'];
try {
    createSession($userId, (bool)$result['is_new']);
} catch (\RuntimeException) {
    header('Location: /login.php?error=login_restricted');
    exit;
}

$displayName = '开发者';
$avatarUrl   = '';
try {
    $stmt = getDB()->prepare('SELECT name, picture FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row) {
        $displayName = trim((string)($row['name'] ?? '')) ?: $displayName;
        $avatarUrl   = trim((string)($row['picture'] ?? ''));
    }
} catch (\Throwable) {}

$greeting = !empty($result['is_new']) ? '欢迎，' : '欢迎回来，';
$safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$safeAvatar = htmlspecialchars(
    $avatarUrl !== '' ? $avatarUrl : 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&background=222&color=fff',
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录成功 · 文谶驿站</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  :root{
    --bg:#fafafa;--card:#fff;--text:#171717;--dim:#737373;--muted:#a3a3a3;
    --border:#e5e5e5;--ring:#d4d4d4;--dot:#16a34a;--shadow:0 4px 24px rgba(0,0,0,.08);
  }
  @media (prefers-color-scheme:dark){
    :root{--bg:#050505;--card:#0f0f0f;--text:#f5f5f5;--dim:#a3a3a3;--muted:#555;
      --border:#1a1a1a;--ring:#2a2a2a;--dot:#22c55e;--shadow:none}
  }
  body{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
    background:var(--bg);color:var(--text);font-family:'Noto Sans SC',-apple-system,BlinkMacSystemFont,sans-serif;gap:16px;padding:24px}
  .card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:48px 44px;
    text-align:center;max-width:420px;width:100%;box-shadow:var(--shadow)}
  .ring{width:72px;height:72px;border-radius:50%;border:1px solid var(--ring);display:flex;align-items:center;
    justify-content:center;margin:0 auto 24px}
  .avatar{width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--ring);display:block;margin:0 auto 20px}
  .label{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
  h1{font-size:22px;font-weight:500;margin-bottom:6px}
  .sub{font-size:13px;color:var(--dim)}
  .foot{font-size:11px;color:var(--muted);text-align:center;font-family:'JetBrains Mono',monospace}
  .foot a{color:inherit;text-decoration:none}
</style>
</head>
<body>
  <div class="card">
    <div class="ring">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
    </div>
    <img class="avatar" src="<?= $safeAvatar ?>" alt="">
    <p class="label">Authentication Successful</p>
    <h1><?= $greeting ?><span><?= $safeName ?></span></h1>
    <p class="sub">正在跳转…</p>
  </div>
  <p class="foot"><a href="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">如果没有自动跳转，请点此继续</a></p>
<script>
  var target = <?= json_encode($next, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  function go(){ location.replace(target); }
  setTimeout(go, 900);
  setTimeout(go, 2500);
</script>
</body>
</html>
