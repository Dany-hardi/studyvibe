<?php
/**
 * Teacher guides (marking + import): illustrated cards, each opens its answer in a full reader.
 * Content comes from marking-faq.php and live-import-faq.php (they return their items). Needs $tdLang.
 * Before including: $gdPrefix (unique id prefix, default 'gd'), $gdOnly (null or ['marking'] / ['import']), $gdHero (bool, default true).
 * Illustrations: assets/img/guides/<id>.svg (unDraw, MIT, recoloured).
 */
$gdLang = (isset($tdLang) && $tdLang === 'en') ? 'en' : 'fr';
$gdP = preg_replace('/[^a-z0-9-]/i', '', (string)($gdPrefix ?? 'gd')) ?: 'gd';
$gdOnlyCats = isset($gdOnly) && is_array($gdOnly) ? $gdOnly : null;
$gdShowHero = $gdHero ?? true;

$gdMk = include __DIR__ . '/marking-faq.php';
$gdIm = include __DIR__ . '/live-import-faq.php';
$gdItems = [];
foreach ($gdMk['items'] as $it) { $gdItems[$it['id']] = $it + ['fill' => static fn($h) => $h]; }
foreach ($gdIm['items'] as $it) { $gdItems[$it['id']] = $it + ['fill' => $gdIm['fill']]; }

