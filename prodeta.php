<?php
require_once __DIR__ . '/bootstrap.php';

$targetId = (int)($_GET['id'] ?? 0);
$me       = optionalLogin();
$isAdmin  = $me && in_array($me['email'], ADMIN_EMAILS);
$unreadCount = $me ? getUnreadCount($me['user_id'] ?? $me['id']) : 0;

$project  = null;
$notFound = false;

if ($targetId) {
    $apiUrl  = 'https://fohok.xin/api/project.php?action=detail&id=' . $targetId;
    $context = stream_context_create(['http' => ['timeout' => 8]]);
    $raw     = @file_get_contents($apiUrl, false, $context);
    if ($raw) {
        $data = json_decode($raw, true);
        if (!empty($data['ok']) && !empty($data['project'])) {
            $project = $data['project'];
        }
    }
}

if (!$targetId || !$project) {
    $notFound = true;
}

// 补充当前用户的点赞状态
$isLikedByMe = false;
$likeCount   = (int)($project['like_count'] ?? 0);
if ($project && $me) {
    $db = getDB();
    $likedStmt = $db->prepare('SELECT COUNT(*) FROM project_likes WHERE project_id = ? AND user_id = ?');
    $likedStmt->execute([$targetId, $me['user_id'] ?? $me['id']]);
    $isLikedByMe = (bool)$likedStmt->fetchColumn();
    $project['is_liked']   = $isLikedByMe;
    $project['like_count'] = $likeCount;
}
// 判断是否为本人项目
$isSelf = $me && $project && ($me['user_id'] ?? $me['id']) == ($project['user_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta property="og:title" content="项目详情 · 文谶驿站">
<meta property="og:description" content="一个为独立开发者打造的开源项目展示空间。
发布你的作品，探索他人的创造，连接志同道合的人。">
<meta property="og:image" content="https://fohok.xin/img/fohok-2.png">
<meta property="og:url" content="https://fohok.xin/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="文谶驿站 · 开发者开放仓库">
<title><?= $notFound ? '项目不存在' : htmlspecialchars($project['name'] ?? '项目详情') ?> · 文谶驿站</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
// ── 举报 ─────────────────────────────────────────────────
function openReportModal() {
  document.getElementById('report-type').value = '';
  document.getElementById('report-email').value = '';
  document.getElementById('report-reason').value = '';
  // 登录用户隐藏邮箱输入框（自动使用绑定邮箱）
  <?php if ($me): ?>
  document.getElementById('report-email-row').style.display = 'none';
  <?php endif; ?>
  document.getElementById('reportModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeReportModal() {
  document.getElementById('reportModal').classList.remove('show');
  document.body.style.overflow = '';
}

// ── 分享弹窗 ─────────────────────────────────────────────
const SHARE_PROJECT_ID = <?= (int)$targetId ?>;
const SHARE_PROJECT_URL = 'https://fohok.xin/prodeta.php?id=' + SHARE_PROJECT_ID;

function openShareModal() {
  const link = SHARE_PROJECT_URL;
  const embedCode = '<iframe src="https://fohok.xin/embed.php?id=' + SHARE_PROJECT_ID + '" width="400" height="105" frameborder="0" style="border:none;border-radius:10px"></iframe>';
  document.getElementById('share-link-text').textContent = link;
  document.getElementById('share-embed-text').textContent = embedCode;
  document.getElementById('share-qr-img').src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=2&data=' + encodeURIComponent(link);
  document.getElementById('shareModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeShareModal() {
  document.getElementById('shareModal').classList.remove('show');
  document.body.style.overflow = '';
}

async function copyShareText(type) {
  const text = type === 'link'
    ? SHARE_PROJECT_URL
    : '<iframe src="https://fohok.xin/embed.php?id=' + SHARE_PROJECT_ID + '" width="400" height="105" frameborder="0" style="border:none;border-radius:10px"></iframe>';
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
    catch { showToast('复制失败，请手动复制', 'error'); }
    document.body.removeChild(ta);
  }
}

async function submitReport(e) {
  e.preventDefault();
  const type = document.getElementById('report-type').value;
  const email = document.getElementById('report-email').value.trim();
  const reason = document.getElementById('report-reason').value.trim();
  if (!type) { showToast('请选择举报类型', 'error'); return; }
  const fd = new FormData();
  fd.append('action', 'submit');
  fd.append('project_id', '<?= $targetId ?>');
  fd.append('report_type', type);
  fd.append('notify_email', email);
  fd.append('reason', reason);
  try {
    const res = await fetch('/api/report.php', { method: 'POST', body: fd });
    const d = await res.json();
    if (d.ok) {
      showToast(d.msg || '举报已提交', 'success');
      closeReportModal();
    } else {
      showToast(d.msg || '提交失败', 'error');
    }
  } catch(err) {
    showToast('网络错误，请重试', 'error');
  }
}

// ── Toast 提示 ──────────────────────────────────────────────
function showToast(msg, type='success') {
  const w = document.getElementById('toast');
  const existing = w.querySelectorAll('.toast-item');
  for (const el of existing) {
    if (el.textContent === msg) return;
  }
  while (existing.length >= 3) {
    existing[0].classList.add('out');
    setTimeout(() => existing[0].remove(), 320);
  }
  const el = document.createElement('div');
  el.className = `toast-item ${type}`; el.textContent = msg;
  el.style.cursor = 'pointer'; el.title = '点击关闭';
  el.addEventListener('click', () => { el.classList.add('out'); setTimeout(() => el.remove(), 320); });
  w.appendChild(el);
  setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(),320); }, 3500);
}

