<?php
declare(strict_types=1);

/**
 * Traceability and observability data for the administrator: what happened on the platform in a period, by whom, from where, and how
 * the delivery channels (e-mail, push, SMS) are doing. One call, Observability::collect(), returns everything an export needs, so the PDF,
 * the Excel workbook, the CSV and the JSON all tell the same story. Nothing here writes to the database.
 *
 * The audit trail is kept 365 days, the login attempts 90 days (see purgeOldSecurityLogs() in auth.php).
 */
final class Observability
{
    /** Row cap per export kind: PDFs stay readable, data exports stay complete. */
    public const PDF_ROWS = 1200;
    public const DATA_ROWS = 50000;

    /** Category => SQL conditions on audit_logs.action (LIKE patterns or exact names). */
    private const CATEGORIES = [
        'security'     => ['login_failed', 'login_password_ok_2fa_pending', '2fa_%', 'password_reset_%', 'user_deleted', 'user_created_by_admin', 'role_%', 'verify_altered', 'verify_notfound', 'verify_revoked', 'ops_%', 'account_%'],
        'access'       => ['login_success', 'login_success_2fa', 'login_%', 'logout', 'signup', 'email_verified'],
        'academic'     => ['assignment_%', 'live_%', 'questions_%', 'comment_%', 'student_request%', 'module_%', 'course_%', 'certificate_%', 'transcript_%'],
        'exports'      => ['export_%'],
        'verification' => ['verify_%', 'transcript_issued', 'certificate_%'],
    ];

    public static function categories(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /** Actions that count as security-relevant in the summary (independent of the category filter). */
    private const SECURITY_ACTIONS = ['login_failed', 'login_password_ok_2fa_pending', '2fa_disabled', '2fa_enabled', 'password_reset_requested', 'user_deleted', 'user_created_by_admin', 'verify_altered', 'verify_notfound', 'verify_revoked'];

    /**
     * @return array{from:string,to:string,days:int}
     */
    public static function period(?string $from, ?string $to, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $f = $from && ($t = strtotime($from)) ? date('Y-m-d', $t) : null;
        $u = $to && ($t = strtotime($to)) ? date('Y-m-d', $t) : null;
        if ($f === null || $u === null) {
            $u = date('Y-m-d');
            $f = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
        }
        if ($f > $u) {
            [$f, $u] = [$u, $f];
        }
        $span = (int)floor((strtotime($u) - strtotime($f)) / 86400) + 1;
        if ($span > 366) {
            $f = date('Y-m-d', strtotime($u . ' -365 days'));
            $span = 366;
        }
        return ['from' => $f, 'to' => $u, 'days' => $span];
    }

    /** SQL fragment and parameters for the category / text filters. */
    private static function filter(?string $category, ?string $q, string $alias = 'al'): array
    {
        $sql = '';
        $p = [];
        if ($category !== null && isset(self::CATEGORIES[$category])) {
            $parts = [];
            foreach (self::CATEGORIES[$category] as $i => $pat) {
                $k = "cat{$i}";
                $parts[] = str_contains($pat, '%') ? "{$alias}.action LIKE :{$k}" : "{$alias}.action = :{$k}";
                $p[$k] = $pat;
            }
            $sql .= ' AND (' . implode(' OR ', $parts) . ')';
        }
        $q = trim((string)$q);
        if ($q !== '') {
            $sql .= " AND ({$alias}.action LIKE :q1 OR {$alias}.details LIKE :q2 OR u.name LIKE :q3 OR u.email LIKE :q4 OR {$alias}.ip_address LIKE :q5)";
            $like = '%' . addcslashes(mb_substr($q, 0, 80), '%_\\') . '%';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $k) {
                $p[$k] = $like;
            }
        }
        return [$sql, $p];
    }

