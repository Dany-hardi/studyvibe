<?php
declare(strict_types=1);

require_once __DIR__ . '/Notifications.php';
require_once __DIR__ . '/EmailQueue.php';
require_once __DIR__ . '/RateLimit.php';

/**
 * The questions and answers under a lesson, between the students of a course and its teacher.
 *
 * Who may read and write: a student enrolled in the course, the teacher who owns it, a promoter. Nobody else (this used to be open to
 * any signed-in account, whatever the lesson).
 *
 * The loop: a student asks (or answers under a thread) -> the teacher is told in the dashboard (bell and the badge of the "Questions"
 * tab); the teacher replies -> the student is told by notification and by email. Both are told once per question/reply.
 */
final class LessonQA
{
    public const MAX = 2000;
    public const MAX_REPLY = 3000;

    /** The lesson with its course and teacher, or null. */
    public static function context(PDO $pdo, int $lessonId): ?array
    {
        $st = $pdo->prepare(
            "SELECT l.id AS lesson_id, l.title AS lesson_title, c.id AS course_id, c.title AS course_title, c.teacher_id
             FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id JOIN courses c ON c.id = ch.course_id WHERE l.id = :id"
        );
        $st->execute(['id' => $lessonId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array $user a row of users (id, role) */
    public static function mayAccess(PDO $pdo, array $user, array $ctx): bool
    {
        $role = (string)($user['role'] ?? '');
        if ($role === 'promoter') {
            return true;
        }
        if ($role === 'teacher') {
            return (int)$ctx['teacher_id'] === (int)$user['id'];
        }
        if ($role === 'student') {
            $st = $pdo->prepare('SELECT 1 FROM enrollments WHERE student_id = :s AND course_id = :c LIMIT 1');
            $st->execute(['s' => $user['id'], 'c' => $ctx['course_id']]);
            return (bool)$st->fetchColumn();
        }
        return false;
    }

    /**
     * @return array{ok:bool,error?:string,comment?:array} errors: not_found, forbidden, empty, too_long, bad_parent, too_many
     */
    public static function post(PDO $pdo, array $user, int $lessonId, string $text, ?int $parentId): array
    {
        $ctx = self::context($pdo, $lessonId);
        if ($ctx === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (!self::mayAccess($pdo, $user, $ctx)) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'error' => 'empty'];
        }
        if (mb_strlen($text) > self::MAX) {
            return ['ok' => false, 'error' => 'too_long'];
        }
        if ($parentId) {
            $p = $pdo->prepare('SELECT 1 FROM lesson_comments WHERE id = :p AND lesson_id = :l AND parent_id IS NULL AND is_hidden = 0');
            $p->execute(['p' => $parentId, 'l' => $lessonId]);
            if (!$p->fetchColumn()) {
                return ['ok' => false, 'error' => 'bad_parent'];
            }
        }
        if (!RateLimit::allow($pdo, 'qa:' . (int)$user['id'], 20, 3600)) {
            return ['ok' => false, 'error' => 'too_many'];
        }
        $pdo->prepare('INSERT INTO lesson_comments (lesson_id, user_id, comment_text, parent_id) VALUES (:l, :u, :t, :p)')
            ->execute(['l' => $lessonId, 'u' => $user['id'], 't' => $text, 'p' => $parentId ?: null]);
        $id = (int)$pdo->lastInsertId();

        // A student's message reaches the teacher
        if (($user['role'] ?? '') === 'student' && (int)$ctx['teacher_id'] > 0) {
            Notifications::send(
                $pdo, (int)$ctx['teacher_id'], 'question', 'Question : ' . $user['name'],
                $ctx['lesson_title'] . ' — ' . mb_strimwidth($text, 0, 160, '…'),
                '/teacher/dashboard.php?course_id=' . (int)$ctx['course_id'] . '&focus=comment-' . $id . '#tab-comments'
            );
        }
        return ['ok' => true, 'comment' => [
            'id' => $id, 'author_name' => (string)($user['name'] ?? ''), 'author_role' => (string)$user['role'], 'comment_text' => $text, 'created_at' => date('Y-m-d H:i:s'),
        ]];
    }

    /**
     * The teacher's reply to a comment. The author is told (notification and email) the first time; editing the reply later does not
     * send again.
     *
     * @return array{ok:bool,error?:string} errors: not_found, empty, too_long
     */
    public static function reply(PDO $pdo, int $teacherId, int $commentId, string $reply): array
    {
        $st = $pdo->prepare(
            "SELECT lc.id, lc.comment_text, lc.replied_at, lc.user_id, l.id AS lesson_id, l.title AS lesson_title, c.id AS course_id, c.title AS course_title,
                    u.name AS author_name, u.email AS author_email, u.lang AS author_lang, u.role AS author_role, t.name AS teacher_name
             FROM lesson_comments lc
             JOIN lessons l ON l.id = lc.lesson_id JOIN chapters ch ON ch.id = l.chapter_id JOIN courses c ON c.id = ch.course_id
             JOIN users u ON u.id = lc.user_id LEFT JOIN users t ON t.id = c.teacher_id
             WHERE lc.id = :id AND c.teacher_id = :t"
        );
        $st->execute(['id' => $commentId, 't' => $teacherId]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $reply = trim($reply);
        if ($reply === '') {
            return ['ok' => false, 'error' => 'empty'];
        }
        if (mb_strlen($reply) > self::MAX_REPLY) {
            return ['ok' => false, 'error' => 'too_long'];
        }
        $first = $c['replied_at'] === null;
        $pdo->prepare('UPDATE lesson_comments SET teacher_reply = :r, replied_by = :u, replied_at = NOW() WHERE id = :id')
            ->execute(['r' => $reply, 'u' => $teacherId, 'id' => $commentId]);

        if ($first && (int)$c['user_id'] !== $teacherId && $c['author_role'] === 'student') {
            $url = rtrim((string)APP_URL, '/') . '/student/dashboard.php?course_id=' . (int)$c['course_id'] . '&lesson_id=' . (int)$c['lesson_id'];
            Notifications::send(
                $pdo, (int)$c['user_id'], 'reply', 'Votre enseignant a répondu',
                $c['lesson_title'] . ' — ' . mb_strimwidth($reply, 0, 160, '…'), '/student/dashboard.php?course_id=' . (int)$c['course_id'] . '&lesson_id=' . (int)$c['lesson_id']
            );
            EmailQueue::enqueue($pdo, (int)$c['user_id'], (string)$c['author_email'], 'comment_reply', [
                'name' => (string)$c['author_name'], 'lesson_title' => (string)$c['lesson_title'], 'course_title' => (string)$c['course_title'],
                'question' => (string)$c['comment_text'], 'reply' => $reply, 'teacher' => (string)($c['teacher_name'] ?? ''),
                'lang' => ($c['author_lang'] ?? 'fr') === 'en' ? 'en' : 'fr', 'url' => $url,
            ], 'qa-reply:' . $commentId);
            EmailQueue::kick();
        }
        return ['ok' => true];
    }
}
