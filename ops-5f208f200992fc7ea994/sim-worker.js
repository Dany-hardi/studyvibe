/* Runs the simulator off the main thread, so the page stays responsive while thousands of virtual people are simulated. */
importScripts('sim.js');
self.onmessage = (e) => {
  const { id, type, cfg, steps } = e.data;
  try {
    if (type === 'run') { const r = StudySim.simulate(cfg); r.findings = StudySim.findings(r); self.postMessage({ id, ok: true, result: r }); }
    else if (type === 'sweep') { self.postMessage({ id, ok: true, result: StudySim.sweep(cfg, steps) }); }
  } catch (err) { self.postMessage({ id, ok: false, error: String(err && err.stack || err) }); }
};
