/*
 * Teacher dashboard, "Questions et modération" tab: answering the requests of students (POST /teacher/requests.php, action=answer).
 * Everything shown is put in with textContent.
 */
(function () {
  'use strict';
  var T = window.SV_T || {};
  var toast = function (kind, msg) { if (window.Toast && Toast[kind]) Toast[kind](msg); };
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.rq-send') : null;
    if (!btn) return;
    var card = btn.closest('[data-rq]'), ta = card.querySelector('.rq-answer'), text = ta.value.trim();
    if (text.length < 10) { toast('error', T.rq_short || ''); ta.focus(); return; }
    btn.disabled = true;
    var fd = new FormData(); fd.append('action', 'answer'); fd.append('id', card.dataset.rq); fd.append('response', text);
    fetch('/teacher/requests.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      btn.disabled = false;
      if (!d.success) { toast('error', d.message || T.rq_err || ''); return; }
      toast('success', T.rq_sent || d.message);
      var done = document.createElement('div'); done.className = 'rq-resp';
      var b = document.createElement('strong'); b.textContent = T.rq_your_answer || ''; done.appendChild(b); done.appendChild(document.createElement('br')); done.appendChild(document.createTextNode(text));
      card.classList.remove('is-open'); card.classList.add('is-done');
      card.querySelectorAll('.rq-answer, .rq-send, .rq-hint').forEach(function (n) { n.remove(); });
      card.appendChild(done);
    }).catch(function () { btn.disabled = false; toast('error', T.rq_err || ''); });
  });
})();
