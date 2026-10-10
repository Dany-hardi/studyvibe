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
$gdFlat = [];
foreach ($gdSections as $s) { foreach ($s['items'] as $id) { if (isset($gdItems[$id])) { $gdFlat[] = ['id' => $id, 'sec' => $s]; } } }
$gdCount = count($gdFlat);
$cats = $gdOnlyCats ?? ['marking', 'import'];
?>
<section class="gd" id="<?= $gdP ?>" data-gd="<?= $gdP ?>">
<div class="gd-list">
<?php if ($gdShowHero): ?>
    <header class="gd-hero">
        <div class="gd-hero-text">
            <p class="gd-kicker"><?= $e($G['kicker']) ?></p>
            <h2><?= $e($G['title']) ?></h2>
            <p class="gd-lede"><?= $e($G['lede']) ?></p>
            <ol class="gd-steps"><?php foreach ($G['steps'] as $i => $st): ?><li><span><?= $i + 1 ?></span><?= $e($st) ?></li><?php endforeach; ?></ol>
        </div>
        <img class="gd-hero-art" src="/assets/img/guides/hero.svg" alt="" width="420" height="380">
    </header>
<?php endif; ?>
    <div class="gd-bar">
        <?php if (count($cats) > 1): ?>
        <div class="gd-chips" role="group">
            <button type="button" class="gd-chip" data-gd-cat="all" aria-pressed="true"><?= $e($G['all']) ?> <small><?= $gdCount ?></small></button>
            <?php foreach ($cats as $c): ?><button type="button" class="gd-chip" data-gd-cat="<?= $c ?>" aria-pressed="false"><?= $e($G['cats'][$c]) ?> <small><?= count(array_filter($gdFlat, fn($f) => $f['sec']['cat'] === $c)) ?></small></button><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <label class="gd-search"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input type="search" data-gd-search placeholder="<?= $e($G['search']) ?>" aria-label="<?= $e($G['search']) ?>"></label>
    </div>
    <?php foreach ($gdSections as $s): [$sTitle, $sLede] = $G['sections'][$s['id']]; ?>
    <div class="gd-sec" data-gd-sec data-cat="<?= $s['cat'] ?>" style="--gd-c: <?= $s['color'] ?>">
        <div class="gd-sec-head"><h3><?= $e($sTitle) ?></h3><p><?= $e($sLede) ?></p></div>
        <div class="gd-grid">
        <?php foreach ($s['items'] as $id): if (!isset($gdItems[$id])) { continue; } $n = array_search($id, array_column($gdFlat, 'id')) + 1; ?>
            <a class="gd-card" href="#<?= $gdP ?>-<?= $id ?>" data-gd-open="<?= $id ?>" data-text="<?= $e(mb_strtolower($gdItems[$id]['q'] . ' ' . $G['teasers'][$id])) ?>">
                <span class="gd-card-art"><img src="/assets/img/guides/<?= $id ?>.svg" alt="" loading="lazy" width="320" height="240"></span>
                <span class="gd-card-n"><?= $n ?></span>
                <span class="gd-card-body"><strong><?= $e($gdItems[$id]['q']) ?></strong><span><?= $e($G['teasers'][$id]) ?></span></span>
                <span class="gd-card-go"><?= $e($G['read']) ?> <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <p class="gd-none" hidden><?= $e($G['none']) ?></p>
    <?php if (!$gdOnlyCats || in_array('import', $gdOnlyCats, true)): ?>
    <div class="gd-tpl"><strong><?= $e($G['tpl_h']) ?></strong>
        <a class="t-btn t-btn-ghost" href="/teacher/bulk-import.php?template=1" download><?= $e($gdIm['tpl_zip']) ?></a>
        <a class="t-btn t-btn-ghost" href="/teacher/sample-questions.csv" download><?= $e($gdIm['tpl_csv']) ?></a>
    </div>
    <?php endif; ?>
