/*
 * Teacher dashboard, assignments tab: the marking window. AsgGrade.open(submissionId) loads one submission from
 * /teacher/grade-assignment.php and lets the teacher give a mark (or fill the rubric), write a feedback, return an annotated copy,
 * release a held mark, or ask for a new version. Also: AsgGrade.releaseAll / withdrawAll (buttons of the assignment card) and
 * AsgSim.run / AsgSim.show (similarity check). Everything shown is put in with textContent.
 */
(function () {
  'use strict';
  var T = window.SV_T || {};
  var $ = function (id) { return document.getElementById(id); };
  var t = function (k, v) { var s = T[k] || k; Object.keys(v || {}).forEach(function (n) { s = s.split('{' + n + '}').join(String(v[n])); }); return s; };
  var toast = function (kind, msg) { if (window.Toast && Toast[kind]) Toast[kind](msg); };
  var current = null;

  function post(data, file) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    if (file) fd.append('feedback_file', file);
    return fetch('/teacher/grade-assignment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).catch(function () { return { success: false }; });
  }
  function fmt(n) { return String(Math.round(n * 100) / 100).replace('.', ','); }
  function num(v) { var s = String(v == null ? '' : v).trim().replace(',', '.'); return s !== '' && isFinite(s) ? parseFloat(s) : null; }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }
  function row(label, node) {
    var d = el('div', 'ag-row'), l = el('span', 'ag-k', label), v = el('span', 'ag-v');
    if (typeof node === 'string') v.textContent = node; else v.appendChild(node);
    d.appendChild(l); d.appendChild(v); return d;
  }
  function link(href, text) { var a = el('a', '', text); a.href = href; a.target = '_blank'; a.rel = 'noopener noreferrer'; return a; }
  function errorText(code, max) {
    return { bad_score: t('ag_bad_score', { max: fmt(max) }), rubric_incomplete: t('ag_err_rubric_incomplete'), rubric_bad_points: t('ag_err_rubric_bad'),
      fb_bad_type: t('ag_err_fb_bad_type'), fb_too_big: t('ag_err_fb_too_big'), fb_bad_content: t('ag_err_fb_bad_content') }[code] || (String(code || '').indexOf('fb_') === 0 ? t('ag_err_fb') : t('ag_err'));
  }

  function open(id) {
    var ov = $('ag-overlay'); if (!ov) return;
    $('ag-body').textContent = '…'; ov.classList.add('is-open');
    fetch('/teacher/grade-assignment.php?submission_id=' + encodeURIComponent(id), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (!d.success) { ov.classList.remove('is-open'); toast('error', d.message || t('ag_err')); return; } current = d; render(d); })
      .catch(function () { ov.classList.remove('is-open'); toast('error', t('ag_err')); });
  }

  // The rubric grid: one block per criterion with level shortcuts, a points field and a comment
  function rubricBlock(s) {
    var wrap = el('div', 'ag-rb'), given = {};
    (s.rubric_scores || []).forEach(function (r) { given[r.id] = r; });
    var total = el('div', 'ag-rb-total'), inputs = [];
    function recompute() {
      var sum = 0; inputs.forEach(function (i) { var v = num(i.value); if (v !== null) sum += v; });
      total.textContent = t('ag_rubric_total', { n: fmt(sum), max: fmt(s.max) });
    }
    s.rubric.forEach(function (c) {
      var box = el('div', 'ag-rb-c');
      var head = el('div', 'ag-rb-h'); head.appendChild(el('strong', '', c.title)); head.appendChild(el('span', 'ag-rb-m', '/ ' + fmt(c.max)));
      box.appendChild(head);
      if (c.description) box.appendChild(el('p', 'ag-rb-d', c.description));
      var pts = el('input'); pts.type = 'text'; pts.inputMode = 'decimal'; pts.autocomplete = 'off'; pts.className = 'ag-rb-p'; pts.dataset.cid = c.id; pts.setAttribute('aria-label', c.title + ' (' + t('ag_rubric_points') + ')');
      pts.value = given[c.id] ? fmt(given[c.id].points) : '';
      pts.addEventListener('input', recompute);
      inputs.push(pts);
      var line = el('div', 'ag-rb-l');
      (c.levels || []).forEach(function (lv) {
        var b = el('button', 'ag-rb-lv', lv.label + ' · ' + fmt(lv.points)); b.type = 'button'; if (lv.description) b.title = lv.description;
        b.addEventListener('click', function () { pts.value = fmt(lv.points); recompute(); });
        line.appendChild(b);
      });
      line.appendChild(pts);
      box.appendChild(line);
      var cm = el('input'); cm.type = 'text'; cm.maxLength = 500; cm.className = 'ag-rb-cm'; cm.dataset.cid = c.id; cm.placeholder = t('ag_rubric_comment');
      cm.value = given[c.id] ? (given[c.id].comment || '') : '';
      box.appendChild(cm);
      wrap.appendChild(box);
    });
    wrap.appendChild(total); recompute();
    return wrap;
  }
  function rubricValues(body) {
    var out = {};
    body.querySelectorAll('.ag-rb-p').forEach(function (i) { out[i.dataset.cid] = { points: i.value, comment: '' }; });
    body.querySelectorAll('.ag-rb-cm').forEach(function (i) { if (out[i.dataset.cid]) out[i.dataset.cid].comment = i.value; });
    return out;
  }

  function render(d) {
    var s = d.submission, body = $('ag-body'), hasRubric = s.rubric && s.rubric.length;
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
    if (s.similarity) { var sm = row(t('asg_sim_col'), t('ag_sim', { score: fmt(s.similarity.score), name: s.similarity.with })); if (s.similarity.score >= 60) sm.classList.add('is-bad'); body.appendChild(sm); }
    if (s.held) body.appendChild(el('p', 'ag-held', t('ag_held')));
    else if (s.released_at && s.release_mode === 'manual') body.appendChild(el('p', 'ag-released', t('ag_released', { date: s.released_at })));

    var form = el('div', 'ag-form'), in1 = null;
    if (hasRubric) {
      form.appendChild(el('label', '', t('ag_rubric')));
      form.appendChild(rubricBlock(s));
    } else {
      var l1 = el('label', '', t('ag_score', { max: fmt(s.max) })); l1.htmlFor = 'ag-score';
      in1 = el('input'); in1.id = 'ag-score'; in1.type = 'text'; in1.inputMode = 'decimal'; in1.autocomplete = 'off';
      in1.value = s.score !== null ? fmt(s.score) : '';
      form.appendChild(l1); form.appendChild(in1);
    }
    var l2 = el('label', '', t('ag_feedback')); l2.htmlFor = 'ag-feedback';
    var ta = el('textarea'); ta.id = 'ag-feedback'; ta.maxLength = 5000; ta.value = s.feedback || '';
    form.appendChild(l2); form.appendChild(ta);

    // The annotated copy
    var l3 = el('label', '', t('ag_fbfile')); l3.htmlFor = 'ag-fbfile';
    var fin = el('input'); fin.id = 'ag-fbfile'; fin.type = 'file'; fin.accept = '.pdf,.docx,.doc,.odt,.txt,.md,.png,.jpg,.jpeg,.zip';
    form.appendChild(l3); form.appendChild(fin); form.appendChild(el('small', 'ag-hint', t('ag_file_hint')));
    var rmBox = null;
    if (s.feedback_file_url) {
      var cur = el('div', 'ag-cur'); cur.appendChild(el('span', '', t('ag_file_current') + ' : ')); cur.appendChild(link(s.feedback_file_url, s.feedback_file_name || 'file'));
      var lab = el('label', 'ag-chk'); rmBox = el('input'); rmBox.type = 'checkbox'; lab.appendChild(rmBox); lab.appendChild(document.createTextNode(' ' + t('ag_file_remove')));
      cur.appendChild(lab); form.appendChild(cur);
    }

    var save = el('button', 'ag-btn primary', t('ag_save')); save.type = 'button';
    save.addEventListener('click', function () {
      save.disabled = true;
      var data = { action: 'grade', submission_id: s.id, feedback: ta.value };
      if (hasRubric) data.rubric_json = JSON.stringify(rubricValues(body)); else data.score = in1.value;
      if (rmBox && rmBox.checked) data.remove_file = '1';
      post(data, fin.files && fin.files[0] ? fin.files[0] : null).then(function (r) {
        save.disabled = false;
        if (!r.success) { toast('error', errorText(r.error, s.max)); return; }
        toast('success', r.released === false ? t('ag_saved_held') : t('ag_saved')); setTimeout(function () { location.reload(); }, 700);
      });
    });
    form.appendChild(save);
    if (s.held) {
      var rel = el('button', 'ag-btn', t('ag_release_one')); rel.type = 'button';
      rel.addEventListener('click', function () {
        rel.disabled = true;
        post({ action: 'release', submission_id: s.id }).then(function (r) {
          rel.disabled = false;
          if (!r.success) { toast('error', t('ag_err')); return; }
          toast('success', t('ag_release_ok')); setTimeout(function () { location.reload(); }, 700);
        });
      });
      form.appendChild(rel);
    }
    body.appendChild(form);

    var rev = el('details', 'ag-rev'), sum = el('summary', '', t('ag_revision')); rev.appendChild(sum);
    var l4 = el('label', '', t('ag_revision_note')); l4.htmlFor = 'ag-note';
    var ta2 = el('textarea'); ta2.id = 'ag-note'; ta2.maxLength = 500;
    var send = el('button', 'ag-btn', t('ag_revision_send')); send.type = 'button';
    send.addEventListener('click', function () {
      if (ta2.value.trim().length < 5) { toast('error', t('ag_note_needed')); return; }
      send.disabled = true;
      post({ action: 'revision', submission_id: s.id, note: ta2.value }).then(function (r) {
        send.disabled = false;
        if (!r.success) { toast('error', t('ag_err')); return; }
        toast('success', t('ag_revision_done')); setTimeout(function () { location.reload(); }, 600);
      });
    });
    rev.appendChild(l4); rev.appendChild(ta2); rev.appendChild(send);
    body.appendChild(rev);

    if (d.history && d.history.length) {
      var h = el('div', 'ag-hist'); h.appendChild(el('strong', '', t('ag_history')));
      d.history.forEach(function (v) {
        h.appendChild(el('p', '', t('ag_version_n', { n: v.attempt }) + ' · ' + v.submitted_at + (v.score !== null ? ' · ' + fmt(parseFloat(v.score)) + ' / ' + fmt(s.max) : '') + (v.file_name ? ' · ' + v.file_name : '') + (v.feedback_file_name ? ' · ✎ ' + v.feedback_file_name : '')));
      });
      body.appendChild(h);
    }
    var first = $('ag-score') || body.querySelector('.ag-rb-p'); if (first) first.focus();
  }

  // ── Release of marks (buttons on the assignment card) ───────────────────────────────────────────────────────────────────────
  function releaseAll(lessonId, n) {
    if (!window.confirm(t('asg_release_confirm', { n: n }))) return;
    post({ action: 'release_all', lesson_id: lessonId }).then(function (r) {
      if (!r.success) { toast('error', t('ag_err')); return; }
      toast('success', t('asg_release_done', { n: r.released })); setTimeout(function () { location.reload(); }, 800);
    });
  }
  function withdrawAll(lessonId) {
    if (!window.confirm(t('asg_withdraw_confirm'))) return;
    post({ action: 'withdraw_all', lesson_id: lessonId }).then(function (r) {
      if (!r.success) { toast('error', t('ag_err')); return; }
      toast('success', t('asg_withdraw_done')); setTimeout(function () { location.reload(); }, 800);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var ov = $('ag-overlay'); if (!ov) return;
    $('ag-close').addEventListener('click', function () { ov.classList.remove('is-open'); });
    ov.addEventListener('click', function (e) { if (e.target === ov) ov.classList.remove('is-open'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') ov.classList.remove('is-open'); });
  });
  window.AsgGrade = { open: open, releaseAll: releaseAll, withdrawAll: withdrawAll };

  // ── Similarity check ───────────────────────────────────────────────────────────────────────────────────────────────────────
  function simPanel(lessonId) { return $('asg-sim-' + lessonId); }
  function renderSim(lessonId, d) {
    var box = simPanel(lessonId); if (!box) return;
    box.textContent = ''; box.hidden = false;
    var a = d.analysis;
    if (a) {
      box.appendChild(el('p', 'sim-sum', t('asg_sim_summary', { a: a.analysed, p: a.flagged })));
      if (a.skipped && a.skipped.length) {
        var why = {}; a.skipped.forEach(function (x) { why[x.reason] = (why[x.reason] || 0) + 1; });
        box.appendChild(el('p', 'sim-skip', t('asg_sim_skipped', { n: a.skipped.length, why: Object.keys(why).map(function (k) { return why[k] + ' ' + t('asg_sim_r_' + k); }).join(', ') })));
      }
      if (a.ignored_common) box.appendChild(el('p', 'sim-skip', t('asg_sim_ignored', { n: a.ignored_common })));
    }
    if (d.checked_at) box.appendChild(el('p', 'sim-skip', t('asg_sim_checked', { date: d.checked_at })));
    if (d.stale) box.appendChild(el('p', 'sim-stale', t('asg_sim_stale')));
    if (!d.pairs.length) { box.appendChild(el('p', 'sim-none', t('asg_sim_none'))); }
    d.pairs.forEach(function (p) {
      var det = el('details', 'sim-pair sim-' + p.level), sm = el('summary');
      sm.appendChild(el('span', 'sim-score', fmt(p.score) + ' %'));
      sm.appendChild(el('span', 'sim-lv', t('asg_sim_' + p.level)));
      sm.appendChild(el('span', 'sim-who', p.name_a + (p.mat_a ? ' (' + p.mat_a + ')' : '') + '  ↔  ' + p.name_b + (p.mat_b ? ' (' + p.mat_b + ')' : '')));
      det.appendChild(sm);
      if (p.excerpts && p.excerpts.length) {
        det.appendChild(el('strong', 'sim-ph', t('asg_sim_passages')));
        p.excerpts.forEach(function (x) { det.appendChild(el('blockquote', '', x)); });
      }
      box.appendChild(det);
    });
    box.appendChild(el('p', 'sim-note', t('asg_sim_note')));
  }
  function simShow(lessonId) {
    fetch('/teacher/assignment-similarity.php?lesson_id=' + encodeURIComponent(lessonId), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(function (d) { if (d.success && (d.checked_at || d.pairs.length)) renderSim(lessonId, d); }).catch(function () {});
  }
  function simRun(lessonId, btn) {
    var box = simPanel(lessonId); if (box) { box.hidden = false; box.textContent = t('asg_sim_running'); }
    if (btn) btn.disabled = true;
    var fd = new FormData(); fd.append('lesson_id', lessonId);
    fetch('/teacher/assignment-similarity.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (btn) btn.disabled = false; if (!d.success) { if (box) box.textContent = d.message || t('asg_sim_err'); return; } renderSim(lessonId, d); })
      .catch(function () { if (btn) btn.disabled = false; if (box) box.textContent = t('asg_sim_err'); });
  }
  window.AsgSim = { run: simRun, show: simShow };
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-sim-lesson]').forEach(function (b) { if (b.dataset.simChecked === '1') simShow(b.dataset.simLesson); });
  });
})();
