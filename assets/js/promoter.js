/* Promoter console. Vanilla JS, no dependencies. Strings come from window.SV_T / SV_PM. */
(function () {
  'use strict';
  var T = window.SV_T || {}, P = window.SV_PM || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var tpl = function (s, v) { return String(s == null ? '' : s).replace(/\{(\w+)\}/g, function (_, k) { return v && v[k] != null ? v[k] : ''; }); };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
  var csrf = function () { var m = $('meta[name="csrf-token"]'); return m ? m.content : ''; };

  /* ---------- Toasts ---------- */
  function toast(msg, kind) {
    var box = $('#toasts'); if (!box || !msg) return;
    var el = document.createElement('div');
    el.className = 'toast' + (kind === 'err' ? ' err' : '');
    el.textContent = msg; box.appendChild(el);
    setTimeout(function () { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 320); }, kind === 'err' ? 7000 : 4200);
  }

  /* ---------- Network ---------- */
  function post(url, fd) {
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function netErr(e) { toast(T.net + (e && e.message ? e.message : ''), 'err'); }

  /* ---------- Theme + language ---------- */
  $$('[data-dark-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.documentElement.classList.toggle('dark');
      try { localStorage.setItem('sv_dark', d ? '1' : '0'); } catch (e) {}
    });
  });
  $$('[data-lang]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      fetch('/api/set-language.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ lang: a.dataset.lang }) })
        .then(function () { location.reload(); });
    });
  });

  /* ---------- Dialogs ---------- */
  function openDlg(id) { var d = typeof id === 'string' ? document.getElementById(id) : id; if (d && !d.open) d.showModal(); return d; }
  $$('dialog.dlg').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
    $$('[data-close]', d).forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
  });
  $$('[data-open]').forEach(function (b) { b.addEventListener('click', function () { openDlg(b.dataset.open); }); });

  /* Promise-based confirm. opts: title, body (html allowed via bodyHtml), label, danger, phrase */
  function confirmDlg(o) {
    return new Promise(function (resolve) {
      var d = $('#confirm-dlg'), yes = $('#cf-yes'), no = $('#cf-no'), ph = $('#cf-phrase'), pin = $('#cf-phrase-in');
      $('#cf-h').textContent = o.title;
      if (o.bodyHtml) $('#cf-p').innerHTML = o.bodyHtml; else $('#cf-p').textContent = o.body || '';
      yes.textContent = o.label || T.confirm;
      yes.className = 'btn ' + (o.danger ? 'btn-danger' : 'btn-primary');
      var need = o.phrase ? String(o.phrase).toLowerCase() : '';
      ph.hidden = !need; pin.value = '';
      if (need) $('#cf-phrase-hint').textContent = tpl(T.phrase_hint, { w: o.phrase });
      yes.disabled = !!need;
      var done = false;
      function finish(v) { if (done) return; done = true; cleanup(); if (d.open) d.close(); resolve(v); }
      function onYes() { finish(true); }
      function onNo() { finish(false); }
      function onIn() { yes.disabled = pin.value.trim().toLowerCase() !== need; }
      function onCancel(e) { e.preventDefault(); finish(false); }
      function onBackdrop(e) { if (e.target === d) finish(false); }
      function cleanup() { yes.removeEventListener('click', onYes); no.removeEventListener('click', onNo); pin.removeEventListener('input', onIn); d.removeEventListener('cancel', onCancel); d.removeEventListener('click', onBackdrop); }
      yes.addEventListener('click', onYes); no.addEventListener('click', onNo); pin.addEventListener('input', onIn);
      d.addEventListener('cancel', onCancel); d.addEventListener('click', onBackdrop);
      d.showModal();
      (need ? pin : no).focus();
    });
  }

  /* ---------- Panels ---------- */
  var panels = $$('[data-panel]'), links = $$('[data-tab]');
  var alias = { 'tab-community': 'tab-people' };
  function show(id, push) {
    id = alias[id] || id;
    if (!document.getElementById(id) || !document.getElementById(id).hasAttribute('data-panel')) id = 'tab-overview';
    panels.forEach(function (p) { p.hidden = p.id !== id; });
    links.forEach(function (a) { if (a.dataset.tab === id) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
    if (push !== false) { try { history.replaceState(null, '', '#' + id); } catch (e) {} }
    var act = $('.pm-rail [aria-current="page"]'); if (act && act.scrollIntoView) act.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    if (id === 'tab-api' && !keysLoaded) loadKeys();
  }
  links.forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); show(a.dataset.tab); window.scrollTo({ top: 0, behavior: 'instant' }); }); });
  $$('[data-goto]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.dataset.fstatus) { statusSel.value = b.dataset.fstatus; applyFilters(); }
      show(b.dataset.goto); window.scrollTo({ top: 0, behavior: 'instant' });
    });
  });
  window.addEventListener('hashchange', function () { show(location.hash.slice(1), false); });

  /* ---------- Row menus (keyboard usable) ---------- */
  var menuEl = null, menuBtn = null, menuAt = 0;
  function closeMenu(refocus) {
    if (!menuEl) return;
    menuEl.remove(); menuEl = null;
    if (menuBtn) { menuBtn.setAttribute('aria-expanded', 'false'); if (refocus) menuBtn.focus(); }
    menuBtn = null;
  }
  function openMenu(btn) {
    closeMenu(false);
    var acts = (btn.dataset.acts || '').split(',').filter(Boolean);
    if (!acts.length) return;
    var m = document.createElement('div');
    m.className = 'menu'; m.setAttribute('role', 'menu'); m.setAttribute('aria-label', btn.getAttribute('aria-label'));
    acts.forEach(function (a, i) {
      if (a === 'delete' || a === 'revoke_cert' || a === 'cancel_session') {
        if (i > 0) m.appendChild(document.createElement('hr'));
      }
      var it = document.createElement('button');
      it.type = 'button'; it.setAttribute('role', 'menuitem'); it.tabIndex = -1;
      it.dataset.act = a;
      if (a === 'delete' || a === 'revoke_cert' || a === 'cancel_session') it.className = 'danger';
      it.textContent = T['m_' + a] || a;
      m.appendChild(it);
    });
    document.body.appendChild(m);
    var r = btn.getBoundingClientRect(), mw = m.offsetWidth, mh = m.offsetHeight;
    var left = Math.max(8, Math.min(window.innerWidth - mw - 8, r.right - mw));
    var top = r.bottom + 4; if (top + mh > window.innerHeight - 8) top = Math.max(8, r.top - mh - 4);
    m.style.left = left + 'px'; m.style.top = top + 'px';
    btn.setAttribute('aria-expanded', 'true'); menuEl = m; menuBtn = btn; menuAt = Date.now();
    var items = $$('button', m); items[0].focus();
    m.addEventListener('keydown', function (e) {
      var i = items.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % items.length].focus(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + items.length) % items.length].focus(); }
      else if (e.key === 'Home') { e.preventDefault(); items[0].focus(); }
      else if (e.key === 'End') { e.preventDefault(); items[items.length - 1].focus(); }
      else if (e.key === 'Escape') { e.preventDefault(); closeMenu(true); }
      else if (e.key === 'Tab') { closeMenu(false); }
    });
    m.addEventListener('click', function (e) {
      var it = e.target.closest('button[data-act]'); if (!it) return;
      var b = btn, act = it.dataset.act; closeMenu(false); b.focus();
      runMenuAction(b, act);
    });
  }
  document.addEventListener('click', function (e) {
    var k = e.target.closest('.kebab');
    if (k) { e.preventDefault(); if (menuBtn === k) closeMenu(false); else openMenu(k); return; }
    if (menuEl && !e.target.closest('.menu')) closeMenu(false);
  });
  document.addEventListener('keydown', function (e) {
    var k = e.target.closest && e.target.closest('.kebab');
    if (k && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { e.preventDefault(); openMenu(k); }
  });
  window.addEventListener('resize', function () { closeMenu(false); });
  window.addEventListener('scroll', function (e) { if (Date.now() - menuAt > 400 && !(e.target.closest && e.target.closest('.menu'))) closeMenu(false); }, true);

  function runMenuAction(b, act) {
    var scope = b.dataset.menu, d = b.dataset;
    if (scope === 'user') return userAction(+d.id, act, d.name);
    if (scope === 'cert') {
      if (act === 'open') window.open('/certificate.php?code=' + encodeURIComponent(d.code), '_blank', 'noopener');
      else if (act === 'copy') copyText(d.code).then(function () { toast(T.copied); });
      else if (act === 'revoke_cert') {
        confirmDlg({ title: T.revcert_t, body: tpl(T.revcert_p, { code: d.code, name: d.name }), label: T.revcert_b, danger: true }).then(function (ok) {
          if (!ok) return; var f = $('#remove-cert-form'); f.certificate_id.value = d.id; f.submit();
        });
      }
    }
    if (scope === 'session') {
      if (act === 'postpone') {
        $('#pp-id').value = d.id; $('#pp-name').textContent = d.name; $('#pp-start').value = d.start; $('#pp-end').value = d.end; openDlg('postpone-modal');
      } else if (act === 'cancel_session') {
        confirmDlg({ title: T.cancels_t, body: tpl(T.cancels_p, { name: d.name }), label: T.cancels_b, danger: true }).then(function (ok) {
          if (!ok) return; var f = $('#cancel-session-form'); f.session_id.value = d.id; f.submit();
        });
      }
    }
  }

  function copyText(txt) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(txt);
    return new Promise(function (res) {
      var ta = document.createElement('textarea'); ta.value = txt; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) {} ta.remove(); res();
    });
  }

  /* ---------- People: filter, selection, bulk ---------- */
  var rows = $$('#pe-body tr'), qIn = $('#pe-q'), statusSel = $('#pe-status'), roleBtns = $$('#pe-roles .pill');
  var role = 'all', shown = P.perPage || 50, selected = {};
  function matches(tr) {
    if (role !== 'all' && tr.dataset.role !== role) return false;
    var st = statusSel.value; if (st !== 'all' && (' ' + tr.dataset.st + ' ').indexOf(' ' + st + ' ') < 0) return false;
    var q = qIn.value.trim().toLowerCase(); if (q && tr.dataset.q.indexOf(q) < 0) return false;
    return true;
  }
  function applyFilters(reset) {
    if (reset !== false) shown = P.perPage || 50;
    var n = 0, total = 0;
    rows.forEach(function (tr) {
      var ok = matches(tr); if (ok) total++;
      var vis = ok && n < shown; if (vis) n++;
      tr.hidden = !vis;
    });
    $('#pe-shown').textContent = n;
    $('#pe-empty').hidden = total > 0;
    $('#pe-more').hidden = total <= n;
    syncSelection();
  }
  qIn.addEventListener('input', function () { applyFilters(); });
  statusSel.addEventListener('change', function () { applyFilters(); });
  roleBtns.forEach(function (b) { b.addEventListener('click', function () { role = b.dataset.role; roleBtns.forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); applyFilters(); }); });
  $('#pe-more').addEventListener('click', function () { shown += (P.perPage || 50); applyFilters(false); });

  function selectedIds() { return Object.keys(selected).filter(function (k) { return selected[k]; }).map(Number); }
  function syncSelection() {
    // drop selections that are no longer visible so bulk actions never touch hidden rows
    $$('.pe-chk').forEach(function (c) { var tr = c.closest('tr'); if (tr.hidden) { c.checked = false; selected[c.value] = false; } tr.classList.toggle('is-selected', c.checked); });
    var ids = selectedIds(), bar = $('#pe-bulk');
    bar.hidden = ids.length === 0; $('#pe-bulk-n').textContent = ids.length;
    var vis = $$('.pe-chk').filter(function (c) { return !c.closest('tr').hidden; });
    var all = $('#pe-all'); all.checked = vis.length > 0 && vis.every(function (c) { return c.checked; });
    all.indeterminate = !all.checked && vis.some(function (c) { return c.checked; });
  }
  $('#pe-body').addEventListener('change', function (e) { if (e.target.classList.contains('pe-chk')) { selected[e.target.value] = e.target.checked; syncSelection(); } });
  $('#pe-all').addEventListener('change', function (e) {
    $$('.pe-chk').forEach(function (c) { if (!c.closest('tr').hidden) { c.checked = e.target.checked; selected[c.value] = c.checked; } }); syncSelection();
  });
  $('#pe-bulk-clear').addEventListener('click', function () { $$('.pe-chk').forEach(function (c) { c.checked = false; }); selected = {}; syncSelection(); });

  function manage(id, action) {
    var fd = new FormData(); fd.append('user_id', id); fd.append('action', action);
    return post('/promoter/manage-user.php', fd);
  }
  function userAction(id, act, name) {
    if (act === 'contact') { $('#dm-student-id').value = id; $('#dm-student-name').value = name; openDlg('direct-message-modal'); return; }
    var map = { approve: 'approve', suspend: 'toggle_active', reactivate: 'toggle_active', delete: 'delete' };
    var o = { title: tpl(T[act + '_t'], { name: name }), body: tpl(T[act + '_p'], { name: name }), label: T[act + '_b'], danger: act === 'delete' };
    if (act === 'delete') o.phrase = T.phrase;
    confirmDlg(o).then(function (ok) {
      if (!ok) return;
      manage(id, map[act]).then(function (d) {
        if (d.success) { toast(d.message || T.ok); setTimeout(function () { location.reload(); }, 700); } else toast(d.message || T.err, 'err');
      }).catch(netErr);
    });
  }
  $$('[data-user-act]').forEach(function (b) { b.addEventListener('click', function () { userAction(+b.dataset.id, b.dataset.userAct, b.dataset.name); }); });

  $$('[data-bulk]').forEach(function (b) {
    b.addEventListener('click', function () {
      var act = b.dataset.bulk, ids = selectedIds().filter(function (id) { return id !== P.me; });
      var targets = ids.filter(function (id) {
        var tr = $('#pe-body tr[data-user="' + id + '"]'); if (!tr) return false;
        if (act === 'approve') return tr.dataset.pending === '1';
        if (act === 'suspend') return tr.dataset.active === '1';
        if (act === 'reactivate') return tr.dataset.active === '0';
        return true;
      });
      if (!targets.length) { toast(T.bulk_none, 'err'); return; }
      var o = { title: tpl(T['bulk_' + act + '_t'], { n: targets.length }), body: T['bulk_' + act + '_p'], label: T[act + '_b'] + ' (' + targets.length + ')', danger: act === 'delete' };
      if (act === 'delete') o.phrase = T.phrase;
      var map = { approve: 'approve', suspend: 'toggle_active', reactivate: 'toggle_active', delete: 'delete' };
      confirmDlg(o).then(function (ok) {
        if (!ok) return;
        var okN = 0, chain = Promise.resolve();
        targets.forEach(function (id) { chain = chain.then(function () { return manage(id, map[act]).then(function (d) { if (d.success) okN++; }).catch(function () {}); }); });
        chain.then(function () { toast(tpl(T.bulk_done, { ok: okN, n: targets.length }), okN === targets.length ? '' : 'err'); setTimeout(function () { location.reload(); }, 900); });
      });
    });
  });

  /* ---------- Create user / direct message ---------- */
  var cu = $('#create-user-form');
  cu.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = $('#create-user-btn'), err = $('#modal-error'); err.hidden = true; btn.disabled = true; btn.textContent = T.creating;
    post('/promoter/create-user.php', new FormData(cu)).then(function (d) {
      btn.disabled = false; btn.textContent = T.cu_submit;
      if (d.success) { $('#user-modal').close(); cu.reset(); toast(T.ok); setTimeout(function () { location.reload(); }, 700); }
      else { err.textContent = d.message || T.err; err.hidden = false; }
    }).catch(function (x) { btn.disabled = false; btn.textContent = T.cu_submit; netErr(x); });
  });
  var dm = $('#direct-message-form');
  dm.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = $('#send-dm-btn'); btn.disabled = true; btn.textContent = T.dm_sending;
    post('/promoter/send-direct-message.php', new FormData(dm)).then(function (d) {
      btn.disabled = false; btn.textContent = T.dm_send;
      if (d.success) { toast(d.message || T.ok); $('#direct-message-modal').close(); dm.reset(); } else toast(d.message || T.err, 'err');
    }).catch(function (x) { btn.disabled = false; btn.textContent = T.dm_send; netErr(x); });
  });

  /* ---------- Courses & modules ---------- */
  $$('select[id^="teacher-select-"]').forEach(function (s) {
    var id = s.id.replace('teacher-select-', ''), btn = $('[data-assign="' + id + '"]');
    s.addEventListener('change', function () { btn.disabled = !s.value || s.value === s.dataset.current; });
  });
  function setTeacher(courseId, teacherId, label) {
    var fd = new FormData(); fd.append('course_id', courseId); fd.append('teacher_id', teacherId);
    post('/promoter/update-teacher.php', fd).then(function (d) {
      if (d.success) { toast(label); setTimeout(function () { location.reload(); }, 700); } else toast(d.message || T.err, 'err');
    }).catch(netErr);
  }
  $$('[data-assign]').forEach(function (b) { b.addEventListener('click', function () {
    var s = document.getElementById('teacher-select-' + b.dataset.assign);
    if (!s || !s.value) { toast(T.pick_teacher, 'err'); return; }
    setTeacher(b.dataset.assign, s.value, T.assigned);
  }); });
  $$('[data-unassign]').forEach(function (b) { b.addEventListener('click', function () {
    confirmDlg({ title: T.revteacher_t, body: T.revteacher_p, label: T.revteacher_b, danger: true }).then(function (ok) { if (ok) setTeacher(b.dataset.unassign, '0', T.removed); });
  }); });

  $$('[data-mod-edit]').forEach(function (b) { b.addEventListener('click', function () {
    $('#md-id').value = b.dataset.id; $('#md-title').value = b.dataset.title; $('#md-desc').value = b.dataset.desc; openDlg('module-modal');
  }); });
  $('#module-form').addEventListener('submit', function (e) {
    e.preventDefault();
    post('/promoter/update-module.php', new FormData(e.target)).then(function (d) {
      if (d.success) { toast(d.message || T.ok); setTimeout(function () { location.reload(); }, 600); } else toast(d.message || T.err, 'err');
    }).catch(netErr);
  });
  $$('[data-mod-del]').forEach(function (b) { b.addEventListener('click', function () {
    confirmDlg({ title: T.moddel_t, body: tpl(T.moddel_p, { name: b.dataset.name }), label: T.moddel_b, danger: true }).then(function (ok) {
      if (!ok) return; var fd = new FormData(); fd.append('module_id', b.dataset.id);
      post('/promoter/delete-module.php', fd).then(function (d) { if (d.success) { toast(d.message || T.ok); setTimeout(function () { location.reload(); }, 600); } else toast(d.message || T.err, 'err'); }).catch(netErr);
    });
  }); });

  /* ---------- Certificates ---------- */
  function issue(studentId, courseId, name, course, btn) {
    confirmDlg({ title: T.issue_t, body: tpl(T.issue_p, { name: name, course: course }), label: T.issue_b }).then(function (ok) {
      if (!ok) return;
      var fd = new FormData(); fd.append('student_id', studentId); fd.append('course_id', courseId);
      if (btn) btn.classList.add('is-busy');
      post('/promoter/issue-certificate.php', fd).then(function (d) {
        if (d.success) { toast(tpl(T.cert_ok, { code: d.certificate_code, name: d.student_name || name })); setTimeout(function () { location.reload(); }, 900); }
        else { toast(d.message || T.err, 'err'); if (btn) btn.classList.remove('is-busy'); }
      }).catch(function (x) { if (btn) btn.classList.remove('is-busy'); netErr(x); });
    });
  }
  $$('[data-issue]').forEach(function (b) { b.addEventListener('click', function () { issue(b.dataset.student, b.dataset.course, b.dataset.name, b.dataset.ctitle, b); }); });
  var mc = $('#manual-cert-form');
  mc.addEventListener('submit', function (e) {
    e.preventDefault();
    var s = mc.student_id, c = mc.course_id;
    if (!s.value || !c.value) return;
    issue(s.value, c.value, s.options[s.selectedIndex].text, c.options[c.selectedIndex].text, null);
  });
  function simpleFilter(inputSel, bodySel, emptySel) {
    var inp = $(inputSel), body = $(bodySel), empty = $(emptySel); if (!inp || !body) return;
    inp.addEventListener('input', function () {
      var q = inp.value.trim().toLowerCase(), n = 0;
      $$('tr[data-q]', body).forEach(function (tr) { var ok = !q || tr.dataset.q.indexOf(q) >= 0; tr.hidden = !ok; if (ok) n++; });
      if (empty) empty.hidden = n > 0;
    });
  }
  simpleFilter('#ce-q', '#ce-body', '#ce-empty');
  simpleFilter('#au-q', '#au-body', '#au-empty');

  /* ---------- Newsletter ---------- */
  var nlForm = $('#newsletter-form'), aud = $('#nl-aud'), subj = $('#nl-subject'), body = $('#nl-body');
  function audN() { return (P.aud || {})[aud.value] || 0; }
  function updatePreview() {
    var n = audN(), a = (P.audLabels || {})[aud.value], d = (P.audDesc || {})[aud.value];
    $('#nl-line').innerHTML = tpl(esc(T.line), { n: '<b>' + n + '</b>', d: esc(d) });
    $('#pv-to').textContent = tpl(T.prev_to, { a: a, n: n });
    $('#pv-subject').textContent = subj.value.trim() || T.prev_nosub;
    var pb = $('#pv-body'), txt = body.value.trim();
    pb.textContent = txt || pb.dataset.empty; pb.classList.toggle('empty', !txt);
  }
  $('#pv-body').dataset.empty = $('#pv-body').textContent;
  [aud, subj, body].forEach(function (el) { el.addEventListener('input', updatePreview); el.addEventListener('change', updatePreview); });
  updatePreview();
  nlForm.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!subj.value.trim() || !body.value.trim()) { toast(T.nl_empty, 'err'); (subj.value.trim() ? body : subj).focus(); return; }
    var n = audN(); if (!n) { toast(T.nl_norecip, 'err'); return; }
    var d = (P.audDesc || {})[aud.value];
    confirmDlg({ title: tpl(T.nl_t, { n: n }), bodyHtml: tpl(esc(T.nl_p).replace(/&lt;br&gt;/g, '<br>'), { subject: esc(subj.value.trim()), aud: esc(d) }), label: tpl(T.nl_b, { n: n }) }).then(function (ok) {
      if (!ok) return;
      var btn = $('#nl-send'), old = btn.textContent; btn.disabled = true; btn.textContent = T.sending;
      post('/promoter/send-newsletter.php', new FormData(nlForm)).then(function (r) {
        if (r.success) { toast(r.message, ''); setTimeout(function () { location.reload(); }, 1800); }
        else { toast(r.message || T.err, 'err'); btn.disabled = false; btn.textContent = old; }
      }).catch(function (x) { btn.disabled = false; btn.textContent = old; netErr(x); });
    });
  });
  var ts = $('#test-smtp-btn');
  if (ts) ts.addEventListener('click', function () {
    ts.classList.add('is-busy');
    post('/promoter/test-mail.php', new FormData()).then(function (d) { ts.classList.remove('is-busy'); toast(d.message || T.smtp_test, d.success ? '' : 'err'); }).catch(function (x) { ts.classList.remove('is-busy'); netErr(x); });
  });

  var kb = $('#send-keys-btn');
  if (kb) kb.addEventListener('click', function () {
    var n = +kb.dataset.count;
    confirmDlg({ title: tpl(T.kb_t, { n: n }), body: T.kb_p, label: tpl(T.kb_b, { n: n }) }).then(function (ok) {
      if (!ok) return;
      var st = $('#keys-broadcast-status'); kb.disabled = true; st.hidden = false; st.className = 'note'; st.textContent = T.kb_sending;
      var fd = new FormData(); fd.append('csrf_token', csrf());
      post('/promoter/send-keys-broadcast.php', fd).then(function (d) {
        st.textContent = d.message || ''; st.className = 'note' + (d.success ? '' : ' warn');
        toast(d.message || T.ok, d.success ? '' : 'err'); if (!d.success) kb.disabled = false;
      }).catch(function (x) { kb.disabled = false; st.hidden = true; netErr(x); });
    });
  });

  /* ---------- API keys ---------- */
  var keysLoaded = false;
  function fmtDate(s) {
    if (!s) return ''; var d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d) ? s : d.toLocaleDateString(P.lang === 'en' ? 'en-GB' : 'fr-FR', { day: '2-digit', month: P.lang === 'en' ? 'short' : '2-digit', year: 'numeric' });
  }
  function loadKeys() {
    fetch('/promoter/list-api-keys.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      keysLoaded = true;
      var tb = $('#api-keys-body');
      if (!d.success || !d.keys.length) { tb.innerHTML = '<tr><td colspan="5" class="tbl-empty">' + esc(T.ak_none) + '</td></tr>'; return; }
      tb.innerHTML = d.keys.map(function (k) {
        var on = +k.is_active === 1;
        return '<tr><td><span class="name">' + esc(k.label) + '</span></td>' +
          '<td><span class="st ' + (on ? 'ok' : 'off') + '">' + esc(on ? T.ak_active : T.ak_revoked) + '</span></td>' +
          '<td class="c-hide-sm c-nowrap num">' + esc(fmtDate(k.created_at)) + '</td>' +
          '<td class="c-hide-sm">' + esc(k.created_by_name || '') + '</td>' +
          '<td class="c-act">' + (on ? '<button type="button" class="btn btn-quiet btn-sm" style="color:var(--danger)" data-key-revoke="' + (+k.id) + '" data-name="' + esc(k.label) + '">' + esc(T.ak_revoke) + '…</button>' : '') + '</td></tr>';
      }).join('');
    }).catch(netErr);
  }
  $('#api-keys-body').addEventListener('click', function (e) {
    var b = e.target.closest('[data-key-revoke]'); if (!b) return;
    confirmDlg({ title: tpl(T.akrev_t, { name: b.dataset.name }), body: T.akrev_p, label: T.akrev_b, danger: true }).then(function (ok) {
      if (!ok) return; var fd = new FormData(); fd.append('key_id', b.dataset.keyRevoke); fd.append('csrf_token', csrf());
      post('/promoter/revoke-api-key.php', fd).then(function (d) { if (d.success) { toast(T.ak_revoked_ok); loadKeys(); } else toast(d.message || T.err, 'err'); }).catch(netErr);
    });
  });
  $('#api-key-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = $('#api-key-create'), label = $('#ak-label').value.trim() || tpl(T.ak_default, { d: new Date().toLocaleDateString(P.lang === 'en' ? 'en-GB' : 'fr-FR') });
    var fd = new FormData(); fd.append('label', label); fd.append('csrf_token', csrf());
    btn.classList.add('is-busy');
    post('/promoter/create-api-key.php', fd).then(function (d) {
      btn.classList.remove('is-busy');
      if (!d.success) { toast(d.message || T.err, 'err'); return; }
      $('#api-key-value').value = d.api_key; $('#api-key-result').hidden = false; $('#ak-label').value = '';
      $('#api-key-copy').textContent = T.ak_copy; $('#api-key-value').focus(); $('#api-key-value').select();
      $('#api-key-result').scrollIntoView({ block: 'nearest' });
      toast(T.ak_created); loadKeys();
    }).catch(function (x) { btn.classList.remove('is-busy'); netErr(x); });
  });
  $('#api-key-copy').addEventListener('click', function () {
    var v = $('#api-key-value'), b = this; v.select();
    copyText(v.value).then(function () { b.textContent = T.ak_copied; });
  });
  $('#api-key-done').addEventListener('click', function () { $('#api-key-value').value = ''; $('#api-key-result').hidden = true; });

  /* ---------- AI report ---------- */
  var ab = $('#audit-btn');
  if (ab) ab.addEventListener('click', function () {
    ab.disabled = true; $('#audit-loading').hidden = false; $('#audit-result-container').hidden = true;
    fetch('/api/ai-promoter.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(function (d) {
        ab.disabled = false; $('#audit-loading').hidden = true;
        if (d.success && d.report) { $('#audit-text').textContent = d.report; $('#audit-result-container').hidden = false; toast(T.ai_ok); }
        else toast(d.error || T.err, 'err');
      }).catch(function (x) { ab.disabled = false; $('#audit-loading').hidden = true; netErr(x); });
  });

  /* ---------- Notifications ---------- */
  var nb = $('#notif-btn'), np = $('#notif-panel');
  function loadNotifs() {
    fetch('/student/get-notifications.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.success) return;
      $('#notif-count').hidden = !(d.unread_count > 0);
      np.innerHTML = d.notifications && d.notifications.length
        ? d.notifications.map(function (n) { return '<a href="' + esc(n.link || '#') + '"' + (+n.is_read === 0 ? ' style="font-weight:600"' : '') + '>' + esc(n.title) + '<small>' + esc(n.body || '') + '</small></a>'; }).join('')
        : '<p>' + esc(T.notif_none) + '</p>';
    }).catch(function () {});
  }
  nb.addEventListener('click', function (e) { e.stopPropagation(); np.hidden = !np.hidden; nb.setAttribute('aria-expanded', String(!np.hidden)); });
  document.addEventListener('click', function (e) { if (!np.hidden && !e.target.closest('#notif-wrap')) { np.hidden = true; nb.setAttribute('aria-expanded', 'false'); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !np.hidden) { np.hidden = true; nb.setAttribute('aria-expanded', 'false'); nb.focus(); } });
  loadNotifs();

  /* ---------- Boot ---------- */
  applyFilters();
  show(location.hash.slice(1) || 'tab-overview', false);
  window.scrollTo({ top: 0, behavior: 'instant' });
  if (P.ok) {
    toast(P.ok);
    try { var u = new URL(location.href); u.searchParams.delete('success'); history.replaceState(null, '', u.pathname + (u.search || '') + u.hash); } catch (e) {}
  }
})();
