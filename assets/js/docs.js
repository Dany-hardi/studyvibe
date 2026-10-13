/* Documentation page: live search over the sections, side menu that follows the reading position. */
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var secs = $$('.dc-sec'), links = $$('.dc-nav a[data-spy]'), q = $('#dc-q'), hits = $('#dc-hits'), empty = $('#dc-empty');
  var norm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
  var texts = secs.map(function (s) { return norm(s.textContent); });

  /* Search: hides the sections without the words typed (all words must be present), and the matching menu entries */
  function search() {
    var words = norm(q.value).split(/\s+/).filter(Boolean), n = 0;
    secs.forEach(function (s, i) {
      var ok = words.every(function (w) { return texts[i].indexOf(w) > -1; });
      s.hidden = !ok; if (ok) n++;
      var li = $('.dc-nav a[data-spy="' + s.id + '"]');
      if (li) li.parentNode.hidden = !ok;
      if (ok && words.length) $$('details', s).forEach(function (d) { d.open = norm(d.textContent).indexOf(words[0]) > -1; });
    });
    hits.hidden = !words.length;
    hits.textContent = (n === 1 ? hits.dataset.one : hits.dataset.other).replace('{n}', n);
    empty.hidden = n > 0;
  }
  q.addEventListener('input', search);
  q.addEventListener('keydown', function (e) { if (e.key === 'Escape') { q.value = ''; search(); } });

  /* Scroll spy */
  if ('IntersectionObserver' in window) {
    var cur = null;
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) cur = e.target.id; });
      links.forEach(function (a) { a.classList.toggle('on', a.dataset.spy === cur); });
    }, { rootMargin: '-20% 0px -70% 0px' });
    secs.forEach(function (s) { io.observe(s); });
  }

  /* On a phone the menu starts folded so the content is first */
  var box = $('.dc-nav-box');
  if (box && matchMedia('(max-width: 960px)').matches) box.open = false;
  links.forEach(function (a) { a.addEventListener('click', function () { if (matchMedia('(max-width: 960px)').matches) box.open = false; }); });

  /* A link to #faq (from the footer) opens straight on that section */
  if (location.hash) { var t = $(location.hash); if (t && t.classList.contains('dc-sec')) setTimeout(function () { t.scrollIntoView(); }, 50); }
})();
