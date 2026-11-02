<?php
require_once __DIR__ . '/bootstrap.php';
$user    = requireLogin();
$userId  = $user['user_id'] ?? $user['id'];
$isAdmin = in_array($user['email'], ADMIN_EMAILS);
$unreadCount = getUnreadCount($userId);

// 站内信转发（通知通道）开关状态
ensureForwardSchema();
$inboxForward = isInboxForwardEnabled($userId);

$accountHost = parse_url(WENCHAN_OAUTH_AUTH_URL, PHP_URL_HOST) ?: 'account.fohok.xin';
$accountCenter = 'https://' . $accountHost;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta property="og:title" content="个人中心 · 文谶驿站">
<meta property="og:description" content="一个为独立开发者打造的开源项目展示空间。
发布你的作品，探索他人的创造，连接志同道合的人。">
<meta property="og:image" content="https://fohok.xin/img/fohok-2.png">
<meta property="og:url" content="https://fohok.xin/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="文谶驿站 · 开发者开放仓库">
<title>个人中心 · 文谶驿站</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Noto+Sans+SC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}

  /* 日间主题（默认） */
  :root {
    --bg-primary: #fafafa;
    --bg-secondary: #ffffff;
    --bg-tertiary: #f5f5f5;
    --bg-hover: #f0f0f0;
    --bg-card: #ffffff;
    --bg-input: #f5f5f5;
    --bg-modal: #ffffff;
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
    --accent-color: #171717;
    --accent-hover: #404040;
    --link-color: #2563eb;
    --link-hover: #3b82f6;
    --danger-color: #dc2626;
    --danger-bg: #fef2f2;
    --danger-border: #fecaca;
    --success-color: #16a34a;
    --warn-color: #ca8a04;
    --badge-approved-bg: #f0fdf4;
    --badge-approved-color: #166534;
    --badge-approved-border: #bbf7d0;
    --badge-pending-bg: #fefce8;
    --badge-pending-color: #854d0e;
    --badge-pending-border: #fde68a;
    --badge-rejected-bg: #fef2f2;
    --badge-rejected-color: #991b1b;
    --badge-rejected-border: #fecaca;
    --badge-taken-down-bg: #fff7ed;
    --badge-taken-down-color: #9a3412;
    --badge-taken-down-border: #fed7aa;
    --badge-hidden-bg: #f5f5f5;
    --badge-hidden-color: #737373;
    --badge-hidden-border: #d4d4d4;
    --overlay-bg: rgba(0, 0, 0, 0.5);
    --shadow-card: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-lg: 0 4px 24px rgba(0,0,0,0.1);
    --scrollbar-track: #f5f5f5;
    --scrollbar-thumb: #d4d4d4;
    --tab-bg: #f5f5f5;
    --tab-active-bg: #e5e5e5;
    --tab-border: #d4d4d4;
    --proj-row-border: #f0f0f0;
  }

  /* 夜间主题 */
  @media (prefers-color-scheme: dark) {
    :root {
      --bg-primary: #050505;
      --bg-secondary: #0a0a0a;
      --bg-tertiary: #111111;
      --bg-hover: #1a1a1a;
      --bg-card: #0f0f0f;
      --bg-input: #111111;
      --bg-modal: #0a0a0a;
      --text-primary: #f5f5f5;
      --text-secondary: #d4d4d4;
      --text-dim: #a3a3a3;
      --text-muted: #737373;
      --text-inverse: #0a0a0a;
      --border-primary: #1a1a1a;
      --border-secondary: #222222;
      --border-hover: #404040;
      --border-input: #222222;
      --border-input-focus: #444444;
      --accent-color: #e5e5e5;
      --accent-hover: #ffffff;
      --link-color: #60a5fa;
      --link-hover: #93c5fd;
      --danger-color: #f87171;
      --danger-bg: #1a0808;
      --danger-border: #3a1a1a;
      --success-color: #4ade80;
      --warn-color: #fbbf24;
      --badge-approved-bg: #052e16;
      --badge-approved-color: #4ade80;
      --badge-approved-border: #14532d;
      --badge-pending-bg: #1c1917;
      --badge-pending-color: #fbbf24;
      --badge-pending-border: #292524;
      --badge-rejected-bg: #1a0808;
      --badge-rejected-color: #f87171;
      --badge-rejected-border: #3a1a1a;
      --badge-taken-down-bg: #1a0f05;
      --badge-taken-down-color: #fb923c;
      --badge-taken-down-border: #3a2010;
      --badge-hidden-bg: #111111;
      --badge-hidden-color: #555555;
      --badge-hidden-border: #2a2a2a;
      --overlay-bg: rgba(0, 0, 0, 0.88);
      --shadow-card: none;
      --shadow-lg: 0 4px 24px rgba(0,0,0,0.3);
      --scrollbar-track: #0a0a0a;
      --scrollbar-thumb: #333333;
      --tab-bg: #111111;
      --tab-active-bg: #1a1a1a;
      --tab-border: #1a1a1a;
      --proj-row-border: #111111;
    }
  }

  body{
    background: var(--bg-primary);
    color: var(--text-primary);
    font-family: 'Noto Sans SC', sans-serif;
    min-height: 100vh;
  }
  ::-webkit-scrollbar{width:4px}
  ::-webkit-scrollbar-track{background:var(--scrollbar-track)}
  ::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb)}

  #topbar{
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 56px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-primary);
    z-index: 50;
    display: flex;
    align-items: center;
    padding: 0 20px;
    gap: 12px;
    backdrop-filter: blur(12px);
  }

  .inp{
    width: 100%;
    background: var(--bg-input);
    border: 1px solid var(--border-input);
    color: var(--text-primary);
    padding: 10px 14px;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color .2s;
    outline: none;
  }
  .inp:focus{border-color:var(--border-input-focus)}
  .inp::placeholder{color:var(--text-muted)}
  .inp[readonly]{opacity:.7;cursor:default;background:var(--bg-primary)}
  textarea.inp{resize:vertical}
  label{
    font-size: 12px;
    color: var(--text-dim);
    letter-spacing: .05em;
    text-transform: uppercase;
    margin-bottom: 4px;
    display: block;
  }

  .btn{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all .2s;
    border: none;
    font-family: inherit;
    text-decoration: none;
  }
  .btn-primary{
    background: var(--accent-color);
    color: var(--text-inverse);
  }
  .btn-primary:hover{background:var(--accent-hover)}
  .btn-ghost{
    background: transparent;
    color: var(--text-dim);
    border: 1px solid var(--border-secondary);
  }
  .btn-ghost:hover{
    color: var(--text-primary);
    border-color: var(--border-hover);
  }

  .tab-btn{
    padding: 5px 14px;border-radius: 5px;font-size: 13px;
    font-weight: 500;cursor: pointer;transition: all .2s;
    border: 1px solid transparent;background: transparent;
    color: var(--text-dim);font-family: inherit;
  }
  .tab-btn:hover{color: var(--text-primary);background: var(--bg-hover)}
  .tab-btn.active{
    background: var(--tab-active-bg);color: var(--text-primary);
    border-color: var(--tab-border);
  }

  .btn-danger{
    background: transparent;
    color: var(--danger-color);
    border: 1px solid var(--danger-border);
  }
  .btn-danger:hover{background:var(--danger-bg)}
  .submit-tab{
    padding:6px 14px;font-size:12px;border:none;background:transparent;
    color:var(--text-dim);cursor:pointer;border-radius:5px;transition:all .15s;
    white-space:nowrap;font-weight:500;
  }
  .submit-tab.active{background:var(--bg-card);color:var(--text-primary);box-shadow:0 0 0 1px var(--border-hover)}
  .submit-tab:not(.active):hover{color:var(--text-primary)}
  .submit-panel{display:none}.submit-panel.active{display:block}

  .btn-sm{padding:5px 11px;font-size:12px}

  /* 安全设置项 */
  .sec-item{
    display: flex;align-items: center;justify-content: space-between;
    padding: 16px 0;border-bottom: 1px solid var(--border-primary);
    gap: 16px;
  }
  .sec-item:last-child{border-bottom:none}
  .sec-left{cursor: pointer;flex:1;min-width:0}
  .sec-title{
    font-size: 14px;font-weight: 500;color: var(--text-primary);
    display: flex;align-items: center;gap: 8px;margin-bottom: 4px;
  }
  .sec-desc{
    font-size: 12px;color: var(--text-muted);line-height: 1.5;
  }
  .sec-right{flex-shrink:0}

  /* 开关 */
  .toggle-switch{
    position: relative;display: inline-block;width: 44px;height: 24px;
    cursor: pointer;
  }
  .toggle-switch input{display:none}
  .toggle-track{
    position: absolute;inset: 0;border-radius: 99px;
    background: var(--border-secondary);transition: background .25s;
  }
  .toggle-track::after{
    content: '';position: absolute;top: 2px;left: 2px;
    width: 20px;height: 20px;border-radius: 50%;
    background: #fff;transition: transform .25s;box-shadow: 0 1px 3px rgba(0,0,0,.15);
  }
  .toggle-switch input:checked + .toggle-track{
    background: var(--accent-color);
  }
  .toggle-switch input:checked + .toggle-track::after{
    transform: translateX(20px);
  }

  /* 凭据列表弹窗 */
  .cred-row{
    display: flex;align-items: center;justify-content: space-between;
    padding: 12px 0;border-bottom: 1px solid var(--border-primary);
    gap: 12px;
  }
  .cred-row:last-child{border-bottom:none}
  .cred-name{
    font-size: 13px;color: var(--text-primary);font-weight: 500;
  }
  .cred-time{
    font-size: 11px;color: var(--text-muted);font-family: 'JetBrains Mono',monospace;
  }

  .countdown-btn:disabled{opacity:.5;cursor:not-allowed}

  .modal-header{
    padding: 14px 20px;border-bottom: 1px solid var(--border-primary);
    display: flex;align-items: center;justify-content: space-between;
  }
  .modal-body{
    padding: 16px 20px;
  }
  .modal-footer{
    padding: 14px 20px;border-top: 1px solid var(--border-primary);
    display: flex;align-items: center;justify-content: flex-end;gap: 10px;
  }

  .section{
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: 10px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-card);
  }

  .badge{
    display: inline-block;
    padding: 2px 8px;
    border-radius: 99px;
    font-size: 11px;
    font-family: 'JetBrains Mono', monospace;
    font-weight: 500;
  }
  .badge-approved{
    background: var(--badge-approved-bg);
    color: var(--badge-approved-color);
    border: 1px solid var(--badge-approved-border);
  }
  .badge-pending{
    background: var(--badge-pending-bg);
    color: var(--badge-pending-color);
    border: 1px solid var(--badge-pending-border);
  }
  .badge-rejected{
    background: var(--badge-rejected-bg);
    color: var(--badge-rejected-color);
    border: 1px solid var(--badge-rejected-border);
  }
  .badge-taken-down{
    background: var(--badge-taken-down-bg);
    color: var(--badge-taken-down-color);
    border: 1px solid var(--badge-taken-down-border);
  }
  .badge-hidden{
    background: var(--badge-hidden-bg);
    color: var(--badge-hidden-color);
    border: 1px solid var(--badge-hidden-border);
  }

  .dropdown{position:relative}
  .dropdown-menu{
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    background: var(--bg-card);
    border: 1px solid var(--border-secondary);
    border-radius: 8px;
    min-width: 150px;
    overflow: hidden;
    z-index: 200;
    opacity: 0;
    pointer-events: none;
    transform: translateY(-8px);
    transition: all .2s;
    box-shadow: var(--shadow-lg);
  }
  .dropdown-menu.show{opacity:1;pointer-events:all;transform:translateY(0)}
  .dropdown-item{
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    font-size: 14px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: background .15s;
    text-decoration: none;
  }
  .dropdown-item:hover{
    background: var(--bg-hover);
    color: var(--text-primary);
  }
  .dropdown-item.danger{color:var(--danger-color)}
  .dropdown-item.danger:hover{background:var(--danger-bg)}

  #toast{
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 999;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .toast-item{
    background: var(--bg-card);
    border: 1px solid var(--border-secondary);
    color: var(--text-primary);
    padding: 12px 18px;
    border-radius: 8px;
    font-size: 14px;
    max-width: 300px;
    animation: tIn .3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: var(--shadow-lg);
  }
  .toast-item.out{animation:tOut .3s ease forwards}
  .toast-item.success::before{content:'✓';color:var(--success-color);font-weight:700}
  .toast-item.error::before{content:'✕';color:var(--danger-color);font-weight:700}
  @keyframes tIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
  @keyframes tOut{from{opacity:1}to{opacity:0;transform:translateX(20px)}}

  /* Modal */
  .modal-bg{
    position: fixed;
    inset: 0;
    background: var(--overlay-bg);
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    pointer-events: none;
    transition: opacity .25s;
  }
  .modal-bg.show{opacity:1;pointer-events:all}
  /* 可在其他弹窗之上打开的弹窗层 */
  #captchaModal, #deleteConfirmModal { z-index: 150; }
  .modal{
    background: var(--bg-modal);
    border: 1px solid var(--border-secondary);
    border-radius: 12px;
    width: 100%;
    max-width: 420px;
    transform: scale(.97) translateY(8px);
    transition: transform .25s;
    box-shadow: var(--shadow-lg);
  }
  .modal-bg.show .modal{transform:scale(1) translateY(0)}

  /* edit modal */
  #editModal .modal{max-width:680px;max-height:90vh;overflow-y:auto}

  body.locked{overflow:hidden}

  /* Project row */
  .proj-row{
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 0;
    border-bottom: 1px solid var(--proj-row-border);
  }
  .proj-row:last-child{border-bottom:none}
  .proj-row-actions{display:flex;gap:6px;flex-shrink:0}

  /* 验证码容器 */
  #captcha-wrap{overflow:visible;transition:min-height .25s ease}

  /* ── 头像修改弹窗 ── */
  .avatar-tab-btn{
    flex: 1;
    padding: 8px 0;
    font-size: 13px;
    border-radius: 5px;
    background: transparent;
    border: none;
    color: var(--text-dim);
    cursor: pointer;
    font-family: inherit;
    transition: all .2s;
    font-weight: 500;
  }
  .avatar-tab-btn.active{
    background: var(--tab-active-bg);
    color: var(--text-primary);
  }
  .avatar-tab-pane{display:none}
  .avatar-tab-pane.active{display:block}

  /* 头像悬停遮罩 */
  .avatar-wrap{
    position: relative;
    cursor: pointer;
    display: inline-block;
    border-radius: 50%;
    overflow: hidden;
  }
  .avatar-wrap:hover .avatar-overlay{opacity:1}
  .avatar-overlay{
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.55);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity .2s;
    border-radius: 50%;
  }
  .avatar-overlay svg{color:#fff}

  /* 预览区 */
  #avatar-preview-qq,#avatar-preview-url{
    width: 72px;
    height: 72px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-secondary);
    display: none;
    flex-shrink: 0;
  }

  /* 带按钮的输入框 */
  .inp-with-btn{display:flex;gap:0}
  .inp-with-btn .inp{border-radius:6px 0 0 6px;border-right:none}
  .inp-with-btn .inp:focus{border-color:var(--border-input-focus);z-index:1}
  .inp-with-btn .inp-btn{
    background: var(--bg-hover);
    border: 1px solid var(--border-input);
    color: var(--text-secondary);
    padding: 0 14px;
    border-radius: 0 6px 6px 0;
    cursor: pointer;
    font-size: 13px;
    white-space: nowrap;
    transition: all .2s;
    font-family: inherit;
  }
  .inp-with-btn .inp-btn:hover{
    background: var(--border-hover);
    color: var(--text-primary);
  }

  /* 辅助类 */
  .text-danger { color: var(--danger-color); }
  .text-dim { color: var(--text-dim); }
  .text-muted { color: var(--text-muted); }
  .text-primary { color: var(--text-primary); }
  .text-secondary { color: var(--text-secondary); }
  .text-xs{font-size:12px}.text-sm{font-size:14px}
  .font-medium{font-weight:500}.font-mono{font-family:'JetBrains Mono',monospace}
  .truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .flex{display:flex}.grid{display:grid}
  .flex-1{flex:1}.flex-wrap{flex-wrap:wrap}
  .items-center{align-items:center}.justify-between{justify-content:space-between}
  .gap-1{gap:4px}.gap-2{gap:8px}.gap-3{gap:12px}.gap-4{gap:16px}
  .grid-cols-2{grid-template-columns:1fr 1fr}
  .p-5{padding:20px}.p-6{padding:24px}
  .pt-2{padding-top:8px}.mt-1{margin-top:4px}.mb-1{margin-bottom:4px}.mb-2{margin-bottom:8px}
  .space-y-4 > * + *{margin-top:16px}
  .w-full{width:100%}.rounded{border-radius:6px}.rounded-lg{border-radius:8px}
  .border-b{border-bottom:1px solid var(--border-primary)}
  .inline-block{display:inline-block}
  .inline-flex{display:inline-flex}
  .animate-spin{animation:spin .7s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}

  /* ── Footer ── */
  .page-footer{
    text-align: center;
    padding: 8px 24px 32px;
  }
  .page-footer a{
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: var(--text-muted);
    text-decoration: none;
    letter-spacing: .05em;
    transition: color .2s;
  }
  .page-footer a:hover{
    color: var(--text-dim);
  }

  @media(max-width:640px){
    .tab-btn{padding:5px 10px;font-size:12px}
    .avatar-tab-btn{font-size:12px;padding:6px 0}
    .section{padding:16px}
    main{padding:20px 12px 48px}
  }
