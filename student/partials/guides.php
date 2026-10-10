<?php
/**
 * Student guides: illustrated cards, one per question, each opening a full-page reader.
 * Content: guides-content-fr.php / guides-content-en.php (same ids). Layout: lib/GuideKit.php. Needs sd() / sdLang().
 * Illustrations: assets/img/student-guides/<id>.svg (unDraw, MIT, recoloured).
 */
require_once __DIR__ . '/../../lib/GuideKit.php';

$sgLang = function_exists('sdLang') ? sdLang() : 'fr';
$sgContent = require __DIR__ . '/guides-content-' . ($sgLang === 'en' ? 'en' : 'fr') . '.php';

$SG = [
'fr' => [
 'hero' => ['kicker' => 'Aide', 'title' => 'Une question ? La réponse est ici.', 'lede' => 'Compte, cours, leçons, devoirs, évaluations, certificats : tout ce qu’un étudiant peut se demander, expliqué simplement et pas à pas. Choisissez une carte, la réponse s’ouvre en pleine page.',
  'steps' => ['Trouvez la carte qui ressemble à votre question', 'Lisez la réponse, étape par étape', 'Passez à la suivante ou écrivez-nous si besoin']],
 'ui' => ['all' => 'Tout', 'search' => 'Chercher dans l’aide…', 'read' => 'Lire', 'back' => 'Toute l’aide', 'prev' => 'Précédent', 'next' => 'Suivant', 'none' => 'Aucune carte ne correspond à votre recherche.'],
 'cats' => ['start' => 'Démarrer', 'learn' => 'Apprendre et rendre', 'eval' => 'Évaluations et certificats', 'help' => 'Aide et confidentialité'],
 'sections' => [
  'account' => ['Mon compte', 'Créer, confirmer et sécuriser votre compte, et rester informé.'],
  'course' => ['Rejoindre un cours', 'Lien, clé d’inscription, calendrier : tout pour entrer dans un cours.'],
  'lessons' => ['Suivre les leçons', 'Contenus, quiz, notes, assistant et questions à l’enseignant.'],
  'assignments' => ['Les devoirs', 'Rendre son travail, comprendre sa note, demander une révision.'],
  'evals' => ['Les évaluations', 'En direct ou libres : rejoindre, répondre, retrouver son résultat.'],
  'certs' => ['Certification et résultats', 'L’examen final, le certificat vérifiable et votre relevé.'],
  'help' => ['Aide et vie privée', 'Écrire à votre enseignant, vos données, votre équipement.'],
 ],
],
'en' => [
 'hero' => ['kicker' => 'Help', 'title' => 'A question? The answer is here.', 'lede' => 'Account, courses, lessons, assignments, evaluations, certificates: everything a student may wonder about, explained simply and step by step. Pick a card and the answer opens full page.',
  'steps' => ['Find the card that looks like your question', 'Read the answer, step by step', 'Go to the next one or write to us if needed']],
 'ui' => ['all' => 'All', 'search' => 'Search the help…', 'read' => 'Read', 'back' => 'All help', 'prev' => 'Previous', 'next' => 'Next', 'none' => 'No card matches your search.'],
 'cats' => ['start' => 'Get started', 'learn' => 'Learn and hand in', 'eval' => 'Evaluations and certificates', 'help' => 'Help and privacy'],
 'sections' => [
  'account' => ['My account', 'Create, confirm and secure your account, and stay informed.'],
  'course' => ['Joining a course', 'Link, enrolment key, calendar: everything to get into a course.'],
  'lessons' => ['Following lessons', 'Content, quizzes, notes, assistant and questions to the teacher.'],
  'assignments' => ['Assignments', 'Handing in your work, understanding your mark, asking for a review.'],
  'evals' => ['Evaluations', 'Live or free: join, answer, find your result.'],
  'certs' => ['Certification and results', 'The final exam, the verifiable certificate and your transcript.'],
  'help' => ['Help and privacy', 'Writing to your teacher, your data, your equipment.'],
 ],
],
];
$S = $SG[$sgLang === 'en' ? 'en' : 'fr'];
$sgLayout = [
 ['account', 'start', '#2f6f8f', ['create-account', 'email-confirm', 'login-help', 'profile', 'security', 'notifications']],
 ['course', 'start', '#3f6b5a', ['join-course', 'enroll-key', 'enroll-blocked', 'my-courses', 'library']],
 ['lessons', 'learn', '#b5472b', ['lesson-flow', 'lesson-locked', 'video-pdf', 'lesson-quiz', 'notes', 'ai', 'ask-teacher']],
 ['assignments', 'learn', '#7a4b8a', ['asg-submit', 'asg-deadline', 'asg-mark', 'asg-review', 'asg-original']],
 ['evals', 'eval', '#c98a2b', ['eval-kinds', 'eval-join', 'eval-reminder', 'eval-prepare', 'eval-during', 'eval-connection', 'eval-integrity', 'eval-results', 'eval-cancelled', 'eval-missed']],
 ['certs', 'eval', '#1f7a7a', ['final-exam', 'certificate', 'transcript']],
 ['help', 'help', '#5a6b7a', ['contact-teacher', 'privacy', 'devices', 'still-stuck']],
];
$sgItems = [];
$sgSections = [];
foreach ($sgLayout as [$sid, $cat, $color, $ids]) {
    foreach ($ids as $id) {
        $sgItems[$id] = ['q' => $sgContent[$id]['q'], 'a' => $sgContent[$id]['a'], 'teaser' => $sgContent[$id]['t'], 'img' => '/assets/img/student-guides/' . $id . '.svg'];
    }
    $sgSections[] = ['id' => $sid, 'cat' => $cat, 'color' => $color, 'title' => $S['sections'][$sid][0], 'lede' => $S['sections'][$sid][1], 'items' => $ids];
}
GuideKit::render([
    'prefix' => 'sg', 'main' => true, 'class' => 'gd--student', 'heading' => 'h1',
    'switchFn' => 'switchTab', 'switchArg' => 'help',
    'hero' => $S['hero'] + ['img' => '/assets/img/student-guides/hero.svg'],
    'cats' => $S['cats'], 'sections' => $sgSections, 'items' => $sgItems, 'ui' => $S['ui'],
]);
?>
<style>
.gd--student { --gd-sticky: 0px; padding-bottom: 2rem; }
@media (max-width: 900px) { .gd--student { --gd-sticky: 3.6rem; } }
</style>
