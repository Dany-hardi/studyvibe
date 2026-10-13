<?php
declare(strict_types=1);

/**
 * Unit tests of the Markdown posting parser (lib/CareersMarkdown.php): header and sections in French and English, normalisation of values,
 * every error and warning, and the files shipped with the project (template and the three example postings). No database.
 *
 *   php tests/unit/careers_markdown.php
 */
require_once __DIR__ . '/../../lib/CareersMarkdown.php';

$passed = 0; $failed = [];
function t(string $name, callable $fn): void { global $passed, $failed; try { $fn(); $passed++; } catch (Throwable $e) { $failed[] = $name . ': ' . $e->getMessage(); } }
function eq($a, $b): void { if ($a !== $b) { throw new RuntimeException('expected ' . var_export($b, true) . ', got ' . var_export($a, true)); } }
function ok(bool $c, string $m = 'condition is false'): void { if (!$c) { throw new RuntimeException($m); } }
function codes(array $list): array { return array_column($list, 'code'); }

$good = <<<'MD'
---
title: Support Engineer
team: Customer Success
location: Yaoundé
work_mode: Hybride
type: CDI
level: Mid
openings: 2
closes: 2099-01-31
salary: "To be discussed: based on experience"
code: supp
status: open
screening: on
screening_threshold: 40
auto_decline_days: 14
min_years: 2
min_education: Licence
---
<!-- a comment
     over two lines -->
## Summary
Help teachers and students get unstuck fast, and turn what you learn into fixes for the product.

