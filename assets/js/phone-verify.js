/* Compulsory phone verification window (see lib/PhonePrompt.php). Talks to /api/phone.php. */
(function () {
  'use strict';
  var cfg = window.PV_CFG || {};
  var T = cfg.t || {};
  var $ = function (id) { return document.getElementById(id); };
  var overlay = $('pv-overlay');
  if (!overlay) return;

  var stepPhone = $('pv-step-phone'), stepCode = $('pv-step-code'), stepDone = $('pv-done');
  var phone = $('pv-phone'), code = $('pv-code');
  var resendBtn = $('pv-resend'), timer = null;

  document.documentElement.classList.add('pv-lock');
  if (cfg.prefill) phone.value = cfg.prefill;

  function post(data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch('/api/phone.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { success: false, message: T.err_network }; }); })
      .catch(function () { return { success: false, message: T.err_network }; });
  }
  function show(el, msg) { el.textContent = msg || ''; el.hidden = !msg; }
  function busy(btn, on, label) { btn.disabled = on; if (label) btn.textContent = label; }

  function startCountdown(seconds) {
    clearInterval(timer);
    var left = seconds;
    function tick() {
      if (left <= 0) { clearInterval(timer); resendBtn.disabled = false; resendBtn.textContent = T.resend; return; }
      resendBtn.disabled = true; resendBtn.textContent = (T.resend_in || '').replace(':s', String(left));
      left--;
    }
    tick(); timer = setInterval(tick, 1000);
  }

  function sendCode(isResend) {
    var value = phone.value.trim();
    show($('pv-error-phone'), ''); show($('pv-error-code'), '');
    if (!value) { show($('pv-error-phone'), T.err_empty); phone.focus(); return Promise.resolve(); }
    busy($('pv-send'), true, T.sending);
    return post({ action: 'start', phone: value }).then(function (d) {
      busy($('pv-send'), false, T.send);
      if (!d.success) {
        if (isResend) { show($('pv-error-code'), d.message); } else { show($('pv-error-phone'), d.message); }
        if (d.error === 'too_soon' && stepCode.hidden) { /* stay on the phone step */ }
        return;
      }
      stepPhone.hidden = true; stepCode.hidden = false;
      $('pv-sent').textContent = (T.sent || '').replace(':phone', d.phone_masked || '');
      var dev = $('pv-dev');
      if (d.dev_code) { dev.textContent = (T.dev || '').replace(':code', d.dev_code); dev.hidden = false; } else { dev.hidden = true; }
      code.value = ''; code.focus();
      startCountdown(60);
    });
  }

  stepPhone.addEventListener('submit', function (e) { e.preventDefault(); sendCode(false); });
  resendBtn.addEventListener('click', function () { sendCode(true); });
  $('pv-change').addEventListener('click', function () {
    clearInterval(timer); stepCode.hidden = true; stepPhone.hidden = false; show($('pv-error-code'), ''); phone.focus();
  });

  code.addEventListener('input', function () { code.value = code.value.replace(/\D+/g, '').slice(0, 6); if (code.value.length === 6) stepCode.requestSubmit(); });

  stepCode.addEventListener('submit', function (e) {
    e.preventDefault();
    show($('pv-error-code'), '');
    var v = code.value.replace(/\D+/g, '');
    if (v.length !== 6) { show($('pv-error-code'), T.err_code); code.focus(); return; }
    busy($('pv-verify'), true, T.verifying);
    post({ action: 'verify', code: v }).then(function (d) {
      busy($('pv-verify'), false, T.verify);
      if (!d.success) { show($('pv-error-code'), d.message); code.select(); return; }
      clearInterval(timer);
      stepCode.hidden = true; stepDone.hidden = false;
      setTimeout(function () { location.reload(); }, 900);
    });
  });

  // The window cannot be dismissed: keep the keyboard inside it
  overlay.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab') return;
    var f = Array.prototype.filter.call(overlay.querySelectorAll('a[href],button,input'), function (el) { return !el.disabled && !el.closest('[hidden]'); });
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
  setTimeout(function () { phone.focus(); }, 50);
})();
