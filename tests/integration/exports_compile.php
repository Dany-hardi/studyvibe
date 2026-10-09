<?php
declare(strict_types=1);

/**
 * Every LaTeX document of the app is built from awkward sample data (accents, math, special characters, a picture, written and
 * true/false questions, a long table) and compiled with the real LaTeX engine. Needs pdflatex and the database from .env.
 *
 *   php tests/integration/exports_compile.php [output-folder]    (keeps the PDFs in that folder when given)
 */

require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/ExportDocs.php';

$pdo = Database::getInstance();
$out = $argv[1] ?? null;
if ($out !== null && !is_dir($out)) { mkdir($out, 0755, true); }
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

// a picture stored the way the app stores question pictures
$im = imagecreatetruecolor(1200, 700); imagefill($im, 0, 0, imagecolorallocate($im, 250, 248, 240));
imagesetthickness($im, 5); imageline($im, 100, 600, 1100, 600, 0); imageline($im, 100, 600, 100, 80, 0);
for ($x = 100; $x < 1100; $x += 8) { imagefilledellipse($im, $x, (int)(600 - (($x - 100) ** 2) / 1900), 7, 7, imagecolorallocate($im, 181, 72, 42)); }
$src = sys_get_temp_dir() . '/zz_export_img.png'; imagepng($im, $src);
$saved = MediaStore::saveImageFile($pdo, $src, 'live_question', 1600);
check('sample picture stored', $saved['ok'] === true);

$session = ['title' => "Contrôle d'Algèbre n°2 — 50 % & plus_tard #A", 'course_title' => 'Mathématiques générales (L1)', 'session_code' => 'abc123', 'start_time' => '2026-10-20 14:30:00', 'default_time_limit' => 60, 'is_async' => 0];
$questions = [
  ['question_text' => 'Que vaut $\\int_0^1 x^2\\,dx$ ? Rappel : x² ≤ x pour x ∈ [0 ; 1].', 'question_type' => 'mcq', 'option_a' => '1/3', 'option_b' => '1/2', 'option_c' => 'π ≈ 3,14', 'option_d' => "100 % de l'aire & 50_%", 'correct_option' => 'A', 'explanation' => 'Primitive $x^3/3$, donc 1/3. Attention aux pièges : \\ _ # { } ~ ^', 'time_limit' => null, 'image_path' => null],
  ['question_text' => "D'après la courbe ci-dessous, quelle est la nature de f ?", 'question_type' => 'mcq', 'option_a' => 'Affine', 'option_b' => 'Parabolique', 'option_c' => 'Constante', 'option_d' => 'Périodique', 'correct_option' => 'B', 'explanation' => '', 'time_limit' => 90, 'image_path' => $saved['file'] ?? null],
  ['question_text' => 'La somme des angles d\'un triangle vaut 180°.', 'question_type' => 'mcq', 'option_a' => 'Vrai', 'option_b' => 'Faux', 'option_c' => '', 'option_d' => '', 'correct_option' => 'A', 'explanation' => 'Géométrie euclidienne.', 'time_limit' => null, 'image_path' => null],
  ['question_text' => 'Résoudre $2x+3=8$ et donner x.', 'question_type' => 'written', 'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'correct_option' => '2.5|5/2', 'explanation' => 'x = 5/2', 'time_limit' => null, 'image_path' => null],
];
$regs = [];
foreach (['Éloïse Ndzié', 'Jean-Baptiste Tchoumi', "O'Neil & Fils", 'Zoé Mballa', 'Ali Bello', 'Marie Curie', 'Paul Biya Jr', 'Sans Copie'] as $i => $name) {
    $regs[] = ['name' => $name, 'email' => strtolower(str_replace([' ', '\'', '&', 'é', 'ï'], ['.', '', '', 'e', 'i'], $name)) . '_x@exemple.cm', 'score' => $i === 7 ? null : [100, 75, 75, 50, 50, 25, 0][min($i, 6)]];
}
for ($i = 0; $i < 40; $i++) { $regs[] = ['name' => "Étudiant Numéro $i", 'email' => "etudiant$i@exemple.cm", 'score' => ($i * 7) % 101]; }
usort($regs, fn($a, $b) => ($b['score'] ?? -1) <=> ($a['score'] ?? -1));

$answers = [];
foreach ($questions as $q) { $answers[] = LiveScoring::asSeen($q + ['question_id' => count($answers) + 1], $q['question_type'] === 'written' ? '2,5' : 'C', 7, true); }

$docs = [
  'paper_subject_fr' => ExportDocs::questionPaper($pdo, $session, $questions, 'subject', 'fr'),
  'paper_correction_fr' => ExportDocs::questionPaper($pdo, $session, $questions, 'correction', 'fr'),
  'paper_subject_en' => ExportDocs::questionPaper($pdo, $session, $questions, 'subject', 'en'),
  'grades_fr' => ExportDocs::gradesReport($session, $regs, 4, 'fr'),
  'grades_en' => ExportDocs::gradesReport($session, $regs, 4, 'en'),
  'ranking_fr' => ExportDocs::rankingReport($session, $regs, 4, 'fr'),
  'student_fr' => ExportDocs::studentReport($pdo, ['name' => 'Éloïse Ndzié', 'session_title' => $session['title'], 'course_title' => $session['course_title']], $answers, 'fr'),
  'sheet_landscape' => ExportDocs::sheetReport(['name' => 'Journal', 'title' => 'Journal d\'audit', 'meta' => [['Filtre', 'tout']], 'headers' => ['ID', 'Date', 'Action', 'Détails'], 'rows' => array_map(fn($i) => [$i, '09/10/2026 15:26', 'login_password_ok_2fa_pending', "Session #$i (Mode : subject) & 50 %"], range(1, 60))], 'fr'),
  'empty_grades' => ExportDocs::gradesReport($session, [], 0, 'fr'),
  'empty_paper' => ExportDocs::questionPaper($pdo, $session, [], 'subject', 'fr'),
];
foreach ($docs as $name => $d) {
    check("$name: traditional font, no sans-serif or helvetica", !str_contains($d['tex'], 'helvet') && !str_contains($d['tex'], 'sfdefault') && str_contains($d['tex'], '{lmodern}'));
    $pdf = LatexCompiler::compile($d['tex'], $d['assets']);
    check("$name compiles", $pdf !== null && str_starts_with((string)$pdf, '%PDF'));
    if ($pdf !== null && $out !== null) { file_put_contents("$out/$name.pdf", $pdf); }
    if ($pdf !== null && $name === 'paper_subject_fr') {
        check('picture is embedded in the paper', str_contains($pdf, '/Subtype/Image') || str_contains($pdf, '/Subtype /Image'));
        // Every font embedded in the paper is Latin Modern (the Computer Modern family)
        if (trim((string)shell_exec('command -v pdffonts')) !== '') {
            $tmp = tempnam(sys_get_temp_dir(), 'zzpdf'); file_put_contents($tmp, $pdf);
            $names = [];
            foreach (array_slice(explode("\n", (string)shell_exec('pdffonts ' . escapeshellarg($tmp) . ' 2>/dev/null')), 2) as $line) {
                if (preg_match('/^\S+?\+(\S+)/', $line, $m)) { $names[] = $m[1]; }
            }
            @unlink($tmp);
            check('paper uses Latin Modern (plus the AMS symbol font) only', $names !== [] && count(array_filter($names, fn($f) => !preg_match('/^(LM|MSAM|MSBM)/', $f))) === 0, implode(',', array_unique($names)));
        }
    }
}
MediaStore::delete($pdo, 'live_question', $saved['file'] ?? '');
@unlink($src);
echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
