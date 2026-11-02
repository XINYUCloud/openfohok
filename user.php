<?php
require_once __DIR__ . '/bootstrap.php';

$targetId = (int)($_GET['id'] ?? 0);
$db       = getDB();
$me       = optionalLogin();
$profile  = null;

if ($targetId) {
    $stmt = $db->prepare(
        "SELECT id, name, picture, bio, website, email, phone FROM users WHERE id = ? AND reg_source != 'guest'"
    );
    $stmt->execute([$targetId]);
    $profile = $stmt->fetch();
}

$projects     = [];
$calendarData = [];

if ($profile) {
    $currentUserId = $me ? ($me['user_id'] ?? $me['id']) : null;
    $pstmt = $db->prepare(
        "SELECT p.id, p.name, p.version, p.description, p.project_url, p.developer_website,
                p.contact_email, p.contact_phone, p.contact_qq, p.contact_wechat,
                p.release_date, p.view_count, p.created_at,
                (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id) AS like_count"
         . ($currentUserId ? ', (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id AND user_id = ?) AS is_liked' : '')
        . " FROM projects p
         WHERE p.user_id = ? AND p.status = 'approved' AND p.is_hidden = 0
         ORDER BY p.created_at DESC"
    );
    $params = $currentUserId ? [$currentUserId, $targetId] : [$targetId];
    $pstmt->execute($params);
    $projects = $pstmt->fetchAll();

    // 转换 like 数据
    foreach ($projects as &$proj) {
        $proj['like_count'] = (int)($proj['like_count'] ?? 0);
        $proj['is_liked']   = $currentUserId ? (bool)($proj['is_liked'] ?? false) : false;
    }
    unset($proj);

    $calStmt = $db->prepare(
        "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
         FROM projects
         WHERE user_id = ? AND status = 'approved' AND is_hidden = 0
           AND created_at >= DATE_SUB(CURDATE(), INTERVAL 52 WEEK)
         GROUP BY DATE(created_at)"
    );
    $calStmt->execute([$targetId]);
    foreach ($calStmt->fetchAll() as $row) {
        $calendarData[$row['day']] = (int)$row['cnt'];
    }
}

$isAdmin  = $me && in_array($me['email'], ADMIN_EMAILS);
$isSelf   = $me && (($me['user_id'] ?? $me['id']) == $targetId);
$unreadCount = $me ? getUnreadCount($me['user_id'] ?? $me['id']) : 0;
$notFound = !$targetId || !$profile;