</style>
</head>
<body>

<!-- ══ TOPBAR ══════════════════════════════════════════════ -->
<header id="topbar">
  <span class="text-sm font-medium" style="color:var(--text-primary);">个人中心</span>
  <div class="flex-1"></div>
  <a href="/messages.php" style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:6px;transition:all .2s;color:var(--text-dim);text-decoration:none;position:relative;margin-right:4px" title="站内信"
     onmouseover="this.style.background='#000';this.style.color='#fff'"
     onmouseout="this.style.background='transparent';this.style.color='var(--text-dim)'">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    <?php if ($unreadCount > 0): ?>
    <span style="position:absolute;top:2px;right:2px;min-width:16px;height:16px;background:#3b82f6;color:#fff;border-radius:99px;font-size:10px;font-family:'JetBrains Mono',monospace;display:flex;align-items:center;justify-content:center;padding:0 3px;font-weight:700;line-height:1"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
    <?php endif; ?>
  </a>
  <div class="dropdown">
    <button onclick="dd('userMenu')" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
      <img src="<?= htmlspecialchars($user['picture'] ?? 'https://ui-avatars.com/api/?name='.urlencode($user['name']??'U').'&background=222&color=fff') ?>"
           class="w-8 h-8 rounded-full object-cover" style="border:1px solid var(--border-secondary)" alt="avatar">
    </button>
    <div id="userMenu" class="dropdown-menu">
      <a href="/home.php" class="dropdown-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        返回首页
      </a>
      <?php if ($isAdmin): ?>
      <a href="/admin/" class="dropdown-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/></svg>
        管理后台
      </a>
      <?php endif; ?>
      <a href="/logout.php" class="dropdown-item danger">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        退出登录
      </a>
    </div>
  </div>
