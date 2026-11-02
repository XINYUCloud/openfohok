<?php
/**
 * 文谶驿站 · 新文速递邮件推送脚本
 * 由 collector.py 在检测到新文章时通过 CLI 调用：
 *   php send_emails.php '<json 编码的新文章数组>'
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

app_log('mail', '=== 推送任务启动 ===');

if (empty($argv[1])) {
    app_log('mail', '错误：未收到文章参数，argv[1] 为空。');
    fwrite(STDERR, "No article payload provided.\n");
    exit(1);
}

$newArticles = json_decode($argv[1], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    app_log('mail', '错误：文章 JSON 解析失败 - ' . json_last_error_msg());
    fwrite(STDERR, "Invalid JSON payload: " . json_last_error_msg() . "\n");
    exit(1);
}

if (empty($newArticles)) {
    app_log('mail', '无新文章，任务结束。');
    exit(0);
}

$subscribersFile = __DIR__ . '/data/rss-email.json';
if (!file_exists($subscribersFile)) {
    app_log('mail', '无订阅者文件，任务结束。');
    exit(0);
}

$emails = json_decode(file_get_contents($subscribersFile), true);
if (!is_array($emails) || empty($emails)) {
    app_log('mail', '订阅者列表为空，任务结束。');
    exit(0);
}

app_log('mail', '本次推送文章数：' . count($newArticles) . '，订阅者数：' . count($emails));

if (!defined('RESEND_API_KEY') || RESEND_API_KEY === '') {
    app_log('mail', '错误：RESEND_API_KEY 未配置。');
    fwrite(STDERR, "RESEND_API_KEY missing.\n");
    exit(1);
}

$resend = Resend::client(RESEND_API_KEY);

// 构造 HTML 内容
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$htmlContent = "<div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,\"Noto Serif SC\",sans-serif; max-width:640px; margin:0 auto;'>";
$htmlContent .= "<h2 style='color:#2d6a4f; border-bottom:2px solid #2d6a4f; padding-bottom:10px;'>文谶驿站 · 墨引思潮：新文寄达</h2>";
foreach ($newArticles as $a) {
    $title    = e($a['title'] ?? '无标题');
    $link     = e($a['link'] ?? '#');
    $site     = e($a['site_name'] ?? '');
    $author   = e($a['author'] ?? '佚名');
    $pubTime  = e($a['pub_time'] ?? '');
    $summary  = e($a['summary'] ?? '');
    $imageUrl = e($a['image_url'] ?? '');

    $imageHtml = $imageUrl
        ? "<img src='{$imageUrl}' style='width:100%; max-height:220px; object-fit:cover; border-radius:8px; margin-bottom:10px; display:block;' alt=''>"
        : '';

    $htmlContent .= "
    <div style='border-bottom:1px solid #eee; padding:15px 0;'>
        {$imageHtml}
        <h3 style='margin:0;'><a href='{$link}' style='color:#2d6a4f; text-decoration:none;'>{$title}</a></h3>
        <p style='color:#666; font-size:13px; margin:6px 0;'>来自：{$site} | 作者：{$author} | 时间：{$pubTime}</p>
        <p style='color:#333; font-size:14px; line-height:1.6;'>摘要：{$summary}...</p>
    </div>";
}
$htmlContent .= "<p style='font-size:12px; color:#999; margin-top:20px;'>您可以前往 <a href='" . SITE_BASE_URL . "/page.html' style='color:#2d6a4f;'>文谶驿站</a> 查看更多。如需取消订阅，请联系站点管理员。</p>";
$htmlContent .= "</div>";

$fromHeader = RESEND_FROM_NAME . ' <' . RESEND_FROM_EMAIL . '>';

$successCount = 0;
$failCount = 0;

foreach ($emails as $toEmail) {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        app_log('mail', "跳过无效邮箱: {$toEmail}");
        continue;
    }
    try {
        $result = $resend->emails->send([
            'from'    => $fromHeader,
            'to'      => [$toEmail],
            'subject' => '您有新的文谶快讯，请展卷细读',
            'html'    => $htmlContent,
        ]);
        $successCount++;
        $resultId = is_object($result) && isset($result->id) ? $result->id : (is_array($result) ? ($result['id'] ?? '') : '');
        app_log('mail', "发送成功 -> {$toEmail} (id: {$resultId})");
        echo "Sent to {$toEmail}\n";
    } catch (\Throwable $e) {
        $failCount++;
        app_log('mail', "发送失败 -> {$toEmail}: " . $e->getMessage());
        echo "Failed for {$toEmail}: " . $e->getMessage() . "\n";
    }
}

app_log('mail', "=== 推送任务结束：成功 {$successCount}，失败 {$failCount} ===");