// 订阅计数
$subscriberCount = 0;
$isSubscribed = false;
if ($profile) {
    $subStmt = $db->prepare('SELECT COUNT(*) FROM user_subscriptions WHERE target_user_id = ?');
    $subStmt->execute([$targetId]);
    $subscriberCount = (int)$subStmt->fetchColumn();
    if ($me && !$isSelf) {
        $checkStmt = $db->prepare('SELECT id FROM user_subscriptions WHERE subscriber_id = ? AND target_user_id = ?');
        $checkStmt->execute([$me['user_id'] ?? $me['id'], $targetId]);
        $isSubscribed = (bool)$checkStmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta property="og:title" content="开发者主页 · 文谶驿站">
<meta property="og:description" content="一个为独立开发者打造的开源项目展示空间。
发布你的作品，探索他人的创造，连接志同道合的人。">
<meta property="og:image" content="https://fohok.xin/img/fohok-2.png">
<meta property="og:url" content="https://fohok.xin/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="文谶驿站 · 开发者开放仓库">
<title><?= $notFound ? '用户不存在' : htmlspecialchars($profile['name'] ?? '开发者') ?> · 文谶驿站</title>
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
    --bg-modal: #ffffff;
    --bg-hero: #f8f8f8;
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
    --overlay-bg: rgba(0, 0, 0, 0.5);
    --shadow-card: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-lg: 0 4px 24px rgba(0,0,0,0.1);
    --scrollbar-track: #f5f5f5;
    --scrollbar-thumb: #d4d4d4;
    --cal-cell-0-bg: #f5f5f5;
    --cal-cell-0-border: #d4d4d4;
    --cal-cell-1-bg: #dcfce7;
    --cal-cell-1-border: #bbf7d0;
    --cal-cell-2-bg: #86efac;
    --cal-cell-2-border: #4ade80;
    --cal-cell-3-bg: #22c55e;
    --cal-cell-3-border: #16a34a;
    --cal-label-color: #a3a3a3;
    --cal-legend-label: #a3a3a3;
    --cal-tooltip-bg: #ffffff;
    --cal-tooltip-border: #e5e5e5;
    --cal-tooltip-color: #404040;
  }

  /* 夜间主题 */
  @media (prefers-color-scheme: dark) {
    :root {
      --bg-primary: #050505;
      --bg-secondary: #0a0a0a;
      --bg-tertiary: #111111;
      --bg-hover: #1a1a1a;
      --bg-card: #0f0f0f;
      --bg-modal: #0a0a0a;
      --bg-hero: #0a0a0a;
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
      --overlay-bg: rgba(0, 0, 0, 0.88);
      --shadow-card: none;
      --shadow-lg: 0 4px 24px rgba(0,0,0,0.3);
      --scrollbar-track: #0a0a0a;
      --scrollbar-thumb: #2a2a2a;
      --cal-cell-0-bg: #111111;
      --cal-cell-0-border: #1a1a1a;
      --cal-cell-1-bg: #052e16;
      --cal-cell-1-border: #053820;
      --cal-cell-2-bg: #14532d;
      --cal-cell-2-border: #16623a;
      --cal-cell-3-bg: #4ade80;
      --cal-cell-3-border: #22c55e;
      --cal-label-color: #3a3a3a;
      --cal-legend-label: #3a3a3a;
      --cal-tooltip-bg: #1a1a1a;
      --cal-tooltip-border: #2a2a2a;
      --cal-tooltip-color: #aaaaaa;
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

  .btn{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all .2s;
    border: none;
    font-family: inherit;
    text-decoration: none;
  }
  .btn-ghost{
    background: transparent;
    color: var(--text-dim);
    border: 1px solid var(--border-secondary);
  }
  .btn-ghost:hover{
    color: var(--text-primary);
    border-color: var(--border-hover);
  }

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
  .dropdown-item.danger:hover{background:#1a0808}

  .page-body{margin-top:56px}

  /* Hero */
  .hero-section{
    background: var(--bg-hero);
    border-bottom: 1px solid var(--border-primary);
  }
  .hero-inner{
    max-width: 1100px;
    margin: 0 auto;
    display: flex;
    align-items: stretch;
    min-height: 200px;
  }
  .hero-left{
    flex: 0 0 auto;
    width: 320px;
    padding: 44px 32px 40px;
    border-right: 1px solid var(--border-primary);
  }
  .hero-right{
    flex: 1;
    min-width: 0;
    padding: 44px 32px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  /* ── 手机端：去掉多余空白 ── */
  @media(max-width:768px){
    .hero-inner{flex-direction:column;min-height:unset}
    .hero-left{width:100%;border-right:none;border-bottom:1px solid var(--border-primary);padding:32px 20px 28px}
    .hero-right{padding:20px 20px 24px;justify-content:flex-start;flex:0 0 auto}
  }

  /* 日历 */
  .cal-title{
    font-size: 11px;
    color: var(--cal-label-color);
    font-family: 'JetBrains Mono', monospace;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 10px;
  }

  .cal-scroll{overflow-x:auto;overflow-y:hidden}
  .cal-scroll::-webkit-scrollbar{height:3px}
  .cal-scroll::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb);border-radius:2px}

  .cal-inner{display:inline-flex;flex-direction:column;gap:0}
  .cal-month-labels{display:flex;gap:3px;margin-bottom:4px}
  .cal-month-label{
    font-size: 10px;
    color: var(--cal-label-color);
    font-family: 'JetBrains Mono', monospace;
    flex-shrink: 0;
    text-align: left;
  }
  .cal-grid{display:flex;gap:3px}
  .cal-col{display:flex;flex-direction:column;gap:3px}

  .cal-cell{
    width: 12px;
    height: 12px;
    border-radius: 2px;
    flex-shrink: 0;
    cursor: pointer;
    transition: opacity .15s;
  }
  .cal-cell:hover{opacity:.75}
  .cal-cell[data-count="0"]{
    background: var(--cal-cell-0-bg);
    border: 1px solid var(--cal-cell-0-border);
  }
  .cal-cell[data-count="1"]{
    background: var(--cal-cell-1-bg);
    border: 1px solid var(--cal-cell-1-border);
  }
  .cal-cell[data-count="2"]{
    background: var(--cal-cell-2-bg);
    border: 1px solid var(--cal-cell-2-border);
  }
  .cal-cell[data-count="3"]{
    background: var(--cal-cell-3-bg);
    border: 1px solid var(--cal-cell-3-border);
  }

  .cal-legend{display:flex;align-items:center;gap:5px;margin-top:10px}
  .cal-legend-label{
    font-size: 10px;
    color: var(--cal-legend-label);
    font-family: 'JetBrains Mono', monospace;
  }
  .cal-legend-cell{width:10px;height:10px;border-radius:2px;flex-shrink:0}

  #cal-tooltip{
    position: fixed;
    background: var(--cal-tooltip-bg);
    border: 1px solid var(--cal-tooltip-border);
    color: var(--cal-tooltip-color);
    font-size: 10px;
    font-family: 'JetBrains Mono', monospace;
    padding: 3px 8px;
    border-radius: 4px;
    pointer-events: none;
    z-index: 999;
    white-space: nowrap;
    display: none;
  }

  /* 用户信息 */
  .user-avatar{
    width: 72px;
    height: 72px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border-primary);
    margin-bottom: 14px;
    display: block;
  }
  .user-name{
    font-size: 19px;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 4px;
  }
  .user-bio{
    font-size: 13px;
    color: var(--text-dim);
    line-height: 1.7;
    margin-bottom: 12px;
  }
  .user-meta{display:flex;flex-direction:column;gap:6px}
  .user-meta-item{
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-dim);
    text-decoration: none;
    transition: color .15s;
  }
  .user-meta-item:hover{color:var(--text-primary)}
  .user-stat{
    font-size: 11px;
    color: var(--text-muted);
    font-family: 'JetBrains Mono', monospace;
    margin-top: 14px;
  }

  /* 项目卡片 */
  .proj-card{
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: 8px;
    padding: 20px;
    cursor: pointer;
    transition: all .2s;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-card);
    display: flex;
    flex-direction: column;
  }
  .proj-card:hover{
    border-color: var(--border-hover);
    transform: translateY(-2px);
    background: var(--bg-hover);
  }
  .proj-card::before{
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, transparent 60%, rgba(128,128,128,0.03));
    pointer-events: none;
  }
  .projects-grid{
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
  }

  /* 弹窗 */
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
  .modal{
    background: var(--bg-modal);
    border: 1px solid var(--border-secondary);
    border-radius: 12px;
    width: 100%;
    max-width: 680px;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(.97) translateY(8px);
    transition: transform .25s;
    box-shadow: var(--shadow-lg);
  }
  .modal-bg.show .modal{transform:scale(1) translateY(0)}
  body.locked{overflow:hidden}

  /* 404 */
  .not-found{min-height:calc(100vh - 56px);display:flex;align-items:center;justify-content:center}
  .not-found-box{text-align:center;padding:40px}

  /* ── Footer ── */
  .page-footer{
    max-width: 1100px;
    margin: 0 auto;
    padding: 8px 24px 36px;
    text-align: center;
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
</style>
</head>
<body>

<!-- ══ TOPBAR ══════════════════════════════════════════════ -->
<header id="topbar">
  <a href="/home.php" style="display:flex;align-items:center;gap:7px;opacity:.7;transition:opacity .2s;text-decoration:none;color:var(--text-dim);" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.7">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    <span style="font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.08em" class="hidden sm:block">返回广场</span>
  </a>
  <div style="flex:1"></div>

  <?php if ($me): ?>
  <a href="/messages.php" style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:6px;transition:all .2s;color:var(--text-dim);text-decoration:none;position:relative;margin-right:4px" title="站内信"
     onmouseover="this.style.background='var(--bg-hover)';this.style.color='var(--text-primary)'"
     onmouseout="this.style.background='transparent';this.style.color='var(--text-dim)'">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    <?php if ($unreadCount > 0): ?>
    <span style="position:absolute;top:2px;right:2px;min-width:16px;height:16px;background:#3b82f6;color:#fff;border-radius:99px;font-size:10px;font-family:'JetBrains Mono',monospace;display:flex;align-items:center;justify-content:center;padding:0 3px;font-weight:700;line-height:1"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
    <?php endif; ?>
  </a>
  <div class="dropdown">
    <button onclick="dd('userMenu')" style="display:flex;align-items:center;gap:8px;background:none;border:none;cursor:pointer;padding:0" class="hover:opacity-80 transition-opacity">
      <img src="<?= htmlspecialchars($me['picture'] ?? 'https://ui-avatars.com/api/?name='.urlencode($me['name']??'U').'&background=222&color=fff') ?>"
           style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid var(--border-secondary)" alt="">
    </button>
    <div id="userMenu" class="dropdown-menu">
      <a href="/profile.php" class="dropdown-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        个人中心
      </a>
      <?php if ($isAdmin): ?>
      <a href="/admin/" class="dropdown-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/></svg>
        管理后台
      </a>
      <?php endif; ?>
      <a href="/logout.php" class="dropdown-item danger">退出登录</a>
    </div>
  </div>
  <?php else: ?>
  <a href="/login.php" class="btn btn-ghost" style="padding:6px 14px;font-size:13px">登录</a>
  <?php endif; ?>
</header>

<div class="page-body">

<?php if ($notFound): ?>
<!-- ══ 用户不存在════════════════════════════════ -->
<div class="not-found">
  <div class="not-found-box">
    <div style="font-size:64px;opacity:.08;margin-bottom:20px;font-family:'JetBrains Mono',monospace;color:var(--text-muted);">404</div>
    <p style="font-size:16px;color:var(--text-dim);margin-bottom:8px">找不到这个用户</p>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:28px">TA 可能从未存在过，或者已被删除</p>
    <a href="/home.php" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-dim);text-decoration:none;border:1px solid var(--border-secondary);padding:8px 18px;border-radius:6px;transition:all .2s"
       onmouseover="this.style.borderColor='var(--border-hover)';this.style.color='var(--text-primary)'"
       onmouseout="this.style.borderColor='var(--border-secondary)';this.style.color='var(--text-dim)'">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      返回项目广场
    </a>
  </div>
