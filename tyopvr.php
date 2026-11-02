<?php
// 邮箱链接登录已下线，统一使用文谶账户 OAuth 登录
require_once __DIR__ . '/bootstrap.php';

header('Location: /login.php?error=migrated', true, 302);
exit;
