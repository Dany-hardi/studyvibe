<?php
declare(strict_types=1);

/**
 * Client of the Anthropic Messages API, used ONLY to mark code and written answers in live evaluations (structured verdicts).
 * The lesson assistants do not use it: they run on Gemini (see GeminiClient).
 *
 * Spending tokens wisely:
 *  - prompt caching: the tool schema, the system prompt, and for marking the whole question (figure, statement, reference, notes) are a cached
 *    prefix shared by every student; only the student's answer is new, and it comes last;
 *  - the model answers with a tool call whose schema keeps the verdict short; the output limit is tight (no sampling parameter is sent: recent models refuse them);
 *  - marking uses the strong model (ANTHROPIC_MODEL);
 *  - the token usage of every call is returned so it can be stored and shown.
 * Rate limiting: calls go through a pool of lanes (one per key and model, see AiKeyPool) with a per-minute budget (ANTHROPIC_RPM); the rate-limit
 * headers of every answer and the Retry-After header put a lane to rest exactly as long as needed, so a full quota is never hammered.
 * Safety: the key comes from the server environment only and is never put in a page, a log, an error message or the database; only HTTPS to
 * api.anthropic.com; short timeouts; no automatic retry beyond moving to the next lane (the caller decides, with a back-off); errors are reduced
 * to a short code, and the raw body is never kept since it can echo parts of a prompt.
 */
