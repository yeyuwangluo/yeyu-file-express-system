@php
  $riskExpires = now()->addMinutes(10)->timestamp;
  $checkedAt = $file->malware_scan_checked_at ? strtotime((string) $file->malware_scan_checked_at) : 0;
  $riskPayload = implode('|', [$code, $file->id, $checkedAt, $riskExpires]);
  $riskSignature = hash_hmac('sha256', $riskPayload, (string) config('app.key'));
  $formatSize = function (int $size): string {
      $units = ['B', 'KB', 'MB', 'GB'];
      $value = max(0, $size);
      $index = 0;
      while ($value >= 1024 && $index < count($units) - 1) {
          $value /= 1024;
          $index++;
      }
      return number_format($value, ($value >= 10 || $index === 0) ? 0 : 1).' '.$units[$index];
  };
  $confirmedDownloadUrl = url('/api/v1/files/' . $code . '/download') . '?' . http_build_query([
      'risk_ack' => '1',
      'risk_expires' => $riskExpires,
      'risk_signature' => $riskSignature,
  ]);
  $threatTypes = [];
  $maliciousEntries = [];
  $seenEntryNames = [];
  $aiReasonsByFile = [];
  foreach (($aiScanLogs ?? []) as $log) {
      $logFilename = trim((string) ($log->filename ?? ''));
      $logReason = trim((string) ($log->reason ?? ''));
      if ($logFilename !== '' && $logReason !== '') {
          $aiReasonsByFile[$logFilename] = [
              'reason' => $logReason,
              'confidence' => $log->confidence ?? null,
              'model' => $log->model ?? null,
              'scanner' => $log->scanner ?? null,
          ];
      }
  }
  $primaryScanDetail = [];
  if (is_array($malwareScanDetails['details'][0] ?? null)) {
      $primaryScanDetail = $malwareScanDetails['details'][0];
  }
  $fallbackReason = function (array $entry): string {
      $name = (string) ($entry['name'] ?? '该文件');
      $threats = array_values(array_filter(array_map('strval', $entry['threats'] ?? [])));
      if ($threats === []) {
          return 'AI 扫描结果将该文件判定为风险内容，系统已按高风险文件处理。';
      }

      $plainThreats = [];
      foreach ($threats as $threat) {
          $plainThreats[] = trim(preg_replace('/\s*\(在文件：.*?\)$/u', '', $threat));
      }
      $plainThreats = array_values(array_unique(array_filter($plainThreats)));

      return $name.' 命中 '.implode('、', $plainThreats).' 等高风险特征，系统已按高风险内容处理。';
  };
  if (is_array($malwareScanDetails ?? null)) {
      $threatTypes = array_values(array_unique(array_filter(array_merge(
          $malwareScanDetails['threats'] ?? [],
          $malwareScanDetails['threat_types'] ?? []
      ))));

      foreach (($malwareScanDetails['files'] ?? []) as $name) {
          if (!is_string($name) || trim($name) === '') {
              continue;
          }
          if (isset($seenEntryNames[$name])) {
              continue;
          }
          $seenEntryNames[$name] = true;
          $matchedThreats = [];
          foreach ($threatTypes as $threat) {
              if (str_contains((string) $threat, $name)) {
                  $matchedThreats[] = $threat;
              }
          }
          $maliciousEntries[] = [
              'name' => $name,
              'threats' => $matchedThreats ?: $threatTypes,
              'reason' => $aiReasonsByFile[$name]['reason'] ?? ($primaryScanDetail['reason'] ?? null),
              'confidence' => $aiReasonsByFile[$name]['confidence'] ?? ($primaryScanDetail['confidence'] ?? null),
              'model' => $aiReasonsByFile[$name]['model'] ?? ($primaryScanDetail['model'] ?? null),
              'scanner' => $aiReasonsByFile[$name]['scanner'] ?? ($primaryScanDetail['scanner'] ?? null),
              'size' => null,
          ];
      }

      foreach (($malwareScanDetails['details']['archive_scan']['files'] ?? []) as $archiveFile) {
          if (($archiveFile['is_malicious'] ?? false) !== true) {
              continue;
          }
          $name = (string) ($archiveFile['name'] ?? '');
          if ($name !== '' && isset($seenEntryNames[$name])) {
              continue;
          }
          if ($name !== '') {
              $seenEntryNames[$name] = true;
          }
          if ($name !== '' && empty($archiveFile['reason']) && isset($aiReasonsByFile[$name]['reason'])) {
              $archiveFile['reason'] = $aiReasonsByFile[$name]['reason'];
              $archiveFile['confidence'] = $archiveFile['confidence'] ?? $aiReasonsByFile[$name]['confidence'];
              $archiveFile['model'] = $archiveFile['model'] ?? $aiReasonsByFile[$name]['model'];
              $archiveFile['scanner'] = $archiveFile['scanner'] ?? $aiReasonsByFile[$name]['scanner'];
          }
          $maliciousEntries[] = $archiveFile;
      }
  }
  $hardBlockedThreats = [
      'adult_content',
      'sexual_content',
      'minor_sexual_content',
      'graphic_violence',
      'extreme_violence',
      'terrorism',
      'extremism',
      'drug_trade',
      'illegal_activity',
      'hate',
      'harassment',
  ];
  $mimeType = strtolower((string) ($file->mime_type ?? ''));
  $isHardBlocked = in_array(true, array_map(fn ($threat) => in_array((string) $threat, $hardBlockedThreats, true), $threatTypes), true)
      || str_starts_with($mimeType, 'image/')
      || str_starts_with($mimeType, 'video/');
  $scanIndicatesRisk = (bool) ($malwareScanDetails['is_malicious'] ?? false)
      || $file->malware_scan_passed === false
      || !empty($threatTypes);
  $displayRiskScore = max((int) ($file->risk_score ?? 0), $scanIndicatesRisk ? 95 : 0);
  $displayRiskColor = $displayRiskScore >= 80 ? '#dc2626' : '#92400e';
