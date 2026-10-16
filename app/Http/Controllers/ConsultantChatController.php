<?php

namespace App\Http\Controllers;

use App\Services\Assessment\DeterministicEmergencyScreener;
use App\Services\Consultant\GeminiConsultant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ConsultantChatController extends Controller
{
    public function stream(Request $request, GeminiConsultant $consultant): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ], [
            'message.required' => __('consultant.validation.required'),
            'message.max' => __('consultant.validation.max'),
        ]);
        $messages = $this->sessionMessages($request);
        $emergencyLocked = collect($messages)->contains(
            fn (array $message): bool => $message['role'] === 'assistant'
                && $message['emergency'] === true,
        );

        if (! $emergencyLocked) {
            $messages[] = [
                'role' => 'user',
                'content' => trim($validated['message']),
                'emergency' => false,
                'red_flags' => [],
                'first_aid_steps' => [],
            ];
        }

        return response()->stream(function () use ($request, $consultant, $messages, $emergencyLocked): void {
            $send = static function (string $event, array $data): void {
                echo 'event: '.$event."\n";
                echo 'data: '.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            if ($emergencyLocked) {
                $send('complete', [
                    'messages' => $messages,
                    'emergencyContact' => $this->emergencyContact(),
                ]);

                return;
            }

            $providerMessages = array_map(
                fn (array $message): array => [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ],
                $messages,
            );
            $send('started', ['messageCount' => count($messages)]);
            try {
                $reply = $consultant->quickReply($providerMessages, app()->getLocale());
                $send('delta', ['text' => $reply->content]);
            } catch (Throwable $exception) {
                $errorCode = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'GEMINI_UNEXPECTED_FAILURE';
                Log::warning('AI consultant request failed.', [
                    'error_code' => $errorCode,
                    'exception' => $exception::class,
                ]);
                $send('error', ['message' => $this->errorMessage($errorCode)]);

                return;
            }

            $completedMessages = [...$messages, [
                'role' => 'assistant',
                'content' => $reply->content,
                'emergency' => $reply->emergency,
                'red_flags' => $reply->redFlags,
                'first_aid_steps' => $reply->firstAidSteps,
            ]];
            $completedMessages = array_slice($completedMessages, -12);
            $request->session()->put('consultant_chat', $completedMessages);
            $request->session()->save();
            $send('complete', [
                'messages' => $completedMessages,
                'emergencyContact' => $this->emergencyContact(),
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function startIntake(
        Request $request,
        DeterministicEmergencyScreener $emergencyScreener,
    ): RedirectResponse {
        $storedMessages = $request->session()->get('consultant_chat', []);
        $messages = is_array($storedMessages) ? $storedMessages : [];
        $emergencyLocked = collect($messages)->contains(
            fn (mixed $message): bool => is_array($message)
                && ($message['role'] ?? null) === 'assistant'
                && ($message['emergency'] ?? false) === true,
        );
        $patientMessages = collect($messages)
            ->filter(fn (mixed $message): bool => is_array($message)
                && ($message['role'] ?? null) === 'user'
                && is_string($message['content'] ?? null))
            ->map(fn (array $message): string => trim($message['content']))
            ->filter()
            ->values();
        $mainComplaint = mb_substr($patientMessages->implode(' '), 0, 500);

        if ($emergencyLocked || $emergencyScreener->screenText($mainComplaint) !== []) {
            $request->session()->forget('intake.chat_handoff');

            return to_route('emergency');
        }

        if ($mainComplaint === '') {
            return to_route('home', ['consult' => 'ai'])
                ->withErrors(['chat_handoff' => 'Describe the concern in the chat before starting intake.']);
        }

        $request->session()->put('intake.chat_handoff', [
            'main_complaint' => $mainComplaint,
            'message_count' => $patientMessages->count(),
            'started_at' => now()->toIso8601String(),
        ]);

        return to_route('intake.start');
    }

    public function store(Request $request, GeminiConsultant $consultant): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ], [
            'message.required' => __('consultant.validation.required'),
            'message.max' => __('consultant.validation.max'),
        ]);

        $storedMessages = $request->session()->get('consultant_chat', []);
        $messages = [];

        if (is_array($storedMessages)) {
            foreach (array_slice($storedMessages, -10) as $item) {
                if (! is_array($item)
                    || ! in_array($item['role'] ?? null, ['user', 'assistant'], true)
                    || ! is_string($item['content'] ?? null)) {
                    continue;
                }

                $messages[] = [
                    'role' => (string) $item['role'],
                    'content' => $item['content'],
                    'emergency' => $item['role'] === 'assistant'
                        && ($item['emergency'] ?? false) === true,
                    'red_flags' => is_array($item['red_flags'] ?? null)
                        ? array_values(array_filter($item['red_flags'], 'is_string'))
                        : [],
                    'first_aid_steps' => is_array($item['first_aid_steps'] ?? null)
                        ? array_values(array_filter($item['first_aid_steps'], 'is_string'))
                        : [],
                ];
            }
        }

        $emergencyLocked = collect($messages)->contains(
            fn (array $message): bool => $message['role'] === 'assistant'
                && $message['emergency'] === true,
        );

        if ($emergencyLocked) {
            if ($request->expectsJson()) {
                return response()->json([
                    'messages' => $messages,
                    'emergencyContact' => [
                        'label' => config('medipath.emergency.contact_label'),
                        'phone' => config('medipath.emergency.phone'),
                    ],
                ]);
            }

            return to_route('home', ['consult' => 'ai']);
        }

        $messages[] = [
            'role' => 'user',
            'content' => trim($validated['message']),
            'emergency' => false,
            'red_flags' => [],
            'first_aid_steps' => [],
        ];

        $providerMessages = array_map(
            fn (array $message): array => [
                'role' => $message['role'],
                'content' => $message['content'],
            ],
            $messages,
        );

        try {
            $reply = $consultant->reply($providerMessages, app()->getLocale());
        } catch (RuntimeException $exception) {
            Log::warning('AI consultant request failed.', [
                'error_code' => $exception->getMessage(),
                'exception' => $exception::class,
                'cause' => mb_substr((string) $exception->getPrevious()?->getMessage(), 0, 500),
            ]);

            $message = match ($exception->getMessage()) {
                'GEMINI_CONNECTION_FAILED' => __('consultant.errors.connection'),
                'GEMINI_SERVICE_UNAVAILABLE' => __('consultant.errors.unavailable'),
                'GEMINI_RATE_LIMITED' => __('consultant.errors.rate_limited'),
                'GEMINI_API_KEY_MISSING' => __('consultant.errors.not_configured'),
                'GEMINI_CIRCUIT_OPEN' => __('consultant.errors.circuit_open'),
                default => __('consultant.errors.default'),
            };

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => ['message' => [$message]],
                ], 503);
            }

            return back()->withErrors(['message' => $message])->withInput();
        }

        $messages[] = [
            'role' => 'assistant',
            'content' => $reply->content,
            'emergency' => $reply->emergency,
            'red_flags' => $reply->redFlags,
            'first_aid_steps' => $reply->firstAidSteps,
        ];
        $messages = array_slice($messages, -12);
        $request->session()->put('consultant_chat', $messages);

        if ($request->expectsJson()) {
            return response()->json([
                'messages' => $messages,
                'emergencyContact' => [
                    'label' => config('medipath.emergency.contact_label'),
                    'phone' => config('medipath.emergency.phone'),
                ],
            ]);
        }

        return to_route('home', ['consult' => 'ai']);
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $request->session()->forget('consultant_chat');

        if ($request->expectsJson()) {
            return response()->json(['messages' => []]);
        }

        return to_route('home', ['consult' => 'ai']);
    }

    /** @return array<int, array{role: string, content: string, emergency: bool, red_flags: array<int, string>, first_aid_steps: array<int, string>}> */
    private function sessionMessages(Request $request): array
    {
        $storedMessages = $request->session()->get('consultant_chat', []);
        $messages = [];

        if (! is_array($storedMessages)) {
            return $messages;
        }

        foreach (array_slice($storedMessages, -10) as $item) {
            if (! is_array($item)
                || ! in_array($item['role'] ?? null, ['user', 'assistant'], true)
                || ! is_string($item['content'] ?? null)) {
                continue;
            }

            $messages[] = [
                'role' => (string) $item['role'],
                'content' => $item['content'],
                'emergency' => $item['role'] === 'assistant'
                    && ($item['emergency'] ?? false) === true,
                'red_flags' => is_array($item['red_flags'] ?? null)
                    ? array_values(array_filter($item['red_flags'], 'is_string'))
                    : [],
                'first_aid_steps' => is_array($item['first_aid_steps'] ?? null)
                    ? array_values(array_filter($item['first_aid_steps'], 'is_string'))
                    : [],
            ];
        }

        return $messages;
    }

    /** @return array{label: mixed, phone: mixed} */
    private function emergencyContact(): array
    {
        return [
            'label' => config('medipath.emergency.contact_label'),
            'phone' => config('medipath.emergency.phone'),
        ];
    }

    private function errorMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'GEMINI_CONNECTION_FAILED' => __('consultant.errors.connection'),
            'GEMINI_SERVICE_UNAVAILABLE' => __('consultant.errors.unavailable'),
            'GEMINI_RATE_LIMITED' => __('consultant.errors.rate_limited'),
            'GEMINI_API_KEY_MISSING' => __('consultant.errors.not_configured'),
            'GEMINI_CIRCUIT_OPEN' => __('consultant.errors.circuit_open'),
            default => __('consultant.errors.default'),
        };
    }
}
