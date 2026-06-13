<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI扫描配置 - 叶宇文件快递后台</title>
  <link rel="icon" href="/qrlogo.png?v=2">
  <link rel="stylesheet" href="/fonts/misans/font.css">
  <link rel="stylesheet" href="/build/assets/app-DWxc1te1.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue",
                   "PingFang SC", "MiSans", "Noto Sans SC", sans-serif;
      background: #f5f5f7;
      color: #1d1d1f;
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .container-fluid {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }

    .card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      overflow: hidden;
      margin-bottom: 20px;
    }

    .card-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px 24px;
      border-bottom: 1px solid #e5e7eb;
    }

    .card-header h4 {
      margin: 0;
      font-size: 18px;
      font-weight: 700;
    }

    .card-body {
      padding: 24px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: #374151;
    }

    .form-control {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 14px;
      transition: border-color 0.2s;
    }

    .form-control:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .btn {
      display: inline-block;
      padding: 10px 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
      margin-right: 10px;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .btn-test {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .btn-test:hover {
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .btn:disabled:hover {
      box-shadow: none;
    }

    .form-text {
      display: block;
      margin-top: 6px;
      font-size: 12px;
      color: #6b7280;
    }

    .text-muted {
      color: #6b7280;
    }

    .custom-control {
      position: relative;
      display: block;
      min-height: 1.5rem;
      padding-left: 1.5rem;
    }

    .custom-control-input {
      position: absolute;
      left: 0;
      top: 0.25rem;
      width: 1rem;
      height: 1rem;
      cursor: pointer;
    }

    .custom-control-label {
      margin-bottom: 0;
      padding-left: 0.5rem;
      cursor: pointer;
    }

    .d-block {
      display: block;
    }

    .status-msg {
      background: #d1fae5;
      border: 1px solid #10b981;
      color: #065f46;
      padding: 8px 12px;
      border-radius: 6px;
      margin-bottom: 16px;
      font-weight: 500;
      font-size: 13px;
    }

    .error-msg {
      background: #fee2e2;
      border: 1px solid #ef4444;
      color: #991b1b;
      padding: 8px 12px;
      border-radius: 6px;
      margin-bottom: 16px;
      font-weight: 500;
      font-size: 13px;
    }

    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    .test-result {
      margin-top: 16px;
      padding: 12px;
      border-radius: 6px;
      display: none;
    }

    .test-result.success {
      background: #d1fae5;
      border: 1px solid #10b981;
      color: #065f46;
    }

    .test-result.error {
      background: #fee2e2;
      border: 1px solid #ef4444;
      color: #991b1b;
    }

    .test-result.loading {
      background: #fef3c7;
      border: 1px solid #f59e0b;
      color: #92400e;
    }

    .test-result h5 {
      margin: 0 0 8px 0;
      font-size: 13px;
      font-weight: 600;
    }

    .test-result pre {
      background: rgba(0,0,0,0.05);
      padding: 10px;
      border-radius: 6px;
      overflow-x: auto;
      font-size: 12px;
      margin: 0;
    }

    .spinner {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid currentColor;
      border-radius: 50%;
      border-top-color: transparent;
      animation: spin 0.6s linear infinite;
      margin-right: 8px;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .button-group {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }

    .section-title {
      margin: 28px 0 16px;
      padding-top: 20px;
      border-top: 1px solid #e5e7eb;
      color: #111827;
      font-size: 16px;
      font-weight: 800;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }

    @media (max-width: 720px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  @if (session('status'))
    <div class="container-fluid">
      <div class="status-msg">{{ session('status') }}</div>
    </div>
  @endif

  @if ($errors->any())
    <div class="container-fluid">
      <div class="error-msg">{{ $errors->first() }}</div>
    </div>
  @endif

  <div class="container-fluid">
    <a href="{{ route('admin-lite.dashboard') }}" class="back-link">← 返回Dashboard</a>
    
    <div class="card">
      <div class="card-header">
        <h4>AI扫描配置</h4>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin-lite.update-ai-settings') }}" id="aiSettingsForm">
          @csrf
          
          <div class="form-group">
            <label class="d-block">启用AI扫描</label>
            <div class="custom-control custom-switch">
              <input type="checkbox" name="ai_scan_enabled" class="custom-control-input" id="ai_scan_enabled" {{ $ai_scan_enabled ? 'checked' : '' }}>
              <label class="custom-control-label" for="ai_scan_enabled">启用AI智能扫描</label>
            </div>
            <small class="form-text text-muted">启用后将使用AI模型进行文件内容分析</small>
          </div>
          
          <div class="form-group">
            <label for="ai_scan_api_url">API地址</label>
            <input type="text" class="form-control" id="ai_scan_api_url" name="ai_scan_api_url" value="{{ $ai_scan_api_url }}" placeholder="https://api.openai.com/v1/chat/completions">
            <small class="form-text text-muted">OpenAI兼容的API地址</small>
          </div>
          
          <div class="form-group">
            <label for="ai_scan_api_key">API密钥</label>
            <input type="password" class="form-control" id="ai_scan_api_key" name="ai_scan_api_key" value="" placeholder="留空保持当前密钥，输入新密钥则覆盖">
            <small class="form-text text-muted">用于访问AI服务的API密钥</small>
          </div>
          
          <div class="form-group">
            <label for="ai_scan_model">模型名称</label>
            <input type="text" class="form-control" id="ai_scan_model" name="ai_scan_model" value="{{ $ai_scan_model }}" placeholder="gpt-4">
            <small class="form-text text-muted">要使用的AI模型名称</small>
          </div>
          
          <div class="form-group">
            <label for="ai_scan_timeout">超时时间(秒)</label>
            <input type="number" class="form-control" id="ai_scan_timeout" name="ai_scan_timeout" value="{{ $ai_scan_timeout }}" min="5" max="120">
            <small class="form-text text-muted">AI API请求超时时间</small>
          </div>
          
          <div class="form-group">
            <label for="ai_scan_max_file_size">最大文件大小(KB)</label>
            <input type="number" class="form-control" id="ai_scan_max_file_size" name="ai_scan_max_file_size" value="{{ $ai_scan_max_file_size }}" min="1" max="1048576">
            <small class="form-text text-muted">AI扫描的最大文件大小限制</small>
          </div>

          <div class="section-title">ZIP 深度扫描策略</div>

          <div class="form-grid">
            <div class="form-group">
              <label for="ai_scan_retry_count">AI重试次数</label>
              <input type="number" class="form-control" id="ai_scan_retry_count" name="ai_scan_retry_count" value="{{ $ai_scan_retry_count }}" min="1" max="5">
              <small class="form-text text-muted">AI接口临时失败时的重试次数</small>
            </div>

            <div class="form-group">
              <label for="archive_max_scan_files">ZIP最大扫描条目数</label>
              <input type="number" class="form-control" id="archive_max_scan_files" name="archive_max_scan_files" value="{{ $archive_max_scan_files }}" min="1" max="100">
              <small class="form-text text-muted">限制单个ZIP内部最多进入AI扫描的文件数量</small>
            </div>
          </div>

          <div class="form-group">
            <label for="archive_scan_extensions">ZIP文本扫描扩展名</label>
            <input type="text" class="form-control" id="archive_scan_extensions" name="archive_scan_extensions" value="{{ $archive_scan_extensions }}">
            <small class="form-text text-muted">逗号分隔。图片扩展名由系统单独识别并走媒体审核。</small>
          </div>

          <div class="form-group">
            <label class="d-block">ZIP内部图片扫描</label>
            <div class="custom-control custom-switch">
              <input type="checkbox" name="archive_media_scan_enabled" class="custom-control-input" id="archive_media_scan_enabled" {{ $archive_media_scan_enabled ? 'checked' : '' }}>
              <label class="custom-control-label" for="archive_media_scan_enabled">扫描ZIP内部图片</label>
            </div>
            <small class="form-text text-muted">开启后将识别ZIP中的 jpg、png、gif、webp、bmp、avif 图片并提交AI媒体审核</small>
          </div>

          <div class="form-group">
            <label for="archive_media_extensions">ZIP图片扫描扩展名</label>
            <input type="text" class="form-control" id="archive_media_extensions" name="archive_media_extensions" value="{{ $archive_media_extensions }}">
            <small class="form-text text-muted">逗号分隔。默认包含 jpg、jpeg、jfif、png、webp、bmp、avif、heic、heif、tif、tiff、ico。</small>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label for="archive_media_max_file_size">ZIP内部单图上限(KB)</label>
              <input type="number" class="form-control" id="archive_media_max_file_size" name="archive_media_max_file_size" value="{{ $archive_media_max_file_size }}" min="1" max="8192">
              <small class="form-text text-muted">默认8192KB，超过上限的内部图片会被跳过并写入报告</small>
            </div>

            <div class="form-group">
              <label for="archive_media_failure_policy">ZIP图片AI失败策略</label>
              <select class="form-control" id="archive_media_failure_policy" name="archive_media_failure_policy">
                <option value="block" {{ $archive_media_failure_policy === 'block' ? 'selected' : '' }}>临时拦截</option>
                <option value="review" {{ $archive_media_failure_policy === 'review' ? 'selected' : '' }}>进入复核风险</option>
                <option value="allow" {{ $archive_media_failure_policy === 'allow' ? 'selected' : '' }}>放行并记录</option>
              </select>
              <small class="form-text text-muted">建议保持临时拦截，降低违规图片漏放风险</small>
            </div>
          </div>
          
          <div class="button-group">
            <button type="button" class="btn btn-test" id="testConnectionBtn" onclick="testAiConnection()">
              <span id="testBtnText">测试连接</span>
            </button>
            <button type="submit" class="btn btn-primary">保存配置</button>
          </div>
        </form>

        <div id="testResult" class="test-result"></div>
      </div>
    </div>
  </div>

  <script>
    function testAiConnection() {
      const apiUrl = document.getElementById('ai_scan_api_url').value.trim();
      const apiKey = document.getElementById('ai_scan_api_key').value.trim();
      const model = document.getElementById('ai_scan_model').value.trim();
      const timeout = document.getElementById('ai_scan_timeout').value;
      
      if (!apiUrl || !apiKey || !model) {
        showTestResult('error', '请先填写API地址、密钥和模型名称');
        return;
      }

      const testBtn = document.getElementById('testConnectionBtn');
      const testBtnText = document.getElementById('testBtnText');
      
      testBtn.disabled = true;
      testBtnText.innerHTML = '<span class="spinner"></span>测试中...';
      showTestResult('loading', '正在测试AI连接，请稍候...');

      fetch('{{ route('admin-lite.ai-settings.test') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value
        },
        body: JSON.stringify({
          api_url: apiUrl,
          api_key: apiKey,
          model: model,
          timeout: timeout || 30
        })
      })
      .then(response => response.json())
      .then(data => {
        testBtn.disabled = false;
        testBtnText.textContent = '测试连接';
        
        if (data.success) {
          showTestResult('success', '✅ ' + data.message, data.data);
        } else {
          showTestResult('error', '❌ ' + data.message);
        }
      })
      .catch(error => {
        testBtn.disabled = false;
        testBtnText.textContent = '测试连接';
        showTestResult('error', '❌ 请求失败: ' + error.message);
      });
    }

    function showTestResult(type, message, data = null) {
      const resultDiv = document.getElementById('testResult');
      resultDiv.className = 'test-result ' + type;
      resultDiv.style.display = 'block';
      
      // 处理多行错误消息
      const formattedMessage = message.replace(/\\n/g, '<br>');
      
      let html = '<h5>' + formattedMessage + '</h5>';
      
      if (data) {
        html += '<pre>';
        html += '提供商: ' + data.provider + '\n';
        html += '模型: ' + data.model + '\n';
        html += '响应: ' + data.response + '\n';
        if (data.tokens_used) {
          html += '消耗Token: ' + data.tokens_used + '\n';
        }
        if (data.response_time) {
          html += '响应时间: ' + data.response_time + '秒';
        }
        html += '</pre>';
      }
      
      resultDiv.innerHTML = html;
    }
  </script>
</body>
</html>
