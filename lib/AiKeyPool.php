<?php
declare(strict_types=1);

/**
 * The pool of Anthropic keys with the load balancer and the rate limiter in front of it.
 *
 * A lane is a (key, model) pair. The next call goes to the least recently used key of the first model that still has room (round robin over the
 * keys). Every lane has a per-minute budget (ANTHROPIC_RPM, 0 = no limit) so a request is never sent to a lane that would refuse it, and a rest time:
 * the Retry-After of a 429, the reset time announced by the rate-limit headers when a quota is nearly used up, 20 s for a busy API, 30 minutes for an
 * account without credit, an hour for a refused key or a retired model. The state lives in the database (table ai_key_state), shared by the web
 * requests and the background workers. A key is stored only as a short hash.
 */
final class AiKeyPool
{
    public const REST_BUSY = 20;
    public const REST_BILLING = 1800;
    public const REST_RETIRED = 3600;

    /** Tests: the per-minute budget (null = the configuration) */
    public static ?int $rpmOverride = null;
    /** Tests: the keys (null = the configuration) */
    public static ?array $keysOverride = null;
    /** Tests: keep the state of the lanes even when a fake network is in use */
    public static bool $trackWithTransport = false;

    /** @return string[] ANTHROPIC_API_KEYS (comma separated) then ANTHROPIC_API_KEY, without duplicates */
    public static function keys(): array
    {
        if (self::$keysOverride !== null) {
            return self::$keysOverride;
        }
        $list = [];
        foreach ([defined('ANTHROPIC_API_KEY') ? (string)ANTHROPIC_API_KEY : '', defined('ANTHROPIC_API_KEYS') ? (string)ANTHROPIC_API_KEYS : ''] as $raw) {
            foreach (explode(',', $raw) as $k) {
                $k = trim($k);
                if ($k !== '' && preg_match('/^sk-ant-[A-Za-z0-9._-]{16,300}$/', $k)) {
                    $list[$k] = $k;
                }
            }
        }
        return array_values($list);
    }

    public static function hash(string $key): string
    {
        return substr(hash('sha256', $key), 0, 16);
    }

    private static function rpm(): int
    {
        if (self::$rpmOverride !== null) {
            return max(0, self::$rpmOverride);
        }
        return defined('ANTHROPIC_RPM') ? max(0, (int)ANTHROPIC_RPM) : 40;
    }

