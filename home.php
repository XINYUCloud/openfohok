<?php
require_once __DIR__ . '/bootstrap.php';
$user       = optionalLogin();
$isLoggedIn = $user !== null;
$userId     = $isLoggedIn ? ($user['user_id'] ?? $user['id']) : null;
$isAdmin    = $isLoggedIn && in_array($user['email'], ADMIN_EMAILS);
$unreadCount = $isLoggedIn ? getUnreadCount($userId) : 0;
$ua         = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile   = (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone|BlackBerry|webOS|Opera Mini/i', $ua);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="alternate" type="application/rss+xml" title="文谶驿站 RSS" href="/rss.php">
<meta property="og:title" content="文谶驿站 · 开发者开放仓库">
<meta property="og:description" content="一个为独立开发者打造的开源项目展示空间。
发布你的作品，探索他人的创造，连接志同道合的人。">
<meta property="og:image" content="https://fohok.xin/img/fohok-2.png">
<meta property="og:url" content="https://fohok.xin/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="文谶驿站 · 开发者开放仓库">
<title>文谶驿站 · 开发者开放仓库</title>
<script src="https://cdn.tailwindcss.com">
</script>
<script>
tailwind.config = {
  theme: {
    extend: {
      fontFamily: { mono:['"JetBrains Mono"','monospace'], sans:['"Noto Sans SC"','sans-serif'] },
      colors: {
        dark:{950:'#050505',900:'#0a0a0a',800:'#111111',700:'#1a1a1a',600:'#222222',500:'#2a2a2a'},
        accent:{DEFAULT:'#e5e5e5',dim:'#888',muted:'#444'}
      }
    }
  }
}

</script>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Noto+Sans+SC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  
  /* 日间主题（默认） */
  :root {
    --bg-primary: #fafafa;
    --bg-secondary: #ffffff;
    --bg-tertiary: #f5f5f5;
    --bg-hover: #f0f0f0;
    --bg-modal: #ffffff;
    --bg-card: #ffffff;
    --bg-input: #f5f5f5;
    --bg-badge-green: #f0fdf4;
    --bg-badge-blue: #eff6ff;
    --bg-badge-yellow: #fefce8;
    --bg-notice: #f0f9ff;
    --text-primary: #171717;
    --text-secondary: #404040;
    --text-dim: #737373;
    --text-muted: #a3a3a3;
    --text-inverse: #fafafa;
    --border-primary: #e5e5e5;
    --border-secondary: #d4d4d4;
    --border-hover: #a3a3a3;
    --border-input: #d4d4d4;
    --border-input-focus: #737373;
    --border-badge-green: #bbf7d0;
    --border-badge-blue: #bfdbfe;
    --border-badge-yellow: #fde68a;
    --border-notice: #bae6fd;
    --text-badge-green: #166534;
    --text-badge-blue: #1e40af;
    --text-badge-yellow: #854d0e;
    --text-notice: #075985;
    --accent-color: #171717;
    --accent-hover: #404040;
    --danger-color: #dc2626;
    --danger-bg: #fef2f2;
    --danger-border: #fecaca;
    --overlay-bg: rgba(0, 0, 0, 0.5);
    --shadow-card: 0 1px 3px rgba(0,0,0,0.08);
    --scrollbar-track: #f5f5f5;
    --scrollbar-thumb: #d4d4d4;
  }

  /* 夜间主题 */
  @media (prefers-color-scheme: dark) {
    :root {
      --bg-primary: #050505;
      --bg-secondary: #0a0a0a;
      --bg-tertiary: #111111;
      --bg-hover: #1a1a1a;
      --bg-modal: #0a0a0a;
      --bg-card: #0f0f0f;
      --bg-input: #111111;
      --bg-badge-green: #052e16;
      --bg-badge-blue: #0f172a;
      --bg-badge-yellow: #1a1a00;
      --bg-notice: #0d1117;
      --text-primary: #f5f5f5;
      --text-secondary: #d4d4d4;
      --text-dim: #a3a3a3;
      --text-muted: #737373;
      --text-inverse: #0a0a0a;
      --border-primary: #1a1a1a;
      --border-secondary: #222222;
      --border-hover: #404040;
      --border-input: #222222;
      --border-input-focus: #525252;
      --border-badge-green: #14532d;
      --border-badge-blue: #1e3a5f;
      --border-badge-yellow: #3a3000;
      --border-notice: #1e3a5f;
      --text-badge-green: #4ade80;
      --text-badge-blue: #60a5fa;
      --text-badge-yellow: #fbbf24;
      --text-notice: #7dd3fc;
      --accent-color: #e5e5e5;
      --accent-hover: #ffffff;
      --danger-color: #f87171;
      --danger-bg: #1a0808;
      --danger-border: #3a1515;
      --overlay-bg: rgba(0, 0, 0, 0.85);
      --shadow-card: none;
      --scrollbar-track: #0a0a0a;
      --scrollbar-thumb: #333333;
    }
  }

  body{
    background: var(--bg-primary);
    color: var(--text-primary);
    font-family: 'Noto Sans SC', sans-serif;
    min-height: 100vh;
  }
  ::-webkit-scrollbar{width:4px;height:4px}
  ::-webkit-scrollbar-track{background:var(--scrollbar-track)}
  ::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb)}

  #topbar{
    position:fixed;top:0;left:0;right:0;height:56px;
    background: var(--bg-secondary);
    border-bottom:1px solid var(--border-primary);
    z-index:50;display:flex;align-items:center;padding:0 20px;gap:12px;
    backdrop-filter:blur(12px);
  }

  #sidebar{
    position:fixed;top:0;left:-300px;width:300px;height:100vh;
    background:var(--bg-secondary);
    border-right:1px solid var(--border-primary);
    z-index:60;transition:left .3s cubic-bezier(.4,0,.2,1);overflow-y:auto;
  }
  #sidebar.open{left:0}
  #overlay{
    position:fixed;inset:0;background:var(--overlay-bg);
    z-index:55;opacity:0;pointer-events:none;transition:opacity .3s;
  }
  #overlay.show{opacity:1;pointer-events:all}

  .proj-card{
    background:var(--bg-card);
    border:1px solid var(--border-primary);
    border-radius:8px;padding:20px;cursor:pointer;transition:all .2s;
    position:relative;overflow:hidden;display:flex;flex-direction:column;
    box-shadow: var(--shadow-card);
  }
  .proj-card::before{
    content:'';position:absolute;inset:0;
    background:linear-gradient(135deg,transparent 60%,rgba(128,128,128,0.03));
    pointer-events:none;
  }
  .proj-card:hover{
    border-color:var(--border-hover);
    transform:translateY(-2px);
    background:var(--bg-hover);
  }
  .proj-card .card-content{flex:1;display:flex;flex-direction:column}
  .proj-card .card-desc{min-height:54px;line-height:1.5}
  .proj-card .card-footer{
    margin-top:auto;padding-top:12px;
    border-top:1px solid var(--border-primary);
    display:flex;align-items:center;gap:8px;
  }

  .modal-bg{
    position:fixed;inset:0;background:var(--overlay-bg);
    z-index:100;display:flex;align-items:center;justify-content:center;
    padding:20px;opacity:0;pointer-events:none;transition:opacity .25s;
  }
  .modal-bg.show{opacity:1;pointer-events:all}
  .modal{
    background:var(--bg-modal);
    border:1px solid var(--border-secondary);
    border-radius:12px;width:100%;max-width:620px;max-height:90vh;
    overflow-y:auto;transform:scale(.97) translateY(8px);transition:transform .25s;
  }
  .modal-bg.show .modal{transform:scale(1) translateY(0)}

  .inp{
    width:100%;background:var(--bg-input);
    border:1px solid var(--border-input);
    color:var(--text-primary);
    padding:10px 14px;border-radius:6px;font-size:14px;
    font-family:inherit;transition:border-color .2s;outline:none;
  }
  .inp:focus{border-color:var(--border-input-focus)}
  .inp::placeholder{color:var(--text-muted)}
  .inp-with-btn{display:flex;gap:0}
  .inp-with-btn .inp{border-radius:6px 0 0 6px;border-right:none}
  .inp-btn{
    padding:10px 16px;font-size:13px;border:1px solid var(--border-input);
    border-radius:0 6px 6px 0;background:var(--bg-tertiary);
    color:var(--text-primary);cursor:pointer;white-space:nowrap;
    font-family:inherit;transition:background .15s;
  }
  .inp-btn:hover{background:var(--bg-hover)}
  label{
    font-size:12px;color:var(--text-dim);
    letter-spacing:.05em;text-transform:uppercase;
    margin-bottom:4px;display:block;
  }

  .btn{
    display:inline-flex;align-items:center;justify-content:center;
    gap:6px;padding:10px 20px;border-radius:6px;font-size:14px;
    font-weight:500;cursor:pointer;transition:all .2s;border:none;
    font-family:inherit;text-decoration:none;
  }
  .btn-primary{
    background:var(--accent-color);color:var(--text-inverse);
  }
  .btn-primary:hover{background:var(--accent-hover)}
  .btn-ghost{
    background:transparent;color:var(--text-dim);
    border:1px solid var(--border-secondary);
  }
  .btn-ghost:hover{
    color:var(--text-primary);border-color:var(--border-hover);
  }
  .btn-danger{
    background:transparent;color:var(--danger-color);
    border:1px solid var(--danger-border);
    font-size:12px;padding:6px 12px;border-radius:5px;
  }
  .btn-danger:hover{background:var(--danger-bg);border-color:var(--danger-color)}

  .like-btn{
    display:inline-flex;align-items:center;gap:4px;
    padding:4px 8px;border-radius:5px;font-size:12px;
    font-family:'JetBrains Mono',monospace;cursor:pointer;
    transition:all .2s;border:1px solid var(--border-secondary);
    background:transparent;color:var(--text-muted);
    user-select:none;flex-shrink:0;
  }
  .like-btn:hover{color:var(--danger-color);border-color:var(--danger-border)}
  .like-btn.liked{color:var(--danger-color);border-color:var(--danger-border);background:var(--danger-bg)}
  .like-btn svg{transition:transform .15s}
  .like-btn:hover svg{transform:scale(1.1)}
  .like-btn.liked svg{fill:var(--danger-color)}
  .like-btn .like-count{min-width:14px;text-align:center}

  #toast{
    position:fixed;bottom:24px;right:24px;z-index:999;
    display:flex;flex-direction:column;gap:8px;
  }
  .toast-item{
    background:var(--bg-card);border:1px solid var(--border-secondary);
    color:var(--text-primary);
    padding:12px 18px;border-radius:8px;font-size:14px;
    max-width:320px;animation:tIn .3s ease forwards;
    display:flex;align-items:center;gap:8px;
    box-shadow: var(--shadow-card);
  }
  .toast-item.out{animation:tOut .3s ease forwards}
  .toast-item.success::before{content:'✓';color:#4ade80;font-weight:700}
  .toast-item.error::before{content:'✕';color:var(--danger-color);font-weight:700}
  @keyframes tIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
  @keyframes tOut{from{opacity:1;transform:translateX(20px)}to{opacity:0;transform:translateX(20px)}}

  .badge{
    display:inline-block;padding:2px 8px;border-radius:99px;
    font-size:11px;font-family:'JetBrains Mono',monospace;font-weight:500;
  }
  .badge-approved{
    background:var(--bg-badge-green);color:var(--text-badge-green);
    border:1px solid var(--border-badge-green);
  }
  .badge-guest{
    background:var(--bg-badge-blue);color:var(--text-badge-blue);
    border:1px solid var(--border-badge-blue);
  }
  .badge-current{
    background:var(--bg-badge-yellow);color:var(--text-badge-yellow);
    border:1px solid var(--border-badge-yellow);
    font-size:10px;padding:2px 7px;
  }
  .tech-badge{
    display:inline-block;font-size:10px;padding:2px 8px;border-radius:4px;
    font-family:'JetBrains Mono',monospace;font-weight:500;
    background:var(--bg-tertiary);color:var(--text-dim);
    border:1px solid var(--border-primary);
  }
  .tech-badges{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px}

  /* 分享面板 */
  .share-panel-wrap{display:flex;flex-direction:column;align-items:center;gap:20px}
  .share-panel-qr{display:flex;flex-direction:column;align-items:center;gap:8px;flex-shrink:0}
  .share-panel-right{flex:1;display:flex;flex-direction:column;gap:16px;min-width:0;width:100%}
  @media(min-width:500px){
    .share-panel-wrap{flex-direction:row;align-items:flex-start}
    .share-panel-right{width:auto}
  }
  .share-code-box{position:relative;background:var(--bg-tertiary);border:1px solid var(--border-primary);border-radius:6px;padding:10px 12px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--text-secondary);word-break:break-all;line-height:1.6}
  .share-code-box-text{max-height:100px;overflow-y:auto;padding-right:28px}
  .share-copy-btn{position:absolute;top:6px;right:6px;font-size:10px;padding:2px 8px;border-radius:4px;border:1px solid var(--border-secondary);background:var(--bg-card);color:var(--text-dim);cursor:pointer;transition:all .15s;font-family:inherit}
  .share-copy-btn:hover{color:var(--text-primary);border-color:var(--border-hover)}
  .share-label{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-family:'JetBrains Mono',monospace}
  .share-qr-img-wrap{width:160px;height:160px;border-radius:8px;border:1px solid var(--border-primary);background:#fff;padding:8px}
  .share-qr-img-wrap img{width:100%;height:100%;display:block}
  .share-qr-tip{font-size:11px;color:var(--text-muted);font-family:'JetBrains Mono',monospace}

  .dropdown{position:relative}
  .dropdown-menu{
    position:absolute;right:0;top:calc(100%+8px);
    background:var(--bg-card);border:1px solid var(--border-secondary);
    border-radius:8px;min-width:150px;overflow:hidden;
    z-index:200;opacity:0;pointer-events:none;
    transform:translateY(-8px);transition:all .2s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  }
  .dropdown-menu.show{opacity:1;pointer-events:all;transform:translateY(0)}
  .dropdown-item{
    display:flex;align-items:center;gap:10px;
    padding:10px 16px;font-size:14px;color:var(--text-secondary);
    cursor:pointer;transition:background .15s;text-decoration:none;
  }
  .dropdown-item:hover{background:var(--bg-hover);color:var(--text-primary)}
  .dropdown-item.danger{color:var(--danger-color)}
  .dropdown-item.danger:hover{background:var(--danger-bg)}

  .projects-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
    gap:16px;
  }
  body.locked{overflow:hidden}

  .author-link{
    color:var(--text-dim);text-decoration:none;
    transition:color .15s;font-size:12px;
  }
  .author-link:hover{color:var(--text-primary)}

  .steps{display:flex;align-items:center;gap:0;margin-bottom:20px}
  .step-ind{
    display:flex;align-items:center;gap:6px;
    font-size:12px;color:var(--text-muted);transition:color .2s;
  }
  .step-ind.active{color:var(--text-primary)}
  .step-ind.done{color:#4ade80}
  .step-num{
    width:20px;height:20px;border-radius:50%;
    border:1px solid currentColor;display:flex;
    align-items:center;justify-content:center;
    font-size:10px;font-family:'JetBrains Mono',monospace;
    flex-shrink:0;transition:all .2s;
  }
  .step-ind.done .step-num{
    background:var(--bg-badge-green);border-color:var(--border-badge-green);
  }
  .step-line{flex:1;height:1px;background:var(--border-primary);margin:0 8px}

  .guest-notice{
    background:var(--bg-notice);border:1px solid var(--border-notice);
    border-radius:8px;padding:12px 14px;font-size:12px;
    color:var(--text-notice);line-height:1.7;
  }
  .guest-notice strong{color:var(--text-notice)}

  .step-panel{display:none}.step-panel.active{display:block}

  .submit-tab{
    padding:6px 14px;font-size:12px;border:none;background:transparent;
    color:var(--text-dim);cursor:pointer;border-radius:5px;transition:all .15s;
    white-space:nowrap;font-weight:500;
  }
  .submit-tab.active{background:var(--bg-card);color:var(--text-primary);box-shadow:0 0 0 1px var(--border-hover)}
  .submit-tab:not(.active):hover{color:var(--text-primary)}
  .submit-panel{display:none}.submit-panel.active{display:block}

  .optional-toggle{
    display:flex;align-items:center;gap:6px;width:100%;padding:8px 0;
    background:none;border:none;color:var(--text-dim);font-size:13px;font-family:inherit;
    cursor:pointer;transition:color .15s;
  }
  .optional-toggle:hover{color:var(--text-primary)}
  .optional-toggle svg{transition:transform .2s}
  .optional-toggle.open svg{transform:rotate(180deg)}
  .optional-fields{overflow:hidden;transition:max-height .3s ease}

  #captcha-wrap{min-height:60px}

  .share-btn{
    display:inline-flex;align-items:center;justify-content:center;
    width:32px;height:32px;border-radius:6px;
    background:var(--bg-tertiary);border:1px solid var(--border-secondary);
    color:var(--text-dim);cursor:pointer;transition:all .2s;flex-shrink:0;
  }
  .share-btn:hover{
    background:var(--bg-hover);border-color:var(--border-hover);
    color:var(--text-primary);
  }
  .share-btn.copied{
    background:var(--bg-badge-green);border-color:var(--border-badge-green);
    color:#4ade80;
  }

  /* ── Session Modal ─────────────────────────────── */
  .session-card{
    background:var(--bg-card);border:1px solid var(--border-primary);
    border-radius:8px;padding:14px 16px;
    display:flex;align-items:flex-start;gap:12px;
    transition:border-color .2s;position:relative;
  }
  .session-card.is-current{border-color:var(--border-badge-yellow)}
  .session-card:hover{border-color:var(--border-hover)}
  .session-icon{
    width:36px;height:36px;border-radius:8px;
    background:var(--bg-tertiary);border:1px solid var(--border-secondary);
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;color:var(--text-dim);
  }
  .session-info{flex:1;min-width:0}
  .session-device{
    font-size:13px;color:var(--text-secondary);font-weight:500;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  }
  .session-meta{
    font-size:11px;color:var(--text-muted);margin-top:3px;
    font-family:'JetBrains Mono',monospace;display:flex;flex-wrap:wrap;gap:6px;
  }
  .session-meta span{display:flex;align-items:center;gap:3px}
  .session-revoke-btn{flex-shrink:0;align-self:center}

  #sessions-list{display:flex;flex-direction:column;gap:10px}
  #sessions-loading{
    text-align:center;padding:40px 0;
    color:var(--text-muted);font-size:13px;
  }

  /* ── Page footer ──────────────────────────────── */
  #page-footer{
    text-align:center;
    padding:32px 20px 40px;
    font-family:'JetBrains Mono',monospace;
    font-size:11px;
    letter-spacing:.04em;
    color:var(--text-muted);
  }
  #page-footer a{
    color:var(--text-muted);
    text-decoration:none;
    border-bottom:1px solid var(--border-secondary);
    transition:color .15s,border-color .15s;
  }
  #page-footer a:hover{
    color:var(--text-dim);
    border-color:var(--border-hover);
  }

  /* 其他辅助样式 */
  .animate-spin{
    animation: spin 1s linear infinite;
  }
  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
  .text-red-500{color:var(--danger-color)}
  .flex{display:flex}
  .flex-1{flex:1}
  .hidden{display:none}
  .block{display:block}
  .w-full{width:100%}
  .text-center{text-align:center}
  .text-sm{font-size:0.875rem}
  .text-xs{font-size:0.75rem}
  .font-medium{font-weight:500}
  .font-mono{font-family:'JetBrains Mono',monospace}
  .truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .rounded{border-radius:4px}
  .rounded-lg{border-radius:8px}
  .rounded-full{border-radius:9999px}
  .mb-4{margin-bottom:1rem}
  .mb-8{margin-bottom:2rem}
  .mt-1{margin-top:0.25rem}
  .mt-0\.5{margin-top:0.125rem}
  .p-5{padding:1.25rem}
  .p-6{padding:1.5rem}
  .px-3{padding-left:0.75rem;padding-right:0.75rem}
  .px-6{padding-left:1.5rem;padding-right:1.5rem}
  .py-3{padding-top:0.75rem;padding-bottom:0.75rem}
  .py-10{padding-top:2.5rem;padding-bottom:2.5rem}
  .py-24{padding-top:6rem;padding-bottom:6rem}
  .gap-3{gap:0.75rem}
  .gap-4{gap:1rem}
  .space-y-4 > * + * {margin-top:1rem}
  .space-y-5 > * + * {margin-top:1.25rem}
  .border-b{border-bottom-width:1px}
  .border-t{border-top-width:1px}
  .object-cover{object-fit:cover}
  .overflow-hidden{overflow:hidden}
  .whitespace-pre-wrap{white-space:pre-wrap}
  .leading-relaxed{line-height:1.625}
  .tracking-widest{letter-spacing:0.1em}
  .transition-colors{transition-property:color,background-color,border-color;transition-duration:0.15s}
  .transition-opacity{transition-property:opacity;transition-duration:0.15s}
  .transition-all{transition-property:all;transition-duration:0.2s}
  .hover\:underline:hover{text-decoration:underline}
  .underline{text-decoration:underline}
