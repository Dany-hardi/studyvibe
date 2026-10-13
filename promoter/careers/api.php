<?php
declare(strict_types=1);

/**
 * JSON API of the careers workspace. POST only, JSON or form body with "action":
 *
 *   bootstrap                                        everything the page shows (stats, postings, applications)
 *   overview                                         numbers for the overview tab
 *   job_get{id}  job_save{id?,...fields}  job_status{id,status}  job_delete{id}
 *   apps{job_id?,status?,q?,starred?}                list of applications
 *   app_bucket{id,bucket}  apps_bulk{ids,op}  app_rescreen{id}  interview_save{application_id,...}  interview_cancel{id}  interview_complete{id}  interviews
 *   md_preview{md}  md_import{md,status}      a posting from a Markdown file: preview with every problem, then creation
 *   app_get{id}  app_status{id,status,notify?,message?}  app_note{id,body}  app_rate{id,rating}  app_star{id,on}  app_delete{id}
 *
 * Answers {success:true,...} or {success:false,error,message,fields?}. Same-site check + CSRF header + administrator role on every call.
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../lib/RateLimit.php';
require_once __DIR__ . '/../../lib/CareersScreening.php';
require_once __DIR__ . '/../../lib/CareersMarkdown.php';
require_once __DIR__ . '/../../lib/CareersInterviews.php';

$adminId = careersAdminOrFail(true);
Security::requirePostFromSameSite();
careersCheckCsrf();

$in = $_POST;
if (str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $j = json_decode((string)file_get_contents('php://input'), true);
    $in = is_array($j) ? $j : [];
}
$action = (string)($in['action'] ?? '');
$id = (int)($in['id'] ?? 0);

$out = static function (array $res, int $okCode = 200): never {
    if (empty($res['ok']) && !isset($res['success'])) {
        $code = match ($res['error'] ?? '') { 'not_found' => 404, 'validation', 'invalid', 'empty', 'incomplete', 'past_date', 'has_applications' => 422, default => 400 };
        http_response_code($code);
        echo json_encode(['success' => false] + $res, JSON_UNESCAPED_UNICODE);
        exit;
    }
    unset($res['ok']);
    http_response_code($okCode);
    echo json_encode(['success' => true] + $res, JSON_UNESCAPED_UNICODE);
    exit;
};

try {
    $pdo = Database::getInstance();
    if (!RateLimit::allow($pdo, 'careers:admin:' . $adminId, 600, 600)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Trop de requêtes. Patientez un instant.']);
        exit;
    }

    switch ($action) {
        case 'bootstrap':
            $out(['ok' => true, 'stats' => Careers::stats($pdo), 'jobs' => Careers::adminJobs($pdo), 'apps' => Careers::applications($pdo, ['bucket' => 'any'], 1500), 'interviews' => CareersInterviews::list($pdo)]);

        case 'overview':
            $out(['ok' => true, 'stats' => Careers::stats($pdo)]);

        case 'job_get':
            $job = Careers::job($pdo, $id);
            $job ? $out(['ok' => true, 'job' => $job, 'derived' => array_column(CareersScreening::deriveKeywords($job), 'label')]) : $out(['ok' => false, 'error' => 'not_found', 'message' => 'Offre introuvable.']);

        case 'job_save':
            $res = Careers::saveJob($pdo, $adminId, $id > 0 ? $id : null, $in);
            if ($res['ok']) {
                auditLog($res['created'] ? 'career_job_created' : 'career_job_updated', 'Job #' . $res['id'] . ' ' . $res['slug']);
                $res['job'] = Careers::job($pdo, (int)$res['id']);
            }
            $out($res, !empty($res['created']) ? 201 : 200);

        case 'job_status':
            $res = Careers::setJobStatus($pdo, $id, (string)($in['status'] ?? ''));
            if ($res['ok']) {
                auditLog('career_job_status', 'Job #' . $id . ' → ' . $res['status']);
            }
            $out($res);

        case 'job_delete':
            $res = Careers::deleteJob($pdo, $id);
            if ($res['ok']) {
                auditLog('career_job_deleted', 'Job #' . $id);
            }
            $out($res);

        case 'apps':
            $out(['ok' => true, 'apps' => Careers::applications($pdo, [
                'job_id' => $in['job_id'] ?? null, 'status' => $in['status'] ?? null, 'q' => $in['q'] ?? '', 'starred' => !empty($in['starred']), 'bucket' => $in['bucket'] ?? 'inbox',
            ])]);

        case 'app_get':
            $app = Careers::application($pdo, $id);
            if (!$app) {
                $out(['ok' => false, 'error' => 'not_found', 'message' => 'Candidature introuvable.']);
            }
            auditLog('career_application_viewed', 'Application #' . $id . ' ' . $app['reference']);
            $out(['ok' => true, 'app' => $app]);

        case 'app_status':
            $res = Careers::setStatus($pdo, $id, $adminId, (string)($in['status'] ?? ''), (string)($in['message'] ?? ''), !empty($in['notify']));
            if ($res['ok']) {
                auditLog('career_application_status', 'Application #' . $id . ' → ' . ($in['status'] ?? '') . (!empty($in['notify']) ? ' (candidate e-mailed)' : ''));
            }
            $out($res);

        case 'app_note':
            $res = Careers::addNote($pdo, $id, $adminId, (string)($in['body'] ?? ''));
            $out($res);

        case 'app_rate':
            $r = $in['rating'] ?? null;
            $res = Careers::setRating($pdo, $id, $adminId, ($r === null || $r === '' || (int)$r === 0) ? null : (int)$r);
            $out($res);

        case 'app_star':
            $out(Careers::setStar($pdo, $id, !empty($in['on'])));

        case 'app_delete':
            $res = Careers::deleteApplication($pdo, $id);
            if ($res['ok']) {
                auditLog('career_application_deleted', 'Application #' . $id);
            }
            $out($res);

        case 'md_preview':
            $p = CareersMarkdown::parse((string)($in['md'] ?? ''), $pdo);
            $out(['ok' => true, 'valid' => $p['ok'], 'errors' => $p['errors'], 'warnings' => $p['warnings'], 'job' => $p['job'], 'preview' => $p['preview']]);

        case 'md_import':
            $res = CareersMarkdown::import($pdo, $adminId, (string)($in['md'] ?? ''), isset($in['status']) ? (string)$in['status'] : null);
            if ($res['ok']) {
                auditLog('career_job_imported', 'Job #' . $res['id'] . ' ' . $res['slug'] . ' from a Markdown file');
                $out(['ok' => true, 'id' => $res['id'], 'slug' => $res['slug'], 'job' => $res['job'], 'warnings' => $res['warnings']], 201);
            }
            $out(['ok' => false, 'error' => 'validation', 'errors' => $res['errors'], 'message' => 'Le fichier contient des erreurs.']);

        case 'app_bucket':
            $res = Careers::moveBucket($pdo, $id, $adminId, (string)($in['bucket'] ?? ''));
            if ($res['ok'] && !empty($res['changed'])) {
                auditLog('career_application_bucket', 'Application #' . $id . ' → ' . $res['bucket']);
            }
            $out($res);

        case 'apps_bulk':
            $res = Careers::bulk($pdo, (array)($in['ids'] ?? []), $adminId, (string)($in['op'] ?? ''));
            if ($res['ok']) {
                auditLog('career_bulk_' . ($in['op'] ?? ''), $res['done'] . ' application(s)');
            }
            $out($res);

        case 'app_rescreen':
            $r = CareersScreening::screenApplication($pdo, $id, false);
            $r === null ? $out(['ok' => false, 'error' => 'server', 'message' => 'Lecture impossible.']) : $out(['ok' => true] + $r);

        case 'interview_save':
            $res = CareersInterviews::save($pdo, (int)($in['application_id'] ?? 0), $adminId, $in, !array_key_exists('notify', $in) || !empty($in['notify']));
            if ($res['ok']) {
                auditLog('career_interview_saved', 'Application #' . (int)($in['application_id'] ?? 0) . ' interview #' . $res['id']);
            }
            $out($res);

        case 'interview_cancel':
            $res = CareersInterviews::cancel($pdo, $id, $adminId, !array_key_exists('notify', $in) || !empty($in['notify']));
            if ($res['ok']) {
                auditLog('career_interview_cancelled', 'Interview #' . $id);
            }
            $out($res);

        case 'interview_complete':
            $out(CareersInterviews::complete($pdo, $id, $adminId));

        case 'interviews':
            $out(['ok' => true, 'interviews' => CareersInterviews::list($pdo)]);

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
    }
} catch (Throwable $e) {
    logServerError($e, 'promoter/careers/api:' . $action);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.']);
}
