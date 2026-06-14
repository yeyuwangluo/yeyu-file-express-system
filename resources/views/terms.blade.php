<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>用户协议与隐私政策 - 叶宇文件快递</title>
  <meta name="description" content="叶宇文件快递用户协议与隐私政策">
  <link rel="icon" href="/qrlogo.png?v=2">
  <link rel="stylesheet" href="/fonts/misans/font.css">
  <link rel="stylesheet" href="/build/assets/app-DWxc1te1.css">
  <style>
    .terms-body {
      margin: 0;
      min-height: 100vh;
      background: var(--fe-page-bg, #f4f6fb);
      color: var(--fe-text, #172033);
      font-family: MiSans, "Microsoft YaHei", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .terms-shell {
      width: min(960px, calc(100% - 32px));
      margin: 0 auto;
      padding: 16px 0 56px;
    }

    .terms-nav {
      position: sticky;
      top: 12px;
      z-index: 30;
      margin-bottom: 42px;
    }

    .terms-nav-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      min-height: 58px;
      padding: 10px 14px;
      border: 1px solid var(--fe-border, rgba(148, 163, 184, .32));
      border-radius: 8px;
      background: var(--fe-nav-bg, rgba(255, 255, 255, .84));
      box-shadow: 0 16px 36px -26px rgba(15, 23, 42, .45);
      backdrop-filter: blur(26px);
    }

    .terms-brand {
      display: grid;
      grid-template-columns: 36px minmax(0, 1fr);
      align-items: center;
      column-gap: 10px;
      min-width: 0;
      color: inherit;
      text-decoration: none;
    }

    .terms-brand img {
      width: 36px;
      height: 36px;
      border-radius: 8px;
    }

    .terms-brand strong,
    .terms-brand small {
      display: block;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .terms-brand strong {
      font-size: 15px;
      font-weight: 800;
      line-height: 1.25;
    }

    .terms-brand small {
      margin-top: 2px;
      color: var(--fe-muted, #667085);
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0;
      text-transform: uppercase;
    }

    .terms-actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 8px;
    }

    .terms-actions a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 36px;
      padding: 8px 12px;
      border-radius: 8px;
      color: var(--fe-muted, #667085);
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      transition: background-color .16s ease, color .16s ease;
    }

    .terms-actions a:hover,
    .terms-actions a:focus-visible {
      background: var(--fe-control-bg, rgba(15, 23, 42, .06));
      color: var(--fe-text, #172033);
      outline: none;
    }

    .terms-hero {
      max-width: 760px;
      margin: 0 auto 24px;
      text-align: center;
    }

    .terms-kicker {
      display: inline-flex;
      align-items: center;
      min-height: 30px;
      margin-bottom: 12px;
      padding: 6px 10px;
      border: 1px solid rgba(37, 99, 235, .18);
      border-radius: 8px;
      background: rgba(37, 99, 235, .08);
      color: #1d4ed8;
      font-size: 13px;
      font-weight: 800;
    }

    .terms-hero h1 {
      margin: 0;
      color: var(--fe-text, #172033);
      font-size: clamp(26px, 4vw, 42px);
      line-height: 1.14;
      font-weight: 900;
      letter-spacing: 0;
    }

    .terms-hero p {
      max-width: 620px;
      margin: 14px auto 0;
      color: var(--fe-muted, #667085);
      font-size: 15px;
      line-height: 1.8;
    }

    .terms-tabs {
      display: flex;
      justify-content: center;
      gap: 6px;
      width: fit-content;
      margin: 0 auto 18px;
      padding: 5px;
      border: 1px solid var(--fe-border, rgba(148, 163, 184, .28));
      border-radius: 8px;
      background: var(--fe-control-bg, rgba(15, 23, 42, .05));
    }

    .terms-tab {
      min-height: 40px;
      padding: 9px 18px;
      border: 0;
      border-radius: 7px;
      background: transparent;
      color: var(--fe-muted, #667085);
      cursor: pointer;
      font: inherit;
      font-size: 14px;
      font-weight: 800;
      transition: background-color .16s ease, color .16s ease, box-shadow .16s ease;
    }

    .terms-tab:hover,
    .terms-tab:focus-visible {
      color: var(--fe-text, #172033);
      outline: none;
    }

    .terms-tab.active {
      background: var(--fe-panel-strong-bg, #fff);
      color: var(--fe-text, #172033);
      box-shadow: 0 10px 28px -22px rgba(15, 23, 42, .55);
    }

    .terms-panel[hidden] {
      display: none;
    }

    .terms-content {
      border: 1px solid var(--fe-border, rgba(148, 163, 184, .28));
      border-radius: 8px;
      background: var(--fe-panel-bg, rgba(255, 255, 255, .82));
      box-shadow: 0 24px 60px -44px rgba(15, 23, 42, .45);
      padding: 30px;
      backdrop-filter: blur(18px);
    }

    .terms-content h2 {
      margin: 0 0 16px;
      color: var(--fe-text, #172033);
      font-size: 22px;
      line-height: 1.35;
      font-weight: 900;
      letter-spacing: 0;
    }

    .terms-content h3 {
      margin: 26px 0 10px;
      color: var(--fe-text, #172033);
      font-size: 17px;
      line-height: 1.45;
      font-weight: 850;
    }

    .terms-content p,
    .terms-content li {
      color: var(--fe-text, #172033);
      font-size: 15px;
      line-height: 1.88;
    }

    .terms-content p {
      margin: 0 0 12px;
    }

    .terms-content ul,
    .terms-content ol {
      margin: 0 0 14px;
      padding-left: 21px;
    }

    .terms-content li {
      margin-bottom: 6px;
    }

    .terms-content strong {
      font-weight: 850;
    }

    .terms-content .warn-box {
      display: flex;
      gap: 10px;
      margin: 18px 0;
      padding: 14px 16px;
      border: 1px solid rgba(148, 163, 184, .3);
      border-radius: 8px;
      background: rgba(255, 255, 255, .54);
      font-size: 14px;
      line-height: 1.7;
    }

    .terms-content .warn-box.danger {
      border-color: rgba(220, 38, 38, .24);
      background: rgba(254, 242, 242, .78);
    }

    .terms-content .warn-box.success {
      border-color: rgba(22, 163, 74, .22);
      background: rgba(240, 253, 244, .78);
    }

    .terms-consent {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: center;
      gap: 18px;
      margin-top: 18px;
      padding: 18px;
      border: 1px solid rgba(37, 99, 235, .22);
      border-radius: 8px;
      background: rgba(239, 246, 255, .86);
      box-shadow: 0 18px 44px -34px rgba(37, 99, 235, .5);
    }

    .terms-consent strong {
      display: block;
      margin-bottom: 4px;
      color: #1e3a8a;
      font-size: 16px;
      font-weight: 900;
    }

    .terms-consent p {
      margin: 0;
      color: #315a93;
      font-size: 14px;
      line-height: 1.65;
    }

    .terms-consent-actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 10px;
    }

    .terms-accept-button,
    .terms-secondary-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 42px;
      border-radius: 8px;
      padding: 10px 16px;
      font: inherit;
      font-size: 14px;
      font-weight: 850;
      text-decoration: none;
      cursor: pointer;
      transition: transform .16s ease, background-color .16s ease, color .16s ease, border-color .16s ease;
    }

    .terms-accept-button {
      border: 1px solid #2563eb;
      background: #2563eb;
      color: #fff;
    }

    .terms-accept-button:hover,
    .terms-accept-button:focus-visible {
      background: #1d4ed8;
      border-color: #1d4ed8;
      outline: none;
      transform: translateY(-1px);
    }

    .terms-accept-button.accepted {
      border-color: #16a34a;
      background: #16a34a;
    }

    .terms-secondary-link {
      border: 1px solid rgba(37, 99, 235, .22);
      background: rgba(255, 255, 255, .62);
      color: #1e40af;
    }

    .terms-secondary-link:hover,
    .terms-secondary-link:focus-visible {
      background: #fff;
      outline: none;
    }

    .terms-accept-status {
      grid-column: 1 / -1;
      padding-top: 2px;
      color: #15803d;
      font-size: 13px;
      font-weight: 750;
    }

    .terms-footer {
      margin-top: 30px;
      color: var(--fe-muted, #667085);
      text-align: center;
      font-size: 13px;
      line-height: 1.7;
    }

    .terms-footer a {
      color: #2563eb;
      text-decoration: none;
      font-weight: 750;
    }

    .terms-footer a:hover {
      text-decoration: underline;
    }

    @media (prefers-color-scheme: dark) {
      .terms-body {
        background: #10141c;
        color: #f4f7fb;
      }

      .terms-nav-inner,
      .terms-content {
        background: rgba(24, 31, 43, .84);
        border-color: rgba(148, 163, 184, .24);
      }

      .terms-kicker {
        border-color: rgba(96, 165, 250, .28);
        background: rgba(37, 99, 235, .18);
        color: #93c5fd;
      }

      .terms-content h2,
      .terms-content h3,
      .terms-content p,
      .terms-content li,
      .terms-tab.active,
      .terms-brand {
        color: #f8fafc;
      }

      .terms-tabs {
        background: rgba(148, 163, 184, .12);
      }

      .terms-tab.active {
        background: rgba(255, 255, 255, .1);
      }

      .terms-content .warn-box {
        background: rgba(15, 23, 42, .42);
      }

      .terms-content .warn-box.danger {
        background: rgba(127, 29, 29, .24);
      }

      .terms-content .warn-box.success {
        background: rgba(20, 83, 45, .22);
      }

      .terms-consent {
        border-color: rgba(96, 165, 250, .28);
        background: rgba(30, 58, 138, .32);
      }

      .terms-consent strong {
        color: #bfdbfe;
      }

      .terms-consent p {
        color: #c7d2fe;
      }

      .terms-secondary-link {
        border-color: rgba(147, 197, 253, .24);
        background: rgba(255, 255, 255, .08);
        color: #bfdbfe;
      }

      .terms-secondary-link:hover,
      .terms-secondary-link:focus-visible {
        background: rgba(255, 255, 255, .14);
      }

      .terms-accept-status {
        color: #86efac;
      }
    }

    @media (max-width: 720px) {
      .terms-shell {
        width: min(100% - 24px, 960px);
        padding-top: 12px;
      }

      .terms-nav {
        top: 8px;
        margin-bottom: 30px;
      }

      .terms-nav-inner,
      .terms-consent {
        grid-template-columns: 1fr;
      }

      .terms-nav-inner {
        align-items: stretch;
      }

      .terms-actions,
      .terms-consent-actions {
        justify-content: stretch;
      }

      .terms-actions a,
      .terms-accept-button,
      .terms-secondary-link {
        flex: 1 1 auto;
      }

      .terms-hero h1 {
        font-size: 28px;
      }

      .terms-tabs {
        width: 100%;
      }

      .terms-tab {
        flex: 1 1 0;
        padding-inline: 10px;
      }

      .terms-content {
        padding: 22px 18px;
      }
    }

    @media (max-width: 430px) {
      .terms-brand small {
        display: none;
      }

      .terms-nav-inner {
        padding: 10px;
      }

      .terms-actions a {
        padding-inline: 9px;
        font-size: 12px;
      }
    }
  </style>
</head>
<body class="fe-page terms-body">
  <div class="terms-shell">
    <nav class="terms-nav" aria-label="页面导航">
      <div class="terms-nav-inner">
        <a class="terms-brand" href="/">
          <img src="/qrlogo.png" alt="叶宇文件快递">
          <span>
            <strong>叶宇文件快递</strong>
            <small>Yeyu File Express</small>
          </span>
        </a>
        <div class="terms-actions">
          <a href="/">上传</a>
          <a href="/status">状态</a>
          <a href="#terms-consent">同意协议</a>
        </div>
      </div>
    </nav>

    <header class="terms-hero">
      <span class="terms-kicker">服务使用前确认</span>
      <h1>用户协议与隐私政策</h1>
      <p>请阅读用户协议和隐私政策。确认同意后，上传页会记住本次选择，后续可以继续使用文件快递服务。</p>
    </header>

    <div class="terms-tabs" role="tablist" aria-label="协议内容切换">
      <button class="terms-tab active" id="terms-tab-terms" data-tab="terms" type="button" role="tab" aria-controls="terms-panel-terms" aria-selected="true">用户协议</button>
      <button class="terms-tab" id="terms-tab-privacy" data-tab="privacy" type="button" role="tab" aria-controls="terms-panel-privacy" aria-selected="false">隐私政策</button>
    </div>

    <section class="terms-panel active" id="terms-panel-terms" data-terms-panel="terms" role="tabpanel" aria-labelledby="terms-tab-terms">
      <div class="terms-content">
        {!! $termsContent !!}
      </div>
    </section>

    <section class="terms-panel" id="terms-panel-privacy" data-terms-panel="privacy" role="tabpanel" aria-labelledby="terms-tab-privacy" hidden>
      <div class="terms-content">
        {!! $privacyContent !!}
      </div>
    </section>

    <section class="terms-consent" id="terms-consent" aria-label="同意协议">
      <div>
        <strong>阅读完成后继续使用</strong>
        <p>点击同意后，系统会在当前浏览器记录同意状态。若本页由上传页打开，确认后会自动尝试关闭本页。</p>
      </div>
      <div class="terms-consent-actions">
        <button class="terms-accept-button" id="acceptTerms" type="button">我已阅读并同意</button>
        <a class="terms-secondary-link" href="/">返回首页</a>
      </div>
      <div class="terms-accept-status" id="acceptStatus" hidden>已记录同意状态，正在返回。</div>
    </section>

    <footer class="terms-footer">
      <p>如有疑问，请联系站点管理员。</p>
      <p><a href="/">返回首页</a></p>
    </footer>
  </div>

  <script>
    (function() {
      var acceptedKey = 'terms_accepted';
      var acceptedVersion = '2025.12.22';
      var tabs = document.querySelectorAll('.terms-tab');
      var panels = document.querySelectorAll('.terms-panel');
      var acceptButton = document.getElementById('acceptTerms');
      var acceptStatus = document.getElementById('acceptStatus');

      function showPanel(target) {
        tabs.forEach(function(tab) {
          var isActive = tab.dataset.tab === target;
          tab.classList.toggle('active', isActive);
          tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach(function(panel) {
          var isActive = panel.dataset.termsPanel === target;
          panel.classList.toggle('active', isActive);
          panel.hidden = !isActive;
        });
      }

      function hasAccepted() {
        try {
          return window.localStorage.getItem(acceptedKey) === acceptedVersion;
        } catch (error) {
          return false;
        }
      }

      function setAcceptedState() {
        acceptButton.classList.add('accepted');
        acceptButton.textContent = '已同意，正在返回';
        acceptStatus.hidden = false;
      }

      tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
          showPanel(tab.dataset.tab);
        });
      });

      if (hasAccepted()) {
        acceptButton.classList.add('accepted');
        acceptButton.textContent = '已同意，返回首页';
        acceptStatus.textContent = '当前浏览器已记录同意状态。';
        acceptStatus.hidden = false;
      }

      acceptButton.addEventListener('click', function() {
        try {
          window.localStorage.setItem(acceptedKey, acceptedVersion);
        } catch (error) {
          acceptStatus.textContent = '浏览器阻止了本地记录，请允许本地存储后重试。';
          acceptStatus.hidden = false;
          return;
        }

        setAcceptedState();

        try {
          if (window.opener && !window.opener.closed) {
            window.opener.postMessage({ type: 'yeyu:terms-accepted', version: acceptedVersion }, window.location.origin);
            window.setTimeout(function() {
              window.close();
            }, 550);
            return;
          }
        } catch (error) {
        }

        window.setTimeout(function() {
          window.location.href = '/';
        }, 650);
      });
    })();
  </script>
</body>
</html>
