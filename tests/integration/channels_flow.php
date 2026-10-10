<?php
declare(strict_types=1);

/**
 * Every channel between a student and a teacher, end to end: questions under a lesson (access, notification of the teacher, reply and
 * notification of the student), requests (review of an assignment mark, question about a live result, free message), the answer, the
 * notifications and emails on both sides, and who may not see or do what. Also the contestation of a cancelled result at the HTTP level
 * (its rules are tested in results_review.php). Needs the app running (default http://127.0.0.1:8123) and the database from .env.
 * Creates throw-away rows (zz.chtest.*@test.local: they never receive mail) and removes them.
 *
 *   php tests/integration/channels_flow.php [http://127.0.0.1:8123]
 */

putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/Assignments.php';
require_once __DIR__ . '/../../lib/LessonQA.php';
require_once __DIR__ . '/../../lib/StudentRequests.php';
require_once __DIR__ . '/../../lib/LiveResults.php';
require_once __DIR__ . '/../../lib/Contests.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

$pw = 'Chan-test-9Zq!';
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.chtest.%@test.local'");
$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$mk = function (string $n, string $role, ?string $mat = null, string $lang = 'fr') use ($pdo, $pw): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,matricule,tour_seen_at,lang) VALUES (?,?,?,?,NOW(),1,1,NULL,?,NOW(),?)")
        ->execute(["zz.chtest.$n@test.local", password_hash($pw, PASSWORD_DEFAULT), ucfirst($n), $role, $mat, $lang]);
    return (int)$pdo->lastInsertId();
};
$T = $mk('teacher', 'teacher'); $O = $mk('other', 'teacher');
$S1 = $mk('ama', 'student', '26C001', 'en'); $S2 = $mk('biyik', 'student', '26C002'); $S3 = $mk('outsider', 'student', '26C003');
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ chan course','d','',1)")->execute([$mod, $T, $T]);
$course = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ chan other course','d','',1)")->execute([$mod, $O, $O]);
$courseO = (int)$pdo->lastInsertId();
foreach ([$S1, $S2] as $u) { $pdo->prepare("INSERT INTO enrollments (student_id,course_id) VALUES (?,?)")->execute([$u, $course]); }
$pdo->prepare("INSERT INTO chapters (course_id,title,sort_order) VALUES (?,'Ch',1)")->execute([$course]); $ch = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO chapters (course_id,title,sort_order) VALUES (?,'ChO',1)")->execute([$courseO]); $chO = (int)$pdo->lastInsertId();
$mkLesson = function (int $chapter, string $title, array $o = []) use ($pdo): int {
    $pdo->prepare("INSERT INTO lessons (chapter_id,title,content_type,text_content,sort_order,has_assignment,assignment_title,assignment_type,allowed_file_types,assignment_deadline,assignment_max_score,assignment_allow_resubmit,assignment_release_mode,is_compulsory)
                   VALUES (?,?,'text','x',?,?,?,'link','pdf',?,20,1,?,0)")
        ->execute([$chapter, $title, $o['order'] ?? 1, $o['asg'] ?? 0, $o['asg'] ?? 0 ? $title : null, date('Y-m-d H:i:s', time() + 86400), $o['mode'] ?? 'immediate']);
    return (int)$pdo->lastInsertId();
};
$L = $mkLesson($ch, 'Leçon un'); $L2 = $mkLesson($ch, 'Leçon deux', ['order' => 2]); $LO = $mkLesson($chO, 'Leçon autre cours');
$LA = $mkLesson($ch, 'Devoir immédiat', ['asg' => 1, 'order' => 3]); $LM = $mkLesson($ch, 'Devoir manuel', ['asg' => 1, 'order' => 4, 'mode' => 'manual']);
foreach ([$S1, $S2] as $u) { foreach ([$L, $L2, $LA] as $l) { $pdo->prepare("INSERT IGNORE INTO lesson_progress (student_id, lesson_id, completed) VALUES (?,?,1)")->execute([$u, $l]); } }
// live session with two results: a normal one (S1, 80 %) and a cancelled one (S2)
$pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status) VALUES (?,?,'ZZ chan exam',?,NOW() - INTERVAL 3 HOUR,NOW() - INTERVAL 2 HOUR,30,1)")->execute([$course, $T, 'ZC' . bin2hex(random_bytes(3))]);
$sess = (int)$pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
$reg = function (int $uid, string $name, ?float $score, bool $cancel = false) use ($pdo, $sess): int {
    $pdo->prepare("INSERT INTO live_eval_registrations (session_id,name,email,student_id,score,cancelled_at,cancelled_reason,cancelled_score) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$sess, $name, "zz.chtest." . strtolower($name) . "@test.local", $uid, $cancel ? null : $score, $cancel ? date('Y-m-d H:i:s') : null, $cancel ? 'Réponses identiques' : null, $cancel ? $score : null]);
    return (int)$pdo->lastInsertId();
};
$r1 = $reg($S1, 'Ama', 80.0); $r2 = $reg($S2, 'Biyik', 65.0, true);

$cleanup = function () use ($pdo) {
    $pdo->exec("DELETE FROM courses WHERE title LIKE 'ZZ chan %'");
    $pdo->exec("DELETE FROM live_eval_sessions WHERE title LIKE 'ZZ chan %'");
    $pdo->exec("DELETE FROM users WHERE email LIKE 'zz.chtest.%@test.local'");
    $pdo->exec("DELETE FROM email_outbox WHERE to_email LIKE 'zz.chtest.%'");
    $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'qa:%' OR bucket LIKE 'request:%' OR bucket LIKE 'contest:%'");
    foreach (glob(sys_get_temp_dir() . '/zz_jar_ch_*') ?: [] as $f) { @unlink($f); }
};
register_shutdown_function($cleanup);

function http(string $jar, string $base, string $path, array $post = [], array $headers = []): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $headers]);
    if ($post !== []) { curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]); }
    $body = (string)curl_exec($ch);
    return ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'json' => json_decode($body, true), 'body' => $body];
}
$jar = fn(string $n) => sys_get_temp_dir() . "/zz_jar_ch_$n";
foreach (['teacher', 'other', 'ama', 'biyik', 'outsider'] as $n) {
    @unlink($jar($n));
    check("$n signs in", (http($jar($n), $base, '/login-action.php', ['email' => "zz.chtest.$n@test.local", 'password' => $pw])['json']['success'] ?? false) === true);
}
$one = fn(string $sql) => $pdo->query($sql)->fetchColumn();
$post = fn(string $who, int $lesson, string $text, array $x = [], array $h = []) => http($jar($who), $base, '/student/post-comment.php', ['lesson_id' => $lesson, 'comment_text' => $text] + $x, $h);
$comments = fn(string $who, int $lesson) => http($jar($who), $base, '/student/get-comments.php?lesson_id=' . $lesson);
$seen = function (string $who, int $lesson) use ($comments): string { $j = $comments($who, $lesson)['json']['comments'] ?? []; return implode("\n", array_map(fn($c) => $c['comment_text'] . ' ' . ($c['teacher_reply'] ?? ''), $j)); };

