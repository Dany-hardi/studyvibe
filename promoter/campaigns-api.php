<?php
declare(strict_types=1);

/** Tracked links and QR codes. POST action=create|archive|restore. The link carries ?src=<slug>, sign-ups are attributed to it. */
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'promoter') {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

function pmSlug(string $name): string
{
    $s = strtolower(trim($name));
    $s = strtr($s, ['à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c']);
    $s = trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return substr($s !== '' ? $s : 'campagne', 0, 30);
}

try {
    $pdo    = Database::getInstance();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $name   = trim((string)($_POST['name'] ?? ''));
        $target = (string)($_POST['target'] ?? 'signup');
        $ref    = trim((string)($_POST['target_ref'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120 || !in_array($target, ['signup', 'home', 'course', 'live'], true)) {
            echo json_encode(['success' => false, 'message' => 'Nom ou type invalide.']);
            exit;
        }
        if ($target === 'course') {
            $ok = $pdo->prepare('SELECT 1 FROM courses WHERE id = :i'); $ok->execute(['i' => (int)$ref]);
            if (!$ok->fetchColumn()) { echo json_encode(['success' => false, 'message' => 'Cours introuvable.']); exit; }
            $ref = (string)(int)$ref;
        } elseif ($target === 'live') {
            $ok = $pdo->prepare('SELECT session_code FROM live_eval_sessions WHERE id = :i'); $ok->execute(['i' => (int)$ref]);
            $code = $ok->fetchColumn();
            if (!$code) { echo json_encode(['success' => false, 'message' => 'Séance introuvable.']); exit; }
            $ref = (string)(int)$ref;
        } else {
            $ref = '';
        }

        $slug = pmSlug($name);
        $try = $slug;
        for ($i = 2; $i < 50; $i++) {
            $st = $pdo->prepare('SELECT 1 FROM campaigns WHERE slug = :s'); $st->execute(['s' => $try]);
            if (!$st->fetchColumn()) { break; }
            $try = substr($slug, 0, 27) . '-' . $i;
        }
        $pdo->prepare('INSERT INTO campaigns (name, slug, target, target_ref, created_by) VALUES (:n, :s, :t, :r, :u)')
            ->execute(['n' => $name, 's' => $try, 't' => $target, 'r' => $ref !== '' ? $ref : null, 'u' => (int)$_SESSION['user_id']]);
        auditLog('campaign_created', "{$name} ({$try}, {$target})");
        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'slug' => $try]);
        exit;
    }

    if (in_array($action, ['archive', 'restore'], true)) {
        $pdo->prepare('UPDATE campaigns SET archived = :a WHERE id = :i')->execute(['a' => $action === 'archive' ? 1 : 0, 'i' => (int)($_POST['id'] ?? 0)]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
} catch (Throwable $e) {
    logServerError($e, 'promoter/campaigns-api');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
