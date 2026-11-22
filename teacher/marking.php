<?php
declare(strict_types=1);

/**
 * The grading bucket of one assignment (lib/MarkingBucket.php): send the copies to marking, set the marking rules, go copy by copy and
 * confirm or change what the engine proposes. Works on a phone (one column, big buttons, previous / next).
 *
 *   GET  ?lesson_id=N [&sub=ID] [&f=check|done|all] [&view=rules]
 *   POST action=send | rerun | rules | confirm | release_all
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/MarkingBucket.php';

if (!isLoggedIn() || !isTeacherRole($_SESSION['user_role'] ?? null)) {
    header('Location: /index.php');
    exit;
}
$teacherId = (int)$_SESSION['user_id'];
$pdo = Database::getInstance();
$en = (TranslationService::getLang() ?? 'fr') === 'en';
$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$n2 = static fn($v) => $v === null ? '—' : rtrim(rtrim(number_format((float)$v, 2, $en ? '.' : ',', ''), '0'), $en ? '.' : ',');

$lesson = MarkingBucket::lesson($pdo, (int)($_GET['lesson_id'] ?? $_POST['lesson_id'] ?? 0), $teacherId);
if ($lesson === null) {
    http_response_code(404);
    exit($en ? 'Assignment not found.' : 'Devoir introuvable.');
}
$lessonId = (int)$lesson['id'];

$T = $en ? [
    'back' => 'Back to assignments', 'title' => 'Grading bucket', 'max' => 'out of', 'deadline' => 'Deadline', 'no_deadline' => 'no deadline',
    'send' => 'Send to marking', 'send_n' => 'Send %d copies to marking', 'send_confirm_early' => 'The deadline has not passed. Copies sent now can no longer be changed by the students. Send anyway?',
    'send_confirm' => 'Students will see "being marked" and can no longer change their copy. Continue?',
    'rules' => 'Marking rules', 'rerun' => 'Run the engine again', 'release' => 'Release the marks (%d)',
    'running' => 'The engine is reading the copies: %d left. This page refreshes by itself.',
    'no_rules' => 'The engine has no rules yet: add key points for each criterion (or a model answer) so it can propose marks.',
    'reliab' => 'Engine vs you, on %d checked copy(ies): average gap %s pt, %d %% within %s pt.',
    'reliab_none' => 'Once you confirm copies, this shows how close the engine is to your marks.',
    'f_check' => 'To check', 'f_done' => 'Checked', 'f_all' => 'All',
    'student' => 'Student', 'status' => 'Status', 'engine' => 'Engine', 'final' => 'Mark', 'none' => 'No copy here.',
    'pick' => 'Choose a copy in the list.', 'file' => 'File', 'link' => 'Link', 'sent' => 'Handed in', 'late' => 'late', 'version' => 'version %d',
    'conf' => ['high' => 'High confidence', 'medium' => 'Medium confidence', 'low' => 'Low confidence'],
    'found' => 'Found', 'missing' => 'Missing', 'points' => 'Points', 'comment' => 'Comment for the student', 'basis_model' => 'from the model answer (%s %% close)', 'basis_none' => 'nothing to go on: mark it yourself',
    'pens' => 'Penalties proposed (untick to drop)', 'feedback' => 'Feedback to the student', 'confirm' => 'Confirm and next', 'confirm_only' => 'Confirm',
    'prev' => 'Previous', 'next' => 'Next', 'copy' => 'The copy (key points found are highlighted)', 'words' => '%d words',
    'saved' => 'Mark saved.', 'saved_held' => 'Mark saved. It stays hidden from the student until you release the marks.', 'sent_ok' => '%d copies sent to marking.',
    'rerun_ok' => 'The engine reads the copies again.', 'rules_ok' => 'Rules saved.', 'released_ok' => '%d marks released.',
    'err' => ['bad_score' => 'The mark must be a number between 0 and the maximum.', 'rubric_incomplete' => 'Give points for every criterion.', 'rubric_bad_points' => 'A criterion has points out of its range.', 'not_found' => 'Copy not found.'],
    'already' => 'Already marked: %s. Saving again replaces it.',
    'reasons' => ['short_text' => 'short copy', 'no_rules' => 'a criterion has no key points and no model answer', 'other_words' => 'few key points but close to the model answer: maybe other words', 'spelling' => 'many approximate matches (spelling)', 'similar' => 'looks like another copy', 'far_from_class' => 'far from the rest of the class', 'model_disagrees' => 'the key points are there but the wording is far from the model answer: check the criteria marked on the model answer'],
    'errors' => ['no_file' => 'No file (link only): mark it by hand.', 'unreadable' => 'The text cannot be read (scan, picture or unread format): mark it by hand.', 'too_short' => 'Too little text to read: mark it by hand.'],
    'pen' => ['late' => 'Late', 'short' => 'Too short (%s words)', 'section' => 'Missing part: %s', 'similarity' => 'Close to another copy (%s %%)'],
    'p' => ['todo' => 'To hand in', 'missed' => 'Not handed in', 'submitted' => 'Handed in (can change)', 'locked' => 'Waiting for marking', 'in_marking' => 'In the bucket, being read', 'proposed' => 'Proposal ready', 'by_hand' => 'Mark by hand', 'held' => 'Marked, not released', 'released' => 'Released', 'revision' => 'New version requested'],
    // rules
    'r_lede' => 'The engine follows these rules to propose a mark. You confirm every mark: it never decides alone.',
    'r_strict' => 'Strictness', 'r_strict_o' => ['lenient' => 'Lenient: spelling slips accepted, partial credit generous', 'normal' => 'Normal: small slips accepted, points in proportion', 'strict' => 'Strict: exact words, all / half / nothing'],
    'r_tone' => 'Tone of the feedback', 'r_tone_o' => ['encouraging' => 'Encouraging', 'neutral' => 'Neutral', 'direct' => 'Direct'],
    'r_crit' => 'Key points per criterion', 'r_keys' => 'One key point per line. Accepted variants on the same line, separated by |',
    'r_keys_ph' => "chlorophyll\nlight | sunlight\ncarbon dioxide | CO2", 'r_need' => 'Key points needed for full points (empty: all)',
    'r_model' => 'Model answer (optional)', 'r_model_h' => 'Used for criteria without key points, and as a check.',
    'r_struct' => 'Structure', 'r_min' => 'Minimum length (words, 0: none)', 'r_sections' => 'Required parts, one per line (e.g. Introduction, Conclusion)',
    'r_pens' => 'Penalties (points taken off, 0: none)', 'r_late' => 'Late', 'r_short' => 'Under the minimum length', 'r_section' => 'Each missing part', 'r_sim' => 'Copy close to another', 'r_sim_at' => 'from (%)',
    'r_tpl' => 'Your own words (optional)', 'r_tpl_h' => 'Replace the opening line by level, and the closing line.', 'r_high' => 'Good copy (80 % and more)', 'r_mid' => 'Average copy (50 to 80 %)', 'r_low' => 'Weak copy (under 50 %)', 'r_closing' => 'Closing line',
    'r_save' => 'Save the rules', 'r_save_rerun' => 'Save and run the engine again', 'r_keys_l' => 'Key points', 'r_overall' => 'Overall mark (no rubric)',
] : [
    'back' => 'Retour aux devoirs', 'title' => 'Bac de correction', 'max' => 'sur', 'deadline' => 'Date limite', 'no_deadline' => 'sans date limite',
    'send' => 'Envoyer en correction', 'send_n' => 'Envoyer %d copies en correction', 'send_confirm_early' => 'La date limite n’est pas passée. Les copies envoyées maintenant ne pourront plus être modifiées par les étudiants. Envoyer quand même ?',
    'send_confirm' => 'Les étudiants verront « en cours de correction » et ne pourront plus modifier leur copie. Continuer ?',
    'rules' => 'Règles de correction', 'rerun' => 'Relancer la correction automatique', 'release' => 'Publier les notes (%d)',
    'running' => 'Le moteur lit les copies : encore %d. La page se met à jour toute seule.',
    'no_rules' => 'Le moteur n’a pas encore de règles : indiquez les points clés de chaque critère (ou un corrigé) pour qu’il propose des notes.',
    'reliab' => 'Moteur et vous, sur %d copie(s) vérifiée(s) : écart moyen %s pt, %d %% à %s pt près.',
    'reliab_none' => 'Dès que vous validez des copies, vous voyez ici à quel point le moteur est proche de vos notes.',
    'f_check' => 'À vérifier', 'f_done' => 'Vérifiées', 'f_all' => 'Toutes',
    'student' => 'Étudiant', 'status' => 'Statut', 'engine' => 'Moteur', 'final' => 'Note', 'none' => 'Aucune copie ici.',
    'pick' => 'Choisissez une copie dans la liste.', 'file' => 'Fichier', 'link' => 'Lien', 'sent' => 'Rendu le', 'late' => 'en retard', 'version' => 'version %d',
    'conf' => ['high' => 'Confiance élevée', 'medium' => 'Confiance moyenne', 'low' => 'Confiance faible'],
    'found' => 'Trouvé', 'missing' => 'Absent', 'points' => 'Points', 'comment' => 'Commentaire pour l’étudiant', 'basis_model' => 'd’après le corrigé (proche à %s %%)', 'basis_none' => 'rien pour juger : notez vous-même',
    'pens' => 'Pénalités proposées (décochez pour retirer)', 'feedback' => 'Appréciation pour l’étudiant', 'confirm' => 'Valider et suivante', 'confirm_only' => 'Valider',
    'prev' => 'Précédente', 'next' => 'Suivante', 'copy' => 'La copie (points clés trouvés surlignés)', 'words' => '%d mots',
    'saved' => 'Note enregistrée.', 'saved_held' => 'Note enregistrée. Elle reste cachée à l’étudiant jusqu’à la publication des notes.', 'sent_ok' => '%d copies envoyées en correction.',
    'rerun_ok' => 'Le moteur relit les copies.', 'rules_ok' => 'Règles enregistrées.', 'released_ok' => '%d notes publiées.',
    'err' => ['bad_score' => 'La note doit être un nombre entre 0 et le maximum.', 'rubric_incomplete' => 'Donnez des points à chaque critère.', 'rubric_bad_points' => 'Un critère a des points hors de son barème.', 'not_found' => 'Copie introuvable.'],
    'already' => 'Déjà notée : %s. Enregistrer à nouveau remplace la note.',
    'reasons' => ['short_text' => 'copie courte', 'no_rules' => 'un critère n’a ni points clés ni corrigé', 'other_words' => 'peu de points clés mais proche du corrigé : peut-être d’autres mots', 'spelling' => 'beaucoup de correspondances approximatives (orthographe)', 'similar' => 'ressemble à une autre copie', 'far_from_class' => 'très loin du reste de la classe', 'model_disagrees' => 'les points clés sont là mais la formulation est loin du corrigé : vérifiez les critères notés d’après le corrigé'],
    'errors' => ['no_file' => 'Pas de fichier (lien seulement) : à corriger à la main.', 'unreadable' => 'Texte illisible (scan, image ou format non lu) : à corriger à la main.', 'too_short' => 'Trop peu de texte pour être lu : à corriger à la main.'],
    'pen' => ['late' => 'Retard', 'short' => 'Trop court (%s mots)', 'section' => 'Partie absente : %s', 'similarity' => 'Copie proche d’une autre (%s %%)'],
    'p' => ['todo' => 'À rendre', 'missed' => 'Non rendu', 'submitted' => 'Rendu (modifiable)', 'locked' => 'En attente de correction', 'in_marking' => 'Dans le bac, en lecture', 'proposed' => 'Proposition prête', 'by_hand' => 'À corriger à la main', 'held' => 'Corrigé, non publié', 'released' => 'Publié', 'revision' => 'Nouvelle version demandée'],
    'r_lede' => 'Le moteur suit ces règles pour proposer une note. Vous validez chaque note : il ne décide jamais seul.',
    'r_strict' => 'Sévérité', 'r_strict_o' => ['lenient' => 'Indulgente : fautes d’orthographe acceptées, points partiels généreux', 'normal' => 'Normale : petites fautes acceptées, points au prorata', 'strict' => 'Stricte : mots exacts, tout / moitié / rien'],
    'r_tone' => 'Ton de l’appréciation', 'r_tone_o' => ['encouraging' => 'Encourageant', 'neutral' => 'Neutre', 'direct' => 'Direct'],
    'r_crit' => 'Points clés par critère', 'r_keys' => 'Un point clé par ligne. Variantes acceptées sur la même ligne, séparées par |',
    'r_keys_ph' => "chlorophylle\nlumière | lumineuse\ndioxyde de carbone | CO2", 'r_need' => 'Points clés nécessaires pour tous les points (vide : tous)',
    'r_model' => 'Corrigé type (facultatif)', 'r_model_h' => 'Sert aux critères sans points clés, et de contrôle.',
    'r_struct' => 'Structure', 'r_min' => 'Longueur minimale (mots, 0 : aucune)', 'r_sections' => 'Parties obligatoires, une par ligne (ex. Introduction, Conclusion)',
    'r_pens' => 'Pénalités (points retirés, 0 : aucune)', 'r_late' => 'Retard', 'r_short' => 'Sous la longueur minimale', 'r_section' => 'Chaque partie absente', 'r_sim' => 'Copie proche d’une autre', 'r_sim_at' => 'à partir de (%)',
    'r_tpl' => 'Vos propres mots (facultatif)', 'r_tpl_h' => 'Remplacent la phrase d’ouverture selon le niveau, et la phrase de fin.', 'r_high' => 'Bonne copie (80 % et plus)', 'r_mid' => 'Copie moyenne (50 à 80 %)', 'r_low' => 'Copie faible (moins de 50 %)', 'r_closing' => 'Phrase de fin',
    'r_save' => 'Enregistrer les règles', 'r_save_rerun' => 'Enregistrer et relancer le moteur', 'r_keys_l' => 'Points clés', 'r_overall' => 'Note globale (sans barème)',
];

$criteria = MarkingBucket::criteria($lesson);
$rules = MarkingBucket::rules($lesson);
$max = (float)$lesson['assignment_max_score'];
$self = '/teacher/marking.php?lesson_id=' . $lessonId;
$filter = in_array($_GET['f'] ?? '', ['check', 'done', 'all'], true) ? $_GET['f'] : 'check';
$inFilter = static function (string $p, string $f): bool {
    return match ($f) {
        'check' => in_array($p, ['proposed', 'by_hand', 'in_marking'], true),
        'done' => in_array($p, ['held', 'released'], true),
        default => true,
    };
};

// ------------------------------------------------------------------------------------------------------------------------------
// Actions
// ------------------------------------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('CSRF');
    }
    $act = (string)($_POST['action'] ?? '');
    $go = static function (string $qs) use ($self): never {
        header('Location: ' . $self . $qs, true, 303);
        exit;
    };
    if ($act === 'send') {
        $r = MarkingBucket::send($pdo, $lesson, $teacherId);
        auditLog('assignment_to_marking', "Lesson #{$lessonId}: {$r['sent']} copies");
        MarkingBucket::run($pdo, $lessonId, 6.0);
        $go('&msg=sent&n=' . $r['sent']);
    }
    if ($act === 'rerun') {
        MarkingBucket::rerun($pdo, $lesson);
        MarkingBucket::run($pdo, $lessonId, 6.0);
        $go('&msg=rerun');
    }
    if ($act === 'rules') {
        $raw = (array)($_POST['r'] ?? []);
        MarkingBucket::saveRules($pdo, $lesson, $raw);
        auditLog('assignment_marking_rules', "Lesson #{$lessonId}");
        if (!empty($_POST['rerun'])) {
            $lesson = MarkingBucket::lesson($pdo, $lessonId, $teacherId);
            MarkingBucket::rerun($pdo, $lesson);
            MarkingBucket::run($pdo, $lessonId, 6.0);
            $go('&msg=rerun');
        }
        $go('&view=rules&msg=rules');
    }
    if ($act === 'release_all') {
        $r = Assignments::releaseMarks($pdo, $lessonId, $teacherId);
        auditLog('assignment_release_all', "Lesson #{$lessonId}");
        $go('&f=done&msg=released&n=' . (int)$r['released']);
    }
    if ($act === 'confirm') {
        $subId = (int)($_POST['sub'] ?? 0);
        $copy = MarkingBucket::copy($pdo, $lesson, $subId);
        if ($copy === null) {
            $go('&msg=err&e=not_found');
        }
        $rubricIn = [];
        foreach ($criteria as $c) {
            $rubricIn[$c['id']] = ['points' => (string)($_POST['pts'][$c['id']] ?? ''), 'comment' => (string)($_POST['cmt'][$c['id']] ?? '')];
        }
        // Penalties: only those the engine proposed, kept by the teacher (labels and points come from the server, not the form)
        $pens = [];
        foreach ((array)($copy['result']['penalties'] ?? []) as $i => $p) {
            if (!empty($_POST['pen'][$i])) {
                $pens[] = ['label' => sprintf($T['pen'][$p['code']] ?? $p['code'], $p['detail'] ?? ''), 'points' => $p['points']];
            }
        }
        $res = Assignments::grade($pdo, $subId, $teacherId, (string)($_POST['score'] ?? ''), (string)($_POST['feedback'] ?? ''), [
            'rubric' => $rubricIn, 'penalties' => $criteria !== [] ? $pens : [],
        ]);
        if (!$res['ok']) {
            $go('&f=' . $filter . '&sub=' . $subId . '&msg=err&e=' . urlencode((string)$res['error']));
        }
        auditLog('assignment_grade', "Submission #{$subId} (bucket)");
        // The next copy still to check, in the bucket's order
        $next = null;
        if (!empty($_POST['next'])) {
            foreach (MarkingBucket::copies($pdo, $lesson) as $c) {
                if ((int)$c['id'] !== $subId && $inFilter($c['pipeline'], 'check')) {
                    $next = (int)$c['id'];
                    break;
                }
            }
        }
        $go('&f=' . ($next ? 'check' : $filter) . '&sub=' . ($next ?: $subId) . '&msg=' . ($res['released'] ? 'saved' : 'saved_held'));
    }
    $go('');
}

// ------------------------------------------------------------------------------------------------------------------------------
// Page
// ------------------------------------------------------------------------------------------------------------------------------
$pending = MarkingBucket::pending($pdo, $lessonId);
if ($pending > 0) {
    MarkingBucket::run($pdo, $lessonId, 5.0);           // a slice while the teacher looks; the cron worker does the rest
    $pending = MarkingBucket::pending($pdo, $lessonId);
}
$copies = MarkingBucket::copies($pdo, $lesson);
$stats = MarkingBucket::stats($copies, $max);
$list = array_values(array_filter($copies, fn($c) => $inFilter($c['pipeline'], $filter)));
$toSend = count(array_filter($copies, fn($c) => in_array($c['pipeline'], ['submitted', 'locked'], true)));
$held = (int)($stats['count']['held'] ?? 0);
$deadlinePassed = !empty($lesson['assignment_deadline']) && strtotime((string)$lesson['assignment_deadline']) < time();
$view = ($_GET['view'] ?? '') === 'rules' ? 'rules' : 'copies';

$sel = null;
$prev = $next = null;
if ($view === 'copies') {
    $selId = (int)($_GET['sub'] ?? 0);
    if ($selId === 0 && $list) {
        $selId = (int)$list[0]['id'];
    }
    $sel = $selId ? MarkingBucket::copy($pdo, $lesson, $selId) : null;
    foreach ($list as $i => $c) {
        if ((int)$c['id'] === $selId) {
            $prev = $list[$i - 1]['id'] ?? null;
            $next = $list[$i + 1]['id'] ?? null;
        }
    }
}

$msg = (string)($_GET['msg'] ?? '');
$flash = match ($msg) {
    'sent' => sprintf($T['sent_ok'], (int)($_GET['n'] ?? 0)), 'rerun' => $T['rerun_ok'], 'rules' => $T['rules_ok'], 'saved' => $T['saved'], 'saved_held' => $T['saved_held'],
    'released' => sprintf($T['released_ok'], (int)($_GET['n'] ?? 0)), 'err' => $T['err'][$_GET['e'] ?? ''] ?? ($en ? 'Not saved.' : 'Non enregistré.'), default => '',
};

/** The copy's text, escaped, with the key points found highlighted. */
$highlight = static function (string $text, array $terms) use ($h): string {
    $out = $h($text);
    $terms = array_slice(array_values(array_unique(array_filter($terms, fn($t) => mb_strlen($t) >= 2))), 0, 60);
    usort($terms, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    if ($terms) {
        $alt = implode('|', array_map(fn($t) => preg_quote($h($t), '/'), $terms));
        $out = (string)preg_replace('/(?<![\p{L}\p{N}])(' . $alt . ')(?![\p{L}\p{N}])/iu', '<mark>$1</mark>', $out);
    }
    return nl2br($out);
};
$title = (string)($lesson['assignment_title'] ?: $lesson['title']);
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="<?= $en ? 'en' : 'fr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($pending > 0 && $view === 'copies'): ?><meta http-equiv="refresh" content="5"><?php endif; ?>
<title><?= $h($T['title']) ?> · <?= $h($title) ?> — StudyVibe</title>
<?= Brand::headLinks() ?>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<script>try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
<style>
  body { margin: 0; background: var(--paper); color: var(--ink); font: 16px/1.5 'Hanken Grotesk', system-ui, sans-serif; }
  main { max-width: 76rem; margin: 0 auto; padding: 1.25rem 1rem 4rem; }
  a { color: var(--clay); }
  h1 { font: 500 clamp(1.5rem, 4vw, 2.1rem)/1.15 'Fraunces', serif; margin: .4rem 0 .2rem; }
  h2 { font: 500 1.2rem/1.25 'Fraunces', serif; margin: 1.5rem 0 .6rem; }
  h3 { font-size: 1rem; margin: 0 0 .4rem; }
  .sub, .muted { color: var(--ink-2); }
  .sub { margin: 0 0 1rem; }
  .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 1rem 1.1rem; }
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; padding: .55rem 1rem; min-height: 44px; box-sizing: border-box; border: 1px solid var(--line-2); border-radius: 10px; background: var(--card); color: var(--ink); text-decoration: none; cursor: pointer; font: inherit; font-weight: 600; }
  .btn-main { background: var(--clay); border-color: var(--clay); color: #fff; }
  .btn[aria-disabled="true"] { opacity: .45; pointer-events: none; }
  .btn:focus-visible, a:focus-visible, .input:focus-visible { outline: 2px solid var(--clay); outline-offset: 2px; }
  .bar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin: 0 0 1rem; }
  .bar form { margin: 0; }
  .flash { background: #E8F2EA; color: #24402F; border-radius: 10px; padding: .7rem 1rem; margin: 0 0 1rem; }
  .flash.bad { background: #FBE9E7; color: #7A2412; }
  .note { background: color-mix(in srgb, var(--clay) 9%, var(--card)); border: 1px solid color-mix(in srgb, var(--clay) 35%, var(--line)); border-radius: 10px; padding: .7rem 1rem; margin: 0 0 1rem; }
  .pipe { display: flex; flex-wrap: wrap; gap: .35rem; margin: 0 0 1rem; padding: 0; list-style: none; }
  .chip { display: inline-block; padding: .1rem .55rem; border-radius: 999px; font-size: .78rem; font-weight: 600; background: var(--paper); border: 1px solid var(--line); white-space: nowrap; }
  .chip.high, .chip.released, .chip.ok { background: #E8F2EA; border-color: #9CC3A6; color: #24402F; }
  .chip.medium, .chip.held, .chip.proposed { background: #FDF3DC; border-color: #E5C77A; color: #5C4310; }
  .chip.low, .chip.by_hand, .chip.no { background: #FBE9E7; border-color: #E6A99B; color: #7A2412; }
  .tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0 0 .8rem; }
  .tabs a { padding: .35rem .8rem; min-height: 36px; box-sizing: border-box; display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 999px; text-decoration: none; color: var(--ink); font-size: .88rem; }
  .tabs a.on { background: var(--ink); color: var(--paper); border-color: var(--ink); }
  .grid { display: grid; grid-template-columns: minmax(16rem, 22rem) minmax(0, 1fr); gap: 1.1rem; align-items: start; }
  @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } .grid > .list { order: 2; } }
  .list ul { list-style: none; margin: 0; padding: 0; background: var(--card); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
  .list li a { display: flex; justify-content: space-between; gap: .6rem; padding: .6rem .8rem; border-bottom: 1px solid var(--line); text-decoration: none; color: var(--ink); }
  .list li:last-child a { border-bottom: 0; }
  .list li a.on { background: color-mix(in srgb, var(--clay) 9%, var(--card)); }
  .list .nm { font-weight: 600; font-size: .92rem; }
  .list .sc { font-variant-numeric: tabular-nums; font-size: .88rem; text-align: right; white-space: nowrap; }
  .crit { border-top: 1px solid var(--line); padding: .8rem 0; }
  .crit:first-of-type { border-top: 0; }
  .crit-head { display: flex; justify-content: space-between; gap: .5rem; align-items: baseline; flex-wrap: wrap; }
  .kp { display: flex; flex-wrap: wrap; gap: .3rem; margin: .4rem 0; }
  .row2 { display: grid; grid-template-columns: 7rem minmax(0, 1fr); gap: .6rem; }
  @media (max-width: 520px) { .row2 { grid-template-columns: 1fr; } }
  .input { width: 100%; box-sizing: border-box; min-height: 44px; }
  textarea.input { min-height: 7rem; }
  label { font-size: .88rem; font-weight: 600; display: block; margin: 0 0 .25rem; }
  .hint { font-size: .82rem; color: var(--ink-2); margin: .2rem 0 0; }
  .navrow { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: space-between; margin-top: 1rem; position: sticky; bottom: 0; background: var(--card); padding: .6rem 0; border-top: 1px solid var(--line); }
  .copytext { max-height: 32rem; overflow: auto; background: var(--paper); border: 1px solid var(--line); border-radius: 10px; padding: .8rem 1rem; font-size: .92rem; white-space: normal; word-break: break-word; }
  .copytext mark { background: #FCE7A6; color: inherit; padding: 0 .1em; border-radius: 3px; }
  .meta { display: flex; flex-wrap: wrap; gap: .4rem 1rem; font-size: .9rem; color: var(--ink-2); margin: .3rem 0 .8rem; }
  .fs { border: 1px solid var(--line); border-radius: 12px; padding: .9rem 1rem 1rem; margin: 0 0 1rem; background: var(--card); }
  .fs legend { font-weight: 700; padding: 0 .4rem; }
  .cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: .8rem; }
  .pen label { font-weight: 500; display: flex; gap: .5rem; align-items: center; min-height: 36px; }
</style>
</head>
<body>
<main>
  <a href="/teacher/dashboard.php?course_id=<?= (int)$lesson['course_id'] ?>#tab-assignments"><?= $h($T['back']) ?></a>
  <h1><?= $h($title) ?></h1>
  <p class="sub"><?= $h($T['title']) ?> · <?= $h($lesson['course_title']) ?> · <?= $h($T['max']) ?> <?= $h($n2($max)) ?> ·
    <?= $h($T['deadline']) ?> : <span class="num"><?= !empty($lesson['assignment_deadline']) ? $h(date('d/m/Y H:i', strtotime((string)$lesson['assignment_deadline']))) : $h($T['no_deadline']) ?></span></p>

  <?php if ($flash !== ''): ?><p class="flash<?= $msg === 'err' ? ' bad' : '' ?>" role="status"><?= $h($flash) ?></p><?php endif; ?>

  <ul class="pipe" aria-label="<?= $h($T['status']) ?>">
    <?php foreach ($T['p'] as $code => $label): if (empty($stats['count'][$code])) continue; ?>
      <li><span class="chip <?= $h($code) ?>"><?= $h($label) ?> · <?= (int)$stats['count'][$code] ?></span></li>
    <?php endforeach; ?>
  </ul>

  <div class="bar">
    <?php if ($toSend > 0): ?>
      <form method="post" onsubmit="return confirm(<?= $h(json_encode($deadlinePassed ? $T['send_confirm'] : $T['send_confirm_early'])) ?>)">
        <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="send">
        <button class="btn btn-main" type="submit"><?= $h(sprintf($T['send_n'], $toSend)) ?></button>
      </form>
    <?php endif; ?>
    <a class="btn" href="<?= $h($self . ($view === 'rules' ? '' : '&view=rules')) ?>"><?= $h($view === 'rules' ? $T['f_check'] : $T['rules']) ?></a>
    <?php if (!empty($lesson['assignment_marking_at'])): ?>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="rerun">
        <button class="btn" type="submit"><?= $h($T['rerun']) ?></button></form>
    <?php endif; ?>
    <?php if ($held > 0): ?>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="release_all">
        <button class="btn" type="submit"><?= $h(sprintf($T['release'], $held)) ?></button></form>
    <?php endif; ?>
  </div>

  <?php if (!MarkingEngine::hasRules($rules) && $view === 'copies'): ?><p class="note"><?= $h($T['no_rules']) ?> <a href="<?= $h($self . '&view=rules') ?>"><?= $h($T['rules']) ?></a></p><?php endif; ?>
  <?php if ($pending > 0 && $view === 'copies'): ?><p class="note" role="status"><?= $h(sprintf($T['running'], $pending)) ?></p><?php endif; ?>
  <?php if ($view === 'copies'): ?>
    <p class="sub"><?= $stats['checked'] > 0 ? $h(sprintf($T['reliab'], $stats['checked'], $n2($stats['avg_gap']), $stats['within'], $n2($stats['point']))) : $h($T['reliab_none']) ?></p>
  <?php endif; ?>

<?php if ($view === 'rules'): ?>
  <!-- ======================= Marking rules ======================= -->
  <p class="sub"><?= $h($T['r_lede']) ?></p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="rules">
    <fieldset class="fs"><legend><?= $h($T['r_strict']) ?> · <?= $h($T['r_tone']) ?></legend>
      <div class="cols">
        <div><label for="r-strict"><?= $h($T['r_strict']) ?></label>
          <select class="input" id="r-strict" name="r[strictness]"><?php foreach ($T['r_strict_o'] as $k => $v): ?><option value="<?= $k ?>" <?= $rules['strictness'] === $k ? 'selected' : '' ?>><?= $h($v) ?></option><?php endforeach; ?></select></div>
        <div><label for="r-tone"><?= $h($T['r_tone']) ?></label>
          <select class="input" id="r-tone" name="r[tone]"><?php foreach ($T['r_tone_o'] as $k => $v): ?><option value="<?= $k ?>" <?= $rules['tone'] === $k ? 'selected' : '' ?>><?= $h($v) ?></option><?php endforeach; ?></select></div>
      </div>
    </fieldset>
    <fieldset class="fs"><legend><?= $h($T['r_crit']) ?></legend>
      <p class="hint"><?= $h($T['r_keys']) ?></p>
      <?php foreach (($criteria ?: [['id' => 'all', 'title' => $T['r_overall'], 'max' => $max]]) as $c): $cr = $rules['criteria'][$c['id']] ?? ['keys' => [], 'need' => 0]; ?>
        <div class="crit">
          <div class="crit-head"><h3><?= $h($c['title']) ?></h3><span class="muted num"><?= $h($n2($c['max'])) ?> pts</span></div>
          <div class="row2">
            <div><label for="need-<?= $h($c['id']) ?>"><?= $h($T['r_need']) ?></label>
              <input class="input num" id="need-<?= $h($c['id']) ?>" name="r[criteria][<?= $h($c['id']) ?>][need]" inputmode="numeric" value="<?= $cr['keys'] && $cr['need'] < count($cr['keys']) ? (int)$cr['need'] : '' ?>"></div>
            <div><label for="keys-<?= $h($c['id']) ?>"><?= $h($T['r_keys_l']) ?></label>
              <textarea class="input" id="keys-<?= $h($c['id']) ?>" name="r[criteria][<?= $h($c['id']) ?>][keys]" placeholder="<?= $h($T['r_keys_ph']) ?>"><?= $h(implode("\n", array_map(fn($k) => implode(' | ', $k), $cr['keys']))) ?></textarea></div>
          </div>
        </div>
      <?php endforeach; ?>
    </fieldset>
    <fieldset class="fs"><legend><?= $h($T['r_model']) ?></legend>
      <p class="hint"><?= $h($T['r_model_h']) ?></p>
      <textarea class="input" name="r[model_answer]" aria-label="<?= $h($T['r_model']) ?>" style="min-height:10rem"><?= $h($rules['model_answer']) ?></textarea>
    </fieldset>
    <fieldset class="fs"><legend><?= $h($T['r_struct']) ?></legend>
      <div class="cols">
        <div><label for="r-min"><?= $h($T['r_min']) ?></label><input class="input num" id="r-min" name="r[min_words]" inputmode="numeric" value="<?= (int)$rules['min_words'] ?>"></div>
        <div><label for="r-sec"><?= $h($T['r_sections']) ?></label><textarea class="input" id="r-sec" name="r[sections]" style="min-height:4.5rem"><?= $h(implode("\n", $rules['sections'])) ?></textarea></div>
      </div>
    </fieldset>
    <fieldset class="fs"><legend><?= $h($T['r_pens']) ?></legend>
      <div class="cols">
        <?php foreach (['late' => 'r_late', 'short' => 'r_short', 'section' => 'r_section', 'similarity' => 'r_sim', 'similarity_at' => 'r_sim_at'] as $k => $lab): ?>
          <div><label for="pen-<?= $k ?>"><?= $h($T[$lab]) ?></label><input class="input num" id="pen-<?= $k ?>" name="r[penalties][<?= $k ?>]" inputmode="decimal" value="<?= $h($n2($rules['penalties'][$k])) ?>"></div>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <fieldset class="fs"><legend><?= $h($T['r_tpl']) ?></legend>
      <p class="hint"><?= $h($T['r_tpl_h']) ?></p>
      <div class="cols">
        <?php foreach (['high' => 'r_high', 'mid' => 'r_mid', 'low' => 'r_low', 'closing' => 'r_closing'] as $k => $lab): ?>
          <div><label for="tpl-<?= $k ?>"><?= $h($T[$lab]) ?></label><input class="input" id="tpl-<?= $k ?>" name="r[templates][<?= $k ?>]" maxlength="400" value="<?= $h($rules['templates'][$k] ?? '') ?>"></div>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <div class="bar">
      <button class="btn btn-main" type="submit"><?= $h($T['r_save']) ?></button>
      <?php if (!empty($lesson['assignment_marking_at'])): ?><button class="btn" type="submit" name="rerun" value="1"><?= $h($T['r_save_rerun']) ?></button><?php endif; ?>
    </div>
  </form>

<?php else: ?>
  <!-- ======================= Copies ======================= -->
  <nav class="tabs">
    <?php foreach (['check' => 'f_check', 'done' => 'f_done', 'all' => 'f_all'] as $f => $lab):
      $cnt = count(array_filter($copies, fn($c) => $inFilter($c['pipeline'], $f))); ?>
      <a href="<?= $h($self . '&f=' . $f) ?>" class="<?= $filter === $f ? 'on' : '' ?>"><?= $h($T[$lab]) ?> (<?= $cnt ?>)</a>
    <?php endforeach; ?>
  </nav>
  <div class="grid">
    <div class="list">
      <?php if (!$list): ?><p class="card muted"><?= $h($T['none']) ?></p><?php else: ?>
      <ul>
        <?php foreach ($list as $c): ?>
          <li><a href="<?= $h($self . '&f=' . $filter . '&sub=' . (int)$c['id']) ?>" class="<?= $sel && (int)$sel['id'] === (int)$c['id'] ? 'on' : '' ?>">
            <span><span class="nm"><?= $h($c['name']) ?></span><br>
              <span class="chip <?= $h($c['pipeline']) ?>"><?= $h($T['p'][$c['pipeline']] ?? $c['pipeline']) ?></span>
              <?php if ($c['pipeline'] === 'proposed' && $c['engine_confidence']): ?><span class="chip <?= $h($c['engine_confidence']) ?>"><?= $h($T['conf'][$c['engine_confidence']] ?? '') ?></span><?php endif; ?></span>
            <span class="sc"><?= $c['score'] !== null ? '<strong>' . $h($n2($c['score'])) . '</strong>' : ($c['engine_score'] !== null ? $h($n2($c['engine_score'])) . ' ?' : '—') ?><br><span class="muted">/ <?= $h($n2($max)) ?></span></span>
          </a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <section class="card" aria-live="polite">
      <?php if ($sel === null): ?>
        <p class="muted"><?= $h($T['pick']) ?></p>
      <?php else:
        $res = $sel['result'];
        $isMarked = $sel['graded_at'] !== null;
        $rowsBy = [];
        foreach ((array)($res['rows'] ?? []) as $r) { $rowsBy[$r['id']] = $r; }
        $doneBy = [];
        foreach ($sel['rubric_rows'] as $r) { $doneBy[$r['id']] = $r; }
        $canMark = in_array($sel['pipeline'], ['proposed', 'by_hand', 'held', 'released', 'in_marking', 'locked', 'submitted'], true);
      ?>
        <h2 style="margin-top:0"><?= $h($sel['name']) ?></h2>
        <div class="meta">
          <?php if ($sel['matricule']): ?><span class="num"><?= $h($sel['matricule']) ?></span><?php endif; ?>
          <span><?= $h($T['sent']) ?> <span class="num"><?= $h(date('d/m/Y H:i', strtotime((string)$sel['submitted_at']))) ?></span></span>
          <?php if ((int)$sel['is_late'] === 1): ?><span class="chip no"><?= $h($T['late']) ?></span><?php endif; ?>
          <?php if ((int)$sel['attempt_count'] > 1): ?><span><?= $h(sprintf($T['version'], (int)$sel['attempt_count'])) ?></span><?php endif; ?>
          <span class="chip <?= $h($sel['pipeline']) ?>"><?= $h($T['p'][$sel['pipeline']] ?? '') ?></span>
        </div>
        <div class="meta">
          <?php if ($sel['submitted_file_path']): ?><span><?= $h($T['file']) ?> : <a href="/download.php?type=assignment&file=<?= rawurlencode((string)$sel['submitted_file_path']) ?>"><?= $h($sel['submitted_file_name'] ?: 'copie') ?></a></span><?php endif; ?>
          <?php if ($sel['submitted_link']): ?><span><?= $h($T['link']) ?> : <a href="<?= $h($sel['submitted_link']) ?>" target="_blank" rel="noopener noreferrer"><?= $h(mb_strimwidth((string)$sel['submitted_link'], 0, 60, '…')) ?></a></span><?php endif; ?>
          <?php if (!empty($res['words'])): ?><span><?= $h(sprintf($T['words'], (int)$res['words'])) ?></span><?php endif; ?>
        </div>

        <?php if ($sel['engine_error']): ?><p class="note"><?= $h($T['errors'][$sel['engine_error']] ?? $sel['engine_error']) ?></p><?php endif; ?>
        <?php if ($isMarked): ?><p class="note"><?= $h(sprintf($T['already'], $n2($sel['score']) . ' / ' . $n2($max))) ?></p><?php endif; ?>
        <?php if (!empty($res['ok'])): ?>
          <p><span class="chip <?= $h($res['confidence']) ?>"><?= $h($T['conf'][$res['confidence']] ?? '') ?></span>
            <?php if ($res['reasons']): ?><span class="muted" style="font-size:.88rem"> · <?= $h(implode(' · ', array_map(fn($r) => $T['reasons'][$r] ?? $r, $res['reasons']))) ?></span><?php endif; ?></p>
        <?php endif; ?>

        <?php if ($canMark): ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="confirm">
          <input type="hidden" name="sub" value="<?= (int)$sel['id'] ?>">
          <?php if ($criteria): foreach ($criteria as $c):
            $er = $rowsBy[$c['id']] ?? null; $dn = $doneBy[$c['id']] ?? null;
            $val = $dn !== null ? $dn['points'] : ($er['points'] ?? null); ?>
            <div class="crit">
              <div class="crit-head"><h3><?= $h($c['title']) ?></h3><span class="muted num">/ <?= $h($n2($c['max'])) ?><?= $er && $er['points'] !== null ? ' · ' . $h($T['engine']) . ' ' . $h($n2($er['points'])) : '' ?></span></div>
              <?php if ($er): ?>
                <?php if ($er['basis'] === 'keys'): ?>
                  <div class="kp"><?php foreach ($er['found'] as $k): ?><span class="chip ok">✓ <?= $h($k) ?></span><?php endforeach; ?><?php foreach ($er['missing'] as $k): ?><span class="chip no">✗ <?= $h($k) ?></span><?php endforeach; ?></div>
                <?php elseif ($er['basis'] === 'model'): ?><p class="hint"><?= $h(sprintf($T['basis_model'], (int)round(100 * (float)($res['model_similarity'] ?? 0)))) ?></p>
                <?php else: ?><p class="hint"><?= $h($T['basis_none']) ?></p><?php endif; ?>
              <?php endif; ?>
              <div class="row2">
                <div><label for="pts-<?= $h($c['id']) ?>"><?= $h($T['points']) ?></label>
                  <input class="input num" id="pts-<?= $h($c['id']) ?>" name="pts[<?= $h($c['id']) ?>]" inputmode="decimal" required value="<?= $val !== null ? $h($n2($val)) : '' ?>"></div>
                <div><label for="cmt-<?= $h($c['id']) ?>"><?= $h($T['comment']) ?></label>
                  <input class="input" id="cmt-<?= $h($c['id']) ?>" name="cmt[<?= $h($c['id']) ?>]" maxlength="500" value="<?= $h($dn['comment'] ?? '') ?>"></div>
              </div>
            </div>
          <?php endforeach; else:
            $er = $rowsBy['all'] ?? null; $val = $isMarked ? $sel['score'] : ($res['score'] ?? null); ?>
            <?php if ($er && $er['basis'] === 'keys'): ?>
              <div class="kp"><?php foreach ($er['found'] as $k): ?><span class="chip ok">✓ <?= $h($k) ?></span><?php endforeach; ?><?php foreach ($er['missing'] as $k): ?><span class="chip no">✗ <?= $h($k) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
            <div class="row2" style="margin:.6rem 0">
              <div><label for="score"><?= $h($T['final']) ?> / <?= $h($n2($max)) ?></label>
                <input class="input num" id="score" name="score" inputmode="decimal" required value="<?= $val !== null ? $h($n2($val)) : '' ?>"></div>
            </div>
          <?php endif; ?>

          <?php if ($criteria && !$isMarked && !empty($res['penalties'])): ?>
            <div class="crit pen"><h3><?= $h($T['pens']) ?></h3>
              <?php foreach ($res['penalties'] as $i => $p): ?>
                <label><input type="checkbox" name="pen[<?= (int)$i ?>]" value="1" checked> <?= $h(sprintf($T['pen'][$p['code']] ?? $p['code'], $p['detail'] ?? '')) ?> (−<?= $h($n2($p['points'])) ?>)</label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="crit"><label for="feedback"><?= $h($T['feedback']) ?></label>
            <textarea class="input" id="feedback" name="feedback" maxlength="5000"><?= $h($isMarked ? (string)$sel['feedback'] : (string)($res['feedback'] ?? '')) ?></textarea></div>

          <div class="navrow">
            <span style="display:flex;gap:.5rem">
              <a class="btn" href="<?= $h($self . '&f=' . $filter . '&sub=' . (int)$prev) ?>" aria-disabled="<?= $prev ? 'false' : 'true' ?>">← <?= $h($T['prev']) ?></a>
              <a class="btn" href="<?= $h($self . '&f=' . $filter . '&sub=' . (int)$next) ?>" aria-disabled="<?= $next ? 'false' : 'true' ?>"><?= $h($T['next']) ?> →</a>
            </span>
            <span style="display:flex;gap:.5rem;flex-wrap:wrap">
              <button class="btn" type="submit"><?= $h($T['confirm_only']) ?></button>
              <button class="btn btn-main" type="submit" name="next" value="1"><?= $h($T['confirm']) ?></button>
            </span>
          </div>
        </form>
        <?php endif; ?>

        <?php if (!empty($sel['engine_text'])): ?>
          <h2><?= $h($T['copy']) ?></h2>
          <div class="copytext"><?= $highlight((string)$sel['engine_text'], (array)($res['highlights'] ?? [])) ?></div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>
</main>
</body>
</html>