// ================================================================ questions under a lesson
check('a student not enrolled cannot read the discussion of a lesson', $comments('outsider', $L)['code'] === 403);
check('...nor write in it', ($r = $post('outsider', $L, 'Je ne suis pas inscrit'))['code'] === 403 && ($r['json']['error'] ?? '') === 'forbidden' && (int)$one("SELECT COUNT(*) FROM lesson_comments WHERE lesson_id = $L") === 0);
check('the teacher of ANOTHER course cannot read or write', $comments('other', $L)['code'] === 403 && $post('other', $L, 'Intrus')['code'] === 403);
check('an anonymous visitor cannot', http(sys_get_temp_dir() . '/zz_jar_ch_anon', $base, '/student/get-comments.php?lesson_id=' . $L)['code'] === 200 && (http(sys_get_temp_dir() . '/zz_jar_ch_anon', $base, '/student/get-comments.php?lesson_id=' . $L)['json']['success'] ?? true) === false);
check('an unknown lesson', $post('ama', 99999999, 'Bonjour')['code'] === 404);
check('an empty message and a too long one are refused', ($post('ama', $L, '   ')['json']['error'] ?? '') === 'empty' && ($post('ama', $L, str_repeat('x', LessonQA::MAX + 1))['json']['error'] ?? '') === 'too_long');
check('a request from another website is refused', $post('ama', $L, 'Question piège', [], ['Sec-Fetch-Site: cross-site'])['code'] === 403);

