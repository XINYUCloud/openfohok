<?php
// ============================================================
// App 下载页
// 直接下载 + 实时进度检测 + 新粗野主义 UI
// ============================================================
require_once __DIR__ . '/bootstrap.php';

// 尝试获取当前用户
$user = optionalLogin();
$isLoggedIn = $user !== null;
$isAdmin = $isLoggedIn && in_array($user['email'], ADMIN_EMAILS);

$updateData = null;
try {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM app_updates ORDER BY created_at DESC LIMIT 1');
    $latest = $stmt->fetch();
    if ($latest) {
        $updateData = [
            'has_update'   => true,
            'app_name'     => $latest['app_name'],
            'version'      => $latest['version'],
            'changelog'    => $latest['changelog'],
            'package_size' => $latest['package_size'],
            'download_url' => $latest['download_url'],
            'is_force'     => (bool)$latest['is_force'],
        ];
    }
} catch (\Throwable $e) {
    $updateData = null;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="文谶驿站客户端下载 — 开发者开放仓库">
<meta property="og:title" content="文谶驿站 · 客户端下载">
<meta property="og:description" content="下载文谶驿站 App，随时随地浏览开发者开放仓库。">
<meta property="og:image" content="https://fohok.xin/img/fohok-2.png">
<meta property="og:url" content="https://fohok.xin/download.php">
<meta property="og:type" content="website">
<link rel="icon" href="/img/fohok-2.png" sizes="any">
<title>下载 · 文谶驿站</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Archivo+Black&family=Noto+Sans+SC:wght@500;700;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── TOKENS ─────────────────────────────────────────── */
:root {
  --black:   #000000;
  --white:   #ffffff;
  --cream:   #f6f1e6;
  --yellow:  #ffe500;
  --pink:    #ff2e88;
  --blue:    #2d2dff;
  --green:   #16d46a;
  --orange:  #ff6a1f;
  --cyan:    #00d0e0;
  --gray:    #b8b8b8;
  --red:     #e02020;

  --f-display: 'Archivo Black', 'Noto Sans SC', sans-serif;
  --f-sans:    'Space Grotesk', 'Noto Sans SC', system-ui, sans-serif;
  --f-mono:    'JetBrains Mono', ui-monospace, monospace;

  --bw: 3px;
  --sh: 7px 7px 0 var(--black);
  --sh-lg: 10px 10px 0 var(--black);
  --ease: cubic-bezier(0.16, 1, 0.3, 1);
}

html { font-size: 16px; scroll-behavior: smooth; }
body {
  background: var(--cream);
  color: var(--black);
  font-family: var(--f-sans);
  min-height: 100vh;
  display: flex; flex-direction: column;
  -webkit-font-smoothing: antialiased;
}

::-webkit-scrollbar { width: 12px; }
::-webkit-scrollbar-track { background: var(--cream); border-left: 3px solid var(--black); }
::-webkit-scrollbar-thumb { background: var(--black); }

a { color: inherit; text-decoration: none; }
::selection { background: var(--yellow); color: var(--black); }

/* ── TOPBAR ─────────────────────────────────────────── */
#topbar {
  position: fixed; top: 0; left: 0; right: 0; height: 64px;
  background: var(--black);
  color: var(--white);
  border-bottom: var(--bw) solid var(--black);
  z-index: 100;
  display: flex; align-items: center;
  padding: 0 26px; gap: 16px;
}

.logo-mark {
  display: flex; align-items: center; gap: 12px;
  color: var(--white);
}
.logo-block {
  width: 38px; height: 38px;
  object-fit: cover;
  display: block;
  border: var(--bw) solid var(--white);
  box-shadow: 3px 3px 0 var(--pink);
}
.logo-text {
  display: flex; flex-direction: column; line-height: 1.0;
}
.logo-char {
  font-family: var(--f-display);
  font-size: 18px; font-weight: 900;
  letter-spacing: -0.01em;
}
.logo-sub {
  font-family: var(--f-mono);
  font-size: 9.5px;
  color: var(--yellow);
  letter-spacing: 0.2em;
  text-transform: uppercase;
  margin-top: 3px;
  font-weight: 700;
}

/* ── ANNOUNCEMENT BUBBLE (from logo) ────────────────── */
.logo-wrap { position: relative; }

