<?php
declare(strict_types=1);

/**
 * Uploaded files that survive a redeploy.
 *
 * The web server's disk is a cache. Hosts such as Railway or Render wipe it on every deploy, so each upload is also copied
 * into the database, in slices of 256 KB (a single row would hit MySQL's max_allowed_packet, which is only 1 MB on a
 * default local install). When a file is missing from the disk, download.php calls restore() and puts it back.
 *
 * Images (profile photos, course covers, question images) go through saveImage(): the type is read from the file itself,
 * not from what the browser claims, the picture is re-encoded (which removes anything hidden inside it, EXIF included),
 * turned upright, and scaled down, so a 6 MB phone photo becomes a 100 to 300 KB file.
 *
 * Keys look like "avatar/3f2a....jpg": the kind of file, then its name. Kinds and their folders are listed in DIRS.
 */
final class MediaStore
{
    public const SLICE = 262144;

    /** kind => folder under uploads/ */
    public const DIRS = [
        'avatar'        => 'avatars',
        'cover'         => 'course-covers',
        'live_question' => 'live_questions',
        'library'       => 'library',
        'assignment'    => 'assignments',
        'pdf'           => 'pdfs',
    ];

    public static function dir(string $kind): string
    {
        $sub = self::DIRS[$kind] ?? throw new InvalidArgumentException('Unknown media kind: ' . $kind);
        $dir = dirname(__DIR__) . '/uploads/' . $sub . '/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public static function path(string $kind, string $file): string
    {
        return self::dir($kind) . basename($file);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Images
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Validates, cleans and stores an uploaded picture.
     *
     * @param array{tmp_name?:string,error?:int,size?:int} $upload an entry of $_FILES
     * @return array{ok:bool,file?:string,error?:string} error is one of: none, upload, too_big, not_image, too_large_dimensions, server
     */
    public static function saveImage(PDO $pdo, array $upload, string $kind, int $maxDim, int $maxBytes = 8388608, int $quality = 84): array
    {
        $err = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'none'];
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'too_big'];
        }
        $tmp = (string)($upload['tmp_name'] ?? '');
        if ($err !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'upload'];
        }
        if ((int)($upload['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'too_big'];
        }
        return self::saveImageFile($pdo, $tmp, $kind, $maxDim, $quality);
    }

    /** Same as saveImage() for a file already on the disk (this is what the tests call). */
    public static function saveImageFile(PDO $pdo, string $tmp, string $kind, int $maxDim, int $quality = 84): array
    {
        $info = @getimagesize($tmp);
        $mime = $info['mime'] ?? '';
        if ($info === false || !in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return ['ok' => false, 'error' => 'not_image'];
        }
        // A "decompression bomb": tiny file, enormous picture. Refuse before GD tries to allocate it.
        if ($info[0] * $info[1] > 40_000_000) {
            return ['ok' => false, 'error' => 'too_large_dimensions'];
        }
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/gif'  => @imagecreatefromgif($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        };
        if (!$src) {
            return ['ok' => false, 'error' => 'not_image'];
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $rot = match ((int)($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
            if ($rot !== 0 && ($turned = imagerotate($src, $rot, 0))) {
                $src = $turned;
            }
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, $maxDim / max($w, $h));
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // transparent areas become white
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $name = bin2hex(random_bytes(16)) . '.jpg';
        $path = self::path($kind, $name);
        $saved = imagejpeg($dst, $path, $quality);
        if (!$saved) {
            return ['ok' => false, 'error' => 'server'];
        }
        @chmod($path, 0644);

        if (!self::persist($pdo, $kind . '/' . $name, $path, 'image/jpeg')) {
            @unlink($path);
            return ['ok' => false, 'error' => 'server'];
        }
        return ['ok' => true, 'file' => $name];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Documents
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Stores an uploaded document under a generated name, keeping only the listed extensions.
     *
     * @param string[] $allowedExt lower-case extensions
     * @return array{ok:bool,file?:string,size?:int,error?:string}
     */
    public static function saveDocument(PDO $pdo, array $upload, string $kind, array $allowedExt, int $maxBytes, string $prefix = ''): array
    {
        $err = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'none'];
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'too_big'];
        }
        $tmp = (string)($upload['tmp_name'] ?? '');
        if ($err !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'upload'];
        }
        $size = (int)filesize($tmp);
        if ($size > $maxBytes) {
            return ['ok' => false, 'error' => 'too_big'];
        }
        $ext = strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return ['ok' => false, 'error' => 'bad_type'];
        }
        $name = $prefix . bin2hex(random_bytes(16)) . '.' . $ext;
        $path = self::path($kind, $name);
        if (!move_uploaded_file($tmp, $path)) {
            return ['ok' => false, 'error' => 'server'];
        }
        @chmod($path, 0644);
        if (!self::persist($pdo, $kind . '/' . $name, $path, '')) {
            @unlink($path);
            return ['ok' => false, 'error' => 'server'];
        }
        return ['ok' => true, 'file' => $name, 'size' => $size];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The database copy
    // ---------------------------------------------------------------------------------------------------------------

    /** Copies a file on the disk into the database, replacing any earlier copy under the same key. */
    public static function persist(PDO $pdo, string $key, string $path, string $mime = ''): bool
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return false;
        }
        $own = !$pdo->inTransaction();
        try {
            if ($own) {
                $pdo->beginTransaction();
            }
            $pdo->prepare('DELETE FROM media_chunks WHERE media_key = :k')->execute(['k' => $key]);
            $ins = $pdo->prepare('INSERT INTO media_chunks (media_key, seq, data) VALUES (:k, :s, :d)');
            $seq = 0;
            while (!feof($fh)) {
                $chunk = fread($fh, self::SLICE);
                if ($chunk === false || ($chunk === '' && $seq > 0)) {
                    break;
                }
                $ins->bindValue('k', $key);
                $ins->bindValue('s', $seq++, PDO::PARAM_INT);
                $ins->bindValue('d', $chunk, PDO::PARAM_LOB);
                $ins->execute();
            }
            $pdo->prepare(
                'REPLACE INTO media_files (media_key, mime, size_bytes, sha256) VALUES (:k, :m, :s, :h)'
            )->execute(['k' => $key, 'm' => $mime, 's' => (int)filesize($path), 'h' => hash_file('sha256', $path)]);
            if ($own) {
                $pdo->commit();
            }
            return true;
        } catch (Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('MediaStore::persist ' . $key . ': ' . $e->getMessage());
            return false;
        } finally {
            fclose($fh);
        }
    }

    /** Puts a missing file back on the disk from its database copy. Returns true when the file exists afterwards. */
    public static function restore(PDO $pdo, string $key, string $path): bool
    {
        if (is_file($path)) {
            return true;
        }
        try {
            $meta = $pdo->prepare('SELECT sha256 FROM media_files WHERE media_key = :k');
            $meta->execute(['k' => $key]);
            $sha = $meta->fetchColumn();
            if ($sha === false) {
                return false;
            }
            $rows = $pdo->prepare('SELECT data FROM media_chunks WHERE media_key = :k ORDER BY seq ASC');
            $rows->execute(['k' => $key]);
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $tmp = $path . '.' . getmypid() . '.part';
            $out = @fopen($tmp, 'wb');
            if (!$out) {
                return false;
            }
            while (($chunk = $rows->fetchColumn()) !== false) {
                fwrite($out, (string)$chunk);
            }
            fclose($out);
            if (!hash_equals((string)$sha, (string)hash_file('sha256', $tmp))) {
                @unlink($tmp);   // a damaged copy is never served
                return false;
            }
            return @rename($tmp, $path);
        } catch (Throwable $e) {
            error_log('MediaStore::restore ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Removes a file from the disk and from the database. */
    public static function delete(PDO $pdo, string $kind, string $file): void
    {
        $file = basename($file);
        if ($file === '') {
            return;
        }
        @unlink(self::path($kind, $file));
        try {
            $key = $kind . '/' . $file;
            $pdo->prepare('DELETE FROM media_chunks WHERE media_key = :k')->execute(['k' => $key]);
            $pdo->prepare('DELETE FROM media_files WHERE media_key = :k')->execute(['k' => $key]);
        } catch (Throwable $e) {
            error_log('MediaStore::delete ' . $e->getMessage());
        }
    }

    /** Short messages for the error codes above, in French (the default language of the app). */
    public static function message(string $code): string
    {
        return match ($code) {
            'none'                 => 'Aucun fichier reçu.',
            'too_big'              => 'Le fichier est trop volumineux.',
            'not_image'            => 'Ce fichier n’est pas une image valide (JPG, PNG, GIF ou WebP).',
            'too_large_dimensions' => 'Les dimensions de cette image sont excessives.',
            'bad_type'             => 'Ce type de fichier n’est pas accepté.',
            default                => 'Le transfert a échoué. Veuillez réessayer.',
        };
    }
}
