<?php
declare(strict_types=1);

/**
 * The code answers of a session for the teacher: what each student wrote, the verdict and feedback, the corrected script, and the
 * buttons to overrule the AI or send an answer back to the marking. The ZIP with every correction script is downloaded from here.
 *
 *   /teacher/live-code-review.php?session_id=12
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/LiveCodeReview.php';
require_once __DIR__ . '/../lib/AiKeyPool.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    http_response_code(403);
    exit('Accès non autorisé.');
}
$sessionId = (int)($_GET['session_id'] ?? 0);
$pdo = Database::getInstance();
$st = $pdo->prepare("SELECT s.*, c.title AS course_title FROM live_eval_sessions s JOIN courses c ON c.id = s.course_id WHERE s.id = :i AND s.teacher_id = :t");
$st->execute(['i' => $sessionId, 't' => (int)$_SESSION['user_id']]);
$session = $st->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(404);
    exit('Séance introuvable.');
}
$en = TranslationService::getLang() === 'en';
$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$T = $en ? [
    'title' => 'Corrections', 'back' => 'Back to the dashboard', 'zip' => 'Download all corrections (ZIP)', 'run' => 'Mark what is waiting', 'answers' => 'Answers', 'correct' => 'Correct',
    'wrong' => 'Incorrect', 'waiting' => 'Waiting', 'review' => 'To decide', 'check' => 'Worth a look', 'none' => 'No student has submitted an answer yet.', 'student' => 'Student',
    'submitted' => 'Submitted answer', 'feedback' => 'Feedback to the student', 'logic' => 'Logic analysis', 'issues' => 'Problems found', 'corrected' => 'Corrected version', 'ai' => 'AI',
    'teacher' => 'Teacher', 'mk_ok' => 'Mark correct', 'mk_ko' => 'Mark incorrect', 'again' => 'Send back to the AI', 'note' => 'Note (optional)', 'q' => 'Question',
    'st_pending' => 'Waiting for the marking', 'st_working' => 'Being marked', 'st_review' => 'The AI could not mark it: please decide', 'st_done' => 'Marked', 'unconf' => 'The AI marking is not set up on this server (ANTHROPIC_API_KEY): answers wait until it is.',
    'human' => 'The AI is not sure: please check', 'ref' => 'Reference (hidden from students)', 'blocking' => 'blocking', 'minor' => 'minor', 'saved' => 'Saved', 'err' => 'Something went wrong',
    'copy' => 'Copy', 'all' => 'All', 'only' => 'Only the ones to check', 'by' => 'decided by',
] : [
    'title' => 'Corrections', 'back' => 'Retour au tableau de bord', 'zip' => 'Télécharger toutes les corrections (ZIP)', 'run' => 'Corriger ce qui attend', 'answers' => 'Réponses', 'correct' => 'Correctes',
    'wrong' => 'Incorrectes', 'waiting' => 'En attente', 'review' => 'À trancher', 'check' => 'À vérifier', 'none' => 'Aucun étudiant n’a encore soumis de réponse.', 'student' => 'Étudiant',
    'submitted' => 'Réponse soumise', 'feedback' => 'Commentaire pour l’étudiant', 'logic' => 'Analyse de la logique', 'issues' => 'Problèmes relevés', 'corrected' => 'Version corrigée', 'ai' => 'IA',
    'teacher' => 'Enseignant', 'mk_ok' => 'Marquer correct', 'mk_ko' => 'Marquer incorrect', 'again' => 'Renvoyer à l’IA', 'note' => 'Note (facultatif)', 'q' => 'Question',
    'st_pending' => 'En attente de correction', 'st_working' => 'Correction en cours', 'st_review' => 'L’IA n’a pas pu corriger : à vous de trancher', 'st_done' => 'Corrigé', 'unconf' => 'La correction par l’IA n’est pas configurée sur ce serveur (ANTHROPIC_API_KEY) : les réponses attendent.',
    'human' => 'L’IA hésite : à vérifier', 'ref' => 'Référence (cachée aux étudiants)', 'blocking' => 'bloquant', 'minor' => 'mineur', 'saved' => 'Enregistré', 'err' => 'Une erreur est survenue',
    'copy' => 'Copier', 'all' => 'Toutes', 'only' => 'Seulement celles à vérifier', 'by' => 'décidé par',
];

$qs = $pdo->prepare("SELECT * FROM live_eval_questions WHERE session_id = :i ORDER BY sort_order, id");
$qs->execute(['i' => $sessionId]);
$allQ = $qs->fetchAll(PDO::FETCH_ASSOC);
$numbers = [];
foreach ($allQ as $i => $q) { $numbers[(int)$q['id']] = $i + 1; }
$codeQ = array_values(array_filter($allQ, fn($q) => LiveCodeReview::handles($q['question_type'])));
if (!$codeQ) {
    http_response_code(404);
    exit($en ? 'This session has no code or written-answer question.' : 'Cette séance n’a pas de question de code ni de question rédigée.');
}
$rv = $pdo->prepare("
    SELECT cr.*, r.name AS student_name, r.email AS student_email
    FROM live_eval_code_reviews cr JOIN live_eval_registrations r ON r.id = cr.registration_id
    WHERE r.session_id = :s ORDER BY cr.question_id, r.name, r.id
");
$rv->execute(['s' => $sessionId]);
$rows = array_map([LiveCodeReview::class, 'effective'], $rv->fetchAll(PDO::FETCH_ASSOC));
$byQ = [];
$tot = ['ok' => 0, 'ko' => 0, 'wait' => 0, 'review' => 0];
foreach ($rows as $r) {
    $byQ[(int)$r['question_id']][] = $r;
    if ($r['final'] === true) { $tot['ok']++; } elseif ($r['final'] === false) { $tot['ko']++; }
    if (in_array($r['status'], ['pending', 'working'], true) && $r['final'] === null) { $tot['wait']++; }
    if ($r['status'] === 'review' && $r['final'] === null) { $tot['review']++; }
}
$only = isset($_GET['check']);
$csrf = csrfToken();
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="<?= $en ? 'en' : 'fr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($T['title']) ?> — StudyVibe</title>
<?= Brand::headLinks() ?>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<script>try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
<style>
  body { margin: 0; background: var(--paper); color: var(--ink); font: 16px/1.5 'Hanken Grotesk', system-ui, sans-serif; }
  main { max-width: 66rem; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
  a { color: var(--clay); }
  h1 { font: 500 clamp(1.6rem, 4vw, 2.2rem)/1.15 'Fraunces', serif; margin: .5rem 0 .25rem; }
  h2 { font: 500 1.25rem/1.25 'Fraunces', serif; margin: 2rem 0 .75rem; }
  .sub { color: var(--ink-2); margin: 0 0 1.25rem; }
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr)); gap: .75rem; margin-bottom: 1rem; }
  .stat { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: .8rem 1rem; }
  .stat b { display: block; font: 600 1.5rem/1.2 'Hanken Grotesk', sans-serif; font-variant-numeric: tabular-nums; }
  .stat span { color: var(--ink-2); font-size: .85rem; }
  .bar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; margin: 0 0 1rem; }
  .btn { display: inline-block; padding: .6rem 1rem; min-height: 44px; box-sizing: border-box; border: 1px solid var(--line-2); border-radius: 10px; background: var(--card); color: var(--ink); text-decoration: none; cursor: pointer; font: inherit; }
  .btn-main { background: var(--clay); border-color: var(--clay); color: #fff; }
  .btn:focus-visible, a:focus-visible, summary:focus-visible { outline: 2px solid var(--clay); outline-offset: 2px; }
  details.ans { background: var(--card); border: 1px solid var(--line); border-radius: 12px; margin: 0 0 .6rem; }
  details.ans > summary { list-style: none; cursor: pointer; padding: .8rem 1rem; display: flex; flex-wrap: wrap; gap: .5rem 1rem; align-items: center; }
  details.ans > summary::-webkit-details-marker { display: none; }
  .who { font-weight: 600; } .mail { color: var(--ink-2); font-size: .85rem; }
  .pill { border-radius: 999px; padding: .1rem .65rem; font-size: .78rem; font-weight: 700; }
  .ok { background: #E8F2EA; color: #24402F; } .ko { background: #FCE9E6; color: #9C2B1F; } .wt { background: #FFF3D6; color: #7A5200; } .hm { background: #E7EEF9; color: #234; }
  .body { padding: 0 1rem 1rem; display: grid; gap: .9rem; }
  .cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: .9rem; }
  .prose { padding: .8rem; background: var(--paper-2, #f4f0e8); border: 1px solid var(--line); border-radius: 8px; max-height: 24rem; overflow: auto; white-space: pre-wrap; line-height: 1.55; }
  pre { margin: 0; padding: .8rem; background: var(--paper-2, #f4f0e8); border: 1px solid var(--line); border-radius: 8px; overflow: auto; max-height: 24rem; font: 13px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; tab-size: 4; }
  h4 { margin: 0 0 .3rem; font-size: .8rem; letter-spacing: .05em; text-transform: uppercase; color: var(--ink-2); }
  .issue { margin: .15rem 0; } .muted { color: var(--ink-2); }
  .act { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
  .act input[type=text] { flex: 1; min-width: 12rem; padding: .55rem .7rem; border: 1px solid var(--line-2); border-radius: 8px; background: var(--card); color: var(--ink); font: inherit; }
  .notice { background: #FFF3D6; color: #7A5200; border-radius: 10px; padding: .7rem 1rem; margin: 0 0 1rem; }
</style>
</head>
<body>
<main>
  <a href="/teacher/dashboard.php?course_id=<?= (int)$session['course_id'] ?>#tab-live-eval"><?= $h($T['back']) ?></a>
  <h1><?= $h($T['title']) ?> — <?= $h($session['title']) ?></h1>
  <p class="sub"><?= $h($session['course_title']) ?></p>
  <?php if (!AnthropicClient::isConfigured()): ?><p class="notice"><?= $h($T['unconf']) ?></p><?php endif; ?>
  <?php if (AnthropicClient::isConfigured()): $pool = AiKeyPool::status($pdo, AnthropicClient::models('mark'));
        $tk = $pdo->prepare("SELECT COALESCE(SUM(c.tokens_in),0) ti, COALESCE(SUM(c.tokens_out),0) tout, COALESCE(SUM(c.tokens_cache_read),0) tr, COALESCE(SUM(c.tokens_cache_write),0) tw, COUNT(*) n, SUM(c.model LIKE 'local%') nl
                             FROM live_eval_code_reviews c JOIN live_eval_registrations r ON r.id = c.registration_id WHERE r.session_id = :s AND c.status = 'done'");
        $tk->execute(['s' => $sessionId]); $tok = $tk->fetch(PDO::FETCH_ASSOC); ?>
    <p class="sub" style="margin:.3rem 0 .4rem"><?= $h($en ? 'AI keys' : 'Clés IA') ?> : <b><?= (int)$pool['keys'] ?></b> · <?= $h($en ? 'lanes ready' : 'voies prêtes') ?> <b><?= (int)$pool['ready'] ?>/<?= (int)$pool['lanes'] ?></b>
      <?php foreach ($pool['resting'] as $r): ?> · <span title="<?= $h((string)$r['error']) ?>"><?= $h($r['key'] . ' ' . $r['model']) ?> <?= $h($en ? 'resting' : 'en pause') ?> <?= (int)$r['seconds'] ?> s</span><?php endforeach; ?></p>
    <?php if ((int)$tok['n'] > 0): $cachedShare = ($tok['ti'] + $tok['tr'] + $tok['tw']) > 0 ? round(100 * $tok['tr'] / ($tok['ti'] + $tok['tr'] + $tok['tw'])) : 0; ?>
    <p class="sub" style="margin:0 0 1rem; font-size:.9rem"><?= $h($en ? 'Tokens used by this session' : 'Jetons utilisés par cette séance') ?> : <b><?= number_format((int)$tok['ti'] + (int)$tok['tw']) ?></b> <?= $h($en ? 'in' : 'en entrée') ?>,
      <b><?= number_format((int)$tok['tr']) ?></b> <?= $h($en ? 'read from the cache' : 'lus dans le cache') ?> (<?= $cachedShare ?> %), <b><?= number_format((int)$tok['tout']) ?></b> <?= $h($en ? 'out' : 'en sortie') ?> · <?= (int)$tok['n'] ?> <?= $h($en ? 'answers' : 'réponses') ?><?= (int)$tok['nl'] > 0 ? ' (' . (int)$tok['nl'] . ' ' . $h($en ? 'too short, no call' : 'trop courtes, sans appel') . ')' : '' ?></p>
    <?php endif; ?>
  <?php endif; ?>

  <div class="stats">
    <div class="stat"><b><?= count($rows) ?></b><span><?= $h($T['answers']) ?></span></div>
    <div class="stat"><b><?= $tot['ok'] ?></b><span><?= $h($T['correct']) ?></span></div>
    <div class="stat"><b><?= $tot['ko'] ?></b><span><?= $h($T['wrong']) ?></span></div>
    <div class="stat"><b id="n-wait"><?= $tot['wait'] ?></b><span><?= $h($T['waiting']) ?></span></div>
    <div class="stat"><b><?= $tot['review'] ?></b><span><?= $h($T['review']) ?></span></div>
  </div>
  <div class="bar">
    <?php if ($rows): ?><a class="btn btn-main" href="/teacher/export-live-corrections.php?session_id=<?= $sessionId ?>"><?= $h($T['zip']) ?></a><?php endif; ?>
    <?php if ($tot['wait'] > 0): ?><button class="btn" id="run" type="button"><?= $h($T['run']) ?></button><?php endif; ?>
    <a class="btn" href="?session_id=<?= $sessionId ?><?= $only ? '' : '&check=1' ?>"><?= $h($only ? $T['all'] : $T['only']) ?></a>
    <span id="msg" class="muted" role="status"></span>
  </div>

  <?php if (!$rows): ?><p class="muted"><?= $h($T['none']) ?></p><?php endif; ?>
  <?php foreach ($codeQ as $q): $list = $byQ[(int)$q['id']] ?? []; if (!$list) { continue; } ?>
    <h2><?= $h($T['q']) ?> <?= (int)$numbers[(int)$q['id']] ?> · <?= $h($q['question_type'] === 'open' ? ($en ? 'Written answer' : 'Réponse rédigée') : LiveCodeReview::LANGS[LiveCodeReview::langOf($q['code_lang'] ?? null)][0]) ?></h2>
    <p class="muted" style="white-space:pre-wrap"><?= $h($q['question_text']) ?></p>
    <?php foreach ($list as $r):
        $final = $r['final'];
        $needsCheck = $r['needs_human'] || ($r['status'] === 'review' && $final === null);
        if ($only && !$needsCheck && $final !== null) { continue; }
        [$cls, $label] = $final === true ? ['ok', $T['correct']] : ($final === false ? ['ko', $T['wrong']] : ['wt', $r['status'] === 'review' ? $T['review'] : $T['waiting']]);
    ?>
    <details class="ans" <?= $needsCheck ? 'open' : '' ?> data-id="<?= (int)$r['id'] ?>">
      <summary>
        <span class="who"><?= $h($r['student_name']) ?></span><span class="mail"><?= $h($r['student_email']) ?></span>
        <span class="pill <?= $cls ?>"><?= $h($label) ?></span>
        <?php if ($r['override_verdict'] !== null): ?><span class="pill hm"><?= $h($T['by']) ?> <?= $h($T['teacher']) ?></span><?php elseif ($final !== null): ?><span class="pill hm"><?= $h($T['ai']) ?></span><?php endif; ?>
        <?php if ($r['needs_human']): ?><span class="pill wt"><?= $h($T['human']) ?></span><?php endif; ?>
      </summary>
      <div class="body">
        <?php if ($final === null): ?><p class="muted"><?= $h($T['st_' . ($r['status'] === 'review' ? 'review' : ($r['status'] === 'working' ? 'working' : 'pending'))]) ?><?= $r['last_error'] ? ' (' . $h($r['last_error']) . ')' : '' ?></p><?php endif; ?>
        <div class="cols">
          <div><h4><?= $h($T['submitted']) ?></h4><?= $q['question_type'] === 'open' ? '<div class="prose">' . $h($r['code']) . '</div>' : '<pre>' . $h($r['code']) . '</pre>' ?></div>
          <?php if ($r['corrected_code'] !== null && $r['corrected_code'] !== ''): ?><div><h4><?= $h($T['corrected']) ?></h4><?= $q['question_type'] === 'open' ? '<div class="prose">' . $h($r['corrected_code']) . '</div>' : '<pre>' . $h($r['corrected_code']) . '</pre>' ?></div><?php endif; ?>
        </div>
        <?php if (trim((string)$r['feedback']) !== ''): ?><div><h4><?= $h($T['feedback']) ?></h4><div style="white-space:pre-wrap"><?= $h($r['feedback']) ?></div></div><?php endif; ?>
        <?php if (trim((string)$r['logic_analysis']) !== ''): ?><div><h4><?= $h($T['logic']) ?></h4><div class="muted" style="white-space:pre-wrap"><?= $h($r['logic_analysis']) ?></div></div><?php endif; ?>
        <?php if ($r['issues_list']): ?><div><h4><?= $h($T['issues']) ?></h4>
          <?php foreach ($r['issues_list'] as $i): ?><div class="issue"><strong>[<?= $h($i['severity'] === 'blocking' ? $T['blocking'] : $T['minor']) ?>]</strong> <?= $i['where'] !== '' ? $h($i['where']) . ' : ' : '' ?><?= $h($i['description']) ?></div><?php endforeach; ?></div><?php endif; ?>
        <?php if (trim((string)$r['override_note']) !== ''): ?><div><h4><?= $h($T['teacher']) ?></h4><?= $h($r['override_note']) ?></div><?php endif; ?>
        <div class="act">
          <input type="text" maxlength="500" placeholder="<?= $h($T['note']) ?>" aria-label="<?= $h($T['note']) ?>">
          <button class="btn" type="button" data-act="ok"><?= $h($T['mk_ok']) ?></button>
          <button class="btn" type="button" data-act="ko"><?= $h($T['mk_ko']) ?></button>
          <button class="btn" type="button" data-act="requeue"><?= $h($T['again']) ?></button>
        </div>
      </div>
    </details>
    <?php endforeach; ?>
  <?php endforeach; ?>
</main>
<script>
(function () {
  var CSRF = <?= json_encode($csrf) ?>, SID = <?= (int)$sessionId ?>, MSG = <?= json_encode(['saved' => $T['saved'], 'err' => $T['err']], JSON_UNESCAPED_UNICODE) ?>;
  var msg = document.getElementById('msg');
  function post(data) {
    data.csrf_token = CSRF;
    var fd = new FormData(); for (var k in data) fd.append(k, data[k]);
    return fetch('/teacher/live-code-api.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  document.querySelectorAll('details.ans [data-act]').forEach(function (b) {
    b.addEventListener('click', function () {
      var box = b.closest('details.ans'), id = box.dataset.id, a = b.dataset.act;
      var note = box.querySelector('input[type=text]').value;
      b.disabled = true; msg.textContent = '…';
      post(a === 'requeue' ? { action: 'requeue', review_id: id } : { action: 'override', review_id: id, correct: a === 'ok' ? '1' : '0', note: note })
        .then(function (j) { if (j.ok) { msg.textContent = MSG.saved; location.reload(); } else { msg.textContent = MSG.err; b.disabled = false; } })
        .catch(function () { msg.textContent = MSG.err; b.disabled = false; });
    });
  });
  var run = document.getElementById('run');
  if (run) run.addEventListener('click', function () {
    run.disabled = true; msg.textContent = '…';
    post({ action: 'process', session_id: SID }).then(function () { location.reload(); }).catch(function () { msg.textContent = MSG.err; run.disabled = false; });
  });
})();
</script>
</body>
</html>
