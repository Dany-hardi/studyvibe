<?php
declare(strict_types=1);

/**
 * First-login walkthrough. Daniel, the guide, presents each tab of the dashboard while the rest of the page is dimmed.
 *
 *   <?php Tour::render('student', $lang, $blocked); ?>   just before </body> of a dashboard
 *
 * Starts by itself while users.tour_seen_at is NULL (new accounts only, see Database.php), and can always be replayed
 * with the round button at the bottom of the page. Steps carry a list of CSS selectors: the first visible one wins,
 * which is how the same step lands on the side rail on desktop and on the bottom bar on phones.
 */
final class Tour
{
    /** @param bool $blocked true while something else must be done first (e.g. the compulsory matricule form) */
    public static function render(string $role, string $lang, bool $blocked = false): void
    {
        $lang  = $lang === 'en' ? 'en' : 'fr';
        $steps = self::steps($role, $lang);
        if (!$steps) {
            return;
        }

        $seen = true;
        try {
            $stmt = Database::getInstance()->prepare('SELECT tour_seen_at FROM users WHERE id = :id');
            $stmt->execute(['id' => (int)($_SESSION['user_id'] ?? 0)]);
            $row  = $stmt->fetch(PDO::FETCH_NUM);
            $seen = !$row || $row[0] !== null;
        } catch (Throwable $e) {
            $seen = true;
        }

        $contexts = ['main' => $steps];
        $reader   = self::readerSteps($role, $lang);
        if ($reader) {
            $contexts['reader'] = $reader;   // Daniel adapts: inside the lesson reader he explains the reader, not the dashboard tabs
        }

        $cfg = [
            'role'      => $role,
            'lang'      => $lang,
            'autostart' => !$seen && !$blocked,
            'contexts'  => $contexts,
            'ui'        => self::ui($lang),
        ];
        echo '<link rel="stylesheet" href="/assets/css/tour.css">' . "\n";
        echo '<script>window.SV_TOUR = ' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>' . "\n";
        echo '<script src="/assets/js/tour.js" defer></script>' . "\n";
    }

    private static function ui(string $lang): array
    {
        return $lang === 'en'
            ? ['name' => 'Daniel', 'role' => 'Your guide', 'next' => 'Next', 'back' => 'Back', 'skip' => 'Skip', 'done' => 'Got it',
               'start' => 'Start the tour', 'step' => 'Step :n of :t', 'fab' => 'Guided tour with Daniel', 'fab_reader' => 'Daniel explains this screen', 'close' => 'Close the tour']
            : ['name' => 'Daniel', 'role' => 'Votre guide', 'next' => 'Suivant', 'back' => 'Précédent', 'skip' => 'Passer', 'done' => 'C’est compris',
               'start' => 'Commencer la visite', 'step' => 'Étape :n sur :t', 'fab' => 'Visite guidée avec Daniel', 'fab_reader' => 'Daniel vous explique cet écran', 'close' => 'Fermer la visite'];
    }

