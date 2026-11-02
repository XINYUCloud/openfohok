<?php
/**
 * 项目嵌入式卡片 - 可通过 iframe 嵌入第三方网站
 * 用法: <iframe src="https://fohok.xin/embed.php?id=1" width="400" height="160" frameborder="0"></iframe>
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$projectId = (int)($_GET['id'] ?? 0);
if (!$projectId) { http_response_code(404); echo '项目不存在'; exit; }

$stmt = $db->prepare(
    'SELECT p.id, p.name, p.version, p.description, p.project_url, p.tech_stack,
            p.release_date, p.created_at,
            u.name AS author_name
     FROM projects p JOIN users u ON p.user_id = u.id
     WHERE p.id = ? AND p.status = \'approved\' AND p.is_hidden = 0'
);
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) { http_response_code(404); echo '项目不存在或已下架'; exit; }

$techList = [];
if (!empty($project['tech_stack'])) {
    $techList = array_filter(array_map('trim', explode(',', $project['tech_stack'])));
}

$detailUrl = APP_URL . '/prodeta.php?id=' . (int)$project['id'];
$desc = mb_substr($project['description'] ?? '', 0, 100, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= htmlspecialchars($project['name']) ?></title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Noto Sans SC',sans-serif;background:transparent}
  .card{
    display:flex;flex-direction:column;gap:6px;padding:14px;border-radius:10px;
    background:#fff;border:1px solid #e5e7eb;max-width:480px;
    text-decoration:none;color:inherit;transition:box-shadow .2s,transform .2s;
  }
  .card:hover{box-shadow:0 4px 20px rgba(0,0,0,.08);transform:translateY(-1px)}
  .card-header{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .card-title{font-size:14px;font-weight:600;color:#111}
  .card-ver{font-size:11px;font-family:'JetBrains Mono',monospace;background:#f3f4f6;color:#6b7280;padding:1px 6px;border-radius:4px}
  .card-author{font-size:11px;color:#9ca3af}
  .card-desc{font-size:12px;color:#6b7280;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .card-tech{display:flex;flex-wrap:wrap;gap:3px;margin-top:2px}
  .card-tech span{font-size:9px;font-family:'JetBrains Mono',monospace;background:#eff6ff;color:#2563eb;padding:1px 5px;border-radius:3px;border:1px solid #bfdbfe}
  .card-footer{display:flex;align-items:center;gap:6px;margin-top:auto;padding-top:4px}
  .card-link{font-size:11px;color:#2563eb;text-decoration:none;font-weight:500}
  .card-brand{font-size:9px;color:#d1d5db;margin-left:auto;font-family:'JetBrains Mono',monospace}

  @media(prefers-color-scheme:dark){
    .card{background:#1a1a1a;border-color:#2a2a2a}
    .card:hover{box-shadow:0 4px 20px rgba(0,0,0,.3)}
    .card-title{color:#f3f4f6}
    .card-ver{background:#2a2a2a;color:#9ca3af}
    .card-author{color:#6b7280}
    .card-desc{color:#9ca3af}
    .card-tech span{background:#0c2240;color:#60a5fa;border-color:#1e3a5f}
    .card-link{color:#60a5fa}
    .card-brand{color:#374151}
  }
</style>
</head>
<body>
<a class="card" href="<?= htmlspecialchars($detailUrl) ?>" target="_blank" rel="noopener">
  <div class="card-header">
    <span class="card-title"><?= htmlspecialchars($project['name']) ?></span>
    <span class="card-ver"><?= htmlspecialchars($project['version']) ?></span>
    <span class="card-author"><?= htmlspecialchars($project['author_name']) ?></span>
  </div>
  <?php if ($desc): ?>
  <div class="card-desc"><?= htmlspecialchars($desc) ?></div>
  <?php endif; ?>
  <?php if ($techList): ?>
  <div class="card-tech">
    <?php foreach (array_slice($techList, 0, 5) as $tech): ?>
    <span><?= htmlspecialchars($tech) ?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="card-footer">
    <span class="card-link">查看项目 →</span>
    <span class="card-brand">fohok.xin</span>
  </div>
</a>
</body>
</html>