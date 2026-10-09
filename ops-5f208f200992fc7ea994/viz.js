/* Animated architecture diagram for a simulation run: browsers, Apache workers, PHP cores, database connections, mail queue,
   with requests travelling between them. It replays the snapshots recorded by sim.js, so it can pause, rewind and change speed. */
(function (root) {
  'use strict';
  const W = 1200, H = 560;
  const C = { bg: '#12100c', panel: '#1a1712', line: '#2e2a20', text: '#ede6d6', dim: '#8d8573', clay: '#e27b57', pine: '#8fbf92', ochre: '#e5b24f', blue: '#7aa5d6', red: '#ef5b5b', violet: '#a98bd6', grey: '#4a453a' };
  const TYPE_COLOR = { 1: C.blue, 2: '#6f8aa3', 3: C.ochre, 4: C.violet, 5: C.clay, 0: C.dim };

  function create(canvas, hooks) {
    const ctx = canvas.getContext('2d');
    const S = { run: null, t: 0, playing: false, speed: 1, last: 0, caption: null, capUntil: 0, seen: new Set() };
    let raf = 0, dpr = 1;

    function resize() {
      dpr = Math.min(2, window.devicePixelRatio || 1);
      const w = canvas.clientWidth; canvas.width = Math.round(w * dpr); canvas.height = Math.round(w * H / W * dpr);
      draw();
    }
    new ResizeObserver(resize).observe(canvas);

    const rr = (x, y, w, h, r) => { ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); };
    const label = (txt, x, y, size = 13, col = C.text, align = 'left', weight = '600') => { ctx.fillStyle = col; ctx.font = `${weight} ${size}px 'Hanken Grotesk', system-ui, sans-serif`; ctx.textAlign = align; ctx.textBaseline = 'alphabetic'; ctx.fillText(txt, x, y); };
    const panel = (x, y, w, h, title, sub) => { rr(x, y, w, h, 14); ctx.fillStyle = C.panel; ctx.fill(); ctx.strokeStyle = C.line; ctx.lineWidth = 1; ctx.stroke(); label(title, x + 16, y + 26, 14, C.text); if (sub) label(sub, x + 16, y + 44, 11, C.dim, 'left', '500'); };
    const hash = (i) => { let h = (i + 1) * 2654435761 >>> 0; h ^= h >>> 15; h = Math.imul(h, 2246822519) >>> 0; return (h >>> 0) / 4294967296; };

    function snapAt(t) { const r = S.run; if (!r || !r.snaps.length) return null; const i = Math.min(r.snaps.length - 1, Math.max(0, Math.floor(t / r.snapshotMs))); return r.snaps[i]; }
    function seriesAt(t) { const r = S.run; if (!r || !r.series.length) return null; return r.series[Math.min(r.series.length - 1, Math.max(0, Math.floor(t / 1000)))]; }

    /* positions of the nodes requests travel through */
    const P = { net: [305, 270], apacheIn: [395, 270], php: [805, 185], db: [1050, 175], queue: [372, 270] };
    const clientPos = (i) => [38 + hash(i) * 200, 92 + hash(i + 77777) * 360];

    function drawBrowsers(s, run) {
      panel(20, 62, 235, 420, 'Browsers', `${run.cfg.users.toLocaleString()} people`);
      const total = run.cfg.users, dots = Math.min(900, total), per = total / dots;
      const groups = [[s[1], C.grey], [s[2], C.clay], [s[3], C.ochre], [s[4], C.pine], [s[5], C.blue], [s[6], C.red]];
      let k = 0; const cols = Math.ceil(Math.sqrt(dots * 1.15)), cell = Math.min(11, 190 / cols);
      for (const [n, col] of groups) {
        const cnt = Math.round(n / per); ctx.fillStyle = col;
        for (let j = 0; j < cnt && k < dots; j++, k++) { const cx = 36 + (k % cols) * cell, cy = 100 + Math.floor(k / cols) * cell; ctx.beginPath(); ctx.arc(cx + 3, cy + 3, Math.max(1.6, cell * 0.33), 0, 6.283); ctx.fill(); }
      }
      const leg = [['Waiting to join', C.grey, s[1]], ['Opening the page', C.clay, s[2]], ['In the lobby', C.ochre, s[3]], ['Taking the exam', C.pine, s[4]], ['Finished', C.blue, s[5]], ['Locked out', C.red, s[6]]];
      leg.forEach(([n, col, v], i) => { const y = 400 + i * 13; ctx.fillStyle = col; ctx.fillRect(34, y - 8, 8, 8); label(`${n}`, 48, y, 10.5, C.dim, 'left', '500'); label(String(v), 240, y, 10.5, C.text, 'right'); });
    }

    function drawApache(s, run) {
      const I = run.cfg.infra, Wk = I.workers, exec = s[7], held = s[8], q = s[9];
      panel(360, 62, 345, 420, 'Apache workers', `${I.keepAlive ? 'keep-alive ' + I.keepAliveTimeoutS + ' s' : 'keep-alive off'} · ${Wk} workers`);
      // accept queue
      rr(366, 118, 22, 354, 8); ctx.fillStyle = '#0d0b08'; ctx.fill(); ctx.strokeStyle = q > 0 ? C.red : C.line; ctx.stroke();
      const qd = Math.min(q, 60); ctx.fillStyle = C.red; for (let i = 0; i < qd; i++) { ctx.fillRect(371, 468 - (i + 1) * 5.6, 12, 3.6); }
      label(q > 0 ? q + ' waiting' : 'queue', 377, 488, 10, q > 0 ? C.red : C.dim, 'center', '600');
      // worker grid
      const ax = 398, ay = 100, aw = 298, ah = 330;
      const cols = Math.max(4, Math.ceil(Math.sqrt(Wk * aw / ah))), rows = Math.ceil(Wk / cols), cw = aw / cols, ch = Math.min(ah / rows, cw);
      const sz = Math.max(2.5, Math.min(cw, ch) - 1.6);
      for (let i = 0; i < Wk; i++) {
        const cx = ax + (i % cols) * cw, cy = ay + Math.floor(i / cols) * ch;
        ctx.fillStyle = i < exec ? C.clay : (i < exec + held ? C.ochre : '#26221a');
        ctx.fillRect(cx, cy, sz, sz);
      }
      const full = exec + held >= Wk;
      label(`${exec} working`, 400, 452, 12, C.clay); label(`${held} idle (kept by keep-alive)`, 400, 470, 11, C.ochre, 'left', '500');
      label(`${exec + held} / ${Wk}`, 695, 462, 20, full ? C.red : C.text, 'right', '700');
    }

    function drawPhp(s, run) {
      panel(735, 62, 150, 250, 'PHP', `${run.cfg.infra.cores} cores`);
      const cores = run.cfg.infra.cores, util = s[11], on = Math.round(util * cores);
      const cols = Math.min(4, cores), cw = 30;
      for (let i = 0; i < cores; i++) { const cx = 758 + (i % cols) * (cw + 8), cy = 112 + Math.floor(i / cols) * (cw + 8); rr(cx, cy, cw, cw, 6); ctx.fillStyle = i < on ? (util > .9 ? C.red : C.pine) : '#26221a'; ctx.fill(); }
      const rows = Math.ceil(cores / cols);
      label(Math.round(util * 100) + '%', 810, 112 + rows * 38 + 34, 30, util > .9 ? C.red : C.text, 'center', '700');
      label('CPU in use', 810, 112 + rows * 38 + 54, 11, C.dim, 'center', '500');
    }

    function drawDb(s, run) {
      const I = run.cfg.infra, used = s[10], max = I.dbMaxConnections;
      panel(920, 62, 260, 250, 'MariaDB', `${max} connections allowed`);
      // cylinder
      const cx = 990, top = 128; ctx.fillStyle = '#24201a'; ctx.strokeStyle = C.line;
      ctx.beginPath(); ctx.ellipse(cx, top, 46, 13, 0, 0, 6.283); ctx.fill(); ctx.stroke();
      ctx.fillRect(cx - 46, top, 92, 76); ctx.beginPath(); ctx.ellipse(cx, top + 76, 46, 13, 0, 0, 6.283); ctx.fill(); ctx.stroke();
      ctx.beginPath(); ctx.ellipse(cx, top, 46, 13, 0, 0, 6.283); ctx.fillStyle = '#2f2a21'; ctx.fill(); ctx.stroke();
      label('data', cx, top + 46, 11, C.dim, 'center', '500');
      const pct = Math.min(1, used / max);
      label(`${used}`, 1100, 168, 34, pct > .9 ? C.red : C.text, 'center', '700'); label('connections open', 1100, 188, 11, C.dim, 'center', '500');
      rr(940, 238, 220, 12, 6); ctx.fillStyle = '#26221a'; ctx.fill(); rr(940, 238, Math.max(8, 220 * pct), 12, 6); ctx.fillStyle = pct > .9 ? C.red : pct > .6 ? C.ochre : C.pine; ctx.fill();
      label(`${Math.round(pct * 100)}% of the limit`, 940, 272, 11, C.dim, 'left', '500');
      label(run.cfg.infra.dbPersistent ? 'persistent connections' : 'a connection per request', 1160, 272, 11, C.dim, 'right', '500');
    }

    function drawMail(s, run) {
      panel(735, 332, 445, 150, 'Results emails', run.cfg.code.mailQueued ? 'queued, sent in the background' : 'sent inside each student\'s request');
      const backlog = s[14], sent = s[15], failed = s[16];
      // queue
      rr(756, 392, 150, 58, 10); ctx.fillStyle = '#0d0b08'; ctx.fill(); ctx.strokeStyle = C.line; ctx.stroke();
      const n = Math.min(backlog, 60); ctx.fillStyle = C.ochre;
      for (let i = 0; i < n; i++) { ctx.fillRect(764 + (i % 20) * 7, 399 + Math.floor(i / 20) * 14, 5, 9); }
      label(`${backlog} waiting`, 831, 466, 11, C.ochre, 'center', '600');
      // arrow + smtp
      ctx.strokeStyle = C.dim; ctx.setLineDash([4, 4]); ctx.beginPath(); ctx.moveTo(912, 420); ctx.lineTo(980, 420); ctx.stroke(); ctx.setLineDash([]);
      rr(986, 392, 168, 58, 10); ctx.fillStyle = '#0d0b08'; ctx.fill(); ctx.strokeStyle = C.line; ctx.stroke();
      label(String(sent), 1070, 424, 26, C.pine, 'center', '700'); label(failed ? `sent · ${failed} failed` : 'sent', 1070, 442, 11, failed ? C.red : C.dim, 'center', '500');
    }

    function drawLinks() {
      ctx.strokeStyle = '#3a3528'; ctx.lineWidth = 1.5; ctx.setLineDash([5, 6]); ctx.lineDashOffset = -((S.t / 60) % 11);
      [[255, 270, 360, 270], [705, 200, 735, 200], [885, 190, 920, 190], [800, 312, 800, 332]].forEach(([a, b, c, d]) => { ctx.beginPath(); ctx.moveTo(a, b); ctx.lineTo(c, d); ctx.stroke(); });
      ctx.setLineDash([]); label('network', 307, 258, 10, C.dim, 'center', '500');
    }

    function drawPackets(run) {
      const f = run.flows; if (!f.length) return;
      // first flow whose response has not arrived yet (flows are stored in order of response time)
      let lo = 0, hi = f.length; while (lo < hi) { const m = (lo + hi) >> 1; if (f[m][6] < S.t) lo = m + 1; else hi = m; }
      let shown = 0; const cap = 260, waitingBase = [P.queue[0], 130];
      for (let i = lo; i < f.length && shown < cap; i++) {
        const [ci, ty, tSend, tArr, tStart, tEnd, tResp, err] = f[i];
        if (tSend > S.t) continue; if (f[i][6] > S.t + 25000) break;
        const c = clientPos(ci); let x, y, col = err ? C.red : (TYPE_COLOR[ty] || C.dim), r = 3.2;
        const lerp = (a, b, p) => a + (b - a) * Math.max(0, Math.min(1, p));
        if (S.t < tArr) { const p = (S.t - tSend) / Math.max(1, tArr - tSend); x = lerp(c[0], P.apacheIn[0], p); y = lerp(c[1], P.apacheIn[1], p); }
        else if (S.t < tStart) { const k = hash(ci) ; x = P.queue[0]; y = 110 + ((S.t - tArr) / 40 + k * 300) % 340; r = 2.6; col = C.red; }
        else if (S.t < tEnd) {
          const p = (S.t - tStart) / Math.max(1, tEnd - tStart);
          const row = 120 + hash(ci + 3) * 300, col2 = 420 + hash(ci + 9) * 250;
          if (p < .35) { const q = p / .35; x = lerp(P.apacheIn[0], col2, q); y = lerp(P.apacheIn[1], row, q); }
          else if (p < .6) { const q = (p - .35) / .25; x = lerp(col2, P.php[0], q); y = lerp(row, P.php[1], q); }
          else { const q = (p - .6) / .4; x = lerp(P.php[0], P.db[0], q); y = lerp(P.php[1], P.db[1], q); }
          r = 3.8;
        } else { const p = (S.t - tEnd) / Math.max(1, tResp - tEnd); x = lerp(P.apacheIn[0], c[0], p); y = lerp(P.apacheIn[1], c[1], p); col = err ? C.red : '#cfc7b3'; }
        ctx.globalAlpha = .9; ctx.fillStyle = col; ctx.beginPath(); ctx.arc(x, y, r, 0, 6.283); ctx.fill(); shown++;
      }
      ctx.globalAlpha = 1;
    }

    function drawHud(run, s) {
      const se = seriesAt(S.t);
      const clock = `${Math.floor(S.t / 60000)}:${String(Math.floor(S.t / 1000) % 60).padStart(2, '0')}`;
      label('T+' + clock, 22, 36, 22, C.text, 'left', '700');
      const items = se ? [['requests / s', se.rps, C.text], ['median', se.p50 + ' ms', C.text], ['slowest 1%', se.p99 >= 1000 ? (se.p99 / 1000).toFixed(1) + ' s' : se.p99 + ' ms', se.p99 > 1000 ? C.red : C.text], ['errors / s', se.err, se.err ? C.red : C.dim]] : [];
      items.forEach(([n, v, col], i) => { const x = 780 + i * 105; label(String(v), x, 30, 20, col, 'left', '700'); label(n, x, 46, 10.5, C.dim, 'left', '500'); });
      // milestone captions
      const ms = run.milestones; Object.keys(ms).forEach(k => { const m = ms[k]; if (S.t >= m.t && !S.seen.has(k)) { S.seen.add(k); S.caption = m.label; S.capUntil = S.t + 5500; if (hooks && hooks.onMilestone) hooks.onMilestone(k, m); } });
      if (S.caption && S.t < S.capUntil) {
        const w = Math.min(760, 40 + S.caption.length * 8); rr(W / 2 - w / 2, 506, w, 38, 12); ctx.fillStyle = 'rgba(0,0,0,.72)'; ctx.fill(); ctx.strokeStyle = C.clay; ctx.stroke(); label(S.caption, W / 2, 530, 15, C.text, 'center', '600');
      }
    }

    function draw() {
      ctx.setTransform(dpr * canvas.width / dpr / W, 0, 0, dpr * canvas.width / dpr / W, 0, 0);
      const k = canvas.width / W; ctx.setTransform(k, 0, 0, k, 0, 0);
      ctx.clearRect(0, 0, W, H); ctx.fillStyle = C.bg; ctx.fillRect(0, 0, W, H);
      const run = S.run;
      if (!run) { label('Choose a scenario and press Run: the servers will be drawn here.', W / 2, H / 2, 18, C.dim, 'center', '500'); return; }
      const s = snapAt(S.t); if (!s) return;
      drawLinks(); drawBrowsers(s, run); drawApache(s, run); drawPhp(s, run); drawDb(s, run); drawMail(s, run); drawPackets(run); drawHud(run, s);
    }

    function frame(ts) {
      if (!S.playing) return;
      const dt = Math.min(80, ts - S.last); S.last = ts;
      S.t += dt * S.speed;
      const end = S.run ? S.run.snaps[S.run.snaps.length - 1][0] : 0;
      if (S.t >= end) { S.t = end; S.playing = false; if (hooks && hooks.onEnd) hooks.onEnd(); }
      if (hooks && hooks.onTime) hooks.onTime(S.t, end);
      draw();
      if (S.playing) raf = requestAnimationFrame(frame);
    }

    return {
      setRun(r) { S.run = r; S.t = 0; S.seen = new Set(); S.caption = null; draw(); if (hooks && hooks.onTime && r) hooks.onTime(0, r.snaps[r.snaps.length - 1][0]); },
      play() { if (!S.run) return; if (S.t >= S.run.snaps[S.run.snaps.length - 1][0]) { S.t = 0; S.seen = new Set(); } S.playing = true; S.last = performance.now(); cancelAnimationFrame(raf); raf = requestAnimationFrame(frame); },
      pause() { S.playing = false; },
      toggle() { S.playing ? this.pause() : this.play(); return S.playing; },
      seek(ms) { S.t = ms; S.seen = new Set(); if (S.run) for (const k in S.run.milestones) if (S.run.milestones[k].t < ms) S.seen.add(k); draw(); if (hooks && hooks.onTime && S.run) hooks.onTime(S.t, S.run.snaps[S.run.snaps.length - 1][0]); },
      speed(x) { S.speed = x; },
      get time() { return S.t; }, get playing() { return S.playing; },
      redraw: draw,
    };
  }

  /* small time-series charts under the stage, with a cursor at the current replay time */
  function chart(canvas, series, opts) {
    const ctx = canvas.getContext('2d'); const dpr = Math.min(2, window.devicePixelRatio || 1);
    const w = canvas.clientWidth, h = canvas.clientHeight; canvas.width = w * dpr; canvas.height = h * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const pad = { l: 38, r: 8, t: 16, b: 16 };
    return function draw(cursorSec) {
      ctx.clearRect(0, 0, w, h);
      const n = Math.max(1, ...opts.lines.map(l => l.data.length)); let max = 1;
      opts.lines.forEach(l => l.data.forEach(v => { if (v > max) max = v; }));
      const log = opts.log; const f = (v) => log ? Math.log10(Math.max(1, v)) / Math.log10(Math.max(10, max)) : v / max;
      ctx.strokeStyle = '#2a261e'; ctx.lineWidth = 1; ctx.fillStyle = '#8d8573'; ctx.font = '10px system-ui'; ctx.textAlign = 'right';
      for (let g = 0; g <= 2; g++) { const y = pad.t + (1 - g / 2) * (h - pad.t - pad.b); ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(w - pad.r, y); ctx.stroke(); const v = log ? Math.pow(Math.max(10, max), g / 2) : max * g / 2; ctx.fillText(v >= 1000 ? (v / 1000).toFixed(v >= 10000 ? 0 : 1) + 'k' : Math.round(v) + '', pad.l - 5, y + 3); }
      ctx.textAlign = 'left'; ctx.fillStyle = '#8d8573'; ctx.fillText(opts.title, pad.l, 11);
      opts.lines.forEach(l => {
        ctx.strokeStyle = l.color; ctx.lineWidth = 1.6; ctx.beginPath();
        l.data.forEach((v, i) => { const x = pad.l + (i / Math.max(1, n - 1)) * (w - pad.l - pad.r), y = pad.t + (1 - Math.min(1, f(v))) * (h - pad.t - pad.b); i ? ctx.lineTo(x, y) : ctx.moveTo(x, y); });
        ctx.stroke();
      });
      if (cursorSec != null) { const x = pad.l + (Math.min(n - 1, cursorSec) / Math.max(1, n - 1)) * (w - pad.l - pad.r); ctx.strokeStyle = '#ede6d6'; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(x, pad.t); ctx.lineTo(x, h - pad.b); ctx.stroke(); }
    };
  }

  root.OpsViz = { create, chart, colors: C };
})(typeof self !== 'undefined' ? self : this);
