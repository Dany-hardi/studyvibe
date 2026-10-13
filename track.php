<?php
declare(strict_types=1);

/**
 * The candidate's own page (?t=PERSONAL_TOKEN): where the application stands, interview details with the answer buttons, a calendar file,
 * the visible history, and the choices to withdraw or to erase everything. Without a token: a form that e-mails the link.
 * The token (160 random bits) is the key: the page is never indexed, never cached, and sends no referrer. Text: locales/track.php.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/SiteChrome.php';
require_once __DIR__ . '/lib/Careers.php';
require_once __DIR__ . '/lib/CareersInterviews.php';
require_once __DIR__ . '/lib/CareersArt.php';
require_once __DIR__ . '/lib/CareersWorker.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$L = (require __DIR__ . '/locales/track.php')[$lang];
$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$token = strtolower(trim((string)($_GET['t'] ?? '')));
$app = null;
try {
    $pdo = Database::getInstance();
    CareersWorker::tick($pdo);
    $app = $token !== '' ? Careers::byToken($pdo, $token) : null;
} catch (Throwable $e) {
    logServerError($e, 'track');
}

// The calendar file of one interview
if ($app && isset($_GET['ics'])) {
    $iv = CareersInterviews::get($pdo, (int)$_GET['ics']);
    if ($iv && (int)$iv['application_id'] === (int)$app['id']) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="interview-' . (int)$iv['id'] . '.ics"');
        echo CareersInterviews::ics($iv, $app, $lang);
        exit;
    }
    http_response_code(404);
    exit;
}

$months = $lang === 'en' ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] : ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$days = $lang === 'en' ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] : ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$date = static function (?string $d, bool $time = false) use ($months): string {
    $t = $d ? strtotime($d) : false;
    return $t ? (int)date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ' ' . date('Y', $t) . ($time ? ' · ' . date('H:i', $t) : '') : '';
};
$long = static function (string $d) use ($months, $days): string { $t = strtotime($d); return ucfirst($days[(int)date('w', $t)]) . ' ' . (int)date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ' ' . date('Y', $t); };

$status = $app ? (string)$app['status'] : '';
$stepOf = ['new' => 0, 'reviewing' => 1, 'shortlisted' => 2, 'interview' => 3, 'offer' => 4, 'hired' => 5, 'rejected' => 5, 'withdrawn' => -1];
$cur = $stepOf[$status] ?? 0;
$trail = $interviews = [];
$active = null;
if ($app) {
    $trail = Careers::publicTrail($pdo, (int)$app['id']);
    $interviews = $pdo->prepare('SELECT * FROM career_interviews WHERE application_id = :a ORDER BY scheduled_at DESC');
    $interviews->execute(['a' => $app['id']]);
    $interviews = $interviews->fetchAll(PDO::FETCH_ASSOC);
    foreach ($interviews as $iv) {
        if (in_array($iv['status'], CareersInterviews::OPEN, true) || $iv['status'] === 'declined') {
            $active = $iv;
            break;
        }
    }
}
$role = $app ? ($app['job_title'] ?: $L['spont']) : '';
$sent = isset($_GET['sent']);
$pageTitle = $L['title'];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<?= SiteChrome::head(['title' => $pageTitle, 'desc' => $L['desc'], 'css' => ['/assets/css/careers.css', '/assets/css/track.css'], 'robots' => 'noindex, nofollow']) ?>
</head>
<body class="v2 ca-body tk-body" id="top">
<?= SiteChrome::header($lang, 'careers') ?>

<main id="main">
<?php if (!$app): ?>
  <section class="tk-lookup">
    <div class="ca-wrap tk-lookup-in">
      <div>
        <p class="kicker"><?= $h($L['kicker']) ?></p>
        <h1><?= $L['h1'] ?></h1>
        <p class="ca-lede"><?= $h($L['lede']) ?></p>
        <?php if ($token !== ''): ?><p class="tk-warn" role="alert"><?= $h($L['bad_link']) ?></p><?php endif; ?>
        <form class="tk-form" id="tk-lookup" novalidate>
          <div class="field"><label for="tk-ref"><?= $h($L['l_ref']) ?></label><input class="input" id="tk-ref" name="reference" autocomplete="off" autocapitalize="characters" maxlength="16" spellcheck="false" placeholder="<?= $h($L['ph_ref']) ?>" required></div>
          <div class="field"><label for="tk-email"><?= $h($L['l_email']) ?></label><input class="input" id="tk-email" name="email" type="email" autocomplete="email" maxlength="190" required></div>
          <p class="ca-err" id="tk-err" role="alert"></p>
          <button class="btn btn-primary btn-lg" type="submit" id="tk-go"><?= $h($L['send']) ?></button>
        </form>
        <div class="tk-sent" id="tk-sent" hidden><h2><?= $h($L['sent_h']) ?></h2><p><?= $h($L['sent_p']) ?></p></div>
      </div>
      <div class="tk-art"><?= CareersArt::sent('') ?></div>
    </div>
  </section>
<?php else:
    $final = $cur === 5 || $status === 'withdrawn';
    $isHired = $status === 'hired';
    $stageText = $L['stage'][$status] ?? $L['stage']['new'];
?>
  <section class="tk-hero">
    <div class="ca-wrap">
      <p class="kicker"><?= $h($L['kicker']) ?></p>
      <h1><?= $h(str_replace('{name}', explode(' ', trim((string)$app['full_name']))[0] ?? '', $L['hello'])) ?></h1>
      <p class="tk-sub"><?= $h(str_replace('{role}', $role, $L['for_role'])) ?></p>
      <ul class="tk-meta">
        <li><?= $h($L['ref']) ?> <b class="tk-mono"><?= $h($app['reference']) ?></b></li>
        <li><?= $h(str_replace('{date}', $date($app['created_at']), $L['applied'])) ?></li>
        <li><?= $h(str_replace('{date}', $date($app['status_changed_at'] ?: $app['created_at']), $L['updated'])) ?></li>
      </ul>
    </div>
  </section>

  <div class="ca-wrap tk-grid">
    <div class="tk-main">
      <?php if ($status === 'withdrawn'): ?>
        <div class="tk-banner tk-banner-mute"><?= $h($L['stage']['withdrawn'][1]) ?></div>
      <?php else: ?>
      <ol class="tk-steps" aria-label="<?= $h($L['now']) ?>">
        <?php foreach ($L['steps'] as $i => $label):
            $state = $i < $cur ? 'done' : ($i === $cur ? 'now' : 'todo');
            if ($i === 5) { $label = $status === 'hired' ? $L['final_hired'] : ($status === 'rejected' ? $L['final_rejected'] : $label); }
            $cls = 'tk-step tk-' . $state . ($i === 5 && $status === 'rejected' ? ' tk-bad' : '') . ($i === 5 && $status === 'hired' ? ' tk-good' : ''); ?>
          <li class="<?= $cls ?>" <?= $state === 'now' ? 'aria-current="step"' : '' ?>>
            <span class="tk-dot"><?php if ($state === 'done' || ($i === 5 && $status === 'hired')): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg><?php else: ?><?= $i + 1 ?><?php endif; ?></span>
            <span class="tk-label"><?= $h($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>

      <section class="tk-card tk-stage <?= $isHired ? 'tk-card-good' : '' ?>">
        <p class="tk-eyebrow"><?= $h($L['now']) ?></p>
        <h2><?= $h($stageText[0]) ?></h2>
        <p><?= $h($stageText[1]) ?></p>
      </section>

      <?php if ($active):
        $t = strtotime((string)$active['scheduled_at']);
        $open = in_array($active['status'], CareersInterviews::OPEN, true) && $t > time();
        $where = (string)$active['location'];
        $isUrl = Careers::url($where) !== '';
        $gcal = CareersInterviews::googleLink($active, $app, $lang);
        $icsUrl = '/track.php?t=' . $token . '&ics=' . (int)$active['id']; ?>
      <section class="tk-card tk-iv" id="interview" data-iv="<?= (int)$active['id'] ?>">
        <p class="tk-eyebrow"><?= $h($L['iv_h']) ?></p>
        <div class="tk-iv-head">
          <div class="tk-tile"><b><?= $h(mb_strtoupper($months[(int)date('n', $t) - 1])) ?></b><span><?= (int)date('j', $t) ?></span></div>
          <div>
            <p class="tk-iv-date"><?= $h($long((string)$active['scheduled_at'])) ?></p>
            <p class="tk-iv-time"><?= date('H:i', $t) ?> – <?= date('H:i', CareersInterviews::endsAt($active)) ?> <small><?= $h(date('T') . ' (UTC' . date('P') . ')') ?></small></p>
            <p class="tk-pill tk-iv-<?= $h($active['status']) ?>"><?= $h($L['iv_status'][$active['status']] ?? $active['status']) ?></p>
          </div>
        </div>
        <dl class="tk-dl">
          <div><dt><?= $h($L['iv_format']) ?></dt><dd><?= $h($L['modes'][$active['mode']] ?? '') ?> · <?= (int)$active['duration_min'] ?> <?= $h($L['iv_min']) ?></dd></div>
          <div><dt><?= $h($L['iv_where']) ?></dt><dd><?php if ($where === ''): ?><?= $h($active['mode'] === 'video' ? $L['iv_link_later'] : '—') ?><?php elseif ($isUrl): ?><a href="<?= $h($where) ?>" target="_blank" rel="noopener noreferrer"><?= $h($where) ?></a><?php else: ?><?= $h($where) ?><?php endif; ?></dd></div>
          <?php if ($active['interviewer'] !== ''): ?><div><dt><?= $h($L['iv_who']) ?></dt><dd><?= $h($active['interviewer']) ?></dd></div><?php endif; ?>
          <?php if (!empty($active['instructions'])): ?><div><dt><?= $h($L['iv_notes']) ?></dt><dd><?= nl2br($h($active['instructions'])) ?></dd></div><?php endif; ?>
        </dl>
        <?php if ($active['candidate_note']): ?><p class="tk-note-mine">“<?= $h($active['candidate_note']) ?>”</p><?php endif; ?>
        <?php if ($open): ?>
          <p class="tk-cal"><?= $h($L['iv_cal']) ?> : <a href="<?= $h($gcal) ?>" target="_blank" rel="noopener"><?= $h($L['iv_gcal']) ?></a> · <a href="<?= $h($icsUrl) ?>"><?= $h($L['iv_ics']) ?></a></p>
          <div class="tk-iv-act" id="tk-iv-act">
            <?php if ($active['status'] !== 'confirmed'): ?><button class="btn btn-primary" type="button" data-resp="confirm"><?= $h($L['iv_confirm']) ?></button><?php endif; ?>
            <button class="btn btn-ghost" type="button" data-resp="reschedule"><?= $h($L['iv_resched']) ?></button>
            <button class="btn btn-ghost tk-danger-b" type="button" data-resp="decline"><?= $h($L['iv_decline']) ?></button>
          </div>
          <form class="tk-resp-form" id="tk-resp-form" hidden novalidate>
            <label for="tk-note"><?= $h($L['iv_note']) ?></label>
            <textarea class="input" id="tk-note" rows="3" maxlength="500" placeholder="<?= $h($L['iv_note_ph']) ?>"></textarea>
            <p class="ca-err" id="tk-resp-err" role="alert"></p>
            <div class="tk-form-a"><button class="btn btn-primary" type="submit" id="tk-resp-go"><?= $h($L['iv_send']) ?></button><button class="btn btn-ghost" type="button" id="tk-resp-x"><?= $h($L['iv_cancel']) ?></button></div>
          </form>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if ($trail): ?>
      <section class="tk-card">
        <h2 class="tk-h2"><?= $h($L['trail_h']) ?></h2>
        <ol class="tk-trail">
          <?php foreach (array_reverse($trail) as $e):
              $txt = '';
              $b = json_decode((string)$e['body'], true);
              if ($e['kind'] === 'status') { $txt = $e['to_status'] === 'new' ? $L['ev']['received'] : str_replace('{stage}', $L['stagename'][$e['to_status']] ?? $e['to_status'], $L['ev']['to']); }
              elseif ($e['kind'] === 'interview') { $k = 'iv_' . $e['to_status']; $txt = str_replace('{when}', isset($b['when']) ? $date($b['when'], true) : '', $L['ev'][$k] ?? ''); }
              elseif ($e['kind'] === 'candidate') { $txt = $L['ev']['cand_' . $e['to_status']] ?? ''; }
              if ($txt === '') { continue; } ?>
            <li><span class="tk-tdot"></span><div><p><?= $h($txt) ?></p><small><?= $h($date($e['created_at'], true)) ?></small></div></li>
          <?php endforeach; ?>
        </ol>
      </section>
      <?php endif; ?>
    </div>

    <aside class="tk-side">
      <section class="tk-card">
        <h2 class="tk-h2"><?= $h($L['mine_h']) ?></h2>
        <dl class="tk-dl tk-dl-s">
          <div><dt><?= $h($L['mine_email']) ?></dt><dd><?= $h($app['email']) ?></dd></div>
          <?php if ($app['resume_name']): ?><div><dt><?= $h($L['mine_resume']) ?></dt><dd><?= $h($app['resume_name']) ?></dd></div><?php endif; ?>
          <div><dt><?= $h($L['mine_pool']) ?></dt><dd><?= $h((int)$app['talent_consent'] ? $L['yes'] : $L['no']) ?></dd></div>
        </dl>
        <p class="tk-privacy"><?= $L['privacy'] ?></p>
      </section>
      <section class="tk-card">
        <h2 class="tk-h2"><?= $h($L['danger_h']) ?></h2>
        <div class="tk-choices">
          <?php if (!in_array($status, ['withdrawn', 'hired', 'rejected'], true)): ?><button class="btn btn-ghost" type="button" data-dlg="withdraw"><?= $h($L['withdraw']) ?></button><?php endif; ?>
          <button class="btn btn-ghost tk-danger-b" type="button" data-dlg="erase"><?= $h($L['erase']) ?></button>
        </div>
      </section>
    </aside>
  </div>

  <?php foreach (['withdraw', 'erase'] as $d): ?>
  <dialog class="tk-dialog" id="dlg-<?= $d ?>" aria-labelledby="dlg-<?= $d ?>-t">
    <form method="dialog">
      <h2 id="dlg-<?= $d ?>-t"><?= $h($L[$d . '_h']) ?></h2>
      <p><?= $h($L[$d . '_p']) ?></p>
      <p class="ca-err" data-dlg-err></p>
      <div class="tk-form-a"><button class="btn btn-ghost" value="cancel"><?= $h($L['cancel']) ?></button><button class="btn <?= $d === 'erase' ? 'tk-btn-danger' : 'btn-primary' ?>" value="ok" data-dlg-ok="<?= $d ?>"><?= $h($L[$d . '_ok']) ?></button></div>
    </form>
  </dialog>
  <?php endforeach; ?>
<?php endif; ?>
</main>

<?= SiteChrome::footer($lang) ?>
<script>window.TK = <?= json_encode(['t' => $app ? $token : '', 'L' => ['err' => $L['err'], 'rate' => $L['rate'], 'invalid' => $L['invalid'], 'note_req' => $L['iv_note_req'], 'answered' => $L['iv_answered'], 'closed' => $L['iv_closed'], 'erased_h' => $L['erased_h'], 'erased_p' => $L['erased_p'], 'back' => $L['back']]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?= SiteChrome::scripts() ?>
<script src="/assets/js/track.js" defer></script>
</body>
</html>