$r = $post('ama', $L, 'Pouvez-vous réexpliquer la notion de limite ?');
$q1 = (int)($r['json']['comment']['id'] ?? 0);
check('an enrolled student asks a question', ($r['json']['success'] ?? false) === true && $q1 > 0 && $r['json']['comment']['author_role'] === 'student', $r['body']);
$n = $pdo->query("SELECT type, title, link FROM notifications WHERE user_id = $T AND type = 'question' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('the teacher is told (notification pointing to the Questions tab)', $n && str_contains($n['title'], 'Ama') && str_contains((string)$n['link'], "course_id=$course") && str_contains((string)$n['link'], '#tab-comments'), json_encode($n));
check('a classmate sees the question', str_contains($seen('biyik', $L), 'réexpliquer la notion de limite'));
check('a thread: another student answers under the question', ($r = $post('biyik', $L, 'Regarde le chapitre 2.', ['parent_id' => $q1]))['json']['success'] === true);
$q2 = (int)$one("SELECT id FROM lesson_comments WHERE lesson_id = $L AND parent_id = $q1");
check('a thread under a message of another lesson is refused', ($post('ama', $L2, 'Mauvais fil', ['parent_id' => $q1])['json']['error'] ?? '') === 'bad_parent');
check('a thread under a reply (only one level) is refused', ($post('ama', $L, 'Trop profond', ['parent_id' => $q2])['json']['error'] ?? '') === 'bad_parent');
check('the teacher sees them in the dashboard feed', str_contains(html_entity_decode(http($jar('teacher'), $base, '/teacher/dashboard.php?course_id=' . $course)['body']), 'réexpliquer la notion de limite'));

$reply = fn(string $who, int $id, string $text, array $h = []) => http($jar($who), $base, '/teacher/reply-comment.php', ['comment_id' => $id, 'reply_text' => $text], $h);
check('a student cannot reply as the teacher', $reply('ama', $q1, 'Je me réponds moi-même')['code'] === 403 && $one("SELECT teacher_reply FROM lesson_comments WHERE id = $q1") === null);
check('another teacher cannot reply', ($reply('other', $q1, 'Intrus')['json']['error'] ?? '') === 'not_found');
check('an empty reply is refused', ($reply('teacher', $q1, '  ')['json']['error'] ?? '') === 'empty');
check('a reply from another website is refused', $reply('teacher', $q1, 'Réponse piège', ['Sec-Fetch-Site: cross-site'])['code'] === 403);
check('the teacher replies', ($r = $reply('teacher', $q1, 'Une limite décrit le comportement de f près d’un point. Voir l’exemple 3.'))['json']['success'] === true, $r['body']);
$n = $pdo->query("SELECT type, title, link FROM notifications WHERE user_id = $S1 AND type = 'reply' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('the student is told in the app (notification linking to the lesson)', $n && str_contains((string)$n['link'], "lesson_id=$L"), json_encode($n));
$mail = $pdo->query("SELECT template, args FROM email_outbox WHERE to_email = 'zz.chtest.ama@test.local' AND template = 'comment_reply'")->fetchAll(PDO::FETCH_ASSOC);
$args = json_decode($mail[0]['args'] ?? '{}', true);
check('...and by email, once, in their language, with the question and the answer', count($mail) === 1 && ($args['lang'] ?? '') === 'en' && str_contains($args['question'] ?? '', 'limite') && str_contains($args['reply'] ?? '', 'comportement'));
$sent = []; Mailer::$capture = function ($to, $subject, $html) use (&$sent) { $sent[] = ['to' => $to, 'subject' => $subject, 'html' => $html]; };
EmailQueue::work($pdo);
$m = array_values(array_filter($sent, fn($x) => $x['to'] === 'zz.chtest.ama@test.local'))[0] ?? ['subject' => '', 'html' => ''];
check('the email renders (English subject, the answer, a link to the lesson)', str_contains($m['subject'], 'Answer') && str_contains($m['html'], 'comportement') && str_contains($m['html'], "lesson_id=$L"), $m['subject']);
Mailer::$capture = null;
check('editing the reply does not notify again', $reply('teacher', $q1, 'Version corrigée de la réponse, plus complète.')['json']['success'] === true && (int)$one("SELECT COUNT(*) FROM email_outbox WHERE template = 'comment_reply' AND to_email = 'zz.chtest.ama@test.local'") === 1 && (int)$one("SELECT COUNT(*) FROM notifications WHERE user_id = $S1 AND type = 'reply'") === 1);
check('a classmate and the student see the answer under the question', str_contains($seen('ama', $L), 'Version corrigée') && str_contains($seen('biyik', $L), 'Version corrigée'));
$hide = http($jar('teacher'), $base, '/teacher/moderate-comment.php', ['comment_id' => $q2, 'is_hidden' => 1]);
check('the teacher hides a comment: nobody sees it any more', ($hide['json']['success'] ?? false) === true && !str_contains($seen('ama', $L), 'Regarde le chapitre 2'));
check('a student cannot moderate', http($jar('ama'), $base, '/teacher/moderate-comment.php', ['comment_id' => $q1, 'is_hidden' => 1])['code'] === 403 && (int)$one("SELECT is_hidden FROM lesson_comments WHERE id = $q1") === 0);
check('another teacher cannot moderate', (http($jar('other'), $base, '/teacher/moderate-comment.php', ['comment_id' => $q1, 'is_hidden' => 1])['json']['success'] ?? true) === false);
$pdo->exec("DELETE FROM rate_hits WHERE bucket = 'qa:$S2'");
$okN = 0; for ($i = 0; $i < 22; $i++) { $okN += (int)(LessonQA::post($pdo, ['id' => $S2, 'role' => 'student', 'name' => 'Biyik'], $L, "message $i", null)['ok']); }
check('a student cannot flood the discussion (20 messages an hour)', $okN === 20, (string)$okN);

// ================================================================ requests: a live result
$ask = fn(string $who, array $p, array $h = []) => http($jar($who), $base, '/api/requests.php', $p, $h);
$tReq = fn(string $who = 'teacher') => http($jar($who), $base, '/teacher/requests.php');
check('only a student can use the requests endpoint', $ask('teacher', ['kind' => 'course', 'course_id' => $course, 'subject' => 'x y z', 'message' => str_repeat('a', 30)])['code'] === 403);
check('an unknown kind', ($ask('ama', ['kind' => 'magic', 'message' => str_repeat('a', 30)])['json']['error'] ?? '') === 'kind');
check('a short message is refused', ($ask('ama', ['kind' => 'live_result', 'ref_id' => $r1, 'message' => 'trop court'])['json']['error'] ?? '') === 'too_short');
check('a request from another website is refused', $ask('ama', ['kind' => 'live_result', 'ref_id' => $r1, 'message' => str_repeat('a', 30)], ['Sec-Fetch-Site: cross-site'])['code'] === 403);
check('a student cannot ask about the result of another', ($ask('biyik', ['kind' => 'live_result', 'ref_id' => $r1, 'message' => str_repeat('Question sur la correction. ', 3)])['json']['error'] ?? '') === 'forbidden');
check('a cancelled result is redirected to the contestation', ($ask('biyik', ['kind' => 'live_result', 'ref_id' => $r2, 'message' => str_repeat('Question sur la correction. ', 3)])['json']['error'] ?? '') === 'cancelled');
$r = $ask('ama', ['kind' => 'live_result', 'ref_id' => $r1, 'message' => 'La question 4 me semble avoir deux bonnes réponses, pouvez-vous vérifier ?']);
$req1 = (int)($r['json']['request_id'] ?? 0);
check('a student asks about their result', ($r['json']['success'] ?? false) === true && $req1 > 0, $r['body']);
check('once per result', ($ask('ama', ['kind' => 'live_result', 'ref_id' => $r1, 'message' => str_repeat('Encore une question. ', 3)])['json']['error'] ?? '') === 'already');
check('the teacher is told (notification and queued email)', (int)$one("SELECT COUNT(*) FROM notifications WHERE user_id = $T AND type = 'request'") === 1 && (int)$one("SELECT COUNT(*) FROM email_outbox WHERE template = 'request_received' AND to_email = 'zz.chtest.teacher@test.local'") === 1);
$inbox = $tReq()['json'];
check('the request is in the teacher inbox, open, with the student and the item', ($inbox['open'] ?? 0) === 1 && $inbox['requests'][0]['id'] === $req1 && $inbox['requests'][0]['student_name'] === 'Ama' && $inbox['requests'][0]['student_matricule'] === '26C001' && $inbox['requests'][0]['kind'] === 'live_result' && str_contains($inbox['requests'][0]['subject'], 'ZZ chan exam'), json_encode($inbox));
check('another teacher sees nothing and cannot answer', ($tReq('other')['json']['requests'] ?? ['x']) === [] && (http($jar('other'), $base, '/teacher/requests.php', ['action' => 'answer', 'id' => $req1, 'response' => str_repeat('Intrus. ', 3)])['json']['error'] ?? '') === 'not_found');
check('a student cannot use the teacher inbox', http($jar('ama'), $base, '/teacher/requests.php')['code'] === 403 && http($jar('ama'), $base, '/teacher/requests.php', ['action' => 'answer', 'id' => $req1, 'response' => str_repeat('Moi. ', 5)])['code'] === 403);
$ans = fn(int $id, string $text, array $h = []) => http($jar('teacher'), $base, '/teacher/requests.php', ['action' => 'answer', 'id' => $id, 'response' => $text], $h);
check('a too short answer is refused', ($ans($req1, 'ok')['json']['error'] ?? '') === 'too_short');
check('an answer from another website is refused', $ans($req1, 'Réponse piège pour test', ['Sec-Fetch-Site: cross-site'])['code'] === 403);
check('the teacher answers', ($r = $ans($req1, 'Vous avez raison : j’ai corrigé la question 4 et rétabli les points concernés.'))['json']['success'] === true, $r['body']);
check('only once', ($ans($req1, 'Une seconde réponse pour voir.')['json']['error'] ?? '') === 'already');
check('the student is told (notification, email in their language)', (int)$one("SELECT COUNT(*) FROM notifications WHERE user_id = $S1 AND type = 'request_answer'") === 1 && json_decode((string)$one("SELECT args FROM email_outbox WHERE template = 'request_answer' AND to_email = 'zz.chtest.ama@test.local'"), true)['lang'] === 'en');
$mine = http($jar('ama'), $base, '/api/requests.php')['json'];
check('the student reads the answer in their list', count($mine['requests']) === 1 && $mine['requests'][0]['status'] === 'answered' && str_contains($mine['requests'][0]['response'], 'question 4'));
check('a student only sees their own requests', http($jar('biyik'), $base, '/api/requests.php')['json']['requests'] === []);
check('the inbox now shows it answered, no longer open', ($t = $tReq()['json'])['open'] === 0 && $t['requests'][0]['status'] === 'answered');

// ================================================================ requests: an assignment mark
$subA = function (string $who, int $lesson, int $uid) use ($base, $jar, $pdo) { http($jar($who), $base, '/api/submit-assignment.php', ['lesson_id' => $lesson, 'assignment_link' => 'https://x.cm/' . $uid . '/' . $lesson]); return (int)$pdo->query("SELECT id FROM lesson_assignment_submissions WHERE lesson_id = $lesson AND student_id = $uid")->fetchColumn(); };
$sA = $subA('ama', $LA, $S1);
check('a mark that does not exist yet cannot be questioned', ($ask('ama', ['kind' => 'assignment', 'ref_id' => $sA, 'message' => str_repeat('Merci de réviser ma note. ', 3)])['json']['error'] ?? '') === 'not_released');
Assignments::grade($pdo, $sA, $T, '9', 'Rapport incomplet.');
check('a student cannot ask about the mark of another student', ($ask('biyik', ['kind' => 'assignment', 'ref_id' => $sA, 'message' => str_repeat('Merci de réviser ma note. ', 3)])['json']['error'] ?? '') === 'forbidden');
$r = $ask('ama', ['kind' => 'assignment', 'ref_id' => $sA, 'message' => 'Je pense avoir traité la partie 3 dans l’annexe : pouvez-vous la relire ?']);
check('once the mark is released the student can ask for a review', ($r['json']['success'] ?? false) === true, $r['body']);
check('...but only once for that mark', ($ask('ama', ['kind' => 'assignment', 'ref_id' => $sA, 'message' => str_repeat('Merci de réviser ma note. ', 3)])['json']['error'] ?? '') === 'already');
$inb = $tReq()['json']['requests'];
$asg = array_values(array_filter($inb, fn($x) => $x['kind'] === 'assignment'))[0] ?? [];
check('the teacher sees it with the submission to open', ($asg['ref_id'] ?? 0) === $sA && $asg['status'] === 'open' && str_contains($asg['subject'], 'Devoir immédiat'));
$tr = http($jar('teacher'), $base, '/teacher/grade-assignment.php', ['action' => 'grade', 'submission_id' => $sA, 'score' => '13', 'feedback' => 'Annexe relue : note relevée.']);
check('the answer to a review request is the new mark, given in the marking window', ($tr['json']['success'] ?? false) === true && (float)$one("SELECT score FROM lesson_assignment_submissions WHERE id = $sA") === 13.0);
check('...and the request is answered with a word to the student', ($ans((int)$asg['id'], 'J’ai relu l’annexe : la note passe de 9 à 13.')['json']['success'] ?? false) === true);
Assignments::requestRevision($pdo, $sA, $T, 'Ajoutez la partie sur les tests.');
$subA('ama', $LA, $S1);
Assignments::grade($pdo, $sA, $T, '15', 'Mieux.');
check('after a new version is marked, a new review request is possible (a new mark)', ($ask('ama', ['kind' => 'assignment', 'ref_id' => $sA, 'message' => 'Je souhaite comprendre le retrait de points sur la conclusion.'])['json']['success'] ?? false) === true);
// manual release: a held mark cannot be questioned
$pdo->prepare("INSERT IGNORE INTO lesson_progress (student_id, lesson_id, completed) VALUES (?,?,1)")->execute([$S2, $LA]);
$sM = $subA('biyik', $LM, $S2);
Assignments::grade($pdo, $sM, $T, '5', 'Insuffisant.');
check('a mark the teacher still holds back cannot be questioned (it does not exist for the student)', ($ask('biyik', ['kind' => 'assignment', 'ref_id' => $sM, 'message' => str_repeat('Pourquoi cette note ? ', 3)])['json']['error'] ?? '') === 'not_released');

// ================================================================ requests: a free message to the teacher
check('a student not enrolled cannot write to the teacher of that course', ($ask('outsider', ['kind' => 'course', 'course_id' => $course, 'subject' => 'Bonjour', 'message' => str_repeat('Message libre. ', 3)])['json']['error'] ?? '') === 'not_found');
check('a free message needs a subject', ($ask('biyik', ['kind' => 'course', 'course_id' => $course, 'subject' => '', 'message' => str_repeat('Message libre. ', 3)])['json']['error'] ?? '') === 'subject');
$r = $ask('biyik', ['kind' => 'course', 'course_id' => $course, 'subject' => 'Problème pendant l’examen', 'message' => 'Ma connexion a coupé à la question 6 ; je voudrais pouvoir expliquer ce qui s’est passé.']);
check('an enrolled student writes a free message (complaint, problem...)', ($r['json']['success'] ?? false) === true, $r['body']);
check('several free messages are possible (not one per item)', ($ask('biyik', ['kind' => 'course', 'course_id' => $course, 'subject' => 'Suite', 'message' => 'Complément : la coupure a duré environ deux minutes.'])['json']['success'] ?? false) === true);
check('they reach the right teacher only', (int)$one("SELECT COUNT(*) FROM student_requests WHERE teacher_id = $T AND kind = 'course'") === 2 && (int)$one("SELECT COUNT(*) FROM student_requests WHERE teacher_id = $O") === 0);
check('the teacher dashboard shows the open requests', str_contains(html_entity_decode(http($jar('teacher'), $base, '/teacher/dashboard.php?course_id=' . $course)['body']), 'Problème pendant l’examen'));

// ================================================================ the contestation of a cancelled result
$rc = http($jar('biyik'), $base, '/api/contest.php', ['registration_id' => $r2, 'message' => 'Mes réponses identiques à celles de ma voisine s’expliquent : nous avons révisé ensemble et j’ai rédigé seul.']);
check('a student contests a cancelled result', ($rc['json']['success'] ?? false) === true, $rc['body']);
check('the teacher is told (notification and email)', (int)$one("SELECT COUNT(*) FROM notifications WHERE user_id = $T AND type = 'contest'") === 1 && (int)$one("SELECT COUNT(*) FROM email_outbox WHERE template = 'contest_received' AND to_email = 'zz.chtest.teacher@test.local'") === 1);
check('another student cannot contest it', ($x = http($jar('ama'), $base, '/api/contest.php', ['registration_id' => $r2, 'message' => str_repeat('Je conteste à sa place. ', 3)])) && $x['code'] === 403);
$cid = (int)$one("SELECT id FROM result_contests WHERE registration_id = $r2");
check('the teacher decides: the result is restored and the student is told', Contests::resolve($pdo, $cid, $T, true, 'Explication acceptée.')['ok'] === true && $one("SELECT cancelled_at FROM live_eval_registrations WHERE id = $r2") === null && (int)$one("SELECT COUNT(*) FROM email_outbox WHERE template = 'contest_decision' AND to_email = 'zz.chtest.biyik@test.local'") === 1 && (int)$one("SELECT COUNT(*) FROM notifications WHERE user_id = $S2 AND type LIKE '%contest%'") >= 1);

// ================================================================ the bell, on both sides
$pdo->exec("DELETE FROM notifications WHERE user_id = $T AND type = 'question' AND id < (SELECT m FROM (SELECT MAX(id) m FROM notifications WHERE user_id = $T AND type = 'question') x)"); // the rate-limit test above created many; the bell shows the latest 20
$bt = http($jar('teacher'), $base, '/student/get-notifications.php')['json'];
check('the teacher\'s bell lists the questions, requests and contestations', $bt['unread_count'] >= 3 && count(array_filter($bt['notifications'], fn($n) => $n['type'] === 'question')) >= 1 && count(array_filter($bt['notifications'], fn($n) => $n['type'] === 'request')) >= 1 && count(array_filter($bt['notifications'], fn($n) => $n['type'] === 'contest')) >= 1);
$bs = http($jar('ama'), $base, '/student/get-notifications.php')['json'];
check('the student\'s bell lists the reply, the answer to the request and the marks', count(array_filter($bs['notifications'], fn($n) => $n['type'] === 'reply')) === 1 && count(array_filter($bs['notifications'], fn($n) => $n['type'] === 'request_answer')) >= 1 && count(array_filter($bs['notifications'], fn($n) => $n['type'] === 'assignment_graded')) >= 1, implode(',', array_column($bs['notifications'], 'type')));
check('a student never sees the notifications of a teacher', count(array_filter($bs['notifications'], fn($n) => in_array($n['type'], ['question', 'request', 'contest'], true))) === 0);

echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
