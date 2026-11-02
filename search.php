<?php
require_once __DIR__ . '/bootstrap.php';
$me      = optionalLogin();
$isAdmin = $me && in_array($me['email'], ADMIN_EMAILS);

$preKeyword = trim($_GET['s'] ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta property="og:title" content="项目搜索 · 文谶驿站">
<meta property="og:description" content="一个为独立开发者打造的开源项目展示空间。
发布你的作品，探索他人的创造，连接志同道合的人。">
<meta property="og:image" content="https://fohok.xin/img/fohok-2.png">
<meta property="og:url" content="https://fohok.xin/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="文谶驿站 · 开发者开放仓库">
<title><?= $preKeyword ? htmlspecialchars($preKeyword).' · ' : '' ?>搜索 · 文谶驿站</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Noto+Sans+SC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
/* ── Reset & Base ─────────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0}
html{-webkit-text-size-adjust:100%}

/* 日间主题（默认） */
:root {
  --bg-primary: #fafafa;
  --bg-secondary: #ffffff;
  --bg-tertiary: #f5f5f5;
  --bg-hover: #f0f0f0;
  --bg-card: #ffffff;
  --bg-input: #f5f5f5;
  --bg-modal: #ffffff;
  --bg-search-input: #f5f5f5;
  --bg-detail-row: #f8f8f8;
  --text-primary: #171717;
  --text-secondary: #404040;
  --text-dim: #737373;
  --text-muted: #a3a3a3;
  --text-inverse: #fafafa;
  --border-primary: #e5e5e5;
  --border-secondary: #d4d4d4;
  --border-hover: #a3a3a3;
  --border-input: #d4d4d4;
  --border-input-focus: #a3a3a3;
  --accent-color: #171717;
  --accent-hover: #404040;
  --link-color: #2563eb;
  --link-hover: #3b82f6;
  --danger-color: #dc2626;
  --success-color: #16a34a;
  --warn-color: #ca8a04;
  --highlight-color: #d97706;
  --highlight-bg: transparent;
  --badge-guest-bg: #eff6ff;
  --badge-guest-color: #1e40af;
  --badge-guest-border: #bfdbfe;
  --badge-version-bg: #f5f5f5;
  --badge-version-color: #737373;
  --badge-version-border: #d4d4d4;
  --overlay-bg: rgba(0, 0, 0, 0.5);
  --shadow-card: 0 1px 3px rgba(0,0,0,0.08);
  --shadow-lg: 0 4px 24px rgba(0,0,0,0.1);
  --scrollbar-track: #f5f5f5;
  --scrollbar-thumb: #d4d4d4;
  --card-border: #e5e5e5;
  --card-bg: #ffffff;
  --card-hover-bg: #f8f8f8;
  --card-hover-border: #d4d4d4;
  --card-foot-border: #f0f0f0;
  --state-text-color: #a3a3a3;
}

/* 夜间主题 */
@media (prefers-color-scheme: dark) {
  :root {
    --bg-primary: #050505;
    --bg-secondary: #0a0a0a;
    --bg-tertiary: #111111;
    --bg-hover: #1a1a1a;
    --bg-card: #0d0d0d;
    --bg-input: #111111;
    --bg-modal: #0a0a0a;
    --bg-search-input: #0d0d0d;
    --bg-detail-row: #0d0d0d;
    --text-primary: #f5f5f5;
    --text-secondary: #d4d4d4;
    --text-dim: #a3a3a3;
    --text-muted: #737373;
    --text-inverse: #0a0a0a;
    --border-primary: #1a1a1a;
    --border-secondary: #222222;
    --border-hover: #404040;
    --border-input: #1e1e1e;
    --border-input-focus: #333333;
    --accent-color: #e5e5e5;
    --accent-hover: #ffffff;
    --link-color: #60a5fa;
    --link-hover: #93c5fd;
    --danger-color: #f87171;
    --success-color: #4ade80;
    --warn-color: #fbbf24;
    --highlight-color: #f59e0b;
    --highlight-bg: transparent;
    --badge-guest-bg: #0f172a;
    --badge-guest-color: #60a5fa;
    --badge-guest-border: #1e3a5f;
    --badge-version-bg: #0d0d0d;
    --badge-version-color: #444444;
    --badge-version-border: #1a1a1a;
    --overlay-bg: rgba(0, 0, 0, 0.88);
    --shadow-card: none;
    --shadow-lg: 0 4px 24px rgba(0,0,0,0.3);
    --scrollbar-track: #0a0a0a;
    --scrollbar-thumb: #333333;
    --card-border: #181818;
    --card-bg: #0d0d0d;
    --card-hover-bg: #101010;
    --card-hover-border: #252525;
    --card-foot-border: #161616;
    --state-text-color: #333333;
  }
}

body{
  background: var(--bg-primary);
  color: var(--text-primary);
  font-family: 'Noto Sans SC', sans-serif;
  min-height: 100vh;
  line-height: 1.5;
}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:var(--scrollbar-track)}
::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb);border-radius:2px}
a{text-decoration:none}

