<?php
// ============================================================
// 项目评分 API - 系统升级新增
// action: rate | get | my_rating
// 评分范围：1-5 星
// ============================================================
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user       = getCurrentUser();
$isLoggedIn = $user !== null;
$action     = $_POST['action'] ?? $_GET['action'] ?? '';
$db         = getDB();
$uid        = currentUserId($user);

// 评分会改变数据，只接受 POST + 同源
if ($action === 'rate') {
    securityRequirePost();
}

// ── 获取项目评分（公开） ─────────────────────────────────
if ($action === 'get') {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) {
        jsonResponse(false, '参数错误');
    }

    $avgStmt = $db->prepare(
        'SELECT AVG(rating) AS avg_rating, COUNT(*) AS rating_count
         FROM project_ratings WHERE project_id = ?'
    );
    $avgStmt->execute([$projectId]);
    $result = $avgStmt->fetch();

    $myRating = 0;
    if ($uid) {
        $myStmt = $db->prepare('SELECT rating FROM project_ratings WHERE project_id = ? AND user_id = ?');
        $myStmt->execute([$projectId, $uid]);
        $myRow = $myStmt->fetch();
        $myRating = $myRow ? (int)$myRow['rating'] : 0;
    }

    jsonResponse(true, '', [
        'avg_rating'   => $result['avg_rating'] ? round((float)$result['avg_rating'], 1) : 0,
        'rating_count' => (int)$result['rating_count'],
        'my_rating'    => $myRating,
    ]);
}

// ── 以下操作需要登录 ─────────────────────────────────────
if (in_array($action, ['rate', 'my_rating'])) {
    if (!$isLoggedIn) {
        jsonResponse(false, '请先登录');
    }
}

// ── 评分（每人每项目仅一次，可修改） ─────────────────────
if ($action === 'rate') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $rating    = (int)($_POST['rating'] ?? 0);

    if (!$projectId) {
        jsonResponse(false, '参数错误');
    }
    if ($rating < 1 || $rating > 5) {
        jsonResponse(false, '评分范围为 1-5 星');
    }
    if (!securityRateLimit('project_rate', 30, 60, 'u:' . $uid)) {
        jsonResponse(false, '操作过于频繁，请稍后再试');
    }

    // 验证项目存在且已通过审核
    $projStmt = $db->prepare('SELECT id, user_id, name FROM projects WHERE id = ? AND status = \'approved\' AND is_hidden = 0');
    $projStmt->execute([$projectId]);
    $project = $projStmt->fetch();
    if (!$project) {
        jsonResponse(false, '项目不存在');
    }

    // 不能给自己的项目评分
    if ((int)$project['user_id'] === $uid) {
        jsonResponse(false, '不能为自己的项目评分');
    }

    // 检查是否已评分
    $check = $db->prepare('SELECT id FROM project_ratings WHERE project_id = ? AND user_id = ?');
    $check->execute([$projectId, $uid]);
    $existing = $check->fetch();

    if ($existing) {
        $db->prepare('UPDATE project_ratings SET rating = ?, updated_at = NOW() WHERE id = ?')
           ->execute([$rating, $existing['id']]);
    } else {
        $db->prepare('INSERT INTO project_ratings (project_id, user_id, rating) VALUES (?, ?, ?)')
           ->execute([$projectId, $uid, $rating]);
    }

    // 获取更新后的平均分
    $avgStmt = $db->prepare(
        'SELECT AVG(rating) AS avg_rating, COUNT(*) AS rating_count
         FROM project_ratings WHERE project_id = ?'
    );
    $avgStmt->execute([$projectId]);
    $result = $avgStmt->fetch();

    jsonResponse(true, '评分成功', [
        'avg_rating'   => round((float)$result['avg_rating'], 1),
        'rating_count' => (int)$result['rating_count'],
        'my_rating'    => $rating,
    ]);
}

// ── 获取我的评分 ─────────────────────────────────────────
if ($action === 'my_rating') {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) {
        jsonResponse(false, '参数错误');
    }

    $stmt = $db->prepare('SELECT rating FROM project_ratings WHERE project_id = ? AND user_id = ?');
    $stmt->execute([$projectId, $uid]);
    $row = $stmt->fetch();

    jsonResponse(true, '', ['my_rating' => $row ? (int)$row['rating'] : 0]);
}

jsonResponse(false, '未知操作');
