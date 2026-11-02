<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$user       = getCurrentUser();   // 可能为 null（游客）
$isLoggedIn = $user !== null;
$action     = $_POST['action'] ?? $_GET['action'] ?? '';
$db         = getDB();

/** 外部链接仅允许 http(s) 或站内绝对路径，避免 javascript: 等伪协议 */
function projectSafeUrl(string $url): bool {
    if ($url === '') return true;
    if ($url[0] === '/') return !(isset($url[1]) && $url[1] === '/');
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && preg_match('#^https?://#i', $url) === 1;
}

// 会改变状态的操作只接受 POST + 同源请求
if (in_array($action, [
    'submit', 'update', 'delete', 'toggle_hide', 'like',
    'publish_new_version', 'screenshot_request', 'screenshot_clear', 'import_repo',
], true)) {
    securityRequirePost();
}

// ── 检查发布限制（登录用户） ────────────────────────────────
if ($isLoggedIn && in_array($action, ['submit', 'update', 'publish_new_version'])) {
    $uid = $user['user_id'] ?? $user['id'];
    $chk = $db->prepare('SELECT publish_restricted_until, restrict_reason FROM users WHERE id = ?');
    $chk->execute([$uid]);
    $r = $chk->fetch();
    if ($r['publish_restricted_until'] && strtotime($r['publish_restricted_until']) > time()) {
        echo json_encode(['ok' => false, 'msg' => '你的账号已被限制发布至 ' . $r['publish_restricted_until'] . '，原因：' . ($r['restrict_reason'] ?: '违反平台规范')]);
        exit;
    }
}

// ── 提交项目──────────────────────────
if ($action === 'submit') {
    // 极验服务端验证
    $captchaResult = verifyCaptcha($_POST);
    if (!$captchaResult['ok']) {
        echo json_encode(['ok' => false, 'msg' => $captchaResult['msg']]);
        exit;
    }

    if (!securityRateLimit('project_submit', 5, 600)) {
        echo json_encode(['ok' => false, 'msg' => '提交过于频繁，请稍后再试']);
        exit;
    }

    $name        = trim($_POST['name'] ?? '');
    $version     = trim($_POST['version'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $project_url = trim($_POST['project_url'] ?? '');
    $release_date      = trim($_POST['release_date'] ?? '') ?: null;
    $developer_website = trim($_POST['developer_website'] ?? '') ?: null;
    $contact_email     = trim($_POST['contact_email'] ?? '') ?: null;
    $contact_phone     = trim($_POST['contact_phone'] ?? '') ?: null;
    $contact_qq        = trim($_POST['contact_qq'] ?? '') ?: null;
    $contact_wechat    = trim($_POST['contact_wechat'] ?? '') ?: null;
    $tech_stack        = trim($_POST['tech_stack'] ?? '') ?: null;

    if (!$name || !$version || !$description || !$project_url) {
        echo json_encode(['ok' => false, 'msg' => '请填写必填字段']);
        exit;
    }
    if (mb_strlen($name) > 100 || mb_strlen($version) > 50 || mb_strlen($description) > 5000) {
        echo json_encode(['ok' => false, 'msg' => '项目名称、版本号或简介超出长度限制']);
        exit;
    }
    if (!filter_var($project_url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $project_url)) {
        echo json_encode(['ok' => false, 'msg' => '项目网址需为 http/https 链接']);
        exit;
    }
    if (!projectSafeUrl((string)$developer_website) || !projectSafeUrl(trim($_POST['cover_url'] ?? ''))) {
        echo json_encode(['ok' => false, 'msg' => '开发者网站或封面图链接不合法']);
        exit;
    }

    // 确定 user_id：已登录用真实 ID，游客创建/复用游客账号
    if ($isLoggedIn) {
        $userId       = $user['user_id'] ?? $user['id'];
        $notifyEmail  = $user['email'];
        $notifyName   = $user['name'] ?? '开发者';
        $isGuest      = 0;
    } else {
        $guestName  = trim($_POST['guest_name']  ?? '');
        $guestEmail = trim($_POST['guest_email'] ?? '');
        if (!$guestName || !$guestEmail) {
            echo json_encode(['ok' => false, 'msg' => '请填写提交者昵称和邮箱']);
            exit;
        }
        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'msg' => '邮箱格式不正确']);
            exit;
        }

        if (mb_strlen($guestName) > 40) {
            echo json_encode(['ok' => false, 'msg' => '提交者昵称过长']);
            exit;
        }
        if (!securityRateLimit('guest_submit', 5, 3600)) {
            echo json_encode(['ok' => false, 'msg' => '提交过于频繁，请稍后再试']);
            exit;
        }

        // 只复用未绑定文谶身份的游客记录；真实账号绝不能被游客流程改写
        $chk = $db->prepare(
            "SELECT id FROM users
             WHERE LOWER(email) = LOWER(?)
               AND wenchan_uid IS NULL AND wenchan_sub IS NULL AND xgorb_sub IS NULL
               AND (reg_source = 'guest' OR reg_source IS NULL)
             ORDER BY id ASC LIMIT 1"
        );
        $chk->execute([$guestEmail]);
        $existing = $chk->fetchColumn();

        if ($existing) {
            $userId = (int)$existing;
        } else {
            $db->prepare(
                "INSERT INTO users (xgorb_sub, name, email, reg_source, last_login_at, created_at, updated_at)
                 VALUES (NULL, ?, ?, 'guest', NOW(), NOW(), NOW())"
            )->execute([$guestName, $guestEmail]);
            $userId = (int)$db->lastInsertId();
        }

        $notifyEmail = $guestEmail;
        $notifyName  = $guestName;
        $isGuest     = 1;
    }

    $stmt = $db->prepare(
        'INSERT INTO projects (user_id, name, version, release_date, project_url, description,
            developer_website, contact_email, contact_phone, contact_qq, contact_wechat, tech_stack,
            cover_url, is_guest)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $userId,
        $name, $version, $release_date, $project_url, $description,
        $developer_website, $contact_email, $contact_phone, $contact_qq, $contact_wechat,
        $tech_stack,
        trim($_POST['cover_url'] ?? '') ?: null,
        $isGuest,
    ]);

    $projectId = (int)$db->lastInsertId();

    // 站内通知用户
    $notifyUserId = $userId;
    createNotification($notifyUserId, 'project_submitted', "项目「{$name}」已提交审核", "您的项目「{$name} v{$version}」已成功提交，请耐心等待审核。", $projectId);

    $project = [
        'name'        => $name,
        'version'     => $version,
        'project_url' => $project_url,
        'description' => $description,
    ];
    $notifyUser = ['name' => $notifyName, 'email' => $notifyEmail];

    if ($notifyEmail) @sendProjectSubmittedToUser($notifyUser, $project);
    @sendProjectSubmittedToAdmin($notifyUser, $project);

    echo json_encode(['ok' => true, 'msg' => '提交成功~，请耐心等待审核！']);
    exit;
}

