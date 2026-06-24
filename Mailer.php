<?php
declare(strict_types=1);

/**
 * Envoi d'emails StudyVibe — SMTP natif (sans dépendance Composer).
 * Configurez SMTP_* dans .env ; sinon fallback sur mail().
 */
class Mailer
{
    private static ?string $lastError = null;

    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    public static function isConfigured(): bool
    {
        return defined('SMTP_HOST') && SMTP_HOST !== '' && defined('SMTP_USER') && SMTP_USER !== '';
    }

    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        self::$lastError = null;
        $from     = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@studyvibe.edu';
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'StudyVibe';

        if (self::isConfigured()) {
            $ok = self::sendSmtp($to, $subject, $htmlBody, $from, $fromName);
            if (!$ok) {
                self::$lastError = 'Échec de connexion ou d\'envoi SMTP.';
            }
            return $ok;
        }

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$from}>\r\n";

        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
        if (!$ok) {
            self::$lastError = 'mail() PHP indisponible — configurez SMTP_HOST dans .env';
        }
        return $ok;
    }

    public static function welcome(string $to, string $name, string $roleLabel = 'apprenant'): bool
    {
        $appUrl = APP_URL;
        $body   = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300'>Bienvenue sur StudyVibe</h2>
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>Votre compte {$roleLabel} a été créé avec succès. Vérifiez votre adresse email pour activer toutes les fonctionnalités.</p>
            <p><a href='{$appUrl}' style='display:inline-block;padding:12px 24px;background:#111;color:#fff;text-decoration:none;font-size:13px'>Accéder à la plateforme</a></p>
        ");
        return self::send($to, 'Bienvenue sur StudyVibe', $body);
    }

    public static function emailVerification(string $to, string $name, string $token): bool
    {
        $url  = APP_URL . '/verify-email.php?token=' . urlencode($token);
        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300'>Confirmez votre adresse email</h2>
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>Cliquez sur le bouton ci-dessous pour vérifier votre adresse et activer votre compte StudyVibe.</p>
            <p><a href='{$url}' style='display:inline-block;padding:12px 24px;background:#004B23;color:#fff;text-decoration:none;font-size:13px'>Vérifier mon email</a></p>
            <p style='font-size:12px;color:#888'>Ce lien expire dans 48 heures.</p>
        ");
        return self::send($to, 'Vérifiez votre email — StudyVibe', $body);
    }

    public static function passwordReset(string $to, string $name, string $token): bool
    {
        $url  = APP_URL . '/reset-password.php?token=' . urlencode($token);
        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300'>Réinitialisation du mot de passe</h2>
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>Une demande de réinitialisation a été effectuée. Si vous êtes à l'origine de cette demande, cliquez ci-dessous :</p>
            <p><a href='{$url}' style='display:inline-block;padding:12px 24px;background:#111;color:#fff;text-decoration:none;font-size:13px'>Choisir un nouveau mot de passe</a></p>
            <p style='font-size:12px;color:#888'>Ce lien expire dans 2 heures. Ignorez cet email si vous n'avez pas fait cette demande.</p>
        ");
        return self::send($to, 'Réinitialisation mot de passe — StudyVibe', $body);
    }

    public static function certification(string $to, string $name, string $moduleTitle, string $certCode): bool
    {
        $appUrl    = APP_URL;
        $verifyUrl = $appUrl . '/verify.php?code=' . urlencode($certCode);
        $certUrl   = $appUrl . '/certificate.php?code=' . urlencode($certCode);
        $body      = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300'>Félicitations !</h2>
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>Vous avez validé le module <strong>" . htmlspecialchars($moduleTitle) . "</strong>.</p>
            <p>Code certificat : <code style='background:#f5f5f7;padding:4px 8px'>{$certCode}</code></p>
            <p><a href='{$certUrl}' style='display:inline-block;padding:12px 24px;background:#004B23;color:#fff;text-decoration:none;font-size:13px;margin-right:8px'>Voir mon certificat</a>
            <a href='{$verifyUrl}' style='font-size:13px;color:#004B23'>Vérifier en ligne</a></p>
        ");
        return self::send($to, 'Votre certificat StudyVibe — ' . $moduleTitle, $body);
    }

    public static function quizResults(string $to, string $studentName, string $lessonTitle, string $courseTitle, int $score, array $qas): bool
    {
        $qasHtml = '';
        foreach ($qas as $idx => $qa) {
            $num = $idx + 1;
            $status = $qa['answered_correctly'] ? "<span style='color:#004B23; font-weight:bold;'>✓ Correct (+1)</span>" : "<span style='color:#C62828; font-weight:bold;'>✕ Incorrect (0)</span>";
            
            $optionsHtml = '';
            foreach (['A', 'B', 'C', 'D'] as $opt) {
                $optText = $qa['option_' . strtolower($opt)] ?? '';
                $isCorrectOpt = $opt === $qa['correct_option'];
                $isSelectedOpt = $opt === $qa['selected_option'];
                
                $style = 'padding: 6px 12px; margin-bottom: 4px; border: 1px solid #E5E5E7; font-size: 13px;';
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
                <div style='margin-bottom: 24px; border-bottom: 1px solid #E5E5E7; padding-bottom: 16px;'>
                    <h4 style='margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #111;'>Question {$num} : " . htmlspecialchars($qa['question_text']) . "</h4>
                    <div style='margin-bottom: 10px;'>{$optionsHtml}</div>
                    <div style='font-size: 12px;'>Statut : {$status}</div>
                </div>
            ";
        }

        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300;color:#004B23;margin-top:0;'>Copie de vos réponses au Quiz</h2>
            <p>Bonjour <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
            <p>Vous avez complété avec succès le quiz pour la leçon : <strong>" . htmlspecialchars($lessonTitle) . "</strong> (Cours : <em>" . htmlspecialchars($courseTitle) . "</em>).</p>
            
            <div style='background-color:#F5F5F7; border: 1px solid #E5E5E7; padding: 16px; margin: 20px 0; text-align: center;'>
                <div style='font-size:12px; text-transform:uppercase; color:#888; letter-spacing:1px;'>Note Officielle</div>
                <div style='font-size:36px; font-weight:bold; color:#004B23; margin: 5px 0;'>{$score}%</div>
                <div style='font-size:12px; color:#555;'>Vos réponses ont été enregistrées de manière définitive.</div>
            </div>
            
            <h3 style='font-family:Georgia,serif;font-weight:300;border-bottom:2px solid #004B23;padding-bottom:6px;margin-top:30px;'>Détails de vos réponses</h3>
            {$qasHtml}
        ");
        
        return self::send($to, "StudyVibe — Résultats du Quiz : {$lessonTitle}", $body);
    }

    public static function sendLiveEvalResults(string $to, string $studentName, string $sessionTitle, float $score, array $qas): bool
    {
        $qasHtml = '';
        foreach ($qas as $idx => $qa) {
            $num = $idx + 1;
            $isCorrect = $qa['selected_option'] === $qa['correct_option'];
            $status = $isCorrect ? "<span style='color:#004B23; font-weight:bold;'>✓ Correct (+1)</span>" : "<span style='color:#C62828; font-weight:bold;'>✕ Incorrect (0)</span>";
            
            $optionsHtml = '';
            foreach (['A', 'B', 'C', 'D'] as $opt) {
                $optText = $qa['option_' . strtolower($opt)] ?? '';
                $isCorrectOpt = $opt === $qa['correct_option'];
                $isSelectedOpt = $opt === $qa['selected_option'];
                
                $style = 'padding: 6px 12px; margin-bottom: 4px; border: 1px solid #E5E5E7; font-size: 13px;';
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
                <div style='margin-bottom: 24px; border-bottom: 1px solid #E5E5E7; padding-bottom: 16px;'>
                    <h4 style='margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #111;'>Question {$num} : " . htmlspecialchars($qa['question_text']) . "</h4>
                    <div style='margin-bottom: 10px;'>{$optionsHtml}</div>
                    <div style='font-size: 12px;'>Statut : {$status}</div>
                </div>
            ";
        }

        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300;color:#004B23;margin-top:0;'>Résultats de votre Téléévaluation</h2>
            <p>Bonjour <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
            <p>Vous avez participé à la séance de téléévaluation : <strong>" . htmlspecialchars($sessionTitle) . "</strong>.</p>
            
            <div style='background-color:#F5F5F7; border: 1px solid #E5E5E7; padding: 16px; margin: 20px 0; text-align: center;'>
                <div style='font-size:12px; text-transform:uppercase; color:#888; letter-spacing:1px;'>Note Obtenue</div>
                <div style='font-size:36px; font-weight:bold; color:#004B23; margin: 5px 0;'>{$score}%</div>
                <div style='font-size:12px; color:#555;'>Ce résultat a été transmis à votre enseignant.</div>
            </div>
            
            <h3 style='font-family:Georgia,serif;font-weight:300;border-bottom:2px solid #004B23;padding-bottom:6px;margin-top:30px;'>Détails des questions</h3>
            {$qasHtml}
        ");
        
        return self::send($to, "StudyVibe — Résultats Téléévaluation : {$sessionTitle}", $body);
    }

    /** Notification promoteur : nouvel cours créé par un enseignant. */
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
            <p><a href='{$appUrl}/promoter/dashboard.php' style='display:inline-block;padding:12px 24px;background:#004B23;color:#fff;text-decoration:none;font-size:13px'>Ouvrir la console promoteur</a></p>
        ");
        return self::send($to, 'StudyVibe — Nouveau cours : ' . $courseTitle, $body);
    }

    /** Notification enseignant : cours révoqué ou réassigné par le promoteur. */
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

    /** Notification enseignant : compte validé par le promoteur. */
    public static function teacherApproved(string $to, string $name): bool
    {
        $appUrl = APP_URL;
        $body   = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300'>Compte validé !</h2>
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>Bonne nouvelle ! Votre compte d'enseignant sur StudyVibe a été validé par le promoteur.</p>
            <p>Vous pouvez dès maintenant vous connecter à votre espace, créer vos cours et gérer vos leçons.</p>
            <p><a href='{$appUrl}' style='display:inline-block;padding:12px 24px;background:#004B23;color:#fff;text-decoration:none;font-size:13px'>Me connecter</a></p>
        ");
        return self::send($to, 'Votre compte enseignant a été validé ! — StudyVibe', $body);
    }

    /** Message direct envoyé par le promoteur à un apprenant. */
    public static function directMessage(string $to, string $studentName, string $subject, string $messageText): bool
    {
        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300;color:#004B23'>Message de l'administration StudyVibe</h2>
            <p>Bonjour <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
            <p>Le promoteur de la plateforme vous a envoyé le message direct suivant :</p>
            <div style='background:#F5F5F7;border-left:4px solid #004B23;padding:16px;margin:20px 0;line-height:1.6;font-family:inherit;white-space:pre-wrap;'>" . htmlspecialchars($messageText) . "</div>
            <p style='font-size:12px;color:#666;'>Vous pouvez répondre à ce message ou vous connecter sur StudyVibe pour suivre vos cours.</p>
        ");
        return self::send($to, $subject, $body);
    }

    /** Newsletter envoyée par le promoteur. */
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

    private static function wrap(string $content): string
    {
        return "<!DOCTYPE html><html><body style='font-family:Inter,Arial,sans-serif;color:#111;max-width:560px;margin:0 auto;padding:32px'>{$content}<hr style='border:none;border-top:1px solid #eee;margin-top:32px'><p style='font-size:11px;color:#888'>StudyVibe — Plateforme Académique</p></body></html>";
    }

    private static function sendSmtp(string $to, string $subject, string $html, string $from, string $fromName): bool
    {
        $host = SMTP_HOST;
        $port = (int)(defined('SMTP_PORT') ? SMTP_PORT : 587);
        $user = SMTP_USER;
        $pass = defined('SMTP_PASS') ? SMTP_PASS : '';

        $socket = @fsockopen(($port === 465 ? 'ssl://' : '') . $host, $port, $errno, $errstr, 10);
        if (!$socket) return false;

        $read = static function () use ($socket): string {
            $data = '';
            while ($line = fgets($socket, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $data;
        };
        $write = static function (string $cmd) use ($socket): void {
            fwrite($socket, $cmd . "\r\n");
        };

        $read();
        $write("EHLO studyvibe.local");
        $read();

        if ($port !== 465) {
            $write('STARTTLS');
            $read();
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write("EHLO studyvibe.local");
            $read();
        }

        $write('AUTH LOGIN');
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        if (!str_starts_with($read(), '235')) {
            fclose($socket);
            return false;
        }

        $write("MAIL FROM:<{$from}>");
        $read();
        $write("RCPT TO:<{$to}>");
        $read();
        $write('DATA');
        $read();

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $message  = "From: {$fromName} <{$from}>\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Subject: {$encodedSubject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "\r\n{$html}\r\n.";
        $write($message);
        $ok = str_starts_with($read(), '250');
        $write('QUIT');
        fclose($socket);
        return $ok;
    }
}
