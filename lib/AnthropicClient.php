<?php
declare(strict_types=1);

/**
 * Structured-answer client used to mark code written in live evaluations. The provider is Gemini when GEMINI_API_KEY is set (the default),
 * Anthropic otherwise or when AI_PROVIDER=anthropic. Both return the same thing: a verdict as structured data.
 * (The class keeps its first name; everything that marks goes through ::structured().)
 *
 * Anthropic Messages API details:
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

    private const GEMINI_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** Tests force a provider; otherwise it follows the configuration. */
    public static ?string $provider = null;

    public static function provider(): string
    {
        if (self::$provider !== null) {
            return self::$provider;
        }
        $p = defined('AI_PROVIDER') ? strtolower(trim((string)AI_PROVIDER)) : '';
        if ($p === 'gemini' || $p === 'anthropic') {
            return $p;
        }
        return self::geminiKey() !== '' ? 'gemini' : 'anthropic';
    }

    private static function geminiKey(): string
    {
        $k = defined('GEMINI_API_KEY') ? trim((string)GEMINI_API_KEY) : '';
        return ($k === '' || $k === 'votre_cle_api_gemini_ici') ? '' : $k;
    }

    public static function isConfigured(): bool
    {
        if (self::$transport !== null) {
            return true;
        }
        return self::provider() === 'gemini' ? self::geminiKey() !== '' : (defined('ANTHROPIC_API_KEY') && trim((string)ANTHROPIC_API_KEY) !== '');
    }

    /** The Gemini models to try, in order (each has its own quota: when one is full or busy, the next one takes the call). */
    public static function geminiModels(): array
    {
        $raw = defined('GEMINI_MARKING_MODEL') ? (string)GEMINI_MARKING_MODEL : '';
        $out = [];
        foreach (explode(',', $raw) as $m) {
            $m = trim($m);
            if (preg_match('/^[a-z0-9._-]{3,60}$/i', $m)) {
                $out[] = $m;
            }
        }
        return $out ?: ['gemini-2.5-flash'];
    }

    public static function model(): string
    {
        if (self::provider() === 'gemini') {
            return self::geminiModels()[0];
        }
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
        if (self::provider() === 'gemini') {
            return self::structuredGemini($system, $user, $schema, $maxTokens, $timeout);
        }
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

    /** Same contract as the Anthropic path: the verdict comes back as JSON that follows the schema, errors are short codes. */
    private static function structuredGemini(string $system, string|array $user, array $schema, int $maxTokens, int $timeout): array
    {
        $parts = [];
        foreach (is_string($user) ? [['type' => 'text', 'text' => $user]] : $user as $b) {
            if (($b['type'] ?? '') === 'text') {
                $parts[] = ['text' => (string)$b['text']];
            } elseif (($b['type'] ?? '') === 'image' && isset($b['source']['data'])) {
                $parts[] = ['inlineData' => ['mimeType' => (string)$b['source']['media_type'], 'data' => (string)$b['source']['data']]];
            }
        }
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => max($maxTokens, 8192),     // the thinking of the model is counted in it
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $schema,
            ],
        ];
        $status = 0; $body = ''; $used = self::model();
        foreach (self::geminiModels() as $i => $m) {
            $used = $m;
            $p = $payload;
            if (str_starts_with($m, 'gemini-2.5-flash')) {
                $p['generationConfig']['thinkingConfig'] = ['thinkingBudget' => 1024];   // enough to trace the code, without 15 seconds of thinking
            }
            [$status, $body] = self::$transport !== null ? (self::$transport)($p) : self::postGemini($p, $timeout, $m);
            // quota full, model busy or retired: the next model of the list takes the call
            if (!in_array($status, [429, 503, 529, 404, 0], true)) {
                break;
            }
        }
        if ($status === 0) {
            return ['ok' => false, 'error' => 'timeout', 'retryable' => true];
        }
        if ($status !== 200) {
            $err = match (true) {
                $status === 429 => 'rate_limited',
                $status === 503 || $status === 529 => 'overloaded',
                $status === 401 || $status === 403 || ($status === 400 && stripos($body, 'API key') !== false) => 'auth',
                $status >= 500 => 'server',
                default => 'bad_request',
            };
            $res = ['ok' => false, 'error' => $err, 'retryable' => in_array($err, ['rate_limited', 'overloaded', 'server'], true)];
            if ($status === 429 && preg_match('/"retryDelay"\s*:\s*"(\d+(?:\.\d+)?)s"/', $body, $mm)) {
                $res['retry_after'] = (int)ceil((float)$mm[1]);      // the API says when the quota is back
            }
            return $res;
        }
        $json = json_decode($body, true);
        $text = '';
        foreach ((array)($json['candidates'][0]['content']['parts'] ?? []) as $p) {
            if (isset($p['text']) && empty($p['thought'])) {
                $text .= (string)$p['text'];
            }
        }
        $data = json_decode(trim($text), true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'bad_answer', 'retryable' => true];
        }
        return ['ok' => true, 'data' => $data, 'model' => (string)($json['modelVersion'] ?? $used)];
    }

    /** @return array{0:int,1:string} */
    private static function postGemini(array $payload, int $timeout, string $model): array
    {
        $ch = curl_init(self::GEMINI_ENDPOINT . rawurlencode($model) . ':generateContent');
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
            // the key goes in a header, never in the URL (URLs end up in logs)
            CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-goog-api-key: ' . self::geminiKey()],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch);
        return [$body === false ? 0 : $status, $body === false ? '' : (string)$body];
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
