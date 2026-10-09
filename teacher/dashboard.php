<?php
/**
 * StudyVibe Academic LMS - Teacher Dashboard Controller
 *
 * This controller serves as the primary management panel for instructors,
 * providing course scheduling, content curation, evaluation authoring,
 * live tele-evaluations, and student progress metrics.
 *
 * PHP version 8.2
 *
 * @category  Controller
 * @package   StudyVibe\Teacher
 * @author    StudyVibe Team <development@studyvibe.academic>
 * @copyright 2026 StudyVibe
 * @license   Proprietary
 * @link      https://studyvibe.academic
 */

declare(strict_types=1);

// =========================================================================
// SECTION 1: AUTHENTICATION & INPUT PARAMETERS SECURITY
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/MediaStore.php';
require_once __DIR__ . '/../lib/LiveSmsNotifier.php';
require_once __DIR__ . '/../lib/LiveMailNotifier.php';
require_once __DIR__ . '/../Mailer.php';
requireRole('teacher');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // requireCsrf();
}

$user = getCurrentUser();
$pdo  = Database::getInstance();

$defaultCourseSvg = '<svg class="w-12 h-12 text-[#B5482A]" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>';

$selectedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selectedCourse   = null;

// =========================================================================
// SECTION 2: HELPER FUNCTIONS & UTILITIES
// =========================================================================
function isValidYoutubeOrVimeo(string $url): bool {
    return (bool) preg_match('#^https?://(www\.)?(youtube\.com/watch|youtu\.be/|vimeo\.com/)#i', $url);
}

function handlePdfUpload(array $file): ?string {
    // Same checks as every other upload, and a copy in the database (slices of 256 KB) so the PDF outlives a redeploy.
    $res = MediaStore::saveDocument(Database::getInstance(), $file, 'pdf', ['pdf'], 20 * 1024 * 1024);
    return $res['ok'] ? $res['file'] : null;
}

/**
 * An activated (or moved) session is announced to the enrolled students by email (always) and by SMS (when switched on).
 * Returns the query-string part the dashboard uses to tell the teacher how many were queued.
 */
function notifyStudentsOfSession(PDO $pdo, int $sessionId, int $teacherId): string {
    try {
        $own = $pdo->prepare("SELECT 1 FROM live_eval_sessions WHERE id = :id AND teacher_id = :tid");
        $own->execute(['id' => $sessionId, 'tid' => $teacherId]);
        if (!$own->fetchColumn()) {
            return '';
        }
        $mail = LiveMailNotifier::notify($pdo, $sessionId);
        $param = '&mail_queued=' . (int)$mail['queued'] . '&mail_eligible=' . (int)$mail['eligible'];
        if (SmsGateway::enabled()) {
            $sms = LiveSmsNotifier::notify($pdo, $sessionId);
            $param .= '&sms_queued=' . (int)$sms['queued'] . '&sms_nophone=' . (int)$sms['skipped_no_phone'];
        }
        return $param;
    } catch (Throwable $e) {
        logServerError($e, 'live announcement');
        return '';
    }
}

