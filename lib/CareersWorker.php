<?php
declare(strict_types=1);

require_once __DIR__ . '/Careers.php';
require_once __DIR__ . '/CareersScreening.php';
require_once __DIR__ . '/CareersInterviews.php';
require_once __DIR__ . '/InterviewMail.php';

/**
 * The careers engine that works around the clock. One call to run() does every job that is due, each safe to repeat:
 *
 *   screen      applications not yet screened, or screened before their posting's criteria changed (screen_version), are read and sorted
 *   expire      postings past their closing date are closed
 *   interviews  finished ones are marked completed; reminders go out the day before and two hours before; one chase for an invitation
 *               still unanswered after 48 hours; each reminder is sent once (reminder24_at / reminder2_at / chase_at) and retried at most 3 times
 *   decline     applications held in "screened out" are politely declined when the posting asks for it (auto_decline_days) and nobody reviewed them
 *   retention   applications of people who are out of the process are erased after RETENTION_MONTHS (24 with a talent-pool consent)
 *   alerts      a posting closing within 3 days is announced to the administrators once
 *   digest      one e-mail a day (from 07:30) to the administrators: what waits for a decision
 *
 * Where it runs
 *   - cron, every minute: lib/careers-worker.php (also called by lib/email-worker.php, which the Docker image already schedules);
 *   - tick(): on ordinary page loads (careers pages, the tracking page, the workspace), at most once a minute, after the answer has been sent,
 *     so a quiet site is still served when no cron is configured.
 * A lock keeps two runs from overlapping.
 */
final class CareersWorker
{
    private const TICK_SECONDS = 60;
    private const MAX_TRIES = 3;

    public static function tick(PDO $pdo): void
    {
        try {
            $flag = dirname(__DIR__) . '/uploads/live_cache/careers_tick.flag';
            if (!is_dir(dirname($flag))) {
                @mkdir(dirname($flag), 0755, true);
            }
            if ((time() - (int)@filemtime($flag)) < self::TICK_SECONDS) {
                return;
            }
            @touch($flag);
            register_shutdown_function(static function () use ($pdo): void {
                try {
                    if (function_exists('fastcgi_finish_request')) {
                        fastcgi_finish_request();
                    }
                    self::run($pdo, 6);
                } catch (Throwable $e) {
                    error_log('[careers] tick: ' . $e->getMessage());
                }
            });
        } catch (Throwable $e) {
            error_log('[careers] tick: ' . $e->getMessage());
        }
    }

