<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>登录 - 叶宇文件快递后台</title>
  <link rel="icon" href="/qrlogo.png?v=2">
  <link rel="stylesheet" href="/fonts/misans/font.css">
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
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .login-wrapper {
      width: 100%;
      max-width: 400px;
    }

    .login-brand {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 32px;
      text-decoration: none;
      color: inherit;
    }

    .login-brand img {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      margin-bottom: 16px;
      box-shadow: 0 4px 16px rgba(0,0,0,.08);
    }

    .login-brand h1 {
      font-size: 22px;
      font-weight: 700;
      letter-spacing: -.02em;
      margin-bottom: 4px;
    }

    .login-brand p {
      font-size: 14px;
      color: #86868b;
    }

    .login-card {
      background: #fff;
      border-radius: 20px;
      padding: 32px 28px;
      box-shadow: 0 2px 20px rgba(0,0,0,.06);
    }

    .login-card h2 {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 24px;
      text-align: center;
      letter-spacing: -.01em;
    }

    .login-alert {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .login-alert.error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #991b1b;
    }

    .login-alert svg {
      width: 16px;
      height: 16px;
      flex-shrink: 0;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 18px;
    }

    .field label {
      font-size: 14px;
      font-weight: 600;
      color: #1d1d1f;
    }

    .field input {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #d2d2d7;
      border-radius: 10px;
      font-size: 15px;
      background: #fafafa;
      color: #1d1d1f;
      transition: border-color .2s, box-shadow .2s;
      outline: none;
      font-family: inherit;
    }

    .field input:focus {
      border-color: #0071e3;
      box-shadow: 0 0 0 3px rgba(0,113,227,.12);
      background: #fff;
    }

    .field input::placeholder {
      color: #aeaeb2;
    }

    .login-submit {
      width: 100%;
      padding: 13px 24px;
      background: #1d1d1f;
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background .2s, transform .1s;
      font-family: inherit;
      margin-top: 4px;
    }

    .login-submit:hover {
      background: #333;
    }

    .login-submit:active {
      transform: scale(.98);
    }

    .login-footer {
      text-align: center;
      margin-top: 20px;
    }

    .login-footer a {
      font-size: 13px;
      color: #0071e3;
      text-decoration: none;
      font-weight: 500;
    }

    .login-footer a:hover {
      text-decoration: underline;
    }

    @media (max-width: 480px) {
      body {
        align-items: flex-start;
        padding-top: 15vh;
      }
      .login-card {
        padding: 24px 20px;
      }
    }
  </style>
  <link rel="stylesheet" href="/css/admin-panel.css?v=20260520">
</head>
<body class="fe-admin-login">
  <div class="login-wrapper">
    <a href="/" class="login-brand">
      <img src="/qrlogo.png" alt="Logo">
      <h1>叶宇文件快递</h1>
      <p>管理后台</p>
    </a>

    <div class="login-card">
      <h2>管理员登录</h2>

      @if ($errors->any())
        <div class="login-alert error">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('admin-lite.login.submit') }}" autocomplete="on">
        @csrf
        <div class="field">
          <label for="email">邮箱</label>
          <input id="email" name="email" type="email" placeholder="admin@example.com" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </div>
        <div class="field">
          <label for="password">密码</label>
          <input id="password" name="password" type="password" placeholder="请输入密码" autocomplete="current-password" required>
        </div>
        <button type="submit" class="login-submit">登录</button>
      </form>
    </div>

    <div class="login-footer">
      <a href="/">返回首页</a>
    </div>
  </div>
</body>
</html>
