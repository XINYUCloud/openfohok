<?php
require_once __DIR__ . '/../bootstrap.php';
$admin   = requireAdmin();
$adminId = (int)($admin['user_id'] ?? $admin['id']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理后台 · 文谶驿站</title>
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
    --version-color: #16a34a;
    --danger-color: #dc2626;
    --danger-bg: #fef2f2;
    --danger-border: #fecaca;
    --success-color: #16a34a;
    --success-bg: #f0fdf4;
    --success-border: #bbf7d0;
    --warn-color: #ca8a04;
    --warn-bg: #fefce8;
    --warn-border: #fde68a;
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
    --badge-guest-bg: #eff6ff;
    --badge-guest-color: #1e40af;
    --badge-guest-border: #bfdbfe;
    --stat-card-bg: #ffffff;
    --stat-card-border: #e5e5e5;
    --tab-active-color: #171717;
    --tab-active-border: #171717;
    --tab-inactive-color: #a3a3a3;
    --table-header-color: #a3a3a3;
    --table-header-border: #e5e5e5;
    --table-row-border: #f5f5f5;
    --table-row-hover: rgba(0,0,0,0.02);
    --overlay-bg: rgba(0, 0, 0, 0.5);
    --shadow-card: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-lg: 0 4px 24px rgba(0,0,0,0.1);
    --scrollbar-track: #f5f5f5;
    --scrollbar-thumb: #d4d4d4;
    --toast-bg: #ffffff;
    --toast-border: #e5e5e5;
    --dropdown-bg: #ffffff;
    --dropdown-border: #e5e5e5;
    --dropdown-item-color: #404040;
    --dropdown-item-hover-bg: #f0f0f0;
    --dropdown-item-hover-color: #171717;
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
      --version-color: #4ade80;
      --danger-color: #f87171;
      --danger-bg: #1a0808;
      --danger-border: #3a1a1a;
      --success-color: #4ade80;
      --success-bg: #052e16;
      --success-border: #14532d;
      --warn-color: #fbbf24;
      --warn-bg: #1c1108;
      --warn-border: #3a2808;
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
      --badge-guest-bg: #0f172a;
      --badge-guest-color: #60a5fa;
      --badge-guest-border: #1e3a5f;
      --stat-card-bg: #0f0f0f;
      --stat-card-border: #1a1a1a;
      --tab-active-color: #e5e5e5;
      --tab-active-border: #e5e5e5;
      --tab-inactive-color: #555555;
      --table-header-color: #555555;
      --table-header-border: #1a1a1a;
      --table-row-border: #0f0f0f;
      --table-row-hover: rgba(255,255,255,0.02);
      --overlay-bg: rgba(0, 0, 0, 0.88);
      --shadow-card: none;
      --shadow-lg: 0 4px 24px rgba(0,0,0,0.3);
      --scrollbar-track: #0a0a0a;
      --scrollbar-thumb: #333333;
      --toast-bg: #111111;
      --toast-border: #222222;
      --dropdown-bg: #111111;
      --dropdown-border: #222222;
      --dropdown-item-color: #bbbbbb;
      --dropdown-item-hover-bg: #1a1a1a;
      --dropdown-item-hover-color: #ffffff;
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
    padding: 9px 13px;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    transition: border-color .2s;
  }
  .inp:focus{border-color:var(--border-input-focus)}
  .inp::placeholder{color:var(--text-muted)}
  textarea.inp{resize:vertical}
  label{
    font-size: 11px;
    color: var(--text-dim);
    letter-spacing: .06em;
    text-transform: uppercase;
    margin-bottom: 3px;
    display: block;
  }

  .btn{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 7px 14px;
    border-radius: 5px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all .15s;
    border: none;
    font-family: inherit;
    text-decoration: none;
    white-space: nowrap;
  }
  .btn-primary{
    background: var(--accent-color);
    color: var(--text-inverse);
  }
  .btn-primary:hover{background:var(--accent-hover)}
  .btn-success{
    background: var(--success-bg);
    color: var(--success-color);
    border: 1px solid var(--success-border);
  }
  .btn-success:hover{background:var(--success-bg);opacity:0.8}
  .btn-danger{
    background: var(--danger-bg);
    color: var(--danger-color);
    border: 1px solid var(--danger-border);
  }
  .btn-danger:hover{background:var(--danger-bg);opacity:0.8}
  .btn-ghost{
    background: transparent;
    color: var(--text-dim);
    border: 1px solid var(--border-secondary);
  }
  .btn-ghost:hover{
    color: var(--text-primary);
    border-color: var(--border-hover);
  }
  .btn-warn{
    background: var(--warn-bg);
    color: var(--warn-color);
    border: 1px solid var(--warn-border);
  }
  .btn-warn:hover{background:var(--warn-bg);opacity:0.8}

  .submit-tab{
    padding:5px 12px;font-size:11px;border:none;background:transparent;
    color:var(--text-dim);cursor:pointer;border-radius:4px;transition:all .15s;
    white-space:nowrap;font-weight:500;font-family:inherit;
  }
  .submit-tab.active{background:var(--bg-card);color:var(--text-primary);box-shadow:0 0 0 1px var(--border-hover)}
  .submit-tab:not(.active):hover{color:var(--text-primary)}

  .badge{
    display: inline-block;
    padding: 2px 7px;
    border-radius: 99px;
    font-size: 10px;
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
  .badge-guest{
    background: var(--badge-guest-bg);
    color: var(--badge-guest-color);
    border: 1px solid var(--badge-guest-border);
  }

  /* 版本标签 */ .badge-tag{font-size:9px;padding:1px 6px;border-radius:99px;font-family:'JetBrains Mono',monospace;font-weight:500}
  .badge-tag.stable{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
  .badge-tag.beta{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe}
  .badge-tag.lts{background:#fefce8;color:#ca8a04;border:1px solid #fef08a}
  .badge-tag.deprecated{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
  @media (prefers-color-scheme:dark){
    .badge-tag.stable{background:#052e16;color:#4ade80;border-color:#14532d}
    .badge-tag.beta{background:#0c2240;color:#60a5fa;border-color:#1e3a5f}
    .badge-tag.lts{background:#1e1000;color:#facc15;border-color:#3a2a00}
    .badge-tag.deprecated{background:#1a0808;color:#f87171;border-color:#3a1a1a}
  }

  .tech-badge{
    display:inline-block;font-size:10px;padding:2px 8px;border-radius:4px;
    font-family:'JetBrains Mono',monospace;font-weight:500;
    background:var(--bg-tertiary);color:var(--text-dim);
    border:1px solid var(--border-primary);
  }
  .tech-badges{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px}

  .tab{
    padding: 8px 16px;
    font-size: 13px;
    color: var(--tab-inactive-color);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all .2s;
    white-space: nowrap;
  }
  .tab.active{
    color: var(--tab-active-color);
    border-color: var(--tab-active-border);
  }

  .stat-card{
    background: var(--stat-card-bg);
    border: 1px solid var(--stat-card-border);
    border-radius: 8px;
    padding: 20px;
    box-shadow: var(--shadow-card);
  }

  table{width:100%;border-collapse:collapse}
  th{
    text-align: left;
    padding: 9px 12px;
    font-size: 10px;
    color: var(--table-header-color);
    letter-spacing: .08em;
    text-transform: uppercase;
    border-bottom: 1px solid var(--table-header-border);
    font-weight: 500;
  }
  td{
    padding: 10px 12px;
    font-size: 13px;
    border-bottom: 1px solid var(--table-row-border);
    vertical-align: middle;
    color: var(--text-secondary);
  }
  tr:hover td{background:var(--table-row-hover)}

  /* Modal */
  .modal-bg{
    position: fixed;
    inset: 0;
    background: var(--overlay-bg);
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    opacity: 0;
    pointer-events: none;
    transition: opacity .22s;
  }
  .modal-bg.show{opacity:1;pointer-events:all}
  .modal{
    background: var(--bg-modal);
    border: 1px solid var(--border-secondary);
    border-radius: 12px;
    width: 100%;
    max-width: 680px;
    max-height: 92vh;
    overflow-y: auto;
    transform: scale(.97) translateY(8px);
    transition: transform .22s;
    box-shadow: var(--shadow-lg);
  }
  .modal-sm{max-width:440px}
  .modal-bg.show .modal{transform:scale(1) translateY(0)}

  /* Toast */
  #toast{
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 999;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .toast-item{
    background: var(--toast-bg);
    border: 1px solid var(--toast-border);
    color: var(--text-primary);
    padding: 11px 16px;
    border-radius: 8px;
    font-size: 13px;
    max-width: 300px;
    display: flex;
    align-items: center;
    gap: 8px;
    animation: tIn .25s ease;
    box-shadow: var(--shadow-lg);
  }
  .toast-item.out{animation:tOut .25s ease forwards}
  .toast-item.success::before{content:'✓';color:var(--success-color);font-weight:700}
  .toast-item.error::before{content:'✕';color:var(--danger-color);font-weight:700}
  @keyframes tIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}
  @keyframes tOut{to{opacity:0;transform:translateX(16px)}}

  .dropdown{position:relative}
  .dropdown-menu{
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    background: var(--dropdown-bg);
    border: 1px solid var(--dropdown-border);
    border-radius: 8px;
    min-width: 140px;
    overflow: hidden;
    z-index: 200;
    opacity: 0;
    pointer-events: none;
    transform: translateY(-6px);
    transition: all .18s;
    box-shadow: var(--shadow-lg);
  }
  .dropdown-menu.show{opacity:1;pointer-events:all;transform:translateY(0)}
  .dropdown-item{
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px 14px;
    font-size: 13px;
    color: var(--dropdown-item-color);
    cursor: pointer;
    transition: background .12s;
    text-decoration: none;
  }
  .dropdown-item:hover{
    background: var(--dropdown-item-hover-bg);
    color: var(--dropdown-item-hover-color);
  }
  .dropdown-item.danger{color:var(--danger-color)}
  .dropdown-item.danger:hover{background:var(--danger-bg)}

  body.locked{overflow:hidden}

  .actions-cell{display:flex;gap:6px;flex-wrap:nowrap}

  /* 辅助文字颜色 */
  .text-dim { color: var(--text-dim); }
  .text-muted { color: var(--text-muted); }
  .text-primary { color: var(--text-primary); }
  .text-secondary { color: var(--text-secondary); }
</style>
</head>
<body>

<!-- ══ TOPBAR ═══════════════════════════════════════════════ -->
<header id="topbar">
  <span class="font-mono text-xs tracking-widest text-muted">ADMIN</span>
  <span class="text-sm font-medium ml-1" style="color:var(--text-primary);">管理后台</span>
  <div class="flex-1"></div>
  <a href="/home.php" class="btn btn-ghost mr-2" style="padding:5px 12px;font-size:12px">← 前台</a>
  <div class="dropdown">
    <button onclick="dd('adminMenu')" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
      <img src="<?= htmlspecialchars($admin['picture']??'') ?>"
           onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($admin['name']??'A') ?>&background=222&color=fff'"
           class="w-8 h-8 rounded-full object-cover" style="border:1px solid var(--border-secondary)" alt="">
    </button>
    <div id="adminMenu" class="dropdown-menu">
      <a href="/logout.php" class="dropdown-item danger">退出登录</a>
    </div>
  </div>
</header>

<main style="margin-top:56px;padding:24px;max-width:1400px;margin-left:auto;margin-right:auto">

  <!-- Stats -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-7">
    <div class="stat-card"><p class="text-xs text-muted mb-1">总用户</p><p class="text-2xl font-mono" style="color:var(--text-primary);" id="s-users">-</p></div>
    <div class="stat-card"><p class="text-xs text-muted mb-1">总项目</p><p class="text-2xl font-mono" style="color:var(--text-primary);" id="s-total">-</p></div>
    <div class="stat-card"><p class="text-xs text-muted mb-1">待审核</p><p class="text-2xl font-mono" style="color:var(--warn-color);" id="s-pending">-</p></div>
    <div class="stat-card"><p class="text-xs text-muted mb-1">已通过</p><p class="text-2xl font-mono" style="color:var(--success-color);" id="s-approved">-</p></div>
  </div>

  <!-- Tabs -->
  <div class="flex border-b mb-5" style="border-color:var(--border-primary);overflow-x:auto;gap:0">
    <div class="tab active" onclick="switchTab('pending',this)">待审核</div>
    <div class="tab" onclick="switchTab('approved',this)">已通过</div>
    <div class="tab" onclick="switchTab('rejected',this)">已拒绝</div>
    <div class="tab" onclick="switchTab('users',this)">用户管理</div>
    <div class="tab" onclick="switchTab('app_updates',this)">App更新</div>
    <div class="tab" onclick="switchTab('announcements',this)">公告</div>
    <div class="tab" onclick="switchTab('notifications',this)">站内信</div>
    <div class="tab" onclick="switchTab('reports',this)">举报管理</div>
  </div>

  <!-- Project table -->
  <div id="tab-projects" style="overflow-x:auto">
    <table id="proj-table">
      <thead>
        <tr>
          <th>ID</th><th>项目名称</th><th>版本</th><th>提交者</th><th>状态</th><th>提交时间</th><th>操作</th>
        </tr>
      </thead>
      <tbody id="proj-tbody"><tr><td colspan="7" class="text-center py-10 text-sm text-muted">Please be patient...</td></tr></tbody>
    </table>
  </div>

  <!-- Users table -->
  <div id="tab-users" class="hidden" style="overflow-x:auto">
    <table>
      <thead>
        <tr><th>ID</th><th>用户</th><th>邮箱</th><th>注册时间</th><th>最后登录</th><th>项目数</th><th>操作</th></tr>
      </thead>
      <tbody id="users-tbody"><tr><td colspan="7" class="text-center py-10 text-sm text-muted">Please be patient...</td></tr></tbody>
    </table>
  </div>

  <!-- App 更新管理 -->
  <div id="tab-app-updates" class="hidden">
    <div class="flex justify-end mb-4">
      <button onclick="openCreateUpdate()" class="btn btn-primary">+ 添加版本</button>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr><th>ID</th><th>App 名称</th><th>版本号</th><th>包体大小</th><th>强制更新</th><th>更新时间</th><th>操作</th></tr>
        </thead>
        <tbody id="app-updates-tbody"><tr><td colspan="7" class="text-center py-10 text-sm text-muted">暂无版本记录</td></tr></tbody>
      </table>
    </div>
  </div>
<!-- 公告管理 -->
  <div id="tab-announcements" class="hidden">
    <div class="flex justify-end mb-4">
      <button onclick="openCreateAnnouncement()" class="btn btn-primary">+ 添加公告</button>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr><th>ID</th><th>标题</th><th>紧急程度</th><th>更新时间</th><th>操作</th></tr>
        </thead>
        <tbody id="announcements-tbody"><tr><td colspan="5" class="text-center py-10 text-sm text-muted">暂无公告</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- Notifications tab -->
  <div id="tab-notifications" class="hidden">
    <div class="flex justify-end mb-4 gap-2">
      <button onclick="openSendNotification()" class="btn btn-primary">+ 发送通知</button>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr><th>ID</th><th>接收者</th><th>类型</th><th>标题</th><th>状态</th><th>时间</th><th>操作</th></tr>
        </thead>
        <tbody id="notifications-tbody"><tr><td colspan="7" class="text-center py-10 text-sm text-muted">暂无通知</td></tr></tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:center;gap:6px;margin-top:16px" id="notif-pagination"></div>
  </div>

  <!-- Reports tab -->
  <div id="tab-reports" class="hidden">
    <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
      <button onclick="clearResolvedReports()" class="btn btn-ghost" style="font-size:11px;padding:4px 10px">清除已通过</button>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr><th>ID</th><th>项目</th><th>举报人</th><th>类型</th><th>原因</th><th>状态</th><th>时间</th><th>操作</th></tr>
        </thead>
        <tbody id="reports-tbody"><tr><td colspan="8" class="text-center py-10 text-sm text-muted">暂无举报</td></tr></tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:center;gap:6px;margin-top:16px" id="reports-pagination"></div>
  </div>
</main>

<!-- ══ 项目详情 & 审核弹窗 ══════════════════════════════════ -->
<div id="reviewModal" class="modal-bg" onclick="if(event.target===this)cm('reviewModal')">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <div class="flex items-center gap-2">
        <h3 id="rv-title" class="font-medium" style="color:var(--text-primary);">项目详情</h3>
        <div class="flex items-center gap-1 ml-4" style="background:var(--bg-tertiary);border:1px solid var(--border-primary);border-radius:6px;padding:2px">
          <button type="button" id="rv-tab-info" class="submit-tab active" onclick="switchRvTab('info')">基本信息</button>
          <button type="button" id="rv-tab-versions" class="submit-tab" onclick="switchRvTab('versions')">版本历史</button>
        </div>
      </div>
      <button onclick="cm('reviewModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div id="rv-body" class="p-5"></div>
    <div id="rv-versions-body" class="p-5" style="display:none;max-height:400px;overflow-y:auto"></div>
    <div id="rv-actions" class="p-5 border-t flex gap-3" style="border-color:var(--border-primary)"></div>
  </div>
</div>

<!-- ══ 填写拒绝/删除原因弹窗 ════════════════════════════════ -->
<div id="reasonModal" class="modal-bg" onclick="if(event.target===this)cm('reasonModal')">
  <div class="modal modal-sm" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <h3 id="reason-title" class="font-medium" style="color:var(--text-primary);">填写原因</h3>
      <button onclick="cm('reasonModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="p-5 space-y-4">
      <div>
        <label id="reason-label">原因</label>
        <textarea id="reason-input" class="inp" rows="3" placeholder="请填写原因，将通过邮件发送给用户..."></textarea>
      </div>
      <div class="flex gap-3">
        <button onclick="cm('reasonModal')" class="btn btn-ghost flex-1">取消</button>
        <button id="reason-confirm" class="btn btn-danger flex-1">确认</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ 编辑用户弹窗 ══════════════════════════════════════════ -->
<div id="editUserModal" class="modal-bg" onclick="if(event.target===this)cm('editUserModal')">
  <div class="modal modal-sm" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <h3 class="font-medium" style="color:var(--text-primary);">编辑用户</h3>
      <button onclick="cm('editUserModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="editUserForm" class="p-5 space-y-4" onsubmit="submitEditUser(event)">
      <input type="hidden" id="eu-id">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label>用户名 *</label>
          <input id="eu-name" class="inp" placeholder="昵称" required>
        </div>
        <div>
          <label>邮箱</label>
          <input id="eu-email" type="email" class="inp" placeholder="邮箱地址">
        </div>
      </div>
      <div>
        <label>个人简介</label>
        <textarea id="eu-bio" class="inp" rows="2" placeholder="选填"></textarea>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label>个人网站</label>
          <input id="eu-website" type="url" class="inp" placeholder="https://...">
        </div>
        <div>
          <label>电话</label>
          <input id="eu-phone" class="inp" placeholder="选填">
        </div>
      </div>
      <div class="flex gap-3 pt-1">
        <button type="button" onclick="cm('editUserModal')" class="btn btn-ghost flex-1">取消</button>
        <button type="submit" class="btn btn-primary flex-1">保存</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ 用户限制弹窗 ══════════════════════════════════════ -->
<div id="userRestrictModal" class="modal-bg" onclick="if(event.target===this)cm('userRestrictModal')">
  <div class="modal modal-sm" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <h3 class="font-medium" style="color:var(--text-primary);">用户限制</h3>
      <button onclick="cm('userRestrictModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="p-5 space-y-3">
      <div id="ur-restrict-user-info" class="p-3 rounded-lg border text-sm" style="background:var(--bg-tertiary);border-color:var(--border-primary);"></div>
      <div id="ur-restriction-status" class="text-xs text-muted"></div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label style="font-size:11px">限制类型</label>
          <select id="ur-restrict-type" class="inp" style="font-size:12px;padding:6px 10px">
            <option value="publish">限制发布</option>
            <option value="login">限制登录</option>
          </select>
        </div>
        <div>
          <label style="font-size:11px">限制时长</label>
          <div style="display:flex;gap:0">
            <input type="number" id="ur-restrict-duration" class="inp" min="1" value="1" style="font-size:12px;padding:6px 10px;border-radius:6px 0 0 6px;flex:1">
            <select id="ur-restrict-unit" class="inp" style="font-size:12px;padding:6px 8px;border-radius:0 6px 6px 0;width:auto;min-width:60px;border-left:0">
              <option value="60">时</option>
              <option value="1440">天</option>
              <option value="43200">月</option>
              <option value="525600">年</option>
            </select>
          </div>
        </div>
      </div>
      <div>
        <label style="font-size:11px">限制原因</label>
        <input id="ur-restrict-reason" class="inp" placeholder="如：项目违规" style="font-size:12px;padding:6px 10px">
      </div>
      <div class="flex gap-3">
        <button type="button" onclick="submitUserRestrict()" class="btn btn-danger" style="flex:1;font-size:12px;padding:8px">确认限制</button>
        <button type="button" id="ur-unrestrict-login-btn" onclick="unrestrictUserFromModal('login')" class="btn btn-ghost" style="flex:1;font-size:12px;padding:8px;display:none">解除登录限制</button>
        <button type="button" id="ur-unrestrict-publish-btn" onclick="unrestrictUserFromModal('publish')" class="btn btn-ghost" style="flex:1;font-size:12px;padding:8px;display:none">解除发布限制</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ 添加/编辑 App 更新弹窗 ════════════════════════════════ -->
<div id="updateModal" class="modal-bg" onclick="if(event.target===this)cm('updateModal')">
  <div class="modal modal-sm" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <h3 id="update-modal-title" class="font-medium" style="color:var(--text-primary);">添加 App 版本</h3>
      <button onclick="cm('updateModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="updateForm" class="p-5 space-y-3" onsubmit="submitUpdate(event)" style="max-height:70vh;overflow-y:auto">
      <input type="hidden" id="update-id-field">
      <div>
        <label>App 名称 *</label>
        <input id="update-app-name" class="inp" placeholder="例如：文谶驿站" required>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label>版本号 *</label>
          <input id="update-version" class="inp" placeholder="例如：1.0.1" required>
        </div>
        <div>
          <label>包体大小</label>
          <input id="update-package-size" class="inp" placeholder="例如：12.5 MB">
        </div>
      </div>
      <div>
        <label>下载地址 *</label>
        <input id="update-download-url" type="url" class="inp" placeholder="https://..." required>
      </div>
      <div>
        <label>更新介绍</label>
        <textarea id="update-changelog" class="inp" rows="3" placeholder="填写本次更新的内容..."></textarea>
      </div>
      <div>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" id="update-is-force" style="width:16px;height:16px">
          <span style="font-size:13px;color:var(--text-secondary)">强制更新（用户必须更新后才能使用）</span>
        </label>
      </div>
      <div class="flex gap-3 pt-1">
        <button type="button" onclick="cm('updateModal')" class="btn btn-ghost flex-1">取消</button>
        <button type="submit" class="btn btn-primary flex-1">保存</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ 公告弹窗 ══════════════════════════════════════════ -->
<div id="announcementModal" class="modal-bg" onclick="if(event.target===this)cm('announcementModal')">
  <div class="modal modal-lg" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <h3 id="ann-modal-title" class="font-medium" style="color:var(--text-primary);">添加公告</h3>
      <button onclick="cm('announcementModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="announcementForm" class="p-5 space-y-3" onsubmit="submitAnnouncement(event)" style="max-height:70vh;overflow-y:auto">
      <input type="hidden" id="ann-id-field">
      <div>
        <label>标题 *</label>
        <input id="ann-title" class="inp" placeholder="公告标题" required>
      </div>
      <div>
        <label>紧急程度 *</label>
        <select id="ann-severity" class="inp" required>
          <option value="notice">通知</option>
          <option value="info">提示</option>
          <option value="important">重要</option>
          <option value="warning">警告</option>
        </select>
      </div>
      <div>
        <label>内容 *（支持 HTML）</label>
        <textarea id="ann-content" class="inp" rows="8" placeholder="公告内容，支持 HTML 标签..." required></textarea>
      </div>
      <div class="flex gap-3 pt-1">
        <button type="button" onclick="cm('announcementModal')" class="btn btn-ghost flex-1">取消</button>
        <button type="submit" class="btn btn-primary flex-1">保存</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ 限制用户弹窗 ══════════════════════════════════════ -->
<div id="restrictModal" class="modal-bg" onclick="if(event.target===this)cm('restrictModal')">
  <div class="modal modal-lg" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <h3 class="font-medium" style="color:var(--text-primary);">处罚管理</h3>
      <button onclick="cm('restrictModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="restrictForm" class="p-5 space-y-3" onsubmit="submitRestrict(event)" style="max-height:70vh;overflow-y:auto">
      <input type="hidden" id="restrict-user-id">
      <input type="hidden" id="restrict-report-id">
      <input type="hidden" id="restrict-project-id">
      <div class="p-3 rounded-lg border text-sm" style="background:var(--bg-tertiary);border-color:var(--border-primary);" id="restrict-user-info"></div>
      <!-- 项目处罚 -->
      <div style="border:1px solid var(--border-primary);border-radius:6px;padding:12px;">
        <label style="font-size:12px;font-weight:600;color:var(--text-dim);display:block;margin-bottom:8px">项目处罚</label>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;color:var(--text-primary)">
            <input type="checkbox" id="punish-reject" value="reject" onchange="updatePunishExclusive(this)"> 下架项目（状态改为已拒绝）
          </label>
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;color:var(--text-primary)">
            <input type="checkbox" id="punish-delete" value="delete" onchange="updatePunishExclusive(this)"> 删除项目
          </label>
        </div>
      </div>
      <!-- 账号处罚 -->
      <div style="border:1px solid var(--border-primary);border-radius:6px;padding:12px;">
        <label style="font-size:12px;font-weight:600;color:var(--text-dim);display:block;margin-bottom:8px">账号处罚</label>
        <div>
          <label style="font-size:11px">限制类型</label>
          <select id="restrict-type" class="inp" onchange="document.getElementById('restrict-duration-row').style.display=this.value?'':'none'">
            <option value="">不限制</option>
            <option value="publish">限制发布项目</option>
            <option value="login">限制登录</option>
          </select>
        </div>
        <div id="restrict-duration-row" style="display:none;margin-top:8px">
          <label style="font-size:11px">限制时长</label>
          <div style="display:flex;gap:0">
            <input type="number" id="restrict-duration" class="inp" min="1" value="1" style="border-radius:6px 0 0 6px;flex:1">
            <select id="restrict-duration-unit" class="inp" style="border-radius:0 6px 6px 0;width:auto;min-width:60px;border-left:0">
              <option value="60">时</option>
              <option value="1440">天</option>
              <option value="43200">月</option>
              <option value="525600">年</option>
            </select>
          </div>
        </div>
      </div>
      <div>
        <label>处罚原因</label>
        <textarea id="restrict-reason" class="inp" rows="3" placeholder="处罚原因（将展示给用户）"></textarea>
      </div>
      <div class="flex gap-3 pt-1">
        <button type="button" onclick="cm('restrictModal')" class="btn btn-ghost flex-1">取消</button>
        <button type="submit" class="btn btn-danger flex-1">确认处罚</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ 发送站内信弹窗 ══════════════════════════════════════ -->
<div id="notifSendModal" class="modal-bg" onclick="if(event.target===this)cm('notifSendModal')">
  <div class="modal modal-lg" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <h3 class="font-medium" style="color:var(--text-primary);">发送站内信</h3>
      <button onclick="cm('notifSendModal')" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-900 transition-colors" style="color:var(--text-dim);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="notifSendForm" class="p-5 space-y-3" onsubmit="submitSendNotification(event)" style="max-height:70vh;overflow-y:auto">
      <div>
        <label>选择用户 *</label>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <input type="text" id="notif-user-search" class="inp" placeholder="搜索用户..." oninput="filterNotifUsers()" style="margin-bottom:0">
          <button type="button" class="btn btn-ghost btn-sm" style="white-space:nowrap;font-size:11px;padding:8px 12px" onclick="selectAllNotifUsers()">全选</button>
          <button type="button" class="btn btn-ghost btn-sm" style="white-space:nowrap;font-size:11px;padding:8px 12px" onclick="deselectAllNotifUsers()">取消</button>
        </div>
        <div id="notif-user-list" class="inp" style="max-height:200px;overflow-y:auto;padding:4px;background:var(--bg-input, #fafafa)">
          <span style="font-size:12px;color:var(--text-muted)">加载中...</span>
        </div>
        <p style="font-size:11px;color:var(--text-muted);margin-top:4px">已选择 <span id="notif-selected-count">0</span> 位用户</p>
      </div>
      <div>
        <label>标题 *</label>
        <input id="notif-title" class="inp" placeholder="通知标题" required>
      </div>
      <div>
        <label>内容</label>
        <textarea id="notif-content" class="inp" rows="6" placeholder="通知内容（可选）"></textarea>
      </div>
      <div class="flex gap-3 pt-1">
        <button type="button" onclick="cm('notifSendModal')" class="btn btn-ghost flex-1">取消</button>
        <button type="submit" class="btn btn-primary flex-1">发送</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ 通用确认对话框 ══════════════════════════════════════ -->
<div id="confirmModal" class="modal-bg">
  <div class="modal modal-sm" style="text-align:center;padding:32px 24px">
    <div id="confirm-icon" style="margin-bottom:12px;display:flex;justify-content:center">
      <svg id="confirm-icon-svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
    <h3 id="confirm-title" style="font-size:15px;font-weight:600;margin-bottom:6px;color:var(--text-primary)"></h3>
    <p id="confirm-message" style="font-size:13px;color:var(--text-dim);line-height:1.6;margin-bottom:20px"></p>
    <div class="flex gap-3">
      <button id="confirm-cancel-btn" class="btn btn-ghost flex-1" style="font-size:12px;padding:8px">取消</button>
      <button id="confirm-ok-btn" class="btn btn-primary flex-1" style="font-size:12px;padding:8px">确认</button>
    </div>
  </div>
</div>

<!-- ══ 通用输入对话框 ══════════════════════════════════════ -->
<div id="promptModal" class="modal-bg">
  <div class="modal modal-sm" style="padding:28px 24px">
    <h3 id="prompt-title" style="font-size:15px;font-weight:600;margin-bottom:4px;color:var(--text-primary)"></h3>
    <p id="prompt-message" style="font-size:13px;color:var(--text-dim);margin-bottom:16px;line-height:1.6"></p>
    <input id="prompt-input" class="inp" style="margin-bottom:16px;font-size:13px;padding:8px 12px" placeholder="">
    <div class="flex gap-3">
      <button id="prompt-cancel-btn" class="btn btn-ghost flex-1" style="font-size:12px;padding:8px">取消</button>
      <button id="prompt-ok-btn" class="btn btn-primary flex-1" style="font-size:12px;padding:8px">确认</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
const ADMIN_ID = <?= $adminId ?>;
let currentTab = 'pending';
let reasonCb   = null; // 原因弹窗的回调

document.addEventListener('DOMContentLoaded', () => {
  loadStats();
  loadProjects('pending');
});

// ── Dropdown ─────────────────────────────────────────────────
function dd(id) { document.getElementById(id).classList.toggle('show'); }
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
});

// ── Modal ─────────────────────────────────────────────────────
function om(id) { document.getElementById(id).classList.add('show'); document.body.classList.add('locked'); }
function cm(id) { document.getElementById(id).classList.remove('show'); document.body.classList.remove('locked'); }

// ── 审核弹窗标签切换 ──────────────────────────────────────
let rvProjectId = 0;
function switchRvTab(tab) {
  document.querySelectorAll('#reviewModal .submit-tab').forEach(t => t.classList.remove('active'));
  document.getElementById('rv-tab-' + tab).classList.add('active');
  document.getElementById('rv-body').style.display = tab === 'info' ? '' : 'none';
  document.getElementById('rv-versions-body').style.display = tab === 'versions' ? '' : 'none';
  if (tab === 'versions') loadRvVersions();
}

async function loadRvVersions() {
  const body = document.getElementById('rv-versions-body');
  body.innerHTML = '<div class="text-center py-8 text-sm text-muted">加载中...</div>';
  try {
    const d = await api('?action=version_history&project_id=' + rvProjectId);
    if (!d.ok || !d.versions || !d.versions.length) {
      body.innerHTML = '<div class="text-center py-8 text-sm text-muted">暂无版本历史</div>';
      return;
    }
    body.innerHTML = d.versions.map(v => {
      const tagHtml = v.tag ? `<span class="badge-tag ${v.tag}">${v.tag}</span>` : '';
      return `<div class="flex items-start justify-between py-3 border-b" style="border-color:var(--border-primary);">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="font-mono text-xs" style="color:var(--version-color);">${esc(v.version)}</span>
            ${tagHtml}
          </div>
          ${v.release_date ? `<div class="text-xs text-muted font-mono">${esc(v.release_date)}</div>` : ''}
          ${v.changelog ? `<div class="text-xs text-dim mt-1 whitespace-pre-wrap">${esc(v.changelog)}</div>` : ''}
        </div>
        <button onclick="deleteVersion(${v.id})" class="btn btn-danger" style="font-size:10px;padding:2px 8px;flex-shrink:0;margin-left:12px">删除</button>
      </div>`;
    }).join('');
  } catch(e) {
    body.innerHTML = '<div class="text-center py-8 text-sm text-muted">加载失败</div>';
  }
}

async function deleteVersion(id) {
  if (!confirm('确定删除该版本记录？')) return;
  const d = await post({action:'delete_version', id});
  if (d.ok) { toast('版本记录已删除','success'); loadRvVersions(); }
  else toast(d.msg||'删除失败','error');
}

// ── 通用确认对话框 ─────────────────────────────────────────
function showConfirm(title, message, danger = false) {
  return new Promise(resolve => {
    document.getElementById('confirm-title').textContent = title;
    document.getElementById('confirm-message').textContent = message;
    const okBtn = document.getElementById('confirm-ok-btn');
    okBtn.className = danger ? 'btn btn-danger flex-1' : 'btn btn-primary flex-1';
    okBtn.style.cssText = 'font-size:12px;padding:8px';
    const svg = document.getElementById('confirm-icon-svg');
    svg.style.color = danger ? 'var(--danger-color)' : 'var(--text-dim)';
    svg.innerHTML = danger
      ? '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'
      : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
    om('confirmModal');
    okBtn.onclick = () => { cm('confirmModal'); resolve(true); };
    document.getElementById('confirm-cancel-btn').onclick = () => { cm('confirmModal'); resolve(false); };
    document.getElementById('confirmModal').onclick = e => { if (e.target === document.getElementById('confirmModal')) { cm('confirmModal'); resolve(false); } };
  });
}

// ── 通用输入对话框 ─────────────────────────────────────────
function showPrompt(title, message, placeholder = '') {
  return new Promise(resolve => {
    document.getElementById('prompt-title').textContent = title;
    document.getElementById('prompt-message').textContent = message;
    const input = document.getElementById('prompt-input');
    input.value = '';
    input.placeholder = placeholder;
    om('promptModal');
    input.focus();
    document.getElementById('prompt-ok-btn').onclick = () => { cm('promptModal'); resolve(input.value.trim()); };
    document.getElementById('prompt-cancel-btn').onclick = () => { cm('promptModal'); resolve(null); };
    document.getElementById('promptModal').onclick = e => { if (e.target === document.getElementById('promptModal')) { cm('promptModal'); resolve(null); } };
    input.onkeydown = e => { if (e.key === 'Enter') { cm('promptModal'); resolve(input.value.trim()); } };
  });
}

// ── Tabs ─────────────────────────────────────────────────────
function switchTab(tab, el) {
  currentTab = tab;
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('tab-projects').classList.add('hidden');
  document.getElementById('tab-users').classList.add('hidden');
  document.getElementById('tab-app-updates').classList.add('hidden');
  document.getElementById('tab-announcements').classList.add('hidden');
  document.getElementById('tab-notifications').classList.add('hidden');
  document.getElementById('tab-reports').classList.add('hidden');
  if (tab === 'users') {
    document.getElementById('tab-users').classList.remove('hidden');
    loadUsers();
  } else if (tab === 'app_updates') {
    document.getElementById('tab-app-updates').classList.remove('hidden');
    loadAppUpdates();
  } else if (tab === 'announcements') {
    document.getElementById('tab-announcements').classList.remove('hidden');
    loadAnnouncements();
  } else if (tab === 'notifications') {
    document.getElementById('tab-notifications').classList.remove('hidden');
    loadAdminNotifications();
  } else if (tab === 'reports') {
    document.getElementById('tab-reports').classList.remove('hidden');
    loadAdminReports();
  } else {
    document.getElementById('tab-projects').classList.remove('hidden');
    loadProjects(tab);
  }
}

// ── Stats ─────────────────────────────────────────────────────
async function loadStats() {
  const d = await api('?action=stats');
  if (!d.ok) return;
  document.getElementById('s-users').textContent    = d.users;
  document.getElementById('s-total').textContent    = d.total;
  document.getElementById('s-pending').textContent  = d.pending;
  document.getElementById('s-approved').textContent = d.approved;
}

// ── Projects ──────────────────────────────────────────────────
async function loadProjects(status) {
  const tbody = document.getElementById('proj-tbody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-sm text-muted">Please be patient...</td></tr>';
  const d = await api(`?action=projects&status=${status}`);
  if (!d.ok || !d.projects.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-sm text-muted">暂无数据</td></tr>';
    return;
  }
  const bMap = {pending:'badge-pending',approved:'badge-approved',rejected:'badge-rejected',taken_down:'badge-taken-down'};
  const lMap = {pending:'待审核',approved:'已通过',rejected:'已拒绝',taken_down:'已下架'};
  tbody.innerHTML = d.projects.map(p => `
    <tr>
      <td class="font-mono text-xs text-muted">#${p.id}</td>
      <td>
        <div class="flex items-center gap-2">
          <span style="color:var(--text-primary);font-weight:500;">${esc(p.name)}</span>
          ${p.is_guest ? '<span class="badge badge-guest">游客</span>' : ''}
        </div>
      </td>
      <td class="font-mono text-xs text-muted">${esc(p.version)}</td>
      <td>
        <div class="flex items-center gap-2">
          <img src="${esc(p.author_pic||'')}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(p.author_name||'U')}&background=222&color=fff&size=24'" class="w-5 h-5 rounded-full object-cover flex-shrink-0">
          <span class="text-dim text-xs truncate" style="max-width:100px">${esc(p.author_name||'-')}</span>
        </div>
      </td>
      <td><span class="badge ${bMap[p.status]||'badge-pending'}">${lMap[p.status]||p.status}</span></td>
      <td class="text-xs text-muted font-mono">${fd(p.created_at)}</td>
      <td>
        <div class="actions-cell">
          <button onclick='openReview(${JSON.stringify(p)})' class="btn btn-ghost">详情</button>
          ${p.status==='pending'
            ? `<button onclick="approve(${p.id})" class="btn btn-success">通过</button>
               <button onclick="openReason('reject',${p.id})" class="btn btn-danger">拒绝</button>`
            : ''}
          <button onclick="deleteProjBtn(${p.id},'${esc(p.status)}')" class="btn btn-danger">删除</button>
        </div>
      </td>
    </tr>
  `).join('');
}

// ── Project detail modal ──────────────────────────────────────
function openReview(p) {
  rvProjectId = p.id;
  switchRvTab('info');
  document.getElementById('rv-title').textContent = p.name;
  const c = [];
  if(p.contact_email)  c.push(`<a href="mailto:${esc(p.contact_email)}" style="color:var(--link-color)">${esc(p.contact_email)}</a>`);
  if(p.contact_phone)  c.push(esc(p.contact_phone));
  if(p.contact_qq)     c.push('QQ:'+esc(p.contact_qq));
  if(p.contact_wechat) c.push('微信:'+esc(p.contact_wechat));
  document.getElementById('rv-body').innerHTML = `
    <div class="space-y-4 text-sm">
      <div class="grid grid-cols-2 gap-3">
        <div><span class="text-xs text-muted">版本</span><p class="font-mono mt-0.5" style="color:var(--text-primary);">${esc(p.version)}</p></div>
        <div><span class="text-xs text-muted">发布日期</span><p class="mt-0.5" style="color:var(--text-primary);">${p.release_date||'-'}</p></div>
        <div><span class="text-xs text-muted">提交者</span><p class="mt-0.5" style="color:var(--text-primary);">${esc(p.author_name||'-')}</p></div>
        <div><span class="text-xs text-muted">邮箱</span><p class="mt-0.5" style="color:var(--text-primary);">${esc(p.author_email||'-')}</p></div>
      </div>
      <div><span class="text-xs text-muted">项目地址</span><p class="mt-0.5"><a href="${esc(p.project_url)}" target="_blank" style="color:var(--link-color);word-break:break-all">${esc(p.project_url)}</a></p></div>
      ${p.developer_website?`<div><span class="text-xs text-muted">开发者网站</span><p class="mt-0.5"><a href="${esc(p.developer_website)}" target="_blank" style="color:var(--link-color)">${esc(p.developer_website)}</a></p></div>`:''}
      <div><span class="text-xs text-muted">项目介绍</span><p class="mt-1 leading-relaxed whitespace-pre-wrap" style="color:var(--text-secondary);">${esc(p.description)}</p></div>
      ${renderTechBadges(p.tech_stack)}
      ${c.length?`<div><span class="text-xs text-muted">联系方式</span><p class="mt-0.5" style="color:var(--text-secondary);">${c.join(' · ')}</p></div>`:''}
      ${p.reject_reason?`<div style="background:var(--danger-bg);border:1px solid var(--danger-border);border-radius:6px;padding:10px 14px"><span class="text-xs" style="color:var(--danger-color);">拒绝原因</span><p class="mt-0.5 text-xs" style="color:var(--danger-color);">${esc(p.reject_reason)}</p></div>`:''}
    </div>`;
  document.getElementById('rv-actions').innerHTML = p.status==='pending' ? `
    <button onclick="approve(${p.id});cm('reviewModal')" class="btn btn-success flex-1">✓ 通过审核</button>
    <button onclick="cm('reviewModal');openReason('reject',${p.id})" class="btn btn-danger flex-1">✕ 拒绝</button>
    <button onclick="cm('reviewModal');deleteProjBtn(${p.id},'${p.status}')" class="btn btn-warn">删除</button>
  ` : `<button onclick="cm('reviewModal')" class="btn btn-ghost">关闭</button>
       <button onclick="cm('reviewModal');deleteProjBtn(${p.id},'${p.status}')" class="btn btn-danger">删除项目</button>`;
  om('reviewModal');
}

async function approve(id) {
  const d = await post({action:'approve',id});
  if (d.ok) { toast('审核已通过','success'); loadProjects(currentTab); loadStats(); }
  else toast(d.msg||'操作失败','error');
}

// ── 拒绝 & 删除（需填写原因的情况）──────────────────────────
function openReason(type, id) {
  const titles = { reject:'填写拒绝原因', delete:'填写删除原因' };
  const labels = { reject:'拒绝原因', delete:'删除原因' };
  document.getElementById('reason-title').textContent    = titles[type] || '填写原因';
  document.getElementById('reason-label').textContent    = labels[type] || '原因';
  document.getElementById('reason-confirm').textContent  = type === 'reject' ? '确认拒绝' : '确认删除';
  document.getElementById('reason-confirm').className    = 'btn btn-danger flex-1';
  document.getElementById('reason-input').value = '';

  reasonCb = async () => {
    const reason = document.getElementById('reason-input').value.trim();
    if (!reason) { toast('请填写原因','error'); return; }
    const action = type === 'reject' ? 'reject' : 'delete_project';
    const d = await post({ action, id, reason });
    if (d.ok) {
      cm('reasonModal');
      toast(type === 'reject' ? '已拒绝此项目' : '已删除并通知','success');
      loadProjects(currentTab);
      loadStats();
    } else {
      toast(d.msg||'操作失败','error');
    }
  };
  document.getElementById('reason-confirm').onclick = reasonCb;
  om('reasonModal');
}

async function deleteProjBtn(id, status) {
  const needReason = ['pending','rejected'].includes(status);
  if (needReason) {
    openReason('delete', id);
    return;
  }
  if (!(await showConfirm('确认删除项目', '确定删除该项目？此操作不可撤销。', true))) return;
  const d = await post({action:'delete_project',id});
  if (d.ok) { toast('已删除','success'); loadProjects(currentTab); loadStats(); }
  else toast(d.msg||'删除失败','error');
}

// ── Users ─────────────────────────────────────────────────────
async function loadUsers() {
  const tbody = document.getElementById('users-tbody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-sm text-muted">Please be patient...</td></tr>';
  const d = await api('?action=users');
  if (!d.ok || !d.users.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-sm text-muted">暂无用户</td></tr>';
    return;
  }
  tbody.innerHTML = d.users.map(u => `
    <tr id="urow-${u.id}">
      <td class="font-mono text-xs text-muted">#${u.id}</td>
      <td>
        <div class="flex items-center gap-2">
          <img src="${esc(u.picture||'')}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(u.name||'U')}&background=222&color=fff&size=32'" class="w-7 h-7 rounded-full object-cover flex-shrink-0">
          <div>
            <p class="text-sm leading-tight" style="color:var(--text-primary);">${esc(u.name||'匿名')}</p>
            ${u.reg_source === 'guest' ? '<span class="badge badge-guest" style="font-size:9px">游客</span>' : ''}
          </div>
        </div>
      </td>
      <td class="text-xs text-dim">${esc(u.email||'-')}</td>
      <td class="font-mono text-xs text-muted">${fd(u.created_at)}</td>
      <td class="font-mono text-xs text-muted">${fd(u.last_login_at)}</td>
      <td class="font-mono text-xs text-dim">${u.project_count||0}</td>
      <td>
        <div class="actions-cell">
          <button onclick='openEditUser(${JSON.stringify(u)})' class="btn btn-ghost">编辑</button>
          ${u.id != ADMIN_ID ? `<button onclick='openUserRestrictModal(${u.id},${JSON.stringify(u.name||'匿名')})' class="btn btn-danger" style="font-size:10px;padding:2px 8px">限制</button><button onclick="deleteUser(${u.id})" class="btn btn-danger">删除</button>` : '<span class="text-xs text-muted">（自身）</span>'}
        </div>
      </td>
    </tr>
  `).join('');
}

function openEditUser(u) {
  document.getElementById('eu-id').value      = u.id;
  document.getElementById('eu-name').value    = u.name    || '';
  document.getElementById('eu-email').value   = u.email   || '';
  document.getElementById('eu-bio').value     = u.bio     || '';
  document.getElementById('eu-website').value = u.website || '';
  document.getElementById('eu-phone').value   = u.phone   || '';
  om('editUserModal');
}

function openUserRestrictModal(userId, userName) {
  document.getElementById('ur-restrict-user-info').innerHTML = '<strong style="color:var(--text-primary)">' + esc(userName||'未知用户') + '</strong> <span class="text-muted">· ID: ' + userId + '</span>';
  document.getElementById('ur-restrict-type').value = 'publish';
  document.getElementById('ur-restrict-duration').value = 1;
  document.getElementById('ur-restrict-unit').value = '1440';
  document.getElementById('ur-restrict-reason').value = '';
  // Store user ID
  document.getElementById('ur-restrict-user-info').setAttribute('data-user-id', userId);
  loadUserRestrictStatus(userId);
  om('userRestrictModal');
}

async function loadUserRestrictStatus(userId) {
  const statusEl = document.getElementById('ur-restriction-status');
  const unrestrictLoginBtn = document.getElementById('ur-unrestrict-login-btn');
  const unrestrictPublishBtn = document.getElementById('ur-unrestrict-publish-btn');
  statusEl.innerHTML = '加载中...';
  unrestrictLoginBtn.style.display = 'none';
  unrestrictPublishBtn.style.display = 'none';
  try {
    const res = await fetch('/admin/api.php?action=user_restrictions&user_id=' + userId);
    const d = await res.json();
    if (!d.ok) { statusEl.innerHTML = '无法获取状态'; return; }
    const a = d.active || {};
    const now = new Date();
    let html = '';
    let hasAny = false;
    // 登录限制
    if (a.login_restricted_until && new Date(a.login_restricted_until) > now) {
      html += '<div style="color:#f87171;margin-bottom:2px">登录限制 · 截止 ' + a.login_restricted_until + '</div>';
      unrestrictLoginBtn.style.display = '';
      hasAny = true;
    }
    // 发布限制
    if (a.publish_restricted_until && new Date(a.publish_restricted_until) > now) {
      html += '<div style="color:#fbbf24;margin-bottom:2px">发布限制 · 截止 ' + a.publish_restricted_until + '</div>';
      unrestrictPublishBtn.style.display = '';
      hasAny = true;
    }
    if (a.restrict_reason) {
      html += '<span class="text-muted">' + esc(a.restrict_reason) + '</span>';
    }
    if (hasAny) {
      statusEl.innerHTML = html;
    } else {
      statusEl.innerHTML = '<span style="color:#16a34a">无活跃限制</span>';
    }
  } catch(e) {
    statusEl.innerHTML = '无法获取状态';
  }
}

async function submitUserRestrict() {
  const userId = parseInt(document.getElementById('ur-restrict-user-info').getAttribute('data-user-id'));
  const type = document.getElementById('ur-restrict-type').value;
  const durationVal = parseInt(document.getElementById('ur-restrict-duration').value);
  const unit = parseInt(document.getElementById('ur-restrict-unit').value);
  const duration = durationVal * unit;
  const reason = document.getElementById('ur-restrict-reason').value.trim();
  if (!userId || !duration || isNaN(duration) || duration <= 0) { toast('请填写有效时长','error'); return; }
  const d = await post({action:'restrict_user', user_id: userId, restrict_type: type, duration_minutes: duration, reason: reason||'违反平台规范'});
  if (d.ok) {
    toast(d.msg||'已限制用户','success');
    loadUserRestrictStatus(userId);
  } else {
    toast(d.msg||'操作失败','error');
  }
}

async function unrestrictUserFromModal(type) {
  const userId = parseInt(document.getElementById('ur-restrict-user-info').getAttribute('data-user-id'));
  if (!userId) { toast('参数错误','error'); return; }
  if (!(await showConfirm('确认解除限制', '确定解除该用户的' + (type==='login'?'登录':'发布') + '限制？'))) return;
  const d = await post({action:'unrestrict_user', user_id: userId, restrict_type: type});
  if (d.ok) {
    toast(d.msg||'已解除限制','success');
    loadUserRestrictStatus(userId);
  } else {
    toast(d.msg||'操作失败','error');
  }
}

async function submitEditUser(e) {
  e.preventDefault();
  const d = await post({
    action:  'edit_user',
    id:      document.getElementById('eu-id').value,
    name:    document.getElementById('eu-name').value,
    email:   document.getElementById('eu-email').value,
    bio:     document.getElementById('eu-bio').value,
    website: document.getElementById('eu-website').value,
    phone:   document.getElementById('eu-phone').value,
  });
  if (d.ok) { cm('editUserModal'); toast(d.msg,'success'); loadUsers(); }
  else toast(d.msg||'保存失败','error');
}

async function deleteUser(id) {
  if (!(await showConfirm('确认删除用户', '确定删除该用户？其名下所有项目将一并删除，且不可恢复。', true))) return;
  const d = await post({action:'delete_user',id});
  if (d.ok) { document.getElementById(`urow-${id}`)?.remove(); toast('用户已删除','success'); loadStats(); }
  else toast(d.msg||'删除失败','error');
}

// ═════════════════════════════════════════════════════════════
//  App 更新管理
// ═════════════════════════════════════════════════════════════

async function loadAppUpdates() {
  const tbody = document.getElementById('app-updates-tbody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-sm text-muted">Please be patient...</td></tr>';
  const d = await api('?action=app_updates');
  if (!d.ok || !d.updates.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-sm text-muted">暂无版本记录</td></tr>';
    return;
  }
  tbody.innerHTML = d.updates.map(u => `
    <tr id="updrow-${u.id}">
      <td class="font-mono text-xs text-muted">#${u.id}</td>
      <td style="color:var(--text-primary);font-weight:500">${esc(u.app_name)}</td>
      <td class="font-mono text-xs" style="color:var(--text-secondary)">${esc(u.version)}</td>
      <td class="text-xs text-dim">${esc(u.package_size||'-')}</td>
      <td>${u.is_force ? '<span class="badge badge-rejected">强制</span>' : '<span class="badge badge-approved">可选</span>'}</td>
      <td class="font-mono text-xs text-muted">${fd(u.updated_at)}</td>
      <td>
        <div class="actions-cell">
          <button onclick='openEditUpdate(${JSON.stringify(u)})' class="btn btn-ghost">编辑</button>
          <button onclick="deleteUpdate(${u.id})" class="btn btn-danger">删除</button>
        </div>
      </td>
    </tr>
  `).join('');
}

function openCreateUpdate() {
  document.getElementById('update-modal-title').textContent = '添加 App 版本';
  document.getElementById('updateForm').reset();
  document.getElementById('update-id-field').value = '';
  om('updateModal');
}

function openEditUpdate(u) {
  document.getElementById('update-modal-title').textContent = '编辑 App 版本';
  document.getElementById('update-id-field').value    = u.id;
  document.getElementById('update-app-name').value    = u.app_name || '';
  document.getElementById('update-version').value     = u.version || '';
  document.getElementById('update-package-size').value= u.package_size || '';
  document.getElementById('update-download-url').value= u.download_url || '';
  document.getElementById('update-changelog').value   = u.changelog || '';
  document.getElementById('update-is-force').checked  = !!u.is_force;
  om('updateModal');
}

async function submitUpdate(e) {
  e.preventDefault();
  const id = document.getElementById('update-id-field').value;
  const payload = {
    action:         id ? 'edit_app_update' : 'create_app_update',
    id:             id,
    app_name:       document.getElementById('update-app-name').value,
    version:        document.getElementById('update-version').value,
    package_size:   document.getElementById('update-package-size').value,
    download_url:   document.getElementById('update-download-url').value,
    changelog:      document.getElementById('update-changelog').value,
    is_force:       document.getElementById('update-is-force').checked ? 1 : 0,
  };
  const d = await post(payload);
  if (d.ok) {
    cm('updateModal');
    toast(id ? '版本更新已保存' : '版本更新已创建', 'success');
    loadAppUpdates();
  } else {
    toast(d.msg || '保存失败', 'error');
  }
}

async function deleteUpdate(id) {
  if (!(await showConfirm('确认删除版本', '确定删除该版本记录？'))) return;
  const d = await post({action:'delete_app_update', id});
  if (d.ok) { document.getElementById(`updrow-${id}`)?.remove(); toast('已删除','success'); }
  else toast(d.msg||'删除失败','error');
}

// ═════════════════════════════════════════════════════════════
//  公告管理
// ═════════════════════════════════════════════════════════════

const SEV_LABELS = { notice: '通知', info: '提示', important: '重要', warning: '警告' };
const SEV_BADGES = { notice: 'badge-approved', info: 'badge-pending', important: 'badge-rejected', warning: 'badge-guest' };

async function loadAnnouncements() {
  const tbody = document.getElementById('announcements-tbody');
  tbody.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-sm text-muted">Please be patient...</td></tr>';
  const d = await api('?action=announcements');
  if (!d.ok || !d.announcements.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-sm text-muted">暂无公告</td></tr>';
    return;
  }
  tbody.innerHTML = d.announcements.map(a => `
    <tr id="annrow-${a.id}">
      <td class="font-mono text-xs text-muted">#${a.id}</td>
      <td style="color:var(--text-primary);font-weight:500;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(a.title)}</td>
      <td><span class="badge ${SEV_BADGES[a.severity]||'badge-pending'}">${SEV_LABELS[a.severity]||'通知'}</span></td>
      <td class="font-mono text-xs text-muted">${fd(a.updated_at)}</td>
      <td>
        <div class="actions-cell">
          <button onclick='openEditAnnouncement(${JSON.stringify(a)})' class="btn btn-ghost">编辑</button>
          <button onclick="deleteAnnouncement(${a.id})" class="btn btn-danger">删除</button>
        </div>
      </td>
    </tr>
  `).join('');
}

function openCreateAnnouncement() {
  document.getElementById('ann-modal-title').textContent = '添加公告';
  document.getElementById('announcementForm').reset();
  document.getElementById('ann-id-field').value = '';
  document.getElementById('ann-severity').value = 'notice';
  om('announcementModal');
}

function openEditAnnouncement(a) {
  document.getElementById('ann-modal-title').textContent = '编辑公告';
  document.getElementById('ann-id-field').value   = a.id;
  document.getElementById('ann-title').value      = a.title || '';
  document.getElementById('ann-content').value    = a.content || '';
  document.getElementById('ann-severity').value   = a.severity || 'notice';
  om('announcementModal');
}

async function submitAnnouncement(e) {
  e.preventDefault();
  const id = document.getElementById('ann-id-field').value;
  const payload = {
    action:   id ? 'edit_announcement' : 'create_announcement',
    id:       id,
    title:    document.getElementById('ann-title').value,
    content:  document.getElementById('ann-content').value,
    severity: document.getElementById('ann-severity').value,
  };
  const d = await post(payload);
  if (d.ok) {
    cm('announcementModal');
    toast(id ? '公告已保存' : '公告已创建', 'success');
    loadAnnouncements();
  } else {
    toast(d.msg || '保存失败', 'error');
  }
}

async function deleteAnnouncement(id) {
  if (!(await showConfirm('确认删除公告', '确定删除该公告？'))) return;
  const d = await post({action:'delete_announcement', id});
  if (d.ok) { document.getElementById(`annrow-${id}`)?.remove(); toast('已删除','success'); }
  else toast(d.msg||'删除失败','error');
}

// ── 站内信管理 ────────────────────────────────────────────────
const TYPE_LABELS = {
  login: '登录通知',
  welcome: '欢迎消息',
  project_submitted: '项目提交',
  project_approved: '审核通过',
  project_rejected: '审核拒绝',
  project_deleted: '项目删除',
  project_liked: '项目点赞',
  admin_message: '管理员消息',
  new_subscriber: '新订阅者',
  new_project: '项目更新',
  project_reported: '项目被举报',
  report_submitted: '提交举报',
  report_result: '举报结果',
  restricted: '账号限制'
};

let notifPage = 1;

async function loadAdminNotifications(page) {
  notifPage = page || 1;
  const tbody = document.getElementById('notifications-tbody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-sm text-muted">加载中...</td></tr>';
  const res = await fetch(`/admin/api.php?action=notifications&page=${notifPage}`);
  const d = await res.json();
  if (!d.ok || !d.notifications.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-sm text-muted">暂无通知</td></tr>';
    return;
  }
  tbody.innerHTML = d.notifications.map(n => `
    <tr id="notifrow-${n.id}">
      <td class="font-mono text-xs">${n.id}</td>
      <td><span style="color:var(--text-primary);">${esc(n.user_name)}</span><br><span class="text-muted text-xs">${esc(n.user_email)}</span></td>
      <td class="text-xs">${TYPE_LABELS[n.type] || n.type}</td>
      <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(n.title)}">${n.is_read ? '' : '<span class="inline-block w-2 h-2 rounded-full mr-1" style="background:#3b82f6;"></span>'}${esc(n.title)}</td>
      <td><span class="text-xs" style="color:${n.is_read?'var(--text-muted)':'#3b82f6'}">${n.is_read?'已读':'未读'}</span></td>
      <td class="text-xs text-muted">${n.created_at}</td>
      <td><button onclick="deleteAdminNotif(${n.id})" class="btn btn-danger btn-sm" style="font-size:11px;padding:3px 8px">删除</button></td>
    </tr>
  `).join('');

  const totalPages = Math.ceil(d.total / 30);
  const pag = document.getElementById('notif-pagination');
  if (totalPages > 1) {
    pag.innerHTML = `
      <button onclick="loadAdminNotifications(${notifPage-1})" ${notifPage<=1?'disabled':''}>上一页</button>
      <span class="text-xs text-muted mx-2">${notifPage}/${totalPages}</span>
      <button onclick="loadAdminNotifications(${notifPage+1})" ${notifPage>=totalPages?'disabled':''}>下一页</button>
    `;
  } else {
    pag.innerHTML = '';
  }
}

async function deleteAdminNotif(id) {
  if (!(await showConfirm('确认删除通知', '确定删除该通知？'))) return;
  const d = await post({action:'delete_notification', id});
  if (d.ok) { document.getElementById(`notifrow-${id}`)?.remove(); toast('已删除','success'); }
  else toast(d.msg||'删除失败','error');
}

let notifAllUsers = []; // 缓存用户列表

async function openSendNotification() {
  const res = await fetch('/admin/api.php?action=users_simple');
  const d = await res.json();
  notifAllUsers = d.ok && d.users ? d.users : [];
  renderNotifUserList();
  document.getElementById('notif-title').value = '';
  document.getElementById('notif-content').value = '';
  document.getElementById('notif-user-search').value = '';
  document.getElementById('notif-selected-count').textContent = '0';
  om('notifSendModal');
}

function renderNotifUserList(filter) {
  const list = document.getElementById('notif-user-list');
  let users = notifAllUsers;
  if (filter) {
    const q = filter.toLowerCase();
    users = notifAllUsers.filter(u => u.name.toLowerCase().includes(q) || (u.email||'').toLowerCase().includes(q));
  }
  if (!users.length) {
    list.innerHTML = '<span style="font-size:12px;color:var(--text-muted)">无匹配用户</span>';
    return;
  }
  list.innerHTML = users.map(u => `
    <label style="display:flex;align-items:center;gap:8px;padding:6px 8px;cursor:pointer;border-radius:4px;font-size:13px;transition:background .1s"
           onmouseover="this.style.background='var(--bg-hover, #eee)'" onmouseout="this.style.background=''">
      <input type="checkbox" value="${u.id}" class="notif-user-cb" onchange="updateNotifSelectedCount()" style="width:15px;height:15px">
      <span>${esc(u.name)}</span>
      <span style="font-size:11px;color:var(--text-muted)">${esc(u.email)}</span>
    </label>
  `).join('');
}

function filterNotifUsers() {
  const q = document.getElementById('notif-user-search').value;
  renderNotifUserList(q);
}

function updateNotifSelectedCount() {
  const count = document.querySelectorAll('.notif-user-cb:checked').length;
  document.getElementById('notif-selected-count').textContent = count;
}

function selectAllNotifUsers() {
  document.querySelectorAll('.notif-user-cb').forEach(cb => cb.checked = true);
  updateNotifSelectedCount();
}

function deselectAllNotifUsers() {
  document.querySelectorAll('.notif-user-cb').forEach(cb => cb.checked = false);
  updateNotifSelectedCount();
}

function getSelectedUserIds() {
  return Array.from(document.querySelectorAll('.notif-user-cb:checked')).map(cb => cb.value);
}

async function submitSendNotification(e) {
  e.preventDefault();
  const userIds = getSelectedUserIds();
  const title = document.getElementById('notif-title').value.trim();
  const content = document.getElementById('notif-content').value.trim();
  if (!userIds.length || !title) { toast('请选择至少一个用户并填写标题','error'); return; }
  const d = await post({action:'send_notification', user_ids: userIds.join(','), title, content});
  if (d.ok) {
    toast(d.msg || '通知已发送','success');
    cm('notifSendModal');
    loadAdminNotifications(notifPage);
  } else {
    toast(d.msg||'发送失败','error');
  }
}

// ── Helpers ───────────────────────────────────────────────────
async function api(qs) {
  const r = await fetch('/admin/api.php' + qs);
  return r.json();
}
async function post(data) {
  const fd = new FormData();
  for (const [k,v] of Object.entries(data)) fd.append(k, v);
  const r = await fetch('/admin/api.php', {method:'POST', body:fd});
  return r.json();
}
function toast(msg, type='success') {
  const w = document.getElementById('toast');
  const el = document.createElement('div');
  el.className = `toast-item ${type}`;
  el.textContent = msg;
  w.appendChild(el);
  setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 260); }, 3000);
}
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function renderTechBadges(ts) {
  if (!ts) return '';
  const list = String(ts).split(',').map(s => s.trim()).filter(Boolean);
  if (!list.length) return '';
  return '<div class="tech-badges">' + list.map(t => '<span class="tech-badge">' + esc(t) + '</span>').join('') + '</div>';
}
function fd(s) { if(!s) return '-'; const d=new Date(s); return`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }

// ── 举报管理 ────────────────────────────────────────────────
const REPORT_TYPES = {
  spam: '垃圾信息',
  violation: '违规内容',
  copyright: '侵权问题',
  misleading: '虚假误导',
  other: '其他问题'
};

let reportsPage = 1;

async function loadAdminReports(page) {
  reportsPage = page || 1;
  const tbody = document.getElementById('reports-tbody');
  tbody.innerHTML = '<tr><td colspan="8" class="text-center py-10 text-sm text-muted">加载中...</td></tr>';
  const res = await fetch(`/admin/api.php?action=reports&page=${reportsPage}`);
  const d = await res.json();
  if (!d.ok || !d.reports.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-10 text-sm text-muted">暂无举报</td></tr>';
    document.getElementById('reports-pagination').innerHTML = '';
    return;
  }
  tbody.innerHTML = d.reports.map(r => `
    <tr id="reportrow-${r.id}">
      <td class="font-mono text-xs">${r.id}</td>
      <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(r.project_name||'')}">${esc(r.project_name||'已删除')}</td>
      <td>${r.reporter_name ? esc(r.reporter_name) : '<span class="text-muted text-xs">匿名</span>'}</td>
      <td><span class="text-xs px-2 py-1 rounded" style="background:var(--bg-tertiary);">${REPORT_TYPES[r.report_type]||r.report_type}</span></td>
      <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(r.reason||'')}">${r.reason ? esc(r.reason) : '<span class="text-muted">-</span>'}</td>
      <td><span class="text-xs" style="color:${r.status==='pending'?'#f59e0b':r.status==='resolved'?'#16a34a':'#737373'}">${r.status==='pending'?'待处理':r.status==='resolved'?'已处理':'已驳回'}</span></td>
      <td class="text-xs text-muted">${r.created_at}</td>
      <td>
        ${r.status === 'pending' ? `
        <div style="display:flex;gap:4px;flex-wrap:wrap">
          <button onclick="openRestrictModal(${r.id},${r.project_id||0},${r.author_id||0},'${esc(r.project_name||'')}','${esc(r.author_name||'')}')" class="btn btn-danger btn-sm" style="font-size:10px;padding:2px 6px">处罚</button>
          <button onclick="resolveReport(${r.id},'resolved')" class="btn btn-sm" style="font-size:10px;padding:2px 6px;background:#16a34a;color:#fff">通过</button>
          <button onclick="resolveReport(${r.id},'dismissed')" class="btn btn-ghost btn-sm" style="font-size:10px;padding:2px 6px">驳回</button>
        </div>
        ` : '<span class="text-xs text-muted">已处理</span>'}
      </td>
    </tr>
  `).join('');

  const totalPages = Math.ceil(d.total / 20);
  const pag = document.getElementById('reports-pagination');
  if (totalPages > 1) {
    pag.innerHTML = `
      <button onclick="loadAdminReports(${reportsPage-1})" ${reportsPage<=1?'disabled':''}>上一页</button>
      <span class="text-xs text-muted mx-2">${reportsPage}/${totalPages}</span>
      <button onclick="loadAdminReports(${reportsPage+1})" ${reportsPage>=totalPages?'disabled':''}>下一页</button>
    `;
  } else {
    pag.innerHTML = '';
  }
}

async function resolveReport(id, status) {
  const title = status === 'resolved' ? '处理备注' : '驳回原因';
  const note = await showPrompt(title, '请输入' + title + '（可选）：', '可选');
  if (note === null) return;
  const d = await post({action:'resolve_report', id, status, note: note||''});
  if (d.ok) { toast(d.msg,'success'); loadAdminReports(reportsPage); }
  else toast(d.msg||'操作失败','error');
}

async function clearResolvedReports() {
  if (!(await showConfirm('确认清除', '确认清除所有已通过的举报？此操作不可撤销。', true))) return;
  const d = await post({action:'clear_resolved_reports'});
  if (d.ok) { toast(d.msg,'success'); loadAdminReports(reportsPage); }
  else toast(d.msg||'操作失败','error');
}

function openRestrictModal(reportId, projectId, authorId, projectName, authorName) {
  document.getElementById('restrict-report-id').value = reportId;
  document.getElementById('restrict-user-id').value = authorId;
  document.getElementById('restrict-project-id').value = projectId;
  document.getElementById('restrict-user-info').innerHTML = `
    <strong style="color:var(--text-primary)">${esc(authorName||'未知用户')}</strong>
    <span class="text-muted"> · 项目：</span><strong style="color:var(--text-primary)">${esc(projectName||'未知项目')}</strong>
    <span class="text-muted"> · 举报ID：${reportId}</span>
  `;
  document.getElementById('restrict-type').value = '';
  document.getElementById('restrict-duration').value = 1;
  document.getElementById('restrict-duration-unit').value = '1440';
  document.getElementById('restrict-reason').value = '';
  document.getElementById('restrict-duration-row').style.display = 'none';
  document.getElementById('punish-reject').checked = false;
  document.getElementById('punish-delete').checked = false;
  om('restrictModal');
}

// 项目处罚二选一
function updatePunishExclusive(el) {
  if (el.checked) {
    const otherId = el.id === 'punish-reject' ? 'punish-delete' : 'punish-reject';
    document.getElementById(otherId).checked = false;
  }
}

// 账号处罚类型切换显示时长

async function submitRestrict(e) {
  e.preventDefault();
  const userId = parseInt(document.getElementById('restrict-user-id').value) || 0;
  const projectId = parseInt(document.getElementById('restrict-project-id').value) || 0;
  const reportId = parseInt(document.getElementById('restrict-report-id').value) || 0;
  const restrictType = document.getElementById('restrict-type').value;
  const durationVal = parseInt(document.getElementById('restrict-duration').value);
  const unit = parseInt(document.getElementById('restrict-duration-unit').value);
  const duration = durationVal * unit;
  const reason = document.getElementById('restrict-reason').value.trim();
  const punishReject = document.getElementById('punish-reject').checked;
  const punishDelete = document.getElementById('punish-delete').checked;
  const punishType = punishReject ? 'reject' : (punishDelete ? 'delete' : '');

  // 至少选择一个处罚
  if (!restrictType && !punishType) { toast('请至少选择一种处罚方式','error'); return; }
  if (restrictType && (!duration || isNaN(duration) || duration <= 0)) { toast('请填写有效时长','error'); return; }

  let msgs = [];
  // 项目处罚
  if (punishType) {
    const d = await post({action:'punish_project', project_id: projectId, punish_type: punishType, report_id: reportId});
    if (d.ok) msgs.push(d.msg); else { toast(d.msg||'项目处罚失败','error'); return; }
  }
  // 账号处罚
  if (restrictType) {
    const d = await post({action:'restrict_user', user_id: userId, restrict_type: restrictType, duration_minutes: duration, reason: reason||'违反平台规范', report_id: reportId});
    if (d.ok) msgs.push(d.msg); else { toast(d.msg||'账号处罚失败','error'); return; }
  }

  toast(msgs.join('；') || '处罚完成','success');
  cm('restrictModal');
  resolveReport(reportId, 'resolved');
}

function updateRestrictDurations() {}

</script>
</body>
</html>