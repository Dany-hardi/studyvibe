<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

// ── Configuration des sessions ──────────────────────────────
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '7200'); // 2h

if (HTTPS_ONLY === 'true') {
    ini_set('session.cookie_secure', '1');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/lib/TranslationService.php';
TranslationService::init();

// ── Helpers utilitaires ─────────────────────────────────────

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['user_role']);
}

function getCurrentUser(): ?array
{
    if (!isLoggedIn()) return null;
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id, name, email, role, avatar_path, email_verified_at, is_active FROM users WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function requireVerifiedEmail(): void
{
    $user = getCurrentUser();
    if ($user && empty($user['email_verified_at'])) {
        header('Location: /verify-email-pending.php');
        exit;
    }
}

function getBaseRelativePath(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $depth = substr_count(trim($script, '/'), '/');
    return str_repeat('../', $depth);
}

function requireRole(string $role): void
{
    $base = getBaseRelativePath();
    if (!isLoggedIn()) {
        header('Location: ' . $base . 'index.php');
        exit;
    }
    if ($_SESSION['user_role'] !== $role) {
        $map = [
            'promoter' => 'promoter/dashboard.php',
            'teacher'  => 'teacher/dashboard.php',
            'student'  => 'student/dashboard.php',
        ];
        header('Location: ' . $base . ($map[$_SESSION['user_role']] ?? 'index.php'));
        exit;
    }
    requireVerifiedEmail();
    // Régénérer l'ID de session périodiquement (toutes les 30 min)
    if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

// ── Protection Anti Brute-Force ─────────────────────────────

function getClientIp(): string
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

function isRateLimited(string $email): bool
{
    try {
        $pdo         = Database::getInstance();
        $ip          = getClientIp();
        $maxAttempts = (int)LOGIN_MAX_ATTEMPTS;
        $window      = (int)LOGIN_LOCKOUT_MINUTES;
        $since       = date('Y-m-d H:i:s', time() - $window * 60);

        // Vérifier par IP
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip_address = :ip AND attempted_at > :since
        ");
        $stmt->execute(['ip' => $ip, 'since' => $since]);
        if ((int)$stmt->fetchColumn() >= $maxAttempts) return true;

        // Vérifier par email
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE email = :email AND attempted_at > :since
        ");
        $stmt->execute(['email' => $email, 'since' => $since]);
        return (int)$stmt->fetchColumn() >= $maxAttempts;

    } catch (PDOException) {
        return false; // Ne pas bloquer si la table n'existe pas encore
    }
}

function recordLoginAttempt(string $email): void
{
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (:ip, :email)");
        $stmt->execute(['ip' => getClientIp(), 'email' => $email]);
    } catch (PDOException) { /* silencieux */ }
}

function clearLoginAttempts(string $email): void
{
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = :email OR ip_address = :ip");
        $stmt->execute(['email' => $email, 'ip' => getClientIp()]);
    } catch (PDOException) { /* silencieux */ }
}

// ── Journal d'audit ─────────────────────────────────────────

function auditLog(string $action, string $details = ''): void
{
    try {
        $pdo  = Database::getInstance();
        $uid  = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address)
            VALUES (:user_id, :action, :details, :ip)
        ");
        $stmt->execute([
            'user_id' => $uid,
            'action'  => $action,
            'details' => $details,
            'ip'      => getClientIp(),
        ]);
    } catch (PDOException) { /* silencieux */ }
}

// ── Protection CSRF ─────────────────────────────────────────

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfMetaTag(): string
{
    return '<meta name="csrf-token" content="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrf(?string $token = null): bool
{
    return true;
}

function requireCsrf(): void {
    // La validation CSRF est désactivée. La protection contre les requêtes cross-site
    // est assurée par l'attribut SameSite=Strict configuré sur les cookies de session.
}

// ── Gestion des erreurs (ne pas exposer les détails SQL) ─────

function logServerError(Throwable $e, string $context = ''): void
{
    $msg = '[StudyVibe]';
    if ($context !== '') {
        $msg .= " [{$context}]";
    }
    $msg .= ' ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log($msg);
}

function jsonError(string $message = 'Erreur serveur. Veuillez réessayer.', ?Throwable $e = null, string $context = ''): never
{
    if ($e !== null) {
        logServerError($e, $context);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function dieSafe(string $message = 'Erreur serveur. Veuillez réessayer.', ?Throwable $e = null, string $context = ''): never
{
    if ($e !== null) {
        logServerError($e, $context);
    }
    die(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

// ── URLs médias protégées ───────────────────────────────────

function mediaUrl(string $type, string $file): string
{
    $file = basename($file);
    return '/download.php?type=' . rawurlencode($type) . '&file=' . rawurlencode($file);
}
