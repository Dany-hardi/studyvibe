<?php
declare(strict_types=1);

/**
 * API REST StudyVibe v1
 * Authentification: header X-API-Key
 *
 * GET /api/v1/courses          — liste des cours publics
 * GET /api/v1/certificates/:code — vérifier un certificat
 * GET /api/v1/modules          — liste des modules
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-API-Key, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function apiRateLimited(): bool
{
    try {
        $pdo  = Database::getInstance();
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $since = date('Y-m-d H:i:s', time() - 60);
        $pdo->prepare("DELETE FROM api_rate_limits WHERE requested_at < :since")->execute(['since' => $since]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE ip_address = :ip AND requested_at > :since");
        $stmt->execute(['ip' => $ip, 'since' => $since]);
        if ((int)$stmt->fetchColumn() >= 120) {
            return true;
        }
        $pdo->prepare("INSERT INTO api_rate_limits (ip_address) VALUES (:ip)")->execute(['ip' => $ip]);
        return false;
    } catch (PDOException) {
        return false;
    }
}

if (apiRateLimited()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Trop de requêtes. Réessayez dans une minute.']);
    exit;
}

function apiError(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function requireApiKey(): void
{
    $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($key === '') {
        apiError(401, 'Clé API requise (header X-API-Key).');
    }
    try {
        $pdo  = Database::getInstance();
        $hash = hash('sha256', $key);
        $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE key_hash = :hash AND is_active = 1");
        $stmt->execute(['hash' => $hash]);
        if (!$stmt->fetch()) {
            apiError(403, 'Clé API invalide.');
        }
    } catch (PDOException) {
        apiError(500, 'Erreur serveur.');
    }
}

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = preg_replace('#^/api/v1#', '', $uri);
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = Database::getInstance();

    // Vérification publique — pas de clé requise
    if ($method === 'GET' && preg_match('#^/certificates/([A-Za-z0-9\-]+)$#', $uri, $m)) {
        $stmt = $pdo->prepare("
            SELECT cert.certificate_code, cert.issued_at, u.name AS student_name, m.title AS module_title
            FROM certificates cert
            JOIN users u ON u.id = cert.student_id
            JOIN modules m ON m.id = cert.module_id
            WHERE cert.certificate_code = :code
        ");
        $stmt->execute(['code' => $m[1]]);
        $cert = $stmt->fetch();
        if (!$cert) {
            apiError(404, 'Certificat introuvable.');
        }
        echo json_encode(['success' => true, 'valid' => true, 'certificate' => $cert]);
        exit;
    }

    requireApiKey();

    if ($method === 'GET' && $uri === '/courses') {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $count = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.description, m.title AS module, COALESCE(u.name, 'Non assigné') AS teacher,
                   c.start_date, c.end_date, c.eval_deadline, c.is_published
            FROM courses c
            JOIN modules m ON m.id = c.module_id
            LEFT JOIN users u ON u.id = c.teacher_id
            ORDER BY c.id DESC
            LIMIT :lim OFFSET :off
        ");
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        echo json_encode([
            'success' => true,
            'data' => $rows,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $count],
        ]);
        exit;
    }

    if ($method === 'GET' && $uri === '/modules') {
        $rows = $pdo->query("SELECT id, title, description, created_at FROM modules ORDER BY id DESC")->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    apiError(404, 'Endpoint introuvable.');

} catch (PDOException $e) {
    apiError(500, 'Erreur serveur.');
}
