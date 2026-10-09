// Live-evaluation load test. Usage: node tests/load/live-eval-load.js --users 300 --q 10 --t 15 --join 30 --tag mytest
// Never point this at a database with real data: it deletes live_eval_sessions. See tests/load/README.md.
const http = require('http'); const { execSync } = require('child_process'); const fs = require('fs');
const arg = (k, d) => { const i = process.argv.indexOf('--' + k); return i > 0 ? process.argv[i + 1] : d; };
const N = +arg('users', 100), Q = +arg('q', 10), T = +arg('t', 15), JOIN = +arg('join', 20), TAG = arg('tag', 'run');
const POLL_Q = +arg('pollq', 2000), POLL_L = +arg('polll', 2500), KA = arg('keepalive', '1') === '1', HOST = process.env.LT_HOST || '127.0.0.1', PORT = +(process.env.LT_PORT || 8088);
const MYSQL = process.env.LT_MYSQL || 'mysql -h127.0.0.1 -P3399 -uroot svload';   // the TEST database only
const sql = (s) => execSync(`${MYSQL} -N -e "${s.replace(/"/g, '\\"')}"`).toString().trim();

// ── seed a fresh session ──
const CODE = 'LT' + Date.now().toString(36);
sql('delete from live_eval_sessions');
const lead = JOIN + 15;
sql(`insert into live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,status,default_time_limit) values (1,1,'Load test','${CODE}', now() + interval ${lead} second, now() + interval ${lead + Q * T + 60} second, 1, ${T})`);
const SID = +sql(`select id from live_eval_sessions where session_code='${CODE}'`);
for (let i = 1; i <= Q; i++) sql(`insert into live_eval_questions (session_id,question_text,option_a,option_b,option_c,option_d,correct_option,sort_order,time_limit) values (${SID},'Question ${i} : combien font 2+2 ? \\\\(x^2\\\\)','4','3','5','6','A',${i},${T})`);
const START = Date.now() + lead * 1000;

// ── metrics ──
const lat = {}; const errs = {}; const timeline = {}; let total = 0;
const rec = (type, ms, ok, why) => {
  total++; (lat[type] ||= []).push(ms);
  const sec = Math.floor((Date.now() - T0) / 5000) * 5; const b = (timeline[sec] ||= { n: 0, ms: 0, e: 0, max: 0 }); b.n++; b.ms += ms; b.max = Math.max(b.max, ms); if (!ok) b.e++;
  if (!ok) { const k = type + ':' + why; errs[k] = (errs[k] || 0) + 1; }
};
const pct = (a, p) => { if (!a.length) return 0; const s = [...a].sort((x, y) => x - y); return s[Math.min(s.length - 1, Math.floor(s.length * p))]; };
const T0 = Date.now();

function user(i) {
  const agent = new http.Agent({ keepAlive: KA, maxSockets: 1 });
  let cookie = ''; const email = `lt${i}@gmail.com`; let stopped = false, finished = false, lastQ = 0;
  const req = (type, method, path, body) => new Promise((resolve) => {
    const t = Date.now();
    const headers = { Cookie: cookie, 'Accept-Encoding': 'identity' };
    if (body) { headers['Content-Type'] = 'application/x-www-form-urlencoded'; headers['Content-Length'] = Buffer.byteLength(body); }
    const r = http.request({ host: HOST, port: PORT, path, method, agent, headers, timeout: 20000 }, (res) => {
      const sc = res.headers['set-cookie']; if (sc) cookie = sc.map(c => c.split(';')[0]).join('; ') || cookie;
      const chunks = []; res.on('data', c => chunks.push(c)); res.on('end', () => {
        const ms = Date.now() - t; const txt = Buffer.concat(chunks).toString(); let ok = res.statusCode < 400, why = res.statusCode >= 400 ? 'http' + res.statusCode : '', json = null;
        if (path.startsWith('/api/')) { try { json = JSON.parse(txt); if (json.success === false && !json.not_registered) { ok = false; why = 'api:' + (json.message || '').slice(0, 40); } else if (json.not_registered) { ok = false; why = 'not_registered'; } } catch (e) { ok = false; why = 'badjson'; } }
        rec(type, ms, ok, why); resolve({ status: res.statusCode, txt, json, loc: res.headers.location });
      });
    });
    r.on('timeout', () => { r.destroy(); rec(type, Date.now() - t, false, 'timeout'); resolve({ status: 0 }); });
    r.on('error', (e) => { rec(type, Date.now() - t, false, 'net:' + (e.code || e.message)); resolve({ status: 0 }); });
    if (body) r.write(body); r.end();
  });
  const sleep = (ms) => new Promise(r => setTimeout(r, ms));
  return (async () => {
    await sleep(Math.random() * JOIN * 1000);
    await req('page', 'GET', `/live-session.php?code=${CODE}`);                          // opens the invitation page
    const reg = await req('register', 'POST', `/live-session.php?code=${CODE}`, `register_live=1&auth_action=guest&email=${encodeURIComponent(email)}&name=${encodeURIComponent('Etudiant ' + i)}`);
    await req('page', 'GET', `/live-session.php?code=${CODE}`);                          // lands in the lobby
    // lobby until start
    while (Date.now() < START - 200 && !stopped) { await req('poll_lobby', 'GET', `/api/live-eval-poll.php?code=${CODE}&action=poll_lobby`); await sleep(POLL_L); }
    const endAt = START + Q * T * 1000 + 40000;
    while (!finished && Date.now() < endAt) {
      const r = await req('poll_quiz', 'GET', `/api/live-eval-poll.php?code=${CODE}&action=poll_quiz`);
      const j = r.json;
      if (j && j.status === 'finished') { finished = true; rec('finished', 0, true, ''); break; }
      if (j && j.status === 'active' && j.question && !j.already_answered && j.question.id !== lastQ) {
        lastQ = j.question.id; const qid = j.question.id; const wait = (1500 + Math.random() * Math.max(500, (j.question.seconds_left - 4) * 1000));
        setTimeout(() => { req('submit_answer', 'POST', `/api/live-eval-poll.php?code=${CODE}`, `action=submit_answer&question_id=${qid}&selected_option=${Math.random() < .7 ? 'A' : 'B'}`); }, wait);
      }
      await sleep(POLL_Q);
    }
    agent.destroy();
    return finished;
  })();
}

