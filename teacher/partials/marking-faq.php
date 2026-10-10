<?php
/**
 * Marking guide (FAQ): how marks are produced on StudyVibe, for teachers.
 * Included in tab-comments (questions forum) and in tab-help. Needs $tdLang. Set $mkfPrefix before including so the
 * element ids stay unique when the partial appears twice on the page. Reuses the .lif-* styles of live-import-faq.php.
 * The rules written here were read from the code (Assignments, LiveSession scoring, final exam, Contests).
 */
$mkfLang = (isset($tdLang) && $tdLang === 'en') ? 'en' : 'fr';
$mkfP = preg_replace('/[^a-z0-9-]/i', '', (string)($mkfPrefix ?? 'mkf'));

$MKF = [
'fr' => [
 'kicker' => 'Aide', 'title' => 'Comment fonctionne la notation sur StudyVibe',
 'lede' => 'Où naissent les notes, qui les calcule, ce que voit l’étudiant et ce que vous pouvez corriger. À lire une fois, pour répondre aux questions de vos étudiants sans hésiter.',
 'items' => [
 ['id' => 'map', 'q' => 'Vue d’ensemble : quelles notes existent, et où les trouver ?', 'a' => <<<'HTML'
<table class="lif-table"><thead><tr><th>Type</th><th>Qui note</th><th>Où vous le voyez</th></tr></thead><tbody>
<tr><td>Quiz de leçon</td><td>Automatique</td><td>Onglet « Notes »</td></tr>
<tr><td>Examen final de certification</td><td>Automatique</td><td>Onglet « Notes » et certificats</td></tr>
<tr><td>Évaluation en direct</td><td>Automatique</td><td>Séance en direct, résultats ; export Excel, PDF, CSV</td></tr>
<tr><td>Devoir</td><td><strong>Vous</strong> (ou barème)</td><td>Onglet « Devoirs » : bouton « Noter »</td></tr>
</tbody></table>
<p class="lif-note">Les notes de devoirs sont rapportées <strong>à part</strong> : elles ne comptent ni dans le résultat du cours ni dans le certificat, qui viennent de l’examen final.</p>
HTML],
 ['id' => 'quiz', 'q' => 'Comment les quiz de leçon sont-ils notés ?', 'a' => <<<'HTML'
<ul>
<li>Chaque réponse est enregistrée au clic, sans bouton « valider ».</li>
<li>La leçon est terminée quand <strong>toutes les questions ont reçu une réponse</strong>, juste ou fausse. Le score est le <strong>pourcentage de bonnes réponses</strong>.</li>
<li>L’étudiant peut recommencer ; le meilleur score apparaît dans ses résultats.</li>
</ul>
HTML],
 ['id' => 'final', 'q' => 'Comment fonctionne l’examen final et le certificat ?', 'a' => <<<'HTML'
<ul>
<li>L’examen s’ouvre quand l’étudiant a terminé <strong>toutes les leçons obligatoires</strong>.</li>
<li>Au plus <strong>3 tentatives par 24 heures</strong>.</li>
<li>Réussite à partir de <strong>80 %</strong> : le certificat est créé et envoyé par e-mail.</li>
<li>En dessous de 80 %, rien n’est délivré ; l’étudiant peut retenter après sa pause.</li>
</ul>
HTML],
 ['id' => 'live', 'q' => 'Comment une évaluation en direct est-elle notée ?', 'a' => <<<'HTML'
<ul>
<li>Note = <strong>bonnes réponses ÷ nombre total de questions × 100</strong>, arrondie à 2 décimales.</li>
<li><strong>Pas de points négatifs</strong>. Une question sans réponse vaut 0, comme une mauvaise réponse.</li>
<li><strong>Réponses écrites</strong> : comparées au texte attendu après normalisation (majuscules, accents, espaces et ponctuation ignorés). Séparez plusieurs réponses acceptées par <code>|</code> (<code>Paris|paris</code>) ; <code>~</code> tolère une petite faute de frappe.</li>
<li><strong>Propositions mélangées</strong> : la bonne réponse suit son texte, pas sa lettre ; la correction reste juste pour chaque étudiant.</li>
<li>L’étudiant voit un repère de réussite à <strong>50 %</strong> ; c’est un affichage, pas une décision à votre place.</li>
<li><strong>Évaluation asynchrone</strong> : même calcul, l’étudiant passe l’épreuve avant la date limite au lieu d’une heure fixe.</li>
</ul>
HTML],
 ['id' => 'cancel', 'q' => 'Un résultat est annulé ou contesté : que se passe-t-il ?', 'a' => <<<'HTML'
<ul>
<li>Un résultat <strong>annulé</strong> (triche, incident) n’est plus compté. L’étudiant peut le <strong>contester</strong> une fois, avec un message.</li>
<li>Vous recevez une notification et un e-mail. Dans la séance, vous <strong>rétablissez</strong> le résultat ou vous le <strong>maintenez</strong>, avec une explication ; l’étudiant est prévenu dans les deux cas.</li>
</ul>
HTML],
 ['id' => 'asg', 'q' => 'Comment noter un devoir, pas à pas ?', 'a' => <<<'HTML'
<ol>
<li>Onglet « Devoirs » → ligne de l’étudiant → <strong>Noter</strong>. La fenêtre montre le fichier, le lien, le commentaire et les versions précédentes.</li>
<li>Saisissez la note (la virgule est acceptée, maximum 20 par défaut, réglable sur la leçon) et un commentaire.</li>
<li><strong>Avec un barème</strong> : une ligne par critère (points et commentaire). <strong>Le serveur additionne</strong> ; la note maximale est la somme des critères. Modifier le barème plus tard ne change pas les notes déjà données.</li>
<li><strong>Copie annotée</strong> : joignez un PDF, DOCX, image, ZIP… (20 Mo). L’étudiant la reçoit seulement quand la note est publiée.</li>
<li><strong>Demander une nouvelle version</strong> si le travail est insuffisant : l’étudiant peut renvoyer même après la date limite ; l’ancienne note reste archivée.</li>
<li>Un devoir rendu après la date limite est marqué « en retard » (si vous acceptez les retards).</li>
</ol>
HTML],
 ['id' => 'release', 'q' => 'Quand l’étudiant voit-il sa note de devoir ?', 'a' => <<<'HTML'
<ul>
<li><strong>Publication immédiate</strong> (par défaut) : dès l’enregistrement, l’étudiant reçoit une notification et un e-mail.</li>
<li><strong>Après approbation</strong> : la note est enregistrée mais cachée. L’étudiant ne voit ni note, ni commentaire, ni barème, ni copie annotée, et ne reçoit rien. « Publier les notes (N) » les révèle toutes ; « Masquer » les cache de nouveau (sans prévenir).</li>
<li>Vous voyez toujours toutes les notes, dans le tableau et dans les exports.</li>
</ul>
HTML],
 ['id' => 'similar', 'q' => 'La vérification des ressemblances : comment l’utiliser ?', 'a' => <<<'HTML'
<p>Bouton « Vérifier les ressemblances » sur le devoir. Les travaux sont comparés <strong>entre eux</strong> (pas avec le web) ; les paires à partir de 15 % sont listées avec les passages communs. Un pourcentage élevé est une raison de regarder de près, jamais une preuve. Les scans, liens seuls et textes très courts ne sont pas comparés.</p>
HTML],
 ['id' => 'requests', 'q' => 'Un étudiant me demande de revoir sa note : que faire ?', 'a' => <<<'HTML'
<ol>
<li>La demande arrive ici, dans « Demandes des étudiants » (badge sur l’onglet), avec un lien vers la correction.</li>
<li>Rouvrez la copie. Si vous changez la note, faites-le dans « Noter » (devoir) ou la séance (résultat).</li>
<li>Répondez dans la demande (10 caractères minimum). L’étudiant reçoit votre réponse par e-mail et notification, et la lit dans <em>Résultats → Mes demandes</em>. Une demande reçoit une seule réponse.</li>
</ol>
HTML],
 ['id' => 'qa', 'q' => 'Les questions posées sous une leçon', 'a' => <<<'HTML'
<p>Les étudiants inscrits posent leurs questions sous la leçon ; vous êtes prévenu. Répondez ici : l’étudiant reçoit un e-mail à la première réponse. Vous pouvez masquer un message déplacé.</p>
HTML],
 ['id' => 'exports', 'q' => 'Exporter les notes', 'a' => <<<'HTML'
<ul>
<li><strong>Devoirs</strong> : « Notes (Excel) » et « Notes (PDF) », une ligne par étudiant inscrit (matricule, nom, e-mail), une colonne par devoir, total et pourcentage ; « Non rendu » et « À noter » sont écrits en toutes lettres.</li>
<li><strong>Évaluations en direct</strong> : Excel, PDF et CSV avec le matricule.</li>
</ul>
HTML],
 ],
],
'en' => [
 'kicker' => 'Help', 'title' => 'How marking works on StudyVibe',
 'lede' => 'Where marks come from, who calculates them, what the student sees and what you can correct. Read it once, then answer your students’ questions with confidence.',
 'items' => [
 ['id' => 'map', 'q' => 'Overview: which marks exist, and where do I find them?', 'a' => <<<'HTML'
<table class="lif-table"><thead><tr><th>Type</th><th>Who marks</th><th>Where you see it</th></tr></thead><tbody>
<tr><td>Lesson quiz</td><td>Automatic</td><td>“Grades” tab</td></tr>
<tr><td>Final certification exam</td><td>Automatic</td><td>“Grades” tab and certificates</td></tr>
<tr><td>Live evaluation</td><td>Automatic</td><td>Live session, results; Excel, PDF, CSV export</td></tr>
<tr><td>Assignment</td><td><strong>You</strong> (or a rubric)</td><td>“Assignments” tab: “Mark” button</td></tr>
</tbody></table>
<p class="lif-note">Assignment marks are reported <strong>separately</strong>: they count neither in the course result nor in the certificate, which come from the final exam.</p>
HTML],
 ['id' => 'quiz', 'q' => 'How are lesson quizzes marked?', 'a' => <<<'HTML'
<ul>
<li>Each answer is saved on click, with no “submit” button.</li>
<li>The lesson is complete when <strong>every question has an answer</strong>, right or wrong. The score is the <strong>percentage of correct answers</strong>.</li>
<li>The student can retry; the best score shows in their results.</li>
</ul>
HTML],
 ['id' => 'final', 'q' => 'How do the final exam and the certificate work?', 'a' => <<<'HTML'
<ul>
<li>The exam opens once the student has finished <strong>all compulsory lessons</strong>.</li>
<li>At most <strong>3 attempts per 24 hours</strong>.</li>
<li>Pass at <strong>80 %</strong> or more: the certificate is created and emailed.</li>
<li>Below 80 % nothing is issued; the student can try again after the pause.</li>
</ul>
HTML],
 ['id' => 'live', 'q' => 'How is a live evaluation marked?', 'a' => <<<'HTML'
<ul>
<li>Mark = <strong>correct answers ÷ total number of questions × 100</strong>, rounded to 2 decimals.</li>
<li><strong>No negative marking</strong>. An unanswered question scores 0, like a wrong one.</li>
<li><strong>Written answers</strong>: compared with the expected text after normalisation (case, accents, spaces and punctuation ignored). Separate several accepted answers with <code>|</code> (<code>Paris|paris</code>); <code>~</code> tolerates a small typo.</li>
<li><strong>Shuffled options</strong>: the right answer follows its text, not its letter; marking stays correct for each student.</li>
<li>The student sees a pass marker at <strong>50 %</strong>; it is a display, not a decision made for you.</li>
<li><strong>Asynchronous evaluation</strong>: same calculation, the student sits it before the deadline instead of at a fixed time.</li>
</ul>
HTML],
 ['id' => 'cancel', 'q' => 'A result is cancelled or contested: what happens?', 'a' => <<<'HTML'
<ul>
<li>A <strong>cancelled</strong> result (cheating, incident) no longer counts. The student can <strong>contest</strong> it once, with a message.</li>
<li>You get a notification and an email. In the session you <strong>restore</strong> the result or <strong>uphold</strong> the cancellation, with an explanation; the student is told either way.</li>
</ul>
HTML],
 ['id' => 'asg', 'q' => 'How do I mark an assignment, step by step?', 'a' => <<<'HTML'
<ol>
<li>“Assignments” tab → the student’s row → <strong>Mark</strong>. The window shows the file, the link, the comment and earlier versions.</li>
<li>Enter the mark (comma accepted, maximum 20 by default, adjustable on the lesson) and a comment.</li>
<li><strong>With a rubric</strong>: one line per criterion (points and comment). <strong>The server adds them up</strong>; the maximum mark is the sum of the criteria. Editing the rubric later does not change marks already given.</li>
<li><strong>Annotated copy</strong>: attach a PDF, DOCX, image, ZIP… (20 MB). The student gets it only once the mark is released.</li>
<li><strong>Request a new version</strong> if the work is not good enough: the student can resubmit even after the deadline; the old mark stays archived.</li>
<li>Work handed in after the deadline is flagged “late” (if you accept late work).</li>
</ol>
HTML],
 ['id' => 'release', 'q' => 'When does the student see an assignment mark?', 'a' => <<<'HTML'
<ul>
<li><strong>Immediate release</strong> (default): as soon as you save, the student gets a notification and an email.</li>
<li><strong>After my approval</strong>: the mark is saved but hidden. The student sees no mark, comment, rubric or annotated copy and receives nothing. “Release marks (N)” reveals them all; “Hide” hides them again (nobody is told).</li>
<li>You always see every mark, in the table and in exports.</li>
</ul>
HTML],
 ['id' => 'similar', 'q' => 'The similarity check: how do I use it?', 'a' => <<<'HTML'
<p>“Check similarities” button on the assignment. Works are compared <strong>with each other</strong> (not with the web); pairs from 15 % up are listed with the shared passages. A high score is a reason to look closely, never proof. Scans, link-only work and very short texts are not compared.</p>
HTML],
 ['id' => 'requests', 'q' => 'A student asks me to review their mark: what do I do?', 'a' => <<<'HTML'
<ol>
<li>The request arrives here, in “Student requests” (badge on the tab), with a link to the work.</li>
<li>Reopen the copy. If you change the mark, do it in “Mark” (assignment) or in the session (result).</li>
<li>Answer in the request (10 characters minimum). The student gets your answer by email and notification, and reads it in <em>Results → My requests</em>. A request receives a single answer.</li>
</ol>
HTML],
 ['id' => 'qa', 'q' => 'Questions asked under a lesson', 'a' => <<<'HTML'
<p>Enrolled students ask their questions under the lesson; you are notified. Reply here: the student gets an email on the first reply. You can hide an inappropriate message.</p>
HTML],
 ['id' => 'exports', 'q' => 'Exporting marks', 'a' => <<<'HTML'
<ul>
<li><strong>Assignments</strong>: “Marks (Excel)” and “Marks (PDF)”, one row per enrolled student (matricule, name, email), one column per assignment, total and percentage; “Not handed in” and “To mark” are written out.</li>
<li><strong>Live evaluations</strong>: Excel, PDF and CSV with the matricule.</li>
</ul>
HTML],
 ],
],
];
$MK = $MKF[$mkfLang];
?>
<section class="lif mkf" id="<?= $mkfP ?>" aria-label="<?= htmlspecialchars($MK['title'], ENT_QUOTES, 'UTF-8') ?>">
    <h3 class="mkf-title"><?= htmlspecialchars($MK['title'], ENT_QUOTES, 'UTF-8') ?></h3>
    <p class="lif-lede"><?= htmlspecialchars($MK['lede'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php foreach ($MK['items'] as $i => $it): ?>
        <details class="lif-item" id="<?= $mkfP ?>-<?= $it['id'] ?>">
            <summary><span class="lif-n"><?= ($i + 1) ?></span><?= htmlspecialchars($it['q'], ENT_QUOTES, 'UTF-8') ?></summary>
            <div class="lif-body"><?= $it['a'] ?></div>
        </details>
    <?php endforeach; ?>
</section>
<style>
.mkf-title { font-size: 1.25rem; margin: .4rem 0 .2rem; }
.mkf-wrap { margin: 0 0 1.6rem; }
.mkf-wrap > summary { cursor: pointer; font-weight: 600; padding: .5rem 0; color: var(--ink); }
.mkf { margin-bottom: 1.6rem; }
</style>
