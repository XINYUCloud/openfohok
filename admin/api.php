<?php

// ── 时长格式化 ─────────────────────────────────────────────
function formatDurationText(int $minutes): string {
    if ($minutes < 60) return $minutes . " 分钟";
    if ($minutes < 1440) return floor($minutes/60) . " 小时";
    if ($minutes < 43200) return floor($minutes/1440) . " 天";
    if ($minutes < 525600) return floor($minutes/43200) . " 个月";
    return floor($minutes/525600) . " 年";
}

require_once __DIR__ . './../bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
$admin  = requireAdmin();
$db     = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 只读操作可用 GET；其余一律 POST + 同源，防 CSRF
$readActions = [
    'stats', 'projects', 'users', 'app_updates', 'announcements',
    'users_simple', 'notifications', 'reports', 'user_restrictions', 'version_history',
];
if (!in_array($action, $readActions, true)) {
    securityRequirePost();
}

// 站点自建 OAuth 授权服务已下线，相关管理操作一并停用
if (in_array($action, ['create_oauth_app', 'edit_oauth_app', 'toggle_oauth_app', 'delete_oauth_app'], true)) {
    http_response_code(410);
    echo json_encode(['ok' => false, 'msg' => '站点自建 OAuth 服务已下线，应用统一接入文谶账户']);
    exit;
}

// ═════════════════════════════════════════════════════════════
//  邮件异步发送机制
//  解决：审核操作等因同步等待邮件发送导致响应慢的问题
//  原理：先返回 HTTP 响应，再通过 fastcgi_finish_request +
//        register_shutdown_function 在后台发送邮件
// ═════════════════════════════════════════════════════════════
$emailQueue = [];

// 脚本结束时执行排队的邮件任务
register_shutdown_function(function () {
    global $emailQueue;
    if (empty($emailQueue)) return;
    @ignore_user_abort(true);
    @set_time_limit(60);
    foreach ($emailQueue as $cb) {
        try { $cb(); } catch (\Throwable $e) {}
    }
    $emailQueue = [];
});

/** 将邮件/通知发送任务加入异步队列（不阻塞当前请求） */
function deferEmail(callable $cb): void {
    global $emailQueue;
    $emailQueue[] = $cb;
}

/** 输出 JSON 并立即结束 HTTP 请求，后续邮件在后台发送 */
function jsonExit(array $data): void {
    echo json_encode($data);
    while (ob_get_level()) ob_end_flush();
    flush();
    // PHP-FPM 环境：立即向客户端发送响应，脚本继续运行
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
}

// ── 统计 ──────────────────────────────────────────────────
if ($action === 'stats') {
    $users    = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $total    = (int)$db->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    $pending  = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='pending'")->fetchColumn();
    $approved = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='approved'")->fetchColumn();
    echo json_encode(['ok'=>true,'users'=>$users,'total'=>$total,'pending'=>$pending,'approved'=>$approved]);
    exit;
}

// ── 项目列表 ──────────────────────────────────────────────
if ($action === 'projects') {
    $status  = $_GET['status'] ?? 'pending';
    $allowed = ['pending','approved','rejected','taken_down'];
    if (!in_array($status, $allowed)) $status = 'pending';
    $stmt = $db->prepare(
        'SELECT p.*, u.name AS author_name, u.picture AS author_pic, u.email AS author_email
         FROM projects p JOIN users u ON p.user_id = u.id
         WHERE p.status = ?
         ORDER BY p.created_at DESC LIMIT 500'
    );
    $stmt->execute([$status]);
    echo json_encode(['ok'=>true,'projects'=>$stmt->fetchAll()]);
    exit;
}

