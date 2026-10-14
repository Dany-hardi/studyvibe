# StudyVibe: Product Direction Report

**To:** CEO
**From:** Product & Architecture
**Date:** 9 October 2026
**Subject:** Rebrand, new positioning and feature roadmap, based on a full audit of the code and the running application

---

## 1. The short version

I read the codebase end to end: the schema (about 40 tables), the three dashboards, the live exam engine, the AI endpoints, the public API, the brand kit, the design docs and the performance report. Then I mapped what exists against what the market needs.

My conclusions, in order of importance:

1. **StudyVibe's real strength is the live exam, not the LMS.** Plenty of products let you upload a course. Very few let a lecturer open a room, have 300 students start on the same second, and get graded results by email a minute later, with no WebSocket, no app install, and guests who have no account. The performance report backs this: p99 of 44 ms at 300 students, 0% errors over about 380,000 requests. That is the headline.
2. **The visual rebrand is mostly finished.** The "quiet campus" identity (paper, ink, terracotta, Fraunces headings, a bookmark-with-a-V logo, bilingual FR/EN, dark mode) is coherent and distinctive. I do not recommend another visual overhaul. I recommend finishing the rollout (dashboards, exam room dark mode, a few leftover pages) and changing the *story* the visuals tell.
3. **The positioning is too generic today.** The landing page says "The course, the exam, the certificate" and "A platform for higher education". That describes every LMS on earth. The product is sharper than its own pitch.
4. **The feature gaps that matter** are exam integrity (the README promises it, the code does not have it), question variety (multiple choice A to D only), mobile and low-bandwidth readiness (no installable app, no offline), and any way to charge money. Those four decide whether StudyVibe stays a very good university tool or becomes a business.
5. **There is a trust gap between the marketing and the engineering.** The landing page claims "40 to 60 students" while the system has been verified at 300. Meanwhile there are no automated tests, a stale deployment guide, and a 6-character minimum password. Fixing these is cheap and removes real risk before growth.

---

## 2. What StudyVibe is today (the audit)

### 2.1 Facts about the build

| Item | Finding |
|---|---|
| Stack | Native PHP 8.x, MySQL/MariaDB via PDO, vanilla JS, no framework, almost no vendor code |
| Size | About 32,000 lines of PHP, 4,500 lines of JS, 13 stylesheets |
| History | 106 commits, first on 24 June 2026, most of the work in a burst on 25 and 26 July. Roughly four months old |
| Languages | French (default) and English, switchable per user |
| Roles | Student, Teacher, Promoter (institution administrator) |
| Hidden extra | A secret "control center" for the platform owner, hidden behind a plain 404 for everyone else |
| Hosting assumptions | Docker, Apache with tuning files, Railway or Render style hosts, timezone set to Africa/Douala |
| Tests | Load and simulation scripts exist (`tests/load`, `tests/sim`). No unit or integration tests, no CI |

### 2.2 The full feature mindmap

```
                                    STUDYVIBE
                                        |
     +-----------------+----------------+----------------+------------------+
     |                 |                |                |                  |
  STUDENT           TEACHER          PROMOTER        LIVE EXAM          PLATFORM
  (Apprenant)      (Enseignant)     (Institution)     ENGINE           SERVICES
```

**STUDENT SPACE** (tabs: home, courses, evaluations, results, certificates, notes, Q&A, AI, profile)
- Enrolment with one click, course catalogue, enrolment and evaluation windows per course
- Guided reader: text, multiple videos, PDFs (PDF.js), resources and links, in one page
- Strict lesson order: lesson N+1 opens only when N is done; videos are released one at a time, enforced server-side
- Quizzes that unlock after reading, with deadlines; lessons with expired quizzes are skipped so nobody is blocked forever
- Time-stamped video notes
- Lesson Q&A with threaded comments and moderation
- AI assistant per lesson: summarize, explain, generate a 3-question self-test, free chat
- Final exam for a course, with attempts tracked
- Open assignments with a deadline and file submission
- Study-time tracking, progress saved across devices, "continue where you left off"
- Gamification: an 18-tier badge engine
- Results, transcript (relevé), evaluation-results PDF export, certification report
- Compulsory profile with matricule (student number) validation
- Notifications (in-app and email), first-login guided tour by a mascot named Daniel

