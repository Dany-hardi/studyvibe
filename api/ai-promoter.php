<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/GeminiClient.php';

// Verifier l'authentification et le role promoteur
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'promoter') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acces refuse. Session promoteur requise.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance();

    // 1. KPI Globaux
    $totalStudents = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $totalCourses  = (int)$db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $totalCerts    = (int)$db->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
    $enrollments   = (int)$db->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();

    // 2. Inscriptions par cours
    $stmt = $db->query("
        SELECT c.title, COUNT(e.id) as student_count 
        FROM courses c 
        LEFT JOIN enrollments e ON c.id = e.course_id 
        GROUP BY c.id
    ");
    $coursesStats = $stmt->fetchAll();

    // Preparer le recapitulatif
    $dataReport = "STATISTIQUES DE L'ETABLISSEMENT :\n"
                . "- Nombre d'etudiants inscrits : {$totalStudents}\n"
                . "- Nombre total de cours : {$totalCourses}\n"
                . "- Nombre d'inscriptions totales : {$enrollments}\n"
                . "- Certificats decernes avec succes : {$totalCerts}\n\n"
                . "REPARTITION DES INSCRIPTIONS PAR COURS :\n";
    
    foreach ($coursesStats as $cs) {
        $dataReport .= "- Cours \"{$cs['title']}\" : {$cs['student_count']} inscriptions.\n";
    }

    $client = new GeminiClient(GEMINI_API_KEY);

    // Prompt systeme strict pour le promoteur (sans emojis, analytique)
    $systemInstruction = "Vous etes un expert en audit educatif et strategie universitaire.\n"
                       . "Votre tache consiste a rediger un rapport strategique et analytique clair pour le promoteur de l'etablissement StudyVibe LMS.\n\n"
                       . "DIRECTIVES STRICTES :\n"
                       . "1. Votre ton doit etre formel, rigoureux, concis et analytique.\n"
                       . "2. N'utilisez aucun emoji ni fioriture decorative. Le rapport doit etre sous forme de texte brut structure.\n"
                       . "3. Articulez le rapport autour des rubriques suivantes :\n"
                       . "   - Diagnostic d'Activite : Analyse generale de la participation et de la reussite.\n"
                       . "   - Analyse de la Repartition : Evaluation de l'attraction des differents cours.\n"
                       . "   - Recommandations Operationalisees : Proposer 3 leviers strategiques concrets d'amelioration de la plateforme.";

    $prompt = "Voici les donnees actuelles de l'etablissement :\n\n" . $dataReport . "\n\nGenerez le rapport d'audit academique.";
    $response = $client->generate($prompt, $systemInstruction, false);

    echo json_encode(['success' => true, 'report' => $response]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
