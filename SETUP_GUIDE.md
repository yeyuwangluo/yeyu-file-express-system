# Yeyu File Express 搭建教程

本文档用于在一台空服务器上部署 Yeyu File Express。项目基于 Laravel 9，前端资源使用 Vite 构建，核心能力包括文件上传、分享页、我的文件、批量分享、局域网投递、后台管理、AI 风险扫描、压缩包内部扫描、队列任务和运维自检。

## 适用场景

- 全新服务器首次部署
- 从 GitHub 私有仓库拉取代码部署
- 线上项目更新和回滚
- 排查上传、扫描、队列、后台登录等常见问题

## 部署前准备

准备以下信息：

- 服务器公网 IP
- 域名，例如 `pan.example.com`
- GitHub 仓库访问权限
- 数据库名称、用户名、密码
- 管理员邮箱和初始密码
- AI 扫描接口地址、模型名和 API Key，如需启用 AI 扫描
- HTTPS 证书，推荐使用宝塔面板、Nginx Proxy Manager 或 ACME 工具签发

推荐部署目录：

```text
/www/wwwroot/yeyu-file-express-system
```

## 环境要求

- Linux 服务器，推荐 Ubuntu 22.04、Debian 12、AlmaLinux 9 或同类发行版
- PHP 8.0.2 或更高版本，推荐 PHP 8.2
- Composer 2
- Node.js 18 或更高版本
- MySQL 5.7+、MariaDB 10.3+ 或 SQLite
- Nginx 或 Apache，生产环境推荐 Nginx
- Supervisor 或同类进程守护工具，用于管理 Laravel 队列

PHP 扩展要求：

- `bcmath`
- `ctype`
- `curl`
- `fileinfo`
- `json`
- `mbstring`
- `openssl`
- `pdo`
- `pdo_mysql` 或 `pdo_sqlite`
- `tokenizer`
- `xml`
- `zip`

可选组件：

- ClamAV：启用本机病毒扫描时使用
- Redis：需要更高性能队列或缓存时使用
- S3 兼容对象存储：需要把上传文件放到对象存储时使用

## 获取代码

```bash
# 进入网站根目录
cd /www/wwwroot

# 克隆私有仓库
git clone https://github.com/yeyuwangluo/yeyu-file-express-system.git yeyu-file-express-system

# 进入项目目录
cd yeyu-file-express-system
```

如果服务器无法直接拉取私有仓库，推荐在 GitHub 创建只读 Deploy Key，或使用具备仓库权限的 HTTPS Token。

## 安装依赖

```bash
# 安装生产 PHP 依赖
composer install --no-dev --optimize-autoloader

# 安装前端依赖
npm ci

# 构建前端资源
npm run build
```

如果没有 `package-lock.json` 或 `npm ci` 失败，使用：

```bash
# 安装前端依赖
npm install

# 构建前端资源
npm run build
```

## 创建环境配置

```bash
# 复制环境变量模板
cp .env.example .env

# 生成 Laravel 应用密钥
php artisan key:generate
```

生产环境至少配置以下字段：

```env
APP_NAME="叶宇文件快递"
APP_ENV=production
APP_KEY=base64:generated-by-artisan
APP_DEBUG=false
APP_URL=https://pan.example.com
YEYU_FILE_EXPRESS_INSTALLED=true

APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=zh_CN
APP_FAKER_LOCALE=zh_CN

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yeyu_file_express
DB_USERNAME=yeyu_file_express
DB_PASSWORD=<DB_PASSWORD>

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_DRIVER=database
FILESYSTEM_DISK=local

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=<STRONG_ADMIN_PASSWORD>

UPLOAD_MAX_FILE_SIZE=52428800
UPLOAD_DEFAULT_EXPIRE_DAYS=1
UPLOAD_MAX_EXPIRE_DAYS=7
STORAGE_LIMIT_BYTES=107374182400

VIRUS_SCAN_ENABLED=false
CLAMAV_PATH=clamscan
VIRUS_SCAN_TIMEOUT_SECONDS=60

GEETEST_ENABLED=false
GEETEST_CAPTCHA_ID=

LAN_TRANSFER_ENABLED=true
LAN_MAX_FILE_SIZE=2147483648
LAN_MAX_FILE_COUNT=5
LAN_EXPIRE_MINUTES=10

VITE_APP_NAME="${APP_NAME}"
```

