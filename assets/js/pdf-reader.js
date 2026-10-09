/* PDF reader for lessons: pages flow in one calm column, rendered as they come into view.
   A page counts as read after it has been on screen for a moment, so jumping to the last page does not finish the lesson.
   window.SVPdf.render(container, url, { title, onComplete, labels }) */
(() => {
  const PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
  const WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  let libPromise = null;

  function loadLib() {
    if (window['pdfjs-dist/build/pdf']) return Promise.resolve(window['pdfjs-dist/build/pdf']);
    if (libPromise) return libPromise;
    libPromise = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = PDFJS;
      s.onload = () => { const lib = window['pdfjs-dist/build/pdf']; lib.GlobalWorkerOptions.workerSrc = WORKER; resolve(lib); };
      s.onerror = () => { libPromise = null; reject(new Error('pdf.js')); };
      document.head.appendChild(s);
    });
    return libPromise;
  }

  const I = {
    minus: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg>',
    plus: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
    fit: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>',
    full: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H4v5M15 4h5v5M9 20H4v-5M15 20h5v-5"/></svg>',
    down: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11m0 0l-4-4m4 4l4-4M5 20h14"/></svg>',
    check: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
  };

  const L = (o, k, d) => (o && o.labels && o.labels[k]) || d;

  async function render(container, url, options = {}) {
    if (container._pdfCleanup) container._pdfCleanup();
    const title = options.title || 'PDF';
    const lbl = {
      read: L(options, 'read', 'Pages lues'), keep: L(options, 'keep', 'Lisez chaque page pour continuer.'),
      done: L(options, 'done', 'Document lu en entier.'), loading: L(options, 'loading', 'Ouverture du document…'),
      err: L(options, 'err', 'Impossible d’ouvrir ce document ici.'), dl: L(options, 'dl', 'Télécharger'),
      zin: L(options, 'zin', 'Agrandir'), zout: L(options, 'zout', 'Réduire'), fit: L(options, 'fit', 'Ajuster à la largeur'),
      full: L(options, 'full', 'Plein écran'), page: L(options, 'page', 'Page'), of: L(options, 'of', 'sur'),
    };

    container.innerHTML = `
      <div class="pr" role="region" aria-label="${title.replace(/"/g, '&quot;')}">
        <div class="pr-bar">
          <span class="pr-title"></span>
          <span class="pr-pos num" aria-live="polite"><b data-pr-cur>1</b> / <span data-pr-total>–</span></span>
          <div class="pr-tools">
            <button type="button" class="pr-btn" data-pr-out title="${lbl.zout}" aria-label="${lbl.zout}">${I.minus}</button>
            <span class="pr-zoom num" data-pr-zoom>100%</span>
            <button type="button" class="pr-btn" data-pr-in title="${lbl.zin}" aria-label="${lbl.zin}">${I.plus}</button>
            <button type="button" class="pr-btn pr-btn-txt" data-pr-fit title="${lbl.fit}" aria-label="${lbl.fit}">1:1</button>
            <button type="button" class="pr-btn" data-pr-full title="${lbl.full}" aria-label="${lbl.full}">${I.full}</button>
            <a class="pr-btn" href="${url}" target="_blank" rel="noopener" title="${lbl.dl}" aria-label="${lbl.dl}">${I.down}</a>
          </div>
          <div class="pr-meter" aria-hidden="true"><i data-pr-meter></i></div>
        </div>
        <div class="pr-stage" data-pr-stage tabindex="0">
          <div class="pr-loading" data-pr-loading><span class="pr-spin"></span>${lbl.loading}</div>
          <div class="pr-pages" data-pr-pages></div>
        </div>
        <p class="pr-status" data-pr-status role="status">&nbsp;</p>
      </div>`;

    const $ = (s) => container.querySelector(s);
    container.querySelector('.pr-title').textContent = title;
    const root = $('.pr'), stage = $('[data-pr-stage]'), pagesEl = $('[data-pr-pages]'), loadingEl = $('[data-pr-loading]');
    const curEl = $('[data-pr-cur]'), totalEl = $('[data-pr-total]'), zoomEl = $('[data-pr-zoom]'), meter = $('[data-pr-meter]'), status = $('[data-pr-status]');

    let pdf = null, zoom = 1, total = 0, completed = false, destroyed = false;
    const seen = new Set(), dwell = new Map(), rendered = new Map(), pageSlots = [];
    let renderIO = null, seenIO = null, resizeTimer = null;
    const maxWidth = 900;

    function baseWidth() { return Math.max(260, Math.min(maxWidth, stage.clientWidth - 24)) * zoom; }

    function setStatus() {
      const n = seen.size;
      meter.style.width = total ? Math.round((n / total) * 100) + '%' : '0';
      status.classList.toggle('is-done', completed);
      status.innerHTML = completed ? I.check + ' ' + lbl.done : lbl.read + ' <b class="num">' + n + ' / ' + total + '</b> · ' + lbl.keep;
    }

    function markSeen(i) {
      if (seen.has(i)) return;
      seen.add(i);
      pageSlots[i].classList.add('is-read');
      if (!completed && seen.size >= total) {
        completed = true;
        if (typeof options.onComplete === 'function') options.onComplete();
      }
      setStatus();
    }

    async function drawPage(i) {
      if (destroyed || rendered.get(i) === zoom) return;
      const slot = pageSlots[i], canvas = slot.querySelector('canvas');
      const page = await pdf.getPage(i + 1);
      const base = page.getViewport({ scale: 1 });
      const scale = baseWidth() / base.width;
      const vp = page.getViewport({ scale });
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.floor(vp.width * dpr); canvas.height = Math.floor(vp.height * dpr);
      canvas.style.width = Math.floor(vp.width) + 'px'; canvas.style.height = Math.floor(vp.height) + 'px';
      slot.style.width = Math.floor(vp.width) + 'px'; slot.style.height = Math.floor(vp.height) + 'px';
      const ctx = canvas.getContext('2d');
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      rendered.set(i, zoom);
      await page.render({ canvasContext: ctx, viewport: vp }).promise;
      slot.classList.add('is-ready');
    }

    function release(i) {
      const slot = pageSlots[i], canvas = slot.querySelector('canvas');
      canvas.width = 0; canvas.height = 0; rendered.delete(i); slot.classList.remove('is-ready');
    }

    async function layoutSlots() {
      const first = await pdf.getPage(1), base = first.getViewport({ scale: 1 });
      const w = baseWidth(), ratio = base.height / base.width;
      pageSlots.forEach((slot, i) => {
        if (!slot.classList.contains('is-ready')) { slot.style.width = Math.floor(w) + 'px'; slot.style.height = Math.floor(w * ratio) + 'px'; }
      });
    }

    function scroller() {
      for (let n = root.parentElement; n; n = n.parentElement) {
        const o = getComputedStyle(n).overflowY;
        if ((o === 'auto' || o === 'scroll') && n.scrollHeight > n.clientHeight - 1) return n;
      }
      return document.fullscreenElement === root ? stage : null;
    }

    function observe() {
      const sroot = document.fullscreenElement === root ? stage : (scroller() || null);
      if (renderIO) renderIO.disconnect();
      if (seenIO) seenIO.disconnect();
      renderIO = new IntersectionObserver((entries) => entries.forEach(e => {
        const i = +e.target.dataset.i;
        if (e.isIntersecting) { drawPage(i).catch(() => { }); }
        else if (rendered.has(i) && Math.abs(i - (+curEl.textContent - 1)) > 6) release(i);
      }), { root: sroot, rootMargin: '900px 0px' });
      seenIO = new IntersectionObserver((entries) => entries.forEach(e => {
        const i = +e.target.dataset.i;
        const h = e.boundingClientRect.height || 1, vh = (e.rootBounds && e.rootBounds.height) || innerHeight;
        const visible = e.intersectionRect.height;
        const enough = visible >= Math.min(h * 0.55, vh * 0.45);
        if (e.isIntersecting && enough) {
          if (!dwell.has(i)) dwell.set(i, setTimeout(() => { dwell.delete(i); markSeen(i); }, 1100));
          curEl.textContent = i + 1;
        } else if (dwell.has(i)) { clearTimeout(dwell.get(i)); dwell.delete(i); }
      }), { root: sroot, threshold: [0, .15, .3, .45, .6, .75, 1] });
      pageSlots.forEach(s => { renderIO.observe(s); seenIO.observe(s); });
    }

    function setZoom(z) {
      zoom = Math.max(.6, Math.min(1.8, z));
      zoomEl.textContent = Math.round(zoom * 100) + '%';
      rendered.clear();
      pageSlots.forEach(s => s.classList.remove('is-ready'));
      layoutSlots().then(observe);
    }

    $('[data-pr-in]').onclick = () => setZoom(zoom + .15);
    $('[data-pr-out]').onclick = () => setZoom(zoom - .15);
    $('[data-pr-fit]').onclick = () => setZoom(1);
    $('[data-pr-full]').onclick = () => {
      if (document.fullscreenElement) document.exitFullscreen();
      else if (root.requestFullscreen) root.requestFullscreen().catch(() => { });
    };
    const onFs = () => { root.classList.toggle('is-full', document.fullscreenElement === root); setTimeout(() => setZoom(zoom), 120); };
    document.addEventListener('fullscreenchange', onFs);

    const onKey = (e) => {
      if (!root.contains(document.activeElement) && document.activeElement !== document.body) return;
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        const cur = +curEl.textContent - 1, to = Math.max(0, Math.min(total - 1, cur + (e.key === 'ArrowRight' ? 1 : -1)));
        if (to !== cur && pageSlots[to]) { e.preventDefault(); pageSlots[to].scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      }
    };
    root.addEventListener('keydown', onKey);

    const onResize = () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(() => { if (pdf && !document.fullscreenElement) setZoom(zoom); }, 200); };
    addEventListener('resize', onResize);

    container._pdfCleanup = () => {
      destroyed = true;
      if (renderIO) renderIO.disconnect();
      if (seenIO) seenIO.disconnect();
      dwell.forEach(t => clearTimeout(t));
      document.removeEventListener('fullscreenchange', onFs);
      removeEventListener('resize', onResize);
      clearTimeout(resizeTimer);
      try { pdf && pdf.destroy(); } catch (e) { }
      container._pdfCleanup = null;
    };

    try {
      const lib = await loadLib();
      pdf = await lib.getDocument({ url, withCredentials: true }).promise;
      if (destroyed) return;
      total = pdf.numPages;
      totalEl.textContent = total;
      loadingEl.remove();
      for (let i = 0; i < total; i++) {
        const slot = document.createElement('div');
        slot.className = 'pr-page'; slot.dataset.i = i;
        slot.innerHTML = '<canvas aria-label="' + lbl.page + ' ' + (i + 1) + ' ' + lbl.of + ' ' + total + '"></canvas><span class="pr-pagenum num">' + (i + 1) + '</span><span class="pr-tick" aria-hidden="true">' + I.check + '</span>';
        pagesEl.appendChild(slot); pageSlots.push(slot);
      }
      setStatus();
      await layoutSlots();
      observe();
    } catch (err) {
      if (destroyed) return;
      loadingEl.innerHTML = '<span>' + lbl.err + '</span> <a href="' + url + '" target="_blank" rel="noopener">' + lbl.dl + '</a>';
      loadingEl.classList.add('is-error');
    }
  }

  window.SVPdf = { render };
})();