    /**
     * Takes the next lane. @param string[] $models the models to use, best first
     * @return array{key:string,model:string,id:int}|array{wait:int} (seconds until a lane is free again)
     */
    public static function acquire(PDO $pdo, array $models): array
    {
        $keys = self::keys();
        $rpm = self::rpm();
        foreach ($keys as $k) {
            foreach ($models as $m) {
                $pdo->prepare("INSERT IGNORE INTO ai_key_state (key_hash, model) VALUES (:h, :m)")->execute(['h' => self::hash($k), 'm' => $m]);
            }
        }
        $byHash = []; foreach ($keys as $k) { $byHash[self::hash($k)] = $k; }
        if (!$byHash) {
            return ['wait' => 60];
        }
        $in = implode(',', array_map(fn($h) => $pdo->quote($h), array_keys($byHash)));
        $free = "(cooldown_until IS NULL OR cooldown_until <= NOW()) AND (:rpm1 = 0 OR window_start IS NULL OR window_start < NOW() - INTERVAL 60 SECOND OR window_calls < :rpm2)";
        foreach ($models as $m) {                     // the best model first, every key of it before the next model
            for ($attempt = 0; $attempt < count($keys); $attempt++) {
                $st = $pdo->prepare("SELECT id, key_hash FROM ai_key_state WHERE model = :m AND key_hash IN ($in) AND $free ORDER BY last_used IS NOT NULL, last_used, id LIMIT 1");
                $st->execute(['m' => $m, 'rpm1' => $rpm, 'rpm2' => $rpm]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    break;
                }
                // take the lane atomically: two processes never get the same slot of the minute
                $claim = $pdo->prepare("UPDATE ai_key_state
                    SET window_calls = IF(window_start IS NULL OR window_start < NOW() - INTERVAL 60 SECOND, 1, window_calls + 1),
                        window_start = IF(window_start IS NULL OR window_start < NOW() - INTERVAL 60 SECOND, NOW(), window_start),
                        last_used = NOW(3)
                    WHERE id = :i AND $free");
                $claim->execute(['i' => $row['id'], 'rpm1' => $rpm, 'rpm2' => $rpm]);
                if ($claim->rowCount() === 1) {
                    return ['key' => $byHash[$row['key_hash']], 'model' => $m, 'id' => (int)$row['id']];
                }
            }
        }
        // nothing is free: how long until the first lane is
        $wait = 60;
        $mq = implode(',', array_map(fn($x) => $pdo->quote($x), $models));
        $st = $pdo->query("SELECT GREATEST(IFNULL(TIMESTAMPDIFF(SECOND, NOW(), cooldown_until), 0), 0) AS cd, window_calls, IFNULL(TIMESTAMPDIFF(SECOND, window_start, NOW()), 999) AS age
                           FROM ai_key_state WHERE key_hash IN ($in) AND model IN ($mq)");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $w = max((int)$r['cd'], ($rpm > 0 && (int)$r['window_calls'] >= $rpm && (int)$r['age'] < 60) ? 60 - (int)$r['age'] : 0);
            $wait = min($wait, max(1, $w));
        }
        return ['wait' => $wait];
    }

    /** What happened on a lane. $outcome: ok | ok_rest (answered, but the quota is nearly used up) | rate | busy | retired | auth | billing */
    public static function report(PDO $pdo, int $id, string $outcome, ?int $seconds = null): void
    {
        if ($outcome === 'ok') {
            $pdo->prepare("UPDATE ai_key_state SET ok_count = ok_count + 1, last_error = NULL, cooldown_until = NULL WHERE id = :i")->execute(['i' => $id]);
            return;
        }
        if ($outcome === 'ok_rest') {
            $pdo->prepare("UPDATE ai_key_state SET ok_count = ok_count + 1, last_error = NULL, cooldown_until = NOW() + INTERVAL :s SECOND WHERE id = :i")
                ->execute(['s' => max(1, min(300, $seconds ?? 5)), 'i' => $id]);
            return;
        }
        $rest = match ($outcome) {
            'rate' => max(1, min(900, $seconds ?? 60)),
            'busy' => self::REST_BUSY,
            'billing' => self::REST_BILLING,
            'retired', 'auth' => self::REST_RETIRED,
            default => 0,
        };
        $pdo->prepare("UPDATE ai_key_state SET fail_count = fail_count + 1, last_error = :e, cooldown_until = NOW() + INTERVAL :s SECOND WHERE id = :i")
            ->execute(['e' => $outcome, 's' => $rest, 'i' => $id]);
    }

    /** @return array{keys:int,lanes:int,ready:int,resting:array<int,array{key:string,model:string,error:?string,seconds:int}>} for the teacher's page */
    public static function status(PDO $pdo, array $models): array
    {
        $keys = self::keys();
        $rpm = self::rpm();
        $hashes = []; foreach ($keys as $i => $k) { $hashes[self::hash($k)] = $i + 1; }
        $resting = []; $ready = 0;
        if ($hashes) {
            $st = $pdo->query("SELECT key_hash, model, last_error, GREATEST(IFNULL(TIMESTAMPDIFF(SECOND, NOW(), cooldown_until), 0), 0) AS cd, window_calls,
                               IFNULL(TIMESTAMPDIFF(SECOND, window_start, NOW()), 999) AS age FROM ai_key_state WHERE key_hash IN (" . implode(',', array_map(fn($h) => $pdo->quote($h), array_keys($hashes))) . ")");
            $seen = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (!in_array($r['model'], $models, true)) { continue; }
                $seen[$r['key_hash'] . $r['model']] = true;
                $full = $rpm > 0 && (int)$r['window_calls'] >= $rpm && (int)$r['age'] < 60;
                if ((int)$r['cd'] > 0 || $full) {
                    $resting[] = ['key' => 'key ' . $hashes[$r['key_hash']], 'model' => (string)$r['model'], 'error' => $r['last_error'], 'seconds' => max((int)$r['cd'], $full ? 60 - (int)$r['age'] : 0)];
                } else { $ready++; }
            }
            $ready += max(0, count($keys) * count($models) - count($seen));    // lanes never used yet
        }
        return ['keys' => count($keys), 'lanes' => count($keys) * count($models), 'ready' => $ready, 'resting' => $resting];
    }
}
