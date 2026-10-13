/* Shared behaviour of the public pages: language, theme, mobile menu. Loaded by lib/SiteChrome.php. */
(function () {
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* Language: same endpoint as the rest of the site, then reload in the new language */
  $$('[data-lang]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var lang = a.getAttribute('data-lang');
      if (document.documentElement.lang === lang) return;
      fetch('/api/set-language.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ lang: lang }) })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.success) location.reload(); else location.search = '?lang=' + lang; })
        .catch(function () { location.search = '?lang=' + lang; });
    });
  });

  /* Theme */
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var dark = document.documentElement.classList.toggle('dark');
      try { localStorage.setItem('sv_dark', dark ? '1' : '0'); } catch (e) {}
    });
  });

  /* Mobile menu: closes on outside click, Escape, or after choosing a link */
  var menu = document.querySelector('.sc-menu');
  if (menu) {
    document.addEventListener('click', function (e) { if (menu.open && !menu.contains(e.target)) menu.open = false; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); } });
    $$('a', menu).forEach(function (a) { a.addEventListener('click', function () { menu.open = false; }); });
  }

  /* Back to top */
  $$('[data-scroll-top]').forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
  });
})();
