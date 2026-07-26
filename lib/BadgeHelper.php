<?php
/**
 * StudyVibe LMS - Gamification Badge Management Helper
 *
 * Evaluates student performance metrics and awards badges.
 *
 * PHP version 8.2
 */

declare(strict_types=1);

class BadgeHelper
{
    /**
     * Evaluates all badge conditions for a student and awards new badges.
     *
     * @param PDO $pdo Database connection.
     * @param int $studentId Student ID.
     * @return array List of earned badge records.
     */
    public static function evaluateBadges(PDO $pdo, int $studentId): array
    {
        $earnedTypes = [];

        try {
            // 1. first_lesson: Compléter au moins 1 leçon
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = :sid AND completed = 1");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $earnedTypes[] = 'first_lesson';
            }

            // 2. study_hour: Cumuler plus d'1h d'étude (3600 secondes)
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(seconds_spent), 0) FROM study_sessions WHERE student_id = :sid");
            $stmt->execute(['sid' => $studentId]);
            $studySeconds = (int)$stmt->fetchColumn();
            if ($studySeconds >= 3600) {
                $earnedTypes[] = 'study_hour';
            }

            // 3. course_complete: Compléter à 100% au moins un cours
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = :sid AND progress_percent = 100");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $earnedTypes[] = 'course_complete';
            }

            // 4. certified: Obtenir un certificat
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE student_id = :sid");
            $stmt->execute(['sid' => $studentId]);
            $certCount = (int)$stmt->fetchColumn();
            if ($certCount > 0) {
                $earnedTypes[] = 'certified';
            }

            // 5. perfect_score: Obtenir 100% à un quiz ou un examen
            $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = :sid AND score = 100");
            $stmt1->execute(['sid' => $studentId]);
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM certification_attempts WHERE student_id = :sid AND score = 100");
            $stmt2->execute(['sid' => $studentId]);
            if ((int)$stmt1->fetchColumn() > 0 || (int)$stmt2->fetchColumn() > 0) {
                $earnedTypes[] = 'perfect_score';
            }

            // 6. multitasker: S'inscrire à au moins 3 cours
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = :sid");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() >= 3) {
                $earnedTypes[] = 'multitasker';
            }

            // 7. night_owl: Réviser la nuit (entre 22h et 4h)
            $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = :sid AND (HOUR(completed_at) >= 22 OR HOUR(completed_at) < 4)");
            $stmt1->execute(['sid' => $studentId]);
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM certification_attempts WHERE student_id = :sid AND (HOUR(attempted_at) >= 22 OR HOUR(attempted_at) < 4)");
            $stmt2->execute(['sid' => $studentId]);
            if ((int)$stmt1->fetchColumn() > 0 || (int)$stmt2->fetchColumn() > 0) {
                $earnedTypes[] = 'night_owl';
            }

            // 8. note_taker: Prendre des notes de cours
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM video_notes WHERE student_id = :sid");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $earnedTypes[] = 'note_taker';
            }

            // --- 10 NOUVEAUX BADGES DE GAMIFICATION ---

            // 9. speed_demon: 5 leçons complétées le même jour
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = :sid AND DATE(completed_at) = CURRENT_DATE()");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() >= 5) {
                $earnedTypes[] = 'speed_demon';
            }

            // 10. marathoner: Cumuler plus de 10h d'étude (36000 secondes)
            if ($studySeconds >= 36000) {
                $earnedTypes[] = 'marathoner';
            }

            // 11. quiz_master: Réussir 10 quiz de leçons
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT lesson_id) FROM lesson_progress WHERE student_id = :sid AND completed = 1 AND score >= 80");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() >= 10) {
                $earnedTypes[] = 'quiz_master';
            }

            // 12. early_bird: Réviser ou passer un exam tôt le matin (5h-8h)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = :sid AND (HOUR(completed_at) >= 5 AND HOUR(completed_at) < 8)");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $earnedTypes[] = 'early_bird';
            }

            // 13. bibliophile: Inscrit à des cours avec accès aux ressources bibliothèque
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM course_library_items cli
                JOIN enrollments e ON cli.course_id = e.course_id
                WHERE e.student_id = :sid
            ");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() >= 3) {
                $earnedTypes[] = 'bibliophile';
            }

            // 14. assignment_ace: Au moins 1 devoir rendu
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_assignment_submissions WHERE student_id = :sid");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $earnedTypes[] = 'assignment_ace';
            }

            // 15. tele_champion: Participer à une séance de téléévaluation en direct
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_registrations WHERE student_id = :sid");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $earnedTypes[] = 'tele_champion';
            }

            // 16. community_voice: Poster au moins 3 commentaires ou réponses Q&R
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_comments WHERE user_id = :sid");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() >= 3) {
                $earnedTypes[] = 'community_voice';
            }

            // 17. streak_master: Sessions d'étude sur au moins 3 jours distincts
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT DATE(session_date)) FROM study_sessions WHERE student_id = :sid");
            $stmt->execute(['sid' => $studentId]);
            if ((int)$stmt->fetchColumn() >= 3) {
                $earnedTypes[] = 'streak_master';
            }

            // 18. scholar_god: Obtenir 3 certificats officiels
            if ($certCount >= 3) {
                $earnedTypes[] = 'scholar_god';
            }

            // Enregistrer tous les badges gagnés dans la base de données
            if (!empty($earnedTypes)) {
                $insertStmt = $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_type) VALUES (:sid, :type)");
                foreach ($earnedTypes as $type) {
                    $insertStmt->execute(['sid' => $studentId, 'type' => $type]);
                }
            }

            // Retourner les badges obtenus
            $stmt = $pdo->prepare("SELECT badge_type, earned_at FROM student_badges WHERE student_id = :sid ORDER BY earned_at DESC");
            $stmt->execute(['sid' => $studentId]);
            return $stmt->fetchAll();

        } catch (Throwable $e) {
            error_log("[BadgeHelper] Erreur d'évaluation des badges : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Returns full metadata configuration for all 18 gamification badges.
     *
     * @return array
     */
    public static function getAllBadgesConfig(): array
    {
        return [
            'first_lesson' => [
                'title'  => 'Pionnier',
                'desc'   => 'Compléter votre toute première leçon sur la plateforme.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>',
                'color'  => 'from-blue-500 to-indigo-600',
                'border' => 'border-blue-200',
            ],
            'study_hour' => [
                'title'  => 'Apprenant Assidu',
                'desc'   => 'Cumuler plus d\'une heure de temps d\'étude sur StudyVibe.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                'color'  => 'from-amber-500 to-orange-600',
                'border' => 'border-amber-200',
            ],
            'course_complete' => [
                'title'  => 'Finisseur d\'Élite',
                'desc'   => 'Compléter à 100% au moins un cours de votre programme.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0110 21a3.745 3.745 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.746 3.746 0 011.043-3.296a3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" /></svg>',
                'color'  => 'from-emerald-500 to-teal-600',
                'border' => 'border-emerald-200',
            ],
            'certified' => [
                'title'  => 'Diplômé Officiel',
                'desc'   => 'Obtenir votre premier certificat de réussite académique.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84a50.58 50.58 0 00-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" /></svg>',
                'color'  => 'from-purple-500 to-fuchsia-600',
                'border' => 'border-purple-200',
            ],
            'perfect_score' => [
                'title'  => 'Major de Promo',
                'desc'   => 'Obtenir un score parfait de 100% à un quiz de leçon ou examen final.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499c.195-.558.976-.558 1.17 0l2.36 6.816a1 1 0 00.95.69h7.162c.582 0 .822.748.35 1.14l-5.797 4.837a1 1 0 00-.364 1.118l2.36 6.816c.196.558-.432 1.016-.906.69l-5.797-4.837a1 1 0 00-1.17 0l-5.797 4.837c-.474.326-1.102-.132-.906-.69l2.36-6.816a1 1 0 00-.364-1.118L2.05 12.139c-.472-.392-.232-1.14.35-1.14h7.162a1 1 0 00.95-.69l2.36-6.82z" /></svg>',
                'color'  => 'from-yellow-500 to-rose-600',
                'border' => 'border-yellow-200',
            ],
            'multitasker' => [
                'title'  => 'Esprit Polyvalent',
                'desc'   => 'S\'inscrire activement à au moins 3 cours différents.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>',
                'color'  => 'from-cyan-500 to-blue-600',
                'border' => 'border-cyan-200',
            ],
            'night_owl' => [
                'title'  => 'Chouette de Nuit',
                'desc'   => 'Compléter des révisions ou évaluations entre 22h et 4h du matin.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>',
                'color'  => 'from-indigo-600 to-slate-900',
                'border' => 'border-indigo-300',
            ],
            'note_taker' => [
                'title'  => 'Priseur de Notes',
                'desc'   => 'Rédiger et sauvegarder des notes personnelles pendant une vidéo de cours.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>',
                'color'  => 'from-teal-500 to-emerald-700',
                'border' => 'border-teal-200',
            ],
            'speed_demon' => [
                'title'  => 'Foudre de Guerre',
                'desc'   => 'Compléter au moins 5 leçons en une seule journée.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>',
                'color'  => 'from-red-500 to-amber-500',
                'border' => 'border-red-200',
            ],
            'marathoner' => [
                'title'  => 'Marathonien du Savoir',
                'desc'   => 'Franchir la barre des 10 heures de travail cumulées sur StudyVibe.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m-9-6h9.5" /></svg>',
                'color'  => 'from-violet-600 to-purple-800',
                'border' => 'border-violet-300',
            ],
            'quiz_master' => [
                'title'  => 'Maître des Quiz',
                'desc'   => 'Réussir avec succès (>=80%) au moins 10 quiz de leçons différents.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>',
                'color'  => 'from-emerald-600 to-emerald-900',
                'border' => 'border-emerald-300',
            ],
            'early_bird' => [
                'title'  => 'Lève-Tôt Académique',
                'desc'   => 'Compléter une leçon ou un examen tôt le matin (5h-8h).',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>',
                'color'  => 'from-amber-400 to-yellow-600',
                'border' => 'border-amber-300',
            ],
            'bibliophile' => [
                'title'  => 'Bibliophile Érudit',
                'desc'   => 'Accéder aux ressources et documents de la Bibliothèque de Cours.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5M4.5 21V10.5" /></svg>',
                'color'  => 'from-yellow-600 to-amber-800',
                'border' => 'border-yellow-300',
            ],
            'assignment_ace' => [
                'title'  => 'As des Devoirs',
                'desc'   => 'Soumettre un travail pratique d\'évaluation à l\'enseignant.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-12M10.125 2.25h.375a2.625 2.625 0 012.625 2.625v2.625h2.625a2.625 2.625 0 012.625 2.625v.375M10.125 2.25L16.875 9" /></svg>',
                'color'  => 'from-blue-600 to-cyan-800',
                'border' => 'border-blue-300',
            ],
            'tele_champion' => [
                'title'  => 'Champion du Live',
                'desc'   => 'Rejoindre et participer à une séance de téléévaluation synchrone.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-2.36a.75.75 0 011.03.688v6.344a.75.75 0 01-1.03.688l-4.72-2.36M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" /></svg>',
                'color'  => 'from-rose-500 to-pink-700',
                'border' => 'border-rose-300',
            ],
            'community_voice' => [
                'title'  => 'Voix de la Communauté',
                'desc'   => 'Participer aux échanges académiques avec au moins 3 interventions.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-.462 0-.924-.003-1.385-.01a23.682 23.682 0 01-4.218-.466M7.5 14.25a3 3 0 00-3 3v3.091l3-3c.462 0 .924-.003 1.385-.01a23.682 23.682 0 014.218-.466" /></svg>',
                'color'  => 'from-sky-500 to-blue-700',
                'border' => 'border-sky-300',
            ],
            'streak_master' => [
                'title'  => 'Habitude de Fer',
                'desc'   => 'Étudier sur au moins 3 jours distincts sur la plateforme.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" /></svg>',
                'color'  => 'from-orange-500 to-red-700',
                'border' => 'border-orange-300',
            ],
            'scholar_god' => [
                'title'  => 'Érudit Suprême',
                'desc'   => 'Valider avec succès 3 examens et obtenir 3 certificats officiels.',
                'icon'   => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21l-8-4.5v-9L12 3l8 4.5v9l-8 4.5z" /></svg>',
                'color'  => 'from-amber-300 to-yellow-500 text-yellow-950',
                'border' => 'border-amber-400',
            ],
        ];
    }
}
