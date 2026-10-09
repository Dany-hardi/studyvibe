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
