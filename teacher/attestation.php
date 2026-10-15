<?php
declare(strict_types=1);

/**
 * Certificate of activity of a teacher: how many evaluations were held, how many people took part, taken from the sealed archives (so it stays true
 * after sessions are deleted). Each certificate has a code anyone can check on /verify-attestation.php, e.g. for an interview or an application.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/Archive.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    http_response_code(403);
    exit('Accès non autorisé.');
}
$pdo = Database::getInstance();
$teacherId = (int)$_SESSION['user_id'];
$en = TranslationService::getLang() === 'en';
$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'issue') {
    if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('CSRF');
    }
    $r = Archive::issueAttestation($pdo, $teacherId);
    auditLog('attestation_issued', $r['code'] ?? '');
    header('Location: /teacher/attestation.php' . ($r ? '?code=' . urlencode($r['code']) : ''));
    exit;
}

$code = (string)($_GET['code'] ?? '');
$view = null;
if ($code !== '') {
    $view = Archive::openAttestation($pdo, $code);
    if ($view && (int)$view['meta']['teacher_id'] !== $teacherId) {
        $view = null;
    }
}
$list = $pdo->prepare("SELECT code, created_at, items FROM archives WHERE kind = 'attestation' AND teacher_id = ? ORDER BY id DESC LIMIT 50");
$list->execute([$teacherId]);
$list = $list->fetchAll(PDO::FETCH_ASSOC);
$live = Archive::teacherActivity($pdo, $teacherId);
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
$T = $en
    ? ['t' => 'Certificate of activity', 'back' => 'Back to archives', 'lede' => 'Proof of the evaluations you ran on StudyVibe, built from the sealed archives. Share the code or the link: anyone can verify it.', 'now' => 'Your figures today', 'issue' => 'Issue a certificate', 'sess' => 'Evaluations held', 'part' => 'Participations', 'fin' => 'Copies handed in', 'ppl' => 'Distinct people still identifiable', 'period' => 'Period', 'issued' => 'Issued', 'verify' => 'Public verification link', 'prev' => 'Previous certificates', 'print' => 'Print', 'ev' => 'Evaluation', 'date' => 'Date', 'seal' => 'Seal (SHA-256)', 'ok' => 'Intact', 'note' => 'Participations count every registration; people whose identity was erased on request are still counted in participations.']
    : ['t' => 'Attestation d’activité', 'back' => 'Retour aux archives', 'lede' => 'Preuve des évaluations que vous avez menées sur StudyVibe, construite à partir des archives scellées. Partagez le code ou le lien : n’importe qui peut le vérifier.', 'now' => 'Vos chiffres aujourd’hui', 'issue' => 'Émettre une attestation', 'sess' => 'Évaluations menées', 'part' => 'Participations', 'fin' => 'Copies rendues', 'ppl' => 'Personnes distinctes encore identifiables', 'period' => 'Période', 'issued' => 'Émise le', 'verify' => 'Lien de vérification public', 'prev' => 'Attestations précédentes', 'print' => 'Imprimer', 'ev' => 'Évaluation', 'date' => 'Date', 'seal' => 'Sceau (SHA-256)', 'ok' => 'Intègre', 'note' => 'Les participations comptent chaque inscription ; les personnes dont l’identité a été effacée sur demande restent comptées dans les participations.'];
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="<?= $en ? 'en' : 'fr' ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $h($T['t']) ?> — StudyVibe</title>
<?= Brand::headLinks() ?>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#faf8f5;color:#1c1b19}main{max-width:52rem;margin:0 auto;padding:1.5rem 1rem 4rem}
h1{font-weight:500}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:.45rem .5rem;border-bottom:1px solid #e4dfd6;font-size:.9rem;vertical-align:top}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:.8rem;margin:1rem 0}.card{background:#fff;border:1px solid #e4dfd6;padding:.9rem}.card b{display:block;font-size:1.8rem;font-variant-numeric:tabular-nums}
button,.b{padding:.6rem 1rem;border:1px solid #1c1b19;background:#1c1b19;color:#fff;cursor:pointer;text-decoration:none;display:inline-block;font:inherit}code{word-break:break-all}.mut{color:#6b665d;font-size:.85rem}
@media print{.noprint{display:none}body{background:#fff}}
</style>
</head>
<body><main>
<p class="noprint"><a href="/teacher/archives.php">← <?= $h($T['back']) ?></a></p>
<?php if ($view): $d = $view['data']; $a = $d['activity']; $link = $base . '/verify-attestation.php?code=' . urlencode($d['code']); ?>
  <h1><?= $h($T['t']) ?></h1>
  <p><strong><?= $h($d['teacher']['name']) ?></strong> · <?= $h($T['issued']) ?> <?= $h($d['issued_at']) ?> · <code><?= $h($d['code']) ?></code></p>
  <div class="cards">
    <div class="card"><b><?= (int)$a['sessions'] ?></b><?= $h($T['sess']) ?></div>
    <div class="card"><b><?= (int)$a['participations'] ?></b><?= $h($T['part']) ?></div>
    <div class="card"><b><?= (int)$a['finished'] ?></b><?= $h($T['fin']) ?></div>
    <div class="card"><b><?= (int)$a['distinct_people'] ?></b><?= $h($T['ppl']) ?></div>
  </div>
  <p><?= $h($T['period']) ?> : <?= $h(substr((string)$a['first'], 0, 10)) ?> → <?= $h(substr((string)$a['last'], 0, 10)) ?></p>
  <p><?= $h($T['verify']) ?> : <a href="<?= $h($link) ?>"><code><?= $h($link) ?></code></a></p>
  <table><thead><tr><th><?= $h($T['ev']) ?></th><th><?= $h($T['date']) ?></th><th><?= $h($T['part']) ?></th><th><?= $h($T['fin']) ?></th><th><?= $h($T['seal']) ?></th></tr></thead><tbody>
  <?php foreach ($a['list'] as $e): ?><tr><td><?= $h($e['title']) ?></td><td><?= $h(substr((string)$e['date'], 0, 10)) ?></td><td><?= (int)$e['participants'] ?></td><td><?= (int)$e['finished'] ?></td><td><code><?= $h(substr((string)$e['sha256'], 0, 16)) ?>…</code></td></tr><?php endforeach; ?></tbody></table>
  <p class="mut"><?= $h($T['note']) ?></p>
  <p class="noprint"><button type="button" onclick="window.print()"><?= $h($T['print']) ?></button></p>
<?php else: ?>
  <h1><?= $h($T['t']) ?></h1>
  <p><?= $h($T['lede']) ?></p>
  <h2><?= $h($T['now']) ?></h2>
  <div class="cards">
    <div class="card"><b><?= (int)$live['sessions'] ?></b><?= $h($T['sess']) ?></div>
    <div class="card"><b><?= (int)$live['participations'] ?></b><?= $h($T['part']) ?></div>
    <div class="card"><b><?= (int)$live['finished'] ?></b><?= $h($T['fin']) ?></div>
    <div class="card"><b><?= (int)$live['distinct_people'] ?></b><?= $h($T['ppl']) ?></div>
  </div>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="issue"><button type="submit"><?= $h($T['issue']) ?></button></form>
  <?php if ($list): ?><h2><?= $h($T['prev']) ?></h2>
  <table><tbody><?php foreach ($list as $r): ?><tr><td><a href="?code=<?= $h(urlencode($r['code'])) ?>"><?= $h($r['code']) ?></a></td><td><?= $h($r['created_at']) ?></td><td><?= (int)$r['items'] ?> <?= $h(strtolower($T['part'])) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php endif; ?>
</main></body></html>
