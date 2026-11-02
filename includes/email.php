<?php
// ============================================================
// 邮件发送辅助 (people-mail API)
//
// 异步发送机制：
//   所有 sendEmail* 调用只负责把邮件“加入进程内队列”并立即返回，
//   不阻塞当前请求；当 HTTP 响应已经发送给客户端后，
//   register_shutdown_function 回调会利用 fastcgi_finish_request
//   在后台逐封发送队列中的邮件。
// ============================================================

if (!isset($GLOBALS['__mail_queue'])) {
    $GLOBALS['__mail_queue'] = [];
}

// 注册后台发送器（进程内只注册一次）
function registerAsyncMailFlush(): void {
    if (getenv('OPEN_FOHOK_MAIL_SYNC')) {
        // 提供开关：需要同步发送（如 CLI 脚本）时设置 OPEN_FOHOK_MAIL_SYNC=1
        return;
    }
    if (defined('__ASYNC_MAIL_FLUSH_REGISTERED')) return;
    define('__ASYNC_MAIL_FLUSH_REGISTERED', 1);

    register_shutdown_function(function () {
        $queue = $GLOBALS['__mail_queue'] ?? [];
        $GLOBALS['__mail_queue'] = [];
        if (empty($queue)) return;

        // 先尽快释放给客户端的 HTTP 响应
        try {
            while (ob_get_level()) { @ob_end_flush(); }
            @flush();
            if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
        } catch (\Throwable) {}

        @ignore_user_abort(true);
        @set_time_limit(120);
        foreach ($queue as $job) {
            try { dispatchMailJob($job); } catch (\Throwable) {}
        }
    });
}

// 将一封邮件加入异步队列
function enqueueMail(array $payload): bool {
    $GLOBALS['__mail_queue'][] = $payload;
    registerAsyncMailFlush();
    return true;
}

