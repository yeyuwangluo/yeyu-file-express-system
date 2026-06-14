# OSS对象存储配置指南

## 功能概述
叶宇文件快递系统现已支持OSS对象存储功能，兼容以下存储服务：
- 阿里云OSS
- 腾讯云COS
- AWS S3
- 本地存储

## 配置步骤

### 1. 访问OSS配置页面


### 2. 选择存储方式
- **本地存储**: 默认方式，文件存储在服务器本地
- **阿里云OSS**: 使用阿里云对象存储服务
- **腾讯云COS**: 使用腾讯云对象存储服务

### 3. 配置阿里云OSS

#### 3.1 获取阿里云OSS配置信息
1. 登录阿里云控制台
2. 进入对象存储OSS服务
3. 创建或选择Bucket
4. 获取以下信息：
   - Access Key ID
   - Access Key Secret
   - Region (如: oss-cn-hangzhou)
   - Bucket名称
   - Endpoint (如: oss-cn-hangzhou.aliyuncs.com)

#### 3.2 填写配置信息
在OSS配置页面中填写：
- **Access Key ID**: 阿里云Access Key ID
- **Access Key Secret**: 阿里云Access Key Secret
- **Region**: 所在区域，如 oss-cn-hangzhou
- **Bucket**: 存储桶名称
- **Endpoint**: 访问端点，如 oss-cn-hangzhou.aliyuncs.com
- **访问URL**: 可选，自定义访问URL

### 4. 配置腾讯云COS

#### 4.1 获取腾讯云COS配置信息
1. 登录腾讯云控制台
2. 进入对象存储COS服务
3. 创建或选择存储桶
4. 获取以下信息：
   - Secret ID
   - Secret Key
   - Region (如: ap-guangzhou)
   - Bucket名称
   - Endpoint (如: cos.ap-guangzhou.myqcloud.com)

#### 4.2 填写配置信息
在OSS配置页面中填写：
- **Secret ID**: 腾讯云Secret ID
- **Secret Key**: 腾讯云Secret Key
- **Region**: 所在区域，如 ap-guangzhou
- **Bucket**: 存储桶名称
- **Endpoint**: 访问端点，如 cos.ap-guangzhou.myqcloud.com
- **访问URL**: 可选，自定义访问URL

### 5. 测试连接
配置完成后，点击测试连接按钮，或者访问：


### 6. 保存配置
点击保存配置按钮保存所有设置。

## 环境变量配置 (可选)

除了通过后台配置，也可以直接在.env文件中设置：

### 阿里云OSS配置
```env
OSS_PROVIDER=aliyun
OSS_ACCESS_KEY_ID=your-access-key-id
OSS_ACCESS_KEY_SECRET=your-access-key-secret
OSS_REGION=oss-cn-hangzhou
OSS_BUCKET=your-bucket
OSS_ENDPOINT=oss-cn-hangzhou.aliyuncs.com
```

### 腾讯云COS配置
```env
OSS_PROVIDER=tencent
OSS_ACCESS_KEY_ID=your-secret-id
OSS_ACCESS_KEY_SECRET=your-secret-key
OSS_REGION=ap-guangzhou
OSS_BUCKET=your-bucket
OSS_ENDPOINT=cos.ap-guangzhou.myqcloud.com
```

## 注意事项

### 安全建议
1. 不要将Access Key等敏感信息提交到版本控制系统
2. 定期更换Access Key
3. 设置适当的Bucket访问权限
4. 启用防盗链和访问控制

### 性能优化
1. 选择就近的Region以减少延迟
2. 使用CDN加速访问
3. 合理设置文件缓存策略

### 备份策略
1. 重要文件建议多地域备份
2. 定期检查OSS存储状态
3. 设置生命周期管理策略

### 成本控制
1. 关注存储用量和流量费用
2. 设置合理的存储类型
3. 定期清理过期文件

## 故障排查

### 连接失败
1. 检查Access Key是否正确
2. 确认Bucket名称和Region是否匹配
3. 检查网络连接和防火墙设置
4. 验证Endpoint格式是否正确

### 文件上传失败
1. 确认Bucket是否有写入权限
2. 检查文件大小是否超出限制
3. 验证存储空间是否充足
4. 查看服务器错误日志

### 访问速度慢
1. 选择更近的Region
2. 启用CDN加速
3. 优化网络连接
4. 检查服务器带宽

## 返回本地存储
如需切回本地存储：
1. 访问OSS配置页面
2. 选择本地存储
3. 点击保存配置

## 技术支持
如遇到问题，请查看：
- 服务器日志: /www/wwwroot/yeyu-file-express-system/storage/logs/
- Nginx日志: /www/wwwlogs/pan.yeyupan.cc.log
- OSS测试页面: /oss-test.php

---

*最后更新: 2026-06-06*