    /**
     * Everything for one period.
     *
     * @return array<string,mixed>
     */
    public static function collect(PDO $pdo, string $from, string $to, ?string $category = null, ?string $q = null, int $rowLimit = self::PDF_ROWS): array
    {
        $start = $from . ' 00:00:00';
        $end = $to . ' 23:59:59';
        [$fsql, $fp] = self::filter($category, $q);
        $base = "FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id WHERE al.created_at BETWEEN :s AND :e" . $fsql;
        $bind = ['s' => $start, 'e' => $end] + $fp;
        $run = function (string $sql, array $extra = []) use ($pdo, $bind): PDOStatement {
            $st = $pdo->prepare($sql);
            $st->execute($bind + $extra);
            return $st;
        };

        $total = (int)$run("SELECT COUNT(*) {$base}")->fetchColumn();
        $actors = (int)$run("SELECT COUNT(DISTINCT al.user_id) {$base}")->fetchColumn();
        $ips = (int)$run("SELECT COUNT(DISTINCT al.ip_address) {$base}")->fetchColumn();

        $counts = [];
        foreach ($run("SELECT al.action, COUNT(*) c {$base} GROUP BY al.action ORDER BY c DESC")->fetchAll() as $r) {
            $counts[(string)$r['action']] = (int)$r['c'];
        }
        $sum = fn(array $actions) => array_sum(array_map(fn($a) => $counts[$a] ?? 0, $actions));
        $loginsOk = $sum(['login_success', 'login_success_2fa']);
        $loginsKo = $sum(['login_failed']);
        $exports = 0;
        foreach ($counts as $a => $c) {
            $exports += str_starts_with($a, 'export_') ? $c : 0;
        }
        $verifs = $sum(['verify_ok', 'verify_notfound', 'verify_altered', 'verify_revoked']);

        $summary = [
            'events' => $total, 'actors' => $actors, 'ips' => $ips,
            'logins_ok' => $loginsOk, 'logins_failed' => $loginsKo,
            'failure_rate' => ($loginsOk + $loginsKo) > 0 ? round($loginsKo / ($loginsOk + $loginsKo) * 100, 1) : 0.0,
            'security' => $sum(self::SECURITY_ACTIONS), 'exports' => $exports,
            'deletions' => $sum(['user_deleted']), 'verifications' => $verifs, 'verify_suspect' => $sum(['verify_altered', 'verify_notfound']),
        ];

        // Events per day, with the failed logins alongside (days without events are kept at zero)
        $perDay = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
            $perDay[date('Y-m-d', $d)] = ['day' => date('Y-m-d', $d), 'events' => 0, 'failed' => 0];
        }
        foreach ($run("SELECT DATE(al.created_at) d, COUNT(*) c, SUM(al.action = 'login_failed') f {$base} GROUP BY DATE(al.created_at)")->fetchAll() as $r) {
            if (isset($perDay[$r['d']])) {
                $perDay[$r['d']]['events'] = (int)$r['c'];
                $perDay[$r['d']]['failed'] = (int)$r['f'];
            }
        }

        $topActors = $run("SELECT COALESCE(u.name, '—') name, COALESCE(u.role, '—') role, COUNT(*) c, MAX(al.created_at) last_seen {$base} GROUP BY al.user_id, u.name, u.role ORDER BY c DESC LIMIT 10")->fetchAll();
        $failedIps = $run("SELECT al.ip_address ip, COUNT(*) c, MAX(al.created_at) last_seen {$base} AND al.action = 'login_failed' GROUP BY al.ip_address ORDER BY c DESC LIMIT 10")->fetchAll();

        $security = $run("SELECT al.id, al.created_at, COALESCE(u.name, '—') name, al.action, al.details, al.ip_address {$base} AND al.action IN ('"
            . implode("','", self::SECURITY_ACTIONS) . "') ORDER BY al.id DESC LIMIT 150")->fetchAll();