</style>
</head>
<body>

<!-- ══ TOPBAR ══════════════════════════════════════════════ -->
<header id="topbar">
  <button onclick="toggleSidebar()" class="w-9 h-9 flex items-center justify-center rounded hover:bg-dark-700 transition-colors" style="color:var(--text-dim);">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <span class="font-mono text-sm tracking-widest flex-1 hidden sm:block" style="color:var(--text-dim);">OPEN-FOHOK</span>
  <div class="flex-1 sm:flex-none"></div>

  <?php if ($isLoggedIn): ?>
  <a href="/search.php" class="w-9 h-9 flex items-center justify-center rounded hover:bg-dark-700 transition-colors" style="color:var(--text-dim);" title="搜索项目">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  </a>
  <a href="/messages.php" class="w-9 h-9 flex items-center justify-center rounded hover:bg-dark-700 transition-colors" style="color:var(--text-dim);position:relative;" title="站内信">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    <?php if ($unreadCount > 0): ?>
    <span style="position:absolute;top:2px;right:2px;min-width:16px;height:16px;background:#3b82f6;color:#fff;border-radius:99px;font-size:10px;font-family:'JetBrains Mono',monospace;display:flex;align-items:center;justify-content:center;padding:0 3px;font-weight:700;line-height:1"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
    <?php endif; ?>
  </a>
  <div class="dropdown">
    <button onclick="toggleDropdown('userMenu')" class="flex items-center gap-2 rounded-full hover:opacity-80 transition-opacity">
      <img src="<?= htmlspecialchars($user['picture'] ?? 'https://ui-avatars.com/api/?name='.urlencode($user['name']??'U').'&background=222&color=fff') ?>"
           class="w-8 h-8 rounded-full border object-cover" style="border-color:var(--border-secondary);" alt="avatar">
    </button>
    <div id="userMenu" class="dropdown-menu">
      <a href="/profile.php" class="dropdown-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        个人中心
      </a>
      <?php if ($isMobile): ?>
      <button onclick="openQRScanModal();toggleDropdown('userMenu')" class="dropdown-item w-full text-left" style="border:none;background:none;font-family:inherit">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        扫一扫
      </button>
      <?php endif; ?>
      <?php if ($isAdmin): ?>
      <a href="/admin/" class="dropdown-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        管理后台
      </a>
      <?php endif; ?>
      <a href="/logout.php" class="dropdown-item danger">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        退出登录
      </a>
    </div>
  </div>
  <?php else: ?>
  <a href="/search.php" class="w-9 h-9 flex items-center justify-center rounded hover:bg-dark-700 transition-colors" style="color:var(--text-dim);" title="搜索项目">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  </a>
  <a href="/login.php" class="btn btn-ghost" style="padding:7px 16px;font-size:13px">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    登录
  </a>
  <?php endif; ?>
