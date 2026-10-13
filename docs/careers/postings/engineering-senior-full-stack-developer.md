---
title: Senior Full-Stack Developer (PHP and MySQL)
team: engineering
location: Douala, Cameroon
work_mode: hybrid
type: full_time
level: Senior
openings: 2
closes: 2026-12-15
salary: Discussed with shortlisted candidates, based on experience
status: open

screening: on
screening_threshold: 35
auto_decline_days: 0
min_years: 5
min_education: bachelor
---

## Summary

Build and run the PHP and MySQL platform behind StudyVibe's live evaluations, courses and certificates, from the database to the browser, and ship improvements that thousands of students and teachers use every week.

## About the role

StudyVibe is a learning platform for universities and training centres. Teachers build courses, students follow them and sit evaluations (live, or at their own pace), the platform marks the answers automatically, and it issues certificates that anyone can verify online. The application is written in PHP 8 with MySQL and plain JavaScript, with no front-end framework, runs in Docker, and is used in French and English.

You will join the engineering team as one of its most experienced developers. You own features from the first conversation with the person who needs them to the day they run in production: you design the data model, write the PHP, build the interface, test it, ship it, and look at how it behaves afterwards. You also review other developers' code and help keep the codebase simple to read.

The hardest parts of the product are the live evaluations (dozens or hundreds of students must start on the same second, every answer must be saved without loss, and the mark must be computed on the server), the correctness of marks and certificates, and keeping the application fast on slow mobile connections. Expect to work on all three.

In your first month you will fix real bugs and ship one small feature, so that you learn the codebase and the way we release. By the end of your third month you are expected to have owned and shipped a medium-sized feature from start to finish. You will know the role is going well when what you ship works the first time in production, is covered by automated tests, and is easy for other developers to change.

This is a hybrid position based in Douala. You work at least three days a week from our Douala office and the other days from home, during West Africa Time (WAT, UTC+1) business hours. You must already live in Douala or be able to move there before your start date. The contract is full-time.

## Responsibilities

- Design, build, test and ship features across the PHP back end, the MySQL database and the JavaScript front end, from the first specification to production.
- Keep live evaluations reliable: keep start times synchronised to the server clock, make sure no answer is ever lost, and compute every mark on the server.
- Write and review database migrations and queries; find slow queries with EXPLAIN and fix them with indexes or a better design.
- Protect users' data: validate every input, escape every output, enforce who may see what, and fix security findings quickly.
- Write automated tests for the code you ship and keep the existing test suite passing.
- Review other developers' code within one working day, with clear and kind comments.
- Investigate production incidents from logs, find the root cause, fix it, and write a short note on what happened and how it will be prevented.
- Improve the speed of pages on slow mobile connections: smaller payloads, fewer requests, caching.
- Maintain the Docker image and the deployment scripts, and document anything another developer would need to know.
- Work directly with teachers, administrators and the customer-success team to understand a problem before writing code.

## Requirements

- At least 5 years of professional experience building web applications with PHP (PHP 8 or later), including at least 2 years as the main developer of an application running in production.
- Strong MySQL or MariaDB skills: you design normalised schemas, write migrations, write joins and aggregate queries by hand, and read an EXPLAIN plan to fix a slow query.
- Strong JavaScript (ES6 and later) without relying on a framework: the DOM, fetch, events, forms, accessibility basics, and behaviour on mobile browsers.
- Solid knowledge of HTTP and REST: methods, status codes, caching headers, sessions and cookies.
- Practical knowledge of web security: the OWASP Top 10, password hashing, protection against CSRF and XSS, and prepared statements against SQL injection.
- Daily use of Git, with branches and pull requests.
- Experience writing automated tests (unit or integration) for back-end code.
- Spoken and written English and French good enough to discuss a design and write documentation in either language.
- A bachelor's degree in computer science or a related field, or equivalent professional experience that you describe in your application.
- The right to work in Cameroon, and the ability to work from our Douala office at least three days a week.

## Nice to have

- Experience with time-critical systems such as online exams, ticketing or auctions.
- Experience building progressive web apps: service workers, offline mode, installable apps, web push notifications.
- Docker and Linux server administration (Apache, cron, logs).
- Experience with a learning management system or another education product.
- Knowledge of web accessibility (WCAG 2.1 level AA).
- Experience measuring and improving front-end performance.
- Contributions to open-source projects.

## Benefits

- Hybrid working: three days in the Douala office, two days from home.
- Real ownership: you decide how a feature is built and see it used by real students within days of shipping it.
- A small team where code review matters and where your opinion is heard.
- A laptop and the tools you need to do the job.
- Time and a budget for learning (courses, books, conferences), agreed with your manager.

## Screening

### Must have

- !php
- mysql|mariadb
- javascript|js
- git
- rest|api|http

### Nice to have

- docker
- pwa|service worker|progressive web
- accessibility|wcag|a11y
- phpunit|unit test|integration test|automated test
- lms|moodle|e-learning|elearning
