# 文谶驿站 App 后端 (open-app)

本目录是「文谶驿站」移动端 App 所需的 PHP 后端接口，发布到 **https://fohok.xin/open-app/** 目录。

## 2026-09-08 修复记录：验证码登录「服务器繁忙请稍后重试」

**根因**：后端在「验证码发送/校验」链路中一旦抛出未捕获的 PHP 异常（如内核模块缺失、DB 驱动问题、
重复函数定义），整页会退化成裸 PHP 错误页/空白页，前端只能兜底提示「服务器繁忙请稍后重试」。

**修复**：
1. `_lib.php` 在**加载站点内核之前**注册三类全局兜底，保证任何阶段的异常都转成统一 JSON：
   - `set_error_handler()`：非致命警告 / 可恢复错误转 `ErrorException`；
   - `set_exception_handler()`：未捕获异常 → `{"ok":false,"msg":"服务器内部错误"}`；
   - `register_shutdown_function()`：致命错误（require 语法错误等）→ 同样兜底为 JSON。
2. `index.php` 的 `login_email_send` / `login_email_check` 全程 `try-catch`，逐段给出明确文案
   （初始化失败 / 验证码生成失败 / 校验失败 / 令牌创建失败），不再落到裸错误页。
3. 前端 `wencan-app/common/api.js` 新增 `friendlyError()`：优先取后端 `msg`，
   仅在后端返回**非 JSON**（HTML 错误页/空白）时按 HTTP 状态给出「服务器暂时不可用」等可理解提示。

**线上验证**（2026-11-01 实测，均返回规范 JSON 而非 HTML）：
- `GET /index.php` → `{"ok":false,"msg":"未知操作: "}`
- `login_email_send` → `{"ok":false,"msg":"邮箱验证码登录已下线，请使用文谶账户登录"}`
- `me`（无 token）→ `{"ok":false,"code":"UNAUTHORIZED","msg":"请先登录"}`
- `project_submit` 缺字段 → `{"ok":false,"msg":"请填写必填字段"}`

## 目录结构

```
open-app/
├── _lib.php           # 公共库：内核加载、AppToken 鉴权、OAuth 票据、建表/补列
├── _oauth_client.php  # 旧版 OAuth 配置占位（自建 OAuth 已下线，保留仅为兼容加载）
├── index.php          # 统一接口入口（所有 action 都走这里）
├── oauth_start.php    # App 内嵌 Webview 登录入口（统一走文谶账户 OAuth）
├── oauth_callback.php # 已停用（410），标准回调为站点根目录 callback.php
├── oauth_landing.php  # 登录完成后签发 AppToken 并交回 App（票据 + postMessage 双通道）
├── schema.sql         # App 专用表（app_tokens / app_oauth_tickets）
└── README.md
```

### 登录链路

```
App                        open-app 后端                 文谶账户 account.fohok.xin
 │ ① oauth_ticket_create ──▶ 生成 ticket                    │
 │ ◀── ticket ─────────────                                 │
 │ ② web-view 打开 oauth_start.php?ticket ──▶ 绑定当前会话   │
 │                            state+PKCE → 302 ───────────▶ /oauth/authorize.php
 │                                                        （登录 + 同意授权）
 │                            ◀── 302 code+state ──────────┤
 │                            ③ 站点根 callback.php 校验    │
 │                               state / PKCE / code        │
 │                               建立站点会话 → 302 landing │
 │ ④ 落地页 open-app/oauth_landing.php?ticket               │
 │    本会话发起过该票据才签发 AppToken 写入票据              │
 │ ⑤ 轮询 oauth_ticket_poll ─▶ 取回 {token,user}             │
 │ ⑥ 落地页 postMessage（加速通道，可先于⑤到）               │
```

安全要点：
- `state` 一次性、与会话绑定，回调严格比对，防登录 CSRF / 重放；
- PKCE `S256`：verifier 只存服务端 flow 表，challenge 随授权请求发出；
- 票据与会话绑定：落地页只承认本会话发起过的票据，避免票据链接被转发后窃取 AppToken；
- 用户拒绝授权时票据标记 `denied`，App 轮询感知后提示「已取消授权」；
- AppToken 与文谶 access_token 相互独立，App 只持有 AppToken。

## 部署要求

1. 本目录必须部署在站点根目录下的 `open-app/`（即 `https://fohok.xin/open-app/`）。
   因为后端复用了站点根目录的 `bootstrap.php`（config/db/auth/email/notification/OAuth 全部逻辑），
   **不要脱离主项目单独部署**。
2. PHP ≥ 7.1（推荐 8.x），MySQL 5.7+，开启 curl 扩展（OAuth / 邮件依赖）。
3. 数据库与主站一致（`config/config.php` 中的 `open-user`）。
4. 首次部署执行一次 `schema.sql` 初始化表；不执行也会由后端 `ensureAppSchema()` 自动建表并补列。

## 接口约定

- 请求地址：`https://fohok.xin/open-app/index.php`
- 调用方式：`POST/GET`，用 `action` 参数区分接口
- 需要登录的接口：`Authorization: Bearer <app_token>`
- 响应统一为 JSON：`{"ok":true, ...}` / `{"ok":false, "msg":"..."}`
- 允许跨域（H5 调试），原生 App 无跨域限制。

