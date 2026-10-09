# StudyVibe v2 redesign — brief for every agent

Read `docs/DESIGN_V2.md`, `assets/css/sv2.css` (tokens), `assets/css/landing.css` and `index.php` (the finished reference for look and feel) BEFORE designing.

## The look
Quiet campus: paper, ink, one warm accent (clay #B5482A). Fraunces for headings (one italic emphasised word at most), Hanken Grotesk for UI, tabular numerals for scores/timers. Light and dark theme through the tokens in sv2.css (`html.dark`). Load fonts like index.php does and include `/assets/css/sv2.css` plus `/assets/css/brand.css`.

## Hard rules
- No card tilt, no scale-on-hover, no glow, no gradients as decoration, no glassmorphism, no emoji as icons, no neon, no electric blue, no purple. Motion = one easing `cubic-bezier(.2,.7,.2,1)`, short fade/rise, honour `prefers-reduced-motion`.
- No invented numbers, testimonials, partners or fake stats. Use real data or leave the block out.
- Think like a product designer, not a template: decide what the user comes here to do, put that first, cut everything else. Hierarchy by type size and spacing, not by boxes inside boxes. Comfortable density for tables. 44px touch targets. Visible focus rings.
- Responsive from 360px up. Keyboard usable. Contrast AA.
- The logo ALWAYS comes from `lib/Brand.php` (`Brand::logo('md')`, `Brand::mark()`), never hand-drawn inline. The logo agent is replacing its internals in parallel; call only those public methods.
- Colours only from tokens (`var(--paper)`, `--card`, `--ink`, `--ink-2`, `--ink-3`, `--line`, `--clay`, `--clay-press`, `--clay-soft`, `--pine`, `--ochre`, `--ok`, `--danger`). Zero hardcoded greens.
- Bilingual: every visible string must exist in FR and EN. Use `TranslationService` (`locales/fr.php`, `locales/en.php`: add keys, prefix yours with your area, e.g. `sd_`) or the `$T[$lang]` array pattern from index.php. Do not leave raw French or English literals in markup you rewrite. JS strings: pass through a `window.SV_T`-style object.
- PRESERVE behaviour: keep every PHP query, endpoint URL, form field name, element ID and JS hook that existing JS or other files depend on (grep before renaming anything). You are re-designing markup, CSS, layout and interaction polish; do not change business logic, DB schema or API contracts. If something must change, say so in your final report.
- Only edit the files assigned to you. Other agents are editing other files at the same time. Shared files (assets/css/app.css, assets/js/app.js, lib/Brand.php, sv2.css) are READ-ONLY for you; if you need a shared utility, put it in a new file named after your area (e.g. `assets/css/student.css`).
- Existing global CSS (`assets/css/app.css`, class prefix `sv-`) still loads on old pages; when you rewrite a page you may stop using it there, but do not delete it.

## How to test
- Web server is already running at http://127.0.0.1:8123 and MySQL at 127.0.0.1:3306 (docker container `studyvibe-mysql`). Do not restart them.
- Test logins (local dev DB): dev-student@studyvibe.local, dev-teacher@studyvibe.local, dev-promoter@studyvibe.local, password `Test1234!`. They are created for this work; you may add rows to the local DB (courses, lessons, sessions) to get realistic data, but do not drop or truncate anything.
- Screenshots: `node ~/.claude/jobs/5a0facc7/tools/shot.mjs <student|teacher|promoter|anon> <path> <out.png> [w=1360] [h=900] [dark=0|1] [fullpage=0|1] [click-selector]` then Read the PNG. Take desktop light, desktop dark, and 390px mobile for every screen you touch, and actually look at them. Console errors are printed. Save screenshots under `$CLAUDE_JOB_DIR/tmp/<your-area>/`, never in the repo.
- Run `php -l` on every PHP file you edit. Click through every interactive state you can reach (modals, tabs, empty states, error states).

## Done means
Every screen in your scope looks like it belongs to the landing page, works in light and dark and on mobile, loses nothing it did before, and has no console errors. Do not commit. Final report: files changed, screens verified (with what you saw), anything you could not verify, anything you changed in behaviour.
