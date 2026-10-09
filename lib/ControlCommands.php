<?php
declare(strict_types=1);

/**
 * The control center's command console. Every command is listed with its risk, high-risk ones must be confirmed by typing
 * their name, and every run is written to the audit log.
 */
final class ControlCommands
{
    public static function catalog(): array
    {
        return [
            ['id' => 'health_check',      'risk' => 'low',  'label' => 'Health check',               'about' => 'Tests the database, writable folders, disk space, mail setup and schema, and lists anything that needs attention.'],
            ['id' => 'purge_live_cache',  'risk' => 'low',  'label' => 'Empty live-evaluation cache', 'about' => 'Deletes the small shared cache files of live evaluations. They rebuild themselves within seconds. Useful after editing a running session.'],
            ['id' => 'purge_insights',    'risk' => 'low',  'label' => 'Refresh dashboard numbers',   'about' => 'Clears the 20-second cache of the promoter dashboard so the next load recomputes everything.'],
            ['id' => 'opcache_reset',     'risk' => 'low',  'label' => 'Reset PHP opcache',           'about' => 'Makes PHP recompile every file at its next request. Run it after deploying code on a server that never rechecks file dates.'],
            ['id' => 'rerun_migrations',  'risk' => 'mid',  'label' => 'Re-run database migrations',  'about' => 'Forgets the migration stamp and applies every schema migration again (they are safe to repeat).'],
            ['id' => 'purge_logs',        'risk' => 'mid',  'label' => 'Purge old security logs now', 'about' => 'Deletes sign-in attempts older than 90 days and audit entries older than one year, as promised in the privacy policy.'],
            ['id' => 'retry_mails',       'risk' => 'mid',  'label' => 'Retry failed result emails',  'about' => 'Puts failed results emails back in the queue and starts the background senders.'],
            ['id' => 'clear_lockouts',    'risk' => 'mid',  'label' => 'Clear sign-in lockouts',      'about' => 'Removes all recorded failed sign-in attempts, which unblocks accounts and addresses temporarily locked by the brute-force protection.'],
            ['id' => 'export_report',     'risk' => 'low',  'label' => 'Export a system report',      'about' => 'Downloads the detected configuration and the latest calibration as a JSON file, to keep or to compare after a change.'],
            ['id' => 'maintenance_on',    'risk' => 'high', 'label' => 'Maintenance mode: ON (30 min)', 'about' => 'Every visitor except a signed-in promoter gets a "back soon" page. It switches itself off after 30 minutes.', 'phrase' => 'MAINTENANCE'],
            ['id' => 'maintenance_off',   'risk' => 'mid',  'label' => 'Maintenance mode: OFF',       'about' => 'Reopens the platform immediately.'],
        ];
    }

    public static function run(PDO $pdo, string $id): array
    {
        $root = dirname(__DIR__);
        switch ($id) {
            case 'health_check':
                $r = [];
                $add = function (string $name, string $level, string $detail) use (&$r) { $r[] = ['name' => $name, 'level' => $level, 'detail' => $detail]; };
                try { $t = microtime(true); $pdo->query('SELECT 1'); $add('Database', 'ok', 'answers in ' . round((microtime(true) - $t) * 1000, 1) . ' ms'); } catch (Throwable $e) { $add('Database', 'fail', 'not reachable'); }
                foreach (['uploads', 'uploads/live_cache', 'uploads/avatars', 'uploads/pdfs'] as $d) {
                    $p = $root . '/' . $d; $add('Folder ' . $d, is_dir($p) ? (is_writable($p) ? 'ok' : 'fail') : 'warn', is_dir($p) ? (is_writable($p) ? 'writable' : 'not writable') : 'does not exist yet');
                }
                $free = (float)@disk_free_space($root) / 1073741824; $add('Disk', $free < 1 ? 'fail' : ($free < 5 ? 'warn' : 'ok'), round($free, 1) . ' GB free');
                $add('Email sending', class_exists('Mailer') && Mailer::isConfigured() ? 'ok' : 'warn', class_exists('Mailer') && Mailer::isConfigured() ? 'SMTP configured' : 'SMTP not configured: confirmations, certificates and results will not be sent');
                $add('HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (defined('HTTPS_ONLY') && HTTPS_ONLY === 'true') ? 'ok' : 'warn', 'cookies are only marked secure on HTTPS');
                $add('PHP exec()', function_exists('exec') ? 'ok' : 'warn', function_exists('exec') ? 'available for the background mail senders' : 'disabled: results emails stay queued until `php lib/live-mail-worker.php` runs');
                try { $d = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE matricule IS NOT NULL AND matricule <> '' GROUP BY matricule HAVING COUNT(*) > 1 LIMIT 1")->fetchColumn(); $add('Duplicate matricules', $d ? 'warn' : 'ok', $d ? 'at least one matricule is held by two accounts' : 'none'); } catch (Throwable) {}
                try { $q = (int)$pdo->query("SELECT COUNT(*) FROM live_eval_mail_queue WHERE status='failed'")->fetchColumn(); $add('Results emails', $q ? 'warn' : 'ok', $q ? "$q failed" : 'no failures'); } catch (Throwable) {}
                return ['message' => 'Health check finished.', 'checks' => $r];

            case 'purge_live_cache':
                $n = 0; foreach (glob($root . '/uploads/live_cache/*') ?: [] as $f) { if (is_file($f) && @unlink($f)) { $n++; } }
                return ['message' => "$n cache file(s) removed."];

            case 'purge_insights':
                $n = 0; foreach (glob($root . '/uploads/insights_cache/*') ?: [] as $f) { if (is_file($f) && @unlink($f)) { $n++; } }
                return ['message' => "$n cached dashboard file(s) removed."];

            case 'opcache_reset':
                if (!function_exists('opcache_reset')) { return ['message' => 'opcache is not loaded on this server.', 'warn' => true]; }
                return ['message' => opcache_reset() ? 'opcache reset: files recompile on their next request.' : 'opcache could not be reset.'];

            case 'rerun_migrations':
                Database::forceMigrate();
                return ['message' => 'Migrations applied again without error.'];

            case 'purge_logs':
                $a = $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 90 DAY)");
                $b = $pdo->exec("DELETE FROM audit_logs WHERE created_at < (NOW() - INTERVAL 365 DAY)");
                return ['message' => "$a sign-in attempt(s) and $b audit entr(ies) deleted."];

            case 'retry_mails':
                $n = $pdo->exec("UPDATE live_eval_mail_queue SET status='pending', attempts=0, next_try_at=NULL, claim=NULL WHERE status IN ('failed','sending')");
                require_once $root . '/lib/LiveMailQueue.php'; LiveMailQueue::kick();
                return ['message' => "$n email(s) put back in the queue, senders started."];

            case 'clear_lockouts':
                $n = $pdo->exec("DELETE FROM login_attempts");
                return ['message' => "$n recorded attempt(s) cleared."];

            case 'maintenance_on':
                @file_put_contents($root . '/uploads/maintenance.flag', (string)(time() + 1800));
                return ['message' => 'Maintenance mode is on for 30 minutes. You can still sign in and use every page.'];

            case 'maintenance_off':
                $had = is_file($root . '/uploads/maintenance.flag'); @unlink($root . '/uploads/maintenance.flag');
                return ['message' => $had ? 'The platform is open again.' : 'Maintenance mode was not on.'];
        }
        return ['message' => 'Unknown command.', 'warn' => true];
    }
}
