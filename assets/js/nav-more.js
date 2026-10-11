/*
 * "More" control for vertical tab lists. When a list is taller than the screen (short laptops, phones in landscape, the teacher drawer),
 * a round "..." button with a chevron appears at the bottom of it. Click: the list slides up to show the tabs left; at the end the same
 * button points up and brings the list back to the top. Nothing is added when everything fits.
 *
 *   <div data-nav-more> ... </div>   the scrolling container (overflow-y: auto)
 */
(function () {
  'use strict';
  var fr = (document.documentElement.lang || 'fr').slice(0, 2) !== 'en';
  var L = { more: fr ? 'Voir plus d’onglets' : 'Show more tabs', top: fr ? 'Revenir en haut' : 'Back to the top', moreT: fr ? 'Plus' : 'More', topT: fr ? 'Haut' : 'Top' };
  var chev = function (d) { return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + d + '"/></svg>'; };
  var down = chev('M6 9l6 6 6-6'), up = chev('M6 15l6-6 6 6');

  function setup(box) {
    var wrap = document.createElement('div'); wrap.className = 'nav-more-wrap'; wrap.hidden = true;
    var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'nav-more';
    wrap.appendChild(btn); box.appendChild(wrap);
    var atEnd = false;
    function update() {
      var room = box.scrollHeight - box.clientHeight;
      if (room <= 6) { wrap.hidden = true; return; }
      wrap.hidden = false;
      atEnd = box.scrollTop >= room - 6;
      btn.innerHTML = '<span>' + (atEnd ? L.topT : L.moreT) + '</span>' + (atEnd ? up : down);
      btn.setAttribute('aria-label', atEnd ? L.top : L.more);
      btn.title = atEnd ? L.top : L.more;
      btn.classList.toggle('is-up', atEnd);
    }
    btn.addEventListener('click', function () {
      var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
      box.scrollTo({ top: atEnd ? 0 : box.scrollTop + Math.max(120, box.clientHeight * 0.7), behavior: reduce ? 'auto' : 'smooth' });
    });
    box.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    if (window.ResizeObserver) { var ro = new ResizeObserver(update); ro.observe(box); Array.prototype.forEach.call(box.children, function (c) { if (c !== wrap) ro.observe(c); }); }
    update(); setTimeout(update, 400);
  }
  function init() { Array.prototype.forEach.call(document.querySelectorAll('[data-nav-more]'), setup); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
