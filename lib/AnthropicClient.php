<?php
declare(strict_types=1);

/**
 * Minimal client for the Anthropic Messages API, used to mark code written in live evaluations.
 *
 * - The key comes from the server environment only (ANTHROPIC_API_KEY). It is never put in a page, a log, an error message or the database.
 * - Only HTTPS to api.anthropic.com; short connect and total timeouts; one call = one request, no automatic retry here (the caller
 *   decides, with a back-off, so a slow API can never pile up requests).
 * - The model is forced to answer through a tool whose input schema is the verdict: the answer is structured data, never free text to run.
 * - Errors are reduced to a short code ("rate_limited", "overloaded", "timeout", "auth", "bad_request", "server", "bad_answer"). The raw
 *   response body is never kept, since it can echo parts of the prompt (a student's code).
 */
final class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const VERSION = '2023-06-01';

    /** Tests replace the network: fn(array $payload): array{0:int,1:string} (HTTP status, body). */
    public static ?\Closure $transport = null;

    public static function isConfigured(): bool
    {
        return self::$transport !== null || (defined('ANTHROPIC_API_KEY') && trim((string)ANTHROPIC_API_KEY) !== '');
    }

    public static function model(): string
    {
        $m = defined('ANTHROPIC_MODEL') ? trim((string)ANTHROPIC_MODEL) : '';
        return preg_match('/^[a-z0-9._-]{3,60}$/i', $m) ? $m : 'claude-sonnet-5-5';
    }

    /**
     * Asks the model to answer by calling one tool.
     *
     * @param string|array $user the message, or a list of content blocks (text and images)
     * @param array $schema JSON schema of the tool input (the structure of the answer)
     * @return array{ok:bool,data?:array,error?:string,model?:string,retryable?:bool}
     */
    public static function structured(string $system, string|array $user, string $toolName, string $toolDescription, array $schema, int $maxTokens = 3000, int $timeout = 75): array
    {
        $payload = [
            'model' => self::model(),
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
            'tools' => [['name' => $toolName, 'description' => $toolDescription, 'input_schema' => $schema]],
            'tool_choice' => ['type' => 'tool', 'name' => $toolName],
        ];
        [$status, $body] = self::$transport !== null ? (self::$transport)($payload) : self::post($payload, $timeout);

        if ($status === 0) {
            return ['ok' => false, 'error' => 'timeout', 'retryable' => true];
        }
        if ($status !== 200) {
            $err = match (true) {
                $status === 400 && stripos($body, 'credit balance') !== false => 'billing',   // no credit left on the account: waits for the top-up
                $status === 429 => 'rate_limited',
                $status === 529 => 'overloaded',
                $status === 401 || $status === 403 => 'auth',
                $status >= 500 => 'server',
                default => 'bad_request',
            };
            return ['ok' => false, 'error' => $err, 'retryable' => in_array($err, ['rate_limited', 'overloaded', 'server', 'billing'], true)];
        }
        $json = json_decode($body, true);
        foreach ((array)($json['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === $toolName && is_array($block['input'] ?? null)) {
                return ['ok' => true, 'data' => $block['input'], 'model' => (string)($json['model'] ?? self::model())];
            }
        }
        return ['ok' => false, 'error' => 'bad_answer', 'retryable' => true];
    }

    /** @return array{0:int,1:string} */
    private static function post(array $payload, int $timeout): array
    {
        $key = trim((string)ANTHROPIC_API_KEY);
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['content-type: application/json', 'anthropic-version: ' . self::VERSION, 'x-api-key: ' . $key],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch);
        return [$body === false ? 0 : $status, $body === false ? '' : (string)$body];
    }
}
