<?php
require_once __DIR__ . '/bootstrap.php';

// 尝试获取当前用户，未登录不跳转
$user = optionalLogin();
$isLoggedIn = $user !== null;
$isAdmin = $isLoggedIn && in_array($user['email'], ADMIN_EMAILS)
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta itemprop="name" content="文谶驿站 - 开发者开放仓库"/>
<meta itemprop="image" content="https://blog.fohok.xin/wp-content/uploads/2026/04/cropped-a91fabf8a49526b0ab7941fb2659297e1755339178171.jpg"/>
<meta name="description" itemprop="description" content="一个为独立开发者打造的开源项目展示仓库。在这里，发布你的作品，探索他人的创造，连接志同道合的开发者。"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>
/* Apply theme before first paint to avoid flash */
(function(){var t=localStorage.getItem('fohok_theme');
if(t==='dark'||((!t)&&window.matchMedia('(prefers-color-scheme: dark)').matches)){
  document.documentElement.setAttribute('data-theme','dark');
}else{document.documentElement.setAttribute('data-theme','light');}
})();
</script>
<meta name="theme-color" content="#fafafa" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#050505" media="(prefers-color-scheme: dark)">
<meta property="og:title" content="文谶驿站 · 开发者开放仓库">
<meta property="og:description" content="一个为独立开发者打造的开源项目展示空间。发布你的作品，探索他人的创造，连接志同道合的人。">
<meta property="og:image" content="https://fohok.xin/img/fohok-2.png">
<meta property="og:url" content="https://fohok.xin/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="文谶驿站 · 开发者开放仓库">
<link rel="icon" href="/img/fohok-2.png" sizes="any">
<link rel="alternate" type="application/rss+xml" title="文谶驿站 RSS" href="/fohok-rss.php">
<title>文谶驿站 · 开发者开放仓库</title>

<link rel="preconnect" href="https://fonts.font.im">
<link href="https://fonts.font.im/css2?family=JetBrains+Mono:wght@400;500;700&family=Noto+Sans+SC:wght@300;400;500;700&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── TOKENS ─────────────────────────────────────────────── */
:root {
  color-scheme: light;
  /* 日间 */
  --bg-primary: #fafafa;
  --bg-card: #ffffff;
  --bg-secondary: #f5f5f5;
  --bg-input: #f5f5f5;
  --bg-hover: #f0f0f0;
  --text-primary: #171717;
  --text-secondary: #404040;
  --text-dim: #737373;
  --text-muted: #a3a3a3;
  --border-primary: #e5e5e5;
  --border-secondary: #d4d4d4;
  --border-input: #d4d4d4;
  --border-hover: #a3a3a3;
  --accent-color: #171717;
  --accent-inverse: #f5f5f5;
  --danger-color: #dc2626;
  --grid-line: rgba(23,23,23,.05);

  /* 状态色板（日间） */
  --state-ok-bg: #f0fdf4;  --state-ok-bd: #bbf7d0;  --state-ok-tx: #166534;
  --state-info-bg: #eff6ff;--state-info-bd:#bfdbfe; --state-info-tx:#1e40af;
  --state-warn-bg:#fefce8; --state-warn-bd:#fde68a; --state-warn-tx:#854d0e;
  --state-notice-bg:#f0f9ff;--state-notice-bd:#bae6fd;--state-notice-tx:#075985;
  --state-danger-bg:#fef2f2;--state-danger-bd:#fecaca;--state-danger-tx:#dc2626;

  --scrollbar-track: #f5f5f5;
  --scrollbar-thumb: #d4d4d4;

  --f-sans: 'Noto Sans SC', system-ui, sans-serif;
  --f-mono: 'JetBrains Mono', ui-monospace, monospace;

  --r-2: 4px; --r-3: 6px; --r-4: 8px; --r-6: 12px;
  --ease: cubic-bezier(0.16, 1, 0.3, 1);
}

[data-theme="dark"] {
  color-scheme: dark;
  --bg-primary: #050505;
  --bg-card: #0a0a0a;
  --bg-secondary: #111111;
  --bg-input: #111111;
  --bg-hover: #1a1a1a;
  --text-primary: #f5f5f5;
  --text-secondary: #d4d4d4;
  --text-dim: #a3a3a3;
  --text-muted: #737373;
  --border-primary: #1a1a1a;
  --border-secondary: #222222;
  --border-input: #222222;
  --border-hover: #333333;
  --accent-color: #f5f5f5;
  --accent-inverse: #171717;
  --danger-color: #f87171;
  --grid-line: rgba(255,255,255,.045);

  --state-ok-bg:#052e16; --state-ok-bd:#14532d; --state-ok-tx:#4ade80;
  --state-info-bg:#0f172a;--state-info-bd:#1e3a5f;--state-info-tx:#60a5fa;
  --state-warn-bg:#1a1a00;--state-warn-bd:#3a3000;--state-warn-tx:#fbbf24;
  --state-notice-bg:#0d1117;--state-notice-bd:#1e3a5f;--state-notice-tx:#7dd3fc;
  --state-danger-bg:#1a0808;--state-danger-bd:#3a1515;--state-danger-tx:#f87171;

  --scrollbar-track: #0a0a0a;
  --scrollbar-thumb: #333333;
}

