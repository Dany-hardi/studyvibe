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
            if (!$ok && self::$lastError === null) {
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
        $coursesInfo = '';
        
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
                    $coursesInfo .= "<div style='background-color:#FAF9F6; border: 1px solid #E5E5E7; padding: 20px; margin: 24px 0; border-radius: 4px;'>";
                    $coursesInfo .= "<h4 style='margin-top:0; margin-bottom:12px; color:#004B23; font-family:Georgia,serif; font-size:15px; font-weight:normal;'>🔑 Clés d'inscription de vos cours :</h4>";
                    $coursesInfo .= "<p style='font-size:12px; color:#555; margin-bottom:12px;'>Copiez ces clés et utilisez-les sur votre tableau de bord pour déverrouiller vos cours instantanément :</p>";
                    $coursesInfo .= "<ul style='margin:0; padding-left:20px; line-height:1.6; font-size:13px; color:#111;'>";
                    foreach ($courses as $c) {
                        $coursesInfo .= "<li style='margin-bottom:6px;'><strong>" . htmlspecialchars($c['title']) . "</strong> : <code style='background:#E5E5E7; padding:2px 6px; border-radius:3px; font-weight:bold; font-family:monospace;'>" . htmlspecialchars($c['enrollment_key']) . "</code></li>";
                    }
                    $coursesInfo .= "</ul></div>";
                }
            } catch (Exception $e) {
                // Fallback silently if database is not available
            }
        }

        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300;color:#004B23;margin-top:0;'>Bienvenue sur StudyVibe !</h2>
            <p>Bonjour <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>Nous sommes ravis de vous accueillir au sein de notre communauté d'apprentissage. Votre compte de type <strong>" . htmlspecialchars($roleLabel) . "</strong> a été créé avec succès.</p>
            <p>Notre mission est de vous offrir une expérience d'apprentissage fluide, intuitive et enrichissante. Pour commencer dès aujourd'hui, accédez à votre espace pour découvrir tous vos cours.</p>
            
            {$coursesInfo}

            <p style='margin-top:24px;'><a href='{$appUrl}' style='display:inline-block;padding:12px 24px;background:#004B23;color:#fff;text-decoration:none;font-size:13px;font-weight:600;border-radius:4px;'>Accéder à mon espace StudyVibe</a></p>
            <p style='font-size:13px;color:#555;margin-top:24px;'>Si vous avez des questions ou si vous avez besoin d'aide pour vos premiers pas, notre équipe est à votre entière disposition. N'hésitez pas à répondre directement à ce message.</p>
            <p style='font-size:13px;color:#111;margin-top:24px;'>Chaleureusement,<br><strong>L'équipe StudyVibe</strong></p>
        ");
        
        return self::send($to, 'Bienvenue sur StudyVibe !', $body);
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

    public static function sendLiveEvalResults(string $to, string $studentName, string $sessionTitle, int $correctCount, int $totalQuestions, array $qas, int $registrationId = 0): bool
    {
        $scorePercent = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 1) : 0.0;
        $statusLabel = $scorePercent >= 50 ? 'Validé (Réussite)' : 'Non validé';
        $statusColor = $scorePercent >= 50 ? '#004B23' : '#C62828';

        $linkHtml = '';
        if ($registrationId > 0 && defined('APP_SECRET')) {
            $token = hash_hmac('sha256', (string)$registrationId, APP_SECRET);
            $baseUrl = defined('APP_URL') ? APP_URL : '';
            if (empty($baseUrl)) {
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $baseUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            }
            $url = rtrim($baseUrl, '/') . "/student/evaluation-results.php?registration_id={$registrationId}&token={$token}";
            $linkHtml = "
                <div style='margin: 25px 0; text-align: center;'>
                    <a href='" . htmlspecialchars($url, ENT_QUOTES) . "' style='display: inline-block; padding: 12px 24px; background-color: #004B23; color: #FFFFFF; font-weight: bold; text-decoration: none; border-radius: 4px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.15);'>Consulter mon rapport détaillé & correction</a>
                    <div style='font-size: 11px; color: #888; margin-top: 8px;'>Ce lien sécurisé vous permet d'accéder aux justifications et explications de chaque question en ligne.</div>
                </div>
            ";
        }

        $body = self::wrap("
            <h2 style='font-family:Georgia,serif;font-weight:300;color:#004B23;margin-top:0;'>Résultats de votre Téléévaluation</h2>
            <p>Bonjour <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
            <p>Vous avez participé à la séance de téléévaluation : <strong>" . htmlspecialchars($sessionTitle) . "</strong>.</p>
            
            <div style='background-color:#F5F5F7; border: 1px solid #E5E5E7; padding: 16px; margin: 20px 0;'>
                <div style='font-size:12px; text-transform:uppercase; color:#888; letter-spacing:1px; text-align: center; margin-bottom: 10px;'>Statistiques Individuelles d'Évaluation</div>
                
                <table style='width: 100%; border-collapse: collapse; font-size: 13px;'>
                    <tr style='border-bottom: 1px solid #E5E5E7;'>
                        <td style='padding: 8px 0; color: #555;'>Score obtenu :</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #111;'>{$correctCount} / {$totalQuestions}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #E5E5E7;'>
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

        // Si c'est un mot de passe d'application Google (16 char sans espace), on nettoie les espaces
        if (str_contains($host, 'gmail.com') && strlen(str_replace(' ', '', $pass)) === 16) {
            $pass = str_replace(' ', '', $pass);
        }

        // Désactiver la vérification SSL stricte pour éviter les échecs dus aux certificats CA manquants sur Railway
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $remoteSocketAddress = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remoteSocketAddress, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) {
            self::$lastError = "Connexion impossible à {$remoteSocketAddress} : [{$errno}] {$errstr}";
            return false;
        }

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
            $tlsResponse = $read();
            if (!str_starts_with($tlsResponse, '220')) {
                self::$lastError = "STARTTLS rejeté par le serveur : " . trim($tlsResponse);
                fclose($socket);
                return false;
            }
            
            // Activer le chiffrement TLS sur la socket
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                self::$lastError = "Échec de l'activation du chiffrement TLS (Handshake)";
                fclose($socket);
                return false;
            }
            
            $write("EHLO studyvibe.local");
            $read();
        }

        $write('AUTH LOGIN');
        $authLoginRes = $read();
        if (!str_starts_with($authLoginRes, '334')) {
            self::$lastError = "AUTH LOGIN non supporté ou rejeté : " . trim($authLoginRes);
            fclose($socket);
            return false;
        }

        $write(base64_encode($user));
        $userRes = $read();
        if (!str_starts_with($userRes, '334')) {
            self::$lastError = "SMTP Username base64 rejeté : " . trim($userRes);
            fclose($socket);
            return false;
        }

        $write(base64_encode($pass));
        $authResponse = $read();
        if (!str_starts_with($authResponse, '235')) {
            self::$lastError = "Authentification SMTP échouée : " . trim($authResponse);
            fclose($socket);
            return false;
        }

        $write("MAIL FROM:<{$from}>");
        $mailFromRes = $read();
        if (!str_starts_with($mailFromRes, '250')) {
            self::$lastError = "MAIL FROM rejeté : " . trim($mailFromRes);
            fclose($socket);
            return false;
        }

        $write("RCPT TO:<{$to}>");
        $rcptRes = $read();
        if (!str_starts_with($rcptRes, '250') && !str_starts_with($rcptRes, '251')) {
            self::$lastError = "Destinataire RCPT TO rejeté ({$to}) : " . trim($rcptRes);
            fclose($socket);
            return false;
        }

        $write('DATA');
        $dataRes = $read();
        if (!str_starts_with($dataRes, '354')) {
            self::$lastError = "Commande DATA rejetée : " . trim($dataRes);
            fclose($socket);
            return false;
        }

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

        $write('QUIT');
        fclose($socket);
        return $ok;
    }
}