</header>

<!-- ══ OVERLAY & SIDEBAR ════════════════════════════════════ -->
<div id="overlay" onclick="closeSidebar()"></div>

<aside id="sidebar">
  <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary);">
    <span class="font-mono text-xs tracking-widest" style="color:var(--text-dim);">MENU</span>
    <button onclick="closeSidebar()" class="w-7 h-7 flex items-center justify-center rounded hover:bg-dark-700 transition-colors" style="color:var(--text-dim);">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>

  <?php if ($isLoggedIn): ?>
  <div class="p-5 border-b" style="border-color:var(--border-primary);">
    <div class="flex items-center gap-3 mb-3">
      <img src="<?= htmlspecialchars($user['picture'] ?? 'https://ui-avatars.com/api/?name='.urlencode($user['name']??'U').'&background=222&color=fff') ?>"
           class="w-12 h-12 rounded-full border object-cover flex-shrink-0" style="border-color:var(--border-secondary);" alt="avatar">
      <div class="min-w-0">
        <p class="text-sm font-medium truncate" style="color:var(--text-primary);"><?= htmlspecialchars($user['name'] ?? '匿名用户') ?></p>
        <p class="text-xs truncate mt-0.5" style="color:var(--text-dim);"><?= htmlspecialchars($user['email'] ?? '') ?></p>
      </div>
    </div>
    <?php if ($isAdmin): ?><span class="badge badge-approved">尊敬的管理账号</span><?php endif; ?>
  </div>
  <div class="p-5">
    <button onclick="openSubmitModal();closeSidebar()" class="btn btn-primary w-full">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      发布项目
    </button>
  </div>
  <nav class="px-3 pb-3">
    <a href="/" class="dropdown-item rounded-lg" style="padding:10px 12px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      刷新驿站
    </a>
    <a href="/profile.php" class="dropdown-item rounded-lg" style="padding:10px 12px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
      个人中心
    </a>
    <?php if ($isAdmin): ?>
    <a href="/admin/" class="dropdown-item rounded-lg" style="padding:10px 12px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      管理后台
    </a>
    <?php endif; ?>
    <!-- 登录会话管理入口 -->
    <button onclick="openSessionModal();closeSidebar()" class="dropdown-item rounded-lg w-full text-left" style="padding:10px 12px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/><circle cx="17" cy="8" r="1.5" fill="currentColor" stroke="none"/><line x1="3" y1="8" x2="13" y2="8"/><line x1="3" y1="12" x2="10" y2="12"/></svg>
      登录会话管理
    </button>
    <a href="/logout.php" class="dropdown-item rounded-lg danger" style="padding:10px 12px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      退出登录
    </a>
  </nav>
  <?php else: ?>
  <div class="p-5 border-b" style="border-color:var(--border-primary);">
    <p class="text-xs mb-4 leading-relaxed" style="color:var(--text-dim);">无需注册/登录即可发布项目，注册/登录后可管理你发布的开源作品。</p>
    <button onclick="openSubmitModal();closeSidebar()" class="btn btn-primary w-full mb-2">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      发布项目
    </button>
    <a href="/login.php" class="btn btn-ghost w-full" onclick="closeSidebar()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      使用文谶账户登录
    </a>
  </div>
  <nav class="px-3 py-3">
    <a href="/" class="dropdown-item rounded-lg" style="padding:10px 12px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      刷新驿站
    </a>
  </nav>
  <?php endif; ?>
</aside>

<!-- ══ MAIN ═════════════════════════════════════════════════ -->
<main style="margin-top:56px;padding:28px 24px;max-width:1280px;margin-left:auto;margin-right:auto">
  <div class="flex items-center justify-between mb-8">
    <div>
      <h2 class="text-xl font-medium" style="color:var(--text-primary);">项目广场</h2>
      <p class="text-xs mt-1 font-mono" style="color:var(--text-dim);">OPEN SOURCE PROJECTS</p>
    </div>
    <button onclick="openSubmitModal()" class="btn btn-primary">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      发布项目
    </button>
  </div>

  <div id="projects-grid" class="projects-grid"></div>
  <div id="load-more" class="text-center py-10 hidden">
    <button onclick="loadProjects()" class="btn btn-ghost">载入更多数据</button>
  </div>
  <div id="empty-state" class="text-center py-24 hidden">
    <div class="text-6xl mb-4 opacity-20">◎</div>
    <p style="color:var(--text-dim);" class="text-sm">暂无已发布项目</p>
  </div>
  <div id="loading-state" class="text-center py-24">
    <div class="inline-block w-6 h-6 border-2 rounded-full animate-spin" style="border-color:var(--border-secondary);border-top-color:var(--text-dim);"></div>
  </div>
</main>

<!-- ══ PAGE FOOTER ══════════════════════════════════════════ -->
<footer id="page-footer">
<a href="https://beian.miit.gov.cn" target="_blank" style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;color:inherit;">
    <img src="https://fohok.xin/img/icp.png" alt="ICP备案图标" style="width:20px;height:auto;">
    闽ICP备2026024151号
</a>
<span>|</span>
<a href="https://beian.mps.gov.cn/#/query/webSearch?code=35058302351237" target="_blank" style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;color:inherit;">
    <img src="https://fohok.xin/img/mps.png" alt="公安备案图标" style="width:20px;height:auto;">
    闽公网安备35058302351237号
</a>
</footer>

