# Load test for live evaluations

Simulates a whole room: students open the invitation link, register, wait in the lobby, poll the question every 2 s, answer,
and reach the end where scoring and the results email happen. It measures latency per request type, errors, Apache workers and
database connections.

**Run it against a throw-away copy of the app with its own database, never against production or a database with real data.**

1. Copy the project somewhere else and give the copy its own `.env`: a test database (`mysqldump --no-data` of the real schema is enough, plus one teacher, one module, one course with id 1), `HTTPS_ONLY=false`, `LOGIN_MAX_ATTEMPTS=100000`, and `SMTP_HOST=127.0.0.1`, `SMTP_PORT=4465`, `SMTP_USER=x`, `SMTP_PASS=abcdabcdabcdabcd`.
2. Start the fake mail server (each command costs 120 ms, so one email takes about 1.7 s, like Gmail): `python3 tests/load/smtp-sink.py 0.12`
3. Serve the copy with the Apache settings you want to test, then:

```
export LT_MYSQL="mysql -h127.0.0.1 -P3399 -uroot svload"   # the TEST database
export LT_PORT=8088
ulimit -n 65536
node tests/load/live-eval-load.js --users 300 --q 6 --t 12 --join 30 --tag room300
node tests/load/live-eval-load.js --users 400 --q 6 --t 12 --join 3  --tag spike      # everybody arrives at once
node tests/load/live-eval-load.js --users 400 --q 15 --t 20 --join 30 --tag soak      # five minutes
```

A run passes when every student finished and was scored, errors are 0%, and `poll_quiz` p95 stays under about 300 ms.
The generator and the server share the machine here, so the real server has more headroom than the numbers suggest.