.ann-bubble {
  position: absolute;
  top: calc(100% + 16px);
  left: 0;
  width: 340px;
  max-width: calc(100vw - 52px);
  background: var(--white);
  color: var(--black);
  border: var(--bw) solid var(--black);
  box-shadow: var(--sh-lg);
  z-index: 300;
  opacity: 0;
  pointer-events: none;
  transform: translateY(-12px) scale(0.96);
  transform-origin: top left;
  transition: opacity .2s var(--ease), transform .2s var(--ease);
}
.ann-bubble.show { opacity: 1; pointer-events: all; transform: translateY(0) scale(1); }

.ann-bubble::before {
  content: '';
  position: absolute; top: -11px; left: 14px;
  width: 0; height: 0;
  border-left: 11px solid transparent;
  border-right: 11px solid transparent;
  border-bottom: 11px solid var(--black);
}
.ann-bubble::after {
  content: '';
  position: absolute; top: -7px; left: 17px;
  width: 0; height: 0;
  border-left: 7px solid transparent;
  border-right: 7px solid transparent;
  border-bottom: 7px solid var(--white);
  transition: border-bottom-color .15s;
}
.ann-bubble.ann-info      { background: var(--blue);   color: var(--white); }
.ann-bubble.ann-important { background: var(--yellow); color: var(--black); }
.ann-bubble.ann-warning   { background: var(--red);    color: var(--white); }
.ann-bubble.ann-info::after      { border-bottom-color: var(--blue); }
.ann-bubble.ann-important::after { border-bottom-color: var(--yellow); }
.ann-bubble.ann-warning::after   { border-bottom-color: var(--red); }

.ann-head {
  background: var(--black); color: var(--white);
  padding: 10px 16px;
  display: flex; align-items: center; justify-content: space-between;
  font-family: var(--f-mono); font-size: 11px; font-weight: 700;
  letter-spacing: 0.16em; text-transform: uppercase;
  border-bottom: var(--bw) solid var(--black);
}
.ann-close-x {
  background: none; border: none; cursor: pointer;
  color: var(--white); font-size: 16px; font-weight: 900;
  line-height: 1; padding: 0 0 0 8px;
  font-family: var(--f-sans);
}
.ann-close-x:hover { color: var(--pink); }

.ann-title {
  font-family: var(--f-display); font-size: 18px; font-weight: 900;
  letter-spacing: -0.01em; line-height: 1.2;
  padding: 16px 18px 6px;
}
.ann-content {
  font-family: var(--f-sans); font-size: 14px; line-height: 1.65;
  font-weight: 500; padding: 0 18px 18px;
}
.ann-content p { margin: 0; }
.ann-content p + p { margin-top: 6px; }
.ann-content strong { font-weight: 900; }
.ann-content a {
  font-weight: 700;
  text-decoration: underline;
  text-decoration-thickness: 2px;
  text-underline-offset: 2px;
}
.ann-bubble.ann-info .ann-content a, .ann-bubble.ann-warning .ann-content a { color: var(--white); }
.ann-content a:hover { opacity: 0.7; }

/* Badge dot on logo */
.logo-badge {
  position: absolute; top: -5px; right: -5px;
  width: 12px; height: 12px;
  background: var(--pink);
  border: 2px solid var(--black);
  border-radius: 50%;
  display: none;
  animation: badgePulse 1.8s ease infinite;
}
.logo-badge.show { display: block; }
@keyframes badgePulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.25); }
}

/* ── DROPDOWN ───────────────────────────────────────── */
.dropdown { position: relative; }
.dropdown-trigger {
  background: none; border: none; cursor: pointer;
  display: flex; align-items: center; gap: 8px;
  padding: 3px;
}
.dropdown-trigger img {
  width: 36px; height: 36px;
  border: var(--bw) solid var(--white);
  object-fit: cover;
}
.dropdown-menu {
  position: absolute; right: 0; top: calc(100% + 14px);
  background: var(--white);
  color: var(--black);
  border: var(--bw) solid var(--black);
  min-width: 200px;
  z-index: 200; opacity: 0; pointer-events: none;
  transform: translateY(-8px);
  transition: opacity .16s var(--ease), transform .16s var(--ease);
  box-shadow: var(--sh-lg);
  padding: 4px;
}
.dropdown-menu.show { opacity: 1; pointer-events: all; transform: translateY(0); }
.dropdown-item {
  display: flex; align-items: center; gap: 11px;
  padding: 12px 14px; font-size: 14px; font-weight: 700;
  color: var(--black); cursor: pointer;
  border: none; background: none; width: 100%; text-align: left;
  font-family: var(--f-sans);
  transition: background .1s, color .1s;
}
.dropdown-item:hover { background: var(--yellow); }
.dropdown-item.danger { color: var(--pink); }
.dropdown-item.danger:hover { background: var(--pink); color: var(--white); }
.dropdown-sep { height: var(--bw); background: var(--black); margin: 3px 0; }

