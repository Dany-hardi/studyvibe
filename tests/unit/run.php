<?php
declare(strict_types=1);

/**
 * Unit tests for the pure rules of the platform (no database, no web server).
 *
 *   php tests/unit/run.php
 *
 * Exit code 0 when everything passes, 1 otherwise. Add a test by calling t('what it proves', fn() => ...).
 */

require_once __DIR__ . '/../../lib/LiveScoring.php';
require_once __DIR__ . '/../../lib/PasswordPolicy.php';
require_once __DIR__ . '/../../lib/Matricule.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Phone.php';
require_once __DIR__ . '/../../lib/Totp.php';
require_once __DIR__ . '/../../lib/SmsGateway.php';
require_once __DIR__ . '/../../lib/LiveSmsNotifier.php';
require_once __DIR__ . '/../../lib/ExportTheme.php';
require_once __DIR__ . '/../../Mailer.php';
require_once __DIR__ . '/../../QuestionImporter.php';

$passed = 0;
$failed = [];

function t(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
    } catch (Throwable $e) {
        $failed[] = $name . ': ' . $e->getMessage();
    }
}

function eq(mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException('expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

// ---- LiveScoring: multiple choice
t('mcq: right letter is correct', fn() => eq(LiveScoring::isCorrect('mcq', 'B', 'B'), true));
t('mcq: wrong letter is wrong', fn() => eq(LiveScoring::isCorrect('mcq', 'A', 'B'), false));
t('mcq: no answer is wrong', fn() => eq(LiveScoring::isCorrect('mcq', '', 'B'), false));

// ---- LiveScoring: written answers
t('written: exact text, case and spaces ignored', fn() => eq(LiveScoring::isCorrect('written', ' X ^ 2 ', 'x^2'), true));
t('written: comma equals dot', fn() => eq(LiveScoring::isCorrect('written', '2,5', '2.5'), true));
t('written: 2.50 equals 2.5', fn() => eq(LiveScoring::isCorrect('written', '2.50', '2.5'), true));
t('written: alternatives with |', fn() => eq(LiveScoring::isCorrect('written', 'x²', 'x^2|x²'), true));
t('written: tolerance inside', fn() => eq(LiveScoring::isCorrect('written', '2.58', '2.5~0.1'), true));
t('written: tolerance outside', fn() => eq(LiveScoring::isCorrect('written', '2.7', '2.5~0.1'), false));
t('written: tolerance with comma', fn() => eq(LiveScoring::isCorrect('written', '2,45', '2,5~0,1'), true));
t('written: empty is wrong', fn() => eq(LiveScoring::isCorrect('written', '', '2.5'), false));
t('written: wrong text', fn() => eq(LiveScoring::isCorrect('written', 'x^3', 'x^2|x²'), false));

// ---- LiveScoring: per-student option order
t('option order: is a permutation of A-D', function () {
    $o = LiveScoring::optionOrder(17, 204);
    sort($o);
    eq($o, ['A', 'B', 'C', 'D']);
});
t('option order: same student and question always the same', fn() => eq(LiveScoring::optionOrder(9, 33), LiveScoring::optionOrder(9, 33)));
t('option order: students do not all get the same order', function () {
    $seen = [];
    for ($rid = 1; $rid <= 60; $rid++) {
        $seen[implode('', LiveScoring::optionOrder($rid, 500))] = true;
    }
    if (count($seen) < 10) {
        throw new RuntimeException('only ' . count($seen) . ' different orders over 60 students');
    }
});

// ---- LiveScoring::asSeen (what the student reads afterwards is what they saw)
t('as seen: with no shuffle nothing moves', function () {
    $q = ['question_id' => 5, 'option_a' => 'x', 'option_b' => 'y', 'option_c' => 'z', 'option_d' => 'w', 'correct_option' => 'B'];
    $r = LiveScoring::asSeen($q, 'C', 9, false);
    eq([$r['option_a'], $r['option_b'], $r['correct_option'], $r['selected_option']], ['x', 'y', 'B', 'C']);
});
t('as seen: letters and texts stay together for 200 students', function () {
    $q = ['question_id' => 77, 'option_a' => 'three', 'option_b' => 'four', 'option_c' => 'five', 'option_d' => 'six', 'correct_option' => 'B', 'question_type' => 'mcq'];
    $orig = ['A' => 'three', 'B' => 'four', 'C' => 'five', 'D' => 'six'];
    for ($rid = 1; $rid <= 200; $rid++) {
        foreach (['A', 'B', 'C', 'D', ''] as $picked) {
            $r = LiveScoring::asSeen($q, $picked, $rid, true);
            $shown = ['A' => $r['option_a'], 'B' => $r['option_b'], 'C' => $r['option_c'], 'D' => $r['option_d']];
            eq($shown[$r['correct_option']], 'four');                                   // the marked correct answer is the right text
            eq($picked === '' ? '' : $shown[$r['selected_option']], $picked === '' ? '' : $orig[$picked]);   // the marked pick is what they chose
            eq(LiveScoring::isCorrect('mcq', $r['selected_option'], $r['correct_option']), $picked === 'B');   // same verdict as before
            $order = LiveScoring::optionOrder($rid, 77);
            eq($r['option_a'], $orig[$order[0]]);                                        // first button = first of their order
        }
    }
});
t('as seen: written questions are untouched', function () {
    $q = ['question_id' => 5, 'question_type' => 'written', 'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'correct_option' => '2.5'];
    $r = LiveScoring::asSeen($q, '2,5', 9, true);
    eq([$r['correct_option'], $r['selected_option']], ['2.5', '2,5']);
});

// ---- true/false and written answers
t('shuffle: only a full A to D question is shuffled', function () {
    eq(LiveScoring::canShuffle(['question_type' => 'mcq', 'option_a' => 'a', 'option_b' => 'b', 'option_c' => 'c', 'option_d' => 'd']), true);
    eq(LiveScoring::canShuffle(['question_type' => 'mcq', 'option_a' => 'Vrai', 'option_b' => 'Faux', 'option_c' => '', 'option_d' => '']), false);
    eq(LiveScoring::canShuffle(['question_type' => 'written', 'option_a' => 'a', 'option_b' => 'b', 'option_c' => 'c', 'option_d' => 'd']), false);
});
t('as seen: a true/false question keeps Vrai before Faux', function () {
    $q = ['question_id' => 3, 'question_type' => 'mcq', 'option_a' => 'Vrai', 'option_b' => 'Faux', 'option_c' => '', 'option_d' => '', 'correct_option' => 'A'];
    for ($rid = 1; $rid <= 50; $rid++) {
        $r = LiveScoring::asSeen($q, 'B', $rid, true);
        eq([$r['option_a'], $r['option_b'], $r['correct_option'], $r['selected_option']], ['Vrai', 'Faux', 'A', 'B']);
    }
});
t('written answers are shown in words', function () {
    eq(LiveScoring::displayAnswer('2.5|5/2'), '2.5 ou 5/2');
    eq(LiveScoring::displayAnswer('2.5~0.1', 'en'), '2.5 (± 0.1)');
    eq(LiveScoring::displayAnswer('x^2|x²|x*x', 'en'), 'x^2 or x² or x*x');
});

// ---- ExportTheme (text made safe for LaTeX)
t('latex: special characters are escaped', fn() => eq(ExportTheme::esc('50 % & plus_tard #A {x}'), '50 \\% \\& plus\\_tard \\#A \\{x\\}'));
t('latex: a colon inside a time or address gets no French space', fn() => eq(ExportTheme::esc('15:26 https://x.cm'), '15\\string:26 https\\string://x.cm'));
t('latex: math written by a teacher is kept', fn() => eq(ExportTheme::tex('Que vaut $x^2 + 1$ ?'), 'Que vaut $x^2 + 1$ ?'));
t('latex: unicode symbols outside math become math', fn() => eq(ExportTheme::tex('x² ≤ π'), 'x$^2$ $\\leq$ $\\pi$'));
t('latex: a stray dollar or percent cannot break the build', function () {
    $o = ExportTheme::tex('Prix : 5 $ et 10 %');
    eq(str_contains($o, '\\$'), true);
    eq(str_contains($o, '\\%'), true);
});
t('latex: paragraphs from <br> but one line when inline', function () {
    eq(str_contains(ExportTheme::tex("a<br>b"), "\n\n"), true);
    eq(str_contains(ExportTheme::tex("a<br>b", true), "\n"), false);
});
t('latex: the document uses the traditional typeface and no sans-serif', function () {
    $d = ExportTheme::document('x', ['lang' => 'fr']);
    eq(str_contains($d, '{lmodern}'), true);
    eq(str_contains($d, 'sfdefault') || str_contains($d, 'helvet') || str_contains($d, 'fontspec'), false);
});
t('latex: file names are plain', fn() => eq(ExportTheme::slug('Contrôle d\'Algèbre n°2 — 50 %'), 'controle_d_algebre_n_2_50'));

// ---- Email design and message structure
t('email: every message carries the inline logo, rounded card, web fonts with fallbacks, no emoji', function () {
    $h = EmailTheme::layout(EmailTheme::title('Bonjour') . EmailTheme::button('https://x.cm', 'Go'));
    eq(str_contains($h, 'cid:svlogo@studyvibe'), true);
    eq(str_contains($h, 'border-radius:24px'), true);
    eq(str_contains($h, "'Fraunces', Georgia"), true);
    eq(str_contains($h, "'Hanken Grotesk'"), true);
    eq(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $h), 0);
});
t('email: text is escaped in titles, badges and facts', function () {
    $h = EmailTheme::title('<script>alert(1)</script>') . EmailTheme::badge('<b>x</b>') . EmailTheme::facts([['<i>', '&']]);
    eq(str_contains($h, '<script>'), false);
    eq(str_contains($h, '&lt;script&gt;'), true);
});
t('email: plain-text version has links written out and no logo link', function () {
    $txt = EmailTheme::toText(EmailTheme::layout('<p>Salut <a href="https://x.cm/a">ici</a></p>', ['preheader' => 'p']));
    eq(str_contains($txt, 'ici (https://x.cm/a)'), true);
    eq(str_starts_with($txt, 'http'), false);
});
t('email: MIME has HTML, plain text and the logo as an inline image', function () {
    $m = Mailer::buildMime('a@b.cm', 'Sujet é', EmailTheme::layout('<p>é</p>'), 'noreply@x.cm', 'StudyVibe');
    eq(str_contains($m['headers'], 'multipart/related'), true);
    eq(str_contains($m['body'], 'Content-Type: text/plain'), true);
    eq(str_contains($m['body'], 'Content-Type: text/html'), true);
    eq(str_contains($m['body'], 'Content-ID: <svlogo@studyvibe>'), true);
    eq(str_starts_with($m['subject'], '=?UTF-8?B?'), true);
    foreach (explode("\r\n", $m['body']) as $line) {
        if (strlen($line) > 998) { throw new RuntimeException('line too long for SMTP'); }
    }
});
t('email: addresses and subjects with line breaks are refused (header injection)', function () {
    Mailer::$capture = fn() => null;
    eq(Mailer::send("a@b.cm\r\nBcc: evil@x.cm", 'Sujet', '<p>x</p>'), false);
    eq(Mailer::send('a@b.cm', "Sujet\r\nBcc: evil@x.cm", '<p>x</p>'), false);
    eq(Mailer::send('a@b.cm', 'Sujet', '<p>x</p>'), true);
    Mailer::$capture = null;
});

// ---- Question import (CSV)
t('csv: a quoted cell may hold line breaks (a code snippet in a question)', function () {
    $r = QuestionImporter::parseCsv("question,option_a,option_b,option_c,option_d,correct\n\"Que fait ce code ?\nx = 1\nprint(x)\",1,2,3,4,A\n");
    eq(count($r['questions']), 1);
    eq($r['questions'][0]['question_text'], "Que fait ce code ?\nx = 1\nprint(x)");
});
t('csv: image and time_limit columns are read, a bad time is reported and ignored', function () {
    $r = QuestionImporter::parseCsv("question,option_a,option_b,option_c,option_d,correct,image,time_limit\nQ1,a,b,c,d,B,fig.png,45\nQ2,a,b,c,d,A,,abc\n");
    eq([$r['questions'][0]['image'], $r['questions'][0]['time_limit'], $r['questions'][1]['time_limit']], ['fig.png', 45, null]);
    eq(count($r['errors']), 1);
});
t('csv: a true/false question (only A and B) is accepted for live sessions only', function () {
    $csv = "question,option_a,option_b,option_c,option_d,correct\nLa Terre est ronde,Vrai,Faux,,,A\n";
    eq(count(QuestionImporter::parseCsv($csv, true)['questions']), 1);
    eq(count(QuestionImporter::parseCsv($csv, false)['questions']), 0);
    eq(count(QuestionImporter::parseCsv("question,option_a,option_b,option_c,option_d,correct\nQ,Vrai,Faux,,,C\n", true)['questions']), 0);
});
t('csv: semicolon files and a BOM still work', function () {
    $r = QuestionImporter::parseCsv("\xEF\xBB\xBFquestion;option_a;option_b;option_c;option_d;correct\nQ;a;b;c;d;D\n");
    eq($r['questions'][0]['correct_option'], 'D');
});

// ---- no email to fake addresses
t('mail: reserved test domains never receive email', function () {
    foreach (['a@test.local', 'a@example.com', 'a@mail.example.org', 'a@x.invalid', 'a@localhost', 'a@school.test', 'a@zz.local'] as $e) {
        eq(Mailer::isTestAddress($e), true);
    }
    foreach (['danyhardi06@gmail.com', 'danyhardi06+zz@gmail.com', 'prof@univ-yaounde1.cm', 'a@testing.com', 'a@local.com'] as $e) {
        eq(Mailer::isTestAddress($e), false);
    }
});
t('mail: send() refuses a test address without sending (capture off)', function () {
    Mailer::$capture = null;
    eq(Mailer::send('someone@test.local', 'Sujet', '<p>x</p>'), false);
    eq(str_contains((string)Mailer::getLastError(), 'test'), true);
});

// ---- PasswordPolicy
t('password: 7 characters rejected', fn() => eq(PasswordPolicy::check('abc1234') !== null, true));
t('password: 8 characters accepted', fn() => eq(PasswordPolicy::check('lune-8Fox'), null));
t('password: common password rejected', fn() => eq(PasswordPolicy::check('password123') !== null, true));
t('password: repeated character rejected', fn() => eq(PasswordPolicy::check('aaaaaaaaaa') !== null, true));
t('password: email as password rejected', fn() => eq(PasswordPolicy::check('me@school.cm', 'me@school.cm') !== null, true));

// ---- Matricule
t('matricule: normalises spaces and case', fn() => eq(Matricule::normalize(' 24 g 123 '), '24G123'));
t('matricule: valid 6 characters', fn() => eq(Matricule::check('24G123', 2026), null));
t('matricule: valid 7 characters', fn() => eq(Matricule::check('24G1234', 2026), null));
t('matricule: empty', fn() => eq(Matricule::check('', 2026), 'empty'));
t('matricule: bad format', fn() => eq(Matricule::check('G24123', 2026), 'format'));
t('matricule: year in the future', fn() => eq(Matricule::check('30G123', 2026), 'future'));

// ---- Phone
t('phone: local Cameroon number gets +237', fn() => eq(Phone::normalize('6 12 34 56 78'), '+237612345678'));
t('phone: leading zero is dropped', fn() => eq(Phone::normalize('0612345678'), '+237612345678'));
t('phone: international with plus', fn() => eq(Phone::normalize('+237 612 345 678'), '+237612345678'));
t('phone: 00 prefix', fn() => eq(Phone::normalize('00237-6-12-34-56-78'), '+237612345678'));
t('phone: country code typed without plus', fn() => eq(Phone::normalize('237612345678'), '+237612345678'));
t('phone: another country', fn() => eq(Phone::normalize('+33 6 12 34 56 78'), '+33612345678'));
t('phone: letters refused', fn() => eq(Phone::normalize('abc123'), null));
t('phone: too short refused', fn() => eq(Phone::normalize('1234'), null));
t('phone: empty refused', fn() => eq(Phone::normalize('  '), null));
t('phone: injection attempt refused', fn() => eq(Phone::normalize("612345678'; DROP TABLE users;--"), null));
t('phone: mask hides the middle', fn() => eq(Phone::mask('+237612345678'), '+237 6•• ••• 678'));

// ---- Totp (RFC 6238 test vectors, secret "12345678901234567890")
t('totp: RFC 6238 vector at T=59', fn() => eq(Totp::code(Totp::base32Encode('12345678901234567890'), intdiv(59, 30)), '287082'));
t('totp: RFC 6238 vector at T=1111111109', fn() => eq(Totp::code(Totp::base32Encode('12345678901234567890'), intdiv(1111111109, 30)), '081804'));
t('totp: base32 round trip', fn() => eq(Totp::base32Decode(Totp::base32Encode("\x00\xffhello")), "\x00\xffhello"));
t('totp: right code accepted', fn() => eq(Totp::verify(Totp::base32Encode('12345678901234567890'), '287082', 0, 59), 1));
t('totp: code one step late still accepted (clock drift)', fn() => eq(Totp::verify(Totp::base32Encode('12345678901234567890'), '287082', 0, 59 + 30), 1));
t('totp: code two steps late refused', fn() => eq(Totp::verify(Totp::base32Encode('12345678901234567890'), '287082', 0, 59 + 90), null));
t('totp: same step cannot be used twice', fn() => eq(Totp::verify(Totp::base32Encode('12345678901234567890'), '287082', 1, 59), null));
t('totp: wrong or malformed code refused', function () {
    $s = Totp::base32Encode('12345678901234567890');
    eq(Totp::verify($s, '000000', 0, 59), null);
    eq(Totp::verify($s, '12345', 0, 59), null);
    eq(Totp::verify($s, 'abcdef', 0, 59), null);
});
t('totp: secret survives encryption and is not readable', function () {
    $secret = Totp::newSecret();
    $stored = Totp::encrypt($secret);
    eq(strpos($stored, $secret), false);
    eq(Totp::decrypt($stored), $secret);
    eq(Totp::decrypt('garbage'), null);
});
t('totp: uri carries issuer and secret', function () {
    $u = Totp::uri('ABCDEFGH', 'me@school.cm');
    eq(str_starts_with($u, 'otpauth://totp/StudyVibe%3Ame%40school.cm?secret=ABCDEFGH&issuer=StudyVibe'), true);
});

// ---- SMS text
t('sms: accents become plain letters (keeps the message in 160-character parts)', fn() => eq(SmsGateway::plain('Évaluation à 14h — « test » d’été'), 'Evaluation a 14h - " test " d\'ete'));
t('sms: live announcement carries name, time, instructions and link', function () {
    $m = LiveSmsNotifier::message(['title' => 'QCM', 'course_title' => 'Maths', 'start_time' => '2026-10-20 14:30:00', 'is_async' => 0, 'n_questions' => 20], 'Marie Curie', 'https://x.cm/live-session.php?code=abc', 'fr');
    foreach (['Marie', '20/10/2026', '14:30', 'QCM', 'Maths', '20 questions', 'https://x.cm/live-session.php?code=abc', 'connectez-vous'] as $needle) {
        eq(str_contains($m, $needle), true);
    }
});
t('sms: async announcement mentions the deadline', function () {
    $m = LiveSmsNotifier::message(['title' => 'Devoir', 'course_title' => 'Maths', 'start_time' => '2026-10-20 14:30:00', 'is_async' => 1, 'async_deadline' => '2026-10-25 23:00:00', 'n_questions' => 5], 'John', 'https://x.cm/l', 'en');
    eq(str_contains($m, '25/10/2026 23:00'), true);
});

echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) {
    echo "  FAIL  $f\n";
}
exit($failed ? 1 : 0);
