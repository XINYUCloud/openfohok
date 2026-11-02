<?php
// ============================================================
// 文谶驿站 App 后端公共库
// 部署目录：https://fohok.xin/open-app/
// 依赖：站点根目录的 bootstrap.php（config / db / auth / email / notification）
// ============================================================

// ── 全局兜底必须先于站点内核加载 ─────────────────────────────
// 站点任一模块缺失/报错，都会在 require 阶段致命退出。
// 若兜底注册在 require 之后，则无法拦截，前端会收到裸 PHP 错误页 → “服务器繁忙”。
// 因此这里先把全局异常/致命错误处理器注册好，再加载站点内核。
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    if ($severity & (E_USER_ERROR | E_RECOVERABLE_ERROR)) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});
// 全局异常兜底：提前注册，未捕获异常统一转 JSON（含站点内核加载阶段的异常）。
// 正常业务通过 json_out() 结束（会 exit），不会走到兜底。
set_exception_handler(function ($e) {
    if (headers_sent()) { return; }
    http_response_code(200); // 一律 200，让业务错误由 json.msg 表达
    echo json_encode([
        'ok'    => false,
        'msg'   => '服务器内部错误，请稍后再试',
        'debug' => (defined('OPENAPP_DEBUG') && OPENAPP_DEBUG) ? $e->getMessage() : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
// 致命错误兜底：仅当确实发生了未被处理的分段/致命错误（如 require 了一个语法错误的 PHP 文件）
// 且尚未输出任何内容时才接管，避免污染正常响应。
register_shutdown_function(function () {
    if (headers_sent()) return;
    $err = error_get_last();
    $fatal = in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
    if ($fatal && $err['message'] !== 'Call to undefined function json_out()') {
        @header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => '服务器内部错误，请稍后再试'], JSON_UNESCAPED_UNICODE);
    }
});

// 加载站点内核（配置、数据库、鉴权、邮件、通知、OAuth、WebAuthn）
$kernel = __DIR__ . '/../bootstrap.php';
if (file_exists($kernel)) {
    require_once $kernel;
} else {
    // 独立部署兜底：允许通过环境变量指向站点根，找不到则报错
    if (defined('OPENAPP_BOOTSTRAP') && file_exists(OPENAPP_BOOTSTRAP)) {
        require_once OPENAPP_BOOTSTRAP;
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => '后端内核缺失：请将本目录部署到站点根目录/open-app 下']);
        exit;
    }
}

// App OAuth 客户端配置（标准 OAuth 2.0 + PKCE 流程，见 _oauth_client.php 头部说明）
require_once __DIR__ . '/_oauth_client.php';

// 统一 JSON 输出 + CORS（便于 HBuilderX H5 调试）
function json_out($data): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        // 允许 App 原生请求与 H5 调试跨域
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
    // 原生 App 无法保存 Cookie，统一走 AppToken，忽略 web session 干扰
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// OPTIONS 预检
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    json_out(['ok' => true]);
}

// AppToken 有效期：30 天
if (!defined('APP_TOKEN_LIFETIME')) define('APP_TOKEN_LIFETIME', 30 * 24 * 3600);
// 每个用户保留的最多有效 AppToken 数
if (!defined('APP_TOKEN_MAX'))        define('APP_TOKEN_MAX', 5);

/**
 * 确保 App 相关表存在（首次运行时自动建表/补列）
 */
function ensureAppSchema(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();
    $db->exec(
        "CREATE TABLE IF NOT EXISTS app_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token CHAR(64) NOT NULL,
            platform VARCHAR(20) DEFAULT 'app',
            user_agent VARCHAR(500) NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL,
            last_seen_at DATETIME NULL,
            expires_at DATETIME NULL,
            UNIQUE KEY uniq_token (token),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    // 旧版本部署补列（表已存在但缺设备信息列）
    $dbName = null;
    try {
        $dbName = $db->query('SELECT DATABASE()')->fetchColumn();
    } catch (\Throwable $e) {}
    if ($dbName) {
        foreach (['user_agent' => 'VARCHAR(500) NULL', 'ip_address' => 'VARCHAR(45) NULL'] as $col => $def) {
            try {
                $chk = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = \'app_tokens\' AND COLUMN_NAME = ?');
                $chk->execute([$dbName, $col]);
                if ((int)$chk->fetchColumn() === 0) {
                    $db->exec("ALTER TABLE app_tokens ADD COLUMN {$col} {$def}");
                }
            } catch (\Throwable $e) {}
        }
    }
    $db->exec(
        "CREATE TABLE IF NOT EXISTS app_oauth_tickets (
            ticket CHAR(32) PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            token CHAR(64) NULL,
            user_json TEXT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $done = true;
}

/**
 * 从请求中解析 Bearer AppToken
 */
function resolveAppToken(): string {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) return $m[1];
    $t = $_POST['token'] ?? $_GET['token'] ?? $_POST['app_token'] ?? $_GET['app_token'] ?? '';
    return is_string($t) ? $t : '';
}