/* ── BUTTONS ────────────────────────────────────────── */
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  gap: 8px; font-family: var(--f-sans); font-size: 15px; font-weight: 700;
  cursor: pointer; border: var(--bw) solid var(--black);
  transition: transform .12s var(--ease), box-shadow .12s var(--ease);
  text-decoration: none; letter-spacing: 0.01em;
  position: relative;
}
.btn-pink {
  background: var(--pink); color: var(--white);
  padding: 14px 26px; box-shadow: var(--sh);
}
.btn-pink:hover { transform: translate(4px, 4px); box-shadow: 3px 3px 0 var(--black); }
.btn-yellow {
  background: var(--yellow); color: var(--black);
  padding: 14px 26px; box-shadow: var(--sh);
}
.btn-yellow:hover { transform: translate(4px, 4px); box-shadow: 3px 3px 0 var(--black); }
.btn-outline {
  background: var(--cream); color: var(--black);
  padding: 13px 25px; box-shadow: var(--sh);
}
.btn-outline:hover { transform: translate(4px, 4px); box-shadow: 3px 3px 0 var(--black); background: var(--white); }
.btn-sm { padding: 10px 18px; font-size: 13.5px; box-shadow: 5px 5px 0 var(--black); }
.btn-sm:hover { box-shadow: 2px 2px 0 var(--black); }

/* ── TOPBAR APP ICON ──────────────────────────────── */
.topbar-app-icon {
  position: relative;
  display: inline-flex; align-items: center; justify-content: center;
  width: 38px; height: 38px;
  border: var(--bw) solid var(--white);
  color: var(--white);
  transition: background .12s;
}
.topbar-app-icon:hover { background: var(--pink); }
.app-icon-dot {
  position: absolute; top: -6px; right: -6px;
  width: 10px; height: 10px;
  background: var(--green); border: 2px solid var(--black);
  border-radius: 50%;
}

/* ── PAGE WRAP ──────────────────────────────────────── */
.page-wrap {
  flex: 1;
  padding: 96px 28px 48px;
  max-width: 1080px; margin: 0 auto;
  width: 100%;
}

/* ── PAGE HEADER ────────────────────────────────────── */
.page-header {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 20px; flex-wrap: wrap;
  margin-bottom: 40px;
}
.page-title {
  font-family: var(--f-display);
  font-weight: 900;
  font-size: clamp(32px, 5.5vw, 56px);
  letter-spacing: -0.03em;
  color: var(--black);
  line-height: 0.95;
  text-transform: none;
}
.page-tag {
  font-family: var(--f-mono);
  font-size: 13px; font-weight: 700; letter-spacing: 0.1em;
  text-transform: uppercase;
  background: var(--black); color: var(--white);
  padding: 8px 14px;
  border: var(--bw) solid var(--black);
  box-shadow: var(--sh);
}

/* ── CARD ───────────────────────────────────────────── */
.card {
  background: var(--white);
  border: var(--bw) solid var(--black);
  box-shadow: var(--sh);
  padding: 24px;
  margin-bottom: 24px;
}
.card-header {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; flex-wrap: wrap;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: var(--bw) solid var(--black);
}
.card-label {
  font-family: var(--f-display);
  font-size: 20px; font-weight: 900;
  letter-spacing: -0.01em;
  color: var(--black);
}
.card-tag {
  font-family: var(--f-mono);
  font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
  text-transform: uppercase;
  background: var(--black); color: var(--white);
  padding: 5px 10px;
}

/* ── TWO COLUMN LAYOUT ──────────────────────────────── */
.dl-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
  align-items: start;
}