</header>

<main style="margin-top:56px;padding:28px 24px;max-width:900px;margin-left:auto;margin-right:auto">

  <!-- 个人信息 -->
  <div class="section">
    <div class="flex items-center gap-4 mb-6">
      <!-- 可点击头像 -->
      <div class="avatar-wrap w-16 h-16 flex-shrink-0" onclick="openAvatarModal()" title="修改头像">
        <img id="main-avatar"
             src="<?= htmlspecialchars($user['picture'] ?? '') ?>"
             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']??'U') ?>&background=222&color=fff&size=64'"
             class="w-16 h-16 rounded-full object-cover" style="border:1px solid var(--border-secondary)" alt="avatar">
        <div class="avatar-overlay">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
        </div>
      </div>
      <div>
        <div style="display:flex; align-items:center; gap:8px;">
          <h2 class="text-lg font-medium" style="color:var(--text-primary); margin:0;"><?= htmlspecialchars($user['name'] ?? '匿名用户') ?></h2>
          <a href="<?= htmlspecialchars($accountCenter) ?>/profile.php"
             target="_blank" rel="noopener"
             class="btn btn-ghost btn-sm"
             style="margin-left:4px; white-space:nowrap;">文谶账户中心</a>
        </div>
        <p class="text-sm mt-0.5" style="color:var(--text-dim);"><?= htmlspecialchars($user['email'] ?? '') ?></p>
      </div>
    </div>

    <div id="profileForm" class="space-y-4">
      <!-- 用户名 -->
      <div>
        <label>用户名 <span class="text-danger">*</span></label>
        <input id="f-name" class="inp" value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="你的昵称">
      </div>
      <!-- 个人简介 -->
      <div>
        <label>个人简介</label>
        <textarea id="f-bio" class="inp" rows="2" placeholder="介绍一下自己..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label>个人网站</label>
          <input id="f-website" type="url" class="inp" placeholder="https://..." value="<?= htmlspecialchars($user['website'] ?? '') ?>">
        </div>
        <div>
          <label>联系电话</label>
          <input id="f-phone" class="inp" placeholder="选填" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
        </div>
      </div>
      <div class="flex justify-end">
        <button onclick="openSaveModal()" class="btn btn-primary">保存修改</button>
      </div>
    </div>
  </div>

  <!-- 我的项目 -->
  <div class="section">
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <button class="tab-btn active" id="tab-my-projects" onclick="switchTab('my')">我的项目</button>
        <button class="tab-btn" id="tab-liked-projects" onclick="switchTab('liked')">点赞过的项目</button>
        <button class="tab-btn" id="tab-security" onclick="switchTab('security')">安全设置</button>
      </div>
      <a href="/home.php" class="btn btn-ghost btn-sm">+ 发布项目</a>
    </div>
    <div id="my-projects">
      <div class="text-center py-10">
        <div class="inline-block w-5 h-5 border-2 rounded-full animate-spin" style="border-color:var(--border-secondary);border-top-color:var(--text-dim);"></div>
      </div>
    </div>
    <div id="liked-projects" style="display:none">
      <div class="text-center py-10">
        <div class="inline-block w-5 h-5 border-2 rounded-full animate-spin" style="border-color:var(--border-secondary);border-top-color:var(--text-dim);"></div>
      </div>
    </div>
    <div id="security-settings" style="display:none">
      <div class="sec-item">
        <div class="sec-left">
          <div class="sec-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            账户与安全
          </div>
          <div class="sec-desc">密码、双重验证与登录设备管理请在文谶账户中心进行</div>
        </div>
        <div class="sec-right">
          <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($accountCenter) ?>/security.php" target="_blank" rel="noopener" style="font-size:11px;padding:4px 12px">前往管理</a>
        </div>
      </div>

      <div class="sec-item">
        <div class="sec-left">
          <div class="sec-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17V9M4 17h2v-8H4v8zm7 4V5h-2v16h2zm7-8v4h-2v-4h2z"/><path d="M17 21v-6M17 3v6"/></svg>
            站内信转发
          </div>
          <div class="sec-desc">开启后，本站收到的站内信将通过通知通道同步转发至你的文谶账号中心</div>
        </div>
        <div class="sec-right">
          <label class="toggle-switch" id="inbox-forward-toggle-label">
            <input type="checkbox" id="inbox-forward-toggle" <?= $inboxForward ? 'checked' : '' ?> onchange="toggleInboxForward()">
            <span class="toggle-track"></span>
          </label>
        </div>
      </div>

      <div class="sec-item">
        <div class="sec-left">
          <div class="sec-title" style="color:var(--danger-color)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            注销账号
          </div>
          <div class="sec-desc">注销账号将删除你所有关联的项目及数据</div>
        </div>
        <div class="sec-right">
          <button class="btn btn-danger btn-sm" onclick="openDeleteAccount()" style="font-size:11px;padding:4px 12px">注销</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="page-footer">
<a href="https://beian.miit.gov.cn" target="_blank" style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;">
    <img src="https://fohok.xin/img/icp.png" alt="ICP备案图标" style="width:20px;height:auto;">
    闽ICP备2026024151号 | <a href="https://beian.mps.gov.cn/#/query/webSearch?code=35058302351237" target="_blank" style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;">
    <img src="https://fohok.xin/img/mps.png" alt="公安备案图标" style="width:20px;height:auto;">闽公网安备35058302351237号
