<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>安装完成 - 叶宇文件快递</title>
  <link rel="icon" href="/qrlogo.png?v=2">
  <link rel="stylesheet" href="/fonts/misans/font.css">
  <link rel="stylesheet" href="/build/assets/app-DWxc1te1.css">
  <style>
    .install-success { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
    .success-card { width: min(100%, 580px); border: 1px solid var(--fe-border); border-radius: var(--fe-radius-panel); background: var(--fe-panel-bg); box-shadow: var(--fe-shadow); backdrop-filter: var(--fe-blur); padding: 40px 32px; }
    .success-icon { width: 72px; height: 72px; margin: 0 auto 24px; border-radius: 50%; background: hsl(var(--heroui-success) / .12); display: flex; align-items: center; justify-content: center; animation: pop .5s cubic-bezier(.34,1.56,.64,1) both; }
    .success-icon svg { width: 36px; height: 36px; color: var(--fe-success); }
    .success-card h1 { margin: 0 0 6px; font-size: 26px; font-weight: 800; color: var(--fe-text); text-align: center; letter-spacing: -.02em; }
    .success-card .subtitle { margin: 0 0 28px; color: var(--fe-muted); font-size: 14px; text-align: center; }
    .inject-section { margin-bottom: 24px; }
    .inject-section h2 { font-size: 14px; font-weight: 700; color: var(--fe-muted); text-transform: uppercase; letter-spacing: .04em; margin: 0 0 10px; }
    .inject-list { display: grid; gap: 6px; }
    .inject-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--fe-border-strong); border-radius: 10px; background: var(--fe-control-bg); font-size: 13px; }
    .inject-item .check { width: 18px; height: 18px; border-radius: 50%; background: hsl(var(--heroui-success) / .15); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .inject-item .check svg { width: 11px; height: 11px; color: var(--fe-success); }
    .inject-item .text { flex: 1; color: var(--fe-text); font-weight: 500; }
    .inject-item .detail { color: var(--fe-muted); font-size: 12px; font-weight: 400; }
    .inject-item code { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px; background: var(--fe-panel-strong-bg); padding: 2px 6px; border-radius: 4px; color: var(--fe-text); }
    .success-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 28px; }
    .success-actions a { display: inline-flex; min-height: 42px; align-items: center; justify-content: center; border-radius: var(--fe-radius-control); padding: 0 22px; font-size: 14px; font-weight: 700; text-decoration: none; cursor: pointer; transition: transform .14s, opacity .14s; }
    .success-actions a:active { transform: scale(.97); }
    .btn-primary { background: var(--fe-text); color: hsl(var(--heroui-background)); }
    .btn-primary:hover { opacity: .82; }
    .btn-secondary { background: var(--fe-control-bg); color: var(--fe-text); border: 1px solid var(--fe-border-strong); }
    .btn-secondary:hover { background: var(--fe-control-hover-bg); }
    .success-footer { text-align: center; margin-top: 24px; color: var(--fe-muted-soft); font-size: 12px; }
    @keyframes pop { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    @media (max-width: 480px) { .success-card { padding: 28px 18px; } }
  </style>
</head>
<body class="fe-page">
  <div class="install-success">
    <div class="success-card">
      <div class="success-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
      </div>
      <h1>安装成功</h1>
      <p class="subtitle">{{ $appName }} 已完成初始化，以下是注入的配置摘要</p>

      {{-- 站点信息 --}}
      <div class="inject-section">
        <h2>站点信息</h2>
        <div class="inject-list">
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">站点名称 <span class="detail">&mdash; <code>{{ $appName }}</code></span></span>
          </div>
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">站点地址 <span class="detail">&mdash; <code>{{ $appUrl }}</code></span></span>
          </div>
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">APP_KEY <span class="detail">&mdash; 已自动生成</span></span>
          </div>
        </div>
      </div>

      {{-- 数据库 --}}
      <div class="inject-section">
        <h2>数据库</h2>
        <div class="inject-list">
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">数据库类型 <span class="detail">&mdash; <code>{{ $dbType }}</code></span></span>
          </div>
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">数据表迁移 <span class="detail">&mdash; 全部迁移完成</span></span>
          </div>
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">初始数据 <span class="detail">&mdash; 已填充种子数据</span></span>
          </div>
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">存储链接 <span class="detail">&mdash; storage:link 已创建</span></span>
          </div>
        </div>
      </div>

      {{-- 管理员 --}}
      <div class="inject-section">
        <h2>管理员账号</h2>
        <div class="inject-list">
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">管理员邮箱 <span class="detail">&mdash; <code>{{ $adminEmail }}</code></span></span>
          </div>
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">密码已加密存储 <span class="detail">&mdash; bcrypt hash 写入 settings 表</span></span>
          </div>
        </div>
      </div>

      {{-- 运行环境 --}}
      <div class="inject-section">
        <h2>运行环境</h2>
        <div class="inject-list">
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">PHP <span class="detail">&mdash; <code>{{ $phpVersion }}</code></span></span>
          </div>
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">Laravel <span class="detail">&mdash; <code>{{ $laravelVersion }}</code></span></span>
          </div>
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">XIAOXIN_FILE_EXPRESS_INSTALLED <span class="detail">&mdash; 标记为 <code>true</code></span></span>
          </div>
          <div class="inject-item">
            <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
            <span class="text">config:clear <span class="detail">&mdash; 配置缓存已清除</span></span>
          </div>
        </div>
      </div>

      <div class="success-actions">
        <a href="/admin-lite/login" class="btn-primary">进入后台</a>
        <a href="/" class="btn-secondary">访问前台</a>
      </div>
      <p class="success-footer">如需修改配置，可登录后台「系统配置」页面进行调整。</p>
    </div>
  </div>
</body>
</html>
