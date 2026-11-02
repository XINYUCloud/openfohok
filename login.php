<?php
require_once __DIR__ . '/bootstrap.php';

// 登录成功后的跳转目标（仅允许站内相对路径，防止开放重定向）
$to = securitySafeNextPath($_GET['to'] ?? '/home.php', '/home.php');
if ($to === '/login.php' || $to === '/callback.php') {
    $to = '/home.php';
}

// 已登录直接跳转目标页
$already = getCurrentUser();
if ($already) {
    header('Location: ' . $to);
    exit;
}

$errorCode = trim((string)($_GET['error'] ?? ''));
$errorMap = [
    'access_denied'     => '你已取消授权，未完成登录。',
    'invalid_callback'  => '登录回调参数缺失，请重新登录。',
    'invalid_state'     => '登录会话已失效，请重新登录。',
    'session_mismatch'  => '登录会话与当前浏览器不匹配，请重新登录。',
    'state_used'        => '该登录请求已被使用，请重新登录。',
    'state_expired'     => '登录请求已过期，请重新登录。',
    'exchange_failed'   => '与文谶账户通信失败，请稍后重试。',
    'userinfo_invalid'  => '未能获取文谶账户资料，请稍后重试。',
    'email_conflict'    => '该邮箱已被其他账号占用，请联系管理员处理。',
    'account_mismatch'  => '当前文谶账户与驿站账号不一致，请使用原账号登录。',
    'too_many_requests' => '操作过于频繁，请稍后再试。',
    'rate_limited'      => '操作过于频繁，请稍后再试。',
    'server_error'      => '服务器开小差了，请稍后重试。',
    'server_busy'       => '系统繁忙，请稍后重试。',
    'login_restricted'  => '该账号已被限制登录。',
    'migrated'          => '登录方式已升级，请使用文谶账户重新登录。',
];
$errorMessage = $errorMap[$errorCode] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<meta property="og:title" content="登录 · 文谶驿站">
<meta property="og:description" content="一个为独立开发者打造的开源项目展示空间。">
<meta property="og:image" content="https://fohok.xin/img/fohok-2.png">
<meta property="og:url" content="https://fohok.xin/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="文谶驿站 · 开发者开放仓库">
<title>登录 · 文谶驿站</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Noto+Sans+SC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}

  :root {
    --bg-primary: #fafafa;
    --bg-secondary: #ffffff;
    --bg-tertiary: #f5f5f5;
    --bg-hover: #f0f0f0;
    --bg-card: #ffffff;
    --text-primary: #171717;
    --text-secondary: #404040;
    --text-dim: #737373;
    --text-muted: #a3a3a3;
    --text-inverse: #fafafa;
    --border-primary: #e5e5e5;
    --border-secondary: #d4d4d4;
    --danger-color: #dc2626;
    --danger-bg: #fef2f2;
    --danger-border: #fecaca;
    --success-color: #16a34a;
    --overlay-bg: rgba(0, 0, 0, 0.5);
    --shadow-card: 0 4px 24px rgba(0,0,0,0.08);
    --scrollbar-track: #f5f5f5;
    --scrollbar-thumb: #d4d4d4;
  }

  @media (prefers-color-scheme: dark) {
    :root {
      --bg-primary: #050505;
      --bg-secondary: #0a0a0a;
      --bg-tertiary: #111111;
      --bg-hover: #1a1a1a;
      --bg-card: #0a0a0a;
      --text-primary: #f5f5f5;
      --text-secondary: #d4d4d4;
      --text-dim: #a3a3a3;
      --text-muted: #737373;
      --text-inverse: #0a0a0a;
      --border-primary: #1a1a1a;
      --border-secondary: #222222;
      --danger-color: #f87171;
      --danger-bg: #1a0808;
      --danger-border: #3f1d1d;
      --success-color: #4ade80;
      --overlay-bg: rgba(0, 0, 0, 0.92);
      --shadow-card: none;
      --scrollbar-track: #0a0a0a;
      --scrollbar-thumb: #333333;
    }
  }

  body{
    background: var(--bg-primary);
    color: var(--text-primary);
    font-family:'Noto Sans SC',sans-serif;
    min-height:100vh;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:24px;
  }

  ::-webkit-scrollbar{width:4px;height:4px}
  ::-webkit-scrollbar-track{background:var(--scrollbar-track)}
  ::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb)}

  .auth-wrap{
    display: flex;
    align-items: stretch;
    width: 100%;
    max-width: 420px;
  }

  .qr-side{ display: none; }

  @media (min-width: 860px){
    .auth-wrap{ max-width: 760px; }
    .qr-side{
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      width: 300px;
      flex-shrink: 0;
      background: var(--bg-card);
      border: 1px solid var(--border-primary);
      border-right: none;
      border-radius: 14px 0 0 14px;
      box-shadow: var(--shadow-card);
      padding: 32px 24px;
    }
    .card{
      border-radius: 0 14px 14px 0 !important;
      box-shadow: none !important;
    }
  }

  .card{
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: 14px;
    width: 100%;
    max-width: 420px;
    overflow: hidden;
    box-shadow: var(--shadow-card);
  }
  .card-head{
    padding: 32px 32px 24px;
    border-bottom: 1px solid var(--border-primary);
    text-align: center;
  }
  .card-body{ padding: 28px 32px 32px; }

  .logo{
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    color: var(--text-muted);
    letter-spacing: .15em;
    margin-bottom: 16px;
  }
  .card-title{
    font-size: 20px;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 6px;
  }
  .card-sub{ font-size: 13px; color: var(--text-dim); }

  .alert{
    display: flex;
    align-items: flex-start;
    gap: 8px;
    background: var(--danger-bg);
    border: 1px solid var(--danger-border);
    color: var(--danger-color);
    font-size: 13px;
    line-height: 1.6;
    padding: 10px 12px;
    border-radius: 8px;
    margin-bottom: 18px;
  }

  .btn{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background .2s, color .2s;
    border: none;
    font-family: inherit;
    text-decoration: none;
  }
  .btn-primary{
    background: var(--text-primary);
    color: var(--text-inverse);
  }
  .btn-primary:hover{ background: var(--text-secondary); }
  .btn-primary.is-loading{ pointer-events: none; opacity: .65; }

  .login-hint{
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.7;
    text-align: center;
    margin-top: 16px;
  }

  .text-dim{ color: var(--text-dim); }
  .text-primary{ color: var(--text-primary); }

  @keyframes spin { to { transform: rotate(360deg); } }

  .page-footer-block{
    margin-top: 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
  }
  .back-link{
    font-size: 12px;
    text-decoration: none;
    transition: color .2s;
    color: var(--text-muted);
  }
  .back-link:hover{ color: var(--text-dim); }

  footer#page-footer p{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    flex-wrap:wrap;
    font-size:11px;
    color:var(--text-muted);
  }
  footer#page-footer a{
    display:inline-flex;
    align-items:center;
    gap:4px;
    font-family:'JetBrains Mono', monospace;
    font-size:11px;
    color:var(--text-muted);
    text-decoration:none;
    letter-spacing:.02em;
  }
  footer#page-footer a:hover{ color: var(--text-dim); }

  /* ── 二维码侧栏 ── */
  .qr-side-title{
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 4px;
  }
  .qr-side-sub{
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 18px;
    text-align: center;
  }
  #qr-container{
    position: relative;
    width: 200px;
    height: 200px;
    margin-bottom: 16px;
    cursor: default;
    border-radius: 8px;
    border: 1px solid var(--border-primary);
    overflow: hidden;
  }
  #qr-container.clickable{ cursor: pointer; }
  #qr-canvas{ display: block; width: 100%; height: 100%; }
  #qr-overlay{
    position: absolute;
    inset: 0;
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--overlay-bg);
    text-align: center;
    padding: 12px;
    border-radius: 7px;
  }
  #qr-overlay .qr-overlay-checkmark{
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--success-color);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
  }
  #qr-overlay .qr-overlay-checkmark svg{ stroke: #fff; }
  #qr-overlay-icon{ font-size: 30px; margin-bottom: 8px; line-height: 1; }
  #qr-overlay-text{ font-size: 12px; color: #fff; font-weight: 500; }
  #qr-overlay-sub{ font-size: 11px; color: rgba(255,255,255,0.75); margin-top: 4px; }
  #qr-container.clickable:hover #qr-canvas{ filter: brightness(0.94); }

  .user-badge{
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-primary);
    border-radius: 10px;
    text-align: left;
  }
  .user-badge img{
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border-secondary);
    flex-shrink: 0;
  }