</a>
</a>
</footer>

</main>

<!-- ══ 头像修改弹窗 ════════════════════════════════════════════ -->
<div id="avatarModal" class="modal-bg" onclick="if(event.target===this)closeModal('avatarModal')">
  <div class="modal" style="max-width:460px" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <h3 class="font-medium" style="color:var(--text-primary);">修改头像</h3>
      <button onclick="closeModal('avatarModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="p-5 space-y-4">
      <!-- 标签切换 -->
      <div class="flex gap-1 p-1 rounded-lg" style="background:var(--tab-bg);border:1px solid var(--tab-border)">
        <button id="tab-qq-btn"  class="avatar-tab-btn active" onclick="switchAvatarTab('qq')">同步 QQ 头像</button>
        <button id="tab-url-btn" class="avatar-tab-btn"        onclick="switchAvatarTab('url')">同步图片 URL</button>
      </div>

      <!-- QQ 面板 -->
      <div id="tab-qq" class="avatar-tab-pane active space-y-3">
        <label>QQ号码</label>
        <div class="flex items-center gap-3">
          <div class="inp-with-btn flex-1">
            <input id="avatar-qq-input" class="inp" placeholder="输入QQ号后回车或点击预览"
                   onkeydown="if(event.key==='Enter')previewQQ()">
            <button class="inp-btn" onclick="previewQQ()">预览</button>
          </div>
          <img id="avatar-preview-qq" alt="QQ头像预览" style="display:none">
        </div>
        <p class="text-xs text-muted">将使用QQ头像作为您的个人头像</p>
      </div>

      <!-- URL 面板 -->
      <div id="tab-url" class="avatar-tab-pane space-y-3">
        <label>图片链接</label>
        <div class="flex items-center gap-3">
          <div class="inp-with-btn flex-1">
            <input id="avatar-url-input" type="url" class="inp" placeholder="输入图片链接后回车或点击预览"
                   onkeydown="if(event.key==='Enter')previewURL()">
            <button class="inp-btn" onclick="previewURL()">预览</button>
          </div>
          <img id="avatar-preview-url" alt="头像预览" style="display:none">
        </div>
        <p class="text-xs text-muted">支持公开可访问的图片链接</p>
      </div>

      <div class="flex gap-3 pt-1">
        <button onclick="closeModal('avatarModal')" class="btn btn-ghost flex-1">取消</button>
        <button onclick="confirmAvatarChange()" class="btn btn-primary flex-1">保存头像</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ 极验验证弹窗══════════════════════════ -->
<div id="captchaModal" class="modal-bg" onclick="if(event.target===this)closeCaptchaModal()">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <h3 class="font-medium" style="color:var(--text-primary);" id="captcha-modal-title">人机验证</h3>
      <button onclick="closeCaptchaModal()" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="p-5">
      <p class="text-sm mb-4 text-dim">请完成下方人机验证，验证通过后将自动保存您的修改~</p>
      <div id="captcha-wrap"></div>
    </div>
  </div>
</div>

<!-- ══ 编辑项目弹窗 ══════════════════════════════════════════ -->
<div id="editModal" class="modal-bg" onclick="if(event.target===this)closeModal('editModal')">
  <div class="modal" style="max-width:680px" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <div class="flex items-center gap-1" style="background:var(--bg-tertiary);border:1px solid var(--border-primary);border-radius:6px;padding:2px">
        <button type="button" id="edit-tab-edit" class="submit-tab active" onclick="switchEditTab('edit')">编辑项目</button>
        <button type="button" id="edit-tab-version" class="submit-tab" onclick="switchEditTab('version')">发布新版本</button>
      </div>
      <button onclick="closeModal('editModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- 编辑面板 -->
    <div id="edit-panel-edit" class="submit-panel active">
    <form id="editForm" class="p-5 space-y-4" onsubmit="handleEdit(event)">
      <input type="hidden" id="edit-id">
      <div class="grid grid-cols-2 gap-4">
        <div><label>项目名称 *</label><input id="edit-name" class="inp" required></div>
        <div><label>版本 *</label><input id="edit-version" class="inp" required></div>
      </div>
      <div><label>项目地址 *</label><input id="edit-url" type="url" class="inp" required></div>
      <div><label>项目介绍 *</label><textarea id="edit-desc" class="inp" rows="3" required></textarea></div>
      <div><label>技术栈</label><input id="edit-tech-stack" class="inp" placeholder="例如: php, html, javascript, java, python..."></div>
      <div><label>自定义封面图链接</label><input id="edit-cover" class="inp" placeholder="https://example.com/cover.png"></div>
      <div class="grid grid-cols-2 gap-4">
        <div><label>发布日期</label><input id="edit-date" type="date" class="inp" readonly style="cursor:not-allowed;opacity:0.6"></div>
        <div><label>开发者网站</label><input id="edit-devsite" type="url" class="inp"></div>
      </div>
      <div>
        <label>联系方式</label>
        <div class="grid grid-cols-2 gap-3 mt-1">
          <input id="edit-cemail" type="email" class="inp" placeholder="邮箱">
          <input id="edit-cphone" class="inp" placeholder="电话">
          <input id="edit-cqq" class="inp" placeholder="QQ">
          <input id="edit-cwechat" class="inp" placeholder="微信">
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal('editModal')" class="btn btn-ghost flex-1">取消</button>
        <button type="submit" class="btn btn-primary flex-1">保存并重新提交审核</button>
      </div>
    </form>
    </div>

    <!-- 发布新版本面板 -->
    <div id="edit-panel-version" class="submit-panel">
    <form id="newVersionForm" class="p-5 space-y-4" onsubmit="submitNewVersion(event)">
      <input type="hidden" id="nv-id">
      <div class="grid grid-cols-2 gap-4">
        <div><label>项目名称</label><input id="nv-name" class="inp" readonly></div>
        <div><label>当前版本</label><input id="nv-current-version" class="inp" readonly></div>
      </div>
      <div><label>新版本号 <span class="text-danger">*</span></label><input id="nv-version" class="inp" required placeholder="例如: 1.1.0"></div>
      <div><label>更新日志</label><textarea id="nv-changelog" class="inp" rows="4" placeholder="此版本的新增功能、修复和改进..."></textarea></div>
      <div><label>版本标签</label><select id="nv-tag" class="inp"><option value="">无标签</option><option value="stable">stable — 稳定版</option><option value="beta">beta — 测试版</option><option value="lts">lts — 长期支持</option><option value="deprecated">deprecated — 已弃用</option></select></div>
      <div class="grid grid-cols-2 gap-4">
        <div><label>更新日期</label><input id="nv-date" type="date" class="inp"></div>
        <div><label>开发者网站</label><input id="nv-devsite" type="url" class="inp"></div>
      </div>
      <div><label>项目地址</label><input id="nv-url" type="url" class="inp"></div>
      <div><label>项目介绍</label><textarea id="nv-desc" class="inp" rows="3"></textarea></div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal('editModal')" class="btn btn-ghost flex-1">取消</button>
        <button type="submit" class="btn btn-primary flex-1">发布新版本</button>
      </div>
    </form>
    </div>
  </div>
</div>

<!-- ══ 注销确认弹窗 ══════════════════════════════════════════ -->
<div id="deleteConfirmModal" class="modal-bg" onclick="if(event.target===this)closeModal('deleteConfirmModal')">
  <div class="modal" onclick="event.stopPropagation()" style="max-width:440px">
    <div class="modal-header">
      <span style="font-size:13px;font-weight:500;color:var(--danger-color)">⚠ 注销账号</span>
      <button onclick="closeModal('deleteConfirmModal')" class="modal-close" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:5px;border:none;background:transparent;cursor:pointer;color:var(--text-muted)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:14px;color:var(--text-secondary);line-height:1.8;margin-bottom:8px">
        注销账号后，以下数据将被<b>永久删除</b>且无法恢复：
      </p>
      <ul style="font-size:13px;color:var(--text-dim);padding-left:20px;line-height:2;margin-bottom:12px">
        <li>你发布的所有项目</li>
        <li>所有项目点赞记录</li>
        <li>所有站内信通知</li>
        <li>账号及个人资料</li>
      </ul>
      <p style="font-size:13px;color:var(--danger-color);font-weight:500">
        请冷静处理，确认无误后再操作。
      </p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('deleteConfirmModal')">取消</button>
      <button class="btn btn-danger btn-sm countdown-btn" id="confirm-delete-btn" disabled onclick="confirmDeleteAccount()">
        我已知晓 (<span id="delete-countdown">5</span>s)
      </button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script src="https://static.geetest.com/v4/gt4.js"></script>
