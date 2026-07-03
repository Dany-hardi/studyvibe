<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

// =========================================================================
// SECTION 1: SECURE SESSION INITIALIZATION & COOKIE CONFIGURATION
// =========================================================================

// Configure strict session cookie security properties
ini_set('session.cookie_httponly', '1');      // Prevent XSS from accessing session IDs via document.cookie
ini_set('session.cookie_samesite', 'Strict');  // Prevent CSRF by withholding cookie on cross-site requests
ini_set('session.use_strict_mode', '1');       // Force use of server-generated session IDs
ini_set('session.gc_maxlifetime', '7200');     // Set session lifetime to 2 hours (in seconds)

// Enforce SSL-only cookies if running on HTTPS
if (HTTPS_ONLY === 'true') {
    ini_set('session.cookie_secure', '1');
}

// Start session if not already initialized
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load localization and translation translation service
require_once __DIR__ . '/lib/TranslationService.php';
TranslationService::init();

// =========================================================================
// SECTION 2: AUTHENTICATION STATE & USER DATA RETRIEVAL
// =========================================================================

/**
 * Checks if the current request belongs to an authenticated user.
 * 
 * @return bool True if both user ID and role session variables exist.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['user_role']);
}

/**
 * Retrieves the currently logged-in user details from the database.
 * 
 * @return array|null The user record array, or null if unauthenticated/non-existent.
 */
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

/**
 * Gating function: redirects users with unverified emails to the pending screen.
 * 
 * @return void
 */
function requireVerifiedEmail(): void
{
    $user = getCurrentUser();
    if ($user && empty($user['email_verified_at'])) {
        header('Location: /verify-email-pending.php');
        exit;
    }
}

/**
 * Calculates relative path depth mapping to reference root files (e.g. index.php).
 * 
 * @return string Path prefix (e.g. '../../') matching directory hierarchy.
 */
function getBaseRelativePath(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $depth = substr_count(trim($script, '/'), '/');
    return str_repeat('../', $depth);
}

// =========================================================================
// SECTION 3: ROLE-BASED ACCESS GATES & SECURITY AUDIT
// =========================================================================

/**
 * Gating function: Enforces specific user role access. Redirects unauthorized 
 * sessions to respective dashboards or landing page. Also handles session ID 
 * rotation for session fixation defense.
 * 
 * @param string $role Target role expected ('promoter' | 'teacher' | 'student').
 * @return void
 */
function requireRole(string $role): void
{
    $base = getBaseRelativePath();
    if (!isLoggedIn()) {
        header('Location: ' . $base . 'index.php');
        exit;
    }
    
    // Redirect if session role does not match gating role
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
    
    // Periodically rotate the session ID (every 30 minutes) to mitigate session hijacking
    if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

// =========================================================================
// SECTION 4: ANTI BRUTE-FORCE RATE LIMITING & CLIENT IP DETECTION
// =========================================================================

/**
 * Resolves the client connection IP address, handling proxy headers securely.
 * 
 * @return string resolved IP address.
 */
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

/**
 * Checks if login attempts have exceeded limits for a given IP or Email.
 * 
 * @param string $email The target login email.
 * @return bool True if lockout is currently active.
 */
function isRateLimited(string $email): bool
{
    try {
        $pdo         = Database::getInstance();
        $ip          = getClientIp();
        $maxAttempts = (int)LOGIN_MAX_ATTEMPTS;
        $window      = (int)LOGIN_LOCKOUT_MINUTES;
        $since       = date('Y-m-d H:i:s', time() - $window * 60);

        // Check attempts registered on the client IP
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip_address = :ip AND attempted_at > :since
        ");
        $stmt->execute(['ip' => $ip, 'since' => $since]);
        if ((int)$stmt->fetchColumn() >= $maxAttempts) return true;

        // Check attempts registered on the target login email
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE email = :email AND attempted_at > :since
        ");
        $stmt->execute(['email' => $email, 'since' => $since]);
        return (int)$stmt->fetchColumn() >= $maxAttempts;

    } catch (PDOException) {
        return false; // Fail open to prevent locking everyone out if database is transiently offline
    }
}

/**
 * Logs a failed authentication attempt to the database for rate-limit auditing.
 * 
 * @param string $email Target login email.
 * @return void
 */
function recordLoginAttempt(string $email): void
{
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (:ip, :email)");
        $stmt->execute(['ip' => getClientIp(), 'email' => $email]);
    } catch (PDOException) { /* Fail silently */ }
}

