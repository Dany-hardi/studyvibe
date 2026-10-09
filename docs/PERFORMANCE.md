# StudyVibe: performance, capacity and backend report

| | |
|---|---|
| **Scope** | Live evaluations (the heaviest workload: a whole room answering at the same time) |
| **Date** | 8 October 2026 |
| **Stack tested** | Apache 2.4 prefork + mod_php 8.2, MariaDB 10.4, 8-core / 31 GB machine |
| **Method** | Simulated students against the app's real endpoints, on an isolated copy with its own database (never production data). See section 8. |

---

## 1. Core figures

| Question | Answer |
|---|---|
| How many students could a live evaluation hold **before** the work? | **About 150.** From the 151st student on, pages and registration timed out. |
| How many **now**? | **300 students verified end to end with browser-like clients. The code and database side ran 2,000 simulated students with 0 errors.** |
| Response time for a student's poll at 300 students | **p50 13 ms, p99 44 ms** (was p99 20 s) |
| Browser-like clients, 300 students, keep-alive off | p50 15 ms, p99 70 to 105 ms; 0.15 to 0.3% of requests lost between my test client and the server (never seen in Apache's log) |
| Compute capacity (code + database), polite clients | 1,000 students: p50 67 ms, p99 199 ms. 2,000 students: p50 112 ms, p99 448 ms, 0 errors (see the correction note) |
| Error rate with the new code and server settings | **0%** (8 runs, about 380,000 requests) |
| SQL statements per poll | **34 before, 3 after** |
| Poll endpoint throughput | **538 → 2,065 requests/s** (3.8×) at 100 concurrent |
| Web workers in use at 300 students | **6 to 10 with keep-alive off** (was 150, the whole pool). With a 1 s keep-alive, real browsers hold about 180 to 190 |
| End-of-exam wait (everyone finishes together) | **53 ms** (was 10 to 40 s) |
| Results emails | 300 of 300 delivered in the background; exam unaffected even when the mail server is down |
| Peak load handled by the code and database | **1,216 requests/s** (2,000 simulated students) |

> **Correction (found while building the control center).** The first set of load tests used Node's HTTP client, which closes its connection right after each response whenever the server says `Keep-Alive: timeout=1`. **Real browsers do not**: they keep the connection open until the server closes it, so with `KeepAliveTimeout 1` each request pins a worker for about one more second. Re-measured with a browser-like client, 300 students hold about 180 to 190 workers with `KeepAliveTimeout 1` (against 4 measured before), and Apache's slow process spawning then causes the first timeouts unless the pool is pre-forked. Consequences: (1) the 1,000 and 2,000 student runs prove the **code and database** can carry that load, they do not prove Apache's connection layer can, so treat them as compute capacity, not as a promise for browsers; (2) the recommended Apache setting is now **`KeepAlive Off`**, which cannot pin workers (6 to 10 in use at 300 students, measured); (3) with 1,000+ browsers, keep-alive off still needs re-testing on dedicated hardware, because on this single laptop the load generator, Apache and MariaDB competed for the same 8 cores (load average above 35) and the measurements stopped being meaningful.

**The honest condition:** these results need the server settings of section 6. The code changes alone remove most of the cost, but Apache's default settings still lock out everyone above 150 students (run D in section 7).

---

## 2. Key metrics (targets and what was measured)

| Metric | Target | Measured at 300 students | At 1,000 | At 2,000 | Status |
|---|---|---|---|---|---|
| Students able to finish and be scored | 100% | 300/300 | 1000/1000 | 2000/2000 | met |
| Error rate | < 0.5% | 0% | 0% | 0% | met |
| `poll_quiz` p95 | < 300 ms | 31 ms | 157 ms | 364 ms | met |
| `poll_quiz` p99 | < 1 s | 44 ms | 199 ms | 448 ms | met |
| Answer submission p99 | < 1 s | 39 ms | 178 ms | 462 ms | met |
| Registration p99 | < 1 s | 27 ms | 79 ms | 133 ms | met |
| Invitation page p99 | < 1 s | 24 ms | 67 ms | 156 ms | met |
| Peak throughput | n/a | 192 req/s | 626 req/s | 1,216 req/s | |
| Busy web workers (peak) | below the pool | 4 | 44 | 121 | met (pool 600) |
| Database connections (max used) | below `max_connections` | 22 | 37 | 103 | met (limit 800) |
| Memory | n/a | n/a | n/a | ~16 MB per worker | |
| Arrival spike | no errors | 400 students in 3 s: 0 errors, slowest request 132 ms | | | met |
| 5-minute exam | no drift | latency flat from minute 0 to 5 (average 15 to 22 ms) | | | met |
| Mail server down | exam unaffected | 0 errors, p99 48 ms | | | met |

Load profile of one student: about **0.55 requests per second** during an exam (a poll every 2 s plus answers). A 300-student room is therefore about 165 requests per second.

---

## 3. Backend features that carry a live evaluation

### 3.1 Server-authoritative timeline
- The server computes what question is active from the session start time and each question's time limit. Browsers never decide the time; each poll returns `server_time_ms` so clocks stay aligned.
- **Pause and resume** by the teacher shifts a stored offset, so every student's timeline moves together.
- **Synchronous** (everyone at once) and **asynchronous** (each student at their own pace, with a deadline) modes share the same endpoint.
- An answer is accepted only for the question currently open; late answers are refused by the server.

### 3.2 Poll API (`api/live-eval-poll.php`)
- `poll_lobby`: counts and countdown, served **without opening a PHP session**.
- `poll_quiz`: current question, time left, answers received, already-answered flag, or the final leaderboard.
- `submit_answer`: validated (question belongs to the session, option valid, window open) and stored with an upsert on a `UNIQUE (registration_id, question_id)` key, so a double tap or a retry never creates a duplicate (0 duplicates in all runs).

### 3.3 Shared cache with single-flight refresh
- Session and questions (2 s), room counts (2 to 3 s), answers received (1 s) and the Top-10 leaderboard (3 s) are computed **once** and shared by the whole room.
- Writes are atomic (temp file then rename), so no reader ever sees a half-written file.
- When an entry expires and many requests arrive together, **one** refreshes it and the others serve the previous value (no stampede on the database).

### 3.4 Light session and write discipline
- The activity timestamp (`last_activity`) is written at most every 4 s per student, instead of on every poll (the "online" window is 10 s).
- The session no longer stores the whole registration row on every poll, so PHP skips rewriting the session file when nothing changed.

### 3.5 Background results email queue
- At the end of the exam a student's request only inserts one row (`live_eval_mail_queue`, unique per participant). It does not talk to the mail server.
- Up to 4 background workers (`lib/live-mail-worker.php`) send the mails, each claiming one row atomically. Failures are retried twice after 90 s. Workers stay alive while a retry is pending, then exit by themselves.
- Mail can also be drained by hand or by cron: `php lib/live-mail-worker.php`.

### 3.6 Database layer
- **Schema migrations run once per deployment**, not per request: a stamp file tied to `Database.php`, guarded by a database lock. Editing `Database.php` re-runs them automatically.
- Optional **persistent connections** (`DB_PERSISTENT=1` in `.env`), off by default.
- Indexes in place for this workload: unique registration per session and email, unique answer per registration and question, per-session indexes on registrations, questions and answers.

### 3.7 Scoring and leaderboard
- Scoring happens on the student's first poll after the end: multiple-choice by exact option, written answers normalised (spaces, decimal comma). The score is stored once.
- Average score in the test was 69% against a simulated 70% accuracy.

---

## 4. Achievements

| # | Achievement | Evidence |
|---|---|---|
| 1 | **Capacity raised from about 150 to 300 verified students with browser-like clients**, and the code and database shown to carry 2,000 simulated students | Runs A to K and the correction note |
| 2 | **End-of-exam stampede removed**: slowest final requests from 10 to 40 s down to 53 ms | Runs B and C |
| 3 | **SQL per poll cut from 34 to 3**, including 9 failing `ALTER TABLE` attempts per request that can take table locks | Section 5 |
| 4 | **Web worker use cut from 150 busy to 4** at 300 students | Runs B, C, E |
| 5 | **Database connection use cut from 150 of 151 to 23** at 300 students | Runs B and C |
| 6 | **Poll endpoint 3.8× faster** (538 to 2,065 requests/s) | Section 5 |
| 7 | **Email decoupled from the exam**: 300 of 300 delivered after the exam; mail server down changes nothing for students | Runs C and J |
| 8 | **Resilient to an arrival spike**: 400 students in 3 s, slowest request 132 ms | Run I |
| 9 | **Stable over time**: 5-minute exam with no latency drift or connection growth | Run K |
| 10 | **Reproducible**: load-test tooling and tuned server configs are in the repository | `tests/load/`, `docker/` |
| 11 | **Docker image verified**: tuned Apache and PHP settings load correctly (syntax OK, 41 Apache processes at start instead of 6, opcache on) | Built on `php:8.2-apache` |

---

## 5. Cost of a single poll, before and after

One student alone in a room, polling every 2 s for 80 s, counting statements on the database:

| | Before | After |
|---|---|---|
| All SQL statements per `poll_quiz` | **34** | **3** |
| `SELECT` per poll | 23 | 2 |
| Failing `ALTER TABLE` attempts per poll | 9 | 0 |
| Database connections opened per poll | 1 | 1 (0 with `DB_PERSISTENT=1`) |

In a full room the "after" figure is lower still, because the shared counters are computed once for everyone. The "before" figure was paid again on every page and every poll.

---

## 6. Actions to apply on the server (required for 300+)

These are configuration, not code. Without them the old 150-student ceiling returns.

### Docker (the repository `Dockerfile` already does this)
- `docker/apache-tuning.conf`: `KeepAlive Off`, `MaxRequestWorkers 600`, `ServerLimit 600`, `StartServers 60` with 40 to 120 spare workers (the pool must be started in advance: Apache forks new workers only gradually), `Timeout 30`.
- `docker/php-tuning.ini`: opcache on (192 MB), errors logged not displayed.
- **Database server** (not in the app image): apply `docker/mariadb-tuning.cnf`: `max_connections 800`, `innodb_buffer_pool_size 512M`, `innodb_flush_log_at_trx_commit 2`.

### XAMPP or plain Apache prefork
- `etc/extra/httpd-default.conf`: `KeepAlive Off`.
- `etc/extra/httpd-mpm.conf` (prefork block): `ServerLimit 600`, `MaxRequestWorkers 600`, `StartServers 60`, `MinSpareServers 40`, `MaxSpareServers 120`.
- `etc/php.ini`: `opcache.enable=1`.
- `etc/my.cnf` under `[mysqld]`: the three MariaDB lines above. Restart Apache and MariaDB.

### VPS with Apache event MPM + PHP-FPM
- Keep-alive does not pin PHP workers there. Use `pm = dynamic`, `pm.max_children = 200`, opcache on, same MariaDB settings.

### Email
- A free Gmail account allows about **500 emails a day**. One 300-student exam sends 300 result emails, so two big exams in a day will exceed it and the queue will mark mails as failed. For regular large exams use a transactional email service (Brevo, Mailjet, SES) in the `SMTP_*` settings. Nothing else changes.

### Sizing guide (from measurements)

| Room size | Busy workers (peak) | DB connections (peak) | Server memory to plan |
|---|---|---|---|
| 300 | 4 to 15 | about 25 | 2 GB |
| 600 | 15 | about 40 | 2 to 4 GB |
| 1,000 | 44 | about 40 | 4 GB |
| 2,000 | 121 | about 105 | 8 GB and 4+ cores |

Keep `MaxRequestWorkers` times 16 MB inside your free memory, and `max_connections` above `MaxRequestWorkers`.

---

## 7. Test results in detail

Students open the page, register, wait in the lobby (poll every 2.5 s), answer every question once, poll every 2 s, finish and get scored. "Locked out" means page or registration requests timing out at 20 s. The generator, the server and a fake SMTP server (1.7 s per email, like Gmail) shared one machine.

| Run | Students | Setup | Outcome | `poll_quiz` p50 / p95 / p99 | Peak req/s | Busy workers | DB conns |
|---|---:|---|---|---|---:|---:|---:|
| A | 300 | Original code, stock Apache (150 workers, keep-alive 5 s), stock MariaDB | **150 locked out**, 150 finished, 25.6% errors | 48 ms / 20 s / 20 s | 116 | 150 | 90 |
| B | 300 | Original code, keep-alive off | 0 errors; **150 workers and 150 of 151 DB connections** at the end | 56 ms / 120 ms / **10.3 s** | 193 | 150 | 150 |
| C | 300 | **New code**, keep-alive off | 0 errors | 13 / 36 / 53 ms | 190 | 8 | 23 |
| D | 300 | New code, **stock Apache** | **150 locked out** (31% errors), CPU idle | 9 ms / 11 s / 20 s | 122 | 150 | 25 |
| E | 300 | New code, keep-alive 1 s, 600 workers | 0 errors | 13 / 31 / 44 ms | 192 | 4 | 22 |
| F | 600 | Fully tuned | 0 errors | 19 / 88 / 119 ms | 378 | 15 | 36 |
| G | 1,000 | Fully tuned | 0 errors | 67 / 157 / 199 ms | 626 | 44 | 37 |
| H | 2,000 | Fully tuned | 0 errors, slowest request 1.26 s | 112 / 364 / 448 ms | 1,216 | 121 | 103 |
| I | 400 | Arrival spike: all within 3 s | 0 errors | 16 / 57 / 79 ms | 385 | 20 | 16 |
| J | 300 | Mail server down during the exam | 0 errors; 300 mails queued, delivered on retry | 13 / 29 / 48 ms | 196 | 8 | 13 |
| K | 400 | 5-minute soak (15 questions of 20 s) | 0 errors, no drift | 16 / 52 / 79 ms | 232 | 15 | 26 |

How to read it:
- **A versus D:** the 150-student wall is Apache's keep-alive behaviour and does not depend on the code. (With a 5 s keep-alive the early client behaved like a browser, so these two runs stand.)
- **B versus C:** what the code changes alone buy (worker use 150 to 8, p99 10.3 s to 53 ms).
- **E to H:** the headroom of the code and database once both are in place. These four runs used the early client, so read them as compute capacity (section 1, correction).

---

## 8. How the tests were run, and how to repeat them

- Fresh database with the real schema (`mysqldump --no-data`), one teacher, one course. A new live session is created for each run.
- Apache prefork with mod_php was started with the settings under test; a fake SMTP server with STARTTLS and 120 ms per command stood in for Gmail.
- Per-student behaviour follows `live-session.php`: page load, registration, lobby polling, question polling, one answer per question at a random moment, finish.
- Server-side samples every 3 s: Apache busy workers, MariaDB connections and running threads, load average.
- To repeat: `tests/load/README.md` and `node tests/load/live-eval-load.js --users 300 --q 6 --t 12 --join 30`.

---

## 9. Limits, risks and recommendations

**Limits of the tests**
- Load generator and server shared one machine over localhost. Real rooms add mobile networks and school Wi-Fi, where slow connections hold a worker longer. The gap between 300 needed and 2,000 tolerated is meant to absorb this, but it is a simulation, not a guarantee.
- Simulated clients use the real endpoints and rhythm, not a real browser (no page rendering, images or on-screen maths).
- The database tuning was not measured separately: stock MariaDB held 300 students; the tuned one is for headroom beyond 1,000 and for write bursts.
- Only live evaluations were load-tested. Course reading, the PDF reader, video chaining, the student dashboard and teacher exports were not.

**Recommendations**
1. Before an important exam, rehearse with a few dozen real phones on the real server and watch Apache `server-status` and MariaDB `Threads_connected`.
2. If anything looks tight, raise `MaxRequestWorkers` first (within the memory available), then the database connection limit.
3. Move to a transactional email provider before regular exams above about 400 students a day.
4. Load-test the course reading and teacher export paths next; they have the same per-request overheads that were removed here, but their behaviour under load is unmeasured.

---

## 10. Files

| Path | Purpose |
|---|---|
| `Database.php` | Migrations once per deployment; optional persistent connections |
| `api/live-eval-poll.php` | Poll API with shared cache, light sessions, queued email |
| `lib/LiveMailQueue.php`, `lib/live-mail-worker.php` | Background results email |
| `docker/apache-tuning.conf`, `docker/php-tuning.ini`, `docker/mariadb-tuning.cnf` | Server settings loaded by the `Dockerfile` |
| `tests/load/live-eval-load.js`, `tests/load/smtp-sink.py`, `tests/load/README.md` | Load-test tooling |
