/*
 * Teacher dashboard: the "exam finished" window and the results review table.
 *
 *   SVReview.open(sessionId)     opens the review table (also used by the "Examiner les résultats" menu entry)
 *   window.RV_PROMPT = {id, title, n, mean}   when set, the "exam finished" window opens at once
 *
 * Data comes from /teacher/live-results.php. Everything shown is put in with textContent, never as HTML.
 */
(function () {
  'use strict';
  var T = window.SV_T || {};
  var $ = function (id) { return document.getElementById(id); };
  var t = function (key, vars) {
    var s = T[key] || key;
    Object.keys(vars || {}).forEach(function (k) { s = s.split('{' + k + '}').join(String(vars[k])); });
    return s;
  };
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }
  function post(data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch('/teacher/live-results.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).catch(function () { return { success: false }; });
  }
  function toast(kind, msg) { if (window.Toast && Toast[kind]) Toast[kind](msg); }

  var state = { sid: 0, data: null, flaggedOnly: false, q: '' };
  var lastFocus = null;

  function open(overlay) { lastFocus = document.activeElement; overlay.classList.add('is-open'); var f = overlay.querySelector('button, input'); if (f) f.focus(); }
  function close(overlay) { overlay.classList.remove('is-open'); if (lastFocus && lastFocus.focus) lastFocus.focus(); }

  // ---------------------------------------------------------------------------------------------- review table
  function integrityCell(row) {
    var td = el('td');
    var chip;
    if (row.cancelled) {
      chip = el('span', 'rv-chip muted', t('rv_cancelled'));
    } else if (!row.submitted) {
      chip = el('span', 'rv-chip muted', t('rv_not_submitted'));
    } else if (!row.integrity.watched && row.integrity.notes.length === 0) {
      chip = el('span', 'rv-chip muted', t('rv_unwatched'));
    } else if (row.integrity.level === 2) {
      chip = el('span', 'rv-chip suspect', t('rv_suspect'));
    } else if (row.integrity.level === 1) {
      chip = el('span', 'rv-chip look', t('rv_look'));
    } else {
      chip = el('span', 'rv-chip ok', t('rv_ok'));
    }
    td.appendChild(chip);
    var notes = row.integrity.notes || [];
    if (row.cancelled && row.cancel_reason) notes = [{ code: 'reason', text: row.cancel_reason }].concat(notes);
    if (notes.length) {
      var ul = el('ul', 'rv-notes');
      notes.forEach(function (n) {
        var text = n.code === 'exits' ? t('rv_note_exits', { n: n.n })
          : n.code === 'similar' ? t('rv_note_similar', { name: n.with, k: n.shared, m: n.of })
          : n.text;
        ul.appendChild(el('li', '', text));
      });
      td.appendChild(ul);
    }
    return td;
  }

  function render() {
    var d = state.data;
    $('rv-title').textContent = d.session.title;
    var s = d.summary;
    $('rv-sub').textContent = d.session.course + ' · ' + t('rv_summary', { total: s.total, attention: s.attention, suspect: s.suspect, cancelled: s.cancelled, contested: s.contested || 0 });
    var body = $('rv-body');
    body.textContent = '';
    var q = state.q.trim().toLowerCase();
    var rows = d.rows.filter(function (r) {
      if (state.flaggedOnly && !(r.integrity.level > 0 || r.cancelled)) return false;
      if (q && (r.name + ' ' + r.email + ' ' + r.matricule).toLowerCase().indexOf(q) === -1) return false;
      return true;
    });
    if (!rows.length) { body.appendChild(el('p', 'rv-empty', t('rv_none'))); return; }

    var table = el('table', 'rv-table');
    var thead = el('thead'); var hr = el('tr');
    ['rv_col_matricule', 'rv_col_name', 'rv_col_email', 'rv_col_mark', 'rv_col_integrity', 'rv_col_action'].forEach(function (k) { hr.appendChild(el('th', '', t(k))); });
    thead.appendChild(hr); table.appendChild(thead);
    var tb = el('tbody');
    rows.forEach(function (r) {
      var tr = el('tr', r.cancelled ? 'is-cancelled' : '');
      tr.appendChild(el('td', 'rv-mat', r.matricule || '—'));
      var nameCell = tr.appendChild(el('td', ''));
      nameCell.appendChild(el('span', 'rv-name', r.name));
      if (r.contest) {
        var cc = r.contest.status === 'open' ? 'look' : (r.contest.status === 'accepted' ? 'ok' : 'muted');
        nameCell.appendChild(document.createElement('br'));
        nameCell.appendChild(el('span', 'rv-chip ' + cc, r.contest.status === 'open' ? t('rv_contest_chip') : t('rv_cs_' + r.contest.status)));
      }
      tr.appendChild(el('td', '', r.email));
      tr.appendChild(el('td', 'rv-num', r.mark === null ? '—' : r.mark + ' / ' + r.total));
      tr.appendChild(integrityCell(r));
      var act = el('td');
      if (r.contest && r.contest.status === 'open') {
        var ans = el('button', 'rv-btn sm primary', t('rv_contest_answer'));
        ans.type = 'button';
        ans.addEventListener('click', function () { askContest(r); });
        act.appendChild(ans);
      } else {
        var b = el('button', 'rv-btn sm' + (r.cancelled ? '' : ' danger'), r.cancelled ? t('rv_restore') : t('rv_cancel'));
        b.type = 'button';
        b.addEventListener('click', function () { r.cancelled ? restore(r) : askCancel(r); });
        act.appendChild(b);
      }
      tr.appendChild(act);
      tb.appendChild(tr);
    });
    table.appendChild(tb);
    body.appendChild(table);
  }

  function load(sid) {
    state.sid = sid;
    return fetch('/teacher/live-results.php?session_id=' + encodeURIComponent(sid), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.success) { toast('error', d.message || t('rv_err')); return false; }
        state.data = d;
        $('rv-watch-hint').hidden = d.session.integrity_watch;
        $('rv-analysis').href = '/teacher/live-analysis.php?session_id=' + encodeURIComponent(sid);
        render();
        return true;
      })
      .catch(function () { toast('error', t('rv_err')); return false; });
  }

  // ---------------------------------------------------------------------------------------------- cancel / restore
  function askCancel(row) {
    var ov = $('rv-confirm');
    $('rv-confirm-title').textContent = t('rv_cancel_title', { name: row.name });
    $('rv-reason').value = '';
    ov.dataset.rid = row.id;
    open(ov);
  }
  function doCancel() {
    var ov = $('rv-confirm'), btn = $('rv-confirm-ok');
    btn.disabled = true;
    post({ action: 'cancel', registration_id: ov.dataset.rid, reason: $('rv-reason').value }).then(function (res) {
      btn.disabled = false;
      if (!res.success) { toast('error', t('rv_err')); return; }
      close(ov); toast('success', t('rv_cancelled_ok')); load(state.sid);
    });
  }
  // ---------------------------------------------------------------------------------------------- answering a contestation
  function askContest(row) {
    var ov = $('rv-contest');
    $('rv-contest-title').textContent = t('rv_contest_title', { name: row.name });
    $('rv-contest-msg').textContent = row.contest.message;
    $('rv-contest-resp').value = '';
    $('rv-dec-accept').checked = true;
    ov.dataset.cid = row.contest.id;
    open(ov);
  }
  function doContest() {
    var ov = $('rv-contest'), btn = $('rv-contest-ok');
    var accept = $('rv-dec-accept').checked, resp = $('rv-contest-resp').value.trim();
    if (!accept && resp.length < 10) { toast('error', t('rv_reason_needed')); return; }
    btn.disabled = true;
    post({ action: 'resolve_contest', contest_id: ov.dataset.cid, decision: accept ? 'accept' : 'reject', response: resp }).then(function (res) {
      btn.disabled = false;
      if (!res.success) { toast('error', res.error === 'reason_needed' ? t('rv_reason_needed') : t('rv_err')); return; }
      close(ov); toast('success', t('rv_contest_ok')); load(state.sid);
    });
  }
  function restore(row) {
    post({ action: 'restore', registration_id: row.id }).then(function (res) {
      if (!res.success) { toast('error', t('rv_err')); return; }
      toast('success', t('rv_restored_ok')); load(state.sid);
    });
  }

  // ---------------------------------------------------------------------------------------------- wiring
  function openReview(sid) {
    var ov = $('rv-modal-overlay');
    if (!ov) return;
    state.flaggedOnly = false; state.q = '';
    $('rv-flagged').checked = false; $('rv-search').value = '';
    $('rv-body').textContent = ''; $('rv-body').appendChild(el('p', 'rv-empty', '…'));
    open(ov);
    load(sid);
    post({ action: 'prompt_seen', session_id: sid });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!$('rv-modal-overlay')) return;
    $('rv-close').addEventListener('click', function () { close($('rv-modal-overlay')); });
    $('rv-flagged').addEventListener('change', function (e) { state.flaggedOnly = e.target.checked; render(); });
    $('rv-search').addEventListener('input', function (e) { state.q = e.target.value; render(); });
    $('rv-confirm-ok').addEventListener('click', doCancel);
    $('rv-confirm-no').addEventListener('click', function () { close($('rv-confirm')); });
    $('rv-contest-ok').addEventListener('click', doContest);
    $('rv-contest-no').addEventListener('click', function () { close($('rv-contest')); });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      ['rv-contest', 'rv-confirm', 'rv-modal-overlay', 'rv-prompt-overlay'].some(function (id) {
        var o = $(id); if (o && o.classList.contains('is-open')) { close(o); return true; } return false;
      });
    });

    var p = window.RV_PROMPT;
    if (p && $('rv-prompt-overlay')) {
      $('rv-prompt-title').textContent = p.title;
      $('rv-prompt-lede').textContent = t('rv_done_lede', { n: p.n });
      $('rv-prompt-view').addEventListener('click', function () { close($('rv-prompt-overlay')); openReview(p.id); });
      $('rv-prompt-later').addEventListener('click', function () { close($('rv-prompt-overlay')); post({ action: 'prompt_seen', session_id: p.id }); });
      open($('rv-prompt-overlay'));
    }
  });

  window.SVReview = { open: openReview };
})();
