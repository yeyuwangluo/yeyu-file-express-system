# 生产部署指南

本文档是生产部署的快速入口。完整逐步教程见仓库根目录的 [`SETUP_GUIDE.md`](../SETUP_GUIDE.md)。

## 部署目标

- Web 根目录指向 `public`
- `.env` 保存在服务器本地
- `storage/`、`vendor/`、`node_modules/`、上传文件和数据库备份不提交到 Git
- 队列 worker 和 Laravel 定时任务长期运行
- 生产环境使用 HTTPS，`APP_DEBUG=false`

## 基础环境

- PHP 8.0.2 或更高版本，推荐 PHP 8.2
- Composer 2
- MySQL 5.7+、MariaDB 10.3+ 或 SQLite
- Nginx 或 Apache，生产环境推荐 Nginx
- Supervisor 或同类进程守护工具
- Node.js 18+，仅在服务器上重新构建前端资源时需要

## `.env` 文件在哪里

GitHub 上看不到 `.env` 文件是正常的。仓库只提交 `.env.example`，真正的 `.env` 需要部署到服务器后在项目根目录生成。

生产服务器上的 `.env` 路径示例：

```text
/www/wwwroot/yeyu-file-express-system/.env
```

生成方式：

```bash
# 进入项目目录
cd /www/wwwroot/yeyu-file-express-system

# 从模板复制出服务器本地配置文件
cp .env.example .env

# 生成应用密钥
php artisan key:generate
```

`.env` 会包含数据库密码、应用密钥、后台初始密码和第三方服务密钥，应只保存在服务器本地。

## 首次部署

```bash
# 进入站点目录
cd /www/wwwroot

# 拉取代码
git clone https://github.com/yeyuwangluo/yeyu-file-express-system.git yeyu-file-express-system

# 进入项目目录
cd yeyu-file-express-system

# 安装 PHP 依赖
composer install --no-dev --optimize-autoloader

# 创建环境文件
cp .env.example .env

# 生成应用密钥
php artisan key:generate
```

编辑 `.env`，至少确认：

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pan.example.com
YEYU_FILE_EXPRESS_INSTALLED=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yeyu_file_express
DB_USERNAME=yeyu_file_express
DB_PASSWORD=<DB_PASSWORD>

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_DRIVER=database
FILESYSTEM_DISK=local

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=<STRONG_ADMIN_PASSWORD>
```

初始化应用：

```bash
# 执行数据库迁移
php artisan migrate --force

# 写入默认配置和管理员账号
php artisan db:seed --force

# 创建公开存储链接
php artisan storage:link

# 生成生产缓存
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 前端资源

仓库已包含可直接部署的静态资源。需要重新构建前端资源时执行：

```bash
# 安装前端依赖
npm ci

# 构建前端资源
npm run build
```

如果 `npm ci` 失败，使用：

```bash
# 安装前端依赖
npm install

# 构建前端资源
npm run build
```

## Nginx 示例

```nginx
server {
    listen 80;
    server_name pan.example.com;
    root /www/wwwroot/yeyu-file-express-system/public;
    index index.php index.html;

    client_max_body_size 500M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    location ~ /\.(env|git|svn) {
        deny all;
    }
}
```

启用 HTTPS 后，把 `.env` 中的 `APP_URL` 改成 HTTPS 域名，并执行：

```bash
# 刷新配置缓存
php artisan config:cache
```

## 队列服务

文件扫描、压缩包内部扫描、AI 扫描和运维心跳依赖队列。

Supervisor 示例：

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

## 定时任务

```cron
* * * * * cd /www/wwwroot/yeyu-file-express-system && php artisan schedule:run >> /dev/null 2>&1
```

定时任务负责过期文件清理、局域网投递会话清理、运维自检和队列心跳记录。

## 更新流程

```bash
# 进入项目目录
cd /www/wwwroot/yeyu-file-express-system

# 拉取最新代码
git pull

# 更新 PHP 依赖
composer install --no-dev --optimize-autoloader

# 按需重新构建前端资源
npm ci
npm run build

# 执行数据库迁移
php artisan migrate --force

# 刷新缓存
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 重启队列
php artisan queue:restart

# 执行运维自检
php artisan yeyu-file-express:ops-check --strict
```

## 上线验收

1. 首页可访问：`https://pan.example.com/`
2. API 状态可访问：`https://pan.example.com/api/v1/status`
3. 后台可登录：`https://pan.example.com/admin-lite`
4. 小文件上传、分享、下载正常
5. 队列自检正常：`php artisan yeyu-file-express:ops-check`
6. `storage/logs/laravel.log` 无连续错误
7. 定时任务和队列 worker 已持续运行

## 详细配置

以下内容见 [`SETUP_GUIDE.md`](../SETUP_GUIDE.md)：

- MySQL 和 SQLite 详细配置
- 上传大小配置
- AI 扫描配置
- ZIP 内部图片扫描配置
- ClamAV 病毒扫描配置
- 对象存储配置
- 备份和回滚流程
- 常见问题排查