/* ── Topbar ───────────────────────────────────────────────── */
#topbar{
  position:fixed;top:0;left:0;right:0;height:52px;
  background:var(--bg-secondary);
  border-bottom:1px solid var(--border-primary);
  z-index:50;display:flex;align-items:center;
  padding:0 16px;gap:10px;backdrop-filter:blur(12px);
}
.topbar-back{display:flex;align-items:center;gap:6px;opacity:.5;transition:opacity .2s;color:var(--text-primary)}
.topbar-back:hover{opacity:1}
.topbar-logo{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-muted);letter-spacing:.1em}
@media(max-width:480px){.topbar-logo{display:none}}

/* ── Buttons ──────────────────────────────────────────────── */
.btn{
  display:inline-flex;align-items:center;justify-content:center;
  gap:6px;padding:0 18px;height:44px;border-radius:8px;
  font-size:14px;font-weight:500;cursor:pointer;
  transition:all .2s;border:none;font-family:inherit;white-space:nowrap;flex-shrink:0;
}
.btn-primary{background:var(--accent-color);color:var(--text-inverse)}
.btn-primary:hover{background:var(--accent-hover)}
.btn-primary:active{background:var(--text-muted);transform:scale(.98)}
.btn-primary:disabled{background:var(--bg-hover);color:var(--text-muted);cursor:not-allowed}
.btn-ghost{background:transparent;color:var(--text-dim);border:1px solid var(--border-secondary);padding:0 14px;height:36px;font-size:13px}
.btn-ghost:hover{color:var(--text-primary);border-color:var(--border-hover)}

/* ── Dropdown ─────────────────────────────────────────────── */
.dropdown{position:relative}
.dropdown-menu{
  position:absolute;right:0;top:calc(100% + 8px);
  background:var(--bg-card);border:1px solid var(--border-secondary);
  border-radius:10px;min-width:160px;overflow:hidden;z-index:200;
  opacity:0;pointer-events:none;transform:translateY(-6px);transition:all .2s;
  box-shadow:var(--shadow-lg);
}
.dropdown-menu.show{opacity:1;pointer-events:all;transform:translateY(0)}
.dropdown-item{display:flex;align-items:center;gap:10px;padding:11px 16px;font-size:14px;color:var(--text-secondary);cursor:pointer;transition:background .15s}
.dropdown-item:hover{background:var(--bg-hover);color:var(--text-primary)}
.dropdown-item.danger{color:var(--danger-color)}
.dropdown-item.danger:hover{background:#1a0808}

/* ── Search area ──────────────────────────────────────────── */
.page-body{margin-top:52px;padding:0 16px}
.search-wrap{max-width:660px;margin:0 auto;padding:36px 0 24px}
.search-label{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-muted);letter-spacing:.18em;text-transform:uppercase;margin-bottom:16px}
.search-box{display:flex;gap:8px;align-items:stretch}
.search-inp{
  flex:1;min-width:0;background:var(--bg-search-input);border:1px solid var(--border-input);
  color:var(--text-primary);padding:0 16px;height:44px;border-radius:8px;
  font-size:15px;font-family:inherit;outline:none;transition:border-color .2s;-webkit-appearance:none;
}
.search-inp:focus{border-color:var(--border-input-focus)}
.search-inp::placeholder{color:var(--text-muted)}
@media(max-width:480px){.search-inp{font-size:16px}}

