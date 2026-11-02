<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$user       = getCurrentUser();
$isLoggedIn = $user !== null;
$action     = $_POST['action'] ?? $_GET['action'] ?? '';
$db         = getDB();

// 会改变状态的操作用 require POST + 同源
if (in_array($action, ['mark_read', 'mark_all_read', 'delete_all', 'forward_toggle'], true)) {
    securityRequirePost();
}

// 需要登录的操作
if (in_array($action, ['list', 'detail', 'mark_read', 'mark_all_read', 'delete_all', 'unread_count', 'forward_toggle', 'wenchan_list'], true)) {
    if (!$isLoggedIn) {
        echo json_encode(['ok' => false, 'msg' => '请先登录']);
        exit;
    }
}

$userId = $isLoggedIn ? (int)($user['user_id'] ?? $user['id']) : 0;

// 清理过期通知
$db->exec('DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');

// ── 获取未读数量 ──────────────────────────────────────────
if ($action === 'unread_count') {
    $countStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $countStmt->execute([$userId]);
    $count = (int)$countStmt->fetchColumn();
    echo json_encode(['ok' => true, 'count' => $count]);
    exit;
}

// ── 通知列表（project_liked 置顶）────────────
if ($action === 'list') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // project_liked 通知：联表获取点赞人信息和项目名
    $stmt = $db->prepare(
        "SELECT n.*, u.name AS liker_name, u.picture AS liker_pic,
                p.name AS project_name
         FROM notifications n
         LEFT JOIN users u ON n.liker_id = u.id
         LEFT JOIN projects p ON n.related_id = p.id
         WHERE n.user_id = ?
         ORDER BY
           CASE WHEN n.type = 'project_liked' THEN 0 ELSE 1 END,
           n.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$userId, $limit, $offset]);
    $notifications = $stmt->fetchAll();

    $totalStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
    $totalStmt->execute([$userId]);
    $total = (int)$totalStmt->fetchColumn();

    foreach ($notifications as &$n) {
        $n['is_read'] = (bool)$n['is_read'];
    }
    unset($n);

    echo json_encode(['ok' => true, 'notifications' => $notifications, 'total' => $total, 'page' => $page]);
    exit;
}

// ── 通知详情（同时标记为已读）──────────────────────────────
if ($action === 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare(
        "SELECT n.*, u.name AS liker_name, u.picture AS liker_pic,
                p.name AS project_name
         FROM notifications n
         LEFT JOIN users u ON n.liker_id = u.id
         LEFT JOIN projects p ON n.related_id = p.id
         WHERE n.id = ? AND n.user_id = ?"
    );
    $stmt->execute([$id, $userId]);
    $notification = $stmt->fetch();
    if (!$notification) {
        echo json_encode(['ok' => false, 'msg' => '通知不存在']);
        exit;
    }
    // 标记为已读
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
       ->execute([$id, $userId]);
    $notification['is_read'] = true;

    echo json_encode(['ok' => true, 'notification' => $notification]);
    exit;
}

// ── 标记已读 ──────────────────────────────────────────────
if ($action === 'mark_read') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
       ->execute([$id, $userId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── 一键全部已读 ──────────────────────────────────────────
if ($action === 'mark_all_read') {
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
       ->execute([$userId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── 清除所有通知 ──────────────────────────────────────────
if ($action === 'delete_all') {
    $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── 读取文谶账号中心站内信（notification:read）────────────
if ($action === 'wenchan_list') {
    header('Cache-Control: no-store');

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $force = !empty($_GET['force']);

    if (!function_exists('wenchanReadEnabled') || !wenchanReadEnabled()) {
        echo json_encode([
            'ok'             => false,
            'config_missing' => true,
            'need_reauth'    => false,
            'msg'            => '站点尚未启用文谶账号通知读取',
        ]);
        exit;
    }

    // 本地短缓存：账号中心按 IP + 应用限流 60 次/分钟，避免切标签或误触刷新打满
    $cache = $_SESSION['wenchan_notif_cache'] ?? null;
    $maxAge = $force ? 3 : 20;
    if (is_array($cache)
        && (int)($cache['page'] ?? 0) === $page
        && time() - (int)($cache['at'] ?? 0) < $maxAge) {
        $cached = (array)($cache['data'] ?? []);
        $cached['cached'] = true;
        echo json_encode($cached);
        exit;
    }

    try {
        $result = wenchanFetchNotifications((int)$userId, $page, 10);
    } catch (\Throwable $e) {
        error_log('[wenchanNotifications] ' . $e->getMessage());
        $result = wenchanNotificationResult(false, ['msg' => '读取失败，请稍后重试']);
    }
    $result['cached'] = false;
    $_SESSION['wenchan_notif_cache'] = ['at' => time(), 'page' => $page, 'data' => $result];

    echo json_encode($result);
    exit;
}

// ── 站内信转发开关（通知通道 → 文谶账号中心）─────────────
if ($action === 'forward_toggle') {
    $enabled = !empty($_POST['enabled']) ? 1 : 0;
    ensureForwardSchema();
    setInboxForwardEnabled($userId, (bool)$enabled);
    echo json_encode(['ok' => true, 'enabled' => (bool)$enabled]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => '未知操作']);