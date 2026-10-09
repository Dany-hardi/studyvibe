/* StudyVibe v2 — teacher console behaviour.
   Everything here is progressive: the page works without it, it adds sorting,
   filters, grouped exports, confirmation dialogs and the live control room. */
(function () {
  'use strict';
  var T = window.SV_T || {};
  function t(key, vars) {
    var s = T[key] != null ? T[key] : key;
    if (vars) Object.keys(vars).forEach(function (k) { s = s.split('{' + k + '}').join(vars[k]); });
    return s;
  }
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }

  /* ── Language switch fallback (shared app.js may not define it) ── */
  if (typeof window.changeLanguage !== 'function') {
    window.changeLanguage = function (lang) {
      fetch('/api/set-language.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ lang: lang }) })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.success) location.reload(); })
        .catch(function () {});
    };
  }

  /* ── Confirmation dialogs for destructive buttons ─────────────── */
  var dlg;
  function ensureDialog() {
    if (dlg) return dlg;
    dlg = document.createElement('dialog');
    dlg.className = 't-dialog';
    dlg.setAttribute('aria-labelledby', 't-dlg-title');
    dlg.innerHTML = '<form method="dialog"><h3 id="t-dlg-title"></h3><p id="t-dlg-msg"></p>' +
      '<label id="t-dlg-typed" hidden><span id="t-dlg-typed-l"></span><input type="text" autocomplete="off" id="t-dlg-typed-i"></label>' +
      '<div class="row"><button type="button" class="t-btn t-btn-ghost" data-dlg="cancel"></button>' +
      '<button type="button" class="t-btn" data-dlg="ok"></button></div></form>';
    document.body.appendChild(dlg);
    return dlg;
  }
  function confirmDialog(opts) {
    var d = ensureDialog();
    $('#t-dlg-title', d).textContent = opts.title || '';
    $('#t-dlg-msg', d).textContent = opts.message || '';
    var cancel = $('[data-dlg=cancel]', d), ok = $('[data-dlg=ok]', d);
    cancel.textContent = t('dlg_cancel');
    ok.textContent = opts.ok || 'OK';
    ok.className = 't-btn ' + (opts.kind === 'danger' ? 't-btn-danger-solid' : 't-btn-primary');
    var typed = $('#t-dlg-typed', d), typedI = $('#t-dlg-typed-i', d);
    typedI.value = '';
    if (opts.type) {
      typed.hidden = false;
      $('#t-dlg-typed-l', d).textContent = t('dlg_type', { w: opts.type });
      ok.disabled = true;
      typedI.oninput = function () { ok.disabled = typedI.value.trim().toUpperCase() !== opts.type.toUpperCase(); };
    } else { typed.hidden = true; ok.disabled = false; typedI.oninput = null; }
    return new Promise(function (resolve) {
      function done(v) { d.close(); cancel.onclick = ok.onclick = null; d.onclose = null; resolve(v); }
      cancel.onclick = function () { done(false); };
      ok.onclick = function () { done(true); };
      d.onclose = function () { resolve(false); };
      d.showModal();
      (opts.type ? typedI : cancel).focus();
    });
  }
  window.svConfirm = confirmDialog;
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-confirm]');
    if (!btn || btn.dataset.confirmed === '1') return;
    e.preventDefault();
    e.stopPropagation();
    confirmDialog({
      title: btn.getAttribute('data-confirm-title'), message: btn.getAttribute('data-confirm'),
      ok: btn.getAttribute('data-confirm-ok'), kind: btn.getAttribute('data-confirm-kind'),
      type: btn.getAttribute('data-confirm-type') || ''
    }).then(function (yes) {
      if (!yes) return;
      btn.dataset.confirmed = '1';
      if (btn.form && btn.form.requestSubmit) btn.form.requestSubmit(btn); else btn.click();
    });
  }, true);

  /* ── Tables: sort, filter, export ─────────────────────────────── */
  function cellValue(td) {
    if (!td) return '';
    if (td.dataset && td.dataset.v != null) return td.dataset.v;
    return td.textContent.replace(/\s+/g, ' ').trim();
  }
  function toNumber(v) {
    if (v === '' || v == null) return NaN;
    var m = String(v).replace(',', '.').match(/-?\d+(\.\d+)?/);
    return m && /^[\s\-\d.,/%a-z]*$/i.test(v) ? parseFloat(m[0]) : NaN;
  }
  function sortTable(th, dir) {
    var table = th.closest('table'), body = table.tBodies[0];
    if (!body) return;
    var idx = th.cellIndex;
    var rows = $$('tr', body).filter(function (r) { return !(r.cells.length === 1 && r.cells[0].colSpan > 1); });
    var vals = rows.map(function (r) { return cellValue(r.cells[idx]); });
    var numeric = vals.every(function (v) { return v === '' || !isNaN(toNumber(v)); }) && vals.some(function (v) { return v !== ''; });
    var order = rows.map(function (r, i) { return { r: r, v: vals[i], n: numeric ? toNumber(vals[i]) : 0 }; });
    order.sort(function (a, b) {
      var c = numeric ? ((isNaN(a.n) ? -Infinity : a.n) - (isNaN(b.n) ? -Infinity : b.n)) : a.v.localeCompare(b.v, undefined, { numeric: true, sensitivity: 'base' });
      return dir === 'ascending' ? c : -c;
    });
    order.forEach(function (o) { body.appendChild(o.r); });
    $$('th', table).forEach(function (h) { if (h !== th) h.removeAttribute('aria-sort'); });
    th.setAttribute('aria-sort', dir);
  }
  function enhanceTables(root) {
    $$('main table', root || document).forEach(function (table) {
      if (table.dataset.enh) return;
      var head = table.tHead; if (!head) return;
      table.dataset.enh = '1';
      $$('th', head).forEach(function (th) {
        if (th.hasAttribute('data-sort') && th.dataset.sort === 'off') return;
        var txt = th.textContent.trim();
        if (!txt) return;
        th.setAttribute('data-sort', th.dataset.sort || 'auto');
        th.tabIndex = 0;
      });
    });
  }
  document.addEventListener('click', function (e) {
    var th = e.target.closest && e.target.closest('th[data-sort]');
    if (!th) return;
    sortTable(th, th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending');
  });
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('th[data-sort]')) { e.preventDefault(); e.target.click(); }
  });
  document.addEventListener('input', function (e) {
    var inp = e.target.closest && e.target.closest('[data-table-filter]');
    if (!inp) return;
    var table = document.getElementById(inp.getAttribute('data-table-filter'));
    if (!table || !table.tBodies[0]) return;
    var q = inp.value.trim().toLowerCase(), shown = 0;
    $$('tr', table.tBodies[0]).forEach(function (r) {
      if (r.cells.length === 1 && r.cells[0].colSpan > 1) return;
      var hit = !q || r.textContent.toLowerCase().indexOf(q) !== -1;
      r.hidden = !hit; if (hit) shown++;
    });
    var c = document.querySelector('[data-table-count="' + inp.getAttribute('data-table-filter') + '"]');
    if (c) c.textContent = shown;
  });

  function tableMatrix(table) {
    var rows = [];
    $$('tr', table).forEach(function (tr) {
      if (tr.cells.length === 1 && tr.cells[0].colSpan > 1) return;
      rows.push($$('th,td', tr).filter(function (c) { return !c.querySelector('form'); }).map(function (c) { return c.textContent.replace(/\s+/g, ' ').trim(); }));
    });
    return rows;
  }
  function download(name, mime, content) {
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([content], { type: mime }));
    a.download = name; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-export]');
    if (!b) return;
    var table = document.getElementById(b.dataset.table); if (!table) return;
    var name = (b.dataset.name || 'export').replace(/[^\w-]+/g, '_');
    var kind = b.dataset.export;
    if (kind === 'csv') {
      var csv = tableMatrix(table).map(function (r) { return r.map(function (v) { return '"' + v.replace(/"/g, '""') + '"'; }).join(','); }).join('\r\n');
      download(name + '.csv', 'text/csv;charset=utf-8', '﻿' + csv);
    } else if (kind === 'xlsx') {
      if (typeof window.exportTableToExcel === 'function') window.exportTableToExcel(b.dataset.table, name);
    } else if (kind === 'pdf') {
      var w = window.open('', '_blank'); if (!w) return;
      w.document.write('<!doctype html><meta charset="utf-8"><title>' + escapeHtml(name) + '</title><style>body{font:13px/1.4 system-ui,sans-serif;color:#1E1B16;margin:24px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #bbb;padding:5px 8px;text-align:left}th{background:#eee}form,button{display:none}</style>' + table.outerHTML);
      w.document.close(); w.focus(); setTimeout(function () { w.print(); }, 250);
    }
  });
  window.svExportGroup = function (tableId, name) {
    return '<div class="t-export" role="group"><span>' + escapeHtml(t('exp_pdf_title')) + '</span>' +
      ['csv', 'xlsx', 'pdf'].map(function (k) { return '<button type="button" data-export="' + k + '" data-table="' + tableId + '" data-name="' + name + '">' + (k === 'xlsx' ? 'Excel' : k.toUpperCase()) + '</button>'; }).join('') + '</div>';
  };

  /* ── Course outline: jump to a chapter / lesson, follow the scroll ── */
  window.goChapter = function (id) {
    var el = document.getElementById('chapter-' + id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  window.goLesson = function (id) {
    var panel = document.getElementById('panel-' + id), item = document.getElementById('lesson-item-' + id);
    if (panel && !panel.classList.contains('open') && typeof window.toggleLesson === 'function') window.toggleLesson(id);
    if (item) {
      item.scrollIntoView({ behavior: 'smooth', block: 'start' });
      item.classList.remove('flash'); void item.offsetWidth; item.classList.add('flash');
    }
  };
  function outlineSpy() {
    var links = $$('[data-outline-lesson]'); if (!links.length || !('IntersectionObserver' in window)) return;
    var map = {};
    links.forEach(function (a) { map[a.getAttribute('data-outline-lesson')] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var id = en.target.id.replace('lesson-item-', '');
        links.forEach(function (a) { a.classList.toggle('on', a === map[id]); });
      });
    }, { rootMargin: '-15% 0px -70% 0px' });
    $$('.lesson-item').forEach(function (el) { io.observe(el); });
  }

  /* ── Q&A filter ─────────────────────────────────────────────── */
  window.qaFilter = function (mode) {
    window.__qaMode = mode;
    $$('[data-qa-card]').forEach(function (c) { c.hidden = (mode === 'open' && c.dataset.unanswered !== '1'); });
    var o = document.getElementById('qa-f-open'), a = document.getElementById('qa-f-all');
    if (o) o.setAttribute('aria-selected', mode === 'open' ? 'true' : 'false');
    if (a) a.setAttribute('aria-selected', mode === 'all' ? 'true' : 'false');
  };

  /* ── Live control room ──────────────────────────────────────── */
  var offset = 0; // server clock minus client clock, in seconds
  function room(id) { return document.getElementById('room-' + id); }
  function setExpanded(r, open) {
    var body = $('#room-body-' + r.dataset.id), btn = $('[data-room-toggle]', r); if (!body) return;
    body.hidden = !open;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    $('[data-room-toggle-text]', btn).textContent = open ? t('room_collapse') : t('room_expand');
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-room-toggle]'); if (!b) return;
    var r = b.closest('[data-room]'), body = $('#room-body-' + r.dataset.id);
    setExpanded(r, body.hidden);
  });
  window.roomTab = function (id, which) {
    var q = document.getElementById('live-session-questions-' + id), p = document.getElementById('live-session-results-' + id);
    var tq = document.getElementById('rtab-q-' + id), tp = document.getElementById('rtab-p-' + id);
    if (!q || !p) return;
    q.classList.toggle('hidden', which !== 'questions'); p.classList.toggle('hidden', which !== 'results');
    if (tq) tq.setAttribute('aria-selected', which === 'questions' ? 'true' : 'false');
    if (tp) tp.setAttribute('aria-selected', which === 'results' ? 'true' : 'false');
  };
  window.openRoom = function (id, tab) {
    if (typeof window.switchDashboardTab === 'function') window.switchDashboardTab('tab-live-eval');
    var r = room(id); if (!r) return;
    setExpanded(r, true);
    if (tab) window.roomTab(id, tab);
    setTimeout(function () { r.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 60);
  };
  var CHIP = { live: 'live', paused: 'paused', wait: 'wait', ended: 'ended', off: 'off' };
  function applyState(r, state, extraSub) {
    if (r.dataset.state === state && !extraSub) return;
    r.dataset.state = state;
    r.className = r.className.replace(/\bis-(live|paused|wait|ended|off)\b/g, '').trim() + ' is-' + state;
    var chip = $('[data-room-chip]', r); chip.className = 't-chip ' + CHIP[state];
    $('[data-room-chip-text]', r).textContent = t('state_' + state);
    var word = $('[data-room-word]', r); word.className = 't-state-word ' + state; word.textContent = t('word_' + state);
    var sub = $('[data-room-sub]', r);
    if (extraSub) sub.textContent = extraSub;
    else sub.textContent = t(state === 'live' ? (r.dataset.async === '1' ? 'room_sub_open_async' : 'room_sub_live') : 'room_sub_' + state, {});
  }
  window.svRoomUpdate = function (s) {
    var r = room(s.id); if (!r) return;
    var total = parseInt(s.total_questions, 10) || 0, async = r.dataset.async === '1';
    var st = r.dataset.state;
    if (!async && (st === 'wait' || st === 'live')) {
      if (s.status === 'active') applyState(r, 'live');
      else if (s.status === 'finished') applyState(r, 'ended');
      else if (s.status === 'waiting' && st === 'live') applyState(r, 'wait', t('room_sub_wait', { w: '' }).replace(/\s+\.$/, '.'));
    }
    st = r.dataset.state;
    var steps = $$('[data-room-steps] i', r), idx = parseInt(s.active_question_index, 10);
    var label = $('[data-room-qlabel]', r);
    if (!async) {
      steps.forEach(function (el, i) {
        el.className = (st === 'ended' || (idx >= 0 && i < idx)) ? 'done' : (st !== 'ended' && i === idx ? 'now' : '');
      });
      if (label) {
        if (st === 'live' || st === 'paused') label.textContent = idx >= 0 ? t('rm_q_now', { i: idx + 1, n: total }) : t('rm_finished_all');
        else if (st === 'ended') label.textContent = t('rm_finished_all');
        else label.textContent = total ? t('room_q_total', { n: total }) : t('room_no_q');
      }
    }
    var reg = parseInt(s.participant_count, 10) || 0, votes = s.status === 'active' ? (parseInt(s.active_question_votes, 10) || 0) : 0;
    var of = $('[data-room-votes-of]', r); if (of) of.textContent = (st === 'live' || st === 'paused') && reg ? t('rm_votes_of', { n: reg }) : '';
    var bar = $('[data-room-bar]', r); if (bar) bar.style.width = (reg ? Math.min(100, Math.round(votes / reg * 100)) : 0) + '%';
  };
  function fmt(sec) {
    sec = Math.max(0, Math.floor(sec));
    var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
    return (h ? h + ':' : '') + (h ? String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
  }
  function tick() {
    var now = Date.now() / 1000 + offset;
    $$('[data-room]').forEach(function (r) {
      var c = $('[data-room-countdown]', r); if (!c) return;
      var left = parseInt(r.dataset.start, 10) - now;
      if (r.dataset.state === 'wait' && left > 0) c.textContent = t('cd_starts_in') + ' ' + fmt(left);
      else c.textContent = '';
    });
  }

  /* ── boot ───────────────────────────────────────────────────── */
  function boot() {
    var first = $('[data-room][data-now]');
    if (first) offset = parseInt(first.dataset.now, 10) - Date.now() / 1000;
    enhanceTables();
    outlineSpy();
    tick(); setInterval(tick, 1000);
    if (window.__qaMode == null && $('#qa-f-open')) window.qaFilter($('#qa-f-open').getAttribute('aria-selected') === 'true' ? 'open' : 'all');
    var main = document.getElementById('main');
    if (main && 'MutationObserver' in window) {
      var pending = false;
      new MutationObserver(function () {
        if (pending) return; pending = true;
        requestAnimationFrame(function () { pending = false; enhanceTables(); });
      }).observe(main, { childList: true, subtree: true });
    }
    if (typeof window.pollTeacherLiveStats === 'function') window.pollTeacherLiveStats();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