/* ── Results ──────────────────────────────────────────────── */
.results-wrap{max-width:660px;margin:0 auto;padding:0 0 60px}
.result-count{font-size:11px;color:var(--text-muted);font-family:'JetBrains Mono',monospace;margin-bottom:14px;letter-spacing:.04em}
.result-count em{color:var(--text-dim);font-style:normal}

.proj-card{
  background:var(--card-bg);border:1px solid var(--card-border);
  border-radius:10px;padding:18px;
  cursor:pointer;transition:border-color .2s,background .2s;
  position:relative;overflow:hidden;margin-bottom:10px;-webkit-tap-highlight-color:transparent;
  box-shadow:var(--shadow-card);
}
.proj-card:hover{border-color:var(--card-hover-border);background:var(--card-hover-bg)}
.proj-card:active{background:var(--bg-hover)}
.card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px}
.card-name-row{display:flex;align-items:center;gap:6px;flex:1;min-width:0}
.card-name{font-size:14px;font-weight:500;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.card-version{font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--text-muted);flex-shrink:0;margin-top:2px}
.card-desc{font-size:13px;color:var(--text-dim);line-height:1.65;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.card-foot{display:flex;align-items:center;gap:8px;padding-top:12px;border-top:1px solid var(--card-foot-border)}
.card-avatar{width:18px;height:18px;border-radius:50%;object-fit:cover;border:1px solid var(--border-secondary);flex-shrink:0}
.card-date{margin-left:auto;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--text-muted)}
mark{background:var(--highlight-bg);color:var(--highlight-color);font-weight:600}

/* ── States ───────────────────────────────────────────────── */
.state-box{text-align:center;padding:72px 0}
.state-icon{font-size:36px;opacity:.07;margin-bottom:10px}
.state-text{font-size:13px;color:var(--state-text-color)}
.hidden{display:none!important}

/* ── Spinner ──────────────────────────────────────────────── */
.spinner{width:22px;height:22px;border-radius:50%;border:2px solid var(--border-secondary);border-top-color:var(--text-dim);animation:spin .7s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── Detail Modal ─────────────────────────────────────────── */
.modal-bg{
  position:fixed;inset:0;background:var(--overlay-bg);z-index:100;
  display:flex;align-items:flex-end;justify-content:center;padding:0;
  opacity:0;pointer-events:none;transition:opacity .25s;
}
.modal-bg.show{opacity:1;pointer-events:all}
.modal{
  background:var(--bg-modal);border:1px solid var(--border-secondary);
  border-radius:16px 16px 0 0;
  width:100%;max-width:700px;max-height:92vh;overflow-y:auto;
  transform:translateY(24px);transition:transform .3s cubic-bezier(.32,.72,0,1);
  box-shadow:var(--shadow-lg);
}
.modal-bg.show .modal{transform:translateY(0)}
@media(min-width:640px){
  .modal-bg{align-items:center;padding:20px}
  .modal{border-radius:14px;max-height:88vh}
}
body.locked{overflow:hidden}
.modal-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:18px 20px;border-bottom:1px solid var(--border-primary);
  position:sticky;top:0;background:var(--bg-modal);z-index:1;gap:8px;
}
.modal-title{font-size:15px;font-weight:500;color:var(--text-secondary);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.modal-actions{display:flex;align-items:center;gap:4px;flex-shrink:0}
.modal-icon-btn{
  width:30px;height:30px;border:none;background:transparent;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  border-radius:6px;color:var(--text-muted);transition:color .15s,background .15s;
}
.modal-icon-btn:hover{color:var(--text-primary);background:var(--bg-hover)}
.modal-body{padding:20px}

/* detail rows */
.detail-link-row{display:flex;align-items:center;gap:10px;padding:11px 14px;background:var(--bg-detail-row);border:1px solid var(--border-primary);border-radius:8px}
.detail-link-row a{color:var(--text-secondary);font-size:13px;word-break:break-all;transition:color .15s}
.detail-link-row a:hover{color:var(--text-primary)}

/* ── Badges ───────────────────────────────────────────────── */
.badge-guest{background:var(--badge-guest-bg);color:var(--badge-guest-color);border:1px solid var(--badge-guest-border);display:inline-block;padding:2px 7px;border-radius:99px;font-size:9px;font-family:'JetBrains Mono',monospace;font-weight:500;flex-shrink:0}
.badge-version{font-size:11px;font-family:'JetBrains Mono',monospace;padding:3px 8px;background:var(--badge-version-bg);border:1px solid var(--badge-version-border);border-radius:4px;color:var(--badge-version-color)}
.author-link{color:var(--text-dim);font-size:12px;transition:color .15s}
.author-link:hover{color:var(--text-primary)}

/* ── Toast ────────────────────────────────────────────────── */
#toast{position:fixed;bottom:20px;right:16px;z-index:999;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.toast-item{background:var(--bg-card);border:1px solid var(--border-secondary);color:var(--text-primary);padding:11px 16px;border-radius:8px;font-size:13px;max-width:300px;display:flex;align-items:center;gap:8px;animation:tIn .25s ease forwards;pointer-events:all;box-shadow:var(--shadow-lg)}
.toast-item.out{animation:tOut .25s ease forwards}
.toast-item.success::before{content:'✓';color:var(--success-color);font-weight:700;flex-shrink:0}
.toast-item.error::before{content:'✕';color:var(--danger-color);font-weight:700;flex-shrink:0}
@keyframes tIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}
@keyframes tOut{from{opacity:1}to{opacity:0;transform:translateX(16px)}}

