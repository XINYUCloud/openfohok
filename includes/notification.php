<?php
// ============================================================
// 站内信通知辅助函数
// ============================================================

/**
 * 站内信转发队列：响应返回后再推送到账号中心，避免外部接口拖慢页面
 */
function queueWenchanForward(int $userId, string $title, string $content): void {
    if (!function_exists('forwardToWenchanAccount')) return;

    $GLOBALS['__wenchanForwardQueue'][] = [$userId, $title, $content];
    if (!empty($GLOBALS['__wenchanForwardRegistered'])) return;
    $GLOBALS['__wenchanForwardRegistered'] = true;

    register_shutdown_function(function () {
        $queue = $GLOBALS['__wenchanForwardQueue'] ?? [];
        if ($queue === []) return;
        $GLOBALS['__wenchanForwardQueue'] = [];

        @ignore_user_abort(true);
        @set_time_limit(30);
        if (function_exists('fastcgi_finish_request')) {
            while (ob_get_level() > 0) { @ob_end_flush(); }
            @fastcgi_finish_request();
        }
        foreach ($queue as $item) {
            try {
                forwardToWenchanAccount((int)$item[0], (string)$item[1], (string)$item[2]);
            } catch (\Throwable) {}
        }
    });
}

/**
 * 创建一条站内信通知
 * 若该用户开启了「站内信转发」，则在响应结束后异步转发至其文谶账户中心（尽力而为）
 */
function createNotification(int $userId, string $type, string $title, string $content = '', ?int $relatedId = null, ?int $likerId = null): void {
    try {
        $db = getDB();
        $db->prepare(
            'INSERT INTO notifications (user_id, type, title, content, related_id, liker_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $type, $title, $content, $relatedId, $likerId]);

        queueWenchanForward($userId, $title, $content);
    } catch (\Throwable) {}
}

// ── 举报/限制相关通知 ────────────────────────────────────

/**
 * 被举报项目作者收到通知
 */
function createReportNotifyAuthor(int $authorId, string $projectName, string $reportTypeLabel): void {
    $types = [
        'spam' => '垃圾信息',
        'violation' => '违规内容',
        'copyright' => '侵权问题',
        'misleading' => '虚假误导',
        'other' => '其他问题'
    ];
    $label = $types[$reportTypeLabel] ?? $reportTypeLabel;
    createNotification(
        $authorId, 'project_reported',
        "你的项目「{$projectName}」收到举报",
        "你的项目因「{$label}」被用户举报，请检查项目内容是否符合平台规范。如有疑问请联系管理员。",
        null
    );
}

/**
 * 作者被限制后通知
 */
function createRestrictionNotification(int $userId, string $restrictType, string $durationText, string $reason): void {
    $typeLabel = $restrictType === 'login' ? '登录' : '发布';
    createNotification(
        $userId, 'restricted',
        "你的账号已被限制{$typeLabel}",
        "限制时长：{$durationText}\n原因：{$reason}\n如有疑问请联系管理员。",
        null
    );
}

/**
 * 作者被解除限制后通知
 */
function createUnrestrictionNotification(int $userId, string $restrictType): void {
    $typeLabel = $restrictType === 'login' ? '登录' : '发布';
    createNotification(
        $userId, 'restricted',
        "你的账号{$typeLabel}限制已解除",
        "你的账号{$typeLabel}限制已被管理员解除，感谢你的配合。",
        null
    );
}

/**
 * 被订阅开发者重新提交审核通过（项目更新）时，通知所有订阅者
 * 使用 new_project 类型但文案为"项目更新"
 */
function notifySubscribersProjectUpdate(int $developerId, string $developerName, int $projectId, string $projectName): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT us.subscriber_id, u.email, u.name
             FROM user_subscriptions us
             JOIN users u ON u.id = us.subscriber_id
             WHERE us.target_user_id = ?'
        );
        $stmt->execute([$developerId]);
        $subscribers = $stmt->fetchAll();

        if (empty($subscribers)) return;

        $notifyTitle = htmlspecialchars($developerName) . ' 更新了项目';
        $notifyContent = '项目「' . htmlspecialchars($projectName) . '」发布了更新，快去看看吧。';

        foreach ($subscribers as $sub) {
            // 站内信
            createNotification(
                (int)$sub['subscriber_id'], 'new_project',
                $notifyTitle, $notifyContent, $projectId
            );
            // 邮件通知
            if (!empty($sub['email'])) {
                sendSubscriberProjectUpdateEmail(
                    $sub['email'],
                    $sub['name'] ?? '用户',
                    $developerName,
                    $projectName,
                    $projectId
                );
            }
        }
    } catch (\Throwable) {}
}