**TEACHER SPACE** (tabs: overview, courses, content, quiz, library, assignments, live evaluation, grades, comments, students, certificates, settings)
- Courses, chapters, lessons, video/PDF/resources
- Course library with compulsory items
- Question import from CSV (95-question and Calculus samples in the repo)
- AI exam generator: reads course material, produces a question bank, saves it
- Assignments: collection, download as ZIP or Excel
- Grades: per lesson, per live session, with score conversion CSV
- Exports in Excel, PDF, CSV and LaTeX (the LaTeX compile route is rare and useful for math and physics departments)
- Comment moderation and replies
- Live session management (below)

**PROMOTER CONSOLE** (tabs: overview, people, academy, content, live, certificates, communications, growth, insights, API, audit)
- Create and deactivate teacher and student accounts, assign teachers to modules
- Modules and course structure
- Manual or automatic certificate issuing, bulk export
- Newsletter and direct messaging, key broadcasts, GDPR-style unsubscribe
- API keys (create, list, revoke) and a public `/api/v1` with courses, modules and certificate verification
- Growth and Insights: anonymous traffic counters, channel attribution through `?src=` tags (QR campaigns), funnel, engagement, learning stats, platform health, auto-generated recommendations
- AI institutional audit
- Audit log

**LIVE EXAM ENGINE** (the crown jewel)
- Waiting room with live count of who has arrived
- Server-clock synchronised countdown, per-question timers, per-question time override, image questions
- Guest entry by email (accepted domains only), no account needed; lookup tells whether the email already has an account
- Synchronous and asynchronous modes (async has a deadline)
- Teacher controls: pause, resume, reset, live statistics
- Server-side scoring, results emailed through a background mail queue with a worker
- Verified performance: 300 students end to end, 2,000 simulated students with zero errors

**PLATFORM SERVICES**
- Self-healing schema (the app creates and alters its own tables at runtime)
- Custom PDF writer, custom spreadsheet exporter, LaTeX compiler, mailer, i18n
- Brand system shared by web, email, PDF, LaTeX and Excel from one source (`lib/Brand.php`)
- Rate limiting for login and API, login attempt tracking, audit logs, verified email, password reset tokens

### 2.3 What is genuinely strong

- **Live exam throughput** with simple infrastructure. Cutting 34 SQL statements per poll to 3 is the sort of work that wins universities that have had a bad exam night.
- **Guest access to a live exam.** An external candidate can sit the exam with an email address and still get a score and, if applicable, a certificate. That serves professional training, entrance tests and recruitment as much as university classes.
- **Certificates that verify publicly**, with a code anyone can paste on the landing page, plus an API endpoint for the same check.
- **A real brand system.** One logo source renders correctly in a browser, an email, a PDF, a LaTeX document and an Excel banner. Few products this young have that.
- **Output formats that match how African and French universities work**: Excel, PDF relevé, LaTeX, matricule rules (`YY L NNN(N)`), a French-first experience.
- **Operational awareness.** A hidden control center, a performance report with an honest correction note, and a mail queue show the team found and fixed real production pain (the 67-student crash).

### 2.4 What is weak or missing

| Area | Finding | Why it matters |
|---|---|---|
| Exam integrity | Live exam has no tab-switch, focus-loss or fullscreen detection (searched `live-session.php`; the README lists this as a future item). No question or answer shuffling found | The first question any lecturer asks is "how do I stop cheating?" |
| Question types | Live questions are strictly four options A to D with one correct letter. No multi-answer, true/false, numeric, short answer, matching | Limits use to MCQ subjects; math, law, languages and medicine need more |
| Money | No payments, plans or pricing anywhere in the code. "Get started free" has no paid tier behind it | No revenue path |
| Mobile | No manifest, no service worker, no installable app, no offline. Responsive CSS exists | Target students are largely on phones with uneven connectivity |
| Notifications | Email and in-app only. No WhatsApp or SMS | In francophone Africa WhatsApp is the real notification channel |
| Passwords | Minimum length 6, no breach or strength checks, no 2FA for teachers or promoters | Promoters can issue certificates; their accounts deserve more protection |
| Engineering safety net | No automated tests, no CI, `DEPLOYMENT.md` lists migrations only up to v9 while the repo has v13, schema changes happen at runtime inside `Database.php` | Every new feature risks breaking an old one; onboarding a second developer is hard |
| Code shape | `teacher/dashboard.php` is almost 6,000 lines, `live-session.php` 2,700, `promoter/dashboard.php` 1,300. Business logic, markup and CSS share files | Slows every future feature and every redesign |
| Claims on the site | Landing says built for "40 to 60" concurrent students; the performance report verifies 300 | The site undersells the best selling point |
| Naming | "Promoteur / Promoter" is understood locally but opaque in English | Confusing for international buyers |
| Boilerplate | File headers say "Advanced Engineering Team" | Cosmetic, but it should say who actually owns the code |
| Secrets | `.env.example` currently has the SMTP password blank (good, an earlier copy had a real one). Make sure that password was rotated, since git history keeps it | Credential hygiene |