/* ── BASE ─────────────────────────────────────────────── */
html { font-size: 16px; scroll-behavior: smooth; }
body {
  background: var(--bg-primary);
  color: var(--text-primary);
  font-family: var(--f-sans);
  min-height: 100vh;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
  transition: background .3s, color .3s;
}

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: var(--scrollbar-track); }
::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 2px; }

a { color: inherit; text-decoration: none; }
::selection { background: color-mix(in srgb, var(--accent-color) 16%, transparent); color: var(--text-primary); }

/* Utility */
.hl { background: var(--bg-secondary); padding: 0 .12em; border-radius: 2px; }

/* ── TOPBAR ───────────────────────────────────────────── */
#topbar {
  position: fixed; top: 0; left: 0; right: 0; height: 64px;
  background: color-mix(in srgb, var(--bg-primary) 80%, transparent);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border-bottom: 1px solid var(--border-primary);
  z-index: 100;
  display: flex; align-items: center;
  padding: 0 28px; gap: 12px;
}

.logo-mark { display: flex; align-items: center; gap: 12px; color: var(--text-primary); }
.logo-block { width: 34px; height: 34px; object-fit: cover; display: block; border-radius: 6px; border: 1px solid var(--border-secondary); }
.logo-text { display: flex; flex-direction: column; line-height: 1.1; }
.logo-char { font-family: var(--f-sans); font-size: 17px; font-weight: 700; letter-spacing: .01em; color: var(--text-primary); }
.logo-sub { font-family: var(--f-mono); font-size: 9.5px; color: var(--text-dim); letter-spacing: .18em; text-transform: uppercase; margin-top: 2px; font-weight: 500; }

/* ── TOPBAR ACTION ICONS ─────────────────────────────── */
.topbar-app-icon, .theme-toggle {
  position: relative;
  display: inline-flex; align-items: center; justify-content: center;
  width: 40px; height: 40px;
  color: var(--text-primary);
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--r-3);
  transition: background .15s, border-color .15s, transform .15s, box-shadow .15s;
  text-decoration: none; cursor: pointer; font-family: inherit;
}
.topbar-app-icon:hover, .theme-toggle:hover { background: var(--bg-hover); border-color: var(--border-hover); transform: translateY(-1px); }
.app-icon-dot { position: absolute; top: -3px; right: -3px; width: 10px; height: 10px; background: var(--danger-color); border: 2px solid var(--bg-card); border-radius: 50%; pointer-events: none; }
.theme-toggle .ico-sun { display: none; }
.theme-toggle .ico-moon { display: block; }
[data-theme="dark"] .theme-toggle .ico-sun { display: block; }
[data-theme="dark"] .theme-toggle .ico-moon { display: none; }

/* ── DROPDOWN ────────────────────────────────────────── */
.dropdown { position: relative; }
.dropdown-trigger { background: none; border: none; cursor: pointer; display: flex; align-items: center; padding: 3px; }
.dropdown-trigger img { width: 36px; height: 36px; border-radius: 50%; border: 2px solid var(--border-primary); object-fit: cover; }
.dropdown-menu {
  position: absolute; right: 0; top: calc(100% + 10px);
  background: var(--bg-card); color: var(--text-primary);
  border: 1px solid var(--border-secondary); border-radius: var(--r-6);
  min-width: 210px; z-index: 200; opacity: 0; pointer-events: none;
  transform: translateY(-6px);
  transition: opacity .18s var(--ease), transform .18s var(--ease);
  box-shadow: 0 12px 32px -12px rgba(0,0,0,.18);
  padding: 6px;
}
.dropdown-menu.show { opacity: 1; pointer-events: auto; transform: none; }
.dropdown-item {
  display: flex; align-items: center; gap: 11px;
  padding: 10px 12px; font-size: 14px; font-weight: 500;
  color: var(--text-primary); cursor: pointer;
  border: none; background: none; width: 100%; text-align: left;
  font-family: var(--f-sans); border-radius: var(--r-2);
  transition: background .12s;
}
.dropdown-item:hover { background: var(--bg-hover); }
.dropdown-item.danger { color: var(--danger-color); }
.dropdown-item.danger:hover { background: color-mix(in srgb, var(--danger-color) 10%, transparent); }
.dropdown-sep { height: 1px; background: var(--border-primary); margin: 6px 4px; }

