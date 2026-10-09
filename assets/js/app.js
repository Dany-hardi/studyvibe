/**
 * StudyVibe — app.js
 * Toast notification system + Dark mode toggle
 * Vanilla JS, aucune dépendance externe.
 */

/* ── DARK MODE ──────────────────────────────────────────── */

function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/* ── MARKDOWN & LATEX TYPESETTING RENDERER ───────────────── */

/**
 * Renders raw string content containing Markdown and LaTeX (KaTeX) into formatted HTML.
 * @param {string} text - Raw input text containing Markdown formatting & LaTeX formulas ($...$ or $$...$$).
 * @return {string} Formatted HTML ready to be inserted into DOM.
 */
function renderMarkdownAndMath(text) {
  if (!text || typeof text !== 'string') return '';

  // Fallback if marked is not loaded
  if (typeof marked === 'undefined') {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/\n/g, '<br>');
  }

  const mathTokens = [];
  let tokenIndex = 0;

  // 1. Preserve display math $$ ... $$ and \[ ... \]
  let processed = text.replace(/(\$\$[\s\S]+?\$\$|\\\[[\s\S]+?\\\])/g, function(match) {
    const token = `___MATH_DISPLAY_${tokenIndex++}___`;
    mathTokens.push({ token, code: match, display: true });
    return token;
  });

  // 2. Preserve inline math $ ... $ (excluding escaped \$ and plain currency)
  processed = processed.replace(/(?<!\\)\$([^\$\n]+?)(?<!\\)\$/g, function(match, inner) {
    if (/^\s*\d+(\.\d+)?\s*$/.test(inner) && !/[a-zA-Z\\+\-*\/=^_{}]/.test(inner)) {
      return match;
    }
    const token = `___MATH_INLINE_${tokenIndex++}___`;
    mathTokens.push({ token, code: match, display: false });
    return token;
  });

  // 3. Preserve inline math \( ... \)
  processed = processed.replace(/\\\(([\s\S]+?)\\\)/g, function(match) {
    const token = `___MATH_INLINE_${tokenIndex++}___`;
    mathTokens.push({ token, code: match, display: false });
    return token;
  });

  // Configure marked if available
  try {
    if (typeof marked.setOptions === 'function') {
      marked.setOptions({
        gfm: true,
        breaks: true,
        headerIds: false,
        mangle: false
      });
    }
  } catch (e) {}

  // Parse Markdown to HTML
  let html = '';
  try {
    html = (typeof marked.parse === 'function') ? marked.parse(processed) : marked(processed);
  } catch (e) {
    const div = document.createElement('div');
    div.textContent = processed;
    html = div.innerHTML.replace(/\n/g, '<br>');
  }

  // Restore and render KaTeX math tokens
  mathTokens.forEach(item => {
    let mathStr = item.code;
    if (item.display) {
      if (mathStr.startsWith('$$') && mathStr.endsWith('$$')) mathStr = mathStr.slice(2, -2);
      else if (mathStr.startsWith('\\[') && mathStr.endsWith('\\]')) mathStr = mathStr.slice(2, -2);
    } else {
      if (mathStr.startsWith('$') && mathStr.endsWith('$')) mathStr = mathStr.slice(1, -1);
      else if (mathStr.startsWith('\\(') && mathStr.endsWith('\\)')) mathStr = mathStr.slice(2, -2);
    }

    let rendered = item.code;
    if (typeof katex !== 'undefined' && typeof katex.renderToString === 'function') {
      try {
        rendered = katex.renderToString(mathStr, {
          displayMode: item.display,
          throwOnError: false
        });
      } catch (err) {
        rendered = `<span class="katex-error text-red-500 font-mono text-xs">${item.code}</span>`;
      }
    }

    const replacement = item.display
      ? `<div class="katex-display-container my-3 py-1 overflow-x-auto text-center">${rendered}</div>`
      : `<span class="katex-inline-container inline-block px-0.5">${rendered}</span>`;

    if (item.display && html.includes(`<p>${item.token}</p>`)) {
      html = html.replace(`<p>${item.token}</p>`, replacement);
    } else {
      html = html.replace(item.token, replacement);
    }
  });

  return html;
}