### 关键环境变量说明

| 变量 | 说明 | 建议值 |
| --- | --- | --- |
| `APP_ENV` | 运行环境 | `production` |
| `APP_DEBUG` | 调试模式 | 生产环境使用 `false` |
| `APP_URL` | 站点访问地址 | HTTPS 域名 |
| `YEYU_FILE_EXPRESS_INSTALLED` | 安装状态开关 | 部署完成后使用 `true` |
| `QUEUE_CONNECTION` | 队列驱动 | 小型部署用 `database` |
| `SESSION_DRIVER` | Session 驱动 | `database` |
| `CACHE_DRIVER` | 缓存驱动 | `database` 或 `redis` |
| `FILESYSTEM_DISK` | 上传文件存储磁盘 | 本地存储用 `local` |
| `UPLOAD_MAX_FILE_SIZE` | 单文件上传上限，单位字节 | 按服务器带宽和磁盘调整 |
| `STORAGE_LIMIT_BYTES` | 总存储容量限制，单位字节 | 按磁盘容量调整 |
| `VIRUS_SCAN_ENABLED` | ClamAV 病毒扫描开关 | 安装 ClamAV 后再启用 |
| `GEETEST_ENABLED` | 极验验证开关 | 配好验证码后再启用 |
| `LAN_TRANSFER_ENABLED` | 局域网投递入口开关 | 需要该功能时使用 `true` |

## 数据库配置

### MySQL 或 MariaDB

创建数据库和账号后，把账号信息写入 `.env`。

```sql
CREATE DATABASE yeyu_file_express CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'yeyu_file_express'@'127.0.0.1' IDENTIFIED BY '<DB_PASSWORD>';
GRANT ALL PRIVILEGES ON yeyu_file_express.* TO 'yeyu_file_express'@'127.0.0.1';
FLUSH PRIVILEGES;
```

`.env` 配置示例：

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yeyu_file_express
DB_USERNAME=yeyu_file_express
DB_PASSWORD=<DB_PASSWORD>
```

### SQLite

SQLite 适合测试、小规模部署或临时演示。

```bash
# 创建 SQLite 数据库文件
touch database/database.sqlite
```

`.env` 配置示例：

```env
DB_CONNECTION=sqlite
DB_DATABASE=/www/wwwroot/yeyu-file-express-system/database/database.sqlite
```

生产环境使用 SQLite 时，需要重点关注数据库备份和并发写入压力。

## 初始化应用

```bash
# 执行数据库迁移
php artisan migrate --force

# 写入默认配置和管理员账号
php artisan db:seed --force

# 创建 storage 公开访问软链接
php artisan storage:link

# 生成优化缓存
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

如果 `db:seed` 提示管理员已存在，可以继续下一步，后台账号以数据库现有记录为准。

## 目录权限

Web 服务用户需要写入 `storage`、`bootstrap/cache` 和 SQLite 数据库文件。

常见 Web 服务用户：

- Ubuntu/Debian Nginx：`www-data`
- CentOS/AlmaLinux Nginx：`nginx`
- 宝塔面板：通常是 `www`

```bash
# 进入项目目录
cd /www/wwwroot/yeyu-file-express-system

# 设置 Laravel 运行目录可写
chmod -R ug+rw storage bootstrap/cache
```

如需调整属主，按实际 Web 服务用户执行：

```bash
# 示例：把项目属主改为 www-data
chown -R www-data:www-data /www/wwwroot/yeyu-file-express-system
```

## Nginx 配置

将 `server_name` 和 `root` 替换为你的域名和部署目录。

```nginx
server {
    listen 80;
    server_name pan.example.com;
    root /www/wwwroot/yeyu-file-express-system/public;
    index index.php index.html;

    client_max_body_size 500M;
    client_body_timeout 120s;
    send_timeout 120s;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 120s;
    }

    location ~ /\.(env|git|svn) {
        deny all;
    }

    location ~* \.(sql|sqlite|bak|backup|zip|tar|gz)$ {
        deny all;
    }
}
```

