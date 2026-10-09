/* Walkthrough with Daniel. Config comes from lib/Tour.php (window.SV_TOUR). */
(() => {
  const cfg = window.SV_TOUR;
  if (!cfg || !cfg.contexts || !cfg.contexts.main || !cfg.contexts.main.length) return;
  const UI = cfg.ui;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let steps = cfg.contexts.main;
  let ctxName = 'main';
  /* Where is the person? The lesson reader covers the dashboard, so Daniel explains that screen instead. */
  const currentContext = () => {
    const rd = document.getElementById('study-modal');
    return cfg.contexts.reader && rd && !rd.classList.contains('hidden') ? 'reader' : 'main';
  };
  let i = 0, active = false, openedDrawer = false, raf = 0, current = null;

  const el = (tag, cls, html) => { const n = document.createElement(tag); if (cls) n.className = cls; if (html != null) n.innerHTML = html; return n; };
  const esc = (s) => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const post = (action) => { try { fetch('/api/tour.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=' + action }); } catch (e) {} };
  const sleep = (ms) => new Promise(r => setTimeout(r, reduce ? Math.min(ms, 30) : ms));

  /* DOM */
  const root = el('div', 'tour-root');
  root.setAttribute('aria-hidden', 'true');
  const shield = el('div', 'tour-shield');
  const spot = el('div', 'tour-spot is-none');
  const card = el('div', 'tour-card');
  card.setAttribute('role', 'dialog'); card.setAttribute('aria-modal', 'true'); card.setAttribute('aria-labelledby', 'tour-title'); card.setAttribute('tabindex', '-1');
  card.innerHTML =
    '<div class="tour-daniel"><img src="/assets/img/daniel.png" alt="" width="88" height="88"></div>' +
    '<div class="tour-who"><b>' + esc(UI.name) + '</b><span>' + esc(UI.role) + '</span><span class="tour-count" aria-live="polite"></span></div>' +
    '<h2 class="tour-title" id="tour-title"></h2><p class="tour-text" id="tour-text"></p>' +
    '<div class="tour-dots" aria-hidden="true"></div>' +
    '<div class="tour-actions"><button type="button" class="tour-skip">' + esc(UI.skip) + '</button>' +
    '<button type="button" class="tour-btn tour-back">' + esc(UI.back) + '</button>' +
    '<button type="button" class="tour-btn primary tour-next"></button></div>';
  card.setAttribute('aria-describedby', 'tour-text');
  root.append(shield, spot, card);
  document.body.appendChild(root);

  const q = (s) => card.querySelector(s);
  const dots = q('.tour-dots');
  const buildDots = () => { dots.innerHTML = ''; steps.forEach(() => dots.appendChild(el('i'))); };
  buildDots();

  /* Floating replay button */
  const fab = el('button', 'tour-fab');
  fab.type = 'button'; fab.setAttribute('aria-label', UI.fab); fab.title = UI.fab;
  fab.innerHTML = '<img src="/assets/img/daniel.png" alt="" width="52" height="52">';
  document.body.appendChild(fab);

  /* Daniel moves: the animation replaces the still picture once the library is there */
  let lottieTried = false;
  function loadDaniel() {
    if (lottieTried) return; lottieTried = true;
    const mount = () => {
      if (!window.lottie) return;
      const box = q('.tour-daniel');
      const holder = el('div'); holder.style.cssText = 'position:absolute;inset:0;opacity:0;transition:opacity .4s';
      box.appendChild(holder);
      const a = lottie.loadAnimation({ container: holder, renderer: 'svg', loop: true, autoplay: !reduce, path: '/assets/anim/daniel.json', rendererSettings: { preserveAspectRatio: 'xMidYMid slice' } });
      a.addEventListener('DOMLoaded', () => { holder.style.opacity = 1; const img = box.querySelector('img'); if (img) setTimeout(() => img.remove(), 400); });
    };
    if (window.lottie) return mount();
    const s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie_light.min.js';
    s.onload = mount; document.head.appendChild(s);
  }

  /* Finding things */
  const rectOf = (n) => n.getBoundingClientRect();
  const usable = (n) => { if (!n) return false; const r = rectOf(n); if (r.width < 2 || r.height < 2) return false; const cs = getComputedStyle(n); return cs.visibility !== 'hidden' && cs.display !== 'none'; };
  const onScreen = (r) => r.right > 4 && r.left < innerWidth - 4 && r.bottom > 0 && r.top < innerHeight;
  const find = (sels) => { for (const s of sels || []) { const n = document.querySelector(s); if (usable(n)) return n; } return null; };
  const isDisabled = (n) => n.matches('.is-disabled, [aria-disabled="true"], [disabled]');

  /* Panels a step may need open (reader outline on small screens) */
  let openedOutline = false;
  async function runBefore(step) {
    if (!step.before || typeof window.setOutline !== 'function') return;
    const rd = document.getElementById('study-modal');
    if (!rd) return;
    const isOpen = rd.classList.contains('rd--outline-open');
    if (step.before === 'outline' && !isOpen) { setOutline(true); openedOutline = true; await sleep(320); }
    else if (step.before === 'close' && isOpen && openedOutline) { setOutline(false); openedOutline = false; await sleep(320); }
  }

  async function ensureDrawer(step) {
    const rail = document.getElementById('t-rail');
    if (!rail || typeof window.toggleMobileDrawer !== 'function') return false;
    const rr = rail.getBoundingClientRect();
    const open = rail.classList.contains('open');
    const needs = !!step.rail && !open && !(rr.right > 4 && rr.left > -4);
    const wantClosed = !step.rail && open && openedDrawer;
    if (needs) { toggleMobileDrawer(); openedDrawer = true; await sleep(380); return true; }
    if (wantClosed) { toggleMobileDrawer(); openedDrawer = false; await sleep(380); }
    return open;
  }

  /* Placement */
  function place(r) {
    const m = 12, top0 = 44, cw = card.offsetWidth, ch = card.offsetHeight, vw = innerWidth, vh = innerHeight;
    if (vw <= 640) {
      card.style.left = '12px'; card.style.right = '12px';
      const lower = r && (r.top + r.height / 2) > vh * .5;
      card.style.top = (r && lower ? top0 + (document.querySelector('.sd-topbar') ? 56 : 0) : Math.max(top0, vh - ch - m - 8)) + 'px';
      if (!r) card.style.top = Math.max(top0, (vh - ch) / 2) + 'px';
      return;
    }
    card.style.right = 'auto';
    if (!r) { card.style.left = Math.round((vw - cw) / 2) + 'px'; card.style.top = Math.round((vh - ch) / 2 + 20) + 'px'; return; }
    const gap = 18, clampX = (x) => Math.min(Math.max(m, x), vw - cw - m), clampY = (y) => Math.min(Math.max(top0, y), vh - ch - m);
    const cands = [
      { ok: r.right + gap + cw + m <= vw, x: r.right + gap, y: clampY(r.top + r.height / 2 - ch / 2) },
      { ok: r.bottom + gap + 40 + ch + m <= vh, x: clampX(r.left + r.width / 2 - cw / 2), y: r.bottom + gap + 40 },
      { ok: r.left - gap - cw - m >= 0, x: r.left - gap - cw, y: clampY(r.top + r.height / 2 - ch / 2) },
      { ok: r.top - gap - ch - top0 >= 0, x: clampX(r.left + r.width / 2 - cw / 2), y: r.top - gap - ch },
    ];
    const c = cands.find(c => c.ok) || { x: clampX(r.left), y: clampY(r.bottom + gap) };
    card.style.left = Math.round(c.x) + 'px'; card.style.top = Math.round(c.y) + 'px';
  }

  function aim(target) {
    if (!target) { spot.classList.add('is-none'); spot.style.left = innerWidth / 2 + 'px'; spot.style.top = innerHeight / 2 + 'px'; spot.style.width = '0px'; spot.style.height = '0px'; return null; }
    const r = rectOf(target), pad = 6, br = parseFloat(getComputedStyle(target).borderTopLeftRadius) || 0;
    spot.classList.remove('is-none');
    spot.style.left = (r.left - pad) + 'px'; spot.style.top = (r.top - pad) + 'px';
    spot.style.width = (r.width + pad * 2) + 'px'; spot.style.height = (r.height + pad * 2) + 'px';
    spot.style.borderRadius = Math.min(br + pad, 28) + 'px';
    return r;
  }

  function track() {
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => { if (!active || !current) return; const r = aim(current); place(r); });
  }

  /* Step rendering */
  async function show(n) {
    i = Math.max(0, Math.min(steps.length - 1, n));
    const s = steps[i];
    card.classList.remove('is-in');

    await runBefore(s);
    const drawerNow = await ensureDrawer(s);
    if (s.click && !drawerNow) { const b = find(s.click); if (b && !isDisabled(b)) { b.click(); await sleep(260); } }

    let t = find(s.target);
    if (t) {
      const r0 = rectOf(t);
      if (r0.top < 8 || r0.bottom > innerHeight - 8) { t.scrollIntoView({ block: 'center', behavior: reduce ? 'auto' : 'smooth' }); await sleep(420); }
      if (!onScreen(rectOf(t))) t = null;
    }
    current = t;

    q('.tour-title').textContent = s.title;
    q('.tour-text').textContent = s.text;
    q('.tour-count').textContent = UI.step.replace(':n', i + 1).replace(':t', steps.length);
    [...dots.children].forEach((d, k) => { d.className = k === i ? 'on' : (k < i ? 'done' : ''); });
    q('.tour-back').hidden = i === 0;
    const last = i === steps.length - 1;
    q('.tour-next').textContent = i === 0 ? UI.start : (last ? UI.done : UI.next);
    q('.tour-skip').hidden = last;

    const r = aim(t);
    place(r);
    requestAnimationFrame(() => { card.classList.add('is-in'); q('.tour-next').focus({ preventScroll: true }); });
  }

  /* Open / close */
  function start() {
    if (active) return;
    ctxName = currentContext();
    steps = cfg.contexts[ctxName];
    buildDots();
    active = true; loadDaniel();
    root.classList.add('is-on'); root.removeAttribute('aria-hidden');
    fab.classList.add('is-hidden');
    document.addEventListener('keydown', onKey, true);
    addEventListener('resize', track); addEventListener('scroll', track, true);
    show(0);
  }
  async function stop(markSeen = true) {
    if (!active) return;
    active = false; current = null;
    document.removeEventListener('keydown', onKey, true);
    removeEventListener('resize', track); removeEventListener('scroll', track, true);
    card.classList.remove('is-in'); spot.classList.add('is-none');
    root.classList.remove('is-on'); root.setAttribute('aria-hidden', 'true');
    if (openedDrawer && typeof window.toggleMobileDrawer === 'function') { toggleMobileDrawer(); openedDrawer = false; }
    if (openedOutline && typeof window.setOutline === 'function') { setOutline(false); openedOutline = false; }
    fab.classList.remove('is-hidden');
    if (markSeen && ctxName === 'main') post('complete');
  }
  const next = () => (i >= steps.length - 1 ? stop() : show(i + 1));
  const back = () => show(i - 1);

  function onKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); stop(); }
    else if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
    else if (e.key === 'ArrowLeft') { e.preventDefault(); if (i > 0) back(); }
    else if (e.key === 'Tab') {
      const f = [...card.querySelectorAll('button:not([hidden])')];
      if (!f.length) return;
      const k = f.indexOf(document.activeElement);
      if (e.shiftKey && k <= 0) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && k === f.length - 1) { e.preventDefault(); f[0].focus(); }
      else if (k === -1) { e.preventDefault(); f[0].focus(); }
    }
  }

  q('.tour-next').addEventListener('click', next);
  q('.tour-back').addEventListener('click', back);
  q('.tour-skip').addEventListener('click', () => stop());
  shield.addEventListener('click', (e) => e.stopPropagation());
  fab.addEventListener('click', start);
  /* the button follows the screen: it names the guide for where you are */
  const syncFab = () => { const c = currentContext(); const label = c === 'reader' ? (UI.fab_reader || UI.fab) : UI.fab; fab.title = label; fab.setAttribute('aria-label', label); fab.dataset.ctx = c; };
  syncFab(); setInterval(syncFab, 700);
  window.SVTour = { start, stop };

  if (cfg.autostart && currentContext() === 'main') {
    const go = () => setTimeout(start, 900);
    document.readyState === 'complete' ? go() : addEventListener('load', go);
  }
})();