<script>
let captchaObj    = null;
let captchaReady  = false;
let captchaInited = false;

// 当前待执行回调
let captchaSuccessCallback = null;

// 当前头像标签：'qq' | 'url'
let currentAvatarTab = 'qq';
// 待保存的头像
let pendingAvatarUrl = '';

let captchaResizeObserver = null;

document.addEventListener('DOMContentLoaded', () => {
  loadMyProjects();
  initCaptchaResizeObserver();
});

// ── 监听验证码容器尺寸──────────────────────────────────
function initCaptchaResizeObserver() {
  const wrap = document.getElementById('captcha-wrap');
  captchaResizeObserver = new ResizeObserver(() => {
    const inner = wrap.firstElementChild;
    if (!inner) return;
    const h = inner.scrollHeight || inner.offsetHeight;
    if (h > 0) wrap.style.minHeight = h + 'px';
  });
  captchaResizeObserver.observe(wrap);
}

function resetCaptchaWrapHeight() {
  document.getElementById('captcha-wrap').style.minHeight = '';
}

// ── Dropdown ─────────────────────────────────────────────────
function dd(id) { document.getElementById(id).classList.toggle('show'); }
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
});

// ── 头像修改弹窗 ──────────────────────────────────────────────
function openAvatarModal() {
  // 重置面板
  currentAvatarTab = 'qq';
  pendingAvatarUrl = '';
  document.getElementById('avatar-qq-input').value  = '';
  document.getElementById('avatar-url-input').value = '';
  hidePreview('qq');
  hidePreview('url');
  switchAvatarTab('qq');
  openModal('avatarModal');
}

function switchAvatarTab(tab) {
  currentAvatarTab = tab;
  document.getElementById('tab-qq').classList.toggle('active',  tab === 'qq');
  document.getElementById('tab-url').classList.toggle('active', tab === 'url');
  document.getElementById('tab-qq-btn').classList.toggle('active',  tab === 'qq');
  document.getElementById('tab-url-btn').classList.toggle('active', tab === 'url');
}

function hidePreview(type) {
  const el = document.getElementById(`avatar-preview-${type}`);
  el.onerror = null;           
  el.style.display = 'none';
  el.removeAttribute('src');
}

function previewQQ() {
  const qq = document.getElementById('avatar-qq-input').value.trim();
  if (!qq || !/^\d{5,11}$/.test(qq)) { showToast('请输入有效的QQ号', 'error'); return; }
  const url = `https://q.qlogo.cn/headimg_dl?dst_uin=${qq}&spec=640&img_type=jpg`;
  const img  = document.getElementById('avatar-preview-qq');
  img.onerror = () => { img.style.display = 'none'; showToast('QQ头像载入失败，请检查QQ号', 'error'); };
  img.style.display = 'block';
  img.src = url;
  pendingAvatarUrl = url;
}

function previewURL() {
  const url = document.getElementById('avatar-url-input').value.trim();
  if (!url) { showToast('请输入图片链接', 'error'); return; }
  const img = document.getElementById('avatar-preview-url');
  img.onerror = () => { img.style.display = 'none'; showToast('图片加载失败，请检查链接是否有效', 'error'); };
  img.style.display = 'block';
  img.src = url;
  pendingAvatarUrl = url;
}

function confirmAvatarChange() {
  // 若用户尚未点预览
  if (!pendingAvatarUrl) {
    if (currentAvatarTab === 'qq') {
      previewQQ();
    } else {
      previewURL();
    }
    if (!pendingAvatarUrl) return; 
  }
  closeModal('avatarModal');
  // 打开验证弹窗
  openCaptchaForAvatar();
}

// ══════════════════════════════════════════════════════════════
// 极验验证弹窗
// ══════════════════════════════════════════════════════════════
function openSaveModal() {
  const name = document.getElementById('f-name').value.trim();
  if (!name) { showToast('用户名不能为空', 'error'); return; }
  document.getElementById('captcha-modal-title').textContent = '完成验证后保存~';
  captchaSuccessCallback = doSaveProfile;
  openCaptchaModal();
}

function openCaptchaForAvatar() {
  document.getElementById('captcha-modal-title').textContent = '完成验证后保存~';
  captchaSuccessCallback = doSaveAvatar;
  openCaptchaModal();
}

function openCaptchaModal() {
  openModal('captchaModal');
  if (!captchaInited) {
    captchaInited = true;
    initCaptcha();
  } else if (captchaObj) {
    captchaObj.reset();
    captchaReady = false;
    resetCaptchaWrapHeight();
  } else {
    // captchaObj 意外为空，重新初始化
    captchaInited = false;
    initCaptcha();
    captchaInited = true;
  }
}

function closeCaptchaModal() {
  closeModal('captchaModal');
  resetCaptchaWrapHeight();
}

function initCaptcha() {
  window.initGeetest4({
    captchaId: '<?= CAPTCHA_ID ?>',
    product: 'float',
  }, function(obj) {
    captchaObj = obj;
    obj.appendTo('#captcha-wrap');

    obj.onSuccess(async function() {
      captchaReady = true;
      // 深拷贝验证数据，防止 reset 后引用被清空
      const captchaData = { ...obj.getValidate() };
      closeCaptchaModal();
      if (typeof captchaSuccessCallback === 'function') {
        await captchaSuccessCallback(captchaData);
        captchaSuccessCallback = null;
      }
      
      captchaReady = false;
    });

    obj.onError(function() {
      showToast('验证码加载失败，请刷新重试', 'error');
    });
  });
}

async function doSaveProfile(captchaData) {
  const fd = new FormData();
  fd.append('action',  'update');
  fd.append('name',    document.getElementById('f-name').value.trim());
  fd.append('bio',     document.getElementById('f-bio').value.trim());
  fd.append('website', document.getElementById('f-website').value.trim());
  fd.append('phone',   document.getElementById('f-phone').value.trim());
  for (const [k, v] of Object.entries(captchaData)) fd.append(k, v);

  const res  = await fetch('/api/user.php', { method: 'POST', body: fd });
  const data = await res.json();
  showToast(data.msg || (data.ok ? '保存成功' : '保存失败'), data.ok ? 'success' : 'error');
}

async function doSaveAvatar(captchaData) {
  const fd = new FormData();
  fd.append('action',  'update_avatar');
  fd.append('picture', pendingAvatarUrl);
  for (const [k, v] of Object.entries(captchaData)) fd.append(k, v);

  const res  = await fetch('/api/user.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    // 更新页面头像显示
    const newSrc = pendingAvatarUrl;
    document.getElementById('main-avatar').src = newSrc;
    document.querySelectorAll('#topbar img').forEach(img => img.src = newSrc);
    pendingAvatarUrl = '';
  }
  showToast(data.msg || (data.ok ? '头像已更新，可能需要1-2分钟同步~' : '保存失败'), data.ok ? 'success' : 'error');
}