// ── 需要登录的操作：统一校验 ─────────────────────────────
if (in_array($action, ['update', 'delete', 'toggle_hide', 'my_projects', 'like', 'liked_projects', 'publish_new_version', 'version_history'])) {
    if (!$isLoggedIn) {
        echo json_encode(['ok' => false, 'msg' => '请先登录']);
        exit;
    }
}

// ── 更新项目 ──────────────────────────────────────────────
if ($action === 'update') {
    $id          = (int)($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $version     = trim($_POST['version'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $project_url = trim($_POST['project_url'] ?? '');

    if (!$id || !$name || !$version || !$description || !$project_url) {
        echo json_encode(['ok' => false, 'msg' => '参数错误']);
        exit;
    }
    if (mb_strlen($name) > 100 || mb_strlen($version) > 50 || mb_strlen($description) > 5000) {
        echo json_encode(['ok' => false, 'msg' => '项目名称、版本号或简介超出长度限制']);
        exit;
    }
    if (!filter_var($project_url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $project_url)) {
        echo json_encode(['ok' => false, 'msg' => '项目网址需为 http/https 链接']);
        exit;
    }
    foreach (['developer_website', 'cover_url', 'download_url'] as $urlField) {
        if (!projectSafeUrl(trim($_POST[$urlField] ?? ''))) {
            echo json_encode(['ok' => false, 'msg' => '开发者网站、封面或下载链接不合法']);
            exit;
        }
    }

    // 确认是自己的项目
    $userId = $user['user_id'] ?? $user['id'];
    $check  = $db->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
    $check->execute([$id, $userId]);
    if (!$check->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '项目不存在']);
        exit;
    }

    // 保存旧版本到历史记录（仅版本号变更时）
    $old = $db->prepare('SELECT version, release_date, description, project_url, developer_website FROM projects WHERE id = ?');
    $old->execute([$id]);
    $oldRow = $old->fetch();
    if ($oldRow && $oldRow['version'] !== $version) {
        $db->prepare(
            'INSERT INTO project_versions (project_id, version, release_date, description, project_url, developer_website, changelog)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $id,
            $oldRow['version'],
            $oldRow['release_date'],
            $oldRow['description'],
            $oldRow['project_url'],
            $oldRow['developer_website'],
            trim($_POST['changelog'] ?? '') ?: null,
        ]);
    }

    $db->prepare(
        'UPDATE projects SET name=?,version=?,release_date=?,project_url=?,description=?,
            developer_website=?,contact_email=?,contact_phone=?,contact_qq=?,contact_wechat=?,
            tech_stack=?,cover_url=?,download_url=?,download_filename=?,download_filetype=?,download_filesize=?,
            status=\'pending\', updated_at=NOW()
         WHERE id = ? AND user_id = ?'
    )->execute([
        $name, $version,
        trim($_POST['release_date'] ?? '') ?: null,
        $project_url, $description,
        trim($_POST['developer_website'] ?? '') ?: null,
        trim($_POST['contact_email'] ?? '') ?: null,
        trim($_POST['contact_phone'] ?? '') ?: null,
        trim($_POST['contact_qq'] ?? '') ?: null,
        trim($_POST['contact_wechat'] ?? '') ?: null,
        trim($_POST['tech_stack'] ?? '') ?: null,
        trim($_POST['cover_url'] ?? '') ?: null,
        trim($_POST['download_url'] ?? '') ?: null,
        trim($_POST['download_filename'] ?? '') ?: null,
        trim($_POST['download_filetype'] ?? '') ?: null,
        trim($_POST['download_filesize'] ?? '') ?: null,
        $id, $userId,
    ]);

    echo json_encode(['ok' => true, 'msg' => '更新成功，重新进入审核队列']);
    exit;
}

