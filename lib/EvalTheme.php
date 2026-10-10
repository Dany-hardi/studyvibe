<?php
declare(strict_types=1);

/**
 * Picks a theme (and its illustration and colour) for an evaluation from its title and course title,
 * so each card on /evaluations.php carries a picture that fits the subject. Pure function, no database.
 */
final class EvalTheme
{
    /** theme => [colour, illustrations (assets/img/evals/<theme>-<name>.svg), keywords (lower case, no accents), label fr, label en] */
    private const THEMES = [
        'math'        => ['#3b5b7a', ['math', 'mathematics'], ['math', 'calcul', 'algebre', 'analyse', 'statist', 'probab', 'geometrie', 'integral', 'arithm', 'logique', 'matrice', 'equation'], 'Mathématiques', 'Mathematics'],
        'code'        => ['#0f6f6f', ['programmer', 'programming', 'typing-code', 'pair-programming', 'web-developer'], ['programm', 'informatique', 'code', 'algorithm', 'python', 'java', 'php', 'javascript', 'web', 'logiciel', 'developp', 'sql', 'html', 'css', 'systeme', 'reseau', 'cyber', 'unix', 'linux', 'genie logiciel'], 'Informatique', 'Computing'],
        'data'        => ['#7a4b8a', ['data-at-work', 'data-trends', 'data-analysis'], ['data', 'donnee', 'intelligence artificielle', 'machine learning', 'big data', 'base de donnees', 'analytique'], 'Données', 'Data'],
        'science'     => ['#3f6b5a', ['science', 'physics', 'studying-science'], ['physique', 'chimie', 'science', 'mecanique', 'electri', 'electron', 'thermo', 'optique', 'quantique', 'laboratoire', 'astronom'], 'Sciences', 'Science'],
        'health'      => ['#c0455a', ['doctors', 'medical-research', 'medical-care'], ['medec', 'sante', 'biologie', 'anatom', 'physiolog', 'pharma', 'infirm', 'clinique', 'biochim', 'epidemi', 'nutrition'], 'Santé et vivant', 'Health and life'],
        'nature'      => ['#5c8a3a', ['environmental-study', 'nature-benefits', 'wind-turbines'], ['environnement', 'ecolog', 'agricult', 'agro', 'geolog', 'climat', 'energie', 'durable', 'foret', 'hydrolog', 'geograph'], 'Environnement', 'Environment'],
        'business'    => ['#c98a2b', ['business-decisions', 'business-plan', 'finance'], ['gestion', 'econom', 'marketing', 'comptab', 'financ', 'management', 'entreprise', 'commerce', 'banque', 'audit', 'entrepreneur', 'business'], 'Gestion et économie', 'Business and economics'],
        'law'         => ['#7a5230', ['legal-counsel', 'judge'], ['droit', 'juridi', 'loi', 'justice', 'fiscal', 'contrat', 'constitution', 'legal'], 'Droit', 'Law'],
        'language'    => ['#a8503a', ['essay-writing', 'writer', 'book-writer'], ['langue', 'anglais', 'francais', 'english', 'communication', 'redaction', 'litterature', 'grammaire', 'expression', 'traduction', 'espagnol', 'allemand', 'linguist'], 'Langues et expression', 'Languages and writing'],
        'reading'     => ['#8a5a2b', ['reading-a-book', 'continuous-learning'], ['histoire', 'philosoph', 'sociolog', 'psycholog', 'lecture', 'culture', 'pedagog', 'education', 'anthropolog', 'sciences humaines'], 'Lettres et sciences humaines', 'Humanities'],
        'engineering' => ['#4a5a8a', ['engineering-team', 'qa-engineers', 'circuit-board'], ['ingenieur', 'genie', 'construction', 'btp', 'industriel', 'automatique', 'robot', 'materiaux', 'architecture', 'telecom', 'maintenance'], 'Ingénierie', 'Engineering'],
        'design'      => ['#b5478a', ['creative-designer', 'design-thinking', 'art-lover'], ['design', 'graphi', 'art', 'creatif', 'ux', 'multimedia', 'dessin', 'musique', 'image', 'video'], 'Création et design', 'Design and creative'],
        'general'     => ['#b5472b', ['exam-prep', 'exams', 'quiz', 'learning', 'studying'], [], 'Évaluation', 'Evaluation'],
    ];

    /** Lower case without accents, so "Algèbre" matches "algebre". */
    public static function normalize(string $s): string
    {
        $s = function_exists('transliterator_transliterate')
            ? (string)transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s)
            : strtolower(strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c']));
        return ' ' . trim(preg_replace('/\s+/', ' ', $s) ?? $s) . ' ';
    }

    /** The best matching theme key; the title counts double against the course title. */
    public static function detect(string $title, string $course): string
    {
        $t = self::normalize($title);
        $c = self::normalize($course);
        $best = 'general';
        $bestScore = 0;
        foreach (self::THEMES as $key => [, , $words]) {
            $score = 0;
            foreach ($words as $w) {
                $w = trim($w);
                // short words must be whole words ("art" is not "part"); longer ones match the start of a word ("programm" = "programmation")
                $needle = strlen($w) <= 4 ? ' ' . $w . ' ' : ' ' . $w;
                if (str_contains($t, $needle)) { $score += 2; }
                if (str_contains($c, $needle)) { $score += 1; }
            }
            if ($score > $bestScore) { $bestScore = $score; $best = $key; }
        }
        return $best;
    }

    /** Illustration URL for a theme; the session id spreads the variants so neighbouring cards differ. */
    public static function art(string $theme, int $seed): string
    {
        $theme = isset(self::THEMES[$theme]) ? $theme : 'general';
        $list = self::THEMES[$theme][1];
        return '/assets/img/evals/' . $theme . '-' . $list[abs($seed) % count($list)] . '.svg';
    }

    public static function color(string $theme): string
    {
        return (self::THEMES[$theme] ?? self::THEMES['general'])[0];
    }

    public static function label(string $theme, string $lang): string
    {
        $t = self::THEMES[$theme] ?? self::THEMES['general'];
        return $lang === 'en' ? $t[4] : $t[3];
    }

    /** @return string[] every theme key */
    public static function themes(): array
    {
        return array_keys(self::THEMES);
    }

    /** @return string[] every illustration file path relative to the project root (used by the tests) */
    public static function files(): array
    {
        $out = [];
        foreach (self::THEMES as $key => [, $names]) {
            foreach ($names as $n) { $out[] = 'assets/img/evals/' . $key . '-' . $n . '.svg'; }
        }
        return $out;
    }
}
