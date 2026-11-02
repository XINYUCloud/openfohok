-- ============================================================
-- 文谶驿站 · open-user 数据库结构
-- 仅包含表结构、索引、外键与存储过程，不包含任何业务数据。
-- 适用于 MySQL 5.7+
--
-- 导入方式：
--   mysql -u <user> -p <database> < database/schema.sql
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;

DELIMITER $$
-- 存储过程
CREATE PROCEDURE `AddColumnIfNotExists` (IN `p_table` VARCHAR(64), IN `p_column` VARCHAR(64), IN `p_definition` TEXT, IN `p_after` VARCHAR(64))   BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        IF p_after IS NOT NULL AND p_after != '' THEN
            SET @sql = CONCAT(@sql, ' AFTER `', p_after, '`');
        END IF;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE `AddIndexIfNotExists` (IN `p_table` VARCHAR(64), IN `p_index` VARCHAR(64), IN `p_columns` TEXT)   BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE `DropColumnIfExists` (IN `p_table` VARCHAR(64), IN `p_column` VARCHAR(64))   BEGIN
    IF EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- 表的结构 `activity_logs`

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT '用户ID（NULL=游客）',
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型',
  `target_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '目标类型',
  `target_id` int(10) UNSIGNED DEFAULT NULL COMMENT '目标ID',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User-Agent',
  `metadata` text COLLATE utf8mb4_unicode_ci COMMENT '附加数据(JSON)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户活动日志表';

-- 表的结构 `announcements`

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL COMMENT '公告标题',
  `content` text NOT NULL COMMENT '公告内容（支持 HTML）',
  `severity` enum('notice','info','important','warning') NOT NULL DEFAULT 'notice' COMMENT '通知/提示/重要/警告',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告表';

-- 表的结构 `app_auth_codes`

CREATE TABLE `app_auth_codes` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `email` varchar(191) NOT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified` tinyint(4) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `app_login_codes`

CREATE TABLE `app_login_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `code` char(6) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='App邮箱登录验证码';

-- 表的结构 `app_oauth_tickets`

CREATE TABLE `app_oauth_tickets` (
  `ticket` char(32) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `token` char(64) DEFAULT NULL,
  `user_json` text,
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `oauth_state` char(32) DEFAULT NULL,
  `oauth_verifier` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='App Webview OAuth轮询票据';

-- 表的结构 `app_tokens`

CREATE TABLE `app_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `device` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_seen` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `app_updates`

CREATE TABLE `app_updates` (
  `id` int(11) NOT NULL,
  `app_name` varchar(255) NOT NULL COMMENT 'App 名称',
  `version` varchar(50) NOT NULL COMMENT '版本号，如 1.0.1',
  `changelog` text COMMENT '更新介绍/更新日志',
  `package_size` varchar(50) DEFAULT NULL COMMENT '包体大小，如 12.5 MB',
  `download_url` varchar(500) NOT NULL COMMENT '包体下载地址',
  `is_force` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否强制更新 0=否 1=是',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='App 版本更新表';

-- 表的结构 `email_login_tokens`

CREATE TABLE `email_login_tokens` (
  `token` varchar(64) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `verified` tinyint(1) DEFAULT '0' COMMENT '0=待验证 1=已验证',
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `email_register_tokens`

CREATE TABLE `email_register_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `token` varchar(128) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(200) NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `notifications`

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'login/project_submitted/project_approved/project_rejected/project_deleted/admin_message/project_liked/project_commented/comment_replied/new_subscriber/new_project/restricted/welcome/report_submitted/project_reported/project_taken_down',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci COMMENT '通知正文',
  `related_id` int(10) UNSIGNED DEFAULT NULL COMMENT '关联项目ID等',
  `liker_id` int(10) UNSIGNED DEFAULT NULL COMMENT '点赞人用户ID',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 表的结构 `oauth_access_tokens`

CREATE TABLE `oauth_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `app_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '应用 ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户 ID',
  `access_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '访问令牌（64 位十六进制）',
  `expires_at` datetime NOT NULL COMMENT '令牌过期时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` datetime DEFAULT NULL COMMENT '最后使用时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth access_token 表';

-- 表的结构 `oauth_apps`

