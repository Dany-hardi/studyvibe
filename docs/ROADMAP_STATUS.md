# Roadmap status (product direction report, 9 October 2026)

Done in code, with how to check it. "Not done" items need something outside the repository (a provider account, a business decision) or are too large to do safely in one pass.

## Done

| Item | Where | Check it |
|---|---|---|
| Exam integrity: count tab exits, warn the student on return | `live-session.php`, `api/live-eval-poll.php` (`report_integrity`), session option "Compter les sorties de l'onglet" | Create a session with the option, leave the tab during a question, open the analysis page |
| Options shuffled per student (stable for a student, different between students) | `LiveScoring::optionOrder`, poll returns `option_keys`, client maps back to the original letter | Session option "Mélanger les propositions" |
| True/false questions | Empty option buttons are hidden | Fill only A and B |
| Written answers: several accepted answers (`a\|b`) and numeric tolerance (`2.5~0.1`) | `lib/LiveScoring.php`, used by the room, results page, PDF and emails | `php tests/unit/run.php` |
| Item analysis + integrity report + CSV | `teacher/live-analysis.php`, linked from the session's Export menu | Open it on any session |
| Password rule: 8 characters minimum, common passwords refused | `lib/PasswordPolicy.php`; sign-up, reset, promoter user creation, all matching form hints | Try `password123` |
| Landing positioning: "L'examen en direct, sans accroc", verified-figure note, role names | `index.php`, `promoter/dashboard.php` labels | Open `/` |
| Installable app + offline page; static files cached, pages and API never cached | `manifest.webmanifest`, `sw.js`, `offline.html`, `Brand::headLinks()` | Browser "Install" prompt; go offline and open a page |
| Add to LinkedIn on certificates | `certificate.php` | Student certificate page |
| Unit tests and CI | `tests/unit/run.php`, `.github/workflows/ci.yml` | `php tests/unit/run.php` |
| Deployment guide brought up to date, background mail worker documented | `DEPLOYMENT.md` | |
| Migration helper for deploys | `scripts/migrate.php`, `schema_v14_exam_integrity.sql` | `php scripts/migrate.php` |

## Not done, and why

| Item | Reason |
|---|---|
| Payments, plans and workspaces (multi-institution) | Needs a payment provider account and pricing decisions; workspaces change the data model for every table |
| WhatsApp / SMS notifications | Needs a provider account and approved message templates |
| LTI connector, webhooks, API v2 | Needs a design with a first partner |
| SSO, roster import, 2FA | Each touches the login path; worth its own change with its own tests |
| Proctoring (webcam) | Needs a privacy and legal decision first |
| Question bank with random draw, multi-answer questions, regrade | Schema and teacher UI work across the importer, the AI generator and the exports |
| QR code on the certificate PDF | Needs a QR encoder (none in the project) |
| Splitting `teacher/dashboard.php` | A refactor of about 6,000 lines; do it on its own branch with the tests above as a safety net |
| Replacing the "Advanced Engineering Team" file headers | The right author line is the owner's call |


## Second batch (9 October 2026)