---

## 3. Where StudyVibe should stand: the new positioning

### 3.1 The problem with the current pitch

"The course, the exam, the certificate" is accurate and forgettable. It competes against Moodle (free, huge), Google Classroom (free, trusted) and Canvas (polished, expensive). StudyVibe cannot win a feature checklist against them, and it should not try.

### 3.2 The wedge

Look at what a lecturer in a large cohort dreads: the exam. Connectivity is uneven, rooms are full, cheating is easy, marking takes a week, and the platform has crashed before. StudyVibe has engineered exactly that moment better than anything else in its class.

**Position StudyVibe as the exam room that holds, with the learning and the certificate attached.**

The course content becomes the on-ramp that fills the exam room with enrolled students. The exam becomes the product people recommend. The certificate becomes proof that travels.

### 3.3 Proposed positioning statement

> **For universities, training centres and examining bodies in francophone Africa, StudyVibe is the live-exam platform that runs a whole cohort on the same second, grades it instantly, and issues a certificate anyone can verify. It works on a phone, in a browser, with no install.**

### 3.4 Taglines to test

French first, since French is the default and the core market:

| Option | Reasoning |
|---|---|
| **« L'examen en direct, sans accroc. »** | Direct, promises the one thing that hurt before |
| **« Toute la promo, même seconde, même note juste. »** | Speaks to cohort size and fairness |
| **« Vos examens tiennent. Vos certificats se vérifient. »** | Two proofs in one line |
| **« Du cours au certificat, l'examen au centre. »** | Keeps the full journey but puts the exam at its heart |

My pick to test first: *« L'examen en direct, sans accroc. »* with the current line used as the sub-headline.

### 3.5 Who we are for, in priority order

1. **Universities and grandes écoles** with cohorts of 100 to 500 who run QCM-style continuous assessment and end-of-module tests. This is the beachhead and the segment the code already serves best.
2. **Professional training and certification bodies** who test external candidates (the guest-by-email flow is built for them).
3. **Recruiters and HR teams** running a skills test for a batch of applicants. This needs the integrity features and result export, both within reach.
4. **Secondary schools and exam prep academies** later, once mobile and WhatsApp are in place.

I would stop describing the product as "for higher education" generally. Name the buyer: the exam officer and the head of department.

### 3.6 What to say less of

- "Enterprise-grade" and "Advanced Engineering Team" language in the README and file headers. It reads like a template and a university buyer will not believe it. Say what is true: "tested with 300 students at once".
- Generic LMS claims (chapters, lessons, comments). Keep them, but as supporting detail.

### 3.7 Rename the roles

| Today | Suggested (EN) | Suggested (FR) |
|---|---|---|
| Promoter | Institution admin | Administrateur / Direction pédagogique |
| Teacher | Teacher / Examiner | Enseignant |
| Student | Student / Candidate | Étudiant / Candidat |

"Candidate" matters for the guest flow and for certification customers who are not students.

---

## 4. Brand and presentation

### 4.1 What to keep

The identity in `docs/BRAND.md` and `docs/DESIGN_V2.md` is good and already consistent: terracotta `#B5482A`, ink, paper, ochre; Fraunces and Hanken Grotesk; no gradients, glow or tilt; the V-as-checkmark wordmark; dark mode through tokens. It feels like a calm campus rather than a tech startup, which suits exams and trust.

### 4.2 What to finish

The design plan lists phases. Phase 1 is done. Phases 2 to 5 (student dashboard, reader/results/certificate pages, teacher and promoter dashboards, exam-room dark mode, full FR/EN string audit) are still partly open. The git status shows many files modified and new CSS files not yet committed. Finish and commit this work before adding visual ideas.

### 4.3 Presentation changes I do recommend

