<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Analytics.php';
Analytics::hit('view:evaluations');
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/EvalTheme.php';

$pdo = Database::getInstance();
require_once __DIR__ . '/lib/LiveEvalModes.php';
LiveEvalModes::advance($pdo);

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
$userSeen = [];
$userReg = [];
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user) {
        $scoreStmt = $pdo->prepare("
            SELECT r.session_id, r.score, r.async_q_id, r.cancelled_at,
                   TIMESTAMPDIFF(SECOND, COALESCE(r.last_activity, r.registered_at), NOW()) AS idle_seconds,
                   (SELECT COUNT(*) FROM live_eval_answers a WHERE a.registration_id = r.id) AS answered
            FROM live_eval_registrations r
            WHERE r.email = :email
        ");
        $scoreStmt->execute(['email' => $user['email']]);
        $scores = $scoreStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($scores as $s) {
            $userScores[(int)$s['session_id']] = $s['score'];
            $userSeen[(int)$s['session_id']] = true;
            $userReg[(int)$s['session_id']] = $s;
        }
    }
}
?>
<?php
$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$T = [
'fr' => [
 'title' => 'Évaluations — StudyVibe', 'kicker' => 'Évaluations libres', 'h1' => 'Passez une évaluation, <em>à votre rythme</em>',
 'lede' => 'Ces évaluations sont ouvertes à tous jusqu’à leur date limite. Choisissez-en une, lisez les règles, lancez-vous : votre résultat est calculé à la fin, question par question.',
 'steps' => ['Choisissez une carte', 'Lisez les règles', 'Lancez-vous, recevez votre résultat'],
 'stat_open' => 'ouvertes', 'stat_soon' => 'ferment bientôt', 'stat_done' => 'terminées par vous', 'stat_todo' => 'à faire',
 'search' => 'Matière, cours ou évaluation…', 'home' => 'Accueil', 'login' => 'Se connecter', 'space' => 'Mon espace', 'theme' => 'Changer de thème',
 'f_all' => 'Toutes', 'f_todo' => 'À faire', 'f_done' => 'Terminées', 'f_soon' => 'Ferment bientôt', 'f_new' => 'Nouveautés',
 'sort' => 'Trier par', 's_soon' => 'Date limite proche', 's_new' => 'Plus récentes', 's_az' => 'Cours (A à Z)',
 'q' => 'questions', 'per' => 's par question', 'about' => '≈ :n min au total', 'closes' => 'Ferme dans', 'open' => 'Toujours ouverte', 'expired' => 'Date limite dépassée',
 'b_new' => 'Nouveau', 'b_soon' => 'Ferme bientôt', 'b_done' => 'Terminée', 'b_inprog' => 'En cours', 'b_todo' => 'À faire', 'resume' => 'Reprendre', 'answered_of' => ':a / :n répondues',
 'start' => 'Commencer', 'retry' => 'Recommencer', 'details' => 'Voir les règles', 'count' => 'évaluation(s)',
 'empty_h' => 'Rien à passer pour le moment', 'empty_p' => 'Les évaluations libres apparaîtront ici dès que vos enseignants les publieront. Profitez-en pour réviser : vos leçons vous attendent.',
 'empty_cta' => 'Aller à mes cours', 'nores_h' => 'Aucune évaluation ne correspond', 'nores_p' => 'Essayez un autre mot, ou retirez le filtre.', 'reset' => 'Tout afficher',
 'sheet_before' => 'Avant de commencer', 'sheet_facts' => 'En bref', 'sheet_rules' => 'Les règles', 'sheet_close' => 'Fermer',
 'f_q' => 'Questions', 'f_t' => 'Temps par question', 'f_total' => 'Durée estimée', 'f_dead' => 'Date limite', 'f_status' => 'Votre statut', 'min' => 'min', 'none' => 'Aucune date limite',
 'r_one' => 'Les questions s’affichent <strong>une par une</strong>, chacune avec son minuteur. Quand il tombe à zéro, la question se ferme toute seule.',
 'r_nonneg' => '<strong>Aucun point négatif</strong> : une question sans réponse vaut 0, mieux vaut toujours répondre.',
 'r_score' => 'La note est le <strong>pourcentage de bonnes réponses</strong>. Vous la voyez dès la fin, avec la correction détaillée.',
 'r_shuffle' => 'Les propositions sont <strong>mélangées</strong> : ne vous fiez pas à l’ordre vu par un camarade.',
 'r_integrity' => '<strong>Surveillance activée</strong> : quitter la fenêtre est enregistré et votre enseignant peut le voir.',
 'r_retry' => 'Vous pouvez <strong>recommencer</strong> jusqu’à la date limite : la nouvelle tentative remplace la précédente.',
 'r_retry_done' => 'Recommencer <strong>remplace votre résultat actuel</strong> (:s %). Assurez-vous d’en avoir besoin.',
 'r_net' => 'Si la connexion coupe, vos réponses sont gardées et <strong>envoyées dès son retour</strong>.',
 'r_leave' => 'Vous quittez ou fermez l’onglet ? <strong>Votre progression est gardée 24 h</strong> : en revenant, vous reprenez à la question où vous vous étiez arrêté.',
 'r_resume' => 'Vous pouvez <strong>reprendre</strong> jusqu’au :d. Le temps de la question en cours ne s’écoule pas pendant votre absence.',
 'guest_h' => 'Pas de compte ?', 'guest_p' => 'Connectez-vous pour retrouver vos résultats dans votre espace, ou participez en invité avec votre nom et une adresse <strong>gmail.com</strong>, <strong>icloud.com</strong> ou <strong>facsciences-uy1.cm</strong> : vos résultats vous y sont envoyés.',
 'go_login' => 'Se connecter d’abord', 'help' => 'Besoin d’aide ? Consultez le guide', 'help_url' => '/student/dashboard.php#help',
 'foot_c' => 'Tous droits réservés.', 'foot_p' => 'Confidentialité', 'ev' => 'Évaluations',
],
'en' => [
 'title' => 'Evaluations — StudyVibe', 'kicker' => 'Free evaluations', 'h1' => 'Take an evaluation, <em>at your own pace</em>',
 'lede' => 'These evaluations are open to everyone until their deadline. Pick one, read the rules, get started: your result is worked out at the end, question by question.',
 'steps' => ['Pick a card', 'Read the rules', 'Start and get your result'],
 'stat_open' => 'open', 'stat_soon' => 'closing soon', 'stat_done' => 'completed by you', 'stat_todo' => 'to do',
 'search' => 'Subject, course or evaluation…', 'home' => 'Home', 'login' => 'Sign in', 'space' => 'My space', 'theme' => 'Toggle theme',
 'f_all' => 'All', 'f_todo' => 'To do', 'f_done' => 'Completed', 'f_soon' => 'Closing soon', 'f_new' => 'New',
 'sort' => 'Sort by', 's_soon' => 'Closing soonest', 's_new' => 'Newest', 's_az' => 'Course (A to Z)',
 'q' => 'questions', 'per' => 's per question', 'about' => '≈ :n min in total', 'closes' => 'Closes in', 'open' => 'Always open', 'expired' => 'Deadline passed',
 'b_new' => 'New', 'b_soon' => 'Closing soon', 'b_done' => 'Completed', 'b_inprog' => 'In progress', 'b_todo' => 'To do', 'resume' => 'Resume', 'answered_of' => ':a / :n answered',
 'start' => 'Start', 'retry' => 'Retake', 'details' => 'See the rules', 'count' => 'evaluation(s)',
 'empty_h' => 'Nothing to take right now', 'empty_p' => 'Free evaluations will appear here as soon as your teachers publish them. Use the time to revise: your lessons are waiting.',
 'empty_cta' => 'Go to my courses', 'nores_h' => 'No evaluation matches', 'nores_p' => 'Try another word, or remove the filter.', 'reset' => 'Show all',
 'sheet_before' => 'Before you start', 'sheet_facts' => 'At a glance', 'sheet_rules' => 'The rules', 'sheet_close' => 'Close',
 'f_q' => 'Questions', 'f_t' => 'Time per question', 'f_total' => 'Estimated length', 'f_dead' => 'Deadline', 'f_status' => 'Your status', 'min' => 'min', 'none' => 'No deadline',
 'r_one' => 'Questions appear <strong>one at a time</strong>, each with its own timer. When it reaches zero, the question closes by itself.',
 'r_nonneg' => '<strong>No negative marking</strong>: an unanswered question scores 0, so it is always better to answer.',
 'r_score' => 'The mark is the <strong>percentage of correct answers</strong>. You see it right at the end, with the detailed correction.',
 'r_shuffle' => 'Options are <strong>shuffled</strong>: do not rely on the order a classmate saw.',
 'r_integrity' => '<strong>Monitoring is on</strong>: leaving the window is recorded and your teacher can see it.',
 'r_retry' => 'You can <strong>retake</strong> until the deadline: the new attempt replaces the previous one.',
 'r_retry_done' => 'Retaking <strong>replaces your current result</strong> (:s %). Make sure you need it.',
 'r_net' => 'If the connection drops, your answers are kept and <strong>sent as soon as it returns</strong>.',
 'r_leave' => 'Leaving or closing the tab? <strong>Your progress is kept for 24 hours</strong>: when you come back, you resume at the question where you stopped.',
 'r_resume' => 'You can <strong>resume</strong> until :d. The time of the question in progress does not run while you are away.',
 'guest_h' => 'No account?', 'guest_p' => 'Sign in to find your results in your space, or take part as a guest with your name and a <strong>gmail.com</strong>, <strong>icloud.com</strong> or <strong>facsciences-uy1.cm</strong> address: your results are sent there.',
 'go_login' => 'Sign in first', 'help' => 'Need help? Read the guide', 'help_url' => '/student/dashboard.php#help',
 'foot_c' => 'All rights reserved.', 'foot_p' => 'Privacy', 'ev' => 'Evaluations',
],
][$lang];
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$roleHome = ['promoter'=>'/promoter/dashboard.php','teacher'=>'/teacher/dashboard.php','student'=>'/student/dashboard.php'];
$loggedIn = isLoggedIn();
$isStudent = $loggedIn && (($_SESSION['user_role'] ?? '') === 'student');

