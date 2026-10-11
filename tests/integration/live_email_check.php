<?php
declare(strict_types=1);

/**
 * The email field of the live-evaluation entry form recognises student accounts only: a teacher, an administrator or a deactivated
 * student is answered "not registered", and the name of a non-student is never disclosed. Needs the app running (default
 * http://127.0.0.1:8123). Throw-away rows zz.lec.*@test.local are removed at the end.
 *
 *   php tests/integration/live_email_check.php [http://127.0.0.1:8123]
 */
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/LiveGuest.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

// the domain rule needs a real accepted domain, so the throw-away accounts use gmail.com addresses that cannot exist for real
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.lec.%@gmail.com'");
$mk = function (string $n, string $role, int $active = 1) use ($pdo): string {
    $email = "zz.lec.$n@gmail.com";
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,tour_seen_at,lang) VALUES (?,?,?,?,NOW(),?,1,NULL,NOW(),'fr')")
        ->execute([$email, password_hash('x-Test-9Zq!', PASSWORD_DEFAULT), 'Nom ' . ucfirst($n), $role, $active]);
    return $email;
};
$emails = ['student' => $mk('student', 'student'), 'teacher' => $mk('teacher', 'teacher'), 'promoter' => $mk('promoter', 'promoter'), 'off' => $mk('off', 'student', 0)];
$ask = function (string $email) use ($base): array {
    $jar = tempnam(sys_get_temp_dir(), 'lec');
    $ch = curl_init($base . '/api/live-check-email.php?email=' . urlencode($email));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    $r = json_decode((string)curl_exec($ch), true);
    @unlink($jar);
    return is_array($r) ? $r : [];
};

$r = $ask($emails['student']);
check('a student account is recognised, with its name', ($r['allowed'] ?? false) === true && ($r['registered'] ?? false) === true && ($r['name'] ?? '') === 'Nom Student', json_encode($r));
$r = $ask($emails['teacher']);
check('a teacher account is not recognised', ($r['allowed'] ?? false) === true && ($r['registered'] ?? true) === false, json_encode($r));
check('...and the teacher\'s name is not disclosed', ($r['name'] ?? '') === '');
$r = $ask($emails['promoter']);
check('an administrator account is not recognised', ($r['registered'] ?? true) === false && ($r['name'] ?? '') === '', json_encode($r));
$r = $ask($emails['off']);
check('a deactivated student is not recognised', ($r['registered'] ?? true) === false, json_encode($r));
$r = $ask('zz.lec.nobody@gmail.com');
check('an unknown address is allowed (as a guest) but not recognised', ($r['allowed'] ?? false) === true && ($r['registered'] ?? true) === false);
$r = $ask('someone@yahoo.fr');
check('a domain that is not accepted is still refused', ($r['allowed'] ?? true) === false);

check('LiveGuest::findUser finds the student only', LiveGuest::findUser($pdo, $emails['student']) !== null
    && LiveGuest::findUser($pdo, $emails['teacher']) === null && LiveGuest::findUser($pdo, $emails['promoter']) === null && LiveGuest::findUser($pdo, $emails['off']) === null);

// the entry itself (POST register_live) must not link a teacher's address to the teacher's account either
$pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status,is_async) SELECT id, teacher_id, 'ZZ lec exam', 'zzlec0001', NOW() + INTERVAL 1 DAY, NOW() + INTERVAL 2 DAY, 30, 1, 0 FROM courses WHERE teacher_id IS NOT NULL LIMIT 1")->execute();
$sid = (int)$pdo->lastInsertId();
if ($sid > 0) {
    $enter = function (string $email, string $name) use ($base): void {
        $jar = tempnam(sys_get_temp_dir(), 'lec');
        $ch = curl_init($base . '/live-session.php?code=zzlec0001');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['register_live' => 1, 'email' => $email, 'name' => $name]), CURLOPT_TIMEOUT => 30]);
        curl_exec($ch); @unlink($jar);
    };
    $enter($emails['teacher'], 'Invité Prof');
    $row = $pdo->query("SELECT name, student_id FROM live_eval_registrations WHERE session_id = $sid AND email = '" . $emails['teacher'] . "'")->fetch(PDO::FETCH_ASSOC);
    check('a teacher entering as a guest is a guest: typed name kept, no account linked', $row && $row['name'] === 'Invité Prof' && $row['student_id'] === null, json_encode($row));
    $enter($emails['student'], 'Autre nom tapé');
    $row = $pdo->query("SELECT name, student_id FROM live_eval_registrations WHERE session_id = $sid AND email = '" . $emails['student'] . "'")->fetch(PDO::FETCH_ASSOC);
    check('a student entering by email keeps the name and the id of the account', $row && $row['name'] === 'Nom Student' && (int)$row['student_id'] > 0, json_encode($row));
    $pdo->exec("DELETE FROM live_eval_sessions WHERE id = $sid");
} else {
    check('a course with a teacher exists to run the entry test', false);
}

$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.lec.%@gmail.com'");
echo "\n" . $passed . ' passed, ' . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