<!-- ══ SUBMIT MODAL ═════════════════════════════════════════ -->
<div id="submitModal" class="modal-bg" onclick="handleModalBgClick(event,'submitModal')">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-6 border-b" style="border-color:var(--border-primary);">
      <div class="flex items-center gap-2">
        <div class="flex items-center gap-1" style="background:var(--bg-tertiary);border:1px solid var(--border-primary);border-radius:6px;padding:2px">
          <button type="button" id="submit-tab-manual" class="submit-tab active" onclick="switchSubmitTab('manual')">手动发布</button>
          <?php if ($isLoggedIn): ?>
          <button type="button" id="submit-tab-import" class="submit-tab" onclick="switchSubmitTab('import')">从仓库导入</button>
          <?php endif; ?>
        </div>
      </div>
      <button onclick="closeModal('submitModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-dark-700 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- 手动发布面板 -->
    <div id="submit-panel-manual" class="submit-panel active">
    <form id="submitForm" onsubmit="handleSubmit(event)">
      <?php if (!$isLoggedIn): ?>
      <div class="steps px-6 pt-5">
        <div class="step-ind active" id="si-1"><div class="step-num">1</div><span>项目信息</span></div>
        <div class="step-line"></div>
        <div class="step-ind" id="si-2"><div class="step-num">2</div><span>提交者</span></div>
        <div class="step-line"></div>
        <div class="step-ind" id="si-3"><div class="step-num">3</div><span>验证</span></div>
      </div>
      <?php endif; ?>

      <!-- Step 1: 项目信息 -->
      <div class="step-panel active px-6 pb-6 pt-5 space-y-4" id="panel-1">
        <div class="grid grid-cols-2 gap-4">
          <div><label>项目名称 <span class="text-red-500">*</span></label><input name="name" id="f-name" class="inp" placeholder="My Awesome Project" required></div>
          <div><label>版本 <span class="text-red-500">*</span></label><input name="version" id="f-version" class="inp" placeholder="v1.0.0" required></div>
        </div>
        <div><label>项目地址 <span class="text-red-500">*</span></label><input name="project_url" id="f-url" type="url" class="inp" placeholder="https://github.com/..." required></div>
        <div><label>项目介绍 <span class="text-red-500">*</span></label><textarea name="description" id="f-desc" class="inp" rows="3" placeholder="简要描述您的项目..." style="resize:vertical" required></textarea></div>
        <div><label>发布日期 <span class="text-red-500">*</span></label><input name="release_date" id="f-date" type="date" class="inp" required></div>

        <!-- 更多选项（折叠） -->
        <div>
          <button type="button" id="f-optional-toggle" class="optional-toggle" onclick="toggleOptionalFields()">
            <svg id="f-optional-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            更多选项
          </button>
          <div id="f-optional-fields" class="optional-fields" style="display:none">
            <div class="space-y-4 pt-3">
              <div><label>技术栈</label><input name="tech_stack" id="f-tech-stack" class="inp" placeholder="例如: php, html, javascript, java, python..."></div>
              <div><label>自定义封面图链接</label><input name="cover_url" id="f-cover" class="inp" placeholder="https://example.com/cover.png"></div>
              <div><label>开发者网站</label><input name="developer_website" id="f-devsite" type="url" class="inp" placeholder="https://..."></div>
              <div>
                <label>联系方式 <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-3 mt-1">
                  <input name="contact_email" id="f-cemail" type="email" class="inp" placeholder="邮箱">
                  <input name="contact_phone" id="f-cphone" class="inp" placeholder="电话">
                  <input name="contact_qq" id="f-cqq" class="inp" placeholder="QQ">
                  <input name="contact_wechat" id="f-cwechat" class="inp" placeholder="微信">
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php if ($isLoggedIn): ?>
        <div><label>帮我们战胜机器人 :) <span class="text-red-500">*</span></label><div id="captcha-wrap" class="mt-1"></div></div>
        <div class="flex gap-3 pt-2">
          <button type="button" onclick="closeModal('submitModal')" class="btn btn-ghost flex-1">取消</button>
          <button type="submit" id="submitBtn" class="btn btn-primary flex-1" disabled>提交审核</button>
        </div>
        <?php else: ?>
        <div class="flex gap-3 pt-2">
          <button type="button" onclick="closeModal('submitModal')" class="btn btn-ghost flex-1">取消</button>
          <button type="button" onclick="goStep2()" class="btn btn-primary flex-1">下一步 →</button>
        </div>
        <?php endif; ?>
      </div>

      <?php if (!$isLoggedIn): ?>
      <!-- Step 2: 提交者信息 -->
      <div class="step-panel px-6 pb-6 pt-2 space-y-4" id="panel-2">
        <div class="guest-notice">
          <strong>游客提交说明</strong><br>
          审核通过后项目将展示在广场，但<strong>无法在线编辑或删除</strong>。<br>
          如需管理项目，请先 <a href="/login.php" class="underline hover:text-white transition-colors">登录 / 注册</a></a>。
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div><label>你的昵称 <span class="text-red-500">*</span></label><input name="guest_name" id="f-gname" class="inp" placeholder="随便起个名字"></div>
          <div><label>联系邮箱 <span class="text-red-500">*</span></label><input name="guest_email" id="f-gemail" type="email" class="inp" placeholder="用于接收审核结果"></div>
        </div>
        <p class="text-xs" style="color:var(--text-dim);">邮箱仅用于发送审核结果通知，不会公开展示。</p>
        <div class="flex gap-3 pt-2">
          <button type="button" onclick="goStep(1)" class="btn btn-ghost flex-1">← 上一步</button>
          <button type="button" onclick="goStep3()" class="btn btn-primary flex-1">下一步 →</button>
        </div>
      </div>

      <!-- Step 3: 验证 -->
      <div class="step-panel px-6 pb-6 pt-2 space-y-4" id="panel-3">
        <div><label>帮我们战胜机器人 :) <span class="text-red-500">*</span></label><div id="captcha-wrap" class="mt-1"></div></div>
        <div class="flex gap-3 pt-2">
          <button type="button" onclick="goStep(2)" class="btn btn-ghost flex-1">← 上一步</button>
          <button type="submit" id="submitBtn" class="btn btn-primary flex-1" disabled>提交审核</button>
        </div>
      </div>
      <?php endif; ?>
    </form>
    </div><!-- /submit-panel-manual -->

    <!-- 从仓库导入面板 -->
    <div id="submit-panel-import" class="submit-panel">
      <div class="p-5 space-y-4">
        <p class="text-xs" style="color:var(--text-dim);">粘贴 GitHub 或 Gitee 仓库地址，自动获取项目信息并填充到发布表单。</p>
        <div>
          <label>仓库地址</label>
          <div class="inp-with-btn">
            <input id="import-repo-url" class="inp" placeholder="https://github.com/user/repo 或 https://gitee.com/user/repo"
                   onkeydown="if(event.key==='Enter')fetchRepoInfo()">
            <button class="inp-btn" onclick="fetchRepoInfo()">获取</button>
          </div>
        </div>
        <div id="import-loading" style="display:none;text-align:center;padding:12px;color:var(--text-dim);">
          <div class="inline-block w-5 h-5 border-2 rounded-full animate-spin" style="border-color:var(--border-secondary);border-top-color:var(--text-dim);"></div>
          <span class="ml-2 text-sm">正在获取仓库信息...</span>
        </div>
        <div id="import-preview" style="display:none">
          <div class="p-4 rounded-lg" style="background:var(--bg-tertiary);border:1px solid var(--border-primary)">
            <div class="flex items-center gap-2 mb-2">
              <span class="text-sm font-medium" style="color:var(--text-primary);" id="import-preview-name"></span>
              <span class="text-xs font-mono" style="color:var(--text-muted);" id="import-preview-lang"></span>
            </div>
            <p class="text-xs mb-2" style="color:var(--text-dim);" id="import-preview-desc"></p>
            <div class="flex items-center gap-4 text-xs" style="color:var(--text-muted);">
              <span id="import-preview-stars"></span>
              <span id="import-preview-platform"></span>
            </div>
          </div>
          <button onclick="fillImportToForm()" class="btn btn-primary w-full mt-3">填充到发布表单</button>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ══ DETAIL MODAL ════════════════════════════════════════ -->
<div id="detailModal" class="modal-bg" onclick="handleModalBgClick(event,'detailModal')">
  <div class="modal" onclick="event.stopPropagation()" style="max-width:700px">
    <div class="flex items-center justify-between p-6 border-b" style="border-color:var(--border-primary);">
      <div class="flex items-center gap-3">
        <button id="square-share-back-btn" onclick="closeSquareShareModal()" class="w-7 h-7 flex items-center justify-center rounded transition-colors" style="display:none;color:var(--text-dim);border:1px solid var(--border-secondary);background:transparent" title="返回项目详情">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <h3 id="detail-title" class="font-medium" style="color:var(--text-primary);">项目详情</h3>
      </div>
      <div class="flex items-center gap-2">
        <button id="detail-open-btn" onclick="event.stopPropagation();window.open('/prodeta.php?id='+currentDetailId,'_blank')" class="share-btn" title="在新标签页打开" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </button>
        <button id="detail-report-btn" onclick="event.stopPropagation();openReportModal()" class="share-btn" title="举报该项目" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </button>
        <button id="detail-share-btn" onclick="openSquareShareModal()" class="share-btn" title="分享项目" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        </button>
        <button onclick="closeModal('detailModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-dark-700 transition-colors" style="color:var(--text-dim);">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </div>
    <div id="detail-body" class="p-6"></div>
    <div id="square-share-body" style="display:none"></div>
  </div>
</div>

<!-- ══ REPORT MODAL ════════════════════════════════════════ -->
<div id="reportModal" class="modal-bg" onclick="handleModalBgClick(event,'reportModal')">
  <div class="modal" onclick="event.stopPropagation()" style="max-width:460px">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <span style="font-size:13px;font-weight:500;color:var(--text-primary);display:flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#f87171"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        举报项目
      </span>
      <button onclick="closeModal('reportModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-dark-700 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="p-5">
      <p style="font-size:13px;color:var(--text-dim);margin-bottom:16px;line-height:1.6">
        您正在对 <strong style="color:var(--text-primary)" id="report-project-name">-</strong> 进行举报...
      </p>
      <form id="reportForm" onsubmit="submitReport(event)">
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:12px;font-weight:500;color:var(--text-dim);margin-bottom:6px">举报类型 *</label>
          <select id="report-type" required style="width:100%;padding:8px 12px;font-size:13px;border:1px solid var(--border-primary);border-radius:6px;background:var(--bg-card);color:var(--text-primary)">
            <option value="">请选择举报类型</option>
            <option value="spam">垃圾信息</option>
            <option value="violation">违规内容</option>
            <option value="copyright">侵权问题</option>
            <option value="misleading">虚假误导</option>
            <option value="other">其他问题</option>
          </select>
        </div>
        <div style="margin-bottom:14px" id="report-email-row">
          <label style="display:block;font-size:12px;font-weight:500;color:var(--text-dim);margin-bottom:6px">举报结果通知邮箱()可选)</label>
          <input type="email" id="report-email" placeholder="我们将通过此邮箱通知你处理结果" style="width:100%;padding:8px 12px;font-size:13px;border:1px solid var(--border-primary);border-radius:6px;background:var(--bg-card);color:var(--text-primary)">
        </div>
        <div style="margin-bottom:16px">
          <label style="display:block;font-size:12px;font-weight:500;color:var(--text-dim);margin-bottom:6px">其他补充(可选)</label>
          <textarea id="report-reason" rows="3" placeholder="请补充更多细节..." style="width:100%;padding:8px 12px;font-size:13px;border:1px solid var(--border-primary);border-radius:6px;background:var(--bg-card);color:var(--text-primary);resize:vertical"></textarea>
        </div>
        <div style="display:flex;gap:10px">
          <button type="button" onclick="closeModal('reportModal')" style="flex:1;padding:9px;font-size:13px;border:1px solid var(--border-secondary);border-radius:6px;background:transparent;color:var(--text-dim);cursor:pointer">取消</button>
          <button type="submit" style="flex:1;padding:9px;font-size:13px;border:none;border-radius:6px;background:#dc2626;color:#fff;cursor:pointer;font-weight:500">提交举报</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ SESSION MODAL ═══════════════════════════════════════ -->
<div id="sessionModal" class="modal-bg" onclick="handleModalBgClick(event,'sessionModal')">
  <div class="modal" onclick="event.stopPropagation()" style="max-width:660px">
    <div class="flex items-center justify-between p-6 border-b" style="border-color:var(--border-primary);">
      <div>
        <h3 class="font-medium" style="color:var(--text-primary);">登录会话管理</h3>
        <p class="text-xs mt-0.5 font-mono" style="color:var(--text-dim);">ACTIVE SESSIONS</p>
      </div>
      <button onclick="closeModal('sessionModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-dark-700 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="p-6">
      <div id="sessions-loading" style="display:none">
        <div class="inline-block w-5 h-5 border-2 rounded-full animate-spin" style="border-color:var(--border-secondary);border-top-color:var(--text-dim);"></div>
        <span style="margin-left:10px;color:var(--text-muted);font-size:13px">Please be patient...</span>
      </div>
      <div id="sessions-list"></div>
    </div>
  </div>