/* ── BUTTONS ─────────────────────────────────────────── */
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  gap: 8px; font-family: var(--f-sans); font-size: 14px; font-weight: 500;
  cursor: pointer; border-radius: var(--r-3);
  transition: background .18s, border-color .18s, color .18s, transform .18s var(--ease), box-shadow .18s;
  text-decoration: none; letter-spacing: .01em; border: 1px solid transparent;
}
.btn svg { stroke-width: 2; flex-shrink: 0; }
.btn-primary { background: var(--accent-color); color: var(--accent-inverse); padding: 12px 22px; }
.btn-primary:hover { filter: brightness(1.12); transform: translateY(-1px); box-shadow: 0 8px 20px -10px rgba(0,0,0,.4); }
.btn-ghost { background: transparent; color: var(--text-dim); border-color: var(--border-secondary); padding: 11px 21px; }
.btn-ghost:hover { border-color: var(--border-hover); color: var(--text-primary); background: var(--bg-hover); transform: translateY(-1px); }
.btn-sm { padding: 8px 14px; font-size: 13px; border-radius: var(--r-2); }
.btn-sm.btn-primary:hover { box-shadow: 0 6px 14px -8px rgba(0,0,0,.35); }

/* ── ANNOUNCEMENT BUBBLE ─────────────────────────────── */
.logo-wrap { position: relative; }
.ann-bubble {
  position: absolute;
  top: calc(100% + 14px); left: 0;
  width: 340px; max-width: calc(100vw - 52px);
  background: var(--bg-card); color: var(--text-primary);
  border: 1px solid var(--border-secondary); border-radius: var(--r-6);
  box-shadow: 0 20px 44px -18px rgba(0,0,0,.22);
  z-index: 300; opacity: 0; pointer-events: none;
  transform: translateY(-8px) scale(.98);
  transform-origin: top left;
  transition: opacity .2s var(--ease), transform .2s var(--ease);
  overflow: hidden;
}
.ann-bubble.show { opacity: 1; pointer-events: auto; transform: none; }
.ann-bubble::before {
  content: ''; position: absolute; top: -7px; left: 16px;
  width: 12px; height: 12px;
  background: var(--bg-card);
  border-left: 1px solid var(--border-secondary);
  border-top: 1px solid var(--border-secondary);
  transform: rotate(45deg); z-index: 1;
}
.ann-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 18px;
  font-family: var(--f-mono); font-size: 11px; font-weight: 500;
  letter-spacing: .12em; text-transform: uppercase; color: var(--text-dim);
  border-bottom: 1px solid var(--border-primary);
}
.ann-close-x { background: none; border: none; cursor: pointer; color: var(--text-dim); font-size: 16px; font-weight: 500; line-height: 1; padding: 2px 6px; border-radius: var(--r-2); font-family: var(--f-sans); }
.ann-close-x:hover { color: var(--text-primary); background: var(--bg-hover); }
.ann-title { font-family: var(--f-sans); font-size: 15px; font-weight: 700; color: var(--text-primary); line-height: 1.3; padding: 14px 18px 6px; }
.ann-content { font-size: 13.5px; line-height: 1.7; color: var(--text-secondary); padding: 0 18px 16px; }
.ann-content p { margin: 0; }
.ann-content p + p { margin-top: 8px; }
.ann-content a { color: var(--accent-color); font-weight: 500; text-decoration: underline; text-underline-offset: 2px; }
.ann-content a:hover { opacity: .75; }

/* severity 状态（自动适配日/夜） */
.ann-bubble.ann-notice   { background: var(--state-notice-bg); border-color: var(--state-notice-bd); }
.ann-bubble.ann-notice::before   { background: var(--state-notice-bg); }
.ann-bubble.ann-info     { background: var(--state-info-bg); border-color: var(--state-info-bd); }
.ann-bubble.ann-info::before     { background: var(--state-info-bg); }
.ann-bubble.ann-warning  { background: var(--state-warn-bg); border-color: var(--state-warn-bd); }
.ann-bubble.ann-warning::before  { background: var(--state-warn-bg); }
.ann-bubble.ann-important{ background: var(--accent-color); color: var(--accent-inverse); border-color: var(--accent-color); }
.ann-bubble.ann-important::before{ background: var(--accent-color); }
.ann-bubble.ann-important .ann-head{ color: color-mix(in srgb, currentColor 72%, transparent); border-bottom-color: color-mix(in srgb, currentColor 18%, transparent); }
.ann-bubble.ann-important .ann-title{ color: inherit; }
.ann-bubble.ann-important .ann-content{ color: color-mix(in srgb, currentColor 86%, transparent); }
.ann-bubble.ann-important .ann-content a{ color: inherit; text-decoration-color: currentColor; }
.ann-bubble.ann-important .ann-close-x{ color: inherit; }
.ann-bubble.ann-important .ann-close-x:hover{ background: color-mix(in srgb, #fff 12%, transparent); }

/* Badge dot on logo */
.logo-badge {
  position: absolute; top: -2px; right: -2px;
  width: 10px; height: 10px;
  background: var(--danger-color);
  border: 2px solid var(--bg-primary);
  border-radius: 50%; display: none;
  animation: badgePulse 1.8s ease infinite;
}
.logo-badge.show { display: block; }
@keyframes badgePulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.3); } }

