<?php
declare(strict_types=1);

/**
 * What the control center knows about the running installation: hardware, web server, PHP, database, the application and
 * which version of the code paths it runs. Everything is read from the real thing; where a value cannot be read it is
 * labelled "assumed" so the simulation never pretends to know more than it does.
 */
final class ControlState
{
    private const REF = [                          // measured on the reference machine used to calibrate the simulator
        'cpu_loop_ms' => 29.0, 'bcrypt_ms' => 63.0, 'db_connect_ms' => 0.55, 'db_select_ms' => 0.075, 'db_write_ms' => 0.25,
    ];

    private static function src(mixed $value, string $how, ?string $detail = null): array
    {
        return ['value' => $value, 'how' => $how, 'detail' => $detail];   // how: read | measured | assumed
    }

    private static function file(string $path): ?string
    {
        return is_readable($path) ? (string)@file_get_contents($path) : null;
    }

    public static function hardware(): array
    {
        $cores = null;
        $cpu = self::file('/proc/cpuinfo');
        if ($cpu !== null) {
            $cores = substr_count($cpu, "\nprocessor");
            if (str_starts_with($cpu, 'processor')) { $cores++; }
        }
        $mem = self::file('/proc/meminfo');
        $total = $avail = null;
        if ($mem && preg_match('/MemTotal:\s+(\d+)/', $mem, $a)) { $total = (int)round($a[1] / 1024); }
        if ($mem && preg_match('/MemAvailable:\s+(\d+)/', $mem, $a)) { $avail = (int)round($a[1] / 1024); }
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $up = self::file('/proc/uptime');
        return [
            'cores' => $cores ? self::src($cores, 'read', '/proc/cpuinfo') : self::src(4, 'assumed'),
            'ram_mb' => $total ? self::src($total, 'read', '/proc/meminfo') : self::src(4096, 'assumed'),
            'ram_free_mb' => $avail,
            'load' => $load ? array_map(fn($x) => round($x, 2), $load) : null,
            'disk_free_gb' => round((float)@disk_free_space(dirname(__DIR__)) / 1073741824, 1),
            'uptime_h' => $up ? round((float)explode(' ', $up)[0] / 3600, 1) : null,
            'os' => PHP_OS_FAMILY . ' ' . php_uname('r'),
        ];
    }

    /** Apache settings: the last value found while reading the usual configuration files wins, like Apache itself. */
    public static function web(): array
    {
        $sapi = PHP_SAPI;
        // Only look at Apache's files when PHP really runs inside or behind Apache. The built-in development server and the
        // command line have nothing to do with whatever Apache happens to be installed on the same machine.
        $files = in_array($sapi, ['cli-server', 'cli'], true) ? [] : [
            '/etc/apache2/apache2.conf', '/etc/apache2/mods-enabled/mpm_prefork.conf', '/etc/apache2/mods-enabled/mpm_event.conf',
            '/etc/apache2/conf-enabled/*.conf',
            '/opt/lampp/etc/httpd.conf', '/opt/lampp/etc/extra/httpd-mpm.conf', '/opt/lampp/etc/extra/httpd-default.conf',
            '/usr/local/apache2/conf/httpd.conf', '/etc/httpd/conf/httpd.conf', '/etc/httpd/conf.d/*.conf',
        ];
        $found = [];
        $mpm = null;
        foreach ($files as $glob) {
            foreach (glob($glob) ?: [] as $f) {
                $txt = self::file($f);
                if ($txt === null) { continue; }
                $inPrefork = false; $inEvent = false;
                foreach (preg_split('/\R/', $txt) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#') { continue; }
                    if (preg_match('/<IfModule\s+mpm_prefork_module/i', $line)) { $inPrefork = true; continue; }
                    if (preg_match('/<IfModule\s+mpm_(event|worker)_module/i', $line)) { $inEvent = true; continue; }
                    if (preg_match('/<\/IfModule>/i', $line)) { $inPrefork = $inEvent = false; continue; }
                    if (preg_match('/^(KeepAlive|KeepAliveTimeout|MaxKeepAliveRequests|ListenBacklog|Timeout)\s+(\S+)/i', $line, $m)) {
                        $found[strtolower($m[1])] = [$m[2], $f];
                    }
                    if ($inPrefork && preg_match('/^(MaxRequestWorkers|MaxClients|StartServers|MinSpareServers|MaxSpareServers)\s+(\d+)/i', $line, $m)) {
                        $key = strtolower($m[1] === 'MaxClients' ? 'MaxRequestWorkers' : $m[1]);
                        $found[$key] = [(int)$m[2], $f]; $mpm = 'prefork';
                    }
                    if ($inEvent && preg_match('/^(MaxRequestWorkers|MaxClients)\s+(\d+)/i', $line, $m)) { $found['maxrequestworkers'] = [(int)$m[2], $f]; $mpm = 'event'; }
                }
            }
        }
        $pick = function (string $k, mixed $default, string $assumedNote) use ($found) {
            return isset($found[$k]) ? self::src($found[$k][1] === null ? $default : $found[$k][0], 'read', $found[$k][1]) : self::src($default, 'assumed', $assumedNote);
        };
        $kaOn = isset($found['keepalive']) ? strtolower((string)$found['keepalive'][0]) === 'on' : true;
        return [
            'sapi' => $sapi,
            'software' => (string)($_SERVER['SERVER_SOFTWARE'] ?? $sapi),
            'mpm' => $mpm ?: ($sapi === 'apache2handler' ? 'prefork' : $sapi),
            'workers' => $pick('maxrequestworkers', 150, 'Apache default'),
            'start_servers' => $pick('startservers', 5, 'Apache default'),
            'keepalive' => isset($found['keepalive']) ? self::src($kaOn, 'read', $found['keepalive'][1]) : self::src(true, 'assumed', 'Apache default'),
            'keepalive_timeout' => $pick('keepalivetimeout', 5, 'Apache default'),
            'listen_backlog' => $pick('listenbacklog', 511, 'Apache default'),
            'config_visible' => (bool)$found,
        ];
    }

