<?php
// ============================================================
// 文谶驿站 App 统一接口入口
// 部署：https://fohok.xin/open-app/index.php
// 请求：https://fohok.xin/open-app/index.php?action=xxx
// 鉴权：Authorization: Bearer <app_token>（登录类接口返回 token）
// 返回：{"ok":true,...} / {"ok":false,"msg":"..."}
// ============================================================

require_once __DIR__ . '/_lib.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db     = getDB();
$user   = appUser();          // 可能为 null（游客）
$uid    = $user ? (int)($user['id'] ?? $user['user_id']) : null;

// 邮箱验证码登录已下线，App 统一使用文谶账户 OAuth
if (in_array($action, ['login_email_send', 'login_email_check'], true)) {
    http_response_code(410);
    json_out(['ok' => false, 'msg' => '邮箱验证码登录已下线，请使用文谶账户登录', 'login_url' => '/open-app/oauth_start.php']);
}

/** 外部链接仅允许 http(s) 或站内绝对路径，避免 javascript: 等伪协议 */
function appSafeUrl(string $url): bool {
    if ($url === '') return true;
    if ($url[0] === '/') return !(isset($url[1]) && $url[1] === '/');
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && preg_match('#^https?://#i', $url) === 1;
}

/** 校验项目链接字段，合法时返回空串，非法时返回错误文案 */
function appProjectUrlError(array $data): string {
    if (!appSafeUrl(trim((string)($data['developer_website'] ?? '')))
        || !appSafeUrl(trim((string)($data['cover_url'] ?? '')))
        || !appSafeUrl(trim((string)($data['download_url'] ?? '')))) {
        return '开发者网站、封面或下载链接不合法';
    }
    return '';
}

// ════════════════════════════ 登录 / 账户 ════════════════════════════

// 退出登录
if ($action === 'logout') {
    $token = resolveAppToken();
    if ($token) {
        try { $db->prepare('DELETE FROM app_tokens WHERE token = ?')->execute([$token]); } catch (\Throwable $e) {}
    }
    json_out(['ok' => true]);
}

// 当前用户信息（含未读通知数）
if ($action === 'me') {
    requireAppUser();
    $info = safeUser($user);
    $info['unread'] = (int)getUnreadCount($uid);
    $cnt = null;
    try {
        $s = $db->prepare('SELECT COUNT(*) c FROM projects WHERE user_id = ?'); $s->execute([$uid]); $cnt = (int)$s->fetchColumn();
    } catch (\Throwable $e) {}
    $info['my_project_count'] = $cnt ?? 0;
    json_out(['ok' => true, 'user' => $info]);
}

// 更新个人资料
if ($action === 'profile_update') {
    $u = requireAppUser();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') json_out(['ok' => false, 'msg' => '用户名不能为空']);
    if (mb_strlen($name) > 40) json_out(['ok' => false, 'msg' => '用户名过长']);
    $bio     = trim($_POST['bio'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    if (mb_strlen($bio) > 500) json_out(['ok' => false, 'msg' => '简介过长']);
    if (mb_strlen($phone) > 30) json_out(['ok' => false, 'msg' => '联系方式过长']);
    if ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $website) || strlen($website) > 255)) {
        json_out(['ok' => false, 'msg' => '个人网站需为有效的 http/https 链接']);
    }
    $db->prepare('UPDATE users SET name=?, bio=?, website=?, phone=?, updated_at=NOW() WHERE id=?')
       ->execute([$name, $bio ?: null, $website ?: null, $phone ?: null, $uid]);
    json_out(['ok' => true, 'msg' => '保存成功']);
}

// 更新头像
if ($action === 'avatar_update') {
    requireAppUser();
    $pic = trim($_POST['picture'] ?? '');
    if (!$pic || strlen($pic) > 500 || !filter_var($pic, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $pic)) {
        json_out(['ok' => false, 'msg' => '头像地址不合法，需为 https 链接']);
    }
    $db->prepare('UPDATE users SET picture=?, updated_at=NOW() WHERE id=?')->execute([$pic, $uid]);
    json_out(['ok' => true, 'msg' => '头像已更新，可能需要1-2分钟同步~']);
}

// ════════════════════════════ OAuth（App webview 授权）════════════════════════════

// 创建 OAuth 票据（App 打开 webview 前调用）
if ($action === 'oauth_ticket_create') {
    json_out(array_merge(['ok' => true], createOauthTicket()));
}

