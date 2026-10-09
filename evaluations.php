<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Analytics.php';
Analytics::hit('view:evaluations');
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/lib/Brand.php';

$pdo = Database::getInstance();

// Récupérer toutes les évaluations asynchrones actives
$stmt = $pdo->prepare("
    SELECT s.*, c.title AS course_title,
           (SELECT COUNT(*) FROM live_eval_questions WHERE session_id = s.id) AS question_count
    FROM live_eval_sessions s
    JOIN courses c ON s.course_id = c.id
    WHERE s.status = 1 
      AND s.is_async = 1 
      AND (s.async_deadline IS NULL OR s.async_deadline > NOW())
    ORDER BY s.created_at DESC
");
$stmt->execute();
$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si l'utilisateur est connecté, vérifier ses tentatives / notes
$userScores = [];
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user) {
        $scoreStmt = $pdo->prepare("
            SELECT session_id, score 
            FROM live_eval_registrations 
            WHERE email = :email
        ");
        $scoreStmt->execute(['email' => $user['email']]);
        $scores = $scoreStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($scores as $s) {
            $userScores[(int)$s['session_id']] = $s['score'];
        }
    }
}
?>
<?php
$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$T = [
'fr' => ['title'=>'Évaluations — StudyVibe','h1'=>'Évaluations <em>libres</em>','lede'=>'Des évaluations que vous passez à votre rythme, jusqu’à la date limite. Choisissez-en une et lancez-vous.',
 'search'=>'Rechercher un cours ou une évaluation','home'=>'Accueil','login'=>'Se connecter','space'=>'Mon espace','theme'=>'Changer de thème',
 'empty_h'=>'Rien à passer pour le moment','empty_p'=>'Les évaluations libres ouvertes apparaîtront ici dès que votre enseignant les publie.','nores'=>'Aucun résultat pour cette recherche.',
 'q'=>'questions','per'=>'s par question','closes'=>'Ferme dans','open'=>'Toujours ouvert','expired'=>'Date limite dépassée','done'=>'Terminée','inprog'=>'En cours de correction',
 'start'=>'Commencer','retry'=>'Recommencer','count'=>'évaluation(s) ouverte(s)','foot_c'=>'Tous droits réservés.','foot_p'=>'Confidentialité'],
'en' => ['title'=>'Evaluations — StudyVibe','h1'=>'Open <em>evaluations</em>','lede'=>'Evaluations you take at your own pace, until the deadline. Pick one and get started.',
 'search'=>'Search a course or an evaluation','home'=>'Home','login'=>'Sign in','space'=>'My space','theme'=>'Toggle theme',
 'empty_h'=>'Nothing to take right now','empty_p'=>'Open evaluations will show up here as soon as your teacher publishes them.','nores'=>'No result for this search.',
 'q'=>'questions','per'=>'s per question','closes'=>'Closes in','open'=>'Always open','expired'=>'Deadline passed','done'=>'Completed','inprog'=>'Being graded',
 'start'=>'Start','retry'=>'Retake','count'=>'open evaluation(s)','foot_c'=>'All rights reserved.','foot_p'=>'Privacy'],
][$lang];
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$roleHome = ['promoter'=>'/promoter/dashboard.php','teacher'=>'/teacher/dashboard.php','student'=>'/student/dashboard.php'];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($T['title']) ?></title>
<meta name="theme-color" content="#F5F0E6">
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<link rel="stylesheet" href="/assets/css/brand.css">
<link rel="stylesheet" href="/assets/css/landing.css">
<?= csrfMetaTag(); ?>
<style>
  .page-head { padding: 64px 0 32px; }
  .page-head h1 { font-size: clamp(2.4rem, 5vw, 3.8rem); font-weight: 450; margin-top: .8rem; }
  .page-head h1 em { font-style: italic; color: var(--clay); font-weight: 400; }
  .page-head .lede { margin-top: 1rem; color: var(--ink-2); font-size: 1.15rem; max-width: 52ch; }
  .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 20px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
  .toolbar .input { max-width: 380px; }
  .count { color: var(--ink-3); font-size: .9rem; }
  .rows { list-style: none; margin: 0; padding: 0; }
  .row { display: grid; grid-template-columns: 1fr auto; gap: 24px; padding: 26px 0; border-bottom: 1px solid var(--line); align-items: center; }
  .row-course { font-size: .78rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--ink-3); }
  .row h3 { font-size: 1.5rem; font-weight: 450; margin: .35rem 0 .6rem; }
  .meta { display: flex; flex-wrap: wrap; gap: .4rem 1.4rem; font-size: .9rem; color: var(--ink-2); }
  .meta .num { color: var(--ink); font-weight: 600; }
  .state { display: inline-flex; align-items: center; gap: .45rem; font-size: .88rem; font-weight: 600; }
  .state.live { color: var(--pine); } .state.late { color: var(--danger); } .state.ok { color: var(--ok); }
  .row-side { display: grid; gap: .6rem; justify-items: end; text-align: right; }
  .score { font-family: var(--font-display); font-size: 1.6rem; line-height: 1; }
  .score small { font-family: var(--font-body); font-size: .85rem; color: var(--ink-3); }
  .empty { padding: 72px 0; max-width: 46ch; }
  .empty h2 { font-size: 2rem; font-weight: 450; } .empty p { color: var(--ink-2); margin-top: .6rem; }
  @media (max-width: 640px) { .row { grid-template-columns: 1fr; } .row-side { justify-items: start; text-align: left; } .row .btn { width: 100%; } }