// One entry per card: the theme picks the picture and the colour, the user's own state picks the badge.
$now = time();
$cards = [];
$nSoon = $nDone = $nTodo = 0;
foreach ($evaluations as $ev) {
    $id = (int)$ev['id'];
    $theme = EvalTheme::detect((string)$ev['title'], (string)$ev['course_title']);
    $deadline = !empty($ev['async_deadline']) ? (int)strtotime((string)$ev['async_deadline']) : 0;
    $nq = (int)$ev['question_count'];
    $tl = (int)$ev['default_time_limit'];
    $seen = isset($userSeen[$id]);
    $score = ($seen && $userScores[$id] !== null) ? (float)$userScores[$id] : null;
    // done: finished. inprog: started and still resumable (24 h after the last activity). Otherwise it is to do (a stale attempt starts over).
    $reg = $userReg[$id] ?? null;
    $answered = $reg ? (int)$reg['answered'] : 0;
    $resumable = $reg && $score === null && empty($reg['cancelled_at']) && ($answered > 0 || !empty($reg['async_q_id'])) && (int)$reg['idle_seconds'] <= 86400;
    $state = $score !== null ? 'done' : ($resumable ? 'inprog' : 'todo');
    $resumeUntil = $resumable ? $now + (86400 - (int)$reg['idle_seconds']) : 0;
    $soon = $deadline > 0 && ($deadline - $now) <= 72 * 3600;
    $isNew = !empty($ev['created_at']) && ($now - (int)strtotime((string)$ev['created_at'])) <= 3 * 86400;
    if ($soon) { $nSoon++; }
    if ($state === 'done') { $nDone++; } elseif ($state === 'todo') { $nTodo++; }
    $cards[] = [
        'id' => $id, 'title' => (string)$ev['title'], 'course' => (string)$ev['course_title'], 'theme' => $theme,
        'themeLabel' => EvalTheme::label($theme, $lang), 'color' => EvalTheme::color($theme), 'art' => EvalTheme::art($theme, $id),
        'nq' => $nq, 'tl' => $tl, 'total' => (int)ceil(($nq * $tl) / 60), 'deadline' => $deadline, 'created' => (int)strtotime((string)($ev['created_at'] ?? 'now')),
        'state' => $state, 'score' => $score, 'soon' => $soon, 'new' => $isNew, 'answered' => $answered, 'resumeUntil' => $resumeUntil,
        'shuffle' => (int)($ev['shuffle_options'] ?? 0) === 1, 'integrity' => (int)($ev['integrity_watch'] ?? 0) === 1,
        'url' => '/live-session.php?code=' . urlencode((string)$ev['session_code']),
    ];
}
$jsCards = array_map(fn($c) => ['id' => $c['id'], 'title' => $c['title'], 'course' => $c['course'], 'theme' => $c['themeLabel'], 'color' => $c['color'], 'art' => $c['art'], 'nq' => $c['nq'], 'tl' => $c['tl'], 'total' => $c['total'], 'deadline' => $c['deadline'], 'state' => $c['state'], 'score' => $c['score'], 'answered' => $c['answered'], 'resumeUntil' => $c['resumeUntil'], 'shuffle' => $c['shuffle'], 'integrity' => $c['integrity'], 'url' => $c['url']], $cards);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($T['title']) ?></title>
<?php require_once __DIR__ . '/lib/SocialMeta.php'; echo SocialMeta::tags($T['title'], $T['lede'], '/evaluations.php', 'og-evaluation-async.png'); ?>
<meta name="theme-color" content="#F5F0E6">
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<link rel="stylesheet" href="/assets/css/brand.css">
<link rel="stylesheet" href="/assets/css/landing.css">
<link rel="stylesheet" href="/assets/css/evaluations-page.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/evaluations-page.css') ?>">
<link rel="manifest" href="/manifest.webmanifest">
<script>if("serviceWorker"in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/sw.js").catch(function(){})})}</script>
<?= Brand::pwaTags() ?>
<?= csrfMetaTag(); ?>
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
      <a href="/evaluations.php" aria-current="page" style="color:var(--ink)"><?= $h($T['ev']) ?></a>
    </nav>
    <div class="nav-tools">
      <div class="seg" role="group" aria-label="Language">
        <a href="#" onclick="changeLanguage('fr');return false;" <?= $lang === 'fr' ? 'aria-current="true"' : '' ?>>FR</a>
        <a href="#" onclick="changeLanguage('en');return false;" <?= $lang === 'en' ? 'aria-current="true"' : '' ?>>EN</a>
      </div>
      <button class="chip" type="button" data-dark-toggle aria-label="<?= $h($T['theme']) ?>"><svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 2a6 6 0 0 0 0 12z" fill="currentColor"/></svg></button>
      <?php if ($loggedIn): ?>
        <a class="btn btn-primary" href="<?= $h($roleHome[$_SESSION['user_role']] ?? '/') ?>"><?= $h($T['space']) ?></a>
      <?php else: ?>
        <a class="btn btn-primary" href="/index.php?auth=login&amp;redirect=<?= urlencode('/evaluations.php') ?>"><?= $h($T['login']) ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="wrap ev">
  <section class="ev-hero">
    <div class="ev-hero-text">
      <p class="kicker"><?= $h($T['kicker']) ?></p>
      <h1><?= $T['h1'] ?></h1>
      <p class="ev-lede"><?= $h($T['lede']) ?></p>
      <ol class="ev-steps"><?php foreach ($T['steps'] as $i => $st): ?><li><span><?= $i + 1 ?></span><?= $h($st) ?></li><?php endforeach; ?></ol>
      <?php if ($cards): ?>
      <dl class="ev-stats">
        <div><dt><?= count($cards) ?></dt><dd><?= $h($T['stat_open']) ?></dd></div>
        <div><dt><?= $nSoon ?></dt><dd><?= $h($T['stat_soon']) ?></dd></div>
        <?php if ($loggedIn): ?><div><dt><?= $nTodo ?></dt><dd><?= $h($T['stat_todo']) ?></dd></div><div><dt><?= $nDone ?></dt><dd><?= $h($T['stat_done']) ?></dd></div><?php endif; ?>
      </dl>
      <?php endif; ?>
    </div>
    <img class="ev-hero-art" src="/assets/img/evals/hero.svg" alt="" width="460" height="400">
  </section>

<?php if (!$cards): ?>
  <section class="ev-empty">
    <img src="/assets/img/evals/empty.svg" alt="" width="360" height="300">
    <div><h2><?= $h($T['empty_h']) ?></h2><p><?= $h($T['empty_p']) ?></p>
      <a class="btn btn-primary" href="<?= $isStudent ? '/student/dashboard.php#courses' : '/' ?>"><?= $h($T['empty_cta']) ?></a></div>
  </section>
<?php else: ?>
  <div class="ev-bar">
    <div class="ev-chips" role="group" aria-label="Filtres">
      <button type="button" class="ev-chip" data-f="all" aria-pressed="true"><?= $h($T['f_all']) ?> <small><?= count($cards) ?></small></button>
      <?php if ($loggedIn): ?>
      <button type="button" class="ev-chip" data-f="todo" aria-pressed="false"><?= $h($T['f_todo']) ?> <small><?= $nTodo ?></small></button>
      <button type="button" class="ev-chip" data-f="done" aria-pressed="false"><?= $h($T['f_done']) ?> <small><?= $nDone ?></small></button>
      <?php endif; ?>
      <button type="button" class="ev-chip" data-f="soon" aria-pressed="false"><?= $h($T['f_soon']) ?> <small><?= $nSoon ?></small></button>
      <button type="button" class="ev-chip" data-f="new" aria-pressed="false"><?= $h($T['f_new']) ?> <small><?= count(array_filter($cards, fn($c) => $c['new'])) ?></small></button>
    </div>
    <div class="ev-tools">
      <label class="ev-search"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
        <input type="search" id="ev-q" placeholder="<?= $h($T['search']) ?>" aria-label="<?= $h($T['search']) ?>"></label>
      <label class="ev-sort"><span><?= $h($T['sort']) ?></span>
        <select id="ev-sort"><option value="soon"><?= $h($T['s_soon']) ?></option><option value="new"><?= $h($T['s_new']) ?></option><option value="az"><?= $h($T['s_az']) ?></option></select></label>
    </div>
  </div>
  <p class="ev-count" aria-live="polite"><span id="ev-visible"><?= count($cards) ?></span> <?= $h($T['count']) ?></p>

  <ul class="ev-grid" id="ev-grid">
  <?php foreach ($cards as $c): ?>
    <li class="ev-card" style="--c: <?= $h($c['color']) ?>" data-id="<?= $c['id'] ?>" data-state="<?= $c['state'] ?>" data-soon="<?= $c['soon'] ? 1 : 0 ?>" data-new="<?= $c['new'] ? 1 : 0 ?>"
        data-deadline="<?= $c['deadline'] ?>" data-created="<?= $c['created'] ?>" data-course="<?= $h(mb_strtolower($c['course'])) ?>"
        data-text="<?= $h(mb_strtolower($c['title'] . ' ' . $c['course'] . ' ' . $c['themeLabel'])) ?>">
      <button type="button" class="ev-card-open" data-open="<?= $c['id'] ?>" aria-label="<?= $h($c['title'] . ' — ' . $T['details']) ?>">
        <span class="ev-art"><img src="<?= $h($c['art']) ?>" alt="" loading="lazy" width="320" height="240">
          <span class="ev-badges">
            <?php if ($c['state'] === 'done'): ?><span class="ev-badge ok"><?= $h($T['b_done']) ?> · <?= number_format((float)$c['score'], 0) ?> %</span>
            <?php elseif ($c['state'] === 'inprog'): ?><span class="ev-badge info"><?= $h($T['b_inprog']) ?> · <?= $h(str_replace([':a', ':n'], [(string)$c['answered'], (string)$c['nq']], $T['answered_of'])) ?></span><?php endif; ?>
            <?php if ($c['soon'] && $c['state'] !== 'done'): ?><span class="ev-badge warn" data-soon-badge><?= $h($T['b_soon']) ?></span>
            <?php elseif ($c['new'] && $c['state'] === 'todo'): ?><span class="ev-badge new"><?= $h($T['b_new']) ?></span><?php endif; ?>
          </span>
          <span class="ev-theme"><?= $h($c['themeLabel']) ?></span>
        </span>
        <span class="ev-body">
          <span class="ev-course"><?= $h($c['course']) ?></span>
          <strong class="ev-title"><?= $h($c['title']) ?></strong>
          <span class="ev-meta">
            <span><b><?= $c['nq'] ?></b> <?= $h($T['q']) ?></span>
            <span><b><?= $c['tl'] ?></b><?= $h($T['per']) ?></span>
            <span><?= $h(str_replace(':n', (string)max(1, $c['total']), $T['about'])) ?></span>
          </span>
          <span class="ev-foot">
            <?php if ($c['deadline']): ?><span class="ev-time" data-deadline="<?= $c['deadline'] ?>"><?= $h($T['closes']) ?> <strong>--</strong></span>
            <?php else: ?><span class="ev-time"><?= $h($T['open']) ?></span><?php endif; ?>
            <span class="ev-go"><?= $h($c['state'] === 'todo' ? $T['start'] : ($c['state'] === 'done' ? $T['retry'] : $T['resume'])) ?>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
          </span>
        </span>
      </button>
    </li>
  <?php endforeach; ?>
  </ul>

  <section class="ev-empty" id="ev-none" hidden>
    <img src="/assets/img/evals/noresult.svg" alt="" width="300" height="250">
    <div><h2><?= $h($T['nores_h']) ?></h2><p><?= $h($T['nores_p']) ?></p><button type="button" class="btn btn-ghost" id="ev-reset"><?= $h($T['reset']) ?></button></div>
  </section>

  <div class="ev-sheet" id="ev-sheet" role="dialog" aria-modal="true" aria-labelledby="ev-sheet-title" hidden>
    <div class="ev-sheet-backdrop" data-close></div>
    <div class="ev-sheet-panel" tabindex="-1">
      <button type="button" class="ev-sheet-x" data-close aria-label="<?= $h($T['sheet_close']) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
      <div class="ev-sheet-art"><img id="sh-art" src="" alt=""><span id="sh-theme" class="ev-theme"></span></div>
      <div class="ev-sheet-main">
        <p class="ev-sheet-kicker"><?= $h($T['sheet_before']) ?> · <span id="sh-course"></span></p>
        <h2 id="ev-sheet-title"></h2>
        <dl class="ev-facts">
          <div><dt><?= $h($T['f_q']) ?></dt><dd id="sh-q"></dd></div>
          <div><dt><?= $h($T['f_t']) ?></dt><dd id="sh-t"></dd></div>
          <div><dt><?= $h($T['f_total']) ?></dt><dd id="sh-total"></dd></div>
          <div><dt><?= $h($T['f_dead']) ?></dt><dd id="sh-dead"></dd></div>
          <?php if ($loggedIn): ?><div><dt><?= $h($T['f_status']) ?></dt><dd id="sh-status"></dd></div><?php endif; ?>
        </dl>
        <h3><?= $h($T['sheet_rules']) ?></h3>
        <ul class="ev-rules" id="sh-rules"></ul>
        <?php if (!$loggedIn): ?>
        <div class="ev-guest"><strong><?= $h($T['guest_h']) ?></strong><p><?= $T['guest_p'] ?></p></div>
        <?php endif; ?>
        <div class="ev-sheet-actions">
          <a class="btn btn-primary" id="sh-go" href="#"><?= $h($T['start']) ?></a>
          <?php if (!$loggedIn): ?><a class="btn btn-ghost" href="/index.php?auth=login&amp;redirect=<?= urlencode('/evaluations.php') ?>"><?= $h($T['go_login']) ?></a>
          <?php elseif ($isStudent): ?><a class="ev-help" href="<?= $h($T['help_url']) ?>"><?= $h($T['help']) ?></a><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
</main>

<footer class="foot" style="margin-top:72px"><div class="wrap foot-in">
  <?= Brand::logo('sm') ?>
  <span>© <?= date('Y') ?> StudyVibe. <?= $h($T['foot_c']) ?> · <a href="/privacy.php"><?= $h($T['foot_p']) ?></a></span>
</div></footer>

<script src="/assets/js/app.js"></script>
<script>
  window.EV = { lang: <?= json_encode($lang) ?>, T: <?= json_encode($T, JSON_UNESCAPED_UNICODE) ?>, cards: <?= json_encode($jsCards, JSON_UNESCAPED_UNICODE) ?>, loggedIn: <?= json_encode($loggedIn) ?>, now: <?= $now ?> };
</script>
<script src="/assets/js/evaluations-page.js?v=<?= (int)@filemtime(__DIR__ . '/assets/js/evaluations-page.js') ?>"></script>
</body>
</html>
