<?php
declare(strict_types=1);

require_once __DIR__ . '/Phone.php';
require_once __DIR__ . '/RateLimit.php';
require_once __DIR__ . '/SmsQueue.php';
require_once __DIR__ . '/Security.php';

/**
 * Proving that a phone number belongs to the signed-in user, with a 6-digit code sent by SMS.
 *
 * What protects it:
 *   - the code comes from random_int (not guessable), is kept only as an HMAC, expires after 10 minutes, and dies after
 *     5 wrong tries or after one success; asking for a new code cancels the old one
 *   - sending is limited per account (1 per minute, 5 per hour), per number (5 per hour) and per address (15 per hour), so
 *     the feature cannot be used to flood somebody's phone or to run up the SMS bill
 *   - checking is limited too (10 wrong codes per hour per account), on top of the 5 tries per code
 *   - a number is saved on the account only after a correct code, and a number can belong to one account only
 *   - the code is never written to the database or the logs in clear
 */
final class Otp
{
    public const PURPOSE_PHONE = 'phone_verify';
    private const TTL_MINUTES = 10;
    private const MAX_TRIES = 5;

    /**
     * @return array{ok:bool,error?:string,retry_after?:int,phone_masked?:string,dev_code?:string,expires_in?:int}
     */
    public static function start(PDO $pdo, int $userId, string $rawPhone, string $ip, string $lang = 'fr'): array
    {
        $phone = Phone::normalize($rawPhone);
        if ($phone === null) {
            return ['ok' => false, 'error' => 'phone_invalid'];
        }
        // Codes are hashed with APP_SECRET: with the placeholder secret anybody could precompute them, so refuse outside dev
        if (!SmsGateway::isConfigured() || (!Security::secretIsStrong() && APP_DEBUG !== 'true')) {
            return ['ok' => false, 'error' => 'sms_unavailable'];
        }

        $taken = $pdo->prepare("SELECT id FROM users WHERE phone_e164 = :p AND id <> :u LIMIT 1");
        $taken->execute(['p' => $phone, 'u' => $userId]);
        if ($taken->fetchColumn()) {
            return ['ok' => false, 'error' => 'phone_taken'];
        }

        if (!RateLimit::allow($pdo, "otp:user:$userId:min", 1, 60)) {
            return ['ok' => false, 'error' => 'too_soon', 'retry_after' => RateLimit::retryAfter($pdo, "otp:user:$userId:min", 60)];
        }
        if (!RateLimit::allow($pdo, "otp:user:$userId:hour", 5, 3600)
            || !RateLimit::allow($pdo, 'otp:phone:' . hash('sha256', $phone), 5, 3600)
            || !RateLimit::allow($pdo, 'otp:ip:' . hash('sha256', $ip), 15, 3600)) {
            return ['ok' => false, 'error' => 'too_many', 'retry_after' => 600];
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE otp_codes SET consumed_at = NOW() WHERE user_id = :u AND purpose = :p AND consumed_at IS NULL")
            ->execute(['u' => $userId, 'p' => self::PURPOSE_PHONE]);
        $pdo->prepare(
            "INSERT INTO otp_codes (user_id, purpose, phone, code_hash, expires_at)
             VALUES (:u, :p, :ph, :h, NOW() + INTERVAL " . self::TTL_MINUTES . " MINUTE)"
        )->execute(['u' => $userId, 'p' => self::PURPOSE_PHONE, 'ph' => $phone, 'h' => self::hash($userId, $code)]);
        $otpId = (int)$pdo->lastInsertId();
        $pdo->commit();

        $text = $lang === 'en'
            ? "StudyVibe: your verification code is $code. It expires in " . self::TTL_MINUTES . " minutes. Never share it with anyone."
            : "StudyVibe : votre code de verification est $code. Il expire dans " . self::TTL_MINUTES . " minutes. Ne le partagez jamais.";
        $res = SmsQueue::sendNow($pdo, $userId, $phone, 'otp', $text, 'Verification code (not stored)', $ip);

        if (!$res['ok']) {
            $pdo->prepare("UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id")->execute(['id' => $otpId]);
            return ['ok' => false, 'error' => 'sms_failed'];
        }
        $pdo->prepare("UPDATE users SET phone_pending = :p WHERE id = :u")->execute(['p' => $phone, 'u' => $userId]);

        $out = ['ok' => true, 'phone_masked' => Phone::mask($phone), 'expires_in' => self::TTL_MINUTES * 60];
        // Only on a developer machine with the log driver: lets the screens be tried without a phone
        if (APP_DEBUG === 'true' && strtolower((string)SMS_DRIVER) === 'log') {
            $out['dev_code'] = $code;
        }
        return $out;
    }

    /** @return array{ok:bool,error?:string,tries_left?:int,phone?:string,phone_masked?:string} */
    public static function verify(PDO $pdo, int $userId, string $code): array
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return ['ok' => false, 'error' => 'code_format'];
        }
        if (!RateLimit::allow($pdo, "otpv:user:$userId", 10, 3600)) {
            return ['ok' => false, 'error' => 'too_many'];
        }

