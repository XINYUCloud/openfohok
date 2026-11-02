<?php
// ============================================================
// 站内信转发（通知通道）模块
//
// 功能：当用户在「个人中心 → 安全设置」开启「站内信转发」后，
// 本站在为该用户创建站内信（通知）时，会通过账号中心的
// /oauth/notify.php 通知通道，将站内信同步转发至文谶账号中心
// (account.fohok.xin)，便于用户统一查看。
//
// 依赖配置：WENCHAN_OAUTH_APP_ID / APP_SECRET / NOTIFY_URL /
//           REFRESH_URL / SCOPE
// ============================================================

if (!function_exists('ensureForwardSchema')) {

    /**
     * 确保 users 表包含站内信转发所需的字段（幂等，重复调用安全）
     * 采用信息架构查询，仅当字段缺失时执行 ALTER，避免对已有环境产生副作用。
     */
    function ensureForwardSchema(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $db  = getDB();
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
                'wenchan_access_token'     => "ALTER TABLE users ADD COLUMN wenchan_access_token VARCHAR(128) NULL DEFAULT NULL",
                'wenchan_refresh_token'    => "ALTER TABLE users ADD COLUMN wenchan_refresh_token VARCHAR(128) NULL DEFAULT NULL",
                'wenchan_token_expires_at' => "ALTER TABLE users ADD COLUMN wenchan_token_expires_at DATETIME NULL DEFAULT NULL",
                // 账号中心把 access_token 与本次授权 state 绑定，读取站内信必须原样回传
                'wenchan_oauth_state'      => "ALTER TABLE users ADD COLUMN wenchan_oauth_state VARCHAR(64) NULL DEFAULT NULL",
                'inbox_forward_enabled'    => "ALTER TABLE users ADD COLUMN inbox_forward_enabled TINYINT(1) NOT NULL DEFAULT 0",
            ];
            foreach ($wanted as $name => $sql) {
                if (!isset($existing[$name])) {
                    $db->exec($sql);
                }
            }
        } catch (\Throwable) {
            // 建表失败不阻断业务，对应功能自动失效
        }
    }

    /**
     * 读取用户的文谶 OAuth 令牌与开关信息
     */
    function getWenchanTokens(int $userId): array {
        try {
            $stmt = getDB()->prepare(
                'SELECT wenchan_access_token, wenchan_refresh_token, wenchan_token_expires_at, wenchan_oauth_state
                 FROM users WHERE id = ?'
            );
            $stmt->execute([$userId]);
            return $stmt->fetch() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 保存用户的文谶 OAuth 令牌
     */
    function saveWenchanTokens(int $userId, ?string $accessToken, ?string $refreshToken, ?string $tokenExpiresAt = null, ?string $oauthState = null): void {
        try {
            getDB()->prepare(
                'UPDATE users SET
                    wenchan_access_token     = ?,
                    wenchan_refresh_token    = ?,
                    wenchan_token_expires_at = ?,
                    wenchan_oauth_state      = ?
                 WHERE id = ?'
            )->execute([$accessToken, $refreshToken, $tokenExpiresAt, $oauthState, $userId]);
        } catch (\Throwable) {}
    }

    /**
     * 用户是否开启了站内信转发
     */
    function isInboxForwardEnabled(int $userId): bool {
        try {
            $stmt = getDB()->prepare('SELECT inbox_forward_enabled FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 设置/关闭站内信转发开关
     */
    function setInboxForwardEnabled(int $userId, bool $enabled): void {
        try {
            getDB()->prepare('UPDATE users SET inbox_forward_enabled = ? WHERE id = ?')
                ->execute([$enabled ? 1 : 0, $userId]);
        } catch (\Throwable) {}
    }

    /**
     * 通过账号中心 refresh 端点续期 access_token
     * 返回新的 access_token；失败返回空串
     */
    function refreshWenchanToken(int $userId): string {
        $tokens = getWenchanTokens($userId);
        $refresh = $tokens['wenchan_refresh_token'] ?? '';
        if ($refresh === '') return '';

        $ch = curl_init(WENCHAN_OAUTH_REFRESH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => http_build_query([
                'refresh_token' => $refresh,
                'app_id'        => WENCHAN_OAUTH_APP_ID,
                'app_secret'    => WENCHAN_OAUTH_APP_SECRET,
            ]),
            CURLOPT_HTTPHEADER      => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_CONNECTTIMEOUT  => 4,
            CURLOPT_TIMEOUT         => 8,
            CURLOPT_FOLLOWLOCATION  => false,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        if ($resp === false) return '';

        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data['ok']) || empty($data['access_token'])) {
            return '';
        }

        $newExpiresAt = null;
        if (!empty($data['expires_in'])) {
            $newExpiresAt = date('Y-m-d H:i:s', time() + (int)$data['expires_in']);
        }
        saveWenchanTokens(
            $userId,
            $data['access_token'],
            $data['refresh_token'] ?? $refresh,
            $newExpiresAt,
            (string)($data['state'] ?? ($tokens['wenchan_oauth_state'] ?? '')) ?: null
        );
        return $data['access_token'];
    }

    /**
     * 将一条站内信转发至文谶账号中心（通知通道）
     * 仅在用户开启转发、持有有效 access_token 时推送；任何失败静默忽略，不阻塞主流程。
     *
     * @param int    $userId  本站用户 ID
     * @param string $title   站内信标题（1-120 字符）
     * @param string $content 站内信正文（1-2000 字符）
     */
    function forwardToWenchanAccount(int $userId, string $title, string $content): void {
        try {
            ensureForwardSchema();
            if (!isInboxForwardEnabled($userId)) return;

            $tokens = getWenchanTokens($userId);
            $access = $tokens['wenchan_access_token'] ?? '';
            if ($access === '') return;

            // access_token 过期时尝试静默续期
            if (!empty($tokens['wenchan_token_expires_at']) && strtotime($tokens['wenchan_token_expires_at']) < time()) {
                $access = refreshWenchanToken($userId);
                if ($access === '') return;
            }

            $ch = curl_init(WENCHAN_OAUTH_NOTIFY_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST            => true,
                CURLOPT_POSTFIELDS      => http_build_query([
                    'app_id'     => WENCHAN_OAUTH_APP_ID,
                    'app_secret' => WENCHAN_OAUTH_APP_SECRET,
                    'access_token' => $access,
                    'title'      => mb_substr((string)$title, 0, 120),
                    'content'    => mb_substr((string)$content, 0, 2000),
                ]),
                CURLOPT_HTTPHEADER      => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_CONNECTTIMEOUT  => 3,
                CURLOPT_TIMEOUT         => 5,
                CURLOPT_FOLLOWLOCATION  => false,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable) {
            // 尽力而为，忽略所有异常
        }
    }
}