<?php
declare(strict_types=1);

/**
 * Public form: anyone can send the PDFs of a course so the quizzes are built from the real course content.
 *
 *   GET            the form (?sent=REFERENCE shows the thank-you screen)
 *   POST multipart email, name, filiere (or __other + filiere_other), level, course, note, files[] (1 to 5 PDFs, 10 MB each),
 *                  _t (signed time the form was drawn), website (honeypot, must stay empty)
 *
 * Protection: same-site check, honeypot, minimum fill time, limits per address and per email, real PDF check on every file.
 * The files go to the super teachers' Contributions page (teacher/contributions.php). Emails go out after the answer.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Security.php';
require_once __DIR__ . '/lib/RateLimit.php';
require_once __DIR__ . '/lib/CourseMaterials.php';
require_once __DIR__ . '/lib/SiteChrome.php';

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$en = $lang === 'en';
$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$secret = defined('APP_SECRET') ? (string)APP_SECRET : 'studyvibe';
$pdo = Database::getInstance();

$L = $en ? [
    'title' => 'Send course material', 'kicker' => 'Contribute',
    'lede' => 'Send the PDFs of a course (notes, slides, past exams). The teaching team reads them and builds quizzes that follow the real course, not questions from nowhere.',
    'email' => 'Your email', 'name' => 'Your name (optional)', 'filiere' => 'Field of study', 'filiere_pick' => 'Choose your field', 'other' => 'Other (type it)',
    'filiere_other' => 'Your field', 'level' => 'Level', 'level_pick' => 'Choose', 'course' => 'Course', 'course_ph' => 'e.g. Integral calculus',
    'files' => 'PDF files', 'files_hint' => 'Up to 5 PDFs, 10 MB each at most.', 'note' => 'A note (optional)', 'note_ph' => 'e.g. chapters 1 to 4, with the 2025 exam',
    'send' => 'Send the material', 'privacy' => 'Your email is only used to tell you what became of your material.',
    'sent_h' => 'Thank you, we have it', 'sent_p' => 'Your material reached the teaching team. A confirmation was sent to your email.', 'ref' => 'Reference', 'again' => 'Send other material',
    'err_email' => 'Enter a valid email address.', 'err_filiere' => 'Choose or type your field of study.', 'err_course' => 'Write the name of the course.',
    'err_files_none' => 'Add at least one PDF.', 'err_files_many' => 'At most 5 PDFs at a time.', 'err_file_big' => '“{f}” is bigger than 10 MB.',
    'err_file_type' => '“{f}” is not a PDF.', 'err_server' => 'Something went wrong on our side. Try again in a moment.', 'err_rate' => 'Too many submissions. Try again later.',
    'err_too_big' => 'The files are too big together. Send fewer at a time.', 'err_top' => 'Please check the form.', 'js_many' => 'At most 5 PDFs.', 'js_big' => 'is bigger than 10 MB',
] : [
    'title' => 'Envoyer des supports de cours', 'kicker' => 'Contribuer',
    'lede' => 'Envoyez les PDF d’un cours (notes, diapositives, anciens sujets). L’équipe pédagogique les lit et construit des quiz fidèles au vrai cours, pas des questions tombées du ciel.',
    'email' => 'Votre adresse e-mail', 'name' => 'Votre nom (facultatif)', 'filiere' => 'Filière', 'filiere_pick' => 'Choisissez votre filière', 'other' => 'Autre (à écrire)',
    'filiere_other' => 'Votre filière', 'level' => 'Niveau', 'level_pick' => 'Choisir', 'course' => 'Cours', 'course_ph' => 'Ex. : Calcul intégral',
    'files' => 'Fichiers PDF', 'files_hint' => 'Jusqu’à 5 PDF, 10 Mo chacun au maximum.', 'note' => 'Une précision (facultatif)', 'note_ph' => 'Ex. : chapitres 1 à 4, avec le sujet de 2025',
    'send' => 'Envoyer les supports', 'privacy' => 'Votre adresse sert seulement à vous dire ce que sont devenus vos supports.',
    'sent_h' => 'Merci, c’est bien reçu', 'sent_p' => 'Vos supports sont arrivés chez l’équipe pédagogique. Une confirmation vous a été envoyée par e-mail.', 'ref' => 'Référence', 'again' => 'Envoyer d’autres supports',
    'err_email' => 'Saisissez une adresse e-mail valide.', 'err_filiere' => 'Choisissez ou écrivez votre filière.', 'err_course' => 'Écrivez le nom du cours.',
    'err_files_none' => 'Ajoutez au moins un PDF.', 'err_files_many' => '5 PDF au maximum à la fois.', 'err_file_big' => '« {f} » dépasse 10 Mo.',
    'err_file_type' => '« {f} » n’est pas un PDF.', 'err_server' => 'Un problème est survenu de notre côté. Réessayez dans un instant.', 'err_rate' => 'Trop d’envois. Réessayez plus tard.',
    'err_too_big' => 'Les fichiers sont trop lourds ensemble. Envoyez-en moins à la fois.', 'err_top' => 'Vérifiez le formulaire.', 'js_many' => '5 PDF au maximum.', 'js_big' => 'dépasse 10 Mo',
];

$errors = [];
$old = [];
$errText = static function (string $code) use ($L): string {
    [$k, $f] = array_pad(explode(':', $code, 2), 2, '');
    return str_replace('{f}', $f, $L['err_' . $k] ?? $L['err_server']);
};

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Security::requirePostFromSameSite();
    $old = $_POST;
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors['files'] = 'too_big';   // bigger than post_max_size: PHP drops everything
    } else {
        // Bots: hidden field filled, signature wrong, or sent in under three seconds. They get a normal-looking answer and nothing is stored.
        $bot = trim((string)($_POST['website'] ?? '')) !== '';
        $ts = explode('.', (string)($_POST['_t'] ?? ''), 2);
        if (count($ts) !== 2 || !ctype_digit($ts[0]) || !hash_equals(hash_hmac('sha256', 'material-form|' . $ts[0], $secret), $ts[1]) || (time() - (int)$ts[0]) < 3) {
            $bot = true;
        }
        if ($bot) {
            usleep(random_int(300000, 900000));
            header('Location: /contribute.php?sent=MAT-' . date('y') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)), true, 303);
            exit;
        }
        $ipHash = hash('sha256', Security::clientIp());
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!RateLimit::allow($pdo, 'material:ip:' . $ipHash, 6, 3600)
            || ($email !== '' && !RateLimit::allow($pdo, 'material:mail:' . hash('sha256', $email), 5, 86400))) {
            http_response_code(429);
            $errors['top'] = 'rate';
        } else {
            $res = CourseMaterials::submit($pdo, $_POST, CourseMaterials::normalizeFiles($_FILES['files'] ?? null), $ipHash, $lang);
            if ($res['ok']) {
                $id = (int)$res['id'];
                // Answer first; the mail server is slow and must not hold the person's screen.
                register_shutdown_function(static function () use ($id): void {
                    try {
                        if (function_exists('fastcgi_finish_request')) {
                            fastcgi_finish_request();
                        }
                        require_once __DIR__ . '/Mailer.php';
                        require_once __DIR__ . '/lib/Notifications.php';
                        $pdo = Database::getInstance();
                        $s = CourseMaterials::find($pdo, $id);
                        if (!$s) {
                            return;
                        }
                        $n = count($s['files']);
                        Mailer::materialReceived($s, $n);
                        foreach (CourseMaterials::reviewers($pdo) as $r) {
                            Notifications::mirror($pdo, (int)$r['id'], 'material', 'Nouveaux supports : ' . $s['course_label'], $n . ' PDF · ' . $s['filiere'], '/teacher/contributions.php?id=' . $id);
                            Mailer::materialToReview((string)$r['email'], (string)$r['name'], $s, $n);
                        }
                    } catch (Throwable $e) {
                        error_log('[materials] after submission ' . $id . ': ' . $e->getMessage());
                    }
                });
                header('Location: /contribute.php?sent=' . rawurlencode((string)$res['reference']), true, 303);
                exit;
            }
            $errors = $res['errors'];
            http_response_code(isset($errors['server']) ? 500 : 422);
        }
    }
}

$sent = preg_match('/^MAT-[0-9A-Z]{4,10}$/', (string)($_GET['sent'] ?? '')) ? (string)$_GET['sent'] : '';
$filieres = CourseMaterials::filieres($pdo);
$courses = $pdo->query("SELECT DISTINCT title FROM courses WHERE is_published = 1 ORDER BY title LIMIT 400")->fetchAll(PDO::FETCH_COLUMN);
$now = time();
$stamp = $now . '.' . hash_hmac('sha256', 'material-form|' . $now, $secret);
$v = static fn(string $k) => $h($old[$k] ?? '');
$oldFiliere = (string)($old['filiere'] ?? '');
$fieldErr = static function (string $k) use ($errors, $errText, $h): string {
    return isset($errors[$k]) ? '<p class="mt-err" id="err-' . $k . '" role="alert">' . $h($errText($errors[$k])) . '</p>' : '';
};
$inv = static fn(string $k) => isset($errors[$k]) ? ' invalid" aria-invalid="true" aria-describedby="err-' . $k : '';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<?= SiteChrome::head(['title' => $L['title'] . ' — StudyVibe', 'desc' => $L['lede']]) ?>
<style>
  .mt-wrap { max-width: 44rem; margin: 0 auto; padding: 2.5rem 1rem 4rem; }
  .mt-kicker { color: var(--clay); font-weight: 600; letter-spacing: .06em; text-transform: uppercase; font-size: .8rem; margin: 0; }
  .mt-wrap h1 { font: 450 clamp(1.8rem, 5vw, 2.5rem)/1.12 var(--font-display, 'Fraunces', serif); margin: .4rem 0 .6rem; }
  .mt-lede { color: var(--ink-2); margin: 0 0 1.75rem; font-size: 1.05rem; }
  .mt-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 1.5rem; }
  .mt-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
  @media (max-width: 560px) { .mt-row { grid-template-columns: 1fr; } .mt-card { padding: 1.1rem; } }
  .mt-hint { color: var(--ink-3); font-size: .85rem; margin: 0; }
  .mt-err { color: var(--danger, #B42318); font-size: .88rem; margin: 0; }
  .mt-top { background: color-mix(in srgb, var(--danger, #B42318) 10%, var(--card)); border-radius: 10px; padding: .75rem 1rem; margin: 0 0 1rem; }
  .mt-drop { border: 1.5px dashed var(--line-2); border-radius: 12px; padding: 1rem; background: var(--paper); }
  .mt-drop input { width: 100%; }
  .mt-list { list-style: none; margin: .6rem 0 0; padding: 0; font-size: .9rem; }
  .mt-list li { display: flex; justify-content: space-between; gap: 1rem; padding: .3rem 0; border-bottom: 1px solid var(--line); }
  .mt-list li.bad { color: var(--danger, #B42318); }
  .mt-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
  .mt-done { text-align: center; }
  .mt-done .ref { font: 600 1.4rem/1.2 ui-monospace, monospace; letter-spacing: .05em; margin: .5rem 0 1.25rem; }
  textarea.input { min-height: 5.5rem; resize: vertical; }
</style>
</head>
<body class="v2">
<?= SiteChrome::header($lang) ?>
<main id="main" class="mt-wrap">
<?php if ($sent !== ''): ?>
  <div class="mt-card mt-done" role="status">
    <p class="mt-kicker"><?= $h($L['kicker']) ?></p>
    <h1><?= $h($L['sent_h']) ?></h1>
    <p class="mt-lede"><?= $h($L['sent_p']) ?></p>
    <p class="mt-hint"><?= $h($L['ref']) ?></p>
    <p class="ref"><?= $h($sent) ?></p>
    <a class="btn btn-ghost" href="/contribute.php"><?= $h($L['again']) ?></a>
  </div>
<?php else: ?>
  <p class="mt-kicker"><?= $h($L['kicker']) ?></p>
  <h1><?= $h($L['title']) ?></h1>
  <p class="mt-lede"><?= $h($L['lede']) ?></p>

  <form class="mt-card" method="post" action="/contribute.php" enctype="multipart/form-data" novalidate id="mt-form">
    <?php if ($errors): ?><p class="mt-top" role="alert"><?= $h(isset($errors['top']) ? $errText($errors['top']) : (isset($errors['server']) ? $errText('server') : $L['err_top'])) ?></p><?php endif; ?>
    <input type="hidden" name="_t" value="<?= $h($stamp) ?>">
    <div class="mt-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <div class="mt-row">
      <div class="field"><label for="mt-email"><?= $h($L['email']) ?></label>
        <input class="input<?= $inv('email') ?>" type="email" id="mt-email" name="email" required maxlength="190" autocomplete="email" value="<?= $v('email') ?>"><?= $fieldErr('email') ?></div>
      <div class="field"><label for="mt-name"><?= $h($L['name']) ?></label>
        <input class="input" type="text" id="mt-name" name="name" maxlength="120" autocomplete="name" value="<?= $v('name') ?>"></div>
    </div>

    <div class="mt-row">
      <div class="field"><label for="mt-filiere"><?= $h($L['filiere']) ?></label>
        <?php if ($filieres): ?>
          <select class="input<?= $inv('filiere') ?>" id="mt-filiere" name="filiere" required>
            <option value=""><?= $h($L['filiere_pick']) ?></option>
            <?php foreach ($filieres as $f): ?><option value="<?= $h($f['name']) ?>" <?= $oldFiliere === $f['name'] ? 'selected' : '' ?>><?= $h($f['name']) ?></option><?php endforeach; ?>
            <option value="__other" <?= $oldFiliere === '__other' ? 'selected' : '' ?>><?= $h($L['other']) ?></option>
          </select>
          <input class="input" type="text" id="mt-filiere-other" name="filiere_other" maxlength="120" placeholder="<?= $h($L['filiere_other']) ?>" aria-label="<?= $h($L['filiere_other']) ?>" value="<?= $v('filiere_other') ?>" <?= $oldFiliere === '__other' ? '' : 'hidden' ?>>
        <?php else: ?>
          <input class="input<?= $inv('filiere') ?>" type="text" id="mt-filiere" name="filiere" required maxlength="120" value="<?= $v('filiere') ?>">
        <?php endif; ?>
        <?= $fieldErr('filiere') ?></div>
      <div class="field"><label for="mt-level"><?= $h($L['level']) ?></label>
        <select class="input" id="mt-level" name="level">
          <option value=""><?= $h($L['level_pick']) ?></option>
          <?php foreach (CourseMaterials::LEVELS as $lv): ?><option value="<?= $h($lv) ?>" <?= ($old['level'] ?? '') === $lv ? 'selected' : '' ?>><?= $h($lv === 'Autre' && $en ? 'Other' : $lv) ?></option><?php endforeach; ?>
        </select></div>
    </div>

    <div class="field"><label for="mt-course"><?= $h($L['course']) ?></label>
      <input class="input<?= $inv('course') ?>" type="text" id="mt-course" name="course" required maxlength="190" list="mt-courses" placeholder="<?= $h($L['course_ph']) ?>" value="<?= $v('course') ?>">
      <datalist id="mt-courses"><?php foreach ($courses as $c): ?><option value="<?= $h($c) ?>"><?php endforeach; ?></datalist>
      <?= $fieldErr('course') ?></div>

    <div class="field"><label for="mt-files"><?= $h($L['files']) ?></label>
      <div class="mt-drop">
        <input type="file" id="mt-files" name="files[]" accept="application/pdf,.pdf" multiple required aria-describedby="mt-files-hint<?= isset($errors['files']) ? ' err-files' : '' ?>">
        <ul class="mt-list" id="mt-list" aria-live="polite"></ul>
      </div>
      <p class="mt-hint" id="mt-files-hint"><?= $h($L['files_hint']) ?></p>
      <?= $fieldErr('files') ?></div>

    <div class="field"><label for="mt-note"><?= $h($L['note']) ?></label>
      <textarea class="input" id="mt-note" name="note" maxlength="2000" placeholder="<?= $h($L['note_ph']) ?>"><?= $v('note') ?></textarea></div>

    <button class="btn btn-primary btn-lg" type="submit" id="mt-send" style="width:100%"><?= $h($L['send']) ?></button>
    <p class="mt-hint" style="margin-top:.75rem;text-align:center"><?= $h($L['privacy']) ?></p>
  </form>
<?php endif; ?>
</main>
<?= SiteChrome::footer($lang) ?>
<?= SiteChrome::scripts() ?>
<script>
(function () {
  var sel = document.getElementById('mt-filiere'), other = document.getElementById('mt-filiere-other');
  if (sel && other && sel.tagName === 'SELECT') {
    sel.addEventListener('change', function () { other.hidden = sel.value !== '__other'; if (!other.hidden) other.focus(); });
  }
  var input = document.getElementById('mt-files'), list = document.getElementById('mt-list'), send = document.getElementById('mt-send');
  if (!input) return;
  var MAX = <?= CourseMaterials::MAX_FILES ?>, BYTES = <?= CourseMaterials::MAX_BYTES ?>, msgMany = <?= json_encode($L['js_many'], JSON_UNESCAPED_UNICODE) ?>, msgBig = <?= json_encode($L['js_big'], JSON_UNESCAPED_UNICODE) ?>;
  input.addEventListener('change', function () {
    list.innerHTML = ''; var bad = input.files.length > MAX;
    Array.prototype.forEach.call(input.files, function (f) {
      var li = document.createElement('li'), big = f.size > BYTES; if (big) bad = true;
      li.className = big ? 'bad' : '';
      var a = document.createElement('span'); a.textContent = f.name + (big ? ' · ' + msgBig : '');
      var b = document.createElement('span'); b.textContent = (f.size / 1048576).toFixed(1) + ' Mo';
      li.appendChild(a); li.appendChild(b); list.appendChild(li);
    });
    if (input.files.length > MAX) { var li = document.createElement('li'); li.className = 'bad'; li.textContent = msgMany; list.appendChild(li); }
    send.disabled = bad;
  });
  document.getElementById('mt-form').addEventListener('submit', function () { send.disabled = true; });
})();
</script>
</body>
</html>