const DarkMode = (() => {
  const KEY = 'sv_dark';
  const root = document.documentElement;

  function apply(isDark) {
    isDark ? root.classList.add('dark') : root.classList.remove('dark');
    document.querySelectorAll('[data-dark-toggle]').forEach(btn => {
      btn.setAttribute('aria-checked', isDark ? 'true' : 'false');
      btn.title = isDark ? 'Passer en mode clair' : 'Passer en mode sombre';
    });
  }

  function init() {
    const saved = localStorage.getItem(KEY);
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    apply(saved !== null ? saved === '1' : prefersDark);
  }

  function toggle() {
    const isDark = root.classList.toggle('dark');
    localStorage.setItem(KEY, isDark ? '1' : '0');
    apply(isDark);
  }

  document.addEventListener('DOMContentLoaded', () => {
    init();
    document.querySelectorAll('[data-dark-toggle]').forEach(btn => {
      btn.addEventListener('click', toggle);
    });
  });

  return { init, toggle, isDark: () => root.classList.contains('dark') };
})();

/* ── TOAST SYSTEM ───────────────────────────────────────── */

const Toast = (() => {
  let container = null;

  function getContainer() {
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      /* A popover sits in the top layer, so toasts stay visible above an open modal <dialog> (login, signup) */
      if ('showPopover' in container) container.setAttribute('popover', 'manual');
      document.body.appendChild(container);
    }
    return container;
  }

  /**
   * Affiche un toast.
   * @param {string} message
   * @param {'success'|'error'|'info'} type
   * @param {number} duration ms avant disparition automatique (0 = permanent)
   */
  function show(message, type = 'success', duration = 4500) {
    const icons = { success: '✓', error: '✕', info: 'ℹ' };
    const el = document.createElement('div');
    el.className = `sv-toast ${type}`;
    el.innerHTML = `
            <span class="toast-icon">${icons[type] ?? '•'}</span>
            <span class="toast-msg">${message}</span>
            <button class="toast-close" aria-label="Fermer">×</button>
        `;

    const close = () => {
      el.classList.add('hiding');
      el.addEventListener('animationend', () => el.remove(), { once: true });
    };

    el.querySelector('.toast-close').addEventListener('click', close);
    if (duration > 0) setTimeout(close, duration);

    const box = getContainer();
    box.appendChild(el);
    if (box.hasAttribute('popover')) {
      try { if (box.matches(':popover-open')) box.hidePopover(); box.showPopover(); } catch (e) { /* older engines: plain fixed box */ }
    }
    return el;
  }

  return {
    success: (msg, dur) => show(msg, 'success', dur),
    error: (msg, dur) => show(msg, 'error', dur),
    info: (msg, dur) => show(msg, 'info', dur),
  };
})();

/* ── EXAM TIMER ─────────────────────────────────────────── */

const ExamTimer = (() => {
  let interval = null;

  /**
   * Démarre un compte à rebours.
   * @param {string} elementId - ID de l'élément à mettre à jour
   * @param {number} minutes   - Durée totale en minutes
   * @param {Function} onExpire - Callback appelé à l'expiration
   */
  function start(elementId, minutesOrSeconds, onExpire, useSeconds = false) {
    const el = document.getElementById(elementId);
    if (!el) return;

    if (!el.hasAttribute('aria-live')) {
      el.setAttribute('aria-live', 'polite');
      el.setAttribute('role', 'timer');
    }

    let remaining = useSeconds ? Math.floor(minutesOrSeconds) : Math.floor(minutesOrSeconds * 60);

    function update() {
      const m = Math.floor(remaining / 60).toString().padStart(2, '0');
      const s = (remaining % 60).toString().padStart(2, '0');
      el.textContent = `${m}:${s}`;

      if (remaining <= 300) el.classList.add('warning');

      if (remaining <= 0) {
        clearInterval(interval);
        if (typeof onExpire === 'function') onExpire();
      }
      remaining--;
    }

    update();
    interval = setInterval(update, 1000);
  }

  function startSeconds(elementId, seconds, onExpire) {
    start(elementId, seconds, onExpire, true);
  }

  function stop() {
    if (interval) clearInterval(interval);
  }

  return { start, startSeconds, stop };
})();

/* ── GLOBAL FETCH HELPER ────────────────────────────────── */

/**
 * Effectue une requête POST AJAX et retourne les données JSON.
 * Gère automatiquement les erreurs réseau et le jeton CSRF.
 */
function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
}

function injectCsrfIntoForms() {
  const token = getCsrfToken();
  if (!token) return;
  document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(form => {
    if (form.querySelector('input[name="csrf_token"]')) return;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'csrf_token';
    input.value = token;
    form.appendChild(input);
  });
}

document.addEventListener('DOMContentLoaded', injectCsrfIntoForms);

