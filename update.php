<?php
// ============================================================
// App 更新检测接口
// POST /update.php
// 参数: current_version (string) — 当前 App 版本号
//
// 返回:
//   - 有新版本: {ok:true, has_update:true, app_name, version, changelog, package_size, download_url, is_force}
//   - 已是最新: {ok:true, has_update:false, msg:"已是最新版本"}
//   - 传入版本异常(大于系统最新): {ok:false, msg:"版本号异常"}
// ============================================================

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

$currentVersion = trim($_POST['current_version'] ?? $_GET['current_version'] ?? '');

if (!$currentVersion) {
    echo json_encode(['ok' => false, 'msg' => '缺少 current_version 参数']);
    exit;
}

$db = getDB();

// 获取系统最新版本
$stmt = $db->query('SELECT * FROM app_updates ORDER BY created_at DESC LIMIT 1');
$latest = $stmt->fetch();

if (!$latest) {
    echo json_encode(['ok' => false, 'msg' => '暂无版本信息']);
    exit;
}

// 版本号比较
$cmp = version_compare($currentVersion, $latest['version']);

if ($cmp > 0) {
    // 传入版本大于系统最新版本 → 异常
    echo json_encode([
        'ok'  => false,
        'msg' => '版本号异常',
        'current_version' => $currentVersion,
        'latest_version'  => $latest['version'],
    ]);
    exit;
}

if ($cmp === 0) {
    // 已是最新版本
    echo json_encode([
        'ok'         => true,
        'has_update' => false,
        'msg'        => '已是最新版本',
        'version'    => $latest['version'],
    ]);
    exit;
}

// 有新版本
echo json_encode([
    'ok'           => true,
    'has_update'   => true,
    'app_name'     => $latest['app_name'],
    'version'      => $latest['version'],
    'changelog'    => $latest['changelog'],
    'package_size' => $latest['package_size'],
    'download_url' => $latest['download_url'],
    'is_force'     => (bool)$latest['is_force'],
    'msg'          => '发现新版本 ' . $latest['version'],
]);