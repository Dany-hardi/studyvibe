# StudyVibe brand kit

**The mark.** A rounded monoline wordmark: "study" in ink (`#14151C`), "vibe" in terracotta (`#B5482A`) where the V is a check mark, and an orange spark (`#FF6A3D`) as the i-dot. The symbol (favicon, avatar, app icon) is the white check on a terracotta rounded square with the spark (a lighter `#FFD27A` on the icon so it reads on terracotta). Dark theme: letters `#F4F1EA`, terracotta `#E27B57`. Page palette: clay `#B5482A`, ink `#1E1B16`, paper `#F5F0E6`, ochre `#D9A23B`. The designer's original used violet `#5B3DF5` for the V; it was replaced by terracotta everywhere.

Everything goes through `lib/Brand.php`. Never hand-draw the logo and never use webfonts for it (the letters are paths).

## Web
```php
require_once __DIR__ . '/lib/Brand.php';
<head> <?= Brand::headLinks() ?> </head>       // favicon svg+png, apple-touch-icon, theme-color, brand.css, once-per-session flag
<?= Brand::logo('md') ?>                        // sm | md | lg | xl, inline SVG, follows --ink/--clay (light and dark)
<?= Brand::logo('xl', true) ?>                  // intro: types on with a caret, bookmark drops in, one shine sweep
<?= Brand::mark(24) ?>                          // symbol only
```
The intro plays once per browser session (sessionStorage `sv_brand_seen`, set by `headLinks()`), is skipped with `prefers-reduced-motion`, and without JS it simply plays once per page load (pure CSS). Use `animate` only for the landing hero and the live lobby.
Wrap the logo in your own `<a>` when it should link. `Brand::logo()` outputs `role="img" aria-label="StudyVibe"`.

## Email (HTML)
`Brand::emailHeader($bg, $width)` returns a table-safe header with `logo-email.png` (400 px, shown at 190 px, retina) hosted at `APP_URL/assets/img/logo-email.png`, with alt text "StudyVibe". `Mailer::wrap()` already uses it; every `Mailer::*` template gets it. Colours: `Brand::CLAY` for buttons/headings, `Brand::INK` text, `Brand::PAPER` background. Remote images need `APP_URL` to be public.

## PDF
* Raw writer (`lib/PdfReportBuilder.php`): `$pdf->drawLogo($x, $y, $widthPt, $mono = false)` draws the real vector wordmark (no image, no font). `addCoverPage()` does it for you. `Brand::pdfHeight($w)` gives the height.
* Anywhere else: `Brand::pdfOps($x,$y,$w,$mono)` returns PDF operators, or use the PNG `Brand::pdfPng($mono)`.
* Use `$mono = true` for black-and-white print.

## LaTeX
Add `Brand::latexPreamble()` to the preamble (graphicx, xcolor, `svclay svink svpaper svochre`, macros `\svlogo[width]` and `\svlogomono[width]`). Compile through `LatexCompiler::compile()`, which stages `svlogo.png` and `svlogo-mono.png` next to `document.tex`. If you call pdflatex yourself, copy `Brand::pdfPng(false/true)` to those two names in the build directory.

## Excel / CSV
`SpreadsheetExporter` writes a banner row with the wordmark as rich text (Georgia, V in clay), the sheet name and date, and header cells in paper-2 with a clay rule. SpreadsheetML cannot hold an image, so this is the faithful substitute. CSV stays plain.

## Files in `assets/img/`
`logo-mark.svg` symbol (terracotta square, white check, spark), `logo-wordmark-white.svg`, `logo-wordmark.svg` / `logo-wordmark-dark.svg` / `logo-mono.svg` (single ink colour), `favicon.svg` (switches clay in dark tabs), `favicon.png` 32, `apple-touch-icon.png` 180, `logo.png` 1024 transparent, `logo-mono.png`, `logo-email.png` 400, `logo-mark.png` 512.

## Regenerating
The artwork lives in `StudyVibe Logo.pdf` (raster pages from the designer). `scripts/brand/build.py` traces it into vector paths, recolours the V to terracotta and writes `assets/img/*.svg` and `lib/BrandData.php`; `scripts/brand/render.js` renders the PNGs (logo, mono, email, mark, favicon, apple-touch-icon).
```
mkdir /tmp/logo && pdfimages -png "StudyVibe Logo.pdf" /tmp/logo/im
python3 scripts/brand/build.py /tmp/logo && node scripts/brand/render.js
```
To change a colour edit the constants at the top of `build.py` (and `Brand::CLAY/WORD/SPARK` plus `assets/css/brand.css`), then regenerate everything together.
