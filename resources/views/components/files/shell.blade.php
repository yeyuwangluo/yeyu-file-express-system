<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="叶宇文件快递文件分享页面">
  <title>{{ $title ?? '叶宇文件快递' }}</title>
  <link rel="icon" href="/qrlogo.png?v=2">
  <link rel="stylesheet" href="/fonts/misans/font.css">
  <link rel="stylesheet" href="/build/assets/app-DWxc1te1.css">
  <link rel="stylesheet" href="/_next/static/css/1c8d3152a8988e8c.css">
  <link rel="stylesheet" href="/replica-enhance.css">
  <link rel="stylesheet" href="/css/file-share.css?v=20260606-scrollable">
  <style>
    .fe-file-page {
      min-height: 100vh;
      background:
        radial-gradient(circle at 14% 10%, rgba(79, 70, 229, .24), transparent 28%),
        radial-gradient(circle at 86% 8%, rgba(20, 184, 166, .18), transparent 25%),
        radial-gradient(circle at 50% 105%, rgba(59, 130, 246, .16), transparent 32%),
        linear-gradient(135deg, #eef5ff, #e8eeff 48%, #eef8f7);
      color: #0f172a;
      position: relative;
      overflow-x: hidden;
    }
    .fe-file-page::before {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      background-image:
        linear-gradient(rgba(15, 23, 42, .035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(15, 23, 42, .035) 1px, transparent 1px);
      background-size: 46px 46px;
      mask-image: radial-gradient(circle at 50% 12%, black, transparent 72%);
    }
    .fe-file-page::after {
      content: '';
      position: fixed;
      right: -120px;
      top: 18%;
      width: 320px;
      height: 320px;
      border-radius: 999px;
      background: rgba(20, 184, 166, .14);
      filter: blur(22px);
      pointer-events: none;
    }
    .fe-file-page .panel {
      width: min(760px, calc(100% - 32px));
      border: 0;
      background: transparent;
      box-shadow: none;
      backdrop-filter: none;
      position: relative;
      z-index: 1;
    }
    .brand {
      margin-bottom: 30px;
      padding: 12px;
      border: 0;
      border-radius: 20px;
      background: transparent;
    }
    .brand img { box-shadow: 0 14px 36px -24px rgba(37, 99, 235, .8); }
    .brand strong { letter-spacing: -.02em; }
    .brand span { color: #64748b; }
    .file-hero {
      display: grid;
      gap: 16px;
      margin-bottom: 20px;
    }
    .file-hero h1 {
      margin: 0;
      font-size: clamp(28px, 5vw, 42px);
      line-height: 1.06;
      letter-spacing: -.04em;
      overflow-wrap: anywhere;
      background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 46%, #0f766e 100%);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    .file-hero .muted {
      max-width: 620px;
      font-size: 15px;
      line-height: 1.8;
    }
    .file-state-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }
    .badge {
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .45);
      border-radius: 999px;
      font-weight: 850;
    }
    .share-code {
      display: grid;
      grid-template-columns: 1fr auto;
      align-items: center;
      gap: 14px;
      border: 0;
      border-radius: 24px;
      background:
        radial-gradient(circle at 92% 18%, rgba(20, 184, 166, .14), transparent 32%),
        linear-gradient(135deg, rgba(59, 130, 246, .09), rgba(79, 70, 229, .04));
      box-shadow: none;
      padding: 18px;
    }
    .share-code span { color: #64748b; font-size: 12px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
    .share-code strong {
      font-size: clamp(30px, 8vw, 54px);
      letter-spacing: .12em;
      color: #1d4ed8;
      text-shadow: 0 12px 34px rgba(37, 99, 235, .18);
    }
    .details {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      margin: 18px 0;
    }
    .details div {
      border: 0;
      border-radius: 16px;
      background: rgba(15, 23, 42, .035);
      padding: 12px 14px;
      box-shadow: none;
    }
    .details dt {
      margin-bottom: 5px;
      font-size: 12px;
      font-weight: 800;
      color: #64748b;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .details dd {
      margin: 0;
      color: #0f172a;
      font-weight: 750;
      overflow-wrap: anywhere;
    }
    .file-callout {
      border-radius: 18px;
      padding: 16px;
      margin: 18px 0;
      font-size: 14px;
      line-height: 1.7;
    }
    .file-callout.safe {
      border: 1px solid rgba(16, 185, 129, .22);
      background: rgba(236, 253, 245, .72);
      color: #065f46;
    }
    .file-callout.warn {
      border: 1px solid rgba(245, 158, 11, .28);
      background: rgba(255, 251, 235, .82);
      color: #78350f;
    }
    .file-callout.danger {
      border: 1px solid rgba(239, 68, 68, .24);
      background: rgba(254, 242, 242, .86);
      color: #7f1d1d;
    }
    .threat-chip {
      display: inline-flex;
      margin: 3px;
      border-radius: 999px;
      background: #fee2e2;
      color: #b91c1c;
      padding: 4px 10px;
      font-size: 12px;
      font-weight: 800;
    }
    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 18px;
    }
    .actions a,
    .actions button,
    .download-form button,
    .mobile-primary-action {
      min-height: 48px;
      border-radius: 16px;
      font-weight: 850;
      box-shadow: 0 18px 46px -30px rgba(15, 23, 42, .6);
      touch-action: manipulation;
    }
    .actions input,
    .actions select {
      min-height: 48px;
      border-radius: 14px;
      border: 1px solid rgba(15, 23, 42, .12);
      background: rgba(255, 255, 255, .68);
      padding: 0 12px;
      font-size: 16px;
    }
    .actions .secondary-link {
      background: rgba(15, 23, 42, .06);
      color: #0f172a;
      box-shadow: none;
    }
    .download-form {
      display: grid;
      grid-template-columns: 1fr auto auto;
      gap: 10px;
      border: 0;
      border-radius: 20px;
      background: rgba(15, 23, 42, .035);
      padding: 12px;
      box-shadow: none;
    }
    .download-form input {
      min-height: 44px;
      border-radius: 12px;
    }
    .download-form .secondary-action {
      background: rgba(15, 23, 42, .06);
      color: #0f172a;
      box-shadow: none;
    }
    .inline-preview {
      margin: 20px 0 18px;
      border: 0;
      border-radius: 24px;
      background: rgba(15, 23, 42, .035);
      box-shadow: none;
      overflow: hidden;
    }
    .inline-preview-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 14px 16px;
      border-bottom: 1px solid rgba(15, 23, 42, .08);
    }
    .inline-preview-head strong {
      display: block;
      color: #0f172a;
      font-size: 15px;
      letter-spacing: -.02em;
    }
    .inline-preview-head span {
      display: block;
      margin-top: 3px;
      color: #64748b;
      font-size: 12px;
      line-height: 1.5;
    }
    .preview-pill {
      flex-shrink: 0;
      border: 1px solid rgba(59, 130, 246, .18);
      border-radius: 999px;
      background: rgba(59, 130, 246, .08);
      color: #1d4ed8 !important;
      padding: 5px 10px;
      font-size: 11px !important;
      font-weight: 900;
      letter-spacing: .08em;
    }
    .inline-preview-stage {
      min-height: 280px;
      background:
        linear-gradient(rgba(15, 23, 42, .04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(15, 23, 42, .04) 1px, transparent 1px),
        rgba(255, 255, 255, .08);
      background-size: 22px 22px;
    }
    .inline-preview-stage iframe {
      display: block;
      width: 100%;
      height: min(58vh, 520px);
      min-height: 280px;
      border: 0;
      background: transparent;
    }
    .inline-preview-empty {
      display: grid;
      min-height: 280px;
      place-items: center;
      padding: 28px;
      color: #64748b;
      font-size: 14px;
      text-align: center;
    }
    @media (max-width: 640px) {
      .fe-file-page { padding-bottom: max(18px, env(safe-area-inset-bottom)); }
      .fe-file-page .panel { width: min(100% - 18px, 760px); padding-top: 12px; }
      .brand { margin-bottom: 22px; }
      .file-hero h1 { font-size: clamp(28px, 9vw, 36px); }
      .file-hero .muted { font-size: 14px; line-height: 1.7; }
      .share-code { grid-template-columns: 1fr; text-align: center; }
      .share-code strong { letter-spacing: .08em; }
      .details { grid-template-columns: 1fr; }
      .download-form { grid-template-columns: 1fr; }
      .download-form input { font-size: 18px; text-align: center; letter-spacing: .08em; }
      .inline-preview-head { align-items: flex-start; flex-direction: column; }
      .inline-preview-stage iframe { height: 360px; }
      .actions { display: grid; grid-template-columns: 1fr; gap: 10px; }
      .actions a, .actions button, .actions input, .actions select { width: 100%; justify-content: center; }
      .mobile-cards { overflow: visible !important; }
      .mobile-cards table, .mobile-cards thead, .mobile-cards tbody, .mobile-cards tr, .mobile-cards th, .mobile-cards td { display: block; width: 100%; }
      .mobile-cards table { min-width: 0 !important; border-collapse: separate !important; }
      .mobile-cards thead { display: none; }
      .mobile-cards tr { border-radius: 18px; background: rgba(255, 255, 255, .56); padding: 12px; margin: 12px 0; box-shadow: 0 12px 30px -26px rgba(15, 23, 42, .55); }
      .mobile-cards td { border: 0 !important; padding: 7px 0 !important; }
      .mobile-cards td::before { content: attr(data-label); display: block; margin-bottom: 3px; color: #64748b; font-size: 12px; font-weight: 850; }
      .mobile-cards td[data-label="选择"]::before { display: none; }
      .mobile-cards td button, .mobile-cards td a { margin: 4px 4px 4px 0; min-height: 38px; }
    }
  </style>
</head>
<body class="fe-page fe-file-page {{ $pageClass ?? '' }}">
  <main class="panel">
    <div class="brand">
      <img src="/qrlogo.png" alt="叶宇文件快递">
      <div><strong>叶宇文件快递</strong><span>本地系统站点</span></div>
    </div>
    {{ $slot }}
  </main>
  <script>
    function showShareToast(message) {
      document.querySelector(".file-share-toast-host")?.remove();

      const host = document.createElement("div");
      host.className = "file-share-toast-host";
      host.setAttribute("aria-live", "polite");
      const toast = document.createElement("div");
      toast.className = "file-share-toast";
      toast.textContent = message;
      host.appendChild(toast);
      document.body.appendChild(host);
      setTimeout(() => host.remove(), 1800);
    }

    document.addEventListener("click", async (event) => {
      const button = event.target.closest("[data-copy]");
      if (!button) return;

      try {
        if (navigator.clipboard && navigator.clipboard.writeText) await navigator.clipboard.writeText(button.dataset.copy);
        else throw new Error('clipboard unavailable');
        showShareToast("链接已复制");
        var oldText = button.textContent;
        button.textContent = "已复制";
        setTimeout(function () { button.textContent = oldText; }, 1400);
      } catch (error) {
        window.prompt("请手动复制链接", button.dataset.copy);
      }
    });
  </script>
</body>
</html>
