<?php
// ============================================================
// 会话与当前用户
// 身份来源统一为文谶账户 OAuth（account.fohok.xin）
// ============================================================

/**
 * 启动安全会话
 * - Cookie 仅限本站主机（不共享到子域，避免被其它子站读取）
 * - HttpOnly + SameSite=Lax + HTTPS 时 Secure
 */
function startSecureSession(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => securityIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * 获取当前登录用户，未登录或过期返回 null
 */
function getCurrentUser(): ?array {
    startSecureSession();

    $token = $_COOKIE['op_token'] ?? ($_SESSION['op_token'] ?? null);
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    try {
        $stmt = getDB()->prepare(
            'SELECT s.*, u.* FROM sessions s
             JOIN users u ON s.user_id = u.id
             WHERE s.id = ? AND s.expires_at > NOW()'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
    } catch (\Throwable) {
        return null;
    }

    if (!$row) {
        setcookie('op_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => securityIsHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_SESSION['op_token']);
        return null;
    }

    return $row;
}

/**
 * 未登录返回 null（不跳转）
 */
function optionalLogin(): ?array {
    return getCurrentUser();
}

/**
 * 要求登录，否则跳转登录页
 */
function requireLogin(): array {
    $user = getCurrentUser();
    if (!$user) {
        $to = securitySafeNextPath($_SERVER['REQUEST_URI'] ?? '/', '/home.php');
        header('Location: /login.php?to=' . urlencode($to));
        exit;
    }
    return $user;
}

/**
 * 要求管理员登录（按账号中心验证过的邮箱判定）
 */
function requireAdmin(): array {
    $user = requireLogin();
    $email = strtolower((string)($user['email'] ?? ''));
    $admins = array_map('strtolower', ADMIN_EMAILS);
    if ($email === '' || !in_array($email, $admins, true)) {
        http_response_code(403);
        die('无权访问');
    }
    return $user;
}

/**
 * 创建登录会话
 */
function createSession(int $userId, bool $isNewUser = false): string {
    $db = getDB();

    $stmt = $db->prepare('SELECT login_restricted_until, restrict_reason FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $r = $stmt->fetch();
    if ($r && !empty($r['login_restricted_until']) && strtotime((string)$r['login_restricted_until']) > time()) {
        throw new \RuntimeException('账号已被限制登录至 ' . $r['login_restricted_until'] . '，原因：' . ($r['restrict_reason'] ?: '违反平台规范'));
    }

    $token = bin2hex(random_bytes(32));
    $exp   = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

    $db->prepare(
        'INSERT INTO sessions(id, user_id, expires_at, ip_address, user_agent)
         VALUES(?, ?, ?, ?, ?)'
    )->execute([
        $token,
        $userId,
        $exp,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);

    startSecureSession();
    // 防会话固定：登录后更换 PHP 会话 ID
    @session_regenerate_id(true);

    setcookie('op_token', $token, [
        'expires'  => time() + SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => securityIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_SESSION['op_token'] = $token;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '未知';
    if ($isNewUser) {
        createNotification($userId, 'welcome', '欢迎来到文谶驿站', "感谢你的加入！\n\n在这里你可以浏览独立开发者的开源项目，也可以发布自己的作品，与志同道合的人交流。\n\n祝你探索愉快！");
    } else {
        createNotification($userId, 'login', '新设备登录提醒', "您的账号于 " . date('Y-m-d H:i') . " 通过 IP: {$ip} 登录了文谶驿站。\n\n如非本人操作，请及时检查账号安全并于会话管理中对陌生登录进行踢除。");
    }

    return $token;
}

/**
 * 退出登录
 */
function logout(): void {
    startSecureSession();
    $token = $_COOKIE['op_token'] ?? ($_SESSION['op_token'] ?? null);
    if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
        try {
            getDB()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$token]);
        } catch (\Throwable) {}
    }

    setcookie('op_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => securityIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();

    header('Location: /login.php');
    exit;
}
