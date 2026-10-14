/*
 * Free-pace exam room: common start, every student goes at their own speed, hands everything in at the end.
 * Server side: api/live-eval-paced.php (lib/LivePaced.php). The browser only displays: the clocks, the locks and the final submission are
 * decided by the server.
 */
(function () {
  'use strict';
  var PR = window.PR || {};
  var $ = function (id) { return document.getElementById(id); };
  var T = {
    fr: {
      loading: 'Chargement de votre évaluation…',
      lobby_kicker: 'Évaluation en direct', lobby_lede: 'Le départ est commun : l\'évaluation s\'ouvrira pour tout le monde au même instant.',
      lobby_rules: [
        'Vous répondez dans l\'ordre que vous voulez, vous pouvez passer une question et y revenir.',
        'Vos réponses sont enregistrées au fur et à mesure ; rien n\'est envoyé tant que vous n\'avez pas cliqué sur « Soumettre toutes mes réponses ».',
        'Les QCM et les réponses courtes ou rédigées ont un temps propre qui ne s\'écoule que lorsque la question est ouverte.',
        'Les questions de code n\'ont aucune limite de temps : l\'évaluation s\'arrête pour tous à l\'heure de fin indiquée en haut de l\'écran.'
      ],
      submit_all: 'Soumettre toutes mes réponses', count: '{a} / {n} répondues', end_in: 'Fin dans {t}', no_end: 'Pas d\'heure de fin',
      saved: 'Enregistré {t}', saving: 'Enregistrement…', save_err: 'Non enregistré, nouvelle tentative…', offline: 'Hors ligne : sauvegardé sur cet appareil',
      q_of: 'Question {n} / {m}', type_mcq: 'QCM', type_written: 'Réponse courte', type_open: 'Question rédigée', type_code: 'Question de code',
      timer_left: 'Temps restant', timer_done: 'Temps écoulé', timer_free: 'Sans limite de temps', locked_msg: 'Le temps de cette question est écoulé. Votre dernière réponse enregistrée est conservée.',
      prev: 'Précédent', skip: 'Passer', next: 'Suivant', clear: 'Effacer ma réponse', figure_zoom: 'Agrandir la figure',
      ph_written: 'Votre réponse', ph_open: 'Rédigez votre réponse ici…', words: '{n} mot(s)', chars: '{n} caractères',
      code_title: 'Votre code', code_empty: 'Aucun code écrit pour l\'instant.', code_lines: '{n} ligne(s) écrite(s)', code_open: 'Écrire mon code', code_resume: 'Reprendre mon code',
      code_nolimit: 'Aucune limite de temps pour cette question', code_hint: 'Votre code est enregistré automatiquement. Cliquez sur le bouton pour ouvrir l\'éditeur : la question et la figure y restent visibles.',
      code_done: 'Enregistrer et fermer', code_format: 'Formater', code_ref: 'Énoncé et figure', code_ok: '✓ Aucun problème détecté', code_errors: '{n} erreur(s)', code_warnings: '{n} avertissement(s)',
      code_formatted: 'Code formaté', code_count: '{n} / {m} caractères', code_keys: 'Tab : indenter · Maj+Tab : désindenter · Alt+Maj+F : formater · Échap : fermer',
      sev_error: 'Erreur', sev_warning: 'Avertissement', sev_info: 'Remarque', line: 'ligne {n}',
      review_title: 'Des questions n\'ont pas de réponse', review_lede: 'Vous pouvez les compléter maintenant, ou envoyer votre évaluation telle quelle. Une question sans réponse compte pour zéro.',
      review_back: 'Revenir aux questions', review_force: 'Soumettre quand même', answer_it: 'Répondre',
      confirm_title: 'Soumettre définitivement ?', confirm_all: 'Toutes vos questions ont une réponse. Une fois envoyée, votre évaluation ne peut plus être modifiée.',
      confirm_some: '{n} question(s) restent sans réponse et compteront pour zéro. Une fois envoyée, votre évaluation ne peut plus être modifiée.',
      confirm_yes: 'Oui, soumettre', confirm_no: 'Non, continuer',
      done_title: 'Vos réponses ont été envoyées', done_lede: 'Merci ! Vous avez répondu à {a} question(s) sur {n}.',
      done_ai: 'Vos réponses de code et vos réponses rédigées sont corrigées par l\'IA, cela peut prendre quelques minutes. Vous les retrouverez avec leur correction dans votre rapport.',
      done_results: 'Voir mon rapport', done_leave: 'Quitter', closed_title: 'La séance est terminée', closed_lede: 'Le temps imparti est écoulé : vos réponses enregistrées ont été envoyées.',
      net_err: 'Connexion perdue. Vos réponses restent enregistrées sur cet appareil et partiront dès que le réseau revient.', unload: 'Des réponses ne sont pas encore enregistrées. Quitter quand même ?',
      nav_aria: 'Liste des questions', lg_answered: 'Répondue', lg_skipped: 'Passée', lg_locked: 'Temps écoulé',
      lobby_title_in: 'Départ dans'
    },
    en: {
      loading: 'Loading your evaluation…',
      lobby_kicker: 'Live evaluation', lobby_lede: 'Everybody starts together: the evaluation opens for everyone at the same moment.',
      lobby_rules: [
        'Answer in any order you like; you can skip a question and come back to it.',
        'Your answers are saved as you go; nothing is sent until you click “Submit all my answers”.',
        'Multiple-choice, short and written-answer questions have their own time, which only runs while the question is open.',
        'Code questions have no time limit: the evaluation stops for everyone at the end time shown at the top of the screen.'
      ],
      submit_all: 'Submit all my answers', count: '{a} / {n} answered', end_in: 'Ends in {t}', no_end: 'No end time',
      saved: 'Saved {t}', saving: 'Saving…', save_err: 'Not saved, retrying…', offline: 'Offline: kept on this device',
      q_of: 'Question {n} / {m}', type_mcq: 'Multiple choice', type_written: 'Short answer', type_open: 'Written answer', type_code: 'Code question',
      timer_left: 'Time left', timer_done: 'Time is up', timer_free: 'No time limit', locked_msg: 'Time is up for this question. Your last saved answer is kept.',
      prev: 'Previous', skip: 'Skip', next: 'Next', clear: 'Clear my answer', figure_zoom: 'Enlarge the figure',
      ph_written: 'Your answer', ph_open: 'Write your answer here…', words: '{n} word(s)', chars: '{n} characters',
      code_title: 'Your code', code_empty: 'No code written yet.', code_lines: '{n} line(s) written', code_open: 'Write my code', code_resume: 'Resume my code',
      code_nolimit: 'No time limit for this question', code_hint: 'Your code is saved automatically. Click the button to open the editor: the question and the figure stay visible there.',
      code_done: 'Save and close', code_format: 'Format', code_ref: 'Statement and figure', code_ok: '✓ No problem found', code_errors: '{n} error(s)', code_warnings: '{n} warning(s)',
      code_formatted: 'Code formatted', code_count: '{n} / {m} characters', code_keys: 'Tab: indent · Shift+Tab: outdent · Alt+Shift+F: format · Esc: close',
      sev_error: 'Error', sev_warning: 'Warning', sev_info: 'Note', line: 'line {n}',
      review_title: 'Some questions have no answer', review_lede: 'You can complete them now, or hand in your evaluation as it is. An unanswered question scores zero.',
      review_back: 'Back to the questions', review_force: 'Submit anyway', answer_it: 'Answer',
      confirm_title: 'Submit for good?', confirm_all: 'Every question has an answer. Once sent, your evaluation can no longer be changed.',
      confirm_some: '{n} question(s) have no answer and will score zero. Once sent, your evaluation can no longer be changed.',
      confirm_yes: 'Yes, submit', confirm_no: 'No, keep going',
      done_title: 'Your answers have been sent', done_lede: 'Thank you! You answered {a} question(s) out of {n}.',
      done_ai: 'Your code and written answers are marked by AI, which can take a few minutes. You will find them with their correction in your report.',
      done_results: 'View my report', done_leave: 'Leave', closed_title: 'The session is over', closed_lede: 'The time is up: your saved answers have been sent.',
      net_err: 'Connection lost. Your answers stay saved on this device and will be sent as soon as the network is back.', unload: 'Some answers are not saved yet. Leave anyway?',
      nav_aria: 'Question list', lg_answered: 'Answered', lg_skipped: 'Skipped', lg_locked: 'Time is up',
      lobby_title_in: 'Starting in'
    }
  };
  var L = T[PR.lang === 'en' ? 'en' : 'fr'];
  if (window.CodeTools) window.CodeTools.setLang(PR.lang === 'en' ? 'en' : 'fr');
  function f(s, v) { s = String(s); if (v) for (var k in v) s = s.split('{' + k + '}').join(String(v[k])); return s; }
  function fmtClock(sec) { sec = Math.max(0, Math.floor(sec)); var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60; return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0'); }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }

  /* ── state ─────────────────────────────────────────────────────────────────────────────── */
  var S = { qs: [], cur: 0, visited: {}, submitted: false, endLeft: null, startLeft: null, timers: {}, lastTick: Date.now(), net: true, dirty: {}, saveTimers: {}, retry: {}, codeQ: null };
  var views = ['pr-loading', 'pr-lobby', 'pr-exam', 'pr-review', 'pr-done'];
  function show(id) { views.forEach(function (v) { $(v).hidden = v !== id; }); $('pr-top').hidden = !(id === 'pr-exam' || id === 'pr-review'); window.scrollTo(0, 0); }

  /* ── network ──────────────────────────────────────────────────────────────────────────── */
  function api(action, data) {
    var fd = new FormData();
    fd.append('code', PR.code); fd.append('action', action);
    for (var k in (data || {})) fd.append(k, data[k]);
    return fetch('/api/live-eval-paced.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { S.net = true; return r.json().catch(function () { return { ok: false, error: 'bad' }; }); })
      .catch(function () { S.net = false; return { ok: false, error: 'network' }; });
  }
  function lsKey(q) { return 'pr_' + PR.code + '_' + q.id; }
  function lsPut(q) { try { localStorage.setItem(lsKey(q), JSON.stringify({ a: q.answer, t: Date.now() })); } catch (e) {} }
  function lsGet(q) { try { var v = JSON.parse(localStorage.getItem(lsKey(q)) || 'null'); return v && typeof v.a === 'string' ? v : null; } catch (e) { return null; } }
  function lsClear() { try { Object.keys(localStorage).forEach(function (k) { if (k.indexOf('pr_' + PR.code + '_') === 0) localStorage.removeItem(k); }); } catch (e) {} }

  /* ── boot ─────────────────────────────────────────────────────────────────────────────── */
  $('pr-loading-t').textContent = L.loading;
  function boot() {
    api('state').then(function (d) {
      if (!d.ok) {
        if (d.error === 'not_registered') { location.href = '/live-session.php?code=' + encodeURIComponent(PR.code); return; }
        $('pr-loading-t').textContent = d.error === 'network' ? L.net_err : 'Erreur.';
        setTimeout(boot, 3000); return;
      }
      S.data = d;
      $('pr-title').textContent = d.title; $('pr-course').textContent = d.course;
      if (d.submitted) { showDone(null, true); return; }
      if (d.window === 'waiting') { showLobby(d); return; }
      if (d.window === 'closed') { api('ping').then(function () { boot2closed(); }); return; }
      startExam(d);
    });
  }
  function boot2closed() { api('state').then(function (d) { showDone(null, true, true); }); }

  /* ── lobby ────────────────────────────────────────────────────────────────────────────── */
  function showLobby(d) {
    show('pr-lobby');
    $('pr-lobby-kicker').textContent = L.lobby_kicker; $('pr-lobby-title').textContent = d.title; $('pr-lobby-lede').textContent = L.lobby_lede;
    var ul = $('pr-rules'); ul.textContent = '';
    L.lobby_rules.forEach(function (r) { ul.appendChild(el('li', '', r)); });
    S.startLeft = d.clock.seconds_to_start; var t0 = Date.now();
    var iv = setInterval(function () {
      var left = S.startLeft - (Date.now() - t0) / 1000;
      $('pr-lobby-count').textContent = fmtClock(left);
      if (left <= 0) { clearInterval(iv); setTimeout(boot, 400); }
    }, 250);
  }

  /* ── exam ─────────────────────────────────────────────────────────────────────────────── */
  function startExam(d) {
    S.qs = d.questions;
    S.qs.forEach(function (q) {
      q.left = q.limit == null ? null : Math.max(0, q.limit - q.used);
      q.saved = q.answer;
      var b = lsGet(q);
      if (b && q.answer === '' && b.a !== '' && !q.locked) { q.answer = b.a; markDirty(q); }
      if (q.used > 0 || q.answered) S.visited[q.id] = true;
    });
    S.endLeft = d.clock.seconds_to_end; S.endAt = S.endLeft == null ? null : Date.now() + S.endLeft * 1000;
    var idx = 0;
    if (d.current) { S.qs.forEach(function (q, i) { if (q.id === d.current) idx = i; }); }
    $('pr-submit-all').textContent = L.submit_all;
    $('pr-nav').setAttribute('aria-label', L.nav_aria);
    show('pr-exam');
    goto(idx, true);
    setInterval(tick, 1000);
    setInterval(heartbeat, 10000);
    S.qs.forEach(function (q) { if (S.dirty[q.id]) scheduleSave(q, 200); });
  }

  function answeredCount() { return S.qs.filter(function (q) { return q.answer.trim() !== ''; }).length; }

  function goto(i, first) {
    if (i < 0 || i >= S.qs.length) return;
    var prev = S.qs[S.cur];
    if (prev && !first) { flushSave(prev); }
    S.cur = i;
    var q = S.qs[i];
    S.visited[q.id] = true;
    api('open', { question_id: q.id }).then(function (r) {
      if (r && r.ok && S.qs[S.cur] === q) { q.used = r.used; q.left = r.left; q.locked = r.locked; updateTimer(q); if (r.locked) lockUi(q); }
      else if (r && (r.error === 'submitted' || r.error === 'closed')) { showDone(null, true, r.error === 'closed'); }
    });
    renderNav(); renderCard();
    if (!first) { $('pr-card').focus({ preventScroll: true }); window.scrollTo({ top: 0, behavior: 'smooth' }); }
  }

  function typeLabel(q) { return L['type_' + q.type] || q.type; }

  function renderNav() {
    var nav = $('pr-nav'); nav.textContent = '';
    var prog = el('div', 'pr-progress'); var bar = el('span'); bar.style.width = (S.qs.length ? answeredCount() / S.qs.length * 100 : 0) + '%'; prog.appendChild(bar); nav.appendChild(prog);
    var grid = el('div', 'pr-nav-grid');
    S.qs.forEach(function (q, i) {
      var b = el('button', 'pr-chipbtn', String(q.n)); b.type = 'button';
      var answered = q.answer.trim() !== '';
      if (answered) b.classList.add('is-answered'); else if (S.visited[q.id] && i !== S.cur) b.classList.add('is-skipped');
      if (q.locked) b.classList.add('is-locked');
      if (i === S.cur) { b.classList.add('is-current'); b.setAttribute('aria-current', 'step'); }
      if (q.type === 'code') b.appendChild(el('span', 'pr-ico', '</>'));
      b.setAttribute('aria-label', f(L.q_of, { n: q.n, m: S.qs.length }) + ' – ' + typeLabel(q) + (answered ? ' – ' + L.lg_answered : ''));
      b.addEventListener('click', function () { goto(i); });
      grid.appendChild(b);
    });
    nav.appendChild(grid);
    var lg = el('div', 'pr-legend');
    [['a', L.lg_answered], ['s', L.lg_skipped], ['l', L.lg_locked]].forEach(function (x) { var d = el('div'); var i = el('i', x[0]); d.appendChild(i); d.appendChild(document.createTextNode(x[1])); lg.appendChild(d); });
    nav.appendChild(lg);
    $('pr-count').textContent = f(L.count, { a: answeredCount(), n: S.qs.length });
  }

  function renderCard() {
    var q = S.qs[S.cur], card = $('pr-card'); card.textContent = '';
    var head = el('div', 'pr-qhead');
    head.appendChild(el('span', 'pr-qn', f(L.q_of, { n: q.n, m: S.qs.length })));
    head.appendChild(el('span', 'pr-badge' + (q.type === 'code' ? ' code' : ''), typeLabel(q)));
    var tm = el('span', 'pr-timer'); tm.id = 'pr-timer'; head.appendChild(tm);
    card.appendChild(head);
    var text = el('div', 'pr-qtext' + (q.type === 'code' || q.type === 'open' ? ' no-math' : ''), q.text); card.appendChild(text);
    if (q.image) {
      var fig = el('figure', 'pr-fig'); var img = el('img'); img.src = q.image; img.alt = ''; img.addEventListener('click', function () { zoom(q.image); });
      fig.appendChild(img); var cap = el('figcaption'); var zb = el('button', 'pr-link', L.figure_zoom); zb.type = 'button'; zb.addEventListener('click', function () { zoom(q.image); }); cap.appendChild(zb); fig.appendChild(cap); card.appendChild(fig);
    }
    var area = el('div', 'pr-answer'); area.id = 'pr-answer'; card.appendChild(area);
    if (q.type === 'mcq') renderMcq(q, area); else if (q.type === 'written') renderWritten(q, area); else if (q.type === 'open') renderOpen(q, area); else renderCodeBox(q, area);
    var lock = el('div', 'pr-lockmsg', L.locked_msg); lock.id = 'pr-lockmsg'; lock.hidden = true; card.appendChild(lock);
    var foot = el('div', 'pr-foot'); var left = el('div', 'pr-foot-l'); var right = el('div', 'pr-foot-r');
    var bp = el('button', 'pr-btn pr-btn-ghost', '← ' + L.prev); bp.type = 'button'; bp.disabled = S.cur === 0; bp.addEventListener('click', function () { goto(S.cur - 1); });
    var bs = el('button', 'pr-btn pr-btn-ghost', L.skip); bs.type = 'button'; bs.disabled = S.cur >= S.qs.length - 1; bs.addEventListener('click', function () { goto(S.cur + 1); });
    var bn = el('button', 'pr-btn pr-btn-main', L.next + ' →'); bn.type = 'button'; bn.disabled = S.cur >= S.qs.length - 1; bn.addEventListener('click', function () { goto(S.cur + 1); });
    left.appendChild(bp); right.appendChild(bs); right.appendChild(bn);
    if (S.cur >= S.qs.length - 1) { var bz = el('button', 'pr-btn pr-btn-main', L.submit_all); bz.type = 'button'; bz.addEventListener('click', startSubmit); right.appendChild(bz); }
    foot.appendChild(left); foot.appendChild(right); card.appendChild(foot);
    updateTimer(q); if (q.locked) lockUi(q);
    typeset(card);
  }

  function renderMcq(q, area) {
    var box = el('div', 'pr-opts'); box.setAttribute('role', 'radiogroup');
    q.options.forEach(function (txt, i) {
      if (String(txt).trim() === '') return;
      var key = q.option_keys[i];
      var b = el('button', 'pr-opt' + (q.answer === key ? ' is-picked' : '')); b.type = 'button'; b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', q.answer === key ? 'true' : 'false');
      b.appendChild(el('span', 'l', 'ABCD'[i])); b.appendChild(el('span', 't', txt));
      b.addEventListener('click', function () {
        if (q.locked) return;
        q.answer = q.answer === key ? '' : key; markDirty(q); scheduleSave(q, 0);
        box.querySelectorAll('.pr-opt').forEach(function (o, k) { var on = q.option_keys[k] === q.answer; o.classList.toggle('is-picked', on); o.setAttribute('aria-checked', on ? 'true' : 'false'); });
        renderNav();
      });
      box.appendChild(b);
    });
    area.appendChild(box);
  }
  function renderWritten(q, area) {
    var inp = el('input', 'pr-field'); inp.type = 'text'; inp.maxLength = 255; inp.value = q.answer; inp.placeholder = L.ph_written; inp.id = 'pr-input'; inp.autocomplete = 'off';
    inp.addEventListener('input', function () { q.answer = inp.value; markDirty(q); scheduleSave(q, 700); renderNavLight(); });
    area.appendChild(inp);
  }
  function renderOpen(q, area) {
    var ta = el('textarea', 'pr-field'); ta.value = q.answer; ta.placeholder = L.ph_open; ta.id = 'pr-input'; ta.maxLength = PR.max_code;
    var hint = el('div', 'pr-hint'); var w = el('span'); var c = el('span'); hint.appendChild(w); hint.appendChild(c);
    function upd() { var words = ta.value.trim() === '' ? 0 : ta.value.trim().split(/\s+/).length; w.textContent = f(L.words, { n: words }); c.textContent = f(L.chars, { n: ta.value.length }); }
    ta.addEventListener('input', function () { q.answer = ta.value; markDirty(q); scheduleSave(q, 900); upd(); renderNavLight(); });
    upd(); area.appendChild(ta); area.appendChild(hint);
  }
  function renderCodeBox(q, area) {
    var box = el('div', 'pr-codebox');
    var top = el('div', 'pr-codebox-top');
    top.appendChild(el('span', 'pr-chip', q.profile ? q.profile.label : q.code_lang));
    var info = el('span', ''); info.id = 'pr-code-info'; top.appendChild(info);
    box.appendChild(top);
    var prev = el('pre', 'pr-codeprev'); prev.id = 'pr-code-prev'; box.appendChild(prev);
    var btn = el('button', 'pr-btn pr-btn-main'); btn.type = 'button'; btn.id = 'pr-code-open'; btn.addEventListener('click', function () { openCode(q); });
    box.appendChild(btn);
    box.appendChild(el('p', 'pr-note', L.code_hint));
    area.appendChild(box);
    refreshCodeBox(q);
  }
  function refreshCodeBox(q) {
    var info = $('pr-code-info'), prev = $('pr-code-prev'), btn = $('pr-code-open'); if (!info) return;
    var has = q.answer.trim() !== '';
    var lines = has ? q.answer.split('\n').length : 0;
    info.textContent = has ? f(L.code_lines, { n: lines }) : L.code_empty;
    prev.hidden = !has; prev.textContent = has ? q.answer.split('\n').slice(0, 7).join('\n') : '';
    btn.textContent = has ? L.code_resume : L.code_open;
  }
  function renderNavLight() { var c = answeredCount(); $('pr-count').textContent = f(L.count, { a: c, n: S.qs.length }); var bar = document.querySelector('.pr-progress > span'); if (bar) bar.style.width = (c / S.qs.length * 100) + '%'; var chip = document.querySelectorAll('.pr-chipbtn')[S.cur]; if (chip) chip.classList.toggle('is-answered', S.qs[S.cur].answer.trim() !== ''); }

  /* ── timers ───────────────────────────────────────────────────────────────────────────── */
  function updateTimer(q) {
    var tm = $('pr-timer'); if (!tm || S.qs[S.cur] !== q) return;
    tm.className = 'pr-timer';
    if (q.limit == null) { tm.classList.add('is-free'); tm.textContent = L.timer_free; return; }
    if (q.locked || q.left <= 0) { tm.classList.add('is-done'); tm.textContent = L.timer_done; return; }
    if (q.left <= 30) tm.classList.add('is-low');
    tm.textContent = '⏱ ' + fmtClock(q.left);
    tm.title = L.timer_left;
  }
  function tick() {
    var now = Date.now(), dt = Math.round((now - S.lastTick) / 1000); S.lastTick = now; if (dt < 1) dt = 1;
    var q = S.qs[S.cur];
    if (q && q.limit != null && !q.locked && !S.submitted) {
      q.left = Math.max(0, q.left - dt); updateTimer(q);
      if (q.left <= 0) { q.locked = true; flushSave(q); lockUi(q); renderNav(); }
    }
    if (S.endAt != null) {
      var left = Math.max(0, (S.endAt - now) / 1000), e = $('pr-end');
      e.textContent = f(L.end_in, { t: fmtClock(left) }); e.classList.toggle('is-low', left < 300);
      if (left <= 0 && !S.submitted && !S.ending) { S.ending = true; heartbeat(); }
    } else { $('pr-end').textContent = L.no_end; }
  }
  function lockUi(q) {
    if (S.qs[S.cur] !== q) return;
    var a = $('pr-answer'); if (a) a.querySelectorAll('input, textarea, button.pr-opt').forEach(function (x) { x.disabled = true; });
    var m = $('pr-lockmsg'); if (m) m.hidden = false; updateTimer(q);
  }
  function heartbeat() {
    if (S.submitted) return;
    api('ping').then(function (r) {
      if (!r || !r.ok) return;
      if (r.submitted) { showDone(null, true, r.window === 'closed'); return; }
      if (r.clock && r.clock.seconds_to_end != null) S.endAt = Date.now() + r.clock.seconds_to_end * 1000;
      var q = S.qs[S.cur];
      if (r.timing && q && r.timing.limit != null) { q.used = r.timing.used; q.left = r.timing.left; if (r.timing.locked && !q.locked) { q.locked = true; lockUi(q); renderNav(); } updateTimer(q); }
    });
  }

  /* ── saving ───────────────────────────────────────────────────────────────────────────── */
  function markDirty(q) { S.dirty[q.id] = true; lsPut(q); setSave('saving'); }
  function scheduleSave(q, delay) { clearTimeout(S.saveTimers[q.id]); S.saveTimers[q.id] = setTimeout(function () { doSave(q); }, delay); }
  function flushSave(q) { if (S.dirty[q.id]) { clearTimeout(S.saveTimers[q.id]); doSave(q); } }
  function doSave(q) {
    if (!S.dirty[q.id]) return Promise.resolve();
    var sent = q.answer;
    return api('save', { question_id: q.id, answer: sent }).then(function (r) {
      if (r.ok) { if (q.answer === sent) { S.dirty[q.id] = false; q.saved = sent; } setSave('saved'); S.retry[q.id] = 0; if (r.left != null) { q.left = r.left; } return; }
      if (r.error === 'locked') { S.dirty[q.id] = false; q.locked = true; lockUi(q); renderNav(); setSave('saved'); return; }
      if (r.error === 'submitted' || r.error === 'closed') { S.dirty[q.id] = false; showDone(null, true, r.error === 'closed'); return; }
      setSave(r.error === 'network' ? 'offline' : 'err');
      S.retry[q.id] = Math.min((S.retry[q.id] || 0) + 1, 6);
      setTimeout(function () { if (S.dirty[q.id]) doSave(q); }, 1500 * S.retry[q.id]);
    });
  }
  function setSave(kind) {
    var t;
    if (kind === 'saving') t = L.saving; else if (kind === 'saved') t = f(L.saved, { t: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) });
    else if (kind === 'offline') t = L.offline; else t = L.save_err;
    ['pr-save', 'pr-code-save'].forEach(function (id) { var e = $(id); if (e) { e.textContent = t; e.classList.toggle('is-err', kind === 'err' || kind === 'offline'); } });
  }
  window.addEventListener('beforeunload', function (e) {
    if (S.submitted) return;
    var any = Object.keys(S.dirty).some(function (k) { return S.dirty[k]; });
    if (any) { e.preventDefault(); e.returnValue = L.unload; return L.unload; }
  });

  /* ── submission ───────────────────────────────────────────────────────────────────────── */
  function unansweredList() { return S.qs.filter(function (q) { return q.answer.trim() === ''; }); }
  function flushAll() { return Promise.all(S.qs.map(function (q) { return doSave(q); })); }
  function startSubmit() {
    flushAll().then(function () {
      var un = unansweredList();
      if (un.length) showReview(un); else askConfirm(0);
    });
  }
  function showReview(un) {
    $('pr-review-title').textContent = L.review_title; $('pr-review-lede').textContent = L.review_lede;
    var ul = $('pr-unanswered'); ul.textContent = '';
    un.forEach(function (q) {
      var li = el('li'); li.appendChild(el('span', 'n', '#' + q.n)); li.appendChild(el('span', 'pr-badge' + (q.type === 'code' ? ' code' : ''), typeLabel(q)));
      li.appendChild(el('span', 't', q.text.replace(/\s+/g, ' ').slice(0, 120)));
      var b = el('button', 'pr-btn pr-btn-ghost', L.answer_it); b.type = 'button'; b.addEventListener('click', function () { show('pr-exam'); goto(S.qs.indexOf(q)); }); li.appendChild(b); ul.appendChild(li);
    });
    $('pr-review-back').textContent = L.review_back; $('pr-review-force').textContent = L.review_force + ' (' + un.length + ')';
    show('pr-review');
  }
  $('pr-review-back').addEventListener('click', function () { show('pr-exam'); renderCard(); });
  $('pr-review-force').addEventListener('click', function () { askConfirm(unansweredList().length); });
  $('pr-submit-all').addEventListener('click', startSubmit);
  function askConfirm(n) {
    $('pr-confirm-title').textContent = L.confirm_title; $('pr-confirm-text').textContent = n ? f(L.confirm_some, { n: n }) : L.confirm_all;
    $('pr-confirm-yes').textContent = L.confirm_yes; $('pr-confirm-no').textContent = L.confirm_no; $('pr-confirm').hidden = false; $('pr-confirm-yes').focus();
    $('pr-confirm-yes').onclick = function () { $('pr-confirm').hidden = true; doSubmit(n > 0); };
    $('pr-confirm-no').onclick = function () { $('pr-confirm').hidden = true; if (!$('pr-review').hidden) return; };
  }
  function doSubmit(force) {
    $('pr-submit-all').disabled = true;
    api('submit', { confirm: force ? '1' : '0' }).then(function (r) {
      $('pr-submit-all').disabled = false;
      if (r.ok) { S.submitted = true; lsClear(); showDone(r, false); return; }
      if (r.error === 'unanswered') { showReview(S.qs.filter(function (q) { return r.unanswered.indexOf(q.id) !== -1; })); return; }
      if (r.error === 'network') { alert(L.net_err); return; }
      alert('Erreur : ' + (r.error || '?'));
    });
  }
  function showDone(r, again, closed) {
    S.submitted = true; show('pr-done'); $('pr-top').hidden = true; $('pr-code').hidden = true;
    $('pr-done-title').textContent = closed ? L.closed_title : L.done_title;
    var a = r && r.answered != null ? r.answered : answeredCount(), n = r && r.total != null ? r.total : S.qs.length;
    $('pr-done-lede').textContent = closed ? L.closed_lede : (n ? f(L.done_lede, { a: a, n: n }) : L.closed_lede);
    var hasMarked = S.qs.some(function (q) { return q.type === 'code' || q.type === 'open'; }) || (r && r.pending_ai > 0);
    $('pr-done-ai').textContent = hasMarked || !S.qs.length ? L.done_ai : ''; $('pr-done-ai').hidden = !$('pr-done-ai').textContent;
    var res = $('pr-done-results'); if (PR.results_url && !PR.guest) { res.href = PR.results_url; res.textContent = L.done_results; res.hidden = false; } else res.hidden = true;
    var lv = $('pr-done-leave'); lv.href = PR.leave_url; lv.textContent = L.done_leave;
  }

  /* ── figure zoom ──────────────────────────────────────────────────────────────────────── */
  function zoom(src) { $('pr-zoom-img').src = src; $('pr-zoom').hidden = false; }
  $('pr-zoom').addEventListener('click', function () { $('pr-zoom').hidden = true; });
  $('pr-zoom-close').addEventListener('click', function () { $('pr-zoom').hidden = true; });

  /* ── math ─────────────────────────────────────────────────────────────────────────────── */
  function typeset(node) {
    if (typeof window.renderMathInElement !== 'function') return;
    try { window.renderMathInElement(node, { delimiters: [{ left: '$$', right: '$$', display: true }, { left: '$', right: '$', display: false }, { left: '\\(', right: '\\)', display: false }, { left: '\\[', right: '\\]', display: true }], ignoredClasses: ['no-math', 'pr-field', 'pr-codeprev'], throwOnError: false }); } catch (e) {}
  }
  window.addEventListener('load', function () { if ($('pr-card')) typeset($('pr-card')); });

  /* ── code editor ──────────────────────────────────────────────────────────────────────── */
  var C = { q: null, lintTimer: null, phpTimer: null, phpErrors: [], problems: [] };
  var input = $('pr-code-input');
  function openCode(q) {
    C.q = q; C.phpErrors = [];
    $('pr-code-title').textContent = L.code_title; $('pr-code-lang').textContent = q.profile ? q.profile.label : q.code_lang;
    $('pr-code-nolimit').textContent = L.code_nolimit; $('pr-code-format').textContent = L.code_format; $('pr-code-done').textContent = L.code_done;
    $('pr-code-ref-sum').textContent = L.code_ref; $('pr-code-ref-text').textContent = q.text;
    var fig = $('pr-code-ref-fig'); fig.hidden = !q.image; if (q.image) { $('pr-code-ref-img').src = q.image; $('pr-code-ref-zoom').textContent = L.figure_zoom; }
    $('pr-code-hint').textContent = L.code_keys;
    input.value = q.answer; input.disabled = false;
    $('pr-code').hidden = false; document.body.style.overflow = 'hidden';
    syncGutter(); runLint(); updateCount(); setSave('saved');
    setTimeout(function () { input.focus(); input.setSelectionRange(input.value.length, input.value.length); }, 30);
  }
  function closeCode() {
    if (!C.q) return;
    var q = C.q; q.answer = input.value; flushSave(q); doSave(q);
    $('pr-code').hidden = true; document.body.style.overflow = ''; C.q = null;
    refreshCodeBox(q); renderNav(); var b = $('pr-code-open'); if (b) b.focus();
  }
  $('pr-code-done').addEventListener('click', closeCode);
  $('pr-code-ref-zoom').addEventListener('click', function () { if (C.q && C.q.image) zoom(C.q.image); });
  $('pr-code-ref-img').addEventListener('click', function () { if (C.q && C.q.image) zoom(C.q.image); });
  $('pr-code-format').addEventListener('click', formatNow);

  function profile() { return (C.q && C.q.profile) || { family: 'plain', lang: 'other' }; }
  function updateCount() { $('pr-code-count').textContent = f(L.code_count, { n: input.value.length, m: PR.max_code }); }
  function syncGutter() {
    var n = input.value.split('\n').length, g = $('pr-code-gutter'), sev = {};
    C.problems.forEach(function (p) { var o = sev[p.line]; var rank = { error: 3, warning: 2, info: 1 }; if (!o || rank[p.severity] > rank[o]) sev[p.line] = p.severity; });
    var html = '';
    for (var i = 1; i <= n; i++) { var s = sev[i]; html += '<div' + (s ? ' class="' + (s === 'error' ? 'e' : (s === 'warning' ? 'w' : 'i')) + '"' : '') + '>' + i + '</div>'; }
    g.innerHTML = html; g.scrollTop = input.scrollTop;
  }
  input.addEventListener('scroll', function () { $('pr-code-gutter').scrollTop = input.scrollTop; });
  input.addEventListener('input', function () {
    var q = C.q; if (!q) return;
    q.answer = input.value; markDirty(q); scheduleSave(q, 900);
    updateCount(); clearTimeout(C.lintTimer); C.lintTimer = setTimeout(runLint, 250); syncGutter();
    if (q.profile && q.profile.lang === 'php') { clearTimeout(C.phpTimer); C.phpTimer = setTimeout(runPhp, 1400); }
  });
  function runLint() {
    if (!C.q || !window.CodeTools) return;
    var list = window.CodeTools.lint(profile(), input.value);
    C.phpErrors.forEach(function (e) { if (!list.some(function (p) { return p.line === e.line && p.severity === 'error'; })) list.push({ line: e.line, col: 1, severity: 'error', message: 'PHP : ' + e.message }); });
    C.problems = list; renderProblems(); syncGutter();
  }
  function runPhp() {
    var q = C.q; if (!q || input.value.trim() === '') { C.phpErrors = []; return; }
    var snapshot = input.value;
    api('lint_php', { code: snapshot }).then(function (r) { if (r && r.ok && r.checked && input.value === snapshot) { C.phpErrors = r.errors || []; runLint(); } });
  }
  function renderProblems() {
    var box = $('pr-code-problems'), sum = $('pr-code-lint'); box.textContent = '';
    var errs = C.problems.filter(function (p) { return p.severity === 'error'; }), warns = C.problems.filter(function (p) { return p.severity === 'warning'; });
    var shown = C.problems.filter(function (p) { return p.severity !== 'info'; }).slice(0, 40);
    if (input.value.trim() === '') { sum.textContent = ''; sum.className = 'prc-lintsum'; }
    else if (errs.length) { sum.textContent = f(L.code_errors, { n: errs.length }) + (warns.length ? ' · ' + f(L.code_warnings, { n: warns.length }) : ''); sum.className = 'prc-lintsum bad'; }
    else if (warns.length) { sum.textContent = f(L.code_warnings, { n: warns.length }); sum.className = 'prc-lintsum warn'; }
    else { sum.textContent = L.code_ok; sum.className = 'prc-lintsum good'; }
    shown.forEach(function (p) {
      var b = el('button'); b.type = 'button';
      b.appendChild(el('span', 'sev ' + p.severity, L['sev_' + p.severity] + ' · ' + f(L.line, { n: p.line })));
      b.appendChild(el('span', '', p.message));
      b.addEventListener('click', function () { gotoLine(p.line); }); box.appendChild(b);
    });
  }
  function gotoLine(n) {
    var lines = input.value.split('\n'), pos = 0; for (var i = 0; i < n - 1 && i < lines.length; i++) pos += lines[i].length + 1;
    input.focus(); input.setSelectionRange(pos, pos + (lines[n - 1] || '').length); input.scrollTop = Math.max(0, (n - 4) * 21.6);
  }
  function replaceAll(text) {
    // an insertion keeps the browser's undo history (Ctrl+Z gives the unformatted code back)
    input.focus(); input.setSelectionRange(0, input.value.length);
    var okExec = false; try { okExec = document.execCommand('insertText', false, text); } catch (e) {}
    if (!okExec || input.value !== text) { input.value = text; input.dispatchEvent(new Event('input')); }
  }
  function formatNow() {
    if (!C.q || !window.CodeTools || input.value.trim() === '') return;
    var out = window.CodeTools.format(profile(), input.value);
    if (out !== input.value) replaceAll(out);
    var sum = $('pr-code-lint'); var keep = sum.textContent, cls = sum.className; sum.textContent = L.code_formatted; sum.className = 'prc-lintsum good';
    setTimeout(function () { renderProblems(); }, 1400);
  }
  // editing comfort: Tab / Shift+Tab, auto-indent on Enter
  input.addEventListener('keydown', function (e) {
    var unit = '    ';
    if (e.key === 'Tab') {
      e.preventDefault();
      var s = input.selectionStart, en = input.selectionEnd, v = input.value;
      if (s !== en && v.slice(s, en).indexOf('\n') !== -1 || e.shiftKey) {
        var ls = v.lastIndexOf('\n', s - 1) + 1, block = v.slice(ls, en), lines = block.split('\n');
        var out = lines.map(function (l) { return e.shiftKey ? l.replace(/^( {1,4}|\t)/, '') : unit + l; }).join('\n');
        input.setSelectionRange(ls, en); document.execCommand('insertText', false, out);
        input.setSelectionRange(ls, ls + out.length);
      } else { document.execCommand('insertText', false, unit); }
      return;
    }
    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
      var v2 = input.value, pos = input.selectionStart, ls2 = v2.lastIndexOf('\n', pos - 1) + 1, cur = v2.slice(ls2, pos);
      var ind = (cur.match(/^[ \t]*/) || [''])[0];
      var extra = (/[{(\[]\s*$/.test(cur) || (profile().family === 'python' && /:\s*$/.test(cur))) ? unit : '';
      e.preventDefault();
      var after = v2.slice(pos, pos + 1), closes = extra && /[)}\]]/.test(after);
      document.execCommand('insertText', false, '\n' + ind + extra + (closes ? '\n' + ind : ''));
      if (closes) { var np = input.selectionStart - ind.length - 1; input.setSelectionRange(np, np); }
      return;
    }
    if ((e.key === 'F' || e.key === 'f') && e.altKey && e.shiftKey) { e.preventDefault(); formatNow(); return; }
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); if (C.q) { flushSave(C.q); } return; }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (!$('pr-zoom').hidden) { $('pr-zoom').hidden = true; return; }
      if (!$('pr-confirm').hidden) { $('pr-confirm').hidden = true; return; }
      if (!$('pr-code').hidden) { closeCode(); }
      return;
    }
    if (e.altKey && !e.shiftKey && $('pr-code').hidden && !$('pr-exam').hidden) {
      if (e.key === 'ArrowRight') { e.preventDefault(); goto(S.cur + 1); } else if (e.key === 'ArrowLeft') { e.preventDefault(); goto(S.cur - 1); }
    }
  });

  boot();
})();
