# 叶宇文件快递部署清单

## 服务器环境

- PHP 8.0.2+，启用 `pdo_mysql`、`fileinfo`、`mbstring`、`openssl`、`curl`、`tokenizer`、`xml`、`ctype`、`json` 扩展。
- MySQL 5.7+，数据库名建议 `xiaoxin_file_express`，字符集 `utf8mb4`，排序规则 `utf8mb4_unicode_ci`。
- Nginx 或 Caddy 作为 Web 入口，站点根目录指向 `xiaoxin-file-express-system/public`。
- 前端 CSS/JS 已预编译到 `public/_next/static/`，部署不需要 Node.js。
- 队列 worker 和 scheduler 必须作为系统服务或计划任务常驻。
- Composer 依赖锁以 PHP 8.0.2 为平台生成，安装时使用仓库内 `composer.lock`。不要在生产服务器随意执行 `composer update`。

## PHP 版本说明

当前代码兼容基线是 PHP 8.0.2+。为了兼容普通虚拟主机和 PHP 8.0 环境，框架固定在 Laravel 9.52 线；这意味着部分上游安全公告的官方修复版本会要求 PHP 8.1+ 或 Laravel 10+。如果服务器可以升级到 PHP 8.1/8.2+，后续应单独安排框架升级来消除这些上游公告。

在 PHP 8.4 上执行 `composer check-platform-reqs` 可能会因 `nette/schema` 的 Composer 元数据上限报告不匹配；实际安装请使用仓库内 `composer.lock` 和已固定的 Composer platform。

## 生产 `.env`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=xiaoxin_file_express
DB_USERNAME=xiaoxin_file_express
DB_PASSWORD=change-this-password

FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change-me-immediately

UPLOAD_MAX_FILE_SIZE=52428800
UPLOAD_DEFAULT_EXPIRE_DAYS=1
UPLOAD_MAX_EXPIRE_DAYS=7
STORAGE_LIMIT_BYTES=107374182400
```

对象存储切换为 S3 兼容服务时：

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=auto
AWS_BUCKET=xiaoxin-file-express
AWS_ENDPOINT=https://s3.example.com
AWS_URL=
AWS_USE_PATH_STYLE_ENDPOINT=true
```

## 发布步骤

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

首次发布后访问 `/admin`，系统会跳转到统一风格后台 `/admin-lite`。用 `ADMIN_EMAIL` / `ADMIN_PASSWORD` 登录并立刻修改管理员密码。

## Nginx 示例

```nginx
server {
    listen 80;
    server_name your-domain.example;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.example;
    root /var/www/xiaoxin-file-express-system/public;
    index index.php;

    client_max_body_size 50m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

PHP 限制需与后台配置保持一致：

不同服务器的 PHP-FPM socket 名称可能是 `php8.0-fpm.sock`、`php8.1-fpm.sock`、`php8.2-fpm.sock`、`php8.3-fpm.sock` 或 TCP 端口，按实际环境修改 `fastcgi_pass`。

```ini
upload_max_filesize=50M
post_max_size=50M
max_execution_time=120
```

## Queue Worker

Systemd 示例：

```ini
[Unit]
Description=Xiaoxin File Express Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/xiaoxin-file-express-system
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --timeout=120

[Install]
WantedBy=multi-user.target
```

## Scheduler

Crontab 示例：

```cron
* * * * * cd /var/www/xiaoxin-file-express-system && php artisan schedule:run >> /dev/null 2>&1
```

## 验收

- `php artisan migrate --force` 成功。
- `php artisan queue:work --once` 能执行一次任务。
- `php artisan schedule:run` 能触发计划任务。
- 公开页、上传、分享页、提取码下载、局域网文本互传、后台登录均可用。
- 重启 PHP-FPM、队列 worker、Web 服务后，历史文件仍可下载。
