/* Careers pages: reveal on scroll, filters on the list, copy link, and the application form (client checks + upload). */
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var L = window.CA_L || {};

  /* ---- Reveal on scroll ---- */
  var rv = $$('.ca-rv');
  if ('IntersectionObserver' in window && rv.length) {
    var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }); }, { rootMargin: '0px 0px -8% 0px', threshold: .08 });
    rv.forEach(function (el) { io.observe(el); });
    /* Anything already above the fold when the page opens at a deep link must not wait for a scroll */
    setTimeout(function () { rv.forEach(function (el) { var r = el.getBoundingClientRect(); if (r.top < innerHeight) el.classList.add('in'); }); }, 60);
  } else { rv.forEach(function (el) { el.classList.add('in'); }); }

  /* ---- Filters on the list ---- */
  var list = $('#ca-jobs');
  if (list) {
    var q = $('#ca-q'), dept = $('#ca-dept'), type = $('#ca-type'), mode = $('#ca-mode'), count = $('#ca-count'), reset = $('#ca-reset'), none = $('#ca-nomatch');
    var items = $$('.ca-job', list);
    var apply = function () {
      var term = q.value.trim().toLowerCase(), n = 0;
      items.forEach(function (li) {
        var ok = (!term || li.dataset.hay.indexOf(term) > -1) && (!dept.value || li.dataset.dept === dept.value) && (!type.value || li.dataset.type === type.value) && (!mode.value || li.dataset.mode === mode.value);
        li.hidden = !ok; if (ok) n++;
      });
      count.textContent = (n === 1 ? count.dataset.one : count.dataset.other).replace('{n}', n);
      var active = !!(term || dept.value || type.value || mode.value);
      reset.hidden = !active;
      none.hidden = n > 0; list.hidden = n === 0;
    };
    [q, dept, type, mode].forEach(function (el) { el.addEventListener('input', apply); el.addEventListener('change', apply); });
    reset.addEventListener('click', function () { q.value = ''; dept.value = type.value = mode.value = ''; apply(); q.focus(); });
  }

  /* ---- Copy link ---- */
  var share = $('#ca-share');
  if (share) {
    share.addEventListener('click', function () {
      var done = function () { var s = $('span', share), old = s.textContent; s.textContent = share.dataset.copied; setTimeout(function () { s.textContent = old; }, 1800); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(location.href.split('#')[0]).then(done, done);
      else { var t = document.createElement('textarea'); t.value = location.href; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); } catch (e) {} t.remove(); done(); }
    });
  }

  /* ---- Application form ---- */
  var form = $('#ca-form');
  if (!form) return;
  var err = L.err || {}, banner = $('#ca-banner'), send = $('#ca-send');
  var kb = function (n) { return n < 1048576 ? Math.max(1, Math.round(n / 1024)) + ' KB' : (n / 1048576).toFixed(1) + ' MB'; };

  var zones = {};   // the file fields (résumé, cover letter), each with its own drop zone
  function setErr(name, code) {
    var p = $('[data-err="' + name + '"]', form), f = form.elements[name];
    if (p) { p.textContent = code ? (err[code] || err.invalid) : ''; p.classList.toggle('show', !!code); }
    if (zones[name]) zones[name].drop.classList.toggle('is-invalid', !!code);
    else if (f && f.classList) f.classList.toggle('is-invalid', !!code);
    if (f && f.setAttribute) { if (code) f.setAttribute('aria-invalid', 'true'); else f.removeAttribute('aria-invalid'); }
  }
  function clearAll() { $$('[data-err]', form).forEach(function (p) { setErr(p.dataset.err, ''); }); banner.classList.remove('show'); }

  function url(v) { v = v.trim(); if (!v) return true; if (!/^https?:\/\//i.test(v)) v = 'https://' + v; try { var u = new URL(v); return /\./.test(u.hostname); } catch (e) { return false; } }
  function checkFile(f) {
    if (!f) return '';
    var ext = (f.name.split('.').pop() || '').toLowerCase();
    if ((L.ext || ['pdf', 'doc', 'docx']).indexOf(ext) < 0) return 'bad_type';
    if (f.size > (L.maxBytes || 5242880)) return 'too_big';
    return '';
  }
  function validate() {
    var e = {}, v = function (n) { return (form.elements[n].value || '').trim(); };
    if (v('full_name').length < 2) e.full_name = 'required';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('email'))) e.email = v('email') ? 'invalid' : 'required';
    if (v('phone') && !/^[0-9 +().\-]{6,40}$/.test(v('phone'))) e.phone = 'invalid';
    if (!url(v('linkedin_url'))) e.linkedin_url = 'invalid';
    if (!url(v('portfolio_url'))) e.portfolio_url = 'invalid';
    if (v('years_experience') && !/^\d{1,2}$/.test(v('years_experience'))) e.years_experience = 'invalid';
    Object.keys(zones).forEach(function (k) { var z = zones[k], f = z.input.files && z.input.files[0], c = !f ? (z.required ? 'required' : '') : checkFile(f); if (c) e[k] = c; });
    if (!form.elements.consent.checked) e.consent = 'consent';
    return e;
  }
  function show(errors) {
    clearAll();
    var first = null;
    Object.keys(errors).forEach(function (k) { setErr(k, errors[k]); if (!first) first = zones[k] ? zones[k].input : form.elements[k]; });
    if (first) { banner.textContent = err.fix; banner.classList.add('show'); try { first.focus({ preventScroll: true }); } catch (x) {} var t = (first.closest('.field') || first).getBoundingClientRect().top + scrollY - 110; scrollTo({ top: t, behavior: 'smooth' }); }
  }

  /* File fields: a picker with drag and drop, one per .ca-dz block */
  $$('.ca-dz', form).forEach(function (z) {
    var name = z.dataset.name, input = $('input[type=file]', z), drop = $('.ca-drop', z), box = $('.ca-file', z);
    zones[name] = { input: input, drop: drop, required: z.dataset.required === '1' };
    function showFile() {
      var f = input.files && input.files[0];
      if (!f) { box.classList.remove('show'); drop.hidden = false; return; }
      $('.ca-file-n', z).textContent = f.name; $('.ca-file-s', z).textContent = kb(f.size);
      $('.ca-file-ic', z).textContent = ((f.name.split('.').pop() || '').slice(0, 4) || 'FILE').toUpperCase();
      box.classList.add('show'); drop.hidden = true;
      setErr(name, checkFile(f));
    }
    input.addEventListener('change', showFile);
    $('.ca-file-x', z).addEventListener('click', function () { input.value = ''; showFile(); setErr(name, ''); input.focus(); });
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('drag'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('drag'); }); });
    drop.addEventListener('drop', function (e) {
      e.preventDefault();
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        try { var dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]); input.files = dt.files; } catch (x) { return; }
        showFile();
      }
    });
  });

  form.addEventListener('input', function (e) { var n = e.target.name; if (n) setErr(n, ''); });
  form.elements.consent.addEventListener('change', function () { setErr('consent', ''); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var errors = validate();
    if (Object.keys(errors).length) { show(errors); return; }
    clearAll();
    send.disabled = true; send.classList.add('is-busy'); $('#ca-send-t').textContent = L.submitting;
    var fd = new FormData(form);
    fetch(form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { success: false, error: 'server' }; }); })
      .then(function (d) {
        if (d && d.success) {
          $('#ca-ok-p').textContent = (L.okP || '').replace('{name}', (d.name || '').split(' ')[0]).replace('{email}', d.email || '');
          $('#ca-ok-ref').textContent = d.reference;
          if (d.track) $('#ca-ok-track').href = d.track;
          $('#ca-form-wrap').hidden = true;
          var ok = $('#ca-ok'); ok.classList.add('show');
          /* The form has just collapsed and the page got shorter: scroll after the layout settled, to the confirmation itself */
          requestAnimationFrame(function () { ok.scrollIntoView({ behavior: 'smooth', block: 'start' }); ok.focus({ preventScroll: true }); });
          return;
        }
        if (d && d.fields && Object.keys(d.fields).length) { show(d.fields); }
        else { banner.textContent = err[d && d.error] || err.server; banner.classList.add('show'); }
      })
      .catch(function () { banner.textContent = err.network; banner.classList.add('show'); })
      .then(function () { send.disabled = false; send.classList.remove('is-busy'); $('#ca-send-t').textContent = L.submit; });
  });
})();