document.addEventListener('click', function(e) {
  if (e.target === document.getElementById('reportModal')) {
    closeReportModal();
  }
});

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
    --bg-card: #ffffff;
    --bg-hero: #f8f8f8;
    --bg-info-row: #fafafa;
    --bg-clock: #f8f8f8;
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
    --version-color: #16a34a;
    --version-bg: #f0fdf4;
    --version-border: #bbf7d0;
    --date-color: #2563eb;
    --date-bg: #eff6ff;
    --date-border: #bfdbfe;
    --views-color: #7c3aed;
    --views-bg: #f5f3ff;
    --views-border: #ddd6fe;
    --clock-seconds: #3b82f6;
    --clock-seconds-bg: #3b82f6;
    --clock-bg: #f8f8f8;
    --clock-tick: #d4d4d4;
    --clock-tick-hour: #a3a3a3;
    --overlay-bg: rgba(0, 0, 0, 0.1);
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
      --bg-card: #0f0f0f;
      --bg-hero: #0a0a0a;
      --bg-info-row: #0d0d0d;
      --bg-clock: #0d0d0d;
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
      --version-color: #4ade80;
      --version-bg: #052e16;
      --version-border: #14532d;
      --date-color: #60a5fa;
      --date-bg: #0c2240;
      --date-border: #1e3a5f;
      --views-color: #a78bfa;
      --views-bg: #1e1040;
      --views-border: #2e1065;
      --clock-seconds: #60a5fa;
      --clock-seconds-bg: #60a5fa;
      --clock-bg: #0d0d0d;
      --clock-tick: #2a2a2a;
      --clock-tick-hour: #333333;
      --overlay-bg: rgba(0, 0, 0, 0.5);
      --shadow-card: none;
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

  /* ── Topbar ── */
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
    padding:5px 10px;border-radius:5px;font-size:12px;
    font-family:'JetBrains Mono',monospace;cursor:pointer;
    transition:all .2s;border:1px solid var(--border-secondary);
    background:transparent;color:var(--text-dim);
    user-select:none;flex-shrink:0;
  }
  .like-btn:hover{color:#dc2626;border-color:#fecaca}
  @media (prefers-color-scheme: dark) {
    .like-btn:hover{color:#f87171;border-color:#3a1a1a}
  }
  .like-btn.liked{color:#dc2626;border-color:#fecaca;background:#fef2f2}
  @media (prefers-color-scheme: dark) {
    .like-btn.liked{color:#f87171;border-color:#3a1a1a;background:#1a0808}
  }
  .like-btn svg{transition:transform .15s}
  .like-btn:hover svg{transform:scale(1.1)}
  .like-btn.liked svg{fill:currentColor}
  .like-btn .like-count{min-width:14px;text-align:center}

  .share-btn{
    display:inline-flex;align-items:center;gap:4px;
    padding:4px 10px;font-size:11px;font-family:inherit;
    border-radius:4px;cursor:pointer;transition:all .15s;
    background:transparent;border:1px solid var(--border-secondary);
    color:var(--text-dim);
  }
  .share-btn:hover{color:var(--text-primary);border-color:var(--border-hover);background:var(--bg-hover)}

  /* 分享弹窗 */
  .share-modal-body{display:flex;flex-direction:column;gap:20px}
  .share-modal-body .share-row{display:flex;flex-direction:column;gap:20px}
  @media(min-width:500px){
    .share-modal-body{max-width:640px}
    .share-modal-body .share-row{flex-direction:row;align-items:flex-start}
    .share-modal-body .share-right{flex:1;display:flex;flex-direction:column;gap:16px;min-width:0}
  }
  .share-qrcode-wrap{display:flex;flex-direction:column;align-items:center;gap:8px;flex-shrink:0}
  .share-qrcode{width:180px;height:180px;border-radius:8px;border:1px solid var(--border-primary);background:#fff;padding:8px}
  .share-qrcode img{width:100%;height:100%;display:block}
  .share-qrcode-tip{font-size:11px;color:var(--text-muted);font-family:'JetBrains Mono',monospace}
  .share-section{}
  .share-section-title{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-family:'JetBrains Mono',monospace}
  .share-code-box{position:relative;background:var(--bg-tertiary);border:1px solid var(--border-primary);border-radius:6px;padding:10px 12px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--text-secondary);word-break:break-all;line-height:1.6;max-height:100px;overflow-y:auto}
  .share-copy-btn{position:absolute;top:6px;right:6px;font-size:10px;padding:2px 8px;border-radius:4px;border:1px solid var(--border-secondary);background:var(--bg-card);color:var(--text-dim);cursor:pointer;transition:all .15s}
  .share-copy-btn:hover{color:var(--text-primary);border-color:var(--border-hover)}

  .report-btn{
    display:inline-flex;align-items:center;gap:4px;
    padding:4px 10px;font-size:11px;font-family:inherit;
    border-radius:4px;cursor:pointer;transition:all .15s;
    background:transparent;border:1px solid var(--border-secondary);
    color:var(--text-dim);
  }
  .report-btn:hover{color:#dc2626;border-color:#fecaca;background:#fef2f2}
  @media (prefers-color-scheme: dark) {
    .report-btn:hover{color:#f87171;border-color:#3a1a1a;background:#1a0808}
  }

  .modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;display:none;align-items:center;justify-content:center;padding:20px}
  .modal-bg.show{display:flex}
  .modal{background:var(--bg-card);border:1px solid var(--border-primary);border-radius:12px;width:100%;max-width:460px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
  .modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-primary)}
  .modal-close{width:28px;height:28px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;cursor:pointer;color:var(--text-dim);border-radius:6px;transition:all .15s}
  .modal-close:hover{background:var(--bg-hover);color:var(--text-primary)}
  .modal-body{padding:20px}
  .inp{width:100%;padding:8px 12px;font-size:13px;border:1px solid var(--border-primary);border-radius:6px;background:var(--bg-card);color:var(--text-primary);font-family:inherit;resize:vertical}
  .inp::placeholder{color:var(--text-muted)}
  .btn-ghost{border:1px solid var(--border-secondary);background:transparent;color:var(--text-dim);cursor:pointer;border-radius:6px;padding:9px;font-size:13px;transition:all .15s;font-family:inherit}
  .btn-ghost:hover{color:var(--text-primary);border-color:var(--border-hover)}
  .btn-primary{border:none;background:#dc2626;color:#fff;cursor:pointer;border-radius:6px;padding:9px;font-size:13px;font-weight:500;transition:all .15s;font-family:inherit}
  .btn-primary:hover{background:#b91c1c}

  /* ── Toast ──────────────────────────────────────────── */
  #toast{position:fixed;bottom:24px;right:24px;z-index:1001;display:flex;flex-direction:column;gap:8px}
  .toast-item{background:var(--bg-card);border:1px solid var(--border-secondary);color:var(--text-primary);padding:12px 18px;border-radius:8px;font-size:14px;max-width:320px;animation:tIn .3s ease forwards;display:flex;align-items:center;gap:8px;box-shadow:var(--shadow-card)}
  .toast-item.out{animation:tOut .3s ease forwards}
  .toast-item.success::before{content:'✓';color:#4ade80;font-weight:700}
  .toast-item.error::before{content:'✕';color:var(--danger-color);font-weight:700}
  @keyframes tIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
  @keyframes tOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(20px)}}
  @keyframes spin{to{transform:rotate(360deg)}}

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
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
  .dropdown-item.danger{color:#f87171}
  .dropdown-item.danger:hover{background:#1a0808}

  /* ── Layout ── */
  .page-body{margin-top:56px}

  /* ── Hero ── */
  .hero-section{
    background: var(--bg-hero);
    border-bottom: 1px solid var(--border-primary);
  }
  .hero-inner{
    max-width: 1100px;
    margin: 0 auto;
    padding: 40px 32px 36px;
    display: grid;
    grid-template-columns: 1fr 200px;
    gap: 48px;
    align-items: center;
  }

  /* 顶部 meta 行 */
  .hero-meta-row{
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
  }
  .badge{
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-family: 'JetBrains Mono', monospace;
    padding: 3px 9px;
    border-radius: 4px;
    border: 1px solid var(--border-primary);
    background: var(--bg-tertiary);
    color: var(--text-dim);
    letter-spacing: .04em;
  }
  .badge-version{
    color: var(--version-color);
    border-color: var(--version-border);
    background: var(--version-bg);
  }
  .badge-date{
    color: var(--date-color);
    border-color: var(--date-border);
    background: var(--date-bg);
  }
  .badge-views{
    color: var(--views-color);
    border-color: var(--views-border);
    background: var(--views-bg);
  }

  /* 项目名 */
  .proj-title{
    font-size: 28px;
    font-weight: 500;
    color: var(--text-primary);
    line-height: 1.3;
    margin-bottom: 14px;
  }

  /* 描述 */
  .proj-desc{
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.9;
    white-space: pre-wrap;
  }

  /* ── 访问按钮 ── */
  .visit-btn{
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-top: 24px;
    padding: 10px 20px;
    background: var(--accent-color);
    color: var(--text-inverse);
    border-radius: 7px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all .2s;
  }
  .visit-btn:hover{
    background: var(--accent-hover);
    transform: translateY(-1px);
  }

  /* ── 版本历史 Hero 盒子（桌面端替代时钟） ── */
  .version-history-hero{
    background: var(--bg-clock);
    border: 1px solid var(--border-primary);
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 260px;
  }
  .version-history-hero-header{
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 12px 16px;
    font-size: 11px;
    font-family: 'JetBrains Mono', monospace;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .08em;
    border-bottom: 1px solid var(--border-primary);
    flex-shrink: 0;
  }
  .version-history-hero-body{
    padding: 12px 16px;
    overflow-y: auto;
    flex: 1;
  }
  .version-history-hero-body::-webkit-scrollbar{width:3px}
  .version-history-hero-body::-webkit-scrollbar-track{background:transparent}
  .version-history-hero-body::-webkit-scrollbar-thumb{background:var(--border-secondary);border-radius:3px}
  .version-history-hero-body .timeline-container{padding-left:16px;margin-top:0}
  .version-history-hero-body .timeline-container::before{left:4px}
  .version-history-hero-body .timeline-item::before{left:-16px;width:7px;height:7px;top:5px}
  .version-history-hero-body .timeline-version{font-size:11px}
  .version-history-hero-body .timeline-date{font-size:9px}
  .version-history-hero-body .timeline-changelog{font-size:10px}
  .version-history-hero-body .timeline-desc{font-size:10px}
  .version-history-hero-body .timeline-item{padding-bottom:12px}
  .version-history-hero-body .timeline-spinner{margin:4px 0}

  /* ── 正文区 ── */
  .content-area{
    max-width: 1100px;
    margin: 0 auto;
    padding: 28px 32px 60px;
    display: grid;
    grid-template-columns: 1fr 260px;
    gap: 32px;
    align-items: start;
  }

  /* 信息卡片 */
  .info-card{
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: var(--shadow-card);
  }
  .info-card-header{
    padding: 13px 18px;
    border-bottom: 1px solid var(--border-primary);
    font-size: 11px;
    font-family: 'JetBrains Mono', monospace;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .08em;
  }
  .info-row{
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--bg-info-row);
    transition: background .15s;
  }
  .info-row:last-child{border-bottom:none}
  .info-row:hover{background:var(--bg-hover)}
  .info-row-icon{
    flex-shrink: 0;
    margin-top: 1px;
    color: var(--text-muted);
  }
  .info-row-content{flex:1;min-width:0}
  .info-row-label{
    font-size: 10px;
    font-family: 'JetBrains Mono', monospace;
    color: var(--text-muted);
    margin-bottom: 3px;
    text-transform: uppercase;
    letter-spacing: .06em;
  }
  .info-row-value{
    font-size: 13px;
    color: var(--text-dim);
    word-break: break-all;
    line-height: 1.5;
  }
  .info-row-value a{
    color: var(--link-color);
    text-decoration: none;
    transition: color .15s;
  }
  .info-row-value a:hover{
    color: var(--link-hover);
    text-decoration: underline;
  }

  /* 作者卡片 */
  .author-card{
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: 10px;
    padding: 20px;
    box-shadow: var(--shadow-card);
  }
  .author-avatar{
    width: 52px;
    height: 52px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border-primary);
    display: block;
    margin-bottom: 12px;
  }
  .author-name{
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 4px;
  }
  .author-sub{
    font-size: 11px;
    color: var(--text-muted);
    font-family: 'JetBrains Mono', monospace;
  }
  .author-link{
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 14px;
    font-size: 12px;
    color: var(--text-muted);
    text-decoration: none;
    border: 1px solid var(--border-primary);
    padding: 6px 12px;
    border-radius: 5px;
    transition: all .2s;
  }
  .author-link:hover{
    color: var(--text-primary);
    border-color: var(--border-hover);
  }

  /* 分割线 */
  .section-divider{
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border-primary) 20%, var(--border-primary) 80%, transparent);
  }

  /* ── 404 ── */
  .not-found{
    min-height: calc(100vh - 56px);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .not-found-box{
    text-align: center;
    padding: 40px;
  }

  /* ── Footer ── */
  .page-footer{
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 32px 40px;
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

  /* ── 响应式 ── */
  @media(max-width:768px){
    .content-area{grid-template-columns:1fr;padding:20px 20px 48px}
    .proj-title{font-size:22px}
    .hero-inner{grid-template-columns:1fr;padding:32px 20px 28px;gap:24px}
    .version-history-hero{display:none}
    #version-history-row{display:flex}
  }

  /* 桌面端隐藏 info-card 中的版本历史，手机端显示 */
  @media(min-width:769px){
    #version-history-row{display:none}
  }

  /* ── 版本历史时间线 ──────────────────────────────────── */
  .timeline-container{
    position:relative;
    padding-left:20px;
    margin-top:8px;
  }
  .timeline-container::before{
    content:'';
    position:absolute;
    left:5px;
    top:4px;
    bottom:4px;
    width:1px;
    background:var(--border-primary);
  }
  .timeline-item{
    position:relative;
    padding-bottom:16px;
  }
  .timeline-item:last-child{
    padding-bottom:0;
  }
  .timeline-item::before{
    content:'';
    position:absolute;
    left:-20px;
    top:6px;
    width:9px;
    height:9px;
    border-radius:50%;
    background:var(--bg-card);
    border:2px solid var(--border-secondary);
    z-index:1;
    transition:all .2s;
  }
  .timeline-item:hover::before{
    border-color:var(--version-color);
    background:var(--version-bg);
  }
  .timeline-version{
    font-family:'JetBrains Mono',monospace;
    font-size:12px;
    font-weight:600;
    color:var(--version-color);
    margin-bottom:3px;
    letter-spacing:.04em;
  }
  .timeline-date{
    font-family:'JetBrains Mono',monospace;
    font-size:10px;
    color:var(--text-muted);
    margin-bottom:4px;
    letter-spacing:.04em;
  }
  .timeline-changelog{
    font-size:11px;
    color:var(--text-dim);
    line-height:1.6;
    white-space:pre-wrap;
    word-break:break-all;
  }
  .timeline-desc{
    font-size:11px;
    color:var(--text-muted);
    line-height:1.5;
    margin-top:3px;
  }
  .timeline-empty{
    font-size:12px;
    color:var(--text-muted);
    padding:4px 0 0;
    font-style:italic;
  }

  /* 版本标签 */ .badge-tag{font-size:9px;padding:1px 6px;border-radius:99px;font-family:'JetBrains Mono',monospace;font-weight:500;display:inline-block;margin-left:6px;vertical-align:middle}
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

  /* 技术栈标签 */ .tech-badge{display:inline-block;font-size:10px;padding:2px 8px;border-radius:4px;font-family:'JetBrains Mono',monospace;font-weight:500;margin:1px;background:var(--bg-tertiary);color:var(--text-dim);border:1px solid var(--border-primary)}
  .tech-badges{display:flex;flex-wrap:wrap;gap:2px;margin-top:2px}

  /* 加载 spinner */
  .timeline-spinner{
    width:20px;
    height:20px;
    border:2px solid var(--border-primary);
    border-top-color:var(--text-dim);
    border-radius:50%;
    animation:spin .8s linear infinite;
    margin:8px 0 4px;
  }

  @media(max-width:768px){
    .timeline-container{padding-left:18px}
    .timeline-item::before{left:-18px;width:8px;height:8px;top:5px}
    .timeline-version{font-size:11px}
    .timeline-changelog{font-size:10px}
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

  <?php if ($me): ?>
  <a href="/messages.php" style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:6px;transition:all .2s;color:var(--text-dim);text-decoration:none;position:relative;margin-right:4px" title="站内信"
     onmouseover="this.style.background='#000';this.style.color='#fff'"
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
<!-- ══ 404 ══ -->
<div class="not-found">
  <div class="not-found-box">
    <div style="font-size:64px;opacity:.08;margin-bottom:20px;font-family:'JetBrains Mono',monospace;color:var(--text-muted);">404</div>
    <p style="font-size:16px;color:var(--text-dim);margin-bottom:8px">找不到这个项目</p>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:28px">该项目可能不存在、已被删除或尚未通过审核</p>
    <a href="/home.php"
       style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-dim);text-decoration:none;border:1px solid var(--border-secondary);padding:8px 18px;border-radius:6px;transition:all .2s"
       onmouseover="this.style.borderColor='var(--border-hover)';this.style.color='var(--text-primary)'"
       onmouseout="this.style.borderColor='var(--border-secondary)';this.style.color='var(--text-dim)'">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      返回项目广场
    </a>
  </div>
</div>

<?php else:
  $contacts = [];
  if (!empty($project['contact_email']))  $contacts[] = ['type'=>'email',  'label'=>'邮箱', 'value'=>$project['contact_email']];
  if (!empty($project['contact_phone']))  $contacts[] = ['type'=>'phone',  'label'=>'电话', 'value'=>$project['contact_phone']];
  if (!empty($project['contact_qq']))     $contacts[] = ['type'=>'qq',     'label'=>'QQ',   'value'=>$project['contact_qq']];
  if (!empty($project['contact_wechat'])) $contacts[] = ['type'=>'wechat', 'label'=>'微信', 'value'=>$project['contact_wechat']];
?>
<!-- ══ HERO ══ -->
<div class="hero-section">
  <div class="hero-inner">

    <!-- 左：标题 & 描述 -->
    <div>
      <div class="hero-meta-row">
        <?php if (!empty($project['version'])): ?>
        <span class="badge badge-version"><?= htmlspecialchars($project['version']) ?></span>
        <?php endif; ?>
        <?php if (!empty($project['release_date'])): ?>
        <span class="badge badge-date">
          <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <?= htmlspecialchars($project['release_date']) ?>
        </span>
        <?php endif; ?>
        <span class="badge badge-views">
          <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <?= (int)($project['view_count'] ?? 0) ?> 次浏览
        </span>
        <button class="share-btn" onclick="openShareModal()" title="分享项目" style="margin-right:8px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>分享</button>
        <?php if ($me): ?>
        <?php if (!$isSelf): ?>
        <button class="report-btn" onclick="openReportModal()" title="举报该项目" style="margin-right:8px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>举报</button>
        <?php endif; ?>
        <button class="like-btn <?= ($project['is_liked'] ?? false) ? 'liked' : '' ?>" id="detail-like-btn" onclick="toggleLike(<?= (int)$targetId ?>,this)" title="<?= ($project['is_liked'] ?? false) ? '取消点赞' : '点赞' ?>">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="<?= ($project['is_liked'] ?? false) ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          <span class="like-count"><?= (int)($project['like_count'] ?? 0) ?></span>
        </button>
        <?php else: ?>
        <span class="like-btn" style="cursor:default" title="登录后即可点赞">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          <span class="like-count"><?= (int)($project['like_count'] ?? 0) ?></span>
        </span>
        <?php endif; ?>
      </div>

      <h1 class="proj-title"><?= htmlspecialchars($project['name'] ?? '') ?></h1>

      <?php if (!empty($project['description'])): ?>
      <p class="proj-desc"><?= htmlspecialchars($project['description']) ?></p>
      <?php endif; ?>

      <?php if (!empty($project['project_url'])): ?>
      <a href="<?= htmlspecialchars($project['project_url']) ?>" target="_blank" rel="noopener" class="visit-btn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        访问项目
      </a>
      <?php endif; ?>
    </div>

    <!-- 右：版本历史（桌面端） -->
    <div class="version-history-hero" id="version-history-hero">
      <div class="version-history-hero-header">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        版本历史
      </div>
      <div class="version-history-hero-body" id="version-history-hero-body">
        <div class="timeline-spinner" id="version-hero-spinner"></div>
      </div>
    </div>

  </div>
</div>
<div class="section-divider"></div>

<!-- ══ 正文区 ══ -->
<div class="content-area">

  <!-- 左：链接 & 联系 -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <div class="info-card">
      <div class="info-card-header">项目链接</div>
      <?php if (!empty($project['project_url'])): ?>
      <div class="info-row">
        <div class="info-row-icon">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        </div>
        <div class="info-row-content">
          <div class="info-row-label">项目地址</div>
          <div class="info-row-value">
            <a href="<?= htmlspecialchars($project['project_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($project['project_url']) ?></a>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!empty($project['developer_website'])): ?>
      <div class="info-row">
        <div class="info-row-icon">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        </div>
        <div class="info-row-content">
          <div class="info-row-label">开发者主页</div>
          <div class="info-row-value">
            <a href="<?= htmlspecialchars($project['developer_website']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($project['developer_website']) ?></a>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($contacts)): ?>
    <div class="info-card">
      <div class="info-card-header">联系方式</div>
      <?php foreach ($contacts as $c): ?>
      <div class="info-row">
        <div class="info-row-icon">
          <?php if ($c['type'] === 'email'): ?>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <?php elseif ($c['type'] === 'phone'): ?>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.19h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <?php else: ?>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/></svg>
          <?php endif; ?>
        </div>
        <div class="info-row-content">
          <div class="info-row-label"><?= htmlspecialchars($c['label']) ?></div>
          <div class="info-row-value">
            <?php if ($c['type'] === 'email'): ?>
              <a href="mailto:<?= htmlspecialchars($c['value']) ?>"><?= htmlspecialchars($c['value']) ?></a>
            <?php else: ?>
              <?= htmlspecialchars($c['value']) ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($project['project_url'])): ?>
    <!-- ── 项目截图 ──────────────────────────────────────────── -->
    <div class="info-card" style="margin-top:20px">
      <div class="info-card-header" style="display:flex;align-items:center;justify-content:space-between">
        <span>项目截图</span>
        <?php $hasCustomCover = !empty($project['cover_url']); ?>
        <?php $isProjectOwner = $me && (int)($me['user_id'] ?? $me['id']) === (int)$project['user_id']; ?>
        <?php if ($isProjectOwner && !empty($project['screenshot_task_id']) && !$hasCustomCover): ?>
        <button id="screenshot-refresh-btn" class="btn-ghost" style="font-size:11px;padding:3px 10px;display:flex;align-items:center;gap:4px" onclick="refreshScreenshot()" title="刷新截图">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          刷新 <span id="screenshot-refresh-remaining">(0/3)</span>
        </button>
        <?php endif; ?>
      </div>
      <div id="screenshot-area" style="position:relative">
        <?php if ($hasCustomCover): ?>
        <div id="screenshot-scroll" style="max-height:400px;overflow-y:auto;border-radius:6px;border:1px solid var(--border-primary);background:var(--bg-tertiary)">
          <img id="screenshot-img"
               src="<?= htmlspecialchars($project['cover_url']) ?>"
               alt="项目封面" style="width:100%;display:block"
               onerror="this.src='<?= htmlspecialchars(APP_URL) ?>/img/fohok-3.png'">
        </div>
        <?php elseif (!empty($project['screenshot_task_id'])): ?>
        <div id="screenshot-scroll" style="max-height:400px;overflow-y:auto;border-radius:6px;border:1px solid var(--border-primary);background:var(--bg-tertiary)">
          <img id="screenshot-img"
               src="<?= htmlspecialchars(WEBSCR_API_URL . '/image.php?id=' . urlencode($project['screenshot_task_id'])) ?>"
               alt="项目截图" style="width:100%;display:block"
               onerror="onScreenshotError()">
        </div>
        <div id="screenshot-retry" style="display:none;flex-direction:column;align-items:center;padding:24px;gap:10px">
        <?php else: ?>
        <div id="screenshot-placeholder" style="display:flex;flex-direction:column;align-items:center;padding:24px;gap:10px">
        <?php endif; ?>
        <?php if (!$hasCustomCover): ?>
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" opacity="0.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          <p id="screenshot-status" style="font-size:12px;color:var(--text-muted);text-align:center">
            <?= !empty($project['screenshot_task_id']) ? '截图载入失败，请重试' : '该项目暂无预览截图' ?>
          </p>
          <?php if ($me): ?>
          <button id="screenshot-btn" class="btn btn-ghost" style="font-size:12px;padding:6px 14px" onclick="requestScreenshot()">
            <?= !empty($project['screenshot_task_id']) ? '重新请求截图' : '请求截图' ?>
          </button>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <!-- 刷新进度区域 -->
        <div id="screenshot-progress" style="display:none;flex-direction:column;align-items:center;padding:24px;gap:10px">
          <div class="spinner" style="width:28px;height:28px;border:3px solid var(--border-primary);border-top-color:var(--text-dim);border-radius:50%;animation:spin .8s linear infinite"></div>
          <p id="screenshot-progress-text" style="font-size:12px;color:var(--text-muted);text-align:center">正在刷新截图...</p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($project['download_url'])): ?>
    <div class="info-card" style="margin-top:20px">
      <div class="info-card-header"><span>文件下载</span></div>
      <div style="display:flex;align-items:center;gap:12px;padding:12px;margin-top:8px;border-radius:8px;border:1px solid var(--border-primary);background:var(--bg-tertiary)">
        <div style="flex-shrink:0;width:40px;height:40px;border-radius:8px;background:var(--bg-hover);display:flex;align-items:center;justify-content:center">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-dim)"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:500;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($project['download_filename'] ?: basename($project['download_url']) ?: '下载文件') ?></div>
          <div style="display:flex;align-items:center;gap:6px;margin-top:2px">
            <?php if (!empty($project['download_filetype'])): ?>
            <span style="font-size:10px;font-family:'JetBrains Mono',monospace;background:var(--bg-hover);color:var(--text-dim);padding:1px 5px;border-radius:3px"><?= htmlspecialchars($project['download_filetype']) ?></span>
            <?php endif; ?>
            <?php if (!empty($project['download_filesize'])): ?>
            <span style="font-size:10px;color:var(--text-muted);font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($project['download_filesize']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <a href="<?= htmlspecialchars($project['download_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="font-size:12px;padding:5px 14px;flex-shrink:0;text-decoration:none">下载</a>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- 右：作者 & 元信息 -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <div class="author-card">
      <div style="font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:16px">开发者信息</div>
      <img src="<?= htmlspecialchars($project['author_pic'] ?? '') ?>"
           onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($project['author_name']??'U') ?>&background=1a1a1a&color=555&size=52'"
           class="author-avatar" alt="avatar">
      <div class="author-name"><?= htmlspecialchars($project['author_name'] ?? '未知开发者') ?></div>
      <?php if (!empty($project['user_id'])): ?>
      <a href="/user.php?id=<?= (int)$project['user_id'] ?>" class="author-link">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        查看主页
      </a>
      <?php endif; ?>
    </div>

    <div class="info-card">
      <div class="info-card-header">项目信息</div>
      <div class="info-row">
        <div class="info-row-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
        <div class="info-row-content">
          <div class="info-row-label">当前版本</div>
          <div class="info-row-value" style="font-family:'JetBrains Mono',monospace;color:var(--version-color)"><?= htmlspecialchars($project['version'] ?? '-') ?></div>
        </div>
      </div>
      <!-- 版本历史 -->
      <div class="info-row" id="version-history-row">
        <div class="info-row-icon">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="info-row-content">
          <div class="info-row-label">版本历史</div>
          <div id="version-history-container">
            <div class="timeline-spinner" id="version-spinner"></div>
          </div>
        </div>
      </div>
      <?php if (!empty($project['tech_stack'])): ?>
      <div class="info-row">
        <div class="info-row-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></div>
        <div class="info-row-content">
          <div class="info-row-label">技术栈</div>
          <div class="info-row-value" style="font-size:12px">
            <?php foreach (array_filter(array_map('trim', explode(',', $project['tech_stack'] ?? ''))) as $tech): ?>
            <span class="tech-badge"><?= htmlspecialchars($tech) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!empty($project['release_date'])): ?>
      <div class="info-row">
        <div class="info-row-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div class="info-row-content">
          <div class="info-row-label">发布日期</div>
          <div class="info-row-value" style="font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($project['release_date']) ?></div>
        </div>
      </div>
      <?php endif; ?>
      <div class="info-row">
        <div class="info-row-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
        <div class="info-row-content">
          <div class="info-row-label">总浏览</div>
          <div class="info-row-value" style="font-family:'JetBrains Mono',monospace"><?= number_format((int)($project['view_count'] ?? 0)) ?></div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-row-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div class="info-row-content">
          <div class="info-row-label">审核时间</div>
          <div class="info-row-value" style="font-family:'JetBrains Mono',monospace"><?= date('Y-m-d', strtotime($project['created_at'])) ?></div>
        </div>
      </div>
    </div>

  </div>

</div><!-- .content-area -->
<?php endif; ?>

<!-- ══ Footer ══ -->
<div id="toast"></div>

<footer class="page-footer">
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

</div><!-- .page-body -->

<script>
/* ── Dropdown ── */
function dd(id) { document.getElementById(id).classList.toggle('show'); }
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
});

// ── 项目截图 ───────────────────────────────────────────────
let screenshotPollTimer = null;

// 页面加载时获取刷新次数
(async function() {
  try {
    const res = await fetch('/api/project.php?action=screenshot_refresh_count');
    const d = await res.json();
    if (d.ok) updateRefreshCount(d.count, d.max);
  } catch(e) {}
})();

function updateRefreshCount(count, max) {
  const el = document.getElementById('screenshot-refresh-remaining');
  if (el) el.textContent = '(' + count + '/' + max + ')';
}

async function refreshScreenshot() {
  const btn = document.getElementById('screenshot-refresh-btn');
  btn.disabled = true;

  // 隐藏图片，显示进度区域
  const scroll = document.getElementById('screenshot-scroll');
  if (scroll) scroll.style.display = 'none';
  const progress = document.getElementById('screenshot-progress');
  if (progress) progress.style.display = 'flex';
  const progressText = document.getElementById('screenshot-progress-text');
  if (progressText) progressText.textContent = '正在提交刷新请求...';

  try {
    const fd = new FormData();
    fd.append('action', 'screenshot_request');
    fd.append('project_id', '<?= $targetId ?>');
    fd.append('refresh', '1');
    const res = await fetch('/api/project.php', { method: 'POST', body: fd });
    const d = await res.json();
    if (!d.ok) {
      showToast(d.msg||'刷新失败','error');
      btn.disabled = false;
      if (progress) progress.style.display = 'none';
      if (scroll) scroll.style.display = '';
      return;
    }
    // 更新次数显示
    const cntRes = await fetch('/api/project.php?action=screenshot_refresh_count');
    const cntD = await cntRes.json();
    if (cntD.ok) updateRefreshCount(cntD.count, cntD.max);
    startScreenshotPoll(d.task_id);
  } catch(e) {
    showToast('网络错误','error');
    btn.disabled = false;
    if (progress) progress.style.display = 'none';
    if (scroll) scroll.style.display = '';
  }
}

async function onScreenshotError() {
  // 隐藏损坏的图片和滚动容器，显示重试区域
  const scroll = document.getElementById('screenshot-scroll');
  if (scroll) scroll.style.display = 'none';
  const retry = document.getElementById('screenshot-retry');
  if (retry) retry.style.display = 'flex';
  // 隐藏刷新按钮
  const refreshBtn = document.getElementById('screenshot-refresh-btn');
  if (refreshBtn) refreshBtn.style.display = 'none';
  // 清除数据库中的截图任务ID，以便用户可重新生成
  try {
    const fd = new FormData();
    fd.append('action', 'screenshot_clear');
    fd.append('project_id', '<?= $targetId ?>');
    await fetch('/api/project.php', { method: 'POST', body: fd });
  } catch(e) { /* 静默 */ }
}

async function requestScreenshot() {
  const btn = document.getElementById('screenshot-btn');
  btn.disabled = true;
  btn.textContent = 'Please be patient...';
  showToast('Please be patient...', 'success');

  try {
    const fd = new FormData();
    fd.append('action', 'screenshot_request');
    fd.append('project_id', '<?= $targetId ?>');
    const res = await fetch('/api/project.php', { method: 'POST', body: fd });
    const d = await res.json();
    if (!d.ok) { showToast(d.msg||'请求失败','error'); btn.disabled = false; btn.textContent = '请求截图'; return; }
    startScreenshotPoll(d.task_id);
  } catch(e) {
    showToast('网络错误','error');
    btn.disabled = false;
    btn.textContent = '请求截图';
  }
}

function startScreenshotPoll(taskId) {
  if (screenshotPollTimer) clearInterval(screenshotPollTimer);
  const btn = document.getElementById('screenshot-btn');
  const statusEl = document.getElementById('screenshot-status');
  const progressText = document.getElementById('screenshot-progress-text');
  const isRefresh = (progressText && document.getElementById('screenshot-progress').style.display !== 'none');
  if (btn) { btn.textContent = 'Please be patient...'; btn.disabled = true; }
  let polls = 0;
  const maxPolls = 60;

  screenshotPollTimer = setInterval(async () => {
    polls++;
    try {
      const res = await fetch('/api/project.php?action=screenshot_status&task_id=' + encodeURIComponent(taskId));
      const d = await res.json();
      if (!d.ok) {
        clearInterval(screenshotPollTimer);
        const msg = d.msg||'查询失败';
        if (isRefresh && progressText) progressText.textContent = msg;
        else if (statusEl) { statusEl.textContent = msg; }
        if (btn) { btn.disabled = false; btn.textContent = '重试'; }
        return;
      }
      const cond = d.condition;
      if (cond === '已完成') {
        clearInterval(screenshotPollTimer);
        showScreenshotImage(d.image_url);
      } else if (cond === '异常') {
        clearInterval(screenshotPollTimer);
        const msg = '截图失败啦，请重试';
        if (isRefresh && progressText) progressText.textContent = msg;
        else if (statusEl) { statusEl.textContent = msg; statusEl.style.color = 'var(--danger-color)'; }
        if (btn) { btn.disabled = false; btn.textContent = '重试'; }
      } else {
        const progress = ['排队中','待处理','处理中'];
        const txt = progress.includes(cond) ? '截图' + cond + '...' : cond;
        if (isRefresh && progressText) progressText.textContent = txt;
        else if (statusEl) statusEl.textContent = txt;
        if (polls >= maxPolls) {
          clearInterval(screenshotPollTimer);
          const msg = '截图超时，请稍后刷新页面查看';
          if (isRefresh && progressText) progressText.textContent = msg;
          else if (statusEl) statusEl.textContent = msg;
          if (btn) { btn.disabled = false; btn.textContent = '刷新查看'; }
        }
      }
    } catch(e) {
      clearInterval(screenshotPollTimer);
      const msg = '查询失败';
      if (isRefresh && progressText) progressText.textContent = msg;
      else if (statusEl) statusEl.textContent = msg;
      if (btn) { btn.disabled = false; btn.textContent = '重试'; }
    }
  }, 5000);
}

function showScreenshotImage(imageUrl) {
  const area = document.getElementById('screenshot-area');
  if (!area) return;
  // 隐藏进度区域
  const progress = document.getElementById('screenshot-progress');
  if (progress) progress.style.display = 'none';
  area.innerHTML = '<div id="screenshot-scroll" style="max-height:400px;overflow-y:auto;border-radius:6px;border:1px solid var(--border-primary);background:var(--bg-tertiary)"><img src="' + imageUrl + '" alt="项目截图" style="width:100%;display:block" onclick="window.open(this.src,\'_blank\')" title="点击查看原图"></div>';
  // 显示刷新按钮
  const refreshBtn = document.getElementById('screenshot-refresh-btn');
  if (refreshBtn) refreshBtn.style.display = 'flex';
}

// ── 点赞 / 取消点赞 ─────────────────────────────────────────
async function toggleLike(projectId, btn) {
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
    } else {
      alert(data.msg || '操作失败');
    }
  } catch(err) {
    alert('网络异常，请重试');
  } finally {
    btn.disabled = false;
  }
}