// ── 通过项目 ──────────────────────────────────────────────
if ($action === 'approve') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }

    $stmt = $db->prepare(
        'SELECT p.*, u.name AS uname, u.email AS uemail FROM projects p
         JOIN users u ON p.user_id=u.id WHERE p.id=?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['ok'=>false,'msg'=>'项目不存在']); exit; }

    // 判断是否首次审核通过：approved_at 为空说明从未通过审核
    $alreadyNotified = !empty($row['approved_at']);

    // 首次通过设置 approved_at，后续保持原值
    $db->prepare(
        $alreadyNotified
            ? "UPDATE projects SET status='approved', reject_reason=NULL WHERE id=?"
            : "UPDATE projects SET status='approved', reject_reason=NULL, approved_at=NOW() WHERE id=?"
    )->execute([$id]);

    // 邮件 + 订阅者通知全部异步化，不阻塞响应
    $uname   = $row['uname'];
    $uemail  = $row['uemail'];
    $projId  = $id;
    $projName = $row['name'];
    $devId    = (int)$row['user_id'];

    if ($uemail) {
        deferEmail(function () use ($uname, $uemail, $projName) {
            @sendProjectApproved(
                ['name'=>$uname, 'email'=>$uemail],
                ['name'=>$projName]
            );
        });
    }
    // 站内通知（纯 DB 操作，很快）
    createNotification($devId, 'project_approved', "项目「{$projName}」审核通过", "恭喜！您的项目「{$projName}」已通过审核，现已在项目广场中展示。", $projId);
    // 订阅者通知（可能发多封邮件）也异步
    if (!$alreadyNotified) {
        deferEmail(function () use ($devId, $uname, $projId, $projName) {
            notifySubscribersNewProject($devId, $uname, $projId, $projName);
        });
    } else {
        deferEmail(function () use ($devId, $uname, $projId, $projName) {
            notifySubscribersProjectUpdate($devId, $uname, $projId, $projName);
        });
    }
    jsonExit(['ok'=>true]);
}