</div>

<?php if ($isMobile): ?>
<!-- ══ QR SCAN MODAL ════════════════════════════════════════ -->
<div id="qrScanModal" class="modal-bg" onclick="handleModalBgClick(event,'qrScanModal')">
  <div class="modal" onclick="event.stopPropagation()" style="max-width:460px">
    <div class="flex items-center justify-between p-6 border-b" style="border-color:var(--border-primary);">
      <div>
        <h3 class="font-medium" style="color:var(--text-primary);">扫一扫</h3>
        <p class="text-xs mt-0.5 font-mono" style="color:var(--text-dim);">SCAN QR CODE</p>
      </div>
      <button onclick="closeQRScanModal()" class="w-7 h-7 flex items-center justify-center rounded hover:bg-dark-700 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="p-6">
      <!-- 摄像头预览 -->
      <div id="qrscan-camera-wrap" style="display:none">
        <div id="qrscan-reader" style="width:100%;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border-secondary)"></div>
        <p class="text-center text-xs mt-3" style="color:var(--text-dim)">请对准 PC 端登录二维码进行扫描识别</p>
      </div>
      <!-- 权限被拒绝 -->
      <div id="qrscan-denied" style="display:none;text-align:center;padding:20px 0">
        <div style="width:56px;height:56px;border-radius:50%;background:var(--danger-bg);border:1px solid var(--danger-border);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--danger-color)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <p style="font-size:14px;color:var(--text-secondary);margin-bottom:6px">摄像头权限被拒绝</p>
        <p style="font-size:12px;color:var(--text-dim);line-height:1.7">请在浏览器设置中允许访问摄像头，<br>然后刷新页面重试。</p>
      </div>
      <!-- 加载中 -->
      <div id="qrscan-loading" style="text-align:center;padding:40px 0">
        <div class="inline-block w-5 h-5 border-2 rounded-full animate-spin" style="border-color:var(--border-secondary);border-top-color:var(--text-dim);"></div>
        <p class="text-xs mt-3" style="color:var(--text-dim)">正在检查摄像头权限...</p>
      </div>
      <!-- 确认面板 -->
      <div id="qrscan-confirm" style="display:none">
        <div style="text-align:center;margin-bottom:20px">
          <div style="width:56px;height:56px;border-radius:50%;background:var(--bg-badge-green);border:1px solid var(--border-badge-green);display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--text-badge-green)" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <p style="font-size:15px;font-weight:500;color:var(--text-primary);margin-bottom:4px">扫码成功</p>
          <p style="font-size:12px;color:var(--text-dim)">确认使用当前账号在 PC 端登录？</p>
        </div>
        <div id="qrscan-user-card" style="background:var(--bg-tertiary);border:1px solid var(--border-primary);border-radius:var(--radius);padding:14px;margin-bottom:20px;display:none">
          <div style="display:flex;align-items:center;gap:12px">
            <img id="qrscan-confirm-avatar" src="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:1px solid var(--border-secondary)" alt="">
            <div>
              <div style="font-size:11px;color:var(--text-dim);margin-bottom:2px">当前登录账号</div>
              <div id="qrscan-confirm-name" style="font-size:14px;font-weight:500;color:var(--text-primary)"></div>
            </div>
          </div>
        </div>
        <button class="btn btn-primary w-full" id="qrscan-confirm-btn" onclick="doQRScanConfirm()">确认登录</button>
        <button class="btn btn-ghost w-full" onclick="resetQRScan()" style="margin-top:10px">重新扫描</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div id="toast"></div>
<script src="https://static.geetest.com/v4/gt4.js">
</script>
<?php if ($isMobile): ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js">
</script>
<?php endif; ?>
<script>
const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
<?php if ($isMobile): ?>
window.__currentUser = <?= $isLoggedIn ? json_encode(['id'=>$user['id'],'name'=>$user['name'],'picture'=>$user['picture']]) : 'null' ?>;
<?php endif; ?>

let currentPage     = 1;
let totalProjects   = 0;
let captchaObj      = null;
let captchaData     = null;
let currentDetailId = null;
let openModalCount  = 0;

document.addEventListener('DOMContentLoaded', () => {
  loadProjects(true);
  initCaptcha();
});

// ── Sidebar ──────────────────────────────────────────────────
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('show');
  document.body.classList.toggle('locked');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('show');
  if (openModalCount === 0) document.body.classList.remove('locked');
}

// ── Dropdown ─────────────────────────────────────────────────
function toggleDropdown(id) { document.getElementById(id).classList.toggle('show'); }
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
});

// ── Modal ─────────────────────────────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.add('show');
  openModalCount++;
  document.body.classList.add('locked');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('show');
  openModalCount = Math.max(0, openModalCount - 1);
  if (openModalCount === 0) document.body.classList.remove('locked');
}
function handleModalBgClick(e, id) {
  if (e.target === e.currentTarget) closeModal(id);
}

function openSubmitModal() {
  if (!IS_LOGGED_IN) goStep(1);
  switchSubmitTab('manual');
  openModal('submitModal');
}

// ── 更多选项折叠 ──────────────────────────────────────────
function toggleOptionalFields() {
  const fields = document.getElementById('f-optional-fields');
  const toggle = document.getElementById('f-optional-toggle');
  const isOpen = fields.style.display !== 'none';
  if (isOpen) {
    fields.style.display = 'none';
    toggle.classList.remove('open');
  } else {
    fields.style.display = 'block';
    toggle.classList.add('open');
  }
}

// ── Step nav（游客）─────────────────────────────────────────
function goStep(n) {
  document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
  document.getElementById(`panel-${n}`).classList.add('active');
  for (let i = 1; i <= 3; i++) {
    const el = document.getElementById(`si-${i}`);
    if (!el) continue;
    el.classList.remove('active','done');
    if (i < n) { el.classList.add('done'); el.querySelector('.step-num').textContent='✓'; }
    else { el.querySelector('.step-num').textContent=i; if(i===n) el.classList.add('active'); }
  }
}
function goStep2() {
  const checks = [
    {el:document.getElementById('f-name'),label:'项目名称'},
    {el:document.getElementById('f-version'),label:'版本'},
    {el:document.getElementById('f-url'),label:'项目地址'},
    {el:document.getElementById('f-desc'),label:'项目介绍'},
    {el:document.getElementById('f-date'),label:'发布日期'},
  ];
  for (const {el,label} of checks) {
    if (!el.value.trim()) { showToast(`请填写${label}`,'error'); el.focus(); return; }
  }
  try { new URL(document.getElementById('f-url').value); } catch { showToast('项目地址格式不正确','error'); return; }
  // 联系方式至少一项
  if (!hasContact()) { showToast('请至少填写一项联系方式','error'); return; }
  goStep(2);
}
function goStep3() {
  const gname  = document.getElementById('f-gname').value.trim();
  const gemail = document.getElementById('f-gemail').value.trim();
  if (!gname)  { showToast('请填写昵称','error'); return; }
  if (!gemail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(gemail)) { showToast('请填写正确的邮箱地址','error'); return; }
  goStep(3);
}

// ── Load projects ─────────────────────────────────────────────
async function loadProjects(reset = false) {
  if (reset) { currentPage = 1; document.getElementById('projects-grid').innerHTML = ''; }
  document.getElementById('loading-state').classList.remove('hidden');
  document.getElementById('empty-state').classList.add('hidden');
  try {
    const res  = await fetch(`/api/project.php?action=list&page=${currentPage}`);
    const data = await res.json();
    document.getElementById('loading-state').classList.add('hidden');
    if (!data.ok) return;
    totalProjects = data.total;
    const grid = document.getElementById('projects-grid');
    if (data.projects.length === 0 && currentPage === 1) {
      document.getElementById('empty-state').classList.remove('hidden');
      return;
    }
    data.projects.forEach(p => {
      const card = document.createElement('div');
      card.className = 'proj-card';
      card.onclick = () => openDetail(p.id);
      const guestTag = p.is_guest
        ? '<span class="badge badge-guest" style="font-size:9px;padding:1px 5px">游客</span>'
        : '';
      const authorHtml = p.is_guest
        ? `<span style="font-size:12px;color:var(--text-muted)">${esc(p.author_name||'匿名')}</span>`
        : `<a href="/user.php?id=${p.user_id}" class="author-link" onclick="event.stopPropagation()">${esc(p.author_name||'匿名')}</a>`;
      card.innerHTML = `
        <div class="card-content">
          <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2 flex-1 min-w-0 mr-2">
              <h4 class="text-sm font-medium leading-tight truncate" style="color:var(--text-primary);">${esc(p.name)}</h4>
              ${guestTag}
            </div>
            <span class="text-xs font-mono flex-shrink-0" style="color:var(--text-dim);">${esc(p.version)}</span>
          </div>
          <p class="text-xs leading-relaxed card-desc" style="color:var(--text-dim);">${esc(truncateDesc(p.description))}</p>
        </div>
        <div class="card-footer">
          <img src="${esc(p.author_pic||'')}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(p.author_name||'U')}&background=222&color=fff&size=24'" class="w-5 h-5 rounded-full border object-cover" style="border-color:var(--border-secondary);" alt="">
          ${authorHtml}
          ${IS_LOGGED_IN ? `<button class="like-btn ${p.is_liked?'liked':''}" onclick="event.stopPropagation();toggleLike(${p.id},this)" title="${p.is_liked?'取消点赞':'点赞'}"><svg width="13" height="13" viewBox="0 0 24 24" fill="${p.is_liked?'currentColor':'none'}" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span class="like-count">${p.like_count||0}</span></button>` : `<span class="like-btn" style="cursor:default" onclick="event.stopPropagation()" title="登录后即可点赞"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span class="like-count">${p.like_count||0}</span></span>`}
          <span class="ml-auto text-xs font-mono" style="color:var(--text-muted);">${formatDate(p.created_at)}</span>
        </div>
      `;
      grid.appendChild(card);
    });
    currentPage++;
    document.getElementById('load-more').classList.toggle('hidden', (currentPage-1)*12 >= totalProjects);
  } catch(err) {
    document.getElementById('loading-state').classList.add('hidden');
    showToast('加载失败，请刷新重试','error');
  }
}