// ── 删除项目 ──────────────────────────────────────────────
if ($action === 'delete') {
    $id     = (int)($_POST['id'] ?? 0);
    $userId = (int)($user['user_id'] ?? $user['id']);
    if (!$id) {
        echo json_encode(['ok' => false, 'msg' => '参数错误']);
        exit;
    }

    // 先确认归属，避免误删他人项目的版本记录
    $check = $db->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
    $check->execute([$id, $userId]);
    if (!$check->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '项目不存在']);
        exit;
    }

    $db->prepare('DELETE FROM project_versions WHERE project_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── 切换隐藏 ──────────────────────────────────────────────
if ($action === 'toggle_hide') {
    $id     = (int)($_POST['id'] ?? 0);
    $userId = (int)($user['user_id'] ?? $user['id']);
    if (!$id) {
        echo json_encode(['ok' => false, 'msg' => '参数错误']);
        exit;
    }
    $upd = $db->prepare(
        'UPDATE projects SET is_hidden = NOT is_hidden WHERE id = ? AND user_id = ?'
    );
    $upd->execute([$id, $userId]);
    if ($upd->rowCount() === 0) {
        echo json_encode(['ok' => false, 'msg' => '项目不存在']);
        exit;
    }
    $row = $db->prepare('SELECT is_hidden FROM projects WHERE id = ? AND user_id = ?');
    $row->execute([$id, $userId]);
    $hidden = (bool)$row->fetchColumn();
    echo json_encode(['ok' => true, 'hidden' => $hidden]);
    exit;
}

// ── 统计数据 ──────────────────────────────────────────────
if ($action === 'stats') {
    $total_projects    = (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    $approved_projects = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='approved' AND is_hidden=0")->fetchColumn();
    $total_developers  = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM projects")->fetchColumn();

    echo json_encode([
        'ok' => true,
        'stats' => [
            'total_projects'    => $total_projects,
            'approved_projects' => $approved_projects,
            'total_developers'  => $total_developers,
        ]
    ]);
    exit;
}

// ── 获取项目列表──────────────────────────────
if ($action === 'list') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;

    $userId = $isLoggedIn ? ($user['user_id'] ?? $user['id']) : null;

    $stmt = $db->prepare(
        'SELECT p.*, u.name AS author_name, u.picture AS author_pic,
                (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id) AS like_count'
         . ($userId ? ', (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id AND user_id = ?) AS is_liked' : '')
        . ' FROM projects p JOIN users u ON p.user_id = u.id
         WHERE p.status = \'approved\' AND p.is_hidden = 0
         ORDER BY p.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $params = $userId ? [$userId, $limit, $offset] : [$limit, $offset];
    $stmt->execute($params);
    $projects = $stmt->fetchAll();

    // 转换 is_liked 为布尔值
    foreach ($projects as &$proj) {
        $proj['like_count'] = (int)$proj['like_count'];
        $proj['is_liked']   = $userId ? (bool)$proj['is_liked'] : false;
    }
    unset($proj);

    $total = (int)$db->query(
        'SELECT COUNT(*) FROM projects WHERE status=\'approved\' AND is_hidden=0'
    )->fetchColumn();

    echo json_encode(['ok' => true, 'projects' => $projects, 'total' => $total, 'page' => $page]);
    exit;
}

// ── 获取项目详情 ──────────────────────────────────────────
if ($action === 'detail') {
    $id   = (int)($_GET['id'] ?? 0);
    $userId = $isLoggedIn ? ($user['user_id'] ?? $user['id']) : null;

    $stmt = $db->prepare(
        'SELECT p.*, u.name AS author_name, u.picture AS author_pic,
                (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id) AS like_count'
         . ($userId ? ', (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id AND user_id = ?) AS is_liked' : '')
        . ' FROM projects p JOIN users u ON p.user_id = u.id
         WHERE p.id = ?'
    );
    $params = $userId ? [$userId, $id] : [$id];
    $stmt->execute($params);
    $project = $stmt->fetch();
    if (!$project) { echo json_encode(['ok' => false]); exit; }

    // 公开页访问控制：非所有者只能看已通过且未隐藏的项目
    if ($project['user_id'] != $userId && ($project['status'] !== 'approved' || $project['is_hidden'])) {
        echo json_encode(['ok' => false]); exit;
    }

    $project['like_count'] = (int)($project['like_count'] ?? 0);
    $project['is_liked']   = $userId ? (bool)($project['is_liked'] ?? false) : false;

    // 更新访问量
    $db->prepare('UPDATE projects SET view_count=view_count+1 WHERE id=?')->execute([$id]);
    echo json_encode(['ok' => true, 'project' => $project]);
    exit;
}

// ── 请求项目截图 ────────────────────────────────────────
if ($action === 'screenshot_request') {
    if (!$isLoggedIn) { echo json_encode(['ok'=>false,'msg'=>'请先登录']); exit; }
    $projectId = (int)($_POST['project_id'] ?? 0);
    $refresh   = ($_POST['refresh'] ?? '') === '1';
    if (!$projectId) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }

    $uid   = (int)($user['user_id'] ?? $user['id']);
    $stmt  = $db->prepare('SELECT project_url, screenshot_task_id, cover_url, user_id FROM projects WHERE id = ?');
    $stmt->execute([$projectId]);
    $proj = $stmt->fetch();
    if (!$proj) { echo json_encode(['ok'=>false,'msg'=>'项目不存在']); exit; }
    // 刷新会消耗配额并覆盖所有人的截图，只允许项目作者操作
    if ($refresh && (int)$proj['user_id'] !== $uid) {
        echo json_encode(['ok'=>false,'msg'=>'只有项目作者可以刷新截图']);
        exit;
    }
    if (!empty($proj['cover_url'])) { echo json_encode(['ok'=>false,'msg'=>'已设置自定义封面图，不支持截图刷新']); exit; }
    if (empty($proj['project_url'])) { echo json_encode(['ok'=>false,'msg'=>'项目未提供网址']); exit; }

    $today = date('Y-m-d');

    // 刷新时检查每日次数限制
    if ($refresh) {
        $cntStmt = $db->prepare('SELECT screenshot_refresh_count, screenshot_refresh_date FROM users WHERE id = ?');
        $cntStmt->execute([$uid]);
        $cnt = $cntStmt->fetch();
        $refreshCount = ($cnt['screenshot_refresh_date'] === $today) ? (int)$cnt['screenshot_refresh_count'] : 0;
        if ($refreshCount >= 3) {
            echo json_encode(['ok'=>false,'msg'=>'今日刷新次数已用完（3/3），请明天再试']);
            exit;
        }
    }

    // 已有截图任务且非刷新，直接返回
    $taskId = $proj['screenshot_task_id'];
    if ($taskId && !$refresh) {
        echo json_encode(['ok'=>true, 'task_id'=>$taskId, 'existing'=>true]);
        exit;
    }

    // 刷新时清除旧任务ID
    if ($refresh && $taskId) {
        $db->prepare('UPDATE projects SET screenshot_task_id = NULL WHERE id = ?')->execute([$projectId]);
    }

    // 刷新时递增计数
    if ($refresh) {
        if ($cnt['screenshot_refresh_date'] === $today) {
            $db->prepare('UPDATE users SET screenshot_refresh_count = screenshot_refresh_count + 1 WHERE id = ?')->execute([$uid]);
        } else {
            $db->prepare('UPDATE users SET screenshot_refresh_count = 1, screenshot_refresh_date = ? WHERE id = ?')->execute([$today, $uid]);
        }
    }

    // 提交截图任务
    $payload = json_encode([
        'url' => $proj['project_url'],
        'email' => ADMIN_EMAILS[0],
        'width' => 1440,
        'height' => 900,
        'full_page' => true,
    ]);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . WEBSCR_API_KEY . "\r\n",
            'content' => $payload,
            'timeout' => 15,
        ],
    ]);
    $raw = @file_get_contents(WEBSCR_API_URL . '/submit.php', false, $ctx);
    if (!$raw) { echo json_encode(['ok'=>false,'msg'=>'截图服务暂不可用']); exit; }
    $result = json_decode($raw, true);
    if (($result['code'] ?? 0) !== 200) {
        echo json_encode(['ok'=>false,'msg'=>$result['message'] ?? '提交失败']);
        exit;
    }
    $taskId = $result['task_id'];
    $db->prepare('UPDATE projects SET screenshot_task_id = ? WHERE id = ?')->execute([$taskId, $projectId]);
    echo json_encode(['ok'=>true, 'task_id'=>$taskId, 'existing'=>false]);
    exit;
}