## 登录方式

App 登录统一使用文谶账户身份，均签发 AppToken（默认有效期 30 天）：

| 方式 | 说明 | 接口 |
|------|------|------|
| 内嵌 Webview OAuth | App 内 `<web-view>` 打开，走文谶账户登录 | `oauth_start.php?ticket=...` |
| 扫码授权 | App 扫 PC 网页登录二维码并确认 | `qr_scan` / `qr_confirm` |
| 邮箱验证码 | 已下线（返回 410） | `login_email_send` / `login_email_check` |

### 配置步骤（管理员，一次性）

1. 在 [文谶账户开发者中心](https://account.fohok.xin/doc/oauth-docs.php) 创建应用：
   - 应用名称：`文谶驿站 App`
   - 回调地址：`https://fohok.xin/callback.php`
2. 将分配的 App ID / App Secret 写入站点 `config/config.php` 的
   `WENCHAN_OAUTH_APP_ID` / `WENCHAN_OAUTH_APP_SECRET`（仅服务端保存）。
3. 保存即生效，无需重启。

> 站点自建 OAuth 服务（`fohok.xin/oauth/`）已彻底下线，统一使用文谶账户 OAuth。

## 接口总览

### 登录 / 账户
| action | 方法 | 说明 | 鉴权 |
|--------|------|------|------|
| `login_email_send` | POST | 已下线，返回 410 提示改用文谶账户登录 | — |
| `login_email_check` | POST | 已下线，返回 410 提示改用文谶账户登录 | — |
| `logout` | POST | 注销当前 token | 有 |
| `me` | GET | 当前用户信息 + 未读数 | 有 |
| `profile_update` | POST | 更新资料 name/bio/website/phone | 有 |
| `avatar_update` | POST | 更新头像 picture | 有 |
| `sessions_list` | GET | 会话列表（App 会话 app_tokens + 网页会话 sessions 合并，id 形如 `app:12`/`web:34`，`is_current` 标记当前设备） | 有 |
| `session_revoke` | POST | 踢除某会话 id | 有 |
| `session_revoke_others` | POST | 踢除其他所有会话（保留当前 AppToken） | 有 |
| `oauth_ticket_create` | GET | 创建 OAuth 轮询票据（打开 webview 前调用） | 无 |
| `oauth_ticket_poll` | GET/POST | 轮询票据状态：授权完成取回 `{token,user}`；用户拒绝返回 `{done:true,denied:true}` | 无 |

### 项目
| action | 方法 | 说明 |
|--------|------|------|
| `projects_list` | GET | 项目列表，page 分页 |
| `project_detail` | GET | 项目详情 id |
| `project_versions` | GET | 版本历史 id |
| `project_search` | POST | 搜索 keyword |
| `stats` | GET | 站内统计 |
| `announcement` | GET | 公告 id（缺省取最新） |
| `user_profile` | GET | 公开用户主页 id |
| `project_submit` | POST | 提交项目（支持游客） |
| `my_projects` | GET | 我的项目 |
| `liked_projects` | GET | 点赞过的项目 |
| `project_like` | POST | 点赞/取消 id |
| `project_update` | POST | 编辑项目（本人） |
| `project_publish_version` | POST | 发布新版本（本人） |
| `project_delete` | POST | 删除项目（本人） |
| `project_toggle_hide` | POST | 显示/隐藏（本人） |

### 互动
| action | 方法 | 说明 | 鉴权 |
|--------|------|------|------|
| `report_submit` | POST | 举报项目 | 可选 |
| `subscription_toggle` | POST | 订阅/取消订阅 | 有 |
| `subscription_check` | GET | 是否已订阅 | 可选 |
| `subscription_counts` | GET | 订阅者数量 | 无 |

### 通知
| action | 方法 | 说明 | 鉴权 |
|--------|------|------|------|
| `unread_count` | GET | 未读通知数 | 有 |
| `notifications_list` | GET | 通知列表 page | 有 |
| `notification_detail` | GET | 通知详情(自动已读) id | 有 |
| `notification_mark_read` | POST | 标记已读 id | 有 |
| `notification_mark_all_read` | POST | 全部已读 | 有 |
| `notification_delete_all` | POST | 清空通知 | 有 |

### 扫码登录（App 扫 PC 网页二维码）
| action | 方法 | 说明 | 鉴权 |
|--------|------|------|------|
| `qr_scan` | POST | 登录态扫码标记 session_id | 有 |
| `qr_confirm` | POST | 确认登录 session_id | 有 |
| `qr_cancel` | POST | 取消登录 session_id | 有 |

## 常见响应错误码

- `{"ok":false,"code":"UNAUTHORIZED","msg":"请先登录"}` → token 缺失或过期，App 应跳转到登录页
- `{"ok":false,"banned":true,...}` → 账号被限制登录

## 备注

- 各接口统计/评分/截图等网页端能力如需在 App 呈现，可按 `index.php` 中的模式自行补充。
- App 端唯一需要改的前端常量是接口 BaseURL（见 App 工程 `common/config.js`）。