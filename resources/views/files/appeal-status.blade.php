<x-files.shell title="申诉查询 - 叶宇文件快递">
  <section class="file-hero">
    <div class="file-state-row"><span class="badge">申诉查询</span><span class="badge">{{ $lookupCode }}</span></div>
    <h1>{{ !empty($appeal) ? '申诉处理进度' : '未找到申诉记录' }}</h1>
    <p class="muted">凭申诉查询码可以查看管理员处理状态和备注。</p>
  </section>

  @if(!empty($appeal))
    <dl class="details">
      <div><dt>分享码</dt><dd>{{ $appeal['code'] ?? '-' }}</dd></div>
      <div><dt>状态</dt><dd>{{ $appeal['status'] ?? 'pending' }}</dd></div>
      <div><dt>提交时间</dt><dd>{{ $appeal['submitted_at'] ?? '-' }}</dd></div>
      <div><dt>更新时间</dt><dd>{{ $appeal['updated_at'] ?? '-' }}</dd></div>
    </dl>

    @if(!empty($appeal['review_note']) || !empty($appeal['reviewed_at']))
      <section class="file-callout safe">
        <strong style="display:block;margin-bottom:8px;">处理结果</strong>
        @if(!empty($appeal['review_note']))<div>{{ $appeal['review_note'] }}</div>@endif
        @if(!empty($appeal['reviewed_at']))<div class="muted">处理时间：{{ $appeal['reviewed_at'] }}</div>@endif
      </section>
    @else
      <section class="file-callout warn">管理员尚未处理，请稍后再查。</section>
    @endif

    <div class="actions">
      <a href="{{ route('files.show', ['code' => $appeal['code'] ?? '']) }}">返回文件页面</a>
      <button type="button" data-copy="{{ url('/appeals/'.$lookupCode) }}">复制查询链接</button>
    </div>
  @else
    <section class="file-callout warn">查询码无效或记录已归档，请确认提交申诉后页面显示的查询码。</section>
    <div class="actions"><a href="/">返回首页</a></div>
  @endif
</x-files.shell>
