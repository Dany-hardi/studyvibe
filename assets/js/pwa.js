/*
 * StudyVibe app: install pop-up, notification pop-up and the "App and notifications" panel (window.SVPwa.open()).
 *
 *  - Android / desktop Chrome and Edge: the browser's own install prompt, triggered from our pop-up.
 *  - iPhone / iPad (Safari): there is no install prompt, so the pop-up shows the three steps (Share, Add to Home Screen).
 *  - Notifications: asked for politely, only once the person is signed in, never on the first second of a visit, and never in the exam room.
 *  - On sign-out the device is detached from the account, so the next person on a shared phone never sees someone else's alerts.
 */
(function () {
  'use strict';
  var doc = document, win = window, ls = (function () { try { return win.localStorage; } catch (e) { return null; } })();
  var lang = (doc.documentElement.lang || 'fr').slice(0, 2) === 'en' ? 'en' : 'fr';
  var metaAuth = doc.querySelector('meta[name="sv-auth"]'), metaUid = doc.querySelector('meta[name="sv-uid"]');
  var role = metaAuth ? metaAuth.content : '', uid = metaUid ? metaUid.content : '';
  var loggedIn = !!role;
  var ua = navigator.userAgent || '';
  var isIOS = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isAndroid = /android/i.test(ua);
  var isFirefox = /firefox|fxios/i.test(ua);
  var standalone = (win.matchMedia && win.matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
  var deferred = null;
  var pushSupported = 'serviceWorker' in navigator && 'PushManager' in win && 'Notification' in win;

  var S = {
    fr: {
      app: 'Application StudyVibe', later: 'Plus tard', never: 'Ne plus me le proposer', close: 'Fermer', ok: 'J’ai compris',
      i_title: 'Gardez StudyVibe dans votre poche', i_text: 'Installez l’application : elle s’ouvre en un geste, comme sur votre téléphone, sans rien télécharger de lourd.',
      i_b1: 'Un accès direct depuis votre écran d’accueil', i_b2: 'Des notifications pour vos nouvelles leçons, vos résultats et vos rappels d’évaluation', i_b3: 'Plus rapide, même quand le réseau est faible',
      i_btn: 'Installer l’application', i_ios_title: 'Ajoutez StudyVibe à votre écran d’accueil', i_ios_text: 'Sur iPhone et iPad, cela se fait en trois gestes depuis Safari. L’application peut alors vous envoyer des notifications.',
      i_s1: 'Touchez le bouton <b>Partager</b> {share} en bas de Safari', i_s2: 'Faites défiler et choisissez <b>Sur l’écran d’accueil</b> {plus}', i_s3: 'Touchez <b>Ajouter</b> en haut à droite',
      i_manual_title: 'Installez StudyVibe', i_manual_text: 'Ouvrez le menu de votre navigateur (⋮ ou ⋯), puis choisissez <b>Installer l’application</b> ou <b>Ajouter à l’écran d’accueil</b>.',
      i_done: 'Application installée. Bienvenue !',
      p_kicker: 'Notifications', p_title: 'Ne manquez plus rien', p_text: 'Autorisez les notifications sur cet appareil pour être prévenu à l’instant, même quand StudyVibe est fermé.',
      p_b1: 'Une leçon est ajoutée ou mise à jour dans vos cours', p_b2: 'Votre devoir est noté, votre résultat est prêt, votre enseignant vous répond', p_b3: 'Une évaluation en direct commence dans 15 minutes',
      p_btn: 'Activer les notifications', p_note: 'Vous pourrez les couper à tout moment, ici ou dans les réglages de votre appareil.',
      p_ios: 'Sur iPhone et iPad, les notifications fonctionnent une fois l’application ajoutée à l’écran d’accueil.',
      p_on: 'Notifications activées sur cet appareil.', p_denied: 'Notifications bloquées. Autorisez-les dans les réglages du navigateur ou de l’appareil, puis revenez ici.', p_err: 'Impossible d’activer les notifications pour le moment.',
      s_title: 'Application et notifications', s_app: 'Application', s_app_on: 'StudyVibe est installée sur cet appareil.', s_app_off: 'Ajoutez StudyVibe à votre écran d’accueil pour l’ouvrir comme une application.',
      s_push: 'Notifications sur cet appareil', s_push_on: 'Activées : vous serez prévenu des nouvelles leçons, résultats et rappels.', s_push_off: 'Désactivées sur cet appareil.',
      s_push_na: 'Ce navigateur ne gère pas les notifications.', s_push_login: 'Connectez-vous pour activer les notifications.', s_push_off_btn: 'Désactiver', s_test: 'Envoyer un essai',
      s_test_sent: 'Notification d’essai envoyée.', s_off_done: 'Notifications désactivées sur cet appareil.', s_unavailable: 'Les notifications ne sont pas encore disponibles sur ce serveur.',
    },
    en: {
      app: 'StudyVibe app', later: 'Later', never: 'Don’t suggest it again', close: 'Close', ok: 'Got it',
      i_title: 'Keep StudyVibe in your pocket', i_text: 'Install the app: it opens in one tap, like any app on your phone, with nothing heavy to download.',
      i_b1: 'Direct access from your home screen', i_b2: 'Notifications for new lessons, your results and evaluation reminders', i_b3: 'Faster, even on a weak connection',
      i_btn: 'Install the app', i_ios_title: 'Add StudyVibe to your home screen', i_ios_text: 'On iPhone and iPad it takes three taps from Safari. The app can then send you notifications.',
      i_s1: 'Tap the <b>Share</b> button {share} at the bottom of Safari', i_s2: 'Scroll and choose <b>Add to Home Screen</b> {plus}', i_s3: 'Tap <b>Add</b> at the top right',
      i_manual_title: 'Install StudyVibe', i_manual_text: 'Open your browser menu (⋮ or ⋯), then choose <b>Install app</b> or <b>Add to Home screen</b>.',
      i_done: 'App installed. Welcome!',
      p_kicker: 'Notifications', p_title: 'Never miss a thing', p_text: 'Allow notifications on this device to be told right away, even when StudyVibe is closed.',
      p_b1: 'A lesson is added or updated in your courses', p_b2: 'Your assignment is marked, your result is ready, your teacher replies', p_b3: 'A live evaluation starts in 15 minutes',
      p_btn: 'Turn on notifications', p_note: 'You can turn them off at any time, here or in your device settings.',
      p_ios: 'On iPhone and iPad, notifications work once the app has been added to the home screen.',
      p_on: 'Notifications are on for this device.', p_denied: 'Notifications are blocked. Allow them in your browser or device settings, then come back here.', p_err: 'Notifications could not be turned on right now.',
      s_title: 'App and notifications', s_app: 'App', s_app_on: 'StudyVibe is installed on this device.', s_app_off: 'Add StudyVibe to your home screen to open it like an app.',
      s_push: 'Notifications on this device', s_push_on: 'On: you will be told about new lessons, results and reminders.', s_push_off: 'Off on this device.',
      s_push_na: 'This browser does not support notifications.', s_push_login: 'Sign in to turn notifications on.', s_push_off_btn: 'Turn off', s_test: 'Send a test',
      s_test_sent: 'Test notification sent.', s_off_done: 'Notifications turned off on this device.', s_unavailable: 'Notifications are not available on this server yet.',
    },
  }[lang];

  var ICONS = {
    share: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M8 7l4-4 4 4"/><path d="M5 11v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8"/></svg>',
    plus: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M12 8v8M8 12h8"/></svg>',
    home: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9.5V20h13V9.5"/></svg>',
    bell: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2H4.5Z"/><path d="M10 21h4"/></svg>',
    bolt: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6z"/></svg>',
    x: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    check: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>',
  };

  /* ── small storage helpers (every read and write is guarded: private windows throw) ── */
  function get(k) { try { return ls ? ls.getItem(k) : null; } catch (e) { return null; } }
  function set(k, v) { try { if (ls) ls.setItem(k, v); } catch (e) {} }
  function snoozed(k) { var t = parseInt(get(k) || '0', 10); return t && Date.now() < t; }
  function snooze(k, days) { set(k, String(Date.now() + days * 86400000)); }
  var DAY = 86400000;

  function inExam() {
    if (/\/live-session\.php/.test(location.pathname)) return true;
    var m = doc.getElementById('final-exam-modal');
    return !!(m && !m.classList.contains('hidden') && m.offsetParent !== null);
  }

  /* ── modal plumbing ── */
  var current = null, lastFocus = null;
  function build(cfg) {
    close(true);
    lastFocus = doc.activeElement;
    var el = doc.createElement('div');
    el.className = 'svp'; el.style.setProperty('--svp-c', cfg.color || '#b5472b'); el.setAttribute('role', 'dialog'); el.setAttribute('aria-modal', 'true'); el.setAttribute('aria-labelledby', 'svp-title');
    el.innerHTML = '<div class="svp-backdrop" data-svp-close></div><div class="svp-card" tabindex="-1">' +
      '<div class="svp-art"><button type="button" class="svp-x" data-svp-close aria-label="' + S.close + '">' + ICONS.x + '</button><img src="' + cfg.img + '" alt="" width="320" height="240"></div>' +
      '<div class="svp-body">' + cfg.html + '</div></div>';
    doc.body.appendChild(el);
    current = el;
    var card = el.querySelector('.svp-card'); card.focus();
    el.addEventListener('click', function (e) {
      if (e.target.closest('[data-svp-close]')) { if (cfg.onDismiss) cfg.onDismiss('x'); close(); return; }
      var a = e.target.closest('[data-svp-act]'); if (a && cfg.onAct) cfg.onAct(a.getAttribute('data-svp-act'), a);
    });
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { if (cfg.onDismiss) cfg.onDismiss('x'); close(); return; }
      if (e.key === 'Tab') {
        var f = [].slice.call(el.querySelectorAll('button, a[href]')).filter(function (x) { return !x.disabled && x.offsetParent !== null; });
        if (!f.length) return; var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && (doc.activeElement === first || doc.activeElement === card)) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
    return el;
  }
  function close(silent) {
    if (!current) return;
    current.remove(); current = null;
    if (!silent && lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
  }
  function toast(msg) {
    var t = doc.createElement('div'); t.className = 'svp-toast'; t.setAttribute('role', 'status'); t.textContent = msg; doc.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 3800);
  }

  /* ── install ── */
  function canInstallHere() {
    if (standalone || get('sv_pwa_installed') === '1') return false;
    if (isIOS) return /safari/i.test(ua) && !/crios|fxios|edgios/i.test(ua) ? 'ios' : 'ios-other';
    if (deferred) return 'native';
    if (isAndroid && isFirefox) return 'manual';
    return false;
  }
  function installHtml(kind) {
    if (kind === 'ios') {
      return '<p class="svp-kicker">' + S.app + '</p><h2 class="svp-title" id="svp-title">' + S.i_ios_title + '</h2><p class="svp-text">' + S.i_ios_text + '</p>' +
        '<ol class="svp-steps"><li><span>' + S.i_s1.replace('{share}', ICONS.share) + '</span></li><li><span>' + S.i_s2.replace('{plus}', ICONS.plus) + '</span></li><li><span>' + S.i_s3 + '</span></li></ol>' +
        '<div class="svp-actions"><button type="button" class="svp-btn primary" data-svp-act="later">' + S.ok + '</button><button type="button" class="svp-link" data-svp-act="never">' + S.never + '</button></div>';
    }
    if (kind === 'manual' || kind === 'ios-other') {
      var t = kind === 'ios-other' ? (lang === 'en' ? 'Open this page in Safari, then use Share, then Add to Home Screen.' : 'Ouvrez cette page dans Safari, puis utilisez Partager, puis Sur l’écran d’accueil.') : S.i_manual_text;
      return '<p class="svp-kicker">' + S.app + '</p><h2 class="svp-title" id="svp-title">' + S.i_manual_title + '</h2><p class="svp-text">' + t + '</p>' +
        '<div class="svp-actions"><button type="button" class="svp-btn primary" data-svp-act="later">' + S.ok + '</button><button type="button" class="svp-link" data-svp-act="never">' + S.never + '</button></div>';
    }
    return '<p class="svp-kicker">' + S.app + '</p><h2 class="svp-title" id="svp-title">' + S.i_title + '</h2><p class="svp-text">' + S.i_text + '</p>' +
      '<ul class="svp-list"><li><span class="ic">' + ICONS.home + '</span><span>' + S.i_b1 + '</span></li><li><span class="ic">' + ICONS.bell + '</span><span>' + S.i_b2 + '</span></li><li><span class="ic">' + ICONS.bolt + '</span><span>' + S.i_b3 + '</span></li></ul>' +
      '<div class="svp-actions"><button type="button" class="svp-btn primary" data-svp-act="install">' + S.i_btn + '</button><button type="button" class="svp-btn ghost" data-svp-act="later">' + S.later + '</button><button type="button" class="svp-link" data-svp-act="never">' + S.never + '</button></div>';
  }
  function openInstall(manual) {
    var kind = canInstallHere();
    if (!kind) return false;
    build({
      img: kind === 'native' ? '/assets/img/pwa/install.svg' : '/assets/img/pwa/ios.svg', color: '#b5472b', html: installHtml(kind),
      onDismiss: function () { if (!manual) snooze('sv_pwa_i_snooze', 3); },
      onAct: function (act) {
        if (act === 'install') {
          var d = deferred; if (!d) { close(); return; }
          d.prompt();
          d.userChoice.then(function (r) { deferred = null; close(); if (r && r.outcome === 'accepted') { set('sv_pwa_installed', '1'); } else { snooze('sv_pwa_i_snooze', 7); } }).catch(function () { close(); });
        } else if (act === 'later') { snooze('sv_pwa_i_snooze', manual ? 0 : 7); close(); }
        else if (act === 'never') { snooze('sv_pwa_i_snooze', 90); close(); }
      },
    });
    return true;
  }
  win.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; scheduleInstall(); });
  win.addEventListener('appinstalled', function () {
    deferred = null; set('sv_pwa_installed', '1'); close(true); toast(S.i_done);
    setTimeout(function () { if (loggedIn) maybePushPrompt(true); }, 1800);
  });

  /* ── push ── */
  function b64uToBytes(s) { var p = '='.repeat((4 - s.length % 4) % 4), b = atob((s + p).replace(/-/g, '+').replace(/_/g, '/')), a = new Uint8Array(b.length); for (var i = 0; i < b.length; i++) a[i] = b.charCodeAt(i); return a; }
  function api(body) {
    return fetch('/api/push.php' + (body ? '' : '?action=state'), { method: body ? 'POST' : 'GET', credentials: 'same-origin', headers: body ? { 'Content-Type': 'application/json' } : {}, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().catch(function () { return { success: false }; }); });
  }
  var stateCache = null;
  function serverState() { return stateCache ? Promise.resolve(stateCache) : api().then(function (s) { stateCache = s; return s; }).catch(function () { return { configured: false }; }); }
  function currentSub() { return navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }); }
  var inflight = null;                                   // one subscription attempt at a time (the page may ask twice at load)
  function subscribe() {
    if (inflight) return inflight;
    inflight = subscribeNow().then(function (x) { inflight = null; return x; }, function (e) { inflight = null; throw e; });
    return inflight;
  }
  function subscribeNow() {
    return serverState().then(function (st) {
      if (!st || !st.configured) throw new Error('not_configured');
      return navigator.serviceWorker.ready.then(function (reg) {
        return reg.pushManager.getSubscription().then(function (sub) {
          return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64uToBytes(st.publicKey) });
        });
      });
    }).then(function (sub) {
      return api(Object.assign({ action: 'subscribe' }, sub.toJSON())).then(function (r) { if (!r.success) throw new Error(r.error || 'server'); set('sv_push_sync_' + uid, String(Date.now())); return sub; });
    });
  }
  function enablePush() {
    if (!pushSupported) return Promise.reject(new Error('unsupported'));
    return Notification.requestPermission().then(function (perm) { if (perm !== 'granted') throw new Error('denied'); return subscribe(); });
  }
  function disablePush() {
    return currentSub().then(function (sub) {
      if (!sub) return;
      var ep = sub.endpoint;
      return api({ action: 'unsubscribe', endpoint: ep }).catch(function () {}).then(function () { return sub.unsubscribe(); });
    });
  }
  // A device that already said yes: keep the server in step (once every 12 hours, and after a new sign-in)
  function silentSync() {
    if (!loggedIn || !pushSupported || Notification.permission !== 'granted') return;
    var last = parseInt(get('sv_push_sync_' + uid) || '0', 10);
    if (last && Date.now() - last < 12 * 3600000) return;
    subscribe().catch(function () {});
  }
  function pushPromptHtml(iosNeedsInstall) {
    return '<p class="svp-kicker">' + S.p_kicker + '</p><h2 class="svp-title" id="svp-title">' + S.p_title + '</h2><p class="svp-text">' + S.p_text + '</p>' +
      '<ul class="svp-list"><li><span class="ic">' + ICONS.bolt + '</span><span>' + S.p_b1 + '</span></li><li><span class="ic">' + ICONS.check + '</span><span>' + S.p_b2 + '</span></li><li><span class="ic">' + ICONS.bell + '</span><span>' + S.p_b3 + '</span></li></ul>' +
      '<div class="svp-actions"><button type="button" class="svp-btn primary" data-svp-act="enable">' + S.p_btn + '</button><button type="button" class="svp-btn ghost" data-svp-act="later">' + S.later + '</button><button type="button" class="svp-link" data-svp-act="never">' + S.never + '</button></div>' +
      '<p class="svp-note">' + S.p_note + '</p>';
  }
  function openPushPrompt() {
    build({
      img: '/assets/img/pwa/push.svg', color: '#0f6f6f', html: pushPromptHtml(),
      onDismiss: function () { snooze('sv_pwa_p_snooze', 3); },
      onAct: function (act, btn) {
        if (act === 'enable') {
          btn.disabled = true;
          enablePush().then(function () { close(); toast(S.p_on); api({ action: 'test' }); })
            .catch(function (e) { close(); toast(e && e.message === 'denied' ? S.p_denied : (e && e.message === 'not_configured' ? S.s_unavailable : S.p_err)); if (e && e.message === 'denied') snooze('sv_pwa_p_snooze', 30); });
        } else if (act === 'later') { snooze('sv_pwa_p_snooze', 7); close(); }
        else if (act === 'never') { snooze('sv_pwa_p_snooze', 90); close(); }
      },
    });
  }
  function maybePushPrompt(force) {
    if (!loggedIn || !pushSupported || inExam() || current) return;
    if (Notification.permission !== 'default') return;
    if (!force && snoozed('sv_pwa_p_snooze')) return;
    serverState().then(function (st) {
      if (!st || !st.configured || current || inExam()) return;
      currentSub().then(function (sub) { if (!sub) openPushPrompt(); });
    });
  }

  /* ── scheduling: never at once, never over an exam, one pop-up at a time ── */
  var installTimer = null;
  function scheduleInstall() {
    if (installTimer || standalone) return;
    installTimer = setTimeout(function () {
      installTimer = null;
      if (current || inExam() || snoozed('sv_pwa_i_snooze')) return;
      openInstall(false);
    }, 7000);
  }
  function scheduleStart() {
    if (inExam()) return;
    if (standalone) {
      if (loggedIn) { silentSync(); setTimeout(function () { maybePushPrompt(false); }, 5000); }
      return;
    }
    silentSync();
    var kind = isIOS ? canInstallHere() : (isAndroid && isFirefox ? 'manual' : null);
    if (kind) scheduleInstall();                       // Chrome/Edge fire beforeinstallprompt by themselves
    if (loggedIn && pushSupported && !isIOS) setTimeout(function () { if (!current && !deferred && !installTimer) maybePushPrompt(false); }, 22000);
  }

  /* ── the "App and notifications" panel (profile, menus) ── */
  function openSettings() {
    function render() {
      var kind = canInstallHere();
      var rows = '';
      rows += '<div class="svp-row"><h4>' + S.s_app + '</h4><p>' + ((standalone || get('sv_pwa_installed') === '1') ? S.s_app_on : S.s_app_off) + '</p>' +
        ((standalone || get('sv_pwa_installed') === '1') ? '' : (kind ? '<button type="button" class="svp-btn primary" data-svp-act="install-now">' + S.i_btn + '</button>' : '')) + '</div>';
      return Promise.all([loggedIn && pushSupported ? currentSub().catch(function () { return null; }) : null, loggedIn ? serverState() : null]).then(function (r) {
        var sub = r[0], st = r[1], perm = pushSupported ? Notification.permission : 'unsupported';
        var line, btns = '', cls = 'svp-status';
        if (!loggedIn) { line = S.s_push_login; }
        else if (!pushSupported) { line = isIOS && !standalone ? S.p_ios : S.s_push_na; cls += ' bad'; }
        else if (st && !st.configured) { line = S.s_unavailable; cls += ' bad'; }
        else if (perm === 'denied') { line = S.p_denied; cls += ' bad'; }
        else if (perm === 'granted' && sub) { line = S.s_push_on; cls += ' on'; btns = '<button type="button" class="svp-btn ghost" data-svp-act="test">' + S.s_test + '</button><button type="button" class="svp-btn ghost" data-svp-act="off">' + S.s_push_off_btn + '</button>'; }
        else { line = S.s_push_off; btns = '<button type="button" class="svp-btn primary" data-svp-act="on">' + S.p_btn + '</button>'; }
        rows += '<div class="svp-row"><h4>' + S.s_push + '</h4><div class="' + cls + '"><span class="dot"></span><span>' + line + '</span></div>' + btns + '</div>';
        return '<p class="svp-kicker">' + S.app + '</p><h2 class="svp-title" id="svp-title">' + S.s_title + '</h2><div class="svp-rows">' + rows + '</div>';
      });
    }
    function show() {
      render().then(function (html) {
        build({
          img: '/assets/img/pwa/settings.svg', color: '#3b5b7a', html: html,
          onAct: function (act, btn) {
            btn.disabled = true;
            if (act === 'install-now') {
              if (deferred) { var d = deferred; d.prompt(); d.userChoice.then(function (x) { deferred = null; if (x.outcome === 'accepted') set('sv_pwa_installed', '1'); show(); }); }
              else { close(); openInstall(true); }
            }
            else if (act === 'on') enablePush().then(function () { toast(S.p_on); api({ action: 'test' }); show(); }).catch(function (e) { toast(e && e.message === 'denied' ? S.p_denied : S.p_err); show(); });
            else if (act === 'off') disablePush().then(function () { toast(S.s_off_done); show(); });
            else if (act === 'test') api({ action: 'test' }).then(function () { toast(S.s_test_sent); btn.disabled = false; });
          },
        });
      });
    }
    show();
  }

  /* ── sign-out detaches this device ── */
  doc.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href*="logout.php"]');
    if (!a || !loggedIn || !pushSupported || Notification.permission !== 'granted') return;
    e.preventDefault();
    var href = a.href, done = false, go = function () { if (!done) { done = true; location.href = href; } };
    setTimeout(go, 1500);
    disablePush().then(go, go);
  }, true);

  win.SVPwa = { open: openSettings, install: function () { return openInstall(true); }, enable: enablePush, disable: disablePush, supported: pushSupported, standalone: standalone };
  doc.addEventListener('click', function (e) { var b = e.target.closest && e.target.closest('[data-sv-pwa]'); if (b) { e.preventDefault(); openSettings(); } });
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', scheduleStart); else scheduleStart();
})();
