<?php
// ============================================================
// 文谶账号站内信读取（OAuth scope: notification:read）
//
// 账号中心接口：POST https://account.fohok.xin/oauth/notifications.php
// 必须同时携带 access_token 与该次授权绑定的 state；
// 且开发者已开通「通知订阅」、应用已绑定、用户已同意 notification:read。
//
// 配置：config/wenchan.php（不入库、不进更新包）
//   define('WENCHAN_DEVELOPER_ID', 'fhkdev_...');
//   define('WENCHAN_NOTIFICATION_READ_ENABLED', true);
// ============================================================

if (!function_exists('wenchanNotificationsConfig')) {

    function wenchanNotificationsConfig(): void {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        $file = __DIR__ . '/../config/wenchan.php';
        if (is_file($file)) {
            try {
                require $file;
            } catch (\Throwable $e) {
                error_log('[wenchanNotifications] config load failed: ' . $e->getMessage());
            }
        }
        if (!defined('WENCHAN_DEVELOPER_ID')) {
            define('WENCHAN_DEVELOPER_ID', (string)(getenv('WENCHAN_DEVELOPER_ID') ?: ''));
        }
        if (!defined('WENCHAN_NOTIFICATION_READ_ENABLED')) {
            define('WENCHAN_NOTIFICATION_READ_ENABLED', (string)(getenv('WENCHAN_NOTIFICATION_READ_ENABLED') ?: '') === '1');
        }
        if (!defined('WENCHAN_OAUTH_NOTIFICATIONS_URL')) {
            define('WENCHAN_OAUTH_NOTIFICATIONS_URL', (string)(getenv('WENCHAN_OAUTH_NOTIFICATIONS_URL') ?: 'https://account.fohok.xin/oauth/notifications.php'));
        }
    }

    /**
     * 站点是否已启用「读取文谶账号站内信」
     * 需要同时配置开发者编号并打开开关，避免未完成账号中心侧申请时报错。
     */
    function wenchanReadEnabled(): bool {
        wenchanNotificationsConfig();
        return (bool)WENCHAN_NOTIFICATION_READ_ENABLED && trim((string)WENCHAN_DEVELOPER_ID) !== '';
    }

    /**
     * 读取用户的文谶账号站内信列表
     * 返回：ok / notifications / page / casc / total / pages / need_reauth / config_missing / msg
     */
    function wenchanFetchNotifications(int $userId, int $page = 1, int $casc = 10): array {
        wenchanNotificationsConfig();
        ensureForwardSchema();

        $page = max(1, $page);
        $casc = max(1, min(50, $casc));

        if (trim((string)WENCHAN_DEVELOPER_ID) === '') {
            return wenchanNotificationResult(false, [
                'config_missing' => true,
                'msg'            => '站点尚未配置文谶开发者编号',
            ]);
        }

        $tokens = getWenchanTokens($userId);
        $access = (string)($tokens['wenchan_access_token'] ?? '');
        if ($access === '') {
            return wenchanNotificationResult(false, [
                'need_reauth' => true,
                'msg'         => '尚未授权读取文谶账号通知',
            ]);
        }

        if (!empty($tokens['wenchan_token_expires_at'])
            && strtotime((string)$tokens['wenchan_token_expires_at']) < time()) {
            $access = refreshWenchanToken($userId);
            if ($access === '') {
                return wenchanNotificationResult(false, [
                    'need_reauth' => true,
                    'msg'         => '授权已过期，请重新授权',
                ]);
            }
            $tokens = getWenchanTokens($userId);
        }

        return wenchanRequestNotifications($tokens, (string)WENCHAN_DEVELOPER_ID, $page, $casc);
    }

    /**
     * 调用账号中心 notifications.php（不访问本地数据库，便于单独验证）
     */
    function wenchanRequestNotifications(array $tokens, string $developerId, int $page = 1, int $casc = 10): array {
        wenchanNotificationsConfig();

        $access = (string)($tokens['wenchan_access_token'] ?? '');
        $state  = (string)($tokens['wenchan_oauth_state'] ?? '');

        if ($developerId === '') {
            return wenchanNotificationResult(false, [
                'config_missing' => true,
                'msg'            => '站点尚未配置文谶开发者编号',
            ]);
        }
        if ($access === '' || $state === '') {
            return wenchanNotificationResult(false, [
                'need_reauth' => true,
                'msg'         => '当前授权未绑定 state，请重新授权',
            ]);
        }

        $page = max(1, $page);
        $casc = max(1, min(50, $casc));

        $ch = curl_init(WENCHAN_OAUTH_NOTIFICATIONS_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'app_id'       => WENCHAN_OAUTH_APP_ID,
                'developer_id' => $developerId,
                'access_token' => $access,
                'state'        => $state,
                'casc'         => $casc,
                'page'         => $page,
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $resp   = curl_exec($ch);
        $err    = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $err !== '') {
            error_log('[wenchanNotifications] curl error: ' . $err);
            return wenchanNotificationResult(false, ['msg' => '无法连接文谶账号中心，请稍后重试']);
        }

        $data = json_decode((string)$resp, true);
        if (!is_array($data)) {
            error_log('[wenchanNotifications] invalid JSON, HTTP ' . $status);
            return wenchanNotificationResult(false, ['msg' => '文谶账号中心返回数据异常，请稍后重试']);
        }

        if (empty($data['ok'])) {
            $msg = trim((string)($data['msg'] ?? '读取失败'));
            $needReauth = (bool)preg_match(
                '/重新授权|尚未授权 notification:read|未绑定 state|state 校验失败|access_token 无效|access_token 已过期|令牌.*(无效|过期)/u',
                $msg
            );
            $configMissing = (bool)preg_match(
                '/尚未开通通知订阅|未绑定通知订阅权限|开发者编号与应用不匹配|应用不存在|应用尚未通过审核|应用已被禁用|开发者不存在|缺少必要参数/u',
                $msg
            );
            return wenchanNotificationResult(false, [
                'need_reauth'    => $needReauth,
                'config_missing' => $configMissing,
                'msg'            => $msg !== '' ? $msg : '读取失败',
            ]);
        }

        $rows = [];
        foreach ((array)($data['notifications'] ?? []) as $n) {
            if (!is_array($n)) continue;
            $type = (string)($n['type'] ?? 'system');
            if (!in_array($type, ['system', 'developer', 'security', 'app'], true)) {
                $type = 'system';
            }
            $rows[] = [
                'id'            => (int)($n['id'] ?? 0),
                'type'          => $type,
                'title'         => (string)($n['title'] ?? ''),
                'content'       => (string)($n['content'] ?? ''),
                'is_read'       => !empty($n['is_read']),
                'source'        => (string)($n['source'] ?? ''),
                'source_app_id' => (string)($n['source_app_id'] ?? ''),
                'created_at'    => (string)($n['created_at'] ?? ''),
            ];
        }

        return wenchanNotificationResult(true, [
            'notifications' => $rows,
            'page'          => (int)($data['page'] ?? $page),
            'casc'          => (int)($data['casc'] ?? $casc),
            'total'         => (int)($data['total'] ?? 0),
            'pages'         => (int)($data['pages'] ?? 0),
        ]);
    }

    function wenchanNotificationResult(bool $ok, array $extra = []): array {
        return array_merge([
            'ok'             => $ok,
            'notifications'  => [],
            'page'           => 1,
            'casc'           => 10,
            'total'          => 0,
            'pages'          => 0,
            'need_reauth'    => false,
            'config_missing' => false,
            'msg'            => '',
        ], $extra);
    }
}