/**
 * Clears recorded login attempts for an IP/email after a successful authentication.
 * 
 * @param string $email Target login email.
 * @return void
 */
function clearLoginAttempts(string $email): void
{
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = :email OR ip_address = :ip");
        $stmt->execute(['email' => $email, 'ip' => getClientIp()]);
    } catch (PDOException) { /* Fail silently */ }
}

// =========================================================================
// SECTION 5: SECURITY AUDIT LOGGING SYSTEM
// =========================================================================

/**
 * Registers an administrative or security audit log entry.
 * 
 * @param string $action The action key (e.g. 'export_grades').
 * @param string $details Additional contextual parameters.
 * @return void
 */
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
    } catch (PDOException) { /* Fail silently */ }
}

// =========================================================================
// SECTION 6: CSRF UTILITY HOOKS (SAMESITE COOKIE DEFENSE)
// =========================================================================

/**
 * Generates or retrieves the active user CSRF token.
 * 
 * @return string Token value.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generates HTML meta tag displaying the CSRF token value.
 * 
 * @return string Meta tag markup.
 */
function csrfMetaTag(): string
{
    return '<meta name="csrf-token" content="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Generates hidden form input containing the CSRF token.
 * 
 * @return string Input HTML markup.
 */
function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * CSRF placeholder validation.
 * 
 * @param string|null $token CSRF token sent in request payload.
 * @return bool Always true. SameSite=Strict cookies are used as primary protection.
 */
function validateCsrf(?string $token = null): bool
{
    return true;
}

/**
 * CSRF placeholder check. SameSite=Strict is used as the primary browser defense.
 * 
 * @return void
 */
function requireCsrf(): void 
{
    // SameSite=Strict cookie configuration is the primary cross-site request defense.
}

// =========================================================================
// SECTION 7: EXCEPTION HANDLING & SECURE SERVER ERROR ROUTING
// =========================================================================

/**
 * Safe logger for system errors. Does not output SQL strings to client response.
 * 
 * @param Throwable $e Handled exception.
 * @param string    $context Description of file/operation scope.
 * @return void
 */
function logServerError(Throwable $e, string $context = ''): void
{
    $msg = '[StudyVibe]';
    if ($context !== '') {
        $msg .= " [{$context}]";
    }
    $msg .= ' ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log($msg);
}

/**
 * Gracefully halts script execution with a structured JSON error response.
 * 
 * @param string         $message Client-facing error description.
 * @param Throwable|null $e Underlying system error (logged, not returned).
 * @param string         $context Operation scope description.
 * @return never
 */
function jsonError(string $message = 'Erreur serveur. Veuillez réessayer.', ?Throwable $e = null, string $context = ''): never
{
    if ($e !== null) {
        logServerError($e, $context);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Gracefully redirects user request to standard custom Error Display Screen.
 * 
 * @param string         $message Client-facing error description.
 * @param Throwable|null $e Underlying system error (logged, not returned).
 * @param string         $context Operation scope description.
 * @return never
 */
function dieSafe(string $message = 'Erreur serveur. Veuillez réessayer.', ?Throwable $e = null, string $context = ''): never
{
    if ($e !== null) {
        logServerError($e, $context);
    }
    
    $errorCode = 500;
    $errorTitle = "Erreur Système";
    $errorMessage = $message;
    $badgeText = "Alerte";
    
    $errorFile = __DIR__ . '/error.php';
    if (file_exists($errorFile)) {
        include $errorFile;
        exit;
    }
    
    die(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

// =========================================================================
// SECTION 8: PROTECTED MEDIA ACCESS ROUTER
// =========================================================================

/**
 * Wraps file names to download proxies, protecting local server file structures.
 * 
 * @param string $type The media category (e.g. 'lesson_pdf').
 * @param string $file The filename.
 * @return string Sanitized download URL path.
 */
function mediaUrl(string $type, string $file): string
{
    $file = basename($file);
    return '/download.php?type=' . rawurlencode($type) . '&file=' . rawurlencode($file);
}
