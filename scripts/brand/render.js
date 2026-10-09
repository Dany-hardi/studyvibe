/* Renders the PNG brand assets from the SVGs in assets/img (run after build.py).
   Usage: node scripts/brand/render.js   (needs playwright-core and a Chrome/Chromium at CHROME or /usr/bin/google-chrome) */
const { chromium } = require('playwright-core');
const fs = require('fs'), path = require('path');
const IMG = path.join(__dirname, '..', '..', 'assets', 'img') + '/';
const svg = f => fs.readFileSync(IMG + f, 'utf8');
const jobs = [
  // file, svg, width, height (null = by ratio), background
  ['logo.png', 'logo-wordmark.svg', 1024, null, null],
  ['logo-mono.png', 'logo-mono.svg', 1024, null, null],
  ['logo-email.png', 'logo-wordmark.svg', 400, null, null],
  ['logo-mark.png', 'logo-mark.svg', 512, 512, null],
  ['favicon.png', 'logo-mark.svg', 32, 32, null],
  ['apple-touch-icon.png', 'logo-mark.svg', 180, 180, 'full'],
];
(async () => {
  const b = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  for (const [out, file, w, h, bg] of jobs) {
    let s = svg(file);
    const vb = s.match(/viewBox="([\d.\s-]+)"/)[1].split(/\s+/).map(Number);
    const hh = h || Math.round(w * vb[3] / vb[2]);
    if (bg === 'full') {            // iOS rounds the corners itself: bleed the colour to the edges
      s = s.replace(/<path class="bg"[^>]*\/>/, '<rect class="bg" width="512" height="512" fill="#B5482A"/>');
    }
    const p = await b.newPage({ viewport: { width: w, height: hh }, deviceScaleFactor: 1 });
    await p.setContent(`<body style="margin:0;background:transparent"><div style="width:${w}px;height:${hh}px">${s.replace('<svg ', '<svg width="' + w + '" height="' + hh + '" ')}</div></body>`);
    await p.screenshot({ path: IMG + out, omitBackground: true, clip: { x: 0, y: 0, width: w, height: hh } });
    await p.close();
    console.log(out, w + 'x' + hh);
  }
  await b.close();
})();
