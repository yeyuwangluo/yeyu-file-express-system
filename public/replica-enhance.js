(() => {
  const selectAll = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const textOf = (node) => (node?.innerText || node?.textContent || "").replace(/\s+/g, " ").trim();
  let lastSuccessCode = "";

  function applyTheme(theme, event) {
    const root = document.documentElement;
    const next = theme === "dark" ? "dark" : "light";
    const update = () => {
      root.classList.remove("light", "dark");
      root.classList.add(next);
      root.style.colorScheme = next;
      localStorage.setItem("theme", next);
      updateThemeButtons();
    };

    if (document.startViewTransition && event?.clientX != null) {
      const transition = document.startViewTransition(update);
      transition.ready.then(() => {
        const x = event.clientX;
        const y = event.clientY;
        const endRadius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
        document.documentElement.animate(
          {
            clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${endRadius}px at ${x}px ${y}px)`],
          },
          {
            duration: 420,
            easing: "ease-out",
            pseudoElement: "::view-transition-new(root)",
          },
        );
      }).catch(() => {});
      return;
    }

    update();
  }

  function updateThemeButtons() {
    const isDark = document.documentElement.classList.contains("dark");
    selectAll('button[aria-label*="切换"], button[aria-label="切换主题"]').forEach((button) => {
      button.disabled = false;
      button.removeAttribute("data-disabled");
      button.setAttribute("aria-label", isDark ? "切换到亮色模式" : "切换到暗色模式");
      if (!button.dataset.replicaThemeButton) {
        button.dataset.replicaThemeButton = "true";
        button.addEventListener("click", (event) => {
          event.preventDefault();
          event.stopImmediatePropagation();
          applyTheme(document.documentElement.classList.contains("dark") ? "light" : "dark", event);
        }, true);
      }
    });
  }

  function closeReplicaModal() {
    document.querySelector(".replica-modal-backdrop")?.remove();
  }

  function showHistoryModal() {
    closeReplicaModal();
    const raw = localStorage.getItem("upload_history");
    let history = [];
    try {
      history = raw ? JSON.parse(raw) : [];
    } catch {
      history = [];
    }

    const rows = history.length
      ? history.map((item) => `
        <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(156,163,175,.25)">
          <div style="min-width:0">
            <strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(item.originalName || item.code || "未命名文件")}</strong>
            <span style="font-size:12px;color:#6b7280">${escapeHtml(item.code || "")} · ${formatSize(item.size || 0)}</span>
          </div>
          <a class="replica-button" style="display:inline-flex;align-items:center;text-decoration:none" href="${escapeHtml(item.shareUrl || "#")}">打开</a>
        </div>
      `).join("")
      : `<div class="replica-empty"><div><strong>暂无上传记录</strong><span>上传文件后会自动保存到这里</span></div></div>`;

    const backdrop = document.createElement("div");
    backdrop.className = "replica-modal-backdrop";
    backdrop.innerHTML = `
      <section class="replica-modal" role="dialog" aria-modal="true" aria-labelledby="replica-history-title">
        <div class="replica-modal__header">
          <h2 class="replica-modal__title" id="replica-history-title">上传历史 <span style="font-size:13px;color:#6b7280">(${history.length} 条记录)</span></h2>
          <button class="replica-modal__close" type="button" aria-label="关闭">×</button>
        </div>
        <div class="replica-modal__body">${rows}</div>
        <div class="replica-modal__footer" style="display:flex;justify-content:flex-end">
          <button class="replica-button" type="button">关闭</button>
        </div>
      </section>
    `;
    backdrop.addEventListener("click", (event) => {
      if (event.target === backdrop || event.target.closest(".replica-modal__close, .replica-button[type='button']")) {
        closeReplicaModal();
      }
    });
    document.body.appendChild(backdrop);
  }

  function setupHistoryFallback() {
    document.addEventListener("click", (event) => {
      const button = event.target.closest("button");
      if (!button || !textOf(button).includes("历史记录")) return;
      setTimeout(() => {
        if (!document.body.innerText.includes("上传历史")) {
          showHistoryModal();
        }
      }, 240);
    }, true);
  }

  function normalizeHomeCopy() {
    if (location.pathname !== "/") return;
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    let node;
    while ((node = walker.nextNode())) {
      if (node.nodeValue.includes("最大支持 100.0 MB")) {
        node.nodeValue = node.nodeValue.replace("最大支持 100.0 MB", "最大支持 50.0 MB，支持 jpg, jpeg, png, gif, webp 等 37 种格式");
      }
    }
  }

  function setupKeyboard() {
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closeReplicaModal();
    });
  }

  function toast(message) {
    document.querySelector(".replica-toast")?.remove();
    const el = document.createElement("div");
    el.className = "replica-toast";
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2600);
  }

  function captureUploadSuccessFallback() {
    document.addEventListener("click", (event) => {
      const button = event.target.closest("button");
      if (!button || textOf(button) !== "开始上传") return;
      setTimeout(() => {
        if (document.body.innerText.includes("上传成功")) {
          persistVisibleUploadHistory();
        }
      }, 1200);
    }, true);
  }

  function routeShareLinks() {
    document.addEventListener("click", (event) => {
      const link = event.target.closest('a[href^="/files/"], a[href^="' + location.origin + '/files/"]');
      if (!link) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      window.location.href = link.href;
    }, true);
  }

  function persistVisibleUploadHistory() {
    const body = document.body.innerText || "";
    const originPattern = new RegExp(escapeRegExp(location.origin) + "/files/([A-Z0-9]+)");
    const match = body.match(originPattern) || body.match(/\/files\/([A-Z0-9]+)/);
    if (!match || match[1] === lastSuccessCode) return;
    lastSuccessCode = match[1];
    const lines = body.split(/\n+/).map((line) => line.trim()).filter(Boolean);
    const fileName = valueAfter(lines, "文件名") || `文件-${match[1]}`;
    const sizeText = valueAfter(lines, "大小") || "0 B";
    const expireText = valueAfter(lines, "过期时间") || "";
    const item = {
      code: match[1],
      originalName: fileName,
      size: parseSize(sizeText),
      shareUrl: `${location.origin}/files/${match[1]}`,
      hasExtractCode: body.includes("提取码") && !body.includes("提取码 无需"),
      status: "active",
      expiresAt: expireText ? Date.parse(expireText.replace(/\//g, "-")) || Date.now() + 86400000 : Date.now() + 86400000,
      uploadedAt: Date.now(),
    };
    let history = [];
    try {
      history = JSON.parse(localStorage.getItem("upload_history") || "[]");
    } catch {
      history = [];
    }
    history = [item, ...history.filter((entry) => entry.code !== item.code)].slice(0, 20);
    localStorage.setItem("upload_history", JSON.stringify(history));
  }

  function valueAfter(lines, label) {
    const index = lines.findIndex((line) => line === label);
    return index >= 0 ? lines[index + 1] || "" : "";
  }

  function parseSize(value) {
    const match = String(value).match(/^([\d.]+)\s*(B|KB|MB|GB)$/i);
    if (!match) return 0;
    const base = Number(match[1]) || 0;
    const unit = match[2].toUpperCase();
    const scale = { B: 1, KB: 1024, MB: 1024 ** 2, GB: 1024 ** 3 }[unit] || 1;
    return Math.round(base * scale);
  }

  function patchExternalFooterImages() {
    selectAll('img[src*="beian.mps.gov.cn/web/assets/logo01"]').forEach((image) => {
      image.referrerPolicy = "no-referrer";
      image.addEventListener("error", () => {
        const fallback = document.createElement("span");
        fallback.textContent = "公安";
        fallback.style.cssText = "display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:4px;background:rgba(107,114,128,.16);font-size:10px;font-weight:700";
        image.replaceWith(fallback);
      }, { once: true });
    });
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (char) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    }[char]));
  }

  function escapeRegExp(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  function formatSize(size) {
    if (!size) return "0 B";
    const units = ["B", "KB", "MB", "GB"];
    let value = size;
    let index = 0;
    while (value >= 1024 && index < units.length - 1) {
      value /= 1024;
      index += 1;
    }
    return `${value.toFixed(value >= 10 || index === 0 ? 0 : 1)} ${units[index]}`;
  }

  function init() {
    setupHistoryFallback();
    setupKeyboard();
    captureUploadSuccessFallback();
    routeShareLinks();

    setTimeout(() => {
      updateThemeButtons();
      normalizeHomeCopy();
      let ticks = 0;
      const interval = setInterval(() => {
        updateThemeButtons();
        normalizeHomeCopy();
        patchExternalFooterImages();
        ticks += 1;
        if (ticks > 20) clearInterval(interval);
      }, 500);
    }, 1800);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