</style>
</head>
<body>

<div class="auth-wrap">

  <div class="qr-side" id="qr-side">
    <p class="qr-side-title">扫码登录</p>
    <p class="qr-side-sub">使用 App 扫描二维码</p>

    <div id="qr-container">
      <canvas id="qr-canvas" width="200" height="200"></canvas>

      <div id="qr-overlay">
        <div class="qr-overlay-checkmark" id="qr-overlay-check" style="display:none;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p id="qr-overlay-icon" style="display:none;"></p>
        <p id="qr-overlay-text"></p>
        <p id="qr-overlay-sub" style="display:none;"></p>
      </div>
    </div>

    <p id="qr-status-text" style="font-size:12px;color:var(--text-dim);margin-bottom:4px;text-align:center;">正在生成二维码...</p>

    <div id="qr-scanned-user" style="display:none;margin-top:12px;width:100%;">
      <div class="user-badge">
        <img id="qr-scan-avatar" src="" alt="">
        <div>
          <p style="font-size:10px;margin-bottom:2px" class="text-dim">扫描账号</p>
          <p id="qr-scan-name" style="font-size:13px;font-weight:500;" class="text-primary"></p>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <p class="logo">OPEN-FOHOK</p>
      <h1 class="card-title">欢迎登录</h1>
      <p class="card-sub">文谶驿站 · 开发者开放平台</p>
    </div>
    <div class="card-body">

      <?php if ($errorMessage): ?>
      <div class="alert" role="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?php endif; ?>

      <a id="login-btn" class="btn btn-primary" href="/api/auth.php?action=start&amp;to=<?= urlencode($to) ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          <polyline points="9 12 11 14 15 10"/>
        </svg>
        使用文谶账户登录
      </a>

      <p class="login-hint">统一账户 · 授权后自动返回当前页面</p>

    </div>
  </div>

