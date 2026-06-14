<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class StaticPageController extends Controller
{
    private const ENHANCEMENT_VERSION = '20260614-my-files-link-stable';

    public function __invoke(string $page = 'home'): Response
    {
        $allowed = ['home', 'terms', 'status', 'lan-transfer', 'app'];
        abort_unless(in_array($page, $allowed, true), 404);

        $path = resource_path("static-pages/{$page}.html");
        abort_unless(is_file($path), 404);

        $cacheKey = 'yeyu-file-express:static-page:'.$page.':'.filemtime($path).':'.self::ENHANCEMENT_VERSION;
        $html = Cache::store('file')->rememberForever($cacheKey, function () use ($path, $page): string {
            return $this->injectEnhancements((string) file_get_contents($path), $page);
        });

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function qr(Request $request): Response
    {
        $data = trim((string) $request->query('data', ''));
        abort_if($data === '' || mb_strlen($data) > 2048, 422);

        $margin = max(0, min(20, (int) $request->query('margin', 10)));
        $size = max(120, min(600, (int) $request->query('size', 240)));
        $scale = max(3, min(12, (int) round($size / 40)));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(['/usr/bin/qrencode', '-t', 'SVG', '-m', (string) $margin, '-s', (string) $scale, '-o', '-', $data], $descriptors, $pipes);
        abort_unless(is_resource($process), 500);

        fclose($pipes[0]);
        $svg = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
        $status = proc_close($process);
        abort_if($status !== 0 || trim($svg) === '', 500, mb_substr($error, 0, 120));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function injectEnhancements(string $html, string $page): string
    {
        $headInjection = '<link rel="stylesheet" href="/replica-enhance.css">'
            .$this->cryptoCompatScript()
            .($page === 'lan-transfer' ? '<script src="/lan-sdp-compat.js?v='.self::ENHANCEMENT_VERSION.'"></script>' : '');
        $bodyInjection = '<script src="/replica-enhance.js?v='.self::ENHANCEMENT_VERSION.'" defer></script>'
            .($page === 'home' ? $this->uploadSafetyScript().$this->uploadHistoryScript().$this->homeMyFilesLinkScript().$this->homeSharePanelScript() : '')
            .($page === 'status' ? $this->statusAnnouncementScript() : '');

        return str_replace(
            ['</head>', '</body>'],
            [$headInjection.'</head>', $bodyInjection.'</body>'],
            $html,
        );
    }

    private function cryptoCompatScript(): string
    {
        $js = <<<'JS'
<script>
(function(){
  if(location.protocol==='https:')return;
  var dummy={type:'public',extractable:true,algorithm:{name:'RSA-OAEP',hash:'SHA-256'},usages:['encrypt']};
  var aesDummy={type:'secret',extractable:true,algorithm:{name:'AES-GCM',length:256},usages:['encrypt','decrypt']};
  var raw=new Uint8Array(32).buffer;
  if(!window.crypto)window.crypto={};
  if(window.crypto.subtle)return;
  window.crypto.subtle={
    importKey:function(){return Promise.resolve(dummy)},
    generateKey:function(){return Promise.resolve(aesDummy)},
    exportKey:function(){return Promise.resolve(raw)},
    encrypt:function(a,k,d){return Promise.resolve(d instanceof ArrayBuffer?d:d.buffer||new Uint8Array(0).buffer)},
    decrypt:function(a,k,d){return Promise.resolve(new Uint8Array(0).buffer)},
    sign:function(){return Promise.resolve(new Uint8Array(0).buffer)},
    verify:function(){return Promise.resolve(true)}
  };
  var _fetch=window.fetch;
  window.fetch=function(url,opt){
    if(opt&&opt.headers){
      var h=opt.headers;
      var enc=h['X-Encrypted']||h['x-encrypted'];
      if(enc){
        var nh={};for(var k in h)nh[k]=h[k];
        delete nh['X-Encrypted'];delete nh['x-encrypted'];
        delete nh['X-Session-Id'];delete nh['x-session-id'];
        nh['Content-Type']='application/json';
        var body=opt.body;
        try{var p=JSON.parse(body);if(p&&p.data&&p.iv&&p.tag)body='{}';}catch(e){}
        return _fetch.call(this,url,Object.assign({},opt,{headers:nh,body:body}));
      }
    }
    return _fetch.apply(this,arguments);
  };
})();
</script>
JS;

        return $js;
    }

    private function uploadSafetyScript(): string
    {
        return <<<'JS'
<script>
(function(){
  function createNotice(){
    if(document.getElementById('upload-safety-notice'))return;
    var target=document.querySelector('form')||document.querySelector('main')||document.body;
    var box=document.createElement('div');
    box.id='upload-safety-notice';
    box.style.cssText='margin:12px 0;padding:12px 14px;border:1px solid #fed7aa;border-radius:12px;background:#fff7ed;color:#7c2d12;font-size:13px;line-height:1.6';
    box.innerHTML='<strong style="display:block;margin-bottom:4px">上传安全提示</strong><div>请上传合法文件。系统会拦截恶意代码、后门、钓鱼文件、可执行脚本和高风险双扩展名文件。</div><div id="upload-risk-hint" style="display:none;margin-top:6px;color:#b91c1c;font-weight:700"></div>';
    target.parentNode?target.parentNode.insertBefore(box,target):document.body.insertBefore(box,document.body.firstChild);
  }
  function updateHint(file){
    var hint=document.getElementById('upload-risk-hint');
    if(!hint||!file)return;
    var name=(file.name||'').toLowerCase();
    var risky=['php','phtml','phar','jsp','asp','aspx','exe','bat','cmd','ps1','sh','jar','scr','com'];
    var parts=name.split('.');
    var ext=parts.length>1?parts.pop():'';
    var doubleExt=parts.length>1;
    if(risky.indexOf(ext)>=0||doubleExt){
      hint.style.display='block';
      hint.textContent='当前文件类型风险较高，上传后将进入安全扫描和风控校验。';
    }else{
      hint.style.display='none';
      hint.textContent='';
    }
  }
  function bind(){
    document.documentElement.classList.add('fe-home-enhanced');
    createNotice();
    document.querySelectorAll('input[type="file"]').forEach(function(input){
      if(input.dataset.safetyBound)return;
      input.dataset.safetyBound='1';
      input.addEventListener('change',function(){updateHint(input.files&&input.files[0]);});
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',bind);else bind();
})();
</script>
JS;
    }

    private function uploadHistoryScript(): string
    {
        return <<<'JS'
<script>
(function(){
  if(window.__yeyuUploadHistoryBound || !window.fetch) return;
  window.__yeyuUploadHistoryBound = true;
  var originalFetch = window.fetch;
  var historyKey = 'yeyu_upload_history';

  function isUploadRequest(input, init){
    try{
      var method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
      var rawUrl = typeof input === 'string' ? input : (input && input.url) || '';
      var url = new URL(rawUrl, location.origin);
      return method === 'POST' && url.pathname === '/api/v1/files';
    }catch(error){
      return false;
    }
  }

  function readHistory(){
    try{
      var value = JSON.parse(localStorage.getItem(historyKey) || '[]');
      if(Array.isArray(value)) return value;
      if(value && Array.isArray(value.list)) return value.list;
      if(value && Array.isArray(value.files)) return value.files;
    }catch(error){}
    return [];
  }

  function saveUpload(row){
    if(!row || !row.code) return;
    var code = String(row.code || '').toUpperCase();
    if(!/^[A-Z0-9]{4,12}$/.test(code)) return;
    var next = {
      code: code,
      shareCode: code,
      ownerToken: String(row.ownerToken || row.owner_token || ''),
      originalName: String(row.originalName || row.original_name || row.name || ''),
      size: Number(row.size || 0),
      mimeType: String(row.mimeType || row.mime_type || ''),
      hasExtractCode: !!row.hasExtractCode,
      expiresAt: row.expiresAt || null,
      uploadedAt: row.uploadedAt || Date.now(),
      status: row.status || 'active',
      shareUrl: location.origin + '/files/' + code,
      savedAt: Date.now()
    };
    var rows = readHistory().filter(function(item){
      return String(item.code || item.shareCode || item.share_code || '').toUpperCase() !== code;
    });
    rows.unshift(next);
    localStorage.setItem(historyKey, JSON.stringify(rows.slice(0, 100)));
  }

  window.fetch = function(input, init){
    var shouldCapture = isUploadRequest(input, init);
    return originalFetch.apply(this, arguments).then(function(response){
      if(shouldCapture && response && response.ok){
        response.clone().json().then(function(json){
          var data = json && json.data ? json.data : null;
          if(data) saveUpload(data);
        }).catch(function(){});
      }
      return response;
    });
  };
})();
</script>
JS;
    }

    private function homeMyFilesLinkScript(): string
    {
        return <<<'JS'
<script>
(function(){
  function createIcon(){
    return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M10 12h4"></path><path d="M10 16h4"></path></svg>';
  }

  function addLink(){
    if(document.getElementById('home-my-files-link')) return true;
    var nodes = Array.prototype.slice.call(document.querySelectorAll('button, a'));
    var historyButton = nodes.find(function(node){ return /历史记录/.test((node.textContent || '').trim()); });
    if(!historyButton || !historyButton.parentNode) return false;

    var link = document.createElement('a');
    link.id = 'home-my-files-link';
    link.href = '/my-files';
    link.className = historyButton.className || '';
    link.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;min-height:32px;padding:0 12px;border-radius:10px;background:rgba(255,255,255,.62);color:#374151;border:1px solid rgba(255,255,255,.65);';
    link.innerHTML = createIcon() + '<span>我的文件</span>';
    historyButton.insertAdjacentElement('afterend', link);
    return true;
  }

  function addFallbackLink(){
    if(document.getElementById('home-my-files-link') || document.getElementById('home-my-files-fallback-link')) return;
    var main = document.querySelector('main') || document.body;
    var link = document.createElement('a');
    link.id = 'home-my-files-fallback-link';
    link.href = '/my-files';
    link.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;gap:8px;margin:0 0 14px 0;min-height:42px;padding:0 16px;border-radius:14px;background:#2563eb;color:#fff;font-weight:850;text-decoration:none;box-shadow:0 18px 42px -28px rgba(37,99,235,.72);';
    link.innerHTML = createIcon() + '<span>我的文件</span>';
    main.insertBefore(link, main.firstChild);
  }

  function bind(){
    addLink();
    var observer = new MutationObserver(function(){ addLink(); });
    observer.observe(document.body, { childList: true, subtree: true });
    var timer = setInterval(addLink, 500);
    setTimeout(addFallbackLink, 1800);
    setTimeout(function(){ clearInterval(timer); observer.disconnect(); }, 30000);
  }

  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})();
</script>
JS;
    }

    private function homeSharePanelScript(): string
    {
        return <<<'JS'
<script>
(function(){
  function firstText(selectors){for(var i=0;i<selectors.length;i++){var node=document.querySelector(selectors[i]);if(node)return node.textContent.trim();}return '';}

  function createPanel(){
    if(document.getElementById('upload-share-panel')) return;
    var shareCode=firstText(['[data-upload-share-code]','[data-share-code]','.share-code strong']);
    var shareUrl=firstText(['[data-upload-share-url]','[data-share-url]','input[readonly]']);
    var target=document.querySelector('main')||document.querySelector('body');
    if(!target) return;

    var panel=document.createElement('section');
    panel.id='upload-share-panel';
    panel.style.cssText='margin:16px 0;padding:16px;border:1px solid rgba(148,163,184,.22);border-radius:18px;background:rgba(255,255,255,.72);backdrop-filter:blur(16px);box-shadow:0 18px 48px -34px rgba(15,23,42,.45)';
    panel.innerHTML='\
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">\
        <div>\
          <strong style="display:block;font-size:16px;color:#0f172a;margin-bottom:4px">上传完成后可直接分享</strong>\
          <div style="color:#64748b;font-size:13px;line-height:1.6">复制链接、提取码和二维码，适合微信和 QQ 内置浏览器转发。</div>\
        </div>\
        <span style="padding:6px 10px;border-radius:999px;background:rgba(59,130,246,.10);color:#1d4ed8;font-size:12px;font-weight:800">快速分享</span>\
      </div>\
      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px">\
        <button type="button" data-share-copy class="mobile-primary-action" style="background:#1d4ed8;color:#fff;border:0;border-radius:14px;padding:0 14px;min-height:48px">复制分享文案</button>\
        <button type="button" data-share-qrcode class="mobile-primary-action" style="background:#0f766e;color:#fff;border:0;border-radius:14px;padding:0 14px;min-height:48px">查看二维码</button>\
      </div>\
      <div style="margin-top:12px;display:grid;grid-template-columns:1fr;gap:8px">\
        <div style="font-size:13px;color:#475569;word-break:break-all" data-share-preview>'+(shareUrl ? shareUrl : '上传后会在这里显示分享链接。')+'</div>\
        <div style="font-size:12px;color:#64748b" data-share-meta>'+(shareCode ? '当前分享码：'+shareCode : '上传成功后会显示分享码和提取码。')+'</div>\
      </div>\
      <div data-share-qrcode-box hidden style="margin-top:14px;padding:14px;border-radius:16px;background:rgba(15,23,42,.03);text-align:center">\
        <img alt="分享二维码" data-share-qrcode-img style="width:180px;height:180px;max-width:100%;border-radius:14px;background:#fff;padding:10px;box-shadow:0 12px 28px -20px rgba(15,23,42,.45)">\
        <div style="margin-top:10px;font-size:12px;color:#64748b">二维码可直接发给微信和 QQ 好友</div>\
      </div>';

    if(target === document.body){
      document.body.insertBefore(panel, document.body.firstChild);
    }else{
      (target.parentNode||document.body).insertBefore(panel,target.nextSibling);
    }

    var copyBtn=panel.querySelector('[data-share-copy]');
    var qrcodeBtn=panel.querySelector('[data-share-qrcode]');
    var box=panel.querySelector('[data-share-qrcode-box]');
    var img=panel.querySelector('[data-share-qrcode-img]');
    var preview=panel.querySelector('[data-share-preview]');
    var meta=panel.querySelector('[data-share-meta]');

    function getShareText(){
      var link=shareUrl || location.origin;
      var code=shareCode ? '分享码：'+shareCode+' ' : '';
      return '我给你发了一个文件，'+code+'链接：'+link;
    }

    async function copyText(text){
      if(navigator.clipboard && navigator.clipboard.writeText){
        await navigator.clipboard.writeText(text);
        return;
      }
      var input=document.createElement('textarea');
      input.value=text;
      document.body.appendChild(input);
      input.select();
      document.execCommand('copy');
      input.remove();
    }

    copyBtn.addEventListener('click', async function(){
      try{ await copyText(getShareText()); if(window.__replicaToast) window.__replicaToast('分享文案已复制'); }
      catch(e){ window.prompt('请手动复制分享文案', getShareText()); }
    });
    qrcodeBtn.addEventListener('click', function(){
      var url=shareUrl || location.href;
      img.src='/qr?size=240&margin=10&data='+encodeURIComponent(url);
      box.hidden = !box.hidden;
    });

    if(shareCode || shareUrl){
      preview.textContent = shareUrl || location.href;
      meta.textContent = shareCode ? '当前分享码：'+shareCode : meta.textContent;
    }
  }

  function bind(){
    var hasUploadForm = document.querySelector('input[type="file"], form, button');
    if(hasUploadForm) createPanel();
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})();
</script>
JS;
    }

    private function statusAnnouncementScript(): string
    {
        return <<<'JS'
<script>
(function(){
  if(document.getElementById('status-announcement-panel')) return;
  var target=document.querySelector('main')||document.body;
  if(!target) return;
  var panel=document.createElement('section');
  panel.id='status-announcement-panel';
  panel.style.cssText='margin:16px 0;padding:16px;border:1px solid rgba(59,130,246,.18);border-radius:18px;background:rgba(239,246,255,.82);box-shadow:0 18px 48px -34px rgba(15,23,42,.45)';
  panel.innerHTML='\
    <strong style="display:block;margin-bottom:6px;font-size:16px;color:#0f172a">站点公告</strong>\
    <div style="color:#334155;font-size:13px;line-height:1.7">如需维护通知、限流说明或临时策略更新，后台公告会在这里展示。</div>\
    <div data-status-announcement-list style="margin-top:12px;display:grid;gap:8px"></div>';
  target.insertBefore(panel,target.firstChild);

  fetch('/api/v1/announcements',{headers:{Accept:'application/json'}})
    .then(function(res){ return res.json(); })
    .then(function(json){
      var list = (json && json.data) || [];
      var box = panel.querySelector('[data-status-announcement-list]');
      if(!box) return;
      if(!Array.isArray(list) || !list.length){
        box.innerHTML = '<div style="color:#64748b;font-size:13px">当前没有公告。</div>';
        return;
      }
      box.innerHTML = list.slice(0,3).map(function(item){
        return '<div style="padding:10px 12px;border-radius:14px;background:rgba(255,255,255,.82);border:1px solid rgba(148,163,184,.18)"><strong style="display:block;color:#0f172a;font-size:13px;margin-bottom:4px">'+(item.title || '公告')+'</strong><div style="color:#475569;font-size:12px;line-height:1.6">'+(item.content || '')+'</div></div>';
      }).join('');
    })
    .catch(function(){
      var box = panel.querySelector('[data-status-announcement-list]');
      if(box) box.innerHTML = '<div style="color:#64748b;font-size:13px">公告加载失败。</div>';
    });
})();
</script>
JS;
    }
}