/* ── HERO ─────────────────────────────────────────────── */
.hero {
  min-height: 100svh; padding-top: 64px;
  position: relative;
  display: flex; align-items: center;
  border-bottom: 1px solid var(--border-primary);
  overflow: hidden;
}
/* 细网格纹理，呼应开发者气质 */
.hero::before {
  content: ''; position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(to right, var(--grid-line) 1px, transparent 1px),
    linear-gradient(to bottom, var(--grid-line) 1px, transparent 1px);
  background-size: 44px 44px;
  -webkit-mask-image: radial-gradient(82% 68% at 50% 34%, #000 0%, transparent 80%);
  mask-image: radial-gradient(82% 68% at 50% 34%, #000 0%, transparent 80%);
}
.hero-inner {
  position: relative; z-index: 1;
  width: 100%; max-width: 1280px; margin: 0 auto;
  padding: 90px 28px 110px;
}
.hero-eyebrow {
  display: inline-flex; align-items: center; gap: 10px;
  font-family: var(--f-mono); font-size: 12px; font-weight: 500;
  letter-spacing: .14em; text-transform: uppercase; color: var(--text-dim);
  background: var(--bg-card); border: 1px solid var(--border-secondary);
  border-radius: var(--r-3); padding: 7px 13px; margin-bottom: 30px;
}
.hero-eyebrow .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--state-ok-tx); box-shadow: 0 0 0 3px color-mix(in srgb, var(--state-ok-tx) 16%, transparent); }
.hero-title {
  font-family: var(--f-sans); font-weight: 700;
  font-size: clamp(40px, 7vw, 84px);
  line-height: 1.05; letter-spacing: -.02em; color: var(--text-primary);
  margin-bottom: 22px; text-transform: none;
}
.hero-title .l2 { display: block; }
.hero-title .marker, .hero-title .marker-pink {
  color: var(--accent-color);
  background: var(--bg-secondary);
  border-radius: var(--r-2);
  padding: 0 .08em;
  box-shadow: inset 0 -2px 0 color-mix(in srgb, var(--accent-color) 22%, transparent);
}
.hero-sub {
  display: flex; align-items: center; gap: 12px;
  font-family: var(--f-mono); font-size: clamp(14px, 2vw, 17px); font-weight: 500;
  color: var(--text-dim); letter-spacing: .06em; margin-bottom: 26px;
}
.hero-sub::before { content: ''; width: 38px; height: 1px; background: var(--border-hover); }
.hero-desc {
  font-size: 16px; line-height: 1.8; color: var(--text-secondary);
  max-width: 560px; font-weight: 400; margin-bottom: 38px;
}
.hero-desc em {
  font-style: normal; font-weight: 500; color: var(--text-primary);
  background: linear-gradient(transparent 62%, color-mix(in srgb, var(--accent-color) 16%, transparent) 0);
  padding: 0 .08em; border-radius: 2px;
}
.hero-cta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

/* ── SECTION HEAD ────────────────────────────────────── */
.section { border-bottom: 1px solid var(--border-primary); padding: 84px 28px; position: relative; }
.section-inner { max-width: 1280px; margin: 0 auto; }
.section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 40px; }
.section-title { font-family: var(--f-sans); font-weight: 700; font-size: clamp(26px, 4vw, 40px); letter-spacing: -.02em; color: var(--text-primary); line-height: 1.1; }
.section-title .mk { color: var(--accent-color); border-bottom: 2px solid var(--border-hover); padding-bottom: 2px; }
.section-tag { font-family: var(--f-mono); font-size: 12px; font-weight: 500; letter-spacing: .08em; text-transform: uppercase; color: var(--text-dim); border: 1px solid var(--border-primary); background: var(--bg-card); padding: 6px 12px; border-radius: var(--r-3); }

/* ── STATS (卡片式) ──────────────────────────────────── */
.stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.stat-cell {
  background: var(--bg-card);
  border: 1px solid var(--border-primary);
  border-radius: var(--r-4);
  padding: 30px 24px; position: relative; overflow: hidden;
  transition: transform .18s var(--ease), border-color .18s, box-shadow .18s;
}
.stat-cell::after {
  content: ''; position: absolute; top: 0; left: -120%; width: 55%; height: 100%;
  background: linear-gradient(105deg, transparent, rgba(255,255,255,.22), transparent);
  transform: skewX(-18deg); transition: left .6s var(--ease); opacity: 0;
}
.stat-cell:hover::after { left: 150%; opacity: 1; }
.stat-cell:hover { transform: translateY(-2px); border-color: var(--border-hover); box-shadow: 0 10px 24px -16px rgba(0,0,0,.22); }
.stat-num { font-family: var(--f-mono); font-weight: 700; font-size: clamp(30px, 5vw, 52px); line-height: 1; color: var(--text-primary); margin-bottom: 12px; display: block; letter-spacing: -.02em; }
.stat-label { font-family: var(--f-mono); font-size: 12px; letter-spacing: .04em; text-transform: uppercase; color: var(--text-dim); font-weight: 500; }

