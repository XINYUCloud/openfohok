<?php
/**
 * 计划任务入口 — 每 2 分钟由 cron 触发
 * 用法: 星号/2 * * * * curl -sS http://fohok.xin/api/plan.php > /dev/null 2>&1
 */
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

// 该入口由 cron 定时调用；限流防止被外部高频触发做无效扫描
if (!securityRateLimit('plan_run', 6, 60)) {
    http_response_code(429);
    echo 'too many requests';
    exit;
}

$db = getDB();
$now = time();
$count = 0;

// ── 1. 自动解除过期限制 ──────────────────────────────────
// 查询所有同时过期的登录/发布限制（避免重复处理同一用户）
$stmt = $db->query(
    "SELECT id, name, email, login_restricted_until, publish_restricted_until, restrict_reason
     FROM users
     WHERE (login_restricted_until IS NOT NULL AND login_restricted_until <= NOW())
        OR (publish_restricted_until IS NOT NULL AND publish_restricted_until <= NOW())"
);

while ($row = $stmt->fetch()) {
    $userId = (int)$row['id'];
    $userName = $row['name'] ?: '用户';
    $userEmail = $row['email'];

    // 检查登录限制
    if ($row['login_restricted_until'] && strtotime($row['login_restricted_until']) <= $now) {
        $db->prepare('UPDATE users SET login_restricted_until = NULL WHERE id = ?')->execute([$userId]);
        $db->prepare("UPDATE user_restrictions SET is_active = 0 WHERE user_id = ? AND restrict_type = 'login' AND is_active = 1")->execute([$userId]);
        createNotification($userId, 'restricted', '你的账号登录限制已解除', '你的账号登录限制已解除，感谢你的配合。');
        if ($userEmail) {
            @sendSimpleEmail($userEmail, '【文谶驿站】你的账号登录限制已解除',
                "你好，{$userName}！\n\n你的账号登录限制已解除，感谢你的配合，如有疑问请联系管理员。\n\n此邮件由系统自动发送，请勿回复。");
        }
        $count++;
    }

    // 检查发布限制
    if ($row['publish_restricted_until'] && strtotime($row['publish_restricted_until']) <= $now) {
        $db->prepare('UPDATE users SET publish_restricted_until = NULL WHERE id = ?')->execute([$userId]);
        $db->prepare("UPDATE user_restrictions SET is_active = 0 WHERE user_id = ? AND restrict_type = 'publish' AND is_active = 1")->execute([$userId]);
        createNotification($userId, 'restricted', '你的账号发布限制已解除', '你的账号发布限制已解除，感谢你的配合。');
        if ($userEmail) {
            @sendSimpleEmail($userEmail, '【文谶驿站】你的账号发布限制已解除',
                "你好，{$userName}！\n\n你的账号发布限制已解除，\n感谢你的配合，如有疑问请联系管理员。\n\n此邮件由系统自动发送，请勿回复。");
        }
        $count++;
    }

    // 两项限制都解除后清除 restrict_reason
    $actStmt = $db->prepare('SELECT login_restricted_until, publish_restricted_until FROM users WHERE id = ?');
    $actStmt->execute([$userId]);
    $active = $actStmt->fetch();
    if (!$active['login_restricted_until'] && !$active['publish_restricted_until']) {
        $db->prepare('UPDATE users SET restrict_reason = NULL WHERE id = ?')->execute([$userId]);
    }
}

// ── 2. 清理过期会话 ──────────────────────────────────────
$db->exec("DELETE FROM sessions WHERE expires_at < NOW()");

// ── 3. 清理过期验证令牌 ──────────────────────────────────
$db->exec("DELETE FROM email_login_tokens WHERE expires_at < NOW()");
$db->exec("DELETE FROM email_register_tokens WHERE expires_at < NOW()");

// 输出状态（可选，用于 cron 日志）
if ($count > 0) {
    echo date('Y-m-d H:i:s') . " - 解除 {$count} 项限制\n";
}
echo "OK\n";