async function svPost(url, formData) {
  const token = getCsrfToken();
  if (token && !formData.has('csrf_token')) {
    formData.append('csrf_token', token);
  }
  try {
    const res = await fetch(url, {
      method: 'POST',
      body: formData,
      headers: token ? { 'X-CSRF-TOKEN': token } : {},
    });
    if (!res.ok) throw new Error(`Erreur HTTP ${res.status}`);
    return await res.json();
  } catch (err) {
    Toast.error('Erreur réseau : ' + err.message);
    throw err;
  }
}

/* ── PDF VIEWER (PDF.js) ────────────────────────────────── */

const PdfViewer = (() => {
  let pdfjsLib = null;
  let activeKeyHandler = null;

  const PDF_ICON = `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`;

  function loadScript() {
    if (pdfjsLib) return Promise.resolve(pdfjsLib);
    return new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
      s.onload = () => {
        pdfjsLib = window['pdfjs-dist/build/pdf'];
        pdfjsLib.GlobalWorkerOptions.workerSrc =
          'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        resolve(pdfjsLib);
      };
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  function teardown(container) {
    if (activeKeyHandler) {
      document.removeEventListener('keydown', activeKeyHandler);
      activeKeyHandler = null;
    }
    container._pdfCleanup = null;
  }

  async function render(container, url, options = {}) {
    teardown(container);
    const title = options.title || 'Document PDF';

    container.innerHTML = `
      <div class="sv-pdf-reader" role="region" aria-label="Lecteur PDF">
        <div class="sv-pdf-reader__header">
          <div class="sv-pdf-reader__label">
            ${PDF_ICON}
            <span data-pdf-title class="truncate max-w-[150px] md:max-w-xs">Document PDF</span>
          </div>
          <div class="sv-pdf-reader__toolbar">
            <button type="button" class="sv-pdf-reader__btn" data-pdf-prev title="Page précédente" aria-label="Page précédente">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
            </button>
            <span class="sv-pdf-reader__page-info">
              <input type="number" class="sv-pdf-reader__page-input" data-pdf-page-input min="1" value="1" aria-label="Numéro de page"> /
              <span data-pdf-total>1</span>
            </span>
            <button type="button" class="sv-pdf-reader__btn" data-pdf-next title="Page suivante" aria-label="Page suivante">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
            </button>
            
            <span class="sv-pdf-reader__sep"></span>
            
            <button type="button" class="sv-pdf-reader__btn" data-pdf-zoom-out title="Réduire" aria-label="Réduire">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 12H6"/></svg>
            </button>
            <span class="sv-pdf-reader__zoom-label" data-pdf-zoom-label>100%</span>
            <button type="button" class="sv-pdf-reader__btn" data-pdf-zoom-in title="Agrandir" aria-label="Agrandir">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            </button>
            <button type="button" class="sv-pdf-reader__btn" data-pdf-fit title="Ajuster à la largeur" aria-label="Ajuster à la largeur">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75v4.5m0-4.5h-4.5m4.5 0L15 9m5.25 11.25v-4.5m0 4.5h-4.5m4.5 0L15 15"/></svg>
            </button>
            
            <span class="sv-pdf-reader__sep"></span>
            
            <button type="button" class="sv-pdf-reader__btn" data-pdf-focus title="Mode Focus" aria-label="Mode Focus">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h6.5m-6.5 0v6.5m0-6.5L9 9m11.25-5.25h-6.5m6.5 0v6.5m0-6.5L15 9M3.75 20.25h6.5m-6.5 0v-6.5m0 6.5L9 15m11.25 5.25h-6.5m6.5 0v-6.5m0 6.5L15 15"/></svg>
            </button>
            
            <span class="sv-pdf-reader__sep"></span>
            
            <a href="${url}" target="_blank" rel="noopener" class="sv-pdf-reader__btn sv-pdf-reader__btn--primary" data-pdf-download title="Télécharger le PDF">
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
              PDF
            </a>
          </div>
        </div>
        <div class="sv-pdf-reader__viewport" data-pdf-viewport>
          <div class="sv-pdf-reader__loading">Chargement du document…</div>
          <div class="sv-pdf-reader__canvas-wrap hidden" data-pdf-wrap>
            <div class="sv-pdf-reader__page"><canvas data-pdf-canvas></canvas></div>
          </div>
        </div>
        <div class="sv-pdf-reader__footer" data-pdf-footer>← → pour naviguer · molette + Ajuster pour le confort de lecture</div>
      </div>`;

    const root = container.querySelector('.sv-pdf-reader');
    const titleEl = container.querySelector('[data-pdf-title]');
    if (titleEl) titleEl.textContent = title;
    const viewport = container.querySelector('[data-pdf-viewport]');
    const wrap = container.querySelector('[data-pdf-wrap]');
    const loading = container.querySelector('.sv-pdf-reader__loading');
    const canvas = container.querySelector('[data-pdf-canvas]');
    const pageInput = container.querySelector('[data-pdf-page-input]');
    const totalEl = container.querySelector('[data-pdf-total]');
    const zoomLabel = container.querySelector('[data-pdf-zoom-label]');
    const footer = container.querySelector('[data-pdf-footer]');

    let pdf = null;
    let pageNum = 1;
    let scale = 1;
    let fitScale = 1;
    let rendering = false;

    function updateControls() {
      if (!pdf) return;
      const total = pdf.numPages;
      pageInput.max = total;
      pageInput.value = pageNum;
      totalEl.textContent = total;
      container.querySelector('[data-pdf-prev]').disabled = pageNum <= 1;
      container.querySelector('[data-pdf-next]').disabled = pageNum >= total;
      zoomLabel.textContent = Math.round(scale * 100) + '%';
      footer.textContent = `Page ${pageNum} sur ${total} · ← → pour naviguer · bouton Ajuster pour la largeur optimale`;
    }

    async function computeFitScale(page) {
      const base = page.getViewport({ scale: 1 });
      const avail = Math.max(280, viewport.clientWidth - 48);
      return Math.min(2.2, Math.max(0.6, avail / base.width));
    }

    async function drawPage() {
      if (!pdf || rendering) return;
      rendering = true;
      try {
        const page = await pdf.getPage(pageNum);
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const vp = page.getViewport({ scale });

        canvas.width = Math.floor(vp.width * dpr);
        canvas.height = Math.floor(vp.height * dpr);
        canvas.style.width = Math.floor(vp.width) + 'px';
        canvas.style.height = Math.floor(vp.height) + 'px';

        const renderCtx = canvas.getContext('2d');
        renderCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
        await page.render({ canvasContext: renderCtx, viewport: vp }).promise;
        updateControls();
      } finally {
        rendering = false;
      }
    }

    function goToPage(n) {
      if (!pdf) return;
      pageNum = Math.max(1, Math.min(pdf.numPages, n));
      drawPage();
      viewport.scrollTop = 0;
      if (pageNum >= pdf.numPages && typeof options.onComplete === 'function') {
        options.onComplete();
      }
    }

    try {
      const lib = await loadScript();
      pdf = await lib.getDocument({ url: url, withCredentials: true }).promise;
      totalEl.textContent = pdf.numPages;

      loading.classList.add('hidden');
      wrap.classList.remove('hidden');

      fitScale = await computeFitScale(await pdf.getPage(1));
      scale = fitScale;
      await drawPage();

      container.querySelector('[data-pdf-prev]').onclick = () => goToPage(pageNum - 1);
      container.querySelector('[data-pdf-next]').onclick = () => goToPage(pageNum + 1);
      container.querySelector('[data-pdf-zoom-in]').onclick = () => { scale = Math.min(3, scale + 0.15); drawPage(); };
      container.querySelector('[data-pdf-zoom-out]').onclick = () => { scale = Math.max(0.5, scale - 0.15); drawPage(); };
      container.querySelector('[data-pdf-fit]').onclick = async () => {
        fitScale = await computeFitScale(await pdf.getPage(pageNum));
        scale = fitScale;
        drawPage();
      };

      pageInput.addEventListener('change', () => {
        const n = parseInt(pageInput.value, 10);
        if (!isNaN(n)) goToPage(n);
      });

      const focusBtn = container.querySelector('[data-pdf-focus]');
      if (focusBtn) {
        focusBtn.onclick = () => {
          const modal = document.getElementById('study-modal');
          if (modal) {
            modal.classList.toggle('sv-pdf-focus-mode');
            // Recompute fitScale and redraw after class changes
            setTimeout(async () => {
              if (pdf) {
                fitScale = await computeFitScale(await pdf.getPage(pageNum));
                scale = fitScale;
                drawPage();
              }
            }, 300);
          }
        };
      }

      activeKeyHandler = (e) => {
        if (!root.isConnected || !document.getElementById('study-modal') ||
          document.getElementById('study-modal').classList.contains('hidden')) return;
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); goToPage(pageNum - 1); }
        if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); goToPage(pageNum + 1); }
        if (e.key === '+' || e.key === '=') { e.preventDefault(); scale = Math.min(3, scale + 0.15); drawPage(); }
        if (e.key === '-') { e.preventDefault(); scale = Math.max(0.5, scale - 0.15); drawPage(); }
      };
      document.addEventListener('keydown', activeKeyHandler);

      let resizeTimer;
      const onResize = () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(async () => {
          if (!pdf) return;
          fitScale = await computeFitScale(await pdf.getPage(pageNum));
          if (Math.abs(scale - fitScale) < 0.05) {
            scale = fitScale;
            drawPage();
          }
        }, 200);
      };
      window.addEventListener('resize', onResize);
      container._pdfCleanup = () => {
        window.removeEventListener('resize', onResize);
        teardown(container);
      };

    } catch (err) {
      loading.classList.add('hidden');
      viewport.innerHTML = `<div class="sv-pdf-reader__error">Impossible de charger le PDF.<br><a href="${url}" target="_blank" rel="noopener" style="color:var(--sv-accent);margin-top:0.5rem;display:inline-block">Ouvrir dans un nouvel onglet</a></div>`;
    }
  }

  return { render };
})();

