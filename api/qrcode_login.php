<?php
// ============================================================
// 扫码登录 API
//   generate : 生成二维码会话（仅登录页所在浏览器，绑定 PHP 会话）
//   poll     : 轮询二维码状态（必须与生成端同一浏览器会话）
//   scan     : 手机端扫码，标记为已扫描
//   confirm  : 手机端确认登录
//   cancel   : 手机端取消登录
// 所有操作仅接受 POST；浏览器请求需同源，App 请求需携带 Bearer Token。
// ============================================================

require_once __DIR__ . '/../bootstrap.php';

const QR_SESSION_TTL = 120;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * 补齐扫码会话表的绑定列（幂等）
 */
function qrEnsureSchema(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'qrcode_sessions' AND COLUMN_NAME = 'session_hash'"
        );
        $stmt->execute();
        if ((int)$stmt->fetchColumn() === 0) {
            $db->exec('ALTER TABLE qrcode_sessions ADD COLUMN session_hash CHAR(64) NULL DEFAULT NULL');
        }
    } catch (\Throwable) {}
}

/**
 * 请求携带的 App 令牌（Bearer 或表单 app_token）
 */
function qrBearerToken(): string {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if ($auth !== '' && preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
        return $m[1];
    }
    $token = $_POST['app_token'] ?? '';
    return is_string($token) ? trim($token) : '';
}

/**
 * 扫码端用户：网页 Cookie 会话或 App Bearer 令牌
 */
function getQRCodeUser(): ?array {
    $user = getCurrentUser();
    if ($user) return $user;

    $token = qrBearerToken();
    if ($token === '' || strlen($token) < 16 || strlen($token) > 200) {
        return null;
    }

    $db = getDB();

    try {
        $stmt = $db->prepare('SELECT id, name, email, picture FROM users WHERE api_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) return $user;
    } catch (\Throwable) {}

    try {
        $stmt = $db->prepare(
            'SELECT u.id, u.name, u.email, u.picture
             FROM users u
             JOIN app_tokens t ON u.id = t.user_id
             WHERE t.token = ? AND (t.expires_at IS NULL OR t.expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) return $user;
    } catch (\Throwable) {}

    try {
        $stmt = $db->prepare(
            'SELECT u.id, u.name, u.email, u.picture
             FROM users u
             JOIN sessions s ON u.id = s.user_id
             WHERE s.id = ? AND s.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) return $user;
    } catch (\Throwable) {}

    return null;
}

/**
 * 加载扫码会话，校验有效期；过期时顺手标记
 */
function qrLoadSession(PDO $db, string $sessionId): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM qrcode_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch();
    if (!$row) return null;

    if (strtotime((string)$row['expires_at']) < time()) {
        try {
            $db->prepare('UPDATE qrcode_sessions SET status = "expired" WHERE id = ?')->execute([$sessionId]);
        } catch (\Throwable) {}
        return null;
    }
    return $row;
}

function qrUserPayload(?array $user): ?array {
    if (!$user) return null;
    return [
        'id'      => (int)$user['id'],
        'name'    => (string)($user['name'] ?? '用户'),
        'picture' => (string)($user['picture'] ?? ''),
    ];
}

// ── 仅允许 POST ──────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    jsonResponse(false, '请使用 POST 请求');
}

$action = trim((string)($_POST['action'] ?? ''));
if ($action === '') {
    jsonResponse(false, '缺少 action 参数');
}

startSecureSession();
qrEnsureSchema();
$db = getDB();

