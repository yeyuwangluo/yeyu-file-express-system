<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>编辑管理员 - 叶宇文件快递</title>
  <link rel="icon" href="/qrlogo.png?v=2">
  <link rel="stylesheet" href="/fonts/misans/font.css">
  <link rel="stylesheet" href="/build/assets/app-DWxc1te1.css">
  <link rel="stylesheet" href="/css/admin-panel.css?v=20260520">
</head>
<body class="fe-page fe-admin">
  <main class="admin-edit-shell">
    <div class="admin-edit-top">
      <a class="sidebar-brand" href="{{ route('admin-lite.dashboard', ['tab' => 'users']) }}">
        <img src="/qrlogo.png" alt="Logo">
        <span><strong>叶宇文件快递</strong><small>Admin Panel</small></span>
      </a>
      <a class="admin-action secondary" href="{{ route('admin-lite.dashboard', ['tab' => 'users']) }}">返回管理员</a>
    </div>

    @if (session('status'))
      <div class="status-msg">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="error-msg">{{ $errors->first() }}</div>
    @endif

    <section class="section-card">
      <div class="admin-header">
        <div>
          <h1>编辑管理员</h1>
          <span class="muted">{{ $adminUser->email }}</span>
        </div>
        <span class="pill">{{ $adminUser->status }}</span>
      </div>

      <form method="post" action="{{ route('admin-lite.users.update', $adminUser) }}" class="form-grid">
        @csrf
        @method('put')

        <label>名称
          <input name="name" value="{{ old('name', $adminUser->name) }}" required>
        </label>

        <label>邮箱
          <input name="email" type="email" value="{{ old('email', $adminUser->email) }}" required>
        </label>

        <label>角色
          <select name="role" required>
            @foreach(['owner', 'admin', 'viewer'] as $role)
              <option value="{{ $role }}" @selected(old('role', $adminUser->role ?? 'admin') === $role)>{{ $role }}</option>
            @endforeach
          </select>
        </label>

        <label>状态
          <select name="status" required>
            <option value="active" @selected(old('status', $adminUser->status) === 'active')>active</option>
            <option value="disabled" @selected(old('status', $adminUser->status) === 'disabled')>disabled</option>
          </select>
        </label>

        <label class="full">权限
          <textarea name="permissions" rows="4" placeholder="admins.manage">{{ old('permissions', implode("\n", $adminUser->permissions_json ?? [])) }}</textarea>
        </label>

        <label class="full">新密码
          <input name="password" type="password" minlength="8" placeholder="留空则不修改密码">
        </label>

        <div class="admin-edit-actions">
          <button type="submit">保存修改</button>
          <a class="admin-action secondary" href="{{ route('admin-lite.dashboard', ['tab' => 'users']) }}">取消</a>
        </div>
      </form>
    </section>
  </main>
</body>
</html>
