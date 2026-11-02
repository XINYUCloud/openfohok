<?php
// ============================================================
// 扫码登录 - 移动端确认页面
// 用户扫码后打开此页面，确认/取消登录
// ============================================================

require_once __DIR__ . '/../bootstrap.php';

$sessionId = trim($_GET['s'] ?? '');

// 未登录 → 跳转登录页
$user = getCurrentUser();
if (!$user) {
    header('Location: /login.php?to=' . urlencode('/qrcode/confirm.php?s=' . $sessionId));
    exit;
}

if (!$sessionId) {
    die('缺少二维码参数');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>扫码登录确认 · 文谶驿站</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2/html5-qrcode.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Noto+Sans+SC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

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
    --border-primary: #e5e5e5;
    --border-secondary: #d4d4d4;
    --accent-color: #171717;
    --accent-hover: #404040;
    --success-color: #16a34a;
    --success-bg: #f0fdf4;
    --danger-color: #dc2626;
    --danger-bg: #fef2f2;
    --warning-color: #d97706;
    --warning-bg: #fffbeb;
    --shadow-card: 0 4px 24px rgba(0,0,0,0.08);
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
      --border-primary: #1a1a1a;
      --border-secondary: #222222;
      --accent-color: #e5e5e5;
      --accent-hover: #ffffff;
      --success-color: #4ade80;
      --success-bg: #052e16;
      --danger-color: #f87171;
      --danger-bg: #1a0808;
      --warning-color: #fbbf24;
      --warning-bg: #1a1400;
      --shadow-card: none;
    }
  }

  body {
    background: var(--bg-primary);
    color: var(--text-primary);
    font-family: 'Noto Sans SC', sans-serif;
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }

  .card {
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: 16px;
    width: 100%;
    max-width: 360px;
    overflow: hidden;
    box-shadow: var(--shadow-card);
  }

  .card-head {
    padding: 28px 28px 20px;
    text-align: center;
    border-bottom: 1px solid var(--border-primary);
  }

  .card-body {
    padding: 24px 28px 28px;
    text-align: center;
  }

  .logo {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: var(--text-muted);
    letter-spacing: .15em;
    margin-bottom: 14px;
  }

  .card-title {
    font-size: 18px;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 4px;
  }

  .card-sub {
    font-size: 13px;
    color: var(--text-dim);
  }

  .user-badge {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-primary);
    border-radius: 10px;
    margin-bottom: 24px;
    text-align: left;
  }

  .user-badge img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border-secondary);
    flex-shrink: 0;
  }

  .desc {
    font-size: 13px;
    color: var(--text-dim);
    line-height: 1.7;
    margin-bottom: 24px;
  }

  .btn {
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
    transition: all .2s;
    border: none;
    font-family: inherit;
    text-decoration: none;
  }

  .btn-primary {
    background: var(--accent-color);
    color: var(--bg-secondary);
  }
  .btn-primary:hover { background: var(--accent-hover); }
  .btn-primary:disabled {
    background: var(--bg-hover);
    color: var(--text-muted);
    cursor: not-allowed;
  }

  .btn-danger {
    background: var(--danger-color);
    color: #fff;
  }

  .btn-ghost {
    background: transparent;
    color: var(--text-dim);
    border: 1px solid var(--border-primary);
  }
  .btn-ghost:hover {
    background: var(--bg-hover);
    border-color: var(--border-secondary);
  }

  .btn + .btn {
    margin-top: 12px;
  }

  /* 成功状态 */
  .success-icon {
    width: 56px; height: 56px;
    border-radius: 50%;
    background: var(--success-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  /* ── 扫描器浮层 ── */
  #scanner-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: #000;
    flex-direction: column;
  }

  #scanner-overlay.active {
    display: flex;
  }

  .scanner-header {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    padding-top: max(16px, env(safe-area-inset-top));
  }

  .scanner-header .scanner-title {
    color: #fff;
    font-size: 15px;
    font-weight: 500;
  }

  .scanner-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,.12);
    border: none;
    cursor: pointer;
    color: #fff;
    font-size: 18px;
    transition: background .2s;
  }
  .scanner-close:hover {
    background: rgba(255,255,255,.22);
  }

  #qr-reader {
    width: 100%;
    height: 100%;
  }

  /* 覆盖 html5-qrcode 默认样式 */
  #qr-reader video {
    object-fit: cover !important;
    width: 100% !important;
    height: 100% !important;
  }
  #qr-reader__scan_region {
    position: relative;
    width: 100%;
    height: 100%;
  }
  #qr-reader__dashboard {
    display: none !important;
  }
  #qr-reader__header_message {
    display: none !important;
  }

  .scanner-hint {
    position: absolute;
    bottom: 80px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    color: rgba(255,255,255,.7);
    font-size: 13px;
    text-align: center;
    padding: 8px 16px;
    background: rgba(0,0,0,.5);
    border-radius: 20px;
    pointer-events: none;
  }

  .scanner-error {
    position: absolute;
    bottom: 120px;
    left: 20px;
    right: 20px;
    z-index: 10;
    color: #fff;
    font-size: 13px;
    text-align: center;
    padding: 10px 16px;
    background: rgba(220,38,38,.85);
    border-radius: 10px;
    display: none;
  }
  .scanner-error.show {
    display: block;
  }

  @media (min-width: 768px) {
    /* 桌面端扫描器居中显示 */
    #scanner-overlay {
      align-items: center;
      justify-content: center;
    }
    #qr-reader {
      max-width: 480px;
      max-height: 480px;
      border-radius: 12px;
      overflow: hidden;
    }
    .scanner-hint {
      bottom: auto;
      top: calc(50% + 260px);
    }
  }
