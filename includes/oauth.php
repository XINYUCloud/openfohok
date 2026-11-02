<?php
// ============================================================
// 文谶驿站 OAuth 2.0 授权模块 - 公共函数（安全增强版）
// ============================================================

// OAuth 安全常量
if (!defined('OAUTH_CODE_EXPIRE_MINUTES')) {
    define('OAUTH_CODE_EXPIRE_MINUTES', 10);
}
if (!defined('OAUTH_CSRF_TOKEN_NAME')) {
    define('OAUTH_CSRF_TOKEN_NAME', 'oauth_csrf_token');
}
if (!defined('OAUTH_RATE_LIMIT_AUTHORIZE')) {
    define('OAUTH_RATE_LIMIT_AUTHORIZE', 10);   // 授权接口：每个 IP 每分钟最多 10 次
}
if (!defined('OAUTH_RATE_LIMIT_VERIFY')) {
    define('OAUTH_RATE_LIMIT_VERIFY', 30);       // 校验接口：每个 IP 每分钟最多 30 次
}
if (!defined('OAUTH_RATE_LIMIT_WINDOW')) {
    define('OAUTH_RATE_LIMIT_WINDOW', 60);       // 时间窗口：60 秒
}
if (!defined('OAUTH_ACCESS_TOKEN_EXPIRE_DAYS')) {
    define('OAUTH_ACCESS_TOKEN_EXPIRE_DAYS', 30); // access_token 有效期：30 天
}

// Session 中保存授权请求的键名
if (!defined('OAUTH_SESSION_REQUEST_KEY')) {
    define('OAUTH_SESSION_REQUEST_KEY', 'oauth_authorize_request');
}

/**
 * 生成应用 ID（32位十六进制）
 */
function generateAppId(): string {
    return bin2hex(random_bytes(16));
}

/**
 * 生成应用校验密钥（64位十六进制）
 */
function generateAppSecret(): string {
    return bin2hex(random_bytes(32));
}

// ============================================================
// PKCE（Proof Key for Code Exchange）辅助函数
// 用于防止授权码被截获后冒用，是 OAuth 2.0 公开客户端的标准防护手段。
// ============================================================

/**
 * 生成 PKCE code_verifier（43-128 字符的 Base64URL 字符串）
 */