// 轮询票据状态（授权完成后拿到 AppToken）
if ($action === 'oauth_ticket_poll') {
    $ticket = trim($_GET['ticket'] ?? $_POST['ticket'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $ticket)) json_out(['ok' => false, 'msg' => '票据格式错误']);
    json_out(pollOauthTicket($ticket));
}

// ════════════════════════════ 项目（公开）════════════════════════════

// 项目列表
if ($action === 'projects_list') {
    $page  = max(1, (int)($_GET['page'] ?? $_POST['page'] ?? 1));
    $limit = 12; $offset = ($page - 1) * $limit;
    $sql = 'SELECT p.*, u.name AS author_name, u.picture AS author_pic,
                (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id) AS like_count'
        .($uid ? ', (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id AND user_id = ?) AS is_liked' : '')
        .' FROM projects p JOIN users u ON p.user_id = u.id
          WHERE p.status = \'approved\' AND p.is_hidden = 0
          ORDER BY p.created_at DESC LIMIT ? OFFSET ?';
    $stmt = $db->prepare($sql);
    $params = $uid ? [$uid, $limit, $offset] : [$limit, $offset];
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
    foreach ($projects as &$pr) { $pr['like_count']=(int)$pr['like_count']; $pr['is_liked']=$uid?(bool)$pr['is_liked']:false; }
    unset($pr);
    $total = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='approved' AND is_hidden=0")->fetchColumn();
    json_out(['ok' => true, 'projects' => $projects, 'total' => $total, 'page' => $page]);
}

// 项目详情
if ($action === 'project_detail') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $sql = 'SELECT p.*, u.name AS author_name, u.picture AS author_pic,
                (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id) AS like_count'
        .($uid ? ', (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id AND user_id = ?) AS is_liked' : '')
        .' FROM projects p JOIN users u ON p.user_id = u.id WHERE p.id = ?';
    $stmt = $db->prepare($sql);
    $params = $uid ? [$uid, $id] : [$id];
    $stmt->execute($params);
    $project = $stmt->fetch();
    if (!$project) json_out(['ok' => false, 'msg' => '项目不存在']);
    if ((int)$project['user_id'] != $uid && ($project['status'] !== 'approved' || $project['is_hidden'])) {
        json_out(['ok' => false, 'msg' => '项目不存在']);
    }
    $project['like_count'] = (int)($project['like_count'] ?? 0);
    $project['is_liked']   = $uid ? (bool)($project['is_liked'] ?? false) : false;
    $db->prepare('UPDATE projects SET view_count = view_count + 1 WHERE id = ?')->execute([$id]);
    json_out(['ok' => true, 'project' => $project]);
}

// 版本历史
if ($action === 'project_versions') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM project_versions WHERE project_id = ? ORDER BY created_at DESC');
    $stmt->execute([$id]);
    json_out(['ok' => true, 'versions' => $stmt->fetchAll()]);
}

// 项目搜索
if ($action === 'project_search') {
    $keyword = trim($_POST['keyword'] ?? '');
    if ($keyword === '') json_out(['ok' => false, 'msg' => '请输入关键词']);
    $kw = '%' . $keyword . '%';
    $sql = 'SELECT p.id, p.name, p.version, p.description, p.project_url, p.developer_website,
                   p.contact_email, p.contact_phone, p.contact_qq, p.contact_wechat,
                   p.release_date, p.view_count, p.created_at, p.is_guest,
                   p.status, p.is_hidden,
                   u.name AS author_name, u.picture AS author_pic, u.id AS user_id,
                (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id) AS like_count'
        .($uid ? ', (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id AND user_id = ?) AS is_liked' : '')
        .' FROM projects p LEFT JOIN users u ON p.user_id = u.id
          WHERE p.status = \'approved\' AND p.is_hidden = 0
            AND (p.name LIKE ? OR p.description LIKE ? OR u.name LIKE ?)
          LIMIT 50';
    $stmt = $db->prepare($sql);
    $params = $uid ? array_merge([$uid], [$kw, $kw, $kw]) : [$kw, $kw, $kw];
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
    foreach ($projects as &$pr) { $pr['like_count']=(int)($pr['like_count']??0); $pr['is_liked']=$uid?(bool)($pr['is_liked']??false):false; }
    unset($pr);
    json_out(['ok' => true, 'projects' => $projects]);
}

// 站内统计
if ($action === 'stats') {
    $total       = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='approved' AND is_hidden=0")->fetchColumn();
    $approved    = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status='approved'")->fetchColumn();
    $developers  = (int)$db->query('SELECT COUNT(DISTINCT user_id) FROM projects')->fetchColumn();
    json_out(['ok' => true, 'stats' => ['total_projects' => $total, 'approved_projects' => $approved, 'total_developers' => $developers]]);
}