</style>
</head>
<body>

<div class="card">
  <div class="card-head">
    <p class="logo">OPEN-FOHOK</p>
    <h1 class="card-title" id="page-title">扫码登录确认</h1>
    <p class="card-sub" id="page-sub">文谶驿站 - 开发者开放仓库</p>
  </div>
  <div class="card-body" id="card-body">

    <!-- 加载中 -->
    <div id="loading-state">
      <div style="display:flex;justify-content:center;margin-bottom:16px;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--text-dim)" stroke-width="2"
             style="animation:spin .8s linear infinite;">
          <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
        </svg>
      </div>
      <p style="font-size:13px;color:var(--text-dim);">正在校验登录码，Please be patient...</p>
    </div>

    <!-- 确认面板 -->
    <div id="confirm-state" style="display:none">
      <div class="user-badge">
        <img id="confirm-avatar" src="" alt="">
        <div>
          <p style="font-size:11px;margin-bottom:3px;color:var(--text-dim);">当前登录账号: </p>
          <p id="confirm-name" style="font-size:15px;font-weight:500;"></p>
        </div>
      </div>
      <p class="desc">
        是否确认使用此账号在另一台设备上登录？<br>
        确认后，另一台设备将登录此账号。
      </p>
      <button id="btn-confirm" class="btn btn-primary" onclick="doConfirm()">登录</button>
      <button id="btn-cancel" class="btn btn-ghost" onclick="doCancel()">取消</button>
    </div>

    <!-- 已过期 -->
    <div id="expired-state" style="display:none">
      <p style="font-size:14px;color:var(--text-dim);margin-bottom:16px;">二维码已过期或无效</p>
      <div id="expired-actions"></div>
    </div>

    <!-- 已取消 -->
    <div id="cancelled-state" style="display:none">
      <p style="font-size:14px;color:var(--text-dim);margin-bottom:16px;">已取消登录</p>
      <div id="cancelled-actions"></div>
    </div>

    <!-- 已成功 -->
    <div id="success-state" style="display:none">
      <div class="success-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--success-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <p style="font-size:16px;font-weight:500;margin-bottom:6px;">登录已确认</p>
      <p style="font-size:13px;color:var(--text-dim);">请返回页面继续</p>
      <div id="success-actions" style="margin-top:16px;"></div>
    </div>

  </div>
</div>

