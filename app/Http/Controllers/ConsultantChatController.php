<?php

namespace App\Http\Controllers;

use App\Services\Consultant\GeminiConsultant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ConsultantChatController extends Controller
{
    public function store(Request $request, GeminiConsultant $consultant): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ], [
            'message.required' => 'Type a message for the AI consultant.',
            'message.max' => 'Keep the message under 1,000 characters.',
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
                ];
            }
        }

        $messages[] = ['role' => 'user', 'content' => trim($validated['message'])];

        try {
            $reply = $consultant->reply($messages);
        } catch (RuntimeException $exception) {
            Log::warning('AI consultant request failed.', [
                'error_code' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            $message = match ($exception->getMessage()) {
                'GEMINI_CONNECTION_FAILED' => 'The AI consultant did not respond in time. Check your connection and try again.',
                'GEMINI_SERVICE_UNAVAILABLE' => 'Gemini is temporarily busy. Please try again in a moment.',
                'GEMINI_RATE_LIMITED' => 'Gemini received too many requests in a short period. Please wait a minute and try again.',
                'GEMINI_API_KEY_MISSING' => 'The AI consultant has not been configured yet. You can request a real consultant instead.',
                default => 'The AI consultant is unavailable right now. Try again, or request a real consultant.',
            };

            return back()->withErrors([
                'message' => $message,
            ])->withInput();
        }

        $messages[] = ['role' => 'assistant', 'content' => $reply];
        $request->session()->put('consultant_chat', array_slice($messages, -12));

        return to_route('home', ['consult' => 'ai']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('consultant_chat');

        return to_route('home', ['consult' => 'ai']);
    }
}