// 公开用户主页
if ($action === 'user_profile') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT id, name, picture, bio, website, phone, email FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $profile = $stmt->fetch();
    if (!$profile) json_out(['ok' => false, 'msg' => '用户不存在']);
    $proj = $db->prepare("SELECT id, name, version, description, project_url, developer_website, release_date, view_count, created_at
                          FROM projects WHERE user_id = ? AND status='approved' AND is_hidden=0 ORDER BY created_at DESC LIMIT 50");
    $proj->execute([$id]);
    $projects = $proj->fetchAll();
    json_out(['ok' => true, 'profile' => $profile, 'projects' => $projects]);
}

// 公告
if ($action === 'announcement') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) { // 没有 id 时返回最新一条
        $stmt = $db->query('SELECT id, title, content, severity, created_at FROM announcements ORDER BY id DESC LIMIT 1');
    } else {
        $stmt = $db->prepare('SELECT id, title, content, severity, created_at FROM announcements WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
    }
    $row = $stmt->fetch();
    if (!$row) json_out(['ok' => false, 'msg' => '无公告']);
    json_out(['ok' => true, 'announcement' => $row]);
}

// ════════════════════════════ 项目（需登录 / 需本人）════════════════════════════

// 提交项目（允许游客，与网页端一致）
if ($action === 'project_submit') {
    $name        = trim($_POST['name'] ?? '');
    $version     = trim($_POST['version'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $project_url = trim($_POST['project_url'] ?? '');
    if (!$name || !$version || !$description || !$project_url) json_out(['ok' => false, 'msg' => '请填写必填字段']);
    if (mb_strlen($name) > 100 || mb_strlen($version) > 50 || mb_strlen($description) > 5000) json_out(['ok' => false, 'msg' => '项目名称、版本号或简介超出长度限制']);
    if (!filter_var($project_url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $project_url)) json_out(['ok' => false, 'msg' => '项目网址需为 http/https 链接']);
    $urlErr = appProjectUrlError($_POST);
    if ($urlErr !== '') json_out(['ok' => false, 'msg' => $urlErr]);
    if (!securityRateLimit('app_project_submit', 5, 600, $uid ? 'u:' . $uid : '')) json_out(['ok' => false, 'msg' => '提交过于频繁，请稍后再试']);

    if ($uid) {
        try { checkUserRestriction($uid, 'publish'); } catch (\RuntimeException $e) { json_out(['ok' => false, 'msg' => $e->getMessage()]); }
        $userId = $uid; $notifyEmail = $user['email']; $notifyName = $user['name'] ?? '开发者'; $isGuest = 0;
    } else {
        $guestName  = trim($_POST['guest_name']  ?? '');
        $guestEmail = trim($_POST['guest_email'] ?? '');
        if (!$guestName || !$guestEmail) json_out(['ok' => false, 'msg' => '请填写提交者昵称和邮箱']);
        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'msg' => '邮箱格式不正确']);
        if (mb_strlen($guestName) > 40) json_out(['ok' => false, 'msg' => '提交者昵称过长']);
        if (!securityRateLimit('app_guest_submit', 5, 3600)) json_out(['ok' => false, 'msg' => '提交过于频繁，请稍后再试']);
        // 只复用未绑定文谶身份的游客记录，真实账号不可被游客流程改写
        $chk = $db->prepare(
            "SELECT id FROM users
             WHERE LOWER(email) = LOWER(?)
               AND wenchan_uid IS NULL AND wenchan_sub IS NULL AND xgorb_sub IS NULL
               AND (reg_source = 'guest' OR reg_source IS NULL)
             ORDER BY id ASC LIMIT 1"
        );
        $chk->execute([$guestEmail]);
        $existing = $chk->fetchColumn();
        if ($existing) { $userId = (int)$existing; }
        else { $db->prepare("INSERT INTO users (xgorb_sub, name, email, reg_source, last_login_at, created_at, updated_at) VALUES (NULL,?,?,'guest',NOW(),NOW(),NOW())")->execute([$guestName,$guestEmail]); $userId=(int)$db->lastInsertId(); }
        $notifyEmail=$guestEmail; $notifyName=$guestName; $isGuest=1;
    }

    $db->prepare(
        'INSERT INTO projects (user_id, name, version, release_date, project_url, description,
            developer_website, contact_email, contact_phone, contact_qq, contact_wechat, tech_stack, cover_url, is_guest)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $userId, $name, $version,
        trim($_POST['release_date'] ?? '') ?: null, $project_url, $description,
        trim($_POST['developer_website'] ?? '') ?: null,
        trim($_POST['contact_email'] ?? '') ?: null,
        trim($_POST['contact_phone'] ?? '') ?: null,
        trim($_POST['contact_qq'] ?? '') ?: null,
        trim($_POST['contact_wechat'] ?? '') ?: null,
        trim($_POST['tech_stack'] ?? '') ?: null,
        trim($_POST['cover_url'] ?? '') ?: null,
        $isGuest,
    ]);
    $projectId = (int)$db->lastInsertId();
    try { createNotification($userId, 'project_submitted', "项目「{$name}」已提交审核", "您的项目「{$name} v{$version}」已成功提交，请耐心等待审核。", $projectId); } catch (\Throwable $e) {}
    @sendProjectSubmittedToUser(['name'=>$notifyName,'email'=>$notifyEmail], ['name'=>$name,'version'=>$version,'project_url'=>$project_url,'description'=>$description]);
    @sendProjectSubmittedToAdmin(['name'=>$notifyName,'email'=>$notifyEmail], ['name'=>$name,'version'=>$version,'project_url'=>$project_url,'description'=>$description]);
    json_out(['ok' => true, 'msg' => '提交成功~，请耐心等待审核！', 'id' => $projectId]);
}

