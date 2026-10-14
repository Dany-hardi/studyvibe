<?php
declare(strict_types=1);

/**
 * The pool of Gemini keys used to mark code answers, with a load balancer in front of it.
 *
 * Every (key, model) pair has its own quota, so the balancer treats each pair as a lane:
 *  - the next call goes to the least recently used key of the best model that still has room (round robin over the keys);
 *  - a lane that answered "quota full" rests for the time the API gave (or one minute), one that answered "busy" rests for 20 s, a retired model
 *    or a refused key rests for an hour;
 *  - a per-minute budget per lane (GEMINI_RPM_PER_MODEL, 5 on the free plan, 0 = no limit) keeps us from wasting calls on a lane that is full.
 * The state lives in the database (table ai_key_state), shared by the web requests and the background workers. A key is only ever stored as
 * a short hash. Quotas are counted per Google project: the keys must come from different projects to add up.
 */
final class AiKeyPool
{
    public const REST_BUSY = 20;
    public const REST_RETIRED = 3600;

    /** Tests: the per-minute budget (null = the configuration) */
    public static ?int $rpmOverride = null;
    /** Tests: the keys (null = the configuration) */
    public static ?array $keysOverride = null;

    /** @return string[] the usable keys: GEMINI_API_KEYS (comma separated) then GEMINI_API_KEY, without duplicates */
    public static function keys(): array
    {
        if (self::$keysOverride !== null) {
            return self::$keysOverride;
        }
        $list = [];
        foreach ([defined('GEMINI_API_KEYS') ? (string)GEMINI_API_KEYS : '', defined('GEMINI_API_KEY') ? (string)GEMINI_API_KEY : ''] as $raw) {
            foreach (explode(',', $raw) as $k) {
                $k = trim($k);
                if ($k !== '' && $k !== 'votre_cle_api_gemini_ici' && preg_match('/^[A-Za-z0-9._-]{20,200}$/', $k)) {
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
        return defined('GEMINI_RPM_PER_MODEL') ? max(0, (int)GEMINI_RPM_PER_MODEL) : 5;
    }

    /**
     * Takes the next lane. @return array{key:string,model:string,id:int}|array{wait:int} (seconds until a lane is free again)
     */
    public static function acquire(PDO $pdo): array
    {
        $keys = self::keys();
        $models = AnthropicClient::geminiModels();
        $rpm = self::rpm();
        foreach ($keys as $k) {
            foreach ($models as $m) {
                $pdo->prepare("INSERT IGNORE INTO ai_key_state (key_hash, model) VALUES (:h, :m)")->execute(['h' => self::hash($k), 'm' => $m]);
            }
        }
        $byHash = []; foreach ($keys as $k) { $byHash[self::hash($k)] = $k; }
        $free = "(cooldown_until IS NULL OR cooldown_until <= NOW()) AND (:rpm1 = 0 OR window_start IS NULL OR window_start < NOW() - INTERVAL 60 SECOND OR window_calls < :rpm2)";
        foreach ($models as $m) {                     // the best model first, every key of it before the next model
            for ($attempt = 0; $attempt < max(1, count($keys)); $attempt++) {
                $st = $pdo->prepare("SELECT id, key_hash FROM ai_key_state WHERE model = :m AND key_hash IN (" . implode(',', array_map(fn($h) => $pdo->quote($h), array_keys($byHash))) . ") AND $free ORDER BY last_used IS NOT NULL, last_used, id LIMIT 1");
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
        $st = $pdo->query("SELECT GREATEST(IFNULL(TIMESTAMPDIFF(SECOND, NOW(), cooldown_until), 0), 0) AS cd, window_calls, IFNULL(TIMESTAMPDIFF(SECOND, window_start, NOW()), 999) AS age FROM ai_key_state WHERE key_hash IN ("
            . implode(',', array_map(fn($h) => $pdo->quote($h), array_keys($byHash))) . ")");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $w = max((int)$r['cd'], ($rpm > 0 && (int)$r['window_calls'] >= $rpm && (int)$r['age'] < 60) ? 60 - (int)$r['age'] : 0);
            $wait = min($wait, max(1, $w));
        }
        return ['wait' => $wait];
    }

    /** What happened on a lane. $outcome: ok | rate | busy | retired | auth */
    public static function report(PDO $pdo, int $id, string $outcome, ?int $seconds = null): void
    {
        $rest = match ($outcome) {
            'rate' => max(5, min(21600, $seconds ?? 60)),
            'busy' => self::REST_BUSY,
            'retired', 'auth' => self::REST_RETIRED,
            default => 0,
        };
        if ($outcome === 'ok') {
            $pdo->prepare("UPDATE ai_key_state SET ok_count = ok_count + 1, last_error = NULL, cooldown_until = NULL WHERE id = :i")->execute(['i' => $id]);
            return;
        }
        $pdo->prepare("UPDATE ai_key_state SET fail_count = fail_count + 1, last_error = :e, cooldown_until = NOW() + INTERVAL :s SECOND WHERE id = :i")
            ->execute(['e' => $outcome, 's' => $rest, 'i' => $id]);
    }

    /** @return array{keys:int,lanes:int,ready:int,resting:array<int,array{key:string,model:string,error:?string,seconds:int}>} for the teacher's page */
    public static function status(PDO $pdo): array
    {
        $keys = self::keys();
        $models = AnthropicClient::geminiModels();
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
