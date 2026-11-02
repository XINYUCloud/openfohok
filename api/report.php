<?php
// ============================================================
// 内容举报 API
// action: submit
// ============================================================
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action = $_POST['action'] ?? '';

// ── 提交举报 ──────────────────────────────────────────
if ($action === 'submit') {
    securityRequirePost();

    $projectId  = (int)($_POST['project_id'] ?? 0);
    $reportType = trim($_POST['report_type'] ?? '');
    $reason     = trim($_POST['reason'] ?? '');
    $notifyEmail = trim($_POST['notify_email'] ?? '');

    if (!$projectId) {
        echo json_encode(['ok' => false, 'msg' => '参数错误：缺少项目ID']);
        exit;
    }
    if (mb_strlen($reason) > 1000) {
        echo json_encode(['ok' => false, 'msg' => '举报说明过长']);
        exit;
    }
    if (!securityRateLimit('report_submit', 5, 3600)) {
        echo json_encode(['ok' => false, 'msg' => '提交过于频繁，请稍后再试']);
        exit;
    }

    $validTypes = ['spam', 'violation', 'copyright', 'misleading', 'other'];
    if (!in_array($reportType, $validTypes)) {
        echo json_encode(['ok' => false, 'msg' => '请选择有效的举报类型']);
        exit;
    }

    // 验证项目存在
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name, user_id, status FROM projects WHERE id = ?');
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) {
        echo json_encode(['ok' => false, 'msg' => '项目不存在']);
        exit;
    }

    // 获取当前用户
    $me = getCurrentUser();
    $reporterId = $me ? ($me['user_id'] ?? $me['id']) : null;

    // 不能举报自己的项目
    if ($reporterId && (int)$project['user_id'] === $reporterId) {
        echo json_encode(['ok' => false, 'msg' => '不能举报自己的项目']);
        exit;
    }

    // 登录用户自动使用绑定邮箱
    if ($reporterId) {
        $uStmt = $db->prepare('SELECT email FROM users WHERE id = ?');
        $uStmt->execute([$reporterId]);
        $notifyEmail = $uStmt->fetchColumn() ?: '';
    }

    // 邮箱格式校验
    if ($notifyEmail && !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'msg' => '邮箱格式不正确']);
        exit;
    }

    // 禁止重复举报（同一人同一项目）
    if ($reporterId) {
        $dup = $db->prepare('SELECT id FROM reports WHERE project_id = ? AND reporter_id = ? AND status = ?');
        $dup->execute([$projectId, $reporterId, 'pending']);
        if ($dup->fetch()) {
            echo json_encode(['ok' => false, 'msg' => '你已举报过该项目，请耐心等待处理']);
            exit;
        }
    }

    $db->prepare(
        'INSERT INTO reports (project_id, reporter_id, report_type, reason, notify_email) VALUES (?, ?, ?, ?, ?)'
    )->execute([$projectId, $reporterId, $reportType, $reason ?: null, $notifyEmail ?: null]);

    // 登录用户下发站内信通知
    if ($reporterId) {
        $types = ['spam'=>'垃圾信息','violation'=>'违规内容','copyright'=>'侵权问题','misleading'=>'虚假误导','other'=>'其他问题'];
        $typeLabel = $types[$reportType] ?? $reportType;
        createNotification(
            $reporterId, 'report_submitted',
            "你已举报项目「{$project['name']}」",
            "举报类型：{$typeLabel}\n将在审核后通过站内信或邮箱通知你处理结果。",
            $projectId
        );
    }

    // 通知管理员邮箱
    $types = ['spam'=>'垃圾信息','violation'=>'违规内容','copyright'=>'侵权问题','misleading'=>'虚假误导','other'=>'其他问题'];
    $typeLabel2 = $types[$reportType] ?? $reportType;
    $adminSubject = '【文谶驿站】新举报：' . $project['name'];
    $adminBody = "项目「{$project['name']}」收到新的举报。\n\n举报类型：{$typeLabel2}\n" . ($reason ? "补充说明：{$reason}\n" : '') . ($reporterId ? "举报人ID：{$reporterId}\n" : "举报人：匿名\n") . "\n请登录后台处理。";
    @sendSimpleEmail(ADMIN_EMAILS, $adminSubject, $adminBody);

    echo json_encode(['ok' => true, 'msg' => '举报已提交，感谢你的反馈']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => '未知操作']);