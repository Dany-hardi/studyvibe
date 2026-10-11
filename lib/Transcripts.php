<?php
declare(strict_types=1);

/**
 * Issued transcripts. Every time a student downloads a transcript, a row records its code, a summary of what it said and a
 * fingerprint (HMAC with APP_SECRET over the code, the student, the date and the summary). The QR code on the PDF points to
 * /verify.php?code=TR-…, which looks the row up, recomputes the fingerprint (so a row edited in the database no longer checks
 * out) and shows the summary. A transcript can be revoked, and then verify.php says so.
 */
final class Transcripts
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';   // no 0/O, 1/I/L

    /** A fresh code like TR-K7Q2-9XMA-4HPD. */
    public static function newCode(): string
    {
        $c = '';
        for ($i = 0; $i < 12; $i++) {
            $c .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        return 'TR-' . implode('-', str_split($c, 4));
    }

    public static function looksLikeCode(string $code): bool
    {
        return preg_match('/^TR-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code) === 1;
    }

    /** What the transcript says, in a few numbers (what verify.php shows, and what the fingerprint covers). */
    public static function summarize(array $courses, array $lessons, array $failed): array
    {
        $best = [];
        foreach ($courses as $c) {
            if (($c['best_score'] ?? null) !== null) {
                $best[] = (float)$c['best_score'];
            }
        }
        $lessonScores = array_map(fn($l) => (float)$l['score'], $lessons);
        return [
            'courses'      => count($courses),
            'lessons'      => count($lessons),
            'failed'       => count($failed),
            'avg_best'     => $best ? round(array_sum($best) / count($best), 1) : null,
            'avg_lessons'  => $lessonScores ? round(array_sum($lessonScores) / count($lessonScores), 1) : null,
            'progress_avg' => $courses ? round(array_sum(array_map(fn($c) => (float)$c['progress_percent'], $courses)) / count($courses), 1) : null,
        ];
    }

    public static function fingerprint(string $code, int $studentId, string $issuedAt, string $summaryJson): string
    {
        return hash_hmac('sha256', implode('|', [$code, $studentId, $issuedAt, $summaryJson]), (string)APP_SECRET);
    }

    /** Records an issue and returns its row (code, issued_at, fingerprint, summary). */
    public static function issue(PDO $pdo, int $studentId, array $summary, string $ip = ''): array
    {
        $json = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $issuedAt = date('Y-m-d H:i:s');
        for ($try = 0; $try < 5; $try++) {
            $code = self::newCode();
            $fp = self::fingerprint($code, $studentId, $issuedAt, (string)$json);
            try {
                $pdo->prepare("INSERT INTO transcript_issues (code, student_id, summary, fingerprint, issued_at, issued_ip) VALUES (:c, :s, :j, :f, :t, :ip)")
                    ->execute(['c' => $code, 's' => $studentId, 'j' => $json, 'f' => $fp, 't' => $issuedAt, 'ip' => substr($ip, 0, 45) ?: null]);
                return ['code' => $code, 'student_id' => $studentId, 'issued_at' => $issuedAt, 'fingerprint' => $fp, 'summary' => $summary];
            } catch (PDOException $e) {
                if ($try === 4) {
                    throw $e;   // not a code clash: let the caller decide
                }
            }
        }
        throw new RuntimeException('unreachable');
    }

    /**
     * The row for a code, or null. Adds 'valid' (the fingerprint matches), 'revoked' and the student's name and matricule.
     *
     * @return array<string,mixed>|null
     */
    public static function find(PDO $pdo, string $code): ?array
    {
        if (!self::looksLikeCode($code)) {
            return null;
        }
        $st = $pdo->prepare("SELECT t.*, u.name AS student_name, u.matricule FROM transcript_issues t JOIN users u ON u.id = t.student_id WHERE t.code = :c");
        $st->execute(['c' => $code]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        $expected = self::fingerprint((string)$row['code'], (int)$row['student_id'], (string)$row['issued_at'], (string)$row['summary']);
        $row['valid'] = hash_equals($expected, (string)$row['fingerprint']);
        $row['revoked'] = !empty($row['revoked_at']);
        $row['summary_data'] = json_decode((string)$row['summary'], true) ?: [];
        return $row;
    }
}