// ── 版本历史 ─────────────────────────────────────────────
(async function loadVersionHistory() {
  try {
    const res = await fetch('/api/project.php?action=version_history&id=<?= $targetId ?>');
    const data = await res.json();

    const versions = (data.ok && data.versions && data.versions.length > 0) ? data.versions : [];
    const renderTimeline = function(container) {
      const timeline = document.createElement('div');
      timeline.className = 'timeline-container';
      versions.forEach(function(v) {
        const item = document.createElement('div');
        item.className = 'timeline-item';
        var html = '<div class="timeline-version">' + escapeHtml(v.version || '') + '</div>';
        if (v.tag) html += '<span class="badge-tag ' + escapeHtml(v.tag) + '">' + escapeHtml(v.tag) + '</span>';
        if (v.release_date) html += '<div class="timeline-date">' + escapeHtml(v.release_date) + '</div>';
        if (v.changelog) html += '<div class="timeline-changelog">' + escapeHtml(v.changelog) + '</div>';
        item.innerHTML = html;
        timeline.appendChild(item);
      });
      container.appendChild(timeline);
    };

    const renderEmpty = function(container) {
      container.innerHTML = '<div class="timeline-empty">暂无历史版本</div>';
    };

    // Hero 桌面端容器
    const heroBody = document.getElementById('version-history-hero-body');
    const heroSpinner = document.getElementById('version-hero-spinner');
    if (heroBody && heroSpinner) {
      heroSpinner.remove();
      versions.length ? renderTimeline(heroBody) : renderEmpty(heroBody);
    }

    // Info card 手机端容器
    const infoContainer = document.getElementById('version-history-container');
    const infoSpinner = document.getElementById('version-spinner');
    if (infoContainer && infoSpinner) {
      infoSpinner.remove();
      versions.length ? renderTimeline(infoContainer) : renderEmpty(infoContainer);
    }
  } catch(err) {
    ['version-history-hero-body','version-history-container'].forEach(function(id) {
      const el = document.getElementById(id);
      if (el) {
        const s = el.querySelector('.timeline-spinner');
        if (s) s.remove();
        el.innerHTML = '<div class="timeline-empty">加载失败</div>';
      }
    });
  }
})();