// 我的项目
if ($action === 'my_projects') {
    requireAppUser();
    $stmt = $db->prepare('SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$uid]);
    json_out(['ok' => true, 'projects' => $stmt->fetchAll()]);
}

// 点赞过的项目
if ($action === 'liked_projects') {
    requireAppUser();
    $stmt = $db->prepare(
        "SELECT p.id, p.name, p.version, p.description, p.project_url, p.view_count, p.created_at,
                u.name AS author_name, u.picture AS author_pic,
                (SELECT COUNT(*) FROM project_likes WHERE project_id = p.id) AS like_count,
                1 AS is_liked
         FROM project_likes l
         JOIN projects p ON p.id = l.project_id
         JOIN users u ON u.id = p.user_id
         WHERE l.user_id = ? AND p.status = 'approved' AND p.is_hidden = 0
         ORDER BY l.id DESC"
    );
    $stmt->execute([$uid]);
    $projects = $stmt->fetchAll();
    foreach ($projects as &$pr) { $pr['like_count']=(int)$pr['like_count']; }
    unset($pr);
    json_out(['ok' => true, 'projects' => $projects]);
}

// 点赞 / 取消点赞
if ($action === 'project_like') {
    requireAppUser();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) json_out(['ok' => false, 'msg' => '参数错误']);
    $stmt = $db->prepare("SELECT id, user_id, name FROM projects WHERE id = ? AND status='approved' AND is_hidden=0 LIMIT 1");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) json_out(['ok' => false, 'msg' => '项目不存在']);
    if ((int)$p['user_id'] == $uid) json_out(['ok' => false, 'msg' => '不能给自己点赞']);

    $chk = $db->prepare('SELECT id FROM project_likes WHERE project_id=? AND user_id=?');
    $chk->execute([$id, $uid]);
    if ($chk->fetchColumn()) {
        $db->prepare('DELETE FROM project_likes WHERE project_id=? AND user_id=?')->execute([$id, $uid]);
        $isLiked = false;
    } else {
        $db->prepare('INSERT INTO project_likes (project_id, user_id, created_at) VALUES (?,?,NOW())')->execute([$id, $uid]);
        $isLiked = true;
        try { createLikeNotification((int)$p['user_id'], $uid, $id, $p['name'], $user['name'] ?? '好友'); } catch (\Throwable $e) {}
    }
    $cnt = (int)$db->query('SELECT COUNT(*) FROM project_likes WHERE project_id='.$id)->fetchColumn();
    json_out(['ok' => true, 'is_liked' => $isLiked, 'like_count' => $cnt]);
}