1. **Lead the landing page with the live room, not the three-step journey.** The hero already shows an exam card. Make it interactive: let a visitor click through a demo question with a countdown. Show the real number: "tested with 300 students at once".
2. **Replace the "40 to 60 students" note** with the verified figure and a link to a one-page performance summary. A skeptical exam officer will read that page.
3. **Add a "Run an exam in 5 minutes" strip**: import CSV, share link, start. Three screenshots, no marketing words.
4. **A proof page for certificates**: show a sample certificate, the public check, and the API call. Employers are the second audience for this brand.
5. **Daniel the guide** is a nice human touch. Keep him in the product tour, and give him a small role in the exam lobby ("Your exam starts in 40 seconds") so the mascot works for the brand at the moment that matters.
6. **Sales assets**: a two-page PDF per segment (university, training body), built from the existing PDF builder. The `Presentation_StudyVibe_Universite` file in `docs` is a start; align it with the new positioning.
7. **No invented numbers or testimonials.** The design brief already forbids this and it is the right rule. Collect real ones from the first exams and use those.

---

## 5. Feature roadmap

I split this into three horizons. Each item says why it is there and roughly how heavy it is. "S" is days, "M" is a couple of weeks, "L" is a month or more.

### Horizon 1: next 6 weeks. Make the wedge unbeatable

| # | Feature | Why | Size |
|---|---|---|---|
| 1 | **Exam integrity pack**: tab-switch and window-blur counter with warnings, optional fullscreen requirement, question and option shuffling per student, one active session per account/guest, an "integrity report" per student for the teacher | Removes the top objection. The README already promises it | M |
| 2 | **More question types**: true/false, multiple correct answers, numeric answer with tolerance, short text with accepted answers, matching. Change `live_eval_questions` from fixed A to D columns to a flexible options model | Unlocks maths, sciences, law, languages | M |
| 3 | **Question bank with tags and difficulty**, reusable across sessions, random draw of N questions per student | Makes cheating by neighbour pointless and saves teachers hours | M |
| 4 | **Item analysis after an exam**: success rate per question, discrimination, distractor choice, score distribution (the README already lists this as a future export) | Gives lecturers a reason to love the product, and feeds the AI generator | S |
| 5 | **Update public claims and docs**: landing figure, `DEPLOYMENT.md` through v13, security note about the SMTP password rotation | Trust, no code risk | S |
| 6 | **Password and account hardening**: minimum 10 characters or passphrase check, optional 2FA (email code) for teachers and promoters, session list | Certificates carry the institution's name | S–M |
| 7 | **First automated tests and CI**: start with scoring, lesson order rules, matricule validation, certificate code and the poll endpoint | Protects the exam path while you change it | M |

### Horizon 2: 2 to 4 months. Reach and revenue

| # | Feature | Why | Size |
|---|---|---|---|
| 8 | **Installable web app (PWA)**: manifest, service worker, cached shell, resume after dropped connection in the exam room (answers queued and replayed) | The most likely student device is a phone on a weak network. This also removes the "frozen screen" failure from the first production crash | M |
| 9 | **WhatsApp and SMS notifications** for exam reminders, results and certificates (through a provider API) | Where students actually look. Boosts attendance on exam day | M |
| 10 | **Mobile money and card payments** (local providers plus Stripe) for course fees, exam fees and certificate fees | There is no revenue path in the code today | L |
| 11 | **Plans and workspaces**: one institution = one workspace with its own branding, domain whitelist for guests, usage limits and billing. Right now guest domains and branding are global | Turns the product into something you can sell to many institutions | L |
| 12 | **Roster import and SSO**: CSV of students with matricules, optional Google/Microsoft sign-in | Removes the biggest onboarding cost for a department | M |
| 13 | **Certificate upgrades**: shareable page and LinkedIn "add to profile" link, QR code on the PDF, institution logo and signature per workspace, expiry and revocation | Makes every certificate an advertisement and a trust anchor | M |
| 14 | **Result review mode**: after the exam, students see their answers with the correct ones and explanations; teachers can regrade a question (accept two answers) and recompute | Teachers always need this after the first complaint | M |
| 15 | **Teacher dashboard split**: break the 6,000-line file into per-tab partials and API endpoints | A prerequisite for every other teacher-side feature | M |

### Horizon 3: 4 to 12 months. Differentiation