// ── 清除截图任务ID（图片加载失败时调用） ──────────────────
if ($action === 'screenshot_clear') {
    if (!$isLoggedIn) { echo json_encode(['ok'=>false,'msg'=>'请先登录']); exit; }
    $projectId = (int)($_POST['project_id'] ?? 0);
    if (!$projectId) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }
    $uid = (int)($user['user_id'] ?? $user['id']);
    $db->prepare('UPDATE projects SET screenshot_task_id = NULL WHERE id = ? AND user_id = ?')->execute([$projectId, $uid]);
    echo json_encode(['ok'=>true, 'msg'=>'已清除截图缓存']);
    exit;
}

// ── 查询截图刷新次数 ────────────────────────────────────
if ($action === 'screenshot_refresh_count') {
    if (!$isLoggedIn) { echo json_encode(['ok'=>false,'msg'=>'请先登录']); exit; }
    $uid = $user['user_id'] ?? $user['id'];
    $stmt = $db->prepare('SELECT screenshot_refresh_count, screenshot_refresh_date FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    $today = date('Y-m-d');
    $count = ($row['screenshot_refresh_date'] === $today) ? (int)$row['screenshot_refresh_count'] : 0;
    echo json_encode(['ok'=>true, 'count'=>$count, 'max'=>3]);
    exit;
}

// ── 查询截图状态 ────────────────────────────────────────
if ($action === 'screenshot_status') {
    if (!$isLoggedIn) { echo json_encode(['ok'=>false,'msg'=>'请先登录']); exit; }
    $taskId = trim($_GET['task_id'] ?? '');
    if (!$taskId) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer " . WEBSCR_API_KEY . "\r\n",
            'timeout' => 10,
        ],
    ]);
    $raw = @file_get_contents(WEBSCR_API_URL . '/query.php?id=' . urlencode($taskId), false, $ctx);
    if (!$raw) { echo json_encode(['ok'=>false,'msg'=>'查询失败']); exit; }
    $result = json_decode($raw, true);
    if (($result['code'] ?? 0) !== 200) {
        echo json_encode(['ok'=>false,'msg'=>$result['message'] ?? '查询失败']);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'condition' => $result['condition'],
        'has_image' => $result['has_image'] ?? false,
        'image_url' => ($result['has_image'] ?? false) ? (WEBSCR_API_URL . '/image.php?id=' . urlencode($taskId)) : null,
    ]);
    exit;
}