启用 HTTPS 后，将 `.env` 中的 `APP_URL` 改为 HTTPS 域名：

```env
APP_URL=https://pan.example.com
```

然后刷新配置缓存：

```bash
# 刷新 Laravel 配置缓存
php artisan config:cache
```

## 上传大小配置

上传大小需要同时配置 Laravel、PHP 和 Nginx。

Laravel：

```env
UPLOAD_MAX_FILE_SIZE=52428800
CHUNKED_UPLOAD_ENABLED=true
CHUNKED_UPLOAD_MAX_CHUNK_SIZE=10485760
CHUNKED_UPLOAD_MAX_CHUNKS=10000
```

PHP：

```ini
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 120
max_input_time = 120
memory_limit = 512M
```

Nginx：

```nginx
client_max_body_size 500M;
client_body_timeout 120s;
fastcgi_read_timeout 120s;
```

改完 PHP 或 Nginx 配置后，重载对应服务。

## 队列服务

上传后的哈希计算、安全扫描、AI 扫描、压缩包内部扫描和运维心跳依赖 Laravel 队列。生产环境必须运行队列 worker。

临时运行方式：

```bash
# 前台运行队列 worker
php artisan queue:work --tries=3 --timeout=120
```

Supervisor 配置示例：

```ini
[program:yeyu-file-express-worker]
process_name=%(program_name)s_%(process_num)02d
directory=/www/wwwroot/yeyu-file-express-system
command=php artisan queue:work --tries=3 --timeout=120 --sleep=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/www/wwwroot/yeyu-file-express-system/storage/logs/worker.log
stopwaitsecs=130
```

队列状态检查：

```bash
# 查看待处理队列数量和自检状态
php artisan yeyu-file-express:ops-check

# JSON 格式输出
php artisan yeyu-file-express:ops-check --json
```

## 定时任务

Laravel 调度器负责清理过期文件、局域网投递会话、运行运维自检和记录队列心跳。

```bash
# 编辑当前用户 crontab
crontab -e
```

加入以下内容：

```cron
* * * * * cd /www/wwwroot/yeyu-file-express-system && php artisan schedule:run >> /dev/null 2>&1
```

当前项目内置调度任务：

- 每小时清理过期文件：`files:clean-expired`
- 每分钟记录队列心跳：`yeyu-file-express:queue-heartbeat`
- 每 5 分钟记录运维自检：`yeyu-file-express:ops-check --record`
- 每 30 分钟清理局域网投递会话：`yeyu-file-express:cleanup-lan-sessions`
- 每天 03:20 清理 30 天前运维日志：`yeyu-file-express:prune-logs --days=30`

## 后台入口

- 后台地址：`https://pan.example.com/admin-lite`
- 管理员邮箱：`.env` 中的 `ADMIN_EMAIL`
- 管理员密码：`.env` 中的 `ADMIN_PASSWORD`

首次登录后建议完成：

- 修改默认管理员密码
- 检查上传大小、过期天数和允许文件类型
- 配置页脚备案信息
- 配置 AI 扫描接口，如需启用
- 配置 123 网盘或对象存储，如需使用外部存储
- 运行一次运维自检

## AI 扫描配置

AI 扫描通过后台配置，入口：

```text
https://pan.example.com/admin-lite/ai-settings
```

常用字段：

- `ai_scan_enabled`：AI 扫描开关
- `ai_scan_api_url`：模型接口地址，通常为兼容 OpenAI Chat Completions 的 URL
- `ai_scan_api_key`：模型接口密钥
- `ai_scan_model`：模型名称
- `ai_scan_timeout`：请求超时时间，单位秒
- `ai_scan_max_file_size`：参与 AI 文本扫描的最大文件大小，单位 KB
- `archive_max_scan_files`：单个压缩包最多扫描多少个内部文件
- `archive_scan_extensions`：压缩包内部文本/代码扫描后缀
- `archive_media_scan_enabled`：压缩包内部图片扫描开关
- `archive_media_extensions`：压缩包内部图片扫描后缀
- `archive_media_max_file_size`：压缩包内部单张图片扫描上限，单位 KB
- `archive_media_failure_policy`：压缩包内部图片 AI 调用失败策略

