<?php
declare(strict_types=1);

/**
 * Session analysis for a teacher: how the room scored, how each question behaved, and who left the exam tab.
 *
 *   /teacher/live-analysis.php?session_id=12            the page
 *   /teacher/live-analysis.php?session_id=12&format=csv  the per-question table as CSV
 *
 * Read-only. Correctness always comes from LiveScoring, so this page agrees with the scores students received.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/LiveScoring.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    http_response_code(403);
    exit('Accès non autorisé.');
}

$sessionId = (int)($_GET['session_id'] ?? 0);
$pdo = Database::getInstance();
$stmt = $pdo->prepare("
    SELECT s.*, c.title AS course_title FROM live_eval_sessions s
    JOIN courses c ON c.id = s.course_id
    WHERE s.id = :sid AND s.teacher_id = :tid
");
$stmt->execute(['sid' => $sessionId, 'tid' => (int)$_SESSION['user_id']]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(404);
    exit('Séance introuvable.');
}

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$T = [
 'fr' => ['title' => 'Analyse de la séance', 'back' => 'Retour au tableau de bord', 'participants' => 'Participants', 'graded' => 'Notés', 'mean' => 'Moyenne', 'median' => 'Médiane', 'range' => 'Min / Max',
          'dist' => 'Répartition des notes', 'items' => 'Analyse des questions', 'q' => 'Question', 'type' => 'Type', 'ok' => 'Réussite', 'answered' => 'Réponses', 'disc' => 'Discrimination', 'wrong' => 'Mauvaise réponse la plus choisie', 'flag' => 'Remarque',
          'easy' => 'Très facile', 'hard' => 'Très difficile', 'ambig' => 'À vérifier : une mauvaise réponse domine', 'neg' => 'À vérifier : les meilleurs échouent plus que les autres', 'fine' => 'RAS',
          'integ' => 'Intégrité', 'integ_off' => 'Le suivi des sorties d\'onglet n\'était pas activé pour cette séance.', 'integ_none' => 'Aucune sortie d\'onglet enregistrée.', 'student' => 'Étudiant', 'exits' => 'Sorties', 'last' => 'Dernière sortie', 'score' => 'Note',
          'shuffle' => 'Propositions mélangées', 'yes' => 'oui', 'no' => 'non', 'csv' => 'Télécharger le tableau (CSV)', 'noq' => 'Cette séance n\'a pas encore de questions.', 'nodata' => 'Pas encore de notes.',
          'help' => 'Discrimination : écart de réussite entre les 27 % meilleurs et les 27 % derniers. Au-dessus de 0,3, la question sépare bien les niveaux. Négative : à relire.', 'written' => 'écrite', 'mcq' => 'QCM'],
 'en' => ['title' => 'Session analysis', 'back' => 'Back to the dashboard', 'participants' => 'Participants', 'graded' => 'Graded', 'mean' => 'Mean', 'median' => 'Median', 'range' => 'Min / Max',
          'dist' => 'Score distribution', 'items' => 'Question analysis', 'q' => 'Question', 'type' => 'Type', 'ok' => 'Correct', 'answered' => 'Answers', 'disc' => 'Discrimination', 'wrong' => 'Most chosen wrong answer', 'flag' => 'Note',
          'easy' => 'Very easy', 'hard' => 'Very hard', 'ambig' => 'Check: one wrong answer dominates', 'neg' => 'Check: top students miss it more than the rest', 'fine' => 'OK',
          'integ' => 'Integrity', 'integ_off' => 'Tab-exit tracking was not turned on for this session.', 'integ_none' => 'No tab exit was recorded.', 'student' => 'Student', 'exits' => 'Exits', 'last' => 'Last exit', 'score' => 'Score',
          'shuffle' => 'Options shuffled', 'yes' => 'yes', 'no' => 'no', 'csv' => 'Download the table (CSV)', 'noq' => 'This session has no questions yet.', 'nodata' => 'No scores yet.',
          'help' => 'Discrimination: the gap in success rate between the top 27% and the bottom 27% of students. Above 0.3 the question separates levels well. Negative: review it.', 'written' => 'written', 'mcq' => 'MCQ'],
][$lang];
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$qs = $pdo->prepare("SELECT * FROM live_eval_questions WHERE session_id = :sid ORDER BY sort_order ASC, id ASC");
$qs->execute(['sid' => $sessionId]);
$questions = $qs->fetchAll(PDO::FETCH_ASSOC);

$rs = $pdo->prepare("SELECT id, name, score, focus_losses, last_focus_loss_at FROM live_eval_registrations WHERE session_id = :sid");
$rs->execute(['sid' => $sessionId]);
$regs = $rs->fetchAll(PDO::FETCH_ASSOC);

$as = $pdo->prepare("
    SELECT a.registration_id, a.question_id, a.selected_option FROM live_eval_answers a
    JOIN live_eval_registrations r ON r.id = a.registration_id WHERE r.session_id = :sid
");
$as->execute(['sid' => $sessionId]);
$answers = [];   // [question_id][registration_id] = selected
foreach ($as->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $answers[(int)$a['question_id']][(int)$a['registration_id']] = (string)$a['selected_option'];
}

// Registrations with a score, ranked, for the top / bottom 27 % groups
$scored = array_values(array_filter($regs, fn($r) => $r['score'] !== null));
usort($scored, fn($a, $b) => (float)$b['score'] <=> (float)$a['score']);
$groupSize = max(1, (int)round(count($scored) * 0.27));
$top = array_column(array_slice($scored, 0, $groupSize), 'id');
$bottom = array_column(array_slice($scored, -$groupSize), 'id');

$rows = [];
foreach ($questions as $i => $q) {
    $qid = (int)$q['id'];
    $type = (string)($q['question_type'] ?? 'mcq');
    $given = $answers[$qid] ?? [];
    $right = 0;
    $wrongCount = [];
    $rightBy = [];
    foreach ($given as $rid => $sel) {
        $ok = LiveScoring::isCorrect($type, $sel, (string)$q['correct_option']);
        $rightBy[$rid] = $ok;
        if ($ok) {
            $right++;
        } elseif ($type === 'mcq' && $sel !== '') {
            $wrongCount[$sel] = ($wrongCount[$sel] ?? 0) + 1;
        }
    }
    $n = count($given);
    $rate = $n > 0 ? $right / $n : null;
    $rateOf = function (array $ids) use ($rightBy): ?float {
        $seen = 0; $ok = 0;
        foreach ($ids as $rid) {
            if (array_key_exists($rid, $rightBy)) { $seen++; $ok += $rightBy[$rid] ? 1 : 0; }
        }
        return $seen > 0 ? $ok / $seen : null;
    };
    $t = $rateOf($top); $b = $rateOf($bottom);
    $disc = ($t !== null && $b !== null && count($scored) >= 6) ? $t - $b : null;
    arsort($wrongCount);
    $topWrong = $wrongCount ? array_key_first($wrongCount) : null;

    $flag = $T['fine'];
    if ($rate !== null && $n >= 5) {
        if ($disc !== null && $disc < 0) $flag = $T['neg'];
        elseif ($topWrong !== null && $wrongCount[$topWrong] > $right) $flag = $T['ambig'];
        elseif ($rate >= 0.95) $flag = $T['easy'];
        elseif ($rate <= 0.2) $flag = $T['hard'];
    }
    $rows[] = ['n' => $i + 1, 'text' => (string)$q['question_text'], 'type' => $type, 'rate' => $rate, 'answered' => $n,
               'disc' => $disc, 'wrong' => $topWrong !== null ? $topWrong . ' (' . $wrongCount[$topWrong] . ')' : '', 'flag' => $flag];
}

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analyse-seance-' . $sessionId . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['#', $T['q'], $T['type'], $T['ok'] . ' %', $T['answered'], $T['disc'], $T['wrong'], $T['flag']], ';');
    foreach ($rows as $r) {
        fputcsv($out, [$r['n'], $r['text'], $r['type'], $r['rate'] !== null ? round($r['rate'] * 100, 1) : '', $r['answered'],
                       $r['disc'] !== null ? round($r['disc'], 2) : '', $r['wrong'], $r['flag']], ';');
    }
    fclose($out);
    exit;
}

// Summary figures
$scores = array_map(fn($r) => (float)$r['score'], $scored);
sort($scores);
$cnt = count($scores);
$mean = $cnt ? array_sum($scores) / $cnt : null;
$median = $cnt ? (($cnt % 2) ? $scores[intdiv($cnt, 2)] : ($scores[$cnt / 2 - 1] + $scores[$cnt / 2]) / 2) : null;
$buckets = array_fill(0, 10, 0);
foreach ($scores as $s) { $buckets[min(9, (int)floor($s / 10))]++; }
$maxBucket = max(1, max($buckets));
$watch = !empty($session['integrity_watch']);
$exits = array_values(array_filter($regs, fn($r) => (int)$r['focus_losses'] > 0));
usort($exits, fn($a, $b) => (int)$b['focus_losses'] <=> (int)$a['focus_losses']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($T['title']) ?> — StudyVibe</title>
<?= Brand::headLinks() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<script>try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
<style>
  body { margin: 0; background: var(--paper); color: var(--ink); font: 16px/1.5 'Hanken Grotesk', system-ui, sans-serif; }
  main { max-width: 64rem; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
  a { color: var(--clay); }
  h1 { font: 500 clamp(1.6rem, 4vw, 2.2rem)/1.15 'Fraunces', serif; margin: .5rem 0 .25rem; }
  h2 { font: 500 1.35rem/1.2 'Fraunces', serif; margin: 2.5rem 0 .75rem; }
  .sub { color: var(--ink-2); margin: 0 0 1.5rem; }
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 1rem; }
  .stat { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: .9rem 1rem; }
  .stat b { display: block; font: 600 1.6rem/1.2 'Hanken Grotesk', sans-serif; font-variant-numeric: tabular-nums; }
  .stat span { color: var(--ink-2); font-size: .85rem; }
  .bars { display: grid; grid-template-columns: repeat(10, 1fr); gap: .4rem; align-items: end; height: 9rem; }
  .bar { background: var(--clay); border-radius: 4px 4px 0 0; min-height: 2px; }
  .bar-l { display: grid; grid-template-columns: repeat(10, 1fr); gap: .4rem; font-size: .75rem; color: var(--ink-2); text-align: center; margin-top: .25rem; }
  .wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; background: var(--card); border: 1px solid var(--line); border-radius: 12px; font-size: .92rem; }
  th, td { padding: .6rem .75rem; text-align: left; border-bottom: 1px solid var(--line); vertical-align: top; }
  th { color: var(--ink-2); font-weight: 600; font-size: .8rem; white-space: nowrap; }
  td.num { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .muted { color: var(--ink-2); }
  .warn { color: var(--clay); font-weight: 600; }
  .btn { display: inline-block; padding: .6rem 1rem; min-height: 44px; box-sizing: border-box; border: 1px solid var(--line-2); border-radius: 10px; text-decoration: none; color: var(--ink); }
  .btn:focus-visible, a:focus-visible { outline: 2px solid var(--clay); outline-offset: 2px; }
</style>
</head>
<body>
<main>
  <a href="/teacher/dashboard.php?course_id=<?= (int)$session['course_id'] ?>#tab-live-eval"><?= $h($T['back']) ?></a>
  <h1><?= $h($session['title']) ?></h1>
  <p class="sub"><?= $h($session['course_title']) ?> · <?= $h($T['shuffle']) ?>: <?= $h(!empty($session['shuffle_options']) ? $T['yes'] : $T['no']) ?></p>

  <div class="stats">
    <div class="stat"><b><?= count($regs) ?></b><span><?= $h($T['participants']) ?></span></div>
    <div class="stat"><b><?= $cnt ?></b><span><?= $h($T['graded']) ?></span></div>
    <div class="stat"><b><?= $mean !== null ? number_format($mean, 1) . ' %' : '–' ?></b><span><?= $h($T['mean']) ?></span></div>
    <div class="stat"><b><?= $median !== null ? number_format($median, 1) . ' %' : '–' ?></b><span><?= $h($T['median']) ?></span></div>
    <div class="stat"><b><?= $cnt ? number_format($scores[0], 0) . ' / ' . number_format($scores[$cnt - 1], 0) : '–' ?></b><span><?= $h($T['range']) ?></span></div>
  </div>

  <h2><?= $h($T['dist']) ?></h2>
  <?php if ($cnt === 0): ?>
    <p class="muted"><?= $h($T['nodata']) ?></p>
  <?php else: ?>
    <div class="bars" role="img" aria-label="<?= $h($T['dist']) ?>">
      <?php foreach ($buckets as $i => $c): ?>
        <div class="bar" style="height: <?= round($c / $maxBucket * 100) ?>%" title="<?= $i * 10 ?>–<?= $i * 10 + 10 ?> % : <?= $c ?>"></div>
      <?php endforeach; ?>
    </div>
    <div class="bar-l"><?php for ($i = 0; $i < 10; $i++): ?><span><?= $i * 10 ?></span><?php endfor; ?></div>
  <?php endif; ?>

  <h2><?= $h($T['items']) ?></h2>
  <?php if (!$rows): ?>
    <p class="muted"><?= $h($T['noq']) ?></p>
  <?php else: ?>
    <p class="muted"><?= $h($T['help']) ?></p>
    <div class="wrap"><table>
      <thead><tr><th>#</th><th><?= $h($T['q']) ?></th><th><?= $h($T['type']) ?></th><th><?= $h($T['ok']) ?></th><th><?= $h($T['answered']) ?></th><th><?= $h($T['disc']) ?></th><th><?= $h($T['wrong']) ?></th><th><?= $h($T['flag']) ?></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="num"><?= $r['n'] ?></td>
          <td><?= $h(mb_strimwidth($r['text'], 0, 110, '…')) ?></td>
          <td><?= $h($r['type'] === 'written' ? $T['written'] : $T['mcq']) ?></td>
          <td class="num"><?= $r['rate'] !== null ? round($r['rate'] * 100) . ' %' : '–' ?></td>
          <td class="num"><?= $r['answered'] ?></td>
          <td class="num"><?= $r['disc'] !== null ? number_format($r['disc'], 2) : '–' ?></td>
          <td class="num"><?= $h($r['wrong']) ?></td>
          <td class="<?= $r['flag'] === $T['fine'] ? 'muted' : 'warn' ?>"><?= $h($r['flag']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p><a class="btn" href="?session_id=<?= $sessionId ?>&amp;format=csv"><?= $h($T['csv']) ?></a></p>
  <?php endif; ?>

  <h2><?= $h($T['integ']) ?></h2>
  <?php if (!$watch): ?>
    <p class="muted"><?= $h($T['integ_off']) ?></p>
  <?php elseif (!$exits): ?>
    <p class="muted"><?= $h($T['integ_none']) ?></p>
  <?php else: ?>
    <div class="wrap"><table>
      <thead><tr><th><?= $h($T['student']) ?></th><th><?= $h($T['exits']) ?></th><th><?= $h($T['last']) ?></th><th><?= $h($T['score']) ?></th></tr></thead>
      <tbody>
      <?php foreach ($exits as $r): ?>
        <tr>
          <td><?= $h($r['name']) ?></td>
          <td class="num <?= (int)$r['focus_losses'] >= 3 ? 'warn' : '' ?>"><?= (int)$r['focus_losses'] ?></td>
          <td class="num"><?= $h($r['last_focus_loss_at'] ?? '') ?></td>
          <td class="num"><?= $r['score'] !== null ? number_format((float)$r['score'], 1) . ' %' : '–' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</main>
</body>
</html>
