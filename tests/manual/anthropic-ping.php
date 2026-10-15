<?php
declare(strict_types=1);

/**
 * Manual check of the marking request against the real Anthropic API (a few cents at most, never run by the test suites):
 *   php tests/manual/anthropic-ping.php
 * It sends the same kind of request as the marking (system prompt, tool, cached question block, student code), once without and once with a
 * picture, and prints only the outcome: ok or the API's own explanation. The key is read from the environment/.env and never printed.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/LiveCodeReview.php';

echo 'keys: ', count(AiKeyPool::keys()), ' | model: ', AnthropicClient::model(), "\n";
$q = ['question_text' => 'Write a C function int add(int a, int b) that returns a + b.', 'question_type' => 'code', 'code_lang' => 'c',
      'code_reference' => 'int add(int a, int b) { return a + b; }', 'code_notes' => 'Returns the sum.',
      'code' => 'int add(int a, int b) { return a + b; }'];
$png = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png',
        'data' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==']];
foreach (['without picture' => null, 'with picture' => $png] as $label => $img) {
    $r = AnthropicClient::structured(LiveCodeReview::systemPrompt('code'), LiveCodeReview::userBlocks($q, $img), 'submit_review',
        'Report the review of the student answer: verdict, reasoning, feedback and a corrected version.', LiveCodeReview::schema('code'), LiveCodeReview::MAX_OUTPUT_TOKENS);
    echo str_pad($label, 16), ': ', $r['ok'] ? 'OK verdict=' . var_export($r['data']['verdict'] ?? null, true) . ' tokens=' . json_encode($r['usage'] ?? []) : 'FAILED ' . ($r['error'] ?? '?') . ' | ' . ($r['detail'] ?? ''), "\n";
}