function generatePkceCodeVerifier(int $length = 128): string {
    // RFC 7636 要求长度 43-128，且仅包含 [A-Za-z0-9-._~]
    $min = 43;
    $max = 128;
    if ($length < $min) $length = $min;
    if ($length > $max) $length = $max;

    $bytes = random_bytes((int)ceil($length * 3 / 4));
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/**
 * 根据 code_verifier 与 method 计算 code_challenge
 * 只支持 S256（推荐）与 plain（不推荐，按 RFC 7636 保留）
 */
function computePkceCodeChallenge(string $verifier, string $method = 'S256'): string {
    $method = strtolower($method);
    if ($method === 's256') {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
    if ($method === 'plain') {
        return $verifier;
    }
    throw new \InvalidArgumentException('不支持的 PKCE 加密方式：' . $method);
}

/**
 * 验证 code_verifier 是否与存储的 code_challenge 匹配
 */
function verifyPkceCodeChallenge(?string $verifier, ?string $storedChallenge, ?string $method = 'S256'): bool {
    if ($storedChallenge === null || $storedChallenge === '') {
        // 授权请求未使用 PKCE 时，不校验 verifier
        return true;
    }
    if ($verifier === null || $verifier === '') {
        return false;
    }
    $method = strtolower($method ?: 's256');
    try {
        return hash_equals($storedChallenge, computePkceCodeChallenge($verifier, $method));
    } catch (\Throwable) {
        return false;
    }
}

/**
 * 校验 PKCE code_challenge 格式是否合法
 */
function isValidPkceCodeChallenge(string $challenge): bool {
    return (bool)preg_match('/^[A-Za-z0-9\-\._~]{43,128}$/', $challenge);
}

/**
 * 根据 app_id 获取应用信息
 */
function getOAuthApp(string $appId): ?array {
    if ($appId === '') return null;
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM oauth_apps WHERE app_id = ? LIMIT 1');
        $stmt->execute([$appId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (\Throwable) {
        return null;
    }
}

/**
 * 校验 app_id + redirect_uri 是否匹配且应用可用
 * 返回 [ok, msg, app]
 *
 * 回调地址匹配规则：
 * 只比较 scheme://host[:port]/path 部分，忽略 query 参数。
 * 末尾斜杠归一化，避免因为多一个 / 导致匹配失败。
 */
function validateOAuthRequest(string $appId, string $redirectUri): array {
    if ($appId === '' || $redirectUri === '') {
        return [false, '缺少必要参数 app_id 或 redirect_uri', null];
    }

    $app = getOAuthApp($appId);
    if (!$app) {
        return [false, '应用不存在，app_id 无效', null];
    }
    if (($app['status'] ?? '') !== 'active') {
        return [false, '该应用已被禁用，暂不可用', null];
    }

    $incomingBase = _parseCallbackBase($redirectUri);
    $registeredBase = _parseCallbackBase($app['callback_url'] ?? '');

    if ($incomingBase === null || $registeredBase === null) {
        return [false, '回调地址格式不合法', null];
    }

    if ($incomingBase !== $registeredBase) {
        return [false, '回调地址与应用注册地址不匹配', null];
    }

    return [true, '', $app];
}

/**
 * 提取回调地址的 base URL：scheme://host[:port]/path
 * 忽略 query 和 fragment，末尾斜杠归一化
 */
function _parseCallbackBase(string $url): ?string {
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return null;
    }
    if (!in_array($parts['scheme'], ['http', 'https'], true)) {
        return null;
    }
    $base = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port'])) {
        $base .= ':' . $parts['port'];
    }
    $base .= $parts['path'] ?? '/';
    return rtrim($base, '/');
}

/**
 * 生成一次性授权码并写入数据库
 *
 * @param string      $appId              应用 ID
 * @param int         $userId             当前登录用户 ID
 * @param string|null $state              第三方传入的 state（可选）
 * @param string|null $codeChallenge      PKCE code_challenge（可选但强烈推荐）
 * @param string|null $codeChallengeMethod PKCE code_challenge_method，默认 S256
 * @return string 授权码
 */
function createOAuthCode(
    string $appId,
    int $userId,
    ?string $state = null,
    ?string $codeChallenge = null,
    ?string $codeChallengeMethod = null
): string {
    $db   = getDB();
    $code = bin2hex(random_bytes(24));

    // 清理过期授权码
    try { $db->exec('DELETE FROM oauth_authorizations WHERE expires_at < NOW()'); } catch (\Throwable) {}

    $challengeMethod = ($codeChallengeMethod !== null && $codeChallengeMethod !== '') ? $codeChallengeMethod : 'S256';

    $db->prepare(
        'INSERT INTO oauth_authorizations
         (code, app_id, user_id, state, code_challenge, code_challenge_method, expires_at, used)
         VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), 0)'
    )->execute([
        $code, $appId, $userId,
        $state ?? '',
        $codeChallenge ?? '',
        $codeChallenge !== null && $codeChallenge !== '' ? $challengeMethod : '',
        OAUTH_CODE_EXPIRE_MINUTES
    ]);

    return $code;
}

/**
 * 校验接口核心逻辑：核销授权码并返回用户信息
 *
 * 只支持 app_id + app_secret（原始密钥）方式。
 * 校验请求必须提供对应的 code_verifier。
 * 原始密钥不会出现在 URL/日志中，服务端通过 hash_equals 做恒定时间比对。
 *
 * 返回 [ok, msg, userInfo]
 */
