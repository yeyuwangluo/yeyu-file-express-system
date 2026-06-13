<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSS存储配置 - 叶宇文件快递</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="mb-4">OSS对象存储配置</h2>
        
        <div class="alert alert-info">
            配置阿里云OSS或腾讯云COS进行文件存储，无需额外配置即可使用S3兼容的对象存储服务。
        </div>

        <form method="POST" action="/admin-lite/settings" id="ossForm">
            @csrf
            <input type="hidden" name="max_file_size" value="10485760">
            <input type="hidden" name="default_expire_days" value="7">
            <input type="hidden" name="max_expire_days" value="30">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">存储方式选择</h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">存储方式</label>
                        <select class="form-select" name="storage_disk" id="storageDisk">
                            <option value="local" {{ $settings['storage']['disk'] == 'local' ? 'selected' : '' }}>本地存储</option>
                            <option value="oss" {{ $settings['storage']['disk'] == 'oss' ? 'selected' : '' }}>阿里云OSS</option>
                            <option value="tencent" {{ $settings['storage']['disk'] == 'tencent' ? 'selected' : '' }}>腾讯云COS</option>
                        </select>
                        <small class="form-text text-muted">选择文件存储方式，切换后会自动保存配置</small>
                    </div>
                </div>
            </div>

            <div class="card mb-4" id="ossConfig">
                <div class="card-header">
                    <h5 class="mb-0">阿里云OSS配置</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Access Key ID</label>
                                <input type="text" class="form-control" name="oss_access_key_id" value="{{ $settings['storage']['ossAccessKeyId'] }}" placeholder="请输入Access Key ID">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Access Key Secret</label>
                                <input type="password" class="form-control" name="oss_access_key_secret" value="{{ $settings['storage']['ossAccessKeySecret'] }}" placeholder="请输入Access Key Secret">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Region</label>
                                <input type="text" class="form-control" name="oss_region" value="{{ $settings['storage']['ossRegion'] }}" placeholder="如: oss-cn-hangzhou">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Bucket</label>
                                <input type="text" class="form-control" name="oss_bucket" value="{{ $settings['storage']['ossBucket'] }}" placeholder="请输入Bucket名称">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Endpoint</label>
                                <input type="text" class="form-control" name="oss_endpoint" value="{{ $settings['storage']['ossEndpoint'] }}" placeholder="如: oss-cn-hangzhou.aliyuncs.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">访问URL (可选)</label>
                                <input type="text" class="form-control" name="oss_url" value="{{ $settings['storage']['ossUrl'] }}" placeholder="如: https://your-bucket.oss-cn-hangzhou.aliyuncs.com">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" id="tencentConfig">
                <div class="card-header">
                    <h5 class="mb-0">腾讯云COS配置</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Secret ID</label>
                                <input type="text" class="form-control" name="tencent_secret_id" value="{{ $settings['storage']['tencentSecretId'] }}" placeholder="请输入Secret ID">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Secret Key</label>
                                <input type="password" class="form-control" name="tencent_secret_key" value="{{ $settings['storage']['tencentSecretKey'] }}" placeholder="请输入Secret Key">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Region</label>
                                <input type="text" class="form-control" name="tencent_region" value="{{ $settings['storage']['tencentRegion'] }}" placeholder="如: ap-guangzhou">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Bucket</label>
                                <input type="text" class="form-control" name="tencent_bucket" value="{{ $settings['storage']['tencentBucket'] }}" placeholder="请输入Bucket名称">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Endpoint</label>
                                <input type="text" class="form-control" name="tencent_endpoint" value="{{ $settings['storage']['tencentEndpoint'] }}" placeholder="如: cos.ap-guangzhou.myqcloud.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">访问URL (可选)</label>
                                <input type="text" class="form-control" name="tencent_url" value="{{ $settings['storage']['tencentUrl'] }}" placeholder="如: https://your-bucket.cos.ap-guangzhou.myqcloud.com">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <button type="submit" class="btn btn-primary">保存配置</button>
                <a href="/admin-lite" class="btn btn-secondary">返回后台</a>
                <button type="button" class="btn btn-success" onclick="testOSS()">测试连接</button>
            </div>
        </form>
    </div>

    <script>
        // 根据存储方式显示对应的配置项
        document.getElementById('storageDisk').addEventListener('change', function() {
            var disk = this.value;
            document.getElementById('ossConfig').style.display = disk === 'oss' ? 'block' : 'none';
            document.getElementById('tencentConfig').style.display = disk === 'tencent' ? 'block' : 'none';
        });

        // 初始化显示状态
        var currentDisk = document.getElementById('storageDisk').value;
        document.getElementById('ossConfig').style.display = currentDisk === 'oss' ? 'block' : 'none';
        document.getElementById('tencentConfig').style.display = currentDisk === 'tencent' ? 'block' : 'none';

        function testOSS() {
            var disk = document.getElementById('storageDisk').value;
            if (disk === 'local') {
                alert('本地存储无需测试连接');
                return;
            }
            
            var requiredFields = [];
            if (disk === 'oss') {
                if (!document.getElementById('oss_access_key_id')?.value) requiredFields.push('Access Key ID');
                if (!document.getElementById('oss_access_key_secret')?.value) requiredFields.push('Access Key Secret');
                if (!document.getElementById('oss_bucket')?.value) requiredFields.push('Bucket');
            } else if (disk === 'tencent') {
                if (!document.getElementById('tencent_secret_id')?.value) requiredFields.push('Secret ID');
                if (!document.getElementById('tencent_secret_key')?.value) requiredFields.push('Secret Key');
                if (!document.getElementById('tencent_bucket')?.value) requiredFields.push('Bucket');
            }
            
            if (requiredFields.length > 0) {
                alert('请填写以下必填项: ' + requiredFields.join(', '));
                return;
            }
            
            alert('请先保存配置，然后访问 /oss-test.php 进行连接测试');
        }
    </script>
</body>
</html>