</div>

<div class="page-footer-block">
  <a href="/home.php" class="back-link">← 返回项目广场</a>
  <footer id="page-footer">
    <p>
      <a href="https://beian.miit.gov.cn" target="_blank" rel="noopener">
        <img src="https://fohok.xin/img/icp.png" alt="ICP备案图标" style="width:20px;height:auto;">
        闽ICP备2026024151号
      </a>
      <span>|</span>
      <a href="https://beian.mps.gov.cn/#/query/webSearch?code=35058302351237" target="_blank" rel="noopener">
        <img src="https://fohok.xin/img/mps.png" alt="公安备案图标" style="width:20px;height:auto;">
        闽公网安备35058302351237号
      </a>
    </p>
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
const LOGIN_TO   = <?= json_encode($to, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const QR_EXPIRES = 120;
let qrSessionId  = null;
let qrPollTimer  = null;
let qrRemainSec  = QR_EXPIRES;
let qrCountdown  = null;
let qrScannedNotFinal = false;

document.getElementById('login-btn').addEventListener('click', function () {
  this.classList.add('is-loading');
  this.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .8s linear infinite;flex-shrink:0"><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg>正在跳转…';
});

function isDesktopQR() {
  return window.matchMedia('(min-width: 860px)').matches;
}

async function initQRCode() {
  if (!isDesktopQR()) return;

  stopQRPolling();
  stopQRCountdown();
  qrScannedNotFinal = false;
  qrSessionId = null;

  const overlay = document.getElementById('qr-overlay');
  if (overlay) overlay.style.display = 'none';
  const container = document.getElementById('qr-container');
  if (container) container.classList.remove('clickable');
  const scanned = document.getElementById('qr-scanned-user');
  if (scanned) scanned.style.display = 'none';
  const canvas = document.getElementById('qr-canvas');
  if (canvas) canvas.getContext('2d').clearRect(0, 0, 200, 200);

  setQRStatusText('正在生成二维码...');

  try {
    const fd = new FormData();
    fd.append('action', 'generate');
    const res = await fetch('/api/qrcode_login.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();

    if (!data.ok) {
      setQRStatusText(data.msg || '生成失败，点击二维码重试');
      if (container) container.classList.add('clickable');
      return;
    }

    qrSessionId = data.session_id;
    drawQRCode('qr-canvas', window.location.origin + '/qrcode/confirm.php?s=' + encodeURIComponent(qrSessionId));

    setQRStatusText('请使用手机扫描二维码');
    if (container) container.classList.add('clickable');
    startQRPolling();
    startQRCountdown();
  } catch (e) {
    setQRStatusText('网络错误，点击二维码重试');
    if (container) container.classList.add('clickable');
  }
}

function setQRStatusText(text) {
  const el = document.getElementById('qr-status-text');
  if (el) el.textContent = text;
}

function drawQRCode(canvasId, text) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || typeof qrcode !== 'function') return;
  const ctx = canvas.getContext('2d');

  const qr = qrcode(0, 'M');
  qr.addData(text);
  qr.make();

  const moduleCount = qr.getModuleCount();
  const size = 200;
  const cellSize = size / moduleCount;

  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--bg-card').trim() || '#ffffff';
  ctx.fillRect(0, 0, size, size);

  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() || '#171717';
  for (let row = 0; row < moduleCount; row++) {
    for (let col = 0; col < moduleCount; col++) {
      if (qr.isDark(row, col)) {
        const x = Math.round(col * cellSize);
        const y = Math.round(row * cellSize);
        const w = Math.max(1, Math.round((col + 1) * cellSize) - x);
        const h = Math.max(1, Math.round((row + 1) * cellSize) - y);
        ctx.fillRect(x, y, w, h);
      }
    }
  }
}