// ── server-side sampler ──
const samples = []; let sampling = true;
(async () => {
  while (sampling) {
    try {
      const st = await new Promise((res) => http.get({ host: HOST, port: PORT, path: '/server-status?auto', timeout: 3000, agent: false }, (r) => { let d = ''; r.on('data', c => d += c); r.on('end', () => res(d)); }).on('error', () => res('')));
      const busy = +(/BusyWorkers: (\d+)/.exec(st) || [0, -1])[1], idle = +(/IdleWorkers: (\d+)/.exec(st) || [0, -1])[1];
      const g = sql("show global status where Variable_name in ('Threads_connected','Threads_running','Max_used_connections','Aborted_connects','Connection_errors_max_connections')").split('\n').reduce((o, l) => { const [k, v] = l.split('\t'); o[k] = +v; return o; }, {});
      const load = fs.readFileSync('/proc/loadavg', 'utf8').split(' ')[0];
      samples.push({ t: Math.round((Date.now() - T0) / 1000), busy, idle, thr: g.Threads_connected, run: g.Threads_running, max: g.Max_used_connections, maxerr: g.Connection_errors_max_connections || 0, load: +load });
    } catch (e) {}
    await new Promise(r => setTimeout(r, 3000));
  }
})();

(async () => {
  console.log(`[${TAG}] ${N} users, ${Q} questions x ${T}s, join over ${JOIN}s, session ${CODE}, keepalive=${KA}`);
  const results = await Promise.all(Array.from({ length: N }, (_, i) => user(i + 1)));
  sampling = false; await new Promise(r => setTimeout(r, 500));
  await new Promise(r => setTimeout(r, 12000)); // let background mail finish
  const done = results.filter(Boolean).length;
  const scored = +sql(`select count(*) from live_eval_registrations where session_id=${SID} and score is not null`);
  const regs = +sql(`select count(*) from live_eval_registrations where session_id=${SID}`);
  const ans = +sql(`select count(*) from live_eval_answers a join live_eval_registrations r on r.id=a.registration_id where r.session_id=${SID}`);
  const out = { tag: TAG, users: N, registered: regs, finished_ok: done, scored, answers: ans, expected_answers_max: N * Q, total_requests: total, wall_s: Math.round((Date.now() - T0) / 1000), types: {}, errors: errs,
    peak_busy_workers: Math.max(...samples.map(s => s.busy)), peak_db_connections: Math.max(...samples.map(s => s.thr)), peak_db_running: Math.max(...samples.map(s => s.run)), db_max_used: samples.length ? samples[samples.length - 1].max : 0, db_conn_refused: samples.length ? samples[samples.length - 1].maxerr : 0, peak_load1: Math.max(...samples.map(s => s.load)) };
  for (const [k, a] of Object.entries(lat)) out.types[k] = { n: a.length, p50: pct(a, .5), p95: pct(a, .95), p99: pct(a, .99), max: Math.max(...a) };
  out.timeline = Object.entries(timeline).map(([s, b]) => ({ t: +s, rps: +(b.n / 5).toFixed(1), avg: Math.round(b.ms / b.n), max: b.max, err: b.e }));
  out.samples = samples;
  const OUT = process.env.LT_OUT || '.'; fs.mkdirSync(OUT, { recursive: true }); fs.writeFileSync(`${OUT}/result-${TAG}.json`, JSON.stringify(out));
  const t = out.types; const e = Object.values(errs).reduce((a, b) => a + b, 0);
  console.log(`\n=== ${TAG} ===`);
  console.log(`registered ${regs}/${N} | finished ${done}/${N} | scored ${scored}/${N} | answers ${ans} | requests ${total} | errors ${e} (${(e / total * 100).toFixed(2)}%)`);
  for (const k of ['page', 'register', 'poll_lobby', 'poll_quiz', 'submit_answer']) if (t[k]) console.log(k.padEnd(14), `n=${String(t[k].n).padEnd(6)} p50=${t[k].p50}ms p95=${t[k].p95}ms p99=${t[k].p99}ms max=${t[k].max}ms`);
  console.log(`peak: apache busy workers ${out.peak_busy_workers} | db connections ${out.peak_db_connections} (max used ${out.db_max_used}, refused ${out.db_conn_refused}) | db running ${out.peak_db_running} | load1 ${out.peak_load1}`);
  if (e) console.log('errors:', JSON.stringify(errs));
  process.exit(0);
})();