// ── 拒绝项目（发邮件 → 更新状态）────────────────────────
if ($action === 'reject') {
    $id     = (int)($_POST['id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if (!$id || !$reason) { echo json_encode(['ok'=>false,'msg'=>'请填写拒绝原因']); exit; }

    $stmt = $db->prepare(
        'SELECT p.*, u.name AS uname, u.email AS uemail FROM projects p
         JOIN users u ON p.user_id=u.id WHERE p.id=?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['ok'=>false,'msg'=>'项目不存在']); exit; }

    // 邮件异步发送
    $uname  = $row['uname'];
    $uemail = $row['uemail'];
    $projName = $row['name'];
    $devId    = (int)$row['user_id'];

    if ($uemail) {
        deferEmail(function () use ($uname, $uemail, $projName, $reason) {
            @sendProjectRejected(
                ['name'=>$uname, 'email'=>$uemail],
                ['name'=>$projName],
                $reason
            );
        });
    }
    // 先更新状态，再返回响应
    $db->prepare("UPDATE projects SET status='rejected', reject_reason=? WHERE id=?")->execute([$reason, $id]);
    // 站内通知
    createNotification($devId, 'project_rejected', "项目「{$projName}」审核未通过", "您的项目「{$projName}」未通过审核。\n\n拒绝原因：{$reason}", $id);
    jsonExit(['ok'=>true]);
}

// ── 管理员删除项目 ─────────────────────────────────────────
// 已通过项目：直接删除，无需原因
// 待审核/已拒绝项目：填写原因/邮件发送
if ($action === 'delete_project') {
    $id     = (int)($_POST['id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }

    $stmt = $db->prepare(
        'SELECT p.*, u.name AS uname, u.email AS uemail FROM projects p
         JOIN users u ON p.user_id=u.id WHERE p.id=?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['ok'=>false,'msg'=>'项目不存在']); exit; }

    $needReason = in_array($row['status'], ['pending', 'rejected']);

    if ($needReason && !$reason) {
        // 前端凭 need_reason 标识弹窗填写原因
        echo json_encode(['ok'=>false,'msg'=>'need_reason']);
        exit;
    }

    $uname   = $row['uname'];
    $uemail  = $row['uemail'];
    $projName = $row['name'];
    $devId    = (int)$row['user_id'];

    if ($needReason && $reason && $uemail) {
        deferEmail(function () use ($uname, $uemail, $projName, $reason) {
            @sendProjectDeletedByAdmin(
                ['name'=>$uname, 'email'=>$uemail],
                ['name'=>$projName],
                $reason
            );
        });
    }

    $db->prepare('DELETE FROM project_versions WHERE project_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);
    // 站内通知
    $deleteMsg = $reason ? "您的项目「{$projName}」已被管理员删除。\n\n删除原因：{$reason}" : "您的项目「{$projName}」已被管理员删除。";
    createNotification($devId, 'project_deleted', "项目「{$projName}」已被删除", $deleteMsg, null);
    jsonExit(['ok'=>true]);
}

// ── 用户列表 ──────────────────────────────────────────────
if ($action === 'users') {
    $stmt = $db->query(
        'SELECT u.id, u.name, u.email, u.picture, u.bio, u.website, u.phone,
                u.xgorb_sub, u.created_at, u.last_login_at,
                (SELECT COUNT(*) FROM projects p WHERE p.user_id=u.id) AS project_count
         FROM users u ORDER BY u.created_at DESC LIMIT 1000'
    );
    echo json_encode(['ok'=>true,'users'=>$stmt->fetchAll()]);
    exit;
}

// ── 编辑用户 ──────────────────────────────────────────────
if ($action === 'edit_user') {
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $bio     = trim($_POST['bio'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');

    if (!$id || !$name) { echo json_encode(['ok'=>false,'msg'=>'姓名不能为空']); exit; }

    if ($email) {
        $dup = $db->prepare('SELECT id FROM users WHERE email=? AND id!=? LIMIT 1');
        $dup->execute([$email, $id]);
        if ($dup->fetchColumn()) {
            echo json_encode(['ok'=>false,'msg'=>'该邮箱已被其他账号使用']);
            exit;
        }
    }

    $db->prepare(
        'UPDATE users SET name=?,email=?,bio=?,website=?,phone=?,updated_at=NOW() WHERE id=?'
    )->execute([$name, $email ?: null, $bio ?: null, $website ?: null, $phone ?: null, $id]);

    echo json_encode(['ok'=>true,'msg'=>'用户信息已更新']);
    exit;
}

// ── 删除用户 ──────────────────────────────────────────────
if ($action === 'delete_user') {
    $id     = (int)($_POST['id'] ?? 0);
    $selfId = (int)($admin['user_id'] ?? $admin['id']);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }
    if ($id === $selfId) { echo json_encode(['ok'=>false,'msg'=>'不能删除自己的账号']); exit; }

    // 级联删除所有关联数据
    $db->prepare('DELETE FROM project_likes WHERE user_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM project_likes WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)')->execute([$id]);
    $db->prepare('DELETE FROM projects WHERE user_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM notifications WHERE liker_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM webauthn_credentials WHERE user_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM user_subscriptions WHERE subscriber_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM user_subscriptions WHERE target_user_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

    echo json_encode(['ok'=>true]);
    exit;
}

// ═════════════════════════════════════════════════════════════
//  App 更新管理
// ═════════════════════════════════════════════════════════════

// ── App 更新列表 ────────────────────────────────────────────
if ($action === 'app_updates') {
    $stmt = $db->query(
        'SELECT id, app_name, version, changelog, package_size, download_url, is_force, created_at, updated_at
         FROM app_updates ORDER BY created_at DESC LIMIT 500'
    );
    echo json_encode(['ok'=>true,'updates'=>$stmt->fetchAll()]);
    exit;
}

// ── 创建 App 更新 ───────────────────────────────────────────
if ($action === 'create_app_update') {
    $appName      = trim($_POST['app_name'] ?? '');
    $version      = trim($_POST['version'] ?? '');
    $changelog    = trim($_POST['changelog'] ?? '');
    $packageSize  = trim($_POST['package_size'] ?? '');
    $downloadUrl  = trim($_POST['download_url'] ?? '');
    $isForce      = (int)($_POST['is_force'] ?? 0);

    if (!$appName || !$version || !$downloadUrl) {
        echo json_encode(['ok'=>false,'msg'=>'App 名称、版本号和下载地址为必填项']);
        exit;
    }
    if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
        echo json_encode(['ok'=>false,'msg'=>'下载地址格式不正确']);
        exit;
    }

    // 检查版本号是否已存在
    $dup = $db->prepare('SELECT id FROM app_updates WHERE version = ? LIMIT 1');
    $dup->execute([$version]);
    if ($dup->fetchColumn()) {
        echo json_encode(['ok'=>false,'msg'=>'该版本号已存在']);
        exit;
    }

    $db->prepare(
        'INSERT INTO app_updates (app_name, version, changelog, package_size, download_url, is_force)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $appName, $version, $changelog ?: null, $packageSize ?: null, $downloadUrl, $isForce,
    ]);

    echo json_encode(['ok'=>true,'msg'=>'版本更新已创建']);
    exit;
}

// ── 编辑 App 更新 ───────────────────────────────────────────
if ($action === 'edit_app_update') {
    $id           = (int)($_POST['id'] ?? 0);
    $appName      = trim($_POST['app_name'] ?? '');
    $version      = trim($_POST['version'] ?? '');
    $changelog    = trim($_POST['changelog'] ?? '');
    $packageSize  = trim($_POST['package_size'] ?? '');
    $downloadUrl  = trim($_POST['download_url'] ?? '');
    $isForce      = (int)($_POST['is_force'] ?? 0);

    if (!$id || !$appName || !$version || !$downloadUrl) {
        echo json_encode(['ok'=>false,'msg'=>'App 名称、版本号和下载地址为必填项']);
        exit;
    }
    if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
        echo json_encode(['ok'=>false,'msg'=>'下载地址格式不正确']);
        exit;
    }

    // 检查版本号是否被其他记录使用
    $dup = $db->prepare('SELECT id FROM app_updates WHERE version = ? AND id != ? LIMIT 1');
    $dup->execute([$version, $id]);
    if ($dup->fetchColumn()) {
        echo json_encode(['ok'=>false,'msg'=>'该版本号已被其他更新记录使用']);
        exit;
    }

    $db->prepare(
        'UPDATE app_updates SET
            app_name=?, version=?, changelog=?, package_size=?, download_url=?, is_force=?
         WHERE id=?'
    )->execute([
        $appName, $version, $changelog ?: null, $packageSize ?: null, $downloadUrl, $isForce, $id,
    ]);

    echo json_encode(['ok'=>true,'msg'=>'版本更新已保存']);
    exit;
}

// ── 删除 App 更新 ───────────────────────────────────────────
if ($action === 'delete_app_update') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }

    $db->prepare('DELETE FROM app_updates WHERE id=?')->execute([$id]);
    echo json_encode(['ok'=>true,'msg'=>'已删除']);
    exit;
}

