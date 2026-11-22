<?php
declare(strict_types=1);

require_once __DIR__ . '/Assignments.php';
require_once __DIR__ . '/AssignmentRubric.php';
require_once __DIR__ . '/AssignmentSimilarity.php';
require_once __DIR__ . '/MarkingEngine.php';
require_once __DIR__ . '/MediaStore.php';

/**
 * The grading bucket of an assignment (docs/NEXT_UPDATES_PLAN.md, 5.2 and 5.3).
 *
 *   1. The teacher clicks "Send to marking": every handed-in copy that is not marked yet goes into the bucket (in_marking_at). From
 *      then on the copy cannot be changed and the student reads "your work is being marked". Copies that arrive later (late work, a
 *      new version the teacher asked for) join the bucket by themselves.
 *   2. The engine (MarkingEngine) reads each copy and stores a proposal: points per criterion, penalties, feedback, a confidence level.
 *      It runs in small slices (a time budget) from the bucket page and from the cron worker (lib/marking-worker.php), never twice at
 *      the same time for one assignment (a MySQL named lock).
 *   3. The teacher goes copy by copy, low confidence first, and confirms or changes the mark (Assignments::grade). The engine's mark is
 *      kept next to the teacher's: the gap between them tells whether the rules need tuning.
 */
final class MarkingBucket
{
    /** The lesson (assignment) with its course, only for the teacher who owns the course. */
    public static function lesson(PDO $pdo, int $lessonId, int $teacherId): ?array
    {
        $st = $pdo->prepare(
            "SELECT l.*, c.id AS course_id, c.title AS course_title, c.teacher_id
             FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id JOIN courses c ON c.id = ch.course_id
             WHERE l.id = :l AND c.teacher_id = :t AND l.has_assignment = 1"
        );
        $st->execute(['l' => $lessonId, 't' => $teacherId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function criteria(array $lesson): array
    {
        return AssignmentRubric::decode($lesson['assignment_rubric'] ?? null);
    }

    public static function rules(array $lesson): array
    {
        return MarkingEngine::decodeRules($lesson['assignment_marking_rules'] ?? null, self::criteria($lesson));
    }

    public static function saveRules(PDO $pdo, array $lesson, array $raw): array
    {
        $rules = MarkingEngine::normalizeRules($raw, self::criteria($lesson));
        $pdo->prepare("UPDATE lessons SET assignment_marking_rules = :r WHERE id = :id")
            ->execute(['r' => json_encode($rules, JSON_UNESCAPED_UNICODE), 'id' => $lesson['id']]);
        return $rules;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Into the bucket
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Sends every handed-in, unmarked copy to marking. Each student is told in the app ("being marked").
     *
     * @return array{ok:bool,sent:int}
     */
    public static function send(PDO $pdo, array $lesson, int $teacherId): array
    {
        $pdo->prepare("UPDATE lessons SET assignment_marking_at = COALESCE(assignment_marking_at, NOW()) WHERE id = :id")->execute(['id' => $lesson['id']]);
        $st = $pdo->prepare(
            "SELECT id, student_id FROM lesson_assignment_submissions
             WHERE lesson_id = :l AND in_marking_at IS NULL AND graded_at IS NULL
               AND (revision_requested_at IS NULL OR revision_requested_at < submitted_at)"
        );
        $st->execute(['l' => $lesson['id']]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $ids = implode(',', array_map(fn($r) => (int)$r['id'], $rows));
            $pdo->exec("UPDATE lesson_assignment_submissions SET in_marking_at = NOW(), engine_at = NULL, engine_error = NULL WHERE id IN ($ids)");
            require_once __DIR__ . '/Notifications.php';
            $title = (string)($lesson['assignment_title'] ?: $lesson['title']);
            foreach ($rows as $r) {
                try {
                    Notifications::send($pdo, (int)$r['student_id'], 'assignment', 'Devoir en cours de correction', $title, '/student/dashboard.php?course_id=' . (int)$lesson['course_id'] . '&lesson_id=' . (int)$lesson['id']);
                } catch (Throwable $e) { /* a notification never blocks marking */ }
            }
        }
        // The copied-copy penalty needs the similarity check to be fresh
        if (self::rules($lesson)['penalties']['similarity'] > 0) {
            try {
                AssignmentSimilarity::analyse($pdo, (int)$lesson['id'], $teacherId);
            } catch (Throwable $e) { /* the engine runs without it */ }
        }
        return ['ok' => true, 'sent' => count($rows)];
    }

    /** Forgets the proposals of the copies not marked yet, so the engine reads them again with the new rules. */
    public static function rerun(PDO $pdo, array $lesson): int
    {
        $st = $pdo->prepare(
            "UPDATE lesson_assignment_submissions SET engine_at = NULL, engine_score = NULL, engine_confidence = NULL, engine_result = NULL
             WHERE lesson_id = :l AND in_marking_at IS NOT NULL AND graded_at IS NULL AND (engine_error IS NULL OR engine_error = '')"
        );
        $st->execute(['l' => $lesson['id']]);
        return $st->rowCount();
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The engine, in slices
    // ---------------------------------------------------------------------------------------------------------------

    /** How many copies of the bucket still wait for the engine. */
    public static function pending(PDO $pdo, int $lessonId): int
    {
        $st = $pdo->prepare("SELECT COUNT(*) FROM lesson_assignment_submissions WHERE lesson_id = :l AND in_marking_at IS NOT NULL AND engine_at IS NULL AND graded_at IS NULL");
        $st->execute(['l' => $lessonId]);
        return (int)$st->fetchColumn();
    }

    /**
     * Reads and marks the copies waiting in the bucket, for at most $budget seconds.
     *  - first every copy's text is extracted (the slow part: pdftotext), stored once in engine_text
     *  - once all texts are there, every waiting copy is marked (fast, pure PHP) with the whole class as the vocabulary
     *
     * @return array{done:int,pending:int,busy?:bool}
     */
    public static function run(PDO $pdo, int $lessonId, float $budget = 8.0): array
    {
        $lockName = 'sv_marking_' . $lessonId;
        $got = (int)$pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", 0)")->fetchColumn();
        if ($got !== 1) {
            return ['done' => 0, 'pending' => self::pending($pdo, $lessonId), 'busy' => true];
        }
        $start = microtime(true);
        $done = 0;
        try {
            $st = $pdo->prepare(
                "SELECT l.*, c.id AS course_id FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id JOIN courses c ON c.id = ch.course_id WHERE l.id = :l"
            );
            $st->execute(['l' => $lessonId]);
            $lesson = $st->fetch(PDO::FETCH_ASSOC);
            if (!$lesson) {
                return ['done' => 0, 'pending' => 0];
            }

            // 1. Texts
            $q = $pdo->prepare(
                "SELECT id, submitted_file_path FROM lesson_assignment_submissions
                 WHERE lesson_id = :l AND in_marking_at IS NOT NULL AND engine_at IS NULL AND graded_at IS NULL AND engine_text IS NULL ORDER BY id"
            );
            $q->execute(['l' => $lessonId]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $s) {
                if (microtime(true) - $start > $budget) {
                    return ['done' => $done, 'pending' => self::pending($pdo, $lessonId)];
                }
                [$text, $error] = self::readCopy($pdo, (string)($s['submitted_file_path'] ?? ''));
                if ($error !== null) {
                    $pdo->prepare("UPDATE lesson_assignment_submissions SET engine_error = :e, engine_at = NOW(), engine_score = NULL, engine_result = NULL, engine_confidence = 'low' WHERE id = :id")
                        ->execute(['e' => $error, 'id' => $s['id']]);
                    $done++;
                    continue;
                }
                $pdo->prepare("UPDATE lesson_assignment_submissions SET engine_text = :t WHERE id = :id")
                    ->execute(['t' => mb_substr((string)$text, 0, MarkingEngine::MAX_TEXT), 'id' => $s['id']]);
            }

            // 2. Marking, once every text is in
            $criteria = self::criteria($lesson);
            $rules = self::rules($lesson);
            $all = $pdo->prepare(
                "SELECT s.id, s.engine_text, s.engine_at, s.is_late, s.graded_at, u.lang
                 FROM lesson_assignment_submissions s JOIN users u ON u.id = s.student_id
                 WHERE s.lesson_id = :l AND s.in_marking_at IS NOT NULL AND s.engine_text IS NOT NULL"
            );
            $all->execute(['l' => $lessonId]);
            $rows = $all->fetchAll(PDO::FETCH_ASSOC);
            $idf = [];
            foreach ($rows as $r) {
                $idf[] = array_column(MarkingEngine::tokens((string)$r['engine_text']), 'w');
            }
            $sim = [];
            try {
                $sim = AssignmentSimilarity::results($pdo, $lessonId, (int)$pdo->query("SELECT c.teacher_id FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id JOIN courses c ON c.id = ch.course_id WHERE l.id = " . (int)$lessonId)->fetchColumn())['by_submission'] ?? [];
            } catch (Throwable $e) { /* no similarity data */ }

            $results = [];
            foreach ($rows as $r) {
                if ($r['engine_at'] !== null || $r['graded_at'] !== null) {
                    continue;
                }
                $results[(int)$r['id']] = MarkingEngine::mark((string)$r['engine_text'], $criteria, $rules, [
                    'max' => (float)$lesson['assignment_max_score'], 'late' => (int)$r['is_late'] === 1,
                    'similarity' => isset($sim[(int)$r['id']]) ? (float)$sim[(int)$r['id']]['score'] : null,
                    'lang' => ($r['lang'] ?? 'fr') === 'en' ? 'en' : 'fr', 'idf' => $idf,
                ]);
            }
            if ($results) {
                // The class check compares with every proposal of the bucket, the old ones included
                $old = $pdo->prepare("SELECT id, engine_score FROM lesson_assignment_submissions WHERE lesson_id = :l AND engine_at IS NOT NULL AND engine_score IS NOT NULL AND graded_at IS NULL");
                $old->execute(['l' => $lessonId]);
                $pool = $results;
                foreach ($old->fetchAll(PDO::FETCH_ASSOC) as $o) {
                    $pool['o' . $o['id']] = ['ok' => true, 'score' => (float)$o['engine_score'], 'reasons' => [], 'conf_value' => 1.0, 'confidence' => 'high'];
                }
                $pool = MarkingEngine::classCheck($pool, (float)$lesson['assignment_max_score']);
                $up = $pdo->prepare("UPDATE lesson_assignment_submissions SET engine_score = :sc, engine_confidence = :c, engine_result = :r, engine_error = :e, engine_at = NOW() WHERE id = :id AND graded_at IS NULL");
                foreach ($results as $id => $_) {
                    $r = $pool[$id];
                    $up->execute([
                        'sc' => !empty($r['ok']) ? $r['score'] : null, 'c' => !empty($r['ok']) ? $r['confidence'] : 'low',
                        'r' => json_encode($r, JSON_UNESCAPED_UNICODE), 'e' => !empty($r['ok']) ? null : (string)($r['error'] ?? 'unreadable'), 'id' => $id,
                    ]);
                    $done++;
                }
            }
            return ['done' => $done, 'pending' => self::pending($pdo, $lessonId)];
        } finally {
            $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")");
        }
    }

    /** @return array{0:?string,1:?string} [text, error] error: no_file | unreadable */
    private static function readCopy(PDO $pdo, string $file): array
    {
        if ($file === '') {
            return [null, 'no_file'];
        }
        $path = MediaStore::path('assignment', $file);
        if (!is_file($path)) {
            MediaStore::restore($pdo, 'assignment/' . $file, $path);
        }
        $text = AssignmentSimilarity::extractText($path, pathinfo($file, PATHINFO_EXTENSION));
        if ($text === null || trim($text) === '') {
            return [null, 'unreadable'];
        }
        return [$text, null];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // What the bucket page shows
    // ---------------------------------------------------------------------------------------------------------------

    /** Every submission of the assignment with its pipeline status, low-confidence proposals first. */
    public static function copies(PDO $pdo, array $lesson): array
    {
        $st = $pdo->prepare(
            "SELECT s.id, s.student_id, s.submitted_at, s.is_late, s.attempt_count, s.score, s.graded_at, s.released_at, s.revision_requested_at,
                    s.in_marking_at, s.engine_score, s.engine_confidence, s.engine_error, s.engine_at, s.submitted_file_name, s.submitted_link,
                    u.name, u.matricule
             FROM lesson_assignment_submissions s JOIN users u ON u.id = s.student_id
             WHERE s.lesson_id = :l ORDER BY u.name"
        );
        $st->execute(['l' => $lesson['id']]);
        $rank = ['low' => 0, 'medium' => 1, 'high' => 2];
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $r['pipeline'] = Assignments::pipeline($lesson, $r);
            $out[] = $r;
        }
        $order = ['by_hand' => 0, 'proposed' => 1, 'in_marking' => 2, 'locked' => 3, 'submitted' => 4, 'revision' => 5, 'held' => 6, 'released' => 7];
        usort($out, function ($a, $b) use ($order, $rank) {
            $o = ($order[$a['pipeline']] ?? 9) <=> ($order[$b['pipeline']] ?? 9);
            if ($o !== 0) {
                return $o;
            }
            if ($a['pipeline'] === 'proposed') {
                $c = ($rank[$a['engine_confidence']] ?? 0) <=> ($rank[$b['engine_confidence']] ?? 0);
                if ($c !== 0) {
                    return $c;
                }
            }
            return strcmp((string)$a['name'], (string)$b['name']);
        });
        return $out;
    }

    /** One copy of the bucket, with everything the marking screen shows. */
    public static function copy(PDO $pdo, array $lesson, int $submissionId): ?array
    {
        $st = $pdo->prepare(
            "SELECT s.*, u.name, u.matricule, u.email FROM lesson_assignment_submissions s JOIN users u ON u.id = s.student_id WHERE s.id = :id AND s.lesson_id = :l"
        );
        $st->execute(['id' => $submissionId, 'l' => $lesson['id']]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $r['pipeline'] = Assignments::pipeline($lesson, $r);
        $r['result'] = $r['engine_result'] ? (json_decode((string)$r['engine_result'], true) ?: null) : null;
        $r['rubric_rows'] = AssignmentRubric::decodeScores((string)($r['rubric_scores'] ?? ''));
        return $r;
    }

    /**
     * Counts per status and the reliability of the engine: on copies the teacher has marked, the average gap between the engine's
     * mark and the teacher's, and the share within 1 point (on a mark out of 20; scaled to the assignment's maximum).
     */
    public static function stats(array $copies, float $max): array
    {
        $count = [];
        $gaps = [];
        foreach ($copies as $c) {
            $count[$c['pipeline']] = ($count[$c['pipeline']] ?? 0) + 1;
            if ($c['graded_at'] !== null && $c['engine_score'] !== null && $c['score'] !== null) {
                $gaps[] = abs((float)$c['score'] - (float)$c['engine_score']);
            }
        }
        $point = $max > 0 ? $max / 20 : 1;
        return [
            'count' => $count,
            'checked' => count($gaps),
            'avg_gap' => $gaps ? round(array_sum($gaps) / count($gaps), 2) : null,
            'within' => $gaps ? (int)round(100 * count(array_filter($gaps, fn($g) => $g <= $point + 0.001)) / count($gaps)) : null,
            'point' => round($point, 2),
        ];
    }
}