/**
 * 用 AppToken 解析当前用户，未登录返回 null
 */
function appUser(): ?array {
    $token = resolveAppToken();
    if (!$token) return null;
    $db = getDB();

    // 方式 A：app_tokens 表（优先）
    try {
        $stmt = $db->prepare(
            'SELECT u.* FROM app_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token = ? AND (t.expires_at IS NULL OR t.expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $u = $stmt->fetch();
        if ($u) {
            try { $db->prepare('UPDATE app_tokens SET last_seen_at = NOW() WHERE token = ?')->execute([$token]); } catch (\Throwable $e) {}
            return $u;
        }
    } catch (\Throwable $e) {}

    // 方式 B：兼容 users.api_token
    try {
        $stmt = $db->prepare('SELECT * FROM users WHERE api_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $u = $stmt->fetch();
        if ($u) return $u;
    } catch (\Throwable $e) {}

    return null;
}

/**
 * 需要登录的接口；未登录直接输出错误并退出
 */
function requireAppUser(): array {
    $u = appUser();
    if (!$u) {
        json_out(['ok' => false, 'code' => 'UNAUTHORIZED', 'msg' => '请先登录']);
    }
    return $u;
}

/**
 * 为用户签发一个新的 AppToken，并清理该用户过期/超量的 token
 */
function mintAppToken(int $userId, string $platform = 'app'): string {
    ensureAppSchema();
    $db     = getDB();
    $token  = bin2hex(random_bytes(32));
    $exp    = date('Y-m-d H:i:s', time() + APP_TOKEN_LIFETIME);
    $ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? ('WenChanApp/' . $platform), 0, 500);
    $ip     = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45) ?: null;
    $db->prepare(
        'INSERT INTO app_tokens (user_id, token, platform, user_agent, ip_address, created_at, last_seen_at, expires_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)'
    )->execute([$userId, $token, $platform, $ua, $ip, $exp]);

    // 只清理该用户：过期的 + 超出保留上限的（旧的）
    try {
        $db->prepare(
            "DELETE FROM app_tokens
             WHERE user_id = ? AND (
                expires_at < NOW()
                OR id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM app_tokens
                        WHERE user_id = ? ORDER BY created_at DESC LIMIT ?
                    ) keep
                )
             )"
        )->execute([$userId, $userId, APP_TOKEN_MAX]);
    } catch (\Throwable $e) {}
    return $token;
}

/**
 * 检查用户是否在登录/发布限制期内；在限制期内抛 RuntimeException
 */
