<?php
// ============================================================
// 文谶账户 OAuth 2.0（授权码 + PKCE）客户端
//
// 身份模型：
//   wenchan_uid  = 账号中心用户 ID（不可变，主身份键）
//   wenchan_sub  = 账号中心 sub（兼容旧数据，辅助键）
//
// 安全要点：
//   1. 授权流程一次性写入 wenchan_oauth_flows，state 与会话绑定；
//      回调必须来自同一浏览器会话，且 state 只能使用一次（防登录 CSRF / 重放）。
//   2. 本机账号仅在账号中心确认邮箱已验证时才允许按邮箱认领，
//      已绑定其它文谶身份的账号绝不合并（防账号接管）。
//   3. 增量授权使用账号中心 access_token，用户只需确认新增权限。
// ============================================================

/** 合并 scope，去重且保持顺序 */
function wenchanMergeScope(string $base, string ...$extra): string {
    $scopes = preg_split('/[\s,]+/', trim($base), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($extra as $chunk) {
        foreach (preg_split('/[\s,]+/', trim($chunk), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $scope) {
            if (!in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }
    }
    return implode(' ', $scopes);
}

/** 登录统一申请的权限集合 */
function wenchanScopeForLogin(string $extraScope = ''): string {
    $scope = WENCHAN_OAUTH_SCOPE;
    if (function_exists('wenchanReadEnabled') && wenchanReadEnabled()) {
        $scope = wenchanMergeScope($scope, 'notification:read');
    }
    return wenchanMergeScope($scope, $extraScope);
}

/**
 * 幂等补齐身份与授权流程表结构
 */
function wenchanEnsureSchema(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db = getDB();

        $existing = [];
        $stmt = $db->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
        );
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $existing[$row['COLUMN_NAME']] = true;
        }

        $wanted = [
            'wenchan_uid' => 'ALTER TABLE users ADD COLUMN wenchan_uid BIGINT UNSIGNED NULL DEFAULT NULL',
            'wenchan_sub' => 'ALTER TABLE users ADD COLUMN wenchan_sub VARCHAR(191) NULL DEFAULT NULL',
        ];
        foreach ($wanted as $name => $sql) {
            if (!isset($existing[$name])) {
                $db->exec($sql);
            }
        }

        foreach ([
            'uniq_wenchan_uid' => 'ALTER TABLE users ADD UNIQUE KEY uniq_wenchan_uid (wenchan_uid)',
            'uniq_wenchan_sub' => 'ALTER TABLE users ADD UNIQUE KEY uniq_wenchan_sub (wenchan_sub)',
        ] as $index => $sql) {
            $idx = $db->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = ?"
            );
            $idx->execute([$index]);
            if ((int)$idx->fetchColumn() === 0) {
                try { $db->exec($sql); } catch (\Throwable) {}
            }
        }

        $db->exec(
            "CREATE TABLE IF NOT EXISTS wenchan_oauth_flows (
                state CHAR(64) NOT NULL PRIMARY KEY,
                session_hash CHAR(64) NOT NULL,
                code_verifier VARCHAR(128) NOT NULL,
                mode VARCHAR(16) NOT NULL DEFAULT 'login',
                user_id INT UNSIGNED NULL,
                next_path VARCHAR(255) NULL,
                scope VARCHAR(500) NOT NULL,
                access_token CHAR(64) NULL,
                created_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                KEY idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (\Throwable) {
        // 结构补齐失败不阻断请求；登录时会再次尝试
    }
}

/**
 * 发起授权：$linkUserId 非空表示已登录用户补授权（不重建会话）
 */
function wenchanOauthStart(?string $to = null, string $extraScope = '', ?int $linkUserId = null): void {
    wenchanEnsureSchema();
    startSecureSession();

    if (!securityRateLimit('oauth_start', 20, 60)) {
        header('Location: /login.php?error=too_many_requests');
        exit;
    }

    $state    = bin2hex(random_bytes(32));
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    if (strlen($verifier) < 43) {
        $verifier .= str_repeat('A', 43 - strlen($verifier));
    }
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $mode = $linkUserId !== null ? 'link' : 'login';
    $scope = wenchanScopeForLogin($extraScope);
    $next = securitySafeNextPath($to, $mode === 'link' ? '/messages.php' : '/home.php');

    $accessToken = '';
    if ($mode === 'link') {
        $accessToken = wenchanActiveAccessToken($linkUserId);
    }

    try {
        $db = getDB();
        $db->prepare(
            'INSERT INTO wenchan_oauth_flows
                (state, session_hash, code_verifier, mode, user_id, next_path, scope, access_token, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE))'
        )->execute([
            $state,
            hash('sha256', session_id()),
            $verifier,
            $mode,
            $linkUserId,
            $next,
            $scope,
            $accessToken !== '' ? $accessToken : null,
        ]);

        if (mt_rand(1, 10) === 1) {
            $db->exec('DELETE FROM wenchan_oauth_flows WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        }
    } catch (\Throwable) {
        header('Location: /login.php?error=server_busy');
        exit;
    }

    $params = [
        'app_id'                => WENCHAN_OAUTH_APP_ID,
        'redirect_uri'          => WENCHAN_OAUTH_CALLBACK,
        'response_type'         => 'code',
        'scope'                 => $scope,
        'state'                 => $state,
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256',
    ];
    if ($accessToken !== '') {
        $params['access_token'] = $accessToken;
    }

    header('Location: ' . WENCHAN_OAUTH_AUTH_URL . '?' . http_build_query($params));
    exit;
}

/**
 * 处理 OAuth 回调
 *
 * 返回：
 *   ['ok'=>true,  'mode'=>'login'|'link', 'user_id'=>int, 'is_new'=>bool, 'next'=>string]
 *   ['ok'=>false, 'code'=>string, 'mode'=>string, 'next'=>string]
 */
function wenchanOauthCallback(string $state, string $code): array {
    wenchanEnsureSchema();
    startSecureSession();

    $fallbackMode = 'login';
    $fallbackNext = '/login.php';

    if ($state === '' || $code === '') {
        return ['ok' => false, 'code' => 'invalid_callback', 'mode' => $fallbackMode, 'next' => $fallbackNext];
    }
    if (!securityRateLimit('oauth_callback', 30, 60)) {
        return ['ok' => false, 'code' => 'too_many_requests', 'mode' => $fallbackMode, 'next' => $fallbackNext];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM wenchan_oauth_flows WHERE state = ? LIMIT 1');
        $stmt->execute([$state]);
        $flow = $stmt->fetch();
    } catch (\Throwable) {
        return ['ok' => false, 'code' => 'server_error', 'mode' => $fallbackMode, 'next' => $fallbackNext];
    }

    if (!$flow) {
        return ['ok' => false, 'code' => 'invalid_state', 'mode' => $fallbackMode, 'next' => $fallbackNext];
    }

    $mode = (string)$flow['mode'];
    $next = securitySafeNextPath($flow['next_path'] ?? '', $mode === 'link' ? '/messages.php' : '/home.php');
    $err  = static fn(string $code) => ['ok' => false, 'code' => $code, 'mode' => $mode, 'next' => $next];

    if (!hash_equals((string)$flow['session_hash'], hash('sha256', session_id()))) {
        return $err('session_mismatch');
    }
    if (!empty($flow['used_at'])) {
        return $err('state_used');
    }
    if (strtotime((string)$flow['expires_at']) < time()) {
        return $err('state_expired');
    }

    // 一次性消费：并发回调只有一个能成功
    try {
        $consume = $db->prepare('UPDATE wenchan_oauth_flows SET used_at = NOW() WHERE state = ? AND used_at IS NULL');
        $consume->execute([$state]);
        if ($consume->rowCount() !== 1) {
            return $err('state_used');
        }
    } catch (\Throwable) {
        return $err('server_error');
    }

    $result = wenchanExchangeCode($code, (string)$flow['code_verifier']);
    if (!$result) {
        return $err('exchange_failed');
    }

    $info = $result['user'] ?? [];
    $sub  = trim((string)($info['sub'] ?? ''));
    $uid  = (int)($info['id'] ?? 0);
    if ($sub === '' || $uid <= 0) {
        return $err('userinfo_invalid');
    }

    if ($mode === 'link') {
        $target = (int)$flow['user_id'];
        if ($target <= 0 || !wenchanIdentityMatchesUser($target, $info)) {
            return $err('account_mismatch');
        }
        try {
            $saved = wenchanUpsertUser($info, $result + ['state' => $state]);
        } catch (\RuntimeException $e) {
            return $err($e->getMessage() === 'email_conflict' ? 'email_conflict' : 'server_error');
        } catch (\Throwable) {
            return $err('server_error');
        }
        if ((int)$saved['id'] !== $target) {
            return $err('account_mismatch');
        }
        return ['ok' => true, 'mode' => 'link', 'user_id' => $target, 'is_new' => false, 'next' => $next];
    }

    try {
        $saved = wenchanUpsertUser($info, $result + ['state' => $state]);
    } catch (\RuntimeException $e) {
        return $err($e->getMessage() === 'email_conflict' ? 'email_conflict' : 'server_error');
    } catch (\Throwable) {
        return $err('server_error');
    }

    return [
        'ok'      => true,
        'mode'    => 'login',
        'user_id' => (int)$saved['id'],
        'is_new'  => (bool)($saved['is_new'] ?? false),
        'next'    => $next,
    ];
}

/**
 * 判断账号中心身份是否属于本站指定用户
 */
function wenchanIdentityMatchesUser(int $userId, array $info): bool {
    try {
        $stmt = getDB()->prepare(
            'SELECT id, wenchan_uid, wenchan_sub, email FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
    } catch (\Throwable) {
        return false;
    }
    if (!$row) return false;

    $uid = (int)($info['id'] ?? 0);
    $sub = trim((string)($info['sub'] ?? ''));

    if (!empty($row['wenchan_uid']) && (int)$row['wenchan_uid'] === $uid) {
        return true;
    }
    if ($sub !== '' && (string)$row['wenchan_sub'] === $sub) {
        return true;
    }
    if (empty($row['wenchan_uid']) && empty($row['wenchan_sub'])
        && !empty($info['email_verified'])
        && $uid > 0
        && strcasecmp((string)($row['email'] ?? ''), (string)($info['email'] ?? '')) === 0) {
        return true;
    }
    return false;
}

/**
 * 按文谶身份落库（查找 → 认领/建档 → 保存令牌）
 *
 * @throws \RuntimeException email_conflict
 */
function wenchanUpsertUser(array $info, ?array $tokens = null): array {
    ensureForwardSchema();

    $db      = getDB();
    $sub     = trim((string)($info['sub'] ?? ''));
    $uid     = (int)($info['id'] ?? 0);
    $email   = strtolower(trim((string)($info['email'] ?? '')));
    $verified = !empty($info['email_verified']);
    $name    = trim((string)($info['name'] ?? ''));
    $picture = trim((string)($info['picture'] ?? ''));
    $given   = trim((string)($info['given_name'] ?? ''));
    $family  = trim((string)($info['family_name'] ?? ''));

    if ($sub === '' || $uid <= 0) {
        throw new \RuntimeException('userinfo_invalid');
    }

    $byUid = null;
    $bySub = null;
    try {
        $stmt = $db->prepare('SELECT * FROM users WHERE wenchan_uid = ? LIMIT 1');
        $stmt->execute([$uid]);
        $byUid = $stmt->fetch() ?: null;

        $stmt = $db->prepare('SELECT * FROM users WHERE wenchan_sub = ? LIMIT 1');
        $stmt->execute([$sub]);
        $bySub = $stmt->fetch() ?: null;
    } catch (\Throwable) {
        // 兼容尚未补齐列的环境
    }

    if ($byUid && $bySub && (int)$byUid['id'] !== (int)$bySub['id']) {
        throw new \RuntimeException('email_conflict');
    }

    $row = $byUid ?: $bySub;

    if (!$row && $email !== '' && $verified) {
        $stmt = $db->prepare('SELECT * FROM users WHERE LOWER(email) = ? ORDER BY id ASC');
        $stmt->execute([$email]);
        $candidates = $stmt->fetchAll() ?: [];

        $unbound = [];
        foreach ($candidates as $candidate) {
            if (empty($candidate['wenchan_uid']) && (string)($candidate['wenchan_sub'] ?? '') === '') {
                $unbound[] = $candidate;
            }
        }
        if ($unbound !== []) {
            $row = $unbound[0];
        } elseif ($candidates !== []) {
            // 邮箱已绑定到其它文谶账号，拒绝合并
            throw new \RuntimeException('email_conflict');
        }
    }

    if ($row) {
        $userId = (int)$row['id'];
        $sets   = ['last_login_at = NOW()', 'updated_at = NOW()'];
        $args   = [];

        if (empty($row['wenchan_uid'])) { $sets[] = 'wenchan_uid = ?'; $args[] = $uid; }
        if (empty($row['wenchan_sub'])) { $sets[] = 'wenchan_sub = ?'; $args[] = $sub; }
        if ($verified) {
            if ($email !== '' && strcasecmp((string)($row['email'] ?? ''), $email) !== 0) {
                $sets[] = 'email = ?';
                $args[] = $email;
            }
            if ((int)($row['email_verified'] ?? 0) !== 1) {
                $sets[] = 'email_verified = 1';
            }
        }
        if ($name !== '' && trim((string)($row['name'] ?? '')) === '') { $sets[] = 'name = ?'; $args[] = $name; }
        if ($picture !== '' && trim((string)($row['picture'] ?? '')) === '') { $sets[] = 'picture = ?'; $args[] = $picture; }
        if ($given !== '' && trim((string)($row['given_name'] ?? '')) === '') { $sets[] = 'given_name = ?'; $args[] = $given; }
        if ($family !== '' && trim((string)($row['family_name'] ?? '')) === '') { $sets[] = 'family_name = ?'; $args[] = $family; }

        $args[] = $userId;
        $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
        wenchanSaveTokens($userId, $tokens);
        return ['id' => $userId, 'is_new' => false];
    }

    // 新建：邮箱未验证时不落库，避免占用他人的邮箱
    $storedEmail = $verified ? ($email !== '' ? $email : null) : null;

    try {
        $db->prepare(
            'INSERT INTO users
                (wenchan_uid, wenchan_sub, name, given_name, family_name, email, email_verified,
                 picture, reg_source, last_login_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())'
        )->execute([
            $uid,
            $sub,
            $name !== '' ? $name : null,
            $given !== '' ? $given : null,
            $family !== '' ? $family : null,
            $storedEmail,
            $storedEmail !== null ? 1 : 0,
            $picture !== '' ? $picture : null,
            'wenchan',
        ]);
        $userId = (int)$db->lastInsertId();
    } catch (\PDOException $e) {
        // 并发首登：唯一键冲突后回读
        $stmt = $db->prepare('SELECT id FROM users WHERE wenchan_uid = ? OR wenchan_sub = ? LIMIT 1');
        $stmt->execute([$uid, $sub]);
        $userId = (int)$stmt->fetchColumn();
        if ($userId <= 0) {
            throw new \RuntimeException('server_error');
        }
        wenchanSaveTokens($userId, $tokens);
        return ['id' => $userId, 'is_new' => false];
    }

    wenchanSaveTokens($userId, $tokens);
    return ['id' => $userId, 'is_new' => true];
}

/** 保存本次授权令牌 */
function wenchanSaveTokens(int $userId, ?array $tokens): void {
    if (!$tokens || empty($tokens['access_token'])) {
        return;
    }
    $expiresAt = null;
    if (!empty($tokens['expires_in'])) {
        $expiresAt = date('Y-m-d H:i:s', time() + (int)$tokens['expires_in']);
    }
    saveWenchanTokens(
        $userId,
        (string)$tokens['access_token'],
        isset($tokens['refresh_token']) ? (string)$tokens['refresh_token'] : null,
        $expiresAt,
        isset($tokens['state']) ? (string)$tokens['state'] : null
    );
}

/**
 * 取用户可用的 access_token（过期时静默刷新），失败返回空串
 */
function wenchanActiveAccessToken(int $userId): string {
    $tokens = getWenchanTokens($userId);
    $access = (string)($tokens['wenchan_access_token'] ?? '');
    if ($access === '') {
        return '';
    }
    if (!empty($tokens['wenchan_token_expires_at'])
        && strtotime((string)$tokens['wenchan_token_expires_at']) < time() + 60) {
        $access = function_exists('refreshWenchanToken') ? refreshWenchanToken($userId) : '';
    }
    return (string)$access;
}

/**
 * 用一次性授权码 + PKCE code_verifier 换取令牌与用户信息
 */
function wenchanExchangeCode(string $code, string $codeVerifier): ?array {
    $verifyResp = _wenchanCurl(WENCHAN_OAUTH_VERIFY_URL, [
        'app_id'        => WENCHAN_OAUTH_APP_ID,
        'code'          => $code,
        'app_secret'    => WENCHAN_OAUTH_APP_SECRET,
        'code_verifier' => $codeVerifier,
    ]);
    if ($verifyResp === null) {
        return null;
    }
    $data = json_decode($verifyResp, true);
    if (!is_array($data) || empty($data['ok']) || empty($data['access_token'])) {
        return null;
    }

    $userResp = _wenchanCurl(WENCHAN_OAUTH_USERINFO_URL, [
        'app_id'       => WENCHAN_OAUTH_APP_ID,
        'app_secret'   => WENCHAN_OAUTH_APP_SECRET,
        'access_token' => $data['access_token'],
    ]);
    if ($userResp === null) {
        return null;
    }
    $userData = json_decode($userResp, true);
    if (!is_array($userData) || empty($userData['ok']) || !is_array($userData['user'] ?? null)) {
        return null;
    }

    return [
        'user'          => $userData['user'],
        'access_token'  => $data['access_token']  ?? null,
        'refresh_token' => $data['refresh_token'] ?? null,
        'expires_in'    => $data['expires_in']    ?? null,
    ];
}

/** 向账号中心接口发起表单 POST，返回原始响应；失败返回 null */
function _wenchanCurl(string $url, array $fields): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $err !== '') {
        error_log("[wenchanOAuth] curl error to {$url}: {$err}");
        return null;
    }
    if ($http !== 200) {
        error_log("[wenchanOAuth] HTTP {$http} from {$url}");
    }
    return is_string($resp) ? $resp : null;
}
