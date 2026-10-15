<?php
declare(strict_types=1);

require_once __DIR__ . '/RateLimit.php';

/**
 * Gemini client of the lesson assistants (student mentor, teacher assistant, promoter reports). Anthropic is NOT used here: it only marks scripts
 * (see AnthropicClient).
 *
 * Keys: GEMINI_API_KEY and, optionally, GEMINI_API_KEYS (comma separated). Calls rotate over the keys and over the models (GEMINI_MODEL, comma
 * separated), and when a key or a model answers "quota full", "busy", "retired" or "refused" the call moves to the next one at once; the one that
 * failed rests for a while so it is not hit again by every request. Per-person and platform-wide limits protect the quota (see guard()).
 * Without any key a demonstration answer is returned, so the pages keep working.
 */
class GeminiClient
{
    /** Tests replace the network: fn(array $payload, string $keyHash, string $model): array{0:int,1:string} (HTTP status, body). */
    public static ?\Closure $transport = null;
    /** Tests: the keys (null = the configuration). */
    public static ?array $keysOverride = null;

    /** Per person: calls per hour and per day. */
    private const LIMITS = ['student' => [20, 60], 'teacher' => [30, 120], 'promoter' => [12, 40]];
    /** Characters sent at most (a lesson is cut here), and the longest answer in tokens. */
    private const MAX_SYSTEM_CHARS = 40000;
    private const MAX_PROMPT_CHARS = 16000;

