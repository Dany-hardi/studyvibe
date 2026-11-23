# StudyVibe: next set of updates

*22 November 2026. Written after reading the deploy setup (Dockerfile, DEPLOYMENT.md, docker/), the roles in `schema.sql`, the quiz timer code in `teacher/dashboard.php`, and the assignment code in `lib/Assignments.php`.*

Six pieces of work. Hosting comes first because nothing else matters if half of Cameroon can't open the site.

---

## 1. Hosting

### What the app actually needs

The app is more than "PHP + MySQL". The Dockerfile installs LaTeX (PDF reports), GD/zip/exif, a cron job every minute (email queue, live reminders) and background workers (`lib/*-worker.php`). So **classic shared hosting (Hostinger, o2switch, LWS...) won't run it fully**: no LaTeX, no long workers. You need something that runs the Docker image or a real Linux box.

Two things also tie the app to a single server today:
- PHP sessions are files on disk (`session.save_handler = files`).
- Uploads live in the local `uploads/` folder.

That's fine for one server. Before running two servers behind a load balancer, both have to move (step 6).

### Why Render fails on Orange

Not proven yet. There is no public report of `*.onrender.com` being blocked on Orange Cameroon, but there are reports of Render failing on some networks elsewhere (Nigeria, PLDT in the Philippines, some institution Wi-Fi). So the first step is a test, not a move: from an Orange SIM, open the app on the Render address, then on a domain behind Cloudflare, and compare (and with another operator or a VPN).

One limit no host can fix: when the authorities restrict the internet (as on Orange and Camtel during the November 2025 election protests), every site is affected.

### The plan (prices checked 23 November 2026)

| Stage | Where | Database | Files and backups | Cost |
|---|---|---|---|---|
| **0. This week** | Still Render, behind a `.com` on **Cloudflare** (proxy on) | Aiven, as now | as now | about 10.50 USD a year |
| **1. Free** | **Oracle Cloud Always Free**, ARM, 2 cores / 12 GB RAM / 200 GB disk, home region Johannesburg; the same Docker image built for ARM; **Cloudflare Tunnel** so no port is open | MySQL 8 on the same server | nightly `mysqldump` to **Cloudflare R2** (10 GB free) | 0 |
| **1b. If Oracle refuses** | **Vultr Johannesburg**, 2 GB plan (1 GB is too small for MySQL + LaTeX) | MySQL on the server | R2 | about 12–18 USD a month |
| **2. Real usage** | **Vultr Johannesburg**, 2–4 GB | on the server, or Vultr managed MySQL | R2 | about 20–40 USD a month |
| **3. Exam peaks** | 2 servers behind Cloudflare or Vultr load balancing | managed MySQL with a read copy | uploads on R2 | depends on traffic |

Cloudflare stays in front at every stage (DNS, HTTPS, cache of `assets/`, protection), because that's the piece most likely to fix the Orange problem.

**Notes:**
- **Domain:** a `.com` at Cloudflare Registrar is sold at cost, about 10.50 USD a year, same price at renewal. `.cm` costs 85–125 USD a year at most registrars; `.com.cm` is about 21 USD. Take the `.com` now, add a Cameroon name later if wanted.
- **Oracle Free changed:** since 18 August 2026 the free ARM allowance is 2 cores and 12 GB (it was 4 and 24). Still enough for the app, MySQL and the workers. Sign-up asks for a card (no charge). The home region can't be changed later, and Johannesburg sometimes has no free ARM capacity (creating the server then fails). A free server that stays almost idle for 7 days can be taken back: keep the nightly backups so it can be rebuilt in an hour.
- **Hetzner dropped:** prices went up twice in 2026 (April and June) and new customers face limited provisioning. Its servers are in Europe anyway.
- **DigitalOcean dropped:** no region in Africa.
- **Fly.io:** has Johannesburg and runs Docker, but no free tier, prices that are hard to predict, and MySQL is awkward there. Not chosen.
- **AWS Cape Town:** the closest big cloud, but costly for this stage.
- **Shared hosting** (Hostinger, LWS, o2switch): still no, no LaTeX and no long workers.
- **Stay on MySQL:** the code uses MySQL syntax and `GET_LOCK`; Postgres-only hosts (Neon, Supabase) are out.
- **Before stage 3:** sessions must leave the server's disk (database or Redis) and uploads must go to R2 (order of work, step 9).

