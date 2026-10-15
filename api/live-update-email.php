<?php
declare(strict_types=1);

/**
 * The participant of a live evaluation corrects the email their results are sent to (shown on the last screen).
 * POST only, JSON. The participant is the registration the page stored in the PHP session for this room code.
 * A mail still waiting in the queue is redirected, one already sent goes out again to the new address.
 */
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/LiveGuest.php';
require_once __DIR__ . '/../lib/LiveMailQueue.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
Security::requirePostFromSameSite();

$out = static function (array $body, int $code = 200): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
};

$code  = trim((string)($_POST['code'] ?? ''));
$regId = (int)($_SESSION['live_registrations'][$code] ?? 0);
if ($code === '' || $regId <= 0) {
    $out(['ok' => false, 'message' => 'Session introuvable. Rouvrez le lien de la séance.'], 403);
}

// at most 10 changes per minute per browser session
$now  = time();
$hits = array_filter($_SESSION['live_email_updates'] ?? [], fn($t) => $t > $now - 60);
if (count($hits) >= 10) {
    $out(['ok' => false, 'message' => 'Trop de tentatives. Patientez une minute.'], 429);
}
$hits[] = $now;
$_SESSION['live_email_updates'] = array_values($hits);

$email = LiveGuest::normalize((string)($_POST['email'] ?? ''));
$error = LiveGuest::check($email);
if ($error !== null) {
    $out(['ok' => false, 'message' => $error], 422);
}

try {
    $pdo = Database::getInstance();
    $st = $pdo->prepare("SELECT r.* FROM live_eval_registrations r JOIN live_eval_sessions s ON s.id = r.session_id WHERE r.id = :r AND s.session_code = :c AND r.cancelled_at IS NULL");
    $st->execute(['r' => $regId, 'c' => $code]);
    $reg = $st->fetch(PDO::FETCH_ASSOC);
    if (!$reg) {
        $out(['ok' => false, 'message' => 'Inscription introuvable.'], 404);
    }
    if (!empty($reg['student_id'])) {
        $out(['ok' => false, 'message' => "Votre adresse est celle de votre compte StudyVibe : modifiez-la dans votre profil."], 403);
    }
    if (strtolower((string)$reg['email']) === $email) {
        $out(['ok' => true, 'email' => $email, 'changed' => false]);
    }
    $dup = $pdo->prepare("SELECT 1 FROM live_eval_registrations WHERE session_id = :s AND email = :e AND id <> :r");
    $dup->execute(['s' => $reg['session_id'], 'e' => $email, 'r' => $regId]);
    if ($dup->fetchColumn()) {
        $out(['ok' => false, 'message' => 'Cette adresse est déjà utilisée par un autre participant de cette séance.'], 409);
    }

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE live_eval_registrations SET email = :e WHERE id = :r")->execute(['e' => $email, 'r' => $regId]);
    $q = $pdo->prepare("SELECT id, payload, status FROM live_eval_mail_queue WHERE registration_id = :r FOR UPDATE");
    $q->execute(['r' => $regId]);
    $job = $q->fetch(PDO::FETCH_ASSOC);
    $requeued = false;
    if ($job) {
        $payload = json_decode((string)$job['payload'], true) ?: [];
        $payload['email'] = $email;
        if ($job['status'] === 'sent' || $job['status'] === 'failed') {
            $requeued = true;
            $pdo->prepare("UPDATE live_eval_mail_queue SET payload = :p, status = 'pending', attempts = 0, claim = NULL, next_try_at = NULL, last_error = NULL, sent_at = NULL WHERE id = :i")
                ->execute(['p' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'i' => $job['id']]);
        } else {
            $pdo->prepare("UPDATE live_eval_mail_queue SET payload = :p WHERE id = :i")
                ->execute(['p' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'i' => $job['id']]);
        }
    }
    $pdo->commit();
    if ($requeued) {
        LiveMailQueue::kick();
    }
    $out(['ok' => true, 'email' => $email, 'changed' => true, 'resent' => $requeued]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('live-update-email: ' . $e->getMessage());
    $out(['ok' => false, 'message' => 'Modification impossible pour le moment.'], 500);
}
