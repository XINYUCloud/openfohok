<?php
// ============================================================
// 公告查询接口
// GET /api/announcement.php?id=1
// ============================================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理预检请求（OPTIONS）
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}


require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['ok' => false, 'msg' => '缺少 id 参数']);
    exit;
}

$db = getDB();

$stmt = $db->prepare('SELECT id, title, content, severity, created_at FROM announcements WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['ok' => false, 'msg' => '公告不存在']);
    exit;
}

echo json_encode([
    'ok'           => true,
    'id'           => (int)$row['id'],
    'title'        => $row['title'],
    'content'      => $row['content'],
    'severity'     => $row['severity'],
    'created_at'   => $row['created_at'],
]);