CREATE TABLE `oauth_apps` (
  `id` int(10) UNSIGNED NOT NULL,
  `app_id` varchar(64) NOT NULL COMMENT '应用ID，公开使用',
  `app_secret` varchar(128) NOT NULL COMMENT '应用校验密钥，仅创建时展示一次',
  `name` varchar(100) NOT NULL COMMENT '应用名称',
  `version` varchar(50) DEFAULT NULL COMMENT '应用版本',
  `description` text COMMENT '应用介绍',
  `icon` varchar(500) DEFAULT NULL COMMENT '应用图标URL',
  `privacy_url` varchar(500) DEFAULT NULL COMMENT '隐私条款链接',
  `help_url` varchar(500) DEFAULT NULL COMMENT '帮助政策链接',
  `developer_email` varchar(200) DEFAULT NULL COMMENT '开发者邮箱',
  `callback_url` varchar(500) NOT NULL COMMENT '授权回调链接',
  `status` enum('active','disabled') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT '创建的管理员用户ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `oauth_authorizations`

CREATE TABLE `oauth_authorizations` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(64) NOT NULL COMMENT '一次性授权码',
  `app_id` varchar(64) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `state` varchar(64) DEFAULT NULL COMMENT '第三方传入的 state 参数',
  `code_challenge` varchar(255) DEFAULT NULL COMMENT 'PKCE code_challenge',
  `code_challenge_method` varchar(10) DEFAULT NULL COMMENT 'PKCE 方法：S256 / PLAIN',
  `used` tinyint(1) NOT NULL DEFAULT '0' COMMENT '授权码是否已被校验接口核销',
  `used_at` datetime DEFAULT NULL COMMENT '核销时间',
  `expires_at` datetime NOT NULL COMMENT '授权码过期时间（10分钟）',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `oauth_rate_limits`

CREATE TABLE `oauth_rate_limits` (
  `id` int(10) UNSIGNED NOT NULL,
  `identifier` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标识符（IP 或复合键）',
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型（authorize/verify）',
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT '1' COMMENT '尝试次数',
  `window_start` datetime NOT NULL COMMENT '时间窗口起始',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 频率限制表';

-- 表的结构 `oauth_settings`

CREATE TABLE `oauth_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置键名',
  `setting_value` text COLLATE utf8mb4_unicode_ci COMMENT '配置值',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 系统配置表';

-- 表的结构 `oauth_states`

CREATE TABLE `oauth_states` (
  `state` varchar(64) NOT NULL,
  `wenchan_verifier` varchar(255) NOT NULL DEFAULT '',
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `oauth_userinfo_logs`

CREATE TABLE `oauth_userinfo_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `access_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '访问令牌',
  `app_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '应用 ID',
  `success` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否请求成功',
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '失败原因或成功标识',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '请求方 IP',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '请求方 User-Agent',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 用户信息同步接口审计日志';

-- 表的结构 `oauth_verify_logs`

CREATE TABLE `oauth_verify_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '授权码',
  `app_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '应用 ID（新方式下记录）',
  `success` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否校验成功',
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '失败原因或成功标识',
  `is_legacy` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否使用旧 md5(secret_hash) 方式',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '请求方 IP',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '请求方 User-Agent',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 校验接口审计日志';

-- 表的结构 `projects`

CREATE TABLE `projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL COMMENT '项目名称',
  `version` varchar(30) NOT NULL COMMENT '版本',
  `release_date` date DEFAULT NULL COMMENT '发布日期',
  `project_url` varchar(500) NOT NULL COMMENT '项目地址',
  `screenshot_task_id` varchar(20) DEFAULT NULL COMMENT '网页截图任务ID',
  `description` text NOT NULL COMMENT '项目介绍',
  `tech_stack` varchar(512) DEFAULT NULL COMMENT '技术栈，逗号分隔',
  `cover_url` varchar(500) DEFAULT NULL,
  `download_url` varchar(500) DEFAULT NULL,
  `download_filename` varchar(255) DEFAULT NULL,
  `download_filetype` varchar(50) DEFAULT NULL,
  `download_filesize` varchar(50) DEFAULT NULL,
  `developer_website` varchar(500) DEFAULT NULL COMMENT '开发者网站',
  `contact_email` varchar(200) DEFAULT NULL COMMENT '联系邮箱',
  `contact_phone` varchar(30) DEFAULT NULL COMMENT '联系电话',
  `contact_qq` varchar(20) DEFAULT NULL COMMENT 'QQ',
  `contact_wechat` varchar(50) DEFAULT NULL COMMENT '微信',
  `status` enum('pending','approved','rejected','taken_down') NOT NULL DEFAULT 'pending' COMMENT '项目状态',
  `reject_reason` text COMMENT '拒绝原因',
  `is_hidden` tinyint(1) DEFAULT '0' COMMENT '用户隐藏',
  `view_count` int(10) UNSIGNED DEFAULT '0',
  `comment_count` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '评论数（冗余）',
  `favorite_count` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '收藏数（冗余）',
  `rating_avg` decimal(3,1) DEFAULT '0.0' COMMENT '平均评分（冗余）',
  `rating_count` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '评分人数（冗余）',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approved_at` datetime DEFAULT NULL,
  `is_guest` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否游客提交 0-否 1-是'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `project_comments`

CREATE TABLE `project_comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL COMMENT '项目ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '评论者用户ID',
  `parent_id` int(10) UNSIGNED DEFAULT NULL COMMENT '父评论ID（回复）',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '评论内容',
  `status` enum('visible','deleted','hidden') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'visible' COMMENT '评论状态',
  `is_edited` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已编辑',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目评论表';

-- 表的结构 `project_likes`

CREATE TABLE `project_likes` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 表的结构 `project_versions`

CREATE TABLE `project_versions` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `version` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '版本号',
  `release_date` date DEFAULT NULL COMMENT '发布日期',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '版本描述',
  `project_url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目地址',
  `developer_website` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '开发者网站',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `changelog` text COLLATE utf8mb4_unicode_ci COMMENT '更新日志',
  `tag` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '版本标签: stable/beta/lts/deprecated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目版本历史';

-- 表的结构 `qrcode_sessions`

CREATE TABLE `qrcode_sessions` (
  `id` varchar(64) NOT NULL COMMENT '二维码唯一标识 session_id',
  `status` enum('pending','scanned','confirmed','expired','cancelled') NOT NULL DEFAULT 'pending' COMMENT '状态',
  `user_id` int(11) DEFAULT NULL COMMENT '扫码确认的用户 ID（confirmed 时填入）',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'PC 端 IP',
  `user_agent` varchar(500) DEFAULT NULL COMMENT 'PC 端 User-Agent',
  `scan_ip` varchar(45) DEFAULT NULL COMMENT '扫码端 IP',
  `scan_ua` varchar(500) DEFAULT NULL COMMENT '扫码端 User-Agent',
  `scan_user_id` int(11) DEFAULT NULL COMMENT '扫码时未确认前记录的用户 ID',
  `confirmed_at` datetime DEFAULT NULL COMMENT '确认时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL COMMENT '过期时间',
  `session_hash` char(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='扫码登录会话表';

-- 表的结构 `rate_limits`

CREATE TABLE `rate_limits` (
  `bucket` char(64) NOT NULL,
  `action` varchar(40) NOT NULL,
  `hits` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `window_start` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `reports`

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL COMMENT '被举报项目ID',
  `reporter_id` int(11) DEFAULT NULL COMMENT '举报人ID（NULL=匿名）',
  `report_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '举报类型: spam/violation/copyright/misleading/other',
  `reason` text COLLATE utf8mb4_unicode_ci COMMENT '其他补充说明',
  `notify_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '举报结果通知邮箱',
  `status` enum('pending','resolved','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '处理状态',
  `admin_id` int(11) DEFAULT NULL COMMENT '处理管理员ID',
  `admin_note` text COLLATE utf8mb4_unicode_ci COMMENT '管理员处理备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 表的结构 `sessions`

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `expires_at` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `users`

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `xgorb_sub` varchar(255) DEFAULT NULL,
  `reg_source` enum('xgorb','email','wenchan','web','guest') NOT NULL DEFAULT 'xgorb',
  `name` varchar(100) DEFAULT NULL,
  `given_name` varchar(50) DEFAULT NULL,
  `family_name` varchar(50) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT '0',
  `picture` varchar(500) DEFAULT NULL,
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `bio` text COMMENT '用户简介',
  `website` varchar(300) DEFAULT NULL COMMENT '用户网站',
  `phone` varchar(30) DEFAULT NULL COMMENT '用户电话',
  `webauthn_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '通行密钥登录开关 0=关闭 1=开启',
  `restrict_reason` text COMMENT '限制原因',
  `login_restricted_until` datetime DEFAULT NULL COMMENT '登录限制截止时间',
  `publish_restricted_until` datetime DEFAULT NULL COMMENT '发布限制截止时间',
  `screenshot_refresh_count` int(11) DEFAULT '0' COMMENT '当日截图刷新次数',
  `screenshot_refresh_date` date DEFAULT NULL COMMENT '截图刷新计数日期',
  `favorite_count` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '收藏项目数（冗余）',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `wenchan_access_token` varchar(128) DEFAULT NULL,
  `wenchan_refresh_token` varchar(128) DEFAULT NULL,
  `wenchan_token_expires_at` datetime DEFAULT NULL,
  `inbox_forward_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `wenchan_oauth_state` varchar(64) DEFAULT NULL,
  `wenchan_uid` bigint(20) UNSIGNED DEFAULT NULL,
  `wenchan_sub` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表的结构 `user_restrictions`

CREATE TABLE `user_restrictions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `restrict_type` enum('publish','login') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '限制类型: publish=限制发布, login=限制登录',
  `duration_minutes` int(11) NOT NULL COMMENT '限制时长(分钟)',
  `reason` text COLLATE utf8mb4_unicode_ci COMMENT '限制原因',
  `admin_id` int(11) DEFAULT NULL COMMENT '操作管理员ID',
  `report_id` int(11) DEFAULT NULL COMMENT '关联举报ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL COMMENT '过期时间',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 表的结构 `user_subscriptions`

CREATE TABLE `user_subscriptions` (
  `id` int(11) NOT NULL,
  `subscriber_id` int(11) NOT NULL COMMENT '订阅者ID',
  `target_user_id` int(11) NOT NULL COMMENT '被订阅者ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 表的结构 `webauthn_credentials`

CREATE TABLE `webauthn_credentials` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `credential_id` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Base64URL 编码的凭据 ID',
  `public_key` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PEM 格式公钥',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '设备名称',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 表的结构 `wenchan_oauth_flows`

CREATE TABLE `wenchan_oauth_flows` (
  `state` char(64) NOT NULL,
  `session_hash` char(64) NOT NULL,
  `code_verifier` varchar(128) NOT NULL,
  `mode` varchar(16) NOT NULL DEFAULT 'login',
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `next_path` varchar(255) DEFAULT NULL,
  `scope` varchar(500) NOT NULL,
  `access_token` char(64) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 转储表的索引

-- 表的索引 `activity_logs`
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

-- 表的索引 `announcements`
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`);

-- 表的索引 `app_auth_codes`
ALTER TABLE `app_auth_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auth_session` (`session_id`);

-- 表的索引 `app_login_codes`
ALTER TABLE `app_login_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_user` (`user_id`);

-- 表的索引 `app_oauth_tickets`
ALTER TABLE `app_oauth_tickets`
  ADD PRIMARY KEY (`ticket`),
  ADD UNIQUE KEY `uniq_state` (`oauth_state`);

-- 表的索引 `app_tokens`
ALTER TABLE `app_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_appt_user` (`user_id`);

-- 表的索引 `app_updates`
ALTER TABLE `app_updates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_version` (`version`),
  ADD KEY `idx_created_at` (`created_at`);

-- 表的索引 `email_login_tokens`
ALTER TABLE `email_login_tokens`
  ADD PRIMARY KEY (`token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

-- 表的索引 `email_register_tokens`
ALTER TABLE `email_register_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_email` (`email`);

-- 表的索引 `notifications`
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_liker_id` (`liker_id`);

-- 表的索引 `oauth_access_tokens`
ALTER TABLE `oauth_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_app_id` (`app_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_access_token` (`access_token`),
  ADD KEY `idx_expires_at` (`expires_at`);

-- 表的索引 `oauth_apps`
ALTER TABLE `oauth_apps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `app_id` (`app_id`);

-- 表的索引 `oauth_authorizations`
ALTER TABLE `oauth_authorizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_app_user` (`app_id`,`user_id`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_state` (`state`),
  ADD KEY `idx_used_expires` (`used`,`expires_at`);

-- 表的索引 `oauth_rate_limits`
ALTER TABLE `oauth_rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier_action_window` (`identifier`,`action`,`window_start`);

-- 表的索引 `oauth_settings`
ALTER TABLE `oauth_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_setting_key` (`setting_key`);

-- 表的索引 `oauth_states`
ALTER TABLE `oauth_states`
  ADD PRIMARY KEY (`state`),
  ADD KEY `idx_state` (`state`);

-- 表的索引 `oauth_userinfo_logs`
ALTER TABLE `oauth_userinfo_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_access_token` (`access_token`),
  ADD KEY `idx_app_id` (`app_id`),
  ADD KEY `idx_created_at` (`created_at`);

-- 表的索引 `oauth_verify_logs`
ALTER TABLE `oauth_verify_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_app_id` (`app_id`),
  ADD KEY `idx_created_at` (`created_at`);

-- 表的索引 `projects`
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status_hidden_created` (`status`,`is_hidden`,`created_at`);

-- 表的索引 `project_comments`
ALTER TABLE `project_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_id` (`project_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

-- 表的索引 `project_likes`
ALTER TABLE `project_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_project_user` (`project_id`,`user_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_project_id` (`project_id`);

-- 表的索引 `project_versions`
ALTER TABLE `project_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_id` (`project_id`),
  ADD KEY `idx_created_at` (`created_at`);

-- 表的索引 `qrcode_sessions`
ALTER TABLE `qrcode_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_expires` (`status`,`expires_at`);

-- 表的索引 `rate_limits`
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`bucket`),
  ADD KEY `idx_window` (`window_start`);

-- 表的索引 `reports`
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_reporter` (`reporter_id`);

-- 表的索引 `sessions`
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_user_expires` (`user_id`,`expires_at`);

-- 表的索引 `users`
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `xgorb_sub` (`xgorb_sub`),
  ADD UNIQUE KEY `uniq_wenchan_uid` (`wenchan_uid`),
  ADD UNIQUE KEY `uniq_wenchan_sub` (`wenchan_sub`),
  ADD KEY `idx_email` (`email`);

-- 表的索引 `user_restrictions`
ALTER TABLE `user_restrictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_active` (`is_active`);

-- 表的索引 `user_subscriptions`
ALTER TABLE `user_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sub` (`subscriber_id`,`target_user_id`),
  ADD KEY `idx_target` (`target_user_id`),
  ADD KEY `idx_subscriber` (`subscriber_id`);

-- 表的索引 `webauthn_credentials`
ALTER TABLE `webauthn_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_credential_id` (`credential_id`),
  ADD KEY `idx_user_id` (`user_id`);

-- 表的索引 `wenchan_oauth_flows`
ALTER TABLE `wenchan_oauth_flows`
  ADD PRIMARY KEY (`state`),
  ADD KEY `idx_expires` (`expires_at`);

-- 自增主键

-- 自增主键 `activity_logs`
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `announcements`
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- 自增主键 `app_auth_codes`
ALTER TABLE `app_auth_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- 自增主键 `app_login_codes`
ALTER TABLE `app_login_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `app_tokens`
ALTER TABLE `app_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- 自增主键 `app_updates`
ALTER TABLE `app_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- 自增主键 `email_register_tokens`
ALTER TABLE `email_register_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `notifications`
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `oauth_access_tokens`
ALTER TABLE `oauth_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `oauth_apps`
ALTER TABLE `oauth_apps`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `oauth_authorizations`
ALTER TABLE `oauth_authorizations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `oauth_rate_limits`
ALTER TABLE `oauth_rate_limits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `oauth_settings`
ALTER TABLE `oauth_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `oauth_userinfo_logs`
ALTER TABLE `oauth_userinfo_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `oauth_verify_logs`
ALTER TABLE `oauth_verify_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `projects`
ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `project_comments`
ALTER TABLE `project_comments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `project_likes`
ALTER TABLE `project_likes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `project_versions`
ALTER TABLE `project_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- 自增主键 `reports`
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- 自增主键 `users`
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 自增主键 `user_restrictions`
ALTER TABLE `user_restrictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- 自增主键 `user_subscriptions`
ALTER TABLE `user_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- 自增主键 `webauthn_credentials`
ALTER TABLE `webauthn_credentials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 外键约束

-- 外键约束 `email_login_tokens`
ALTER TABLE `email_login_tokens`
  ADD CONSTRAINT `email_login_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- 外键约束 `projects`
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- 外键约束 `project_comments`
ALTER TABLE `project_comments`
  ADD CONSTRAINT `fk_comment_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- 外键约束 `sessions`
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