</div>
<?php foreach ($gdFlat as $k => $f): $id = $f['id']; $s = $f['sec']; $prev = $gdFlat[$k - 1] ?? null; $next = $gdFlat[$k + 1] ?? null; ?>
<article class="gd-doc" id="<?= $gdP ?>-<?= $id ?>" data-gd-doc="<?= $id ?>" hidden style="--gd-c: <?= $s['color'] ?>">
    <button type="button" class="gd-back" data-gd-back><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg> <?= $e($G['back']) ?></button>
    <header class="gd-doc-head">
        <div>
            <p class="gd-crumb"><?= $e($G['cats'][$s['cat']]) ?> <span>›</span> <?= $e($G['sections'][$s['id']][0]) ?> <span>·</span> <?= $k + 1 ?>/<?= $gdCount ?></p>
            <h3><?= $e($gdItems[$id]['q']) ?></h3>
            <p class="gd-doc-lede"><?= $e($G['teasers'][$id]) ?></p>
        </div>
        <img src="/assets/img/guides/<?= $id ?>.svg" alt="" loading="lazy" width="320" height="240">
    </header>
    <div class="gd-body"><?= ($gdItems[$id]['fill'])($gdItems[$id]['a']) ?></div>
    <nav class="gd-pn">
        <?php foreach ([['prev', $prev], ['next', $next]] as [$dir, $t]): if (!$t): ?><span></span><?php continue; endif; ?>
        <a class="gd-pn-<?= $dir ?>" href="#<?= $gdP ?>-<?= $t['id'] ?>" data-gd-open="<?= $t['id'] ?>" style="--gd-c: <?= $t['sec']['color'] ?>">
            <img src="/assets/img/guides/<?= $t['id'] ?>.svg" alt="" loading="lazy" width="120" height="90">
            <span><small><?= $e($G[$dir]) ?></small><strong><?= $e($gdItems[$t['id']]['q']) ?></strong></span>
        </a>
        <?php endforeach; ?>
    </nav>
