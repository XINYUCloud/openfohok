<?php
// ============================================================
// 登录入口：跳转文谶账户授权页
// GET /api/auth.php?action=start&to=/home.php
// ============================================================
require_once __DIR__ . '/../bootstrap.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'start';
$action = is_string($action) ? $action : 'start';
$to     = $_GET['to'] ?? $_POST['to'] ?? null;
$to     = is_string($to) ? $to : null;

if (!in_array($action, ['start', 'wenchan'], true)) {
    jsonResponse(false, '未知操作');
}

wenchanOauthStart($to);
