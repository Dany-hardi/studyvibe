<?php
declare(strict_types=1);
require_once __DIR__ . '/../../QuestionImporter.php';
$pass = 0; $fail = 0;
$check = function (string $n, bool $ok) use (&$pass, &$fail): void { echo ($ok ? 'ok   ' : 'FAIL ') . $n . "\n"; $ok ? $pass++ : $fail++; };

$head = '"question","type","option_a","option_b","option_c","option_d","correct","explanation","image","time_limit","code_lang","reference","marking_notes"' . "\n";
$csv = $head
  . '"Write add","code","","","","","","","a.png","600","Python","def add(a, b): return a + b","no builtins"' . "\n"
  . '"Explain LSP","open","","","","","","","","240","","Subtypes must be substitutable.","ESSENTIAL: contract preserved"' . "\n"
  . '"Pick","mcq","a","b","c","d","B","why","","90","","",""' . "\n";
$r = QuestionImporter::parseCsv($csv, true);
$q = $r['questions'];
$check('three questions, no error', count($q) === 3 && !$r['errors']);
$check('a code question keeps its language (normalised), reference and notes', $q[0]['question_type'] === 'code' && $q[0]['code_lang'] === 'python' && str_contains($q[0]['code_reference'], 'def add') && $q[0]['code_notes'] === 'no builtins');
$check('the stored right answer of a code question is the marker', $q[0]['correct_option'] === 'CODE_OK' && $q[0]['option_a'] === '');
$check('a written-answer question has no language', $q[1]['question_type'] === 'open' && $q[1]['code_lang'] === null && $q[1]['time_limit'] === 240);
$check('multiple choice is untouched', $q[2]['question_type'] === 'mcq' && $q[2]['correct_option'] === 'B');
$check('an unknown language becomes "other"', QuestionImporter::parseCsv($head . '"x","code","","","","","","","","","Brainfuck","ref",""' . "\n", true)['questions'][0]['code_lang'] === 'other');

$noRef = QuestionImporter::parseCsv($head . '"Write add","code","","","","","","","","","java","",""' . "\n", true);
$check('a code question without reference nor notes is refused', !$noRef['questions'] && count($noRef['errors']) === 1);
$notLive = QuestionImporter::parseCsv($csv, false);
$check('outside a live evaluation these types are refused', count($notLive['questions']) === 1 && count($notLive['errors']) === 2);
$long = QuestionImporter::parseCsv($head . '"x","open","","","","","","","","","","' . str_repeat('a', 20000) . '",""' . "\n", true);
$check('an over-long reference is cut', mb_strlen($long['questions'][0]['code_reference']) === LiveCodeReview::MAX_REFERENCE);
$old = QuestionImporter::parseCsv("question,option_a,option_b,option_c,option_d,correct,explanation\n\"Q\",\"1\",\"2\",\"3\",\"4\",\"C\",\"e\"\n", true);
$check('an older file without the new columns still imports', count($old['questions']) === 1 && $old['questions'][0]['question_type'] === 'mcq');

echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