**My pick:** stage 0 this week and the Orange test, then Oracle Free in Johannesburg with MySQL on the box and backups to R2, then Vultr Johannesburg once real users come in.

Sources (checked 23 November 2026): Oracle free tier [docs](https://docs.oracle.com/en-us/iaas/Content/FreeTier/resourceref.htm) and [the August cut](https://news.ycombinator.com/item?id=49183750); [Hetzner 2026 prices](https://northflank.com/blog/hetzner-cloud-server-price-increases); [Vultr Johannesburg listings](https://valebyte.com/en/vps/south-africa/?provider=vultr) (confirm on vultr.com); [Cloudflare Registrar](https://developers.cloudflare.com/registrar); [.com.cm](https://www.101domain.com/com_cm.htm) and [.cm](https://www.netim.com/en/domain-name/cm-domain) prices; [Render network issue reports](https://community.render.com/t/solved-infinite-loading-for-all-of-our-services-in-frankfurt-region-from-nigeria/4626); [OONI Cameroon](https://explorer.ooni.org/findings/393608039300).

---

## 2. Course material submissions (public form)

Anyone (student, delegate, friend) can send the PDFs of a course so quizzes are built from real course content.

**Public page** `/contribute.php` (no login):
- Email (required), full name (optional)
- Filière (dropdown) and level
- Course name (text, with suggestions from existing courses)
- Up to **5 PDFs per submission, 10 MB each**
- Optional note ("chapters 1 to 4, 2025 exam included")

**Checks on the server:**
- The file really is a PDF (starts with `%PDF`, same check `Assignments::contentMatches` already does), size and count enforced server side, not only in the browser.
- Rate limit by IP and by email (reuse `lib/RateLimit.php`), plus a hidden honeypot field against bots.
- Files stored outside the web root (like assignment files), served only to allowed roles.
- An email confirms the submission; the sender gets another one when their material is used.

**Data:** `material_submissions` (id, email, name, filiere, level, course_label, course_id nullable, note, status, created_at, reviewed_by, reviewed_at) and `material_files` (id, submission_id, path, original_name, size, sha1). The sha1 spots the same PDF sent twice.

**Status flow:** `new` → `in_review` → `accepted` (linked to a course) / `rejected` (with reason) → `used` (a quiz was generated from it).

**Filière list:** there is no filière table today. Add a small `filieres` table the super teacher / promoter can edit, so the dropdown isn't hard-coded.

---

## 3. Super teacher role

A fourth role between promoter and teacher: `super_teacher`.

**Can:**
- **View** every course, lesson, quiz and assignment of every teacher (read only: no edit, no delete of another teacher's content).
- Receive the material submissions (dashboard tab "Contributions": list, filters by filière/course/status, PDF preview, accept/reject, link to a course).
- Generate quizzes from accepted PDFs (their own quizzes, which they own and can edit).

**Cannot** (stays promoter only): create or delete users, API keys, newsletters, careers/ATS, billing-type settings, observability.

**How:**
- Extend the role ENUM (`Database.php` auto-migration).
- One helper, e.g. `Security::can($user, 'course.edit', $courseId)`, used everywhere instead of the scattered `role === 'teacher' && teacher_id = ?` checks. Today ownership checks are spread across files like `Assignments::ownedSubmission`, so this is the riskiest part: every teacher endpoint must be checked, and tests added for "teacher A can't touch teacher B's course, super teacher can read it but not change it".
- The super teacher logs into the teacher dashboard with extra tabs, not a separate app.

---

## 4. Quiz timer: one rule

**Today** a quiz has a default time per question, each question can override it, CSV imports can carry their own times, code questions have none, and the form explains all that in a paragraph. Too many rules.

**New rule:** the teacher sets one time when creating the quiz and **it applies to every question**. No per-question override in the form, no time column read from CSV, no "times missing" warning.

Decided: **the same number of seconds for each question** (e.g. 45 s per question). **Done** (step 2 below).

What changed:
- Create/edit forms: one field "Temps par question", default 30 s. Code questions keep no limit.
- `live_eval_questions.time_limit` stays in the database but is ignored everywhere (live room, free-pace room, teacher stats, emails, exports). New questions and imports store nothing there.
- The per-question time editor in the session panel is removed.
- Written (open) questions stay as they are: off by default, the quiz creator can switch them on.

---

## 5. Assignments, exercises, grading

The base is solid (see `docs/ASSIGNMENTS_AUDIT.md`): server-side deadline, resubmission, versions, rubrics, release of marks. What's missing is a clear pipeline and help with marking.

### 5.1 One status list, shown the same way to student and teacher

| Status | Student sees | Teacher sees |
|---|---|---|
| `draft` | Not handed in yet | — |
| `submitted` | Handed in, you can still change it until the deadline | Handed in |
| `locked` | Handed in, deadline passed, can't change | Waiting for marking |
| `in_marking` | **Your work is being marked** | In the grading bucket |
| `ai_proposed` | Your work is being marked | Proposal ready, to check |
| `graded` (held) | Your work is being marked | Marked, not released |
| `released` | Mark + feedback | Released |
| `revision` | New version requested | Waiting for new version |
| `late` (flag) | Handed in late | Late |

**Rule:** a student can edit or replace their submission as long as the deadline isn't reached. After the deadline it's locked, except if the teacher asks for a new version.

### 5.2 Grading bucket

- After the deadline (or on demand), the teacher clicks **"Send to marking"**: all locked submissions of that assignment go into the bucket and every student sees "being marked".
- The bucket is a queue: one row per copy, filters (to check / checked / released), and a marking screen with previous / next to go copy by copy, phone-friendly.

### 5.3 Marking engine (no AI, no API cost)

Decided: **no paid AI**. The marking is done by algorithms running on the server, following the rules the teacher sets. It proposes, the teacher confirms.

**What the teacher sets for each assignment (the rules):**
- The rubric: criteria, points per criterion.
- For each criterion: **key points** expected (words, phrases, formulas, numbers), with synonyms and accepted variants, and how many must appear for full / half / no points.
- A **model answer** (optional), used to measure how close a copy is.
- **Penalties**: missing required section, under minimum length, late, copy too similar to another student.
- **Strictness** (lenient / normal / strict): how close a word must be to count (spelling tolerance), partial credit or not.
- **Feedback tone** (encouraging / neutral / direct) and comment templates per level ("Bon travail sur…", "Il manque…"). This is the "sentiment" the teacher gives: the app writes feedback in that voice.

**What the engine does with each copy:**
1. Extracts the text (PDF via `pdftotext`, already installed in the Docker image; DOCX via its XML; plain text). A scanned PDF with no text is flagged "to mark by hand" (no OCR in the first version).
2. Normalises: lowercase, accents removed, French and English stop words removed, light stemming, so "intégrales", "integrale" and "intégrer" can match.
3. **Key point matching** per criterion: exact, synonym, and fuzzy match (Levenshtein distance by strictness), numbers and formulas compared as values. Gives points per criterion.
4. **Similarity to the model answer**: TF-IDF cosine similarity on words and word pairs. Used as a check, and to adjust a criterion if the teacher wants.
5. **Structure checks**: length, required sections or headings present, references present.
6. Similarity between students (the check that already exists in `lib/AssignmentSimilarity.php`) applies the penalty if the teacher set one.
7. Builds the feedback from the teacher's templates and tone: what was found, what's missing, per criterion.
8. Gives a **confidence level**: low when the text is short or unreadable, when few key points were found but the model-answer similarity is high (the student may have used other words), or when the mark is far from the class.

**Then the teacher:** goes through the bucket, sees the found / missing key points highlighted in the copy, confirms or changes the mark in one click. Low-confidence copies come first.

**Reliability is measured:** each assignment stores the engine's mark next to the teacher's final mark, and the dashboard shows the average gap and the % of copies within 1 point. The teacher can see if the rules need tuning (add a synonym, lower strictness) and **re-run the engine** on the whole bucket.

Honest limit: an algorithm reads words, it doesn't understand reasoning. It will be reliable for answers with expected content (definitions, steps, results, key concepts). For essays and open opinion it gives a first sort, and the teacher's check is what makes the mark fair.

All PHP, runs in a background worker like the email queue, zero cost per copy.

### 5.4 Forms

Rework the assignment and exercise forms: grouped sections (instructions, hand-in, deadline & late policy, marking rules), the rubric editor inside the same form, preview of what the student will see, validation messages next to each field, works on a phone.

### 5.5 Student "Notes" tab

A **Notes** (grades) section on the student side: one line per course with the mark obtained (released assignment marks, evaluations, final exam), and a detail view per course. Only released marks show. Note: the lesson companion already has a "Notes" tab for personal notes, so this one will be named "Mes notes" / "Grades" to avoid confusion.


### What was built (23 November 2026)

- **Statuses and lock** (`lib/Assignments.php`: `pipeline()`, `studentPipeline()`, `state()`): a handed-in copy can be replaced until the deadline and is locked after it (accepted late work is only for a first hand-in); a copy in the bucket is locked too. The Devoirs tab and the student's lesson and results use the same list. A held mark reads "being marked" for the student.
- **Grading bucket** (`lib/MarkingBucket.php`, `teacher/marking.php`, reached from "Bac de correction" on each assignment): "Send N copies to marking", filters to check / checked / all, low-confidence copies and copies to mark by hand first, previous / next, "confirm and next", release of held marks, phone layout. Copies arriving after the sending (late work, a requested new version) join the bucket by themselves. Students get an in-app notice.
- **Engine** (`lib/MarkingEngine.php`): exactly section 5.3. Text read once (stored), class-wide TF-IDF, stemmer, fuzzy matching by strictness, numbers by value, sections, penalties (late, short, missing part, copied), feedback in the chosen tone or the teacher's own lines, confidence with reasons. A copy whose key points are there but whose wording is far from the model answer is flagged low (probably right, in other words). Runs in slices from the page and every minute from `lib/marking-worker.php` (cron line added to `docker/studyvibe-cron`).
- **Rules page** (the bucket's "Règles de correction"): strictness, tone, key points per criterion with `|` variants, how many are needed, model answer, length and required parts, penalties, own opening/closing lines. Save, or save and run again.
- **Reliability**: the engine's mark is kept next to the teacher's; the bucket shows the average gap and the share within 1 point (per 20).
- **Penalties kept by the teacher** become lines of the marking grid with negative points; the student sees them.
- **Lesson form**: the assignment part is in four groups (task, handing in, deadline and late work, marking) with messages next to the fields (forbidden format, date already passed, maximum out of range) and the lock rule written out.
- **Mes notes** (`lib/StudentGrades.php`): the student tab "Résultats" is now "Mes notes" and starts with the mark per course: released assignment marks, evaluations and best final exam, each on /20, the course mark being their plain average (said on the page), with the detail of every mark. `#notes` opens it.
- **Tests**: `tests/integration/marking_bucket.php` (52 checks), 19 new unit tests; `assignments_advanced` updated for "being marked".

---

## 6. Load and peak traffic

In order, each step is worth doing on its own:
1. Cloudflare in front, cache static files (`assets/`, images) at the edge.
2. Sessions out of files: into MySQL or Redis, so any server can serve any student.
3. Uploads to Cloudflare R2 (S3 compatible) instead of local disk.
4. Workers (email, AI marking, code review) on their own small process/container, so a marking batch never slows down a live exam.
5. Two app servers behind a load balancer, health check endpoint, deploy one at a time (no downtime).
6. Managed MySQL with a read replica for reports and exports.
7. A load test (k6) simulating 300–1000 students in a live session before each exam season. `docs/PERFORMANCE.md` already has the tuning for one server.

---

## Order of work

| Step | What | Depends on |
|---|---|---|
| 1 | `.com` on Cloudflare in front of Render, test on Orange | nothing |
| 2 | Quiz timer simplification (**done**) | nothing |
| 3 | Super teacher role + permission helper + tests (**done**) | nothing |
| 4 | Material submission form + Contributions tab (**done**) | 3 |
| 5 | Assignment statuses, lock rule, grading bucket (**done**) | nothing |
| 6 | Marking engine (rules, key points, similarity, feedback tone) + reliability stats (**done**) | 5 |
| 7 | Forms rework (assignments) + Notes tab (**done**; exercises are the lesson quizzes, unchanged) | 5 |
| 8 | Move to Oracle Free (Johannesburg), MySQL on the box, nightly backups to R2; Vultr Johannesburg if Oracle refuses | 1 |
| 9 | Sessions/uploads out of the box, workers apart, load test | 8 |

Each step ships on its own, with tests, and one commit (or a few) per step.

## Decisions (22 November 2026)

1. Timer: same time for each question.
2. Super teacher: views everything, generates quizzes, doesn't edit other teachers' content.
3. Marking: no paid AI. Rule-based engine set by the teacher, teacher confirms.
4. Domain: none yet. Buy one when doing step 1 (`studyvibe.cm` via a Cameroonian registrar, or a `.com` on Cloudflare Registrar at cost price).