// 概率清理历史会话
if (mt_rand(1, 20) === 1) {
    try {
        $db->exec("DELETE FROM qrcode_sessions WHERE expires_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    } catch (\Throwable) {}
}

// ── 生成二维码会话 ───────────────────────────────────────────
if ($action === 'generate') {
    securityRequireSameOrigin();
    if (!securityRateLimit('qr_generate', 12, 60)) {
        jsonResponse(false, '操作过于频繁，请稍后再试');
    }

    $sessionId = bin2hex(random_bytes(16));
    $expiry    = date('Y-m-d H:i:s', time() + QR_SESSION_TTL);

    try {
        $db->prepare(
            'INSERT INTO qrcode_sessions (id, status, ip_address, user_agent, session_hash, expires_at)
             VALUES (?, "pending", ?, ?, ?, ?)'
        )->execute([
            $sessionId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            hash('sha256', session_id()),
            $expiry,
        ]);
    } catch (\Throwable) {
        jsonResponse(false, '生成二维码失败，请稍后重试');
    }

    jsonResponse(true, '', [
        'session_id' => $sessionId,
        'expires_in' => QR_SESSION_TTL,
    ]);
}

// ── 轮询二维码状态（仅生成端浏览器）─────────────────────────
if ($action === 'poll') {
    securityRequireSameOrigin();
    if (!securityRateLimit('qr_poll', 120, 60)) {
        jsonResponse(false, '操作过于频繁', ['status' => 'expired']);
    }

    $sessionId = trim((string)($_POST['session_id'] ?? ''));
    $row = qrLoadSession($db, $sessionId);
    if (!$row) {
        jsonResponse(false, '二维码无效或已过期', ['status' => 'expired']);
    }

    if (!hash_equals((string)($row['session_hash'] ?? ''), hash('sha256', session_id()))) {
        jsonResponse(false, '二维码与当前会话不匹配', ['status' => 'expired']);
    }

    if ($row['status'] === 'confirmed' && $row['user_id']) {
        $userId = (int)$row['user_id'];

        // 原子消费，避免并发轮询重复建会话
        $consume = $db->prepare(
            'UPDATE qrcode_sessions SET status = "expired"
             WHERE id = ? AND status = "confirmed" AND session_hash = ?'
        );
        $consume->execute([$sessionId, hash('sha256', session_id())]);
        if ($consume->rowCount() !== 1) {
            jsonResponse(false, '二维码无效或已过期', ['status' => 'expired']);
        }

        try {
            createSession($userId);
        } catch (\RuntimeException $e) {
            jsonResponse(false, $e->getMessage());
        }

        $ustmt = $db->prepare('SELECT id, name, picture FROM users WHERE id = ? LIMIT 1');
        $ustmt->execute([$userId]);

        jsonResponse(true, '', [
            'status' => 'confirmed',
            'user'   => qrUserPayload($ustmt->fetch() ?: null),
        ]);
    }

    if ($row['status'] === 'scanned' && $row['scan_user_id']) {
        $ustmt = $db->prepare('SELECT id, name, picture FROM users WHERE id = ? LIMIT 1');
        $ustmt->execute([(int)$row['scan_user_id']]);

        jsonResponse(false, '等待确认', [
            'status' => 'scanned',
            'user'   => qrUserPayload($ustmt->fetch() ?: null),
        ]);
    }

    if ($row['status'] === 'cancelled') {
        jsonResponse(false, '登录已取消', ['status' => 'expired']);
    }

    jsonResponse(false, '等待扫码', ['status' => 'pending']);
}

// ── 手机端扫码 / 确认 / 取消 ─────────────────────────────────
if (!in_array($action, ['scan', 'confirm', 'cancel'], true)) {
    jsonResponse(false, '未知操作');
}

// 浏览器操作需同源；携带 Bearer 的 App 请求凭令牌身份，不受 Origin 限制
if (qrBearerToken() === '') {
    securityRequireSameOrigin();
}

$user = getQRCodeUser();
if (!$user) {
    jsonResponse(false, '请先登录后再操作');
}
$userId = (int)$user['id'];

if (!securityRateLimit('qr_' . $action, $action === 'scan' ? 30 : 20, 60)) {
    jsonResponse(false, '操作过于频繁，请稍后再试');
}

$sessionId = trim((string)($_POST['session_id'] ?? ''));
$row = qrLoadSession($db, $sessionId);
if (!$row) {
    jsonResponse(false, '二维码无效或已过期');
}

if ($action === 'scan') {
    if ($row['status'] === 'confirmed') {
        jsonResponse(false, '该二维码已被确认登录');
    }
    if ($row['status'] === 'cancelled') {
        jsonResponse(false, '该二维码已被取消');
    }
    if ($row['status'] === 'expired') {
        jsonResponse(false, '二维码已过期，请刷新后重试');
    }

    $db->prepare(
        'UPDATE qrcode_sessions
         SET status = "scanned", scan_ip = ?, scan_ua = ?, scan_user_id = ?
         WHERE id = ? AND status IN ("pending", "scanned")'
    )->execute([
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        $userId,
        $sessionId,
    ]);

    jsonResponse(true, '', ['user' => qrUserPayload($user)]);
}

if ($action === 'confirm') {
    if ($row['status'] !== 'scanned') {
        jsonResponse(false, '二维码状态异常，请重新扫码');
    }
    if ((int)$row['scan_user_id'] !== $userId) {
        jsonResponse(false, '确认失败，操作者与扫码者不一致');
    }

    $stmt = $db->prepare(
        'UPDATE qrcode_sessions SET status = "confirmed", user_id = ?, confirmed_at = NOW()
         WHERE id = ? AND status = "scanned" AND scan_user_id = ?'
    );
    $stmt->execute([$userId, $sessionId, $userId]);
    if ($stmt->rowCount() !== 1) {
        jsonResponse(false, '二维码状态异常，请重新扫码');
    }

    jsonResponse(true, '已确认登录');
}

// cancel：未扫码时任何人不可取消；已扫码时仅扫码者本人可取消
if (!empty($row['scan_user_id']) && (int)$row['scan_user_id'] !== $userId) {
    jsonResponse(false, '无权取消该登录请求');
}

if (!in_array($row['status'], ['pending', 'scanned'], true)) {
    jsonResponse(false, '当前状态不可取消');
}

$db->prepare('UPDATE qrcode_sessions SET status = "cancelled" WHERE id = ?')->execute([$sessionId]);
jsonResponse(true, '已取消');
