# StudyVibe v2 — design direction

**Idea:** a quiet campus. Paper, ink, one warm accent. Nothing glows, nothing tilts.

## Tokens (assets/css/sv2.css)
| Role | Light | Dark |
|---|---|---|
| paper (page) | #F5F0E6 | #15130F |
| card | #FBF8F2 | #221F18 |
| ink | #1E1B16 | #F1EBDD |
| clay (accent, actions) | #B5482A | #E27B57 |
| pine (secondary, timers) | #24402F | #A9C4A8 |
| ochre (highlights) | #D9A23B | #E5B24F |

Type: Fraunces for headings (italic for the one emphasised word), Hanken Grotesk for UI. No monospace.
Motion: one easing `cubic-bezier(.2,.7,.2,1)`, 14px rise + fade, honours prefers-reduced-motion. No tilt, no scale on scroll.
Logo: a bookmark whose notch makes a V, with a second V inside it.

## Phases
1. Done: tokens, logo, landing, login/signup/reset dialog, lobby re-skin, global palette swap in app.css and all pages.
2. Student dashboard: rebuild layout around "what do I do next" (continue lesson, next exam, certificates).
3. Reader, results, certificate pages.
4. Teacher and promoter dashboards.
5. Dark mode polish for the exam room, EN/FR audit of all remaining hardcoded strings.
