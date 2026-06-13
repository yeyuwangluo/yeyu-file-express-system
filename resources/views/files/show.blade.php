@php
  $title = $expired ? '文件不存在或已过期 - 叶宇文件快递' : $file->original_name.' - 叶宇文件快递';
  $shareUrl = url('/files/'.$code);
  $downloadUrl = url('/api/v1/files/'.$code.'/download');
  $previewUrl = url('/api/v1/files/'.$code.'/preview');
  $shareMessage = '我给你发了一个文件：'.(!$expired && $file ? $file->original_name : '文件分享').'，链接：'.$shareUrl.(!$expired && $file && $file->has_extract_code ? '，提取码请向分享者索取。' : '');
  $qrUrl = route('qr', ['size' => 240, 'margin' => 10, 'data' => $shareUrl]);
  $daysLeft = (!$expired && $file && $file->expires_at) ? now()->diffInDays($file->expires_at, false) : null;
  $isLargeFile = !$expired && $file && (int) $file->size >= 100 * 1024 * 1024;
  $userAgent = strtolower((string) request()->userAgent());
  $isInAppBrowser = str_contains($userAgent, 'micromessenger') || str_contains($userAgent, 'qq/') || str_contains($userAgent, 'mqqbrowser');
  $previewable = false;
  if (!$expired && $file) {
      $mime = strtolower((string) ($file->mime_type ?: ''));
      $extension = strtolower((string) ($file->extension ?: pathinfo($file->original_name, PATHINFO_EXTENSION)));
      $previewable = $mime === 'application/pdf'
          || $extension === 'pdf'
          || (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml')
          || str_starts_with($mime, 'audio/')
          || str_starts_with($mime, 'video/')
          || str_starts_with($mime, 'text/')
          || in_array($extension, ['txt', 'md', 'csv', 'json', 'log', 'xml', 'yaml', 'yml'], true);
  }
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
  $archiveScan = is_array($malwareScanDetails['details']['archive_scan'] ?? null) ? $malwareScanDetails['details']['archive_scan'] : null;
  $archiveFiles = is_array($archiveScan['files'] ?? null) ? $archiveScan['files'] : [];
  $archiveSkippedExamples = is_array($archiveScan['skipped_examples'] ?? null) ? $archiveScan['skipped_examples'] : [];
  $archiveRiskFiles = array_values(array_filter($archiveFiles, fn ($entry) => is_array($entry) && !empty($entry['is_malicious'])));
  $archiveCoverage = is_numeric($archiveScan['coverage_percent'] ?? null) ? (float) $archiveScan['coverage_percent'] : 0.0;
  $archiveSkippedCount = (int) ($archiveScan['skipped_files'] ?? 0);
  $archiveFullyCovered = $archiveCoverage >= 100 && $archiveSkippedCount === 0;
  $archiveEntrySize = function ($size) use ($formatSize): string {
      return is_numeric($size) ? $formatSize((int) $size) : '未知大小';
  };
@endphp

<x-files.shell :title="$title">
  @if (!empty($notices))
    <div class="file-callout safe">
      <strong style="display:block;margin-bottom:8px;">文件通知</strong>
      @foreach ($notices as $notice)
        <div>{{ $notice['message'] ?? '有新的处理结果。' }} @if(!empty($notice['note']))<span class="muted">{{ $notice['note'] }}</span>@endif</div>
      @endforeach
    </div>
  @endif
  @if (!empty($appeals))
    <div class="file-callout warn">
      <strong style="display:block;margin-bottom:8px;">申诉进度</strong>
      @foreach ($appeals as $appeal)
        <div>
          状态：{{ $appeal['status'] ?? 'pending' }}
          @if(!empty($appeal['review_note']))<span class="muted">{{ $appeal['review_note'] }}</span>@endif
          @if(!empty($appeal['submitted_at']))<span class="muted">提交于 {{ $appeal['submitted_at'] }}</span>@endif
        </div>
      @endforeach
    </div>
  @endif
  @if ($expired)
    <section class="file-hero">
      <div class="file-state-row"><span class="badge warning">链接失效</span></div>
      <h1>文件不存在或已过期</h1>
      <p class="muted">这个分享链接当前不可用。文件可能已经过期、被删除，或分享码不存在。</p>
    </section>
    <div class="actions">
      <a href="/">返回上传</a>
    </div>
  @elseif ($scanPending)
    <section class="file-hero">
      <div class="file-state-row"><span class="badge warning">扫描中</span><span class="badge">分享码 {{ $code }}</span></div>
      <h1>{{ $file->original_name }}</h1>
      <p class="muted">文件正在进行安全扫描。扫描完成后页面会自动刷新，并展示可下载或拦截状态。</p>
    </section>

    <div class="share-code" aria-label="分享码">
      <span>分享码</span>
      <strong>{{ $code }}</strong>
    </div>

    <dl class="details">
      <div><dt>文件名</dt><dd>{{ $file->original_name }}</dd></div>
      <div><dt>大小</dt><dd>{{ $formatSize((int) $file->size) }}</dd></div>
      <div><dt>提取码</dt><dd>{{ $file->has_extract_code ? '需要' : '无需' }}</dd></div>
      <div><dt>过期时间</dt><dd>{{ $file->expires_at ? $file->expires_at->timezone('Asia/Shanghai')->format('Y/m/d H:i') : '未知' }}</dd></div>
      <div><dt>扫描状态</dt><dd style="color: #f59e0b;">病毒扫描中...</dd></div>
    </dl>

    <div class="file-callout warn">系统正在确认文件内容，当前阶段会临时关闭下载入口。</div>

    <div class="actions">
      <button type="button" disabled>文件扫描中，请稍后</button>
      <a class="secondary-link" href="/">继续上传</a>
      <a class="secondary-link" href="/my-files">我的文件</a>
    </div>
    <p id="scan-refresh-note" class="muted" style="font-size:12px;margin-top:10px;">正在获取最新扫描状态。</p>
    <script>
      (function () {
        var note = document.getElementById('scan-refresh-note');
        var attempts = 0;
        async function refreshScanStatus() {
          attempts += 1;
          try {
            var response = await fetch('/api/v1/files/{{ $code }}/scan-status', { headers: { 'Accept': 'application/json' } });
            var result = await response.json();
            var status = result && result.data ? result.data.malwareStatus : 'pending';
            if (status === 'clean' || status === 'threat') {
              window.location.reload();
              return;
            }
            if (note) note.textContent = '安全扫描仍在进行，已检查 ' + attempts + ' 次。';
          } catch (error) {
            if (note) note.textContent = '扫描状态暂时无法获取，稍后自动重试。';
          }
          setTimeout(refreshScanStatus, 5000);
        }
        setTimeout(refreshScanStatus, 1200);
      })();
    </script>
  @elseif ($scanFailed)
    <section class="file-hero">
      <div class="file-state-row"><span class="badge danger">扫描失败</span><span class="badge">分享码 {{ $code }}</span></div>
      <h1>{{ $file->original_name }}</h1>
      <p class="muted">文件病毒扫描未通过，系统已拦截普通下载。</p>
    </section>

    <div class="share-code" aria-label="分享码">
      <span>分享码</span>
      <strong>{{ $code }}</strong>
    </div>

    <dl class="details">
      <div><dt>文件名</dt><dd>{{ $file->original_name }}</dd></div>
      <div><dt>大小</dt><dd>{{ $formatSize((int) $file->size) }}</dd></div>
      <div><dt>提取码</dt><dd>{{ $file->has_extract_code ? '需要' : '无需' }}</dd></div>
      <div><dt>过期时间</dt><dd>{{ $file->expires_at ? $file->expires_at->timezone('Asia/Shanghai')->format('Y/m/d H:i') : '未知' }}</dd></div>
      <div><dt>扫描状态</dt><dd style="color: #ef4444;">扫描未通过</dd></div>
    </dl>

    <div class="file-callout danger">此文件已被安全策略拦截。请联系分享者确认文件来源和内容。</div>

    <div class="actions">
      <button type="button" disabled>文件已被拦截</button>
      <a class="secondary-link" href="/">继续上传</a>
    </div>
  @elseif ($hasMalwareThreats)
    <section class="file-hero">
      <div class="file-state-row"><span class="badge danger">检测到风险</span><span class="badge">分享码 {{ $code }}</span></div>
      <h1>{{ $file->original_name }}</h1>
      <p class="muted">AI 安全扫描检测到此文件包含违规内容或安全风险。普通下载和在线预览已被拦截，详情页会展示风险依据。</p>
    </section>

    <div class="share-code" aria-label="分享码">
      <span>分享码</span>
      <strong>{{ $code }}</strong>
    </div>

    <dl class="details">
      <div><dt>文件名</dt><dd>{{ $file->original_name }}</dd></div>
      <div><dt>大小</dt><dd>{{ $formatSize((int) $file->size) }}</dd></div>
      <div><dt>提取码</dt><dd>{{ $file->has_extract_code ? '需要' : '无需' }}</dd></div>
      <div><dt>过期时间</dt><dd>{{ $file->expires_at ? $file->expires_at->timezone('Asia/Shanghai')->format('Y/m/d H:i') : '未知' }}</dd></div>
      <div><dt>扫描状态</dt><dd style="color: #ef4444;">检测到风险</dd></div>
    </dl>

    @if (!empty($malwareScanDetails['threats']))
      <div class="file-callout danger">
        <strong style="display:block;margin-bottom:8px;">威胁类型</strong>
        @foreach (array_slice($malwareScanDetails['threats'], 0, 5) as $threat)
          <span class="threat-chip">{{ $threat }}</span>
        @endforeach
      </div>
    @endif

    @if ($archiveScan)
      <section class="archive-scan-report danger">
        <div class="archive-scan-head">
          <div>
            <strong>压缩包内部扫描报告</strong>
            <span>系统已检查 ZIP 内部文件，并将风险汇总到当前分享。</span>
          </div>
          <span class="archive-scan-status danger">发现风险</span>
        </div>
        <div class="archive-scan-stats">
          <div><span>内部文件</span><strong>{{ (int) ($archiveScan['total_files'] ?? 0) }}</strong></div>
          <div><span>已扫描</span><strong>{{ (int) ($archiveScan['scanned_files'] ?? 0) }}</strong></div>
          <div><span>跳过</span><strong>{{ (int) ($archiveScan['skipped_files'] ?? 0) }}</strong></div>
          <div><span>覆盖率</span><strong>{{ $archiveScan['coverage_percent'] ?? 0 }}%</strong></div>
        </div>
        @if (!empty($archiveRiskFiles))
          <div class="archive-scan-section">
            <strong>风险条目</strong>
            <div class="archive-entry-list">
              @foreach (array_slice($archiveRiskFiles, 0, 12) as $entry)
                <div class="archive-entry danger">
                  <div>
                    <strong>{{ $entry['name'] ?? '未知文件' }}</strong>
                    <span>{{ $archiveEntrySize($entry['size'] ?? null) }} · {{ $entry['entry_type'] ?? $entry['scanner'] ?? 'scan' }} · {{ $entry['confidence'] ?? 'none' }}</span>
                  </div>
                  <p>{{ $entry['reason'] ?? '检测到风险' }}</p>
                </div>
              @endforeach
            </div>
          </div>
        @endif
        @if (!empty($archiveSkippedExamples))
          <div class="archive-scan-section">
            <strong>跳过示例</strong>
            <div class="archive-skip-list">
              @foreach (array_slice($archiveSkippedExamples, 0, 8) as $skip)
                <span>{{ $skip['name'] ?? '未知条目' }}：{{ $skip['reason'] ?? '已跳过' }}</span>
              @endforeach
            </div>
          </div>
        @endif
      </section>
    @endif

    <div class="actions">
      <a href="{{ route('files.threat-details', ['code' => $code]) }}" style="background:#dc2626;">查看威胁详情</a>
      <button type="button" disabled>普通下载已拦截</button>
      <a class="secondary-link" href="{{ route('files.threat-details', ['code' => $code]) }}#appeal">提交申诉</a>
      <a class="secondary-link" href="/">继续上传</a>
    </div>
  @else
    <section class="file-hero">
      <div class="file-state-row"><span class="badge">安全扫描通过</span><span class="badge">分享码 {{ $code }}</span></div>
      <h1>{{ $file->original_name }}</h1>
      <p class="muted">文件已准备好，可以复制链接或直接下载。下载前请核对文件名、大小、提取码和有效期。</p>
    </section>

    <div class="share-code" aria-label="分享码">
      <span>分享码</span>
      <strong>{{ $code }}</strong>
    </div>

    <dl class="details">
      <div><dt>文件名</dt><dd>{{ $file->original_name }}</dd></div>
      <div><dt>大小</dt><dd>{{ $formatSize((int) $file->size) }}</dd></div>
      <div><dt>提取码</dt><dd>{{ $file->has_extract_code ? '需要' : '无需' }}</dd></div>
      <div><dt>过期时间</dt><dd>{{ $file->expires_at ? $file->expires_at->timezone('Asia/Shanghai')->format('Y/m/d H:i') : '未知' }}</dd></div>
      <div><dt>扫描状态</dt><dd style="color: #10b981;">扫描通过</dd></div>
      <div><dt>下载次数</dt><dd>{{ $file->downloads_count ?? $file->download_count ?? 0 }}</dd></div>
    </dl>

    @if(!empty($fileMeta['note']))
      <section class="file-callout warn">
        <strong style="display:block;margin-bottom:8px;">分享备注</strong>
        <div style="font-size:13px;line-height:1.7;white-space:pre-wrap;">{{ $fileMeta['note'] }}</div>
      </section>
    @endif

    <section class="file-callout safe">
      <strong style="display:block;margin-bottom:8px;">下载前确认</strong>
      <div style="display:grid;gap:6px;font-size:13px;line-height:1.7;">
        <span>文件类型：{{ strtoupper((string) ($file->extension ?: 'FILE')) }}</span>
        <span>有效期：{{ is_int($daysLeft) && $daysLeft >= 0 ? '剩余约 '.$daysLeft.' 天' : '以页面显示时间为准' }}</span>
        <span>安全状态：已完成公开下载前安全检查</span>
      </div>
    </section>

    @if ($archiveScan)
      <section class="archive-scan-report safe">
        <div class="archive-scan-head">
          <div>
            <strong>压缩包内部扫描报告</strong>
            <span>{{ $archiveFullyCovered ? '系统已检查 ZIP 内部文件，当前分享未发现拦截风险。' : '系统已展示 ZIP 内部扫描覆盖情况，未覆盖条目请查看跳过原因。' }}</span>
          </div>
          <span class="archive-scan-status {{ $archiveFullyCovered ? 'safe' : 'partial' }}">{{ $archiveFullyCovered ? '扫描通过' : '部分覆盖' }}</span>
        </div>
        <div class="archive-scan-stats">
          <div><span>内部文件</span><strong>{{ (int) ($archiveScan['total_files'] ?? 0) }}</strong></div>
          <div><span>已扫描</span><strong>{{ (int) ($archiveScan['scanned_files'] ?? 0) }}</strong></div>
          <div><span>跳过</span><strong>{{ (int) ($archiveScan['skipped_files'] ?? 0) }}</strong></div>
          <div><span>覆盖率</span><strong>{{ $archiveScan['coverage_percent'] ?? 0 }}%</strong></div>
        </div>
        @if (!empty($archiveFiles))
          <div class="archive-scan-section">
            <strong>已扫描条目</strong>
            <div class="archive-entry-list">
              @foreach (array_slice($archiveFiles, 0, 12) as $entry)
                <div class="archive-entry">
                  <div>
                    <strong>{{ $entry['name'] ?? '未知文件' }}</strong>
                    <span>{{ $archiveEntrySize($entry['size'] ?? null) }} · {{ $entry['entry_type'] ?? $entry['scanner'] ?? 'scan' }} · {{ $entry['confidence'] ?? 'none' }}</span>
                  </div>
                  <p>{{ $entry['reason'] ?? '扫描完成' }}</p>
                </div>
              @endforeach
            </div>
          </div>
        @endif
        @if (!empty($archiveSkippedExamples))
          <div class="archive-scan-section">
            <strong>跳过示例</strong>
            <div class="archive-skip-list">
              @foreach (array_slice($archiveSkippedExamples, 0, 8) as $skip)
                <span>{{ $skip['name'] ?? '未知条目' }}：{{ $skip['reason'] ?? '已跳过' }}</span>
              @endforeach
            </div>
          </div>
        @endif
      </section>
    @endif

    @if ($file->has_extract_code)
      @if (request()->query('extract_error'))
        <div class="file-callout danger">提取码错误，请重新输入。</div>
      @endif
      <form class="download-form" action="{{ $downloadUrl }}" method="get">
        <input name="extractCode" placeholder="输入提取码" minlength="4" maxlength="6" pattern="[A-Za-z0-9]{4,6}" inputmode="latin" autocomplete="off" required aria-label="提取码">
        <button type="submit">下载文件</button>
        @if ($previewable)
          <button type="submit" class="secondary-action" data-preview-submit data-preview-target="file-preview-frame" data-preview-url="{{ $previewUrl }}">在线预览</button>
        @endif
      </form>
    @endif

    <div class="actions">
      @unless ($file->has_extract_code)
        <a href="{{ $downloadUrl }}">下载文件</a>
      @endunless
      <button type="button" data-copy="{{ $shareUrl }}">复制链接</button>
      <button type="button" data-copy="{{ $shareMessage }}">复制分享文案</button>
      <button type="button" data-native-share data-title="{{ $file->original_name }}" data-text="{{ $shareMessage }}" data-url="{{ $shareUrl }}">系统分享</button>
      <a class="secondary-link" href="/">继续上传</a>
    </div>

    <section class="file-callout">
      <strong style="display:block;margin-bottom:8px;">微信 / QQ 下载提示</strong>
      <div style="font-size:13px;line-height:1.7;">遇到下载没有反应时，可先复制链接，在系统浏览器中打开。视频和压缩包建议使用浏览器下载后再查看。</div>
      @if ($isInAppBrowser)
        <div style="margin-top:8px;font-size:13px;font-weight:700;line-height:1.7;">当前可能处于微信/QQ 内置浏览器，推荐复制链接后使用系统浏览器打开下载。</div>
      @endif
      @if ($isLargeFile)
        <div style="margin-top:8px;font-size:13px;font-weight:700;line-height:1.7;">这是大文件，移动网络下载可能被中断，建议切换 Wi-Fi 或使用系统浏览器下载。</div>
      @endif
    </section>

    <section class="file-callout" style="display:grid;grid-template-columns:auto 1fr;gap:14px;align-items:center;">
      <img src="{{ $qrUrl }}" alt="分享二维码" width="132" height="132" loading="lazy" style="border-radius:16px;background:#fff;padding:8px;box-shadow:0 14px 36px -26px rgba(15,23,42,.55);">
      <div>
        <strong style="display:block;margin-bottom:8px;">扫码打开分享</strong>
        <div class="muted" style="font-size:13px;line-height:1.7;">二维码适合截图转发给 QQ、微信好友，也可以在另一台设备扫码下载。</div>
      </div>
    </section>
    @if ($previewable)
      <section class="inline-preview" aria-label="文件在线预览">
        <div class="inline-preview-head">
          <div>
            <strong>在线预览</strong>
            <span>{{ $file->has_extract_code ? '输入提取码后在当前页面预览' : '当前页面小屏预览，无需跳转' }}</span>
          </div>
          <span class="preview-pill">{{ strtoupper((string) ($file->extension ?: 'PREVIEW')) }}</span>
        </div>
        <div class="inline-preview-stage">
          @if ($file->has_extract_code)
            <div class="inline-preview-empty" id="file-preview-empty">请输入提取码，然后点击“在线预览”。</div>
            <iframe id="file-preview-frame" title="{{ $file->original_name }} 在线预览" loading="lazy" referrerpolicy="same-origin" hidden></iframe>
          @else
            <iframe id="file-preview-frame" title="{{ $file->original_name }} 在线预览" src="{{ $previewUrl }}" loading="lazy" referrerpolicy="same-origin"></iframe>
          @endif
        </div>
      </section>
    @endif
    <div class="file-callout safe">本站已完成安全扫描。图片、PDF、文本、音视频文件可直接在线预览，其他文件请下载后查看。</div>
    <script>
      document.querySelectorAll('[data-native-share]').forEach(function (button) {
        button.addEventListener('click', async function () {
          var payload = { title: button.dataset.title || document.title, text: button.dataset.text || '', url: button.dataset.url || location.href };
          if (navigator.share) {
            try { await navigator.share(payload); return; } catch (error) {}
          }
          if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(payload.text || payload.url);
            if (window.showShareToast) window.showShareToast('分享文案已复制');
          } else {
            window.prompt('请手动复制分享文案', payload.text || payload.url);
          }
        });
      });
      document.querySelectorAll('.download-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
          var button = event.submitter || form.querySelector('button[type="submit"]');
          if (button && button.matches('[data-preview-submit]')) {
            event.preventDefault();
            var frame = document.getElementById(button.dataset.previewTarget || 'file-preview-frame');
            var empty = document.getElementById('file-preview-empty');
            var params = new URLSearchParams(new FormData(form));
            if (frame) {
              frame.hidden = false;
              frame.src = button.dataset.previewUrl + '?' + params.toString();
            }
            if (empty) empty.hidden = true;
            button.textContent = '已加载预览';
            setTimeout(function () { button.textContent = '在线预览'; }, 1200);
            return;
          }
          if (button) {
            button.disabled = true;
            button.textContent = button.classList.contains('secondary-action') ? '打开预览...' : '准备下载...';
          }
        });
      });
    </script>
  @endif
</x-files.shell>