function startQRPolling() {
  stopQRPolling();
  qrPollTimer = setInterval(pollQRStatus, 2000);
}

function stopQRPolling() {
  if (qrPollTimer) { clearInterval(qrPollTimer); qrPollTimer = null; }
}

async function pollQRStatus() {
  if (!qrSessionId) return;
  const sessionId = qrSessionId;

  try {
    const fd = new FormData();
    fd.append('action', 'poll');
    fd.append('session_id', sessionId);
    const res = await fetch('/api/qrcode_login.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();

    if (sessionId !== qrSessionId) return;

    if (data.status === 'expired' || data.ok === false) {
      stopQRPolling();
      stopQRCountdown();
      qrScannedNotFinal = false;
      showQROverlay({ icon: '⏱', text: '二维码已过期', sub: '点击二维码刷新', clickable: true });
      setQRStatusText('二维码已过期');
      return;
    }

    if (data.status === 'scanned') {
      qrScannedNotFinal = true;
      showQROverlay({ check: true, text: '扫描成功', sub: '请在手机上确认登录', clickable: false });
      setQRStatusText('扫描成功，请在手机上确认登录');
      if (data.user) showScannedUser(data.user);
      return;
    }

    if (data.status === 'confirmed') {
      stopQRPolling();
      stopQRCountdown();
      qrScannedNotFinal = false;
      showQROverlay({ check: true, text: '登录成功', sub: '正在跳转...', clickable: false });
      setQRStatusText('登录成功，正在跳转...');
      setTimeout(() => { window.location.replace(LOGIN_TO); }, 600);
      return;
    }

    if (data.status === 'pending' && qrScannedNotFinal) {
      qrScannedNotFinal = false;
      hideQROverlay();
      setQRStatusText('请使用手机扫描二维码');
      const scanned = document.getElementById('qr-scanned-user');
      if (scanned) scanned.style.display = 'none';
    }
  } catch (e) {
    // 网络错误不中断轮询
  }
}

function startQRCountdown() {
  stopQRCountdown();
  qrRemainSec = QR_EXPIRES;
  qrCountdown = setInterval(() => {
    qrRemainSec--;
    if (qrRemainSec <= 0) {
      stopQRCountdown();
      stopQRPolling();
      qrScannedNotFinal = false;
      showQROverlay({ icon: '⏱', text: '二维码已过期', sub: '点击二维码刷新', clickable: true });
      setQRStatusText('二维码已过期');
    }
  }, 1000);
}

function stopQRCountdown() {
  if (qrCountdown) { clearInterval(qrCountdown); qrCountdown = null; }
}

function showQROverlay({ icon = '', text = '', sub = '', check = false, clickable = false }) {
  const overlay = document.getElementById('qr-overlay');
  if (!overlay) return;
  overlay.style.display = 'flex';

  const checkEl = document.getElementById('qr-overlay-check');
  const iconEl  = document.getElementById('qr-overlay-icon');
  const textEl  = document.getElementById('qr-overlay-text');
  const subEl   = document.getElementById('qr-overlay-sub');

  if (check) {
    checkEl.style.display = 'flex';
    iconEl.style.display = 'none';
  } else {
    checkEl.style.display = 'none';
    if (icon) { iconEl.style.display = ''; iconEl.textContent = icon; }
    else { iconEl.style.display = 'none'; }
  }

  textEl.textContent = text;
  if (sub) { subEl.style.display = ''; subEl.textContent = sub; }
  else { subEl.style.display = 'none'; }

  const container = document.getElementById('qr-container');
  if (container) container.classList.toggle('clickable', !!clickable);
}

function hideQROverlay() {
  const overlay = document.getElementById('qr-overlay');
  if (overlay) overlay.style.display = 'none';
  const container = document.getElementById('qr-container');
  if (container) container.classList.add('clickable');
}

function showScannedUser(user) {
  const fallback = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name || 'U') + '&background=222&color=fff&size=88';
  const container = document.getElementById('qr-scanned-user');
  if (!container) return;
  const img  = document.getElementById('qr-scan-avatar');
  const name = document.getElementById('qr-scan-name');
  img.src = user.picture || fallback;
  img.onerror = function () { this.onerror = null; this.src = fallback; };
  name.textContent = user.name || '用户';
  container.style.display = '';
}

(function bindQRClick() {
  const el = document.getElementById('qr-container');
  if (!el) return;
  el.addEventListener('click', () => {
    if (!isDesktopQR()) return;
    if (qrScannedNotFinal) return;
    if (!el.classList.contains('clickable') && qrSessionId) return;
    initQRCode();
  });
})();

document.addEventListener('DOMContentLoaded', () => {
  if (isDesktopQR()) initQRCode();
});

let lastIsDesktop = null;
window.addEventListener('resize', () => {
  const nowDesktop = isDesktopQR();
  if (lastIsDesktop === null) lastIsDesktop = nowDesktop;
  if (nowDesktop !== lastIsDesktop) {
    lastIsDesktop = nowDesktop;
    if (nowDesktop && !qrSessionId) initQRCode();
    else if (!nowDesktop) { stopQRPolling(); stopQRCountdown(); }
  }
});
</script>
</body>
</html>