/* ── FEATURES (卡片式) ───────────────────────────────── */
.feat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.feat-card {
  background: var(--bg-card);
  border: 1px solid var(--border-primary);
  border-radius: var(--r-4);
  padding: 24px; position: relative; overflow: hidden;
  transition: transform .18s var(--ease), border-color .18s, box-shadow .18s;
}
.feat-card::after {
  content: ''; position: absolute; top: 0; left: -120%; width: 55%; height: 100%;
  background: linear-gradient(105deg, transparent, rgba(255,255,255,.20), transparent);
  transform: skewX(-18deg); transition: left .6s var(--ease); opacity: 0;
}
.feat-card:hover::after { left: 150%; opacity: 1; }
.feat-card:hover { transform: translateY(-2px); border-color: var(--border-hover); box-shadow: 0 12px 26px -16px rgba(0,0,0,.24); }
.feat-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.feat-tag { font-family: var(--f-mono); font-size: 11px; font-weight: 500; letter-spacing: .04em; padding: 3px 10px; border-radius: 99px; border: 1px solid var(--border-secondary); color: var(--text-dim); transition: border-color .15s, color .15s; }
.feat-card:hover .feat-tag { border-color: var(--accent-color); color: var(--accent-color); }
.feat-ico { width: 40px; height: 40px; color: var(--accent-color); background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--r-3); display: grid; place-items: center; transition: background .15s, transform .15s; }
.feat-card:hover .feat-ico { background: var(--bg-hover); }
.feat-title { font-family: var(--f-sans); font-size: 16px; font-weight: 700; color: var(--text-primary); letter-spacing: -.01em; line-height: 1.25; margin-bottom: 8px; }
.feat-desc { font-size: 13.5px; line-height: 1.75; color: var(--text-secondary); font-weight: 400; }

/* ── CTA ─────────────────────────────────────────────── */
.cta-section { padding: 0; border-bottom: 1px solid var(--border-primary); }
.cta-card { background: var(--bg-card); border-top: 1px solid var(--border-primary); padding: 90px 28px 100px; position: relative; overflow: hidden; }
.cta-card::before {
  content: ''; position: absolute; top: -90px; right: -90px;
  width: 260px; height: 260px; border-radius: 50%;
  border: 1px solid var(--border-primary);
  background: radial-gradient(circle at center, var(--bg-secondary), transparent 70%);
  z-index: 0;
}
.cta-inner { position: relative; z-index: 1; max-width: 1280px; margin: 0 auto; }
.cta-label { display: inline-flex; align-items: center; gap: 10px; font-family: var(--f-mono); font-size: 12px; font-weight: 500; letter-spacing: .12em; text-transform: uppercase; color: var(--text-dim); border: 1px solid var(--border-secondary); background: var(--bg-primary); border-radius: var(--r-3); padding: 7px 12px; margin-bottom: 22px; }
.cta-title { font-family: var(--f-sans); font-weight: 700; font-size: clamp(34px, 7vw, 64px); line-height: 1.04; letter-spacing: -.02em; color: var(--text-primary); margin-bottom: 22px; max-width: 860px; }
.cta-title .mk { color: var(--accent-color); border-bottom: 3px solid var(--border-hover); padding-bottom: 2px; }
.cta-sub { font-size: 16px; color: var(--text-secondary); font-weight: 400; line-height: 1.75; max-width: 480px; margin-bottom: 34px; }
.cta-actions { display: flex; gap: 12px; flex-wrap: wrap; }