<!-- ── 扫描器浮层 ── -->
<div id="scanner-overlay">
  <div class="scanner-header">
    <span class="scanner-title">扫描二维码</span>
    <button class="scanner-close" onclick="closeScanner()" aria-label="关闭">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>
  </div>
  <div id="qr-reader"></div>
  <p class="scanner-hint">请将登录页二维码对准扫描框进行扫描</p>
  <div class="scanner-error" id="scanner-error-msg"></div>
</div>

<script>
const SESSION_ID = <?= json_encode($sessionId) ?>;
const API_URL = '/api/qrcode_login.php';

// 当前用户信息（PHP 注入）
const CURRENT_USER = {
  id: <?= json_encode($user['id']) ?>,
  name: <?= json_encode($user['name'] ?? '用户') ?>,
  picture: <?= json_encode($user['picture'] ?? '') ?>,
};

// ── 设备检测 ──────────────────────────────────────────────────
function isMobile() {
  return /Android|iPhone|iPad|iPod|webOS/i.test(navigator.userAgent) ||
         (navigator.maxTouchPoints > 0 && window.innerWidth < 768);
}

// ── 渲染操作按钮 ──────────────────────────────────────────────
function renderActionButtons(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  if (isMobile()) {
    // 移动端：显示重新扫描按钮
    container.innerHTML = '<button class="btn btn-primary" onclick="openScanner()">'
      + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">'
      + '<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/>'
      + '<path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/>'
      + '<rect x="7" y="7" width="10" height="10" rx="2"/>'
      + '</svg>重新扫描</button>';
  } else {
    // 桌面端：显示返回首页
    container.innerHTML = '<a href="/home.php" class="btn btn-ghost">返回首页</a>';
  }
}

// ── 页面加载时自动扫码 ─────────────────────────────────────────
async function autoScan() {
  try {
    const fd = new FormData();
    fd.append('action', 'scan');
    fd.append('session_id', SESSION_ID);

    const res  = await fetch(API_URL, { method: 'POST', body: fd });
    const data = await res.json();

    if (data.ok) {
      showConfirmPanel(data.user || CURRENT_USER);
    } else {
      showState('expired');
    }
  } catch (e) {
    console.error('扫码请求失败:', e);
    showState('expired');
  }
}

function showConfirmPanel(u) {
  const avatar = u.picture || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.name || 'U') + '&background=222&color=fff&size=88';
  document.getElementById('confirm-avatar').src = avatar;
  document.getElementById('confirm-avatar').onerror = function() {
    this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.name || 'U') + '&background=222&color=fff&size=88';
  };
  document.getElementById('confirm-name').textContent = u.name || '用户';
  showState('confirm');
}

function showState(state) {
  ['loading', 'confirm', 'expired', 'cancelled', 'success'].forEach(s => {
    const el = document.getElementById(s + '-state');
    if (el) el.style.display = s === state ? '' : 'none';
  });

  // 为需要操作按钮的状态渲染按钮
  if (state === 'expired') {
    renderActionButtons('expired-actions');
  } else if (state === 'cancelled') {
    renderActionButtons('cancelled-actions');
  } else if (state === 'success') {
    renderActionButtons('success-actions');
  }
}

// ── 确认登录 ──────────────────────────────────────────────────
async function doConfirm() {
  const btn = document.getElementById('btn-confirm');
  btn.disabled = true;
  btn.textContent = '确认中...';

  try {
    const fd = new FormData();
    fd.append('action', 'confirm');
    fd.append('session_id', SESSION_ID);

    const res  = await fetch(API_URL, { method: 'POST', body: fd });
    const data = await res.json();

    if (data.ok) {
      showState('success');
      // 2 秒后自动关闭页面
      if (isMobile()) {
        setTimeout(() => {
          try { window.close(); } catch(e) {}
        }, 2000);
      }
    } else {
      btn.disabled = false;
      btn.textContent = '确认登录';
      alert(data.msg || '确认失败，请重试');
    }
  } catch (e) {
    btn.disabled = false;
    btn.textContent = '确认登录';
    alert('网络错误，请重试');
  }
}

// ── 取消登录 ──────────────────────────────────────────────────
async function doCancel() {
  const btn = document.getElementById('btn-cancel');
  btn.disabled = true;
  btn.textContent = '取消中...';

  try {
    const fd = new FormData();
    fd.append('action', 'cancel');
    fd.append('session_id', SESSION_ID);

    await fetch(API_URL, { method: 'POST', body: fd });
  } catch (e) {}

  showState('cancelled');
}