/* ── Footer ───────────────────────────────────────────────── */
.page-footer{max-width:660px;margin:0 auto;padding:8px 0 40px;text-align:center}
.page-footer a{
  font-family:'JetBrains Mono',monospace;
  font-size:11px;
  color:var(--text-muted);
  text-decoration:none;
  letter-spacing:.05em;
  transition:color .2s;
}
.page-footer a:hover{color:var(--text-dim)}
</style>
</head>
<body>

<!-- ══ TOPBAR ═══════════════════════════════════════════════ -->
<header id="topbar">
  <a href="/home.php" class="topbar-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    <span class="topbar-logo">OPEN-FOHOK</span>
  </a>
  <div style="flex:1"></div>
  <?php if ($me): ?>
  <div class="dropdown">
    <button onclick="dd('userMenu')" style="background:none;border:none;cursor:pointer;padding:0;display:flex">
      <img src="<?= htmlspecialchars($me['picture'] ?? 'https://ui-avatars.com/api/?name='.urlencode($me['name']??'U').'&background=222&color=fff') ?>"
           style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid var(--border-secondary)" alt="">
    </button>
    <div id="userMenu" class="dropdown-menu">
      <a href="/profile.php" class="dropdown-item">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        个人中心
      </a>
      <?php if ($isAdmin): ?>
      <a href="/admin/" class="dropdown-item">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/></svg>
        管理后台
      </a>
      <?php endif; ?>
      <a href="/logout.php" class="dropdown-item danger">退出登录</a>
    </div>
  </div>
  <?php else: ?>
  <a href="/login.php" class="btn btn-ghost">登录</a>
  <?php endif; ?>
</header>

<!-- ══ SEARCH BAR ════════════════════════════════════════════ -->
<div class="page-body">
  <div class="search-wrap">
    <p class="search-label">Search Projects</p>
    <div class="search-box">
      <input id="search-inp" class="search-inp" type="search"
             placeholder="项目名称、介绍、开发者..."
             value="<?= htmlspecialchars($preKeyword) ?>"
             autocomplete="off"
             onkeydown="if(event.key==='Enter')triggerSearch()">
      <button id="search-btn" class="btn btn-primary" onclick="triggerSearch()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        搜索
      </button>
    </div>
  </div>

  <!-- ══ RESULTS ═══════════════════════════════════════════════ -->
  <div class="results-wrap">
    <div id="state-idle" class="state-box">
      <div class="state-icon">⌕</div>
      <p class="state-text">输入关键词后按回车或点击搜索</p>
    </div>
    <div id="state-loading" class="state-box hidden">
      <div class="spinner"></div>
    </div>
    <div id="state-empty" class="state-box hidden">
      <div class="state-icon">◎</div>
      <p class="state-text">没有找到相关项目</p>
    </div>
    <div id="state-error" class="state-box hidden">
      <div class="state-icon" style="opacity:.15">!</div>
      <p class="state-text" style="color:var(--danger-color)">驿站繁忙，请稍后再试</p>
    </div>
    <p id="result-count" class="result-count hidden"></p>
    <div id="results-list"></div>
  </div>

  <!-- ══ FOOTER ═══════════════════════════════════════════════ -->
  <footer class="page-footer">
