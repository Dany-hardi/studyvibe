/* Landing page behaviour: reveal on scroll, animated illustrations, auth dialog. */
(() => {
  const T = window.SV_T || {};
  /* Anonymous funnel counters (no identifiers, see the privacy policy) */
  const track = (event) => { try { const fd = new FormData(); fd.append('event', event); navigator.sendBeacon('/api/track.php', fd); } catch (e) {} };
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Nav border once the page scrolls */
  const nav = $('#nav');
  const onScroll = () => nav.classList.toggle('scrolled', scrollY > 8);
  addEventListener('scroll', onScroll, { passive: true }); onScroll();

  /* Reveal on scroll */
  const rv = $$('.rv');
  if ('IntersectionObserver' in window && !reduce) {
    const io = new IntersectionObserver((es) => es.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
    }), { threshold: .15, rootMargin: '0px 0px -6% 0px' });
    rv.forEach(el => io.observe(el));
  } else rv.forEach(el => el.classList.add('in'));

  /* Lottie people: loaded once visible, paused off-screen, a still frame for reduced motion */
  const initLottie = () => {
    if (!window.lottie) return;
    const io = 'IntersectionObserver' in window ? new IntersectionObserver((es) => es.forEach(e => {
      const a = e.target._anim; if (!a) return;
      e.isIntersecting ? a.play() : a.pause();
    }), { threshold: .1 }) : null;
    $$('[data-lottie]').forEach(el => {
      const a = lottie.loadAnimation({ container: el, renderer: 'svg', loop: true, autoplay: false, path: el.dataset.lottie,
        rendererSettings: { preserveAspectRatio: 'xMidYMid meet', progressiveLoad: true } });
      el._anim = a;
      a.addEventListener('DOMLoaded', () => {
        el.classList.add('is-ready');
        if (reduce) a.goToAndStop(Math.floor(a.totalFrames * .5), true);
        else if (!io) a.play();
      });
      /* Intro plays once, then the loop skips the build-up */
      let looped = false;
      a.addEventListener('loopComplete', () => {
        if (looped) return; looped = true;
        a.playSegments([Math.floor(a.totalFrames * .2), a.totalFrames], true);
      });
      if (io && !reduce) io.observe(el);
    });
  };
  if (document.readyState === 'complete') initLottie(); else addEventListener('load', initLottie);

  /* Hero art follows the pointer very slightly */
  const art = $('.hero-art');
  if (art && !reduce && matchMedia('(hover: hover)').matches) {
    addEventListener('pointermove', (e) => {
      const x = (e.clientX / innerWidth - .5), y = (e.clientY / innerHeight - .5);
      art.style.setProperty('--px', (x * 14).toFixed(1) + 'px'); art.style.setProperty('--py', (y * 10).toFixed(1) + 'px');
    }, { passive: true });
  }

  /* Auth dialog */
  const dlg = $('#auth');
  const views = { login: $('#view-login'), forgot: $('#view-forgot'), signup: $('#view-signup'), tfa: $('#view-tfa') };
  function show(view) {
    Object.entries(views).forEach(([k, el]) => el.hidden = k !== view);
    if (view === 'signup') { goStep(1); track('signup_open'); }
    if (view === 'login') track('login_open');
    const f = $('input:not([type=hidden]):not([type=checkbox])', views[view]);
    if (f) setTimeout(() => f.focus(), 60);
  }
  function open(view) {
    if (!dlg.open) dlg.showModal();
    show(view);
  }
  $$('[data-open-auth]').forEach(b => b.addEventListener('click', () => open(b.dataset.openAuth)));
  $$('[data-view]').forEach(b => b.addEventListener('click', () => show(b.dataset.view)));
  $$('[data-close-auth]').forEach(b => b.addEventListener('click', () => dlg.close()));
  dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });

  $$('[data-toggle-pw]').forEach(b => b.addEventListener('click', () => {
    const i = document.getElementById(b.dataset.togglePw);
    const hidden = i.type === 'password';
    i.type = hidden ? 'text' : 'password';
    b.textContent = hidden ? b.dataset.hide : b.dataset.show;
  }));

  /* Login */
  const loginBtn = $('#login-btn');
  $('#login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    loginBtn.disabled = true; loginBtn.textContent = T.busy_login;
    const fd = new FormData();
    fd.append('email', $('#login-email').value.trim());
    fd.append('password', $('#login-password').value);
    const redirect = new URLSearchParams(location.search).get('redirect');
    if (redirect) fd.append('redirect', redirect);
    try {
      const data = await svPost('/login-action.php', fd);
      if (data.success && data.requires_2fa) { $('#tfa-code').value = ''; $('#tfa-status').hidden = true; show('tfa'); loginBtn.disabled = false; loginBtn.textContent = T.login; return; }
      if (data.success) { Toast.success(T.ok_login); setTimeout(() => location.href = data.redirect, 500); return; }
      if (data.code === 'invalid_credentials') showLoginFail(); else Toast.error(data.message);
    } catch { /* svPost already toasted */ }
    loginBtn.disabled = false; loginBtn.textContent = T.login;
  });

  /* Second step for accounts with two-factor authentication */
  $('#tfa-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $('#tfa-btn'), box = $('#tfa-status');
    const code = $('#tfa-code').value.trim();
    if (!code) return;
    const label = btn.textContent;
    btn.disabled = true; btn.textContent = T.tfa_busy;
    const fd = new FormData(); fd.append('code', code);
    try {
      const d = await svPost('/api/2fa-login.php', fd);
      if (d.success) { Toast.success(T.ok_login); setTimeout(() => location.href = d.redirect, 400); return; }
      if (d.expired) { show('login'); Toast.error(d.message || T.tfa_err); }
      else { box.className = 'fg-status is-error'; box.textContent = d.message || T.tfa_err; box.hidden = false; $('#tfa-code').select(); }
    } catch { /* svPost already toasted */ }
    btn.disabled = false; btn.textContent = label;
  });

  /* Wrong credentials: popup inside the dialog (toasts sit under a <dialog> in the top layer) */
  const lf = $('#login-fail');
  function showLoginFail() { lf.hidden = false; $('#lf-retry').focus(); }
  function hideLoginFail() {
    lf.hidden = true;
    const pw = $('#login-password'); pw.value = ''; pw.focus();
  }
  $('#lf-retry').addEventListener('click', hideLoginFail);
  lf.addEventListener('click', (ev) => { if (ev.target === lf) hideLoginFail(); });
  lf.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); hideLoginFail(); } });

  /* Password reset — feedback lives inside the dialog (toasts sit under a <dialog> in the top layer) */
  const fgBtn = $('#forgot-btn'), fgMail = $('#forgot-email'), fgStatus = $('#forgot-status');
  let fgTimer = null;
  function fgShow(kind, html) {
    fgStatus.hidden = false;
    fgStatus.className = 'fg-status is-' + kind;
    fgStatus.innerHTML = html;
  }
  function fgCooldown(sec) {
    clearInterval(fgTimer);
    fgBtn.disabled = true;
    const tick = () => {
      if (sec <= 0) { clearInterval(fgTimer); fgBtn.disabled = false; fgBtn.textContent = T.fg_resend; return; }
      fgBtn.textContent = T.fg_wait.replace(':s', String(sec--));
    };
    tick();
    fgTimer = setInterval(tick, 1000);
  }
  fgMail.addEventListener('input', () => { fgStatus.hidden = true; fgMail.classList.remove('invalid'); });
  fgBtn.addEventListener('click', async () => {
    const email = fgMail.value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      fgMail.classList.add('invalid'); fgMail.focus();
      return fgShow('error', esc(T.fg_bad));
    }
    const label = fgBtn.textContent;
    fgBtn.disabled = true; fgBtn.textContent = T.fg_busy;
    fgShow('busy', esc(T.fg_busy));
    const fd = new FormData(); fd.append('email', email);
    try {
      const d = await svPost('/forgot-password-action.php', fd);
      if (d && d.success) {
        fgShow('ok', '<strong>' + esc(T.fg_ok_h) + '</strong><span>' + esc(T.fg_ok_p).replace(':email', '<b>' + esc(email) + '</b>') + '</span>');
        fgCooldown(30);
      } else {
        fgShow('error', esc((d && d.message) || T.fg_err));
        fgBtn.disabled = false; fgBtn.textContent = label;
      }
    } catch {
      fgShow('error', esc(T.fg_err));
      fgBtn.disabled = false; fgBtn.textContent = label;
    }
  });
  function esc(v) { return String(v).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

  /* Signup */
  const role = $('#signup-role'), next = $('#signup-next'), sBtn = $('#signup-btn');
  const name = $('#signup-name'), mail = $('#signup-email'), pass = $('#signup-password'), phone = $('#signup-phone');
  const bar = $('#pw-bar');
  function goStep(n) { $('#signup-step-1').hidden = n !== 1; $('#signup-step-2').hidden = n !== 2; if (n === 2) { setTimeout(() => name.focus(), 60); track('signup_step2'); } validate(); }
  $$('.pick').forEach(p => p.addEventListener('click', () => {
    role.value = p.dataset.role;
    $$('.pick').forEach(x => x.setAttribute('aria-pressed', String(x === p)));
    validate();
  }));
  next.addEventListener('click', () => goStep(2));
  $('#signup-back').addEventListener('click', () => goStep(1));
  function validate() {
    const roleOk = role.value === 'student' || role.value === 'teacher';
    const nameOk = name.value.trim().length >= 2;
    const mailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail.value.trim());
    const n = pass.value.length, passOk = n >= 8;
    // The phone field exists only when text messages are switched on (FEATURE_SMS)
    const phoneOk = !phone || (phone.value.replace(/\D/g, '').length >= 8 && /^[+\d\s().-]+$/.test(phone.value.trim()));
    if (phone) phone.classList.toggle('invalid', !!phone.value && !phoneOk);
    name.classList.toggle('invalid', !!name.value && !nameOk);
    mail.classList.toggle('invalid', !!mail.value && !mailOk);
    const score = Math.min(100, n * 12 + (/\d/.test(pass.value) ? 20 : 0) + (/[A-Z]/.test(pass.value) ? 15 : 0));
    bar.style.width = n ? score + '%' : '0';
    bar.style.background = score < 40 ? 'var(--danger)' : score < 70 ? 'var(--ochre)' : 'var(--ok)';
    next.disabled = !roleOk;
    sBtn.disabled = !(roleOk && nameOk && mailOk && phoneOk && passOk);
    return !sBtn.disabled;
  }
  [name, mail, phone, pass].filter(Boolean).forEach(el => el.addEventListener('input', () => { validate(); suHide(); }));
  const suBox = $('#signup-status');
  function suShow(kind, html) { suBox.className = 'fg-status ' + (kind === 'ok' ? 'is-ok' : kind === 'busy' ? 'is-busy' : 'is-error'); suBox.innerHTML = html; suBox.hidden = false; }
  function suHide() { suBox.hidden = true; }
  $('#signup-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!validate()) return;
    const label = sBtn.textContent;
    track('signup_submit');
    sBtn.disabled = true; sBtn.textContent = T.busy_sign;
    const fd = new FormData();
    fd.append('role', role.value); fd.append('name', name.value.trim());
    fd.append('email', mail.value.trim()); if (phone) fd.append('phone', phone.value.trim()); fd.append('password', pass.value);
    if ($('#signup-newsletter').checked) fd.append('newsletter', '1');
    suShow('busy', esc(T.busy_sign));
    try {
      const d = await svPost('/signup-action.php', fd);
      if (d.success) {
        suShow('ok', esc(T.ok_sign + (d.mail_sent === false ? T.mail_warn : '')));
        setTimeout(() => location.href = d.redirect, d.mail_sent === false ? 2500 : 1200);
        return;
      }
      suShow('error', esc(d.message));
    } catch { suShow('error', esc(T.net_err)); }
    sBtn.textContent = label; validate();
  });

  /* Deep links: ?auth=login|signup opens the dialog, ?error=auth_required explains why */
  const q = new URLSearchParams(location.search);
  if (q.get('error') === 'auth_required') { Toast.error(T.auth_req); open('login'); }
  else if (q.get('auth') === 'login' || q.get('auth') === 'signup') open(q.get('auth'));
})();