    /** Steps for a screen that covers the dashboard (the lesson reader). Targets are tried in order, `before` opens a panel first. */
    private static function readerSteps(string $role, string $lang): array
    {
        if ($role !== 'student') {
            return [];
        }
        $en = $lang === 'en';
        $pick = fn(string $fr, string $e): string => $en ? $e : $fr;
        return [
            ['id' => 'r-welcome', 'title' => $pick('Vous êtes dans la lecture', 'You are in the reader'),
             'text' => $pick('Je vous montre comment cet écran fonctionne. C’est rapide, et vous pouvez m’arrêter avec Échap.',
                             'Let me show you how this screen works. It is quick, and Esc stops me any time.')],
            ['id' => 'r-outline', 'title' => $pick('Le plan du cours', 'The course outline'), 'before' => 'outline', 'target' => ['#study-sidebar'],
             'text' => $pick('Vos leçons dans l’ordre. Une coche veut dire terminée. Un cadenas s’ouvre tout seul dès que vous finissez la leçon d’avant, on ne peut pas sauter d’étape.',
                             'Your lessons in order. A tick means finished. A padlock opens by itself as soon as you finish the lesson before it, steps cannot be skipped.')],
            ['id' => 'r-count', 'title' => $pick('Votre avancement', 'Your progress'), 'before' => 'outline', 'target' => ['#rd-outline-count'],
             'text' => $pick('Ici, le nombre de leçons terminées sur le total du cours. La barre dessous se remplit au fil des leçons.',
                             'Here, the number of finished lessons out of the course total. The bar below fills up as you go.')],
            ['id' => 'r-lesson', 'title' => $pick('La leçon', 'The lesson'), 'before' => 'close', 'target' => ['.rd-article'],
             'text' => $pick('Lisez jusqu’au bout. Pour un PDF, chaque page doit être vue. Quand une leçon a plusieurs vidéos, elles se suivent une par une, la suivante s’ouvre quand la vôtre est terminée.',
                             'Read to the end. For a PDF, every page has to be seen. When a lesson has several videos, they go one after the other, the next opens once yours is finished.')],
            ['id' => 'r-companion', 'title' => $pick('Notes et assistant', 'Notes and assistant'), 'before' => 'close', 'target' => ['#btn-companion'],
             'text' => $pick('Prenez des notes liées à la leçon, posez vos questions à l’assistant ou à votre enseignant, tout se passe dans ce panneau.',
                             'Take notes tied to the lesson, ask the assistant or your teacher a question, it all happens in this panel.')],
            ['id' => 'r-timer', 'title' => $pick('Temps d’étude', 'Study time'), 'before' => 'close', 'target' => ['.rd-timer'],
             'text' => $pick('Le chronomètre compte le temps passé sur la leçon. Il sert à votre suivi, rien d’autre.',
                             'The timer counts the time spent on the lesson. It is used for your own follow-up, nothing else.')],
            ['id' => 'r-leave', 'title' => $pick('Quitter la lecture', 'Leave the reader'), 'before' => 'close', 'target' => ['.rd-leave'],
             'text' => $pick('Vous pouvez partir à tout moment. Votre progression est enregistrée et vous reprendrez là où vous vous êtes arrêté.',
                             'You can leave at any time. Your progress is saved and you will pick up where you stopped.')],
            ['id' => 'r-end', 'title' => $pick('À tout de suite', 'See you in a moment'),
             'text' => $pick('Quand vous terminez une leçon, je passe vous féliciter et la suivante s’ouvre toute seule. Bonne lecture !',
                             'When you finish a lesson I will stop by to congratulate you and the next one opens by itself. Happy reading!')],
        ];
    }