// ── Detail ─────────────────────────────────────────────────
async function openDetail(id) {
  currentDetailId = id;
  currentReportProjectId = id;
  currentReportProjectName = '';

  // 重置分享面板状态
  document.getElementById('square-share-body').style.display = 'none';
  document.getElementById('square-share-back-btn').style.display = 'none';
  document.getElementById('detail-body').style.display = '';

  document.getElementById('detail-title').textContent = '项目详情';
  document.getElementById('detail-body').innerHTML = '<div class="text-center py-10"><div class="inline-block w-5 h-5 border-2 rounded-full animate-spin" style="border-color:var(--border-secondary);border-top-color:var(--text-dim);"></div></div>';

  const shareBtn = document.getElementById('detail-share-btn');
  const reportBtn = document.getElementById('detail-report-btn');
  const openBtn = document.getElementById('detail-open-btn');
  // 先隐藏，加载完成后根据归属判断
  reportBtn.style.display = 'none';
  openBtn.style.display = 'inline-flex';
  shareBtn.style.display = 'inline-flex';
  shareBtn.classList.remove('copied');

  openModal('detailModal');
  try {
    const res  = await fetch(`/api/project.php?action=detail&id=${id}`);
    const data = await res.json();
    if (!data.ok) {
      document.getElementById('detail-body').innerHTML = '<p class="text-center text-sm" style="color:var(--text-dim);">加载失败啦•﹏•</p>';
      return;
    }
    const p = data.project;
    document.getElementById('detail-title').textContent = p.name;
    currentReportProjectName = p.name;
    // 登录用户且项目不属于自己时显示举报按钮
    const isOwner = IS_LOGGED_IN && window.__currentUser && p.user_id == window.__currentUser.id;
    reportBtn.style.display = (IS_LOGGED_IN && !isOwner) ? 'inline-flex' : 'none';
    const contacts = [];
    if (p.contact_email)  contacts.push(`<a href="mailto:${esc(p.contact_email)}" class="author-link">${esc(p.contact_email)}</a>`);
    if (p.contact_phone)  contacts.push(esc(p.contact_phone));
    if (p.contact_qq)     contacts.push('QQ: '+esc(p.contact_qq));
    if (p.contact_wechat) contacts.push('微信: '+esc(p.contact_wechat));
    const authorLink = p.is_guest
      ? `<span class="text-xs" style="color:var(--text-dim);">${esc(p.author_name||'匿名游客')}</span>`
      : `<a href="/user.php?id=${p.user_id}" class="author-link">${esc(p.author_name||'匿名')}</a>`;
    const guestBadge = p.is_guest ? '<span class="badge badge-guest" style="font-size:10px">游客提交</span>' : '';
    document.getElementById('detail-body').innerHTML = `
      <div class="space-y-5">
        <div class="flex items-center gap-3 flex-wrap">
          <span class="text-xs font-mono px-2 py-1 rounded" style="background:var(--bg-tertiary);color:var(--text-dim);">${esc(p.version)}</span>
          ${p.release_date?`<span class="text-xs" style="color:var(--text-dim);">${esc(p.release_date)}</span>`:''}
          ${guestBadge}
        </div>
        ${renderTechBadges(p.tech_stack)}
        <div class="flex items-center gap-3">
          ${IS_LOGGED_IN ? `<button class="like-btn ${p.is_liked?'liked':''}" onclick="toggleLike(${p.id},this,'detail')" title="${p.is_liked?'取消点赞':'点赞'}"><svg width="13" height="13" viewBox="0 0 24 24" fill="${p.is_liked?'currentColor':'none'}" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span class="like-count">${p.like_count||0}</span></button>` : `<span class="like-btn" style="cursor:default" onclick="event.stopPropagation()" title="登录后即可点赞"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span class="like-count">${p.like_count||0}</span></span>`}
          <span class="text-xs" style="color:var(--text-muted);">${p.view_count} 次浏览</span>
        </div>
        <p class="text-sm leading-relaxed whitespace-pre-wrap" style="color:var(--text-secondary);">${esc(p.description)}</p>
        <div class="grid gap-3 text-sm">
          <div class="flex items-center gap-3 p-3 rounded-lg border" style="background:var(--bg-tertiary);border-color:var(--border-primary);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-dim);flex-shrink:0;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            <a href="${esc(p.project_url)}" target="_blank" class="hover:underline truncate text-sm" style="color:var(--text-primary);">${esc(p.project_url)}</a>
          </div>
          ${p.developer_website?`<div class="flex items-center gap-3 p-3 rounded-lg border" style="background:var(--bg-tertiary);border-color:var(--border-primary);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-dim);flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <a href="${esc(p.developer_website)}" target="_blank" class="hover:underline hover:text-white truncate text-sm" style="color:var(--text-dim);">${esc(p.developer_website)}</a>
          </div>`:''}
          ${contacts.length?`<div class="p-3 rounded-lg border text-xs" style="background:var(--bg-tertiary);border-color:var(--border-primary);color:var(--text-dim);">${contacts.join(' · ')}</div>`:''}
        </div>
        <div class="flex items-center gap-2 pt-2 border-t" style="border-color:var(--border-primary);">
          <img src="${esc(p.author_pic||'')}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(p.author_name||'U')}&background=222&color=fff&size=32'" class="w-7 h-7 rounded-full border object-cover" style="border-color:var(--border-secondary);" alt="">
          ${authorLink}
          <span class="text-xs ml-1" style="color:var(--text-muted);">· ${formatDate(p.created_at)}</span>
        </div>
      </div>
    `;
  } catch(err) {
    document.getElementById('detail-body').innerHTML = '<p class="text-center text-sm" style="color:var(--text-dim);">加载失败啦•﹏•</p>';
  }
}

// ── Copy Share Link ──────────────────────────────────────────
async function copyShareLink(event) {
  event.stopPropagation();
  if (!currentDetailId) return;
  const shareUrl = `https://fohok.xin/prodeta.php?id=${currentDetailId}`;
  try {
    await navigator.clipboard.writeText(shareUrl);
    const btn = document.getElementById('detail-share-btn');
    btn.classList.add('copied');
    showToast('链接已复制到剪贴板！', 'success');
    setTimeout(() => btn.classList.remove('copied'), 1500);
  } catch {
    const ta = document.createElement('textarea');
    ta.value = shareUrl;
    ta.style.cssText = 'position:fixed;left:-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      const btn = document.getElementById('detail-share-btn');
      btn.classList.add('copied');
      showToast('链接已复制到剪贴板！', 'success');
      setTimeout(() => btn.classList.remove('copied'), 1500);
    } catch { showToast('复制失败，请手动复制链接', 'error'); }
    document.body.removeChild(ta);
  }
}

// ── 导入仓库 ─────────────────────────────────────────────────
let importedRepoData = null;

function switchSubmitTab(tab) {
  document.querySelectorAll('.submit-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.submit-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('submit-tab-' + tab).classList.add('active');
  document.getElementById('submit-panel-' + tab).classList.add('active');
  if (tab === 'import') {
    document.getElementById('import-repo-url').value = '';
    document.getElementById('import-loading').style.display = 'none';
    document.getElementById('import-preview').style.display = 'none';
    importedRepoData = null;
  }
}

async function fetchRepoInfo() {
  const url = document.getElementById('import-repo-url').value.trim();
  if (!url) { showToast('请输入仓库地址', 'error'); return; }

  const loading = document.getElementById('import-loading');
  const preview = document.getElementById('import-preview');
  loading.style.display = 'block';
  preview.style.display = 'none';
  importedRepoData = null;

  try {
    const res = await fetch('/api/project.php?action=import_repo', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'import_repo', url }).toString()
    });
    const data = await res.json();
    loading.style.display = 'none';

    if (!data.ok) {
      showToast(data.msg || '获取仓库信息失败', 'error');
      return;
    }

    importedRepoData = data;
    document.getElementById('import-preview-name').textContent = data.name || '';
    document.getElementById('import-preview-lang').textContent = data.language || '';
    document.getElementById('import-preview-desc').textContent = data.description || '';
    document.getElementById('import-preview-stars').textContent = data.stars ? ('★ ' + data.stars) : '';
    document.getElementById('import-preview-platform').textContent = data.platform || '';
    preview.style.display = 'block';
  } catch (err) {
    loading.style.display = 'none';
    showToast('网络异常，请重试', 'error');
  }
}

function fillImportToForm() {
  if (!importedRepoData) { showToast('请先获取仓库信息', 'error'); return; }
  const d = importedRepoData;
  if (d.name) document.getElementById('f-name').value = d.name;
  if (d.description) document.getElementById('f-desc').value = d.description;
  if (d.project_url) document.getElementById('f-url').value = d.project_url;
  if (d.developer_website) document.getElementById('f-devsite').value = d.developer_website;
  if (d.language) document.getElementById('f-version').value = 'v1.0.0';
  switchSubmitTab('manual');
  showToast('仓库信息已填充，请完善后提交', 'success');
}

// ── 联系方式校验 ──────────────────────────────────────────
function hasContact() {
  return document.getElementById('f-cemail').value.trim()
    || document.getElementById('f-cphone').value.trim()
    || document.getElementById('f-cqq').value.trim()
    || document.getElementById('f-cwechat').value.trim();
}

// ── Submit ─────────────────────────────────────────────────
async function handleSubmit(e) {
  e.preventDefault();
  if (!document.getElementById('f-date').value.trim()) { showToast('请填写发布日期','error'); return; }
  if (!hasContact()) { showToast('请至少填写一项联系方式','error'); return; }
  if (!captchaData) { showToast('请先完成人机验证','error'); return; }
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.textContent = '提交中...';
  try {
    const fd = new FormData(document.getElementById('submitForm'));
    fd.append('action','submit');
    for (const [k,v] of Object.entries(captchaData)) fd.append(k,v);
    const res  = await fetch('/api/project.php',{method:'POST',body:fd});
    const data = await res.json();
    if (data.ok) {
      closeModal('submitModal');
      document.getElementById('submitForm').reset();
      captchaData = null; captchaObj && captchaObj.reset();
      if (!IS_LOGGED_IN) goStep(1);
      document.getElementById('submitBtn').disabled = true;
      showToast(data.msg,'success');
    } else {
      showToast(data.msg||'提交失败','error');
      captchaData = null; captchaObj && captchaObj.reset();
      document.getElementById('submitBtn').disabled = true;
    }
  } catch(err) {
    showToast('网络异常，请重试','error');
  } finally {
    btn.disabled = false;
    if (btn.textContent === '提交中...') btn.textContent = '提交审核';
  }
}

