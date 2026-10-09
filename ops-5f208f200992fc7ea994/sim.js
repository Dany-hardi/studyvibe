/* StudyVibe load simulator: a discrete-event model of the real request path.
 *
 *   browsers ──network──> Apache workers (prefork, keep-alive) ──> PHP (CPU shared by all cores) ──> MariaDB connections
 *                                                                              └──> mail queue ──> SMTP
 *
 * Nothing here is random scripting: every number comes from the cost table below (measured on the reference machine with the
 * load tests in tests/load) scaled by the live calibration of the server this runs on, and from the infrastructure and code
 * profile the control center reads from the real installation.
 *
 * Pure JS, no DOM: runs in a Web Worker or in Node (tests/sim).
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.StudySim = factory();
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  /* ───────────── defaults ───────────── */
  const DEFAULTS = {
    scenario: 'live_exam',                       // live_exam | signup_wave
    users: 300,
    exam: { questions: 10, secondsPerQuestion: 15, joinWindowS: 30, leadS: 45, pollMs: 2000, lobbyPollMs: 2500 },
    signup: { windowS: 60 },
    infra: { cores: 8, workers: 150, keepAlive: true, keepAliveTimeoutS: 5, listenBacklog: 511, dbMaxConnections: 151, dbPersistent: false, dbOnSameHost: true, flushLog: 1, opcache: false, ramMB: 8192, memPerWorkerMB: 22 },
    code: { profile: 'optimized', mailQueued: true, signupMailQueued: false },
    net: { rttMs: 80, rttJitter: 0.35, tls: true, timeoutS: 20 },
    mail: { smtpMs: 1700, workers: 4, dailyQuota: 500, quotaUsed: 0, down: false },
    speed: 1,                                    // >1 = this machine is slower than the reference machine
    seed: 7,
    snapshotMs: 250,
    flowSample: 0.04,                            // share of requests kept for the animation
    detail: true,
  };

  /* CPU cost per request in ms on the reference machine (opcache on, optimized code), plus database work. */
  const COST = {
    page:       { cpu: 9.0,  stmts: 5,    writes: 0 },
    register:   { cpu: 8.0,  stmts: 5,    writes: 1 },
    poll_lobby: { cpu: 2.6,  stmts: 0.12, writes: 0 },
    poll_quiz:  { cpu: 4.8,  stmts: 1.8,  writes: 0.5 },
    submit:     { cpu: 3.8,  stmts: 2,    writes: 1 },
    finish:     { cpu: 6.0,  stmts: 6,    writes: 2.5 },
    landing:    { cpu: 7.0,  stmts: 2,    writes: 1 },
    signup:     { cpu: 8.0 + 65.0, stmts: 7, writes: 3 },     // 65 ms of password hashing
  };
  const LEGACY_EXTRA = {                          // what the original code paid on top, per request
    all:        { cpu: 0.8, stmts: 23, alters: 9 },           // schema probes and failing ALTER TABLE on every request
    poll_quiz:  { cpu: 0.6, stmts: 6,  writes: 0.5 },         // unshared counters, activity update on every poll
    poll_lobby: { cpu: 0.5, stmts: 3 },
    finish:     { cpu: 1.0, stmts: 4 },
  };
  const STMT_MS = 0.12, ALTER_MS = 0.4, WRITE_CPU_MS = 0.25, CONNECT_CPU_MS = 0.5, CONNECT_DELAY_MS = 0.4;
  const FSYNC_MS = { 1: 3.2, 2: 0.15 };
  const MAIL_CPU_MS = 25;

  /* ───────────── tiny utilities ───────────── */
  function rng(seed) { let a = seed >>> 0; return function () { a |= 0; a = (a + 0x6D2B79F5) | 0; let t = Math.imul(a ^ (a >>> 15), 1 | a); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; }; }
  const merge = (a, b) => { const o = Array.isArray(a) ? a.slice() : Object.assign({}, a); for (const k in (b || {})) o[k] = (b[k] && typeof b[k] === 'object' && !Array.isArray(b[k]) && a[k] && typeof a[k] === 'object') ? merge(a[k], b[k]) : b[k]; return o; };

  class Heap {                                     // min-heap on .t then .n
    constructor() { this.a = []; }
    get size() { return this.a.length; }
    push(x) { const a = this.a; a.push(x); let i = a.length - 1; while (i > 0) { const p = (i - 1) >> 1; if (this._lt(a[i], a[p])) { [a[i], a[p]] = [a[p], a[i]]; i = p; } else break; } }
    pop() { const a = this.a; const top = a[0], last = a.pop(); if (a.length) { a[0] = last; let i = 0; for (;;) { let l = 2 * i + 1, r = l + 1, m = i; if (l < a.length && this._lt(a[l], a[m])) m = l; if (r < a.length && this._lt(a[r], a[m])) m = r; if (m === i) break; [a[i], a[m]] = [a[m], a[i]]; i = m; } } return top; }
    peek() { return this.a[0]; }
    _lt(x, y) { return x.t < y.t || (x.t === y.t && x.n < y.n); }
  }
  class VHeap extends Heap { _lt(x, y) { return x.v < y.v || (x.v === y.v && x.n < y.n); } }

  /** Log-scale latency histogram, 1 ms to ~2 minutes. */
  class Hist {
    constructor() { this.b = new Uint32Array(220); this.n = 0; this.max = 0; this.sum = 0; }
    add(ms) { const i = Math.max(0, Math.min(219, Math.floor(Math.log(Math.max(1, ms)) / Math.log(1.0525)))); this.b[i]++; this.n++; this.sum += ms; if (ms > this.max) this.max = ms; }
    q(p) { if (!this.n) return 0; const target = Math.ceil(this.n * p); let c = 0; for (let i = 0; i < 220; i++) { c += this.b[i]; if (c >= target) return Math.round(Math.pow(1.0525, i + 0.5)); } return Math.round(this.max); }
  }

  /** Processor sharing: n tasks share `cores` cores, each runs at min(1, cores/n). Virtual-time implementation, O(log n) per event. */
  class PS {
    constructor(cores) { this.cores = cores; this.vt = 0; this.last = 0; this.heap = new VHeap(); this.n = 0; this.token = 0; this.busyArea = 0; }
    rate() { return this.n === 0 ? 1 : Math.min(1, this.cores / this.n); }
    advance(t) { const dt = t - this.last; if (dt > 0) { this.vt += dt * this.rate(); this.busyArea += dt * Math.min(this.n, this.cores); this.last = t; } }
  }

  /* ───────────── the simulation ───────────── */
  function simulate(userCfg) {
    const cfg = merge(DEFAULTS, userCfg || {});
    const R = rng(cfg.seed), I = cfg.infra, N = cfg.users, NET = cfg.net, legacy = cfg.code.profile === 'legacy';
    const TIMEOUT = NET.timeoutS * 1000;
    const ev = new Heap(); let seq = 0, now = 0;
    const at = (t, fn) => { ev.push({ t, n: seq++, fn }); };

    const ps = new PS(I.cores);
    let psPending = null;

    /* resources */
    let workersHeld = 0, workersExec = 0;          // held = executing + idle keep-alive
    const queue = [];                              // waiting connections {req, dead}
    let dbConns = 0, dbPeak = 0;
    let persistentConns = 0;
    let busyPeak = 0, queuePeak = 0, cpuSamples = 0;

    /* mail */
    const mailQ = []; let mailActive = 0, mailSent = 0, mailFailed = 0, mailPeak = 0, mailDoneAt = 0, mailQuotaLeft = Math.max(0, cfg.mail.dailyQuota - cfg.mail.quotaUsed), mailQuotaHit = false;

    /* metrics */
    const types = {}; const histAll = {}; const bucketMs = 1000;
    const series = [];                              // per second
    const win = { h: new Hist(), done: 0, err: 0, arr: 0 };
    let errs = {}; let totalReq = 0, totalErr = 0;
    const flows = []; const milestones = {};
    const mark = (k, label) => { if (!(k in milestones)) milestones[k] = { t: now, label }; };

    /* clients */
    const clients = new Array(N);
    const counts = { idle: N, loading: 0, lobby: 0, exam: 0, done: 0, failed: 0 };
    const setState = (c, s) => { counts[c.state]--; c.state = s; counts[s]++; };

    const exam = cfg.exam;
    const startAt = cfg.scenario === 'live_exam' ? (exam.joinWindowS + exam.leadS) * 1000 : 0;
    const examLen = exam.questions * exam.secondsPerQuestion * 1000;
    const endAt = startAt + examLen;

    /* service times are never constant: lognormal spread (sigma 0.55) around the table value */
    const gauss = () => { const u = Math.max(1e-9, R()), v = R(); return Math.sqrt(-2 * Math.log(u)) * Math.cos(2 * Math.PI * v); };
    const jit = () => Math.exp(gauss() * 0.55 - 0.15);
    let inlineSmtp = 0;

    /* ── cost of one request ── */
    function costOf(type, c) {
      const base = COST[type]; let cpu = base.cpu, stmts = base.stmts, writes = base.writes || 0, alters = 0;
      if (!I.opcache) cpu *= 1.9;
      if (legacy) {
        const e = LEGACY_EXTRA.all, x = LEGACY_EXTRA[type] || {};
        cpu += e.cpu + (x.cpu || 0); stmts += e.stmts + (x.stmts || 0); alters = e.alters; writes += x.writes || 0;
      }
      let dbCpu = stmts * STMT_MS + alters * ALTER_MS + writes * WRITE_CPU_MS;
      let connectCpu = 0, connectDelay = 0;
      if (!I.dbPersistent) { connectCpu = CONNECT_CPU_MS; connectDelay = CONNECT_DELAY_MS; }
      const j = jit();
      let cpuMs = (cpu + connectCpu + (I.dbOnSameHost ? dbCpu : 0)) * cfg.speed * j;
      let delayMs = (connectDelay + (I.dbOnSameHost ? 0 : dbCpu) + writes * FSYNC_MS[I.flushLog] + stmts * 0.04) * j;
      let holdDb = true;
      // The mail is sent inside the request. Many simultaneous sessions slow every one of them down (TLS handshakes, provider throttling).
      if (type === 'finish' && !cfg.code.mailQueued) { delayMs += (cfg.mail.down ? 8000 : cfg.mail.smtpMs) * (1 + inlineSmtp / 40); }
      if (type === 'signup' && !cfg.code.signupMailQueued) { delayMs += (cfg.mail.down ? 8000 : cfg.mail.smtpMs) * 2; }
      return { cpuMs, delayMs, holdDb };
    }

    /* ── processor sharing plumbing ── */
    function psAdd(workMs, done) {
      ps.advance(now);
      const task = { v: ps.vt + workMs, n: seq++, done };
      ps.heap.push(task); ps.n++;
      psReschedule();
    }
    function psReschedule() {
      const tok = ++ps.token;
      if (!ps.heap.size) return;
      const dt = Math.max(0, (ps.heap.peek().v - ps.vt) / ps.rate());
      at(now + dt, () => {
        if (tok !== ps.token) return;
        ps.advance(now);
        while (ps.heap.size && ps.heap.peek().v <= ps.vt + 1e-9) { const t = ps.heap.pop(); ps.n--; t.done(); }
        psReschedule();
      });
    }

    /* ── server side of a request ── */
    function rec(type, ms, err) {
      totalReq++;
      const h = histAll[type] || (histAll[type] = new Hist()); h.add(ms);
      const t = types[type] || (types[type] = { n: 0, err: 0 }); t.n++;
      win.done++; win.h.add(ms);
      if (err) { t.err++; totalErr++; win.err++; errs[type + ':' + err] = (errs[type + ':' + err] || 0) + 1; }
    }

    function newConn(req) {
      const c = req.client, cn = { open: true, idle: false, idleId: 0, owner: c, primary: !req.parallel };
      workersHeld++; req.conn = cn; if (cn.primary) c.conn = cn;
      return cn;
    }
    function release(req) {
      if (req.holdsDb && !I.dbPersistent) { dbConns--; req.holdsDb = false; }
      workersExec--;
      const cn = req.conn;
      if (!cn || !cn.open) return;
      if (I.keepAlive && I.keepAliveTimeoutS > 0 && !req.aborted) {
        cn.idle = true; const id = ++cn.idleId;
        at(now + I.keepAliveTimeoutS * 1000, () => { if (cn.open && cn.idle && cn.idleId === id) closeConn(cn); });
      } else closeConn(cn);
    }
    function closeConn(cn) {
      if (!cn || !cn.open) return; cn.open = false; if (cn.owner.conn === cn) cn.owner.conn = null; workersHeld--;
      dispatchQueue();
    }
    function dispatchQueue() {
      while (workersHeld < I.workers && queue.length) {
        const q = queue.shift(); if (q.dead) continue;
        newConn(q.req);
        at(now + 0.15, () => execute(q.req));
      }
    }

    function execute(req) {
      workersExec++; if (workersHeld > busyPeak) busyPeak = workersHeld;
      if (req.dead) { req.aborted = true; release(req); return; }            // the client gave up while waiting
      req.tStart = now;
      // a database connection
      if (I.dbPersistent) {
        // one per Apache child, kept between requests: the count follows the number of workers that ever ran
        if (workersHeld > persistentConns) { if (workersHeld > I.dbMaxConnections) return failFast(req, 'db_max'); persistentConns = workersHeld; }
        dbConns = persistentConns;
      } else {
        if (dbConns >= I.dbMaxConnections) return failFast(req, 'db_max');
        dbConns++; req.holdsDb = true;
      }
      if (dbConns > dbPeak) dbPeak = dbConns;
      if (dbConns >= I.dbMaxConnections * 0.97) mark('db_full', 'Database connections almost exhausted');
      const cost = costOf(req.type, req.client);
      if (req.type === 'finish' && !cfg.code.mailQueued) { inlineSmtp++; req.smtp = true; }
      psAdd(cost.cpuMs, () => {
        at(now + cost.delayMs, () => finishExec(req));
      });
    }
    function failFast(req, why) {
      req.err = why; at(now + 3, () => finishExec(req));
    }
    function finishExec(req) {
      req.tEnd = now;
      if (req.smtp) { inlineSmtp--; req.smtp = false; }
      if (req.type === 'finish' && !req.err) onFinishServer(req);
      release(req);
      // response travels back
      at(now + req.rtt / 2, () => onResponse(req));
    }

    function onFinishServer(req) {
      if (cfg.scenario !== 'live_exam') return;
      if (!cfg.code.mailQueued) { return; }                      // sent inline during the request
      mailQ.push({ attempts: 0, at: now }); if (mailQ.length > mailPeak) mailPeak = mailQ.length; pumpMail();
    }

    /* ── mail workers ── */
    function pumpMail() {
      while (mailActive < cfg.mail.workers && mailQ.length) {
        const j = mailQ.find(x => x.at <= now); if (!j) { break; }
        mailQ.splice(mailQ.indexOf(j), 1); mailActive++; j.attempts++;
        const cost = cfg.mail.down ? 3000 : cfg.mail.smtpMs * (0.85 + R() * 0.3);
        psAdd(MAIL_CPU_MS * cfg.speed, () => {
          at(now + cost, () => {
            mailActive--;
            if (cfg.mail.down || mailQuotaLeft <= 0) {
              if (mailQuotaLeft <= 0 && !cfg.mail.down) mailQuotaHit = true;
              if (j.attempts < 3 && !mailQuotaHit) { j.at = now + 90000; mailQ.push(j); at(j.at, pumpMail); } else mailFailed++;
            } else { mailSent++; mailQuotaLeft--; mailDoneAt = now; }
            pumpMail();
          });
        });
      }
    }

    /* ── network: client side ── */
    function sendRequest(c, type, after) {
      const req = { client: c, type, tSend: now, rtt: NET.rttMs * (1 + (R() - 0.5) * 2 * NET.rttJitter), after, dead: false, err: null, parallel: !!(c.inflight && !c.inflight.done) };
      if (!req.parallel) c.inflight = req;
      const cn = c.conn;
      let travel = req.rtt / 2;
      if (req.parallel || !(cn && cn.open)) travel += req.rtt * (NET.tls ? 2 : 1);   // TCP (+ TLS) handshake
      req.sample = cfg.detail && R() < cfg.flowSample;
      at(now + TIMEOUT, () => { if (!req.done) { req.done = true; req.dead = true; req.aborted = true; rec(type, TIMEOUT, 'timeout'); onClientResult(req, 'timeout'); } });
      at(now + travel, () => arrive(req));
      win.arr++;
      return req;
    }
    function arrive(req) {
      if (req.dead) return;
      const c = req.client; req.tArrive = now;
      if (!req.parallel && c.conn && c.conn.open) { const cn = c.conn; cn.idle = false; cn.idleId++; req.conn = cn; execute(req); return; }
      if (workersHeld < I.workers) { newConn(req); at(now + 0.15, () => execute(req)); return; }
      if (queue.length >= I.listenBacklog) { req.err = 'refused'; at(now + req.rtt / 2, () => onResponse(req)); return; }
      const q = { req, dead: false }; queue.push(q); req.q = q; if (queue.length > queuePeak) queuePeak = queue.length;
      mark('queue', 'Requests start waiting for a free worker');
    }
    function onResponse(req) {
      if (req.done) return;                         // already timed out client side
      req.done = true; const ms = now - req.tSend; req.tResp = now;
      if (ms > TIMEOUT) { rec(req.type, TIMEOUT, 'timeout'); return onClientResult(req, 'timeout'); }
      rec(req.type, ms, req.err);
      if (req.sample && flows.length < 24000) flows.push([req.client.i, req.type === 'poll_quiz' ? 1 : req.type === 'poll_lobby' ? 2 : req.type === 'submit' ? 3 : req.type === 'finish' ? 4 : req.type === 'register' || req.type === 'signup' ? 5 : 0, Math.round(req.tSend), Math.round(req.tArrive || req.tSend), Math.round(req.tStart || req.tArrive || req.tSend), Math.round(req.tEnd || now), Math.round(now), req.err ? 1 : 0]);
      onClientResult(req, req.err);
    }
    function onClientResult(req, err) {
      if (err === 'timeout') {
        mark('timeouts', 'First requests time out');
        if (req.q) req.q.dead = true;
        // the client closes its socket; a held worker is released when its request ends
        const c = req.client; if (c.conn && !req.tStart) { /* never reached a worker */ }
      }
      if (err === 'db_max') mark('db_refused', 'Database refuses connections');
      const c = req.client; if (c.inflight === req) c.inflight = null;
      if (req.after) req.after(err);
    }

    /* ── live exam behaviour of one student ── */
    function startStudent(c) {
      const jitter = R() * exam.joinWindowS * 1000;
      at(jitter, () => {
        setState(c, 'loading');
        sendRequest(c, 'page', (e1) => {
          sendRequest(c, 'register', (e2) => {
            if (e2) {
              c.tries = (c.tries || 0) + 1;
              if (c.tries >= 3) { setState(c, 'failed'); return; }
              at(now + 5000, () => { startStudent2(c); });   // the student presses the button again
              return;
            }
            sendRequest(c, 'page', () => lobby(c));
          });
        });
      });
    }
    function startStudent2(c) { sendRequest(c, 'register', (e) => { if (e) { c.tries++; if (c.tries >= 3) setState(c, 'failed'); else at(now + 5000, () => startStudent2(c)); } else sendRequest(c, 'page', () => lobby(c)); }); }
    function lobby(c) {
      setState(c, 'lobby');
      const tick = () => {
        if (c.state === 'failed' || c.state === 'done') return;
        if (now >= startAt) { setState(c, 'exam'); return examLoop(c); }
        sendRequest(c, 'poll_lobby', () => at(now + exam.lobbyPollMs * (0.95 + R() * 0.1), tick));
      };
      tick();
    }
    function examLoop(c) {
      let lastQ = -1;
      const tick = () => {
        if (c.state === 'done' || c.state === 'failed') return;
        const finished = now >= endAt;
        sendRequest(c, finished ? 'finish' : 'poll_quiz', (err) => {
          if (finished && !err) { setState(c, 'done'); return; }
          if (!finished) {
            const q = Math.floor((now - startAt) / (exam.secondsPerQuestion * 1000));
            if (q !== lastQ && q >= 0 && q < exam.questions) {
              lastQ = q;
              const wait = 1500 + R() * Math.max(500, (exam.secondsPerQuestion - 4) * 1000);
              at(now + wait, () => { if (c.state === 'exam') sendRequest(c, 'submit', null); });
            }
          }
          at(now + exam.pollMs * (0.95 + R() * 0.1), tick);
        });
      };
      tick();
    }

    /* ── sign-up wave behaviour ── */
    function startSignup(c) {
      at(R() * cfg.signup.windowS * 1000, () => {
        setState(c, 'loading');
        sendRequest(c, 'landing', () => at(now + 8000 + R() * 12000, () => {          // the person fills in the form
          sendRequest(c, 'signup', (e) => { if (e) { c.tries = (c.tries || 0) + 1; if (c.tries < 3) return at(now + 4000, () => startSignup2(c)); setState(c, 'failed'); } else setState(c, 'done'); });
        }));
      });
    }
    function startSignup2(c) { sendRequest(c, 'signup', (e) => { if (e) { c.tries++; if (c.tries < 3) at(now + 4000, () => startSignup2(c)); else setState(c, 'failed'); } else setState(c, 'done'); }); }

    for (let i = 0; i < N; i++) { clients[i] = { i, state: 'idle', conn: null, inflight: null, tries: 0 }; (cfg.scenario === 'signup_wave' ? startSignup : startStudent)(clients[i]); }

    /* ── sampling ── */
    const snaps = []; let snapN = 0, lastSecond = 0, busySnap = 0;
    function snapshot() {
      ps.advance(now);
      const t = now / 1000;
      if (cfg.detail) snaps.push([
        Math.round(now), counts.idle, counts.loading, counts.lobby, counts.exam, counts.done, counts.failed,
        workersExec, Math.max(0, workersHeld - workersExec), queue.reduce((a, q) => a + (q.dead ? 0 : 1), 0), dbConns,
        +(Math.min(1, ps.n / I.cores)).toFixed(3), win.done, win.err, mailQ.length + mailActive, mailSent, mailFailed,
      ]);
      cpuSamples++;
      if (now - lastSecond >= bucketMs) {
        series.push({ t: Math.round(now / 1000), rps: win.done, arrivals: win.arr, err: win.err, p50: win.h.q(.5), p95: win.h.q(.95), p99: win.h.q(.99), cpu: Math.min(1, ps.n / I.cores), busy: workersHeld, exec: workersExec, queue: queue.length, db: dbConns, mail: mailQ.length + mailActive });
        win.h = new Hist(); win.done = 0; win.err = 0; win.arr = 0; lastSecond = now;
      }
      if (workersHeld >= I.workers) mark('saturated', 'Every Apache worker is taken');
      if (ps.n > I.cores * 1.5) mark('cpu', 'CPU is the limit: requests wait for a core');
      if (mailQ.length > 0 && cfg.scenario === 'live_exam') mark('mail', 'Results emails start queueing');
    }
    const horizon = (cfg.scenario === 'signup_wave' ? (cfg.signup.windowS + 90) * 1000 : endAt + 45000) + TIMEOUT * 0.5;
    for (let t = 0; t <= horizon + 120000; t += cfg.snapshotMs) at(t, () => { if (t <= horizon || mailQ.length || mailActive || ps.n) { snapshot(); } });
    if (cfg.scenario === 'live_exam') at(startAt, () => mark('start', 'The exam starts'));
    if (cfg.scenario === 'live_exam') at(endAt, () => mark('finish', 'Everybody reaches the end together'));

    /* ── run ── */
    let steps = 0, stopAt = horizon + 120000;
    while (ev.size) {
      const e = ev.pop(); if (e.t > stopAt) break; now = e.t; e.fn(); steps++;
      if (steps % 4 === 0 && now > horizon && counts.idle + counts.loading + counts.lobby + counts.exam === 0 && !mailQ.length && !mailActive && ps.n === 0) break;
    }
    snapshot();

    /* ── results ── */
    const summary = { users: N, requests: totalReq, errors: totalErr, errorRate: totalReq ? totalErr / totalReq : 0, finished: counts.done, lockedOut: counts.failed, notFinished: N - counts.done - counts.failed, types: {}, errorsBy: errs,
      peakWorkers: busyPeak, workers: I.workers, peakQueue: queuePeak, peakDb: dbPeak, dbMax: I.dbMaxConnections, peakCpu: series.reduce((a, s) => Math.max(a, s.cpu), 0), avgCpu: ps.busyArea / Math.max(1, now) / I.cores,
      mailSent, mailFailed, mailPeak, mailBacklogEnd: mailQ.length + mailActive, mailDrainS: mailDoneAt ? Math.max(0, Math.round((mailDoneAt - endAt) / 1000)) : 0, mailQuotaHit,
      memoryNeedMB: Math.round(busyPeak * I.memPerWorkerMB), ramMB: I.ramMB, durationS: Math.round(now / 1000), peakRps: series.reduce((a, s) => Math.max(a, s.rps), 0) };
    for (const k in histAll) summary.types[k] = { n: histAll[k].n, p50: histAll[k].q(.5), p95: histAll[k].q(.95), p99: histAll[k].q(.99), max: Math.round(histAll[k].max), errors: types[k].err };
    const hot = ['poll_quiz', 'submit', 'finish', 'signup'].filter(k => summary.types[k]);
    summary.p99 = hot.length ? Math.max(...hot.map(k => summary.types[k].p99)) : 0;
    summary.p50 = hot.length ? Math.max(...hot.map(k => summary.types[k].p50)) : 0;
    summary.verdict = verdict(summary, cfg);
    return { cfg, summary, series, snaps, flows, milestones, snapshotMs: cfg.snapshotMs };
  }

  /* ───────────── verdict ───────────── */
  function verdict(s, cfg) {
    const bad = s.lockedOut > 0 || s.errorRate > 0.005, slow = s.p99 > 1000;
    let level = 'ok', title = 'Holds comfortably';
    if (bad && (s.lockedOut > s.users * 0.05 || s.errorRate > 0.05)) { level = 'fail'; title = 'Breaks down'; }
    else if (bad || slow) { level = 'warn'; title = bad ? 'Degrades' : 'Holds, but slowly'; }
    else if (s.p99 > 300 || s.peakWorkers >= s.workers * 0.8) { level = 'tight'; title = 'Holds, with little headroom'; }
    return { level, title };
  }

  /* ───────────── findings: why, and what to change ───────────── */
  function findings(res) {
    const s = res.summary, c = res.cfg, I = c.infra, out = [];
    const sat = res.milestones.saturated, legacy = c.code.profile === 'legacy';
    if (s.lockedOut > 0 || (s.errorsBy['page:timeout'] || s.errorsBy['register:timeout'])) {
      if (sat && I.keepAlive && I.keepAliveTimeoutS >= 2 && c.scenario === 'live_exam') {
        out.push({ sev: 'high', key: 'keepalive', title: 'Idle keep-alive connections are using up the worker pool', body: `Each student polls every ${c.exam.pollMs / 1000} s, shorter than the ${I.keepAliveTimeoutS} s keep-alive, so every connected browser holds one of the ${I.workers} Apache workers permanently. From student ${I.workers + 1} on, nobody can get a worker. ${s.lockedOut} people were locked out.`, fix: [{ path: 'infra.keepAliveTimeoutS', value: 1, label: 'Keep-alive 1 s' }, { path: 'infra.workers', value: Math.max(600, I.workers), label: 'Allow 600 workers' }] });
      } else if (sat) {
        out.push({ sev: 'high', key: 'workers', title: 'The Apache worker pool is full', body: `All ${I.workers} workers were busy at the same time and requests queued until they timed out.`, fix: [{ path: 'infra.workers', value: I.workers * 3, label: `Allow ${I.workers * 3} workers` }] });
      }
    }
    if (s.errorsBy['poll_quiz:db_max'] || s.errorsBy['finish:db_max'] || s.errorsBy['page:db_max'] || res.milestones.db_refused) {
      out.push({ sev: 'high', key: 'dbconn', title: 'The database ran out of connections', body: `MariaDB allows ${I.dbMaxConnections} connections and the peak was ${s.peakDb}. Beyond the limit, visitors see "too many connections".`, fix: [{ path: 'infra.dbMaxConnections', value: Math.max(800, I.dbMaxConnections), label: 'Allow 800 connections' }] });
    }
    if (legacy && c.scenario === 'live_exam') {
      out.push({ sev: 'mid', key: 'legacy', title: 'The older request path is still in use', body: 'Every request pays about 30 extra SQL statements (schema checks and failing ALTER TABLE) and each student sends their own results email during the final request.', fix: [{ path: 'code.profile', value: 'optimized', label: 'Use the optimized code path' }] });
    }
    if (!I.opcache && s.peakCpu > 0.6) {
      out.push({ sev: 'mid', key: 'opcache', title: 'PHP opcache is off', body: 'Without opcache every request recompiles the PHP files. Turning it on cuts CPU per request by roughly half.', fix: [{ path: 'infra.opcache', value: true, label: 'Enable opcache' }] });
    }
    if (s.peakCpu > 0.92 && s.p99 > 400) {
      out.push({ sev: 'high', key: 'cpu', title: 'The CPU is the limit', body: `All ${I.cores} cores were saturated (${Math.round(s.peakCpu * 100)}%) and requests waited for a core. More workers will not help; more cores or fewer expensive requests will.`, fix: [{ path: 'infra.cores', value: I.cores * 2, label: `Double the cores (${I.cores * 2})` }] });
    }
    if (c.scenario === 'signup_wave' && !c.code.signupMailQueued && s.types.signup && s.types.signup.p50 > 2000) {
      out.push({ sev: 'high', key: 'signupmail', title: 'Sign-up waits for two emails to be sent', body: `The sign-up request sends the confirmation and welcome emails before answering (about ${Math.round(c.mail.smtpMs * 2 / 100) / 10} s). Each sign-up keeps a worker and a database connection for that whole time. Median sign-up took ${(s.types.signup.p50 / 1000).toFixed(1)} s.`, fix: [{ path: 'code.signupMailQueued', value: true, label: 'Queue sign-up emails' }] });
    }
    if (c.scenario === 'live_exam' && (s.mailBacklogEnd > 0 || s.mailQuotaHit || s.mailFailed > 0)) {
      out.push({ sev: s.mailQuotaHit ? 'high' : 'mid', key: 'mail', title: s.mailQuotaHit ? 'The email provider daily limit is reached' : 'Results emails take a while to go out', body: s.mailQuotaHit ? `Only ${s.mailSent} of ${s.users} result emails could be sent before the ${c.mail.dailyQuota}-per-day limit. A transactional email service removes this ceiling.` : `${s.mailSent} emails were sent, ${s.mailBacklogEnd} still waiting. Draining takes about ${s.mailDrainS} s after the exam with ${c.mail.workers} sending workers; students are not affected.`, fix: s.mailQuotaHit ? [] : [{ path: 'mail.workers', value: c.mail.workers * 2, label: `${c.mail.workers * 2} mail workers` }] });
    }
    if (s.memoryNeedMB > s.ramMB * 0.85) {
      out.push({ sev: 'high', key: 'ram', title: 'Not enough memory for that many workers', body: `${s.peakWorkers} workers at about ${c.infra.memPerWorkerMB} MB need ${s.memoryNeedMB} MB, the machine has ${s.ramMB} MB. The server would start swapping and slow to a crawl.`, fix: [] });
    }
    if (!out.length) out.push({ sev: 'ok', key: 'fine', title: 'Nothing is close to a limit', body: `Workers peaked at ${s.peakWorkers} of ${I.workers}, database connections at ${s.peakDb} of ${I.dbMaxConnections}, CPU at ${Math.round(s.peakCpu * 100)}%. The slowest 1% of requests took ${s.p99} ms.`, fix: [] });
    return out;
  }

  /* ───────────── capacity sweep ───────────── */
  function sweep(baseCfg, steps) {
    const rows = [];
    for (const n of steps) {
      const r = simulate(merge(baseCfg, { users: n, detail: false }));
      const s = r.summary;
      rows.push({ users: n, p99: s.p99, p50: s.p50, errorRate: s.errorRate, lockedOut: s.lockedOut, peakWorkers: s.peakWorkers, peakDb: s.peakDb, peakCpu: s.peakCpu, level: s.verdict.level });
    }
    let safe = 0; for (const r of rows) { if (r.level === 'ok' || r.level === 'tight') safe = r.users; else break; }
    return { rows, maxSafeUsers: safe };
  }

  return { simulate, findings, sweep, DEFAULTS, COST, merge };
});