<a href="https://beian.miit.gov.cn" target="_blank" style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;">
    <img src="https://fohok.xin/img/icp.png" alt="ICP备案图标" style="width:20px;height:auto;">
    闽ICP备2026024151号 | <a href="https://beian.mps.gov.cn/#/query/webSearch?code=35058302351237" target="_blank" style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;">
    <img src="https://fohok.xin/img/mps.png" alt="公安备案图标" style="width:20px;height:auto;">闽公网安备35058302351237号
</a>
</a>

</footer>
</div>

<!-- ══ DETAIL MODAL ══════════════════════════════════════════ -->
<div id="detailModal" class="modal-bg" onclick="if(event.target===this)closeDetail()">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-head">
      <h3 id="detail-title" class="modal-title">项目详情</h3>
      <div class="modal-actions">
        <!-- 分享按钮 -->
        <button class="modal-icon-btn" onclick="shareProject()" title="复制链接">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
          </svg>
        </button>
        <!-- 关闭按钮 -->
        <button class="modal-icon-btn" onclick="closeDetail()" aria-label="关闭">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </div>
    <div id="detail-body" class="modal-body"></div>
  </div>
</div>

<div id="toast"></div>

<!-- 极验 v4 SDK -->
<script src="https://static.geetest.com/v4/gt4.js"></script>
<script>
const PRE_KEYWORD   = <?= json_encode($preKeyword) ?>;
const CAPTCHA_ID_JS = '<?= CAPTCHA_ID ?>';

let captchaObj  = null;   // 当前极验实例
let pendingKw   = null;   
let currentProjId = null; // 当前打开的项目 id

// ── 初始化极验实例 ────────────────────────────────────────────

function initCaptcha(afterInitCallback) {
  // 销毁旧实例
  if (captchaObj) {
    try { captchaObj.destroy(); } catch(e) {}
    captchaObj = null;
  }

  window.initGeetest4({
    captchaId: CAPTCHA_ID_JS,
    product  : 'bind',
  }, function(obj) {
    captchaObj = obj;

    obj.onSuccess(function() {
      const validate = obj.getValidate();
      if (pendingKw) {
        const kw = pendingKw;
        pendingKw = null;
        doSearch(kw, validate);
      }
    });

    obj.onError(function() {
      pendingKw = null;
      setBtnLoading(false);
      setState('idle');
      showToast('验证服务异常，请稍后重试', 'error');
    });

    obj.onClose(function() {
      // 用户关闭了极验弹出的二次核验窗口
      pendingKw = null;
      setBtnLoading(false);
      setState('idle');
    });

    if (typeof afterInitCallback === 'function') afterInitCallback();
  });
}

// ── 触发搜索 ──────────────────────────────────────────────────
function triggerSearch() {
  const kw = document.getElementById('search-inp').value.trim();
  if (!kw) { showToast('请输入搜索关键词', 'error'); return; }
  if (!captchaObj) { showToast('验证组件尚未就绪，请稍候', 'error'); return; }

  pendingKw = kw;
  setBtnLoading(true);
  setState('loading');

  // 触发极验后台无感验证
  captchaObj.showCaptcha();
}

