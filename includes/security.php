<?php
// ============================================================
// 安全基础：HTTPS 判定、响应头、同源校验、限流、通用响应
// ============================================================

/**
 * 是否为 HTTPS 请求（兼容反向代理）
 */
function securityIsHttps(): bool {
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    $forwarded = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwarded !== '' && str_contains($forwarded, 'https')) {
        return true;
    }
    return false;
}

/**
 * 下发基础安全响应头（幂等）
 */
function securitySendHeaders(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    if (securityIsHttps()) {
        header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }
}

/**
 * 当前请求主机（小写、去掉端口）
 */
function securityRequestHost(): string {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        $host = strtolower((string)($_SERVER['SERVER_NAME'] ?? ''));
    }
    if (preg_match('/^(\[[^\]]+\]|[^:]+)(?::\d+)?$/', $host, $m)) {
        $host = $m[1];
    }
    return $host;
}

/**
 * 判断请求是否来自本站（防 CSRF）
 *
 * 优先使用 Sec-Fetch-Site；缺失时回退到 Origin / Referer 的主机比对。
 * 站点 2026 年后主流浏览器均会发送 Sec-Fetch-Site，其余请求按最严格处理。
 */
function securityIsSameOriginRequest(): bool {
    $site = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    if ($site !== '') {
        return in_array($site, ['same-origin', 'none'], true);
    }

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
        $value = (string)($_SERVER[$key] ?? '');
        if ($value === '') continue;
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        return strtolower((string)$parts['host']) === securityRequestHost();
    }
    return false;
}

/**
 * 要求同源请求，否则返回 403 JSON 并退出
 */
function securityRequireSameOrigin(): void {
    if (securityIsSameOriginRequest()) return;
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => '非法请求来源'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 要求 POST + 同源（所有会改变状态的接口必须先调用）
 */
function securityRequirePost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => '请使用 POST 请求'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    securityRequireSameOrigin();
}

/**
 * 限流：同一 action + key 在窗口期内最多 max 次
 * 表不可用时放行，避免因数据库异常阻断业务。
 */
function securityRateLimit(string $action, int $max, int $windowSeconds = 60, string $key = ''): bool {
    static $tableReady = false;

    if ($key === '') {
        $key = 'ip:' . (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
    $bucket = hash('sha256', $action . '|' . $key);
    $now    = time();

    try {
        $db = getDB();
        if (!$tableReady) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS rate_limits (
                    bucket CHAR(64) NOT NULL PRIMARY KEY,
                    action VARCHAR(40) NOT NULL,
                    hits INT UNSIGNED NOT NULL DEFAULT 0,
                    window_start INT UNSIGNED NOT NULL,
                    KEY idx_window (window_start)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $tableReady = true;
        }

        // 概率清理历史窗口
        if (mt_rand(1, 50) === 1) {
            $db->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([$now - 3600]);
        }

        $stmt = $db->prepare('SELECT hits, window_start FROM rate_limits WHERE bucket = ? LIMIT 1');
        $stmt->execute([$bucket]);
        $row = $stmt->fetch();

        if ($row && (int)$row['window_start'] > $now - $windowSeconds) {
            if ((int)$row['hits'] >= $max) {
                return false;
            }
            $db->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?')->execute([$bucket]);
            return true;
        }

        $db->prepare(
            'INSERT INTO rate_limits (bucket, action, hits, window_start) VALUES (?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE hits = 1, window_start = VALUES(window_start), action = VALUES(action)'
        )->execute([$bucket, $action, $now]);
    } catch (\Throwable) {
        // 限流表不可用时放行
    }
    return true;
}

/**
 * 输出 JSON 并结束请求
 */
function jsonResponse(bool $ok, string $msg = '', array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 当前登录用户 ID（未登录返回 0）
 */
function currentUserId(?array $user = null): int {
    if ($user === null) {
        $user = getCurrentUser();
    }
    if (!$user) return 0;
    return (int)($user['user_id'] ?? $user['id'] ?? 0);
}

/**
 * 校验站内跳转路径，非法时返回 fallback
 */
function securitySafeNextPath($path, string $fallback = '/home.php'): string {
    $path = is_string($path) ? trim($path) : '';
    if ($path === ''
        || $path[0] !== '/'
        || (isset($path[1]) && $path[1] === '/')
        || str_contains($path, '\\')
        || preg_match('/[\r\n]/', $path)) {
        return $fallback;
    }
    return $path;
}