/* ── SESSION TIMER (leçon) ──────────────────────────────── */

const SessionTimer = (() => {
  let interval = null;
  let startTime = null;
  let lessonId = 0;
  let elapsed = 0;

  function start(lid, displayId) {
    stop();
    lessonId = lid;
    startTime = Date.now();
    const el = document.getElementById(displayId);
    if (!el) return;
    interval = setInterval(() => {
      elapsed = Math.floor((Date.now() - startTime) / 1000);
      const m = Math.floor(elapsed / 60).toString().padStart(2, '0');
      const s = (elapsed % 60).toString().padStart(2, '0');
      el.textContent = `${m}:${s}`;
    }, 1000);
  }

  function stop() {
    if (interval) {
      clearInterval(interval);
      interval = null;
      if (lessonId > 0 && elapsed > 10) {
        const fd = new FormData();
        fd.append('lesson_id', lessonId);
        fd.append('seconds', elapsed);
        fetch('/student/track-session.php', { method: 'POST', body: fd });
      }
    }
    lessonId = 0;
    elapsed = 0;
  }

  return { start, stop };
})();

/* ── COURSE SEARCH ──────────────────────────────────────── */

function initCourseSearch(inputId, gridId) {
  const input = document.getElementById(inputId);
  const grid = document.getElementById(gridId);
  if (!input || !grid) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    grid.querySelectorAll('[data-course-card]').forEach(card => {
      const text = card.dataset.search || '';
      card.classList.toggle('hidden', q !== '' && !text.includes(q));
    });
  });
}

