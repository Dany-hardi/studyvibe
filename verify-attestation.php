<?php
declare(strict_types=1);

/** Public check of a certificate of activity: the figures are read from the sealed archive and the seal is compared with the ledger. */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/Archive.php';

$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$code = strtoupper(trim((string)($_GET['code'] ?? '')));
$res = null;
if ($code !== '') {
    $pdo = Database::getInstance();
    require_once __DIR__ . '/lib/RateLimit.php';
    require_once __DIR__ . '/lib/Security.php';
    if (!RateLimit::allow($pdo, 'verify_att:' . hash('sha256', Security::clientIp()), 30, 60)) {
        http_response_code(429);
        exit('Trop de vérifications. Patientez une minute.');
    }
    $res = Archive::openAttestation($pdo, $code);
}
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Vérifier une attestation — StudyVibe</title>
<?= Brand::headLinks() ?>
<style>body{font-family:system-ui,sans-serif;margin:0;background:#faf8f5;color:#1c1b19}main{max-width:44rem;margin:0 auto;padding:2rem 1rem 4rem}h1{font-weight:500}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:.4rem .5rem;border-bottom:1px solid #e4dfd6;font-size:.9rem}.ok{color:#1d6b3a;font-weight:600}.ko{color:#b42318;font-weight:600}
input{padding:.6rem;border:1px solid #bbb;font:inherit}button{padding:.6rem 1rem;border:1px solid #1c1b19;background:#1c1b19;color:#fff;font:inherit;cursor:pointer}code{word-break:break-all}.mut{color:#6b665d;font-size:.85rem}</style>
</head>
<body><main>
<h1>Vérifier une attestation d’activité</h1>
<form method="get"><input name="code" value="<?= $h($code) ?>" placeholder="SVA-XXXXXXXXXX" required> <button type="submit">Vérifier</button></form>
<?php if ($code !== '' && !$res): ?>
  <p class="ko">Code inconnu, ou le contenu ne correspond plus à son sceau.</p>
<?php elseif ($res): $d = $res['data']; $a = $d['activity']; ?>
  <p class="<?= $res['ledger_ok'] ? 'ok' : 'ko' ?>"><?= $res['ledger_ok'] ? '✔ Attestation authentique : le contenu correspond à son sceau enregistré.' : '✖ Le sceau ne correspond pas au registre : ne pas se fier à ce document.' ?></p>
  <p><strong><?= $h($d['teacher']['name']) ?></strong><br>Émise le <?= $h($d['issued_at']) ?> · code <code><?= $h($d['code']) ?></code></p>
  <table><tbody>
    <tr><th>Évaluations menées</th><td><?= (int)$a['sessions'] ?></td></tr>
    <tr><th>Participations</th><td><?= (int)$a['participations'] ?></td></tr>
    <tr><th>Copies rendues</th><td><?= (int)$a['finished'] ?></td></tr>
    <tr><th>Période</th><td><?= $h(substr((string)$a['first'], 0, 10)) ?> → <?= $h(substr((string)$a['last'], 0, 10)) ?></td></tr>
  </tbody></table>
  <p class="mut">Sceau SHA-256 : <code><?= $h($res['meta']['sha256']) ?></code></p>
<?php endif; ?>
</main></body></html>