// 编辑项目（本人）
if ($action === 'project_update') {
    $u = requireAppUser();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM projects WHERE id=? AND user_id=?'); $stmt->execute([$id, $uid]);
    $p = $stmt->fetch();
    if (!$p) json_out(['ok' => false, 'msg' => '项目不存在']);

    $name = trim($_POST['name'] ?? ''); $version = trim($_POST['version'] ?? '');
    $description = trim($_POST['description'] ?? ''); $project_url = trim($_POST['project_url'] ?? '');
    if (!$name || !$version || !$description || !$project_url) json_out(['ok' => false, 'msg' => '请填写必填字段']);
    if (mb_strlen($name) > 100 || mb_strlen($version) > 50 || mb_strlen($description) > 5000) json_out(['ok' => false, 'msg' => '项目名称、版本号或简介超出长度限制']);
    if (!filter_var($project_url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $project_url)) json_out(['ok' => false, 'msg' => '项目网址需为 http/https 链接']);
    $urlErr = appProjectUrlError($_POST);
    if ($urlErr !== '') json_out(['ok' => false, 'msg' => $urlErr]);

    try { checkUserRestriction($uid, 'publish'); } catch (\RuntimeException $e) { json_out(['ok' => false, 'msg' => $e->getMessage()]); }

    // 版本变更时保留旧版本历史
    if ($version !== $p['version']) {
        try {
            $db->prepare(
                'INSERT INTO project_versions (project_id, version, release_date, description, project_url, developer_website, changelog, tag)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([
                $id, $p['version'], $p['release_date'], $p['description'], $p['project_url'],
                $p['developer_website'], trim($_POST['changelog'] ?? ''), trim($_POST['tag'] ?? 'default'),
            ]);
        } catch (\Throwable $e) {}
    }

    $db->prepare(
        'UPDATE projects SET name=?, version=?, description=?, project_url=?, release_date=?,
            developer_website=?, contact_email=?, contact_phone=?, contact_qq=?, contact_wechat=?,
            tech_stack=?, cover_url=?, download_url=?, download_filename=?, download_filetype=?,
            download_filesize=?, changelog=?, status=\'pending\', updated_at=NOW()
         WHERE id=?'
    )->execute([
        $name, $version, $description, $project_url,
        trim($_POST['release_date'] ?? '') ?: null,
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
        trim($_POST['changelog'] ?? '') ?: null,
        $id,
    ]);
    try { createNotification($uid, 'project_submitted', "项目「{$name}」已提交修改", "您的项目「{$name} v{$version}」已重新进入审核队列。", $id); } catch (\Throwable $e) {}
    json_out(['ok' => true, 'msg' => '更新成功，重新进入审核队列']);
}

// 发布新版本（本人）
if ($action === 'project_publish_version') {
    $u = requireAppUser();
    $id = (int)($_POST['project_id'] ?? $_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM projects WHERE id=? AND user_id=?'); $stmt->execute([$id, $uid]);
    $p = $stmt->fetch();
    if (!$p) json_out(['ok' => false, 'msg' => '项目不存在']);
    $version = trim($_POST['version'] ?? '');
    if (!$version) json_out(['ok' => false, 'msg' => '请填写新版本号']);
    if (mb_strlen($version) > 50) json_out(['ok' => false, 'msg' => '版本号过长']);
    $pjUrl = trim($_POST['project_url'] ?? '');
    if ($pjUrl !== '' && (!filter_var($pjUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $pjUrl))) {
        json_out(['ok' => false, 'msg' => '项目网址需为 http/https 链接']);
    }
    $urlErr = appProjectUrlError($_POST);
    if ($urlErr !== '') json_out(['ok' => false, 'msg' => $urlErr]);

    try { checkUserRestriction($uid, 'publish'); } catch (\RuntimeException $e) { json_out(['ok' => false, 'msg' => $e->getMessage()]); }

    $db->prepare(
        'INSERT INTO project_versions (project_id, version, release_date, description, project_url, developer_website, changelog, tag)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $id, $version, trim($_POST['release_date'] ?? '') ?: null,
        trim($_POST['description'] ?? '') ?: null,
        trim($_POST['project_url'] ?? '') ?: null,
        trim($_POST['developer_website'] ?? '') ?: null,
        trim($_POST['changelog'] ?? '') ?: null,
        trim($_POST['tag'] ?? 'default'),
    ]);
    $db->prepare('UPDATE projects SET version=?, description=COALESCE(NULLIF(?, \'\'), description), project_url=COALESCE(NULLIF(?, \'\'), project_url), changelog=?, status=\'pending\', updated_at=NOW() WHERE id=?')
       ->execute([$version, trim($_POST['description'] ?? ''), trim($_POST['project_url'] ?? ''), trim($_POST['changelog'] ?? ''), $id]);
    try { createNotification($uid, 'project_submitted', "项目已发布新版本 v{$version}", "您的项目「{$p['name']}」已提交 v{$version}，重新进入审核队列。", $id); } catch (\Throwable $e) {}
    json_out(['ok' => true, 'msg' => '新版本已提交，重新进入审核队列']);
}

// 删除项目（本人）
if ($action === 'project_delete') {
    requireAppUser();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) json_out(['ok' => false, 'msg' => '参数错误']);
    $chk = $db->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
    $chk->execute([$id, $uid]);
    if (!$chk->fetch()) json_out(['ok' => false, 'msg' => '项目不存在']);
    $db->prepare('DELETE FROM project_versions WHERE project_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    json_out(['ok' => true]);
}

