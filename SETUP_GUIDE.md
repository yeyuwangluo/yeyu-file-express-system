# Yeyu File Express 搭建教程

本文档用于从空服务器部署 Yeyu File Express。项目基于 Laravel 9，前端资源使用 Vite 构建。

## 环境要求

- Linux 服务器，推荐 Ubuntu 22.04、Debian 12 或兼容发行版
- PHP 8.0.2 或更高版本，推荐 PHP 8.2
- PHP 扩展：`bcmath`、`ctype`、`curl`、`fileinfo`、`json`、`mbstring`、`openssl`、`pdo`、`tokenizer`、`xml`、`zip`
- Composer 2
- Node.js 18 或更高版本
- MySQL 5.7+、MariaDB 10.3+ 或 SQLite
- Nginx 或 Apache

## 获取代码

```bash
# 克隆项目
git clone https://github.com/yeyuwangluo/yeyu-file-express-system.git yeyu-file-express-system

# 进入项目目录
cd yeyu-file-express-system
```

## 安装依赖

```bash
# 安装 PHP 依赖
composer install --no-dev --optimize-autoloader

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

编辑 `.env`，至少配置以下项目：

```env
APP_NAME="Yeyu File Express"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
YEYU_FILE_EXPRESS_INSTALLED=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yeyu_file_express
DB_USERNAME=yeyu_file_express
DB_PASSWORD=your-db-password

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change-me-immediately
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

## 初始化数据库

```bash
# 执行数据库迁移
php artisan migrate --force

# 写入默认配置
php artisan db:seed --force
```

## 初始化目录权限

```bash
# 创建存储软链接
php artisan storage:link

# 设置运行目录权限
chmod -R ug+rw storage bootstrap/cache
```

生产环境建议将项目属主设置为 Web 服务用户，例如 `www-data`、`nginx` 或宝塔面板对应用户。

## Nginx 配置示例

将 `server_name` 和 `root` 替换为你的域名和部署目录。

```nginx
server {
    listen 80;
    server_name your-domain.example;
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

    location ~ /\.(env|git|svn|ht) {
        deny all;
    }
}
```

启用 HTTPS 后，将 `APP_URL` 改为 `https://your-domain.example`。

## 队列服务

上传后的安全扫描、哈希计算和后台任务依赖 Laravel 队列。生产环境建议使用 Supervisor 管理。

```bash
# 临时运行队列 worker
php artisan queue:work --tries=3 --timeout=120
```

Supervisor 配置示例：

```ini
[program:yeyu-file-express-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /www/wwwroot/yeyu-file-express-system/artisan queue:work --tries=3 --timeout=120
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/www/wwwroot/yeyu-file-express-system/storage/logs/worker.log
```

## 定时任务

Laravel 调度器用于清理过期文件、记录运行状态和维护统计数据。

```bash
# 编辑当前用户 crontab
crontab -e
```

加入以下内容：

```cron
* * * * * cd /www/wwwroot/yeyu-file-express-system && php artisan schedule:run >> /dev/null 2>&1
```

## 后台入口

- 后台地址：`https://your-domain.example/admin-lite`
- 管理员邮箱：`.env` 中的 `ADMIN_EMAIL`
- 管理员密码：`.env` 中的 `ADMIN_PASSWORD`

上线后立即修改默认管理员密码，并只保留 HTTPS 访问。

## 常用命令

```bash
# 清理配置缓存
php artisan config:clear

# 缓存生产配置
php artisan config:cache

# 清理视图缓存
php artisan view:clear

# 缓存路由
php artisan route:cache

# 重启队列 worker
php artisan queue:restart
```

## 更新流程

```bash
# 拉取最新代码
git pull

# 更新 PHP 依赖
composer install --no-dev --optimize-autoloader

# 更新前端依赖并构建资源
npm install
npm run build

# 执行迁移并刷新缓存
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

## 安全检查

上线前确认以下内容：

- `.env` 没有提交到 Git 仓库
- `storage/`、`vendor/`、`node_modules/` 没有提交到 Git 仓库
- 数据库备份、上传文件、证书私钥没有放在项目公开目录
- `APP_DEBUG=false`
- `APP_URL` 使用 HTTPS 域名
- 后台管理员密码已经改成强密码
- Nginx `root` 指向 `public` 目录
- 队列 worker 和定时任务已经启动