        $rows = $run("SELECT al.id, al.created_at, al.user_id, COALESCE(u.name, '') name, COALESCE(u.role, '') role, al.action, COALESCE(al.details, '') details, COALESCE(al.ip_address, '') ip {$base} ORDER BY al.id DESC LIMIT " . max(1, $rowLimit))->fetchAll();

        return [
            'period' => ['from' => $from, 'to' => $to, 'days' => count($perDay)],
            'filters' => ['category' => $category, 'q' => trim((string)$q)],
            'summary' => $summary,
            'timeline' => array_values($perDay),
            'top_actions' => array_slice($counts, 0, 15, true),
            'top_actors' => $topActors,
            'failed_ips' => $failedIps,
            'security_events' => $security,
            'delivery' => self::delivery($pdo, $start, $end),
            'usage' => self::usage($pdo, $from, $to),
            'rows' => $rows,
            'rows_truncated' => $total > count($rows),
            'rows_total' => $total,
            'retention' => ['audit_days' => 365, 'login_attempts_days' => 90],
        ];
    }

    /** Outbound channels: how many messages were queued, sent, failed in the period. */
    private static function delivery(PDO $pdo, string $start, string $end): array
    {
        $out = [];
        $channels = [
            'email' => ['email_outbox', 'created_at'], 'sms' => ['sms_outbox', 'created_at'],
            'push' => ['push_outbox', 'created_at'], 'live_mail' => ['live_eval_mail_queue', 'created_at'],
        ];
        foreach ($channels as $name => [$table, $col]) {
            try {
                $st = $pdo->prepare("SELECT status, COUNT(*) c FROM {$table} WHERE {$col} BETWEEN :s AND :e GROUP BY status");
                $st->execute(['s' => $start, 'e' => $end]);
                $by = [];
                foreach ($st->fetchAll() as $r) {
                    $by[(string)$r['status']] = (int)$r['c'];
                }
                $total = array_sum($by);
                $sent = $by['sent'] ?? 0;
                $failed = $by['failed'] ?? 0;
                $out[$name] = ['total' => $total, 'sent' => $sent, 'failed' => $failed, 'pending' => max(0, $total - $sent - $failed),
                    'success_rate' => ($sent + $failed) > 0 ? round($sent / ($sent + $failed) * 100, 1) : null];
            } catch (PDOException) {
                $out[$name] = ['total' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0, 'success_rate' => null];
            }
        }
        return $out;
    }

    /** Page views and other counters of the anonymous analytics (site_events), top ten of the period. */
    private static function usage(PDO $pdo, string $from, string $to): array
    {
        try {
            $st = $pdo->prepare("SELECT event, SUM(n) c FROM site_events WHERE day BETWEEN :f AND :t GROUP BY event ORDER BY c DESC LIMIT 10");
            $st->execute(['f' => $from, 't' => $to]);
            return array_map(fn($r) => ['event' => (string)$r['event'], 'count' => (int)$r['c']], $st->fetchAll());
        } catch (PDOException) {
            return [];
        }
    }

    /**
     * A reference and a fingerprint for the report: the same data, the same fingerprint. The reference says when and by whom it was made.
     *
     * @return array{id:string,fingerprint:string,generated_at:string}
     */
    public static function stamp(array $data, string $by): array
    {
        $core = ['period' => $data['period'], 'filters' => $data['filters'], 'summary' => $data['summary'], 'ids' => array_map(fn($r) => (int)$r['id'], $data['rows'])];
        $fp = hash_hmac('sha256', json_encode($core), (string)APP_SECRET);
        return ['id' => 'OBS-' . date('Ymd-His') . '-' . strtoupper(substr($fp, 0, 6)), 'fingerprint' => $fp, 'generated_at' => date('Y-m-d H:i:s'), 'generated_by' => $by];
    }

    /** Human label for an action name: login_failed → "login failed". */
    public static function label(string $action): string
    {
        return str_replace('_', ' ', $action);
    }
}
