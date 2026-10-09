<?php
declare(strict_types=1);

/**
 * The order in which a course is taken. Every student endpoint that serves lesson content asks this class,
 * so the rules hold even when someone calls the endpoints by hand.
 *
 *  1. Lessons are taken in course order (chapters by sort_order, then lessons by sort_order). A lesson opens only when
 *     every lesson before it is completed. A lesson whose quiz deadline has passed unfinished is skipped, it cannot
 *     block the rest of the course forever.
 *  2. Inside a lesson, videos are taken one at a time: video N+1 is not even sent to the browser before N is done.
 */
final class LessonFlow
{
    /** @return array<int, array{id:int,title:string,expired:bool}> lessons of the course in reading order */
    public static function orderedLessons(PDO $pdo, int $courseId): array
    {
        $stmt = $pdo->prepare("
            SELECT l.id, l.title, l.quiz_deadline
            FROM lessons l
            JOIN chapters ch ON ch.id = l.chapter_id
            WHERE ch.course_id = :cid
            ORDER BY ch.sort_order ASC, ch.id ASC, l.sort_order ASC, l.id ASC
        ");
        $stmt->execute(['cid' => $courseId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'id'      => (int)$r['id'],
                'title'   => (string)$r['title'],
                'expired' => !empty($r['quiz_deadline']) && strtotime((string)$r['quiz_deadline']) < time(),
            ];
        }
        return $out;
    }

    /**
     * @return array<int, array{locked:bool, blocker:?string, completed:bool, expired:bool}> keyed by lesson id
     */
    public static function status(PDO $pdo, int $studentId, int $courseId): array
    {
        $lessons = self::orderedLessons($pdo, $courseId);
        $stmt = $pdo->prepare("
            SELECT lp.lesson_id FROM lesson_progress lp
            JOIN lessons l ON l.id = lp.lesson_id
            JOIN chapters ch ON ch.id = l.chapter_id
            WHERE lp.student_id = :sid AND ch.course_id = :cid AND lp.completed = 1
        ");
        $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);
        $done = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));

        $result = [];
        $blocker = null;
        foreach ($lessons as $l) {
            $completed = isset($done[$l['id']]);
            $result[$l['id']] = [
                'locked'    => $blocker !== null,
                'blocker'   => $blocker,
                'completed' => $completed,
                'expired'   => !$completed && $l['expired'],
            ];
            if ($blocker === null && !$completed && !$l['expired']) {
                $blocker = $l['title'];
            }
        }
        return $result;
    }

    /** @return string|null title of the lesson that must be finished first, or NULL when the lesson is open */
    public static function blockerFor(PDO $pdo, int $studentId, int $courseId, int $lessonId): ?string
    {
        $st = self::status($pdo, $studentId, $courseId)[$lessonId] ?? null;
        return $st && $st['locked'] ? ($st['blocker'] ?? '') : null;
    }

    /** @return array<int, array{key:string,url:string,label:string}> main video first, then the extra ones; duplicates removed */
    public static function videos(PDO $pdo, int $lessonId): array
    {
        $stmt = $pdo->prepare("SELECT content_type, video_url FROM lessons WHERE id = :id");
        $stmt->execute(['id' => $lessonId]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lesson || !in_array($lesson['content_type'], ['video', 'mixed'], true)) {
            return [];
        }
        $list = [];
        $seen = [];
        $main = trim((string)($lesson['video_url'] ?? ''));
        if ($main !== '') {
            $list[] = ['key' => 'm', 'url' => $main, 'label' => ''];
            $seen[$main] = true;
        }
        $stmt = $pdo->prepare("SELECT id, label, url FROM lesson_videos WHERE lesson_id = :lid ORDER BY sort_order ASC, id ASC");
        $stmt->execute(['lid' => $lessonId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
            $u = trim((string)$v['url']);
            if ($u === '' || isset($seen[$u])) {
                continue;
            }
            $seen[$u] = true;
            $list[] = ['key' => 'v' . (int)$v['id'], 'url' => $u, 'label' => (string)($v['label'] ?? '')];
        }
        return $list;
    }

    /** @return array<string, true> video keys the student has finished in this lesson */
    public static function videosDone(PDO $pdo, int $studentId, int $lessonId): array
    {
        $stmt = $pdo->prepare("SELECT video_key FROM lesson_video_progress WHERE student_id = :s AND lesson_id = :l AND completed_at IS NOT NULL");
        $stmt->execute(['s' => $studentId, 'l' => $lessonId]);
        return array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
    }

    /** True when the lesson has no videos, or every one of them is done. */
    public static function allVideosDone(PDO $pdo, int $studentId, int $lessonId): bool
    {
        $videos = self::videos($pdo, $lessonId);
        if (!$videos) {
            return true;
        }
        $done = self::videosDone($pdo, $studentId, $lessonId);
        foreach ($videos as $v) {
            if (!isset($done[$v['key']])) {
                return false;
            }
        }
        return true;
    }

    /** Key of the first video not yet done, or NULL when all are. */
    public static function currentVideoKey(PDO $pdo, int $studentId, int $lessonId): ?string
    {
        $done = self::videosDone($pdo, $studentId, $lessonId);
        foreach (self::videos($pdo, $lessonId) as $v) {
            if (!isset($done[$v['key']])) {
                return $v['key'];
            }
        }
        return null;
    }

    /** @return array{course_id:int}|null the course a lesson belongs to */
    public static function courseOf(PDO $pdo, int $lessonId): ?array
    {
        $stmt = $pdo->prepare("SELECT ch.course_id FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id WHERE l.id = :id");
        $stmt->execute(['id' => $lessonId]);
        $cid = $stmt->fetchColumn();
        return $cid === false ? null : ['course_id' => (int)$cid];
    }
}
