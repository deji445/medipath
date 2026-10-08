<?php

namespace App\Services\Consultant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GeminiConsultant
{
    /** @param array<int, array{role: string, content: string}> $messages */
    public function reply(array $messages): string
    {
        $apiKey = (string) config('medipath.assessment.gemini.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY_MISSING');
        }

        $lastMessage = $messages[array_key_last($messages)] ?? null;

        if ($lastMessage === null) {
            throw new RuntimeException('EMPTY_CONVERSATION');
        }

        if ($this->mayBeEmergency($lastMessage['content'])) {
            return 'This may need urgent help. Call 112 or go to the nearest emergency facility now. Do not wait for this chat. You can also use MediPath’s emergency finder.';
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->connectTimeout((int) config('medipath.assessment.gemini.connect_timeout', 5))
                ->timeout((int) config('medipath.assessment.gemini.timeout', 20))
                ->post((string) config('medipath.assessment.gemini.endpoint'), [
                    'model' => (string) config('medipath.assessment.gemini.model'),
                    'system_instruction' => $this->systemInstruction(),
                    'input' => json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    'store' => false,
                    'generation_config' => [
                        'max_output_tokens' => 500,
                        'thinking_level' => 'low',
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('GEMINI_CONNECTION_FAILED', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('GEMINI_HTTP_'.$response->status());
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
                    $text = trim((string) ($content['text'] ?? ''));

                    if ($text !== '') {
                        return mb_substr($text, 0, 2000);
                    }
                }
            }
        }

        throw new RuntimeException('GEMINI_MISSING_OUTPUT');
    }

    private function mayBeEmergency(string $message): bool
    {
        return preg_match('/\b(can(?:not|\'t) breathe|chest pain|unconscious|seizure|heavy bleeding|stroke|suicid|overdose|poison|severe allergic|anaphyl)/i', $message) === 1;
    }

    private function systemInstruction(): string
    {
        return <<<'PROMPT'
You are MediPath's AI health consultant for low-risk, non-emergency health guidance in a prototype. Be calm, concise, and conversational. Ask at most one useful follow-up question at a time. Never diagnose, prescribe, change medication, claim certainty, or replace a clinician. Give plain-language self-care guidance only when it is low risk. If symptoms could be urgent, tell the person to call local emergency services or seek immediate in-person care. Encourage speaking with a real clinician when the situation is uncertain, persistent, worsening, or needs examination. Do not use Markdown tables. Keep each reply under 120 words.
PROMPT;
    }
}
