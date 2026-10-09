<?php
declare(strict_types=1);

/**
 * The page of a cancelled result: why it was cancelled, the student's own copy, the contestation form, and then the teacher's
 * final answer. Opened from the email (signed link) or from the evaluations list of the student dashboard.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/Contests.php';

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$en = $lang === 'en';
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$rid = (int)($_GET['registration_id'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));

$pdo = Database::getInstance();
$reg = $rid > 0 ? Contests::registration($pdo, $rid) : null;
$user = isLoggedIn() ? getCurrentUser() : null;

$deny = static function (string $title, string $text) use ($h, $en): never {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>StudyVibe</title>'
       . '<body style="font:16px/1.5 system-ui,sans-serif;background:#F5F0E6;color:#1E1B16;display:grid;place-items:center;min-height:100vh;margin:0;padding:1rem">'
       . '<main style="max-width:28rem"><h1 style="font:500 1.6rem Georgia,serif">' . $h($title) . '</h1><p>' . $h($text) . '</p>'
       . '<p><a href="/index.php" style="color:#B5482A">' . ($en ? 'Back to home' : 'Retour à l’accueil') . '</a></p></main>';
    exit;
};
if ($reg === null || !Contests::mayAccess($reg, $user, $token)) {
    $deny($en ? 'Access denied' : 'Accès refusé', $en ? 'This page is only for the student concerned.' : 'Cette page est réservée à l’étudiant concerné.');
}
if ($reg['cancelled_at'] === null) {
    $contest = Contests::find($pdo, $rid);
    if ($contest === null) {
        // Nothing was cancelled (or the result was restored by hand): send the student to their results
        header('Location: ' . ($user ? '/student/dashboard.php' : '/index.php'));
        exit;
    }
}
$contest = Contests::find($pdo, $rid);
$copy = Contests::copy($pdo, $reg);
$restored = $contest !== null && $contest['status'] === 'accepted';

$T = $en ? [
    'title' => 'Contest a cancelled result', 'kicker' => 'Evaluation', 'cancelled' => 'Your result was cancelled', 'restored' => 'Your result was restored',
    'why' => 'Reason given by your teacher', 'nowhy' => 'No reason was written.', 'copy' => 'Your copy', 'copy_hint' => 'Your answers, as you gave them. The correction is not shown while the result is cancelled.',
    'no_answer' => 'No answer', 'form_h' => 'Explain your side', 'form_p' => 'Write what happened, in your own words. Your teacher reads it and answers; the answer is final. You can send one message only.',
    'send' => 'Send my contestation', 'sending' => 'Sending…', 'sent_h' => 'Your contestation was sent', 'sent_p' => 'Your teacher has been told. You will get their answer by email and in your space.',
    'status_open' => 'Waiting for your teacher’s answer', 'status_accepted' => 'Accepted: your result counts again', 'status_rejected' => 'Final decision: the cancellation is maintained',
    'your_msg' => 'Your message', 'answer' => 'Your teacher’s answer', 'see_result' => 'See my corrected result', 'back' => 'Back to my space', 'min' => 'At least {n} characters.',
    'err' => 'Could not send. Please try again.', 'course' => 'Course', 'session' => 'Evaluation',
] : [
    'title' => 'Contester un résultat annulé', 'kicker' => 'Évaluation', 'cancelled' => 'Votre résultat a été annulé', 'restored' => 'Votre résultat a été rétabli',
    'why' => 'Motif indiqué par votre enseignant', 'nowhy' => 'Aucun motif n’a été écrit.', 'copy' => 'Votre copie', 'copy_hint' => 'Vos réponses, telles que vous les avez données. La correction n’est pas affichée tant que le résultat est annulé.',
    'no_answer' => 'Pas de réponse', 'form_h' => 'Expliquez votre point de vue', 'form_p' => 'Racontez ce qui s’est passé, avec vos mots. Votre enseignant lit votre message et répond ; sa réponse est définitive. Vous ne pouvez envoyer qu’un seul message.',
    'send' => 'Envoyer ma contestation', 'sending' => 'Envoi…', 'sent_h' => 'Votre contestation est envoyée', 'sent_p' => 'Votre enseignant est prévenu. Vous recevrez sa réponse par e-mail et dans votre espace.',
    'status_open' => 'En attente de la réponse de votre enseignant', 'status_accepted' => 'Acceptée : votre résultat est de nouveau pris en compte', 'status_rejected' => 'Décision définitive : l’annulation est maintenue',
    'your_msg' => 'Votre message', 'answer' => 'La réponse de votre enseignant', 'see_result' => 'Voir mon résultat corrigé', 'back' => 'Retour à mon espace', 'min' => 'Au moins {n} caractères.',
    'err' => 'Envoi impossible. Réessayez.', 'course' => 'Cours', 'session' => 'Évaluation',
];
$resultUrl = '/student/evaluation-results.php?registration_id=' . $rid . '&token=' . hash_hmac('sha256', (string)$rid, APP_SECRET);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $h($T['title']) ?> — StudyVibe</title>
<?= Brand::headLinks() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<script>try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
<style>
  body { margin: 0; background: var(--paper); color: var(--ink); font: 16px/1.5 'Hanken Grotesk', system-ui, sans-serif; }
  main { max-width: 44rem; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
  a { color: var(--clay); }
  h1 { font: 500 clamp(1.6rem, 4vw, 2.1rem)/1.2 'Fraunces', serif; margin: 1rem 0 .25rem; }
  h2 { font: 500 1.25rem/1.25 'Fraunces', serif; margin: 0 0 .5rem; }
  .kicker { color: var(--clay); font-weight: 700; font-size: .75rem; letter-spacing: .08em; text-transform: uppercase; margin: 1.25rem 0 0; }
  .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 1.25rem; margin: 1rem 0; }
  .facts { display: grid; grid-template-columns: auto 1fr; gap: .25rem 1rem; margin: .5rem 0 0; }
  .facts dt { color: var(--ink-3); } .facts dd { margin: 0; font-weight: 600; }
  .muted { color: var(--ink-2); }
  .q { padding: .75rem 0; border-top: 1px solid var(--line); } .q:first-of-type { border-top: 0; }
  .q b { display: block; margin-bottom: .25rem; }
  .pick { display: inline-block; padding: .2rem .7rem; border-radius: 999px; background: var(--clay-soft); color: var(--clay); font-weight: 600; font-size: .9rem; }
  .none { color: var(--ink-3); font-style: italic; }
  textarea { width: 100%; box-sizing: border-box; min-height: 9rem; padding: .7rem .85rem; border-radius: 12px; border: 1.5px solid var(--line-2); background: var(--paper); color: var(--ink); font: 500 1rem 'Hanken Grotesk', sans-serif; resize: vertical; }
  textarea:focus-visible, button:focus-visible, a:focus-visible { outline: 2px solid var(--clay); outline-offset: 2px; }
  .btn { min-height: 46px; padding: .5rem 1.3rem; border-radius: 12px; border: 0; background: var(--clay); color: #fff; font: 700 1rem 'Hanken Grotesk', sans-serif; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn.ghost { background: transparent; color: var(--ink); border: 1px solid var(--line-2); }
  .btn:disabled { opacity: .6; cursor: progress; }
  .pill { display: inline-block; padding: .25rem .8rem; border-radius: 999px; font-weight: 700; font-size: .85rem; }
  .pill.open { background: color-mix(in srgb, var(--ochre) 28%, transparent); color: #7A5A12; }
  .pill.ok { background: color-mix(in srgb, var(--pine) 16%, transparent); color: var(--pine); }
  .pill.no { background: color-mix(in srgb, var(--danger) 14%, transparent); color: var(--danger); }
  .msg { white-space: pre-wrap; background: var(--paper); border-radius: 12px; padding: .8rem 1rem; margin: .5rem 0 0; }
  .err { color: var(--danger); font-weight: 600; margin: .5rem 0 0; } .err[hidden] { display: none; }
  .count { color: var(--ink-3); font-size: .85rem; margin: .25rem 0 .75rem; }
</style>
</head>
<body>
<main>
  <?= Brand::logo('md') ?>
  <p class="kicker"><?= $h($T['kicker']) ?></p>
  <h1><?= $h($restored ? $T['restored'] : $T['cancelled']) ?></h1>
  <dl class="facts">
    <dt><?= $h($T['course']) ?></dt><dd><?= $h($reg['course_title']) ?></dd>
    <dt><?= $h($T['session']) ?></dt><dd><?= $h($reg['session_title']) ?></dd>
  </dl>

  <?php if ($reg['cancelled_at'] !== null || $restored): ?>
  <section class="card">
    <h2><?= $h($T['why']) ?></h2>
    <p class="<?= $reg['cancelled_reason'] ? '' : 'none' ?>" style="margin:0"><?= $h($reg['cancelled_reason'] ?: $T['nowhy']) ?></p>
  </section>
  <?php endif; ?>

  <section class="card" aria-labelledby="copy-h">
    <h2 id="copy-h"><?= $h($T['copy']) ?></h2>
    <p class="muted" style="margin-top:0"><?= $h($T['copy_hint']) ?></p>
    <?php foreach ($copy as $q): ?>
      <div class="q">
        <b><?= $q['n'] ?>. <?= $h($q['text']) ?></b>
        <?php if ($q['picked_text'] !== ''): ?>
          <span class="pick"><?= $q['type'] === 'mcq' ? $h($q['picked']) . '. ' : '' ?><?= $h($q['picked_text']) ?></span>
        <?php else: ?>
          <span class="none"><?= $h($T['no_answer']) ?></span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>

  <?php if ($contest === null): ?>
  <section class="card" id="form-card" aria-labelledby="form-h">
    <h2 id="form-h"><?= $h($T['form_h']) ?></h2>
    <p class="muted" style="margin-top:0"><?= $h($T['form_p']) ?></p>
    <form id="cf" novalidate>
      <label for="msg" class="muted"><?= $h($T['your_msg']) ?></label>
      <textarea id="msg" maxlength="<?= Contests::MAX ?>" required></textarea>
      <p class="count"><span id="n">0</span> / <?= Contests::MAX ?> · <?= $h(str_replace('{n}', (string)Contests::MIN, $T['min'])) ?></p>
      <p class="err" id="err" role="alert" hidden></p>
      <button class="btn" id="go" type="submit"><?= $h($T['send']) ?></button>
    </form>
  </section>
  <section class="card" id="sent-card" hidden><h2><?= $h($T['sent_h']) ?></h2><p class="muted" style="margin:0"><?= $h($T['sent_p']) ?></p></section>
  <?php else: ?>
  <section class="card" aria-labelledby="st-h">
    <h2 id="st-h"><span class="pill <?= $contest['status'] === 'open' ? 'open' : ($contest['status'] === 'accepted' ? 'ok' : 'no') ?>"><?= $h($T['status_' . $contest['status']]) ?></span></h2>
    <p class="muted" style="margin:.75rem 0 0"><?= $h($T['your_msg']) ?></p>
    <div class="msg"><?= $h($contest['message']) ?></div>
    <?php if ($contest['status'] !== 'open'): ?>
      <p class="muted" style="margin:1rem 0 0"><?= $h($T['answer']) ?></p>
      <div class="msg"><?= $h($contest['teacher_response'] ?: '—') ?></div>
    <?php endif; ?>
    <?php if ($restored): ?><p style="margin:1rem 0 0"><a class="btn" href="<?= $h($resultUrl) ?>"><?= $h($T['see_result']) ?></a></p><?php endif; ?>
  </section>
  <?php endif; ?>

  <p><a class="btn ghost" href="<?= $user ? '/student/dashboard.php' : '/index.php' ?>"><?= $h($T['back']) ?></a></p>
</main>
<?php if ($contest === null): ?>
<script>
(function () {
  var msg = document.getElementById('msg'), n = document.getElementById('n'), err = document.getElementById('err'), go = document.getElementById('go');
  msg.addEventListener('input', function () { n.textContent = msg.value.length; });
  document.getElementById('cf').addEventListener('submit', function (e) {
    e.preventDefault(); err.hidden = true; go.disabled = true; var label = go.textContent; go.textContent = <?= json_encode($T['sending']) ?>;
    var fd = new FormData(); fd.append('registration_id', <?= json_encode((string)$rid) ?>); fd.append('token', <?= json_encode($token) ?>); fd.append('message', msg.value);
    fetch('/api/contest.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.success) { document.getElementById('form-card').hidden = true; document.getElementById('sent-card').hidden = false; window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
      err.textContent = d.message || <?= json_encode($T['err']) ?>; err.hidden = false; go.disabled = false; go.textContent = label;
    }).catch(function () { err.textContent = <?= json_encode($T['err']) ?>; err.hidden = false; go.disabled = false; go.textContent = label; });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
