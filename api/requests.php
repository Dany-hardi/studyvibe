<?php
declare(strict_types=1);

/**
 * A student writes to their teacher (review of a mark, question about a result, message about a course) and reads the answers.
 *
 *   GET                                 the student's own requests, newest first
 *   POST kind=assignment  ref_id=<submission id> message=...        (the mark must have been released to the student)
 *   POST kind=live_result ref_id=<registration id> message=...      (not for a cancelled result: that has its own contestation)
 *   POST kind=course      course_id=<id> subject=... message=...
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/StudentRequests.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$msgs = [
    'kind'         => ['Type de demande invalide.', 'Invalid request type.'],
    'not_found'    => ['Élément introuvable.', 'Item not found.'],
    'forbidden'    => ['Cet élément n’est pas à vous.', 'This item is not yours.'],
    'not_released' => ['La note n’est pas encore publiée : il n’y a rien à réviser pour l’instant.', 'The mark is not released yet: there is nothing to review for now.'],
    'cancelled'    => ['Ce résultat a été annulé : utilisez la contestation prévue pour cela.', 'This result was cancelled: use the contestation made for that.'],
    'no_result'    => ['Ce résultat n’est pas encore disponible.', 'This result is not available yet.'],
    'already'      => ['Vous avez déjà envoyé une demande pour cette note ou ce résultat.', 'You already sent a request for this mark or result.'],
    'too_short'    => ['Expliquez votre demande en quelques phrases (au moins ' . StudentRequests::MIN . ' caractères).', 'Explain your request in a few sentences (at least ' . StudentRequests::MIN . ' characters).'],
    'too_long'     => ['Votre message est trop long (' . StudentRequests::MAX . ' caractères au plus).', 'Your message is too long (' . StudentRequests::MAX . ' characters at most).'],
    'subject'      => ['Donnez un objet à votre message (3 caractères au moins).', 'Give your message a subject (3 characters at least).'],
    'too_many'     => ['Trop de demandes en peu de temps. Réessayez dans un moment.', 'Too many requests in a short time. Try again in a while.'],
];

try {
    $pdo = Database::getInstance();
    $me = getCurrentUser();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requirePostFromSameSite();
        $res = StudentRequests::create($pdo, ['id' => (int)$me['id'], 'name' => (string)$me['name'], 'email' => (string)$me['email']], [
            'kind' => $_POST['kind'] ?? '', 'ref_id' => $_POST['ref_id'] ?? 0, 'course_id' => $_POST['course_id'] ?? 0, 'subject' => $_POST['subject'] ?? '', 'message' => $_POST['message'] ?? '',
        ]);
        if (!$res['ok']) {
            http_response_code(['forbidden' => 403, 'not_found' => 404, 'too_many' => 429][$res['error']] ?? 200);
            echo json_encode(['success' => false, 'error' => $res['error'], 'message' => ($msgs[$res['error']] ?? ['Erreur.', 'Error.'])[$lang === 'en' ? 1 : 0]]);
            exit;
        }
        auditLog('student_request', 'Request #' . $res['request_id'] . ' (' . (string)($_POST['kind'] ?? '') . ')');
        echo json_encode(['success' => true, 'request_id' => $res['request_id']]);
        exit;
    }
    echo json_encode(['success' => true, 'requests' => StudentRequests::forStudent($pdo, (int)$me['id'])], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logServerError($e, 'api/requests');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.']);
}