| # | Feature | Why | Size |
|---|---|---|---|
| 16 | **AI exam copilot (teacher side)**: generate variants of a question, rewrite for difficulty, detect ambiguous or duplicate items, draft explanations. Keep teachers in control: AI proposes, human approves | Builds on `ai-teacher.php` that already generates banks | M |
| 17 | **AI study coach (student side)**: after each exam, a personal revision plan from missed questions, tied back to the exact lesson | Closes the loop between exam and course, which is the whole story of the product | M |
| 18 | **Proctoring options** (webcam snapshots, ID check) as an opt-in premium tier, with a clear privacy page. Only after the lighter integrity pack proves demand | Needed for high-stakes certification clients, expensive to do well | L |
| 19 | **Cohort analytics for the institution**: pass rates by course, teacher, year; early warning for students falling behind | The promoter console already collects half the data | M |
| 20 | **Optional WebSocket or server-sent events path** for rooms above about 1,000 students, as the README already notes | Only if real demand appears; polling is working | M |
| 21 | **Open API v2 and webhooks** (result.created, certificate.issued) plus an LTI connector so StudyVibe exams can sit inside Moodle or Canvas | Lets StudyVibe be adopted *beside* a university's existing LMS, which is far easier than replacing it | M–L |
| 22 | **Marketplace of question banks and course templates** | A long-term network effect once there are many teachers | L |

### Things I would deliberately not build yet

- A full social/community layer, forums or a feed. The Q&A under lessons is enough.
- A video hosting pipeline. YouTube embeds and uploaded files serve the current need.
- A native mobile app. A PWA gets 90% of the value at a fraction of the cost.
- More gamification. Eighteen badges are plenty until retention data says otherwise.

---

## 6. Technical foundations to fix alongside the roadmap

These are not glamorous, and they decide whether the roadmap above can ship safely.

1. **Move schema changes out of runtime.** `Database.php` creates and alters tables on live requests. It was a pragmatic choice and it made early deployment easy, but concurrent first requests and failed ALTERs are risks. Adopt numbered migration files with a tiny runner and a version table; keep the idempotent style.
2. **Split the giant files.** Target: no PHP file above about 800 lines. Extract data access into `lib` services and keep views thin. Start with `teacher/dashboard.php`.
3. **Add tests where money and reputation sit:** scoring, certificate issuing, lesson gating, guest entry rules, API key auth, and the poll endpoint. Wire a CI job that runs them plus a PHP lint.
4. **Document the real deployment.** Bring `DEPLOYMENT.md` up to v13 and write the actual production recipe, including the Apache tuning and the mail worker (the performance report already contains most of it).
5. **Secrets.** Confirm the previously committed SMTP password was rotated, and add a pre-commit check for secrets.
6. **Consistent authorship.** Replace the generic "Advanced Engineering Team" headers with the real owner or remove them.

---

## 7. Suggested sequencing and what success looks like

**Weeks 1–2:** finish and commit the visual rollout; fix claims and docs; password and secret hygiene; first tests.
**Weeks 3–6:** integrity pack, question types, item analysis.
**Months 2–4:** PWA with offline-safe exam answers, WhatsApp/SMS, roster import, certificate upgrades, workspaces.
**Months 4–6:** payments and plans, LTI/webhooks, AI copilot.

Measures worth watching, all of which the Insights panel can already feed:

- Live exams run per month and peak concurrent students
- Percentage of exams completed without a support call
- Teachers who run a second exam after the first
- Certificates verified by someone other than the holder
- Sign-ups by `?src=` channel (the attribution is already built)

---

## 8. Is there anything that should *not* change?

Yes, quite a lot, and I want that to be clear so nothing good gets rewritten by accident:

- The live-exam architecture (polling, server clock, file cache, background mail queue). It works and it is measured.
- The visual identity and the brand pipeline.
- Bilingual FR/EN with French as default.
- The three-role structure underneath (only the names change).
- The no-framework approach for now. It is a strength at this size. Revisit only when the team grows.
- The rule against invented statistics and testimonials.

---

## 9. Decisions I need from you

1. **Do we commit to the "live exam first" positioning?** Everything above follows from it.
2. **Which first customer segment:** universities, or certification and training bodies? The guest flow suits the second; the course features suit the first. I lean towards universities for volume and certification bodies for revenue per exam.
3. **Pricing model:** per institution per year, per exam seat, or per certificate. I would pilot per exam seat with a free tier up to a small cohort.
4. **Integrity stance:** light (tab-switch, shuffling) only, or also proctoring later? This affects privacy copy and legal review.
5. **Who owns the next stage of engineering**, since a second developer and a test suite change how fast the roadmap can move.

---

*Correction after implementation:* live questions already support a written-answer type beside multiple choice, so the gap is smaller than section 2.4 says (no multi-answer, matching or question bank). See `docs/ROADMAP_STATUS.md` for what was built from this report.

*Method note: this report is based on reading the repository as it stands today, including uncommitted changes, plus the brand, design and performance documents in `docs/`. I did not run a live security penetration test and I did not interview users, so the market claims (who buys, what they pay) are reasoned judgments that should be checked with a handful of customer conversations before pricing is set.*
