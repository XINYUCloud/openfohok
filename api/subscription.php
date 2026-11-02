<?php
// ============================================================
// 开发者订阅 API
// action: toggle | check | subscribers | subscriptions
// ============================================================
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['ok' => false, 'msg' => '请先登录']);
    exit;
}
$userId = (int)($user['user_id'] ?? $user['id']);
$db = getDB();

// 订阅状态变更只接受 POST + 同源
if ($action === 'toggle') {
    securityRequirePost();

    $targetId = (int)($_POST['target_user_id'] ?? 0);
    if (!$targetId || $targetId === $userId) {
        echo json_encode(['ok' => false, 'msg' => '参数错误']);
        exit;
    }
    if (!securityRateLimit('subscription_toggle', 60, 3600, 'u:' . $userId)) {
        echo json_encode(['ok' => false, 'msg' => '操作过于频繁，请稍后再试']);
        exit;
    }

    // 检查目标用户是否存在
    $check = $db->prepare('SELECT id, name FROM users WHERE id = ?');
    $check->execute([$targetId]);
    $targetUser = $check->fetch();
    if (!$targetUser) {
        echo json_encode(['ok' => false, 'msg' => '用户不存在']);
        exit;
    }

    // 检查是否已订阅
    $subCheck = $db->prepare(
        'SELECT id FROM user_subscriptions WHERE subscriber_id = ? AND target_user_id = ?'
    );
    $subCheck->execute([$userId, $targetId]);
    $existing = $subCheck->fetch();

    if ($existing) {
        // 取消订阅
        $db->prepare('DELETE FROM user_subscriptions WHERE id = ?')->execute([$existing['id']]);
        echo json_encode(['ok' => true, 'subscribed' => false, 'msg' => '已取消订阅']);
    } else {
        // 订阅
        $db->prepare(
            'INSERT INTO user_subscriptions (subscriber_id, target_user_id) VALUES (?, ?)'
        )->execute([$userId, $targetId]);

        // 发送通知给被订阅者
        createNewSubscriberNotification($targetId, $userId, $user['name'] ?? '用户');

        echo json_encode(['ok' => true, 'subscribed' => true, 'msg' => '订阅成功']);
    }
    exit;
}

// ── 检查订阅状态 ──────────────────────────────────────
if ($action === 'check') {
    $targetId = (int)($_GET['target_user_id'] ?? 0);
    if (!$targetId) {
        echo json_encode(['ok' => false, 'msg' => '参数错误']);
        exit;
    }

    $subCheck = $db->prepare(
        'SELECT id FROM user_subscriptions WHERE subscriber_id = ? AND target_user_id = ?'
    );
    $subCheck->execute([$userId, $targetId]);
    $subscribed = (bool)$subCheck->fetch();

    echo json_encode(['ok' => true, 'subscribed' => $subscribed]);
    exit;
}

// ── 获取订阅者列表──────────────────────
if ($action === 'subscribers') {
    $stmt = $db->prepare(
        'SELECT us.id, u.id as user_id, u.name, u.picture, us.created_at
         FROM user_subscriptions us
         JOIN users u ON u.id = us.subscriber_id
         WHERE us.target_user_id = ?
         ORDER BY us.created_at DESC'
    );
    $stmt->execute([$userId]);
    $subscribers = $stmt->fetchAll();

    $countStmt = $db->prepare('SELECT COUNT(*) FROM user_subscriptions WHERE target_user_id = ?');
    $countStmt->execute([$userId]);
    $count = (int)$countStmt->fetchColumn();

    echo json_encode(['ok' => true, 'subscribers' => $subscribers, 'count' => $count]);
    exit;
}

// ── 获取我的订阅列表────────────────────
if ($action === 'subscriptions') {
    $stmt = $db->prepare(
        'SELECT us.id, u.id as user_id, u.name, u.picture, us.created_at
         FROM user_subscriptions us
         JOIN users u ON u.id = us.target_user_id
         WHERE us.subscriber_id = ?
         ORDER BY us.created_at DESC'
    );
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll();

    $countStmt = $db->prepare('SELECT COUNT(*) FROM user_subscriptions WHERE subscriber_id = ?');
    $countStmt->execute([$userId]);
    $count = (int)$countStmt->fetchColumn();

    echo json_encode(['ok' => true, 'subscriptions' => $subscriptions, 'count' => $count]);
    exit;
}

// ── 获取订阅计数──────────────────
if ($action === 'counts') {
    $targetId = (int)($_GET['target_user_id'] ?? 0);
    if (!$targetId) {
        echo json_encode(['ok' => false, 'msg' => '参数错误']);
        exit;
    }

    $subCount = $db->prepare('SELECT COUNT(*) FROM user_subscriptions WHERE target_user_id = ?');
    $subCount->execute([$targetId]);
    $subscriberCount = (int)$subCount->fetchColumn();

    $subscribed = false;
    if ($userId) {
        $check = $db->prepare('SELECT id FROM user_subscriptions WHERE subscriber_id = ? AND target_user_id = ?');
        $check->execute([$userId, $targetId]);
        $subscribed = (bool)$check->fetch();
    }

    echo json_encode(['ok' => true, 'subscriber_count' => $subscriberCount, 'subscribed' => $subscribed]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => '未知操作']);