// ── Captcha ─────────────────────────────────────────────────
function initCaptcha() {
  window.initGeetest4({
    captchaId: '<?= CAPTCHA_ID ?>',
    product: 'float',
  }, function(obj) {
    captchaObj = obj;
    obj.appendTo('#captcha-wrap');
    obj.onSuccess(function() {
      captchaData = obj.getValidate();
      document.getElementById('submitBtn').disabled = false;
    });
    obj.onError(function() { showToast('验证码加载失败，请刷新重试','error'); });
    obj.onClose(function() { if (!captchaData) document.getElementById('submitBtn').disabled = true; });
  });
}

// ══ SESSION MANAGEMENT ════════════════════════════════════════

/**
 * 解析 User-Agent 字符串
 */
function parseUA(ua) {
  if (!ua) return { device: '未知设备', os: '', browser: '', icon: 'desktop' };

  const isMobile  = /Mobile|Android|iPhone|iPad|iPod/i.test(ua);
  const isTablet  = /iPad|Tablet/i.test(ua);
  const isAndroid = /Android/i.test(ua);
  const isIOS     = /iPhone|iPad|iPod/i.test(ua);
  const isWin     = /Windows NT/i.test(ua);
  const isMac     = /Macintosh|Mac OS X/i.test(ua);
  const isLinux   = /Linux/i.test(ua);

  // OS
  let os = '';
  if (isIOS) {
    const m = ua.match(/OS ([\d_]+)/);
    os = 'iOS' + (m ? ' ' + m[1].replace(/_/g,'.') : '');
  } else if (isAndroid) {
    const m = ua.match(/Android ([\d.]+)/);
    os = 'Android' + (m ? ' ' + m[1] : '');
  } else if (isWin) {
    const m = ua.match(/Windows NT ([\d.]+)/);
    const ver = {'10.0':'10/11','6.3':'8.1','6.2':'8','6.1':'7','6.0':'Vista','5.1':'XP'}[m&&m[1]] || (m&&m[1]) || '';
    os = 'Windows' + (ver ? ' ' + ver : '');
  } else if (isMac) {
    const m = ua.match(/Mac OS X ([\d_]+)/);
    os = 'macOS' + (m ? ' ' + m[1].replace(/_/g,'.') : '');
  } else if (isLinux) {
    os = 'Linux';
  }

  // Browser
  let browser = '';
  if (/EdgA?\//i.test(ua)) {
    const m = ua.match(/Edg[A]?\/([\d.]+)/i);
    browser = 'Edge' + (m ? ' ' + m[1].split('.')[0] : '');
  } else if (/OPR\//i.test(ua) || /Opera/i.test(ua)) {
    const m = ua.match(/OPR\/([\d.]+)/i);
    browser = 'Opera' + (m ? ' ' + m[1].split('.')[0] : '');
  } else if (/Chrome\//i.test(ua) && !/Chromium/i.test(ua)) {
    const m = ua.match(/Chrome\/([\d.]+)/i);
    browser = 'Chrome' + (m ? ' ' + m[1].split('.')[0] : '');
  } else if (/Firefox\//i.test(ua)) {
    const m = ua.match(/Firefox\/([\d.]+)/i);
    browser = 'Firefox' + (m ? ' ' + m[1].split('.')[0] : '');
  } else if (/Safari\//i.test(ua)) {
    const m = ua.match(/Version\/([\d.]+)/i);
    browser = 'Safari' + (m ? ' ' + m[1].split('.')[0] : '');
  } else {
    browser = '未知浏览器';
  }

  // Device label
  let device = '';
  if (isTablet) device = 'iPad';
  else if (isIOS) device = 'iPhone';
  else if (isAndroid && isMobile) device = 'Android 手机';
  else if (isAndroid) device = 'Android 平板';
  else if (isMac) device = 'Mac';
  else if (isWin) device = 'Windows 电脑';
  else if (isLinux) device = 'Linux 设备';
  else device = '未知设备';

  const icon = isTablet ? 'tablet' : (isMobile ? 'mobile' : 'desktop');
  return { device, os, browser, icon };
}

function deviceIcon(type) {
  if (type === 'mobile') return `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="18" r="1" fill="currentColor" stroke="none"/></svg>`;
  if (type === 'tablet') return `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="2" width="16" height="20" rx="2"/><circle cx="12" cy="17" r="1" fill="currentColor" stroke="none"/></svg>`;
  return `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8M12 18v2"/></svg>`;
}

async function openSessionModal() {
  openModal('sessionModal');
  const listEl    = document.getElementById('sessions-list');
  const loadingEl = document.getElementById('sessions-loading');
  listEl.innerHTML = '';
  loadingEl.style.display = 'flex';
  loadingEl.style.alignItems = 'center';

  try {
    const res  = await fetch('/api/session.php?action=list');
    const data = await res.json();
    loadingEl.style.display = 'none';

    if (!data.ok) {
      listEl.innerHTML = `<p class="text-center text-sm" style="color:var(--text-muted);padding:30px 0">${esc(data.msg||'加载失败')}</p>`;
      return;
    }

    if (!data.sessions || data.sessions.length === 0) {
      listEl.innerHTML = `<p class="text-center text-sm" style="color:var(--text-muted);padding:30px 0">暂无会话记录</p>`;
      return;
    }

    data.sessions.forEach(s => {
      const info    = parseUA(s.user_agent);
      const isCur   = s.is_current;
      const card    = document.createElement('div');
      card.className = 'session-card' + (isCur ? ' is-current' : '');
      card.dataset.sid = s.id;

      const loginTime   = formatDatetime(s.created_at);
      const expireTime  = formatDatetime(s.expires_at);
      const isExpired   = new Date(s.expires_at) < new Date();

      card.innerHTML = `
        <div class="session-icon">${deviceIcon(info.icon)}</div>
        <div class="session-info">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="session-device">${esc(info.device)}</span>
            ${isCur ? '<span class="badge badge-current">当前登录</span>' : ''}
            ${isExpired ? '<span class="badge" style="background:var(--danger-bg);color:var(--danger-color);border-color:var(--danger-border);font-size:10px">已过期</span>' : ''}
          </div>
          <div class="session-meta">
            ${info.browser ? `<span><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>${esc(info.browser)}</span>` : ''}
            ${info.os ? `<span style="color:var(--text-muted)">·</span><span>${esc(info.os)}</span>` : ''}
            ${s.ip_address ? `<span style="color:var(--text-muted)">·</span><span><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>${esc(s.ip_address)}</span>` : ''}
          </div>
          <div class="session-meta" style="margin-top:4px">
            <span><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>登录于 ${loginTime}</span>
            <span style="color:var(--text-muted)">·</span>
            <span style="color:${isExpired?'var(--text-muted)':'#22c55e'}">有效至 ${expireTime}</span>
          </div>
        </div>
        ${!isCur ? `<div class="session-revoke-btn"><button class="btn btn-danger" onclick="revokeSession('${esc(s.id)}', this)">退出</button></div>` : ''}
      `;
      listEl.appendChild(card);
    });

  } catch(err) {
    loadingEl.style.display = 'none';
    listEl.innerHTML = `<p class="text-center text-sm" style="color:var(--text-muted);padding:30px 0">网络异常，请重试</p>`;
  }
}

async function revokeSession(sessionId, btn) {
  btn.disabled = true;
  btn.textContent = '退出中...';
  try {
    const fd = new FormData();
    fd.append('action', 'revoke');
    fd.append('session_id', sessionId);
    const res  = await fetch('/api/session.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.ok) {
      // 找到对应卡片，fade out 并移除
      const card = document.querySelector(`.session-card[data-sid="${CSS.escape(sessionId)}"]`);
      if (card) {
        card.style.transition = 'opacity .3s, transform .3s';
        card.style.opacity = '0';
        card.style.transform = 'translateX(10px)';
        setTimeout(() => card.remove(), 320);
      }
      showToast('已成功退出该会话~', 'success');
    } else {
      showToast(data.msg || '操作失败', 'error');
      btn.disabled = false;
      btn.textContent = '退出';
    }
  } catch(err) {
    showToast('驿站繁忙，请重试', 'error');
    btn.disabled = false;
    btn.textContent = '退出';
  }
}

<?php if ($isMobile): ?>
// ═════════════════════════════════════════════════════════════
//  QR CODE SCAN (from avatar dropdown)
// ═════════════════════════════════════════════════════════════
let qrScanInstance = null;
let qrScanSessionId = null;

function openQRScanModal() {
  openModal('qrScanModal');
  qrScanSessionId = null;
  showQRScanState('loading');
  checkCameraPermission();
}

function closeQRScanModal() {
  stopQRScanning();
  closeModal('qrScanModal');
  setTimeout(() => resetQRScanUI(), 300);
}

async function checkCameraPermission() {
  try {
    // 先尝试用 Permissions API 查询
    if (navigator.permissions && navigator.permissions.query) {
      const result = await navigator.permissions.query({ name: 'camera' });
      if (result.state === 'denied') {
        showQRScanState('denied');
        return;
      }
      // prompt 或 granted 都尝试启动
      showQRScanState('camera');
      startQRScanning();
      // 监听权限变化
      result.onchange = () => {
        if (result.state === 'denied') {
          stopQRScanning();
          showQRScanState('denied');
        } else if (result.state === 'granted') {
          showQRScanState('camera');
          startQRScanning();
        }
      };
      return;
    }
  } catch (e) {
    // 某些浏览器不支持查询 camera 权限，直接尝试启动
  }
  // 兜底：直接尝试启动，让浏览器自己弹授权框
  showQRScanState('camera');
  startQRScanning();
}

function showQRScanState(state) {
  document.getElementById('qrscan-loading').style.display = state === 'loading' ? '' : 'none';
  document.getElementById('qrscan-camera-wrap').style.display = state === 'camera' ? '' : 'none';
  document.getElementById('qrscan-denied').style.display = state === 'denied' ? '' : 'none';
  document.getElementById('qrscan-confirm').style.display = state === 'confirm' ? '' : 'none';
}

function resetQRScanUI() {
  document.getElementById('qrscan-loading').style.display = '';
  document.getElementById('qrscan-camera-wrap').style.display = 'none';
  document.getElementById('qrscan-denied').style.display = 'none';
  document.getElementById('qrscan-confirm').style.display = 'none';
  document.getElementById('qrscan-user-card').style.display = 'none';
  const btn = document.getElementById('qrscan-confirm-btn');
  if (btn) { btn.disabled = false; btn.textContent = '确认登录'; }
}

