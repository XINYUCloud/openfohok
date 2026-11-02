<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/wenchan_oauth.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/captcha.php';
require_once __DIR__ . '/includes/notification.php';
require_once __DIR__ . '/includes/forwarding.php';
require_once __DIR__ . '/includes/wenchan_notifications.php';

securitySendHeaders();
