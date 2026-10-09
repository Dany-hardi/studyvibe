/* Plays the illustration on the account pages (lottie loaded on demand, still frame for reduced motion). */
(() => {
  const nodes = document.querySelectorAll('[data-lottie]');
  if (!nodes.length) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const run = () => nodes.forEach(el => {
    const a = lottie.loadAnimation({ container: el, renderer: 'svg', loop: true, autoplay: !reduce, path: el.dataset.lottie, rendererSettings: { preserveAspectRatio: 'xMidYMid meet' } });
    a.addEventListener('DOMLoaded', () => { el.classList.add('is-ready'); if (reduce) a.goToAndStop(Math.floor(a.totalFrames * .5), true); });
  });
  const s = document.createElement('script');
  s.src = 'https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie_light.min.js';
  s.onload = run; document.head.appendChild(s);
})();
