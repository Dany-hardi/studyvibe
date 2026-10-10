
## 10 October 2026: channels, reminders, admin, marking guide
- Student → teacher: requests (mark review, result question, free message) with a teacher inbox, one answer per request, notifications and emails both ways; lesson questions hardened (access check, same-site POST, rate limit). `lib/StudentRequests.php`, `lib/LessonQA.php`, `api/requests.php`, `teacher/requests.php`, migration 2.6l. Test: `tests/integration/channels_flow.php` (75).
- 15-minute reminder before live evaluations (`lib/LiveReminders.php`, cron every minute); administrator creates any account and is the only one who deletes users (`lib/AdminUsers.php`).
- Marking guide for teachers (`teacher/partials/marking-faq.php`): in "Questions et modération" and in the Help tab, FR/EN.
