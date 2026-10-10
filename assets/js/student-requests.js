/*
 * Student dashboard: writing to a teacher. Buttons with data-req open one dialog:
 *   data-req="assignment"  data-ref=<submission id>      review of a released mark
 *   data-req="live_result" data-ref=<registration id>    question about an evaluation result
 *   data-req="course"                                    a free message (pick the course, give a subject)
 * The server (api/requests.php) checks everything again. Everything shown is put in with textContent.
 */
(function () {
  'use strict';
  function T(k) { return (window.SV_T && window.SV_T[k]) || k; }
  function byId(id) { return document.getElementById(id); }

  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-req]') : null;
    if (!b) return;
    var kind = b.dataset.req;
    byId('req-kind').value = kind; byId('req-ref').value = b.dataset.ref || '';
    byId('req-title').textContent = T('req_h_' + kind);
    byId('req-about').textContent = b.dataset.title || '';
    byId('req-course-wrap').classList.toggle('hidden', kind !== 'course');
    byId('req-subject-wrap').classList.toggle('hidden', kind !== 'course');
    byId('req-hint').textContent = kind === 'assignment' ? T('req_hint_assignment') : (kind === 'live_result' ? T('req_hint_live') : T('req_hint'));
    byId('req-message').value = ''; byId('req-subject').value = '';
    var err = byId('req-error'); err.classList.add('hidden'); err.textContent = '';
    openModal('req-modal');
  });

  window.submitRequest = function (ev) {
    ev.preventDefault();
    var kind = byId('req-kind').value, msg = byId('req-message').value.trim(), btn = byId('req-send'), err = byId('req-error');
    function fail(m) { err.textContent = m; err.classList.remove('hidden'); }
    if (msg.length < 20) { fail(T('req_short')); return; }
    if (kind === 'course' && byId('req-subject').value.trim().length < 3) { fail(T('req_need_subject')); return; }
    var fd = new FormData(); fd.append('kind', kind); fd.append('message', msg);
    if (kind === 'course') { fd.append('course_id', byId('req-course').value); fd.append('subject', byId('req-subject').value.trim()); } else { fd.append('ref_id', byId('req-ref').value); }
    btn.disabled = true; btn.textContent = T('req_sending'); err.classList.add('hidden');
    fetch('/api/requests.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      btn.disabled = false; btn.textContent = T('req_send');
      if (!d.success) { fail(d.message || T('req_err')); return; }
      closeModal('req-modal');
      if (window.Toast && Toast.success) Toast.success(T('req_sent'));
      setTimeout(function () { location.hash = '#results'; location.reload(); }, 700);
    }).catch(function () { btn.disabled = false; btn.textContent = T('req_send'); fail(T('req_err')); });
  };
})();