// 真正通过 people-mail API 发送（低层，不进队列）
function dispatchMailJob(array $payload): bool {
    $ch = curl_init(MAIL_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Auth-Code: ' . MAIL_AUTH_CODE,
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

// 构建 people-mail 载荷
function buildMailPayload(string|array $toEmail, string $toName, string $subject, string $htmlBody): array {
    $toArray = is_array($toEmail) ? $toEmail : [$toEmail];
    return [
        'smtp_host'   => MAIL_SMTP_HOST,
        'smtp_port'   => MAIL_SMTP_PORT,
        'smtp_secure' => MAIL_SMTP_SECURE,
        'username'    => MAIL_SMTP_USERNAME,
        'from_email'  => MAIL_FROM_EMAIL,
        'from_name'   => MAIL_FROM_NAME,
        'to'          => $toArray,
        'subject'     => $subject,
        'body'        => $htmlBody,
        'is_html'     => true,
    ];
}

function sendEmail($toEmail, string $toName, string $subject, string $htmlBody): bool {
    $payload = buildMailPayload($toEmail, $toName, $subject, $htmlBody);
    return enqueueMail($payload);
}

/** 发送管理员文本邮件 */
function sendSimpleEmail(string|array $toEmail, string $subject, string $body): bool {
    $html = '<div style="font-family:monospace;white-space:pre-wrap;padding:20px;color:#e5e5e5;background:#0a0a0a">' . htmlspecialchars($body) . '</div>';
    return sendEmail($toEmail, '管理员', $subject, $html);
}

/** 通知用户：项目已提交审核 */
function sendProjectSubmittedToUser(array $user, array $project): void {
    $name    = htmlspecialchars($user['name'] ?? '开发者');
    $proj    = htmlspecialchars($project['name']);
    $ver     = htmlspecialchars($project['version']);
    $subject = "【文谶驿站】您的项目「{$proj}」已提交审核";
    $html = <<<HTML
<div style="font-family:'PingFang SC',sans-serif;max-width:600px;margin:0 auto;background:#0a0a0a;color:#e5e5e5;border:1px solid #222;border-radius:8px;overflow:hidden;">
  <div style="background:#111;padding:24px 32px;border-bottom:1px solid #222;">
    <h1 style="margin:0;font-size:20px;color:#fff;letter-spacing:0.05em;">文谶驿站 · 开发者平台</h1>
  </div>
  <div style="padding:32px;">
    <p>您好，<strong>{$name}</strong>！</p>
    <p>您提交的项目 <strong>「{$proj}」v{$ver}</strong> 已成功进入审核队列，我们将在 1-3 个工作日内完成审核。</p>
    <p>审核通过后，您的项目将在开放平台首页展示。若有疑问，请联系管理员。</p>
    <p style="margin-top:32px;color:#666;font-size:13px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    sendEmail($user['email'], $name, $subject, $html);
}

/** 通知管理员：有新项目待审核 */
function sendProjectSubmittedToAdmin(array $user, array $project): void {
    $dev      = htmlspecialchars($user['name'] ?? $user['email']);
    $proj     = htmlspecialchars($project['name']);
    $ver      = htmlspecialchars($project['version']);
    $url      = htmlspecialchars($project['project_url']);
    $desc     = nl2br(htmlspecialchars($project['description']));
    $subject  = "【开放平台】新项目待审核：{$proj}";
    $adminUrl = APP_URL . '/admin/';
    $html = <<<HTML
<div style="font-family:'PingFang SC',sans-serif;max-width:600px;margin:0 auto;background:#0a0a0a;color:#e5e5e5;border:1px solid #222;border-radius:8px;overflow:hidden;">
  <div style="background:#111;padding:24px 32px;border-bottom:1px solid #222;">
    <h1 style="margin:0;font-size:20px;color:#fff;">文谶驿站 · 管理通知</h1>
  </div>
  <div style="padding:32px;">
    <p>有新项目待审核，请及时处理：</p>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <tr><td style="padding:8px 0;color:#888;width:90px;">项目名称</td><td style="padding:8px 0;"><strong>{$proj}</strong></td></tr>
      <tr><td style="padding:8px 0;color:#888;">版本</td><td style="padding:8px 0;">{$ver}</td></tr>
      <tr><td style="padding:8px 0;color:#888;">提交者</td><td style="padding:8px 0;">{$dev}</td></tr>
      <tr><td style="padding:8px 0;color:#888;">项目地址</td><td style="padding:8px 0;"><a href="{$url}" style="color:#60a5fa;">{$url}</a></td></tr>
      <tr><td style="padding:8px 0;color:#888;vertical-align:top;">项目介绍</td><td style="padding:8px 0;">{$desc}</td></tr>
    </table>
    <div style="margin-top:24px;">
      <a href="{$adminUrl}" style="display:inline-block;background:#fff;color:#000;padding:10px 24px;border-radius:4px;text-decoration:none;font-weight:600;font-size:14px;">前往后台审核</a>
    </div>
    <p style="margin-top:32px;color:#666;font-size:13px;">此邮件由系统自动发送。</p>
  </div>
</div>
HTML;
    sendEmail(ADMIN_EMAILS, '管理员', $subject, $html);
}

/** 通知用户：审核结果 */
function sendProjectReviewResult(array $user, array $project): void {
    $name    = htmlspecialchars($user['name'] ?? '开发者');
    $proj    = htmlspecialchars($project['name']);
    $status  = $project['status'] === 'approved' ? '通过' : '拒绝';
    $color   = $project['status'] === 'approved' ? '#4ade80' : '#f87171';
    $reason  = $project['reject_reason'] ? '<p>原因：' . htmlspecialchars($project['reject_reason']) . '</p>' : '';
    $subject = "【文谶驿站】您的项目「{$proj}」审核{$status}";
    $html = <<<HTML
<div style="font-family:'PingFang SC',sans-serif;max-width:600px;margin:0 auto;background:#0a0a0a;color:#e5e5e5;border:1px solid #222;border-radius:8px;overflow:hidden;">
  <div style="background:#111;padding:24px 32px;border-bottom:1px solid #222;">
    <h1 style="margin:0;font-size:20px;color:#fff;">文谶驿站 · 审核通知</h1>
  </div>
  <div style="padding:32px;">
    <p>您好，<strong>{$name}</strong>！</p>
    <p>您的项目 <strong>「{$proj}」</strong> 审核结果：<span style="color:{$color};font-weight:700;">{$status}</span></p>
    {$reason}
    <p style="margin-top:32px;color:#666;font-size:13px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    sendEmail($user['email'], $name, $subject, $html);
}

/** 通知用户：项目审核通过 */
function sendProjectApproved(array $user, array $project): void {
    $name    = htmlspecialchars($user['name'] ?? '开发者');
    $proj    = htmlspecialchars($project['name'] ?? '');
    $subject = "【文谶驿站】您的项目「{$proj}」审核通过";
    $html = <<<HTML
<div style="font-family:'PingFang SC',sans-serif;max-width:600px;margin:0 auto;background:#0a0a0a;color:#e5e5e5;border:1px solid #222;border-radius:8px;overflow:hidden;">
  <div style="background:#111;padding:24px 32px;border-bottom:1px solid #222;">
    <h1 style="margin:0;font-size:20px;color:#fff;">文谶驿站 · 审核通知</h1>
  </div>
  <div style="padding:32px;">
    <p>您好，<strong>{$name}</strong>！</p>
    <p>恭喜！您的项目 <strong>「{$proj}」</strong> 已通过审核，现已展示在开放平台首页。</p>
    <p>感谢您的贡献，期待更多精彩作品！</p>
    <p style="margin-top:32px;color:#666;font-size:13px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    sendEmail($user['email'], $name, $subject, $html);
}

/** 通知用户：项目审核被拒绝 */
function sendProjectRejected(array $user, array $project, string $reason): void {
    $name       = htmlspecialchars($user['name'] ?? '开发者');
    $proj       = htmlspecialchars($project['name'] ?? '');
    $reasonText = htmlspecialchars($reason);
    $subject    = "【文谶驿站】您的项目「{$proj}」审核未通过";
    $html = <<<HTML
<div style="font-family:'PingFang SC',sans-serif;max-width:600px;margin:0 auto;background:#0a0a0a;color:#e5e5e5;border:1px solid #222;border-radius:8px;overflow:hidden;">
  <div style="background:#111;padding:24px 32px;border-bottom:1px solid #222;">
    <h1 style="margin:0;font-size:20px;color:#fff;">文谶驿站 · 审核通知</h1>
  </div>
  <div style="padding:32px;">
    <p>您好，<strong>{$name}</strong>！</p>
    <p>很遗憾，您的项目 <strong>「{$proj}」</strong> 未通过审核。</p>
    <div style="background:#1a1a1a;padding:16px;border-left:3px solid #f87171;margin:20px 0;">
      <p style="margin:0;color:#f87171;"><strong>拒绝原因：</strong></p>
      <p style="margin:8px 0 0 0;">{$reasonText}</p>
    </div>
    <p>您可以根据以上反馈修改后重新提交。如有疑问，请联系管理员。</p>
    <p style="margin-top:32px;color:#666;font-size:13px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    sendEmail($user['email'], $name, $subject, $html);
}

/** 通知用户：项目已被管理员删除 */
function sendProjectDeletedByAdmin(array $user, array $project, string $reason): void {
    $name       = htmlspecialchars($user['name'] ?? '开发者');
    $email      = $user['email'] ?? '';
    $proj       = htmlspecialchars($project['name'] ?? '');
    $reasonText = htmlspecialchars($reason);
    $subject    = "【文谶驿站】您的项目「{$proj}」已被删除";
    $html = <<<HTML
<div style="font-family:'PingFang SC',sans-serif;max-width:600px;margin:0 auto;background:#0a0a0a;color:#e5e5e5;border:1px solid #222;border-radius:8px;overflow:hidden;">
  <div style="background:#111;padding:24px 32px;border-bottom:1px solid #222;">
    <h1 style="margin:0;font-size:20px;color:#fff;">文谶驿站 · 项目通知</h1>
  </div>
  <div style="padding:32px;">
    <p>您好，<strong>{$name}</strong>！</p>
    <p>很遗憾地通知您，您的项目 <strong>「{$proj}」</strong> 已被管理员删除。</p>
    <div style="background:#1a1a1a;padding:16px;border-left:3px solid #f87171;margin:20px 0;">
      <p style="margin:0;color:#f87171;"><strong>删除原因：</strong></p>
      <p style="margin:8px 0 0 0;">{$reasonText}</p>
    </div>
    <p>如有疑问，请联系管理员。</p>
    <p style="margin-top:32px;color:#666;font-size:13px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    if ($email) {
        sendEmail($email, $name, $subject, $html);
    }
}

/** 发送邮箱登录验证链接 */
function sendEmailLoginLink(string $toEmail, string $toName, string $token): void {
    $name     = htmlspecialchars($toName ?: '用户');
    $link     = APP_URL . '/tyopvr.php?token=' . urlencode($token);
    $linkEsc  = htmlspecialchars($link);
    $subject  = '【文谶驿站】邮箱登录验证';
    $html = <<<HTML
<div style="font-family:'PingFang SC',sans-serif;max-width:600px;margin:0 auto;background:#0a0a0a;color:#e5e5e5;border:1px solid #222;border-radius:8px;overflow:hidden;">
  <div style="background:#111;padding:24px 32px;border-bottom:1px solid #222;">
    <h1 style="margin:0;font-size:20px;color:#fff;letter-spacing:0.05em;">文谶驿站 · 邮箱登录</h1>
  </div>
  <div style="padding:32px;">
    <p>您好，<strong>{$name}</strong>！</p>
    <p>您正在使用邮箱登录 <strong>文谶驿站 - 开发者开放平台</strong>，请点击下方按钮完成身份验证：</p>
    <div style="margin:28px 0;">
      <a href="{$linkEsc}" style="display:inline-block;background:#e5e5e5;color:#050505;padding:12px 32px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;letter-spacing:0.03em;">验证登录</a>
    </div>
    <p style="font-size:13px;color:#666;">或复制以下链接到浏览器打开：</p>
    <p style="font-size:12px;color:#555;word-break:break-all;margin-top:6px;">{$linkEsc}</p>
    <div style="margin-top:28px;padding-top:20px;border-top:1px solid #1a1a1a;">
      <p style="font-size:12px;color:#555;margin:0;">链接有效期 <strong style="color:#fbbf24;">3 分钟</strong>，请尽快操作。</p>
      <p style="font-size:12px;color:#555;margin:6px 0 0 0;"> 若非本人操作，请忽略此邮件，账号安全不受影响。</p>
    </div>
    <p style="margin-top:32px;color:#666;font-size:12px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    sendEmail($toEmail, $toName ?: '用户', $subject, $html);
}

// ── 订阅者项目通知邮件 ─────────────────────────────────

function sendSubscriberProjectEmail(string $toEmail, string $toName, string $developerName, string $projectName, int $projectId): void {
    $subject = $developerName . ' 发布了新项目：' . $projectName;
    $projectUrl = 'https://fohok.xin/prodeta.php?id=' . $projectId;
    $nameEsc = htmlspecialchars($toName);
    $devEsc = htmlspecialchars($developerName);
    $projEsc = htmlspecialchars($projectName);
    $urlEsc = htmlspecialchars($projectUrl);

    $html = <<<HTML
<div style="max-width:520px;margin:0 auto;background:#050505;color:#e5e5e5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;border-radius:8px;overflow:hidden;border:1px solid #1a1a1a;">
  <div style="background:#171717;padding:18px 24px;border-bottom:1px solid #222;">
    <h1 style="margin:0;font-size:18px;color:#fff;letter-spacing:0.04em;">文谶驿站 · 项目更新</h1>
  </div>
  <div style="padding:32px;">
    <p>您好，<strong>{$nameEsc}</strong>！</p>
    <p>您订阅的开发者 <strong style="color:#fbbf24;">{$devEsc}</strong> 刚刚发布了一个新项目：</p>
    <div style="margin:24px 0;background:#0f0f0f;border:1px solid #1a1a1a;border-radius:6px;padding:20px;">
      <p style="margin:0;font-size:16px;font-weight:600;color:#fff;">{$projEsc}</p>
      <p style="margin:8px 0 0 0;font-size:13px;color:#888;">点击下方按钮查看详情</p>
    </div>
    <div style="margin:28px 0;">
      <a href="{$urlEsc}" style="display:inline-block;background:#fbbf24;color:#050505;padding:12px 32px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;letter-spacing:0.03em;">查看项目</a>
    </div>
    <div style="margin-top:28px;padding-top:20px;border-top:1px solid #1a1a1a;">
      <p style="font-size:12px;color:#555;margin:0;">你可以在 <a href="https://fohok.xin/profile.php" style="color:#fbbf24;">个人中心</a> 管理订阅。</p>
    </div>
    <p style="margin-top:32px;color:#666;font-size:12px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    sendEmail($toEmail, $toName ?: '用户', $subject, $html);
}

/** 订阅者项目更新通知邮件 */
function sendSubscriberProjectUpdateEmail(string $toEmail, string $toName, string $developerName, string $projectName, int $projectId): void {
    $subject = $developerName . ' 更新了项目：' . $projectName;
    $projectUrl = 'https://fohok.xin/prodeta.php?id=' . $projectId;
    $nameEsc = htmlspecialchars($toName);
    $devEsc = htmlspecialchars($developerName);
    $projEsc = htmlspecialchars($projectName);
    $urlEsc = htmlspecialchars($projectUrl);

    $html = <<<HTML
<div style="max-width:520px;margin:0 auto;background:#050505;color:#e5e5e5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;border-radius:8px;overflow:hidden;border:1px solid #1a1a1a;">
  <div style="background:#171717;padding:18px 24px;border-bottom:1px solid #222;">
    <h1 style="margin:0;font-size:18px;color:#fff;letter-spacing:0.04em;">文谶驿站 · 项目更新</h1>
  </div>
  <div style="padding:32px;">
    <p>您好，<strong>{$nameEsc}</strong>！</p>
    <p>您订阅的开发者 <strong style="color:#fbbf24;">{$devEsc}</strong> 更新了项目：</p>
    <div style="margin:24px 0;background:#0f0f0f;border:1px solid #1a1a1a;border-radius:6px;padding:20px;">
      <p style="margin:0;font-size:16px;font-weight:600;color:#fff;">{$projEsc}</p>
      <p style="margin:8px 0 0 0;font-size:13px;color:#888;">该项目发布了新版本，点击下方按钮查看详情</p>
    </div>
    <div style="margin:28px 0;">
      <a href="{$urlEsc}" style="display:inline-block;background:#fbbf24;color:#050505;padding:12px 32px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;letter-spacing:0.03em;">查看项目</a>
    </div>
    <div style="margin-top:28px;padding-top:20px;border-top:1px solid #1a1a1a;">
      <p style="font-size:12px;color:#555;margin:0;">你可以在 <a href="https://fohok.xin/profile.php" style="color:#fbbf24;">个人中心</a> 管理订阅。</p>
    </div>
    <p style="margin-top:32px;color:#666;font-size:12px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    sendEmail($toEmail, $toName ?: '用户', $subject, $html);
}

/** 作者被限制发布通知邮件 */
function sendRestrictPublishEmail(string $toEmail, string $toName, string $projectName, string $durationText, string $reason): void {
    $subject = '【文谶驿站】你的项目「' . $projectName . '」因违规被限制发布';
    $nameEsc = htmlspecialchars($toName);
    $projEsc = htmlspecialchars($projectName);
    $durEsc  = htmlspecialchars($durationText);
    $reasonEsc = htmlspecialchars($reason);

    $html = <<<HTML
<div style="max-width:520px;margin:0 auto;background:#050505;color:#e5e5e5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;border-radius:8px;overflow:hidden;border:1px solid #1a1a1a;">
  <div style="background:#dc2626;padding:18px 24px;border-bottom:1px solid #991b1b;">
    <h1 style="margin:0;font-size:18px;color:#fff;letter-spacing:0.04em;">文谶驿站 · 内容审核</h1>
  </div>
  <div style="padding:32px;">
    <p>您好，<strong>{$nameEsc}</strong>！</p>
    <p>你的项目 <strong style="color:#f87171;">{$projEsc}</strong> 因用户举报经审核被处以限制发布处罚。</p>
    <div style="margin:24px 0;background:#0f0f0f;border:1px solid #1a1a1a;border-radius:6px;padding:20px;">
      <p style="margin:0;font-size:14px;color:#ccc;">限制时长：<strong style="color:#fbbf24;">{$durEsc}</strong></p>
      <p style="margin:12px 0 0 0;font-size:14px;color:#ccc;">限制原因：{$reasonEsc}</p>
    </div>
    <div style="margin-top:28px;padding-top:20px;border-top:1px solid #1a1a1a;">
      <p style="font-size:12px;color:#555;margin:0;">如有疑问，请联系管理员。</p>
    </div>
    <p style="margin-top:32px;color:#666;font-size:12px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    sendEmail($toEmail, $toName ?: '用户', $subject, $html);
}

/** 作者被限制登录通知邮件 */
function sendRestrictLoginEmail(string $toEmail, string $toName, string $durationText, string $reason): void {
    $subject = '【文谶驿站】你的账号已被限制登录';
    $nameEsc = htmlspecialchars($toName);
    $durEsc  = htmlspecialchars($durationText);
    $reasonEsc = htmlspecialchars($reason);

    $html = <<<HTML
<div style="max-width:520px;margin:0 auto;background:#050505;color:#e5e5e5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;border-radius:8px;overflow:hidden;border:1px solid #1a1a1a;">
  <div style="background:#dc2626;padding:18px 24px;border-bottom:1px solid #991b1b;">
    <h1 style="margin:0;font-size:18px;color:#fff;letter-spacing:0.04em;">文谶驿站 · 账号限制</h1>
  </div>
  <div style="padding:32px;">
    <p>您好，<strong>{$nameEsc}</strong>！</p>
    <p>你的账号因违规行为被管理员限制登录。</p>
    <div style="margin:24px 0;background:#0f0f0f;border:1px solid #1a1a1a;border-radius:6px;padding:20px;">
      <p style="margin:0;font-size:14px;color:#ccc;">限制时长：<strong style="color:#fbbf24;">{$durEsc}</strong></p>
      <p style="margin:12px 0 0 0;font-size:14px;color:#ccc;">限制原因：{$reasonEsc}</p>
    </div>
    <div style="margin-top:28px;padding-top:20px;border-top:1px solid #1a1a1a;">
      <p style="font-size:12px;color:#555;margin:0;">如有疑问，请联系管理员。</p>
    </div>
    <p style="margin-top:32px;color:#666;font-size:12px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    sendEmail($toEmail, $toName ?: '用户', $subject, $html);
}

/** 举报结果通知举报人邮件 */
function sendReportResultEmail(string $toEmail, string $projectName, string $resultLabel, string $note): void {
    $subject = '【文谶驿站】你举报的项目「' . $projectName . '」已处理';
    $projEsc = htmlspecialchars($projectName);
    $resultEsc = htmlspecialchars($resultLabel);
    $noteEsc = htmlspecialchars($note);

    $html = <<<HTML
<div style="max-width:520px;margin:0 auto;background:#050505;color:#e5e5e5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;border-radius:8px;overflow:hidden;border:1px solid #1a1a1a;">
  <div style="background:#171717;padding:18px 24px;border-bottom:1px solid #222;">
    <h1 style="margin:0;font-size:18px;color:#fff;letter-spacing:0.04em;">文谶驿站 · 举报处理结果</h1>
  </div>
  <div style="padding:32px;">
    <p>感谢你的举报反馈！</p>
    <p>你举报的项目 <strong style="color:#fbbf24;">{$projEsc}</strong> 已由管理员处理。</p>
    <div style="margin:24px 0;background:#0f0f0f;border:1px solid #1a1a1a;border-radius:6px;padding:20px;">
      <p style="margin:0;font-size:14px;color:#ccc;">处理结果：<strong style="color:#fbbf24;">{$resultEsc}</strong></p>
      <p style="margin:12px 0 0 0;font-size:14px;color:#ccc;">管理员备注：{$noteEsc}</p>
    </div>
    <p style="margin-top:32px;color:#666;font-size:12px;">此邮件由系统自动发送，请勿直接回复。</p>
  </div>
</div>
HTML;
    sendEmail($toEmail, '用户', $subject, $html);
}