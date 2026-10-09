<?php
declare(strict_types=1);

require_once __DIR__ . '/Security.php';

/**
 * Time-based one-time passwords (RFC 6238), the codes shown by Google Authenticator, Microsoft Authenticator, Authy, 1Password...
 * 6 digits, 30-second steps, HMAC-SHA1 (the only algorithm every authenticator app supports).
 *
 * The secret of an account is stored encrypted (libsodium secretbox, key derived from APP_SECRET), so a copy of the
 * database alone is not enough to generate someone's codes.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const STEP = 30;
    public const DIGITS = 6;

    public static function newSecret(): string
    {
        return self::base32Encode(random_bytes(20));   // 160 bits, as recommended
    }

    public static function code(string $base32Secret, int $step): string
    {
        $key = self::base32Decode($base32Secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);   // 'J' = 64-bit big-endian counter
        $offset = ord($hash[19]) & 0x0F;
        $bin = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string)($bin % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Checks a code against the current step and one step either side (clock drift of a phone). A step that was already
     * accepted ($lastUsedStep) is refused, so a code seen over somebody's shoulder cannot be used a second time.
     *
     * @return int|null the step that matched (store it as the new "last used step"), or null
     */
    public static function verify(string $base32Secret, string $code, int $lastUsedStep = 0, ?int $now = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }
        $current = intdiv($now ?? time(), self::STEP);
        $found = null;
        for ($i = -1; $i <= 1; $i++) {
            $step = $current + $i;
            // Always compare all three candidates, so the time taken does not tell which one matched
            if (hash_equals(self::code($base32Secret, $step), $code) && $step > $lastUsedStep) {
                $found = $step;
            }
        }
        return $found;
    }

    /** The otpauth:// address an authenticator app reads (from the QR code, or by tapping it on a phone). */
    public static function uri(string $base32Secret, string $account, string $issuer = 'StudyVibe'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $base32Secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::STEP;
    }

    // ---- storage -----------------------------------------------------------------------------------------------

    public static function encrypt(string $base32Secret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($base32Secret, $nonce, self::key()));
    }

    public static function decrypt(string $stored): ?string
    {
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key()
        );
        return $plain === false ? null : $plain;
    }

    private static function key(): string
    {
        return hash('sha256', 'studyvibe|totp|' . APP_SECRET, true);
    }

    // ---- base32 ------------------------------------------------------------------------------------------------

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($b32, '='))) as $c) {
            $p = strpos(self::ALPHABET, $c);
            if ($p === false) {
                continue;
            }
            $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