// 显示/隐藏项目（本人）
if ($action === 'project_toggle_hide') {
    requireAppUser();
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare('UPDATE projects SET is_hidden = NOT is_hidden WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    $hidden = $db->query('SELECT is_hidden FROM projects WHERE id='.$id.' AND user_id='.$uid)->fetchColumn();
    json_out(['ok' => true, 'hidden' => (bool)$hidden]);
}

// ════════════════════════════ 举报 / 订阅 ════════════════════════════

if ($action === 'report_submit') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $reportType = trim($_POST['report_type'] ?? '');
    $valid = ['spam','violation','copyright','misleading','other'];
    if (!$projectId) json_out(['ok' => false, 'msg' => '参数错误：缺少项目ID']);
    if (!in_array($reportType, $valid)) json_out(['ok' => false, 'msg' => '请选择有效的举报类型']);
    $reason = trim($_POST['reason'] ?? '');
    $stmt = $db->prepare('SELECT id, user_id, name FROM projects WHERE id=?'); $stmt->execute([$projectId]);
    $p = $stmt->fetch();
    if (!$p) json_out(['ok' => false, 'msg' => '项目不存在']);
    if ($uid && (int)$p['user_id'] == $uid) json_out(['ok' => false, 'msg' => '不能举报自己的项目']);
    $notifyEmail = $uid ? ($user['email'] ?? null) : (trim($_POST['notify_email'] ?? '') ?: null);

    // 防止重复举报 pending
    $dup = $db->prepare("SELECT id FROM reports WHERE project_id=? AND reporter_id=? AND status='pending'");
    $dup->execute([$projectId, $uid]);
    if ($dup->fetchColumn()) json_out(['ok' => false, 'msg' => '你已举报过该项目，请耐心等待处理']);

    $db->prepare('INSERT INTO reports (project_id, reporter_id, report_type, reason, notify_email) VALUES (?,?,?,?,?)')
       ->execute([$projectId, $uid, $reportType, $reason, $notifyEmail]);
    if ($uid) { try { createReportNotifyAuthor((int)$p['user_id'], $p['name'], $reportType); } catch (\Throwable $e) {} }
    json_out(['ok' => true, 'msg' => '举报已提交，感谢你的反馈']);
}

// 订阅 / 取消订阅
if ($action === 'subscription_toggle') {
    requireAppUser();
    $target = (int)($_POST['target_user_id'] ?? 0);
    if (!$target || $target == $uid) json_out(['ok' => false, 'msg' => '参数错误']);
    $chk = $db->prepare('SELECT id FROM user_subscriptions WHERE subscriber_id=? AND target_user_id=?');
    $chk->execute([$uid, $target]);
    if ($chk->fetchColumn()) {
        $db->prepare('DELETE FROM user_subscriptions WHERE subscriber_id=? AND target_user_id=?')->execute([$uid, $target]);
        json_out(['ok' => true, 'subscribed' => false, 'msg' => '已取消订阅']);
    }
    $db->prepare('INSERT INTO user_subscriptions (subscriber_id, target_user_id, created_at) VALUES (?,?,NOW())')->execute([$uid, $target]);
    try { createNewSubscriberNotification($target, $uid, $user['name'] ?? '好友'); } catch (\Throwable $e) {}
    json_out(['ok' => true, 'subscribed' => true, 'msg' => '订阅成功']);
}

if ($action === 'subscription_check') {
    $target = (int)($_GET['target_user_id'] ?? $_POST['target_user_id'] ?? 0);
    $sub = false;
    if ($uid && $target) {
        $chk = $db->prepare('SELECT id FROM user_subscriptions WHERE subscriber_id=? AND target_user_id=?');
        $chk->execute([$uid, $target]);
        $sub = (bool)$chk->fetchColumn();
    }
    json_out(['ok' => true, 'subscribed' => $sub]);
}

if ($action === 'subscription_counts') {
    $target = (int)($_GET['target_user_id'] ?? $_POST['target_user_id'] ?? 0);
    $count = (int)$db->query('SELECT COUNT(*) FROM user_subscriptions WHERE target_user_id='.$target)->fetchColumn();
    json_out(['ok' => true, 'subscriber_count' => $count]);
}

// ════════════════════════════ 通知 ════════════════════════════

if ($action === 'unread_count') {
    requireAppUser();
    json_out(['ok' => true, 'count' => (int)getUnreadCount($uid)]);
}

