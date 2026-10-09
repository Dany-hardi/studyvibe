<?php
declare(strict_types=1);

require_once __DIR__ . '/MediaStore.php';
require_once __DIR__ . '/../QuestionImporter.php';

/**
 * Bulk import of questions with their pictures, from one ZIP file.
 *
 * THE FORMAT
 *   mon-pack.zip
 *     questions.csv      one CSV (the first one found). Columns, in any order, header names are forgiving:
 *                        question, option_a, option_b, option_c, option_d, correct, explanation       (as before)
 *                        type        optional: "mcq" or "written" (a written question has no options; "correct" holds the accepted answer)
 *                        image       optional: the name of a picture file inside the zip, for example  figure1.png
 *                        time_limit  optional: seconds for this question (5 to 3600); empty = the session's default
 *     images/            optional folder (any folder name, or none) with PNG, JPG, GIF or WebP files
 *   A plain .csv file works too (no pictures then).
 *
 * THE RULES
 *   - Nothing is imported until the package has been checked ("preview"). The check reports, line by line, what is wrong.
 *   - All or nothing: if any line has an error, nothing is imported, so a half-imported exam never happens.
 *   - A picture must be a real picture (checked on the file itself), is scaled to 1600 px and kept in the database copy too.
 *   - Safety: at most 300 questions, 400 files, 120 MB once unpacked, 8 MB per picture; file names with folders or ".." are never
 *     used as paths (pictures are matched by their file name only, nothing is written outside a private temporary folder).
 *   - Pictures are used by live evaluations. Lesson and course quizzes do not carry pictures: they are ignored with a warning.
 */
final class BulkPackage
{
    public const MAX_QUESTIONS = 300;
    public const MAX_ENTRIES = 400;
    public const MAX_UNPACKED = 125829120;   // 120 MB
    public const MAX_IMAGE = 8388608;        // 8 MB
    private const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /**
     * Opens a package (a .zip or a bare .csv) into memory and a private temp folder.
     *
     * @return array{csv:?string,images:array<string,string>,warnings:string[],errors:string[],tmp:string}
     *         images: lower-case file name => path of the unpacked file. Call cleanup($result['tmp']) when done.
     */
    public static function open(string $path, string $originalName): array
    {
        $out = ['csv' => null, 'images' => [], 'warnings' => [], 'errors' => [], 'tmp' => ''];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($ext, ['csv', 'txt'], true)) {
            $out['csv'] = (string)file_get_contents($path);
            return $out;
        }
        if ($ext !== 'zip') {
            $out['errors'][] = 'Envoyez un fichier .zip (questions.csv et un dossier d’images) ou un fichier .csv.';
            return $out;
        }
        if (!class_exists('ZipArchive')) {
            $out['errors'][] = 'Le serveur ne sait pas ouvrir les fichiers ZIP (extension PHP zip absente).';
            return $out;
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            $out['errors'][] = 'Ce fichier ZIP est illisible ou endommagé.';
            return $out;
        }
        if ($zip->numFiles > self::MAX_ENTRIES) {
            $out['errors'][] = 'Le ZIP contient trop de fichiers (' . self::MAX_ENTRIES . ' au plus).';
            $zip->close();
            return $out;
        }

