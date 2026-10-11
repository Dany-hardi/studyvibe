<?php
declare(strict_types=1);

/**
 * Public verification page. Anybody who scans the QR code of a StudyVibe certificate or transcript (or types its code) lands here and
 * sees whether the document is genuine, who it was issued to and what it says. Certificate codes look like SV-1-A3F7B2C8,
 * transcript codes like TR-K7Q2-9XMA-4HPD. Nothing here needs an account.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Analytics.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/Transcripts.php';
require_once __DIR__ . '/lib/SocialMeta.php';
Analytics::hit('view:verify');

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$en = $lang === 'en';
$h = fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$T = $en ? [
    'title' => 'Verify a document', 'registry' => 'Verification registry', 'kicker' => 'Public registry',
    'h1' => 'Is this document <em>genuine</em>?', 'lede' => 'Scan the QR code printed on a StudyVibe certificate or transcript, or type its code below. The answer comes straight from our records.',
    'placeholder' => 'e.g. SV-1-A3F7B2C8 or TR-K7Q2-9XMA-4HPD', 'check' => 'Check', 'label' => 'Document code',
    'steps' => [['Find the code', 'It is printed at the bottom of the document, next to the QR code.'], ['Scan or type it', 'Your phone camera opens this page by itself, or type the code here.'], ['Read the answer', 'Genuine, altered or unknown: you see it at once, with what the document says.']],
    'steps_h' => 'How it works', 'can_h' => 'What can be checked', 'can' => [['Certificates', 'Awarded when a course final assessment is passed.'], ['Transcripts', 'Courses, lessons and results, as of the day they were issued.']],
    'ok_cert' => 'Genuine certificate', 'ok_tr' => 'Genuine transcript', 'bad_h' => 'Document not recognised', 'bad_p' => 'No document matches this code. Check it for typing mistakes (letters and digits are easily confused), or ask the holder for the original.',
    'empty_code' => 'No code was given.', 'throttle' => 'Too many checks in a short time. Please wait a few minutes and try again.', 'server' => 'A server error occurred. Please try again.',
    'altered_h' => 'Record altered', 'altered_p' => 'The record for this code does not match its integrity fingerprint. Do not rely on this document and contact StudyVibe.',
    'revoked_h' => 'Document revoked', 'revoked_p' => 'This transcript was cancelled by StudyVibe and is no longer valid.',
    'holder' => 'Issued to', 'course' => 'Course', 'teacher' => 'Instructor', 'issued' => 'Issued on', 'code' => 'Code', 'method' => 'How it was awarded',
    'manual' => 'Awarded exceptionally by the competent academic authority.', 'auto' => 'Awarded automatically after passing the final assessment with at least 80 %.',
    'matricule' => 'Student number', 'courses' => 'Courses', 'lessons' => 'Lessons completed', 'avg_best' => 'Average of best scores', 'progress' => 'Average progress',
    'tr_note' => 'This summary reflects the transcript on the day it was issued. Detailed results stay private to the student.', 'integrity' => 'Integrity', 'integrity_ok' => 'Fingerprint matches',
    'checked' => 'Checked on', 'copy' => 'Copy link', 'copied' => 'Copied', 'again' => 'Check another document', 'seal' => 'Official seal', 'back' => 'Back to StudyVibe', 'footer' => 'StudyVibe · Public verification registry',
    'img_alt' => 'A student with an exam sheet', 'no_alt' => 'Nothing found',
] : [
    'title' => 'Vérifier un document', 'registry' => 'Registre de vérification', 'kicker' => 'Registre public',
    'h1' => 'Ce document est-il <em>authentique</em> ?', 'lede' => 'Scannez le QR code imprimé sur un certificat ou un relevé StudyVibe, ou saisissez son code ci-dessous. La réponse vient directement de nos registres.',
    'placeholder' => 'ex. SV-1-A3F7B2C8 ou TR-K7Q2-9XMA-4HPD', 'check' => 'Vérifier', 'label' => 'Code du document',
    'steps' => [['Trouvez le code', 'Il est imprimé en bas du document, à côté du QR code.'], ['Scannez ou saisissez-le', 'L’appareil photo de votre téléphone ouvre cette page, ou tapez le code ici.'], ['Lisez la réponse', 'Authentique, modifié ou inconnu : vous le voyez tout de suite, avec ce que dit le document.']],
    'steps_h' => 'Comment ça marche', 'can_h' => 'Ce qui peut être vérifié', 'can' => [['Certificats', 'Délivrés lors de la réussite à l’évaluation finale d’un cours.'], ['Relevés de notes', 'Cours, leçons et résultats, à la date de leur édition.']],
    'ok_cert' => 'Certificat authentique', 'ok_tr' => 'Relevé authentique', 'bad_h' => 'Document non reconnu', 'bad_p' => 'Aucun document ne correspond à ce code. Vérifiez qu’il n’y a pas d’erreur de saisie (lettres et chiffres se confondent facilement) ou demandez l’original à son titulaire.',
    'empty_code' => 'Aucun code n’a été fourni.', 'throttle' => 'Trop de vérifications en peu de temps. Patientez quelques minutes puis réessayez.', 'server' => 'Une erreur serveur est survenue. Veuillez réessayer.',
    'altered_h' => 'Enregistrement altéré', 'altered_p' => 'L’enregistrement de ce code ne correspond plus à son empreinte d’intégrité. Ne vous fiez pas à ce document et contactez StudyVibe.',
    'revoked_h' => 'Document révoqué', 'revoked_p' => 'Ce relevé a été annulé par StudyVibe et n’est plus valable.',
    'holder' => 'Délivré à', 'course' => 'Cours', 'teacher' => 'Enseignant', 'issued' => 'Date de délivrance', 'code' => 'Code', 'method' => 'Mode de délivrance',
    'manual' => 'Attribution exceptionnelle par l’autorité académique compétente.', 'auto' => 'Délivré automatiquement après la réussite à l’évaluation finale avec au moins 80 %.',
    'matricule' => 'Matricule', 'courses' => 'Cours', 'lessons' => 'Leçons validées', 'avg_best' => 'Moyenne des meilleurs scores', 'progress' => 'Progression moyenne',
    'tr_note' => 'Ce résumé reflète le relevé le jour de son édition. Les résultats détaillés restent privés à l’étudiant.', 'integrity' => 'Intégrité', 'integrity_ok' => 'L’empreinte correspond',
    'checked' => 'Vérifié le', 'copy' => 'Copier le lien', 'copied' => 'Copié', 'again' => 'Vérifier un autre document', 'seal' => 'Sceau officiel', 'back' => 'Retour à StudyVibe', 'footer' => 'StudyVibe · Registre public de vérification',
    'img_alt' => 'Un étudiant avec une copie d’examen', 'no_alt' => 'Aucun résultat',
];

$code = trim((string)($_GET['code'] ?? ''));
$kind = '';          // 'certificate' | 'transcript' | ''
$doc = null;         // the row
$state = 'idle';     // idle | ok | altered | revoked | notfound | error | throttled

if ($code !== '') {
    // A light throttle per browser session: enough to make guessing codes pointless without bothering a real person
    $now = time();
    $_SESSION['verify_hits'] = array_values(array_filter((array)($_SESSION['verify_hits'] ?? []), fn($t) => $now - (int)$t < 600));
    if (count($_SESSION['verify_hits']) >= 40) {
        $state = 'throttled';
    } else {
        $_SESSION['verify_hits'][] = $now;
        try {
            $pdo = Database::getInstance();
            if (Transcripts::looksLikeCode(strtoupper($code))) {
                $code = strtoupper($code);
                $doc = Transcripts::find($pdo, $code);
                $kind = 'transcript';
                $state = !$doc ? 'notfound' : (!$doc['valid'] ? 'altered' : ($doc['revoked'] ? 'revoked' : 'ok'));
            } elseif (preg_match('/^[A-Za-z0-9_-]{4,100}$/', $code) === 1) {
                $st = $pdo->prepare("
                    SELECT cert.*, u.name AS student_name, c.title AS course_title, t.name AS teacher_name
                    FROM certificates cert
                    JOIN users u ON cert.student_id = u.id
                    LEFT JOIN courses c ON cert.course_id = c.id
                    LEFT JOIN users t ON c.teacher_id = t.id
                    WHERE cert.certificate_code = :code
                ");
                $st->execute(['code' => $code]);
                $doc = $st->fetch() ?: null;
                $kind = 'certificate';
                $state = $doc ? 'ok' : 'notfound';
            } else {
                $state = 'notfound';
            }
        } catch (Throwable $e) {
            error_log('[verify] ' . $e->getMessage());
            $state = 'error';
        }
    }
    if (in_array($state, ['ok', 'altered', 'revoked', 'notfound'], true)) {
        auditLog('verify_' . $state, substr($code, 0, 40));
    }
}

$verifyUrl = rtrim((string)APP_URL, '/') . '/verify.php' . ($code !== '' ? '?code=' . urlencode($code) : '');
$mask = function (?string $m): string {
    $m = (string)$m;
    return strlen($m) > 4 ? substr($m, 0, 2) . str_repeat('•', max(1, strlen($m) - 4)) . substr($m, -2) : $m;
};
$fmtDate = fn($ts) => date($en ? 'M j, Y \a\t H:i' : 'd/m/Y \à H:i', strtotime((string)$ts));
$pct = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format((float)$v, 1, $en ? '.' : ',', ''), '0'), $en ? '.' : ',') . ' %';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" class="v2">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($T['title']) ?> — StudyVibe</title>
<meta name="robots" content="noindex, nofollow">
<?= SocialMeta::tags($T['title'] . ' — StudyVibe', $T['lede'], '/verify.php', 'og-certificate.png') ?>
<?= Brand::headLinks() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/sv2.css') ?>">
<link rel="stylesheet" href="/assets/css/verify.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/verify.css') ?>">
<script>
  document.documentElement.classList.add('js');
  try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
</script>
</head>
<body class="v2 vf-body">

<header class="vf-top">
  <div class="vf-top-in">
    <a class="vf-logo" href="/index.php" aria-label="StudyVibe"><?= Brand::logo('md') ?></a>
    <span class="vf-badge"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></svg><?= $h($T['registry']) ?></span>
  </div>
</header>

<main class="vf-wrap">

<section class="vf-hero">
  <div class="vf-hero-text">
    <p class="vf-kicker"><?= $h($T['kicker']) ?></p>
    <h1><?= $T['h1'] ?></h1>
    <p class="vf-lede"><?= $h($T['lede']) ?></p>
    <form class="vf-form" action="/verify.php" method="get" role="search">
      <label for="vf-code" class="vf-sr"><?= $h($T['label']) ?></label>
      <input id="vf-code" name="code" type="text" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="100"
             placeholder="<?= $h($T['placeholder']) ?>" value="<?= $h($code) ?>" required>
      <button type="submit" class="btn btn-primary"><?= $h($T['check']) ?></button>
    </form>
  </div>
  <figure class="vf-art" role="img" aria-label="<?= $h($state === 'notfound' ? $T['no_alt'] : $T['img_alt']) ?>">
    <div class="vf-blob" aria-hidden="true"></div>
    <img src="/assets/img/evals/<?= in_array($state, ['notfound', 'altered', 'error'], true) ? 'noresult' : 'general-exams' ?>.svg" alt="" width="520" height="350" decoding="async">
  </figure>
</section>

<?php if ($state === 'throttled' || $state === 'error'): ?>
<section class="vf-result is-bad" aria-live="polite">
  <div class="vf-status"><span class="vf-ico">!</span><div><h2><?= $h($state === 'throttled' ? $T['throttle'] : $T['server']) ?></h2></div></div>
</section>

<?php elseif ($state === 'notfound'): ?>
<section class="vf-result is-bad" aria-live="polite">
  <div class="vf-status"><span class="vf-ico">✕</span><div><h2><?= $h($T['bad_h']) ?></h2><p><?= $h($T['bad_p']) ?></p></div></div>
  <p class="vf-codeline"><?= $h($T['code']) ?> : <code><?= $h($code) ?></code></p>
</section>

<?php elseif ($state === 'altered'): ?>
<section class="vf-result is-bad" aria-live="polite">
  <div class="vf-status"><span class="vf-ico">!</span><div><h2><?= $h($T['altered_h']) ?></h2><p><?= $h($T['altered_p']) ?></p></div></div>
  <p class="vf-codeline"><?= $h($T['code']) ?> : <code><?= $h($code) ?></code></p>
</section>

<?php elseif ($state === 'revoked' && $doc): ?>
<section class="vf-result is-warn" aria-live="polite">
  <div class="vf-status"><span class="vf-ico">!</span><div><h2><?= $h($T['revoked_h']) ?></h2><p><?= $h($T['revoked_p']) ?></p></div></div>
  <p class="vf-codeline"><?= $h($T['code']) ?> : <code><?= $h($code) ?></code> · <?= $h($T['issued']) ?> <?= $h($fmtDate($doc['issued_at'])) ?></p>
</section>

<?php elseif ($state === 'ok' && $kind === 'certificate' && $doc): ?>
<section class="vf-result is-ok" aria-live="polite">
  <div class="vf-status">
    <span class="vf-ico"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
    <div><h2><?= $h($T['ok_cert']) ?></h2><p><?= $h($T['checked']) ?> <?= $h($fmtDate('now')) ?></p></div>
    <svg class="vf-seal" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="46" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="50" cy="50" r="38" fill="none" stroke="currentColor" stroke-width=".6" stroke-dasharray="1.2 2.4"/><text x="50" y="47" text-anchor="middle" font-size="11" font-weight="700" fill="currentColor" font-family="Hanken Grotesk, sans-serif">STUDY</text><text x="50" y="60" text-anchor="middle" font-size="11" font-weight="700" fill="currentColor" font-family="Hanken Grotesk, sans-serif">VIBE</text></svg>
  </div>
  <div class="vf-body-card">
    <p class="vf-eyebrow"><?= $h($T['holder']) ?></p>
    <p class="vf-name"><?= $h($doc['student_name']) ?></p>
    <dl class="vf-grid">
      <div><dt><?= $h($T['course']) ?></dt><dd><?= $h($doc['course_title'] ?? '—') ?></dd></div>
      <div><dt><?= $h($T['teacher']) ?></dt><dd><?= $h($doc['teacher_name'] ?? '—') ?></dd></div>
      <div><dt><?= $h($T['issued']) ?></dt><dd><?= $h($fmtDate($doc['issued_at'])) ?></dd></div>
      <div><dt><?= $h($T['code']) ?></dt><dd><code><?= $h($doc['certificate_code']) ?></code></dd></div>
      <div class="vf-wide"><dt><?= $h($T['method']) ?></dt><dd><?= $h(!empty($doc['manual_issue']) ? $T['manual'] : $T['auto']) ?></dd></div>
    </dl>
    <?php if (isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'student' && (int)($_SESSION['user_id'] ?? 0) === (int)$doc['student_id']): ?>
      <a class="btn btn-ghost" href="/certificate.php?code=<?= urlencode((string)$doc['certificate_code']) ?>"><?= $en ? 'Open my certificate' : 'Ouvrir mon certificat' ?></a>
    <?php endif; ?>
  </div>
</section>

<?php elseif ($state === 'ok' && $kind === 'transcript' && $doc): $S = $doc['summary_data']; ?>
<section class="vf-result is-ok" aria-live="polite">
  <div class="vf-status">
    <span class="vf-ico"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
    <div><h2><?= $h($T['ok_tr']) ?></h2><p><?= $h($T['checked']) ?> <?= $h($fmtDate('now')) ?></p></div>
    <svg class="vf-seal" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="46" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="50" cy="50" r="38" fill="none" stroke="currentColor" stroke-width=".6" stroke-dasharray="1.2 2.4"/><text x="50" y="47" text-anchor="middle" font-size="11" font-weight="700" fill="currentColor" font-family="Hanken Grotesk, sans-serif">STUDY</text><text x="50" y="60" text-anchor="middle" font-size="11" font-weight="700" fill="currentColor" font-family="Hanken Grotesk, sans-serif">VIBE</text></svg>
  </div>
  <div class="vf-body-card">
    <p class="vf-eyebrow"><?= $h($T['holder']) ?></p>
    <p class="vf-name"><?= $h($doc['student_name']) ?></p>
    <dl class="vf-grid">
      <?php if (!empty($doc['matricule'])): ?><div><dt><?= $h($T['matricule']) ?></dt><dd><code><?= $h($mask($doc['matricule'])) ?></code></dd></div><?php endif; ?>
      <div><dt><?= $h($T['issued']) ?></dt><dd><?= $h($fmtDate($doc['issued_at'])) ?></dd></div>
      <div><dt><?= $h($T['code']) ?></dt><dd><code><?= $h($doc['code']) ?></code></dd></div>
      <div><dt><?= $h($T['integrity']) ?></dt><dd class="vf-good">✓ <?= $h($T['integrity_ok']) ?></dd></div>
    </dl>
    <div class="vf-stats">
      <div><b><?= (int)($S['courses'] ?? 0) ?></b><span><?= $h($T['courses']) ?></span></div>
      <div><b><?= (int)($S['lessons'] ?? 0) ?></b><span><?= $h($T['lessons']) ?></span></div>
      <div><b><?= $h($pct($S['avg_best'] ?? null)) ?></b><span><?= $h($T['avg_best']) ?></span></div>
      <div><b><?= $h($pct($S['progress_avg'] ?? null)) ?></b><span><?= $h($T['progress']) ?></span></div>
    </div>
    <p class="vf-note"><?= $h($T['tr_note']) ?></p>
  </div>
</section>
<?php endif; ?>

<?php if ($state === 'ok'): ?>
<p class="vf-actions">
  <button type="button" class="btn btn-ghost" id="vf-copy" data-url="<?= $h($verifyUrl) ?>" data-done="<?= $h($T['copied']) ?>"><?= $h($T['copy']) ?></button>
  <a class="btn btn-text" href="/verify.php"><?= $h($T['again']) ?></a>
</p>
<?php endif; ?>

<?php if ($state === 'idle'): ?>
<section class="vf-block" aria-labelledby="vf-steps-h">
  <h2 id="vf-steps-h"><?= $h($T['steps_h']) ?></h2>
  <ol class="vf-steps">
    <?php foreach ($T['steps'] as $i => [$t, $d]): ?>
      <li><span class="vf-n"><?= $i + 1 ?></span><div><h3><?= $h($t) ?></h3><p><?= $h($d) ?></p></div></li>
    <?php endforeach; ?>
  </ol>
</section>
<section class="vf-block" aria-labelledby="vf-can-h">
  <h2 id="vf-can-h"><?= $h($T['can_h']) ?></h2>
  <div class="vf-can">
    <?php foreach ($T['can'] as $i => [$t, $d]): ?>
      <article class="vf-card">
        <span class="vf-card-ico" aria-hidden="true">
          <?php if ($i === 0): ?><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5.5"/><path d="M8.5 13.5L7 21l5-2.5 5 2.5-1.5-7.5"/></svg>
          <?php else: ?><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h7"/></svg><?php endif; ?>
        </span>
        <h3><?= $h($t) ?></h3><p><?= $h($d) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

</main>

<footer class="vf-foot">
  <a href="/index.php"><?= $h($T['back']) ?></a><span aria-hidden="true">·</span><span><?= $h($T['footer']) ?></span>
</footer>

<script>
(function () {
  var b = document.getElementById('vf-copy');
  if (!b) return;
  b.addEventListener('click', function () {
    var done = function () { var o = b.textContent; b.textContent = b.dataset.done; setTimeout(function () { b.textContent = o; }, 1600); };
    if (navigator.clipboard) { navigator.clipboard.writeText(b.dataset.url).then(done, done); } else { done(); }
  });
})();
</script>
</body>
</html>