    private static function steps(string $role, string $lang): array
    {
        $en = $lang === 'en';
        $pick = fn(string $fr, string $e): string => $en ? $e : $fr;

        if ($role === 'student') {
            $rail = fn(string $k): array => ['.sd-rail [data-nav="' . $k . '"]', '.sd-bar-nav [data-nav="' . $k . '"]'];
            return [
                ['id' => 'welcome', 'title' => $pick('Bonjour, je suis Daniel', 'Hi, I’m Daniel'),
                 'text' => $pick('Je vous fais faire le tour de votre espace en moins d’une minute. Vous pouvez m’arrêter à tout moment avec la touche Échap, et je reste disponible en bas de l’écran si vous voulez revenir.',
                                 'I’ll walk you around your space in under a minute. You can stop me any time with the Esc key, and I’ll stay at the bottom of the screen in case you want me back.')],
                ['id' => 'home', 'title' => $pick('Accueil', 'Home'), 'target' => $rail('home'), 'click' => $rail('home'),
                 'text' => $pick('Votre point de départ. Vous y trouvez le cours à reprendre là où vous vous êtes arrêté, les prochains examens et vos derniers certificats.',
                                 'Your starting point. It shows the course to pick up where you left off, your next exams and your latest certificates.')],
                ['id' => 'courses', 'title' => $pick('Cours', 'Courses'), 'target' => $rail('courses'), 'click' => $rail('courses'),
                 'text' => $pick('Tous vos cours sont ici. On lit le texte, on regarde la vidéo, on ouvre le PDF, et on prend des notes sans quitter la page.',
                                 'All your courses live here. You read the text, watch the video, open the PDF and take notes without leaving the page.')],
                ['id' => 'courses-tabs', 'title' => $pick('Trois onglets dans Cours', 'Three tabs inside Courses'), 'target' => ['.sd-tabs'], 'click' => $rail('courses'),
                 'text' => $pick('Mes cours pour ceux auxquels vous êtes inscrit, Catalogue pour vous inscrire à un nouveau cours en un clic, et Bibliothèque pour les documents que vos enseignants partagent.',
                                 'My courses for the ones you are enrolled in, Catalogue to join a new course in one click, and Library for the documents your teachers share.')],
                ['id' => 'evals', 'title' => $pick('Évaluations', 'Evaluations'), 'target' => $rail('evals'), 'click' => $rail('evals'),
                 'text' => $pick('Les examens en direct et les devoirs à rendre. Quand une séance est ouverte, vous la rejoignez d’ici, et tout le monde démarre à la même seconde.',
                                 'Live exams and assignments to hand in. When a session is open you join it from here, and everyone starts on the same second.')],
                ['id' => 'results', 'title' => $pick('Résultats', 'Results'), 'target' => $rail('results'), 'click' => $rail('results'),
                 'text' => $pick('Vos notes, quiz après quiz, et votre relevé complet. Pas besoin de demander à qui que ce soit.',
                                 'Your grades, quiz after quiz, and your full transcript. No need to ask anyone.')],
                ['id' => 'certs', 'title' => $pick('Certificats', 'Certificates'), 'target' => $rail('certs'), 'click' => $rail('certs'),
                 'text' => $pick('Chaque module réussi vous donne un certificat PDF avec un code unique. Un recruteur peut le vérifier en quelques secondes.',
                                 'Every module you pass earns a PDF certificate with a unique code. A recruiter can check it in a few seconds.')],
                ['id' => 'notif', 'title' => $pick('Notifications', 'Notifications'), 'target' => ['#notif-btn', '#notif-btn-m'], 'click' => $rail('home'),
                 'text' => $pick('La cloche vous prévient quand une séance démarre, qu’une note arrive ou qu’un certificat est prêt.',
                                 'The bell tells you when a session starts, a grade comes in or a certificate is ready.')],
                ['id' => 'profile', 'title' => $pick('Votre profil', 'Your profile'), 'target' => ['#tab-btn-profile', '.sd-topbar-tools [data-nav="profile"]'], 'click' => ['#tab-btn-profile', '.sd-topbar-tools [data-nav="profile"]'],
                 'text' => $pick('Photo, matricule, langue et thème sombre se règlent ici. Vérifiez que votre matricule est bien renseigné, vos enseignants s’en servent pour vos notes.',
                                 'Photo, student number, language and dark theme are set here. Check that your student number is filled in, your teachers use it for your grades.')],
                ['id' => 'end', 'title' => $pick('Voilà, c’est tout', 'That’s the tour'), 'click' => $rail('home'),
                 'text' => $pick('Si vous avez besoin de moi plus tard, le bouton rond en bas à droite me rappelle. Bonnes études !',
                                 'If you need me later, the round button at the bottom right brings me back. Happy studying!')],
            ];
        }

        if ($role === 'teacher') {
            $tab = fn(string $t): array => ['[data-tab-target="' . $t . '"]'];
            return [
                ['id' => 'welcome', 'title' => $pick('Bonjour, je suis Daniel', 'Hi, I’m Daniel'),
                 'text' => $pick('Je vous présente vos outils d’enseignant. Presque tout tourne autour d’un cours, donc on commence par là. Échap m’arrête à tout moment, et le bouton rond en bas de l’écran me rappelle.',
                                 'Let me show you your teaching tools. Nearly everything revolves around a course, so we start there. Esc stops me any time, and the round button at the bottom of the screen brings me back.')],
                ['id' => 'course-pick', 'title' => $pick('Choisir un cours', 'Pick a course'), 'rail' => true, 'target' => ['.t-course-pick'],
                 'text' => $pick('Sélectionnez ici le cours sur lequel vous travaillez. Tant qu’aucun cours n’est choisi, la plupart des onglets restent grisés.',
                                 'Select the course you are working on here. Until one is chosen, most tabs stay greyed out.')],
                ['id' => 'course-new', 'title' => $pick('Créer un cours', 'Create a course'), 'rail' => true, 'target' => ['.t-newcourse'],
                 'text' => $pick('Pas encore de cours ? Un clic ici et vous démarrez le premier. Donnez-lui un titre, vous ajouterez le reste ensuite.',
                                 'No course yet? One click here starts your first. Give it a title, you can add the rest later.')],
                ['id' => 'today', 'title' => $pick('Aujourd’hui', 'Today'), 'rail' => true, 'target' => $tab('tab-overview'), 'click' => $tab('tab-overview'),
                 'text' => $pick('Le résumé de votre journée : séances en cours, devoirs rendus, questions qui attendent une réponse.',
                                 'A summary of your day: sessions running, assignments handed in, questions waiting for an answer.')],
                ['id' => 'course', 'title' => $pick('Cours', 'Course'), 'rail' => true, 'target' => $tab('tab-course'), 'click' => $tab('tab-course'),
                 'text' => $pick('C’est là que vous construisez : chapitres, leçons, texte, vidéo, PDF et quiz de leçon.',
                                 'This is where you build: chapters, lessons, text, video, PDF and lesson quizzes.')],
                ['id' => 'library', 'title' => $pick('Bibliothèque', 'Library'), 'rail' => true, 'target' => $tab('tab-library'), 'click' => $tab('tab-library'),
                 'text' => $pick('Vos documents et ressources pour les étudiants du cours. Vous pouvez en rendre certains obligatoires.',
                                 'Your documents and resources for the students of the course. You can make some of them compulsory.')],
                ['id' => 'live', 'title' => $pick('Examen en direct', 'Live exam'), 'rail' => true, 'target' => $tab('tab-live-eval'), 'click' => $tab('tab-live-eval'),
                 'text' => $pick('Préparez une séance, importez vos questions depuis un fichier CSV, lancez le décompte et suivez les participants en temps réel. Pause, reprise et export des notes sont là aussi.',
                                 'Set up a session, import your questions from a CSV file, start the countdown and follow participants in real time. Pause, resume and grade export are here too.')],
                ['id' => 'grades', 'title' => $pick('Notes', 'Grades'), 'rail' => true, 'target' => $tab('tab-grades'), 'click' => $tab('tab-grades'),
                 'text' => $pick('Toutes les notes de vos étudiants dans un tableau avec recherche. Export en CSV, Excel ou PDF.',
                                 'Every grade of your students in one searchable table. Export to CSV, Excel or PDF.')],
                ['id' => 'assign', 'title' => $pick('Devoirs', 'Assignments'), 'rail' => true, 'target' => $tab('tab-assignments'), 'click' => $tab('tab-assignments'),
                 'text' => $pick('Les devoirs libres avec une date limite. Les rendus arrivent ici et le petit badge vous indique les nouveaux.',
                                 'Open assignments with a deadline. Submissions land here and the small badge shows the new ones.')],
                ['id' => 'qa', 'title' => $pick('Questions', 'Questions'), 'rail' => true, 'target' => $tab('tab-comments'), 'click' => $tab('tab-comments'),
                 'text' => $pick('Les questions et commentaires de vos étudiants. Répondez depuis cette page, le badge compte celles qui attendent.',
                                 'Questions and comments from your students. Reply from this page, the badge counts the ones still waiting.')],
                ['id' => 'end', 'title' => $pick('Voilà, c’est tout', 'That’s the tour'), 'rail' => true, 'click' => $tab('tab-overview'),
                 'text' => $pick('Créez votre premier cours et le reste se débloque. Si vous voulez me revoir, le bouton rond en bas à droite est là. Bonne rentrée !',
                                 'Create your first course and the rest unlocks. If you want to see me again, the round button at the bottom right is there. Have a great term!')],
            ];
        }

        return [];
    }
}
