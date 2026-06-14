<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>系统安装 - 叶宇文件快递</title>
  <link rel="icon" href="/qrlogo.png?v=2">
  <link rel="stylesheet" href="/fonts/misans/font.css">
  <link rel="stylesheet" href="/install.css">
</head>
<body class="fe-page fe-install">
  @php
    $errorFor = fn (string $field): ?string => $formErrors[$field][0] ?? null;
    $dbConnection = $input['db_connection'] ?? 'sqlite';
  @endphp
  <main>
    <header class="install-nav">
      <a class="brand" href="/install">
        <img src="/qrlogo.png" alt="叶宇文件快递">
        <span><strong>叶宇文件快递</strong><small>System Setup</small></span>
      </a>
      <div class="install-steps" aria-label="安装步骤">
        <span>环境</span>
        <span>数据库</span>
        <span>管理员</span>
      </div>
    </header>

    <section class="install-hero">
      <div>
        <span class="badge">首次安装</span>
        <h1>系统安装</h1>
        <p class="fe-muted">完成基础环境、数据库和管理员配置后，系统会自动迁移数据表并打开站点。</p>
      </div>
      <div class="install-summary">
        <span>{{ PHP_VERSION }}</span>
        <strong>PHP 8.0.2+</strong>
      </div>
    </section>

    @if (! empty($formErrors['install']))
      <section class="install-alert danger">
        <strong>安装失败</strong>
        <span>{{ $formErrors['install'][0] }}</span>
      </section>
    @endif

    <div class="install-grid">
      <aside class="install-panel">
        <h2>环境检查</h2>
        @foreach (['runtime' => '运行环境', 'writable' => '写入权限'] as $group => $title)
          <div class="check-group">
            <h3>{{ $title }}</h3>
            @foreach ($checks[$group] ?? [] as $check)
              <div class="check-row {{ $check['ok'] ? 'ok' : 'bad' }}">
                <span class="dot"></span>
                <div>
                  <strong>{{ $check['label'] }}</strong>
                  <small>{{ $check['detail'] }}</small>
                </div>
              </div>
            @endforeach
          </div>
        @endforeach
      </aside>

      <form class="install-panel install-form" method="post" action="{{ route('install.store') }}" novalidate>
        <section>
          <div class="section-head">
            <h2>站点设置</h2>
          </div>
          <div class="form-grid">
            <label>站点名称
              <input name="app_name" value="{{ $input['app_name'] ?? '叶宇文件快递' }}" required>
              @if ($errorFor('app_name'))<span class="field-error">{{ $errorFor('app_name') }}</span>@endif
            </label>
            <label>站点地址
              <input name="app_url" value="{{ $input['app_url'] ?? url('/') }}" placeholder="https://example.com" required>
              @if ($errorFor('app_url'))<span class="field-error">{{ $errorFor('app_url') }}</span>@endif
            </label>
          </div>
        </section>

        <section>
          <div class="section-head">
            <h2>数据库</h2>
          </div>
          <div class="segmented" role="radiogroup" aria-label="数据库类型">
            <label>
              <input type="radio" name="db_connection" value="sqlite" @checked($dbConnection !== 'mysql')>
              <span>SQLite</span>
            </label>
            <label>
              <input type="radio" name="db_connection" value="mysql" @checked($dbConnection === 'mysql')>
              <span>MySQL</span>
            </label>
          </div>
          @if ($errorFor('db_connection'))<span class="field-error">{{ $errorFor('db_connection') }}</span>@endif

          <div class="db-panel" data-db-panel="sqlite">
            <label>SQLite 文件
              <input name="sqlite_path" value="{{ $input['sqlite_path'] ?? 'database/database.sqlite' }}">
              @if ($errorFor('sqlite_path'))<span class="field-error">{{ $errorFor('sqlite_path') }}</span>@endif
            </label>
          </div>

          <div class="db-panel" data-db-panel="mysql">
            <div class="form-grid">
              <label>主机
                <input name="db_host" value="{{ $input['db_host'] ?? '127.0.0.1' }}">
                @if ($errorFor('db_host'))<span class="field-error">{{ $errorFor('db_host') }}</span>@endif
              </label>
              <label>端口
                <input name="db_port" type="number" value="{{ $input['db_port'] ?? '3306' }}">
                @if ($errorFor('db_port'))<span class="field-error">{{ $errorFor('db_port') }}</span>@endif
              </label>
              <label>数据库名
                <input name="db_database" value="{{ $input['db_database'] ?? 'yeyu_file_express' }}">
                @if ($errorFor('db_database'))<span class="field-error">{{ $errorFor('db_database') }}</span>@endif
              </label>
              <label>用户名
                <input name="db_username" value="{{ $input['db_username'] ?? 'root' }}">
                @if ($errorFor('db_username'))<span class="field-error">{{ $errorFor('db_username') }}</span>@endif
              </label>
              <label class="span-2">密码
                <input name="db_password" type="password" value="{{ $input['db_password'] ?? '' }}" autocomplete="new-password">
                @if ($errorFor('db_password'))<span class="field-error">{{ $errorFor('db_password') }}</span>@endif
              </label>
            </div>
          </div>
        </section>

        <section>
          <div class="section-head">
            <h2>管理员</h2>
          </div>
          <div class="form-grid">
            <label>名称
              <input name="admin_name" value="{{ $input['admin_name'] ?? 'Administrator' }}" required>
              @if ($errorFor('admin_name'))<span class="field-error">{{ $errorFor('admin_name') }}</span>@endif
            </label>
            <label>邮箱
              <input name="admin_email" type="email" value="{{ $input['admin_email'] ?? 'admin@example.com' }}" required>
              @if ($errorFor('admin_email'))<span class="field-error">{{ $errorFor('admin_email') }}</span>@endif
            </label>
            <label>密码
              <input name="admin_password" type="password" minlength="8" autocomplete="new-password" required>
              @if ($errorFor('admin_password'))<span class="field-error">{{ $errorFor('admin_password') }}</span>@endif
            </label>
            <label>确认密码
              <input name="admin_password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
            </label>
          </div>
        </section>

        <div class="install-actions">
          <button type="submit">开始安装</button>
        </div>
      </form>
    </div>
  </main>
  <script>
    (() => {
      const radios = document.querySelectorAll('input[name="db_connection"]');
      const panels = document.querySelectorAll('[data-db-panel]');
      const sync = () => {
        const selected = document.querySelector('input[name="db_connection"]:checked')?.value || 'sqlite';
        panels.forEach((panel) => {
          panel.hidden = panel.dataset.dbPanel !== selected;
        });
      };
      radios.forEach((radio) => radio.addEventListener('change', sync));
      sync();
    })();
  </script>
</body>
</html>