// ── My projects ───────────────────────────────────────────────
async function loadMyProjects() {
  const res  = await fetch('/api/project.php?action=my_projects');
  const data = await res.json();
  const wrap = document.getElementById('my-projects');

  if (!data.ok || !data.projects.length) {
    wrap.innerHTML = '<p class="text-center text-sm py-8" style="color:var(--text-dim);">暂无项目，<a href="/home.php" class="underline" style="color:var(--link-color);">去发布第一个项目</a></p>';
    return;
  }

  const statusMap   = { pending:'badge-pending', approved:'badge-approved', rejected:'badge-rejected', taken_down:'badge-taken-down', hidden:'badge-hidden' };
  const statusLabel = { pending:'审核中', approved:'已通过', rejected:'已拒绝', taken_down:'已下架', hidden:'已隐藏' };

  wrap.innerHTML = data.projects.map(p => `
    <div class="proj-row" id="proj-row-${p.id}">
      <div style="flex:1;min-width:0">
        <div class="flex items-center gap-2 mb-1 flex-wrap">
          <span class="text-sm font-medium truncate" style="color:var(--text-primary);">${esc(p.name)}</span>
          <span class="text-xs font-mono" style="color:var(--text-muted);">${esc(p.version)}</span>
        </div>
        <p class="text-xs truncate" style="color:var(--text-dim);">${esc(p.description)}</p>
        ${p.reject_reason ? `<p class="text-xs mt-1 text-danger">拒绝原因：${esc(p.reject_reason)}</p>` : ''}
      </div>
      <div class="proj-row-actions">
        <span class="badge ${statusMap[p.status]||'badge-pending'}">${statusLabel[p.status]||p.status}</span>
        <button onclick='openEditProj(${JSON.stringify(p)})' class="btn btn-ghost btn-sm">编辑</button>
        <button onclick="toggleHide(${p.id},this)" class="btn btn-ghost btn-sm">${p.is_hidden ? '显示' : '隐藏'}</button>
        <button onclick="deleteProj(${p.id})" class="btn btn-danger btn-sm">删除</button>
      </div>
    </div>
  `).join('');
}

// ── Tab switching ────────────────────────────────────────────
let currentTab = 'my';
let likedLoaded = false;

function switchTab(tab) {
  currentTab = tab;
  document.getElementById('tab-my-projects').classList.toggle('active', tab === 'my');
  document.getElementById('tab-liked-projects').classList.toggle('active', tab === 'liked');
  document.getElementById('tab-security').classList.toggle('active', tab === 'security');
  document.getElementById('my-projects').style.display = tab === 'my' ? '' : 'none';
  document.getElementById('liked-projects').style.display = tab === 'liked' ? '' : 'none';
  document.getElementById('security-settings').style.display = tab === 'security' ? '' : 'none';
  if (tab === 'liked' && !likedLoaded) {
    likedLoaded = true;
    loadLikedProjects();
  }
}

// ── Liked projects ───────────────────────────────────────────
async function loadLikedProjects() {
  const res  = await fetch('/api/project.php?action=liked_projects');
  const data = await res.json();
  const wrap = document.getElementById('liked-projects');

  if (!data.ok || !data.projects.length) {
    wrap.innerHTML = '<p class="text-center text-sm py-8" style="color:var(--text-dim);">暂无点赞过的项目，<a href="/home.php" class="underline" style="color:var(--link-color);">去广场逛逛</a></p>';
    return;
  }

  wrap.innerHTML = data.projects.map(p => `
    <div class="proj-row" id="liked-row-${p.id}">
      <div style="flex:1;min-width:0">
        <div class="flex items-center gap-2 mb-1 flex-wrap">
          <span class="text-sm font-medium truncate" style="color:var(--text-primary);">${esc(p.name)}</span>
          <span class="text-xs font-mono" style="color:var(--text-muted);">${esc(p.version)}</span>
          <span class="text-xs" style="color:var(--text-dim);">by ${esc(p.author_name||'匿名')}</span>
        </div>
        <p class="text-xs truncate" style="color:var(--text-dim);">${esc(p.description)}</p>
      </div>
      <div class="proj-row-actions">
        <span class="text-xs font-mono" style="color:var(--text-muted);">♥ ${p.like_count||0}</span>
        <a href="/prodeta.php?id=${p.id}" target="_blank" class="btn btn-ghost btn-sm">查看</a>
        <button onclick="unlikeProject(${p.id},this)" class="btn btn-danger btn-sm" style="font-size:11px">取消点赞</button>
      </div>
    </div>
  `).join('');
}

async function unlikeProject(id, btn) {
  btn.disabled = true;
  btn.textContent = '...';
  try {
    const fd = new FormData();
    fd.append('action', 'like');
    fd.append('id', id);
    const res  = await fetch('/api/project.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      document.getElementById(`liked-row-${id}`)?.remove();
      showToast('已取消点赞', 'success');
      // 检查是否还有项目
      const wrap = document.getElementById('liked-projects');
      if (!wrap.querySelector('.proj-row')) {
        wrap.innerHTML = '<p class="text-center text-sm py-8" style="color:var(--text-dim);">暂无点赞过的项目，<a href="/home.php" class="underline" style="color:var(--link-color);">去广场逛逛</a></p>';
      }
    } else {
      showToast(data.msg || '操作失败', 'error');
      btn.disabled = false;
      btn.textContent = '取消点赞';
    }
  } catch(err) {
    showToast('网络异常，请重试', 'error');
    btn.disabled = false;
    btn.textContent = '取消点赞';
  }
}

function openEditProj(p) {
  document.getElementById('edit-id').value      = p.id;
  document.getElementById('edit-name').value    = p.name;
  document.getElementById('edit-version').value = p.version;
  document.getElementById('edit-url').value     = p.project_url;
  document.getElementById('edit-desc').value    = p.description;
  document.getElementById('edit-date').value    = p.release_date || '';
  document.getElementById('edit-devsite').value = p.developer_website || '';
  document.getElementById('edit-cemail').value  = p.contact_email || '';
  document.getElementById('edit-cphone').value  = p.contact_phone || '';
  document.getElementById('edit-cqq').value     = p.contact_qq || '';
  document.getElementById('edit-cwechat').value = p.contact_wechat || '';
  document.getElementById('edit-tech-stack').value = p.tech_stack || '';
  document.getElementById('edit-cover').value = p.cover_url || '';
  switchEditTab('edit');
  openModal('editModal');
}