// ═════════════════════════════════════════════════════════════
//  公告管理
// ═════════════════════════════════════════════════════════════

// ── 公告列表 ────────────────────────────────────────────
if ($action === 'announcements') {
    $stmt = $db->query(
        'SELECT id, title, content, severity, created_at, updated_at
         FROM announcements ORDER BY created_at DESC LIMIT 500'
    );
    echo json_encode(['ok'=>true,'announcements'=>$stmt->fetchAll()]);
    exit;
}

// ── 创建公告 ────────────────────────────────────────────
if ($action === 'create_announcement') {
    $title    = trim($_POST['title'] ?? '');
    $content  = trim($_POST['content'] ?? '');
    $severity = $_POST['severity'] ?? 'notice';

    if (!$title || !$content) {
        echo json_encode(['ok'=>false,'msg'=>'标题和内容为必填项']);
        exit;
    }
    $allowed = ['notice','info','important','warning'];
    if (!in_array($severity, $allowed)) $severity = 'notice';

    $db->prepare(
        'INSERT INTO announcements (title, content, severity) VALUES (?, ?, ?)'
    )->execute([$title, $content, $severity]);

    echo json_encode(['ok'=>true,'msg'=>'公告已创建']);
    exit;
}

// ── 编辑公告 ────────────────────────────────────────────
if ($action === 'edit_announcement') {
    $id       = (int)($_POST['id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $content  = trim($_POST['content'] ?? '');
    $severity = $_POST['severity'] ?? 'notice';

    if (!$id || !$title || !$content) {
        echo json_encode(['ok'=>false,'msg'=>'标题和内容为必填项']);
        exit;
    }
    $allowed = ['notice','info','important','warning'];
    if (!in_array($severity, $allowed)) $severity = 'notice';

    $db->prepare(
        'UPDATE announcements SET title=?, content=?, severity=? WHERE id=?'
    )->execute([$title, $content, $severity, $id]);

    echo json_encode(['ok'=>true,'msg'=>'公告已保存']);
    exit;
}

// ── 删除公告 ────────────────────────────────────────────
if ($action === 'delete_announcement') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }

    $db->prepare('DELETE FROM announcements WHERE id=?')->execute([$id]);
    echo json_encode(['ok'=>true,'msg'=>'已删除']);
    exit;
}

// ── 站内信：获取用户列表（选择发送对象）───────────────
if ($action === 'users_simple') {
    $stmt = $db->query('SELECT id, name, email FROM users ORDER BY id DESC LIMIT 500');
    echo json_encode(['ok'=>true,'users'=>$stmt->fetchAll()]);
    exit;
}

