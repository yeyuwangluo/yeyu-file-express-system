<x-files.shell title="页面不存在 - 叶宇文件快递">
  <span class="badge warning">404</span>
  <h1>页面不存在</h1>
  <p class="muted">没有找到 /{{ ltrim($path ?? '', '/') }}。可以返回首页继续上传，或查看服务状态。</p>
  <div class="actions">
    <a href="/">返回首页</a>
    <a class="secondary" href="/status">查看状态</a>
  </div>
</x-files.shell>
