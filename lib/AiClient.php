<?php
declare(strict_types=1);

require_once __DIR__ . '/AnthropicClient.php';
require_once __DIR__ . '/RateLimit.php';

/**
 * The AI assistants (student mentor, teacher assistant, promoter reports) on the Anthropic API, through the same balancer and rate limiter as the
 * marking (see AnthropicClient). The small model answers (ANTHROPIC_MODEL_CHAT), the system prompt is cached, and the answer length is capped.
 * Without a key, a demonstration answer is returned so the pages keep working.
 */
class AiClient
{
    /** Longest answer (in tokens) of a plain reply and of a structured (JSON) one. */
    private const MAX_TOKENS_TEXT = 1200;
    private const MAX_TOKENS_JSON = 2800;

    /** Per person: calls per hour and per day. */
    private const LIMITS = ['student' => [20, 60], 'teacher' => [30, 120], 'promoter' => [12, 40]];
    /** Characters sent at most: a lesson is cut here rather than paying for a book (about 3 characters per token). */
    private const MAX_SYSTEM_CHARS = 30000;
    private const MAX_PROMPT_CHARS = 12000;

    /**
     * Rate limiting of the assistants: per person (hour and day) and for the whole platform (day, ANTHROPIC_DAILY_CHAT).
     * @return string|null null when the call may go ahead, otherwise the message to show
     */
    public static function guard(PDO $pdo, int $userId, string $role): ?string
    {
        [$perHour, $perDay] = self::LIMITS[$role] ?? [20, 60];
        $globalCap = defined('ANTHROPIC_DAILY_CHAT') ? max(1, (int)ANTHROPIC_DAILY_CHAT) : 3000;
        if (!RateLimit::allow($pdo, "ai:chat:$role:$userId:h", $perHour, 3600) || !RateLimit::allow($pdo, "ai:chat:$role:$userId:d", $perDay, 86400)) {
            return "Vous avez atteint la limite d'utilisation de l'assistant pour le moment. Réessayez un peu plus tard.";
        }
        if (!RateLimit::allow($pdo, 'ai:chat:day', $globalCap, 86400)) {
            return "L'assistant a atteint sa limite quotidienne d'utilisation. Réessayez demain.";
        }
        return null;
    }

    /**
     * @param string $prompt the user's prompt
     * @param string $systemInstruction the rules and context of the assistant
     * @param bool $jsonMode the answer must be one JSON document
     * @param bool $useSearch kept for the callers: there is no web search on this provider, the model answers from the context it is given
     * @throws Exception when the service cannot answer
     */
    public function generate(string $prompt, string $systemInstruction = '', bool $jsonMode = false, bool $useSearch = false): string
    {
        if (!AnthropicClient::isConfigured()) {
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

            return "Bonjour. Je reponds en mode demonstration car aucune cle API Anthropic n'a ete renseignee dans votre fichier .env. Posez-moi vos questions sur le cours, je ferai de mon mieux pour vous guider de maniere factuelle et structuree.";
        }

        $systemInstruction = mb_substr($systemInstruction, 0, self::MAX_SYSTEM_CHARS);
        $prompt = mb_substr($prompt, 0, self::MAX_PROMPT_CHARS);
        if ($jsonMode) {
            $systemInstruction = rtrim($systemInstruction) . "\n\nRéponds uniquement avec un document JSON valide, sans texte avant ni après et sans balises Markdown.";
        }
        $r = AnthropicClient::text($systemInstruction !== '' ? $systemInstruction : 'Tu es un assistant pédagogique précis et concis.', $prompt, $jsonMode ? self::MAX_TOKENS_JSON : self::MAX_TOKENS_TEXT, 'chat');
        if (!$r['ok']) {
            $why = match ($r['error'] ?? '') {
                'rate_limited', 'overloaded', 'timeout', 'server' => "Le service d'IA est très sollicité en ce moment : réessayez dans un instant.",
                'billing' => "Le service d'IA est momentanément indisponible.",
                'auth' => "Le service d'IA n'est pas configuré correctement.",
                default => "Le service d'IA n'a pas pu répondre.",
            };
            throw new \Exception($why);
        }
        $text = (string)$r['text'];
        if ($jsonMode) {
            $text = trim((string)preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
        }
        return $text;
    }
}