</article>
<?php endforeach; ?>
</section>
<?php if (empty($GLOBALS['gdAssetsDone'])): $GLOBALS['gdAssetsDone'] = true; ?>
<style>
.gd { --gd-c: var(--clay, #b5472b); color: var(--ink); }
.gd [hidden] { display: none !important; }
.gd-hero { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr); align-items: center; gap: 2rem; padding: 2.2rem 2.4rem; margin: 0 0 1.6rem; border-radius: 28px; background: linear-gradient(135deg, #f6e7da 0%, #f3ddcf 100%); border: 1px solid var(--line); overflow: hidden; }
.gd-hero-text { min-width: 0; }
.gd-kicker { margin: 0 0 .5rem; font-size: .75rem; letter-spacing: .16em; text-transform: uppercase; font-weight: 700; color: #b5472b; }
.gd-hero h2 { margin: 0 0 .7rem; font-size: clamp(2rem, 3.6vw, 2.8rem); line-height: 1.08; color: #2b2118; }
.gd .gd-lede { margin: 0 0 1.5rem; max-width: 34rem; font-size: 1.02rem; line-height: 1.6; color: #5b4a3d; }
.gd-steps { list-style: none; display: grid; gap: .55rem; margin: 0; padding: 0; }
.gd-steps li { display: flex; align-items: center; gap: .7rem; font-size: .92rem; color: #3b2f26; }
.gd-steps li span { flex: none; width: 1.7rem; height: 1.7rem; border-radius: 50%; background: #b5472b; color: #fff; font-weight: 700; font-size: .8rem; display: grid; place-items: center; }
.gd-hero-art { width: 100%; height: auto; max-height: 300px; object-fit: contain; justify-self: end; }
.gd-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .8rem; margin: 0 0 1.4rem; position: sticky; top: 3.5rem; z-index: 4; padding: .6rem 0; background: var(--paper, #f5f0e6); }
.gd-chips { display: flex; flex-wrap: wrap; gap: .45rem; }
.gd-chip { border: 1px solid var(--line); background: var(--card); color: var(--ink-2); border-radius: 999px; padding: .45rem .95rem; font: inherit; font-size: .88rem; cursor: pointer; transition: all .15s; }
.gd-chip small { opacity: .6; margin-left: .25rem; }
.gd-chip:hover { border-color: var(--clay); color: var(--ink); }
.gd-chip[aria-pressed="true"] { background: #2b2118; border-color: #2b2118; color: #fff; }
.gd-search { display: flex; align-items: center; gap: .5rem; padding: .45rem .9rem; border: 1px solid var(--line); border-radius: 999px; background: var(--card); color: var(--ink-3); min-width: min(100%, 17rem); }
.gd-search input { border: 0; outline: 0; background: transparent; font: inherit; font-size: .9rem; color: var(--ink); width: 100%; }
.gd-search:focus-within { border-color: var(--clay); }
.gd-sec { margin: 0 0 2.4rem; }
.gd-sec-head { display: flex; align-items: baseline; gap: 1rem; flex-wrap: wrap; margin: 0 0 1rem; padding-left: .9rem; border-left: 4px solid var(--gd-c); }
.gd-sec-head h3 { margin: 0; font-size: 1.35rem; }
.gd-sec-head p { margin: 0; color: var(--ink-2); font-size: .92rem; flex: 1 1 18rem; }
.gd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.1rem; }
.gd-card { position: relative; display: flex; flex-direction: column; background: var(--card); border: 1px solid var(--line); border-radius: 22px; overflow: hidden; text-decoration: none; color: var(--ink); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
.gd-card:hover, .gd-card:focus-visible { transform: translateY(-4px); box-shadow: 0 16px 34px rgba(43, 33, 24, .12); border-color: var(--gd-c); outline: none; }
.gd-card-art { display: block; aspect-ratio: 4 / 3; background: color-mix(in srgb, var(--gd-c) 11%, #fbf7ef); padding: .9rem 1rem 0; overflow: hidden; }
.gd-card-art img { width: 100%; height: 100%; object-fit: contain; transition: transform .35s ease; }
.gd-card:hover .gd-card-art img { transform: scale(1.06); }
.gd-card-n { position: absolute; top: .8rem; left: .8rem; width: 1.8rem; height: 1.8rem; border-radius: 50%; background: #fff; color: var(--gd-c); font-weight: 700; font-size: .82rem; display: grid; place-items: center; box-shadow: 0 2px 8px rgba(0,0,0,.12); }
.gd-card-body { display: flex; flex-direction: column; gap: .35rem; padding: 1rem 1.2rem .4rem; flex: 1; }
.gd-card-body strong { font-size: 1.02rem; line-height: 1.3; }
.gd-card-body span { color: var(--ink-2); font-size: .88rem; line-height: 1.5; }
.gd-card-go { display: flex; align-items: center; gap: .4rem; padding: .5rem 1.2rem 1.05rem; font-size: .86rem; font-weight: 700; color: var(--gd-c); }
.gd-card-go svg { transition: transform .18s; } .gd-card:hover .gd-card-go svg { transform: translateX(4px); }
.gd-none { text-align: center; color: var(--ink-3); padding: 2rem 0; }
.gd-tpl { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; padding: 1.1rem 1.3rem; border: 1px dashed var(--line); border-radius: 18px; }
.gd-tpl strong { margin-right: .5rem; }
/* reader */
.gd-doc { max-width: 56rem; margin: 0 auto; animation: gd-in .28s ease; }
@keyframes gd-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.gd-back { display: inline-flex; align-items: center; gap: .5rem; border: 1px solid var(--line); background: var(--card); color: var(--ink); border-radius: 999px; padding: .5rem 1.05rem .5rem .85rem; font: inherit; font-size: .88rem; cursor: pointer; margin: 0 0 1rem; }
.gd-back:hover { border-color: var(--gd-c); color: var(--gd-c); }
.gd-doc-head { display: grid; grid-template-columns: minmax(0, 1fr) 270px; align-items: center; gap: 1.5rem; padding: 1.6rem 2rem 0 2rem; border-radius: 26px 26px 0 0; background: color-mix(in srgb, var(--gd-c) 11%, #fbf7ef); border: 1px solid var(--line); border-bottom: 0; overflow: hidden; }
.gd-doc-head img { width: 100%; height: 220px; object-fit: contain; align-self: end; }
.gd-crumb { margin: 0 0 .6rem; font-size: .76rem; letter-spacing: .08em; text-transform: uppercase; font-weight: 700; color: var(--gd-c); }
.gd-crumb span { opacity: .5; margin: 0 .25rem; }
.gd-doc-head h3 { margin: 0 0 .6rem; font-size: clamp(1.5rem, 2.8vw, 2.1rem); line-height: 1.18; color: #2b2118; }
.gd-doc-lede { margin: 0 0 1.4rem; color: #5b4a3d; font-size: 1rem; }
.gd-body { background: var(--card); border: 1px solid var(--line); border-radius: 0 0 26px 26px; padding: 1.8rem 2.1rem 2.1rem; font-size: 1rem; line-height: 1.7; }
.gd-body p { margin: .7rem 0; } .gd-body ul, .gd-body ol { margin: .6rem 0 .6rem 1.5rem; padding: 0; } .gd-body ul { list-style: disc outside; } .gd-body ol { list-style: decimal outside; } .gd-body ul ul { list-style: circle outside; }
.gd-body li { margin: .45rem 0; padding-left: .2rem; } .gd-body li::marker { color: var(--gd-c); font-weight: 700; }
.gd-body h5 { font-size: 1.05rem; font-weight: 700; margin: 1.3rem 0 .3rem; }
.gd-body code { background: var(--paper-2, rgba(0,0,0,.06)); padding: .1rem .38rem; border-radius: 6px; font-size: .86em; word-break: break-word; }
.lif-code { background: #1f201c; color: #ece9df; padding: 1rem 1.2rem; border-radius: 12px; overflow-x: auto; font-size: .84rem; line-height: 1.55; white-space: pre; margin: .9rem 0; }
.lif-note { border-left: 4px solid var(--gd-c); padding: .7rem 1rem; background: color-mix(in srgb, var(--gd-c) 8%, var(--card)); border-radius: 0 12px 12px 0; color: var(--ink-2); }
.lif-table { width: 100%; border-collapse: collapse; margin: .9rem 0; font-size: .9rem; display: block; overflow-x: auto; }
.lif-table th { text-align: left; padding: .6rem .75rem; border-bottom: 2px solid var(--line); background: var(--paper-2, rgba(0,0,0,.04)); white-space: nowrap; }
.lif-table td { padding: .6rem .75rem; border-bottom: 1px solid var(--line); vertical-align: top; min-width: 8rem; }
.gd-pn { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.2rem; }
.gd-pn a { display: flex; align-items: center; gap: .9rem; padding: .8rem 1rem; border: 1px solid var(--line); border-radius: 18px; background: var(--card); text-decoration: none; color: var(--ink); transition: border-color .15s, transform .15s; }
.gd-pn a:hover { border-color: var(--gd-c); transform: translateY(-2px); }
.gd-pn img { flex: none; width: 76px; height: 58px; object-fit: contain; border-radius: 10px; background: color-mix(in srgb, var(--gd-c) 11%, #fbf7ef); }
.gd-pn small { display: block; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: var(--gd-c); font-weight: 700; }
.gd-pn strong { font-size: .95rem; line-height: 1.3; }
.gd-pn-next { flex-direction: row-reverse; text-align: right; grid-column: 2; }
@media (max-width: 820px) { .gd-hero { grid-template-columns: 1fr; padding: 1.5rem; } .gd-hero-art { order: -1; max-height: 160px; justify-self: center; } .gd-doc-head { grid-template-columns: 1fr; padding: 1.3rem 1.3rem 0; } .gd-doc-head img { order: -1; height: 160px; } .gd-body { padding: 1.3rem; } .gd-pn { grid-template-columns: 1fr; } .gd-pn-next { grid-column: auto; } }
@media (prefers-reduced-motion: reduce) { .gd-card, .gd-card-art img, .gd-doc { transition: none; animation: none; } }
</style>
<?php endif; ?>
<script>
(function () {
  function init(root) {
    var list = root.querySelector('.gd-list');
    var docs = root.querySelectorAll('.gd-doc');
    var cat = 'all';
    function top() { var t = root.closest('.tab-content') || root; try { t.scrollIntoView({ block: 'start' }); } catch (e) {} var m = document.querySelector('main, .t-main'); if (m && m.scrollTo) m.scrollTo({ top: 0 }); window.scrollTo(0, 0); }
    function show(id) {
      var d = root.querySelector('[data-gd-doc="' + id + '"]'); if (!d) return false;
      docs.forEach(function (x) { x.hidden = true; });
      list.hidden = true; d.hidden = false; top();
      return true;
    }
    function back() { docs.forEach(function (x) { x.hidden = true; }); list.hidden = false; top(); }
    function apply() {
      var q = (root.querySelector('[data-gd-search]').value || '').trim().toLowerCase(), any = false;
      root.querySelectorAll('[data-gd-sec]').forEach(function (s) {
        var okCat = cat === 'all' || s.getAttribute('data-cat') === cat, n = 0;
        s.querySelectorAll('.gd-card').forEach(function (c) { var ok = !q || c.getAttribute('data-text').indexOf(q) > -1; c.hidden = !ok; if (ok) n++; });
        s.hidden = !okCat || n === 0; if (!s.hidden) any = true;
      });
      root.querySelector('.gd-none').hidden = any;
    }
    root.addEventListener('click', function (e) {
      var o = e.target.closest('[data-gd-open]'); if (o && root.contains(o)) { e.preventDefault(); show(o.getAttribute('data-gd-open')); return; }
      if (e.target.closest('[data-gd-back]')) { e.preventDefault(); back(); return; }
      var c = e.target.closest('[data-gd-cat]'); if (c) { cat = c.getAttribute('data-gd-cat'); root.querySelectorAll('[data-gd-cat]').forEach(function (x) { x.setAttribute('aria-pressed', x === c ? 'true' : 'false'); }); apply(); }
    });
    root.querySelector('[data-gd-search]').addEventListener('input', apply);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && list.hidden && root.offsetParent !== null) back(); });
    root.gdShow = show; root.gdBack = back;
  }
  document.querySelectorAll('[data-gd]:not([data-gd-ready])').forEach(function (r) { r.setAttribute('data-gd-ready', '1'); init(r); });
  if (window.openGuide) return;
  // From elsewhere in the dashboard: open the guides tab on a given card (used by the import forms and the questions tab).
  window.openGuide = function (id, tab) {
    var root = document.querySelector('#tab-help [data-gd]'); if (!root) return;
    if (typeof switchDashboardTab === 'function') switchDashboardTab('tab-help');
    root.gdBack(); if (id) root.gdShow(id);
  };
  window.openLiveImportFaq = window.openGuide;
})();
</script>