function clearLiveSessionCache(): void {
    $cacheDir = __DIR__ . '/../uploads/live_cache';
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir . '/*.json') as $file) {
            @unlink($file);
        }
    }
}
// =========================================================================
// SECTION 3: REQUEST ACTION DISPATCHERS & STATE WRITERS
// =========================================================================
try {
    $teacherId = (int)$user['id'];
    $modules = $pdo->query("SELECT * FROM modules ORDER BY title ASC")->fetchAll();

    // ── Création de cours (enseignant) ───────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_course') {
        $moduleId      = (int)($_POST['module_id'] ?? 0);
        $title         = trim((string)($_POST['course_title'] ?? ''));
        $description   = trim((string)($_POST['course_desc'] ?? ''));
        $enrollmentKey = trim((string)($_POST['enrollment_key'] ?? ''));
        $startDate     = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate       = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $evalDeadline  = !empty($_POST['eval_deadline']) ? $_POST['eval_deadline'] : null;
        $examMinutes   = max(30, (int)($_POST['exam_duration_minutes'] ?? 90));

        if (!empty($title) && $moduleId > 0) {
            $coverImage = null;
            $uploadError = null;
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Checked on the file itself, scaled to 1280 px, and copied into the database so it survives redeploys.
                $coverRes = MediaStore::saveImage($pdo, $_FILES['cover_image'], 'cover', 1280, 8 * 1024 * 1024, 84);
                if ($coverRes['ok']) {
                    $coverImage = $coverRes['file'];
                } else {
                    $uploadError = match ($coverRes['error'] ?? '') {
                        'too_big'   => 'upload_size_error',
                        'not_image' => 'upload_ext_error',
                        'server'    => 'upload_move_error',
                        default     => 'upload_error',
                    };
                }
            }

            $coverImageData = null;

            $stmt = $pdo->prepare("
                INSERT INTO courses (
                    module_id, teacher_id, created_by, title, description, svg_icon,
                    enrollment_key, start_date, end_date, eval_deadline, exam_duration_minutes, cover_image, cover_image_data
                ) VALUES (
                    :module_id, :teacher_id, :created_by, :title, :description, :svg_icon,
                    :enrollment_key, :start_date, :end_date, :eval_deadline, :exam_minutes, :cover_image, :cover_image_data
                )
            ");
            $stmt->execute([
                'module_id'      => $moduleId,
                'teacher_id'     => $user['id'],
                'created_by'     => $user['id'],
                'title'          => $title,
                'description'    => $description,
                'svg_icon'       => $defaultCourseSvg,
                'enrollment_key' => $enrollmentKey !== '' ? $enrollmentKey : null,
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'eval_deadline'  => $evalDeadline,
                'exam_minutes'   => $examMinutes,
                'cover_image'    => $coverImage,
                'cover_image_data' => $coverImageData,
            ]);
            $newCourseId = (int)$pdo->lastInsertId();

            $modStmt = $pdo->prepare("SELECT title FROM modules WHERE id = :id");
            $modStmt->execute(['id' => $moduleId]);
            $moduleTitle = (string)$modStmt->fetchColumn();

            auditLog('course_created', "Cours « {$title} » (module: {$moduleTitle})");

            $promoters = $pdo->query("SELECT email, name FROM users WHERE role = 'promoter'")->fetchAll();
            foreach ($promoters as $promoter) {
                if (!empty($promoter['email'])) {
                    Mailer::courseCreatedByTeacher(
                        (string)$promoter['email'],
                        (string)$promoter['name'],
                        (string)$user['name'],
                        $title,
                        $moduleTitle
                    );
                }
            }

            $errParam = $uploadError ? "&error=" . urlencode($uploadError) : "";
            header("Location: /teacher/dashboard.php?course_id={$newCourseId}&success=course_created" . $errParam);
            exit;
        }
    }

    // ── Fetch teacher courses ──────────────────────────────────────────────
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = :tid ORDER BY id DESC");
    $stmt->execute(['tid' => $user['id']]);
    $myCourses = $stmt->fetchAll();

    // Overall stats for teacher
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.student_id) 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE c.teacher_id = :tid
    ");
    $stmt->execute(['tid' => $user['id']]);
    $totalTeacherStudents = (int)$stmt->fetchColumn();


    $stmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_sessions WHERE teacher_id = :tid");
    $stmt->execute(['tid' => $user['id']]);
    $totalTeacherLiveSessions = (int)$stmt->fetchColumn();

    if ($selectedCourseId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id AND teacher_id = :tid");
        $stmt->execute(['id' => $selectedCourseId, 'tid' => $user['id']]);
        $selectedCourse = $stmt->fetch() ?: null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST Actions
    // ─────────────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedCourse) {
        $action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

        if (in_array($action, ['add_live_session', 'edit_live_session', 'toggle_live_session', 'delete_live_session', 'add_live_question', 'delete_live_question', 'delete_all_live_questions', 'delete_live_participant', 'reset_live_session', 'toggle_live_pause'], true)) {
            clearLiveSessionCache();
        }

        // ── A. Ajouter un chapitre ─────────────────────────────────────
        if ($action === 'add_chapter') {
            $title = trim((string)($_POST['chapter_title'] ?? ''));
            if (!empty($title)) {
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM chapters WHERE course_id=:cid");
                $stmt->execute(['cid' => $selectedCourse['id']]);
                $maxSort = (int)$stmt->fetchColumn();

                $stmt = $pdo->prepare("INSERT INTO chapters (course_id,title,sort_order) VALUES (:cid,:title,:so)");
                $stmt->execute(['cid' => $selectedCourse['id'], 'title' => $title, 'so' => $maxSort + 1]);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=chapter_added#tab-course"); exit;
            }
        }

        // ── B. Éditer un chapitre ──────────────────────────────────────
        if ($action === 'edit_chapter') {
            $chapterId = (int)($_POST['chapter_id'] ?? 0);
            $title     = trim((string)($_POST['chapter_title'] ?? ''));
            if ($chapterId > 0 && !empty($title)) {
                // Vérifier que le chapitre appartient bien à ce cours
                $stmt = $pdo->prepare("UPDATE chapters SET title=:title WHERE id=:id AND course_id=:cid");
                $stmt->execute(['title' => $title, 'id' => $chapterId, 'cid' => $selectedCourse['id']]);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=chapter_updated#tab-course"); exit;
            }
        }

        // ── C. Supprimer un chapitre ───────────────────────────────────
        if ($action === 'delete_chapter') {
            $chapterId = (int)($_POST['chapter_id'] ?? 0);
            if ($chapterId > 0) {
                $stmt = $pdo->prepare("DELETE FROM chapters WHERE id=:id AND course_id=:cid");
                $stmt->execute(['id' => $chapterId, 'cid' => $selectedCourse['id']]);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=chapter_deleted#tab-course"); exit;
            }
        }

        // ── D. Ajouter une leçon ───────────────────────────────────────
        if ($action === 'add_lesson') {
            $chapterId   = (int)($_POST['chapter_id'] ?? 0);
            $title       = trim((string)($_POST['lesson_title'] ?? ''));
            $contentType = (string)($_POST['content_type'] ?? 'text');
            $textContent = trim((string)($_POST['text_content'] ?? '')) ?: null;
            $isCompulsory = isset($_POST['is_compulsory']) ? (int)$_POST['is_compulsory'] : 1;
            $quizDeadline = !empty($_POST['quiz_deadline']) ? $_POST['quiz_deadline'] : null;
            $hasAssignment = !empty($_POST['has_assignment']) ? 1 : 0;
            $assignmentTitle = trim((string)($_POST['assignment_title'] ?? '')) ?: null;
            $assignmentType = (string)($_POST['assignment_type'] ?? 'both');
            $allowedFileTypes = trim((string)($_POST['allowed_file_types'] ?? 'pdf,docx')) ?: 'pdf,docx';
            $assignmentInstructions = trim((string)($_POST['assignment_instructions'] ?? '')) ?: null;
            $assignmentDeadline = !empty($_POST['assignment_deadline']) ? $_POST['assignment_deadline'] : null;
            $pdfPath     = null;

            if (in_array($contentType, ['pdf','mixed'], true) && !empty($_FILES['lesson_pdf']['name'])) {
                $pdfPath = handlePdfUpload($_FILES['lesson_pdf']);
            }

            $pdfData = null;
            if ($pdfPath !== null) {
                $pdfData = null;   // the copy lives in media_chunks (lib/MediaStore.php)
            }

            if (!empty($title) && $chapterId > 0) {
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM lessons WHERE chapter_id=:cid");
                $stmt->execute(['cid' => $chapterId]);
                $maxSort = (int)$stmt->fetchColumn();

                $stmt = $pdo->prepare("
                    INSERT INTO lessons (chapter_id,title,content_type,text_content,pdf_path,pdf_data,video_url,sort_order,is_compulsory,quiz_deadline,has_assignment,assignment_title,assignment_type,allowed_file_types,assignment_instructions,assignment_deadline)
                    VALUES (:cid,:title,:ct,:tc,:pp,:pd,NULL,:so,:ic,:qd,:ha,:at,:atype,:aft,:ai,:ad)
                ");
                $stmt->execute([
                    'cid' => $chapterId, 'title' => $title, 'ct' => $contentType,
                    'tc'  => $textContent, 'pp' => $pdfPath, 'pd' => $pdfData, 'so' => $maxSort + 1,
                    'ic'  => $isCompulsory,
                    'qd'  => $quizDeadline, 'ha' => $hasAssignment, 'at' => $assignmentTitle,
                    'atype' => $assignmentType, 'aft' => $allowedFileTypes,
                    'ai'  => $assignmentInstructions, 'ad' => $assignmentDeadline,
                ]);
                $lessonId = (int)$pdo->lastInsertId();

                // Vidéos initiales
                this_processNewVideos($pdo, $lessonId, $_POST);

                // Ressources initiales
                this_processNewResources($pdo, $lessonId, $_POST);

                require_once __DIR__ . '/../lib/LessonProgressionHelper.php';
                LessonProgressionHelper::handleLessonUpdate($pdo, $lessonId, true);

                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=lesson_added#tab-course"); exit;
            }
        }

        // ── E. Éditer une leçon ────────────────────────────────────────
        if ($action === 'edit_lesson') {
            $lessonId    = (int)($_POST['lesson_id'] ?? 0);
            $title       = trim((string)($_POST['lesson_title'] ?? ''));
            $contentType = (string)($_POST['content_type'] ?? 'text');
            $textContent = trim((string)($_POST['text_content'] ?? '')) ?: null;
            $isCompulsory = isset($_POST['is_compulsory']) ? (int)$_POST['is_compulsory'] : 1;
            $quizDeadline = !empty($_POST['quiz_deadline']) ? $_POST['quiz_deadline'] : null;
            $hasAssignment = !empty($_POST['has_assignment']) ? 1 : 0;
            $assignmentTitle = trim((string)($_POST['assignment_title'] ?? '')) ?: null;
            $assignmentType = (string)($_POST['assignment_type'] ?? 'both');
            $allowedFileTypes = trim((string)($_POST['allowed_file_types'] ?? 'pdf,docx')) ?: 'pdf,docx';
            $assignmentInstructions = trim((string)($_POST['assignment_instructions'] ?? '')) ?: null;
            $assignmentDeadline = !empty($_POST['assignment_deadline']) ? $_POST['assignment_deadline'] : null;
            $deletePdf   = !empty($_POST['delete_pdf']) && $_POST['delete_pdf'] === '1';

            if ($lessonId > 0 && !empty($title)) {
                // Vérifier ownership via chapter → course
                $stmt = $pdo->prepare("
                    SELECT l.id, l.pdf_path FROM lessons l
                    JOIN chapters ch ON ch.id = l.chapter_id
                    WHERE l.id=:lid AND ch.course_id=:cid
                ");
                $stmt->execute(['lid' => $lessonId, 'cid' => $selectedCourse['id']]);
                $existingLesson = $stmt->fetch();
                if ($existingLesson) {
                    // Nouveau PDF éventuellement uploadé (quel que soit le type)
                    $newPdf    = null;
                    $finalPdf  = $existingLesson['pdf_path']; // conserver par défaut
                    $pdfData   = null;
                    $updatePdfData = false;

                    if (!empty($_FILES['lesson_pdf']['name']) && $_FILES['lesson_pdf']['error'] === UPLOAD_ERR_OK) {
                        $uploaded = handlePdfUpload($_FILES['lesson_pdf']);
                        if ($uploaded) {
                            // Supprimer l'ancien fichier physique si existant
                            if ($finalPdf) {
                                MediaStore::delete($pdo, 'pdf', (string)$finalPdf);
                            }
                            $finalPdf = $uploaded;
                            $pdfData = null;   // the copy lives in media_chunks (lib/MediaStore.php)
                            $updatePdfData = true;
                        }
                    } elseif ($deletePdf) {
                        // Suppression explicite du PDF sans remplacement
                        if ($finalPdf) {
                            MediaStore::delete($pdo, 'pdf', (string)$finalPdf);
                        }
                        $finalPdf = null;
                        $pdfData = null;
                        $updatePdfData = true;
                    }

                    if ($updatePdfData) {
                        $stmt = $pdo->prepare("
                            UPDATE lessons SET title=:t,content_type=:ct,text_content=:tc,pdf_path=:pp,pdf_data=:pd,is_compulsory=:ic,quiz_deadline=:qd,has_assignment=:ha,assignment_title=:at,assignment_type=:atype,allowed_file_types=:aft,assignment_instructions=:ai,assignment_deadline=:ad
                            WHERE id=:id
                        ");
                        $stmt->execute([
                            't'=>$title,'ct'=>$contentType,'tc'=>$textContent,'pp'=>$finalPdf,'pd'=>$pdfData,'ic'=>$isCompulsory,'qd'=>$quizDeadline,
                            'ha'=>$hasAssignment,'at'=>$assignmentTitle,'atype'=>$assignmentType,'aft'=>$allowedFileTypes,'ai'=>$assignmentInstructions,'ad'=>$assignmentDeadline,'id'=>$lessonId
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE lessons SET title=:t,content_type=:ct,text_content=:tc,is_compulsory=:ic,quiz_deadline=:qd,has_assignment=:ha,assignment_title=:at,assignment_type=:atype,allowed_file_types=:aft,assignment_instructions=:ai,assignment_deadline=:ad
                            WHERE id=:id
                        ");
                        $stmt->execute([
                            't'=>$title,'ct'=>$contentType,'tc'=>$textContent,'ic'=>$isCompulsory,'qd'=>$quizDeadline,
                            'ha'=>$hasAssignment,'at'=>$assignmentTitle,'atype'=>$assignmentType,'aft'=>$allowedFileTypes,'ai'=>$assignmentInstructions,'ad'=>$assignmentDeadline,'id'=>$lessonId
                        ]);
                    }

                    // Nouvelles vidéos à ajouter
                    this_processNewVideos($pdo, $lessonId, $_POST);

                    // Nouvelles ressources
                    this_processNewResources($pdo, $lessonId, $_POST);

                    require_once __DIR__ . '/../lib/LessonProgressionHelper.php';
                    LessonProgressionHelper::handleLessonUpdate($pdo, $lessonId);

                    header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=lesson_updated#tab-course"); exit;
                }
            }
        }

        // ── F. Supprimer une leçon ─────────────────────────────────────
        if ($action === 'delete_lesson') {
            $lessonId = (int)($_POST['lesson_id'] ?? 0);
            if ($lessonId > 0) {
                $stmt = $pdo->prepare("
                    DELETE l FROM lessons l
                    JOIN chapters ch ON ch.id=l.chapter_id
                    WHERE l.id=:lid AND ch.course_id=:cid
                ");
                $stmt->execute(['lid' => $lessonId, 'cid' => $selectedCourse['id']]);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=lesson_deleted#tab-course"); exit;
            }
        }

        // ── G. Supprimer une vidéo de leçon ───────────────────────────
        if ($action === 'delete_lesson_video') {
            $videoId  = (int)($_POST['video_id'] ?? 0);
            $lessonId = (int)($_POST['lesson_id'] ?? 0);
            if ($videoId > 0) {
                // Vérifier ownership
                $stmt = $pdo->prepare("
                    SELECT lv.id FROM lesson_videos lv
                    JOIN lessons l ON l.id=lv.lesson_id
                    JOIN chapters ch ON ch.id=l.chapter_id
                    WHERE lv.id=:vid AND ch.course_id=:cid
                ");
                $stmt->execute(['vid' => $videoId, 'cid' => $selectedCourse['id']]);
                if ($stmt->fetch()) {
                    $pdo->prepare("DELETE FROM lesson_videos WHERE id=:id")->execute(['id' => $videoId]);
                    if ($lessonId > 0) {
                        require_once __DIR__ . '/../lib/LessonProgressionHelper.php';
                        LessonProgressionHelper::handleLessonUpdate($pdo, $lessonId);
                    }
                }
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&open_lesson={$lessonId}&success=video_deleted#tab-course"); exit;
            }
        }

        // ── H. Supprimer une ressource de leçon ───────────────────────
        if ($action === 'delete_lesson_resource') {
            $resourceId = (int)($_POST['resource_id'] ?? 0);
            $lessonId   = (int)($_POST['lesson_id'] ?? 0);
            if ($resourceId > 0) {
                $stmt = $pdo->prepare("
                    SELECT lr.id FROM lesson_resources lr
                    JOIN lessons l ON l.id=lr.lesson_id
                    JOIN chapters ch ON ch.id=l.chapter_id
                    WHERE lr.id=:rid AND ch.course_id=:cid
                ");
                $stmt->execute(['rid' => $resourceId, 'cid' => $selectedCourse['id']]);
                if ($stmt->fetch()) {
                    $pdo->prepare("DELETE FROM lesson_resources WHERE id=:id")->execute(['id' => $resourceId]);
                    if ($lessonId > 0) {
                        require_once __DIR__ . '/../lib/LessonProgressionHelper.php';
                        LessonProgressionHelper::handleLessonUpdate($pdo, $lessonId);
                    }
                }
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&open_lesson={$lessonId}&success=resource_deleted#tab-course"); exit;
            }
        }

        // ── H2. Bibliothèque du Cours : Ajouter une ressource ──────────
        if ($action === 'add_library_item') {
            $title       = trim((string)($_POST['library_title'] ?? ''));
            $category    = (string)($_POST['library_category'] ?? 'pdf');
            $description = trim((string)($_POST['library_description'] ?? '')) ?: null;
            $markdown    = trim((string)($_POST['library_content_markdown'] ?? '')) ?: null;
            $videoUrl    = trim((string)($_POST['library_video_url'] ?? '')) ?: null;
            $filePath    = null;
            $fileSize    = null;

            $allowedCategories = ['syllabus', 'pdf', 'video', 'guide', 'text_markdown', 'other'];
            if (!in_array($category, $allowedCategories, true)) {
                $category = 'pdf';
            }

            if (!empty($_FILES['library_file']['name'])) {
                $doc = MediaStore::saveDocument($pdo, $_FILES['library_file'], 'library', ['pdf', 'docx', 'doc', 'zip', 'png', 'jpg', 'jpeg'], 64 * 1024 * 1024, 'lib_');
                if ($doc['ok']) {
                    $filePath = $doc['file'];
                    $fileSize = $doc['size'];
                } else {
                    $libErr = $doc['error'] ?? 'upload';
                    header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&error=library_" . urlencode($libErr) . "#tab-library"); exit;
                }
            }

            if (!empty($title)) {
                $stmt = $pdo->prepare("
                    INSERT INTO course_library_items (course_id, title, category, description, content_markdown, file_path, file_size, video_url)
                    VALUES (:cid, :t, :cat, :d, :m, :fp, :fs, :vu)
                ");
                $stmt->execute([
                    'cid' => $selectedCourse['id'],
                    't'   => $title,
                    'cat' => $category,
                    'd'   => $description,
                    'm'   => $markdown,
                    'fp'  => $filePath,
                    'fs'  => $fileSize,
                    'vu'  => $videoUrl,
                ]);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=library_added#tab-library"); exit;
            }
        }

        // ── H3. Bibliothèque du Cours : Supprimer une ressource ────────
        if ($action === 'delete_library_item') {
            $itemId = (int)($_POST['library_item_id'] ?? 0);
            if ($itemId > 0) {
                $stmt = $pdo->prepare("SELECT file_path FROM course_library_items WHERE id = :id AND course_id = :cid");
                $stmt->execute(['id' => $itemId, 'cid' => $selectedCourse['id']]);
                $item = $stmt->fetch();
                if ($item) {
                    if (!empty($item['file_path'])) {
                        MediaStore::delete($pdo, 'library', (string)$item['file_path']);
                    }
                    $pdo->prepare("DELETE FROM course_library_items WHERE id = :id")->execute(['id' => $itemId]);
                }
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=library_deleted#tab-library"); exit;
            }
        }

        // ── I. Éditer le cours ─────────────────────────────────────────
        if ($action === 'edit_course') {
            $title       = trim((string)($_POST['course_title'] ?? ''));
            $description = trim((string)($_POST['course_description'] ?? ''));
            $enrollKey   = trim((string)($_POST['enrollment_key'] ?? '')) ?: null;
            $startDate   = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $endDate     = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $evalDeadline = !empty($_POST['eval_deadline']) ? $_POST['eval_deadline'] : null;
            $examMinutes = max(30, (int)($_POST['exam_duration_minutes'] ?? 90));

            if (!empty($title)) {
                $coverImage = null;
                $uploadError = null;
                if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    // Checked on the file itself, scaled to 1280 px, and copied into the database so it survives redeploys.
                    $coverRes = MediaStore::saveImage($pdo, $_FILES['cover_image'], 'cover', 1280, 8 * 1024 * 1024, 84);
                    if ($coverRes['ok']) {
                        $coverImage = $coverRes['file'];
                    } else {
                        $uploadError = match ($coverRes['error'] ?? '') {
                            'too_big'   => 'upload_size_error',
                            'not_image' => 'upload_ext_error',
                            'server'    => 'upload_move_error',
                            default     => 'upload_error',
                        };
                    }
                }

                if ($coverImage) {
                    $coverImageData = null;   // the copy lives in media_chunks now (lib/MediaStore.php)
                    $oldCover = $pdo->prepare("SELECT cover_image FROM courses WHERE id = :id AND teacher_id = :tid");
                    $oldCover->execute(['id' => $selectedCourse['id'], 'tid' => $user['id']]);
                    $oldCoverFile = (string)$oldCover->fetchColumn();
                    $stmt = $pdo->prepare("
                        UPDATE courses SET title=:t, description=:d, enrollment_key=:ek,
                            start_date=:sd, end_date=:ed, eval_deadline=:ev, exam_duration_minutes=:em,
                            cover_image=:ci, cover_image_data=:cid
                        WHERE id=:id AND teacher_id=:tid
                    ");
                    $stmt->execute([
                        't'=>$title,'d'=>$description,'ek'=>$enrollKey,
                        'sd'=>$startDate,'ed'=>$endDate,'ev'=>$evalDeadline,'em'=>$examMinutes,
                        'ci'=>$coverImage,
                        'cid'=>$coverImageData,
                        'id'=>$selectedCourse['id'],'tid'=>$user['id'],
                    ]);
                    if ($oldCoverFile !== '' && $oldCoverFile !== $coverImage) {
                        MediaStore::delete($pdo, 'cover', $oldCoverFile);
                    }
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE courses SET title=:t, description=:d, enrollment_key=:ek,
                            start_date=:sd, end_date=:ed, eval_deadline=:ev, exam_duration_minutes=:em
                        WHERE id=:id AND teacher_id=:tid
                    ");
                    $stmt->execute([
                        't'=>$title,'d'=>$description,'ek'=>$enrollKey,
                        'sd'=>$startDate,'ed'=>$endDate,'ev'=>$evalDeadline,'em'=>$examMinutes,
                        'id'=>$selectedCourse['id'],'tid'=>$user['id'],
                    ]);
                }
                $errParam = $uploadError ? "&error=" . urlencode($uploadError) : "";
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=course_updated" . $errParam . "#tab-settings"); exit;
            }
        }

        // ── J. Ajouter une question de leçon ──────────────────────────
        if ($action === 'add_lesson_question') {
            $lessonId     = (int)($_POST['lesson_id'] ?? 0);
            $questionText = trim((string)($_POST['question_text'] ?? ''));
            $optionA      = trim((string)($_POST['option_a'] ?? ''));
            $optionB      = trim((string)($_POST['option_b'] ?? ''));
            $optionC      = trim((string)($_POST['option_c'] ?? ''));
            $optionD      = trim((string)($_POST['option_d'] ?? ''));
            $correct      = trim((string)($_POST['correct_option'] ?? ''));

            if ($lessonId > 0 && !empty($questionText) && !empty($optionA) && !empty($optionB)
                && !empty($optionC) && !empty($optionD) && in_array($correct, ['A','B','C','D'], true)) {
                $stmt = $pdo->prepare("
                    INSERT INTO lesson_questions (lesson_id,question_text,option_a,option_b,option_c,option_d,correct_option)
                    VALUES (:lid,:qt,:a,:b,:c,:d,:co)
                ");
                $stmt->execute(['lid'=>$lessonId,'qt'=>$questionText,'a'=>$optionA,'b'=>$optionB,'c'=>$optionC,'d'=>$optionD,'co'=>$correct]);

                require_once __DIR__ . '/../lib/LessonProgressionHelper.php';
                LessonProgressionHelper::handleLessonUpdate($pdo, $lessonId);

                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=lesson_question_added#tab-course"); exit;
            }
        }

        // ── K. Ajouter une question de certification ───────────────────
        if ($action === 'add_course_question') {
            $questionText = trim((string)($_POST['question_text'] ?? ''));
            $optionA      = trim((string)($_POST['option_a'] ?? ''));
            $optionB      = trim((string)($_POST['option_b'] ?? ''));
            $optionC      = trim((string)($_POST['option_c'] ?? ''));
            $optionD      = trim((string)($_POST['option_d'] ?? ''));
            $correct      = trim((string)($_POST['correct_option'] ?? ''));

            if (!empty($questionText) && !empty($optionA) && !empty($optionB) && !empty($optionC)
                && !empty($optionD) && in_array($correct, ['A','B','C','D'], true)) {
                $stmt = $pdo->prepare("
                    INSERT INTO course_questions (course_id,question_text,option_a,option_b,option_c,option_d,correct_option)
                    VALUES (:cid,:qt,:a,:b,:c,:d,:co)
                ");
                $stmt->execute(['cid'=>$selectedCourse['id'],'qt'=>$questionText,'a'=>$optionA,'b'=>$optionB,'c'=>$optionC,'d'=>$optionD,'co'=>$correct]);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=course_question_added#tab-course"); exit;
            }
        }

        // ── L. Supprimer une question de certification ─────────────────
        if ($action === 'delete_course_question') {
            $qid = (int)($_POST['question_id'] ?? 0);
            if ($qid > 0) {
                $stmt = $pdo->prepare("DELETE FROM course_questions WHERE id=:id AND course_id=:cid");
                $stmt->execute(['id'=>$qid,'cid'=>$selectedCourse['id']]);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=course_question_deleted#tab-course"); exit;
            }
        }

        // ── M. Ajouter une séance de téléévaluation ───────────────────
        if ($action === 'add_live_session') {
            $title = trim((string)($_POST['live_title'] ?? ''));
            $startTime = trim((string)($_POST['live_start_time'] ?? ''));
            $endTime = trim((string)($_POST['live_end_time'] ?? ''));
            $limit = (int)($_POST['default_time_limit'] ?? 30);

            if (!empty($title) && !empty($startTime) && !empty($endTime)) {
                $code = bin2hex(random_bytes(8));
                $stmt = $pdo->prepare("
                    INSERT INTO live_eval_sessions (course_id, teacher_id, title, session_code, start_time, end_time, default_time_limit, status, shuffle_options, integrity_watch)
                    VALUES (:cid, :tid, :title, :code, :start, :end, :limit, 0, :shuf, :watch)
                ");
                $stmt->execute([
                    'cid'   => $selectedCourse['id'],
                    'tid'   => $teacherId,
                    'title' => $title,
                    'code'  => $code,
                    'start' => $startTime,
                    'end'   => $endTime,
                    'limit' => $limit,
                    'shuf'  => isset($_POST['shuffle_options']) ? 1 : 0,
                    'watch' => isset($_POST['integrity_watch']) ? 1 : 0,
                ]);
                $newSessionId = (int)$pdo->lastInsertId();
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_session_created&open_session={$newSessionId}#tab-live-eval"); exit;
            }
        }

        // ── Modifier une séance de téléévaluation ───────────────────
        if ($action === 'edit_live_session') {
            $sid = (int)($_POST['session_id'] ?? 0);
            $title = trim((string)($_POST['live_title'] ?? ''));
            $startTime = trim((string)($_POST['live_start_time'] ?? ''));
            $endTime = trim((string)($_POST['live_end_time'] ?? ''));
            $limit = (int)($_POST['default_time_limit'] ?? 30);
            $isAsync = isset($_POST['is_async']) ? 1 : 0;
            $asyncDeadline = isset($_POST['async_deadline']) && $_POST['async_deadline'] !== '' ? trim((string)$_POST['async_deadline']) : null;

            if ($sid > 0 && !empty($title) && !empty($startTime) && !empty($endTime)) {
                $stmt = $pdo->prepare("
                    UPDATE live_eval_sessions 
                    SET title = :title, start_time = :start, end_time = :end, default_time_limit = :limit,
                        is_async = :is_async, async_deadline = :async_deadline, status = 1,
                        shuffle_options = :shuf, integrity_watch = :watch
                    WHERE id = :id AND teacher_id = :tid
                ");
                $stmt->execute([
                    'title' => $title,
                    'start' => $startTime,
                    'end'   => $endTime,
                    'limit' => $limit,
                    'is_async' => $isAsync,
                    'async_deadline' => $asyncDeadline,
                    'shuf'  => isset($_POST['shuffle_options']) ? 1 : 0,
                    'watch' => isset($_POST['integrity_watch']) ? 1 : 0,
                    'id'    => $sid,
                    'tid'   => $teacherId
                ]);
                // A session that was moved is announced again; an unchanged one is not (see LiveSmsNotifier)
                $smsParam = notifyStudentsOfSession($pdo, $sid, $teacherId);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_session_updated{$smsParam}&open_session={$sid}#tab-live-eval"); exit;
            }
        }

        // ── N. Activer/Désactiver une séance de téléévaluation ────────
        if ($action === 'toggle_live_session') {
            $sid = (int)($_POST['session_id'] ?? 0);
            $status = (int)($_POST['status'] ?? 0) === 1 ? 1 : 0;
            if ($sid > 0) {
                $stmt = $pdo->prepare("UPDATE live_eval_sessions SET status=:status WHERE id=:id AND teacher_id=:tid");
                $stmt->execute(['status' => $status, 'id' => $sid, 'tid' => $teacherId]);
                // Activating a session tells the enrolled students by SMS (once per version of the session)
                $smsParam = '';
                if ($status === 1) {
                    $smsParam = notifyStudentsOfSession($pdo, $sid, $teacherId);
                }
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_session_toggled{$smsParam}&open_session={$sid}#tab-live-eval"); exit;
            }
        }

        // ── O. Supprimer une séance de téléévaluation ─────────────────
        if ($action === 'delete_live_session') {
            $sid = (int)($_POST['session_id'] ?? 0);
            if ($sid > 0) {
                $stmt = $pdo->prepare("DELETE FROM live_eval_sessions WHERE id=:id AND teacher_id=:tid");
                $stmt->execute(['id' => $sid, 'tid' => $teacherId]);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_session_deleted#tab-live-eval"); exit;
            }
        }

        // ── O2. Supprimer/Exclure un participant d'une séance ───────────
        if ($action === 'delete_live_participant') {
            $regId = (int)($_POST['registration_id'] ?? 0);
            $sid   = (int)($_POST['session_id'] ?? 0);
            if ($regId > 0 && $sid > 0) {
                $stmt = $pdo->prepare("SELECT id FROM live_eval_sessions WHERE id = :sid AND teacher_id = :tid");
                $stmt->execute(['sid' => $sid, 'tid' => $teacherId]);
                if ($stmt->fetch()) {
                    $delStmt = $pdo->prepare("DELETE FROM live_eval_registrations WHERE id = :id AND session_id = :sid");
                    $delStmt->execute(['id' => $regId, 'sid' => $sid]);
                    header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_participant_deleted&open_session={$sid}#tab-live-eval");
                    exit;
                }
            }
        }

        // ── O3. Réinitialiser la séance (reset exam) ─────────────────────
        if ($action === 'reset_live_session') {
            $sid = (int)($_POST['session_id'] ?? 0);
            if ($sid > 0) {
                $stmt = $pdo->prepare("SELECT id FROM live_eval_sessions WHERE id = :sid AND teacher_id = :tid");
                $stmt->execute(['sid' => $sid, 'tid' => $teacherId]);
                if ($stmt->fetch()) {
                    // Supprimer toutes les réponses des participants de cette session
                    $stmtDelAnswers = $pdo->prepare("
                        DELETE FROM live_eval_answers 
                        WHERE registration_id IN (
                            SELECT id FROM live_eval_registrations WHERE session_id = :sid
                        )
                    ");
                    $stmtDelAnswers->execute(['sid' => $sid]);
                    
                    // Supprimer les participants
                    $stmtDelRegs = $pdo->prepare("DELETE FROM live_eval_registrations WHERE session_id = :sid");
                    $stmtDelRegs->execute(['sid' => $sid]);
                    
                    // Réinitialiser les champs de pause
                    $stmtResetSession = $pdo->prepare("
                        UPDATE live_eval_sessions 
                        SET is_paused = 0, paused_at = NULL, pause_duration = 0 
                        WHERE id = :sid
                    ");
                    $stmtResetSession->execute(['sid' => $sid]);
                    
                    header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_session_reset&open_session={$sid}#tab-live-eval");
                    exit;
                }
            }
        }

        // ── O4. Mettre en pause ou Reprendre la séance ───────────────────
        if ($action === 'toggle_live_pause') {
            $sid = (int)($_POST['session_id'] ?? 0);
            if ($sid > 0) {
                $stmt = $pdo->prepare("SELECT id, is_paused, paused_at, pause_duration FROM live_eval_sessions WHERE id = :sid AND teacher_id = :tid");
                $stmt->execute(['sid' => $sid, 'tid' => $teacherId]);
                $session = $stmt->fetch();
                if ($session) {
                    if ((int)$session['is_paused'] === 1) {
                        // Actuellement en pause -> on reprend
                        $pausedAt = $session['paused_at'];
                        $addedPause = 0;
                        if ($pausedAt) {
                            $addedPause = time() - strtotime($pausedAt);
                            if ($addedPause < 0) $addedPause = 0;
                        }
                        $newPauseDuration = (int)$session['pause_duration'] + $addedPause;
                        
                        $update = $pdo->prepare("
                            UPDATE live_eval_sessions 
                            SET is_paused = 0, paused_at = NULL, pause_duration = :pd 
                            WHERE id = :sid
                        ");
                        $update->execute(['pd' => $newPauseDuration, 'sid' => $sid]);
                        
                        header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_session_resumed&open_session={$sid}#tab-live-eval");
                        exit;
                    } else {
                        // Actuellement en cours -> on met en pause
                        $update = $pdo->prepare("
                            UPDATE live_eval_sessions 
                            SET is_paused = 1, paused_at = NOW() 
                            WHERE id = :sid
                        ");
                        $update->execute(['sid' => $sid]);
                        
                        header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_session_paused&open_session={$sid}#tab-live-eval");
                        exit;
                    }
                }
            }
        }

        // ── P. Ajouter une question à une téléévaluation ──────────────
        if ($action === 'add_live_question') {
            $sid          = (int)($_POST['session_id'] ?? 0);
            $questionText = trim((string)($_POST['question_text'] ?? ''));
            $qType        = trim((string)($_POST['question_type'] ?? 'mcq'));
            $optionA      = trim((string)($_POST['option_a'] ?? ''));
            $optionB      = trim((string)($_POST['option_b'] ?? ''));
            $optionC      = trim((string)($_POST['option_c'] ?? ''));
            $optionD      = trim((string)($_POST['option_d'] ?? ''));
            $correct      = trim((string)($_POST['correct_option'] ?? ''));
            $explanation  = trim((string)($_POST['explanation'] ?? ''));
            $timeLimit    = trim((string)($_POST['time_limit'] ?? ''));
            
            $isValid = false;
            if ($sid > 0 && !empty($questionText) && !empty($correct)) {
                if ($qType === 'written') {
                    $isValid = true;
                } elseif (!empty($optionA) && !empty($optionB) && !empty($optionC) && !empty($optionD)) {
                    $isValid = true;
                }
            }
            
            if ($isValid) {
                // The picture is checked as a picture, scaled to 1600 px, and copied into the database so it is still
                // there after a redeploy. It is served to the exam room through /download.php?type=live_question.
                $imagePath = null;
                if (!empty($_FILES['live_image']) && ($_FILES['live_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $imgRes = MediaStore::saveImage($pdo, $_FILES['live_image'], 'live_question', 1600, 8 * 1024 * 1024, 86);
                    if ($imgRes['ok']) {
                        $imagePath = $imgRes['file'];
                    } else {
                        header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&error=live_image_" . urlencode($imgRes['error'] ?? 'upload') . "&open_session={$sid}#tab-live-eval"); exit;
                    }
                }

                $tLimit = ($timeLimit === '') ? null : (int)$timeLimit;

                $stmt = $pdo->prepare("
                    INSERT INTO live_eval_questions (session_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_option, explanation, time_limit, image_path)
                    VALUES (:sid, :qt, :type, :a, :b, :c, :d, :co, :exp, :limit, :img)
                ");
                $stmt->execute([
                    'sid'   => $sid,
                    'qt'    => $questionText,
                    'type'  => $qType,
                    'a'     => $qType === 'written' ? '' : $optionA,
                    'b'     => $qType === 'written' ? '' : $optionB,
                    'c'     => $qType === 'written' ? '' : $optionC,
                    'd'     => $qType === 'written' ? '' : $optionD,
                    'co'    => $correct,
                    'exp'   => $explanation,
                    'limit' => $tLimit,
                    'img'   => $imagePath
                ]);

                $sidForRedirect = (int)($_POST['session_id'] ?? 0);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_question_added&open_session={$sidForRedirect}#tab-live-eval"); exit;
            }
        }

        // ── Q. Supprimer une question de téléévaluation ────────────────
        if ($action === 'delete_live_question') {
            $qid = (int)($_POST['question_id'] ?? 0);
            if ($qid > 0) {
                $stmt = $pdo->prepare("
                    SELECT q.image_path, s.course_id 
                    FROM live_eval_questions q
                    JOIN live_eval_sessions s ON q.session_id = s.id
                    WHERE q.id = :id AND s.teacher_id = :tid
                ");
                $stmt->execute(['id' => $qid, 'tid' => $teacherId]);
                $qData = $stmt->fetch();
                if ($qData) {
                    if ($qData['image_path']) {
                        MediaStore::delete($pdo, 'live_question', (string)$qData['image_path']);
                    }
                    $del = $pdo->prepare("DELETE FROM live_eval_questions WHERE id = :id");
                    $del->execute(['id' => $qid]);
                    header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_question_deleted#tab-live-eval"); exit;
                }
            }
        }

        // ── Q2. Supprimer TOUTES les questions d'une séance de téléévaluation ──
        if ($action === 'delete_all_live_questions') {
            $sid = (int)($_POST['session_id'] ?? 0);
            if ($sid > 0) {
                $stmt = $pdo->prepare("
                    SELECT s.id 
                    FROM live_eval_sessions s
                    WHERE s.id = :id AND s.teacher_id = :tid AND s.course_id = :cid
                ");
                $stmt->execute(['id' => $sid, 'tid' => $teacherId, 'cid' => $selectedCourse['id']]);
                if ($stmt->fetch()) {
                    $imgStmt = $pdo->prepare("SELECT image_path FROM live_eval_questions WHERE session_id = :sid");
                    $imgStmt->execute(['sid' => $sid]);
                    $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($images as $img) {
                        if ($img) {
                            MediaStore::delete($pdo, 'live_question', (string)$img);
                        }
                    }
                    
                    $del = $pdo->prepare("DELETE FROM live_eval_questions WHERE session_id = :sid");
                    $del->execute(['sid' => $sid]);
                    
                    header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_all_questions_deleted&open_session={$sid}#tab-live-eval"); exit;
                }
            }
        }

        // Webinaires actions removed
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fetch data for rendering
    // ─────────────────────────────────────────────────────────────────────
    $chapters              = [];
    $finalExamQuestionCount = 0;
    $finalQuestions        = [];
    $liveSessions          = [];

    $courseComments        = [];

    if ($selectedCourse) {
        $stmt = $pdo->prepare("SELECT * FROM chapters WHERE course_id=:cid ORDER BY sort_order ASC,id ASC");
        $stmt->execute(['cid' => $selectedCourse['id']]);
        $chapters = $stmt->fetchAll();

        foreach ($chapters as &$chapter) {
            $stmt = $pdo->prepare("SELECT * FROM lessons WHERE chapter_id=:cid ORDER BY sort_order ASC,id ASC");
            $stmt->execute(['cid' => $chapter['id']]);
            $chapter['lessons'] = $stmt->fetchAll();

            foreach ($chapter['lessons'] as &$lesson) {
                // Question count
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_questions WHERE lesson_id=:lid");
                $stmt->execute(['lid' => $lesson['id']]);
                $lesson['question_count'] = (int)$stmt->fetchColumn();

                // Videos
                $stmt = $pdo->prepare("SELECT * FROM lesson_videos WHERE lesson_id=:lid ORDER BY sort_order ASC,id ASC");
                $stmt->execute(['lid' => $lesson['id']]);
                $lesson['videos'] = $stmt->fetchAll();

                // Resources
                $stmt = $pdo->prepare("SELECT * FROM lesson_resources WHERE lesson_id=:lid ORDER BY sort_order ASC,id ASC");
                $stmt->execute(['lid' => $lesson['id']]);
                $lesson['resources'] = $stmt->fetchAll();

                // Comments / Q&A
                $stmt = $pdo->prepare("
                    SELECT lc.*, u.name AS author_name, u.role AS author_role
                    FROM lesson_comments lc
                    JOIN users u ON u.id = lc.user_id
                    WHERE lc.lesson_id = :lid
                    ORDER BY lc.created_at ASC
                ");
                $stmt->execute(['lid' => $lesson['id']]);
                $lesson['comments'] = $stmt->fetchAll();
            }
            unset($lesson);
        }
        unset($chapter);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_questions WHERE course_id=:cid");
        $stmt->execute(['cid' => $selectedCourse['id']]);
        $finalExamQuestionCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM course_questions WHERE course_id=:cid ORDER BY id ASC");
        $stmt->execute(['cid' => $selectedCourse['id']]);
        $finalQuestions = $stmt->fetchAll();

        // Charger les séances de téléévaluation
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   (SELECT COUNT(*) FROM live_eval_questions WHERE session_id = s.id) AS question_count,
                   (SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = s.id) AS participant_count,
                   (SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = s.id AND last_activity >= NOW() - INTERVAL 10 SECOND) AS online_count
            FROM live_eval_sessions s
            WHERE s.course_id = :cid AND s.teacher_id = :tid
            ORDER BY s.created_at DESC
        ");
        $stmt->execute(['cid' => $selectedCourse['id'], 'tid' => $teacherId]);
        $liveSessions = $stmt->fetchAll();

        foreach ($liveSessions as &$ls) {
            $stmt = $pdo->prepare("SELECT * FROM live_eval_questions WHERE session_id = :sid ORDER BY sort_order ASC, id ASC");
            $stmt->execute(['sid' => $ls['id']]);
            $ls['questions'] = $stmt->fetchAll();

            $totalDuration = 0;
            foreach ($ls['questions'] as $q) {
                $limit = $q['time_limit'] !== null ? (int)$q['time_limit'] : (int)$ls['default_time_limit'];
                $totalDuration += $limit;
            }
            $sessionStart = strtotime($ls['start_time']);
            $sessionEnd = $sessionStart + $totalDuration;
            $isSessionAsync = isset($ls['is_async']) && (int)$ls['is_async'] === 1;
            if ($isSessionAsync) {
                $ls['is_finished'] = !empty($ls['async_deadline']) && (time() >= strtotime($ls['async_deadline']));
            } else {
                $ls['is_finished'] = (time() >= $sessionEnd || time() >= strtotime($ls['end_time']));
            }
        }
        unset($ls);



        // Charger les commentaires globaux du cours
        $stmt = $pdo->prepare("
            SELECT lc.*, u.name AS author_name, u.role AS author_role, l.title AS lesson_title
            FROM lesson_comments lc
            JOIN users u ON u.id = lc.user_id
            JOIN lessons l ON l.id = lc.lesson_id
            JOIN chapters ch ON ch.id = l.chapter_id
            WHERE ch.course_id = :cid
            ORDER BY lc.created_at DESC
        ");
        $stmt->execute(['cid' => $selectedCourse['id']]);
        $courseComments = $stmt->fetchAll();

        // Charger les éléments de la bibliothèque du cours
        $courseLibraryItems = [];
        try {
            $stmt = $pdo->prepare("SELECT * FROM course_library_items WHERE course_id = :cid ORDER BY created_at DESC");
            $stmt->execute(['cid' => $selectedCourse['id']]);
            $courseLibraryItems = $stmt->fetchAll();
        } catch (Throwable $e) {
            $courseLibraryItems = [];
        }
    }

    // Charger toutes les leçons ayant la fonctionnalité de devoir activée (has_assignment = 1)
    // ainsi que toutes les soumissions associées, ordonnées par Chapitre -> Leçon -> Date de soumission
    $asgLessonsStmt = $pdo->prepare("
        SELECT 
            l.*,
            ch.id AS chapter_id,
            ch.title AS chapter_title,
            ch.sort_order AS chapter_sort,
            c.id AS course_id,
            c.title AS course_title
        FROM lessons l
        JOIN chapters ch ON ch.id = l.chapter_id
        JOIN courses c ON c.id = ch.course_id
        WHERE l.has_assignment = 1 
          AND c.teacher_id = :tid " . ($selectedCourse ? " AND c.id = " . (int)$selectedCourse['id'] : "") . "
        ORDER BY ch.sort_order ASC, ch.id ASC, l.sort_order ASC, l.id ASC
    ");
    $asgLessonsStmt->execute(['tid' => $teacherId]);
    $configuredAssignmentLessons = $asgLessonsStmt->fetchAll(PDO::FETCH_ASSOC);

    $teacherAssignments = [];
    foreach ($configuredAssignmentLessons as &$asgLes) {
        $subStmt = $pdo->prepare("
            SELECT 
                las.id,
                las.lesson_id,
                las.student_id,
                las.student_name AS declared_student_name,
                las.student_matricule,
                las.submission_type,
                las.submitted_file_path,
                las.submitted_file_name,
                las.submitted_link,
                las.student_comment,
                las.submitted_at,
                u.name AS student_name,
                u.email AS student_email,
                u.avatar_path AS student_avatar
            FROM lesson_assignment_submissions las
            JOIN users u ON u.id = las.student_id
            WHERE las.lesson_id = :lid
            ORDER BY las.submitted_at DESC
        ");
        $subStmt->execute(['lid' => $asgLes['id']]);
        $asgLes['submissions'] = $subStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($asgLes['submissions'] as $sItem) {
            $sItem['lesson_title'] = $asgLes['title'];
            $sItem['course_title'] = $asgLes['course_title'];
            $teacherAssignments[] = $sItem;
        }
    }
    unset($asgLes);

} catch (PDOException $e) {
    dieSafe('Erreur serveur. Veuillez réessayer.', $e, 'teacher/dashboard');
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper functions for video/resource insertion (called inside POST block above)
// ─────────────────────────────────────────────────────────────────────────────
function this_processNewVideos(PDO $pdo, int $lessonId, array $post): void {
    $urls   = (array)($post['new_video_urls']   ?? []);
    $labels = (array)($post['new_video_labels'] ?? []);

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM lesson_videos WHERE lesson_id=:lid");
    $stmt->execute(['lid' => $lessonId]);
    $maxSort = (int)$stmt->fetchColumn();

    $ins = $pdo->prepare("INSERT INTO lesson_videos (lesson_id,label,url,sort_order) VALUES (:lid,:label,:url,:so)");
    foreach ($urls as $i => $url) {
        $url = trim($url);
        if (empty($url)) continue;
        $label = trim($labels[$i] ?? '') ?: 'Vidéo ' . ($i + 1);
        $ins->execute(['lid'=>$lessonId,'label'=>$label,'url'=>$url,'so'=>$maxSort + $i + 1]);
    }
}

function this_processNewResources(PDO $pdo, int $lessonId, array $post): void {
    $urls   = (array)($post['new_resource_urls']   ?? []);
    $labels = (array)($post['new_resource_labels'] ?? []);
    $types  = (array)($post['new_resource_types']  ?? []);

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM lesson_resources WHERE lesson_id=:lid");
    $stmt->execute(['lid' => $lessonId]);
    $maxSort = (int)$stmt->fetchColumn();

    $allowed = ['link','file','reference'];
    $ins = $pdo->prepare("INSERT INTO lesson_resources (lesson_id,label,url,resource_type,sort_order) VALUES (:lid,:label,:url,:rt,:so)");
    foreach ($urls as $i => $url) {
        $url = trim($url);
        if (empty($url)) continue;
        $label = trim($labels[$i] ?? '') ?: 'Ressource ' . ($i + 1);
        $type  = in_array($types[$i] ?? '', $allowed, true) ? $types[$i] : 'link';
        $ins->execute(['lid'=>$lessonId,'label'=>$label,'url'=>$url,'rt'=>$type,'so'=>$maxSort + $i + 1]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers view
// ─────────────────────────────────────────────────────────────────────────────
// Leçon à ouvrir automatiquement après une action (ex: suppression vidéo)
$openLessonId = isset($_GET['open_lesson']) ? (int)$_GET['open_lesson'] : 0;

$successMessages = [
    'chapter_added'          => '✓ Chapitre créé avec succès.',
    'chapter_updated'        => '✓ Chapitre mis à jour.',
    'chapter_deleted'        => '✓ Chapitre supprimé.',
    'lesson_added'           => '✓ Leçon ajoutée avec succès.',
    'lesson_updated'         => '✓ Leçon mise à jour.',
    'lesson_deleted'         => '✓ Leçon supprimée.',
    'video_deleted'          => '✓ Vidéo supprimée.',
    'resource_deleted'       => '✓ Ressource supprimée.',
    'lesson_question_added'  => '✓ Question de leçon enregistrée.',
    'course_question_added'  => '✓ Question de certification enregistrée.',
    'course_question_deleted'=> '✓ Question de certification supprimée.',
    'questions_imported'     => '✓ Questions importées avec succès.',
    'course_updated'         => '✓ Informations du cours mises à jour.',
    'course_created'         => '✓ Cours créé avec succès. Le promoteur a été informé.',
    'live_all_questions_deleted' => '✓ Toutes les questions de la séance ont été supprimées.',

];
$successKey = (string)($_GET['success'] ?? '');
$successMsg = $successMessages[$successKey] ?? null;

// =========================================================================
// SECTION 4: HTML PORTAL VIEW LAYOUT & PRESENTATION
// =========================================================================
?>
<?php
// ─── View helpers (presentation only) ────────────────────────────────────
if (!class_exists('Brand')) { require_once __DIR__ . '/../lib/Brand.php'; }
$tdLang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$tdDict = require __DIR__ . '/../locales/teacher-ui.php';
function td(string $key, array $r = []): string {
    global $tdDict, $tdLang;
    $s = $tdDict[$tdLang][$key] ?? $tdDict['fr'][$key] ?? $key;
    foreach ($r as $k => $v) { $s = str_replace('{' . $k . '}', (string)$v, $s); }
    return $s;
}
function tde(string $key, array $r = []): string { return htmlspecialchars(td($key, $r), ENT_QUOTES, 'UTF-8'); }
function tdIcon(string $name): string {
    static $p = [
        'today'   => 'M3 11.5L12 4l9 7.5M5.5 10v9.5h13V10',
        'outline' => 'M4 5.5C5.5 4.5 8 4.3 12 6.5c4-2.2 6.5-2 8-1v13c-1.5-1-4-1.2-8 1-4-2.2-6.5-2-8-1v-13zM12 6.5V19.5',
        'library' => 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
        'live'    => 'M15 10l5-3v10l-5-3M4 6h9a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2z',
        'grades'  => 'M4 20V11M10 20V4M16 20v-6M21 20H3',
        'assign'  => 'M8 3.5h6.5L19 8v12.5H8zM14 3.5V8h5M11 13h5M11 16.5h5',
        'qa'      => 'M4 5.5h16v10H9.5L5 19.5v-4H4z',
        'menu'    => 'M4 7h16M4 12h16M4 17h16',
        'bell'    => 'M6 9a6 6 0 0112 0c0 5 2 6.5 2 6.5H4S6 14 6 9zM10 19a2 2 0 004 0',
        'moon'    => 'M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z',
        'sun'     => 'M12 8a4 4 0 100 8 4 4 0 000-8zM12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4',
        'logout'  => 'M14 4h4a2 2 0 012 2v12a2 2 0 01-2 2h-4M10 8l-4 4 4 4M6 12h11',
        'plus'    => 'M12 5v14M5 12h14',
        'download'=> 'M12 4v10m0 0l-4-4m4 4l4-4M5 19h14',
        'x'       => 'M6 6l12 12M18 6L6 18',
        'play'    => 'M8 5l11 7-11 7z',
        'pause'   => 'M9 5v14M15 5v14',
        'reset'   => 'M4 12a8 8 0 108-8H7M7 4L4 7l3 3',
        'edit'    => 'M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4',
        'trash'   => 'M5 7h14M10 7V4.5h4V7M7 7l.8 12h8.4L17 7M10 11v5M14 11v5',
        'share'   => 'M18 8a3 3 0 100-6 3 3 0 000 6zM6 15a3 3 0 100-6 3 3 0 000 6zM18 22a3 3 0 100-6 3 3 0 000 6zM8.7 10.7l6.6-3.4M8.7 13.3l6.6 3.4',
        'mail'    => 'M4 6h16v12H4zM4 7l8 6 8-6',
        'chev'    => 'M6 9l6 6 6-6',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($p[$name] ?? '') . '"/></svg>';
}

// Live-session state for the control room and the "today" list.
function tdSessionState(array $s): string {
    $now = time();
    if (!empty($s['is_finished'])) return 'ended';
    if ((int)$s['status'] !== 1) return 'off';
    if (strtotime($s['start_time']) > $now) return 'wait';
    if (empty($s['is_async']) && (int)($s['is_paused'] ?? 0) === 1) return 'paused';
    return 'live';
}
$successMsg = ($successKey !== '' && isset($tdDict['fr']['ok_' . $successKey])) ? td('ok_' . $successKey) : null;
function tdExportGroup(string $tableId, string $name): string {
    return '<div class="t-export" role="group" aria-label="' . tde('export') . '"><span>' . tde('export') . '</span>'
        . '<button type="button" data-export="csv" data-table="' . $tableId . '" data-name="' . $name . '">CSV</button>'
        . '<button type="button" data-export="xlsx" data-table="' . $tableId . '" data-name="' . $name . '">Excel</button>'
        . '<button type="button" data-export="pdf" data-table="' . $tableId . '" data-name="' . $name . '">PDF</button></div>';
}

// English: translate the legacy French literals still present in older markup / JS (see locales/teacher-legacy-en.php).
if ($tdLang === 'en') {
    $tdLegacy = require __DIR__ . '/../locales/teacher-legacy-en.php';
    foreach ($tdLegacy as $k => $v) {
        if (strpos($k, "'") !== false) { $tdLegacy[str_replace("'", "\\'", $k)] = str_replace("'", "\\'", $v); }
    }
    uksort($tdLegacy, fn($a, $b) => strlen($b) <=> strlen($a));
    $tdLegacyRe = '/(?<![\p{L}\p{N}_])(' . implode('|', array_map(fn($k) => preg_quote($k, '/'), array_keys($tdLegacy))) . ')(?![\p{L}\p{N}_])/u';
    ob_start(function ($html) use ($tdLegacy, $tdLegacyRe) {
        return preg_replace_callback($tdLegacyRe, fn($m) => $tdLegacy[$m[1]] ?? $m[0], $html) ?? $html;
    });
}
$tdNow = time();
$tdLiveNow = []; $tdUpcoming = [];
foreach (($liveSessions ?? []) as $_s) {
    $st = tdSessionState($_s);
    if ($st === 'live' || $st === 'paused') $tdLiveNow[] = $_s;
    elseif ($st === 'wait' || $st === 'off') $tdUpcoming[] = $_s;
}
usort($tdUpcoming, fn($a, $b) => strtotime($a['start_time']) <=> strtotime($b['start_time']));
$tdUnanswered = 0;
foreach (($courseComments ?? []) as $_c) {
    if (($_c['author_role'] ?? '') === 'student' && empty($_c['teacher_reply']) && (int)$_c['is_hidden'] === 0) $tdUnanswered++;
}
$tdNewSubs = 0;
foreach (($teacherAssignments ?? []) as $_a) {
    if (!empty($_a['submitted_at']) && strtotime($_a['submitted_at']) >= $tdNow - 7 * 86400) $tdNewSubs++;
}
$tdFirstName = trim(explode(' ', (string)($user['name'] ?? ''))[0] ?? '');
$tdInitial = mb_strtoupper(mb_substr((string)($user['name'] ?? 'E'), 0, 1));
$tdHasCourse = (bool)$selectedCourse;
?>
<!DOCTYPE html>
<html lang="<?= $tdLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#F5F0E6">
    <title><?= tde('page_title') ?></title>
    <?= Brand::headLinks() ?>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png" sizes="32x32">
    <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/sv2.css">
    <link rel="stylesheet" href="/assets/css/teacher.css">

    <!-- KaTeX: formulas in questions and lessons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script>
        document.documentElement.classList.add('js');
        try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
        function renderMath() {
            if (typeof renderMathInElement === 'function') {
                renderMathInElement(document.body, {
                    delimiters: [
                        {left: '$$', right: '$$', display: true},
                        {left: '$', right: '$', display: false},
                        {left: '\\(', right: '\\)', display: false},
                        {left: '\\[', right: '\\]', display: true}
                    ],
                    throwOnError: false
                });
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js" onload="renderMath()"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js"></script>

    <?= csrfMetaTag(); ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: { preflight: true },
            theme: { extend: { fontFamily: { sans: ['Hanken Grotesk', 'system-ui', 'sans-serif'], serif: ['Fraunces', 'Georgia', 'serif'] } } }
        }
    </script>
    <script src="/assets/js/app.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <script>window.SV_T = <?= json_encode($tdDict[$tdLang] ?? [], JSON_UNESCAPED_UNICODE) ?>; window.SV_LANG = <?= json_encode($tdLang) ?>;</script>
</head>
<body class="v2 tv2 font-sans antialiased">
<a href="#main" class="t-skip"><?= tde('skip') ?></a>
<div class="t-scrim" id="t-scrim" onclick="toggleMobileDrawer()"></div>

<div class="t-shell">

    <!-- ───────── Left rail ───────── -->
    <aside class="t-rail" id="t-rail" aria-label="<?= tde('nav_label') ?>">
        <div class="t-rail-head">
            <a href="/teacher/dashboard.php" class="brand" aria-label="StudyVibe"><?= Brand::logo('md') ?></a>
            <span class="t-rail-role"><?= tde('role') ?></span>
        </div>

        <div class="t-rail-scroll">
            <div class="t-course-pick">
                <label class="t-label" for="t-course-select"><?= tde('course') ?></label>
                <select id="t-course-select" onchange="location.href='/teacher/dashboard.php?course_id='+this.value;" class="input">
                    <option value="0" <?= !$selectedCourse ? 'selected' : '' ?>><?= tde('course_choose') ?></option>
                    <?php foreach ($myCourses as $mc): ?>
                        <option value="<?= (int)$mc['id']; ?>" <?= ($selectedCourse && (int)$selectedCourse['id'] === (int)$mc['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($mc['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="t-newcourse" onclick="toggleModal('create-course-modal'); closeDrawer();">
                <span style="width:1rem;height:1rem;display:inline-block"><?= tdIcon('plus') ?></span> <?= tde('course_new') ?>
            </button>

            <?php
            $navDisabled = $selectedCourse ? '' : ' is-disabled" aria-disabled="true';
            $navItem = function (string $tab, string $icon, string $label, bool $needsCourse = true, $badge = null, bool $quiet = false) use ($selectedCourse) {
                $dis = ($needsCourse && !$selectedCourse);
                echo '<button type="button" onclick="switchDashboardTab(\'' . $tab . '\'); closeDrawer();" data-tab-target="' . $tab . '" class="sidebar-tab-btn' . ($dis ? ' is-disabled" aria-disabled="true' : '') . '">'
                   . tdIcon($icon) . '<span>' . htmlspecialchars($label) . '</span>'
                   . ($badge ? '<span class="t-badge' . ($quiet ? ' quiet' : '') . '">' . (int)$badge . '</span>' : '')
                   . '</button>';
            };
            ?>
            <nav class="t-nav-group" aria-label="<?= tde('nav_today') ?>">
                <?php $navItem('tab-overview', 'today', td('nav_today'), false); ?>
            </nav>
            <nav class="t-nav-group">
                <p><?= tde('nav_g_build') ?></p>
                <?php $navItem('tab-course', 'outline', td('nav_course')); ?>
                <?php $navItem('tab-library', 'library', td('nav_library')); ?>
            </nav>
            <nav class="t-nav-group">
                <p><?= tde('nav_g_run') ?></p>
                <?php $navItem('tab-live-eval', 'live', td('nav_live'), true, count($tdLiveNow)); ?>
            </nav>
            <nav class="t-nav-group">
                <p><?= tde('nav_g_follow') ?></p>
                <?php $navItem('tab-grades', 'grades', td('nav_grades')); ?>
                <?php $navItem('tab-assignments', 'assign', td('nav_assign'), true, $tdNewSubs, true); ?>
                <?php $navItem('tab-comments', 'qa', td('nav_qa'), true, $tdUnanswered); ?>
            </nav>
        </div>

        <div class="t-rail-foot">
            <button type="button" class="t-avatar t-avatar-btn" id="rail-avatar" onclick="toggleModal('profile-modal')" title="<?= tde('prof_open') ?>" aria-label="<?= tde('prof_open') ?>" style="border:0;cursor:pointer;overflow:hidden;padding:0">
                <?php if (!empty($user['avatar_path'])): ?><img src="<?= htmlspecialchars(mediaUrl('avatar', $user['avatar_path'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= htmlspecialchars($tdInitial) ?><?php endif; ?>
            </button>
            <div class="who"><b><?= htmlspecialchars($user['name']); ?></b><span><?= htmlspecialchars($user['email']); ?></span></div>
            <a href="/logout.php" class="t-icon-link" title="<?= tde('logout') ?>" aria-label="<?= tde('logout') ?>"><?= tdIcon('logout') ?></a>
        </div>
    </aside>

    <!-- ───────── Main ───────── -->
    <div class="t-main">
        <header class="t-top">
            <div class="t-crumb">
                <button type="button" class="t-icon-link t-menu-btn" onclick="toggleMobileDrawer()" aria-label="<?= tde('menu') ?>" aria-controls="t-rail"><?= tdIcon('menu') ?></button>
                <?php if ($selectedCourse): ?>
                    <span class="hidden sm:inline"><?= htmlspecialchars($selectedCourse['title']) ?></span><span class="sep hidden sm:inline">/</span>
                <?php endif; ?>
                <b id="t-section-name"><?= tde('nav_today') ?></b>
            </div>
            <div class="t-top-tools">
                <div class="t-notif" id="notif-wrap">
                    <button type="button" id="notif-btn" class="t-icon-link" aria-label="<?= tde('notifs') ?>" aria-haspopup="true" aria-expanded="false" style="position:relative">
                        <?= tdIcon('bell') ?>
                        <span id="notif-count" class="t-notif-count hidden">0</span>
                    </button>
                    <div id="notif-panel-container" class="t-notif-panel hidden">
                        <div class="t-notif-head">
                            <span><?= tde('notifs') ?></span>
                            <button onclick="markAllNotificationsRead(event)" class="t-link" style="font-size:.8125rem"><?= tde('notifs_read_all') ?></button>
                        </div>
                        <div id="notif-panel"></div>
                    </div>
                </div>
                <button class="t-icon-link t-theme" data-dark-toggle type="button" aria-label="<?= tde('theme') ?>" title="<?= tde('theme') ?>"><span class="moon"><?= tdIcon('moon') ?></span><span class="sun"><?= tdIcon('sun') ?></span></button>
                <select id="lang-selector" onchange="changeLanguage(this.value)" class="t-lang" aria-label="<?= tde('language') ?>">
                    <option value="fr" <?= $tdLang === 'fr' ? 'selected' : ''; ?>>FR</option>
                    <option value="en" <?= $tdLang === 'en' ? 'selected' : ''; ?>>EN</option>
                </select>
            </div>
        </header>

        <main class="t-content" id="main">

            <?php if ($successMsg): ?>
                <script>
                    window.addEventListener('DOMContentLoaded', () => {
                        if (typeof Toast !== 'undefined') { Toast.success(<?= json_encode($successMsg) ?>); }
                    });
                </script>
            <?php endif; ?>

            <?php if (isset($_GET['mail_queued'])): $mailQ = (int)$_GET['mail_queued']; $mailE = (int)($_GET['mail_eligible'] ?? 0); ?>
                <script>
                    window.addEventListener('DOMContentLoaded', () => {
                        if (typeof Toast !== 'undefined') { Toast.success(<?= json_encode(td('mail_toast', ['n' => $mailQ, 'm' => $mailE])) ?>); }
                    });
                </script>
            <?php endif; ?>
            <?php if (SmsGateway::enabled() && isset($_GET['sms_queued'])): $smsQ = (int)$_GET['sms_queued']; $smsN = (int)($_GET['sms_nophone'] ?? 0); ?>
                <script>
                    window.addEventListener('DOMContentLoaded', () => {
                        if (typeof Toast !== 'undefined') { Toast.success(<?= json_encode(td('sms_toast', ['n' => $smsQ, 'm' => $smsN])) ?>); }
                    });
                </script>
            <?php endif; ?>

            <?php
            $errorKey = (string)($_GET['error'] ?? '');
            $errorMessages = [
                'upload_move_error' => td('err_upload_move'),
                'upload_size_error' => td('err_upload_size'),
                'upload_ext_error'  => td('err_upload_ext'),
                'upload_error'      => td('err_upload'),
                'library_too_big'   => td('err_upload_size'),
                'library_bad_type'  => td('err_upload_ext'),
                'library_server'    => td('err_upload'),
                'library_upload'    => td('err_upload'),
                'live_image_not_image' => td('err_upload_ext'),
                'live_image_too_big'   => td('err_upload_size'),
                'live_image_server'    => td('err_upload'),
                'live_image_upload'    => td('err_upload'),
            ];
            $errorMsg = $errorMessages[$errorKey] ?? null;
            if ($errorMsg): ?>
                <script>
                    window.addEventListener('DOMContentLoaded', () => {
                        if (typeof Toast !== 'undefined') { Toast.error(<?= json_encode($errorMsg) ?>); }
                    });
                </script>
            <?php endif; ?>

            <!-- ═════════ TODAY (tab-overview) ═════════ -->
            <div id="tab-overview" class="tab-content" data-title="<?= tde('nav_today') ?>">
            <?php
            function tdWhen(int $ts): string {
                $d = $ts - time();
                if ($d < 0) return td('when_now');
                if ($d < 3600) return td('when_min', ['n' => max(1, (int)round($d / 60))]);
                if (date('Y-m-d', $ts) === date('Y-m-d')) return td('when_today', ['t' => date('H:i', $ts)]);
                if (date('Y-m-d', $ts) === date('Y-m-d', time() + 86400)) return td('when_tomorrow', ['t' => date('H:i', $ts)]);
                return td('when_date', ['d' => date('d/m', $ts), 't' => date('H:i', $ts)]);
            }
            ?>

            <?php if (!$selectedCourse): ?>
                <?php
                // Cross-course view: sessions that are live or still ahead, unanswered questions per course, learners per course.
                $tdAllSessions = []; $tdCourseStudents = []; $tdCourseOpenQ = [];
                if (!empty($myCourses)) {
                    $st = $pdo->prepare("
                        SELECT s.id, s.title, s.course_id, s.status, s.start_time, s.end_time, s.is_async, s.async_deadline, s.is_paused,
                               c.title AS course_title,
                               (SELECT COUNT(*) FROM live_eval_questions q WHERE q.session_id = s.id) AS question_count,
                               (SELECT COUNT(*) FROM live_eval_registrations r WHERE r.session_id = s.id) AS participant_count,
                               (SELECT COUNT(*) FROM live_eval_registrations r WHERE r.session_id = s.id AND r.last_activity >= NOW() - INTERVAL 10 SECOND) AS online_count
                        FROM live_eval_sessions s JOIN courses c ON c.id = s.course_id
                        WHERE s.teacher_id = :t AND ((s.is_async = 0 AND s.end_time >= NOW()) OR (s.is_async = 1 AND (s.async_deadline IS NULL OR s.async_deadline >= NOW())))
                        ORDER BY s.start_time ASC LIMIT 6");
                    $st->execute(['t' => $teacherId]);
                    $tdAllSessions = $st->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($tdAllSessions as &$_r) { $_r['is_finished'] = false; } unset($_r);

                    $st = $pdo->prepare("SELECT e.course_id, COUNT(*) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.teacher_id = :t GROUP BY e.course_id");
                    $st->execute(['t' => $teacherId]);
                    foreach ($st->fetchAll(PDO::FETCH_NUM) as $_r) { $tdCourseStudents[(int)$_r[0]] = (int)$_r[1]; }

                    $st = $pdo->prepare("
                        SELECT ch.course_id, COUNT(*) FROM lesson_comments lc
                        JOIN users u ON u.id = lc.user_id AND u.role = 'student'
                        JOIN lessons l ON l.id = lc.lesson_id JOIN chapters ch ON ch.id = l.chapter_id
                        JOIN courses c ON c.id = ch.course_id
                        WHERE c.teacher_id = :t AND lc.is_hidden = 0 AND (lc.teacher_reply IS NULL OR lc.teacher_reply = '')
                        GROUP BY ch.course_id");
                    $st->execute(['t' => $teacherId]);
                    foreach ($st->fetchAll(PDO::FETCH_NUM) as $_r) { $tdCourseOpenQ[(int)$_r[0]] = (int)$_r[1]; }
                }
                ?>
                <header class="t-head">
                    <div>
                        <p class="t-kicker"><?= tde('console') ?></p>
                        <h1><?= td('hello', ['name' => '<em>' . htmlspecialchars($tdFirstName ?: td('hello_fallback')) . '</em>']) ?></h1>
                        <p><?= tde('home_lede') ?></p>
                    </div>
                    <div class="t-actions">
                        <button type="button" class="t-btn t-btn-primary" onclick="toggleModal('create-course-modal')"><?= tdIcon('plus') ?><?= tde('course_new') ?></button>
                    </div>
                </header>

                <div class="t-today">
                    <section aria-labelledby="h-courses">
                        <h2 class="t-section-title" id="h-courses"><?= tde('your_courses') ?></h2>
                        <?php if (empty($myCourses)): ?>
                            <div class="t-empty boxed">
                                <p><?= tde('empty_courses') ?></p>
                                <?php if (!empty($modules)): ?>
                                    <button type="button" class="t-btn t-btn-primary" onclick="toggleModal('create-course-modal')"><?= tdIcon('plus') ?><?= tde('course_create_first') ?></button>
                                <?php else: ?>
                                    <p class="t-hint" style="margin:0"><?= tde('empty_courses_nomodule') ?></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <ul class="t-courses">
                                <?php foreach ($myCourses as $mc): $mid = (int)$mc['id']; ?>
                                    <li>
                                        <a href="/teacher/dashboard.php?course_id=<?= $mid ?>"<?= !empty($mc['cover_image']) ? ' style="grid-template-columns:auto 1fr auto"' : '' ?>>
                                            <?php if (!empty($mc['cover_image'])): ?><img src="<?= htmlspecialchars(mediaUrl('cover', $mc['cover_image'])) ?>" alt="" loading="lazy" width="96" height="54" style="width:96px;height:54px;object-fit:cover;border-radius:8px;border:1px solid var(--line)"><?php endif; ?>
                                            <div>
                                                <b><?= htmlspecialchars($mc['title']) ?></b><br>
                                                <span class="num"><?= td('n_learners', ['n' => $tdCourseStudents[$mid] ?? 0]) ?><?php if (!empty($tdCourseOpenQ[$mid])): ?> · <strong style="color:var(--clay)"><?= td('n_open_q', ['n' => $tdCourseOpenQ[$mid]]) ?></strong><?php endif; ?></span>
                                            </div>
                                            <span class="go"><?= tde('open') ?> →</span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>

                    <section aria-labelledby="h-soon">
                        <h2 class="t-section-title" id="h-soon"><?= tde('sessions_ahead') ?></h2>
                        <?php if (empty($tdAllSessions)): ?>
                            <div class="t-empty" style="padding-top:.25rem"><p><?= tde('empty_sessions_all') ?></p></div>
                        <?php else: ?>
                            <ul class="t-attn">
                                <?php foreach ($tdAllSessions as $s): $stt = tdSessionState($s); ?>
                                    <li>
                                        <span class="mark <?= $stt === 'live' ? 'live' : ($stt === 'wait' ? 'urgent' : '') ?>"></span>
                                        <div>
                                            <b><?= htmlspecialchars($s['title']) ?></b>
                                            <span class="sub"><?= htmlspecialchars($s['course_title']) ?> · <?= $stt === 'live' || $stt === 'paused' ? td('state_' . $stt) : tdWhen(strtotime($s['start_time'])) ?><?php if ($stt === 'off'): ?> · <?= tde('not_activated') ?><?php endif; ?></span>
                                        </div>
                                        <a class="t-btn t-btn-ghost t-btn-sm" href="/teacher/dashboard.php?course_id=<?= (int)$s['course_id'] ?>#tab-live-eval"><?= tde('open') ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                </div>

            <?php else: ?>
                <?php
                // ── What needs attention, most urgent first ──────────────────────
                $tdItems = [];
                foreach ($tdLiveNow as $s) {
                    $tdItems[] = ['live', td($s['is_paused'] ?? 0 ? 'att_paused' : 'att_live', ['t' => htmlspecialchars($s['title'])]),
                        td('att_live_sub', ['o' => (int)$s['online_count'], 'p' => (int)$s['participant_count']]),
                        td('att_open_room'), "openRoom(" . (int)$s['id'] . ")"];
                }
                foreach (array_slice($tdUpcoming, 0, 3) as $s) {
                    $stt = tdSessionState($s);
                    $noQ = (int)$s['question_count'] === 0;
                    $soon = strtotime($s['start_time']) - $tdNow < 86400;
                    $sub = $noQ ? td('att_noq') : ($stt === 'off' ? td('att_not_on') : td('att_registered', ['n' => (int)$s['participant_count']]));
                    $tdItems[] = [($noQ || ($soon && $stt === 'off')) ? 'urgent' : 'normal',
                        td('att_upcoming', ['t' => htmlspecialchars($s['title']), 'w' => tdWhen(strtotime($s['start_time']))]),
                        $sub, $noQ ? td('att_add_q') : td('att_prepare'), "openRoom(" . (int)$s['id'] . ")"];
                }
                if ($tdUnanswered > 0) {
                    $tdItems[] = ['urgent', td('att_open_q', ['n' => $tdUnanswered]), td('att_open_q_sub'), td('att_answer'), "switchDashboardTab('tab-comments')"];
                }
                if ($tdNewSubs > 0) {
                    $tdItems[] = ['normal', td('att_new_subs', ['n' => $tdNewSubs]), td('att_new_subs_sub'), td('att_see_subs'), "switchDashboardTab('tab-assignments')"];
                }
                $tdLessonTotal = 0;
                foreach ($chapters as $_ch) { $tdLessonTotal += count($_ch['lessons'] ?? []); }
                if (empty($chapters)) {
                    $tdItems[] = ['normal', td('att_no_chapter'), td('att_no_chapter_sub'), td('att_add_chapter'), "toggleModal('chapter-modal')"];
                } elseif ($finalExamQuestionCount < 30 && $tdLessonTotal > 0) {
                    $tdItems[] = ['normal', td('att_final_short', ['n' => $finalExamQuestionCount]), td('att_final_short_sub'), td('att_finish'), "switchDashboardTab('tab-grades'); switchGradesSubTab('subtab-certs')"];
                }
                ?>
                <header class="t-head">
                    <div>
                        <p class="t-kicker"><?= tde('nav_today') ?></p>
                        <h1><?= htmlspecialchars($selectedCourse['title']); ?></h1>
                        <?php if (!empty($selectedCourse['description'])): ?><p><?= htmlspecialchars($selectedCourse['description']); ?></p><?php endif; ?>
                        <p class="num" style="font-size:.9rem;color:var(--ink-3)">
                            <?= tde('enroll_key') ?> :
                            <strong style="color:var(--ink)"><?= $selectedCourse['enrollment_key'] ? htmlspecialchars($selectedCourse['enrollment_key']) : tde('enroll_free'); ?></strong>
                        </p>
                    </div>
                    <div class="t-actions">
                        <button type="button" onclick="openShareModal(<?= (int)$selectedCourse['id'] ?>)" class="t-btn t-btn-ghost"><?= tdIcon('share') ?><?= tde('share') ?></button>
                        <button type="button" onclick="openEditCourseModal()" class="t-btn t-btn-ghost"><?= tdIcon('edit') ?><?= tde('course_edit') ?></button>
                    </div>
                </header>

                <?php if (!empty($selectedCourse['cover_image'])): ?>
                    <img class="t-cover" src="/download.php?type=cover&file=<?= urlencode($selectedCourse['cover_image']); ?>" alt="">
                <?php endif; ?>

                <?php
                $studentCountStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = :cid");
                $studentCountStmt->execute(['cid' => $selectedCourse['id']]);
                $studentCount = (int)$studentCountStmt->fetchColumn();

                $lessonQuizCountStmt = $pdo->prepare("
                     SELECT COUNT(DISTINCT l.id)
                     FROM lessons l
                     JOIN chapters ch ON l.chapter_id = ch.id
                     JOIN lesson_questions lq ON lq.lesson_id = l.id
                     WHERE ch.course_id = :cid
                ");
                $lessonQuizCountStmt->execute(['cid' => $selectedCourse['id']]);
                $lessonQuizCount = (int)$lessonQuizCountStmt->fetchColumn();

                $certificatesCountStmt = $pdo->prepare("
                     SELECT COUNT(*) FROM certificates cert
                     JOIN courses c ON cert.module_id = c.module_id
                     WHERE c.id = :cid
                ");
                $certificatesCountStmt->execute(['cid' => $selectedCourse['id']]);
                $certificatesCount = (int)$certificatesCountStmt->fetchColumn();
                ?>

                <div class="t-today">
                    <section aria-labelledby="h-attn">
                        <h2 class="t-section-title" id="h-attn"><?= tde('needs_you') ?></h2>
                        <?php if (empty($tdItems)): ?>
                            <div class="t-empty boxed">
                                <p><?= tde('att_none') ?></p>
                                <button type="button" class="t-btn t-btn-primary" onclick="switchDashboardTab('tab-course')"><?= tde('att_go_outline') ?></button>
                            </div>
                        <?php else: ?>
                            <ul class="t-attn">
                                <?php foreach ($tdItems as [$lvl, $title, $sub, $label, $js]): ?>
                                    <li>
                                        <span class="mark <?= $lvl === 'live' ? 'live' : ($lvl === 'urgent' ? 'urgent' : '') ?>"></span>
                                        <div><b><?= $title ?></b><span class="sub"><?= $sub ?></span></div>
                                        <button type="button" class="t-btn <?= $lvl === 'normal' ? 't-btn-ghost' : 't-btn-primary' ?> t-btn-sm" onclick="<?= $js ?>"><?= htmlspecialchars($label) ?></button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>

                    <section aria-labelledby="h-nums">
                        <h2 class="t-section-title" id="h-nums"><?= tde('course_in_numbers') ?></h2>
                        <div class="t-stats">
                            <a href="#tab-grades" onclick="switchDashboardTab('tab-grades'); switchGradesSubTab('subtab-students'); return false;"><span class="n"><?= $studentCount ?></span><span class="l"><?= tde('stat_learners') ?></span></a>
                            <a href="#tab-course" onclick="switchDashboardTab('tab-course'); return false;"><span class="n"><?= $tdLessonTotal ?></span><span class="l"><?= tde('stat_lessons') ?></span></a>
                            <a href="#tab-live-eval" onclick="switchDashboardTab('tab-live-eval'); return false;"><span class="n"><?= count($liveSessions); ?></span><span class="l"><?= tde('stat_sessions') ?></span></a>
                            <a href="#tab-grades" onclick="switchDashboardTab('tab-grades'); switchGradesSubTab('subtab-certs'); return false;"><span class="n"><?= $certificatesCount; ?></span><span class="l"><?= tde('stat_certs') ?></span></a>
                        </div>
                        <p class="t-hint" style="margin-top:.75rem"><?= td('stat_quiz_note', ['n' => $lessonQuizCount]) ?></p>
                    </section>
                </div>

            <?php endif; ?>

            </div>

        <?php if ($selectedCourse): ?>

            <!-- 2. PLAN & CONTENU (tab-course) -->
            <div id="tab-course" class="tab-content hidden" data-title="<?= tde('nav_course') ?>">
                <header class="t-head">
                    <div>
                        <p class="t-kicker"><?= tde('nav_g_build') ?></p>
                        <h2><?= tde('course_title') ?></h2>
                        <p><?= tde('course_lede') ?></p>
                    </div>
                    <div class="t-actions">
                        <span class="t-savestate<?= $successMsg ? '' : '' ?>" id="t-savestate" role="status"><i></i><span><?= $successMsg ? td('saved_at', ['t' => date('H:i')]) : td('saved_none') ?></span></span>
                        <button type="button" onclick="toggleModal('chapter-modal')" class="t-btn t-btn-primary"><?= tdIcon('plus') ?><?= tde('chapter_add') ?></button>
                    </div>
                </header>

                <div class="t-editor">
                    <nav class="t-outline" aria-label="<?= tde('outline') ?>">
                        <h4><?= tde('outline') ?></h4>
                        <?php if (empty($chapters)): ?>
                            <span class="dim"><?= tde('outline_empty') ?></span>
                        <?php else: ?>
                            <ol>
                                <?php foreach ($chapters as $_ch): ?>
                                    <li>
                                        <button type="button" class="ch" onclick="goChapter(<?= (int)$_ch['id'] ?>)"><?= htmlspecialchars($_ch['title']) ?></button>
                                        <ol>
                                            <?php foreach (($_ch['lessons'] ?? []) as $_l): ?>
                                                <li><a href="#lesson-item-<?= (int)$_l['id'] ?>" data-outline-lesson="<?= (int)$_l['id'] ?>" onclick="goLesson(<?= (int)$_l['id'] ?>); return false;"><?= htmlspecialchars($_l['title']) ?></a></li>
                                            <?php endforeach; ?>
                                            <li><button type="button" class="t-link dim" style="text-decoration:none;font-weight:500" onclick="openLessonModal(<?= (int)$_ch['id'] ?>)">+ <?= tde('lesson_add_short') ?></button></li>
                                        </ol>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </nav>

                    <div class="space-y-6">
                    <?php if (empty($chapters)): ?>
                        <div class="p-8 border border-dashed border-[var(--line)] text-center text-sm font-light text-[var(--ink-3)]">
                            Aucun chapitre créé. Cliquez sur le bouton ci-dessus pour structurer votre premier chapitre.
                        </div>
                    <?php else: ?>
                        <?php foreach ($chapters as $ch): ?>
                        <div class="sv-chapter-card" id="chapter-<?= $ch['id']; ?>">

                            <!-- En-tête du chapitre -->
                            <div class="flex justify-between items-center px-6 py-4 border-b border-[var(--line)] bg-[var(--paper-2)]">
                                <h4 class="font-serif text-base font-medium text-[var(--ink)]">
                                    <?= htmlspecialchars($ch['title']); ?>
                                </h4>
                                <div class="flex items-center gap-2">
                                    <button onclick="openEditChapterModal(<?= $ch['id']; ?>, <?= htmlspecialchars(json_encode($ch['title'])); ?>)"
                                        class="icon-btn" title="Modifier le chapitre">
                                        <svg class="w-3.5 h-3.5 text-[var(--ink-2)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z"/>
                                        </svg>
                                    </button>
                                    <button onclick="confirmDeleteChapter(<?= $ch['id']; ?>, <?= htmlspecialchars(json_encode($ch['title'])); ?>)"
                                        class="icon-btn danger" title="Supprimer le chapitre">
                                        <svg class="w-3.5 h-3.5 text-[var(--danger)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    <button onclick="openLessonModal(<?= $ch['id']; ?>)"
                                        class="t-btn t-btn-ghost t-btn-sm">+ Leçon
                                    </button>
                                </div>
                            </div>

                            <!-- Leçons du chapitre -->
                            <div class="divide-y divide-[var(--line)]">
                                <?php if (empty($ch['lessons'])): ?>
                                    <p class="text-xs text-[var(--ink-3)] italic font-light px-6 py-4">Aucune leçon dans ce chapitre.</p>
                                <?php else: ?>
                                    <?php foreach ($ch['lessons'] as $les): ?>
                                    <?php $isOpen = $openLessonId === (int)$les['id']; ?>
                                    <div class="lesson-item" id="lesson-item-<?= $les['id']; ?>">

                                        <!-- Ligne principale de la leçon -->
                                        <div class="px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-3 flex-grow min-w-0">
                                                <!-- Accordion toggle -->
                                                <button onclick="toggleLesson(<?= $les['id']; ?>)"
                                                    class="flex-shrink-0 w-5 h-5 text-[var(--ink-3)] hover:text-[var(--clay)] transition-colors"
                                                    title="Voir les détails">
                                                    <svg id="chevron-<?= $les['id']; ?>" class="w-4 h-4 transition-transform <?= $isOpen ? 'rotate-90':'' ?>"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                </button>
                                                <div class="min-w-0">
                                                    <div class="font-medium text-sm text-[var(--ink)] flex items-center gap-2 flex-wrap">
                                                        <?= htmlspecialchars($les['title']); ?>
                                                        <?php if ((int)($les['is_compulsory'] ?? 1) === 1): ?>
                                                            <span class="text-xs font-semibold bg-[var(--pine-soft)] border border-[var(--clay)] text-[var(--clay)] px-1.5 py-0.5 rounded">
                                                                Obligatoire
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-xs font-semibold bg-[var(--paper-2)] border border-[var(--line)] text-[var(--ink-2)] px-1.5 py-0.5 rounded">
                                                                Optionnelle
                                                            </span>
                                                        <?php endif; ?>
                                                        <span class="text-xs num   bg-[var(--paper-2)] border border-[var(--line)] px-1.5 py-0.5 text-[var(--ink-2)]">
                                                            <?= htmlspecialchars($les['content_type']); ?>
                                                        </span>
                                                        <?php if (!empty($les['videos'])): ?>
                                                            <span class="text-xs num t-bg-info-soft border t-bd-info t-tx-info px-1.5 py-0.5">
                                                                <?= count($les['videos']); ?> vidéo<?= count($les['videos']) > 1 ? 's':''; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($les['resources'])): ?>
                                                            <span class="text-xs num t-bg-warn-soft border t-bd-warn t-tx-warn px-1.5 py-0.5">
                                                                <?= count($les['resources']); ?> ressource<?= count($les['resources']) > 1 ? 's':''; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-xs num text-[var(--ink-3)] mt-0.5">
                                                        <?= $les['question_count']; ?> question(s) d'évaluation
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Actions leçon -->
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <button onclick="openEditLessonModal(<?= htmlspecialchars(json_encode($les), ENT_QUOTES); ?>)"
                                                    class="icon-btn" title="Modifier la leçon">
                                                    <svg class="w-3.5 h-3.5 text-[var(--ink-2)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z"/>
                                                    </svg>
                                                </button>
                                                <button onclick="confirmDeleteLesson(<?= $les['id']; ?>, <?= htmlspecialchars(json_encode($les['title'])); ?>)"
                                                    class="icon-btn danger" title="Supprimer la leçon">
                                                    <svg class="w-3.5 h-3.5 text-[var(--danger)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                                <button onclick="openQuestionModal(<?= $les['id']; ?>, <?= htmlspecialchars(json_encode($les['title'])); ?>)"
                                                    class="t-btn t-btn-ghost t-btn-sm">+ Question
                                                </button>
                                                <?php if (!empty(trim($les['text_content'] ?? ''))): ?>
                                                    <button onclick="openAiQuizConfigModal(<?= $les['id']; ?>, <?= htmlspecialchars(json_encode($les['title'])); ?>)"
                                                        class="t-btn t-btn-ghost t-btn-sm">Quiz IA
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Panel accordéon : détails de la leçon -->
                                        <div class="lesson-panel <?= $isOpen ? 'open':'' ?> slide-in border-t border-[var(--line)] bg-[var(--paper-2)] px-8 py-5 space-y-5"
                                            id="panel-<?= $les['id']; ?>">

                                            <!-- Contenu textuel -->
                                            <?php if (!empty($les['text_content'])): ?>
                                            <div class="space-y-1">
                                                <p class="text-xs font-semibold   text-[var(--ink-3)]">Contenu textuel</p>
                                                <p class="text-xs font-light text-[var(--ink-2)] leading-relaxed max-w-2xl line-clamp-4">
                                                    <?= htmlspecialchars($les['text_content']); ?>
                                                </p>
                                            </div>
                                            <?php endif; ?>

                                            <!-- PDF -->
                                            <?php if (!empty($les['pdf_path'])): ?>
                                            <div class="space-y-1">
                                                <p class="text-xs font-semibold   text-[var(--ink-3)]">Document PDF</p>
                                                <a href="<?= mediaUrl('pdf', $les['pdf_path']); ?>" target="_blank"
                                                    class="text-xs text-[var(--clay)] underline num">
                                                    <?= htmlspecialchars($les['pdf_path']); ?>
                                                </a>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Vidéos -->
                                            <?php if (!empty($les['videos'])): ?>
                                            <div class="space-y-2">
                                                <p class="text-xs font-semibold   text-[var(--ink-3)]">Vidéos</p>
                                                <div class="space-y-1.5">
                                                    <?php foreach ($les['videos'] as $vid): ?>
                                                    <div class="flex items-center justify-between gap-3 bg-[var(--card)] border border-[var(--line)] px-3 py-2 rounded-sm">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <svg class="w-3.5 h-3.5 t-tx-info flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                            <span class="text-xs font-medium text-[var(--ink)] flex-shrink-0"><?= htmlspecialchars($vid['label']); ?></span>
                                                            <a href="<?= htmlspecialchars($vid['url']); ?>" target="_blank"
                                                                class="text-[11px] text-[var(--clay)] underline truncate num">
                                                                <?= htmlspecialchars($vid['url']); ?>
                                                            </a>
                                                        </div>
                                                        <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" class="flex-shrink-0">
                                                        <?= csrfInput(); ?>
                                                            <input type="hidden" name="action" value="delete_lesson_video">
                                                            <input type="hidden" name="video_id" value="<?= $vid['id']; ?>">
                                                            <input type="hidden" name="lesson_id" value="<?= $les['id']; ?>">
                                                            <button type="submit" class="icon-btn danger" title="Supprimer la vidéo"
                                                                onclick="return confirm('Supprimer cette vidéo ?')">
                                                                <svg class="w-3 h-3 text-[var(--danger)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Ressources -->
                                            <?php if (!empty($les['resources'])): ?>
                                            <div class="space-y-2">
                                                <p class="text-xs font-semibold   text-[var(--ink-3)]">Ressources</p>
                                                <div class="space-y-1.5">
                                                    <?php foreach ($les['resources'] as $res): ?>
                                                    <div class="flex items-center justify-between gap-3 bg-[var(--card)] border border-[var(--line)] px-3 py-2 rounded-sm">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <svg class="w-3.5 h-3.5 text-[var(--ink-3)] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                            </svg>
                                                            <span class="text-xs font-medium text-[var(--ink)] flex-shrink-0"><?= htmlspecialchars($res['label']); ?></span>
                                                            <a href="<?= htmlspecialchars($res['url']); ?>" target="_blank"
                                                                class="text-[11px] text-[var(--clay)] underline truncate num">
                                                                <?= htmlspecialchars($res['url']); ?>
                                                            </a>
                                                        </div>
                                                        <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" class="flex-shrink-0">
                                                        <?= csrfInput(); ?>
                                                            <input type="hidden" name="action" value="delete_lesson_resource">
                                                            <input type="hidden" name="resource_id" value="<?= $res['id']; ?>">
                                                            <input type="hidden" name="lesson_id" value="<?= $les['id']; ?>">
                                                            <button type="submit" class="icon-btn danger" title="Supprimer la ressource"
                                                                onclick="return confirm('Supprimer cette ressource ?')">
                                                                <svg class="w-3 h-3 text-[var(--danger)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (empty($les['videos']) && empty($les['resources']) && empty($les['text_content']) && empty($les['pdf_path'])): ?>
                                                <p class="text-xs text-[var(--ink-3)] italic">Aucun contenu détaillé disponible.</p>
                                            <?php endif; ?>

                                            <!-- Section Discussions / Q&R -->
                                            <div class="border-t border-[var(--line)] pt-4 mt-4 space-y-3">
                                                <p class="text-xs font-semibold   text-[var(--ink-3)]">Espace d'Échange (Q&R)</p>
                                                <div class="space-y-3" id="comments-list-<?= $les['id']; ?>">
                                                    <?php if (empty($les['comments'])): ?>
                                                        <p class="text-xs text-[var(--ink-3)] italic">Aucune question ou commentaire sur cette leçon.</p>
                                                    <?php else: ?>
                                                        <?php foreach ($les['comments'] as $comm): ?>
                                                            <div class="p-3 bg-[var(--card)] border border-[var(--line)] rounded-sm space-y-2 text-xs <?= $comm['is_hidden'] ? 'opacity-60 bg-[var(--paper-2)]' : ''; ?>" id="comment-card-<?= $comm['id']; ?>">
                                                                <div class="flex justify-between items-start">
                                                                    <div>
                                                                        <strong class="text-[var(--clay)]"><?= htmlspecialchars($comm['author_name']); ?></strong>
                                                                        <span class="text-[var(--ink-3)]">(<?= htmlspecialchars($comm['author_role']); ?>)</span>
                                                                        <span class="text-xs text-[var(--ink-3)] ml-2"><?= $comm['created_at']; ?></span>
                                                                        <?php if ($comm['is_hidden']): ?>
                                                                            <span class="text-xs t-bg-danger-soft t-tx-danger px-1.5 py-0.5 ml-2 font-semibold rounded-sm">Masqué</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="flex gap-2">
                                                                        <!-- Masquer / Afficher -->
                                                                        <button onclick="moderateComment(<?= $comm['id']; ?>, <?= $comm['is_hidden'] ? 0 : 1; ?>)"
                                                                            class="text-xs  font-semibold text-[var(--ink-2)] hover:underline">
                                                                            <?= $comm['is_hidden'] ? 'Afficher' : 'Masquer'; ?>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <p class="text-[var(--ink-2)] font-light"><?= htmlspecialchars($comm['comment_text']); ?></p>

                                                                <!-- Réponse de l'enseignant -->
                                                                <div id="reply-container-<?= $comm['id']; ?>">
                                                                    <?php if (!empty($comm['teacher_reply'])): ?>
                                                                        <div class="mt-2 pl-3 border-l-2 border-[var(--clay)] text-[var(--ink-2)]">
                                                                            <strong>Votre réponse :</strong> <?= htmlspecialchars($comm['teacher_reply']); ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <!-- Formulaire de réponse -->
                                                                <form onsubmit="submitReply(event, <?= $comm['id']; ?>)" class="mt-2 flex gap-2">
                                                                    <input type="text" placeholder="Répondre à ce message..." required id="reply-input-<?= $comm['id']; ?>"
                                                                        class="flex-1 px-3 py-1 bg-[var(--paper-2)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm">
                                                                    <button type="submit" class="px-3 py-1 bg-[var(--ink)] text-white text-xs font-semibold   hover:bg-[var(--clay)] rounded-sm">
                                                                        Envoyer
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                    </div><!-- /lesson-item -->
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div><!-- /lessons -->
                        </div><!-- /chapter -->
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2b. BIBLIOTHÈQUE DU COURS (tab-library) -->
            <div id="tab-library" class="tab-content hidden" data-title="<?= tde('nav_library') ?>">
                <header class="t-head">
                    <div>
                        <p class="t-kicker"><?= tde('nav_g_build') ?></p>
                        <h2><?= tde('lib_title') ?></h2>
                        <p><?= tde('lib_lede') ?></p>
                    </div>
                    <?php if ($selectedCourse && !empty($courseLibraryItems)): ?>
                        <div class="t-actions"><button type="button" onclick="openAddLibraryModal()" class="t-btn t-btn-primary"><?= tdIcon('plus') ?><?= tde('lib_add') ?></button></div>
                    <?php endif; ?>
                </header>

                <?php if (empty($courseLibraryItems)): ?>
                    <div class="t-empty boxed">
                        <p><?= tde('lib_empty') ?></p>
                        <?php if ($selectedCourse): ?><button type="button" onclick="openAddLibraryModal()" class="t-btn t-btn-primary"><?= tdIcon('plus') ?><?= tde('lib_add') ?></button><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($courseLibraryItems as $item): ?>
                            <div class="bg-[var(--card)]  border border-[var(--line)]  p-5 rounded-xl flex flex-col justify-between hover:shadow-md transition-all space-y-4">
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="px-2.5 py-0.5 text-xs font-bold   t-bg-warn-soft t-tx-warn  rounded-sm">
                                            <?= htmlspecialchars(strtoupper($item['category'])) ?>
                                        </span>
                                        <span class="text-[11px] text-[var(--ink-3)] num">
                                            <?= date('d/m/Y', strtotime($item['created_at'])) ?>
                                        </span>
                                    </div>
                                    <h4 class="text-base font-semibold text-[var(--ink)]  line-clamp-2">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </h4>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="text-xs text-[var(--ink-2)]  line-clamp-2">
                                            <?= htmlspecialchars($item['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="pt-3 border-t border-[var(--line)]  flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <?php if (!empty($item['file_path'])): ?>
                                            <a href="/download.php?type=library&file=<?= urlencode(basename($item['file_path'])) ?>" target="_blank"
                                               class="px-3 py-1.5 bg-[var(--clay)] text-white text-[11px] font-semibold   rounded-md hover:bg-[var(--clay-press)] transition-colors inline-flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                PDF / Fichier
                                            </a>
                                        <?php elseif (!empty($item['video_url'])): ?>
                                            <a href="<?= htmlspecialchars($item['video_url']) ?>" target="_blank"
                                               class="px-3 py-1.5 bg-[var(--danger)] text-white text-[11px] font-semibold   rounded-md hover:bg-[var(--danger)] transition-colors inline-flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                                                Vidéo
                                            </a>
                                        <?php elseif (!empty($item['content_markdown'])): ?>
                                            <span class="px-2.5 py-1 bg-[var(--paper-2)]  text-[var(--ink-2)]  text-xs num rounded-md">
                                                Texte LaTeX / Markdown
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <form method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cette ressource ?');">
                                        <input type="hidden" name="action" value="delete_library_item">
                                        <input type="hidden" name="library_item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="p-1.5 t-tx-danger hover:t-tx-danger hover:t-bg-danger-soft  rounded-md transition-colors" title="Supprimer">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ═════════ LIVE SESSIONS: the control room (tab-live-eval) ═════════ -->
            <div id="tab-live-eval" class="tab-content hidden" data-title="<?= tde('nav_live') ?>">
                <header class="t-head">
                    <div>
                        <p class="t-kicker"><?= tde('nav_g_run') ?></p>
                        <h2><?= td('live_title') ?></h2>
                        <p><?= tde('live_lede') ?></p>
                    </div>
                    <?php if ($selectedCourse && !empty($liveSessions)): ?>
                        <div class="t-actions">
                            <button type="button" onclick="toggleModal('add-live-session-modal')" class="t-btn t-btn-primary"><?= tdIcon('plus') ?><?= tde('live_new') ?></button>
                        </div>
                    <?php endif; ?>
                </header>

                <?php
                // Most useful first: live, then what is coming (soonest first), then what is over.
                $tdRank = ['live' => 0, 'paused' => 0, 'wait' => 1, 'off' => 2, 'ended' => 3];
                $tdRooms = $liveSessions;
                usort($tdRooms, function ($a, $b) use ($tdRank) {
                    $ra = $tdRank[tdSessionState($a)]; $rb = $tdRank[tdSessionState($b)];
                    if ($ra !== $rb) return $ra <=> $rb;
                    return $ra === 3 ? strtotime($b['start_time']) <=> strtotime($a['start_time']) : strtotime($a['start_time']) <=> strtotime($b['start_time']);
                });
                $tdOpenSession = (int)($_GET['open_session'] ?? 0);
                $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                $proto = $isHttps ? 'https' : 'http';
                ?>

                <?php if (empty($tdRooms)): ?>
                    <div class="t-empty boxed">
                        <p><?= tde('live_empty') ?></p>
                        <button type="button" onclick="toggleModal('add-live-session-modal')" class="t-btn t-btn-primary"><?= tdIcon('plus') ?><?= tde('live_new') ?></button>
                    </div>
                <?php else: ?>
                    <?php foreach ($tdRooms as $rIdx => $ls):
                        $sid = (int)$ls['id'];
                        $state = tdSessionState($ls);
                        $isAsync = !empty($ls['is_async']);
                        $sessionLink = $proto . "://" . $_SERVER['HTTP_HOST'] . "/live-session.php?code=" . $ls['session_code'];
                        $nQ = count($ls['questions']);
                        $startTs = strtotime($ls['start_time']);
                        $collapsed = !($rIdx === 0 || $tdOpenSession === $sid);
                        $endLabel = $isAsync && !empty($ls['async_deadline']) ? date('d/m/Y H:i', strtotime($ls['async_deadline'])) : date('d/m/Y H:i', strtotime($ls['end_time']));
                        $partStmt = $pdo->prepare("
                            SELECT id, name, email, score, last_activity, registered_at
                            FROM live_eval_registrations WHERE session_id = :sid ORDER BY registered_at DESC");
                        $partStmt->execute(['sid' => $sid]);
                        $participants = $partStmt->fetchAll(PDO::FETCH_ASSOC);
                        $stateSub = [
                            'off'    => td('room_sub_off'),
                            'wait'   => td('room_sub_wait', ['w' => tdWhen($startTs)]),
                            'live'   => $isAsync ? td('room_sub_open_async') : td('room_sub_live'),
                            'paused' => td('room_sub_paused'),
                            'ended'  => td('room_sub_ended'),
                        ][$state];
                    ?>
                    <article class="t-room is-<?= $state ?>" id="room-<?= $sid ?>"
                             data-room data-id="<?= $sid ?>" data-state="<?= $state ?>" data-start="<?= $startTs ?>" data-now="<?= $tdNow ?>"
                             data-async="<?= $isAsync ? 1 : 0 ?>" data-total="<?= $nQ ?>" data-paused="<?= $state === 'paused' ? 1 : 0 ?>">

                        <div class="t-room-top">
                            <div style="min-width:0">
                                <h3><?= htmlspecialchars($ls['title']) ?></h3>
                            </div>
                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                                <span class="t-chip <?= $state === 'live' ? 'live' : ($state === 'paused' ? 'paused' : ($state === 'wait' ? 'wait' : ($state === 'ended' ? 'ended' : 'off'))) ?>" data-room-chip><i></i><span data-room-chip-text><?= tde('state_' . $state) ?></span></span>
                                <button type="button" class="t-btn t-btn-quiet t-btn-sm" data-room-toggle aria-expanded="<?= $collapsed ? 'false' : 'true' ?>" aria-controls="room-body-<?= $sid ?>">
                                    <span data-room-toggle-text><?= $collapsed ? tde('room_expand') : tde('room_collapse') ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="t-room-meta num">
                            <span><?= tde('room_start') ?> <b><?= date('d/m/Y H:i', $startTs) ?></b></span>
                            <span><?= $isAsync ? tde('room_deadline') : tde('room_end') ?> <b><?= $endLabel ?></b></span>
                            <span><?= tde('room_per_q') ?> <b><?= (int)$ls['default_time_limit'] ?> s</b></span>
                            <span><?= $isAsync ? tde('room_type_async') : tde('room_type_sync') ?></span>
                        </div>

                        <div id="room-body-<?= $sid ?>" <?= $collapsed ? 'hidden' : '' ?>>
                            <div class="t-state">
                                <div class="t-state-big">
                                    <div class="t-state-word <?= $state ?>" data-room-word aria-live="polite"><?= tde('word_' . $state) ?></div>
                                    <p class="t-state-sub" data-room-sub><?= $stateSub ?></p>
                                    <p class="t-state-sub num" data-room-countdown style="margin-top:.35rem;color:var(--ink-3)"></p>
                                </div>
                                <div class="t-numbers">
                                    <div><span class="n live-online-count-<?= $sid ?>"><?= (int)$ls['online_count'] ?></span><span class="l"><?= tde('room_online') ?></span></div>
                                    <div><span class="n live-inscrits-count-<?= $sid ?>"><?= (int)$ls['participant_count'] ?></span><span class="l"><?= tde('room_registered') ?></span></div>
                                    <div><span class="n"><span class="live-votes-count-<?= $sid ?>" data-room-votes>0</span> <small data-room-votes-of></small></span><span class="l"><?= $isAsync ? tde('room_submitted') : tde('room_answers_now') ?></span></div>
                                </div>
                            </div>

                            <?php if (!$isAsync): ?>
                            <div class="t-prog">
                                <div class="t-prog-row">
                                    <span data-room-qlabel><?= $nQ > 0 ? td('room_q_total', ['n' => $nQ]) : tde('room_no_q') ?></span>
                                    <b class="num"><span class="questions-count-<?= $sid ?>"><?= $nQ ?></span> <?= tde('room_questions') ?></b>
                                </div>
                                <div class="t-steps" data-room-steps aria-hidden="true">
                                    <?php for ($i = 0; $i < $nQ; $i++): ?><i></i><?php endfor; ?>
                                </div>
                                <div class="t-answers" aria-hidden="true"><span data-room-bar></span></div>
                            </div>
                            <?php else: ?>
                                <span class="questions-count-<?= $sid ?>" hidden><?= $nQ ?></span>
                            <?php endif; ?>

                            <!-- Controls: three separate groups -->
                            <div class="t-controls">
                                <div class="grp">
                                    <small><?= tde('grp_session') ?></small>
                                    <div class="row">
                                        <?php if ($state === 'ended'): ?>
                                            <button type="button" onclick="openDispatchModal(<?= $sid ?>)" class="t-btn t-btn-primary"><?= tdIcon('mail') ?><?= tde('btn_send_mails') ?></button>
                                        <?php else: ?>
                                            <?php if ($state === 'off'): ?>
                                                <form method="POST" action="/teacher/dashboard.php?course_id=<?= (int)$selectedCourse['id'] ?>&action=toggle_live_session">
                                                    <?= csrfInput(); ?>
                                                    <input type="hidden" name="session_id" value="<?= $sid ?>">
                                                    <input type="hidden" name="status" value="1">
                                                    <button type="submit" class="t-btn t-btn-primary" <?= $nQ === 0 ? 'data-confirm="' . tde('confirm_activate_noq') . '" data-confirm-title="' . tde('confirm_activate_title') . '" data-confirm-ok="' . tde('btn_activate') . '" data-confirm-kind="primary"' : '' ?>><?= tdIcon('play') ?><?= tde('btn_activate') ?></button>
                                                </form>
                                            <?php else: ?>
                                                <?php if (!$isAsync && ($state === 'live' || $state === 'paused')): ?>
                                                    <form method="POST" action="/teacher/dashboard.php?course_id=<?= (int)$selectedCourse['id'] ?>&action=toggle_live_pause">
                                                        <?= csrfInput(); ?>
                                                        <input type="hidden" name="session_id" value="<?= $sid ?>">
                                                        <?php if ($state === 'paused'): ?>
                                                            <button type="submit" class="t-btn t-btn-primary"><?= tdIcon('play') ?><?= tde('btn_resume') ?></button>
                                                        <?php else: ?>
                                                            <button type="submit" class="t-btn t-btn-ghost"><?= tdIcon('pause') ?><?= tde('btn_pause') ?></button>
                                                        <?php endif; ?>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" action="/teacher/dashboard.php?course_id=<?= (int)$selectedCourse['id'] ?>&action=toggle_live_session">
                                                    <?= csrfInput(); ?>
                                                    <input type="hidden" name="session_id" value="<?= $sid ?>">
                                                    <input type="hidden" name="status" value="0">
                                                    <button type="submit" class="t-btn t-btn-ghost"
                                                        <?= ($state === 'live' || $state === 'paused') ? 'data-confirm="' . tde('confirm_deactivate') . '" data-confirm-title="' . tde('confirm_deactivate_title') . '" data-confirm-ok="' . tde('btn_deactivate') . '" data-confirm-kind="danger"' : '' ?>><?= tde('btn_deactivate') ?></button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <button type="button" class="t-btn t-btn-ghost"
                                                data-session="<?= htmlspecialchars(json_encode([
                                                    "id" => $ls["id"],
                                                    "title" => $ls["title"],
                                                    "start_time" => date("Y-m-d\TH:i", strtotime($ls["start_time"])),
                                                    "end_time" => date("Y-m-d\TH:i", strtotime($ls["end_time"])),
                                                    "default_time_limit" => $ls["default_time_limit"],
                                                    "is_async" => $ls["is_async"] ?? 0,
                                                    "shuffle_options" => $ls["shuffle_options"] ?? 0,
                                                    "integrity_watch" => $ls["integrity_watch"] ?? 0,
                                                    "async_deadline" => isset($ls["async_deadline"]) && $ls["async_deadline"] ? date("Y-m-d\TH:i", strtotime($ls["async_deadline"])) : ""
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openEditLiveSessionModal(this)"><?= tdIcon('edit') ?><?= tde('btn_edit') ?></button>
                                    </div>
                                </div>

                                <div class="grp">
                                    <small><?= tde('grp_export') ?></small>
                                    <div class="row">
                                        <details class="t-menu">
                                            <summary class="t-btn t-btn-ghost"><?= tdIcon('download') ?><?= tde('btn_export') ?><span style="width:.9rem;height:.9rem;display:inline-block"><?= tdIcon('chev') ?></span></summary>
                                            <div class="t-menu-list">
                                                <a href="#" onclick="SVReview.open(<?= $sid ?>); return false;"><?= tde('rv_open') ?></a>
                                                <a href="/teacher/live-analysis.php?session_id=<?= $sid ?>"><?= tde('live_analysis') ?></a>
                                                <hr>
                                                <small><?= tde('exp_grades') ?></small>
                                                <a href="/teacher/export-live-grades.php?session_id=<?= $sid ?>"><?= tde('exp_excel') ?></a>
                                                <a href="/teacher/export-live-pdf.php?session_id=<?= $sid ?>"><?= tde('exp_pdf') ?></a>
                                                <a href="/teacher/export-live-grades-latex.php?session_id=<?= $sid ?>"><?= tde('exp_latex') ?></a>
                                                <a href="/teacher/export-live-grades-latex.php?session_id=<?= $sid ?>&format=tex"><?= tde('exp_tex') ?></a>
                                                <a href="#" onclick="openScoreConverterModal(<?= $sid ?>); return false;"><?= tde('exp_converted') ?></a>
                                                <?php if ($nQ > 0): ?>
                                                    <hr>
                                                    <small><?= tde('exp_paper') ?></small>
                                                    <a href="/teacher/export-live-questions-latex.php?session_id=<?= $sid ?>&mode=subject"><?= tde('exp_subject') ?></a>
                                                    <a href="/teacher/export-live-questions-latex.php?session_id=<?= $sid ?>&mode=correction"><?= tde('exp_correction') ?></a>
                                                    <a href="/teacher/export-live-questions-latex.php?session_id=<?= $sid ?>&mode=subject&format=tex"><?= tde('exp_subject_tex') ?></a>
                                                    <a href="/teacher/export-live-questions-latex.php?session_id=<?= $sid ?>&mode=correction&format=tex"><?= tde('exp_correction_tex') ?></a>
                                                    <a href="/teacher/download-async-csv.php?type=live&id=<?= $sid ?>"><?= tde('exp_questions_csv') ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    </div>
                                </div>

                                <div class="grp danger">
                                    <small><?= tde('grp_danger') ?></small>
                                    <div class="row">
                                        <form method="POST" action="/teacher/dashboard.php?course_id=<?= (int)$selectedCourse['id'] ?>&action=reset_live_session">
                                            <?= csrfInput(); ?>
                                            <input type="hidden" name="session_id" value="<?= $sid ?>">
                                            <button type="submit" class="t-btn t-btn-danger t-btn-sm"
                                                data-confirm="<?= htmlspecialchars(td('confirm_reset', ['n' => (int)$ls['participant_count']])) ?>"
                                                data-confirm-title="<?= tde('confirm_reset_title') ?>" data-confirm-ok="<?= tde('btn_reset') ?>" data-confirm-kind="danger"
                                                data-confirm-type="<?= (int)$ls['participant_count'] > 0 ? htmlspecialchars(td('confirm_type_word')) : '' ?>"><?= tdIcon('reset') ?><?= tde('btn_reset') ?></button>
                                        </form>
                                        <form method="POST" action="/teacher/dashboard.php?course_id=<?= (int)$selectedCourse['id'] ?>&action=delete_live_session">
                                            <?= csrfInput(); ?>
                                            <input type="hidden" name="session_id" value="<?= $sid ?>">
                                            <button type="submit" class="t-btn t-btn-danger t-btn-sm"
                                                data-confirm="<?= tde('confirm_delete_session') ?>" data-confirm-title="<?= tde('confirm_delete_session_title') ?>" data-confirm-ok="<?= tde('btn_delete') ?>" data-confirm-kind="danger"><?= tdIcon('trash') ?><?= tde('btn_delete') ?></button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="t-link-row">
                                <span style="color:var(--ink-3);white-space:nowrap"><?= tde('room_link') ?></span>
                                <code class="num" title="<?= htmlspecialchars($sessionLink) ?>"><?= htmlspecialchars($sessionLink) ?></code>
                                <button type="button" onclick="copyToClipboard(<?= htmlspecialchars(json_encode($sessionLink), ENT_QUOTES) ?>)" class="t-btn t-btn-ghost t-btn-sm"><?= tde('btn_copy') ?></button>
                            </div>

                            <div class="t-room-panels">
                                <div class="t-tabs" role="tablist">
                                    <button type="button" role="tab" id="rtab-q-<?= $sid ?>" aria-selected="true" aria-controls="live-session-questions-<?= $sid ?>" onclick="roomTab(<?= $sid ?>,'questions')"><?= tde('tab_questions') ?> (<span class="questions-count-<?= $sid ?>"><?= $nQ ?></span>)</button>
                                    <button type="button" role="tab" id="rtab-p-<?= $sid ?>" aria-selected="false" aria-controls="live-session-results-<?= $sid ?>" onclick="roomTab(<?= $sid ?>,'results')"><?= tde('tab_participants') ?> (<?= count($participants) ?>)</button>
                                </div>

                                <!-- Questions -->
                                <div id="live-session-questions-<?= $sid ?>" class="t-panel" role="tabpanel" aria-labelledby="rtab-q-<?= $sid ?>">
                                    <?php if (empty($ls['questions'])): ?>
                                        <p class="t-hint" style="margin-bottom:1rem;font-size:1rem"><?= tde('q_empty') ?></p>
                                    <?php else: ?>
                                        <ol class="t-qlist" style="list-style:none;margin:0 0 1.5rem;padding:0;border-top:1px solid var(--line)">
                                            <?php foreach ($ls['questions'] as $qIdx => $q):
                                                $qType = $q['question_type'] ?? 'mcq';
                                            ?>
                                                <li style="display:grid;grid-template-columns:2rem 1fr auto;gap:.5rem 1rem;padding:.9rem 0;border-bottom:1px solid var(--line);align-items:start" data-q-step="<?= $qIdx ?>">
                                                    <span class="num" style="font-weight:600;color:var(--ink-3)"><?= $qIdx + 1 ?></span>
                                                    <div style="min-width:0">
                                                        <div style="font-weight:500"><?= htmlspecialchars($q['question_text']) ?></div>
                                                        <?php if ($qType === 'mcq'): ?>
                                                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));gap:.15rem 1rem;font-size:.875rem;color:var(--ink-2);margin-top:.35rem">
                                                                <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $L => $col): ?>
                                                                    <span style="<?= strtoupper((string)$q['correct_option']) === $L ? 'color:var(--ok);font-weight:600' : '' ?>"><?= $L ?>. <?= htmlspecialchars((string)$q[$col]) ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div style="display:flex;flex-wrap:wrap;gap:.25rem 1rem;font-size:.8125rem;color:var(--ink-3);margin-top:.4rem">
                                                            <span class="t-chip" style="padding:0 .5rem"><?= $qType === 'written' ? tde('q_type_written') : tde('q_type_mcq') ?></span>
                                                            <span><?= tde('q_answer') ?> <strong style="color:var(--ink)"><?= htmlspecialchars((string)$q['correct_option']) ?></strong></span>
                                                            <?php if ($q['time_limit']): ?><span class="num"><?= (int)$q['time_limit'] ?> s</span><?php endif; ?>
                                                            <?php if (!empty($q['explanation'])): ?><span><?= tde('q_explanation') ?> <?= htmlspecialchars($q['explanation']) ?></span><?php endif; ?>
                                                            <?php if ($q['image_path']): ?><a href="<?= htmlspecialchars(mediaUrl('live_question', (string)$q['image_path'])) ?>" target="_blank" rel="noopener" style="color:var(--clay)"><?= tde('q_image') ?></a><?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <form method="POST" action="/teacher/dashboard.php?course_id=<?= (int)$selectedCourse['id'] ?>&action=delete_live_question">
                                                        <?= csrfInput(); ?>
                                                        <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                                                        <button type="submit" class="icon-btn danger" title="<?= tde('q_delete') ?>" aria-label="<?= tde('q_delete') ?>"
                                                            data-confirm="<?= tde('confirm_delete_q') ?>" data-confirm-title="<?= tde('confirm_delete_q_title') ?>" data-confirm-ok="<?= tde('btn_delete') ?>" data-confirm-kind="danger">
                                                            <span style="width:1rem;height:1rem;display:block"><?= tdIcon('trash') ?></span>
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    <?php endif; ?>

                                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:2rem">
                                        <form method="POST" action="/teacher/dashboard.php?course_id=<?= (int)$selectedCourse['id'] ?>&action=add_live_question" enctype="multipart/form-data">
                                            <?= csrfInput(); ?>
                                            <input type="hidden" name="session_id" value="<?= $sid ?>">
                                            <h4 style="font-size:1.25rem;margin-bottom:1rem"><?= tde('q_add_title') ?></h4>
                                            <div class="t-field">
                                                <label for="qt-<?= $sid ?>"><?= tde('q_type') ?></label>
                                                <select id="qt-<?= $sid ?>" name="question_type" onchange="handleQuestionTypeChange(this)">
                                                    <option value="mcq"><?= tde('q_type_mcq_long') ?></option>
                                                    <option value="written"><?= tde('q_type_written_long') ?></option>
                                                </select>
                                            </div>
                                            <div class="t-field">
                                                <label for="qx-<?= $sid ?>"><?= tde('q_text') ?></label>
                                                <textarea id="qx-<?= $sid ?>" name="question_text" required rows="3"></textarea>
                                                <span class="t-hint"><?= tde('q_text_hint') ?></span>
                                            </div>
                                            <div class="mcq-options-group">
                                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 .75rem">
                                                    <?php foreach (['a', 'b', 'c', 'd'] as $L): ?>
                                                        <div class="t-field">
                                                            <label for="qo<?= $L ?>-<?= $sid ?>"><?= tde('q_option') ?> <?= strtoupper($L) ?></label>
                                                            <input type="text" id="qo<?= $L ?>-<?= $sid ?>" name="option_<?= $L ?>">
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 .75rem">
                                                <div class="t-field correct-option-container">
                                                    <label for="qc-<?= $sid ?>"><?= tde('q_correct') ?></label>
                                                    <select id="qc-<?= $sid ?>" name="correct_option" required>
                                                        <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                                                    </select>
                                                </div>
                                                <div class="t-field">
                                                    <label for="qd-<?= $sid ?>"><?= tde('q_duration') ?></label>
                                                    <input type="number" min="5" id="qd-<?= $sid ?>" name="time_limit">
                                                    <span class="t-hint"><?= td('q_duration_hint', ['n' => (int)$ls['default_time_limit']]) ?></span>
                                                </div>
                                            </div>
                                            <div class="t-field">
                                                <label for="qe-<?= $sid ?>"><?= tde('q_explain') ?></label>
                                                <textarea id="qe-<?= $sid ?>" name="explanation" rows="2"></textarea>
                                            </div>
                                            <div class="t-field">
                                                <label for="qi-<?= $sid ?>"><?= tde('q_image_label') ?></label>
                                                <input type="file" id="qi-<?= $sid ?>" name="live_image" accept="image/*">
                                            </div>
                                            <button type="submit" class="t-btn t-btn-primary"><?= tde('q_add_btn') ?></button>
                                        </form>

                                        <div>
                                            <h4 style="font-size:1.25rem;margin-bottom:.6rem"><?= tde('q_import_title') ?></h4>
                                            <p style="color:var(--ink-2);font-size:.95rem;margin-bottom:.75rem"><?= tde('q_import_lede') ?></p>
                                            <p style="font-size:.875rem;color:var(--ink-3);margin-bottom:1rem"><?= tde('q_import_cols') ?> <code>question, type, option_a, option_b, option_c, option_d, correct, explanation</code>. <?= tde('q_import_type') ?></p>
                                            <div class="t-field">
                                                <label for="qf-<?= $sid ?>"><?= tde('q_import_file') ?></label>
                                                <input type="file" id="qf-<?= $sid ?>" accept=".csv,.xlsx,.xls,.txt" onchange="importLiveQuestionsFile(this, <?= $sid ?>)">
                                                <span class="t-hint"><?= tde('q_import_note') ?></span>
                                            </div>
                                            <a href="/teacher/sample-questions.csv" download class="t-link"><?= tde('q_import_sample') ?></a>

                                            <?php if (!empty($ls['questions'])): ?>
                                                <form method="POST" action="/teacher/dashboard.php?course_id=<?= (int)$selectedCourse['id'] ?>&action=delete_all_live_questions" style="margin-top:2rem;padding-top:1.25rem;border-top:1px solid var(--line)">
                                                    <?= csrfInput(); ?>
                                                    <input type="hidden" name="session_id" value="<?= $sid ?>">
                                                    <button type="submit" class="t-btn t-btn-danger t-btn-sm"
                                                        data-confirm="<?= tde('confirm_delete_all_q') ?>" data-confirm-title="<?= tde('confirm_delete_all_q_title') ?>" data-confirm-ok="<?= tde('q_delete_all') ?>" data-confirm-kind="danger"><?= tdIcon('trash') ?><?= tde('q_delete_all') ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Participants -->
                                <div id="live-session-results-<?= $sid ?>" class="t-panel hidden" role="tabpanel" aria-labelledby="rtab-p-<?= $sid ?>">
                                    <?php if (empty($participants)): ?>
                                        <p class="t-hint" style="font-size:1rem"><?= tde('part_empty') ?></p>
                                    <?php else: ?>
                                        <div class="t-tablebar">
                                            <input type="search" data-table-filter="part-table-<?= $sid ?>" aria-label="<?= tde('table_search') ?>" placeholder="<?= tde('table_search') ?>">
                                            <span class="t-hint num"><span data-table-count="part-table-<?= $sid ?>"><?= count($participants) ?></span> <?= tde('part_count_label') ?></span>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table id="part-table-<?= $sid ?>">
                                                <thead>
                                                    <tr>
                                                        <th data-sort="text"><?= tde('col_name') ?></th>
                                                        <th data-sort="text"><?= tde('col_email') ?></th>
                                                        <th data-sort="num" class="text-right"><?= tde('col_score') ?></th>
                                                        <th data-sort="text"><?= tde('col_presence') ?></th>
                                                        <th data-sort="num"><?= tde('col_registered_at') ?></th>
                                                        <th><span class="sr-only"><?= tde('col_actions') ?></span></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($participants as $p):
                                                        $isOnline = $p['last_activity'] ? (time() - strtotime($p['last_activity']) <= 10) : false;
                                                    ?>
                                                        <tr>
                                                            <td style="font-weight:600"><?= htmlspecialchars($p['name']) ?></td>
                                                            <td style="color:var(--ink-2)"><?= htmlspecialchars($p['email']) ?></td>
                                                            <td class="text-right" data-v="<?= $p['score'] !== null ? (float)$p['score'] : -1 ?>">
                                                                <?php if ($p['score'] !== null): ?>
                                                                    <strong><?= number_format((float)$p['score'], 2) ?></strong><span style="color:var(--ink-3)"> / 20</span>
                                                                <?php else: ?><span style="color:var(--ink-3)"><?= tde('score_pending') ?></span><?php endif; ?>
                                                            </td>
                                                            <td data-v="<?= $isOnline ? 1 : 0 ?>">
                                                                <span class="t-chip <?= $isOnline ? 'live' : 'off' ?>"><i></i><?= $isOnline ? tde('presence_online') : tde('presence_offline') ?></span>
                                                            </td>
                                                            <td class="num" data-v="<?= strtotime($p['registered_at']) ?>"><?= date('d/m/Y H:i', strtotime($p['registered_at'])) ?></td>
                                                            <td class="text-right">
                                                                <form method="POST" action="/teacher/dashboard.php?course_id=<?= (int)$selectedCourse['id'] ?>&action=delete_live_participant" style="display:inline">
                                                                    <?= csrfInput(); ?>
                                                                    <input type="hidden" name="registration_id" value="<?= (int)$p['id'] ?>">
                                                                    <input type="hidden" name="session_id" value="<?= $sid ?>">
                                                                    <button type="submit" class="t-btn t-btn-danger t-btn-sm"
                                                                        data-confirm="<?= tde('confirm_exclude') ?>" data-confirm-title="<?= tde('confirm_exclude_title') ?>" data-confirm-ok="<?= tde('btn_exclude') ?>" data-confirm-kind="danger"><?= tde('btn_exclude') ?></button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 5. NOTES & SUIVI (tab-grades) -->
            <div id="tab-grades" class="tab-content hidden" data-title="<?= tde('nav_grades') ?>">
                <header class="t-head">
                    <div>
                        <p class="t-kicker"><?= tde('nav_g_follow') ?></p>
                        <h2><?= tde('grades_title') ?></h2>
                        <p><?= tde('grades_lede') ?></p>
                    </div>
                </header>

                <div class="t-tabs" role="tablist" style="padding:0;margin-bottom:1.5rem">
                    <button type="button" role="tab" onclick="switchGradesSubTab('subtab-students')" id="btn-subtab-students" aria-selected="true"><?= tde('gtab_students') ?></button>
                    <button type="button" role="tab" onclick="switchGradesSubTab('subtab-quiz')" id="btn-subtab-quiz" aria-selected="false"><?= tde('gtab_quiz') ?></button>
                    <button type="button" role="tab" onclick="switchGradesSubTab('subtab-certs')" id="btn-subtab-certs" aria-selected="false"><?= tde('gtab_certs') ?></button>
                </div>

                <!-- Sub-tab 1 : Élèves Inscrits -->
                <div id="subtab-students" class="grades-subtab space-y-4">
                    <div class="t-tablebar">
                        <input type="search" data-table-filter="students-table" aria-label="<?= tde('table_search') ?>" placeholder="<?= tde('table_search') ?>">
                        <?= tdExportGroup('students-table', 'apprenants') ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse" id="students-table">
                            <thead>
                                <tr class="bg-[var(--paper-2)] border-b border-[var(--line)]">
                                    <th class="p-4 font-semibold text-[var(--ink)]">Matricule</th>
                                    <th class="p-4 font-semibold text-[var(--ink)]">Nom complet</th>
                                    <th class="p-4 font-semibold text-[var(--ink)] text-center">Leçons terminées</th>
                                    <th class="p-4 font-semibold text-[var(--ink)] text-center">Progression</th>
                                    <th class="p-4 font-semibold text-[var(--ink)] text-center">Moyenne quiz</th>
                                    <th class="p-4 font-semibold text-[var(--ink)] text-center">Devoirs rendus</th>
                                    <th class="p-4 font-semibold text-[var(--ink)]">Leçon en cours</th>
                                </tr>
                            </thead>
                            <tbody id="registered-students-list-body" class="divide-y divide-[var(--line)]">
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-[var(--ink-3)] italic">Chargement des inscriptions...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sub-tab 2 : Notes des Quiz -->
                <div id="subtab-quiz" class="grades-subtab hidden space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 bg-[var(--card)] p-5 border border-[var(--line)] rounded-sm">
                        <div class="flex flex-wrap items-center gap-6 text-xs text-[var(--ink-2)]" id="lesson-grades-summary">
                            <!-- Rempli en JS -->
                        </div>
                    </div>
                    <div class="border border-[var(--line)] rounded-sm divide-y divide-[var(--line)] overflow-hidden" id="lesson-grades-accordion-container">
                        <!-- Rempli en JS -->
                    </div>
                </div>

                <!-- Sub-tab 3 : Certifications & Module -->
                <div id="subtab-certs" class="grades-subtab hidden space-y-8">
                    <!-- Tentatives de Certification Globale -->
                    <div class="space-y-4">
                        <h4 class="text-xs font-semibold text-[var(--ink-2)]  ">Tentatives de Certification Globale (QCM Final)</h4>
                        <div class="overflow-x-auto border border-[var(--line)] rounded-sm bg-[var(--card)]">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-[var(--paper-2)] border-b border-[var(--line)]">
                                        <th class="p-4 font-semibold text-[var(--ink)]">Nom complet</th>
                                        <th class="p-4 font-semibold text-[var(--ink)]">E-mail</th>
                                        <th class="p-4 font-semibold text-[var(--ink)] text-center">Score obtenu</th>
                                        <th class="p-4 font-semibold text-[var(--ink)] text-center">Statut</th>
                                        <th class="p-4 font-semibold text-[var(--ink)] text-right">Date tentative</th>
                                    </tr>
                                </thead>
                                <tbody id="certifications-course-body" class="divide-y divide-[var(--line)]">
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-[var(--ink-3)] italic">Chargement des certifications globales...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Certificats de Module Émis -->
                    <div class="space-y-4">
                        <h4 class="text-xs font-semibold text-[var(--ink-2)]  ">Certificats de Module Émis (PDFs)</h4>
                        <div class="overflow-x-auto border border-[var(--line)] rounded-sm bg-[var(--card)]">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-[var(--paper-2)] border-b border-[var(--line)]">
                                        <th class="p-4 font-semibold text-[var(--ink)]">Nom complet</th>
                                        <th class="p-4 font-semibold text-[var(--ink)]">E-mail</th>
                                        <th class="p-4 font-semibold text-[var(--ink)]">Code de vérification</th>
                                        <th class="p-4 font-semibold text-[var(--ink)]">Date d'émission</th>
                                        <th class="p-4 font-semibold text-[var(--ink)] text-right">Attribution Manuelle</th>
                                    </tr>
                                </thead>
                                <tbody id="certifications-module-body" class="divide-y divide-[var(--line)]">
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-[var(--ink-3)] italic">Chargement des certificats de module...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── QCM Final de Certification ────────────────── -->
                    <div class="border-t border-[var(--line)]  pt-8 space-y-8">
                        <div class="flex justify-between items-start gap-4">
                            <div class="space-y-2">
                                <h3 class="font-serif text-2xl font-light text-[var(--ink)] ">Évaluation Finale du Cours</h3>
                                <p class="text-sm font-light text-[var(--ink-2)]  max-w-xl">
                                    Ce QCM est accessible uniquement à l'étudiant ayant complété 100% du cours.
                                    Score minimum requis : <span class="font-semibold text-[var(--clay)] ">80%</span>.
                                </p>
                                <div class="text-xs num ">
                                    Questions actuelles :
                                    <span class="<?= $finalExamQuestionCount >= 30 ? 'text-[var(--clay)] ' : 'text-[var(--danger)]'; ?> font-semibold">
                                        <?= $finalExamQuestionCount; ?> / 30 minimum
                                    </span>
                                    <?php if ($finalExamQuestionCount < 30): ?>
                                        <span class="text-[var(--danger)] italic block mt-1">Au moins 30 questions requises pour validation.</span>
                                    <?php else: ?>
                                        <span class="text-[var(--clay)]  block mt-1">L'évaluation finale est prête.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="toggleModal('course-question-modal')"
                                    class="flex-shrink-0 px-4 py-2 bg-[var(--ink)]  text-white  text-xs font-semibold   hover:bg-[var(--clay)]  transition-colors rounded-sm">
                                    + Question Finale
                                </button>
                                <button type="button" onclick="openImportModal('course')"
                                    class="flex-shrink-0 px-4 py-2 border border-[var(--line)]  text-xs font-semibold   hover:border-[var(--clay)]  rounded-sm ">
                                    Importer CSV/Excel
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($finalQuestions)): ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto border border-[var(--line)]  divide-y divide-[var(--line)]  bg-[var(--paper-2)] ">
                            <?php foreach ($finalQuestions as $index => $fq): ?>
                            <div class="px-4 py-3 text-sm font-light flex justify-between items-start gap-4">
                                <div class="flex-grow min-w-0">
                                    <div class="font-medium text-[var(--ink)]  text-sm">
                                        <?= ($index + 1); ?>. <?= htmlspecialchars($fq['question_text']); ?>
                                    </div>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 mt-2 text-xs text-[var(--ink-2)] ">
                                        <div>A. <?= htmlspecialchars($fq['option_a']); ?></div>
                                        <div>B. <?= htmlspecialchars($fq['option_b']); ?></div>
                                        <div>C. <?= htmlspecialchars($fq['option_c']); ?></div>
                                        <div>D. <?= htmlspecialchars($fq['option_d']); ?></div>
                                    </div>
                                    <div class="mt-1.5 text-xs num text-[var(--clay)]  font-semibold">
                                        Réponse : <?= htmlspecialchars($fq['correct_option']); ?>
                                    </div>
                                </div>
                                <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" class="flex-shrink-0">
                                    <?= csrfInput(); ?>
                                    <input type="hidden" name="action" value="delete_course_question">
                                    <input type="hidden" name="question_id" value="<?= $fq['id']; ?>">
                                    <button type="submit" class="icon-btn danger" title="Supprimer la question"
                                        onclick="return confirm('Supprimer cette question de certification ?')">
                                        <svg class="w-3 h-3 text-[var(--danger)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 5. DÉPÔT DES DEVOIRS (tab-assignments) -->
            <div id="tab-assignments" class="tab-content hidden" data-title="<?= tde('nav_assign') ?>">
                <header class="t-head">
                    <div>
                        <p class="t-kicker"><?= tde('nav_g_follow') ?></p>
                        <h2><?= tde('asg_title') ?></h2>
                        <p><?= tde('asg_lede') ?></p>
                    </div>
                    <div class="t-actions">
                        <div class="t-export" role="group" aria-label="<?= tde('export') ?>"><span><?= tde('export') ?></span>
                            <a href="/teacher/download-assignments-excel.php?course_id=<?= $selectedCourseId; ?>"><?= tde('asg_exp_csv') ?></a>
                            <a href="/teacher/download-assignments-zip.php?course_id=<?= $selectedCourseId; ?>"><?= tde('asg_exp_zip') ?></a>
                        </div>
                    </div>
                </header>

                <div class="t-stats" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:2rem">
                    <div><span class="n"><?= count($configuredAssignmentLessons); ?></span><span class="l"><?= tde('asg_s_conf') ?></span></div>
                    <div><span class="n"><?= count($teacherAssignments); ?></span><span class="l"><?= tde('asg_s_total') ?></span></div>
                    <div><span class="n"><?= count(array_filter($teacherAssignments, fn($a) => !empty($a['submitted_file_path']))); ?></span><span class="l"><?= tde('asg_s_files') ?></span></div>
                    <div><span class="n"><?= count(array_filter($teacherAssignments, fn($a) => !empty($a['submitted_link']))); ?></span><span class="l"><?= tde('asg_s_links') ?></span></div>
                </div>

                <!-- Assignment Lessons Grouped by Chapter & Lesson -->
                <?php if (empty($configuredAssignmentLessons)): ?>
                    <div class="t-empty boxed">
                        <p><?= tde('asg_empty') ?></p>
                        <button type="button" class="t-btn t-btn-primary" onclick="switchDashboardTab('tab-course')"><?= tde('asg_empty_btn') ?></button>
                    </div>
                    <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($configuredAssignmentLessons as $lesAsg): 
                            $subs = $lesAsg['submissions'] ?? [];
                            $asgType = $lesAsg['assignment_type'] ?? 'both';
                            $allowedFmts = strtoupper($lesAsg['allowed_file_types'] ?? 'pdf, docx');
                        ?>
                            <div class="bg-[var(--card)]  border border-[var(--line)]  rounded-lg overflow-hidden shadow-sm">
                                <!-- En-tête de la Leçon (Chapitre > Leçon) -->
                                <div class="bg-[var(--paper)]  p-4 border-b border-[var(--line)]  flex flex-col md:flex-row md:items-center justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="px-2 py-0.5 text-xs num  bg-[var(--clay-soft)] text-[var(--clay)]   font-bold rounded">
                                                <?= htmlspecialchars($lesAsg['chapter_title']); ?>
                                            </span>
                                            <h4 class="font-semibold text-sm text-[var(--ink)] ">
                                                <?= htmlspecialchars($lesAsg['title']); ?>
                                            </h4>
                                            <?php if (!empty($lesAsg['assignment_title'])): ?>
                                                <span class="text-xs italic text-[var(--ink-2)] ">
                                                    — "<?= htmlspecialchars($lesAsg['assignment_title']); ?>"
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1.5 flex-wrap text-[11px] text-[var(--ink-2)] ">
                                            <span class="font-medium">Modalité :</span>
                                            <?php if ($asgType === 'file'): ?>
                                                <span class="px-1.5 py-0.5 t-bg-ok-soft t-tx-ok   rounded font-semibold text-xs">Document Obligatoire</span>
                                            <?php elseif ($asgType === 'link'): ?>
                                                <span class="px-1.5 py-0.5 t-bg-info-soft t-tx-info   rounded font-semibold text-xs">Lien Web Obligatoire</span>
                                            <?php else: ?>
                                                <span class="px-1.5 py-0.5 t-bg-info-soft t-tx-info   rounded font-semibold text-xs">Document ou Lien</span>
                                            <?php endif; ?>

                                            <span class="text-[var(--ink-3)] ">•</span>
                                            <span>Formats : <strong class="num text-[var(--ink)] "><?= htmlspecialchars($allowedFmts); ?></strong></span>

                                            <?php if (!empty($lesAsg['assignment_deadline'])): ?>
                                                <span class="text-[var(--ink-3)] ">•</span>
                                                <span>Limite : <strong class="num t-tx-warn "><?= date('d/m/Y H:i', strtotime($lesAsg['assignment_deadline'])); ?></strong></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <a href="/teacher/download-assignments-excel.php?lesson_id=<?= $lesAsg['id']; ?>" 
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[var(--clay)] text-white hover:bg-[var(--clay-press)] text-[11px] font-semibold   rounded transition-colors" title="Exporter cette leçon en Excel">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Excel (.csv)
                                        </a>
                                        <a href="/teacher/download-assignments-zip.php?lesson_id=<?= $lesAsg['id']; ?>" 
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[var(--clay)] text-[var(--clay)] hover:bg-[var(--clay-soft)] text-[11px] font-semibold   rounded transition-colors" title="Télécharger tous les fichiers de cette leçon en ZIP">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Fichiers (.zip)
                                        </a>
                                    </div>
                                </div>

                                <?php if (!empty($lesAsg['assignment_instructions'])): ?>
                                    <div class="px-5 py-3 t-bg-warn-soft  border-b border-[var(--line)]  text-xs text-[var(--ink-2)] ">
                                        <strong class="font-semibold text-[var(--ink)]  block mb-1">Consigne du devoir :</strong>
                                        <div class="assignment-instructions-render math-render space-y-1" data-instructions="<?= htmlspecialchars($lesAsg['assignment_instructions']); ?>">
                                            <?= nl2br(htmlspecialchars($lesAsg['assignment_instructions'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Tableau des soumissions pour cette leçon -->
                                <?php if (empty($subs)): ?>
                                    <div class="p-6 text-center text-xs text-[var(--ink-3)]  italic">
                                        Aucune soumission d'étudiant enregistrée pour le moment pour cette leçon.
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left border-collapse">
                                            <thead>
                                                <tr class="bg-[var(--paper-2)]  border-b border-[var(--line)]  text-xs font-semibold   text-[var(--ink-2)] ">
                                                    <th class="py-2.5 px-4">Élève</th>
                                                    <th class="py-2.5 px-4">Lien Projet / App</th>
                                                    <th class="py-2.5 px-4">Fichier Rendu</th>
                                                    <th class="py-2.5 px-4">Date de Dépôt</th>
                                                    <th class="py-2.5 px-4">Commentaire Élève</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-[var(--line)]  text-xs">
                                                <?php foreach ($subs as $asg): ?>
                                                    <tr class="hover:bg-[var(--paper)]  transition-colors">
                                                        <td class="py-3 px-4">
                                                            <div class="flex items-center gap-3">
                                                                <div class="w-7 h-7 rounded-full bg-[var(--clay)] text-white font-bold flex items-center justify-center text-xs flex-shrink-0">
                                                                    <?= strtoupper(substr($asg['student_name'] ?? 'E', 0, 1)); ?>
                                                                </div>
                                                                <div>
                                                                    <div class="font-semibold text-[var(--ink)] ">
                                                                        <?= htmlspecialchars($asg['declared_student_name'] ?: $asg['student_name']); ?>
                                                                    </div>
                                                                    <?php if (!empty($asg['student_matricule'])): ?>
                                                                        <div class="text-xs num font-bold text-[var(--clay)] ">
                                                                            Matricule : <?= htmlspecialchars($asg['student_matricule']); ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div class="text-xs text-[var(--ink-3)] "><?= htmlspecialchars($asg['student_email']); ?></div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="py-3 px-4">
                                                            <?php if (!empty($asg['submitted_link'])): ?>
                                                                <a href="<?= htmlspecialchars($asg['submitted_link']); ?>" target="_blank" rel="noopener noreferrer" 
                                                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 t-bg-info-soft  t-tx-info  hover:underline rounded num text-[11px]">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                                    Consulter le lien
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-[var(--ink-3)] italic">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="py-3 px-4">
                                                            <?php if (!empty($asg['submitted_file_path'])): 
                                                                $ext = strtolower(pathinfo($asg['submitted_file_path'], PATHINFO_EXTENSION));
                                                                $isPdf = $ext === 'pdf';
                                                            ?>
                                                                <a href="/download.php?type=assignment&file=<?= rawurlencode($asg['submitted_file_path']); ?>" 
                                                                   target="_blank" 
                                                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 <?= $isPdf ? 't-bg-danger-soft t-tx-danger  ' : 't-bg-ok-soft t-tx-ok  ' ?> hover:underline rounded text-[11px] font-medium">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                                    <?= htmlspecialchars($asg['submitted_file_name'] ?: 'Fichier ' . strtoupper($ext)); ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-[var(--ink-3)] italic">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="py-3 px-4 text-[11px] text-[var(--ink-2)]  num">
                                                            <?= date('d/m/Y H:i', strtotime($asg['submitted_at'])); ?>
                                                        </td>
                                                        <td class="py-3 px-4 max-w-xs truncate text-[var(--ink-2)] " title="<?= htmlspecialchars($asg['student_comment'] ?? ''); ?>">
                                                            <?= !empty($asg['student_comment']) ? htmlspecialchars($asg['student_comment']) : '<span class="text-[var(--ink-3)] italic">—</span>'; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 6. COMMUNAUTÉ & Q&R (tab-comments) -->
            <div id="tab-comments" class="tab-content hidden" data-title="<?= tde('nav_qa') ?>">
                <header class="t-head">
                    <div>
                        <p class="t-kicker"><?= tde('nav_g_follow') ?></p>
                        <h2><?= tde('qa_title') ?></h2>
                        <p><?= tde('qa_lede') ?></p>
                    </div>
                    <?php if (!empty($courseComments)): ?>
                    <div class="t-tabs" role="group" style="padding:0;border:0" aria-label="<?= tde('qa_filter') ?>">
                        <button type="button" id="qa-f-open" aria-selected="<?= $tdUnanswered > 0 ? 'true' : 'false' ?>" onclick="qaFilter('open')"><?= tde('qa_f_open') ?> (<?= $tdUnanswered ?>)</button>
                        <button type="button" id="qa-f-all" aria-selected="<?= $tdUnanswered > 0 ? 'false' : 'true' ?>" onclick="qaFilter('all')"><?= tde('qa_f_all') ?> (<?= count($courseComments) ?>)</button>
                    </div>
                    <?php endif; ?>
                </header>

                <?php if (empty($courseComments)): ?>
                    <div class="t-empty boxed">
                        <p><?= tde('qa_empty') ?></p>
                        <button type="button" class="t-btn t-btn-ghost" onclick="switchDashboardTab('tab-course')"><?= tde('qa_empty_btn') ?></button>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($courseComments as $com): 
                            $comHidden = (int)$com['is_hidden'] === 1;
                        ?>
                            <div class="border border-[var(--line)] p-6 rounded-sm bg-[var(--card)] space-y-4 relative" data-qa-card data-unanswered="<?= (($com['author_role'] ?? '') === 'student' && empty($com['teacher_reply']) && !$comHidden) ? 1 : 0 ?>" id="qa-card-<?= (int)$com['id'] ?>">
                                <!-- Badge de statut -->
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-[11px]  font-bold px-2 py-0.5 rounded-sm bg-[var(--paper-2)] border border-[var(--line)] text-[var(--ink-2)]">
                                            Leçon : <?= htmlspecialchars($com['lesson_title']) ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-[var(--ink-3)] num"><?= date('d/m/Y H:i', strtotime($com['created_at'])) ?></span>
                                        <?php if (($com['author_role'] ?? '') === 'student' && empty($com['teacher_reply']) && !$comHidden): ?><span class="t-chip wait" data-qa-badge><?= tde('qa_unanswered') ?></span><?php endif; ?>
                                        <?php if ($comHidden): ?>
                                            <span class="px-2 py-0.5 text-[11px] font-bold   rounded-sm t-bg-danger-soft border t-bd-danger t-tx-danger">Masqué</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Corps du commentaire -->
                                <div>
                                    <p class="text-xs font-semibold text-[var(--ink)] mb-1"><?= htmlspecialchars($com['author_name']) ?> <span class="text-xs font-normal text-[var(--ink-3)] num">(<?= htmlspecialchars($com['author_role'] ?? 'student') ?>)</span></p>
                                    <p class="text-xs text-[var(--ink-2)] font-light leading-relaxed whitespace-pre-line"><?= htmlspecialchars($com['comment_text']) ?></p>
                                </div>

                                <!-- Réponses existantes -->
                                <?php if (!empty($com['teacher_reply'])): ?>
                                    <div class="bg-[var(--paper-2)] p-4 rounded-sm border-l-2 border-[var(--clay)] space-y-1">
                                        <p class="text-xs font-bold text-[var(--clay)]  ">Réponse de l'Enseignant</p>
                                        <p class="text-xs text-[var(--ink)] font-light leading-relaxed whitespace-pre-line"><?= htmlspecialchars($com['teacher_reply']) ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Actions Moderation & Réponse -->
                                <div class="pt-4 border-t border-[var(--paper-2)] flex flex-wrap gap-3 items-center justify-between">
                                    <div class="flex gap-2">
                                        <button onclick="moderateComment(<?= $com['id'] ?>, <?= $comHidden ? 0 : 1 ?>)" class="px-3 py-1 bg-[var(--card)] border border-[var(--line)] text-xs font-semibold   rounded-sm hover:bg-[var(--paper-2)] transition-colors">
                                            <?= $comHidden ? 'Afficher' : 'Masquer' ?>
                                        </button>
                                        <button onclick="toggleAccordion('reply-form-<?= $com['id'] ?>')" class="px-3 py-1 bg-[var(--ink)] text-white text-xs font-semibold   rounded-sm hover:bg-[var(--clay)] transition-colors">
                                            Répondre / Modifier la réponse
                                        </button>
                                    </div>
                                </div>

                                <!-- Formulaire de réponse -->
                                <div id="reply-form-<?= $com['id'] ?>" class="hidden pt-4 border-t border-[var(--line)] space-y-3">
                                    <textarea id="reply-text-<?= $com['id'] ?>" rows="3" placeholder="Saisissez votre réponse..." class="w-full px-3 py-2 border border-[var(--line)] rounded-sm focus:outline-none focus:border-[var(--clay)] text-xs bg-[var(--card)]"><?= htmlspecialchars($com['teacher_reply'] ?? '') ?></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button onclick="toggleAccordion('reply-form-<?= $com['id'] ?>')" class="px-3 py-1.5 border border-[var(--line)] text-xs font-semibold   rounded-sm text-[var(--ink-2)] bg-[var(--card)] hover:bg-[var(--paper-2)]">Annuler</button>
                                        <button onclick="submitReply(<?= $com['id'] ?>)" class="px-3 py-1.5 bg-[var(--clay)] text-white text-xs font-semibold   rounded-sm hover:bg-[var(--clay-press)]">Enregistrer</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
        </main>
        <footer class="t-foot"><span><?= tde('foot_l') ?></span><a href="/privacy.php"><?= tde('foot_r') ?></a></footer>
    </div><!-- /t-main -->
</div><!-- /t-shell -->

<!-- ══════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════ -->

<!-- ── Modal : Partager le cours ───────────────────────────── -->
<div id="share-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6 overflow-y-auto">
    <div class="bg-[var(--card)]  p-8 max-w-md w-full border border-[var(--line)]  space-y-6 modal-inner relative">
        <button type="button" onclick="closeShareModal()" class="absolute top-4 right-4 text-[var(--ink-3)] hover:text-[var(--ink)]  transition-colors" aria-label="Fermer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <h3 class="font-serif text-2xl font-light text-[var(--ink)] ">Partager le cours</h3>
        <p class="text-xs text-[var(--ink-2)]  font-light">Copiez le lien ci-dessous pour inviter des apprenants ou partager le cours.</p>
        
        <div class="flex items-center gap-2">
            <input type="text" id="share-url-input" readonly
                class="flex-grow px-3 py-2 bg-[var(--paper-2)]  border border-[var(--line)]  text-xs num focus:outline-none rounded-md text-[var(--ink)] ">
            <button id="share-copy-btn" onclick="copyShareLink()"
                class="px-4 py-2 bg-[var(--clay)]  text-white text-xs font-semibold   rounded-md hover:bg-[var(--clay)]  transition-colors flex-shrink-0">
                Copier
            </button>
        </div>
    </div>
</div>

<!-- ── Modal : Créer un cours ───────────────────────────── -->
<div id="create-course-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6 overflow-y-auto">
    <div class="bg-[var(--card)] p-8 max-w-lg w-full border border-[var(--line)] space-y-6 modal-inner my-8">
        <h3 class="font-serif text-2xl font-light">Créer un nouveau cours</h3>
        <p class="text-xs text-[var(--ink-2)] font-light">Le promoteur sera automatiquement informé et pourra réassigner ce cours si nécessaire.</p>
        <?php if (empty($modules)): ?>
            <p class="text-sm text-[var(--danger)]">Aucun module disponible. Demandez au promoteur de créer un module de formation.</p>
            <button type="button" onclick="toggleModal('create-course-modal')" class="sv-btn-ms-outline">Fermer</button>
        <?php else: ?>
        <form action="/teacher/dashboard.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="create_course">
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Module parent</label>
                <select name="module_id" required class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
                    <option value="">Choisir un module…</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= $m['id']; ?>"><?= htmlspecialchars($m['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Titre du cours</label>
                <input type="text" name="course_title" required placeholder="ex: Introduction à la logique pure"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Description</label>
                <textarea name="course_desc" rows="3" placeholder="Brève introduction au cours…"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Image de couverture (Optionnelle)</label>
                <input type="file" name="cover_image" accept="image/*"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Clé d'inscription (optionnelle)</label>
                <input type="text" name="enrollment_key" placeholder="ex: CODE2026"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm num">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Début</label>
                    <input type="date" name="start_date" class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Fin</label>
                    <input type="date" name="end_date" class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Deadline éval.</label>
                    <input type="date" name="eval_deadline" class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Durée QCM (min)</label>
                    <input type="number" name="exam_duration_minutes" value="90" min="30" max="180"
                        class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm rounded-sm">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="toggleModal('create-course-modal')" class="sv-btn-ms-outline">Annuler</button>
                <button type="submit" class="sv-btn-ms">Créer le cours</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- ── Modal : Éditer le cours ──────────────────────────── -->
<?php if ($selectedCourse): ?>
<div id="edit-course-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6 overflow-y-auto">
    <div class="bg-[var(--card)] p-8 max-w-lg w-full border border-[var(--line)] space-y-6 modal-inner my-8">
        <h3 class="font-serif text-2xl font-light">Éditer le cours</h3>
        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="edit_course">
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Titre</label>
                <input type="text" name="course_title" id="edit-course-title" required
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Description</label>
                <textarea name="course_description" id="edit-course-description" rows="4"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Image de couverture (Optionnelle)</label>
                <?php if ($selectedCourse && !empty($selectedCourse['cover_image'])): ?>
                    <div class="mb-2 flex items-center gap-3 bg-[var(--paper-2)]  p-2 rounded border border-[var(--line)] ">
                        <img src="/download.php?type=cover&file=<?= urlencode($selectedCourse['cover_image']); ?>" 
                             class="w-12 h-12 object-cover rounded shadow-sm">
                        <span class="text-xs text-[var(--ink-2)]  truncate">Image actuelle : <?= htmlspecialchars($selectedCourse['cover_image']) ?></span>
                    </div>
                <?php endif; ?>
                <input type="file" name="cover_image" accept="image/*"
                    class="w-full px-4 py-2 bg-[var(--paper-2)]  border border-[var(--line)]  text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Clé d'inscription (laisser vide pour accès libre)</label>
                <input type="text" name="enrollment_key" id="edit-course-key"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm num">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Début</label>
                    <input type="date" name="start_date" id="edit-course-start"
                        class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Fin</label>
                    <input type="date" name="end_date" id="edit-course-end"
                        class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Deadline éval.</label>
                    <input type="date" name="eval_deadline" id="edit-course-eval"
                        class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Durée QCM (min)</label>
                    <input type="number" name="exam_duration_minutes" id="edit-course-exam" min="30" max="180"
                        class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm rounded-sm">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="toggleModal('edit-course-modal')"
                    class="sv-btn-ms-outline">Annuler</button>
                <button type="submit"
                    class="sv-btn-ms">Sauvegarder</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal : Ajouter un chapitre ─────────────────────── -->
<div id="chapter-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6">
    <div class="bg-[var(--card)] p-8 max-w-md w-full border border-[var(--line)] space-y-6">
        <h3 class="font-serif text-2xl font-light">Nouveau Chapitre</h3>
        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_chapter">
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Titre du Chapitre</label>
                <input type="text" name="chapter_title" required placeholder="ex: Chapitre III — Modélisation formelle"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="toggleModal('chapter-modal')"
                    class="sv-btn-ms-outline">Annuler</button>
                <button type="submit"
                    class="sv-btn-ms">Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal : Éditer un chapitre ──────────────────────── -->
<div id="edit-chapter-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6">
    <div class="bg-[var(--card)] p-8 max-w-md w-full border border-[var(--line)] space-y-6">
        <h3 class="font-serif text-2xl font-light">Modifier le Chapitre</h3>
        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_chapter">
            <input type="hidden" name="chapter_id" id="edit-chapter-id">
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Titre du Chapitre</label>
                <input type="text" name="chapter_title" id="edit-chapter-title" required
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="toggleModal('edit-chapter-modal')"
                    class="sv-btn-ms-outline">Annuler</button>
                <button type="submit"
                    class="sv-btn-ms">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Forms cachés pour suppressions ──────────────────── -->
<form id="delete-chapter-form" method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" class="hidden">
    <input type="hidden" name="action" value="delete_chapter">
    <input type="hidden" name="chapter_id" id="delete-chapter-id">
</form>
<form id="delete-lesson-form" method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" class="hidden">
    <input type="hidden" name="action" value="delete_lesson">
    <input type="hidden" name="lesson_id" id="delete-lesson-id">
</form>

<!-- ── Modal : Ajouter / Éditer une leçon ──────────────── -->
<div id="lesson-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6">
    <div class="bg-[var(--card)] p-8 max-w-2xl w-full border border-[var(--line)] modal-inner">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-serif text-2xl font-light" id="lesson-modal-title">Nouvelle Leçon</h3>
            <button type="button" onclick="toggleModal('lesson-modal')" class="text-[var(--ink-3)] hover:text-[var(--danger)]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST"
            enctype="multipart/form-data" class="space-y-5" id="lesson-form">
            <input type="hidden" name="action" id="lesson-form-action" value="add_lesson">
            <input type="hidden" id="lesson-chapter-id" name="chapter_id" value="">
            <input type="hidden" id="lesson-edit-id" name="lesson_id" value="">

            <!-- Titre -->
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Titre de la leçon *</label>
                <input type="text" name="lesson_title" id="lesson-title-input" required
                    placeholder="ex: 1. Les théorèmes de Gödel"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
            </div>

            <!-- Type de contenu -->
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Type de support</label>
                <select name="content_type" id="content_type" onchange="toggleContentFields(this.value)"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
                    <option value="text">Texte riche</option>
                    <option value="pdf">Document PDF</option>
                    <option value="video">Vidéo(s)</option>
                    <option value="mixed">Mixte (texte + PDF + vidéo)</option>
                </select>
            </div>

            <!-- Caractère Obligatoire ou Optionnel -->
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)]  mb-2">Caractère de la leçon</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative flex items-center justify-between p-3 border border-[var(--line)]  rounded-sm cursor-pointer hover:bg-[var(--paper-2)]  transition-colors">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="is_compulsory" id="lesson-compulsory-1" value="1" checked class="text-[var(--clay)] focus:ring-0">
                            <span class="text-xs font-semibold text-[var(--ink)] ">Leçon Obligatoire</span>
                        </div>
                        <span class="text-xs bg-[var(--pine-soft)] text-[var(--clay)] px-2 py-0.5 rounded font-bold">Requise</span>
                    </label>
                    <label class="relative flex items-center justify-between p-3 border border-[var(--line)]  rounded-sm cursor-pointer hover:bg-[var(--paper-2)]  transition-colors">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="is_compulsory" id="lesson-compulsory-0" value="0" class="text-[var(--clay)] focus:ring-0">
                            <span class="text-xs font-semibold text-[var(--ink)] ">Leçon Optionnelle</span>
                        </div>
                        <span class="text-xs bg-[var(--paper-2)] text-[var(--ink-2)] px-2 py-0.5 rounded font-bold">Facultative</span>
                    </label>
                </div>
                <p class="text-xs text-[var(--ink-3)]  mt-1.5">
                    Une leçon obligatoire doit être complétée par l'étudiant avant de pouvoir tenter l'évaluation finale de certification.
                </p>
            </div>

            <!-- Date Limite d'Évaluation (Quiz) -->
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Date limite d'évaluation / quiz (optionnelle)</label>
                <input type="datetime-local" name="quiz_deadline" id="lesson-quiz-deadline-input"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
            </div>

            <!-- Contenu textuel -->
            <div id="field-text" class="space-y-3">
                <div class="flex justify-between items-center">
                    <label class="block text-xs font-semibold   text-[var(--ink-2)] ">
                        Contenu textuel de la leçon
                    </label>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-semibold bg-[var(--pine-soft)] text-[var(--clay)]   border border-[var(--clay)]">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Markdown &amp; LaTeX (KaTeX) supportés
                    </span>
                </div>

                <!-- Barre d'outils de formatage rapides -->
                <div class="flex flex-wrap gap-1.5 p-1.5 bg-[var(--paper-2)]  border border-[var(--line)]  rounded-sm text-xs">
                    <button type="button" onclick="insertFormatIntoLesson('bold')" title="Gras (**texte**)" class="px-2 py-1 bg-[var(--card)]  border border-[var(--line)]  text-[var(--ink)]  rounded hover:bg-[var(--paper-2)]  font-bold">B</button>
                    <button type="button" onclick="insertFormatIntoLesson('italic')" title="Italique (*texte*)" class="px-2 py-1 bg-[var(--card)]  border border-[var(--line)]  text-[var(--ink)]  rounded hover:bg-[var(--paper-2)]  italic">I</button>
                    <button type="button" onclick="insertFormatIntoLesson('h1')" title="Titre principal (#)" class="px-2 py-1 bg-[var(--card)]  border border-[var(--line)]  text-[var(--ink)]  rounded hover:bg-[var(--paper-2)]  font-semibold text-[11px]">H1</button>
                    <button type="button" onclick="insertFormatIntoLesson('h2')" title="Sous-titre (##)" class="px-2 py-1 bg-[var(--card)]  border border-[var(--line)]  text-[var(--ink)]  rounded hover:bg-[var(--paper-2)]  font-semibold text-[11px]">H2</button>
                    <button type="button" onclick="insertFormatIntoLesson('list')" title="Liste à puces (- )" class="px-2 py-1 bg-[var(--card)]  border border-[var(--line)]  text-[var(--ink)]  rounded hover:bg-[var(--paper-2)]  text-[11px]">• Liste</button>
                    <button type="button" onclick="insertFormatIntoLesson('quote')" title="Citation (> )" class="px-2 py-1 bg-[var(--card)]  border border-[var(--line)]  text-[var(--ink)]  rounded hover:bg-[var(--paper-2)]  text-[11px]">“ ”</button>
                    <button type="button" onclick="insertFormatIntoLesson('code')" title="Bloc de code (```)" class="px-2 py-1 bg-[var(--card)]  border border-[var(--line)]  text-[var(--ink)]  rounded hover:bg-[var(--paper-2)]  num text-xs">&lt;/&gt;</button>
                    <div class="h-5 w-px bg-[var(--line)]  my-auto mx-0.5"></div>
                    <button type="button" onclick="insertFormatIntoLesson('math_inline')" title="Formule LaTeX Inline ($ ... $)" class="px-2 py-1 bg-[var(--clay)] text-white rounded hover:bg-[var(--clay-press)] num text-[11px]">$x^2$</button>
                    <button type="button" onclick="insertFormatIntoLesson('math_display')" title="Formule LaTeX Centrée ($$ ... $$)" class="px-2 py-1 bg-[var(--clay)] text-white rounded hover:bg-[var(--clay-press)] num text-[11px]">$$\int$$</button>
                </div>

                <!-- Champ Texte & Onglets Éditer / Aperçu -->
                <div class="space-y-2">
                    <div class="flex border-b border-[var(--line)] ">
                        <button type="button" id="btn-lesson-tab-edit" onclick="switchLessonTextTab('edit')" class="px-3 py-1.5 text-xs font-semibold border-b-2 border-[var(--clay)] text-[var(--clay)]  ">
                            Édition Texte
                        </button>
                        <button type="button" id="btn-lesson-tab-preview" onclick="switchLessonTextTab('preview')" class="px-3 py-1.5 text-xs font-semibold text-[var(--ink-3)] hover:text-[var(--ink)]  border-b-2 border-transparent">
                            Aperçu en direct (Markdown &amp; LaTeX)
                        </button>
                    </div>

                    <div id="lesson-text-editor-wrap">
                        <textarea name="text_content" id="lesson-text-input" rows="6"
                            oninput="updateLessonLivePreview()"
                            placeholder="Rédigez le contenu de la leçon (Markdown &amp; LaTeX pris en charge)...&#10;Ex: # Chapitre 1&#10;Voici une formule inline : $f(x) = x^2 + 2x + 1$&#10;Et une formule centrée :&#10;$$ \int_0^\infty e^{-x^2} dx = \frac{\sqrt{\pi}}{2} $$"
                            class="w-full px-4 py-2 bg-[var(--paper-2)]  border border-[var(--line)]  text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm num text-[var(--ink)] "></textarea>
                    </div>

                    <div id="lesson-text-preview-wrap" class="hidden min-h-[140px] p-4 bg-[var(--card)]  border border-[var(--line)]  rounded-sm text-sm overflow-y-auto max-h-[300px]">
                        <div id="lesson-text-preview" class="sv-lesson-text">
                            <span class="text-xs text-[var(--ink-3)] italic">L'aperçu en direct s'affichera ici au fur et à mesure de votre saisie...</span>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-[var(--ink-3)] ">
                    Rédigez en Markdown (<code># Titre</code>, <code>**Gras**</code>, listes) et insérez vos équations LaTeX (<code>$ ... $</code> ou <code>$$ ... $$</code>). Elles seront automatiquement composées et affichées pour l'étudiant.
                </p>
            </div>

            <!-- PDF — Upload / Remplacement / Suppression -->
            <div id="field-pdf" class="hidden">
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-3">Document PDF</label>

                <!-- PDF existant (affiché en mode édition uniquement) -->
                <div id="existing-pdf-block" class="hidden mb-3">
                    <div class="flex items-center gap-3 px-4 py-3 bg-[var(--paper-2)] border border-[var(--line)] rounded-sm">
                        <!-- Icône PDF -->
                        <svg class="w-8 h-8 text-[var(--danger)] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <div class="flex-grow min-w-0">
                            <p class="text-xs font-semibold text-[var(--ink)] truncate" id="existing-pdf-name">—</p>
                            <p class="text-xs text-[var(--ink-3)] mt-0.5">PDF actuel de cette leçon</p>
                        </div>
                        <a id="existing-pdf-preview-link" href="#" target="_blank"
                            class="flex-shrink-0 px-3 py-1.5 text-xs font-semibold   border border-[var(--clay)] text-[var(--clay)] hover:bg-[var(--clay-soft)] hover:text-white transition-colors rounded-sm">
                            Aperçu
                        </a>
                        <button type="button" id="btn-delete-pdf"
                            onclick="togglePdfDelete()"
                            class="flex-shrink-0 px-3 py-1.5 text-xs font-semibold   border border-[var(--danger)] text-[var(--danger)] hover:bg-[var(--danger)] hover:text-white transition-colors rounded-sm">
                            Supprimer
                        </button>
                    </div>
                    <!-- Alerte de confirmation suppression -->
                    <div id="pdf-delete-confirm" class="hidden mt-2 px-4 py-3 bg-[var(--paper-2)] border border-[var(--danger)] rounded-sm">
                        <p class="text-xs text-[var(--danger)] font-semibold mb-2">Attention : Ce PDF sera supprimé définitivement à la sauvegarde.</p>
                        <input type="hidden" name="delete_pdf" id="delete-pdf-flag" value="0">
                        <button type="button" onclick="cancelPdfDelete()" class="text-xs font-semibold text-[var(--ink-2)] hover:underline">Annuler</button>
                    </div>
                </div>

                <!-- Zone d'upload nouveau PDF -->
                <div id="pdf-upload-zone"
                    class="relative border-2 border-dashed border-[var(--line)] rounded-sm hover:border-[var(--clay)] transition-colors cursor-pointer"
                    onclick="document.getElementById('lesson-pdf-input').click()"
                    ondragover="event.preventDefault(); this.classList.add('border-[var(--clay)]')"
                    ondragleave="this.classList.remove('border-[var(--clay)]')"
                    ondrop="handlePdfDrop(event)">
                    <input type="file" name="lesson_pdf" id="lesson-pdf-input" accept="application/pdf"
                        class="sr-only" onchange="handlePdfSelect(this)">
                    <div id="pdf-drop-placeholder" class="flex flex-col items-center justify-center gap-2 py-6 px-4 text-center">
                        <svg class="w-8 h-8 text-[var(--ink-3)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-xs font-semibold text-[var(--ink-2)]">Glisser-déposer un PDF ici</p>
                        <p class="text-xs text-[var(--ink-3)]">ou <span class="text-[var(--clay)] font-semibold underline">cliquer pour parcourir</span> — max 60 Mo</p>
                    </div>
                    <div id="pdf-selected-preview" class="hidden flex items-center gap-3 px-4 py-4">
                        <svg class="w-7 h-7 text-[var(--danger)] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <div class="flex-grow min-w-0">
                            <p class="text-xs font-semibold text-[var(--ink)] truncate" id="pdf-selected-name"></p>
                            <p class="text-xs text-[var(--clay)] font-semibold mt-0.5">✓ Prêt à être uploadé</p>
                        </div>
                        <button type="button" onclick="clearPdfSelection(event)"
                            class="flex-shrink-0 w-6 h-6 rounded-full bg-[var(--line)] hover:bg-[var(--danger)] hover:text-white flex items-center justify-center transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <p class="text-xs text-[var(--ink-3)] mt-1.5">Laissez vide pour conserver le PDF existant.</p>
            </div>

            <!-- ── Bloc Vidéos multiples ─────────────────── -->
            <div id="field-video" class="hidden space-y-3">
                <div class="flex justify-between items-center">
                    <label class="block text-xs font-semibold   text-[var(--ink-2)]">Vidéos (YouTube / Vimeo)</label>
                    <button type="button" onclick="addVideoRow()"
                        class="text-[11px] text-[var(--clay)] font-semibold hover:underline">+ Ajouter une vidéo</button>
                </div>
                <div id="video-rows" class="space-y-2"></div>
            </div>

            <!-- ── Configuration du Dépôt de Devoirs ────────── -->
            <div class="border-t border-[var(--line)]  pt-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="has_assignment" id="lesson-has-assignment" value="1" onchange="toggleAssignmentFields(this.checked)" class="w-4 h-4 text-[var(--clay)] focus:ring-[var(--clay)] border-[var(--line)] rounded cursor-pointer">
                        <label for="lesson-has-assignment" class="text-xs font-semibold   text-[var(--ink)]  cursor-pointer">
                            Activer un devoir / travail à rendre pour cette leçon
                        </label>
                    </div>
                    <span class="text-xs text-[var(--clay)]  font-semibold bg-[var(--pine-soft)]  px-2 py-0.5 rounded">PDF, DOCX (&le;20Mo) &amp; Liens</span>
                </div>

                <div id="assignment-config-fields" class="hidden space-y-3 pl-4 border-l-2 border-[var(--clay)] pt-1">
                    <div>
                        <label class="block text-[11px] font-semibold text-[var(--ink-2)]  mb-1">Titre / Consigne rapide du devoir</label>
                        <input type="text" name="assignment_title" id="lesson-assignment-title" placeholder="ex: Exercice pratique 1 - Application web ou rapport PDF" class="w-full px-3 py-1.5 bg-[var(--paper-2)]  border border-[var(--line)]  text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm ">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-[var(--ink-2)]  mb-1">Type de rendu exigé</label>
                            <select name="assignment_type" id="lesson-assignment-type" class="w-full px-3 py-1.5 bg-[var(--paper-2)]  border border-[var(--line)]  text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm ">
                                <option value="both">Document (Fichier) OU Lien web (Défaut)</option>
                                <option value="file">Document / Fichier uniquement</option>
                                <option value="link">Lien URL uniquement (GitHub, Drive, etc.)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-[var(--ink-2)]  mb-1">Formats de document acceptés</label>
                            <input type="text" name="allowed_file_types" id="lesson-allowed-file-types" value="pdf,docx" placeholder="ex: pdf, docx, md, zip" class="w-full px-3 py-1.5 bg-[var(--paper-2)]  border border-[var(--line)]  text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm num ">
                            <p class="text-[11px] text-[var(--ink-3)] mt-0.5">Extensions séparées par des virgules (ex: pdf, docx, md, zip, txt)</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[var(--ink-2)]  mb-1">Instructions détaillées &amp; Modalités de rendu</label>
                        <textarea name="assignment_instructions" id="lesson-assignment-instructions" rows="4" placeholder="Expliquez l'exercice à accomplir (Markdown &amp; LaTeX supportés, ex: $E = mc^2$ ou **Consignes**), la nature des fichiers ou liens attendus..." class="w-full px-3 py-1.5 bg-[var(--paper-2)]  border border-[var(--line)]  text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm num "></textarea>
                        
                        <!-- Zone d'aperçu en direct (Markdown & LaTeX) -->
                        <div id="assignment-instructions-preview-wrapper" class="hidden mt-2 p-3 bg-[var(--paper-2)]  border border-[var(--line)]  rounded-sm">
                            <div class="text-xs font-semibold   text-[var(--ink-3)] mb-1">Aperçu en direct (Markdown &amp; LaTeX) :</div>
                            <div id="assignment-instructions-preview" class="text-xs text-[var(--ink-2)]  leading-relaxed space-y-2 math-render"></div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[var(--ink-2)]  mb-1">Date limite de rendu (Optionnelle)</label>
                        <input type="datetime-local" name="assignment_deadline" id="lesson-assignment-deadline" class="w-full px-3 py-1.5 bg-[var(--paper-2)]  border border-[var(--line)]  text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm ">
                    </div>
                </div>
            </div>

            <!-- ── Bloc Ressources complémentaires ────────── -->
            <div class="border-t border-[var(--line)] pt-4 space-y-3">
                <div class="flex justify-between items-center">
                    <label class="block text-xs font-semibold   text-[var(--ink-2)]">Ressources complémentaires</label>
                    <button type="button" onclick="addResourceRow()"
                        class="text-[11px] text-[var(--clay)] font-semibold hover:underline">+ Ajouter une ressource</button>
                </div>
                <p class="text-xs text-[var(--ink-3)]">Liens externes, articles de référence, dépôts GitHub, slides, etc.</p>
                <div id="resource-rows" class="space-y-2"></div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="toggleModal('lesson-modal')"
                    class="sv-btn-ms-outline">Annuler</button>
                <button type="submit" id="lesson-submit-btn"
                    class="sv-btn-ms">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal : Configuration Génération Quiz IA ───────────────────────── -->
<div id="ai-quiz-config-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6">
    <div class="bg-[var(--card)] p-8 max-w-md w-full border border-[var(--line)] space-y-6 modal-inner">
        <div class="flex justify-between items-center pb-3 border-b border-[var(--line)]">
            <div>
                <h3 class="font-serif text-xl font-light">Génération de Quiz IA</h3>
                <p id="ai-config-lesson-title" class="text-xs font-light text-[var(--ink-3)] mt-1">Configuration du questionnaire</p>
            </div>
            <button type="button" onclick="toggleModal('ai-quiz-config-modal')" class="text-[var(--ink-3)] hover:text-[var(--danger)]">
                ✕
            </button>
        </div>

        <form onsubmit="submitAiQuizConfig(event)" class="space-y-5">
            <input type="hidden" id="ai-config-lesson-id" value="">
            <input type="hidden" id="ai-config-lesson-name" value="">

            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">
                    Nombre de questions (Max 25)
                </label>
                <input type="number" id="ai-config-num-questions" min="1" max="25" value="5" required
                    class="w-full p-2.5 bg-[var(--paper-2)] border border-[var(--line)] text-sm text-[var(--ink)] num focus:border-[var(--clay)] outline-none rounded-sm">
                <p class="text-[11px] text-[var(--ink-3)] mt-1">Choisissez entre 1 et 25 questions par leçon (limite max : 25).</p>
            </div>

            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">
                    Niveau de difficulté
                </label>
                <select id="ai-config-difficulty" required
                    class="w-full p-2.5 bg-[var(--paper-2)] border border-[var(--line)] text-sm text-[var(--ink)] focus:border-[var(--clay)] outline-none rounded-sm">
                    <option value="Facile">Facile (Notions fondamentales & questions directes)</option>
                    <option value="Moyen" selected>Moyen (Compréhension & application pratique)</option>
                    <option value="Difficile">Difficile (Analyse approfondie & cas pratiques)</option>
                    <option value="Expert / Piège">Expert / Piège (Réflexion critique & détails avancés)</option>
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-[var(--line)]">
                <button type="button" onclick="toggleModal('ai-quiz-config-modal')" class="sv-btn-ms-outline">Annuler</button>
                <button type="submit" class="sv-btn-ms">Générer avec l'IA</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal : Génération de Quiz IA (Gemini) ───────────────────────── -->
<div id="ai-quiz-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6">
    <div class="bg-[var(--card)] p-8 max-w-2xl w-full border border-[var(--line)] flex flex-col max-h-[85vh] modal-inner">
        <div class="flex justify-between items-center mb-4 flex-shrink-0">
            <div>
                <h3 class="font-serif text-2xl font-light">Génération de Quiz IA</h3>
                <p id="ai-quiz-lesson-title" class="text-xs font-light text-[var(--ink-3)] mt-1">Leçon : ...</p>
            </div>
            <button type="button" onclick="toggleModal('ai-quiz-modal')" class="text-[var(--ink-3)] hover:text-[var(--danger)]">
                ✕
            </button>
        </div>

        <!-- Zone de chargement -->
        <div id="ai-quiz-loading" class="flex-grow flex flex-col items-center justify-center py-12 space-y-3">
            <div class="w-8 h-8 border-2 border-[var(--ink)] border-t-transparent rounded-full animate-spin"></div>
            <p class="text-xs num   text-[var(--ink-2)]">Génération en cours par l'IA...</p>
        </div>

        <!-- Zone d'affichage des questions générées -->
        <div id="ai-quiz-content" class="hidden flex-grow overflow-y-auto space-y-6 my-4 pr-2 text-sm">
            <p class="text-xs text-[var(--ink-2)] font-light">
                Voici les questions générées à partir du texte de votre leçon. Vous pouvez relire, modifier ou décocher celles que vous ne souhaitez pas ajouter.
            </p>
            <div id="ai-quiz-questions-list" class="space-y-6"></div>
        </div>

        <div id="ai-quiz-footer" class="hidden flex justify-end gap-3 pt-4 border-t border-[var(--line)] flex-shrink-0">
            <button type="button" onclick="toggleModal('ai-quiz-modal')" class="sv-btn-ms-outline">Annuler</button>
            <button type="button" onclick="submitAiQuestions()" class="sv-btn-ms">Enregistrer les questions</button>
        </div>
    </div>
</div>

<!-- ── Modal : Question de leçon ───────────────────────── -->
<div id="question-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6">
    <div class="bg-[var(--card)] p-8 max-w-lg w-full border border-[var(--line)] space-y-5 modal-inner">
        <h3 class="font-serif text-2xl font-light">Question d'Évaluation</h3>
        <p id="lesson-question-subtitle" class="text-xs font-light text-[var(--ink-3)]"></p>
        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_lesson_question">
            <input type="hidden" id="question-lesson-id" name="lesson_id">
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Libellé de la question *</label>
                <textarea name="question_text" required rows="2" placeholder="Posez la question..."
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <?php foreach (['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $k=>$v): ?>
                <div>
                    <label class="block text-xs font-semibold  text-[var(--ink-2)] mb-1">Option <?= $v; ?></label>
                    <input type="text" name="option_<?= $k; ?>" required
                        class="w-full px-3 py-1.5 bg-[var(--paper-2)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm">
                </div>
                <?php endforeach; ?>
            </div>
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Bonne réponse</label>
                <select name="correct_option" required
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
                    <option value="A">Option A</option>
                    <option value="B">Option B</option>
                    <option value="C">Option C</option>
                    <option value="D">Option D</option>
                </select>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="toggleModal('question-modal')"
                    class="sv-btn-ms-outline">Annuler</button>
                <button type="submit"
                    class="sv-btn-ms">Enregistrer</button>
            </div>
            <div class="border-t border-[var(--line)] pt-4 mt-2">
                <p class="text-xs font-semibold   text-[var(--ink-2)] mb-2">Import en masse</p>
                <p class="text-xs text-[var(--ink-3)] mb-3">CSV ou Excel — colonnes : question, option_a…d, correct (A-D)</p>
                <button type="button" onclick="openImportModal('lesson')"
                    class="text-xs text-[var(--clay)] font-semibold hover:underline">Importer depuis un fichier →</button>
                <a href="/teacher/sample-questions.csv" download class="text-xs text-[var(--ink-3)] ml-3 hover:underline">Modèle CSV</a>
                <a href="#" onclick="event.preventDefault(); downloadCurrentQuestions('lesson')" class="text-xs text-[var(--clay)] ml-3 hover:underline">Télécharger les questions (.csv)</a>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal : Question de certification ───────────────── -->
<div id="course-question-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6">
    <div class="bg-[var(--card)] p-8 max-w-lg w-full border border-[var(--line)] space-y-5 modal-inner">
        <h3 class="font-serif text-2xl font-light">Question de Certification</h3>
        <p class="text-xs font-light text-[var(--ink-3)]">Score minimum requis : 80%. Ajoutez au moins 30 questions.</p>
        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_course_question">
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Libellé de la question *</label>
                <textarea name="question_text" required rows="2"
                    placeholder="ex: Quelle est l'impasse résolue par Alan Turing ?"
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <?php foreach (['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $k=>$v): ?>
                <div>
                    <label class="block text-xs font-semibold  text-[var(--ink-2)] mb-1">Option <?= $v; ?></label>
                    <input type="text" name="option_<?= $k; ?>" required
                        class="w-full px-3 py-1.5 bg-[var(--paper-2)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm">
                </div>
                <?php endforeach; ?>
            </div>
            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-2">Bonne réponse</label>
                <select name="correct_option" required
                    class="w-full px-4 py-2 bg-[var(--paper-2)] border border-[var(--line)] text-sm focus:outline-none focus:border-[var(--clay)] rounded-sm">
                    <option value="A">Option A</option>
                    <option value="B">Option B</option>
                    <option value="C">Option C</option>
                    <option value="D">Option D</option>
                </select>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="toggleModal('course-question-modal')"
                    class="sv-btn-ms-outline">Annuler</button>
                <button type="submit"
                    class="sv-btn-ms">Enregistrer</button>
            </div>
            <div class="border-t border-[var(--line)] pt-4 mt-2">
                <p class="text-xs font-semibold   text-[var(--ink-2)] mb-2">Import en masse</p>
                <button type="button" onclick="openImportModal('course')"
                    class="text-xs text-[var(--clay)] font-semibold hover:underline">Importer depuis un fichier →</button>
                <a href="/teacher/sample-questions.csv" download class="text-xs text-[var(--ink-3)] ml-3 hover:underline">Modèle CSV</a>
                <a href="#" onclick="event.preventDefault(); downloadCurrentQuestions('course')" class="text-xs text-[var(--clay)] ml-3 hover:underline">Télécharger les questions (.csv)</a>
            </div>
        </form>
    </div>
</div>



<!-- ── Modal : Éditer une séance de téléévaluation ────────── -->
<div id="edit-live-session-modal" class="hidden fixed inset-0 bg-black/40  z-[60] flex items-center justify-center p-6">
    <div class="bg-[var(--card)] p-8 max-w-lg w-full border border-[var(--line)] modal-inner">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-serif text-2xl font-light">Modifier la Séance</h3>
            <button type="button" onclick="toggleModal('edit-live-session-modal')" class="text-[var(--ink-3)] hover:text-[var(--danger)]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=edit_live_session" class="space-y-4">
            <input type="hidden" name="session_id" id="edit-live-session-id">
            
            <div>
                <label class="block text-xs font-semibold text-[var(--ink-2)]   mb-2">Titre de la Séance</label>
                <input type="text" name="live_title" id="edit-live-title" required class="w-full px-3 py-2 border border-[var(--line)] rounded-sm focus:outline-none focus:border-[var(--clay)] text-sm bg-[var(--card)]">
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-[var(--ink-2)]   mb-2">Temps par question (secondes)</label>
                <input type="number" name="default_time_limit" id="edit-live-limit" required min="5" class="w-full px-3 py-2 border border-[var(--line)] rounded-sm focus:outline-none focus:border-[var(--clay)] text-sm bg-[var(--card)]">
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-[var(--ink-2)]   mb-2">Date/Heure de Début</label>
                <input type="datetime-local" name="live_start_time" id="edit-live-start-time" required class="w-full px-3 py-2 border border-[var(--line)] rounded-sm focus:outline-none focus:border-[var(--clay)] text-sm bg-[var(--card)]">
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-[var(--ink-2)]   mb-2">Date/Heure de Fin</label>
                <input type="datetime-local" name="live_end_time" id="edit-live-end-time" required class="w-full px-3 py-2 border border-[var(--line)] rounded-sm focus:outline-none focus:border-[var(--clay)] text-sm bg-[var(--card)]">
            </div>

            <div class="flex items-center gap-2 py-1">
                <input type="checkbox" name="shuffle_options" id="edit-live-shuffle" value="1" class="w-4 h-4 border-[var(--line)] rounded focus:ring-0">
                <label for="edit-live-shuffle" class="text-xs font-semibold text-[var(--ink-2)] cursor-pointer"><?= tde('live_shuffle') ?></label>
            </div>
            <div class="flex items-center gap-2 py-1">
                <input type="checkbox" name="integrity_watch" id="edit-live-integrity" value="1" class="w-4 h-4 border-[var(--line)] rounded focus:ring-0">
                <label for="edit-live-integrity" class="text-xs font-semibold text-[var(--ink-2)] cursor-pointer"><?= tde('live_integrity') ?></label>
            </div>

            <div class="flex items-center gap-2 py-1">
                <input type="checkbox" name="is_async" id="edit-live-is-async" value="1" onchange="toggleAsyncDeadlineField(this.checked)" class="w-4 h-4 text-[var(--clay)] border-[var(--line)] rounded focus:ring-0">
                <label for="edit-live-is-async" class="text-xs font-semibold text-[var(--ink-2)]   cursor-pointer">Activer le Mode Asynchrone (Devoir Libre)</label>
            </div>
            
            <div id="async-deadline-container" class="hidden">
                <label class="block text-xs font-semibold text-[var(--ink-2)]   mb-2">Date Limite d'Accès Asynchrone</label>
                <input type="datetime-local" name="async_deadline" id="edit-live-async-deadline" class="w-full px-3 py-2 border border-[var(--line)] rounded-sm focus:outline-none focus:border-[var(--clay)] text-sm bg-[var(--card)]">
            </div>
            
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="toggleModal('edit-live-session-modal')" class="px-4 py-2 border border-[var(--line)] text-xs font-semibold   rounded-sm text-[var(--ink-2)] hover:bg-[var(--paper-2)] bg-[var(--card)]">Annuler</button>
                <button type="submit" class="px-4 py-2 bg-[var(--ink)] text-white text-xs font-semibold   rounded-sm hover:bg-black">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── Modal : Mon profil (photo et nom) ────────── -->
<div id="profile-modal" class="hidden fixed inset-0 bg-black/40 z-[60] flex items-center justify-center p-6" role="dialog" aria-modal="true" aria-labelledby="profile-modal-title">
    <div class="bg-[var(--card)] p-8 max-w-md w-full border border-[var(--line)] modal-inner">
        <div class="flex justify-between items-center mb-6">
            <h3 id="profile-modal-title" class="font-serif text-2xl font-light"><?= tde('prof_title') ?></h3>
            <button type="button" onclick="toggleModal('profile-modal')" class="text-[var(--ink-3)] hover:text-[var(--danger)]" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div style="display:flex;gap:1rem;align-items:center;margin-bottom:1rem">
            <span class="t-avatar" style="width:5rem;height:5rem;overflow:hidden;font-size:1.8rem">
                <?php if (!empty($user['avatar_path'])): ?><img id="profile-photo-preview" src="<?= htmlspecialchars(mediaUrl('avatar', $user['avatar_path'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: ?><img id="profile-photo-preview" alt="" style="width:100%;height:100%;object-fit:cover;display:none"><span id="profile-photo-initial"><?= htmlspecialchars($tdInitial) ?></span><?php endif; ?>
            </span>
            <div>
                <input type="file" id="profile-photo-input" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" onchange="uploadTeacherPhoto()">
                <button type="button" class="t-btn t-btn-ghost" onclick="document.getElementById('profile-photo-input').click()"><?= tde('prof_change') ?></button>
                <button type="button" class="t-btn t-btn-ghost" onclick="removeTeacherPhoto()"><?= tde('prof_remove') ?></button>
                <p class="t-hint" style="margin-top:.4rem"><?= tde('prof_hint') ?></p>
            </div>
        </div>
        <form onsubmit="return saveTeacherName(event)" class="space-y-4">
            <div class="t-field">
                <label for="profile-name"><?= tde('prof_name') ?></label>
                <input type="text" id="profile-name" maxlength="100" required value="<?= htmlspecialchars((string)$user['name']) ?>">
            </div>
            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
                <button type="submit" class="t-btn t-btn-primary"><?= tde('prof_save') ?></button>
                <a href="/account/security.php" class="t-btn t-btn-ghost"><?= SmsGateway::enabled() ? tde('prof_security') : htmlspecialchars($tdLang === 'en' ? 'Two-factor authentication' : 'Double authentification') ?></a>
            </div>
        </form>
    </div>
</div>

<!-- ── Résultats : fenêtre « évaluation terminée » et tableau d'examen ────────── -->
<link rel="stylesheet" href="/assets/css/results-review.css">
<?php
$rvPrompt = null;
try {
    // The most recent finished session of this teacher that has participants and was not offered for review yet.
    // Synchronous: start + the sum of the question times has passed. Asynchronous: the deadline has passed.
    $rvStmt = $pdo->prepare("
        SELECT s.id, s.title,
               (SELECT COUNT(*) FROM live_eval_registrations r WHERE r.session_id = s.id) AS n
        FROM live_eval_sessions s
        WHERE s.teacher_id = :t AND s.status = 1 AND s.results_prompted_at IS NULL
          AND EXISTS (SELECT 1 FROM live_eval_registrations r WHERE r.session_id = s.id)
          AND (
                (s.is_async = 0 AND s.start_time + INTERVAL (
                    SELECT COALESCE(SUM(COALESCE(q.time_limit, s.default_time_limit)), 0) FROM live_eval_questions q WHERE q.session_id = s.id
                ) SECOND < NOW())
             OR (s.is_async = 1 AND s.async_deadline IS NOT NULL AND s.async_deadline < NOW())
          )
        ORDER BY s.start_time DESC LIMIT 1
    ");
    $rvStmt->execute(['t' => $teacherId]);
    $rvPrompt = $rvStmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { /* the window is a convenience; the page works without it */ }
?>
<?php if ($rvPrompt): ?>
<script>window.RV_PROMPT = <?= json_encode(['id' => (int)$rvPrompt['id'], 'title' => (string)$rvPrompt['title'], 'n' => (int)$rvPrompt['n']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php endif; ?>

<div id="rv-prompt-overlay" class="rv-overlay" role="dialog" aria-modal="true" aria-labelledby="rv-prompt-title">
    <div class="rv-card rv-prompt">
        <p class="rv-kicker"><?= tde('rv_done_kicker') ?></p>
        <h2 class="rv-h" id="rv-prompt-title"></h2>
        <p class="rv-p" id="rv-prompt-lede"></p>
        <div class="rv-actions">
            <button type="button" class="rv-btn primary" id="rv-prompt-view"><?= tde('rv_view') ?></button>
            <button type="button" class="rv-btn" id="rv-prompt-later"><?= tde('rv_later') ?></button>
        </div>
    </div>
</div>

<div id="rv-modal-overlay" class="rv-overlay" role="dialog" aria-modal="true" aria-labelledby="rv-title">
    <div class="rv-card rv-modal">
        <div class="rv-head">
            <div>
                <p class="rv-kicker"><?= tde('rv_title_kicker') ?></p>
                <h2 class="rv-h" id="rv-title"></h2>
                <p class="rv-sub" id="rv-sub"></p>
            </div>
            <button type="button" class="rv-x" id="rv-close" aria-label="<?= tde('rv_close') ?>">&times;</button>
        </div>
        <div class="rv-bar">
            <input type="search" class="rv-in" id="rv-search" placeholder="<?= tde('rv_search') ?>" aria-label="<?= tde('rv_search') ?>">
            <label class="rv-check"><input type="checkbox" id="rv-flagged"> <?= tde('rv_only_flagged') ?></label>
            <span class="rv-spacer"></span>
            <a class="rv-btn sm" id="rv-analysis" href="#" target="_blank" rel="noopener"><?= tde('rv_analysis') ?></a>
        </div>
        <div class="rv-body" id="rv-body"></div>
        <div class="rv-foot">
            <span id="rv-watch-hint" hidden><?= tde('rv_watch_hint') ?> </span><?= tde('rv_hint') ?>
        </div>
    </div>
</div>

<div id="rv-confirm" class="rv-overlay" role="dialog" aria-modal="true" aria-labelledby="rv-confirm-title" style="z-index:9100">
    <div class="rv-card rv-confirm">
        <h2 class="rv-h" id="rv-confirm-title" style="font-size:1.35rem"></h2>
        <p class="rv-p" style="margin-bottom:0"><?= tde('rv_cancel_p') ?></p>
        <label for="rv-reason"><?= tde('rv_reason') ?></label>
        <textarea id="rv-reason" maxlength="255"></textarea>
        <div class="rv-row">
            <button type="button" class="rv-btn" id="rv-confirm-no"><?= tde('rv_keep') ?></button>
            <button type="button" class="rv-btn primary" id="rv-confirm-ok"><?= tde('rv_confirm') ?></button>
        </div>
    </div>
</div>
<script src="/assets/js/results-review.js" defer></script>

<!-- ── Modal : Créer une séance de téléévaluation ────────── -->
<div id="add-live-session-modal" class="hidden fixed inset-0 bg-black/40  z-[60] flex items-center justify-center p-6">
    <div class="bg-[var(--card)]  p-8 max-w-lg w-full border border-[var(--line)]  rounded-xl modal-inner shadow-md">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-[var(--clay-soft)] text-[var(--clay)]  flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <h3 class="font-serif text-2xl font-light text-[var(--ink)] ">Créer une Séance Live</h3>
            </div>
            <button type="button" onclick="toggleModal('add-live-session-modal')" class="text-[var(--ink-3)] hover:text-[var(--danger)]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?? 0 ?>&action=add_live_session" class="space-y-4">
            <?= csrfInput(); ?>
            <div>
                <label class="block text-xs font-semibold text-[var(--ink-2)]    mb-2">Titre de la Séance</label>
                <input type="text" name="live_title" required placeholder="Ex: Examen Intra-semestriel" class="w-full px-3 py-2 border border-[var(--line)]  rounded-lg focus:outline-none focus:border-[var(--clay)] text-sm bg-[var(--card)]  ">
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-[var(--ink-2)]    mb-2">Temps par question (secondes)</label>
                <input type="number" name="default_time_limit" required value="30" min="5" class="w-full px-3 py-2 border border-[var(--line)]  rounded-lg focus:outline-none focus:border-[var(--clay)] text-sm bg-[var(--card)]  ">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-[var(--ink-2)]    mb-2">Date/Heure Début</label>
                    <input type="datetime-local" name="live_start_time" required class="w-full px-3 py-2 border border-[var(--line)]  rounded-lg focus:outline-none focus:border-[var(--clay)] text-sm bg-[var(--card)]  ">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[var(--ink-2)]    mb-2">Date/Heure Fin</label>
                    <input type="datetime-local" name="live_end_time" required class="w-full px-3 py-2 border border-[var(--line)]  rounded-lg focus:outline-none focus:border-[var(--clay)] text-sm bg-[var(--card)]  ">
                </div>
            </div>

            <div class="flex items-center gap-2 py-1">
                <input type="checkbox" name="shuffle_options" id="add-live-shuffle" value="1" class="w-4 h-4 border-[var(--line)] rounded focus:ring-0">
                <label for="add-live-shuffle" class="text-xs font-semibold text-[var(--ink-2)] cursor-pointer"><?= tde('live_shuffle') ?></label>
            </div>
            <div class="flex items-center gap-2 py-1">
                <input type="checkbox" name="integrity_watch" id="add-live-integrity" value="1" class="w-4 h-4 border-[var(--line)] rounded focus:ring-0">
                <label for="add-live-integrity" class="text-xs font-semibold text-[var(--ink-2)] cursor-pointer"><?= tde('live_integrity') ?></label>
            </div>

            <div class="flex items-center gap-2 py-1">
                <input type="checkbox" name="is_async" id="add-live-is-async" value="1" onchange="document.getElementById('add-async-deadline-container').classList.toggle('hidden', !this.checked)" class="w-4 h-4 text-[var(--clay)] border-[var(--line)] rounded focus:ring-0">
                <label for="add-live-is-async" class="text-xs font-semibold text-[var(--ink-2)]    cursor-pointer">Activer Mode Asynchrone (Devoir Libre)</label>
            </div>
            
            <div id="add-async-deadline-container" class="hidden">
                <label class="block text-xs font-semibold text-[var(--ink-2)]    mb-2">Date Limite Asynchrone</label>
                <input type="datetime-local" name="async_deadline" class="w-full px-3 py-2 border border-[var(--line)]  rounded-lg focus:outline-none focus:border-[var(--clay)] text-sm bg-[var(--card)]  ">
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-[var(--line)] ">
                <button type="button" onclick="toggleModal('add-live-session-modal')" class="px-4 py-2 border border-[var(--line)]  text-xs font-semibold   rounded-lg text-[var(--ink-2)]  hover:bg-[var(--paper-2)] bg-[var(--card)] ">Annuler</button>
                <button type="submit" class="px-4 py-2 bg-[var(--clay)] text-white text-xs font-semibold   rounded-lg hover:bg-[var(--clay-press)]">Créer la séance</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal : Import questions CSV/Excel ─────────────── -->
<div id="import-questions-modal" class="hidden fixed inset-0 bg-black/40  z-50 flex items-center justify-center p-6">
    <div class="bg-[var(--card)] p-8 max-w-md w-full border border-[var(--line)] space-y-5 modal-inner">
        <h3 class="font-serif text-2xl font-light">Importer des questions</h3>
        <p id="import-modal-desc" class="text-xs text-[var(--ink-3)]"></p>
        <div class="space-y-3">
            <input type="file" id="import-questions-file" accept=".csv,.xlsx,.xls,.txt"
                class="w-full text-sm file:mr-3 file:py-2 file:px-4 file:border-0 file:bg-[var(--paper-2)] file:text-xs file:font-semibold">
            <p class="text-xs text-[var(--ink-3)]">Format : question, option_a, option_b, option_c, option_d, correct (A-D). Séparateur , ou ;</p>
            <div id="import-result" class="hidden text-xs p-3 border"></div>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" onclick="toggleModal('import-questions-modal')" class="sv-btn-ms-outline">Fermer</button>
            <button type="button" id="import-questions-btn" class="sv-btn-ms">Importer</button>
        </div>
    </div>
</div>

<!-- ── Modal : Dispatch/Envoyer les résultats par e-mail ────────── -->
<div id="dispatch-emails-modal" class="hidden fixed inset-0 bg-black/40  z-[60] flex items-center justify-center p-6">
    <div class="bg-[var(--card)] p-8 max-w-2xl w-full border border-[var(--line)] modal-inner flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-serif text-2xl font-light">Envoyer les e-mails de résultats</h3>
            <button type="button" onclick="toggleModal('dispatch-emails-modal')" class="text-[var(--ink-3)] hover:text-[var(--danger)]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="text-xs text-[var(--ink-2)] mb-4">
            Destinataires de la séance : <strong id="dispatch-session-title">--</strong>
        </div>

        <!-- Zone de chargement / Tableau des drafts -->
        <div class="flex-1 overflow-y-auto min-h-[250px] border border-[var(--line)] p-2 bg-[var(--paper-2)]">
            <div id="dispatch-loading" class="flex flex-col items-center justify-center h-full py-8 space-y-2">
                <span class="inline-block w-6 h-6 rounded-full border-2 border-t-transparent border-[var(--clay)] animate-spin"></span>
                <span class="text-xs text-[var(--ink-3)]">Chargement du brouillon des résultats...</span>
            </div>
            
            <table id="dispatch-table" class="hidden w-full text-xs text-left border-collapse">
                <thead>
                    <tr class="border-b border-[var(--line)] bg-[var(--paper-2)] text-[var(--ink-2)]   font-semibold">
                        <th class="p-3">Étudiant</th>
                        <th class="p-3">Adresse e-mail</th>
                        <th class="p-3 text-center">Note</th>
                        <th class="p-3 text-center">Statut</th>
                    </tr>
                </thead>
                <tbody id="dispatch-tbody" class="divide-y divide-[var(--line)]">
                    <!-- Rempli dynamiquement -->
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-center pt-6 mt-4 border-t border-[var(--line)]">
            <span id="dispatch-count-info" class="text-xs text-[var(--ink-2)]">--</span>
            <div class="flex gap-3">
                <button type="button" onclick="toggleModal('dispatch-emails-modal')" class="px-4 py-2 border border-[var(--line)] text-xs font-semibold   rounded-sm text-[var(--ink-2)] hover:bg-[var(--paper-2)] bg-[var(--card)]">Annuler</button>
                <button id="dispatch-confirm-btn" type="button" onclick="confirmDispatch()" class="px-4 py-2 bg-[var(--clay)] text-white text-xs font-semibold   rounded-sm hover:bg-[var(--clay-press)] disabled:opacity-50">Approuver et Envoyer</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════════════════ -->

<!-- ── Modal : Ajouter une ressource à la bibliothèque ───── -->
<div id="add-library-modal" class="fixed inset-0 z-50 bg-black/60  hidden items-center justify-center p-4">
    <div class="bg-[var(--card)]  w-full max-w-2xl rounded-2xl shadow-md border border-[var(--line)]  overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-6 border-b border-[var(--line)]  flex items-center justify-between bg-[var(--paper-2)] ">
            <div>
                <h3 class="text-lg font-bold text-[var(--ink)]  flex items-center gap-2">
                    <svg class="w-5 h-5 t-tx-warn" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Ajouter une ressource à la bibliothèque
                </h3>
                <p class="text-xs text-[var(--ink-2)]  mt-0.5">Partagez un document PDF (max 64Mo), une vidéo, ou du contenu Markdown/LaTeX.</p>
            </div>
            <button type="button" onclick="toggleModal('add-library-modal')" class="text-[var(--ink-3)] hover:text-[var(--ink-2)]  text-xl font-bold p-1">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="action" value="add_library_item">

            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)]  mb-1">Titre de la ressource *</label>
                <input type="text" name="library_title" required placeholder="Ex: Syllabus du cours, Support PDF Chapitre 1..."
                       class="w-full px-4 py-2.5 bg-[var(--paper-2)]  border border-[var(--line)]  rounded-lg text-sm focus:outline-none focus:border-[var(--clay)]">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold   text-[var(--ink-2)]  mb-1">Catégorie *</label>
                    <select name="library_category" class="w-full px-4 py-2.5 bg-[var(--paper-2)]  border border-[var(--line)]  rounded-lg text-sm focus:outline-none focus:border-[var(--clay)]">
                        <option value="pdf">Fichier PDF (jusqu'à 64Mo)</option>
                        <option value="syllabus">Syllabus officiel</option>
                        <option value="video">Lien Vidéo (YouTube / MP4)</option>
                        <option value="text_markdown">Texte Rédigé (Markdown / LaTeX)</option>
                        <option value="guide">Guide d'étude / Fiche</option>
                        <option value="other">Autre format</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold   text-[var(--ink-2)]  mb-1">Fichier à joindre (PDF, DOCX, ZIP max 64Mo)</label>
                    <input type="file" name="library_file" accept=".pdf,.docx,.doc,.zip,.png,.jpg,.jpeg"
                           class="w-full px-3 py-1.5 bg-[var(--paper-2)]  border border-[var(--line)]  rounded-lg text-xs focus:outline-none focus:border-[var(--clay)]">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)]  mb-1">Description courte</label>
                <input type="text" name="library_description" placeholder="Aperçu des thèmes abordés ou consignes..."
                       class="w-full px-4 py-2.5 bg-[var(--paper-2)]  border border-[var(--line)]  rounded-lg text-sm focus:outline-none focus:border-[var(--clay)]">
            </div>

            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)]  mb-1">URL de vidéo (optionnel)</label>
                <input type="url" name="library_video_url" placeholder="https://www.youtube.com/watch?v=..."
                       class="w-full px-4 py-2.5 bg-[var(--paper-2)]  border border-[var(--line)]  rounded-lg text-sm focus:outline-none focus:border-[var(--clay)]">
            </div>

            <div>
                <label class="block text-xs font-semibold   text-[var(--ink-2)]  mb-1">Contenu Rédigé / Formules LaTeX (Markdown & LaTeX supportés)</label>
                <textarea name="library_content_markdown" rows="4" placeholder="Insérez ici votre texte avec équations LaTeX $E=mc^2$ ou du Markdown..."
                          class="w-full px-4 py-2.5 bg-[var(--paper-2)]  border border-[var(--line)]  rounded-lg text-sm num focus:outline-none focus:border-[var(--clay)]"></textarea>
            </div>

            <div class="pt-4 border-t border-[var(--line)]  flex items-center justify-end gap-3">
                <button type="button" onclick="toggleModal('add-library-modal')"
                        class="px-4 py-2 bg-[var(--paper-2)]  text-[var(--ink-2)]  text-xs font-semibold   rounded-lg hover:bg-[var(--paper-2)]">
                    Annuler
                </button>
                <button type="submit"
                        class="px-5 py-2 bg-[var(--clay)] text-white text-xs font-semibold   rounded-lg hover:bg-[var(--clay-press)] transition-colors shadow-sm">
                    Publier dans la bibliothèque
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════════════════════ -->
<script>
function openAddLibraryModal() {
    toggleModal('add-library-modal');
}
/**
 * SECTION 5: CLIENT-SIDE DASHBOARD BEHAVIOR & INTERACTION LOGIC
 *
 * This section controls user tab switching, modal operations, evaluation question rendering,
 * and asynchronous stats polling/realtime UI updates.
 */

// ── Escape HTML (must be first — used by many functions below) ────────────
function escHtml(str) {
    var s = String(str == null ? '' : str);
    s = s.split('&').join('&amp;');
    s = s.split('<').join('&lt;');
    s = s.split('>').join('&gt;');
    s = s.split('"').join('&quot;');
    s = s.split("'").join('&#39;');
    return s;
}

// --- Dashboard Tab Management ---
function switchDashboardTab(tabId) {
    const hasCourse = <?= $selectedCourse ? 'true' : 'false' ?>;
    if (tabId !== 'tab-overview' && !hasCourse) {
        return; // a course must be selected first
    }
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById(tabId);
    if (target) {
        target.classList.remove('hidden');
        const name = document.getElementById('t-section-name');
        if (name && target.dataset.title) name.textContent = target.dataset.title;
    }
    document.querySelectorAll('.sidebar-tab-btn').forEach(btn => {
        const on = btn.getAttribute('data-tab-target') === tabId;
        btn.classList.toggle('is-active', on);
        if (on) btn.setAttribute('aria-current', 'page'); else btn.removeAttribute('aria-current');
    });
    history.replaceState(null, null, '#' + tabId);
    window.scrollTo({ top: 0 });
}

function toggleMobileDrawer() {
    const rail = document.getElementById('t-rail');
    const scrim = document.getElementById('t-scrim');
    if (!rail) return;
    const open = !rail.classList.contains('open');
    rail.classList.toggle('open', open);
    if (scrim) scrim.classList.toggle('open', open);
}
function closeDrawer() {
    const rail = document.getElementById('t-rail');
    if (rail && rail.classList.contains('open')) toggleMobileDrawer();
}

window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById(hash)) {
        switchDashboardTab(hash);
    } else {
        switchDashboardTab('tab-overview');
    }
});

// ── Modal generic ─────────────────────────────────────────
function teacherProfilePost(fd, onOk) {
    return fetch('/teacher/update-profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { onOk(d); if (typeof Toast !== 'undefined') Toast.success(SV_T.prof_ok || 'OK'); }
            else if (typeof Toast !== 'undefined') Toast.error(d.message || SV_T.prof_err);
        })
        .catch(() => { if (typeof Toast !== 'undefined') Toast.error(SV_T.prof_err); });
}
function setTeacherPhoto(url) {
    const prev = document.getElementById('profile-photo-preview');
    const ini = document.getElementById('profile-photo-initial');
    if (prev) { if (url) { prev.src = url + '&t=' + Date.now(); prev.style.display = ''; } else { prev.removeAttribute('src'); prev.style.display = 'none'; } }
    if (ini) ini.style.display = url ? 'none' : '';
    const rail = document.getElementById('rail-avatar');
    if (rail) {
        rail.innerHTML = url ? '<img src="' + url + '&t=' + Date.now() + '" alt="" style="width:100%;height:100%;object-fit:cover">' : <?= json_encode($tdInitial) ?>;
    }
}
function uploadTeacherPhoto() {
    const f = document.getElementById('profile-photo-input').files[0];
    if (!f) return;
    const fd = new FormData(); fd.append('avatar', f);
    teacherProfilePost(fd, d => setTeacherPhoto(d.url));
}
function removeTeacherPhoto() {
    const fd = new FormData(); fd.append('remove_avatar', '1');
    teacherProfilePost(fd, () => setTeacherPhoto(null));
}
function saveTeacherName(e) {
    e.preventDefault();
    const fd = new FormData(); fd.append('name', document.getElementById('profile-name').value);
    teacherProfilePost(fd, d => { document.querySelectorAll('.t-rail-foot .who b').forEach(b => b.textContent = d.name); });
    return false;
}

function toggleModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.toggle('hidden');
    if (!modal.classList.contains('hidden')) {
        setTimeout(renderMath, 50);
    }
}

function handleQuestionTypeChange(selectElement) {
    const form = selectElement.closest('form');
    const type = selectElement.value;
    const mcqOptionsGroup = form.querySelector('.mcq-options-group');
    const correctOptionContainer = form.querySelector('.correct-option-container');
    
    if (type === 'written') {
        if (mcqOptionsGroup) mcqOptionsGroup.classList.add('hidden');
        if (correctOptionContainer) {
            correctOptionContainer.innerHTML = `
                <label class="block text-xs text-[var(--ink-2)]   mb-1 font-semibold">Réponse correcte (Calcul/Texte)</label>
                <input type="text" name="correct_option" required placeholder="Ex: 24.5, 42, pi..." class="w-full px-3 py-1.5 border border-[var(--line)] rounded-sm text-xs bg-[var(--card)] focus:outline-none focus:border-[var(--clay)]">
            `;
        }
    } else {
        if (mcqOptionsGroup) mcqOptionsGroup.classList.remove('hidden');
        if (correctOptionContainer) {
            correctOptionContainer.innerHTML = `
                <label class="block text-xs text-[var(--ink-2)]   mb-1 font-semibold">Option Correcte</label>
                <select name="correct_option" required class="w-full px-2 py-1.5 border border-[var(--line)] rounded-sm text-xs bg-[var(--card)] focus:outline-none focus:border-[var(--clay)]">
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            `;
        }
    }
}

function toggleAsyncDeadlineField(checked) {
    const container = document.getElementById('async-deadline-container');
    if (container) {
        if (checked) {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }
}

function openEditLiveSessionModal(button) {
    const session = JSON.parse(button.getAttribute('data-session'));
    document.getElementById('edit-live-session-id').value = session.id;
    document.getElementById('edit-live-title').value = session.title;
    document.getElementById('edit-live-limit').value = session.default_time_limit;
    document.getElementById('edit-live-start-time').value = session.start_time;
    document.getElementById('edit-live-end-time').value = session.end_time;
    
    const shuf = document.getElementById('edit-live-shuffle');
    if (shuf) shuf.checked = parseInt(session.shuffle_options) === 1;
    const watch = document.getElementById('edit-live-integrity');
    if (watch) watch.checked = parseInt(session.integrity_watch) === 1;

    const isAsync = (parseInt(session.is_async) === 1);
    const cb = document.getElementById('edit-live-is-async');
    if (cb) {
        cb.checked = isAsync;
        toggleAsyncDeadlineField(isAsync);
    }
    const deadlineInput = document.getElementById('edit-live-async-deadline');
    if (deadlineInput) {
        deadlineInput.value = session.async_deadline || '';
    }
    
    toggleModal('edit-live-session-modal');
}

function closeAllModals() {
    document.querySelectorAll('[id$="-modal"]').forEach(m => m.classList.add('hidden'));
}

let currentDispatchSessionId = null;
let dispatchRegistrationIds = [];

function openDispatchModal(sessionId) {
    currentDispatchSessionId = sessionId;
    dispatchRegistrationIds = [];
    
    document.getElementById('dispatch-loading').classList.remove('hidden');
    document.getElementById('dispatch-table').classList.add('hidden');
    document.getElementById('dispatch-session-title').textContent = 'Chargement...';
    document.getElementById('dispatch-count-info').textContent = '';
    document.getElementById('dispatch-confirm-btn').disabled = true;
    document.getElementById('dispatch-confirm-btn').textContent = 'Approuver et Envoyer';
    
    toggleModal('dispatch-emails-modal');
    
    fetch(`/api/teacher-dispatch-live-emails.php?session_id=${sessionId}&action=get_draft`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert("Erreur: " + data.message);
                toggleModal('dispatch-emails-modal');
                return;
            }
            
            document.getElementById('dispatch-session-title').textContent = data.session_title;
            
            const tbody = document.getElementById('dispatch-tbody');
            tbody.innerHTML = '';
            dispatchRegistrationIds = data.drafts.map(s => s.id);
            
            if (data.drafts.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-[var(--ink-3)] italic">Aucun participant inscrit à cette session.</td></tr>`;
                document.getElementById('dispatch-count-info').textContent = 'Aucun destinataire';
                document.getElementById('dispatch-confirm-btn').disabled = true;
            } else {
                let activeCount = 0;
                data.drafts.forEach(student => {
                    const hasAnswered = student.answered_count > 0;
                    if (hasAnswered) activeCount++;
                    
                    const scoreText = `${student.correct_count} / ${student.total_questions}`;
                    const statusText = hasAnswered ? 'Participé' : 'Inscrit uniquement';
                    const statusClass = hasAnswered ? 't-tx-ok font-semibold' : 'text-[var(--ink-2)] italic';
                    
                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-[var(--line)] hover:bg-[var(--paper-2)]';
                    tr.innerHTML = `
                        <td class="p-3 font-medium text-[var(--ink)]">${escapeHtml(student.name)}</td>
                        <td class="p-3 text-[var(--ink-2)]">${escapeHtml(student.email)}</td>
                        <td class="p-3 text-center num">${scoreText}</td>
                        <td class="p-3 text-center ${statusClass}">${statusText}</td>
                    `;
                    tbody.appendChild(tr);
                });
                
                document.getElementById('dispatch-count-info').textContent = `${data.drafts.length} inscrit(s) (${activeCount} participant(s) actif(s))`;
                document.getElementById('dispatch-confirm-btn').disabled = false;
            }
            
            document.getElementById('dispatch-loading').classList.add('hidden');
            document.getElementById('dispatch-table').classList.remove('hidden');
        })
        .catch(err => {
            console.error(err);
            alert("Erreur lors de la récupération du brouillon.");
            toggleModal('dispatch-emails-modal');
        });
}

async function confirmDispatch() {
    if (!currentDispatchSessionId || dispatchRegistrationIds.length === 0) return;
    
    const btn = document.getElementById('dispatch-confirm-btn');
    const countInfo = document.getElementById('dispatch-count-info');
    
    btn.disabled = true;
    btn.textContent = 'Envoi en cours...';
    
    const totalEmails = dispatchRegistrationIds.length;
    let processedCount = 0;
    let failedCount = 0;
    
    // Config: 1 channel, chunks of 2 emails to prevent Gmail SMTP blocks/timeouts
    const CHUNK_SIZE = 2;
    const CONCURRENT_CHANNELS = 1;
    
    const chunks = [];
    for (let i = 0; i < totalEmails; i += CHUNK_SIZE) {
        chunks.push(dispatchRegistrationIds.slice(i, i + CHUNK_SIZE));
    }
    
    const updateProgressUI = () => {
        countInfo.innerHTML = `<span class="text-[var(--clay)] font-semibold flex items-center gap-2"><span class="w-3 h-3 rounded-full border-2 border-t-transparent border-[var(--clay)] animate-spin"></span> Envoi : ${processedCount} / ${totalEmails} ${failedCount > 0 ? `(<span class="t-tx-danger">${failedCount} échecs</span>)` : ''}</span>`;
    };

    updateProgressUI();
    
    let chunkIndex = 0;
    
    let lastErrorMessage = '';
    const worker = async () => {
        while (chunkIndex < chunks.length) {
            const currentIndex = chunkIndex++;
            const currentChunk = chunks[currentIndex];
            if (!currentChunk) break;
            
            const formData = new FormData();
            formData.append('session_id', currentDispatchSessionId);
            formData.append('action', 'dispatch');
            formData.append('registration_ids', JSON.stringify(currentChunk));
            
            try {
                const response = await fetch('/api/teacher-dispatch-live-emails.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) throw new Error("HTTP " + response.status);
                
                const data = await response.json();
                
                processedCount += currentChunk.length;
                if (!data.success) {
                    failedCount += currentChunk.length;
                    if (data.message) lastErrorMessage = data.message;
                } else if (data.fail_count > 0) {
                    failedCount += data.fail_count;
                    if (data.last_error) lastErrorMessage = data.last_error;
                }
                updateProgressUI();
            } catch (err) {
                console.error("Erreur d'envoi pour un lot", err);
                processedCount += currentChunk.length; // Skip over the failed ones in UI logic to prevent infinite hanging
                failedCount += currentChunk.length;
                lastErrorMessage = err.message;
                updateProgressUI();
            }
        }
    };
    
    const channels = [];
    for (let i = 0; i < CONCURRENT_CHANNELS; i++) {
        channels.push(worker());
    }
    
    await Promise.all(channels);
    
    btn.textContent = 'Terminé';
    countInfo.innerHTML = `<span class="text-[var(--clay)] font-semibold">Envoi terminé ! (${processedCount}/${totalEmails}) ${failedCount > 0 ? `<span class="t-tx-danger">[${failedCount} échecs]</span>` : ''}</span>`;
    
    setTimeout(() => {
        if (failedCount > 0) {
            alert(`Processus terminé, mais il y a eu ${failedCount} échecs.\nDernière erreur : ${lastErrorMessage}\nVérifiez la configuration SMTP.`);
        } else {
            alert("Tous les e-mails ont été traités avec succès.");
        }
        toggleModal('dispatch-emails-modal');
    }, 500);
}

function escapeHtml(str) {
    if (!str) return '';
    var s = String(str);
    s = s.split('&').join('&amp;');
    s = s.split('<').join('&lt;');
    s = s.split('>').join('&gt;');
    s = s.split('"').join('&quot;');
    s = s.split("'").join('&#039;');
    return s;
}

function pollTeacherLiveStats() {
    const liveTab = document.getElementById('tab-live-eval');
    const isTabActive = liveTab && !liveTab.classList.contains('hidden');
    if (!isTabActive) return;
    const params = new URLSearchParams(window.location.search);
    const courseId = params.get('course_id');
    if (!courseId) return;

    fetch('/api/teacher-live-stats.php?course_id=' + courseId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.sessions) {
                data.sessions.forEach(session => {
                    const set = (sel, v) => { const el = document.querySelector(sel + session.id); if (el) el.textContent = v; };
                    set('.live-inscrits-count-', session.participant_count);
                    set('.live-online-count-', session.online_count);
                    set('.questions-count-', session.total_questions);
                    set('.live-votes-count-', session.status === 'active' ? session.active_question_votes : 0);
                    if (window.svRoomUpdate) window.svRoomUpdate(session);
                });
            }
        })
        .catch(err => console.debug('live stats sync:', err));
}
function pollLiveStats() { pollTeacherLiveStats(); }
setInterval(pollTeacherLiveStats, 3000);

// Close modal on backdrop click
document.querySelectorAll('[id$="-modal"]').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeAllModals();
    });
});

// ── Accordion leçons ──────────────────────────────────────
function toggleLesson(id) {
    const panel   = document.getElementById('panel-' + id);
    const chevron = document.getElementById('chevron-' + id);
    const isOpen  = panel.classList.contains('open');
    panel.classList.toggle('open', !isOpen);
    chevron.classList.toggle('rotate-90', !isOpen);
}

// ── Share course ───────────────────────────────────────────
function openShareModal(courseId) {
    const url = window.location.origin + '/join.php?course=' + courseId;
    document.getElementById('share-url-input').value = url;
    document.getElementById('share-copy-btn').textContent = 'Copier';
    document.getElementById('share-modal').classList.remove('hidden');
}

function closeShareModal() {
    document.getElementById('share-modal').classList.add('hidden');
}

function copyShareLink() {
    const input = document.getElementById('share-url-input');
    const btn   = document.getElementById('share-copy-btn');
    navigator.clipboard.writeText(input.value).then(() => {
        btn.textContent = 'Copie !';
        btn.style.background = '#B5482A';
        setTimeout(() => { btn.textContent = 'Copier'; btn.style.background = ''; }, 2000);
    }).catch(() => {
        input.select();
        document.execCommand('copy');
        btn.textContent = 'Copie !';
        setTimeout(() => { btn.textContent = 'Copier'; }, 2000);
    });
}

// ── Edit course ───────────────────────────────────────────
function openEditCourseModal() {
    <?php if ($selectedCourse): ?>
    document.getElementById('edit-course-title').value       = <?= json_encode($selectedCourse['title']); ?>;
    document.getElementById('edit-course-description').value = <?= json_encode($selectedCourse['description'] ?? ''); ?>;
    document.getElementById('edit-course-key').value         = <?= json_encode($selectedCourse['enrollment_key'] ?? ''); ?>;
    document.getElementById('edit-course-start').value       = <?= json_encode($selectedCourse['start_date'] ?? ''); ?>;
    document.getElementById('edit-course-end').value         = <?= json_encode($selectedCourse['end_date'] ?? ''); ?>;
    document.getElementById('edit-course-eval').value        = <?= json_encode($selectedCourse['eval_deadline'] ?? ''); ?>;
    document.getElementById('edit-course-exam').value        = <?= json_encode($selectedCourse['exam_duration_minutes'] ?? 90); ?>;
    <?php endif; ?>
    toggleModal('edit-course-modal');
}

// ── Edit chapter ──────────────────────────────────────────
function openEditChapterModal(id, title) {
    document.getElementById('edit-chapter-id').value    = id;
    document.getElementById('edit-chapter-title').value = title;
    toggleModal('edit-chapter-modal');
}

// ── Confirm delete chapter ────────────────────────────────
function confirmDeleteChapter(id, title) {
    if (!confirm('Supprimer le chapitre «\u00a0' + title + '\u00a0» et toutes ses leçons ?\n\nCette action est irréversible.')) return;
    document.getElementById('delete-chapter-id').value = id;
    document.getElementById('delete-chapter-form').submit();
}

// ── Confirm delete lesson ─────────────────────────────────
function confirmDeleteLesson(id, title) {
    if (!confirm('Supprimer la leçon «\u00a0' + title + '\u00a0» ?\n\nCette action est irréversible.')) return;
    document.getElementById('delete-lesson-id').value = id;
    document.getElementById('delete-lesson-form').submit();
}

// ── Add lesson modal (new) ────────────────────────────────
function openLessonModal(chapterId) {
    resetLessonModal();
    document.getElementById('lesson-modal-title').textContent  = 'Nouvelle Leçon';
    document.getElementById('lesson-form-action').value        = 'add_lesson';
    document.getElementById('lesson-chapter-id').value        = chapterId;
    document.getElementById('lesson-submit-btn').textContent   = 'Ajouter';
    if (document.getElementById('lesson-compulsory-1')) {
        document.getElementById('lesson-compulsory-1').checked = true;
    }
    toggleModal('lesson-modal');
}

function toggleAssignmentFields(checked) {
    const fields = document.getElementById('assignment-config-fields');
    if (fields) {
        if (checked) {
            fields.classList.remove('hidden');
        } else {
            fields.classList.add('hidden');
        }
    }
}

// ── Edit lesson modal ─────────────────────────────────────
function openEditLessonModal(lesson) {
    resetLessonModal();
    document.getElementById('lesson-modal-title').textContent  = 'Modifier la leçon';
    document.getElementById('lesson-form-action').value        = 'edit_lesson';
    document.getElementById('lesson-edit-id').value            = lesson.id;
    document.getElementById('lesson-chapter-id').value        = '';
    document.getElementById('lesson-title-input').value        = lesson.title || '';
    document.getElementById('lesson-submit-btn').textContent   = 'Mettre à jour';

    // Caractère obligatoire
    const isComp = (lesson.is_compulsory == 1 || lesson.is_compulsory === undefined || lesson.is_compulsory === null);
    if (isComp && document.getElementById('lesson-compulsory-1')) {
        document.getElementById('lesson-compulsory-1').checked = true;
    } else if (document.getElementById('lesson-compulsory-0')) {
        document.getElementById('lesson-compulsory-0').checked = true;
    }

    // Type de contenu
    const ct = document.getElementById('content_type');
    ct.value = lesson.content_type || 'text';
    toggleContentFields(ct.value);

    // Date limite du quiz
    document.getElementById('lesson-quiz-deadline-input').value = lesson.quiz_deadline ? lesson.quiz_deadline.substring(0, 16).replace(' ', 'T') : '';

    // Devoirs / Exercice à rendre
    const hasAsg = (lesson.has_assignment == 1 || lesson.has_assignment === true || lesson.has_assignment === '1');
    const chkAsg = document.getElementById('lesson-has-assignment');
    if (chkAsg) {
        chkAsg.checked = hasAsg;
        toggleAssignmentFields(hasAsg);
    }
    document.getElementById('lesson-assignment-title').value = lesson.assignment_title || '';
    if (document.getElementById('lesson-assignment-type')) {
        document.getElementById('lesson-assignment-type').value = lesson.assignment_type || 'both';
    }
    if (document.getElementById('lesson-allowed-file-types')) {
        document.getElementById('lesson-allowed-file-types').value = lesson.allowed_file_types || 'pdf,docx';
    }
    document.getElementById('lesson-assignment-instructions').value = lesson.assignment_instructions || '';
    document.getElementById('lesson-assignment-deadline').value = lesson.assignment_deadline ? lesson.assignment_deadline.substring(0, 16).replace(' ', 'T') : '';
    updateAssignmentInstructionsPreview();

    // Texte
    document.getElementById('lesson-text-input').value = lesson.text_content || '';
    switchLessonTextTab('edit');
    updateLessonLivePreview();

    // ── PDF existant ──────────────────────────────────────
    const pdfBlock = document.getElementById('existing-pdf-block');
    if (lesson.pdf_path) {
        document.getElementById('existing-pdf-name').textContent = lesson.pdf_path;
        document.getElementById('existing-pdf-preview-link').href = '/download.php?type=pdf&file=' + encodeURIComponent(lesson.pdf_path);
        pdfBlock.classList.remove('hidden');
        // S'assurer que le champ PDF est visible même si content_type != pdf
        document.getElementById('field-pdf').classList.remove('hidden');
    } else {
        pdfBlock.classList.add('hidden');
    }
    // Réinitialiser l'état suppression
    cancelPdfDelete();

    toggleModal('lesson-modal');
}

// Réinitialise le formulaire leçon
function resetLessonModal() {
    document.getElementById('lesson-form').reset();
    document.getElementById('lesson-edit-id').value = '';
    document.getElementById('lesson-quiz-deadline-input').value = '';
    const chkAsg = document.getElementById('lesson-has-assignment');
    if (chkAsg) {
        chkAsg.checked = false;
        toggleAssignmentFields(false);
    }
    document.getElementById('lesson-assignment-title').value = '';
    document.getElementById('lesson-assignment-instructions').value = '';
    document.getElementById('lesson-assignment-deadline').value = '';
    updateAssignmentInstructionsPreview();
    document.getElementById('video-rows').innerHTML    = '';
    document.getElementById('resource-rows').innerHTML = '';
    switchLessonTextTab('edit');
    updateLessonLivePreview();
    // Réinitialiser bloc PDF
    document.getElementById('existing-pdf-block').classList.add('hidden');
    document.getElementById('existing-pdf-name').textContent = '';
    document.getElementById('existing-pdf-preview-link').href = '#';
    clearPdfSelection();
    cancelPdfDelete();
    toggleContentFields('text');
}

// ── Content type toggle ───────────────────────────────────
function toggleContentFields(type) {
    const show = (id, visible) => {
        document.getElementById(id).classList.toggle('hidden', !visible);
    };
    show('field-text',  type === 'text'  || type === 'mixed');
    // Pour le PDF : toujours visible si type === pdf|mixed, ou si un PDF existe déjà (en mode édition)
    const hasPdf = !document.getElementById('existing-pdf-block').classList.contains('hidden');
    show('field-pdf',   type === 'pdf'   || type === 'mixed' || hasPdf);
    show('field-video', type === 'video' || type === 'mixed');
}

// ── Gestion PDF : sélection fichier ──────────────────────
function handlePdfSelect(input) {
    if (input.files && input.files[0]) {
        showPdfSelectedPreview(input.files[0].name);
    }
}

function handlePdfDrop(event) {
    event.preventDefault();
    document.getElementById('pdf-upload-zone').classList.remove('border-[var(--clay)]');
    const file = event.dataTransfer.files[0];
    if (!file) return;
    if (file.type !== 'application/pdf') {
        Toast && Toast.error('Seuls les fichiers PDF sont acceptés.');
        return;
    }
    const input = document.getElementById('lesson-pdf-input');
    // Affecter le fichier au vrai input via DataTransfer
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    showPdfSelectedPreview(file.name);
}

function showPdfSelectedPreview(name) {
    document.getElementById('pdf-drop-placeholder').classList.add('hidden');
    const preview = document.getElementById('pdf-selected-preview');
    preview.classList.remove('hidden');
    preview.classList.add('flex');
    document.getElementById('pdf-selected-name').textContent = name;
}

function clearPdfSelection(event) {
    if (event) event.stopPropagation();
    const input = document.getElementById('lesson-pdf-input');
    if (input) { try { input.value = ''; } catch(e) {} }
    const placeholder = document.getElementById('pdf-drop-placeholder');
    const preview     = document.getElementById('pdf-selected-preview');
    if (placeholder) placeholder.classList.remove('hidden');
    if (preview) { preview.classList.add('hidden'); preview.classList.remove('flex'); }
    if (document.getElementById('pdf-selected-name')) document.getElementById('pdf-selected-name').textContent = '';
}

// ── Gestion PDF : suppression ─────────────────────────────
function togglePdfDelete() {
    const flag    = document.getElementById('delete-pdf-flag');
    const confirm = document.getElementById('pdf-delete-confirm');
    const btn     = document.getElementById('btn-delete-pdf');
    flag.value = '1';
    confirm.classList.remove('hidden');
    btn.classList.add('hidden');
}

function cancelPdfDelete() {
    const flag    = document.getElementById('delete-pdf-flag');
    const confirm = document.getElementById('pdf-delete-confirm');
    const btn     = document.getElementById('btn-delete-pdf');
    if (flag)    flag.value = '0';
    if (confirm) confirm.classList.add('hidden');
    if (btn)     btn.classList.remove('hidden');
}

// ── Vidéos multiples ──────────────────────────────────────
let videoRowCount = 0;
function addVideoRow(defaultLabel = '', defaultUrl = '') {
    videoRowCount++;
    const idx = videoRowCount;
    const row = document.createElement('div');
    row.className = 'video-row flex items-center gap-2';
    row.innerHTML = `
        <input type="text" name="new_video_labels[]" value="${escHtml(defaultLabel)}"
            placeholder="Intitulé (ex: Cours 1 — Introduction)"
            class="w-1/3 px-3 py-1.5 bg-[var(--paper-2)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm flex-shrink-0">
        <input type="url" name="new_video_urls[]" value="${escHtml(defaultUrl)}"
            placeholder="https://www.youtube.com/watch?v=..." required
            class="flex-grow px-3 py-1.5 bg-[var(--paper-2)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm num">
        <button type="button" onclick="this.parentElement.remove()"
            class="icon-btn danger flex-shrink-0" title="Retirer">
            <svg class="w-3 h-3 text-[var(--danger)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    document.getElementById('video-rows').appendChild(row);
}

// ── Ressources complémentaires ────────────────────────────
let resourceRowCount = 0;
function addResourceRow() {
    resourceRowCount++;
    const row = document.createElement('div');
    row.className = 'resource-row flex items-center gap-2 flex-wrap';
    row.innerHTML = `
        <select name="new_resource_types[]"
            class="px-2 py-1.5 bg-[var(--paper-2)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm flex-shrink-0">
            <option value="link">Lien</option>
            <option value="file">Fichier</option>
            <option value="reference">Référence</option>
        </select>
        <input type="text" name="new_resource_labels[]"
            placeholder="Libellé (ex: Slides du cours)"
            class="w-36 px-3 py-1.5 bg-[var(--paper-2)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm flex-shrink-0">
        <input type="url" name="new_resource_urls[]"
            placeholder="https://..." required
            class="flex-grow px-3 py-1.5 bg-[var(--paper-2)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm num">
        <button type="button" onclick="this.parentElement.remove()"
            class="icon-btn danger flex-shrink-0">
            <svg class="w-3 h-3 text-[var(--danger)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    document.getElementById('resource-rows').appendChild(row);
}

// ── Génération de Quiz IA (Gemini) ──────────────────────
let aiQuizLessonId = null;
let aiGeneratedQuestions = [];

function openAiQuizConfigModal(lessonId, lessonTitle) {
    document.getElementById('ai-config-lesson-id').value = lessonId;
    document.getElementById('ai-config-lesson-name').value = lessonTitle;
    document.getElementById('ai-config-lesson-title').textContent = 'Leçon : ' + lessonTitle;
    document.getElementById('ai-config-num-questions').value = 5;
    document.getElementById('ai-config-difficulty').value = 'Moyen';
    toggleModal('ai-quiz-config-modal');
}

function submitAiQuizConfig(e) {
    e.preventDefault();
    const lessonId = document.getElementById('ai-config-lesson-id').value;
    const lessonTitle = document.getElementById('ai-config-lesson-name').value;
    let numQuestions = parseInt(document.getElementById('ai-config-num-questions').value, 10) || 5;
    numQuestions = Math.min(25, Math.max(1, numQuestions));
    const difficulty = document.getElementById('ai-config-difficulty').value || 'Moyen';

    toggleModal('ai-quiz-config-modal');
    generateAiQuiz(lessonId, lessonTitle, numQuestions, difficulty);
}

function generateAiQuiz(lessonId, lessonTitle, numQuestions = 5, difficulty = 'Moyen') {
    aiQuizLessonId = lessonId;
    aiGeneratedQuestions = [];
    
    document.getElementById('ai-quiz-lesson-title').textContent = `Leçon : ${lessonTitle} (${numQuestions} questions — Niveau ${difficulty})`;
    
    // Configurer l'affichage modal initial
    document.getElementById('ai-quiz-loading').classList.remove('hidden');
    document.getElementById('ai-quiz-content').classList.add('hidden');
    document.getElementById('ai-quiz-footer').classList.add('hidden');
    
    toggleModal('ai-quiz-modal');

    fetch('/api/ai-teacher.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            lesson_id: lessonId,
            num_questions: numQuestions,
            difficulty_level: difficulty,
            action: 'generate'
        })
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('ai-quiz-loading').classList.add('hidden');
        if (data.success && data.questions && data.questions.length > 0) {
            aiGeneratedQuestions = data.questions;
            renderAiGeneratedQuestions();
            document.getElementById('ai-quiz-content').classList.remove('hidden');
            document.getElementById('ai-quiz-footer').classList.remove('hidden');
        } else {
            toggleModal('ai-quiz-modal');
            Toast.error(data.error || 'Aucune question n\'a pu être générée.');
        }
    })
    .catch(err => {
        document.getElementById('ai-quiz-loading').classList.add('hidden');
        toggleModal('ai-quiz-modal');
        Toast.error('Erreur réseau lors de la génération : ' + err.message);
    });
}

function renderAiGeneratedQuestions() {
    const container = document.getElementById('ai-quiz-questions-list');
    container.innerHTML = '';

    aiGeneratedQuestions.forEach((q, idx) => {
        const item = document.createElement('div');
        item.className = 'p-5 border border-[var(--line)] bg-[var(--paper-2)] rounded-sm space-y-4';
        item.innerHTML = `
            <div class="flex items-start gap-3">
                <input type="checkbox" id="ai-q-check-${idx}" checked
                    class="mt-1 w-4 h-4 text-[var(--clay)] border-[var(--line)] rounded-sm focus:ring-[var(--clay)]">
                <div class="flex-grow space-y-3">
                    <div>
                        <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-1">
                            Question ${idx + 1}
                        </label>
                        <input type="text" id="ai-q-text-${idx}" value="${escHtml(q.question)}"
                            class="w-full px-3 py-1.5 bg-[var(--card)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] num   text-[var(--ink-3)] mb-1">Option A</label>
                            <input type="text" id="ai-q-opt-a-${idx}" value="${escHtml(q.options.A || '')}"
                                class="w-full px-3 py-1.5 bg-[var(--card)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] num   text-[var(--ink-3)] mb-1">Option B</label>
                            <input type="text" id="ai-q-opt-b-${idx}" value="${escHtml(q.options.B || '')}"
                                class="w-full px-3 py-1.5 bg-[var(--card)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] num   text-[var(--ink-3)] mb-1">Option C</label>
                            <input type="text" id="ai-q-opt-c-${idx}" value="${escHtml(q.options.C || '')}"
                                class="w-full px-3 py-1.5 bg-[var(--card)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] num   text-[var(--ink-3)] mb-1">Option D</label>
                            <input type="text" id="ai-q-opt-d-${idx}" value="${escHtml(q.options.D || '')}"
                                class="w-full px-3 py-1.5 bg-[var(--card)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold   text-[var(--ink-2)] mb-1">Option correcte</label>
                        <select id="ai-q-correct-${idx}"
                            class="px-3 py-1.5 bg-[var(--card)] border border-[var(--line)] text-xs focus:outline-none focus:border-[var(--clay)] rounded-sm">
                            <option value="A" ${q.correct === 'A' ? 'selected':''}>A</option>
                            <option value="B" ${q.correct === 'B' ? 'selected':''}>B</option>
                            <option value="C" ${q.correct === 'C' ? 'selected':''}>C</option>
                            <option value="D" ${q.correct === 'D' ? 'selected':''}>D</option>
                        </select>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(item);
    });
}

function submitAiQuestions() {
    const selectedQuestions = [];
    aiGeneratedQuestions.forEach((q, idx) => {
        const checkbox = document.getElementById(`ai-q-check-${idx}`);
        if (checkbox && checkbox.checked) {
            selectedQuestions.push({
                question: document.getElementById(`ai-q-text-${idx}`).value.trim(),
                options: {
                    A: document.getElementById(`ai-q-opt-a-${idx}`).value.trim(),
                    B: document.getElementById(`ai-q-opt-b-${idx}`).value.trim(),
                    C: document.getElementById(`ai-q-opt-c-${idx}`).value.trim(),
                    D: document.getElementById(`ai-q-opt-d-${idx}`).value.trim()
                },
                correct: document.getElementById(`ai-q-correct-${idx}`).value
            });
        }
    });

    if (selectedQuestions.length === 0) {
        Toast.error('Veuillez sélectionner au moins une question à enregistrer.');
        return;
    }

    // Désactiver le bouton d'enregistrement
    const btn = document.querySelector('#ai-quiz-footer button:last-child');
    if (btn) btn.disabled = true;

    fetch('/api/ai-teacher.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            lesson_id: aiQuizLessonId,
            action: 'save',
            questions: selectedQuestions
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Toast.success('Quiz IA ajouté avec succès.');
            toggleModal('ai-quiz-modal');
            setTimeout(() => location.reload(), 1000);
        } else {
            if (btn) btn.disabled = false;
            Toast.error(data.error || 'Erreur lors de l\'enregistrement.');
        }
    })
    .catch(err => {
        if (btn) btn.disabled = false;
        Toast.error('Erreur réseau lors de l\'enregistrement : ' + err.message);
    });
}

// ── Question de leçon ─────────────────────────────────────
function openQuestionModal(lessonId, lessonTitle) {
    document.getElementById('question-lesson-id').value          = lessonId;
    document.getElementById('lesson-question-subtitle').textContent = 'Leçon\u00a0: ' + lessonTitle;
    toggleModal('question-modal');
}

// escHtml is defined at the top of this <script> block

// ── Keyboard ESC ferme les modals ─────────────────────────
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllModals(); });

// ── Import questions CSV / Excel ──────────────────────────
let importType = 'course';
const courseIdForImport = <?= (int)$selectedCourseId; ?>;

function openImportModal(type) {
    importType = type;
    document.getElementById('import-questions-file').value = '';
    document.getElementById('import-result').classList.add('hidden');
    document.getElementById('import-modal-desc').textContent = type === 'lesson'
        ? 'Importez des questions pour la leçon sélectionnée.'
        : 'Importez des questions de certification pour ce cours.';
    if (type === 'lesson' && !document.getElementById('question-lesson-id').value) {
        Toast.error('Ouvrez d\'abord une leçon via « + Question ».');
        return;
    }
    toggleModal('import-questions-modal');
}

document.getElementById('import-questions-btn')?.addEventListener('click', async () => {
    const fileInput = document.getElementById('import-questions-file');
    const file      = fileInput.files[0];
    if (!file) { Toast.error('Sélectionnez un fichier.'); return; }

    const ext = file.name.split('.').pop().toLowerCase();
    closeAllModals();

    const lessonIdVal = document.getElementById('question-lesson-id')?.value || null;

    if (ext === 'csv' || ext === 'txt') {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const text = e.target.result;
                const rawRows = parseCsvJs(text);
                const { questions, mathCount } = processParsedRows(rawRows);
                openCsvPreview(questions, mathCount, importType, courseIdForImport, null, lessonIdVal);
            } catch (err) {
                Toast.error("Erreur de lecture CSV : " + err.message);
            }
        };
        reader.readAsText(file);
    } else if (ext === 'xlsx' || ext === 'xls') {
        try {
            const buf = await file.arrayBuffer();
            const wb  = XLSX.read(buf, { type: 'array' });
            const sheet = wb.Sheets[wb.SheetNames[0]];
            const rows  = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
            const { questions, mathCount } = processParsedRows(rows);
            openCsvPreview(questions, mathCount, importType, courseIdForImport, null, lessonIdVal);
        } catch (err) {
            Toast.error("Erreur de lecture Excel : " + err.message);
        }
    } else {
        Toast.error('Format non supporté. Utilisez .csv ou .xlsx');
    }
});

// Statistiques enseignant
fetch('/teacher/get-stats.php').then(r => r.json()).then(data => {
    const el = document.getElementById('teacher-stats');
    if (!data.success || !el) return;
    el.innerHTML = data.courses.map(c => `
        <div class="sv-kpi-item" style="border:none;border-right:2px solid #111;border-bottom:none;">
            <h4 class="text-sm font-bold text-[var(--ink)] mb-3">${c.title}</h4>
            <div class="grid grid-cols-2 gap-3">
                <div><span class="sv-kpi-value" style="font-size:1.25rem">${c.enrollments}</span><br><span class="sv-kpi-label">Inscrits</span></div>
                <div><span class="sv-kpi-value" style="font-size:1.25rem">${Math.round(c.avg_progress)}%</span><br><span class="sv-kpi-label">Progression</span></div>
                <div><span class="sv-kpi-value" style="font-size:1.25rem">${c.pass_rate}%</span><br><span class="sv-kpi-label">Taux réussite</span></div>
                <div><span class="sv-kpi-value" style="font-size:1.25rem">${Math.round(c.avg_score)}%</span><br><span class="sv-kpi-label">Score moyen</span></div>
            </div>
        </div>
    `).join('') || '<p class="text-sm font-bold text-[var(--ink-2)] p-5">Aucune statistique disponible.</p>';
});

<?php if ($selectedCourse): ?>
// Cache global pour les données récupérées par l'API
window.currentCourseGrades = null;

function loadTeacherGrades(courseId) {
    fetch(`/teacher/get-grades.php?course_id=${courseId}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.currentCourseGrades = data;
            // Mettre à jour visuellement les KPI si nécessaire
            if (data.enrolled_students && document.getElementById('kpi-enrolled-count')) {
                document.getElementById('kpi-enrolled-count').textContent = data.enrolled_students.length;
            }
            populateRegisteredStudents();
            populateLessonGrades();
            populateCertifications();
        } else {
            console.error("Erreur de chargement des notes :", data.message);
        }
    })
    .catch(err => {
        console.error("Erreur réseau lors de la récupération des notes :", err);
    });
}

function populateRegisteredStudents() {
    if (!window.currentCourseGrades || !window.currentCourseGrades.enrolled_students) return;
    const body = document.getElementById('registered-students-list-body');
    const students = window.currentCourseGrades.enrolled_students;
    if (!students.length) {
        body.innerHTML = `<tr><td colspan="7" class="p-4 text-center text-[var(--ink-3)] italic">Aucun élève inscrit à ce cours pour le moment.</td></tr>`;
    } else {
        body.innerHTML = students.map(s => {
            const pct = parseInt(s.progress_percent, 10) || 0;
            const lessons = (s.lessons_done ?? 0) + '/' + (s.lessons_total ?? 0);
            const quiz = s.quiz_percent !== null && s.quiz_percent !== undefined ? s.quiz_percent + '%' : '—';
            const asg = s.assignments_total > 0 ? s.assignments_done + '/' + s.assignments_total : '—';
            const where = s.current_lesson ? escapeHtml(s.current_lesson) : (s.lessons_total > 0 ? 'Cours terminé' : '—');
            return `<tr>
                <td class="p-3 num text-[var(--ink-2)] font-semibold">${escapeHtml(s.matricule || '—')}</td>
                <td class="p-3 font-semibold text-[var(--ink)]">${escapeHtml(s.student_name)}</td>
                <td class="p-3 text-center num">${lessons}</td>
                <td class="p-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <div class="w-16 bg-[var(--line)] h-1.5 rounded-full overflow-hidden" aria-hidden="true">
                            <div class="bg-[var(--clay)] h-full" style="width: ${pct}%"></div>
                        </div>
                        <span class="font-semibold text-xs text-[var(--clay)] num">${pct}%</span>
                    </div>
                </td>
                <td class="p-3 text-center num">${quiz}</td>
                <td class="p-3 text-center num">${asg}</td>
                <td class="p-3 text-[var(--ink-2)]">${where}</td>
            </tr>`;
        }).join('');
    }
}

function populateLessonGrades() {
    if (!window.currentCourseGrades || !window.currentCourseGrades.lesson_grades) return;
    const container = document.getElementById('lesson-grades-accordion-container');
    const summary   = document.getElementById('lesson-grades-summary');
    const grades    = window.currentCourseGrades.lesson_grades;

    if (!grades.length) {
        summary.innerHTML = '';
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center py-16 text-center px-8">
                <svg class="w-12 h-12 text-[var(--line)] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm font-semibold text-[var(--ink-2)]">Aucune leçon avec quiz trouvée</p>
                <p class="text-xs text-[var(--ink-3)] mt-1">Ajoutez des questions à vos leçons pour voir les évaluations ici.</p>
            </div>`;
        return;
    }

    const byLesson = {};
    grades.forEach(g => {
        const lid = g.lesson_id;
        if (!byLesson[lid]) {
            byLesson[lid] = {
                lesson_id:     lid,
                lesson_title:  g.lesson_title,
                chapter_title: g.chapter_title,
                total_questions: parseInt(g.total_questions) || 0,
                students: []
            };
        }
        byLesson[lid].students.push(g);
    });

    const lessons = Object.values(byLesson);

    const totalStudents  = (window.currentCourseGrades.enrolled_students || []).length;
    const totalLessons   = lessons.length;
    let   totalDone      = 0;
    let   totalUndone    = 0;
    let   scoreSum       = 0;
    let   scoreCount     = 0;
    lessons.forEach(l => {
        l.students.forEach(s => {
            if (s.status === 'Terminé') { totalDone++; scoreSum += parseFloat(s.score_percent) || 0; scoreCount++; }
            else totalUndone++;
        });
    });
    const avgScore = scoreCount > 0 ? (scoreSum / scoreCount).toFixed(1) : '—';

    summary.innerHTML = `
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[var(--clay)] inline-block"></span>
            <span class="font-semibold text-[var(--ink)]">${totalLessons}</span>&nbsp;leçon(s) évaluée(s)
        </div>
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[var(--ink-3)] inline-block"></span>
            <span class="font-semibold text-[var(--ink)]">${totalStudents}</span>&nbsp;apprenant(s) inscrits
        </div>
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[var(--ok)] inline-block"></span>
            <span class="font-semibold text-[var(--ok)]">${totalDone}</span>&nbsp;quiz terminés
        </div>
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[var(--danger)] inline-block"></span>
            <span class="font-semibold text-[var(--danger)]">${totalUndone}</span>&nbsp;non terminés
        </div>
        <div class="flex items-center gap-2 ml-auto">
            <span class="text-[var(--ink-3)]">Score moyen global :</span>
            <span class="font-bold text-[var(--clay)] text-sm">${avgScore !== '—' ? avgScore + '%' : '—'}</span>
        </div>`;

    let accordionHtml = '';
    lessons.forEach((lesson, index) => {
        const panelId   = 'lgpanel-' + index;
        const tableId   = 'lgtable-' + index;
        const done      = lesson.students.filter(s => s.status === 'Terminé').length;
        const total_s   = lesson.students.length;
        const undone    = total_s - done;
        const avgL      = done > 0
            ? (lesson.students.filter(s=>s.status==='Terminé').reduce((a,s)=>a+parseFloat(s.score_percent||0),0)/done).toFixed(1)
            : null;
        const completionPct = total_s > 0 ? Math.round((done/total_s)*100) : 0;

        accordionHtml += `
        <div class="bg-[var(--card)]">
            <button type="button"
                class="w-full flex items-center justify-between px-8 py-5 text-left hover:bg-[var(--paper-2)] transition-colors focus:outline-none"
                onclick="toggleGradeAccordion('${panelId}', this)">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[var(--pine-soft)] flex items-center justify-center text-[var(--clay)] font-bold text-xs mt-0.5">
                        ${index + 1}
                    </div>
                    <div>
                        <p class="text-xs  font-semibold  text-[var(--ink-3)] mb-0.5">${escHtml(lesson.chapter_title)}</p>
                        <h4 class="font-serif text-lg font-medium text-[var(--ink)]">${escHtml(lesson.lesson_title)}</h4>
                        <div class="flex items-center gap-3 mt-2">
                            <div class="flex items-center gap-1">
                                <div class="w-24 bg-[var(--line)] h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-[var(--clay)] h-full rounded-full transition-all" style="width:${completionPct}%"></div>
                                </div>
                                <span class="text-xs font-semibold text-[var(--clay)]">${completionPct}%</span>
                            </div>
                            <span class="text-xs text-[var(--ink-3)]">${lesson.total_questions} question(s)</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-[var(--pine-soft)] text-[var(--ok)]">
                        ✓ ${done} terminé(s)
                    </span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-[var(--paper-2)] text-[var(--ink-3)]">
                        ○ ${undone} non terminé(s)
                    </span>
                    ${avgL !== null ? `<span class="text-xs font-bold text-[var(--clay)] min-w-[3.5rem] text-right">${avgL}%</span>` : '<span class="text-xs text-[var(--ink-3)] min-w-[3.5rem] text-right">—</span>'}
                    <svg class="w-4 h-4 text-[var(--ink-3)] transition-transform duration-200" id="chevron-${panelId}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </button>

            <div id="${panelId}" class="hidden border-t border-[var(--line)] bg-[var(--paper-2)]">
                <div class="px-8 py-4 flex justify-between items-center">
                    <p class="text-xs text-[var(--ink-2)]">Résultats pour cette leçon — tous les apprenants inscrits sont listés.</p>
                    <button onclick="exportTableToExcel('${tableId}', 'Notes_${escHtml(lesson.lesson_title).replace(/[^a-zA-Z0-9]/g,'_')}_${index}')" class="px-3 py-1.5 bg-[var(--clay)] text-white text-xs font-semibold   rounded-sm hover:bg-[var(--ink)] transition-colors">
                        ⬇ Exporter
                    </button>
                </div>
                <div class="px-8 pb-6 overflow-x-auto">
                    <table class="w-full text-xs text-left border border-[var(--line)] rounded-sm" id="${tableId}">
                        <thead>
                            <tr class="bg-[var(--paper-2)] border-b border-[var(--line)]">
                                <th class="px-4 py-3 font-semibold   text-[var(--ink-2)] text-xs">Nom de l'apprenant</th>
                                <th class="px-4 py-3 font-semibold   text-[var(--ink-2)] text-xs">Email</th>
                                <th class="px-4 py-3 font-semibold   text-[var(--ink-2)] text-xs text-center">Note finale</th>
                                <th class="px-4 py-3 font-semibold   text-[var(--ink-2)] text-xs text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--line)]">
                            ${lesson.students.map(s => {
                                const done_s    = s.status === 'Terminé';
                                const correct   = parseInt(s.correct_count) || 0;
                                const total_q   = parseInt(s.total_questions) || parseInt(lesson.total_questions) || 0;
                                return `<tr class="hover:bg-[var(--paper-2)] transition-colors">
                                    <td class="px-4 py-3 font-semibold text-[var(--ink)] whitespace-nowrap">${escHtml(s.student_name)}</td>
                                    <td class="px-4 py-3 text-[var(--ink-2)] font-light">${escHtml(s.student_email)}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-bold text-xs num ${done_s ? 'text-[var(--clay)]' : 'text-[var(--ink-3)]'}">
                                            ${done_s ? `${correct} / ${total_q}` : `0 / ${total_q}`}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        ${done_s
                                            ? `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-[var(--pine-soft)] text-[var(--ok)]">✓ Terminé</span>`
                                            : `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-[var(--paper-2)] text-[var(--ink-3)]">○ Non terminé</span>`
                                        }
                                    </td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;
    });
    container.innerHTML = accordionHtml;
}

function exportTableToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) {
        if (typeof Toast !== 'undefined') Toast.error('Tableau introuvable.');
        else alert('Tableau introuvable.');
        return;
    }
    const cleanFilename = (filename || 'export').replace(/[^a-zA-Z0-9_-]/g, '_');
    
    if (typeof XLSX !== 'undefined' && XLSX.utils && XLSX.writeFile) {
        const wb = XLSX.utils.table_to_book(table, { sheet: 'Notes' });
        XLSX.writeFile(wb, cleanFilename + '.xlsx');
    } else {
        const html = table.outerHTML;
        const blob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = cleanFilename + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
}

function toggleGradeAccordion(id, btn) {
    const panel   = document.getElementById(id);
    const chevron = document.getElementById('chevron-' + id);
    if (!panel) return;
    const isOpen = !panel.classList.contains('hidden');
    panel.classList.toggle('hidden', isOpen);
    if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
}

function toggleAccordion(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
}

// ── Auto-open live tab & session accordion from URL params ──────────────
(function autoOpenLiveTabAndSession() {
    const params = new URLSearchParams(window.location.search);
    const sessionId = params.get('open_session');
    if (!sessionId) return;
    window.addEventListener('DOMContentLoaded', () => {
        switchDashboardTab('tab-live-eval');
        if (window.openRoom) window.openRoom(parseInt(sessionId, 10));
    });
    window.history.replaceState({}, '', window.location.pathname + '?course_id=' + params.get('course_id') + '#tab-live-eval');
})();

function populateCertifications() {
    if (!window.currentCourseGrades) return;
    
    // Remplir tableau QCM final
    const examBody = document.getElementById('certifications-course-body');
    const attempts = window.currentCourseGrades.certification_attempts || [];
    if (!attempts.length) {
        examBody.innerHTML = `<tr><td colspan="5" class="p-4 text-center text-[var(--ink-3)]">Aucune tentative de certification enregistrée pour le moment.</td></tr>`;
    } else {
        examBody.innerHTML = attempts.map(a => {
            const passed = parseInt(a.passed) === 1;
            const date = a.attempted_at ? new Date(a.attempted_at).toLocaleDateString('fr-FR', {hour: '2-digit', minute:'2-digit'}) : '—';
            return `<tr>
                <td class="p-3 font-semibold text-[var(--ink)]">${a.student_name}</td>
                <td class="p-3 text-[var(--ink-2)]">${a.student_email}</td>
                <td class="p-3 text-center num font-semibold ${passed ? 'text-[var(--clay)]' : 'text-[var(--danger)]'}">${parseFloat(a.score).toFixed(1)}%</td>
                <td class="p-3 text-center font-semibold ${passed ? 'text-[var(--clay)]' : 'text-[var(--danger)]'}">${passed ? '✓ Réussi' : '✕ Échoué'}</td>
                <td class="p-3 text-right num text-[var(--ink-3)]">${date}</td>
            </tr>`;
        }).join('');
    }

    // Remplir tableau Certifs de module
    const moduleBody = document.getElementById('certifications-module-body');
    const certs = window.currentCourseGrades.module_certificates || [];
    if (!certs.length) {
        moduleBody.innerHTML = `<tr><td colspan="5" class="p-4 text-center text-[var(--ink-3)]">Aucun certificat de module émis pour le moment.</td></tr>`;
    } else {
        moduleBody.innerHTML = certs.map(c => {
            const date = c.issued_at ? new Date(c.issued_at).toLocaleDateString('fr-FR', {hour: '2-digit', minute:'2-digit'}) : '—';
            return `<tr>
                <td class="p-3 font-semibold text-[var(--ink)]">${c.student_name}</td>
                <td class="p-3 text-[var(--ink-2)]">${c.student_email}</td>
                <td class="p-3 num text-[var(--clay)]">${c.certificate_code}</td>
                <td class="p-3 num text-[var(--ink-3)]">${date}</td>
                <td class="p-3 text-right font-semibold">${parseInt(c.manual_issue) === 1 ? 'Oui' : 'Non'}</td>
            </tr>`;
        }).join('');
    }
}

function switchGradesSubTab(subTabId) {
    document.querySelectorAll('.grades-subtab').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById(subTabId);
    if (target) target.classList.remove('hidden');
    ['btn-subtab-students', 'btn-subtab-quiz', 'btn-subtab-certs'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.setAttribute('aria-selected', id === 'btn-' + subTabId ? 'true' : 'false');
    });
}

function copyToClipboard(text) {
    const done = () => { if (typeof Toast !== 'undefined') Toast.success(SV_T.js_link_copied || 'Link copied'); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(() => { prompt('', text); });
    } else { prompt('', text); }
}

function importLiveQuestionsFile(input, sessionId) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    const ext = file.name.split('.').pop().toLowerCase();
    closeAllModals();

    const courseId = <?= (int)$selectedCourse['id'] ?>;

    if (ext === 'csv' || ext === 'txt') {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const text = e.target.result;
                const rawRows = parseCsvJs(text);
                const { questions, mathCount } = processParsedRows(rawRows);
                openCsvPreview(questions, mathCount, 'live', courseId, sessionId, null);
            } catch (err) {
                alert("Erreur de lecture CSV : " + err.message);
            }
        };
        reader.readAsText(file);
    } else if (ext === 'xlsx' || ext === 'xls') {
        try {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const wb = XLSX.read(data, {type: 'array'});
                    const sheet = wb.Sheets[wb.SheetNames[0]];
                    const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                    const { questions, mathCount } = processParsedRows(rows);
                    openCsvPreview(questions, mathCount, 'live', courseId, sessionId, null);
                } catch (err) {
                    alert("Erreur de traitement Excel : " + err.message);
                }
            };
            reader.readAsArrayBuffer(file);
        } catch (err) {
            alert("Erreur de lecture Excel : " + err.message);
        }
    } else {
        alert('Format non supporté. Utilisez .csv ou .xlsx');
    }
}

// ── Client-side CSV Parser & Validation Helpers ──
function parseCsvJs(text) {
    let lines = [];
    let row = [""];
    let inQuotes = false;
    text = String(text).replace(/^\uFEFF/, '');
    // Comma, semicolon or tab: whichever the header line uses most, outside quotes
    const firstLine = text.split(/\r?\n/, 1)[0] || '';
    const counts = { ',': 0, ';': 0, '\t': 0 };
    let q0 = false;
    for (const ch of firstLine) { if (ch === '"') q0 = !q0; else if (!q0 && ch in counts) counts[ch]++; }
    const delim = Object.keys(counts).sort((a, b) => counts[b] - counts[a])[0];
    
    for (let i = 0; i < text.length; i++) {
        let c = text[i];
        let next = text[i+1];
        
        if (c === '"') {
            if (inQuotes && next === '"') {
                row[row.length - 1] += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }
        } else if (c === delim && !inQuotes) {
            row.push("");
        } else if ((c === '\r' || c === '\n') && !inQuotes) {
            if (c === '\r' && next === '\n') i++;
            if (row.length > 1 || row[0] !== "") {
                lines.push(row);
            }
            row = [""];
        } else {
            row[row.length - 1] += c;
        }
    }
    if (row.length > 1 || row[0] !== "") {
        lines.push(row);
    }
    return lines;
}

/* Column positions from the header row. An exact name beats a loose one, and numbering columns
   ("N° question", "question_id", "n", "#") are never taken for the question text. */
function resolveCsvColumns(headerRow) {
    const slug = (h) => String(h || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/°/g, '_').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    const NUM = ['id', 'n', 'no', 'num', 'numero', 'nb', 'index', 'ordre', 'order', 'rang', 'number'];
    const idx = { q: -1, a: -1, b: -1, c: -1, d: -1, cor: -1, exp: -1 };
    const set = (k, i) => { if (idx[k] === -1) idx[k] = i; };
    headerRow.forEach((raw, i) => {
        const key = slug(raw);
        if (!key || NUM.includes(key)) return;
        const mo = key.match(/^(?:option|opt|choix|proposition|reponse|answer)?_?([abcd])$/);
        if (mo) { set(mo[1], i); return; }
        if (['correct', 'correct_option', 'correct_answer', 'bonne_reponse', 'reponse_correcte', 'bonne', 'answer', 'solution', 'reponse', 'corrige'].includes(key) || key.startsWith('correct') || key.startsWith('bonne_rep')) { set('cor', i); return; }
        if (/explanation|explication|justification|commentaire|correction/.test(key)) { set('exp', i); return; }
        if (key === 'type' || key === 'question_type' || key === 'type_question') return;
        if (['question', 'question_text', 'libelle', 'enonce', 'intitule', 'texte', 'text'].includes(key)) { set('q', i); return; }
        if (key.split('_').length <= 3 && !key.split('_').some(t => NUM.includes(t)) && /question|libelle|enonce|intitule/.test(key)) set('q', i);
    });
    return idx;
}

function processParsedRows(rows) {
    let header = null;
    let startIndex = 0;
    
    if (rows.length > 0) {
        const knownNames = [
            'question', 'libelle', 'enonce',
            'option_a', 'option_b', 'option_c', 'option_d',
            'a', 'b', 'c', 'd',
            'correct', 'reponse', 'réponse', 'bonne_reponse',
            'explanation', 'explication', 'justification',
        ];
        let firstRow = rows[0];
        // A real header row has SHORT cells (≤30 chars) that exactly match known names.
        // We must NOT trigger on data rows whose long free-text contains trigger words.
        const shortCells = firstRow.map(cell => (typeof cell === 'string' && cell.trim().length <= 30) ? cell : '');
        const probe = resolveCsvColumns(shortCells.map(c => String(c).trim().length > 1 ? c : ''));
        // A real header names the question column, or at least four known columns; single letters in a data row do not count
        const headerMatches = (probe.q !== -1 || Object.values(probe).filter(i => i !== -1).length >= 4) ? Object.values(probe).filter(i => i !== -1).length : 0;
        if (headerMatches >= 2) {
            header = { idx: resolveCsvColumns(firstRow) };
            startIndex = 1;
        }
    }
    
    let questions = [];
    let mathCount = 0;
    
    for (let i = startIndex; i < rows.length; i++) {
        let cols = rows[i];
        if (cols.length < 6) continue;
        
        let qText = cols[0] || "";
        let optA = cols[1] || "";
        let optB = cols[2] || "";
        let optC = cols[3] || "";
        let optD = cols[4] || "";
        let correct = (cols[5] || "").toUpperCase().trim();
        let explanation = cols[6] || "";
        
        if (header) {
            const idx = header.idx;
            if (idx.q !== -1) qText = cols[idx.q] || "";
            if (idx.a !== -1) optA = cols[idx.a] || "";
            if (idx.b !== -1) optB = cols[idx.b] || "";
            if (idx.c !== -1) optC = cols[idx.c] || "";
            if (idx.d !== -1) optD = cols[idx.d] || "";
            if (idx.cor !== -1) correct = (cols[idx.cor] || "").toUpperCase().trim();
            if (idx.exp !== -1) explanation = cols[idx.exp] || "";
            // A "question" that is only a number came from a numbering column: use the longest free-text cell instead
            if (/^\d{1,4}[.)\-]?$/.test(qText.trim())) {
                const used = new Set(Object.values(idx));
                let best = '';
                cols.forEach((v, k) => { if (!used.has(k) && String(v || '').trim().length > best.length) best = String(v).trim(); });
                if (best.length > 8) qText = best;
            }
        }
        
        if (!qText.trim() && !optA.trim() && !optB.trim()) continue;
        
        let allText = qText + optA + optB + optC + optD + explanation;
        let hasMath = allText.includes('$');
        if (hasMath) mathCount++;
        
        let errors = [];
        if (!qText.trim()) errors.push("Question vide");
        if (!optA.trim() || !optB.trim() || !optC.trim() || !optD.trim()) errors.push("Toutes les options (A, B, C, D) doivent être remplies");
        if (!['A', 'B', 'C', 'D'].includes(correct)) {
            if (correct === '1') correct = 'A';
            else if (correct === '2') correct = 'B';
            else if (correct === '3') correct = 'C';
            else if (correct === '4') correct = 'D';
            else errors.push("Réponse correcte invalide (doit être A, B, C ou D)");
        }
        
        questions.push({
            question_text: qText,
            option_a: optA,
            option_b: optB,
            option_c: optC,
            option_d: optD,
            correct_option: correct,
            explanation: explanation,
            has_math: hasMath,
            errors: errors
        });
    }
    
    return { questions, mathCount };
}

let currentImportSessionId = null;
let currentImportCourseId = null;
let currentImportType = null;
let currentImportLessonId = null;
let parsedQuestionsToImport = [];

function openCsvPreview(questions, mathCount, type, courseId, sessionId, lessonId) {
    currentImportType = type;
    currentImportCourseId = courseId;
    currentImportSessionId = sessionId;
    currentImportLessonId = lessonId;
    parsedQuestionsToImport = questions;
    
    document.getElementById('csv-stat-total').textContent = questions.length;
    document.getElementById('csv-stat-math').textContent = mathCount;
    
    const tbody = document.getElementById('csv-preview-table-body');
    tbody.innerHTML = "";
    
    let hasAnyWarnings = false;
    
    questions.forEach((q, idx) => {
        const row = document.createElement('tr');
        row.className = "border-b border-[var(--line)] hover:bg-[var(--paper-2)]";
        
        let statusBadge = `<span class="t-bg-ok-soft t-tx-ok px-2 py-0.5 rounded font-semibold text-xs">Valide</span>`;
        if (q.errors.length > 0) {
            hasAnyWarnings = true;
            statusBadge = `<span class="t-bg-danger-soft t-tx-danger px-2 py-0.5 rounded font-semibold text-xs" title="${q.errors.join(', ')}">Erreur</span>`;
        }
        
        const escapeHtml = (str) => { var s = String(str||''); s=s.split('&').join('&amp;'); s=s.split('<').join('&lt;'); s=s.split('>').join('&gt;'); return s; };
        
        const qType = q.question_type || 'mcq';
        let optionsHtml = '';
        if (qType === 'mcq') {
            optionsHtml = `
                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-[var(--ink-2)] mt-1">
                    <div class="math-render"><span class="font-semibold text-[var(--ink-3)]">A:</span> ${escapeHtml(q.option_a)}</div>
                    <div class="math-render"><span class="font-semibold text-[var(--ink-3)]">B:</span> ${escapeHtml(q.option_b)}</div>
                    <div class="math-render"><span class="font-semibold text-[var(--ink-3)]">C:</span> ${escapeHtml(q.option_c)}</div>
                    <div class="math-render"><span class="font-semibold text-[var(--ink-3)]">D:</span> ${escapeHtml(q.option_d)}</div>
                </div>
            `;
        } else {
            optionsHtml = `
                <div class="text-xs t-tx-warn font-semibold   mt-1 t-bg-warn-soft border t-bd-warn px-2 py-0.5 rounded-sm inline-block">
                    Question écrite / Calcul
                </div>
            `;
        }

        row.innerHTML = `
            <td class="p-3 text-center text-[var(--ink-3)] font-medium">${idx + 1}</td>
            <td class="p-3 space-y-1.5 text-left">
                <div class="font-bold text-[var(--ink)] math-render">${escapeHtml(q.question_text)}</div>
                ${optionsHtml}
                ${q.explanation ? `<div class="text-[11px] text-[var(--clay)] font-medium mt-1 math-render"><span class="font-semibold text-[var(--ink-3)]">Explication :</span> ${escapeHtml(q.explanation)}</div>` : ''}
                ${q.errors.length > 0 ? `<div class="text-xs t-tx-danger font-medium mt-1">[Alerte] ${q.errors.join(' | ')}</div>` : ''}
            </td>
            <td class="p-3 text-center font-bold text-[var(--clay)]">${escapeHtml(q.correct_option)}</td>
            <td class="p-3 text-center">${statusBadge}</td>
        `;
        
        tbody.appendChild(row);
    });
    
    const warnBadge = document.getElementById('csv-warning-badge');
    if (hasAnyWarnings) {
        warnBadge.classList.remove('hidden');
    } else {
        warnBadge.classList.add('hidden');
    }
    
    const modal = document.getElementById('csv-preview-modal');
    modal.classList.remove('hidden');
    
    setTimeout(() => {
        renderMath();
    }, 150);
}

function closeCsvPreview() {
    document.getElementById('csv-preview-modal').classList.add('hidden');
    const inputs = document.querySelectorAll('input[type="file"]');
    inputs.forEach(input => input.value = "");
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(renderMath, 200);
    
    document.getElementById('csv-confirm-btn')?.addEventListener('click', async () => {
        if (parsedQuestionsToImport.length === 0) return;
        
        const hasErrors = parsedQuestionsToImport.some(q => q.errors.length > 0);
        if (hasErrors) {
            if (!confirm("Attention : certaines questions contiennent des erreurs et ne seront pas importées. Continuer quand même ?")) {
                return;
            }
        }
        
        const btn = document.getElementById('csv-confirm-btn');
        const spinner = document.getElementById('csv-confirm-spinner');
        
        btn.disabled = true;
        spinner.classList.remove('hidden');
        
        const formData = new FormData();
        formData.append('type', currentImportType);
        formData.append('course_id', currentImportCourseId);
        if (currentImportSessionId) {
            formData.append('session_id', currentImportSessionId);
        }
        if (currentImportLessonId) {
            formData.append('lesson_id', currentImportLessonId);
        }
        
        const rowsJson = parsedQuestionsToImport.map(q => [
            q.question_text,
            q.option_a,
            q.option_b,
            q.option_c,
            q.option_d,
            q.correct_option,
            q.explanation || "",
            q.question_type || "mcq"
        ]);
        
        formData.append('rows_json', JSON.stringify(rowsJson));
        
        try {
            const res = await fetch('/teacher/import-questions.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                if (typeof Toast !== 'undefined') Toast.success(data.message);
                else alert(data.message);
                
                setTimeout(() => {
                    if (currentImportSessionId) {
                        window.location.href = window.location.pathname + '?course_id=' + currentImportCourseId + '&open_session=' + currentImportSessionId + '#tab-live-eval';
                    } else if (currentImportLessonId) {
                        window.location.href = window.location.pathname + '?course_id=' + currentImportCourseId + '&open_lesson=' + currentImportLessonId + '#tab-course';
                    } else {
                        window.location.href = window.location.pathname + '?course_id=' + currentImportCourseId + '#tab-course';
                    }
                }, 1000);
            } else {
                if (typeof Toast !== 'undefined') Toast.error(data.message);
                else alert("Erreur : " + data.message);
            }
        } catch (err) {
            console.error(err);
            if (typeof Toast !== 'undefined') Toast.error("Erreur réseau.");
            else alert("Erreur réseau.");
        } finally {
            btn.disabled = false;
            spinner.classList.add('hidden');
        }
    });
});

loadTeacherGrades(<?= (int)$selectedCourse['id']; ?>);
<?php endif; ?>

function loadNotifications() {
    fetch('/student/get-notifications.php')
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const badge = document.getElementById('notif-count');
        const panel = document.getElementById('notif-panel');
        if (data.unread_count > 0) {
            badge.textContent = data.unread_count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
        panel.innerHTML = data.notifications.length
            ? data.notifications.map(n => `<a href="${n.link || '#'}" class="block p-3 border-b border-[var(--line)] hover:bg-[var(--paper-2)] ${n.is_read == 0 ? 'font-semibold' : ''}"><div class="text-xs">${n.title}</div><div class="text-[11px] text-[var(--ink-3)]">${n.body || ''}</div></a>`).join('')
            : '<p class="p-3 text-xs text-[var(--ink-3)]">Aucune notification.</p>';
    });
}
(function () {
    const btn = document.getElementById('notif-btn');
    const box = document.getElementById('notif-panel-container');
    if (!btn || !box) return;
    const setOpen = (open) => { box.classList.toggle('hidden', !open); btn.setAttribute('aria-expanded', open ? 'true' : 'false'); };
    btn.addEventListener('click', (e) => { e.stopPropagation(); const open = box.classList.contains('hidden'); setOpen(open); if (open) loadNotifications(); });
    document.addEventListener('click', (e) => { if (!box.contains(e.target)) setOpen(false); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
})();
loadNotifications();

// ── Q&R : Réponse de l'enseignant ──────────────────────
function submitReply(a, b) {
    // Two call styles: from the lesson panel submit(event, id), from the moderation tab onclick(id).
    let commentId, input, fromForm = false;
    if (a && typeof a === 'object' && a.preventDefault) { a.preventDefault(); commentId = b; input = document.getElementById(`reply-input-${commentId}`); fromForm = true; }
    else { commentId = a; input = document.getElementById(`reply-text-${commentId}`); }
    const replyText = input ? input.value.trim() : '';
    if (!replyText) { if (input) input.focus(); return; }

    const fd = new FormData();
    fd.append('comment_id', commentId);
    fd.append('reply_text', replyText);

    fetch('/teacher/reply-comment.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const container = document.getElementById(`reply-container-${commentId}`);
            if (container) {
                container.innerHTML = `<div class="mt-2 pl-3 border-l-2 border-[var(--clay)] text-[var(--ink-2)] text-xs"><strong>${SV_T.js_your_reply}</strong> ${escHtml(replyText)}</div>`;
            }
            if (input) input.value = '';
            const card = document.getElementById(`qa-card-${commentId}`);
            if (card) {
                card.dataset.unanswered = '0';
                card.querySelector('[data-qa-badge]')?.remove();
                document.getElementById(`reply-form-${commentId}`)?.classList.add('hidden');
                if (typeof qaFilter === 'function') qaFilter(window.__qaMode || 'all');
            }
            if (typeof Toast !== 'undefined') Toast.success(SV_T.js_reply_posted);
        } else {
            if (typeof Toast !== 'undefined') Toast.error(data.message || SV_T.js_reply_error);
        }
    })
    .catch(err => { if (typeof Toast !== 'undefined') Toast.error(SV_T.js_network + ' ' + err.message); });
}

// ── Q&R : Modération (masquer / afficher) ─────────────
function moderateComment(commentId, hide) {
    const fd = new FormData();
    fd.append('comment_id', commentId);
    fd.append('is_hidden', hide);

    fetch('/teacher/moderate-comment.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById(`comment-card-${commentId}`);
            if (card) {
                if (hide) {
                    card.classList.add('opacity-60', 'bg-[var(--paper-2)]');
                    // update button
                    const btn = card.querySelector('button[onclick*="moderateComment"]');
                    if (btn) {
                        btn.setAttribute('onclick', `moderateComment(${commentId}, 0)`);
                        btn.textContent = 'Afficher';
                    }
                    // add hidden badge
                    const nameDiv = card.querySelector('div.flex > div');
                    if (nameDiv && !nameDiv.querySelector('.hidden-badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'hidden-badge text-xs t-bg-danger-soft t-tx-danger px-1.5 py-0.5 ml-2 font-semibold rounded-sm';
                        badge.textContent = 'Masqué';
                        nameDiv.appendChild(badge);
                    }
                } else {
                    card.classList.remove('opacity-60', 'bg-[var(--paper-2)]');
                    const btn = card.querySelector('button[onclick*="moderateComment"]');
                    if (btn) {
                        btn.setAttribute('onclick', `moderateComment(${commentId}, 1)`);
                        btn.textContent = 'Masquer';
                    }
                    card.querySelector('.hidden-badge')?.remove();
                }
                if (typeof Toast !== 'undefined') Toast.success(hide ? 'Commentaire masqué.' : 'Commentaire affiché.');
            }
        } else {
            if (typeof Toast !== 'undefined') Toast.error(data.message || 'Erreur de modération.');
        }
    })
    .catch(err => { if (typeof Toast !== 'undefined') Toast.error('Erreur réseau: ' + err.message); });
}

function downloadCurrentQuestions(type) {
    let id = 0;
    if (type === 'lesson') {
        id = document.getElementById('question-lesson-id')?.value || 0;
    } else if (type === 'course') {
        id = <?= (int)($selectedCourseId ?? 0) ?>;
    }
    if (!id || id == 0) {
        if (typeof Toast !== 'undefined') Toast.error("Impossible de récupérer l'identifiant pour l'export.");
        else alert("Impossible de récupérer l'identifiant pour l'export.");
        return;
    }
    window.location.href = `/teacher/download-async-csv.php?type=${type}&id=${id}`;
}

// ── Markdown & LaTeX Lesson Editor Helpers ─────────
function switchLessonTextTab(tab) {
    const editBtn = document.getElementById('btn-lesson-tab-edit');
    const previewBtn = document.getElementById('btn-lesson-tab-preview');
    const editWrap = document.getElementById('lesson-text-editor-wrap');
    const previewWrap = document.getElementById('lesson-text-preview-wrap');
    
    if (!editBtn || !previewBtn || !editWrap || !previewWrap) return;

    if (tab === 'edit') {
        editWrap.classList.remove('hidden');
        previewWrap.classList.add('hidden');
        editBtn.className = "px-3 py-1.5 text-xs font-semibold border-b-2 border-[var(--clay)] text-[var(--clay)]  ";
        previewBtn.className = "px-3 py-1.5 text-xs font-semibold text-[var(--ink-3)] hover:text-[var(--ink)]  border-b-2 border-transparent";
    } else {
        updateLessonLivePreview();
        editWrap.classList.add('hidden');
        previewWrap.classList.remove('hidden');
        previewBtn.className = "px-3 py-1.5 text-xs font-semibold border-b-2 border-[var(--clay)] text-[var(--clay)]  ";
        editBtn.className = "px-3 py-1.5 text-xs font-semibold text-[var(--ink-3)] hover:text-[var(--ink)]  border-b-2 border-transparent";
    }
}

function updateLessonLivePreview() {
    const text = document.getElementById('lesson-text-input')?.value || '';
    const previewEl = document.getElementById('lesson-text-preview');
    if (!previewEl) return;
    if (!text.trim()) {
        previewEl.innerHTML = '<span class="text-xs text-[var(--ink-3)] italic">L\'aperçu en direct s\'affichera ici au fur et à mesure de votre saisie...</span>';
        return;
    }
    previewEl.innerHTML = renderMarkdownAndMath(text);
    if (typeof renderMathInElement === 'function') {
        renderMathInElement(previewEl, {
            delimiters: [
                {left: '$$', right: '$$', display: true},
                {left: '$', right: '$', display: false},
                {left: '\\(', right: '\\)', display: false},
                {left: '\\[', right: '\\]', display: true}
            ],
            throwOnError: false
        });
    }
}

function insertFormatIntoLesson(type) {
    const input = document.getElementById('lesson-text-input');
    if (!input) return;
    const start = input.selectionStart;
    const end = input.selectionEnd;
    const selected = input.value.substring(start, end);
    let before = input.value.substring(0, start);
    let after = input.value.substring(end);
    let inserted = '';
    let cursorOffset = 0;

    switch (type) {
        case 'bold':
            inserted = `**${selected || 'texte en gras'}**`;
            cursorOffset = selected ? inserted.length : 2;
            break;
        case 'italic':
            inserted = `*${selected || 'texte en italique'}*`;
            cursorOffset = selected ? inserted.length : 1;
            break;
        case 'h1':
            inserted = `\n# ${selected || 'Titre de la section'}\n`;
            cursorOffset = inserted.length;
            break;
        case 'h2':
            inserted = `\n## ${selected || 'Sous-titre'}\n`;
            cursorOffset = inserted.length;
            break;
        case 'list':
            inserted = `\n- ${selected || 'Élément 1'}\n- Élément 2\n`;
            cursorOffset = inserted.length;
            break;
        case 'quote':
            inserted = `\n> ${selected || 'Citation ou remarque importante'}\n`;
            cursorOffset = inserted.length;
            break;
        case 'code':
            inserted = `\n\`\`\`javascript\n${selected || '// Votre code ici'}\n\`\`\`\n`;
            cursorOffset = inserted.length;
            break;
        case 'math_inline':
            inserted = `$${selected || 'f(x) = x^2 + 1'}$`;
            cursorOffset = selected ? inserted.length : 1;
            break;
        case 'math_display':
            inserted = `\n$$ ${selected || '\\int_0^1 f(x) dx = F(1) - F(0)'} $$\n`;
            cursorOffset = selected ? inserted.length : 3;
            break;
    }

    input.value = before + inserted + after;
    input.focus();
    input.setSelectionRange(start + cursorOffset, start + cursorOffset);
    updateLessonLivePreview();
}

function updateAssignmentInstructionsPreview() {
    const textarea = document.getElementById('lesson-assignment-instructions');
    const wrapper = document.getElementById('assignment-instructions-preview-wrapper');
    const preview = document.getElementById('assignment-instructions-preview');
    if (!textarea || !wrapper || !preview) return;

    const val = textarea.value.trim();
    if (val.length > 0) {
        wrapper.classList.remove('hidden');
        preview.innerHTML = typeof renderMarkdownAndMath === 'function' ? renderMarkdownAndMath(val) : val.replace(/\n/g, '<br>');
        if (typeof renderMathInElement === 'function') {
            renderMathInElement(preview, {
                delimiters: [
                    {left: '$$', right: '$$', display: true},
                    {left: '$', right: '$', display: false},
                    {left: '\\(', right: '\\)', display: false},
                    {left: '\\[', right: '\\]', display: true}
                ],
                throwOnError: false
            });
        }
    } else {
        wrapper.classList.add('hidden');
        preview.innerHTML = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const asgInput = document.getElementById('lesson-assignment-instructions');
    if (asgInput) {
        asgInput.addEventListener('input', updateAssignmentInstructionsPreview);
        asgInput.addEventListener('keyup', updateAssignmentInstructionsPreview);
        asgInput.addEventListener('paste', function() {
            setTimeout(updateAssignmentInstructionsPreview, 50);
        });
    }

    document.querySelectorAll('.assignment-instructions-render[data-instructions]').forEach(function(el) {
        const raw = el.getAttribute('data-instructions');
        if (raw && typeof renderMarkdownAndMath === 'function') {
            el.innerHTML = renderMarkdownAndMath(raw);
            if (typeof renderMathInElement === 'function') {
                renderMathInElement(el, {
                    delimiters: [
                        {left: '$$', right: '$$', display: true},
                        {left: '$', right: '$', display: false},
                        {left: '\\(', right: '\\)', display: false},
                        {left: '\\[', right: '\\]', display: true}
                    ],
                    throwOnError: false
                });
            }
        }
    });
});
</script>

<!-- Modal d'aperçu et de validation de QCM (CSV / Excel) -->
<div id="csv-preview-modal" class="fixed inset-0 bg-black/60  z-[9999] hidden flex items-center justify-center p-4">
    <div class="bg-[var(--card)] rounded-lg shadow-md w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden slide-in" style="overflow:hidden">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-[var(--line)] flex justify-between items-center bg-[var(--paper-2)] rounded-t-lg" style="flex-shrink:0">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-[var(--ink-2)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <div>
                    <h3 class="text-base font-bold text-[var(--ink)]">Validation et Aperçu du QCM</h3>
                    <p class="text-xs text-[var(--ink-2)] font-medium">Vérifiez la lisibilité et le rendu de vos formules mathématiques LaTeX avant de valider l'importation.</p>
                </div>
            </div>
            <button onclick="closeCsvPreview()" class="text-[var(--ink-3)] hover:text-[var(--ink-2)] text-xl font-bold">&times;</button>
        </div>
        
        <!-- Stats and Warnings -->
        <div class="px-6 py-3 t-bg-info-soft border-b t-bd-info flex flex-wrap gap-4 items-center justify-between" style="flex-shrink:0">
            <div class="flex gap-4 text-xs font-semibold text-[var(--ink-2)]">
                <span>Total détecté : <strong id="csv-stat-total" class="t-tx-info">0</strong> questions</span>
                <span>Formules LaTeX validées : <strong id="csv-stat-math" class="t-tx-ok">0</strong></span>
            </div>
            <div id="csv-warning-badge" class="hidden text-xs t-bg-warn-soft t-tx-warn px-2.5 py-1 rounded font-medium">
                Avertissements de formatage détectés
            </div>
        </div>

        <!-- Table Content -->
        <div class="flex-1 px-6 pt-0 pb-6" style="min-height:0;overflow-y:auto;overscroll-behavior:contain">
            <table class="w-full border-collapse text-left text-xs">
                <thead style="position:sticky;top:0;z-index:10">
                    <tr class="border-b-2 border-[var(--line)] bg-[var(--paper-2)] text-[var(--ink-2)] font-bold  ">
                        <th class="p-3 w-12 text-center bg-[var(--paper-2)]">N°</th>
                        <th class="p-3 bg-[var(--paper-2)]">Question &amp; Options (Aperçu Live)</th>
                        <th class="p-3 w-20 text-center bg-[var(--paper-2)]">Correct</th>
                        <th class="p-3 w-24 text-center bg-[var(--paper-2)]">Statut</th>
                    </tr>
                </thead>
                <tbody id="csv-preview-table-body" class="divide-y divide-[var(--line)]">
                    <!-- Rempli dynamiquement -->
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-[var(--line)] bg-[var(--paper-2)] flex justify-between items-center rounded-b-lg" style="flex-shrink:0">
            <button onclick="closeCsvPreview()" class="px-4 py-2 border border-[var(--line)] text-[var(--ink-2)] rounded text-xs font-semibold hover:bg-[var(--paper-2)] transition-colors">
                Annuler
            </button>
            <button id="csv-confirm-btn" class="px-5 py-2 bg-[var(--clay)] text-white rounded text-xs font-semibold hover:bg-[var(--clay-press)] transition-colors flex items-center gap-2">
                <span>Confirmer l'importation</span>
                <span id="csv-confirm-spinner" class="hidden animate-spin h-3 w-3 border-2 border-white border-t-transparent rounded-full"></span>
            </button>
        </div>
    </div>
</div>

<!-- MODAL: CONVERSION DE BAREME ET PREVISUALISATION DU RAPPORT CSV -->
<div id="modal-score-converter" class="fixed inset-0 bg-black/60  z-[9999] hidden flex items-center justify-center p-4">
    <div class="bg-[var(--card)]  max-w-2xl w-full border border-[var(--line)]  rounded-lg shadow-md overflow-hidden flex flex-col max-h-[90vh]">
        <!-- En-tête -->
        <div class="px-6 py-4 border-b border-[var(--line)]  flex justify-between items-center bg-[var(--paper-2)] ">
            <div>
                <h3 class="font-serif text-lg font-bold text-[var(--ink)] " id="converter-session-title">Conversion du Barème &amp; Export CSV</h3>
                <p class="text-xs text-[var(--ink-3)]">Ajustez la note maximale et prévisualisez avant téléchargement</p>
            </div>
            <button onclick="closeScoreConverterModal()" class="text-[var(--ink-3)] hover:text-[var(--ink-2)]  text-xl font-bold p-1">&times;</button>
        </div>

        <!-- Corps du Modal -->
        <div class="p-6 space-y-6 overflow-y-auto flex-1">
            <!-- Contrôle du barème -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 t-bg-ok-soft  p-4 border t-bd-ok  rounded-lg items-center">
                <div>
                    <label class="block text-xs font-bold   text-[var(--ink-2)]  mb-1">Barème d'Origine</label>
                    <div class="text-sm font-semibold text-[var(--ink)] " id="converter-orig-scale">... points</div>
                    <span class="text-xs text-[var(--ink-2)]">Calculé d'après le nombre de questions de l'évaluation</span>
                </div>
                <div>
                    <label for="converter-target-input" class="block text-xs font-bold   text-[var(--clay)]  mb-1">Convertir la note sur :</label>
                    <div class="flex items-center gap-2">
                        <input type="number" id="converter-target-input" min="1" max="1000" step="0.5" value="30" oninput="updateScorePreviewLive()"
                            class="w-full px-3 py-2 bg-[var(--card)]  border t-bd-ok  text-sm font-bold text-[var(--clay)]  focus:outline-none focus:ring-2 focus:ring-[var(--clay)] rounded-sm">
                        <span class="text-xs font-bold text-[var(--ink-2)] ">pts</span>
                    </div>
                </div>
            </div>

            <!-- Tableau de prévisualisation -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <h4 class="text-xs font-bold   text-[var(--ink-2)] ">Aperçu du Rapport CSV</h4>
                    <span id="converter-student-count" class="text-[11px] font-medium text-[var(--ink-2)]">0 étudiant(s)</span>
                </div>
                
                <div class="border border-[var(--line)]  rounded-sm overflow-hidden max-h-64 overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-[var(--paper-2)]  text-[var(--ink-2)]  font-semibold border-b border-[var(--line)]  sticky top-0">
                            <tr>
                                <th class="p-2.5 num">matricule</th>
                                <th class="p-2.5">nom_prenom</th>
                                <th class="p-2.5 text-right text-[var(--ink-3)] font-normal">Originale</th>
                                <th class="p-2.5 text-right font-bold text-[var(--clay)] ">note</th>
                            </tr>
                        </thead>
                        <tbody id="converter-preview-tbody" class="divide-y divide-[var(--line)]  bg-[var(--card)] ">
                            <!-- Generated rows -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pied du Modal -->
        <div class="px-6 py-4 border-t border-[var(--line)]  bg-[var(--paper-2)]  flex justify-between items-center">
            <button type="button" onclick="closeScoreConverterModal()" class="px-4 py-2 border border-[var(--line)] text-xs font-semibold   rounded-sm hover:bg-[var(--paper-2)]">
                Annuler
            </button>
            <button type="button" onclick="downloadConvertedCsv()" class="px-6 py-2.5 bg-[var(--clay)] text-white text-xs font-semibold   hover:bg-[var(--clay-press)] transition-all rounded-sm shadow-md flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Télécharger le CSV
            </button>
        </div>
    </div>
</div>

<script>
let currentConverterSessionId = 0;
let currentConverterData = null;

function openScoreConverterModal(sessionId) {
    currentConverterSessionId = sessionId;
    const modal = document.getElementById('modal-score-converter');
    modal.classList.remove('hidden');
    
    // Fetch preview data
    fetchScorePreview();
}

function closeScoreConverterModal() {
    document.getElementById('modal-score-converter').classList.add('hidden');
}

function fetchScorePreview() {
    const targetScaleInput = document.getElementById('converter-target-input');
    const targetScale = targetScaleInput ? targetScaleInput.value : 30;
    const tbody = document.getElementById('converter-preview-tbody');
    tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-[var(--ink-3)] italic">Chargement de l'aperçu...</td></tr>`;

    fetch(`/teacher/export-live-csv.php?session_id=${currentConverterSessionId}&target_scale=${targetScale}&preview=1`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            currentConverterData = data;
            document.getElementById('converter-session-title').textContent = data.session_title;
            document.getElementById('converter-orig-scale').textContent = `${data.original_scale} points`;
            document.getElementById('converter-student-count').textContent = `${data.students.length} étudiant(s)`;
            
            if (targetScaleInput && (!targetScaleInput.value || targetScaleInput.value == 30)) {
                targetScaleInput.value = data.target_scale;
            }

            renderPreviewTable(data.students);
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center t-tx-danger font-semibold">${data.message}</td></tr>`;
        }
    })
    .catch(err => {
        tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center t-tx-danger font-semibold">Erreur réseau : ${err.message}</td></tr>`;
    });
}

function updateScorePreviewLive() {
    if (!currentConverterData) return;
    const targetScale = parseFloat(document.getElementById('converter-target-input').value) || currentConverterData.original_scale;
    const origScale = currentConverterData.original_scale;

    currentConverterData.students.forEach(s => {
        const rawPct = (origScale > 0) ? (s.raw_score / origScale) : 0;
        const conv = Math.round(rawPct * targetScale * 100) / 100;
        s.converted_score = conv;
        s.note = (conv % 1 === 0) ? String(Math.round(conv)) : String(conv);
    });

    renderPreviewTable(currentConverterData.students);
}

function renderPreviewTable(students) {
    const tbody = document.getElementById('converter-preview-tbody');
    if (!students || students.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-[var(--ink-3)] italic">Aucun participant enregistré dans cette évaluation.</td></tr>`;
        return;
    }

    let html = '';
    students.forEach(s => {
        html += `
        <tr class="hover:bg-[var(--paper-2)] ">
            <td class="p-2.5 num text-[var(--ink-2)]  font-bold">${escapeHtml(s.matricule)}</td>
            <td class="p-2.5 font-medium text-[var(--ink)] ">${escapeHtml(s.nom_prenom)}</td>
            <td class="p-2.5 text-right text-[var(--ink-3)] num">${s.raw_score} / ${currentConverterData.original_scale}</td>
            <td class="p-2.5 text-right font-bold num text-[var(--clay)]  text-sm">${s.note}</td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function downloadConvertedCsv() {
    const targetScale = document.getElementById('converter-target-input').value;
    window.location.href = `/teacher/export-live-csv.php?session_id=${currentConverterSessionId}&target_scale=${targetScale}`;
    closeScoreConverterModal();
}
</script>
<script src="/assets/js/teacher.js"></script>
<?php
require_once __DIR__ . '/../lib/PhonePrompt.php';
$phoneBlocks = PhonePrompt::render($user, $tdLang);
require_once __DIR__ . '/../lib/Tour.php'; Tour::render('teacher', $tdLang, $phoneBlocks);
?>
</body>
</html>