/* ── DOWNLOAD BUTTON ────────────────────────────────── */
.dl-btn {
  display: inline-flex; align-items: center; justify-content: center;
  gap: 10px;
  width: 100%;
  font-family: var(--f-sans); font-size: 16px; font-weight: 700;
  cursor: pointer;
  border: var(--bw) solid var(--black);
  background: var(--pink); color: var(--white);
  padding: 18px 24px;
  box-shadow: var(--sh);
  transition: transform .12s var(--ease), box-shadow .12s var(--ease);
}
.dl-btn:hover { transform: translate(4px, 4px); box-shadow: 3px 3px 0 var(--black); }

/* ── META ROW ───────────────────────────────────────── */
.meta-row {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 0;
  font-size: 14px; font-weight: 500;
  color: var(--black);
  border-bottom: 1px solid var(--cream);
}
.meta-row:last-child { border-bottom: none; }
.meta-icon {
  width: 18px; height: 18px; flex-shrink: 0;
  color: var(--black);
}

/* ── CHANGELOG ──────────────────────────────────────── */
.changelog-body {
  font-size: 13px; font-weight: 500;
  line-height: 1.8;
  color: var(--black);
  white-space: pre-wrap;
}

/* ── HINT ───────────────────────────────────────────── */
.dl-hint {
  text-align: center;
  font-size: 13px;
  color: var(--black);
  font-weight: 500;
  line-height: 1.8;
  margin-top: 20px;
  background: var(--white);
  border: var(--bw) solid var(--black);
  box-shadow: 5px 5px 0 var(--black);
  padding: 14px 18px;
}
.dl-hint em {
  font-style: normal;
  font-weight: 700;
  background: var(--yellow);
  padding: 0 .25em;
}

/* ── ERROR STATE ────────────────────────────────────── */
.error-state {
  text-align: center;
  padding: 60px 0;
}
.error-icon {
  width: 64px; height: 64px;
  margin: 0 auto 20px;
  border: var(--bw) solid var(--black);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: var(--black);
}
.error-text {
  font-size: 15px;
  color: var(--black);
  font-weight: 500;
  line-height: 1.8;
  background: var(--white);
  border: var(--bw) solid var(--black);
  box-shadow: var(--sh);
  padding: 18px 22px;
  display: inline-block;
}
.error-text a {
  font-weight: 700;
  background: var(--yellow);
  padding: 0 .25em;
}