    /**
     * @param int $screenBatch how many applications to read in this run (a PDF takes a moment; the CLI worker does more)
     * @return array<string,int> what was done
     */
    public static function run(PDO $pdo, int $screenBatch = 25): array
    {
        $lock = @fopen(sys_get_temp_dir() . '/studyvibe_careers_worker.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['skipped' => 1];
        }
        $done = [];
        try {
            foreach ([
                'screened'  => fn() => self::screenPending($pdo, $screenBatch),
                'expired'   => fn() => self::expireJobs($pdo),
                'completed' => fn() => self::completeInterviews($pdo),
                'reminded'  => fn() => self::remind($pdo),
                'declined'  => fn() => self::autoDecline($pdo),
                'purged'    => fn() => self::purge($pdo),
                'alerts'    => fn() => self::closingAlerts($pdo),
                'digest'    => fn() => self::digest($pdo),
            ] as $name => $task) {
                try {
                    $done[$name] = (int)$task();
                } catch (Throwable $e) {
                    error_log('[careers] ' . $name . ': ' . $e->getMessage());
                    $done[$name] = -1;
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $done;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Bookkeeping
    // -----------------------------------------------------------------------------------------------------------------

    private static function state(PDO $pdo, string $name): string
    {
        $st = $pdo->prepare('SELECT value FROM career_state WHERE name = :n');
        $st->execute(['n' => $name]);
        return (string)($st->fetchColumn() ?: '');
    }

    private static function setState(PDO $pdo, string $name, string $value): void
    {
        $pdo->prepare('REPLACE INTO career_state (name, value) VALUES (:n, :v)')->execute(['n' => $name, 'v' => $value]);
    }

    /** True the first MAX_TRIES times it is asked for this key: a message that cannot be sent is not retried for ever. */
    private static function mayTry(PDO $pdo, string $key): bool
    {
        $n = (int)self::state($pdo, 'try:' . $key);
        if ($n >= self::MAX_TRIES) {
            return false;
        }
        self::setState($pdo, 'try:' . $key, (string)($n + 1));
        return true;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Tasks
    // -----------------------------------------------------------------------------------------------------------------

    private static function screenPending(PDO $pdo, int $limit): int
    {
        $stamp = CareersScreening::VERSION;
        $st = $pdo->prepare("
            SELECT a.id FROM career_applications a LEFT JOIN career_jobs j ON j.id = a.job_id
            WHERE a.bucket <> 'archived' AND (
                a.screen_state = 'pending'
                OR (a.screen_state = 'done' AND a.screen_version <> IFNULL(j.screen_version, 1) * 100 + $stamp)
                OR (a.screen_state = 'error' AND a.updated_at < NOW() - INTERVAL 1 HOUR)
            ) ORDER BY a.id ASC LIMIT " . max(1, min(100, $limit)));
        $st->execute();
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
            if (CareersScreening::screenApplication($pdo, (int)$id) !== null) {
                $n++;
            }
        }
        return $n;
    }

    private static function expireJobs(PDO $pdo): int
    {
        $st = $pdo->prepare("UPDATE career_jobs SET status = 'closed', closed_at = NOW() WHERE status = 'open' AND closes_at IS NOT NULL AND closes_at < :d");
        $st->execute(['d' => date('Y-m-d')]);
        return $st->rowCount();
    }

    private static function completeInterviews(PDO $pdo): int
    {
        $st = $pdo->prepare("SELECT id, application_id, scheduled_at FROM career_interviews
            WHERE status IN ('scheduled','confirmed','reschedule_requested','declined') AND DATE_ADD(scheduled_at, INTERVAL duration_min MINUTE) < :now");
        $st->execute(['now' => date('Y-m-d H:i:s')]);
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pdo->prepare("UPDATE career_interviews SET status = 'completed' WHERE id = :i")->execute(['i' => $row['id']]);
            Careers::event($pdo, (int)$row['application_id'], null, 'interview', null, 'completed', json_encode(['when' => $row['scheduled_at'], 'id' => (int)$row['id']]), true);
            $n++;
        }
        return $n;
    }

    private static function remind(PDO $pdo): int
    {
        $now = date('Y-m-d H:i:s');
        $sent = 0;
        // [column to stamp, SQL condition, mailer]
        $kinds = [
            ['reminder24_at', "i.status IN ('scheduled','confirmed') AND i.reminder24_at IS NULL AND i.scheduled_at <= :lim AND i.scheduled_at > :floor AND TIMESTAMPDIFF(MINUTE, i.invited_at, i.scheduled_at) >= 1500", 'reminder24', date('Y-m-d H:i:s', time() + 86400), date('Y-m-d H:i:s', time() + 3 * 3600)],
            ['reminder2_at', "i.status IN ('scheduled','confirmed') AND i.reminder2_at IS NULL AND i.scheduled_at <= :lim AND i.scheduled_at > :floor AND TIMESTAMPDIFF(MINUTE, i.invited_at, i.scheduled_at) >= 180", 'reminder2', date('Y-m-d H:i:s', time() + 2 * 3600), date('Y-m-d H:i:s', time() + 600)],
            ['chase_at', "i.status = 'scheduled' AND i.chase_at IS NULL AND i.invited_at <= :lim AND i.scheduled_at > :floor", 'chase', date('Y-m-d H:i:s', time() - 48 * 3600), date('Y-m-d H:i:s', time() + 12 * 3600)],
        ];
        foreach ($kinds as [$col, $cond, $mailer, $lim, $floor]) {
            $st = $pdo->prepare("SELECT i.* FROM career_interviews i JOIN career_applications a ON a.id = i.application_id
                WHERE $cond AND a.status NOT IN ('rejected','hired','withdrawn') ORDER BY i.scheduled_at ASC LIMIT 50");
            $st->execute(['lim' => $lim, 'floor' => $floor]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $iv) {
                $app = Careers::application($pdo, (int)$iv['application_id']);
                if (!$app) {
                    continue;
                }
                $key = $mailer . ':' . $iv['id'] . ':' . substr((string)$iv['scheduled_at'], 0, 16);
                if (!self::mayTry($pdo, $key)) {
                    $pdo->prepare("UPDATE career_interviews SET $col = :n WHERE id = :i")->execute(['n' => $now, 'i' => $iv['id']]);
                    continue;
                }
                $ok = InterviewMail::$mailer($app, $iv);
                if ($ok) {
                    $pdo->prepare("UPDATE career_interviews SET $col = :n WHERE id = :i")->execute(['n' => $now, 'i' => $iv['id']]);
                    Careers::event($pdo, (int)$iv['application_id'], null, 'email', null, 'interview', 'Reminder: ' . $mailer);
                    $sent++;
                }
            }
        }
        return $sent;
    }

    private static function autoDecline(PDO $pdo): int
    {
        $st = $pdo->query("SELECT a.id FROM career_applications a JOIN career_jobs j ON j.id = a.job_id
            WHERE a.bucket = 'screened_out' AND a.status = 'new' AND j.auto_decline_days > 0 AND a.bucket_changed_at <= NOW() - INTERVAL j.auto_decline_days DAY LIMIT 20");
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
            if (Careers::autoDecline($pdo, (int)$id)) {
                $n++;
            }
        }
        return $n;
    }

    /** Erases applications of people who are out of the process once their retention time is over. */
    private static function purge(PDO $pdo): int
    {
        $a = Careers::RETENTION_MONTHS;
        $b = Careers::RETENTION_POOL_MONTHS;
        $st = $pdo->query("SELECT id, reference FROM career_applications
            WHERE status <> 'hired' AND (status IN ('rejected','withdrawn') OR bucket IN ('screened_out','archived'))
              AND COALESCE(status_changed_at, bucket_changed_at, created_at) < NOW() - INTERVAL IF(talent_consent = 1, $b, $a) MONTH LIMIT 50");
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (Careers::deleteApplication($pdo, (int)$row['id'])['ok']) {
                $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (NULL, 'career_retention_purge', :d, 'worker')")
                    ->execute(['d' => 'Application ' . $row['reference'] . ' erased after its retention time']);
                $n++;
            }
        }
        return $n;
    }

    private static function closingAlerts(PDO $pdo): int
    {
        $st = $pdo->prepare("SELECT j.id, j.title, j.closes_at, (SELECT COUNT(*) FROM career_applications a WHERE a.job_id = j.id) AS apps
            FROM career_jobs j WHERE j.status = 'open' AND j.closes_at BETWEEN :a AND :b");
        $st->execute(['a' => date('Y-m-d'), 'b' => date('Y-m-d', strtotime('+3 day'))]);
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $j) {
            $key = 'closing:' . $j['id'] . ':' . $j['closes_at'];
            if (self::state($pdo, $key) !== '' || !self::mayTry($pdo, $key . ':try')) {
                continue;
            }
            require_once __DIR__ . '/EmailTheme.php';
            $days = max(0, (int)ceil((strtotime((string)$j['closes_at'] . ' 23:59:59') - time()) / 86400));
            $html = EmailTheme::layout(
                EmailTheme::badge('Recrutement') . EmailTheme::title('Une offre se termine bientôt')
                . EmailTheme::lede('« <strong>' . EmailTheme::h((string)$j['title']) . '</strong> » se termine le ' . date('d/m/Y', strtotime((string)$j['closes_at'])) . ($days <= 1 ? ' (demain ou aujourd’hui)' : ' (dans ' . $days . ' jours)') . '.')
                . EmailTheme::facts([['Candidatures reçues', (string)(int)$j['apps']]])
                . EmailTheme::p('Prolongez la date limite si vous cherchez encore des profils, ou préparez la lecture des candidatures.')
                . EmailTheme::button(self::appUrl() . '/promoter/careers/#jobs', 'Ouvrir les offres'),
                ['preheader' => 'Offre bientôt terminée : ' . $j['title'], 'lang' => 'fr']
            );
            foreach (CareersMail::adminEmails($pdo) as $to) {
                Mailer::send((string)$to, 'Offre bientôt terminée : ' . $j['title'], $html);
            }
            self::setState($pdo, $key, date('c'));
            $n++;
        }
        return $n;
    }

    private static function appUrl(): string
    {
        return defined('APP_URL') && (string)APP_URL !== '' ? rtrim((string)APP_URL, '/') : '';
    }

    /** One message a day, from 07:30, listing what waits for a decision. Nothing waiting, nothing sent. */
    private static function digest(PDO $pdo): int
    {
        if ((int)date('Hi') < 730 || self::state($pdo, 'digest_date') === date('Y-m-d')) {
            return 0;
        }
        $waiting = (int)$pdo->query("SELECT COUNT(*) FROM career_applications WHERE bucket = 'inbox' AND status = 'new' AND created_at < NOW() - INTERVAL 48 HOUR")->fetchColumn();
        $held = (int)$pdo->query("SELECT COUNT(*) FROM career_applications WHERE bucket = 'screened_out'")->fetchColumn();
        $heldOld = (int)$pdo->query("SELECT COUNT(*) FROM career_applications WHERE bucket = 'screened_out' AND bucket_changed_at < NOW() - INTERVAL 3 DAY")->fetchColumn();
        $strong = (int)$pdo->query("SELECT COUNT(*) FROM career_applications WHERE bucket = 'inbox' AND status = 'new' AND screen_verdict = 'strong'")->fetchColumn();
        $iv = $pdo->prepare("SELECT i.scheduled_at, a.full_name, a.job_title, i.status FROM career_interviews i JOIN career_applications a ON a.id = i.application_id
            WHERE i.status IN ('scheduled','confirmed','reschedule_requested','declined') AND i.scheduled_at BETWEEN :a AND :b ORDER BY i.scheduled_at");
        $iv->execute(['a' => date('Y-m-d H:i:s'), 'b' => date('Y-m-d 23:59:59', strtotime('+1 day'))]);
        $interviews = $iv->fetchAll(PDO::FETCH_ASSOC);
        $needs = (int)$pdo->query("SELECT COUNT(*) FROM career_interviews WHERE status IN ('declined','reschedule_requested') AND scheduled_at > NOW()")->fetchColumn();
        $errors = (int)$pdo->query("SELECT COUNT(*) FROM career_applications WHERE screen_state = 'error'")->fetchColumn();
        if (!$waiting && !$heldOld && !$strong && !$interviews && !$needs && !$errors) {
            self::setState($pdo, 'digest_date', date('Y-m-d'));
            return 0;
        }

        require_once __DIR__ . '/EmailTheme.php';
        $rows = [];
        if ($strong) { $rows[] = ['Profils solides à lire', (string)$strong]; }
        if ($waiting) { $rows[] = ['Candidatures sans réponse depuis 48 h', (string)$waiting]; }
        if ($held) { $rows[] = ['Dans la section « Écartées »', $held . ($heldOld ? ' (dont ' . $heldOld . ' depuis plus de 3 jours)' : '')]; }
        if ($needs) { $rows[] = ['Entretiens à replanifier (refus ou demande)', (string)$needs]; }
        if ($errors) { $rows[] = ['Lectures automatiques en erreur', (string)$errors]; }
        $list = '';
        foreach ($interviews as $r) {
            $list .= '<tr><td style="padding:6px 0;font-size:14px;">' . EmailTheme::h(date('d/m H:i', strtotime((string)$r['scheduled_at']))) . ' · <strong>' . EmailTheme::h((string)$r['full_name']) . '</strong> — ' . EmailTheme::h((string)($r['job_title'] ?: 'Spontanée')) . '</td></tr>';
        }
        $html = EmailTheme::layout(
            EmailTheme::badge('Recrutement') . EmailTheme::title('Le point du jour')
            . EmailTheme::lede('Voici ce qui attend une décision dans votre espace recrutement.')
            . EmailTheme::facts($rows ?: [['Rien d’urgent', '—']])
            . ($list ? EmailTheme::sectionTitle('Entretiens aujourd’hui et demain') . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $list . '</table>' : '')
            . EmailTheme::button(self::appUrl() . '/promoter/careers/', 'Ouvrir l’espace recrutement'),
            ['preheader' => 'Candidatures et entretiens en attente', 'lang' => 'fr']
        );
        $n = 0;
        foreach (CareersMail::adminEmails($pdo) as $to) {
            if (Mailer::send((string)$to, 'Recrutement : le point du jour', $html)) {
                $n++;
            }
        }
        self::setState($pdo, 'digest_date', date('Y-m-d'));
        return $n;
    }
}