/* ── TAB TRANSITIONS ────────────────────────────────────── */

function switchTabAnimated(tabName, tabs, prefix = 'tab') {
  tabs.forEach(t => {
    const panel = document.getElementById(`${prefix}-${t}`);
    if (panel) {
      panel.classList.add('hidden');
      panel.classList.remove('sv-fade-in');
    }
    const btn = document.getElementById(`${prefix}-btn-${t}`);
    if (btn) {
      btn.classList.remove('border-[#111111]', 'text-[#111111]', 'font-semibold');
      btn.classList.add('border-transparent', 'text-[#555555]', 'font-light');
    }
  });
  const active = document.getElementById(`${prefix}-${tabName}`);
  if (active) {
    active.classList.remove('hidden');
    active.classList.add('sv-fade-in');
  }
  const activeBtn = document.getElementById(`${prefix}-btn-${tabName}`);
  if (activeBtn) {
    activeBtn.classList.remove('border-transparent', 'text-[#555555]', 'font-light');
    activeBtn.classList.add('border-[#111111]', 'text-[#111111]', 'font-semibold');
  }
}

/* ── BADGE LABELS ───────────────────────────────────────── */

const BADGE_LABELS = {
  study_hour: '1h d\'étude',
  first_lesson: ' Première leçon',
  certified: ' Certifié',
  course_complete: ' Cours terminé',
};

/* ── CERTIFICATION CELEBRATION MODAL ───────────────────── */