function startQRScanning() {
  if (!window.Html5Qrcode) {
    showToast('扫码载入失败，请刷新重试', 'error');
    closeQRScanModal();
    return;
  }
  const reader = document.getElementById('qrscan-reader');
  if (!reader) return;
  reader.innerHTML = '';
  qrScanInstance = new Html5Qrcode('qrscan-reader');
  qrScanInstance.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: { width: 220, height: 220 } },
    onQRScanDecoded,
    () => {}
  ).catch(err => {
    // 权限被拒绝或没有摄像头
    const msg = String(err.message || err);
    if (msg.includes('Permission') || msg.includes('permission') || msg.includes('denied') || msg.includes('NotAllowed')) {
      showQRScanState('denied');
    } else if (msg.includes('NotFound') || msg.includes('Found')) {
      showToast('未检测到摄像头设备', 'error');
      closeQRScanModal();
    } else {
      showToast('摄像头启动失败：' + msg, 'error');
      closeQRScanModal();
    }
  });
}

function stopQRScanning() {
  if (qrScanInstance) {
    try { qrScanInstance.stop(); } catch (e) {}
    try { qrScanInstance.clear(); } catch (e) {}
    qrScanInstance = null;
  }
}

function onQRScanDecoded(decodedText) {
  stopQRScanning();
  // 提取 session_id
  let sid = '';
  try {
    const url = new URL(decodedText);
    sid = url.searchParams.get('s') || '';
  } catch (e) {
    const m = decodedText.match(/[?&]s=([a-f0-9]+)/);
    if (m) sid = m[1];
  }
  if (!sid || sid.length < 16) {
    showToast('无效的二维码，请扫描 PC 端登录二维码', 'error');
    resetQRScan();
    return;
  }
  qrScanSessionId = sid;
  // 显示确认面板
  showQRScanState('confirm');
  // 如果已登录，显示当前用户信息
  const userCard = document.getElementById('qrscan-user-card');
  if (IS_LOGGED_IN && window.__currentUser) {
    const u = window.__currentUser;
    const avatar = document.getElementById('qrscan-confirm-avatar');
    const name = document.getElementById('qrscan-confirm-name');
    avatar.src = u.picture || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.name || 'U') + '&background=222&color=fff&size=88';
    avatar.onerror = function() { this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.name || 'U') + '&background=222&color=fff&size=88'; };
    name.textContent = u.name || '用户';
    userCard.style.display = '';
  } else {
    userCard.style.display = 'none';
  }
}

function resetQRScan() {
  qrScanSessionId = null;
  showQRScanState('camera');
  startQRScanning();
}

async function doQRScanConfirm() {
  if (!qrScanSessionId) { showToast('二维码已失效', 'error'); resetQRScan(); return; }
  const btn = document.getElementById('qrscan-confirm-btn');
  btn.disabled = true; btn.textContent = '确认中…';

  try {
    // Step 1: scan
    const scanRes = await fetch('/api/qrcode_login.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'scan', session_id: qrScanSessionId })
    });
    const scanData = await scanRes.json();
    if (!scanData.ok) {
      showToast(scanData.msg || '扫码失败', 'error');
      btn.disabled = false; btn.textContent = '确认登录';
      if (scanData.msg && scanData.msg.includes('过期')) {
        setTimeout(() => resetQRScan(), 1500);
      }
      return;
    }

    // Step 2: confirm
    const confirmRes = await fetch('/api/qrcode_login.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'confirm', session_id: qrScanSessionId })
    });
    const confirmData = await confirmRes.json();

    if (confirmData.ok) {
      showToast('PC 端登录已确认', 'success');
      closeQRScanModal();
    } else {
      showToast(confirmData.msg || '确认失败', 'error');
      btn.disabled = false; btn.textContent = '确认登录';
    }
  } catch (e) {
    showToast('网络异常', 'error');
    btn.disabled = false; btn.textContent = '确认登录';
  }
}
<?php endif; ?>

// ── Like / Unlike ───────────────────────────────────────────
async function toggleLike(projectId, btn, source) {
  if (!IS_LOGGED_IN) {
    showToast('请先登录后再点赞~', 'error');
    return;
  }
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('action', 'like');
    fd.append('id', projectId);
    const res  = await fetch('/api/project.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      // 更新当前按钮状态
      btn.classList.toggle('liked', data.is_liked);
      btn.title = data.is_liked ? '取消点赞' : '点赞';
      const svg = btn.querySelector('svg');
      if (svg) svg.setAttribute('fill', data.is_liked ? 'currentColor' : 'none');
      const countEl = btn.querySelector('.like-count');
      if (countEl) countEl.textContent = data.like_count;

      // 如果是从详情弹窗触发的，同步更新卡片上的按钮
      if (source === 'detail') {
        const cardBtns = document.querySelectorAll(`.proj-card button[onclick*="toggleLike(${projectId}"]`);
        cardBtns.forEach(cb => {
          cb.classList.toggle('liked', data.is_liked);
          cb.title = data.is_liked ? '取消点赞' : '点赞';
          const csvg = cb.querySelector('svg');
          if (csvg) csvg.setAttribute('fill', data.is_liked ? 'currentColor' : 'none');
          const ccount = cb.querySelector('.like-count');
          if (ccount) ccount.textContent = data.like_count;
        });
      }
    } else {
      showToast(data.msg || '操作失败', 'error');
    }
  } catch(err) {
    showToast('网络异常，请重试', 'error');
  } finally {
    btn.disabled = false;
  }
}

// ── Toast & Utils ────────────────────────────────────────────
function showToast(msg, type='success') {
  const w = document.getElementById('toast');
  // 去重：相同消息不重复显示
  const existing = w.querySelectorAll('.toast-item');
  for (const el of existing) {
    if (el.textContent === msg) return;
  }
  // 限制数量
  while (existing.length >= 3) {
    existing[0].classList.add('out');
    setTimeout(() => existing[0].remove(), 320);
  }
  const el = document.createElement('div');
  el.className = `toast-item ${type}`; el.textContent = msg;
  // 点击关闭
  el.style.cursor = 'pointer'; el.title = '点击关闭';
  el.addEventListener('click', () => { el.classList.add('out'); setTimeout(() => el.remove(), 320); });
  w.appendChild(el);
  setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(),320); }, 3500);
}
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function renderTechBadges(techStack) {
  if (!techStack) return '';
  const list = String(techStack).split(',').map(s => s.trim()).filter(Boolean);
  if (!list.length) return '';
  return '<div class="tech-badges">' + list.map(t => '<span class="tech-badge">' + esc(t) + '</span>').join('') + '</div>';
}
function truncateDesc(s) {
  const t = String(s||'').trim();
  return t.length > 55 ? t.slice(0,55) + '...' : t;
}
function formatDate(s) {
  if (!s) return '';
  const d = new Date(s);
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function formatDatetime(s) {
  if (!s) return '';
  const d = new Date(s);
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
}
<?php if (isset($_GET['error'])): ?>
showToast('<?= htmlspecialchars($_GET['error'], ENT_QUOTES) ?>','error');
<?php endif; ?>


// ── 举报 ─────────────────────────────────────────────────
let currentReportProjectId = 0;
let currentReportProjectName = '';

function openReportModal() {
  document.getElementById('report-project-name').textContent = currentReportProjectName;
  document.getElementById('report-type').value = '';
  document.getElementById('report-email').value = '';
  document.getElementById('report-reason').value = '';
  // 登录用户隐藏邮箱输入框（自动使用绑定邮箱）
  const emailRow = document.getElementById('report-email-row');
  if (IS_LOGGED_IN) {
    emailRow.style.display = 'none';
  } else {
    emailRow.style.display = '';
  }
  openModal('reportModal');
}

function closeReportModal() {
  closeModal('reportModal');
}

async function submitReport(e) {
  e.preventDefault();
  const type = document.getElementById('report-type').value;
  const email = document.getElementById('report-email').value.trim();
  const reason = document.getElementById('report-reason').value.trim();
  if (!type) { showToast('请选择举报类型','error'); return; }
  const fd = new FormData();
  fd.append('action', 'submit');
  fd.append('project_id', currentReportProjectId);
  fd.append('report_type', type);
  fd.append('notify_email', email);
  fd.append('reason', reason);
  try {
    const res = await fetch('/api/report.php', { method: 'POST', body: fd });
    const d = await res.json();
    if (d.ok) {
      showToast(d.msg || '举报已提交','success');
      closeReportModal();
    } else {
      showToast(d.msg || '提交失败','error');
    }
  } catch(err) {
    showToast('网络错误，请重试','error');
  }
}

// ── 广场分享弹窗 ─────────────────────────────────────────
function openSquareShareModal() {
  if (!currentDetailId) return;
  const link = 'https://fohok.xin/prodeta.php?id=' + currentDetailId;
  const embed = '<iframe src="https://fohok.xin/embed.php?id=' + currentDetailId + '" width="400" height="105" frameborder="0" style="border:none;border-radius:10px"></iframe>';
  const html = `
    <div style="padding:24px">
      <div class="share-panel-wrap">
        <div class="share-panel-qr">
          <div class="share-qr-img-wrap">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=144x144&margin=2&data=${encodeURIComponent(link)}" alt="二维码">
          </div>
          <div class="share-qr-tip">扫码查看项目</div>
        </div>
        <div class="share-panel-right">
          <div>
            <div class="share-label">项目链接</div>
            <div class="share-code-box">${esc(link)}<button class="share-copy-btn" onclick="copyText('${esc(link)}')">复制</button></div>
          </div>
          <div>
            <div class="share-label">嵌入式卡片</div>
            <div class="share-code-box"><div class="share-code-box-text">${esc(embed)}</div><button class="share-copy-btn" onclick="copyText('${esc(embed)}')">复制</button></div>
          </div>
        </div>
      </div>
    </div>`;
  document.getElementById('detail-body').style.display = 'none';
  const shareBody = document.getElementById('square-share-body');
  shareBody.innerHTML = html;
  shareBody.style.display = 'block';
  document.getElementById('square-share-back-btn').style.display = 'inline-flex';
  document.getElementById('detail-title').textContent = '分享项目';
}

function closeSquareShareModal() {
  document.getElementById('square-share-body').style.display = 'none';
  document.getElementById('square-share-back-btn').style.display = 'none';
  document.getElementById('detail-body').style.display = '';
  if (currentReportProjectName) document.getElementById('detail-title').textContent = currentReportProjectName;
}

async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    showToast('已复制到剪贴板', 'success');
  } catch {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;left:-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); showToast('已复制到剪贴板', 'success'); }
    catch { showToast('复制失败', 'error'); }
    document.body.removeChild(ta);
  }
}

</script>
</body>
</html>