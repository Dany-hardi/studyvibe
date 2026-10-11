<?php
declare(strict_types=1);

/**
 * Web Push without any library: VAPID (RFC 8292) and message encryption "aes128gcm" (RFC 8291) on top of PHP's openssl.
 *
 * Keys live in the environment: VAPID_PRIVATE_PEM_B64 (the private key, PEM, base64 on one line) and VAPID_SUBJECT (mailto: or https:).
 * `php scripts/generate-vapid.php` prints both. The public key shown to browsers is derived from the private one.
 */
final class WebPush
{
    /** Tests replace the network: fn(string $endpoint, array $headers, string $body): array{status:int, error?:string} */
    public static $transport = null;

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64u(string $s): string
    {
        return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    public static function isConfigured(): bool
    {
        return self::privateKey() !== null;
    }

    /** @return \OpenSSLAsymmetricKey|null */
    private static function privateKey()
    {
        $b64 = defined('VAPID_PRIVATE_PEM_B64') ? (string)VAPID_PRIVATE_PEM_B64 : (string)(getenv('VAPID_PRIVATE_PEM_B64') ?: '');
        if ($b64 === '') {
            return null;
        }
        $pem = base64_decode($b64, true);
        $key = $pem ? @openssl_pkey_get_private($pem) : false;
        return $key ?: null;
    }

    /** Uncompressed point (65 bytes) of a key, from its details. */
    private static function rawPublic($key): string
    {
        $d = openssl_pkey_get_details($key);
        return "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** The public key browsers need (base64url of the 65-byte point), or '' when push is not set up. */
    public static function publicKey(): string
    {
        $k = self::privateKey();
        return $k ? self::b64u(self::rawPublic($k)) : '';
    }

    /** @return array{pem_b64:string, public:string} a fresh VAPID key pair */
    public static function generateKeys(): array
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($k, $pem);
        return ['pem_b64' => base64_encode($pem), 'public' => self::b64u(self::rawPublic($k))];
    }

    /** Signed token that tells the push service who is sending (audience = origin of the endpoint). */
    public static function vapidJwt(string $audience, ?int $exp = null): string
    {
        $key = self::privateKey();
        if (!$key) {
            throw new RuntimeException('VAPID keys are not configured');
        }
        $subject = defined('VAPID_SUBJECT') && VAPID_SUBJECT !== '' ? (string)VAPID_SUBJECT : (string)(getenv('VAPID_SUBJECT') ?: 'mailto:admin@studyvibe.edu');
        $in = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.'
            . self::b64u(json_encode(['aud' => $audience, 'exp' => $exp ?? time() + 12 * 3600, 'sub' => $subject]));
        openssl_sign($in, $der, $key, OPENSSL_ALGO_SHA256);
        return $in . '.' . self::b64u(self::derToRaw($der));
    }

    /** ECDSA signatures come out of openssl as DER; JWT wants R||S, 32 bytes each. */
    private static function derToRaw(string $der): string
    {
        $o = 2 + (ord($der[1]) & 0x80 ? (ord($der[1]) & 0x7f) : 0);
        $rl = ord($der[$o + 1]);
        $r = substr($der, $o + 2, $rl);
        $o2 = $o + 2 + $rl;
        $sl = ord($der[$o2 + 1]);
        $s = substr($der, $o2 + 2, $sl);
        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    /** SubjectPublicKeyInfo around a raw P-256 point, so openssl accepts the browser's public key. */
    private static function spki(string $raw65): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw65;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Encrypts $payload for one subscription (RFC 8291). $ephemeral and $salt exist for the tests; leave them null.
     * @return string the request body: salt(16) | record size(4) | key id length(1) | sender public key(65) | ciphertext
     */
    public static function encrypt(string $payload, string $uaPublicB64u, string $authB64u, $ephemeral = null, ?string $salt = null): string
    {
        $ua = self::unb64u($uaPublicB64u);
        $auth = self::unb64u($authB64u);
        if (strlen($ua) !== 65 || $ua[0] !== "\x04" || strlen($auth) < 16) {
            throw new InvalidArgumentException('Invalid subscription keys');
        }
        $eph = $ephemeral ?: openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $asPublic = self::rawPublic($eph);
        $secret = openssl_pkey_derive(openssl_pkey_get_public(self::spki($ua)), $eph);
        if ($secret === false) {
            throw new RuntimeException('ECDH failed');
        }
        $salt = $salt ?? random_bytes(16);
        $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $ua . $asPublic, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = $payload . "\x02";                         // one record, so the last one: delimiter 0x02
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
    }

    /**
     * Sends one message. $sub needs endpoint, p256dh, auth.
     * @return array{status:int, ok:bool, gone:bool, error:?string}  gone = the browser dropped the subscription (404/410): delete it
     */
    public static function send(array $sub, string $payload, int $ttl = 86400, string $urgency = 'normal'): array
    {
        try {
            if (!self::isConfigured() && self::$transport === null) {
                return ['status' => 0, 'ok' => false, 'gone' => false, 'error' => 'VAPID keys are not configured'];
            }
            $body = self::encrypt($payload, (string)$sub['p256dh'], (string)$sub['auth']);
            $p = parse_url((string)$sub['endpoint']);
            if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host'])) {
                return ['status' => 0, 'ok' => false, 'gone' => true, 'error' => 'Invalid endpoint'];
            }
            $aud = 'https://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
            $headers = [
                'Content-Type' => 'application/octet-stream', 'Content-Encoding' => 'aes128gcm', 'Content-Length' => (string)strlen($body),
                'TTL' => (string)$ttl, 'Urgency' => $urgency,
                'Authorization' => 'vapid t=' . self::vapidJwt($aud) . ', k=' . self::publicKey(),
            ];
            $r = self::$transport ? (self::$transport)((string)$sub['endpoint'], $headers, $body) : self::post((string)$sub['endpoint'], $headers, $body);
            $st = (int)($r['status'] ?? 0);
            return ['status' => $st, 'ok' => $st >= 200 && $st < 300, 'gone' => $st === 404 || $st === 410, 'error' => $r['error'] ?? null];
        } catch (Throwable $e) {
            return ['status' => 0, 'ok' => false, 'gone' => false, 'error' => $e->getMessage()];
        }
    }

    private static function post(string $url, array $headers, string $body): array
    {
        $h = [];
        foreach ($headers as $k => $v) { $h[] = $k . ': ' . $v; }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5]);
        curl_exec($ch);
        $st = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch) ?: null;
        curl_close($ch);
        return ['status' => $st, 'error' => $err];
    }
}
