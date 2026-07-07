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
require_once __DIR__ . '/../Mailer.php';
requireRole('teacher');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // requireCsrf();
}

$user = getCurrentUser();
$pdo  = Database::getInstance();

$defaultCourseSvg = '<svg class="w-12 h-12 text-[#004B23]" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>';

$selectedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selectedCourse   = null;

// =========================================================================
// SECTION 2: HELPER FUNCTIONS & UTILITIES
// =========================================================================
function isValidYoutubeOrVimeo(string $url): bool {
    return (bool) preg_match('#^https?://(www\.)?(youtube\.com/watch|youtu\.be/|vimeo\.com/)#i', $url);
}

function handlePdfUpload(array $file): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['application/pdf'];
    if (!in_array($file['type'], $allowed, true) && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        return null;
    }
    if ($file['size'] > 20 * 1024 * 1024) return null;

    $uploadDir = __DIR__ . '/../uploads/pdfs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $newName = md5(uniqid('', true)) . '.pdf';
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
        return $newName;
    }
    return null;
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
                if ($_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['cover_image']['tmp_name'];
                    $fileName = $_FILES['cover_image']['name'];
                    $fileSize = $_FILES['cover_image']['size'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (in_array($fileExtension, $allowedExtensions, true)) {
                        if ($fileSize <= 3 * 1024 * 1024) {
                            $newFileName = md5(uniqid('', true)) . '.' . $fileExtension;
                            $uploadFileDir = __DIR__ . '/../uploads/course-covers/';
                            if (!is_dir($uploadFileDir)) {
                                mkdir($uploadFileDir, 0777, true);
                            }
                            if (move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                                $coverImage = $newFileName;
                            } else {
                                $uploadError = 'upload_move_error';
                            }
                        } else {
                            $uploadError = 'upload_size_error';
                        }
                    } else {
                        $uploadError = 'upload_ext_error';
                    }
                } else {
                    $uploadError = 'upload_error';
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO courses (
                    module_id, teacher_id, created_by, title, description, svg_icon,
                    enrollment_key, start_date, end_date, eval_deadline, exam_duration_minutes, cover_image
                ) VALUES (
                    :module_id, :teacher_id, :created_by, :title, :description, :svg_icon,
                    :enrollment_key, :start_date, :end_date, :eval_deadline, :exam_minutes, :cover_image
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
            $quizDeadline = !empty($_POST['quiz_deadline']) ? $_POST['quiz_deadline'] : null;
            $pdfPath     = null;

            if (in_array($contentType, ['pdf','mixed'], true) && !empty($_FILES['lesson_pdf']['name'])) {
                $pdfPath = handlePdfUpload($_FILES['lesson_pdf']);
            }

            if (!empty($title) && $chapterId > 0) {
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM lessons WHERE chapter_id=:cid");
                $stmt->execute(['cid' => $chapterId]);
                $maxSort = (int)$stmt->fetchColumn();

                $stmt = $pdo->prepare("
                    INSERT INTO lessons (chapter_id,title,content_type,text_content,pdf_path,video_url,sort_order,quiz_deadline)
                    VALUES (:cid,:title,:ct,:tc,:pp,NULL,:so,:qd)
                ");
                $stmt->execute([
                    'cid' => $chapterId, 'title' => $title, 'ct' => $contentType,
                    'tc'  => $textContent, 'pp' => $pdfPath, 'so' => $maxSort + 1,
                    'qd'  => $quizDeadline,
                ]);
                $lessonId = (int)$pdo->lastInsertId();

                // Vidéos initiales
                this_processNewVideos($pdo, $lessonId, $_POST);

                // Ressources initiales
                this_processNewResources($pdo, $lessonId, $_POST);

                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=lesson_added#tab-course"); exit;
            }
        }

        // ── E. Éditer une leçon ────────────────────────────────────────
        if ($action === 'edit_lesson') {
            $lessonId    = (int)($_POST['lesson_id'] ?? 0);
            $title       = trim((string)($_POST['lesson_title'] ?? ''));
            $contentType = (string)($_POST['content_type'] ?? 'text');
            $textContent = trim((string)($_POST['text_content'] ?? '')) ?: null;
            $quizDeadline = !empty($_POST['quiz_deadline']) ? $_POST['quiz_deadline'] : null;
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

                    if (!empty($_FILES['lesson_pdf']['name']) && $_FILES['lesson_pdf']['error'] === UPLOAD_ERR_OK) {
                        $uploaded = handlePdfUpload($_FILES['lesson_pdf']);
                        if ($uploaded) {
                            // Supprimer l'ancien fichier physique si existant
                            if ($finalPdf && file_exists(__DIR__ . '/../uploads/pdfs/' . $finalPdf)) {
                                @unlink(__DIR__ . '/../uploads/pdfs/' . $finalPdf);
                            }
                            $finalPdf = $uploaded;
                        }
                    } elseif ($deletePdf) {
                        // Suppression explicite du PDF sans remplacement
                        if ($finalPdf && file_exists(__DIR__ . '/../uploads/pdfs/' . $finalPdf)) {
                            @unlink(__DIR__ . '/../uploads/pdfs/' . $finalPdf);
                        }
                        $finalPdf = null;
                    }

                    $stmt = $pdo->prepare("
                        UPDATE lessons SET title=:t,content_type=:ct,text_content=:tc,pdf_path=:pp,quiz_deadline=:qd
                        WHERE id=:id
                    ");
                    $stmt->execute(['t'=>$title,'ct'=>$contentType,'tc'=>$textContent,'pp'=>$finalPdf,'qd'=>$quizDeadline,'id'=>$lessonId]);

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
                    if ($_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                        $fileTmpPath = $_FILES['cover_image']['tmp_name'];
                        $fileName = $_FILES['cover_image']['name'];
                        $fileSize = $_FILES['cover_image']['size'];
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                        
                        if (in_array($fileExtension, $allowedExtensions, true)) {
                            if ($fileSize <= 3 * 1024 * 1024) {
                                $newFileName = md5(uniqid('', true)) . '.' . $fileExtension;
                                $uploadFileDir = __DIR__ . '/../uploads/course-covers/';
                                if (!is_dir($uploadFileDir)) {
                                    mkdir($uploadFileDir, 0777, true);
                                }
                                if (move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                                    $coverImage = $newFileName;
                                } else {
                                    $uploadError = 'upload_move_error';
                                }
                            } else {
                                $uploadError = 'upload_size_error';
                            }
                        } else {
                            $uploadError = 'upload_ext_error';
                        }
                    } else {
                        $uploadError = 'upload_error';
                    }
                }

                if ($coverImage) {
                    $stmt = $pdo->prepare("
                        UPDATE courses SET title=:t, description=:d, enrollment_key=:ek,
                            start_date=:sd, end_date=:ed, eval_deadline=:ev, exam_duration_minutes=:em, cover_image=:ci
                        WHERE id=:id AND teacher_id=:tid
                    ");
                    $stmt->execute([
                        't'=>$title,'d'=>$description,'ek'=>$enrollKey,
                        'sd'=>$startDate,'ed'=>$endDate,'ev'=>$evalDeadline,'em'=>$examMinutes,
                        'ci'=>$coverImage,
                        'id'=>$selectedCourse['id'],'tid'=>$user['id'],
                    ]);
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
                    INSERT INTO live_eval_sessions (course_id, teacher_id, title, session_code, start_time, end_time, default_time_limit, status)
                    VALUES (:cid, :tid, :title, :code, :start, :end, :limit, 0)
                ");
                $stmt->execute([
                    'cid'   => $selectedCourse['id'],
                    'tid'   => $teacherId,
                    'title' => $title,
                    'code'  => $code,
                    'start' => $startTime,
                    'end'   => $endTime,
                    'limit' => $limit
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
                        is_async = :is_async, async_deadline = :async_deadline, status = 1
                    WHERE id = :id AND teacher_id = :tid
                ");
                $stmt->execute([
                    'title' => $title,
                    'start' => $startTime,
                    'end'   => $endTime,
                    'limit' => $limit,
                    'is_async' => $isAsync,
                    'async_deadline' => $asyncDeadline,
                    'id'    => $sid,
                    'tid'   => $teacherId
                ]);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_session_updated&open_session={$sid}#tab-live-eval"); exit;
            }
        }

        // ── N. Activer/Désactiver une séance de téléévaluation ────────
        if ($action === 'toggle_live_session') {
            $sid = (int)($_POST['session_id'] ?? 0);
            $status = (int)($_POST['status'] ?? 0) === 1 ? 1 : 0;
            if ($sid > 0) {
                $stmt = $pdo->prepare("UPDATE live_eval_sessions SET status=:status WHERE id=:id AND teacher_id=:tid");
                $stmt->execute(['status' => $status, 'id' => $sid, 'tid' => $teacherId]);
                header("Location: /teacher/dashboard.php?course_id={$selectedCourse['id']}&success=live_session_toggled&open_session={$sid}#tab-live-eval"); exit;
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
                $imagePath = null;
                if (!empty($_FILES['live_image']['tmp_name']) && is_uploaded_file($_FILES['live_image']['tmp_name'])) {
                    $ext = strtolower(pathinfo($_FILES['live_image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
                        $uploadDir = __DIR__ . '/../uploads/live_questions/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $filename = uniqid('live_q_', true) . '.' . $ext;
                        if (move_uploaded_file($_FILES['live_image']['tmp_name'], $uploadDir . $filename)) {
                            $imagePath = $filename;
                        }
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
                        @unlink(__DIR__ . '/../uploads/live_questions/' . $qData['image_path']);
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
                            @unlink(__DIR__ . '/../uploads/live_questions/' . $img);
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
    }

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
<!DOCTYPE html>
<html lang="fr" class="h-full sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png" sizes="32x32">
    <link rel="apple-touch-icon" href="/assets/img/favicon.png">

    <title>Espace Enseignant — StudyVibe</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <!-- Bibliothèques KaTeX pour le rendu des formules mathématiques et caractères spéciaux en LaTeX -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script>
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
    
    <?= csrfMetaTag(); ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/assets/js/app.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans:  ['Inter', 'sans-serif'],
                        serif: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        .fade-in  { animation: fadeIn .4s ease-out forwards; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:none} }
        .slide-in { animation: slideIn .25s ease-out forwards; }
        @keyframes slideIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }

        /* Accordion panel */
        .lesson-panel { display: none; }
        .lesson-panel.open { display: block; }

        /* Scrollable modal content */
        .modal-inner { max-height: 85vh; overflow-y: auto; }

        /* Icon button */
        .icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px;
            border: 1px solid #E5E5E7; background: #F5F5F7;
            cursor: pointer; border-radius: 2px; transition: all .08s;
        }
        .icon-btn:hover { border-color: #004B23; background: #fff; }
        .icon-btn.danger:hover { border-color: #D32F2F; background: #fff2f2; }

        /* Instant tactile feedback for all buttons */
        button:active,
        .sv-btn-ms:active,
        .sv-btn-ms-outline:active,
        .icon-btn:active,
        .sidebar-tab-btn:active {
            transform: scale(0.97) !important;
            opacity: 0.9;
            transition: transform 0.05s ease-out !important;
        }

        /* Video URL rows */
        .video-row + .video-row { margin-top: .5rem; }
        .resource-row + .resource-row { margin-top: .5rem; }
    </style>
</head>
<body class="font-sans antialiased text-[#111111] dark:text-white bg-[#FAF9F6] dark:bg-[#121212] min-h-screen flex flex-col md:flex-row overflow-x-hidden">

    <!-- MOBILE TOP BAR -->
    <div class="w-full md:hidden bg-[#004B23] text-white py-4 px-4 flex justify-between items-center sticky top-0 z-30 shadow-md">
        <div class="flex items-center gap-3">
            <button onclick="toggleMobileDrawer()" class="p-1 text-white hover:text-white/80 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            <span class="font-serif text-lg font-bold tracking-tight">StudyVibe</span>
        </div>
        <div class="flex items-center gap-3">
            <!-- Notifications (Mobile) -->
            <div class="relative" id="mobile-notif-wrap">
                <button type="button" onclick="toggleMobileNotifs()" class="relative p-1.5 text-white hover:text-white/80 transition-colors rounded-full" aria-label="Notifications">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                    <span id="mobile-notif-count" class="hidden absolute -top-1 -right-1 bg-[#D32F2F] text-white text-[9px] font-bold px-1 py-0.2 rounded-full min-w-[15px] text-center border border-white">0</span>
                </button>
                <div id="mobile-notif-panel-container" class="hidden absolute right-0 top-full mt-2 w-72 bg-white dark:bg-[#1E1E1E] border border-[#E5E5E7] dark:border-[#2C2C2C] shadow-xl z-50 text-left text-sm rounded-lg overflow-hidden flex flex-col max-h-[300px]">
                    <div class="p-3 border-b border-[#E5E5E7] dark:border-[#2C2C2C] flex justify-between items-center bg-[#F9F7F4] dark:bg-[#252525] flex-shrink-0">
                        <span class="font-serif font-semibold text-xs uppercase tracking-wider text-[#111111] dark:text-white">Notifications</span>
                        <button onclick="markAllNotificationsRead(event)" class="text-[10px] text-[#004B23] dark:text-[#34C759] hover:underline font-semibold">Tout marquer comme lu</button>
                    </div>
                    <div id="mobile-notif-panel" class="overflow-y-auto flex-grow max-h-[250px] dark:text-white/80"></div>
                </div>
            </div>
            <a href="/logout.php" class="text-xs text-red-300 uppercase tracking-wider font-semibold hover:underline">Déconnexion</a>
        </div>
    </div>

    <!-- MOBILE DRAWER -->
    <div id="mobile-drawer" class="fixed inset-0 z-50 md:hidden hidden">
        <div onclick="toggleMobileDrawer()" class="fixed inset-0 bg-black/50 transition-opacity"></div>
        <div class="relative flex-1 flex flex-col max-w-xs w-full bg-[#004B23] pt-5 pb-4 transition-transform duration-300">
            <div class="absolute top-0 right-0 -mr-12 pt-2">
                <button onclick="toggleMobileDrawer()" class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                    <span class="sr-only">Close sidebar</span>
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="flex-shrink-0 flex items-center px-6 gap-3 border-b border-[#003619] pb-4">
                <span class="font-serif text-xl font-bold tracking-tight text-white">StudyVibe</span>
                <span class="text-[10px] uppercase tracking-widest bg-[#003619] text-white px-2 py-0.5 border border-[#002610] font-mono">Enseignant</span>
            </div>
            <div class="mt-5 flex-1 h-0 overflow-y-auto px-4 space-y-6">
                <!-- Quick actions -->
                <button type="button" onclick="toggleModal('create-course-modal'); toggleMobileDrawer();"
                        class="w-full px-4 py-2.5 bg-white text-[#092215] text-[10px] font-bold uppercase tracking-wider hover:bg-[#E2ECE9] rounded-lg transition-colors flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Créer un Cours
                </button>

                <!-- Course List -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-white/50">Sélectionner un Cours</label>
                    <div class="space-y-1">
                        <?php if (empty($myCourses)): ?>
                            <p class="text-xs italic text-white/40">Aucun cours disponible.</p>
                        <?php else: ?>
                            <?php foreach ($myCourses as $mc): 
                                $isSel = ($selectedCourse && (int)$selectedCourse['id'] === (int)$mc['id']);
                            ?>
                                <a href="/teacher/dashboard.php?course_id=<?= $mc['id']; ?>"
                                   class="block px-3 py-2 rounded-lg text-xs transition-all <?= $isSel ? 'bg-white/20 text-white font-semibold' : 'hover:bg-white/10 text-white/70'; ?>">
                                    <?= htmlspecialchars($mc['title']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="space-y-1">
                    <button onclick="switchDashboardTab('tab-overview'); toggleMobileDrawer();" data-tab-target="tab-overview" 
                            class="sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 text-white hover:bg-white/10">
                        Vue d'ensemble
                    </button>
                    <button onclick="switchDashboardTab('tab-course'); toggleMobileDrawer();" data-tab-target="tab-course" 
                            class="sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 <?= $selectedCourse ? 'text-white hover:bg-white/10' : 'opacity-40 cursor-not-allowed text-white/40' ?>">
                        Plan &amp; Contenu
                    </button>
                    <button onclick="switchDashboardTab('tab-live-eval'); toggleMobileDrawer();" data-tab-target="tab-live-eval" 
                            class="sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 <?= $selectedCourse ? 'text-white hover:bg-white/10' : 'opacity-40 cursor-not-allowed text-white/40' ?>">
                        Téléévaluations (Live)
                    </button>

                    <button onclick="switchDashboardTab('tab-grades'); toggleMobileDrawer();" data-tab-target="tab-grades" 
                            class="sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 <?= $selectedCourse ? 'text-white hover:bg-white/10' : 'opacity-40 cursor-not-allowed text-white/40' ?>">
                        Notes &amp; Suivi
                    </button>
                    <button onclick="switchDashboardTab('tab-comments'); toggleMobileDrawer();" data-tab-target="tab-comments" 
                            class="sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 <?= $selectedCourse ? 'text-white hover:bg-white/10' : 'opacity-40 cursor-not-allowed text-white/40' ?>">
                        Communauté &amp; Q&amp;R
                    </button>
                </nav>
            </div>
            <div class="flex-shrink-0 flex border-t border-[#003619] p-4 bg-[#003c1c] items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[#003619] flex items-center justify-center border border-white/20 flex-shrink-0">
                    <svg class="w-4 h-4 text-white/80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div class="flex-grow overflow-hidden">
                    <div class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($user['name']); ?></div>
                    <div class="text-[10px] text-white/60 truncate"><?= htmlspecialchars($user['email']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- LEFT SIDEBAR (Desktop) -->
    <aside class="w-64 bg-[#004B23] text-white flex flex-col justify-between h-screen sticky top-0 border-r border-[#003619] hidden md:flex flex-shrink-0 z-40">
        <!-- Logo / Brand Header -->
        <div class="p-6 border-b border-[#003619] flex items-center gap-3">
            <svg class="w-8 h-8" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="46" stroke="#FFFFFF" stroke-width="3.5" />
                <line x1="33" y1="31" x2="62" y2="25" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="49" y2="53" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="14" y2="13" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="42" y2="11" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="20" y2="53" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <line x1="49" y1="53" x2="62" y2="25" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <circle cx="62" cy="25" r="6" fill="#34C759" />
                <circle cx="49" cy="53" r="6" fill="#34C759" />
                <circle cx="33" cy="31" r="6" fill="#34C759" />
                <circle cx="14" cy="13" r="6" fill="#34C759" />
                <circle cx="42" cy="11" r="6" fill="#34C759" />
                <circle cx="20" cy="53" r="6" fill="#34C759" />
                <path d="M56 10 C52 14, 52 24, 52 29 C52 31, 50 33, 49 33 L45 33 L49 35 C50 37, 51 38, 50 40 C49 41, 47 42, 49 44 C51 45, 54 46, 56 46 C59 46, 65 38, 66 41 C68 46, 60 52, 56 60 C51 68, 50 78, 53 88" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M33 55 C32 52, 32 48, 33 46 C34 44, 36 44, 37 47 C37 50, 37 53, 37 55 C37 51, 38 46, 39 44 C40 42, 42 42, 43 45 C43 48, 43 51, 43 54 C43 51, 44 47, 45 45 C46 43, 48 43, 49 46 C50 49, 51 57, 51 68 C51 75, 49 81, 47 85" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M33 55 C34 61, 35 68, 37 75 C38 81, 39 84, 40 86" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span class="font-serif text-lg font-semibold tracking-tight text-white">StudyVibe</span>
            <span class="text-[9px] uppercase tracking-widest bg-[#003619] text-white px-2 py-0.5 border border-[#002610] ml-2 font-mono">Enseignant</span>
        </div>

        <!-- Navigation Menu -->
        <div class="flex-grow py-6 px-4 space-y-6 overflow-y-auto">
            <!-- Create course button -->
            <button type="button" onclick="toggleModal('create-course-modal')"
                    class="w-full px-4 py-2.5 bg-white text-[#004B23] hover:bg-white/95 text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Créer un Cours
            </button>

            <!-- Course Select Dropdown or Listing -->
            <div class="space-y-2">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-white/50">Sélectionner un Cours</label>
                <div class="relative">
                    <select onchange="location.href='/teacher/dashboard.php?course_id='+this.value;" class="w-full bg-[#003619] text-white border border-[#002610] rounded-lg py-2 px-3 text-xs focus:outline-none focus:border-white/40 appearance-none cursor-pointer">
                        <option value="0" <?= !$selectedCourse ? 'selected' : '' ?>>-- Choisir un cours --</option>
                        <?php foreach ($myCourses as $mc): ?>
                            <option value="<?= $mc['id']; ?>" <?= ($selectedCourse && (int)$selectedCourse['id'] === (int)$mc['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($mc['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-white/60">
                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                    </div>
                </div>
            </div>

            <!-- Workspace Tabs -->
            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-white/50 mb-2">Workspace</label>
                
                <button onclick="switchDashboardTab('tab-overview')" data-tab-target="tab-overview" 
                        class="sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 text-white font-semibold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"/></svg>
                    Vue d'ensemble
                </button>

                <button onclick="switchDashboardTab('tab-course')" data-tab-target="tab-course" 
                        class="sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 <?= $selectedCourse ? 'text-white/70 hover:text-white hover:bg-white/10' : 'opacity-40 cursor-not-allowed text-white/40' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Plan &amp; Contenu
                </button>

                <button onclick="switchDashboardTab('tab-live-eval')" data-tab-target="tab-live-eval" 
                        class="sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 <?= $selectedCourse ? 'text-white/70 hover:text-white hover:bg-white/10' : 'opacity-40 cursor-not-allowed text-white/40' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Téléévaluations (Live)
                </button>



                <button onclick="switchDashboardTab('tab-grades')" data-tab-target="tab-grades" 
                        class="sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 <?= $selectedCourse ? 'text-white/70 hover:text-white hover:bg-white/10' : 'opacity-40 cursor-not-allowed text-white/40' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                    Notes &amp; Suivi
                </button>

                <button onclick="switchDashboardTab('tab-comments')" data-tab-target="tab-comments" 
                        class="sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 <?= $selectedCourse ? 'text-white/70 hover:text-white hover:bg-white/10' : 'opacity-40 cursor-not-allowed text-white/40' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    Communauté &amp; Q&amp;R
                </button>
            </div>
        </div>

        <!-- Profile / Sidebar Footer -->
        <div class="p-4 border-t border-[#003619] bg-[#003c1c] flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-9 h-9 rounded-full bg-[#003619] flex items-center justify-center border border-white/20 flex-shrink-0">
                    <svg class="w-4 h-4 text-white/80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div class="flex-grow overflow-hidden">
                    <div class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($user['name']); ?></div>
                    <div class="text-[10px] text-white/60 truncate"><?= htmlspecialchars($user['email']); ?></div>
                </div>
            </div>
            <a href="/logout.php" title="Déconnexion" class="text-white/60 hover:text-red-400 transition-colors flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTAINER -->
    <div class="flex-grow flex flex-col min-h-screen overflow-x-hidden">

        <!-- Top Header Controls (Desktop) -->
        <header class="hidden md:flex justify-between items-center py-4 px-8 border-b border-[#E5E5E7] dark:border-[#2C2C2C] bg-white dark:bg-[#1A1A1A] sticky top-0 z-30">
            <div class="flex items-center gap-2">
                <span class="text-xs text-[#888888] dark:text-[#AAAAAA] uppercase tracking-wider font-semibold">Console Enseignant</span>
            </div>
            <div class="flex items-center gap-4">
                <!-- Notifications -->
                <div class="relative" id="notif-wrap">
                    <button type="button" id="notif-btn" class="relative p-1.5 text-[#555555] dark:text-[#AAAAAA] hover:text-[#004B23] dark:hover:text-[#34C759] transition-colors rounded-full hover:bg-[#F5F5F7] dark:hover:bg-[#252525]" aria-label="Notifications">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span id="notif-count" class="hidden absolute -top-1 -right-1 bg-[#D32F2F] text-white text-[9px] font-bold px-1 py-0.2 rounded-full min-w-[15px] text-center border border-white">0</span>
                    </button>
                    <div id="notif-panel-container" class="hidden absolute right-0 top-full mt-2 w-80 bg-white dark:bg-[#1E1E1E] border border-[#E5E5E7] dark:border-[#2C2C2C] shadow-xl z-50 text-left text-sm rounded-lg overflow-hidden flex flex-col max-h-[360px]">
                        <div class="p-3 border-b border-[#E5E5E7] dark:border-[#2C2C2C] flex justify-between items-center bg-[#F9F7F4] dark:bg-[#252525] flex-shrink-0">
                            <span class="font-serif font-semibold text-xs uppercase tracking-wider text-[#111111] dark:text-white">Notifications</span>
                            <button onclick="markAllNotificationsRead(event)" class="text-[10px] text-[#004B23] dark:text-[#34C759] hover:underline font-semibold">Tout marquer comme lu</button>
                        </div>
                        <div id="notif-panel" class="overflow-y-auto flex-grow max-h-[300px] dark:text-white/80"></div>
                    </div>
                </div>

                <button class="sv-dark-toggle" data-dark-toggle title="Mode sombre"></button>
                
                <div class="relative inline-block text-left">
                    <select id="lang-selector" onchange="changeLanguage(this.value)" class="bg-transparent text-xs border border-[#E5E5E7] dark:border-[#2C2C2C] text-[#555555] dark:text-[#AAAAAA] rounded-sm py-1 px-2 focus:outline-none focus:border-[#004B23] dark:focus:border-[#34C759]">
                        <option value="fr" <?= TranslationService::getLang() === 'fr' ? 'selected' : ''; ?>>FR</option>
                        <option value="en" <?= TranslationService::getLang() === 'en' ? 'selected' : ''; ?>>EN</option>
                    </select>
                </div>
            </div>
        </header>

        <!-- Main Workspace Area -->
        <main class="flex-grow p-6 md:p-10 lg:p-12 space-y-10 w-full mx-auto bg-[#FAF9F6] dark:bg-[#121212] transition-colors duration-200">

            <?php if ($successMsg): ?>
                <div class="p-4 bg-[#EAF2EC] border border-[#004B23]/20 dark:bg-[#1D3D25] dark:border-[#34C759]/20 text-[#004B23] dark:text-[#34C759] text-sm font-semibold rounded-lg fade-in">
                    <?= htmlspecialchars($successMsg); ?>
                </div>
            <?php endif; ?>

            <?php
            $errorKey = (string)($_GET['error'] ?? '');
            $errorMessages = [
                'upload_move_error' => '❌ Échec du déplacement du fichier téléchargé. Veuillez vérifier les droits d\'écriture du dossier uploads/course-covers/.',
                'upload_size_error' => '❌ L\'image de couverture dépasse la taille maximale autorisée de 3 Mo.',
                'upload_ext_error'  => '❌ Format d\'image non autorisé. Formats acceptés : JPG, JPEG, PNG, WEBP.',
                'upload_error'      => '❌ Une erreur est survenue lors du chargement de l\'image de couverture.',
            ];
            $errorMsg = $errorMessages[$errorKey] ?? null;
            if ($errorMsg): ?>
                <div class="p-4 bg-red-50 border border-red-200 dark:bg-red-950/20 dark:border-red-800/30 text-red-800 dark:text-red-300 text-sm font-semibold rounded-lg fade-in">
                    <?= htmlspecialchars($errorMsg); ?>
                </div>
            <?php endif; ?>

            <!-- 1. VUE D'ENSEMBLE (tab-overview) -->
            <div id="tab-overview" class="tab-content space-y-12">
            
            <?php if (!$selectedCourse): ?>
                
                <!-- Welcome/State message when no course is active -->
                <div class="text-center py-16 px-4 bg-white/80 dark:bg-[#1E1E1E]/80 backdrop-blur-md border border-[#E5E5E7] dark:border-[#2C2C2C] rounded-2xl max-w-2xl mx-auto space-y-6">
                    <svg class="w-16 h-16 text-[#004B23] dark:text-[#34C759] mx-auto opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
                              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <h2 class="font-serif text-3xl font-light text-[#111111] dark:text-white mb-2">Créez ou sélectionnez un cours</h2>
                    <p class="text-sm font-light text-[#555555] dark:text-[#AAAAAA]">
                        Utilisez le bouton « Créer un cours » dans la barre latérale, ou sélectionnez un cours existant pour commencer à gérer son contenu, ses séances de live, et suivre les notes de vos élèves.
                    </p>
                    
                    <?php if (!empty($modules)): ?>
                        <button type="button" onclick="toggleModal('create-course-modal')"
                                class="px-6 py-2.5 bg-[#111111] dark:bg-[#FFFFFF] text-white dark:text-[#111111] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] dark:hover:bg-[#34C759] dark:hover:text-white rounded-lg transition-all shadow-sm">
                            + Créer mon premier cours
                        </button>
                    <?php else: ?>
                        <p class="text-xs text-[#888888] dark:text-[#AAAAAA] italic mt-4">Aucun module disponible — le promoteur doit d'abord créer un module de formation.</p>
                    <?php endif; ?>
                </div>

                <!-- Global Stats Grid (for teacher overall) -->
                <?php
                // Fetch stats for all teacher courses combined
                $allCoursesCount = count($myCourses);
                
                $totalEnrolledCount = 0;
                if ($allCoursesCount > 0) {
                    $cids = array_map(fn($c) => (int)$c['id'], $myCourses);
                    $cidsStr = implode(',', $cids);
                    $totalEnrolledCount = (int)$pdo->query("SELECT COUNT(*) FROM enrollments WHERE course_id IN ($cidsStr)")->fetchColumn();
                }
                
                // Total live sessions (QuizBox)
                $totalQuizSessions = 0;
                if ($allCoursesCount > 0) {
                    $totalQuizSessions = (int)$pdo->query("SELECT COUNT(*) FROM live_eval_sessions WHERE course_id IN ($cidsStr)")->fetchColumn();
                }
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-12 max-w-5xl mx-auto">
                    <div class="bg-white/80 dark:bg-[#1E1E1E]/80 backdrop-blur-md border border-[#E5E5E7] dark:border-[#2C2C2C] p-6 rounded-xl shadow-sm flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-[#555555] dark:text-[#AAAAAA] mb-1">Total Cours</div>
                            <div class="text-3xl font-semibold text-[#111111] dark:text-white"><?= $allCoursesCount ?></div>
                        </div>
                        <svg class="w-8 h-8 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                    </div>
                    <div class="bg-white/80 dark:bg-[#1E1E1E]/80 backdrop-blur-md border border-[#E5E5E7] dark:border-[#2C2C2C] p-6 rounded-xl shadow-sm flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-[#555555] dark:text-[#AAAAAA] mb-1">Total Apprenants</div>
                            <div class="text-3xl font-semibold text-[#111111] dark:text-white"><?= $totalEnrolledCount ?></div>
                        </div>
                        <svg class="w-8 h-8 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.109A11.386 11.386 0 0110.089 20.5a11.378 11.378 0 01-4.94-1.27v-.1c0-2.22 3.584-3.496 5.894-3.496 2.31 0 5.894 1.27 5.894 3.496m-4.121-6.953A4.125 4.125 0 1111 8a4.125 4.125 0 012.879 4.175m4.746-1.125a3.375 3.375 0 11-3.375-3.375 3.375 3.375 0 013.375 3.375z" /></svg>
                    </div>

                    <div class="bg-white/80 dark:bg-[#1E1E1E]/80 backdrop-blur-md border border-[#E5E5E7] dark:border-[#2C2C2C] p-6 rounded-xl shadow-sm flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-[#555555] dark:text-[#AAAAAA] mb-1">Téléévaluations</div>
                            <div class="text-3xl font-semibold text-[#111111] dark:text-white"><?= $totalQuizSessions ?></div>
                        </div>
                        <svg class="w-8 h-8 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                </div>

            <?php else: ?>

                <!-- Course Title Header -->
                <div class="border-b border-[#E5E5E7] dark:border-[#2C2C2C] pb-8 space-y-3">
                    <?php if (!empty($selectedCourse['cover_image'])): ?>
                        <div class="w-full h-48 rounded-xl overflow-hidden mb-6 relative bg-[#004B23]">
                            <img src="/download.php?type=cover&file=<?= urlencode($selectedCourse['cover_image']); ?>" 
                                 alt="Couverture du cours" 
                                 class="w-full h-full object-cover">
                        </div>
                    <?php endif; ?>
                    <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                        <div class="space-y-1 flex-grow">
                            <h2 class="font-serif text-4xl font-light text-[#111111] dark:text-white">
                                <?= htmlspecialchars($selectedCourse['title']); ?>
                            </h2>
                            <p class="text-sm font-light text-[#555555] dark:text-[#AAAAAA] max-w-3xl leading-relaxed">
                                <?= htmlspecialchars($selectedCourse['description']); ?>
                            </p>
                            <div class="text-xs text-[#888888] dark:text-[#AAAAAA] font-mono pt-1">
                                Clé d'inscription :
                                <span class="font-semibold text-[#111111] dark:text-white">
                                    <?= $selectedCourse['enrollment_key'] ? htmlspecialchars($selectedCourse['enrollment_key']) : 'Aucune (libre)'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-2 flex-shrink-0">
                            <button onclick="openShareModal(<?= $selectedCourse['id'] ?>)"
                                class="px-3 py-1.5 bg-[#004B23] dark:bg-[#34C759] text-white text-[11px] font-semibold uppercase tracking-wider hover:bg-[#006630] dark:hover:bg-[#28a745] rounded-lg flex items-center gap-1.5 transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                                </svg>
                                Partager
                            </button>
                            <button onclick="openEditCourseModal()"
                                class="px-3 py-1.5 bg-[#F5F5F7] dark:bg-[#2C2C2E] border border-[#E5E5E7] dark:border-[#2C2C2C] text-[#555555] dark:text-[#AAAAAA] text-[11px] font-semibold uppercase tracking-wider hover:border-[#004B23] dark:hover:border-[#34C759] rounded-lg transition-colors">
                                ✎ Éditer le cours
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Course KPI Metrics -->
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 my-8">
                    <!-- KPI 1: Apprenants -->
                    <div class="bg-white/80 dark:bg-[#1E1E1E]/80 backdrop-blur-md border border-[#E5E5E7] dark:border-[#2C2C2C] p-6 hover:shadow-md transition-all cursor-pointer flex flex-col justify-between rounded-xl shadow-sm" onclick="switchDashboardTab('tab-grades'); switchGradesSubTab('subtab-students');">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-xs font-semibold uppercase tracking-wider text-[#555555] dark:text-[#AAAAAA]">Apprenants</span>
                                <svg class="w-6 h-6 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div class="text-3xl font-semibold text-[#111111] dark:text-white mb-2"><?= $studentCount; ?></div>
                            <p class="text-xs text-[#888888] dark:text-[#AAAAAA] font-light">Élèves inscrits et progression.</p>
                        </div>
                        <div class="mt-4 text-xs font-semibold text-[#004B23] dark:text-[#34C759] uppercase tracking-wider flex items-center gap-1">
                            Consulter <span>→</span>
                        </div>
                    </div>

                    <!-- KPI 2: Quiz Leçons -->
                    <div class="bg-white/80 dark:bg-[#1E1E1E]/80 backdrop-blur-md border border-[#E5E5E7] dark:border-[#2C2C2C] p-6 hover:shadow-md transition-all cursor-pointer flex flex-col justify-between rounded-xl shadow-sm" onclick="switchDashboardTab('tab-grades'); switchGradesSubTab('subtab-quiz');">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-xs font-semibold uppercase tracking-wider text-[#555555] dark:text-[#AAAAAA]">Quiz Leçons</span>
                                <svg class="w-6 h-6 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="text-3xl font-semibold text-[#111111] dark:text-white mb-2"><?= $lessonQuizCount; ?></div>
                            <p class="text-xs text-[#888888] dark:text-[#AAAAAA] font-light">Quiz configurés pour les leçons.</p>
                        </div>
                        <div class="mt-4 text-xs font-semibold text-[#004B23] dark:text-[#34C759] uppercase tracking-wider flex items-center gap-1">
                            Consulter <span>→</span>
                        </div>
                    </div>

                    <!-- KPI 3: Certificats -->
                    <div class="bg-white/80 dark:bg-[#1E1E1E]/80 backdrop-blur-md border border-[#E5E5E7] dark:border-[#2C2C2C] p-6 hover:shadow-md transition-all cursor-pointer flex flex-col justify-between rounded-xl shadow-sm" onclick="switchDashboardTab('tab-grades'); switchGradesSubTab('subtab-certs');">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-xs font-semibold uppercase tracking-wider text-[#555555] dark:text-[#AAAAAA]">Certificats</span>
                                <svg class="w-6 h-6 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                            </div>
                            <div class="text-3xl font-semibold text-[#111111] dark:text-white mb-2"><?= $certificatesCount; ?></div>
                            <p class="text-xs text-[#888888] dark:text-[#AAAAAA] font-light">Certificats de module délivrés.</p>
                        </div>
                        <div class="mt-4 text-xs font-semibold text-[#004B23] dark:text-[#34C759] uppercase tracking-wider flex items-center gap-1">
                            Consulter <span>→</span>
                        </div>
                    </div>

                    <!-- KPI 4: Téléévaluations -->
                    <div class="bg-white/80 dark:bg-[#1E1E1E]/80 backdrop-blur-md border border-[#E5E5E7] dark:border-[#2C2C2C] p-6 hover:shadow-md transition-all cursor-pointer flex flex-col justify-between rounded-xl shadow-sm" onclick="switchDashboardTab('tab-live-eval')">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-xs font-semibold uppercase tracking-wider text-[#555555] dark:text-[#AAAAAA]">Téléévaluations</span>
                                <svg class="w-6 h-6 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="text-3xl font-semibold text-[#111111] dark:text-white mb-2"><?= count($liveSessions); ?></div>
                            <p class="text-xs text-[#888888] dark:text-[#AAAAAA] font-light">Sessions QuizBox synchrones et devoirs libres.</p>
                        </div>
                        <div class="mt-4 text-xs font-semibold text-[#004B23] dark:text-[#34C759] uppercase tracking-wider flex items-center gap-1">
                            Gérer <span>→</span>
                        </div>
                    </div>


                </div>

                <!-- Info guide box -->
                <div class="bg-white/85 dark:bg-[#1E1E1E]/85 border border-[#E5E5E7] dark:border-[#2C2C2C] p-8 rounded-xl space-y-4">
                    <h4 class="font-serif text-xl font-light text-[#111111] dark:text-white">Bienvenue dans votre Workspace de Cours</h4>
                    <p class="text-sm font-light text-[#555555] dark:text-[#AAAAAA] leading-relaxed">
                        Sélectionnez les différents onglets de la barre latérale pour concevoir les modules du cours, lancer des sessions d'évaluation interactives (QuizBox) ou consulter les notes et les progressions détaillées de vos étudiants.
                    </p>
                </div>

            <?php endif; ?>

            </div>

        <?php if ($selectedCourse): ?>

            <!-- 2. PLAN & CONTENU (tab-course) -->
            <div id="tab-course" class="tab-content hidden space-y-12">
                <div class="flex justify-between items-center">
                    <h3 class="font-serif text-2xl font-light text-[#111111]">Plan du Cours & Chapitres</h3>
                    <button onclick="toggleModal('chapter-modal')"
                        class="sv-btn-ms">
                        + Ajouter un Chapitre
                    </button>
                </div>

                <!-- Liste des chapitres -->
                <div class="space-y-6">
                    <?php if (empty($chapters)): ?>
                        <div class="p-8 border border-dashed border-[#E5E5E7] text-center text-sm font-light text-[#888888]">
                            Aucun chapitre créé. Cliquez sur le bouton ci-dessus pour structurer votre premier chapitre.
                        </div>
                    <?php else: ?>
                        <?php foreach ($chapters as $ch): ?>
                        <div class="sv-chapter-card" id="chapter-<?= $ch['id']; ?>">

                            <!-- En-tête du chapitre -->
                            <div class="flex justify-between items-center px-6 py-4 border-b border-[#E5E5E7] bg-[#F5F5F7]">
                                <h4 class="font-serif text-base font-medium text-[#111111]">
                                    <?= htmlspecialchars($ch['title']); ?>
                                </h4>
                                <div class="flex items-center gap-2">
                                    <button onclick="openEditChapterModal(<?= $ch['id']; ?>, <?= htmlspecialchars(json_encode($ch['title'])); ?>)"
                                        class="icon-btn" title="Modifier le chapitre">
                                        <svg class="w-3.5 h-3.5 text-[#555555]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z"/>
                                        </svg>
                                    </button>
                                    <button onclick="confirmDeleteChapter(<?= $ch['id']; ?>, <?= htmlspecialchars(json_encode($ch['title'])); ?>)"
                                        class="icon-btn danger" title="Supprimer le chapitre">
                                        <svg class="w-3.5 h-3.5 text-[#D32F2F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    <button onclick="openLessonModal(<?= $ch['id']; ?>)"
                                        class="px-3 py-1 bg-white border border-[#E5E5E7] text-[11px] font-semibold uppercase tracking-wider hover:border-[#004B23] rounded-sm text-[#004B23]">
                                        + Leçon
                                    </button>
                                </div>
                            </div>

                            <!-- Leçons du chapitre -->
                            <div class="divide-y divide-[#E5E5E7]">
                                <?php if (empty($ch['lessons'])): ?>
                                    <p class="text-xs text-[#888888] italic font-light px-6 py-4">Aucune leçon dans ce chapitre.</p>
                                <?php else: ?>
                                    <?php foreach ($ch['lessons'] as $les): ?>
                                    <?php $isOpen = $openLessonId === (int)$les['id']; ?>
                                    <div class="lesson-item" id="lesson-item-<?= $les['id']; ?>">

                                        <!-- Ligne principale de la leçon -->
                                        <div class="px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-3 flex-grow min-w-0">
                                                <!-- Accordion toggle -->
                                                <button onclick="toggleLesson(<?= $les['id']; ?>)"
                                                    class="flex-shrink-0 w-5 h-5 text-[#888888] hover:text-[#004B23] transition-colors"
                                                    title="Voir les détails">
                                                    <svg id="chevron-<?= $les['id']; ?>" class="w-4 h-4 transition-transform <?= $isOpen ? 'rotate-90':'' ?>"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                </button>
                                                <div class="min-w-0">
                                                    <div class="font-medium text-sm text-[#111111] flex items-center gap-2 flex-wrap">
                                                        <?= htmlspecialchars($les['title']); ?>
                                                        <span class="text-[10px] font-mono uppercase tracking-widest bg-[#F5F5F7] border border-[#E5E5E7] px-1.5 py-0.5 text-[#555555]">
                                                            <?= htmlspecialchars($les['content_type']); ?>
                                                        </span>
                                                        <?php if (!empty($les['videos'])): ?>
                                                            <span class="text-[10px] font-mono bg-blue-50 border border-blue-200 text-blue-700 px-1.5 py-0.5">
                                                                <?= count($les['videos']); ?> vidéo<?= count($les['videos']) > 1 ? 's':''; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($les['resources'])): ?>
                                                            <span class="text-[10px] font-mono bg-amber-50 border border-amber-200 text-amber-700 px-1.5 py-0.5">
                                                                <?= count($les['resources']); ?> ressource<?= count($les['resources']) > 1 ? 's':''; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-xs font-mono text-[#888888] mt-0.5">
                                                        <?= $les['question_count']; ?> question(s) d'évaluation
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Actions leçon -->
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <button onclick="openEditLessonModal(<?= htmlspecialchars(json_encode($les), ENT_QUOTES); ?>)"
                                                    class="icon-btn" title="Modifier la leçon">
                                                    <svg class="w-3.5 h-3.5 text-[#555555]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z"/>
                                                    </svg>
                                                </button>
                                                <button onclick="confirmDeleteLesson(<?= $les['id']; ?>, <?= htmlspecialchars(json_encode($les['title'])); ?>)"
                                                    class="icon-btn danger" title="Supprimer la leçon">
                                                    <svg class="w-3.5 h-3.5 text-[#D32F2F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                                <button onclick="openQuestionModal(<?= $les['id']; ?>, <?= htmlspecialchars(json_encode($les['title'])); ?>)"
                                                    class="px-3 py-1 bg-[#F5F5F7] border border-[#E5E5E7] text-[11px] font-semibold uppercase tracking-wider hover:border-[#004B23] rounded-sm">
                                                    + Question
                                                </button>
                                                <?php if (!empty(trim($les['text_content'] ?? ''))): ?>
                                                    <button onclick="generateAiQuiz(<?= $les['id']; ?>, <?= htmlspecialchars(json_encode($les['title'])); ?>)"
                                                        class="px-3 py-1 bg-[#111111] text-[#FFFFFF] text-[11px] font-semibold uppercase tracking-wider hover:bg-[#004B23] rounded-sm">
                                                        Quiz IA
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Panel accordéon : détails de la leçon -->
                                        <div class="lesson-panel <?= $isOpen ? 'open':'' ?> slide-in border-t border-[#E5E5E7] bg-[#FAFAFA] px-8 py-5 space-y-5"
                                            id="panel-<?= $les['id']; ?>">

                                            <!-- Contenu textuel -->
                                            <?php if (!empty($les['text_content'])): ?>
                                            <div class="space-y-1">
                                                <p class="text-[10px] font-semibold uppercase tracking-widest text-[#888888]">Contenu textuel</p>
                                                <p class="text-xs font-light text-[#444444] leading-relaxed max-w-2xl line-clamp-4">
                                                    <?= htmlspecialchars($les['text_content']); ?>
                                                </p>
                                            </div>
                                            <?php endif; ?>

                                            <!-- PDF -->
                                            <?php if (!empty($les['pdf_path'])): ?>
                                            <div class="space-y-1">
                                                <p class="text-[10px] font-semibold uppercase tracking-widest text-[#888888]">Document PDF</p>
                                                <a href="<?= mediaUrl('pdf', $les['pdf_path']); ?>" target="_blank"
                                                    class="text-xs text-[#004B23] underline font-mono">
                                                    <?= htmlspecialchars($les['pdf_path']); ?>
                                                </a>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Vidéos -->
                                            <?php if (!empty($les['videos'])): ?>
                                            <div class="space-y-2">
                                                <p class="text-[10px] font-semibold uppercase tracking-widest text-[#888888]">Vidéos</p>
                                                <div class="space-y-1.5">
                                                    <?php foreach ($les['videos'] as $vid): ?>
                                                    <div class="flex items-center justify-between gap-3 bg-white border border-[#E5E5E7] px-3 py-2 rounded-sm">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <svg class="w-3.5 h-3.5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                            <span class="text-xs font-medium text-[#111111] flex-shrink-0"><?= htmlspecialchars($vid['label']); ?></span>
                                                            <a href="<?= htmlspecialchars($vid['url']); ?>" target="_blank"
                                                                class="text-[11px] text-[#004B23] underline truncate font-mono">
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
                                                                <svg class="w-3 h-3 text-[#D32F2F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                                <p class="text-[10px] font-semibold uppercase tracking-widest text-[#888888]">Ressources</p>
                                                <div class="space-y-1.5">
                                                    <?php foreach ($les['resources'] as $res): ?>
                                                    <div class="flex items-center justify-between gap-3 bg-white border border-[#E5E5E7] px-3 py-2 rounded-sm">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <svg class="w-3.5 h-3.5 text-[#888888] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                            </svg>
                                                            <span class="text-xs font-medium text-[#111111] flex-shrink-0"><?= htmlspecialchars($res['label']); ?></span>
                                                            <a href="<?= htmlspecialchars($res['url']); ?>" target="_blank"
                                                                class="text-[11px] text-[#004B23] underline truncate font-mono">
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
                                                                <svg class="w-3 h-3 text-[#D32F2F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                                <p class="text-xs text-[#888888] italic">Aucun contenu détaillé disponible.</p>
                                            <?php endif; ?>

                                            <!-- Section Discussions / Q&R -->
                                            <div class="border-t border-[#E5E5E7] pt-4 mt-4 space-y-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-widest text-[#888888]">Espace d'Échange (Q&R)</p>
                                                <div class="space-y-3" id="comments-list-<?= $les['id']; ?>">
                                                    <?php if (empty($les['comments'])): ?>
                                                        <p class="text-xs text-[#888888] italic">Aucune question ou commentaire sur cette leçon.</p>
                                                    <?php else: ?>
                                                        <?php foreach ($les['comments'] as $comm): ?>
                                                            <div class="p-3 bg-white border border-[#E5E5E7] rounded-sm space-y-2 text-xs <?= $comm['is_hidden'] ? 'opacity-60 bg-gray-50' : ''; ?>" id="comment-card-<?= $comm['id']; ?>">
                                                                <div class="flex justify-between items-start">
                                                                    <div>
                                                                        <strong class="text-[#004B23]"><?= htmlspecialchars($comm['author_name']); ?></strong>
                                                                        <span class="text-[#888888]">(<?= htmlspecialchars($comm['author_role']); ?>)</span>
                                                                        <span class="text-[10px] text-[#888888] ml-2"><?= $comm['created_at']; ?></span>
                                                                        <?php if ($comm['is_hidden']): ?>
                                                                            <span class="text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 ml-2 font-semibold rounded-sm">Masqué</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="flex gap-2">
                                                                        <!-- Masquer / Afficher -->
                                                                        <button onclick="moderateComment(<?= $comm['id']; ?>, <?= $comm['is_hidden'] ? 0 : 1; ?>)"
                                                                            class="text-[10px] uppercase font-semibold text-[#555555] hover:underline">
                                                                            <?= $comm['is_hidden'] ? 'Afficher' : 'Masquer'; ?>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <p class="text-[#333333] font-light"><?= htmlspecialchars($comm['comment_text']); ?></p>

                                                                <!-- Réponse de l'enseignant -->
                                                                <div id="reply-container-<?= $comm['id']; ?>">
                                                                    <?php if (!empty($comm['teacher_reply'])): ?>
                                                                        <div class="mt-2 pl-3 border-l-2 border-[#004B23] text-[#333333]">
                                                                            <strong>Votre réponse :</strong> <?= htmlspecialchars($comm['teacher_reply']); ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <!-- Formulaire de réponse -->
                                                                <form onsubmit="submitReply(event, <?= $comm['id']; ?>)" class="mt-2 flex gap-2">
                                                                    <input type="text" placeholder="Répondre à ce message..." required id="reply-input-<?= $comm['id']; ?>"
                                                                        class="flex-1 px-3 py-1 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
                                                                    <button type="submit" class="px-3 py-1 bg-[#111111] text-white text-[10px] font-semibold uppercase tracking-wider hover:bg-[#004B23] rounded-sm">
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

            <!-- 3. TÉLÉÉVALUATIONS (tab-live-eval) -->
            <div id="tab-live-eval" class="tab-content hidden space-y-12 animate-fade-in">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></span>
                        <h3 class="font-serif text-2xl font-light">Téléévaluations Synchrones (Live)</h3>
                    </div>
                </div>

                <!-- Section 1 : Bouton / Formulaire de création de séance -->
                <div class="mb-8 border-b border-[#E5E5E7] pb-6 bg-white p-6 border border-[#E5E5E7] rounded-sm">
                    <button onclick="toggleAccordion('add-live-session-form')" class="px-4 py-2 bg-[#004B23] text-white text-xs font-semibold uppercase tracking-wider rounded-sm hover:bg-[#003d1c] transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Créer une nouvelle séance
                    </button>

                    <form id="add-live-session-form" method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=add_live_session" class="hidden mt-4 p-5 bg-[#F5F5F7] border border-[#E5E5E7] rounded-sm space-y-4">
                        <?= csrfInput(); ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-[#555555] uppercase tracking-wider mb-2">Titre de la Séance</label>
                                <input type="text" name="live_title" required placeholder="Ex: Examen Intra-semestriel" class="w-full px-3 py-2 border border-[#E5E5E7] rounded-sm focus:outline-none focus:border-[#004B23] text-sm bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#555555] uppercase tracking-wider mb-2">Temps par question (secondes)</label>
                                <input type="number" name="default_time_limit" required value="30" min="5" class="w-full px-3 py-2 border border-[#E5E5E7] rounded-sm focus:outline-none focus:border-[#004B23] text-sm bg-white">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-[#555555] uppercase tracking-wider mb-2">Date/Heure de Début</label>
                                <input type="datetime-local" name="live_start_time" required class="w-full px-3 py-2 border border-[#E5E5E7] rounded-sm focus:outline-none focus:border-[#004B23] text-sm bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#555555] uppercase tracking-wider mb-2">Date/Heure de Fin</label>
                                <input type="datetime-local" name="live_end_time" required class="w-full px-3 py-2 border border-[#E5E5E7] rounded-sm focus:outline-none focus:border-[#004B23] text-sm bg-white">
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" onclick="toggleAccordion('add-live-session-form')" class="px-4 py-2 border border-[#E5E5E7] text-xs font-semibold uppercase tracking-wider rounded-sm text-[#555555] hover:bg-gray-50 bg-white">Annuler</button>
                            <button type="submit" class="px-4 py-2 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider rounded-sm hover:bg-black">Enregistrer</button>
                        </div>
                    </form>
                </div>

                <!-- Section 2 : Liste des séances existantes -->
                <h4 class="text-xs font-semibold text-[#555555] uppercase tracking-wider mb-4">Séances configurées</h4>
                
                <?php if (empty($liveSessions)): ?>
                    <div class="p-8 text-center border border-[#E5E5E7] text-[#888888] text-sm bg-white rounded-sm">
                        Aucune séance de téléévaluation configurée pour ce cours.
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($liveSessions as $ls): 
                            $isActive = (int)$ls['status'] === 1;
                            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
                                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                            $proto = $isHttps ? 'https' : 'http';
                            $sessionLink = $proto . "://" . $_SERVER['HTTP_HOST'] . "/live-session.php?code=" . $ls['session_code'];
                        ?>
                            <div class="border border-[#E5E5E7] p-6 rounded-sm bg-white space-y-4 hover:shadow-md transition-shadow">
                                <!-- En-tête de la séance -->
                                <div class="flex flex-wrap justify-between items-start gap-4">
                                    <div>
                                        <h5 class="font-semibold text-lg text-[#111111]"><?= htmlspecialchars($ls['title']) ?></h5>
                                        <div class="text-xs text-[#555555] mt-1 space-x-4">
                                            <span>Début : <strong><?= date('d/m/Y H:i', strtotime($ls['start_time'])) ?></strong></span>
                                            <span>Fin : <strong><?= date('d/m/Y H:i', strtotime($ls['end_time'])) ?></strong></span>
                                            <span>Durée par défaut : <strong><?= $ls['default_time_limit'] ?>s</strong></span>
                                        </div>
                                    </div>
                                    <!-- Statut & Actions de base -->
                                    <div class="flex items-center gap-3">
                                        <?php if ($ls['is_finished']): ?>
                                            <span class="px-3 py-1.5 text-xs font-semibold rounded-full border bg-red-50 border-red-200 text-[#D32F2F] flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#D32F2F]"></span> Terminé
                                            </span>
                                        <?php else: ?>
                                            <!-- Bouton Activation -->
                                            <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=toggle_live_session">
                                                <?= csrfInput(); ?>
                                                <input type="hidden" name="session_id" value="<?= $ls['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $isActive ? 0 : 1 ?>">
                                                <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-full border <?= $isActive ? 'bg-green-50 border-green-200 text-[#004B23]' : 'bg-gray-50 border-gray-200 text-[#555555]' ?>">
                                                    <?= $isActive ? '● Activé (ON)' : '○ Désactivé (OFF)' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Bouton Modifier -->
                                        <button type="button" 
                                                data-session="<?= htmlspecialchars(json_encode([
                                                    "id" => $ls["id"],
                                                    "title" => $ls["title"],
                                                    "start_time" => date("Y-m-d\TH:i", strtotime($ls["start_time"])),
                                                    "end_time" => date("Y-m-d\TH:i", strtotime($ls["end_time"])),
                                                    "default_time_limit" => $ls["default_time_limit"],
                                                    "is_async" => $ls["is_async"] ?? 0,
                                                    "async_deadline" => isset($ls["async_deadline"]) && $ls["async_deadline"] ? date("Y-m-d\TH:i", strtotime($ls["async_deadline"])) : ""
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openEditLiveSessionModal(this)"
                                                class="p-1.5 border border-[#E5E5E7] text-[#111111] rounded-sm hover:bg-gray-50" 
                                                title="Modifier la séance">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>

                                        <!-- Bouton Supprimer -->
                                        <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=delete_live_session" onsubmit="return confirm('Supprimer cette séance ? Toutes les questions et réponses seront perdues.');">
                                            <?= csrfInput(); ?>
                                            <input type="hidden" name="session_id" value="<?= $ls['id'] ?>">
                                            <button type="submit" class="p-1.5 border border-red-200 text-red-600 rounded-sm hover:bg-red-50" title="Supprimer la séance">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Lien de partage -->
                                <div class="bg-[#F5F5F7] p-3 rounded-sm flex items-center justify-between border border-[#E5E5E7] text-xs">
                                    <span class="font-mono text-[#555555] overflow-x-auto truncate mr-4"><?= $sessionLink ?></span>
                                    <button onclick="copyToClipboard('<?= $sessionLink ?>')" class="px-3 py-1 bg-white border border-[#E5E5E7] text-[10px] font-semibold uppercase tracking-wider rounded-sm hover:bg-gray-50 transition-colors">Copier</button>
                                </div>

                                <!-- Statistiques & Boutons d'édition -->
                                <div class="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-[#F0F0F2] text-xs text-[#555555]">
                                    <div class="space-x-4 flex flex-wrap items-center gap-y-2">
                                        <span>Questions : <strong class="questions-count-<?= $ls['id'] ?>"><?= $ls['question_count'] ?></strong></span>
                                        <span>Inscrits : <strong class="live-inscrits-count-<?= $ls['id'] ?>"><?= $ls['participant_count'] ?></strong></span>
                                        <span>En ligne : <strong class="live-online-count-<?= $ls['id'] ?> text-green-700 font-semibold"><?= $ls['online_count'] ?></strong></span>
                                        <span class="live-votes-badge-<?= $ls['id'] ?> hidden bg-[#E2ECE9] text-[#004B23] text-[10px] px-2.5 py-0.5 rounded-full font-semibold">
                                            <span class="live-votes-count-<?= $ls['id'] ?>">0</span> réponses reçues
                                        </span>
                                        <span class="live-status-glow-<?= $ls['id'] ?> hidden text-xs font-semibold text-green-700 flex items-center gap-1">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span> En cours
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3">
                                        <?php if ($ls['is_finished']): ?>
                                            <button type="button" onclick="openDispatchModal(<?= $ls['id'] ?>)" class="px-3 py-1.5 bg-[#004B23] text-white text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-[#003d1c] flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                Envoyer les e-mails
                                            </button>
                                        <?php endif; ?>

                                        <a href="/teacher/export-live-grades.php?session_id=<?= $ls['id'] ?>" class="px-3 py-1.5 border border-[#E5E5E7] text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-gray-50 bg-white text-[#111111] flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Rapport Excel
                                        </a>

                                        <a href="/teacher/export-live-pdf.php?session_id=<?= $ls['id'] ?>" class="px-3 py-1.5 border border-[#E5E5E7] text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-gray-50 bg-white text-[#111111] flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            Rapport PDF
                                        </a>

                                        <a href="/teacher/export-live-questions-latex.php?session_id=<?= $ls['id'] ?>&mode=subject" class="px-3 py-1.5 border border-[#E5E5E7] text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-gray-50 bg-white text-[#111111] flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-indigo-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Imprimer Sujet
                                        </a>

                                        <a href="/teacher/export-live-questions-latex.php?session_id=<?= $ls['id'] ?>&mode=correction" class="px-3 py-1.5 border border-[#E5E5E7] text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-gray-50 bg-white text-[#111111] flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-orange-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Imprimer Corrigé
                                        </a>

                                        <?php if (!(isset($ls['is_async']) && (int)$ls['is_async'] === 1)): ?>
                                            <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=toggle_live_pause" class="inline-block">
                                                <?= csrfInput(); ?>
                                                <input type="hidden" name="session_id" value="<?= $ls['id'] ?>">
                                                <?php if ((int)$ls['is_paused'] === 1): ?>
                                                    <button type="submit" class="px-3 py-1.5 bg-[#004B23] text-white text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-[#003d1c] flex items-center gap-1.5" title="Reprendre l'évaluation">
                                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                                                        Reprendre
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="px-3 py-1.5 bg-yellow-600 text-white text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-yellow-700 flex items-center gap-1.5" title="Mettre en pause l'évaluation">
                                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        Pause
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=reset_live_session" onsubmit="return confirm('Réinitialiser la séance ? TOUS les étudiants inscrits et leurs notes/réponses seront définitivement supprimés.');" class="inline-block">
                                            <?= csrfInput(); ?>
                                            <input type="hidden" name="session_id" value="<?= $ls['id'] ?>">
                                            <button type="submit" class="px-3 py-1.5 border border-red-200 text-red-600 text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-red-50 bg-white flex items-center gap-1.5" title="Réinitialiser l'examen (Reset)">
                                                <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89M9 11l3-3 3 3m-3-3v12"/></svg>
                                                Réinitialiser
                                            </button>
                                        </form>

                                        <button onclick="toggleAccordion('live-session-questions-<?= $ls['id'] ?>')" class="px-3 py-1.5 bg-[#111111] text-white text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-black">
                                            Gérer les questions
                                        </button>
                                        <button onclick="toggleAccordion('live-session-results-<?= $ls['id'] ?>')" class="px-3 py-1.5 bg-[#004B23] text-white text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-[#003d1c]">
                                            Résultats &amp; Inscrits
                                        </button>
                                    </div>
                                </div>

                                <!-- Accordéon : Gestion des questions de la séance -->
                                <div id="live-session-questions-<?= $ls['id'] ?>" class="hidden border-t border-[#E5E5E7] pt-4 space-y-6">
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 bg-[#F8F9FA] p-5 border border-[#E5E5E7] rounded-sm">
                                        <!-- Formulaire Ajout Question -->
                                        <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=add_live_question" enctype="multipart/form-data" class="space-y-4 bg-white p-5 border border-[#E5E5E7] rounded-sm shadow-sm">
                                            <?= csrfInput(); ?>
                                            <input type="hidden" name="session_id" value="<?= $ls['id'] ?>">
                                            <div class="flex items-center justify-between border-b border-[#E5E5E7] pb-3 mb-2">
                                                <h6 class="text-xs font-bold text-[#004B23] uppercase tracking-wider">Créer une nouvelle question</h6>
                                                <div class="flex items-center gap-2">
                                                    <label class="text-[10px] text-gray-500 font-semibold uppercase">Type :</label>
                                                    <select name="question_type" onchange="handleQuestionTypeChange(this)" class="px-2 py-1 border border-[#E5E5E7] rounded-sm text-[10px] font-semibold bg-gray-50 text-[#111111] focus:outline-none">
                                                        <option value="mcq">QCM Standard</option>
                                                        <option value="written">Question écrite / Calcul</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1 font-semibold">Énoncé de la question (KaTeX supporté)</label>
                                                <textarea name="question_text" required placeholder="Saisir la question... ex: Calculer $f'(x)$ pour $f(x) = x^2$" class="w-full px-3 py-1.5 border border-[#E5E5E7] rounded-sm text-xs bg-white focus:outline-none focus:border-[#004B23] h-14"></textarea>
                                            </div>
                                            <div class="mcq-options-group space-y-3">
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1">Option A</label>
                                                        <input type="text" name="option_a" placeholder="Option A" class="w-full px-3 py-1.5 border border-[#E5E5E7] rounded-sm text-xs bg-white focus:outline-none focus:border-[#004B23]">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1">Option B</label>
                                                        <input type="text" name="option_b" placeholder="Option B" class="w-full px-3 py-1.5 border border-[#E5E5E7] rounded-sm text-xs bg-white focus:outline-none focus:border-[#004B23]">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1">Option C</label>
                                                        <input type="text" name="option_c" placeholder="Option C" class="w-full px-3 py-1.5 border border-[#E5E5E7] rounded-sm text-xs bg-white focus:outline-none focus:border-[#004B23]">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1">Option D</label>
                                                        <input type="text" name="option_d" placeholder="Option D" class="w-full px-3 py-1.5 border border-[#E5E5E7] rounded-sm text-xs bg-white focus:outline-none focus:border-[#004B23]">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div class="correct-option-container">
                                                    <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1">Option Correcte</label>
                                                    <select name="correct_option" required class="w-full px-2 py-1.5 border border-[#E5E5E7] rounded-sm text-xs bg-white focus:outline-none focus:border-[#004B23]">
                                                        <option value="A">A</option>
                                                        <option value="B">B</option>
                                                        <option value="C">C</option>
                                                        <option value="D">D</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1">Durée spécifique (secondes)</label>
                                                    <input type="number" name="time_limit" placeholder="Vide = par défaut" class="w-full px-2 py-1.5 border border-[#E5E5E7] rounded-sm text-xs bg-white focus:outline-none focus:border-[#004B23]">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1 font-semibold">Explication / Justification (pour correction)</label>
                                                <textarea name="explanation" placeholder="Saisir la justification de la réponse..." class="w-full px-3 py-1.5 border border-[#E5E5E7] rounded-sm text-xs bg-white focus:outline-none focus:border-[#004B23] h-12"></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1">Image d'illustration (facultative)</label>
                                                <input type="file" name="live_image" accept="image/*" class="w-full text-xs file:mr-3 file:py-1 file:px-2 file:border-0 file:bg-[#E5E5E7] file:text-[10px] file:font-semibold">
                                            </div>
                                            <button type="submit" class="w-full py-2 bg-[#004B23] text-white text-[11px] font-semibold uppercase tracking-wider rounded-sm hover:bg-[#003d1c] shadow-sm transition-colors">Ajouter la question</button>
                                        </form>

                                        <!-- Importer des questions -->
                                        <div class="space-y-5 border-l border-gray-200 pl-6 flex flex-col justify-between bg-white p-5 border border-[#E5E5E7] rounded-sm shadow-sm">
                                            <div class="space-y-2">
                                                <h6 class="text-xs font-bold text-[#004B23] uppercase tracking-wider border-b border-[#E5E5E7] pb-3 mb-2">Import en masse (CSV / Excel)</h6>
                                                <p class="text-[10px] text-[#888888] leading-relaxed">
                                                    Téléversez un fichier CSV ou Excel pour charger les questions de la séance en bloc.
                                                    Les colonnes requises sont : <code class="bg-gray-100 px-1 py-0.5 font-mono text-[9px]">question, type, option_a, option_b, option_c, option_d, correct, explanation</code>.
                                                </p>
                                                <p class="text-[9px] text-gray-500 italic">
                                                    Pour le type : saisissez <strong>mcq</strong> (pour QCM) ou <strong>written</strong> (pour calculs/questions ouvertes).
                                                </p>
                                            </div>
                                            <div class="p-4 border border-dashed border-[#E5E5E7] rounded-sm bg-gray-50 text-center">
                                                <input type="file" accept=".csv,.xlsx,.xls,.txt" onchange="importLiveQuestionsFile(this, <?= $ls['id'] ?>)" class="w-full text-xs file:mr-3 file:py-1.5 file:px-3 file:border-0 file:bg-white file:border file:border-[#E5E5E7] file:text-[10px] file:font-semibold rounded-sm">
                                            </div>
                                            <p class="text-[9px] text-[#888888] italic">Note: L'importation s'effectue instantanément après le choix du fichier.</p>
                                        </div>
                                    </div>

                                    <!-- Liste des questions existantes de la séance -->
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center mb-2">
                                            <h6 class="text-xs font-semibold text-[#111111] uppercase tracking-wider">Questions de la séance (<?= count($ls['questions']) ?>)</h6>
                                            <?php if (!empty($ls['questions'])): ?>
                                                <a href="/teacher/download-async-csv.php?type=live&id=<?= $ls['id'] ?>" class="text-[10px] text-[#004B23] font-semibold hover:underline bg-[#EAF2EC] px-2 py-1 rounded-sm">Exporter en CSV</a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (empty($ls['questions'])): ?>
                                            <p class="text-xs text-[#888888] italic">Aucune question pour le moment.</p>
                                        <?php else: ?>
                                            <div class="divide-y divide-[#E5E5E7]">
                                                <?php foreach ($ls['questions'] as $qIdx => $q): 
                                                    $qType = $q['question_type'] ?? 'mcq';
                                                ?>
                                                    <div class="py-4 flex justify-between items-start gap-4">
                                                        <div class="space-y-1 text-xs">
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <span class="font-bold text-gray-400">Q<?= $qIdx + 1 ?>.</span>
                                                                <span class="font-medium text-[#111111]"><?= htmlspecialchars($q['question_text']) ?></span>
                                                                <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded-sm <?= $qType === 'written' ? 'bg-orange-50 border border-orange-200 text-orange-700' : 'bg-blue-50 border border-blue-200 text-blue-700' ?>">
                                                                    <?= $qType === 'written' ? 'Calcul / Écrite' : 'QCM' ?>
                                                                </span>
                                                            </div>
                                                            <?php if ($qType === 'mcq'): ?>
                                                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 text-[11px] text-[#555555] mt-1">
                                                                    <span>A: <?= htmlspecialchars($q['option_a']) ?></span>
                                                                    <span>B: <?= htmlspecialchars($q['option_b']) ?></span>
                                                                    <span>C: <?= htmlspecialchars($q['option_c']) ?></span>
                                                                    <span>D: <?= htmlspecialchars($q['option_d']) ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="text-[10px] text-[#888888] flex flex-wrap gap-x-4 gap-y-1 pt-1">
                                                                <span>Bonne réponse : <strong class="text-[#004B23]"><?= htmlspecialchars($q['correct_option']) ?></strong></span>
                                                                <?php if ($q['time_limit']): ?>
                                                                    <span>Durée : <strong><?= $q['time_limit'] ?>s</strong></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($q['explanation'])): ?>
                                                                    <span>Justification : <em class="text-gray-600"><?= htmlspecialchars($q['explanation']) ?></em></span>
                                                                <?php endif; ?>
                                                                <?php if ($q['image_path']): ?>
                                                                    <a href="/uploads/live_questions/<?= $q['image_path'] ?>" target="_blank" class="text-blue-600 hover:underline">✓ Image d'illustration</a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=delete_live_question" onsubmit="return confirm('Supprimer cette question ?');">
                                                            <?= csrfInput(); ?>
                                                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                                            <button type="submit" class="text-red-500 hover:text-red-700 p-1">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="pt-4 border-t border-[#E5E5E7] flex justify-end">
                                                <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=delete_all_live_questions" onsubmit="return confirm('Êtes-vous absolument sûr de vouloir supprimer TOUTES les questions de cette séance ? Cette action est irréversible.');">
                                                    <?= csrfInput(); ?>
                                                    <input type="hidden" name="session_id" value="<?= $ls['id'] ?>">
                                                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-[11px] font-semibold uppercase tracking-wider rounded-sm transition-colors flex items-center gap-2">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        Supprimer toutes les questions
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Accordéon : Résultats & Inscrits -->
                                <div id="live-session-results-<?= $ls['id'] ?>" class="hidden border-t border-[#E5E5E7] pt-4 space-y-4">
                                    <h4 class="font-serif text-sm font-semibold text-[#111111] uppercase tracking-wider">Participants inscrits &amp; Résultats</h4>
                                    <?php
                                    $partStmt = $pdo->prepare("
                                        SELECT id, name, email, score, last_activity, registered_at
                                        FROM live_eval_registrations
                                        WHERE session_id = :sid
                                        ORDER BY registered_at DESC
                                    ");
                                    $partStmt->execute(['sid' => $ls['id']]);
                                    $participants = $partStmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php if (empty($participants)): ?>
                                        <p class="text-xs text-[#888888] italic p-4 text-center bg-gray-50 border border-[#E5E5E7]">Aucun participant inscrit pour le moment.</p>
                                    <?php else: ?>
                                        <div class="overflow-x-auto border border-[#E5E5E7] rounded-sm bg-white">
                                            <table class="w-full text-left text-xs border-collapse">
                                                <thead>
                                                    <tr class="bg-gray-100 border-b border-[#E5E5E7]">
                                                        <th class="p-3 font-semibold text-[#111111]">Nom complet</th>
                                                        <th class="p-3 font-semibold text-[#111111]">E-mail</th>
                                                        <th class="p-3 font-semibold text-[#111111] text-center">Score / Note</th>
                                                        <th class="p-3 font-semibold text-[#111111] text-center">Présence</th>
                                                        <th class="p-3 font-semibold text-[#111111] text-center">Date d'inscription</th>
                                                        <th class="p-3 font-semibold text-[#111111] text-center">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($participants as $p): 
                                                        $isOnline = false;
                                                        if ($p['last_activity']) {
                                                            $isOnline = (time() - strtotime($p['last_activity']) <= 10);
                                                        }
                                                        $scoreVal = $p['score'] !== null ? number_format((float)$p['score'], 2) . '/20' : 'Non complété';
                                                        $scoreClass = $p['score'] !== null ? 'text-green-700 font-semibold' : 'text-gray-500 italic';
                                                    ?>
                                                        <tr class="border-b border-[#E5E5E7] hover:bg-gray-50">
                                                            <td class="p-3 font-medium text-[#111111]"><?= htmlspecialchars($p['name']) ?></td>
                                                            <td class="p-3 text-[#555555]"><?= htmlspecialchars($p['email']) ?></td>
                                                            <td class="p-3 text-center <?= $scoreClass ?>"><?= $scoreVal ?></td>
                                                            <td class="p-3 text-center">
                                                                <?php if ($isOnline): ?>
                                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-50 border border-green-200 text-green-700">
                                                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> En ligne
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-50 border border-gray-200 text-gray-500">
                                                                        Hors ligne
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="p-3 text-center text-gray-500"><?= date('d/m/Y H:i', strtotime($p['registered_at'])) ?></td>
                                                            <td class="p-3 text-center">
                                                                <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=delete_live_participant" onsubmit="return confirm('Exclure ce participant ? Ses réponses et notes pour cette séance seront définitivement perdues.');" class="inline-block">
                                                                    <?= csrfInput(); ?>
                                                                    <input type="hidden" name="registration_id" value="<?= $p['id'] ?>">
                                                                    <input type="hidden" name="session_id" value="<?= $ls['id'] ?>">
                                                                    <button type="submit" class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 rounded-sm">
                                                                        Exclure
                                                                    </button>
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
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>




            <!-- 5. NOTES & SUIVI (tab-grades) -->
            <div id="tab-grades" class="tab-content hidden space-y-12 animate-fade-in">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-serif text-2xl font-light text-[#111111]">Notes, Inscriptions &amp; Certifications</h3>
                </div>

                <!-- Barre d'onglets secondaires (Sub-tabs) -->
                <div class="flex border-b border-[#E5E5E7] mb-6 flex-wrap gap-1">
                    <button onclick="switchGradesSubTab('subtab-students')" id="btn-subtab-students"
                        class="px-4 py-2 border-b-2 font-semibold text-xs uppercase tracking-wider transition-colors border-[#004B23] text-[#004B23]">
                        Élèves Inscrits
                    </button>
                    <button onclick="switchGradesSubTab('subtab-quiz')" id="btn-subtab-quiz"
                        class="px-4 py-2 border-b-2 font-semibold text-xs uppercase tracking-wider transition-colors border-transparent text-[#888888] hover:text-[#111111]">
                        Notes des Quiz
                    </button>
                    <button onclick="switchGradesSubTab('subtab-certs')" id="btn-subtab-certs"
                        class="px-4 py-2 border-b-2 font-semibold text-xs uppercase tracking-wider transition-colors border-transparent text-[#888888] hover:text-[#111111]">
                        Certifications &amp; Code
                    </button>
                </div>

                <!-- Sub-tab 1 : Élèves Inscrits -->
                <div id="subtab-students" class="grades-subtab space-y-4">
                    <h4 class="text-xs font-semibold text-[#555555] uppercase tracking-wider">Liste des étudiants inscrits au cours</h4>
                    <div class="overflow-x-auto border border-[#E5E5E7] rounded-sm bg-white">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-gray-100 border-b border-[#E5E5E7]">
                                    <th class="p-4 font-semibold text-[#111111]">Nom complet</th>
                                    <th class="p-4 font-semibold text-[#111111]">E-mail</th>
                                    <th class="p-4 font-semibold text-[#111111] text-center">Progression générale</th>
                                    <th class="p-4 font-semibold text-[#111111] text-right">Date d'inscription</th>
                                </tr>
                            </thead>
                            <tbody id="registered-students-list-body" class="divide-y divide-[#E5E5E7]">
                                <tr>
                                    <td colspan="4" class="p-4 text-center text-[#888888] italic">Chargement des inscriptions...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sub-tab 2 : Notes des Quiz -->
                <div id="subtab-quiz" class="grades-subtab hidden space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 bg-white p-5 border border-[#E5E5E7] rounded-sm">
                        <div class="flex flex-wrap items-center gap-6 text-xs text-[#555555]" id="lesson-grades-summary">
                            <!-- Rempli en JS -->
                        </div>
                    </div>
                    <div class="border border-[#E5E5E7] rounded-sm divide-y divide-[#E5E5E7] overflow-hidden" id="lesson-grades-accordion-container">
                        <!-- Rempli en JS -->
                    </div>
                </div>

                <!-- Sub-tab 3 : Certifications & Module -->
                <div id="subtab-certs" class="grades-subtab hidden space-y-8">
                    <!-- Tentatives de Certification Globale -->
                    <div class="space-y-4">
                        <h4 class="text-xs font-semibold text-[#555555] uppercase tracking-wider">Tentatives de Certification Globale (QCM Final)</h4>
                        <div class="overflow-x-auto border border-[#E5E5E7] rounded-sm bg-white">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-gray-100 border-b border-[#E5E5E7]">
                                        <th class="p-4 font-semibold text-[#111111]">Nom complet</th>
                                        <th class="p-4 font-semibold text-[#111111]">E-mail</th>
                                        <th class="p-4 font-semibold text-[#111111] text-center">Score obtenu</th>
                                        <th class="p-4 font-semibold text-[#111111] text-center">Statut</th>
                                        <th class="p-4 font-semibold text-[#111111] text-right">Date tentative</th>
                                    </tr>
                                </thead>
                                <tbody id="certifications-course-body" class="divide-y divide-[#E5E5E7]">
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-[#888888] italic">Chargement des certifications globales...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Certificats de Module Émis -->
                    <div class="space-y-4">
                        <h4 class="text-xs font-semibold text-[#555555] uppercase tracking-wider">Certificats de Module Émis (PDFs)</h4>
                        <div class="overflow-x-auto border border-[#E5E5E7] rounded-sm bg-white">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-gray-100 border-b border-[#E5E5E7]">
                                        <th class="p-4 font-semibold text-[#111111]">Nom complet</th>
                                        <th class="p-4 font-semibold text-[#111111]">E-mail</th>
                                        <th class="p-4 font-semibold text-[#111111]">Code de vérification</th>
                                        <th class="p-4 font-semibold text-[#111111]">Date d'émission</th>
                                        <th class="p-4 font-semibold text-[#111111] text-right">Attribution Manuelle</th>
                                    </tr>
                                </thead>
                                <tbody id="certifications-module-body" class="divide-y divide-[#E5E5E7]">
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-[#888888] italic">Chargement des certificats de module...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── QCM Final de Certification ────────────────── -->
                    <div class="border-t border-[#E5E5E7] dark:border-[#2C2C2C] pt-8 space-y-8">
                        <div class="flex justify-between items-start gap-4">
                            <div class="space-y-2">
                                <h3 class="font-serif text-2xl font-light text-[#111111] dark:text-white">Évaluation Finale du Cours</h3>
                                <p class="text-sm font-light text-[#555555] dark:text-[#AAAAAA] max-w-xl">
                                    Ce QCM est accessible uniquement à l'étudiant ayant complété 100% du cours.
                                    Score minimum requis : <span class="font-semibold text-[#004B23] dark:text-[#34C759]">80%</span>.
                                </p>
                                <div class="text-xs font-mono dark:text-[#E8E8E8]">
                                    Questions actuelles :
                                    <span class="<?= $finalExamQuestionCount >= 30 ? 'text-[#004B23] dark:text-[#34C759]' : 'text-[#D32F2F]'; ?> font-semibold">
                                        <?= $finalExamQuestionCount; ?> / 30 minimum
                                    </span>
                                    <?php if ($finalExamQuestionCount < 30): ?>
                                        <span class="text-[#D32F2F] italic block mt-1">Au moins 30 questions requises pour validation.</span>
                                    <?php else: ?>
                                        <span class="text-[#004B23] dark:text-[#34C759] block mt-1">L'évaluation finale est prête.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="toggleModal('course-question-modal')"
                                    class="flex-shrink-0 px-4 py-2 bg-[#111111] dark:bg-[#E8E8E8] text-white dark:text-[#111111] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] dark:hover:bg-[#4ADE80] transition-colors rounded-sm">
                                    + Question Finale
                                </button>
                                <button type="button" onclick="openImportModal('course')"
                                    class="flex-shrink-0 px-4 py-2 border border-[#E5E5E7] dark:border-[#3A3A3A] text-xs font-semibold uppercase tracking-wider hover:border-[#004B23] dark:hover:text-[#4ADE80] rounded-sm dark:text-white">
                                    Importer CSV/Excel
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($finalQuestions)): ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto border border-[#E5E5E7] dark:border-[#2C2C2C] divide-y divide-[#E5E5E7] dark:divide-[#2C2C2C] bg-[#F5F5F7] dark:bg-[#1E1E1E]">
                            <?php foreach ($finalQuestions as $index => $fq): ?>
                            <div class="px-4 py-3 text-sm font-light flex justify-between items-start gap-4">
                                <div class="flex-grow min-w-0">
                                    <div class="font-medium text-[#111111] dark:text-white text-sm">
                                        <?= ($index + 1); ?>. <?= htmlspecialchars($fq['question_text']); ?>
                                    </div>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 mt-2 text-xs text-[#555555] dark:text-[#AAAAAA]">
                                        <div>A. <?= htmlspecialchars($fq['option_a']); ?></div>
                                        <div>B. <?= htmlspecialchars($fq['option_b']); ?></div>
                                        <div>C. <?= htmlspecialchars($fq['option_c']); ?></div>
                                        <div>D. <?= htmlspecialchars($fq['option_d']); ?></div>
                                    </div>
                                    <div class="mt-1.5 text-xs font-mono text-[#004B23] dark:text-[#34C759] font-semibold">
                                        Réponse : <?= htmlspecialchars($fq['correct_option']); ?>
                                    </div>
                                </div>
                                <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" class="flex-shrink-0">
                                    <?= csrfInput(); ?>
                                    <input type="hidden" name="action" value="delete_course_question">
                                    <input type="hidden" name="question_id" value="<?= $fq['id']; ?>">
                                    <button type="submit" class="icon-btn danger" title="Supprimer la question"
                                        onclick="return confirm('Supprimer cette question de certification ?')">
                                        <svg class="w-3 h-3 text-[#D32F2F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

            <!-- 6. COMMUNAUTÉ & Q&R (tab-comments) -->
            <div id="tab-comments" class="tab-content hidden space-y-12 animate-fade-in">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-serif text-2xl font-light text-[#111111]">Questions &amp; Réponses de la Communauté</h3>
                </div>

                <?php if (empty($courseComments)): ?>
                    <div class="p-8 text-center border border-[#E5E5E7] text-[#888888] text-sm bg-white rounded-sm">
                        Aucune question posée pour le moment sur ce cours.
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($courseComments as $com): 
                            $comHidden = (int)$com['is_hidden'] === 1;
                        ?>
                            <div class="border border-[#E5E5E7] p-6 rounded-sm bg-white space-y-4 hover:shadow-md transition-shadow relative">
                                <!-- Badge de statut -->
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-[9px] uppercase font-bold px-2 py-0.5 rounded-sm bg-gray-100 border border-gray-200 text-gray-600">
                                            Leçon : <?= htmlspecialchars($com['lesson_title']) ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] text-gray-400 font-mono"><?= date('d/m/Y H:i', strtotime($com['created_at'])) ?></span>
                                        <?php if ($comHidden): ?>
                                            <span class="px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded-sm bg-red-50 border border-red-200 text-red-700">Masqué</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Corps du commentaire -->
                                <div>
                                    <p class="text-xs font-semibold text-[#111111] mb-1"><?= htmlspecialchars($com['author_name']) ?> <span class="text-[10px] font-normal text-gray-400 font-mono">(<?= htmlspecialchars($com['author_role'] ?? 'student') ?>)</span></p>
                                    <p class="text-xs text-[#555555] font-light leading-relaxed whitespace-pre-line"><?= htmlspecialchars($com['comment_text']) ?></p>
                                </div>

                                <!-- Réponses existantes -->
                                <?php if (!empty($com['teacher_reply'])): ?>
                                    <div class="bg-[#F5F5F7] p-4 rounded-sm border-l-2 border-[#004B23] space-y-1">
                                        <p class="text-[10px] font-bold text-[#004B23] uppercase tracking-wider">Réponse de l'Enseignant</p>
                                        <p class="text-xs text-[#111111] font-light leading-relaxed whitespace-pre-line"><?= htmlspecialchars($com['teacher_reply']) ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Actions Moderation & Réponse -->
                                <div class="pt-4 border-t border-[#F0F0F2] flex flex-wrap gap-3 items-center justify-between">
                                    <div class="flex gap-2">
                                        <button onclick="moderateComment(<?= $com['id'] ?>, <?= $comHidden ? 0 : 1 ?>)" class="px-3 py-1 bg-white border border-[#E5E5E7] text-[10px] font-semibold uppercase tracking-wider rounded-sm hover:bg-gray-50 transition-colors">
                                            <?= $comHidden ? 'Afficher' : 'Masquer' ?>
                                        </button>
                                        <button onclick="toggleAccordion('reply-form-<?= $com['id'] ?>')" class="px-3 py-1 bg-[#111111] text-white text-[10px] font-semibold uppercase tracking-wider rounded-sm hover:bg-[#004B23] transition-colors">
                                            Répondre / Modifier la réponse
                                        </button>
                                    </div>
                                </div>

                                <!-- Formulaire de réponse -->
                                <div id="reply-form-<?= $com['id'] ?>" class="hidden pt-4 border-t border-[#E5E5E7] space-y-3">
                                    <textarea id="reply-text-<?= $com['id'] ?>" rows="3" placeholder="Saisissez votre réponse..." class="w-full px-3 py-2 border border-[#E5E5E7] rounded-sm focus:outline-none focus:border-[#004B23] text-xs bg-white"><?= htmlspecialchars($com['teacher_reply'] ?? '') ?></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button onclick="toggleAccordion('reply-form-<?= $com['id'] ?>')" class="px-3 py-1.5 border border-[#E5E5E7] text-[10px] font-semibold uppercase tracking-wider rounded-sm text-[#555555] bg-white hover:bg-gray-50">Annuler</button>
                                        <button onclick="submitReply(<?= $com['id'] ?>)" class="px-3 py-1.5 bg-[#004B23] text-white text-[10px] font-semibold uppercase tracking-wider rounded-sm hover:bg-[#003d1c]">Enregistrer</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </section>
</main>

<!-- ══════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════ -->

<!-- ── Modal : Partager le cours ───────────────────────────── -->
<div id="share-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-6 overflow-y-auto">
    <div class="bg-white dark:bg-[#1E1E1E] p-8 max-w-md w-full border border-[#E5E5E7] dark:border-[#2C2C2C] space-y-6 modal-inner relative">
        <button type="button" onclick="closeShareModal()" class="absolute top-4 right-4 text-[#888888] hover:text-[#111111] dark:hover:text-white transition-colors" aria-label="Fermer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <h3 class="font-serif text-2xl font-light text-[#111111] dark:text-white">Partager le cours</h3>
        <p class="text-xs text-[#555555] dark:text-[#AAAAAA] font-light">Copiez le lien ci-dessous pour inviter des apprenants ou partager le cours.</p>
        
        <div class="flex items-center gap-2">
            <input type="text" id="share-url-input" readonly
                class="flex-grow px-3 py-2 bg-[#F5F5F7] dark:bg-[#2C2C2E] border border-[#E5E5E7] dark:border-[#2C2C2C] text-xs font-mono focus:outline-none rounded-md text-[#111111] dark:text-white">
            <button id="share-copy-btn" onclick="copyShareLink()"
                class="px-4 py-2 bg-[#004B23] dark:bg-[#34C759] text-white text-xs font-semibold uppercase tracking-wider rounded-md hover:bg-[#006630] dark:hover:bg-[#28a745] transition-colors flex-shrink-0">
                Copier
            </button>
        </div>
    </div>
</div>

<!-- ── Modal : Créer un cours ───────────────────────────── -->
<div id="create-course-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-6 overflow-y-auto">
    <div class="bg-white p-8 max-w-lg w-full border border-[#E5E5E7] space-y-6 modal-inner my-8">
        <h3 class="font-serif text-2xl font-light">Créer un nouveau cours</h3>
        <p class="text-xs text-[#555555] font-light">Le promoteur sera automatiquement informé et pourra réassigner ce cours si nécessaire.</p>
        <?php if (empty($modules)): ?>
            <p class="text-sm text-[#D32F2F]">Aucun module disponible. Demandez au promoteur de créer un module de formation.</p>
            <button type="button" onclick="toggleModal('create-course-modal')" class="sv-btn-ms-outline">Fermer</button>
        <?php else: ?>
        <form action="/teacher/dashboard.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="create_course">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Module parent</label>
                <select name="module_id" required class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
                    <option value="">Choisir un module…</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= $m['id']; ?>"><?= htmlspecialchars($m['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Titre du cours</label>
                <input type="text" name="course_title" required placeholder="ex: Introduction à la logique pure"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Description</label>
                <textarea name="course_desc" rows="3" placeholder="Brève introduction au cours…"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Image de couverture (Optionnelle)</label>
                <input type="file" name="cover_image" accept="image/*"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Clé d'inscription (optionnelle)</label>
                <input type="text" name="enrollment_key" placeholder="ex: CODE2026"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm font-mono">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Début</label>
                    <input type="date" name="start_date" class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Fin</label>
                    <input type="date" name="end_date" class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Deadline éval.</label>
                    <input type="date" name="eval_deadline" class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Durée QCM (min)</label>
                    <input type="number" name="exam_duration_minutes" value="90" min="30" max="180"
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
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
<div id="edit-course-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-6 overflow-y-auto">
    <div class="bg-white p-8 max-w-lg w-full border border-[#E5E5E7] space-y-6 modal-inner my-8">
        <h3 class="font-serif text-2xl font-light">Éditer le cours</h3>
        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="edit_course">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Titre</label>
                <input type="text" name="course_title" id="edit-course-title" required
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Description</label>
                <textarea name="course_description" id="edit-course-description" rows="4"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Image de couverture (Optionnelle)</label>
                <?php if ($selectedCourse && !empty($selectedCourse['cover_image'])): ?>
                    <div class="mb-2 flex items-center gap-3 bg-[#F5F5F7] dark:bg-[#1E1E1E] p-2 rounded border border-[#E5E5E7] dark:border-[#2C2C2C]">
                        <img src="/download.php?type=cover&file=<?= urlencode($selectedCourse['cover_image']); ?>" 
                             class="w-12 h-12 object-cover rounded shadow-sm">
                        <span class="text-xs text-[#555555] dark:text-[#AAAAAA] truncate">Image actuelle : <?= htmlspecialchars($selectedCourse['cover_image']) ?></span>
                    </div>
                <?php endif; ?>
                <input type="file" name="cover_image" accept="image/*"
                    class="w-full px-4 py-2 bg-[#F5F5F7] dark:bg-[#1E1E1E] border border-[#E5E5E7] dark:border-[#2C2C2C] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Clé d'inscription (laisser vide pour accès libre)</label>
                <input type="text" name="enrollment_key" id="edit-course-key"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm font-mono">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Début</label>
                    <input type="date" name="start_date" id="edit-course-start"
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Fin</label>
                    <input type="date" name="end_date" id="edit-course-end"
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Deadline éval.</label>
                    <input type="date" name="eval_deadline" id="edit-course-eval"
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Durée QCM (min)</label>
                    <input type="number" name="exam_duration_minutes" id="edit-course-exam" min="30" max="180"
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
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
<div id="chapter-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-6">
    <div class="bg-white p-8 max-w-md w-full border border-[#E5E5E7] space-y-6">
        <h3 class="font-serif text-2xl font-light">Nouveau Chapitre</h3>
        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_chapter">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Titre du Chapitre</label>
                <input type="text" name="chapter_title" required placeholder="ex: Chapitre III — Modélisation formelle"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
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
<div id="edit-chapter-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-6">
    <div class="bg-white p-8 max-w-md w-full border border-[#E5E5E7] space-y-6">
        <h3 class="font-serif text-2xl font-light">Modifier le Chapitre</h3>
        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_chapter">
            <input type="hidden" name="chapter_id" id="edit-chapter-id">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Titre du Chapitre</label>
                <input type="text" name="chapter_title" id="edit-chapter-title" required
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
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
<div id="lesson-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-6">
    <div class="bg-white p-8 max-w-2xl w-full border border-[#E5E5E7] modal-inner">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-serif text-2xl font-light" id="lesson-modal-title">Nouvelle Leçon</h3>
            <button type="button" onclick="toggleModal('lesson-modal')" class="text-[#888888] hover:text-[#D32F2F]">
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
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Titre de la leçon *</label>
                <input type="text" name="lesson_title" id="lesson-title-input" required
                    placeholder="ex: 1. Les théorèmes de Gödel"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
            </div>

            <!-- Type de contenu -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Type de support</label>
                <select name="content_type" id="content_type" onchange="toggleContentFields(this.value)"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
                    <option value="text">Texte riche</option>
                    <option value="pdf">Document PDF</option>
                    <option value="video">Vidéo(s)</option>
                    <option value="mixed">Mixte (texte + PDF + vidéo)</option>
                </select>
            </div>

            <!-- Date Limite d'Évaluation (Quiz) -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Date limite d'évaluation / quiz (optionnelle)</label>
                <input type="datetime-local" name="quiz_deadline" id="lesson-quiz-deadline-input"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
            </div>

            <!-- Contenu textuel -->
            <div id="field-text" class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555]">Contenu textuel</label>
                <textarea name="text_content" id="lesson-text-input" rows="5"
                    placeholder="Saisissez le contenu de votre leçon..."
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm"></textarea>
            </div>

            <!-- PDF — Upload / Remplacement / Suppression -->
            <div id="field-pdf" class="hidden">
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-3">Document PDF</label>

                <!-- PDF existant (affiché en mode édition uniquement) -->
                <div id="existing-pdf-block" class="hidden mb-3">
                    <div class="flex items-center gap-3 px-4 py-3 bg-[#F5F5F7] border border-[#E5E5E7] rounded-sm">
                        <!-- Icône PDF -->
                        <svg class="w-8 h-8 text-[#D32F2F] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <div class="flex-grow min-w-0">
                            <p class="text-xs font-semibold text-[#111111] truncate" id="existing-pdf-name">—</p>
                            <p class="text-[10px] text-[#888888] mt-0.5">PDF actuel de cette leçon</p>
                        </div>
                        <a id="existing-pdf-preview-link" href="#" target="_blank"
                            class="flex-shrink-0 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider border border-[#004B23] text-[#004B23] hover:bg-[#004B23] hover:text-white transition-colors rounded-sm">
                            Aperçu
                        </a>
                        <button type="button" id="btn-delete-pdf"
                            onclick="togglePdfDelete()"
                            class="flex-shrink-0 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider border border-[#D32F2F] text-[#D32F2F] hover:bg-[#D32F2F] hover:text-white transition-colors rounded-sm">
                            Supprimer
                        </button>
                    </div>
                    <!-- Alerte de confirmation suppression -->
                    <div id="pdf-delete-confirm" class="hidden mt-2 px-4 py-3 bg-[#fff2f2] border border-[#D32F2F] rounded-sm">
                        <p class="text-xs text-[#D32F2F] font-semibold mb-2">Attention : Ce PDF sera supprimé définitivement à la sauvegarde.</p>
                        <input type="hidden" name="delete_pdf" id="delete-pdf-flag" value="0">
                        <button type="button" onclick="cancelPdfDelete()" class="text-[10px] font-semibold text-[#555555] hover:underline">Annuler</button>
                    </div>
                </div>

                <!-- Zone d'upload nouveau PDF -->
                <div id="pdf-upload-zone"
                    class="relative border-2 border-dashed border-[#E5E5E7] rounded-sm hover:border-[#004B23] transition-colors cursor-pointer"
                    onclick="document.getElementById('lesson-pdf-input').click()"
                    ondragover="event.preventDefault(); this.classList.add('border-[#004B23]')"
                    ondragleave="this.classList.remove('border-[#004B23]')"
                    ondrop="handlePdfDrop(event)">
                    <input type="file" name="lesson_pdf" id="lesson-pdf-input" accept="application/pdf"
                        class="sr-only" onchange="handlePdfSelect(this)">
                    <div id="pdf-drop-placeholder" class="flex flex-col items-center justify-center gap-2 py-6 px-4 text-center">
                        <svg class="w-8 h-8 text-[#AAAAAA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-xs font-semibold text-[#555555]">Glisser-déposer un PDF ici</p>
                        <p class="text-[10px] text-[#888888]">ou <span class="text-[#004B23] font-semibold underline">cliquer pour parcourir</span> — max 20 Mo</p>
                    </div>
                    <div id="pdf-selected-preview" class="hidden flex items-center gap-3 px-4 py-4">
                        <svg class="w-7 h-7 text-[#D32F2F] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <div class="flex-grow min-w-0">
                            <p class="text-xs font-semibold text-[#111111] truncate" id="pdf-selected-name"></p>
                            <p class="text-[10px] text-[#004B23] font-semibold mt-0.5">✓ Prêt à être uploadé</p>
                        </div>
                        <button type="button" onclick="clearPdfSelection(event)"
                            class="flex-shrink-0 w-6 h-6 rounded-full bg-[#E5E5E7] hover:bg-[#D32F2F] hover:text-white flex items-center justify-center transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <p class="text-[10px] text-[#888888] mt-1.5">Laissez vide pour conserver le PDF existant.</p>
            </div>

            <!-- ── Bloc Vidéos multiples ─────────────────── -->
            <div id="field-video" class="hidden space-y-3">
                <div class="flex justify-between items-center">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555]">Vidéos (YouTube / Vimeo)</label>
                    <button type="button" onclick="addVideoRow()"
                        class="text-[11px] text-[#004B23] font-semibold hover:underline">+ Ajouter une vidéo</button>
                </div>
                <div id="video-rows" class="space-y-2"></div>
            </div>

            <!-- ── Bloc Ressources complémentaires ────────── -->
            <div class="border-t border-[#E5E5E7] pt-4 space-y-3">
                <div class="flex justify-between items-center">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555]">Ressources complémentaires</label>
                    <button type="button" onclick="addResourceRow()"
                        class="text-[11px] text-[#004B23] font-semibold hover:underline">+ Ajouter une ressource</button>
                </div>
                <p class="text-[10px] text-[#888888]">Liens externes, articles de référence, dépôts GitHub, slides, etc.</p>
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

<!-- ── Modal : Génération de Quiz IA (Gemini) ───────────────────────── -->
<div id="ai-quiz-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-6">
    <div class="bg-white p-8 max-w-2xl w-full border border-[#E5E5E7] flex flex-col max-h-[85vh] modal-inner">
        <div class="flex justify-between items-center mb-4 flex-shrink-0">
            <div>
                <h3 class="font-serif text-2xl font-light">Génération de Quiz IA</h3>
                <p id="ai-quiz-lesson-title" class="text-xs font-light text-[#888888] mt-1">Leçon : ...</p>
            </div>
            <button type="button" onclick="toggleModal('ai-quiz-modal')" class="text-[#888888] hover:text-[#D32F2F]">
                ✕
            </button>
        </div>

        <!-- Zone de chargement -->
        <div id="ai-quiz-loading" class="flex-grow flex flex-col items-center justify-center py-12 space-y-3">
            <div class="w-8 h-8 border-2 border-[#111111] border-t-transparent rounded-full animate-spin"></div>
            <p class="text-xs font-mono uppercase tracking-widest text-[#555555]">Génération en cours par l'IA...</p>
        </div>

        <!-- Zone d'affichage des questions générées -->
        <div id="ai-quiz-content" class="hidden flex-grow overflow-y-auto space-y-6 my-4 pr-2 text-sm">
            <p class="text-xs text-[#555555] font-light">
                Voici les questions générées à partir du texte de votre leçon. Vous pouvez relire, modifier ou décocher celles que vous ne souhaitez pas ajouter.
            </p>
            <div id="ai-quiz-questions-list" class="space-y-6"></div>
        </div>

        <div id="ai-quiz-footer" class="hidden flex justify-end gap-3 pt-4 border-t border-[#E5E5E7] flex-shrink-0">
            <button type="button" onclick="toggleModal('ai-quiz-modal')" class="sv-btn-ms-outline">Annuler</button>
            <button type="button" onclick="submitAiQuestions()" class="sv-btn-ms">Enregistrer les questions</button>
        </div>
    </div>
</div>

<!-- ── Modal : Question de leçon ───────────────────────── -->
<div id="question-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-6">
    <div class="bg-white p-8 max-w-lg w-full border border-[#E5E5E7] space-y-5 modal-inner">
        <h3 class="font-serif text-2xl font-light">Question d'Évaluation</h3>
        <p id="lesson-question-subtitle" class="text-xs font-light text-[#888888]"></p>
        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_lesson_question">
            <input type="hidden" id="question-lesson-id" name="lesson_id">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Libellé de la question *</label>
                <textarea name="question_text" required rows="2" placeholder="Posez la question..."
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <?php foreach (['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $k=>$v): ?>
                <div>
                    <label class="block text-xs font-semibold uppercase text-[#555555] mb-1">Option <?= $v; ?></label>
                    <input type="text" name="option_<?= $k; ?>" required
                        class="w-full px-3 py-1.5 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
                </div>
                <?php endforeach; ?>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Bonne réponse</label>
                <select name="correct_option" required
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
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
            <div class="border-t border-[#E5E5E7] pt-4 mt-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Import en masse</p>
                <p class="text-[10px] text-[#888888] mb-3">CSV ou Excel — colonnes : question, option_a…d, correct (A-D)</p>
                <button type="button" onclick="openImportModal('lesson')"
                    class="text-xs text-[#004B23] font-semibold hover:underline">Importer depuis un fichier →</button>
                <a href="/teacher/sample-questions.csv" download class="text-xs text-[#888] ml-3 hover:underline">Modèle CSV</a>
                <a href="#" onclick="event.preventDefault(); downloadCurrentQuestions('lesson')" class="text-xs text-[#004B23] ml-3 hover:underline">Télécharger les questions (.csv)</a>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal : Question de certification ───────────────── -->
<div id="course-question-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-6">
    <div class="bg-white p-8 max-w-lg w-full border border-[#E5E5E7] space-y-5 modal-inner">
        <h3 class="font-serif text-2xl font-light">Question de Certification</h3>
        <p class="text-xs font-light text-[#888888]">Score minimum requis : 80%. Ajoutez au moins 30 questions.</p>
        <form action="/teacher/dashboard.php?course_id=<?= $selectedCourseId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_course_question">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Libellé de la question *</label>
                <textarea name="question_text" required rows="2"
                    placeholder="ex: Quelle est l'impasse résolue par Alan Turing ?"
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <?php foreach (['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $k=>$v): ?>
                <div>
                    <label class="block text-xs font-semibold uppercase text-[#555555] mb-1">Option <?= $v; ?></label>
                    <input type="text" name="option_<?= $k; ?>" required
                        class="w-full px-3 py-1.5 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
                </div>
                <?php endforeach; ?>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Bonne réponse</label>
                <select name="correct_option" required
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
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
            <div class="border-t border-[#E5E5E7] pt-4 mt-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Import en masse</p>
                <button type="button" onclick="openImportModal('course')"
                    class="text-xs text-[#004B23] font-semibold hover:underline">Importer depuis un fichier →</button>
                <a href="/teacher/sample-questions.csv" download class="text-xs text-[#888] ml-3 hover:underline">Modèle CSV</a>
                <a href="#" onclick="event.preventDefault(); downloadCurrentQuestions('course')" class="text-xs text-[#004B23] ml-3 hover:underline">Télécharger les questions (.csv)</a>
            </div>
        </form>
    </div>
</div>



<!-- ── Modal : Éditer une séance de téléévaluation ────────── -->
<div id="edit-live-session-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-[60] flex items-center justify-center p-6">
    <div class="bg-white p-8 max-w-lg w-full border border-[#E5E5E7] modal-inner">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-serif text-2xl font-light">Modifier la Séance</h3>
            <button type="button" onclick="toggleModal('edit-live-session-modal')" class="text-[#888888] hover:text-[#D32F2F]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="/teacher/dashboard.php?course_id=<?= $selectedCourse['id'] ?>&action=edit_live_session" class="space-y-4">
            <input type="hidden" name="session_id" id="edit-live-session-id">
            
            <div>
                <label class="block text-xs font-semibold text-[#555555] uppercase tracking-wider mb-2">Titre de la Séance</label>
                <input type="text" name="live_title" id="edit-live-title" required class="w-full px-3 py-2 border border-[#E5E5E7] rounded-sm focus:outline-none focus:border-[#004B23] text-sm bg-white">
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-[#555555] uppercase tracking-wider mb-2">Temps par question (secondes)</label>
                <input type="number" name="default_time_limit" id="edit-live-limit" required min="5" class="w-full px-3 py-2 border border-[#E5E5E7] rounded-sm focus:outline-none focus:border-[#004B23] text-sm bg-white">
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-[#555555] uppercase tracking-wider mb-2">Date/Heure de Début</label>
                <input type="datetime-local" name="live_start_time" id="edit-live-start-time" required class="w-full px-3 py-2 border border-[#E5E5E7] rounded-sm focus:outline-none focus:border-[#004B23] text-sm bg-white">
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-[#555555] uppercase tracking-wider mb-2">Date/Heure de Fin</label>
                <input type="datetime-local" name="live_end_time" id="edit-live-end-time" required class="w-full px-3 py-2 border border-[#E5E5E7] rounded-sm focus:outline-none focus:border-[#004B23] text-sm bg-white">
            </div>

            <div class="flex items-center gap-2 py-1">
                <input type="checkbox" name="is_async" id="edit-live-is-async" value="1" onchange="toggleAsyncDeadlineField(this.checked)" class="w-4 h-4 text-[#004B23] border-[#E5E5E7] rounded focus:ring-0">
                <label for="edit-live-is-async" class="text-xs font-semibold text-[#555555] uppercase tracking-wider cursor-pointer">Activer le Mode Asynchrone (Devoir Libre)</label>
            </div>
            
            <div id="async-deadline-container" class="hidden">
                <label class="block text-xs font-semibold text-[#555555] uppercase tracking-wider mb-2">Date Limite d'Accès Asynchrone</label>
                <input type="datetime-local" name="async_deadline" id="edit-live-async-deadline" class="w-full px-3 py-2 border border-[#E5E5E7] rounded-sm focus:outline-none focus:border-[#004B23] text-sm bg-white">
            </div>
            
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="toggleModal('edit-live-session-modal')" class="px-4 py-2 border border-[#E5E5E7] text-xs font-semibold uppercase tracking-wider rounded-sm text-[#555555] hover:bg-gray-50 bg-white">Annuler</button>
                <button type="submit" class="px-4 py-2 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider rounded-sm hover:bg-black">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── Modal : Import questions CSV/Excel ─────────────── -->
<div id="import-questions-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-6">
    <div class="bg-white p-8 max-w-md w-full border border-[#E5E5E7] space-y-5 modal-inner">
        <h3 class="font-serif text-2xl font-light">Importer des questions</h3>
        <p id="import-modal-desc" class="text-xs text-[#888888]"></p>
        <div class="space-y-3">
            <input type="file" id="import-questions-file" accept=".csv,.xlsx,.xls,.txt"
                class="w-full text-sm file:mr-3 file:py-2 file:px-4 file:border-0 file:bg-[#F5F5F7] file:text-xs file:font-semibold">
            <p class="text-[10px] text-[#888888]">Format : question, option_a, option_b, option_c, option_d, correct (A-D). Séparateur , ou ;</p>
            <div id="import-result" class="hidden text-xs p-3 border"></div>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" onclick="toggleModal('import-questions-modal')" class="sv-btn-ms-outline">Fermer</button>
            <button type="button" id="import-questions-btn" class="sv-btn-ms">Importer</button>
        </div>
    </div>
</div>

<!-- ── Modal : Dispatch/Envoyer les résultats par e-mail ────────── -->
<div id="dispatch-emails-modal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-[60] flex items-center justify-center p-6">
    <div class="bg-white p-8 max-w-2xl w-full border border-[#E5E5E7] modal-inner flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-serif text-2xl font-light">Envoyer les e-mails de résultats</h3>
            <button type="button" onclick="toggleModal('dispatch-emails-modal')" class="text-[#888888] hover:text-[#D32F2F]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="text-xs text-[#555555] mb-4">
            Destinataires de la séance : <strong id="dispatch-session-title">--</strong>
        </div>

        <!-- Zone de chargement / Tableau des drafts -->
        <div class="flex-1 overflow-y-auto min-h-[250px] border border-[#E5E5E7] p-2 bg-[#F9F9FA]">
            <div id="dispatch-loading" class="flex flex-col items-center justify-center h-full py-8 space-y-2">
                <span class="inline-block w-6 h-6 rounded-full border-2 border-t-transparent border-[#004B23] animate-spin"></span>
                <span class="text-xs text-[#888888]">Chargement du brouillon des résultats...</span>
            </div>
            
            <table id="dispatch-table" class="hidden w-full text-xs text-left border-collapse">
                <thead>
                    <tr class="border-b border-[#E5E5E7] bg-[#F5F5F7] text-[#555555] uppercase tracking-wider font-semibold">
                        <th class="p-3">Étudiant</th>
                        <th class="p-3">Adresse e-mail</th>
                        <th class="p-3 text-center">Note</th>
                        <th class="p-3 text-center">Statut</th>
                    </tr>
                </thead>
                <tbody id="dispatch-tbody" class="divide-y divide-[#E5E5E7]">
                    <!-- Rempli dynamiquement -->
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-center pt-6 mt-4 border-t border-[#E5E5E7]">
            <span id="dispatch-count-info" class="text-xs text-[#555555]">--</span>
            <div class="flex gap-3">
                <button type="button" onclick="toggleModal('dispatch-emails-modal')" class="px-4 py-2 border border-[#E5E5E7] text-xs font-semibold uppercase tracking-wider rounded-sm text-[#555555] hover:bg-gray-50 bg-white">Annuler</button>
                <button id="dispatch-confirm-btn" type="button" onclick="confirmDispatch()" class="px-4 py-2 bg-[#004B23] text-white text-xs font-semibold uppercase tracking-wider rounded-sm hover:bg-[#003d1c] disabled:opacity-50">Approuver et Envoyer</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════════════════ -->
<footer class="border-t border-[#E5E5E7] py-6 px-12 flex justify-between items-center bg-[#F5F5F7] text-xs text-[#888888] font-light">
    <div>StudyVibe Académique — Plateforme Enseignante</div>
    <div>Console Professeur</div>
</footer>

<!-- ══════════════════════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════════════════════ -->
<script>
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
    if (tabId !== 'tab-overview' && !<?= $selectedCourse ? 'true' : 'false' ?>) {
        return; // Don't allow switching if no course is selected
    }
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.add('hidden');
    });
    const target = document.getElementById(tabId);
    if (target) {
        target.classList.remove('hidden');
    }
    document.querySelectorAll('.sidebar-tab-btn').forEach(btn => {
        const targetAttr = btn.getAttribute('data-tab-target');
        const isCourseSelected = <?= $selectedCourse ? 'true' : 'false' ?>;
        
        if (targetAttr === tabId) {
            btn.className = "sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 bg-white/20 text-white font-semibold";
        } else {
            if (targetAttr === 'tab-overview' || isCourseSelected) {
                btn.className = "sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 text-white/70 hover:text-white hover:bg-white/10";
            } else {
                btn.className = "sidebar-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left text-sm transition-all duration-200 opacity-40 cursor-not-allowed text-white/40";
            }
        }
    });
    history.replaceState(null, null, '#' + tabId);
}

function toggleMobileDrawer() {
    const drawer = document.getElementById('mobile-drawer');
    if (drawer) {
        if (drawer.classList.contains('hidden')) {
            drawer.classList.remove('hidden');
            drawer.classList.add('flex');
        } else {
            drawer.classList.add('hidden');
            drawer.classList.remove('flex');
        }
    }
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
                <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1 font-semibold">Réponse correcte (Calcul/Texte)</label>
                <input type="text" name="correct_option" required placeholder="Ex: 24.5, 42, pi..." class="w-full px-3 py-1.5 border border-[#E5E5E7] rounded-sm text-xs bg-white focus:outline-none focus:border-[#004B23]">
            `;
        }
    } else {
        if (mcqOptionsGroup) mcqOptionsGroup.classList.remove('hidden');
        if (correctOptionContainer) {
            correctOptionContainer.innerHTML = `
                <label class="block text-[10px] text-[#555555] uppercase tracking-wider mb-1 font-semibold">Option Correcte</label>
                <select name="correct_option" required class="w-full px-2 py-1.5 border border-[#E5E5E7] rounded-sm text-xs bg-white focus:outline-none focus:border-[#004B23]">
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
                tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-[#888888] italic">Aucun participant inscrit à cette session.</td></tr>`;
                document.getElementById('dispatch-count-info').textContent = 'Aucun destinataire';
                document.getElementById('dispatch-confirm-btn').disabled = true;
            } else {
                let activeCount = 0;
                data.drafts.forEach(student => {
                    const hasAnswered = student.answered_count > 0;
                    if (hasAnswered) activeCount++;
                    
                    const scoreText = `${student.correct_count} / ${student.total_questions}`;
                    const statusText = hasAnswered ? 'Participé' : 'Inscrit uniquement';
                    const statusClass = hasAnswered ? 'text-green-700 font-semibold' : 'text-gray-500 italic';
                    
                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-[#E5E5E7] hover:bg-gray-50';
                    tr.innerHTML = `
                        <td class="p-3 font-medium text-[#111111]">${escapeHtml(student.name)}</td>
                        <td class="p-3 text-[#555555]">${escapeHtml(student.email)}</td>
                        <td class="p-3 text-center font-mono">${scoreText}</td>
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
        countInfo.innerHTML = `<span class="text-[#004B23] font-semibold flex items-center gap-2"><span class="w-3 h-3 rounded-full border-2 border-t-transparent border-[#004B23] animate-spin"></span> Envoi : ${processedCount} / ${totalEmails} ${failedCount > 0 ? `(<span class="text-red-500">${failedCount} échecs</span>)` : ''}</span>`;
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
    countInfo.innerHTML = `<span class="text-[#004B23] font-semibold">Envoi terminé ! (${processedCount}/${totalEmails}) ${failedCount > 0 ? `<span class="text-red-500">[${failedCount} échecs]</span>` : ''}</span>`;
    
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

// Polling temps réel des séances de téléévaluation côté enseignant
function pollLiveStats() {
    const modal = document.getElementById('live-evaluation-modal');
    if (!modal || modal.classList.contains('hidden')) {
        return;
    }
    const params = new URLSearchParams(window.location.search);
    const courseId = params.get('course_id');
    if (!courseId) return;

    fetch('/api/teacher-live-stats.php?course_id=' + courseId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.sessions) {
                data.sessions.forEach(session => {
                    // Mettre à jour les inscrits
                    const inscritsEl = document.querySelector('.live-inscrits-count-' + session.id);
                    if (inscritsEl) {
                        inscritsEl.textContent = session.participant_count;
                    }

                    // Mettre à jour les participants en ligne
                    const onlineEl = document.querySelector('.live-online-count-' + session.id);
                    if (onlineEl) {
                        onlineEl.textContent = session.online_count;
                    }

                    // Mettre à jour les questions
                    const questionsEl = document.querySelector('.questions-count-' + session.id);
                    if (questionsEl) {
                        questionsEl.textContent = session.total_questions;
                    }

                    // Mettre à jour les réponses reçues et l'état en cours
                    const badgeEl = document.querySelector('.live-votes-badge-' + session.id);
                    const votesEl = document.querySelector('.live-votes-count-' + session.id);
                    const glowEl = document.querySelector('.live-status-glow-' + session.id);

                    if (session.status === 'active') {
                        if (glowEl) glowEl.classList.remove('hidden');
                        if (badgeEl) {
                            badgeEl.classList.remove('hidden');
                            if (votesEl) {
                                votesEl.textContent = session.active_question_votes;
                            }
                        }
                    } else {
                        if (glowEl) glowEl.classList.add('hidden');
                        if (badgeEl) badgeEl.classList.add('hidden');
                    }
                });
            }
        })
        .catch(err => console.error("Erreur de synchronisation en direct :", err));
}
setInterval(pollLiveStats, 3000);

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
        btn.style.background = '#006630';
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
    document.getElementById('existing-pdf-info').classList.add('hidden');
    toggleModal('lesson-modal');
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

    // Type de contenu
    const ct = document.getElementById('content_type');
    ct.value = lesson.content_type || 'text';
    toggleContentFields(ct.value);

    // Date limite du quiz
    document.getElementById('lesson-quiz-deadline-input').value = lesson.quiz_deadline ? lesson.quiz_deadline.substring(0, 16).replace(' ', 'T') : '';

    // Texte
    document.getElementById('lesson-text-input').value = lesson.text_content || '';

    // ── PDF existant ──────────────────────────────────────
    const pdfBlock = document.getElementById('existing-pdf-block');
    if (lesson.pdf_path) {
        document.getElementById('existing-pdf-name').textContent = lesson.pdf_path;
        document.getElementById('existing-pdf-preview-link').href = '/uploads/pdfs/' + lesson.pdf_path;
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
    document.getElementById('video-rows').innerHTML    = '';
    document.getElementById('resource-rows').innerHTML = '';
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
    document.getElementById('pdf-upload-zone').classList.remove('border-[#004B23]');
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
            class="w-1/3 px-3 py-1.5 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm flex-shrink-0">
        <input type="url" name="new_video_urls[]" value="${escHtml(defaultUrl)}"
            placeholder="https://www.youtube.com/watch?v=..." required
            class="flex-grow px-3 py-1.5 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm font-mono">
        <button type="button" onclick="this.parentElement.remove()"
            class="icon-btn danger flex-shrink-0" title="Retirer">
            <svg class="w-3 h-3 text-[#D32F2F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            class="px-2 py-1.5 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm flex-shrink-0">
            <option value="link">Lien</option>
            <option value="file">Fichier</option>
            <option value="reference">Référence</option>
        </select>
        <input type="text" name="new_resource_labels[]"
            placeholder="Libellé (ex: Slides du cours)"
            class="w-36 px-3 py-1.5 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm flex-shrink-0">
        <input type="url" name="new_resource_urls[]"
            placeholder="https://..." required
            class="flex-grow px-3 py-1.5 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm font-mono">
        <button type="button" onclick="this.parentElement.remove()"
            class="icon-btn danger flex-shrink-0">
            <svg class="w-3 h-3 text-[#D32F2F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    document.getElementById('resource-rows').appendChild(row);
}

// ── Génération de Quiz IA (Gemini) ──────────────────────
let aiQuizLessonId = null;
let aiGeneratedQuestions = [];

function generateAiQuiz(lessonId, lessonTitle) {
    aiQuizLessonId = lessonId;
    aiGeneratedQuestions = [];
    
    document.getElementById('ai-quiz-lesson-title').textContent = 'Leçon : ' + lessonTitle;
    
    // Configurer l'affichage modal initial
    document.getElementById('ai-quiz-loading').classList.remove('hidden');
    document.getElementById('ai-quiz-content').classList.add('hidden');
    document.getElementById('ai-quiz-footer').classList.add('hidden');
    
    toggleModal('ai-quiz-modal');

    fetch('/api/ai-teacher.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lesson_id: lessonId, action: 'generate' })
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
        item.className = 'p-5 border border-[#E5E5E7] bg-[#FAFAFA] rounded-sm space-y-4';
        item.innerHTML = `
            <div class="flex items-start gap-3">
                <input type="checkbox" id="ai-q-check-${idx}" checked
                    class="mt-1 w-4 h-4 text-[#004B23] border-[#E5E5E7] rounded-sm focus:ring-[#004B23]">
                <div class="flex-grow space-y-3">
                    <div>
                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-[#555555] mb-1">
                            Question ${idx + 1}
                        </label>
                        <input type="text" id="ai-q-text-${idx}" value="${escHtml(q.question)}"
                            class="w-full px-3 py-1.5 bg-white border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-mono uppercase tracking-widest text-[#888888] mb-1">Option A</label>
                            <input type="text" id="ai-q-opt-a-${idx}" value="${escHtml(q.options.A || '')}"
                                class="w-full px-3 py-1.5 bg-white border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
                        </div>
                        <div>
                            <label class="block text-[9px] font-mono uppercase tracking-widest text-[#888888] mb-1">Option B</label>
                            <input type="text" id="ai-q-opt-b-${idx}" value="${escHtml(q.options.B || '')}"
                                class="w-full px-3 py-1.5 bg-white border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
                        </div>
                        <div>
                            <label class="block text-[9px] font-mono uppercase tracking-widest text-[#888888] mb-1">Option C</label>
                            <input type="text" id="ai-q-opt-c-${idx}" value="${escHtml(q.options.C || '')}"
                                class="w-full px-3 py-1.5 bg-white border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
                        </div>
                        <div>
                            <label class="block text-[9px] font-mono uppercase tracking-widest text-[#888888] mb-1">Option D</label>
                            <input type="text" id="ai-q-opt-d-${idx}" value="${escHtml(q.options.D || '')}"
                                class="w-full px-3 py-1.5 bg-white border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-[#555555] mb-1">Option correcte</label>
                        <select id="ai-q-correct-${idx}"
                            class="px-3 py-1.5 bg-white border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
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
            <h4 class="text-sm font-bold text-[#111111] mb-3">${c.title}</h4>
            <div class="grid grid-cols-2 gap-3">
                <div><span class="sv-kpi-value" style="font-size:1.25rem">${c.enrollments}</span><br><span class="sv-kpi-label">Inscrits</span></div>
                <div><span class="sv-kpi-value" style="font-size:1.25rem">${Math.round(c.avg_progress)}%</span><br><span class="sv-kpi-label">Progression</span></div>
                <div><span class="sv-kpi-value" style="font-size:1.25rem">${c.pass_rate}%</span><br><span class="sv-kpi-label">Taux réussite</span></div>
                <div><span class="sv-kpi-value" style="font-size:1.25rem">${Math.round(c.avg_score)}%</span><br><span class="sv-kpi-label">Score moyen</span></div>
            </div>
        </div>
    `).join('') || '<p class="text-sm font-bold text-[#555] p-5">Aucune statistique disponible.</p>';
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
        body.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-[#888888] italic">Aucun élève inscrit à ce cours pour le moment.</td></tr>`;
    } else {
        body.innerHTML = students.map(s => {
            const date = s.enrolled_at ? new Date(s.enrolled_at).toLocaleDateString('fr-FR', {hour: '2-digit', minute:'2-digit'}) : '—';
            const progress = s.progress_percent !== null ? s.progress_percent + '%' : '0%';
            return `<tr>
                <td class="p-3 font-semibold text-[#111111]">${s.student_name}</td>
                <td class="p-3 text-[#555555]">${s.student_email}</td>
                <td class="p-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <div class="w-16 bg-[#E5E5E7] h-1.5 rounded-full overflow-hidden">
                            <div class="bg-[#004B23] h-full" style="width: ${progress}"></div>
                        </div>
                        <span class="font-semibold text-xs text-[#004B23]">${progress}</span>
                    </div>
                </td>
                <td class="p-3 text-right font-mono text-[#888888]">${date}</td>
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
                <svg class="w-12 h-12 text-[#E5E5E7] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm font-semibold text-[#555555]">Aucune leçon avec quiz trouvée</p>
                <p class="text-xs text-[#888888] mt-1">Ajoutez des questions à vos leçons pour voir les évaluations ici.</p>
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
            <span class="w-2 h-2 rounded-full bg-[#004B23] inline-block"></span>
            <span class="font-semibold text-[#111111]">${totalLessons}</span>&nbsp;leçon(s) évaluée(s)
        </div>
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#888888] inline-block"></span>
            <span class="font-semibold text-[#111111]">${totalStudents}</span>&nbsp;apprenant(s) inscrits
        </div>
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#22C55E] inline-block"></span>
            <span class="font-semibold text-[#22C55E]">${totalDone}</span>&nbsp;quiz terminés
        </div>
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#D32F2F] inline-block"></span>
            <span class="font-semibold text-[#D32F2F]">${totalUndone}</span>&nbsp;non terminés
        </div>
        <div class="flex items-center gap-2 ml-auto">
            <span class="text-[#888888]">Score moyen global :</span>
            <span class="font-bold text-[#004B23] text-sm">${avgScore !== '—' ? avgScore + '%' : '—'}</span>
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
        <div class="bg-white">
            <button type="button"
                class="w-full flex items-center justify-between px-8 py-5 text-left hover:bg-[#FAFAFA] transition-colors focus:outline-none"
                onclick="toggleGradeAccordion('${panelId}', this)">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[#E8F5E9] flex items-center justify-center text-[#004B23] font-bold text-xs mt-0.5">
                        ${index + 1}
                    </div>
                    <div>
                        <p class="text-[10px] uppercase font-semibold tracking-wider text-[#888888] mb-0.5">${escHtml(lesson.chapter_title)}</p>
                        <h4 class="font-serif text-lg font-medium text-[#111111]">${escHtml(lesson.lesson_title)}</h4>
                        <div class="flex items-center gap-3 mt-2">
                            <div class="flex items-center gap-1">
                                <div class="w-24 bg-[#E5E5E7] h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-[#004B23] h-full rounded-full transition-all" style="width:${completionPct}%"></div>
                                </div>
                                <span class="text-[10px] font-semibold text-[#004B23]">${completionPct}%</span>
                            </div>
                            <span class="text-[10px] text-[#888888]">${lesson.total_questions} question(s)</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-[#DCFCE7] text-[#15803D]">
                        ✓ ${done} terminé(s)
                    </span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-[#F5F5F7] text-[#888888]">
                        ○ ${undone} non terminé(s)
                    </span>
                    ${avgL !== null ? `<span class="text-xs font-bold text-[#004B23] min-w-[3.5rem] text-right">${avgL}%</span>` : '<span class="text-xs text-[#888888] min-w-[3.5rem] text-right">—</span>'}
                    <svg class="w-4 h-4 text-[#888888] transition-transform duration-200" id="chevron-${panelId}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </button>

            <div id="${panelId}" class="hidden border-t border-[#E5E5E7] bg-[#FAFAFA]">
                <div class="px-8 py-4 flex justify-between items-center">
                    <p class="text-xs text-[#555555]">Résultats pour cette leçon — tous les apprenants inscrits sont listés.</p>
                    <button onclick="exportTableToExcel('${tableId}', 'Notes_${escHtml(lesson.lesson_title).replace(/[^a-zA-Z0-9]/g,'_')}_${index}')" class="px-3 py-1.5 bg-[#004B23] text-white text-[10px] font-semibold uppercase tracking-wider rounded-sm hover:bg-[#111111] transition-colors">
                        ⬇ Exporter
                    </button>
                </div>
                <div class="px-8 pb-6 overflow-x-auto">
                    <table class="w-full text-xs text-left border border-[#E5E5E7] rounded-sm" id="${tableId}">
                        <thead>
                            <tr class="bg-[#F5F5F7] border-b border-[#E5E5E7]">
                                <th class="px-4 py-3 font-semibold uppercase tracking-wider text-[#555555] text-[10px]">Apprenant</th>
                                <th class="px-4 py-3 font-semibold uppercase tracking-wider text-[#555555] text-[10px]">Email</th>
                                <th class="px-4 py-3 font-semibold uppercase tracking-wider text-[#555555] text-[10px] text-center">Note</th>
                                <th class="px-4 py-3 font-semibold uppercase tracking-wider text-[#555555] text-[10px] text-center">Bonnes rép.</th>
                                <th class="px-4 py-3 font-semibold uppercase tracking-wider text-[#555555] text-[10px] text-center">Statut</th>
                                <th class="px-4 py-3 font-semibold uppercase tracking-wider text-[#555555] text-[10px] text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E5E7]">
                            ${lesson.students.map(s => {
                                const done_s    = s.status === 'Terminé';
                                const score     = parseFloat(s.score_percent) || 0;
                                const correct   = parseInt(s.correct_count) || 0;
                                const total_q   = parseInt(s.total_questions) || 0;
                                const date      = s.completed_at ? new Date(s.completed_at).toLocaleDateString('fr-FR', {day:'2-digit',month:'2-digit',year:'numeric'}) : '—';
                                const barColor  = !done_s ? '#E5E5E7' : score >= 70 ? '#004B23' : score >= 40 ? '#F59E0B' : '#D32F2F';
                                const barWidth  = done_s ? score : 0;
                                return `<tr class="hover:bg-[#F9F9F9] transition-colors">
                                    <td class="px-4 py-3 font-semibold text-[#111111] whitespace-nowrap">${escHtml(s.student_name)}</td>
                                    <td class="px-4 py-3 text-[#555555] font-light">${escHtml(s.student_email)}</td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="w-16 bg-[#E5E5E7] h-2 rounded-full overflow-hidden flex-shrink-0">
                                                <div class="h-full rounded-full transition-all" style="width:${barWidth}%;background:${barColor}"></div>
                                            </div>
                                            <span class="font-bold text-xs" style="color:${done_s ? barColor : '#888888'}">${done_s ? score.toFixed(1) + '%' : '0%'}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center font-mono text-[#555555]">${done_s ? correct + ' / ' + total_q : '—'}</td>
                                    <td class="px-4 py-3 text-center">
                                        ${done_s
                                            ? `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-[#DCFCE7] text-[#15803D]">✓ Terminé</span>`
                                            : `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-[#F5F5F7] text-[#888888]">○ Non terminé</span>`
                                        }
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-[#888888]">${date}</td>
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
    if (sessionId) {
        // Ensure the live eval tab is active
        switchDashboardTab('tab-live-eval');

        // Open the specific session accordion if provided
        const accordion = document.getElementById('live-session-questions-' + sessionId);
        if (accordion) {
            accordion.classList.remove('hidden');
            // Scroll into view after a brief delay
            setTimeout(() => accordion.scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
        }

        // Clean up URL without reloading
        const cleanUrl = window.location.pathname + '?course_id=' + params.get('course_id') + '#tab-live-eval';
        window.history.replaceState({}, '', cleanUrl);
    }
})();

function populateCertifications() {
    if (!window.currentCourseGrades) return;
    
    // Remplir tableau QCM final
    const examBody = document.getElementById('certifications-course-body');
    const attempts = window.currentCourseGrades.certification_attempts || [];
    if (!attempts.length) {
        examBody.innerHTML = `<tr><td colspan="5" class="p-4 text-center text-[#888888]">Aucune tentative de certification enregistrée pour le moment.</td></tr>`;
    } else {
        examBody.innerHTML = attempts.map(a => {
            const passed = parseInt(a.passed) === 1;
            const date = a.attempted_at ? new Date(a.attempted_at).toLocaleDateString('fr-FR', {hour: '2-digit', minute:'2-digit'}) : '—';
            return `<tr>
                <td class="p-3 font-semibold text-[#111111]">${a.student_name}</td>
                <td class="p-3 text-[#555555]">${a.student_email}</td>
                <td class="p-3 text-center font-mono font-semibold ${passed ? 'text-[#004B23]' : 'text-[#D32F2F]'}">${parseFloat(a.score).toFixed(1)}%</td>
                <td class="p-3 text-center font-semibold ${passed ? 'text-[#004B23]' : 'text-[#D32F2F]'}">${passed ? '✓ Réussi' : '✕ Échoué'}</td>
                <td class="p-3 text-right font-mono text-[#888888]">${date}</td>
            </tr>`;
        }).join('');
    }

    // Remplir tableau Certifs de module
    const moduleBody = document.getElementById('certifications-module-body');
    const certs = window.currentCourseGrades.module_certificates || [];
    if (!certs.length) {
        moduleBody.innerHTML = `<tr><td colspan="5" class="p-4 text-center text-[#888888]">Aucun certificat de module émis pour le moment.</td></tr>`;
    } else {
        moduleBody.innerHTML = certs.map(c => {
            const date = c.issued_at ? new Date(c.issued_at).toLocaleDateString('fr-FR', {hour: '2-digit', minute:'2-digit'}) : '—';
            return `<tr>
                <td class="p-3 font-semibold text-[#111111]">${c.student_name}</td>
                <td class="p-3 text-[#555555]">${c.student_email}</td>
                <td class="p-3 font-mono text-[#004B23]">${c.certificate_code}</td>
                <td class="p-3 font-mono text-[#888888]">${date}</td>
                <td class="p-3 text-right font-semibold">${parseInt(c.manual_issue) === 1 ? 'Oui' : 'Non'}</td>
            </tr>`;
        }).join('');
    }
}

function switchGradesSubTab(subTabId) {
    document.querySelectorAll('.grades-subtab').forEach(el => {
        el.classList.add('hidden');
    });
    const target = document.getElementById(subTabId);
    if (target) {
        target.classList.remove('hidden');
    }
    
    // Switch buttons
    const btnIds = ['btn-subtab-students', 'btn-subtab-quiz', 'btn-subtab-certs'];
    btnIds.forEach(id => {
        const btn = document.getElementById(id);
        if (!btn) return;
        if (id === 'btn-' + subTabId) {
            btn.className = "px-4 py-2 border-b-2 font-semibold text-xs uppercase tracking-wider transition-colors border-[#004B23] text-[#004B23]";
        } else {
            btn.className = "px-4 py-2 border-b-2 font-semibold text-xs uppercase tracking-wider transition-colors border-transparent text-[#888888] hover:text-[#111111]";
        }
    });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert("Lien copié dans le presse-papiers !");
    }).catch(err => {
        console.error("Erreur lors de la copie :", err);
    });
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
        } else if (c === ',' && !inQuotes) {
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
        let headerMatches = firstRow.filter(cell =>
            typeof cell === 'string' && cell.trim().length <= 30 &&
            knownNames.includes(cell.trim().toLowerCase())
        ).length;
        if (headerMatches >= 2) {
            header = firstRow.map(h => (h || '').toLowerCase().trim());
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
            let qIdx = header.findIndex(h => h.includes('question') || h.includes('libelle') || h.includes('enonce'));
            let aIdx = header.findIndex(h => h === 'a' || h.includes('option_a'));
            let bIdx = header.findIndex(h => h === 'b' || h.includes('option_b'));
            let cIdx = header.findIndex(h => h === 'c' || h.includes('option_c'));
            let dIdx = header.findIndex(h => h === 'd' || h.includes('option_d'));
            let corIdx = header.findIndex(h => h.includes('correct') || h.includes('reponse') || h.includes('bonne'));
            let expIdx = header.findIndex(h => h.includes('explanation') || h.includes('explication') || h.includes('justification'));
            
            if (qIdx !== -1) qText = cols[qIdx] || "";
            if (aIdx !== -1) optA = cols[aIdx] || "";
            if (bIdx !== -1) optB = cols[bIdx] || "";
            if (cIdx !== -1) optC = cols[cIdx] || "";
            if (dIdx !== -1) optD = cols[dIdx] || "";
            if (corIdx !== -1) correct = (cols[corIdx] || "").toUpperCase().trim();
            if (expIdx !== -1) explanation = cols[expIdx] || "";
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
        row.className = "border-b border-gray-100 hover:bg-gray-50/50";
        
        let statusBadge = `<span class="bg-green-100 text-green-800 px-2 py-0.5 rounded font-semibold text-[10px]">Valide</span>`;
        if (q.errors.length > 0) {
            hasAnyWarnings = true;
            statusBadge = `<span class="bg-red-100 text-red-800 px-2 py-0.5 rounded font-semibold text-[10px]" title="${q.errors.join(', ')}">Erreur</span>`;
        }
        
        const escapeHtml = (str) => { var s = String(str||''); s=s.split('&').join('&amp;'); s=s.split('<').join('&lt;'); s=s.split('>').join('&gt;'); return s; };
        
        const qType = q.question_type || 'mcq';
        let optionsHtml = '';
        if (qType === 'mcq') {
            optionsHtml = `
                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-gray-600 mt-1">
                    <div class="math-render"><span class="font-semibold text-gray-400">A:</span> ${escapeHtml(q.option_a)}</div>
                    <div class="math-render"><span class="font-semibold text-gray-400">B:</span> ${escapeHtml(q.option_b)}</div>
                    <div class="math-render"><span class="font-semibold text-gray-400">C:</span> ${escapeHtml(q.option_c)}</div>
                    <div class="math-render"><span class="font-semibold text-gray-400">D:</span> ${escapeHtml(q.option_d)}</div>
                </div>
            `;
        } else {
            optionsHtml = `
                <div class="text-[10px] text-orange-700 font-semibold uppercase tracking-wider mt-1 bg-orange-50 border border-orange-200 px-2 py-0.5 rounded-sm inline-block">
                    Question écrite / Calcul
                </div>
            `;
        }

        row.innerHTML = `
            <td class="p-3 text-center text-gray-400 font-medium">${idx + 1}</td>
            <td class="p-3 space-y-1.5 text-left">
                <div class="font-bold text-gray-900 math-render">${escapeHtml(q.question_text)}</div>
                ${optionsHtml}
                ${q.explanation ? `<div class="text-[11px] text-[#004B23] font-medium mt-1 math-render"><span class="font-semibold text-[#888]">Explication :</span> ${escapeHtml(q.explanation)}</div>` : ''}
                ${q.errors.length > 0 ? `<div class="text-[10px] text-red-600 font-medium mt-1">[Alerte] ${q.errors.join(' | ')}</div>` : ''}
            </td>
            <td class="p-3 text-center font-bold text-[#004B23]">${escapeHtml(q.correct_option)}</td>
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
            ? data.notifications.map(n => `<a href="${n.link || '#'}" class="block p-3 border-b border-[#E5E5E7] hover:bg-[#F5F5F7] ${n.is_read == 0 ? 'font-semibold' : ''}"><div class="text-xs">${n.title}</div><div class="text-[11px] text-[#888]">${n.body || ''}</div></a>`).join('')
            : '<p class="p-3 text-xs text-[#888]">Aucune notification.</p>';
    });
}
document.getElementById('notif-btn')?.addEventListener('click', () => {
    document.getElementById('notif-panel').classList.toggle('hidden');
    loadNotifications();
});
loadNotifications();

// ── Q&R : Réponse de l'enseignant ──────────────────────
function submitReply(event, commentId) {
    event.preventDefault();
    const input = document.getElementById(`reply-input-${commentId}`);
    const replyText = input ? input.value.trim() : '';
    if (!replyText) return;

    const fd = new FormData();
    fd.append('comment_id', commentId);
    fd.append('reply_text', replyText);

    fetch('/teacher/reply-comment.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const container = document.getElementById(`reply-container-${commentId}`);
            if (container) {
                container.innerHTML = `<div class="mt-2 pl-3 border-l-2 border-[#004B23] text-[#333333] text-xs"><strong>Votre réponse :</strong> ${replyText}</div>`;
            }
            if (input) input.value = '';
            if (typeof Toast !== 'undefined') Toast.success('Réponse publiée.');
        } else {
            if (typeof Toast !== 'undefined') Toast.error(data.message || 'Erreur lors de la réponse.');
        }
    })
    .catch(err => { if (typeof Toast !== 'undefined') Toast.error('Erreur réseau: ' + err.message); });
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
                    card.classList.add('opacity-60', 'bg-gray-50');
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
                        badge.className = 'hidden-badge text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 ml-2 font-semibold rounded-sm';
                        badge.textContent = 'Masqué';
                        nameDiv.appendChild(badge);
                    }
                } else {
                    card.classList.remove('opacity-60', 'bg-gray-50');
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
</script>

<!-- Modal d'aperçu et de validation de QCM (CSV / Excel) -->
<div id="csv-preview-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col slide-in">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-t-lg">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Validation et Aperçu du QCM</h3>
                    <p class="text-xs text-gray-500 font-medium">Vérifiez la lisibilité et le rendu de vos formules mathématiques LaTeX avant de valider l'importation.</p>
                </div>
            </div>
            <button onclick="closeCsvPreview()" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>
        
        <!-- Stats and Warnings -->
        <div class="px-6 py-3 bg-blue-50/50 border-b border-blue-100 flex flex-wrap gap-4 items-center justify-between">
            <div class="flex gap-4 text-xs font-semibold text-gray-700">
                <span>Total détecté : <strong id="csv-stat-total" class="text-blue-700">0</strong> questions</span>
                <span>Formules LaTeX validées : <strong id="csv-stat-math" class="text-green-700">0</strong></span>
            </div>
            <div id="csv-warning-badge" class="hidden text-xs bg-amber-100 text-amber-800 px-2.5 py-1 rounded font-medium">
                Avertissements de formatage détectés
            </div>
        </div>

        <!-- Table Content -->
        <div class="flex-1 overflow-y-auto p-6">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b-2 border-gray-200 bg-gray-50 text-gray-600 font-bold uppercase tracking-wider">
                        <th class="p-3 w-12 text-center">N°</th>
                        <th class="p-3">Question &amp; Options (Aperçu Live)</th>
                        <th class="p-3 w-20 text-center">Correct</th>
                        <th class="p-3 w-24 text-center">Statut</th>
                    </tr>
                </thead>
                <tbody id="csv-preview-table-body" class="divide-y divide-gray-100">
                    <!-- Rempli dynamiquement -->
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center rounded-b-lg">
            <button onclick="closeCsvPreview()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded text-xs font-semibold hover:bg-gray-100 transition-colors">
                Annuler
            </button>
            <button id="csv-confirm-btn" class="px-5 py-2 bg-[#004B23] text-white rounded text-xs font-semibold hover:bg-[#003c1c] transition-colors flex items-center gap-2">
                <span>Confirmer l'importation</span>
                <span id="csv-confirm-spinner" class="hidden animate-spin h-3 w-3 border-2 border-white border-t-transparent rounded-full"></span>
            </button>
        </div>
    </div>
</div>
</body>
</html>