    public static function php(): array
    {
        $opc = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return [
            'version' => PHP_VERSION, 'sapi' => PHP_SAPI,
            'opcache' => self::src((bool)($opc['opcache_enabled'] ?? false), 'read', $opc ? 'opcache_get_status' : 'extension not loaded'),
            'opcache_hit_rate' => isset($opc['opcache_statistics']['opcache_hit_rate']) ? round((float)$opc['opcache_statistics']['opcache_hit_rate'], 1) : null,
            'memory_limit' => (string)ini_get('memory_limit'),
            'session_handler' => (string)ini_get('session.save_handler'),
            'exec_available' => function_exists('exec') && !in_array('exec', $disabled, true),
            'extensions' => ['pdo_mysql' => extension_loaded('pdo_mysql'), 'curl' => extension_loaded('curl'), 'mbstring' => extension_loaded('mbstring'), 'zip' => extension_loaded('zip')],
            'mem_per_worker_mb' => self::src(!empty($opc['opcache_enabled']) ? 18 : 24, 'assumed', 'typical mod_php worker, measured at 16 MB with opcache'),
        ];
    }

    public static function db(PDO $pdo): array
    {
        $var = function (string $n) use ($pdo) { try { $r = $pdo->query("SHOW VARIABLES LIKE '" . $n . "'")->fetch(PDO::FETCH_NUM); return $r ? $r[1] : null; } catch (Throwable) { return null; } };
        $stat = function (string $n) use ($pdo) { try { $r = $pdo->query("SHOW GLOBAL STATUS LIKE '" . $n . "'")->fetch(PDO::FETCH_NUM); return $r ? (int)$r[1] : null; } catch (Throwable) { return null; } };
        $host = strtolower((string)(defined('DB_HOST') ? DB_HOST : ''));
        $local = in_array($host, ['127.0.0.1', 'localhost', '::1', ''], true);
        $up = $stat('Uptime');
        return [
            'version' => $var('version'),
            'host' => $host, 'same_host' => self::src($local, 'read', 'DB_HOST'),
            'max_connections' => self::src((int)$var('max_connections'), 'read', 'SHOW VARIABLES'),
            'buffer_pool_mb' => (int)round((int)$var('innodb_buffer_pool_size') / 1048576),
            'flush_log' => self::src((int)$var('innodb_flush_log_at_trx_commit'), 'read', 'SHOW VARIABLES'),
            'threads_connected' => $stat('Threads_connected'), 'max_used' => $stat('Max_used_connections'),
            'uptime_h' => $up ? round($up / 3600, 1) : null,
            'qps' => $up ? round(($stat('Questions') ?? 0) / max(1, $up), 1) : null,
            'persistent' => self::src(defined('DB_PERSISTENT') && in_array(strtolower((string)DB_PERSISTENT), ['1', 'true', 'on'], true), 'read', '.env DB_PERSISTENT'),
        ];
    }