function verifyOAuthCode(string $code, array $params): array {
    $appId        = trim($params['app_id']        ?? '');
    $appSecret    = trim($params['app_secret']    ?? '');
    $codeVerifier = trim($params['code_verifier'] ?? '');

    if ($code === '') {
        return [false, '缺少必要参数 code', null];
    }
    if ($appId === '' || $appSecret === '') {
        return [false, '缺少必要参数 app_id 或 app_secret', null];
    }
    if ($codeVerifier === '') {
        return [false, '缺少必要参数 code_verifier', null];
    }
    if (!isValidPkceCodeVerifier($codeVerifier)) {
        return [false, 'code_verifier 格式不合法', null];
    }

    // code 格式校验：48 位十六进制（bin2hex(random_bytes(24))）
    if (!preg_match('/^[a-f0-9]{48}$/', $code)) {
        return [false, '授权码格式不合法', null];
    }

    $db = getDB();

    // 校验 app_id + app_secret 是否匹配
    $app = getOAuthApp($appId);
    if (!$app) {
        return [false, '应用不存在', null];
    }
    if (!hash_equals($app['app_secret'], $appSecret)) {
        return [false, '应用校验密钥不正确', null];
    }

    // 使用事务 + 条件更新，确保 code 只被核销一次（防止并发重放）
    try {
        $db->beginTransaction();

        $stmt = $db->prepare(
            'SELECT * FROM oauth_authorizations
             WHERE code = ? AND used = 0 AND expires_at > NOW()
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$code]);
        $auth = $stmt->fetch();

        if (!$auth) {
            $db->rollBack();
            // 区分"已被使用"和"不存在/过期"，帮助第三方排查
            $exists = $db->prepare('SELECT used, expires_at FROM oauth_authorizations WHERE code = ? LIMIT 1');
            $exists->execute([$code]);
            $row = $exists->fetch();
            if (!$row) {
                return [false, '授权码无效', null];
            }
            if ((int)$row['used'] === 1) {
                return [false, '授权码已被使用', null];
            }
            return [false, '授权码已过期', null];
        }

        // 校验 code 是否属于该 app_id
        if ($appId !== ($auth['app_id'] ?? '')) {
            $db->rollBack();
            return [false, '授权码与应用不匹配', null];
        }

        // PKCE 校验：核销时必须传对应 code_verifier
        $storedChallenge = $auth['code_challenge'] ?? '';
        if ($storedChallenge === '' || !verifyPkceCodeChallenge($codeVerifier, $storedChallenge, $auth['code_challenge_method'] ?? 'S256')) {
            $db->rollBack();
            return [false, 'PKCE 校验失败，code_verifier 不匹配', null];
        }

        // 核销授权码
        $db->prepare('UPDATE oauth_authorizations SET used = 1, used_at = NOW() WHERE id = ?')
           ->execute([$auth['id']]);

        $user = getOAuthUserById((int)$auth['user_id']);

        if (!$user) {
            $db->rollBack();
            return [false, '用户不存在', null];
        }

        $db->commit();
        return [true, '', $user];
    } catch (\Throwable $e) {
        try { $db->rollBack(); } catch (\Throwable) {}
        throw $e;
    }
}

/**
 * 校验 PKCE code_verifier 格式是否合法
 */
function isValidPkceCodeVerifier(string $verifier): bool {
    return (bool)preg_match('/^[A-Za-z0-9\-\._~]{43,128}$/', $verifier);
}

/**
 * 记录 verify 接口审计日志
 * 如果日志表不存在则静默忽略，不阻塞业务
 */
function logOAuthVerify(string $code, bool $success, string $msg, ?string $appId = null): void {
    try {
        $db = getDB();
        $db->prepare(
            'INSERT INTO oauth_verify_logs (code, app_id, success, message, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $code,
            $appId,
            $success ? 1 : 0,
            $msg,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (\Throwable) {
        // 表不存在时忽略
    }
}

// ============================================================
// Access Token（用户信息同步接口凭证）
// ============================================================

/**
 * 生成 access_token 并写入数据库
 *
 * @param string $appId  应用 ID
 * @param int    $userId 用户 ID
 * @return string access_token
 */
function createOAuthAccessToken(string $appId, int $userId): string {
    $db    = getDB();
    $token = bin2hex(random_bytes(32));

    // 清理过期 token
    try { $db->exec('DELETE FROM oauth_access_tokens WHERE expires_at < NOW()'); } catch (\Throwable) {}

    $db->prepare(
        'INSERT INTO oauth_access_tokens
         (app_id, user_id, access_token, expires_at, created_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW())'
    )->execute([
        $appId,
        $userId,
        $token,
        OAUTH_ACCESS_TOKEN_EXPIRE_DAYS
    ]);

    return $token;
}

/**
 * 验证 access_token 并返回关联信息
 *
 * 返回 [ok, msg, data]
 * data 包含 app_id, user_id 等字段
 */
function verifyOAuthAccessToken(string $token): array {
    if ($token === '') {
        return [false, '缺少必要参数 access_token', null];
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return [false, 'access_token 格式不合法', null];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT * FROM oauth_access_tokens
             WHERE access_token = ? AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            return [false, 'access_token 无效或已过期', null];
        }

        // 更新最后使用时间
        $db->prepare('UPDATE oauth_access_tokens SET last_used_at = NOW() WHERE id = ?')
           ->execute([$row['id']]);

        return [true, '', $row];
    } catch (\Throwable $e) {
        return [false, '服务暂时不可用，请稍后重试', null];
    }
}

/**
 * 校验 app_id + app_secret 是否匹配
 */
function verifyOAuthAppSecret(string $appId, string $appSecret): array {
    if ($appId === '' || $appSecret === '') {
        return [false, '缺少必要参数 app_id 或 app_secret', null];
    }

    $app = getOAuthApp($appId);
    if (!$app) {
        return [false, '应用不存在', null];
    }
    if (!hash_equals($app['app_secret'], $appSecret)) {
        return [false, '应用校验密钥不正确', null];
    }

    return [true, '', $app];
}

/**
 * 根据用户 ID 获取用户信息（供 verify / userinfo 使用）
 */
function getOAuthUserById(int $userId): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT id AS user_id, name, email, picture, bio, website, phone
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    } catch (\Throwable) {
        return null;
    }
}

