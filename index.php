<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Landing & Authentication Portal
 * 
 * Serves as the public gateway page. Welcomes visitors, manages locale selection switches, 
 * renders showcase carousels representing system features, and implements security controls 
 * for login, password-reset requests, and multi-step registration (student/teacher profiles).
 * Rediriges logged-in users automatically to their respective roles' dashboards.
 * 
 * @package    StudyVibe
 * @author     Advanced Engineering Team
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';

// =========================================================================
// SECTION 1: USER SESSION REDIRECTION GATE
// =========================================================================

if (isLoggedIn()) {
    $map = [
        'promoter' => '/promoter/dashboard.php', 
        'teacher' => '/teacher/dashboard.php', 
        'student' => '/student/dashboard.php'
    ];
    header('Location: ' . ($map[$_SESSION['user_role']] ?? '/index.php'));
    exit;
}
?>
<?php
$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';

require_once __DIR__ . '/lib/Analytics.php';
Analytics::captureSource();      // ?src=qr-campus-1 is remembered for the session, so a later sign-up is attributed to it
Analytics::hit('view:landing');
$T = [
'fr' => [
 'title' => 'StudyVibe — L’examen en direct, sans accroc',
 'desc' => "Toute la promo démarre à la même seconde, les notes arrivent sans correction à la main, et chaque réussite devient un certificat vérifiable. Pour les universités et les centres de formation.",
 'nav_eval' => 'Évaluations', 'nav_how' => 'Le parcours', 'nav_live' => 'Examen en direct', 'nav_who' => 'Pour qui',
 'login' => 'Se connecter', 'join' => 'Créer un compte', 'theme' => 'Changer de thème', 'menu' => 'Menu',
 'kicker' => 'Examens en direct pour l’enseignement supérieur et la formation',
 'h1' => 'L’examen en direct, <em>sans accroc.</em>',
 'lede' => 'Toute la promo démarre à la même seconde, les notes arrivent sans correction à la main, et chaque réussite devient un certificat vérifiable. Les cours sont là pour préparer la salle.',
 'cta1' => 'Commencer gratuitement', 'cta2' => 'Voir un examen en direct',
 'note' => 'Testé avec 300 étudiants simulés connectés en même temps. Dans le navigateur, sans application à installer.',
 'room_live' => 'EN DIRECT', 'room_title' => 'INF242 · QCM de fin de module', 'room_count' => '47 connectés',
 'room_q_n' => 'Question 6 sur 20', 'room_q' => 'Quelle est la complexité d’une recherche dans un arbre binaire de recherche équilibré ?',
 'room_o' => ['O(1)', 'O(log n)', 'O(n)', 'O(n log n)'],
 'study_alt' => 'Une étudiante révise sur son ordinateur', 'cert_alt' => 'Une étudiante consulte son certificat', 'end_alt' => 'Des diplômés qui célèbrent', 'end_p' => 'Créez votre compte, préparez la séance, et laissez la salle se remplir. Le reste se fait tout seul.',
 'art_alt' => 'Un étudiant lit sur un canapé', 'live_alt' => 'Une étudiante suit une séance en direct sur son ordinateur',
 's1_k' => 'Le parcours', 's1_h' => 'Trois temps, un seul compte.',
 's1_p' => 'Un étudiant s’inscrit, apprend, se fait évaluer, puis reçoit une preuve qu’il peut montrer à n’importe qui.',
 'm1_h' => 'Apprendre', 'm1_p' => 'Des cours découpés en chapitres et leçons. On lit le texte, on regarde la vidéo, on ouvre le PDF, sans quitter la page.',
 'm1_l' => ['Notes personnelles liées à la minute de la vidéo', 'Quiz de leçon qui se débloque après la lecture', 'Assistant IA pour résumer ou expliquer un passage', 'Progression sauvegardée d’un appareil à l’autre'],
 'm2_h' => 'Être évalué', 'm2_p' => 'L’enseignant lance une séance, tout le monde commence à la même seconde, et les notes arrivent sans correction à la main.',
 'm2_l' => ['Examens synchronisés sur l’horloge du serveur', 'Import de questions depuis un fichier CSV', 'Devoirs libres avec une date limite', 'Export des notes en Excel et en PDF'],
 'm3_h' => 'Être reconnu', 'm3_p' => 'Chaque réussite donne un certificat PDF avec un code unique. Un recruteur le vérifie en quelques secondes.',
 'm3_l' => ['Code de vérification public', 'Relevé de notes par étudiant', 'Émission automatique ou manuelle', 'Interface API pour relier votre système de scolarité'],
 's2_k' => 'Examen en direct', 's2_h' => 'Une salle d’attente, un signal, et tout le monde part ensemble.',
 's2_p' => 'La séance se prépare à l’avance. Les étudiants arrivent dans la salle, voient combien de camarades sont là, et l’examen démarre à l’heure prévue.',
 'f' => [['Synchronisation', 'Le décompte s’appuie sur l’horloge du serveur, pas sur celle du téléphone de chacun.'], ['Sans installation', 'Tout passe par le navigateur. Pas de WebSocket à configurer, pas d’application à télécharger.'], ['Pour l’enseignant', 'Pause, reprise, réinitialisation et statistiques de la séance en temps réel.'], ['Pour l’étudiant', 'Minuteur par question, formules mathématiques lisibles, résultat envoyé par email.']],
 's3_k' => 'Pour qui', 's3_h' => 'Trois rôles, trois espaces.',
 'r' => [
   ['Étudiant', 'Apprenant', ['Catalogue de cours et inscription en un clic', 'Liseuse avec notes et assistant IA', 'Résultats, relevé et certificats au même endroit']],
   ['Enseignant', 'Enseignant', ['Cours, chapitres, leçons, médias', 'Séances en direct et devoirs libres', 'Notes, statistiques et modération des questions']],
   ['Administrateur', 'Administrateur', ['Comptes enseignants et étudiants', 'Certificats, newsletters, clés API', 'Vue d’ensemble de l’établissement']]],
 's4_k' => 'Certificats', 's4_h' => 'Un certificat qui se vérifie.', 's4_p' => 'Vous avez un code reçu avec un certificat ? Collez-le ici pour confirmer qu’il est authentique.',
 'v_ph' => 'Code du certificat', 'v_btn' => 'Vérifier',
 'cert_k' => 'Certificat de réussite', 'cert_h' => 'Module validé', 'cert_p' => 'Remis à un étudiant ayant validé l’ensemble des évaluations du module.', 'cert_c' => 'Code', 'cert_ex' => 'exemple',
 'end_h' => 'Prêt à ouvrir <em>votre première séance ?</em>',
 'foot_c' => 'Tous droits réservés.', 'foot_p' => 'Confidentialité',
 'a_login_h' => 'Bon retour', 'a_login_s' => 'Connectez-vous pour reprendre là où vous vous étiez arrêté.',
 'email' => 'Adresse e-mail', 'email_ph' => 'vous@exemple.com', 'pw' => 'Mot de passe', 'show' => 'Afficher', 'hide' => 'Masquer',
 'forgot' => 'Mot de passe oublié ?', 'forgot_s' => 'Indiquez votre e-mail, nous vous envoyons un lien pour en choisir un nouveau.', 'forgot_btn' => 'Envoyer le lien', 'back' => 'Retour',
 'no_acc' => 'Pas encore de compte ?', 'has_acc' => 'Déjà inscrit ?',
 'a_sign_h' => 'Créer votre compte', 'a_sign_s' => 'Deux étapes, moins d’une minute.',
 'role_s' => 'Étudiant', 'role_s_d' => 'Je suis des cours', 'role_t' => 'Enseignant', 'role_t_d' => 'Je crée et j’anime',
 'cont' => 'Continuer', 'name' => 'Nom complet', 'name_ph' => 'Marie Curie', 'pw_ph' => '8 caractères minimum', 'pw_h' => 'Au moins 8 caractères.',
 'phone' => 'Numéro de téléphone', 'phone_ph' => '6 12 34 56 78', 'phone_h' => 'Pour les rappels d’examens et la sécurité du compte. Nous le vérifierons par SMS après votre connexion.', 'news' => 'Recevoir la newsletter (facultatif)', 'create' => 'Créer mon compte',
 'net_err' => 'Le serveur ne répond pas comme prévu. Vérifiez votre connexion et réessayez dans un instant.', 'fg_busy' => 'Envoi en cours…', 'fg_ok_h' => 'Lien envoyé', 'fg_ok_p' => 'Si un compte existe pour :email, un lien de réinitialisation vient de partir. Il reste valable deux heures. Pensez à regarder vos courriers indésirables.', 'fg_bad' => 'Cette adresse e-mail ne semble pas valide.', 'fg_err' => 'Le message n’a pas pu partir. Vérifiez votre connexion et réessayez.', 'fg_resend' => 'Renvoyer le lien', 'fg_wait' => 'Renvoyer dans :s s',
 'lf_h' => 'Mot de passe incorrect', 'lf_p' => 'L\'adresse e-mail ou le mot de passe saisi est incorrect. Vérifiez vos informations et réessayez.', 'lf_retry' => 'Réessayer',
 'tfa_h' => 'Vérification en deux étapes', 'tfa_s' => 'Ouvrez votre application d’authentification et saisissez le code à 6 chiffres. Vous pouvez aussi utiliser un code de secours.', 'tfa_code' => 'Code', 'tfa_btn' => 'Valider', 'tfa_busy' => 'Vérification…', 'tfa_err' => 'Vérification impossible. Réessayez.', 'busy_login' => 'Connexion…', 'busy_sign' => 'Création…', 'ok_login' => 'Connexion réussie, redirection…',
 'ok_sign' => 'Compte créé. Consultez votre boîte mail pour l’activer.', 'mail_warn' => ' Si rien n’arrive, vérifiez vos courriers indésirables.',
 'auth_req' => 'Connectez-vous pour accéder à cette évaluation.', 'close' => 'Fermer',
],
'en' => [
 'title' => 'StudyVibe — Live exams that hold up',
 'desc' => 'The whole cohort starts on the same second, grades arrive without marking by hand, and every pass becomes a certificate anyone can verify. For universities and training centres.',
 'nav_eval' => 'Evaluations', 'nav_how' => 'How it works', 'nav_live' => 'Live exams', 'nav_who' => 'Who it’s for',
 'login' => 'Sign in', 'join' => 'Create account', 'theme' => 'Toggle theme', 'menu' => 'Menu',
 'kicker' => 'Live exams for higher education and training',
 'h1' => 'Live exams <em>that hold up.</em>',
 'lede' => 'The whole cohort starts on the same second, grades arrive without marking by hand, and every pass becomes a certificate anyone can verify. The courses are there to prepare the room.',
 'cta1' => 'Get started free', 'cta2' => 'See a live exam',
 'note' => 'Tested with 300 simulated students connected at the same time. In the browser, nothing to install.',
 'room_live' => 'LIVE', 'room_title' => 'INF242 · End-of-module quiz', 'room_count' => '47 connected',
 'room_q_n' => 'Question 6 of 20', 'room_q' => 'What is the time complexity of a lookup in a balanced binary search tree?',
 'room_o' => ['O(1)', 'O(log n)', 'O(n)', 'O(n log n)'],
 'study_alt' => 'A student revising at her computer', 'cert_alt' => 'A student looking at her certificate', 'end_alt' => 'Graduates celebrating', 'end_p' => 'Create your account, set up the session, and let the room fill up. The rest takes care of itself.',
 'art_alt' => 'A student reading on a couch', 'live_alt' => 'A student following a live session on a laptop',
 's1_k' => 'How it works', 's1_h' => 'Three moments, one account.',
 's1_p' => 'A student signs up, learns, gets assessed, then walks away with proof they can show anyone.',
 'm1_h' => 'Learn', 'm1_p' => 'Courses split into chapters and lessons. Students read the text, watch the video, open the PDF, all without leaving the page.',
 'm1_l' => ['Personal notes tied to the minute of the video', 'Lesson quizzes that unlock after reading', 'AI assistant to summarise or explain a passage', 'Progress saved across devices'],
 'm2_h' => 'Get assessed', 'm2_p' => 'The teacher starts a session, everyone begins on the same second, and grades arrive without marking by hand.',
 'm2_l' => ['Exams synchronised on the server clock', 'Question import from a CSV file', 'Open assignments with a deadline', 'Grades exported to Excel and PDF'],
 'm3_h' => 'Get recognised', 'm3_p' => 'Every pass produces a PDF certificate with a unique code. A recruiter can check it in seconds.',
 'm3_l' => ['Public verification code', 'Transcript per student', 'Automatic or manual issuing', 'API to connect your registrar system'],
 's2_k' => 'Live exams', 's2_h' => 'A waiting room, one signal, and everyone starts together.',
 's2_p' => 'The session is prepared ahead of time. Students arrive in the room, see how many classmates are there, and the exam begins at the scheduled time.',
 'f' => [['Synchronised', 'The countdown follows the server clock, not each phone’s own clock.'], ['No install', 'Everything runs in the browser. No WebSocket to configure, no app to download.'], ['For teachers', 'Pause, resume, reset and live session statistics.'], ['For students', 'Per-question timer, readable math formulas, result sent by email.']],
 's3_k' => 'Who it’s for', 's3_h' => 'Three roles, three spaces.',
 'r' => [
   ['Student', 'Learner', ['Course catalogue and one-click enrolment', 'Reader with notes and AI assistant', 'Results, transcript and certificates in one place']],
   ['Teacher', 'Teacher', ['Courses, chapters, lessons, media', 'Live sessions and open assignments', 'Grades, statistics and question moderation']],
   ['Institution admin', 'Institution admin', ['Teacher and student accounts', 'Certificates, newsletters, API keys', 'Overview of the whole institution']]],
 's4_k' => 'Certificates', 's4_h' => 'A certificate you can check.', 's4_p' => 'Got a code with a certificate? Paste it here to confirm it is genuine.',
 'v_ph' => 'Certificate code', 'v_btn' => 'Verify',
 'cert_k' => 'Certificate of achievement', 'cert_h' => 'Module completed', 'cert_p' => 'Awarded to a student who passed every assessment in the module.', 'cert_c' => 'Code', 'cert_ex' => 'example',
 'end_h' => 'Ready to open <em>your first session?</em>',
 'foot_c' => 'All rights reserved.', 'foot_p' => 'Privacy',
 'a_login_h' => 'Welcome back', 'a_login_s' => 'Sign in to pick up where you left off.',
 'email' => 'Email address', 'email_ph' => 'you@example.com', 'pw' => 'Password', 'show' => 'Show', 'hide' => 'Hide',
 'forgot' => 'Forgot your password?', 'forgot_s' => 'Enter your email and we will send a link to choose a new one.', 'forgot_btn' => 'Send the link', 'back' => 'Back',
 'no_acc' => 'No account yet?', 'has_acc' => 'Already registered?',
 'a_sign_h' => 'Create your account', 'a_sign_s' => 'Two steps, under a minute.',
 'role_s' => 'Student', 'role_s_d' => 'I follow courses', 'role_t' => 'Teacher', 'role_t_d' => 'I create and run them',
 'cont' => 'Continue', 'name' => 'Full name', 'name_ph' => 'Marie Curie', 'pw_ph' => 'At least 8 characters', 'pw_h' => 'At least 8 characters.',
 'phone' => 'Phone number', 'phone_ph' => '6 12 34 56 78', 'phone_h' => 'For exam reminders and account security. We will verify it by SMS after you sign in.', 'news' => 'Receive the newsletter (optional)', 'create' => 'Create my account',
 'net_err' => 'The server did not answer as expected. Check your connection and try again in a moment.', 'fg_busy' => 'Sending…', 'fg_ok_h' => 'Link sent', 'fg_ok_p' => 'If an account exists for :email, a reset link is on its way. It stays valid for two hours. Check your spam folder too.', 'fg_bad' => 'That email address does not look valid.', 'fg_err' => 'The message could not be sent. Check your connection and try again.', 'fg_resend' => 'Send again', 'fg_wait' => 'Send again in :s s',
 'lf_h' => 'Incorrect password', 'lf_p' => 'The email or password you entered is incorrect. Please check your details and try again.', 'lf_retry' => 'Try again',
 'tfa_h' => 'Two-step verification', 'tfa_s' => 'Open your authenticator app and enter the 6-digit code. You can also use a recovery code.', 'tfa_code' => 'Code', 'tfa_btn' => 'Verify', 'tfa_busy' => 'Checking…', 'tfa_err' => 'Could not verify. Please try again.', 'busy_login' => 'Signing in…', 'busy_sign' => 'Creating…', 'ok_login' => 'Signed in, redirecting…',
 'ok_sign' => 'Account created. Check your inbox to activate it.', 'mail_warn' => ' If nothing arrives, look in your spam folder.',
 'auth_req' => 'Sign in to access this evaluation.', 'close' => 'Close',
],
][$lang];
$e = fn(string $k): string => htmlspecialchars((string)$T[$k], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e('title') ?></title>
<meta name="description" content="<?= $e('desc') ?>">
<meta name="theme-color" content="#F5F0E6">
<?= Brand::headLinks() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<link rel="stylesheet" href="/assets/css/landing.css">
<?= csrfMetaTag(); ?>
<script>
  document.documentElement.classList.add('js');
  try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
</script>
</head>
<body class="v2">

<header class="nav" id="nav">
  <div class="wrap nav-in">
    <a href="#top" class="brand" aria-label="StudyVibe"><?= Brand::logo('md') ?></a>
    <nav class="nav-links" aria-label="Main">
      <a href="#parcours"><?= $e('nav_how') ?></a>
      <a href="#direct"><?= $e('nav_live') ?></a>
      <a href="#roles"><?= $e('nav_who') ?></a>
      <a href="/evaluations.php"><?= $e('nav_eval') ?></a>
    </nav>
    <div class="nav-tools">
      <div class="seg" role="group" aria-label="Language">
        <a href="#" onclick="changeLanguage('fr');return false;" <?= $lang === 'fr' ? 'aria-current="true"' : '' ?>>FR</a>
        <a href="#" onclick="changeLanguage('en');return false;" <?= $lang === 'en' ? 'aria-current="true"' : '' ?>>EN</a>
      </div>
      <button class="chip" type="button" data-dark-toggle aria-label="<?= $e('theme') ?>"><svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 2a6 6 0 0 1 0 12z" fill="currentColor"/></svg></button>
      <button class="btn btn-ghost hide-sm" type="button" data-open-auth="login"><?= $e('login') ?></button>
      <button class="btn btn-primary" type="button" data-open-auth="signup"><?= $e('join') ?></button>
    </div>
  </div>
</header>

<main id="top">
<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <div class="hero-logo"><?= Brand::logo('xl', true) ?></div>
      <p class="kicker"><?= $e('kicker') ?></p>
      <h1 style="margin-top:1rem"><?= $T['h1'] ?></h1>
      <p class="lede"><?= $e('lede') ?></p>
      <div class="hero-cta">
        <button class="btn btn-primary btn-lg" type="button" data-open-auth="signup"><?= $e('cta1') ?></button>
        <a class="btn btn-ghost btn-lg" href="#direct"><?= $e('cta2') ?></a>
      </div>
      <p class="hero-note"><?= $e('note') ?></p>
    </div>

    <figure class="hero-art" aria-label="<?= $e('room_title') ?>">
      <div class="art-blob" aria-hidden="true"></div>
      <div class="art-anim" data-lottie="/assets/anim/hero-reader-couch.json" role="img" aria-label="<?= $e('art_alt') ?>"></div>
      <span class="chip-float cf-live"><span class="live"><i></i><?= $e('room_live') ?></span><b class="num"><?= $e('room_count') ?></b></span>
      <span class="chip-float cf-cert"><span class="tick" aria-hidden="true">✓</span><code>SV-7K2Q-94MD</code></span>
      <span class="chip-float cf-score"><span class="num">17<small>/20</small></span></span>
    </figure>
  </div>
</section>

<section class="sec alt wavy" id="parcours">
  <div class="wrap">
    <div class="head-row">
      <div class="sec-head rv">
        <p class="kicker"><?= $e('s1_k') ?></p>
        <h2><?= $e('s1_h') ?></h2>
        <p><?= $e('s1_p') ?></p>
      </div>
      <div class="head-art rv" style="--d:.1s"><div class="art-blob" aria-hidden="true"></div><div class="art-anim" data-lottie="/assets/anim/parcours-study.json" role="img" aria-label="<?= $e('study_alt') ?>"></div></div>
    </div>
    <div class="moments">
      <?php foreach ([1, 2, 3] as $i): ?>
      <article class="moment rv" style="--d:<?= ($i - 1) * .1 ?>s">
        <div class="n">0<?= $i ?></div>
        <div><h3><?= $e("m{$i}_h") ?></h3><p><?= $e("m{$i}_p") ?></p></div>
        <ul><?php foreach ($T["m{$i}_l"] as $li): ?><li><?= htmlspecialchars($li) ?></li><?php endforeach; ?></ul>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec wavy" id="direct">
  <div class="wrap live-grid">
    <div class="live-img rv"><div class="art-anim" data-lottie="/assets/anim/live-laptop.json" role="img" aria-label="<?= $e('live_alt') ?>"></div></div>
    <div class="rv" style="--d:.1s">
      <p class="kicker"><?= $e('s2_k') ?></p>
      <h2 style="font-size:clamp(2rem,4vw,3.1rem);margin-top:.8rem;font-weight:450"><?= $e('s2_h') ?></h2>
      <p style="margin-top:1rem;color:var(--ink-2);font-size:1.1rem"><?= $e('s2_p') ?></p>
      <dl class="facts">
        <?php foreach ($T['f'] as $f): ?><div class="fact"><dt><?= htmlspecialchars($f[0]) ?></dt><dd><?= htmlspecialchars($f[1]) ?></dd></div><?php endforeach; ?>
      </dl>
    </div>
  </div>
</section>

<section class="sec alt wavy" id="roles">
  <div class="wrap">
    <div class="sec-head rv"><p class="kicker"><?= $e('s3_k') ?></p><h2><?= $e('s3_h') ?></h2></div>
    <div class="roles">
      <?php foreach ($T['r'] as $n => $r): ?>
      <article class="role rv" style="--d:<?= $n * .08 ?>s">
        <div class="role-art" data-lottie="/assets/anim/<?= ['role-student','role-teacher','role-promoter'][$n] ?>.json" aria-hidden="true"></div>
        <span class="tag"><?= htmlspecialchars($r[1]) ?></span>
        <h3><?= htmlspecialchars($r[0]) ?></h3>
        <ul><?php foreach ($r[2] as $li): ?><li><?= htmlspecialchars($li) ?></li><?php endforeach; ?></ul>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec wavy">
  <div class="wrap verify">
    <div class="rv">
      <p class="kicker"><?= $e('s4_k') ?></p>
      <h2 style="font-size:clamp(2rem,4vw,3.1rem);margin-top:.8rem;font-weight:450"><?= $e('s4_h') ?></h2>
      <p style="margin-top:1rem;color:var(--ink-2);font-size:1.1rem"><?= $e('s4_p') ?></p>
      <form class="verify-form" action="/verify.php" method="get">
        <input class="input" name="code" required placeholder="<?= $e('v_ph') ?>" aria-label="<?= $e('v_ph') ?>" autocomplete="off" spellcheck="false">
        <button class="btn btn-primary" type="submit"><?= $e('v_btn') ?></button>
      </form>
    </div>
    <div class="verify-side rv" style="--d:.1s">
    <div class="verify-art"><div class="art-blob" aria-hidden="true"></div><div class="art-anim" data-lottie="/assets/anim/verify-certificate.json" role="img" aria-label="<?= $e('cert_alt') ?>"></div></div>
    <div class="cert" aria-hidden="true">
      <div class="k"><?= $e('cert_k') ?></div>
      <h4><?= $e('cert_h') ?></h4>
      <p><?= $e('cert_p') ?></p>
      <div class="code"><span><?= $e('cert_c') ?> · <?= $e('cert_ex') ?></span><code>SV-7K2Q-94MD</code></div>
    </div>
    </div>
  </div>
</section>

<section class="closing wavy">
  <div class="wrap closing-grid">
    <div class="rv">
      <h2><?= $T['end_h'] ?></h2>
      <p class="closing-p"><?= $e('end_p') ?></p>
      <div class="hero-cta" style="animation:none;margin-top:2rem">
        <button class="btn btn-primary btn-lg" type="button" data-open-auth="signup"><?= $e('join') ?></button>
        <button class="btn btn-ghost btn-lg" type="button" data-open-auth="login"><?= $e('login') ?></button>
      </div>
    </div>
    <div class="closing-art rv" style="--d:.12s"><div class="art-anim" data-lottie="/assets/anim/closing-graduates.json" role="img" aria-label="<?= $e('end_alt') ?>"></div></div>
  </div>
</section>
</main>

<footer class="foot"><div class="wrap foot-in">
  <a href="#top" class="brand" aria-label="StudyVibe"><?= Brand::logo('sm') ?></a>
  <span>© <?= date('Y') ?> StudyVibe. <?= $e('foot_c') ?> · <a href="/privacy.php"><?= $e('foot_p') ?></a></span>
</div></footer>

<!-- Auth dialog: login, password reset and two-step signup -->
<dialog class="auth" id="auth" aria-labelledby="auth-title">
  <div class="auth-in">
    <button class="auth-x" type="button" data-close-auth aria-label="<?= $e('close') ?>">×</button>
    <a class="brand" href="#" onclick="return false" aria-label="StudyVibe"><?= Brand::logo('sm') ?></a>

    <div class="login-fail" id="login-fail" role="alertdialog" aria-labelledby="lf-h" aria-describedby="lf-p" hidden>
      <div class="login-fail-card">
        <div class="login-fail-ic" aria-hidden="true">!</div>
        <h3 id="lf-h"><?= $e('lf_h') ?></h3>
        <p id="lf-p"><?= $e('lf_p') ?></p>
        <button type="button" class="btn btn-primary" id="lf-retry"><?= $e('lf_retry') ?></button>
      </div>
    </div>

    <section class="view" id="view-login">
      <h2 id="auth-title"><?= $e('a_login_h') ?></h2>
      <p class="sub"><?= $e('a_login_s') ?></p>
      <form id="login-form" novalidate>
        <div class="field"><label for="login-email"><?= $e('email') ?></label>
          <input class="input" type="email" id="login-email" name="email" required autocomplete="email" placeholder="<?= $e('email_ph') ?>"></div>
        <div class="field"><label for="login-password"><?= $e('pw') ?></label>
          <div class="pw"><input class="input" type="password" id="login-password" name="password" required autocomplete="current-password">
          <button type="button" class="btn-text" data-toggle-pw="login-password" data-show="<?= $e('show') ?>" data-hide="<?= $e('hide') ?>"><?= $e('show') ?></button></div></div>
        <button class="btn btn-primary btn-lg" type="submit" id="login-btn"><?= $e('login') ?></button>
      </form>
      <p class="auth-foot"><button type="button" class="btn-text" data-view="forgot"><?= $e('forgot') ?></button></p>
      <p class="auth-foot"><?= $e('no_acc') ?> <button type="button" class="btn-text" data-view="signup"><?= $e('join') ?></button></p>
    </section>

    <section class="view" id="view-tfa" hidden>
      <h2><?= $e('tfa_h') ?></h2>
      <p class="sub"><?= $e('tfa_s') ?></p>
      <form id="tfa-form" novalidate>
        <div class="field"><label for="tfa-code"><?= $e('tfa_code') ?></label>
          <input class="input" type="text" id="tfa-code" name="code" inputmode="text" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" maxlength="12" required placeholder="123456"></div>
        <div class="fg-status" id="tfa-status" role="alert" aria-live="polite" hidden></div>
        <button class="btn btn-primary btn-lg" type="submit" id="tfa-btn"><?= $e('tfa_btn') ?></button>
      </form>
      <p class="auth-foot"><button type="button" class="btn-text" data-view="login"><?= $e('back') ?></button></p>
    </section>

    <section class="view" id="view-forgot" hidden>
      <h2><?= $e('forgot') ?></h2>
      <p class="sub"><?= $e('forgot_s') ?></p>
      <div class="field"><label for="forgot-email"><?= $e('email') ?></label>
        <input class="input" type="email" id="forgot-email" autocomplete="email" placeholder="<?= $e('email_ph') ?>"></div>
      <button class="btn btn-primary btn-lg" type="button" id="forgot-btn"><?= $e('forgot_btn') ?></button>
      <div class="fg-status" id="forgot-status" role="status" aria-live="polite" hidden></div>
      <p class="auth-foot"><button type="button" class="btn-text" data-view="login"><?= $e('back') ?></button></p>
    </section>

    <section class="view" id="view-signup" hidden>
      <h2><?= $e('a_sign_h') ?></h2>
      <p class="sub"><?= $e('a_sign_s') ?></p>
      <form id="signup-form" novalidate>
        <input type="hidden" id="signup-role" name="role" value="">
        <div class="step" id="signup-step-1">
          <div class="roles-pick">
            <button type="button" class="pick" data-role="student" aria-pressed="false"><strong><?= $e('role_s') ?></strong><span><?= $e('role_s_d') ?></span></button>
            <button type="button" class="pick" data-role="teacher" aria-pressed="false"><strong><?= $e('role_t') ?></strong><span><?= $e('role_t_d') ?></span></button>
          </div>
          <button class="btn btn-primary btn-lg" type="button" id="signup-next" disabled><?= $e('cont') ?></button>
        </div>
        <div class="step" id="signup-step-2" hidden>
          <div class="field"><label for="signup-name"><?= $e('name') ?></label>
            <input class="input" id="signup-name" name="name" required autocomplete="name" placeholder="<?= $e('name_ph') ?>"></div>
          <div class="field"><label for="signup-email"><?= $e('email') ?></label>
            <input class="input" type="email" id="signup-email" name="email" required autocomplete="email" placeholder="<?= $e('email_ph') ?>"></div>
          <div class="field"><label for="signup-phone"><?= $e('phone') ?></label>
            <input class="input" type="tel" inputmode="tel" id="signup-phone" name="phone" required autocomplete="tel" maxlength="30" placeholder="<?= $e('phone_ph') ?>"><span class="hint"><?= $e('phone_h') ?></span></div>
          <div class="field"><label for="signup-password"><?= $e('pw') ?></label>
            <div class="pw"><input class="input" type="password" id="signup-password" name="password" required minlength="8" autocomplete="new-password" placeholder="<?= $e('pw_ph') ?>">
            <button type="button" class="btn-text" data-toggle-pw="signup-password" data-show="<?= $e('show') ?>" data-hide="<?= $e('hide') ?>"><?= $e('show') ?></button></div>
            <div class="meter"><i id="pw-bar"></i></div><span class="hint" id="hint-password"><?= $e('pw_h') ?></span></div>
          <label class="check"><input type="checkbox" id="signup-newsletter" name="newsletter" value="1"><span><?= $e('news') ?></span></label>
          <div class="auth-actions">
            <button class="btn btn-ghost" type="button" id="signup-back"><?= $e('back') ?></button>
            <button class="btn btn-primary btn-lg" type="submit" id="signup-btn" disabled><?= $e('create') ?></button>
          </div>
        </div>
            <div class="fg-status" id="signup-status" role="status" aria-live="polite" hidden></div>
      </form>
      <p class="auth-foot"><?= $e('has_acc') ?> <button type="button" class="btn-text" data-view="login"><?= $e('login') ?></button></p>
    </section>
  </div>
</dialog>

<script>window.SV_T = <?= json_encode(array_intersect_key($T, array_flip(['net_err','fg_busy','fg_ok_h','fg_ok_p','fg_bad','fg_err','fg_resend','fg_wait','forgot_btn','lf_h','lf_p','lf_retry','busy_login','busy_sign','ok_login','ok_sign','mail_warn','tfa_err','tfa_btn','tfa_busy','auth_req','login','create','show','hide'])), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie_light.min.js" defer></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/landing.js"></script>
</body>
</html>