@endphp

<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>威胁详情 - {{ $file->original_name ?? '未知文件' }} - 叶宇文件快递</title>
  <link rel="icon" href="/qrlogo.png?v=2">
  <link rel="stylesheet" href="/fonts/misans/font.css">
  <link rel="stylesheet" href="/build/assets/app-DWxc1te1.css">
  <link rel="stylesheet" href="/_next/static/css/1c8d3152a8988e8c.css">
  <link rel="stylesheet" href="/replica-enhance.css">
  <link rel="stylesheet" href="/css/file-share.css?v=20260606-scrollable">
  <style>
    .threat-actions button,
    .threat-actions a,
    .appeal-form button {
      min-height: 48px;
      touch-action: manipulation;
    }
    .appeal-form input,
    .appeal-form textarea {
      font-size: 16px;
    }
    @media (max-width: 640px) {
      .fe-file-page { padding-bottom: max(18px, env(safe-area-inset-bottom)); }
      .fe-file-page .panel { width: min(100% - 18px, 760px); }
      .fe-file-page h1 { font-size: clamp(26px, 8vw, 34px); word-break: break-word; }
      .share-code { grid-template-columns: 1fr; text-align: center; }
      .details { grid-template-columns: 1fr; }
      .malware-alert { border-radius: 16px !important; padding: 14px !important; }
      .threat-actions { display: grid; grid-template-columns: 1fr; gap: 10px; }
      .threat-actions button,
      .threat-actions a,
      .appeal-form button { width: 100%; justify-content: center; }
      .appeal-form button { justify-self: stretch !important; }
      #danger-download-modal { padding: 12px !important; align-items: flex-end !important; }
      #danger-download-modal > div { max-height: calc(100vh - 24px) !important; border-radius: 18px 18px 0 0 !important; padding: 18px !important; }
      #danger-download-modal [style*="justify-content:flex-end"] { display: grid !important; grid-template-columns: 1fr; }
      #danger-download-cancel,
      #danger-download-confirm { width: 100%; min-height: 48px; }
    }
  </style>
