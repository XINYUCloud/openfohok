<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['ok' => false, 'msg' => '会话异常，请重新登录！']);
    exit;
}

$db     = getDB();
$userId = $user['user_id'] ?? $user['id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 会改变状态的操作用 require POST + 同源
if (in_array($action, ['revoke', 'revoke_others'], true)) {
    securityRequirePost();
}

// ── 当前账号所有会话 ───────────────────────────────────
if ($action === 'list') {
    $currentToken = $_COOKIE['op_token'] ?? ($_SESSION['op_token'] ?? '');

    $stmt = $db->prepare(
        'SELECT id, ip_address, user_agent, created_at, expires_at
         FROM sessions
         WHERE user_id = ? AND expires_at > NOW()
         ORDER BY created_at DESC'
    );
    $stmt->execute([$userId]);
    $sessions = $stmt->fetchAll();

    $result = array_map(function ($s) use ($currentToken) {
        return [
            'id'         => $s['id'],
            'is_current' => hash_equals($s['id'], $currentToken),
            'ip'         => $s['ip_address'] ?? '未知',
            'user_agent' => $s['user_agent'] ?? '',
            'created_at' => $s['created_at'],
            'expires_at' => $s['expires_at'],
        ];
    }, $sessions);

    // 当前会话排最前
    usort($result, fn($a, $b) => $b['is_current'] - $a['is_current']);

    echo json_encode(['ok' => true, 'sessions' => $result]);
    exit;
}

// ── 退出指定会话 ───────────────────────────────────────────
if ($action === 'revoke') {
    $targetId     = trim($_POST['session_id'] ?? '');
    $currentToken = $_COOKIE['op_token'] ?? ($_SESSION['op_token'] ?? '');

    if (!$targetId) {
        echo json_encode(['ok' => false, 'msg' => '参数错误']);
        exit;
    }
    if (hash_equals($targetId, $currentToken)) {
        echo json_encode(['ok' => false, 'msg' => '不能退出当前会话']);
        exit;
    }

    // 确认该会话属于当前用户
    $check = $db->prepare('SELECT id FROM sessions WHERE id = ? AND user_id = ?');
    $check->execute([$targetId, $userId]);
    if (!$check->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '会话不存在，请检查后重试！']);
        exit;
    }

    $db->prepare('DELETE FROM sessions WHERE id = ? AND user_id = ?')
       ->execute([$targetId, $userId]);

    echo json_encode(['ok' => true]);
    exit;
}

// ── 退出除当前外所有会话 ───────────────────────────────────
if ($action === 'revoke_others') {
    $currentToken = $_COOKIE['op_token'] ?? ($_SESSION['op_token'] ?? '');

    $db->prepare('DELETE FROM sessions WHERE user_id = ? AND id != ?')
       ->execute([$userId, $currentToken]);

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => '未知操作']);