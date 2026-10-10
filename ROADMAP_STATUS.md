
## 10 October 2026: channels, reminders, admin, marking guide
- Student → teacher: requests (mark review, result question, free message) with a teacher inbox, one answer per request, notifications and emails both ways; lesson questions hardened (access check, same-site POST, rate limit). `lib/StudentRequests.php`, `lib/LessonQA.php`, `api/requests.php`, `teacher/requests.php`, migration 2.6l. Test: `tests/integration/channels_flow.php` (75).
- 15-minute reminder before live evaluations (`lib/LiveReminders.php`, cron every minute); administrator creates any account and is the only one who deletes users (`lib/AdminUsers.php`).
- Guides for teachers (`teacher/partials/guides.php`): 24 illustrated cards (marking and import), each opens a full-page reader with previous/next; search and filters; marking cards also at the bottom of "Questions et modération". Illustrations: unDraw (MIT) in `assets/img/guides/`.
- Student guides (`student/partials/guides.php`, tab "Aide" in the student space): 40 illustrated cards in 7 themed sections, FR/EN, same reader as the teacher guides (`lib/GuideKit.php`). Content in `student/partials/guides-content-{fr,en}.php`, illustrations in `assets/img/student-guides/` (unDraw, MIT).
- Fixed: the free evaluations list showed the score as "/ 20"; it is a percentage.