// ── 站内信：发送通知给指定用户（支持单用户或多用户） ────
if ($action === 'send_notification') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if (!$title) {
        echo json_encode(['ok'=>false,'msg'=>'请填写标题']);
        exit;
    }

    // 支持单用户 (user_id) 或多用户 (user_ids, 逗号分隔)
    $userIds = [];
    if (!empty($_POST['user_ids'])) {
        $userIds = array_map('intval', array_filter(explode(',', $_POST['user_ids'])));
    } elseif (!empty($_POST['user_id'])) {
        $userIds = [(int)$_POST['user_id']];
    }

    // 如果 user_id 为 0，表示发送给所有用户
    $sendAll = (int)($_POST['user_id'] ?? 0) === 0 && empty($_POST['user_ids']);

    if ($sendAll) {
        $allUsers = $db->query('SELECT id FROM users')->fetchAll();
        $userIds = array_map(fn($u) => (int)$u['id'], $allUsers);
    }

    if (empty($userIds)) {
        echo json_encode(['ok'=>false,'msg'=>'请选择至少一个用户']);
        exit;
    }

    $count = 0;
    foreach ($userIds as $uid) {
        createNotification($uid, 'admin_message', $title, $content, null);
        $count++;
    }

    echo json_encode(['ok'=>true,'msg'=>"通知已发送给 {$count} 位用户", 'sent_count' => $count]);
    exit;
}

// ── 站内信：获取通知列表 ──────────────────────────────────
if ($action === 'notifications') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 30;
    $offset = ($page - 1) * $limit;
    $stmt = $db->prepare(
        'SELECT n.*, u.name AS user_name, u.email AS user_email
         FROM notifications n
         JOIN users u ON n.user_id = u.id
         ORDER BY n.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);
    $notifications = $stmt->fetchAll();
    $total = (int)$db->query('SELECT COUNT(*) FROM notifications')->fetchColumn();

    foreach ($notifications as &$n) {
        $n['is_read'] = (bool)$n['is_read'];
    }
    unset($n);

    echo json_encode(['ok'=>true,'notifications'=>$notifications,'total'=>$total,'page'=>$page]);
    exit;
}

// ── 站内信：删除通知 ──────────────────────────────────────
if ($action === 'delete_notification') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }
    $db->prepare('DELETE FROM notifications WHERE id=?')->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}


// ── 举报列表 ──────────────────────────────────────────────
if ($action === 'reports') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $stmt = $db->prepare(
        'SELECT r.*, p.name AS project_name, p.user_id AS author_id, u.name AS reporter_name, u.email AS reporter_email, u2.name AS author_name
         FROM reports r
         LEFT JOIN projects p ON r.project_id = p.id
         LEFT JOIN users u ON r.reporter_id = u.id
         LEFT JOIN users u2 ON p.user_id = u2.id
         ORDER BY FIELD(r.status, "pending", "resolved", "dismissed"), r.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);
    $reports = $stmt->fetchAll();
    $total = (int)$db->query('SELECT COUNT(*) FROM reports')->fetchColumn();
    echo json_encode(['ok'=>true,'reports'=>$reports,'total'=>$total,'page'=>$page]);
    exit;
}

// ── 处理举报（通过/驳回） ──────────────────────────────────
if ($action === 'resolve_report') {
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $note   = trim($_POST['note'] ?? '');
    $adminId = (int)($admin['user_id'] ?? $admin['id']);
    if (!$id || !in_array($status, ['resolved', 'dismissed'])) {
        echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit;
    }
    $stmt = $db->prepare('SELECT r.*, p.name AS project_name, p.user_id AS author_id, u.name AS author_name, u.email AS author_email FROM reports r LEFT JOIN projects p ON r.project_id = p.id LEFT JOIN users u ON p.user_id = u.id WHERE r.id = ?');
    $stmt->execute([$id]);
    $report = $stmt->fetch();
    if (!$report) { echo json_encode(['ok'=>false,'msg'=>'举报不存在']); exit; }

    $resultLabel = $status === 'resolved' ? '已处理（项目已处罚）' : '已驳回（未发现违规）';
    $projectName = $report['project_name'] ?? '已删除的项目';
    $notifyEmail = $report['notify_email'];
    $reporterId  = (int)$report['reporter_id'];
    $projectId   = (int)$report['project_id'];

    // 通知举报人：站内信（DB 操作，很快）
    if ($reporterId) {
        createNotification(
            $reporterId, 'report_result',
            "你举报的项目「{$projectName}」已有处理结果",
            "处理结果：{$resultLabel}\n管理员备注：" . ($note ?: '无'),
            $projectId
        );
    }
    // 通知举报人：邮箱（异步发送）
    if ($notifyEmail) {
        deferEmail(function () use ($notifyEmail, $projectName, $resultLabel, $note) {
            sendReportResultEmail($notifyEmail, $projectName, $resultLabel, $note ?: '无');
        });
    }

    // 驳回自动删除举报记录
    if ($status === 'dismissed') {
        $db->prepare('DELETE FROM reports WHERE id = ?')->execute([$id]);
        jsonExit(['ok'=>true, 'msg'=>'已驳回并删除']);
    } else {
        $db->prepare('UPDATE reports SET status=?, admin_id=?, admin_note=?, resolved_at=NOW() WHERE id=?')->execute([$status, $adminId, $note ?: null, $id]);
        jsonExit(['ok'=>true, 'msg'=>'已标记为已处理']);
    }
}