const CertCelebration = (() => {
  function show(score, certCode) {
    const overlay = document.createElement('div');
    overlay.className = 'sv-cert-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-label', 'Certification réussie');

    const certLink = certCode
      ? `<a href="/certificate.php?code=${encodeURIComponent(certCode)}" class="sv-btn-ms" style="margin-top:1.25rem;display:inline-flex;">Voir mon certificat</a>`
      : '';

    overlay.innerHTML = `
      <div class="sv-cert-card">
        <div class="sv-cert-icon"></div>
        <h2 style="font-family:'Fraunces',sans-serif;font-size:1.5rem;font-weight:500;margin-bottom:0.5rem;">Certification validée</h2>
        <p style="font-size:0.875rem;color:var(--sv-text-muted,#555);line-height:1.6;">
          Félicitations ! Vous avez obtenu <strong>${score}%</strong> — seuil de 80% atteint.
        </p>
        ${certCode ? `<p style="font-size:0.75rem;font-family:monospace;margin-top:0.75rem;color:var(--sv-text-faint,#888);">${certCode}</p>` : ''}
        ${certLink}
        <button type="button" class="sv-btn-ms-outline" style="margin-top:0.75rem;display:inline-flex;margin-left:0.5rem;" data-cert-close>Fermer</button>
      </div>`;

    const close = () => {
      overlay.classList.add('hiding');
      overlay.addEventListener('animationend', () => overlay.remove(), { once: true });
    };

    overlay.querySelector('[data-cert-close]').addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    document.body.appendChild(overlay);
  }

  return { show };
})();

/* ── LESSON CONTENT GATE ────────────────────────────────── */

