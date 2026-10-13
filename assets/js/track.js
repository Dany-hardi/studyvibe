/* Tracking page: link request, interview answers, withdraw and erase. */
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var C = window.TK || { t: '', L: {} }, L = C.L;
  function post(data) {
    return fetch('/track-action.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(data) })
      .then(function (r) { return r.json().catch(function () { return { success: false, error: 'server' }; }); })
      .catch(function () { return { success: false, error: 'network' }; });
  }
  var msg = function (code) { return L[code] || L.err; };

  /* ---- ask for the link ---- */
  var f = $('#tk-lookup');
  if (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var err = $('#tk-err'), btn = $('#tk-go'); err.textContent = ''; err.classList.remove('show');
      btn.disabled = true;
      post({ action: 'lookup', reference: $('#tk-ref').value, email: $('#tk-email').value }).then(function (d) {
        btn.disabled = false;
        if (d.success) { f.hidden = true; $('#tk-sent').hidden = false; $('#tk-sent').focus && $('#tk-sent').setAttribute('tabindex', '-1'); $('#tk-sent').focus(); return; }
        err.textContent = msg(d.error); err.classList.add('show');
      });
    });
  }
  if (!C.t) return;

  /* ---- answer the interview ---- */
  var act = $('#tk-iv-act'), form = $('#tk-resp-form'), pending = null, ivId = $('#interview') ? +$('#interview').dataset.iv : 0;
  function answer(kind, note) {
    var go = $('#tk-resp-go'); if (go) go.disabled = true;
    return post({ action: 'respond', t: C.t, interview_id: ivId, answer: kind, note: note || '' }).then(function (d) {
      if (go) go.disabled = false;
      if (d.success) { location.reload(); return; }
      var e = $('#tk-resp-err'); if (e) { e.textContent = d.error === 'note_required' ? L.note_req : d.error === 'closed' ? L.closed : msg(d.error); e.classList.add('show'); form.hidden = false; }
    });
  }
  if (act) {
    $$('[data-resp]', act).forEach(function (b) {
      b.addEventListener('click', function () {
        pending = b.dataset.resp;
        if (pending === 'confirm') { b.disabled = true; answer('confirm'); return; }
        form.hidden = false; $('#tk-resp-err').classList.remove('show');
        $('#tk-note').focus();
        $('#tk-resp-go').textContent = b.textContent;
      });
    });
    form.addEventListener('submit', function (e) { e.preventDefault(); if (pending) answer(pending, $('#tk-note').value); });
    $('#tk-resp-x').addEventListener('click', function () { form.hidden = true; pending = null; });
  }

  /* ---- withdraw / erase ---- */
  $$('[data-dlg]').forEach(function (b) { b.addEventListener('click', function () { var d = $('#dlg-' + b.dataset.dlg); if (d) d.showModal(); }); });
  $$('.tk-dialog').forEach(function (d) {
    d.addEventListener('close', function () {
      if (d.returnValue !== 'ok') return;
      var kind = d.id === 'dlg-erase' ? 'erase' : 'withdraw';
      post({ action: kind, t: C.t }).then(function (r) {
        if (!r.success) { alert(msg(r.error)); return; }
        if (kind === 'withdraw') { location.reload(); return; }
        document.querySelector('main').innerHTML = '<section class="tk-lookup"><div class="ca-wrap" style="text-align:center;padding:80px 0"><h1 style="font-size:2.2rem">' + L.erased_h + '</h1><p class="ca-lede" style="margin:1rem auto 2rem">' + L.erased_p + '</p><a class="btn btn-primary btn-lg" href="/careers.php">' + L.back + '</a></div></section>';
        scrollTo({ top: 0 });
      });
    });
  });
  if (location.hash === '#interview' && $('#interview')) setTimeout(function () { $('#interview').scrollIntoView({ block: 'start' }); }, 80);
})();