async function handleEdit(e) {
  e.preventDefault();
  const fd = new FormData(document.getElementById('editForm'));
  fd.append('action', 'update');
  fd.set('id',          document.getElementById('edit-id').value);
  fd.set('name',        document.getElementById('edit-name').value);
  fd.set('version',     document.getElementById('edit-version').value);
  fd.set('project_url', document.getElementById('edit-url').value);
  fd.set('description', document.getElementById('edit-desc').value);
  fd.set('release_date',      document.getElementById('edit-date').value);
  fd.set('developer_website', document.getElementById('edit-devsite').value);
  fd.set('contact_email',  document.getElementById('edit-cemail').value);
  fd.set('contact_phone',  document.getElementById('edit-cphone').value);
  fd.set('contact_qq',     document.getElementById('edit-cqq').value);
  fd.set('contact_wechat', document.getElementById('edit-cwechat').value);
  fd.set('tech_stack',    document.getElementById('edit-tech-stack').value);
  fd.set('cover_url',    document.getElementById('edit-cover').value);

  const res  = await fetch('/api/project.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) { closeModal('editModal'); loadMyProjects(); showToast(data.msg, 'success'); }
  else showToast(data.msg || '保存失败', 'error');
}

async function toggleHide(id, btn) {
  const fd = new FormData();
  fd.append('action', 'toggle_hide'); fd.append('id', id);
  const res  = await fetch('/api/project.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) { btn.textContent = data.hidden ? '显示' : '隐藏'; showToast(data.hidden ? '已隐藏' : '已显示', 'success'); }
}

async function deleteProj(id) {
  if (!confirm('确定删除该项目？此操作不可恢复。')) return;
  const fd = new FormData();
  fd.append('action', 'delete'); fd.append('id', id);
  const res  = await fetch('/api/project.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) { document.getElementById(`proj-row-${id}`)?.remove(); showToast('已删除', 'success'); }
}

// ── 监听验证码容器尺寸──────────────────────────────────
function initCaptchaResizeObserver() {
  const wrap = document.getElementById('captcha-wrap');
  captchaResizeObserver = new ResizeObserver(() => {
    const inner = wrap.firstElementChild;
    if (!inner) return;
    const h = inner.scrollHeight || inner.offsetHeight;
    if (h > 0) wrap.style.minHeight = h + 'px';
  });
  captchaResizeObserver.observe(wrap);
}

function resetCaptchaWrapHeight() {
  document.getElementById('captcha-wrap').style.minHeight = '';
}

// ── Dropdown ─────────────────────────────────────────────────
function dd(id) { document.getElementById(id).classList.toggle('show'); }
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
});

// ── 头像修改弹窗 ──────────────────────────────────────────────
function openAvatarModal() {
  // 重置面板
  currentAvatarTab = 'qq';
  pendingAvatarUrl = '';
  document.getElementById('avatar-qq-input').value  = '';
  document.getElementById('avatar-url-input').value = '';
  hidePreview('qq');
  hidePreview('url');
  switchAvatarTab('qq');
  openModal('avatarModal');
}

function switchAvatarTab(tab) {
  currentAvatarTab = tab;
  document.getElementById('tab-qq').classList.toggle('active',  tab === 'qq');
  document.getElementById('tab-url').classList.toggle('active', tab === 'url');
  document.getElementById('tab-qq-btn').classList.toggle('active',  tab === 'qq');
  document.getElementById('tab-url-btn').classList.toggle('active', tab === 'url');
}

function hidePreview(type) {
  const el = document.getElementById(`avatar-preview-${type}`);
  el.onerror = null;           
  el.style.display = 'none';
  el.removeAttribute('src');
}

function previewQQ() {
  const qq = document.getElementById('avatar-qq-input').value.trim();
  if (!qq || !/^\d{5,11}$/.test(qq)) { showToast('请输入有效的QQ号', 'error'); return; }
  const url = `https://q.qlogo.cn/headimg_dl?dst_uin=${qq}&spec=640&img_type=jpg`;
  const img  = document.getElementById('avatar-preview-qq');
  img.onerror = () => { img.style.display = 'none'; showToast('QQ头像载入失败，请检查QQ号', 'error'); };
  img.style.display = 'block';
  img.src = url;
  pendingAvatarUrl = url;
}

function previewURL() {
  const url = document.getElementById('avatar-url-input').value.trim();
  if (!url) { showToast('请输入图片链接', 'error'); return; }
  const img = document.getElementById('avatar-preview-url');
  img.onerror = () => { img.style.display = 'none'; showToast('图片加载失败，请检查链接是否有效', 'error'); };
  img.style.display = 'block';
  img.src = url;
  pendingAvatarUrl = url;
}

function confirmAvatarChange() {
  // 若用户尚未点预览
  if (!pendingAvatarUrl) {
    if (currentAvatarTab === 'qq') {
      previewQQ();
    } else {
      previewURL();
    }
    if (!pendingAvatarUrl) return; 
  }
  closeModal('avatarModal');
  // 打开验证弹窗
  openCaptchaForAvatar();
}

// ══════════════════════════════════════════════════════════════
// 极验验证弹窗
// ══════════════════════════════════════════════════════════════
function openSaveModal() {
  const name = document.getElementById('f-name').value.trim();
  if (!name) { showToast('用户名不能为空', 'error'); return; }
  document.getElementById('captcha-modal-title').textContent = '完成验证后保存~';
  captchaSuccessCallback = doSaveProfile;
  openCaptchaModal();
}

function openCaptchaForAvatar() {
  document.getElementById('captcha-modal-title').textContent = '完成验证后保存~';
  captchaSuccessCallback = doSaveAvatar;
  openCaptchaModal();
}

function openCaptchaModal() {
  openModal('captchaModal');
  if (!captchaInited) {
    captchaInited = true;
    initCaptcha();
  } else if (captchaObj) {
    captchaObj.reset();
    captchaReady = false;
    resetCaptchaWrapHeight();
  } else {
    // captchaObj 意外为空，重新初始化
    captchaInited = false;
    initCaptcha();
    captchaInited = true;
  }
}

function closeCaptchaModal() {
  closeModal('captchaModal');
  resetCaptchaWrapHeight();
}

function initCaptcha() {
  window.initGeetest4({
    captchaId: '<?= CAPTCHA_ID ?>',
    product: 'float',
  }, function(obj) {
    captchaObj = obj;
    obj.appendTo('#captcha-wrap');

    obj.onSuccess(async function() {
      captchaReady = true;
      // 深拷贝验证数据，防止 reset 后引用被清空
      const captchaData = { ...obj.getValidate() };
      closeCaptchaModal();
      if (typeof captchaSuccessCallback === 'function') {
        await captchaSuccessCallback(captchaData);
        captchaSuccessCallback = null;
      }
      
      captchaReady = false;
    });

    obj.onError(function() {
      showToast('验证码加载失败，请刷新重试', 'error');
    });
  });
}

async function doSaveProfile(captchaData) {
  const fd = new FormData();
  fd.append('action',  'update');
  fd.append('name',    document.getElementById('f-name').value.trim());
  fd.append('bio',     document.getElementById('f-bio').value.trim());
  fd.append('website', document.getElementById('f-website').value.trim());
  fd.append('phone',   document.getElementById('f-phone').value.trim());
  for (const [k, v] of Object.entries(captchaData)) fd.append(k, v);

  const res  = await fetch('/api/user.php', { method: 'POST', body: fd });
  const data = await res.json();
  showToast(data.msg || (data.ok ? '保存成功' : '保存失败'), data.ok ? 'success' : 'error');
}

async function doSaveAvatar(captchaData) {
  const fd = new FormData();
  fd.append('action',  'update_avatar');
  fd.append('picture', pendingAvatarUrl);
  for (const [k, v] of Object.entries(captchaData)) fd.append(k, v);

  const res  = await fetch('/api/user.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    // 更新页面头像显示
    const newSrc = pendingAvatarUrl;
    document.getElementById('main-avatar').src = newSrc;
    document.querySelectorAll('#topbar img').forEach(img => img.src = newSrc);
    pendingAvatarUrl = '';
  }
  showToast(data.msg || (data.ok ? '头像已更新，可能需要1-2分钟同步~' : '保存失败'), data.ok ? 'success' : 'error');
}

// ── My projects ───────────────────────────────────────────────
async function loadMyProjects() {
  const res  = await fetch('/api/project.php?action=my_projects');
  const data = await res.json();
  const wrap = document.getElementById('my-projects');

  if (!data.ok || !data.projects.length) {
    wrap.innerHTML = '<p class="text-center text-sm py-8" style="color:var(--text-dim);">暂无项目，<a href="/home.php" class="underline" style="color:var(--link-color);">去发布第一个项目</a></p>';
    return;
  }

  const statusMap   = { pending:'badge-pending', approved:'badge-approved', rejected:'badge-rejected', taken_down:'badge-taken-down', hidden:'badge-hidden' };
  const statusLabel = { pending:'审核中', approved:'已通过', rejected:'已拒绝', taken_down:'已下架', hidden:'已隐藏' };

  wrap.innerHTML = data.projects.map(p => `
    <div class="proj-row" id="proj-row-${p.id}">
      <div style="flex:1;min-width:0">
        <div class="flex items-center gap-2 mb-1 flex-wrap">
          <span class="text-sm font-medium truncate" style="color:var(--text-primary);">${esc(p.name)}</span>
          <span class="text-xs font-mono" style="color:var(--text-muted);">${esc(p.version)}</span>
        </div>
        <p class="text-xs truncate" style="color:var(--text-dim);">${esc(p.description)}</p>
        ${p.reject_reason ? `<p class="text-xs mt-1 text-danger">拒绝原因：${esc(p.reject_reason)}</p>` : ''}
      </div>
      <div class="proj-row-actions">
        <span class="badge ${statusMap[p.status]||'badge-pending'}">${statusLabel[p.status]||p.status}</span>
        <button onclick='openEditProj(${JSON.stringify(p)})' class="btn btn-ghost btn-sm">编辑</button>
        <button onclick="toggleHide(${p.id},this)" class="btn btn-ghost btn-sm">${p.is_hidden ? '显示' : '隐藏'}</button>
        <button onclick="deleteProj(${p.id})" class="btn btn-danger btn-sm">删除</button>
      </div>
    </div>
  `).join('');
}


// ── 编辑弹窗标签切换 ──────────────────────────────────────────
let currentEditProjectId = null;

function switchEditTab(tab) {
  document.querySelectorAll('#editModal .submit-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('#editModal .submit-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('edit-tab-' + tab).classList.add('active');
  document.getElementById('edit-panel-' + tab).classList.add('active');

  if (tab === 'version') {
    // 切换到新版本面板时，自动获取项目信息
    const id = document.getElementById('edit-id').value;
    if (id) {
      currentEditProjectId = id;
      document.getElementById('nv-id').value = id;
      document.getElementById('nv-name').value = '加载中...';
      document.getElementById('nv-current-version').value = '加载中...';
      document.getElementById('nv-version').value = '';
      document.getElementById('nv-changelog').value = '';
      document.getElementById('nv-date').value = new Date().toISOString().slice(0,10);
      document.getElementById('nv-devsite').value = '';
      document.getElementById('nv-url').value = '';
      document.getElementById('nv-desc').value = '';
      document.getElementById('nv-tag').value = '';

      fetch('/api/project.php?action=detail&id=' + id)
        .then(r => r.json())
        .then(d => {
          if (d.ok && d.project) {
            document.getElementById('nv-name').value = d.project.name || '';
            document.getElementById('nv-current-version').value = d.project.version || '';
            document.getElementById('nv-date').value = new Date().toISOString().slice(0,10);
            document.getElementById('nv-devsite').value = d.project.developer_website || '';
            document.getElementById('nv-url').value = d.project.project_url || '';
            document.getElementById('nv-desc').value = d.project.description || '';
          } else {
            document.getElementById('nv-name').value = '获取失败';
            document.getElementById('nv-current-version').value = '获取失败';
          }
        })
        .catch(() => {
          document.getElementById('nv-name').value = '网络异常';
          document.getElementById('nv-current-version').value = '网络异常';
        });
    }
  }
}

// ── 新版本发布 ──────────────────────────────────────────────────
function openNewVersionModal(id) {
  switchEditTab('version');
  openModal('editModal');

  document.getElementById('nv-id').value = id;
  document.getElementById('nv-name').value = '';
  document.getElementById('nv-current-version').value = '';
  document.getElementById('nv-version').value = '';
  document.getElementById('nv-changelog').value = '';
  document.getElementById('nv-date').value = new Date().toISOString().slice(0,10);
  document.getElementById('nv-devsite').value = '';
  document.getElementById('nv-url').value = '';
  document.getElementById('nv-desc').value = '';
  document.getElementById('nv-tag').value = '';

  // 通过 API 获取完整项目信息
  fetch('/api/project.php?action=detail&id=' + id)
    .then(r => r.json())
    .then(d => {
      if (d.ok && d.project) {
        document.getElementById('nv-name').value = d.project.name || '';
        document.getElementById('nv-current-version').value = d.project.version || '';
        document.getElementById('nv-date').value = new Date().toISOString().slice(0,10);
        document.getElementById('nv-devsite').value = d.project.developer_website || '';
        document.getElementById('nv-url').value = d.project.project_url || '';
        document.getElementById('nv-desc').value = d.project.description || '';
      }
    })
    .catch(() => {});
}

async function submitNewVersion(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'publish_new_version');
  fd.append('project_id', document.getElementById('nv-id').value);
  fd.append('name', document.getElementById('nv-name').value);
  fd.append('version', document.getElementById('nv-version').value);
  fd.append('changelog', document.getElementById('nv-changelog').value);
  fd.append('release_date', document.getElementById('nv-date').value);
  fd.append('developer_website', document.getElementById('nv-devsite').value);
  fd.append('project_url', document.getElementById('nv-url').value);
  fd.append('description', document.getElementById('nv-desc').value);
  fd.append('tag', document.getElementById('nv-tag').value);
  fd.append('tech_stack', document.getElementById('edit-tech-stack').value);
  fd.append('cover_url', document.getElementById('edit-cover').value);

  const res = await fetch('/api/project.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    closeModal('editModal');
    loadMyProjects();
    showToast(data.msg || '新版本发布成功', 'success');
  } else {
    showToast(data.msg || '发布失败', 'error');
  }
}

let toastTimer = null;

function showToast(msg, type = 'success') {
  const wrap = document.getElementById('toast');

  // 去重：如果已有相同消息的 toast，跳过
  const existing = wrap.querySelectorAll('.toast-item');
  for (const el of existing) {
    if (el.textContent === msg) return;
  }

  // 限制同时显示的 toast 数量，超出移除旧 toast
  while (existing.length >= 3) {
    existing[0].classList.add('out');
    setTimeout(() => existing[0].remove(), 320);
  }

  const el = document.createElement('div');
  el.className = `toast-item ${type}`;
  el.textContent = msg;
  // 点击可关闭
  el.style.cursor = 'pointer';
  el.title = '点击关闭';
  el.addEventListener('click', () => {
    el.classList.add('out');
    setTimeout(() => el.remove(), 320);
  });
  wrap.appendChild(el);
  setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 320); }, 3500);
}

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── 安全设置 ──────────────────────────────────────────────────
let deleteAccountCaptchaData = null;