// ── 限制用户发布/登录 ─────────────────────────────────────
if ($action === 'restrict_user') {
    $userId   = (int)($_POST['user_id'] ?? 0);
    $type     = $_POST['restrict_type'] ?? '';
    $duration = (int)($_POST['duration_minutes'] ?? 0);
    $reason   = trim($_POST['reason'] ?? '');
    $reportId = (int)($_POST['report_id'] ?? 0);
    $adminId  = (int)($admin['user_id'] ?? $admin['id']);
    if (!$userId || !in_array($type, ['publish', 'login']) || $duration <= 0) {
        echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit;
    }
    // 获取用户信息
    $stmt = $db->prepare('SELECT id, name, email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();
    if (!$targetUser) { echo json_encode(['ok'=>false,'msg'=>'用户不存在']); exit; }
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$duration} minutes"));
    // 先清除该用户该类型的已有活跃限制
    $db->prepare('UPDATE user_restrictions SET is_active = 0 WHERE user_id = ? AND restrict_type = ? AND is_active = 1')->execute([$userId, $type]);
    // 写入限制记录
    $db->prepare('INSERT INTO user_restrictions (user_id, restrict_type, duration_minutes, reason, admin_id, report_id, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$userId, $type, $duration, $reason ?: null, $adminId, $reportId ?: null, $expiresAt]);
    // 更新 users 表对应限制字段
    $col = $type === 'login' ? 'login_restricted_until' : 'publish_restricted_until';
    $db->prepare("UPDATE users SET {$col} = ?, restrict_reason = ? WHERE id = ?")->execute([$expiresAt, $reason ?: null, $userId]);
    // 限制登录时清除所有会话强制退出
    if ($type === 'login') {
        $db->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$userId]);
    }
    // 格式化时长文本
    $durText = formatDurationText($duration);
    // 获取项目名
    $pname = '未知项目';
    if ($reportId) {
        $pnameStmt = $db->prepare('SELECT p.name FROM reports r JOIN projects p ON r.project_id = p.id WHERE r.id = ?');
        $pnameStmt->execute([$reportId]);
        $fetched = $pnameStmt->fetchColumn();
        if ($fetched) $pname = $fetched;
    }
    // 通知用户：站内信（DB 操作，很快）
    createRestrictionNotification($userId, $type, $durText, $reason ?: '违反平台规范');
    // 通知用户：邮箱（异步发送）
    $targetEmail = $targetUser['email'];
    $targetName  = $targetUser['name'];
    if ($targetEmail) {
        if ($type === 'publish') {
            deferEmail(function () use ($targetEmail, $targetName, $pname, $durText, $reason) {
                sendRestrictPublishEmail($targetEmail, $targetName, $pname, $durText, $reason ?: '违反平台规范');
            });
        } else {
            deferEmail(function () use ($targetEmail, $targetName, $durText, $reason) {
                sendRestrictLoginEmail($targetEmail, $targetName, $durText, $reason ?: '违反平台规范');
            });
        }
    }
    jsonExit(['ok'=>true,'msg'=>'已限制用户']);
}

