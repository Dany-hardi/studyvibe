<?php
declare(strict_types=1);

/**
 * A student contests a result their teacher cancelled.
 *
 *   POST registration_id=5 message=...   [token=...]     (the token comes from the link in the email; a signed-in owner needs none)
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Contests.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/RateLimit.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
Security::requirePostFromSameSite();

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$msgs = [
    'not_found'     => ['Résultat introuvable.', 'Result not found.'],
    'forbidden'     => ['Accès refusé.', 'Access denied.'],
    'not_cancelled' => ['Ce résultat n’a pas été annulé : il n’y a rien à contester.', 'This result was not cancelled: there is nothing to contest.'],
    'already'       => ['Vous avez déjà envoyé une contestation pour ce résultat.', 'You already sent a contestation for this result.'],
    'too_short'     => ['Expliquez votre situation en quelques phrases (au moins ' . Contests::MIN . ' caractères).', 'Explain your situation in a few sentences (at least ' . Contests::MIN . ' characters).'],
    'too_long'      => ['Votre message est trop long (' . Contests::MAX . ' caractères au plus).', 'Your message is too long (' . Contests::MAX . ' characters at most).'],
    'too_many'      => ['Trop de tentatives. Réessayez dans un moment.', 'Too many attempts. Please try again in a while.'],
];
$fail = static function (string $code, int $http = 200) use ($msgs, $lang): never {
    http_response_code($http);
    echo json_encode(['success' => false, 'error' => $code, 'message' => ($msgs[$code] ?? ['Erreur.', 'Error.'])[$lang === 'en' ? 1 : 0]]);
    exit;
};

try {
    $pdo = Database::getInstance();
    $rid = (int)($_POST['registration_id'] ?? 0);
    $reg = $rid > 0 ? Contests::registration($pdo, $rid) : null;
    if ($reg === null) {
        $fail('not_found', 404);
    }
    $user = isLoggedIn() ? getCurrentUser() : null;
    if (!Contests::mayAccess($reg, $user, (string)($_POST['token'] ?? ''))) {
        $fail('forbidden', 403);
    }
    if (!RateLimit::allow($pdo, 'contest:reg:' . $rid, 10, 3600)) {
        $fail('too_many', 429);
    }
    $res = Contests::submit($pdo, $rid, (string)($_POST['message'] ?? ''));
    if (!$res['ok']) {
        $fail((string)$res['error']);
    }
    auditLog('live_contest_submitted', "Registration #{$rid}");
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    logServerError($e, 'api/contest');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.']);
}
