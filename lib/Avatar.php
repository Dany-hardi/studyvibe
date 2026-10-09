<?php
declare(strict_types=1);

require_once __DIR__ . '/MediaStore.php';

/**
 * Profile picture of any account (student, teacher, promoter). One implementation, so every role gets the same checks:
 * the file must really be a picture, it is cleaned and scaled to 512 px, stored in the database as well as on the disk,
 * and the previous picture is removed.
 */
final class Avatar
{
    public const MAX_UPLOAD_BYTES = 6291456;   // 6 MB before scaling

    /** @return array{success:bool,message:string,avatar_path?:string,url?:string} */
    public static function replace(PDO $pdo, int $userId, array $upload): array
    {
        $res = MediaStore::saveImage($pdo, $upload, 'avatar', 512, self::MAX_UPLOAD_BYTES, 86);
        if (!$res['ok']) {
            return ['success' => false, 'message' => MediaStore::message($res['error'] ?? 'server')];
        }
        $new = $res['file'];

        $old = $pdo->prepare('SELECT avatar_path FROM users WHERE id = :id');
        $old->execute(['id' => $userId]);
        $oldFile = (string)$old->fetchColumn();

        $pdo->prepare('UPDATE users SET avatar_path = :p WHERE id = :id')->execute(['p' => $new, 'id' => $userId]);
        if ($oldFile !== '' && $oldFile !== $new) {
            MediaStore::delete($pdo, 'avatar', $oldFile);
        }
        return [
            'success'     => true,
            'message'     => 'Photo mise à jour.',
            'avatar_path' => $new,
            'url'         => '/download.php?type=avatar&file=' . rawurlencode($new),
        ];
    }

    /** Back to the default picture. */
    public static function remove(PDO $pdo, int $userId): array
    {
        $old = $pdo->prepare('SELECT avatar_path FROM users WHERE id = :id');
        $old->execute(['id' => $userId]);
        $oldFile = (string)$old->fetchColumn();
        $pdo->prepare('UPDATE users SET avatar_path = NULL WHERE id = :id')->execute(['id' => $userId]);
        if ($oldFile !== '') {
            MediaStore::delete($pdo, 'avatar', $oldFile);
        }
        return ['success' => true, 'message' => 'Photo supprimée.'];
    }
}