if ($action === 'notifications_list') {
    requireAppUser();
    $page = max(1, (int)($_GET['page'] ?? $_POST['page'] ?? 1));
    $limit = 20; $offset = ($page - 1) * $limit;
    $stmt = $db->prepare(
        'SELECT n.*,
                (SELECT u.name FROM users u WHERE u.id = n.liker_id) AS liker_name,
                (SELECT u.picture FROM users u WHERE u.id = n.liker_id) AS liker_pic,
                (SELECT p.name FROM projects p WHERE p.id = n.related_id) AS project_name
         FROM notifications n WHERE n.user_id = ?
         ORDER BY n.is_read ASC, n.created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute([$uid, $limit, $offset]);
    $list = $stmt->fetchAll();
    $st = $db->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=?'); $st->execute([$uid]); $total = (int)$st->fetchColumn();
    json_out(['ok' => true, 'notifications' => $list, 'total' => $total, 'page' => $page]);
}

if ($action === 'notification_detail') {
    requireAppUser();
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $stmt = $db->prepare(
        'SELECT n.*,
                (SELECT u.name FROM users u WHERE u.id = n.liker_id) AS liker_name,
                (SELECT u.picture FROM users u WHERE u.id = n.liker_id) AS liker_pic,
                (SELECT p.name FROM projects p WHERE p.id = n.related_id) AS project_name
         FROM notifications n WHERE n.id=? AND n.user_id=?'
    );
    $stmt->execute([$id, $uid]);
    $n = $stmt->fetch();
    if (!$n) json_out(['ok' => false, 'msg' => '通知不存在']);
    if (!$n['is_read']) { $db->prepare('UPDATE notifications SET is_read=1 WHERE id=?')->execute([$id]); $n['is_read'] = 1; }
    json_out(['ok' => true, 'notification' => $n]);
}

if ($action === 'notification_mark_read') {
    requireAppUser();
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$id, $uid]);
    json_out(['ok' => true]);
}

if ($action === 'notification_mark_all_read') {
    requireAppUser();
    $db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);
    json_out(['ok' => true]);
}

if ($action === 'notification_delete_all') {
    requireAppUser();
    $db->prepare('DELETE FROM notifications WHERE user_id=?')->execute([$uid]);
    json_out(['ok' => true]);
}

// ════════════════════════════ 会话管理 ════════════════════════════
// App 会话 = app_tokens（本接口签发的 AppToken）；网页会话 = sessions（浏览器 Cookie）
// 合并展示，id 形如 "app:12" / "web:34"，均可单独踢除

if ($action === 'sessions_list') {
    requireAppUser();
    ensureAppSchema();
    $current = resolveAppToken();
    $list = [];

    try {
        $stmt = $db->prepare('SELECT id, token, platform, user_agent, ip_address, created_at, last_seen_at, expires_at
                              FROM app_tokens WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$uid]);
        foreach ($stmt->fetchAll() as $t) {
            $list[] = [
                'id'           => 'app:' . $t['id'],
                'source'       => 'app',
                'platform'     => $t['platform'],
                'user_agent'   => $t['user_agent'] ?: ('WenChanApp/' . $t['platform']),
                'ip_address'   => $t['ip_address'],
                'created_at'   => $t['created_at'],
                'last_seen_at' => $t['last_seen_at'],
                'expires_at'   => $t['expires_at'],
                'is_current'   => ($t['token'] === $current),
            ];
        }
    } catch (\Throwable $e) {}

    try {
        $stmt = $db->prepare('SELECT id, user_agent, ip_address, created_at, expires_at FROM sessions WHERE user_id=? ORDER BY created_at DESC LIMIT 30');
        $stmt->execute([$uid]);
        foreach ($stmt->fetchAll() as $s) {
            $list[] = [
                'id'           => 'web:' . $s['id'],
                'source'       => 'web',
                'platform'     => 'web',
                'user_agent'   => $s['user_agent'],
                'ip_address'   => $s['ip_address'],
                'created_at'   => $s['created_at'],
                'last_seen_at' => null,
                'expires_at'   => $s['expires_at'],
                'is_current'   => false,
            ];
        }
    } catch (\Throwable $e) {}

    json_out(['ok' => true, 'sessions' => $list]);
}

