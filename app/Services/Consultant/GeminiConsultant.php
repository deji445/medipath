<?php

namespace App\Services\Consultant;

use App\Services\Ai\AiCircuitBreaker;
use App\Services\Ai\AiRequestRecorder;
use App\Services\Assessment\DeterministicEmergencyScreener;
use App\Services\Assessment\EmergencyFirstAidGuidance;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

final class GeminiConsultant
{
    public function __construct(
        private readonly DeterministicEmergencyScreener $emergencyScreener,
        private readonly EmergencyFirstAidGuidance $firstAidGuidance,
        private readonly AiRequestRecorder $requestRecorder,
        private readonly AiCircuitBreaker $circuitBreaker,
    ) {}

    /** @param array<int, array{role: string, content: string}> $messages */
    public function quickReply(array $messages, string $locale = 'en'): ConsultantReply
    {
        $emergencyReply = $this->emergencyReply($messages, $locale);

        if ($emergencyReply !== null) {
            return $emergencyReply;
        }

        $startedAt = hrtime(true);
        $attemptCount = 0;
        $model = (string) config('medipath.assessment.gemini.consultant_model');

        try {
            $this->circuitBreaker->beforeRequest();
            $apiKey = (string) config('medipath.assessment.gemini.api_key');

            if ($apiKey === '') {
                throw new RuntimeException('GEMINI_API_KEY_MISSING');
            }

            $endpoint = str_replace(
                '{model}',
                rawurlencode($model),
                (string) config('medipath.assessment.gemini.consultant_endpoint'),
            );
            $response = null;
            $lastConnectionException = null;
            $maxAttempts = max(1, min(
                (int) config('medipath.assessment.gemini.consultant_max_attempts', 2),
                3,
            ));

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $attemptCount = $attempt;

                try {
                    $response = Http::acceptJson()
                        ->asJson()
                        ->withHeaders(['x-goog-api-key' => $apiKey])
                        ->connectTimeout((int) config('medipath.assessment.gemini.connect_timeout', 5))
                        ->timeout($this->replyTimeoutForAttempt($attempt))
                        ->post($endpoint, [
                            'systemInstruction' => [
                                'parts' => [['text' => $this->systemInstruction($locale)]],
                            ],
                            'contents' => array_map(
                                fn (array $message): array => [
                                    'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                                    'parts' => [['text' => $message['content']]],
                                ],
                                $messages,
                            ),
                            'generationConfig' => [
                                'maxOutputTokens' => max(80, min(
                                    (int) config('medipath.assessment.gemini.consultant_max_output_tokens', 320),
                                    500,
                                )),
                                'thinkingConfig' => [
                                    'thinkingLevel' => (string) config(
                                        'medipath.assessment.gemini.consultant_thinking_level',
                                        'minimal',
                                    ),
                                ],
                            ],
                        ]);
                } catch (ConnectionException $exception) {
                    $lastConnectionException = $exception;

                    if ($attempt < $maxAttempts) {
                        $this->pauseBeforeRetry($attempt);

                        continue;
                    }

                    throw new RuntimeException('GEMINI_CONNECTION_FAILED', 0, $exception);
                }

                if ($response->successful()) {
                    break;
                }

                if ($this->isTransientStatus($response->status()) && $attempt < $maxAttempts) {
                    $this->pauseBeforeRetry($attempt);

                    continue;
                }

                throw new RuntimeException($this->errorCodeFor($response->status()));
            }

            if ($response === null) {
                throw new RuntimeException('GEMINI_CONNECTION_FAILED', 0, $lastConnectionException);
            }

            $parts = $response->json('candidates.0.content.parts');
            $text = collect(is_array($parts) ? $parts : [])
                ->filter(fn (mixed $part): bool => is_array($part) && is_string($part['text'] ?? null))
                ->pluck('text')
                ->implode('');
            $text = $this->normalizeModelText($text);

            if ($text === '') {
                throw new RuntimeException('GEMINI_MISSING_OUTPUT');
            }

            $this->circuitBreaker->recordSuccess();
            $this->requestRecorder->record(
                'consultant_chat',
                $model,
                'SUCCEEDED',
                $startedAt,
                $attemptCount,
            );

            return new ConsultantReply(mb_substr($text, 0, 2000));
        } catch (Throwable $exception) {
            $errorCode = $this->controlledErrorCode($exception);

            $this->circuitBreaker->recordFailure($errorCode);
            $this->requestRecorder->record(
                'consultant_chat',
                $model,
                'FAILED',
                $startedAt,
                $attemptCount,
                $errorCode,
            );

            if ($exception instanceof RuntimeException && $exception->getMessage() === $errorCode) {
                throw $exception;
            }

            throw new RuntimeException($errorCode, 0, $exception);
        }
    }

    /** @param array<int, array{role: string, content: string}> $messages */
    public function reply(array $messages, string $locale = 'en'): ConsultantReply
    {
        $emergencyReply = $this->emergencyReply($messages, $locale);

        if ($emergencyReply !== null) {
            return $emergencyReply;
        }

        $startedAt = hrtime(true);
        $attemptCount = 0;
        $model = (string) config('medipath.assessment.gemini.consultant_model');

        try {
            $this->circuitBreaker->beforeRequest();

            $apiKey = (string) config('medipath.assessment.gemini.api_key');

            if ($apiKey === '') {
                throw new RuntimeException('GEMINI_API_KEY_MISSING');
            }

            $response = null;
            $lastConnectionException = null;
            $maxAttempts = max(1, min(
                (int) config('medipath.assessment.gemini.consultant_max_attempts', 2),
                3,
            ));

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $attemptCount = $attempt;

                try {
                    $response = Http::acceptJson()
                        ->asJson()
                        ->withHeaders(['x-goog-api-key' => $apiKey])
                        ->connectTimeout((int) config('medipath.assessment.gemini.connect_timeout', 5))
                        ->timeout($this->replyTimeoutForAttempt($attempt))
                        ->post((string) config('medipath.assessment.gemini.endpoint'), [
                            'model' => $model,
                            'system_instruction' => $this->systemInstruction($locale),
                            'input' => json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                            'store' => false,
                            'generation_config' => [
                                'max_output_tokens' => max(80, min(
                                    (int) config('medipath.assessment.gemini.consultant_max_output_tokens', 320),
                                    500,
                                )),
                                'thinking_level' => (string) config(
                                    'medipath.assessment.gemini.consultant_thinking_level',
                                    'minimal',
                                ),
                            ],
                        ]);
                } catch (ConnectionException $exception) {
                    $lastConnectionException = $exception;

                    if ($attempt < $maxAttempts) {
                        $this->pauseBeforeRetry($attempt);

                        continue;
                    }

                    throw new RuntimeException('GEMINI_CONNECTION_FAILED', 0, $exception);
                }

                if ($response->successful()) {
                    break;
                }

                if ($this->isTransientStatus($response->status()) && $attempt < $maxAttempts) {
                    $this->pauseBeforeRetry($attempt);

                    continue;
                }

                Log::warning('Gemini consultant returned an HTTP error.', [
                    'status' => $response->status(),
                ]);

                throw new RuntimeException($this->errorCodeFor($response->status()));
            }

            if ($response === null) {
                throw new RuntimeException('GEMINI_CONNECTION_FAILED', 0, $lastConnectionException);
            }

            if (! $response->successful()) {
                throw new RuntimeException($this->errorCodeFor($response->status()));
            }

            $steps = $response->json('steps');

            if (! is_array($steps)) {
                throw new RuntimeException('GEMINI_MALFORMED_RESPONSE');
            }

            foreach (array_reverse($steps) as $step) {
                if (! is_array($step) || ($step['type'] ?? null) !== 'model_output') {
                    continue;
                }

                foreach (array_reverse(is_array($step['content'] ?? null) ? $step['content'] : []) as $content) {
                    if (is_array($content) && ($content['type'] ?? null) === 'text') {
                        $text = $this->normalizeModelText((string) ($content['text'] ?? ''));

                        if ($text !== '') {
                            $this->circuitBreaker->recordSuccess();
                            $this->requestRecorder->record(
                                'consultant_chat',
                                $model,
                                'SUCCEEDED',
                                $startedAt,
                                $attemptCount,
                            );

                            return new ConsultantReply(mb_substr($text, 0, 2000));
                        }
                    }
                }
            }

            throw new RuntimeException('GEMINI_MISSING_OUTPUT');
        } catch (Throwable $exception) {
            $errorCode = $this->controlledErrorCode($exception);

            $this->circuitBreaker->recordFailure($errorCode);
            $this->requestRecorder->record(
                'consultant_chat',
                $model,
                'FAILED',
                $startedAt,
                $attemptCount,
                $errorCode,
            );

            if ($exception instanceof RuntimeException && $exception->getMessage() === $errorCode) {
                throw $exception;
            }

            throw new RuntimeException($errorCode, 0, $exception);
        }
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  callable(string): void  $onDelta
     */
    public function streamReply(array $messages, callable $onDelta, string $locale = 'en'): ConsultantReply
    {
        $emergencyReply = $this->emergencyReply($messages, $locale);

        if ($emergencyReply !== null) {
            return $emergencyReply;
        }

        $startedAt = hrtime(true);
        $attemptCount = 0;
        $model = (string) config('medipath.assessment.gemini.consultant_model');

        try {
            $this->circuitBreaker->beforeRequest();
            $apiKey = (string) config('medipath.assessment.gemini.api_key');

            if ($apiKey === '') {
                throw new RuntimeException('GEMINI_API_KEY_MISSING');
            }

            $response = null;
            $lastConnectionException = null;
            $maxAttempts = max(1, min(
                (int) config('medipath.assessment.gemini.consultant_stream_max_attempts', 1),
                3,
            ));
            $endpoint = (string) config('medipath.assessment.gemini.endpoint');

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $attemptCount = $attempt;

                try {
                    $response = Http::accept('text/event-stream')
                        ->asJson()
                        ->withHeaders(['x-goog-api-key' => $apiKey])
                        ->withOptions(['stream' => true])
                        ->connectTimeout((int) config('medipath.assessment.gemini.connect_timeout', 5))
                        ->timeout($this->streamTimeoutForAttempt($attempt))
                        ->post($endpoint, [
                            'model' => $model,
                            'system_instruction' => $this->systemInstruction($locale),
                            'input' => json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                            'stream' => true,
                            'store' => false,
                            'generation_config' => [
                                'max_output_tokens' => max(80, min(
                                    (int) config('medipath.assessment.gemini.consultant_max_output_tokens', 320),
                                    500,
                                )),
                                'thinking_level' => (string) config(
                                    'medipath.assessment.gemini.consultant_thinking_level',
                                    'minimal',
                                ),
                            ],
                        ]);
                } catch (ConnectionException $exception) {
                    $lastConnectionException = $exception;

                    if ($attempt < $maxAttempts) {
                        $this->pauseBeforeRetry($attempt);

                        continue;
                    }

                    throw new RuntimeException('GEMINI_CONNECTION_FAILED', 0, $exception);
                }

                if ($response->successful()) {
                    break;
                }

                if ($this->isTransientStatus($response->status()) && $attempt < $maxAttempts) {
                    $this->pauseBeforeRetry($attempt);

                    continue;
                }

                throw new RuntimeException($this->errorCodeFor($response->status()));
            }

            if ($response === null) {
                throw new RuntimeException('GEMINI_CONNECTION_FAILED', 0, $lastConnectionException);
            }

            if (! $response->successful()) {
                throw new RuntimeException($this->errorCodeFor($response->status()));
            }

            $text = $this->consumeEventStream(
                $response->toPsrResponse()->getBody(),
                $onDelta,
            );
            $text = $this->normalizeModelText($text);

            if ($text === '') {
                throw new RuntimeException('GEMINI_MISSING_OUTPUT');
            }

            $this->circuitBreaker->recordSuccess();
            $this->requestRecorder->record(
                'consultant_chat_stream',
                $model,
                'SUCCEEDED',
                $startedAt,
                $attemptCount,
            );

            return new ConsultantReply(mb_substr($text, 0, 2000));
        } catch (Throwable $exception) {
            $errorCode = $this->controlledErrorCode($exception);

            $this->circuitBreaker->recordFailure($errorCode);
            $this->requestRecorder->record(
                'consultant_chat_stream',
                $model,
                'FAILED',
                $startedAt,
                $attemptCount,
                $errorCode,
            );

            if ($exception instanceof RuntimeException && $exception->getMessage() === $errorCode) {
                throw $exception;
            }

            throw new RuntimeException($errorCode, 0, $exception);
        }
    }

    private function controlledErrorCode(Throwable $exception): string
    {
        if ($exception instanceof ConnectionException) {
            return 'GEMINI_CONNECTION_FAILED';
        }

        if ($exception instanceof RuntimeException
            && preg_match('/^GEMINI_[A-Z0-9_]+$/', $exception->getMessage()) === 1) {
            return $exception->getMessage();
        }

        return 'GEMINI_UNEXPECTED_FAILURE';
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function emergencyReply(array $messages, string $locale): ?ConsultantReply
    {
        if (($messages[array_key_last($messages)] ?? null) === null) {
            throw new RuntimeException('EMPTY_CONVERSATION');
        }

        $redFlagsByCode = [];
        $firstAidSteps = [];

        foreach ($messages as $message) {
            if ($message['role'] !== 'user') {
                continue;
            }

            foreach ($this->emergencyScreener->screenText($message['content']) as $redFlag) {
                $redFlagsByCode[$redFlag->value] = $redFlag;
            }

            foreach ($this->firstAidGuidance->forText($message['content']) as $step) {
                $firstAidSteps[$step] = $step;
            }
        }

        $redFlags = array_values($redFlagsByCode);

        if ($redFlags === []) {
            return null;
        }

        return new ConsultantReply(
            content: $this->emergencyScreener->emergencyChatMessage(),
            emergency: true,
            redFlags: array_map(fn ($code): string => $code->value, $redFlags),
            firstAidSteps: $this->localizedFirstAidSteps(
                array_values($firstAidSteps),
                $locale,
            ),
        );
    }

    /**
     * @param  callable(string): void  $onDelta
     */
    private function consumeEventStream(StreamInterface $body, callable $onDelta): string
    {
        $buffer = '';
        $text = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);
            $this->consumeCompleteEvents($buffer, $text, $onDelta);
        }

        if (trim($buffer) !== '') {
            $buffer .= "\n\n";
            $this->consumeCompleteEvents($buffer, $text, $onDelta);
        }

        return $text;
    }

    /**
     * @param  callable(string): void  $onDelta
     */
    private function consumeCompleteEvents(string &$buffer, string &$text, callable $onDelta): void
    {
        while (preg_match('/\R\R/u', $buffer, $separator, PREG_OFFSET_CAPTURE) === 1) {
            $offset = $separator[0][1];
            $length = strlen($separator[0][0]);
            $event = substr($buffer, 0, $offset);
            $buffer = substr($buffer, $offset + $length);
            $dataLines = [];

            foreach (preg_split('/\R/u', $event) ?: [] as $line) {
                if (str_starts_with($line, 'data:')) {
                    $dataLines[] = ltrim(substr($line, 5));
                }
            }

            if ($dataLines === []) {
                continue;
            }

            try {
                $data = json_decode(implode("\n", $dataLines), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (! is_array($data)
                || ($data['event_type'] ?? null) !== 'step.delta'
                || ($data['delta']['type'] ?? null) !== 'text'
                || ! is_string($data['delta']['text'] ?? null)
                || $data['delta']['text'] === '') {
                continue;
            }

            $delta = $data['delta']['text'];
            $text .= $delta;
            $onDelta($delta);
        }
    }

    private function systemInstruction(string $locale): string
    {
        $emergencyPhone = (string) config('medipath.emergency.phone', '112');
        $languageInstruction = $locale === 'tw'
            ? 'The selected interface language is Ghanaian Twi. Write every user-visible sentence only in clear, natural Ghanaian Twi, including follow-up questions, warnings, and low-risk self-care guidance. Do not drift into English because earlier conversation messages are English. Keep medical terms simple; only include a familiar English clinical term in parentheses when that improves safety. Keep the emergency number 112 unchanged.'
            : 'Reply in clear English.';

        return <<<PROMPT
You are MediPath's AI health consultant for low-risk, non-emergency health guidance in a prototype. Be calm, concise, and conversational. Ask at most one useful follow-up question at a time. Never diagnose, suggest likely causes, list possible conditions, prescribe, change medication, claim certainty, or replace a clinician. Gather essential context before offering general low-risk self-care guidance. Do not repeat emergency disclaimers in routine low-risk replies. If the latest information could be urgent, tell the person to call local emergency services or seek immediate in-person care, do not ask another question, and stop the reply. Encourage speaking with a real clinician when the situation is uncertain, persistent, worsening, or needs examination. Output plain text only: never emit HTML, HTML entities, or Markdown tables. Keep each reply under 100 words.

This service is for Ghana. The configured emergency number is {$emergencyPhone}. Never mention 911, 999, or another country's emergency number. Do not give food, drink, or medication instructions for a possible emergency; direct the person to immediate professional help instead.

{$languageInstruction}
PROMPT;
    }

    private function normalizeModelText(string $text): string
    {
        $normalized = preg_replace('/(?:&#x0*20;|&#0*32;|&nbsp;)/iu', ' ', $text) ?? $text;
        $normalized = preg_replace('/[ \t]+\R/u', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('/[ \t]{2,}/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function errorCodeFor(int $status): string
    {
        return match ($status) {
            408 => 'GEMINI_CONNECTION_FAILED',
            429 => 'GEMINI_RATE_LIMITED',
            503 => 'GEMINI_SERVICE_UNAVAILABLE',
            default => 'GEMINI_HTTP_'.$status,
        };
    }

    private function isTransientStatus(int $status): bool
    {
        return $status === 408 || $status === 429 || $status >= 500;
    }

    private function streamTimeoutForAttempt(int $attempt): int
    {
        if ($attempt === 1) {
            return max(5, (int) config(
                'medipath.assessment.gemini.consultant_stream_initial_timeout',
                12,
            ));
        }

        return max(10, (int) config('medipath.assessment.gemini.consultant_timeout', 30));
    }

    private function replyTimeoutForAttempt(int $attempt): int
    {
        if ($attempt === 1) {
            return max(5, (int) config(
                'medipath.assessment.gemini.consultant_initial_timeout',
                12,
            ));
        }

        return max(10, (int) config('medipath.assessment.gemini.consultant_timeout', 30));
    }

    /**
     * @param  array<int, string>  $steps
     * @return array<int, string>
     */
    private function localizedFirstAidSteps(array $steps, string $locale): array
    {
        if ($locale !== 'tw') {
            return $steps;
        }

        $translations = [
            'Use a clean cloth or dressing to apply firm, continuous pressure to the wound.' => __('consultant.first_aid.bleeding_pressure'),
            'If blood soaks through, add another cloth or dressing on top. Keep the first one in place.' => __('consultant.first_aid.bleeding_layers'),
            'If something is stuck in the wound, leave it in place and press around it, not on it.' => __('consultant.first_aid.embedded_object'),
            'Keep the injured limb still. Do not straighten it or put weight on it.' => __('consultant.first_aid.fracture_still'),
            'Do not move the person unless the area is unsafe.' => __('consultant.first_aid.unsafe_area'),
            'Keep the person comfortable and follow the 112 dispatcher\'s instructions.' => __('consultant.first_aid.dispatcher'),
        ];

        return array_map(
            fn (string $step): string => $translations[$step] ?? $step,
            $steps,
        );
    }

    private function pauseBeforeRetry(int $attempt): void
    {
        $baseMilliseconds = max(0, (int) config('medipath.assessment.gemini.retry_delay_ms', 250));

        if ($baseMilliseconds === 0) {
            return;
        }

        $exponentialDelay = $baseMilliseconds * (2 ** max(0, $attempt - 1));
        $jitter = random_int(0, max(1, intdiv($baseMilliseconds, 4)));

        usleep(($exponentialDelay + $jitter) * 1000);
    }
}