        $st = $pdo->prepare(
            "SELECT id, phone, code_hash, attempts, (expires_at < NOW()) AS expired
             FROM otp_codes WHERE user_id = :u AND purpose = :p AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1"
        );
        $st->execute(['u' => $userId, 'p' => self::PURPOSE_PHONE]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'no_code'];
        }
        if ((int)$row['expired'] === 1) {
            $pdo->prepare("UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id")->execute(['id' => $row['id']]);
            return ['ok' => false, 'error' => 'expired'];
        }
        if ((int)$row['attempts'] >= self::MAX_TRIES) {
            $pdo->prepare("UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id")->execute(['id' => $row['id']]);
            return ['ok' => false, 'error' => 'too_many_tries'];
        }

        if (!hash_equals((string)$row['code_hash'], self::hash($userId, $code))) {
            $pdo->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id")->execute(['id' => $row['id']]);
            $left = self::MAX_TRIES - (int)$row['attempts'] - 1;
            if ($left <= 0) {
                $pdo->prepare("UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id")->execute(['id' => $row['id']]);
                return ['ok' => false, 'error' => 'too_many_tries'];
            }
            return ['ok' => false, 'error' => 'code_wrong', 'tries_left' => $left];
        }

        // Right code: burn it first (so it cannot be replayed), then attach the number to the account
        $burn = $pdo->prepare("UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id AND consumed_at IS NULL");
        $burn->execute(['id' => $row['id']]);
        if ($burn->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'no_code'];   // another request used it a moment ago
        }
        try {
            $pdo->prepare(
                "UPDATE users SET phone_e164 = :p, phone = :p2, phone_verified_at = NOW(), phone_pending = NULL WHERE id = :u"
            )->execute(['p' => $row['phone'], 'p2' => $row['phone'], 'u' => $userId]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {   // the unique index: somebody else verified this number first
                return ['ok' => false, 'error' => 'phone_taken'];
            }
            throw $e;
        }
        RateLimit::reset($pdo, "otpv:user:$userId");
        return ['ok' => true, 'phone' => (string)$row['phone'], 'phone_masked' => Phone::mask((string)$row['phone'])];
    }

    private static function hash(int $userId, string $code): string
    {
        return hash_hmac('sha256', $code, APP_SECRET . '|otp|' . $userId);
    }

    public static function message(string $code, string $lang = 'fr'): string
    {
        $m = [
            'phone_invalid'  => ['Ce numéro de téléphone n’est pas valide. Exemple : 6 12 34 56 78 ou +237 612 345 678.', 'This phone number is not valid. Example: 6 12 34 56 78 or +237 612 345 678.'],
            'phone_taken'    => ['Ce numéro n’est pas disponible. Utilisez un autre numéro.', 'This number is not available. Please use another number.'],
            'sms_unavailable'=> ['L’envoi de SMS est momentanément indisponible. Réessayez plus tard.', 'SMS sending is temporarily unavailable. Please try again later.'],
            'sms_failed'     => ['Le SMS n’a pas pu être envoyé. Vérifiez le numéro et réessayez.', 'The SMS could not be sent. Check the number and try again.'],
            'too_soon'       => ['Un code vient d’être envoyé. Patientez un instant avant d’en demander un autre.', 'A code was just sent. Please wait a moment before asking for another.'],
            'too_many'       => ['Trop de tentatives. Réessayez dans un moment.', 'Too many attempts. Please try again in a while.'],
            'code_format'    => ['Le code contient 6 chiffres.', 'The code has 6 digits.'],
            'no_code'        => ['Aucun code en attente. Demandez un nouveau code.', 'No code is waiting. Please ask for a new one.'],
            'expired'        => ['Ce code a expiré. Demandez-en un nouveau.', 'This code has expired. Please ask for a new one.'],
            'too_many_tries' => ['Trop d’essais avec ce code. Demandez-en un nouveau.', 'Too many tries with this code. Please ask for a new one.'],
            'code_wrong'     => ['Code incorrect.', 'Incorrect code.'],
        ];
        return ($m[$code] ?? ['Une erreur est survenue.', 'Something went wrong.'])[$lang === 'en' ? 1 : 0];
    }
}