// ── 用户自己的项目列表 ────────────────────────────────────
if ($action === 'my_projects') {
    $userId = $user['user_id'] ?? $user['id'];
    $stmt   = $db->prepare(
        'SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$userId]);
    echo json_encode(['ok' => true, 'projects' => $stmt->fetchAll()]);
    exit;
}

// ── 点赞 / 取消点赞 ────────────────────────────────────────
if ($action === 'like') {
    $projectId = (int)($_POST['id'] ?? 0);
    if (!$projectId) {
        echo json_encode(['ok' => false, 'msg' => '参数错误']);
        exit;
    }

    $userId = $user['user_id'] ?? $user['id'];

    // 检查项目是否存在
    $check = $db->prepare('SELECT id, user_id, name FROM projects WHERE id = ? AND status=\'approved\' AND is_hidden=0');
    $check->execute([$projectId]);
    $projectRow = $check->fetch();
    if (!$projectRow) {
        echo json_encode(['ok' => false, 'msg' => '项目不存在']);
        exit;
    }

    // 检查是否已点赞
    $stmt = $db->prepare('SELECT id FROM project_likes WHERE project_id = ? AND user_id = ?');
    $stmt->execute([$projectId, $userId]);
    $existing = $stmt->fetch();

    if ($existing) {
        // 取消点赞
        $db->prepare('DELETE FROM project_likes WHERE project_id = ? AND user_id = ?')
           ->execute([$projectId, $userId]);
        $isLiked = false;
    } else {
        // 点赞
        $db->prepare('INSERT INTO project_likes (project_id, user_id) VALUES (?, ?)')
           ->execute([$projectId, $userId]);
        $isLiked = true;

        // 发送站内信通知给项目所有者（不给自己发）
        $ownerUserId = (int)$projectRow['user_id'];
        if ($ownerUserId !== $userId) {
            $likerName = $user['name'] ?? '用户';
            createLikeNotification($ownerUserId, $userId, $projectId, $projectRow['name'], $likerName);
        }
    }

    // 获取最新点赞数
    $countStmt = $db->prepare('SELECT COUNT(*) FROM project_likes WHERE project_id = ?');
    $countStmt->execute([$projectId]);
    $count = (int)$countStmt->fetchColumn();

    echo json_encode(['ok' => true, 'is_liked' => $isLiked, 'like_count' => $count]);
    exit;
}

// ── 用户点赞过的项目列表 ────────────────────────────────────
if ($action === 'liked_projects') {
    $userId = $user['user_id'] ?? $user['id'];
    $stmt   = $db->prepare(
        'SELECT p.*, u.name AS author_name, u.picture AS author_pic,
                (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id) AS like_count
         FROM project_likes pl
         JOIN projects p ON pl.project_id = p.id
         JOIN users u ON p.user_id = u.id
         WHERE pl.user_id = ? AND p.status = \'approved\' AND p.is_hidden = 0
         ORDER BY pl.created_at DESC'
    );
    $stmt->execute([$userId]);
    $projects = $stmt->fetchAll();

    foreach ($projects as &$proj) {
        $proj['like_count'] = (int)$proj['like_count'];
        $proj['is_liked']   = true; // 用户点赞过的必然是 true
    }
    unset($proj);

    echo json_encode(['ok' => true, 'projects' => $projects]);
    exit;
}

// ── 搜索项目 ──────────────────────────────────────────────
if ($action === 'search') {
    // 极验服务端验证
    $captchaResult = verifyCaptcha($_POST);
    if (!$captchaResult['ok']) {
        echo json_encode(['ok' => false, 'msg' => $captchaResult['msg']]);
        exit;
    }

    $keyword = trim($_POST['keyword'] ?? '');
    if (!$keyword) {
        echo json_encode(['ok' => false, 'msg' => '请输入搜索关键词']);
        exit;
    }
    if (mb_strlen($keyword) > 100) {
        echo json_encode(['ok' => false, 'msg' => '关键词过长']);
        exit;
    }

    $like = '%' . $keyword . '%';
    $userId = $isLoggedIn ? ($user['user_id'] ?? $user['id']) : null;

    $stmt = $db->prepare(
        "SELECT p.id, p.user_id, p.name, p.version, p.description, p.project_url,
                p.developer_website, p.contact_email, p.contact_phone,
                p.contact_qq, p.contact_wechat, p.release_date,
                p.view_count, p.created_at, p.is_guest,
                u.name AS author_name, u.picture AS author_pic,
                (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id) AS like_count"
         . ($userId ? ', (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id AND user_id = ?) AS is_liked' : '')
        . " FROM projects p
         JOIN users u ON p.user_id = u.id
         WHERE p.status = 'approved' AND p.is_hidden = 0
           AND (p.name LIKE ? OR p.description LIKE ? OR u.name LIKE ?)
         ORDER BY
           CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END,
           p.created_at DESC
         LIMIT 50"
    );
    $params = $userId
        ? [$userId, $like, $like, $like, $like]
        : [$like, $like, $like, $like];
    $stmt->execute($params);
    $projects = $stmt->fetchAll();

    foreach ($projects as &$proj) {
        $proj['like_count'] = (int)$proj['like_count'];
        $proj['is_liked']   = $userId ? (bool)($proj['is_liked'] ?? false) : false;
    }
    unset($proj);

    echo json_encode(['ok' => true, 'projects' => $projects]);
    exit;
}

// ── 版本历史 ──────────────────────────────────────────────
if ($action === 'version_history') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'msg' => '参数错误']);
        exit;
    }

    // 非所有者仅能查看已公开的项目历史
    $userId = (int)($user['user_id'] ?? $user['id']);
    $vis = $db->prepare('SELECT user_id, status, is_hidden FROM projects WHERE id = ?');
    $vis->execute([$id]);
    $row = $vis->fetch();
    if (!$row) {
        echo json_encode(['ok' => false, 'msg' => '项目不存在']);
        exit;
    }
    if ((int)$row['user_id'] !== $userId && ($row['status'] !== 'approved' || (int)$row['is_hidden'] === 1)) {
        echo json_encode(['ok' => false, 'msg' => '项目不存在']);
        exit;
    }

    $stmt = $db->prepare(
        'SELECT id, version, release_date, description, project_url, developer_website, changelog, tag, created_at
         FROM project_versions WHERE project_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$id]);
    $versions = $stmt->fetchAll();
    echo json_encode(['ok' => true, 'versions' => $versions]);
    exit;
}