/* ── FOOTER ─────────────────────────────────────────── */
footer {
  background: var(--black);
  color: var(--white);
  padding: 30px 28px;
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; flex-wrap: wrap;
  width: 100%;
}
.footer-brand {
  font-family: var(--f-display); font-size: 16px;
  color: var(--white); letter-spacing: 0.01em;
  font-weight: 900;
  display: flex; align-items: center; gap: 10px;
}
.footer-brand .sq { width: 14px; height: 14px; background: var(--yellow); border: 2px solid var(--white); }
.footer-copy {
  font-family: var(--f-mono); font-size: 11.5px;
  color: var(--white); letter-spacing: 0.03em;
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.footer-copy a {
  color: var(--white);
  transition: color .12s;
  display: inline-flex; align-items: center; gap: 4px;
}
.footer-copy a:hover { color: var(--yellow); }

/* ── RESPONSIVE ─────────────────────────────────────── */
@media (max-width: 860px) {
  .dl-layout { grid-template-columns: 1fr; gap: 20px; }
}

@media (max-width: 640px) {
  #topbar { padding: 0 16px; }
  .logo-sub { display: none; }
  .page-wrap { padding: 80px 16px 32px; }
  .card { padding: 20px; }
  footer { flex-direction: column; align-items: flex-start; gap: 12px; padding: 24px 16px; }
  .ann-bubble { width: 280px; left: -8px; }
  .ann-bubble::before { left: 8px; }
  .ann-bubble::after { left: 11px; }
}
</style>
</head>
<body>

<!-- ══ TOPBAR ════════════════════════════════════════════ -->
<header id="topbar">
  <div class="logo-wrap">
    <a href="/" class="logo-mark" onclick="event.preventDefault(); toggleAnnBubble()">
      <img src="/img/fohok-2.png" alt="Logo" class="logo-block">
      <div class="logo-text">
        <span class="logo-char">文谶驿站</span>
        <span class="logo-sub">OPEN-FOHOK</span>
      </div>
    </a>
    <span class="logo-badge" id="logo-badge" aria-hidden="true"></span>

    <!-- ══ ANNOUNCEMENT BUBBLE ════════════════════════ -->
    <div id="ann-bubble" class="ann-bubble">
      <div class="ann-head">
        <span>公告 / Announcement</span>
        <span style="display:flex;align-items:center;gap:8px;">
          <button class="ann-close-x" onclick="dismissAnnBubble(event)" aria-label="关闭">&times;</button>
        </span>
      </div>
      <div class="ann-title" id="ann-title">Please be patient...</div>
      <div class="ann-content" id="ann-content"></div>
    </div>
  </div>

  <div style="flex:1"></div>

  <?php if ($isLoggedIn): ?>
  <div class="dropdown">
    <button onclick="toggleDropdown('userMenu')" class="dropdown-trigger" aria-label="用户菜单">
      <img src="<?= htmlspecialchars($user['picture'] ?? 'https://ui-avatars.com/api/?name='.urlencode($user['name']??'U').'&background=ff2e88&color=fff') ?>" alt="avatar">
    </button>
    <div id="userMenu" class="dropdown-menu">
      <a href="/profile.php" class="dropdown-item">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        个人中心
      </a>
      <a href="/home.php" class="dropdown-item">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        项目广场
      </a>
      <?php if ($isAdmin): ?>
      <a href="/admin/" class="dropdown-item">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
        管理后台
      </a>
      <?php endif; ?>
      <div class="dropdown-sep"></div>
      <a href="/logout.php" class="dropdown-item danger">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        退出登录
      </a>
    </div>
  </div>
  <?php else: ?>
  <a href="/login.php" class="btn btn-yellow btn-sm">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
    登录
  </a>
  <?php endif; ?>
</header>

<!-- ══ MAIN ══════════════════════════════════════════════ -->
<div class="page-wrap">

<?php if ($updateData): ?>

  <!-- Page Header -->
  <div class="page-header">
    <h1 class="page-title">下载 App</h1>
    <span class="page-tag">v<?= htmlspecialchars($updateData['version']) ?></span>
  </div>

  <!-- Two-Column Layout -->
  <div class="dl-layout">

    <!-- ── LEFT: Download ─────────────────────────── -->
    <div>
      <!-- Download Card -->
      <div class="card" id="dlCard">
        <div class="card-header">
          <span class="card-label">下载安装</span>
          <span class="card-tag">v<?= htmlspecialchars($updateData['version']) ?></span>
        </div>

        <a href="<?= htmlspecialchars($updateData['download_url']) ?>" target="_blank" class="dl-btn" rel="noopener">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          开始下载
        </a>

      </div>

      <!-- Hint -->
      <div class="dl-hint">
        如无法下载，请使用手机浏览器（Chrome / Edge）打开此页面<br>
        或通过 <em>文谶驿站</em> 项目广场顶栏手机图标进入
      </div>
    </div>

    <!-- ── RIGHT: Version Info ────────────────────── -->
    <div>
      <!-- Version Info Card -->
      <div class="card">
        <div class="card-header">
          <span class="card-label">版本信息</span>
        </div>
        <div class="meta-row">
          <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-linecap="round"/></svg>
          <span>适用于 Android 系统</span>
        </div>
        <?php if ($updateData['package_size']): ?>
        <div class="meta-row">
          <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          <span>安装包大小：<?= htmlspecialchars($updateData['package_size']) ?></span>
        </div>
        <?php endif; ?>
        <div class="meta-row">
          <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
          <span>来源：文谶驿站官方</span>
        </div>
        <div class="meta-row">
          <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>版本号：v<?= htmlspecialchars($updateData['version']) ?></span>
        </div>
      </div>

      <!-- Changelog Card -->
      <?php if ($updateData['changelog']): ?>
      <div class="card">
        <div class="card-header">
          <span class="card-label">更新日志</span>
        </div>
        <div class="changelog-body"><?= htmlspecialchars($updateData['changelog']) ?></div>
      </div>
      <?php endif; ?>

      <!-- Install Guide Card -->
      <div class="card">
        <div class="card-header">
          <span class="card-label">安装说明</span>
        </div>
        <div style="font-size:13px;font-weight:500;line-height:1.9">
          <p style="margin-bottom:10px"><strong>1.</strong> 点击「开始下载」，等待下载完成</p>
          <p style="margin-bottom:10px"><strong>2.</strong> 浏览器弹出下载确认后点击「下载」</p>
          <p><strong>3.</strong> 打开下载的 APK 文件进行安装</p>
        </div>
      </div>
    </div>

  </div><!-- /dl-layout -->

<?php else: ?>

  <!-- Error State -->
  <div class="error-state">
    <div class="error-icon">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
    </div>
    <p class="error-text">
      暂无可用的安装包<br>
      请稍后再试，或访问 <a href="/home.php">项目广场</a>
    </p>
  </div>

<?php endif; ?>

</div>

<!-- ══ FOOTER ════════════════════════════════════════════ -->
<footer>
  <span class="footer-brand"><span class="sq"></span>文谶驿站</span>
  <p class="footer-copy">
    &copy; <?= date('Y') ?> 文谶驿站 &middot; 开发者开放仓库 &mdash; A <a href="https://www.xgorb.cn">X-GORB Studio</a> Brand
    <span>|</span>
    <a href="https://beian.miit.gov.cn" target="_blank">
      <img src="https://fohok.xin/img/icp.png" alt="ICP备案图标" style="width:18px;height:auto;">
      闽ICP备2026024151号
    </a>
    <span>|</span>
    <a href="https://beian.mps.gov.cn/#/query/webSearch?code=35058302351237" target="_blank">
      <img src="https://fohok.xin/img/mps.png" alt="公安备案图标" style="width:18px;height:auto;">
      闽公网安备35058302351237号
    </a>
  </p>
</footer>

<!-- ══ TOAST ══════════════════════════════════════════════ -->

<script>
/* Dropdown */
function toggleDropdown(id) {
  document.getElementById(id).classList.toggle('show');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
});