// ── 解除用户限制 ──────────────────────────────────────────
if ($action === 'unrestrict_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $type   = $_POST['restrict_type'] ?? '';
    if (!$userId || !in_array($type, ['publish', 'login'])) {
        echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit;
    }
    $col = $type === 'login' ? 'login_restricted_until' : 'publish_restricted_until';
    $db->prepare("UPDATE users SET {$col} = NULL WHERE id = ?")->execute([$userId]);
    $db->prepare('UPDATE user_restrictions SET is_active = 0 WHERE user_id = ? AND restrict_type = ? AND is_active = 1')->execute([$userId, $type]);
    // 仅当两项限制都已解除时才清除 restrict_reason
    $actStmt = $db->prepare('SELECT login_restricted_until, publish_restricted_until FROM users WHERE id = ?');
    $actStmt->execute([$userId]);
    $active = $actStmt->fetch();
    if (!$active['login_restricted_until'] && !$active['publish_restricted_until']) {
        $db->prepare('UPDATE users SET restrict_reason = NULL WHERE id = ?')->execute([$userId]);
    }
    createUnrestrictionNotification($userId, $type);
    echo json_encode(['ok'=>true,'msg'=>'已解除限制']);
    exit;
}

// ── 处罚项目（下架/删除） ─────────────────────────────────
if ($action === 'punish_project') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $punishType = $_POST['punish_type'] ?? '';
    $reportId = (int)($_POST['report_id'] ?? 0);
    if (!$projectId || !in_array($punishType, ['reject', 'delete'])) {
        echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit;
    }
    $stmt = $db->prepare('SELECT id, name, user_id, status FROM projects WHERE id = ?');
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) { echo json_encode(['ok'=>false,'msg'=>'项目不存在']); exit; }

    if ($punishType === 'reject') {
        $db->prepare("UPDATE projects SET status='taken_down', reject_reason='项目违规下架' WHERE id=?")->execute([$projectId]);
        createNotification(
            (int)$project['user_id'], 'project_taken_down',
            "项目「{$project['name']}」已被下架",
            "您的项目「{$project['name']}」因举报核实违规被管理员下架。如有疑问请联系管理员。",
            $projectId
        );
        echo json_encode(['ok'=>true,'msg'=>'项目已下架']);
    } else {
        $db->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        createNotification(
            (int)$project['user_id'], 'project_deleted',
            "项目「{$project['name']}」已被删除",
            "您的项目「{$project['name']}」因举报核实违规被管理员删除。如有疑问请联系管理员。",
            $projectId
        );
        echo json_encode(['ok'=>true,'msg'=>'项目已删除']);
    }
    exit;
}

// ── 一键清除已通过举报 ──────────────────────────────────
if ($action === 'clear_resolved_reports') {
    $db->prepare('DELETE FROM reports WHERE status = ?')->execute(['resolved']);
    $count = $db->query('SELECT ROW_COUNT()')->fetchColumn();
    echo json_encode(['ok'=>true, 'msg'=>'已清除 ' . (int)$count . ' 条已通过举报']);
    exit;
}

// ── 获取用户限制状态 ──────────────────────────────────────
if ($action === 'user_restrictions') {
    $userId = (int)($_GET['user_id'] ?? 0);
    if (!$userId) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }
    $stmt = $db->prepare('SELECT * FROM user_restrictions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
    $stmt->execute([$userId]);
    $records = $stmt->fetchAll();
    $actStmt = $db->prepare('SELECT login_restricted_until, publish_restricted_until, restrict_reason FROM users WHERE id = ?');
    $actStmt->execute([$userId]);
    $active = $actStmt->fetch();
    echo json_encode(['ok'=>true,'records'=>$records,'active'=>$active]);
    exit;
}

// ── 项目版本历史 ──────────────────────────────────────────
if ($action === 'version_history') {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }
    $stmt = $db->prepare(
        'SELECT id, project_id, version, release_date, description, project_url, developer_website, changelog, tag, created_at
         FROM project_versions WHERE project_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$projectId]);
    echo json_encode(['ok'=>true, 'versions' => $stmt->fetchAll()]);
    exit;
}

// ── 删除版本记录 ──────────────────────────────────────────
if ($action === 'delete_version') {
    $versionId = (int)($_POST['id'] ?? 0);
    if (!$versionId) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }
    $db->prepare('DELETE FROM project_versions WHERE id = ?')->execute([$versionId]);
    echo json_encode(['ok'=>true, 'msg'=>'版本记录已删除']);
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'未知操作']);