final class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const VERSION = '2023-06-01';

    /** Tests replace the network: fn(array $payload, string $keyHash, string $model): array{0:int,1:string,2?:array} (status, body, headers). */
    public static ?\Closure $transport = null;
    /** Tests: the models (null = the configuration) */
    public static ?array $modelsOverride = null;

    public static function isConfigured(): bool
    {
        require_once __DIR__ . '/AiKeyPool.php';
        return self::$transport !== null || AiKeyPool::keys() !== [];
    }

    /** The marking models, in order of preference (ANTHROPIC_MODEL, comma separated). */
    public static function models(string $role = 'mark'): array
    {
        if (self::$modelsOverride !== null) {
            return self::$modelsOverride;
        }
        $out = [];
        foreach (explode(',', defined('ANTHROPIC_MODEL') ? (string)ANTHROPIC_MODEL : '') as $m) {
            $m = trim($m);
            if (preg_match('/^[a-z0-9._-]{3,60}$/i', $m)) {
                $out[] = $m;
            }
        }
        return $out ?: ['claude-sonnet-5-5'];
    }

    public static function model(string $role = 'mark'): string
    {
        return self::models($role)[0];
    }

    /**
     * Asks the model to answer by calling one tool.
     *
     * @param string|array $system the system prompt (cached)
     * @param string|array $user the message, or a list of content blocks (text, images); a block with 'cache_control' ends the cached prefix
     * @param array $schema JSON schema of the tool input (the structure of the answer)
     * @return array{ok:bool,data?:array,error?:string,model?:string,retryable?:bool,retry_after?:int,usage?:array}
     */
    public static function structured(string|array $system, string|array $user, string $toolName, string $toolDescription, array $schema, int $maxTokens = 1600, int $timeout = 75, string $role = 'mark'): array
    {
        $payload = [
            'max_tokens' => $maxTokens,
            'system' => self::systemBlocks($system),
            'messages' => [['role' => 'user', 'content' => $user]],
            'tools' => [['name' => $toolName, 'description' => $toolDescription, 'input_schema' => $schema]],
            'tool_choice' => ['type' => 'tool', 'name' => $toolName],
        ];
        $r = self::call($payload, $role, $timeout);
        if (!$r['ok']) {
            return $r;
        }
        foreach ((array)($r['json']['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === $toolName && is_array($block['input'] ?? null)) {
                return ['ok' => true, 'data' => $block['input'], 'model' => (string)($r['json']['model'] ?? $r['model']), 'usage' => $r['usage']];
            }
        }
        return ['ok' => false, 'error' => 'bad_answer', 'retryable' => true, 'usage' => $r['usage']];
    }

    /** The system prompt as a cached block (a prompt that is too short to be cached is simply sent as is). */
    private static function systemBlocks(string|array $system): array
    {
        return is_array($system) ? $system : [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]];
    }

    /** One call through the balancer: next lane with room, then the lane's answer decides how long it rests. */
    private static function call(array $payload, string $role, int $timeout): array
    {
        require_once __DIR__ . '/AiKeyPool.php';
        $pdo = null;
        if (self::$transport === null || AiKeyPool::$trackWithTransport) {
            try { $pdo = Database::getInstance(); } catch (Throwable $e) { /* no database (a script): the first key and model are used */ }
        }
        $models = self::models($role);
        $lanes = max(1, count(AiKeyPool::keys()) * count($models));
        $status = 0; $body = ''; $headers = []; $model = $models[0]; $lastRetry = null; $lastOutcome = 'busy';
        for ($i = 0; $i < $lanes; $i++) {
            if ($pdo) {
                $lane = AiKeyPool::acquire($pdo, $models);
                if (isset($lane['wait'])) {
                    return ['ok' => false, 'error' => 'rate_limited', 'retryable' => true, 'retry_after' => $lane['wait']];
                }
            } else {
                $lane = ['key' => AiKeyPool::keys()[0] ?? 'sk-ant-local-test-key-000000', 'model' => $models[0], 'id' => 0];
            }
            $model = $lane['model'];
            $p = $payload; $p['model'] = $model;
            [$status, $body, $headers] = self::$transport !== null
                ? ((self::$transport)($p, AiKeyPool::hash($lane['key']), $model) + [2 => []])
                : self::post($p, $timeout, $lane['key']);
            [$outcome, $rest] = self::classify((int)$status, (string)$body, (array)$headers);
            $lastOutcome = $outcome;
            if ($rest !== null && $outcome === 'rate') { $lastRetry = $rest; }
            if ($pdo && $lane['id'] > 0) {
                try { AiKeyPool::report($pdo, $lane['id'], $outcome, $rest); } catch (Throwable $e) { error_log('ai key pool: ' . $e->getMessage()); }
            }
            if ($outcome === 'ok' || $outcome === 'ok_rest') {
                break;          // an answer, good or a request we got wrong: nothing the next lane could change
            }
        }
        $json = json_decode((string)$body, true);
        $usage = self::usage(is_array($json) ? $json : []);
        if ($status === 200) {
            return ['ok' => true, 'json' => is_array($json) ? $json : [], 'usage' => $usage, 'model' => $model];
        }
        if ($status === 0) {
            return ['ok' => false, 'error' => 'timeout', 'retryable' => true];
        }
        $err = match (true) {
            $status === 400 && stripos((string)$body, 'credit balance') !== false => 'billing',     // no credit left: waits for the top-up
            $status === 429 => 'rate_limited',
            $status === 529 || $status === 503 => 'overloaded',
            $status === 401 || $status === 403 => 'auth',
            $status >= 500 => 'server',
            default => 'bad_request',
        };
        $res = ['ok' => false, 'error' => $err, 'retryable' => in_array($err, ['rate_limited', 'overloaded', 'server', 'billing'], true)];
        if ($err === 'bad_request' && is_array($json) && is_string($json['error']['message'] ?? null)) {
            // the API's own explanation of what it refused (a parameter, a size...), short; it never contains the prompt
            $res['detail'] = mb_substr(trim(preg_replace('/\s+/', ' ', $json['error']['message'])), 0, 160);
        }
        if ($lastRetry !== null) {
            $res['retry_after'] = $lastRetry;
        }
        return $res;
    }

    /** @return array{0:string,1:?int} the outcome of a lane (ok | rate | busy | retired | auth | billing) and how long it should rest (seconds) */
    private static function classify(int $status, string $body, array $headers): array
    {
        if ($status === 200) {
            // the quota is nearly used up: the lane rests until it is back, instead of earning a 429
            $rest = null;
            foreach (['requests', 'input-tokens', 'output-tokens'] as $kind) {
                $left = $headers['anthropic-ratelimit-' . $kind . '-remaining'] ?? null;
                $reset = $headers['anthropic-ratelimit-' . $kind . '-reset'] ?? null;
                $low = $kind === 'requests' ? 0 : 1500;
                if ($left !== null && is_numeric($left) && (int)$left <= $low && $reset !== null && ($t = strtotime((string)$reset)) !== false) {
                    $rest = max($rest ?? 0, min(300, max(1, $t - time())));
                }
            }
            return $rest !== null ? ['ok_rest', $rest] : ['ok', null];
        }
        if ($status === 429) {
            $after = isset($headers['retry-after']) && is_numeric($headers['retry-after']) ? (int)ceil((float)$headers['retry-after']) : 60;
            return ['rate', max(1, min(900, $after))];
        }
        if ($status === 400 && stripos($body, 'credit balance') !== false) {
            return ['billing', null];
        }
        if ($status === 0 || $status === 529 || $status === 503 || $status >= 500) {
            return ['busy', null];
        }
        if ($status === 404) {
            return ['retired', null];
        }
        if ($status === 401 || $status === 403) {
            return ['auth', null];
        }
        return ['ok', null];     // a request our side got wrong says nothing about the lane
    }

    private static function usage(array $json): array
    {
        $u = (array)($json['usage'] ?? []);
        return ['in' => (int)($u['input_tokens'] ?? 0), 'out' => (int)($u['output_tokens'] ?? 0),
                'cache_read' => (int)($u['cache_read_input_tokens'] ?? 0), 'cache_write' => (int)($u['cache_creation_input_tokens'] ?? 0)];
    }

    /** @return array{0:int,1:string,2:array} status (0 = network failure), body, response headers (lower case) */
    private static function post(array $payload, int $timeout, string $key): array
    {
        $headers = [];
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
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$headers): int {
                $p = strpos($line, ':');
                if ($p !== false) { $headers[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1)); }
                return strlen($line);
            },
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch);
        return [$body === false ? 0 : $status, $body === false ? '' : (string)$body, $headers];
    }
}
