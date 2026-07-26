<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/GeminiClient.php';

// Verifier l'authentification et le role enseignant
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acces refuse. Session enseignant requise.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Lire l'entree JSON
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'generate';
$lessonId = isset($data['lesson_id']) ? (int)$data['lesson_id'] : 0;
$numQuestions = min(25, max(1, (int)($data['num_questions'] ?? 5)));
$difficulty = trim((string)($data['difficulty_level'] ?? 'Moyen'));

if ($lessonId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de lecon invalide.']);
    exit;
}

try {
    $db = Database::getInstance();

    if ($action === 'save') {
        // Enregistrer les questions selectionnees
        $questions = $data['questions'] ?? [];
        if (empty($questions)) {
            echo json_encode(['success' => false, 'error' => 'Aucune question a enregistrer.']);
            exit;
        }

        $db->beginTransaction();
        $stmt = $db->prepare('INSERT INTO lesson_questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (:lid, :qtext, :oa, :ob, :oc, :od, :correct)');
        
        foreach ($questions as $q) {
            $stmt->execute([
                'lid' => $lessonId,
                'qtext' => $q['question'],
                'oa' => $q['options']['A'] ?? '',
                'ob' => $q['options']['B'] ?? '',
                'oc' => $q['options']['C'] ?? '',
                'od' => $q['options']['D'] ?? '',
                'correct' => strtoupper($q['correct'])
            ]);
        }
        $db->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    // Sinon action === 'generate'
    // Recuperer la lecon
    $stmt = $db->prepare('SELECT title, text_content, pdf_path, video_url, content_type FROM lessons WHERE id = :id');
    $stmt->execute(['id' => $lessonId]);
    $lesson = $stmt->fetch();

    if (!$lesson) {
        echo json_encode(['success' => false, 'error' => 'Lecon introuvable.']);
        exit;
    }

    $lessonTitle = $lesson['title'];
    $lessonContent = $lesson['text_content'] ?? '';

    // Extraire le texte du PDF si present
    $pdfText = '';
    if (!empty($lesson['pdf_path'])) {
        $pdfFullPath = __DIR__ . '/../uploads/pdfs/' . $lesson['pdf_path'];
        if (is_file($pdfFullPath)) {
            $escapedPath = escapeshellarg($pdfFullPath);
            $output = [];
            $returnVar = 0;
            @exec("pdftotext {$escapedPath} - 2>/dev/null", $output, $returnVar);
            if ($returnVar === 0) {
                $pdfText = implode("\n", $output);
            }
        }
    }

    // Recuperer les videos de la lecon
    $vStmt = $db->prepare('SELECT url, label FROM lesson_videos WHERE lesson_id = :id ORDER BY sort_order ASC, id ASC');
    $vStmt->execute(['id' => $lessonId]);
    $videos = $vStmt->fetchAll();

    // Recuperer les ressources complémentaires
    $rStmt = $db->prepare('SELECT url, label, resource_type FROM lesson_resources WHERE lesson_id = :id ORDER BY sort_order ASC, id ASC');
    $rStmt->execute(['id' => $lessonId]);
    $resources = $rStmt->fetchAll();

    // Compiler le contexte d'apprentissage
    $contextDescription = "Titre de la lecon : \"{$lessonTitle}\"\n\n";

    if (!empty(trim($lessonContent))) {
        $contextDescription .= "--- TEXTE DE LA LECON ---\n" . trim($lessonContent) . "\n\n";
    }

    if (!empty(trim($pdfText))) {
        $contextDescription .= "--- CONTENU EXTRAIT DU DOCUMENT PDF ---\n" . trim($pdfText) . "\n\n";
    }

    // Videos
    $videoUrls = [];
    if (!empty($lesson['video_url'])) {
        $videoUrls[] = [
            'url' => $lesson['video_url'],
            'label' => 'Video principale du cours'
        ];
    }
    foreach ($videos as $v) {
        $videoUrls[] = [
            'url' => $v['url'],
            'label' => $v['label']
        ];
    }

    if (!empty($videoUrls)) {
        $contextDescription .= "--- VIDEOS DU COURS ---\n";
        foreach ($videoUrls as $vu) {
            $contextDescription .= "- [{$vu['label']}] : {$vu['url']}\n";
        }
        $contextDescription .= "\n";
    }

    // Ressources
    if (!empty($resources)) {
        $contextDescription .= "--- RESSOURCES COMPLEMENTAIRES ---\n";
        foreach ($resources as $res) {
            $contextDescription .= "- [{$res['label']}] ({$res['resource_type']}) : {$res['url']}\n";
        }
        $contextDescription .= "\n";
    }

    if (empty(trim($lessonContent)) && empty(trim($pdfText)) && empty($videoUrls)) {
        echo json_encode(['success' => false, 'error' => 'Cette lecon ne contient aucun contenu textuel, document PDF ou video. L\'IA ne peut pas generer d\'evaluations sans support de cours.']);
        exit;
    }

    $client = new GeminiClient(GEMINI_API_KEY);

    // Prompt systeme strict pour la creation d'evaluations (sans emojis)
    $systemInstruction = "Vous etes un ingenieur pedagogique méthodique et expert dans la conception d'evaluations academiques.\n"
                       . "Votre unique mission est de concevoir un questionnaire de {$numQuestions} questions QCM a choix unique, de niveau de difficulte \"{$difficulty}\", basées strictly sur la lecon suivante.\n\n"
                       . "Voici le contexte et les documents associes a la lecon :\n"
                       . "=========================================\n"
                       . $contextDescription
                       . "=========================================\n\n"
                       . "INSTRUCTIONS RIGOUREUSES DE CONCEPTION :\n"
                       . "1. Le questionnaire DOIT contenir exactement {$numQuestions} questions distinctes.\n"
                       . "2. Le niveau de difficulte global des questions doit repondre aux exigences du niveau \"{$difficulty}\".\n"
                       . "3. Il doit y avoir exactement 4 options (A, B, C, D) par question.\n"
                       . "4. N'utilisez JAMAIS d'emojis ni de fioritures dans the text de la question ou des options.\n"
                       . "5. Vous avez acces a la recherche Google pour analyser le contenu des videos listees si necessaire afin de formuler des questions pertinentes.\n"
                       . "6. Vous devez renvoyer EXCLUSIVEMENT un objet JSON respectant strictement cette structure :\n"
                       . "{\n"
                       . "  \"questions\": [\n"
                       . "    {\n"
                       . "      \"question\": \"Le texte de la question ?\",\n"
                       . "      \"options\": {\n"
                       . "        \"A\": \"Option A\",\n"
                       . "        \"B\": \"Option B\",\n"
                       . "        \"C\": \"Option C\",\n"
                       . "        \"D\": \"Option D\"\n"
                       . "      },\n"
                       . "      \"correct\": \"A\"\n"
                       . "    }\n"
                       . "  ]\n"
                       . "}\n"
                       . "Important: Pas de code markdown (comme ```json), pas de texte explicatif avant ou apres. Juste l'objet JSON brut.";

    $prompt = "Génère un QCM de {$numQuestions} questions académiques de niveau {$difficulty} basées sur les concepts et supports de cette leçon.";
    $response = $client->generate($prompt, $systemInstruction, true, true);

    $response = trim($response);
    if (str_starts_with($response, '```')) {
        $response = preg_replace('/^```(?:json)?\n?/', '', $response);
        $response = preg_replace('/```$/', '', $response);
        $response = trim($response);
    }

    $quizData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception("Erreur de generation JSON. Reponse brute : " . $response);
    }

    echo json_encode(['success' => true, 'questions' => $quizData['questions'] ?? []]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