/* ── FOOTER ──────────────────────────────────────────── */
footer { background: var(--bg-card); color: var(--text-primary); padding: 26px 28px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; max-width: 1280px; margin: 0 auto; border-bottom: 1px solid var(--border-primary); }
.footer-brand { font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 10px; letter-spacing: .01em; }
.footer-brand .sq { width: 12px; height: 12px; border-radius: 3px; background: var(--accent-color); border: 1px solid var(--border-hover); opacity: .85; }
.footer-copy { font-family: var(--f-mono); font-size: 11.5px; color: var(--text-dim); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.footer-copy a { color: var(--text-dim); transition: color .12s; display: inline-flex; align-items: center; gap: 4px; }
.footer-copy a:hover { color: var(--accent-color); }
.footer-copy img { opacity: .7; border-radius: 2px; }

/* ── TOAST ───────────────────────────────────────────── */
#toast { position: fixed; bottom: 24px; right: 24px; z-index: 999; display: flex; flex-direction: column; gap: 10px; }
.toast-item { background: var(--bg-card); color: var(--text-primary); padding: 12px 18px; font-size: 14px; font-family: var(--f-sans); font-weight: 500; border: 1px solid var(--border-secondary); border-radius: var(--r-4); max-width: 320px; box-shadow: 0 12px 28px -14px rgba(0,0,0,.25); animation: toastIn .28s var(--ease) forwards; }
.toast-item::before { content: '> '; color: var(--accent-color); font-family: var(--f-mono); }
.toast-item.out { animation: toastOut .22s var(--ease) forwards; }
@keyframes toastIn { from { opacity: 0; transform: translate(20px, 0); } to { opacity: 1; transform: none; } }
@keyframes toastOut { from { opacity: 1; transform: none; } to { opacity: 0; transform: translate(20px, 0); } }

/* ── ENTER ANIMATIONS ────────────────────────────────── */
.reveal { opacity: 0; transform: translateY(18px); transition: opacity .6s var(--ease), transform .6s var(--ease); }
.reveal.visible { opacity: 1; transform: none; }

/* ── RESPONSIVE ──────────────────────────────────────── */
@media (max-width: 960px) {
  .feat-grid { grid-template-columns: repeat(2, 1fr); }
  .stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
  #topbar { padding: 0 16px; }
  .logo-sub { display: none; }
  .feat-grid { grid-template-columns: 1fr; }
  .stats { grid-template-columns: 1fr; }
  .section { padding: 56px 18px; }
  .hero-inner { padding: 70px 20px 90px; }
  .cta-card { padding: 64px 18px 76px; }
  footer { flex-direction: column; align-items: flex-start; gap: 12px; padding: 22px 18px; }
  .hero-desc { font-size: 15px; }
  .ann-bubble { width: 288px; left: -8px; }
  .ann-bubble::before { left: 8px; }
}
</style>
</head>
<body>

<!-- ══ TOPBAR ════════════════════════════════════════════ -->
<header id="topbar">
  <div class="logo-wrap">
    <a href="/" class="logo-mark" onclick="event.preventDefault(); toggleAnnBubble()">
      <img class="logo-block" src="https://fohok.xin/img/fohok-2.png" alt="文谶驿站">
      <span class="logo-text">
        <span class="logo-char">文谶驿站</span>
        <span class="logo-sub">Open-Fohok</span>
      </span>
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

  <button class="theme-toggle" id="themeToggle" title="切换主题" aria-label="切换日间/夜间主题">
    <svg class="ico-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
    <svg class="ico-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/></svg>
  </button>

  <a href="/download.php" class="topbar-app-icon" title="下载 App">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
      <rect x="5" y="2" width="14" height="20" rx="1"/>
      <line x1="12" y1="17" x2="12.01" y2="17" stroke-linecap="round"/>
    </svg>
    <span class="app-icon-dot"></span>
  </a>

  <?php if ($isLoggedIn): ?>
  <div class="dropdown">
    <button onclick="toggleDropdown('userMenu')" class="dropdown-trigger" aria-label="用户菜单">
      <img src="<?= htmlspecialchars($user['picture'] ?? 'https://ui-avatars.com/api/?name='.urlencode($user['name']??'U').'&background=f5f5f5&color=171717') ?>" alt="avatar">
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
  <a href="/login.php" class="btn btn-primary btn-sm">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
    登录
  </a>
  <?php endif; ?>
</header>

<!-- ══ HERO ══════════════════════════════════════════════ -->
<section class="hero">
  <div class="hero-inner">

    <span class="hero-eyebrow reveal">
      <span class="dot"></span>
      Open Source Platform · Est. 2025
    </span>

    <h1 class="hero-title reveal" style="transition-delay:.06s">
      <span class="l2"><span class="marker">文谶</span>驿站</span>
      <span class="l2">开发者<span class="marker-pink">开放仓库</span></span>
    </h1>

    <p class="hero-sub reveal" style="transition-delay:.12s">
      Developer Open Repository
    </p>

    <p class="hero-desc reveal" style="transition-delay:.18s">
      一个为<em>独立开发者</em>打造的开源项目展示空间。发布你的作品，探索他人的创造，<em>连接</em>志同道合的人。
    </p>

    <div class="hero-cta reveal" style="transition-delay:.24s">
      <a href="/home.php" class="btn btn-primary">
        进入广场
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="13 6 19 12 13 18"/></svg>
      </a>
      <?php if (!$isLoggedIn): ?>
      <a href="/login.php" class="btn btn-ghost">
        登录 / 注册
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══ STATS ══════════════════════════════════════════════ -->
<section class="section">
  <div class="section-inner">
    <div class="section-head reveal">
      <h2 class="section-title">站内<span class="mk">统计</span></h2>
      <span class="section-tag">statistics</span>
    </div>

    <div class="stats reveal" style="transition-delay:.08s">
      <div class="stat-cell">
        <span class="stat-num" id="stat-projects">—</span>
        <span class="stat-label">Projects / 项目</span>
      </div>
      <div class="stat-cell">
        <span class="stat-num" id="stat-developers">—</span>
        <span class="stat-label">Developers / 开发者</span>
      </div>
      <div class="stat-cell">
        <span class="stat-num" id="stat-approved">—</span>
        <span class="stat-label">Approved / 已收录</span>
      </div>
      <div class="stat-cell">
        <span class="stat-num">∞</span>
        <span class="stat-label">Possibilities / 可能</span>
      </div>
    </div>
  </div>
</section>

<!-- ══ FEATURES ══════════════════════════════════════════ -->
<section class="section">
  <div class="section-inner">
    <div class="section-head reveal">
      <h2 class="section-title">本站<span class="mk">特性</span></h2>
      <span class="section-tag">Index · 目录</span>
    </div>

    <div class="feat-grid reveal" style="transition-delay:.08s">
      <div class="feat-card">
        <div class="feat-head">
          <span class="feat-tag">No. 01</span>
          <div class="feat-ico">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="14"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="13" y2="13"/></svg>
          </div>
        </div>
        <h3 class="feat-title">项目展示</h3>
        <p class="feat-desc">优雅地呈现你的开源项目，版本、简介、链接、联系方式，一目了然。</p>
      </div>

      <div class="feat-card">
        <div class="feat-head">
          <span class="feat-tag">No. 02</span>
          <div class="feat-ico">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l2.5 5 5.5.8-4 3.9.9 5.5L12 15.8l-4.9 2.6.9-5.5-4-3.9 5.5-.8z"/></svg>
          </div>
        </div>
        <h3 class="feat-title">人工审核</h3>
        <p class="feat-desc">所有项目经过严格审核，保证内容质量，杜绝垃圾信息干扰。</p>
      </div>

      <div class="feat-card">
        <div class="feat-head">
          <span class="feat-tag">No. 03</span>
          <div class="feat-ico">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
          </div>
        </div>
        <h3 class="feat-title">Account安全</h3>
        <p class="feat-desc">统一文谶账户 OAuth 登录，支持扫码登录与站内通知同步，安全便捷</p>
      </div>

      <div class="feat-card">
        <div class="feat-head">
          <span class="feat-tag">No. 04</span>
          <div class="feat-ico">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 14 8 9 13 15 17 10 21 14"/><line x1="3" y1="19" x2="21" y2="19"/></svg>
          </div>
        </div>
        <h3 class="feat-title">实时更新</h3>
        <p class="feat-desc">随时提交和修改项目信息，保持作品版本与描述始终处于最新状态。</p>
      </div>

      <div class="feat-card">
        <div class="feat-head">
          <span class="feat-tag">No. 05</span>
          <div class="feat-ico">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="12" height="16"/><rect x="15" y="11" width="6" height="9"/></svg>
          </div>
        </div>
        <h3 class="feat-title">开发者友好</h3>
        <p class="feat-desc">简洁无干扰的界面设计，让你专注于作品本身，而非繁琐的操作流程。</p>
      </div>

      <div class="feat-card">
        <div class="feat-head">
          <span class="feat-tag">No. 06</span>
          <div class="feat-ico">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="12" r="3"/><circle cx="18" cy="6" r="3"/><circle cx="18" cy="18" r="3"/><line x1="8.5" y1="10.5" x2="15.5" y2="7.5"/><line x1="8.5" y1="13.5" x2="15.5" y2="16.5"/></svg>
          </div>
        </div>
        <h3 class="feat-title">连接创作者</h3>
        <p class="feat-desc">通过项目展示页面，结识志同道合的独立开发者，共建开源社区。</p>
      </div>
    </div>
  </div>
</section>

<!-- ══ CTA ════════════════════════════════════════════════ -->
<section class="cta-section">
  <div class="cta-card">
    <div class="cta-inner">
      <div class="reveal">
        <span class="cta-label">加入我们 / Join</span>
        <h2 class="cta-title">
          让更多人<br><span class="mk">看见</span>你的作品
        </h2>
        <p class="cta-sub">
          每一个开源项目都值得被发现。在文谶驿站发布你的作品，今天就开始。
        </p>
        <div class="cta-actions">
          <?php if ($isLoggedIn): ?>
          <a href="/home.php" class="btn btn-primary">
            进入项目广场
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="13 6 19 12 13 18"/></svg>
          </a>
          <?php else: ?>
          <a href="/login.php" class="btn btn-primary">
            立即登录
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="13 6 19 12 13 18"/></svg>
          </a>
          <a href="/home.php" class="btn btn-ghost">浏览广场</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ FOOTER ════════════════════════════════════════════ -->
<footer>
  <span class="footer-brand"><span class="sq"></span>文谶驿站</span>
  <p class="footer-copy">
    © <?= date('Y') ?> 文谶驿站 · 开发者开放仓库 — A <a href="https://www.xgorb.cn">X-GORB Studio</a> Brand
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
<div id="toast"></div>

<script>
/* Theme toggle */
(function(){
  var btn = document.getElementById('themeToggle');
  if (btn) btn.addEventListener('click', function(){
    var doc = document.documentElement;
    var next = doc.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    doc.setAttribute('data-theme', next);
    localStorage.setItem('fohok_theme', next);
  });
})();

/* Dropdown */
function toggleDropdown(id) {
  document.getElementById(id).classList.toggle('show');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
});

