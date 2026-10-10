/* Free evaluations page: filters, search, sort, live countdowns (on the server's clock) and the "before you start" sheet. */
(function () {
  var D = window.EV; if (!D) return;
  var T = D.T, byId = function (i) { return document.getElementById(i); };
  var grid = byId('ev-grid'); if (!grid) return;
  var offset = D.now - Math.floor(Date.now() / 1000);          // server time minus browser time, so a wrong clock changes nothing
  var nowS = function () { return Math.floor(Date.now() / 1000) + offset; };
  var cards = [].slice.call(grid.querySelectorAll('.ev-card'));
  var data = {}; D.cards.forEach(function (c) { data[c.id] = c; });
  var filter = 'all';

  function fmt(sec) {
    var d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60, p = function (n) { return String(n).padStart(2, '0'); };
    return (d ? d + (D.lang === 'en' ? 'd ' : 'j ') : '') + p(h) + 'h ' + p(m) + 'm ' + p(s) + 's';
  }
  function tick() {
    var n = nowS();
    cards.forEach(function (card) {
      var dl = parseInt(card.dataset.deadline, 10), t = card.querySelector('.ev-time');
      if (!dl || !t) return;
      var diff = dl - n;
      if (diff <= 0) { card.classList.add('is-expired'); t.className = 'ev-time is-late'; t.textContent = T.expired; var g = card.querySelector('.ev-go'); if (g) g.style.display = 'none'; return; }
      var s = t.querySelector('strong'); if (s) s.textContent = fmt(diff);
      t.classList.toggle('is-soon', diff <= 72 * 3600);
    });
  }
  setInterval(tick, 1000); tick();

  function apply() {
    var q = byId('ev-q').value.toLowerCase().trim(), n = 0;
    cards.forEach(function (c) {
      var ok = (!q || c.dataset.text.indexOf(q) > -1);
      if (ok && filter === 'todo') ok = c.dataset.state !== 'done';
      if (ok && filter === 'done') ok = c.dataset.state === 'done';
      if (ok && filter === 'soon') ok = c.dataset.soon === '1';
      if (ok && filter === 'new') ok = c.dataset.new === '1';
      c.hidden = !ok; if (ok) n++;
    });
    byId('ev-visible').textContent = n;
    byId('ev-none').hidden = n !== 0; grid.hidden = n === 0;
  }
  function sort() {
    var how = byId('ev-sort').value;
    cards.sort(function (a, b) {
      if (how === 'new') return parseInt(b.dataset.created, 10) - parseInt(a.dataset.created, 10);
      if (how === 'az') return a.dataset.course.localeCompare(b.dataset.course) || a.dataset.text.localeCompare(b.dataset.text);
      var da = parseInt(a.dataset.deadline, 10) || 9e12, db = parseInt(b.dataset.deadline, 10) || 9e12;   // not finished first, then by closing time
      var fa = a.dataset.state === 'done' ? 1 : 0, fb = b.dataset.state === 'done' ? 1 : 0;
      return fa - fb || da - db;
    });
    cards.forEach(function (c) { grid.appendChild(c); });
  }
  document.querySelectorAll('.ev-chip').forEach(function (b) {
    b.addEventListener('click', function () { filter = b.dataset.f; document.querySelectorAll('.ev-chip').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); apply(); });
  });
  byId('ev-q').addEventListener('input', apply);
  byId('ev-sort').addEventListener('change', sort);
  byId('ev-reset').addEventListener('click', function () { byId('ev-q').value = ''; filter = 'all'; document.querySelectorAll('.ev-chip').forEach(function (x) { x.setAttribute('aria-pressed', x.dataset.f === 'all' ? 'true' : 'false'); }); apply(); });
  sort(); apply();

  /* sheet */
  var sheet = byId('ev-sheet'), panel = sheet.querySelector('.ev-sheet-panel'), lastFocus = null;
  function li(html, warn) { var l = document.createElement('li'); if (warn) l.className = 'warn'; l.innerHTML = html; return l; }
  function openSheet(id, fromHash) {
    var c = data[id]; if (!c) return;
    lastFocus = document.activeElement;
    sheet.style.setProperty('--sc', c.color);
    byId('sh-art').src = c.art; byId('sh-theme').textContent = c.theme; byId('sh-theme').style.setProperty('--c', c.color);
    byId('sh-course').textContent = c.course; byId('ev-sheet-title').textContent = c.title;
    byId('sh-q').textContent = c.nq; byId('sh-t').textContent = c.tl + ' s'; byId('sh-total').textContent = '≈ ' + Math.max(1, c.total) + ' ' + T.min;
    byId('sh-dead').textContent = c.deadline ? new Date(c.deadline * 1000).toLocaleString(D.lang === 'en' ? 'en-GB' : 'fr-FR', { dateStyle: 'medium', timeStyle: 'short' }) : T.none;
    var st = byId('sh-status'); if (st) st.textContent = c.state === 'done' ? T.b_done + ' · ' + Math.round(c.score) + ' %' : (c.state === 'inprog' ? T.b_inprog : T.b_todo);
    var r = byId('sh-rules'); r.innerHTML = '';
    r.appendChild(li(T.r_one)); r.appendChild(li(T.r_nonneg)); r.appendChild(li(T.r_score));
    if (c.shuffle) r.appendChild(li(T.r_shuffle));
    if (c.integrity) r.appendChild(li(T.r_integrity, true));
    r.appendChild(li(T.r_net));
    r.appendChild(c.state === 'done' ? li(T.r_retry_done.replace(':s', Math.round(c.score)), true) : li(T.r_retry));
    var go = byId('sh-go'); go.href = c.url; go.textContent = c.state === 'todo' ? T.start : T.retry;
    var expired = c.deadline && c.deadline <= nowS(); go.style.display = expired ? 'none' : '';
    sheet.hidden = false; document.body.classList.add('ev-lock'); panel.scrollTop = 0; panel.focus();
    if (!fromHash) { try { history.replaceState(null, '', '#eval-' + id); } catch (e) {} }
  }
  function closeSheet() {
    if (sheet.hidden) return;
    sheet.hidden = true; document.body.classList.remove('ev-lock');
    try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  grid.addEventListener('click', function (e) { var b = e.target.closest('[data-open]'); if (b) openSheet(parseInt(b.dataset.open, 10)); });
  sheet.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) closeSheet(); });
  document.addEventListener('keydown', function (e) {
    if (sheet.hidden) return;
    if (e.key === 'Escape') { closeSheet(); return; }
    if (e.key === 'Tab') {                                   // keep the focus inside the sheet
      var f = [].slice.call(panel.querySelectorAll('a[href], button')).filter(function (x) { return x.offsetParent !== null; });
      if (!f.length) return; var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && (document.activeElement === first || document.activeElement === panel)) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  var m = /^#eval-(\d+)$/.exec(location.hash || ''); if (m) openSheet(parseInt(m[1], 10), true);
})();
