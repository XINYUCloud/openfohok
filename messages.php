<?php
require_once __DIR__ . '/bootstrap.php';
$user    = requireLogin();
$userId  = $user['user_id'] ?? $user['id'];
$isAdmin = in_array($user['email'], ADMIN_EMAILS);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta property="og:title" content="站内信 · 文谶驿站">
<meta property="og:description" content="一个为独立开发者打造的开源项目展示空间。">
<meta property="og:image" content="https://fohok.xin/img/fohok-2.png">
<meta property="og:url" content="https://fohok.xin/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="文谶驿站 · 开发者开放仓库">
<title>站内信 · 文谶驿站</title>
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
    --bg-unread: #f8faff;
    --bg-like-section: #fff5f5;
    --text-primary: #171717;
    --text-secondary: #404040;
    --text-dim: #737373;
    --text-muted: #a3a3a3;
    --text-inverse: #fafafa;
    --border-primary: #e5e5e5;
    --border-secondary: #d4d4d4;
    --border-hover: #a3a3a3;
    --accent-color: #171717;
    --accent-hover: #404040;
    --link-color: #2563eb;
    --link-hover: #3b82f6;
    --danger-color: #dc2626;
    --danger-bg: #fef2f2;
    --danger-border: #fecaca;
    --dot-color: #3b82f6;
    --like-heart: #dc2626;
    --overlay-bg: rgba(0, 0, 0, 0.5);
    --shadow-card: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-lg: 0 4px 24px rgba(0,0,0,0.1);
    --scrollbar-track: #f5f5f5;
    --scrollbar-thumb: #d4d4d4;
  }

  @media (prefers-color-scheme: dark) {
    :root {
      --bg-primary: #050505;
      --bg-secondary: #0a0a0a;
      --bg-tertiary: #111111;
      --bg-hover: #1a1a1a;
      --bg-card: #0f0f0f;
      --bg-unread: #0f111a;
      --bg-like-section: #1a0a0a;
      --text-primary: #f5f5f5;
      --text-secondary: #d4d4d4;
      --text-dim: #a3a3a3;
      --text-muted: #737373;
      --text-inverse: #0a0a0a;
      --border-primary: #1a1a1a;
      --border-secondary: #222222;
      --border-hover: #404040;
      --accent-color: #e5e5e5;
      --accent-hover: #ffffff;
      --link-color: #60a5fa;
      --link-hover: #93c5fd;
      --danger-color: #f87171;
      --danger-bg: #1a0808;
      --danger-border: #3a1a1a;
      --dot-color: #60a5fa;
      --like-heart: #f87171;
      --overlay-bg: rgba(0, 0, 0, 0.88);
      --shadow-card: none;
      --shadow-lg: 0 4px 24px rgba(0,0,0,0.3);
      --scrollbar-track: #0a0a0a;
      --scrollbar-thumb: #2a2a2a;
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
    position: fixed;top:0;left:0;right:0;height:56px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-primary);
    z-index: 50;display: flex;align-items: center;
    padding: 0 20px;gap: 12px;backdrop-filter: blur(12px);
  }

  .btn{
    display:inline-flex;align-items:center;justify-content:center;
    gap:6px;padding:7px 14px;border-radius:6px;font-size:13px;
    font-weight:500;cursor:pointer;transition:all .2s;
    border:none;font-family:inherit;text-decoration:none;
  }
  .btn-ghost{
    background:transparent;color:var(--text-dim);
    border:1px solid var(--border-secondary);
  }
  .btn-ghost:hover{color:var(--text-primary);border-color:var(--border-hover)}
  .btn-primary{
    background:var(--accent-color);color:var(--text-inverse);
  }
  .btn-primary:hover{background:var(--accent-hover)}

  .dropdown{position:relative}
  .dropdown-menu{
    position:absolute;right:0;top:calc(100% + 8px);
    background:var(--bg-card);border:1px solid var(--border-secondary);
    border-radius:8px;min-width:150px;overflow:hidden;z-index:200;
    opacity:0;pointer-events:none;transform:translateY(-8px);
    transition:all .2s;box-shadow:var(--shadow-lg);
  }
  .dropdown-menu.show{opacity:1;pointer-events:all;transform:translateY(0)}
  .dropdown-item{
    display:flex;align-items:center;gap:10px;padding:10px 16px;
    font-size:14px;color:var(--text-secondary);cursor:pointer;
    transition:background .15s;text-decoration:none;
  }
  .dropdown-item:hover{background:var(--bg-hover);color:var(--text-primary)}
  .dropdown-item.danger{color:var(--danger-color)}
  .dropdown-item.danger:hover{background:var(--danger-bg)}

  .page-body{margin-top:56px}

  /* 内容区 */
  .content-wrap{
    max-width:800px;margin:0 auto;padding:28px 24px 60px;
  }

  .page-header{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:24px;
  }
  .page-title{
    font-size:18px;font-weight:500;color:var(--text-primary);
  }

  /* ── 点赞通知区块（置顶，单行摘要） ── */
  .like-section{
    background: var(--bg-like-section);
    border: 1px solid var(--danger-border);
    border-radius: 10px;
    padding: 0;
    margin-bottom: 20px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color .2s;
  }
  .like-section:hover{
    border-color: var(--danger-color);
  }
  .like-section-header{
    display: flex;align-items: center;gap: 8px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--danger-border);
    font-size: 12px;font-weight: 500;
    color: var(--danger-color);
    font-family: 'JetBrains Mono',monospace;
    letter-spacing: .04em;
  }
  .like-section-header svg{flex-shrink:0}

  .like-summary{
    display: flex;align-items: center;gap: 12px;
    padding: 14px 18px;
    transition: background .15s;
  }
  .like-section:hover .like-summary{background: var(--bg-hover)}

  .like-avatars{
    display: flex;align-items: center;flex-shrink: 0;
  }
  .like-avatars img{
    width: 32px;height: 32px;border-radius: 50%;
    object-fit: cover;border: 2px solid var(--bg-like-section);
    transition: transform .15s;
  }
  .like-avatars img:not(:first-child){margin-left: -10px}
  .like-avatars img:hover{transform: scale(1.15);z-index:5}

  .like-more{
    width: 32px;height: 32px;border-radius: 50%;
    background: var(--bg-tertiary);border: 2px solid var(--bg-like-section);
    display: flex;align-items: center;justify-content: center;
    font-size: 10px;font-family: 'JetBrains Mono',monospace;
    color: var(--text-muted);margin-left: -10px;
  }

  .like-summary-text{
    flex: 1;min-width: 0;
    font-size: 13px;color: var(--text-secondary);
    line-height: 1.5;
  }
  .like-summary-text .liker-name{
    font-weight: 500;color: var(--text-primary);
    cursor: pointer;transition: color .15s;
  }
  .like-summary-text .liker-name:hover{
    color: var(--link-color);text-decoration: underline;
  }
  .like-summary-badge{
    font-size: 11px;font-family: 'JetBrains Mono',monospace;
    color: var(--text-muted);flex-shrink:0;
    display: flex;align-items: center;gap: 4px;
  }
  .like-summary-dot{
    width: 7px;height: 7px;border-radius: 50%;
    background: var(--like-heart);flex-shrink:0;
  }
  .like-summary-arrow{
    font-size: 14px;color: var(--text-muted);
    transition: transform .2s;
  }

  /* ── 订阅者通知区块（新订阅者，与点赞并列） ── */
  .sub-section{
    background: var(--bg-unread);
    border: 1px solid var(--border-secondary);
    border-radius: 10px;
    padding: 0;
    margin-bottom: 20px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color .2s;
  }
  .sub-section:hover{
    border-color: var(--link-color);
  }
  .sub-section-header{
    display: flex;align-items: center;gap: 8px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--border-secondary);
    font-size: 12px;font-weight: 500;
    color: var(--link-color);
    font-family: 'JetBrains Mono',monospace;
    letter-spacing: .04em;
  }
  .sub-section-header svg{flex-shrink:0}

  .sub-summary{
    display: flex;align-items: center;gap: 12px;
    padding: 14px 18px;
    transition: background .15s;
  }
  .sub-section:hover .sub-summary{background: var(--bg-hover)}

  .sub-avatars{
    display: flex;align-items: center;flex-shrink: 0;
  }
  .sub-avatars img{
    width: 32px;height: 32px;border-radius: 50%;
    object-fit: cover;border: 2px solid var(--bg-unread);
    transition: transform .15s;
  }
  .sub-avatars img:not(:first-child){margin-left: -10px}
  .sub-avatars img:hover{transform: scale(1.15);z-index:5}

  .sub-more{
    width: 32px;height: 32px;border-radius: 50%;
    background: var(--bg-tertiary);border: 2px solid var(--bg-unread);
    display: flex;align-items: center;justify-content: center;
    font-size: 10px;font-family: 'JetBrains Mono',monospace;
    color: var(--text-muted);margin-left: -10px;
  }

  .sub-summary-text{
    flex: 1;min-width: 0;
    font-size: 13px;color: var(--text-secondary);
    line-height: 1.5;
  }
  .sub-summary-text .sub-name{
    font-weight: 500;color: var(--text-primary);
    cursor: pointer;transition: color .15s;
  }
  .sub-summary-text .sub-name:hover{
    color: var(--link-color);text-decoration: underline;
  }
  .sub-summary-badge{
    font-size: 11px;font-family: 'JetBrains Mono',monospace;
    color: var(--text-muted);flex-shrink:0;
    display: flex;align-items: center;gap: 4px;
  }
  .sub-summary-dot{
    width: 7px;height: 7px;border-radius: 50%;
    background: var(--link-color);flex-shrink:0;
  }
  .sub-summary-arrow{
    font-size: 14px;color: var(--text-muted);
    transition: transform .2s;
  }

  /* 普通通知列表 */
  .notif-list{display:flex;flex-direction:column;gap:0}

  .notif-item{
    display:flex;align-items:flex-start;gap:14px;
    padding:16px 18px;cursor:pointer;transition:background .15s;
    border-bottom:1px solid var(--border-primary);
    border-radius:6px;margin-bottom:1px;
    background:var(--bg-card);
  }
  .notif-item:hover{background:var(--bg-hover)}
  .notif-item.unread{background:var(--bg-unread)}
  .notif-item.unread:hover{background:var(--bg-hover)}

  .notif-dot{
    width:8px;height:8px;border-radius:50%;background:var(--dot-color);
    flex-shrink:0;margin-top:5px;opacity:0;
  }
  .notif-item.unread .notif-dot{opacity:1}

  .notif-body{flex:1;min-width:0}
  .notif-title{
    font-size:14px;font-weight:500;color:var(--text-primary);
    margin-bottom:4px;line-height:1.4;
  }
  .notif-content{
    font-size:12px;color:var(--text-dim);line-height:1.6;
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
    overflow:hidden;
  }
  .notif-time{
    font-size:11px;font-family:'JetBrains Mono',monospace;
    color:var(--text-muted);flex-shrink:0;margin-top:2px;
  }

  /* 类型图标 */
  .notif-icon{
    width:32px;height:32px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;margin-top:1px;
    background:var(--bg-tertiary);color:var(--text-dim);
  }
  .notif-icon.login{color:#3b82f6;background:#eff6ff}
  .notif-icon.welcome{color:#16a34a;background:#f0fdf4}
  .notif-icon.project_submitted{color:#f59e0b;background:#fffbeb}
  .notif-icon.project_approved{color:#16a34a;background:#f0fdf4}
  .notif-icon.project_rejected{color:#dc2626;background:#fef2f2}
  .notif-icon.project_deleted{color:#dc2626;background:#fef2f2}
  .notif-icon.project_taken_down{color:#ea580c;background:#fff7ed}
  .notif-icon.admin_message{color:#8b5cf6;background:#f5f3ff}
  @media (prefers-color-scheme: dark) {
    .notif-icon.login{color:#60a5fa;background:#0c1a30}
    .notif-icon.welcome{color:#4ade80;background:#052e16}
    .notif-icon.project_submitted{color:#fbbf24;background:#1c1917}
    .notif-icon.project_approved{color:#4ade80;background:#052e16}
    .notif-icon.project_rejected{color:#f87171;background:#1a0808}
    .notif-icon.project_deleted{color:#f87171;background:#1a0808}
    .notif-icon.project_taken_down{color:#fb923c;background:#1a0f05}
    .notif-icon.admin_message{color:#a78bfa;background:#1e1040}
  }

  /* ── 来源标签页 ── */
  .source-tabs{
    display:flex;gap:4px;margin-bottom:20px;
    border-bottom:1px solid var(--border-primary);
  }
  .source-tab{
    display:inline-flex;align-items:center;gap:6px;
    padding:9px 14px 10px;margin-bottom:-1px;
    background:transparent;border:none;border-bottom:2px solid transparent;
    font-family:inherit;font-size:13px;color:var(--text-dim);
    cursor:pointer;transition:color .15s,border-color .15s;
  }
  .source-tab:hover{color:var(--text-primary)}
  .source-tab.active{
    color:var(--text-primary);border-bottom-color:var(--accent-color);
    font-weight:500;
  }
  .source-tab .tab-dot{
    width:6px;height:6px;border-radius:50%;background:var(--dot-color);
    display:none;
  }
  .source-tab.has-unread .tab-dot{display:inline-block}

  /* ── 文谶账号通知（只读） ── */
  .wc-head{
    display:flex;align-items:flex-start;justify-content:space-between;
    gap:16px;margin-bottom:16px;
  }
  .wc-title{font-size:14px;font-weight:500;color:var(--text-primary)}
  .wc-desc{font-size:12px;color:var(--text-muted);line-height:1.7;margin-top:4px}
  .wc-desc a{color:var(--link-color);text-decoration:none}
  .wc-desc a:hover{text-decoration:underline}
  .wc-actions{display:flex;gap:8px;flex-shrink:0}
  .wc-item{cursor:default}
  .wc-meta{display:flex;align-items:center;gap:6px;margin-top:6px;flex-wrap:wrap}
  .wc-source,.wc-type-label{
    font-family:'JetBrains Mono',monospace;font-size:10px;line-height:1.6;
    padding:0 5px;border-radius:3px;
    border:1px solid var(--border-primary);color:var(--text-muted);
  }
  .wc-source{color:var(--text-secondary);border-color:var(--border-secondary)}
  .wc-appid{
    font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-muted);
    max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  }
  .wc-content{-webkit-line-clamp:3}
  .wc-notice{
    border:1px solid var(--border-secondary);border-radius:10px;
    background:var(--bg-card);padding:28px 24px;text-align:center;margin-top:4px;
  }
  .wc-notice-icon{
    width:44px;height:44px;margin:0 auto 14px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    background:var(--bg-tertiary);color:var(--text-dim);
  }
  .wc-notice-title{font-size:15px;font-weight:500;color:var(--text-primary);margin-bottom:6px}
  .wc-notice-desc{font-size:13px;color:var(--text-dim);line-height:1.7;max-width:440px;margin:0 auto}
  .wc-notice-actions{margin-top:18px}
  .wc-notice-foot{font-size:11px;color:var(--text-muted);line-height:1.7;margin-top:14px}
  .notif-icon.wc-type-system{color:#3b82f6;background:#eff6ff}
  .notif-icon.wc-type-developer{color:#8b5cf6;background:#f5f3ff}
  .notif-icon.wc-type-security{color:#f59e0b;background:#fffbeb}
  .notif-icon.wc-type-app{color:#16a34a;background:#f0fdf4}
  @media (prefers-color-scheme: dark) {
    .notif-icon.wc-type-system{color:#60a5fa;background:#0c1a30}
    .notif-icon.wc-type-developer{color:#a78bfa;background:#1e1040}
    .notif-icon.wc-type-security{color:#fbbf24;background:#1c1917}
    .notif-icon.wc-type-app{color:#4ade80;background:#052e16}
  }

  .empty-state{
    text-align:center;padding:60px 20px;color:var(--text-muted);
  }
  .empty-state .empty-icon{font-size:48px;margin-bottom:16px;opacity:.15}

  .pagination{
    display:flex;align-items:center;justify-content:center;
    gap:8px;margin-top:24px;
  }
  .pagination button{
    padding:6px 12px;border-radius:5px;font-size:12px;
    font-family:'JetBrains Mono',monospace;cursor:pointer;
    border:1px solid var(--border-secondary);background:transparent;
    color:var(--text-dim);transition:all .2s;
  }
  .pagination button:hover:not(:disabled){
    color:var(--text-primary);border-color:var(--border-hover);
  }
  .pagination button:disabled{opacity:.3;cursor:default}
  .pagination .page-info{font-size:12px;font-family:'JetBrains Mono',monospace;color:var(--text-muted)}

  .loading-spinner{
    display:flex;justify-content:center;padding:40px;
  }
  .loading-spinner .spinner{
    width:20px;height:20px;border:2px solid var(--border-secondary);
    border-top-color:var(--text-dim);border-radius:50%;
    animation:spin .6s linear infinite;
  }
  @keyframes spin{to{transform:rotate(360deg)}}

  /* Modal */
  .modal-bg{
    position:fixed;inset:0;background:var(--overlay-bg);
    z-index:100;display:flex;align-items:center;justify-content:center;
    padding:20px;opacity:0;pointer-events:none;transition:opacity .25s;
  }
  .modal-bg.show{opacity:1;pointer-events:all}
  .modal{
    background:var(--bg-card);border:1px solid var(--border-secondary);
    border-radius:12px;width:100%;max-width:520px;max-height:80vh;
    overflow-y:auto;transform:scale(.97) translateY(8px);
    transition:transform .25s;box-shadow:var(--shadow-lg);
  }
  .modal-bg.show .modal{transform:scale(1) translateY(0)}
  .modal-header{
    padding:18px 20px;border-bottom:1px solid var(--border-primary);
    display:flex;align-items:center;justify-content:space-between;
  }
  .modal-close{
    width:28px;height:28px;display:flex;align-items:center;justify-content:center;
    border-radius:5px;border:none;background:transparent;cursor:pointer;
    color:var(--text-muted);transition:all .2s;
  }
  .modal-close:hover{background:var(--bg-hover);color:var(--text-primary)}

  .modal-body{
    padding:20px 20px;
  }
  .detail-type-badge{
    display:inline-flex;align-items:center;gap:4px;
    font-size:11px;font-family:'JetBrains Mono',monospace;
    padding:2px 8px;border-radius:4px;margin-bottom:14px;
    border:1px solid var(--border-primary);
    background:var(--bg-tertiary);color:var(--text-dim);
  }
  .detail-content{
    font-size:14px;color:var(--text-secondary);line-height:1.8;
    white-space:pre-wrap;margin-top:12px;
  }

  /* 点赞弹窗内每行 */
  .like-detail-row{
    display: flex;align-items: center;gap: 12px;
    padding: 12px 20px;border-bottom: 1px solid var(--border-primary);
  }
  .like-detail-row:last-child{border-bottom:none}
  .like-detail-avatar{
    width: 40px;height: 40px;border-radius: 50%;
    object-fit: cover;flex-shrink: 0;
    border: 2px solid var(--danger-border);
    cursor: pointer;transition: transform .15s;
  }
  .like-detail-avatar:hover{transform: scale(1.1)}
  .like-detail-inner{
    flex: 1;min-width: 0;display: flex;align-items: center;gap: 6px;
    flex-wrap: wrap;
  }
  .like-detail-name{
    font-size: 14px;font-weight: 500;color: var(--text-primary);
    cursor: pointer;transition: color .15s;
  }
  .like-detail-name:hover{color: var(--link-color);text-decoration: underline}
  .like-detail-action{
    font-size: 13px;color: var(--text-dim);
  }
  .like-detail-proj{
    font-size: 14px;font-weight: 500;color: var(--link-color);
    cursor: pointer;transition: color .15s;
  }
  .like-detail-proj:hover{text-decoration: underline}
  .like-detail-time{
    font-size: 11px;font-family:'JetBrains Mono',monospace;
    color: var(--text-muted);flex-shrink:0;
  }

  body.locked{overflow:hidden}

  @media(max-width:640px){
    .content-wrap{padding:16px 16px 48px}
    .notif-item{padding:14px 14px}
    .like-summary{padding:12px 14px}
    .modal-body{padding:16px 16px}
    .like-detail-row{padding:12px 16px}
    .source-tab{padding:9px 10px 10px}
    .wc-head{flex-direction:column;gap:10px}
    .wc-actions{width:100%}
    .wc-actions .btn{flex:1}
  }
</style>
</head>
<body>

<!-- ══ TOPBAR ══ -->
<header id="topbar">
  <a href="/home.php" style="display:flex;align-items:center;gap:7px;opacity:.7;transition:opacity .2s;text-decoration:none;color:var(--text-dim);"
     onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.7">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    <span style="font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.08em" class="hidden sm:block">返回广场</span>
  </a>
  <div style="flex:1"></div>

  <div class="dropdown">
    <button onclick="dd('userMenu')" style="display:flex;align-items:center;gap:8px;background:none;border:none;cursor:pointer;padding:0" class="hover:opacity-80 transition-opacity">
      <img src="<?= htmlspecialchars($user['picture'] ?? 'https://ui-avatars.com/api/?name='.urlencode($user['name']??'U').'&background=222&color=fff') ?>"
           style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid var(--border-secondary)" alt="">
    </button>
    <div id="userMenu" class="dropdown-menu">
      <a href="/profile.php" class="dropdown-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        个人中心
      </a>
      <?php if ($isAdmin): ?>
      <a href="/admin/" class="dropdown-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        管理后台
      </a>
      <?php endif; ?>
      <a href="/logout.php" class="dropdown-item danger">退出登录</a>
    </div>
  </div>
</header>

<div class="page-body">
  <div class="content-wrap">
    <div class="page-header">
      <h1 class="page-title">站内信</h1>
      <div style="display:flex;gap:8px" id="local-actions">
        <button class="btn btn-ghost" style="padding:5px 14px;font-size:12px;display:none" onclick="clearAllNotifications()" id="clear-all-btn">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          清除所有
        </button>
        <button class="btn btn-ghost" style="padding:5px 14px;font-size:12px;display:none" onclick="markAllRead()" id="mark-all-btn">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          全部已读
        </button>
      </div>
    </div>

    <!-- 来源切换：本站站内信 / 文谶账号通知 -->
    <div class="source-tabs" role="tablist">
      <button class="source-tab active" id="tab-btn-local" role="tab" onclick="switchSourceTab('local')">本站站内信</button>
      <button class="source-tab" id="tab-btn-wenchan" role="tab" onclick="switchSourceTab('wenchan')">
        文谶账号通知<span class="tab-dot"></span>
      </button>
    </div>

    <div id="source-local">
    <!-- 点赞通知置顶区块-->
    <div id="like-section" class="like-section" style="display:none" onclick="openLikesModal()">
      <div class="like-section-header">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        收到点赞
      </div>
      <div class="like-summary">
        <div class="like-avatars" id="like-avatars"></div>
        <div class="like-summary-text" id="like-summary-text"></div>
        <div class="like-summary-badge" id="like-summary-badge"></div>
        <span class="like-summary-arrow">→</span>
      </div>
    </div>

    <!-- 订阅者通知置顶区块 -->
    <div id="sub-section" class="sub-section" style="display:none" onclick="openSubscribersModal()">
      <div class="sub-section-header">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        新订阅者
      </div>
      <div class="sub-summary">
        <div class="sub-avatars" id="sub-avatars"></div>
        <div class="sub-summary-text" id="sub-summary-text"></div>
        <div class="sub-summary-badge" id="sub-summary-badge"></div>
        <span class="sub-summary-arrow">→</span>
      </div>
    </div>

    <!-- 普通通知列表 -->
    <div id="notif-list" class="notif-list">
      <div class="loading-spinner"><div class="spinner"></div></div>
    </div>

    <div id="pagination" class="pagination" style="display:none"></div>

    <div id="empty-state" class="empty-state" style="display:none">
      <div class="empty-icon">&#9993;</div>
      <p style="font-size:14px;margin-bottom:4px">暂无通知╮( •́ω•̀ )╭</p>
      <p style="font-size:12px;color:var(--text-muted)">当你的项目有互动时，将会在这里收到通知</p>
    </div>
    </div><!-- /source-local -->

    <!-- 文谶账号通知（只读，来自账号中心） -->
    <div id="source-wenchan" style="display:none">
      <div class="wc-head">
        <div>
          <div class="wc-title">文谶账号通知</div>
          <div class="wc-desc">
            来自 <a href="https://account.fohok.xin/" target="_blank" rel="noopener">文谶账号中心</a> 的站内信，只读展示；已读状态以账号中心为准。
          </div>
        </div>
        <div class="wc-actions">
          <button class="btn btn-ghost" style="padding:5px 12px;font-size:12px" id="wc-refresh-btn" onclick="loadWenchan(wcPage, true)">刷新</button>
          <a class="btn btn-ghost" style="padding:5px 12px;font-size:12px" href="https://account.fohok.xin/" target="_blank" rel="noopener">账号中心 ↗</a>
        </div>
      </div>
      <div id="wc-body" class="wc-body"></div>
      <div id="wc-pagination" class="pagination" style="display:none"></div>
    </div>
  </div>
</div>

<!-- ══ 普通通知详情弹窗 ══ -->
<div class="modal-bg" id="detailModal">
  <div class="modal">
    <div class="modal-header">
      <span style="font-size:13px;font-weight:500;color:var(--text-primary)">通知详情</span>
      <button class="modal-close" onclick="closeDetail()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" id="detail-body"></div>
  </div>
</div>

<!-- ══ 点赞列表弹窗 ══ -->
<div class="modal-bg" id="likesModal">
  <div class="modal">
    <div class="modal-header">
      <span style="font-size:13px;font-weight:500;color:var(--text-primary);display:flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="color:var(--like-heart)"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        收到点赞
      </span>
      <button class="modal-close" onclick="closeLikesModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" id="likes-body"></div>
  </div>
</div>

<!-- ══ 订阅者列表弹窗 ══ -->
<div class="modal-bg" id="subscribersModal">
  <div class="modal">
    <div class="modal-header">
      <span style="font-size:13px;font-weight:500;color:var(--text-primary);display:flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--link-color)"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        新订阅者
      </span>
      <button class="modal-close" onclick="closeSubscribersModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" id="subscribers-body"></div>
  </div>
</div>

<script>
// ── Dropdown ──
function dd(id) { document.getElementById(id).classList.toggle('show'); }
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
});

const TYPE_ICONS = {
  login: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
  welcome: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>',
  project_submitted: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
  project_approved: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
  project_rejected: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
  project_deleted: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
  project_taken_down: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
  admin_message: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  new_subscriber: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',
  new_project: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>',
  project_reported: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  report_submitted: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
  report_result: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
  restricted: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
};

const TYPE_LABELS = {
  login: '登录通知',
  welcome: '欢迎消息',
  project_submitted: '项目提交',
  project_approved: '审核通过',
  project_rejected: '审核拒绝',
  project_deleted: '项目删除',
  project_taken_down: '项目下架',
  admin_message: '系统消息',
  project_liked: '点赞通知',
  new_subscriber: '新订阅者',
  new_project: '项目更新',
  project_reported: '项目被举报',
  report_submitted: '提交举报',
  report_result: '举报结果',
  restricted: '账号限制'
};

function formatDate(str) {
  if (!str) return '';
  const d = new Date(str.replace(' ', 'T') + '+08:00');
  const now = new Date();
  const diff = now - d;
  if (diff < 60000) return '刚刚';
  if (diff < 3600000) return Math.floor(diff/60000) + '分钟前';
  if (diff < 86400000) return Math.floor(diff/3600000) + '小时前';
  if (diff < 172800000) return '昨天';
  const pad = n => String(n).padStart(2,'0');
  return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+' '+pad(d.getHours())+':'+pad(d.getMinutes());
}

let currentPage = 1;
let totalPages = 1;
let allLikes = []; // 存储所有点赞通知
let allSubscribers = []; // 存储所有新订阅者通知

// ── 文谶账号通知（notification:read）─────────────────────
var wcPage = 1, wcPages = 1, wcLoaded = false, wcLastAt = 0, wcLoading = false;

const WC_ICONS = {
  system: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
  developer: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
  security: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>',
  app: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>'
};
const WC_LABELS = { system: '系统', developer: '开发者', security: '安全', app: '应用' };

function switchSourceTab(name, skipLoad) {
  const isWc = name === 'wenchan';
  document.getElementById('tab-btn-local').classList.toggle('active', !isWc);
  document.getElementById('tab-btn-wenchan').classList.toggle('active', isWc);
  document.getElementById('source-local').style.display = isWc ? 'none' : '';
  document.getElementById('source-wenchan').style.display = isWc ? '' : 'none';
  const actions = document.getElementById('local-actions');
  if (actions) actions.style.display = isWc ? 'none' : 'flex';

  if (isWc && !skipLoad && !wcLoading && (!wcLoaded || Date.now() - wcLastAt > 60000)) {
    loadWenchan(wcPage);
  }
}

async function loadWenchan(page, force) {
  page = Math.max(1, parseInt(page || 1, 10) || 1);
  if (wcLoading) return;

  const body = document.getElementById('wc-body');
  const pag = document.getElementById('wc-pagination');
  const btn = document.getElementById('wc-refresh-btn');
  wcLoading = true;
  wcPage = page;
  if (btn) btn.disabled = true;
  body.innerHTML = '<div class="loading-spinner"><div class="spinner"></div></div>';
  pag.style.display = 'none';

  try {
    const url = '/api/notification.php?action=wenchan_list&page=' + page + (force ? '&force=1' : '');
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    wcLoaded = true;
    wcLastAt = Date.now();

    if (!data.ok) {
      if (data.need_reauth) { renderWenchanAuth(data.msg); return; }
      if (data.config_missing) { renderWenchanUnavailable(data.msg); return; }
      renderWenchanError(data.msg || '读取失败，请稍后重试');
      return;
    }

    const items = Array.isArray(data.notifications) ? data.notifications : [];
    const wcTab = document.getElementById('tab-btn-wenchan');
    if (wcTab) wcTab.classList.toggle('has-unread', items.some(n => !n.is_read));
    if (!items.length) {
      body.innerHTML = '<div class="empty-state"><div class="empty-icon">&#9993;</div>'
        + '<p style="font-size:14px;margin-bottom:4px">账号中心暂无站内信</p>'
        + '<p style="font-size:12px;color:var(--text-muted)">在文谶账号中心收到的通知会显示在这里</p></div>';
      return;
    }

    body.innerHTML = '<div class="notif-list">' + items.map(renderWenchanItem).join('') + '</div>';
    wcPages = Math.max(1, parseInt(data.pages || 1, 10) || 1);
    if (wcPages > 1) {
      pag.style.display = '';
      pag.innerHTML = '<button onclick="loadWenchan(' + (page - 1) + ')" ' + (page <= 1 ? 'disabled' : '') + '>上一页</button>'
        + '<span class="page-info">' + page + ' / ' + wcPages + '</span>'
        + '<button onclick="loadWenchan(' + (page + 1) + ')" ' + (page >= wcPages ? 'disabled' : '') + '>下一页</button>';
    }
  } catch (err) {
    renderWenchanError('网络异常，请稍后重试');
  } finally {
    wcLoading = false;
    if (btn) btn.disabled = false;
  }
}

function renderWenchanItem(n) {
  const type = ['system', 'developer', 'security', 'app'].includes(n.type) ? n.type : 'system';
  const sourceLabel = n.source === 'app' ? '应用' : '系统';
  const appId = n.source_app_id
    ? '<span class="wc-appid" title="' + esc(n.source_app_id) + '">' + esc(n.source_app_id) + '</span>'
    : '';
  return '<div class="notif-item wc-item ' + (n.is_read ? '' : 'unread') + '">'
    + '<div class="notif-dot"></div>'
    + '<div class="notif-icon wc-type-' + type + '">' + WC_ICONS[type] + '</div>'
    + '<div class="notif-body">'
    +   '<div class="notif-title">' + esc(n.title || '（无标题）') + '</div>'
    +   (n.content ? '<div class="notif-content wc-content">' + esc(n.content) + '</div>' : '')
    +   '<div class="wc-meta"><span class="wc-source">' + sourceLabel + '</span>'
    +     '<span class="wc-type-label">' + WC_LABELS[type] + '</span>' + appId + '</div>'
    + '</div>'
    + '<div class="notif-time">' + formatDate(n.created_at) + '</div>'
    + '</div>';
}

function clearWenchanDot() {
  const tab = document.getElementById('tab-btn-wenchan');
  if (tab) tab.classList.remove('has-unread');
}

function renderWenchanAuth(msg) {
  clearWenchanDot();
  document.getElementById('wc-body').innerHTML = '<div class="wc-notice">'
    + '<div class="wc-notice-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>'
    + '<div class="wc-notice-title">开通文谶账号通知读取</div>'
    + '<div class="wc-notice-desc">开通后可在这里查看你在文谶账号中心收到的站内信。授权页会请求「读取你的站内信列表」，同意后仅用于本站展示。请使用与本站账号相同邮箱的文谶账号授权。</div>'
    + '<div class="wc-notice-actions">'
    +   '<a class="btn btn-primary" href="/wenchan_authorize.php?next=/messages.php">开通并授权</a>'
    +   '<button class="btn btn-ghost" style="margin-left:8px" onclick="switchSourceTab(\'local\')">暂不开启</button>'
    + '</div>'
    + '<div class="wc-notice-foot">' + esc(msg || '尚未授权读取文谶账号通知') + '</div>'
    + '</div>';
}

function renderWenchanUnavailable(msg) {
  clearWenchanDot();
  document.getElementById('wc-body').innerHTML = '<div class="wc-notice">'
    + '<div class="wc-notice-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>'
    + '<div class="wc-notice-title">功能暂未开通</div>'
    + '<div class="wc-notice-desc">站点尚未完成文谶账号通知读取配置，请联系管理员。</div>'
    + '<div class="wc-notice-foot">' + esc(msg || '') + '</div>'
    + '</div>';
}

function renderWenchanError(msg) {
  clearWenchanDot();
  document.getElementById('wc-body').innerHTML = '<div class="wc-notice">'
    + '<div class="wc-notice-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></div>'
    + '<div class="wc-notice-title">读取失败</div>'
    + '<div class="wc-notice-desc">' + esc(msg) + '</div>'
    + '<div class="wc-notice-actions"><button class="btn btn-primary" onclick="loadWenchan(wcPage, true)">重试</button></div>'
    + '</div>';
}

// ── 加载通知列表 ──
async function loadNotifications(page) {
  page = page || 1;
  currentPage = page;
  const wrap = document.getElementById('notif-list');
  const empty = document.getElementById('empty-state');
  const pag = document.getElementById('pagination');
  const likeSection = document.getElementById('like-section');

  wrap.innerHTML = '<div class="loading-spinner"><div class="spinner"></div></div>';

  try {
    const res = await fetch(`/api/notification.php?action=list&page=${page}`);
    const data = await res.json();

    if (!data.ok || !data.notifications.length) {
      wrap.innerHTML = '';
      likeSection.style.display = 'none';
      empty.style.display = '';
      pag.style.display = 'none';
      allLikes = [];
      return;
    }

    totalPages = Math.ceil(data.total / 20);
    empty.style.display = 'none';

    // 分离点赞通知、订阅者通知和普通通知
    const likes = data.notifications.filter(n => n.type === 'project_liked');
    const subs = data.notifications.filter(n => n.type === 'new_subscriber');
    const others = data.notifications.filter(n => n.type !== 'project_liked' && n.type !== 'new_subscriber');

    allLikes = likes;
    allSubscribers = subs;

    // 渲染点赞区块
    if (likes.length > 0) {
      likeSection.style.display = '';
      renderLikeSummary(likes);
    } else {
      likeSection.style.display = 'none';
    }

    // 渲染订阅者区块
    const subSection = document.getElementById('sub-section');
    if (subs.length > 0) {
      subSection.style.display = '';
      renderSubscriberSummary(subs);
    } else {
      subSection.style.display = 'none';
    }

    // 渲染普通通知
    if (others.length > 0) {
      wrap.innerHTML = others.map(n => `
        <div class="notif-item ${n.is_read ? '' : 'unread'}" id="notif-${n.id}" onclick="openDetail(${n.id})">
          <div class="notif-dot"></div>
          <div class="notif-icon ${n.type}">${TYPE_ICONS[n.type] || TYPE_ICONS.admin_message}</div>
          <div class="notif-body">
            <div class="notif-title">${esc(n.title)}</div>
            ${n.content ? `<div class="notif-content">${esc(n.content)}</div>` : ''}
          </div>
          <div class="notif-time">${formatDate(n.created_at)}</div>
        </div>
      `).join('');
    } else {
      wrap.innerHTML = '';
    }

    // 分页
    if (totalPages > 1) {
      pag.style.display = '';
      pag.innerHTML = `
        <button onclick="loadNotifications(${page-1})" ${page<=1?'disabled':''}>上一页</button>
        <span class="page-info">${page} / ${totalPages}</span>
        <button onclick="loadNotifications(${page+1})" ${page>=totalPages?'disabled':''}>下一页</button>
      `;
    } else {
      pag.style.display = 'none';
    }

  } catch(err) {
    wrap.innerHTML = '<div class="empty-state"><p style="font-size:14px">加载失败，请刷新重试</p></div>';
  }

  updateMarkAllBtn();
}

// ── 更新"全部已读"和"清除所有"按钮显隐 ──
function updateMarkAllBtn() {
  const markBtn = document.getElementById('mark-all-btn');
  const clearBtn = document.getElementById('clear-all-btn');
  const hasUnreadLikes = allLikes.some(l => !l.is_read);
  const hasUnreadOthers = document.querySelectorAll('#notif-list .notif-item.unread').length > 0;
  const hasAnyNotification = allLikes.length > 0 || document.querySelectorAll('#notif-list .notif-item').length > 0;

  // 全部已读按钮：有未读通知时显示
  markBtn.style.display = (hasUnreadLikes || hasUnreadOthers) ? '' : 'none';
  // 清除所有按钮：有任何通知时显示
  clearBtn.style.display = hasAnyNotification ? '' : 'none';
}

// ── 渲染点赞摘要行 ──
function renderLikeSummary(likes) {
  // 去重用户（按liker_id）
  const seen = new Map();
  const uniqueLikers = [];
  likes.forEach(l => {
    if (!seen.has(l.liker_id)) {
      seen.set(l.liker_id, true);
      uniqueLikers.push(l);
    }
  });

  // 头像
  const maxAvatars = 6;
  const avatarsEl = document.getElementById('like-avatars');
  let avatarsHtml = '';
  uniqueLikers.slice(0, maxAvatars).forEach(l => {
    avatarsHtml += `<a href="/user.php?id=${l.liker_id||''}" onclick="event.stopPropagation()" title="${esc(l.liker_name||'用户')}"><img src="${esc(l.liker_pic||'')}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(l.liker_name||'U')}&background=222&color=fff&size=32'" alt=""></a>`;
  });
  if (uniqueLikers.length > maxAvatars) {
    avatarsHtml += `<span class="like-more">+${uniqueLikers.length - maxAvatars}</span>`;
  }
  avatarsEl.innerHTML = avatarsHtml;

  // 摘要文字
  const names = uniqueLikers.map(l => `<a href="/user.php?id=${l.liker_id||''}" onclick="event.stopPropagation()" class="liker-name">${esc(l.liker_name||'用户')}</a>`).join('、');
  const textEl = document.getElementById('like-summary-text');
  textEl.innerHTML = `${names} 给你的项目点赞了`;

  // 未读计数
  const unreadCount = likes.filter(l => !l.is_read).length;
  const badgeEl = document.getElementById('like-summary-badge');
  if (unreadCount > 0) {
    badgeEl.innerHTML = `<span class="like-summary-dot"></span>${unreadCount} 条新赞`;
  } else {
    badgeEl.innerHTML = '';
  }
}

// ── 打开点赞列表弹窗 ──
async function openLikesModal() {
  const body = document.getElementById('likes-body');
  if (!allLikes.length) {
    body.innerHTML = '<div class="empty-state" style="padding:30px"><p style="font-size:13px;color:var(--text-muted)">暂无点赞</p></div>';
  } else {
    body.innerHTML = allLikes.map(l => `
      <div class="like-detail-row">
        <a href="/user.php?id=${l.liker_id||''}" onclick="event.stopPropagation()" title="查看 ${esc(l.liker_name||'用户')} 的主页">
          <img src="${esc(l.liker_pic||'')}" class="like-detail-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(l.liker_name||'U')}&background=222&color=fff&size=40'" alt="">
        </a>
        <div class="like-detail-inner">
          <a href="/user.php?id=${l.liker_id||''}" onclick="event.stopPropagation()" class="like-detail-name">${esc(l.liker_name||'用户')}</a>
          <span class="like-detail-action">点赞了你的项目</span>
          <a href="/prodeta.php?id=${l.related_id||0}" onclick="event.stopPropagation()" class="like-detail-proj">${esc(l.project_name || '未知项目')}</a>
        </div>
        <span class="like-detail-time">${formatDate(l.created_at)}</span>
      </div>
    `).join('');
  }

  document.getElementById('likesModal').classList.add('show');
  document.body.classList.add('locked');

  // 标记所有点赞通知为已读
  const unreadIds = allLikes.filter(l => !l.is_read).map(l => l.id);
  if (unreadIds.length > 0) {
    // 批量标记
    for (const id of unreadIds) {
      try {
        await fetch('/api/notification.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'action=mark_read&id=' + id
        });
      } catch(err) {}
    }
    // 更新本地状态
    allLikes.forEach(l => l.is_read = true);
    renderLikeSummary(allLikes);
    updateMarkAllBtn();
  }
}

function closeLikesModal() {
  document.getElementById('likesModal').classList.remove('show');
  document.body.classList.remove('locked');
}

// ── 渲染订阅者摘要行 ──
function renderSubscriberSummary(subs) {
  // 去重用户（按liker_id）
  const seen = new Map();
  const uniqueSubs = [];
  subs.forEach(s => {
    if (!seen.has(s.liker_id)) {
      seen.set(s.liker_id, true);
      uniqueSubs.push(s);
    }
  });

  // 头像
  const maxAvatars = 6;
  const avatarsEl = document.getElementById('sub-avatars');
  let avatarsHtml = '';
  uniqueSubs.slice(0, maxAvatars).forEach(s => {
    avatarsHtml += `<a href="/user.php?id=${s.liker_id||''}" onclick="event.stopPropagation()" title="${esc(s.liker_name||'用户')}"><img src="${esc(s.liker_pic||'')}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(s.liker_name||'U')}&background=222&color=fff&size=32'" alt=""></a>`;
  });
  if (uniqueSubs.length > maxAvatars) {
    avatarsHtml += `<span class="sub-more">+${uniqueSubs.length - maxAvatars}</span>`;
  }
  avatarsEl.innerHTML = avatarsHtml;

  // 摘要文字
  const names = uniqueSubs.map(s => `<a href="/user.php?id=${s.liker_id||''}" onclick="event.stopPropagation()" class="sub-name">${esc(s.liker_name||'用户')}</a>`).join('、');
  const textEl = document.getElementById('sub-summary-text');
  textEl.innerHTML = `${names} 订阅了你`;

  // 未读计数
  const unreadCount = subs.filter(s => !s.is_read).length;
  const badgeEl = document.getElementById('sub-summary-badge');
  if (unreadCount > 0) {
    badgeEl.innerHTML = `<span class="sub-summary-dot"></span>${unreadCount} 条新订阅`;
  } else {
    badgeEl.innerHTML = '';
  }
}

// ── 打开订阅者列表弹窗 ──
async function openSubscribersModal() {
  const body = document.getElementById('subscribers-body');
  if (!allSubscribers.length) {
    body.innerHTML = '<div class="empty-state" style="padding:30px"><p style="font-size:13px;color:var(--text-muted)">暂无新订阅者</p></div>';
  } else {
    body.innerHTML = allSubscribers.map(s => `
      <div class="like-detail-row">
        <a href="/user.php?id=${s.liker_id||''}" onclick="event.stopPropagation()" title="查看 ${esc(s.liker_name||'用户')} 的主页">
          <img src="${esc(s.liker_pic||'')}" class="like-detail-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(s.liker_name||'U')}&background=222&color=fff&size=40'" alt="">
        </a>
        <div class="like-detail-inner">
          <a href="/user.php?id=${s.liker_id||''}" onclick="event.stopPropagation()" class="like-detail-name">${esc(s.liker_name||'用户')}</a>
          <span class="like-detail-action">订阅了你</span>
        </div>
        <span class="like-detail-time">${formatDate(s.created_at)}</span>
      </div>
    `).join('');
  }

  document.getElementById('subscribersModal').classList.add('show');
  document.body.classList.add('locked');

  // 标记所有订阅者通知为已读
  const unreadIds = allSubscribers.filter(s => !s.is_read).map(s => s.id);
  if (unreadIds.length > 0) {
    for (const id of unreadIds) {
      try {
        await fetch('/api/notification.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'action=mark_read&id=' + id
        });
      } catch(err) {}
    }
    allSubscribers.forEach(s => s.is_read = true);
    renderSubscriberSummary(allSubscribers);
    updateMarkAllBtn();
  }
}

function closeSubscribersModal() {
  document.getElementById('subscribersModal').classList.remove('show');
  document.body.classList.remove('locked');
}

// ── 打开普通通知详情弹窗 ──
async function openDetail(id) {
  try {
    const res = await fetch(`/api/notification.php?action=detail&id=${id}`);
    const data = await res.json();
    if (!data.ok) return;

    const n = data.notification;
    const body = document.getElementById('detail-body');
    body.innerHTML = `
      <div class="detail-type-badge">${TYPE_ICONS[n.type] || ''} ${TYPE_LABELS[n.type] || n.type}</div>
      <div style="font-size:16px;font-weight:500;color:var(--text-primary);margin-bottom:4px">${esc(n.title)}</div>
      <div style="font-size:11px;color:var(--text-muted);font-family:'JetBrains Mono',monospace;margin-bottom:16px">${formatDate(n.created_at)}</div>
      ${n.content ? `<div class="detail-content">${esc(n.content)}</div>` : '<div class="detail-content" style="color:var(--text-muted)">暂无详细内容</div>'}
      ${n.related_id && (n.type === 'project_submitted' || n.type === 'project_approved' || n.type === 'project_rejected' || n.type === 'project_taken_down' || n.type === 'project_deleted' || n.type === 'new_project') ? `<a href="/prodeta.php?id=${n.related_id}" style="display:inline-block;margin-top:16px;font-size:13px;color:var(--link-color);text-decoration:none">查看项目详情 →</a>` : ''}
    `;

    showModal();
    updateNotifUnread(id);
    updateMarkAllBtn();
  } catch(err) {}
}

function showModal() {
  document.getElementById('detailModal').classList.add('show');
  document.body.classList.add('locked');
}

function closeDetail() {
  document.getElementById('detailModal').classList.remove('show');
  document.body.classList.remove('locked');
}

function updateNotifUnread(id) {
  const item = document.getElementById('notif-'+id);
  if (item) {
    item.classList.remove('unread');
    const dot = item.querySelector('.notif-dot');
    if (dot) dot.style.opacity = '0';
  }
}

// ── 全部已读 ──
async function markAllRead() {
  const btn = document.getElementById('mark-all-btn');
  btn.disabled = true;
  btn.textContent = '...';
  try {
    await fetch('/api/notification.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=mark_all_read'
    });
    loadNotifications(currentPage);
  } catch(err) {} finally {
    btn.disabled = false;
    btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>全部已读';
  }
}

// ── 清除所有通知 ──
async function clearAllNotifications() {
  if (!confirm('确定清除所有通知？此操作不可撤销。')) return;
  const btn = document.getElementById('clear-all-btn');
  btn.disabled = true;
  btn.textContent = '...';
  try {
    await fetch('/api/notification.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=delete_all'
    });
    allLikes = [];
    loadNotifications(1);
  } catch(err) {
    showToast('操作失败，请重试', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>清除所有';
  }
}

function showToast(msg, type) {
  // 去重：相同消息不重复显示
  const existing = document.querySelectorAll('.custom-toast');
  for (const el of existing) {
    if (el.textContent === msg) return;
  }
  const el = document.createElement('div');
  el.className = 'custom-toast';
  el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:10px 18px;border-radius:8px;font-size:13px;box-shadow:0 2px 12px rgba(0,0,0,0.15);animation:tIn .25s ease;max-width:300px;cursor:pointer';
  el.style.background = type === 'error' ? 'var(--danger-bg)' : 'var(--bg-card)';
  el.style.color = type === 'error' ? 'var(--danger-color)' : 'var(--text-primary)';
  el.style.border = '1px solid ' + (type === 'error' ? 'var(--danger-border)' : 'var(--border-secondary)');
  el.textContent = msg;
  el.title = '点击关闭';
  el.addEventListener('click', () => { el.style.opacity = '0'; el.style.transition = 'opacity .25s'; setTimeout(() => el.remove(), 260); });
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .25s'; setTimeout(() => el.remove(), 260); }, 2500);
}

// ── 关闭弹窗 ──
document.getElementById('detailModal').addEventListener('click', function(e) {
  if (e.target === this) closeDetail();
});
document.getElementById('likesModal').addEventListener('click', function(e) {
  if (e.target === this) closeLikesModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') { closeDetail(); closeLikesModal(); }
});

function esc(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

// ── 账号通知授权回调提示 ──
(function initWenchanNotice() {
  const state = new URLSearchParams(location.search).get('wenchan');
  if (!state) return;
  if (state === 'read_ok') {
    switchSourceTab('wenchan', true);
    loadWenchan(1, true);
    showToast('已开通文谶账号通知读取');
  } else if (state === 'cancelled') {
    showToast('已取消授权', 'error');
  } else if (state === 'failed') {
    showToast('授权失败，请重试', 'error');
  } else if (state === 'account_mismatch') {
    showToast('授权账号与当前登录账号不一致，未做变更', 'error');
  } else if (state === 'not_enabled') {
    switchSourceTab('wenchan', true);
    renderWenchanUnavailable('该功能尚未启用');
  }
  history.replaceState(null, '', location.pathname);
})();

// ── 启动 ──
loadNotifications(1);
</script>
</body>
</html>