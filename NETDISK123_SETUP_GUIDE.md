# 123网盘对接配置指南

## 功能概述

123网盘对接功能允许用户将文件直接上传到123网盘，并自动创建分享链接。这提供了额外的存储选择和分享方式。

## 安装步骤

### 1. 文件结构

系统已自动创建以下文件：

-  - 123网盘服务类
-  - 管理控制器
-  - 配置文件
-  - 管理界面
-  - 测试脚本

### 2. 环境变量

GitHub 仓库只保留 `.env.example` 模板，真实 `.env` 是服务器本地配置文件。部署时先在项目根目录生成 `.env`：

```bash
# 进入项目目录
cd /www/wwwroot/yeyu-file-express-system

# 从模板复制出服务器本地配置文件
cp .env.example .env
```

然后在 `.env` 文件中添加或修改以下配置：

# 网盘系统环境变量示例（替换为你自己的值）
APP_NAME=叶宇文件快递
APP_URL=https://pan.yeyupan.cc
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yeyu_file_express
DB_USERNAME=root
DB_PASSWORD=
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change-me-immediately

### 3. 数据库配置

系统使用  表存储123网盘配置，无需额外数据库迁移。

## 配置方法

### 方法一：通过后台管理界面（推荐）

1. 访问后台管理：
2. 登录后进入123网盘配置页面：
3. 填写配置信息并保存
4. 点击"测试连接"验证配置是否正确

### 方法二：直接修改环境变量

编辑服务器项目根目录的 `.env` 文件，设置以下参数：

-  - 启用123网盘功能
-  - 浏览器Cookie信息
-  - API访问令牌
-  - 用户名
-  - 密码（可选）
-  - 最大文件大小（字节）
-  - 自动创建分享链接
-  - 分享链接过期天数

### 获取Cookie和Token的方法

1. 在浏览器中登录123网盘 (https://www.123pan.com)
2. 按F12打开开发者工具
3. 切换到"Application"标签
4. 在左侧找到"Cookies" → "https://www.123pan.com"
5. 复制所有Cookie值（通常包括登录凭证等）
6. 在"Local Storage"中查找token值

## 功能说明

### 上传文件

1. 用户上传文件时可以选择存储到123网盘
2. 文件会先上传到服务器，然后自动同步到123网盘
3. 上传完成后会创建分享链接

### 分享文件

1. 系统会自动创建分享链接（如果启用了自动分享）
2. 分享链接的过期天数可以配置（1-30天）
3. 用户可以直接通过链接下载文件

### 文件下载

1. 用户点击下载链接时，系统会从123网盘获取下载地址
2. 支持直接下载和通过网盘页面下载

## API接口

### 服务类方法

#### 上传文件
```php
$service = new Netdisk123Service();
$result = $service->uploadFile($filePath, $fileName);
```

#### 创建分享链接
```php
$service = new Netdisk123Service();
$result = $service->createShareLink($fileId, $password);
```

#### 获取下载链接
```php
$service = new Netdisk123Service();
$result = $service->getDownloadLink($shareId, $password);
```

#### 测试连接
```php
$service = new Netdisk123Service();
$result = $service->testConnection();
```

## 测试脚本

运行测试脚本检查配置：

```bash
php /www/wwwroot/yeyu-file-express-system/public/netdisk123-test.php
```

## 访问地址

- 后台管理：
- 123网盘配置：
- 测试脚本：

## 注意事项

1. **安全性**：Cookie和Token是敏感信息，请妥善保管
2. **文件大小限制**：默认最大支持100MB文件，可根据需要调整
3. **有效期**：分享链接默认7天后过期，最长30天
4. **网络要求**：服务器需要能够访问123网盘的API接口
5. **存储空间**：请确保123网盘账户有足够的存储空间

## 故障排查

### 上传失败

1. 检查Cookie和Token是否正确
2. 确认网络连接正常
3. 查看服务器日志：
4. 运行测试脚本验证配置

### 分享链接无效

1. 检查分享链接是否已过期
2. 确认密码是否正确（如果设置了密码）
3. 检查123网盘账户状态是否正常

### 连接测试失败

1. 验证Cookie和Token的有效性
2. 检查服务器网络设置
3. 确认123网盘API服务是否正常

## 技术支持

如遇到问题，请检查：

1. Laravel日志文件
2. 123网盘服务状态
3. 网络连接状态
4. 配置文件权限

## 更新日志

### 2026-06-06
- 首次添加123网盘对接功能
- 支持文件上传到123网盘
- 支持自动创建分享链接
- 提供后台管理界面
- 添加配置测试功能