/**
 * 创建点赞通知
 */
function createLikeNotification(int $ownerUserId, int $likerId, int $projectId, string $projectName, string $likerName): void {
    try {
        $db = getDB();
        // 检查是否已有同一 liker 对同一 project 的 project_liked 通知
        $check = $db->prepare(
            'SELECT id FROM notifications
             WHERE user_id = ? AND type = \'project_liked\' AND related_id = ? AND liker_id = ?'
        );
        $check->execute([$ownerUserId, $projectId, $likerId]);
        $existing = $check->fetch();

        if ($existing) {
            // 已有通知，更新时间 + 重置为未读
            $db->prepare(
                'UPDATE notifications SET is_read = 0, created_at = NOW(), title = ?, content = ?
                 WHERE id = ?'
            )->execute([
                "{$likerName} 点赞了你的项目",
                "{$likerName} 点赞了你的项目「{$projectName}」",
                $existing['id']
            ]);
        } else {
            // 新通知
            createNotification(
                $ownerUserId,
                'project_liked',
                "{$likerName} 点赞了你的项目",
                "{$likerName} 点赞了你的项目「{$projectName}」",
                $projectId,
                $likerId
            );
        }
    } catch (\Throwable) {}
}

/**
 * 清理 30 天前的通知
 */
function cleanOldNotifications(): void {
    try {
        getDB()->exec('DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    } catch (\Throwable) {}
}

/**
 * 获取用户未读通知数量
 */
function getUnreadCount(int $userId): int {
    try {
        cleanOldNotifications();
        $stmt = getDB()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (\Throwable) {
        return 0;
    }
}

// ── 订阅相关通知 ──────────────────────────────────────────

/**
 * 创建「新订阅者」通知（被订阅者收到）
 * 防重复：同一 subscriber 对同一 target 只保留一条未读通知
 */
function createNewSubscriberNotification(int $targetUserId, int $subscriberId, string $subscriberName): void {
    try {
        $db = getDB();
        $check = $db->prepare(
            'SELECT id FROM notifications
             WHERE user_id = ? AND type = \'new_subscriber\' AND liker_id = ?'
        );
        $check->execute([$targetUserId, $subscriberId]);
        if ($check->fetch()) {
            // 已有未读通知则更新时间
            $db->prepare(
                'UPDATE notifications SET created_at = NOW(), is_read = 0 WHERE user_id = ? AND type = \'new_subscriber\' AND liker_id = ?'
            )->execute([$targetUserId, $subscriberId]);
            return;
        }
        createNotification($targetUserId, 'new_subscriber',
            htmlspecialchars($subscriberName) . ' 订阅了你',
            htmlspecialchars($subscriberName) . ' 已成为你的订阅者，TA 将在你发布新项目时收到通知。',
            null, $subscriberId);
    } catch (\Throwable) {}
}

/**
 * 被订阅开发者发布新项目时，通知所有订阅者
 * 同时发送邮件通知
 */
function notifySubscribersNewProject(int $developerId, string $developerName, int $projectId, string $projectName): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT us.subscriber_id, u.email, u.name
             FROM user_subscriptions us
             JOIN users u ON u.id = us.subscriber_id
             WHERE us.target_user_id = ?'
        );
        $stmt->execute([$developerId]);
        $subscribers = $stmt->fetchAll();

        if (empty($subscribers)) return;

        $notifyTitle = htmlspecialchars($developerName) . ' 发布了新项目';
        $notifyContent = '项目「' . htmlspecialchars($projectName) . '」已发布，快去看看吧。';

        foreach ($subscribers as $sub) {
            // 站内信
            createNotification(
                (int)$sub['subscriber_id'], 'new_project',
                $notifyTitle, $notifyContent, $projectId
            );
            // 邮件通知
            if (!empty($sub['email'])) {
                sendSubscriberProjectEmail(
                    $sub['email'],
                    $sub['name'] ?? '用户',
                    $developerName,
                    $projectName,
                    $projectId
                );
            }
        }
    } catch (\Throwable) {}
}