</head>
<body class="fe-page fe-file-page">
  <main class="panel">
    <div class="brand">
      <img src="/qrlogo.png" alt="叶宇文件快递">
      <div><strong>叶宇文件快递</strong><span>本地系统站点</span></div>
    </div>

    <span class="badge warning">安全警告</span>
    <h1>{{ $file->original_name ?? '未知文件' }}</h1>
    <p class="muted">此文件已被检测到包含违规内容或安全风险。</p>

    <div class="share-code" aria-label="分享码">
      <span>分享码</span>
      <strong>{{ $code }}</strong>
    </div>

    <dl class="details">
      <div><dt>文件名</dt><dd>{{ $file->original_name ?? '未知文件' }}</dd></div>
      <div><dt>大小</dt><dd>{{ $formatSize((int) $file->size) }}</dd></div>
      <div><dt>风险评分</dt><dd style="color: {{ $displayRiskColor }};">{{ $displayRiskScore }} / 100</dd></div>
      <div><dt>扫描时间</dt><dd>{{ $file->malware_scan_checked_at ? (is_string($file->malware_scan_checked_at) ? $file->malware_scan_checked_at : $file->malware_scan_checked_at->format('Y-m-d H:i:s')) : '未知' }}</dd></div>
      <div><dt>内容安全扫描</dt><dd style="color: #dc2626;">检测到风险</dd></div>
    </dl>

    @if (!empty($appeals))
      <div class="malware-alert" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin:16px 0;">
        <h3 style="color:#1d4ed8;margin:0 0 12px 0;font-size:16px;">申诉进度</h3>
        @foreach ($appeals as $appeal)
          <p style="margin:6px 0;color:#1e3a8a;font-size:13px;line-height:1.6;">
            状态：{{ $appeal['status'] ?? 'pending' }}
            @if(!empty($appeal['review_note'])) · 备注：{{ $appeal['review_note'] }} @endif
            @if(!empty($appeal['reviewed_at'])) · 处理时间：{{ $appeal['reviewed_at'] }} @endif
          </p>
        @endforeach
      </div>
    @endif

    @if(!empty($file->risk_reasons_json) && is_array($file->risk_reasons_json))
      <div class="malware-alert" style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 16px; margin: 16px 0;">
        <h3 style="color: #9a3412; margin: 0 0 12px 0; font-size: 16px;">风险评分依据</h3>
        <ul style="margin: 0; padding-left: 18px; color: #7c2d12; font-size: 13px; line-height: 1.7;">
          @foreach($file->risk_reasons_json as $reason)
            <li>{{ $reason }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if($malwareScanDetails && !empty($malwareScanDetails))
      @if(!empty($threatTypes))
        <div class="malware-alert" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 16px 0;">
          <h3 style="color: #dc2626; margin: 0 0 12px 0; font-size: 16px;">检测到的威胁类型</h3>
          
          <div style="margin: 8px 0;">
            @foreach($threatTypes as $threat)
              <span style="background: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin: 2px; display: inline-block;">{{ $threat }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if(isset($malwareScanDetails['details']['archive_scan']))
        <div class="malware-alert" style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px; margin: 16px 0;">
          <h3 style="color: #92400e; margin: 0 0 12px 0; font-size: 16px;">压缩包扫描结果</h3>
          
          @if(isset($malwareScanDetails['details']['archive_scan']['total_files']))
            <p style="margin: 8px 0; font-size: 13px; color: #78350f;">
              总文件数：{{ $malwareScanDetails['details']['archive_scan']['total_files'] }} | 
              扫描文件数：{{ $malwareScanDetails['details']['archive_scan']['scanned_files'] ?? 0 }} |
              跳过文件：{{ $malwareScanDetails['details']['archive_scan']['skipped_files'] ?? 0 }} |
              恶意文件：{{ $malwareScanDetails['details']['archive_scan']['malicious_files'] ?? 0 }} |
              扫描上限：{{ $malwareScanDetails['details']['archive_scan']['max_scan_files'] ?? 30 }} |
              覆盖率：{{ $malwareScanDetails['details']['archive_scan']['coverage_percent'] ?? 0 }}%
            </p>
            <div style="height:8px;background:#fde68a;border-radius:999px;overflow:hidden;margin:10px 0;">
              <div style="height:8px;width:{{ max(0, min(100, (float) ($malwareScanDetails['details']['archive_scan']['coverage_percent'] ?? 0))) }}%;background:#f59e0b;border-radius:999px;"></div>
            </div>
          @endif
          @if(($malwareScanDetails['details']['archive_scan']['total_files'] ?? 0) > ($malwareScanDetails['details']['archive_scan']['scanned_files'] ?? 0))
            <p style="margin: 8px 0; font-size: 13px; color: #92400e; line-height: 1.6;">
              本次只扫描了压缩包中符合策略的部分文件，未扫描文件仍可能存在风险。请在隔离环境中处理该压缩包。
            </p>
          @endif
          @if(($malwareScanDetails['details']['archive_scan']['status'] ?? '') === 'unsupported_archive_type')
            <p style="margin: 8px 0; font-size: 13px; color: #92400e;">
              {{ $malwareScanDetails['details']['archive_scan']['reason'] ?? '当前压缩包类型暂未进行深度扫描' }}
            </p>
          @endif
          @if(!empty($malwareScanDetails['details']['archive_scan']['skipped_reasons']))
            <div style="margin-top:12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px;">
              <strong style="display:block;color:#9a3412;margin-bottom:8px;font-size:13px;">跳过原因统计</strong>
              <ul style="margin:0;padding-left:18px;color:#7c2d12;font-size:12px;line-height:1.7;">
                @foreach($malwareScanDetails['details']['archive_scan']['skipped_reasons'] as $reason => $count)
                  <li>{{ $reason }}：{{ $count }}</li>
                @endforeach
              </ul>
            </div>
          @endif
          @if(!empty($malwareScanDetails['details']['archive_scan']['skipped_examples']))
            <details style="margin-top:12px;">
              <summary style="cursor:pointer;color:#92400e;font-size:13px;">查看跳过样例</summary>
              <div style="margin-top:8px;max-height:180px;overflow:auto;background:#fff;border:1px solid #fde68a;border-radius:8px;padding:10px;">
                @foreach($malwareScanDetails['details']['archive_scan']['skipped_examples'] as $skip)
                  <div style="font-size:12px;color:#78350f;margin-bottom:6px;">{{ $skip['name'] ?? '未知文件' }}：{{ $skip['reason'] ?? '未知原因' }}</div>
                @endforeach
              </div>
            </details>
          @endif
        </div>
      @endif

      @if(!empty($maliciousEntries))
        <div class="malware-alert" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin: 16px 0;">
          <h3 style="color: #1f2937; margin: 0 0 16px 0; font-size: 16px;">命中的风险文件</h3>
          
          <div style="max-height: 400px; overflow-y: auto;">
            @foreach($maliciousEntries as $entry)
              <div style="background: #fef2f2; border-left: 3px solid #dc2626; padding: 12px; margin-bottom: 8px; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                  <strong style="color: #1f2937; font-size: 14px;">{{ $entry['name'] ?? '未知文件' }}</strong>
                  <span style="font-size: 12px; color: #6b7280;">{{ isset($entry['size']) ? number_format($entry['size']) . ' bytes' : '' }}</span>
                </div>
                
                @if(isset($entry['threats']) && !empty($entry['threats']))
                  <div style="margin-top: 8px;">
                    @foreach($entry['threats'] as $threat)
                      <span style="background: #fee2e2; color: #dc2626; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin: 2px; display: inline-block;">{{ $threat }}</span>
                    @endforeach
                  </div>
                @endif

                  @if(!empty($entry['confidence']) || !empty($entry['model']) || !empty($entry['scanner']))
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;font-size:11px;color:#6b7280;">
                      @if(!empty($entry['confidence']))
                        <span>置信度：<strong style="color:#92400e;">{{ $entry['confidence'] }}</strong></span>
                      @endif
                      @if(!empty($entry['model']))
                        <span>模型：{{ $entry['model'] }}</span>
                      @endif
                      @if(!empty($entry['scanner']))
                        <span>扫描器：{{ $entry['scanner'] }}</span>
                      @endif
                      @if(!empty($entry['entry_type']))
                        <span>条目类型：{{ $entry['entry_type'] }}</span>
                      @endif
                    </div>
                  @endif

                  <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:10px;margin-top:10px;color:#7c2d12;font-size:12px;line-height:1.6;">
                    <strong style="display:block;margin-bottom:4px;">AI 判定原因</strong>
                    {{ !empty($entry['reason']) ? $entry['reason'] : $fallbackReason($entry) }}
                  </div>
              </div>
            @endforeach
          </div>
        </div>
      @endif
    @else
      <div class="malware-alert" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 16px 0;">
        <h3 style="color: #dc2626; margin: 0 0 12px 0; font-size: 16px;">安全警告</h3>
        <p style="margin: 8px 0; font-size: 13px; color: #991b1b;">
          此文件被标记为高风险文件，但详细的风险分析信息未保存。请谨慎处理此文件。
        </p>
      </div>
    @endif

    <div class="malware-alert" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 16px 0;">
      <h3 style="color: #dc2626; margin: 0 0 12px 0; font-size: 16px;">安全提示</h3>
      <p style="margin: 8px 0; font-size: 13px; color: #991b1b; line-height: 1.6;">
        @if($isHardBlocked)
          • 此文件已被识别为违规内容<br>
          • 系统已禁止下载和在线预览<br>
          • 如确认属于误报，请联系管理员在后台进行人工复核
        @else
          • 此文件包含已识别的高风险内容<br>
          • 下载、保存或传播此文件可能带来账号、设备或合规风险<br>
          • 请仅在完全了解风险的情况下继续操作<br>
          • 建议在隔离环境中进行分析和测试
        @endif
      </p>
    </div>

    <div class="actions threat-actions">
      @if($isHardBlocked)
        <button type="button" disabled aria-disabled="true" style="background: #9ca3af; cursor: not-allowed;">已禁止下载</button>
      @else
        <button type="button" id="danger-download-open" style="background: #dc2626;">仍要下载</button>
      @endif
      <a href="{{ route('files.show', ['code' => $code]) }}">返回文件页面</a>
      <a href="/">继续上传</a>
    </div>

    <div id="appeal" class="file-callout warn" style="margin-top:18px;">
      <strong style="display:block;margin-bottom:8px;">认为系统误判？</strong>
      <p style="margin:0 0 12px;color:#78350f;font-size:13px;line-height:1.7;">请提交申诉说明文件用途和误判原因。管理员会在后台复核，确认误报后可恢复访问。</p>
      @if(session('appeal_status'))
        <div style="margin-bottom:10px;color:#065f46;background:#ecfdf5;border-radius:10px;padding:10px;font-size:13px;">{{ session('appeal_status') }}</div>
      @endif
      @if(session('appeal_error'))
        <div style="margin-bottom:10px;color:#991b1b;background:#fef2f2;border-radius:10px;padding:10px;font-size:13px;">{{ session('appeal_error') }}</div>
      @endif
      @if($errors->any())
        <div style="margin-bottom:10px;color:#991b1b;background:#fef2f2;border-radius:10px;padding:10px;font-size:13px;">{{ $errors->first() }}</div>
      @endif
      <form class="appeal-form" method="post" action="{{ route('files.appeal', ['code' => $code]) }}" style="display:grid;gap:10px;">
        @csrf
        <input name="contact" maxlength="120" value="{{ old('contact') }}" placeholder="联系方式（可选）" style="width:100%;min-height:42px;border:1px solid rgba(146,64,14,.18);border-radius:12px;padding:0 12px;">
        <textarea name="reason" required minlength="8" maxlength="1000" rows="4" placeholder="请说明为什么这是误判、文件用途、来源说明等" style="width:100%;border:1px solid rgba(146,64,14,.18);border-radius:12px;padding:10px 12px;resize:vertical;">{{ old('reason') }}</textarea>
        <button type="submit" style="justify-self:start;background:#92400e;">提交申诉</button>
      </form>
    </div>
  </main>

  @if(!$isHardBlocked)
  <div id="danger-download-modal" role="dialog" aria-modal="true" aria-labelledby="danger-download-title" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.68);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="width:min(560px,100%);max-height:calc(100vh - 40px);overflow:auto;background:#fff;border-radius:16px;box-shadow:0 24px 80px rgba(0,0,0,0.28);padding:24px;">
      <h2 id="danger-download-title" style="margin:0 0 10px;color:#991b1b;font-size:22px;">风险确认与免责申明</h2>
      <p style="margin:0 0 14px;color:#374151;line-height:1.7;font-size:14px;">系统已检测到该文件包含违规内容或安全风险。下载、保存、解压、运行或传播该文件可能造成账号风险、设备风险、合规风险或其他损失。</p>
      <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;margin:14px 0;color:#7f1d1d;font-size:13px;line-height:1.7;">
        <strong style="display:block;margin-bottom:6px;">继续下载即表示你确认：</strong>
        <div>1. 你已阅读并理解本文件存在的安全风险。</div>
        <div>2. 你将仅在合法、授权、隔离的环境中分析或处理该文件。</div>
        <div>3. 你自行承担下载和使用该文件产生的全部风险与责任。</div>
        <div>4. 本站仅提供文件传输与风险提示服务，不对继续下载后的后果承担责任。</div>
      </div>
      <label style="display:flex;gap:9px;align-items:flex-start;margin:14px 0;color:#111827;font-size:14px;line-height:1.5;cursor:pointer;">
        <input id="danger-download-agree" type="checkbox" style="width:16px;height:16px;min-width:16px;margin:2px 0 0;accent-color:#dc2626;cursor:pointer;">
        <span>我已了解该文件的安全风险，并同意自行承担继续下载和后续处理产生的全部责任。</span>
      </label>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px;">
        <button type="button" id="danger-download-cancel" style="background:#f3f4f6;color:#374151;border:0;border-radius:10px;padding:10px 16px;cursor:pointer;">取消</button>
        <button type="button" id="danger-download-confirm" disabled aria-disabled="true" style="background:#9ca3af;color:#fff;border:0;border-radius:10px;padding:10px 16px;cursor:not-allowed;">确认并下载</button>
      </div>
    </div>
  </div>

  <script>
    (function () {
      var openButton = document.getElementById('danger-download-open');
      var modal = document.getElementById('danger-download-modal');
      var agree = document.getElementById('danger-download-agree');
      var cancel = document.getElementById('danger-download-cancel');
      var confirm = document.getElementById('danger-download-confirm');
      var downloadUrl = @json($confirmedDownloadUrl);

      function closeModal() {
        modal.style.display = 'none';
        agree.checked = false;
        confirm.disabled = true;
        confirm.style.background = '#9ca3af';
        confirm.style.cursor = 'not-allowed';
        confirm.setAttribute('aria-disabled', 'true');
        openButton.focus();
      }

      openButton.addEventListener('click', function () {
        modal.style.display = 'flex';
        agree.focus();
      });

      cancel.addEventListener('click', closeModal);
      modal.addEventListener('click', function (event) {
        if (event.target === modal) {
          closeModal();
        }
      });
      agree.addEventListener('change', function () {
        confirm.disabled = !agree.checked;
        confirm.setAttribute('aria-disabled', agree.checked ? 'false' : 'true');
        confirm.style.background = agree.checked ? '#dc2626' : '#9ca3af';
        confirm.style.cursor = agree.checked ? 'pointer' : 'not-allowed';
      });
      confirm.addEventListener('click', function () {
        if (agree.checked) {
          window.location.href = downloadUrl;
        }
      });
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.style.display === 'flex') {
          closeModal();
        }
        if (event.key === 'Tab' && modal.style.display === 'flex') {
          var focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
          if (!focusable.length) return;
          var first = focusable[0];
          var last = focusable[focusable.length - 1];
          if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
          } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
          }
        }
      });
    })();
  </script>
  @endif
</body>
</html>
