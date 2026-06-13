<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>123网盘配置 - 叶宇文件快递</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="mb-4">123网盘配置</h2>
        
        <div class="alert alert-info">
            <strong>功能说明：</strong> 配置123网盘API后，用户可以选择将文件上传到123网盘，并自动创建分享链接。
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">基础配置</h5>
                <button type="button" class="btn btn-info btn-sm" onclick="testConnection()">
                    <i class="bi bi-plug"></i> 测试连接
                </button>
            </div>
            <div class="card-body">
                <form id="netdisk123-form">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="enabled" name="enabled" {{ $netdisk123['enabled'] ? 'checked' : '' }}>
                        <label class="form-check-label" for="enabled">
                            启用123网盘功能
                        </label>
                        <div class="form-text">启用后用户可以选择上传文件到123网盘</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">用户名</label>
                                <input type="text" class="form-control" id="username" name="username" value="{{ $netdisk123['username'] }}" placeholder="123网盘登录用户名">
                                <div class="form-text">123网盘登录用户名</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">访问令牌</label>
                                <input type="text" class="form-control" id="token" name="token" value="{{ $netdisk123['token'] }}" placeholder="从123网盘获取的API访问令牌">
                                <div class="form-text">从123网盘获取的API访问令牌</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Cookie</label>
                        <textarea class="form-control" id="cookie" name="cookie" rows="3" placeholder="浏览器Cookie信息，用于API认证">{{ $netdisk123['cookie'] }}</textarea>
                        <div class="form-text">浏览器Cookie信息，用于API认证</div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">上传配置</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">最大文件大小 (字节)</label>
                            <input type="number" class="form-control" id="max_file_size" name="max_file_size" value="{{ $netdisk123['maxFileSize'] }}" placeholder="默认: 104857600 (100MB)">
                            <div class="form-text">默认: 104857600 (100MB)</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" id="auto_share" name="auto_share" {{ $netdisk123['autoShare'] ? 'checked' : '' }}>
                                <label class="form-check-label" for="auto_share">
                                    自动创建分享链接
                                </label>
                                <div class="form-text">上传后自动创建分享链接</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">分享链接过期天数</label>
                            <input type="number" class="form-control" id="share_expire_days" name="share_expire_days" value="{{ $netdisk123['shareExpireDays'] }}" min="1" max="30" placeholder="1-30天">
                            <div class="form-text">1-30天</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-secondary">
            <h6><i class="bi bi-info-circle"></i> 获取Cookie和Token的方法</h6>
            <ol>
                <li>在浏览器中登录123网盘 (https://www.123pan.com)</li>
                <li>按F12打开开发者工具</li>
                <li>切换到"Network"标签</li>
                <li>刷新页面并任意点击一个请求</li>
                <li>在"Headers"中找到"Request Headers"部分的"Cookie"值</li>
                <li>登录后从localStorage中获取token</li>
            </ol>
        </div>

        <div class="mb-3">
            <button type="button" class="btn btn-primary" onclick="saveConfig()">
                <i class="bi bi-save"></i> 保存配置
            </button>
            <a href="/admin-lite" class="btn btn-secondary">返回后台</a>
        </div>
    </div>

    <script>
    function getCsrfToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }

    function saveConfig() {
        const data = {
            enabled: document.getElementById('enabled').checked,
            username: document.getElementById('username').value,
            token: document.getElementById('token').value,
            cookie: document.getElementById('cookie').value,
            max_file_size: parseInt(document.getElementById('max_file_size').value) || 104857600,
            auto_share: document.getElementById('auto_share').checked,
            share_expire_days: parseInt(document.getElementById('share_expire_days').value) || 7
        };
        
        console.log('提交的数据:', data);
        
        fetch('/admin-lite/netdisk123', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            console.log('响应状态:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('响应数据:', data);
            if (data.success) {
                alert(data.message);
            } else {
                alert('保存失败: ' + JSON.stringify(data.errors));
            }
        })
        .catch(error => {
            console.error('请求错误:', error);
            alert('请求失败: ' + error.message);
        });
    }

    function testConnection() {
        fetch('/admin-lite/netdisk123/test', {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            alert('测试失败: ' + error.message);
        });
    }
    </script>
</body>
</html>