    private ?string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, string $model = '')
    {
        $this->apiKey = $apiKey;      // kept for older callers: the rotation uses keys()
        $this->model = $model;
    }

    /** @return string[] */
    public static function keys(): array
    {
        if (self::$keysOverride !== null) {
            return self::$keysOverride;
        }
        $list = [];
        foreach ([defined('GEMINI_API_KEY') ? (string)GEMINI_API_KEY : '', defined('GEMINI_API_KEYS') ? (string)GEMINI_API_KEYS : ''] as $raw) {
            foreach (explode(',', $raw) as $k) {
                $k = trim($k);
                if ($k !== '' && $k !== 'votre_cle_api_gemini_ici' && preg_match('/^[A-Za-z0-9._-]{20,200}$/', $k)) {
                    $list[$k] = $k;
                }
            }
        }
        return array_values($list);
    }

    /** @return string[] */
    public static function models(): array
    {
        $out = [];
        foreach (explode(',', defined('GEMINI_MODEL') ? (string)GEMINI_MODEL : '') as $m) {
            $m = trim($m);
            if (preg_match('/^[a-z0-9._-]{3,60}$/i', $m)) {
                $out[] = $m;
            }
        }
        return $out ?: ['gemini-2.5-flash', 'gemini-3-flash-preview'];
    }

    /**
     * Rate limiting of the assistants: per person (hour and day) and for the whole platform (day, GEMINI_DAILY_CALLS).
     * @return string|null null when the call may go ahead, otherwise the message to show
     */
    public static function guard(PDO $pdo, int $userId, string $role): ?string
    {
        [$perHour, $perDay] = self::LIMITS[$role] ?? [20, 60];
        $globalCap = defined('GEMINI_DAILY_CALLS') ? max(1, (int)GEMINI_DAILY_CALLS) : 3000;
        if (!RateLimit::allow($pdo, "ai:chat:$role:$userId:h", $perHour, 3600) || !RateLimit::allow($pdo, "ai:chat:$role:$userId:d", $perDay, 86400)) {
            return "Vous avez atteint la limite d'utilisation de l'assistant pour le moment. Réessayez un peu plus tard.";
        }
        if (!RateLimit::allow($pdo, 'ai:chat:day', $globalCap, 86400)) {
            return "L'assistant a atteint sa limite quotidienne d'utilisation. Réessayez demain.";
        }
        return null;
    }

    private static function pdo(): ?PDO
    {
        try { return Database::getInstance(); } catch (Throwable $e) { return null; }
    }

    /** A lane (key + model) that failed rests: 60 s after a full quota or a busy API, an hour after a refusal or a retired model. */
    private static function resting(?PDO $pdo, string $lane): bool
    {
        if (!$pdo) { return false; }
        try {
            foreach ([60, 3600] as $w) {
                $st = $pdo->prepare("SELECT 1 FROM rate_hits WHERE bucket = :b AND hit_at >= NOW() - INTERVAL $w SECOND LIMIT 1");
                $st->execute(['b' => substr("gemini:r$w:$lane", 0, 190)]);
                if ($st->fetchColumn()) { return true; }
            }
        } catch (Throwable $e) { /* the state is a help, never a blocker */ }
        return false;
    }

    private static function rest(?PDO $pdo, string $lane, int $window): void
    {
        if (!$pdo) { return; }
        try { $pdo->prepare("INSERT INTO rate_hits (bucket, hit_at) VALUES (:b, NOW())")->execute(['b' => substr("gemini:r$window:$lane", 0, 190)]); } catch (Throwable $e) { /* ignore */ }
    }

    /**
     * Generates content from a prompt and system instructions.
     *
     * @param string $prompt the user's prompt
     * @param string $systemInstruction the behaviour rules and context of the assistant
     * @param bool $jsonMode force a structured JSON output
     * @param bool $useSearch let the model use Google Search (not combinable with JSON mode)
     * @throws Exception when no key can answer
     */
    public function generate(string $prompt, string $systemInstruction = '', bool $jsonMode = false, bool $useSearch = false): string
    {
        if (self::keys() === []) {
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

        $systemInstruction = mb_substr($systemInstruction, 0, self::MAX_SYSTEM_CHARS);
        $prompt = mb_substr($prompt, 0, self::MAX_PROMPT_CHARS);
        $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];
        if ($systemInstruction !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }
        // Gemini does not allow tools (Google Search) together with a JSON response type (HTTP 400)
        if ($jsonMode && !$useSearch) {
            $payload['generationConfig'] = ['responseMimeType' => 'application/json'];
        }
        if ($useSearch) {
            $payload['tools'] = [['google_search' => (object)[]]];
        }

        $keys = self::keys();
        $models = $this->model !== '' ? [$this->model] : self::models();
        $lanes = [];
        $start = random_int(0, max(0, count($keys) - 1));                // the keys share the load
        foreach ($models as $m) {
            for ($i = 0; $i < count($keys); $i++) {
                $lanes[] = [$keys[($start + $i) % count($keys)], $m];
            }
        }
        $pdo = self::pdo();
        $lastCode = 0; $lastMsg = '';
        foreach ($lanes as [$key, $model]) {
            $laneId = substr(hash('sha256', $key), 0, 12) . ':' . $model;
            if (self::resting($pdo, $laneId)) {
                continue;
            }
            [$status, $body] = self::$transport !== null ? (self::$transport)($payload, substr(hash('sha256', $key), 0, 12), $model) : $this->post($payload, $key, $model);
            if ($status === 200) {
                $data = json_decode($body, true);
                $textParts = [];
                foreach ((array)($data['candidates'][0]['content']['parts'] ?? []) as $part) {
                    if (isset($part['text'])) { $textParts[] = $part['text']; }
                }
                if ($textParts) {
                    return implode("\n", $textParts);
                }
                $lastCode = 200; $lastMsg = 'empty';
                continue;
            }
            $lastCode = $status; $lastMsg = (string)(json_decode($body, true)['error']['message'] ?? '');
            if ($status === 429 || $status === 503 || $status === 0 || $status >= 500) {
                self::rest($pdo, $laneId, 60);                             // quota full or busy: next lane now, back in a minute
            } elseif (in_array($status, [401, 403, 404], true) || ($status === 400 && stripos($body, 'API key') !== false)) {
                self::rest($pdo, $laneId, 3600);                            // refused key or retired model: not again for an hour
            } else {
                break;                                                      // a request that is wrong: another lane would not help
            }
        }
        error_log('GeminiClient: no lane answered (last HTTP ' . $lastCode . ')');
        throw new \Exception(in_array($lastCode, [0, 429, 503, 500, 502, 504], true) || $lastCode >= 500
            ? "Le service d'IA est très sollicité en ce moment : réessayez dans un instant."
            : "Le service d'IA n'a pas pu répondre.");
    }

    /** @return array{0:int,1:string} */
    private function post(array $payload, string $key, string $model): array
    {
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent');
        if ($ch === false) {
            return [0, ''];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],     // the key goes in a header, never in the URL
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 90, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$body === false ? 0 : $status, $body === false ? '' : (string)$body];
    }
}
