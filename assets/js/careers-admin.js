/* Careers workspace (promoter/careers/). Plain JavaScript, no dependencies. Data comes from window.CAA (printed by index.php) and api.php.
   Sections: overview, positions, applications (inbox), screening (what the ATS held back), interviews; a drawer for one candidate; dialogs
   for the position editor and the interview scheduler. */
(function () {
  'use strict';
  var C = window.CAA, T = C.t;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var csrf = ($('meta[name="csrf-token"]') || {}).content || '';
  var fmt = function (tpl, o) { return String(tpl).replace(/\{(\w+)\}/g, function (_, k) { return o && o[k] != null ? o[k] : ''; }); };
  var plural = function (n, k) { return fmt(T[k + (n === 1 ? '_one' : '_other')], { n: n }); };
  var json = function (v) { try { return v ? JSON.parse(v) : null; } catch (e) { return null; } };

  var S = { tab: 'overview', stats: C.boot.stats, jobs: C.boot.jobs || [], apps: C.boot.apps || [], interviews: C.boot.interviews || [], jobFilter: 'all',
            f: { q: '', job: '', status: '', starred: false, sort: 'new' }, view: 'list', scrView: 'held', sel: {}, openId: null, opener: null, ivFilter: 'upcoming', sq: '' };
  try { S.view = localStorage.getItem('cx_view') || 'list'; } catch (e) {}

  /* ---------- helpers ---------- */
  function date(s, withTime) {
    if (!s) return '';
    var d = new Date(String(s).replace(' ', 'T')); if (isNaN(d)) return s;
    var out = d.getDate() + ' ' + T.months[d.getMonth()] + ' ' + d.getFullYear();
    if (withTime) out += ' · ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
    return out;
  }
  function hm(s) { var d = new Date(String(s).replace(' ', 'T')); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
  function ago(s) {
    var d = new Date(String(s).replace(' ', 'T')); if (isNaN(d)) return '';
    var m = Math.round((Date.now() - d.getTime()) / 60000), en = C.lang === 'en';
    if (m < 1) return en ? 'just now' : 'à l’instant';
    if (m < 60) return en ? m + ' min ago' : 'il y a ' + m + ' min';
    var h = Math.round(m / 60); if (h < 24) return en ? h + ' h ago' : 'il y a ' + h + ' h';
    var dd = Math.round(h / 24); if (dd < 8) return en ? dd + ' d ago' : 'il y a ' + dd + ' j';
    return date(s);
  }
  function until(s) {
    var m = Math.round((new Date(String(s).replace(' ', 'T')).getTime() - Date.now()) / 60000), en = C.lang === 'en';
    if (m < 0) return '';
    if (m < 60) return en ? 'in ' + m + ' min' : 'dans ' + m + ' min';
    var h = Math.round(m / 60); if (h < 24) return en ? 'in ' + h + ' h' : 'dans ' + h + ' h';
    var d = Math.round(h / 24); return en ? 'in ' + d + ' d' : 'dans ' + d + ' j';
  }
  function initials(n) { var p = String(n).trim().split(/\s+/); return ((p[0] || '')[0] || '?').toUpperCase() + ((p.length > 1 ? p[p.length - 1][0] : '') || '').toUpperCase(); }
  function hue(s) { var h = 0; for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 360; return h; }
  function avatar(n) { return '<span class="cx-av" style="--h:' + hue(n) + '" aria-hidden="true">' + esc(initials(n)) + '</span>'; }
  function pill(st) { return '<span class="cx-pill cx-st-' + esc(st) + '">' + esc(T.as[st] || T.st[st] || st) + '</span>'; }
  function jobPill(st) { return '<span class="cx-pill cx-js-' + esc(st) + '">' + esc(T.st[st] || st) + '</span>'; }
  function ivPill(st) { return '<span class="cx-pill cx-iv-' + esc(st) + '">' + esc(T.ivst[st] || st) + '</span>'; }
  function score(a) {
    if (a.screen_score == null || a.screen_score === '') return '<span class="cx-score cx-v-none" title="' + esc(T.scr_pending) + '">–</span>';
    var v = a.screen_verdict || 'review';
    return '<span class="cx-score cx-v-' + v + '" title="' + esc(T.verdict[v] + ' · ' + a.screen_score + '/100') + '">' + a.screen_score + '</span>';
  }
  function stars(n, cls) { var o = ''; for (var i = 1; i <= 5; i++) o += '<svg class="cx-star' + (i <= n ? ' on' : '') + '" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>'; return '<span class="' + (cls || 'cx-stars') + '" role="img" aria-label="' + n + '/5">' + o + '</span>'; }
  var I = {
    plus: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
    x: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    star: '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>',
    dl: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11"/><path d="m7 11 5 5 5-5"/><path d="M5 20h14"/></svg>',
    ext: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7"/><path d="M8 7h9v9"/></svg>',
    mail: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
    cal: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
    link: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>',
    inbox: '<svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 28 12 10h24l6 18"/><path d="M6 28v8a2 2 0 0 0 2 2h32a2 2 0 0 0 2-2v-8H30a6 6 0 0 1-12 0z"/></svg>',
    brief: '<svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="14" width="36" height="26" rx="4"/><path d="M17 14v-3a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v3"/><path d="M6 26h36"/><path d="M21 26v3h6v-3"/></svg>',
    md: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 17v-4l2 2.500 2-2.500v4"/></svg>',
    filter: '<svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 10h36L28 26v12l-8 4V26z"/></svg>',
    clock: '<svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="9" width="36" height="32" rx="4"/><path d="M6 19h36M15 5v8M33 5v8"/><circle cx="24" cy="30" r="5"/><path d="M24 28v2.5l1.8 1"/></svg>'
  };

  /* ---------- network ---------- */
  function api(action, data) {
    return fetch('/promoter/careers/api.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(Object.assign({ action: action }, data || {}))
    }).then(function (r) { return r.json().catch(function () { return { success: false, message: T.err }; }).then(function (d) { d._status = r.status; return d; }); })
      .catch(function () { return { success: false, message: T.net }; });
  }
  function toast(msg, kind) {
    var box = $('#cx-toasts'), n = document.createElement('div');
    n.className = 'cx-toast' + (kind === 'err' ? ' err' : ''); n.setAttribute('role', kind === 'err' ? 'alert' : 'status'); n.textContent = msg;
    box.appendChild(n); requestAnimationFrame(function () { n.classList.add('in'); });
    setTimeout(function () { n.classList.remove('in'); setTimeout(function () { n.remove(); }, 300); }, kind === 'err' ? 5200 : 2800);
  }
  function confirmBox(title, text, okLabel) {
    return new Promise(function (res) {
      var d = $('#cx-confirm'); $('#cx-confirm-t').textContent = title; $('#cx-confirm-p').textContent = text || ''; $('#cx-confirm-ok').textContent = okLabel || T.yes_delete;
      d.returnValue = ''; d.addEventListener('close', function once() { d.removeEventListener('close', once); res(d.returnValue === 'ok'); });
      d.showModal();
    });
  }
  /* Everything is read again in one call after a change (counters, buckets, interviews) */
  function reload() { return api('bootstrap').then(function (d) { if (d.success) { S.jobs = d.jobs; S.apps = d.apps; S.stats = d.stats; S.interviews = d.interviews || []; } }); }

  /* ---------- derived data ---------- */
  var inbox = function () { return S.apps.filter(function (a) { return a.bucket === 'inbox'; }); };
  var held = function () { return S.apps.filter(function (a) { return a.bucket === 'screened_out'; }); };
  var archived = function () { return S.apps.filter(function (a) { return a.bucket === 'archived'; }); };
  function counts() {
    var c = { total: 0, by: {} };
    C.appStatuses.forEach(function (k) { c.by[k] = 0; });
    inbox().forEach(function (a) { c.total++; c.by[a.status] = (c.by[a.status] || 0) + 1; });
    return c;
  }
  function openIvs() { return S.interviews.filter(function (i) { return ['scheduled', 'confirmed', 'reschedule_requested', 'declined'].indexOf(i.status) > -1 && new Date(i.scheduled_at.replace(' ', 'T')) > new Date(); }); }
  function badges() {
    var c = counts(), open = S.jobs.filter(function (j) { return j.is_open_now; }).length;
    var set = function (k, n) { var b = $('[data-count="' + k + '"]'); if (b) { b.textContent = n; b.hidden = !n; } };
    set('apps', c.by['new'] || 0); set('jobs', open); set('screening', held().length);
    set('interviews', S.interviews.filter(function (i) { return i.status === 'declined' || i.status === 'reschedule_requested'; }).length);
  }
  function jobTitle(a) { return a.job_title || T.spont; }

  /* ---------- tabs ---------- */
  var panels = { overview: renderOverview, jobs: renderJobs, apps: renderApps, screening: renderScreening, interviews: renderInterviews };
  function go(tab, opts) {
    if (!panels[tab]) tab = 'overview';
    S.tab = tab;
    $$('[data-tab]').forEach(function (b) { var on = b.dataset.tab === tab; b.setAttribute('aria-selected', on); b.tabIndex = on ? 0 : -1; });
    Object.keys(panels).forEach(function (k) { $('#panel-' + k).hidden = k !== tab; });
    panels[tab]();
    if (!opts || !opts.silent) { if (location.hash !== '#' + tab) history.replaceState(null, '', '#' + tab); }
    badges();
    softReload(tab);
  }
  /* Candidates answer and applications arrive while the page is open: opening a section reads the latest data again (at most every 8 s) */
  var lastLoad = Date.now();
  function softReload(tab) {
    if (Date.now() - lastLoad < 8000) return;
    lastLoad = Date.now();
    var before = JSON.stringify([S.apps.length, S.interviews.map(function (i) { return i.id + i.status + i.scheduled_at; })]);
    reload().then(function () {
      var after = JSON.stringify([S.apps.length, S.interviews.map(function (i) { return i.id + i.status + i.scheduled_at; })]);
      if (S.tab === tab && drawer.hidden && !$('dialog[open]') && (before !== after || tab === 'overview')) rerender();
      else badges();
    });
  }
  $$('[data-tab]').forEach(function (b, i, all) {
    b.addEventListener('click', function () { go(b.dataset.tab); window.scrollTo({ top: 0 }); });
    b.addEventListener('keydown', function (e) {
      var j = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : -1;
      if (j >= 0) { e.preventDefault(); var n = all[(j + all.length) % all.length]; n.focus(); n.click(); }
    });
  });
  window.addEventListener('hashchange', function () { var h = location.hash.slice(1); if (panels[h] && h !== S.tab) go(h, { silent: true }); });
  function rerender() { badges(); panels[S.tab](); }

  /* ======================================================= OVERVIEW */
  function renderOverview() {
    var c = counts(), st = S.stats || {}, scr = st.screening || {}, open = S.jobs.filter(function (j) { return j.is_open_now; }).length;
    var pipe = (c.by.interview || 0) + (c.by.offer || 0);
    var cards = [
      [T.stat_open, open, '', 'jobs'], [T.stat_apps, c.total, fmt(T.stat_week, { n: st.this_week || 0 }), 'apps'],
      [T.stat_new, c.by['new'] || 0, scr.strong_new ? fmt(T.stat_strong, { n: scr.strong_new }) : '', 'apps:new'], [T.stat_pipe, pipe, '', 'apps:pipe']
    ];
    var html = '<div class="cx-stats">' + cards.map(function (x, i) {
      return '<button type="button" class="cx-stat cx-stat-' + i + '" data-go="' + x[3] + '"><span class="cx-stat-l">' + esc(x[0]) + '</span><span class="cx-stat-n num">' + x[1] + '</span><span class="cx-stat-s">' + esc(x[2]) + '</span></button>';
    }).join('') + '</div>';

    var days = Object.keys(st.days || {}).sort(), vals = days.map(function (d) { return st.days[d]; }), max = Math.max.apply(null, vals.concat([1]));
    var bars = days.map(function (d, i) {
      var h = Math.round((vals[i] / max) * 100), dt = new Date(d + 'T00:00:00');
      return '<div class="cx-bar" title="' + esc(date(d)) + ' · ' + vals[i] + '"><i style="height:' + Math.max(h, vals[i] ? 6 : 2) + '%" class="' + (vals[i] ? 'has' : '') + '"></i><span>' + (i % 2 === 0 || days.length < 8 ? dt.getDate() : '') + '</span></div>';
    }).join('');
    var stages = C.appStatuses.filter(function (k) { return k !== 'withdrawn'; });
    var funnelMax = Math.max.apply(null, stages.map(function (k) { return c.by[k] || 0; }).concat([1]));
    var funnel = stages.map(function (k) {
      return '<button type="button" class="cx-fn" data-status="' + k + '"><span>' + esc(T.as[k]) + '</span><i><b class="cx-st-' + k + '" style="width:' + ((c.by[k] || 0) / funnelMax * 100) + '%"></b></i><em class="num">' + (c.by[k] || 0) + '</em></button>';
    }).join('');

    var attn = [], today = new Date().toISOString().slice(0, 10), soon = new Date(Date.now() + 7 * 864e5).toISOString().slice(0, 10);
    S.jobs.forEach(function (j) {
      if (+j.new_applicants > 0) attn.push({ t: fmt(T.attn_new, { n: j.new_applicants, job: j.title }), go: 'job:' + j.id });
      if (j.is_open_now && j.closes_at && j.closes_at >= today && j.closes_at <= soon) attn.push({ t: fmt(T.attn_closing, { job: j.title, date: date(j.closes_at) }), go: 'jobs' });
    });
    if (held().length) attn.push({ t: fmt(T.attn_held, { n: held().length }), go: 'screening' });
    S.interviews.filter(function (i) { return i.status === 'declined' || i.status === 'reschedule_requested'; }).forEach(function (i) { attn.push({ t: fmt(T.attn_iv, { name: i.full_name }), go: 'interviews' }); });
    var recent = inbox().slice(0, 6), nextIv = openIvs().slice(0, 4);

    html += '<div class="cx-grid2">'
      + '<section class="cx-card"><header class="cx-card-h"><div><h2>' + esc(T.chart_h) + '</h2><p>' + esc(T.chart_sub) + '</p></div></header><div class="cx-chart" role="img" aria-label="' + esc(T.chart_h + ', ' + T.chart_sub) + '">' + bars + '</div></section>'
      + '<section class="cx-card"><header class="cx-card-h"><h2>' + esc(T.funnel_h) + '</h2></header><div class="cx-funnel">' + funnel + '</div></section></div>'
      + '<div class="cx-grid3">'
      + '<section class="cx-card"><header class="cx-card-h"><h2>' + esc(T.scr_card_h) + '</h2><button type="button" class="cx-link" data-go="screening">' + esc(T.see_all) + '</button></header>'
      + '<dl class="cx-kv"><div><dt>' + esc(T.scr_held) + '</dt><dd class="num">' + (scr.held || 0) + '</dd></div><div><dt>' + esc(T.scr_strong) + '</dt><dd class="num">' + (scr.strong_new || 0) + '</dd></div><div><dt>' + esc(T.scr_week) + '</dt><dd class="num">' + (scr.week || 0) + '</dd></div><div><dt>' + esc(T.scr_avg) + '</dt><dd class="num">' + (scr.avg || 0) + '</dd></div>'
      + (scr.pending ? '<div><dt>' + esc(T.scr_pending) + '</dt><dd class="num">' + scr.pending + '</dd></div>' : '') + '</dl></section>'
      + '<section class="cx-card"><header class="cx-card-h"><h2>' + esc(T.iv_next) + '</h2><button type="button" class="cx-link" data-go="interviews">' + esc(T.see_all) + '</button></header>'
      + (nextIv.length ? '<ul class="cx-mini">' + nextIv.map(function (i) { var d = new Date(i.scheduled_at.replace(' ', 'T')); return '<li><button type="button" data-open="' + i.app_id + '"><span class="cx-mini-d"><b>' + d.getDate() + '</b><small>' + esc(T.months[d.getMonth()]) + '</small></span><span class="cx-mini-t"><strong>' + esc(i.full_name) + '</strong><small>' + esc(hm(i.scheduled_at)) + ' · ' + esc(i.job_title || T.spont) + '</small></span>' + ivPill(i.status) + '</button></li>'; }).join('') + '</ul>'
        : '<div class="cx-empty-s">' + I.clock + '<p>' + esc(T.iv_none) + '</p></div>') + '</section>'
      + '<section class="cx-card"><header class="cx-card-h"><h2>' + esc(T.attn_h) + '</h2></header>'
      + (attn.length ? '<ul class="cx-attn">' + attn.slice(0, 7).map(function (a) { return '<li><button type="button" data-go="' + esc(a.go) + '"><span class="cx-dot"></span>' + esc(a.t) + '</button></li>'; }).join('') + '</ul>' : '<p class="cx-muted">' + esc(T.attn_none) + '</p>') + '</section></div>'
      + '<section class="cx-card cx-card-wide"><header class="cx-card-h"><h2>' + esc(T.recent_h) + '</h2>' + (inbox().length ? '<button type="button" class="cx-link" data-go="apps">' + esc(T.see_all) + '</button>' : '') + '</header>'
      + (recent.length ? '<ul class="cx-mini cx-mini-2">' + recent.map(function (a) { return '<li><button type="button" data-open="' + a.id + '">' + avatar(a.full_name) + '<span class="cx-mini-t"><strong>' + esc(a.full_name) + '</strong><small>' + esc(jobTitle(a)) + ' · ' + esc(ago(a.created_at)) + '</small></span>' + score(a) + pill(a.status) + '</button></li>'; }).join('') + '</ul>'
                      : '<div class="cx-empty-s">' + I.inbox + '<p>' + esc(T.recent_empty) + '</p></div>') + '</section>';
    $('#panel-overview').innerHTML = html;
  }

  /* ======================================================= JOBS */
  function jobActions(j) {
    var b = [];
    if (j.status === 'draft') b.push(['status:open', T.publish, 'primary']);
    if (j.status === 'open') b.push(['status:paused', T.pause, '']);
    if (j.status === 'paused' || j.status === 'closed') b.push(['status:open', T.reopen, 'primary']);
    if (j.status === 'open' || j.status === 'paused') b.push(['status:closed', T.close_job, '']);
    return b;
  }
  function renderJobs() {
    var f = S.jobFilter, list = S.jobs.filter(function (j) { return f === 'all' || j.status === f; });
    var chips = ['all'].concat(C.jobStatuses).map(function (k) {
      var n = k === 'all' ? S.jobs.length : S.jobs.filter(function (j) { return j.status === k; }).length;
      return '<button type="button" class="cx-chip' + (f === k ? ' on' : '') + '" data-jf="' + k + '" aria-pressed="' + (f === k) + '">' + esc(k === 'all' ? T.all : T.st[k]) + ' <b class="num">' + n + '</b></button>';
    }).join('');
    var html = '<div class="cx-bar-h"><div><h1>' + esc(T.jobs_h) + '</h1></div><div class="cx-bar-a"><button type="button" class="cx-btn cx-btn-ghost" data-mdimport>' + I.md + '<span class="cx-hide-xs">' + esc(T.md_btn) + '</span></button><button type="button" class="cx-btn cx-btn-primary" data-newjob>' + I.plus + '<span>' + esc(T.new_job) + '</span></button></div></div><div class="cx-chips" role="group">' + chips + '</div>';
    if (!list.length) {
      html += '<div class="cx-empty">' + I.brief + '<h2>' + esc(T.no_jobs_h) + '</h2><p>' + esc(T.no_jobs_p) + '</p><button type="button" class="cx-btn cx-btn-primary" data-newjob>' + I.plus + '<span>' + esc(T.new_job) + '</span></button></div>';
    } else {
      html += '<ul class="cx-jobs">' + list.map(function (j) {
        var n = +j.applicants, meta = [C.teams[j.department] || j.department, j.location, T.modes[j.work_mode], T.types[j.employment_type]].filter(Boolean).map(esc).join(' · ');
        return '<li class="cx-job" data-job="' + j.id + '"><div class="cx-job-main"><div class="cx-job-top"><h3>' + esc(j.title) + '</h3>' + jobPill(j.status) + '<span class="cx-code" title="' + esc(T.l_code) + '">' + esc(j.code) + '</span>' + (+j.screen_enabled ? '<span class="cx-tag" title="' + esc(T.ats_on) + '">ATS</span>' : '') + '</div>'
          + '<p class="cx-job-meta">' + meta + '</p>'
          + '<p class="cx-job-sub">' + (j.closes_at ? esc(fmt(T.closes, { date: date(j.closes_at) })) + ' · ' : '') + esc(fmt(T.updated, { date: date(j.updated_at) })) + (+j.held_applicants ? ' · ' + esc(fmt(T.n_held, { n: j.held_applicants })) : '') + '</p></div>'
          + '<button type="button" class="cx-job-apps" data-jobapps="' + j.id + '" aria-label="' + esc(T.view_apps + ': ' + plural(n, 'n_apps')) + '"><b class="num">' + n + '</b><span>' + esc(plural(n, 'n_apps').replace(/^\d+\s*/, '')) + '</span>' + (+j.new_applicants ? '<em class="num">' + esc(fmt(T.n_new, { n: j.new_applicants })) + '</em>' : '') + '</button>'
          + '<div class="cx-job-act">' + jobActions(j).map(function (a) { return '<button type="button" class="cx-btn cx-btn-sm ' + (a[2] === 'primary' ? 'cx-btn-primary' : 'cx-btn-ghost') + '" data-jact="' + a[0] + '" data-id="' + j.id + '">' + esc(a[1]) + '</button>'; }).join('')
          + '<button type="button" class="cx-btn cx-btn-sm cx-btn-ghost" data-jedit="' + j.id + '">' + esc(T.edit) + '</button>'
          + '<a class="cx-btn cx-btn-sm cx-btn-ghost" href="/jobs/' + encodeURIComponent(j.public_id) + '" target="_blank" rel="noopener">' + esc(j.status === 'open' ? T.view : T.preview) + ' ' + I.ext + '</a>'
          + (n === 0 ? '<button type="button" class="cx-btn cx-btn-sm cx-btn-quiet" data-jdel="' + j.id + '">' + esc(T.delete) + '</button>' : '') + '</div></li>';
      }).join('') + '</ul>';
    }
    $('#panel-jobs').innerHTML = html;
  }

  /* ---------- job editor ---------- */
  function field(name, label, inner, hint, cls) { return '<div class="cx-f ' + (cls || '') + '"><label for="jf-' + name + '">' + esc(label) + '</label>' + inner + (hint ? '<p class="cx-hint">' + esc(hint) + '</p>' : '') + '<p class="cx-err" data-err="' + name + '"></p></div>'; }
  function opts(map, sel) { return Object.keys(map).map(function (k) { return '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + esc(map[k]) + '</option>'; }).join(''); }
  function openJobEditor(job, derived) {
    var j = job || { title: '', department: '', location: '', work_mode: 'onsite', employment_type: 'full_time', level: '', openings: 1, closes_at: '', salary_note: '', summary: '', description: '', responsibilities: '', requirements: '', nice_to_have: '', benefits: '', status: 'draft',
                     screen_enabled: 1, must_have: '', nice_have: '', min_years: 0, min_education: '', screen_threshold: 35, auto_decline_days: 0 };
    var d = $('#cx-job-dlg'), v = function (k) { return esc(j[k] == null ? '' : j[k]); };
    var eduOnly = {}; Object.keys(T.edu).forEach(function (k) { if (k !== 'other') eduOnly[k] = T.edu[k]; });
    d.innerHTML = '<form id="cx-job-form" novalidate>'
      + '<header class="cx-dlg-h"><h2 id="cx-job-title">' + esc(job ? T.job_edit_title : T.job_new_title) + '</h2><button type="button" class="cx-icon" data-dlgclose aria-label="' + esc(T.close) + '">' + I.x + '</button></header>'
      + '<div class="cx-dlg-b">'
      + '<fieldset><legend>' + esc(T.s_basics) + '</legend><div class="cx-fg">'
      + field('title', T.l_title, '<input class="cx-in" id="jf-title" name="title" maxlength="160" required value="' + v('title') + '" placeholder="' + esc(T.ph_title) + '">', '', 'full')
      + field('code', T.l_code, '<input class="cx-in cx-code-in" id="jf-code" name="code" maxlength="4" value="' + v('code') + '" placeholder="' + esc(T.ph_code) + '" autocomplete="off" spellcheck="false" autocapitalize="characters">', T.h_code, 'full')
      + field('department', T.l_dept, '<select class="cx-in" id="jf-department" name="department" required><option value="">' + esc(T.choose_team) + '</option>' + opts(C.teams, j.department) + '</select>')
      + field('location', T.l_loc, '<input class="cx-in" id="jf-location" name="location" maxlength="120" value="' + v('location') + '" placeholder="' + esc(T.ph_loc) + '">')
      + field('work_mode', T.l_mode, '<select class="cx-in" id="jf-work_mode" name="work_mode">' + opts(T.modes, j.work_mode) + '</select>')
      + field('employment_type', T.l_type, '<select class="cx-in" id="jf-employment_type" name="employment_type">' + opts(T.types, j.employment_type) + '</select>')
      + field('level', T.l_level, '<input class="cx-in" id="jf-level" name="level" maxlength="40" value="' + v('level') + '" placeholder="' + esc(T.ph_level) + '">')
      + field('openings', T.l_openings, '<input class="cx-in" id="jf-openings" name="openings" type="number" min="1" max="99" value="' + (j.openings || 1) + '">')
      + field('salary_note', T.l_salary, '<input class="cx-in" id="jf-salary_note" name="salary_note" maxlength="160" value="' + v('salary_note') + '" placeholder="' + esc(T.ph_salary) + '">')
      + field('closes_at', T.l_closes, '<input class="cx-in" id="jf-closes_at" name="closes_at" type="date" value="' + v('closes_at') + '">')
      + '</div></fieldset>'
      + '<fieldset><legend>' + esc(T.s_content) + '</legend><div class="cx-fg">'
      + field('summary', T.l_summary, '<textarea class="cx-in" id="jf-summary" name="summary" rows="2" maxlength="400">' + v('summary') + '</textarea>', T.h_summary, 'full')
      + field('description', T.l_desc, '<textarea class="cx-in" id="jf-description" name="description" rows="6" maxlength="6000">' + v('description') + '</textarea>', T.h_desc, 'full')
      + field('responsibilities', T.l_resp, '<textarea class="cx-in" id="jf-responsibilities" name="responsibilities" rows="5">' + v('responsibilities') + '</textarea>', T.h_lines)
      + field('requirements', T.l_req, '<textarea class="cx-in" id="jf-requirements" name="requirements" rows="5">' + v('requirements') + '</textarea>', T.h_lines)
      + field('nice_to_have', T.l_nice, '<textarea class="cx-in" id="jf-nice_to_have" name="nice_to_have" rows="4">' + v('nice_to_have') + '</textarea>', T.h_lines)
      + field('benefits', T.l_ben, '<textarea class="cx-in" id="jf-benefits" name="benefits" rows="4">' + v('benefits') + '</textarea>', T.h_lines)
      + '</div></fieldset>'
      + '<fieldset class="cx-ats"><legend>' + esc(T.s_screening) + '</legend><p class="cx-hint cx-ats-intro">' + esc(T.ats_intro) + '</p>'
      + '<label class="cx-check cx-ats-toggle"><input type="checkbox" id="jf-screen_enabled"' + (+j.screen_enabled ? ' checked' : '') + '><span>' + esc(T.l_screen_on) + '</span></label>'
      + '<div class="cx-fg" id="jf-ats-fields">'
      + field('must_have', T.l_must, '<textarea class="cx-in" id="jf-must_have" name="must_have" rows="5" placeholder="' + esc(T.ph_must) + '">' + v('must_have') + '</textarea>', T.h_must)
      + field('nice_have', T.l_nicekw, '<textarea class="cx-in" id="jf-nice_have" name="nice_have" rows="5" placeholder="' + esc(T.ph_nice) + '">' + v('nice_have') + '</textarea>', T.h_nicekw)
      + field('min_years', T.l_minyears, '<input class="cx-in" id="jf-min_years" name="min_years" type="number" min="0" max="40" value="' + (j.min_years || 0) + '">', T.h_minyears)
      + field('min_education', T.l_minedu, '<select class="cx-in" id="jf-min_education" name="min_education"><option value="">' + esc(T.none) + '</option>' + opts(eduOnly, j.min_education) + '</select>')
      + field('screen_threshold', T.l_threshold, '<div class="cx-range"><input id="jf-screen_threshold" name="screen_threshold" type="range" min="10" max="80" step="5" value="' + (j.screen_threshold || 35) + '"><output id="jf-thr-out" class="num">' + (j.screen_threshold || 35) + '</output></div>', T.h_threshold)
      + field('auto_decline_days', T.l_autodecline, '<input class="cx-in" id="jf-auto_decline_days" name="auto_decline_days" type="number" min="0" max="90" value="' + (j.auto_decline_days || 0) + '">', T.h_autodecline)
      + '</div>'
      + (derived && derived.length ? '<p class="cx-derived"><b>' + esc(T.derived_h) + '</b> ' + derived.map(function (k) { return '<span class="cx-tag">' + esc(k) + '</span>'; }).join(' ') + '</p>' : '')
      + '</fieldset>'
      + '<fieldset><legend>' + esc(T.s_publish) + '</legend>'
      + field('status', T.l_status, '<select class="cx-in" id="jf-status" name="status">' + opts(T.st, j.status) + '</select>', '', '')
      + '<p class="cx-hint" id="jf-status-help">' + esc(T.status_help[j.status]) + '</p></fieldset>'
      + '<p class="cx-banner" id="jf-banner" role="alert" hidden></p></div>'
      + '<footer class="cx-dlg-f"><button type="button" class="cx-btn cx-btn-ghost" data-dlgclose>' + esc(T.cancel) + '</button>'
      + '<button type="submit" class="cx-btn cx-btn-ghost" data-keep="1">' + esc(T.save) + '</button>'
      + '<button type="submit" class="cx-btn cx-btn-primary" data-publish="1" id="jf-pub"' + (j.status === 'open' ? ' hidden' : '') + '>' + esc(T.save_publish) + '</button></footer></form>';
    var f = $('#cx-job-form', d), sel = $('#jf-status', d), tog = $('#jf-screen_enabled', d), thr = $('#jf-screen_threshold', d), code = $('#jf-code', d);
    code.addEventListener('input', function () { code.value = code.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 4); });
    sel.addEventListener('change', function () { $('#jf-status-help', d).textContent = T.status_help[sel.value]; $('#jf-pub', d).hidden = sel.value === 'open'; });
    var dim = function () { $('#jf-ats-fields', d).classList.toggle('cx-dim', !tog.checked); };
    tog.addEventListener('change', dim); dim();
    thr.addEventListener('input', function () { $('#jf-thr-out', d).textContent = thr.value; });
    $$('[data-dlgclose]', d).forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
    var pub = false;
    $$('button[type=submit]', f).forEach(function (b) { b.addEventListener('click', function () { pub = !!b.dataset.publish; }); });
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var data = {}; new FormData(f).forEach(function (val, k) { data[k] = val; });
      data.screen_enabled = tog.checked ? 1 : 0;
      if (job) data.id = job.id; if (pub) data.status = 'open';
      $$('.cx-err', f).forEach(function (p) { p.textContent = ''; p.classList.remove('show'); }); $$('.cx-in.bad', f).forEach(function (i) { i.classList.remove('bad'); }); $('#jf-banner', f).hidden = true;
      var btns = $$('button[type=submit]', f); btns.forEach(function (b) { b.disabled = true; });
      api('job_save', data).then(function (r) {
        btns.forEach(function (b) { b.disabled = false; });
        if (r.success) { d.close(); toast(data.status === 'open' && (!job || job.status !== 'open') ? T.published : T.saved); return reload().then(function () { rerender(); }); }
        if (r.fields) {
          var first = null;
          Object.keys(r.fields).forEach(function (k) {
            var p = $('[data-err="' + k + '"]', f), i = f.elements[k], msg = T['err_' + k + '_' + r.fields[k]] || T['err_' + k] || T.err_generic;
            if (p) { p.textContent = msg; p.classList.add('show'); } if (i) { i.classList.add('bad'); if (!first) first = i; }
          });
          if (first) first.focus();
        }
        var bn = $('#jf-banner', f); bn.textContent = r.fields ? T.err_generic : (r.message || T.err); bn.hidden = false;
      });
    });
    if (!d.open) d.showModal();
    setTimeout(function () { var t = $('#jf-title', d); if (t && !job) t.focus(); }, 40);
  }

  /* ======================================================= APPLICATIONS (inbox) */
  function reasons(a, max) {
    var out = [], ko = json(a.rep_ko) || [], miss = json(a.rep_missing) || [], flags = json(a.rep_flags) || [];
    ko.forEach(function (k) { out.push('<span class="cx-rs cx-rs-ko" title="' + esc(T.flag_knockout) + '">! ' + esc(k) + '</span>'); });
    miss.filter(function (m) { return ko.indexOf(m) < 0; }).slice(0, Math.max(0, max - out.length)).forEach(function (k) { out.push('<span class="cx-rs">− ' + esc(k) + '</span>'); });
    flags.slice(0, 2).forEach(function (f) { if (f.code !== 'knockout' && T.flag[f.code]) out.push('<span class="cx-rs cx-rs-flag">' + esc(T.flag[f.code]) + '</span>'); });
    return out.join('');
  }
  function filtered() {
    var q = S.f.q.trim().toLowerCase();
    var list = inbox().filter(function (a) {
      if (S.f.status && a.status !== S.f.status) return false;
      if (S.f.starred && !(+a.is_starred)) return false;
      if (S.f.job === C.spontaneous) { if (a.job_id) return false; } else if (S.f.job && String(a.job_id) !== S.f.job) return false;
      if (q && (a.full_name + ' ' + a.email + ' ' + a.reference + ' ' + a.job_title + ' ' + (a.current_title || '')).toLowerCase().indexOf(q) < 0) return false;
      return true;
    });
    if (S.f.sort === 'score') list.sort(function (x, y) { return (y.screen_score == null ? -1 : +y.screen_score) - (x.screen_score == null ? -1 : +x.screen_score); });
    return list;
  }
  function exportUrl() {
    var p = new URLSearchParams(); if (S.f.status) p.set('status', S.f.status); if (S.f.q) p.set('q', S.f.q); if (S.f.starred) p.set('starred', '1');
    if (S.f.job && S.f.job !== C.spontaneous) p.set('job_id', S.f.job); if (S.f.job === C.spontaneous) p.set('job_id', 'none');
    return '/promoter/careers/export.php?' + p.toString();
  }
  function appRow(a) {
    return '<li><button type="button" class="cx-row" data-open="' + a.id + '">' + avatar(a.full_name)
      + '<span class="cx-row-t"><strong>' + esc(a.full_name) + (a.status === 'new' ? ' <i class="cx-new" title="' + esc(T.unread) + '"></i>' : '') + '</strong><small>' + esc(jobTitle(a)) + (a.current_title ? ' · ' + esc(a.current_title) : (a.location ? ' · ' + esc(a.location) : '')) + '</small></span>'
      + score(a) + '<span class="cx-row-r">' + (a.rating ? stars(+a.rating) : '') + '</span>' + pill(a.status) + '<time class="cx-row-d">' + esc(ago(a.created_at)) + '</time>'
      + (+a.is_starred ? '<span class="cx-fav" aria-label="' + esc(T.starred) + '">' + I.star + '</span>' : '<span class="cx-fav-s"></span>') + '</button></li>';
  }
  function renderApps() {
    var list = filtered(), c = counts();
    var jobOpts = '<option value="">' + esc(T.all_jobs) + '</option><option value="' + C.spontaneous + '"' + (S.f.job === C.spontaneous ? ' selected' : '') + '>' + esc(T.spont_filter) + '</option>'
      + S.jobs.map(function (j) { return '<option value="' + j.id + '"' + (String(j.id) === S.f.job ? ' selected' : '') + '>' + esc(j.title) + '</option>'; }).join('');
    var chips = '<button type="button" class="cx-chip' + (!S.f.status ? ' on' : '') + '" data-sf="">' + esc(T.all) + ' <b class="num">' + c.total + '</b></button>'
      + C.appStatuses.map(function (k) { return '<button type="button" class="cx-chip cx-chip-' + k + (S.f.status === k ? ' on' : '') + '" data-sf="' + k + '">' + esc(T.as[k]) + ' <b class="num">' + (c.by[k] || 0) + '</b></button>'; }).join('');
    var active = S.f.q || S.f.job || S.f.status || S.f.starred;
    var html = '<div class="cx-bar-h"><div><h1>' + esc(T.apps_h) + '</h1></div><div class="cx-bar-a">'
      + '<div class="cx-seg" role="group"><button type="button" data-view="list" class="' + (S.view === 'list' ? 'on' : '') + '" aria-pressed="' + (S.view === 'list') + '">' + esc(T.v_list) + '</button><button type="button" data-view="board" class="cx-hide-sm ' + (S.view === 'board' ? 'on' : '') + '" aria-pressed="' + (S.view === 'board') + '">' + esc(T.v_board) + '</button></div>'
      + '<a class="cx-btn cx-btn-ghost" href="' + esc(exportUrl()) + '">' + I.dl + '<span class="cx-hide-xs">' + esc(T.export) + '</span></a></div></div>'
      + '<div class="cx-filters"><div class="cx-search"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="cx-q" class="cx-in" placeholder="' + esc(T.search_ph) + '" value="' + esc(S.f.q) + '" aria-label="' + esc(T.search_ph) + '" autocomplete="off"></div>'
      + '<select class="cx-in" id="cx-jobsel" aria-label="' + esc(T.all_jobs) + '">' + jobOpts + '</select>'
      + '<select class="cx-in cx-sortsel" id="cx-sort" aria-label="' + esc(T.sort) + '"><option value="new"' + (S.f.sort === 'new' ? ' selected' : '') + '>' + esc(T.sort_new) + '</option><option value="score"' + (S.f.sort === 'score' ? ' selected' : '') + '>' + esc(T.sort_score) + '</option></select>'
      + '<button type="button" class="cx-chip' + (S.f.starred ? ' on' : '') + '" data-starred aria-pressed="' + !!S.f.starred + '">★ ' + esc(T.starred) + '</button></div>'
      + '<div class="cx-chips" role="group">' + chips + '</div>'
      + '<p class="cx-results" aria-live="polite">' + esc(plural(list.length, 'results')) + (active ? ' · <button type="button" class="cx-link" data-clear>' + esc(T.clear) + '</button>' : '') + (held().length ? ' · <button type="button" class="cx-link" data-go="screening">' + esc(fmt(T.n_held_link, { n: held().length })) + '</button>' : '') + '</p>';
    if (!list.length) {
      html += '<div class="cx-empty">' + I.inbox + '<h2>' + esc(T.no_apps_h) + '</h2><p>' + esc(T.no_apps_p) + '</p></div>';
    } else if (S.view === 'board') {
      html += '<div class="cx-board">' + C.appStatuses.map(function (k) {
        var col = list.filter(function (a) { return a.status === k; });
        return '<section class="cx-col" data-col="' + k + '" aria-label="' + esc(T.as[k]) + '"><header><span class="cx-dot2 cx-st-' + k + '"></span><h3>' + esc(T.as[k]) + '</h3><b class="num">' + col.length + '</b></header><div class="cx-col-b">'
          + col.map(function (a) { return '<button type="button" class="cx-kcard" draggable="true" data-open="' + a.id + '" data-id="' + a.id + '"><div class="cx-kc-t">' + avatar(a.full_name) + '<span><strong>' + esc(a.full_name) + '</strong><small>' + esc(jobTitle(a)) + '</small></span>' + score(a) + '</div><div class="cx-kc-f">' + (a.rating ? stars(+a.rating) : '<span></span>') + '<time>' + esc(a.next_interview ? T.iv_short + ' ' + date(a.next_interview, true) : ago(a.created_at)) + '</time></div></button>'; }).join('') + '</div></section>';
      }).join('') + '</div>';
    } else {
      html += '<ul class="cx-list">' + list.map(appRow).join('') + '</ul>';
    }
    $('#panel-apps').innerHTML = html;
  }

  /* ======================================================= SCREENING (held back by the ATS) */
  function renderScreening() {
    var view = S.scrView, list = (view === 'held' ? held() : archived()).slice();
    var q = (S.sq || '').trim().toLowerCase();
    if (q) list = list.filter(function (a) { return (a.full_name + ' ' + a.email + ' ' + a.reference + ' ' + a.job_title).toLowerCase().indexOf(q) > -1; });
    list.sort(function (x, y) { return (+x.screen_score || 0) - (+y.screen_score || 0); });
    var selIds = Object.keys(S.sel).filter(function (k) { return S.sel[k]; }), n = selIds.length;
    var html = '<div class="cx-bar-h"><div><h1>' + esc(T.scr_h) + '</h1><p class="cx-sub">' + esc(T.scr_sub) + '</p></div>'
      + '<div class="cx-seg" role="group"><button type="button" data-scr="held" class="' + (view === 'held' ? 'on' : '') + '" aria-pressed="' + (view === 'held') + '">' + esc(T.scr_held_t) + ' <b class="num">' + held().length + '</b></button><button type="button" data-scr="archived" class="' + (view === 'archived' ? 'on' : '') + '" aria-pressed="' + (view === 'archived') + '">' + esc(T.scr_arch_t) + ' <b class="num">' + archived().length + '</b></button></div></div>'
      + '<aside class="cx-note"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/><path d="m9 12 2 2 4-4"/></svg><p>' + esc(T.scr_note) + '</p></aside>'
      + '<div class="cx-filters cx-filters-2"><div class="cx-search"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="cx-sq" class="cx-in" placeholder="' + esc(T.search_ph) + '" value="' + esc(S.sq || '') + '" aria-label="' + esc(T.search_ph) + '" autocomplete="off"></div></div>';
    if (n) {
      html += '<div class="cx-bulk" role="region" aria-label="' + esc(T.bulk) + '"><b class="num">' + esc(fmt(T.selected_n, { n: n })) + '</b><div>'
        + (view === 'held' ? '<button type="button" class="cx-btn cx-btn-sm cx-btn-primary" data-bulk="inbox">' + esc(T.to_pipeline) + '</button><button type="button" class="cx-btn cx-btn-sm cx-btn-ghost" data-bulk="archive">' + esc(T.archive) + '</button>'
                           : '<button type="button" class="cx-btn cx-btn-sm cx-btn-primary" data-bulk="restore">' + esc(T.restore) + '</button>')
        + '<button type="button" class="cx-btn cx-btn-sm cx-btn-danger-o" data-bulk="delete">' + esc(T.delete_now) + '</button><button type="button" class="cx-btn cx-btn-sm cx-btn-quiet" data-bulk="clear">' + esc(T.clear_sel) + '</button></div></div>';
    }
    if (!list.length) {
      html += '<div class="cx-empty">' + I.filter + '<h2>' + esc(view === 'held' ? T.scr_none_h : T.scr_arch_none_h) + '</h2><p>' + esc(view === 'held' ? T.scr_none_p : T.scr_arch_none_p) + '</p></div>';
    } else {
      html += '<div class="cx-selall"><label class="cx-check"><input type="checkbox" data-selall' + (n === list.length ? ' checked' : '') + '><span>' + esc(T.select_all) + '</span></label></div><ul class="cx-hold">' + list.map(function (a) {
        var decl = view === 'held' && +a.auto_decline_days > 0 ? Math.max(0, Math.ceil((new Date(String(a.bucket_changed_at).replace(' ', 'T')).getTime() + a.auto_decline_days * 864e5 - Date.now()) / 864e5)) : null;
        return '<li class="cx-hold-i' + (S.sel[a.id] ? ' sel' : '') + '"><label class="cx-hold-c"><input type="checkbox" data-sel="' + a.id + '"' + (S.sel[a.id] ? ' checked' : '') + ' aria-label="' + esc(a.full_name) + '"></label>'
          + '<button type="button" class="cx-hold-m" data-open="' + a.id + '">' + avatar(a.full_name) + '<span class="cx-row-t"><strong>' + esc(a.full_name) + '</strong><small>' + esc(jobTitle(a)) + ' · ' + esc(fmt(view === 'held' ? T.held_since : T.arch_since, { when: ago(a.bucket_changed_at) })) + '</small><span class="cx-rsw">' + (reasons(a, 3) || '<span class="cx-rs">' + esc(T.no_reason) + '</span>') + '</span></span>' + score(a) + '</button>'
          + (decl !== null ? '<span class="cx-decl" title="' + esc(T.auto_decline_t) + '">' + esc(fmt(T.auto_decline_in, { n: decl })) + '</span>' : '')
          + '<div class="cx-hold-a">' + (view === 'held'
            ? '<button type="button" class="cx-btn cx-btn-sm cx-btn-primary" data-bk="inbox" data-id="' + a.id + '">' + esc(T.to_pipeline) + '</button><button type="button" class="cx-btn cx-btn-sm cx-btn-ghost" data-bk="archived" data-id="' + a.id + '">' + esc(T.archive) + '</button>'
            : '<button type="button" class="cx-btn cx-btn-sm cx-btn-primary" data-bk="inbox" data-id="' + a.id + '">' + esc(T.restore) + '</button>')
          + '<button type="button" class="cx-btn cx-btn-sm cx-btn-quiet" data-del="' + a.id + '">' + esc(T.delete_now) + '</button></div></li>';
      }).join('') + '</ul>';
    }
    $('#panel-screening').innerHTML = html;
  }

  /* ======================================================= INTERVIEWS */
  function renderInterviews() {
    var now = new Date(), startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()), f = S.ivFilter;
    var all = S.interviews.slice();
    var up = all.filter(function (i) { return new Date(i.scheduled_at.replace(' ', 'T')) >= now && i.status !== 'cancelled'; });
    var past = all.filter(function (i) { return new Date(i.scheduled_at.replace(' ', 'T')) < now || i.status === 'cancelled'; }).reverse();
    var needs = up.filter(function (i) { return i.status === 'declined' || i.status === 'reschedule_requested'; });
    var list = f === 'past' ? past : f === 'needs' ? needs : up;
    var groups = {}, order = [];
    list.forEach(function (i) {
      var d = new Date(i.scheduled_at.replace(' ', 'T')), key = d.toDateString();
      if (!groups[key]) { groups[key] = { d: d, items: [] }; order.push(key); }
      groups[key].items.push(i);
    });
    var dayName = function (d) {
      var diff = Math.round((new Date(d.getFullYear(), d.getMonth(), d.getDate()) - startToday) / 864e5);
      if (diff === 0) return T.today; if (diff === 1) return T.tomorrow; if (diff === -1) return T.yesterday;
      return d.getDate() + ' ' + T.months[d.getMonth()] + ' ' + d.getFullYear();
    };
    var html = '<div class="cx-bar-h"><div><h1>' + esc(T.iv_h) + '</h1><p class="cx-sub">' + esc(T.iv_sub) + '</p></div></div>'
      + '<div class="cx-chips" role="group"><button type="button" class="cx-chip' + (f === 'upcoming' ? ' on' : '') + '" data-ivf="upcoming">' + esc(T.iv_upcoming) + ' <b class="num">' + up.length + '</b></button>'
      + '<button type="button" class="cx-chip' + (f === 'needs' ? ' on' : '') + '" data-ivf="needs">' + esc(T.iv_needs) + ' <b class="num">' + needs.length + '</b></button>'
      + '<button type="button" class="cx-chip' + (f === 'past' ? ' on' : '') + '" data-ivf="past">' + esc(T.iv_past) + ' <b class="num">' + past.length + '</b></button></div>';
    if (!list.length) {
      html += '<div class="cx-empty">' + I.clock + '<h2>' + esc(T.iv_none_h) + '</h2><p>' + esc(T.iv_none_p) + '</p></div>';
    } else {
      html += order.map(function (key) {
        var g = groups[key];
        return '<section class="cx-day"><h3>' + esc(dayName(g.d)) + '</h3><ul class="cx-ivl">' + g.items.map(function (i) {
          var live = ['scheduled', 'confirmed', 'reschedule_requested', 'declined'].indexOf(i.status) > -1;
          return '<li class="cx-iv-i"><div class="cx-iv-t"><b class="num">' + esc(hm(i.scheduled_at)) + '</b><small>' + esc(i.duration_min) + ' ' + esc(T.min) + '</small></div>'
            + '<button type="button" class="cx-iv-m" data-open="' + i.app_id + '"><span class="cx-row-t"><strong>' + esc(i.full_name) + '</strong><small>' + esc(i.job_title || T.spont) + ' · ' + esc(T.ivmodes[i.mode]) + (i.interviewer ? ' · ' + esc(i.interviewer) : '') + '</small>'
            + (i.candidate_note ? '<span class="cx-ivnote">“' + esc(i.candidate_note) + '”</span>' : '') + '</span>' + ivPill(i.status) + (live && f !== 'past' ? '<em class="cx-until">' + esc(until(i.scheduled_at)) + '</em>' : '') + '</button>'
            + '<div class="cx-iv-a">' + (live ? '<button type="button" class="cx-btn cx-btn-sm cx-btn-ghost" data-ivedit="' + i.app_id + '" data-iv="' + i.id + '">' + esc(T.iv_move) + '</button><button type="button" class="cx-btn cx-btn-sm cx-btn-quiet" data-ivcancel="' + i.id + '">' + esc(T.iv_cancel) + '</button>' : '') + '</div></li>';
        }).join('') + '</ul></section>';
      }).join('');
    }
    $('#panel-interviews').innerHTML = html;
  }

  /* ======================================================= IMPORT A POSITION FROM A MARKDOWN FILE */
  function mdMsg(m) { return fmt(T.md[m.code] || m.code, Object.assign({ line: m.line }, m.params || {})); }
  function mdList(items, cls) {
    return '<ul class="cx-md-msgs ' + cls + '">' + items.map(function (m) { return '<li>' + (m.line ? '<b class="cx-md-ln">' + esc(fmt(T.md_line, { n: m.line })) + '</b>' : '') + '<span>' + esc(mdMsg(m)) + '</span></li>'; }).join('') + '</ul>';
  }
  function mdParas(text) {
    return String(text || '').split(/\n{2,}/).filter(Boolean).map(function (p) {
      var ls = p.split('\n');
      if (ls.every(function (l) { return l.indexOf('• ') === 0; })) return '<ul class="cx-mdp-list">' + ls.map(function (l) { return '<li>' + esc(l.slice(2)) + '</li>'; }).join('') + '</ul>';
      return '<p>' + ls.map(esc).join('<br>') + '</p>';
    }).join('');
  }
  function mdItems(text, cls) { var a = String(text || '').split('\n').filter(Boolean); return a.length ? '<ul class="cx-mdp-list ' + (cls || '') + '">' + a.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>' : ''; }
  function kwChips(text, derived) {
    return String(text || '').split('\n').filter(Boolean).map(function (k) { var ko = k.charAt(0) === '!'; return '<span class="cx-kw ' + (ko ? 'no cx-kw-ko' : 'ok') + '" title="' + (ko ? esc(T.md_ko) : '') + '">' + esc(ko ? k.slice(1) : k) + (ko ? ' <small>' + esc(T.md_ko) + '</small>' : '') + '</span>'; }).join('');
  }
  function mdPreviewHtml(job, prev) {
    if (!job || !job.title) return '';
    var team = (prev.team_label && prev.team_label[C.lang]) || C.teams[job.department] || '';
    var chips = [job.location, T.modes[job.work_mode], T.types[job.employment_type], job.level].filter(Boolean).map(function (x) { return '<span class="cx-tag cx-tag-lg">' + esc(x) + '</span>'; }).join('');
    var sec = function (h, body) { return body ? '<section><h4>' + esc(h) + '</h4>' + body + '</section>' : ''; };
    var f = function (k, v) { return v ? '<div><dt>' + esc(k) + '</dt><dd>' + v + '</dd></div>' : ''; };
    var scr = +job.screen_enabled;
    return '<article class="cx-mdp"><header>' + (team ? '<p class="cx-mdp-k">' + esc(team) + '</p>' : '') + '<h3>' + esc(job.title) + '</h3>' + (job.summary ? '<p class="cx-mdp-sum">' + esc(job.summary) + '</p>' : '') + '<div class="cx-mdp-chips">' + chips + '</div></header>'
      + '<div class="cx-mdp-grid"><div class="cx-mdp-main">'
      + sec(T.l_desc, mdParas(job.description)) + sec(T.l_resp, mdItems(job.responsibilities)) + sec(T.l_req, mdItems(job.requirements)) + sec(T.l_nice, mdItems(job.nice_to_have, 'soft')) + sec(T.l_ben, mdItems(job.benefits))
      + '</div><aside><section class="cx-mdp-card"><h4>' + esc(T.md_prev_facts) + '</h4><dl class="cx-dl">'
      + f(T.l_dept, esc(team)) + f(T.l_code, '<span class="cx-code">' + esc(prev.code || '') + '</span>' + (prev.code_generated ? ' <small class="cx-muted">' + esc(T.md_code_auto) + '</small>' : ''))
      + f(T.md_status, esc(T.st[job.status] || job.status)) + f(T.l_closes, job.closes_at ? esc(date(job.closes_at)) : '') + f(T.l_openings, +job.openings > 1 ? esc(job.openings) : '') + f(T.l_salary, esc(job.salary_note))
      + '</dl></section><section class="cx-mdp-card cx-ats"><h4>' + esc(T.md_prev_screen) + ' · <span class="' + (scr ? 'cx-on' : 'cx-off') + '">' + esc(scr ? T.md_screen_on : T.md_screen_off) + '</span></h4>'
      + (scr ? '<dl class="cx-dl">' + f(T.md_threshold, esc(job.screen_threshold)) + f(T.md_min_years, +job.min_years ? esc(fmt(T.md_years, { n: job.min_years })) : esc(T.md_none)) + f(T.md_min_edu, job.min_education ? esc(T.edu[job.min_education]) : esc(T.md_none)) + f(T.md_auto_decline, +job.auto_decline_days ? esc(fmt(T.md_days, { n: job.auto_decline_days })) : esc(T.md_never)) + '</dl>'
        + '<p class="cx-kwh">' + esc(T.md_must) + '</p><div class="cx-kws">' + (kwChips(job.must_have) || (prev.derived && prev.derived.length ? prev.derived.map(function (k) { return '<span class="cx-kw mute">' + esc(k) + '</span>'; }).join('') : '<span class="cx-muted">' + esc(T.md_none) + '</span>')) + '</div>'
        + (!job.must_have && prev.derived && prev.derived.length ? '<p class="cx-hint">' + esc(T.md_derived) + '</p>' : '')
        + (job.nice_have ? '<p class="cx-kwh">' + esc(T.md_nice_kw) + '</p><div class="cx-kws">' + kwChips(job.nice_have) + '</div>' : '') : '')
      + '</section></aside></div></article>';
  }
  function openImport() {
    var d = $('#cx-md-dlg'), text = '';
    function frame(step, body, foot) {
      var steps = [T.md_step1, T.md_step2, T.md_step3].map(function (x, i) { return '<li' + (i + 1 === step ? ' class="on" aria-current="step"' : (i + 1 < step ? ' class="done"' : '')) + '><b>' + (i + 1) + '</b>' + esc(x) + '</li>'; }).join('');
      d.innerHTML = '<header class="cx-dlg-h"><div><h2 id="cx-md-title">' + esc(T.md_title) + '</h2><ol class="cx-steps3">' + steps + '</ol></div><button type="button" class="cx-icon" data-dlgclose aria-label="' + esc(T.close) + '">' + I.x + '</button></header>'
        + '<div class="cx-dlg-b">' + body + '</div>' + (foot ? '<footer class="cx-dlg-f">' + foot + '</footer>' : '');
      $$('[data-dlgclose]', d).forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
    }
    function choose(msg) {
      frame(1, '<p class="cx-sub cx-md-intro">' + esc(T.md_intro) + '</p>'
        + (msg ? '<p class="cx-banner" role="alert">' + esc(msg) + '</p>' : '')
        + '<label class="cx-md-drop" id="cx-md-drop"><input type="file" id="cx-md-file" accept=".md,.markdown,.txt,text/markdown,text/plain"><span class="cx-md-ic">' + I.md.replace('width="16" height="16"', 'width="30" height="30"') + '</span><strong>' + esc(T.md_drop) + '</strong><span>' + esc(T.md_or) + ' <u>' + esc(T.md_browse) + '</u></span></label>'
        + '<p class="cx-md-tpl">' + I.dl + ' ' + esc(T.md_tpl) + ': <a href="/promoter/careers/template.php?lang=en">' + esc(T.md_tpl_en) + '</a> · <a href="/promoter/careers/template.php?lang=fr">' + esc(T.md_tpl_fr) + '</a></p>'
        + '<details class="cx-md-paste"><summary>' + esc(T.md_paste) + '</summary><textarea class="cx-in" id="cx-md-text" rows="9" placeholder="' + esc(T.md_paste_ph) + '" spellcheck="false"></textarea><div class="cx-note-a"><button type="button" class="cx-btn cx-btn-ghost cx-btn-sm" id="cx-md-paste-go">' + esc(T.md_paste_go) + '</button></div></details>');
      var drop = $('#cx-md-drop', d), inp = $('#cx-md-file', d);
      var readFile = function (f) {
        if (!f) return;
        if (f.size > 204800) return choose(T.md_file_big);
        var r = new FileReader(); r.onload = function () { run(String(r.result || '')); }; r.readAsText(f, 'UTF-8');
      };
      inp.addEventListener('change', function () { readFile(inp.files && inp.files[0]); });
      ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('drag'); }); });
      ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('drag'); }); });
      drop.addEventListener('drop', function (e) { e.preventDefault(); readFile(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]); });
      $('#cx-md-paste-go', d).addEventListener('click', function () { var v = $('#cx-md-text', d).value; if (v.trim()) { if (v.length > 204800) return choose(T.md_file_big); run(v); } });
    }
    function run(md) {
      text = md;
      frame(1, '<div class="cx-d-load"><span class="cx-spin"></span> ' + esc(T.md_reading) + '</div>');
      api('md_preview', { md: md }).then(function (r) { if (!r.success) return choose(r.message || T.err); review(r); });
    }
    function review(r) {
      var errs = r.errors || [], warns = r.warnings || [], ok = !!r.valid;
      var banner = ok ? '<div class="cx-md-banner ok"><b>' + esc(T.md_ready) + '</b><span>' + esc(T.md_ready_p) + (warns.length ? ' · ' + esc(fmt(T.md_warn_n, { n: warns.length })) : '') + '</span></div>'
                      : '<div class="cx-md-banner bad"><b>' + esc(fmt(T.md_blocked, { n: errs.length })) + '</b><span>' + esc(T.md_blocked_p) + '</span></div>';
      var body = banner + (errs.length ? '<h4 class="cx-md-h">' + esc(T.md_errors) + '</h4>' + mdList(errs, 'err') : '') + (warns.length ? '<h4 class="cx-md-h">' + esc(T.md_warnings) + '</h4>' + mdList(warns, 'warn') : '') + mdPreviewHtml(r.job, r.preview || {});
      frame(ok ? 3 : 2, body, '<button type="button" class="cx-btn cx-btn-ghost" id="cx-md-back">' + esc(T.md_back) + '</button><button type="button" class="cx-btn cx-btn-ghost" id="cx-md-draft"' + (ok ? '' : ' disabled') + '>' + esc(T.md_draft) + '</button><button type="button" class="cx-btn cx-btn-primary" id="cx-md-go"' + (ok ? '' : ' disabled') + '>' + esc(T.md_publish) + '</button>');
      $('#cx-md-back', d).addEventListener('click', function () { choose(); });
      var send = function (status, btn) {
        $$('footer button', d).forEach(function (b) { b.disabled = true; });
        api('md_import', { md: text, status: status }).then(function (x) {
          if (x.success) {
            d.close(); toast(status === 'open' ? T.md_done_open : T.md_done_draft);
            return reload().then(function () { S.jobFilter = 'all'; go('jobs'); });
          }
          if (x.errors) return review({ valid: false, errors: x.errors, warnings: [], job: r.job, preview: r.preview });
          toast(x.message || T.err, 'err'); $$('footer button', d).forEach(function (b) { b.disabled = false; });
        });
      };
      $('#cx-md-draft', d).addEventListener('click', function () { send('draft'); });
      $('#cx-md-go', d).addEventListener('click', function () { send('open'); });
    }
    choose();
    if (!d.open) d.showModal();
  }

  /* ---------- interview scheduler dialog ---------- */
  function pad(n) { return ('0' + n).slice(-2); }
  function toLocalInput(s) { if (!s) return ''; var d = new Date(String(s).replace(' ', 'T')); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
  function openInterview(app, iv) {
    var d = $('#cx-iv-dlg'), v = iv || { scheduled_at: '', duration_min: 45, mode: 'video', location: '', interviewer: '', instructions: '' };
    var def = toLocalInput(v.scheduled_at);
    if (!def) { var t = new Date(Date.now() + 2 * 864e5); def = t.getFullYear() + '-' + pad(t.getMonth() + 1) + '-' + pad(t.getDate()) + 'T10:00'; }
    d.innerHTML = '<form id="cx-iv-form" novalidate><header class="cx-dlg-h"><div><h2 id="cx-iv-title">' + esc(iv ? T.iv_move_t : T.iv_new_t) + '</h2><p class="cx-sub">' + esc(app.full_name + ' · ' + (app.job_title || T.spont)) + '</p></div><button type="button" class="cx-icon" data-dlgclose aria-label="' + esc(T.close) + '">' + I.x + '</button></header>'
      + '<div class="cx-dlg-b"><div class="cx-fg">'
      + field('scheduled_at', T.iv_when, '<input class="cx-in" id="jf-scheduled_at" name="scheduled_at" type="datetime-local" value="' + esc(def) + '" required>', T.iv_tz)
      + field('duration_min', T.iv_duration, '<select class="cx-in" id="jf-duration_min" name="duration_min">' + [15, 30, 45, 60, 90, 120].map(function (m) { return '<option value="' + m + '"' + (+v.duration_min === m ? ' selected' : '') + '>' + m + ' ' + esc(T.min) + '</option>'; }).join('') + '</select>')
      + field('mode', T.iv_mode, '<select class="cx-in" id="jf-mode" name="mode">' + opts(T.ivmodes, v.mode) + '</select>')
      + field('location', T.iv_where, '<input class="cx-in" id="jf-location" name="location" maxlength="255" value="' + esc(v.location || '') + '">', '', '')
      + field('interviewer', T.iv_who, '<input class="cx-in" id="jf-interviewer" name="interviewer" maxlength="120" value="' + esc(v.interviewer || '') + '" placeholder="' + esc(T.iv_who_ph) + '">', '', 'full')
      + field('instructions', T.iv_notes, '<textarea class="cx-in" id="jf-instructions" name="instructions" rows="4" maxlength="1500" placeholder="' + esc(T.iv_notes_ph) + '">' + esc(v.instructions || '') + '</textarea>', T.iv_notes_h, 'full')
      + '</div><label class="cx-check"><input type="checkbox" name="notify" value="1" checked><span>' + I.mail + ' ' + esc(iv ? T.iv_notify_move : T.iv_notify) + '</span></label><p class="cx-hint">' + esc(T.iv_auto) + '</p>'
      + '<p class="cx-banner" id="jf-banner" role="alert" hidden></p></div>'
      + '<footer class="cx-dlg-f"><button type="button" class="cx-btn cx-btn-ghost" data-dlgclose>' + esc(T.cancel) + '</button><button type="submit" class="cx-btn cx-btn-primary">' + esc(iv ? T.iv_move_go : T.iv_send) + '</button></footer></form>';
    var f = $('#cx-iv-form', d), mode = $('#jf-mode', d), loc = $('#jf-location', d);
    var hint = function () { loc.placeholder = mode.value === 'video' ? T.iv_loc_video : mode.value === 'onsite' ? T.iv_loc_onsite : T.iv_loc_phone; };
    mode.addEventListener('change', hint); hint();
    $$('[data-dlgclose]', d).forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var data = { application_id: app.id }; new FormData(f).forEach(function (val, k) { data[k] = val; });
      data.notify = f.elements.notify.checked ? 1 : 0; if (iv) data.id = iv.id;
      $$('.cx-err', f).forEach(function (p) { p.textContent = ''; p.classList.remove('show'); }); $$('.cx-in.bad', f).forEach(function (i) { i.classList.remove('bad'); }); $('#jf-banner', f).hidden = true;
      var btn = $('button[type=submit]', f); btn.disabled = true;
      api('interview_save', data).then(function (r) {
        btn.disabled = false;
        if (r.success) {
          d.close(); toast(r.mailed || !data.notify ? (iv ? T.iv_moved : T.iv_saved) : T.iv_saved_nomail, !r.mailed && data.notify ? 'err' : '');
          if (r.warning) toast(fmt(T.iv_conflict, { name: r.warning }), 'err');
          return reload().then(function () { rerender(); if (S.openId === app.id) refreshDrawer(app.id); });
        }
        if (r.fields) {
          var first = null;
          Object.keys(r.fields).forEach(function (k) { var p = $('[data-err="' + k + '"]', f), i = f.elements[k]; if (p) { p.textContent = T['iv_err_' + r.fields[k]] || T.err_generic; p.classList.add('show'); } if (i) { i.classList.add('bad'); if (!first) first = i; } });
          if (first) first.focus();
        }
        var bn = $('#jf-banner', f); bn.textContent = r.fields ? T.err_generic : (r.message || T.err); bn.hidden = false;
      });
    });
    if (!d.open) d.showModal();
  }

  /* ---------- drawer ---------- */
  var drawer = $('#cx-drawer'), scrim = $('#cx-scrim');
  function closeDrawer() {
    if (drawer.hidden) return;
    drawer.classList.remove('in'); scrim.classList.remove('in'); document.body.classList.remove('cx-lock');
    setTimeout(function () { drawer.hidden = true; scrim.hidden = true; drawer.innerHTML = ''; }, 220);
    S.openId = null; if (S.opener && document.contains(S.opener)) S.opener.focus();
  }
  scrim.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !drawer.hidden && !$('dialog[open]')) closeDrawer();
    if (e.key === 'Tab' && !drawer.hidden && !$('dialog[open]')) {
      var f = $$('button:not([disabled]), a[href], input:not([disabled]), select, textarea', drawer).filter(function (x) { return x.offsetParent !== null; });
      if (!f.length) return; var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  function openApp(id, opener) {
    S.openId = id; if (opener) S.opener = opener; else if (!S.opener) S.opener = document.activeElement;
    drawer.hidden = false; scrim.hidden = false; document.body.classList.add('cx-lock');
    drawer.innerHTML = '<div class="cx-d-load"><span class="cx-spin"></span> ' + esc(T.loading) + '</div>';
    requestAnimationFrame(function () { drawer.classList.add('in'); scrim.classList.add('in'); drawer.focus(); });
    api('app_get', { id: id }).then(function (r) {
      if (S.openId !== id) return;
      if (!r.success) { drawer.innerHTML = '<div class="cx-d-load"><p>' + esc(r.message || T.err) + '</p><button class="cx-btn cx-btn-ghost" type="button" data-dclose>' + esc(T.close) + '</button></div>'; return; }
      renderDrawer(r.app);
    });
  }
  function evText(e) {
    var b = json(e.body) || {};
    if (e.kind === 'status') return e.body === 'auto' ? fmt(T.ev_status_auto, { to: T.as[e.to_status] || e.to_status }) : (e.from_status ? fmt(T.ev_status, { from: T.as[e.from_status] || e.from_status, to: T.as[e.to_status] || e.to_status }) : T.ev_received);
    if (e.kind === 'email') return (String(e.body || '').indexOf('[not sent]') === 0 ? T.ev_email_fail : T.ev_email) + (e.to_status && T.as[e.to_status] ? ' (' + T.as[e.to_status] + ')' : '');
    if (e.kind === 'rating') return e.body ? fmt(T.ev_rating, { n: e.body }) : T.ev_rating_clear;
    if (e.kind === 'screen') return fmt(T.ev_screen, { score: b.score, verdict: T.verdict[b.verdict] || b.verdict, bucket: T.bucket[e.to_status] || e.to_status });
    if (e.kind === 'bucket') return fmt(T.ev_bucket, { to: T.bucket[e.to_status] || e.to_status });
    if (e.kind === 'interview') return T.ev_iv[e.to_status] ? fmt(T.ev_iv[e.to_status], { when: b.when ? date(b.when, true) : '' }) : T.ev_note;
    if (e.kind === 'candidate') return T.ev_cand[e.to_status] || T.ev_note;
    return T.ev_note;
  }
  function atsPanel(a) {
    var r = a.screen;
    if (!r) return '<p class="cx-muted">' + esc(a.screen_state === 'error' ? T.scr_error : T.scr_pending_long) + '</p><button type="button" class="cx-btn cx-btn-sm cx-btn-ghost" data-rescreen>' + esc(T.rescreen) + '</button>';
    var v = r.verdict, groups = r.groups || {}, order = ['must', 'nice', 'years', 'education', 'title', 'complete', 'place'];
    var bars = order.filter(function (k) { return groups[k] && groups[k].on; }).map(function (k) {
      var g = groups[k], pct = Math.round(g.pts / g.max * 100), extra = '';
      if (k === 'years' && g.value != null) extra = ' <small>(' + g.value + ' / ' + g.need + (g.inferred ? ' · ' + esc(T.inferred) : '') + ')</small>';
      return '<div class="cx-gr"><span>' + esc(T.grp[k]) + extra + '</span><i><b style="width:' + pct + '%"></b></i><em class="num">' + Math.round(g.pts) + '/' + g.max + '</em></div>';
    }).join('');
    var kw = function (arr, cls) { return (arr || []).map(function (k) { return '<span class="cx-kw ' + cls + '">' + esc(k) + '</span>'; }).join(''); };
    var flags = (r.flags || []).filter(function (f) { return T.flag[f.code]; }).map(function (f) { return '<span class="cx-rs cx-rs-flag">' + esc(T.flag[f.code]) + (f.items ? ': ' + esc(f.items.join(', ')) : '') + '</span>'; }).join('');
    return '<div class="cx-ats-top"><span class="cx-ring cx-v-' + v + '" style="--p:' + r.score + '"><b class="num">' + r.score + '</b></span><div><p class="cx-ats-v cx-v-' + v + '">' + esc(T.verdict[v]) + '</p><p class="cx-muted">' + esc(fmt(T.scr_when, { when: date(a.screened_at, true) })) + (r.derived ? ' · ' + esc(T.derived_short) : '') + '</p></div></div>'
      + (v === 'review' && !r.readable ? '<p class="cx-callout">' + esc(T.unreadable_help) + '</p>' : '')
      + '<div class="cx-grs">' + bars + '</div>'
      + ((r.matched && r.matched.length) || (r.missing && r.missing.length) ? '<p class="cx-kwh">' + esc(T.kw_required) + '</p><div class="cx-kws">' + kw(r.matched, 'ok') + kw(r.missing, 'no') + '</div>' : '')
      + ((r.nice_matched && r.nice_matched.length) || (r.nice_missing && r.nice_missing.length) ? '<p class="cx-kwh">' + esc(T.kw_nice) + '</p><div class="cx-kws">' + kw(r.nice_matched, 'ok') + kw(r.nice_missing, 'mute') + '</div>' : '')
      + (flags ? '<div class="cx-rsw">' + flags + '</div>' : '')
      + '<button type="button" class="cx-btn cx-btn-sm cx-btn-ghost" data-rescreen>' + esc(T.rescreen) + '</button>';
  }
  function ivBlock(a) {
    var cur = (a.interviews || []).filter(function (i) { return ['scheduled', 'confirmed', 'reschedule_requested', 'declined'].indexOf(i.status) > -1; })[0];
    var closed = ['rejected', 'hired', 'withdrawn'].indexOf(a.status) > -1;
    if (!cur) return (closed ? '' : '<button type="button" class="cx-btn cx-btn-primary cx-btn-sm" data-ivnew>' + I.cal + ' ' + esc(T.iv_schedule) + '</button>')
      + ((a.interviews || []).length ? '<ul class="cx-ivh">' + a.interviews.map(function (i) { return '<li>' + esc(date(i.scheduled_at, true)) + ' ' + ivPill(i.status) + '</li>'; }).join('') + '</ul>' : '');
    var d = new Date(cur.scheduled_at.replace(' ', 'T'));
    return '<div class="cx-ivc"><div class="cx-ivtile"><b>' + esc(T.months[d.getMonth()]) + '</b><span>' + d.getDate() + '</span></div><div><p class="cx-ivc-t">' + esc(date(cur.scheduled_at, true)) + ' <small>' + esc(cur.duration_min) + ' ' + esc(T.min) + '</small></p>'
      + '<p class="cx-muted">' + esc(T.ivmodes[cur.mode]) + (cur.location ? ' · ' + esc(cur.location) : '') + (cur.interviewer ? ' · ' + esc(cur.interviewer) : '') + '</p><p>' + ivPill(cur.status) + ' <em class="cx-until">' + esc(until(cur.scheduled_at)) + '</em></p>'
      + (cur.candidate_note ? '<p class="cx-ivnote">“' + esc(cur.candidate_note) + '”</p>' : '') + '</div></div>'
      + '<div class="cx-ivc-a"><button type="button" class="cx-btn cx-btn-sm cx-btn-ghost" data-ivedit="' + a.id + '" data-iv="' + cur.id + '">' + esc(T.iv_move) + '</button><button type="button" class="cx-btn cx-btn-sm cx-btn-quiet" data-ivcancel="' + cur.id + '">' + esc(T.iv_cancel) + '</button></div>';
  }
  function fileCard(name, bytes, url) {
    var ext = (String(name).split('.').pop() || 'file').toUpperCase().slice(0, 4), size = bytes ? (bytes < 1048576 ? Math.max(1, Math.round(bytes / 1024)) + ' KB' : (bytes / 1048576).toFixed(1) + ' MB') : '';
    return '<div class="cx-resume"><span class="cx-resume-ic">' + esc(ext) + '</span><span class="cx-resume-t"><strong>' + esc(name) + '</strong><small>' + esc(size) + '</small></span><a class="cx-btn cx-btn-ghost cx-btn-sm" href="' + esc(url) + '">' + I.dl + ' ' + esc(T.download) + '</a></div>';
  }
  function renderDrawer(a) {
    var link = function (label, url) { return url ? '<div><dt>' + esc(label) + '</dt><dd><a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer">' + esc(url.replace(/^https?:\/\/(www\.)?/, '').slice(0, 42)) + ' ' + I.ext + '</a></dd></div>' : ''; };
    var row = function (label, val) { return val !== '' && val != null ? '<div><dt>' + esc(label) + '</dt><dd>' + esc(val) + '</dd></div>' : ''; };
    var stageOpts = C.appStatuses.filter(function (k) { return k !== 'withdrawn' || a.status === 'withdrawn'; }).map(function (k) { return '<option value="' + k + '"' + (k === a.status ? ' selected' : '') + '>' + esc(T.as[k]) + '</option>'; }).join('');
    var ext = (String(a.resume_name || a.resume_file || '').split('.').pop() || 'file').toUpperCase().slice(0, 4);
    var size = a.resume_size ? (a.resume_size < 1048576 ? Math.max(1, Math.round(a.resume_size / 1024)) + ' KB' : (a.resume_size / 1048576).toFixed(1) + ' MB') : '';
    var bucketBar = a.bucket === 'screened_out'
      ? '<div class="cx-callout cx-callout-hold"><p>' + esc(T.drawer_held) + '</p><div><button type="button" class="cx-btn cx-btn-sm cx-btn-primary" data-bk="inbox" data-id="' + a.id + '">' + esc(T.to_pipeline) + '</button><button type="button" class="cx-btn cx-btn-sm cx-btn-ghost" data-bk="archived" data-id="' + a.id + '">' + esc(T.archive) + '</button></div></div>'
      : a.bucket === 'archived' ? '<div class="cx-callout cx-callout-hold"><p>' + esc(T.drawer_arch) + '</p><div><button type="button" class="cx-btn cx-btn-sm cx-btn-primary" data-bk="inbox" data-id="' + a.id + '">' + esc(T.restore) + '</button></div></div>' : '';
    drawer.dataset.id = a.id; drawer.dataset.status = a.status;
    drawer.innerHTML =
      '<header class="cx-d-h"><div class="cx-d-id">' + avatar(a.full_name) + '<div><h2 id="cx-drawer-title">' + esc(a.full_name) + '</h2><p>' + esc(jobTitle(a)) + ' · <span class="cx-ref">' + esc(a.reference) + '</span></p></div></div>'
      + '<div class="cx-d-hb"><button type="button" class="cx-icon cx-starbtn' + (+a.is_starred ? ' on' : '') + '" data-dstar aria-pressed="' + !!(+a.is_starred) + '" aria-label="' + esc(+a.is_starred ? T.d_unstar : T.d_star) + '">' + I.star + '</button>'
      + '<button type="button" class="cx-icon" data-dclose aria-label="' + esc(T.close) + '">' + I.x + '</button></div></header>'
      + '<div class="cx-d-b">' + bucketBar
      + '<p class="cx-d-meta">' + pill(a.status) + '<span>' + esc(fmt(T.received, { date: date(a.created_at, true) })) + '</span><button type="button" class="cx-link" data-copytrack="' + esc(a.track_token) + '">' + I.link + ' ' + esc(T.copy_track) + '</button></p>'
      + '<section class="cx-d-sec"><h3>' + esc(T.d_stage) + '</h3><div class="cx-stage"><select class="cx-in" id="cx-stage" aria-label="' + esc(T.d_stage) + '">' + stageOpts + '</select><button type="button" class="cx-btn cx-btn-primary" id="cx-stage-go" disabled>' + esc(T.d_apply_stage) + '</button></div>'
      + '<label class="cx-check"><input type="checkbox" id="cx-notify"><span>' + I.mail + ' ' + esc(T.d_notify) + '</span></label>'
      + '<div id="cx-msgbox" hidden><label class="cx-lab" for="cx-msg">' + esc(T.d_message) + '</label><textarea class="cx-in" id="cx-msg" rows="4" maxlength="2000" placeholder="' + esc(T.d_message_ph) + '"></textarea></div>'
      + '<div class="cx-rate"><span>' + esc(T.d_rating) + '</span><span class="cx-rate-in" role="radiogroup" aria-label="' + esc(T.d_rating) + '">'
      + [1, 2, 3, 4, 5].map(function (n) { return '<button type="button" role="radio" aria-checked="' + (+a.rating === n) + '" class="cx-rate-b' + (+a.rating >= n ? ' on' : '') + '" data-rate="' + n + '" aria-label="' + n + '/5">' + I.star + '</button>'; }).join('')
      + '</span>' + (a.rating ? '<button type="button" class="cx-link" data-rate="0">' + esc(T.d_clear_rating) + '</button>' : '') + '</div></section>'
      + '<section class="cx-d-sec"><h3>' + esc(T.ats_h) + '</h3>' + atsPanel(a) + '</section>'
      + '<section class="cx-d-sec"><h3>' + esc(T.iv_h1) + '</h3>' + ivBlock(a) + '</section>'
      + (a.resume_file ? '<section class="cx-d-sec"><h3>' + esc(T.d_resume) + '</h3><div class="cx-resume"><span class="cx-resume-ic">' + esc(ext) + '</span><span class="cx-resume-t"><strong>' + esc(a.resume_name || a.resume_file) + '</strong><small>' + esc(size) + (a.resume_info && +a.resume_info.chars ? ' · ' + esc(fmt(T.chars, { n: a.resume_info.chars })) : (a.resume_info ? ' · ' + esc(T.no_text) : '')) + '</small></span><a class="cx-btn cx-btn-ghost cx-btn-sm" href="/promoter/careers/resume.php?id=' + a.id + '">' + I.dl + ' ' + esc(T.download) + '</a></div></section>' : '')
      + '<section class="cx-d-sec"><h3>' + esc(T.d_profile) + '</h3><dl class="cx-dl">' + row(T.l_ctitle, a.current_title) + row(T.l_ccomp, a.current_company) + row(T.l_years, a.years_experience != null ? a.years_experience : '') + row(T.l_edu, T.edu[a.education_level] || '')
      + row(T.l_skills, a.skills) + row(T.l_langs, a.languages) + row(T.l_wpref, T.wpref[a.work_pref] || '') + row(T.l_relocate, a.relocate != null ? (+a.relocate ? T.yes : T.no) : '') + '</dl></section>'
      + '<section class="cx-d-sec"><h3>' + esc(T.d_contact) + '</h3><dl class="cx-dl"><div><dt>' + esc(T.l_email) + '</dt><dd><a href="mailto:' + esc(a.email) + '">' + esc(a.email) + '</a></dd></div>'
      + (a.phone ? '<div><dt>' + esc(T.l_phone) + '</dt><dd><a href="tel:' + esc(a.phone.replace(/[^+\d]/g, '')) + '">' + esc(a.phone) + '</a></dd></div>' : '') + row(T.l_location, a.location)
      + link(T.l_linkedin, a.linkedin_url) + link(T.l_portfolio, a.portfolio_url) + '</dl></section>'
      + '<section class="cx-d-sec"><h3>' + esc(T.d_details) + '</h3><dl class="cx-dl">' + row(T.l_avail, a.availability) + row(T.l_salary_exp, a.salary_expectation) + row(T.l_heard, T.heard[a.heard_from] || '') + row(T.l_source, a.source) + row(T.l_lang, (a.lang || '').toUpperCase()) + row(T.l_pool, +a.talent_consent ? T.yes : T.no) + '</dl></section>'
      + '<section class="cx-d-sec"><h3>' + esc(T.d_cover) + '</h3>' + (a.cover_file ? fileCard(a.cover_name || a.cover_file, a.cover_size, '/promoter/careers/resume.php?id=' + a.id + '&type=cover') : '')
      + (a.cover_letter ? '<div class="cx-cover">' + esc(a.cover_letter).replace(/\n/g, '<br>') + '</div>' : (a.cover_file ? '' : '<p class="cx-muted">' + esc(T.d_no_cover) + '</p>')) + '</section>'
      + '<section class="cx-d-sec"><h3>' + esc(T.d_notes) + '</h3><form id="cx-noteform"><textarea class="cx-in" id="cx-note" rows="3" maxlength="3000" placeholder="' + esc(T.note_ph) + '"></textarea><div class="cx-note-a"><button type="submit" class="cx-btn cx-btn-ghost cx-btn-sm" id="cx-note-go" disabled>' + esc(T.add_note) + '</button></div></form>'
      + '<ol class="cx-tl" id="cx-tl">' + timeline(a) + '</ol></section>'
      + (a.others && a.others.length ? '<section class="cx-d-sec"><h3>' + esc(T.others) + '</h3><ul class="cx-others">' + a.others.map(function (o) { return '<li><button type="button" data-open="' + o.id + '"><span>' + esc(o.job_title || T.spont) + '</span>' + pill(o.status) + '</button></li>'; }).join('') + '</ul></section>' : '')
      + '<section class="cx-d-sec cx-danger"><h3>' + esc(T.danger) + '</h3><div class="cx-d-danger">' + (a.bucket !== 'archived' ? '<button type="button" class="cx-btn cx-btn-ghost cx-btn-sm" data-bk="archived" data-id="' + a.id + '">' + esc(T.archive) + '</button>' : '')
      + '<button type="button" class="cx-btn cx-btn-danger-o cx-btn-sm" data-ddel>' + esc(T.delete_app) + '</button></div></section>'
      + '</div>';
    wireDrawer(a);
  }
  function timeline(a) {
    return (a.events || []).map(function (e) {
      var plain = String(e.body || '').replace('[not sent] ', '');
      var b = e.kind === 'note' || (e.kind === 'email' && plain !== '' && plain !== '(default message)' && plain !== '(automatic decline)' && plain.indexOf('Reminder:') !== 0 && plain.indexOf('Interview') !== 0);
      var who = e.actor_name ? ' · ' + esc(fmt(T.by, { name: e.actor_name })) : (e.kind === 'screen' ? ' · ' + esc(T.by_ats) : (e.kind === 'candidate' ? ' · ' + esc(T.by_candidate) : ''));
      return '<li class="cx-ev cx-ev-' + esc(e.kind) + '"><span class="cx-ev-dot"></span><div><p class="cx-ev-t">' + esc(evText(e)) + (+e.visible ? ' <i class="cx-vis" title="' + esc(T.visible_cand) + '">' + I.link + '</i>' : '') + '</p>'
        + (b ? '<p class="cx-ev-b">' + esc(plain).replace(/\n/g, '<br>') + '</p>' : '')
        + '<small>' + esc(date(e.created_at, true)) + who + '</small></div></li>';
    }).join('');
  }
  function patchApp(id, patch) { S.apps.forEach(function (a) { if (+a.id === +id) Object.assign(a, patch); }); }
  function refreshDrawer(id) { return api('app_get', { id: id }).then(function (x) { if (x.success && S.openId === id) renderDrawer(x.app); }); }
  function wireDrawer(a) {
    var stage = $('#cx-stage', drawer), go = $('#cx-stage-go', drawer), notify = $('#cx-notify', drawer), box = $('#cx-msgbox', drawer);
    var upd = function () { go.disabled = !(stage.value !== drawer.dataset.status || notify.checked); box.hidden = !notify.checked; };
    stage.addEventListener('change', function () { var s = stage.value; if (s !== drawer.dataset.status && ['shortlisted', 'offer', 'hired', 'rejected'].indexOf(s) > -1) notify.checked = true; upd(); });
    notify.addEventListener('change', upd);
    go.addEventListener('click', function () {
      if (stage.value === 'interview' && drawer.dataset.status !== 'interview' && !(a.interviews || []).some(function (i) { return ['scheduled', 'confirmed', 'reschedule_requested'].indexOf(i.status) > -1; })) {
        /* An interview needs a date: open the scheduler instead of only changing the label */
        stage.value = drawer.dataset.status; upd(); return openInterview(a, null);
      }
      go.disabled = true;
      api('app_status', { id: a.id, status: stage.value, notify: notify.checked, message: $('#cx-msg', drawer).value }).then(function (r) {
        if (!r.success) { toast(r.message || T.err, 'err'); upd(); return; }
        toast(notify.checked ? (r.mailed ? T.stage_mailed : T.stage_mail_fail) : T.stage_saved, notify.checked && !r.mailed ? 'err' : '');
        reload().then(function () { rerender(); refreshDrawer(a.id); });
      });
    });
    $$('[data-rate]', drawer).forEach(function (b) {
      b.addEventListener('click', function () {
        var n = +b.dataset.rate;
        api('app_rate', { id: a.id, rating: n }).then(function (r) { if (!r.success) return toast(r.message || T.err, 'err'); patchApp(a.id, { rating: n || null }); refreshDrawer(a.id); rerender(); });
      });
    });
    $('[data-dstar]', drawer).addEventListener('click', function () {
      var on = !(+((S.apps.filter(function (x) { return +x.id === +a.id; })[0] || a).is_starred));
      api('app_star', { id: a.id, on: on }).then(function (r) { if (!r.success) return toast(r.message || T.err, 'err'); patchApp(a.id, { is_starred: on ? 1 : 0 }); var b = $('[data-dstar]', drawer); b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); rerender(); });
    });
    var rs = $('[data-rescreen]', drawer);
    if (rs) rs.addEventListener('click', function () { rs.disabled = true; api('app_rescreen', { id: a.id }).then(function (r) { if (!r.success) { rs.disabled = false; return toast(r.message || T.err, 'err'); } toast(T.rescreened); reload().then(function () { rerender(); refreshDrawer(a.id); }); }); });
    var ivn = $('[data-ivnew]', drawer); if (ivn) ivn.addEventListener('click', function () { openInterview(a, null); });
    $$('[data-ivedit]', drawer).forEach(function (b) { b.addEventListener('click', function (ev) { ev.stopPropagation(); var iv = (a.interviews || []).filter(function (i) { return +i.id === +b.dataset.iv; })[0]; openInterview(a, iv); }); });
    var note = $('#cx-note', drawer), ng = $('#cx-note-go', drawer);
    note.addEventListener('input', function () { ng.disabled = !note.value.trim(); });
    $('#cx-noteform', drawer).addEventListener('submit', function (e) {
      e.preventDefault(); if (!note.value.trim()) return; ng.disabled = true;
      api('app_note', { id: a.id, body: note.value }).then(function (r) { if (!r.success) { toast(r.message || T.err, 'err'); ng.disabled = false; return; } toast(T.note_added); refreshDrawer(a.id); });
    });
    $('[data-ddel]', drawer).addEventListener('click', function () { deleteApps([a.id], true); });
  }

  /* ---------- actions shared by the lists and the drawer ---------- */
  function moveBucket(ids, bucket) {
    var op = bucket === 'archived' ? 'archive' : 'inbox';
    return api('apps_bulk', { ids: ids, op: op }).then(function (r) {
      if (!r.success) return toast(r.message || T.err, 'err');
      toast(bucket === 'archived' ? T.archived : T.moved_pipeline); S.sel = {};
      return reload().then(function () { rerender(); if (S.openId && ids.indexOf(S.openId) > -1) refreshDrawer(S.openId); });
    });
  }
  function deleteApps(ids, fromDrawer) {
    return confirmBox(ids.length > 1 ? fmt(T.confirm_delete_n, { n: ids.length }) : T.delete_app, T.confirm_app_delete, T.yes_delete).then(function (ok) {
      if (!ok) return;
      return api('apps_bulk', { ids: ids, op: 'delete' }).then(function (r) {
        if (!r.success) return toast(r.message || T.err, 'err');
        toast(T.app_deleted); S.sel = {}; if (fromDrawer || ids.indexOf(S.openId) > -1) closeDrawer();
        return reload().then(rerender);
      });
    });
  }
  function cancelInterview(id) {
    return confirmBox(T.iv_cancel_t, T.iv_cancel_p, T.iv_cancel_ok).then(function (ok) {
      if (!ok) return;
      return api('interview_cancel', { id: id, notify: 1 }).then(function (r) { if (!r.success) return toast(r.message || T.err, 'err'); toast(T.iv_cancelled); return reload().then(function () { rerender(); if (S.openId) refreshDrawer(S.openId); }); });
    });
  }

  /* ---------- delegated clicks ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-open],[data-go],[data-jf],[data-newjob],[data-jedit],[data-jact],[data-jdel],[data-jobapps],[data-sf],[data-starred],[data-clear],[data-view],[data-status],[data-dclose],[data-scr],[data-bk],[data-del],[data-bulk],[data-ivf],[data-ivedit],[data-ivcancel],[data-copytrack],[data-mdimport]');
    if (!t) return;
    if (t.dataset.dclose !== undefined) return closeDrawer();
    if (t.dataset.mdimport !== undefined) return openImport();
    if (t.dataset.copytrack) {
      var url = location.origin + '/track.php?t=' + t.dataset.copytrack;
      var done = function () { toast(T.track_copied); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(done, function () { prompt(T.copy_track, url); }); else prompt(T.copy_track, url);
      return;
    }
    if (t.dataset.open) { return openApp(+t.dataset.open, t); }
    if (t.dataset.go) {
      var g = t.dataset.go;
      if (g === 'apps:new') { S.f = { q: '', job: '', status: 'new', starred: false, sort: S.f.sort }; return go('apps'); }
      if (g === 'apps:pipe') { S.f = { q: '', job: '', status: 'interview', starred: false, sort: S.f.sort }; return go('apps'); }
      if (g.indexOf('job:') === 0) { S.f = { q: '', job: g.slice(4), status: 'new', starred: false, sort: S.f.sort }; return go('apps'); }
      return go(g);
    }
    if (t.dataset.status !== undefined && t.classList.contains('cx-fn')) { S.f = { q: '', job: '', status: t.dataset.status, starred: false, sort: S.f.sort }; return go('apps'); }
    if (t.dataset.jf) { S.jobFilter = t.dataset.jf; return renderJobs(); }
    if (t.dataset.newjob !== undefined) return openJobEditor(null);
    if (t.dataset.jedit) { return api('job_get', { id: +t.dataset.jedit }).then(function (r) { r.success ? openJobEditor(r.job, r.derived) : toast(r.message || T.err, 'err'); }); }
    if (t.dataset.jact) {
      var st = t.dataset.jact.split(':')[1], id = +t.dataset.id; t.disabled = true;
      return api('job_status', { id: id, status: st }).then(function (r) {
        if (!r.success) { toast(r.message || T.err, 'err'); t.disabled = false; return; }
        toast(st === 'open' ? T.published : T.status_changed); return reload().then(function () { go('jobs'); });
      });
    }
    if (t.dataset.jdel) {
      var jid = +t.dataset.jdel;
      return confirmBox(T.delete, T.confirm_job_delete, T.yes_delete).then(function (ok) {
        if (!ok) return;
        api('job_delete', { id: jid }).then(function (r) { if (!r.success) return toast(r.message || T.err, 'err'); toast(T.deleted); S.jobs = S.jobs.filter(function (j) { return +j.id !== jid; }); go('jobs'); });
      });
    }
    if (t.dataset.jobapps) { S.f = { q: '', job: t.dataset.jobapps, status: '', starred: false, sort: S.f.sort }; return go('apps'); }
    if (t.dataset.sf !== undefined) { S.f.status = t.dataset.sf; return renderApps(); }
    if (t.dataset.starred !== undefined) { S.f.starred = !S.f.starred; return renderApps(); }
    if (t.dataset.clear !== undefined) { S.f = { q: '', job: '', status: '', starred: false, sort: S.f.sort }; return renderApps(); }
    if (t.dataset.view) { S.view = t.dataset.view; try { localStorage.setItem('cx_view', S.view); } catch (x) {} return renderApps(); }
    if (t.dataset.scr) { S.scrView = t.dataset.scr; S.sel = {}; return renderScreening(); }
    if (t.dataset.bk) { return moveBucket([+t.dataset.id], t.dataset.bk); }
    if (t.dataset.del) { return deleteApps([+t.dataset.del], false); }
    if (t.dataset.bulk) {
      var ids = Object.keys(S.sel).filter(function (k) { return S.sel[k]; }).map(Number), op = t.dataset.bulk;
      if (op === 'clear') { S.sel = {}; return renderScreening(); }
      if (op === 'delete') return deleteApps(ids, false);
      return moveBucket(ids, op === 'archive' ? 'archived' : 'inbox');
    }
    if (t.dataset.ivf) { S.ivFilter = t.dataset.ivf; return renderInterviews(); }
    if (t.dataset.ivedit && !t.closest('#cx-drawer')) {
      var appId = +t.dataset.ivedit, ivId = +t.dataset.iv;
      return api('app_get', { id: appId }).then(function (r) { if (!r.success) return toast(r.message || T.err, 'err'); var iv = (r.app.interviews || []).filter(function (i) { return +i.id === ivId; })[0]; openInterview(r.app, iv); });
    }
    if (t.dataset.ivcancel) return cancelInterview(+t.dataset.ivcancel);
  });
  document.addEventListener('input', function (e) {
    if (e.target.id === 'cx-q') { S.f.q = e.target.value; var pos = e.target.selectionStart; clearTimeout(S._qt); S._qt = setTimeout(function () { renderApps(); var q = $('#cx-q'); if (q) { q.focus(); try { q.setSelectionRange(pos, pos); } catch (x) {} } }, 160); }
    if (e.target.id === 'cx-sq') { S.sq = e.target.value; var p2 = e.target.selectionStart; clearTimeout(S._qt); S._qt = setTimeout(function () { renderScreening(); var q = $('#cx-sq'); if (q) { q.focus(); try { q.setSelectionRange(p2, p2); } catch (x) {} } }, 160); }
  });
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.id === 'cx-jobsel') { S.f.job = t.value; renderApps(); }
    if (t.id === 'cx-sort') { S.f.sort = t.value; renderApps(); }
    if (t.dataset && t.dataset.sel) { S.sel[t.dataset.sel] = t.checked; renderScreening(); }
    if (t.dataset && t.dataset.selall !== undefined) {
      var list = S.scrView === 'held' ? held() : archived(); S.sel = {}; if (t.checked) list.forEach(function (a) { S.sel[a.id] = true; }); renderScreening();
    }
  });

  /* ---------- drag and drop on the board ---------- */
  var dragId = null;
  document.addEventListener('dragstart', function (e) { var c = e.target.closest && e.target.closest('.cx-kcard'); if (!c) return; dragId = +c.dataset.id; c.classList.add('drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', String(dragId)); } catch (x) {} });
  document.addEventListener('dragend', function (e) { var c = e.target.closest && e.target.closest('.cx-kcard'); if (c) c.classList.remove('drag'); $$('.cx-col.over').forEach(function (x) { x.classList.remove('over'); }); });
  document.addEventListener('dragover', function (e) { var col = e.target.closest && e.target.closest('.cx-col'); if (col && dragId) { e.preventDefault(); $$('.cx-col.over').forEach(function (x) { if (x !== col) x.classList.remove('over'); }); col.classList.add('over'); } });
  document.addEventListener('drop', function (e) {
    var col = e.target.closest && e.target.closest('.cx-col'); if (!col || !dragId) return; e.preventDefault();
    var id = dragId, to = col.dataset.col; dragId = null; var a = S.apps.filter(function (x) { return +x.id === id; })[0];
    if (!a || a.status === to || to === 'withdrawn') return renderApps();
    if (to === 'interview') { api('app_get', { id: id }).then(function (r) { if (r.success) openInterview(r.app, null); }); return renderApps(); }
    var from = a.status; patchApp(id, { status: to }); renderApps(); badges();
    api('app_status', { id: id, status: to }).then(function (r) { if (!r.success) { patchApp(id, { status: from }); renderApps(); badges(); toast(r.message || T.err, 'err'); } else toast(T.stage_saved); });
  });

  /* ---------- theme + start ---------- */
  $('#cx-theme').addEventListener('click', function () { var dark = document.documentElement.classList.toggle('dark'); try { localStorage.setItem('sv_dark', dark ? '1' : '0'); } catch (e) {} });
  var start = location.hash.slice(1); go(panels[start] ? start : 'overview', { silent: !panels[start] });
  /* A workspace left open for hours stays current: counters and the screening section refresh every few minutes */
  setInterval(function () { if (document.hidden || !drawer.hidden || $('dialog[open]')) return; reload().then(rerender); }, 180000);
})();
