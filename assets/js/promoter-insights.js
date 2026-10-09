/* Promoter dashboard v3: command page, growth (funnel, channels, QR studio) and insights.
   Data comes from /promoter/insights-api.php and /promoter/campaigns-api.php. No chart library: small SVG helpers below. */
(() => {
  const I = window.PM_I18N || {};
  const BASE = window.PM_BASE || location.origin;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const tr = (k, p) => { let s = I[k] !== undefined ? I[k] : k; if (p) Object.keys(p).forEach(n => { s = s.split(':' + n).join(String(p[n])); }); return s; };
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const nf = new Intl.NumberFormat(document.documentElement.lang === 'en' ? 'en-US' : 'fr-FR');
  const fmt = (n) => nf.format(Math.round(n));
  const cssVar = (n, d) => getComputedStyle(document.documentElement).getPropertyValue(n).trim() || d;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const ns = 'http://www.w3.org/2000/svg';

  let days = 30;
  const cache = {};
  async function api(section, d) {
    const key = section + d;
    const r = await fetch(`/promoter/insights-api.php?section=${section}&days=${d}`, { credentials: 'same-origin' });
    if (!r.ok) throw new Error('http ' + r.status);
    const j = await r.json();
    if (!j.success) throw new Error('api');
    cache[key] = j;
    return j;
  }
  const fail = (el) => { if (el) el.innerHTML = `<p class="pi-error">${esc(tr('error'))}</p>`; };

  /* ───────────────────────── chart helpers ───────────────────────── */
  function sparkline(data, color) {
    const w = 120, h = 34, max = Math.max(1, ...data), n = data.length;
    if (!n || data.every(v => v === 0)) return `<svg class="pi-spark" viewBox="0 0 ${w} ${h}" aria-hidden="true"><line x1="0" y1="${h - 2}" x2="${w}" y2="${h - 2}" stroke="var(--line-2)" stroke-width="1.5" stroke-dasharray="3 4"/></svg>`;
    const pts = data.map((v, i) => [i * (w / (n - 1 || 1)), h - 3 - (v / max) * (h - 8)]);
    const line = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
    const area = line + ` L${w} ${h} L0 ${h} Z`;
    const id = 'sp' + Math.random().toString(36).slice(2, 8);
    return `<svg class="pi-spark" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${color}" stop-opacity=".28"/><stop offset="1" stop-color="${color}" stop-opacity="0"/></linearGradient></defs><path d="${area}" fill="url(#${id})"/><path d="${line}" fill="none" stroke="${color}" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>`;
  }

  /** Multi-series line chart with legend toggles and a hover read-out. series: [{key,label,color,data}] */
  function lineChart(host, labels, series) {
    const hidden = new Set(series.filter(x => x.off).map(x => x.key));
    const W = 900, H = 300, pad = { l: 40, r: 14, t: 14, b: 28 };
    host.innerHTML = `<div class="pi-legend"></div><div class="pi-plot"><svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(tr('chart_h'))}"></svg><div class="pi-tip" hidden></div></div>`;
    const svg = $('svg', host), tip = $('.pi-tip', host), legend = $('.pi-legend', host);
    const x = (i) => pad.l + (i / Math.max(1, labels.length - 1)) * (W - pad.l - pad.r);
    function draw() {
      svg.innerHTML = '';
      const vis = series.filter(s => !hidden.has(s.key));
      const max = Math.max(1, ...vis.flatMap(s => s.data));
      const top = niceMax(max);
      const y = (v) => pad.t + (1 - v / top) * (H - pad.t - pad.b);
      for (let g = 0; g <= 4; g++) {
        const v = top * g / 4, yy = y(v);
        svg.insertAdjacentHTML('beforeend', `<line x1="${pad.l}" x2="${W - pad.r}" y1="${yy}" y2="${yy}" stroke="var(--line)" stroke-width="1"/><text x="${pad.l - 8}" y="${yy + 4}" text-anchor="end" class="pi-axis">${fmt(v)}</text>`);
      }
      const step = Math.ceil(labels.length / 7);
      labels.forEach((d, i) => { if (i % step === 0 || i === labels.length - 1) svg.insertAdjacentHTML('beforeend', `<text x="${x(i)}" y="${H - 8}" text-anchor="middle" class="pi-axis">${esc(shortDate(d))}</text>`); });
      vis.forEach((s, si) => {
        const d = s.data.map((v, i) => (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1)).join(' ');
        if (si === 0) svg.insertAdjacentHTML('beforeend', `<path d="${d} L${x(labels.length - 1)} ${y(0)} L${x(0)} ${y(0)} Z" fill="${s.color}" opacity=".08"/>`);
        const p = document.createElementNS(ns, 'path');
        p.setAttribute('d', d); p.setAttribute('fill', 'none'); p.setAttribute('stroke', s.color); p.setAttribute('stroke-width', '2.2'); p.setAttribute('stroke-linejoin', 'round'); p.setAttribute('stroke-linecap', 'round');
        if (!reduce) { const len = 2400; p.style.strokeDasharray = len; p.style.strokeDashoffset = len; p.getBoundingClientRect(); p.style.transition = 'stroke-dashoffset .9s cubic-bezier(.2,.7,.2,1)'; requestAnimationFrame(() => { p.style.strokeDashoffset = 0; }); }
        svg.appendChild(p);
      });
      svg.insertAdjacentHTML('beforeend', `<line class="pi-cursor" y1="${pad.t}" y2="${H - pad.b}" stroke="var(--ink-3)" stroke-width="1" stroke-dasharray="3 3" style="display:none"/>`);
      svg._geom = { x, y, vis };
    }
    legend.innerHTML = series.map(s => `<button type="button" class="pi-chip${hidden.has(s.key) ? ' is-off' : ''}" data-k="${s.key}" aria-pressed="${!hidden.has(s.key)}"><i style="background:${s.color}"></i>${esc(s.label)}</button>`).join('');
    legend.addEventListener('click', (e) => {
      const b = e.target.closest('[data-k]'); if (!b) return;
      const k = b.dataset.k; hidden.has(k) ? hidden.delete(k) : hidden.add(k);
      if (hidden.size === series.length) hidden.delete(k);
      b.setAttribute('aria-pressed', String(!hidden.has(k))); b.classList.toggle('is-off', hidden.has(k)); draw();
    });
    const move = (e) => {
      const r = svg.getBoundingClientRect(), px = ((e.touches ? e.touches[0].clientX : e.clientX) - r.left) / r.width * W;
      const i = Math.max(0, Math.min(labels.length - 1, Math.round((px - pad.l) / (W - pad.l - pad.r) * (labels.length - 1))));
      const g = svg._geom; if (!g) return;
      const cur = $('.pi-cursor', svg); cur.setAttribute('x1', g.x(i)); cur.setAttribute('x2', g.x(i)); cur.style.display = '';
      tip.hidden = false;
      tip.innerHTML = `<b>${esc(longDate(labels[i]))}</b>` + g.vis.map(s => `<span><i style="background:${s.color}"></i>${esc(s.label)} <strong>${fmt(s.data[i])}</strong></span>`).join('');
      const left = g.x(i) / W * r.width; tip.style.left = Math.min(Math.max(left, 80), r.width - 80) + 'px';
    };
    const plot = $('.pi-plot', host);
    plot.addEventListener('mousemove', move); plot.addEventListener('touchmove', move, { passive: true });
    plot.addEventListener('mouseleave', () => { tip.hidden = true; const c = $('.pi-cursor', svg); if (c) c.style.display = 'none'; });
    draw();
  }
  function niceMax(v) { const p = Math.pow(10, Math.floor(Math.log10(v))); const m = v / p; return (m <= 1 ? 1 : m <= 2 ? 2 : m <= 5 ? 5 : 10) * p; }
  const locale = () => (document.documentElement.lang === 'en' ? 'en-US' : 'fr-FR');
  const shortDate = (d) => new Date(d + 'T12:00:00').toLocaleDateString(locale(), { day: 'numeric', month: 'short' });
  const longDate = (d) => new Date(d + 'T12:00:00').toLocaleDateString(locale(), { weekday: 'long', day: 'numeric', month: 'long' });

  /* ───────────────────────── command page ───────────────────────── */
  const KPI_COLORS = { new_users: '--clay', active: '--pine', enrollments: '--ochre', lessons: '--pine', certificates: '--clay', live_part: '--ochre', verify_rate: '--pine' };

  function renderKpis(o) {
    const host = $('#pm-kpis'); if (!host) return;
    host.innerHTML = o.kpis.map((k, idx) => {
      const col = cssVar(KPI_COLORS[k.key] || '--clay', '#B5482A');
      let delta = '';
      if (k.delta === null && k.prev !== null) delta = `<span class="pi-delta is-new">${esc(tr('no_base'))}</span>`;
      else if (k.delta !== null) delta = `<span class="pi-delta ${k.delta > 0 ? 'is-up' : k.delta < 0 ? 'is-down' : 'is-flat'}" title="${esc(tr('vs_prev', { d: o.days }))}">${k.delta > 0 ? '▲' : k.delta < 0 ? '▼' : '•'} ${k.delta === 0 ? esc(tr('flat')) : Math.abs(k.delta) + ' %'}</span>`;
      const val = k.unit === '%' ? (k.value === null ? '—' : `${fmt(k.value)}<small>%</small>`) : fmt(k.value);
      return `<article class="pi-kpi" style="--i:${idx}" title="${esc(tr('k_' + k.key + '_h'))}">
        <p class="pi-kpi-l">${esc(tr('k_' + k.key))}</p>
        <p class="pi-kpi-v num" data-count="${k.value === null ? '' : k.value}">${val}</p>
        <div class="pi-kpi-f">${delta}${k.spark ? sparkline(k.spark, col) : ''}</div>
      </article>`;
    }).join('');
    if (!reduce) $$('.pi-kpi-v[data-count]', host).forEach(el => {
      const target = parseFloat(el.dataset.count); if (!isFinite(target) || target < 2 || el.querySelector('small')) return;
      const t0 = performance.now(), dur = 700;
      const tick = (t) => { const p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3); el.textContent = fmt(target * e); if (p < 1) requestAnimationFrame(tick); else el.textContent = fmt(target); };
      requestAnimationFrame(tick);
    });
  }

  function renderTrend(o) {
    const host = $('#pm-trend'); if (!host) return;
    const s = o.series, c = (v, d) => cssVar(v, d);
    const list = [
      { key: 'signups', label: tr('s_signups'), color: c('--clay', '#B5482A'), data: s.signups },
      { key: 'logins', label: tr('s_logins'), color: c('--pine', '#24402F'), data: s.logins },
      { key: 'enrollments', label: tr('s_enrollments'), color: c('--ochre', '#D9A23B'), data: s.enrollments },
      { key: 'lessons', label: tr('s_lessons'), color: '#5B7FA6', data: s.lessons },
    ];
    if (s.visitors.some(v => v > 0)) list.push({ key: 'visitors', label: tr('s_visitors'), color: '#8A6BB5', data: s.visitors, off: true });
    lineChart(host, s.days, list);
  }

  function renderRecs(list) {
    const host = $('#pm-recs'); if (!host) return;
    if (!list.length) { host.innerHTML = `<div class="pi-calm"><b>${esc(tr('recs_none_t'))}</b><span>${esc(tr('recs_none_p'))}</span></div>`; return; }
    host.innerHTML = list.map(r => {
      const act = tr(r.key + '_a');
      const goAttr = r.go ? ` data-goto="${r.go}"` : '';
      const btn = act && act !== r.key + '_a' && act !== '' && r.go ? `<button type="button" class="btn btn-ghost btn-sm"${goAttr}>${esc(act)}</button>` : '';
      return `<article class="pi-rec is-${r.level}"><span class="pi-rec-dot" aria-hidden="true"></span><p>${esc(tr(r.key, r.params))}</p>${btn}</article>`;
    }).join('');
    wireGoto(host);
  }

  function renderMiniFunnel(f) {
    const host = $('#pm-funnel-mini'); if (!host) return;
    const keys = ['account', 'verified', 'enrolled', 'started', 'lesson_done', 'certificate'];
    const steps = f.steps.filter(s => keys.includes(s.key));
    const max = Math.max(1, ...steps.map(s => s.n));
    host.innerHTML = steps.map((s, i) => `<div class="pi-mf" style="--i:${i}"><span class="pi-mf-l">${esc(tr('fn_' + s.key))}</span><span class="pi-mf-bar"><i style="width:${Math.max(2, s.n / max * 100)}%"></i></span><b class="num">${fmt(s.n)}</b></div>`).join('');
  }

  async function loadOverview() {
    const hero = $('#pm-kpis'); if (!hero) return;
    try {
      const j = await api('overview', days);
      renderKpis(j.overview); renderTrend(j.overview); renderRecs(j.recommendations); renderMiniFunnel(j.funnel);
      const u = $('#pm-updated'); if (u) u.textContent = tr('updated', { t: new Date().toLocaleTimeString(locale(), { hour: '2-digit', minute: '2-digit' }) });
    } catch (e) { fail($('#pm-kpis')); }
  }

  /* ───────────────────────── growth ───────────────────────── */
  function renderFunnel(f) {
    const host = $('#pm-funnel'); if (!host) return;
    const steps = f.steps, max = Math.max(1, ...steps.map(s => s.n));
    let worst = -1, worstLoss = 0;
    steps.forEach((s, i) => { if (i > 0 && steps[i - 1].n >= 3) { const loss = 1 - s.n / steps[i - 1].n; if (steps[i - 1].tracked === s.tracked || true) { if (loss > worstLoss && !(steps[i - 1].tracked && !s.tracked)) { worstLoss = loss; worst = i; } } } });
    host.innerHTML = steps.map((s, i) => {
      const w = Math.max(1.5, s.n / max * 100);
      const pct = s.of_prev === null ? '' : `<span class="pi-fn-pct ${i === worst && worstLoss > .15 ? 'is-worst' : ''}">${s.of_prev}%</span>`;
      const worstBadge = i === worst && worstLoss > .15 ? `<span class="pi-fn-badge">${esc(tr('fn_biggest'))}</span>` : '';
      const sep = i === 3 ? `<div class="pi-fn-sep"><span>${esc(f.tracking_since ? tr('fn_front_note', { d: shortDate(f.tracking_since) }) : tr('fn_front_none'))}</span></div>` : '';
      return `${sep}<div class="pi-fn ${s.tracked ? 'is-front' : ''}" style="--i:${i}"><span class="pi-fn-l">${esc(tr('fn_' + s.key))}</span><span class="pi-fn-track"><i style="width:${w}%"></i></span><b class="num">${fmt(s.n)}</b>${pct}${worstBadge}</div>`;
    }).join('');
  }

  function renderChannels(a) {
    const host = $('#pm-channels'); if (!host) return;
    if (!a.channels.length) { host.innerHTML = `<p class="note">${esc(tr('ch_none'))}</p>`; return; }
    host.innerHTML = `<div class="pi-table-wrap"><table class="pi-table"><thead><tr><th>${esc(tr('ch_channel'))}</th><th class="r">${esc(tr('ch_visits'))}</th><th class="r">${esc(tr('ch_signups'))}</th><th class="r">${esc(tr('ch_verified'))}</th><th class="r">${esc(tr('ch_enrolled'))}</th><th class="r">${esc(tr('ch_conv'))}</th></tr></thead><tbody>${a.channels.map(c => `<tr><td data-l="${esc(tr('ch_channel'))}">${c.label === null ? `<span class="muted">${esc(tr('ch_direct'))}</span>` : `<b>${esc(c.label)}</b>${c.is_campaign ? ` <span class="pi-tag">${esc(tr('ch_campaign'))}</span>` : ` <code>${esc(c.src)}</code>`}`}</td><td class="r num" data-l="${esc(tr('ch_visits'))}">${fmt(c.visits)}</td><td class="r num" data-l="${esc(tr('ch_signups'))}"><b>${fmt(c.signups)}</b></td><td class="r num" data-l="${esc(tr('ch_verified'))}">${fmt(c.verified)}</td><td class="r num" data-l="${esc(tr('ch_enrolled'))}">${fmt(c.enrolled)}</td><td class="r num" data-l="${esc(tr('ch_conv'))}">${c.conv === null ? '—' : c.conv + ' %'}</td></tr>`).join('')}</tbody></table></div>`;
  }

  function renderPages(a) {
    const host = $('#pm-pages'); if (!host) return;
    if (!a.pages.length) { host.innerHTML = `<p class="note">${esc(tr('pg_none'))}</p>`; return; }
    const max = Math.max(1, ...a.pages.map(p => +p.n));
    host.innerHTML = a.pages.map(p => `<div class="pi-mf"><span class="pi-mf-l">${esc(tr('pg_' + p.event))}</span><span class="pi-mf-bar"><i style="width:${Math.max(2, p.n / max * 100)}%"></i></span><b class="num">${fmt(+p.n)}</b></div>`).join('');
  }

  let growthLoaded = false, lastAcq = null;
  async function loadGrowth() {
    try {
      const j = await api('growth', days);
      renderFunnel(j.funnel); renderChannels(j.acquisition); renderPages(j.acquisition); lastAcq = j.acquisition; renderCampaigns(j.acquisition);
      growthLoaded = true;
    } catch (e) { fail($('#pm-funnel')); }
  }

  /* ───────────────────────── QR studio ───────────────────────── */
  const qr = { slug: '', saved: null, target: 'signup', ref: '' };
  const slugify = (s) => String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 30) || 'campagne';

  function qrLink(slug, target, ref) {
    const s = encodeURIComponent(slug);
    switch (target) {
      case 'home': return `${BASE}/index.php?src=${s}`;
      case 'course': return `${BASE}/join.php?course=${encodeURIComponent(ref)}&src=${s}`;
      case 'live': { const live = (window.PM_LIVE || []).find(l => String(l.id) === String(ref)); return live ? `${BASE}/live-session.php?code=${encodeURIComponent(live.code)}&src=${s}` : ''; }
      default: return `${BASE}/index.php?auth=signup&src=${s}`;
    }
  }
  function matrix(text) {
    const q = window.qrcode(0, 'Q'); q.addData(text); q.make();
    const n = q.getModuleCount(); const rows = [];
    for (let r = 0; r < n; r++) { const row = []; for (let c = 0; c < n; c++) row.push(q.isDark(r, c)); rows.push(row); }
    return rows;
  }
  function qrSvg(text, fg = '#1E1B16', bg = '#FFFFFF', margin = 4) {
    const m = matrix(text), n = m.length, size = n + margin * 2; let d = '';
    for (let r = 0; r < n; r++) { let c = 0; while (c < n) { if (m[r][c]) { let e = c; while (e < n && m[r][e]) e++; d += `M${c + margin} ${r + margin}h${e - c}v1h-${e - c}z`; c = e; } else c++; } }
    return `<svg xmlns="${ns}" viewBox="0 0 ${size} ${size}" shape-rendering="crispEdges"><rect width="${size}" height="${size}" fill="${bg}"/><path d="${d}" fill="${fg}"/></svg>`;
  }
  function qrPng(text, px = 1024) {
    return new Promise((res) => {
      const m = matrix(text), n = m.length, margin = 4, size = n + margin * 2, cell = Math.max(1, Math.floor(px / size)), W = cell * size;
      const cv = document.createElement('canvas'); cv.width = cv.height = W; const g = cv.getContext('2d');
      g.fillStyle = '#fff'; g.fillRect(0, 0, W, W); g.fillStyle = '#1E1B16';
      for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (m[r][c]) g.fillRect((c + margin) * cell, (r + margin) * cell, cell, cell);
      cv.toBlob(res, 'image/png');
    });
  }
  const download = (blob, name) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 3000); };

  function studioState() {
    const name = ($('#qr-name') || {}).value || '';
    const target = ($('#qr-target') || {}).value || 'signup';
    const ref = target === 'course' ? ($('#qr-course') || {}).value : target === 'live' ? ($('#qr-live') || {}).value : '';
    const slug = qr.saved ? qr.saved.slug : slugify(name);
    return { name: name.trim(), target, ref, slug, link: (target === 'course' || target === 'live') && !ref ? '' : qrLink(slug, target, ref) };
  }

  function paintStudio() {
    const st = studioState(), box = $('#qr-preview'), link = $('#qr-link');
    $$('[data-qr-ref]').forEach(el => { el.hidden = el.dataset.qrRef !== st.target; });
    if (!st.link) { box.innerHTML = `<p class="pi-qr-empty">${esc(tr(st.target === 'live' ? 'qr_need_ref' : 'qr_need_ref'))}</p>`; link.value = ''; return; }
    box.innerHTML = qrSvg(st.link);
    link.value = st.link;
  }

  async function ensureSaved() {
    const st = studioState();
    if (qr.saved && qr.saved.link === st.link) return qr.saved;
    if (!st.name) { toast(tr('qr_need_name')); $('#qr-name').focus(); return null; }
    if ((st.target === 'course' || st.target === 'live') && !st.ref) { toast(tr('qr_need_ref')); return null; }
    const fd = new FormData(); fd.append('action', 'create'); fd.append('name', st.name); fd.append('target', st.target); fd.append('target_ref', st.ref || '');
    const r = await fetch('/promoter/campaigns-api.php', { method: 'POST', body: fd, credentials: 'same-origin' }); const j = await r.json();
    if (!j.success) { toast(j.message || tr('error')); return null; }
    qr.saved = { slug: j.slug, link: qrLink(j.slug, st.target, st.ref), name: st.name, target: st.target };
    paintStudio(); toast(tr('qr_saved')); growthLoaded = false; loadGrowth();
    return qr.saved;
  }
  function toast(msg) { const t = $('#toasts'); if (!t) return; const d = document.createElement('div'); d.className = 'toast'; d.textContent = msg; t.appendChild(d); setTimeout(() => d.remove(), 3200); }

  function initStudio() {
    const root = $('#qr-studio'); if (!root || !window.qrcode) return;
    const course = $('#qr-course'), live = $('#qr-live');
    course.innerHTML = `<option value="">${esc(tr('qr_pick_course'))}</option>` + (window.PM_COURSES || []).map(c => `<option value="${c.id}">${esc(c.title)}</option>`).join('');
    live.innerHTML = `<option value="">${esc(tr('qr_pick_live'))}</option>` + (window.PM_LIVE || []).map(l => `<option value="${l.id}">${esc(l.title)}</option>`).join('');
    const reset = () => { qr.saved = null; paintStudio(); };
    ['qr-name', 'qr-target', 'qr-course', 'qr-live'].forEach(id => $('#' + id).addEventListener('input', reset));
    $('#qr-copy').addEventListener('click', async () => { const s = await ensureSaved(); if (!s) return; try { await navigator.clipboard.writeText(s.link); } catch (e) { $('#qr-link').select(); document.execCommand('copy'); } toast(tr('qr_copied')); });
    $('#qr-save').addEventListener('click', ensureSaved);
    $('#qr-png').addEventListener('click', async () => { const s = await ensureSaved(); if (!s) return; download(await qrPng(s.link), `qr-${s.slug}.png`); });
    $('#qr-svg').addEventListener('click', async () => { const s = await ensureSaved(); if (!s) return; download(new Blob([qrSvg(s.link)], { type: 'image/svg+xml' }), `qr-${s.slug}.svg`); });
    $('#qr-print').addEventListener('click', async () => {
      const s = await ensureSaved(); if (!s) return;
      const sheet = $('#qr-sheet'); $('.qs-qr', sheet).innerHTML = qrSvg(s.link, '#1E1B16', '#FFFFFF', 2); $('.qs-name', sheet).textContent = s.name; $('.qs-url', sheet).textContent = s.link.replace(/^https?:\/\//, '').replace(/&src=.*$|\?src=.*$/, '');
      document.body.classList.add('is-printing-qr'); setTimeout(() => { window.print(); }, 60);
    });
    addEventListener('afterprint', () => document.body.classList.remove('is-printing-qr'));
    paintStudio();
  }

  function renderCampaigns(a) {
    const host = $('#pm-campaigns'); if (!host) return;
    const stats = {}; a.channels.forEach(c => { stats[c.src] = c; });
    if (!a.campaigns.length) { host.innerHTML = `<p class="note">${esc(tr('cp_none'))}</p>`; return; }
    host.innerHTML = a.campaigns.map(c => {
      const s = stats[c.slug] || { visits: 0, signups: 0 };
      return `<article class="pi-cp ${+c.archived ? 'is-archived' : ''}"><div class="pi-cp-main"><b>${esc(c.name)}</b>${+c.archived ? ` <span class="pi-tag is-off">${esc(tr('cp_archived'))}</span>` : ''}<span class="muted">${esc(tr('qr_t_' + c.target))} · <code>${esc(c.slug)}</code></span></div><div class="pi-cp-stats"><span><b class="num">${fmt(s.visits)}</b> ${esc(tr('ch_visits'))}</span><span><b class="num">${fmt(s.signups)}</b> ${esc(tr('ch_signups'))}</span></div><div class="pi-cp-act"><button type="button" class="btn btn-ghost btn-sm" data-cp-show="${c.id}">${esc(tr('cp_show'))}</button><button type="button" class="btn btn-text btn-sm" data-cp-arch="${c.id}" data-to="${+c.archived ? 'restore' : 'archive'}">${esc(tr(+c.archived ? 'cp_restore' : 'cp_archive'))}</button></div></article>`;
    }).join('');
    host.onclick = async (e) => {
      const arch = e.target.closest('[data-cp-arch]'), show = e.target.closest('[data-cp-show]');
      if (arch) { const fd = new FormData(); fd.append('action', arch.dataset.to); fd.append('id', arch.dataset.cpArch); await fetch('/promoter/campaigns-api.php', { method: 'POST', body: fd, credentials: 'same-origin' }); loadGrowth(); }
      if (show) {
        const c = a.campaigns.find(x => String(x.id) === show.dataset.cpShow); if (!c) return;
        $('#qr-name').value = c.name; $('#qr-target').value = c.target;
        if (c.target === 'course') $('#qr-course').value = c.target_ref || ''; if (c.target === 'live') $('#qr-live').value = c.target_ref || '';
        qr.saved = { slug: c.slug, link: qrLink(c.slug, c.target, c.target_ref), name: c.name, target: c.target }; paintStudio();
        $('#qr-studio').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
      }
    };
  }

  /* ───────────────────────── insights ───────────────────────── */
  function renderEngagement(e) {
    const host = $('#pm-eng'); if (!host) return;
    const cells = [
      ['en_dau', e.dau], ['en_wau', e.wau], ['en_mau', e.mau], ['en_stick', e.stickiness === null ? '—' : e.stickiness + '%', 'en_stick_h'],
      ['en_ret', e.returning_14d, 'en_ret_h'], ['en_study', e.study_hours_30d], ['en_quiz', e.quiz_accuracy === null ? '—' : e.quiz_accuracy + '%'],
    ];
    host.innerHTML = cells.map(([k, v, h], i) => `<div class="pi-stat" style="--i:${i}" ${h ? `title="${esc(tr(h))}"` : ''}><b class="num">${typeof v === 'number' ? fmt(v) : esc(v)}</b><span>${esc(tr(k))}</span></div>`).join('');
  }
  function renderHeat(e) {
    const host = $('#pm-heat'); if (!host) return;
    const g = e.heatmap, max = Math.max(1, ...g.flat());
    let best = { n: 0, d: 0, h: 0 }; g.forEach((row, d) => row.forEach((n, h) => { if (n > best.n) best = { n, d, h }; }));
    let html = `<div class="pi-heat"><span></span>${Array.from({ length: 24 }, (_, h) => `<span class="pi-heat-h">${h % 3 === 0 ? h : ''}</span>`).join('')}`;
    g.forEach((row, d) => { html += `<span class="pi-heat-d">${esc(tr('wd' + d))}</span>` + row.map((n, h) => `<i style="--a:${(n / max).toFixed(2)}" title="${esc(tr('wdf' + d))} ${h}h : ${n}"></i>`).join(''); });
    host.innerHTML = html + `</div><div class="pi-heat-foot"><span>${esc(tr('heat_less'))}</span><i class="pi-heat-scale"></i><span>${esc(tr('heat_more'))}</span>${best.n ? `<b>${esc(tr('heat_best', { d: tr('wdf' + best.d), h: best.h }))}</b>` : ''}</div>`;
  }
  function renderCourses(l) {
    const host = $('#pm-courses'); if (!host) return;
    if (!l.courses.length) { host.innerHTML = `<p class="note">${esc(tr('co_none'))}</p>`; return; }
    host.innerHTML = `<div class="pi-table-wrap"><table class="pi-table"><thead><tr><th>${esc(tr('co_course'))}</th><th class="r">${esc(tr('co_enrolled'))}</th><th>${esc(tr('co_progress'))}</th><th class="r">${esc(tr('co_finished'))}</th><th class="r">${esc(tr('co_certs'))}</th></tr></thead><tbody>${l.courses.map(c => `<tr><td data-l="${esc(tr('co_course'))}"><b>${esc(c.title)}</b><span class="muted"> ${esc(c.teacher)}</span></td><td class="r num" data-l="${esc(tr('co_enrolled'))}">${fmt(c.enrolled)}</td><td data-l="${esc(tr('co_progress'))}"><span class="pi-bar"><i style="width:${c.avg_progress}%"></i></span><span class="num pi-bar-v">${c.avg_progress}%</span></td><td class="r num" data-l="${esc(tr('co_finished'))}">${fmt(c.finished)}</td><td class="r num" data-l="${esc(tr('co_certs'))}">${fmt(c.certs)}</td></tr>`).join('')}</tbody></table></div>`;
  }
  function renderStall(l) {
    const host = $('#pm-stall'); if (!host) return;
    if (!l.stalling.length) { host.innerHTML = `<p class="note">${esc(tr('st_none'))}</p>`; return; }
    host.innerHTML = l.stalling.map(s => `<div class="pi-stall"><div><b>${esc(s.title)}</b><span class="muted">${esc(s.course)}</span></div><div class="pi-stall-r"><span class="pi-bar is-warn"><i style="width:${s.rate}%"></i></span><span class="muted">${esc(tr('st_of', { done: s.done, opened: s.opened }))}</span></div></div>`).join('');
  }
  function renderLive(v) {
    const host = $('#pm-live'); if (!host) return;
    const cells = [['lv_sessions', v.sessions], ['lv_participants', v.participants], ['lv_completion', v.completion === null ? '—' : v.completion + '%'], ['lv_avg', v.avg_score + '%'], ['lv_biggest', v.biggest_room]];
    const up = v.upcoming.length ? v.upcoming.map(u => `<div class="pi-up"><b>${esc(u.title)}</b><span class="muted">${esc(u.course)} · ${esc(new Date(u.start_time.replace(' ', 'T')).toLocaleString(locale(), { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }))}</span><span class="pi-tag">${esc(tr('lv_registered', { n: u.registered }))}</span></div>`).join('') : `<p class="note">${esc(tr('lv_none'))}</p>`;
    host.innerHTML = `<div class="pi-stats">${cells.map(([k, val], i) => `<div class="pi-stat" style="--i:${i}"><b class="num">${typeof val === 'number' ? fmt(val) : esc(val)}</b><span>${esc(tr(k))}</span></div>`).join('')}</div><h3 class="pi-sub">${esc(tr('lv_upcoming'))}</h3>${up}`;
  }
  let insightsLoaded = false;
  async function loadInsights() {
    try { const j = await api('insights', days); renderEngagement(j.engagement); renderHeat(j.engagement); renderCourses(j.learning); renderStall(j.learning); renderLive(j.live); insightsLoaded = true; }
    catch (e) { fail($('#pm-eng')); }
  }

  /* ───────────────────────── wiring ───────────────────────── */
  function wireGoto(root) {
    $$('[data-goto]', root).forEach(b => { if (b._pi) return; b._pi = true; b.addEventListener('click', () => { const id = b.dataset.goto; if (location.hash === '#' + id) dispatchEvent(new HashChangeEvent('hashchange')); else location.hash = id; }); });
  }
  function setRange(d) {
    days = d; $$('[data-range]').forEach(b => b.setAttribute('aria-pressed', String(+b.dataset.range === d)));
    loadOverview(); if (!$('#tab-growth').hidden || growthLoaded) { growthLoaded = false; loadGrowth(); }
  }
  function boot() {
    $$('[data-range]').forEach(b => b.addEventListener('click', () => setRange(+b.dataset.range)));
    wireGoto(document);
    const obs = (id, fn) => { const p = $('#' + id); if (!p) return; const check = () => { if (!p.hidden) fn(); }; new MutationObserver(check).observe(p, { attributes: true, attributeFilter: ['hidden'] }); check(); };
    obs('tab-growth', () => { if (!growthLoaded) loadGrowth(); });
    obs('tab-insights', () => { if (!insightsLoaded) loadInsights(); });
    $$('[data-qr-open]').forEach(b => b.addEventListener('click', () => { location.hash = 'tab-growth'; setTimeout(() => { const s = $('#qr-studio'); if (s) s.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); }, 120); }));
    initStudio();
    loadOverview();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
