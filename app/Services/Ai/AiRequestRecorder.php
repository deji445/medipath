<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AiRequestRecorder
{
    public function recordDeferred(
        string $feature,
        string $model,
        string $outcome,
        int $startedAtNanoseconds,
        int $attemptCount,
        ?string $errorCode = null,
    ): void {
        if (app()->runningUnitTests()) {
            $this->record(
                $feature,
                $model,
                $outcome,
                $startedAtNanoseconds,
                $attemptCount,
                $errorCode,
            );

            return;
        }

        defer(fn () => $this->record(
            $feature,
            $model,
            $outcome,
            $startedAtNanoseconds,
            $attemptCount,
            $errorCode,
        ))->always();
    }

    public function record(
        string $feature,
        string $model,
        string $outcome,
        int $startedAtNanoseconds,
        int $attemptCount,
        ?string $errorCode = null,
    ): void {
        try {
            DB::table('ai_request_events')->insert([
                'feature' => mb_substr($feature, 0, 60),
                'provider' => 'gemini',
                'model' => mb_substr($model, 0, 120),
                'outcome' => $outcome === 'SUCCEEDED' ? 'SUCCEEDED' : 'FAILED',
                'duration_ms' => max(0, (int) round((hrtime(true) - $startedAtNanoseconds) / 1_000_000)),
                'attempt_count' => max(0, min($attemptCount, 10)),
                'retry_count' => max(0, min($attemptCount - 1, 9)),
                'error_code' => $this->safeErrorCode($errorCode),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('AI request metric could not be recorded.', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function safeErrorCode(?string $errorCode): ?string
    {
        if ($errorCode === null || $errorCode === '') {
            return null;
        }

        if (preg_match('/^[A-Z][A-Z0-9_]{0,99}$/', $errorCode) !== 1) {
            return 'UNCLASSIFIED_AI_FAILURE';
        }

        return $errorCode;
    }
}