async function toggleInboxForward() {
  const toggle = document.getElementById('inbox-forward-toggle');
  const enabled = toggle.checked;
  try {
    const res = await fetch('/api/notification.php?action=forward_toggle', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'enabled=' + (enabled ? 1 : 0)
    });
    const d = await res.json();
    if (!d.ok) {
      toggle.checked = !enabled;
      showToast(d.msg || '操作失败', 'error');
      return;
    }
    showToast(enabled ? '站内信转发已开启' : '站内信转发已关闭');
  } catch(e) {
    toggle.checked = !enabled;
    showToast('操作失败，请重试', 'error');
  }
}

// ── 注销账号 ──
let deleteCountdownTimer = null;

function openDeleteAccount() {
  document.getElementById('captcha-modal-title').textContent = '安全验证';
  captchaSuccessCallback = doOpenDeleteConfirm;
  openCaptchaModal();
}

function doOpenDeleteConfirm(captchaData) {
  deleteAccountCaptchaData = captchaData;
  openModal('deleteConfirmModal');
  startDeleteCountdown();
}

function startDeleteCountdown() {
  let sec = 5;
  const btn = document.getElementById('confirm-delete-btn');
  // 重置按钮 HTML
  btn.disabled = true;
  btn.innerHTML = '我已知晓 (<span id="delete-countdown">5</span>s)';
  const span = document.getElementById('delete-countdown');
  if (deleteCountdownTimer) clearInterval(deleteCountdownTimer);
  deleteCountdownTimer = setInterval(() => {
    sec--;
    if (span) span.textContent = sec;
    if (sec <= 0) {
      clearInterval(deleteCountdownTimer);
      btn.disabled = false;
      btn.innerHTML = '我已知晓';
    }
  }, 1000);
}

async function confirmDeleteAccount() {
  if (!deleteAccountCaptchaData) {
    showToast('安全验证已过期，请重新操作', 'error');
    closeModal('deleteConfirmModal');
    return;
  }
  const btn = document.getElementById('confirm-delete-btn');
  btn.disabled = true;
  btn.textContent = 'Please be patient...';
  try {
    const fd = new FormData();
    fd.append('action', 'delete_account');
    for (const k in deleteAccountCaptchaData) fd.append(k, deleteAccountCaptchaData[k]);
    const res = await fetch('/api/user.php', { method: 'POST', body: fd });
    const d = await res.json();
    if (d.ok) {
      deleteAccountCaptchaData = null;
      closeModal('deleteConfirmModal');
      showToast('账号已注销，即将跳转...', 'success');
      setTimeout(() => { window.location.href = '/home.php'; }, 1500);
    } else {
      // 验证失败，清除旧数据，让用户重新验证
      deleteAccountCaptchaData = null;
      showToast(d.msg || '注销失败', 'error');
      btn.disabled = false;
      btn.innerHTML = '我已知晓';
      // 如果安全验证失败，关闭确认弹窗并提示重新操作
      if (d.msg && d.msg.includes('验证')) {
        closeModal('deleteConfirmModal');
        showToast('安全验证失败，请重新点击注销按钮完成验证', 'error');
      }
    }
  } catch(err) {
    showToast('网络异常，请稍后重试', 'error');
    btn.disabled = false;
    btn.innerHTML = '我已知晓';
  }
}

function openModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.display = 'flex';
  el.classList.add('show');
  document.body.classList.add('locked');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('show');
  document.body.classList.remove('locked');
  // 延迟隐藏
  setTimeout(() => { if (!el.classList.contains('show')) el.style.display = 'none'; }, 260);
  if (id === 'deleteConfirmModal') {
    deleteAccountCaptchaData = null;
    if (deleteCountdownTimer) clearInterval(deleteCountdownTimer);
    const btn = document.getElementById('confirm-delete-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '我已知晓 (<span id="delete-countdown">5</span>s)'; }
  }
}
</script>
</body>
</html>