const LessonContentGate = (() => {
  let requirements = [];
  let completed = new Set();
  let onAllComplete = null;
  let cleanupFns = [];

  function reset() {
    cleanupFns.forEach(fn => { try { fn(); } catch (_) { } });
    cleanupFns = [];
    requirements = [];
    completed = new Set();
    onAllComplete = null;
  }

  function markDone(type) {
    if (completed.has(type)) return;
    completed.add(type);
    if (requirements.length > 0 && requirements.every(r => completed.has(r)) && onAllComplete) {
      onAllComplete(type);
    }
  }

  function extractYoutubeId(url) {
    if (!url) return '';
    if (url.includes('youtu.be/')) return url.split('youtu.be/')[1].split(/[?#]/)[0];
    const m = url.match(/[?&]v=([^&]+)/);
    return m ? m[1] : '';
  }

  const ytQueue = [];
  let ytApiLoading = false;

  function loadYoutubeApi() {
    if (window.YT && window.YT.Player) return Promise.resolve();
    if (ytApiLoading) {
      return new Promise(resolve => ytQueue.push(resolve));
    }
    ytApiLoading = true;
    return new Promise(resolve => {
      ytQueue.push(resolve);
      const prev = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = () => {
        if (typeof prev === 'function') prev();
        ytQueue.splice(0).forEach(fn => fn());
      };
      if (!document.getElementById('yt-iframe-api')) {
        const tag = document.createElement('script');
        tag.id = 'yt-iframe-api';
        tag.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(tag);
      }
    });
  }

  function trackTextScroll(el, scrollRoot) {
    const check = () => {
      const rect = el.getBoundingClientRect();
      const rootRect = scrollRoot ? scrollRoot.getBoundingClientRect() : { bottom: window.innerHeight };
      const bottomVisible = rect.bottom <= rootRect.bottom + 24;
      const fitsViewport = rect.height <= (scrollRoot ? scrollRoot.clientHeight : window.innerHeight);
      if (bottomVisible || fitsViewport) markDone('text');
    };
    const root = scrollRoot || window;
    root.addEventListener('scroll', check, { passive: true });
    cleanupFns.push(() => root.removeEventListener('scroll', check));
    setTimeout(check, 400);
    const interval = setInterval(check, 1200);
    cleanupFns.push(() => clearInterval(interval));
  }

  function setupYoutube(videoUrl, container, onEnd) {
    const videoId = extractYoutubeId(videoUrl);
    if (!videoId) return false;

    const playerDiv = document.createElement('div');
    const playerId = 'yt-player-' + Date.now() + Math.floor(Math.random() * 1000000);
    playerDiv.id = playerId;
    playerDiv.className = 'w-full';
    container.appendChild(playerDiv);

    loadYoutubeApi().then(() => {
      const player = new YT.Player(playerId, {
        videoId,
        width: '100%',
        height: 400,
        playerVars: { rel: 0, modestbranding: 1, enablejsapi: 1 },
        events: {
          onStateChange: (e) => {
            if (e.data === YT.PlayerState.ENDED) {
              if (onEnd) onEnd();
            }
          },
        },
      });
      cleanupFns.push(() => { try { player.destroy(); } catch (_) { } });
    });

    return true;
  }

  function setupGenericVideo(videoUrl, label, container, onEnd) {
    const videoBox = document.createElement('div');
    videoBox.className = 'aspect-w-16 aspect-h-9 max-w-3xl border border-[#E5E5E7] bg-black';

    const iframe = document.createElement('iframe');
    iframe.src = videoUrl;
    iframe.className = 'w-full h-[400px]';
    iframe.setAttribute('allowfullscreen', 'true');
    iframe.setAttribute('frameborder', '0');
    videoBox.appendChild(iframe);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'mt-3 px-4 py-2 text-xs font-semibold uppercase tracking-wider border border-[#E5E5E7] hover:bg-[#F5F5F7] rounded-sm';
    btn.textContent = `J'ai terminé la vidéo : ${label}`;
    btn.addEventListener('click', () => {
      btn.disabled = true;
      btn.textContent = `✓ Vidéo terminée : ${label}`;
      if (onEnd) onEnd();
    });

    container.appendChild(videoBox);
    container.appendChild(btn);
    return true;
  }

  /**
   * Initialise le suivi de consommation du contenu d'une leçon.
   * @param {object} config
   * @param {object} config.lesson - Objet leçon (content_type, text_content, pdf_path, video_url)
   * @param {Array} [config.videos] - Liste des vidéos associées (id, label, url)
   * @param {HTMLElement} config.mediaContainer
   * @param {HTMLElement} [config.scrollRoot]
   * @param {Function} config.onComplete - Appelé quand tout le contenu requis est consommé (dernier type en argument)
   */
  function init(config) {
    reset();
    onAllComplete = config.onComplete || null;

    const l = config.lesson || {};
    const videosToTrack = [];
    const seenUrls = new Set();
    if (l.video_url) {
      const cleanUrl = l.video_url.trim();
      if (cleanUrl) {
        videosToTrack.push({ url: cleanUrl, label: 'Vidéo' });
        seenUrls.add(cleanUrl);
      }
    }
    if (config.videos && Array.isArray(config.videos)) {
      config.videos.forEach((v, idx) => {
        if (v.url) {
          const cleanUrl = v.url.trim();
          if (cleanUrl && !seenUrls.has(cleanUrl)) {
            videosToTrack.push({ url: cleanUrl, label: v.label || `Vidéo ${idx + 1}` });
            seenUrls.add(cleanUrl);
          }
        }
      });
    }

    const needsText = (l.content_type === 'text' || l.content_type === 'mixed') && l.text_content;
    const needsPdf = (l.content_type === 'pdf' || l.content_type === 'mixed') && l.pdf_path;
    const needsVideo = (l.content_type === 'video' || l.content_type === 'mixed') && videosToTrack.length > 0;

    if (needsText) requirements.push('text');
    if (needsPdf) requirements.push('pdf');
    if (needsVideo) requirements.push('video');

    if (requirements.length === 0) {
      if (onAllComplete) onAllComplete(null);
      return;
    }

    if (needsText && config.textEl) {
      trackTextScroll(config.textEl, config.scrollRoot || null);
    }

    if (needsVideo && config.videoContainer && videosToTrack.length > 0) {
      config.videoContainer.innerHTML = '';
      config.videoContainer.className = 'w-full max-w-3xl flex flex-col gap-8';

      const completedVideos = new Set();
      const totalVideos = videosToTrack.length;

      videosToTrack.forEach((video, index) => {
        const videoWrapper = document.createElement('div');
        videoWrapper.className = 'w-full space-y-2';

        const labelEl = document.createElement('div');
        labelEl.className = 'text-xs font-semibold uppercase tracking-wider text-[#555555]';
        labelEl.textContent = video.label;
        videoWrapper.appendChild(labelEl);

        const onVideoEnd = () => {
          completedVideos.add(index);
          if (completedVideos.size === totalVideos) {
            markDone('video');
          }
        };

        const isYoutube = video.url.includes('youtube.com') || video.url.includes('youtu.be');
        if (isYoutube) {
          setupYoutube(video.url, videoWrapper, onVideoEnd);
        } else {
          setupGenericVideo(video.url, video.label, videoWrapper, onVideoEnd);
        }

        config.videoContainer.appendChild(videoWrapper);
      });
    }
  }

  return { init, reset, markDone };
})();

// ── Privacy notice ──
// A quiet card in the corner, not a blocking modal. Styled with the v2 tokens, FR/EN from <html lang>.
document.addEventListener('DOMContentLoaded', () => {
  try { if (localStorage.getItem('sv_privacy_accepted')) return; } catch (e) { return; }
  const en = (document.documentElement.lang || 'fr').toLowerCase().startsWith('en');
  const t = en ? {
    h: 'Your data, plainly', p: 'We keep your email and name to run your account, and your study time and quiz answers to compute your results. There are no advertising trackers. You can ask to see, fix or delete your data at any time.',
    more: 'Read the full privacy policy', ok: 'Got it'
  } : {
    h: 'Vos données, simplement', p: 'Nous gardons votre e-mail et votre nom pour gérer votre compte, ainsi que votre temps d’étude et vos réponses aux quiz pour calculer vos résultats. Aucun traceur publicitaire. Vous pouvez demander à consulter, corriger ou supprimer vos données à tout moment.',
    more: 'Lire la politique de confidentialité', ok: 'Compris'
  };
  const css = document.createElement('style');
  css.textContent = `
    #privacy-notice{position:fixed;left:16px;bottom:16px;z-index:10001;max-width:380px;width:calc(100vw - 32px);background:var(--card,var(--sv-surface,#FBF8F2));color:var(--ink,var(--sv-text,#1E1B16));border:1px solid var(--line,var(--sv-border,#DDD5C3));border-radius:14px;padding:18px 18px 16px;box-shadow:0 12px 32px -12px rgba(30,27,22,.28);font-family:var(--font-body,'Hanken Grotesk',system-ui,sans-serif);animation:pnIn .45s cubic-bezier(.2,.7,.2,1) both}
    #privacy-notice h2{font-family:var(--font-display,'Fraunces',Georgia,serif);font-weight:500;font-size:1.15rem;margin:0 0 6px;letter-spacing:-.01em}
    #privacy-notice p{margin:0;font-size:.86rem;line-height:1.55;color:var(--ink-2,var(--sv-text-muted,#4A453C))}
    #privacy-notice .pn-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px}
    #privacy-notice a{font-size:.84rem;font-weight:600;color:var(--clay,var(--sv-accent,#B5482A));text-underline-offset:3px}
    #privacy-notice button{font:600 .88rem var(--font-body,system-ui,sans-serif);background:var(--clay,#B5482A);color:#fff;border:0;border-radius:9px;padding:.6rem 1rem;cursor:pointer;min-height:40px}
    #privacy-notice button:hover{background:var(--clay-press,#96391E)}
    #privacy-notice.out{opacity:0;transform:translateY(8px);transition:opacity .25s,transform .25s}
    @keyframes pnIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
    @media (prefers-reduced-motion:reduce){#privacy-notice{animation:none}}
    @media print{#privacy-notice{display:none}}`;
  document.head.appendChild(css);
  const box = document.createElement('aside');
  box.id = 'privacy-notice';
  box.setAttribute('role', 'region');
  box.setAttribute('aria-label', t.h);
  box.innerHTML = `<h2>${t.h}</h2><p>${t.p}</p><div class="pn-row"><a href="/privacy.php" target="_blank" rel="noopener">${t.more}</a><button type="button" id="privacy-accept-btn">${t.ok}</button></div>`;
  document.body.appendChild(box);
  document.getElementById('privacy-accept-btn').addEventListener('click', () => {
    box.classList.add('out');
    setTimeout(() => { box.remove(); try { localStorage.setItem('sv_privacy_accepted', '1'); } catch (e) {} }, 260);
  });
});

function changeLanguage(lang) {
  fetch('/api/set-language.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ lang: lang })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      Toast.error('Erreur lors du changement de langue.');
    }
  })
  .catch(err => console.error('Error changing language:', err));
}