</style>
<script>
  document.documentElement.classList.add('js');
  try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
</script>
</head>
<body class="v2">
<header class="nav scrolled" id="nav">
  <div class="wrap nav-in">
    <a href="/" aria-label="StudyVibe" style="text-decoration:none"><?= Brand::logo('md') ?></a>
    <nav class="nav-links" aria-label="Main">
      <a href="/"><?= $h($T['home']) ?></a>
      <a href="/evaluations.php" aria-current="page" style="color:var(--ink)"><?= $h($lang === 'en' ? 'Evaluations' : 'Évaluations') ?></a>
    </nav>
    <div class="nav-tools">
      <div class="seg" role="group" aria-label="Language">
        <a href="#" onclick="changeLanguage('fr');return false;" <?= $lang === 'fr' ? 'aria-current="true"' : '' ?>>FR</a>
        <a href="#" onclick="changeLanguage('en');return false;" <?= $lang === 'en' ? 'aria-current="true"' : '' ?>>EN</a>
      </div>
      <button class="chip" type="button" data-dark-toggle aria-label="<?= $h($T['theme']) ?>"><svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 2a6 6 0 0 1 0 12z" fill="currentColor"/></svg></button>
      <?php if (isLoggedIn()): ?>
        <a class="btn btn-primary" href="<?= $h($roleHome[$_SESSION['user_role']] ?? '/') ?>"><?= $h($T['space']) ?></a>
      <?php else: ?>
        <a class="btn btn-primary" href="/index.php?auth=login&amp;redirect=<?= urlencode('/evaluations.php') ?>"><?= $h($T['login']) ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="wrap">
  <section class="page-head">
    <p class="kicker"><?= $h($lang === 'en' ? 'Evaluations' : 'Évaluations') ?></p>
    <h1><?= $T['h1'] ?></h1>
    <p class="lede"><?= $h($T['lede']) ?></p>
  </section>

  <?php if (empty($evaluations)): ?>
    <div class="empty"><h2><?= $h($T['empty_h']) ?></h2><p><?= $h($T['empty_p']) ?></p></div>
  <?php else: ?>
    <div class="toolbar">
      <input type="search" id="search-input" class="input" placeholder="<?= $h($T['search']) ?>" aria-label="<?= $h($T['search']) ?>" oninput="filterEvaluations()">
      <span class="count"><span class="num" id="visible-count"><?= count($evaluations) ?></span> <?= $h($T['count']) ?></span>
    </div>
    <ul class="rows" id="eval-grid">
      <?php foreach ($evaluations as $eval):
        $hasDeadline = !empty($eval['async_deadline']);
        $deadlineTime = $hasDeadline ? strtotime($eval['async_deadline']) : 0;
        $hasAttempted = isset($userScores[(int)$eval['id']]);
        $score = $hasAttempted ? $userScores[(int)$eval['id']] : null;
      ?>
      <li class="row eval-card" data-title="<?= $h(mb_strtolower($eval['title'])) ?>" data-course="<?= $h(mb_strtolower($eval['course_title'])) ?>">
        <div>
          <div class="row-course"><?= $h($eval['course_title']) ?></div>
          <h3 class="eval-name"><?= $h($eval['title']) ?></h3>
          <div class="meta">
            <span><span class="num"><?= (int)$eval['question_count'] ?></span> <?= $h($T['q']) ?></span>
            <span><span class="num"><?= (int)$eval['default_time_limit'] ?></span><?= $h($T['per']) ?></span>
            <?php if ($hasDeadline): ?>
              <span class="state live countdown-container" id="timer-<?= (int)$eval['id'] ?>" data-deadline="<?= $deadlineTime ?>"><?= $h($T['closes']) ?>&nbsp;<strong class="num" id="time-val-<?= (int)$eval['id'] ?>">--</strong></span>
            <?php else: ?>
              <span class="state"><?= $h($T['open']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="row-side">
          <?php if ($hasAttempted && $score !== null): ?>
            <div class="score num"><?= number_format((float)$score, 2) ?><small> / 20</small></div>
            <span class="state ok"><?= $h($T['done']) ?></span>
          <?php elseif ($hasAttempted): ?>
            <span class="state"><?= $h($T['inprog']) ?></span>
          <?php endif; ?>
          <a href="/live-session.php?code=<?= urlencode($eval['session_code']) ?>" class="btn <?= $hasAttempted ? 'btn-ghost' : 'btn-primary' ?> btn-start-eval"><?= $h($hasAttempted ? $T['retry'] : $T['start']) ?></a>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <p class="empty" id="no-results" hidden><?= $h($T['nores']) ?></p>
  <?php endif; ?>
</main>

<footer class="foot" style="margin-top:72px"><div class="wrap foot-in">
  <?= Brand::logo('sm') ?>
  <span>© <?= date('Y') ?> StudyVibe. <?= $h($T['foot_c']) ?> · <a href="/privacy.php"><?= $h($T['foot_p']) ?></a></span>
</div></footer>

<script src="/assets/js/app.js"></script>
<script>
  const SV_EXPIRED = <?= json_encode($T['expired'], JSON_UNESCAPED_UNICODE) ?>;
  function filterEvaluations() {
    const q = document.getElementById('search-input').value.toLowerCase().trim();
    let n = 0;
    document.querySelectorAll('.eval-card').forEach(c => {
      const ok = c.dataset.title.includes(q) || c.dataset.course.includes(q);
      c.hidden = !ok; if (ok) n++;
    });
    document.getElementById('visible-count').textContent = n;
    document.getElementById('no-results').hidden = n !== 0;
  }
  function updateCountdowns() {
    const now = Math.floor(Date.now() / 1000);
    document.querySelectorAll('[id^="timer-"]').forEach(t => {
      const diff = parseInt(t.dataset.deadline, 10) - now;
      if (diff <= 0) {
        t.className = 'state late'; t.textContent = SV_EXPIRED;
        const b = t.closest('.eval-card').querySelector('.btn-start-eval'); if (b) b.style.display = 'none';
        return;
      }
      const d = Math.floor(diff / 86400), h = Math.floor(diff % 86400 / 3600), m = Math.floor(diff % 3600 / 60), s = diff % 60;
      const v = t.querySelector('strong');
      if (v) v.textContent = (d ? d + (document.documentElement.lang === 'en' ? 'd ' : 'j ') : '') + String(h).padStart(2,'0') + 'h ' + String(m).padStart(2,'0') + 'm ' + String(s).padStart(2,'0') + 's';
    });
  }
  setInterval(updateCountdowns, 1000); updateCountdowns();
</script>
</body>
</html>