压缩包内部图片失败策略：

- `block`：AI 调用失败时临时拦截，安全优先
- `review`：AI 调用失败时进入人工复核
- `allow`：AI 调用失败时允许通过，适合低风险内网环境

默认建议使用 `block`。

## 病毒扫描配置

项目支持调用 ClamAV 命令行扫描。

`.env` 示例：

```env
VIRUS_SCAN_ENABLED=true
CLAMAV_PATH=clamscan
VIRUS_SCAN_TIMEOUT_SECONDS=60
```

启用前确认服务器可执行：

```bash
# 查看 ClamAV 版本
clamscan --version
```

大型文件扫描会增加 CPU 和 IO 压力，建议先在小流量时段启用并观察队列积压。

## 对象存储配置

本项目依赖 `league/flysystem-aws-s3-v3`，可连接 S3 兼容对象存储。

`.env` 示例：

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<ACCESS_KEY>
AWS_SECRET_ACCESS_KEY=<SECRET_KEY>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=<BUCKET_NAME>
AWS_ENDPOINT=https://s3.example.com
AWS_URL=https://cdn.example.com/<BUCKET_NAME>
AWS_USE_PATH_STYLE_ENDPOINT=true
```

配置后刷新缓存：

```bash
# 刷新 Laravel 配置缓存
php artisan config:cache
```

## 常用运维命令

```bash
# 查看路由
php artisan route:list

# 清理配置缓存
php artisan config:clear

# 缓存生产配置
php artisan config:cache

# 清理视图缓存
php artisan view:clear

# 缓存视图
php artisan view:cache

# 缓存路由
php artisan route:cache

# 重启队列 worker
php artisan queue:restart

# 运维自检
php artisan yeyu-file-express:ops-check

# 严格模式自检，适合发布前使用
php artisan yeyu-file-express:ops-check --strict
```

## 上线验收清单

完成部署后按顺序检查：

1. 首页可访问：`https://pan.example.com/`
2. 状态页可访问：`https://pan.example.com/status`
3. API 状态正常：`https://pan.example.com/api/v1/status`
4. 后台可登录：`https://pan.example.com/admin-lite`
5. 上传一个小文件，确认生成分享链接
6. 打开分享链接，确认预览、下载和过期时间显示正常
7. 打开 `/my-files`，确认本机历史文件可同步显示
8. 上传一个 ZIP 文件，确认后台能看到扫描状态
9. 执行 `php artisan yeyu-file-express:ops-check`，确认队列心跳正常
10. 确认 `storage/logs/laravel.log` 无连续报错

## 更新流程

发布新版本前建议先备份数据库和 `.env`。

```bash
# 进入项目目录
cd /www/wwwroot/yeyu-file-express-system

# 拉取最新代码
git pull

# 更新 PHP 依赖
composer install --no-dev --optimize-autoloader

# 更新前端依赖
npm ci

# 构建前端资源
npm run build

# 执行数据库迁移
php artisan migrate --force

# 刷新生产缓存
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 重启队列 worker
php artisan queue:restart

# 执行运维自检
php artisan yeyu-file-express:ops-check --strict
```

如果 `npm ci` 因锁文件或环境问题失败，使用：

```bash
# 安装前端依赖
npm install

# 构建前端资源
npm run build
```

## 回滚流程

回滚前确认当前 Git 提交号和数据库备份状态。

```bash
# 查看最近提交
git log --oneline -10

# 切换到指定稳定版本
git checkout <COMMIT_SHA>

# 恢复依赖和构建产物
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# 刷新缓存并重启队列
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

数据库迁移通常需要单独评估回滚方案。生产数据库回滚前先恢复备份到临时库验证。

## 备份建议

必须备份：

- `.env`
- 数据库
- `storage/app` 中的上传文件
- `storage/app/public` 中的公开文件
- Nginx 站点配置
- Supervisor 队列配置

SQLite 备份示例：

```bash
# 创建备份目录
mkdir -p /www/backup/yeyu-file-express