if ($action === 'session_revoke') {
    requireAppUser();
    $id = trim($_POST['id'] ?? '');
    if (!preg_match('/^(app|web):\d+$/', $id)) json_out(['ok' => false, 'msg' => '参数错误']);
    [$src, $num] = explode(':', $id, 2);
    $num = (int)$num;

    if ($src === 'app') {
        $stmt = $db->prepare('SELECT token FROM app_tokens WHERE id=? AND user_id=?');
        $stmt->execute([$num, $uid]);
        $tok = $stmt->fetchColumn();
        if (!$tok) json_out(['ok' => false, 'msg' => '会话不存在']);
        if ($tok === resolveAppToken()) json_out(['ok' => false, 'msg' => '不能退出当前会话，请使用退出登录']);
        $db->prepare('DELETE FROM app_tokens WHERE id=? AND user_id=?')->execute([$num, $uid]);
    } else {
        $db->prepare('DELETE FROM sessions WHERE id=? AND user_id=?')->execute([$num, $uid]);
    }
    json_out(['ok' => true, 'msg' => '已退出该设备']);
}

if ($action === 'session_revoke_others') {
    requireAppUser();
    ensureAppSchema();
    $current = resolveAppToken();
    try { $db->prepare('DELETE FROM app_tokens WHERE user_id=? AND token<>?')->execute([$uid, $current]); } catch (\Throwable $e) {}
    try { $db->prepare('DELETE FROM sessions WHERE user_id=?')->execute([$uid]); } catch (\Throwable $e) {}
    json_out(['ok' => true, 'msg' => '已退出其他设备']);
}

// ════════════════════════════ 扫码登录（App 扫 PC 网页二维码）═══════════════════════════

if ($action === 'qr_scan') {
    requireAppUser();
    $sessionId = trim($_POST['session_id'] ?? '');
    if (!$sessionId) json_out(['ok' => false, 'msg' => '缺少 session_id']);
    $stmt = $db->prepare('SELECT * FROM qrcode_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch();
    if (!$row) json_out(['ok' => false, 'msg' => '二维码无效或已过期']);
    if ($row['status'] === 'confirmed') json_out(['ok' => false, 'msg' => '该二维码已被确认登录']);
    if ($row['status'] === 'expired' || strtotime($row['expires_at']) < time()) {
        $db->prepare("UPDATE qrcode_sessions SET status='expired' WHERE id=?")->execute([$sessionId]);
        json_out(['ok' => false, 'msg' => '二维码已过期，请刷新后重试']);
    }
    if ($row['status'] === 'cancelled') json_out(['ok' => false, 'msg' => '该二维码已被取消']);
    $db->prepare("UPDATE qrcode_sessions SET status='scanned', scan_ip=?, scan_ua=?, scan_user_id=? WHERE id=? AND status='pending'")
       ->execute([$_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), $uid, $sessionId]);
    json_out(['ok' => true, 'user' => ['id' => $uid, 'name' => $user['name'], 'picture' => $user['picture']]]);
}

if ($action === 'qr_confirm') {
    requireAppUser();
    $sessionId = trim($_POST['session_id'] ?? '');
    if (!$sessionId) json_out(['ok' => false, 'msg' => '缺少 session_id']);
    $stmt = $db->prepare('SELECT * FROM qrcode_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch();
    if (!$row) json_out(['ok' => false, 'msg' => '二维码无效或已过期']);
    if ($row['status'] !== 'scanned') json_out(['ok' => false, 'msg' => '二维码状态异常，请重新扫码']);
    if (strtotime($row['expires_at']) < time()) json_out(['ok' => false, 'msg' => '二维码已过期，请刷新后重试']);
    if ((int)$row['scan_user_id'] !== $uid) json_out(['ok' => false, 'msg' => '确认失败，操作者与扫码者不一致']);
    $db->prepare("UPDATE qrcode_sessions SET status='confirmed', user_id=?, confirmed_at=NOW() WHERE id=?")->execute([$uid, $sessionId]);
    json_out(['ok' => true, 'msg' => '已确认登录']);
}

if ($action === 'qr_cancel') {
    requireAppUser();
    $sessionId = trim($_POST['session_id'] ?? '');
    if (!$sessionId) json_out(['ok' => false, 'msg' => '缺少 session_id']);
    $stmt = $db->prepare('SELECT status FROM qrcode_sessions WHERE id=?'); $stmt->execute([$sessionId]);
    $status = $stmt->fetchColumn();
    if (!in_array($status, ['pending','scanned'])) json_out(['ok' => false, 'msg' => '当前状态不可取消']);
    $db->prepare("UPDATE qrcode_sessions SET status='cancelled' WHERE id=?")->execute([$sessionId]);
    json_out(['ok' => true, 'msg' => '已取消']);
}

// 未知操作
json_out(['ok' => false, 'msg' => '未知操作: ' . $action]);