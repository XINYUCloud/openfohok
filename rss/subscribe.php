<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
$email = trim($data['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    app_log('subscribe', "拒绝：邮箱格式不合法 - " . substr($raw, 0, 200));
    echo json_encode(['message' => '邮箱格式似乎不太对哦~'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = __DIR__ . '/data/rss-email.json';
$list = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($list)) {
    $list = [];
}

if (in_array($email, $list, true)) {
    app_log('subscribe', "重复订阅：{$email}");
    echo json_encode(['message' => '邮箱已经订阅啦！'], JSON_UNESCAPED_UNICODE);
    exit;
}

$list[] = $email;
$saved = file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($saved === false) {
    app_log('subscribe', "错误：写入订阅文件失败 - {$email}");
    echo json_encode(['message' => '订阅失败，请稍后再试~'], JSON_UNESCAPED_UNICODE);
    exit;
}

app_log('subscribe', "新增订阅：{$email}");

// 发送欢迎邮件（异步失败不影响订阅本身成功）
try {
    if (defined('RESEND_API_KEY') && RESEND_API_KEY !== '') {
        $resend = Resend::client(RESEND_API_KEY);
        $resend->emails->send([
            'from'    => RESEND_FROM_NAME . ' <' . RESEND_FROM_EMAIL . '>',
            'to'      => [$email],
            'subject' => '欢迎订阅文谶驿站 · 新文速递',
            'html'    => '<div style="font-family:sans-serif; max-width:600px; margin:0 auto;">'
                        . '<h2 style="color:#2d6a4f;">订阅成功！</h2>'
                        . '<p style="color:#333; line-height:1.8;">感谢您订阅文谶驿站 · 墨引思潮。此后每当有新文章寄达，我们都会第一时间通过邮件告知您。</p>'
                        . '<p style="color:#999; font-size:12px;">前往 <a href="' . SITE_BASE_URL . '/page.html" style="color:#2d6a4f;">文谶驿站</a> 查看最新文章。</p>'
                        . '</div>',
        ]);
        app_log('subscribe', "欢迎邮件发送成功 -> {$email}");
    } else {
        app_log('subscribe', "警告：RESEND_API_KEY 未配置，跳过欢迎邮件。");
    }
} catch (\Throwable $e) {
    app_log('subscribe', "欢迎邮件发送失败 -> {$email}: " . $e->getMessage());
}

echo json_encode(['message' => '订阅成功！驿站将在更新时向您推送~'], JSON_UNESCAPED_UNICODE);
