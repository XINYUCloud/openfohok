# 文谶驿站 · 开发者开放仓库

[![CI](https://github.com/XINYUCloud/openfohok/actions/workflows/ci.yml/badge.svg)](https://github.com/XINYUCloud/openfohok/actions/workflows/ci.yml)

文谶驿站（[fohok.xin](https://fohok.xin)）的站点源码，提供开发者作品展示、文谶账户 OAuth 登录、项目提交与审核、站内信以及移动端 App 后端等能力。

## 技术栈

- PHP 8.1+（PDO / cURL / mbstring）
- MySQL 5.7+ / 8.0

## 功能模块

- **站点前台**：首页、搜索、项目详情、用户主页、消息中心、下载页
- **账号与安全**：文谶账户 OAuth 2.0（PKCE）、WebAuthn、极验验证
- **开发者开放仓库**：项目提交、评分、公告、举报、订阅计划
- **管理后台**：用户、项目、举报、公告、版本历史管理
- **移动端 App 后端**：`open-app/`，基于 AppToken 的 JSON API

## 目录结构

```text
.
├── admin/                 # 管理后台
├── api/                   # JSON API
├── config/                # 配置与环境变量加载器
│   ├── env.php            # .env / 环境变量读取
│   ├── config.php         # 站点配置（不含密钥）
│   └── wenchan.example.php
├── database/              # 数据库结构（不含数据）
│   └── schema.sql
├── includes/              # 公共库：数据库、鉴权、OAuth、邮件、安全
├── oauth/                 # 旧版自建 OAuth 服务（已下线）
├── open-app/              # 移动端 App 后端
├── img/                   # 静态图片
├── .env.example           # 环境变量示例
└── *.php                  # 页面入口
```

## 快速开始

### 1. 获取代码

```bash
git clone https://github.com/XINYUCloud/openfohok.git
cd openfohok
```

### 2. 配置环境变量

```bash
cp .env.example .env
```

编辑 `.env` 填写数据库、OAuth、邮件、验证码与截图服务等配置。应用会优先读取真实环境变量，其次读取项目根目录 `.env`。

所有密钥均不应写入代码或提交到仓库。

### 3. 初始化数据库

创建 MySQL 数据库与账号（默认库名/用户名均为 `open-user`，可在 `.env` 中修改）：

```sql
CREATE DATABASE `open-user` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'open-user'@'localhost' IDENTIFIED BY '你的数据库密码';
GRANT ALL PRIVILEGES ON `open-user`.* TO 'open-user'@'localhost';
FLUSH PRIVILEGES;
```

导入主库结构（仅表结构，不含任何数据）：

```bash
mysql -u open-user -p open-user < database/schema.sql
```

`open-app/schema.sql` 包含 App 端附加表。

### 4. 启动

本地开发：

```bash
php -S 127.0.0.1:8000
```

生产环境将 Web 根目录指向仓库根目录。

### 5. 服务器安全配置

生产环境必须禁止直接访问以下路径/文件：

- `.env`、`.git/`
- `config/`

Nginx 示例：

```nginx
location ~ ^/(\.env|\.git/|config/) {
    deny all;
    return 404;
}
```

## 环境变量

完整列表见 [.env.example](.env.example)，核心项如下：

| 变量 | 说明 |
| --- | --- |
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | 数据库连接 |
| `APP_URL` | 站点地址，用于生成回调与邮件链接 |
| `WENCHAN_OAUTH_APP_ID` / `WENCHAN_OAUTH_APP_SECRET` | 文谶账户 OAuth 应用凭据 |
| `WENCHAN_DEVELOPER_ID` | 开发者编号，读取账号中心站内信时使用 |
| `CAPTCHA_ID` / `CAPTCHA_KEY` | 极验验证配置 |
| `MAIL_API_URL` / `MAIL_AUTH_CODE` | people-mail 发信接口 |
| `ADMIN_EMAILS` | 管理员邮箱，英文逗号分隔 |
| `WEBSCR_API_URL` / `WEBSCR_API_KEY` | 网页截图服务 |

> 站内信开发者编号也可以通过 `config/wenchan.php` 配置。复制 `config/wenchan.example.php` 为 `config/wenchan.php` 后填写即可，该文件已被 `.gitignore` 忽略。

## 安全建议

- 请勿提交 `.env`、`config/wenchan.php`、访问令牌、私钥或生产数据库导出。
- 生产环境建议禁止直接访问 `.env`、`.git/`、`config/` 等敏感路径。
- 如需报告安全问题，请使用仓库的「私密漏洞报告」功能。

## 许可证

[MIT](LICENSE)