// ── 发起搜索请求──────────────────────────
async function doSearch(keyword, validate) {
  const url = new URL(window.location.href);
  url.searchParams.set('s', keyword);
  window.history.replaceState({}, '', url.toString());

  try {
    const fd = new FormData();
    fd.append('action',  'search');
    fd.append('keyword', keyword);
    for (const [k, v] of Object.entries(validate)) fd.append(k, v);

    const res  = await fetch('/api/project.php', { method:'POST', body:fd });
    const data = await res.json();

    if (!data.ok) { setState('error'); }
    else if (!data.projects?.length) { setState('empty'); }
    else {
      renderResults(data.projects, keyword);
      setState('results');
      const cnt = document.getElementById('result-count');
      cnt.innerHTML = `找到 <em>${data.projects.length}</em> 个与 <em>${esc(keyword)}</em> 相关的项目`;
      cnt.classList.remove('hidden');
    }
  } catch(e) {
    setState('error');
  } finally {
    setBtnLoading(false);
    // 重新初始化极验
    initCaptcha();
  }
}

// ── 渲染结果卡片 ─────────────────────────────────────────────
function renderResults(projects, keyword) {
  const list = document.getElementById('results-list');
  list.innerHTML = '';
  projects.forEach(p => {
    const card = document.createElement('div');
    card.className = 'proj-card';
    card.onclick = () => openDetail(p.id);
    const guestTag   = p.is_guest ? '<span class="badge-guest">游客</span>' : '';
    const authorHtml = p.is_guest
      ? `<span style="font-size:12px;color:var(--text-muted)">${esc(p.author_name||'匿名')}</span>`
      : `<a href="/user.php?id=${p.user_id}" class="author-link" onclick="event.stopPropagation()">${esc(p.author_name||'匿名')}</a>`;
    card.innerHTML = `
      <div class="card-top">
        <div class="card-name-row">
          <span class="card-name">${highlight(p.name, keyword)}</span>
          ${guestTag}
        </div>
        <span class="card-version">${esc(p.version)}</span>
      </div>
      <p class="card-desc">${highlight(p.description, keyword)}</p>
      <div class="card-foot">
        <img class="card-avatar"
             src="${esc(p.author_pic||'')}"
             onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(p.author_name||'U')}&background=1a1a1a&color=666&size=36'"
             alt="">
        ${authorHtml}
        <span class="card-date">${formatDate(p.created_at)}</span>
      </div>`;
    list.appendChild(card);
  });
}

// ── 关键词高亮 ────────────────────────────────────────────────
function highlight(text, kw) {
  if (!kw || !text) return esc(text||'');
  return esc(text).replace(
    new RegExp(esc(kw).replace(/[.*+?^${}()|[\]\\]/g,'\\$&'), 'gi'),
    m => `<mark>${m}</mark>`
  );
}

// ── 详情弹窗 ─────────────────────────────────────────────────
function closeDetail() {
  document.getElementById('detailModal').classList.remove('show');
  document.body.classList.remove('locked');
  currentProjId = null;
}