/* ── COUNTER UP ANIMATION ────────────────────────────────
   Usage: <span data-counter="1248" data-suffix="+" data-decimals="0">0</span>
   Triggers once when element enters viewport (IntersectionObserver).
   Easing: exponential ease-out — exactly like Anthropic's counters.
   ────────────────────────────────────────────────────── */

const CounterUp = (() => {
  const DURATION = 1600; // ms

  function easeOutExpo(t) {
    return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
  }

  function animateCounter(el) {
    const target   = parseFloat(el.dataset.counter ?? el.dataset.target ?? el.textContent);
    const decimals = parseInt(el.dataset.decimals ?? 0);
    const suffix   = el.dataset.suffix  ?? '';
    const prefix   = el.dataset.prefix  ?? '';
    if (isNaN(target)) return;

    const start = performance.now();

    function step(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / DURATION, 1);
      const eased = easeOutExpo(progress);
      const value = target * eased;

      el.textContent = prefix + value.toFixed(decimals) + suffix;

      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = prefix + target.toFixed(decimals) + suffix;
      }
    }

    requestAnimationFrame(step);
  }

  function init() {
    const els = document.querySelectorAll('[data-counter], [data-target]');
    if (!els.length) return;

    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.3 });

      els.forEach(el => io.observe(el));
    } else {
      // Fallback: animate immediately
      els.forEach(animateCounter);
    }
  }

  // Auto-init on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return { init, animateCounter };
})();

