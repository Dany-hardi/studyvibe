<?php
declare(strict_types=1);

/** The certificate of the signed-in student as a LaTeX-built PDF (A4 landscape). Only the owner can download it. */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/student_i18n.php';
require_once __DIR__ . '/../lib/StudentDocs.php';
requireRole('student');

$code = trim((string)($_GET['code'] ?? ''));
$user = getCurrentUser();
$cert = null;
if ($code !== '' && $user) {
    try {
        $stmt = Database::getInstance()->prepare("
            SELECT cert.*, u.name AS student_name, c.title AS course_title
            FROM certificates cert
            JOIN users u ON cert.student_id = u.id
            LEFT JOIN courses c ON cert.course_id = c.id
            WHERE cert.certificate_code = :code AND cert.student_id = :sid
        ");
        $stmt->execute(['code' => $code, 'sid' => $user['id']]);
        $cert = $stmt->fetch();
    } catch (PDOException $e) {
        $cert = null;
    }
}
if (!$cert) {
    http_response_code(404);
    exit(sd('doc_err_nf_m'));
}

$verifyUrl   = rtrim((string)APP_URL, '/') . '/verify.php?code=' . urlencode($code);
$verifyShort = preg_replace('#^https?://#', '', rtrim((string)APP_URL, '/')) . '/verify.php';
$t = StudentDocs::strings(['issued_year' => sdDate($cert['issued_at'] ?? time(), 'year')]);
StudentDocs::send(StudentDocs::certificate($cert, $t, $verifyUrl, $verifyShort), 'certificat-' . $code . '.pdf', 'export_certificate_pdf');