// ── 二维码扫描器 ──────────────────────────────────────────────
let html5QrCode = null;

async function openScanner() {
  const overlay = document.getElementById('scanner-overlay');
  const errorEl = document.getElementById('scanner-error-msg');

  overlay.classList.add('active');
  errorEl.classList.remove('show');
  errorEl.textContent = '';

  // 检查摄像头权限
  try {
    // 先尝试获取摄像头权限
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    // 立即关闭这个流，html5-qrcode 会自己打开
    stream.getTracks().forEach(t => t.stop());
  } catch (permErr) {
    // 权限被拒绝或摄像头不可用
    overlay.classList.remove('active');
    if (permErr.name === 'NotAllowedError' || permErr.name === 'PermissionDeniedError') {
      alert('摄像头权限未批准，请在系统设置中允许相机权限后重试。');
    } else if (permErr.name === 'NotFoundError' || permErr.name === 'DevicesNotFoundError') {
      alert('未检测到摄像头设备。');
    } else {
      alert('无法使用摄像头：' + (permErr.message || '未知错误'));
    }
    return;
  }

  try {
    html5QrCode = new Html5Qrcode('qr-reader');

    await html5QrCode.start(
      { facingMode: 'environment' },
      {
        fps: 10,
        qrbox: function(viewfinderWidth, viewfinderHeight) {
          const size = Math.min(viewfinderWidth, viewfinderHeight) * 0.7;
          return { width: size, height: size };
        },
        aspectRatio: 1,
      },
      onScanSuccess,
      onScanFailure
    );
  } catch (err) {
    overlay.classList.remove('active');
    console.error('扫描载人失败:', err);
    alert('扫描载入失败，请重试。');
  }
}

function onScanSuccess(decodedText) {
  // 停止扫描
  stopScanner();

  // 解析扫描结果，提取 session ID
  let sessionId = null;

  // 尝试作为 URL 解析
  try {
    const url = new URL(decodedText);
    sessionId = url.searchParams.get('s');
  } catch (e) {
    // 不是完整 URL，尝试作为纯 session ID
    if (/^[a-zA-Z0-9_-]+$/.test(decodedText.trim())) {
      sessionId = decodedText.trim();
    }
  }

  if (sessionId) {
    // 关闭扫描并跳转到新 session
    const overlay = document.getElementById('scanner-overlay');
    overlay.classList.remove('active');
    window.location.href = '/qrcode/confirm.php?s=' + encodeURIComponent(sessionId);
  } else {
    // 扫描的不是有效二维码，显示错误提示
    const errorEl = document.getElementById('scanner-error-msg');
    errorEl.textContent = '无效的二维码，请扫描文谶驿站登录页二维码';
    errorEl.classList.add('show');
    setTimeout(() => errorEl.classList.remove('show'), 3000);

    // 重新启动扫描
    startScannerAgain();
  }
}

function onScanFailure(error) {
  // 静默忽略扫描失败
  // console.debug('QR scan attempt:', error);
}

async function startScannerAgain() {
  if (!html5QrCode) return;
  try {
    await html5QrCode.start(
      { facingMode: 'environment' },
      {
        fps: 10,
        qrbox: function(viewfinderWidth, viewfinderHeight) {
          const size = Math.min(viewfinderWidth, viewfinderHeight) * 0.7;
          return { width: size, height: size };
        },
        aspectRatio: 1,
      },
      onScanSuccess,
      onScanFailure
    );
  } catch (err) {
    console.error('扫描重启失败:', err);
  }
}

function stopScanner() {
  if (html5QrCode && html5QrCode.isScanning) {
    html5QrCode.stop().then(() => {
      html5QrCode.clear();
    }).catch(err => {
      console.error('停止扫描失败:', err);
    });
  }
}

function closeScanner() {
  stopScanner();
  const overlay = document.getElementById('scanner-overlay');
  overlay.classList.remove('active');
}

// 初始化
autoScan();
</script>
</body>
</html>