<x-files.shell title="我的文件 - 叶宇文件快递" pageClass="my-files-page">
  <section class="file-hero">
    <div class="file-state-row"><span class="badge">本机记录</span><span class="badge">批量分享</span></div>
    <h1>我的文件</h1>
    <p class="muted">这里读取当前浏览器保存的上传记录。你可以同步状态、打开分享页，或选中多个文件生成批量分享链接。</p>
  </section>

  <div class="actions my-files-primary-actions">
    <button type="button" id="sync-files">同步状态</button>
    <button type="button" id="create-batch">生成批量分享</button>
    <button type="button" id="copy-selected">复制选中链接</button>
    <button type="button" id="export-selected" class="secondary-action">导出列表</button>
    <button type="button" id="remove-selected" class="secondary-action">移除本机记录</button>
    <button type="button" id="extend-selected" class="secondary-action">批量续期 7 天</button>
    <button type="button" id="extend-expiring" class="secondary-action">续期即将过期</button>
    <button type="button" id="extract-selected" class="secondary-action">批量提取码</button>
    <a class="secondary-link" href="/">继续上传</a>
  </div>
  <div class="my-files-filter-row">
    <input id="file-filter" placeholder="按文件名或分享码筛选">
    <select id="status-filter"><option value="">全部状态</option><option value="active">可用</option><option value="favorite">已收藏</option><option value="expired">已过期</option><option value="deleted">已删除</option><option value="blocked">已封禁</option></select>
    <select id="sort-filter"><option value="uploaded_desc">最新上传</option><option value="expires_asc">最快过期</option><option value="downloads_desc">下载最多</option><option value="name_asc">文件名 A-Z</option></select>
  </div>
  <div class="my-files-filter-row my-files-batch-row">
    <input id="batch-title" maxlength="80" placeholder="批量分享标题，可选">
    <input id="batch-description" maxlength="500" placeholder="批量分享说明，可选">
    <select id="batch-cover"><option value="">自动封面</option></select>
    <select id="batch-expires"><option value="7">7 天有效</option><option value="14">14 天有效</option><option value="30">30 天有效</option></select>
  </div>

  <div id="my-files-status" class="file-callout safe">正在读取本机上传记录。</div>
  <div class="scroll mobile-cards my-files-table-wrap">
    <table class="my-files-table">
      <thead><tr><th></th><th>文件</th><th>分享码</th><th>状态</th><th>大小</th><th>下载</th><th>过期</th><th>操作</th></tr></thead>
      <tbody id="my-files-list"><tr><td colspan="8" class="muted">暂无记录</td></tr></tbody>
    </table>
  </div>

  <section class="file-callout" style="margin-top:18px;">
    <div class="my-files-section-head">
      <strong>批量分享管理</strong>
      <button type="button" id="sync-batches" class="secondary-action">同步批量分享</button>
    </div>
    <div class="scroll mobile-cards my-files-table-wrap compact">
      <table class="my-files-table batch-table">
        <thead><tr><th>标题</th><th>Token</th><th>文件</th><th>过期</th><th>状态</th><th>操作</th></tr></thead>
        <tbody id="batch-share-list"><tr><td colspan="6" class="muted">暂无本机创建的批量分享。</td></tr></tbody>
      </table>
    </div>
  </section>

  <script>
    (function () {
      var list = document.getElementById('my-files-list');
      var status = document.getElementById('my-files-status');
      var filterInput = document.getElementById('file-filter');
      var statusFilter = document.getElementById('status-filter');
      var sortFilter = document.getElementById('sort-filter');
      var batchTitle = document.getElementById('batch-title');
      var batchDescription = document.getElementById('batch-description');
      var batchCover = document.getElementById('batch-cover');
      var batchExpires = document.getElementById('batch-expires');
      var storageKeys = ['yeyu_upload_history', 'upload_history', 'uploadHistory', 'fileHistory', 'uploadedFiles'];
      var batchStorageKey = 'yeyu_batch_shares';
      var favoriteStorageKey = 'yeyu_file_favorites';
      var currentRows = [];
      var currentBatches = [];
      function readCodes() {
        var codes = [];
        storageKeys.forEach(function (key) {
          try {
            var raw = localStorage.getItem(key);
            if (!raw) return;
            var value = JSON.parse(raw);
            var rows = Array.isArray(value) ? value : (value.list || value.files || []);
            rows.forEach(function (item) {
              var code = String(item.code || item.shareCode || item.share_code || '').toUpperCase();
              if (/^[A-Z0-9]{4,12}$/.test(code)) codes.push(code);
            });
          } catch (error) {}
        });
        return Array.from(new Set(codes));
      }
      function readOwnerTokens() {
        var map = {};
        storageKeys.forEach(function (key) {
          try {
            var raw = localStorage.getItem(key);
            if (!raw) return;
            var value = JSON.parse(raw);
            var rows = Array.isArray(value) ? value : (value.list || value.files || []);
            rows.forEach(function (item) {
              var code = String(item.code || item.shareCode || item.share_code || '').toUpperCase();
              var token = String(item.ownerToken || item.owner_token || item.manageToken || item.manage_token || '');
              if (/^[A-Z0-9]{4,12}$/.test(code) && token) map[code] = token;
            });
          } catch (error) {}
        });
        return map;
      }
      function removeCodes(codes) {
        storageKeys.forEach(function (key) {
          try {
            var raw = localStorage.getItem(key);
            if (!raw) return;
            var value = JSON.parse(raw);
            var rows = Array.isArray(value) ? value : (value.list || value.files || []);
            var next = rows.filter(function (item) {
              var code = String(item.code || item.shareCode || item.share_code || '').toUpperCase();
              return codes.indexOf(code) === -1;
            });
            if (Array.isArray(value)) localStorage.setItem(key, JSON.stringify(next));
            else {
              if (Array.isArray(value.list)) value.list = next;
              if (Array.isArray(value.files)) value.files = next;
              localStorage.setItem(key, JSON.stringify(value));
            }
          } catch (error) {}
        });
      }
      function readFavorites() {
        try { return JSON.parse(localStorage.getItem(favoriteStorageKey) || '[]'); } catch (error) { return []; }
      }
      function saveFavorites(rows) { localStorage.setItem(favoriteStorageKey, JSON.stringify(Array.from(new Set(rows)))); }
      function toggleFavorite(code) {
        var rows = readFavorites();
        rows = rows.indexOf(code) >= 0 ? rows.filter(function (item) { return item !== code; }) : rows.concat([code]);
        saveFavorites(rows);
      }
      function formatSize(size) {
        var units = ['B', 'KB', 'MB', 'GB'];
        var value = Number(size || 0);
        var index = 0;
        while (value >= 1024 && index < units.length - 1) { value /= 1024; index += 1; }
        return (value >= 10 || index === 0 ? value.toFixed(0) : value.toFixed(1)) + ' ' + units[index];
      }
      function readBatchShares() {
        try { return JSON.parse(localStorage.getItem(batchStorageKey) || '[]').filter(function (item) { return item && item.token && item.manageToken; }); } catch (error) { return []; }
      }
      function saveBatchShares(rows) {
        localStorage.setItem(batchStorageKey, JSON.stringify(rows.slice(0, 80)));
      }
      function upsertBatchShare(row) {
        var rows = readBatchShares().filter(function (item) { return item.token !== row.token; });
        rows.unshift(row);
        saveBatchShares(rows);
      }
      function renderBatchShares(rows) {
        currentBatches = rows || [];
        var tbody = document.getElementById('batch-share-list');
        if (!currentBatches.length) { tbody.innerHTML = '<tr><td colspan="6" class="muted">暂无本机创建的批量分享。</td></tr>'; return; }
        tbody.innerHTML = currentBatches.map(function (share) {
          var expires = share.expiresAt ? new Date(share.expiresAt).toLocaleString() : '-';
          var title = String(share.title || '批量文件分享').replace(/[&<>]/g, function (s) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]); });
          var state = share.closed ? '已关闭' : (share.expiresAt && Number(share.expiresAt) <= Date.now() ? '已过期' : '可用');
          return '<tr>'+ 
            '<td data-label="标题">'+title+(share.description ? '<br><span class="muted">'+String(share.description).replace(/[&<>]/g, function (s) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]); })+'</span>' : '')+'</td>'+ 
            '<td data-label="Token"><a href="/batch/'+share.token+'">'+share.token+'</a></td>'+ 
            '<td data-label="文件">'+Number(share.count || 0)+'</td>'+ 
            '<td data-label="过期">'+expires+'</td>'+ 
            '<td data-label="状态"><span class="badge '+(state === '可用' ? '' : 'warning')+'">'+state+'</span></td>'+ 
            '<td data-label="操作"><button type="button" data-copy-batch-one="'+share.token+'">复制</button> · <button type="button" data-edit-batch="'+share.token+'">修改</button> · <button type="button" data-extend-batch="'+share.token+'">续期</button> · <button type="button" data-close-batch="'+share.token+'">关闭</button></td>'+ 
          '</tr>';
        }).join('');
      }
      function visibleRows(rows) {
        var keyword = String(filterInput.value || '').trim().toUpperCase();
        var wantedStatus = String(statusFilter.value || '');
        var filtered = rows.filter(function (file) {
          var matchesKeyword = !keyword || String(file.code || '').toUpperCase().indexOf(keyword) >= 0 || String(file.originalName || '').toUpperCase().indexOf(keyword) >= 0;
          var matchesStatus = !wantedStatus || (wantedStatus === 'favorite' ? !!file.favorite : String(file.status || '') === wantedStatus);
          return matchesKeyword && matchesStatus;
        });
        var sort = String(sortFilter.value || 'uploaded_desc');
        return filtered.sort(function (a, b) {
          if (sort === 'expires_asc') return Number(a.expiresAt || 9999999999999) - Number(b.expiresAt || 9999999999999);
          if (sort === 'downloads_desc') return Number(b.downloadCount || 0) - Number(a.downloadCount || 0);
          if (sort === 'name_asc') return String(a.originalName || '').localeCompare(String(b.originalName || ''));
          return Number(b.uploadedAt || 0) - Number(a.uploadedAt || 0);
        });
      }
      function render(rows) {
        rows = visibleRows(rows);
        if (!rows.length) {
        list.innerHTML = '<tr><td colspan="8" class="muted">当前浏览器没有上传记录。上传后会在这里显示。</td></tr>';
          return;
        }
        list.innerHTML = rows.map(function (file) {
          var exists = file.exists !== false;
          var expires = file.expiresAt ? new Date(file.expiresAt).toLocaleString() : '-';
          var lastDownloaded = file.lastDownloadedAt ? new Date(file.lastDownloadedAt).toLocaleString() : '暂无';
          var downloads24h = Number(file.downloads24h || 0);
          var spreadWarn = file.spreadWarning ? '<br><span class="badge warning">传播异常</span>' : '';
          return '<tr>'+
            '<td data-label="选择"><input type="checkbox" data-code="'+file.code+'" '+(exists ? '' : 'disabled')+'></td>'+            
            '<td data-label="文件"><button type="button" data-favorite-one="'+file.code+'" class="secondary-action">'+(file.favorite ? '已收藏' : '收藏')+'</button> '+String(file.originalName || '-').replace(/[&<>]/g, function (s) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]); })+(file.note ? '<br><span class="muted">备注：'+String(file.note).replace(/[&<>]/g, function (s) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]); })+'</span>' : '')+'</td>'+            
            '<td data-label="分享码"><a href="/files/'+file.code+'">'+file.code+'</a></td>'+            
            '<td data-label="状态"><span class="badge '+(exists ? '' : 'warning')+'">'+(file.status || 'unknown')+'</span>'+(Number(file.daysUntilExpiry) >= 0 && Number(file.daysUntilExpiry) <= 2 ? '<br><span class="badge warning">即将过期</span>' : '')+'</td>'+            
            '<td data-label="大小">'+formatSize(file.size)+'</td>'+            
            '<td data-label="下载">'+Number(file.downloadCount || 0)+'<br><span class="muted">24h '+downloads24h+' · 最近 '+lastDownloaded+'</span>'+spreadWarn+'</td>'+            
            '<td data-label="过期">'+expires+'</td>'+            
            '<td data-label="操作"><a href="/files/'+file.code+'">打开</a> · <button type="button" data-copy-one="'+file.code+'">复制</button>'+            
            (file.ownerToken ? ' · <button type="button" data-extend-one="'+file.code+'">续期</button> · <button type="button" data-code-one="'+file.code+'">提取码</button> · <button type="button" data-note-one="'+file.code+'">备注</button> · <button type="button" data-delete-one="'+file.code+'">删除分享</button>' : ' · <span class="muted">无管理凭证</span>')+
            ' · <button type="button" data-remove-one="'+file.code+'">移除本机记录</button></td>'+            
          '</tr>';
        }).join('');
      }
      async function sync() {
        var codes = readCodes();
        if (!codes.length) { render([]); status.textContent = '没有找到本机上传记录。'; return; }
        status.textContent = '正在同步 '+codes.length+' 个分享码。';
        var response = await fetch('/api/v1/files/history-sync', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ codes: codes }) });
        var result = await response.json();
        var rows = result && result.data ? result.data.list || [] : [];
        var ownerTokens = readOwnerTokens();
        var favorites = readFavorites();
        rows.forEach(function (row) { row.ownerToken = ownerTokens[row.code] || ''; row.favorite = favorites.indexOf(row.code) >= 0; });
        currentRows = rows;
        batchCover.innerHTML = '<option value="">自动封面</option>' + rows.filter(function (file) { return String(file.mimeType || '').indexOf('image/') === 0; }).map(function (file) { return '<option value="'+file.code+'">'+file.code+' · '+String(file.originalName || '').replace(/[&<>]/g, function (s) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]); })+'</option>'; }).join('');
        render(currentRows);
        var expiring = rows.filter(function (file) { return file.ownerToken && Number(file.daysUntilExpiry) >= 0 && Number(file.daysUntilExpiry) <= 2; }).length;
        status.textContent = '同步完成，可选择多个文件生成批量分享。即将过期可续期文件：' + expiring + ' 个。';
      }
      async function createBatch() {
        var codes = Array.from(document.querySelectorAll('[data-code]:checked')).map(function (input) { return input.dataset.code; });
        if (codes.length < 2) { status.textContent = '至少选择 2 个文件。'; return; }
        var response = await fetch('/api/v1/files/batch-share', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ codes: codes, title: batchTitle.value, description: batchDescription.value, coverCode: batchCover.value, expiresDays: Number(batchExpires.value || 7) }) });
        var result = await response.json();
        if (!response.ok || !result.data) { status.textContent = result.message || '批量分享生成失败。'; return; }
        if (result.data.manageToken) upsertBatchShare({ token: result.data.token, manageToken: result.data.manageToken, title: batchTitle.value, description: batchDescription.value, count: result.data.count, expiresAt: result.data.expiresAt, url: result.data.url });
        await syncBatchShares();
        status.innerHTML = '批量分享已生成：<a href="'+result.data.url+'">'+location.origin+result.data.url+'</a>';
      }
      async function syncBatchShares() {
        var shares = readBatchShares();
        if (!shares.length) { renderBatchShares([]); return; }
        var response = await fetch('/api/v1/files/batch-shares/owner', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ shares: shares }) });
        var result = await response.json();
        var map = {};
        shares.forEach(function (item) { map[item.token] = item.manageToken; });
        var rows = result && result.data ? result.data.shares || [] : [];
        rows.forEach(function (item) { item.manageToken = map[item.token] || ''; });
        saveBatchShares(rows.filter(function (item) { return item.manageToken; }).map(function (item) { return { token: item.token, manageToken: item.manageToken, title: item.title, description: item.description, count: item.count, expiresAt: item.expiresAt, url: item.url, closed: item.closed }; }));
        renderBatchShares(rows);
      }
      function exportRows(rows) {
        var selected = selectedCodes();
        var source = selected.length ? rows.filter(function (file) { return selected.indexOf(file.code) >= 0; }) : visibleRows(rows);
        if (!source.length) { status.textContent = '没有可导出的文件。'; return; }
        var lines = [['分享码','文件名','状态','大小','下载次数','过期时间','备注','链接'].join(',')];
        source.forEach(function (file) {
          var row = [file.code, file.originalName || '', file.status || '', formatSize(file.size), Number(file.downloadCount || 0), file.expiresAt ? new Date(file.expiresAt).toLocaleString() : '', file.note || '', location.origin + '/files/' + file.code];
          lines.push(row.map(function (value) { return '"' + String(value).replace(/"/g, '""') + '"'; }).join(','));
        });
        var blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'my-files-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(link);
        link.click();
        URL.revokeObjectURL(link.href);
        link.remove();
        status.textContent = '已导出 ' + source.length + ' 条文件记录。';
      }
      function selectedCodes() {
        return Array.from(document.querySelectorAll('[data-code]:checked')).map(function (input) { return input.dataset.code; });
      }
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
      document.getElementById('sync-files').addEventListener('click', sync);
      document.getElementById('sync-batches').addEventListener('click', syncBatchShares);
      document.getElementById('create-batch').addEventListener('click', createBatch);
      document.getElementById('copy-selected').addEventListener('click', async function () {
        var codes = selectedCodes();
        if (!codes.length) { status.textContent = '请先选择文件。'; return; }
        await copyText(codes.map(function (code) {
          var row = currentRows.find(function (file) { return file.code === code; });
          return (row && row.note ? row.note + '：' : '') + location.origin + '/files/' + code;
        }).join('\n'));
        status.textContent = '已复制 '+codes.length+' 个分享链接。';
      });
      document.getElementById('export-selected').addEventListener('click', function () { exportRows(currentRows); });
      document.getElementById('remove-selected').addEventListener('click', function () {
        var codes = selectedCodes();
        if (!codes.length) { status.textContent = '请先选择文件。'; return; }
        if (!confirm('确认从当前浏览器移除选中的本机记录？服务器文件不会被删除。')) return;
        removeCodes(codes);
        currentRows = currentRows.filter(function (file) { return codes.indexOf(file.code) === -1; });
        render(currentRows);
        status.textContent = '已移除 '+codes.length+' 条本机记录，服务器文件保持不变。';
      });
      document.getElementById('extend-selected').addEventListener('click', async function () {
        var codes = selectedCodes();
        var files = currentRows.filter(function (file) { return codes.indexOf(file.code) >= 0 && file.ownerToken; }).map(function (file) { return { code: file.code, ownerToken: file.ownerToken }; });
        if (!files.length) { status.textContent = '选中文件没有可用管理凭证。'; return; }
        var response = await fetch('/api/v1/files/batch-owner/extend', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ files: files, days: 7 }) });
        var result = await response.json();
        status.textContent = result.message || '批量续期已提交。';
        await sync();
      });
      document.getElementById('extend-expiring').addEventListener('click', async function () {
        var files = currentRows.filter(function (file) { return file.ownerToken && Number(file.daysUntilExpiry) >= 0 && Number(file.daysUntilExpiry) <= 2; }).map(function (file) { return { code: file.code, ownerToken: file.ownerToken }; });
        if (!files.length) { status.textContent = '当前没有可续期的即将过期文件。'; return; }
        var response = await fetch('/api/v1/files/batch-owner/extend', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ files: files, days: 7 }) });
        var result = await response.json();
        status.textContent = result.message || '即将过期文件已续期。';
        await sync();
      });
      document.getElementById('extract-selected').addEventListener('click', async function () {
        var codes = selectedCodes();
        var files = currentRows.filter(function (file) { return codes.indexOf(file.code) >= 0 && file.ownerToken; }).map(function (file) { return { code: file.code, ownerToken: file.ownerToken }; });
        if (!files.length) { status.textContent = '选中文件没有可用管理凭证。'; return; }
        var nextCode = prompt('输入新的统一提取码，留空表示关闭提取码。');
        if (nextCode === null) return;
        var response = await fetch('/api/v1/files/batch-owner/extract-code', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ files: files, extractCode: nextCode }) });
        var result = await response.json();
        status.textContent = result.message || '批量提取码已更新。';
        await sync();
      });
      list.addEventListener('click', async function (event) {
        var copyCode = event.target && event.target.dataset ? event.target.dataset.copyOne : '';
        var removeCode = event.target && event.target.dataset ? event.target.dataset.removeOne : '';
        var extendCode = event.target && event.target.dataset ? event.target.dataset.extendOne : '';
        var codeCode = event.target && event.target.dataset ? event.target.dataset.codeOne : '';
        var noteCode = event.target && event.target.dataset ? event.target.dataset.noteOne : '';
        var deleteCode = event.target && event.target.dataset ? event.target.dataset.deleteOne : '';
        var favoriteCode = event.target && event.target.dataset ? event.target.dataset.favoriteOne : '';
        function findRow(code) { return currentRows.find(function (file) { return file.code === code; }); }
        if (favoriteCode) {
          toggleFavorite(favoriteCode);
          currentRows.forEach(function (file) { if (file.code === favoriteCode) file.favorite = !file.favorite; });
          render(currentRows);
          status.textContent = '收藏状态已更新：' + favoriteCode;
        }
        if (copyCode) {
          await copyText(location.origin + '/files/' + copyCode);
          status.textContent = '已复制分享链接：' + copyCode;
        }
        if (removeCode) {
          if (!confirm('确认从当前浏览器移除这条本机记录？服务器文件不会被删除。')) return;
          removeCodes([removeCode]);
          currentRows = currentRows.filter(function (file) { return file.code !== removeCode; });
          render(currentRows);
          status.textContent = '已移除本机记录：' + removeCode;
        }
        if (extendCode) {
          var row = findRow(extendCode);
          if (!row || !row.ownerToken) { status.textContent = '缺少管理凭证。'; return; }
          var extendResp = await fetch('/api/v1/files/'+extendCode+'/owner/extend', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ ownerToken: row.ownerToken, days: 7 }) });
          var extendResult = await extendResp.json();
          status.textContent = extendResult.message || '续期完成。';
          await sync();
        }
        if (codeCode) {
          var codeRow = findRow(codeCode);
          if (!codeRow || !codeRow.ownerToken) { status.textContent = '缺少管理凭证。'; return; }
          var nextCode = prompt('输入新提取码，留空表示关闭提取码。');
          if (nextCode === null) return;
          var codeResp = await fetch('/api/v1/files/'+codeCode+'/owner/extract-code', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ ownerToken: codeRow.ownerToken, extractCode: nextCode }) });
          var codeResult = await codeResp.json();
          status.textContent = codeResult.message || '提取码已更新。';
          await sync();
        }
        if (noteCode) {
          var noteRow = findRow(noteCode);
          if (!noteRow || !noteRow.ownerToken) { status.textContent = '缺少管理凭证。'; return; }
          var nextNote = prompt('输入分享备注，留空表示清空备注。', noteRow.note || '');
          if (nextNote === null) return;
          var noteResp = await fetch('/api/v1/files/'+noteCode+'/owner/meta', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ ownerToken: noteRow.ownerToken, note: nextNote }) });
          var noteResult = await noteResp.json();
          status.textContent = noteResult.message || '备注已更新。';
          await sync();
        }
        if (deleteCode) {
          var deleteRow = findRow(deleteCode);
          if (!deleteRow || !deleteRow.ownerToken) { status.textContent = '缺少管理凭证。'; return; }
          if (!confirm('确认删除这个分享？删除后公开链接会失效，源文件保留审计记录。')) return;
          var deleteResp = await fetch('/api/v1/files/'+deleteCode+'/owner/delete', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ ownerToken: deleteRow.ownerToken }) });
          var deleteResult = await deleteResp.json();
          status.textContent = deleteResult.message || '分享已删除。';
          await sync();
        }
      });
      document.getElementById('batch-share-list').addEventListener('click', async function (event) {
        var target = event.target && event.target.dataset ? event.target.dataset : {};
        var token = target.copyBatchOne || target.editBatch || target.extendBatch || target.closeBatch || '';
        if (!token) return;
        var row = currentBatches.find(function (item) { return item.token === token; }) || readBatchShares().find(function (item) { return item.token === token; });
        if (!row || !row.manageToken) { status.textContent = '缺少批量分享管理凭证。'; return; }
        if (target.copyBatchOne) {
          await copyText(location.origin + '/batch/' + token);
          status.textContent = '已复制批量分享链接。';
        }
        if (target.editBatch) {
          var title = prompt('批量分享标题', row.title || '');
          if (title === null) return;
          var description = prompt('批量分享说明', row.description || '');
          if (description === null) return;
          var editResp = await fetch('/api/v1/files/batch-shares/'+token+'/owner/update', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ manageToken: row.manageToken, title: title, description: description }) });
          var editResult = await editResp.json();
          status.textContent = editResult.message || '批量分享已更新。';
          await syncBatchShares();
        }
        if (target.extendBatch) {
          var extendResp = await fetch('/api/v1/files/batch-shares/'+token+'/owner/extend', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ manageToken: row.manageToken, days: 7 }) });
          var extendResult = await extendResp.json();
          status.textContent = extendResult.message || '批量分享已续期。';
          await syncBatchShares();
        }
        if (target.closeBatch) {
          if (!confirm('确认关闭这个批量分享？批量链接会失效，单个文件分享保持原状态。')) return;
          var closeResp = await fetch('/api/v1/files/batch-shares/'+token+'/owner/close', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ manageToken: row.manageToken }) });
          var closeResult = await closeResp.json();
          status.textContent = closeResult.message || '批量分享已关闭。';
          await syncBatchShares();
        }
      });
      filterInput.addEventListener('input', function () { render(currentRows); });
      statusFilter.addEventListener('change', function () { render(currentRows); });
      sortFilter.addEventListener('change', function () { render(currentRows); });
      sync();
      syncBatchShares();
    })();
  </script>
</x-files.shell>
