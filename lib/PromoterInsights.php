<?php
declare(strict_types=1);

/**
 * Numbers behind the promoter dashboard: trends, funnel, acquisition channels, engagement, learning, live evaluations,
 * platform health, and a short list of recommendations derived from them.
 *
 * Everything here is read-only and works from data the platform already keeps (accounts, enrolments, progress, certificates,
 * audit log) plus the anonymous counters in `site_events`.
 */
final class PromoterInsights
{
    /** Cached JSON for a few seconds: several panels ask for the same numbers at the same moment. */
    public static function cached(string $key, int $ttl, callable $compute): array
    {
        $dir  = __DIR__ . '/../uploads/insights_cache';
        $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';
        if (is_file($file) && (time() - (int)filemtime($file)) < $ttl) {
            $data = json_decode((string)@file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }
        $data = $compute();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode($data)) !== false) {
            @rename($tmp, $file);
        }
        return $data;
    }

    private static function one(PDO $pdo, string $sql, array $p = []): mixed
    {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchColumn();
    }

    private static function rows(PDO $pdo, string $sql, array $p = []): array
    {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function delta(float $now, float $before): ?float
    {
        if ($before <= 0) {
            return $now > 0 ? null : 0.0;      // no base to compare with
        }
        return round(($now - $before) / $before * 100, 1);
    }

    /** @return string[] the last $days dates, oldest first, as Y-m-d */
    public static function days(int $days): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $out[] = date('Y-m-d', strtotime("-{$i} day"));
        }
        return $out;
    }

    /** Fills a day => value map into a list aligned with $days. */
    private static function fill(array $days, array $rows, string $dayKey = 'd', string $valKey = 'n'): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[$r[$dayKey]] = (float)$r[$valKey];
        }
        return array_map(fn($d) => $map[$d] ?? 0, $days);
    }

    // =========================================================================
    // OVERVIEW: headline numbers with change versus the previous period, plus daily series
    // =========================================================================

    public static function overview(PDO $pdo, int $days): array
    {
        $days   = max(7, min(180, $days));
        $from   = date('Y-m-d', strtotime('-' . ($days - 1) . ' day'));
        $prevF  = date('Y-m-d', strtotime('-' . (2 * $days - 1) . ' day'));
        $prevT  = date('Y-m-d', strtotime('-' . $days . ' day'));
        $period = fn(string $col, string $table, string $extra = '') => [
            (int)self::one($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$col} >= :f {$extra}", ['f' => $from]),
            (int)self::one($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$col} >= :a AND {$col} < :b {$extra}", ['a' => $prevF, 'b' => $from]),
        ];

        [$newUsers, $newUsersPrev]     = $period('created_at', 'users', "AND role IN ('student','teacher')");
        [$enrol, $enrolPrev]           = $period('enrolled_at', 'enrollments');
        [$lessons, $lessonsPrev]       = $period('completed_at', 'lesson_progress', 'AND completed = 1');
        [$certs, $certsPrev]           = $period('issued_at', 'certificates');
        [$liveReg, $liveRegPrev]       = $period('registered_at', 'live_eval_registrations');

        $active     = (int)self::one($pdo, "SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE action='login_success' AND created_at >= :f", ['f' => $from]);
        $activePrev = (int)self::one($pdo, "SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE action='login_success' AND created_at >= :a AND created_at < :b", ['a' => $prevF, 'b' => $from]);

        $verifiedNow  = (int)self::one($pdo, "SELECT COUNT(*) FROM users WHERE role IN ('student','teacher') AND created_at >= :f AND email_verified_at IS NOT NULL", ['f' => $from]);
        $verifyRate   = $newUsers > 0 ? round($verifiedNow / $newUsers * 100, 1) : null;

        $totals = [
            'students'    => (int)self::one($pdo, "SELECT COUNT(*) FROM users WHERE role='student'"),
            'teachers'    => (int)self::one($pdo, "SELECT COUNT(*) FROM users WHERE role='teacher'"),
            'courses'     => (int)self::one($pdo, "SELECT COUNT(*) FROM courses"),
            'enrollments' => (int)self::one($pdo, "SELECT COUNT(*) FROM enrollments"),
            'certificates'=> (int)self::one($pdo, "SELECT COUNT(*) FROM certificates"),
            'live_sessions' => (int)self::one($pdo, "SELECT COUNT(*) FROM live_eval_sessions"),
            'avg_progress'=> (int)round((float)self::one($pdo, "SELECT COALESCE(AVG(progress_percent),0) FROM enrollments")),
        ];

        $d = self::days($days);
        $series = [
            'days'       => $d,
            'signups'    => self::fill($d, self::rows($pdo, "SELECT DATE(created_at) d, COUNT(*) n FROM users WHERE role IN ('student','teacher') AND created_at >= :f GROUP BY d", ['f' => $from])),
            'logins'     => self::fill($d, self::rows($pdo, "SELECT DATE(created_at) d, COUNT(DISTINCT user_id) n FROM audit_logs WHERE action='login_success' AND created_at >= :f GROUP BY d", ['f' => $from])),
            'enrollments'=> self::fill($d, self::rows($pdo, "SELECT DATE(enrolled_at) d, COUNT(*) n FROM enrollments WHERE enrolled_at >= :f GROUP BY d", ['f' => $from])),
            'lessons'    => self::fill($d, self::rows($pdo, "SELECT DATE(completed_at) d, COUNT(*) n FROM lesson_progress WHERE completed = 1 AND completed_at >= :f GROUP BY d", ['f' => $from])),
            'visitors'   => self::fill($d, self::rows($pdo, "SELECT day d, SUM(n) n FROM site_events WHERE event='visitor' AND day >= :f GROUP BY day", ['f' => $from])),
        ];

        return [
            'days' => $days,
            'kpis' => [
                ['key' => 'new_users',   'value' => $newUsers, 'prev' => $newUsersPrev, 'delta' => self::delta($newUsers, $newUsersPrev), 'spark' => $series['signups']],
                ['key' => 'active',      'value' => $active,   'prev' => $activePrev,   'delta' => self::delta($active, $activePrev),     'spark' => $series['logins']],
                ['key' => 'enrollments', 'value' => $enrol,    'prev' => $enrolPrev,    'delta' => self::delta($enrol, $enrolPrev),       'spark' => $series['enrollments']],
                ['key' => 'lessons',     'value' => $lessons,  'prev' => $lessonsPrev,  'delta' => self::delta($lessons, $lessonsPrev),   'spark' => $series['lessons']],
                ['key' => 'certificates','value' => $certs,    'prev' => $certsPrev,    'delta' => self::delta($certs, $certsPrev),       'spark' => null],
                ['key' => 'live_part',   'value' => $liveReg,  'prev' => $liveRegPrev,  'delta' => self::delta($liveReg, $liveRegPrev),   'spark' => null],
                ['key' => 'verify_rate', 'value' => $verifyRate, 'prev' => null, 'delta' => null, 'spark' => null, 'unit' => '%'],
            ],
            'totals' => $totals,
            'series' => $series,
        ];
    }

    // =========================================================================
    // FUNNEL: from a visit to a certificate
    // =========================================================================

    public static function funnel(PDO $pdo, int $days): array
    {
        $days = max(7, min(180, $days));
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' day'));
        $ev = fn(string $e) => (int)self::one($pdo, "SELECT COALESCE(SUM(n),0) FROM site_events WHERE event = :e AND day >= :f", ['e' => $e, 'f' => $from]);

        $cohort = "role = 'student' AND created_at >= :f";
        $q = fn(string $extra = '') => (int)self::one($pdo, "SELECT COUNT(*) FROM users u WHERE u.{$cohort} {$extra}", ['f' => $from]);

        $steps = [
            ['key' => 'visitors',     'n' => $ev('visitor'),       'tracked' => true],
            ['key' => 'signup_open',  'n' => $ev('signup_open'),   'tracked' => true],
            ['key' => 'signup_step2', 'n' => $ev('signup_step2'),  'tracked' => true],
            ['key' => 'account',      'n' => $q(),                 'tracked' => false],
            ['key' => 'verified',     'n' => $q("AND u.email_verified_at IS NOT NULL"), 'tracked' => false],
            ['key' => 'enrolled',     'n' => $q("AND EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id = u.id)"), 'tracked' => false],
            ['key' => 'started',      'n' => $q("AND EXISTS (SELECT 1 FROM lesson_progress lp WHERE lp.student_id = u.id)"), 'tracked' => false],
            ['key' => 'lesson_done',  'n' => $q("AND EXISTS (SELECT 1 FROM lesson_progress lp WHERE lp.student_id = u.id AND lp.completed = 1)"), 'tracked' => false],
            ['key' => 'course_done',  'n' => $q("AND EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id = u.id AND e.progress_percent >= 100)"), 'tracked' => false],
            ['key' => 'certificate',  'n' => $q("AND EXISTS (SELECT 1 FROM certificates c WHERE c.student_id = u.id)"), 'tracked' => false],
        ];

        // Counters only exist since the day tracking was switched on; before that the front of the funnel is unknown
        $since = self::one($pdo, "SELECT MIN(day) FROM site_events");
        $prev = null;
        foreach ($steps as &$s) {
            $s['of_prev'] = ($prev !== null && $prev > 0) ? round($s['n'] / $prev * 100, 1) : null;
            $prev = $s['n'];
        }
        unset($s);

        return ['days' => $days, 'steps' => $steps, 'tracking_since' => $since ?: null];
    }

    // =========================================================================
    // ACQUISITION: where sign-ups come from
    // =========================================================================

    public static function acquisition(PDO $pdo, int $days): array
    {
        $days = max(7, min(180, $days));
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' day'));

        $users = self::rows($pdo, "
            SELECT COALESCE(NULLIF(u.signup_source,''), '') AS src, COUNT(*) AS signups,
                   SUM(u.email_verified_at IS NOT NULL) AS verified,
                   SUM(EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id = u.id)) AS enrolled
            FROM users u
            WHERE u.role IN ('student','teacher') AND u.created_at >= :f
            GROUP BY src
        ", ['f' => $from]);
        $visits = [];
        foreach (self::rows($pdo, "SELECT src, SUM(n) n FROM site_events WHERE event='view:landing' AND day >= :f GROUP BY src", ['f' => $from]) as $r) {
            $visits[$r['src']] = (int)$r['n'];
        }
        $opens = [];
        foreach (self::rows($pdo, "SELECT src, SUM(n) n FROM site_events WHERE event='signup_open' AND day >= :f GROUP BY src", ['f' => $from]) as $r) {
            $opens[$r['src']] = (int)$r['n'];
        }

        $by = [];
        foreach ($users as $r) {
            $by[$r['src']] = ['src' => $r['src'], 'signups' => (int)$r['signups'], 'verified' => (int)$r['verified'], 'enrolled' => (int)$r['enrolled']];
        }
        foreach ($visits + $opens as $src => $_) {
            $by[$src] ??= ['src' => $src, 'signups' => 0, 'verified' => 0, 'enrolled' => 0];
        }

        $names = [];
        foreach (self::rows($pdo, "SELECT slug, name FROM campaigns") as $c) {
            $names[$c['slug']] = $c['name'];
        }
        foreach ($by as &$r) {
            $r['visits']   = $visits[$r['src']] ?? 0;
            $r['opens']    = $opens[$r['src']] ?? 0;
            $r['label']    = $r['src'] === '' ? null : ($names[$r['src']] ?? $r['src']);
            $r['is_campaign'] = $r['src'] !== '' && isset($names[$r['src']]);
            $r['conv']     = $r['visits'] > 0 ? round($r['signups'] / $r['visits'] * 100, 1) : null;
        }
        unset($r);
        usort($by, fn($a, $b) => [$b['signups'], $b['visits']] <=> [$a['signups'], $a['visits']]);

        // Pages people actually open (anonymous counters)
        $pages = self::rows($pdo, "SELECT event, SUM(n) n FROM site_events WHERE event LIKE 'view:%' AND day >= :f GROUP BY event ORDER BY n DESC", ['f' => $from]);

        $campaigns = self::rows($pdo, "SELECT id, name, slug, target, target_ref, created_at, archived FROM campaigns ORDER BY archived ASC, id DESC");

        return ['days' => $days, 'channels' => $by, 'pages' => $pages, 'campaigns' => $campaigns];
    }

    // =========================================================================
    // ENGAGEMENT: who comes back, and when
    // =========================================================================

    public static function engagement(PDO $pdo): array
    {
        $distinct = fn(int $d) => (int)self::one($pdo, "SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE action='login_success' AND created_at >= :f", ['f' => date('Y-m-d H:i:s', strtotime("-{$d} day"))]);
        $dau = $distinct(1); $wau = $distinct(7); $mau = $distinct(30);

        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        foreach (self::rows($pdo, "SELECT WEEKDAY(created_at) wd, HOUR(created_at) h, COUNT(*) n FROM audit_logs WHERE action='login_success' AND created_at >= :f GROUP BY wd, h", ['f' => date('Y-m-d', strtotime('-90 day'))]) as $r) {
            $grid[(int)$r['wd']][(int)$r['h']] = (int)$r['n'];
        }

        $returning = (int)self::one($pdo, "
            SELECT COUNT(*) FROM (
                SELECT user_id FROM audit_logs WHERE action='login_success' AND user_id IS NOT NULL AND created_at >= :f
                GROUP BY user_id HAVING COUNT(DISTINCT DATE(created_at)) >= 2
            ) x", ['f' => date('Y-m-d', strtotime('-14 day'))]);

        $study = (int)self::one($pdo, "SELECT COALESCE(SUM(seconds_spent),0) FROM study_sessions WHERE session_date >= :f", ['f' => date('Y-m-d', strtotime('-29 day'))]);
        $quiz  = self::one($pdo, "SELECT AVG(answered_correctly) * 100 FROM lesson_question_answers");

        return [
            'dau' => $dau, 'wau' => $wau, 'mau' => $mau,
            'stickiness' => $mau > 0 ? round($dau / $mau * 100, 1) : null,
            'returning_14d' => $returning,
            'heatmap' => $grid,
            'study_hours_30d' => round($study / 3600, 1),
            'quiz_accuracy' => $quiz !== null && $quiz !== false ? round((float)$quiz, 1) : null,
        ];
    }

    // =========================================================================
    // LEARNING: which courses and lessons work
    // =========================================================================

    public static function learning(PDO $pdo): array
    {
        $courses = self::rows($pdo, "
            SELECT c.id, c.title, COALESCE(t.name,'') AS teacher,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrolled,
                   (SELECT COALESCE(AVG(e.progress_percent),0) FROM enrollments e WHERE e.course_id = c.id) AS avg_progress,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.progress_percent >= 100) AS finished,
                   (SELECT COUNT(*) FROM certificates ce WHERE ce.course_id = c.id) AS certs,
                   (SELECT COUNT(*) FROM chapters ch JOIN lessons l ON l.chapter_id = ch.id WHERE ch.course_id = c.id) AS lessons
            FROM courses c LEFT JOIN users t ON t.id = c.teacher_id
            ORDER BY enrolled DESC, c.id DESC LIMIT 12
        ");
        foreach ($courses as &$c) {
            $c['enrolled'] = (int)$c['enrolled']; $c['finished'] = (int)$c['finished']; $c['certs'] = (int)$c['certs']; $c['lessons'] = (int)$c['lessons'];
            $c['avg_progress'] = (int)round((float)$c['avg_progress']);
        }
        unset($c);

        // Lessons where learners stall: opened by many, finished by few
        $stall = self::rows($pdo, "
            SELECT l.id, l.title, c.title AS course,
                   COUNT(lp.id) AS opened, COALESCE(SUM(lp.completed),0) AS done
            FROM lessons l
            JOIN chapters ch ON ch.id = l.chapter_id
            JOIN courses c ON c.id = ch.course_id
            JOIN lesson_progress lp ON lp.lesson_id = l.id
            GROUP BY l.id, l.title, c.title
            HAVING COUNT(lp.id) >= 2 AND COALESCE(SUM(lp.completed),0) < COUNT(lp.id)
            ORDER BY (COUNT(lp.id) - COALESCE(SUM(lp.completed),0)) DESC, COUNT(lp.id) DESC LIMIT 6
        ");
        foreach ($stall as &$s) {
            $s['opened'] = (int)$s['opened']; $s['done'] = (int)$s['done'];
            $s['rate'] = $s['opened'] > 0 ? (int)round($s['done'] / $s['opened'] * 100) : 0;
        }
        unset($s);

        return ['courses' => $courses, 'stalling' => $stall];
    }

    // =========================================================================
    // LIVE EVALUATIONS
    // =========================================================================

    public static function live(PDO $pdo): array
    {
        $totals = self::rows($pdo, "
            SELECT COUNT(DISTINCT s.id) sessions, COUNT(r.id) participants,
                   SUM(r.score IS NOT NULL) scored, COALESCE(AVG(r.score),0) avg_score
            FROM live_eval_sessions s LEFT JOIN live_eval_registrations r ON r.session_id = s.id
        ")[0];
        $upcoming = self::rows($pdo, "
            SELECT s.id, s.title, s.start_time, c.title AS course, u.name AS teacher,
                   (SELECT COUNT(*) FROM live_eval_registrations r WHERE r.session_id = s.id) AS registered
            FROM live_eval_sessions s JOIN courses c ON c.id = s.course_id JOIN users u ON u.id = s.teacher_id
            WHERE s.end_time >= NOW() ORDER BY s.start_time ASC LIMIT 5
        ");
        $biggest = (int)self::one($pdo, "SELECT COALESCE(MAX(n),0) FROM (SELECT COUNT(*) n FROM live_eval_registrations GROUP BY session_id) x");
        foreach ($upcoming as &$u) { $u['registered'] = (int)$u['registered']; }
        unset($u);
        return [
            'sessions' => (int)$totals['sessions'], 'participants' => (int)$totals['participants'],
            'completion' => (int)$totals['participants'] > 0 ? round((int)$totals['scored'] / (int)$totals['participants'] * 100, 1) : null,
            'avg_score' => round((float)$totals['avg_score'], 1), 'biggest_room' => $biggest, 'upcoming' => $upcoming,
        ];
    }

    // =========================================================================
    // HEALTH + RECOMMENDATIONS
    // =========================================================================

    public static function health(PDO $pdo): array
    {
        $unverifiedOld = (int)self::one($pdo, "SELECT COUNT(*) FROM users WHERE role IN ('student','teacher') AND email_verified_at IS NULL AND created_at < (NOW() - INTERVAL 3 DAY)");
        $pending = self::rows($pdo, "SELECT COUNT(*) n, COALESCE(MIN(created_at), NOW()) oldest FROM users WHERE role='teacher' AND is_approved = 0")[0];
        $awaiting = (int)self::one($pdo, "
            SELECT COUNT(*) FROM (
                SELECT a.student_id, a.course_id FROM certification_attempts a
                WHERE a.passed = 1 AND NOT EXISTS (SELECT 1 FROM certificates ce WHERE ce.student_id = a.student_id AND ce.course_id = a.course_id)
                GROUP BY a.student_id, a.course_id) x");
        $failed24 = (int)self::one($pdo, "SELECT COUNT(*) FROM audit_logs WHERE action='login_failed' AND created_at >= (NOW() - INTERVAL 1 DAY)");
        $ok24     = (int)self::one($pdo, "SELECT COUNT(*) FROM audit_logs WHERE action='login_success' AND created_at >= (NOW() - INTERVAL 1 DAY)");
        $mail = ['pending' => 0, 'failed' => 0];
        try {
            foreach (self::rows($pdo, "SELECT status, COUNT(*) n FROM live_eval_mail_queue GROUP BY status") as $r) {
                if (isset($mail[$r['status']])) { $mail[$r['status']] = (int)$r['n']; }
            }
        } catch (Throwable) {}
        return [
            'unverified_old' => $unverifiedOld,
            'pending_teachers' => (int)$pending['n'],
            'oldest_pending_days' => (int)$pending['n'] > 0 ? (int)floor((time() - strtotime((string)$pending['oldest'])) / 86400) : 0,
            'awaiting_certs' => $awaiting,
            'login_failed_24h' => $failed24, 'login_ok_24h' => $ok24,
            'mail_queue' => $mail,
            'smtp' => class_exists('Mailer') ? Mailer::isConfigured() : null,
        ];
    }

    /** @return array<int, array{level:string,key:string,params:array,go:?string}> most important first */
    public static function recommendations(array $overview, array $funnel, array $health, array $engagement, array $acq): array
    {
        $r = [];
        $add = function (string $level, string $key, array $params = [], ?string $go = null) use (&$r) {
            $r[] = ['level' => $level, 'key' => $key, 'params' => $params, 'go' => $go];
        };
        $step = [];
        foreach ($funnel['steps'] as $s) { $step[$s['key']] = $s; }

        if ($health['pending_teachers'] > 0) {
            $add($health['oldest_pending_days'] >= 2 ? 'high' : 'mid', 'rec_teachers', ['n' => $health['pending_teachers'], 'd' => $health['oldest_pending_days']], 'tab-people');
        }
        if ($health['awaiting_certs'] > 0) {
            $add('mid', 'rec_certs', ['n' => $health['awaiting_certs']], 'tab-certificates');
        }
        if (($step['account']['n'] ?? 0) >= 5) {
            $lost = $step['account']['n'] - ($step['verified']['n'] ?? 0);
            if ($lost / max(1, $step['account']['n']) > 0.25) {
                $add('high', 'rec_unverified', ['n' => $lost, 'total' => $step['account']['n'], 'pct' => round($lost / $step['account']['n'] * 100)], 'tab-people');
            }
        }
        if (($step['verified']['n'] ?? 0) >= 5) {
            $idle = $step['verified']['n'] - ($step['enrolled']['n'] ?? 0);
            if ($idle / max(1, $step['verified']['n']) > 0.3) {
                $add('mid', 'rec_not_enrolled', ['n' => $idle, 'pct' => round($idle / $step['verified']['n'] * 100)], 'tab-growth');
            }
        }
        if (($step['enrolled']['n'] ?? 0) >= 5) {
            $idle = $step['enrolled']['n'] - ($step['started']['n'] ?? 0);
            if ($idle / max(1, $step['enrolled']['n']) > 0.3) {
                $add('mid', 'rec_not_started', ['n' => $idle, 'pct' => round($idle / $step['enrolled']['n'] * 100)], 'tab-communications');
            }
        }
        if (($step['signup_open']['n'] ?? 0) >= 20 && ($step['signup_step2']['n'] ?? 0) / max(1, $step['signup_open']['n']) < 0.4) {
            $add('mid', 'rec_signup_dropoff', ['pct' => round((1 - $step['signup_step2']['n'] / $step['signup_open']['n']) * 100)], 'tab-growth');
        }
        if ($health['login_failed_24h'] >= 10 && $health['login_failed_24h'] > $health['login_ok_24h']) {
            $add('high', 'rec_login_failures', ['n' => $health['login_failed_24h']], 'tab-audit');
        }
        if ($health['smtp'] === false) {
            $add('high', 'rec_smtp', [], null);
        }
        if (($health['mail_queue']['failed'] ?? 0) > 0) {
            $add('mid', 'rec_mail_failed', ['n' => $health['mail_queue']['failed']], null);
        }
        if (($engagement['mau'] ?? 0) >= 5 && ($engagement['wau'] ?? 0) / max(1, $engagement['mau']) < 0.3) {
            $add('low', 'rec_low_weekly', ['wau' => $engagement['wau'], 'mau' => $engagement['mau']], 'tab-communications');
        }
        $campaigns = array_filter($acq['channels'], fn($c) => $c['is_campaign']);
        if (!$campaigns && ($overview['totals']['students'] ?? 0) > 0) {
            $add('low', 'rec_first_qr', [], 'tab-growth');
        }

        $rank = ['high' => 0, 'mid' => 1, 'low' => 2];
        usort($r, fn($a, $b) => $rank[$a['level']] <=> $rank[$b['level']]);
        return array_slice($r, 0, 6);
    }
}