| Item | Where |
|---|---|
| Profile picture for students and teachers (cleaned, scaled to 512 px, kept in the database) | `lib/Avatar.php`, `teacher/update-profile.php`, `student/update-profile.php`, profile window in the teacher dashboard |
| Course cover picture: fixed (a 1 MB database packet limit made photos fail), kept in the database, shown on student cards and the teacher's course list | `lib/MediaStore.php`, `teacher/dashboard.php` |
| Live question picture: root cause fixed (guests could not load it, and nothing kept it); two windows when a question has a picture, zoom on click | `download.php`, `live-session.php` |
| Persistent uploads (library, lesson PDFs, assignments, covers, avatars, question pictures) | `lib/MediaStore.php`, `media_files` and `media_chunks`, `scripts/backfill-media.php` |
| SMS engine (Twilio, Africa's Talking, developer log), queue with retries, background worker | `lib/SmsGateway.php`, `lib/SmsQueue.php`, `lib/sms-worker.php` |
| Phone number: asked at sign-up, compulsory window on every dashboard, verified by a 6-digit SMS code | `lib/Otp.php`, `lib/PhonePrompt.php`, `api/phone.php`, `assets/js/phone-verify.js` |
| SMS to the enrolled students when a live evaluation is activated or moved | `lib/LiveSmsNotifier.php`, `teacher/dashboard.php` |
| Two-factor authentication (authenticator app, recovery codes), optional or required per role | `lib/Totp.php`, `lib/TwoFactor.php`, `api/2fa.php`, `api/2fa-login.php`, `two-factor.php`, `account/security.php` |
| Security: technical errors no longer sent to the browser, developer scripts blocked, same-site check on account endpoints | `auth.php`, `.htaccess`, `lib/Security.php` |
| Tests | `tests/unit/run.php` (50), `tests/integration/account_security_flow.php` (27), `tests/integration/media_store.php` (11) |

Open: a real SMS provider account has to be configured before production (nothing was sent to a real phone; the `log` driver was used), and `getClientIp()` in `auth.php` still trusts forwarding headers, so set up the proxy correctly or switch it to `Security::clientIp()` once `TRUST_PROXY` is set in production.


> SMS is switched off by default (`FEATURE_SMS=false`). To turn it back on: set `FEATURE_SMS=true`, `SMS_DRIVER` and the provider keys in `.env`.


## Exports redesign (9 October 2026)

All PDF, LaTeX and Excel exports (CSV left as it was) now share one design.

| Export | Where | What changed |
|---|---|---|
| Question paper and answer key | `teacher/export-live-questions-latex.php` | Single column, identity lines for the candidate, instructions, 1 point per question, checkboxes (the key marks the right box and gives the justification), written questions get answer lines, **question pictures are included** |
| Grade report | `teacher/export-live-grades-latex.php` | Summary table, distribution of marks as a bar chart, alphabetical marks sheet with a repeated header |
| Order of merit PDF | `teacher/export-live-pdf.php` | LaTeX ranking with ties sharing a rank; the old plain PDF writer stays as the fallback when LaTeX is missing |
| Student correction report | `student/export-evaluation-pdf.php` | Options in the order the student saw them, pictures, written answers shown in words (`2.5 ou 5/2`); no more server log or command shown on failure |
| Promoter PDF | `promoter/export-pdf.php` | Was a redirect to Excel; now a landscape PDF of any sheet (students, audit log, courses...) |
| Excel (all) | `lib/SpreadsheetExporter.php` | Column widths, frozen header, filters, striped rows, number formats, title and facts block, A4 landscape print setup; the live grades sheet now has numeric columns (good answers, out of, percent) |

Typeface: Latin Modern (the vector form of the traditional LaTeX typeface, Computer Modern) in every LaTeX document; the plain `cm` fonts need the `cm-super` package for accents, Latin Modern does not. Layout: `lib/ExportTheme.php`; documents: `lib/ExportDocs.php`.
Tests: `php tests/integration/exports_compile.php [folder]` compiles every document from awkward sample data with the real LaTeX engine (and keeps the PDFs in the folder when one is given).


## Results review, announcements and the new emails (9 October 2026)

| Item | Where |
|---|---|
| "Evaluation finished" window on the teacher dashboard (once per session), leading to the review table: matricule, name, email, mark (x / N), integrity assessment, cancel / restore button per student | `teacher/dashboard.php`, `assets/js/results-review.js`, `assets/css/results-review.css`, `teacher/live-results.php`, also under each session's Export menu ("Examiner les résultats") |
| Integrity assessment: tab exits (when tracked) and identical wrong answers between students; levels nothing / to review / suspicious; presented as signals, the teacher decides | `lib/LiveResults.php` |
| Cancelling a result: the mark is kept aside, the score is removed (leaderboard, exports, certificates ignore it), the student is told by email with the reason; restoring puts it back and tells the student | `lib/LiveResults.php`, `api/live-eval-poll.php` (never recomputes a cancelled score), `lib/LiveMailQueue.php` (drops a queued result mail), student results page and PDF |
| Announcement email to every enrolled student when a session is activated or moved: date, time, duration, numbered instructions, link to the waiting room | `lib/LiveMailNotifier.php`, `lib/EmailQueue.php`, `lib/email-worker.php` |
| All emails redesigned: light, rounded cards, pill badges, soft callouts, fact cards, numbered steps, big mark block; Fraunces and Hanken Grotesk with safe fallbacks; the logo is attached inside each message; plain-text part; proper MIME (quoted-printable, dot-stuffing, Message-ID) | `lib/EmailTheme.php`, `Mailer.php` |
| Tests | `tests/integration/results_review.php` (26), unit tests (69) |


## Contestation of cancelled results and shorter emails (9 October 2026)

| Item | Where |
|---|---|
| Email text smaller and tighter (less scrolling); the announcement now opens with the schedule and the link, then step-by-step joining instructions, rules of conduct (what to do, what not to do) and what follows an exam | `lib/EmailTheme.php`, `Mailer.php` (`liveEvalScheduled`) |
| A student can contest a cancelled result once: page with the reason, their own copy (answers only, no key) and a form; opened from the email (signed link) or from the evaluations list of the dashboard, which now shows "Résultat annulé" and a Contest button instead of "Join" | `student/contest-result.php`, `api/contest.php`, `lib/Contests.php`, `student/dashboard.php` |
| The teacher is told (notification + email) and answers from the review table: restore the result, or keep the cancellation with a written reason; the answer is final and goes to the student by email and notification | `teacher/live-results.php`, `assets/js/results-review.js`, `result_contests` table |
| The old results link of a cancelled result now leads to the contest page instead of an error | `student/evaluation-results.php` |
| Tests | `tests/integration/results_review.php` (45) |
