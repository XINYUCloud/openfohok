<?php
// ============================================================
// 文谶账号站内信配置示例
//
// 复制为 config/wenchan.php 后填写真实值：
//   cp config/wenchan.example.php config/wenchan.php
//
// config/wenchan.php 已被 .gitignore 忽略，不会提交到仓库。
// 也可以改用环境变量 WENCHAN_DEVELOPER_ID /
// WENCHAN_NOTIFICATION_READ_ENABLED 配置。
// ============================================================

// 开发者编号：文谶账号中心 → 开发者中心 → 开发者身份（形如 fhkdev_...）
define('WENCHAN_DEVELOPER_ID', 'fhkdev_your_developer_id');

// 账号中心「通知订阅」审核通过，并为本站应用绑定 notification:read 后再改为 true。
// 开启后，本站所有文谶账号授权都会请求 notification:read。
define('WENCHAN_NOTIFICATION_READ_ENABLED', false);
