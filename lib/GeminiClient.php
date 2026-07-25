<?php
declare(strict_types=1);

/**
 * Client d'API Gemini de Google.
 * Permet d'appeler l'API Gemini avec des instructions système, des prompts et le support du format JSON.
 */
class GeminiClient
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gemini-2.5-flash')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * Génère du contenu à partir d'un prompt et d'instructions système.
     *
     * @param string $prompt Le prompt de l'utilisateur.
     * @param string $systemInstruction Les règles de comportement et contexte de l'assistant (prompt système).
     * @param bool $jsonMode Si true, force la sortie au format JSON structuré.
     * @return string
     * @throws Exception
     */
    public function generate(string $prompt, string $systemInstruction = '', bool $jsonMode = false, bool $useSearch = false): string
    {
        if (empty($this->apiKey) || $this->apiKey === 'votre_cle_api_gemini_ici') {
            // Mode demonstration / simulation intelligent
            if ($jsonMode) {
                return json_encode([
                    "questions" => [
                        [
                            "question" => "Quel est le concept cle mis en avant dans cette section du cours ?",
                            "options" => [
                                "A" => "L'application rigoureuse des principes fondamentaux",
                                "B" => "L'introduction de concepts secondaires non verifies",
                                "C" => "L'absence de structure et de methode",
                                "D" => "L'utilisation de techniques obsoletes"
                            ],
                            "correct" => "A"
                        ],
                        [
                            "question" => "Quelle est l'etape indispensable pour valider ce processus ?",
                            "options" => [
                                "A" => "L'evaluation continue et l'analyse critique",
                                "B" => "La memorisation sans comprehension",
                                "C" => "La suppression des donnees de test",
                                "D" => "L'ignorance des avertissements de securite"
                            ],
                            "correct" => "A"
                        ],
                        [
                            "question" => "Comment optimiser l'engagement des apprenants selon l'analyse strategique ?",
                            "options" => [
                                "A" => "En fournissant des explications claires et des evaluations interactives",
                                "B" => "En augmentant la complexite sans accompagnement",
                                "C" => "En supprimant les options de support et d'aide",
                                "D" => "En limitant l'acces aux ressources pedagogiques"
                            ],
                            "correct" => "A"
                        ]
                    ]
                ]);
            }

            if (str_contains(strtolower($prompt), 'resume') || str_contains(strtolower($prompt), 'summarize')) {
                return "DIAGNOSTIC DE COURS (MODE DEMONSTRATION)\n\n"
                     . "Ce cours aborde les concepts fondamentaux indispensables a la maitrise de la matiere.\n"
                     . "Les points cles a retenir sont :\n"
                     . "- L'assimilation des principes theoriques de base.\n"
                     . "- La mise en application pratique a travers des exercices diriges.\n"
                     . "- L'auto-evaluation systematique pour consolider les acquis et identifier les lacunes.";
            }

            if (str_contains(strtolower($prompt), 'expliqu')) {
                return "EXPLICATION DES CONCEPTS (MODE DEMONSTRATION)\n\n"
                     . "Pour comprendre simplement ce cours, imaginez que chaque concept est une brique elementaire.\n"
                     . "L'assemblage structure de ces briques permet de batir un raisonnement logique et solide.\n"
                     . "L'essentiel est de progresser etape par etape sans bruler les etapes de validation.";
            }

            if (str_contains(strtolower($systemInstruction), 'promoteur')) {
                return "RAPPORT D'AUDIT ACADEMIQUE (MODE DEMONSTRATION)\n\n"
                     . "Diagnostic d'Activite :\n"
                     . "La plateforme StudyVibe LMS presente une repartition d'inscriptions saine avec une participation active sur les cours principaux. Le volume d'apprenants montre une dynamique encourageante.\n\n"
                     . "Analyse de la Repartition :\n"
                     . "Certains cours affichent une concentration d'inscriptions plus elevee, ce qui suggere une forte attractivite des matieres fondamentales ou une meilleure visibilite de ces modules.\n\n"
                     . "Recommandations Operationalisees :\n"
                     . "1. Mettre en place des parcours d'apprentissage personnalises pour dynamiser les cours secondaires.\n"
                     . "2. Integrer des mini-evaluations de mi-parcours pour accroitre l'engagement.\n"
                     . "3. Valoriser les certifications pour motiver la finalisation des modules.";
            }

            return "Bonjour. Je reponds en mode demonstration car aucune cle API Gemini n'a ete renseignee dans votre fichier .env. Posez-moi vos questions sur le cours, je ferai de mon mieux pour vous guider de maniere factuelle et structuree.";
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($this->model) . ":generateContent?key=" . urlencode($this->apiKey);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        // Gemini API ne permet pas d'associer tools (Google Search) et responseMimeType application/json (HTTP 400).
        if ($jsonMode && !$useSearch) {
            $payload['generationConfig'] = [
                'responseMimeType' => 'application/json'
            ];
        }

        if ($useSearch) {
            $payload['tools'] = [
                ['google_search' => (object)[]]
            ];
        }

        $jsonData = json_encode($payload);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \Exception("Impossible d'initialiser cURL.");
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // Desactiver temporairement la verification SSL locale si necessaire (optionnel, mais conseille de laisser actif pour la securite)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception("Erreur de connexion reseau vers Gemini : " . $error);
        }

        if ($httpCode !== 200) {
            $errDetail = json_decode($response, true);
            $errMsg = $errDetail['error']['message'] ?? $response;
            throw new \Exception("L'API Gemini a retourne une erreur (Code HTTP {$httpCode}) : " . $errMsg);
        }

        $data = json_decode($response, true);
        $textParts = [];
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textParts[] = $part['text'];
            }
        }
        if (empty($textParts)) {
            throw new \Exception("Format de reponse inattendu de la part de Gemini.");
        }

        return implode("\n", $textParts);
    }
}
