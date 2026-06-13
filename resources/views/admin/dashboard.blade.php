<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>后台管理 - 叶宇文件快递</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="icon" href="/qrlogo.png?v=2">
  <link rel="stylesheet" href="/fonts/misans/font.css">
  <link rel="stylesheet" href="/build/assets/app-DWxc1te1.css">
  <style>
    /* ── Layout ── */
    .admin-shell { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
    @media (max-width: 900px) { .admin-shell { grid-template-columns: 1fr; } .admin-sidebar { display: none; } .admin-sidebar.open { display: flex; position: fixed; inset: 0; z-index: 999; } }

    /* ── Sidebar ── */
    .admin-sidebar {
      flex-direction: column; gap: 0; padding: 20px 12px;
      border-right: 1px solid var(--fe-border-strong);
      background: var(--fe-panel-strong-bg); backdrop-filter: blur(28px);
      position: sticky; top: 0; height: 100vh; overflow-y: auto;
    }
    .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 4px 8px 20px; text-decoration: none; color: var(--fe-text); }
    .sidebar-brand img { width: 36px; height: 36px; border-radius: 10px; }
    .sidebar-brand strong { font-size: 15px; font-weight: 800; display: block; }
    .sidebar-brand small { font-size: 10px; color: var(--fe-muted); text-transform: uppercase; font-weight: 700; }
    .sidebar-section { padding: 16px 8px 6px; font-size: 11px; font-weight: 700; color: var(--fe-muted-soft); text-transform: uppercase; letter-spacing: .06em; }
    .sidebar-item {
      display: flex; align-items: center; gap: 10px; padding: 10px 12px;
      border-radius: 10px; font-size: 14px; font-weight: 600; color: var(--fe-muted);
      cursor: pointer; transition: all .15s ease; user-select: none; border: none; background: none; width: 100%; text-align: left; font-family: inherit;
    }
    .sidebar-item:hover { background: var(--fe-control-bg); color: var(--fe-text); }
    .sidebar-item.active { background: hsl(var(--heroui-primary) / .1); color: hsl(var(--heroui-primary)); }
    .sidebar-item svg { width: 18px; height: 18px; flex-shrink: 0; }
    .sidebar-bottom { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--fe-border-strong); }
    .sidebar-close { display: none; }
    @media (max-width: 900px) { .sidebar-close { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: none; background: var(--fe-control-bg); border-radius: 8px; cursor: pointer; color: var(--fe-text); position: absolute; top: 16px; right: 16px; } }

    /* ── Main Content ── */
    .admin-main { padding: 24px 28px; overflow-y: auto; }
    @media (max-width: 900px) { .admin-main { padding: 16px; } }
    .admin-topbar { display: none; align-items: center; justify-content: space-between; padding: 12px 16px; margin-bottom: 16px; border-radius: 14px; border: 1px solid var(--fe-border); background: var(--fe-nav-bg); backdrop-filter: blur(28px); }
    .admin-topbar h1 { font-size: 16px; font-weight: 700; margin: 0; }
    .admin-topbar button { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border: none; background: var(--fe-control-bg); border-radius: 10px; cursor: pointer; color: var(--fe-text); }
    @media (max-width: 900px) { .admin-topbar { display: flex; } }

    .admin-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
    .admin-header h1 { font-size: 22px; font-weight: 800; color: var(--fe-text); margin: 0; letter-spacing: -.01em; }
    .admin-header .actions { display: flex; gap: 10px; align-items: center; }
    .admin-kicker { color: hsl(var(--heroui-primary)); font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .admin-subtitle { color: var(--fe-muted); font-size: 13px; margin-top: 6px; }

    /* ── Panels ── */
    .panel { display: none; }
    .panel.active { display: block; }

    /* ── Cards Grid ── */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 24px; }
    .stat-card { border: 1px solid var(--fe-border); border-radius: var(--fe-radius-card); background: var(--fe-panel-bg); box-shadow: var(--fe-shadow); padding: 16px; backdrop-filter: var(--fe-blur); }
    .stat-card span { display: block; color: var(--fe-muted); font-size: 12px; font-weight: 600; }
    .stat-card strong { display: block; margin-top: 6px; color: var(--fe-text); font-size: 22px; font-weight: 800; overflow-wrap: anywhere; }
    .stat-card small { display: block; margin-top: 6px; font-size: 12px; line-height: 1.4; }
    .stat-card.attention { border-color: hsl(var(--heroui-warning) / .42); background: linear-gradient(135deg, hsl(var(--heroui-warning) / .14), var(--fe-panel-bg)); }
    .stat-card.danger { border-color: hsl(var(--heroui-danger) / .36); background: linear-gradient(135deg, hsl(var(--heroui-danger) / .12), var(--fe-panel-bg)); }
    .stat-card.success { border-color: hsl(var(--heroui-success) / .32); background: linear-gradient(135deg, hsl(var(--heroui-success) / .12), var(--fe-panel-bg)); }

    /* ── Sections ── */
    .section-card { border: 1px solid var(--fe-border); border-radius: var(--fe-radius-card); background: var(--fe-panel-bg); box-shadow: var(--fe-shadow); padding: 20px; backdrop-filter: var(--fe-blur); margin-bottom: 20px; }
    .section-card h2 { font-size: 17px; font-weight: 700; color: var(--fe-text); margin: 0 0 16px; }
    .section-card h3 { font-size: 14px; font-weight: 700; color: var(--fe-text); margin: 0 0 12px; }
    .section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
    .section-head h2 { margin: 0; }
    .section-head .muted { color: var(--fe-muted); font-size: 13px; }
    .section-intro { color: var(--fe-muted); font-size: 13px; margin: -6px 0 16px; line-height: 1.6; }

    /* ── Tables ── */
    .scroll { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; color: var(--fe-text); font-size: 13px; }
    th, td { border-bottom: 1px solid var(--fe-border-strong); padding: 9px 8px; text-align: left; vertical-align: top; }
    th { color: var(--fe-muted); font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; }

    /* ── Forms ── */
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
    .form-grid label { display: grid; gap: 5px; color: var(--fe-muted); font-size: 13px; font-weight: 600; }
    .form-grid label.full { grid-column: 1 / -1; }
    .form-grid input, .form-grid textarea, .form-grid select { width: 100%; border: 1px solid var(--fe-border-strong); border-radius: var(--fe-radius-control); background: var(--fe-control-bg); color: var(--fe-text); font: inherit; outline: none; padding: 9px 10px; }
    .form-grid input[type="checkbox"], table input[type="checkbox"] { width: 16px; height: 16px; min-width: 16px; padding: 0; border-radius: 4px; background: #fff; accent-color: hsl(var(--heroui-primary)); box-shadow: none; cursor: pointer; }
    .form-grid label:has(input[type="checkbox"]) { display: inline-flex; align-items: center; justify-content: flex-start; gap: 10px; min-height: 24px; padding: 0; border: 0; background: transparent; }
    .form-grid input:focus, .form-grid textarea:focus, .form-grid select:focus { border-color: hsl(var(--heroui-primary)); box-shadow: 0 0 0 3px hsl(var(--heroui-primary) / .16); }
    .form-grid input[type="checkbox"]:focus, table input[type="checkbox"]:focus { box-shadow: 0 0 0 3px hsl(var(--heroui-primary) / .16); }
    .form-grid textarea { min-height: 80px; resize: vertical; }

    /* ── Buttons ── */
    .fe-admin button, .fe-admin .header-actions a { display: inline-flex; min-height: 36px; align-items: center; justify-content: center; border: 0; border-radius: var(--fe-radius-control); background: var(--fe-text); color: hsl(var(--heroui-background)); padding: 0 14px; font: inherit; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; transition: transform .14s ease, opacity .14s ease; }
    .fe-admin button:hover { opacity: .82; }
    .fe-admin button:active { transform: scale(.97); }
    .fe-admin button.danger { background: var(--fe-danger); }
    .fe-admin button.secondary { background: var(--fe-control-bg); color: var(--fe-text); border: 1px solid var(--fe-border-strong); }
    .fe-admin form.inline { display: inline-flex; gap: 6px; align-items: center; margin: 2px; }

    /* ── Misc ── */
    .muted { color: var(--fe-muted); }
    .pill { display: inline-flex; align-items: center; width: fit-content; border: 1px solid hsl(var(--heroui-primary) / .14); border-radius: 999px; background: hsl(var(--heroui-primary) / .1); color: hsl(var(--heroui-primary-700)); padding: 3px 10px; font-size: 12px; font-weight: 700; }
    .pill.ok { border-color: hsl(var(--heroui-success) / .22); background: hsl(var(--heroui-success) / .12); color: #047857; }
    .pill.warn { border-color: hsl(var(--heroui-warning) / .28); background: hsl(var(--heroui-warning) / .16); color: #92400e; }
    .pill.danger { border-color: hsl(var(--heroui-danger) / .22); background: hsl(var(--heroui-danger) / .1); color: #991b1b; }
    .review-preview { position: relative; display: inline-block; border-radius: 10px; overflow: hidden; background: #111827; }
    .review-preview img, .review-preview video { display: block; }
    .review-preview::after { content: attr(data-watermark); position: absolute; inset: auto -20px 10px -20px; transform: rotate(-12deg); background: rgba(15, 23, 42, .72); color: rgba(255,255,255,.88); font-size: 11px; font-weight: 800; letter-spacing: .06em; text-align: center; padding: 6px 0; pointer-events: none; }
    .shortcut-hint { display: flex; flex-wrap: wrap; gap: 6px; margin: -4px 0 12px; }
    .shortcut-hint kbd { border: 1px solid var(--fe-border-strong); border-radius: 7px; background: var(--fe-control-bg); padding: 2px 7px; color: var(--fe-text); font: inherit; font-size: 12px; font-weight: 800; }
    .status-msg { border: 1px solid hsl(var(--heroui-success) / .18); border-radius: var(--fe-radius-control); background: hsl(var(--heroui-success) / .12); color: #047857; padding: 8px 12px; font-size: 13px; margin-bottom: 16px; }
    .error-msg { border: 1px solid hsl(var(--heroui-danger) / .18); border-radius: var(--fe-radius-control); background: hsl(var(--heroui-danger) / .08); color: #991b1b; padding: 8px 12px; font-size: 13px; margin-bottom: 16px; }

    /* ── AI Test Result ── */
    .ai-test-result { display: none; margin-top: 16px; padding: 12px; border-radius: 6px; font-size: 13px; }
    .ai-test-result.success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; }
    .ai-test-result.error { background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; }
    .ai-test-result.loading { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; }
    .ai-test-message { font-weight: 600; margin-bottom: 8px; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; }
    .ai-test-details { background: rgba(0,0,0,0.05); padding: 8px; border-radius: 4px; font-size: 12px; }
    .ai-test-details div { margin-bottom: 4px; }
    .button-group { display: flex; gap: 10px; margin-top: 16px; }
    .button-group button:first-child { background: var(--fe-control-bg); color: var(--fe-text); border: 1px solid var(--fe-border-strong); }

    /* ── Trend ── */
    .trend { display: grid; gap: 8px; }
    .trend-row { display: grid; grid-template-columns: 50px 1fr 1fr; gap: 8px; align-items: center; font-size: 13px; }
    .bar { min-height: 8px; border-radius: 999px; background: hsl(var(--heroui-default-200) / .72); overflow: hidden; }
    .bar span { display: block; height: 8px; border-radius: inherit; background: hsl(var(--heroui-primary)); }
    .bar.download span { background: hsl(var(--heroui-success)); }

    /* ── Filter ── */
    .filter { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-bottom: 14px; }
    .filter label { display: grid; gap: 4px; color: var(--fe-muted); font-size: 12px; font-weight: 600; }
    .filter input, .filter select { width: 100%; border: 1px solid var(--fe-border-strong); border-radius: var(--fe-radius-control); background: var(--fe-control-bg); color: var(--fe-text); font: inherit; outline: none; padding: 8px 10px; font-size: 13px; }

    /* ── Two col ── */
    .two-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; }
    .ops-hero { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(280px, .7fr); gap: 16px; align-items: stretch; }
    @media (max-width: 960px) { .ops-hero { grid-template-columns: 1fr; } }
    .ops-primary { border: 1px solid hsl(var(--heroui-primary) / .18); border-radius: var(--fe-radius-card); background: linear-gradient(135deg, hsl(var(--heroui-primary) / .12), hsl(var(--heroui-background) / .74)); padding: 20px; box-shadow: var(--fe-shadow); }
    .ops-primary h2 { margin: 6px 0 8px; font-size: 24px; letter-spacing: -.02em; }
    .ops-primary p { color: var(--fe-muted); line-height: 1.7; margin: 0; }
    .ops-primary .meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
    .ops-status-card { border: 1px solid var(--fe-border); border-radius: var(--fe-radius-card); background: var(--fe-panel-bg); padding: 18px; box-shadow: var(--fe-shadow); }
    .ops-status-card strong { display: block; font-size: 28px; margin: 6px 0; }
    .ops-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-top: 16px; }
    .ops-mini { border: 1px solid var(--fe-border); border-radius: 16px; background: hsl(var(--heroui-background) / .62); padding: 14px; }
    .ops-mini .label { color: var(--fe-muted); font-size: 12px; font-weight: 700; }
    .ops-mini .value { color: var(--fe-text); font-size: 18px; font-weight: 800; margin-top: 6px; }
    .ops-mini .desc { color: var(--fe-muted); font-size: 12px; margin-top: 5px; line-height: 1.5; }
    .ops-check-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px; }
    .ops-check-item { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; border: 1px solid var(--fe-border); border-radius: 14px; background: hsl(var(--heroui-background) / .6); padding: 12px; }
    .ops-check-item strong { display: block; font-size: 13px; margin-bottom: 4px; }
    .ops-check-item p { color: var(--fe-muted); font-size: 12px; line-height: 1.5; margin: 0; overflow-wrap: anywhere; }
    .history-table td:first-child { white-space: nowrap; }
    .history-issues { max-width: 300px; }

    /* ── Admin visual refresh ── */
    .fe-admin {
      background:
        radial-gradient(circle at 14% 6%, hsl(var(--heroui-primary) / .12), transparent 30%),
        radial-gradient(circle at 86% 10%, hsl(var(--heroui-success) / .10), transparent 28%),
        linear-gradient(135deg, hsl(var(--heroui-background)), hsl(var(--heroui-default-100) / .58));
    }
    .fe-admin .admin-shell { position: relative; }
    .fe-admin .admin-sidebar {
      border-right-color: hsl(var(--heroui-primary) / .12);
      background: linear-gradient(180deg, hsl(var(--heroui-background) / .92), hsl(var(--heroui-default-100) / .82));
      box-shadow: 18px 0 70px -58px rgba(15, 23, 42, .62);
    }
    .fe-admin .sidebar-brand {
      margin-bottom: 8px;
      border-radius: 18px;
      background: hsl(var(--heroui-primary) / .06);
      padding: 12px;
    }
    .fe-admin .sidebar-brand img {
      border-radius: 14px;
      box-shadow: 0 12px 32px -20px hsl(var(--heroui-primary) / .7);
    }
    .fe-admin .sidebar-section { padding-top: 20px; color: hsl(var(--heroui-default-500)); }
    .fe-admin .sidebar-item {
      border: 1px solid transparent;
      border-radius: 14px;
      min-height: 42px;
    }
    .fe-admin .sidebar-item:hover {
      border-color: hsl(var(--heroui-primary) / .12);
      background: hsl(var(--heroui-primary) / .07);
      transform: translateX(2px);
    }
    .fe-admin .sidebar-item.active {
      border-color: hsl(var(--heroui-primary) / .18);
      background: linear-gradient(135deg, hsl(var(--heroui-primary) / .16), hsl(var(--heroui-primary) / .07));
      box-shadow: inset 3px 0 0 hsl(var(--heroui-primary)), 0 14px 36px -30px hsl(var(--heroui-primary) / .9);
    }
    .fe-admin .admin-main { max-width: 1680px; width: 100%; margin: 0 auto; }
    .fe-admin .admin-header {
      border: 1px solid hsl(var(--heroui-default-200) / .7);
      border-radius: 24px;
      background: hsl(var(--heroui-background) / .76);
      box-shadow: 0 24px 70px -52px rgba(15, 23, 42, .48);
      padding: 18px 20px;
      backdrop-filter: blur(28px) saturate(1.12);
    }
    .fe-admin .admin-header h1 { font-size: clamp(22px, 2vw, 30px); letter-spacing: -.045em; }
    .fe-admin .admin-kicker {
      display: inline-flex;
      width: fit-content;
      border: 1px solid hsl(var(--heroui-primary) / .16);
      border-radius: 999px;
      background: hsl(var(--heroui-primary) / .08);
      padding: 4px 9px;
      line-height: 1;
    }
    .fe-admin .stats-grid { gap: 14px; }
    .fe-admin .stat-card,
    .fe-admin .section-card,
    .fe-admin .ops-status-card,
    .fe-admin .ops-mini,
    .fe-admin .ops-check-item,
    .fe-admin .ops-primary {
      border-color: hsl(var(--heroui-default-200) / .72);
      background: hsl(var(--heroui-background) / .76);
      box-shadow: 0 22px 70px -54px rgba(15, 23, 42, .45);
      backdrop-filter: blur(26px) saturate(1.1);
    }
    .fe-admin .stat-card {
      position: relative;
      overflow: hidden;
      min-height: 118px;
      transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
    }
    .fe-admin .stat-card::after {
      content: '';
      position: absolute;
      right: -28px;
      top: -34px;
      width: 92px;
      height: 92px;
      border-radius: 999px;
      background: hsl(var(--heroui-primary) / .09);
    }
    .fe-admin .stat-card:hover {
      border-color: hsl(var(--heroui-primary) / .18);
      box-shadow: 0 26px 80px -52px rgba(15, 23, 42, .55);
      transform: translateY(-2px);
    }
    .fe-admin .stat-card strong { font-size: clamp(22px, 2vw, 30px); letter-spacing: -.035em; }
    .fe-admin .section-card { padding: clamp(18px, 1.8vw, 26px); }
    .fe-admin .section-head {
      border-bottom: 1px solid hsl(var(--heroui-default-200) / .66);
      margin: -2px 0 16px;
      padding-bottom: 14px;
    }
    .fe-admin .section-card h2 { letter-spacing: -.02em; }
    .fe-admin th {
      background: hsl(var(--heroui-default-100) / .72);
      color: hsl(var(--heroui-default-600));
      position: sticky;
      top: 0;
      z-index: 1;
    }
    .fe-admin td { background: hsl(var(--heroui-background) / .28); }
    .fe-admin tr:hover td { background: hsl(var(--heroui-primary) / .035); }
    .fe-admin .scroll {
      border: 1px solid hsl(var(--heroui-default-200) / .72);
      border-radius: 18px;
      background: hsl(var(--heroui-background) / .52);
    }
    .fe-admin .form-grid input,
    .fe-admin .form-grid textarea,
    .fe-admin .form-grid select,
    .fe-admin .filter input,
    .fe-admin .filter select {
      border-radius: 14px;
      background: hsl(var(--heroui-background) / .78);
      transition: border-color .14s ease, box-shadow .14s ease, background .14s ease;
    }
    .fe-admin button,
    .fe-admin .header-actions a {
      border-radius: 14px;
      box-shadow: 0 14px 34px -26px rgba(15, 23, 42, .72);
    }
    .fe-admin button.secondary { box-shadow: none; }
    .fe-admin .pill { min-height: 24px; }
    .fe-admin .status-msg,
    .fe-admin .error-msg {
      border-radius: 16px;
      box-shadow: 0 16px 44px -36px rgba(15, 23, 42, .5);
    }
    .fe-admin .ops-primary { background: linear-gradient(135deg, hsl(var(--heroui-primary) / .14), hsl(var(--heroui-background) / .78)); }
    .fe-admin .ops-primary h2 { font-size: clamp(24px, 2.4vw, 34px); letter-spacing: -.05em; }
    @media (max-width: 900px) {
      .fe-admin .admin-sidebar.open { padding: 20px; background: hsl(var(--heroui-background) / .96); }
      .fe-admin .admin-header { display: block; padding: 16px; }
      .fe-admin .admin-header .actions { margin-top: 14px; flex-wrap: wrap; }
      .fe-admin .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .fe-admin .two-col { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
      .fe-admin .admin-main { padding: 12px; }
      .fe-admin .stats-grid { grid-template-columns: 1fr; }
      .fe-admin .section-card { padding: 16px; border-radius: 20px; }
      .fe-admin .section-head { align-items: flex-start; flex-direction: column; }
      .fe-admin .button-group { flex-wrap: wrap; }
    }

    /* ── Content editor ── */
    .content-editor textarea { min-height: 300px; font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px; line-height: 1.6; }
    .editor-tabs { display: flex; gap: 4px; margin-bottom: 16px; }
    .editor-tab { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; color: var(--fe-muted); cursor: pointer; border: none; background: none; font-family: inherit; transition: all .15s; }
    .editor-tab:hover { background: var(--fe-control-bg); }
    .editor-tab.active { background: hsl(var(--heroui-primary) / .1); color: hsl(var(--heroui-primary)); }
    .editor-panel { display: none; }
    .editor-panel.active { display: block; }
  </style>
  <link rel="stylesheet" href="/css/admin-panel.css?v=20260520">
</head>
<body class="fe-page fe-admin">
  @php
    $navItems = [
      'overview'  => ['icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>', 'label' => '概览'],
      'files'     => ['icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>', 'label' => '文件管理'],
      'settings'  => ['icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>', 'label' => '系统配置'],
      'users'     => ['icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 'label' => '管理员'],
      'content'   => ['icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>', 'label' => '内容管理'],
      'storage'   => ['icon' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>', 'label' => '存储配置'],
      'ai'       => ['icon' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>', 'label' => 'AI扫描'],
      'security'  => ['icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 'label' => '安全'],
      'logs'      => ['icon' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>', 'label' => '日志'],
    ];
    $activePanel = request()->query('tab', 'overview');
  @endphp

  <div class="admin-shell">
    {{-- Sidebar --}}
    <aside class="admin-sidebar" id="sidebar">
      <button class="sidebar-close" onclick="document.getElementById('sidebar').classList.remove('open')" aria-label="关闭菜单">&times;</button>
      <a class="sidebar-brand" href="/">
        <img src="/qrlogo.png" alt="Logo">
        <span><strong>叶宇文件快递</strong><small>Admin Panel</small></span>
      </a>

      <div class="sidebar-section">管理</div>
      @foreach (['overview', 'files'] as $key)
        <button class="sidebar-item {{ $activePanel === $key ? 'active' : '' }}" data-nav="{{ $key }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $navItems[$key]['icon'] !!}</svg>
          {{ $navItems[$key]['label'] }}
        </button>
      @endforeach

      <div class="sidebar-section">系统</div>
      @foreach (['settings', 'storage', 'users', 'content', 'ai'] as $key)
        <button class="sidebar-item {{ $activePanel === $key ? 'active' : '' }}" data-nav="{{ $key }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $navItems[$key]['icon'] !!}</svg>
          {{ $navItems[$key]['label'] }}
        </button>
      @endforeach

      <div class="sidebar-section">运维</div>
      @foreach (['security', 'logs'] as $key)
        <button class="sidebar-item {{ $activePanel === $key ? 'active' : '' }}" data-nav="{{ $key }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $navItems[$key]['icon'] !!}</svg>
          {{ $navItems[$key]['label'] }}
        </button>
      @endforeach

      <div class="sidebar-bottom">
        <a class="sidebar-item" href="/">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          返回前台
        </a>
        <form method="post" action="{{ route('admin-lite.logout') }}" onsubmit="return confirm('确认退出后台？')">
          @csrf
          <button class="sidebar-item" type="submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          退出登录
          </button>
        </form>
      </div>
    </aside>

    {{-- Main Content --}}
    <main class="admin-main">
      <div class="admin-topbar">
        <h1>叶宇文件快递</h1>
        <button onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="打开菜单">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
      </div>

      @if (session('status'))
        <div class="status-msg">{{ session('status') }}</div>
      @endif
      @if (isset($errors) && $errors->any())
        <div class="error-msg">{{ $errors->first() }}</div>
      @endif

      {{-- ═══════════ 概览 ═══════════ --}}
      <div class="panel {{ $activePanel === 'overview' ? 'active' : '' }}" data-panel="overview">
        <div class="admin-header">
          <h1>概览</h1>
        </div>
        <div class="stats-grid">
          <div class="stat-card"><span>今日上传</span><strong>{{ $stats['todayUploads'] }}</strong></div>
          <div class="stat-card"><span>今日下载</span><strong>{{ $stats['todayDownloads'] }}</strong></div>
          <div class="stat-card"><span>今日上传大小</span><strong>{{ number_format($stats['todayUploadBytes'] / 1024 / 1024, 1) }} MB</strong></div>
          <div class="stat-card"><span>活跃文件</span><strong>{{ $stats['activeFiles'] }}</strong></div>
          <div class="stat-card"><span>已过期</span><strong>{{ $stats['expiredFiles'] }}</strong></div>
          <div class="stat-card"><span>存储占用</span><strong>{{ number_format($stats['storageBytes'] / 1024 / 1024, 1) }} MB</strong></div>
        </div>
        <div class="stats-grid">
          <div class="stat-card"><span>24h 扫描</span><strong>{{ $riskInsights['scanned24h'] ?? 0 }}</strong></div>
          <div class="stat-card"><span>24h 风险</span><strong>{{ $riskInsights['threat24h'] ?? 0 }}</strong></div>
          <div class="stat-card"><span>风险率</span><strong>{{ $riskInsights['threatRate24h'] ?? 0 }}%</strong></div>
          <div class="stat-card"><span>申诉总量</span><strong>{{ $riskInsights['appealTotal'] ?? 0 }}</strong></div>
        </div>
        <div class="section-card">
          <div class="section-head"><h2>最近 7 天趋势</h2><span class="muted">蓝:上传 / 绿:下载</span></div>
          @php $maxU = max(1, collect($trends)->max('uploads')); $maxD = max(1, collect($trends)->max('downloads')); @endphp
          <div class="trend">
            @foreach ($trends as $t)
              <div class="trend-row">
                <span class="muted">{{ $t['date'] }}</span>
                <div class="bar" title="上传 {{ $t['uploads'] }}"><span style="width:{{ max(6, round($t['uploads'] / $maxU * 100)) }}%"></span></div>
                <div class="bar download" title="下载 {{ $t['downloads'] }}"><span style="width:{{ max(6, round($t['downloads'] / $maxD * 100)) }}%"></span></div>
              </div>
            @endforeach
          </div>
        </div>
        <div class="section-card">
          <h2>账号安全</h2>
          <form method="post" action="{{ route('admin-lite.password.update') }}" class="form-grid">
            @csrf
            <label>当前密码<input name="current_password" type="password" required></label>
            <label>新密码<input name="password" type="password" minlength="8" required></label>
            <label>确认新密码<input name="password_confirmation" type="password" minlength="8" required></label>
            <div><button type="submit">修改密码</button></div>
          </form>
        </div>
      </div>

      {{-- ═══════════ 文件管理 ═══════════ --}}
      <div class="panel {{ $activePanel === 'files' ? 'active' : '' }}" data-panel="files">
        <div class="admin-header"><h1>文件管理</h1><span class="muted">最多 50 条</span></div>
        <div class="section-card">
          <form method="get" class="filter">
            <input type="hidden" name="tab" value="files">
            <label>文件名/分享码<input name="q" value="{{ $filters['q'] }}"></label>
            <label>分享码<input name="code" value="{{ $filters['code'] ?? '' }}" placeholder="精确排查"></label>
            <label>文件名<input name="filename" value="{{ $filters['filename'] ?? '' }}" placeholder="原始文件名"></label>
            <label>上传 IP<input name="ip" value="{{ $filters['ip'] }}"></label>
            <label>状态
              <select name="status"><option value="">全部</option>
                @foreach (['active','expired','deleted','blocked'] as $s)<option value="{{ $s }}" @selected($filters['status']===$s)>{{ $s }}</option>@endforeach
              </select>
            </label>
            <label>过期
              <select name="expires"><option value="">全部</option>
                <option value="active" @selected($filters['expires']==='active')>未过期</option>
                <option value="expired" @selected($filters['expires']==='expired')>已过期</option>
                <option value="today" @selected($filters['expires']==='today')>今天过期</option>
                <option value="week" @selected($filters['expires']==='week')>7天内</option>
              </select>
            </label>
            <label>风险
              <select name="risk"><option value="">全部</option>
                <option value="threat" @selected(($filters['risk'] ?? '')==='threat')>检测到威胁</option>
                <option value="high" @selected(($filters['risk'] ?? '')==='high')>高风险评分</option>
                <option value="pending" @selected(($filters['risk'] ?? '')==='pending')>等待扫描</option>
              </select>
            </label>
            <label>ZIP扫描
              <select name="archive_scan"><option value="">全部</option>
                <option value="partial" @selected(($filters['archive_scan'] ?? '')==='partial')>覆盖率低于100%</option>
                <option value="skipped" @selected(($filters['archive_scan'] ?? '')==='skipped')>存在跳过条目</option>
                <option value="media" @selected(($filters['archive_scan'] ?? '')==='media')>包含内部图片</option>
                <option value="ai_failed" @selected(($filters['archive_scan'] ?? '')==='ai_failed')>图片AI失败/待复核</option>
              </select>
            </label>
            <label>风险类型<input name="threat_type" value="{{ $filters['threat_type'] ?? '' }}" placeholder="色情/暴力/恶意代码"></label>
            <label>上传开始<input name="uploaded_from" type="date" value="{{ $filters['uploaded_from'] ?? '' }}"></label>
            <label>上传结束<input name="uploaded_to" type="date" value="{{ $filters['uploaded_to'] ?? '' }}"></label>
            <label>最小大小 KB<input name="size_min" type="number" min="0" value="{{ $filters['size_min'] ?? '' }}"></label>
            <label>最大大小 KB<input name="size_max" type="number" min="0" value="{{ $filters['size_max'] ?? '' }}"></label>
            <label>最少下载<input name="downloads_min" type="number" min="0" value="{{ $filters['downloads_min'] ?? '' }}"></label>
            <div><button type="submit">筛选</button> <a href="?tab=files" style="margin-left:8px;font-size:13px">清空</a></div>
          </form>
          <form id="bulk-files-form" method="post" action="{{ route('admin-lite.files.bulk') }}" class="filter" onsubmit="return confirm('确认对选中文件执行批量操作？')">
            @csrf
            <label>批量操作
              <select name="action" required>
                @if ($adminPermissions['files.rescan'] ?? false)<option value="rescan">重新扫描</option>@endif
                @if ($adminPermissions['files.block'] ?? false)<option value="block">封禁文件</option>@endif
                @if ($adminPermissions['files.extend'] ?? false)<option value="extend">延长有效期</option>@endif
              </select>
            </label>
            <label>延长天数<input name="days" type="number" value="1" min="1" max="365"></label>
            <input name="confirm_text" type="hidden" value="CONFIRM">
            <div><button type="submit" {{ !($adminPermissions['files.rescan'] ?? false) && !($adminPermissions['files.block'] ?? false) && !($adminPermissions['files.extend'] ?? false) ? 'disabled' : '' }}>执行批量操作</button></div>
          </form>
          <div class="scroll">
            <table>
              <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('[data-file-checkbox]').forEach(function(item){ item.checked = this.checked; }, this)"></th><th>文件</th><th>分享码</th><th>大小</th><th>状态</th><th>风险</th><th>下载</th><th>过期</th><th>操作</th></tr></thead>
              <tbody>
              @forelse ($files as $file)
                <tr>
                  <td><input data-file-checkbox form="bulk-files-form" name="file_ids[]" type="checkbox" value="{{ $file->id }}"></td>
                  <td>{{ Str::limit($file->original_name, 30) }}<br><span class="muted">{{ $file->uploader_ip }}</span></td>
                  <td><a href="/files/{{ $file->code }}">{{ $file->code }}</a></td>
                  <td>{{ number_format($file->size / 1024, 1) }} KB</td>
                  <td><span class="pill">{{ $file->publicStatus() }}</span></td>
                  <td>
                    @php
                      $scanStatusLabel = '等待扫描';
                      $scanStatusClass = 'warn';
                      $details = is_array($file->malware_scan_details) ? $file->malware_scan_details : json_decode((string) $file->malware_scan_details, true);
                      $archiveScan = is_array($details['details']['archive_scan'] ?? null) ? $details['details']['archive_scan'] : null;
                      $archiveCoverage = is_numeric($archiveScan['coverage_percent'] ?? null) ? (float) $archiveScan['coverage_percent'] : null;
                      $archiveSkipped = (int) ($archiveScan['skipped_files'] ?? 0);
                      $archiveFiles = is_array($archiveScan['files'] ?? null) ? $archiveScan['files'] : [];
                      $archiveHasMedia = collect($archiveFiles)->contains(fn ($entry) => is_array($entry) && (($entry['entry_type'] ?? '') === 'media'));
                      $archiveHasAiFailure = str_contains(json_encode($archiveScan ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'media_review_failed') || str_contains(json_encode($archiveScan ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'media_review_required');
                      if ($file->malware_scan_checked_at && $file->malware_scan_passed) {
                        $scanStatusLabel = '扫描通过';
                        $scanStatusClass = 'ok';
                      } elseif ($file->malware_scan_checked_at && ! $file->malware_scan_passed) {
                        $reason = (string) (($details['reason'] ?? '') ?: ($details['error'] ?? ''));
                        $scanStatusLabel = str_contains($reason, 'ffmpeg') || str_contains($reason, '抽帧') ? '视频抽帧失败' : '违规拦截';
                        $scanStatusClass = 'danger';
                      }
                    @endphp
                    <span class="pill {{ $scanStatusClass }}">{{ $scanStatusLabel }}</span><br>
                    <span class="pill" style="background:{{ !$file->malware_scan_passed && $file->malware_scan_checked_at ? '#fee2e2' : '#ecfdf5' }};color:{{ !$file->malware_scan_passed && $file->malware_scan_checked_at ? '#991b1b' : '#166534' }};">
                      {{ $file->risk_score ?? 0 }}
                    </span>
                    @if(!$file->malware_scan_passed && $file->malware_scan_checked_at)
                      <br><a href="{{ route('files.threat-details', ['code' => $file->code]) }}" style="font-size:12px;color:#dc2626;">威胁详情</a>
                    @endif
                    @if($archiveScan)
                      <br><span class="pill {{ $archiveCoverage !== null && $archiveCoverage >= 100 && $archiveSkipped === 0 ? 'ok' : 'warn' }}">ZIP {{ $archiveCoverage ?? 0 }}%</span>
                      @if($archiveHasMedia)<span class="pill">media</span>@endif
                      @if($archiveSkipped > 0)<span class="pill warn">跳过 {{ $archiveSkipped }}</span>@endif
                      @if($archiveHasAiFailure)<span class="pill danger">AI失败</span>@endif
                      @if(($archiveCoverage !== null && $archiveCoverage < 100) || $archiveSkipped > 0)
                        <br><span class="muted">建议重新扫描</span>
                      @endif
                    @endif
                  </td>
                  <td>{{ $file->download_count }}</td>
                  <td>{{ $file->expires_at?->format('m-d H:i') ?? '-' }}</td>
                  <td>
                    @if ($adminPermissions['files.extend'] ?? false)<form class="inline" method="post" action="{{ route('admin-lite.files.extend', $file) }}">@csrf<input name="days" type="number" value="1" min="1" style="width:50px"><button type="submit">延长</button></form>@endif
                    @if ($adminPermissions['files.rescan'] ?? false)<form class="inline" method="post" action="{{ route('admin-lite.files.rescan', $file) }}" onsubmit="return confirm('确认重新扫描？扫描期间文件会临时处于风险拦截状态。')">@csrf<input name="confirm_text" type="hidden" value="CONFIRM"><button type="submit">重扫</button></form>@endif
                    @if ($adminPermissions['files.block'] ?? false)<form class="inline" method="post" action="{{ route('admin-lite.files.block', $file) }}" onsubmit="return confirm('确认封禁该文件？用户将无法下载或预览。')">@csrf<input name="confirm_text" type="hidden" value="CONFIRM"><button type="submit">封禁</button></form>@endif
                    @if ($adminPermissions['files.delete'] ?? false)<form class="inline" method="post" action="{{ route('admin-lite.files.delete', $file) }}" onsubmit="return confirm('确认删除该文件？此操作会进入后台删除队列。')">
                      @csrf
                      @method('DELETE')
                      <input name="confirm_text" type="hidden" value="CONFIRM">
                      <button class="danger" type="submit">删除</button>
                    </form>@endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="9" class="muted">暂无文件</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
          @if(method_exists($files, 'links'))
            <div style="margin-top:14px;">{{ $files->links() }}</div>
          @endif
        </div>
      </div>

      {{-- ═══════════ 系统配置 ═══════════ --}}
      <div class="panel {{ $activePanel === 'settings' ? 'active' : '' }}" data-panel="settings">
        <div class="admin-header"><h1>系统配置</h1></div>
        <form method="post" action="{{ route('admin-lite.settings.update') }}">
          @csrf
          <div class="section-card">
            <h2>上传设置</h2>
            <div class="form-grid">
              <label>最大文件大小 (byte)<input name="max_file_size" type="number" value="{{ $settings['upload']['maxFileSize'] }}"></label>
              <label>默认过期天数<input name="default_expire_days" type="number" value="{{ $settings['upload']['defaultExpireDays'] }}"></label>
              <label>最大过期天数<input name="max_expire_days" type="number" value="{{ $settings['upload']['maxExpireDays'] }}"></label>
              <label>允许的扩展名<textarea name="allowed_file_types" rows="2">{{ $settings['upload']['allowedFileTypes'] }}</textarea></label>
              <label>风控拦截分 (1-100)<input name="risk_block_score" type="number" min="1" max="100" value="{{ $settings['risk']['blockScore'] }}"></label>
              <label>误报下载策略<select name="risk_false_positive_policy"><option value="require_ack" @selected(($riskFalsePositivePolicy ?? 'require_ack') === 'require_ack')>仍需风险确认</option><option value="allow_direct" @selected(($riskFalsePositivePolicy ?? 'require_ack') === 'allow_direct')>允许直接下载</option></select></label>
            </div>
          </div>
          <div class="section-card">
            <h2>分片上传</h2>
            <div class="form-grid">
              <label><span>启用分片上传</span><input name="chunked_upload_enabled" type="checkbox" value="1" @checked($settings['chunkedUpload']['enabled'])></label>
              <label>分片大小 (byte)<input name="chunked_upload_max_chunk_size" type="number" value="{{ $settings['chunkedUpload']['maxChunkSize'] }}"></label>
              <label>最大分片数<input name="chunked_upload_max_chunks" type="number" value="{{ $settings['chunkedUpload']['maxChunks'] }}"></label>
              <label>会话有效期 (分钟)<input name="chunked_upload_ttl_minutes" type="number" value="{{ $settings['chunkedUpload']['sessionTtlMinutes'] }}"></label>
            </div>
          </div>
          <div class="section-card">
            <h2>局域网互传</h2>
            <div class="form-grid">
              <label><span>启用互传</span><input name="lan_enabled" type="checkbox" value="1" @checked($settings['lanTransfer']['enabled'])></label>
              <label>单文件大小 (byte)<input name="lan_max_file_size" type="number" value="{{ $settings['lanTransfer']['maxFileSize'] }}"></label>
              <label>文件数量<input name="lan_max_file_count" type="number" value="{{ $settings['lanTransfer']['maxFileCount'] }}"></label>
              <label>总大小 (byte)<input name="lan_max_total_size" type="number" value="{{ $settings['lanTransfer']['maxTotalSize'] }}"></label>
              <label>会话过期 (分钟)<input name="lan_expire_minutes" type="number" value="{{ $settings['lanTransfer']['expireMinutes'] }}"></label>
              <label><span>文本互传</span><input name="lan_text_enabled" type="checkbox" value="1" @checked($settings['lanTransfer']['textEnabled'])></label>
              <label>文本最大长度<input name="lan_text_max_length" type="number" value="{{ $settings['lanTransfer']['textMaxLength'] }}"></label>
              <label>文本最大行数<input name="lan_text_max_lines" type="number" value="{{ $settings['lanTransfer']['textMaxLines'] }}"></label>
              <label>文本保留 (分钟)<input name="lan_text_retention_minutes" type="number" value="{{ $settings['lanTransfer']['textRetentionMinutes'] }}"></label>
            </div>
          </div>
          <div class="section-card">
            <h2>安全与验证</h2>
            <div class="form-grid">
              <label><span>病毒扫描</span><input name="virus_scan_enabled" type="checkbox" value="1" @checked($settings['virusScan']['enabled'])></label>
              <label>ClamAV 路径<input name="virus_scan_clamav_path" value="{{ $settings['virusScan']['clamavPath'] }}"></label>
              <label>扫描超时 (秒)<input name="virus_scan_timeout_seconds" type="number" value="{{ $settings['virusScan']['timeoutSeconds'] }}"></label>
              <label><span>验证码</span><input name="geetest_enabled" type="checkbox" value="1" @checked($settings['geetest']['enabled'])></label>
              <label>GeeTest ID<input name="geetest_captcha_id" value="{{ $settings['geetest']['captchaId'] }}"></label>
            </div>
          </div>
          <div class="section-card">
            <h2>运维告警</h2>
            <div class="form-grid">
              <label><span>启用自检告警</span><input name="ops_alert_enabled" type="checkbox" value="1" @checked($opsAlert['enabled'] ?? false)></label>
              <label class="full">Webhook URL<input name="ops_alert_webhook_url" value="{{ $opsAlert['webhookUrl'] ?? '' }}" placeholder="https://example.com/webhook"></label>
              <label>最小推送间隔 (分钟)<input name="ops_alert_min_interval_minutes" type="number" min="5" max="1440" value="{{ $opsAlert['minIntervalMinutes'] ?? 60 }}"></label>
              <label>最近推送<input value="{{ ($opsAlert['enabled'] ?? false) ? ($opsAlert['lastAlertedAt'] ?? '未记录') : '告警未启用' }}" readonly></label>
              <label>最近响应<input value="{{ $opsAlert['lastStatusCode'] ?? '-' }}" readonly></label>
              <label class="full">最近错误<input value="{{ $opsAlert['lastError'] ?? '' }}" readonly></label>
              <label>最近恢复<input value="{{ ($opsAlert['enabled'] ?? false) ? ($opsAlert['lastRecoveredAt'] ?? '未记录') : '告警未启用' }}" readonly></label>
              <label>恢复响应<input value="{{ $opsAlert['lastRecoveryStatusCode'] ?? '-' }}" readonly></label>
              <label class="full">恢复错误<input value="{{ $opsAlert['lastRecoveryError'] ?? '' }}" readonly></label>
              <label>最近测试<input value="{{ $opsAlert['lastTestedAt'] ?? '未记录' }}" readonly></label>
              <label>测试响应<input value="{{ $opsAlert['lastTestStatusCode'] ?? '-' }}" readonly></label>
              <label class="full">测试错误<input value="{{ $opsAlert['lastTestError'] ?? '' }}" readonly></label>
            </div>
            <p class="muted">配置 Webhook URL 并勾选启用后，自检异常会发送告警，恢复正常时会发送恢复通知。Webhook 仅支持 https 地址，同一类异常会按最小推送间隔限流。</p>
            @if ($adminPermissions['settings.update'] ?? false)
              <form method="post" action="{{ route('admin-lite.ops-alert.test') }}" style="margin-top:10px">
                @csrf
                <button type="submit">发送测试告警</button>
              </form>
            @endif
          </div>
          <div class="section-card">
            <h2>App 下载页</h2>
            <div class="form-grid">
              <label><span>启用 App 页</span><input name="app_enabled" type="checkbox" value="1" @checked($settings['appDownload']['enabled'])></label>
              <label>标题<input name="app_title" value="{{ $settings['appDownload']['title'] }}"></label>
              <label>副标题<input name="app_subtitle" value="{{ $settings['appDownload']['subtitle'] }}"></label>
              <label class="full">描述<textarea name="app_description" rows="2">{{ $settings['appDownload']['description'] }}</textarea></label>
              <label class="full">功能列表 (每行一个)<textarea name="app_features" rows="3">{{ implode("\n", $settings['appDownload']['features'] ?? []) }}</textarea></label>
              <label><span>Android 下载</span><input name="app_android_enabled" type="checkbox" value="1" @checked($settings['appDownload']['androidEnabled'])></label>
              <label>Android URL<input name="app_android_download_url" value="{{ $settings['appDownload']['androidDownloadUrl'] }}"></label>
              <label>Android 版本<input name="app_android_version" value="{{ $settings['appDownload']['androidVersion'] }}"></label>
              <label><span>iOS 下载</span><input name="app_ios_enabled" type="checkbox" value="1" @checked($settings['appDownload']['iosEnabled'])></label>
              <label>iOS URL<input name="app_ios_download_url" value="{{ $settings['appDownload']['iosDownloadUrl'] }}"></label>
              <label>iOS 版本<input name="app_ios_version" value="{{ $settings['appDownload']['iosVersion'] }}"></label>
              <label><span>显示二维码</span><input name="app_qrcode_enabled" type="checkbox" value="1" @checked($settings['appDownload']['qrcodeEnabled'])></label>
            </div>
          </div>
          <div class="section-card">
            <h2>页脚备案</h2>
            <div class="form-grid">
              <label>ICP 备案号<input name="footer_icp_beian" value="{{ $settings['footer']['icpBeian'] }}"></label>
              <label>公安备案号<input name="footer_gongan_beian" value="{{ $settings['footer']['gonganBeian'] }}"></label>
              <label>公安备案 code<input name="footer_gongan_code" value="{{ $settings['footer']['gonganCode'] }}"></label>
              <label class="full">页脚链接 (名称|地址|1/0|排序)<textarea name="footer_links" rows="3">@foreach ($settings['footer']['links'] ?? [] as $link){{ $link['text'] ?? '' }}|{{ $link['href'] ?? '' }}|{{ ($link['enabled'] ?? true) ? '1' : '0' }}|{{ $link['sort'] ?? 0 }}
@endforeach</textarea></label>
            </div>
          </div>
          <div><button type="submit">保存全部配置</button></div>
        </form>
      </div>



      {{-- 存储配置 --}}
      <div class="panel {{ $activePanel === 'storage' ? 'active' : '' }}" data-panel="storage">
        <div class="admin-header"><h1>存储配置</h1></div>
        
        {{-- 默认存储选择 --}}
        <div class="section-card">
          <h2>默认存储方式</h2>
          <form method="post" action="{{ route('admin-lite.settings.update') }}">
            @csrf
            <div class="form-grid">
              <label>默认存储方式
                <select name="default_storage" id="default_storage_select">
                  <option value="local" {{ $settings['storage']['defaultDisk'] === 'local' ? 'selected' : '' }}>本地存储</option>
                  <option value="oss" {{ $settings['storage']['defaultDisk'] === 'oss' ? 'selected' : '' }}>阿里云OSS</option>
                  <option value="tencent" {{ $settings['storage']['defaultDisk'] === 'tencent' ? 'selected' : '' }}>腾讯云COS</option>
                  <option value="s3" {{ $settings['storage']['defaultDisk'] === 's3' ? 'selected' : '' }}>AWS S3</option>
                </select>
              </label>
              <label>切换确认词<input name="confirm_text" placeholder="切换存储时输入 CONFIRM"></label>
            </div>
            <p class="muted">切换到云存储前，请先保存并启用对应云存储配置。123 网盘作为外部转存能力配置。</p>
            <div><button type="submit">保存默认存储设置</button></div>
          </form>
        </div>

        {{-- 阿里云OSS配置 --}}
        <div class="section-card">
          <h2>阿里云OSS配置</h2>
          <form method="post" action="{{ route('admin-lite.settings.update') }}">
            @csrf
            <div class="form-grid">
              <label><span>启用OSS</span><input name="oss_enabled" type="checkbox" value="1" @checked($settings['storage']['ossEnabled'])></label>
              <label>Access Key ID<input name="oss_access_key_id" type="password" value="" placeholder="留空保留当前值"></label>
              <label>Access Key Secret<input name="oss_access_key_secret" type="password" value="" placeholder="留空保留当前值"></label>
              <label>Bucket<input name="oss_bucket" value="{{ $settings['storage']['ossBucket'] }}"></label>
              <label>Endpoint<input name="oss_endpoint" value="{{ $settings['storage']['ossEndpoint'] }}"></label>
              <label>CDN域名<input name="oss_url" value="{{ $settings['storage']['ossUrl'] }}"></label>
            </div>
            <div><button type="submit">保存OSS配置</button></div>
          </form>
        </div>

        {{-- 腾讯云COS配置 --}}
        <div class="section-card">
          <h2>腾讯云COS配置</h2>
          <form method="post" action="{{ route('admin-lite.settings.update') }}">
            @csrf
            <div class="form-grid">
              <label><span>启用COS</span><input name="tencent_enabled" type="checkbox" value="1" @checked($settings['storage']['tencentEnabled'])></label>
              <label>Secret ID<input name="tencent_secret_id" type="password" value="" placeholder="留空保留当前值"></label>
              <label>Secret Key<input name="tencent_secret_key" type="password" value="" placeholder="留空保留当前值"></label>
              <label>Region<input name="tencent_region" value="{{ $settings['storage']['tencentRegion'] }}"></label>
              <label>Bucket<input name="tencent_bucket" value="{{ $settings['storage']['tencentBucket'] }}"></label>
              <label>Endpoint<input name="tencent_endpoint" value="{{ $settings['storage']['tencentEndpoint'] }}"></label>
              <label>CDN域名<input name="tencent_url" value="{{ $settings['storage']['tencentUrl'] }}"></label>
            </div>
            <div><button type="submit">保存COS配置</button></div>
          </form>
        </div>

        {{-- AWS S3配置 --}}
        <div class="section-card">
          <h2>AWS S3配置</h2>
          <form method="post" action="{{ route('admin-lite.settings.update') }}">
            @csrf
            <div class="form-grid">
              <label><span>启用S3</span><input name="s3_enabled" type="checkbox" value="1" @checked($settings['storage']['s3Enabled'])></label>
              <label>Access Key ID<input name="s3_access_key_id" type="password" value="" placeholder="留空保留当前值"></label>
              <label>Secret Access Key<input name="s3_secret_access_key" type="password" value="" placeholder="留空保留当前值"></label>
              <label>Region<input name="s3_region" value="{{ $settings['storage']['s3Region'] }}"></label>
              <label>Bucket<input name="s3_bucket" value="{{ $settings['storage']['s3Bucket'] }}"></label>
              <label>Endpoint<input name="s3_endpoint" value="{{ $settings['storage']['s3Endpoint'] }}"></label>
              <label>URL / CDN域名<input name="s3_url" value="{{ $settings['storage']['s3Url'] ?? '' }}"></label>
            </div>
            <div><button type="submit">保存S3配置</button></div>
          </form>
        </div>

        {{-- 123网盘配置 --}}
        <div class="section-card">
          <h2>123网盘配置</h2>
          <form method="post" action="{{ route('admin-lite.settings.update') }}">
            @csrf
            <div class="form-grid">
              <label><span>启用123网盘</span><input name="netdisk123_enabled" type="checkbox" value="1" @checked($netdisk123['enabled'])></label>
              <label>用户名<input name="netdisk123_username" value="{{ $netdisk123['username'] }}"></label>
              <label>访问令牌<input name="netdisk123_token" type="password" value="" placeholder="留空保留当前值"></label>
              <label class="full">Cookie<textarea name="netdisk123_cookie" rows="3" placeholder="留空保留当前值"></textarea></label>
              <label>最大文件大小<input name="netdisk123_max_file_size" type="number" value="{{ $netdisk123['maxFileSize'] }}"></label>
              <label><span>自动创建分享链接</span><input name="netdisk123_auto_share" type="checkbox" value="1" @checked($netdisk123['autoShare'])></label>
              <label>分享链接过期天数<input name="netdisk123_share_expire_days" type="number" min="1" max="30" value="{{ $netdisk123['shareExpireDays'] }}"></label>
            </div>
            <div><button type="submit">保存123网盘配置</button></div>
          </form>
        </div>
      </div>
      {{-- ═══════════ 管理员 ═══════════ --}}
      <div class="panel {{ $activePanel === 'users' ? 'active' : '' }}" data-panel="users">
        <div class="admin-header"><h1>管理员</h1><span class="muted">owner 可管理账号</span></div>
        @if ($canManageAdmins)
          <div class="section-card">
            <h2>新增管理员</h2>
            <form method="post" action="{{ route('admin-lite.users.store') }}" class="form-grid">
              @csrf
              <label>名称<input name="name" required></label>
              <label>邮箱<input name="email" type="email" required></label>
              <label>密码<input name="password" type="password" minlength="8" required></label>
              <label>角色<select name="role"><option value="admin">admin</option><option value="owner">owner</option><option value="viewer">viewer</option></select></label>
              <label class="full">权限 (每行一个)<textarea name="permissions" rows="2" placeholder="admins.manage"></textarea></label>
              <div><button type="submit">新增</button></div>
            </form>
          </div>
        @endif
        <div class="section-card">
          <div class="scroll">
            <table>
              <thead><tr><th>账号</th><th>角色</th><th>权限</th><th>状态</th><th>最近登录</th><th>操作</th></tr></thead>
              <tbody>
              @forelse ($adminUsers as $u)
                <tr>
                  <td><strong>{{ $u->name }}</strong><br><span class="muted">{{ $u->email }}</span></td>
                  <td>{{ $u->role ?? 'admin' }}</td>
                  <td class="muted">{{ implode(', ', $u->permissions_json ?? []) }}</td>
                  <td><span class="pill">{{ $u->status }}</span></td>
                  <td>{{ $u->last_login_at?->format('m-d H:i') ?? '-' }}</td>
                  <td>
                    @if ($canManageAdmins)
                      <a class="admin-action" href="{{ route('admin-lite.users.edit', $u) }}">编辑</a>
                      <form class="inline" method="post" action="{{ route('admin-lite.users.delete', $u) }}">@csrf@method('delete')<button class="danger" type="submit">停用</button></form>
                    @else
                      <span class="muted">只读</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="muted">暂无管理员</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- ═══════════ 内容管理 ═══════════ --}}
      <div class="panel {{ $activePanel === 'content' ? 'active' : '' }}" data-panel="content">
        <div class="admin-header"><h1>内容管理</h1></div>

        <div class="section-card">
          <div class="editor-tabs">
            <button class="editor-tab active" data-editor="terms-content" type="button">用户协议</button>
            <button class="editor-tab" data-editor="privacy-content" type="button">隐私政策</button>
            <button class="editor-tab" data-editor="announcements" type="button">公告管理</button>
          </div>

          {{-- 用户协议 --}}
          <div class="editor-panel active" data-editor-panel="terms-content">
            <form method="post" action="{{ route('admin-lite.content.update') }}" class="content-editor">
              @csrf
              <input type="hidden" name="content_type" value="terms">
              <p class="muted" style="margin-bottom:12px">编辑前台「用户协议」页面的内容，支持 HTML 格式。</p>
              <div class="form-grid">
                <label class="full">协议内容<textarea name="content_body" rows="16">{{ $termsContent }}</textarea></label>
              </div>
              <div style="margin-top:12px"><button type="submit">保存协议</button></div>
            </form>
          </div>

          {{-- 隐私政策 --}}
          <div class="editor-panel" data-editor-panel="privacy-content">
            <form method="post" action="{{ route('admin-lite.content.update') }}" class="content-editor">
              @csrf
              <input type="hidden" name="content_type" value="privacy">
              <p class="muted" style="margin-bottom:12px">编辑前台「隐私政策」页面的内容，支持 HTML 格式。</p>
              <div class="form-grid">
                <label class="full">隐私政策内容<textarea name="content_body" rows="16">{{ $privacyContent }}</textarea></label>
              </div>
              <div style="margin-top:12px"><button type="submit">保存隐私政策</button></div>
            </form>
          </div>

          {{-- 公告管理 --}}
          <div class="editor-panel" data-editor-panel="announcements">
            <h3>新增公告</h3>
            <form method="post" action="{{ route('admin-lite.announcements.store') }}" class="form-grid" style="margin-bottom:16px">
              @csrf
              <label>标题<input name="title" required></label>
              <label>类型<input name="type" value="warning" required></label>
              <label>优先级<input name="priority" type="number" value="0" required></label>
              <label><span>启用</span><input name="is_active" type="checkbox" value="1" checked></label>
              <label>开始<input name="start_at" type="datetime-local"></label>
              <label>结束<input name="end_at" type="datetime-local"></label>
              <label class="full">内容<textarea name="content" rows="3" required></textarea></label>
              <div><button type="submit">新增公告</button></div>
            </form>
            <div class="scroll">
              <table>
                <thead><tr><th>公告</th><th>状态</th><th>操作</th></tr></thead>
                <tbody>
                @forelse ($announcements as $a)
                  <tr>
                    <td>
                      <form id="ann-{{ $a->id }}" method="post" action="{{ route('admin-lite.announcements.update', $a) }}" style="display:none">@csrf@method('put')<input name="title" value="{{ $a->title }}"><input name="type" value="{{ $a->type }}"><input name="priority" type="number" value="{{ $a->priority }}"><textarea name="content" rows="2">{{ $a->content }}</textarea><input name="start_at" type="datetime-local" value="{{ $a->start_at?->format('Y-m-d\TH:i') }}"><input name="end_at" type="datetime-local" value="{{ $a->end_at?->format('Y-m-d\TH:i') }}"><label><span>启用</span><input name="is_active" type="checkbox" value="1" @checked($a->is_active)></label></form>
                      <strong>{{ $a->title }}</strong><br><span class="muted">{{ $a->type }} / 优先级 {{ $a->priority }}</span>
                    </td>
                    <td>{{ $a->is_active ? '启用' : '停用' }}</td>
                    <td>
                      <button type="submit" form="ann-{{ $a->id }}">保存</button>
                      <form class="inline" method="post" action="{{ route('admin-lite.announcements.delete', $a) }}">@csrf@method('delete')<button class="danger" type="submit">删除</button></form>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="muted">暂无公告</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>



      {{-- AI扫描配置 --}}
      <div class="panel {{ $activePanel === 'ai' ? 'active' : '' }}" data-panel="ai">
        <div class="admin-header"><h1>AI扫描配置</h1></div>
        <form method="POST" action="{{ route('admin-lite.update-ai-settings') }}" id="aiSettingsForm">
          @csrf
          <div class="section-card">
            <h2>基础设置</h2>
            <div class="form-grid">
              <label class="full">
                <span>启用AI扫描</span>
                <input name="ai_scan_enabled" type="checkbox" value="1" @checked($ai_scan_enabled)>
              </label>
            </div>
          </div>
          <div class="section-card">
            <h2>API配置</h2>
            <div class="form-grid">
              <label class="full">
                <span>API地址</span>
                <input name="ai_scan_api_url" type="text" id="ai_scan_api_url" value="{{ $ai_scan_api_url }}" placeholder="https://api.openai.com/v1/chat/completions">
              </label>
              <label class="full">
                <span>API密钥</span>
                <input name="ai_scan_api_key" type="password" id="ai_scan_api_key" value="" placeholder="留空保持当前密钥，输入新密钥则覆盖">
              </label>
              <label class="full">
                <span>模型名称</span>
                <input name="ai_scan_model" type="text" id="ai_scan_model" value="{{ $ai_scan_model }}" placeholder="gpt-4">
              </label>
            </div>
          </div>
          <div class="section-card">
            <h2>高级设置</h2>
            <div class="form-grid">
              <label>
                <span>超时时间(秒)</span>
                <input name="ai_scan_timeout" type="number" id="ai_scan_timeout" value="{{ $ai_scan_timeout }}" min="5" max="120">
              </label>
              <label>
                <span>最大文件大小(KB)</span>
                <input name="ai_scan_max_file_size" type="number" value="{{ $ai_scan_max_file_size }}" min="1" max="1048576">
              </label>
              <label>
                <span>失败重试次数</span>
                <input name="ai_scan_retry_count" type="number" value="{{ $ai_scan_retry_count }}" min="1" max="5">
              </label>
              <label>
                <span>ZIP最多扫描文件数</span>
                <input name="archive_max_scan_files" type="number" value="{{ $archive_max_scan_files }}" min="1" max="100">
              </label>
              <label class="full">
                <span>ZIP可疑后缀</span>
                <textarea name="archive_scan_extensions" rows="3">{{ $archive_scan_extensions }}</textarea>
              </label>
              <label>
                <span>扫描ZIP内部图片</span>
                <input name="archive_media_scan_enabled" type="checkbox" value="1" @checked($archive_media_scan_enabled)>
              </label>
              <label class="full">
                <span>ZIP图片扫描后缀</span>
                <textarea name="archive_media_extensions" rows="2">{{ $archive_media_extensions }}</textarea>
              </label>
              <label>
                <span>ZIP内部单图上限(KB)</span>
                <input name="archive_media_max_file_size" type="number" value="{{ $archive_media_max_file_size }}" min="1" max="8192">
              </label>
              <label>
                <span>ZIP图片AI失败策略</span>
                <select name="archive_media_failure_policy">
                  <option value="block" @selected($archive_media_failure_policy === 'block')>临时拦截</option>
                  <option value="review" @selected($archive_media_failure_policy === 'review')>进入复核风险</option>
                  <option value="allow" @selected($archive_media_failure_policy === 'allow')>放行并记录</option>
                </select>
              </label>
            </div>
          </div>
          <div class="button-group">
            <button type="button" id="testConnectionBtn" onclick="testAiConnection()">测试连接</button>
            <button type="submit">保存AI配置</button>
          </div>
          <div id="aiTestResult" class="ai-test-result"></div>
        </form>
      </div>

      {{-- ═══════════ 安全 ═══════════ --}}
      <div class="panel {{ $activePanel === 'security' ? 'active' : '' }}" data-panel="security">
        @php
          $opsStatus = $operationsHealth['opsCheck']['status'] ?? 'unknown';
          $queueFresh = $operationsHealth['queueHeartbeat']['fresh'] ?? false;
          $actionableFailures = (int) ($operationsHealth['actionableFailedJobsCount'] ?? 0);
          $riskPending = (int) ($riskReviewStats['pending'] ?? 0);
          $riskOverdue = (int) ($riskReviewStats['overdue'] ?? 0);
          $backupFresh = $operationsHealth['backup']['fresh_24h'] ?? false;
          $attentionCount = ($opsStatus === 'ok' ? 0 : 1) + ($queueFresh ? 0 : 1) + ($actionableFailures > 0 ? 1 : 0) + ($riskOverdue > 0 ? 1 : 0) + ($backupFresh ? 0 : 1);
        @endphp
        <div class="admin-header">
          <div>
            <div class="admin-kicker">Security Operations</div>
            <h1>安全运维工作台</h1>
            <div class="admin-subtitle">队列、扫描、风险复核、备份和审计状态集中查看。</div>
          </div>
        </div>
        <div class="section-card">
          <div class="ops-hero">
            <div class="ops-primary">
              <span class="pill {{ $opsStatus === 'ok' ? 'ok' : 'warn' }}">{{ $opsStatus === 'ok' ? '自检正常' : '需要关注' }}</span>
              <h2>{{ $attentionCount === 0 ? '系统运行稳定' : $attentionCount.' 个运维信号待确认' }}</h2>
              <p>自动自检时间 {{ $operationsHealth['opsCheck']['checkedAt'] ?? '未记录' }}。队列心跳、风险复核 SLA、备份新鲜度和失败任务会在这里汇总，便于值守时快速定位优先级。</p>
              <div class="meta">
                <span class="pill {{ $queueFresh ? 'ok' : 'warn' }}">队列 {{ $queueFresh ? '在线' : '待确认' }}</span>
                <span class="pill {{ $riskOverdue > 0 ? 'danger' : 'ok' }}">SLA 超时 {{ $riskOverdue }}</span>
                <span class="pill {{ $backupFresh ? 'ok' : 'warn' }}">备份 {{ $backupFresh ? '24 小时内' : '待确认' }}</span>
                <span class="pill">{{ $currentAdminSummary['role'] ?? 'unknown' }}</span>
              </div>
            </div>
            <div class="ops-status-card">
              <span class="muted">当前管理员</span>
              <strong style="font-size:18px">{{ $currentAdminSummary['email'] ?? '-' }}</strong>
              <p class="muted" style="margin:0 0 14px">权限角色 {{ $currentAdminSummary['role'] ?? 'unknown' }}</p>
              <div class="ops-mini">
                <div class="label">最近运维操作</div>
                <div class="value" style="font-size:14px">{{ $operationsHealth['lastProcessedAt'] ?? '暂无' }}</div>
                <div class="desc">用于判断后台任务和人工操作是否持续活跃。</div>
              </div>
            </div>
          </div>
          <div class="ops-grid">
            <div class="ops-mini">
              <div class="label">待处理队列</div>
              <div class="value">{{ $operationsHealth['jobsCount'] ?? '未知' }}</div>
              <div class="desc">心跳 {{ $operationsHealth['queueHeartbeat']['at'] ?? '未记录' }}</div>
            </div>
            <div class="ops-mini">
              <div class="label">失败任务</div>
              <div class="value">{{ $actionableFailures }}</div>
              <div class="desc">总失败 {{ $operationsHealth['failedJobsCount'] ?? '未知' }}，只展示可处理任务入口。</div>
            </div>
            <div class="ops-mini">
              <div class="label">24 小时 AI 失败</div>
              <div class="value">{{ $operationsHealth['aiFailureCount24h'] ?? 0 }}</div>
              <div class="desc">包含扫描失败、跳过和模型调用异常。</div>
            </div>
            <div class="ops-mini">
              <div class="label">风险复核</div>
              <div class="value">{{ $riskPending }}</div>
              <div class="desc">总风险 {{ $riskReviewStats['total'] ?? 0 }}，确认 {{ $riskReviewStats['confirmed'] ?? 0 }}，误报 {{ $riskReviewStats['false_positive'] ?? 0 }}。</div>
            </div>
            <div class="ops-mini">
              <div class="label">已重扫</div>
              <div class="value">{{ $riskReviewStats['rescanned'] ?? 0 }}</div>
              <div class="desc">等待扫描 {{ $riskReviewStats['scan_pending'] ?? 0 }}，超时复核 {{ $riskOverdue }}。</div>
            </div>
            <div class="ops-mini">
              <div class="label">备份自检</div>
              <div class="value" style="font-size:15px">{{ $operationsHealth['backup']['latest_at'] ?? '未发现备份' }}</div>
              <div class="desc">{{ $operationsHealth['backup']['latest_path'] ?? '扫描默认备份目录。' }}</div>
            </div>
          </div>
        </div>
        <div class="two-col">
          <div class="section-card">
            <div class="section-head"><h2>运行检查</h2><span class="muted">自动自检和维护任务</span></div>
            @if ($adminPermissions['maintenance.run'] ?? false)
              <form method="post" action="{{ route('admin-lite.maintenance.ops-check') }}" style="margin-bottom:12px" onsubmit="return confirm('确认立即运行一次运维自检？')">
                @csrf
                <button type="submit">立即运行自检</button>
              </form>
            @endif
            <div class="ops-check-list">
              @foreach (($operationsHealth['storageWritable'] ?? []) as $name => $ready)
                <div class="ops-check-item">
                  <div><strong>{{ $name }}</strong><p>本地运行目录权限检查</p></div>
                  <span class="pill {{ $ready ? 'ok' : 'danger' }}">{{ $ready ? '可写' : '异常' }}</span>
                </div>
              @endforeach
              <div class="ops-check-item">
                <div><strong>自动自检</strong><p>{{ $operationsHealth['opsCheck']['checkedAt'] ?? '未记录' }} @if (!empty($operationsHealth['opsCheck']['issues'])) · {{ implode(', ', $operationsHealth['opsCheck']['issues']) }} @endif</p></div>
                <span class="pill {{ $opsStatus === 'ok' ? 'ok' : 'warn' }}">{{ $opsStatus === 'ok' ? '正常' : '需关注' }}</span>
              </div>
              <div class="ops-check-item">
                <div><strong>告警推送</strong><p>@if($operationsHealth['opsAlert']['enabled'] ?? false) 最近推送 {{ $operationsHealth['opsAlert']['lastAlertedAt'] ?? '未记录' }}，最近恢复 {{ $operationsHealth['opsAlert']['lastRecoveredAt'] ?? '未记录' }} @else 配置 Webhook 并启用后开始记录推送状态 @endif</p></div>
                <span class="pill {{ ($operationsHealth['opsAlert']['enabled'] ?? false) ? 'ok' : 'warn' }}">{{ ($operationsHealth['opsAlert']['enabled'] ?? false) ? '启用' : '未启用' }}</span>
              </div>
              <div class="ops-check-item">
                <div><strong>队列心跳</strong><p>{{ $operationsHealth['queueHeartbeat']['at'] ?? '未记录' }}</p></div>
                <span class="pill {{ $queueFresh ? 'ok' : 'warn' }}">{{ $queueFresh ? '正常' : '待确认' }}</span>
              </div>
              <div class="ops-check-item">
                <div><strong>LAN 清理</strong><p>{{ $operationsHealth['maintenance']['lanCleanupLastSummary'] ?? '-' }}</p></div>
                <span class="pill">{{ $operationsHealth['maintenance']['lanCleanupLastAt'] ?? '未记录' }}</span>
              </div>
              <div class="ops-check-item">
                <div><strong>日志清理</strong><p>{{ $operationsHealth['maintenance']['logPruneLastSummary'] ?? '-' }}</p></div>
                <span class="pill">{{ $operationsHealth['maintenance']['logPruneLastAt'] ?? '未记录' }}</span>
              </div>
            </div>
          </div>
          <div class="section-card">
            <div class="section-head"><h2>权限视图</h2><span class="muted">当前管理员</span></div>
            <div class="scroll">
              <table>
                <thead><tr><th>权限点</th><th>状态</th></tr></thead>
                <tbody>
                  @foreach ($adminPermissionLabels as $permission => $label)
                    <tr><td>{{ $label }}<br><span class="muted">{{ $permission }}</span></td><td><span class="pill {{ ($adminPermissions[$permission] ?? false) ? 'ok' : 'warn' }}">{{ ($adminPermissions[$permission] ?? false) ? '允许' : '只读' }}</span></td></tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="section-card">
          <div class="section-head"><h2>自检历史</h2><span class="muted">最近记录</span></div>
          <div class="scroll">
            <table class="history-table">
              <thead><tr><th>自检时间</th><th>状态</th><th>队列</th><th>扫描</th><th>风险复核</th><th>问题</th></tr></thead>
              <tbody>
                @forelse ($opsCheckHistory as $item)
                  <tr>
                    <td>{{ $item['checked_at'] ?? '-' }}</td>
                    <td><span class="pill {{ ($item['status'] ?? '') === 'ok' ? 'ok' : 'warn' }}">{{ $item['status'] ?? '-' }}</span></td>
                    <td>{{ $item['pending_jobs'] ?? 0 }} / 失败 {{ $item['actionable_failed_jobs'] ?? 0 }}</td>
                    <td>待扫 {{ $item['malware_scan_pending'] ?? 0 }} / AI {{ $item['ai_failures_24h'] ?? 0 }}</td>
                    <td>待复核 {{ $item['risk_review_pending'] ?? 0 }} / 超时 {{ $item['risk_review_overdue'] ?? 0 }}</td>
                    <td class="muted history-issues">{{ implode(', ', $item['issues'] ?? []) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="muted">暂无自检历史</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="section-card">
          <h2>IP 封禁</h2>
          <form method="post" action="{{ route('admin-lite.blocked-ips.store') }}" class="form-grid" style="margin-bottom:16px">
            @csrf
            <label>IP<input name="ip" required></label>
            <label>范围<select name="scope"><option value="all">全部</option><option value="upload">上传</option><option value="download">下载</option></select></label>
            <label>过期<input name="expires_at" type="datetime-local"></label>
            <label>原因<input name="reason"></label>
            <div><button type="submit">新增封禁</button></div>
          </form>
          <div class="scroll">
            <table>
              <thead><tr><th>IP</th><th>范围</th><th>原因</th><th>过期</th><th>操作</th></tr></thead>
              <tbody>
              @forelse ($blockedIps as $b)
                <tr>
                  <td>{{ $b->ip }}</td><td>{{ $b->scope }}</td><td>{{ $b->reason ?? '-' }}</td>
                  <td>{{ $b->expires_at?->format('m-d H:i') ?? '永久' }}</td>
                  <td>
                    <form method="post" action="{{ route('admin-lite.blocked-ips.unblock', $b) }}" onsubmit="return confirm('确认解除该 IP 封禁？')">@csrf<input name="confirm_text" placeholder="CONFIRM" style="width:80px"><button class="danger" type="submit">解除</button></form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="muted">暂无封禁</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="two-col">
          <div class="section-card">
            <h2>风险文件</h2>
            @php($nextPendingRisk = collect($recentRiskFiles)->first(fn ($item) => (($riskReviewStates[$item->id]['status'] ?? 'pending') === 'pending')))
            @if ($nextPendingRisk)
              <div class="file-callout warn" style="margin-bottom:12px">
                <strong>审核队列</strong><br>
                下一条待复核：{{ $nextPendingRisk->code }} · {{ Str::limit($nextPendingRisk->original_name, 32) }}
                @if ($adminPermissions['files.review'] ?? false)
                  <br><a href="{{ route('admin-lite.files.source', ['file' => $nextPendingRisk->id, 'mode' => 'preview']) }}" target="_blank" rel="noopener">打开源文件</a>
                  · <a href="{{ route('files.threat-details', ['code' => $nextPendingRisk->code]) }}">查看详情</a>
                @endif
              </div>
            @endif
            @if (!empty($riskInsights['topThreatTypes']))
              <div class="file-callout warn" style="margin-bottom:12px">
                <strong>风险类型分布</strong><br>
                @foreach ($riskInsights['topThreatTypes'] as $type => $count)
                  <span class="pill" style="margin:3px">{{ $type }} {{ $count }}</span>
                @endforeach
              </div>
            @endif
            @if (!empty($riskInsights['topUploaderIps']) || !empty($riskInsights['topExtensions']) || !empty($riskInsights['duplicateHashes']))
              <div class="file-callout warn" style="margin-bottom:12px">
                <strong>风险聚类</strong><br>
                @foreach (($riskInsights['topUploaderIps'] ?? []) as $ip => $count)
                  <a class="pill warn" style="margin:3px;text-decoration:none" href="{{ route('admin-lite.dashboard', ['tab' => 'security', 'risk_ip' => $ip]) }}">IP {{ $ip }} · {{ $count }}</a>
                @endforeach
                @foreach (($riskInsights['topExtensions'] ?? []) as $extension => $count)
                  <a class="pill" style="margin:3px;text-decoration:none" href="{{ route('admin-lite.dashboard', ['tab' => 'security', 'risk_ext' => $extension]) }}">.{{ $extension }} · {{ $count }}</a>
                @endforeach
                @foreach (($riskInsights['duplicateHashes'] ?? []) as $hash => $count)
                  <a class="pill danger" style="margin:3px;text-decoration:none" href="{{ route('admin-lite.dashboard', ['tab' => 'security', 'risk_hash' => $hash]) }}">同哈希 {{ Str::limit($hash, 10, '') }} · {{ $count }}</a>
                @endforeach
              </div>
            @endif
            <form method="get" class="filter" style="margin-bottom:12px">
              <input type="hidden" name="tab" value="security">
              <label>复核状态<select name="review"><option value="">全部</option><option value="pending" @selected(($filters['review'] ?? '') === 'pending')>待复核</option><option value="confirmed" @selected(($filters['review'] ?? '') === 'confirmed')>确认风险</option><option value="false_positive" @selected(($filters['review'] ?? '') === 'false_positive')>误报</option><option value="rescanned" @selected(($filters['review'] ?? '') === 'rescanned')>已重扫</option></select></label>
              <label>风险类型<input name="threat_type" value="{{ $filters['threat_type'] ?? '' }}" placeholder="按同类风险筛选"></label>
              <label>风险 IP<input name="risk_ip" value="{{ $filters['risk_ip'] ?? '' }}" placeholder="按上传 IP 筛选"></label>
              <label>扩展名<input name="risk_ext" value="{{ $filters['risk_ext'] ?? '' }}" placeholder="如 jpg"></label>
              <label>SHA-256<input name="risk_hash" value="{{ $filters['risk_hash'] ?? '' }}" placeholder="同哈希文件"></label>
              <label>媒体类型<select name="risk_media"><option value="">全部</option><option value="image" @selected(($filters['risk_media'] ?? '') === 'image')>只看图片</option><option value="video" @selected(($filters['risk_media'] ?? '') === 'video')>只看视频</option></select></label>
              <label>最低风险分<input name="risk_min_score" value="{{ $filters['risk_min_score'] ?? '' }}" placeholder="如 80"></label>
              <label>ZIP扫描<select name="archive_scan"><option value="">全部</option><option value="partial" @selected(($filters['archive_scan'] ?? '') === 'partial')>覆盖率低于100%</option><option value="skipped" @selected(($filters['archive_scan'] ?? '') === 'skipped')>存在跳过条目</option><option value="media" @selected(($filters['archive_scan'] ?? '') === 'media')>包含内部图片</option><option value="ai_failed" @selected(($filters['archive_scan'] ?? '') === 'ai_failed')>图片AI失败/待复核</option></select></label>
              <div><button type="submit">筛选</button></div>
            </form>
            @if (($filters['risk_ip'] ?? '') !== '' || ($filters['risk_ext'] ?? '') !== '' || ($filters['risk_hash'] ?? ''))
              <div class="file-callout warn" style="margin-bottom:12px">
                当前正在查看风险聚类结果，可勾选列表文件后使用下方批量复核。<button type="button" class="secondary" onclick="document.querySelectorAll('[data-risk-checkbox]').forEach(function(item){ item.checked = true; })">勾选当前聚类</button>
              </div>
              <div class="file-callout" style="margin-bottom:12px">
                <strong>聚类预览</strong>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-top:10px;">
                  @foreach (collect($recentRiskFiles)->take(12) as $previewFile)
                    @php($previewMime = strtolower((string) ($previewFile->mime_type ?? '')))
                    <a href="#risk-file-{{ $previewFile->id }}" style="text-decoration:none;color:inherit;border:1px solid var(--fe-border);border-radius:12px;padding:8px;background:rgba(255,255,255,.48);">
                      @if (($adminPermissions['files.review'] ?? false) && str_starts_with($previewMime, 'image/'))
                        <img src="{{ route('admin-lite.files.source', ['file' => $previewFile->id, 'mode' => 'preview']) }}" alt="聚类预览" style="width:100%;height:82px;object-fit:cover;border-radius:8px;background:#f3f4f6;">
                      @elseif (($adminPermissions['files.review'] ?? false) && str_starts_with($previewMime, 'video/'))
                        <video src="{{ route('admin-lite.files.source', ['file' => $previewFile->id, 'mode' => 'preview']) }}" muted preload="metadata" style="width:100%;height:82px;object-fit:cover;border-radius:8px;background:#111;"></video>
                      @else
                        <div style="height:82px;border-radius:8px;background:var(--fe-control-bg);display:grid;place-items:center;">{{ strtoupper($previewFile->extension ?: 'FILE') }}</div>
                      @endif
                      <div style="font-size:12px;margin-top:6px;overflow-wrap:anywhere;">{{ $previewFile->code }}</div>
                    </a>
                  @endforeach
                </div>
              </div>
            @endif
            @if ($adminPermissions['files.review'] ?? false)
              <form id="risk-bulk-review-form" method="post" action="{{ route('admin-lite.files.risk-review.bulk') }}" class="filter" style="margin-bottom:12px" onsubmit="return confirm('确认批量更新选中风险文件复核状态？')">
                @csrf
                <label>批量复核
                  <select name="review_status" required>
                    <option value="confirmed">确认风险</option>
                    <option value="false_positive">标记误报</option>
                    <option value="pending">保持待复核</option>
                    <option value="rescanned">重扫等待</option>
                  </select>
                </label>
                <label>复核备注<input name="review_note" maxlength="500" placeholder="同类风险批量备注"></label>
                <div><button type="submit">批量保存复核</button></div>
              </form>
              <div class="file-callout warn" style="margin-bottom:12px">
                批量标记误报只更新复核结论；强禁止违规媒体仍受公开侧下载/预览拦截保护，后台需逐项确认后再处理申诉。
              </div>
              <div class="shortcut-hint muted">
                <span>快捷键：</span><kbd>J</kbd><span>下一条</span><kbd>K</kbd><span>上一条</span><kbd>1</kbd><span>确认风险</span><kbd>2</kbd><span>误报通过</span><kbd>3</kbd><span>重扫等待</span>
              </div>
              <div class="file-callout safe" style="margin-bottom:12px">
                审核进度：{{ collect($recentRiskFiles)->filter(fn ($item) => (($riskReviewStates[$item->id]['status'] ?? 'pending') !== 'pending'))->count() }} / {{ collect($recentRiskFiles)->count() }}
                @if ($nextPendingRisk)
                  · <a href="#risk-file-{{ $nextPendingRisk->id }}">处理下一条</a>
                @endif
              </div>
            @endif
            <div class="scroll">
              <table>
                <thead><tr>@if ($adminPermissions['files.review'] ?? false)<th><input type="checkbox" onclick="document.querySelectorAll('[data-risk-checkbox]').forEach(function(item){ item.checked = this.checked; }, this)"></th>@endif<th>时间</th><th>文件</th><th>风险分</th><th>复核</th><th>操作</th></tr></thead>
                <tbody>
                @forelse ($recentRiskFiles as $riskFile)
                  <tr id="risk-file-{{ $riskFile->id }}" data-risk-row>
                    @if ($adminPermissions['files.review'] ?? false)<td><input data-risk-checkbox form="risk-bulk-review-form" name="file_ids[]" type="checkbox" value="{{ $riskFile->id }}"></td>@endif
                    <td>{{ $riskFile->malware_scan_checked_at ? (is_string($riskFile->malware_scan_checked_at) ? $riskFile->malware_scan_checked_at : $riskFile->malware_scan_checked_at->format('m-d H:i')) : '-' }}</td>
                    <td>
                      @php($riskMime = strtolower((string) ($riskFile->mime_type ?? '')))
                      @php($riskDetails = is_array($riskFile->malware_scan_details) ? $riskFile->malware_scan_details : json_decode((string) $riskFile->malware_scan_details, true))
                      @php($riskArchiveScan = is_array($riskDetails['details']['archive_scan'] ?? null) ? $riskDetails['details']['archive_scan'] : null)
                      @php($riskArchiveFiles = is_array($riskArchiveScan['files'] ?? null) ? $riskArchiveScan['files'] : [])
                      @php($riskArchiveCoverage = is_numeric($riskArchiveScan['coverage_percent'] ?? null) ? (float) $riskArchiveScan['coverage_percent'] : null)
                      @php($riskArchiveSkipped = (int) ($riskArchiveScan['skipped_files'] ?? 0))
                      @php($riskArchiveHasMedia = collect($riskArchiveFiles)->contains(fn ($entry) => is_array($entry) && (($entry['entry_type'] ?? '') === 'media')))
                      @php($riskArchiveHasAiFailure = str_contains(json_encode($riskArchiveScan ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'media_review_failed') || str_contains(json_encode($riskArchiveScan ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'media_review_required'))
                      @if (($adminPermissions['files.review'] ?? false) && str_starts_with($riskMime, 'image/'))
                        <a class="review-preview" data-watermark="审核专用 {{ $riskFile->code }}" href="{{ route('admin-lite.files.source', ['file' => $riskFile->id, 'mode' => 'preview']) }}" target="_blank" rel="noopener" style="display:block;margin-bottom:6px;width:max-content;">
                          <img src="{{ route('admin-lite.files.source', ['file' => $riskFile->id, 'mode' => 'preview']) }}" alt="源文件预览" style="width:96px;max-height:72px;object-fit:cover;border-radius:10px;background:#f3f4f6;">
                        </a>
                      @elseif (($adminPermissions['files.review'] ?? false) && str_starts_with($riskMime, 'video/'))
                        <span class="review-preview" data-watermark="审核专用 {{ $riskFile->code }}" style="margin-bottom:6px;">
                          <video src="{{ route('admin-lite.files.source', ['file' => $riskFile->id, 'mode' => 'preview']) }}" controls muted preload="metadata" style="width:120px;max-height:82px;border-radius:10px;background:#111;"></video>
                        </span>
                      @endif
                      <a href="/files/{{ $riskFile->code }}">{{ Str::limit($riskFile->original_name, 24) }}</a><br><span class="muted">{{ $riskFile->code }}</span>
                      @if($riskArchiveScan)
                        <br><span class="pill {{ $riskArchiveCoverage !== null && $riskArchiveCoverage >= 100 && $riskArchiveSkipped === 0 ? 'ok' : 'warn' }}">ZIP {{ $riskArchiveCoverage ?? 0 }}%</span>
                        @if($riskArchiveHasMedia)<span class="pill">media</span>@endif
                        @if($riskArchiveSkipped > 0)<span class="pill warn">跳过 {{ $riskArchiveSkipped }}</span>@endif
                        @if($riskArchiveHasAiFailure)<span class="pill danger">AI失败</span>@endif
                        @if(($riskArchiveCoverage !== null && $riskArchiveCoverage < 100) || $riskArchiveSkipped > 0)
                          <br><span class="muted">旧策略或部分覆盖，建议设为重扫等待</span>
                        @endif
                      @endif
                    </td>
                    <td>{{ $riskFile->risk_score ?? 0 }}</td>
                    <td>
                      @php($review = $riskReviewStates[$riskFile->id] ?? [])
                      @php($appeal = $riskAppeals[$riskFile->id] ?? null)
                      <span class="pill">{{ $review['status'] ?? 'pending' }}</span>
                      @if (($review['status'] ?? 'pending') === 'pending' && $riskFile->malware_scan_checked_at && strtotime((string) $riskFile->malware_scan_checked_at) <= now()->subDay()->timestamp)<br><span class="muted">SLA 超时</span>@endif
                      @if (!empty($review['note']))<br><span class="muted">{{ Str::limit($review['note'], 22) }}</span>@endif
                      @if ($appeal)
                        <br><span class="pill" style="background:#fff7ed;color:#9a3412;">申诉 {{ $appeal['count'] }}</span>
                        @if (!empty($appeal['latest']['reason']))<br><span class="muted">{{ Str::limit($appeal['latest']['reason'], 28) }}</span>@endif
                        @if (!empty($appeal['latest']['contact']))<br><span class="muted">联系：{{ Str::limit($appeal['latest']['contact'], 22) }}</span>@endif
                        @if (!empty($appeal['latest']['status']))<br><span class="muted">申诉状态：{{ $appeal['latest']['status'] }}</span>@endif
                      @endif
                    </td>
                    <td>
                      <a href="{{ route('files.threat-details', ['code' => $riskFile->code]) }}">详情</a>
                      @if ($adminPermissions['files.review'] ?? false)
                        · <a href="{{ route('admin-lite.files.source', ['file' => $riskFile->id, 'mode' => 'preview']) }}" target="_blank" rel="noopener">预览源文件</a>
                        · <a href="{{ route('admin-lite.files.source', ['file' => $riskFile->id, 'mode' => 'download']) }}">下载源文件</a>
                        · <a href="{{ route('admin-lite.files.risk-report', ['file' => $riskFile->id]) }}">报告</a>
                        <form method="post" action="{{ route('admin-lite.files.risk-review', ['file' => $riskFile->id]) }}" style="display:grid;gap:6px;margin-top:8px;min-width:180px" data-risk-review-form data-risk-code="{{ $riskFile->code }}" onsubmit="return confirm('确认更新该风险文件复核状态？')">
                          @csrf
                          <select name="review_status">
                            <option value="pending" @selected(($review['status'] ?? 'pending') === 'pending')>待复核</option>
                            <option value="confirmed" @selected(($review['status'] ?? '') === 'confirmed')>确认风险</option>
                            <option value="false_positive" @selected(($review['status'] ?? '') === 'false_positive')>标记误报</option>
                            <option value="rescanned" @selected(($review['status'] ?? '') === 'rescanned')>已重新扫描</option>
                          </select>
                          <select data-note-template>
                            <option value="">复核模板</option>
                            <option value="已核验源文件，确认命中平台违规内容策略。">确认违规</option>
                            <option value="已核验源文件，扫描结果与实际内容不一致，按误报处理。">误报通过</option>
                            <option value="证据不足，保留待复核状态并等待更多上下文。">证据不足</option>
                            <option value="已重新派发扫描任务，等待新结果。">重扫等待</option>
                          </select>
                          <input name="review_note" maxlength="500" placeholder="复核备注" value="{{ $review['note'] ?? '' }}">
                          <button type="submit">保存复核</button>
                        </form>
                        @if (!empty($appeal['latest']['key']))
                          <form method="post" action="{{ route('admin-lite.appeals.review') }}" style="display:grid;gap:6px;margin-top:8px;min-width:180px" onsubmit="return confirm('确认保存申诉处理结果？')">
                            @csrf
                            <input type="hidden" name="appeal_key" value="{{ $appeal['latest']['key'] }}">
                            <select name="appeal_status"><option value="approved">通过申诉</option><option value="rejected">驳回申诉</option><option value="needs_more_info">需要补充</option></select>
                            <select data-note-template>
                              <option value="">申诉模板</option>
                              <option value="申诉材料有效，按误报通过处理。">通过申诉</option>
                              <option value="源文件内容仍符合违规判定，维持拦截。">驳回申诉</option>
                              <option value="请补充文件来源、用途说明或授权证明。">需要补充</option>
                            </select>
                            <input name="review_note" maxlength="500" placeholder="申诉处理备注">
                            <button type="submit">处理申诉</button>
                          </form>
                        @endif
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="{{ ($adminPermissions['files.review'] ?? false) ? 6 : 5 }}" class="muted">暂无风险文件</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="section-card">
            <h2>批量分享管理</h2>
            <div class="scroll">
              <table>
                <thead><tr><th>标题</th><th>Token</th><th>文件</th><th>下载</th><th>创建 IP</th><th>过期</th><th>状态</th><th>操作</th></tr></thead>
                <tbody>
                @forelse ($batchShares as $share)
                  <tr>
                    <td>{{ $share['title'] !== '' ? Str::limit($share['title'], 28) : '批量文件分享' }}</td>
                    <td><a href="{{ route('files.batch', ['token' => $share['token']]) }}" target="_blank" rel="noopener">{{ $share['token'] }}</a></td>
                    <td>{{ $share['count'] }}</td>
                    <td>{{ $share['downloads'] }}</td>
                    <td>{{ $share['ip'] ?: '-' }}</td>
                    <td>{{ $share['expires_at'] ?: '-' }}</td>
                    <td><span class="pill {{ $share['closed'] ? 'warn' : 'ok' }}">{{ $share['closed'] ? '已关闭' : '可用' }}</span></td>
                    <td>
                      @if (($adminPermissions['files.review'] ?? false) && ! $share['closed'])
                        <form method="post" action="{{ route('admin-lite.batch-shares.close', ['token' => $share['token']]) }}" class="inline" onsubmit="return confirm('确认关闭这个批量分享？')">
                          @csrf
                          <button type="submit" class="danger">关闭</button>
                        </form>
                      @else
                        <span class="muted">-</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="8" class="muted">暂无批量分享</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="section-card">
            <h2>风险下载确认</h2>
            <div class="scroll">
              <table>
                <thead><tr><th>时间</th><th>分享码</th><th>IP</th><th>风险分</th><th>User-Agent</th></tr></thead>
                <tbody>
                @forelse ($riskDownloads as $d)
                  <tr><td>{{ $d->created_at?->format('m-d H:i') }}</td><td>{{ $d->code }}</td><td>{{ $d->ip }}</td><td>{{ $d->risk_score ?? '-' }}</td><td>{{ Str::limit($d->user_agent, 28) }}</td></tr>
                @empty
                  <tr><td colspan="5" class="muted">暂无风险下载确认</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
            @if (!empty($downloadRiskEvents))
              <h3 style="margin-top:16px">下载风控观察</h3>
              <div class="scroll">
                <table>
                  <thead><tr><th>时间</th><th>状态</th><th>分享码</th><th>IP</th><th>原因</th><th>次数</th><th>观察到</th><th>操作</th></tr></thead>
                  <tbody>
                  @foreach ($downloadRiskEvents as $event)
                    <tr>
                      <td>{{ $event['created_at'] ?? '-' }}</td>
                      <td><span class="pill warn">{{ $event['status'] ?? 'observing' }}</span></td>
                      <td>{{ $event['code'] ?? '-' }}</td>
                      <td>{{ $event['ip'] ?? '-' }}</td>
                      <td>{{ $event['reason'] ?? '-' }}</td>
                      <td>{{ $event['count'] ?? '-' }}</td>
                      <td>{{ $event['observed_until'] ?? '-' }}</td>
                      <td>
                        @if (($adminPermissions['files.block'] ?? false) && !empty($event['ip']))
                          <form method="post" action="{{ route('admin-lite.blocked-ips.store') }}" class="inline" onsubmit="return confirm('确认封禁该下载 IP？')">
                            @csrf
                            <input type="hidden" name="ip" value="{{ $event['ip'] }}">
                            <input type="hidden" name="scope" value="download">
                            <input type="hidden" name="reason" value="下载风控观察：{{ $event['reason'] ?? '异常下载' }}，次数 {{ $event['count'] ?? '-' }}">
                            <button type="submit" class="danger">封禁下载</button>
                          </form>
                        @else
                          <span class="muted">仅展示</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          </div>
        </div>
        <div class="section-card">
          <h2>AI 扫描失败告警</h2>
          <div class="scroll">
            <table>
              <thead><tr><th>时间</th><th>文件</th><th>类型</th><th>原因</th><th>模型</th></tr></thead>
              <tbody>
              @forelse ($aiFailureLogs as $log)
                <tr>
                  <td>{{ $log->created_at?->format('m-d H:i') }}</td>
                  <td>{{ Str::limit($log->filename, 28) }}</td>
                  <td>{{ $log->threat_type }}</td>
                  <td>{{ Str::limit($log->reason, 48) }}</td>
                  <td>{{ $log->model ?? '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="muted">暂无 AI 扫描失败或跳过记录</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="two-col">
          <div class="section-card">
            <h2>失败任务</h2>
            <form method="get" class="filter" style="margin-bottom:12px">
              <input type="hidden" name="tab" value="security">
              <label><span>只看可处理</span><input name="failed_actionable" type="checkbox" value="1" @checked(($filters['failed_actionable'] ?? '') === '1')></label>
              <div><button type="submit">筛选失败任务</button></div>
            </form>
            <div class="scroll">
              <table>
                <thead><tr><th>时间</th><th>任务</th><th>文件</th><th>详情</th><th>操作</th></tr></thead>
                <tbody>
                @forelse ($failedJobs as $job)
                  <tr>
                    <td>{{ $job['failed_at'] ?? '-' }}</td>
                    <td>{{ Str::limit($job['name'] ?? '未知任务', 32) }}</td>
                    <td>{{ $job['file'] ? ($job['file']->code.' / '.Str::limit($job['file']->original_name, 18)) : ('#'.($job['file_id'] ?? '-')) }}</td>
                    <td><details><summary>{{ $job['summary'] }}</summary><p class="muted">{{ $job['exception'] }}</p><pre class="muted" style="white-space:pre-wrap;max-width:520px">{{ $job['exception_details'] ?? '' }}</pre><pre class="muted" style="white-space:pre-wrap;max-width:520px">{{ $job['payload_excerpt'] ?? '' }}</pre></details></td>
                    <td>
                      @if (!empty($job['actionable']))
                        @if ($adminPermissions['files.rescan'] ?? false)<form method="post" action="{{ route('admin-lite.failed-jobs.retry-scan', ['failedJobId' => $job['id']]) }}" onsubmit="return confirm('确认重新派发该扫描任务？扫描完成前文件会保持风险拦截。')">
                          @csrf
                          <input name="confirm_text" type="hidden" value="CONFIRM">
                          <button type="submit">重派发</button>
                        </form>@else<span class="muted">仅展示</span>@endif
                      @else
                        <span class="muted">仅展示</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="muted">暂无失败任务</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="section-card">
            <h2>可疑下载行为</h2>
            <div class="scroll">
              <table>
                <thead><tr><th>IP</th><th>24 小时下载</th><th>风险确认</th><th>最近时间</th><th>操作</th></tr></thead>
                <tbody>
                @forelse ($suspiciousDownloads as $row)
                  <tr><td>{{ $row['ip'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['risk_acks'] }}</td><td>{{ $row['last_at'] }}</td><td>@if ($adminPermissions['files.block'] ?? false)<form method="post" action="{{ route('admin-lite.blocked-ips.store') }}">@csrf<input type="hidden" name="ip" value="{{ $row['ip'] }}"><input type="hidden" name="scope" value="download"><input type="hidden" name="reason" value="后台可疑下载一键封禁"><button type="submit">封禁下载</button></form>@endif</td></tr>
                @empty
                  <tr><td colspan="5" class="muted">暂无明显可疑下载行为</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="two-col">
          <div class="section-card">
            <h2>下载风控事件</h2>
            <div class="scroll"><table><thead><tr><th>时间</th><th>IP</th><th>分享码</th><th>原因</th><th>次数</th></tr></thead><tbody>
              @forelse ($downloadRiskEvents as $event)
                <tr><td>{{ $event['created_at'] ?? '-' }}</td><td>{{ $event['ip'] ?? '-' }}</td><td>{{ $event['code'] ?? '-' }}</td><td>{{ $event['reason'] ?? '-' }}</td><td>{{ $event['count'] ?? '-' }}</td></tr>
              @empty
                <tr><td colspan="5" class="muted">暂无下载风控事件</td></tr>
              @endforelse
            </tbody></table></div>
          </div>
          <div class="section-card">
            <h2>用户通知</h2>
            <div class="scroll"><table><thead><tr><th>时间</th><th>分享码</th><th>类型</th><th>内容</th></tr></thead><tbody>
              @forelse ($userNotices as $notice)
                <tr><td>{{ $notice['created_at'] ?? '-' }}</td><td>{{ $notice['code'] ?? '-' }}</td><td>{{ $notice['type'] ?? '-' }}</td><td>{{ Str::limit(($notice['message'] ?? '').' '.($notice['note'] ?? ''), 60) }}</td></tr>
              @empty
                <tr><td colspan="4" class="muted">暂无用户通知</td></tr>
              @endforelse
            </tbody></table></div>
          </div>
        </div>
        <div class="section-card">
          <h2>运维清理</h2>
          <div class="form-grid">
            @if ($adminPermissions['maintenance.run'] ?? false)<form method="post" action="{{ route('admin-lite.maintenance.cleanup-lan') }}" onsubmit="return confirm('确认清理过期 LAN 会话？')">
              @csrf
              <button type="submit">清理过期 LAN 会话</button>
            </form>@endif
            @if ($adminPermissions['maintenance.run'] ?? false)<form method="post" action="{{ route('admin-lite.maintenance.prune-logs') }}" onsubmit="return confirm('确认清理旧运行日志？')">
              @csrf
              <label>日志保留天数<input name="days" type="number" min="7" max="365" value="30"></label>
              <button type="submit">清理运行日志</button>
            </form>@endif
          </div>
        </div>
        <div class="two-col">
          <div class="section-card">
            <h2>审计日志</h2>
            <form method="get" class="filter" style="margin-bottom:12px">
              <input type="hidden" name="tab" value="security">
              <label>动作<input name="audit_action" value="{{ $filters['audit_action'] ?? '' }}" placeholder="file.rescan"></label>
              <label>目标<input name="audit_target" value="{{ $filters['audit_target'] ?? '' }}" placeholder="文件 ID 或类型"></label>
              <label>IP<input name="audit_ip" value="{{ $filters['audit_ip'] ?? '' }}" placeholder="127.0.0.1"></label>
              <div><button type="submit">筛选审计</button> <a href="?tab=security" style="margin-left:8px;font-size:13px">清空</a></div>
            </form>
            @if ($adminPermissions['maintenance.run'] ?? false)
              <form method="get" action="{{ route('admin-lite.audit.export') }}" class="filter" style="margin-bottom:12px">
                <label>开始<input name="from" type="date"></label>
                <label>结束<input name="to" type="date"></label>
                <label>动作<input name="action" placeholder="settings.update"></label>
                <label>目标<input name="target" placeholder="文件 ID 或类型"></label>
                <label>IP<input name="ip" placeholder="127.0.0.1"></label>
                <div><button type="submit">导出 CSV</button></div>
              </form>
            @endif
            <div class="scroll">
              <table>
                <thead><tr><th>时间</th><th>动作</th><th>目标</th><th>IP</th><th>详情</th></tr></thead>
                <tbody>
                @forelse ($auditLogs as $log)
                  <tr><td>{{ $log->created_at?->format('m-d H:i') }}</td><td>{{ $log->action }}</td><td class="muted">{{ $log->target_type }} #{{ $log->target_id }}</td><td>{{ $log->ip }}</td><td><details><summary>查看</summary><pre style="white-space:pre-wrap;max-width:420px">{{ json_encode($log->metadata_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre></details></td></tr>
                @empty
                  <tr><td colspan="5" class="muted">暂无日志</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="section-card">
            <h2>错误日志</h2>
            <div class="scroll">
              <table>
                <thead><tr><th>时间</th><th>动作</th><th>目标</th><th>IP</th></tr></thead>
                <tbody>
                @forelse ($errorLogs as $log)
                  <tr><td>{{ $log->created_at?->format('m-d H:i') }}</td><td>{{ $log->action }}</td><td class="muted">{{ $log->target_type }} #{{ $log->target_id }}</td><td>{{ $log->ip }}</td></tr>
                @empty
                  <tr><td colspan="4" class="muted">暂无错误</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="two-col">
          <div class="section-card">
            <h2>管理员登录审计</h2>
            <div class="scroll">
              <table>
                <thead><tr><th>时间</th><th>动作</th><th>IP</th><th>User-Agent</th></tr></thead>
                <tbody>
                @forelse ($recentLoginAuditLogs as $log)
                  <tr><td>{{ $log->created_at?->format('m-d H:i') }}</td><td>{{ $log->action }}</td><td>{{ $log->ip }}</td><td>{{ Str::limit($log->user_agent, 36) }}</td></tr>
                @empty
                  <tr><td colspan="4" class="muted">暂无登录审计</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="section-card">
            <h2>配置变更审计</h2>
            <div class="scroll">
              <table>
                <thead><tr><th>时间</th><th>动作</th><th>IP</th><th>摘要</th></tr></thead>
                <tbody>
                @forelse ($configAuditLogs as $log)
                  <tr><td>{{ $log->created_at?->format('m-d H:i') }}</td><td>{{ $log->action }}</td><td>{{ $log->ip }}</td><td class="muted">{{ Str::limit(json_encode($log->metadata_json, JSON_UNESCAPED_UNICODE), 52) }}</td></tr>
                @empty
                  <tr><td colspan="4" class="muted">暂无配置变更</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      {{-- ═══════════ 日志 ═══════════ --}}
      <div class="panel {{ $activePanel === 'logs' ? 'active' : '' }}" data-panel="logs">
        <div class="admin-header"><h1>日志</h1></div>
        <div class="two-col">
          <div class="section-card">
            <h2>上传日志</h2>
            <div class="scroll">
              <table>
                <thead><tr><th>时间</th><th>文件</th><th>IP</th><th>结果</th></tr></thead>
                <tbody>
                @forelse ($uploads as $u)
                  <tr><td>{{ $u->created_at?->format('m-d H:i') }}</td><td>{{ Str::limit($u->original_name, 25) }}</td><td>{{ $u->ip }}</td><td>{{ $u->success ? '成功' : $u->failure_reason }}</td></tr>
                @empty
                  <tr><td colspan="4" class="muted">暂无记录</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="section-card">
            <h2>下载日志</h2>
            <div class="scroll">
              <table>
                <thead><tr><th>时间</th><th>分享码</th><th>IP</th><th>结果</th></tr></thead>
                <tbody>
                @forelse ($downloads as $d)
                  <tr><td>{{ $d->created_at?->format('m-d H:i') }}</td><td>{{ $d->code }}</td><td>{{ $d->ip }}</td><td>{{ $d->success ? '成功' : $d->failure_reason }}</td></tr>
                @empty
                  <tr><td colspan="4" class="muted">暂无记录</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="section-card">
          <h2>健康检查</h2>
          <div class="scroll">
            <table>
              <thead><tr><th>时间</th><th>状态</th><th>存储</th><th>说明</th></tr></thead>
              <tbody>
              @forelse ($healthChecks as $h)
                <tr><td>{{ $h->checked_at?->format('m-d H:i') }}</td><td>{{ $h->status }}</td><td>{{ $h->storage_status }}</td><td>{{ $h->error_message ?? '-' }}</td></tr>
              @empty
                <tr><td colspan="4" class="muted">暂无记录</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    // 侧边栏导航切换
    document.querySelectorAll('.sidebar-item[data-nav]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var target = this.dataset.nav;
        // 更新导航高亮
        document.querySelectorAll('.sidebar-item[data-nav]').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        // 切换面板
        document.querySelectorAll('.panel[data-panel]').forEach(function(p) { p.classList.remove('active'); });
        var panel = document.querySelector('.panel[data-panel="' + target + '"]');
        if (panel) panel.classList.add('active');
        // 更新 URL
        var url = new URL(window.location);
        url.searchParams.set('tab', target);
        window.history.replaceState({}, '', url);
        // 移动端关闭侧边栏
        document.getElementById('sidebar').classList.remove('open');
      });
    });

    // 内容管理编辑器标签切换
    document.querySelectorAll('.editor-tab').forEach(function(tab) {
      tab.addEventListener('click', function() {
        var target = this.dataset.editor;
        this.closest('.section-card').querySelectorAll('.editor-tab').forEach(function(t) { t.classList.remove('active'); });
        this.classList.add('active');
        this.closest('.section-card').querySelectorAll('.editor-panel').forEach(function(p) { p.classList.remove('active'); });
        var panel = this.closest('.section-card').querySelector('.editor-panel[data-editor-panel="' + target + '"]');
        if (panel) panel.classList.add('active');
      });
    });

    // AI连接测试功能
    function testAiConnection() {
      var apiUrl = document.getElementById('ai_scan_api_url').value.trim();
      var apiKey = document.getElementById('ai_scan_api_key').value.trim();
      var model = document.getElementById('ai_scan_model').value.trim();
      var timeout = document.getElementById('ai_scan_timeout').value;
      
      if (!apiUrl || !apiKey || !model) {
        showAiTestResult('error', '请先填写API地址、密钥和模型名称');
        return;
      }

      var testBtn = document.getElementById('testConnectionBtn');
      testBtn.disabled = true;
      testBtn.textContent = '测试中...';
      showAiTestResult('loading', '正在测试AI连接，请稍候...');

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
      .then(function(response) { return response.json(); })
      .then(function(data) {
        testBtn.disabled = false;
        testBtn.textContent = '测试连接';
        
        if (data.success) {
          showAiTestResult('success', '✅ ' + data.message, data.data);
        } else {
          showAiTestResult('error', '❌ ' + data.message);
        }
      })
      .catch(function(error) {
        testBtn.disabled = false;
        testBtn.textContent = '测试连接';
        showAiTestResult('error', '❌ 请求失败: ' + error.message);
      });
    }

    function showAiTestResult(type, message, data) {
      var resultDiv = document.getElementById('aiTestResult');
      resultDiv.className = 'ai-test-result ' + type;
      resultDiv.style.display = 'block';
      
      // 处理多行错误消息
      var formattedMessage = message.replace(/\\n/g, '<br>');
      
      var html = '<div class="ai-test-message">' + formattedMessage + '</div>';
      
      if (data) {
        html += '<div class="ai-test-details">';
        html += '<div>提供商: ' + data.provider + '</div>';
        html += '<div>模型: ' + data.model + '</div>';
        html += '<div>响应: ' + data.response + '</div>';
        if (data.tokens_used) {
          html += '<div>消耗Token: ' + data.tokens_used + '</div>';
        }
        if (data.response_time) {
          html += '<div>响应时间: ' + data.response_time + '秒</div>';
        }
        html += '</div>';
      }
      
      resultDiv.innerHTML = html;
    }
    document.querySelectorAll('[data-note-template]').forEach(function(select) {
      select.addEventListener('change', function() {
        if (!this.value) return;
        var form = this.closest('form');
        var input = form ? form.querySelector('input[name="review_note"]') : null;
        if (input && !input.value) input.value = this.value;
      });
    });
    var riskReviewForms = Array.prototype.slice.call(document.querySelectorAll('[data-risk-review-form]'));
    var activeRiskIndex = 0;
    function focusRiskReview(index) {
      if (!riskReviewForms.length) return;
      activeRiskIndex = Math.max(0, Math.min(index, riskReviewForms.length - 1));
      var row = riskReviewForms[activeRiskIndex].closest('[data-risk-row]');
      if (row) row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    function submitRiskReview(status, note) {
      if (!riskReviewForms.length) return;
      var form = riskReviewForms[activeRiskIndex];
      var select = form.querySelector('select[name="review_status"]');
      var input = form.querySelector('input[name="review_note"]');
      if (select) select.value = status;
      if (input && !input.value) input.value = note;
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return;
      }
      var button = form.querySelector('button[type="submit"]');
      if (button) button.click();
    }
    document.addEventListener('keydown', function(event) {
      var tag = (event.target && event.target.tagName || '').toLowerCase();
      if (['input', 'textarea', 'select', 'button'].indexOf(tag) !== -1 || event.metaKey || event.ctrlKey || event.altKey) return;
      if (event.key === 'j' || event.key === 'J') { event.preventDefault(); focusRiskReview(activeRiskIndex + 1); }
      if (event.key === 'k' || event.key === 'K') { event.preventDefault(); focusRiskReview(activeRiskIndex - 1); }
      if (event.key === '1') { event.preventDefault(); submitRiskReview('confirmed', '快捷键复核：已核验源文件，确认命中平台违规内容策略。'); }
      if (event.key === '2') { event.preventDefault(); submitRiskReview('false_positive', '快捷键复核：已核验源文件，按误报处理。'); }
      if (event.key === '3') { event.preventDefault(); submitRiskReview('rescanned', '快捷键复核：已重新派发扫描任务，等待新结果。'); }
    });
  </script>
</body>
</html>
