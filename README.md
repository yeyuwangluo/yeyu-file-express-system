# 叶宇文件快递系统

这是叶宇文件快递的 Laravel 版本，实现公开上传下载、分享页、提取码、局域网互传信令、分片上传、后台管理、审计日志、队列任务和定时清理。

## 运行要求

- PHP 8.0.2+
- Composer 2
- MySQL 5.7+ 或 SQLite
- Web 根目录指向 `public`

> 前端 CSS/JS 已预编译到 `public/_next/static/` 目录，部署时不需要 Node.js。

## 关于 `.env` 文件

GitHub 仓库中只保留 `.env.example` 模板文件。真正的 `.env` 是服务器本地配置文件，里面会保存数据库密码、应用密钥、管理员初始密码等敏感信息，因此不会提交到仓库，也不会在 GitHub 页面里看到。

部署时在项目根目录生成 `.env`：

```bash
# 进入项目根目录
cd /www/wwwroot/yeyu-file-express-system

# 从模板复制出服务器本地配置文件
cp .env.example .env

# 生成 Laravel 应用密钥
php artisan key:generate
```

生成后的文件路径是：

```text
/www/wwwroot/yeyu-file-express-system/.env
```

当前兼容基线是 Laravel 9.52，`composer.json` 已把 `config.platform.php` 固定为 `8.0.2`，用于保证普通 PHP 8.0 虚拟主机也能安装同一套依赖。不要在生产机直接改平台版本后执行 `composer update`，否则可能解析出不再兼容 PHP 8.0 的依赖。

## 本地启动

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

也可以在首次打开站点时进入统一风格安装页：未安装状态会自动跳转到 `/install`，页面会检测 PHP 版本、扩展、目录权限，并可填写 SQLite/MySQL 和管理员账号后执行迁移初始化。

后台入口：

- `/admin`：重定向到统一风格后台
- `/admin-lite`：统一风格后台实际入口

初始管理员通过 `.env` 的 `ADMIN_EMAIL` 和 `ADMIN_PASSWORD` 创建，首次上线后应立即修改。

## 验证

```bash
composer why-not php 8.0.2
vendor/bin/phpunit --colors=never
```

生产部署细节见 `docs/deployment.md`。
