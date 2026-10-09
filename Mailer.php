<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Brand.php';

/**
 * StudyVibe LMS - Native SMTP & Mail Service
 * 
 * Provides native SMTP communication via raw stream sockets (tcp/ssl) to avoid 
 * heavy Composer external dependencies (like PHPMailer). Features HTML template wraps, 
 * base64 header encoding, custom SSL/TLS handshake contexts, application/gmail app-password 
 * sanitization, and graceful fallback to the local mail() function.
 * 
 * @package    StudyVibe
 * @author     Advanced Engineering Team
 */
class Mailer
{
    /** @var string|null Tracks description of the last failed SMTP transaction error */
    private static ?string $lastError = null;

    /** @var bool Disables SMTP attempts for current request after a connection failure to avoid cumulative timeouts */
    private static bool $smtpDisabled = false;

    /**
     * Retrieves the description error log from the last failed transaction.
     * 
     * @return string|null The error message, or null if no error occurred.
     */
    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * Checks if SMTP credentials are set.
     * 
     * @return bool True if host and username constants are configured.
     */
    public static function isConfigured(): bool
    {
        return defined('SMTP_HOST') && SMTP_HOST !== '' && defined('SMTP_USER') && SMTP_USER !== '';
    }

    /**
     * Sends an HTML email, routing through SMTP if configured, or falling back to PHP mail().
     * 
     * @param string $to       Recipient email address.
     * @param string $subject  Email subject.
     * @param string $htmlBody The HTML markup template body.
     * @return bool True if dispatch succeeded, false otherwise.
     */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        self::$lastError = null;
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
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$from}>\r\n";

        // Subject header base64 encoded to prevent text representation breaks
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
        if (!$ok) {
            self::$lastError = 'mail() PHP indisponible — configurez SMTP_HOST dans .env';
        }
        return $ok;
    }

    // =========================================================================
    // SECTION 1: CORE TRANSACTIONAL ACCOUNT TEMPLATES
    // =========================================================================

    /**
     * Sends a welcome email containing instructions and course registration keys if applicable.
     * 
     * @param string $to        Recipient email.
     * @param string $name      Recipient name.
     * @param string $roleLabel Description of user account role ('apprenant' | 'enseignant').
     * @return bool True if sent successfully.
     */
    /**
     * Security notice after a change to the account's protection (two-factor turned on or off, new recovery codes).
     * Tells the owner, so a change they did not make does not go unnoticed.
     */
    public static function securityNotice(string $to, string $name, string $what, string $lang = 'fr'): bool
    {
        $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $w = htmlspecialchars($what, ENT_QUOTES, 'UTF-8');
        if ($lang === 'en') {
            $subject = 'Security notice — StudyVibe';
            $body = "<p>Hello {$n},</p><p>{$w}</p><p>If this was you, nothing more to do. If it was not, change your password right away and contact your administrator.</p>";
        } else {
            $subject = 'Alerte de sécurité — StudyVibe';
            $body = "<p>Bonjour {$n},</p><p>{$w}</p><p>Si c’est bien vous, il n’y a rien à faire. Sinon, changez immédiatement votre mot de passe et contactez votre administrateur.</p>";
        }
        return self::send($to, $subject, self::wrap($body, $subject));
    }

    public static function welcome(string $to, string $name, string $roleLabel = 'apprenant'): bool
    {
        $appUrl = APP_URL;
        $coursesInfo = '';
        
        // Fetch active keys to allow students to enroll instantly
        if ($roleLabel === 'apprenant') {
            try {
                require_once __DIR__ . '/Database.php';
                $pdo = Database::getInstance();
                $stmt = $pdo->query("
                    SELECT title, enrollment_key 
                    FROM courses 
                    WHERE is_published = 1 AND enrollment_key IS NOT NULL AND enrollment_key != '' 
                    ORDER BY title ASC
                ");
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($courses)) {
                    $coursesInfo .= "<div style='background-color:#FBF8F2; border: 1px solid #DDD5C3; padding: 20px; margin: 24px 0; border-radius: 4px;'>";
                    $coursesInfo .= "<h4 style='margin-top:0; margin-bottom:12px; color:#B5482A; font-family:Georgia,serif; font-size:15px; font-weight:normal;'>Clés d'inscription de vos cours :</h4>";
                    $coursesInfo .= "<p style='font-size:12px; color:#555; margin-bottom:12px;'>Copiez ces clés et utilisez-les sur votre tableau de bord pour déverrouiller vos cours instantanément :</p>";
                    $coursesInfo .= "<ul style='margin:0; padding-left:20px; line-height:1.6; font-size:13px; color:#111;'>";
                    foreach ($courses as $c) {
                        $coursesInfo .= "<li style='margin-bottom:6px;'><strong>" . htmlspecialchars($c['title']) . "</strong> : <code style='background:#DDD5C3; padding:2px 6px; border-radius:3px; font-weight:bold; font-family:monospace;'>" . htmlspecialchars($c['enrollment_key']) . "</code></li>";
                    }
                    $coursesInfo .= "</ul></div>";
                }
            } catch (Exception $e) {
                // Fallback silently if database is transiently unavailable
            }
        }

        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300;color:#B5482A;margin-top:0;'>Bienvenue sur StudyVibe !</h2>
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>Nous sommes ravis de vous accueillir au sein de notre communauté d'apprentissage. Votre compte de type <strong>" . htmlspecialchars($roleLabel) . "</strong> a été créé avec succès.</p>
            <p>Notre mission est de vous offrir une expérience d'apprentissage fluide, intuitive et enrichissante. Pour commencer dès aujourd'hui, accédez à votre espace pour découvrir tous vos cours.</p>
            
            {$coursesInfo}

            <p style='margin-top:24px;'><a href='{$appUrl}' style='display:inline-block;padding:12px 24px;background:#B5482A;color:#fff;text-decoration:none;font-size:13px;font-weight:600;border-radius:4px;'>Accéder à mon espace StudyVibe</a></p>
            <p style='font-size:13px;color:#555;margin-top:24px;'>Si vous avez des questions ou si vous avez besoin d'aide pour vos premiers pas, notre équipe est à votre entière disposition. N'hésitez pas à répondre directement à ce message.</p>
            <p style='font-size:13px;color:#111;margin-top:24px;'>Chaleureusement,<br><strong>L'équipe StudyVibe</strong></p>
        ");
        
        return self::send($to, 'Bienvenue sur StudyVibe !', $body);
    }

    /**
     * Sends an email verification link.
     * 
     * @param string $to    Recipient email.
     * @param string $name  Recipient name.
     * @param string $token Verification token.
     * @return bool True if sent.
     */
    public static function emailVerification(string $to, string $name, string $token): bool
    {
        $url   = APP_URL . '/verify-email.php?token=' . urlencode($token);
        $first = trim(explode(' ', trim($name))[0] ?? '');
        $body  = self::wrap(
            self::heading('Confirmez votre adresse email')
            . self::p('Bonjour <strong>' . htmlspecialchars($first !== '' ? $first : $name) . '</strong>,')
            . self::p('Bienvenue sur StudyVibe. Une dernière étape et votre compte est prêt : confirmez que cette adresse est bien la vôtre.')
            . self::button($url, 'Vérifier mon email')
            . self::note('Ce lien reste valable <strong>48 heures</strong>. Passé ce délai, vous pourrez en demander un nouveau depuis la page de connexion.')
            . self::p("Le bouton ne s'affiche pas ? Copiez ce lien dans votre navigateur :", 'font-size:12px;color:#6F695C;margin:18px 0 4px 0;')
            . "<p style='margin:0;font-size:12px;word-break:break-all;'><a href='" . htmlspecialchars($url, ENT_QUOTES) . "' style='color:#B5482A;'>" . htmlspecialchars($url) . "</a></p>"
            . self::p("Vous n'avez pas créé de compte StudyVibe ? Ignorez simplement cet email, rien ne sera activé.", 'font-size:12px;color:#6F695C;margin:22px 0 0 0;'),
            'Confirmez votre adresse email pour activer votre compte StudyVibe.'
        );
        return self::send($to, 'Confirmez votre adresse email — StudyVibe', $body);
    }

    /**
     * Dispatches a secure password reset link.
     * 
     * @param string $to    Recipient email.
     * @param string $name  Recipient name.
     * @param string $token Reset security token.
     * @return bool True if sent.
     */
    public static function passwordReset(string $to, string $name, string $token): bool
    {
        $url   = APP_URL . '/reset-password.php?token=' . urlencode($token);
        $first = trim(explode(' ', trim($name))[0] ?? '');
        $body  = self::wrap(
            self::heading('Choisissez un nouveau mot de passe')
            . self::p('Bonjour <strong>' . htmlspecialchars($first !== '' ? $first : $name) . '</strong>,')
            . self::p("Nous avons reçu une demande de réinitialisation du mot de passe de votre compte. Si c'est bien vous, cliquez ci-dessous.")
            . self::button($url, 'Choisir un nouveau mot de passe')
            . self::note('Ce lien reste valable <strong>2 heures</strong> et ne peut servir qu\'une seule fois.')
            . self::p("Vous n'êtes pas à l'origine de cette demande ? Ignorez cet email : votre mot de passe actuel reste inchangé.", 'font-size:12px;color:#6F695C;margin:22px 0 0 0;'),
            'Un lien pour choisir un nouveau mot de passe StudyVibe.'
        );
        return self::send($to, 'Réinitialisation du mot de passe — StudyVibe', $body);
    }

    /**
     * Sends course validation certification details.
     * 
     * @param string $to          Recipient email.
     * @param string $name        Recipient name.
     * @param string $courseTitle Completed course title.
     * @param string $certCode    Unique certification hash code.
     * @return bool True if sent.
     */
    public static function certification(string $to, string $name, string $courseTitle, string $certCode): bool
    {
        $appUrl    = APP_URL;
        $verifyUrl = $appUrl . '/verify.php?code=' . urlencode($certCode);
        $certUrl   = $appUrl . '/certificate.php?code=' . urlencode($certCode);
        $body      = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300'>Félicitations !</h2>
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>Vous avez validé le cours <strong>" . htmlspecialchars($courseTitle) . "</strong>.</p>
            <p>Code certificat : <code style='background:#f5f5f7;padding:4px 8px'>{$certCode}</code></p>
            <p><a href='{$certUrl}' style='display:inline-block;padding:12px 24px;background:#B5482A;color:#fff;text-decoration:none;font-size:13px;margin-right:8px'>Voir mon certificat</a>
            <a href='{$verifyUrl}' style='font-size:13px;color:#B5482A'>Vérifier en ligne</a></p>
        ");
        return self::send($to, 'Votre certificat StudyVibe — ' . $courseTitle, $body);
    }

    // =========================================================================
    // SECTION 2: COMPREHENSIVE COURSE & TELE-EVALUATION MARKS REPORTING
    // =========================================================================

    /**
     * Dispatches lesson quiz response review and correct option maps.
     * 
     * @param string $to          Recipient email.
     * @param string $studentName Recipient name.
     * @param string $lessonTitle Target lesson.
     * @param string $courseTitle Parent course title.
     * @param int    $score       Achieved score percentage.
     * @param array  $qas         List of questions, options, and correctness flags.
     * @return bool True if sent successfully.
     */
    public static function quizResults(string $to, string $studentName, string $lessonTitle, string $courseTitle, int $score, array $qas): bool
    {
        $qasHtml = '';
        foreach ($qas as $idx => $qa) {
            $num = $idx + 1;
            $status = $qa['answered_correctly'] ? "<span style='color:#B5482A; font-weight:bold;'>✓ Correct (+1)</span>" : "<span style='color:#C62828; font-weight:bold;'>✕ Incorrect (0)</span>";
            
            $optionsHtml = '';
            foreach (['A', 'B', 'C', 'D'] as $opt) {
                $optText = $qa['option_' . strtolower($opt)] ?? '';
                $isCorrectOpt = $opt === $qa['correct_option'];
                $isSelectedOpt = $opt === $qa['selected_option'];
                
                $style = 'padding: 6px 12px; margin-bottom: 4px; border: 1px solid #DDD5C3; font-size: 13px;';
                if ($isCorrectOpt) {
                    $style .= 'background-color: #E2F0D9; border-color: #A2D190; color: #385723; font-weight: 500;';
                } elseif ($isSelectedOpt) {
                    $style .= 'background-color: #FCE4D6; border-color: #F8CBAD; color: #C65911;';
                } else {
                    $style .= 'background-color: #FAFAFA; color: #555555;';
                }
                
                $label = "<strong>Option {$opt} :</strong> " . htmlspecialchars($optText);
                if ($isSelectedOpt) {
                    $label .= " <span style='font-size:11px; font-style:italic;'> (Votre réponse)</span>";
                }
                if ($isCorrectOpt) {
                    $label .= " <span style='font-size:11px; font-style:italic;'> (Réponse correcte)</span>";
                }
                
                $optionsHtml .= "<div style='{$style}'>{$label}</div>";
            }
            
            $qasHtml .= "
                <div style='margin-bottom: 24px; border-bottom: 1px solid #DDD5C3; padding-bottom: 16px;'>
                    <h4 style='margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #111;'>Question {$num} : " . htmlspecialchars($qa['question_text']) . "</h4>
                    <div style='margin-bottom: 10px;'>{$optionsHtml}</div>
                    <div style='font-size: 12px;'>Statut : {$status}</div>
                </div>
            ";
        }

        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300;color:#B5482A;margin-top:0;'>Copie de vos réponses au Quiz</h2>
            <p>Bonjour <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
            <p>Vous avez complété avec succès le quiz pour la leçon : <strong>" . htmlspecialchars($lessonTitle) . "</strong> (Cours : <em>" . htmlspecialchars($courseTitle) . "</em>).</p>
            
            <div style='background-color:#F5F0E6; border: 1px solid #DDD5C3; padding: 16px; margin: 20px 0; text-align: center;'>
                <div style='font-size:12px; text-transform:uppercase; color:#888; letter-spacing:1px;'>Note Officielle</div>
                <div style='font-size:36px; font-weight:bold; color:#B5482A; margin: 5px 0;'>{$score}%</div>
                <div style='font-size:12px; color:#555;'>Vos réponses ont été enregistrées de manière définitive.</div>
            </div>
            
            <h3 style='font-family:Georgia,serif;font-weight:300;border-bottom:2px solid #B5482A;padding-bottom:6px;margin-top:30px;'>Détails de vos réponses</h3>
            {$qasHtml}
        ");
        
        return self::send($to, "StudyVibe — Résultats du Quiz : {$lessonTitle}", $body);
    }

    /**
     * Dispatches tele-evaluation session performance details and safe results display links.
     * 
     * @param string $to             Recipient email.
     * @param string $studentName     Recipient name.
     * @param string $sessionTitle   Session room name.
     * @param int    $correctCount   Count of correctly answered questions.
     * @param int    $totalQuestions Total questions counted in assessment.
     * @param array  $qas            Response list parameters.
     * @param int    $registrationId Registration identifier to build validation link.
     * @return bool True if sent successfully.
     */
    public static function sendLiveEvalResults(string $to, string $studentName, string $sessionTitle, int $correctCount, int $totalQuestions, array $qas, int $registrationId = 0): bool
    {
        $scorePercent = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 1) : 0.0;
        $statusLabel = $scorePercent >= 50 ? 'Validé (Réussite)' : 'Non validé';
        $statusColor = $scorePercent >= 50 ? '#B5482A' : '#C62828';

        $linkHtml = '';
        if ($registrationId > 0 && defined('APP_SECRET')) {
            // Generate verification HMAC token to prevent parameter tampering on evaluation result views
            $token = hash_hmac('sha256', (string)$registrationId, APP_SECRET);
            $baseUrl = defined('APP_URL') ? APP_URL : '';
            if (empty($baseUrl)) {
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $baseUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            }
            $url = rtrim($baseUrl, '/') . "/student/evaluation-results.php?registration_id={$registrationId}&token={$token}";
            $linkHtml = "
                <div style='margin: 25px 0; text-align: center;'>
                    <a href='" . htmlspecialchars($url, ENT_QUOTES) . "' style='display: inline-block; padding: 12px 24px; background-color: #B5482A; color: #FFFFFF; font-weight: bold; text-decoration: none; border-radius: 4px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.15);'>Consulter mon rapport détaillé & correction</a>
                    <div style='font-size: 11px; color: #888; margin-top: 8px;'>Ce lien sécurisé vous permet d'accéder aux justifications et explications de chaque question en ligne.</div>
                </div>
            ";
        }

        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300;color:#B5482A;margin-top:0;'>Résultats de votre Téléévaluation</h2>
            <p>Bonjour <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
            <p>Vous avez participé à la séance de téléévaluation : <strong>" . htmlspecialchars($sessionTitle) . "</strong>.</p>
            
            <div style='background-color:#F5F0E6; border: 1px solid #DDD5C3; padding: 16px; margin: 20px 0;'>
                <div style='font-size:12px; text-transform:uppercase; color:#888; letter-spacing:1px; text-align: center; margin-bottom: 10px;'>Statistiques Individuelles d'Évaluation</div>
                
                <table style='width: 100%; border-collapse: collapse; font-size: 13px;'>
                    <tr style='border-bottom: 1px solid #DDD5C3;'>
                        <td style='padding: 8px 0; color: #555;'>Score obtenu :</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #111;'>{$correctCount} / {$totalQuestions}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #DDD5C3;'>
                        <td style='padding: 8px 0; color: #555;'>Taux de réussite :</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #111;'>{$scorePercent}%</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #555;'>Statut :</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold; color: {$statusColor};'>{$statusLabel}</td>
                    </tr>
                </table>
            </div>
            
            {$linkHtml}
        ");
        
        return self::send($to, "StudyVibe — Résultats Téléévaluation : {$sessionTitle}", $body);
    }

    // =========================================================================
    // SECTION 3: ADMINISTRATIVE & PROMOTER COMMUNICATIONS
    // =========================================================================

    /**
     * Alert sent to promoters when a teacher introduces a new course.
     * 
     * @param string $to           Promoter email.
     * @param string $promoterName Promoter name.
     * @param string $teacherName  Author teacher name.
     * @param string $courseTitle  Course title.
     * @param string $moduleTitle  Target module title.
     * @return bool True if sent.
     */
    public static function courseCreatedByTeacher(
        string $to,
        string $promoterName,
        string $teacherName,
        string $courseTitle,
        string $moduleTitle
    ): bool {
        $appUrl = APP_URL;
        $body   = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300'>Nouveau cours créé</h2>
            <p>Bonjour <strong>" . htmlspecialchars($promoterName) . "</strong>,</p>
            <p>L'enseignant <strong>" . htmlspecialchars($teacherName) . "</strong> vient de créer un nouveau cours sur StudyVibe.</p>
            <ul style='line-height:1.8;padding-left:1.2rem'>
                <li><strong>Cours :</strong> " . htmlspecialchars($courseTitle) . "</li>
                <li><strong>Module :</strong> " . htmlspecialchars($moduleTitle) . "</li>
            </ul>
            <p>Le cours apparaît dans votre catalogue. Vous pouvez révoquer ou réassigner l'enseignant titulaire à tout moment.</p>
            <p><a href='{$appUrl}/promoter/dashboard.php' style='display:inline-block;padding:12px 24px;background:#B5482A;color:#fff;text-decoration:none;font-size:13px'>Ouvrir la console promoteur</a></p>
        ");
        return self::send($to, 'StudyVibe — Nouveau cours : ' . $courseTitle, $body);
    }

    /**
     * Alert sent to teachers when their course allocation is adjusted.
     * 
     * @param string $to          Teacher email.
     * @param string $teacherName Teacher name.
     * @param string $courseTitle Course title.
     * @param string $actionLabel Description of the reallocation change.
     * @return bool True if sent.
     */
    public static function courseAssignmentChanged(
        string $to,
        string $teacherName,
        string $courseTitle,
        string $actionLabel
    ): bool {
        $appUrl = APP_URL;
        $body   = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300'>Mise à jour d'assignation</h2>
            <p>Bonjour <strong>" . htmlspecialchars($teacherName) . "</strong>,</p>
            <p>Votre assignation au cours <strong>" . htmlspecialchars($courseTitle) . "</strong> a été modifiée par le promoteur : <em>" . htmlspecialchars($actionLabel) . "</em>.</p>
            <p><a href='{$appUrl}/teacher/dashboard.php' style='display:inline-block;padding:12px 24px;background:#111;color:#fff;text-decoration:none;font-size:13px'>Espace enseignant</a></p>
        ");
        return self::send($to, 'StudyVibe — Assignation cours modifiée', $body);
    }

    /**
     * Alert sent to teachers once registration approval is granted.
     * 
     * @param string $to   Teacher email.
     * @param string $name Teacher name.
     * @return bool True if sent.
     */
    public static function teacherApproved(string $to, string $name): bool
    {
        $appUrl = APP_URL;
        $body   = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300'>Compte validé !</h2>
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>Bonne nouvelle ! Votre compte d'enseignant sur StudyVibe a été validé par le promoteur.</p>
            <p>Vous pouvez dès maintenant vous connecter à votre espace, créer vos cours et gérer vos leçons.</p>
            <p><a href='{$appUrl}' style='display:inline-block;padding:12px 24px;background:#B5482A;color:#fff;text-decoration:none;font-size:13px'>Me connecter</a></p>
        ");
        return self::send($to, 'Votre compte enseignant a été validé ! — StudyVibe', $body);
    }

    /**
     * Sends a direct warning or administrative message to a student.
     * 
     * @param string $to          Recipient student email.
     * @param string $studentName Student name.
     * @param string $subject     Mail header topic.
     * @param string $messageText Message body text.
     * @return bool True if sent.
     */
    public static function directMessage(string $to, string $studentName, string $subject, string $messageText): bool
    {
        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300;color:#B5482A'>Message de l'administration StudyVibe</h2>
            <p>Bonjour <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
            <p>Le promoteur de la plateforme vous a envoyé le message direct suivant :</p>
            <div style='background:#F5F0E6;border-left:4px solid #B5482A;padding:16px;margin:20px 0;line-height:1.6;font-family:inherit;white-space:pre-wrap;'>" . htmlspecialchars($messageText) . "</div>
            <p style='font-size:12px;color:#666;'>Vous pouvez répondre à ce message ou vous connecter sur StudyVibe pour suivre vos cours.</p>
        ");
        return self::send($to, $subject, $body);
    }

    /**
     * Broadcasts promotional newsletters or systemic reports.
     * 
     * @param string $to             Recipient email.
     * @param string $name           Recipient name.
     * @param string $subject        Subject line.
     * @param string $htmlContent    Raw content markup.
     * @param string $unsubscribeUrl Unsubscribe route path.
     * @return bool True if sent.
     */
    public static function newsletter(string $to, string $name, string $subject, string $htmlContent, string $unsubscribeUrl): bool
    {
        $body = self::wrap("
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <div style='line-height:1.7'>" . $htmlContent . "</div>
            <p style='margin-top:32px;font-size:11px;color:#888'>
                <a href='" . htmlspecialchars($unsubscribeUrl) . "' style='color:#888'>Se désabonner</a>
            </p>
        ");
        return self::send($to, $subject, $body);
    }

    /**
     * Dispatches list of enrollment keys requested by student.
     * 
     * @param string $to      Recipient student email.
     * @param string $name    Student name.
     * @param array  $courses Array listing published courses and keys.
     * @return bool True if sent.
     */
    public static function sendEnrollmentKeys(string $to, string $name, array $courses): bool
    {
        $appUrl = APP_URL;
        $coursesInfo = "<div style='background-color:#FBF8F2; border: 1px solid #DDD5C3; padding: 20px; margin: 24px 0; border-radius: 4px;'>";
        $coursesInfo .= "<h4 style='margin-top:0; margin-bottom:12px; color:#B5482A; font-family:Georgia,serif; font-size:15px; font-weight:normal;'>Clés d'inscription de vos cours :</h4>";
        $coursesInfo .= "<ul style='margin:0; padding-left:20px; line-height:1.6; font-size:13px; color:#111;'>";
        foreach ($courses as $c) {
            $coursesInfo .= "<li style='margin-bottom:6px;'><strong>" . htmlspecialchars($c['title']) . "</strong> : <code style='background:#DDD5C3; padding:2px 6px; border-radius:3px; font-weight:bold; font-family:monospace;'>" . htmlspecialchars($c['enrollment_key']) . "</code></li>";
        }
        $coursesInfo .= "</ul></div>";

        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300;color:#B5482A;margin-top:0;'>Vos clés d'inscription StudyVibe</h2>
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>L'administration vient de vous transmettre la liste à jour de toutes les clés d'inscription actives sur StudyVibe.</p>
            <p>Vous pouvez copier ces clés et les saisir sur votre tableau de bord étudiant pour vous inscrire instantanément aux cours correspondants :</p>
            
            {$coursesInfo}

            <p style='margin-top:24px;'><a href='{$appUrl}' style='display:inline-block;padding:12px 24px;background:#B5482A;color:#fff;text-decoration:none;font-size:13px;font-weight:600;border-radius:4px;'>Accéder à mon espace StudyVibe</a></p>
            <p style='font-size:13px;color:#555;margin-top:24px;'>Si vous rencontrez des difficultés d'inscription, n'hésitez pas à répondre directement à ce message.</p>
            <p style='font-size:13px;color:#111;margin-top:24px;'>Cordialement,<br><strong>L'administration StudyVibe</strong></p>
        ");

        return self::send($to, "Vos clés d'inscription aux cours - StudyVibe", $body);
    }

    /**
     * Sends a notification email to students when a lesson content is updated or added.
     * 
     * @param string $to           Student email.
     * @param string $studentName  Student name.
     * @param string $courseTitle  Parent course title.
     * @param string $lessonTitle  Target lesson title.
     * @param int    $courseId     Course ID to build link.
     * @param bool   $isNewLesson  Whether it's a new lesson or an update to an existing lesson.
     * @return bool True if sent successfully.
     */
    public static function sendLessonContentUpdated(
        string $to,
        string $studentName,
        string $courseTitle,
        string $lessonTitle,
        int $courseId,
        bool $isNewLesson = false
    ): bool {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://studyvibe.edu';
        $lessonUrl = rtrim($appUrl, '/') . '/student/dashboard.php?course_id=' . $courseId;

        $badgeLabel = $isNewLesson ? 'Nouvelle leçon ajoutée' : 'Mise à jour de cours';
        $heading = $isNewLesson ? 'Une nouvelle leçon est disponible !' : 'Contenu de cours mis à jour !';

        $introText = $isNewLesson
            ? "Une nouvelle leçon intitulée <strong>" . htmlspecialchars($lessonTitle) . "</strong> a été ajoutée au cours <strong>" . htmlspecialchars($courseTitle) . "</strong>."
            : "L'enseignant a mis à jour le contenu de la leçon <strong>" . htmlspecialchars($lessonTitle) . "</strong> dans le cours <strong>" . htmlspecialchars($courseTitle) . "</strong>.";

        $infoBox = "
            <div style='background-color:#F1DDD2; border-left:4px solid #B5482A; padding:16px 20px; margin:24px 0; border-radius:0 8px 8px 0;'>
                <div style='font-weight:700; color:#96391E; font-size:13px; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;'>Information importante :</div>
                <div style='color:#4A453C; font-size:13px; line-height:1.5;'>
                    Afin de vous permettre d'assimiler les nouveaux éléments ajoutés par l'enseignant, la progression de cette leçon a été réinitialisée. Votre pourcentage d'avancement global sur ce cours a été ajusté sur votre tableau de bord.
                </div>
            </div>
        ";

        $body = self::wrap("
            <div style='margin-bottom:16px;'>
                <span style='display:inline-block; padding:4px 12px; background-color:#F1DDD2; color:#96391E; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; border-radius:20px;'>
                    {$badgeLabel}
                </span>
            </div>
            <h2 style='font-size:22px; font-weight:800; color:#1E1B16; margin:0 0 16px 0; letter-spacing:-0.5px;'>{$heading}</h2>
            <p style='margin:0 0 16px 0;'>Bonjour <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
            <p style='margin:0 0 16px 0;'>{$introText}</p>
            
            {$infoBox}

            <p style='margin:28px 0 20px 0;'>
                <a href='{$lessonUrl}' style='display:inline-block; padding:14px 28px; background-color:#B5482A; color:#FFFFFF; text-decoration:none; font-size:14px; font-weight:700; border-radius:8px; box-shadow:0 4px 12px rgba(181,72,42,0.25); text-align:center;'>
                    Accéder au cours &amp; Découvrir les nouveautés &rarr;
                </a>
            </p>

            <p style='font-size:13px; color:#6F695C; margin-top:24px;'>
                Si vous avez des questions concernant cette mise à jour, vous pouvez directement échanger avec l'enseignant ou contacter l'assistance StudyVibe.
            </p>
            <p style='font-size:13px; color:#4A453C; margin-top:24px; font-weight:600;'>
                Cordialement,<br>L'équipe pédagogique StudyVibe
            </p>
        ");

        $subject = $isNewLesson 
            ? "StudyVibe — Nouvelle leçon disponible : {$courseTitle}"
            : "StudyVibe — Mise à jour de leçon : {$courseTitle}";

        return self::send($to, $subject, $body);
    }

    // =========================================================================
    // SECTION 4: TEMPLATE STYLE WRAPPERS
    // =========================================================================

    /**
     * Encloses HTML content within standard corporate header/footer layouts.
     * 
     * @param string $content HTML block details.
     * @return string Wrapped HTML document.
     */
    private const FONT_DISPLAY = "'Fraunces', Georgia, 'Times New Roman', serif";
    private const FONT_BODY    = "'Hanken Grotesk', 'Helvetica Neue', Helvetica, Arial, sans-serif";

    /** Serif headline (Fraunces where the client loads it, Georgia elsewhere). */
    private static function heading(string $text): string
    {
        return "<h1 style=\"margin:0 0 18px 0; font-family:" . self::FONT_DISPLAY . "; font-weight:500; font-size:28px; line-height:1.2; letter-spacing:-0.02em; color:#1E1B16;\">" . htmlspecialchars($text) . "</h1>";
    }

    private static function p(string $html, string $style = ''): string
    {
        return "<p style=\"margin:0 0 14px 0; font-size:15px; line-height:1.65; color:#4A453C; {$style}\">{$html}</p>";
    }

    /** Bulletproof terracotta button (table based so Outlook keeps the colour and the rounded look). */
    private static function button(string $url, string $label): string
    {
        $u = htmlspecialchars($url, ENT_QUOTES);
        return "<table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin:22px 0 22px 0;'><tr>"
             . "<td align='center' bgcolor='#B5482A' style='border-radius:10px; background-color:#B5482A;'>"
             . "<a href='{$u}' target='_blank' style=\"display:inline-block; padding:14px 28px; font-family:" . self::FONT_BODY . "; font-size:15px; font-weight:700; line-height:1; color:#FFFFFF; text-decoration:none; border-radius:10px; border:1px solid #B5482A;\">" . htmlspecialchars($label) . "</a>"
             . "</td></tr></table>";
    }

    /** Soft callout (expiry, security note). */
    private static function note(string $html): string
    {
        return "<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin:0 0 6px 0;'><tr>"
             . "<td style=\"background-color:#F1DDD2; border-left:3px solid #B5482A; border-radius:6px; padding:12px 16px; font-family:" . self::FONT_BODY . "; font-size:13px; line-height:1.55; color:#4A453C;\">{$html}</td></tr></table>";
    }

    private static function wrap(string $content, string $preheader = ''): string
    {
        $appUrl = defined('APP_URL') ? rtrim((string)APP_URL, '/') : 'https://studyvibe.edu';
        $header = Brand::emailHeader('#FBF8F2', 190);
        $fb  = self::FONT_BODY;
        $pre = $preheader !== ''
            ? "<div style='display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#F5F0E6;'>" . htmlspecialchars($preheader) . str_repeat('&nbsp;&zwnj;', 40) . "</div>"
            : '';

        return "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta name='color-scheme' content='light'>
    <title>StudyVibe</title>
    <link href='https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500&family=Hanken+Grotesk:wght@400;600;700&display=swap' rel='stylesheet'>
</head>
<body style=\"margin:0; padding:0; background-color:#F5F0E6; font-family:{$fb}; -webkit-font-smoothing:antialiased;\">
    {$pre}
    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color:#F5F0E6;'>
        <tr>
            <td align='center' style='padding:36px 14px;'>
                <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width:600px; background-color:#FBF8F2; border:1px solid #DDD5C3; border-radius:14px; overflow:hidden;'>
                    <tr><td style='height:5px; line-height:5px; font-size:0; background-color:#B5482A;'>&nbsp;</td></tr>
                    <tr><td>{$header}</td></tr>
                    <tr>
                        <td style=\"padding:8px 40px 38px 40px; font-family:{$fb}; color:#1E1B16; font-size:15px; line-height:1.65;\">
                            {$content}
                        </td>
                    </tr>
                    <tr>
                        <td style=\"background-color:#F5F0E6; padding:24px 40px; border-top:1px solid #DDD5C3; text-align:center; font-family:{$fb}; color:#6F695C; font-size:12px; line-height:1.6;\">
                            <p style='margin:0 0 6px 0; font-weight:700; color:#4A453C;'>StudyVibe</p>
                            <p style='margin:0 0 12px 0;'>La plateforme pour l&rsquo;enseignement sup&eacute;rieur. Vous recevez ce message automatique car une action a &eacute;t&eacute; effectu&eacute;e avec votre adresse.</p>
                            <p style='margin:0;'><a href='{$appUrl}' style='color:#B5482A; text-decoration:none; font-weight:700;'>Acc&eacute;der &agrave; StudyVibe</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
    }

    // =========================================================================
    // SECTION 5: SOCKET CONNECTION AND DATA STREAMING
    // =========================================================================

    /**
     * Low-level SMTP client. Opens direct streams to servers and handles transactions.
     * 
     * @param string $to       Recipient email.
     * @param string $subject  Email subject.
     * @param string $html     HTML content.
     * @param string $from     Sender email.
     * @param string $fromName Sender display name.
     * @return bool True if successfully dispatched to the MTA.
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

        // Construct headers and body payload
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $message  = "From: {$fromName} <{$from}>\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Subject: {$encodedSubject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "\r\n{$html}\r\n.";
        
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