    /** Which generation of the request paths this installation runs. */
    public static function code(): array
    {
        $root = dirname(__DIR__);
        $has = fn(string $file, string $needle) => str_contains((string)@file_get_contents($root . '/' . $file), $needle);
        $migrateOnce  = $has('Database.php', 'function migrateOnce');
        $sharedCache  = $has('api/live-eval-poll.php', 'function liveCacheRemember');
        $mailQueue    = $has('api/live-eval-poll.php', 'LiveMailQueue::enqueue') && is_file($root . '/lib/LiveMailQueue.php');
        $signupInline = $has('signup-action.php', 'Mailer::emailVerification');
        $optimized = $migrateOnce && $sharedCache && $mailQueue;
        return [
            'profile' => self::src($optimized ? 'optimized' : 'legacy', 'read', 'source files inspected'),
            'migrate_once' => $migrateOnce, 'shared_cache' => $sharedCache, 'mail_queue' => $mailQueue,
            'signup_mail_inline' => $signupInline,
        ];
    }

    public static function app(PDO $pdo): array
    {
        $one = function (string $sql, array $p = []) use ($pdo) { try { $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchColumn(); } catch (Throwable) { return null; } };
        $dirSize = function (string $d): array { $n = 0; $b = 0; foreach (glob($d . '/*') ?: [] as $f) { if (is_file($f)) { $n++; $b += (int)filesize($f); } } return [$n, round($b / 1024)]; };
        $root = dirname(__DIR__);
        [$cacheN, $cacheKb] = $dirSize($root . '/uploads/live_cache');
        $mail = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];
        try { foreach ($pdo->query("SELECT status, COUNT(*) n FROM live_eval_mail_queue GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r) { $mail[$r['status']] = (int)$r['n']; } } catch (Throwable) {}
        $maint = 0; $mf = $root . '/uploads/maintenance.flag';
        if (is_file($mf)) { $maint = max(0, (int)@file_get_contents($mf) - time()); }
        return [
            'users' => ['students' => (int)$one("SELECT COUNT(*) FROM users WHERE role='student'"), 'teachers' => (int)$one("SELECT COUNT(*) FROM users WHERE role='teacher'"), 'promoters' => (int)$one("SELECT COUNT(*) FROM users WHERE role='promoter'")],
            'courses' => (int)$one("SELECT COUNT(*) FROM courses"), 'lessons' => (int)$one("SELECT COUNT(*) FROM lessons"), 'enrollments' => (int)$one("SELECT COUNT(*) FROM enrollments"),
            'live' => ['sessions' => (int)$one("SELECT COUNT(*) FROM live_eval_sessions"), 'upcoming' => (int)$one("SELECT COUNT(*) FROM live_eval_sessions WHERE end_time >= NOW()"), 'biggest_room' => (int)$one("SELECT COALESCE(MAX(n),0) FROM (SELECT COUNT(*) n FROM live_eval_registrations GROUP BY session_id) x"), 'answers' => (int)$one("SELECT COUNT(*) FROM live_eval_answers")],
            'mail_queue' => $mail,
            'login_failed_24h' => (int)$one("SELECT COUNT(*) FROM audit_logs WHERE action='login_failed' AND created_at >= (NOW() - INTERVAL 1 DAY)"),
            'cache_files' => $cacheN, 'cache_kb' => $cacheKb,
            'sessions_files' => count(glob((string)ini_get('session.save_path') . '/sess_*') ?: []) ?: null,
            'db_size_mb' => (float)$one("SELECT ROUND(SUM(data_length + index_length) / 1048576, 1) FROM information_schema.tables WHERE table_schema = DATABASE()"),
            'smtp' => class_exists('Mailer') ? Mailer::isConfigured() : null,
            'smtp_host' => defined('SMTP_HOST') ? (string)SMTP_HOST : '',
            'maintenance_s' => $maint,
            'app_url' => defined('APP_URL') ? (string)APP_URL : '',
            'https_only' => defined('HTTPS_ONLY') ? (string)HTTPS_ONLY : '',
        ];
    }

    /** Everything, plus the configuration the simulator starts from. */
    public static function collect(PDO $pdo): array
    {
        $hw = self::hardware(); $web = self::web(); $php = self::php(); $db = self::db($pdo); $code = self::code(); $app = self::app($pdo);
        $gmail = str_contains(strtolower((string)$app['smtp_host']), 'gmail');
        $sim = [
            'infra' => [
                'cores' => $hw['cores']['value'], 'ramMB' => $hw['ram_mb']['value'], 'workers' => $web['workers']['value'],
                'keepAlive' => $web['keepalive']['value'], 'keepAliveTimeoutS' => (int)$web['keepalive_timeout']['value'], 'listenBacklog' => (int)$web['listen_backlog']['value'],
                'dbMaxConnections' => $db['max_connections']['value'], 'dbPersistent' => $db['persistent']['value'], 'dbOnSameHost' => $db['same_host']['value'],
                'flushLog' => (int)$db['flush_log']['value'], 'opcache' => $php['opcache']['value'], 'memPerWorkerMB' => $php['mem_per_worker_mb']['value'],
            ],
            'code' => ['profile' => $code['profile']['value'], 'mailQueued' => $code['mail_queue'], 'signupMailQueued' => !$code['signup_mail_inline']],
            'mail' => ['smtpMs' => 1700, 'workers' => 4, 'dailyQuota' => $gmail ? 500 : 5000, 'quotaUsed' => $app['mail_queue']['sent'], 'down' => $app['smtp'] === false],
        ];
        return ['generated' => date('c'), 'hardware' => $hw, 'web' => $web, 'php' => $php, 'db' => $db, 'code' => $code, 'app' => $app, 'sim' => $sim, 'ref' => self::REF];
    }

    /** Timed probes against this very server: the numbers the simulator scales its cost table with. */
    public static function calibrate(PDO $pdo): array
    {
        $out = [];
        $ms = fn(float $t0) => (microtime(true) - $t0) * 1000;

        $t = microtime(true); $x = 0;
        for ($i = 0; $i < 60000; $i++) { $x += strlen(json_encode(['a' => $i, 'b' => md5((string)$i), 'c' => [1, 2, 3]])); }
        $out['cpu_loop_ms'] = round($ms($t), 1);
        $t = microtime(true); password_hash('probe', PASSWORD_BCRYPT, ['cost' => 10]); password_hash('probe', PASSWORD_BCRYPT, ['cost' => 10]);
        $out['bcrypt_ms'] = round($ms($t) / 2, 1);

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $t = microtime(true);
        for ($i = 0; $i < 25; $i++) { $c = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $c = null; }
        $out['db_connect_ms'] = round($ms($t) / 25, 2);
        $st = $pdo->prepare('SELECT id, name FROM users WHERE id = :i');
        $t = microtime(true); for ($i = 0; $i < 300; $i++) { $st->execute(['i' => 1]); $st->fetch(); } $out['db_select_ms'] = round($ms($t) / 300, 3);
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ops_probe (id INT PRIMARY KEY, n INT NOT NULL DEFAULT 0, t TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            $w = $pdo->prepare("INSERT INTO ops_probe (id, n) VALUES (1, 1) ON DUPLICATE KEY UPDATE n = n + 1");
            $t = microtime(true); for ($i = 0; $i < 120; $i++) { $w->execute(); } $out['db_write_ms'] = round($ms($t) / 120, 3);
        } catch (Throwable) { $out['db_write_ms'] = null; }

        // HTTP round trips through the real stack (web server, PHP, database), at 1, 8 and 32 concurrent requests
        $out['http'] = null;
        if (PHP_SAPI === 'apache2handler' || PHP_SAPI === 'fpm-fcgi') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $url = $scheme . '://127.0.0.1:' . ((int)($_SERVER['SERVER_PORT'] ?? 80)) . '/api/live-eval-poll.php?code=__ops_probe__&action=poll_lobby';
            $res = [];
            foreach ([1, 8, 32] as $conc) {
                $lat = []; $t0 = microtime(true); $done = 0;
                for ($round = 0; $round < 3; $round++) {
                    $mh = curl_multi_init(); $hs = [];
                    for ($k = 0; $k < $conc; $k++) {
                        $h = curl_init($url); curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Host: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 'Connection: close'], CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]);
                        curl_multi_add_handle($mh, $h); $hs[] = $h;
                    }
                    do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.2); } while ($running > 0);
                    foreach ($hs as $h) { $lat[] = curl_getinfo($h, CURLINFO_TOTAL_TIME) * 1000; $done += curl_getinfo($h, CURLINFO_HTTP_CODE) === 200 ? 1 : 0; curl_multi_remove_handle($mh, $h); curl_close($h); }
                    curl_multi_close($mh);
                }
                sort($lat); $n = count($lat);
                $res[] = ['concurrency' => $conc, 'p50' => round($lat[(int)($n * .5)], 1), 'p95' => round($lat[min($n - 1, (int)($n * .95))], 1), 'rps' => round($n / max(0.001, microtime(true) - $t0)), 'ok' => $done];
            }
            $out['http'] = $res;
        }
        // speed factor of this machine against the reference one (1.0 = same speed, 2.0 = twice as slow)
        $r = self::REF; $parts = [$out['cpu_loop_ms'] / $r['cpu_loop_ms'], $out['bcrypt_ms'] / $r['bcrypt_ms']];
        $out['speed'] = round(max(0.3, min(6.0, array_sum($parts) / count($parts))), 2);
        $out['ref'] = $r;
        return $out;
    }
}
