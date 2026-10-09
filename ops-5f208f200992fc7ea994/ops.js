/* Control center logic: overview, simulator orchestration, commands, calibration and model check. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fmt = (n) => Number(n).toLocaleString('en-US');
  const ms = (v) => v >= 1000 ? (v / 1000).toFixed(v >= 10000 ? 0 : 1) + ' s' : Math.round(v) + ' ms';
  const get = (o, p) => p.split('.').reduce((a, k) => (a == null ? a : a[k]), o);
  const set = (o, p, v) => { const ks = p.split('.'); let a = o; ks.slice(0, -1).forEach(k => { a = a[k] = a[k] || {}; }); a[ks[ks.length - 1]] = v; };

  let STATE = null, DETECTED = null, CAL = null, speed = 1;
  const runs = [];                             // history of simulations
  let current = null;                          // result being shown
  let viz = null, drawCharts = [];

  /* ───────── tabs ───────── */
  function tab(id) {
    $$('.ops-tabs button').forEach(b => b.setAttribute('aria-selected', String(b.dataset.tab === id)));
    $$('.ops-tab').forEach(s => { s.hidden = s.id !== 'tab-' + id; });
    try { history.replaceState(null, '', '#' + id); } catch (e) { }
    if (id === 'sim') requestAnimationFrame(() => { viz && viz.redraw(); renderCharts(); });
  }
  $$('.ops-tabs button').forEach(b => b.addEventListener('click', () => tab(b.dataset.tab)));
  $('#go-sim').addEventListener('click', () => tab('sim'));
  const tick = () => { $('#clock').textContent = new Date().toLocaleTimeString('en-GB'); }; tick(); setInterval(tick, 1000);

  /* ───────── api ───────── */
  async function api(action, data) {
    let init = { credentials: 'same-origin' };
    if (data) {
      const fd = new FormData(); Object.keys(data).forEach(k => fd.append(k, data[k])); fd.append('action', action);
      init = { credentials: 'same-origin', method: 'POST', body: fd, headers: { 'X-Ops-Token': window.OPS.token } };
    }
    const r = await fetch('api.php?action=' + action, init);
    const j = await r.json().catch(() => ({ success: false, message: 'Unreadable answer (' + r.status + ')' }));
    if (!j.success) throw new Error(j.message || 'Request failed');
    return j;
  }

  /* ───────── overview ───────── */
  const tag = (how) => how ? `<span class="src ${how}">${how}</span>` : '';
  const kv = (label, v, how) => `<dt>${esc(label)}</dt><dd>${esc(v)}${tag(how)}</dd>`;
  function renderOverview(s) {
    const hw = s.hardware, w = s.web, p = s.php, d = s.db, a = s.app, c = s.code;
    const pct = (x, y) => Math.min(100, Math.round(x / Math.max(1, y) * 100));
    const mailBad = a.mail_queue.failed > 0;
    const cards = [
      ['Machine', `<dl class="kv">${kv('CPU cores', hw.cores.value, hw.cores.how)}${kv('Memory', fmt(hw.ram_mb.value) + ' MB', hw.ram_mb.how)}${kv('Free memory', hw.ram_free_mb == null ? '—' : fmt(hw.ram_free_mb) + ' MB')}${kv('Load now', hw.load ? hw.load.join(' · ') : '—')}${kv('Disk free', hw.disk_free_gb + ' GB')}${kv('Up for', hw.uptime_h == null ? '—' : hw.uptime_h + ' h')}</dl>`],
      ['Web server', `<dl class="kv">${kv('Server', w.software.split(' ')[0])}${kv('Process model', w.mpm)}${kv('Workers allowed', w.workers.value, w.workers.how)}${kv('Keep-alive', w.keepalive.value ? 'on, ' + w.keepalive_timeout.value + ' s' : 'off', w.keepalive.how)}${kv('Waiting-room size', w.listen_backlog.value, w.listen_backlog.how)}</dl>${w.config_visible ? '' : '<p class="note">Apache\'s configuration files are not readable from here, so these are Apache\'s default values.</p>'}`],
      ['PHP', `<dl class="kv">${kv('Version', p.version)}${kv('Opcache', p.opcache.value ? 'on' : 'off', p.opcache.how)}${kv('Opcache hit rate', p.opcache_hit_rate == null ? '—' : p.opcache_hit_rate + ' %')}${kv('Memory per request', p.memory_limit)}${kv('Background mail possible', p.exec_available ? 'yes' : 'no (exec disabled)')}${kv('Sessions', p.session_handler)}</dl>`],
      ['Database', `<dl class="kv">${kv('Server', (d.version || '').split('-')[0] + ' ' + (/maria/i.test(d.version || '') ? 'MariaDB' : 'MySQL'))}${kv('Connections allowed', d.max_connections.value, d.max_connections.how)}${kv('Peak used so far', d.max_used == null ? '—' : d.max_used)}${kv('Buffer pool', d.buffer_pool_mb + ' MB')}${kv('Write flush', d.flush_log.value === 1 ? 'every commit' : 'every second', d.flush_log.how)}${kv('Same machine', d.same_host.value ? 'yes' : 'no', d.same_host.how)}${kv('Queries / s (average)', d.qps == null ? '—' : d.qps)}${kv('Size', a.db_size_mb + ' MB')}</dl>`],
      ['Request paths in the code', `<dl class="kv">${kv('Profile', c.profile.value, c.profile.how)}${kv('Migrations once per deploy', c.migrate_once ? 'yes' : 'no')}${kv('Shared poll cache', c.shared_cache ? 'yes' : 'no')}${kv('Results emails queued', c.mail_queue ? 'yes' : 'no')}${kv('Sign-up emails sent inside the request', c.signup_mail_inline ? 'yes' : 'no')}</dl><p class="note">${c.profile.value === 'legacy' ? 'The older paths cost about 34 SQL statements per poll.' : 'The optimized paths cost about 3 SQL statements per poll.'}</p>`],
      ['People and content', `<div class="big-n">${fmt(a.users.students + a.users.teachers + a.users.promoters)}</div><dl class="kv">${kv('Students', fmt(a.users.students))}${kv('Teachers', fmt(a.users.teachers))}${kv('Courses / lessons', a.courses + ' / ' + a.lessons)}${kv('Enrolments', fmt(a.enrollments))}${kv('Live sessions (upcoming)', a.live.sessions + ' (' + a.live.upcoming + ')')}${kv('Biggest room so far', a.live.biggest_room)}</dl>`],
      ['Email', `<dl class="kv">${kv('Sending configured', a.smtp ? 'yes' : 'no')}${kv('Provider', a.smtp_host || '—')}${kv('Results queue', `${a.mail_queue.pending} waiting · ${a.mail_queue.sent} sent · ${a.mail_queue.failed} failed`)}</dl>${mailBad ? '<p class="note" style="color:var(--red)">Some results emails failed. Use “Retry failed result emails” in Commands.</p>' : ''}`],
      ['Housekeeping', `<dl class="kv">${kv('Cache files', a.cache_files + ' (' + a.cache_kb + ' KB)')}${kv('Session files', a.sessions_files == null ? '—' : fmt(a.sessions_files))}${kv('Failed sign-ins (24 h)', a.login_failed_24h)}${kv('Maintenance mode', a.maintenance_s > 0 ? 'ON for ' + Math.ceil(a.maintenance_s / 60) + ' min' : 'off')}${kv('HTTPS enforced', a.https_only === 'true' ? 'yes' : 'no')}</dl>`],
    ];
    $('#ov').innerHTML = cards.map((c, i) => `<article class="card" style="--i:${i}"><h3>${esc(c[0])}</h3>${c[1]}</article>`).join('');
  }

  /* ───────── simulator: controls ↔ config ───────── */
  const NUMERIC = (el) => el.type === 'number' || el.hasAttribute('data-int');
  function readCfg() {
    const cfg = {};
    $$('.cfg').forEach(el => {
      const v = el.type === 'checkbox' ? el.checked : (NUMERIC(el) ? Number(el.value) : el.value);
      set(cfg, el.dataset.cfg, v);
    });
    set(cfg, 'net.rttMs', Number($('#c-net').value));
    if (CAL && CAL.speed) cfg.speed = CAL.speed;
    return cfg;
  }
  function writeCfg(cfg) {
    $$('.cfg').forEach(el => { const v = get(cfg, el.dataset.cfg); if (v === undefined) return; if (el.type === 'checkbox') el.checked = !!v; else el.value = v; });
    syncUsersSlider();
    syncScenario();
  }
  // slider position <-> number of people, on a log scale from 20 to 10,000
  const sl2n = (p) => { const n = 20 * Math.pow(500, p / 100), step = n < 100 ? 5 : n < 1000 ? 50 : 100; return Math.max(1, Math.round(n / step) * step); };
  const n2sl = (n) => Math.max(0, Math.min(100, Math.round(Math.log(Math.max(20, n) / 20) / Math.log(500) * 100)));
  function syncUsersSlider() { const n = Number($('#c-users').value); $('#c-users-r').value = n2sl(n); $('#c-users-v').textContent = fmt(n); }
  $('#c-users-r').addEventListener('input', (e) => { $('#c-users').value = sl2n(Number(e.target.value)); syncUsersSlider(); });
  $('#c-users').addEventListener('input', syncUsersSlider);
  function syncScenario() { const sc = $('#c-scenario').value; $$('[data-for]').forEach(el => { el.hidden = el.dataset.for !== sc; }); }
  $('#c-scenario').addEventListener('change', syncScenario);

  function provenance(state) {
    const map = { 'infra.keepAlive': state.web.keepalive, 'infra.keepAliveTimeoutS': state.web.keepalive_timeout, 'infra.workers': state.web.workers, 'infra.cores': state.hardware.cores, 'infra.ramMB': state.hardware.ram_mb,
      'infra.dbMaxConnections': state.db.max_connections, 'infra.flushLog': state.db.flush_log, 'infra.opcache': state.php.opcache, 'infra.dbPersistent': state.db.persistent, 'infra.dbOnSameHost': state.db.same_host, 'code.profile': state.code.profile };
    $$('.src[data-src]').forEach(el => { const m = map[el.dataset.src]; if (m) { el.className = 'src ' + m.how; el.textContent = m.how; el.title = m.detail || ''; } });
  }

  /* ───────── simulator: worker ───────── */
  let worker = null, jobId = 0; const pending = new Map();
  function callWorker(type, payload) {
    if (typeof Worker === 'undefined' || worker === false) {      // no Web Worker available (rare): compute right here, the page just freezes briefly
      return new Promise((res, rej) => setTimeout(() => { try { if (type === 'run') { const r = StudySim.simulate(payload.cfg); r.findings = StudySim.findings(r); res(r); } else res(StudySim.sweep(payload.cfg, payload.steps)); } catch (e) { rej(e); } }, 20));
    }
    if (!worker) { worker = new Worker('sim-worker.js'); worker.onmessage = (e) => { const p = pending.get(e.data.id); if (!p) return; pending.delete(e.data.id); e.data.ok ? p.res(e.data.result) : p.rej(new Error(e.data.error)); }; }
    return new Promise((res, rej) => { const id = ++jobId; pending.set(id, { res, rej }); worker.postMessage(Object.assign({ id, type }, payload)); });
  }
  const busy = (on, t) => { $('#busy').hidden = !on; if (t) $('#busy-t').textContent = t; $$('#b-run, #b-sweep').forEach(b => b.disabled = on); };

  async function run(label) {
    const cfg = readCfg();
    if (cfg.code.profile === 'legacy' && !$('[data-cfg="code.mailQueued"]').dataset.touched) { /* the original code sent mails inline */ }
    busy(true, `Simulating ${fmt(cfg.users)} people…`);
    const t0 = performance.now();
    try {
      const r = await callWorker('run', { cfg });
      r.label = label || `Run ${runs.length + 1}`; r.took = Math.round(performance.now() - t0);
      runs.push(r); show(r);
      viz.play();
    } catch (e) { $('#findings').innerHTML = `<p class="note" style="color:var(--red)">${esc(e.message)}</p>`; }
    busy(false);
  }

  /* ───────── simulator: showing a result ───────── */
  function show(r) {
    current = r; viz.setRun(r); renderCharts(); renderVerdict(r); renderMetrics(r); renderFindings(r); renderSteps(r); renderRuns();
    $('#scrub').value = 0;
  }
  function renderCharts() {
    const r = current; const canv = ['ch1', 'ch2', 'ch3'].map(id => $('#' + id));
    if (!r) { drawCharts = []; return; }
    const se = r.series, L = (k) => se.map(x => x[k]);
    drawCharts = [
      OpsViz.chart(canv[0], null, { title: 'Latency: median · 95% · slowest 1% (ms)', log: true, lines: [{ data: L('p50'), color: '#8fbf92' }, { data: L('p95'), color: '#e5b24f' }, { data: L('p99'), color: '#ef5b5b' }] }),
      OpsViz.chart(canv[1], null, { title: 'Requests per second · errors', lines: [{ data: L('rps'), color: '#7aa5d6' }, { data: L('err'), color: '#ef5b5b' }] }),
      OpsViz.chart(canv[2], null, { title: 'Workers held · waiting · DB connections', lines: [{ data: L('busy'), color: '#e27b57' }, { data: L('queue'), color: '#ef5b5b' }, { data: L('db'), color: '#a98bd6' }] }),
    ];
    drawCharts.forEach(d => d(0));
  }
  function renderVerdict(r) {
    const s = r.summary, v = s.verdict;
    const one = r.cfg.scenario === 'signup_wave'
      ? `${fmt(s.finished)} of ${fmt(s.users)} accounts created, median ${ms(s.types.signup ? s.types.signup.p50 : 0)}, slowest 1% ${ms(s.p99)}.`
      : `${fmt(s.finished)} of ${fmt(s.users)} people finished the exam${s.lockedOut ? `, ${fmt(s.lockedOut)} could not get in` : ''}. The slowest 1% of answers took ${ms(s.p99)}.`;
    $('#verdict').innerHTML = `<h2>Verdict</h2><div class="verdict ${v.level}"><span class="tag">${esc(v.level === 'ok' ? 'Healthy' : v.level === 'tight' ? 'Tight' : v.level === 'warn' ? 'Degraded' : 'Failing')}</span><h3>${esc(v.title)}</h3><p class="dim">${esc(one)}</p></div>`;
  }
  function renderMetrics(r) {
    const s = r.summary, c = r.cfg.infra;
    const m = [
      ['Slowest 1% of requests', ms(s.p99), s.p99 > 3000 ? 'bad' : s.p99 > 800 ? 'warn' : ''],
      ['Median request', ms(s.p50), ''],
      ['Locked out / failed', fmt(s.lockedOut), s.lockedOut ? 'bad' : ''],
      ['Error rate', (s.errorRate * 100).toFixed(1) + ' %', s.errorRate > .02 ? 'bad' : s.errorRate > .002 ? 'warn' : ''],
      ['Workers held (peak)', `${s.peakWorkers} / ${c.workers}`, s.peakWorkers >= c.workers ? 'bad' : s.peakWorkers > c.workers * .8 ? 'warn' : ''],
      ['DB connections (peak)', `${s.peakDb} / ${c.dbMaxConnections}`, s.peakDb >= c.dbMaxConnections * .97 ? 'bad' : ''],
      ['CPU (peak)', Math.round(s.peakCpu * 100) + ' %', s.peakCpu > .92 ? 'bad' : s.peakCpu > .75 ? 'warn' : ''],
      ['Throughput (peak)', fmt(s.peakRps) + ' / s', ''],
      ['Memory for workers', `${fmt(s.memoryNeedMB)} MB`, s.memoryNeedMB > s.ramMB * .85 ? 'bad' : ''],
      ['Emails sent', `${fmt(s.mailSent)}${s.mailBacklogEnd ? ' · ' + s.mailBacklogEnd + ' waiting' : ''}`, s.mailFailed ? 'warn' : ''],
    ];
    $('#metrics').innerHTML = m.map(([l, v, k]) => `<div class="m ${k}"><b>${esc(v)}</b><span>${esc(l)}</span></div>`).join('');
  }
  function renderFindings(r) {
    const list = r.findings || [];
    $('#findings').innerHTML = list.map((f, i) => `<article class="finding ${f.sev}"><h4>${esc(f.title)}</h4><p>${esc(f.body)}</p>${f.fix && f.fix.length ? `<div class="row">${f.fix.map((x, j) => `<button class="btn" data-fix="${i}:${j}">${esc(x.label)} and run again</button>`).join('')}</div>` : ''}</article>`).join('');
  }
  $('#findings').addEventListener('click', (e) => {
    const b = e.target.closest('[data-fix]'); if (!b || !current) return;
    const [i, j] = b.dataset.fix.split(':').map(Number); const fix = current.findings[i].fix[j];
    const el = $(`[data-cfg="${fix.path}"]`); if (!el) return;
    if (el.type === 'checkbox') el.checked = !!fix.value; else el.value = fix.value;
    syncUsersSlider(); run(fix.label);
  });
  function renderSteps(r) {
    const ms_ = Object.values(r.milestones).sort((a, b) => a.t - b.t);
    const t = (x) => `${Math.floor(x / 60000)}:${String(Math.floor(x / 1000) % 60).padStart(2, '0')}`;
    const s = r.summary;
    const lines = [{ t: 0, label: `${fmt(r.cfg.users)} people start arriving over ${r.cfg.scenario === 'signup_wave' ? r.cfg.signup.windowS : r.cfg.exam.joinWindowS} s` }, ...ms_];
    if (s.mailSent || s.mailBacklogEnd) lines.push({ t: r.summary.durationS * 1000 - 2000, label: s.mailBacklogEnd ? `${s.mailBacklogEnd} result emails still waiting when the run ends` : `All ${fmt(s.mailSent)} result emails delivered` });
    $('#steps').innerHTML = lines.map(l => `<li data-t="${l.t}" class="future"><b>${t(l.t)}</b>${esc(l.label)}</li>`).join('');
  }
  function renderRuns() {
    if (!runs.length) { $('#runs').innerHTML = ''; return; }
    const base = runs[0];
    $('#runs').innerHTML = `<table class="t"><thead><tr><th>Run</th><th>People</th><th>Slowest 1%</th><th>Locked out</th><th>Verdict</th></tr></thead><tbody>${runs.map((r, i) => {
      const s = r.summary, lv = s.verdict.level === 'ok' ? 'ok' : s.verdict.level === 'fail' ? 'bad' : 'warn';
      const d = i && base.summary.p99 ? ` <small class="${s.p99 <= base.summary.p99 ? 'ok' : 'bad'}">${s.p99 <= base.summary.p99 ? '▼' : '▲'}${Math.abs(Math.round((s.p99 / Math.max(1, base.summary.p99) - 1) * 100))}%</small>` : '';
      return `<tr data-run="${i}" style="cursor:pointer"><td>${esc(r.label)}</td><td>${fmt(s.users)}</td><td>${ms(s.p99)}${d}</td><td class="${s.lockedOut ? 'bad' : ''}">${fmt(s.lockedOut)}</td><td class="${lv}">${esc(s.verdict.title)}</td></tr>`;
    }).join('')}</tbody></table><div class="row runs-actions"><button class="btn ghost" id="b-clear-runs">Clear history</button></div>`;
    $('#b-clear-runs').onclick = () => { runs.length = 1; renderRuns(); };
  }
  $('#runs').addEventListener('click', (e) => { const tr = e.target.closest('[data-run]'); if (tr) { show(runs[+tr.dataset.run]); viz.play(); } });

  /* ───────── transport ───────── */
  const mmss = (v) => `${Math.floor(v / 60000)}:${String(Math.floor(v / 1000) % 60).padStart(2, '0')}`;
  viz = OpsViz.create($('#stage'), {
    onTime(t, end) {
      $('#tlabel').textContent = `${mmss(t)} / ${mmss(end)}`; $('#scrub').value = Math.round(t / Math.max(1, end) * 1000);
      drawCharts.forEach(d => d(t / 1000));
      $$('#steps li[data-t]').forEach(li => { const on = Number(li.dataset.t) <= t; li.classList.toggle('future', !on); li.classList.toggle('on', on && !li.nextElementSibling || (on && Number(li.nextElementSibling.dataset.t) > t)); });
      $('#b-play').textContent = viz.playing ? '❚❚' : '▶';
    },
    onEnd() { $('#b-play').textContent = '▶'; },
  });
  $('#b-play').addEventListener('click', () => { viz.toggle(); $('#b-play').textContent = viz.playing ? '❚❚' : '▶'; });
  $('#scrub').addEventListener('input', (e) => { if (!current) return; const end = current.snaps[current.snaps.length - 1][0]; viz.seek(Number(e.target.value) / 1000 * end); });
  $('#speed').addEventListener('change', (e) => viz.speed(Number(e.target.value))); viz.speed(2);
  $('#b-run').addEventListener('click', () => run());
  $('#b-reset').addEventListener('click', () => { if (DETECTED) { writeCfg(DETECTED); } });

  $('#b-sweep').addEventListener('click', async () => {
    const cfg = readCfg(); busy(true, 'Looking for the breaking point…');
    const steps = [50, 100, 200, 300, 500, 800, 1200, 2000, 3000, 5000, 8000].filter(n => n <= 10000);
    try {
      const r = await callWorker('sweep', { cfg, steps });
      $('#sweep-panel').hidden = false;
      $('#sweep-note').innerHTML = r.maxSafeUsers ? `With these settings the platform stays healthy up to about <b>${fmt(r.maxSafeUsers)}</b> people at once.` : 'With these settings even the smallest room struggles.';
      const maxP = Math.max(...r.rows.map(x => x.p99), 1000);
      $('#sweep').innerHTML = r.rows.map(x => { const bad = x.level === 'fail' || x.level === 'warn'; const w = Math.max(2, Math.min(100, Math.log10(Math.max(10, x.p99)) / Math.log10(Math.max(100, maxP)) * 100)); return `<div class="sweep-row ${bad ? 'cut' : ''}"><span class="mono">${fmt(x.users)}</span><div class="bar ${x.level === 'fail' ? 'bad' : bad || x.level === 'tight' ? 'warn' : ''}"><i style="width:${w}%"></i></div><span class="mono">${ms(x.p99)}${x.lockedOut ? ' · ' + fmt(x.lockedOut) + ' out' : ''}</span></div>`; }).join('');
      $('#sweep-panel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (e) { $('#sweep-note').textContent = e.message; $('#sweep-panel').hidden = false; }
    busy(false);
  });
  $('#c-net').addEventListener('change', () => { });

  /* ───────── commands ───────── */
  function renderCommands() {
    $('#cmds').innerHTML = window.OPS.commands.map(c => `<article class="cmd"><h3>${esc(c.label)} <span class="risk ${c.risk}">${c.risk}</span></h3><p>${esc(c.about)}</p><div><button class="btn ${c.risk === 'high' ? '' : 'primary'}" data-cmd="${c.id}">Run</button></div></article>`).join('');
  }
  function modal(title, text, phrase) {
    return new Promise((res) => {
      const m = $('#modal'), inp = $('#m-in'); $('#m-t').textContent = title; $('#m-p').textContent = text; inp.hidden = !phrase; inp.value = ''; inp.placeholder = phrase ? 'Type ' + phrase : ''; m.hidden = false; (phrase ? inp : $('#m-yes')).focus();
      const done = (ok) => { m.hidden = true; $('#m-yes').onclick = $('#m-no').onclick = null; res(ok ? (phrase ? inp.value.trim() : true) : null); };
      $('#m-yes').onclick = () => done(true); $('#m-no').onclick = () => done(false);
    });
  }
  $('#cmds').addEventListener('click', async (e) => {
    const b = e.target.closest('[data-cmd]'); if (!b) return;
    const def = window.OPS.commands.find(c => c.id === b.dataset.cmd);
    let phrase = '';
    if (def.risk !== 'low') { const ans = await modal(def.label, def.about, def.phrase); if (ans === null) return; phrase = ans === true ? '' : ans; }
    if (def.id === 'export_report') {
      const blob = new Blob([JSON.stringify({ exported: new Date().toISOString(), state: STATE, calibration: CAL }, null, 2)], { type: 'application/json' });
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'studyvibe-system-report.json'; a.click(); $('#cmd-out').innerHTML = '<p>Report downloaded.</p>'; return;
    }
    b.disabled = true; $('#cmd-out').innerHTML = '<p class="dim">Running…</p>';
    try {
      const r = await api('command', { id: def.id, phrase });
      $('#cmd-out').innerHTML = `<p><b>${esc(def.label)}</b></p><p>${esc(r.message || 'Done.')}</p>` + (r.checks ? r.checks.map(c => `<div class="chk-row"><i class="${c.level}"></i><div>${esc(c.name)}<span>${esc(c.detail)}</span></div></div>`).join('') : '');
      loadState();
    } catch (err) { $('#cmd-out').innerHTML = `<p style="color:var(--red)">${esc(err.message)}</p>`; }
    b.disabled = false;
  });

  /* ───────── calibration ───────── */
  function renderCal(c) {
    const r = c.ref;
    const row = (name, mine, ref, unit) => `<tr><td>${esc(name)}</td><td>${mine == null ? '—' : mine + unit}</td><td class="dim">${ref + unit}</td><td class="${mine == null ? '' : mine / ref > 1.4 ? 'warn' : 'ok'}">${mine == null ? '' : '×' + (mine / ref).toFixed(2)}</td></tr>`;
    $('#cal-out').innerHTML = `<table class="t"><thead><tr><th>Probe</th><th>This server</th><th>Reference</th><th>Relative</th></tr></thead><tbody>${row('PHP compute loop', c.cpu_loop_ms, r.cpu_loop_ms, ' ms')}${row('Password hashing', c.bcrypt_ms, r.bcrypt_ms, ' ms')}${row('Database connection', c.db_connect_ms, r.db_connect_ms, ' ms')}${row('Database lookup', c.db_select_ms, r.db_select_ms, ' ms')}${row('Database write (committed)', c.db_write_ms, r.db_write_ms, ' ms')}</tbody></table>
      ${c.http ? `<h2 style="margin-top:1rem">Web round trips through the real stack</h2><table class="t"><thead><tr><th>At once</th><th>Median</th><th>95%</th><th>Per second</th></tr></thead><tbody>${c.http.map(h => `<tr><td>${h.concurrency}</td><td>${h.p50} ms</td><td>${h.p95} ms</td><td>${fmt(h.rps)}</td></tr>`).join('')}</tbody></table>` : '<p class="note">Web round trips were not measured: this server runs PHP in a mode that cannot call itself (for example the built-in development server).</p>'}
      <p style="margin-top:1rem">Speed factor applied to every simulation: <b class="mono" style="color:var(--clay)">×${c.speed}</b> <span class="dim">(1.00 = the reference machine the cost table was measured on)</span></p>`;
    $('#speed-note').innerHTML = `Calibrated to this server: <b>×${c.speed}</b> the reference speed.`;
  }
  $('#b-cal').addEventListener('click', async () => {
    const b = $('#b-cal'); b.disabled = true; $('#cal-out').innerHTML = '<div class="skel"></div>';
    try { const r = await api('calibrate', {}); CAL = r.calibration; renderCal(CAL); } catch (e) { $('#cal-out').innerHTML = `<p style="color:var(--red)">${esc(e.message)}</p>`; }
    b.disabled = false;
  });

  /* Real load tests (docs/PERFORMANCE.md) replayed through the model */
  const VAL_BASE = { net: { rttMs: 0, rttJitter: 0 }, infra: { cores: 8, dbMaxConnections: 151, flushLog: 1, dbOnSameHost: true, ramMB: 31000 }, mail: { smtpMs: 1700, workers: 4, dailyQuota: 100000 } };
  const LEG = { profile: 'legacy', mailQueued: false, signupMailQueued: false }, OPT = { profile: 'optimized', mailQueued: true, signupMailQueued: false };
  const VAL = [
    { id: 'A', note: 'Original code, stock Apache (150 workers, keep-alive 5 s)', users: 300, exam: { questions: 10, secondsPerQuestion: 15, joinWindowS: 30 }, infra: { workers: 150, keepAlive: true, keepAliveTimeoutS: 5, opcache: false }, code: LEG, real: { lockedOut: 150, p99: null, peakWorkers: 150, peakDb: null } },
    { id: 'B', note: 'Original code, keep-alive off', users: 300, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 30 }, infra: { workers: 150, keepAlive: false, opcache: false }, code: LEG, real: { lockedOut: 0, p99: 10309, peakWorkers: 150, peakDb: 150 } },
    { id: 'C', note: 'Optimized code, keep-alive off', users: 300, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 30 }, infra: { workers: 150, keepAlive: false, opcache: false }, code: OPT, real: { lockedOut: 0, p99: 53, peakWorkers: 8, peakDb: 23 } },
    { id: 'D', note: 'Optimized code, stock Apache', users: 300, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 30 }, infra: { workers: 150, keepAlive: true, keepAliveTimeoutS: 5, opcache: false }, code: OPT, real: { lockedOut: 150, p99: null, peakWorkers: 150, peakDb: null } },
    { id: 'E', note: 'Optimized, keep-alive 1 s, 600 workers (real browsers)', users: 300, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 30 }, infra: { workers: 600, keepAlive: true, keepAliveTimeoutS: 1, opcache: true, dbMaxConnections: 800, flushLog: 2 }, code: OPT, real: { lockedOut: 0, p99: 76, peakWorkers: 189, peakDb: 9 } },
    { id: 'F', note: 'Optimized, keep-alive off, 600 people', users: 600, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 40 }, infra: { workers: 600, keepAlive: false, opcache: true, dbMaxConnections: 800, flushLog: 2 }, code: OPT, real: { lockedOut: 0, p99: 119, peakWorkers: 15, peakDb: 36 } },
    { id: 'H', note: 'Optimized, keep-alive off, 2,000 people', users: 2000, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 90 }, infra: { workers: 600, keepAlive: false, opcache: true, dbMaxConnections: 800, flushLog: 2 }, code: OPT, real: { lockedOut: 0, p99: 448, peakWorkers: 121, peakDb: 103 } },
  ];
  $('#b-val').addEventListener('click', async () => {
    const b = $('#b-val'); b.disabled = true; $('#val-out').innerHTML = '<div class="skel" style="margin-top:1rem"></div>';
    const rows = []; let good = 0, total = 0;
    const ok = (sim, real, f) => real === 0 ? sim <= Math.max(2, f) : (sim / real <= f && real / Math.max(sim, 1e-9) <= f);
    for (const v of VAL) {
      const cfg = StudySim.merge(StudySim.merge(VAL_BASE, { users: v.users, exam: v.exam, infra: v.infra, code: v.code }), { detail: false });
      const s = (await callWorker('run', { cfg })).summary;
      const checks = [['Locked out', v.real.lockedOut, s.lockedOut, (a, bb) => bb === 0 ? a <= 5 : Math.abs(a - bb) <= bb * .35], v.real.p99 != null ? ['Slowest 1%', v.real.p99, s.p99, (a, bb) => ok(a, bb, 4)] : null, ['Workers held', v.real.peakWorkers, s.peakWorkers, (a, bb) => ok(a, bb, 2.2)], v.real.peakDb != null ? ['DB connections', v.real.peakDb, s.peakDb, (a, bb) => ok(a, bb, 2.5)] : null].filter(Boolean);
      checks.forEach(([name, real, sim, f]) => { const g = f(sim, real); total++; if (g) good++; rows.push(`<tr><td>${v.id}</td><td>${esc(name)}</td><td>${fmt(real)}</td><td>${fmt(sim)}</td><td class="${g ? 'ok' : 'warn'}">${g ? 'within range' : 'off'}</td></tr>`); });
    }
    $('#val-out').innerHTML = `<p style="margin-top:1rem"><b class="mono" style="color:var(--clay)">${good} of ${total}</b> predictions fall within the tolerance (about 2× on counts, 4× on latency tails).</p><table class="t"><thead><tr><th>Run</th><th>What</th><th>Real</th><th>Model</th><th></th></tr></thead><tbody>${rows.join('')}</tbody></table><p class="note">Runs: ${VAL.map(v => v.id + ' = ' + v.note).join(' · ')}. Latency tails are the least predictable thing in any model, treat them as an order of magnitude, and trust the structure (what saturates first) more than the exact milliseconds.</p>`;
    b.disabled = false;
  });

  /* ───────── boot ───────── */
  async function loadState() {
    try {
      const s = await api('state'); STATE = s; renderOverview(s); provenance(s);
      if (!DETECTED) {
        DETECTED = StudySim.merge(StudySim.DEFAULTS, s.sim);
        DETECTED.users = Math.max(300, DETECTED.users); DETECTED.scenario = 'live_exam';
        writeCfg(DETECTED);
      }
    } catch (e) { $('#ov').innerHTML = `<p style="color:var(--red)">${esc(e.message)}</p>`; }
  }
  $('#reload-state').addEventListener('click', loadState);
  window.__ops = { viz, run, readCfg };
  renderCommands(); syncUsersSlider(); loadState();
  tab((location.hash || '#ov').slice(1) || 'ov');
})();
