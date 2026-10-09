/*
 * Bulk import of questions with pictures from a ZIP (teacher dashboard, live session panel).
 * Every element with data-bi is one importer: data-type, data-course, data-session (or data-lesson).
 * Step 1 "check" sends the file to /teacher/bulk-import.php (mode=preview) and shows what was found, line by line.
 * Step 2 "import" sends the same file with mode=commit, which re-checks it and imports everything or nothing.
 * Everything shown is put in with textContent.
 */
(function () {
  'use strict';
  var T = window.SV_T || {};
  var t = function (k, v) { var s = T[k] || k; Object.keys(v || {}).forEach(function (n) { s = s.split('{' + n + '}').join(String(v[n])); }); return s; };
  var toast = function (kind, msg) { if (window.Toast && Toast[kind]) Toast[kind](msg); };
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }

  function init(box) {
    var fileIn = box.querySelector('.bi-file'), checkBtn = box.querySelector('.bi-check'), report = box.querySelector('.bi-report'), commitBtn = box.querySelector('.bi-commit');
    var busy = false;

    function send(mode) {
      var f = fileIn.files[0];
      if (!f) { toast('error', t('bi_choose')); return Promise.resolve(null); }
      var fd = new FormData();
      fd.append('file', f); fd.append('mode', mode); fd.append('type', box.dataset.type); fd.append('course_id', box.dataset.course);
      if (box.dataset.session) fd.append('session_id', box.dataset.session);
      if (box.dataset.lesson) fd.append('lesson_id', box.dataset.lesson);
      return fetch('/teacher/bulk-import.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); }).catch(function () { return { success: false, message: t('bi_network') }; });
    }

    function render(d) {
      report.textContent = '';
      report.hidden = false;
      if (!d.summary) { report.appendChild(el('p', 'bi-bad', d.message || t('bi_network'))); commitBtn.hidden = true; return; }
      var s = d.summary;
      report.appendChild(el('p', 'bi-sum', t('bi_summary', { q: s.questions, i: s.with_image, e: s.errors, w: s.warnings })));
      if (d.errors && d.errors.length) {
        var ue = el('ul', 'bi-errors'); d.errors.forEach(function (m) { ue.appendChild(el('li', '', m)); }); report.appendChild(ue);
      }
      if (d.warnings && d.warnings.length) {
        var uw = el('ul', 'bi-warns'); d.warnings.forEach(function (m) { uw.appendChild(el('li', '', m)); }); report.appendChild(uw);
      }
      if (d.rows && d.rows.length) {
        var wrap = el('div', 'bi-tablewrap'), tb = el('table', 'bi-table'), th = el('thead'), hr = el('tr');
        ['bi_col_n', 'bi_col_question', 'bi_col_image', 'bi_col_status'].forEach(function (k) { hr.appendChild(el('th', '', t(k))); });
        th.appendChild(hr); tb.appendChild(th);
        var body = el('tbody');
        d.rows.forEach(function (r) {
          var tr = el('tr', r.status === 'error' ? 'is-error' : '');
          tr.appendChild(el('td', '', String(r.n)));
          tr.appendChild(el('td', '', (r.type === 'written' ? '[' + t('bi_written') + '] ' : '') + r.text + (r.time_limit ? ' · ' + r.time_limit + ' s' : '')));
          tr.appendChild(el('td', '', r.image ? r.image + (r.image_state === 'ok' ? ' ✓' : (r.image_state === 'ignored' ? ' (' + t('bi_ignored') + ')' : ' ✗')) : '—'));
          tr.appendChild(el('td', r.status === 'error' ? 'bi-bad' : 'bi-ok', r.status === 'error' ? (r.messages || []).join(', ') : 'OK'));
          body.appendChild(tr);
        });
        tb.appendChild(body); wrap.appendChild(tb); report.appendChild(wrap);
      }
      commitBtn.hidden = !d.can_import;
      commitBtn.disabled = !d.can_import;
      commitBtn.textContent = t('bi_import', { n: s.questions });
    }

    fileIn.addEventListener('change', function () { report.hidden = true; commitBtn.hidden = true; });
    checkBtn.addEventListener('click', function () {
      if (busy) return; busy = true; checkBtn.disabled = true;
      send('preview').then(function (d) { if (d) render(d); }).finally(function () { busy = false; checkBtn.disabled = false; });
    });
    commitBtn.addEventListener('click', function () {
      if (busy) return; busy = true; commitBtn.disabled = true;
      send('commit').then(function (d) {
        if (!d) return;
        if (d.success) {
          toast('success', t('bi_done', { n: d.imported }));
          setTimeout(function () {
            var u = new URL(window.location.href);
            u.searchParams.set('course_id', box.dataset.course);
            if (box.dataset.session) u.searchParams.set('open_session', box.dataset.session);
            u.hash = box.dataset.session ? '#tab-live-eval' : '#tab-course';
            window.location.href = u.toString();
          }, 900);
        } else {
          render(d); toast('error', d.message || t('bi_network'));
        }
      }).finally(function () { busy = false; });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bi]').forEach(init);
  });
})();
