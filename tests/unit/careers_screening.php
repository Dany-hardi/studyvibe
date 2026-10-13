<?php
declare(strict_types=1);

/**
 * Unit tests of the ATS engine: résumé text extraction (PDF plain, compressed, with a font map; DOCX; DOC) and the screening rules.
 * No database, no web server.
 *
 *   php tests/unit/careers_screening.php
 */
require_once __DIR__ . '/../../lib/CareersScreening.php';

$passed = 0; $failed = [];
function t(string $name, callable $fn): void { global $passed, $failed; try { $fn(); $passed++; } catch (Throwable $e) { $failed[] = $name . ': ' . $e->getMessage(); } }
function eq($a, $b): void { if ($a !== $b) { throw new RuntimeException('expected ' . var_export($b, true) . ', got ' . var_export($a, true)); } }
function ok(bool $c, string $m = 'condition is false'): void { if (!$c) { throw new RuntimeException($m); } }

/** A one-page PDF whose text is drawn with Tj (and TJ), optionally Flate-compressed. */
function pdfWith(array $lines, bool $compress = false, bool $tj = false): string
{
    $c = "BT\n/F1 11 Tf\n14 TL\n50 780 Td\n";
    foreach ($lines as $l) {
        $esc = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $l);
        $c .= $tj ? '[(' . substr($esc, 0, 4) . ') -20 (' . substr($esc, 4) . ')] TJ' . "\nT*\n" : "($esc) Tj\nT*\n";
    }
    $c .= "ET\n";
    $data = $compress ? gzcompress($c) : $c;
    $filter = $compress ? ' /Filter /FlateDecode' : '';
    return "%PDF-1.4\n1 0 obj<< /Type /Catalog >>endobj\n4 0 obj<< /Length " . strlen($data) . $filter . " >>\nstream\n" . $data . "\nendstream\nendobj\ntrailer<< >>\n%%EOF\n";
}
/** A PDF whose strings are 2-byte glyph codes decoded by a ToUnicode CMap (what Word exports). */
function pdfCid(string $text): string
{
    $codes = []; $hex = '';
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $cp = mb_ord($ch, 'UTF-8');
        $code = $codes[$cp] ??= 0x20 + count($codes);
        $hex .= sprintf('%04X', $code);
    }
    $cm = "/CIDInit /ProcSet findresource begin\nbegincmap\n" . count($codes) . " beginbfchar\n";
    foreach ($codes as $cp => $code) { $cm .= sprintf("<%04X> <%04X>\n", $code, $cp); }
    $cm .= "endbfchar\nendcmap\n";
    $content = "BT\n/F1 11 Tf\n50 780 Td\n<$hex> Tj\nET\n";
    return "%PDF-1.5\n5 0 obj<< /Length " . strlen($cm) . " >>\nstream\n$cm\nendstream\nendobj\n6 0 obj<< /Length " . strlen($content) . " >>\nstream\n$content\nendstream\nendobj\n%%EOF\n";
}
function mkTmp(string $data, string $ext): string { $p = tempnam(sys_get_temp_dir(), 'rt') . '.' . $ext; file_put_contents($p, $data); return $p; }
function docx(string $body): string
{
    $p = tempnam(sys_get_temp_dir(), 'dx') . '.docx'; $z = new ZipArchive(); $z->open($p, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $z->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="x"><w:body>' . $body . '</w:body></w:document>'); $z->close(); return $p;
}
$CV = ['Amina Kamga - Senior Full-Stack Developer', 'Douala, Cameroon - 8 years of experience in web development', 'Skills: PHP, MySQL, JavaScript, Laravel, REST APIs, Docker, Git', 'Education: Master in Computer Science, University of Douala', 'Experience: led a team of five developers building an LMS used by 4000 students'];

// ---------------------------------------------------------------- text helpers
t('norm: accents, case and punctuation', fn() => eq(CareersScreening::norm('  Développeur  Full-Stack, C++/C# & Node.js !'), 'developpeur full stack c++ c# node.js'));
t('keywords: knock-out prefix, alternatives and commas', function () {
    $k = CareersScreening::parseKeywords("!php|laravel\nmysql, docker\n\n  ");
    eq(count($k), 3); eq($k[0]['knockout'], true); eq($k[0]['alts'], ['php', 'laravel']); eq($k[1]['knockout'], false); eq($k[2]['label'], 'docker');
});
t('keywords: derived from the posting when no list is given', function () {
    $d = CareersScreening::deriveKeywords(['title' => 'Backend PHP developer', 'requirements' => "Strong PHP and MySQL\nExperience with Docker and REST APIs", 'responsibilities' => 'Build APIs']);
    $labels = array_column($d, 'label');
    ok(in_array('php', $labels, true) && in_array('mysql', $labels, true) && in_array('docker', $labels, true), 'php, mysql and docker expected, got ' . implode(',', $labels));
    ok(!in_array('experience', $labels, true) && !in_array('strong', $labels, true), 'filler words must be dropped');
});

// ---------------------------------------------------------------- extraction
t('pdf: plain Tj text is read', function () use ($CV) { $r = ResumeText::extract(mkTmp(pdfWith($CV), 'pdf'), 'pdf'); ok($r['readable'], 'readable'); ok(str_contains($r['text'], 'Laravel') && str_contains($r['text'], 'University of Douala')); });
t('pdf: Flate-compressed streams are inflated', function () use ($CV) { $r = ResumeText::extract(mkTmp(pdfWith($CV, true), 'pdf'), 'pdf'); ok($r['readable'] && str_contains($r['text'], 'MySQL')); });
t('pdf: TJ arrays with kerning are joined', function () use ($CV) { $r = ResumeText::extract(mkTmp(pdfWith($CV, true, true), 'pdf'), 'pdf'); ok(str_contains($r['text'], 'Docker') && str_contains($r['text'], 'Master in Computer Science'), $r['text']); });
t('pdf: glyph codes are decoded through the ToUnicode map', function () use ($CV) { $r = ResumeText::extract(mkTmp(pdfCid(implode(' ', $CV)), 'pdf'), 'pdf'); ok($r['readable'], 'readable'); ok(str_contains($r['text'], 'Senior Full-Stack Developer'), substr($r['text'], 0, 120)); });
t('pdf: a file with no text layer is "not readable", not an error', function () { $r = ResumeText::extract(mkTmp("%PDF-1.4\n1 0 obj<< >>endobj\n%%EOF", 'pdf') , 'pdf'); eq($r['readable'], false); eq($r['text'], ''); });
t('pdf: random bytes behind a .pdf name give nothing', function () { $r = ResumeText::extract(mkTmp(random_bytes(3000), 'pdf'), 'pdf'); eq($r['readable'], false); });
t('docx: paragraphs are read, entities decoded', function () use ($CV) { $body = ''; foreach ($CV as $l) { $body .= '<w:p><w:r><w:t>' . htmlspecialchars($l) . '</w:t></w:r></w:p>'; } $r = ResumeText::extract(docx($body), 'docx'); ok($r['readable'] && str_contains($r['text'], "PHP, MySQL") && str_contains($r['text'], "\n")); });
t('doc: printable runs of a legacy file are kept', function () use ($CV) { $raw = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . random_bytes(200) . implode("\r", $CV) . random_bytes(100); $r = ResumeText::extract(mkTmp($raw, 'doc'), 'doc'); ok(str_contains($r['text'], 'Laravel'), 'text expected'); });
t('a missing file is not readable', fn() => eq(ResumeText::extract('/nonexistent/x.pdf', 'pdf')['readable'], false));

// ---------------------------------------------------------------- screening
$job = ['id' => 1, 'title' => 'Senior Full-Stack Developer', 'location' => 'Douala, Cameroon', 'work_mode' => 'hybrid', 'summary' => 'Build the platform', 'description' => 'x',
        'responsibilities' => "Ship features with PHP and JavaScript\nKeep MySQL queries fast", 'requirements' => "5+ years of web development\nPHP and MySQL\nREST APIs", 'nice_to_have' => 'Docker',
        'must_have' => "php\nmysql\nrest api", 'nice_have' => "docker\nlaravel\nkubernetes", 'min_years' => 5, 'min_education' => 'bachelor', 'screen_threshold' => 35, 'screen_enabled' => 1];
$good = ['full_name' => 'Amina Kamga', 'email' => 'amina@example.org', 'phone' => '+237 6 55 00 00 00', 'location' => 'Douala, Cameroon', 'years_experience' => 8, 'education_level' => 'master', 'skills' => 'PHP, MySQL, Docker',
         'current_title' => 'Senior Full-Stack Developer', 'cover_letter' => str_repeat('I build reliable web platforms and teach teams how to ship. ', 3), 'linkedin_url' => 'https://linkedin.com/in/amina'];
$cvText = implode("\n", $CV) . "\n" . str_repeat('Delivered projects with PHP, MySQL and REST APIs for universities. ', 6);

t('screening: a strong match scores high and stays in the inbox', function () use ($job, $good, $cvText) {
    $r = CareersScreening::evaluate($job, $good, $cvText, true);
    ok($r['score'] >= 80, 'score ' . $r['score']); eq($r['verdict'], 'strong'); eq($r['bucket'], 'inbox');
    ok(in_array('php', array_map('strtolower', $r['report']['matched']), true) && in_array('kubernetes', $r['report']['nice_missing'], true), 'matched / nice_missing');
});
t('screening: an unrelated résumé is held back as screened_out with its reasons', function () use ($job) {
    $app = ['full_name' => 'Paul Test', 'email' => 'paul@example.org', 'phone' => '', 'location' => 'Paris', 'years_experience' => 1, 'education_level' => 'highschool', 'skills' => 'Cooking, Baking', 'current_title' => 'Pastry cook', 'cover_letter' => ''];
    $r = CareersScreening::evaluate($job, $app, 'Pastry chef with three years in a busy bakery. Specialised in bread, croissants, cakes and customer service. Available for night shifts and weekends. Hardworking and punctual.', true);
    eq($r['verdict'], 'weak'); eq($r['bucket'], 'screened_out'); ok($r['score'] < 35, 'score ' . $r['score']);
    ok(count($r['report']['missing']) >= 3, 'missing keywords listed');
});
t('screening: a knock-out keyword holds the application back even when the rest is strong', function () use ($job, $good, $cvText) {
    $j = $job; $j['must_have'] = "!kubernetes\nphp\nmysql";
    $r = CareersScreening::evaluate($j, $good, $cvText, true);
    eq($r['verdict'], 'weak'); eq($r['bucket'], 'screened_out'); eq($r['report']['knockouts'], ['kubernetes']);
});
t('screening: an unreadable résumé is never screened out, a person must look', function () use ($job) {
    $r = CareersScreening::evaluate($job, ['email' => 'x@example.org', 'skills' => '', 'cover_letter' => '', 'years_experience' => 0], '', false);
    eq($r['verdict'], 'review'); eq($r['bucket'], 'inbox'); ok(in_array('unreadable', array_column($r['report']['flags'], 'code'), true));
});
t('screening: an open application is always "review"', function () use ($good, $cvText) { $r = CareersScreening::evaluate(null, $good, $cvText, true); eq($r['verdict'], 'review'); eq($r['bucket'], 'inbox'); });
t('screening: switched off on the posting, nothing is moved', function () use ($job) {
    $j = $job; $j['screen_enabled'] = 0;
    $r = CareersScreening::evaluate($j, ['email' => 'p@example.org', 'skills' => 'Baking', 'cover_letter' => '', 'years_experience' => 0], str_repeat('Pastry chef with a long career in bakeries and cafes. ', 6), true);
    eq($r['bucket'], 'inbox');
});
t('screening: the threshold of the posting decides what "weak" is', function () use ($job, $good) {
    $j = $job; $j['screen_threshold'] = 100; $good['years_experience'] = 2;
    $r = CareersScreening::evaluate($j, $good, 'Some PHP developer with a short profile. ' . str_repeat('Worked on web projects. ', 12), true);
    eq($r['bucket'], 'screened_out');
});
t('screening: experience is read from the résumé when the form leaves it empty', function () use ($job, $good) {
    $a = $good; $a['years_experience'] = null;
    $r = CareersScreening::evaluate($job, $a, 'Senior developer with 9 years of experience in PHP and MySQL building REST APIs. ' . str_repeat('Delivered many projects. ', 10), true);
    eq($r['report']['groups']['years']['value'], 9); eq($r['report']['groups']['years']['inferred'], true);
});
t('screening: a disposable address costs points and raises a flag', function () use ($job, $good, $cvText) {
    $a = $good; $a['email'] = 'x@mailinator.com';
    $b = CareersScreening::evaluate($job, $good, $cvText, true); $c = CareersScreening::evaluate($job, $a, $cvText, true);
    ok($c['score'] < $b['score'], 'penalty'); ok(in_array('disposable_email', array_column($c['report']['flags'], 'code'), true));
});
t('screening: keyword stuffing is flagged', function () use ($job, $good) {
    $r = CareersScreening::evaluate($job, $good, str_repeat('php mysql rest api ', 60), true);
    ok(in_array('keyword_stuffing', array_column($r['report']['flags'], 'code'), true));
});
t('screening: when the posting lists no keywords, they are derived and the report says so', function () use ($job, $good, $cvText) {
    $j = $job; $j['must_have'] = ''; $j['nice_have'] = '';
    $r = CareersScreening::evaluate($j, $good, $cvText, true); eq($r['report']['derived'], true); ok($r['score'] >= 60, 'score ' . $r['score']);
});
t('screening: accents and plurals do not hide a keyword', function () {
    $r = CareersScreening::evaluate(['title' => 'Développeur', 'must_have' => 'developpeur web', 'work_mode' => 'remote', 'screen_threshold' => 35], ['email' => 'a@b.org', 'skills' => '', 'cover_letter' => ''], 'Développeurs web expérimentés, passionnés par la qualité du code et des interfaces.' . str_repeat(' Mission réalisée avec soin.', 6), true);
    eq($r['report']['matched'], ['developpeur web']);
});
t('screening: the score is always between 0 and 100', function () use ($job) {
    foreach ([['', false], [str_repeat('x', 5000), true], [str_repeat('php ', 5000), true]] as [$txt, $rd]) { $r = CareersScreening::evaluate($job, ['email' => 'a@mailinator.com', 'cover_letter' => str_repeat('a', 50)], $txt, $rd); ok($r['score'] >= 0 && $r['score'] <= 100); }
});

echo "careers screening: $passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