/* ── ANNOUNCEMENT BUBBLE ──────────────────────────────── */
const ANN_SEVERITY = {
  notice:    { label: '通知', cls: 'ann-notice' },
  info:      { label: '提示', cls: 'ann-info' },
  important: { label: '重要', cls: 'ann-important' },
  warning:   { label: '警告', cls: 'ann-warning' }
};
let annData = null;
let annLoaded = false;
let annLoading = false;

async function preloadAnnouncement() {
  if (annLoaded || annLoading) return;
  annLoading = true;
  try {
    const res = await fetch('/api/announcement.php?id=1');
    const data = await res.json();
    annLoaded = true;
    annLoading = false;
    if (!data.ok || !data.title) return;
    annData = data;
    if (!sessionStorage.getItem('ann_dismissed_1')) {
      document.getElementById('logo-badge').classList.add('show');
    }
    setTimeout(() => {
      if (!sessionStorage.getItem('ann_dismissed_1') && !openBubble && annData) {
        toggleAnnBubble();
      }
    }, 1000);
    const bubble = document.getElementById('ann-bubble');
    if (bubble.classList.contains('show')) renderAnnBubble();
  } catch(e) {
    annLoaded = true;
    annLoading = false;
  }
}

function renderAnnBubble() {
  if (!annData) return;
  const sev = ANN_SEVERITY[annData.severity] || ANN_SEVERITY.info;
  const bubble = document.getElementById('ann-bubble');
  bubble.className = 'ann-bubble ' + sev.cls;
  if (openBubble) bubble.classList.add('show');
  document.getElementById('ann-title').textContent = annData.title;
  document.getElementById('ann-content').innerHTML = annData.content || '';
}

let openBubble = false;
function toggleAnnBubble() {
  const bubble = document.getElementById('ann-bubble');
  openBubble = !bubble.classList.contains('show');
  if (openBubble) {
    if (!annLoaded && !annData) {
      document.getElementById('ann-title').textContent = 'Please be patient...';
      document.getElementById('ann-content').textContent = '';
      preloadAnnouncement();
    }
    if (annData) renderAnnBubble();
    bubble.classList.add('show');
    document.getElementById('logo-badge').classList.remove('show');
  } else {
    bubble.classList.remove('show');
  }
}

function dismissAnnBubble(e) {
  if (e) e.stopPropagation();
  const bubble = document.getElementById('ann-bubble');
  bubble.classList.remove('show');
  openBubble = false;
  sessionStorage.setItem('ann_dismissed_1', '1');
  document.getElementById('logo-badge').classList.remove('show');
}

document.addEventListener('click', e => {
  if (!e.target.closest('.logo-wrap')) {
    const bubble = document.getElementById('ann-bubble');
    if (bubble.classList.contains('show')) {
      bubble.classList.remove('show');
      openBubble = false;
    }
  }
});

/* Page init */
preloadAnnouncement();
</script>
</body>
</html>