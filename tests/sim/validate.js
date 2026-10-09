// Compares the simulator with the real load-test runs listed in docs/PERFORMANCE.md.  node tests/sim/validate.js
const fs = require('fs'), path = require('path');
const dir = fs.readdirSync(path.join(__dirname, '../..')).find(d => /^ops-[0-9a-f]{20}$/.test(d));
const S = require(path.join(__dirname, '../..', dir, 'sim.js'));

const base = { net: { rttMs: 0, rttJitter: 0 }, infra: { cores: 8, dbMaxConnections: 151, flushLog: 1, dbOnSameHost: true, ramMB: 31000 }, mail: { smtpMs: 1700, workers: 4, dailyQuota: 100000 } };
const legacy = { profile: 'legacy', mailQueued: false, signupMailQueued: false }, opt = { profile: 'optimized', mailQueued: true, signupMailQueued: false };
// measured = what the real run reported. polite = the early test client that closes idle sockets (so keep-alive pinning could not occur).
const RUNS = [
  { id: 'A', note: 'original code, stock Apache (150 workers, keep-alive 5 s), q10 t15', users: 300, exam: { questions: 10, secondsPerQuestion: 15, joinWindowS: 30 }, infra: { workers: 150, keepAlive: true, keepAliveTimeoutS: 5, opcache: false }, code: legacy,
    measured: { lockedOut: 150, errorRate: 0.256, p99: 20000, peakWorkers: 150, peakDb: 90 } },
  { id: 'B', note: 'original code, keep-alive off, q6 t12', users: 300, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 30 }, infra: { workers: 150, keepAlive: false, opcache: false }, code: legacy,
    measured: { lockedOut: 0, errorRate: 0, p99: 10309, peakWorkers: 150, peakDb: 150 } },
  { id: 'C', note: 'new code, keep-alive off, 150 workers', users: 300, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 30 }, infra: { workers: 150, keepAlive: false, opcache: false }, code: opt,
    measured: { lockedOut: 0, errorRate: 0, p99: 53, peakWorkers: 8, peakDb: 23 } },
  { id: 'D', note: 'new code, stock Apache (keep-alive 5 s, 150 workers)', users: 300, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 30 }, infra: { workers: 150, keepAlive: true, keepAliveTimeoutS: 5, opcache: false }, code: opt,
    measured: { lockedOut: 150, errorRate: 0.309, p99: 20000, peakWorkers: 150, peakDb: 25 } },
  { id: 'E', note: 'new code, keep-alive 1 s, 600 workers, browser-like client', users: 300, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 30 }, infra: { workers: 600, keepAlive: true, keepAliveTimeoutS: 1, opcache: true, dbMaxConnections: 800, flushLog: 2 }, code: opt,
    measured: { lockedOut: 0, errorRate: 0.0026, p99: 76, peakWorkers: 189, peakDb: 9 } },
  { id: 'F', note: 'new code, keep-alive off, 600 users (compute side)', users: 600, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 40 }, infra: { workers: 600, keepAlive: false, opcache: true, dbMaxConnections: 800, flushLog: 2 }, code: opt,
    measured: { lockedOut: 0, errorRate: 0, p99: 119, peakWorkers: 15, peakDb: 36 } },
  { id: 'H', note: 'new code, keep-alive off, 2000 users (compute side)', users: 2000, exam: { questions: 6, secondsPerQuestion: 12, joinWindowS: 90 }, infra: { workers: 600, keepAlive: false, opcache: true, dbMaxConnections: 800, flushLog: 2 }, code: opt,
    measured: { lockedOut: 0, errorRate: 0, p99: 448, peakWorkers: 121, peakDb: 103 } },
];
const within = (sim, real, f) => real === 0 ? sim <= Math.max(2, f) : (sim / real <= f && real / Math.max(sim, 1e-9) <= f);
let pass = 0, total = 0;
console.log('run | metric            |   real |    sim | verdict');
for (const r of RUNS) {
  const cfg = S.merge(base, { users: r.users, exam: r.exam, infra: r.infra, code: r.code });
  const t = Date.now(); const out = S.simulate(S.merge(cfg, { detail: false })); const s = out.summary;
  const locked = r.measured.lockedOut > 0;
  // With lockouts the real test client kept polling after failing to register, which inflates its error rate and p99, so those two are only compared on runs where everybody got in.
  const rows = [
    ['locked out', r.measured.lockedOut, s.lockedOut, (a, b) => b === 0 ? a <= 5 : Math.abs(a - b) <= b * 0.35],
    ...(locked ? [] : [['error rate %', +(r.measured.errorRate * 100).toFixed(1), +(s.errorRate * 100).toFixed(1), (a, b) => Math.abs(a - b) <= Math.max(1, b * 0.4)],
                       ['p99 latency ms', r.measured.p99, s.p99, (a, b) => within(a, b, 4)]]),
    ['peak workers', r.measured.peakWorkers, s.peakWorkers, (a, b) => within(a, b, 2.2)],
    ['peak db conns', r.measured.peakDb, s.peakDb, (a, b) => within(a, b, 2.5)],
  ];
  for (const [name, real, sim, ok] of rows) { const good = ok(sim, real); total++; if (good) pass++; console.log(`${r.id.padEnd(3)} | ${name.padEnd(17)} | ${String(real).padStart(6)} | ${String(sim).padStart(6)} | ${good ? 'ok' : 'OFF'}`); }
  console.log(`    (${r.note}; simulated in ${Date.now() - t} ms)`);
}
console.log(`\n${pass}/${total} checks within tolerance`);
process.exitCode = pass / total >= 0.7 ? 0 : 1;
