<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['ok' => false, 'msg' => '未登录']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db     = getDB();
$userId = (int)($user['user_id'] ?? $user['id']);

// 会改变状态的操作用 require POST + 同源
if (in_array($action, ['update', 'update_avatar', 'delete_account'], true)) {
    securityRequirePost();
}

// ── 获取当前用户信息 ──────────────────────────────────────
if ($action === 'get') {
    echo json_encode(['ok' => true, 'user' => [
        'id'      => $userId,
        'name'    => $user['name'],
        'email'   => $user['email'],
        'picture' => $user['picture'],
        'bio'     => $user['bio'],
        'website' => $user['website'],
        'phone'   => $user['phone'],
    ]]);
    exit;
}

// ── 更新个人信息（极验服务端校验）────────────────────────
if ($action === 'update') {
    $captchaResult = verifyCaptcha($_POST);
    if (!$captchaResult['ok']) {
        echo json_encode(['ok' => false, 'msg' => $captchaResult['msg']]);
        exit;
    }

    $name    = trim($_POST['name'] ?? '');
    $bio     = trim($_POST['bio'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');

    if (!$name) {
        echo json_encode(['ok' => false, 'msg' => '用户名不能为空']);
        exit;
    }
    if (mb_strlen($name) > 40) {
        echo json_encode(['ok' => false, 'msg' => '用户名过长']);
        exit;
    }
    if ($website !== '') {
        $scheme = strtolower((string)parse_url($website, PHP_URL_SCHEME));
        if (!filter_var($website, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true) || strlen($website) > 255) {
            echo json_encode(['ok' => false, 'msg' => '个人网站链接无效']);
            exit;
        }
    }
    if (mb_strlen($bio) > 500 || mb_strlen($phone) > 30) {
        echo json_encode(['ok' => false, 'msg' => '资料超出长度限制']);
        exit;
    }

    $db->prepare(
        'UPDATE users SET name=?, bio=?, website=?, phone=?, updated_at=NOW() WHERE id=?'
    )->execute([$name, $bio ?: null, $website ?: null, $phone ?: null, $userId]);

    echo json_encode(['ok' => true, 'msg' => '保存成功']);
    exit;
}

// ── 获取指定用户公开信息（用于用户主页）──────────────────
if ($action === 'public_profile') {
    $targetId = (int)($_GET['id'] ?? 0);
    if (!$targetId) { echo json_encode(['ok' => false, 'msg' => '参数错误']); exit; }

    $stmt = $db->prepare(
        'SELECT id, name, picture, bio, website FROM users WHERE id = ?'
    );
    $stmt->execute([$targetId]);
    $profile = $stmt->fetch();
    if (!$profile) { echo json_encode(['ok' => false, 'msg' => '用户不存在']); exit; }

    $pstmt = $db->prepare(
        'SELECT id, name, version, description, project_url, developer_website,
                release_date, view_count, created_at
         FROM projects
         WHERE user_id = ? AND status = \'approved\' AND is_hidden = 0
         ORDER BY created_at DESC'
    );
    $pstmt->execute([$targetId]);
    $projects = $pstmt->fetchAll();

    echo json_encode(['ok' => true, 'profile' => $profile, 'projects' => $projects]);
    exit;
}

// ── 更新头像────────────────────────────
if ($action === 'update_avatar') {
    $captchaResult = verifyCaptcha($_POST);
    if (!$captchaResult['ok']) {
        echo json_encode(['ok' => false, 'msg' => $captchaResult['msg']]);
        exit;
    }
    $picture = trim($_POST['picture'] ?? '');
    if (!$picture || strlen($picture) > 500 || !filter_var($picture, FILTER_VALIDATE_URL)) {
        echo json_encode(['ok' => false, 'msg' => '头像链接无效']);
        exit;
    }
    if (strtolower((string)parse_url($picture, PHP_URL_SCHEME)) !== 'https') {
        echo json_encode(['ok' => false, 'msg' => '头像链接必须使用 HTTPS']);
        exit;
    }
    $db->prepare(
        'UPDATE users SET picture=?, updated_at=NOW() WHERE id=?'
    )->execute([$picture, $userId]);
    echo json_encode(['ok' => true, 'msg' => '头像已更新，可能需要1-2分钟同步~']);
    exit;
}

// ── 注销账号 ─────────────────────────────────────────────
if ($action === 'delete_account') {
    $captchaResult = verifyCaptcha($_POST);
    if (!$captchaResult['ok']) {
        echo json_encode(['ok' => false, 'msg' => $captchaResult['msg']]);
        exit;
    }

    try {
        $db->beginTransaction();

        // 先查询用户邮箱（用于后续清理 email_register_tokens）
        $userEmail = $db->prepare('SELECT email FROM users WHERE id = ?');
        $userEmail->execute([$userId]);
        $email = $userEmail->fetchColumn();

        // 删除通行密钥凭据
        $db->prepare('DELETE FROM webauthn_credentials WHERE user_id = ?')->execute([$userId]);

        // 删除点赞记录（用户点的赞 + 用户项目被点的赞）
        $db->prepare('DELETE FROM project_likes WHERE user_id = ?')->execute([$userId]);
        $db->prepare('DELETE FROM project_likes WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)')->execute([$userId]);

        // 删除通知（用户收到的 + 用户作为点赞人的）
        $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);
        $db->prepare('DELETE FROM notifications WHERE liker_id = ?')->execute([$userId]);

        // 删除 OAuth 授权记录
        $db->prepare('DELETE FROM oauth_authorizations WHERE user_id = ?')->execute([$userId]);

        // 删除邮箱验证记录（email_login_tokens 有 ON DELETE CASCADE，但显式清理更安全）
        $db->prepare('DELETE FROM email_login_tokens WHERE user_id = ?')->execute([$userId]);

        // email_register_tokens 表没有 user_id 列，按 email 清理
        if ($email) {
            $db->prepare('DELETE FROM email_register_tokens WHERE email = ?')->execute([$email]);
        }

        // 删除项目（有 ON DELETE CASCADE，但显式清理更安全）
        $db->prepare('DELETE FROM projects WHERE user_id = ?')->execute([$userId]);

        // 删除用户（sessions 和 email_login_tokens 有 ON DELETE CASCADE 自动清理）
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

        $db->commit();

        // 销毁会话
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        echo json_encode(['ok' => true, 'msg' => '账号已注销']);
    } catch (\Exception $e) {
        $db->rollBack();
        error_log('[DeleteAccount] 注销失败: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => '注销失败，请稍后重试']);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => '未知操作']);