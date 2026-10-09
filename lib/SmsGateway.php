<?php
declare(strict_types=1);

/**
 * Sends one text message through the provider chosen in .env (SMS_DRIVER).
 *
 *   log            writes the message to uploads/sms_log/ instead of sending it. Developer machines only: it is refused
 *                  unless APP_DEBUG=true, because that log would contain verification codes.
 *   twilio         TWILIO_SID, TWILIO_TOKEN and TWILIO_FROM (or TWILIO_MESSAGING_SERVICE_SID)
 *   africastalking AT_USERNAME and AT_API_KEY (SMS_SENDER_ID if the account has a registered sender name)
 *
 * send() never throws and never blocks longer than 15 seconds. It returns ['ok' => bool, 'id' => provider message id,
 * 'error' => short reason, 'retry' => whether trying again later may work].
 */
final class SmsGateway
{
    /** @return array{ok:bool,id?:string,error?:string,retry?:bool} */
    public static function send(string $toE164, string $body): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'sms disabled', 'retry' => false];
        }
        if (preg_match('/^\+[1-9]\d{7,14}$/', $toE164) !== 1) {
            return ['ok' => false, 'error' => 'invalid number', 'retry' => false];
        }
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 640) {
            return ['ok' => false, 'error' => 'invalid body', 'retry' => false];
        }
        return match (strtolower((string)SMS_DRIVER)) {
            'twilio'         => self::twilio($toE164, $body),
            'africastalking' => self::africasTalking($toE164, $body),
            'log'            => self::log($toE164, $body),
            default          => ['ok' => false, 'error' => 'unknown SMS_DRIVER', 'retry' => false],
        };
    }

    /** The master switch (FEATURE_SMS in .env). When off, nothing in the app asks for or uses phone numbers. */
    public static function enabled(): bool
    {
        return defined('FEATURE_SMS') && FEATURE_SMS === 'true';
    }

    /** True when a real provider (or the developer log) is configured, so the app can tell users SMS is unavailable. */
    public static function isConfigured(): bool
    {
        if (!self::enabled()) {
            return false;
        }
        return match (strtolower((string)SMS_DRIVER)) {
            'twilio'         => TWILIO_SID !== '' && TWILIO_TOKEN !== '' && (TWILIO_FROM !== '' || TWILIO_MESSAGING_SERVICE_SID !== ''),
            'africastalking' => AT_USERNAME !== '' && AT_API_KEY !== '',
            'log'            => APP_DEBUG === 'true',
            default          => false,
        };
    }

    /**
     * Turns accented letters into plain ones so a message stays in the cheap 160-character GSM alphabet
     * (one accent anywhere switches the whole message to 70 characters per part).
     */
    public static function plain(string $text): string
    {
        $text = strtr($text, [
            '’' => "'", '‘' => "'", '“' => '"', '”' => '"', '«' => '"', '»' => '"', '–' => '-', '—' => '-', '…' => '...', "\u{202F}" => ' ', "\u{00A0}" => ' ',
        ]);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $out = $ascii === false ? preg_replace('/[^\x20-\x7E\n]/', '', $text) : $ascii;
        return trim(preg_replace('/[^\x20-\x7E\n]/', '', (string)$out) ?? '');
    }

    private static function log(string $to, string $body): array
    {
        if (APP_DEBUG !== 'true') {
            return ['ok' => false, 'error' => 'SMS provider not configured', 'retry' => false];
        }
        $dir = dirname(__DIR__) . '/uploads/sms_log';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $id = 'log-' . bin2hex(random_bytes(4));
        $line = json_encode(['at' => date('c'), 'id' => $id, 'to' => $to, 'body' => $body], JSON_UNESCAPED_UNICODE) . "\n";
        $ok = @file_put_contents($dir . '/' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX) !== false;
        return $ok ? ['ok' => true, 'id' => $id] : ['ok' => false, 'error' => 'log not writable', 'retry' => false];
    }

    private static function twilio(string $to, string $body): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'Twilio not configured', 'retry' => false];
        }
        $fields = ['To' => $to, 'Body' => $body];
        if (TWILIO_MESSAGING_SERVICE_SID !== '') {
            $fields['MessagingServiceSid'] = TWILIO_MESSAGING_SERVICE_SID;
        } else {
            $fields['From'] = TWILIO_FROM;
        }
        $res = self::post(
            'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_SID) . '/Messages.json',
            http_build_query($fields),
            [],
            TWILIO_SID . ':' . TWILIO_TOKEN
        );
        if ($res['status'] === 0) {
            return ['ok' => false, 'error' => 'network: ' . $res['error'], 'retry' => true];
        }
        $json = json_decode($res['body'], true) ?: [];
        if ($res['status'] >= 200 && $res['status'] < 300 && !empty($json['sid'])) {
            return ['ok' => true, 'id' => (string)$json['sid']];
        }
        // 4xx: the request itself is wrong (number, credentials, funds): asking again would not help. 5xx and 429 may pass.
        return [
            'ok'    => false,
            'error' => 'twilio ' . $res['status'] . ' ' . substr((string)($json['message'] ?? ''), 0, 120),
            'retry' => $res['status'] >= 500 || $res['status'] === 429,
        ];
    }

    private static function africasTalking(string $to, string $body): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'Africa\'s Talking not configured', 'retry' => false];
        }
        $fields = ['username' => AT_USERNAME, 'to' => $to, 'message' => $body];
        if (SMS_SENDER_ID !== '' && AT_USERNAME !== 'sandbox') {
            $fields['from'] = SMS_SENDER_ID;
        }
        $host = AT_USERNAME === 'sandbox' ? 'api.sandbox.africastalking.com' : 'api.africastalking.com';
        $res = self::post('https://' . $host . '/version1/messaging', http_build_query($fields), ['apiKey: ' . AT_API_KEY, 'Accept: application/json']);
        if ($res['status'] === 0) {
            return ['ok' => false, 'error' => 'network: ' . $res['error'], 'retry' => true];
        }
        $json = json_decode($res['body'], true) ?: [];
        $rec = $json['SMSMessageData']['Recipients'][0] ?? null;
        if ($res['status'] >= 200 && $res['status'] < 300 && is_array($rec) && in_array((int)($rec['statusCode'] ?? 0), [100, 101, 102], true)) {
            return ['ok' => true, 'id' => (string)($rec['messageId'] ?? '')];
        }
        return [
            'ok'    => false,
            'error' => 'at ' . $res['status'] . ' ' . substr((string)($rec['status'] ?? ($json['SMSMessageData']['Message'] ?? '')), 0, 120),
            'retry' => $res['status'] >= 500 || $res['status'] === 429,
        ];
    }

    /** @return array{status:int,body:string,error:string} status 0 means the request never got an answer */
    private static function post(string $url, string $data, array $headers = [], ?string $basicAuth = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        ]);
        if ($basicAuth !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
        }
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        return ['status' => $body === false ? 0 : $status, 'body' => $body === false ? '' : (string)$body, 'error' => $err];
    }
}