</div>

<?php else: ?>
<!-- ══ HERO ═════════════════════════════════════════════════ -->
<div class="hero-section">
  <div class="hero-inner">

    <!-- 左：用户信息 -->
    <div class="hero-left">
      <img src="<?= htmlspecialchars($profile['picture'] ?? '') ?>"
           onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($profile['name']??'U') ?>&background=1a1a1a&color=555&size=72'"
           class="user-avatar" alt="avatar">

      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px">
        <h1 class="user-name"><?= htmlspecialchars($profile['name'] ?? '无名之辈') ?></h1>
        <?php if ($isSelf): ?>
        <a href="/profile.php" class="btn btn-ghost" style="padding:3px 9px;font-size:11px">编辑资料</a>
        <?php elseif ($me): ?>
        <button id="sub-btn" class="btn <?= $isSubscribed ? 'btn-ghost' : 'btn-primary' ?>" style="padding:3px 12px;font-size:11px;border-radius:4px;cursor:pointer;font-family:inherit;transition:all .2s" onclick="toggleSub()">
          <?= $isSubscribed ? '已订阅' : '订阅' ?>
        </button>
        <?php endif; ?>
      </div>

      <?php if ($profile['bio']): ?>
      <p class="user-bio"><?= htmlspecialchars($profile['bio']) ?></p>
      <?php endif; ?>

      <div class="user-meta">
        <?php if ($profile['website']): ?>
        <a href="<?= htmlspecialchars($profile['website']) ?>" target="_blank" rel="noopener" class="user-meta-item">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          <span style="word-break:break-all"><?= htmlspecialchars($profile['website']) ?></span>
        </a>
        <?php endif; ?>

        <?php if ($profile['email']): ?>
        <a href="mailto:<?= htmlspecialchars($profile['email']) ?>" class="user-meta-item">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <span><?= htmlspecialchars($profile['email']) ?></span>
        </a>
        <?php endif; ?>

        <?php if ($profile['phone']): ?>
        <span class="user-meta-item">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.19h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <span><?= htmlspecialchars($profile['phone']) ?></span>
        </span>
        <?php endif; ?>
      </div>

      <p class="user-stat"><?= count($projects) ?> 个已发布项目 &middot; <span id="sub-count"><?= $subscriberCount ?></span> 位订阅者</p>
    </div>

    <!-- 右：创作日历 -->
    <div class="hero-right">
      <p class="cal-title">创作日历 · 近一年</p>
      <div class="cal-scroll">
        <div class="cal-inner">
          <div id="cal-month-labels" class="cal-month-labels"></div>
          <div id="cal-grid" class="cal-grid"></div>
        </div>
      </div>
      <div class="cal-legend">
        <span class="cal-legend-label">少</span>
        <div class="cal-legend-cell" style="background:var(--cal-cell-0-bg);border:1px solid var(--cal-cell-0-border)"></div>
        <div class="cal-legend-cell" style="background:var(--cal-cell-1-bg);border:1px solid var(--cal-cell-1-border)"></div>
        <div class="cal-legend-cell" style="background:var(--cal-cell-2-bg);border:1px solid var(--cal-cell-2-border)"></div>
        <div class="cal-legend-cell" style="background:var(--cal-cell-3-bg);border:1px solid var(--cal-cell-3-border)"></div>
        <span class="cal-legend-label">多</span>
      </div>
    </div>

  </div>