function checkUserRestriction(int $userId, string $kind = 'login'): void {
    $col = $kind === 'publish' ? 'publish_restricted_until' : 'login_restricted_until';
    $db  = getDB();
    $stmt = $db->prepare("SELECT {$col} AS until_at, restrict_reason FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $r = $stmt->fetch();
    if ($r && $r['until_at'] && strtotime($r['until_at']) > time()) {
        $label = $kind === 'publish' ? '发布' : '登录';
        throw new \RuntimeException('你的账号已被限制' . $label . '至 ' . $r['until_at'] . '，原因：' . ($r['restrict_reason'] ?: '违反平台规范'));
    }
}

/**
 * 规范化用户对外字段：避免把 OAuth token 等敏感字段返回给客户端
 */
function safeUser(array $u): array {
    return [
        'id'      => (int)($u['id'] ?? $u['user_id'] ?? 0),
        'name'    => $u['name'] ?? '',
        'email'   => $u['email'] ?? '',
        'picture' => $u['picture'] ?? '',
        'bio'     => $u['bio'] ?? '',
        'website' => $u['website'] ?? '',
        'phone'   => $u['phone'] ?? '',
        'thumb'   => $u['picture'] ?? '',
    ];
}

/**
 * 创建一条 OAuth 票据（App 端轮询用）
 */
function createOauthTicket(): array {
    ensureAppSchema();
    $db = getDB();
    // 顺手清理 15 分钟前的旧票据
    try { $db->exec("DELETE FROM app_oauth_tickets WHERE created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"); } catch (\Throwable $e) {}
    $ticket = bin2hex(random_bytes(16));
    $db->prepare('INSERT INTO app_oauth_tickets (ticket, status, created_at, updated_at) VALUES (?, ?, NOW(), NOW())')
       ->execute([$ticket, 'pending']);
    return ['ticket' => $ticket];
}

/**
 * OAuth 落地页调用：把登录结果写入票据
 * @return string|null 成功时返回签发的 AppToken，失败返回 null
 */
function completeOauthTicket(string $ticket, array $user): ?string {
    ensureAppSchema();
    $db = getDB();
    $uid = (int)($user['id'] ?? $user['user_id'] ?? 0);
    $token = mintAppToken($uid, 'oauth');
    $json = json_encode(safeUser($user), JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare(
        "UPDATE app_oauth_tickets
         SET status='done', user_id=?, token=?, user_json=?, updated_at=NOW()
         WHERE ticket=? AND status='pending'"
    );
    $stmt->execute([$uid, $token, $json, $ticket]);
    return $stmt->rowCount() > 0 ? $token : null;
}

/**
 * App 端轮询票据状态
 */
function pollOauthTicket(string $ticket): array {
    ensureAppSchema();
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT status, token, user_json, created_at FROM app_oauth_tickets WHERE ticket=? LIMIT 1"
    );
    $stmt->execute([$ticket]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'msg' => '票据不存在'];
    if (strtotime($row['created_at']) < time() - 900) {
        return ['ok' => false, 'msg' => '票据已过期，请重新发起登录'];
    }
    if ($row['status'] === 'denied') {
        return ['ok' => true, 'done' => true, 'denied' => true, 'msg' => '已取消授权'];
    }
    if ($row['status'] === 'done' && $row['token']) {
        // 一次性：取走后作废，防重放
        try { $db->prepare("UPDATE app_oauth_tickets SET status='used' WHERE ticket=?")->execute([$ticket]); } catch (\Throwable $e) {}
        return [
            'ok'    => true,
            'done'  => true,
            'token' => $row['token'],
            'user'  => json_decode($row['user_json'] ?: '{}', true),
        ];
    }
    return ['ok' => true, 'done' => false];
}

// ============================================================
// 标准 OAuth 2.0 + PKCE 客户端流程（https://fohok.xin/doc/oauth.html）
// App webview → oauth_start.php → authorize.php → oauth_callback.php
//            → verifyOAuthCode()（服务端校验）→ 签发 AppToken → 票据
// ============================================================

/**
 * 用户在授权页点了「拒绝」：标记票据被取消，App 轮询可感知
 */
function denyOauthTicket(string $ticket): void {
    ensureAppSchema();
    try {
        getDB()->prepare("UPDATE app_oauth_tickets SET status='denied', updated_at=NOW() WHERE ticket=? AND status='pending'")
            ->execute([$ticket]);
    } catch (\Throwable $e) {}
}

/**
 * 落地页调用：读取已完成票据的结果（标准流程由 oauth_callback.php 写入）
 * status 为 done（尚未被 App 轮询取走）或 used（已被取走）均可读
 */
function getOauthTicketResult(string $ticket): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $ticket)) return null;
    ensureAppSchema();
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT token, user_json, created_at FROM app_oauth_tickets
             WHERE ticket=? AND status IN ('done','used') LIMIT 1"
        );
        $stmt->execute([$ticket]);
        $row = $stmt->fetch();
        if (!$row || !$row['token']) return null;
        if (strtotime($row['created_at']) < time() - 900) return null;
        return [
            'token' => $row['token'],
            'user'  => json_decode($row['user_json'] ?: '{}', true),
        ];
    } catch (\Throwable $e) {
        return null;
    }
}