        // First pass: what is inside, without extracting anything
        $total = 0;
        $csvName = null;
        $imageEntries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            if ($st === false) {
                continue;
            }
            $name = str_replace('\\', '/', (string)$st['name']);
            if (str_ends_with($name, '/')) {
                continue;   // a folder
            }
            $total += (int)$st['size'];
            $base = basename($name);
            if ($base === '' || $base[0] === '.' || str_contains($name, '__MACOSX/') || preg_match('~(^|/)\.\.(/|$)~', $name)) {
                continue;   // hidden files, macOS leftovers, and anything trying to climb out of the folder
            }
            $e = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if ($e === 'csv' && $csvName === null) {
                $csvName = $i;
            } elseif (in_array($e, self::IMAGE_EXT, true)) {
                $imageEntries[] = [$i, $base, (int)$st['size']];
            }
        }
        if ($total > self::MAX_UNPACKED) {
            $out['errors'][] = 'Le ZIP est trop volumineux une fois décompressé (120 Mo au plus).';
            $zip->close();
            return $out;
        }
        if ($csvName === null) {
            $out['errors'][] = 'Aucun fichier .csv trouvé dans le ZIP. Ajoutez un fichier questions.csv à la racine.';
            $zip->close();
            return $out;
        }
        $out['csv'] = (string)$zip->getFromIndex($csvName);

        // Second pass: pictures only, into a private temporary folder, by file name
        $tmp = rtrim(sys_get_temp_dir(), '/\\') . '/svbulk_' . bin2hex(random_bytes(8));
        if (!@mkdir($tmp, 0700, true)) {
            $out['errors'][] = 'Impossible de préparer le dossier temporaire.';
            $zip->close();
            return $out;
        }
        $out['tmp'] = $tmp;
        $seq = 0;
        foreach ($imageEntries as [$idx, $base, $size]) {
            $key = strtolower($base);
            if (isset($out['images'][$key])) {
                $out['warnings'][] = "Deux images portent le nom « {$base} » : la première est utilisée.";
                continue;
            }
            if ($size > self::MAX_IMAGE) {
                $out['errors'][] = "L’image « {$base} » dépasse 8 Mo.";
                continue;
            }
            $stream = $zip->getStream((string)$zip->getNameIndex($idx));
            if (!$stream) {
                continue;
            }
            $dest = $tmp . '/' . (++$seq) . '.bin';   // never the name from the zip
            $fh = fopen($dest, 'wb');
            $written = 0;
            while (!feof($stream) && $written <= self::MAX_IMAGE) {
                $chunk = fread($stream, 65536);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $written += strlen($chunk);
                fwrite($fh, $chunk);
            }
            fclose($fh);
            fclose($stream);
            if ($written > self::MAX_IMAGE) {
                @unlink($dest);
                $out['errors'][] = "L’image « {$base} » dépasse 8 Mo.";
                continue;
            }
            $out['images'][$key] = $dest;
        }
        $zip->close();
        return $out;
    }

    public static function cleanup(string $tmp): void
    {
        if ($tmp === '' || !is_dir($tmp) || !str_contains($tmp, 'svbulk_')) {
            return;
        }
        foreach (glob($tmp . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($tmp);
    }

    /**
     * Checks a package and describes it line by line. Nothing is written.
     *
     * @param string $type 'live' | 'lesson' | 'course'
     * @return array{ok:bool,questions:array,rows:array<int,array<string,mixed>>,errors:string[],warnings:string[],summary:array<string,int>,tmp:string,images:array<string,string>}
     */
    public static function check(string $path, string $originalName, string $type): array
    {
        $pkg = self::open($path, $originalName);
        $errors = $pkg['errors'];
        $warnings = $pkg['warnings'];
        $questions = [];
        $rows = [];

        if ($pkg['csv'] !== null) {
            $parsed = QuestionImporter::parseCsv($pkg['csv'], $type === 'live');
            $questions = $parsed['questions'];
            $errors = array_merge($errors, $parsed['errors']);
            if (count($questions) > self::MAX_QUESTIONS) {
                $errors[] = 'Trop de questions (' . self::MAX_QUESTIONS . ' au plus par pack).';
            }
            if (!$questions && !$errors) {
                $errors[] = 'Aucune question valide trouvée dans le CSV.';
            }
        }

        $usedImages = [];
        $withImage = 0;
        foreach ($questions as $i => $q) {
            $msgs = [];
            $status = 'ok';
            $imgState = '';
            $name = (string)($q['image'] ?? '');
            if ($name !== '') {
                if ($type !== 'live') {
                    $warnings[] = 'Question ' . ($i + 1) . ' : les images ne sont utilisées que pour les évaluations en direct, elle sera ignorée.';
                    $imgState = 'ignored';
                } else {
                    $key = strtolower(basename(str_replace('\\', '/', $name)));
                    if (!isset($pkg['images'][$key])) {
                        $errors[] = 'Question ' . ($i + 1) . " : l’image « {$name} » est introuvable dans le ZIP.";
                        $msgs[] = 'image introuvable';
                        $status = 'error';
                        $imgState = 'missing';
                    } else {
                        $info = @getimagesize($pkg['images'][$key]);
                        if ($info === false || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
                            $errors[] = 'Question ' . ($i + 1) . " : « {$name} » n’est pas une image valide.";
                            $msgs[] = 'image invalide';
                            $status = 'error';
                            $imgState = 'invalid';
                        } else {
                            $usedImages[$key] = true;
                            $withImage++;
                            $imgState = 'ok';
                            $questions[$i]['image_tmp'] = $pkg['images'][$key];
                        }
                    }
                }
            }
            $rows[] = [
                'n' => $i + 1, 'text' => mb_strimwidth((string)$q['question_text'], 0, 140, '…'), 'type' => (string)$q['question_type'],
                'image' => $name, 'image_state' => $imgState, 'time_limit' => $q['time_limit'] ?? null, 'status' => $status, 'messages' => $msgs,
            ];
        }
        foreach (array_keys($pkg['images']) as $key) {
            if (!isset($usedImages[$key]) && $type === 'live') {
                $warnings[] = "L’image « {$key} » n’est utilisée par aucune question.";
            }
        }

        return [
            'ok' => !$errors && $questions,
            'questions' => $questions, 'rows' => $rows, 'errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings)),
            'summary' => ['questions' => count($questions), 'with_image' => $withImage, 'errors' => count(array_unique($errors)), 'warnings' => count(array_unique($warnings))],
            'tmp' => $pkg['tmp'], 'images' => $pkg['images'],
        ];
    }

    /**
     * Writes a checked package into a live session: pictures first (checked, scaled, kept in the database), then every question in
     * one transaction. If anything fails the pictures just saved are removed and nothing is imported.
     *
     * @return int number of questions imported
     */
    public static function importLive(PDO $pdo, int $sessionId, array $questions): int
    {
        $saved = [];
        try {
            foreach ($questions as $i => $q) {
                if (!empty($q['image_tmp'])) {
                    $r = MediaStore::saveImageFile($pdo, (string)$q['image_tmp'], 'live_question', 1600);
                    if (!$r['ok']) {
                        throw new RuntimeException('Question ' . ($i + 1) . ' : l’image n’a pas pu être enregistrée (' . ($r['error'] ?? '?') . ').');
                    }
                    $saved[] = $r['file'];
                    $questions[$i]['image_path'] = $r['file'];
                }
            }
            $pdo->beginTransaction();
            $count = QuestionImporter::importLiveQuestions($pdo, $sessionId, $questions);
            $pdo->commit();
            return $count;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($saved as $f) {
                MediaStore::delete($pdo, 'live_question', $f);
            }
            throw $e;
        }
    }

    /** A ready-to-fill package: the CSV with examples, one picture, and a short guide. Returns the path of a temporary zip. */
    public static function template(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'svtpl_');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);

        $csv = "question,type,option_a,option_b,option_c,option_d,correct,explanation,image,time_limit\n"
             . "\"Que renvoie ce code ? (voir l'image)\",mcq,2,3,4,5,B,\"Explication de la réponse.\",exemple.png,45\n"
             . "\"Question sans image\",mcq,Vrai,Faux,,,A,\"Seules A et B sont remplies : c'est une question vrai ou faux.\",,\n"
             . "\"Donnez le résultat de 2+3\",written,,,,,5,\"Réponse écrite : plusieurs réponses possibles avec |  (par exemple 5|cinq).\",,30\n";
        $zip->addFromString('questions.csv', "\xEF\xBB\xBF" . $csv);

        if (function_exists('imagecreatetruecolor')) {
            $im = imagecreatetruecolor(640, 220);
            imagefill($im, 0, 0, imagecolorallocate($im, 33, 34, 30));
            $ink = imagecolorallocate($im, 230, 230, 220);
            $clay = imagecolorallocate($im, 226, 123, 87);
            imagestring($im, 5, 24, 30, 'x = 2', $ink);
            imagestring($im, 5, 24, 60, 'y = x + 2', $ink);
            imagestring($im, 5, 24, 90, 'print(y)', $clay);
            imagestring($im, 3, 24, 170, 'exemple.png : remplacez cette image par la votre', $ink);
            ob_start();
            imagepng($im);
            $zip->addFromString('images/exemple.png', (string)ob_get_clean());
        }
        $zip->addFromString('LISEZMOI.txt', implode("\n", [
            "PACK D'IMPORT EN LOT STUDYVIBE",
            "",
            "Contenu du ZIP :",
            "  questions.csv   le fichier des questions (un seul .csv)",
            "  images/         les images (PNG, JPG, GIF ou WebP), facultatif",
            "",
            "Colonnes de questions.csv (dans n'importe quel ordre) :",
            "  question, option_a, option_b, option_c, option_d, correct, explanation   comme d'habitude",
            "  type          facultatif : mcq (QCM) ou written (réponse écrite)",
            "  image         facultatif : le nom d'un fichier du dossier images/ (par exemple exemple.png)",
            "  time_limit    facultatif : durée de la question en secondes (5 à 3600)",
            "",
            "Règles :",
            "  - Le pack est d'abord vérifié : rien n'est importé tant qu'il y a une erreur (tout ou rien).",
            "  - Une image doit être une vraie image ; elle est réduite à 1600 px de large.",
            "  - Les images servent aux évaluations en direct. Les quiz de leçon et de cours les ignorent.",
            "  - Maximum : 300 questions, 400 fichiers, 120 Mo décompressé, 8 Mo par image.",
            "  - Question vrai/faux : ne remplissez que option_a et option_b.",
            "  - Question écrite : laissez les options vides ; 'correct' contient la réponse attendue (plusieurs réponses : a|b ; tolérance : 2.5~0.1).",
            "",
        ]));
        $zip->close();
        return $path;
    }
}