# 复制 SQLite 数据库
cp database/database.sqlite /www/backup/yeyu-file-express/database-$(date +%F-%H%M%S).sqlite
```

MySQL 备份示例：

```bash
# 导出 MySQL 数据库
mysqldump -h 127.0.0.1 -u yeyu_file_express -p yeyu_file_express > /www/backup/yeyu-file-express/database-$(date +%F-%H%M%S).sql
```

上传文件备份示例：

```bash
# 同步上传文件到备份目录
rsync -a storage/app/ /www/backup/yeyu-file-express/storage-app/
```

## 安全检查

上线前确认：

- `.env` 未提交到 Git 仓库
- `storage/`、`vendor/`、`node_modules/` 未提交到 Git 仓库
- 数据库备份、上传文件、证书私钥未放在 `public` 目录
- `APP_DEBUG=false`
- `APP_ENV=production`
- `APP_URL` 使用 HTTPS 域名
- Nginx `root` 指向项目 `public` 目录
- 后台管理员密码已改成强密码
- GitHub 仓库保持私有或已完成敏感信息清理
- 队列 worker 已运行
- 定时任务已运行
- 上传上限在 Laravel、PHP、Nginx 三处一致
- AI 扫描 API Key 只保存在后台配置或 `.env` 中
- 备份文件存放在 Web 根目录之外

## 常见问题

### 页面显示 500

处理步骤：

```bash
# 查看 Laravel 日志
tail -n 100 storage/logs/laravel.log

# 清理缓存
php artisan config:clear
php artisan view:clear

# 重新缓存
php artisan config:cache
php artisan view:cache
```

重点检查 `.env`、数据库连接、目录权限和 PHP 扩展。

### 上传失败或提示文件过大

检查三处配置：

- `.env` 中的 `UPLOAD_MAX_FILE_SIZE`
- PHP 的 `upload_max_filesize` 和 `post_max_size`
- Nginx 的 `client_max_body_size`

改完配置后刷新缓存并重载 PHP/Nginx。

### 上传成功但扫描状态一直处理中

检查队列 worker：

```bash
# 查看运维自检
php artisan yeyu-file-express:ops-check

# 重启队列
php artisan queue:restart

# 临时前台运行队列观察日志
php artisan queue:work --tries=3 --timeout=120
```

### 后台登录失败

检查：

- `.env` 中的 `ADMIN_EMAIL` 和 `ADMIN_PASSWORD`
- 数据库是否完成迁移和 seeder
- Session 表是否存在
- 浏览器 Cookie 是否被拦截
- `APP_URL` 是否和实际域名一致

### ZIP 内部图片没有扫描

检查后台 AI 设置：

- `archive_media_scan_enabled` 是否开启
- `archive_media_extensions` 是否包含目标图片后缀，例如 `webp`
- `archive_media_max_file_size` 是否小于压缩包内图片大小
- `archive_max_scan_files` 是否过小
- 队列 worker 是否运行

历史扫描结果不会自动刷新。需要在后台对文件执行重新扫描，或重新上传文件生成新的扫描记录。

### `/my-files` 看不到文件

`/my-files` 使用浏览器本地历史记录和后端同步接口。检查：

- 上传完成后浏览器是否允许 localStorage
- 同一个浏览器访问 `/my-files`
- 接口 `/api/v1/files/history-sync` 是否返回正常
- 浏览器是否处于隐私模式或清理过站点数据

### 静态资源样式异常

重新构建前端资源并清理缓存：

```bash
# 构建前端资源
npm run build

# 清理并重新生成视图缓存
php artisan view:clear
php artisan view:cache
```

### 运维自检提示 `queue_heartbeat_stale`

说明队列心跳过期。处理步骤：

```bash
# 重启队列 worker
php artisan queue:restart

# 查看自检状态
php artisan yeyu-file-express:ops-check
```

同时检查 Supervisor 是否正常拉起 worker。

## 部署完成后的推荐维护节奏

- 每天查看后台安全复核和失败任务
- 每周检查磁盘容量、上传增长和备份可用性
- 每次更新前备份数据库和 `.env`
- 每次更新后执行 `php artisan yeyu-file-express:ops-check --strict`
- AI 扫描策略调整后，对历史高风险文件重新扫描