$GD = [
'fr' => [
 'kicker' => 'Aide', 'title' => 'Guides et aide',
 'lede' => 'Noter, répondre à vos étudiants, importer vos questions : tout ce qu’il faut savoir, sans jargon. Choisissez une carte, la réponse s’ouvre en pleine page.',
 'steps' => ['Choisissez la carte qui correspond à votre question', 'Lisez la réponse, pas à pas, en pleine page', 'Passez à la carte suivante ou revenez à la liste'],
 'search' => 'Chercher dans les guides…', 'all' => 'Tout', 'cat_marking' => 'Notation', 'cat_import' => 'Import des questions',
 'read' => 'Lire', 'back' => 'Tous les guides', 'prev' => 'Précédent', 'next' => 'Suivant', 'none' => 'Aucune carte ne correspond à votre recherche.',
 'cards' => 'cartes', 'card' => 'carte', 'tpl_h' => 'Modèles à télécharger',
 'cats' => ['marking' => 'Notation', 'import' => 'Import des questions'],
 'sections' => [
  'auto' => ['Les notes automatiques', 'Quiz de leçon, examen final, évaluation en direct : la plateforme calcule, vous n’avez rien à saisir.'],
  'assign' => ['Noter les devoirs', 'Barème, copie annotée, publication des notes, ressemblances et exports : ce que vous faites à la main.'],
  'talk' => ['Répondre aux étudiants', 'Résultat contesté, demande de révision, question sous une leçon : quoi faire et ce que l’étudiant reçoit.'],
  'prep' => ['Préparer vos questions', 'Choisir la bonne méthode, remplir le fichier, écrire vrai/faux, réponses écrites, durées et formules.'],
  'zip' => ['Les packs ZIP avec images', 'Construire le pack, nommer les images, écrire le fichier qui les relie aux questions.'],
  'fix' => ['Vérifier et corriger', 'Lire le rapport, comprendre les erreurs, connaître les limites, construire un examen solide.'],
 ],
 'teasers' => [
  'map' => 'Quelles notes existent, qui les calcule, où les trouver.', 'quiz' => 'Réponse enregistrée au clic, score en pourcentage.', 'final' => '80 % pour réussir, 3 essais par jour, certificat par e-mail.', 'live' => 'Bonnes réponses ÷ total × 100, sans points négatifs.',
  'asg' => 'Barème, copie annotée, nouvelle version, retard : pas à pas.', 'release' => 'Publication immédiate ou après votre approbation.', 'similar' => 'Comparer les travaux entre eux et voir les passages communs.', 'exports' => 'Excel, PDF et CSV avec le matricule.',
  'cancel' => 'Un résultat annulé peut être contesté : rétablir ou maintenir.', 'requests' => 'Une demande de révision arrive : quoi faire, ce que l’étudiant reçoit.', 'qa' => 'Les questions sous une leçon et vos réponses.',
  'choose' => 'Formulaire, CSV, Excel ou ZIP : laquelle des méthodes ?', 'single' => 'Ajouter une question avec une image en trois clics.', 'csv-columns' => 'Les colonnes du fichier, leur contenu et leurs noms acceptés.', 'csv-excel' => 'Enregistrer en CSV UTF-8 depuis Excel, LibreOffice ou Sheets.', 'types' => 'Vrai/faux, réponse écrite, durée par question, formules.',
  'zip-steps' => 'Créer le pack, l’envoyer, lire l’aperçu.', 'naming' => 'Toutes les règles pour nommer et préparer les images.', 'zip-csv' => 'Le fichier questions.csv qui relie les images aux questions.',
  'checks' => 'Ce que l’application vérifie avant d’importer.', 'errors' => 'Chaque message d’erreur, sa cause et sa solution.', 'limits' => 'Tailles, nombres de questions et de fichiers.', 'after' => 'Voir, corriger, supprimer ou recommencer.', 'tips' => 'Les conseils pour un examen bien construit.',
 ],
],
'en' => [
 'kicker' => 'Help', 'title' => 'Guides and help',
 'lede' => 'Marking, answering your students, importing your questions: everything you need to know, no jargon. Pick a card and the answer opens full page.',
 'steps' => ['Pick the card that matches your question', 'Read the answer, step by step, full page', 'Go to the next card or return to the list'],
 'search' => 'Search the guides…', 'all' => 'All', 'cat_marking' => 'Marking', 'cat_import' => 'Importing questions',
 'read' => 'Read', 'back' => 'All guides', 'prev' => 'Previous', 'next' => 'Next', 'none' => 'No card matches your search.',
 'cards' => 'cards', 'card' => 'card', 'tpl_h' => 'Templates to download',
 'cats' => ['marking' => 'Marking', 'import' => 'Importing questions'],
 'sections' => [
  'auto' => ['Automatic marks', 'Lesson quizzes, the final exam, live evaluations: the platform calculates, you enter nothing.'],
  'assign' => ['Marking assignments', 'Rubric, annotated copy, release of marks, similarity and exports: what you do by hand.'],
  'talk' => ['Answering students', 'Contested result, review request, question under a lesson: what to do and what the student receives.'],
  'prep' => ['Preparing your questions', 'Pick the right method, fill the file, write true/false, written answers, times and formulas.'],
  'zip' => ['ZIP packages with pictures', 'Build the package, name the pictures, write the file that links them to the questions.'],
  'fix' => ['Check and fix', 'Read the report, understand the errors, know the limits, build a solid exam.'],
 ],
 'teasers' => [
  'map' => 'Which marks exist, who calculates them, where to find them.', 'quiz' => 'Answer saved on click, score as a percentage.', 'final' => '80 % to pass, 3 tries a day, certificate by email.', 'live' => 'Correct answers ÷ total × 100, no negative marking.',
  'asg' => 'Rubric, annotated copy, new version, late work: step by step.', 'release' => 'Immediate release or after your approval.', 'similar' => 'Compare works with each other and see the shared passages.', 'exports' => 'Excel, PDF and CSV with the matricule.',
  'cancel' => 'A cancelled result can be contested: restore or uphold.', 'requests' => 'A review request arrives: what to do, what the student receives.', 'qa' => 'Questions under a lesson and your replies.',
  'choose' => 'Form, CSV, Excel or ZIP: which method?', 'single' => 'Add one question with a picture in three clicks.', 'csv-columns' => 'The file columns, their content and accepted names.', 'csv-excel' => 'Save as UTF-8 CSV from Excel, LibreOffice or Sheets.', 'types' => 'True/false, written answer, time per question, formulas.',
  'zip-steps' => 'Build the package, send it, read the preview.', 'naming' => 'All the rules to name and prepare pictures.', 'zip-csv' => 'The questions.csv that links pictures to questions.',
  'checks' => 'What the application checks before importing.', 'errors' => 'Each error message, its cause and its fix.', 'limits' => 'Sizes, numbers of questions and files.', 'after' => 'See, fix, delete or start over.', 'tips' => 'Tips for a well-built exam.',
 ],
],
];
$G = $GD[$gdLang];
$gdSections = [
 ['id' => 'auto', 'cat' => 'marking', 'color' => '#b5472b', 'items' => ['map', 'quiz', 'final', 'live']],
 ['id' => 'assign', 'cat' => 'marking', 'color' => '#3f6b5a', 'items' => ['asg', 'release', 'similar', 'exports']],
 ['id' => 'talk', 'cat' => 'marking', 'color' => '#c98a2b', 'items' => ['cancel', 'requests', 'qa']],
 ['id' => 'prep', 'cat' => 'import', 'color' => '#3b5b7a', 'items' => ['choose', 'single', 'csv-columns', 'csv-excel', 'types']],
 ['id' => 'zip', 'cat' => 'import', 'color' => '#7a4b8a', 'items' => ['zip-steps', 'naming', 'zip-csv']],
 ['id' => 'fix', 'cat' => 'import', 'color' => '#b5472b', 'items' => ['checks', 'errors', 'limits', 'after', 'tips']],
];
if ($gdOnlyCats) { $gdSections = array_values(array_filter($gdSections, fn($s) => in_array($s['cat'], $gdOnlyCats, true))); }
$e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/../../lib/GuideKit.php';
$gdCfgItems = [];
foreach ($gdSections as $s) {
    foreach ($s['items'] as $id) {
        if (!isset($gdItems[$id])) { continue; }
        $gdCfgItems[$id] = ['q' => $gdItems[$id]['q'], 'a' => $gdItems[$id]['a'], 'teaser' => $G['teasers'][$id], 'img' => '/assets/img/guides/' . $id . '.svg', 'fill' => $gdItems[$id]['fill']];
    }
}
$gdSecCfg = [];
foreach ($gdSections as $s) { $gdSecCfg[] = ['id' => $s['id'], 'cat' => $s['cat'], 'color' => $s['color'], 'title' => $G['sections'][$s['id']][0], 'lede' => $G['sections'][$s['id']][1], 'items' => $s['items']]; }
$gdAfter = '';
if (!$gdOnlyCats || in_array('import', $gdOnlyCats, true)) {
    $gdAfter = '<div class="gd-tpl"><strong>' . $e($G['tpl_h']) . '</strong>'
        . '<a class="t-btn t-btn-ghost" href="/teacher/bulk-import.php?template=1" download>' . $e($gdIm['tpl_zip']) . '</a>'
        . '<a class="t-btn t-btn-ghost" href="/teacher/sample-questions.csv" download>' . $e($gdIm['tpl_csv']) . '</a></div>';
}
GuideKit::render([
    'prefix' => $gdP, 'main' => $gdP === 'gd', 'switchFn' => 'switchDashboardTab', 'switchArg' => 'tab-help',
    'hero' => $gdShowHero ? ['kicker' => $G['kicker'], 'title' => $G['title'], 'lede' => $G['lede'], 'steps' => $G['steps'], 'img' => '/assets/img/guides/hero.svg'] : null,
    'cats' => array_intersect_key($G['cats'], array_flip($gdOnlyCats ?? ['marking', 'import'])),
    'sections' => $gdSecCfg, 'items' => $gdCfgItems,
    'ui' => ['all' => $G['all'], 'search' => $G['search'], 'read' => $G['read'], 'back' => $G['back'], 'prev' => $G['prev'], 'next' => $G['next'], 'none' => $G['none']],
    'after' => $gdAfter,
]);
