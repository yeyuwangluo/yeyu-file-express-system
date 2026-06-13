@php
  $formatSize = function (int $size): string {
      $units = ['B', 'KB', 'MB', 'GB'];
      $value = max(0, $size);
      $index = 0;
      while ($value >= 1024 && $index < count($units) - 1) { $value /= 1024; $index++; }
      return number_format($value, ($value >= 10 || $index === 0) ? 0 : 1).' '.$units[$index];
  };
  $category = function ($file): string {
      $mime = strtolower((string) ($file->mime_type ?? ''));
      $ext = strtolower((string) ($file->extension ?? ''));
      if (str_starts_with($mime, 'image/')) return '图片';
      if (str_starts_with($mime, 'video/')) return '视频';
      if (str_starts_with($mime, 'audio/')) return '音频';
      if (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md'], true)) return '文档';
      if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'], true)) return '压缩包';
      return '其他';
  };
  $groupedFiles = $files->groupBy(fn ($file) => $category($file));
  $batchUrl = url('/batch/'.$token);
  $batchQrUrl = route('qr', ['size' => 240, 'margin' => 10, 'data' => $batchUrl]);
  $batchTitle = trim((string) ($batch['title'] ?? '')) ?: '批量文件分享';
  $batchDescription = trim((string) ($batch['description'] ?? ''));
  $totalSize = $files->sum(fn ($file) => (int) $file->size);
  $coverFile = $files->first(fn ($file) => $file->code === ($batch['cover_code'] ?? '') && str_starts_with(strtolower((string) $file->mime_type), 'image/'));
@endphp
<x-files.shell title="批量分享 - 叶宇文件快递">
  <section class="file-hero">
    <div class="file-state-row"><span class="badge {{ $expired ? 'warning' : '' }}">批量分享</span><span class="badge">{{ $token }}</span></div>
    <h1>{{ $expired ? '批量分享已失效' : $batchTitle }}</h1>
    <p class="muted">{{ $expired ? '这个批量分享不存在或已过期。' : ($batchDescription !== '' ? $batchDescription : '以下文件来自同一个批量分享链接，可逐个打开、预览或下载。') }}</p>
  </section>

  @if (!$expired)
    @if ($coverFile)
      <section class="file-callout" style="overflow:hidden;padding:0;background:rgba(255,255,255,.42);">
        <img src="{{ route('api.files.thumbnail', ['code' => $coverFile->code]) }}" alt="批量分享封面" style="display:block;width:100%;max-height:260px;object-fit:cover;">
      </section>
    @endif
    <dl class="details">
      <div><dt>文件数量</dt><dd>{{ $files->count() }}</dd></div>
      <div><dt>总大小</dt><dd>{{ $formatSize((int) $totalSize) }}</dd></div>
      <div><dt>过期时间</dt><dd>{{ $batch['expires_at'] ?? '-' }}</dd></div>
      <div><dt>可打包</dt><dd>{{ $downloadSummary['downloadable'] ?? 0 }} 个</dd></div>
    </dl>
    <div class="actions" style="margin-top:14px;">
      <button type="button" data-copy-batch data-title="{{ $batchTitle }}" data-description="{{ $batchDescription }}">复制批量链接</button>
      <button type="button" data-copy-file-links>复制全部文件链接</button>
      <a class="secondary-link" href="{{ route('files.batch.download', ['token' => $token]) }}" data-batch-download>打包下载</a>
      <a class="secondary-link" href="/my-files">我的文件</a>
    </div>
    <section class="file-callout safe" style="margin-top:14px;">
      <strong>下载前提示</strong><br>
      本次批量分享共 {{ $files->count() }} 个文件，约 {{ $formatSize((int) $totalSize) }}，打包下载会自动排除风险文件、过期文件和当前不可直读的外部存储文件。微信/QQ 内置浏览器下载大文件时，建议复制链接到系统浏览器打开。
    </section>
    @if (!empty($downloadSummary['skipped']))
      <section class="file-callout warn" style="margin-top:14px;">
        <strong>打包下载清单</strong><br>
        可打包 {{ $downloadSummary['downloadable'] ?? 0 }} 个，已跳过 {{ count($downloadSummary['skipped']) }} 个。
        <div class="details" style="margin-top:10px;">
          @foreach (array_slice($downloadSummary['skipped'], 0, 12) as $skipped)
            <div><dt>{{ $skipped['code'] }}</dt><dd>{{ Str::limit($skipped['name'], 24) }} · {{ $skipped['reason'] }}</dd></div>
          @endforeach
        </div>
      </section>
    @endif
    <section class="file-callout" style="display:grid;grid-template-columns:auto 1fr;gap:14px;align-items:center;background:rgba(255,255,255,.48);">
      <img src="{{ $batchQrUrl }}" alt="批量分享二维码" width="132" height="132" loading="lazy" style="border-radius:16px;background:#fff;padding:8px;box-shadow:0 14px 36px -26px rgba(15,23,42,.55);">
      <div>
        <strong style="display:block;margin-bottom:8px;">批量分享二维码</strong>
        <div class="muted" style="font-size:13px;line-height:1.7;">可截图发送给 QQ、微信好友，对方扫码后能看到整个文件夹。</div>
      </div>
    </section>
    <div class="details" style="margin-top:14px;">
      @foreach ($groupedFiles as $groupName => $groupFiles)
        <div><dt>{{ $groupName }}</dt><dd>{{ $groupFiles->count() }} 个</dd></div>
      @endforeach
    </div>
    @foreach ($groupedFiles as $groupName => $groupFiles)
      <details class="file-callout" style="margin-top:14px;" open>
        <summary style="cursor:pointer;font-weight:800;">{{ $groupName }} · {{ $groupFiles->count() }} 个 · {{ $formatSize((int) $groupFiles->sum(fn ($file) => (int) $file->size)) }}</summary>
        <div class="scroll mobile-cards" style="margin-top:12px;overflow:auto;">
          <table style="width:100%;border-collapse:collapse;min-width:620px;">
            <thead><tr><th>文件</th><th>分享码</th><th>大小</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
              @foreach ($groupFiles as $file)
                <tr>
                  <td data-label="文件">{{ $file->original_name }}</td>
                  <td data-label="分享码">{{ $file->code }}</td>
                  <td data-label="大小">{{ $formatSize((int) $file->size) }}</td>
                  <td data-label="状态"><span class="badge">{{ $file->publicStatus() }}</span></td>
                  <td data-label="操作"><a href="{{ route('files.show', ['code' => $file->code]) }}">打开</a> · <a href="{{ route('api.files.download', ['code' => $file->code]) }}">下载</a> · <button type="button" data-copy-group="{{ $groupName }}">复制本组</button></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </details>
    @endforeach
  @endif
  <div class="actions"><a href="/">继续上传</a><a class="secondary-link" href="/my-files">我的文件</a></div>
  @if (!$expired)
    <script>
      (function () {
        var batchUrl = location.href;
        var fileLinks = @json($files->map(fn ($file) => url('/files/'.$file->code))->values()->all());
        var groupLinks = @json($groupedFiles->map(fn ($items) => $items->map(fn ($file) => url('/files/'.$file->code))->values()->all())->all());
        async function copyText(text) {
          if (navigator.clipboard && navigator.clipboard.writeText) await navigator.clipboard.writeText(text);
          else {
            var input = document.createElement('textarea');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
          }
        }
        var copyBatch = document.querySelector('[data-copy-batch]');
        var copyFiles = document.querySelector('[data-copy-file-links]');
        function copied(message) {
          if (window.showShareToast) window.showShareToast(message);
        }
        if (copyBatch) copyBatch.addEventListener('click', function () {
          var title = copyBatch.dataset.title || '批量文件分享';
          var description = copyBatch.dataset.description ? '\n说明：' + copyBatch.dataset.description : '';
          copyText(title + description + '\n链接：' + batchUrl).then(function () { copied('批量分享文案已复制'); });
        });
        if (copyFiles) copyFiles.addEventListener('click', function () { copyText(fileLinks.join('\n')).then(function () { copied('全部文件链接已复制'); }); });
        document.querySelectorAll('[data-copy-group]').forEach(function (button) {
          button.addEventListener('click', function () {
            var group = button.dataset.copyGroup;
            copyText((groupLinks[group] || []).join('\n')).then(function () { copied(group + '链接已复制'); });
          });
        });
        var batchDownload = document.querySelector('[data-batch-download]');
        if (batchDownload) batchDownload.addEventListener('click', function () { batchDownload.textContent = '正在打包...'; });
      })();
    </script>
  @endif
</x-files.shell>
