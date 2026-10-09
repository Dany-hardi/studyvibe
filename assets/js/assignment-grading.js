/*
 * Teacher dashboard, assignments tab: the marking window. AsgGrade.open(submissionId) loads one submission from
 * /teacher/grade-assignment.php and lets the teacher give a mark and a feedback, or ask for a new version.
 * Everything shown is put in with textContent.
 */
(function () {
  'use strict';
  var T = window.SV_T || {};
  var $ = function (id) { return document.getElementById(id); };
  var t = function (k, v) { var s = T[k] || k; Object.keys(v || {}).forEach(function (n) { s = s.split('{' + n + '}').join(String(v[n])); }); return s; };
  var toast = function (kind, msg) { if (window.Toast && Toast[kind]) Toast[kind](msg); };
  var current = null;

  function post(data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch('/teacher/grade-assignment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).catch(function () { return { success: false }; });
  }
  function fmt(n) { return String(Math.round(n * 100) / 100).replace('.', ','); }
  function row(label, node) {
    var d = document.createElement('div'); d.className = 'ag-row';
    var l = document.createElement('span'); l.className = 'ag-k'; l.textContent = label;
    var v = document.createElement('span'); v.className = 'ag-v';
    if (typeof node === 'string') v.textContent = node; else v.appendChild(node);
    d.appendChild(l); d.appendChild(v); return d;
  }
  function link(href, text) { var a = document.createElement('a'); a.href = href; a.textContent = text; a.target = '_blank'; a.rel = 'noopener noreferrer'; return a; }

  function open(id) {
    var ov = $('ag-overlay'); if (!ov) return;
    $('ag-body').textContent = '…'; ov.classList.add('is-open');
    fetch('/teacher/grade-assignment.php?submission_id=' + encodeURIComponent(id), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (!d.success) { ov.classList.remove('is-open'); toast('error', d.message || t('ag_err')); return; } current = d; render(d); })
      .catch(function () { ov.classList.remove('is-open'); toast('error', t('ag_err')); });
  }

  function render(d) {
    var s = d.submission, body = $('ag-body');
    body.textContent = '';
    $('ag-title').textContent = s.assignment;
    $('ag-sub').textContent = s.course;
    body.appendChild(row(t('ag_student'), s.student + (s.matricule ? ' · ' + s.matricule : '')));
    body.appendChild(row(t('ag_email'), s.email));
    body.appendChild(row(t('ag_submitted'), s.submitted_at + (s.late ? ' · ' + t('asg_late') : '') + (s.attempt > 1 ? ' · ' + t('asg_versions', { n: s.attempt }) : '')));
    if (s.file_url) body.appendChild(row(t('ag_file'), link(s.file_url, s.file_name || 'file')));
    if (s.link) body.appendChild(row(t('ag_link'), link(s.link, s.link)));
    if (s.comment) body.appendChild(row(t('ag_comment'), s.comment));
    if (s.revision_requested_at) body.appendChild(row(t('ag_revision_sent'), s.revision_note || ''));

    var form = document.createElement('div'); form.className = 'ag-form';
    var l1 = document.createElement('label'); l1.htmlFor = 'ag-score'; l1.textContent = t('ag_score', { max: fmt(s.max) });
    var in1 = document.createElement('input'); in1.id = 'ag-score'; in1.type = 'text'; in1.inputMode = 'decimal'; in1.autocomplete = 'off';
    in1.value = s.score !== null ? fmt(s.score) : '';
    var l2 = document.createElement('label'); l2.htmlFor = 'ag-feedback'; l2.textContent = t('ag_feedback');
    var ta = document.createElement('textarea'); ta.id = 'ag-feedback'; ta.maxLength = 5000; ta.value = s.feedback || '';
    var save = document.createElement('button'); save.type = 'button'; save.className = 'ag-btn primary'; save.textContent = t('ag_save');
    save.addEventListener('click', function () {
      save.disabled = true;
      post({ action: 'grade', submission_id: s.id, score: in1.value, feedback: ta.value }).then(function (r) {
        save.disabled = false;
        if (!r.success) { toast('error', r.error === 'bad_score' ? t('ag_bad_score', { max: fmt(s.max) }) : t('ag_err')); return; }
        toast('success', t('ag_saved')); setTimeout(function () { location.reload(); }, 600);
      });
    });
    form.appendChild(l1); form.appendChild(in1); form.appendChild(l2); form.appendChild(ta); form.appendChild(save);
    body.appendChild(form);

    var rev = document.createElement('details'); rev.className = 'ag-rev';
    var sm = document.createElement('summary'); sm.textContent = t('ag_revision'); rev.appendChild(sm);
    var l3 = document.createElement('label'); l3.htmlFor = 'ag-note'; l3.textContent = t('ag_revision_note');
    var ta2 = document.createElement('textarea'); ta2.id = 'ag-note'; ta2.maxLength = 500;
    var send = document.createElement('button'); send.type = 'button'; send.className = 'ag-btn'; send.textContent = t('ag_revision_send');
    send.addEventListener('click', function () {
      if (ta2.value.trim().length < 5) { toast('error', t('ag_note_needed')); return; }
      send.disabled = true;
      post({ action: 'revision', submission_id: s.id, note: ta2.value }).then(function (r) {
        send.disabled = false;
        if (!r.success) { toast('error', t('ag_err')); return; }
        toast('success', t('ag_revision_done')); setTimeout(function () { location.reload(); }, 600);
      });
    });
    rev.appendChild(l3); rev.appendChild(ta2); rev.appendChild(send);
    body.appendChild(rev);

    if (d.history && d.history.length) {
      var h = document.createElement('div'); h.className = 'ag-hist';
      var hh = document.createElement('strong'); hh.textContent = t('ag_history'); h.appendChild(hh);
      d.history.forEach(function (v) {
        var p = document.createElement('p');
        p.textContent = t('ag_version_n', { n: v.attempt }) + ' · ' + v.submitted_at + (v.score !== null ? ' · ' + fmt(parseFloat(v.score)) + ' / ' + fmt(s.max) : '') + (v.file_name ? ' · ' + v.file_name : '');
        h.appendChild(p);
      });
      body.appendChild(h);
    }
    var first = $('ag-score'); if (first) first.focus();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var ov = $('ag-overlay'); if (!ov) return;
    $('ag-close').addEventListener('click', function () { ov.classList.remove('is-open'); });
    ov.addEventListener('click', function (e) { if (e.target === ov) ov.classList.remove('is-open'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') ov.classList.remove('is-open'); });
  });
  window.AsgGrade = { open: open };
})();
