<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/EmailTheme.php';

/**
 * StudyVibe LMS - Mail service
 *
 * Native SMTP over stream sockets (no Composer dependency), with a fallback to PHP mail(). Every message is built from the
 * pieces of lib/EmailTheme.php (light, rounded design), sent as multipart (HTML + plain text) in quoted-printable, with the
 * logo attached inline (cid:svlogo@studyvibe) so it always shows.
 *
 * @package    StudyVibe
 */
class Mailer
{
    /** @var string|null Tracks description of the last failed SMTP transaction error */
    private static ?string $lastError = null;

    /** @var bool Disables SMTP attempts for current request after a connection failure to avoid cumulative timeouts */
    private static bool $smtpDisabled = false;

    /** Tests and previews set this to receive (to, subject, html) instead of sending anything. */
    public static ?\Closure $capture = null;

    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    public static function isConfigured(): bool
    {
        return defined('SMTP_HOST') && SMTP_HOST !== '' && defined('SMTP_USER') && SMTP_USER !== '';
    }

    /**
     * Sends an HTML email, routing through SMTP if configured, or falling back to PHP mail().
     * The plain-text version and the inline logo are added here, so callers only provide the HTML.
     */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        self::$lastError = null;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) {
            self::$lastError = 'Adresse ou objet invalide.';
            return false;
        }
        if (self::$capture !== null) {
            (self::$capture)($to, $subject, $htmlBody);
            return true;
        }
        $from     = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@studyvibe.edu';
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'StudyVibe';

        // Route using native SMTP sockets if credentials are valid and not disabled
        if (!self::$smtpDisabled && self::isConfigured()) {
            $ok = self::sendSmtp($to, $subject, $htmlBody, $from, $fromName);
            if ($ok) {
                return true;
            }
            // Mark SMTP disabled for remainder of script execution to prevent cumulative timeouts
            self::$smtpDisabled = true;
        }

        // Fallback: system mail() function
        $mime = self::buildMime($to, $subject, $htmlBody, $from, $fromName);
        $ok = @mail($to, $mime['subject'], $mime['body'], $mime['headers']);
        if (!$ok) {
            self::$lastError = 'mail() PHP indisponible — configurez SMTP_HOST dans .env';
        }
        return $ok;
    }

    /**
     * The whole message as MIME parts. Public so it can be inspected (tests, previews).
     *
     * @return array{headers:string,subject:string,body:string}  headers has no To/Subject and no trailing line break
     */
    public static function buildMime(string $to, string $subject, string $html, string $from, string $fromName): array
    {
        $crlf = static fn(string $s): string => str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $s));
        $qp = static fn(string $s): string => quoted_printable_encode($crlf($s));
        $host = parse_url((string)(defined('APP_URL') ? APP_URL : ''), PHP_URL_HOST) ?: 'studyvibe.local';

        $text = EmailTheme::toText($html);
        $logo = __DIR__ . '/assets/img/logo-email.png';
        $inline = str_contains($html, 'cid:' . EmailTheme::LOGO_CID) && is_file($logo);

        $alt = 'svalt_' . bin2hex(random_bytes(8));
        $altBody = "--{$alt}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . $qp($text) . "\r\n"
                 . "--{$alt}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . $qp($html) . "\r\n"
                 . "--{$alt}--\r\n";

        $headers = 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <{$from}>\r\n"
                 . 'Date: ' . date('r') . "\r\n"
                 . 'Message-ID: <' . bin2hex(random_bytes(12)) . "@{$host}>\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "X-Auto-Response-Suppress: OOF, AutoReply\r\n";

        if ($inline) {
            $rel = 'svrel_' . bin2hex(random_bytes(8));
            $headers .= "Content-Type: multipart/related; type=\"multipart/alternative\"; boundary=\"{$rel}\"";
            $body = "--{$rel}\r\nContent-Type: multipart/alternative; boundary=\"{$alt}\"\r\n\r\n" . $altBody
                  . "--{$rel}\r\nContent-Type: image/png; name=\"studyvibe.png\"\r\nContent-Transfer-Encoding: base64\r\n"
                  . 'Content-ID: <' . EmailTheme::LOGO_CID . ">\r\nContent-Disposition: inline; filename=\"studyvibe.png\"\r\n\r\n"
                  . chunk_split(base64_encode((string)file_get_contents($logo)), 76, "\r\n")
                  . "--{$rel}--\r\n";
        } else {
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$alt}\"";
            $body = $altBody;
        }
        return ['headers' => $headers, 'subject' => '=?UTF-8?B?' . base64_encode($subject) . '?=', 'body' => $body];
    }

    // =========================================================================
    // Small helpers for the templates
    // =========================================================================

    private static function first(string $name): string
    {
        $f = trim(explode(' ', trim($name))[0] ?? '');
        return $f !== '' ? $f : $name;
    }

    private static function h(string $s): string
    {
        return EmailTheme::h($s);
    }

    private static function app(): string
    {
        return defined('APP_URL') ? rtrim((string)APP_URL, '/') : 'https://studyvibe.edu';
    }

    /** "mardi 20 octobre 2026" / "Tuesday, 20 October 2026" */
    private static function longDate(int $ts, string $lang): string
    {
        $dFr = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $mFr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        if ($lang === 'en') {
            return date('l, j F Y', $ts);
        }
        return $dFr[(int)date('w', $ts)] . ' ' . (int)date('j', $ts) . ' ' . $mFr[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
    }

    private static function hello(string $name, string $lang = 'fr'): string
    {
        return EmailTheme::p(($lang === 'en' ? 'Hello ' : 'Bonjour ') . '<strong>' . self::h(self::first($name)) . '</strong>,');
    }

    // =========================================================================
    // SECTION 1: ACCOUNT EMAILS
    // =========================================================================

    public static function welcome(string $to, string $name, string $roleLabel = 'apprenant'): bool
    {
        $keys = '';
        // Students get the enrolment keys of the open courses so they can start at once
        if ($roleLabel === 'apprenant') {
            try {
                require_once __DIR__ . '/Database.php';
                $courses = Database::getInstance()->query("
                    SELECT title, enrollment_key FROM courses
                    WHERE is_published = 1 AND enrollment_key IS NOT NULL AND enrollment_key != '' ORDER BY title ASC
                ")->fetchAll(PDO::FETCH_ASSOC);
                if ($courses) {
                    $rows = array_map(fn($c) => [(string)$c['title'], EmailTheme::code((string)$c['enrollment_key'])], $courses);
                    $keys = EmailTheme::sectionTitle('Vos clés d’inscription')
                          . EmailTheme::p('Saisissez une clé sur votre tableau de bord pour ouvrir le cours correspondant.')
                          . EmailTheme::facts($rows);
                }
            } catch (Throwable $e) {
                // the welcome message does not depend on the database
            }
        }
        $isTeacher = $roleLabel === 'enseignant';
        $html = EmailTheme::layout(
            EmailTheme::badge('Bienvenue')
            . EmailTheme::title('Votre compte est prêt')
            . self::hello($name)
            . EmailTheme::lede('Nous sommes ravis de vous accueillir sur StudyVibe. Votre compte <strong>' . self::h($roleLabel) . '</strong> a été créé.')
            . EmailTheme::p($isTeacher
                ? 'Dès que le promoteur aura validé votre compte, vous pourrez créer vos cours, programmer des évaluations en direct et suivre les résultats de vos étudiants.'
                : 'Ouvrez votre espace pour découvrir vos cours, passer vos évaluations et retrouver vos certificats au même endroit.')
            . $keys
            . EmailTheme::button(self::app(), 'Ouvrir mon espace')
            . EmailTheme::signature('fr'),
            ['preheader' => 'Votre compte StudyVibe a été créé.', 'lang' => 'fr']
        );
        return self::send($to, 'Bienvenue sur StudyVibe', $html);
    }

    public static function emailVerification(string $to, string $name, string $token): bool
    {
        $url = self::app() . '/verify-email.php?token=' . urlencode($token);
        $html = EmailTheme::layout(
            EmailTheme::badge('Une dernière étape')
            . EmailTheme::title('Confirmez votre adresse e-mail')
            . self::hello($name)
            . EmailTheme::lede('Confirmez que cette adresse est bien la vôtre et votre compte StudyVibe sera prêt.')
            . EmailTheme::button($url, 'Confirmer mon adresse')
            . EmailTheme::callout('Ce lien reste valable <strong>48 heures</strong>. Passé ce délai, vous pourrez en demander un nouveau depuis la page de connexion.', 'ink')
            . EmailTheme::linkFallback($url)
            . EmailTheme::small('Vous n’avez pas créé de compte StudyVibe ? Ignorez simplement ce message, rien ne sera activé.'),
            ['preheader' => 'Confirmez votre adresse e-mail pour activer votre compte StudyVibe.', 'lang' => 'fr']
        );
        return self::send($to, 'Confirmez votre adresse e-mail — StudyVibe', $html);
    }

    public static function passwordReset(string $to, string $name, string $token): bool
    {
        $url = self::app() . '/reset-password.php?token=' . urlencode($token);
        $html = EmailTheme::layout(
            EmailTheme::badge('Sécurité', 'ink')
            . EmailTheme::title('Choisissez un nouveau mot de passe')
            . self::hello($name)
            . EmailTheme::lede('Nous avons reçu une demande de réinitialisation du mot de passe de votre compte. Si c’est bien vous, continuez ci-dessous.')
            . EmailTheme::button($url, 'Choisir un nouveau mot de passe')
            . EmailTheme::callout('Ce lien reste valable <strong>2 heures</strong> et ne peut servir qu’une seule fois.', 'ink')
            . EmailTheme::linkFallback($url)
            . EmailTheme::small('Vous n’êtes pas à l’origine de cette demande ? Ignorez ce message : votre mot de passe actuel reste inchangé.'),
            ['preheader' => 'Un lien pour choisir un nouveau mot de passe StudyVibe.', 'lang' => 'fr']
        );
        return self::send($to, 'Réinitialisation du mot de passe — StudyVibe', $html);
    }

    /**
     * Security notice after a change to the account's protection (two-factor turned on or off, new recovery codes).
     */
    public static function securityNotice(string $to, string $name, string $what, string $lang = 'fr'): bool
    {
        $en = $lang === 'en';
        $html = EmailTheme::layout(
            EmailTheme::badge($en ? 'Security notice' : 'Alerte de sécurité', 'red')
            . EmailTheme::title($en ? 'A change was made to your account' : 'Un changement a été fait sur votre compte')
            . self::hello($name, $lang)
            . EmailTheme::callout(self::h($what), 'ochre')
            . EmailTheme::p($en
                ? 'If this was you, there is nothing more to do. If it was not, change your password right away and contact your administrator.'
                : 'Si c’est bien vous, il n’y a rien à faire. Sinon, changez immédiatement votre mot de passe et contactez votre administrateur.')
            . EmailTheme::button(self::app() . '/account/security.php', $en ? 'Review my security settings' : 'Vérifier ma sécurité'),
            ['preheader' => $what, 'lang' => $lang]
        );
        return self::send($to, ($en ? 'Security notice' : 'Alerte de sécurité') . ' — StudyVibe', $html);
    }

    public static function certification(string $to, string $name, string $courseTitle, string $certCode): bool
    {
        $verifyUrl = self::app() . '/verify.php?code=' . urlencode($certCode);
        $certUrl   = self::app() . '/certificate.php?code=' . urlencode($certCode);
        $html = EmailTheme::layout(
            EmailTheme::badge('Certificat', 'pine')
            . EmailTheme::title('Félicitations !')
            . self::hello($name)
            . EmailTheme::lede('Vous avez validé le cours <strong>' . self::h($courseTitle) . '</strong>.')
            . EmailTheme::facts([['Cours', self::h($courseTitle)], ['Code du certificat', EmailTheme::code($certCode)]])
            . EmailTheme::button($certUrl, 'Voir mon certificat')
            . EmailTheme::button($verifyUrl, 'Vérifier en ligne', 'ghost')
            . EmailTheme::small('N’importe qui peut confirmer l’authenticité de ce certificat avec son code, sur la page de vérification.')
            . EmailTheme::signature('fr'),
            ['preheader' => 'Votre certificat StudyVibe est disponible : ' . $courseTitle, 'lang' => 'fr']
        );
        return self::send($to, 'Votre certificat StudyVibe — ' . $courseTitle, $html);
    }

    // =========================================================================
    // SECTION 2: RESULTS
    // =========================================================================

    /** A question with its four options, the right one and the student's pick marked in soft colours. */
    private static function qaBlock(int $num, string $question, array $qa): string
    {
        $isWritten = ($qa['question_type'] ?? 'mcq') === 'written';
        $ok = !empty($qa['answered_correctly']);
        $status = $ok ? ['#E8F2EA', '#24402F', 'Correct (+1)'] : ['#FCE9E6', '#9C2B1F', 'Incorrect (0)'];
        $out = "<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin:0 0 14px 0;'><tr><td style=\"background-color:#FAF8F4;border:1px solid #EFEAE0;border-radius:18px;padding:16px 18px;font-family:'Hanken Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;\">"
             . "<table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'><tr>"
             . "<td style='font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#7A7467;'>Question {$num}</td>"
             . "<td align='right'><span style=\"display:inline-block;background-color:{$status[0]};color:{$status[1]};border-radius:999px;padding:3px 10px;font-size:12px;font-weight:700;\">{$status[2]}</span></td></tr></table>"
             . "<div style='font-size:15px;font-weight:600;color:#2B2722;line-height:1.5;margin:8px 0 10px 0;'>" . self::h($question) . '</div>';
        if ($isWritten) {
            $out .= "<div style='font-size:14px;color:#4E483F;margin:2px 0;'>Votre réponse : <strong>" . self::h((string)($qa['selected_option'] ?? '') !== '' ? (string)$qa['selected_option'] : 'aucune') . '</strong></div>'
                  . "<div style='font-size:14px;color:#24402F;margin:2px 0;'>Réponse correcte : <strong>" . self::h((string)($qa['correct_option'] ?? '')) . '</strong></div>';
        } else {
            foreach (['A', 'B', 'C', 'D'] as $opt) {
                $text = trim((string)($qa['option_' . strtolower($opt)] ?? ''));
                if ($text === '') {
                    continue;
                }
                $right = $opt === ($qa['correct_option'] ?? '');
                $picked = $opt === ($qa['selected_option'] ?? '');
                [$bg, $fg, $bd] = $right ? ['#E8F2EA', '#24402F', '#CFE3D3'] : ($picked ? ['#FCE9E6', '#9C2B1F', '#F5CFC9'] : ['#FFFFFF', '#4E483F', '#EFEAE0']);
                $tag = $picked && $right ? ' · votre réponse, correcte' : ($picked ? ' · votre réponse' : ($right ? ' · réponse correcte' : ''));
                $out .= "<div style=\"background-color:{$bg};color:{$fg};border:1px solid {$bd};border-radius:12px;padding:9px 13px;margin:0 0 6px 0;font-size:14px;line-height:1.45;\"><strong>{$opt}.</strong> " . self::h($text)
                      . ($tag !== '' ? "<span style='font-size:12px;font-weight:700;'>" . self::h($tag) . '</span>' : '') . '</div>';
            }
        }
        if (!empty($qa['explanation'])) {
            $out .= "<div style='font-size:13px;color:#7A7467;line-height:1.55;margin-top:8px;'><em>Explication.</em> " . self::h((string)$qa['explanation']) . '</div>';
        }
        return $out . '</td></tr></table>';
    }

    public static function quizResults(string $to, string $studentName, string $lessonTitle, string $courseTitle, int $score, array $qas): bool
    {
        $blocks = '';
        foreach ($qas as $i => $qa) {
            $blocks .= self::qaBlock($i + 1, (string)($qa['question_text'] ?? ''), $qa);
        }
        $html = EmailTheme::layout(
            EmailTheme::badge('Quiz de leçon')
            . EmailTheme::title('Voici vos réponses au quiz')
            . self::hello($studentName)
            . EmailTheme::lede('Vous avez terminé le quiz de la leçon <strong>' . self::h($lessonTitle) . '</strong> (cours <em>' . self::h($courseTitle) . '</em>).')
            . EmailTheme::stat('Votre note', $score . ' %', 'Vos réponses sont enregistrées définitivement.', $score >= 50 ? 'pine' : 'clay')
            . EmailTheme::sectionTitle('Le détail, question par question')
            . $blocks
            . EmailTheme::signature('fr'),
            ['preheader' => "Votre note au quiz « {$lessonTitle} » : {$score} %", 'lang' => 'fr']
        );
        return self::send($to, "Résultats du quiz — {$lessonTitle}", $html);
    }

    public static function sendLiveEvalResults(string $to, string $studentName, string $sessionTitle, int $correctCount, int $totalQuestions, array $qas, int $registrationId = 0): bool
    {
        $percent = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 1) : 0.0;
        $passed = $percent >= 50;

        $button = '';
        if ($registrationId > 0 && defined('APP_SECRET')) {
            // Signed link so nobody can open somebody else's report by changing the number
            $token = hash_hmac('sha256', (string)$registrationId, APP_SECRET);
            $url = self::app() . "/student/evaluation-results.php?registration_id={$registrationId}&token={$token}";
            $button = EmailTheme::button($url, 'Voir la correction détaillée')
                    . EmailTheme::small('Ce lien personnel vous montre la justification de chaque question.');
        }
        $html = EmailTheme::layout(
            EmailTheme::badge('Évaluation en direct', $passed ? 'pine' : 'clay')
            . EmailTheme::title('Vos résultats sont arrivés')
            . self::hello($studentName)
            . EmailTheme::lede('Merci d’avoir participé à <strong>' . self::h($sessionTitle) . '</strong>. Voici votre résultat.')
            . EmailTheme::stat('Votre note', "{$correctCount} / {$totalQuestions}", ($passed ? 'Validé' : 'Non validé') . ' · ' . str_replace('.', ',', (string)$percent) . ' %', $passed ? 'pine' : 'clay')
            . $button
            . EmailTheme::signature('fr'),
            ['preheader' => "Votre note : {$correctCount} / {$totalQuestions} à « {$sessionTitle} »", 'lang' => 'fr']
        );
        return self::send($to, "Vos résultats — {$sessionTitle}", $html);
    }

    // =========================================================================
    // SECTION 3: LIVE EVALUATIONS (announcement, cancelled result)
    // =========================================================================

    /**
     * Tells a student that a live evaluation is scheduled: when, how long, how to take part, and the link.
     *
     * @param array{title:string,course_title:string,start_time:string,is_async?:int,async_deadline?:?string,n_questions?:int,minutes?:int,integrity_watch?:int,teacher_name?:string} $s
     */
    public static function liveEvalScheduled(string $to, string $name, array $s, string $link, string $lang = 'fr', bool $rescheduled = false): bool
    {
        $en = $lang === 'en';
        $async = !empty($s['is_async']);
        $title = (string)$s['title'];
        $course = (string)$s['course_title'];
        $n = (int)($s['n_questions'] ?? 0);
        $min = (int)($s['minutes'] ?? 0);
        $ts = strtotime((string)$s['start_time']);

        $rows = [[$en ? 'Course' : 'Cours', self::h($course)], [$en ? 'Evaluation' : 'Évaluation', self::h($title)]];
        if ($async) {
            $deadline = !empty($s['async_deadline']) ? strtotime((string)$s['async_deadline']) : null;
            $rows[] = [$en ? 'Open until' : 'Ouvert jusqu’au', $deadline ? self::h(self::longDate($deadline, $lang) . ($en ? ' at ' : ' à ') . date($en ? 'H:i' : 'H \h i', $deadline)) : ($en ? 'See your teacher' : 'Voir votre enseignant')];
        } else {
            $rows[] = ['Date', self::h(self::longDate($ts, $lang))];
            $rows[] = [$en ? 'Start' : 'Début', self::h(date($en ? 'H:i' : 'H \h i', $ts)) . ($en ? ' (everyone starts together)' : ' (tout le monde démarre ensemble)')];
        }
        if ($min > 0) {
            $rows[] = [$en ? 'Duration' : 'Durée', self::h($min . ' min')];
        }
        if ($n > 0) {
            $rows[] = ['Questions', (string)$n];
        }

        $steps = $async
            ? ($en ? [
                ['Open the link', 'Use the button below, on a computer or a phone.'],
                ['Sign in', 'Use your StudyVibe account, or enter your name and email.'],
                ['Start when you are ready', 'Each question has its own timer. Once you answer, you move on.'],
                ['Finish before the deadline', 'Your result arrives by email.'],
            ] : [
                ['Ouvrez le lien', 'Avec le bouton ci-dessous, sur ordinateur ou téléphone.'],
                ['Connectez-vous', 'Avec votre compte StudyVibe, ou saisissez votre nom et votre e-mail.'],
                ['Commencez quand vous êtes prêt', 'Chaque question a son propre chrono. Une fois répondu, vous passez à la suivante.'],
                ['Terminez avant la date limite', 'Votre résultat vous est envoyé par e-mail.'],
            ])
            : ($en ? [
                ['Be ready 10 minutes early', 'Charged device, stable connection, a quiet place.'],
                ['Open the link', 'Use the button below. Sign in with your StudyVibe account, or enter your name and email.'],
                ['Wait in the lobby', 'You will see how many classmates are there and a countdown to the start.'],
                ['Everyone starts on the same second', 'Questions follow one another on a timer. Answer before the time runs out, you cannot go back.'],
                ['Stay on the page', !empty($s['integrity_watch']) ? 'Leaving the exam tab is recorded and your teacher can see it.' : 'Do not close the page until the end screen.'],
                ['Get your result by email', 'It arrives right after the exam.'],
            ] : [
                ['Soyez prêt 10 minutes avant', 'Appareil chargé, connexion stable, un endroit calme.'],
                ['Ouvrez le lien', 'Avec le bouton ci-dessous. Connectez-vous avec votre compte StudyVibe, ou saisissez votre nom et votre e-mail.'],
                ['Patientez dans la salle d’attente', 'Vous verrez combien de camarades sont présents et un compte à rebours jusqu’au début.'],
                ['Tout le monde démarre à la même seconde', 'Les questions s’enchaînent avec un chrono. Répondez avant la fin du temps, on ne revient pas en arrière.'],
                ['Restez sur la page', !empty($s['integrity_watch']) ? 'Quitter l’onglet de l’examen est enregistré et visible par votre enseignant.' : 'Ne fermez pas la page avant l’écran de fin.'],
                ['Recevez votre résultat par e-mail', 'Il arrive juste après l’examen.'],
            ]);

        $headline = $rescheduled
            ? ($en ? 'The evaluation has moved' : 'L’évaluation a changé de créneau')
            : ($async ? ($en ? 'An evaluation is open for you' : 'Une évaluation est ouverte pour vous') : ($en ? 'A live evaluation is scheduled' : 'Une évaluation en direct est programmée'));
        $html = EmailTheme::layout(
            EmailTheme::badge($rescheduled ? ($en ? 'New schedule' : 'Nouveau créneau') : ($en ? 'Evaluation' : 'Évaluation'), $rescheduled ? 'ochre' : 'clay')
            . EmailTheme::title($headline)
            . self::hello($name, $lang)
            . EmailTheme::lede($en
                ? 'Your teacher has ' . ($rescheduled ? 'updated' : 'scheduled') . ' an evaluation for the course <strong>' . self::h($course) . '</strong>. Here is everything you need.'
                : 'Votre enseignant a ' . ($rescheduled ? 'modifié' : 'programmé') . ' une évaluation pour le cours <strong>' . self::h($course) . '</strong>. Voici tout ce qu’il faut savoir.')
            . EmailTheme::facts($rows)
            . EmailTheme::sectionTitle($en ? 'How it works' : 'Comment ça se passe')
            . EmailTheme::steps($steps)
            . EmailTheme::button($link, $async ? ($en ? 'Open the evaluation' : 'Ouvrir l’évaluation') : ($en ? 'Join the waiting room' : 'Rejoindre la salle d’attente'))
            . EmailTheme::linkFallback($link, $lang)
            . EmailTheme::small($en ? 'Good luck!' : 'Bonne chance !'),
            ['preheader' => $async ? $title : ($title . ' · ' . self::longDate($ts, $lang) . ' ' . date($en ? 'H:i' : 'H \h i', $ts)), 'lang' => $lang]
        );
        return self::send($to, ($rescheduled ? ($en ? 'New time: ' : 'Nouveau créneau : ') : ($en ? 'Evaluation scheduled: ' : 'Évaluation programmée : ')) . $title . ' — StudyVibe', $html);
    }

    /** Tells a student that their result of an evaluation was cancelled by the teacher (or restored). */
    public static function liveResultCancelled(string $to, string $name, string $sessionTitle, string $courseTitle, string $reason = '', string $teacherName = '', string $lang = 'fr', bool $restored = false): bool
    {
        $en = $lang === 'en';
        if ($restored) {
            $html = EmailTheme::layout(
                EmailTheme::badge($en ? 'Result restored' : 'Résultat rétabli', 'pine')
                . EmailTheme::title($en ? 'Your result counts again' : 'Votre résultat est rétabli')
                . self::hello($name, $lang)
                . EmailTheme::lede($en
                    ? 'Your teacher has restored your result for <strong>' . self::h($sessionTitle) . '</strong>. It counts again.'
                    : 'Votre enseignant a rétabli votre résultat pour <strong>' . self::h($sessionTitle) . '</strong>. Il est de nouveau pris en compte.')
                . EmailTheme::facts([[$en ? 'Course' : 'Cours', self::h($courseTitle)], [$en ? 'Evaluation' : 'Évaluation', self::h($sessionTitle)]])
                . EmailTheme::button(self::app() . '/student/dashboard.php', $en ? 'Open my space' : 'Ouvrir mon espace')
                . EmailTheme::signature($lang, $teacherName),
                ['preheader' => $sessionTitle, 'lang' => $lang]
            );
            return self::send($to, ($en ? 'Result restored: ' : 'Résultat rétabli : ') . $sessionTitle . ' — StudyVibe', $html);
        }
        $html = EmailTheme::layout(
            EmailTheme::badge($en ? 'Result cancelled' : 'Résultat annulé', 'red')
            . EmailTheme::title($en ? 'Your result was cancelled' : 'Votre résultat a été annulé')
            . self::hello($name, $lang)
            . EmailTheme::lede($en
                ? 'Your teacher has cancelled your result for <strong>' . self::h($sessionTitle) . '</strong>. It no longer counts for this evaluation.'
                : 'Votre enseignant a annulé votre résultat pour <strong>' . self::h($sessionTitle) . '</strong>. Il n’est plus pris en compte pour cette évaluation.')
            . EmailTheme::facts([[$en ? 'Course' : 'Cours', self::h($courseTitle)], [$en ? 'Evaluation' : 'Évaluation', self::h($sessionTitle)]])
            . ($reason !== '' ? EmailTheme::callout(self::h($reason), 'ochre', $en ? 'Reason given' : 'Motif indiqué') : '')
            . EmailTheme::p($en
                ? 'If you think this is a mistake, speak to your teacher: they can restore the result.'
                : 'Si vous pensez qu’il s’agit d’une erreur, parlez-en à votre enseignant : il peut rétablir le résultat.')
            . EmailTheme::button(self::app() . '/student/dashboard.php', $en ? 'Open my space' : 'Ouvrir mon espace', 'ghost')
            . EmailTheme::signature($lang, $teacherName),
            ['preheader' => $sessionTitle, 'lang' => $lang]
        );
        return self::send($to, ($en ? 'Result cancelled: ' : 'Résultat annulé : ') . $sessionTitle . ' — StudyVibe', $html);
    }

    // =========================================================================
    // SECTION 4: ADMINISTRATION AND TEACHING
    // =========================================================================

    public static function courseCreatedByTeacher(string $to, string $promoterName, string $teacherName, string $courseTitle, string $moduleTitle): bool
    {
        $html = EmailTheme::layout(
            EmailTheme::badge('Nouveau cours')
            . EmailTheme::title('Un cours vient d’être créé')
            . self::hello($promoterName)
            . EmailTheme::lede('L’enseignant <strong>' . self::h($teacherName) . '</strong> vient de créer un cours sur StudyVibe.')
            . EmailTheme::facts([['Cours', self::h($courseTitle)], ['Module', self::h($moduleTitle)], ['Enseignant', self::h($teacherName)]])
            . EmailTheme::p('Le cours apparaît dans votre catalogue. Vous pouvez réassigner l’enseignant à tout moment.')
            . EmailTheme::button(self::app() . '/promoter/dashboard.php', 'Ouvrir la console'),
            ['preheader' => $courseTitle . ' · ' . $teacherName, 'lang' => 'fr']
        );
        return self::send($to, 'Nouveau cours : ' . $courseTitle . ' — StudyVibe', $html);
    }

    public static function courseAssignmentChanged(string $to, string $teacherName, string $courseTitle, string $actionLabel): bool
    {
        $html = EmailTheme::layout(
            EmailTheme::badge('Assignation', 'ink')
            . EmailTheme::title('Votre assignation a changé')
            . self::hello($teacherName)
            . EmailTheme::lede('Votre assignation au cours <strong>' . self::h($courseTitle) . '</strong> a été modifiée par le promoteur.')
            . EmailTheme::callout(self::h($actionLabel), 'ochre')
            . EmailTheme::button(self::app() . '/teacher/dashboard.php', 'Ouvrir mon espace'),
            ['preheader' => $courseTitle, 'lang' => 'fr']
        );
        return self::send($to, 'Assignation de cours modifiée — StudyVibe', $html);
    }

    public static function teacherApproved(string $to, string $name): bool
    {
        $html = EmailTheme::layout(
            EmailTheme::badge('Compte validé', 'pine')
            . EmailTheme::title('Bonne nouvelle : votre compte est validé')
            . self::hello($name)
            . EmailTheme::lede('Le promoteur a validé votre compte enseignant. Vous pouvez dès maintenant vous connecter.')
            . EmailTheme::steps([
                ['Créez un cours', 'Chapitres, leçons, vidéos, PDF.'],
                ['Programmez une évaluation en direct', 'Importez vos questions, choisissez l’horaire.'],
                ['Suivez les résultats', 'Notes, intégrité, exports.'],
            ])
            . EmailTheme::button(self::app(), 'Me connecter')
            . EmailTheme::signature('fr'),
            ['preheader' => 'Votre compte enseignant StudyVibe est validé.', 'lang' => 'fr']
        );
        return self::send($to, 'Votre compte enseignant est validé — StudyVibe', $html);
    }

    public static function directMessage(string $to, string $studentName, string $subject, string $messageText): bool
    {
        $html = EmailTheme::layout(
            EmailTheme::badge('Message', 'ink')
            . EmailTheme::title('Un message de l’administration')
            . self::hello($studentName)
            . EmailTheme::p('Le promoteur de la plateforme vous a écrit :')
            . EmailTheme::quote($messageText)
            . EmailTheme::small('Vous pouvez répondre à ce message, ou vous connecter à StudyVibe pour suivre vos cours.')
            . EmailTheme::button(self::app(), 'Ouvrir StudyVibe', 'ghost'),
            ['preheader' => mb_strimwidth($messageText, 0, 90, '…'), 'lang' => 'fr']
        );
        return self::send($to, $subject, $html);
    }

    public static function newsletter(string $to, string $name, string $subject, string $htmlContent, string $unsubscribeUrl): bool
    {
        $html = EmailTheme::layout(
            self::hello($name)
            . "<div style=\"font-size:15px;line-height:1.7;color:#4E483F;\">" . $htmlContent . '</div>',
            ['preheader' => $subject, 'lang' => 'fr', 'unsubscribe' => $unsubscribeUrl,
             'footer' => 'Vous recevez cette lettre parce que vous êtes inscrit(e) à la newsletter StudyVibe.']
        );
        return self::send($to, $subject, $html);
    }

    public static function sendEnrollmentKeys(string $to, string $name, array $courses): bool
    {
        $rows = array_map(fn($c) => [(string)$c['title'], EmailTheme::code((string)$c['enrollment_key'])], $courses);
        $html = EmailTheme::layout(
            EmailTheme::badge('Inscription')
            . EmailTheme::title('Vos clés d’inscription')
            . self::hello($name)
            . EmailTheme::lede('Voici la liste à jour des clés d’inscription actives sur StudyVibe.')
            . EmailTheme::p('Saisissez une clé sur votre tableau de bord étudiant pour vous inscrire tout de suite au cours correspondant.')
            . ($rows ? EmailTheme::facts($rows) : EmailTheme::callout('Aucun cours à clé n’est ouvert pour le moment.', 'ink'))
            . EmailTheme::button(self::app(), 'Ouvrir mon espace')
            . EmailTheme::small('Une difficulté pour vous inscrire ? Répondez simplement à ce message.')
            . EmailTheme::signature('fr', 'L’administration StudyVibe'),
            ['preheader' => 'Les clés d’inscription actives de vos cours.', 'lang' => 'fr']
        );
        return self::send($to, 'Vos clés d’inscription — StudyVibe', $html);
    }

    public static function sendLessonContentUpdated(string $to, string $studentName, string $courseTitle, string $lessonTitle, int $courseId, bool $isNewLesson = false): bool
    {
        $url = self::app() . '/student/dashboard.php?course_id=' . $courseId;
        $html = EmailTheme::layout(
            EmailTheme::badge($isNewLesson ? 'Nouvelle leçon' : 'Mise à jour', $isNewLesson ? 'pine' : 'clay')
            . EmailTheme::title($isNewLesson ? 'Une nouvelle leçon est disponible' : 'Une leçon a été mise à jour')
            . self::hello($studentName)
            . EmailTheme::lede($isNewLesson
                ? 'La leçon <strong>' . self::h($lessonTitle) . '</strong> vient d’être ajoutée au cours <strong>' . self::h($courseTitle) . '</strong>.'
                : 'L’enseignant a mis à jour la leçon <strong>' . self::h($lessonTitle) . '</strong> du cours <strong>' . self::h($courseTitle) . '</strong>.')
            . EmailTheme::callout('Pour vous laisser le temps d’assimiler les nouveautés, la progression de cette leçon a été remise à zéro. Votre avancement global dans le cours en tient compte.', 'ochre', 'À savoir')
            . EmailTheme::button($url, 'Découvrir la leçon')
            . EmailTheme::small('Une question ? Échangez directement avec l’enseignant, depuis la leçon.')
            . EmailTheme::signature('fr', 'L’équipe pédagogique StudyVibe'),
            ['preheader' => $lessonTitle . ' · ' . $courseTitle, 'lang' => 'fr']
        );
        return self::send($to, ($isNewLesson ? 'Nouvelle leçon : ' : 'Leçon mise à jour : ') . $courseTitle . ' — StudyVibe', $html);
    }

    // =========================================================================
    // SECTION 5: SOCKET CONNECTION AND DATA STREAMING
    // =========================================================================

    /**
     * Low-level SMTP client. Opens direct streams to the server and runs the transaction.
     */
    private static function sendSmtp(string $to, string $subject, string $html, string $from, string $fromName): bool
    {
        $host = SMTP_HOST;
        $port = (int)(defined('SMTP_PORT') ? SMTP_PORT : 587);
        $user = SMTP_USER;
        $pass = defined('SMTP_PASS') ? SMTP_PASS : '';

        // Clean up Google App Password whitespaces
        if (str_contains($host, 'gmail.com') && strlen(str_replace(' ', '', $pass)) === 16) {
            $pass = str_replace(' ', '', $pass);
        }

        // Disable certificate verification peer validation if server keys are self-signed
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $remoteSocketAddress = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remoteSocketAddress, $errno, $errstr, 3, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) {
            self::$lastError = "Connexion impossible à {$remoteSocketAddress} : [{$errno}] {$errstr}";
            return false;
        }

        stream_set_timeout($socket, 5);

        // Socket stream readers
        $read = static function () use ($socket): string {
            $data = '';
            while (!feof($socket) && ($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                $info = stream_get_meta_data($socket);
                if (!empty($info['timed_out'])) break;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $data;
        };
        
        // Socket stream writers
        $write = static function (string $cmd) use ($socket): void {
            fwrite($socket, $cmd . "\r\n");
        };

        $read();
        $write("EHLO studyvibe.local");
        $read();

        // Enforce STARTTLS if not running on port 465
        if ($port !== 465) {
            $write('STARTTLS');
            $tlsResponse = $read();
            if (!str_starts_with($tlsResponse, '220')) {
                self::$lastError = "STARTTLS rejeté par le serveur : " . trim($tlsResponse);
                fclose($socket);
                return false;
            }
            
            // Upgrade connection context to TLS Client
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                self::$lastError = "Échec de l'activation du chiffrement TLS (Handshake)";
                fclose($socket);
                return false;
            }
            
            $write("EHLO studyvibe.local");
            $read();
        }

        // Authentication login exchange
        $write('AUTH LOGIN');
        $authLoginRes = $read();
        if (!str_starts_with($authLoginRes, '334')) {
            self::$lastError = "AUTH LOGIN non supporté ou rejeté : " . trim($authLoginRes);
            fclose($socket);
            return false;
        }

        // Send base64 username
        $write(base64_encode($user));
        $userRes = $read();
        if (!str_starts_with($userRes, '334')) {
            self::$lastError = "SMTP Username base64 rejeté : " . trim($userRes);
            fclose($socket);
            return false;
        }

        // Send base64 password
        $write(base64_encode($pass));
        $authResponse = $read();
        if (!str_starts_with($authResponse, '235')) {
            self::$lastError = "Authentification SMTP échouée : " . trim($authResponse);
            fclose($socket);
            return false;
        }

        // MAIL FROM transaction
        $write("MAIL FROM:<{$from}>");
        $mailFromRes = $read();
        if (!str_starts_with($mailFromRes, '250')) {
            self::$lastError = "MAIL FROM rejeté : " . trim($mailFromRes);
            fclose($socket);
            return false;
        }

        // RCPT TO transaction
        $write("RCPT TO:<{$to}>");
        $rcptRes = $read();
        if (!str_starts_with($rcptRes, '250') && !str_starts_with($rcptRes, '251')) {
            self::$lastError = "Destinataire RCPT TO rejeté ({$to}) : " . trim($rcptRes);
            fclose($socket);
            return false;
        }

        // DATA payload block init
        $write('DATA');
        $dataRes = $read();
        if (!str_starts_with($dataRes, '354')) {
            self::$lastError = "Commande DATA rejetée : " . trim($dataRes);
            fclose($socket);
            return false;
        }

        // Full MIME message: HTML + plain text, logo attached inline. Lines starting with a dot are doubled (SMTP rule).
        $mime = self::buildMime($to, $subject, $html, $from, $fromName);
        $message = rtrim(preg_replace('/^\./m', '..', $mime['headers'] . "\r\nTo: {$to}\r\nSubject: " . $mime['subject'] . "\r\n\r\n" . $mime['body']) ?? '', "\r\n") . "\r\n.";

        $write($message);
        $dataEndRes = $read();
        $ok = str_starts_with($dataEndRes, '250');
        if (!$ok) {
            self::$lastError = "Envoi du corps du message échoué : " . trim($dataEndRes);
        }

        // SMTP connection termination
        $write('QUIT');
        fclose($socket);
        return $ok;
    }
}