## About the role
First paragraph with **bold** and a [link](https://example.org).

Second paragraph.
- a bullet inside the description

## Missions
- Answer requests within **one working day**.
- Reproduce a bug and write it up
  so that an engineer can fix it.
- Keep the help centre up to date.
- Follow up after each fix.

## Requirements
- 2 years of support experience
- French and English

## Un plus
- SQL

## Screening
### Indispensables
- !support|assistance
- crm
### Atouts
- zendesk
MD;

t('a complete file parses without error', function () use ($good) { $r = CareersMarkdown::parse($good); eq($r['errors'], []); ok($r['ok']); });
t('values are normalised: team by label, mode and type by French words, level of education, code in capitals', function () use ($good) {
    $j = CareersMarkdown::parse($good)['job'];
    eq($j['department'], 'success'); eq($j['work_mode'], 'hybrid'); eq($j['employment_type'], 'full_time'); eq($j['min_education'], 'bachelor'); eq($j['code'], 'SUPP'); eq($j['openings'], 2);
    eq($j['closes_at'], '2099-01-31'); eq($j['salary_note'], 'To be discussed: based on experience'); eq($j['screen_threshold'], 40); eq($j['auto_decline_days'], 14); eq($j['min_years'], 2);
});
t('headings in French and English both map to the fields; HTML comments are ignored', function () use ($good) {
    $j = CareersMarkdown::parse($good)['job']; eq(count(explode("\n", $j['responsibilities'])), 4); eq($j['nice_to_have'], 'SQL'); ok(!str_contains($j['description'], 'comment'));
});
t('markdown marks are removed from text and links keep their text', function () use ($good) {
    $d = CareersMarkdown::parse($good)['job']['description']; ok(str_contains($d, 'First paragraph with bold and a link.'), $d); ok(!str_contains($d, '**') && !str_contains($d, 'http'));
});
t('a bullet in the description stays a bullet, paragraphs are separated by a blank line', function () use ($good) {
    $d = CareersMarkdown::parse($good)['job']['description']; ok(str_contains($d, "\n\nSecond paragraph.\n\n• a bullet inside the description"), json_encode($d));
});
t('a wrapped bullet (indented continuation) is one item', function () use ($good) {
    ok(in_array('Reproduce a bug and write it up so that an engineer can fix it.', explode("\n", CareersMarkdown::parse($good)['job']['responsibilities']), true));
});
t('screening keywords keep "!" and alternatives, and the two sub-sections do not mix with the posting sections', function () use ($good) {
    $j = CareersMarkdown::parse($good)['job']; eq($j['must_have'], "!support|assistance\ncrm"); eq($j['nice_have'], 'zendesk'); eq($j['nice_to_have'], 'SQL');
});
t('a file without a header is refused', fn() => eq(codes(CareersMarkdown::parse("# Title\n\n## Summary\nx")['errors']), ['no_header']));
t('a header that is not closed is refused', fn() => eq(codes(CareersMarkdown::parse("---\ntitle: A title\n## Summary\nx")['errors']), ['header_not_closed']));
t('empty file, binary text and an oversized file are refused', function () {
    eq(codes(CareersMarkdown::parse('')['errors']), ['no_header']); eq(codes(CareersMarkdown::parse("---\n\xFF\xFE\n---")['errors']), ['not_utf8']); eq(codes(CareersMarkdown::parse(str_repeat('a', CareersMarkdown::MAX_BYTES + 1))['errors']), ['too_big']);
});
t('missing required values are all reported at once', function () {
    $c = codes(CareersMarkdown::parse("---\nstatus: open\n---\n## Summary\nshort")['errors']);
    foreach (['title_required', 'team_required', 'location_required', 'work_mode_required', 'type_required', 'summary_short', 'description_required', 'responsibilities_required', 'requirements_required'] as $x) { ok(in_array($x, $c, true), "missing $x in " . implode(',', $c)); }
});
t('wrong values name the line and what is allowed', function () use ($good) {
    $r = CareersMarkdown::parse(str_replace(['team: Customer Success', 'work_mode: Hybride', 'closes: 2099-01-31', 'min_years: 2'], ['team: wizards', 'work_mode: floating', 'closes: 31 January', 'min_years: 99'], $good));
    $by = []; foreach ($r['errors'] as $e) { $by[$e['code']] = $e; }
    ok(isset($by['team_invalid']) && $by['team_invalid']['line'] === 3 && str_contains($by['team_invalid']['params']['allowed'], 'engineering'));
    ok(isset($by['work_mode_invalid']) && isset($by['closes_invalid']) && isset($by['number_range']) && $by['number_range']['params']['max'] === 40);
});
t('a deadline in the past blocks publishing but not a draft', function () use ($good) {
    $past = str_replace('closes: 2099-01-31', 'closes: 2020-01-01', $good);
    ok(in_array('closes_past', codes(CareersMarkdown::parse($past)['errors']), true));
    ok(CareersMarkdown::parse(str_replace('status: open', 'status: draft', $past))['ok']);
});
t('texts that are too long are errors, never silently cut', function () use ($good) {
    $long = str_replace("Help teachers and students get unstuck fast, and turn what you learn into fixes for the product.", str_repeat('word ', 90), $good);
    ok(in_array('summary_long', codes(CareersMarkdown::parse($long)['errors']), true));
    $item = str_replace('- Follow up after each fix.', '- ' . str_repeat('x', 301), $good);
    ok(in_array('item_long', codes(CareersMarkdown::parse($item)['errors']), true));
    $many = $good . "\n"; $items = ''; for ($i = 0; $i < 26; $i++) { $items .= "- item $i\n"; }
    ok(in_array('too_many_items', codes(CareersMarkdown::parse(str_replace('- Follow up after each fix.', rtrim($items), $good))['errors']), true));
});
t('an invalid code is refused', fn() => ok(in_array('code_invalid', codes(CareersMarkdown::parse(str_replace('code: supp', 'code: ab', $GLOBALS['good']))['errors']), true)));
t('warnings: unknown key, unknown section, no benefits, thin description, few items, screening off', function () use ($good) {
    $r = CareersMarkdown::parse(str_replace(['status: open'], ["status: open\ncolour: blue"], $good) . "\n\n## Random\ntext\n");
    $c = codes($r['warnings']); foreach (['unknown_key', 'unknown_section', 'no_benefits', 'description_thin', 'few_items'] as $x) { ok(in_array($x, $c, true), "missing $x in " . implode(',', $c)); }
    ok(in_array('screening_off', codes(CareersMarkdown::parse(str_replace('screening: on', 'screening: off', $good))['warnings']), true));
});
t('without must-have keywords a warning is raised and keywords are derived from the requirements', function () use ($good) {
    $noKw = preg_replace('/## Screening.*$/s', '', $good);
    $r = CareersMarkdown::parse($noKw); ok(in_array('no_must_have', codes($r['warnings']), true)); ok(count($r['preview']['derived']) >= 1);
});
t('French headings work too', function () {
    $fr = "---\ntitle: Chargé de support\nteam: Succès clients et support\nlocation: Douala\nwork_mode: sur site\ntype: stage\n---\n## Résumé\nAider les enseignants et les étudiants à avancer vite, et transformer ce que vous apprenez en améliorations du produit.\n## Le poste\nUn poste.\n## Vos missions\n- Répondre aux demandes\n## Profil recherché\n- Rigueur\n## Ce que nous offrons\n- Un cadre\n";
    $r = CareersMarkdown::parse($fr); eq($r['errors'], []); eq($r['job']['department'], 'success'); eq($r['job']['work_mode'], 'onsite'); eq($r['job']['employment_type'], 'internship');
});
t('Windows line endings and a BOM are accepted', fn() => eq(CareersMarkdown::parse("\xEF\xBB\xBF" . str_replace("\n", "\r\n", $GLOBALS['good']))['errors'], []));
t('the shipped templates (English and French) are valid files', function () {
    foreach (['en', 'fr'] as $l) { $r = CareersMarkdown::parse(CareersMarkdown::template($l)); eq($r['errors'], [], $l); ok(strlen(CareersMarkdown::template($l)) > 500); }
});
t('the three example postings are valid, detailed and on three different teams with screening on', function () {
    $teams = [];
    foreach (glob(__DIR__ . '/../../docs/careers/postings/*.md') as $f) {
        $r = CareersMarkdown::parse((string)file_get_contents($f)); eq($r['errors'], [], basename($f)); eq($r['warnings'], [], basename($f));
        $j = $r['job']; eq($j['screen_enabled'], 1, basename($f)); ok(mb_strlen($j['description']) > 1200, 'description'); ok(count(explode("\n", $j['responsibilities'])) >= 8 && count(explode("\n", $j['requirements'])) >= 7, 'lists');
        ok($j['must_have'] !== '' && $j['min_years'] > 0 && $j['min_education'] !== '', 'criteria'); $teams[$j['department']] = 1;
    }
    eq(count($teams), 3); ok(isset($teams['engineering'], $teams['sales'], $teams['education']));
});

echo "careers markdown: $passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