// ── 发布新版本 ────────────────────────────────────────────
if ($action === 'publish_new_version') {
    $projectId   = (int)($_POST['project_id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $version     = trim($_POST['version'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $project_url = trim($_POST['project_url'] ?? '');
    $changelog   = trim($_POST['changelog'] ?? '') ?: null;
    $tag         = trim($_POST['tag'] ?? '') ?: null;
    $techStack   = trim($_POST['tech_stack'] ?? '') ?: null;

    if (!$projectId || !$name || !$version || !$description || !$project_url) {
        echo json_encode(['ok' => false, 'msg' => '请填写必填字段']);
        exit;
    }
    if (mb_strlen($name) > 100 || mb_strlen($version) > 50 || mb_strlen($description) > 5000) {
        echo json_encode(['ok' => false, 'msg' => '项目名称、版本号或简介超出长度限制']);
        exit;
    }
    if (!filter_var($project_url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $project_url)) {
        echo json_encode(['ok' => false, 'msg' => '项目网址需为 http/https 链接']);
        exit;
    }
    foreach (['developer_website', 'cover_url', 'download_url'] as $urlField) {
        if (!projectSafeUrl(trim($_POST[$urlField] ?? ''))) {
            echo json_encode(['ok' => false, 'msg' => '开发者网站、封面或下载链接不合法']);
            exit;
        }
    }
    $userId = $user['user_id'] ?? $user['id'];
    $check  = $db->prepare('SELECT id, version, release_date, description, project_url, developer_website FROM projects WHERE id = ? AND user_id = ?');
    $check->execute([$projectId, $userId]);
    $oldRow = $check->fetch();
    if (!$oldRow) {
        echo json_encode(['ok' => false, 'msg' => '项目不存在']);
        exit;
    }

    // 保存旧版本
    $db->prepare(
        'INSERT INTO project_versions (project_id, version, release_date, description, project_url, developer_website, changelog, tag)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $projectId,
        $oldRow['version'],
        $oldRow['release_date'],
        $oldRow['description'],
        $oldRow['project_url'],
        $oldRow['developer_website'],
        $changelog,
        $tag,
    ]);

    // 更新项目
    $releaseDate = trim($_POST['release_date'] ?? '') ?: null;
    $devWebsite  = trim($_POST['developer_website'] ?? '') ?: null;
    $db->prepare(
        'UPDATE projects SET name=?,version=?,release_date=?,project_url=?,description=?,
            developer_website=?,contact_email=?,contact_phone=?,contact_qq=?,contact_wechat=?,
            tech_stack=?,cover_url=?,download_url=?,download_filename=?,download_filetype=?,download_filesize=?,
            status=\'pending\', updated_at=NOW()
         WHERE id = ? AND user_id = ?'
    )->execute([
        $name, $version, $releaseDate, $project_url, $description,
        $devWebsite,
        trim($_POST['contact_email'] ?? '') ?: null,
        trim($_POST['contact_phone'] ?? '') ?: null,
        trim($_POST['contact_qq'] ?? '') ?: null,
        trim($_POST['contact_wechat'] ?? '') ?: null,
        $techStack,
        trim($_POST['cover_url'] ?? '') ?: null,
        trim($_POST['download_url'] ?? '') ?: null,
        trim($_POST['download_filename'] ?? '') ?: null,
        trim($_POST['download_filetype'] ?? '') ?: null,
        trim($_POST['download_filesize'] ?? '') ?: null,
        $projectId, $userId,
    ]);

    createNotification($userId, 'project_submitted', "项目「{$name}」新版本已提交审核", "您的项目「{$name} v{$version}」已提交新版本，请耐心等待审核。", $projectId);
    echo json_encode(['ok' => true, 'msg' => '新版本已提交，重新进入审核队列']);
    exit;
}

// ── 导入仓库信息 ──────────────────────────────────────────
if ($action === 'import_repo') {
    $url = trim($_POST['url'] ?? '');
    if (!$url) {
        echo json_encode(['ok' => false, 'msg' => '请输入仓库地址']);
        exit;
    }
    if (mb_strlen($url) > 300) {
        echo json_encode(['ok' => false, 'msg' => '仓库地址过长']);
        exit;
    }
    if (!securityRateLimit('repo_import', 20, 3600)) {
        echo json_encode(['ok' => false, 'msg' => '操作过于频繁，请稍后再试']);
        exit;
    }
    // 解析 GitHub / Gitee URL
    $repo = null;
    if (preg_match('#github\.com/([^/]+)/([^/]+?)(?:\.git)?/?$#', $url, $m)) {
        $repo = ['platform' => 'github', 'owner' => $m[1], 'repo' => rtrim($m[2], '/')];
    } elseif (preg_match('#gitee\.com/([^/]+)/([^/]+?)(?:\.git)?/?$#', $url, $m)) {
        $repo = ['platform' => 'gitee', 'owner' => $m[1], 'repo' => rtrim($m[2], '/')];
    }
    if (!$repo) {
        echo json_encode(['ok' => false, 'msg' => '无法识别仓库地址，支持 GitHub / Gitee']);
        exit;
    }

    // 请求 API
    $apiUrl = $repo['platform'] === 'github'
        ? "https://api.github.com/repos/{$repo['owner']}/{$repo['repo']}"
        : "https://gitee.com/api/v5/repos/{$repo['owner']}/{$repo['repo']}";
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "User-Agent: Fohok/1.0\r\nAccept: application/json\r\n",
        'timeout' => 10,
    ]]);
    $resp = @file_get_contents($apiUrl, false, $ctx);
    if (!$resp) {
        echo json_encode(['ok' => false, 'msg' => '获取仓库信息失败，请检查地址是否正确']);
        exit;
    }
    $data = json_decode($resp, true);
    if (!$data || !empty($data['message'])) {
        echo json_encode(['ok' => false, 'msg' => '仓库不存在或无法访问']);
        exit;
    }

    $result = [
        'ok' => true,
        'name' => $data['name'] ?? '',
        'description' => $data['description'] ?? '',
        'project_url' => $data['html_url'] ?? $url,
        'developer_website' => $data['homepage'] ?? '',
        'language' => $data['language'] ?? '',
        'stars' => $data['stargazers_count'] ?? 0,
        'platform' => $repo['platform'],
    ];
    echo json_encode($result);
    exit;
}

echo json_encode(['ok' => false, 'msg' => '未知操作']);