/**
 * 记录 userinfo 接口审计日志
 */
function logOAuthUserinfo(string $token, bool $success, string $msg, ?string $appId = null): void {
    try {
        $db = getDB();
        $db->prepare(
            'INSERT INTO oauth_userinfo_logs (access_token, app_id, success, message, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $token,
            $appId,
            $success ? 1 : 0,
            $msg,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (\Throwable) {
        // 表不存在时忽略
    }
}

// ============================================================
// CSRF 防护
// ============================================================

function generateCsrfToken(): string {
    startSecureSession();
    if (empty($_SESSION[OAUTH_CSRF_TOKEN_NAME])) {
        $_SESSION[OAUTH_CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[OAUTH_CSRF_TOKEN_NAME];
}

function verifyCsrfToken(string $token): bool {
    startSecureSession();
    if (empty($_SESSION[OAUTH_CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[OAUTH_CSRF_TOKEN_NAME], $token);
}

/**
 * 消耗 CSRF Token（一次性使用）
 */
function consumeCsrfToken(): void {
    startSecureSession();
    unset($_SESSION[OAUTH_CSRF_TOKEN_NAME]);
}

// ============================================================
// 授权请求 Session 绑定
// 用于在 authorize.php 与 do_authorize.php 之间安全传递参数，
// 避免 do_authorize.php 信任客户端提交的 redirect_uri。
// ============================================================

/**
 * 将授权请求绑定到 session
 */
function bindOAuthRequestToSession(
    string $appId,
    string $redirectUri,
    ?string $state = null,
    ?string $codeChallenge = null,
    ?string $codeChallengeMethod = null
): void {
    startSecureSession();
    $_SESSION[OAUTH_SESSION_REQUEST_KEY] = [
        'app_id'                => $appId,
        'redirect_uri'          => $redirectUri,
        'state'                 => $state,
        'code_challenge'        => $codeChallenge,
        'code_challenge_method' => $codeChallengeMethod,
        'created_at'            => time(),
    ];
}

/**
 * 从 session 读取已绑定的授权请求
 */
function getBoundOAuthRequest(): ?array {
    startSecureSession();
    $req = $_SESSION[OAUTH_SESSION_REQUEST_KEY] ?? null;
    if (!$req) return null;
    // 5 分钟未提交则过期，需要重新走授权页
    if ((time() - (int)($req['created_at'] ?? 0)) > 300) {
        unset($_SESSION[OAUTH_SESSION_REQUEST_KEY]);
        return null;
    }
    return $req;
}

/**
 * 清除 session 中绑定的授权请求
 */
function clearBoundOAuthRequest(): void {
    startSecureSession();
    unset($_SESSION[OAUTH_SESSION_REQUEST_KEY]);
}

/**
 * 构造回调 URL，将 OAuth 参数合并到已有参数中
 * 回调只返回 code 与 state，不会附带任何密钥信息
 */
function buildCallbackUrl(string $redirectUri, array $oauthParams): string {
    $urlParts = parse_url($redirectUri);
    if ($urlParts === false || !isset($urlParts['scheme'], $urlParts['host'])) {
        throw new \InvalidArgumentException('回调地址格式不合法');
    }

    $baseUrl = $urlParts['scheme'] . '://' . $urlParts['host'];
    if (isset($urlParts['port'])) {
        $baseUrl .= ':' . $urlParts['port'];
    }
    $baseUrl .= $urlParts['path'] ?? '/';

    $existingParams = [];
    if (isset($urlParts['query'])) {
        parse_str($urlParts['query'], $existingParams);
    }

    $allParams = array_merge($existingParams, $oauthParams);
    return $baseUrl . '?' . http_build_query($allParams);
}

// ============================================================
// 维护模式
// ============================================================

function getOAuthSetting(string $key, string $default = ''): string {
    try {
        $stmt = getDB()->prepare('SELECT setting_value FROM oauth_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (\Throwable) {
        return $default;
    }
}

function setOAuthSetting(string $key, string $value): void {
    getDB()->prepare(
        'INSERT INTO oauth_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    )->execute([$key, $value]);
}

function isOAuthMaintenance(): bool {
    return getOAuthSetting('maintenance_mode', '0') === '1';
}

function getOAuthMaintenanceNotice(): string {
    return getOAuthSetting('maintenance_notice', 'OAuth 授权服务正在维护中，请稍后再试。');
}

// ============================================================
// 频率限制（基于 IP + action）
// ============================================================

function checkRateLimit(string $action, int $maxAttempts, int $windowSeconds = 60): array {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $identifier = hash('sha256', $ip . ':' . $action);
    $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

    try {
        getDB()->prepare(
            'DELETE FROM oauth_rate_limits WHERE identifier = ? AND action = ? AND window_start < ?'
        )->execute([$identifier, $action, $windowStart]);

        $stmt = getDB()->prepare(
            'SELECT SUM(attempts) as total FROM oauth_rate_limits
             WHERE identifier = ? AND action = ? AND window_start >= ?'
        );
        $stmt->execute([$identifier, $action, $windowStart]);
        $row = $stmt->fetch();
        $count = (int)($row['total'] ?? 0);

        if ($count >= $maxAttempts) {
            return [false, $count, $maxAttempts];
        }

        getDB()->prepare(
            'INSERT INTO oauth_rate_limits (identifier, action, attempts, window_start)
             VALUES (?, ?, 1, NOW())'
        )->execute([$identifier, $action]);

        return [true, $count + 1, $maxAttempts];
    } catch (\Throwable) {
        // 频率限制表不可用时放行，不阻塞正常业务
        return [true, 0, $maxAttempts];
    }
}

/**
 * 按 app_id 限流（与 IP 限流互补）
 * 防止单个应用密钥泄露后被大量滥用
 */
function checkAppRateLimit(string $appId, string $action, int $maxAttempts, int $windowSeconds = 60): array {
    if ($appId === '') {
        return [true, 0, $maxAttempts];
    }
    $identifier = hash('sha256', 'app:' . $appId . ':' . $action);
    $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

    try {
        getDB()->prepare(
            'DELETE FROM oauth_rate_limits WHERE identifier = ? AND action = ? AND window_start < ?'
        )->execute([$identifier, $action, $windowStart]);

        $stmt = getDB()->prepare(
            'SELECT SUM(attempts) as total FROM oauth_rate_limits
             WHERE identifier = ? AND action = ? AND window_start >= ?'
        );
        $stmt->execute([$identifier, $action, $windowStart]);
        $row = $stmt->fetch();
        $count = (int)($row['total'] ?? 0);

        if ($count >= $maxAttempts) {
            return [false, $count, $maxAttempts];
        }

        getDB()->prepare(
            'INSERT INTO oauth_rate_limits (identifier, action, attempts, window_start)
             VALUES (?, ?, 1, NOW())'
        )->execute([$identifier, $action]);

        return [true, $count + 1, $maxAttempts];
    } catch (\Throwable) {
        return [true, 0, $maxAttempts];
    }
}

// ============================================================
// OAuth 管理员校验
// ============================================================

function isOAuthAdmin(): bool {
    $user = getCurrentUser();
    if (!$user) return false;
    return in_array($user['email'] ?? '', ADMIN_EMAILS, true);
}

function requireOAuthAdmin(): void {
    if (!isOAuthAdmin()) {
        http_response_code(403);
        exit('403 Forbidden — 无权访问此页面');
    }
}