function escapeHtml(str) {
  var div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

</script>

<!-- ══ 举报弹窗 ══ -->
<div class="modal-bg" id="reportModal">
  <div class="modal">
    <div class="modal-header">
      <span style="font-size:13px;font-weight:500;color:var(--text-primary);display:flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#f87171"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        举报项目
      </span>
      <button class="modal-close" onclick="closeReportModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;line-height:1.6">
        您正在对 <strong style="color:var(--text-primary)"><?= htmlspecialchars($project['name'] ?? '') ?></strong> 进行举报...
      </p>
      <form id="reportForm" onsubmit="submitReport(event)">
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:12px;font-weight:500;color:var(--text-dim);margin-bottom:6px">举报类型 *</label>
          <select id="report-type" class="inp" required>
            <option value="">请选择举报类型</option>
            <option value="spam">垃圾信息</option>
            <option value="violation">违规内容</option>
            <option value="copyright">侵权问题</option>
            <option value="misleading">虚假误导</option>
            <option value="other">其他问题</option>
          </select>
        </div>
        <div style="margin-bottom:14px" id="report-email-row">
          <label style="display:block;font-size:12px;font-weight:500;color:var(--text-dim);margin-bottom:6px">举报结果通知邮箱(可选)</label>
          <input type="email" id="report-email" class="inp" placeholder="我们将通过此邮箱通知你处理结果">
        </div>
        <div style="margin-bottom:16px">
          <label style="display:block;font-size:12px;font-weight:500;color:var(--text-dim);margin-bottom:6px">其他补充(可选)</label>
          <textarea id="report-reason" class="inp" rows="3" placeholder="请补充更多细节..."></textarea>
        </div>
        <div style="display:flex;gap:10px">
          <button type="button" onclick="closeReportModal()" class="btn-ghost" style="flex:1">取消</button>
          <button type="submit" class="btn-primary" style="flex:1">提交举报</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 分享弹窗 -->
<div class="modal-bg" id="shareModal" onclick="if(event.target===this)closeShareModal()">
  <div class="modal" style="max-width:440px" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between p-5 border-b" style="border-color:var(--border-primary)">
      <h3 class="font-medium" style="color:var(--text-primary)">分享项目</h3>
      <button onclick="closeShareModal()" class="w-7 h-7 flex items-center justify-center rounded transition-colors" style="color:var(--text-dim)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="p-5 share-modal-body">
      <div class="share-row">
        <div class="share-qrcode-wrap">
          <div class="share-qrcode"><img id="share-qr-img" alt="二维码"></div>
          <div class="share-qrcode-tip">扫码查看项目</div>
        </div>
        <div class="share-right">
          <div class="share-section">
            <div class="share-section-title">项目链接</div>
            <div class="share-code-box" id="share-link-box"><span id="share-link-text"></span><button class="share-copy-btn" onclick="copyShareText('link')">复制</button></div>
          </div>
          <div class="share-section">
            <div class="share-section-title">嵌入式卡片</div>
            <div class="share-code-box" id="share-embed-box"><span id="share-embed-text"></span><button class="share-copy-btn" onclick="copyShareText('embed')">复制</button></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>