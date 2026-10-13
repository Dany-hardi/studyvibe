<?php
declare(strict_types=1);

/**
 * One position (?slug=...) with its application form, or the open application (?slug=spontaneous).
 * The form posts to career-apply.php. A signed-in administrator can preview a posting that is not public yet.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/SiteChrome.php';
require_once __DIR__ . '/lib/Careers.php';
require_once __DIR__ . '/lib/CareersArt.php';
require_once __DIR__ . '/lib/CareersWorker.php';
require_once __DIR__ . '/lib/Analytics.php';

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$L = (require __DIR__ . '/locales/careers.php')[$lang];
$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$months = $lang === 'en'
    ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    : ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$date = static function (?string $d) use ($months): string {
    $t = $d ? strtotime($d) : false;
    return $t ? (int)date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ' ' . date('Y', $t) : '';
};

$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
$spont = $slug === Careers::SPONTANEOUS;
$job = null;
$isAdmin = isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'promoter';
if (!$spont) {
    try {
        $job = Careers::jobBySlug(Database::getInstance(), $slug);
    } catch (Throwable $e) {
        logServerError($e, 'career.page');
    }
    // A draft is only for the people who write it.
    if ($job && $job['status'] === 'draft' && !$isAdmin) {
        $job = null;
    }
}
Analytics::captureSource();
Analytics::hit('view:career');

if (!$spont && !$job) {
    http_response_code(404);
}
$open = $spont || ($job && Careers::isOpen($job));
$preview = $job && !$open && $isAdmin && $job['status'] !== 'closed';

$title = $spont ? $L['spont_title'] : ($job['title'] ?? $L['not_found_h']);
$now = time();
$formTs = $now . '.' . hash_hmac('sha256', 'career-form|' . $now, defined('APP_SECRET') ? (string)APP_SECRET : 'studyvibe');
// Without JavaScript the endpoint redirects back here with the reference
$sentRef = preg_match('/^CA-[A-Z0-9]{4,8}$/', (string)($_GET['sent'] ?? '')) ? (string)$_GET['sent'] : '';
$ico = [
    'pin'   => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>',
    'clock' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'home'  => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>',
];
$jsL = ['err' => $L['err'], 'submit' => $L['submit'], 'submitting' => $L['submitting'], 'copied' => $L['copied'], 'okP' => $L['ok_p'],
        'maxBytes' => Careers::RESUME_MAX, 'ext' => Careers::RESUME_EXT, 'drop_change' => $L['drop_change']];

/** One labelled field of the application form. $id = the control's id (for the label), $extra = classes, $hint = small text. */
function caField(string $name, string $label, string $control, bool $required = false, string $id = '', string $hint = '', string $extra = ''): string
{
    $h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $id = $id !== '' ? $id : 'f-name';
    return '<div class="field ' . $h($extra) . '"><label for="' . $h($id) . '"><span>' . $h($label) . ($required ? ' <span class="ca-req" aria-hidden="true">*</span>' : '') . '</span>'
         . ($hint !== '' ? '<span class="ca-hint">' . $h($hint) . '</span>' : '') . '</label>' . $control . '<p class="ca-err" data-err="' . $h($name) . '"></p></div>';
}
/** <option>s from [value => label]. */
function caOptions(array $map): string
{
    $o = '';
    foreach ($map as $v => $l) { $o .= '<option value="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string)$l, ENT_QUOTES, 'UTF-8') . '</option>'; }
    return $o;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<?= SiteChrome::head([
    'title' => $title . ' — StudyVibe',
    'desc' => $spont ? $L['spont_lede'] : ($job ? ($job['summary'] ?: $L['desc']) : $L['not_found_p']),
    'css' => ['/assets/css/careers.css'],
    'robots' => (!$job && !$spont) || $preview ? 'noindex' : '',
]) ?>
</head>
<body class="v2 ca-body" id="top">
<?= SiteChrome::header($lang, 'careers') ?>

<main id="main">
<?php if (!$spont && !$job): ?>
  <section class="ca-sec">
    <div class="ca-wrap">
      <div class="ca-empty">
        <?= CareersArt::empty($L['not_found_h']) ?>
        <div>
          <h1 style="font-size:1.9rem;font-weight:450"><?= $h($L['not_found_h']) ?></h1>
          <p><?= $h($L['not_found_p']) ?></p>
          <a class="btn btn-primary" href="/careers.php#roles"><?= $h($L['closed_cta']) ?></a>
        </div>
      </div>
    </div>
  </section>
<?php else: ?>
  <div class="ca-wrap">
    <nav class="ca-crumbs" aria-label="Breadcrumb">
      <a href="/index.php"><?= $h($L['crumb_home']) ?></a><span aria-hidden="true">/</span>
      <a href="/careers.php"><?= $h($L['crumb_careers']) ?></a><span aria-hidden="true">/</span>
      <span aria-current="page"><?= $h($title) ?></span>
    </nav>
    <?php if ($preview): ?>
      <p class="ca-preview" role="status"><?= $h(str_replace('{status}', $L['statuses'][$job['status']] ?? $job['status'], $L['preview'])) ?></p>
    <?php endif; ?>

    <header class="ca-jhead">
      <p class="kicker"><?= $h($spont ? $L['crumb_careers'] : ($job['department'] !== '' ? $job['department'] : $L['crumb_careers'])) ?></p>
      <h1><?= $h($title) ?></h1>
      <?php if ($spont): ?>
        <p class="ca-lede"><?= $h($L['spont_lede']) ?></p>
      <?php else: ?>
        <?php if ($job['summary'] !== ''): ?><p class="ca-lede"><?= $h($job['summary']) ?></p><?php endif; ?>
        <ul class="ca-tags">
          <?php if ($job['location'] !== ''): ?><li class="ca-tag"><?= $ico['pin'] ?><?= $h($job['location']) ?></li><?php endif; ?>
          <li class="ca-tag"><?= $ico['home'] ?><?= $h($L['modes'][$job['work_mode']] ?? '') ?></li>
          <li class="ca-tag"><?= $ico['clock'] ?><?= $h($L['types'][$job['employment_type']] ?? '') ?></li>
          <?php if ($job['level'] !== ''): ?><li class="ca-tag"><?= $h($job['level']) ?></li><?php endif; ?>
        </ul>
      <?php endif; ?>
      <div class="ca-jhead-row">
        <?php if ($open): ?><a class="btn btn-primary btn-lg" href="#apply"><?= $h($spont ? $L['form_h'] : $L['apply_btn']) ?></a><?php endif; ?>
        <button type="button" class="ca-share" id="ca-share" data-copied="<?= $h($L['copied']) ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
          <span><?= $h($L['share']) ?></span>
        </button>
      </div>
    </header>

    <?php if ($job): ?>
    <div class="ca-jlayout">
      <article class="ca-prose">
        <?php $paras = Careers::paragraphs($job['description']); if ($paras): ?>
          <h2><?= $h($L['about_role']) ?></h2>
          <?php foreach ($paras as $p): ?><p><?= nl2br($h($p)) ?></p><?php endforeach; ?>
        <?php endif; ?>
        <?php foreach ([['responsibilities', 'resp_h', 'ca-list'], ['requirements', 'req_h', 'ca-list'], ['nice_to_have', 'nice_h', 'ca-list ca-list-soft'], ['benefits', 'ben_h', 'ca-list']] as [$field, $key, $cls]):
            $items = Careers::items($job[$field]); if (!$items) { continue; } ?>
          <h2><?= $h($L[$key]) ?></h2>
          <ul class="<?= $cls ?>"><?php foreach ($items as $it): ?><li><?= $h($it) ?></li><?php endforeach; ?></ul>
        <?php endforeach; ?>
      </article>

      <aside class="ca-aside" aria-label="<?= $h($L['facts_h']) ?>">
        <?php if ($open): ?>
        <div class="ca-card">
          <h2><?= $h($L['apply_card_h']) ?></h2>
          <p><?= $h($L['apply_card_p']) ?></p>
          <a class="btn btn-primary" href="#apply"><?= $h($L['apply_btn']) ?></a>
        </div>
        <?php endif; ?>
        <div class="ca-card">
          <h2><?= $h($L['facts_h']) ?></h2>
          <dl class="ca-facts">
            <?php $facts = [
                [$L['fact_team'], $job['department']], [$L['fact_place'], $job['location']], [$L['fact_mode'], $L['modes'][$job['work_mode']] ?? ''],
                [$L['fact_type'], $L['types'][$job['employment_type']] ?? ''], [$L['fact_level'], $job['level']],
                [$L['fact_openings'], (int)$job['openings'] > 1 ? (string)(int)$job['openings'] : ''], [$L['fact_salary'], $job['salary_note']],
                [$L['fact_deadline'], $date($job['closes_at'])], [$L['fact_posted'], $date($job['published_at'])],
            ];
            foreach ($facts as [$k, $v]) { if ($v === '' || $v === null) { continue; } ?>
              <div><dt><?= $h($k) ?></dt><dd><?= $h($v) ?></dd></div>
            <?php } ?>
          </dl>
        </div>
      </aside>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!$open): ?>
    <div class="ca-wrap">
      <div class="ca-closed">
        <?= CareersArt::empty('') ?>
        <div>
          <h2><?= $h($L['closed_h']) ?></h2>
          <p><?= $h($L['closed_p']) ?></p>
          <a class="btn btn-primary" href="/careers.php#roles"><?= $h($L['closed_cta']) ?></a>
        </div>
      </div>
    </div>
    <div style="height:96px"></div>
  <?php else: ?>
  <section class="ca-apply" id="apply" aria-labelledby="ca-form-h">
    <div class="ca-wrap" style="max-width:900px">
      <div class="ca-form-card">
        <div id="ca-form-wrap"<?= $sentRef !== '' ? ' hidden' : '' ?>>
          <header>
            <h2 id="ca-form-h"><?= $h($L['form_h']) ?><?= $spont ? '' : ' — ' . $h($job['title']) ?></h2>
            <p><?= $h($spont ? $L['spont_what'] : $L['form_p']) ?><?= $spont ? ' ' . $h($L['form_p']) : '' ?></p>
          </header>

          <form id="ca-form" action="/career-apply.php" method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="slug" value="<?= $h($spont ? Careers::SPONTANEOUS : $job['slug']) ?>">
            <input type="hidden" name="_t" value="<?= $h($formTs) ?>">
            <input type="hidden" name="source" value="<?= $h(preg_replace('/[^a-z0-9_.-]/', '', strtolower((string)($_GET['src'] ?? ''))) ?: '') ?>">
            <div class="ca-hp" aria-hidden="true"><label>Website <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>

            <fieldset class="ca-fs">
              <legend><?= $h($L['s_you']) ?></legend>
              <div class="ca-grid">
                <?= caField('full_name', $L['l_name'], '<input class="input" id="f-name" name="full_name" type="text" autocomplete="name" maxlength="120" required placeholder="' . $h($L['ph_name']) . '">', true) ?>
                <?= caField('email', $L['l_email'], '<input class="input" id="f-email" name="email" type="email" autocomplete="email" inputmode="email" maxlength="190" required placeholder="' . $h($L['ph_email']) . '">', true, 'f-email') ?>
                <?= caField('phone', $L['l_phone'], '<input class="input" id="f-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="40" placeholder="' . $h($L['ph_phone']) . '">', false, 'f-phone') ?>
                <?= caField('location', $L['l_location'], '<input class="input" id="f-loc" name="location" type="text" autocomplete="address-level2" maxlength="120" placeholder="' . $h($L['ph_location']) . '">', false, 'f-loc') ?>
              </div>
            </fieldset>

            <fieldset class="ca-fs">
              <legend><?= $h($L['s_bg']) ?></legend>
              <div class="ca-grid">
                <?= caField('current_title', $L['l_ctitle'], '<input class="input" id="f-ct" name="current_title" type="text" maxlength="120" placeholder="' . $h($L['ph_ctitle']) . '">', false, 'f-ct') ?>
                <?= caField('current_company', $L['l_ccomp'], '<input class="input" id="f-cc" name="current_company" type="text" maxlength="120" placeholder="' . $h($L['ph_ccomp']) . '">', false, 'f-cc') ?>
                <?= caField('years_experience', $L['l_years'], '<input class="input" id="f-yrs" name="years_experience" type="number" inputmode="numeric" min="0" max="60" step="1" placeholder="' . $h($L['ph_years']) . '">', false, 'f-yrs', $L['h_years']) ?>
                <?= caField('education_level', $L['l_edu'], '<select class="input ca-sel" id="f-edu" name="education_level">' . caOptions($L['edu']) . '</select>', false, 'f-edu') ?>
                <?= caField('skills', $L['l_skills'], '<input class="input" id="f-sk" name="skills" type="text" maxlength="500" placeholder="' . $h($L['ph_skills']) . '">', false, 'f-sk', $L['h_skills'], 'full') ?>
                <?= caField('languages', $L['l_langs'], '<input class="input" id="f-lg" name="languages" type="text" maxlength="160" placeholder="' . $h($L['ph_langs']) . '">', false, 'f-lg', '', 'full') ?>
              </div>
            </fieldset>

            <fieldset class="ca-fs">
              <legend><?= $h($L['s_links']) ?></legend>
              <div class="ca-grid">
                <?= caField('linkedin_url', $L['l_linkedin'], '<input class="input" id="f-li" name="linkedin_url" type="text" inputmode="url" autocapitalize="off" maxlength="255" placeholder="' . $h($L['ph_linkedin']) . '">', false, 'f-li') ?>
                <?= caField('portfolio_url', $L['l_portfolio'], '<input class="input" id="f-pf" name="portfolio_url" type="text" inputmode="url" autocapitalize="off" maxlength="255" placeholder="' . $h($L['ph_portfolio']) . '">', false, 'f-pf') ?>
              </div>
            </fieldset>

            <fieldset class="ca-fs">
              <legend><?= $h($L['s_extra']) ?></legend>
              <div class="ca-grid">
                <div class="field full">
                  <label for="f-resume"><span><?= $h($L['l_resume']) ?> <span class="ca-req" aria-hidden="true">*</span></span><span class="ca-hint"><?= $h($L['h_resume']) ?></span></label>
                  <div class="ca-drop" id="ca-drop">
                    <input id="f-resume" name="resume" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" aria-describedby="err-resume">
                    <span class="ca-drop-ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg></span>
                    <strong><?= $h($L['drop_t']) ?></strong>
                    <span><?= $h($L['drop_or']) ?> <span class="ca-pick"><?= $h($L['drop_browse']) ?></span></span>
                  </div>
                  <div class="ca-file" id="ca-file">
                    <span class="ca-file-ic" id="ca-file-ext">PDF</span>
                    <span class="ca-file-t"><div class="ca-file-n" id="ca-file-n"></div><div class="ca-file-s" id="ca-file-s"></div></span>
                    <button type="button" id="ca-file-x"><?= $h($L['drop_remove']) ?></button>
                  </div>
                  <p class="ca-err" id="err-resume" data-err="resume"></p>
                </div>
                <?= caField('availability', $L['l_availability'], '<input class="input" id="f-av" name="availability" type="text" maxlength="80" placeholder="' . $h($L['ph_availability']) . '">', false, 'f-av') ?>
                <?= caField('salary_expectation', $L['l_salary'], '<input class="input" id="f-sal" name="salary_expectation" type="text" maxlength="80" placeholder="' . $h($L['ph_salary']) . '">', false, 'f-sal') ?>
                <?= caField('work_pref', $L['l_wpref'], '<select class="input ca-sel" id="f-wp" name="work_pref">' . caOptions($L['wpref']) . '</select>', false, 'f-wp') ?>
                <?= caField('heard_from', $L['l_heard'], '<select class="input ca-sel" id="f-hd" name="heard_from">' . caOptions($L['heard']) . '</select>', false, 'f-hd') ?>
                <label class="ca-check full"><input type="checkbox" name="relocate" value="1"><span><?= $h($L['l_relocate']) ?></span></label>
                <div class="field full">
                  <label for="f-cover"><span><?= $h($L['l_cover']) ?></span><span class="ca-hint"><?= $h($L['h_cover']) ?></span></label>
                  <textarea class="input" id="f-cover" name="cover_letter" maxlength="5000" placeholder="<?= $h($L['ph_cover']) ?>"></textarea>
                  <div class="ca-counter"><span id="ca-cc">0</span> / 5000</div>
                  <p class="ca-err" data-err="cover_letter"></p>
                </div>
              </div>
            </fieldset>

            <label class="ca-consent"><input type="checkbox" name="consent" value="1" id="f-consent" required><span><?= $L['consent'] ?></span></label>
            <p class="ca-err" data-err="consent"></p>
            <label class="ca-consent ca-consent-soft"><input type="checkbox" name="talent_consent" value="1"><span><?= $h($L['talent']) ?></span></label>
            <p class="ca-screen-note"><?= $h($L['screen_note']) ?></p>

            <div class="ca-banner" id="ca-banner" role="alert"></div>
            <div class="ca-submit">
              <button class="btn btn-primary btn-lg" type="submit" id="ca-send"><span class="ca-spin" aria-hidden="true"></span><span id="ca-send-t"><?= $h($L['submit']) ?></span></button>
            </div>
          </form>
        </div>

        <div class="ca-ok<?= $sentRef !== '' ? ' show' : '' ?>" id="ca-ok" role="status" aria-live="polite" tabindex="-1">
          <?= CareersArt::sent($L['ok_art']) ?>
          <h2><?= $h($L['ok_h']) ?></h2>
          <p id="ca-ok-p"><?= $sentRef !== '' ? $h(str_replace(['{name}', '{email}'], ['', $lang === 'en' ? 'your inbox' : 'votre boîte mail'], $L['ok_p'])) : '' ?></p>
          <div class="ca-ref"><span><?= $h($L['ok_ref']) ?></span><b id="ca-ok-ref"><?= $h($sentRef) ?></b></div>
          <div class="ca-next">
            <h3><?= $h($L['ok_next_h']) ?></h3>
            <ol><?php foreach ($L['ok_next'] as $n): ?><li><?= $h($n) ?></li><?php endforeach; ?></ol>
          </div>
          <p class="ca-track-p"><?= $h($L['ok_track_p']) ?></p>
          <div class="ca-ok-act"><a class="btn btn-primary" id="ca-ok-track" href="/track.php"><?= $h($L['ok_track']) ?></a><a class="btn btn-ghost" href="/careers.php#roles"><?= $h($L['ok_back']) ?></a></div>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>
<?php endif; ?>
</main>

<?= SiteChrome::footer($lang) ?>
<script>window.CA_L = <?= json_encode($jsL, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?= SiteChrome::scripts() ?>
<script src="/assets/js/careers.js" defer></script>
</body>
</html>