</div>

<!-- ══ PROJECTS ══════════════════════════════════════════════ -->
<main style="max-width:1100px;margin:0 auto;padding:28px 24px">
  <?php if (empty($projects)): ?>
  <div style="text-align:center;padding:80px 0">
    <p style="font-size:48px;opacity:.08;margin-bottom:12px">◎</p>
    <p style="font-size:13px;color:var(--text-muted)">该用户暂无已发布的项目</p>
  </div>
  <?php else: ?>
  <div class="projects-grid">
    <?php foreach ($projects as $p): ?>
    <div class="proj-card" onclick="window.open('/prodeta.php?id=<?= (int)$p['id'] ?>', '_blank')" style="cursor:pointer">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px">
        <h3 style="font-size:13px;font-weight:500;color:var(--text-primary);flex:1;margin-right:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($p['name']) ?></h3>
        <span style="font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--text-muted);flex-shrink:0"><?= htmlspecialchars($p['version']) ?></span>
      </div>
      <p style="font-size:12px;color:var(--text-dim);line-height:1.6;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden"><?= htmlspecialchars($p['description']) ?></p>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:10px;border-top:1px solid var(--border-primary)">
        <span style="font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--text-muted)"><?= date('Y-m-d', strtotime($p['created_at'])) ?></span>
        <div style="display:flex;align-items:center;gap:8px">
          <?php if ($me !== null): ?>
          <button class="like-btn <?= $p['is_liked'] ? 'liked' : '' ?>" onclick="event.stopPropagation();toggleLike(<?= (int)$p['id'] ?>,this)" title="<?= $p['is_liked'] ? '取消点赞' : '点赞' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="<?= $p['is_liked'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <span class="like-count"><?= (int)$p['like_count'] ?></span>
          </button>
          <?php else: ?>
          <span class="like-btn" style="cursor:default" onclick="event.stopPropagation()" title="登录后即可点赞">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <span class="like-count"><?= (int)$p['like_count'] ?></span>
          </span>
          <?php endif; ?>
          <span style="font-size:11px;color:var(--text-muted)"><?= (int)$p['view_count'] ?> 次浏览</span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<!-- Tooltip -->
<div id="cal-tooltip"></div>

<?php endif; ?>

<!-- ══ Footer ══════════════════════════════════════════════ -->
<footer class="page-footer">
<a href="https://beian.miit.gov.cn" target="_blank" style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;">
    <img src="https://fohok.xin/img/icp.png" alt="ICP备案图标" style="width:20px;height:auto;">
    闽ICP备2026024151号 | <a href="https://beian.mps.gov.cn/#/query/webSearch?code=35058302351237" target="_blank" style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;">
    <img src="https://fohok.xin/img/mps.png" alt="公安备案图标" style="width:20px;height:auto;">闽公网安备35058302351237号
</a>
</a>
</footer>

</div><!-- .page-body -->

<script>
const IS_LOGGED_IN = <?= $me !== null ? 'true' : 'false' ?>;
const calendarData = <?= json_encode($calendarData, JSON_UNESCAPED_UNICODE) ?>;

function dd(id) { document.getElementById(id).classList.toggle('show'); }
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
});
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fd(s) {
  if (!s) return '';
  const d = new Date(s);
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

// ── 创作日历 ─────────────────────────────────────────────────
const CELL = 12, GAP = 3, STEP = CELL + GAP;

function buildCalendar() {
  const grid   = document.getElementById('cal-grid');
  const labels = document.getElementById('cal-month-labels');
  if (!grid) return;

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const start = new Date(today);
  start.setDate(start.getDate() - 364);
  start.setDate(start.getDate() - start.getDay());

  const cols           = [];
  const monthPositions = [];
  let lastMonth        = -1;
  let cur              = new Date(start);
  let colIndex         = 0;

  while (cur <= today) {
    const col = document.createElement('div');
    col.className = 'cal-col';

    for (let dow = 0; dow < 7; dow++) {
      const dateStr = `${cur.getFullYear()}-${String(cur.getMonth()+1).padStart(2,'0')}-${String(cur.getDate()).padStart(2,'0')}`;
      const count   = calendarData[dateStr] || 0;
      const cell    = document.createElement('div');
      cell.className        = 'cal-cell';
      cell.dataset.count    = Math.min(count, 3);
      cell.dataset.date     = dateStr;
      cell.dataset.rawCount = count;

      cell.addEventListener('mouseenter', e => showTip(cell, e));
      cell.addEventListener('mousemove',  e => moveTip(e));
      cell.addEventListener('mouseleave', hideTip);

      if (cur.getMonth() !== lastMonth && dow === 0) {
        monthPositions.push({ month: cur.getMonth(), colIdx: colIndex });
        lastMonth = cur.getMonth();
      }

      col.appendChild(cell);
      cur.setDate(cur.getDate() + 1);
      if (cur > today) {
        for (let r = dow + 1; r < 7; r++) {
          const blank = document.createElement('div');
          blank.style.cssText = `width:${CELL}px;height:${CELL}px;flex-shrink:0`;
          col.appendChild(blank);
        }
        break;
      }
    }

    cols.push(col);
    grid.appendChild(col);
    colIndex++;
  }

  const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  monthPositions.forEach(({ month, colIdx }, i) => {
    const next = monthPositions[i + 1];
    const span = next ? next.colIdx - colIdx : cols.length - colIdx;
    if (span < 3) return;
    const label = document.createElement('span');
    label.className   = 'cal-month-label';
    label.textContent = MONTHS[month];
    label.style.width = `${span * STEP - GAP}px`;
    labels.appendChild(label);
  });

  const totalW = cols.length * STEP - GAP;
  document.querySelector('.cal-inner').style.width = totalW + 'px';
}

const tip = document.getElementById('cal-tooltip');

function showTip(cell, e) {
  const count = parseInt(cell.dataset.rawCount) || 0;
  const date  = cell.dataset.date;
  tip.textContent   = count > 0 ? `${date}：${count} 个项目` : date;
  tip.style.display = 'block';
  moveTip(e);
}
function moveTip(e) {
  const tx = e.clientX + 12;
  const ty = e.clientY - 28;
  const bw = document.body.clientWidth;
  tip.style.left = (tx + 160 > bw ? e.clientX - 170 : tx) + 'px';
  tip.style.top  = ty + 'px';
}
function hideTip() { tip.style.display = 'none'; }

document.addEventListener('DOMContentLoaded', buildCalendar);

// ── 点赞 / 取消点赞 ─────────────────────────────────────────
async function toggleLike(projectId, btn, source) {
  if (!IS_LOGGED_IN) {
    alert('请先登录后再点赞~');
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
      btn.classList.toggle('liked', data.is_liked);
      btn.title = data.is_liked ? '取消点赞' : '点赞';
      const svg = btn.querySelector('svg');
      if (svg) svg.setAttribute('fill', data.is_liked ? 'currentColor' : 'none');
      const countEl = btn.querySelector('.like-count');
      if (countEl) countEl.textContent = data.like_count;

      if (source === 'detail') {
        const cardBtns = document.querySelectorAll('.proj-card button[onclick*="toggleLike(' + projectId + '"]');
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
      alert(data.msg || '操作失败');
    }
  } catch(err) {
    alert('网络异常，请重试');
  } finally {
    btn.disabled = false;
  }
}

// ── 订阅 ─────────────────────────────────────────────────
async function toggleSub() {
  const btn = document.getElementById('sub-btn');
  if (!btn) return;
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('action', 'toggle');
    fd.append('target_user_id', '<?= $targetId ?>');
    const res = await fetch('/api/subscription.php', { method: 'POST', body: fd });
    const d = await res.json();
    if (d.ok) {
      btn.textContent = d.subscribed ? '已订阅' : '订阅';
      btn.className = d.subscribed ? 'btn btn-ghost' : 'btn btn-primary';
      btn.style.cssText = 'padding:3px 12px;font-size:11px;border-radius:4px;cursor:pointer;font-family:inherit;transition:all .2s';
      const countEl = document.getElementById('sub-count');
      if (countEl) {
        const current = parseInt(countEl.textContent) || 0;
        countEl.textContent = d.subscribed ? current + 1 : Math.max(0, current - 1);
      }
    }
  } catch(err) {}
  btn.disabled = false;
}
</script>
</body>
</html>