/* Reveal on scroll */
const revealEls = document.querySelectorAll('.reveal');
const revealIO = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); revealIO.unobserve(e.target); }});
}, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
revealEls.forEach(el => revealIO.observe(el));

/* Trigger hero reveals immediately */
window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.hero .reveal').forEach((el, i) => {
    setTimeout(() => el.classList.add('visible'), i * 90);
  });
  loadStats();
  preloadAnnouncement();
});

/* Stats counter */
function animateCount(el, target, duration = 1100) {
  const start = performance.now();
  const step = (now) => {
    const p = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(ease * target);
    if (p < 1) requestAnimationFrame(step);
    else el.textContent = target;
  };
  requestAnimationFrame(step);
}

async function loadStats() {
  try {
    const res = await fetch('/api/project.php?action=stats');
    const data = await res.json();
    if (data.ok && data.stats) {
      animateCount(document.getElementById('stat-projects'),   data.stats.total_projects    || 0);
      animateCount(document.getElementById('stat-developers'), data.stats.total_developers  || 0);
      animateCount(document.getElementById('stat-approved'),   data.stats.approved_projects || 0);
    }
  } catch(e) { console.warn('stats:', e); }
}

/* ── ANNOUNCEMENT BUBBLE ──────────────────────────────── */
const ANN_SEVERITY = {
  notice:    { label: '通知', cls: 'ann-notice' },
  info:      { label: '提示', cls: 'ann-info' },
  important: { label: '重要', cls: 'ann-important' },
  warning:   { label: '警告', cls: 'ann-warning' }
};
let annData = null;       /* cached announcement data */
let annLoaded = false;    /* whether fetch completed (success or fail) */
let annLoading = false;   /* whether fetch is currently in-flight */

