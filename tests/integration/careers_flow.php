<?php
declare(strict_types=1);

/**
 * Careers (lib/Careers.php): postings, validation of applications, the résumé content check, the review pipeline, e-mails to the
 * candidate and the erasure of an application with its file. Everything created here is deleted at the end.
 * The upload itself (move_uploaded_file) only works inside a web request; promoter/careers and career-apply.php are covered by the browser run.
 *
 *   php tests/integration/careers_flow.php
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../Mailer.php';
require_once __DIR__ . '/../../lib/Careers.php';

$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what; }

$tag = 'zz-careers-' . bin2hex(random_bytes(3));
$pdo->prepare("INSERT INTO users (email, password, name, role, email_verified_at, is_active, is_approved, lang) VALUES (?, ?, 'Careers Test', 'promoter', NOW(), 1, 1, 'en')")
    ->execute([$tag . '@studyvibe-test.local', password_hash('x', PASSWORD_DEFAULT)]);
$admin = (int)$pdo->lastInsertId();
$mail = [];
Mailer::$capture = function ($to, $subject, $html) use (&$mail) { $mail[] = [$to, $subject, $html]; };
$jobIds = []; $files = [];

try {
    // --- text helpers
    check('slug: accents and symbols become a clean slug', Careers::slugify('Développeur·se Full-Stack (H/F) !') === 'developpeur-se-full-stack-h-f');
    check('slug: the reserved word is not usable as a posting slug', Careers::uniqueSlug($pdo, 'Spontaneous') !== Careers::SPONTANEOUS);
    check('list text: bullets and numbers are stripped, blank lines dropped', Careers::listText("- one\n\n2) two\n• three") === "one\ntwo\nthree");
    check('url: scheme is added, junk is refused', Careers::url('linkedin.com/in/x') === 'https://linkedin.com/in/x' && Careers::url('javascript:alert(1)') === '' && Careers::url('not a url') === '');

    // --- postings
    $bad = Careers::saveJob($pdo, $admin, null, ['title' => 'ab']);
    check('a posting needs a title', !$bad['ok'] && isset($bad['fields']['title']));
    $draft = Careers::saveJob($pdo, $admin, null, ['department' => 'engineering', 'title' => "QA $tag", 'status' => 'draft']);
    check('a draft can be saved with little', $draft['ok'] && $draft['created']);
    $jobIds[] = $id = (int)$draft['id'];
    $pub = Careers::saveJob($pdo, $admin, $id, ['title' => "QA $tag", 'status' => 'open', 'summary' => 'short']);
    check('publishing needs a readable summary and description', !$pub['ok'] && isset($pub['fields']['summary']) && isset($pub['fields']['description']));
    $pub = Careers::setJobStatus($pdo, $id, 'open');
    check('setJobStatus refuses to publish an incomplete posting', !$pub['ok'] && $pub['error'] === 'incomplete');
    $ok = Careers::saveJob($pdo, $admin, $id, ['title' => "QA $tag", 'status' => 'open', 'summary' => 'Own the quality of every release of the platform.', 'description' => "Para one.\n\nPara two.", 'responsibilities' => "- Write tests\n- Break things", 'closes_at' => date('Y-m-d', strtotime('+30 day')), 'work_mode' => 'remote', 'employment_type' => 'contract']);
    check('a complete posting is published', $ok['ok'] && !$ok['created']);
    $job = Careers::job($pdo, $id);
    check('publishing stamps published_at and keeps the slug', $job['status'] === 'open' && $job['published_at'] !== null && $job['slug'] === $draft['slug']);
    check('it shows on the public list', in_array($id, array_column(Careers::publicJobs($pdo), 'id'), true));
    $past = Careers::saveJob($pdo, $admin, $id, ['title' => "QA $tag", 'status' => 'open', 'summary' => 'Own the quality of every release of the platform.', 'description' => 'x', 'closes_at' => date('Y-m-d', strtotime('-1 day'))]);
    check('an open posting cannot have a closing date in the past', !$past['ok'] && isset($past['fields']['closes_at']));
    $pdo->prepare('UPDATE career_jobs SET closes_at = ? WHERE id = ?')->execute([date('Y-m-d', strtotime('-1 day')), $id]);
    check('a posting past its closing date leaves the public list by itself', !in_array($id, array_column(Careers::publicJobs($pdo), 'id'), true) && !Careers::isOpen(Careers::job($pdo, $id)));
    $pdo->prepare('UPDATE career_jobs SET closes_at = NULL WHERE id = ?')->execute([$id]);
    $r = Careers::setJobStatus($pdo, $id, 'paused');
    check('pausing hides it', $r['ok'] && !in_array($id, array_column(Careers::publicJobs($pdo), 'id'), true));
    Careers::setJobStatus($pdo, $id, 'open');
    check('reopening keeps the first publication date', Careers::job($pdo, $id)['published_at'] === $job['published_at']);

    // --- teams
    check('a posting cannot be created without a team', Careers::saveJob($pdo, $admin, null, ['title' => "No team $tag"])['fields']['department'] === 'required');
    check('a team that is not in the list is refused', Careers::saveJob($pdo, $admin, null, ['title' => "Bad team $tag", 'department' => 'wizards'])['fields']['department'] === 'invalid');
    check('the team is stored as its key, whatever label or alias was given (FR, EN, alias)', Careers::job($pdo, $id)['department'] === 'engineering' && Careers::teamKey('Ventes et partenariats') === 'sales' && Careers::teamKey('Customer Success') === 'success' && Careers::teamKey('dev') === 'engineering');
    check('an update that does not send the team keeps it', Careers::saveJob($pdo, $admin, $id, ['title' => "QA $tag", 'status' => 'draft'])['ok'] && Careers::job($pdo, $id)['department'] === 'engineering');
    Careers::saveJob($pdo, $admin, $id, ['title' => "QA $tag", 'department' => 'Éducation', 'status' => 'open', 'summary' => 'Own the quality of every release of the platform.', 'description' => 'x']);
    check('the team can be changed to another one of the list', Careers::job($pdo, $id)['department'] === 'education');
    check('labels come in both languages and an unknown value is shown as it is', Careers::teamLabel('sales', 'en') === 'Sales & Partnerships' && Careers::teamLabel('sales', 'fr') === 'Ventes et partenariats' && Careers::teamLabel('legacy text', 'en') === 'legacy text');

    // --- posting code and application reference
    check('a posting gets a 4-character code of its own on creation', preg_match('/^[A-Z0-9]{4}$/', (string)$job['code']) === 1);
    check('the suggested code is readable (initials, or the first letters of each word)', Careers::suggestCode('Senior Full-Stack Developer') === 'SFSD' && Careers::suggestCode('Product Designer') === 'PRDE' && Careers::suggestCode('Intern') === 'INTE' && strlen(Careers::suggestCode('QA')) === 4);
    $twin = Careers::saveJob($pdo, $admin, null, ['department' => 'engineering', 'title' => "QA $tag", 'status' => 'draft']); $jobIds[] = (int)$twin['id'];
    check('two postings with the same title never share a code', Careers::job($pdo, (int)$twin['id'])['code'] !== $job['code']);
    $own = Careers::saveJob($pdo, $admin, $id, ['title' => "QA $tag", 'status' => 'open', 'summary' => 'Own the quality of every release of the platform.', 'description' => 'x', 'code' => 'zq7k']);
    check('an administrator can choose the code (capitalised) and it is kept', $own['ok'] && Careers::job($pdo, $id)['code'] === 'ZQ7K');
    $dup = Careers::saveJob($pdo, $admin, (int)$twin['id'], ['title' => "QA $tag", 'code' => 'ZQ7K']);
    check('a code already used by another posting is refused', !$dup['ok'] && $dup['fields']['code'] === 'taken');
    check('a malformed code and the reserved OPEN code are refused', Careers::saveJob($pdo, $admin, $id, ['title' => "QA $tag", 'code' => 'AB'])['fields']['code'] === 'invalid' && Careers::saveJob($pdo, $admin, $id, ['title' => "QA $tag", 'code' => 'OPEN'])['fields']['code'] === 'taken');
    check('saving without a code keeps the current one', Careers::saveJob($pdo, $admin, $id, ['title' => "QA $tag", 'status' => 'open', 'summary' => 'Own the quality of every release of the platform.', 'description' => 'x'])['ok'] && Careers::job($pdo, $id)['code'] === 'ZQ7K');
    $ref = Careers::newReference($pdo, Careers::job($pdo, $id));
    check('a reference is CODE + year + 5 characters, no separator, from the unambiguous alphabet', preg_match('/^ZQ7K' . date('y') . '[A-HJKMNP-Z2-9]{5}$/', $ref) === 1 && strlen($ref) === 11);
    check('an open application uses the OPEN code', str_starts_with(Careers::newReference($pdo, null), 'OPEN' . date('y')));
    $seen = []; for ($i = 0; $i < 300; $i++) { $seen[Careers::newReference($pdo, Careers::job($pdo, $id))] = 1; }
    check('references do not repeat', count($seen) === 300);
    check('both reference shapes are recognised (new and the earlier CA-XXXXXX), others are not', Careers::isReference('ZQ7K26ABCDE') && Careers::isReference('CA-ABC123') && !Careers::isReference('ZQ7K-26-ABCDE') && !Careers::isReference('zq7k26abcde') && !Careers::isReference('x'));

    // --- applications: validation
    $e = Careers::validateApplication(['full_name' => 'A', 'email' => 'nope', 'phone' => 'abc', 'linkedin_url' => 'not a link', 'cover_letter' => 'short']);
    check('validation flags name, e-mail, phone, link, short letter and missing consent', count(array_intersect(['full_name', 'email', 'phone', 'linkedin_url', 'cover_letter', 'consent'], array_keys($e))) === 6);
    check('validation accepts a clean form', Careers::validateApplication(['full_name' => 'Ada Lovelace', 'email' => 'ada@x.org', 'consent' => 1]) === []);
    $nofile = Careers::submit($pdo, $job = Careers::job($pdo, $id), ['full_name' => 'Ada Lovelace', 'email' => "ada.$tag@studyvibe-test.local", 'consent' => 1], [], 'en', '203.0.113.9');
    check('an application without a résumé is refused', !$nofile['ok'] && ($nofile['fields']['resume'] ?? '') === 'required');
    Careers::setJobStatus($pdo, $id, 'closed');
    $closed = Careers::submit($pdo, Careers::job($pdo, $id), ['full_name' => 'Ada Lovelace', 'email' => 'ada@x.org', 'consent' => 1], [], 'en', '203.0.113.9');
    check('a closed posting takes no applications', !$closed['ok'] && $closed['error'] === 'closed');
    Careers::setJobStatus($pdo, $id, 'open');

    // --- résumé content check
    $tmp = tempnam(sys_get_temp_dir(), 'cv');
    file_put_contents($tmp, "%PDF-1.4\n...");  check('résumé: a real PDF passes', Careers::resumeKind($tmp, 'pdf') === null);
    file_put_contents($tmp, '<?php echo 1;');     check('résumé: PHP renamed to .pdf is refused', Careers::resumeKind($tmp, 'pdf') === 'bad_type');
    file_put_contents($tmp, "PK\x03\x04rest");    check('résumé: a ZIP-based file passes as docx, not as pdf', Careers::resumeKind($tmp, 'docx') === null && Careers::resumeKind($tmp, 'pdf') === 'bad_type');
    file_put_contents($tmp, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1x"); check('résumé: legacy .doc passes', Careers::resumeKind($tmp, 'doc') === null);
    @unlink($tmp);

    // --- the review pipeline, on an application inserted directly (the upload needs a web request)
    $dir = MediaStore::dir('career_resume'); $fn = 'cv_' . $tag . '.pdf'; file_put_contents($dir . $fn, '%PDF-1.4 test'); $files[] = $fn;
    MediaStore::persist($pdo, 'career_resume/' . $fn, $dir . $fn);
    $pdo->prepare("INSERT INTO career_applications (reference, job_id, job_title, full_name, email, resume_file, resume_name, resume_size, consent_at, lang) VALUES (?, ?, ?, 'Ada Lovelace', ?, ?, 'ada.pdf', 13, NOW(), 'en')")
        ->execute(['CA-' . strtoupper(substr(md5($tag), 0, 6)), $id, $job['title'], "ada.$tag@studyvibe-test.local", $fn]);
    $app = (int)$pdo->lastInsertId();
    $dup = Careers::submit($pdo, Careers::job($pdo, $id), ['full_name' => 'Ada', 'email' => "ada.$tag@studyvibe-test.local", 'consent' => 1], ['error' => UPLOAD_ERR_NO_FILE], 'en', '203.0.113.9');
    check('listing: the new application is first and starts as "new"', Careers::applications($pdo, ['q' => $tag])[0]['status'] === 'new');
    check('stats count it', Careers::stats($pdo)['by_status']['new'] >= 1 && Careers::newCount($pdo) >= 1);
    $mail = [];
    $r = Careers::setStatus($pdo, $app, $admin, 'interview', 'See you Thursday at 10.', true);
    check('status change with notice: moves, e-mails the candidate in their language', $r['ok'] && $r['mailed'] && count($mail) === 1 && $mail[0][0] === "ada.$tag@studyvibe-test.local" && str_contains($mail[0][1], 'About your application') && str_contains((string)$mail[0][2], 'See you Thursday'));
    $mail = [];
    Careers::setStatus($pdo, $app, $admin, 'rejected', '', false);
    check('status change without notice sends nothing', count($mail) === 0);
    $mail = [];
    Careers::setStatus($pdo, $app, $admin, 'rejected', '', true);
    check('a notice with no text uses the default message', count($mail) === 1 && str_contains((string)$mail[0][2], 'Thank you for the time'));
    check('invalid stage is refused', !Careers::setStatus($pdo, $app, $admin, 'bogus')['ok']);
    check('rating: 1-5 or cleared, nothing else', Careers::setRating($pdo, $app, $admin, 4)['ok'] && Careers::setRating($pdo, $app, $admin, null)['ok'] && !Careers::setRating($pdo, $app, $admin, 9)['ok']);
    check('notes: empty refused, real one kept', !Careers::addNote($pdo, $app, $admin, "  ")['ok'] && Careers::addNote($pdo, $app, $admin, 'Strong profile')['ok']);
    Careers::setStar($pdo, $app, true);
    $full = Careers::application($pdo, $app);
    check('detail: trail holds the moves, the note and the e-mails; the IP hash is never returned', count($full['events']) >= 5 && !array_key_exists('ip_hash', $full) && (int)$full['is_starred'] === 1);
    check('filters: by status, starred, text and posting', count(Careers::applications($pdo, ['status' => 'rejected', 'starred' => true, 'q' => 'Lovelace', 'job_id' => $id])) === 1 && count(Careers::applications($pdo, ['status' => 'hired', 'q' => $tag])) === 0);
    check('a LIKE wildcard in the search is taken literally', count(Careers::applications($pdo, ['q' => '%'])) === 0);
    check('a posting with applications cannot be deleted', Careers::deleteJob($pdo, $id)['error'] === 'has_applications');

    // --- erasure
    $del = Careers::deleteApplication($pdo, $app);
    check('deleting an application removes the row, its trail and the file', $del['ok'] && !Careers::exists($pdo, $app) && !is_file($dir . $fn) && !$pdo->query("SELECT 1 FROM career_application_events WHERE application_id = $app")->fetchColumn() && !$pdo->query("SELECT 1 FROM media_files WHERE media_key = 'career_resume/$fn'")->fetchColumn());
    check('then the posting can be deleted', Careers::deleteJob($pdo, $id)['ok']);
    $jobIds = [];
} finally {
    foreach ($jobIds as $j) { $pdo->prepare('DELETE FROM career_applications WHERE job_id = ?')->execute([$j]); $pdo->prepare('DELETE FROM career_jobs WHERE id = ?')->execute([$j]); }
    foreach ($files as $f) { MediaStore::delete($pdo, 'career_resume', $f); }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$admin]);
}

echo "careers: $passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL: $f\n"; }
exit($failed ? 1 : 0);
