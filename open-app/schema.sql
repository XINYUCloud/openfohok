-- ============================================================
-- 文谶驿站 App 后端所需附加表
-- 说明：本文件用于在目标服务器数据库（open-user）中初始化 App
-- 专用表。首次部署 open-app 时执行一次即可；
-- 后端已内置自动建表/补列逻辑（ensureAppSchema()），
-- 此脚本供手动初始化或核对结构。
-- ============================================================

CREATE TABLE IF NOT EXISTS app_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL,
    platform VARCHAR(20) DEFAULT 'app',
    user_agent VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NULL,
    expires_at DATETIME NULL,
    UNIQUE KEY uniq_token (token),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='App登录令牌';

CREATE TABLE IF NOT EXISTS app_oauth_tickets (
    ticket CHAR(32) PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    token CHAR(64) NULL,
    user_json TEXT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='App Webview OAuth轮询票据';

-- 旧版本 app_tokens 表补列（表已存在时执行；列已存在会报错可忽略）
-- ALTER TABLE app_tokens ADD COLUMN user_agent VARCHAR(500) NULL;
-- ALTER TABLE app_tokens ADD COLUMN ip_address VARCHAR(45) NULL;