async function preloadAnnouncement() {
  /* Guard: don't fetch again if already loaded or currently loading */
  if (annLoaded || annLoading) return;
  annLoading = true;

  try {
    const res = await fetch('https://fohok.xin/api/announcement.php?id=2');
    const data = await res.json();
    annLoaded = true;
    annLoading = false;

    if (!data.ok || !data.title) return;
    annData = data;

    /* Show badge only if NOT dismissed this session */
    if (!sessionStorage.getItem('ann_dismissed_1')) {
      document.getElementById('logo-badge').classList.add('show');
    }

    /* Auto-show bubble 1s after page load, if not dismissed */
    setTimeout(() => {
      if (!sessionStorage.getItem('ann_dismissed_1') && !openBubble && annData) {
        toggleAnnBubble();
      }
    }, 1000);

    /* If the bubble is already open (user clicked before load finished), fill it now */
    const bubble = document.getElementById('ann-bubble');
    if (bubble.classList.contains('show')) renderAnnBubble();
  } catch(e) {
    annLoaded = true;
    annLoading = false;
    console.warn('announcement:', e);
  }
}

function renderAnnBubble() {
  if (!annData) return;
  const sev = ANN_SEVERITY[annData.severity] || ANN_SEVERITY.info;

  const bubble    = document.getElementById('ann-bubble');
  const sevTag    = document.getElementById('ann-sev-tag'); /* may be null if removed from HTML */
  const titleEl   = document.getElementById('ann-title');
  const contentEl = document.getElementById('ann-content');

  /* Apply severity class (color), preserve show state */
  bubble.className = 'ann-bubble ' + sev.cls;
  if (openBubble) bubble.classList.add('show');

  /* sev-tag element was removed from HTML — guard against null */
  if (sevTag) sevTag.textContent = sev.label;

  titleEl.textContent = annData.title;
  contentEl.innerHTML = annData.content || '';
}

let openBubble = false;
function toggleAnnBubble() {
  const bubble = document.getElementById('ann-bubble');
  openBubble = !bubble.classList.contains('show');

  if (openBubble) {
    /* If data not yet loaded, show placeholder and trigger fetch */
    if (!annLoaded && !annData) {
      document.getElementById('ann-title').textContent = 'Please be patient...';
      document.getElementById('ann-content').textContent = '';
      preloadAnnouncement(); /* safe: guarded by annLoading flag */
    }
    /* If data is ready, render it */
    if (annData) renderAnnBubble();
    bubble.classList.add('show');
    /* Hide the badge once opened */
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
  /* Remember dismissal so badge doesn't reappear on reload,
     but data stays cached — user can still click logo to view */
  sessionStorage.setItem('ann_dismissed_1', '1');
  document.getElementById('logo-badge').classList.remove('show');
}

/* Click outside bubble to close */
document.addEventListener('click', e => {
  if (!e.target.closest('.logo-wrap')) {
    const bubble = document.getElementById('ann-bubble');
    if (bubble.classList.contains('show')) {
      bubble.classList.remove('show');
      openBubble = false;
    }
  }
});

/* Toast */
function showToast(msg) {
  const wrap = document.getElementById('toast');
  const el = document.createElement('div');
  el.className = 'toast-item';
  el.textContent = msg;
  wrap.appendChild(el);
  setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 240); }, 3500);
}

/* Read ?error= from URL (PHP fallback for non-PHP environments) */
(function() {
  const p = new URLSearchParams(location.search);
  const err = p.get('error');
  if (err) showToast(err);
})();
</script>
</body>
</html>