async function openDetail(id) {
  currentProjId = id;
  document.getElementById('detail-title').textContent = '项目详情';
  document.getElementById('detail-body').innerHTML =
    '<div style="text-align:center;padding:48px 0"><div class="spinner"></div></div>';
  document.getElementById('detailModal').classList.add('show');
  document.body.classList.add('locked');

  try {
    const res  = await fetch(`/api/project.php?action=detail&id=${id}`);
    const data = await res.json();
    if (!data.ok) { document.getElementById('detail-body').innerHTML = errHtml('加载失败'); return; }

    const p = data.project;
    document.getElementById('detail-title').textContent = p.name;

    const contacts = [];
    if (p.contact_email)  contacts.push(`<a href="mailto:${esc(p.contact_email)}" style="color:var(--link-color)">${esc(p.contact_email)}</a>`);
    if (p.contact_phone)  contacts.push(esc(p.contact_phone));
    if (p.contact_qq)     contacts.push('QQ&nbsp;' + esc(p.contact_qq));
    if (p.contact_wechat) contacts.push('微信&nbsp;' + esc(p.contact_wechat));

    const authorLink = p.is_guest
      ? `<span style="font-size:12px;color:var(--text-muted)">${esc(p.author_name||'匿名游客')}</span>`
      : `<a href="/user.php?id=${p.user_id}" class="author-link">${esc(p.author_name||'匿名')}</a>`;

    document.getElementById('detail-body').innerHTML = `
      <div style="display:flex;flex-direction:column;gap:14px">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span class="badge-version">${esc(p.version)}</span>
          ${p.release_date ? `<span style="font-size:12px;color:var(--text-muted)">${esc(p.release_date)}</span>` : ''}
          ${p.is_guest ? '<span class="badge-guest">游客提交</span>' : ''}
          <span style="font-size:11px;color:var(--text-muted);margin-left:auto">${p.view_count||0} 次浏览</span>
        </div>
        <p style="font-size:13px;color:var(--text-dim);line-height:1.8;white-space:pre-wrap">${esc(p.description)}</p>
        <div style="display:flex;flex-direction:column;gap:8px">
          <div class="detail-link-row">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;color:var(--text-muted)"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            <a href="${esc(p.project_url)}" target="_blank" rel="noopener">${esc(p.project_url)}</a>
          </div>
          ${p.developer_website ? `
          <div class="detail-link-row">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;color:var(--text-muted)"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <a href="${esc(p.developer_website)}" target="_blank" rel="noopener" style="color:var(--text-dim)">${esc(p.developer_website)}</a>
          </div>` : ''}
          ${contacts.length ? `<div style="padding:11px 14px;background:var(--bg-detail-row);border:1px solid var(--border-primary);border-radius:8px;font-size:12px;color:var(--text-muted);display:flex;flex-wrap:wrap;gap:8px">${contacts.join('<span style="color:var(--text-muted)">·</span>')}</div>` : ''}
        </div>
        <div style="display:flex;align-items:center;gap:8px;padding-top:12px;border-top:1px solid var(--border-primary)">
          <img src="${esc(p.author_pic||'')}"
               onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(p.author_name||'U')}&background=1a1a1a&color=666&size=56'"
               style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:1px solid var(--border-secondary)" alt="">
          ${authorLink}
          <span style="font-size:12px;color:var(--text-muted);margin-left:4px">· ${formatDate(p.created_at)}</span>
        </div>
      </div>`;
  } catch(e) {
    document.getElementById('detail-body').innerHTML = errHtml('加载失败');
  }
}

// ── 分享按钮 ─────────────────────────────────────────────────
function shareProject() {
  if (!currentProjId) return;
  const link = `https://fohok.xin/prodeta.php?id=${currentProjId}`;
  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(link)
      .then(() => showToast('链接已复制', 'success'))
      .catch(() => fallbackCopy(link));
  } else {
    fallbackCopy(link);
  }
}
function fallbackCopy(text) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
  document.body.appendChild(ta);
  ta.focus(); ta.select();
  try {
    document.execCommand('copy');
    showToast('链接已复制', 'success');
  } catch(e) {
    showToast('复制失败，请手动复制', 'error');
  }
  document.body.removeChild(ta);
}

document.addEventListener('keydown', e => { if (e.key==='Escape') closeDetail(); });

// ── State / UI helpers ────────────────────────────────────────
function setState(s) {
  ['idle','loading','empty','error'].forEach(x =>
    document.getElementById('state-'+x).classList.toggle('hidden', x!==s)
  );
  if (s !== 'results') {
    document.getElementById('results-list').innerHTML = '';
    document.getElementById('result-count').classList.add('hidden');
  }
}
function setBtnLoading(on) {
  const btn = document.getElementById('search-btn');
  btn.disabled = on;
  btn.innerHTML = on
    ? '<div class="spinner" style="width:16px;height:16px;border-width:2px;border-top-color:#555"></div>'
    : '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>搜索';
}
function errHtml(msg) { return `<p style="text-align:center;color:var(--text-muted);font-size:13px;padding:48px 0">${msg}</p>`; }
function dd(id) { document.getElementById(id).classList.toggle('show'); }
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
});
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatDate(s) {
  if (!s) return '';
  const d = new Date(s);
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function showToast(msg, type='success') {
  const el = document.createElement('div');
  el.className = `toast-item ${type}`;
  el.textContent = msg;
  document.getElementById('toast').appendChild(el);
  setTimeout(() => { el.classList.add('out'); setTimeout(()=>el.remove(), 260); }, 3200);
}

// ── 初始化极验─────────────
initCaptcha(function() {
  if (PRE_KEYWORD) triggerSearch();
});
</script>
</body>
</html>